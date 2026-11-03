#!/usr/bin/env node
/**
 * PermissionMatrix (1.115.0) in a real browser: toggling, inheritance (shown, not removable, live),
 * `requires` both ways and through the roles that inherit, the bulk toggles of a row, a column, a group
 * and all roles with their mixed state, the filter, folding, the count of changes and Reset, the
 * events (and a listener's veto), saving as you go (answered by a stubbed fetch: ok, refused, broken),
 * the grid's keyboard, a phone, the form the page posts — read back by PHP — with and without the
 * script, a matrix that arrives later, sticky head and column, and the contrast of every word and
 * mark in the six themes, light and both spellings of dark.
 *
 * The page is ci/browser-permission-matrix-fixture.php.
 *
 *     node ci/browser-permission-matrix.js                 # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-permission-matrix.js
 *
 * GK_CSS / GK_JS are handed to the fixture (other files instead of css/gridkit.css and js/gridkit.js):
 * that is how the counter-checks ran, one rule taken out at a time. Exit code 0 when every case holds.
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
const FIXTURE = path.join(__dirname, "browser-permission-matrix-fixture.php");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-perm-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const build = (name, args) => {
  const file = path.join(dir, name + ".html");
  fs.writeFileSync(file, execFileSync(php, [FIXTURE, ...args], { maxBuffer: 64 * 1024 * 1024 }));
  return "file://" + file;
};
/** What PHP reads out of a urlencoded body: read(), fromPost() and the raw array. */
const phpRead = (body) => JSON.parse(execFileSync(php, [FIXTURE, "--read"], { input: body }).toString());

