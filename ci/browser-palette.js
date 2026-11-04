#!/usr/bin/env node
/**
 * The command palette (GridKit\CommandPalette + js/command-palette.js, 1.122.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup the server writes is checked there
 * (tests/command-palette.test.php). What the script does with it is checked here — the key on Windows and
 * Linux (Ctrl+K) and on a Mac (Cmd+K), "/", the trigger and GK.palette.open(); that it owns its key (the
 * global search, also on Ctrl+K, stays shut) but leaves it to a rich-text editor; typing, the ranking
 * (start > start of a word > inside a word > letters in order), umlauts both ways, keywords and the marks;
 * groups and their limit; the keys of the listbox and the combobox; Enter, Ctrl/Cmd+Enter (a new tab), the
 * pointer; the events and the veto; records from the server — answered, slow and overtaken, failed and
 * retried, empty, below the minimum, on another origin (refused), hostile — with debounce and abort; the
 * recent entries, their scope and a storage that throws; the focus held and given back, Escape over a
 * modal; 390px as a sheet without a sideways scroll; light and dark (both spellings) under the six themes,
 * measured; the page without script, with a fallback link, and without a palette from the server.
 *
 * The page is ci/browser-palette-fixture.php, served as http://gk.test/fixture.html with page.route(), which
 * also answers the records (GET http://gk.test/search?q=…) and every link the palette follows.
 *
 *     node ci/browser-palette.js                       # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-palette.js
 *     GK_SHOTS=/some/dir node ci/browser-palette.js    # also writes screenshots there
 *     GK_ICONFONT=/path/MaterialIcons.woff2 …          # the icon font, for the pictures (none is shipped)
 *     GK_VERBOSE=1 node ci/browser-palette.js           # prints each case as it runs (stderr)
 *
 * Exit code 0 when every case holds.
 */
const { execFileSync } = require("child_process");
const fs = require("fs");
const path = require("path");

let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}

const php = process.env.GK_PHP || "php";
const fixture = (mode) => execFileSync(php, [path.join(__dirname, "browser-palette-fixture.php"), ...(mode ? [mode] : [])],
  { maxBuffer: 64 * 1024 * 1024 }).toString();
const PAGES = { "/fixture.html": fixture(""), "/bare.html": fixture("bare"), "/nojs.html": fixture("nojs"), "/link.html": fixture("link") };
const SHOTS = process.env.GK_SHOTS || "";
const FONT = process.env.GK_ICONFONT || "";
const BASE = "http://gk.test";

/** Contrast of two colours as the page paints them (a canvas reads any CSS colour the browser knows). */
const PIXEL = `
  const cv = document.createElement("canvas"); cv.width = cv.height = 1;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  const px = (css, under) => { ctx.clearRect(0, 0, 1, 1); if (under) { ctx.fillStyle = "rgb(" + under.join(",") + ")"; ctx.fillRect(0, 0, 1, 1); }
    ctx.fillStyle = css; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
  const lum = (c) => { const f = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const ground = (el) => { const chain = []; for (let e = el; e; e = e.parentElement) chain.unshift(e);
    let c = [255, 255, 255]; chain.forEach((e) => { c = px(getComputedStyle(e).backgroundColor, c); }); return c; };
  const textOn = (el, pseudo) => ratio(px(getComputedStyle(el, pseudo || null).color, ground(el)), ground(el));
`;

