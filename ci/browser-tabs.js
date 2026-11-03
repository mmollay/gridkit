#!/usr/bin/env node
/**
 * Tabs 1.111.0 in a real browser: GridKit\Tabs output and hand-written markup — the address (#<id>=<key>)
 * in and out, Back, the memory and a storage that throws, the parameter and the page's own decision, lazy
 * panels (ok, 500, a network error, retry, a whole page, another origin, `reload`, widgets inside through
 * GK.initContent, a lazy panel in a loaded panel), gk:tabchange / gk:tabshown and a refused switch (click
 * and keys), the JS API on hand-written markup, rows wider than their box (by the row's own width, the
 * keyboard, the arrows), nested rows, a modal, markup injected later, 390px, light and both dark spellings,
 * and the page with the script before 1.111.0.
 *
 * `php tests/run.php` reads the markup Tabs writes; what the script does with it only a browser shows.
 * The page is ci/browser-tabs-fixture.php, served from http://gk.test/ through page.route(), so a lazy
 * panel has an origin of its own and its answers (and failures) are made here.
 *
 *     node ci/browser-tabs.js
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-tabs.js
 *
 * Like ci/browser.js outside the suite: GridKit has no dependencies. Exit code 0 when every case holds.
 */
const { execFileSync } = require("child_process");
const path = require("path");

let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}

const php = process.env.GK_PHP || "php";
const fixture = path.join(__dirname, "browser-tabs-fixture.php");
const run = (arg) => execFileSync(php, [fixture, ...(arg ? [arg] : [])], { maxBuffer: 64 * 1024 * 1024 }).toString();
const PAGE = run();
const OLD = run("old");
const PARTS = JSON.parse(run("fragments"));
const BASE = "http://gk.test";
// Every load its own address (?n=…): a goto that only changes the fragment would be a hashchange, not a load.
let loads = 0;
const fx = (fragment = "") => BASE + "/fixture.html?n=" + ++loads + fragment;

const results = [];
const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail: ok ? "" : JSON.stringify(detail === undefined ? null : detail).slice(0, 500) });

/** The routes: the page, the old page, a page to come from, the lazy panels. */
function server(state) {
  return async (route) => {
    const req = route.request();
    const url = new URL(req.url());
    if (url.host !== "gk.test") {
      state.foreign.push(req.url());
      return route.fulfill({ status: 200, contentType: "text/html", body: PARTS.ok });
    }
    const p = url.pathname;
    const html = (body, status = 200) => route.fulfill({ status, contentType: "text/html; charset=utf-8", body });
    if (p === "/fixture.html") return html(PAGE);
    if (p === "/old.html") return html(OLD);
    if (p === "/other.html") return html('<!doctype html><title>Other</title><p id="other">Another page</p>');
    if (p.startsWith("/panel/") || p.startsWith("/modal/")) {
      state.requests.push({ path: p, xrw: req.headers()["x-requested-with"] || "", method: req.method() });
      if (state.delay) await new Promise((r) => setTimeout(r, state.delay));
      if (p === "/panel/ok" || p === "/panel/first" || p === "/panel/inner") return html(PARTS.ok);
      if (p === "/panel/error") return html(PARTS.error, 500);
      if (p === "/panel/net") return state.netOk ? html(PARTS.ok) : route.abort("failed");
      if (p === "/panel/time") return html(`<p class="probe-time">${++state.time}</p>`);
      if (p === "/panel/widgets") return html(PARTS.widgets);
      if (p === "/panel/page") return html(PARTS.page);
      if (p === "/modal/tabs") return html(PARTS.modal);
    }
    return html("not found", 404);
  };
}

/** Everything about one row that the checks compare. */
function rowState(id) {
  const w = document.getElementById(id);
  if (!w) return null;
  const own = (sel) => [...w.querySelectorAll(sel)].filter((e) => e.closest(".gk-tabs") === w);
  const tabs = own(".gk-tab-btn");
  const panels = own(".gk-tab-panel");
  const shown = panels.filter((p) => getComputedStyle(p).display !== "none").map((p) => p.dataset.tab);
  return {
    active: tabs.filter((b) => b.classList.contains("gk-active")).map((b) => b.dataset.tab),
    selected: tabs.filter((b) => b.getAttribute("aria-selected") === "true").map((b) => b.dataset.tab),
    tabindex: tabs.map((b) => b.tabIndex),
    shown,
    role: (w.querySelector(".gk-tab-nav") || {}).getAttribute ? w.querySelector(".gk-tab-nav").getAttribute("role") : null,
  };
}

