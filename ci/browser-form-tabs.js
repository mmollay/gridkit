#!/usr/bin/env node
/**
 * Form::tab() (1.113.0) in a real browser, light and dark: a submit the browser blocks for a field in a
 * hidden tab opens that tab and the browser focuses the field; the error badges count and follow the user's
 * fixes; the server's errors open their tab with no script at all; the chosen tab travels in _gk_tab and,
 * with tabsRemember(), in localStorage; showIf across tabs; a form inside a page's own tabs with the same
 * keys, in a modal, sent by AJAX, with a required rich-text field, arriving later; a phone 390px wide; and a
 * copy of gridkit.js without the 1.113.0 block (the PawBot dashboard's).
 *
 * `php tests/run.php` checks the markup (tests/form-tabs.test.php); what the script does with it is checked here.
 *
 *     node ci/browser-form-tabs.js                 # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-form-tabs.js
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-form-tabs-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const html = execFileSync(php, [path.join(__dirname, "browser-form-tabs-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }).toString("utf8");
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, html);
// The same page with a gridkit.js that ends before the 1.113.0 block — the PawBot dashboard's copy.
const marker = "// === 1.113.0 — tabs in a form";
const cut = html.indexOf(marker);
const older = path.join(dir, "older.html");
fs.writeFileSync(older, cut > 0 ? html.slice(0, cut) + "</script></body></html>" : html);

/** Helpers inside the page, as window.FT; and every plain form counts its submits instead of leaving. */
const HELPERS = () => {
  window.FT = {
    form: (id) => document.getElementById(id),
    frame: (id) => FT.form(id).querySelector("[data-gk-form-tab] + .gk-tabs"),
    own: (id, sel) => [...FT.frame(id).querySelectorAll(sel)].filter((el) => el.closest(".gk-tabs") === FT.frame(id)),
    active: (id) => FT.own(id, ".gk-tab-btn").filter((b) => b.classList.contains("gk-active")).map((b) => b.dataset.tab).join(","),
    shown: (id) => FT.own(id, ".gk-tab-panel").filter((p) => getComputedStyle(p).display !== "none").map((p) => p.dataset.tab).join(","),
    badges: (id) => Object.fromEntries(FT.own(id, ".gk-tab-btn")
      .map((b) => [b.dataset.tab, (b.querySelector(".gk-tab-badge-error") || {}).textContent || ""])
      .filter((x) => x[1] !== "")),
    field: (id) => FT.form(id).querySelector("[data-gk-form-tab]").value,
    focused: () => (document.activeElement && document.activeElement.name) || "",
    btn: (id, key) => FT.own(id, ".gk-tab-btn").filter((b) => b.dataset.tab === key)[0],
    type: (el, value) => {
      el.value = value;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    },
  };
  window._submitted = {};
  window._tabClicks = 0;
  document.querySelectorAll("form").forEach((f) => {
    if (f.hasAttribute("data-gk-ajax")) return;
    f.addEventListener("submit", (e) => {
      e.preventDefault();
      window._submitted[f.id] = (window._submitted[f.id] || 0) + 1;
    });
  });
  // Tab switches: with GK.tabs (1.111.0) every switch fires gk:tabshown, and the form opens a tab through
  // GK.tabs.select(), without a click; an older script switches by a click on the tab.
  const api = () => !!(window.GK && GK.tabs && typeof GK.tabs.select === "function");
  document.addEventListener("click", (e) => { if (!api() && e.target.closest(".gk-tab-btn")) window._tabClicks++; }, true);
  document.addEventListener("gk:tabshown", () => { window._tabClicks++; }, true);
  const opener = document.getElementById("open-modal");
  if (opener) opener.addEventListener("click", () => GK.modal.show("#modal"));
};

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail: detail === undefined ? "" : JSON.stringify(detail) });

  async function open(url, opts = {}) {
    const context = await browser.newContext({
      viewport: { width: opts.width || 1000, height: 900 },
      javaScriptEnabled: opts.js !== false,
    });
    if (opts.storage) await context.addInitScript(opts.storage);
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto("file://" + url);
    if (opts.js !== false) await page.evaluate(HELPERS);
    if (opts.mode === "dark") await page.evaluate(() => { document.body.dataset.gkMode = "dark"; });
    if (opts.mode === "dark-class") await page.evaluate(() => { document.body.classList.add("gk-dark"); });
    return { page, context, errors };
  }
  const ev = (page, fn, arg) => page.evaluate(fn, arg);

  try {
    // ── 1. A submit blocked in a hidden tab ───────────────────────────────────────────────
    for (const mode of ["light", "dark", "dark-class"]) {
      const { page, context, errors } = await open(fixture, { mode });
      const t = mode === "light" ? "" : `${mode}: `;
      check(`${t}the form starts on its first tab, no badge, _gk_tab says so`,
        (await ev(page, () => [FT.active("f-main"), FT.shown("f-main"), JSON.stringify(FT.badges("f-main")), FT.field("f-main")].join("|"))) === "general|general|{}|general");
      await page.click("#f-main button[type=submit]");
      const r = await ev(page, () => ({ active: FT.active("f-main"), shown: FT.shown("f-main"), focus: FT.focused(), field: FT.field("f-main"),
        msg: document.activeElement.validationMessage, sent: window._submitted["f-main"] || 0 }));
      check(`${t}submit with a required field empty in a hidden tab opens that tab`, r.active === "address" && r.shown === "address", r);
      check(`${t}… the browser focuses the field and has its message for it`, r.focus === "m_city" && r.msg !== "", r);
      check(`${t}… _gk_tab follows, and nothing was sent`, r.field === "address" && r.sent === 0, r);
      await page.waitForTimeout(20);
      const b = await ev(page, () => FT.badges("f-main"));
      check(`${t}the tab with the two empty required fields carries "2 errors", no other tab a badge`,
        JSON.stringify(b) === JSON.stringify({ address: "2 errors" }), b);
      if (mode !== "light") {
        check(`${t}no script errors`, errors.length === 0, errors);
        await context.close();
        continue;
      }

      // One switch per submit, to the FIRST rejected field in DOM order.
      await ev(page, () => { FT.type(FT.form("f-main").querySelector("[name=m_name]"), ""); window._tabClicks = 0; });
      await page.click("#f-main button[type=submit]");
      await page.waitForTimeout(20);
      const one = await ev(page, () => ({ active: FT.active("f-main"), focus: FT.focused(), clicks: window._tabClicks, badges: FT.badges("f-main") }));
      check("with rejected fields in two tabs, the first in DOM order opens — one switch", one.active === "general" && one.focus === "m_name" && one.clicks === 1, one);
      check("both tabs carry their count", JSON.stringify(one.badges) === JSON.stringify({ general: "1 error", address: "2 errors" }), one.badges);

      // Live: fixing a field takes it off the count; a clean tab loses the badge.
      await page.fill("#f-main [name=m_name]", "Ada");
      check("typing into the rejected field takes the badge off its tab",
        JSON.stringify(await ev(page, () => FT.badges("f-main"))) === JSON.stringify({ address: "2 errors" }));
      await ev(page, () => FT.type(FT.form("f-main").querySelector("[name=m_city]"), "Wien"));
      check("one of two fixed: \"1 error\"", (await ev(page, () => FT.badges("f-main").address)) === "1 error");
      await ev(page, () => FT.type(FT.form("f-main").querySelector("[name=m_zip]"), "1010"));
      check("all fixed: no badge", JSON.stringify(await ev(page, () => FT.badges("f-main"))) === "{}");
      await page.click("#f-main button[type=submit]");
      check("a valid form is sent, from whatever tab is open", (await ev(page, () => window._submitted["f-main"])) === 1);

      // A tab is a type="button": a click on it never sends the form.
      await page.click("#f-main .gk-tab-btn[data-tab=notes]");
      await page.click("#f-main .gk-tab-btn[data-tab=general]");
      check("a click on a tab switches and does not send the form", (await ev(page, () => [FT.active("f-main"), window._submitted["f-main"]].join())) === "general,1");
      await page.click("#f-main .gk-tab-btn[data-tab=notes]");
      check("the clicked tab goes into _gk_tab", (await ev(page, () => FT.field("f-main"))) === "notes");
      await page.keyboard.press("ArrowLeft");
      check("the arrow keys too (they click)", (await ev(page, () => [FT.active("f-main"), FT.field("f-main")].join())) === "address,address");

      // A script asking checkValidity() is no submit; requestSubmit(), reportValidity() and Enter are.
      await ev(page, () => { FT.type(FT.form("f-main").querySelector("[name=m_zip]"), ""); });
      await page.click("#f-main .gk-tab-btn[data-tab=general]");
      await ev(page, () => FT.form("f-main").checkValidity());
      check("checkValidity() from a script does not move the tabs", (await ev(page, () => FT.active("f-main"))) === "general");
      await ev(page, () => FT.form("f-main").requestSubmit());
      check("requestSubmit() from a script opens the tab", (await ev(page, () => [FT.active("f-main"), FT.focused()].join())) === "address,m_zip");
      await page.click("#f-main .gk-tab-btn[data-tab=general]");
      await ev(page, () => FT.form("f-main").reportValidity());
      check("reportValidity() from a script opens the tab", (await ev(page, () => [FT.active("f-main"), FT.focused()].join())) === "address,m_zip");
      await page.click("#f-main .gk-tab-btn[data-tab=general]");
      await page.focus("#f-main [name=m_number]");
      await page.keyboard.press("Enter");
      check("Enter in a field above the tabs opens the tab", (await ev(page, () => [FT.active("f-main"), FT.focused()].join())) === "address,m_zip");

      // ── 2. Server errors, posted tab ───────────────────────────────────────────────────
      const s = await ev(page, () => ({ active: FT.active("f-server"), shown: FT.shown("f-server"), badges: FT.badges("f-server"), field: FT.field("f-server") }));
      check("errors() open the first tab with an error, over the posted tab", s.active === "address" && s.shown === "address" && s.field === "address", s);
      check("… and that tab carries \"2 errors\"", JSON.stringify(s.badges) === JSON.stringify({ address: "2 errors" }), s.badges);
      await ev(page, () => FT.type(FT.form("f-server").querySelector("[name=s_city]"), "Wien"));
      check("a message from the server counts until the form is sent again", (await ev(page, () => FT.badges("f-server").address)) === "2 errors");
      check("a posted _gk_tab opens its tab", (await ev(page, () => [FT.active("f-posted"), FT.shown("f-posted")].join())) === "notes,notes");

      // ── 3. showIf across tabs ──────────────────────────────────────────────────────────
      await page.click("#f-showif button[type=submit]");
      check("a required field showIf keeps hidden in another tab blocks nothing", (await ev(page, () => [FT.active("f-showif"), window._submitted["f-showif"]].join())) === "kind,1");
      await page.selectOption("#f-showif [name=i_kind]", "company");
      await page.click("#f-showif button[type=submit]");
      await page.waitForTimeout(20);
      const si = await ev(page, () => ({ active: FT.active("f-showif"), focus: FT.focused(), badges: FT.badges("f-showif"), sent: window._submitted["f-showif"] }));
      check("shown by the field in the first tab, it opens the second on submit", si.active === "company" && si.focus === "i_company" && si.sent === 1, si);
      check("… with its badge", JSON.stringify(si.badges) === JSON.stringify({ company: "1 error" }), si.badges);
      await page.click("#f-showif .gk-tab-btn[data-tab=kind]");
      await page.selectOption("#f-showif [name=i_kind]", "person");
      check("hidden again, it leaves no badge behind", JSON.stringify(await ev(page, () => FT.badges("f-showif"))) === "{}");

      // ── 4. Inside the page's own tabs, with the same keys ──────────────────────────────
      const outer = () => ev(page, () => {
        const o = document.getElementById("outer");
        return [...o.querySelectorAll(":scope > .gk-tab-nav > .gk-tab-btn.gk-active")].map((b) => b.dataset.tab).join() + "/" +
          [...o.querySelectorAll(":scope > .gk-tab-panel")].filter((p) => getComputedStyle(p).display !== "none").map((p) => p.dataset.tab).join();
      });
      check("the nested form takes the second-row shape, its panels no surface",
        (await ev(page, () => FT.frame("f-nested").querySelector(".gk-tab-nav").className + "|" + FT.own("f-nested", ".gk-tab-flaeche").length)) === "gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary|0");
      // The header field stands in the page's panel 'general'; the form's own first tab is 'general' too.
      check("an error above the form's tabs, inside a page tab with the same key, puts no badge on a tab",
        JSON.stringify(await ev(page, () => FT.badges("f-nested"))) === "{}", await ev(page, () => FT.badges("f-nested")));
      await page.click("#f-nested .gk-tab-btn[data-tab=address]");
      check("an inner tab with an outer tab's key leaves the outer tabs alone", (await outer()) === "general/general" && (await ev(page, () => FT.active("f-nested"))) === "address");
      await page.click("#f-nested .gk-tab-btn[data-tab=general]");
      await page.click("#f-nested button[type=submit]");
      check("the jump opens the inner tab, not the outer one with the same key",
        (await ev(page, () => [FT.active("f-nested"), FT.focused()].join())) === "address,n_city" && (await outer()) === "general/general", await outer());
      await page.click("#outer > .gk-tab-nav > .gk-tab-btn[data-tab=address]");
      await page.click("#outer > .gk-tab-nav > .gk-tab-btn[data-tab=general]");
      check("an outer switch leaves the inner choice as it was", (await ev(page, () => FT.active("f-nested"))) === "address");

      // ── 5. In a modal ──────────────────────────────────────────────────────────────────
      check("a form in a modal that is still hidden keeps the server's tab and badge",
        (await ev(page, () => [FT.active("f-modal"), JSON.stringify(FT.badges("f-modal"))].join("|"))) === 'notes|{"notes":"1 error"}');
      await page.click("#open-modal");
      await page.waitForSelector("#modal.gk-modal-open");
      await page.click("#f-modal button[type=submit]");
      const md = await ev(page, () => ({ active: FT.active("f-modal"), focus: FT.focused(), inModal: document.getElementById("modal").contains(document.activeElement) }));
      check("in a modal: the tab opens and the field inside the window has the focus", md.active === "address" && md.focus === "d_city" && md.inModal, md);
      await page.keyboard.press("Escape");

      // ── 6. AJAX errors ─────────────────────────────────────────────────────────────────
      await ev(page, () => {
        window._answer = { ok: false, errors: { a_zip: "Not a postcode" } };
        window.fetch = () => Promise.resolve({ json: () => Promise.resolve(window._answer) });
        FT.form("f-ajax").querySelector("[name=a_city]").value = "Wien";
        FT.form("f-ajax").querySelector("[name=a_zip]").value = "x";
      });
      await page.click("#f-ajax button[type=submit]");
      await page.waitForFunction(() => FT.active("f-ajax") === "address", null, { timeout: 2000 }).catch(() => {});
      const aj = await ev(page, () => ({ active: FT.active("f-ajax"), focus: FT.focused(), badges: FT.badges("f-ajax") }));
      check("an AJAX answer with an error in a hidden tab opens it and focuses the field", aj.active === "address" && aj.focus === "a_zip", aj);
      check("… with the badge", JSON.stringify(aj.badges) === JSON.stringify({ address: "1 error" }), aj.badges);
      await ev(page, () => { window._answer = { ok: false, errors: { a_name: "Taken" } }; });
      await page.click("#f-ajax button[type=submit]");
      await page.waitForFunction(() => FT.active("f-ajax") === "general", null, { timeout: 2000 }).catch(() => {});
      check("the next answer: the old badge goes, the new error's tab opens",
        (await ev(page, () => [FT.active("f-ajax"), FT.focused(), JSON.stringify(FT.badges("f-ajax"))].join("|"))) === 'general|a_name|{"general":"1 error"}');
      await page.click("#f-ajax .gk-tab-btn[data-tab=notes]");
      await ev(page, () => { FT.form("f-ajax").querySelector("[data-gk-error=a_note]").textContent = "Too long"; });
      await page.waitForTimeout(20);
      check("a message a page writes under the open field does not pull the user to an older one",
        (await ev(page, () => [FT.active("f-ajax"), JSON.stringify(FT.badges("f-ajax"))].join("|"))) === 'notes|{"general":"1 error","notes":"1 error"}');

      // ── 7. A required rich-text field, a disabled tab, a form that arrives later ─────────
      await page.click("#f-rich button[type=submit]");
      check("an empty required rich-text field in a hidden tab: its tab opens", (await ev(page, () => [FT.active("f-rich"), window._submitted["f-rich"] || 0].join())) === "body,0");
      check("… and the toast names the field (the check no longer throws before it)",
        (await ev(page, () => [...document.querySelectorAll(".gk-toast")].map((t) => t.textContent).join())).includes("Body is required."));
      await page.click("#f-disabled button[type=submit]");
      check("a required field in a disabled tab still gets its tab opened", (await ev(page, () => [FT.active("f-disabled"), FT.shown("f-disabled"), FT.focused()].join())) === "locked,locked,x_locked");
      await ev(page, () => {
        localStorage.setItem("gk-form-tab-f-late", "notes");
        const box = document.getElementById("late-box");
        box.appendChild(document.getElementById("late").content.cloneNode(true));
        GK.initContent(box);
        FT.form("f-late").addEventListener("submit", (e) => e.preventDefault());
      });
      check("a form that arrives later (GK.initContent, a modal body) opens its remembered tab",
        (await ev(page, () => [FT.active("f-late"), FT.field("f-late")].join())) === "notes,notes");
      await page.click("#f-late button[type=submit]");
      check("a form that arrives later (GK.initContent) jumps as well", (await ev(page, () => [FT.active("f-late"), FT.focused(), FT.field("f-late")].join())) === "address,l_city,address");
      check("GK.formTabs.refresh() recounts after a script changed what is valid", await ev(page, () => {
        const el = FT.form("f-late").querySelector("[name=l_note]");
        el.setCustomValidity("No.");
        GK.formTabs.refresh(FT.form("f-late"));
        const after = FT.badges("f-late").notes;
        el.setCustomValidity("");
        GK.formTabs.refresh(FT.form("f-late"));
        return after === "1 error" && !FT.badges("f-late").notes;
      }));

      // ── 8. The other styles ────────────────────────────────────────────────────────────
      const st = await ev(page, () => ["linie", "kapsel", "text"].map((s) => FT.frame("f-" + s).querySelector(".gk-tab-nav").className + "|" +
        FT.own("f-" + s, ".gk-tab-flaeche").length + "|" + FT.badges("f-" + s).address));
      check("tabsStyle(): line, pill and text rows, no surface, the badge from the server",
        st.join() === "gk-tab-nav gk-tab-nav-linie|0|1 error,gk-tab-nav gk-tab-nav-kapsel|0|1 error,gk-tab-nav gk-tab-nav-text|0|1 error", st);
      const gap = await ev(page, () => {
        const panel = FT.own("f-main", ".gk-tab-panel.gk-active")[0];
        const after = FT.form("f-main").querySelector("[name=m_confirm]").closest(".gk-field");
        return Math.round(after.getBoundingClientRect().top - panel.getBoundingClientRect().bottom);
      });
      check("a field after endTabs() keeps 16px from the surface", gap === 16, gap);
      check("no script errors (light)", errors.length === 0, errors);
      await context.close();
    }

    // ── 9. Remember ──────────────────────────────────────────────────────────────────────
    {
      const { page, context, errors } = await open(fixture);
      await page.click("#f-remember .gk-tab-btn[data-tab=notes]");
      check("tabsRemember(): the chosen tab is noted per form id", (await ev(page, () => localStorage.getItem("gk-form-tab-f-remember"))) === "notes");
      check("… a form without tabsRemember() notes nothing", (await ev(page, () => localStorage.getItem("gk-form-tab-f-main"))) === null);
      await ev(page, () => { localStorage.setItem("gk-form-tab-f-chosen", "notes"); localStorage.setItem("gk-form-tab-f-disabled", "locked"); });
      await page.reload();
      await ev(page, HELPERS);
      check("after a reload the remembered tab opens, and _gk_tab says so", (await ev(page, () => [FT.active("f-remember"), FT.shown("f-remember"), FT.field("f-remember")].join())) === "notes,notes,notes");
      check("… without taking the focus from the page", (await ev(page, () => document.activeElement === document.body)));
      check("a tab the page chose (activeTab) wins over the remembered one", (await ev(page, () => FT.active("f-chosen"))) === "address");
      check("a remembered tab that is disabled is not opened", (await ev(page, () => FT.active("f-disabled"))) === "first");
      check("no script errors (remember)", errors.length === 0, errors);
      await context.close();

      const blocked = await open(fixture, {
        storage: () => Object.defineProperty(window, "localStorage", { get() { throw new Error("blocked"); } }),
      });
      await blocked.page.click("#f-remember .gk-tab-btn[data-tab=notes]");
      check("with storage blocked the tabs still switch, and nothing throws",
        (await ev(blocked.page, () => [FT.active("f-remember"), FT.field("f-remember")].join())) === "notes,notes" && blocked.errors.length === 0, blocked.errors);
      await blocked.context.close();
    }

    // ── 10. A phone, 390px, light and both dark spellings ────────────────────────────────
    for (const mode of ["light", "dark", "dark-class"]) {
      const { page, context, errors } = await open(fixture, { width: 390, mode });
      const r = await ev(page, () => {
        const nav = FT.frame("f-server").querySelector(".gk-tab-nav");
        const btns = FT.own("f-server", ".gk-tab-btn").map((b) => b.getBoundingClientRect());
        const badge = FT.btn("f-server", "address").querySelector(".gk-tab-badge-error");
        const bb = badge.getBoundingClientRect(), ab = FT.btn("f-server", "address").getBoundingClientRect();
        const rgb = (s) => s.match(/[\d.]+/g).slice(0, 3).map(Number);
        const lum = (c) => { const [r, g, b] = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
        const cs = getComputedStyle(badge);
        const [l1, l2] = [lum(rgb(cs.color)), lum(rgb(cs.backgroundColor))].sort((a, b) => b - a);
        return {
          grid: getComputedStyle(nav).display,
          inside: btns.every((b) => b.left >= 0 && b.right <= 390 && b.width > 0),
          badgeIn: bb.left >= ab.left - 0.5 && bb.right <= ab.right + 0.5 && bb.top >= ab.top - 0.5 && bb.bottom <= ab.bottom + 0.5,
          overflow: document.scrollingElement.scrollWidth - document.scrollingElement.clientWidth,
          contrast: Math.round(((l1 + 0.05) / (l2 + 0.05)) * 100) / 100,
          text: badge.textContent,
        };
      });
      check(`390px ${mode}: the card row is the phone grid, every tab on screen`, r.grid === "grid" && r.inside, r);
      check(`390px ${mode}: the error badge stands inside its cell, the page does not scroll sideways`, r.badgeIn && r.overflow <= 0, r);
      check(`390px ${mode}: the error badge says it in words at ≥ 4.5:1`, r.text === "2 errors" && r.contrast >= 4.5, r);
      await page.click("#f-main button[type=submit]");
      check(`390px ${mode}: the jump works on a phone`, (await ev(page, () => [FT.active("f-main"), FT.focused()].join())) === "address,m_city");
      check(`390px ${mode}: no script errors`, errors.length === 0, errors);
      await context.close();
    }

    // ── 11. No script at all ─────────────────────────────────────────────────────────────
    {
      const { page, context } = await open(fixture, { js: false });
      const r = await page.evaluate(() => {
        const shown = (id) => [...document.querySelectorAll("#" + id + " .gk-tab-panel")].filter((p) => getComputedStyle(p).display !== "none").map((p) => p.dataset.tab).join();
        const badge = document.querySelector('#f-server .gk-tab-btn[data-tab=address] .gk-tab-badge-error');
        return { server: shown("f-server"), posted: shown("f-posted"), badge: badge ? badge.textContent : "" };
      });
      check("without a script the server's error tab is the one shown, with its badge", r.server === "address" && r.badge === "2 errors", r);
      check("without a script a posted tab is the one shown", r.posted === "notes", r);
      await context.close();
    }

    // ── 12. A gridkit.js without the 1.113.0 block ────────────────────────────────────────
    {
      const { page, context, errors } = await open(older);
      check("older gridkit.js: the block is really cut", cut > 0 && (await ev(page, () => typeof GK.formTabs)) === "undefined");
      await page.click("#f-main .gk-tab-btn[data-tab=notes]");
      check("older gridkit.js: the tabs switch", (await ev(page, () => [FT.active("f-main"), FT.shown("f-main")].join())) === "notes,notes");
      check("older gridkit.js: the server's badge and tab are there", (await ev(page, () => [FT.active("f-server"), FT.badges("f-server").address].join())) === "address,2 errors");
      await page.click("#f-main .gk-tab-btn[data-tab=general]");
      await page.click("#f-main button[type=submit]");
      check("older gridkit.js: no jump (documented) and nothing is sent",
        (await ev(page, () => [FT.active("f-main"), window._submitted["f-main"] || 0, FT.field("f-main")].join())) === "general,0,general");
      check("older gridkit.js: no script errors", errors.length === 0, errors);
      await context.close();
    }
  } catch (e) {
    check("the run finished", false, e.stack || String(e));
  } finally {
    await browser.close();
  }

  let failed = 0;
  for (const r of results) {
    console.log((r.ok ? "ok    " : "FAIL  ") + r.name);
    if (!r.ok) { failed++; if (r.detail) console.log("        " + r.detail); }
  }
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
})();
