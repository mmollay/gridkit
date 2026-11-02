#!/usr/bin/env node
/**
 * Does the demo show everything GridKit has?
 *
 * css/blocks.json lists every `gk-` class and the release that brought it. This opens the demo,
 * visits each of its sections, triggers what opens on a click (the toasts, a confirmation, the
 * lightbox) and collects the classes that are in the DOM. A class that is in blocks.json and
 * nowhere on the page fails — unless ci/demo-coverage-allow.json says why it is not shown. An
 * allowed class that IS shown fails too, so the list cannot go stale.
 *
 *     node ci/demo-coverage.js                     # needs the "playwright" package, and php
 *     node ci/demo-coverage.js --update            # rewrite the allow list from what is missing now
 *
 * Exit code 0 when every class is shown or allowed.
 */
const { spawn } = require("child_process");
const fs = require("fs");
const path = require("path");

let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}

const root = path.join(__dirname, "..");
const allowFile = path.join(__dirname, "demo-coverage-allow.json");
const update = process.argv.includes("--update");
const port = 8300 + Math.floor(Math.random() * 500);

const server = spawn(process.env.GK_PHP || "php", ["-S", "127.0.0.1:" + port, "-t", root], { stdio: "ignore" });
process.on("exit", () => server.kill());

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  await sleep(1200);
  const blocks = JSON.parse(fs.readFileSync(path.join(root, "css/blocks.json"), "utf8")).blocks;
  const browser = await chromium.launch();
  const seen = new Set();
  const errors = [];
  try {
    const page = await (await browser.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto(`http://127.0.0.1:${port}/demo/`, { waitUntil: "load" });
    await sleep(600);

    const collect = async () => {
      (await page.evaluate(() => {
        const out = new Set();
        document.querySelectorAll("*").forEach((e) => {
          if (e.closest(".demo-section:not(.active)")) return;
          e.classList.forEach((c) => out.add(c));
        });
        return [...out];
      })).forEach((c) => seen.add(c));
    };

    const sections = await page.evaluate(() => [...document.querySelectorAll(".demo-section")].map((s) => s.dataset.section));
    for (const s of sections) {
      await page.evaluate((id) => { location.hash = "#" + id; }, s);
      await sleep(250);
      await collect();
      if (s === "feedback") {
        // What opens on a click: every toast, a confirmation, the dialogs the page has a button for.
        for (const sel of ['[onclick*="GK.toast"]', '[onclick*="GK.confirm"]', '[onclick*="GK.modal"]', "[data-gk-lightbox]", "[data-gk-modal]"]) {
          const n = await page.locator(`.demo-section.active ${sel}`).count();
          for (let i = 0; i < Math.min(n, 6); i++) {
            await page.locator(`.demo-section.active ${sel}`).nth(i).click({ timeout: 2000 }).catch(() => {});
            await sleep(120);
            await collect();
            await page.keyboard.press("Escape");
            await sleep(60);
          }
        }
      }
    }
    // The lightbox, from the gallery.
    await page.evaluate(() => { location.hash = "#gallery"; });
    await sleep(250);
    await page.locator(".demo-section.active .gk-gallery img").first().click({ timeout: 2000 }).catch(() => {});
    await sleep(300);
    await collect();
  } finally {
    await browser.close();
  }

  const allow = fs.existsSync(allowFile) ? JSON.parse(fs.readFileSync(allowFile, "utf8")) : {};
  const missing = [];
  let total = 0;
  for (const [key, b] of Object.entries(blocks)) {
    for (const c of Object.keys(b.classes || {})) {
      total++;
      if (!seen.has(c)) missing.push([key, c]);
    }
  }

  if (update) {
    const next = {};
    for (const [key, c] of missing) (next[key] = next[key] || []).push(c);
    const kept = {};
    for (const key of Object.keys(next).sort()) kept[key] = { reason: (allow[key] && allow[key].reason) || "TODO: say why the demo does not show these", classes: next[key] };
    fs.writeFileSync(allowFile, JSON.stringify(kept, null, 2) + "\n");
    console.log(`wrote ${path.relative(root, allowFile)}: ${missing.length} of ${total} classes not shown`);
    process.exit(0);
  }

  const allowed = new Set(Object.values(allow).flatMap((a) => a.classes));
  const notShown = missing.filter(([, c]) => !allowed.has(c));
  const stale = [...allowed].filter((c) => seen.has(c));
  const unreasoned = Object.entries(allow).filter(([, a]) => !a.reason || /^TODO/.test(a.reason)).map(([k]) => k);

  console.log(`${total - missing.length} of ${total} classes are in the demo, ${missing.length - notShown.length} are allowed to be missing`);
  let failed = 0;
  if (notShown.length) {
    failed++;
    console.log(`\nFAIL  ${notShown.length} class(es) in css/blocks.json are not in the demo and not allowed:`);
    for (const [k, c] of notShown.slice(0, 40)) console.log(`        ${k}: ${c}`);
  }
  if (stale.length) { failed++; console.log(`\nFAIL  ${stale.length} allowed class(es) are in the demo now — take them off the list:\n        ${stale.join(", ")}`); }
  if (unreasoned.length) { failed++; console.log(`\nFAIL  no reason given for: ${unreasoned.join(", ")}`); }
  if (errors.length) { failed++; console.log("\nFAIL  script errors on the demo:\n        " + errors.join("\n        ")); }
  console.log(failed ? `\n${failed} problem(s)` : "\nok");
  process.exit(failed ? 1 : 0);
})();
