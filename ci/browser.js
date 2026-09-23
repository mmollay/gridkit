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
const withHeader = path.join(dir, "header.html");
fs.writeFileSync(withHeader, execFileSync(php, [path.join(__dirname, "browser-fixture.php"), "--header"], { maxBuffer: 64 * 1024 * 1024 }));

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
               // Nur der Hauptwert: seit die Spalte eine Unterzeile hat, stünde
               // sonst beides im textContent.
               first: (() => {
                 const td = document.querySelector('[data-gk-table="prices"] tbody tr td:not(.gk-cb-col)');
                 if (!td) return null;
                 const a = td.querySelector("a.gk-cell-link");
                 return (a || td).childNodes[0] ? ((a || td).childNodes[0].textContent || "").trim() : null;
               })(),
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
               // Über data-label, nicht über den Index: eine neue Spalte würde
               // die Messung sonst still auf eine andere Zelle schieben.
               share: [...document.querySelectorAll('[data-gk-table="prices"] tbody td[data-label="Share"]')]
                 .map((td) => (td.textContent || "").trim()).sort().join("|"),
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
               // The whole cell, byte for byte: the escaping, the encoding and the
               // empty-value guard all show up here, and the string has to be the
               // same before and after a rebuild. An expression would not catch a
               // client that encodes differently or escapes not at all.
               zelle: (() => {
                 const td = document.querySelector('[data-gk-table="prices"] tbody td[data-label="Product"]');
                 return td ? td.innerHTML.replace(/\s+/g, " ").trim() : "fehlt";
               })(),
               // The disguised scheme must not become a link on either side.
               zelleBoese: (() => {
                 const td = document.querySelector('[data-gk-table="prices"] tbody td[data-label="Qty"]');
                 return td ? (td.querySelector("a") ? "VERLINKT" : "kein Link") : "fehlt";
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
      && h.zelleBoese === "kein Link"
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
    // Wie der SERVER die verlinkten Zellen schreibt — ALLE, denn die
    // Besonderheiten verteilen sich auf die Zeilen: die eine trägt die fünf
    // Zeichen, bei denen die Kodierungen auseinandergehen, die andere Markup in
    // ihrer Unterzeile. Eine einzelne Zeile zu vergleichen ginge an beidem vorbei.
    const zellenLesen = () => page.evaluate(() =>
      [...document.querySelectorAll('[data-gk-table="prices"] tbody td')]
        .filter((td) => ["Product", "Leer", "Flag", "Qty"].indexOf(td.getAttribute("data-label")) > -1)
        .map((td) => td.getAttribute("data-label") + " » " + td.outerHTML.replace(/\s+/g, " ").trim())
        .sort().join("\n"));
    const zellenServer = await zellenLesen();
    check("server-rendered table: numeric header and cell right, centred column centred, caption, checkbox names, nowrap, totals row, load time, percent cells", good(before));
    await page.click('[data-gk-table="prices"] [data-gk-sort="price"]');
    const after = await heads();
    check("after a client-side sort all of that is still true — and the sort really happened",
      good(after) && after.rebuilt && after.sort === "ascending" && before.first === "Anvil" && after.first === "Clamp"
      && after.footerCells === before.footerCells);
    // The linked cell with its second line, byte for byte the same on both sides.
    // Both renderers draw the row with id 2 here: before the sort it is the second
    // row, after it the third — so the comparison is of one row, not of a position.
    const zellenClient = await zellenLesen();
    const zellenGleich = zellenClient === zellenServer && zellenServer.indexOf("gk-cell-sub") > -1
      && zellenServer.indexOf("gk-cell-link") > -1 && zellenServer.indexOf("%21") > -1
      // Das & der Vorlage als Entität, die muted-Klasse, und kein Link an einem
      // leeren oder falschen Wert.
      && zellenServer.indexOf("&amp;b=2") > -1 && zellenServer.indexOf("gk-td-muted") > -1
      && zellenServer.indexOf('Leer » <td><a') === -1 && zellenServer.indexOf('Flag » <td><a') === -1;
    check("every linked cell with a second line is written identically by both renderers", zellenGleich);
    if (!zellenGleich) {
      console.log("   Server:\n" + zellenServer.split("\n").map((z) => "     " + z).join("\n"));
      console.log("   Client:\n" + zellenClient.split("\n").map((z) => "     " + z).join("\n"));
    }
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

    // ── A row that opens a side sheet (1.91.0) ─────────────────────────────
    // The default viewport is 1280 wide: the docked, non-modal sheet.
    const sheetState = () => page.evaluate(() => {
      const s = document.getElementById("person-sheet");
      const cur = [...document.querySelectorAll('[data-gk-table="people"] tr[aria-current="true"]')];
      return {
        open: !s.hidden, role: s.getAttribute("role"), modal: s.getAttribute("aria-modal"),
        named: !!s.getAttribute("aria-labelledby") && s.getAttribute("aria-labelledby") === s.querySelector(".gk-sheet-title").id,
        title: s.querySelector(".gk-sheet-title").textContent,
        closeName: s.querySelector(".gk-sheet-close").getAttribute("aria-label"),
        focusIn: s.contains(document.activeElement),
        focus: document.activeElement ? (document.activeElement.id || document.activeElement.className) : null,
        current: cur.map((tr) => tr.querySelector(".gk-row-target").textContent),
        width: Math.round(s.getBoundingClientRect().width),
      };
    });
    await page.evaluate(() => {
      window.__sheetEvents = [];
      document.addEventListener("gk:sheetopen", (e) => window.__sheetEvents.push("open:" + JSON.stringify(e.detail.params)));
      document.addEventListener("gk:sheetclose", () => window.__sheetEvents.push("close"));
    });
    const rows = await page.evaluate(() => [...document.querySelectorAll('[data-gk-table="people"] tbody tr')].map((tr) => ({
      link: tr.classList.contains("gk-row-link"),
      target: tr.querySelector(".gk-row-target") ? tr.querySelector(".gk-row-target").tagName : null,
    })));
    check("a row whose main cell shows nothing stays a plain row; the others carry one button each",
      rows.length === 3 && rows.filter((r) => r.link && r.target === "BUTTON").length === 2
      && rows.filter((r) => !r.link && r.target === null).length === 1);
    // A click on a cell that is not the control: the Plan cell of Zoe.
    await page.click('[data-gk-table="people"] tr.gk-row-link:has(.gk-row-target:text("Zoe Adams")) td[data-label="Plan"]');
    let st1 = await sheetState();
    check("a click anywhere in the row opens its sheet: a named dialog, not modal on a wide screen, the focus inside",
      st1.open && st1.role === "dialog" && st1.named && st1.modal === null && st1.focusIn && st1.width === 440
      && st1.closeName === "Close" && st1.title === "Zoe Adams");
    check("… the row it shows is marked current, and gk:sheetopen carries the row's id",
      st1.current.join() === "Zoe Adams"
      && (await page.evaluate(() => window.__sheetEvents.join())) === 'open:{"id":7}');
    // Another row while it is open: the same sheet, the mark moves.
    await page.click('[data-gk-table="people"] tr.gk-row-link:has(.gk-row-target:text("Mia")) td[data-label="Plan"]');
    st1 = await sheetState();
    check("… another row while it is open: still one sheet, the mark moves along, the title follows",
      st1.open && st1.current.join() === "Mia O'Brien" && st1.title === "Mia O'Brien");
    await page.keyboard.press("Escape");
    st1 = await sheetState();
    const backTo = await page.evaluate(() => document.activeElement.textContent);
    check("Escape closes it, takes the mark off and gives the focus back to the row it came from",
      !st1.open && st1.current.length === 0 && backTo === "Mia O'Brien");
    // Keyboard only: the control is a real button.
    await page.focus('[data-gk-table="people"] .gk-row-target');
    await page.keyboard.press("Enter");
    st1 = await sheetState();
    check("Enter on the row's control opens the sheet", st1.open && st1.focusIn);
    // A mouse opens it on every row; the focus goes to the title, so no ring
    // lands on the close button each time.
    check("… the focus starts on the title — named, out of the tab order, no ring drawn",
      await page.evaluate(() => {
        const t = document.activeElement;
        return t.classList.contains("gk-sheet-title") && t.getAttribute("tabindex") === "-1"
          && getComputedStyle(t).outlineStyle === "none";
      }));
    await page.keyboard.press("Tab");
    check("… and one Tab reaches the close button",
      await page.evaluate(() => document.activeElement.classList.contains("gk-sheet-close")));
    // Tab walks out of a docked sheet: it is not modal on a wide screen.
    await page.focus("#sheet-last");
    await page.keyboard.press("Tab");
    check("… and Tab leaves a docked sheet — no trap where the page is still in use",
      !(await page.evaluate(() => document.getElementById("person-sheet").contains(document.activeElement))));
    // A confirm opened from inside the sheet answers Escape for itself.
    await page.focus("#sheet-ask");
    await page.evaluate(() => { window._sheetAnswer = "pending"; GK.confirm("Delete?").then((a) => (window._sheetAnswer = a)); });
    await page.waitForSelector(".gk-confirm-overlay");
    await page.keyboard.press("Escape");
    await page.waitForFunction(() => window._sheetAnswer !== "pending");
    st1 = await sheetState();
    check("a confirm over the sheet: Escape answers the confirm, the sheet stays",
      (await page.evaluate(() => window._sheetAnswer)) === false && st1.open);
    // Opened from inside a modal: above it. A form in it that saves closes the
    // sheet — not the modal, which is the top of GK.modal's stack.
    await page.evaluate(() => GK.sheet.close());
    await openModal();
    const ueber = await page.evaluate(() => new Promise((fertig) => {
      const sheet = GK.sheet.open("person-sheet");
      const ov = document.querySelector(".gk-modal-overlay.gk-modal-open");
      const hoeher = parseInt(getComputedStyle(sheet).zIndex, 10) > parseInt(getComputedStyle(ov).zIndex, 10);
      const f = document.createElement("form");
      f.setAttribute("data-gk-ajax", "");
      f.action = "/save";
      f.innerHTML = '<button type="submit" id="sheet-save">Save</button>';
      sheet.querySelector(".gk-sheet-body").appendChild(f);
      GK.form.bind(sheet);
      window.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ ok: true }) });
      f.querySelector("#sheet-save").click();
      setTimeout(() => fertig({ hoeher, sheetZu: sheet.hidden, stack: GK.modal.stack.length }), 300);
    }));
    check("a sheet opened from inside a modal lies above it, and a form in it that saves closes the sheet, not the modal",
      ueber.hoeher && ueber.sheetZu && ueber.stack === 1);
    await page.evaluate(() => { document.querySelector("#person-sheet form").remove(); GK.modal.closeAll(); });

    // One at a time.
    await page.evaluate(() => GK.sheet.open("other-sheet"));
    const both = await page.evaluate(() => [document.getElementById("person-sheet").hidden, document.getElementById("other-sheet").hidden]);
    check("opening a second sheet closes the first", both[0] === true && both[1] === false);
    await page.click("#other-sheet .gk-sheet-close");
    check("the close button closes it", await page.evaluate(() => document.getElementById("other-sheet").hidden));
    // Clicks that belong to something else in the row.
    const eigene = await page.evaluate(() => {
      const zoe = [...document.querySelectorAll('[data-gk-table="people"] tr.gk-row-link')]
        .find((tr) => tr.querySelector(".gk-row-target").textContent === "Zoe Adams");
      const sheet = document.getElementById("person-sheet");
      const out = {};
      zoe.querySelector('td[data-label="Site"] a').click();
      out.site = sheet.hidden && location.hash === "#site-zoe";
      zoe.querySelector("td.gk-cb-col input").click();
      out.box = sheet.hidden && zoe.querySelector("td.gk-cb-col input").checked;
      zoe.querySelector("td.gk-actions .gk-btn").click();
      out.btn = sheet.hidden;
      return out;
    });
    check("a second link, the checkbox and a row button in the row stay theirs — none of them opens the sheet",
      eigene.site && eigene.box && eigene.btn);
    // Selecting text in a row is not a click on it.
    const markiert = await page.evaluate(() => {
      const td = document.querySelector('[data-gk-table="people"] tr.gk-row-link td[data-label="Plan"]');
      const r = document.createRange();
      r.selectNodeContents(td);
      getSelection().removeAllRanges();
      getSelection().addRange(r);
      td.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, button: 0 }));
      const offen = !document.getElementById("person-sheet").hidden;
      getSelection().removeAllRanges();
      return offen;
    });
    check("… nor does the end of a text selection", markiert === false);
    // The row is the pointer target: 44px at least.
    const hoehe = await page.evaluate(() => Math.min(...[...document.querySelectorAll("tr.gk-row-link")].map((tr) => tr.getBoundingClientRect().height)));
    check("a row that opens something is at least 44px tall", hoehe >= 44);
    // The client rebuild writes the same row and the same control.
    const reihen = () => page.evaluate(() => [...document.querySelectorAll('[data-gk-table="people"] tbody tr')]
      .map((tr) => tr.className + "|" + (tr.querySelector('td[data-label="Name"]') || {}).innerHTML).sort().join("\n"));
    const vorSort = await reihen();
    await page.click('[data-gk-table="people"] [data-gk-sort="name"]');
    const nachSort = await reihen();
    check("after a client-side sort every row and its control are written byte for byte as the server wrote them",
      vorSort === nachSort && (await page.evaluate(() => !!document.querySelector('[data-gk-table="people"] thead th.gk-sortable-mi'))));
    if (vorSort !== nachSort) console.log(vorSort + "\n---\n" + nachSort);

    // ── A row that is a link ───────────────────────────────────────────────
    await page.evaluate(() => { history.replaceState(null, "", location.pathname); });
    await page.click('[data-gk-table="pages"] tbody tr:first-child td[data-label="Slug"]');
    const zuerst = await page.evaluate(() => location.hash);
    const erwartet = await page.evaluate(() => document.querySelector('[data-gk-table="pages"] tbody tr:first-child .gk-row-target').getAttribute("href"));
    check("a click on a cell of a link row follows the row's link", zuerst === erwartet && erwartet === "#page-b%20e%27ta");
    const vorher = page.context().pages().length;
    const neu = page.context().waitForEvent("page", { timeout: 3000 }).catch(() => null);
    await page.click('[data-gk-table="pages"] tbody tr:last-child td[data-label="Slug"]', { modifiers: ["Control"] });
    const tab = await neu;
    check("… and Ctrl-click on a cell opens it in a new tab, as on the link itself",
      !!tab && page.context().pages().length === vorher + 1);
    if (tab) await tab.close();
    const linkVor = await page.evaluate(() => [...document.querySelectorAll('[data-gk-table="pages"] tbody tr')].map((tr) => tr.outerHTML).sort().join(""));
    await page.click('[data-gk-table="pages"] [data-gk-sort="title"]');
    const linkNach = await page.evaluate(() => [...document.querySelectorAll('[data-gk-table="pages"] tbody tr')].map((tr) => tr.outerHTML).sort().join(""));
    check("… written identically by both renderers, the encoding of the target included", linkVor === linkNach);
    if (linkVor !== linkNach) console.log(linkVor + "\n---\n" + linkNach);

    // ── One row standing for many ──────────────────────────────────────────
    const mehr = () => page.evaluate(() => ({
      expanded: document.querySelector(".gk-table-more-toggle").getAttribute("aria-expanded"),
      visible: document.querySelector("#folded tr").checkVisibility(),
    }));
    const mehrZu = await mehr();
    await page.click(".gk-table-more-toggle");
    const mehrAuf = await mehr();
    await page.click(".gk-table-more-toggle");
    const wiederZu = await mehr();
    check("the summary row's toggle shows and hides what aria-controls names, and says so with aria-expanded",
      mehrZu.expanded === "false" && !mehrZu.visible && mehrAuf.expanded === "true" && mehrAuf.visible
      && wiederZu.expanded === "false" && !wiederZu.visible);

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
      // A live table plus a target OUTSIDE it, for the out-of-band case below.
      "/live": shell("Live", "",
        "<div id='kpi'>old numbers</div>" +
        "<div id='lt' data-gk-live-table='/live'><p>old rows</p></div>"),
    };
    // What the live table gets back: fresh rows, and a template that replaces
    // the element outside the container — including a searchable select and an
    // input the live table itself listens to.
    const liveFragment =
      "<p id='rows'>fresh rows</p>" +
      "<template data-gk-replace='#kpi'><div id='kpi'>new numbers" +
      fs.readFileSync(path.join(dir, "select.html"), "utf8") +
      "<input id='li' data-gk-live-input name='q'></div></template>";
    const liveBroken =
      "<p id='rows2'>rows after a broken template</p>" +
      // An invalid selector: querySelector THROWS on this one.
      "<template data-gk-replace='#a['><b>x</b></template>" +
      // Nothing in it, pointed at a target that exists: the target must survive.
      "<template data-gk-replace='#kpi'></template>" +
      // Pointed at the container being swapped: that would detach the very node
      // the caller still holds, and the event would reach nobody.
      "<template data-gk-replace='#lt'><div id='lt'>hijacked</div></template>";
    const nav = await browser.newPage();
    nav.on("pageerror", (e) => pageErrors.push(e.message));
    await nav.route("http://gk.test/**", (route) => {
      const u = new URL(route.request().url());
      if (u.pathname === "/gridkit.js") return route.fulfill({ contentType: "text/javascript", body: gridkitJs });
      if (u.pathname === "/b.css") return route.fulfill({ contentType: "text/css", body: "#styled-b{text-indent:42px}" });
      // The live table asks for its own address with partial=1.
      if (u.pathname === "/live" && u.searchParams.has("partial")) {
        return route.fulfill({ contentType: "text/html", body: u.searchParams.get("kaputt") ? liveBroken : liveFragment });
      }
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

    // ── On a phone, no field may be small enough for iOS to zoom into ──
    // Safari zooms the whole page into any field under 16px on the first tap and
    // leaves it there. Measured, not read out of the stylesheet: a rule in the
    // wrong media query or a later, more specific one would pass a text check.
    const phone = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    phone.on("pageerror", (e) => pageErrors.push(e.message));
    await phone.goto("file://" + fixture);
    // The form lives in a <template>; put it on the page so its fields render.
    await phone.evaluate(() => {
      const d = document.createElement("div");
      d.innerHTML = document.getElementById("form").innerHTML;
      document.body.appendChild(d);
      if (window.GK && GK.initContent) GK.initContent(d);
    });
    const kleineFelder = await phone.evaluate(() => {
      // Only the kinds that bring up a keyboard — those are the ones iOS zooms
      // into. A checkbox, a colour well or a file button opens something else.
      const tippbar = ["text", "search", "tel", "url", "email", "password", "number",
                       "date", "datetime-local", "month", "week", "time", ""];
      return [...document.querySelectorAll("input, textarea, select")]
        // Really visible: a searchable select keeps a 1x1 transparent input for
        // the form value — nobody can tap that one, so nothing zooms.
        .filter((e) => e.checkVisibility() && e.getBoundingClientRect().height > 4)
        .filter((e) => e.tagName !== "INPUT" || tippbar.indexOf(e.type) > -1)
        .map((e) => ({ was: (e.className || e.tagName) + "/" + (e.type || ""), px: parseFloat(getComputedStyle(e).fontSize) }))
        .filter((f) => f.px < 16);
    });
    check("at 390px no visible field has a font iOS would zoom into",
      kleineFelder.length === 0);
    if (kleineFelder.length) console.log("   " + JSON.stringify(kleineFelder));

    // ── The side sheet on a phone: full screen, modal, the page holds still ──
    // Card mode sets display:block on every tbody and tr — the folded rows must
    // stay folded all the same.
    const gefaltet = await phone.evaluate(() => document.querySelector("#folded tr").checkVisibility());
    check("on a phone the folded rows of a summary row stay folded in card mode", gefaltet === false);
    await phone.tap('[data-gk-table="people"] tr.gk-row-link td[data-label="Plan"]');
    const handy = await phone.evaluate(() => {
      // The end of the slide-in, not a frame of it.
      document.getAnimations().forEach((a) => a.finish());
      const s = document.getElementById("person-sheet");
      const r = s.getBoundingClientRect();
      return { open: !s.hidden, modal: s.getAttribute("aria-modal"), focusIn: s.contains(document.activeElement),
               full: Math.round(r.left) === 0 && Math.round(r.top) === 0 && Math.round(r.width) === innerWidth
                 && Math.round(r.height) === innerHeight,
               still: getComputedStyle(document.documentElement).overflow === "hidden"
                 && getComputedStyle(document.body).overflow === "hidden",
               close: Math.round(s.querySelector(".gk-sheet-close").getBoundingClientRect().height) };
    });
    check("a sheet on a phone covers the screen, is modal, takes the focus, and the page behind stops scrolling",
      handy.open && handy.modal === "true" && handy.focusIn && handy.full && handy.still);
    check("… with a close button of at least 44px", handy.close >= 44);
    // The focus starts on the title, which is not in the tab order — and
    // Shift+Tab from there used to walk straight out of a modal dialog.
    const startTitel = await phone.evaluate(() => document.activeElement.classList.contains("gk-sheet-title"));
    await phone.keyboard.press("Shift+Tab");
    const nachShift = await phone.evaluate(() => document.activeElement.id);
    check("… the focus starts on its title, and Shift+Tab from there wraps to the last control, not out",
      startTitel && nachShift === "sheet-last");
    // The trap: Tab from the last control comes round to the first, not to the page.
    await phone.focus("#sheet-last");
    await phone.keyboard.press("Tab");
    check("… and Tab stays inside it",
      await phone.evaluate(() => document.getElementById("person-sheet").contains(document.activeElement)));
    // Wider while open: it docks, and stops being modal.
    await phone.setViewportSize({ width: 1280, height: 844 });
    const breit = await phone.evaluate(async () => {
      // The media query's change event fires in the next rendering step, not on
      // resize; measured before it, this case failed once in a dozen runs. Media
      // queries are evaluated before animation frames run, so one frame is enough.
      await new Promise((r) => requestAnimationFrame(r));
      const s = document.getElementById("person-sheet");
      return { modal: s.getAttribute("aria-modal"), width: Math.round(s.getBoundingClientRect().width),
               still: getComputedStyle(document.body).overflow === "hidden" };
    });
    check("turned wide while open, it docks at 440px and stops being modal", breit.modal === null && breit.width === 440 && !breit.still);
    await phone.evaluate(() => GK.sheet.close());
    const frei = await phone.evaluate(() => getComputedStyle(document.body).overflow);
    check("… and closed, nothing is left locked", frei !== "hidden");
    await phone.close();

    // ── The docked sheet lies under the header and its user menu ──────────
    // In an admin list a sheet opens on every row click. 1.91.0 as first built
    // put it above the header: the user menu opened UNDER the sheet — Profile,
    // Settings, Sign out, the theme dots and the dark-mode switch out of reach
    // (a click landed in the sheet) while the keyboard still walked through
    // them (WCAG 2.4.11). Every layout GridKit ships, measured by hit-testing.
    const kopf = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    kopf.on("pageerror", (e) => pageErrors.push(e.message));
    await kopf.goto("file://" + withHeader);
    const varianten = [
      ["header-first, fixed header", "header-first", "gk-header-fixed"],
      ["sidebar-first, fixed header", "sidebar-first", "gk-header-fixed"],
      ["sticky header", "header-first", "gk-header-sticky"],
    ];
    for (const [name, layout, art] of varianten) {
      await kopf.evaluate(([layout, art]) => {
        GK.sheet.close();
        document.documentElement.setAttribute("data-gk-layout", layout);
        const h = document.querySelector(".gk-header");
        h.classList.remove("gk-header-fixed", "gk-header-sticky");
        h.classList.add(art);
        window.scrollTo(0, 0);
      }, [layout, art]);
      await kopf.click('[data-gk-table="people"] tr.gk-row-link td[data-label="Plan"]');
      await kopf.click(".gk-header-user");
      const lage = await kopf.evaluate(() => {
        document.getAnimations().forEach((a) => a.finish());
        const sheet = document.getElementById("person-sheet");
        const menu = document.querySelector(".gk-header-user .gk-dropdown-menu");
        const s = sheet.getBoundingClientRect();
        const m = menu.getBoundingClientRect();
        const mitte = (r) => [r.left + r.width / 2, r.top + r.height / 2];
        const ueber = (r) => { const [x, y] = mitte(r); return x > s.left && x < s.right && y > s.top && y < s.bottom; };
        // What someone has to reach in the menu: its links, buttons and switches.
        // The point at the middle of each must belong to the menu. The menu and
        // not the item itself: without the icon font a file:// page renders
        // "light_mode" as a word, which runs over the last theme dot — the
        // menu's own business, not the sheet's.
        const ziele = [...menu.querySelectorAll("a[href], button, input, [role='switch'], [tabindex]")]
          .filter((el) => el.getBoundingClientRect().height > 0);
        const verdeckt = ziele.filter((el) => {
          const [x, y] = mitte(el.getBoundingClientRect());
          return !menu.contains(document.elementFromPoint(x, y));
        }).map((el) => el.textContent.trim() || el.getAttribute("aria-label") || el.className);
        // The header lies over the sheet where the two meet — its last pixel row
        // above the sheet is the header's — and the sheet still covers the
        // list: the middle of its body is its own.
        const kopf = document.querySelector(".gk-header");
        const k = kopf.getBoundingClientRect();
        const [bx, by] = mitte(sheet.querySelector(".gk-sheet-body").getBoundingClientRect());
        return {
          offen: !sheet.hidden && document.querySelector(".gk-header-user").classList.contains("open"),
          ueberlappt: ueber(m),
          ziele: ziele.length,
          verdeckt,
          kopfOben: k.bottom > s.top && kopf.contains(document.elementFromPoint(s.left + 24, k.bottom - 1)),
          blattOben: sheet.contains(document.elementFromPoint(bx, by)),
        };
      });
      check(`${name}: the user menu opened over a docked sheet is on top — nothing in it lies under the sheet`,
        lage.offen && lage.ueberlappt && lage.ziele >= 3 && lage.verdeckt.length === 0);
      if (lage.verdeckt.length) console.log("   under the sheet: " + JSON.stringify(lage.verdeckt));
      check(`${name}: … the header lies over the sheet's top edge, and the sheet over the list`, lage.kopfOben && lage.blattOben);
      await kopf.keyboard.press("Escape");
    }
    // On a phone the sheet is the screen: it covers the header, menu button included.
    await kopf.evaluate(() => { GK.sheet.close(); document.querySelectorAll("[data-gk-dropdown].open").forEach((d) => d.classList.remove("open")); });
    await kopf.setViewportSize({ width: 390, height: 844 });
    await kopf.evaluate(() => { GK.sheet.open("person-sheet"); document.getAnimations().forEach((a) => a.finish()); });
    const handyKopf = await kopf.evaluate(() => {
      const sheet = document.getElementById("person-sheet");
      return [".gk-header-menu-toggle", ".gk-header-user"].every((sel) => {
        const r = document.querySelector(sel).getBoundingClientRect();
        return sheet.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2));
      });
    });
    check("on a phone the sheet covers the header — nothing of the page shows through it", handyKopf);
    await kopf.close();

    // ── A live table replaces what sits outside it, and binds what comes with it ──
    await nav.goto("http://gk.test/live");
    await nav.evaluate(() => new Promise((f) => {
      const c = document.getElementById("lt");
      GK.liveTable.request(c, "/live?partial=1", "/live");
      const bis = Date.now() + 4000;
      (function warte() {
        if (document.getElementById("rows") || Date.now() > bis) return f();
        setTimeout(warte, 30);
      })();
    }));
    const oob = await nav.evaluate(() => ({
      zeilen: !!document.getElementById("rows"),
      kpi: (document.getElementById("kpi") || {}).textContent || "",
      reste: document.querySelectorAll("template[data-gk-replace]").length,
      // The select and the input arrived INSIDE the replaced element: both have
      // to be bound, or the page filters into the void.
      selectGebunden: !!(document.querySelector("[data-gk-select-search]") || {})._gkBound,
      inputGebunden: !!(document.getElementById("li") || {})._gkLiveBound,
    }));
    check("a live table replaces an element outside the container and binds what came with it",
      oob.zeilen && oob.kpi.indexOf("new numbers") === 0 && oob.reste === 0
      && oob.selectGebunden && oob.inputGebunden);

    // A template that cannot be applied must cost that one replacement, not the
    // whole reload: the event, the URL and the re-binding still have to happen.
    const kaputt = await nav.evaluate(() => new Promise((f) => {
      const c = document.getElementById("lt");
      let ereignis = 0;
      document.addEventListener("gk-live-reloaded", () => { ereignis++; }, { once: true });
      GK.liveTable.request(c, "/live?partial=1&kaputt=1", "/live?kaputt=1");
      const bis = Date.now() + 4000;
      (function warte() {
        if (document.getElementById("rows2") || Date.now() > bis) {
          return f({ ereignis, zeilen: !!document.getElementById("rows2"),
                     leerNochDa: !!document.getElementById("kpi"),
                     kpiText: (document.getElementById("kpi") || {}).textContent || "",
                     entfuehrt: (document.getElementById("lt") || {}).textContent === "hijacked",
                     reste: document.querySelectorAll("template[data-gk-replace]").length });
        }
        setTimeout(warte, 30);
      })();
    }));
    check("a template with a broken selector costs one replacement, not the whole reload",
      kaputt.zeilen && kaputt.ereignis === 1 && kaputt.reste === 0);
    check("… and a template with nothing in it leaves its target alone instead of deleting it",
      kaputt.leerNochDa && kaputt.kpiText.indexOf("new numbers") === 0);
    check("… and one pointing at the swapped container itself is skipped, not applied",
      kaputt.zeilen && !kaputt.entfuehrt);

    // The focus survives a replacement: a page filters by keyboard through
    // exactly such a select, and losing it drops the user at the top of the page.
    // #li is the live input inside the replaced #kpi — it comes back with the
    // same id, so the focus can be checked on the element and not just on "not body".
    await nav.evaluate(() => document.getElementById("li").focus());
    const fokusVorher = await nav.evaluate(() => document.activeElement.id);
    await nav.evaluate(() => new Promise((f) => {
      GK.liveTable.request(document.getElementById("lt"), "/live?partial=1", "/live");
      const bis = Date.now() + 4000;
      (function w() { (document.getElementById("rows") || Date.now() > bis) ? f() : setTimeout(w, 30); })();
    }));
    const fokusNachher = await nav.evaluate(() => document.activeElement.id);
    check("the focus stays on the same control after its element was replaced",
      fokusVorher === "li" && fokusNachher === "li");
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
