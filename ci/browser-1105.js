#!/usr/bin/env node
/**
 * The additions of 1.105.0, in a real browser: table bulk actions, CSV export, and
 * (once built) conditional form fields.
 *
 * `php tests/run.php` runs no JavaScript. The markup these features hook into is checked
 * there (tests/table-bulk-csv.test.php); what the script does with it is checked here.
 * The CSV the browser builds is compared, byte for byte, with what Table::csv() writes for
 * the same rows — the two implementations share one rule and nothing else.
 *
 *     node ci/browser-1105.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-1105.js
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
const fixtureScript = path.join(__dirname, "browser-1105-fixture.php");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-1105-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, execFileSync(php, [fixtureScript], { maxBuffer: 64 * 1024 * 1024 }));

/** What PHP writes for these rows and columns. */
const phpCsv = (rows, columns, delimiter = ",") =>
  execFileSync(php, [fixtureScript, "--csv", JSON.stringify(rows), JSON.stringify(columns), delimiter]).toString("utf8");

const ALL = [
  { id: 1, name: "Ada Lovelace", email: "ada@example.org", note: "=1+1", qty: 3 },
  { id: 2, name: 'Bob "the builder"', email: "bob@example.org", note: "a,b", qty: -5 },
  { id: 3, name: "Cy", email: "cy@example.org", note: "two\nlines", qty: 12.5 },
  { id: 4, name: "Dörte Ünal", email: "doerte@example.org", note: "@SUM(A1)", qty: 0 },
  { id: 5, name: "Eve", email: "eve@example.org", note: "+49 123", qty: 7 },
];
const COLUMNS = { name: "Name", email: "Email", note: "Note", qty: "Qty" }; // "secret" has 'export' => false

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, acceptDownloads: true });
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto("file://" + fixture);
    const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail });

    const download = async (selector) => {
      const [dl] = await Promise.all([page.waitForEvent("download"), page.click(selector)]);
      return { name: dl.suggestedFilename(), text: fs.readFileSync(await dl.path(), "utf8") };
    };

    // ── Bulk actions ─────────────────────────────────────────────────────
    await page.evaluate(() => {
      window._bulk = [];
      document.addEventListener("gk:bulkaction", (e) => window._bulk.push(e.detail));
    });
    const bar = '[data-gk-table="people"] .gk-bulk-bar';
    check("the bulk bar holds both actions and the delete button",
      (await page.$$eval(bar + " [data-gk-bulk-action]", (b) => b.map((x) => x.dataset.gkBulkAction))).join() === "archive,void" &&
      !!(await page.$(bar + " [data-gk-bulk-delete]")));

    await page.click('[data-gk-table="people"] tbody tr[data-gk-row-id="1"] input[type=checkbox]');
    await page.click('[data-gk-table="people"] tbody tr[data-gk-row-id="3"] input[type=checkbox]');
    check("ticking rows shows the bulk bar", await page.isVisible(bar));
    await page.click(bar + ' [data-gk-bulk-action="archive"]');
    const first = await page.evaluate(() => window._bulk.slice());
    check("an action fires gk:bulkaction with its name, the ids and the table",
      first.length === 1 && first[0].action === "archive" && first[0].ids.join() === "1,3" && first[0].tableId === "people",
      JSON.stringify(first));

    await page.click(bar + ' [data-gk-bulk-action="void"]');
    await page.waitForSelector(".gk-confirm-overlay");
    check("an action with a question asks it first and fires nothing yet",
      (await page.evaluate(() => window._bulk.length)) === 1 &&
      (await page.textContent(".gk-confirm-overlay")).includes("Void these rows?"));
    await page.keyboard.press("Escape");
    await page.waitForSelector(".gk-confirm-overlay", { state: "detached" });
    check("answering no fires nothing", (await page.evaluate(() => window._bulk.length)) === 1);

    await page.click(bar + ' [data-gk-bulk-action="void"]');
    await page.waitForSelector(".gk-confirm-overlay");
    await page.click(".gk-confirm-overlay .gk-confirm-footer button:last-child");
    await page.waitForFunction(() => window._bulk.length === 2);
    const second = await page.evaluate(() => window._bulk[1]);
    check("answering yes fires the event", second.action === "void" && second.ids.join() === "1,3", JSON.stringify(second));

    await page.click(bar + " [data-gk-bulk-cancel]");
    check("cancel clears the selection and hides the bar", !(await page.isVisible(bar)));

    // ── CSV export, a static table ───────────────────────────────────────
    const all = await download('[data-gk-table="people"] [data-gk-export-csv]');
    check("the file is named as asked", all.name === "people.csv", all.name);
    check("the whole table, byte for byte what Table::csv() writes (formulas defused, 'secret' left out)",
      all.text === phpCsv(ALL, COLUMNS), JSON.stringify(all.text));
    check("it starts with a byte-order mark and ends each line with CRLF",
      all.text.charCodeAt(0) === 0xfeff && all.text.split("\r\n").length === 7);

    await page.fill('[data-gk-table="people"] [data-gk-search]', "ada");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="people"] tbody tr[data-gk-row-id]').length === 1);
    const filtered = await download('[data-gk-table="people"] [data-gk-export-csv]');
    check("after a search, only the rows that are left", filtered.text === phpCsv([ALL[0]], COLUMNS), JSON.stringify(filtered.text));

    await page.fill('[data-gk-table="people"] [data-gk-search]', "");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="people"] tbody tr[data-gk-row-id]').length === 5);
    await page.click('[data-gk-table="people"] [data-gk-sort="qty"]');
    await page.waitForSelector('[data-gk-table="people"] th.gk-sorted-asc');
    const sorted = await download('[data-gk-table="people"] [data-gk-export-csv]');
    const byQty = [...ALL].sort((a, b) => a.qty - b.qty);
    check("after sorting, in the order the table shows", sorted.text === phpCsv(byQty, COLUMNS), JSON.stringify(sorted.text));

    const semi = await download('[data-gk-table="semi"] [data-gk-export-csv]');
    check("a semicolon table writes semicolons and quotes only what needs it",
      semi.name === "semi.csv" && semi.text === phpCsv(ALL.slice(0, 2), { name: "Name", note: "Note" }, ";"), JSON.stringify(semi.text));

    // ── CSV export, a server-driven table ────────────────────────────────
    await Promise.all([page.waitForURL(/gk_export=csv/), page.click('[data-gk-table="server"] [data-gk-export-csv]')]);
    check("a table with no rows in the browser reloads the page with ?gk_export=csv", /gk_export=csv/.test(page.url()), page.url());
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