let passed = 0;
const failures = [];
function check(name, cond, info) {
  if (cond) passed++;
  else failures.push(name + (info === undefined ? "" : " — " + (typeof info === "string" ? info : JSON.stringify(info))));
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const PERMS = ["dash.view", "inv.view", "inv.edit", "inv.delete", "cust.view", "cust.edit", "cust.export", "users.view", "users.roles"];
const EDITABLE = ["acc", "editor", "support", "junior", "guest"];
const cellSel = (m, r, p) => `[data-gk-perm="${m}"] tr[data-gk-perm-perm="${p}"] > td[data-gk-perm-role="${r}"]`;
const boxSel = (m, r, p) => cellSel(m, r, p) + " .gk-perm-check";

/** Everything a cell says about itself. */
async function cell(page, m, r, p) {
  return page.evaluate((sel) => {
    const td = document.querySelector(sel);
    if (!td) return null;
    const box = td.querySelector(".gk-perm-check");
    const hidden = td.querySelector('input[type="hidden"]');
    const desc = (box.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean)
      .map((id) => (document.getElementById(id) || {}).textContent || "").join(" | ");
    return {
      checked: box.checked,
      name: box.getAttribute("name"),
      ariaDisabled: box.getAttribute("aria-disabled") === "true",
      disabled: box.disabled,
      inherited: td.classList.contains("gk-perm-inherited"),
      changed: td.classList.contains("gk-perm-changed"),
      note: (td.querySelector(".gk-perm-note") || {}).textContent || "",
      hidden: hidden ? hidden.value : null,
      label: box.getAttribute("aria-label"),
      desc,
    };
  }, cellSel(m, r, p));
}

async function openPage(browser, url, viewport) {
  const ctx = await browser.newContext({ viewport: viewport || { width: 1360, height: 900 } });
  await ctx.addInitScript(() => {
    window.__saves = [];
    window.__answer = { status: 200, body: { ok: true } };
    window.fetch = function (url, init) {
      window.__saves.push({ url: String(url), method: init.method, headers: init.headers, body: init.body });
      const a = window.__answer;
      const text = typeof a.body === "string" ? a.body : JSON.stringify(a.body);
      return new Promise((done) => setTimeout(() => done(new Response(text, { status: a.status, headers: { "Content-Type": "application/json" } })), 30));
    };
    window.__events = [];
    window.__veto = false;
    // Whether the last Space reached the document still able to scroll something (after the grid's own handler).
    document.addEventListener("keydown", function (e) { if (e.key === " ") window.__spaceSwallowed = e.defaultPrevented; });
    document.addEventListener("gk:permchange", function (e) {
      window.__events.push(JSON.parse(JSON.stringify(Object.assign({ cancelable: e.cancelable }, e.detail))));
      if (window.__veto) e.preventDefault();
    });
  });
  const page = await ctx.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(url, { waitUntil: "load" });
  await sleep(50);
  return { ctx, page, errors };
}

const status = (page, m) => page.evaluate((m) => document.querySelector(`[data-gk-perm="${m}"] [data-gk-perm-status]`).textContent, m);
const toast = (page) => page.evaluate(() => [...document.querySelectorAll(".gk-toast:not(.gk-toast-out) .gk-toast-text")].map((t) => t.textContent).join(" | "));
const clearToasts = (page) => page.evaluate(() => document.querySelectorAll(".gk-toast").forEach((t) => t.remove()));
const events = (page) => page.evaluate(() => window.__events.splice(0));
const changesText = (page, m) => page.evaluate((m) => document.querySelector(`[data-gk-perm="${m}"] [data-gk-perm-changes]`).textContent, m);
const bulk = (m, kind, extra) => `[data-gk-perm="${m}"] .gk-perm-bulk[data-gk-perm-bulk="${kind}"]` + (extra || "");
async function bulkState(page, sel) {
  return page.evaluate((sel) => {
    const b = document.querySelector(sel);
    return b ? { checked: b.checked, mixed: b.indeterminate, disabled: b.getAttribute("aria-disabled") === "true", shown: b.getClientRects().length > 0 } : null;
  }, sel);
}
const count = (page, m, r) => page.evaluate(([m, r]) => document.querySelector(`[data-gk-perm="${m}"] thead th[data-gk-perm-role="${r}"] [data-gk-perm-count]`).textContent, [m, r]);
/** The form as the browser would post it, urlencoded. */
const formBody = (page) => page.evaluate(() => new URLSearchParams(new FormData(document.getElementById("f"))).toString());

/** WCAG contrast of two CSS colours (rgb(), rgba(), color(srgb …), or anything else a canvas can paint). */
const CONTRAST = `
  window.__rgb = function (c) {
    let m = c.match(/^rgba?\\(([^)]+)\\)/);
    if (m) { const v = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [v[0], v[1], v[2], v.length > 3 ? v[3] : 1]; }
    m = c.match(/^color\\(srgb ([^)]+)\\)/);
    if (m) { const v = m[1].split(/[ \\/]+/).filter(Boolean).map(Number); return [v[0] * 255, v[1] * 255, v[2] * 255, v.length > 3 ? v[3] : 1]; }
    // oklch(), oklab(), a named colour: let a canvas convert it.
    const cv = document.createElement("canvas");
    cv.width = cv.height = 1;
    const cx = cv.getContext("2d");
    cx.clearRect(0, 0, 1, 1);
    cx.fillStyle = "rgba(0,0,0,0)";
    cx.fillStyle = c;
    cx.fillRect(0, 0, 1, 1);
    const d = cx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2], d[3] / 255];
  };
  window.__lum = function (rgb) {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(rgb[0]) + 0.7152 * f(rgb[1]) + 0.0722 * f(rgb[2]);
  };
  window.__ratio = function (a, b) {
    const x = __lum(a), y = __lum(b);
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
  };
  /** The opaque colour behind an element: its own background and those of its ancestors, blended. */
  window.__bg = function (el) {
    const layers = [];
    for (let e = el; e; e = e.parentElement) {
      const c = __rgb(getComputedStyle(e).backgroundColor);
      if (c && c[3] > 0) { layers.push(c); if (c[3] >= 1) break; }
    }
    let out = [255, 255, 255];
    for (let i = layers.length - 1; i >= 0; i--) {
      const [r, g, b, a] = layers[i];
      out = [r * a + out[0] * (1 - a), g * a + out[1] * (1 - a), b * a + out[2] * (1 - a)];
    }
    return out;
  };
`;

(async () => {
  const browser = await chromium.launch();
  try {
    const MAIN = build("main", []);
    const DARK = build("dark", ["--mode=dark"]);
    const OLD = build("old", ["--old-js"]);

    for (const [modeName, url] of [["light", MAIN], ["dark", DARK]]) {
      const { ctx, page, errors } = await openPage(browser, url);
      const M = "m";
      const tag = (s) => `[${modeName}] ${s}`;

      // ── set up ────────────────────────────────────────────────────────
      const setup = await page.evaluate(() => {
        const root = document.querySelector('[data-gk-perm="m"]');
        const shown = (el) => !!el && el.getClientRects().length > 0;
        return {
          ready: root.hasAttribute("data-gk-perm-ready"),
          grid: root.querySelector(".gk-perm-table").getAttribute("role"),
          toolbar: shown(root.querySelector(".gk-perm-toolbar")),
          allCol: shown(root.querySelector("thead .gk-perm-all")),
          foldBtn: shown(root.querySelector(".gk-perm-fold")),
          plainGroup: shown(root.querySelector(".gk-perm-group-plain")),
          usersFolded: root.querySelector('tbody[data-gk-perm-group="users"]').hasAttribute("data-gk-perm-folded"),
          usersRowShown: shown(root.querySelector('tr[data-gk-perm-perm="users.view"]')),
          disabledLeft: root.querySelectorAll(".gk-perm-table input:disabled").length,
          picker: shown(root.querySelector(".gk-perm-picker")),
          mixedSwatch: root.querySelector("[data-gk-perm-mixed]").indeterminate,
        };
      });
      check(tag("the script marks the matrix ready and makes the table a grid"), setup.ready && setup.grid === "grid", setup);
      check(tag("the toolbar, the All column and the fold buttons show with the script"), setup.toolbar && setup.allCol && setup.foldBtn && !setup.plainGroup, setup);
      check(tag("a group with 'collapsed' starts folded"), setup.usersFolded && !setup.usersRowShown, setup);
      check(tag("no checkbox stays disabled (aria-disabled instead, so the grid reaches it)"), setup.disabledLeft === 0, setup);
      check(tag("the role select is not shown on a wide screen"), !setup.picker, setup);
      check(tag("the legend's mixed swatch is indeterminate"), setup.mixedSwatch, setup);
      const centred = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] .gk-perm-table td.gk-perm-cell, [data-gk-perm="m"] .gk-perm-table td.gk-perm-all')]
        .map((td) => { const i = td.querySelector("input[type=checkbox]"); if (!i) return 0; const a = td.getBoundingClientRect(), c = i.getBoundingClientRect();
          return Math.abs((a.left + a.right) / 2 - (c.left + c.right) / 2); })
        .filter((d) => d > 2).length);
      check(tag("every box stands in the middle of its cell"), centred === 0, centred);

      let c = await cell(page, M, "editor", "cust.view");
      check(tag("an inherited cell is ticked, nameless, aria-disabled, says via Guest and is described"),
        c.checked && c.inherited && c.name === null && c.ariaDisabled && c.note === "via Guest" && /Inherited from Guest/.test(c.desc), c);
      c = await cell(page, M, "junior", "dash.view");
      check(tag("inheritance goes through the chain (Junior ← Support ← Guest)"), c.checked && c.inherited && c.note === "via Guest", c);
      c = await cell(page, M, "support", "cust.view");
      check(tag("an own grant that the parent also has stays the role's own"), c.checked && !c.inherited && c.name === "perm[support][cust.view]", c);
      c = await cell(page, M, "admin", "inv.delete");
      check(tag("a locked cell is ticked, nameless, aria-disabled and described"), c.checked && c.name === null && c.ariaDisabled && /always has every permission/.test(c.desc), c);
      check(tag("the counts per role"), (await count(page, M, "guest")) === "2 of 9" && (await count(page, M, "editor")) === "3 of 9"
        && (await count(page, M, "junior")) === "3 of 9" && (await count(page, M, "admin")) === "9 of 9",
        [await count(page, M, "guest"), await count(page, M, "editor"), await count(page, M, "junior"), await count(page, M, "admin")]);
      c = await cell(page, M, "acc", "inv.view");
      check(tag("a needed cell says what needs it"), /Needed by Edit invoices/.test(c.desc), c);
      check(tag("a group toggle is mixed while some of the group is granted"), (await bulkState(page, `[data-gk-perm="m"] tbody[data-gk-perm-group="inv"] td[data-gk-perm-role="acc"] .gk-perm-bulk`)).mixed);

      // ── a click, inheritance live ─────────────────────────────────────
      await events(page);
      await page.click(boxSel(M, "guest", "inv.view"), { force: true });
      c = await cell(page, M, "guest", "inv.view");
      check(tag("a click ticks the cell and marks it changed"), c.checked && c.changed && /Changed, not saved yet/.test(c.desc), c);
      c = await cell(page, M, "editor", "inv.view");
      check(tag("the heirs inherit it at once — via Guest, nameless, not marked changed"), c.checked && c.inherited && c.note === "via Guest" && c.name === null && !c.changed, c);
      c = await cell(page, M, "junior", "inv.view");
      check(tag("… down the chain"), c.checked && c.inherited, c);
      let ev = await events(page);
      check(tag("gk:permchange says role, permission, granted, what changed and what is pending"),
        ev.length === 1 && ev[0].role === "guest" && ev[0].permission === "inv.view" && ev[0].granted === true && ev[0].kind === "cell"
        && ev[0].changed.length === 1 && ev[0].changes.length === 1 && ev[0].cancelable === true && ev[0].matrix === "m", ev);
      check(tag("the count says 1 change and Reset is on"), (await changesText(page, M)) === "1 change"
        && (await page.evaluate(() => !document.querySelector('[data-gk-perm="m"] [data-gk-perm-reset]').disabled)));
      check(tag("the role's count follows"), (await count(page, M, "guest")) === "3 of 9" && (await count(page, M, "editor")) === "4 of 9");

      // ── inherited cannot be removed in the child ──────────────────────
      await clearToasts(page);
      await page.click(boxSel(M, "editor", "inv.view"), { force: true });
      c = await cell(page, M, "editor", "inv.view");
      ev = await events(page);
      check(tag("an inherited cell does not untick"), c.checked && c.inherited && ev.length === 0, { c, ev });
      check(tag("… and says why"), /Inherited from Guest — change it in the Guest column/.test(await toast(page)), await toast(page));
      await clearToasts(page);
      await page.click(cellSel(M, "admin", "inv.view"), { force: true });
      check(tag("a locked cell does not untick, and says why"), (await cell(page, M, "admin", "inv.view")).checked && /Admin always has every permission/.test(await toast(page)), await toast(page));

      // ── untick in the parent: the heir's own grant comes back ─────────
      await page.click(boxSel(M, "guest", "cust.view"), { force: true });
      c = await cell(page, M, "support", "cust.view");
      check(tag("when the parent loses it, an heir with its own grant keeps it"), c.checked && !c.inherited && c.name === "perm[support][cust.view]", c);
      c = await cell(page, M, "editor", "cust.view");
      check(tag("… and an heir without one loses it"), !c.checked && !c.inherited && c.name === "perm[editor][cust.view]" && !c.ariaDisabled, c);
      await page.click(boxSel(M, "guest", "cust.view"), { force: true });
      check(tag("ticked again, inherited again"), (await cell(page, M, "editor", "cust.view")).inherited);

      // ── requires ──────────────────────────────────────────────────────
      await events(page);
      await page.click(boxSel(M, "editor", "inv.delete"), { force: true });
      await sleep(120);
      c = await cell(page, M, "editor", "inv.edit");
      check(tag("ticking a permission ticks what it needs"), c.checked && !c.inherited && c.changed, c);
      check(tag("… what it already inherits it does not grant again"), (await cell(page, M, "editor", "inv.view")).inherited);
      check(tag("… and says so"), /Also granted, because it is needed: Edit invoices/.test(await status(page, M)), await status(page, M));
      ev = await events(page);
      check(tag("one event for the click, with both cells"), ev.length === 1 && ev[0].changed.length === 2, ev);
      await page.click(boxSel(M, "editor", "inv.edit"), { force: true });
      await sleep(120);
      c = await cell(page, M, "editor", "inv.delete");
      check(tag("unticking a needed permission unticks what needs it"), !c.checked, c);
      check(tag("… and says so"), /Also removed, because it needs what was removed: Delete invoices/.test(await status(page, M)), await status(page, M));
      // through the heirs: Editor's own export needs cust.view, which it has from Guest only.
      await page.click(boxSel(M, "editor", "cust.export"), { force: true });
      check(tag("a requirement met by inheritance grants nothing more"), (await cell(page, M, "editor", "cust.export")).checked && (await cell(page, M, "editor", "cust.view")).inherited);
      await page.click(boxSel(M, "guest", "cust.view"), { force: true });
      await sleep(120);
      check(tag("the parent losing a requirement takes the heir's dependant with it"), !(await cell(page, M, "editor", "cust.export")).checked);
      check(tag("… but not one whose requirement is the heir's own"), (await cell(page, M, "support", "cust.edit")).checked);
      check(tag("… and says it"), /Also removed, because it needs what was removed: Export customers/.test(await status(page, M)), await status(page, M));

      // ── reset and the count ───────────────────────────────────────────
      const pendingBefore = await page.evaluate(() => GK.permMatrix.changes("m"));
      check(tag("GK.permMatrix.changes() lists what is not saved"), pendingBefore.length === 2
        && pendingBefore.some((x) => x.role === "guest" && x.permission === "cust.view" && x.granted === false), pendingBefore);
      check(tag("the count says 2 changes (a change undone is none)"), (await changesText(page, M)) === "2 changes", await changesText(page, M));
      await events(page);
      await page.click('[data-gk-perm="m"] [data-gk-perm-reset]');
      await sleep(120);
      check(tag("Reset puts everything back"), (await page.evaluate(() => GK.permMatrix.changes("m").length)) === 0
        && (await cell(page, M, "guest", "cust.view")).checked && !(await cell(page, M, "guest", "inv.view")).checked
        && (await cell(page, M, "editor", "cust.view")).inherited);
      check(tag("… with no mark left, the count at none and Reset off"), (await page.evaluate(() => document.querySelectorAll('[data-gk-perm="m"] .gk-perm-table .gk-perm-changed').length)) === 0
        && (await changesText(page, M)) === "No changes" && (await page.evaluate(() => document.querySelector('[data-gk-perm="m"] [data-gk-perm-reset]').disabled)));
      ev = await events(page);
      check(tag("… and fires gk:permchange with kind reset"), ev.length === 1 && ev[0].kind === "reset" && ev[0].changes.length === 0, ev);

      // ── veto ──────────────────────────────────────────────────────────
      await page.evaluate(() => { window.__veto = true; });
      await page.click(boxSel(M, "acc", "dash.view"), { force: true });
      await page.evaluate(() => { window.__veto = false; });
      c = await cell(page, M, "acc", "dash.view");
      check(tag("a listener's preventDefault() puts the change back"), !c.checked && !c.changed && (await changesText(page, M)) === "No changes", c);
      await events(page);

      // ── bulk: a row ───────────────────────────────────────────────────
      const rowBulk = `[data-gk-perm="m"] tr[data-gk-perm-perm="inv.delete"] .gk-perm-bulk`;
      await page.click(rowBulk);
      await sleep(80);
      const rowState = await page.evaluate(() => ["acc", "editor", "support", "junior", "guest"].map((r) => {
        const td = document.querySelector(`[data-gk-perm="m"] tr[data-gk-perm-perm="inv.delete"] td[data-gk-perm-role="${r}"]`);
        return [r, td.querySelector(".gk-perm-check").checked, td.classList.contains("gk-perm-inherited")];
      }));
      check(tag("a row's toggle grants it to every role — parents first, the heirs inherit"),
        rowState.every((x) => x[1]) && rowState.filter((x) => x[2]).map((x) => x[0]).join() === "editor,support,junior", rowState);
      check(tag("… with what it needs"), (await cell(page, M, "guest", "inv.edit")).checked && (await cell(page, M, "guest", "inv.view")).checked);
      check(tag("… and the row's toggle is ticked"), (await bulkState(page, rowBulk)).checked);
      ev = await events(page);
      check(tag("… one event, kind row, permission named"), ev.length === 1 && ev[0].kind === "row" && ev[0].permission === "inv.delete" && ev[0].role === null, ev);
      await page.click(rowBulk);
      const rowOff = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] tr[data-gk-perm-perm="inv.delete"] td[data-gk-perm-role] .gk-perm-check')].map((b) => b.checked));
      check(tag("a second click takes it from every role (the locked one keeps it)"), rowOff.join() === "true,false,false,false,false,false", rowOff);
      await page.evaluate(() => GK.permMatrix.reset("m"));
      await events(page);

      // ── bulk: a column, in view only ─────────────────────────────────
      const colBulk = `[data-gk-perm="m"] thead th[data-gk-perm-role="editor"] .gk-perm-bulk`;
      check(tag("a column toggle is mixed while the role has some"), (await bulkState(page, colBulk)).mixed);
      await page.click(colBulk);
      const col = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] td[data-gk-perm-role="editor"] .gk-perm-check')].map((b) => b.closest("tr").getAttribute("data-gk-perm-perm") + ":" + b.checked));
      check(tag("a column's toggle grants the role every permission in view, not the folded group's"),
        col.filter((x) => x.endsWith(":true")).length === 8 && col.includes("users.roles:false"), col);
      check(tag("… and its toggle is ticked"), (await bulkState(page, colBulk)).checked);
      await page.click(colBulk);
      const colOff = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] td[data-gk-perm-role="editor"] .gk-perm-check')].filter((b) => b.checked).map((b) => b.closest("tr").getAttribute("data-gk-perm-perm")));
      check(tag("a second click takes back what can be taken — the inherited stay"), colOff.join() === "dash.view,cust.view,users.view", colOff);
      check(tag("… so the toggle is mixed"), (await bulkState(page, colBulk)).mixed);
      await page.evaluate(() => GK.permMatrix.reset("m"));

      // ── bulk: a group, one role and all roles ────────────────────────
      const grpGuest = `[data-gk-perm="m"] tbody[data-gk-perm-group="inv"] td[data-gk-perm-role="guest"] .gk-perm-bulk`;
      await page.click(grpGuest);
      check(tag("a group's toggle for a role grants it the group"), (await cell(page, M, "guest", "inv.view")).checked && (await cell(page, M, "guest", "inv.delete")).checked
        && !(await cell(page, M, "guest", "users.view")).checked);
      check(tag("… and the group toggle of an heir shows it, ticked"), (await bulkState(page, `[data-gk-perm="m"] tbody[data-gk-perm-group="inv"] td[data-gk-perm-role="editor"] .gk-perm-bulk`)).checked);
      await page.evaluate(() => GK.permMatrix.reset("m"));
      const grpAll = `[data-gk-perm="m"] tbody[data-gk-perm-group="cust"] .gk-perm-bulk[data-gk-perm-bulk="group-all"]`;
      check(tag("the all-roles toggle of a group is mixed"), (await bulkState(page, grpAll)).mixed);
      await page.click(grpAll);
      const allCust = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] tbody[data-gk-perm-group="cust"] .gk-perm-check')].every((b) => b.checked));
      check(tag("… a click grants the group to every role"), allCust && (await bulkState(page, grpAll)).checked);
      await page.evaluate(() => GK.permMatrix.reset("m"));
      await events(page);

      // ── filter ────────────────────────────────────────────────────────
      const search = '[data-gk-perm="m"] [data-gk-perm-search]';
      const visibleRows = () => page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] tr[data-gk-perm-perm]')].filter((t) => t.getClientRects().length).map((t) => t.getAttribute("data-gk-perm-perm")));
      await page.fill(search, "export");
      check(tag("the filter shows what matches"), (await visibleRows()).join() === "cust.export", await visibleRows());
      check(tag("… and hides a group without a match"), await page.evaluate(() => document.querySelector('[data-gk-perm="m"] tbody[data-gk-perm-group="inv"]').getClientRects().length === 0));
      await page.fill(search, "Roles");
      check(tag("a search opens a folded group to show its match"), (await visibleRows()).join() === "users.roles", await visibleRows());
      await page.fill(search, "invoices");
      check(tag("a group's own name matches all its rows"), (await visibleRows()).join() === "inv.view,inv.edit,inv.delete", await visibleRows());
      await page.click(colBulk);
      const filtered = await page.evaluate(() => GK.permMatrix.changes("m").map((x) => x.permission).join());
      check(tag("a column's toggle under a filter grants only the rows shown"), filtered === "inv.view,inv.edit,inv.delete", filtered);
      await page.evaluate(() => GK.permMatrix.reset("m"));
      await page.fill(search, "zzzz");
      check(tag("no match: the empty line shows"), await page.evaluate(() => document.querySelector('[data-gk-perm="m"] [data-gk-perm-empty]').getClientRects().length > 0));
      await sleep(500);
      check(tag("… and the status says 0 of 9"), /0 of 9 permissions shown/.test(await status(page, M)), await status(page, M));
      await page.fill(search, "");
      check(tag("cleared: every row back, the folded group folded again"), (await visibleRows()).length === 7
        && await page.evaluate(() => document.querySelector('[data-gk-perm="m"] tbody[data-gk-perm-group="users"]').hasAttribute("data-gk-perm-folded")));
      await page.fill(search, "löschen kunden");
      check(tag("several words must all match, accents ignored"), (await visibleRows()).length === 0);
      await page.fill(search, "");

      // ── folding ───────────────────────────────────────────────────────
      const fold = '[data-gk-perm="m"] tbody[data-gk-perm-group="inv"] .gk-perm-fold';
      await page.click(fold);
      check(tag("a group folds"), !(await visibleRows()).includes("inv.view")
        && (await page.getAttribute(fold, "aria-expanded")) === "false");
      await page.click(fold);
      check(tag("… and opens"), (await visibleRows()).includes("inv.view") && (await page.getAttribute(fold, "aria-expanded")) === "true");
      const foldAll = '[data-gk-perm="m"] [data-gk-perm-foldall]';
      check(tag("the fold-all button says Collapse all while one is open"), (await page.textContent(foldAll)).trim() === "Collapse all");
      await page.click(foldAll);
      check(tag("… folds every group (the rows without a group stay)"), (await visibleRows()).join() === "dash.view" && (await page.textContent(foldAll)).trim() === "Expand all");
      await page.click(foldAll);
      check(tag("… and opens them all"), (await visibleRows()).length === 9);
      await page.click('[data-gk-perm="m"] tbody[data-gk-perm-group="users"] .gk-perm-fold');

      // ── the form, read back by PHP ────────────────────────────────────
      await page.click(boxSel(M, "guest", "inv.view"), { force: true });      // heirs inherit it
      await page.click(boxSel(M, "acc", "cust.export"), { force: true });
      await page.click(boxSel(M, "support", "cust.edit"), { force: true });   // untick an own grant
      const body = await formBody(page);
      const params = new URLSearchParams(body);
      check(tag("an editable cell posts a hidden 0 and, ticked, its 1 after it"),
        params.getAll("perm[acc][cust.export]").join() === "0,1" && params.getAll("perm[acc][dash.view]").join() === "0", params.getAll("perm[acc][cust.export]"));
      check(tag("an inherited cell posts 0 only; an own grant the parent also has posts 1"), params.getAll("perm[editor][inv.view]").join() === "0"
        && params.getAll("perm[support][cust.view]").join() === "0,1" && params.getAll("perm[editor][cust.view]").join() === "0", body);
      check(tag("a locked role posts nothing, nor do the bulk toggles"), ![...params.keys()].some((k) => k.startsWith("perm[admin]")) && [...params.keys()].every((k) => k.startsWith("perm[")), [...params.keys()]);
      check(tag("every editable cell is in the post"), new Set([...params.keys()]).size === EDITABLE.length * PERMS.length, new Set([...params.keys()]).size);
      const back = phpRead(body);
      check(tag("PHP reads back exactly what the matrix shows as the roles' own"),
        back.read.guest["inv.view"] === true && back.read.editor["inv.view"] === false && back.read.acc["cust.export"] === true
        && back.read.support["cust.edit"] === false && back.read.support["cust.view"] === true && back.read.junior["dash.view"] === false
        && back.read.editor["users.view"] === true && !("admin" in back.read), back.read);
      check(tag("fromPost() gives the same cells"), JSON.stringify(back.fromPost) === JSON.stringify(back.read), [back.fromPost, back.read]);
      await page.evaluate(() => GK.permMatrix.reset("m"));
      const clean = phpRead(await formBody(page));
      check(tag("an untouched matrix posts what it was given (round trip)"),
        Object.entries(clean.read).every(([r, cells]) => Object.entries(cells).every(([p, v]) => v === ({
          guest: ["cust.view", "dash.view"], support: ["cust.edit", "cust.view"], acc: ["inv.view", "inv.edit", "cust.view"], editor: ["users.view"], junior: [],
        }[r].includes(p)))), clean.read);

      // ── form reset, commit ────────────────────────────────────────────
      await page.click(boxSel(M, "acc", "dash.view"), { force: true });
      await page.click("#form-reset");
      await sleep(80);
      check(tag("the form's own reset button puts the matrix back"), !(await cell(page, M, "acc", "dash.view")).checked
        && (await cell(page, M, "editor", "cust.view")).inherited && (await page.evaluate(() => GK.permMatrix.changes("m").length)) === 0);
      await page.click(boxSel(M, "acc", "dash.view"), { force: true });
      await page.evaluate(() => GK.permMatrix.commit("m"));
      check(tag("commit() makes the state the saved one"), (await page.evaluate(() => GK.permMatrix.changes("m").length)) === 0
        && !(await cell(page, M, "acc", "dash.view")).changed && (await cell(page, M, "acc", "dash.view")).checked);
      await page.click(boxSel(M, "acc", "dash.view"), { force: true });
      await page.evaluate(() => GK.permMatrix.commit("m"));
      await events(page);

      // ── keyboard ──────────────────────────────────────────────────────
      const stops = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] .gk-perm-table *')]
        .filter((e) => e.tabIndex >= 0 && (e.matches("input:not([type=hidden]), button, [tabindex]")) && e.getClientRects().length).length);
      check(tag("the grid is one tab stop"), stops === 1, stops);
      await page.focus('[data-gk-perm="m"] .gk-perm-box');
      await page.keyboard.press("Tab");
      const where = () => page.evaluate(() => {
        const a = document.activeElement;
        const cellEl = a.closest("th, td");
        return {
          role: cellEl && cellEl.getAttribute("data-gk-perm-role"),
          perm: a.closest("tr") && a.closest("tr").getAttribute("data-gk-perm-perm"),
          tag: a.tagName, cls: a.className, bulk: a.getAttribute("data-gk-perm-bulk"),
        };
      });
      let w = await where();
      check(tag("Tab enters the grid where it was left (the cell last used)"), w.perm === "dash.view" && w.role === "acc", w);
      await page.keyboard.press("ArrowRight");
      w = await where();
      check(tag("ArrowRight moves one cell, onto an inherited one too"), w.perm === "dash.view" && w.role === "editor", w);
      await page.keyboard.press("ArrowLeft");
      await page.keyboard.press("Space");
      check(tag("Space toggles"), (await cell(page, M, "acc", "dash.view")).checked);
      await page.keyboard.press("Space");
      check(tag("… and back"), !(await cell(page, M, "acc", "dash.view")).checked);
      await page.keyboard.press("ArrowDown");
      w = await where();
      check(tag("ArrowDown moves to the group row, same column"), w.role === "acc" && w.bulk === "group", w);
      await page.keyboard.press("ArrowDown");
      await page.keyboard.press("ArrowDown");
      await page.keyboard.press("ArrowRight");
      w = await where();
      check(tag("… on to Editor: Edit invoices"), w.perm === "inv.edit" && w.role === "editor", w);
      await page.keyboard.press("Home");
      w = await where();
      check(tag("Home goes to the permission's name"), w.tag === "TH" && w.perm === "inv.edit", w);
      const scrollBefore = await page.evaluate(() => { const b = document.querySelector('[data-gk-perm="m"] .gk-perm-box'); b.scrollTop = 0; return b.scrollTop; });
      await page.keyboard.press("Space");
      const scrollAfter = await page.evaluate(() => document.querySelector('[data-gk-perm="m"] .gk-perm-box').scrollTop);
      check(tag("Space on a header cell does not scroll the box"), scrollAfter === scrollBefore
        && (await page.evaluate(() => window.__spaceSwallowed)) === true, [scrollBefore, scrollAfter]);
      await page.keyboard.press("End");
      w = await where();
      check(tag("End goes to the last role"), w.role === "guest" && w.perm === "inv.edit", w);
      await page.keyboard.press("Control+Home");
      w = await where();
      check(tag("Ctrl+Home goes to the corner"), w.tag === "TH" && /gk-perm-corner/.test(w.cls), w);
      await page.keyboard.press("ArrowRight");
      w = await where();
      check(tag("the head's cells are reached: the All column header"), w.tag === "TH" && /gk-perm-all/.test(w.cls), w);
      await page.keyboard.press("ArrowRight");
      w = await where();
      check(tag("… a locked role's header (no toggle: the cell itself)"), w.tag === "TH" && w.role === "admin", w);
      await page.keyboard.press("ArrowRight");
      w = await where();
      check(tag("… and a role's toggle"), w.bulk === "role" && w.role === "acc", w);
      await page.keyboard.press("Control+End");
      w = await where();
      check(tag("Ctrl+End goes to the last row's last cell (a folded group's rows skipped)"), w.perm === null && w.bulk === "group" && w.role === "guest", w);
      await page.keyboard.press("ArrowUp");
      w = await where();
      check(tag("ArrowUp from the folded group lands on the last row shown"), w.perm === "cust.export" && w.role === "guest", w);
      await page.keyboard.press("Control+Home");
      await page.keyboard.press("ArrowDown");
      await page.keyboard.press("End");
      await page.keyboard.press("ArrowLeft");
      await page.keyboard.press("ArrowLeft");
      await page.keyboard.press("ArrowLeft");
      w = await where();
      await clearToasts(page);
      await events(page);
      await page.keyboard.press("Space");
      await sleep(50);
      c = await cell(page, M, "editor", "dash.view");
      check(tag("Space on an inherited cell changes nothing and says why"), w.role === "editor" && w.perm === "dash.view"
        && c.checked && c.inherited && (await events(page)).length === 0 && /Inherited from Guest/.test(await toast(page)), { w, c, t: await toast(page) });
      const stopsAfter = await page.evaluate(() => [...document.querySelectorAll('[data-gk-perm="m"] .gk-perm-table [tabindex="0"]')].length);
      check(tag("still one tab stop after moving"), stopsAfter === 1, stopsAfter);
      await page.keyboard.press("Tab");
      check(tag("Tab leaves the grid"), await page.evaluate(() => !document.activeElement.closest(".gk-perm-table")));
      const header = await page.evaluate(() => {
        const box = document.querySelector('[data-gk-perm="m"] .gk-perm-box');
        return { top: box.style.scrollPaddingTop, left: box.style.scrollPaddingLeft };
      });
      check(tag("the box keeps a focused cell clear of the sticky head and column"), parseFloat(header.top) > 40 && parseFloat(header.left) > 150, header);

      // ── saved as you go ───────────────────────────────────────────────
      await events(page);
      await page.click(boxSel("auto", "b", "p.edit"), { force: true });
      check(tag("autosave: while it saves, the cell is busy and the line says Saving"), (await changesText(page, "auto")) === "Saving …"
        && (await page.getAttribute(boxSel("auto", "b", "p.edit"), "aria-busy")) === "true");
      await sleep(150);
      const saves = await page.evaluate(() => window.__saves.splice(0));
      check(tag("autosave posts the change as JSON with the headers"), saves.length === 1 && saves[0].url === "https://save.example/perm" && saves[0].method === "POST"
        && saves[0].headers["X-CSRF-Token"] === "tok-123" && saves[0].headers["Content-Type"] === "application/json"
        && saves[0].body === JSON.stringify({ matrix: "auto", changes: { b: { "p.edit": true } } }), saves);
      c = await cell(page, "auto", "b", "p.edit");
      check(tag("… answered ok, it is saved: no mark, Saved"), c.checked && !c.changed && (await changesText(page, "auto")) === "Saved." && /Saved\./.test(await toast(page)), { c, t: await toast(page) });
      await clearToasts(page);
      await page.evaluate(() => { window.__answer = { status: 422, body: { ok: false, error: "Nope, <b>not</b> today" } }; });
      await page.click(boxSel("auto", "a", "p.edit"), { force: true });
      await sleep(150);
      c = await cell(page, "auto", "a", "p.edit");
      check(tag("refused: the cell goes back and the toast says the server's words, as text"), !c.checked && !c.changed
        && (await toast(page)) === "Nope, <b>not</b> today", { c, t: await toast(page) });
      ev = (await events(page)).filter((e) => e.matrix === "auto");
      check(tag("… with a revert event that cannot be vetoed"), ev.length === 3 && ev[2].kind === "revert" && ev[2].cancelable === false && ev[2].changed[0].granted === false, ev);
      await clearToasts(page);
      await page.evaluate(() => { window.__answer = { status: 500, body: "<html>oops" }; });
      await page.click(boxSel("auto", "a", "p.view"), { force: true });
      await sleep(150);
      c = await cell(page, "auto", "a", "p.view");
      check(tag("a broken answer puts it back (a revoke becomes a grant again)"), c.checked && !c.changed, c);
      check(tag("… The change could not be saved."), /The change could not be saved\./.test(await toast(page)), await toast(page));
      await page.evaluate(() => { window.__answer = { status: 200, body: { ok: true } }; });
      check(tag("autosave has no Reset button"), (await page.$('[data-gk-perm="auto"] [data-gk-perm-reset]')) === null);

      // ── read-only ─────────────────────────────────────────────────────
      const ro = await page.evaluate(() => {
        const root = document.querySelector('[data-gk-perm="ro"]');
        return {
          names: root.querySelectorAll("[name]").length,
          bulk: root.querySelectorAll(".gk-perm-bulk").length,
          aria: [...root.querySelectorAll(".gk-perm-table .gk-perm-check")].every((b) => b.getAttribute("aria-disabled") === "true"),
          readonly: root.querySelector(".gk-perm-table").getAttribute("aria-readonly"),
          changes: !!root.querySelector("[data-gk-perm-changes]"),
          inherited: root.querySelector('tr[data-gk-perm-perm="t.one"] td[data-gk-perm-role="y"]').classList.contains("gk-perm-inherited"),
        };
      });
      check(tag("read-only: no field, no bulk toggle, no count of changes, aria-readonly"), ro.names === 0 && ro.bulk === 0 && ro.aria && ro.readonly === "true" && !ro.changes && ro.inherited, ro);
      await clearToasts(page);
      await page.click(boxSel("ro", "x", "t.one"), { force: true });
      check(tag("… a click changes nothing and says Read only"), (await cell(page, "ro", "x", "t.one")).checked && /Read only\./.test(await toast(page)), await toast(page));
      await clearToasts(page);
      await page.click(cellSel("ro", "x", "t.two"), { force: true, position: { x: 4, y: 4 } });
      await sleep(50);
      check(tag("… a click on the padding of a cell that cannot change says nothing"), (await toast(page)) === "", await toast(page));

      // ── a matrix that arrives later ──────────────────────────────────
      await page.evaluate(() => {
        const host = document.getElementById("later-host");
        host.innerHTML = JSON.parse(document.getElementById("gk-fixture").textContent).later;
        GK.initContent(host);
      });
      check(tag("GK.initContent() sets up a matrix added later"), await page.evaluate(() => !!document.querySelector('[data-gk-perm="later"][data-gk-perm-ready]')));

      // ── sticky head and column ───────────────────────────────────────
      await page.setViewportSize({ width: 820, height: 560 });
      await sleep(200);
      const sticky = await page.evaluate(async () => {
        const box = document.querySelector('[data-gk-perm="m"] .gk-perm-box');
        box.scrollTop = 0; box.scrollLeft = 0;
        const th = box.querySelector("thead th[data-gk-perm-role='guest']");
        const name = box.querySelector("tr[data-gk-perm-perm='cust.export'] > th");
        box.scrollTop = box.scrollHeight;
        box.scrollLeft = box.scrollWidth;
        await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
        const b = box.getBoundingClientRect();
        return {
          scrollsY: box.scrollHeight > box.clientHeight, scrollsX: box.scrollWidth > box.clientWidth,
          headTop: Math.round(th.getBoundingClientRect().top - b.top), nameLeft: Math.round(name.getBoundingClientRect().left - b.left),
          pageX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      });
      check(tag("a large matrix scrolls in its box, both ways, and the page does not"), sticky.scrollsY && sticky.scrollsX && sticky.pageX <= 0, sticky);
      check(tag("the role row sticks to the top of the box"), Math.abs(sticky.headTop) <= 1, sticky);
      check(tag("the permission column sticks to its left"), Math.abs(sticky.nameLeft) <= 1, sticky);
      await page.setViewportSize({ width: 1360, height: 900 });

      check(tag("no script error"), errors.length === 0, errors);
      await ctx.close();
    }

    // ── a phone ─────────────────────────────────────────────────────────
    for (const [label, url] of [["light", MAIN], ["dark", DARK]]) {
      const { ctx, page, errors } = await openPage(browser, url, { width: 390, height: 800 });
      const phone = () => page.evaluate(() => {
        const root = document.querySelector('[data-gk-perm="m"]');
        const shown = (el) => !!el && el.getClientRects().length > 0;
        const heads = [...root.querySelectorAll("thead th[data-gk-perm-role]")].filter(shown).map((t) => t.getAttribute("data-gk-perm-role"));
        const cells = [...root.querySelectorAll('tr[data-gk-perm-perm="cust.view"] td')].filter(shown).map((t) => t.getAttribute("data-gk-perm-role"));
        const right = Math.max(...[...root.querySelectorAll("tr")].filter(shown).map((t) => t.getBoundingClientRect().right));
        return {
          picker: shown(root.querySelector(".gk-perm-picker")), heads, cells,
          all: shown(root.querySelector(".gk-perm-all")),
          pageX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          right: Math.round(right), vw: document.documentElement.clientWidth,
          search: Math.round(root.querySelector(".gk-perm-toolbar .gk-search").getBoundingClientRect().height),
        };
      });
      let p = await phone();
      check(`[phone ${label}] a role select, one role's column, no All column`, p.picker && p.heads.join() === "acc" && p.cells.join() === "acc" && !p.all, p);
      check(`[phone ${label}] no sideways page scroll, every row inside the screen`, p.pageX <= 0 && p.right <= p.vw, p);
      check(`[phone ${label}] the search field is a field, not a disc`, p.search < 60, p);
      await page.selectOption('[data-gk-perm="m"] [data-gk-perm-pick]', "editor");
      p = await phone();
      check(`[phone ${label}] choosing a role shows its column`, p.heads.join() === "editor" && p.cells.join() === "editor", p);
      await page.click(boxSel("m", "editor", "dash.view"), { force: true });
      check(`[phone ${label}] its inherited cells say so here too`, (await cell(page, "m", "editor", "dash.view")).checked && (await cell(page, "m", "editor", "dash.view")).note === "via Guest");
      await page.click(cellSel("m", "editor", "inv.view"), { force: true });
      check(`[phone ${label}] the whole cell is a target`, (await cell(page, "m", "editor", "inv.view")).checked);
      const target = await page.evaluate(() => {
        const td = document.querySelector('[data-gk-perm="m"] tr[data-gk-perm-perm="inv.view"] td[data-gk-perm-role="editor"]');
        const r = td.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height) };
      });
      check(`[phone ${label}] a cell is at least 44px each way`, target.w >= 44 && target.h >= 44, target);
      check(`[phone ${label}] no script error`, errors.length === 0, errors);
      await ctx.close();
    }

    // ── without the script ──────────────────────────────────────────────
    {
      const { ctx, page, errors } = await openPage(browser, OLD);
      const nojs = await page.evaluate(() => {
        const root = document.querySelector('[data-gk-perm="m"]');
        const shown = (el) => !!el && el.getClientRects().length > 0;
        return {
          ready: root.hasAttribute("data-gk-perm-ready"),
          grid: root.querySelector(".gk-perm-table").getAttribute("role"),
          toolbar: shown(root.querySelector(".gk-perm-toolbar")),
          all: [...root.querySelectorAll(".gk-perm-all")].some(shown),
          bulk: [...root.querySelectorAll(".gk-perm-bulk")].some(shown),
          fold: [...root.querySelectorAll(".gk-perm-fold")].some(shown),
          plain: [...root.querySelectorAll(".gk-perm-group-plain")].filter(shown).length,
          legendJs: [...root.querySelectorAll(".gk-perm-legend [data-gk-perm-js]")].some(shown),
          usersRow: shown(root.querySelector('tr[data-gk-perm-perm="users.roles"]')),
          inheritedDisabled: root.querySelector('tr[data-gk-perm-perm="cust.view"] td[data-gk-perm-role="editor"] .gk-perm-check').disabled,
          inheritedCount: root.querySelectorAll("td.gk-perm-inherited").length,
          stops: [...root.querySelectorAll(".gk-perm-table input:not([type=hidden])")].filter((e) => !e.disabled && shown(e)).length,
        };
      });
      check("[no script] the form stays a form: no grid, no toolbar, no bulk, no fold, plain group names",
        !nojs.ready && nojs.grid === null && !nojs.toolbar && !nojs.all && !nojs.bulk && !nojs.fold && nojs.plain === 3 && !nojs.legendJs, nojs);
      check("[no script] every row shows (a folded group cannot be opened without the script)", nojs.usersRow, nojs);
      check("[no script] inherited cells are disabled; every other editable cell is a tab stop", nojs.inheritedDisabled && nojs.stops === EDITABLE.length * PERMS.length - nojs.inheritedCount, nojs);
      await page.click(boxSel("m", "guest", "inv.view"), { force: true });
      await page.click(boxSel("m", "support", "cust.edit"), { force: true });
      const back = phpRead(await formBody(page));
      check("[no script] the post reads back as ticked", back.read.guest["inv.view"] === true && back.read.support["cust.edit"] === false
        && back.read.editor["inv.view"] === false, back.read);
      await page.click(boxSel("m", "acc", "inv.delete"), { force: true });
      const needs = phpRead(await formBody(page));
      check("[no script] read() adds what a ticked right needs", needs.read.acc["inv.delete"] === true && needs.read.acc["inv.edit"] === true
        && needs.fromPost.acc["inv.delete"] === true, needs.read.acc);
      await page.click(boxSel("m", "editor", "inv.delete"), { force: true });
      const viaParent = phpRead(await formBody(page));
      check("[no script] … not what the role inherits (Guest's posted View invoices covers Editor)", viaParent.read.editor["inv.view"] === false
        && viaParent.read.editor["inv.edit"] === true && viaParent.read.editor["inv.delete"] === true, viaParent.read.editor);
      await page.setViewportSize({ width: 390, height: 800 });
      await sleep(100);
      const narrow = await page.evaluate(() => ({
        pageX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        box: (() => { const b = document.querySelector('[data-gk-perm="m"] .gk-perm-box'); return b.scrollWidth > b.clientWidth; })(),
      }));
      check("[no script, phone] the box scrolls sideways, the page does not", narrow.pageX <= 0 && narrow.box, narrow);
      check("[no script] no script error", errors.length === 0, errors);
      await ctx.close();
    }

    // ── contrast: six themes, light and both spellings of dark ─────────
    const THEMES = ["indigo", "ocean", "forest", "rose", "amber", "slate"];
    const MODES = ["light", "dark", "dark-class"];
    let measured = 0;
    for (const theme of THEMES) {
      for (const mode of MODES) {
        const url = build(`c-${theme}-${mode}`, [`--theme=${theme}`, `--mode=${mode}`]);
        const { ctx, page } = await openPage(browser, url);
        await page.addScriptTag({ content: CONTRAST });
        // A changed cell, an inherited one, a hovered row: the states measured.
        await page.click(boxSel("m", "guest", "inv.view"), { force: true });
        await page.mouse.move(0, 0);
        const res = await page.evaluate(() => {
          const root = document.querySelector('[data-gk-perm="m"]');
          const out = [];
          const text = (sel, what) => {
            const el = root.querySelector(sel);
            if (!el) { out.push([what, 0, "missing"]); return; }
            out.push([what, __ratio(__rgb(getComputedStyle(el).color), __bg(el)), 4.5]);
          };
          const ui = (what, color, el) => out.push([what, __ratio(__rgb(color), __bg(el)), 3]);
          text(".gk-perm-label", "permission name");
          text(".gk-perm-desc", "permission description");
          text(".gk-perm-needs", "Needs:");
          text(".gk-perm-inherited .gk-perm-note", "via <role>");
          text(".gk-perm-count", "role count");
          text(".gk-perm-role-note", "Locked / Inherits");
          text(".gk-perm-role-desc", "role description");
          text("thead .gk-perm-all", "All roles");
          text(".gk-perm-corner", "Permission (corner)");
          text(".gk-perm-fold .gk-table-group-name", "group name");
          text(".gk-table-group-n", "group count");
          text("[data-gk-perm-changes]", "changes count");
          text(".gk-perm-legend li", "legend");
          const fixed = root.querySelector(".gk-perm-locked .gk-perm-check");
          ui("locked box", getComputedStyle(fixed).accentColor, fixed.closest("td"));
          const inh = root.querySelector("td.gk-perm-inherited .gk-perm-check");
          ui("inherited box", getComputedStyle(inh).accentColor, inh.closest("td"));
          ui("inherited ring", getComputedStyle(inh).outlineColor, inh.closest("td"));
          const own = root.querySelector('tr[data-gk-perm-perm="cust.view"] td[data-gk-perm-role="acc"] .gk-perm-check');
          ui("ticked box", getComputedStyle(own).accentColor, own.closest("td"));
          const danger = root.querySelector('tr.gk-perm-danger td[data-gk-perm-role="acc"] .gk-perm-check');
          ui("risky box", getComputedStyle(danger).accentColor, danger.closest("td"));
          const changed = root.querySelector("td.gk-perm-changed");
          ui("changed dot", getComputedStyle(changed, "::after").backgroundColor, changed);
          const allCell = root.querySelector("tbody .gk-perm-all .gk-perm-bulk");
          ui("All-column box", getComputedStyle(allCell).accentColor, allCell.closest("td"));
          const group = root.querySelector(".gk-perm-group-row td[data-gk-perm-role='acc'] .gk-perm-bulk");
          ui("group box", getComputedStyle(group).accentColor, group.closest("td"));
          const sticky = root.querySelector("tbody .gk-perm-name");
          out.push(["sticky column is opaque", __rgb(getComputedStyle(sticky).backgroundColor)[3], 1]);
          out.push(["sticky head is opaque", __rgb(getComputedStyle(root.querySelector("thead th")).backgroundColor)[3], 1]);
          // Every plain cell has the surface of the sticky column beside it: a row's own colour (dark
          // mode stripes the rows) would show behind the cells that scroll and not behind the one that sticks.
          const striped = [...root.querySelectorAll("tbody tr.gk-perm-row")].filter((tr) => tr.getClientRects().length).filter((tr) => {
            const name = __bg(tr.querySelector("th.gk-perm-name")).map(Math.round).join();
            return [...tr.querySelectorAll("td.gk-perm-cell:not(.gk-perm-changed)")].some((td) => __bg(td).map(Math.round).join() !== name);
          }).length;
          out.push(["row surfaces match the sticky column (1 = yes)", striped === 0 ? 1 : 0, 1]);
          // The states are shapes as well as colours: a dashed ring, a dot that is there.
          out.push(["inherited ring is dashed (1 = yes)", getComputedStyle(inh).outlineStyle === "dashed" ? 1 : 0, 1]);
          const dot = getComputedStyle(changed, "::after");
          out.push(["changed dot is drawn (1 = yes)", dot.content !== "none" && parseFloat(dot.width) >= 6 && __rgb(dot.backgroundColor)[3] === 1 ? 1 : 0, 1]);
          return out;
        });
        for (const [what, ratio, min] of res) {
          measured++;
          check(`[contrast ${theme} ${mode}] ${what} ≥ ${min}`, typeof ratio === "number" && ratio >= min, Math.round(ratio * 100) / 100);
        }
        // Dark mode is the table card's dark surface (#161b22), in both spellings, whatever the theme.
        const card = await page.evaluate(() => getComputedStyle(document.querySelector('[data-gk-perm="m"]')).backgroundColor);
        if (mode !== "light") check(`[contrast ${theme} ${mode}] the card is the table card's dark surface`, card === "rgb(22, 27, 34)", card);
        await ctx.close();
      }
    }
    console.log(`contrast: ${measured} measurements in ${THEMES.length} themes × ${MODES.length} modes`);
  } finally {
    await browser.close();
  }

  if (failures.length) {
    console.log(`\nFAILED — ${failures.length} check(s), ${passed} passed\n`);
    for (const f of failures) console.log("  ✗ " + f);
    process.exit(1);
  }
  console.log(`\nok — ${passed} checks passed`);
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
