#!/usr/bin/env node
/**
 * Table::columnToggle() (1.108.0) in a real browser.
 *
 * `php tests/run.php` holds the markup and the rules (tests/column-toggle.test.php); what they do
 * is measured here, on ci/browser-column-toggle-fixture.php: hiding and showing, the column that
 * starts hidden and the one that cannot be hidden, the memory across a reload, the redraw after a
 * sort, a search and an AJAX reload, the checkbox and button columns before the first column,
 * group headings, the footer and the empty state, more than twenty columns, the keyboard, the
 * compact card at 390px, dark mode, storage that throws, the CSV — and the same page with the
 * script of 1.107 (the PawBot dashboard's copy) and with no script at all.
 *
 * One invariant runs through all of it: in every row of a table the visible cells span exactly as
 * many columns as the header shows. A hidden header over a visible cell, or a group heading that
 * spans a hidden column, breaks it.
 *
 *     node ci/browser-column-toggle.js                 # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-column-toggle.js
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
const fixtureScript = path.join(__dirname, "browser-column-toggle-fixture.php");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-columns-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const run = (...args) => execFileSync(php, [fixtureScript, ...args], { maxBuffer: 64 * 1024 * 1024 });
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, run());
const oldFixture = path.join(dir, "old.html");
fs.writeFileSync(oldFixture, run("--old-js"));
const fragment2 = run("--fragment", "2").toString("utf8");
const bareFixture = path.join(dir, "bare.html");
fs.writeFileSync(bareFixture, run("--no-script"));

/** What PHP writes for these rows and columns. */
const phpCsv = (rows, columns) => run("--csv", JSON.stringify(rows), JSON.stringify(columns)).toString("utf8");

const ROWS = [
  { id: 1, no: "K-1", name: "Ada Lovelace", email: "ada@example.org", city: "London", amount: 1200.5, team: "a" },
  { id: 2, no: "K-2", name: "Bob Builder", email: "bob@example.org", city: "Berlin", amount: 9.9, team: "a" },
  { id: 3, no: "K-3", name: "Cy Young", email: "cy@example.org", city: "Cairo", amount: 300, team: "b" },
  { id: 4, no: "K-4", name: "Dora Explorer", email: "dora@example.org", city: "Dublin", amount: 45, team: "b" },
];

/** A table as it stands: what the header shows, and how wide every row is in columns. */
function measure(id) {
  const wrap = document.querySelector(`[data-gk-table="${id}"]`);
  const table = wrap.querySelector(":scope > .gk-table");
  const shown = (el) => getComputedStyle(el).display !== "none";
  const label = (cell) => cell.getAttribute("data-label") || cell.textContent.trim();
  const heads = [...table.tHead.rows[0].cells].filter(shown);
  const rows = [...table.rows].slice(1);
  const kind = (tr) => tr.matches(".gk-table-group") ? "group" : tr.matches(".gk-empty-row") ? "empty" : tr.closest("tfoot") ? "foot" : "data";
  const menu = wrap.querySelector(".gk-col-toggle-menu");
  const btn = wrap.querySelector(".gk-col-toggle [popovertarget]");
  return {
    heads: heads.map(label),
    rows: rows.map((tr) => ({
      kind: kind(tr),
      span: [...tr.cells].filter(shown).reduce((n, c) => n + c.colSpan, 0),
      cells: [...tr.cells].filter(shown).map(label),
    })),
    marked: wrap.querySelectorAll(".gk-col-hidden").length,
    open: menu ? menu.matches(":popover-open") : null,
    expanded: btn ? btn.getAttribute("aria-expanded") : null,
    boxes: menu ? [...menu.querySelectorAll("input")].map((cb) => ({ key: cb.dataset.gkCol, on: cb.checked, off: cb.disabled })) : [],
  };
}

