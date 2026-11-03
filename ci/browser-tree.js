#!/usr/bin/env node
/**
 * The tree view (GridKit\Tree + js/tree.js, 1.116.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup the server writes is checked there
 * (tests/tree.test.php). What the script does with it is checked here — the ARIA tree it builds,
 * the keys of the WAI pattern and type-ahead, opening and closing and what the browser remembers,
 * children loaded on demand (an answer, an error, a retry, an empty branch, a reload), single and
 * multiple selection with tri-state parents and what a form posts, the filter with the ancestors of a
 * match, moving by drag and drop and by keyboard, the refusal to move a node into its own branch,
 * the save and the way back after a 500, the events and their veto, the API, 390px without a
 * sideways scroll, light and dark (both spellings) under the six themes, and the page without script.
 *
 * The lazy branches (GET http://gk.test/children?parent=…) and the moves (POST http://gk.test/move)
 * are answered with page.route(): no server is needed. The page is ci/browser-tree-fixture.php.
 *
 *     node ci/browser-tree.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-tree.js
 *     GK_SHOTS=/some/dir node ci/browser-tree.js   # also writes screenshots there
 *     GK_VERBOSE=1 node ci/browser-tree.js          # prints each case as it runs (stderr)
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-tree-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
const html = execFileSync(php, [path.join(__dirname, "browser-tree-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }).toString();
fs.writeFileSync(fixture, html);
const BRANCHES = JSON.parse(html.match(/window\.GK_TEST_BRANCHES = (.*?);<\/script>/s)[1]);
const SHOTS = process.env.GK_SHOTS || "";

const T = (id) => `[data-gk-tree="${id}"]`;
const N = (tree, id) => `${T(tree)} [data-gk-tree-id="${id}"]`;
const R = (tree, id) => `${N(tree, id)} > .gk-tree-row`;

/** Contrast of two colours as the page paints them (a canvas reads any CSS colour the browser knows). */
const PIXEL = `
  const cv = document.createElement("canvas"); cv.width = cv.height = 1;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  const px = (css, under) => { ctx.clearRect(0, 0, 1, 1); if (under) { ctx.fillStyle = "rgb(" + under.join(",") + ")"; ctx.fillRect(0, 0, 1, 1); }
    ctx.fillStyle = css; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
  const lum = (c) => { const f = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  // The colour under an element: every background from the page down to it, painted in order.
  const ground = (el) => { const chain = []; for (let e = el; e; e = e.parentElement) chain.unshift(e);
    let c = [255, 255, 255]; chain.forEach((e) => { const b = getComputedStyle(e).backgroundColor; c = px(b, c); }); return c; };
  const textOn = (el) => ratio(px(getComputedStyle(el).color, ground(el)), ground(el));
`;

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };
  try {
    const context = await browser.newContext({ viewport: { width: 1100, height: 900 } });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.setDefaultTimeout(8000);

    // ── The endpoints ─────────────────────────────────────────────────────
    const requests = [];
    const lazyReply = {};
    let moveReply = { status: 200, body: '{"ok":true}' };
    let releaseMove = null;
    let releaseLazy = null;
    await context.route("http://gk.test/**", async (route) => {
      const req = route.request();
      const u = new URL(req.url());
      let body = null;
      try { body = JSON.parse(req.postData() || "null"); } catch (e) { body = req.postData(); }
      requests.push({ path: u.pathname, parent: u.searchParams.get("parent"), method: req.method(), body, headers: req.headers(), url: req.url() });
      const cors = { "Access-Control-Allow-Origin": "*", "Access-Control-Allow-Headers": "*" };
      if (u.pathname === "/children") {
        const parent = u.searchParams.get("parent");
        const r = lazyReply[parent] || { status: 200 };
        if (r.hold) await new Promise((res) => { releaseLazy = res; });
        if (r.abort) return route.abort("failed");
        const failed = (r.status || 200) >= 400;
        return route.fulfill({ status: r.status || 200, contentType: "text/html", headers: cors, body: failed ? "<p>Server error</p>" : BRANCHES[parent] || "" });
      }
      if (u.pathname === "/move") {
        const r = moveReply;
        if (r.hold) await new Promise((res) => { releaseMove = res; });
        if (r.abort) return route.abort("failed");
        return route.fulfill({ status: r.status || 200, contentType: "application/json", headers: cors, body: r.body });
      }
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" });
    });

    const arm = () => page.evaluate(() => {
      window._ev = [];
      window._cancel = null;
      ["gk:treeselect", "gk:treetoggle", "gk:treemove", "gk:treemoved"].forEach((n) =>
        document.addEventListener(n, (e) => window._ev.push(Object.assign({ type: n }, e.detail))));
      document.addEventListener("gk:treemove", (e) => { if (window._cancel && window._cancel(e.detail)) e.preventDefault(); });
    });
    const fresh = async () => {
      await page.evaluate(() => { try { localStorage.clear(); } catch (e) { /* none */ } });
      await page.reload();
      await arm();
    };
    const events = () => page.evaluate(() => window._ev.splice(0));
    const take = () => requests.splice(0);
    const wait = (ms) => page.waitForTimeout(ms);
    const live = async (tree) => { await wait(80); return page.$eval(`${T(tree)} > p[aria-live]`, (p) => p.textContent); };
    const attr = (sel, a) => page.$eval(sel, (el, a) => el.getAttribute(a), a);
    const active = () => page.evaluate(() => {
      const a = document.activeElement;
      const li = a && a.closest ? a.closest(".gk-tree-node") : null;
      return { id: li ? li.getAttribute("data-gk-tree-id") : "", tag: a ? a.tagName : "", cls: a ? a.className : "", isItem: !!a && a === li };
    });
    const kidsOf = (tree, id) => page.evaluate(([t, i]) => {
      const box = i ? document.querySelector(`[data-gk-tree="${t}"] [data-gk-tree-id="${i}"] > .gk-tree-group`) : document.querySelector(`[data-gk-tree="${t}"] .gk-tree-list`);
      return box ? [...box.children].filter((c) => c.classList.contains("gk-tree-node")).map((c) => c.getAttribute("data-gk-tree-id")) : [];
    }, [tree, id]);
    const shown = (tree) => page.evaluate((t) =>
      [...document.querySelectorAll(`[data-gk-tree="${t}"] .gk-tree-node`)].filter((li) => li.offsetParent !== null).map((li) => li.getAttribute("data-gk-tree-id")), tree);
    const centre = async (sel) => {
      const l = page.locator(sel);
      await l.scrollIntoViewIfNeeded();
      const b = await l.boundingBox();
      return { x: b.x + b.width / 2, y: b.y + b.height / 2, top: b.y, bottom: b.y + b.height, h: b.height };
    };
    /** Press the handle of a node, pull past the threshold and on to a point; `hold` keeps the button down. */
    const drag = async (tree, id, to, hold = false) => {
      const from = await centre(`${R(tree, id)} > .gk-tree-handle`);
      await page.mouse.move(from.x, from.y);
      await page.mouse.down();
      await page.mouse.move(from.x - 3, from.y + (to.y >= from.y ? 8 : -8), { steps: 2 });
      await page.mouse.move(to.x, to.y, { steps: 12 });
      await wait(60);
      if (!hold) await page.mouse.up();
    };
    const settled = () => page.waitForFunction(() => !document.querySelector('.gk-tree-node[aria-busy="true"]'));

    await page.goto("file://" + fixture);
    await arm();

    // ── The tree a screen reader meets ────────────────────────────────────
    const aria = await page.evaluate(() => {
      const list = document.querySelector('[data-gk-tree="pages"] .gk-tree-list');
      const cats = document.querySelector('[data-gk-tree="cats"] .gk-tree-list');
      const item = (id) => document.querySelector(`[data-gk-tree-id="${id}"]`);
      const name = (li) => (li.getAttribute("aria-labelledby") || "").split(" ").map((i) => document.getElementById(i).textContent).join(" ");
      return {
        role: list.getAttribute("role"), label: list.getAttribute("aria-label"), multi: list.getAttribute("aria-multiselectable"),
        catsMulti: cats.getAttribute("aria-multiselectable"),
        about: ["role", "aria-level", "aria-posinset", "aria-setsize", "aria-expanded"].map((a) => item("about").getAttribute(a)).join(" "),
        team: ["aria-level", "aria-posinset", "aria-setsize", "aria-expanded"].map((a) => item("team").getAttribute(a)).join(" "),
        group: item("about").querySelector(".gk-tree-group").getAttribute("role"),
        homeExpanded: item("home").hasAttribute("aria-expanded"),
        lazyExpanded: item("blog").getAttribute("aria-expanded"),
        tabbable: [...document.querySelectorAll('[data-gk-tree] [role="treeitem"][tabindex="0"]')].map((li) => li.getAttribute("data-gk-tree-id")),
        names: ["about", "services", "history", "legal"].map((i) => name(item(i))),
        hiddenBox: [...document.querySelectorAll('[data-gk-tree="cats"] input.gk-tree-check')].every((b) => b.getAttribute("aria-hidden") === "true" && b.tabIndex === -1),
        linkOut: item("team").querySelector(".gk-tree-label").tabIndex,
        buttonsOut: [...item("home").querySelectorAll(".gk-tree-actions a, .gk-tree-actions button")].every((b) => b.tabIndex === -1),
        radioShown: getComputedStyle(item("hosting").querySelector(".gk-tree-radio")).display,
        selected: [...list.querySelectorAll('[aria-selected="true"]')].map((li) => li.getAttribute("data-gk-tree-id")),
        disabled: item("legal").getAttribute("aria-disabled"),
      };
    });
    check("the list is a tree with a name; only the multi tree is multiselectable",
      aria.role === "tree" && aria.label === "Pages" && aria.multi === null && aria.catsMulti === "true", JSON.stringify(aria));
    check("a node is a treeitem with its level, position and set size; a branch is a group",
      aria.about === "treeitem 1 2 6 true" && aria.team === "2 1 2 " && aria.group === "group", aria.about + " | " + aria.team);
    check("aria-expanded only on parents (a lazy one is a parent, closed)", !aria.homeExpanded && aria.lazyExpanded === "false");
    check("one treeitem per tree in the tab order — the expandTo() node in the page tree",
      JSON.stringify(aria.tabbable) === JSON.stringify(["team", "c-books", "inbox", "d11"]), JSON.stringify(aria.tabbable));
    check("a node is named by its label, its note, its badge and its count — not by its children or buttons",
      JSON.stringify(aria.names) === JSON.stringify(["About us", "Services 3", "History (unpublished)", "Legal Locked"]), JSON.stringify(aria.names));
    check("boxes, links and row buttons are out of the tab order; the boxes are hidden from a screen reader",
      aria.hiddenBox && aria.linkOut === -1 && aria.buttonsOut);
    check("single selection: the radio button is the script's to hide, the chosen node says aria-selected",
      aria.radioShown === "none" && JSON.stringify(aria.selected) === '["hosting"]');
    check("a disabled node says so", aria.disabled === "true");

    // ── The keys of the pattern ──────────────────────────────────────────
    await page.focus("#before");
    for (let i = 0; i < 4; i++) await page.keyboard.press("Tab");
    let a = await active();
    check("Tab reaches the tree on its one tab stop (after the filter and the two buttons)", a.isItem && a.id === "team", JSON.stringify(a));
    await page.keyboard.press("ArrowDown");
    check("Down goes to the next node in view", (await active()).id === "history");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("ArrowRight");
    check("Right opens a closed parent", (await attr(N("pages", "services"), "aria-expanded")) === "true" && (await active()).id === "services");
    let ev = await events();
    check("…and gk:treetoggle says which and how", ev.length === 1 && ev[0].type === "gk:treetoggle" && ev[0].id === "services" && ev[0].expanded === true && ev[0].tree === "pages", JSON.stringify(ev));
    await page.keyboard.press("ArrowRight");
    check("Right on an open parent goes to its first child", (await active()).id === "web");
    await page.keyboard.press("ArrowLeft");
    check("Left on a child goes to its parent", (await active()).id === "services");
    await page.keyboard.press("ArrowLeft");
    check("Left on an open parent closes it", (await attr(N("pages", "services"), "aria-expanded")) === "false");
    await page.keyboard.press("ArrowUp");
    check("Up goes to the node above in view", (await active()).id === "history");
    await page.keyboard.press("End");
    check("End goes to the last node in view", (await active()).id === "shop");
    await page.keyboard.press("Home");
    check("Home goes to the first node", (await active()).id === "home");
    await page.keyboard.press("b");
    check("type-ahead: a letter goes to the next node that starts with it", (await active()).id === "blog");
    await wait(700);
    await page.keyboard.press("s");
    await page.keyboard.press("e");
    a = await active();
    check("type-ahead: letters typed together find the node they start (s → Shop, se → Services)", a.id === "services", JSON.stringify(a));
    await wait(700);
    await page.keyboard.press("a");
    a = await active();
    check("type-ahead: after a pause a letter starts a new search", a.id === "about", JSON.stringify(a));
    const tabStops = await page.evaluate(() => [...document.querySelectorAll('[data-gk-tree="pages"] [role="treeitem"]')].filter((li) => li.tabIndex === 0).length);
    check("the roving tabindex moves with the focus: still one tab stop", tabStops === 1);
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("Tab");
    a = await active();
    check("Tab from a node reaches the buttons of its own row", a.id === "team" && a.tag === "A" && /gk-btn/.test(a.cls), JSON.stringify(a));
    await page.keyboard.press("Tab");
    await page.keyboard.press("Tab");
    a = await active();
    check("…then its handle", a.id === "team" && /gk-tree-handle/.test(a.cls), JSON.stringify(a));
    await page.keyboard.press("Tab");
    a = await active();
    check("…and then the tree is left (another row's buttons are not in the way)", !(await page.evaluate(() => document.querySelector('[data-gk-tree="pages"]').contains(document.activeElement))), JSON.stringify(a));
    await page.focus(N("pages", "team"));
    await page.keyboard.press("Enter");
    check("Enter on a node with a link follows the link", (await page.evaluate(() => location.hash)) === "#page-team");
    await page.focus(N("plain", "inbox"));
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press("*");
    check("* opens every parent beside the focused node",
      (await attr(N("plain", "inbox"), "aria-expanded")) === "true" && (await attr(N("plain", "archive"), "aria-expanded")) === "true");
    await events();

    // ── Open, closed and remembered ──────────────────────────────────────
    await page.click(`${R("pages", "services")} > .gk-tree-toggle`);
    ev = await events();
    check("a click on the chevron opens the node", (await attr(N("pages", "services"), "aria-expanded")) === "true" && ev.some((e) => e.type === "gk:treetoggle" && e.id === "services"));
    const stored = await page.evaluate(() => JSON.parse(localStorage.getItem("gk-tree:pages") || "null"));
    check("the open nodes are kept in localStorage under the tree's id", stored && stored.open.includes("services") && stored.closed.includes("legal"), JSON.stringify(stored));
    check("a tree with remember(false) keeps nothing", (await page.evaluate(() => localStorage.getItem("gk-tree:plain"))) === null);
    await page.click(`${R("pages", "about")} > .gk-tree-toggle`);
    await page.reload();
    await arm();
    check("after a reload the remembered state is back (services open, about closed but opened by expandTo for team)",
      (await attr(N("pages", "services"), "aria-expanded")) === "true" && (await attr(N("pages", "about"), "aria-expanded")) === "true");
    await page.click(`${T("pages")} [data-gk-tree-all="collapse"]`);
    check("Collapse all closes every parent",
      (await page.$$eval(`${T("pages")} [aria-expanded="true"]`, (l) => l.length)) === 0);
    await page.click(`${T("pages")} [data-gk-tree-all="expand"]`);
    const afterAll = await page.evaluate(() => ["about", "services", "legal", "blog", "shop"].map((i) => document.querySelector(`[data-gk-tree-id="${i}"]`).getAttribute("aria-expanded")));
    check("Expand all opens what is loaded, not a lazy branch", JSON.stringify(afterAll) === '["true","true","true","false","false"]', JSON.stringify(afterAll));
    check("…and no request was made for it", take().filter((r) => r.path === "/children").length === 0);
    await events();

    // ── Children on demand ───────────────────────────────────────────────
    await fresh();
    lazyReply.blog = { hold: true };
    await page.click(`${R("pages", "blog")} > .gk-tree-toggle`);
    await page.waitForFunction(() => document.querySelector('[data-gk-tree-id="blog"]').getAttribute("aria-busy") === "true");
    check("while a branch loads its node is busy and says it is open", (await attr(N("pages", "blog"), "aria-expanded")) === "true");
    releaseLazy();
    delete lazyReply.blog;
    await settled();
    let req = take().filter((r) => r.path === "/children");
    check("the branch is fetched once: GET lazyUrl?parent=<id>", req.length === 1 && req[0].method === "GET" && req[0].parent === "blog" && req[0].headers["x-requested-with"] === "XMLHttpRequest", JSON.stringify(req.map((r) => r.url)));
    check("its children arrive as level 2 nodes in a group",
      JSON.stringify(await kidsOf("pages", "blog")) === '["y2026","y2025"]' && (await attr(N("pages", "y2025"), "aria-level")) === "2" && (await attr(N("pages", "y2025"), "aria-setsize")) === "2"
      && (await attr(`${N("pages", "blog")} > .gk-tree-group`, "role")) === "group");
    check("a loaded child that has children of its own is a parent, closed",
      (await attr(N("pages", "y2025"), "aria-expanded")) === "false" && (await attr(N("pages", "y2026"), "aria-expanded")) === "false");
    lazyReply.y2026 = { status: 500 };
    await page.click(`${R("pages", "y2026")} > .gk-tree-toggle`);
    await page.waitForSelector(`${R("pages", "y2026")} > .gk-tree-loaderror`);
    check("a failed branch closes again and says so in its row, with a button to try again",
      (await attr(N("pages", "y2026"), "aria-expanded")) === "false" && (await page.textContent(`${R("pages", "y2026")} > .gk-tree-loaderror`)) === "Not loaded.Try again");
    check("…and the live region names the node", (await live("pages")) === "The entries of 2026 could not be loaded.");
    lazyReply.y2026 = { status: 200 };
    await page.click(`${R("pages", "y2026")} .gk-tree-retry`);
    await settled();
    check("Try again loads it: the line goes, the children come",
      !(await page.$(`${R("pages", "y2026")} > .gk-tree-loaderror`)) && JSON.stringify(await kidsOf("pages", "y2026")) === '["post-c","post-d"]'
      && (await attr(N("pages", "post-c"), "aria-level")) === "3");
    await page.click(`${R("pages", "shop")} > .gk-tree-toggle`);
    await settled();
    check("an empty answer makes the node a leaf", !(await page.$eval(N("pages", "shop"), (li) => li.hasAttribute("aria-expanded"))));
    take();
    await page.evaluate(() => GK.tree.reload("pages", "blog"));
    req = take().filter((r) => r.path === "/children");
    check("GK.tree.reload() fetches a branch again and puts the same nodes back, once each",
      req.length === 1 && req[0].parent === "blog" && JSON.stringify(await kidsOf("pages", "blog")) === '["y2026","y2025"]'
      && (await page.$$(N("pages", "y2025"))).length === 1);
    lazyReply.y2026 = { abort: true };
    await page.evaluate(() => GK.tree.reload("pages", "y2026"));
    check("no network is a failed branch too", !!(await page.$(`${R("pages", "y2026")} > .gk-tree-loaderror`)));
    lazyReply.y2026 = { status: 200 };
    await events();

    // ── Single selection ─────────────────────────────────────────────────
    await fresh();
    await page.click(`${R("pages", "services")} > .gk-tree-toggle`);
    await events();
    await page.click(`${R("pages", "web")} > .gk-tree-label`);
    ev = await events();
    check("a click on a row selects it: aria-selected moves, the radio follows, gk:treeselect says so",
      JSON.stringify(await page.$$eval(`${T("pages")} [aria-selected="true"]`, (l) => l.map((li) => li.getAttribute("data-gk-tree-id")))) === '["web"]'
      && (await page.$eval(`${T("pages")} input[name="page"]:checked`, (r) => r.value)) === "web"
      && ev.length === 1 && ev[0].type === "gk:treeselect" && ev[0].id === "web" && JSON.stringify(ev[0].ids) === '["web"]', JSON.stringify(ev));
    a = await active();
    check("…and the focus is on the node, not on the hidden radio", a.isItem && a.id === "web", JSON.stringify(a));
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press(" ");
    check("Space selects the focused node", (await page.$eval(`${T("pages")} input[name="page"]:checked`, (r) => r.value)) === "hosting");
    await page.focus(N("pages", "legal"));
    await page.keyboard.press(" ");
    check("a disabled node cannot be selected", (await page.$eval(`${T("pages")} input[name="page"]:checked`, (r) => r.value)) === "hosting");
    await events();

    // ── Several, with tri-state parents ──────────────────────────────────
    const boxes = () => page.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-gk-tree="cats"] .gk-tree-node')].map((li) =>
      [li.getAttribute("data-gk-tree-id"), li.getAttribute("aria-checked") + (li.querySelector(":scope > .gk-tree-row > input").indeterminate ? "~" : "")])));
    let b = await boxes();
    check("a selected parent ticks its branch, but not a disabled node in it",
      b["c-books"] === "true" && b["c-novels"] === "true" && b["c-poetry"] === "true" && b["c-rare"] === "false" && b["c-music"] === "false", JSON.stringify(b));
    check("a selected id in a branch not loaded yet travels as a hidden field",
      (await page.$$eval(`${T("cats")} input[data-gk-tree-pending]`, (l) => l.map((i) => i.name + "=" + i.value).join())) === "cats[]=c-vinyl");
    await page.click(`${R("cats", "c-books")} > .gk-tree-toggle`);
    await page.click(`${R("cats", "c-poetry")} > input`);
    b = await boxes();
    ev = await events();
    check("unticking one child makes its parent mixed (aria-checked and indeterminate)", b["c-books"] === "mixed~" && b["c-poetry"] === "false", JSON.stringify(b));
    ev = ev.filter((e) => e.type !== "gk:treetoggle");
    check("…and gk:treeselect carries the id, the state and every ticked id",
      ev.length === 1 && ev[0].id === "c-poetry" && ev[0].selected === false && ev[0].ids.includes("c-novels") && !ev[0].ids.includes("c-books") && ev[0].ids.includes("c-vinyl"), JSON.stringify(ev));
    a = await active();
    check("a click on a box puts the focus on its node", a.isItem && a.id === "c-poetry", JSON.stringify(a));
    await page.focus(N("cats", "c-books"));
    await page.keyboard.press(" ");
    b = await boxes();
    check("Space on a mixed parent ticks the whole branch", b["c-books"] === "true" && b["c-poetry"] === "true" && b["c-rare"] === "false", JSON.stringify(b));
    await page.click(`${R("cats", "cat-more")} > .gk-tree-toggle`);
    await settled();
    b = await boxes();
    check("when the branch arrives, the pending id is ticked and the hidden field is gone; its parent is mixed",
      b["c-vinyl"] === "true" && b["c-tapes"] === "false" && b["cat-more"] === "mixed~" && b["c-music"] === "mixed~"
      && (await page.$$(`${T("cats")} input[data-gk-tree-pending]`)).length === 0, JSON.stringify(b));
    await page.click(`${R("cats", "c-music")} > .gk-tree-label`);
    b = await boxes();
    check("a click on a parent's name ticks the parent and all below it", b["c-music"] === "true" && b["c-cds"] === "true" && b["c-tapes"] === "true", JSON.stringify(b));
    await page.click("#cats-submit");
    await page.waitForURL(/gk\.test\/submit/);
    const posted = new URL(page.url()).searchParams.getAll("cats[]").sort();
    check("the form posts name[]=id for every ticked node, never a disabled or a mixed one",
      JSON.stringify(posted) === JSON.stringify(["c-books", "c-cds", "c-music", "c-novels", "c-poetry", "c-tapes", "c-vinyl", "cat-more"]), JSON.stringify(posted));
    await page.goto("file://" + fixture);
    await fresh();

    // ── The filter ───────────────────────────────────────────────────────
    const search = `${T("pages")} .gk-tree-search`;
    await page.fill(search, "team");
    await page.waitForFunction(() => document.querySelector('[data-gk-tree="pages"]').hasAttribute("data-gk-tree-filtering"));
    await wait(200);
    let vis = await shown("pages");
    check("the filter shows the match and its ancestors, nothing else", JSON.stringify(vis) === '["about","team"]', JSON.stringify(vis));
    check("…the match is marked", (await page.$$eval(`${T("pages")} mark.gk-tree-hit`, (m) => m.map((x) => x.textContent).join())) === "Team");
    await wait(450);
    check("…and the number of matches is announced", (await live("pages")) === "1 match");
    await page.fill(search, "cafe");
    await wait(250);
    vis = await shown("pages");
    check("the filter ignores accents and case: “cafe” finds “Café & catering”, the ancestor opens",
      JSON.stringify(vis) === '["services","cafe"]' && (await page.$eval(`${R("pages", "cafe")} mark`, (m) => m.textContent)) === "Café", JSON.stringify(vis));
    await page.fill(search, "o");
    await wait(250);
    vis = await shown("pages");
    check("a matching parent keeps its branch (“o”: About us shows Team), a parent that only leads to matches shows only them",
      ["home", "about", "team", "history", "services", "hosting", "seo"].every((i) => vis.includes(i)) && !["web", "cafe", "legal", "imprint"].some((i) => vis.includes(i)), JSON.stringify(vis));
    await page.fill(search, "zzz");
    await wait(700);
    check("no match: the tree says so", (await page.isVisible(`${T("pages")} > .gk-tree-empty`)) && (await live("pages")) === "No matches");
    await page.focus(`${R("pages", "home")} > .gk-tree-handle`).catch(() => {});
    await page.press(search, "Escape");
    await wait(100);
    vis = await shown("pages");
    check("Escape empties the filter and the tree is as it was (about open, services closed)",
      JSON.stringify(vis) === '["home","about","team","history","services","blog","legal","shop"]' && !(await page.isVisible(`${T("pages")} > .gk-tree-empty`))
      && (await page.$$(`${T("pages")} mark`)).length === 0, JSON.stringify(vis));
    await page.fill(search, "team");
    await wait(250);
    await page.focus(`${R("pages", "team")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    check("while filtered, nothing is moved: the handle says why", (await live("pages")) === "Clear the filter to move entries." && !(await page.$(".gk-tree-lifted")));
    await page.fill(search, "");
    await wait(250);

    // ── Drag and drop ────────────────────────────────────────────────────
    await fresh();
    take();
    await page.click(`${R("pages", "services")} > .gk-tree-toggle`);
    let target = await centre(R("pages", "services"));
    await drag("pages", "team", { x: target.x, y: target.y }, true);
    const during = await page.evaluate(() => ({
      inside: !!document.querySelector('[data-gk-tree-id="services"] > .gk-tree-row.gk-tree-drop-inside'),
      ghost: (document.querySelector(".gk-tree-ghost") || {}).textContent,
      dragging: document.querySelector('[data-gk-tree-id="team"]').classList.contains("gk-tree-dragging"),
    }));
    check("dragging: the row it left is marked, a label follows the pointer, the parent under it is framed",
      during.inside && during.ghost === "Team" && during.dragging, JSON.stringify(during));
    await page.mouse.up();
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    ev = (await events()).filter((e) => e.type !== "gk:treetoggle");
    req = take().filter((r) => r.path === "/move");
    check("a drop in the middle of a parent puts the node in it, last", JSON.stringify(await kidsOf("pages", "services")) === '["web","hosting","seo","cafe","team"]');
    check("the move is posted: POST JSON {id, parent, index}, with the headers of moveUrl()",
      req.length === 1 && req[0].method === "POST" && JSON.stringify(req[0].body) === '{"id":"team","parent":"services","index":4}'
      && req[0].headers["x-csrf-token"] === "tok-tree" && /application\/json/.test(req[0].headers["content-type"]), JSON.stringify(req.map((r) => [r.body, r.headers["x-csrf-token"]])));
    check("gk:treemove came first (old and new place), gk:treemoved after the save",
      ev.length === 2 && ev[0].type === "gk:treemove" && ev[0].oldParent === "about" && ev[0].oldIndex === 0 && ev[0].parent === "services" && ev[0].index === 4
      && ev[1].type === "gk:treemoved" && ev[1].ok === true && ev[1].reverted === false, JSON.stringify(ev));
    check("the levels and positions follow the move", (await attr(N("pages", "team"), "aria-level")) === "2" && (await attr(N("pages", "team"), "aria-posinset")) === "5"
      && (await attr(N("pages", "history"), "aria-setsize")) === "1");
    check("…and the live region says where it went, then that it was saved", (await live("pages")) === "Team moved. Level 2, position 5 of 5, in Services. Saved.");
    target = await centre(R("pages", "home"));
    await drag("pages", "hosting", { x: target.x, y: target.top + 3 }, true);
    const line = await page.evaluate(() => { const l = document.querySelector('[data-gk-tree="pages"] .gk-tree-drop-line'); return l && !l.hidden ? l.getBoundingClientRect().top : null; });
    check("over the top quarter of a row a line shows where the node would go", line !== null && Math.abs(line - target.top) < 3, line + " vs " + target.top);
    await page.mouse.up();
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    req = take().filter((r) => r.path === "/move");
    check("…and a drop there puts it before that row, at the top level", JSON.stringify(req.map((r) => r.body)) === '[{"id":"hosting","parent":null,"index":0}]'
      && (await kidsOf("pages", null))[0] === "hosting", JSON.stringify(req.map((r) => r.body)));
    await events();
    check("a node with 'draggable' => false has no handle", !(await page.$(`${R("pages", "home")} > .gk-tree-handle`)) && !!(await page.$(`${R("pages", "about")} > .gk-tree-handle`)));
    target = await centre(R("pages", "services"));
    await drag("pages", "blog", { x: target.x, y: target.bottom - 3 });
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    req = take().filter((r) => r.path === "/move");
    check("below an open parent is its first child", JSON.stringify(req.map((r) => r.body)) === '[{"id":"blog","parent":"services","index":0}]', JSON.stringify(req.map((r) => r.body)));
    await events();
    // Into its own branch: no indicator, a struck-through label, no move.
    await page.click(`${R("pages", "about")} > .gk-tree-toggle`).catch(() => {});
    if ((await attr(N("pages", "about"), "aria-expanded")) !== "true") await page.click(`${R("pages", "about")} > .gk-tree-toggle`);
    target = await centre(R("pages", "history"));
    await drag("pages", "about", { x: target.x, y: target.y }, true);
    const no = await page.evaluate(() => ({
      ghost: document.querySelector(".gk-tree-ghost").classList.contains("gk-tree-ghost-no"),
      line: !!document.querySelector('[data-gk-tree="pages"] .gk-tree-drop-line:not([hidden])'),
      inside: !!document.querySelector(".gk-tree-drop-inside"),
    }));
    check("over its own branch the label is struck through and nothing shows where it would go", no.ghost && !no.line && !no.inside, JSON.stringify(no));
    await page.mouse.up();
    await wait(150);
    check("…a drop there moves nothing and says why", take().filter((r) => r.path === "/move").length === 0
      && JSON.stringify(await kidsOf("pages", "about")) === '["history"]' && (await live("pages")) === "About us cannot be moved into itself.");
    target = await centre(R("pages", "legal"));
    await drag("pages", "cafe", { x: target.x, y: target.y });
    await wait(150);
    check("a disabled node takes nothing", take().filter((r) => r.path === "/move").length === 0 && (await live("pages")) === "Legal cannot take entries.");
    target = await centre(R("pages", "blog"));
    await drag("pages", "cafe", { x: target.x, y: target.y }, true);
    await wait(900);
    check("resting on a closed parent opens it (a lazy one loads)", (await attr(N("pages", "blog"), "aria-expanded")) === "true");
    await page.keyboard.press("Escape");
    await page.mouse.up();
    await wait(100);
    check("Escape during a drag cancels it", take().filter((r) => r.path === "/move").length === 0 && !(await page.$(".gk-tree-ghost")));
    await events();

    // ── Moving with the keyboard ─────────────────────────────────────────
    await fresh();
    take();
    await page.evaluate(() => GK.tree.expand("pages", "services"));
    await page.focus(`${R("pages", "web")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    check("Space on the handle lifts the node: pressed, framed, announced",
      (await attr(`${R("pages", "web")} > .gk-tree-handle`, "aria-pressed")) === "true" && (await page.$eval(N("pages", "web"), (li) => li.classList.contains("gk-tree-lifted")))
      && (await live("pages")) === "Web design lifted. Level 2, position 1 of 4, in Services.");
    await page.keyboard.press("ArrowDown");
    check("Down moves it past its neighbour and says where", JSON.stringify(await kidsOf("pages", "services")) === '["hosting","web","seo","cafe"]'
      && (await live("pages")) === "Level 2, position 2 of 4, in Services.");
    check("…nothing is saved while it is lifted", take().filter((r) => r.path === "/move").length === 0);
    a = await active();
    check("…and the focus stays on the handle", a.id === "web" && /gk-tree-handle/.test(a.cls), JSON.stringify(a));
    await page.keyboard.press(" ");
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    req = take().filter((r) => r.path === "/move");
    check("Space puts it down and posts the move", JSON.stringify(req.map((r) => r.body)) === '[{"id":"web","parent":"services","index":1}]', JSON.stringify(req.map((r) => r.body)));
    await events();
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowLeft");
    check("Left takes it out of its parent, just after it", JSON.stringify(await kidsOf("pages", null)) === '["home","about","services","web","blog","legal","shop"]'
      && (await live("pages")) === "Level 1, position 4 of 7.");
    await page.keyboard.press("ArrowRight");
    check("Right puts it into the node above, as its last child", JSON.stringify(await kidsOf("pages", "services")) === '["hosting","seo","cafe","web"]');
    await page.keyboard.press("Escape");
    check("Escape gives it back to where it was lifted, unsaved", JSON.stringify(await kidsOf("pages", "services")) === '["hosting","web","seo","cafe"]'
      && take().filter((r) => r.path === "/move").length === 0 && (await live("pages")) === "Cancelled. Web design is back in its place."
      && (await attr(`${R("pages", "web")} > .gk-tree-handle`, "aria-pressed")) === "false");
    await page.focus(`${R("pages", "hosting")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowUp");
    check("at the edge it says so and stays", (await live("pages")) === "It cannot go further this way." && (await kidsOf("pages", "services"))[0] === "hosting");
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press("Tab");
    await wait(50);
    check("Tab while lifted gives it back", (await kidsOf("pages", "services"))[0] === "hosting" && !(await page.$(".gk-tree-lifted")));
    await page.focus(`${R("pages", "about")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("ArrowRight");
    check("Right into a disabled neighbour is refused", (await live("pages")) === "Legal cannot take entries." || (await kidsOf("pages", "services")).includes("about"));
    await page.keyboard.press("Escape");
    await events();
    take();

    // ── The way back after a failed save ─────────────────────────────────
    await fresh();
    await page.evaluate(() => GK.tree.expand("pages", "services"));
    take();
    moveReply = { status: 500, body: '{"ok":false,"message":"Pages cannot move on Sundays."}' };
    await page.focus(`${R("pages", "seo")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("Home");
    await page.keyboard.press(" ");
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    ev = await events();
    check("a 500 puts the node back where the server last agreed", JSON.stringify(await kidsOf("pages", "services")) === '["web","hosting","seo","cafe"]');
    check("…shows the server's words in a toast", (await page.$$eval(".gk-toast .gk-toast-text", (t) => t.map((x) => x.textContent))).includes("Pages cannot move on Sundays."));
    check("…and gk:treemoved says ok: false, reverted: true", ev.some((e) => e.type === "gk:treemoved" && e.ok === false && e.reverted === true && e.id === "seo"), JSON.stringify(ev));
    moveReply = { status: 200, body: '{"ok":false}' };
    await page.focus(`${R("pages", "seo")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press(" ");
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    check("a 200 that says {\"ok\":false} is a refusal too, with the default words",
      JSON.stringify(await kidsOf("pages", "services")) === '["web","hosting","seo","cafe"]'
      && (await page.$$eval(".gk-toast .gk-toast-text", (t) => t.map((x) => x.textContent))).includes("The move could not be saved. The entry is back in its place."));
    await events();
    // Two moves in a row, the first held and then refused: both go back, the second is never sent.
    take();
    moveReply = { hold: true, status: 500, body: "{}" };
    await page.focus(`${R("pages", "cafe")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("Home");
    await page.keyboard.press(" ");
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemove"));
    await page.focus(`${R("pages", "web")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("End");
    await page.keyboard.press(" ");
    await wait(100);
    const sentWhileHeld = take().filter((r) => r.path === "/move").length;
    releaseMove();
    await page.waitForFunction(() => window._ev.some((e) => e.type === "gk:treemoved"));
    await wait(100);
    check("moves are sent one after the other; when one fails, it and the later ones go back",
      sentWhileHeld === 1 && take().filter((r) => r.path === "/move").length === 0 && JSON.stringify(await kidsOf("pages", "services")) === '["web","hosting","seo","cafe"]',
      sentWhileHeld + " " + JSON.stringify(await kidsOf("pages", "services")));
    moveReply = { abort: true };
    await page.focus(`${R("pages", "cafe")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowUp");
    await page.keyboard.press(" ");
    await page.waitForFunction(() => window._ev.filter((e) => e.type === "gk:treemoved").length >= 2);
    check("no network: the node goes back as well", JSON.stringify(await kidsOf("pages", "services")) === '["web","hosting","seo","cafe"]');
    moveReply = { status: 200, body: '{"ok":true}' };
    await events();

    // ── The page's veto, a tree without a URL, the API ───────────────────
    await page.evaluate(() => { window._cancel = (d) => d.id === "work"; });
    await page.focus(`${R("plain", "work")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press(" ");
    ev = await events();
    check("gk:treemove is cancelable: the page says no and the node goes back",
      JSON.stringify(await kidsOf("plain", "inbox")) === '["work","private"]' && ev.length === 1 && ev[0].type === "gk:treemove" && (await live("plain")) === "Cancelled. Work is back in its place.", JSON.stringify(ev));
    take();
    await page.focus(`${R("plain", "private")} > .gk-tree-handle`);
    await page.keyboard.press(" ");
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press(" ");
    ev = await events();
    check("without a moveUrl() the move is the page's: gk:treemoved at once, no request",
      take().length === 0 && ev.length === 2 && ev[1].type === "gk:treemoved" && ev[1].ok === true && ev[1].parent === null && ev[1].index === 1, JSON.stringify(ev));
    const api = await page.evaluate(async () => {
      const out = {};
      out.sel = GK.tree.select("cats", ["c-games"]);
      out.after = GK.tree.selected("cats");
      out.single = GK.tree.select("pages", "web");
      GK.tree.collapse("plain");
      out.collapsed = document.querySelector('[data-gk-tree-id="inbox"]').getAttribute("aria-expanded");
      await GK.tree.expand("deep", "d3");
      out.unknown = await GK.tree.expand("pages", "no-such-node");
      out.byElement = GK.tree.selected(document.querySelector("#s-cats .gk-tree")).length > 0;
      out.events = window._ev.length;
      return out;
    });
    check("the API selects, reads, opens and closes, by the tree's id or its element — and fires no event",
      api.sel.includes("c-games") && api.after.includes("c-games") && JSON.stringify(api.single) === '["web"]' && api.collapsed === "false"
      && api.unknown === false && api.byElement && api.events === 0, JSON.stringify(api));

    // ── A phone: 390px ───────────────────────────────────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => GK.tree.expand("deep"));
    await wait(100);
    const phone = await page.evaluate(() => {
      const row = (id) => document.querySelector(`[data-gk-tree-id="${id}"] > .gk-tree-row`);
      const toggleX = (id) => Math.round(row(id).querySelector(".gk-tree-toggle").getBoundingClientRect().left);
      const trees = [...document.querySelectorAll(".gk-tree")].map((t) => t.scrollWidth - t.clientWidth);
      return {
        page: document.documentElement.scrollWidth, trees,
        x5: toggleX("d5"), x6: toggleX("d6"), x11: toggleX("d11"),
        mark6: getComputedStyle(row("d6"), "::before").content, mark5: getComputedStyle(row("d5"), "::before").content,
        mark11: getComputedStyle(row("d11"), "::before").content,
      };
    });
    check("390px: nothing scrolls sideways, not the page and not a tree", phone.page <= 390 && phone.trees.every((w) => w <= 0), JSON.stringify(phone));
    check("390px: the indent stops after five levels, a level number takes over", phone.x5 === phone.x6 && phone.x6 === phone.x11 && phone.mark6 === '"6"' && phone.mark11 === '"11"' && phone.mark5 === "none", JSON.stringify(phone));
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, "tree-phone.png"), fullPage: true });
    await page.setViewportSize({ width: 1100, height: 900 });
    const wide = await page.evaluate(() => {
      const row = (id) => document.querySelector(`[data-gk-tree-id="${id}"] > .gk-tree-row`);
      return { x8: row("d8").querySelector(".gk-tree-toggle").getBoundingClientRect().left, x11: row("d11").querySelector(".gk-tree-toggle").getBoundingClientRect().left, mark8: getComputedStyle(row("d8"), "::before").content, mark9: getComputedStyle(row("d9"), "::before").content };
    });
    check("wide: the indent stops after eight levels", wide.x8 === wide.x11 && wide.mark8 === "none" && wide.mark9 === '"9"', JSON.stringify(wide));
    // A long name: cut, and in full under the pointer.
    await page.evaluate(() => GK.tree.expand("pages", "services"));
    await page.setViewportSize({ width: 390, height: 844 });
    const seo = `${R("pages", "seo")} > .gk-tree-label`;
    const cut = await page.$eval(seo, (l) => l.scrollWidth > l.clientWidth);
    await page.hover(seo);
    await wait(50);
    const tip = await page.$eval(seo, (l) => l.getAttribute("title") || l.getAttribute("data-gk-tip"));
    check("a long name is cut with an ellipsis and shows in full under the pointer", cut && tip === "Search engine optimisation for small and medium-sized businesses in the region", tip);
    await page.focus(N("pages", "seo"));
    check("…and in full on the focused node", await page.$eval(seo, (l) => getComputedStyle(l).whiteSpace === "normal"));
    await page.hover(`${R("pages", "web")} > .gk-tree-label`);
    check("a name that fits gets no title", !(await page.$eval(`${R("pages", "web")} > .gk-tree-label`, (l) => l.getAttribute("title") || l.getAttribute("data-gk-tip"))));
    await page.setViewportSize({ width: 1100, height: 900 });

    // ── Contrast: light, dark in both spellings, six themes ──────────────
    await page.evaluate(() => { GK.tree.expand("pages", "about"); GK.tree.select("pages", "web"); document.querySelector('[data-gk-tree="pages"] .gk-tree-search').value = ""; });
    const measure = () => page.evaluate(new Function(`${PIXEL}
      const q = (s) => document.querySelector(s);
      const row = (id) => q('[data-gk-tree-id="' + id + '"] > .gk-tree-row');
      const ui = (el) => ratio(px(getComputedStyle(el).color, ground(el)), ground(el));
      // A token as the page resolves it in that place: a probe element takes it as its colour.
      const token = (el, name) => { const p = document.createElement("span"); p.style.color = "var(" + name + ")"; el.appendChild(p);
        const c = getComputedStyle(p).color; p.remove(); return px(c); };
      const err = document.createElement("span"); err.className = "gk-tree-loaderror"; err.textContent = "Not loaded."; row("team").appendChild(err);
      const out = {
        label: textOn(row("team").querySelector(".gk-tree-label")),
        muted: textOn(row("history").querySelector(".gk-tree-label")),
        disabled: textOn(row("legal").querySelector(".gk-tree-label")),
        selected: textOn(row("web").querySelector(".gk-tree-label")),
        count: textOn(row("services").querySelector(".gk-tree-count")),
        badge: textOn(row("legal").querySelector(".gk-tree-badge")),
        loaderror: textOn(err),
        marker: ratio(px(getComputedStyle(row("d11"), "::before").color, ground(row("d11"))), ground(row("d11"))),
        toggle: ui(row("about").querySelector(".gk-tree-toggle")),
        handle: ui(row("team").querySelector(".gk-tree-handle")),
        ring: ratio(token(row("team"), "--gk-primary"), ground(row("team"))),
        bar: ratio(token(row("web"), "--gk-primary"), ground(row("web"))),
      };
      err.remove();
      return out;`));
    const themes = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
    const modes = ["light", "dark", "dark-class"];
    const low = [];
    const lowest = {};
    for (const theme of themes) {
      for (const mode of modes) {
        await page.evaluate(([th, m]) => {
          const b = document.body;
          if (th) b.setAttribute("data-gk-theme", th); else b.removeAttribute("data-gk-theme");
          b.removeAttribute("data-gk-mode");
          b.classList.remove("gk-dark");
          if (m === "dark") b.setAttribute("data-gk-mode", "dark");
          if (m === "dark-class") b.classList.add("gk-dark");
        }, [theme, mode]);
        const m = await measure();
        for (const [k, v] of Object.entries(m)) {
          lowest[k] = Math.min(lowest[k] || 99, v);
          const need = ["toggle", "handle", "ring", "bar"].includes(k) ? 3 : 4.5;
          if (!(v >= need)) low.push(`${theme || "plain"}/${mode} ${k} ${v.toFixed(2)}`);
        }
        if (SHOTS && !theme && mode !== "dark-class") await page.screenshot({ path: path.join(SHOTS, `tree-${mode}.png`), clip: { x: 0, y: 0, width: 700, height: 900 } });
      }
    }
    check("contrast in 21 looks (plain + six themes × light, dark, .gk-dark): text 4.5:1, chevron, handle, ring and bar 3:1", low.length === 0, low.join("; "));
    if (process.env.GK_VERBOSE) console.error("        lowest: " + Object.entries(lowest).map(([k, v]) => k + " " + v.toFixed(2)).join(", "));
    // The marked part of a match, light and dark.
    const hit = async () => { await page.fill(search, "team"); await wait(250); return page.$eval(`${T("pages")} mark.gk-tree-hit`, new Function("m", `${PIXEL} return textOn(m);`)); };
    await page.evaluate(() => { document.body.removeAttribute("data-gk-theme"); document.body.removeAttribute("data-gk-mode"); document.body.classList.remove("gk-dark"); });
    const hitLight = await hit();
    await page.evaluate(() => document.body.setAttribute("data-gk-mode", "dark"));
    const hitDark = await hit();
    check("the marked part of a match reads in light and in dark", hitLight >= 4.5 && hitDark >= 4.5, hitLight.toFixed(2) + " / " + hitDark.toFixed(2));
    await page.evaluate(() => document.body.removeAttribute("data-gk-mode"));
    await page.fill(search, "");

    // ── Without a script ─────────────────────────────────────────────────
    const plainCtx = await browser.newContext({ viewport: { width: 1100, height: 900 }, javaScriptEnabled: false });
    await plainCtx.route("http://gk.test/**", (route) => route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" }));
    const np = await plainCtx.newPage();
    await np.goto("file://" + fixture);
    const nos = await np.evaluate(() => ({
      hiddenNodes: [...document.querySelectorAll(".gk-tree-node")].filter((li) => li.offsetParent === null).map((li) => li.getAttribute("data-gk-tree-id")),
      toolbar: getComputedStyle(document.querySelector(".gk-tree-toolbar")).display,
      toggle: getComputedStyle(document.querySelector(".gk-tree-toggle")).display,
      radio: getComputedStyle(document.querySelector('[data-gk-tree-id="hosting"] .gk-tree-radio')).display,
      roles: document.querySelectorAll("[role=tree], [role=treeitem]").length,
      link: document.querySelector('[data-gk-tree-id="team"] .gk-tree-label').getAttribute("href"),
      wrap: getComputedStyle(document.querySelector('[data-gk-tree-id="seo"] .gk-tree-label')).whiteSpace,
    }));
    check("without script every node is in view and the tree is a plain nested list (no tree roles, no dead controls)",
      nos.hiddenNodes.length === 0 && nos.toolbar === "none" && nos.toggle === "none" && nos.roles === 0, JSON.stringify(nos));
    check("…a link is a link, a long name wraps, single selection is a radio button",
      nos.link === "#page-team" && nos.wrap === "normal" && nos.radio !== "none", JSON.stringify(nos));
    await np.click('label[for$="c-games-c"]');
    await np.click("#cats-submit");
    await np.waitForURL(/gk\.test\/submit/);
    const plainPost = new URL(np.url()).searchParams.getAll("cats[]").sort();
    check("…and the boxes post: the ticked ones, the pending one, a label ticks its box",
      JSON.stringify(plainPost) === JSON.stringify(["c-books", "c-games", "c-novels", "c-poetry", "c-vinyl"]), JSON.stringify(plainPost));
    await plainCtx.close();
  } catch (err) {
    check("the run reaches its end", false, (err && err.message ? err.message : String(err)).split("\n")[0]);
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
