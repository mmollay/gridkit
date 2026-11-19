#!/usr/bin/env node
/**
 * The view toggle (1.132.0) in a real browser: the default drawing, a click, the keyboard, two scopes
 * of one id, a choice that survives a reload, a stored name that no button knows, storage that throws,
 * content that arrives later (GK.initContent), a scope without a toggle, a phone, and the page
 * without the script.
 *
 * The page is ci/browser-view-fixture.php: toggle "lists" (default table) for the scopes a and b,
 * toggle "solo" (default cards) for c, scope d without a toggle, and #later-host.
 *
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-view.js
 *
 * Exit code 0 when every case holds.
 */
const { execFileSync } = require("child_process");
const fs = require("fs");
const os = require("os");
const path = require("path");

let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}

const php = process.env.GK_PHP || "php";
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-view-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const build = (name, args) => {
  const file = path.join(dir, name + ".html");
  fs.writeFileSync(file, execFileSync(php, [path.join(__dirname, "browser-view-fixture.php"), ...args], { maxBuffer: 64 * 1024 * 1024 }));
  return "file://" + file;
};
const PAGE = build("page", []);
const NOJS = build("nojs", ["--no-js"]);

let failures = 0;
const check = (id, ok, what) => {
  if (!ok) failures++;
  console.log((ok ? "ok   " : "FAIL ") + id + " " + what);
};

/** For one scope: which drawing is shown ("table", "cards", "both", "none") */
const shown = (page, scope) => page.evaluate((s) => {
  const el = document.getElementById("s-" + s);
  const t = !el.querySelector('[data-gk-view="table"]').hidden;
  const c = !el.querySelector('[data-gk-view="cards"]').hidden;
  return t && c ? "both" : t ? "table" : c ? "cards" : "none";
}, scope);
const pressed = (page, id) => page.evaluate((i) => {
  return Array.from(document.querySelectorAll('[data-gk-view-toggle="' + i + '"] [aria-pressed="true"]')).map((b) => b.getAttribute("data-gk-set-view"));
}, id);

