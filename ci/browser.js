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

    // An overlay marked up with hidden must be invisible on its own — display:flex
    // on the bare class beat the browser's [hidden] until 1.83.0, and a page that
    // switched to hidden showed its modal full-screen over everything.
    const versteckt = await page.evaluate(() => {
      const d = document.createElement("div");
      d.className = "gk-modal-overlay";
      d.hidden = true;
      d.innerHTML = '<div class="gk-modal">x</div>';
      document.body.appendChild(d);
      const sichtbar = d.checkVisibility();
      d.remove();
      return sichtbar;
    });
    check("an overlay with the hidden attribute is invisible without any JavaScript", versteckt === false);

    // ── A modal that already stands on the page: GK.modal.show() / hide() ──
    const stat = () => page.evaluate(() => {
      const ov = document.getElementById("static-show");
      const box = ov.querySelector(".gk-modal");
      const cs = getComputedStyle(ov);
      return {
        da: ov.isConnected,                       // still in the DOM at all
        sichtbar: cs.display !== "none" && !ov.hasAttribute("hidden"),
        offen: ov.classList.contains("gk-modal-open"),
        role: box.getAttribute("role"),
        modal: box.getAttribute("aria-modal"),
        fokus: document.activeElement ? document.activeElement.id || document.activeElement.className : null,
        stack: GK.modal.stack.length,
      };
    });
    await page.evaluate(() => { while (GK.modal.stack.length) GK.modal.close(); });
    await page.evaluate(() => {
      document.getElementById("open-static").onclick = () =>
        GK.modal.show("#static-show", { focus: "#reg-name", onClose: () => (window.__zu = (window.__zu || 0) + 1) });
    });
    await page.click("#open-static");
    const auf = await stat();
    check("show(): the overlay on the page opens, gets the dialog role, aria-modal and the focus you asked for",
      auf.sichtbar && auf.offen && auf.role === "dialog" && auf.modal === "true" && auf.fokus === "reg-name" && auf.stack === 1);

    // The focus trap: Tab from the last control goes back to the first.
    await page.focus("#reg-save");
    await page.keyboard.press("Tab");
    const gefangen = await page.evaluate(() => document.activeElement.closest("#static-show") !== null);
    check("show(): the focus stays inside the dialog", gefangen);

    // Escape closes it — and the overlay must SURVIVE, or it never opens again.
    await page.keyboard.press("Escape");
    const zu = await stat();
    const zaehler = await page.evaluate(() => window.__zu || 0);
    check("Escape closes it, the overlay stays in the DOM, onClose runs and the focus goes back to the button",
      zu.da && !zu.sichtbar && !zu.offen && zu.stack === 0 && zaehler === 1 && zu.fokus === "open-static");
    // aria-modal says the rest of the page is out of bounds — only true while
    // the dialog is up. Left behind, a closed overlay keeps claiming it.
    check("… and aria-modal is taken off again", zu.modal === null);

    await page.click("#open-static");
    check("… and it opens a second time (a static overlay is never removed)", (await stat()).sichtbar);

    // The close button inside needs no onclick of the page's own.
    await page.click("#static-show .gk-modal-close");
    check("the close button inside closes it without the page wiring anything", !(await stat()).sichtbar);

    await page.click("#open-static");
    await page.mouse.click(5, 5);   // backdrop
    check("a click on the backdrop closes it", !(await stat()).sichtbar);

    // A confirm over the static modal: Escape answers the confirm, the modal stays.
    await page.click("#open-static");
    // No return value: GK.confirm hands back a promise that only settles when
    // someone answers, and evaluate() would wait for it forever.
    await page.evaluate(() => { GK.confirm("Delete?", () => {}); });
    await page.waitForSelector(".gk-confirm-overlay");
    await page.keyboard.press("Escape");
    const danach = await stat();
    check("a confirm over it: Escape answers the confirm, the modal underneath stays open",
      danach.sichtbar && (await page.evaluate(() => !document.querySelector(".gk-confirm-overlay"))));
    await page.keyboard.press("Escape");
    check("… and the next Escape closes the modal itself", !(await stat()).sichtbar);

    // One close, one onClose — however it was closed.
    await page.evaluate(() => { window.__zu = 0; });
    await page.click("#open-static");
    await page.click("#static-show .gk-modal-close");
    await page.click("#static-show .gk-modal-close").catch(() => {});   // already hidden: must do nothing
    await page.evaluate(() => GK.modal.hide("#static-show"));
    check("onClose runs exactly once per close, not on every call",
      (await page.evaluate(() => window.__zu)) === 1);

    // Twice show() without hide(): one entry, not two — or the second Escape
    // would answer a modal that is already closed.
    await page.click("#open-static");
    await page.evaluate(() => GK.modal.show("#static-show"));
    const doppelt = await page.evaluate(() => GK.modal.stack.length);
    await page.keyboard.press("Escape");
    check("show() twice leaves one entry, and one Escape closes it",
      doppelt === 1 && !(await stat()).sichtbar && (await page.evaluate(() => GK.modal.stack.length)) === 0);

    // hide() on an overlay that was never shown must leave it alone: marking it
    // hidden would make a modal the page opens itself invisible from then on.
    const unberuehrt = await page.evaluate(() => {
      const ov = document.getElementById("static-bare");
      GK.modal.hide("#static-bare");
      return { hidden: ov.hasAttribute("hidden"), stack: GK.modal.stack.length };
    });
    check("hide() on an overlay that was never opened changes nothing",
      !unberuehrt.hidden && unberuehrt.stack === 0);

    // show() must refuse an overlay GK.modal built itself: marking it static
    // would leave close() hiding it instead of removing it — forever in the DOM.
    await openModal();
    const fremd = await page.evaluate(() => {
      const dyn = GK.modal.stack[GK.modal.stack.length - 1];
      const zurueck = GK.modal.show(dyn);
      return { abgewiesen: zurueck === null, tiefe: GK.modal.stack.length };
    });
    await page.evaluate(() => GK.modal.close());
    const weg = await page.evaluate(() => !document.querySelector(".gk-modal-overlay:not([id])"));
    check("show() refuses a modal GK.modal built itself, and close() still removes it",
      fremd.abgewiesen && fremd.tiefe === 1 && weg);

    // A modal inside a modal: closing the inner one must not take the outer with
    // it. The click handler sits on the outer overlay, so it has to ask which
    // overlay the close button it found actually belongs to.
    await page.click("#open-static");
    await page.evaluate(() => GK.modal.show("#inner-show"));
    await page.click("#inner-close");
    const innen = await page.evaluate(() => ({
      innenZu: document.getElementById("inner-show").hasAttribute("hidden"),
      aussenOffen: document.getElementById("static-show").classList.contains("gk-modal-open"),
      stack: GK.modal.stack.length,
    }));
    check("a modal inside a modal: its close button closes the inner one only",
      innen.innenZu && innen.aussenOffen && innen.stack === 1);
    await page.keyboard.press("Escape");

    // A page that hides its overlay with a class of its own: show() clears the
    // hidden attribute and the inline style, but not that. Opening it anyway
    // would trap the focus in something nobody can see and let the next Escape
    // answer a dialog the user cannot find.
    const warnungen = [];
    page.on("console", (m) => { if (m.type() === "warning") warnungen.push(m.text()); });
    const versteckt2 = await page.evaluate(() => {
      const zurueck = GK.modal.show("#klassen-versteck");
      const ov = document.getElementById("klassen-versteck");
      return { abgewiesen: zurueck === null, stack: GK.modal.stack.length,
               offen: ov.classList.contains("gk-modal-open"), hidden: ov.hasAttribute("hidden") };
    });
    check("show() refuses an overlay the page hides with a class of its own, and says why",
      versteckt2.abgewiesen && versteckt2.stack === 0 && !versteckt2.offen && versteckt2.hidden
      && warnungen.some((w) => w.indexOf("klassen-versteck") > -1));

    // A dynamic modal opened over a static one: in front of it, and each closes
    // on its own. hide() on the one underneath must not pull the focus out of
    // the one on top, and close() must still REMOVE the dynamic overlay.
    await page.click("#open-static");
    // Not openModal(): that one empties the stack first, and the static modal
    // has to stay open underneath.
    await page.evaluate(() => {
      const html = document.getElementById("form").innerHTML;
      window.fetch = () => Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve(html) });
      GK.modal.open("Test", "/x", {}, "medium");
    });
    await page.waitForSelector(".gk-modal-overlay [data-gk-ajax-select]");
    const gemischt = await page.evaluate(() => {
      const st = document.getElementById("static-show");
      const dyn = GK.modal.stack[GK.modal.stack.length - 1];
      return { tiefe: GK.modal.stack.length, obenIstDyn: dyn !== st,
               davor: parseInt(getComputedStyle(dyn).zIndex, 10) > parseInt(getComputedStyle(st).zIndex, 10) };
    });
    check("a dynamic modal over a static one lies in front of it", gemischt.tiefe === 2 && gemischt.obenIstDyn && gemischt.davor);
    await page.evaluate(() => GK.modal.hide("#static-show"));
    const nachUnten = await page.evaluate(() => {
      const dyn = GK.modal.stack[GK.modal.stack.length - 1];
      return { tiefe: GK.modal.stack.length, fokusOben: dyn.contains(document.activeElement),
               untenZu: document.getElementById("static-show").hasAttribute("hidden") };
    });
    check("closing the one underneath leaves the focus in the one on top",
      nachUnten.tiefe === 1 && nachUnten.fokusOben && nachUnten.untenZu);
    // … and the stack really holds the DYNAMIC one now. Taking the top off
    // instead of the one that closed leaves the wrong overlay behind, and the
    // next Escape then answers a modal that is already shut.
    await page.keyboard.press("Escape");
    check("… and the next Escape closes the one that is still open",
      (await page.evaluate(() => GK.modal.stack.length === 0 && !document.querySelector(".gk-modal-overlay:not([id])"))));
    // hide() with no argument, with a dynamic modal on top: it must be REMOVED,
    // not hidden — a hidden one would sit in the DOM with its listeners for good.
    await page.evaluate(() => GK.modal.hide());
    check("hide() on a dynamic modal removes it like close() does",
      (await page.evaluate(() => GK.modal.stack.length === 0 && !document.querySelector(".gk-modal-overlay:not([id])"))));

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
               // loadTime() sits in the columns the footer cells leave; a percent cell
               // reads the same before and after a rebuild; a number cell never wraps.
               meta: ((document.querySelector('[data-gk-table="prices"] tfoot td.gk-table-meta') || {}).textContent || "").trim(),
               share: Array.from(document.querySelectorAll('[data-gk-table="prices"] tbody tr')).map((tr) => ((tr.querySelectorAll("td")[5] || {}).textContent || "").trim()).sort().join("|"),
               numWrap: ((document.querySelector('[data-gk-table="prices"] tbody td.gk-td-num') || { style: {} }).style || {}).whiteSpace || null,
               allBox: (document.querySelector('[data-gk-table="prices"] [data-gk-select-all]') || { getAttribute() {} }).getAttribute("aria-label"),
               // A row button with href is a real <a>, encoded the same on both
               // sides, and carries no data-gk-action (which would fire
               // gk:rowaction on top of the navigation).
               // Collected and sorted: a sort puts a different row first, so the
               // set is what has to stay the same, not the first one.
               // The label colour comes from the server's table now, and a
               // column's own labels may carry a text of their own. Both used to
               // change on the first sort: green turned grey, the text vanished.
               labels: [...document.querySelectorAll('[data-gk-table="prices"] .gk-label')]
                 .map((l) => l.className.replace("gk-label ", "") + ":" + l.textContent.trim()).sort().join("|"),
               // The row checkbox carries the row id, as the server writes it.
               cbWerte: [...document.querySelectorAll('[data-gk-table="prices"] tbody .gk-cb-col input')]
                 .map((c) => c.value).sort().join(","),
               // The icons this table actually shows keep their round joins.
               // That every KIND matches PHP is checked in tests/js.test.php,
               // which compares the two lists — a browser page only ever shows
               // the handful of icons its own buttons use.
               iconRund: (() => {
                 const alle = [...document.querySelectorAll('[data-gk-table="prices"] .gk-btn svg')];
                 const rund = alle.filter((s) => s.getAttribute("stroke-linecap") === "round"
                   && s.getAttribute("stroke-linejoin") === "round").length;
                 return alle.length + ":" + rund;
               })(),
               // The label text is escaped — a row value is application data.
               labelRoh: (() => {
                 const l = [...document.querySelectorAll('[data-gk-table="prices"] .gk-label')]
                   .find((x) => x.textContent.indexOf("<b>") > -1);
                 return l ? l.innerHTML : "kein roher Text";
               })(),
               // The refused href: a plain button on both sides, never a link.
               boese: (() => {
                 const el = document.querySelector('[data-gk-table="prices"] [aria-label="Evil"]');
                 return el ? el.tagName + ":" + (el.getAttribute("href") || "-") : "fehlt";
               })(),
               // A labelled, coloured button: the client used to add gk-btn-sm and
               // ignore 'color', so both changed on the first sort.
               notiz: (() => {
                 const el = [...document.querySelectorAll('[data-gk-table="prices"] .gk-btn')]
                   .find((b) => (b.textContent || "").trim() === "Note");
                 return el ? el.className : "fehlt";
               })(),
               // Nothing may break out of an attribute: the row value holds an
               // apostrophe, and a sort re-renders every one of these.
               ausbruch: (() => {
                 const el = document.querySelector('[data-gk-table="prices"] [data-gk-params]');
                 return el ? el.getAttributeNames().sort().join(",") : "fehlt";
               })(),
               link: (() => {
                 const alle = [...document.querySelectorAll('[data-gk-table="prices"] a[aria-label="Open"]')];
                 return { hrefs: alle.map((a) => a.getAttribute("href")).sort().join("|"),
                          tags: alle.map((a) => a.tagName).join(","),
                          aktion: alle.some((a) => a.hasAttribute("data-gk-action")),
                          params: alle.map((a) => a.getAttribute("data-gk-params")).sort().join("|") };
               })() };
    });
    const good = (h) => h.cell === "right" && h.heads.Price && h.heads.Price.align === "right" && h.heads.Price.justify === "flex-end"
      && h.heads.Qty && h.heads.Qty.align === "right"
      && h.heads.State.align === "center" && h.heads.Product.align !== "right"
      && h.caption === "Price list" && h.rowBox === "Select row" && h.allBox === "Select all"
      && h.nowrap && h.footer === "111,50 €"
      && h.meta === "38 ms" && h.share === "|0.5 %|12.5 %" && h.numWrap === "nowrap"
      && h.boese === "BUTTON:-"
      && h.labels === "gk-label-blue:Sonderfall|gk-label-gray:<b>kaputt</b>|gk-label-green:active"
      && h.cbWerte === "1,2,3"
      && h.iconRund === "9:9"
      && h.labelRoh === "&lt;b&gt;kaputt&lt;/b&gt;"
      && h.notiz === "gk-btn gk-btn-icon-text gk-btn-text gk-btn-danger"
      && h.ausbruch === "aria-label,class,data-gk-params,href"
      && h.link.tags === "A,A,A" && !h.link.aktion
      && h.link.hrefs === "/artikel/a%21b%27c%28d%29%20e%2Af|/artikel/clamp|/artikel/plain"
      && h.link.params === '{"id":1}|{"id":2}|{"id":3}';
    const before = await heads();
    check("server-rendered table: numeric header and cell right, centred column centred, caption, checkbox names, nowrap, totals row, load time, percent cells", good(before));
    await page.click('[data-gk-table="prices"] [data-gk-sort="price"]');
    const after = await heads();
    check("after a client-side sort all of that is still true — and the sort really happened",
      good(after) && after.rebuilt && after.sort === "ascending" && before.first === "Anvil" && after.first === "Clamp"
      && after.footerCells === before.footerCells);
    if (!good(before) || !good(after)) console.log(JSON.stringify({ before, after }));
    // A question before following a link: the delegated handler has to stop the
    // navigation, ask, and only then go.
    const frage = await page.evaluate(() => {
      // Whichever row is on top after the sort — read its id and check against that.
      const el = document.querySelector('[data-gk-table="prices"] [aria-label="Go"]');
      const id = JSON.parse(el.getAttribute("data-gk-params")).id;
      let gefragt = false;
      window.__echt = GK.confirm;
      // "no" first: nothing may move.
      GK.confirm = (msg) => { gefragt = msg; return Promise.resolve(false); };
      el.click();
      GK.confirm = window.__echt;
      return { tag: el.tagName, href: el.getAttribute("href"), ziel: el.getAttribute("data-gk-href"),
               erwartet: "/x/" + id, gefragt, hash: location.hash };
    });
    check("a target with a question is a button, not a link, asks, and does not move on \"no\"",
      frage.tag === "BUTTON" && frage.href === null && frage.ziel === frage.erwartet
      && frage.gefragt === "Sure?" && frage.hash === "");
    // "Yes" has to actually follow the target. A fragment is a real navigation
    // that keeps the page, so the case can watch it happen.
    const gefahren = await page.evaluate(() => new Promise((fertig) => {
      const el = document.querySelector('[data-gk-table="prices"] [aria-label="Jump"]');
      const id = JSON.parse(el.getAttribute("data-gk-params")).id;
      GK.confirm = () => Promise.resolve(true);
      el.click();
      setTimeout(() => { GK.confirm = window.__echt; fertig([location.hash, "#ziel-" + id]); }, 300);
    }));
    check('… and on "yes" it really follows the target', gefahren[0] === gefahren[1]);

    // loadTime() without footer cells: "N entries · 1.23 s", before and after a rebuild.
    const meta = () => page.evaluate(() => ((document.querySelector('[data-gk-table="timed"] tfoot td.gk-table-meta') || {}).textContent || "").trim());
    const metaBefore = await meta();
    await page.click('[data-gk-table="timed"] [data-gk-sort="n"]');
    const metaAfter = await meta();
    check('a table with loadTime() alone keeps its "2 entries · 1.23 s" row through a client-side sort',
      metaBefore === "2 entries · 1.23 s" && metaAfter === metaBefore);
    if (metaAfter !== metaBefore) console.log(JSON.stringify({ metaBefore, metaAfter }));

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
