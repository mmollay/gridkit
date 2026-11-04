#!/usr/bin/env node
/**
 * The filter builder (GridKit\FilterBuilder + js/filter-builder.js, 1.123.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup, the state, the SQL are checked there
 * (tests/filter-builder.test.php). Here: the form without script really goes round the server (add, apply
 * with Enter, an error said in words, remove, a group); with the script the same markup is drawn at once —
 * PHP and JS draw the same group, check states the same way and say the same summary (window.GK_TEST_PARITY);
 * rows added, duplicated and removed without a request and the focus where it belongs; the operators follow
 * the field, the value fields follow the operator; Apply sends compact JSON the server reads back, an
 * incomplete condition stops it, gk:filterchange can veto it; Save view posts to the URL, views kept in the
 * browser come back after a reload; the keyboard alone; hostile labels stay text; 390px without a sideways
 * scroll and with 44px buttons; contrast in light and both dark spellings under the six themes.
 *
 * Every request is answered with page.route(): /fixture.html runs ci/browser-filter-builder-fixture.php with
 * the query string, so no server is needed.
 *
 *     node ci/browser-filter-builder.js                       # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-filter-builder.js
 *     GK_SHOTS=/some/dir node ci/browser-filter-builder.js    # also writes screenshots there
 *     GK_VERBOSE=1 node ci/browser-filter-builder.js          # prints each case as it runs (stderr)
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
const fixture = (mode, query) => execFileSync(php, [path.join(__dirname, "browser-filter-builder-fixture.php"), mode, query || ""],
  { maxBuffer: 64 * 1024 * 1024 }).toString();
const SHOTS = process.env.GK_SHOTS || "";
const FONT = process.env.GK_ICONFONT || "";
const BASE = "http://gk.test";

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

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail).slice(0, 700) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };

  const requests = [];   // every request to the fixture: { method, query, body }
  const saved = [];      // what reached http://gk.test/save
  async function serve(route) {
    const req = route.request();
    const u = new URL(req.url());
    if (u.host !== "gk.test") return route.abort();
    if (u.pathname === "/icons.woff2" && FONT) return route.fulfill({ status: 200, contentType: "font/woff2", body: fs.readFileSync(FONT) });
    if (u.pathname === "/save") {
      saved.push({ method: req.method(), body: req.postData() || "" });
      return route.fulfill({ status: 200, contentType: "text/html", body: "<!doctype html><title>saved</title><p id=dest>saved</p>" });
    }
    const mode = { "/fixture.html": "", "/nojs.html": "nojs", "/de.html": "de" }[u.pathname];
    if (mode === undefined) return route.fulfill({ status: 404, body: "" });
    requests.push({ method: req.method(), query: u.search.slice(1), path: u.pathname });
    return route.fulfill({ status: 200, contentType: "text/html; charset=utf-8", body: fixture(mode, u.search.slice(1)) });
  }
  const addFont = async (page) => {
    if (!FONT || !fs.existsSync(FONT)) return;
    await page.addStyleTag({ content: `@font-face{font-family:"Material Icons";src:url(${BASE}/icons.woff2) format("woff2")}`
      + `.material-icons{font-family:"Material Icons";font-weight:400;font-style:normal;line-height:1;display:inline-block;white-space:nowrap;font-feature-settings:"liga"}` });
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
  const wait = (page, ms) => page.waitForTimeout(ms);
  const rows = (page, sel = "#gk-fb-f") => page.$$eval(sel + " .gk-fb-row:not(.gk-fb-row-group)", (l) => l.map((r) => r.id));
  const focused = (page) => page.evaluate(() => document.activeElement && (document.activeElement.id || document.activeElement.className));
  const caption = (page) => page.$eval("#result caption", (c) => c.textContent);
  const summary = (page, sel = "#gk-fb-f") => page.$eval(sel + " .gk-fb-summary-text", (s) => s.textContent);
  const navigated = async (page, fn) => {
    const before = requests.length;
    await Promise.all([page.waitForNavigation({ timeout: 4000 }).catch(() => null), fn()]);
    await wait(page, 50);
    return requests.length > before ? requests[requests.length - 1] : null;
  };

  try {
    // ── Without script: the form goes round the server ────────────────────
    const off = await browser.newContext({ viewport: { width: 1280, height: 900 }, javaScriptEnabled: false });
    await off.route("**/*", serve);
    const np = await newPage(off, "/nojs.html");
    check("without script: the value() state, applied (12 of 24)", (await caption(np)) === "12 of 24 invoices" && (await rows(np)).length === 2, await caption(np));
    let r = await navigated(np, () => np.click('#gk-fb-f button.gk-fb-add-rule[value="add:"]'));
    check("without script: “Condition” is a submit button — a third row, from the server", !!r && /f%5Bcmd%5D=add%3A/.test(r.query) && (await rows(np)).length === 3, r && r.query);
    check("…the new row is not called wrong yet", await np.$$eval('#gk-fb-f [aria-invalid="true"]', (l) => l.length) === 0
      && await np.$eval("#gk-fb-f .gk-fb-note", (n) => n.hidden));
    await np.selectOption("#gk-fb-f-c2-field", "amount");
    r = await navigated(np, () => np.click("#gk-fb-f .gk-fb-apply"));
    check("…a field changed: after Apply its operators are the money ones", await np.$$eval("#gk-fb-f-c2-op option", (o) => o.map((x) => x.textContent).join(" ")) === "= ≠ < > between",
      await np.$$eval("#gk-fb-f-c2-op option", (o) => o.map((x) => x.textContent).join(" ")));
    check("…and the empty value is said to be missing — in words, with aria-invalid", await np.$eval("#gk-fb-f-c2-err", (e) => e.textContent) === "Enter a number, such as 100 or 19.99."
      && await np.$eval("#gk-fb-f-c2-value", (i) => i.getAttribute("aria-invalid") === "true" && i.getAttribute("aria-describedby") === "gk-fb-f-c2-err")
      && /1 condition is incomplete/.test(await np.$eval("#gk-fb-f .gk-fb-note", (n) => n.textContent)));
    await np.selectOption("#gk-fb-f-c2-op", "lt");
    await np.fill("#gk-fb-f-c2-value", "1000");
    r = await navigated(np, () => np.press("#gk-fb-f-c2-value", "Enter"));
    check("…Enter in a value applies (the hidden default button), not the first remove button", !!r && /f%5Bcmd%5D=apply/.test(r.query) && (await rows(np)).length === 3, r && r.query);
    check("…and the rows are filtered on the server", (await caption(np)) === "5 of 24 invoices" && /Amount &lt; 1,000.00|Amount < 1,000.00/.test(await summary(np)), (await caption(np)) + " / " + await summary(np));
    r = await navigated(np, () => np.click('#gk-fb-f button.gk-fb-add-group[value="group:"]'));
    check("…“Group” adds a group with a condition, joined the other way", (await np.$$eval("#gk-fb-f fieldset fieldset", (l) => l.length)) === 1
      && await np.$eval("#gk-fb-f-g3-join", (s) => s.value) === "or");
    r = await navigated(np, () => np.click('#gk-fb-f button.gk-fb-dup[value="dup:3.0"]'));
    check("…duplicate inside the group", (await rows(np)).join() === "gk-fb-f-c0,gk-fb-f-c1,gk-fb-f-c2,gk-fb-f-c3-0,gk-fb-f-c3-1", (await rows(np)).join());
    r = await navigated(np, () => np.click('#gk-fb-f button.gk-fb-remove[value="remove:3"]'));
    r = await navigated(np, () => np.click('#gk-fb-f button.gk-fb-remove[value="remove:0"]'));
    check("…remove: a group, then a condition", (await rows(np)).length === 2 && (await np.$$eval("#gk-fb-f fieldset fieldset", (l) => l.length)) === 0);
    r = await navigated(np, () => np.click("#gk-fb-f .gk-fb-reset"));
    check("…Reset brings back value()", (await caption(np)) === "12 of 24 invoices", await caption(np));
    r = await navigated(np, () => np.click('#gk-fb-f .gk-fb-view >> text=My open invoices'));
    check("…a saved view is a link with the state", !!r && /^f=/.test(r.query) && await np.$eval("#tree", (p) => p.textContent) === '["and",["status","is","open"]]'
      && await np.$eval('#gk-fb-f .gk-fb-view[aria-current="true"]', (a) => a.textContent) === "My open invoices", r && r.query);
    check("…views kept in the browser need the script: the small builder shows no Save view", await np.$eval("#gk-fb-g .gk-fb-save", (s) => s.hidden && s.getClientRects().length === 0));
    const nojsGroup = await np.$eval("#gk-fb-f > .gk-fb-group", (g) => g.outerHTML);
    await off.close();

    // ── With script ───────────────────────────────────────────────────────
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    await ctx.route("**/*", serve);
    let p = await newPage(ctx, "/fixture.html?f=" + encodeURIComponent('["and",["status","is","open"]]'));
    const jsGroup = await p.$eval("#gk-fb-f > .gk-fb-group", (g) => g.outerHTML);
    check("PHP = JS: the script draws the group the server drew, attribute for attribute", jsGroup === nojsGroup, jsGroup.length + " vs " + nojsGroup.length);
    const parity = await p.evaluate(() => {
      const out = [];
      const tmp = document.createElement("div");
      for (const c of window.GK_TEST_PARITY) {
        const tree = GK.filterBuilder.set("f", c.state);
        tmp.innerHTML = c.group;
        const php = tmp.firstElementChild ? tmp.firstElementChild.outerHTML : "";
        const js = document.querySelector("#gk-fb-f > .gk-fb-group").outerHTML;
        out.push({ state: c.state.slice(0, 60), json: GK.filterBuilder.json("f") === c.json, jsJson: GK.filterBuilder.json("f"), phpJson: c.json,
          summary: GK.filterBuilder.summary("f") === c.summary, jsSum: GK.filterBuilder.summary("f"), phpSum: c.summary,
          group: js === php, tree: JSON.stringify(tree) === JSON.stringify(GK.filterBuilder.get("f")) });
      }
      return out;
    });
    const bad = parity.filter((x) => !x.json || !x.summary || !x.group || !x.tree);
    check(`PHP = JS for ${parity.length} states (odd and hostile ones too): the same checked tree, the same summary, the same markup`, parity.length >= 10 && bad.length === 0, JSON.stringify(bad.slice(0, 2)));
    await p.goto(BASE + "/fixture.html");
    const before = requests.length;
    await p.click('#gk-fb-f button.gk-fb-add-rule[value="add:"]');
    check("with script: “Condition” adds a row at once, no request", (await rows(p)).length === 3 && requests.length === before);
    check("…the focus goes to its field", (await focused(p)) === "gk-fb-f-c2-field", await focused(p));
    check("…a new row is not called wrong", await p.$$eval('#gk-fb-f [aria-invalid="true"]', (l) => l.length) === 0);
    await p.selectOption("#gk-fb-f-c2-field", "due");
    check("…the operators follow the field: a date's", await p.$$eval("#gk-fb-f-c2-op option", (o) => o.map((x) => x.textContent).join("|")) === "is on|is not on|before|after|between"
      && await p.$eval("#gk-fb-f-c2-value", (i) => i.type) === "date" && (await focused(p)) === "gk-fb-f-c2-field");
    await p.selectOption("#gk-fb-f-c1-op", "between");
    check("…an operator that takes two values keeps the one that was typed as the first", await p.$eval("#gk-fb-f-c1-value", (i) => i.value) === "100"
      && await p.$eval("#gk-fb-f-c1-value2", (i) => i.value) === "");
    await p.selectOption("#gk-fb-f-c1-op", "gt");
    check("…and back to one value: the first stays", await p.$eval("#gk-fb-f-c1-value", (i) => i.value) === "100");
    await p.selectOption("#gk-fb-f-c2-op", "between");
    check("…between: two fields, named From and To", await p.$eval("#gk-fb-f-c2-value", (i) => i.getAttribute("aria-label")) === "From, condition 3"
      && await p.$eval("#gk-fb-f-c2-value2", (i) => i.getAttribute("aria-label")) === "To, condition 3");
    await p.fill("#gk-fb-f-c2-value", "2026-12-01");
    await p.fill("#gk-fb-f-c2-value2", "2026-10-01");
    await wait(p, 600);
    check("…the summary follows what is typed, in words", (await summary(p)) === "Status is any of Open, Overdue AND Amount > 100.00 AND Due between Oct 1, 2026 and Dec 1, 2026", await summary(p));
    await p.selectOption("#gk-fb-f-c0-field", "tags");
    check("…a multiselect: “has any of”, a multiple select", await p.$eval("#gk-fb-f-c0-value", (s) => s.multiple && s.options.length === 3)
      && await p.$eval("#gk-fb-f-c0-op", (s) => s.value) === "has_any");
    await p.selectOption("#gk-fb-f-c0-field", "status");
    await p.selectOption("#gk-fb-f-c0-op", "is");
    check("…“is”: a select with “Choose…”", await p.$eval("#gk-fb-f-c0-value", (s) => !s.multiple && s.options[0].textContent === "Choose…"));
    await p.selectOption("#gk-fb-f-c0-value", "overdue");
    await p.selectOption("#gk-fb-f-c1-field", "reminded");
    check("…bool: no value field", await p.$$eval("#gk-fb-f-c1 .gk-fb-value", (l) => l.length) === 0);
    await p.click("#gk-fb-f-c1 .gk-fb-dup");
    check("…duplicate: the copy after it, the focus on the copy", (await rows(p)).length === 4 && (await focused(p)) === "gk-fb-f-c2-field"
      && await p.$eval("#gk-fb-f-c2-field", (s) => s.value) === "reminded", (await focused(p)));
    await p.click("#gk-fb-f-c2 .gk-fb-remove");
    check("…remove: the focus goes to the row that took its place", (await rows(p)).length === 3 && (await focused(p)) === "gk-fb-f-c2-field", await focused(p));
    await p.click("#gk-fb-f-c2 .gk-fb-remove");
    check("…remove the last: the focus goes to the one before", (await rows(p)).length === 2 && (await focused(p)) === "gk-fb-f-c1-field", await focused(p));
    await p.click('#gk-fb-f button.gk-fb-add-group[value="group:"]');
    check("…a group: OR inside AND, the focus in its first row", await p.$eval("#gk-fb-f-g2-join", (s) => s.value) === "or" && (await focused(p)) === "gk-fb-f-c2-0-field", await focused(p));
    await p.click('#gk-fb-f button.gk-fb-add-rule[value="add:2"]');
    await p.selectOption("#gk-fb-f-c2-0-field", "customer");
    await p.fill("#gk-fb-f-c2-0-value", "eck");
    await p.selectOption("#gk-fb-f-c2-1-field", "amount");
    await p.fill("#gk-fb-f-c2-1-value", "abc");
    await p.press("#gk-fb-f-c2-1-value", "Tab");
    check("…a wrong value is said when the field is left — in words, a symbol, aria-invalid", await p.$eval("#gk-fb-f-c2-1-err", (e) => e.textContent) === "Enter a number, such as 100 or 19.99."
      && await p.$eval("#gk-fb-f-c2-1-err svg", () => true) && await p.$eval("#gk-fb-f-c2-1-value", (i) => i.getAttribute("aria-invalid")) === "true");
    await p.fill("#gk-fb-f-c2-1-value", "2000");
    check("…and goes the moment it is right, the caret staying", await p.$$eval("#gk-fb-f-c2-1-err", (l) => l.length) === 0 && (await focused(p)) === "gk-fb-f-c2-1-value"
      && await p.$eval("#gk-fb-f-c2-1-value", (i) => i.selectionStart === 4));
    await p.selectOption("#gk-fb-f-g2-join", "and");
    check("…the join of a group: its joining word follows", await p.$eval("#gk-fb-f-c2-1 .gk-fb-conj", (c) => c.textContent) === "AND");
    await p.selectOption("#gk-fb-f-g2-join", "or");
    await p.click('#gk-fb-f button.gk-fb-add-rule[value="add:"]');
    await p.evaluate(() => { window._ev = []; document.addEventListener("gk:filterchange", (e) => { window._ev.push(e.detail); if (window._veto) e.preventDefault(); }); });
    const reqs = requests.length;
    await p.click("#gk-fb-f .gk-fb-apply");
    await wait(p, 200);
    check("Apply with an incomplete condition: nothing sent, the row marked, the focus on it, the note says so", requests.length === reqs
      && (await focused(p)) === "gk-fb-f-c3-value" && /1 condition is incomplete/.test(await p.$eval("#gk-fb-f .gk-fb-note", (n) => n.hidden ? "" : n.textContent))
      && await p.evaluate(() => window._ev.length === 0), await focused(p));
    await p.click('#gk-fb-f button.gk-fb-add-rule[value="add:"]');
    check("…a row added after that is not called wrong (only the one that was)", await p.$eval("#gk-fb-f-c4-value", (i) => !i.hasAttribute("aria-invalid")), await p.$eval("#gk-fb-f-c4", (li) => li.outerHTML.slice(0, 300)));
    await p.click("#gk-fb-f-c4 .gk-fb-remove");
    await p.click("#gk-fb-f-c3 .gk-fb-remove");
    await p.evaluate(() => { window._veto = true; });
    await p.click("#gk-fb-f .gk-fb-apply");
    await wait(p, 200);
    const ev = await p.evaluate(() => window._ev[0]);
    check("gk:filterchange, cancelable: vetoed, nothing is sent; detail { name, tree, json, summary }", requests.length === reqs && ev && ev.name === "f"
      && ev.json === await p.evaluate(() => GK.filterBuilder.json("f")) && ev.tree.rules.length === 3 && /Customer contains “eck” OR Amount = 2,000.00/.test(ev.summary), JSON.stringify(ev));
    await p.evaluate(() => { window._veto = false; });
    const want = await p.evaluate(() => GK.filterBuilder.json("f"));
    r = await navigated(p, () => p.click("#gk-fb-f .gk-fb-apply"));
    const sent = r ? new URLSearchParams(r.query) : new URLSearchParams();
    check("Apply: the state as compact JSON in one parameter, nothing else of the builder", !!r && sent.get("f") === want && [...sent.keys()].join() === "f", r && r.query);
    check("…the server reads it back to the same tree", await p.$eval("#tree", (t) => t.textContent) === want, await p.$eval("#tree", (t) => t.textContent));
    check("…and the URL is short", r && r.query.length < 400, r && r.query.length);
    await p.goBack();
    await wait(p, 300);
    // Back from the next page out of the bfcache: the page is the one that was left, its fields switched off. Played here
    // without leaving: the submit is held, then the browser's pageshow.
    const back = await p.evaluate(() => {
      const real = HTMLFormElement.prototype.submit;
      HTMLFormElement.prototype.submit = function () {};
      GK.filterBuilder.apply("f");
      HTMLFormElement.prototype.submit = real;
      const off = document.querySelectorAll("#gk-fb-f [data-gk-fb-off]").length;
      window.dispatchEvent(new PageTransitionEvent("pageshow", { persisted: true }));
      return { off, after: document.querySelectorAll("#gk-fb-f [data-gk-fb-off], #gk-fb-f [data-gk-fb-json], #gk-fb-f :disabled:not(.gk-fb-dup):not(.gk-fb-add-rule):not(.gk-fb-add-group)").length };
    });
    check("back again (pageshow from the bfcache): the fields the submit switched off work again", back.off > 5 && back.after === 0, JSON.stringify(back));

    // Reset; the API.
    await p.goto(BASE + "/fixture.html?f=" + encodeURIComponent('["and",["status","is","paid"]]'));
    const api = await p.evaluate(() => {
      const out = {};
      out.get = JSON.stringify(GK.filterBuilder.get("f"));
      out.set = JSON.stringify(GK.filterBuilder.set("gk-fb-f", { join: "or", rules: [{ field: "qty", op: "gt", value: "3" }, { field: "nope", op: "x" }] }));
      out.rows = document.querySelectorAll("#gk-fb-f .gk-fb-row").length;
      out.reset = JSON.stringify(GK.filterBuilder.reset(document.getElementById("gk-fb-f")));
      out.none = GK.filterBuilder.get("#nothing");
      return out;
    });
    check("GK.filterBuilder: get, set (checked, drawn), reset (back to value()), and null for no builder",
      api.get === '{"join":"and","rules":[{"field":"status","op":"is","value":"paid"}]}' && api.set === '{"join":"or","rules":[{"field":"qty","op":"gt","value":3}]}'
      && api.rows === 1 && api.reset === '{"join":"and","rules":[{"field":"status","op":"any_of","value":["open","overdue"]},{"field":"amount","op":"gt","value":100}]}' && api.none === null, JSON.stringify(api));
    r = await navigated(p, () => p.click("#gk-fb-f .gk-fb-reset"));
    check("Reset with script: value() applied", !!r && new URLSearchParams(r.query).get("f") === '["and",["status","any_of",["open","overdue"]],["amount","gt",100]]' && (await caption(p)) === "12 of 24 invoices");

    // Save view: to the URL, and in the browser.
    await p.fill("#gk-fb-f-view", "Big ones");
    await p.evaluate(() => { window._saves = []; document.addEventListener("gk:filtersave", (e) => window._saves.push(e.detail)); });
    await Promise.all([p.waitForNavigation().catch(() => null), p.click("#gk-fb-f .gk-fb-save-btn")]);
    const post = new URLSearchParams(saved.length ? saved[saved.length - 1].body : "");
    check("Save view posts to saveUrl(): the state as JSON under f, the name under f_view", saved.length === 1 && saved[0].method === "POST"
      && post.get("f") === '["and",["status","any_of",["open","overdue"]],["amount","gt",100]]' && post.get("f_view") === "Big ones" && !post.has("f[cmd]"), saved.length && saved[0].body);
    await p.goto(BASE + "/fixture.html");
    await p.click("#gk-fb-f .gk-fb-save-btn");
    check("…without a name: it asks for one, nothing is sent", saved.length === 1 && (await focused(p)) === "gk-fb-f-view"
      && await p.$eval("#gk-fb-f-view", (i) => i.validationMessage) === "Give the view a name.");
    check("views kept in the browser: Save view shows with the script", await p.$eval("#gk-fb-g .gk-fb-save", (s) => !s.hidden));
    await p.click('#gk-fb-g button.gk-fb-add-rule[value="add:"]');
    await p.fill("#gk-fb-g-c0-value", "Anna");
    await p.click('#gk-fb-g button.gk-fb-add-rule[value="add:"]');
    await p.fill("#gk-fb-g-view", "Annas");
    await p.click("#gk-fb-g .gk-fb-save-btn");
    await wait(p, 100);
    check("…an incomplete condition is not saved", await p.evaluate(() => localStorage.getItem("gk-filterbuilder-g")) === null);
    await p.click("#gk-fb-g-c1 .gk-fb-remove");
    await p.click("#gk-fb-g .gk-fb-save-btn");
    await wait(p, 100);
    check("…saved in localStorage and listed with a remove button", await p.evaluate(() => localStorage.getItem("gk-filterbuilder-g")) === '[{"label":"Annas","json":"[\\"and\\",[\\"name\\",\\"contains\\",\\"Anna\\"]]"}]'
      && await p.$eval("#gk-fb-g .gk-fb-views", (n) => !n.hidden) && await p.$eval("#gk-fb-g .gk-fb-view-remove", (b) => b.getAttribute("aria-label")) === "Remove the view Annas");
    await p.evaluate(() => document.addEventListener("gk:filtersave", (e) => { if (e.detail.label === "Mine") e.preventDefault(); }));
    await p.fill("#gk-fb-g-view", "Mine");
    await p.click("#gk-fb-g .gk-fb-save-btn");
    check("…gk:filtersave vetoed: the page keeps it, not the browser", (await p.evaluate(() => JSON.parse(localStorage.getItem("gk-filterbuilder-g")).length)) === 1);
    await p.reload();
    check("…after a reload the view is there, a link with its state", await p.$eval("#gk-fb-g .gk-fb-view", (a) => a.textContent.startsWith("Annas") && new URL(a.href).searchParams.get("g") === '["and",["name","contains","Anna"]]'));
    await p.click("#gk-fb-g .gk-fb-view-remove");
    check("…removed, the list is gone and the focus is not lost", await p.evaluate(() => localStorage.getItem("gk-filterbuilder-g")) === "[]"
      && await p.$eval("#gk-fb-g .gk-fb-views", (n) => n.hidden) && (await focused(p)) === "gk-fb-g-view");
    await p.evaluate(() => localStorage.clear());

    // The keyboard alone.
    await p.goto(BASE + "/fixture.html");
    await p.focus('#gk-fb-f button.gk-fb-add-rule[value="add:"]');
    await p.keyboard.press("Enter");
    check("keyboard: Enter on “Condition” adds a row and puts the focus in it", (await focused(p)) === "gk-fb-f-c2-field");
    const order = [];
    for (let i = 0; i < 5; i++) { order.push(await focused(p)); await p.keyboard.press("Tab"); }
    check("…Tab: field, operator, value, duplicate, remove", order.join() === "gk-fb-f-c2-field,gk-fb-f-c2-op,gk-fb-f-c2-value,gk-fb-btn gk-fb-dup,gk-fb-btn gk-fb-remove", order.join());
    await p.focus("#gk-fb-f-c2-field");
    await p.keyboard.press("ArrowDown");
    await wait(p, 50);
    check("…an arrow key on the field changes it and keeps the focus there", await p.$eval("#gk-fb-f-c2-field", (s) => s.value) === "amount" && (await focused(p)) === "gk-fb-f-c2-field");
    await p.focus("#gk-fb-f-c2-value");
    await p.keyboard.type("250");
    r = await navigated(p, () => p.keyboard.press("Enter"));
    check("…Enter in the value applies", !!r && JSON.parse(new URLSearchParams(r.query).get("f")).length === 4, r && r.query);
    await p.focus("#gk-fb-f-c0 .gk-fb-remove");
    await p.keyboard.press("Space");
    check("…Space on remove: gone, the focus on the next row", (await rows(p)).length === 2 && (await focused(p)) === "gk-fb-f-c0-field", await focused(p));

    // Hostile labels stay text.
    check("hostile labels and values stay text: nothing ran", await p.evaluate(() => window.__pwned === undefined && !document.querySelector("#gk-fb-f img, #gk-fb-f svg[onload]")));
    await p.selectOption("#gk-fb-f-c0-field", "evil");
    check("…drawn by the script as well", await p.evaluate(() => window.__pwned === undefined && document.querySelector("#gk-fb-f-c0-field option[value='evil']").textContent === '<img src=x onerror="window.__pwned=1">'));
    await p.close();

    // German.
    const dp = await newPage(ctx, "/de.html");
    await dp.selectOption("#gk-fb-f-c1-op", "between");
    await dp.fill("#gk-fb-f-c1-value", "1000");
    await dp.fill("#gk-fb-f-c1-value2", "19,5");
    await wait(dp, 600);
    check("German: the words and the numbers of the page", (await summary(dp)) === "Status ist eines von Open, Overdue UND Amount zwischen 19,50 und 1.000,00", await summary(dp));
    await dp.close();

    // ── 390px ─────────────────────────────────────────────────────────────
    const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    await phone.route("**/*", serve);
    const pp = await newPage(phone, "/fixture.html?f=" + encodeURIComponent('["and",["status","any_of",["open","overdue"]],["or",["amount","between",[100,2000]],["due","lt","2026-11-04"],["and",["customer","contains","Ecklund & Partner"]]]]'));
    await addFont(pp);
    const m = await pp.evaluate(() => {
      const sw = document.scrollingElement.scrollWidth, w = window.innerWidth;
      const form = document.getElementById("gk-fb-f").getBoundingClientRect();
      const out = [...document.querySelectorAll("#gk-fb-f *")].filter((el) => el.getClientRects().length && el.getBoundingClientRect().right > form.right + 0.5).map((el) => el.className || el.tagName);
      const small = [...document.querySelectorAll("#gk-fb-f .gk-fb-btn")].filter((b) => b.getBoundingClientRect().width < 44 || b.getBoundingClientRect().height < 44).length;
      const fields = [...document.querySelectorAll("#gk-fb-f .gk-fb-cells .gk-filter")].filter((f) => f.getBoundingClientRect().height < 44).length;
      // The joining word and the buttons share the first line; the cells below take the row's whole width.
      const narrow = [...document.querySelectorAll("#gk-fb-f .gk-fb-row:not(.gk-fb-row-group)")].filter((row) => {
        const cells = row.querySelector(".gk-fb-cells").getBoundingClientRect(), acts = row.querySelector(".gk-fb-actions").getBoundingClientRect();
        return cells.width < row.getBoundingClientRect().width - 1 || acts.bottom > cells.top + 1;
      }).map((row) => row.id);
      return { sw, w, out, small, fields, narrow };
    });
    check("390px: no sideways scroll, nothing past the builder's edge", m.sw <= m.w && m.out.length === 0, JSON.stringify(m));
    check("390px: every button and field at least 44px", m.small === 0 && m.fields === 0, JSON.stringify(m));
    check("390px: the buttons on the line of the joining word, the cells the whole width below", m.narrow.length === 0, JSON.stringify(m.narrow));
    // 320px, three levels deep, a field whose label is longer than the row: nothing past the edge either.
    const phoneUrl = pp.url();
    await pp.setViewportSize({ width: 320, height: 700 });
    await pp.evaluate(() => GK.filterBuilder.set("f", '["and",["evil","is","<b>x</b>"],["or",["amount","gt",1],["and",["evil","is","<b>x</b>"],["customer","contains","x"]]]]'));
    await wait(pp, 100);
    const m320 = await pp.evaluate(() => {
      const form = document.getElementById("gk-fb-f").getBoundingClientRect();
      return { sw: document.scrollingElement.scrollWidth, w: window.innerWidth,
        out: [...document.querySelectorAll("#gk-fb-f *")].filter((el) => el.getClientRects().length && el.getBoundingClientRect().right > form.right + 0.5).map((el) => el.id || el.className) };
    });
    check("320px, three levels, a label longer than the row: no sideways scroll, nothing past the edge", m320.sw <= m320.w && m320.out.length === 0, JSON.stringify(m320));
    await pp.setViewportSize({ width: 390, height: 844 });
    await pp.goto(phoneUrl);
    if (SHOTS) {
      fs.mkdirSync(SHOTS, { recursive: true });
      await pp.locator("#gk-fb-f").screenshot({ path: path.join(SHOTS, "filterbuilder-390-light.png") });
      await pp.evaluate(() => document.body.setAttribute("data-gk-mode", "dark"));
      await pp.locator("#gk-fb-f").screenshot({ path: path.join(SHOTS, "filterbuilder-390-dark.png") });
    }
    await phone.close();

    // ── Contrast: six themes × light, dark, .gk-dark ─────────────────────
    const cp = await newPage(ctx, "/fixture.html?f=" + encodeURIComponent('["and",["status","is","open"],["or",["amount","gt","abc"],["reminded","true"]]]'));
    await addFont(cp);
    // The state has a wrong value (for the error and the note); the first view is marked as the applied one by hand.
    await cp.evaluate(() => document.querySelector("#gk-fb-f .gk-fb-view").setAttribute("aria-current", "true"));
    const measured = [];
    for (const theme of ["indigo", "ocean", "forest", "rose", "amber", "slate"]) {
      for (const mode of ["light", "dark", "class"]) {
        await cp.evaluate(([t, md]) => {
          document.body.setAttribute("data-gk-theme", t);
          document.body.setAttribute("data-gk-mode", md === "dark" ? "dark" : "light");
          document.body.classList.toggle("gk-dark", md === "class");
        }, [theme, mode]);
        await wait(cp, 350);   // the buttons fade their colour: measured once it has settled
        const r2 = await cp.evaluate(PIXEL + `
          const q = (s) => document.querySelector(s);
          ({
            legend: textOn(q("#gk-fb-f fieldset fieldset > legend")),
            join: textOn(q("#gk-fb-f .gk-fb-join-label")),
            conj: textOn(q("#gk-fb-f .gk-fb-conj")),
            nestedConj: textOn(q("#gk-fb-f fieldset fieldset .gk-fb-conj")),
            field: textOn(q("#gk-fb-f-c0-field")),
            error: textOn(q("#gk-fb-f .gk-fb-error span")),
            note: textOn(q("#gk-fb-f .gk-fb-note span")),
            summary: textOn(q("#gk-fb-f .gk-fb-summary-text")),
            summaryLabel: textOn(q("#gk-fb-f .gk-fb-summary-label")),
            view: textOn(q("#gk-fb-f .gk-fb-view:not([aria-current])")),
            current: textOn(q('#gk-fb-f .gk-fb-view[aria-current="true"]')),
            viewsLabel: textOn(q("#gk-fb-f .gk-fb-views-label")),
            icon: textOn(q("#gk-fb-f .gk-fb-btn")),
            add: textOn(q("#gk-fb-f .gk-fb-add-rule span")),
            // The bar of a nested group and the red border of a wrong field: 3:1 against what they stand on.
            bar: ratio(px(getComputedStyle(q("#gk-fb-f fieldset fieldset")).borderInlineStartColor), ground(q("#gk-fb-f fieldset").parentElement)),
            invalid: ratio(px(getComputedStyle(q('#gk-fb-f [aria-invalid="true"]')).borderTopColor), ground(q('#gk-fb-f [aria-invalid="true"]').parentElement)),
            nestedGround: getComputedStyle(q("#gk-fb-f fieldset fieldset")).backgroundColor !== getComputedStyle(q("#gk-fb-f fieldset")).backgroundColor,
          })`);
        measured.push({ theme, mode, ...r2 });
        if (SHOTS && theme === "indigo" && mode !== "class") {
          fs.mkdirSync(SHOTS, { recursive: true });
          await cp.locator("#gk-fb-f").screenshot({ path: path.join(SHOTS, `filterbuilder-1280-${mode}.png`) });
        }
      }
    }
    const texts = ["legend", "join", "conj", "nestedConj", "field", "error", "note", "summary", "summaryLabel", "view", "current", "viewsLabel", "icon", "add"];
    const weak = measured.filter((x) => texts.some((k) => x[k] < 4.5) || x.bar < 3 || x.invalid < 3);
    check("contrast: every text 4.5:1 (legend, labels, joining words, fields, the error and the note, summary, views, buttons), the group bar and the red border 3:1 — 6 themes × light, dark, .gk-dark",
      weak.length === 0, JSON.stringify(weak.length ? weak.slice(0, 2) : measured.slice(0, 1)));
    check("a group inside a group stands on another ground, in every mode", measured.every((x) => x.nestedGround));
    await cp.close();
    await ctx.close();
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