(async () => {
  const browser = await chromium.launch();
  const open = async (url, opts = {}, init) => {
    const ctx = await browser.newContext({ viewport: { width: 1200, height: 800 }, ...opts });
    if (init) await ctx.addInitScript(init);
    const page = await ctx.newPage();
    const errors = [];
    page.on("pageerror", (e) => errors.push(String(e)));
    page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });
    await page.goto(url);
    return { ctx, page, errors };
  };

  // V01 — the defaults: lists = table, solo = cards; the toggles are shown; a scope without a toggle is left alone
  {
    const { ctx, page, errors } = await open(PAGE);
    check("V01a", (await shown(page, "a")) === "table" && (await shown(page, "b")) === "table", "lists start at the table");
    check("V01b", (await shown(page, "c")) === "cards", "solo starts at the cards (its own default)");
    check("V01c", (await shown(page, "d")) === "table", "a scope without a toggle keeps what the server wrote");
    check("V01d", (await pressed(page, "lists")).join() === "table" && (await pressed(page, "solo")).join() === "cards", "aria-pressed follows the drawing");
    check("V01e", await page.evaluate(() => Array.from(document.querySelectorAll("[data-gk-view-toggle]")).every((g) => !g.hidden)), "the toggles are shown");
    check("V01f", (await page.getAttribute("#s-a", "data-gk-view-active")) === "table", "data-gk-view-active on the scope");

    // V02 — a click: both scopes of the id switch, the other id does not, the event carries id and view
    await page.evaluate(() => { window.__ev = []; document.addEventListener("gk:viewchange", (e) => window.__ev.push([e.target.id, e.detail.id, e.detail.view])); });
    await page.click('[data-gk-view-toggle="lists"] [data-gk-set-view="cards"]');
    check("V02a", (await shown(page, "a")) === "cards" && (await shown(page, "b")) === "cards", "a click switches both scopes of the id");
    check("V02b", (await shown(page, "c")) === "cards" && (await shown(page, "d")) === "table", "other ids stay");
    check("V02c", (await pressed(page, "lists")).join() === "cards", "the pressed button moves");
    const ev = await page.evaluate(() => window.__ev);
    check("V02d", ev.length === 2 && ev.every((e) => e[1] === "lists" && e[2] === "cards"), "gk:viewchange once per scope with { id, view }: " + JSON.stringify(ev));
    check("V02e", await page.evaluate(() => GK.view.get("lists")) === "cards", "GK.view.get");

    // V03 — the keyboard: a button is a button; the focus ring shows
    await page.focus('[data-gk-view-toggle="lists"] [data-gk-set-view="table"]');
    await page.keyboard.press("Enter");
    check("V03a", (await shown(page, "a")) === "table", "Enter on a focused button switches");
    await page.keyboard.press("Tab");
    await page.keyboard.press("Space");
    check("V03b", (await shown(page, "a")) === "cards", "Tab, then Space on the next button switches");
    const ring = await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
    check("V03c", ring !== "none", "the focused button has an outline (" + ring + ")");
    check("V03d", await page.evaluate(() => GK.view.set("lists", "bogus") === undefined && GK.view.get("lists") === "cards"), "an unknown name is refused");

    // V04 — it survives a reload (the same context, the same storage)
    await page.reload();
    check("V04a", (await shown(page, "a")) === "cards" && (await pressed(page, "lists")).join() === "cards", "the choice is remembered");
    check("V04b", (await shown(page, "c")) === "cards", "solo is untouched");

    // V05 — content that arrives later
    await page.evaluate(() => { document.getElementById("later-host").innerHTML = JSON.parse(document.getElementById("gk-fixture").textContent).later; GK.initContent(document.getElementById("later-host")); });
    check("V05", (await shown(page, "later")) === "cards", "a scope handed to GK.initContent follows the stored choice (cards, though the server wrote table)");

    // V06 — a phone: no sideways scroll in either drawing
    await page.setViewportSize({ width: 390, height: 800 });
    for (const view of ["table", "cards"]) {
      await page.click('[data-gk-view-toggle="lists"] [data-gk-set-view="' + view + '"]');
      const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      check("V06" + view[0], over <= 0, "390 px, " + view + ": no sideways scroll (" + over + ")");
    }
    check("V00a", errors.length === 0, "no console or page errors" + (errors.length ? ": " + errors.join(" | ") : ""));
    await ctx.close();
  }

  // V07 — a stored name that no button knows: the default stands
  {
    const { ctx, page, errors } = await open(PAGE, {}, () => {
      try { localStorage.setItem("gk-view-lists", "bogus"); } catch (e) {}
    });
    check("V07", (await shown(page, "a")) === "table" && errors.length === 0, "an unknown stored name falls back to the default");
    await ctx.close();
  }

  // V08 — storage that throws: the toggle still works for this page view
  {
    const { ctx, page, errors } = await open(PAGE, {}, () => {
      const boom = () => { throw new Error("blocked"); };
      Storage.prototype.getItem = boom;
      Storage.prototype.setItem = boom;
    });
    await page.click('[data-gk-view-toggle="lists"] [data-gk-set-view="cards"]');
    check("V08", (await shown(page, "a")) === "cards" && errors.length === 0, "a click works without storage" + (errors.length ? ": " + errors.join(" | ") : ""));
    await ctx.close();
  }

  // V09 — without the script: the server's drawing stands, the toggle stays hidden
  {
    const { ctx, page } = await open(NOJS);
    check("V09", (await shown(page, "a")) === "table" && (await page.evaluate(() => document.querySelector("[data-gk-view-toggle]").offsetParent === null)), "no script: the default is visible, the toggle is not offered");
    await ctx.close();
  }

  await browser.close();
  console.log(failures === 0 ? "ALL OK" : failures + " FAILED");
  process.exit(failures === 0 ? 0 : 1);
})();
