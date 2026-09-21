#!/usr/bin/env node
/**
 * Behaviour in a real browser.
 *
 * `php tests/run.php` executes no JavaScript — every check of js/gridkit.js in
 * there compares strings. 1.80.3 showed what that misses: an Escape rework
 * passed the whole suite and still swallowed the first Escape in a modal,
 * because the fault was in what a never-opened list reports as its inline
 * style. It took a browser to see it.
 *
 * Like ci/parity.php this is NOT part of the suite: GridKit has no dependencies
 * and the suite keeps that promise. Run it when touching js/gridkit.js:
 *
 *     node ci/browser.js                        # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser.js
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-browser-"));
// On exit, whatever the way out: a failing php, a browser that does not start and
// Ctrl+C all leave before any finally runs.
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, execFileSync(php, [path.join(__dirname, "browser-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }));
fs.writeFileSync(path.join(dir, "select.html"), execFileSync(php, [path.join(__dirname, "browser-fixture.php"), "--select"]));

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  try {
    const page = await browser.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto("file://" + fixture);

    const check = (name, ok) => results.push({ name, ok: !!ok });
    const stack = () => page.evaluate(() => GK.modal.stack.length);
    const openModal = async () => {
      await page.evaluate(() => {
        const html = document.getElementById("form").innerHTML;
        window.fetch = () => Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve(html) });
        while (GK.modal.stack.length) GK.modal.close();
        GK.modal.open("Test", "/x", {}, "medium");
      });
      await page.waitForSelector(".gk-modal-overlay [data-gk-ajax-select]");
    };

    // ── Escape closes the layer on top, not the modal underneath ─────────
    await openModal();
    await page.focus(".gk-modal-overlay .gk-ajax-search-input");
    await page.keyboard.press("Escape");
    check("AJAX select, list never opened: the first Escape closes the modal", (await stack()) === 0);

    await openModal();
    await page.click(".gk-modal-overlay [data-gk-select-search] .gk-select-display");
    await page.keyboard.press("Escape");
    check("searchable select, list open: the list closes, the modal stays",
      (await stack()) === 1 && !(await page.$(".gk-modal-overlay [data-gk-select-search] .gk-select-display.open")));
    await page.keyboard.press("Escape");
    check("  … and the next Escape closes the modal", (await stack()) === 0);

    await openModal();
    await page.click(".gk-modal-overlay .gk-multiselect-display");
    const multiWasOpen = !!(await page.$(".gk-modal-overlay .gk-multiselect-display.open"));
    await page.keyboard.press("Escape");
    check("multi select, list open: the list closes, the modal stays",
      multiWasOpen && (await stack()) === 1 && !(await page.$(".gk-modal-overlay .gk-multiselect-display.open")));

    await openModal();
    await page.evaluate(() => document.getElementById("menu").classList.add("open"));
    await page.keyboard.press("Escape");
    check("header dropdown open over a modal: the menu closes, the modal stays",
      (await stack()) === 1 && !(await page.$("#menu.open")));

    await openModal();
    await page.evaluate(() => { window._answer = "pending"; GK.confirm("Delete?").then((a) => (window._answer = a)); });
    await page.waitForSelector(".gk-confirm-overlay");
    await page.keyboard.press("Escape");
    await page.waitForFunction(() => window._answer !== "pending");
    check('GK.confirm over a modal: answers "no", the modal stays',
      (await page.evaluate(() => window._answer)) === false && (await stack()) === 1);

    await openModal();
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await page.keyboard.press("Escape");
    check("a modal on its own: Escape closes it", (await stack()) === 0);

    // ── A hand-written modal gets a role, a name and a named close button ──
    const st = await page.evaluate(() => {
      const box = document.querySelector("#static .gk-modal");
      const heading = box.querySelector("h3");
      return {
        role: box.getAttribute("role"),
        named: !!heading.id && box.getAttribute("aria-labelledby") === heading.id,
        close: box.querySelector(".gk-modal-close").getAttribute("aria-label"),
        modal: box.hasAttribute("aria-modal"),
      };
    });
    check("static modal: role=dialog, named by its heading, close button named, no aria-modal",
      st.role === "dialog" && st.named && st.close === "Close" && !st.modal);
    const own = await page.evaluate(() => {
      const box = document.querySelector("#static-own .gk-modal"), bare = document.querySelector("#static-bare .gk-modal");
      return { role: box.getAttribute("role"), by: box.getAttribute("aria-labelledby"), h: box.querySelector("h4").id,
               close: box.querySelector(".gk-modal-close").getAttribute("aria-label"), bareRole: bare.getAttribute("role") };
    });
    check("static modal with its own header: named by the heading it has; a close button with words keeps them",
      own.role === "dialog" && own.by && own.by === own.h && own.close === null);
    check("static modal without any heading: left alone rather than announced as a nameless dialog", own.bareRole === null);

    // ── A header stands where its column stands — before and after a rebuild ──
    const heads = () => page.evaluate(() => {
      const out = {};
      document.querySelectorAll('[data-gk-table="prices"] thead th').forEach((th) => {
        const label = th.textContent.replace(/[^A-Za-z]/g, "").replace(/(unfoldmore|arrowupward|arrowdownward)$/, "");
        if (!label) return;
        const btn = th.querySelector(".gk-sort-btn");
        out[label] = { align: getComputedStyle(th).textAlign, justify: btn ? getComputedStyle(btn).justifyContent : null };
      });
      const cell = document.querySelector('[data-gk-table="prices"] tbody td.gk-td-num');
      const price = document.querySelector('[data-gk-table="prices"] thead th [data-gk-sort="price"]');
      return { heads: out, cell: cell ? getComputedStyle(cell).textAlign : null,
               first: (document.querySelector('[data-gk-table="prices"] tbody tr td:not(.gk-cb-col)') || {}).textContent || null,
               // Only the client writes these two — proof that a rebuild really happened.
               rebuilt: !!document.querySelector('[data-gk-table="prices"] thead th.gk-sortable-mi'),
               sort: price ? price.closest("th").getAttribute("aria-sort") : null,
               caption: (document.querySelector('[data-gk-table="prices"] caption') || {}).textContent || null,
               rowBox: (document.querySelector('[data-gk-table="prices"] tbody td.gk-cb-col input') || { getAttribute() {} }).getAttribute("aria-label"),
               nowrap: !!document.querySelector('[data-gk-table="prices"] table.gk-table-nowrap'),
               footer: ((document.querySelector('[data-gk-table="prices"] tfoot td:nth-child(2)') || {}).textContent || "").trim(),
               footerCells: document.querySelectorAll('[data-gk-table="prices"] tfoot td').length,
               allBox: (document.querySelector('[data-gk-table="prices"] [data-gk-select-all]') || { getAttribute() {} }).getAttribute("aria-label") };
    });
    const good = (h) => h.cell === "right" && h.heads.Price && h.heads.Price.align === "right" && h.heads.Price.justify === "flex-end"
      && h.heads.Qty && h.heads.Qty.align === "right"
      && h.heads.State.align === "center" && h.heads.Product.align !== "right"
      && h.caption === "Price list" && h.rowBox === "Select row" && h.allBox === "Select all"
      && h.nowrap && h.footer === "111,50 €";
    const before = await heads();
    check("server-rendered table: numeric header and cell right, centred column centred, caption, checkbox names, nowrap and totals row present", good(before));
    await page.click('[data-gk-table="prices"] [data-gk-sort="price"]');
    const after = await heads();
    check("after a client-side sort all of that is still true — and the sort really happened",
      good(after) && after.rebuilt && after.sort === "ascending" && before.first === "Anvil" && after.first === "Widget"
      && after.footerCells === before.footerCells);
    if (!good(before) || !good(after)) console.log(JSON.stringify({ before, after }));

    // ── AJAX navigation brings the target page's own stylesheet along ─────
    // Served from memory: XHR does not work on file://.
    const gridkitJs = fs.readFileSync(path.join(__dirname, "..", "js", "gridkit.js"), "utf8");
    const shell = (title, head, body) =>
      "<!DOCTYPE html><html><head><meta charset='utf-8'><title>" + title + "</title>" + head + "</head><body class='gk-root'>" +
      "<aside data-gk-sidebar data-gk-ajax-nav><nav class='gk-sidebar-nav'><a id='to-a' href='/a'>A</a><a id='to-b' href='/b'>B</a><a id='to-c' href='/c'>C</a><a id='to-d' href='/d'>D</a></nav></aside>" +
      "<main data-gk-content>" + body + "</main><script src='/gridkit.js'></script></body></html>";
    const site = {
      "/a": shell("A", "", "<p id='where'>page a</p>"),
      "/b": shell("B", "<link rel='stylesheet' href='/b.css?v=1'><style>#inline-b{letter-spacing:3px}</style>",
        "<p id='where'>page b</p><p id='styled-b'>x</p><p id='inline-b'>y</p>"),
      "/c": shell("C", "", "<p id='where'>page c</p><p id='styled-b'>x</p>"),
      "/d": shell("D", "", "<p id='where'>page d</p>" + fs.readFileSync(path.join(dir, "select.html"), "utf8")),
    };
    const nav = await browser.newPage();
    nav.on("pageerror", (e) => pageErrors.push(e.message));
    await nav.route("http://gk.test/**", (route) => {
      const u = new URL(route.request().url());
      if (u.pathname === "/gridkit.js") return route.fulfill({ contentType: "text/javascript", body: gridkitJs });
      if (u.pathname === "/b.css") return route.fulfill({ contentType: "text/css", body: "#styled-b{text-indent:42px}" });
      if (site[u.pathname]) return route.fulfill({ contentType: "text/html", body: site[u.pathname] });
      return route.fulfill({ status: 404, body: "" });
    });
    await nav.goto("http://gk.test/a");
    // A full page load wipes this. Without it the three cases below pass on a
    // navigation that fell back to location.href — every page brings its own
    // stylesheet along when it is loaded in full.
    await nav.evaluate(() => { window.__stayed = true; });
    const stayed = () => nav.evaluate(() => window.__stayed === true);
    await nav.click("#to-b");
    await nav.waitForFunction(() => document.getElementById("where") && document.getElementById("where").textContent === "page b");
    const onB = await nav.evaluate(() => ({
      indent: getComputedStyle(document.getElementById("styled-b")).textIndent,
      spacing: getComputedStyle(document.getElementById("inline-b")).letterSpacing,
    }));
    check("navigating to a page with its own head stylesheet and <style>: both apply, without a full load",
      onB.indent === "42px" && onB.spacing === "3px" && (await stayed()));
    await nav.click("#to-c");
    await nav.waitForFunction(() => document.getElementById("where").textContent === "page c");
    const onC = await nav.evaluate(() => getComputedStyle(document.getElementById("styled-b")).textIndent);
    check("… and moving on, that page's stylesheet does not follow to the next page", onC === "0px" && (await stayed()));
    await nav.click("#to-b");
    await nav.waitForFunction(() => document.getElementById("where").textContent === "page b");
    const links = await nav.evaluate(() => document.querySelectorAll('link[rel="stylesheet"][href*="b.css"]').length);
    check("… and coming back adds it once, not twice", links === 1 && (await stayed()));
    // The same stylesheet under a NEW cache key is still the same stylesheet.
    site["/a"] = site["/a"].replace("</head>", "<link rel='stylesheet' href='/b.css?v=2'></head>");
    await nav.click("#to-a");
    await nav.waitForFunction(() => document.getElementById("where").textContent === "page a");
    const again = await nav.evaluate(() => document.querySelectorAll('link[rel="stylesheet"][href*="b.css"]').length);
    check("… and a changed cache key does not add a second copy behind the rest", again === 1 && (await stayed()));
    // Widgets on the target page are bound after the swap: a searchable select opens.
    await nav.click("#to-d");
    await nav.waitForFunction(() => document.getElementById("where").textContent === "page d");
    await nav.click("[data-gk-select-search] .gk-select-display");
    const opened = !!(await nav.$("[data-gk-select-search] .gk-select-display.open"));
    check("after navigation the searchable select on the new page opens (widgets are bound)", opened && (await stayed()));
    await nav.close();
  } finally {
    await browser.close();
  }

  for (const r of results) console.log((r.ok ? "ok    " : "FAIL  ") + r.name);
  if (pageErrors.length) console.log("page errors:", pageErrors.slice(0, 3));
  const failed = results.filter((r) => !r.ok).length + (pageErrors.length ? 1 : 0);
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error("aborted:", e.message.split("\n")[0]); process.exit(1); });