/** The records the server knows; a query finds what holds it, accents and case aside. */
const RECORDS = {
  Customers: [
    { title: "Ecklund & Partner", subtitle: "Vienna · Customer no. 1042", url: "/customers/1042", icon: "business" },
    { title: "Müller Logistik", subtitle: "Graz · Customer no. 1050", url: "/customers/1050", icon: "business" },
    { title: "Nordlicht Media", subtitle: "Linz · Customer no. 1088", url: "/customers/1088", icon: "business" },
  ],
  Invoices: [
    { title: "INV-2026-0184", subtitle: "Ecklund & Partner · Mar 12, 2026", amount: "€1,240.00", url: "/invoices/184", icon: "receipt_long" },
    { title: "INV-2026-0201", subtitle: "Nordlicht Media · Mar 28, 2026", amount: "€890.50", url: "/invoices/201", icon: "receipt_long" },
  ],
};
const plain = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail).slice(0, 600) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };

  // ── The server ───────────────────────────────────────────────────────────
  const requests = [];      // every question to /search: { q, xrw, accept, url }
  const foreign = [];       // every request that left gk.test
  const hold = {};          // q → a promise the answer waits for
  const fail = {};          // q → how many times it fails before it answers
  let release = {};
  const holdFor = (q) => { hold[q] = new Promise((res) => { release[q] = res; }); };
  /** Until the server has been asked for q (the debounce is over, the request is out). */
  const asked = async (q) => { for (let i = 0; i < 200 && !requests.some((r) => r.q === q); i++) await new Promise((r) => setTimeout(r, 20)); };

  async function serve(route) {
    const req = route.request();
    const u = new URL(req.url());
    if (u.host !== "gk.test") {
      foreign.push(req.url());
      return route.fulfill({ status: 200, contentType: "application/json", headers: { "Access-Control-Allow-Origin": "*" }, body: '{"items":[{"title":"Foreign","url":"/foreign"}]}' });
    }
    if (PAGES[u.pathname]) return route.fulfill({ status: 200, contentType: "text/html; charset=utf-8", body: PAGES[u.pathname] });
    if (u.pathname === "/icons.woff2" && FONT) return route.fulfill({ status: 200, contentType: "font/woff2", body: fs.readFileSync(FONT) });
    if (u.pathname === "/search") {
      const q = u.searchParams.get("q") || "";
      const h = req.headers();
      requests.push({ q, xrw: h["x-requested-with"] || "", accept: h.accept || "", url: req.url() });
      if (hold[q]) await hold[q];
      if (fail[q] > 0) {
        fail[q]--;
        return route.fulfill({ status: 500, contentType: "application/json", body: '{"error":"down"}' }).catch(() => {});
      }
      let body;
      if (q === "evil") {
        body = JSON.stringify([
          { title: '<img src=x onerror="window.__pwned=2">&amp;', url: "/evil" },
          { title: "Bad link", url: "javascript:window.__pwned=3" },
          { title: "Tab link", url: "java\tscript:window.__pwned=4" },
          { title: "No link" },
          { title: { nested: 1 }, url: "/object" },
          "a string",
        ]);
      } else if (q === "flat") {
        body = JSON.stringify({ items: [{ title: "Flat record", url: "/flat" }] });
      } else {
        const groups = [];
        for (const [title, items] of Object.entries(RECORDS)) {
          const found = items.filter((it) => plain(it.title + " " + it.subtitle).includes(plain(q)));
          if (found.length) groups.push({ title, items: found });
        }
        body = JSON.stringify({ groups });
      }
      return route.fulfill({ status: 200, contentType: "application/json", body }).catch(() => {});
    }
    // Any other address: a page that says where it is.
    return route.fulfill({ status: 200, contentType: "text/html", body: `<!doctype html><title>${u.pathname}</title><p id="dest">${u.pathname}</p>` });
  }

  const addFont = async (page) => {
    if (!FONT || !fs.existsSync(FONT)) return;
    await page.addStyleTag({ content: `@font-face{font-family:"Material Icons";src:url(${BASE}/icons.woff2) format("woff2")}`
      + `.material-icons{font-family:"Material Icons";font-weight:400;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;direction:ltr;font-feature-settings:"liga"}` });
    await page.evaluate(() => document.fonts.ready);
  };

  const newPage = async (ctx, url = "/fixture.html") => {
    const page = await ctx.newPage();
    page.setDefaultTimeout(8000);
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error" && !/Failed to load resource|net::/.test(m.text())) pageErrors.push(m.text()); });
    await page.goto(BASE + url);
    return page;
  };
  const arm = (page) => page.evaluate(() => {
    window._ev = [];
    window._veto = null;
    ["gk:paletteopen", "gk:paletteselect", "gk:paletteaction"].forEach((n) => document.addEventListener(n, (e) => {
      const d = e.detail || {};
      window._ev.push({ type: n, source: d.source, action: d.action, label: d.item && d.item.label, kind: d.item && d.item.kind,
        src: d.item && d.item.source, newTab: d.newTab, query: d.query, palette: d.palette, cancelable: e.cancelable,
        openAtAction: n === "gk:paletteaction" ? document.querySelector(".gk-palette").open : null,
        data: d.item && d.item.data && d.item.data.tag, element: !!(d.item && d.item.element) });
      if (n === "gk:paletteselect" && window._veto && window._veto(d)) e.preventDefault();
    }));
  });
  const events = (page) => page.evaluate(() => window._ev.splice(0));
  const wait = (page, ms) => page.waitForTimeout(ms);
  const isOpen = (page) => page.evaluate(() => { const d = document.querySelector(".gk-palette"); return !!d && d.open; });
  const opts = (page) => page.evaluate(() => [...document.querySelectorAll(".gk-palette-option")].map((o) => o.querySelector(".gk-palette-label").textContent));
  const groups = (page) => page.evaluate(() => [...document.querySelectorAll(".gk-palette-group")].map((g) => ({
    name: g.querySelector(".gk-palette-group-label").textContent,
    items: [...g.querySelectorAll(".gk-palette-option")].map((o) => o.querySelector(".gk-palette-label").textContent),
    more: (g.querySelector(".gk-palette-more") || {}).textContent || "" })));
  const active = (page) => page.evaluate(() => {
    const input = document.querySelector(".gk-palette-input");
    const id = input.getAttribute("aria-activedescendant");
    const el = id ? document.getElementById(id) : null;
    const sel = [...document.querySelectorAll('.gk-palette-option[aria-selected="true"]')];
    return { label: el ? el.querySelector(".gk-palette-label").textContent : "", selected: sel.length, same: sel[0] === el,
      index: el ? [...document.querySelectorAll(".gk-palette-option")].indexOf(el) : -1, focusIsInput: document.activeElement === input };
  });
  const marksOf = (page, label) => page.evaluate((l) => {
    const o = [...document.querySelectorAll(".gk-palette-option")].find((x) => x.querySelector(".gk-palette-label").textContent === l);
    return o ? [...o.querySelectorAll(".gk-palette-label mark.gk-palette-hit")].map((m) => m.textContent) : null;
  }, label);
  const type = async (page, text) => {
    await page.fill(".gk-palette-input", "");
    await page.type(".gk-palette-input", text, { delay: 5 });
    await wait(page, 60);
  };
  const live = async (page) => { await wait(page, 600); return page.$eval("[data-gk-palette-live]", (p) => p.textContent); };
  const openWith = async (page, keys = "Control+k") => {
    const was = await isOpen(page);
    await page.keyboard.press(keys);
    await wait(page, 80);
    if (!was) await page.waitForFunction(() => { const d = document.querySelector(".gk-palette"); return d && d.open; }, null, { timeout: 1500 }).catch(() => {});
  };
  // A Ctrl+click into a new tab: headless Chromium now and then opens no tab, or one that stays blank, for any
  // link — a plain one on a plain page too (1 in 25 measured) — so up to three tries. What the palette has to do
  // is leave the click to the browser.
  const ctrlClick = async (ctx, locator, want) => {
    for (let n = 0; n < 3; n++) {
      const [tab] = await Promise.all([ctx.waitForEvent("page", { timeout: 4000 }).catch(() => null), locator.click({ modifiers: ["Control"] })]);
      if (!tab) continue;
      await tab.waitForURL((u) => String(u) === want, { timeout: 6000 }).catch(() => {});
      const url = tab.url();
      await tab.close();
      if (url === want) return url;
    }
    return "no tab";
  };
  const settle = async (page) => { await page.waitForFunction(() => document.querySelector(".gk-palette-list").getAttribute("aria-busy") !== "true"); await wait(page, 40); };
  // GK.search is on the page too (Ctrl+K, and Meta+K wherever it is): what the palette leaves alone, it may take.
  const shutSearch = (page) => page.evaluate(() => { if (document.querySelector(".gk-search-overlay")) GK.search.close(); });
  const store = (page) => page.evaluate(() => { const o = {}; for (let i = 0; i < localStorage.length; i++) { const k = localStorage.key(i); if (/^gk-palette/.test(k)) o[k] = localStorage.getItem(k); } return o; });

  try {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    await ctx.route("**/*", serve);
    let page = await newPage(ctx);
    await arm(page);

    // ── The trigger and the markup the script finishes ───────────────────
    const trig = await page.$eval(".gk-palette-trigger", (b) => ({ hidden: b.hidden, popup: b.getAttribute("aria-haspopup"), keys: b.getAttribute("aria-keyshortcuts"),
      kbd: b.querySelector(".gk-palette-kbd").textContent, visible: b.getClientRects().length > 0 }));
    check("trigger: shown by the script, announced as opening a dialog", !trig.hidden && trig.visible && trig.popup === "dialog", JSON.stringify(trig));
    check("trigger: the key in this computer's words (Ctrl K) and in aria-keyshortcuts (with /)", trig.kbd === "Ctrl K" && trig.keys === "Control+K /", JSON.stringify(trig));
    check("closed: nothing of the palette is drawn", await page.$eval(".gk-palette", (d) => !d.open && d.getClientRects().length === 0));

    // ── The key on Windows and Linux ─────────────────────────────────────
    await page.focus("#before");
    await openWith(page);
    check("Ctrl+K opens the palette as a modal dialog", await page.evaluate(() => { const d = document.querySelector(".gk-palette"); return d.open && d.matches(":modal"); }));
    let a = await active(page);
    check("open: the field has the focus, expanded, pointing at the first entry", a.focusIsInput && a.index === 0 && a.selected === 1 && a.same
      && await page.$eval(".gk-palette-input", (i) => i.getAttribute("aria-expanded") === "true"), JSON.stringify(a));
    check("Ctrl+K belongs to the palette: the global search (GK.search, also Ctrl+K) stays shut", !(await page.$(".gk-search-overlay")));
    let ev = await events(page);
    check("gk:paletteopen, with how it was opened (hotkey) and the palette's id", ev.length === 1 && ev[0].type === "gk:paletteopen" && ev[0].source === "hotkey" && ev[0].palette === "main", JSON.stringify(ev));
    check("the page behind is inert while it is open", await page.evaluate(() => document.elementFromPoint(5, 5) === document.querySelector(".gk-palette")
      || !document.querySelector(".gk-sidebar").contains(document.elementFromPoint(40, 300))));
    await openWith(page);
    check("Ctrl+K again closes it", !(await isOpen(page)));
    check("…and the focus is back where it was", await page.evaluate(() => document.activeElement.id === "before"));
    await page.keyboard.press("Meta+k");
    await wait(page, 60);
    check("Meta+K does nothing on Windows and Linux", !(await isOpen(page)));
    await page.keyboard.press("Control+Shift+K");
    await wait(page, 60);
    check("Ctrl+Shift+K does nothing (the modifiers have to be exact)", !(await isOpen(page)));
    await shutSearch(page);
    await page.focus("#before");
    await page.keyboard.press("/");
    await wait(page, 80);
    check('"/" opens it (slash())', await isOpen(page));
    ev = await events(page);
    check('gk:paletteopen says "slash"', ev.some((e) => e.type === "gk:paletteopen" && e.source === "slash"), JSON.stringify(ev));
    check('the "/" is not typed into the field', await page.$eval(".gk-palette-input", (i) => i.value === ""));
    await page.keyboard.press("Escape");
    await page.focus("#field");
    await page.keyboard.type("a/b");
    await wait(page, 60);
    check('"/" in a field is a letter: the palette stays shut', !(await isOpen(page)) && await page.$eval("#field", (i) => i.value === "a/b"));
    await openWith(page);
    check("Ctrl+K opens it from inside a field too", await isOpen(page));
    await page.keyboard.press("Escape");
    check("Escape closes it and the field has the focus again", !(await isOpen(page)) && await page.evaluate(() => document.activeElement.id === "field"));
    await page.focus("#editor");
    await openWith(page);
    check("inside a rich-text editor Ctrl+K stays the editor's (a link there)", !(await isOpen(page)));
    await shutSearch(page);
    await page.click("#before");
    await page.click(".gk-palette-trigger");
    await wait(page, 80);
    ev = await events(page);
    check("the trigger opens it (gk:paletteopen: trigger)", (await isOpen(page)) && ev.some((e) => e.source === "trigger"), JSON.stringify(ev));
    await page.keyboard.press("Escape");
    check("closed from the trigger, the focus goes back to the trigger", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-trigger")));
    await page.evaluate(() => GK.palette.open("inv"));
    await page.waitForFunction(() => document.querySelector(".gk-palette-option"));
    check("GK.palette.open('inv') opens it with the query typed in", (await isOpen(page)) && await page.$eval(".gk-palette-input", (i) => i.value === "inv")
      && (await opts(page))[0] === "Invoices", JSON.stringify([await isOpen(page), await opts(page), !!(await page.$(".gk-search-overlay"))]));
    ev = await events(page);
    check('…and says "api"', ev.some((e) => e.source === "api"), JSON.stringify(ev));
    await page.evaluate(() => GK.palette.close());
    check("GK.palette.close() closes it", !(await isOpen(page)));
    await page.evaluate(() => { document.activeElement.blur(); GK.palette.open(); });
    await wait(page, 60);
    await page.keyboard.press("Escape");
    check("opened while nothing had the focus, the palette gives it to its trigger", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-trigger")));

    // ── What it offers with no query ─────────────────────────────────────
    await page.focus("#before");
    await openWith(page);
    let g = await groups(page);
    const names = g.map((x) => x.name);
    check("no query: the sidebar's groups, then the server's, in their order", JSON.stringify(names) === JSON.stringify(["Main", "System", "Create", "Commands", "Pinned", "Archive"]), JSON.stringify(names));
    check("the sidebar's links, by their labels (no badge, no symbol word)", JSON.stringify(g[0].items) === JSON.stringify(["Dashboard", "Customers", "Invoices", "Monthly report", "Yearly report"]), JSON.stringify(g[0].items));
    const sideHints = await page.evaluate(() => [...document.querySelectorAll(".gk-palette-option")].filter((o) => o.querySelector(".gk-palette-hint"))
      .map((o) => o.querySelector(".gk-palette-label").textContent + "=" + o.querySelector(".gk-palette-hint").textContent));
    check("a submenu's entry names its parent; the current page says so", sideHints.includes("Monthly report=Reports") && sideHints.includes("Settings=Current page"), JSON.stringify(sideHints));
    check("a sidebar entry keeps its symbol", await page.evaluate(() => [...document.querySelectorAll(".gk-palette-option")].find((o) => o.textContent.includes("Dashboard")).querySelector(".gk-palette-icon").textContent === "dashboard"));
    const arch = g.find((x) => x.name === "Archive");
    check("at most maxPerGroup (5) under a heading, the rest counted", arch && arch.items.length === 5 && /^4 more/.test(arch.more), JSON.stringify(arch));
    check("an entry with a shortcut shows it", await page.evaluate(() => [...document.querySelectorAll(".gk-palette-option")].some((o) => o.textContent.includes("New invoice") && (o.querySelector(".gk-palette-shortcut") || {}).textContent === "N")));
    check("a link is an <a role=option> out of the tab order; a command is not a link", await page.evaluate(() => {
      const os = [...document.querySelectorAll(".gk-palette-option")];
      const link = os.find((o) => o.textContent.includes("New invoice"));
      const cmd = os.find((o) => o.textContent.includes("Dark mode"));
      return link.tagName === "A" && link.getAttribute("href") === "/invoices/new" && link.tabIndex === -1 && cmd.tagName === "DIV" && !cmd.hasAttribute("href");
    }));
    check("roles: listbox > group (named by its heading) > option", await page.evaluate(() => {
      const list = document.querySelector(".gk-palette-list");
      const gs = [...list.children];
      return list.getAttribute("role") === "listbox" && gs.every((x) => x.getAttribute("role") === "group"
        && document.getElementById(x.getAttribute("aria-labelledby")).textContent.length > 0)
        && [...list.querySelectorAll(".gk-palette-option")].every((o) => o.getAttribute("role") === "option" && o.closest('[role="group"]'));
    }));
    check("the hostile label is text, its markup never runs", await page.evaluate(() => {
      const o = [...document.querySelectorAll(".gk-palette-option")].find((x) => x.textContent.includes("onerror"));
      return !!o && !o.querySelector("img, b") && o.querySelector(".gk-palette-label").textContent === '<img src=x onerror="window.__pwned=1">"\'&</script>'
        && o.querySelector(".gk-palette-hint").textContent === "<b>bold</b>" && o.getAttribute("href") === "/hostile?a=1&b=<2>" && window.__pwned === undefined;
    }));

    // ── Typing, ranking, marks ───────────────────────────────────────────
    await type(page, "inv");
    let o = await opts(page);
    check('"inv": the label that starts with it first, then words that start with it', o[0] === "Invoices" && o.indexOf("New invoice") > 0 && o.indexOf("Print the invoice list") > 0, JSON.stringify(o));
    check('"inv": the matched letters are marked — at the start, at a word', JSON.stringify(await marksOf(page, "Invoices")) === '["Inv"]'
      && JSON.stringify(await marksOf(page, "New invoice")) === '["inv"]', JSON.stringify([await marksOf(page, "Invoices"), await marksOf(page, "New invoice")]));
    check("with a query the group of the best match comes first", (await groups(page))[0].name === "Main", JSON.stringify((await groups(page)).map((x) => x.name)));
    await page.evaluate(() => {
      window._unreg = GK.palette.register({ group: "Tests", items: () => [
        { label: "Rapid exporter", href: "/t/1" }, { label: "Prepaid", href: "/t/2" }, { label: "Monthly reporting", href: "/t/3" },
        { label: "Reports archive", href: "/t/4" }, { label: "Nothing", href: "/t/5" }, { label: "MyReport", href: "/t/6" }, { label: "Moreover", href: "/t/7" },
      ] });
    });
    await page.keyboard.press("Escape");
    await openWith(page);
    await type(page, "rep");
    g = await groups(page);
    const tests = (g.find((x) => x.name === "Tests") || { items: [] }).items;
    check('ranking: "rep" → start, start of a word (camelCase too; the earlier the better), inside a word, letters in order', JSON.stringify(tests) === JSON.stringify(["Reports archive", "MyReport", "Monthly reporting", "Prepaid", "Rapid exporter"]), JSON.stringify(tests));
    check("letters in order are marked one by one", JSON.stringify(await marksOf(page, "Rapid exporter")) === '["R","ep"]' || JSON.stringify(await marksOf(page, "Rapid exporter")) === '["Ra","p"]' || (await marksOf(page, "Rapid exporter")).join("").toLowerCase() === "rep", JSON.stringify(await marksOf(page, "Rapid exporter")));
    await type(page, "invce");
    check('letters may be skipped inside a word: "invce" → New invoice, marked inv…ce', JSON.stringify(await marksOf(page, "New invoice")) === '["inv","ce"]', JSON.stringify([await opts(page), await marksOf(page, "New invoice")]));
    await type(page, "tvl");
    check('…but a jump into another word lands on its start: "tvl" is not t(he in)v(oice) l(ist)', !(await opts(page)).includes("Print the invoice list"), JSON.stringify(await opts(page)));
    await type(page, "tinv");
    check('"tinv" → Print t(he) inv(oice list)', (await opts(page)).includes("Print the invoice list") && JSON.stringify(await marksOf(page, "Print the invoice list")) === '["t","inv"]', JSON.stringify([await opts(page), await marksOf(page, "Print the invoice list")]));
    await type(page, "rnt");
    check('the first letter starts a word: "rnt" is not (p)r(i)nt', !(await opts(page)).includes("Print the invoice list"), JSON.stringify(await opts(page)));
    await type(page, "mo re");
    check('words of a query at the start of words: "mo re" → Monthly report, before Moreover (only its letters)', (await opts(page))[0] === "Monthly report" && JSON.stringify(await marksOf(page, "Monthly report")) === '["Mo","re"]', JSON.stringify([await opts(page), await marksOf(page, "Monthly report")]));
    await type(page, "ni");
    check('"ni": the first letters of words win — N(ew) i(nvoice)', JSON.stringify(await marksOf(page, "New invoice")) === '["N","i"]', JSON.stringify(await marksOf(page, "New invoice")));
    await type(page, "cafe creme");
    check('accents: "cafe creme" finds Café Crème and marks its letters', JSON.stringify(await marksOf(page, "Café Crème")) === '["Café"," Crème"]' || JSON.stringify(await marksOf(page, "Café Crème")) === '["Café Crème"]', JSON.stringify([await opts(page), await marksOf(page, "Café Crème")]));
    for (const [q, label] of [["backerei", "Bäckerei Groß & Söhne"], ["baeckerei", "Bäckerei Groß & Söhne"], ["gross", "Bäckerei Groß & Söhne"], ["soehne", "Bäckerei Groß & Söhne"], ["BÄCK", "Bäckerei Groß & Söhne"]]) {
      await type(page, q);
      check(`umlauts and ß: "${q}" finds ${label}`, (await opts(page)).includes(label), JSON.stringify(await opts(page)));
    }
    await type(page, "baeck");
    check('"baeck" marks the letters that were written: "Bäck"', JSON.stringify(await marksOf(page, "Bäckerei Groß & Söhne")) === '["Bäck"]', JSON.stringify(await marksOf(page, "Bäckerei Groß & Söhne")));
    await type(page, "gro");
    check('"gro" marks "Gro" before the ß', JSON.stringify(await marksOf(page, "Bäckerei Groß & Söhne")) === '["Gro"]', JSON.stringify(await marksOf(page, "Bäckerei Groß & Söhne")));
    await type(page, "bill");
    check("a keyword finds an entry and marks nothing in its label", (await opts(page)).includes("New invoice") && JSON.stringify(await marksOf(page, "New invoice")) === "[]", JSON.stringify(await opts(page)));
    await type(page, "colours");
    check("the hint is searched too", (await opts(page))[0] === "Dark mode", JSON.stringify(await opts(page)));
    await type(page, "onerror");
    check("typing into a hostile label marks it as text", await page.evaluate(() => !document.querySelector(".gk-palette-option img") && window.__pwned === undefined
      && [...document.querySelectorAll(".gk-palette-hit")].some((m) => m.textContent === "onerror")));
    await type(page, "archive");
    g = await groups(page);
    check("a query is limited per group as well (5 + 4 more)", g.length >= 1 && g.find((x) => x.name === "Archive").items.length === 5 && /^4 more/.test(g.find((x) => x.name === "Archive").more), JSON.stringify(g));
    await type(page, "qqzzxx");
    await settle(page);
    check("nothing found: the sentence says what was looked for", await page.$eval(".gk-palette-empty", (p) => !p.hidden && p.textContent === "No results for “qqzzxx”"));
    check("…and is spoken", (await live(page)) === "No results for “qqzzxx”", await page.$eval("[data-gk-palette-live]", (p) => p.textContent));
    check("…the listbox is out of the way, the field points nowhere", await page.evaluate(() => document.querySelector(".gk-palette-list").getClientRects().length === 0
      && !document.querySelector(".gk-palette-input").hasAttribute("aria-activedescendant")));
    await type(page, "inv");
    check("the number of results is spoken once the list has settled", /^\d+ results$/.test(await live(page)), await page.$eval("[data-gk-palette-live]", (p) => p.textContent));
    await page.evaluate(() => window._unreg());
    await page.keyboard.press("Escape");
    await openWith(page);
    if (!(await isOpen(page))) check("debug: reopen after unregister", false, JSON.stringify(await page.evaluate(() => [document.activeElement.outerHTML.slice(0, 120), document.querySelector(".gk-palette").open, !!document.querySelector(".gk-search-overlay")])));
    await type(page, "rep");
    check("register() returns the way to take a source away", !(await groups(page)).some((x) => x.name === "Tests"));

    // ── The keys of the list ─────────────────────────────────────────────
    await type(page, "");
    const total = (await opts(page)).length;
    check("a long list scrolls inside the palette; the line of keys stays in view", await page.evaluate(() => {
      const d = document.querySelector(".gk-palette").getBoundingClientRect();
      const list = document.querySelector(".gk-palette-list");
      const foot = document.querySelector(".gk-palette-foot").getBoundingClientRect();
      return list.scrollHeight > list.clientHeight + 40 && foot.height > 0 && foot.bottom <= d.bottom + 1 && d.bottom <= innerHeight;
    }));
    // A narrow window at a desk (no finger): the phone's sheet without the line of keys.
    await page.setViewportSize({ width: 390, height: 860 });
    check("390px without a finger: no line of keys either", await page.evaluate(() => document.querySelector(".gk-palette-foot").getClientRects().length === 0));
    await page.setViewportSize({ width: 1280, height: 860 });
    await page.keyboard.press("ArrowDown");
    a = await active(page);
    check("Down moves to the next entry (aria-activedescendant and aria-selected agree)", a.index === 1 && a.same && a.selected === 1 && a.focusIsInput, JSON.stringify(a));
    await page.keyboard.press("ArrowUp");
    await page.keyboard.press("ArrowUp");
    a = await active(page);
    check("Up from the first goes round to the last", a.index === total - 1, JSON.stringify([a, total]));
    await page.keyboard.press("ArrowDown");
    check("Down from the last goes round to the first", (await active(page)).index === 0);
    await page.keyboard.press("End");
    check("End: the last entry", (await active(page)).index === total - 1);
    await page.keyboard.press("Home");
    check("Home: the first entry", (await active(page)).index === 0);
    await page.keyboard.press("PageDown");
    const pd = (await active(page)).index;
    check("Page Down jumps several entries", pd > 2 && pd < total, pd);
    await page.keyboard.press("PageUp");
    check("Page Up jumps back", (await active(page)).index === 0);
    check("the entry it moves to is scrolled into view", await page.evaluate(() => {
      const list = document.querySelector(".gk-palette-list");
      document.querySelector(".gk-palette-input").dispatchEvent(new KeyboardEvent("keydown", { key: "End", bubbles: true, cancelable: true }));
      const el = document.getElementById(document.querySelector(".gk-palette-input").getAttribute("aria-activedescendant"));
      const r = el.getBoundingClientRect(), l = list.getBoundingClientRect();
      return r.top >= l.top - 1 && r.bottom <= l.bottom + 1;
    }));
    await page.keyboard.press("Home");
    // Tab goes round inside: the field, the close button.
    await page.keyboard.press("Tab");
    check("Tab: from the field to the close button", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-close")));
    await page.keyboard.press("Tab");
    check("Tab: from the close button back to the field — the focus stays in", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-input")));
    await page.keyboard.press("Shift+Tab");
    check("Shift+Tab goes round the other way", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-close")));
    await page.keyboard.press("Enter");
    check("the close button closes it", !(await isOpen(page)) && await page.evaluate(() => document.activeElement.id === "before"));

    // ── Choosing ─────────────────────────────────────────────────────────
    await events(page);
    await openWith(page);
    await type(page, "dark");
    await page.keyboard.press("Enter");
    await wait(page, 60);
    ev = await events(page);
    const sel = ev.find((e) => e.type === "gk:paletteselect");
    const act = ev.find((e) => e.type === "gk:paletteaction");
    check("Enter on a command: gk:paletteselect (cancelable), then gk:paletteaction with its name", sel && sel.cancelable && act && act.action === "toggle-dark" && act.kind === "action" && act.query === "dark", JSON.stringify(ev));
    check("…after the palette closed, with the focus given back", act && act.openAtAction === false && !(await isOpen(page)) && await page.evaluate(() => document.activeElement.id === "before"));
    check("a command chosen is remembered by its name", await page.evaluate(() => {
      const v = JSON.parse(localStorage.getItem("gk-palette-main") || "[]");
      return v.length === 1 && v[0].t === "action" && v[0].a === "toggle-dark" && v[0].l === "Dark mode" && !("h" in v[0]);
    }), JSON.stringify(await store(page)));
    await page.evaluate(() => { window._veto = (d) => d.item.label === "New customer"; });
    await openWith(page);
    await type(page, "new cus");
    await page.keyboard.press("Enter");
    await wait(page, 100);
    check("a refused gk:paletteselect: nothing happens, the palette stays open", (await isOpen(page)) && page.url() === BASE + "/fixture.html");
    await page.evaluate(() => { window._veto = null; });
    await page.keyboard.press("Escape");
    check("…and the refused entry is not remembered", !Object.values(await store(page)).join("").includes("New customer"), JSON.stringify(await store(page)));
    // Ctrl+Enter: a new tab, this page stays.
    await openWith(page);
    await type(page, "new inv");
    let [popup] = await Promise.all([ctx.waitForEvent("page"), page.keyboard.press("Control+Enter")]);
    await popup.waitForURL((u) => String(u).startsWith(BASE), { timeout: 8000 }).catch(() => {});
    check("Ctrl+Enter opens a link in a new tab and leaves this page", popup.url() === BASE + "/invoices/new" && page.url() === BASE + "/fixture.html" && !(await isOpen(page)), popup.url());
    ev = await events(page);
    check("…gk:paletteselect says newTab", ev.some((e) => e.type === "gk:paletteselect" && e.newTab === true && e.label === "New invoice"), JSON.stringify(ev));
    await popup.close();
    // The pointer.
    await openWith(page);
    await type(page, "print");
    await settle(page);   // the records' answer redraws the list: measure and click what stays
    const box = await page.locator(".gk-palette-option").first().boundingBox();
    await page.mouse.move(box.x + 30, box.y + box.height / 2);
    await page.mouse.move(box.x + 40, box.y + box.height / 2);
    check("the pointer marks the entry it is over", (await active(page)).label === "Print the invoice list");
    const tabUrl = await ctrlClick(ctx, page.locator(".gk-palette-option").first(), BASE + "/invoices/print");
    check("Ctrl+click on a link: the browser's new tab", tabUrl === BASE + "/invoices/print", tabUrl);
    await page.mouse.click(20, 840);
    await wait(page, 60);
    check("a click on the veil closes it", !(await isOpen(page)));
    // Enter on a sidebar entry: a click on that link (the page's own navigation sees it).
    await page.evaluate(() => { window._sideClicks = 0; document.querySelector('.gk-sidebar a[href="/customers"]').addEventListener("click", (e) => { window._sideClicks++; e.preventDefault(); }); });
    await openWith(page);
    await type(page, "custo");
    check('"custo": the sidebar\'s Customers before the server\'s New customer', JSON.stringify((await opts(page)).slice(0, 2)) === '["Customers","New customer"]', JSON.stringify(await opts(page)));
    await page.keyboard.press("Enter");
    await wait(page, 60);
    check("Enter on a sidebar entry clicks that link — AJAX navigation and the page's handlers see it", await page.evaluate(() => window._sideClicks === 1));
    ev = await events(page);
    check("…gk:paletteselect carries the link element", ev.some((e) => e.type === "gk:paletteselect" && e.src === "sidebar" && e.element), JSON.stringify(ev));
    // Enter on a link: the browser goes there.
    await openWith(page);
    await type(page, "new customer");
    await Promise.all([page.waitForURL(BASE + "/customers/new"), page.keyboard.press("Enter")]);
    check("Enter on a link follows it", page.url() === BASE + "/customers/new");

    // ── Recent ───────────────────────────────────────────────────────────
    await page.goto(BASE + "/fixture.html");
    await arm(page);
    await openWith(page);
    g = await groups(page);
    check("next time, what was chosen comes first, newest first, at most recent(4) — Dark mode has dropped out", g[0].name === "Recent"
      && JSON.stringify(g[0].items) === '["New customer","Customers","Print the invoice list","New invoice"]', JSON.stringify(g[0]));
    check("…and is not listed a second time below", !g.slice(1).some((x) => x.items.includes("New customer")), JSON.stringify(g));
    check("the list is stored per palette, as short records", await page.evaluate(() => {
      const v = JSON.parse(localStorage.getItem("gk-palette-main"));
      return Array.isArray(v) && v.length === 4 && v[0].l === "New customer" && v[0].h === "/customers/new" && v[0].t === "link" && v[0].i === "person_add";
    }), JSON.stringify(await store(page)));
    await page.keyboard.press("Escape");
    // At most four (recent(4)).
    await page.evaluate(() => localStorage.setItem("gk-palette-main", JSON.stringify([
      { t: "link", l: "One", h: "/1" }, { t: "link", l: "Two", h: "/2" }, { t: "action", l: "Gone", a: "not-here" },
      { t: "link", l: "Evil", h: "javascript:alert(1)" }, { t: "link", l: "Three", h: "/3" }, { t: "record", l: "Rec", h: "/r", s: "Sub" },
      { t: "link", l: "Five", h: "/5" }, 7, null, { t: "link" }])));
    await openWith(page);
    g = await groups(page);
    check("stored entries are read with care: no javascript:, no action this page lacks, at most recent(4)", JSON.stringify(g[0].items) === '["One","Two","Three","Rec"]', JSON.stringify(g[0]));
    await page.keyboard.press("Escape");
    await page.evaluate(() => localStorage.setItem("gk-palette-main", "{not json"));
    await openWith(page);
    check("a stored value that is not JSON is ignored", (await groups(page))[0].name === "Main");
    await page.keyboard.press("Escape");
    await page.evaluate(() => localStorage.clear());

    // ── Records from the server ──────────────────────────────────────────
    requests.length = 0;
    await openWith(page);
    await type(page, "e");
    await wait(page, 250);
    check("below minLength (2) the server is not asked", requests.length === 0, JSON.stringify(requests));
    await page.fill(".gk-palette-input", "");
    await page.type(".gk-palette-input", "ecklu", { delay: 15 });
    await settle(page);
    check("debounced: one question for the word typed, not one per letter", requests.length === 1 && requests[0].q === "ecklu", JSON.stringify(requests.map((r) => r.q)));
    check("asked as XMLHttpRequest for JSON, on this origin", requests[0].xrw === "XMLHttpRequest" && /application\/json/.test(requests[0].accept) && requests[0].url.startsWith(BASE + "/search?q=ecklu"), JSON.stringify(requests[0]));
    g = await groups(page);
    const cust = g.find((x) => x.name === "Customers");
    check("the records arrive under the server's headings, after the page's entries", cust && cust.items[0] === "Ecklund & Partner" && g[g.length - 1].name === "Invoices", JSON.stringify(g));
    check("a record shows its subtitle, its amount and its symbol, and its letters are marked", await page.evaluate(() => {
      const os = [...document.querySelectorAll(".gk-palette-option")];
      const c = os.find((x) => x.textContent.includes("Ecklund & Partner") && x.getAttribute("href") === "/customers/1042");
      const inv = os.find((x) => x.getAttribute("href") === "/invoices/184");
      return c.querySelector(".gk-palette-hint").textContent === "Vienna · Customer no. 1042" && c.querySelector(".gk-palette-icon").textContent === "business"
        && c.querySelector("mark").textContent === "Ecklu" && inv.querySelector(".gk-palette-meta").textContent === "€1,240.00";
    }));
    check("the list is not busy any more, no status line drawn", await page.evaluate(() => document.querySelector(".gk-palette-list").getAttribute("aria-busy") === "false"
      && document.querySelector(".gk-palette-status").hidden && document.querySelector(".gk-palette-status").getClientRects().length === 0));
    check("the server's order stays within its groups", JSON.stringify((g.find((x) => x.name === "Invoices") || {}).items) === '["INV-2026-0184"]', JSON.stringify(g));
    // Slow and overtaken.
    requests.length = 0;
    holdFor("nord");
    const aborted = [];
    const onFail = (r) => { if (r.url().includes("/search?q=")) aborted.push(new URL(r.url()).searchParams.get("q") + ":" + r.failure().errorText); };
    page.on("requestfailed", onFail);
    await type(page, "nord");
    await asked("nord");
    await page.waitForFunction(() => document.querySelector(".gk-palette-status").getAttribute("data-gk-state") === "loading");
    check("while it asks: the status says so, the list is busy", await page.evaluate(() => {
      const s = document.querySelector(".gk-palette-status");
      return !s.hidden && s.textContent === "Searching…" && document.querySelector(".gk-palette-list").getAttribute("aria-busy") === "true";
    }));
    await page.type(".gk-palette-input", "l", { delay: 5 });
    await settle(page);
    release.nord();
    await wait(page, 200);
    check("a newer question aborts the older one", aborted.some((x) => x.startsWith("nord:")), JSON.stringify(aborted));
    g = await groups(page);
    check("the late answer is never shown: the records are those of the last query", requests.map((r) => r.q).join(",") === "nord,nordl"
      && JSON.stringify(g.filter((x) => x.name === "Customers" || x.name === "Invoices").map((x) => x.items)) === '[["Nordlicht Media"],["INV-2026-0201"]]', JSON.stringify([requests.map((r) => r.q), g]));
    // Closing aborts.
    holdFor("muller");
    await type(page, "muller");
    await asked("muller");
    await page.keyboard.press("Escape");
    release.muller();
    await wait(page, 200);
    check("closing aborts the question in flight", aborted.some((x) => x.startsWith("muller:")), JSON.stringify(aborted));
    // Back below the minimum: no new question follows, and the one in flight is aborted at once.
    holdFor("ecklun");
    await openWith(page);
    await type(page, "ecklun");
    await asked("ecklun");
    await type(page, "e");
    await wait(page, 150);
    check("back below minLength the question in flight is aborted, the status line goes", aborted.some((x) => x.startsWith("ecklun:"))
      && await page.evaluate(() => document.querySelector(".gk-palette-status").hidden), JSON.stringify(aborted));
    release.ecklun();
    await page.keyboard.press("Escape");
    page.off("requestfailed", onFail);
    // Failed, then asked again.
    fail.down = 1;
    await openWith(page);
    await type(page, "down");
    await settle(page);
    check("a failed answer: the status says so, with a way to ask again", await page.evaluate(() => {
      const s = document.querySelector(".gk-palette-status");
      return !s.hidden && s.getAttribute("data-gk-state") === "error" && s.textContent.startsWith("The records could not be searched.") && !!s.querySelector("button.gk-palette-retry");
    }));
    check("…and it is spoken", /The records could not be searched\./.test(await live(page)), await page.$eval("[data-gk-palette-live]", (p) => p.textContent));
    await page.keyboard.press("Tab");
    await page.keyboard.press("Tab");
    check("Tab reaches Try again inside the dialog", await page.evaluate(() => document.activeElement.classList.contains("gk-palette-retry")));
    requests.length = 0;
    await page.keyboard.press("Enter");
    await settle(page);
    const again = await page.evaluate(() => [document.activeElement.className, document.querySelector(".gk-palette-status").getAttribute("data-gk-state"), document.querySelector(".gk-palette-status").textContent]);
    check("Try again asks once more and the focus goes back to the field", requests.length === 1 && requests[0].q === "down" && again[0] === "gk-palette-input" && again[1] !== "error",
      JSON.stringify([requests, again]));
    // Answered, nothing found, while the page has entries.
    await type(page, "dash");
    await settle(page);
    check("no records, but entries of the page: the status says no records were found", await page.evaluate(() => {
      const s = document.querySelector(".gk-palette-status");
      return !s.hidden && s.getAttribute("data-gk-state") === "empty" && s.textContent === "No records found.";
    }) && (await opts(page))[0] === "Dashboard");
    // A flat list and hostile records.
    await type(page, "flat");
    await settle(page);
    check("a response {items: […]} is read too, under the search's group", (await groups(page)).some((x) => x.name === "Records" && x.items[0] === "Flat record"), JSON.stringify(await groups(page)));
    await type(page, "evil");
    await settle(page);
    check("hostile records: markup is text, javascript: links and records without a link are dropped", await page.evaluate(() => {
      const os = [...document.querySelectorAll(".gk-palette-group")].filter((g) => g.querySelector(".gk-palette-group-label").textContent === "Records")
        .flatMap((g) => [...g.querySelectorAll(".gk-palette-option")]);
      return os.length === 1 && os[0].querySelector(".gk-palette-label").textContent === '<img src=x onerror="window.__pwned=2">&amp;'
        && !document.querySelector(".gk-palette img") && window.__pwned === undefined;
    }), JSON.stringify(await opts(page)));
    check("another origin is refused: not one request left gk.test", foreign.length === 0, JSON.stringify(foreign));
    // Umlaut in the query reaches the server as it was typed.
    requests.length = 0;
    await type(page, "Müller");
    await settle(page);
    check("the query is sent as typed (encoded), and a record with an umlaut is marked", requests.some((r) => r.q === "Müller")
      && JSON.stringify(await marksOf(page, "Müller Logistik")) === '["Müller"]', JSON.stringify([requests, await marksOf(page, "Müller Logistik")]));
    // A record chosen is remembered and found again before the server answers.
    await type(page, "nordli");
    await settle(page);
    await page.evaluate(() => { window._veto = null; });
    await Promise.all([page.waitForURL(BASE + "/customers/1088"), (async () => {
      await page.keyboard.press("End");
      const lbl = (await active(page)).label;
      if (lbl !== "Nordlicht Media") { await page.keyboard.press("ArrowUp"); }
      await page.keyboard.press("Enter");
    })()]);
    await page.goto(BASE + "/fixture.html");
    await arm(page);
    holdFor("nordl");
    await openWith(page);
    check("a chosen record is in Recent, with its subtitle", await page.evaluate(() => {
      const g = document.querySelector(".gk-palette-group");
      return g.querySelector(".gk-palette-group-label").textContent === "Recent" && g.querySelector(".gk-palette-label").textContent === "Nordlicht Media"
        && g.querySelector(".gk-palette-hint").textContent === "Linz · Customer no. 1088";
    }));
    await type(page, "nordl");
    check("…and is found by typing while the server still thinks", (await groups(page)).some((x) => x.name === "Recent" && x.items[0] === "Nordlicht Media"), JSON.stringify(await groups(page)));
    release.nordl();
    await settle(page);
    check("…once: the server's answer does not list it twice", (await opts(page)).filter((x) => x === "Nordlicht Media").length === 1, JSON.stringify(await opts(page)));
    await page.keyboard.press("Escape");

    // ── A modal underneath ───────────────────────────────────────────────
    await page.click("#open-modal");
    await wait(page, 80);
    await openWith(page);
    check("Ctrl+K over a modal opens the palette on top", await isOpen(page));
    await page.keyboard.press("Escape");
    await wait(page, 60);
    check("Escape closes the palette, not the modal underneath — the focus is back in the modal", !(await isOpen(page))
      && await page.evaluate(() => !document.getElementById("modal").hidden && document.activeElement.id === "in-modal"));
    await page.keyboard.press("Escape");
    await wait(page, 60);
    check("…the next Escape is the modal's", await page.evaluate(() => document.getElementById("modal").hidden));

    // ── Storage: scoped, and one that throws ─────────────────────────────
    await page.evaluate(() => { localStorage.clear(); GK.theme.init({ scope: "u7" }); });
    await openWith(page);
    await type(page, "dark mode");
    await page.keyboard.press("Enter");
    await wait(page, 60);
    check("in GK.theme's scope the list is stored under gk-palette-main:u7", Object.keys(await store(page)).join() === "gk-palette-main:u7", JSON.stringify(await store(page)));
    await page.evaluate(() => {
      const fail = () => { throw new Error("storage blocked"); };
      Object.defineProperty(window, "localStorage", { configurable: true, get: () => ({ getItem: fail, setItem: fail, removeItem: fail, key: fail, length: 0, clear: fail }) });
    });
    const before = pageErrors.length;
    await openWith(page);
    await type(page, "dark");
    await page.keyboard.press("Enter");
    await wait(page, 60);
    await openWith(page);
    check("a storage that throws: the palette works and forgets", (await groups(page))[0].name === "Main" && pageErrors.length === before, JSON.stringify(pageErrors.slice(before)));
    await page.keyboard.press("Escape");
    await page.close();

    // ── A Mac ────────────────────────────────────────────────────────────
    const mac = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    await mac.addInitScript(() => {
      Object.defineProperty(Navigator.prototype, "platform", { configurable: true, get: () => "MacIntel" });
      Object.defineProperty(Navigator.prototype, "userAgentData", { configurable: true, get: () => undefined });
    });
    await mac.route("**/*", serve);
    const mp = await newPage(mac);
    const mt = await mp.$eval(".gk-palette-trigger", (b) => ({ kbd: b.querySelector(".gk-palette-kbd").textContent, keys: b.getAttribute("aria-keyshortcuts") }));
    check("Mac: the trigger shows ⌘K, aria-keyshortcuts Meta+K", mt.kbd === "⌘K" && mt.keys === "Meta+K /", JSON.stringify(mt));
    check("Mac: the line of keys shows ⌘ for the new tab", await mp.$eval("[data-gk-palette-mod]", (k) => k.textContent === "⌘"));
    await mp.focus("#before");
    await mp.keyboard.press("Control+k");
    await wait(mp, 60);
    check("Mac: Ctrl+K is not the palette's (it is the text field's there)", !(await isOpen(mp)));
    await mp.keyboard.press("Meta+k");
    await wait(mp, 60);
    check("Mac: Cmd+K opens it", await isOpen(mp));
    await type(mp, "new inv");
    [popup] = await Promise.all([mac.waitForEvent("page"), mp.keyboard.press("Meta+Enter")]);
    await popup.waitForURL((u) => String(u).startsWith(BASE), { timeout: 8000 }).catch(() => {});
    check("Mac: Cmd+Enter opens the link in a new tab", popup.url() === BASE + "/invoices/new", popup.url());
    await mac.close();

    // ── A phone: 390px ───────────────────────────────────────────────────
    const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
    await phone.route("**/*", serve);
    const pp = await newPage(phone);
    await addFont(pp);
    const pt = await pp.$eval(".gk-palette-trigger", (b) => { const r = b.getBoundingClientRect(); return { w: r.width, h: r.height, label: b.querySelector(".gk-palette-trigger-label").getClientRects().length, name: b.textContent.includes("Search") }; });
    check("390px: the trigger is its symbol at 44px, its name kept for a screen reader", pt.w >= 44 && pt.h >= 44 && pt.name, JSON.stringify(pt));
    await pp.tap(".gk-palette-trigger");
    await wait(pp, 300);
    const sheet = await pp.evaluate(() => {
      const d = document.querySelector(".gk-palette"); const r = d.getBoundingClientRect();
      const list = document.querySelector(".gk-palette-list");
      const close = document.querySelector(".gk-palette-close").getBoundingClientRect();
      const opt = document.querySelector(".gk-palette-option").getBoundingClientRect();
      return { x: r.x, y: r.y, w: r.width, h: r.height, vw: innerWidth, vh: innerHeight, sx: d.scrollWidth - d.clientWidth, lx: list.scrollWidth - list.clientWidth,
        foot: document.querySelector(".gk-palette-foot").getClientRects().length, close: [close.width, close.height], opt: opt.height, doc: document.documentElement.scrollWidth - innerWidth };
    });
    check("390px: a sheet over the whole screen", sheet.x === 0 && sheet.y === 0 && sheet.w === sheet.vw && Math.abs(sheet.h - sheet.vh) <= 1, JSON.stringify(sheet));
    check("390px: nothing scrolls sideways — not the sheet, the list or the page", sheet.sx <= 0 && sheet.lx <= 0 && sheet.doc <= 0, JSON.stringify(sheet));
    check("390px: targets of a finger (close 44px, entries 44px), no line of keys", sheet.close[0] >= 44 && sheet.close[1] >= 44 && sheet.opt >= 44 && sheet.foot === 0, JSON.stringify(sheet));
    await pp.evaluate(() => GK.palette.register([{ label: "A very long entry whose name goes on and on far beyond the width of any phone screen there is", href: "/long", hint: "With a hint that is long as well, longer than the line" }]));
    await pp.evaluate(() => GK.palette.close());
    await pp.evaluate(() => GK.palette.open("very long"));
    await wait(pp, 100);
    const cut = await pp.evaluate(() => {
      const l = [...document.querySelectorAll(".gk-palette-label")].find((x) => x.textContent.startsWith("A very long"));
      return { cut: l.scrollWidth > l.clientWidth, over: document.querySelector(".gk-palette-list").scrollWidth - document.querySelector(".gk-palette-list").clientWidth };
    });
    check("390px: a long name is cut, not pushed sideways", cut.cut && cut.over <= 0, JSON.stringify(cut));
    if (SHOTS) {
      fs.mkdirSync(SHOTS, { recursive: true });
      await pp.evaluate(() => GK.palette.close());
      await pp.evaluate(() => GK.palette.open("in"));
      await wait(pp, 250);
      await pp.screenshot({ path: path.join(SHOTS, "palette-390-light.png") });
      await pp.evaluate(() => document.body.setAttribute("data-gk-mode", "dark"));
      await pp.screenshot({ path: path.join(SHOTS, "palette-390-dark.png") });
    }
    await phone.close();

    // ── Colours: light, dark in both spellings, six themes ───────────────
    const cp = await newPage(ctx);
    await addFont(cp);
    // Colours are read when they have arrived, not halfway through a transition from the last theme.
    await cp.addStyleTag({ content: "*, *::before, *::after { transition: none !important; animation: none !important; }" });
    await cp.evaluate(() => {
      GK.palette.register({ search: () => Promise.reject(new Error("down")), minLength: 2, debounce: 0, group: "Broken" });
    });
    const measured = [];
    for (const theme of ["indigo", "ocean", "forest", "rose", "amber", "slate"]) {
      for (const mode of ["light", "dark", "class"]) {
        await cp.evaluate(([t, m]) => {
          GK.palette.close();
          document.body.setAttribute("data-gk-theme", t);
          document.body.classList.toggle("gk-dark", m === "class");
          if (m === "class") document.body.removeAttribute("data-gk-mode"); else document.body.setAttribute("data-gk-mode", m);
          GK.palette.open("in");
        }, [theme, mode]);
        await cp.waitForFunction(() => document.querySelector(".gk-palette-status").getAttribute("data-gk-state") === "error");
        const r = await cp.evaluate(PIXEL + `
          const opt = [...document.querySelectorAll(".gk-palette-option")];
          const on = opt.find((o) => o.getAttribute("aria-selected") === "true");
          const off = opt.find((o) => o.getAttribute("aria-selected") !== "true" && o.querySelector("mark") && o.querySelector(".gk-palette-hint"));
          const status = document.querySelector(".gk-palette-status");
          const input = document.querySelector(".gk-palette-input");
          const bar = () => { const bg = ground(on); const c = px(getComputedStyle(document.querySelector(".gk-palette")).backgroundColor);
            return ratio(px(getComputedStyle(on).boxShadow.match(/(rgba?\\([^)]*\\)|color\\([^)]*\\)|oklch\\([^)]*\\)|#[0-9a-f]+)/i)[0]), c); };
          ({
            label: textOn(off.querySelector(".gk-palette-label")),
            mark: textOn(off.querySelector("mark")),
            hint: textOn(off.querySelector(".gk-palette-hint")),
            group: textOn(document.querySelector(".gk-palette-group-label")),
            activeLabel: textOn(on.querySelector(".gk-palette-label")),
            activeMark: on.querySelector("mark") ? textOn(on.querySelector("mark")) : 99,
            placeholder: (() => { const v = input.value; input.value = ""; const r = textOn(input, "::placeholder"); input.value = v; return r; })(),
            status: status.getAttribute("data-gk-state") === "error" ? textOn(status.querySelector("span")) : 0,
            foot: textOn(document.querySelector(".gk-palette-foot span")),
            trigger: textOn(document.querySelector(".gk-palette-trigger-label")),
            bar: bar(),
            // The field is part of the head: no box of its own (the dark rule for every input would draw one).
            field: getComputedStyle(input).backgroundColor,
            // The focus is always in the field; the line under the head is its ring, in the accent.
            ring: (() => { const sh = getComputedStyle(document.querySelector(".gk-palette-head")).boxShadow;
              return /inset/.test(sh) && /\\b2px\\b/.test(sh) && ratio(px(sh.match(/(rgba?\\([^)]*\\)|color\\([^)]*\\)|oklch\\([^)]*\\)|#[0-9a-f]+)/i)[0]),
                px(getComputedStyle(document.querySelector(".gk-palette")).backgroundColor)) >= 3; })(),
            // A mark is underlined and heavier, on the entry's own ground — not the browser's yellow.
            markLook: (() => { const m = getComputedStyle(off.querySelector("mark"));
              return m.backgroundColor === "rgba(0, 0, 0, 0)" && /underline/.test(m.textDecorationLine) && Number(m.fontWeight) >= 700; })(),
            // The failure is said in the red made for text (--gk-danger-text), not in the grey of a hint.
            errRed: (() => { const probe = document.createElement("span"); probe.style.color = "var(--gk-danger-text)"; status.appendChild(probe);
              const want = getComputedStyle(probe).color; probe.remove();
              return getComputedStyle(status.querySelector("span")).color === want && want !== getComputedStyle(off.querySelector(".gk-palette-hint")).color; })(),
          })`);
        measured.push({ theme, mode, ...r });
        if (SHOTS && theme === "indigo" && mode !== "class") {
          fs.mkdirSync(SHOTS, { recursive: true });
          await cp.screenshot({ path: path.join(SHOTS, `palette-1280-${mode}.png`) });
        }
      }
    }
    const weak = measured.filter((m) => ["label", "mark", "hint", "group", "activeLabel", "activeMark", "placeholder", "status", "foot", "trigger"].some((k) => m[k] < 4.5) || m.bar < 3);
    check("contrast: every text 4.5:1 (entries, marks, hints, headings, the chosen entry, placeholder, error, keys, trigger) and the selection bar 3:1 — 6 themes × light, dark, .gk-dark", weak.length === 0,
      JSON.stringify(weak.length ? weak : measured.slice(0, 2)));
    check("the error line is drawn in each mode (measured above)", measured.every((m) => m.status > 0), JSON.stringify(measured.map((m) => m.status)));
    check("the field has no box of its own, in light and in both dark spellings", measured.every((m) => m.field === "rgba(0, 0, 0, 0)"), JSON.stringify(measured.map((m) => m.mode + ":" + m.field)));
    check("the focus ring of the field: a 2px line under the head, 3:1 against the palette", measured.every((m) => m.ring), JSON.stringify(measured.map((m) => m.ring)));
    check("a mark is underlined and heavier, with no background of its own", measured.every((m) => m.markLook), JSON.stringify(measured.map((m) => m.markLook)));
    check("the failure is in the red made for text", measured.every((m) => m.errRed), JSON.stringify(measured.map((m) => m.errRed)));
    await cp.close();

    // ── Without script; a link; no palette from the server ───────────────
    const off = await browser.newContext({ viewport: { width: 1280, height: 860 }, javaScriptEnabled: false });
    await off.route("**/*", serve);
    let np = await off.newPage();
    await np.goto(BASE + "/nojs.html");
    check("without script: no trigger, no palette — nothing that does nothing", await np.evaluate(() =>
      document.querySelector(".gk-palette-trigger").getClientRects().length === 0 && document.querySelector(".gk-palette").getClientRects().length === 0));
    await np.goto(BASE + "/link.html");
    check("without script, with fallback(): the trigger is a link to the search page", await np.evaluate(() => {
      const a = document.querySelector("a.gk-palette-trigger");
      return !!a && a.getClientRects().length > 0 && a.getAttribute("href") === "/search";
    }));
    await off.close();
    const lp = await newPage(ctx, "/link.html");
    await lp.click(".gk-palette-trigger");
    await wait(lp, 80);
    check("with script the link opens the palette instead", (await isOpen(lp)) && lp.url() === BASE + "/link.html");
    await lp.keyboard.press("Escape");
    const searchTab = await ctrlClick(ctx, lp.locator(".gk-palette-trigger"), BASE + "/search");
    check("…and Ctrl+click still opens the search page in a tab", searchTab === BASE + "/search" && !(await isOpen(lp)), searchTab);
    await lp.close();
    const bp = await newPage(ctx, "/bare.html");
    await bp.click(".gk-palette-trigger");
    await wait(bp, 100);
    const bare = await bp.evaluate(() => {
      const d = document.querySelector(".gk-palette");
      return { open: !!d && d.open, id: d && d.getAttribute("data-gk-palette"), label: d && d.getAttribute("aria-label"),
        input: document.activeElement.getAttribute("aria-label"), first: (document.querySelector(".gk-palette-option .gk-palette-label") || {}).textContent,
        foot: d && [...d.querySelectorAll(".gk-palette-foot > span")].map((x) => x.textContent.trim()).join(" | ") };
    });
    check("no palette from the server: the trigger and GK.palette.open() build one — the sidebar, the words of the catalogue", bare.open && bare.id === "auto"
      && bare.label === "Command palette" && bare.input === "Type a command or search…" && bare.first === "Dashboard" && bare.foot === "↑↓ move | ↵ open | Ctrl↵ new tab | Esc close", JSON.stringify(bare));
    await bp.keyboard.press("Escape");
    await bp.keyboard.press("Control+k");
    await wait(bp, 60);
    check("…without a key of its own (no server, no hotkey)", !(await isOpen(bp)));
    await bp.evaluate(() => {
      window._asked = [];
      GK.palette.register({ group: "People", search: (q, signal) => { window._asked.push(q); return Promise.resolve([{ title: "Person " + q, url: "/people/" + q }]); }, minLength: 1, debounce: 0 });
      GK.palette.register({ url: "http://other.test/people" });
      GK.palette.register("nonsense");
    });
    await bp.evaluate(() => GK.palette.open("zed"));
    await settle(bp);
    check("register({ search }) answers with records; { url } on another origin and nonsense are refused", JSON.stringify((await groups(bp)).find((x) => x.name === "People").items) === '["Person zed"]'
      && await bp.evaluate(() => window._asked.join() === "zed") && foreign.length === 0, JSON.stringify(await groups(bp)));
    await bp.close();
  } catch (e) {
    check("the run itself", false, e.stack || e.message);
  } finally {
    await browser.close();
  }
  check("no script errors on the pages", pageErrors.length === 0, pageErrors.join(" | "));

  const failed = results.filter((r) => !r.ok);
  for (const r of results) console.log((r.ok ? "  ok    " : "  FAIL  ") + r.name + (r.ok || !r.detail ? "" : "\n          " + r.detail));
  console.log(`\n${results.length - failed.length} of ${results.length} cases hold`);
  process.exit(failed.length ? 1 : 0);
})();
