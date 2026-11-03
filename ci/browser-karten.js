#!/usr/bin/env node
/**
 * The compact card of 1.106.0, in a real browser at phone width (390px), light and dark.
 *
 * Card mode is CSS only, so `php tests/run.php` can hold its rules (tests/mobile.test.php)
 * but not what they do: whether a title wraps or runs under the buttons, whether the cells
 * of the head meet in one line, whether a checkbox, a left button, the title and the right
 * buttons stand side by side without overlapping. That is measured here, on every table of
 * ci/browser-karten-fixture.php.
 *
 *     node ci/browser-karten.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-karten.js
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-karten-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, execFileSync(php, [path.join(__dirname, "browser-karten-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }));

/** The title each card of a table must show, card by card: the start of its text. */
const NAMES = ["A rather long", "Short", "Third"];
const TITLE = {
  rl1: NAMES, // the cell with the link
  rl2: NAMES, // the link is on the second column; the first is muted
  rl3: NAMES,
  sel: NAMES, // the muted first column is not the title
  hid: NAMES, // the first column is hidden on a phone
  mut: ["A-1", "A-2", "A-3"], // every column muted: the first one after all
  one: NAMES,
  grp: NAMES, // one card per group, in this order
  hrf: NAMES,
  inv: ["RE-2026-001", "RE-2026-002"],
};