(async () => {
  const browser = await chromium.launch();
  const pageErrors = [];
  const fresh = async (opts = {}) => {
    const state = { requests: [], foreign: [], time: 0, netOk: false, delay: 0 };
    const ctx = await browser.newContext({ viewport: { width: opts.width || 1100, height: 900 }, reducedMotion: opts.motion || "no-preference" });
    if (opts.init) await ctx.addInitScript(opts.init);
    await ctx.route("**/*", server(state));
    const page = await ctx.newPage();
    page.on("pageerror", (e) => pageErrors.push((opts.label || "") + ": " + e.message));
    page.state = state;
    page.ctx = ctx;
    return page;
  };
  const row = (page, id) => page.evaluate(rowState, id);
  const hash = (page) => page.evaluate(() => location.hash);
  const settle = (page, ms = 60) => page.waitForTimeout(ms);

  try {
    // ── 1. The address in and out, Back ─────────────────────────────────────────────────────────
    {
      const page = await fresh({ label: "address" });
      await page.goto(fx("#t-api=d"));
      let r = await row(page, "t-api");
      check("address in: #t-api=d opens Delta (panel, class, aria-selected, the one tab stop)", r.active.join() === "d" && r.selected.join() === "d" && r.shown.join() === "d" && r.tabindex.filter((t) => t === 0).length === 1, r);
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      check("address out: a click writes #t-api=b", (await hash(page)) === "#t-api=b", await hash(page));
      const len0 = await page.evaluate(() => history.length);
      await page.click('#t-api .gk-tab-btn[data-tab="a"]');
      await page.click('#t-api .gk-tab-btn[data-tab="d"]');
      check("address out: a switch adds no history entry (replaceState)", (await page.evaluate(() => history.length)) === len0, await page.evaluate(() => history.length));
      // the arrow keys go the same way and step over the disabled tab
      await page.focus('#t-api .gk-tab-btn[data-tab="d"]');
      await page.keyboard.press("ArrowLeft");
      r = await row(page, "t-api");
      check("keys: ArrowLeft from Delta steps over disabled Gamma to Beta, and the address follows", r.active.join() === "b" && (await hash(page)) === "#t-api=b", { r, h: await hash(page) });

      await page.evaluate(() => localStorage.clear());   // t-api remembers: without this the memory, not the deep link, would open Beta
      await page.goto(fx("#t-inner=i2"));
      r = await row(page, "t-inner");
      const outer = await row(page, "t-api");
      check("address in, nested: #t-inner=i2 selects the inner tab AND shows the outer panel around it", r.active.join() === "i2" && outer.active.join() === "b" && outer.shown.join() === "b" && r.shown.join() === "i2", { r, outer });

      await page.evaluate(() => localStorage.clear());   // t-api remembers too: the first tab, not the last one, is the fallback here
      await page.goto(fx("#t-api=c"));
      r = await row(page, "t-api");
      check("address in: a disabled tab is not opened, and the address says what is shown", r.active.join() === "a" && (await hash(page)) === "#t-api=a", { r, h: await hash(page) });
      await page.goto(fx("#t-api=nope"));
      check("address in: an unknown key opens the first tab and the address is corrected", (await row(page, "t-api")).active.join() === "a" && (await hash(page)) === "#t-api=a", await hash(page));

      await page.goto(fx("#top&t-api=d&t-inner=i2"));
      r = await row(page, "t-api");
      check("address in: several pairs and a plain anchor in one fragment — Delta for t-api (its own pair wins over the inner deep link)", r.active.join() === "d" && (await row(page, "t-inner")).active.join() === "i2", r);
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      check("address out: the anchor and the other pair stay where they are", (await hash(page)) === "#top&t-api=b&t-inner=i2", await hash(page));
      await page.click('#t-inner .gk-tab-btn[data-tab="i1"]');
      check("address out: the inner row rewrites only its own pair", (await hash(page)) === "#top&t-api=b&t-inner=i1", await hash(page));

      // Back: a switch made no entry, so Back leaves the page; links to #<id>=<key> and Back between them switch.
      await page.goto(BASE + "/other.html");
      await page.goto(fx());
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      await page.click('#t-api .gk-tab-btn[data-tab="d"]');
      await page.goBack();
      check("Back after two switches leaves the page (no entry per switch)", (await page.evaluate(() => location.pathname)) === "/other.html", await page.evaluate(() => location.href));
      await page.goForward();
      await page.evaluate(() => { location.hash = "#t-api=d"; });
      await settle(page);
      await page.evaluate(() => { location.hash = "#t-api=b"; });
      await settle(page);
      check("a link to #t-api=b switches the tab (hashchange)", (await row(page, "t-api")).active.join() === "b", await row(page, "t-api"));
      await page.goBack();
      await settle(page);
      check("Back between two such links switches back (Delta)", (await row(page, "t-api")).active.join() === "d" && (await hash(page)) === "#t-api=d", { r: await row(page, "t-api"), h: await hash(page) });
      await page.evaluate(() => { location.hash = "#t-api=c"; });
      await settle(page);
      check("a link to a disabled tab changes nothing, and the address is corrected", (await row(page, "t-api")).active.join() === "d" && (await hash(page)) === "#t-api=d", { r: await row(page, "t-api"), h: await hash(page) });
      await page.fill("#probe-dirty", "unsaved");
      await page.evaluate(() => { location.hash = "#t-cancel=preview"; });
      await settle(page);
      check("a link whose switch is refused (unsaved changes): the tab stays, the address says so", (await row(page, "t-cancel")).active.join() === "edit" && (await hash(page)) === "#t-cancel=edit", { r: await row(page, "t-cancel"), h: await hash(page) });
      await page.goto(fx("#t-api=d"));
      await page.evaluate(() => { location.hash = "#t-inner=i2"; });
      await settle(page);
      check("a link to an inner pair opens the outer panel around it — also after the outer came from the address", (await row(page, "t-api")).active.join() === "b" && (await row(page, "t-inner")).active.join() === "i2", [await row(page, "t-api"), await row(page, "t-inner")]);
      await page.evaluate(() => { location.hash = "#t-api=a&t-inner=i1"; });
      await settle(page);
      check("… but an outer row that has its own pair follows that pair", (await row(page, "t-api")).active.join() === "a" && (await row(page, "t-inner")).active.join() === "i1", [await row(page, "t-api"), await row(page, "t-inner")]);
      await page.ctx.close();
    }

    // ── 2. Memory, the parameter, the page's decision, a storage that throws ────────────────────
    {
      const page = await fresh({ label: "memory" });
      await page.goto(fx());
      await page.click('#t-mem .gk-tab-btn[data-tab="three"]');
      await page.click('#t-api .gk-tab-btn[data-tab="d"]');
      const stored = await page.evaluate(() => [localStorage.getItem("gk-tab-t-mem"), localStorage.getItem("gk-tab-t-api")]);
      check("memory: the choice is stored per row as gk-tab-<id>", stored.join() === "three,d", stored);
      check("memory only: a row without hash() leaves the address alone", !(await hash(page)).includes("t-mem"), await hash(page));
      await page.goto(fx());
      check("memory: after a reload Three and Delta are open again", (await row(page, "t-mem")).active.join() === "three" && (await row(page, "t-api")).active.join() === "d", [await row(page, "t-mem"), await row(page, "t-api")]);
      await page.goto(fx("#t-api=b"));
      check("the address wins over the memory", (await row(page, "t-api")).active.join() === "b", await row(page, "t-api"));
      check("the tab a page opens with — from the address or the memory — fires no gk:tabchange or gk:tabshown", (await page.evaluate(() => probeLog.filter((l) => l[0] !== "load").length)) === 0, await page.evaluate(() => probeLog));

      // the parameter: the server rendered ?ptab=two; the memory says three — the address wins
      await page.evaluate(() => localStorage.setItem("gk-tab-t-param", "three"));
      await page.goto(BASE + "/fixture.html?ptab=two");
      check("parameter: ?ptab=two wins over the memory (three)", (await row(page, "t-param")).active.join() === "two", await row(page, "t-param"));
      await page.click('#t-param .gk-tab-btn[data-tab="one"]');
      check("parameter: a switch rewrites ?ptab= in place (no new parameter elsewhere)", (await page.evaluate(() => location.search)) === "?ptab=one", await page.evaluate(() => location.search));
      await page.goto(BASE + "/fixture.html?other=1");
      await page.click('#t-param .gk-tab-btn[data-tab="three"]');   // the row that has param()
      check("parameter: an address without it gets none added on a switch of the row that reads it", (await page.evaluate(() => location.search)) === "?other=1", await page.evaluate(() => location.search));

      // the page's decision (active('b')) beats the address and the memory, and corrects the address
      await page.evaluate(() => localStorage.setItem("gk-tab-t-page", "a"));
      await page.goto(fx("#t-page=c"));
      check("active(): the page's decision beats the address and the memory; the address says what is shown", (await row(page, "t-page")).active.join() === "b" && (await hash(page)) === "#t-page=b", { r: await row(page, "t-page"), h: await hash(page) });
      await page.ctx.close();

      const locked = await fresh({ label: "no storage", init: () => {
        Object.defineProperty(window, "localStorage", { get() { throw new Error("blocked"); } });
      } });
      await locked.goto(fx());
      await locked.click('#t-mem .gk-tab-btn[data-tab="three"]');
      check("storage that throws: the switch works", (await row(locked, "t-mem")).active.join() === "three", await row(locked, "t-mem"));
      await locked.goto(fx());
      check("storage that throws: nothing is remembered, nothing breaks", (await row(locked, "t-mem")).active.join() === "one", await row(locked, "t-mem"));
      await locked.ctx.close();
    }

    // ── 3. Lazy panels ───────────────────────────────────────────────────────────────────────────
    for (const mode of ["light", "dark", "gk-dark"]) {
      const page = await fresh({ label: "lazy " + mode });
      await page.goto(fx());
      if (mode === "dark") await page.evaluate(() => { document.documentElement.setAttribute("data-gk-mode", "dark"); document.body.setAttribute("data-gk-mode", "dark"); });
      if (mode === "gk-dark") await page.evaluate(() => document.body.classList.add("gk-dark"));
      const st = page.state;
      const panel = (key) => page.evaluate((k) => {
        const p = document.querySelector(`#t-lazy > .gk-tab-panel[data-tab="${k}"]`);
        return { html: p.innerHTML, busy: p.getAttribute("aria-busy"), text: p.textContent.trim(), error: !!p.querySelector(".gk-empty[role=alert]"), retry: !!p.querySelector(".gk-empty button"), focus: document.activeElement === p || p.contains(document.activeElement), dim: p.firstElementChild ? +getComputedStyle(p.firstElementChild).opacity : 1 };
      }, key);
      const tab = (key) => page.click(`#t-lazy .gk-tab-btn[data-tab="${key}"]`);
      if (mode === "light") {
        await settle(page, 150);
        const paths = st.requests.map((r) => r.path);
        check("lazy: at load only the tab a row opens with is fetched — no other lazy panel, none in a panel that is not shown", paths.join() === "/panel/first", paths);
        const first = await page.evaluate(() => document.querySelector('#t-lazy-first > .gk-tab-panel[data-tab="first"]').innerHTML);
        check("lazy: a row that opens on a lazy tab has it loaded", first.includes("probe-ok"), first);
        const fb = await page.evaluate(() => { const a = document.querySelector('#t-lazy > .gk-tab-panel[data-tab="ok"] .gk-tab-fallback a'); return a ? [a.getAttribute("href"), a.textContent] : null; });
        check("lazy: until then the panel holds the link of a page without the script", fb && fb[0] === "/panel/ok" && fb[1] === "Open “Loads”", fb);
        await page.click('#t-lazy-first .gk-tab-btn[data-tab="second"]');
        await settle(page, 150);
        const inner = await page.evaluate(() => document.querySelector('#t-lazy-inner > .gk-tab-panel[data-tab="x"]').innerHTML);
        check("lazy: opening the outer panel loads the lazy tab a row inside it opens with", st.requests.some((r) => r.path === "/panel/inner") && inner.includes("probe-ok"), { inner, paths: st.requests.map((r) => r.path) });
        st.requests.length = 0;
      }
      st.delay = 250;
      await tab("ok");
      const during = await panel("ok");
      check(`lazy (${mode}): while it loads the panel is aria-busy and shows the skeleton with a word for a screen reader`, during.busy === "true" && during.html.includes("gk-tab-loading") && during.html.includes("gk-skeleton") && during.text === "Loading…", during);
      const sk = await page.evaluate(() => { const s = document.querySelector("#t-lazy .gk-tab-loading .gk-skeleton"); const p = s.closest(".gk-tab-panel"); const r = s.getBoundingClientRect(); return [getComputedStyle(s).backgroundImage !== "none", r.height > 8, r.width > p.clientWidth * 0.5, Math.round(r.width), p.clientWidth]; });
      check(`lazy (${mode}): the skeleton is drawn as lines across the panel`, sk[0] && sk[1] && sk[2], sk);
      await page.waitForTimeout(400);
      st.delay = 0;
      let p = await panel("ok");
      check(`lazy (${mode}): the answer is in the panel, aria-busy is gone`, p.html.includes('class="probe-ok"') && p.busy === null, p);
      if (mode === "light") {
        check("lazy: the request says X-Requested-With: XMLHttpRequest", st.requests[0] && st.requests[0].xrw === "XMLHttpRequest" && st.requests[0].path === "/panel/ok", st.requests);
        await tab("plain");
        await tab("ok");
        await settle(page, 150);
        check("lazy: a loaded panel stays loaded (no second request)", st.requests.filter((r) => r.path === "/panel/ok").length === 1, st.requests);
      }

      await tab("fail");
      await settle(page, 200);
      p = await panel("fail");
      check(`lazy (${mode}): a 500 shows the failure (role=alert, the words, a retry button) — never the server's body`, p.error && p.retry && p.text.includes("This tab could not be loaded.") && !p.html.includes("probe-evil") && p.busy === null, p);
      const contrast = await page.evaluate(() => {
        const c = document.createElement("canvas"); c.width = c.height = 1; const x = c.getContext("2d", { willReadFrequently: true });
        const rgba = (s) => { x.clearRect(0, 0, 1, 1); x.fillStyle = "#000"; x.fillStyle = s; x.fillRect(0, 0, 1, 1); const d = x.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
        const lum = (v) => { const f = (u) => { u /= 255; return u <= 0.03928 ? u / 12.92 : Math.pow((u + 0.055) / 1.055, 2.4); }; return 0.2126 * f(v[0]) + 0.7152 * f(v[1]) + 0.0722 * f(v[2]); };
        const over = (a, b) => [0, 1, 2].map((i) => a[i] * a[3] + b[i] * (1 - a[3]));
        const bg = (el) => { const chain = []; for (let e = el; e; e = e.parentElement) chain.push(rgba(getComputedStyle(e).backgroundColor)); let v = [255, 255, 255]; for (let i = chain.length - 1; i >= 0; i--) if (chain[i][3] > 0) v = over(chain[i], v); return v; };
        const ratio = (el) => { const b = bg(el), f = over(rgba(getComputedStyle(el).color), b); const a = lum(f), d = lum(b); return (Math.max(a, d) + 0.05) / (Math.min(a, d) + 0.05); };
        const t = document.querySelector('#t-lazy > .gk-tab-panel[data-tab="fail"] .gk-empty-title');
        const r = document.querySelector('#t-lazy > .gk-tab-panel[data-tab="fail"] .gk-empty button');
        const fb = document.querySelector('#t-lazy > .gk-tab-panel[data-tab="net"] .gk-tab-fallback a');
        return { title: ratio(t), retry: ratio(r), fallback: fb ? ratio(fb) : 0 };
      });
      check(`lazy (${mode}): the failure reads (title ${contrast.title.toFixed(2)}:1, retry ${contrast.retry.toFixed(2)}:1, fallback link ${contrast.fallback.toFixed(2)}:1, each ≥ 4.5:1)`, contrast.title >= 4.5 && contrast.retry >= 4.5 && contrast.fallback >= 4.5, contrast);

      if (mode !== "light") { await page.ctx.close(); continue; }
      const log = await page.evaluate(() => probeLog.filter((l) => l[0] === "load" && l[1] === "t-lazy"));
      check("lazy: gk:tabload says ok and status (true 200, then false 500)", JSON.stringify(log) === JSON.stringify([["load", "t-lazy", "ok", true, 200], ["load", "t-lazy", "fail", false, 500]]), log);

      await tab("net");
      await settle(page, 200);
      p = await panel("net");
      check("lazy: a network error shows the failure with its retry button", p.error && p.retry, p);
      st.netOk = true;
      await page.click('#t-lazy > .gk-tab-panel[data-tab="net"] .gk-empty button');
      await settle(page, 250);
      p = await panel("net");
      check("lazy: retry loads it, and the focus that was on the button lands on the panel", p.html.includes("probe-ok") && !p.error && p.focus, p);

      await tab("again");
      await settle(page, 150);
      check("reload: the first view loads (1)", (await panel("again")).text === "1", await panel("again"));
      await tab("plain");
      st.delay = 250;
      await tab("again");
      const keep = await panel("again");
      check("reload: the next view fetches again and keeps the old content, dimmed, while it does", keep.text === "1" && keep.busy === "true" && keep.dim < 1, keep);
      await page.waitForTimeout(400);
      st.delay = 0;
      check("reload: … then shows the new answer (2)", (await panel("again")).text === "2", await panel("again"));
      const reloaded = await page.evaluate(() => GK.tabs.reload("t-lazy", "again"));
      check("GK.tabs.reload(): fetches now and resolves true (3)", reloaded === true && (await panel("again")).text === "3", { reloaded, p: await panel("again") });
      check("GK.tabs.reload(): false for a panel that is not lazy", (await page.evaluate(() => GK.tabs.reload("t-lazy", "plain"))) === false);

      await tab("widgets");
      await settle(page, 200);
      const inner = await page.evaluate(() => {
        const w = document.getElementById("loaded-inner");
        return w ? { role: w.querySelector(".gk-tab-nav").getAttribute("role"), sel: w.querySelector(".gk-tab-btn").getAttribute("aria-selected") } : null;
      });
      check("lazy: GK.initContent runs on the loaded panel — the tabs inside get their roles", inner && inner.role === "tablist" && inner.sel === "true", inner);
      await page.click('#loaded-inner .gk-tab-btn[data-tab="y"]');
      await settle(page, 200);
      const deep = await page.evaluate(() => document.querySelector('#loaded-inner > .gk-tab-panel[data-tab="y"]').innerHTML);
      check("lazy: a lazy panel inside a loaded panel loads too", deep.includes("probe-ok"), deep);

      await tab("page");
      await settle(page, 200);
      p = await panel("page");
      check("lazy: a whole page (a login page after the session ended) is not put into the panel", p.error && !p.html.includes("probe-login"), p);
      await tab("cross");
      await settle(page, 200);
      p = await panel("cross");
      check("lazy: another origin is never asked — the failure instead", p.error && st.foreign.length === 0, { p, foreign: st.foreign });
      await page.ctx.close();
    }

    // ── 4. Events and a refused switch ───────────────────────────────────────────────────────────
    {
      const page = await fresh({ label: "events" });
      await page.goto(fx());
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      let log = await page.evaluate(() => probeLog.filter((l) => l[0] !== "load"));
      check("events: gk:tabchange (cancelable, detail tabsId/from/to/panel) then gk:tabshown, on the .gk-tabs",
        JSON.stringify(log) === JSON.stringify([["change", "t-api", "t-api", "a", "b", "user", true, true], ["shown", "t-api", "a", "b", "user"]]), log);
      await page.evaluate(() => { probeLog.length = 0; });
      await page.focus('#t-inner .gk-tab-btn[data-tab="i1"]');
      await page.keyboard.press("ArrowRight");
      log = await page.evaluate(() => probeLog.filter((l) => l[0] !== "load"));
      check("events: a key fires them as a click does, on the inner row (they bubble)", log.length === 2 && log[0][1] === "t-inner" && log[0][4] === "i2" && log[1][0] === "shown", log);
      await page.evaluate(() => { probeLog.length = 0; });
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      check("events: a click on the tab that is open fires nothing", (await page.evaluate(() => probeLog.length)) === 0);

      // refused: unsaved changes
      await page.fill("#probe-dirty", "changed");
      await page.click('#t-cancel .gk-tab-btn[data-tab="preview"]');
      let r = await row(page, "t-cancel");
      check("refused by a click: the tab, the panel, aria-selected and the address stay", r.active.join() === "edit" && r.selected.join() === "edit" && r.shown.join() === "edit" && !(await hash(page)).includes("t-cancel"), { r, h: await hash(page) });
      await page.focus('#t-cancel .gk-tab-btn[data-tab="edit"]');
      await page.keyboard.press("ArrowRight");
      r = await row(page, "t-cancel");
      const focus = await page.evaluate(() => document.activeElement.dataset.tab);
      check("refused by a key: the tab stays, and the focus goes back to it", r.active.join() === "edit" && r.shown.join() === "edit" && focus === "edit", { r, focus });
      const sel = await page.evaluate(() => GK.tabs.select("t-cancel", "more"));
      check("refused for GK.tabs.select(): it returns false", sel === false && (await row(page, "t-cancel")).active.join() === "edit");
      await page.evaluate(() => { probeLog.length = 0; });
      const forced = await page.evaluate(() => GK.tabs.select("t-cancel", "more", { force: true }));
      log = await page.evaluate(() => probeLog.filter((l) => l[0] !== "load"));
      check("{ force: true }: the event is not cancelable and the switch happens", forced === true && (await row(page, "t-cancel")).active.join() === "more" && !!log[0] && log[0][7] === false, { forced, log });
      await page.evaluate(() => GK.tabs.select("t-cancel", "edit"));
      await page.fill("#probe-dirty", "");
      await page.click('#t-cancel .gk-tab-btn[data-tab="preview"]');
      check("not refused: with nothing unsaved the click switches", (await row(page, "t-cancel")).active.join() === "preview");

      // nested: the outer switch leaves the inner choice
      await page.click('#t-inner .gk-tab-btn[data-tab="i2"]');
      await page.click('#t-api .gk-tab-btn[data-tab="d"]');
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      check("nested: an outer switch leaves the inner choice alone", (await row(page, "t-inner")).active.join() === "i2", await row(page, "t-inner"));

      // a gk:tabchange listener that chooses another tab itself: its choice stands (click and API)
      await page.evaluate(() => { window.probeRedirect = true; });
      await page.click('#t-mem .gk-tab-btn[data-tab="three"]');
      let m = await row(page, "t-mem");
      check("a listener that chooses another tab itself: its choice stands after a click (Two, not Three)", m.active.join() === "two" && m.selected.join() === "two" && m.shown.join() === "two", m);
      await page.evaluate(() => GK.tabs.select("t-mem", "one"));
      const api = await page.evaluate(() => GK.tabs.select("t-mem", "three"));
      m = await row(page, "t-mem");
      check("… and after GK.tabs.select(), which then returns false", api === false && m.active.join() === "two" && m.shown.join() === "two", { api, m });
      await page.evaluate(() => { window.probeRedirect = false; });
      await page.ctx.close();
    }

    // ── 5. The JS API on hand-written markup ─────────────────────────────────────────────────────
    {
      const page = await fresh({ label: "api" });
      await page.goto(fx());
      const a = await page.evaluate(() => {
        const out = {};
        out.current = GK.tabs.current("t-hand");
        out.select = GK.tabs.select("t-hand", "c");
        out.after = GK.tabs.current(document.getElementById("t-hand"));
        out.byPanel = GK.tabs.select(document.querySelector('#t-hand > .gk-tab-panel[data-tab="a"]'), "b");
        out.unknown = GK.tabs.select("t-hand", "nope");
        out.disabled = GK.tabs.select("t-api", "c");
        out.missing = GK.tabs.select("no-such-row", "a");
        out.badge = GK.tabs.badge("t-hand", "a", "New");
        out.badgeHtml = document.querySelector('#t-hand .gk-tab-btn[data-tab="a"]').innerHTML;
        GK.tabs.badge("t-hand", "b", "Error", { tone: "error" });
        out.badgeB = document.querySelector('#t-hand .gk-tab-btn[data-tab="b"]').innerHTML;
        GK.tabs.badge("t-hand", "a", null);
        out.badgeGone = !document.querySelector('#t-hand .gk-tab-btn[data-tab="a"] .gk-tab-badge');
        GK.tabs.count("t-hand", "b", 5);
        out.count = document.querySelector('#t-hand .gk-tab-btn[data-tab="b"] .gk-tab-count').textContent;
        GK.tabs.count("t-hand", "c", 0);
        out.countNew = document.querySelector('#t-hand .gk-tab-btn[data-tab="c"] .gk-tab-count').textContent;
        GK.tabs.count("t-hand", "b", null);
        out.countGone = !document.querySelector('#t-hand .gk-tab-btn[data-tab="b"] .gk-tab-count');
        GK.tabs.badge("t-hand-icons", "b", "Error", { tone: "error" });
        GK.tabs.count("t-hand-icons", "b", 3);
        out.iconName = document.querySelector('#t-hand-icons .gk-tab-btn[data-tab="b"]').getAttribute("aria-label");
        out.links = GK.tabs.current("t-over-links");
        out.linkBadge = GK.tabs.badge("t-over-links", "k1", "New") && !!document.querySelector('#t-over-links a[data-tab="k1"] .gk-tab-badge');
        out.linkSelect = GK.tabs.select("t-over-links", "k2");
        return out;
      });
      const r = await row(page, "t-hand");
      check("API: current() and select() on hand-written markup (by id, by element, by a panel inside)", a.current === "a" && a.select === true && a.after === "c" && a.byPanel === true && r.active.join() === "b" && r.selected.join() === "b" && r.shown.join() === "b", { a, r });
      check("API: select() refuses an unknown key, a disabled tab, an unknown row", a.unknown === false && a.disabled === false && a.missing === false, a);
      check("API: badge() puts a badge after the word and before a count, with the error tone, and takes it away",
        a.badge === true && /Inbox<span class="gk-tab-badge">New<\/span>$/.test(a.badgeHtml) && /Drafts<span class="gk-tab-badge gk-tab-badge-error">Error<\/span><span class="gk-tab-count">2<\/span>/.test(a.badgeB) && a.badgeGone, a);
      check("API: count() changes, adds (0 is a count) and takes away", a.count === "5" && a.countNew === "0" && a.countGone, a);
      check("API: an icon-only tab's name carries the badge and the count", a.iconName === "Details, Error, 3", a.iconName);
      check("API: a row of links — current() reads aria-current, badge() works, select() refuses (a link is a page)", a.links === "k8" && a.linkBadge === true && a.linkSelect === false, a);
      await page.ctx.close();
    }

    // ── 6. Rows wider than their box ─────────────────────────────────────────────────────────────
    // The first pass scrolls smoothly (and waits for it); the others ask for reduced motion, where the arrows jump.
    for (const [width, mode, motion] of [[1100, "light", "no-preference"], [390, "light", "reduce"], [1100, "dark", "reduce"], [390, "gk-dark", "reduce"]]) {
      const page = await fresh({ label: `overflow ${width} ${mode}`, width, motion });
      const glide = motion === "reduce" ? 80 : 900;
      await page.goto(fx());
      if (mode === "dark") await page.evaluate(() => { document.documentElement.setAttribute("data-gk-mode", "dark"); document.body.setAttribute("data-gk-mode", "dark"); });
      if (mode === "gk-dark") await page.evaluate(() => document.body.classList.add("gk-dark"));
      await settle(page, 100);
      const tag = `${width}px ${mode}`;
      const ov = (id) => page.evaluate((id) => {
        const w = document.getElementById(id);
        const nav = w.matches(".gk-tab-nav") ? w : w.querySelector(".gk-tab-nav");
        const n = nav.getBoundingClientRect();
        const arrows = [...nav.querySelectorAll(":scope > .gk-tab-arrow")];
        const vis = (el) => el && getComputedStyle(el).display !== "none";
        const btns = [...nav.querySelectorAll(":scope > .gk-tab-btn")];
        const cur = btns.find((b) => b.classList.contains("gk-active") || b.getAttribute("aria-current") === "page");
        const c = cur ? cur.getBoundingClientRect() : null;
        return {
          over: nav.classList.contains("gk-tab-nav-overflow"), raster: nav.classList.contains("gk-tab-nav-raster"), grid: getComputedStyle(nav).display === "grid",
          start: vis(arrows[0]), end: vis(arrows[1]), arrows: arrows.length,
          hidden: arrows.every((a) => a.getAttribute("aria-hidden") === "true" && a.tabIndex === -1 && !a.matches("a, button")),
          scroll: nav.scrollLeft, max: nav.scrollWidth - nav.clientWidth,
          firstLeft: btns[0].getBoundingClientRect().left - n.left - parseFloat(getComputedStyle(nav).paddingLeft) + nav.scrollLeft,
          curIn: c ? c.left >= n.left - 0.5 && c.right <= n.right + 0.5 : null,
          // the chosen tab is not under an arrow that shows
          curClear: c ? arrows.filter(vis).every((a) => { const r = a.getBoundingClientRect(); return r.right <= c.left + 0.5 || r.left >= c.right - 0.5; }) : null,
          // a visible arrow stands inside the row's box, at its edge (it sticks; it does not scroll away)
          arrowsAtEdges: arrows.every((a, i) => {
            if (!vis(a)) return true;
            const r = a.getBoundingClientRect();
            return r.left >= n.left - 0.5 && r.right <= n.right + 0.5 && (i === 0 ? r.left - n.left < 8 : n.right - r.right < 8);
          }),
          width: nav.scrollWidth,
          // the row ends where its last tab ends (plus its own padding): no arrow adds to what scrolls
          endGap: Math.round(nav.scrollWidth - (btns[btns.length - 1].getBoundingClientRect().right - n.left + nav.scrollLeft + parseFloat(getComputedStyle(nav).paddingRight))),
          page: document.documentElement.scrollWidth - innerWidth,
        };
      }, id);
      for (const style of ["linie", "kapsel", "text"]) {
        const o = await ov("t-over-" + style);
        check(`overflow ${tag}, ${style}: the row is marked, the arrow at the end shows at the row's edge, the one at the start does not; the arrows are aria-hidden, no tab stop`, o.over && !o.start && o.end && o.arrowsAtEdges && o.arrows === 2 && o.hidden, o);
        check(`overflow ${tag}, ${style}: the tabs stand where they stood and the row ends at its last tab (the arrows take no width), the page does not scroll sideways`, Math.abs(o.firstLeft) <= 1 && Math.abs(o.endGap) <= 1 && o.page <= 1, o);
      }
      const card = await ov("t-over-karte");
      check(`overflow ${tag}: the card row keeps its grid (${width > 600 ? "gk-tab-nav-raster" : "the phone grid"}) and gets no arrows`, card.grid && (width <= 600 || card.raster) && !card.over && card.arrows === 0, card);
      const few = await ov("t-few");
      check(`overflow ${tag}: a row that fits is not marked and has no arrows`, !few.over && !few.start && !few.end, few);
      const links = await ov("t-over-links");
      check(`overflow ${tag}: a row of links is covered too, and its current page (the 9th) is scrolled into view`, links.over && links.curIn && links.start, links);

      // the arrows page the row; the keys reach the last tab and bring it into view
      const before = await ov("t-over-linie");
      await page.click("#t-over-linie .gk-tab-arrow-end");
      await settle(page, glide);
      let o = await ov("t-over-linie");
      check(`overflow ${tag}: the end arrow pages the row on (${motion === "reduce" ? "at once" : "smoothly"}), then the start arrow shows at the row's edge`, o.scroll > 100 && o.start && o.arrowsAtEdges, o);
      check(`overflow ${tag}: with both arrows showing the row is exactly as wide as before (the arrows take no room)`, o.width === before.width, { before: before.width, after: o.width });
      await page.click("#t-over-linie .gk-tab-arrow-start");
      await settle(page, glide);
      o = await ov("t-over-linie");
      check(`overflow ${tag}: the start arrow pages back to the start and hides`, o.scroll <= 1 && !o.start && o.end, o);
      await page.focus('#t-over-linie .gk-tab-btn[data-tab="k0"]');
      await page.keyboard.press("End");
      await settle(page, 100);
      o = await ov("t-over-linie");
      const clear = await page.evaluate(() => {
        const nav = document.querySelector("#t-over-linie > .gk-tab-nav"), b = nav.querySelector('.gk-tab-btn[data-tab="k9"]');
        return { sel: b.getAttribute("aria-selected"), focus: document.activeElement === b, inView: b.getBoundingClientRect().right <= nav.getBoundingClientRect().right + 0.5 };
      });
      check(`overflow ${tag}: End selects the last tab, it is in view, the end arrow hides`, clear.sel === "true" && clear.focus && clear.inView && !o.end && o.start, { o, clear });
      await page.keyboard.press("Home");
      await settle(page, 100);
      o = await ov("t-over-linie");
      check(`overflow ${tag}: Home goes back, the first tab is in view, the start arrow hides`, o.curIn && !o.start, o);
      await page.evaluate(() => GK.tabs.select("t-over-kapsel", "k7"));
      o = await ov("t-over-kapsel");
      check(`overflow ${tag}: GK.tabs.select() brings the chosen tab into view, clear of the arrows`, o.curIn && o.curClear, o);
      if (mode !== "light") {
        const k = await page.evaluate(() => {
          const c = document.createElement("canvas"); c.width = c.height = 1; const x = c.getContext("2d", { willReadFrequently: true });
          const rgba = (s) => { x.clearRect(0, 0, 1, 1); x.fillStyle = "#000"; x.fillStyle = s; x.fillRect(0, 0, 1, 1); const d = x.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
          const lum = (v) => { const f = (u) => { u /= 255; return u <= 0.03928 ? u / 12.92 : Math.pow((u + 0.055) / 1.055, 2.4); }; return 0.2126 * f(v[0]) + 0.7152 * f(v[1]) + 0.0722 * f(v[2]); };
          const a = document.querySelector("#t-over-linie .gk-tab-arrow-end");
          const fg = rgba(getComputedStyle(a).color), bg = rgba(getComputedStyle(a).backgroundColor), page = rgba(getComputedStyle(document.body).backgroundColor);
          const r = (p, q) => (Math.max(lum(p), lum(q)) + 0.05) / (Math.min(lum(p), lum(q)) + 0.05);
          return { chevron: r(fg, bg), dark: lum(bg) < 0.2 };
        });
        check(`overflow ${tag}: the arrow is dark in dark mode and its chevron reads (${k.chevron.toFixed(2)}:1 ≥ 3:1)`, k.dark && k.chevron >= 3, k);
      }
      // measured by the row's own width: a wider box takes the mark and the arrows away
      await page.evaluate(() => { document.querySelector("#t-over-text").parentElement.style.width = "1600px"; document.querySelector("#t-over-text").parentElement.style.maxWidth = "none"; });
      await settle(page, 150);
      o = await ov("t-over-text");
      check(`overflow ${tag}: in a box wide enough the row is no longer marked and shows no arrow`, !o.over && !o.start && !o.end, o);
      if (width === 390) {
        const tall = await page.evaluate(() => Math.min(...[...document.querySelectorAll("#t-over-linie .gk-tab-btn")].map((b) => b.getBoundingClientRect().height)));
        check(`overflow ${tag}: every tab of the scrolling row is at least 44px high`, tall >= 43.5, tall);
      }
      await page.ctx.close();
    }

    // ── 7. A modal, and markup injected later ───────────────────────────────────────────────────
    {
      const page = await fresh({ label: "modal" });
      await page.goto(fx());
      await page.evaluate(() => GK.modal.open("Tabs in a window", "/modal/tabs"));
      await page.waitForSelector("#in-modal .gk-tab-nav[role=tablist]");
      await page.click('#in-modal .gk-tab-btn[data-tab="m2"]');
      await settle(page, 200);
      const m = await page.evaluate(() => ({ html: document.querySelector('#in-modal > .gk-tab-panel[data-tab="m2"]').innerHTML, hash: location.hash }));
      check("modal: tabs fetched into GK.modal get their roles, load a lazy panel and write the address", m.html.includes("probe-ok") && m.hash === "#in-modal=m2", m);
      await page.evaluate(() => GK.modal.close());

      await page.evaluate(() => {
        const box = document.getElementById("probe-late-box");
        box.appendChild(document.getElementById("probe-late").content.cloneNode(true));
        GK.initContent(box);
      });
      const late = await row(page, "t-late");
      const sel = await page.evaluate(() => GK.tabs.select("t-late", "b"));
      await settle(page, 200);
      const lateHtml = await page.evaluate(() => document.querySelector('#t-late > .gk-tab-panel[data-tab="b"]').innerHTML);
      check("GK.initContent() on injected markup: roles, select(), a lazy panel and the address", late.role === "tablist" && sel === true && lateHtml.includes("probe-ok") && (await hash(page)).includes("t-late=b"), { late, sel, lateHtml });
      await page.ctx.close();
    }

    // ── 8. The page with the script before 1.111.0 ──────────────────────────────────────────────
    {
      const page = await fresh({ label: "old script" });
      await page.goto(BASE + "/old.html#t-api=d");
      const before = await row(page, "t-api");
      await page.click('#t-api .gk-tab-btn[data-tab="b"]');
      const after = await row(page, "t-api");
      check("older script: Tabs output switches as hand-written tabs always did", before.role === "tablist" && before.active.join() === "a" && after.active.join() === "b" && after.shown.join() === "b", { before, after });
      check("older script: the address is left alone, there is no GK.tabs.select", (await hash(page)) === "#t-api=d" && (await page.evaluate(() => typeof GK.tabs.select)) === "undefined");
      await page.click('#t-lazy .gk-tab-btn[data-tab="ok"]');
      const fb = await page.evaluate(() => { const p = document.querySelector('#t-lazy > .gk-tab-panel[data-tab="ok"]'); const a = p.querySelector(".gk-tab-fallback a"); return { shown: getComputedStyle(p).display !== "none", href: a && a.getAttribute("href"), text: a && a.textContent, visible: !!a && a.getBoundingClientRect().height > 0 }; });
      check("older script: a lazy panel shows a link to its address instead of a skeleton that never ends", fb.shown && fb.visible && fb.href === "/panel/ok" && fb.text === "Open “Loads”" && page.state.requests.length === 0, fb);
      const o = await page.evaluate(() => { const n = document.querySelector("#t-over-linie > .gk-tab-nav"); return { over: n.classList.contains("gk-tab-nav-overflow"), arrows: n.querySelectorAll(".gk-tab-arrow").length, scrolls: n.scrollWidth > n.clientWidth, overflowX: getComputedStyle(n).overflowX }; });
      check("older script: a long row scrolls as it did, no arrows", !o.over && o.arrows === 0 && o.scrolls && o.overflowX === "auto", o);
      await page.ctx.close();
    }
    check("no script errors", pageErrors.length === 0, pageErrors);
  } finally {
    await browser.close();
  }

  process.exit(report() ? 1 : 0);
})().catch((e) => {
  // What held and what failed before the abort is still worth reading.
  report();
  console.error("Aborted:", e && e.stack ? e.stack.split("\n").slice(0, 3).join(" | ") : e);
  process.exit(2);
});

/** Prints every case; returns the number that failed. */
function report() {
  let failed = 0;
  for (const r of results) {
    if (!r.ok) failed++;
    console.log(`${r.ok ? "ok  " : "FAIL"}  ${r.name}${r.ok ? "" : "  " + r.detail}`);
  }
  console.log(`\n${results.length - failed} of ${results.length} held`);
  return failed;
}
