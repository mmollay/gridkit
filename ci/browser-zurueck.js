#!/usr/bin/env node
/**
 * Back keeps the list (1.128.0) and the locked row button, in a real browser.
 *
 *   Z  a setData() table: search "LoA", a filter, a page; go to a row's target; Back. The box,
 *      the select and the page are as they were left AND the rows are the ones they promise —
 *      not every row under a refilled box. A fresh visit (new history entry) starts clean.
 *   R  a RowPager with a search box: the same.
 *   N  GK.navigate's gkNav and other history.state keys survive the writes.
 *   L  lockedIf: the lock in the delete's place, aria-disabled, named "Löschen — reason", no
 *      action/params/question; a click shows the reason; after a sort (client re-render) the same.
 *
 * The page is ci/browser-zurueck-fixture.php, served over http with Cache-Control: no-store.
 *
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-zurueck.js
 *
 * GK_CSS / GK_JS are handed to the fixture (the counter-check runs the old script). Exit 0 when all hold.
 */
const { execFileSync } = require("child_process");
let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}
const path = require("path");
const php = process.env.GK_PHP || "php";
const html = execFileSync(php, [path.join(__dirname, "browser-zurueck-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }).toString();
const BASE = "http://gk.test/";
const shot = process.env.GK_SHOT || "";

let passed = 0;
const failures = [];
function check(name, ok, detail) {
  if (ok) passed++;
  else failures.push(name + (detail === undefined ? "" : "  " + JSON.stringify(detail).slice(0, 400)));
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const view = (page) => page.evaluate(() => {
  const w = document.querySelector('[data-gk-table="kunden"]');
  const rows = Array.from(w.querySelectorAll("tbody tr")).map((r) => r.textContent.trim()).filter((t) => t);
  const liste = Array.from(document.querySelectorAll("#liste tbody tr")).filter((r) => r.style.display !== "none").map((r) => r.textContent.trim());
  return {
    q: w.querySelector("[data-gk-search]").value,
    city: w.querySelector('[data-gk-filter="city"]').value,
    rows,
    page: (w.querySelector(".gk-pg-active") || {}).textContent || "",
    lq: document.getElementById("such").value,
    liste,
    state: history.state,
  };
});

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.route(BASE + "**", (route) => {
    const u = new URL(route.request().url());
    const body = u.pathname.endsWith("ziel.html") ? "<!doctype html><title>Ziel</title><p>Ziel</p>" : html;
    route.fulfill({ status: 200, contentType: "text/html; charset=utf-8", headers: { "Cache-Control": "no-store, no-cache, must-revalidate" }, body });
  });
  await page.goto(BASE + "liste.html");
  await sleep(150);

  // Z + R: narrow both lists, then leave and come back.
  await page.fill('[data-gk-table="kunden"] [data-gk-search]', "LoA");
  await page.fill("#such", "loa");
  await sleep(450);
  const before = await view(page);
  check("Z search narrows to the three LoA rows", before.rows.length === 3 && before.rows.every((t) => t.includes("LoA")), before.rows);
  check("R search narrows to the three LoA entries", before.liste.length === 3, before.liste);
  check("N state written under gkTable:kunden", before.state && before.state["gkTable:kunden"] && before.state["gkTable:kunden"].q === "LoA", before.state);

  await page.evaluate(() => history.replaceState(Object.assign({}, history.state, { gkNav: true }), ""));
  await page.click('[data-gk-table="kunden"] tbody a[href*="ziel.html"]');
  await page.waitForURL(/ziel\.html/);
  await page.goBack();
  await sleep(250);
  const back = await view(page);
  check("Z after Back the box says LoA", back.q === "LoA", back.q);
  check("Z after Back the rows follow the box (3, all LoA)", back.rows.length === 3 && back.rows.every((t) => t.includes("LoA")), back.rows);
  check("R after Back the box says loa", back.lq === "loa", back.lq);
  check("R after Back the list follows the box (3)", back.liste.length === 3, back.liste);
  check("N gkNav kept", back.state && back.state.gkNav === true, back.state);

  // Filter + page: clear the search, filter Wien (10 rows), page 2, leave, Back.
  await page.fill('[data-gk-table="kunden"] [data-gk-search]', "");
  await sleep(400);
  await page.selectOption('[data-gk-table="kunden"] [data-gk-filter="city"]', "Wien");
  await sleep(100);
  await page.click('[data-gk-table="kunden"] [data-gk-page="2"]');
  await sleep(100);
  const filt = await view(page);
  await page.click('[data-gk-table="kunden"] tbody a[href*="ziel.html"]');
  await page.waitForURL(/ziel\.html/);
  await page.goBack();
  await sleep(250);
  const back2 = await view(page);
  check("Z filter kept after Back", back2.city === "Wien", back2.city);
  check("Z page 2 kept after Back", back2.page === "2" && JSON.stringify(back2.rows) === JSON.stringify(filt.rows), [back2.page, back2.rows, filt.rows]);

  // A fresh visit is a new history entry: clean.
  await page.goto(BASE + "liste.html?neu=1");
  await sleep(200);
  const fresh = await view(page);
  check("Z fresh visit starts clean", fresh.q === "" && fresh.city === "" && fresh.page === "1" && fresh.lq === "", fresh);

  // L: locked delete.
  const lk = await page.evaluate(() => {
    const b = Array.from(document.querySelectorAll('[data-gk-table="kunden"] tbody tr')).map((r) => r.querySelector("[data-gk-locked]")).filter(Boolean)[0];
    if (!b) return null;
    return { tag: b.tagName, aria: b.getAttribute("aria-label"), dis: b.getAttribute("aria-disabled"), title: b.getAttribute("title"),
      act: b.hasAttribute("data-gk-action") || b.hasAttribute("data-gk-params") || b.hasAttribute("data-gk-confirm") || b.hasAttribute("onclick"),
      pe: getComputedStyle(b).pointerEvents };
  });
  check("L locked button rendered by the server", lk && lk.tag === "BUTTON" && lk.dis === "true" && /^Löschen — hat \d+ Rechnungen$/.test(lk.aria) && /^hat \d+ Rechnungen$/.test(lk.title) && !lk.act, lk);
  check("L locked button takes the pointer (shows its reason)", lk && lk.pe === "auto", lk);
  await page.click('[data-gk-table="kunden"] [data-gk-locked]', { force: true });
  await sleep(200);
  const toast = await page.evaluate(() => (document.querySelector(".gk-toast") || {}).textContent || "");
  check("L click shows the reason", /hat \d+ Rechnungen/.test(toast), toast);
  await page.click('[data-gk-table="kunden"] [data-gk-sort="name"]');
  await sleep(150);
  const lk2 = await page.evaluate(() => {
    const b = document.querySelector('[data-gk-table="kunden"] [data-gk-locked]');
    return b ? { aria: b.getAttribute("aria-label"), act: b.hasAttribute("data-gk-action") || b.hasAttribute("data-gk-confirm"), n: document.querySelectorAll('[data-gk-table="kunden"] [data-gk-action="delete"]').length } : null;
  });
  check("L after a sort the client writes the same locked button", lk2 && /^Löschen — hat \d+ Rechnungen$/.test(lk2.aria) && !lk2.act, lk2);
  if (shot) await page.screenshot({ path: shot, fullPage: true });

  await browser.close();
  console.log(passed + " passed, " + failures.length + " failed");
  failures.forEach((f) => console.log("  FAIL " + f));
  process.exit(failures.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
