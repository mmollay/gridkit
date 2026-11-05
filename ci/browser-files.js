#!/usr/bin/env node
/**
 * The file manager (GridKit\FileManager + js/file-manager.js, 1.125.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup and receive() are checked there
 * (tests/file-manager.test.php). What the script does is checked here — the toolbar acting at once and
 * the state in the address; the selection by pointer and by keyboard (arrows by rows of tiles, Shift for a
 * range, Ctrl+A, Escape, Enter, Delete); the details beside the list, the URL copied, rename; delete
 * (confirmed, refused by the server), move, a new folder, an action left to the page; the upload — chosen,
 * dropped, pasted — with the checks in the browser (type, size, empty, count), a request per file with
 * the token, the hidden fields and the folder, the progress bar, two at a time, cancel and retry, the
 * server's refusal, a 500, an answer that is no answer, the new file in the listing without a reload,
 * object URLs given back; the live region; the API; 390px (two columns, the details as a sheet with the
 * focus kept inside, 44px targets, no sideways scroll); contrast in light, both dark spellings and the six
 * themes; and the page without script, whose forms post by themselves.
 *
 * The page and the server are page.route(): http://gk.test/fixture.html (ci/browser-files-fixture.php),
 * POST /upload, POST /act. No server is needed.
 *
 *     node ci/browser-files.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-files.js
 *     GK_SHOTS=/some/dir GK_ICONFONT=/path/MaterialIcons.woff2 node ci/browser-files.js   # with pictures
 *     GK_VERBOSE=1 node ci/browser-files.js          # prints each case as it runs (stderr)
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
const fixture = (mode, query) => execFileSync(php, [path.join(__dirname, "browser-files-fixture.php"), mode || "", query || ""],
  { maxBuffer: 64 * 1024 * 1024 }).toString();
const PAGES = { "/fixture.html": fixture(""), "/nojs.html": fixture("nojs") };
const SHOTS = process.env.GK_SHOTS || "";
const FONT = process.env.GK_ICONFONT || "";
const BASE = "http://gk.test";
const M = '[data-gk-files="media"]';
const P = '[data-gk-files="pick"]';
const PNG = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAQAAAADCAIAAAA7ljmRAAAAEklEQVR4nGP4z8AARMg0AwMDAEO+B/kmOaB8AAAAAElFTkSuQmCC", "base64");

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
  const textOn = (el) => ratio(px(getComputedStyle(el).color, ground(el)), ground(el));
`;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail).slice(0, 600) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };

  // ── The server ───────────────────────────────────────────────────────────
  const uploads = [];     // { name, fields, headers, body }
  const actions = [];     // { fields, headers }
  const hold = {};        // file name → promise
  const release = {};
  let failOnce = {};      // file name → times a 500
  let actRefuse = false;
  let fileSeq = 0;
  const holdFor = (n) => { hold[n] = new Promise((res) => { release[n] = res; }); };

  /** The fields of a multipart body, by name (a file field: its filename). */
  function multipart(req) {
    const buf = req.postDataBuffer() || Buffer.alloc(0);
    const type = req.headers()["content-type"] || "";
    const m = /boundary=(.+)$/.exec(type);
    const fields = {};
    if (/x-www-form-urlencoded/.test(type)) {
      for (const [k, v] of new URLSearchParams(buf.toString("utf8"))) (fields[k] = fields[k] || []).push(v);
      return fields;
    }
    if (!m) return fields;
    const parts = buf.toString("latin1").split("--" + m[1]);
    for (const part of parts) {
      const head = /Content-Disposition: form-data; name="([^"]+)"(?:; filename="([^"]*)")?/i.exec(part);
      if (!head) continue;
      const value = head[2] !== undefined ? { filename: Buffer.from(head[2], "latin1").toString("utf8") } : part.split("\r\n\r\n").slice(1).join("\r\n\r\n").replace(/\r\n$/, "");
      (fields[head[1]] = fields[head[1]] || []).push(value);
    }
    return fields;
  }

  async function serve(route) {
    const req = route.request();
    const u = new URL(req.url());
    if (u.host !== "gk.test") return route.fulfill({ status: 404, body: "" });
    if (PAGES[u.pathname]) {
      const body = u.search ? fixture(u.pathname === "/nojs.html" ? "nojs" : "", u.search.slice(1)) : PAGES[u.pathname];
      return route.fulfill({ status: 200, contentType: "text/html; charset=utf-8", body });
    }
    if (u.pathname === "/icons.woff2" && FONT) return route.fulfill({ status: 200, contentType: "font/woff2", body: fs.readFileSync(FONT) });
    if (u.pathname.startsWith("/img/")) return route.fulfill({ status: 200, contentType: "image/png", body: PNG });
    if (u.pathname === "/upload" && req.method() === "POST") {
      const fields = multipart(req);
      const name = (fields["files[]"] || [{}])[0].filename || "";
      uploads.push({ name, fields, headers: req.headers() });
      if (!req.headers()["x-requested-with"]) {
        return route.fulfill({ status: 200, contentType: "text/html", body: `<!doctype html><p id="dest">uploaded ${(fields["files[]"] || []).length}</p>` });
      }
      if (hold[name]) await hold[name];
      if (failOnce[name] > 0) {
        failOnce[name]--;
        return route.fulfill({ status: 500, contentType: "text/html", body: "<h1>Internal error</h1>" }).catch(() => {});
      }
      if (name.startsWith("refuse")) return route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ ok: false, message: "The server refused " + name + "." }) }).catch(() => {});
      if (name.startsWith("junk")) return route.fulfill({ status: 200, contentType: "text/html", body: "<html>not json</html>" }).catch(() => {});
      const id = "up" + (++fileSeq);
      const file = { id, name: name.startsWith("evil") ? '<img src=x onerror="window.__pwned=9">.png' : name, size: 2048, mime: name.endsWith(".pdf") ? "application/pdf" : "image/png",
        modified: 1790000000, url: name.endsWith(".pdf") ? null : BASE + "/img/" + id + ".png" };
      return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true, file }) }).catch(() => {});
    }
    if (u.pathname === "/act" && req.method() === "POST") {
      const fields = multipart(req);
      actions.push({ fields, headers: req.headers() });
      if (!req.headers()["x-requested-with"]) return route.fulfill({ status: 200, contentType: "text/html", body: `<!doctype html><p id="dest">${(fields.action || [""])[0]}</p>` });
      if (actRefuse) return route.fulfill({ status: 409, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Kept for ten years." }) });
      const action = (fields.action || [""])[0];
      const body = { ok: true };
      if (action === "rename") body.file = { id: fields["ids[]"][0], name: fields.name[0] };
      if (action === "folder") body.folder = { id: "nf1", name: fields.name[0] };
      return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
    }
    return route.fulfill({ status: 200, contentType: "text/html", body: `<!doctype html><title>${u.pathname}</title><p id="dest">${u.pathname}${u.search}</p>` });
  }

  const addFont = async (page) => {
    if (!FONT || !fs.existsSync(FONT)) return;
    await page.addStyleTag({ content: `@font-face{font-family:"Material Icons";src:url(${BASE}/icons.woff2) format("woff2")}`
      + `.material-icons{font-family:"Material Icons";font-weight:normal;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;`
      + `display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;font-feature-settings:"liga"}` });
    await page.evaluate(() => document.fonts.ready);
  };

  async function open(opts = {}) {
    const ctx = await browser.newContext({ viewport: opts.viewport || { width: 1280, height: 900 }, hasTouch: !!opts.touch,
      isMobile: !!opts.touch, javaScriptEnabled: opts.js !== false, permissions: ["clipboard-read", "clipboard-write"] });
    await ctx.route("**/*", serve);
    const page = await ctx.newPage();
    page.on("pageerror", (e) => pageErrors.push(String(e)));
    // Count object URLs made and given back.
    await page.addInitScript(() => {
      // http://gk.test is no secure context: the clipboard API is not there. One that records what it is given.
      if (!navigator.clipboard) Object.defineProperty(navigator, "clipboard", { value: { writeText: (t) => { window.__clip = t; return Promise.resolve(); } } });
      window.__made = 0; window.__revoked = 0;
      // A route answers without a network, so no upload progress is reported: report "all sent" once,
      // as a fast line would, before the server has answered.
      const send = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype.send = function (body) {
        const r = send.call(this, body);
        const up = this.upload;
        setTimeout(() => { if (up.onprogress) up.onprogress({ lengthComputable: true, loaded: 1000, total: 1000 }); }, 0);
        return r;
      };
      const c = URL.createObjectURL.bind(URL), r = URL.revokeObjectURL.bind(URL);
      URL.createObjectURL = (b) => { window.__made++; return c(b); };
      URL.revokeObjectURL = (u) => { window.__revoked++; return r(u); };
      window.__events = [];
      ["gk:filesselect", "gk:filesopen", "gk:filesupload", "gk:filesuploaded", "gk:fileserror", "gk:filesaction", "gk:filesdone"].forEach((n) =>
        document.addEventListener(n, (e) => window.__events.push({ type: n, detail: JSON.parse(JSON.stringify(e.detail, (k, v) => (v instanceof File ? { file: v.name } : v))) })));
    });
    await page.goto(BASE + (opts.path || "/fixture.html"));
    if (opts.js !== false) await addFont(page);
    return { ctx, page };
  }

  const live = (page, sel = M) => page.$eval(sel + " [data-gk-files-live]", (n) => n.textContent);
  const waitLive = async (page, re, sel = M) => {
    for (let i = 0; i < 100; i++) { if (re.test(await live(page, sel))) return true; await sleep(20); }
    return false;
  };
  const shown = (page, sel = M) => page.$$eval(sel + " .gk-files-items > [data-gk-file]", (ns) => ns.filter((n) => !n.hidden).map((n) => n.getAttribute("data-gk-file")));
  const events = (page, type) => page.evaluate((t) => window.__events.filter((e) => e.type === t), type);
  const until = async (fn, tries = 150) => { for (let i = 0; i < tries; i++) { if (await fn()) return true; await sleep(20); } return false; };
  const fileOf = (name, mime, bytes) => ({ name, mimeType: mime, buffer: bytes || PNG });

  // ── 1. The page, enhanced ─────────────────────────────────────────────────
  {
    const { ctx, page } = await open();
    const st = await page.$eval(M, (r) => ({
      js: r.classList.contains("gk-files-js"),
      apply: getComputedStyle(r.querySelector(".gk-files-apply")).display,
      send: getComputedStyle(r.querySelector(".gk-files-send")).display,
      all: !r.querySelector(".gk-files-all").hidden,
      download: !r.querySelector('[data-gk-files-action="download"]').hidden,
      disabled: [...r.querySelectorAll("[data-gk-files-action]")].every((b) => b.disabled),
      pickHidden: getComputedStyle(r.querySelector(".gk-files-pick input")).position,
    }));
    check("the script marks the manager and hides Apply and Upload", st.js && st.apply === "none" && st.send === "none", JSON.stringify(st));
    check("'Select all' and the event-only Download appear", st.all && st.download, JSON.stringify(st));
    check("the actions are off while nothing is ticked", st.disabled);
    check("the file field hides inside its button", st.pickHidden === "absolute", st.pickHidden);
    check("the hostile name is text", await page.evaluate(() => !window.__pwned && !!document.querySelector('[data-gk-file="x"] .gk-files-name').textContent.includes("<img")));
    check("the top shows its files in name order", JSON.stringify(await shown(page)) === JSON.stringify(["x", "a", "b", "c", "d", "e"]), JSON.stringify(await shown(page)));

    // ── Toolbar ──
    await page.fill(M + " [data-gk-files-q]", "desk");
    check("typing filters at once", await until(async () => JSON.stringify(await shown(page)) === '["d"]'), JSON.stringify(await shown(page)));
    check("the summary says how many of how many", (await page.$eval(M + " [data-gk-files-summary]", (n) => n.textContent)) === "1 of 6 files shown");
    check("the live region says it too", await waitLive(page, /1 of 6 files shown/));
    check("the search is in the address", new URL(page.url()).searchParams.get("q") === "desk", page.url());
    await page.press(M + " [data-gk-files-q]", "Enter");
    check("Enter does not reload", new URL(page.url()).pathname === "/fixture.html" && (await shown(page)).length === 1);
    await page.press(M + " [data-gk-files-q]", "Escape");
    check("Escape empties the search", await until(async () => (await shown(page)).length === 6));
    await page.fill(M + " [data-gk-files-q]", "zzz");
    await until(async () => (await shown(page)).length === 0);
    check("nothing found says so", await page.$eval(M + " .gk-files-empty", (n) => !n.hidden && n.textContent === "No file matches."));
    await page.fill(M + " [data-gk-files-q]", "");
    await until(async () => (await shown(page)).length === 6);
    await page.selectOption(M + " [data-gk-files-type]", "image");
    check("the type filter", JSON.stringify(await shown(page)) === '["x","a","d","e"]', JSON.stringify(await shown(page)));
    await page.selectOption(M + " [data-gk-files-sort]", "size-desc");
    check("the order follows the select", JSON.stringify(await shown(page)) === '["a","e","d","x"]', JSON.stringify(await shown(page)));
    await page.selectOption(M + " [data-gk-files-type]", "all");
    await page.selectOption(M + " [data-gk-files-sort]", "date-desc");
    check("newest first", JSON.stringify(await shown(page)) === '["d","a","b","e","x","c"]', JSON.stringify(await shown(page)));
    const u = new URL(page.url());
    check("type and order in the address, the default left out", u.searchParams.get("sort") === "date-desc" && !u.searchParams.has("type"), page.url());
    await page.click(M + ' .gk-files-viewbtn:has(input[value="list"])');
    const list = await page.$eval(M, (r) => ({ cls: r.className, disp: getComputedStyle(r.querySelector(".gk-files-item")).display,
      head: getComputedStyle(r.querySelector(".gk-files-head")).display, cols: getComputedStyle(r.querySelector('[data-gk-file="a"]')).gridTemplateColumns }));
    check("the view switches to a list", /gk-files-view-list/.test(list.cls) && list.disp === "grid" && list.head === "grid", JSON.stringify(list));
    check("the list has six columns", list.cols.split(" ").length === 6, list.cols);
    check("the view is in the address", new URL(page.url()).searchParams.get("view") === "list");
    await page.click(M + ' .gk-files-viewbtn:has(input[value="grid"])');
    await page.selectOption(M + " [data-gk-files-sort]", "name-asc");
    check("back to the grid, default parameters dropped", !new URL(page.url()).search.includes("view") && !new URL(page.url()).search.includes("sort"), page.url());
    // The receipt drawer keeps its own parameters under its name.
    await page.selectOption(P + " [data-gk-files-sort]", "size-desc");
    check("param(): a second manager writes pick[sort]", new URL(page.url()).searchParams.get("pick[sort]") === "size-desc" && !new URL(page.url()).searchParams.has("sort"), page.url());

    // ── Selection by keyboard ──
    await page.focus(M + ' [data-gk-file="x"] .gk-files-check');
    await page.keyboard.press("ArrowRight");
    check("→ moves to the next file", await page.evaluate(() => document.activeElement.value) === "a");
    const cols = await page.$$eval(M + " .gk-files-items > .gk-files-item:not([hidden])", (ns) => new Set(ns.map((n) => n.offsetTop)).size && ns.filter((n) => n.offsetTop === ns[0].offsetTop).length);
    await page.keyboard.press("ArrowDown");
    const below = await page.evaluate(() => document.activeElement.closest(".gk-files-item").getAttribute("data-gk-file") || document.activeElement.closest(".gk-files-item").getAttribute("data-gk-folder"));
    const expectBelow = await page.$$eval(M + " .gk-files-items > .gk-files-item:not([hidden])", (ns, c) => {
      const i = ns.findIndex((n) => n.getAttribute("data-gk-file") === "a");
      const n = ns[Math.min(ns.length - 1, i + c)];
      return n.getAttribute("data-gk-file") || n.getAttribute("data-gk-folder");
    }, cols);
    check(`↓ moves a row of tiles down (${cols} to a row)`, below === expectBelow && cols > 2, below + " vs " + expectBelow);
    await page.keyboard.press("Home");
    check("Home: the first entry, a folder link", await page.evaluate(() => document.activeElement.classList.contains("gk-files-name") && !!document.activeElement.closest("[data-gk-folder]")));
    await page.keyboard.press("End");
    check("End: the last", await page.evaluate(() => document.activeElement.value) === "e");
    await page.keyboard.press("Space");
    check("Space ticks", await page.$eval(M + ' [data-gk-file="e"] .gk-files-check', (n) => n.checked));
    check("the bar counts", (await page.$eval(M + " [data-gk-files-count]", (n) => n.textContent)) === "1 selected");
    check("a ticked tile is marked — by its tick and its frame", await page.$eval(M + ' [data-gk-file="e"]', (n) => n.classList.contains("gk-files-selected") && getComputedStyle(n).borderTopColor !== getComputedStyle(n.previousElementSibling).borderTopColor));
    check("the actions are on", await page.$eval(M + ' [data-gk-files-action="delete"]', (n) => !n.disabled));
    check("gk:filesselect told the page", (await events(page, "gk:filesselect")).pop().detail.ids.join() === "e");
    await page.keyboard.press("Shift+ArrowLeft");
    await page.keyboard.press("Shift+ArrowLeft");
    check("Shift+← ticks a range", JSON.stringify(await page.evaluate(() => GK.files.selected("media"))) === '["c","d","e"]', JSON.stringify(await page.evaluate(() => GK.files.selected("media"))));
    await page.keyboard.press("Control+a");
    check("Ctrl+A ticks every file", (await page.evaluate(() => GK.files.selected("media"))).length === 6);
    check("'Select all' is ticked", await page.$eval(M + " [data-gk-files-all]", (n) => n.checked && !n.indeterminate));
    await page.keyboard.press("Escape");
    check("Escape lets go", (await page.evaluate(() => GK.files.selected("media"))).length === 0);
    await page.click(M + " .gk-files-all");
    check("'Select all' by pointer", (await page.evaluate(() => GK.files.selected("media"))).length === 6);
    await page.click(M + " .gk-files-all");
    await page.click(M + ' [data-gk-file="b"] label.gk-files-thumb');
    check("a click on the picture ticks", JSON.stringify(await page.evaluate(() => GK.files.selected("media"))) === '["b"]');
    await page.click(M + ' [data-gk-file="d"] .gk-files-check', { modifiers: ["Shift"] });
    await sleep(30);
    check("Shift+click ticks a range", JSON.stringify(await page.evaluate(() => GK.files.selected("media"))) === '["b","c","d"]', JSON.stringify(await page.evaluate(() => GK.files.selected("media"))));
    check("'Select all' is half", await page.$eval(M + " [data-gk-files-all]", (n) => n.indeterminate));
    await page.evaluate(() => GK.files.select("media", ["b", "c", "d"], false));

    // ── Details ──
    await page.focus(M + ' [data-gk-file="a"] .gk-files-check');
    await page.keyboard.press("Enter");
    const det = await page.$eval(M + " .gk-files-details", (d) => ({ open: !d.hidden, focus: document.activeElement === d, title: d.querySelector(".gk-files-details-title").textContent,
      text: d.querySelector(".gk-files-props").textContent, side: d.getBoundingClientRect().left > d.closest(".gk-files").querySelector(".gk-files-body").getBoundingClientRect().right - 1,
      img: d.querySelector(".gk-files-preview img") && d.querySelector(".gk-files-preview img").alt }));
    check("Enter on a box opens the details, beside the list, with the focus", det.open && det.focus && det.side, JSON.stringify(det));
    check("the details say type, size, dimensions, date", det.title === "Alpine lake.jpg" && /JPG image \(image\/jpeg\)/.test(det.text) && /471 KB/.test(det.text) && /2,400 × 1,600 px/.test(det.text), det.text);
    check("the preview is named", det.img === "Preview of Alpine lake.jpg");
    check("the current tile is marked", await page.$eval(M + ' [data-gk-file="a"]', (n) => n.classList.contains("gk-files-current")));
    check("gk:filesopen", (await events(page, "gk:filesopen")).pop().detail.id === "a");
    await page.click(M + " [data-gk-files-copy]");
    check("Copy URL copies the address", await until(async () => (await page.evaluate(() => window.__clip)) === BASE + "/img/a.png"));
    check("…and says so", await waitLive(page, /The URL was copied/));
    await page.keyboard.press("Escape");
    check("Escape closes and gives the focus back", await page.evaluate(() => document.querySelector('[data-gk-files="media"] .gk-files-details').hidden && document.activeElement.value === "a"));
    await page.click(M + ' [data-gk-file="d"] .gk-files-name');
    check("a click on a name opens instead of following the link", new URL(page.url()).pathname === "/fixture.html" && await page.$eval(M + " .gk-files-details-title", (n) => n.textContent === "Desk.png"));
    check("an image without dimensions is measured", await until(async () => /4 × 3 px/.test(await page.$eval(M + " .gk-files-props", (n) => n.textContent))));
    // Rename.
    await page.fill(M + ' .gk-files-rename input[name="name"]', "Desk at home.png");
    await page.click(M + " .gk-files-rename button");
    check("rename posts and redraws", await until(async () => (await page.$eval(M + ' [data-gk-file="d"] .gk-files-name', (n) => n.textContent)) === "Desk at home.png"));
    const ra = actions[actions.length - 1];
    check("rename posts action, id, name, token, hidden fields and folder",
      ra.fields.action[0] === "rename" && ra.fields["ids[]"][0] === "d" && ra.fields.name[0] === "Desk at home.png" && ra.fields._token[0] === "tok-files"
      && ra.fields.tenant[0] === "7" && ra.fields.folder[0] === "", JSON.stringify(ra.fields));
    check("…with the headers", ra.headers["x-csrf-token"] === "tok-files" && ra.headers["x-requested-with"] === "XMLHttpRequest" && /json/.test(ra.headers.accept));
    check("the details show the new name", await page.$eval(M + " .gk-files-details-title", (n) => n.textContent) === "Desk at home.png");
    await page.click(M + " .gk-files-details-close");
    check("the close button closes", await page.$eval(M + " .gk-files-details", (n) => n.hidden));

    // ── Delete, refused and done ──
    await page.evaluate(() => GK.files.select("media", ["b", "c"]));
    actRefuse = true;
    await page.click(M + ' [data-gk-files-action="delete"]');
    check("Delete asks first", await until(async () => !!(await page.$(".gk-confirm-overlay .gk-confirm-ok"))));
    const confirmBtn = await page.$(".gk-confirm-overlay .gk-confirm-ok");
    if (confirmBtn) await confirmBtn.click();
    check("a refusal leaves the files and says why", await waitLive(page, /Kept for ten years/) && (await shown(page)).includes("c"));
    actRefuse = false;
    await page.click(M + ' [data-gk-files-action="delete"]');
    await until(async () => !!(await page.$(".gk-confirm-overlay .gk-confirm-ok")));
    const ok2 = await page.$(".gk-confirm-overlay .gk-confirm-ok");
    if (ok2) await ok2.click();
    check("deleted files leave the listing", await until(async () => !(await shown(page)).includes("b") && !(await shown(page)).includes("c")));
    check("…said in words", await waitLive(page, /2 files deleted/));
    const da = actions[actions.length - 1];
    check("delete posts the ticked ids", da.fields.action[0] === "delete" && JSON.stringify(da.fields["ids[]"]) === '["b","c"]', JSON.stringify(da.fields));
    // Move.
    await page.evaluate(() => GK.files.select("media", ["e"]));
    await page.selectOption(M + " [data-gk-files-target]", "m");
    await page.click(M + ' [data-gk-files-action="move"]');
    check("a moved file leaves the folder", await until(async () => !(await shown(page)).includes("e")));
    check("move posts the target", actions[actions.length - 1].fields.target[0] === "m");
    // An action left to the page, and a veto.
    await page.evaluate(() => GK.files.select("media", ["a"]));
    const before = actions.length;
    await page.click(M + ' [data-gk-files-action="download"]');
    check("an event-only action fires and posts nothing", (await events(page, "gk:filesaction")).pop().detail.action === "download" && actions.length === before);
    await page.evaluate(() => document.addEventListener("gk:filesaction", (e) => { if (e.detail.action === "move") e.preventDefault(); }, { once: true }));
    await page.click(M + ' [data-gk-files-action="move"]');
    await sleep(100);
    check("preventDefault() on gk:filesaction stops the post", actions.length === before && (await shown(page)).includes("a"));
    await page.evaluate(() => GK.files.select("media", ["a"], false));
    // New folder.
    await page.click(M + " .gk-files-newfolder summary");
    await page.fill(M + ' .gk-files-newfolder input[name="name"]', "Press");
    await page.press(M + ' .gk-files-newfolder input[name="name"]', "Enter");
    check("a new folder appears", await until(async () => !!(await page.$(M + ' [data-gk-folder="nf1"]'))));
    check("…posts its name and parent", actions[actions.length - 1].fields.action[0] === "folder" && actions[actions.length - 1].fields.name[0] === "Press" && actions[actions.length - 1].fields.parent[0] === "");
    check("…and is a target to move to", await page.$eval(M + ' [data-gk-files-target] option[value="nf1"]', (o) => o.textContent === "Press").catch(() => false));
    // Delete by key.
    await page.focus(M + ' [data-gk-file="a"] .gk-files-check');
    await page.keyboard.press("Space");
    await page.keyboard.press("Delete");
    check("Delete on the keyboard asks too", await until(async () => !!(await page.$(".gk-confirm-overlay .gk-confirm-ok"))));
    await page.keyboard.press("Escape");
    await sleep(150);
    check("…and a no keeps the file", (await shown(page)).includes("a"));
    await page.evaluate(() => GK.files.select("media", ["a"], false));
    await ctx.close();
  }

  // ── 2. Upload ─────────────────────────────────────────────────────────────
  {
    const { ctx, page } = await open();
    uploads.length = 0;
    const input = M + ' input[type="file"]';
    await page.setInputFiles(input, [fileOf("one.png", "image/png"), fileOf("two.pdf", "application/pdf", Buffer.from("%PDF-1.4\n%%EOF"))]);
    check("two files, two requests", await until(async () => uploads.length === 2));
    const up = uploads[0];
    check("each request: files[] with one file, the token, the hidden field, the folder",
      up.fields["files[]"].length === 1 && up.fields._token[0] === "tok-files" && up.fields.tenant[0] === "7" && up.fields.folder[0] === "", JSON.stringify(up.fields));
    check("…and the headers", up.headers["x-csrf-token"] === "tok-files" && up.headers["x-requested-with"] === "XMLHttpRequest");
    check("the new files appear without a reload", await until(async () => (await shown(page)).includes("up1") && (await shown(page)).includes("up2")));
    check("a picture with its picture, a PDF with its symbol", await page.$eval(M + ' [data-gk-file="up1"] img', (i) => i.src.endsWith("/img/up1.png")) && await page.$eval(M + ' [data-gk-file="up2"] .gk-files-thumb', (t) => t.textContent.includes("PDF")));
    check("the rows say uploaded — symbol and word", await page.$$eval(M + " .gk-files-job", (js) => js.length === 2 && js.every((j) => j.dataset.state === "done" && /check_circle\s*Uploaded/.test(j.querySelector(".gk-files-job-state").textContent))));
    check("a finished bar is full", await page.$$eval(M + " .gk-files-progress", (ps) => ps.every((p) => p.value === 100)));
    check("the live region ends with the count", await waitLive(page, /2 of 2 files uploaded/));
    check("gk:filesuploaded for each", (await events(page, "gk:filesuploaded")).length === 2);
    check("object URLs are given back", await until(async () => await page.evaluate(() => window.__made > 0 && window.__made === window.__revoked)), await page.evaluate(() => window.__made + "/" + window.__revoked));
    check("a new item is drawn like the server's", await page.evaluate(() => {
      const a = document.querySelector('[data-gk-files="media"] [data-gk-file="a"]'), b = document.querySelector('[data-gk-files="media"] [data-gk-file="up1"]');
      const shape = (n) => [...n.children].map((c) => c.tagName + "." + c.className).join(" ");
      return shape(a) === shape(b);
    }));
    check("an uploaded file can be ticked and opened", await page.evaluate(() => { GK.files.select("media", ["up1"]); GK.files.open("media", "up1");
      return GK.files.selected("media")[0] === "up1" && document.querySelector('[data-gk-files="media"] .gk-files-details-title').textContent === "one.png"; }));
    await page.evaluate(() => { GK.files.close("media"); GK.files.select("media", ["up1"], false); });

    // Checks in the browser.
    uploads.length = 0;
    await page.setInputFiles(input, [fileOf("notes.txt", "text/plain", Buffer.from("hello")), fileOf("huge.png", "image/png", Buffer.alloc(70 * 1024, 1)),
      fileOf("empty.png", "image/png", Buffer.alloc(0)), fileOf("logo.svg", "image/svg+xml", Buffer.from("<svg/>"))]);
    await sleep(200);
    const rows = await page.$$eval(M + " .gk-files-job[data-state=error] .gk-files-job-state", (ns) => ns.map((n) => n.textContent));
    check("refused here: a type, a size, an empty file, an SVG for image/*", rows.length === 4 && uploads.length === 0, JSON.stringify(rows));
    check("…each with its reason", /notes\.txt is a type of file that cannot be uploaded here/.test(rows[0]) && /huge\.png is too large \(70 KB\); at most 64 KB per file/.test(rows[1])
      && /empty\.png is empty/.test(rows[2]) && /logo\.svg is a type/.test(rows[3]), JSON.stringify(rows));
    check("…said in the live region", await waitLive(page, /notes\.txt is a type/));
    check("a refused row has a way to remove it, not to retry", await page.$$eval(M + " .gk-files-job[data-state=error]", (js) => js.every((j) => !j.querySelector('[data-gk-job-act="retry"]') && j.querySelector('[data-gk-job-act="dismiss"]'))));
    check("gk:fileserror for each", (await events(page, "gk:fileserror")).length === 4);
    for (const b of await page.$$(M + ' [data-gk-job-act="dismiss"]')) await b.click();
    check("dismissed rows go, the empty queue hides", await page.$eval(M + " .gk-files-queue", (q) => q.hidden && q.children.length === 0));

    // Count, parallel, progress, cancel, retry.
    ["h1.png", "h2.png", "h3.png"].forEach(holdFor);
    await page.setInputFiles(input, ["h1.png", "h2.png", "h3.png", "h4.png", "h5.png"].map((n) => fileOf(n, "image/png")));
    await until(async () => uploads.length >= 2);
    await sleep(150);
    check("at most four at once: the fifth is refused", await page.$eval(M + ' .gk-files-job:last-child .gk-files-job-state', (n) => /Too many files: at most 4/.test(n.textContent)));
    check("two at the same time", uploads.length === 2, uploads.length);
    const prog = await page.$$eval(M + " .gk-files-job", (js) => js.slice(0, 4).map((j) => [j.dataset.state, j.querySelector("progress").value, j.querySelector(".gk-files-job-state").textContent]));
    check("sending shows its bar and percent; the rest waits", prog[0][0] === "uploading" && prog[0][1] === 99 && /Uploading, 99 %/.test(prog[0][2]) && prog[2][0] === "waiting" && /Waiting/.test(prog[2][2]), JSON.stringify(prog));
    check("the bar is named by the file", await page.$eval(M + " .gk-files-job progress", (p) => p.getAttribute("aria-label") === "h1.png"));
    await page.click(M + ' .gk-files-job:nth-child(2) [data-gk-job-act="cancel"]');
    check("cancel stops a file being sent", await until(async () => (await page.$eval(M + " .gk-files-job:nth-child(2)", (j) => j.dataset.state)) === "cancelled"));
    check("…and the next one starts", await until(async () => uploads.length === 3));
    await page.click(M + ' .gk-files-job:nth-child(4) [data-gk-job-act="cancel"]');
    check("cancel a waiting file", await page.$eval(M + " .gk-files-job:nth-child(4)", (j) => j.dataset.state === "cancelled" && /block\s*Cancelled/.test(j.textContent)));
    release["h1.png"](); release["h3.png"]();
    check("held files finish", await until(async () => (await page.$$eval(M + ' .gk-files-job[data-state="done"]', (n) => n.length)) === 2));
    check("the count: 2 of 2", await waitLive(page, /2 of 2 files uploaded/));
    delete hold["h2.png"];
    await page.click(M + ' .gk-files-job:nth-child(2) [data-gk-job-act="retry"]');
    check("a cancelled file can be tried again", await until(async () => (await page.$eval(M + " .gk-files-job:nth-child(2)", (j) => j.dataset.state)) === "done"));
    for (const b of await page.$$(M + ' [data-gk-job-act="dismiss"]')) await b.click();

    // The server: refusal, 500 and retry, junk, a hostile name.
    failOnce = { "boom.png": 1 };
    await page.setInputFiles(input, [fileOf("refuse.png", "image/png"), fileOf("boom.png", "image/png"), fileOf("junk.png", "image/png"), fileOf("evil.png", "image/png")]);
    await until(async () => (await page.$$eval(M + ' .gk-files-job:not([data-state="waiting"]):not([data-state="uploading"])', (n) => n.length)) === 4);
    const srv = await page.$$eval(M + " .gk-files-job", (js) => js.map((j) => [j.dataset.state, j.querySelector(".gk-files-job-state").textContent, !!j.querySelector('[data-gk-job-act="retry"]')]));
    check("the server's refusal is shown, with no retry", srv[0][0] === "error" && /The server refused refuse\.png\./.test(srv[0][1]) && !srv[0][2], JSON.stringify(srv[0]));
    check("a 500 is said with its status and can be retried", srv[1][0] === "error" && /HTTP 500/.test(srv[1][1]) && srv[1][2], JSON.stringify(srv[1]));
    check("an answer that is no answer is said", srv[2][0] === "error" && /not one an upload expects/.test(srv[2][1]), JSON.stringify(srv[2]));
    check("a hostile name from the server is text", srv[3][0] === "done" && await page.evaluate(() => !window.__pwned) && await page.$eval(M + ' [data-gk-file="up' + (fileSeq) + '"] .gk-files-name', (n) => n.textContent.startsWith("<img")));
    await page.click(M + ' .gk-files-job:nth-child(2) [data-gk-job-act="retry"]');
    check("retry after a 500 succeeds", await until(async () => (await page.$eval(M + " .gk-files-job:nth-child(2)", (j) => j.dataset.state)) === "done"));
    check("the focus stays on the row's button", await page.evaluate(() => document.activeElement.matches('.gk-files-job:nth-child(2) [data-gk-job-act="dismiss"]')));
    for (const b of await page.$$(M + ' [data-gk-job-act="dismiss"]')) await b.click();

    // Drop and paste.
    uploads.length = 0;
    const dropped = await page.evaluate(() => {
      const root = document.querySelector('[data-gk-files="media"]');
      const dt = new DataTransfer();
      dt.items.add(new File([new Uint8Array([137, 80, 78, 71])], "dropped.png", { type: "image/png" }));
      root.querySelector(".gk-files-items").dispatchEvent(new DragEvent("dragenter", { bubbles: true, cancelable: true, dataTransfer: dt }));
      const over = !root.querySelector(".gk-files-items").dispatchEvent(new DragEvent("dragover", { bubbles: true, cancelable: true, dataTransfer: dt }));
      const during = { cls: root.classList.contains("gk-files-dragover"), text: root.querySelector(".gk-files-drop-text").textContent };
      root.querySelector(".gk-files-items").dispatchEvent(new DragEvent("drop", { bubbles: true, cancelable: true, dataTransfer: dt }));
      return { over, during, after: root.classList.contains("gk-files-dragover"), text: root.querySelector(".gk-files-drop-text").textContent };
    });
    check("a file over the manager frames it and says 'Drop to upload'", dropped.over && dropped.during.cls && dropped.during.text === "Drop to upload", JSON.stringify(dropped));
    check("dropped: the frame goes, the words come back", !dropped.after && /^Drop files here/.test(dropped.text), JSON.stringify(dropped));
    check("a dropped file is uploaded", await until(async () => uploads.some((u) => u.name === "dropped.png")));
    await page.focus(M + ' [data-gk-file="a"] .gk-files-check');
    await page.evaluate(() => {
      const dt = new DataTransfer();
      dt.items.add(new File([new Uint8Array([137, 80, 78, 71])], "image.png", { type: "image/png" }));
      document.activeElement.dispatchEvent(new ClipboardEvent("paste", { bubbles: true, cancelable: true, clipboardData: dt }));
    });
    check("a pasted picture is uploaded under a name of its own", await until(async () => uploads.some((u) => /^Pasted picture \d{8}-\d{6}\.png$/.test(u.name))), JSON.stringify(uploads.map((u) => u.name)));
    await page.focus(M + " [data-gk-files-q]");
    const n0 = uploads.length;
    await page.evaluate(() => {
      const dt = new DataTransfer();
      dt.items.add(new File([new Uint8Array([1])], "image.png", { type: "image/png" }));
      document.activeElement.dispatchEvent(new ClipboardEvent("paste", { bubbles: true, cancelable: true, clipboardData: dt }));
    });
    await sleep(150);
    check("pasting into the search field is left to the field", uploads.length === n0);
    // A veto and the API.
    await page.evaluate(() => document.addEventListener("gk:filesupload", (e) => { if (e.detail.name === "veto.png") e.preventDefault(); }));
    await page.evaluate(() => GK.files.upload("media", [new File([new Uint8Array([1, 2])], "veto.png", { type: "image/png" })]));
    await sleep(150);
    check("gk:filesupload can stop a file", !uploads.some((u) => u.name === "veto.png") && await page.$eval(M + " .gk-files-job:last-child", (j) => j.dataset.state === "cancelled"));
    const api = await page.evaluate(() => {
      const f = GK.files.add("media", { id: 77, name: "Api.png", size: 5, mime: "image/png", url: "javascript:alert(1)" });
      const out = { added: !!document.querySelector('[data-gk-files="media"] [data-gk-file="77"]'), url: f.url };
      GK.files.remove("media", [77]);
      out.removed = !document.querySelector('[data-gk-files="media"] [data-gk-file="77"]');
      out.view = GK.files.view("media", "list");
      out.cls = document.querySelector('[data-gk-files="media"]').classList.contains("gk-files-view-list");
      GK.files.view("media", "grid");
      out.pick = GK.files.selected("pick");
      out.single = GK.files.select("pick", ["r1"]);
      return out;
    });
    check("GK.files: add (an unsafe URL dropped), remove, view", api.added && api.url === null && api.removed && api.view === "list" && api.cls, JSON.stringify(api));
    check("GK.files on a single manager: one at a time", JSON.stringify(api.pick) === '["r2"]' && JSON.stringify(api.single) === '["r1"]', JSON.stringify(api));
    await ctx.close();
  }

  // ── 3. A phone ────────────────────────────────────────────────────────────
  {
    const { ctx, page } = await open({ viewport: { width: 390, height: 844 }, touch: true });
    const m = await page.evaluate(() => {
      const r = document.querySelector('[data-gk-files="media"]');
      const tiles = [...r.querySelectorAll(".gk-files-items > .gk-files-item:not([hidden])")];
      return { scroll: document.documentElement.scrollWidth - innerWidth, cols: new Set(tiles.map((t) => Math.round(t.getBoundingClientRect().left))).size,
        fieldFont: getComputedStyle(r.querySelector("[data-gk-files-q]")).fontSize };
    });
    check("390px: no sideways scroll", m.scroll <= 0, m.scroll);
    check("390px: two columns of tiles", m.cols === 2, m.cols);
    await page.setViewportSize({ width: 580, height: 844 });
    const wide = await page.$$eval(M + " .gk-files-items > .gk-files-item:not([hidden])", (ts) => new Set(ts.map((t) => Math.round(t.getBoundingClientRect().left))).size);
    check("580px: still two columns, wider tiles", wide === 2, wide);
    await page.setViewportSize({ width: 390, height: 844 });
    check("390px: fields at 16px", m.fieldFont === "16px", m.fieldFont);
    const small = await page.evaluate(() => {
      const r = document.querySelector('[data-gk-files="media"]');
      return [...r.querySelectorAll(".gk-files-name, .gk-files-viewbtn, .gk-files-pick, .gk-files-bulk .gk-btn, .gk-files-all, .gk-files-newfolder > summary, [data-gk-files-q], .gk-files-field select")]
        .filter((n) => n.offsetParent !== null).map((n) => [n.className || n.tagName, Math.round(n.getBoundingClientRect().height)]).filter((x) => x[1] < 44);
    });
    check("390px: every target at least 44px high", small.length === 0, JSON.stringify(small));
    await page.tap(M + ' [data-gk-file="a"] .gk-files-name');
    const sheet = await page.$eval(M + " .gk-files-details", (d) => { const b = d.getBoundingClientRect();
      return { pos: getComputedStyle(d).position, w: Math.round(b.width), h: Math.round(b.height), role: d.getAttribute("role"), modal: d.getAttribute("aria-modal"),
        lock: document.documentElement.classList.contains("gk-files-sheet-open"), close: Math.round(d.querySelector(".gk-files-details-close").getBoundingClientRect().width) }; });
    check("390px: the details are a sheet over the whole screen", sheet.pos === "fixed" && sheet.w === 390 && sheet.h === 844 && sheet.role === "dialog" && sheet.modal === "true" && sheet.lock, JSON.stringify(sheet));
    check("390px: its close button is 44px", sheet.close >= 44, sheet.close);
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, "files-390-details.png") });
    const inside = [];
    for (let i = 0; i < 8; i++) { await page.keyboard.press("Tab"); inside.push(await page.evaluate(() => !!document.activeElement.closest(".gk-files-details"))); }
    check("390px: Tab stays inside the sheet", inside.every(Boolean), JSON.stringify(inside));
    await page.keyboard.press("Escape");
    check("390px: Escape closes the sheet", await page.$eval(M + " .gk-files-details", (d) => d.hidden && !document.documentElement.classList.contains("gk-files-sheet-open")));
    await page.setInputFiles(M + ' input[type="file"]', [fileOf("a-rather-long-file-name-for-a-small-screen-2026.png", "image/png")]);
    await until(async () => !!(await page.$(M + ' .gk-files-job[data-state="done"]')));
    check("390px: the queue fits", await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    check("390px: the queue's buttons are 44px", await page.$eval(M + " .gk-files-job-btn", (b) => b.getBoundingClientRect().width >= 44 && b.getBoundingClientRect().height >= 44));
    await page.click(M + ' .gk-files-viewbtn:has(input[value="list"])');
    check("390px: the list fits too", await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await page.click(M + ' .gk-files-viewbtn:has(input[value="grid"])');
    if (SHOTS) { await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: path.join(SHOTS, "files-390.png"), fullPage: false }); }
    await ctx.close();
  }

  // ── 4. Contrast: light, both dark spellings, six themes ──────────────────
  {
    const { ctx, page } = await open();
    await page.setInputFiles(M + ' input[type="file"]', [fileOf("ok.png", "image/png"), fileOf("refuse.png", "image/png")]);
    await until(async () => (await page.$$eval(M + ' .gk-files-job[data-state="done"], ' + M + ' .gk-files-job[data-state="error"]', (n) => n.length)) === 2);
    await page.evaluate(() => { GK.files.select("media", ["a"]); GK.files.open("media", "c"); });
    const looks = [];
    for (const theme of ["indigo", "ocean", "forest", "rose", "amber", "slate"]) {
      for (const mode of ["light", "dark", "gk-dark"]) looks.push([theme, mode]);
    }
    const low = [];
    for (const [theme, mode] of looks) {
      await page.evaluate(([theme, mode]) => {
        const b = document.body;
        b.setAttribute("data-gk-theme", theme);
        b.classList.remove("gk-dark");
        if (mode === "gk-dark") { b.removeAttribute("data-gk-mode"); b.classList.add("gk-dark"); } else b.setAttribute("data-gk-mode", mode);
      }, [theme, mode]);
      await sleep(260);   // the tiles change colour with a transition
      const r = await page.evaluate(PIXEL + "(" + (() => {
        const root = document.querySelector('[data-gk-files="media"]');
        const out = [];
        const sel = [".gk-files-name", ".gk-files-size", ".gk-files-date", ".gk-files-summary", ".gk-files-count", ".gk-files-crumbs [aria-current]", ".gk-files-drop-text",
          ".gk-files-hint", ".gk-files-job-name", '.gk-files-job[data-state="done"] .gk-files-job-state', '.gk-files-job[data-state="error"] .gk-files-job-state',
          ".gk-files-details-title", ".gk-files-props dt", ".gk-files-props dd", ".gk-files-selected .gk-files-name", ".gk-files-field > span", ".gk-files-viewbtn",
          ".gk-files-ext", ".gk-files-meta"];
        for (const s of sel) {
          const n = [...root.querySelectorAll(s)].find((x) => x.offsetParent !== null && getComputedStyle(x).display !== "none");
          if (!n) { out.push([s, -1]); continue; }
          out.push([s, Math.round(textOn(n) * 100) / 100]);
        }
        // The progress bar and the frames of the fields: 3:1 against their ground.
        const bar = root.querySelector(".gk-files-search input");
        out.push(["field border", Math.round(ratio(px(getComputedStyle(bar).borderTopColor, ground(bar.parentElement)), ground(bar.parentElement)) * 100) / 100]);
        return out;
      }).toString() + ")()");
      for (const [s, v] of r) {
        if (v === -1) low.push(`${theme}/${mode} ${s} not shown`);
        else if (s === "field border" ? v < 3 : v < 4.5) low.push(`${theme}/${mode} ${s} ${v}:1`);
      }
    }
    check("contrast in 18 looks: text 4.5:1, a field's frame 3:1", low.length === 0, low.join("; "));
    if (SHOTS) {
      await page.evaluate(() => { document.body.setAttribute("data-gk-theme", "indigo"); document.body.classList.remove("gk-dark"); document.body.setAttribute("data-gk-mode", "light"); window.scrollTo(0, 0); });
      await sleep(300);
      await page.locator(M).screenshot({ path: path.join(SHOTS, "files-1280-light.png") });
      await page.evaluate(() => document.body.setAttribute("data-gk-mode", "dark"));
      await sleep(300);
      await page.locator(M).screenshot({ path: path.join(SHOTS, "files-1280-dark.png") });
      await page.evaluate(() => { document.body.setAttribute("data-gk-mode", "light"); GK.files.view("media", "list"); });
      await sleep(300);
      await page.locator(M).screenshot({ path: path.join(SHOTS, "files-1280-list.png") });
    }
    await ctx.close();
  }

  // ── 5. Without script ─────────────────────────────────────────────────────
  {
    const { ctx, page } = await open({ js: false, path: "/nojs.html" });
    const st = await page.$eval(M, (r) => ({
      apply: getComputedStyle(r.querySelector(".gk-files-apply")).display !== "none",
      send: getComputedStyle(r.querySelector(".gk-files-send")).display !== "none",
      input: getComputedStyle(r.querySelector(".gk-files-pick input")).position !== "absolute",
      queue: getComputedStyle(r.querySelector(".gk-files-queue")).display === "none",
      all: getComputedStyle(r.querySelector(".gk-files-all")).display === "none",
      dl: getComputedStyle(r.querySelector('[data-gk-files-action="download"]')).display === "none",
      boxes: r.querySelectorAll(".gk-files-check").length,
    }));
    check("without script: Apply, Upload and the browser's file field are there", st.apply && st.send && st.input, JSON.stringify(st));
    check("without script: what needs the script is hidden", st.queue && st.all && st.dl, JSON.stringify(st));
    check("without script: a real checkbox per file", st.boxes === 6, st.boxes);
    check("without script: no sideways scroll", await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    uploads.length = 0;
    await page.setInputFiles(M + ' input[type="file"]', [fileOf("plain1.png", "image/png"), fileOf("plain2.png", "image/png")]);
    await Promise.all([page.waitForNavigation(), page.click(M + " .gk-files-send")]);
    check("the upload form posts every file as files[] at once", uploads.length === 1 && uploads[0].fields["files[]"].length === 2 && uploads[0].fields._token[0] === "tok-files", JSON.stringify(uploads.map((u) => u.fields)));
    check("…multipart, without the script's headers", /multipart\/form-data/.test(uploads[0].headers["content-type"]) && !uploads[0].headers["x-requested-with"]);
    await page.goto(BASE + "/nojs.html");
    await page.fill(M + " [data-gk-files-q]", "lake");
    await Promise.all([page.waitForNavigation(), page.click(M + " .gk-files-apply")]);
    check("the toolbar is a GET form", new URL(page.url()).searchParams.get("q") === "lake" && new URL(page.url()).pathname === "/nojs.html", page.url());
    check("…and the server draws what it asked for", JSON.stringify(await shown(page)) === '["a"]', JSON.stringify(await shown(page)));
    await page.goto(BASE + "/nojs.html");
    await Promise.all([page.waitForNavigation(), page.click(M + ' [data-gk-folder="r"] .gk-files-name')]);
    check("a folder is a link", new URL(page.url()).searchParams.get("folder") === "r" && JSON.stringify(await shown(page)) === '["r1","r2"]', page.url());
    check("…and the breadcrumb leads back", await page.$eval(M + " .gk-files-crumbs a", (a) => a.getAttribute("href") === "?"));
    await Promise.all([page.waitForNavigation(), page.click(M + ' [data-gk-file="r1"] .gk-files-name')]);
    check("a name links to its details, drawn by the server", await page.$eval(M + " .gk-files-details", (d) => !d.hidden && d.querySelector(".gk-files-details-title").textContent === "Receipt 2026-10-02.pdf"));
    actions.length = 0;
    await page.check(M + ' [data-gk-file="r2"] .gk-files-check');
    await Promise.all([page.waitForNavigation(), page.click(M + ' [data-gk-files-action="delete"]')]);
    check("Delete posts the form to its URL", actions.length === 1 && actions[0].fields.action[0] === "delete" && JSON.stringify(actions[0].fields["ids[]"]) === '["r2"]' && actions[0].fields.folder[0] === "r", JSON.stringify(actions[0] && actions[0].fields));
    await ctx.close();
  }

  await browser.close();
  check("no script error on any page", pageErrors.length === 0, pageErrors.join(" | "));

  const failed = results.filter((r) => !r.ok);
  for (const r of results) if (!r.ok) console.log("FAIL  " + r.name + (r.detail ? "\n        " + r.detail : ""));
  console.log(`${results.length - failed.length}/${results.length} cases hold`);
  process.exit(failed.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