const tick = (id, key) => `[data-gk-table="${id}"] .gk-col-toggle-menu input[data-gk-col="${key}"]`;
const button = (id) => `[data-gk-table="${id}"] .gk-col-toggle [popovertarget]`;

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail });
  /** Every row as wide as the header — the grid holds. */
  const aligned = (m) => m.rows.every((r) => r.span === m.heads.length);
  const show = (m) => JSON.stringify({ heads: m.heads, rows: m.rows.map((r) => r.kind + ":" + r.span) });

  try {
    await cases();
  } catch (e) {
    // A step that cannot go on (a wait that times out) is a failure, not a crash without a word.
    check("the run reached its end", false, e.message.split("\n")[0]);
  } finally {
    await browser.close();
  }

  let failed = 0;
  for (const r of results) {
    console.log((r.ok ? "ok    " : "FAIL  ") + r.name);
    if (!r.ok) { failed++; if (r.detail) console.log("        " + r.detail); }
  }
  if (pageErrors.length) { failed++; console.log("FAIL  script errors on the page:\n        " + pageErrors.join("\n        ")); }
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);

  async function cases() {
    // ── A desktop page with the script ─────────────────────────────────
    const ctx = await browser.newContext({ viewport: { width: 1200, height: 900 }, acceptDownloads: true });
    const page = await ctx.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto("file://" + fixture);
    const m = (id) => page.evaluate(measure, id);
    const open = (id) => page.evaluate((s) => document.querySelector(s).click(), button(id));

    let cols = await m("cols");
    check("'hidden' => true: the E-mail column is not there, header nor cells", !cols.heads.includes("E-mail") && cols.rows.every((r) => !r.cells.includes("E-mail")), show(cols));
    // The header writes the cells over both button columns at its end (Table::renderTableHead()),
    // so with left buttons a header stands one place before its cells: the menu knows both places.
    check("the header keeps its order and loses only E-mail (its button cells at its end, as Table writes it)",
      cols.heads.join("|") === "|No.|Name|City|Amount|Actions|Actions", cols.heads.join("|"));
    check("every row spans the columns the header shows: group headings narrowed, the footer cell over E-mail gone", aligned(cols), show(cols));
    check("…the footer keeps its cell over two columns and loses the one over E-mail",
      JSON.stringify(cols.rows.find((r) => r.kind === "foot").cells) === JSON.stringify(["", "Total", "cities", "1,555.40", ""]), JSON.stringify(cols.rows.find((r) => r.kind === "foot")));
    check("the menu lists every column, E-mail unticked, the fixed No. ticked and disabled",
      JSON.stringify(cols.boxes) === JSON.stringify([
        { key: "no", on: true, off: true }, { key: "name", on: true, off: false }, { key: "email", on: false, off: false },
        { key: "city", on: true, off: false }, { key: "amount", on: true, off: false }]), JSON.stringify(cols.boxes));
    check("the button says the menu is closed and names it", cols.expanded === "false" &&
      (await page.evaluate((s) => { const b = document.querySelector(s); return b.getAttribute("aria-controls") === b.getAttribute("popovertarget") && !!document.getElementById(b.getAttribute("aria-controls")); }, button("cols"))));
    await page.locator(button("lnk")).scrollIntoViewIfNeeded();
    await open("lnk");
    await page.waitForFunction(() => document.querySelector('[data-gk-table="lnk"] .gk-col-toggle-menu').matches(":popover-open"));
    const edge = await page.evaluate(() => {
      const r = document.querySelector('[data-gk-table="lnk"] .gk-col-toggle-menu').getBoundingClientRect();
      const b = document.querySelector('[data-gk-table="lnk"] .gk-col-toggle [popovertarget]').getBoundingClientRect();
      return { left: r.left, right: r.right, top: r.top, button: b.bottom, width: document.documentElement.clientWidth };
    });
    check("a button at the right edge: its menu moves left to stay in the window, still under the button",
      edge.right <= edge.width - 7.5 && edge.top >= edge.button, JSON.stringify(edge));
    await page.keyboard.press("Escape");
    const lnk = await m("lnk");
    check("a row link: its column is in the menu, ticked and disabled", JSON.stringify(lnk.boxes[0]) === JSON.stringify({ key: "name", on: true, off: true }), JSON.stringify(lnk.boxes));
    const plain = await page.evaluate(() => { const w = document.querySelector('[data-gk-table="plain"]'); return { menu: !!w.querySelector(".gk-col-toggle"), marked: w.querySelectorAll(".gk-col-hidden, [data-gk-span]").length }; });
    check("a table without columnToggle() has no menu and no mark", !plain.menu && plain.marked === 0, JSON.stringify(plain));
    const wide = await m("wide");
    check("twenty-two columns: the hidden 22nd, past what the stylesheet covers, is gone too", wide.heads.length === 21 && !wide.heads.includes("C22") && wide.heads.includes("C21") && aligned(wide), show(wide));
    const none = await m("none");
    check("the empty state spans the columns that show", none.rows.length === 1 && none.rows[0].kind === "empty" && aligned(none) && none.heads.join() === "Name,E-mail", show(none));
    const srv0 = await m("srv");
    check("a server table: its hidden City is gone, the group heading and the footer narrowed", !srv0.heads.includes("City") && aligned(srv0), show(srv0));

    // A table that arrives later and is handed to GK.initContent(): bound like the others.
    // Its E-mail box is unticked before the binding, as a browser that restores form state on a
    // reload would leave it: the page's default wins over that, the stored choice over the default.
    await page.evaluate(() => {
      localStorage.setItem("gk-cols-late", '{"city":false}');
      const box = document.getElementById("late");
      box.appendChild(document.getElementById("late-table").content.cloneNode(true));
      box.querySelector('input[data-gk-col="email"]').checked = false;
      GK.initContent(box);
    });
    const late = await m("late");
    check("a table put in later and handed to GK.initContent(): its stored choice is applied, a restored state is not, its button is bound",
      late.heads.join() === "Name,E-mail" && late.expanded === "false" && aligned(late), show(late) + " " + late.expanded);
    await page.evaluate(() => localStorage.removeItem("gk-cols-late"));

    // ── The keyboard ───────────────────────────────────────────────────
    await page.evaluate(() => {
      window._esc = [];
      document.addEventListener("keydown", (e) => { if (e.key === "Escape") window._esc.push(e.defaultPrevented); });
    });
    await page.focus(button("cols"));
    await page.keyboard.press("Enter");
    await page.waitForFunction(() => document.activeElement && document.activeElement.matches(".gk-col-toggle-menu input"));
    cols = await m("cols");
    const focused = () => page.evaluate(() => document.activeElement.dataset.gkCol || document.activeElement.tagName);
    check("Enter opens the menu, aria-expanded follows, the focus is on the first box that can be ticked",
      cols.open && cols.expanded === "true" && (await focused()) === "name", JSON.stringify({ open: cols.open, expanded: cols.expanded, focus: await focused() }));
    // .gk-dropdown-item fades its background in: measured once the fade is over (or has not come).
    await page.waitForFunction(() => document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu label:has(input[data-gk-col="name"])')
      .getAnimations().length === 0, null, { timeout: 2000 }).catch(() => {});
    const lit = await page.evaluate(() => {
      const menu = document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu');
      const row = (key) => getComputedStyle(menu.querySelector(`label:has(input[data-gk-col="${key}"])`)).backgroundColor;
      return { focused: row("name"), other: row("city") };
    });
    check("the row under the keyboard is marked like the one under the pointer", lit.focused !== lit.other, JSON.stringify(lit));
    const box = await page.evaluate(() => { const r = document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu').getBoundingClientRect(); const b = document.querySelector('[data-gk-table="cols"] .gk-col-toggle [popovertarget]').getBoundingClientRect(); return { top: r.top, left: r.left, bottom: b.bottom, bleft: b.left }; });
    check("the menu stands under its button", box.top >= box.bottom && box.top - box.bottom < 12 && Math.abs(box.left - box.bleft) < 2, JSON.stringify(box));
    await page.keyboard.press("ArrowDown");
    const f1 = await focused();
    await page.keyboard.press("Space");
    cols = await m("cols");
    check("Arrow down moves to E-mail, Space shows it: header, cells, its footer cell", f1 === "email" && cols.heads.includes("E-mail") && aligned(cols) && cols.rows.find((r) => r.kind === "foot").cells.includes("mails"), f1 + " " + show(cols));
    check("the choice is stored for this table id, as what differs from the page's default",
      (await page.evaluate(() => localStorage.getItem("gk-cols-cols"))) === '{"email":true}', await page.evaluate(() => localStorage.getItem("gk-cols-cols")));
    await page.keyboard.press("End");
    const fEnd = await focused();
    await page.keyboard.press("ArrowDown");
    const fRound = await focused();
    await page.keyboard.press("Home");
    const fHome = await focused();
    await page.keyboard.press("ArrowUp");
    const fUp = await focused();
    check("End, round the end, Home and round the start: the disabled box is passed over", [fEnd, fRound, fHome, fUp].join() === "amount,name,name,amount", [fEnd, fRound, fHome, fUp].join());
    await page.keyboard.press("Escape");
    cols = await m("cols");
    check("Escape closes the menu, the focus goes back to the button, aria-expanded follows",
      !cols.open && cols.expanded === "false" && (await page.evaluate((s) => document.activeElement === document.querySelector(s), button("cols"))), JSON.stringify({ open: cols.open, expanded: cols.expanded }));
    check("…and the key is claimed, so a modal the table stands in stays open", JSON.stringify(await page.evaluate(() => window._esc)) === "[true]", JSON.stringify(await page.evaluate(() => window._esc)));

    await page.keyboard.press("Enter");
    await page.waitForFunction(() => document.activeElement && document.activeElement.matches(".gk-col-toggle-menu input"));
    for (let i = 0; i < 4; i++) await page.keyboard.press("Tab");
    cols = await m("cols");
    check("Tab out of the menu closes it", !cols.open && cols.expanded === "false", JSON.stringify({ open: cols.open, focus: await page.evaluate(() => document.activeElement.outerHTML.slice(0, 60)) }));

    // ── The pointer ────────────────────────────────────────────────────
    await open("cols");
    await page.waitForFunction(() => document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu').matches(":popover-open"));
    await page.click(`[data-gk-table="cols"] .gk-col-toggle-menu label:has(input[data-gk-col="city"])`);
    cols = await m("cols");
    check("a click on City hides it, with its footer cell; the menu stays open for the next one",
      !cols.heads.includes("City") && !cols.rows.find((r) => r.kind === "foot").cells.includes("cities") && aligned(cols) && cols.open, show(cols));
    await page.mouse.click(1100, 20);
    cols = await m("cols");
    check("a click outside closes it", !cols.open && cols.expanded === "false");

    // ── Redraws ─────────────────────────────────────────────────────────
    await page.click('[data-gk-table="cols"] [data-gk-sort="name"]');
    await page.waitForSelector('[data-gk-table="cols"] th.gk-sorted-asc');
    cols = await m("cols");
    check("after a sort (the rows written anew): City still hidden, E-mail still shown, every row as wide as the header",
      !cols.heads.includes("City") && cols.heads.includes("E-mail") && cols.rows.every((r) => !r.cells.includes("City")) && aligned(cols) && cols.marked > 0, show(cols));
    await page.fill('[data-gk-table="cols"] [data-gk-search]', "cairo");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="cols"] tbody tr[data-gk-row-id]').length === 1);
    cols = await m("cols");
    check("after a search: the search still looks at the hidden City, and the redraw holds the columns",
      !cols.heads.includes("City") && aligned(cols) && cols.rows.some((r) => r.kind === "data"), show(cols));
    await page.fill('[data-gk-table="cols"] [data-gk-search]', "zzz");
    await page.waitForSelector('[data-gk-table="cols"] .gk-empty-row');
    cols = await m("cols");
    check("…a search that finds nothing: the empty state spans the columns that show", aligned(cols), show(cols));
    await page.fill('[data-gk-table="cols"] [data-gk-search]', "");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="cols"] tbody tr[data-gk-row-id]').length === 4);

    // ── The CSV of a static table ──────────────────────────────────────
    const [dl] = await Promise.all([page.waitForEvent("download"), page.click('[data-gk-table="cols"] [data-gk-export-csv]')]);
    const csv = fs.readFileSync(await dl.path(), "utf8");
    const sorted = [...ROWS].sort((a, b) => a.name.localeCompare(b.name));
    check("the CSV holds what the table shows: E-mail (shown by the user) in, City (hidden) out",
      csv === phpCsv(sorted, { no: "No.", name: "Name", email: "E-mail", amount: "Amount" }), JSON.stringify(csv));

    // ── An AJAX reload of a server table ───────────────────────────────
    await page.evaluate((html) => { window.fetch = () => Promise.resolve(new Response(html, { status: 200 })); }, fragment2);
    await page.click('[data-gk-table="srv"] [data-gk-page="2"]');
    await page.waitForFunction(() => document.querySelector('[data-gk-table="srv"] tbody').textContent.includes("Server four"));
    let srv = await m("srv");
    check("after an AJAX reload (the server's own fragment): City still hidden, group heading and footer narrowed",
      !srv.heads.includes("City") && srv.rows.every((r) => !r.cells.includes("City")) && aligned(srv) && srv.rows.some((r) => r.kind === "group"), show(srv));
    await open("srv");
    await page.click(tick("srv", "city"));
    srv = await m("srv");
    check("…and shown again from the menu, in the reloaded rows", srv.heads.includes("City") && aligned(srv), show(srv));
    await page.click(tick("srv", "email"));
    await page.keyboard.press("Escape");
    await Promise.all([page.waitForURL(/gk_export=csv/), page.click('[data-gk-table="srv"] [data-gk-export-csv]')]);
    const url = new URL(page.url());
    check("a server table's export names the hidden columns (?gk_hidden=), for Table::sendCsv() to leave out",
      url.searchParams.get("gk_export") === "csv" && url.searchParams.get("gk_hidden") === "email", page.url());

    // ── The memory, across a reload ────────────────────────────────────
    await page.goto("file://" + fixture);
    cols = await m("cols");
    srv = await m("srv");
    check("after a reload the choice is back: E-mail shown, City hidden; the server table: City shown, E-mail hidden",
      cols.heads.includes("E-mail") && !cols.heads.includes("City") && aligned(cols) && srv.heads.join() === "Name,City" && aligned(srv), show(cols) + " " + show(srv));
    await open("cols");
    await page.click(tick("cols", "email"));
    await page.click(tick("cols", "city"));
    check("back to the page's defaults, nothing is stored", (await page.evaluate(() => localStorage.getItem("gk-cols-cols"))) === null,
      await page.evaluate(() => localStorage.getItem("gk-cols-cols")));
    await page.keyboard.press("Escape");

    // ── One column stays ───────────────────────────────────────────────
    await page.evaluate(() => localStorage.setItem("gk-cols-none", '{"name":false,"email":false}'));
    await page.reload();
    let nn = await m("none");
    check("a stored choice that would hide every column brings the first one back", nn.heads.join() === "Name" && aligned(nn), show(nn));
    await page.evaluate(() => localStorage.removeItem("gk-cols-none"));
    await page.reload();
    await open("none");
    await page.click(tick("none", "name"));
    nn = await m("none");
    check("the last column shown cannot be unticked", JSON.stringify(nn.boxes) === JSON.stringify([
      { key: "name", on: false, off: false }, { key: "city", on: false, off: false }, { key: "email", on: true, off: true }]), JSON.stringify(nn.boxes));
    await page.click(tick("none", "city"));
    nn = await m("none");
    check("…until another is shown", nn.boxes.every((b) => !b.off) && nn.heads.join() === "City,E-mail" && aligned(nn), JSON.stringify(nn.boxes));
    await page.keyboard.press("Escape");
    await ctx.close();

    // ── Phone: the compact card, light and dark ────────────────────────
    for (const mode of ["light", "dark"]) {
      const c = await browser.newContext({ viewport: { width: 390, height: 844 } });
      const p = await c.newPage();
      p.on("pageerror", (e) => pageErrors.push(e.message));
      await p.goto("file://" + fixture);
      await p.evaluate((md) => { document.body.dataset.gkMode = md; }, mode);
      const cards = () => p.evaluate(() => [...document.querySelectorAll('[data-gk-table="cols"] tbody tr[data-gk-row-id]')].map((tr) => {
        const shown = [...tr.cells].filter((td) => getComputedStyle(td).display !== "none");
        const title = shown.find((td) => getComputedStyle(td).gridRowStart === "1" && !td.matches(".gk-cb-col, .gk-actions"));
        const sub = title && title.querySelector(".gk-cell-sub");
        return {
          display: getComputedStyle(tr).display,
          // The text of the cell without its second line.
          title: title ? [...title.childNodes].filter((n) => !(n.classList && n.classList.contains("gk-cell-sub"))).map((n) => n.textContent).join("").trim() : null,
          sub: sub ? getComputedStyle(sub).textAlign : null,
          rule: title ? parseFloat(getComputedStyle(title).borderBottomWidth) : null,
          titleLabel: title ? getComputedStyle(title, "::before").display !== "none" : null,
          fields: shown.filter((td) => td !== title && !td.matches(".gk-cb-col, .gk-actions")).map((td) => td.dataset.label),
        };
      }));
      let k = await cards();
      check(`390px ${mode}: a card leaves the hidden E-mail out; Name is the title`,
        k.length === 4 && k.every((x) => x.display === "grid" && !x.fields.includes("E-mail") && x.fields.join() === "No.,City,Amount") && k[0].title === "Ada Lovelace", JSON.stringify(k[0]));
      await p.evaluate((s) => document.querySelector(s).click(), button("cols"));
      await p.waitForFunction(() => document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu').matches(":popover-open"));
      const menuBox = await p.evaluate(() => { const r = document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu').getBoundingClientRect(); return { l: r.left, r: r.right, w: document.documentElement.clientWidth }; });
      check(`390px ${mode}: the menu stays inside the window`, menuBox.l >= 0 && menuBox.r <= menuBox.w, JSON.stringify(menuBox));
      const colours = await p.evaluate(() => {
        const menu = document.querySelector('[data-gk-table="cols"] .gk-col-toggle-menu');
        const item = menu.querySelector("label:has(input:not(:disabled)) span");
        const rgb = (s) => (s.match(/[\d.]+/g) || []).slice(0, 3).map(Number);
        const lum = (c) => { const [r, g, b] = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
        const bg = rgb(getComputedStyle(menu).backgroundColor), fg = rgb(getComputedStyle(item).color);
        const page = rgb(getComputedStyle(menu.closest(".gk-table-wrap")).backgroundColor);
        const [a, b] = [lum(bg), lum(fg)].sort((x, y) => y - x);
        const cs = getComputedStyle(menu);
        return { bg, fg, page, ratio: (a + 0.05) / (b + 0.05), border: cs.borderTopWidth, radius: cs.borderTopLeftRadius, shadow: cs.boxShadow !== "none" };
      });
      check(`390px ${mode}: the menu looks like GridKit's dropdown, not like the browser's popover (12px corners, a shadow, ${mode === "dark" ? "a 1px edge" : "no border"})`,
        colours.radius === "12px" && colours.shadow && colours.border === (mode === "dark" ? "1px" : "0px"), JSON.stringify(colours));
      check(`390px ${mode}: the menu's text reads on its background (at least 4.5:1)${mode === "dark" ? ", which is dark and raised above the table, as the dropdown is" : ""}`,
        colours.ratio >= 4.5 && (mode === "light" ? colours.bg[0] > 200 : colours.bg[0] < 80 && colours.bg[0] > colours.page[0]), JSON.stringify(colours));
      await p.click(tick("cols", "name"));
      await p.keyboard.press("Escape");
      k = await cards();
      check(`390px ${mode}: Name hidden, the next column that shows and is not muted (City) takes the title, without a label`,
        k.map((x) => x.title).join() === "London,Berlin,Cairo,Dublin" && k.every((x) => !x.titleLabel && x.fields.join() === "No.,Amount"), JSON.stringify(k));
      check(`390px ${mode}: …its second line stands under it, left, as a title's does`, k.every((x) => x.sub === "left"), JSON.stringify(k.map((x) => x.sub)));
      await p.evaluate((s) => document.querySelector(s).click(), button("cols"));
      await p.click(tick("cols", "city"));
      await p.click(tick("cols", "amount"));
      await p.keyboard.press("Escape");
      k = await cards();
      check(`390px ${mode}: every field that shows is muted — the first of them is the title, without a label`,
        k.map((x) => x.title).join() === "K-1,K-2,K-3,K-4" && k.every((x) => x.fields.length === 0 && !x.titleLabel), JSON.stringify(k));
      check(`390px ${mode}: …and a card that is nothing but its head has no rule under it`, k.every((x) => x.rule === 0), JSON.stringify(k.map((x) => x.rule)));
      await c.close();
    }

    // ── Storage that throws ────────────────────────────────────────────
    const sc = await browser.newContext({ viewport: { width: 1200, height: 900 } });
    await sc.addInitScript(() => {
      Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new Error("storage denied"); } });
    });
    const sp = await sc.newPage();
    const spErrors = [];
    sp.on("pageerror", (e) => spErrors.push(e.message));
    await sp.goto("file://" + fixture);
    await sp.evaluate((s) => document.querySelector(s).click(), button("cols"));
    await sp.click(tick("cols", "city"));
    let st = await sp.evaluate(measure, "cols");
    check("storage that throws: the menu still hides a column, and nothing breaks", !st.heads.includes("City") && aligned(st) && spErrors.length === 0, spErrors.join(" | ") + show(st));
    await sp.reload();
    st = await sp.evaluate(measure, "cols");
    check("…and after a reload the page's defaults are back", st.heads.includes("City") && !st.heads.includes("E-mail") && spErrors.length === 0, spErrors.join(" | "));
    await sc.close();

    // ── The script of 1.107 (the PawBot dashboard's copy) ──────────────
    const oc = await browser.newContext({ viewport: { width: 1200, height: 900 } });
    const op = await oc.newPage();
    const opErrors = [];
    op.on("pageerror", (e) => opErrors.push(e.message));
    await op.goto("file://" + oldFixture);
    let o = await op.evaluate(measure, "cols");
    check("an older script: the page renders, 'hidden' is hidden, no mark is set", !o.heads.includes("E-mail") && o.heads.includes("City") && o.marked === 0 && opErrors.length === 0, opErrors.join(" | ") + show(o));
    await op.click(button("cols"));
    o = await op.evaluate(measure, "cols");
    check("…the menu opens without it (a native popover)", o.open === true);
    await op.click(tick("cols", "city"));
    await op.click(tick("cols", "email"));
    await op.click('[data-gk-table="cols"] [data-gk-sort="name"]');
    await op.waitForSelector('[data-gk-table="cols"] th.gk-sorted-asc');
    o = await op.evaluate(measure, "cols");
    check("…its checkboxes hide and show columns, also after a sort", !o.heads.includes("City") && o.heads.includes("E-mail") && o.rows.filter((r) => r.kind === "data").every((r) => r.span === o.heads.length), show(o));
    const oFoot = o.rows.find((r) => r.kind === "foot");
    check("…and a footer cell over one column goes with it", oFoot && oFoot.cells.includes("mails") && !oFoot.cells.includes("cities") && oFoot.span === o.heads.length, JSON.stringify(oFoot));
    await oc.close();

    // ── A script added after the page has loaded ───────────────────────
    // GK.init() then runs inside the main block, before the column toggle's block exists.
    const lc = await browser.newContext({ viewport: { width: 1200, height: 900 } });
    const lp = await lc.newPage();
    lp.on("pageerror", (e) => pageErrors.push(e.message));
    await lp.goto("file://" + bareFixture);
    await lp.evaluate(() => localStorage.setItem("gk-cols-cols", '{"city":false}'));
    await lp.addScriptTag({ path: path.join(__dirname, "..", "js", "gridkit.js") });
    const lt = await lp.evaluate(measure, "cols");
    check("a script added after the page has loaded binds the menus too", !lt.heads.includes("City") && lt.expanded === "false" && aligned(lt), show(lt));
    await lc.close();

    // ── No script at all ───────────────────────────────────────────────
    const nc = await browser.newContext({ viewport: { width: 1200, height: 900 }, javaScriptEnabled: false });
    const np = await nc.newPage();
    await np.goto("file://" + fixture);
    const firstPaint = await np.evaluate(() => [...document.querySelectorAll('[data-gk-table="cols"] thead th, [data-gk-table="cols"] tbody td[data-label="E-mail"]')]
      .filter((c) => c.textContent.trim() === "E-mail" || c.dataset.label === "E-mail").map((c) => getComputedStyle(c).display).join());
    check("no script: the 'hidden' column (header and cells) is hidden by the server's markup — no flash before a script runs", firstPaint === "none,none,none,none,none", firstPaint);
    await np.click(button("cols"));
    await np.click(tick("cols", "city"));
    const noJs = await np.evaluate(() => [...document.querySelectorAll('[data-gk-table="cols"] thead th')].filter((th) => getComputedStyle(th).display !== "none").map((th) => th.textContent.trim()).join("|"));
    check("no script: the button opens the menu and a checkbox hides its column", noJs === "|No.|Name|Amount|Actions|Actions", noJs);
    await nc.close();
  }
})();