/** Everything about the cards of one table, measured in the page. */
function measure(id) {
  const wrap = document.querySelector(`[data-gk-table="${id}"]`);
  const shown = (el) => el.offsetParent !== null && getComputedStyle(el).display !== "none";
  const label = (td) => {
    const b = getComputedStyle(td, "::before");
    return b.display !== "none" && b.content !== "none" && b.content !== '""';
  };
  const cards = [...wrap.querySelectorAll("tbody tr")]
    .filter((tr) => shown(tr) && !tr.matches(".gk-table-group, .gk-table-more, .gk-empty-row"))
    .map((tr) => {
      const r = tr.getBoundingClientRect();
      const tds = [...tr.children].filter(shown);
      const head = tds.filter((td) => getComputedStyle(td).gridRowStart === "1");
      const fields = tds.filter((td) => !head.includes(td));
      const title = head.find((td) => !td.matches(".gk-cb-col, .gk-actions"));
      const box = (el) => { const b = el.getBoundingClientRect(); return { l: b.left, r: b.right, t: b.top, b: b.bottom, h: b.height }; };
      return {
        display: getComputedStyle(tr).display,
        height: r.height,
        overflow: tr.scrollWidth - tr.clientWidth,
        card: box(tr),
        paddingRight: getComputedStyle(tr).paddingRight,
        head: head.map((td) => ({
          kind: td.matches(".gk-cb-col") ? "checkbox" : td.matches(".gk-actions-left") ? "left" : td.matches(".gk-actions-right") ? "right" : "title",
          ...box(td),
          // where its content starts: the checkbox itself, the first button, the title's text
          inner: (td.firstElementChild || td).getBoundingClientRect().left,
          overflow: td.scrollWidth - td.clientWidth,
          rule: parseFloat(getComputedStyle(td).borderBottomWidth),
        })),
        title: title ? { text: title.textContent.trim(), label: label(title), height: title.getBoundingClientRect().height } : null,
        fields: fields.map((td) => ({ ...box(td), label: label(td), text: td.textContent.trim() })),
      };
    });
  const others = [...wrap.querySelectorAll("tbody tr.gk-table-group, tbody tr.gk-table-more, tbody tr.gk-empty-row, tfoot tr")]
    .filter(shown)
    .map((tr) => ({ cls: tr.className || "tfoot", display: getComputedStyle(tr).display }));
  return { cards, others };
}

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail });
  try {
    for (const mode of ["light", "dark"]) {
      const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
      page.on("pageerror", (e) => pageErrors.push(e.message));
      await page.goto("file://" + fixture);
      await page.evaluate((m) => { document.body.dataset.gkMode = m; }, mode);
      const ids = await page.evaluate(() => [...document.querySelectorAll(".gk-table-wrap.gk-table-mobile-card")].map((w) => w.dataset.gkTable));
      check(`${mode}: the fixture has its tables in card mode`, ids.length === 11, ids.join(","));

      for (const id of ids) {
        const { cards, others } = await page.evaluate(measure, id);
        const all = (fn) => cards.every(fn);
        const where = (fn) => cards.map((c, i) => (fn(c) ? null : i)).filter((i) => i !== null).join(",");

        others.forEach((o) => check(`${mode} ${id}: "${o.cls}" is not a card of fields`, o.display !== "grid", o.display));
        if (id === "emp") { check(`${mode} emp: the empty state has no card`, cards.length === 0); continue; }

        check(`${mode} ${id}: every card is a grid`, all((c) => c.display === "grid"), where((c) => c.display === "grid"));
        check(`${mode} ${id}: every card has a head on its first line`, all((c) => c.head.length > 0), where((c) => c.head.length > 0));
        if (!all((c) => c.head.length > 0)) continue; // the cases below measure the head
        check(`${mode} ${id}: nothing runs out of a card sideways`, all((c) => c.overflow <= 1 && c.head.every((h) => h.overflow <= 1)), where((c) => c.overflow <= 1 && c.head.every((h) => h.overflow <= 1)));
        check(`${mode} ${id}: the titles are ${TITLE[id].join(" / ")}, without a column label`,
          cards.length === TITLE[id].length && cards.every((c, i) => c.title && c.title.text.startsWith(TITLE[id][i]) && !c.title.label), JSON.stringify(cards.map((c) => c.title)));
        check(`${mode} ${id}: every field below the head has its label`, all((c) => c.fields.every((f) => f.label)), where((c) => c.fields.every((f) => f.label)));
        check(`${mode} ${id}: the fields are below the head`,
          all((c) => c.fields.every((f) => f.t >= Math.max(...c.head.map((h) => h.b)) - 0.5)), where((c) => c.fields.every((f) => f.t >= Math.max(...c.head.map((h) => h.b)) - 0.5)));
        check(`${mode} ${id}: the cells of the head do not overlap`,
          all((c) => c.head.every((a, i) => c.head.every((b, j) => j <= i || a.r <= b.l + 0.5 || b.r <= a.l + 0.5))));
        check(`${mode} ${id}: no cell leaves its card`,
          all((c) => [...c.head, ...c.fields].every((x) => x.l >= c.card.l - 0.5 && x.r <= c.card.r + 0.5)));
        check(`${mode} ${id}: the head ends in one line — every cell of it at the same height`,
          all((c) => new Set(c.head.map((h) => Math.round(h.b))).size === 1 && new Set(c.head.map((h) => h.rule)).size === 1));
        check(`${mode} ${id}: a card with fields has a rule under its head, a card without none`,
          all((c) => (c.fields.length ? c.head[0].rule === 1 : c.head[0].rule === 0)), JSON.stringify(cards.map((c) => [c.fields.length, c.head[0].rule])));
        check(`${mode} ${id}: the head and the fields start at the same left edge`,
          all((c) => !c.fields.length || Math.abs(Math.min(...c.head.map((h) => h.inner)) - c.fields[0].l) <= 0.5), JSON.stringify(cards.map((c) => [Math.min(...c.head.map((h) => h.inner)), c.fields[0] && c.fields[0].l])));
        check(`${mode} ${id}: a field takes one line (at most 30px)`, all((c) => c.fields.every((f) => f.h <= 30)), JSON.stringify(cards.map((c) => c.fields.map((f) => Math.round(f.h)))));
      }

      // The order of the head: checkbox, left buttons, title, right buttons.
      for (const id of ["sel", "inv", "rl3", "hrf"]) {
        const { cards } = await page.evaluate(measure, id);
        const order = cards[0].head.slice().sort((a, b) => a.l - b.l).map((h) => h.kind).join(" ");
        const want = { sel: "checkbox left title right", inv: "left title right", rl3: "title right", hrf: "title right" }[id];
        check(`${mode} ${id}: the head reads "${want}"`, order === want, order);
      }

      const first = async (id) => (await page.evaluate(measure, id)).cards[0] || { height: 1e9, title: null, paddingRight: "" };
      const inv = await first("inv");
      check(`${mode} inv: a card of six fields and buttons on both sides is at most 210px tall (386px before 1.106.0)`, inv.height <= 210, Math.round(inv.height) + "px");
      const rl1 = await first("rl1");
      const h = rl1.title ? rl1.title.height : 0;
      check(`${mode} rl1: a long title wraps instead of running under the chevron (two lines and the e-mail)`, h >= 45, Math.round(h) + "px");
      check(`${mode} rl1: a card with a link keeps 40px on the right for its chevron`, rl1.paddingRight === "40px", rl1.paddingRight);
      const grp = await page.evaluate(measure, "grp");
      check(`${mode} grp: three group headings and the footer row are there, and none is a grid`, grp.others.length === 4 && grp.others.every((o) => o.display !== "grid"), JSON.stringify(grp.others));
      await page.close();
    }

    // Wider than a phone, a table is a table: none of this applies.
    const wide = await browser.newPage({ viewport: { width: 1200, height: 900 } });
    await wide.goto("file://" + fixture);
    const displays = await wide.evaluate(() => [...new Set([...document.querySelectorAll(".gk-table-mobile-card tbody tr")].map((tr) => getComputedStyle(tr).display))]);
    check("1200px: every row is a table row", displays.length === 1 && displays[0] === "table-row", displays.join(","));
    await wide.close();
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
})();
