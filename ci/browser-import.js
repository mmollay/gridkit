#!/usr/bin/env node
/**
 * The CSV import (GridKit\Import + js/import.js, 1.119.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: what Import.php reads, checks and writes is held there
 * (tests/import.test.php). Here the point is that the browser arrives at the very same: 196 files
 * (written by hand for each rule, and 160 drawn at random — every separator, quoting, line ending,
 * encoding and value shape) are read by js/import.js and compared with what Import::parseCsv(),
 * result() and the panel of Import.php make of them — the rows, every message, the counts, the
 * markup. Then the flow: a file chosen, dropped and pasted; the mapping changed (a field taken from
 * another column, the summary spoken in place); the separator, the encoding and the header row
 * changed; a refused file; Cancel; a step 2 drawn by the server taken over; Import, after which
 * the server — reading the file and the mapping again — hands over the rows the browser showed.
 * The same flow without script, as a POST in three steps, the file kept under a token or carried
 * in the form. Then 390px without a sideways scroll, contrast in light, data-gk-mode="dark" and
 * .gk-dark under no theme and the six, Chrome's accessibility tree, and screenshots.
 *
 * The page is ci/browser-import-fixture.php behind PHP's own server, which this script starts on a
 * free port (GK_URL=http://127.0.0.1:8140/ uses one already running it instead:
 * php -S 127.0.0.1:8140 ci/browser-import-fixture.php).
 *
 *     node ci/browser-import.js                     # needs the "playwright" package, and php
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-import.js
 *     GK_SHOTS=/some/dir GK_ICONFONT=/path/material-icons.woff2 node ci/browser-import.js   # screenshots
 *     GK_VERBOSE=1 node ci/browser-import.js        # prints each case as it runs (stderr)
 *     GK_PARTS=corpus,js node ci/browser-import.js  # only some parts: corpus, js, nojs, phone, look
 *
 * Exit code 0 when every case holds.
 */
const { spawn } = require("child_process");
const fs = require("fs");
const net = require("net");
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
const SHOTS = process.env.GK_SHOTS || "";
const FONT = process.env.GK_ICONFONT || "";
const root = path.join(__dirname, "..");
const store = fs.mkdtempSync(path.join(os.tmpdir(), "gk-import-store-"));
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), "gk-import-files-"));
let server = null;
process.on("exit", () => {
  if (server) server.kill();
  fs.rmSync(store, { recursive: true, force: true });
  fs.rmSync(tmp, { recursive: true, force: true });
});
process.on("SIGINT", () => process.exit(130));

const THEMES = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
const MODES = ["light", "dark", "class"];
const sample = fs.readFileSync(path.join(root, "demo/media/kunden.csv"));
const PARTS = (process.env.GK_PARTS || "corpus,js,nojs,phone,look").split(",");
const part = (name) => PARTS.indexOf(name) >= 0;

function freePort() {
  return new Promise((resolve) => {
    const s = net.createServer();
    s.listen(0, "127.0.0.1", () => { const p = s.address().port; s.close(() => resolve(p)); });
  });
}

async function startServer() {
  if (process.env.GK_URL) return process.env.GK_URL.replace(/\/?$/, "/");
  const port = await freePort();
  server = spawn(php, ["-S", "127.0.0.1:" + port, path.join(__dirname, "browser-import-fixture.php")],
    { stdio: "ignore", env: Object.assign({}, process.env, { GK_IMPORT_STORE: store }) });
  const url = "http://127.0.0.1:" + port + "/";
  for (let i = 0; i < 50; i++) {
    try { const r = await fetch(url + "sample.csv"); if (r.ok) return url; } catch (e) { /* not yet */ }
    await new Promise((r) => setTimeout(r, 100));
  }
  throw new Error("the fixture server did not start");
}

function file(name, bytes) {
  const p = path.join(tmp, name);
  fs.writeFileSync(p, bytes);
  return p;
}

/** Deep equality, the way JSON sees it. */
function same(a, b) {
  return JSON.stringify(sort(a)) === JSON.stringify(sort(b));
}
function sort(v) {
  if (Array.isArray(v)) return v.map(sort);
  if (v && typeof v === "object") return Object.keys(v).sort().reduce((o, k) => { o[k] = sort(v[k]); return o; }, {});
  return v;
}
function firstDiff(a, b, at) {
  at = at || "";
  if (same(a, b)) return null;
  if (a && b && typeof a === "object" && typeof b === "object") {
    const keys = Array.from(new Set(Object.keys(a).concat(Object.keys(b))));
    for (const k of keys) { const d = firstDiff(a[k], b[k], at + "." + k); if (d) return d; }
  }
  return at + ": " + String(JSON.stringify(a)).slice(0, 160) + " ≠ " + String(JSON.stringify(b)).slice(0, 160);
}

/** Contrast as the page paints it (a canvas reads any CSS colour the browser knows). */
const PIXEL = `
  const cv = document.createElement("canvas"); cv.width = cv.height = 1;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  const px = (css, under) => { ctx.clearRect(0, 0, 1, 1); if (under) { ctx.fillStyle = "rgb(" + under.join(",") + ")"; ctx.fillRect(0, 0, 1, 1); }
    ctx.fillStyle = css; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
  const lum = (c) => { const f = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const ground = (el) => { const chain = []; for (let e = el; e; e = e.parentElement) chain.unshift(e);
    let c = [255, 255, 255]; chain.forEach((e) => { c = px(getComputedStyle(e).backgroundColor, c); }); return c; };
  const textOn = (el, pseudo) => { const g = ground(el); const s = getComputedStyle(el, pseudo || null);
    const bg = pseudo ? px(s.backgroundColor, g) : g; return ratio(px(s.color, bg), bg); };
`;

(async () => {
  const url = await startServer();
  const corpus = await (await fetch(url + "corpus.json")).json();
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };
  const font = FONT && fs.existsSync(FONT) ? fs.readFileSync(FONT).toString("base64") : "";
  const open = async (opts) => {
    const context = await browser.newContext(Object.assign({ viewport: { width: 1280, height: 900 } }, opts || {}));
    // The icon font is not shipped; with GK_ICONFONT it is served in place of Google's.
    await context.route(/fonts\.(googleapis|gstatic)\.com/, (r) => r.fulfill({ status: 200, body: "" }));
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on("pageerror", (e) => pageErrors.push(e.message));
    return { context, page };
  };
  const addFont = async (page) => {
    if (!font) return;
    await page.addStyleTag({ content: `@font-face{font-family:"Material Icons";src:url(data:font/woff2;base64,${font}) format("woff2")}`
      + `.material-icons{font-family:"Material Icons";font-weight:400;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;direction:ltr;font-feature-settings:"liga"}` });
  };
  const arm = (page) => page.evaluate(() => {
    window._ev = [];
    ["gk:importread", "gk:importmap", "gk:importready", "gk:importerror"].forEach((n) =>
      document.addEventListener(n, (e) => window._ev.push(Object.assign({ type: n, form: e.target.id }, JSON.parse(JSON.stringify(Object.assign({}, e.detail, { import: undefined })))))));
  });
  const events = (page) => page.evaluate(() => window._ev.splice(0));
  const serverResult = (page) => page.evaluate(() => JSON.parse(document.getElementById("server-result").textContent));
  // The words of an element as they are read: an icon's ligature is not a word (it is aria-hidden).
  const text = (page, sel) => page.$eval(sel, (el) => {
    const c = el.cloneNode(true);
    c.querySelectorAll(".material-icons").forEach((i) => i.remove());
    return c.textContent.replace(/\s+/g, " ").trim();
  });
  const has = (page, sel) => page.$(sel).then((x) => !!x);
  const demo = corpus.find((c) => c.name === "demo sample");

  try {
    // ═══ 1. The corpus: the browser reads and checks as Import.php does ═══════════════════════
    if (part("corpus")) {
      const { context, page } = await open();
      await page.goto(url);
      const out = await page.evaluate(async (corpus) => {
        const cfg = JSON.parse(document.querySelector("#gk-import-cmp script[data-gk-import-config]").textContent);
        const bytes = (b64) => Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
        // The panel of each side, without what only one of them draws: the server's "Check again"
        // and the file it carries (storage(false)); Cancel is a request there and none here.
        const normal = (html) => {
          const t = document.createElement("template");
          t.innerHTML = html;
          t.content.querySelectorAll('button[value="map"], input[name="cmp[data]"], input[name="cmp[filename]"], button[value="cancel"], [data-gk-import-cancel]')
            .forEach((n) => n.remove());
          return t.innerHTML;
        };
        const res = [];
        for (const c of corpus) {
          const b = bytes(c.b64);
          const parsed = GK.import.parse(b, { maxRows: 300, maxBytes: 262144, names: c.names, t: cfg.t, thousands: cfg.thousands, decimal: cfg.decimal });
          const result = await GK.import.read("cmp", new File([b], c.file));
          const panel = document.querySelector("#gk-import-cmp .gk-import-panel");
          const err = document.querySelector("#gk-import-cmp [data-gk-import-error] .gk-message-content");
          res.push({ parsed, result, panel: panel ? normal(panel.outerHTML) : "", php: normal(c.panel), error: err ? err.textContent : null,
            step: document.getElementById("gk-import-cmp").getAttribute("data-gk-import-step") });
        }
        return res;
      }, corpus);
      let parsedOk = 0, resultOk = 0, panelOk = 0;
      corpus.forEach((c, n) => {
        const o = out[n];
        const d1 = firstDiff(o.parsed, c.parsed);
        if (!d1) parsedOk++; else check(`corpus ${c.name}: parse() = Import::parseCsv()`, false, d1);
        const d2 = firstDiff(o.result, c.result);
        if (!d2) resultOk++; else check(`corpus ${c.name}: result() = Import::result()`, false, d2);
        if (c.result.error !== null) {
          if (o.error === c.result.error && o.step === "upload" && o.panel === "") panelOk++;
          else check(`corpus ${c.name}: refused with the server's words`, false, JSON.stringify([o.error, o.step]));
        } else if (o.panel === o.php) panelOk++;
        else {
          let i = 0; while (i < o.panel.length && o.panel[i] === o.php[i]) i++;
          check(`corpus ${c.name}: the panel is Import.php's, byte for byte`, false, "at " + i + ": " + o.panel.slice(i - 60, i + 80) + "\n   php: " + o.php.slice(i - 60, i + 80));
        }
      });
      check(`corpus: ${parsedOk} of ${corpus.length} files read as Import::parseCsv() reads them`, parsedOk === corpus.length);
      check(`corpus: ${resultOk} of ${corpus.length} results — rows, messages, counts, mapping, notes, meta — as Import::result()`, resultOk === corpus.length);
      check(`corpus: ${panelOk} of ${corpus.length} panels as Import.php draws them (or the same refusal)`, panelOk === corpus.length);
      check("corpus: every rule is in it", ["Windows-1252", "UTF-16LE", "UTF-16BE", "UTF-8"].every((e) => corpus.some((c) => c.parsed.meta.encoding === e))
        && [";", ",", "\t", "|"].every((d) => corpus.some((c) => c.parsed.meta.delimiter === d))
        && ["CRLF", "LF", "CR"].every((l) => corpus.some((c) => c.parsed.meta.lineEnding === l))
        && corpus.some((c) => c.parsed.meta.enclosure === "'") && corpus.some((c) => c.parsed.meta.sep) && corpus.some((c) => c.parsed.meta.unclosed)
        && corpus.some((c) => !c.parsed.meta.header) && corpus.filter((c) => c.result.error).length >= 4
        && corpus.reduce((n, c) => n + c.result.total, 0) > 1500 && corpus.reduce((n, c) => n + c.result.invalid, 0) > 500);
      await context.close();
    }

    // ═══ 2. The flow with the script ═════════════════════════════════════════════════════════
    if (part("js")) {
      const { context, page } = await open();
      await page.goto(url);
      await addFont(page);
      await arm(page);
      // A file chosen: the panel at once, without a request.
      let requests = 0;
      page.on("request", (r) => { if (r.method() === "POST") requests++; });
      await page.setInputFiles("#gk-import-c-file", file("kunden.csv", sample));
      await page.waitForSelector("#gk-import-c .gk-import-panel");
      const r0 = demo.result;
      check("JS: a chosen file is read at once, no request", requests === 0);
      check("JS: step 2 — the form says so, the steps too", await page.$eval("#gk-import-c", (f) => f.getAttribute("data-gk-import-step")) === "map"
        && await page.$eval("#gk-import-c .gk-steps", (o) => o.children[1].getAttribute("aria-current") === "step" && o.children[0].classList.contains("gk-steps-done")
          && o.children[0].querySelector(".gk-sr-only").textContent === " (done)"));
      check("JS: the Check-file button is out of the way", await page.$eval("#gk-import-c [data-gk-import-start]", (d) => d.hidden));
      check("JS: the summary", await text(page, "#gk-import-c .gk-import-summary") === `${r0.total} rows, ${r0.invalid} with errors.`, await text(page, "#gk-import-c .gk-import-summary"));
      const r1 = await page.evaluate(() => GK.import.result("c"));
      check("JS: the result is the server's", same(r1, r0), firstDiff(r1, r0));
      let ev = await events(page);
      check("JS: gk:importread, gk:importmap (auto), gk:importready",
        ev.map((e) => e.type).join() === "gk:importread,gk:importmap,gk:importready" && ev[0].filename === "kunden.csv" && ev[0].meta.encoding === "Windows-1252"
        && ev[0].total === 20 && ev[1].source === "auto" && ev[2].result.valid === r0.valid, JSON.stringify(ev.map((e) => [e.type, e.source])));
      check("JS: six rows in the preview (preview(6))", (await page.$$("#gk-import-c .gk-import-rowno")).length === 6);

      // A field taken from another column: the first lets go, the summary changes in place.
      const summaryBefore = await page.$("#gk-import-c .gk-import-summary");
      await page.focus("#gk-import-c-map-8");
      await page.selectOption("#gk-import-c-map-8", "first");
      ev = await events(page);
      const mapping = await page.evaluate(() => GK.import.map("c"));
      check("JS: choosing First name for Notiz lets go of it in Vorname", mapping[8] === "first" && mapping[1] === null
        && await page.$eval("#gk-import-c-map-1", (s) => s.value === "" && s.closest(".gk-import-col").classList.contains("gk-import-ignored")), JSON.stringify(mapping));
      check("JS: gk:importmap says the person chose", ev[0] && ev[0].type === "gk:importmap" && ev[0].source === "user" && ev[1].type === "gk:importready");
      check("JS: the summary is the same element — a live region keeps speaking", await page.evaluate((el) => el.isConnected && el.getAttribute("role") === "status", summaryBefore));
      check("JS: the focus stays on the select", await page.evaluate(() => document.activeElement.id) === "gk-import-c-map-8");
      check("JS: the preview follows: Notiz feeds First name", await text(page, "#gk-import-c .gk-import-preview thead th:nth-child(11) .gk-import-target") === "→ First name");
      // A required field let go: the button goes off, the message names it.
      await page.selectOption("#gk-import-c-map-0", "");
      check("JS: a required field without a column — named, Import off",
        await text(page, "#gk-import-c .gk-import-missing") === "Required, but no column fills it: Customer no."
        && await page.$eval('#gk-import-c button[value="confirm"]', (b) => b.disabled));
      await page.evaluate(() => { const m = GK.import.map("c"); m[0] = "no"; m[1] = "first"; m[8] = null; GK.import.map("c", m); });
      check("JS: GK.import.map() sets it back, Import on again", !(await page.$eval('#gk-import-c button[value="confirm"]', (b) => b.disabled))
        && (await events(page)).some((e) => e.type === "gk:importmap" && e.source === "api"));

      // The separator, the encoding, the header row.
      await page.focus('#gk-import-c select[name="c[delimiter]"]');
      await page.selectOption('#gk-import-c select[name="c[delimiter]"]', ",");
      check("JS: another separator reads the file again; the focus stays on it", await page.evaluate(() => GK.import.result("c").meta.delimiter) === ","
        && (await page.$$("#gk-import-c .gk-import-col")).length !== 9 && await page.evaluate(() => document.activeElement.name) === "c[delimiter]");
      await page.selectOption('#gk-import-c select[name="c[delimiter]"]', ";");
      await page.selectOption("#gk-import-c-map-8", "first");
      await page.selectOption('#gk-import-c select[name="c[encoding]"]', "UTF-8");
      check("JS: UTF-8 for a Windows-1252 file is said, and the file is read as before",
        await text(page, '#gk-import-c .gk-import-panel > [role="alert"]') === "The file is not valid UTF-8. Choose Windows-1252."
        && await page.$eval('#gk-import-c select[name="c[encoding]"]', (s) => s.value) === "Windows-1252"
        && (await text(page, "#gk-import-c .gk-import-preview tbody tr:nth-child(2) td:nth-child(5)")) === "Weiß");
      check("JS: the same columns keep the person's mapping", await page.evaluate(() => GK.import.map("c")[8]) === "first"
        && (await events(page)).filter((e) => e.type === "gk:importmap").pop().source === "user");
      await page.selectOption("#gk-import-c-map-1", "first");
      await page.click("#gk-import-c label.gk-checkbox-wrap");
      check("JS: without a header row the columns are numbered and row 1 is data", await text(page, "#gk-import-c .gk-import-col-name") === "Column Column 1"
        && await text(page, "#gk-import-c .gk-import-rowno") === "1");
      await page.click("#gk-import-c label.gk-checkbox-wrap");

      // Import: the server reads the posted file and mapping again and hands over the same rows.
      await page.selectOption("#gk-import-c-map-8", "first");
      const before = await page.evaluate(() => GK.import.result("c"));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="confirm"]')]);
      const srv = await serverResult(page);
      check("JS → server: one POST, step done", srv.name === "c" && srv.step === "done", JSON.stringify([srv.name, srv.step]));
      check("JS → server: the rows the browser showed, read again on the server", same(srv.result.rows, before.rows) && same(srv.result.errors, before.errors)
        && same(srv.result.mapping, before.mapping), firstDiff(srv.result, before));
      check("JS → server: nothing kept on the server for it", fs.readdirSync(store).length === 0, fs.readdirSync(store).join());
      check("JS → server: the done step", await text(page, "#gk-import-c .gk-import-summary") === `${before.valid} rows imported; ${before.invalid} with errors left out.`);
      check("JS → server: the hidden field went with it", (await page.$eval('#gk-import-c input[name="_token"]', (i) => i.value)) === 'tok-<&>"');

      // Dropped on the box: the file field takes it, so the form posts it.
      await page.goto(url);
      await arm(page);
      const csv = "Nr;Nachname;E-Mail;Menge\r\nK1;Huber;a@b.at;1.500\r\nK2;Gruber;x;2\r\n";
      await page.evaluate((csv) => {
        const box = document.querySelector("#gk-import-s .gk-import-drop");
        const dt = new DataTransfer();
        dt.items.add(new File([csv], "drop.csv", { type: "text/csv" }));
        box.dispatchEvent(new DragEvent("dragenter", { dataTransfer: dt, bubbles: true, cancelable: true }));
        window._over = box.classList.contains("gk-import-dragover");
        box.dispatchEvent(new DragEvent("dragover", { dataTransfer: dt, bubbles: true, cancelable: true }));
        box.dispatchEvent(new DragEvent("drop", { dataTransfer: dt, bubbles: true, cancelable: true }));
      }, csv);
      await page.waitForSelector("#gk-import-s .gk-import-panel");
      check("JS: a file over the box marks it", await page.evaluate(() => window._over) && !(await page.$eval("#gk-import-s .gk-import-drop", (b) => b.classList.contains("gk-import-dragover"))));
      check("JS: a dropped file is in the file field", await page.$eval("#gk-import-s-file", (i) => i.files.length === 1 && i.files[0].name === "drop.csv"));
      check("JS: and read", await text(page, "#gk-import-s .gk-import-source") === `drop.csv · ${Buffer.byteLength(csv)} bytes · 2 rows`, await text(page, "#gk-import-s .gk-import-source"));
      const dropped = await page.evaluate(() => GK.import.result("s"));
      check("JS: 1.500 is fifteen hundred (locale de)", dropped.rows[2] && dropped.rows[2].qty === "1500", JSON.stringify(dropped.rows));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="confirm"]')]);
      let s2 = await serverResult(page);
      check("JS → server: a dropped file posts, the server agrees", s2.name === "s" && s2.step === "done" && same(s2.result.rows, dropped.rows), firstDiff(s2.result.rows, dropped.rows));

      // Pasted: read a moment after the typing stops; the server reads what the form posts.
      await page.goto(url);
      await arm(page);
      await page.click("#gk-import-s .gk-import-paste summary");
      await page.fill("#gk-import-s-text", "Nr\tNachname\tAbo\nK1\tHuber\tja\nK2\tGruber\tvielleicht\n");
      await page.waitForSelector("#gk-import-s .gk-import-panel");
      check("JS: pasted text is read", (await text(page, "#gk-import-s .gk-import-source")).startsWith("Pasted text ·")
        && await page.$eval('#gk-import-s select[name="s[delimiter]"]', (s) => s.value) === "tab");
      const pasted = await page.evaluate(() => GK.import.result("s"));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="confirm"]')]);
      s2 = await serverResult(page);
      check("JS → server: pasted text, the same rows, messages and meta (the form posts CRLF, the browser reads CRLF)", s2.step === "done"
        && same(s2.result.rows, pasted.rows) && same(s2.result.errors, pasted.errors) && same(s2.result.meta, pasted.meta), firstDiff(s2.result, pasted));

      // GK.import.read(): the file goes into the file field, so Import posts it.
      await page.goto(url);
      const viaApi = await page.evaluate(() => GK.import.read("s", new Blob(["Nr;Nachname;E-Mail\nK1;Huber;a@b.at\nK2;Gruber;x\n"]), "api.csv"));
      check("JS: GK.import.read() puts the file in the file field", await page.$eval("#gk-import-s-file", (i) => i.files.length === 1 && i.files[0].name === "api.csv")
        && viaApi.valid === 1 && viaApi.invalid === 1);
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="confirm"]')]);
      s2 = await serverResult(page);
      check("JS → server: a file read by GK.import.read() posts", s2.name === "s" && s2.step === "done" && same(s2.result.rows, viaApi.rows), firstDiff(s2.result.rows, viaApi.rows));

      // Refused before a byte is read; read and refused; Cancel.
      await page.goto(url);
      await arm(page);
      await page.setInputFiles("#gk-import-small-file", file("big.csv", "x".repeat(5000)));
      await page.waitForSelector("#gk-import-small [data-gk-import-error]");
      ev = await events(page);
      check("JS: a file over the limit is refused in words, before it is read", await text(page, '#gk-import-small [role="alert"]') === "The file is 5 KB; at most 4 KB can be imported."
        && ev.length === 1 && ev[0].type === "gk:importerror" && !(await has(page, "#gk-import-small .gk-import-panel")));
      await page.setInputFiles("#gk-import-small-file", file("rows.csv", "Nr;Nachname\n" + "K;x\n".repeat(6)));
      await page.waitForFunction(() => /more than 5 rows/.test((document.querySelector('#gk-import-small [role="alert"]') || {}).textContent || ""));
      check("JS: too many rows, in words", await text(page, '#gk-import-small [role="alert"]') === "The file has more than 5 rows. Import it in parts of at most 5 rows.");
      await page.setInputFiles("#gk-import-small-file", file("ok.csv", "Nr;Nachname\nK1;Huber\n"));
      await page.waitForSelector("#gk-import-small .gk-import-panel");
      check("JS: a good file after a refused one clears the message", !(await has(page, "#gk-import-small [data-gk-import-error]")));
      await page.click("#gk-import-small [data-gk-import-cancel]");
      check("JS: Cancel goes back to step 1 without a request", !(await has(page, "#gk-import-small .gk-import-panel"))
        && await page.$eval("#gk-import-small", (f) => f.getAttribute("data-gk-import-step")) === "upload"
        && await page.$eval("#gk-import-small-file", (i) => i.files.length === 0 && document.activeElement === i)
        && !(await page.$eval("#gk-import-small [data-gk-import-start]", (d) => d.hidden)));

      // A step 2 the server drew (storage(false)) is taken over — the same panel, then live.
      const { context: c2, page: noScript } = await open();
      await noScript.goto(url + "?nojs=1");
      const serverPanel = await noScript.$eval("#gk-import-a .gk-import-panel", (p) => p.outerHTML);
      await c2.close();
      await page.goto(url);
      await arm(page);
      const adopted = await page.$eval("#gk-import-a .gk-import-panel", (p) => p.outerHTML);
      const strip = (h) => h.replace(/<button type="submit"[^>]*value="map">.*?<\/button>/, "");
      check("JS: a server's step 2 is taken over unchanged (but for Check again)", strip(serverPanel) === adopted,
        (() => { const a = strip(serverPanel); let i = 0; while (i < a.length && a[i] === adopted[i]) i++; return a.slice(i - 80, i + 80) + "\n   js: " + adopted.slice(i - 80, i + 80); })());
      await page.selectOption("#gk-import-a-map-3", "");
      check("JS: and checked live from then on", (await page.evaluate(() => GK.import.result("a").mapping[3])) === null
        && (await text(page, "#gk-import-a .gk-import-summary")) !== `${demo.result.total} rows, ${demo.result.invalid} with errors.`);
      await page.selectOption("#gk-import-a-map-3", "email");
      const adoptedResult = await page.evaluate(() => GK.import.result("a"));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-a button[value="confirm"]')]);
      s2 = await serverResult(page);
      check("JS → server: the taken-over step 2 posts the file it carries", s2.name === "a" && s2.step === "done" && same(s2.result.rows, adoptedResult.rows));
      await context.close();
    }

    // ═══ 3. Without script: a POST in three steps ═══════════════════════════════════════════
    if (part("nojs")) {
      const { context, page } = await open({ javaScriptEnabled: false });
      await page.goto(url);
      await page.setInputFiles("#gk-import-c-file", file("kunden.csv", sample));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="upload"]')]);
      let srv = await serverResult(page);
      const token = await page.$eval('#gk-import-c input[name="c[token]"]', (i) => i.value).catch(() => "");
      check("no JS: the upload leads to step 2", srv.name === "c" && srv.step === "map" && await page.$eval("#gk-import-c", (f) => f.getAttribute("data-gk-import-step")) === "map");
      check("no JS: the file waits under a token of 128 bits, readable by the server alone", /^[0-9a-f]{32}$/.test(token)
        && fs.existsSync(path.join(store, `gk-import-${token}.csv`)) && (fs.statSync(path.join(store, `gk-import-${token}.csv`)).mode & 0o777) === 0o600);
      check("no JS: the summary, the preview of 6, every row with errors", await text(page, "#gk-import-c .gk-import-summary") === `20 rows, ${demo.result.invalid} with errors.`
        && (await page.$$("#gk-import-c .gk-import-rowno")).length === 6
        && (await page.$$("#gk-import-c .gk-import-errors li")).length === Object.values(demo.result.errors).reduce((n, e) => n + Object.keys(e).length, 0));
      check("no JS: the result is the corpus's", same(srv.result.rows, demo.result.rows) && same(srv.result.errors, demo.result.errors), firstDiff(srv.result, demo.result));
      check("no JS: the hidden field travels", await page.$eval('#gk-import-c input[name="_token"]', (i) => i.value) === 'tok-<&>"');
      // A field for two columns: the first keeps it, said.
      await page.selectOption("#gk-import-c-map-8", "first");
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="map"]')]);
      srv = await serverResult(page);
      check("no JS: Check again applies the posted mapping; a field twice keeps the first", srv.step === "map" && srv.result.mapping[1] === "first" && srv.result.mapping[8] === null
        && (await text(page, "#gk-import-c .gk-import-notes")).includes("First name was chosen for two columns; only the first (Vorname) is used."));
      await page.selectOption("#gk-import-c-map-1", "");
      await page.selectOption("#gk-import-c-map-8", "first");
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="map"]')]);
      srv = await serverResult(page);
      check("no JS: and moves it when the person moves it", srv.result.mapping[1] === null && srv.result.mapping[8] === "first");
      await page.selectOption('#gk-import-c select[name="c[encoding]"]', "UTF-8");
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="map"]')]);
      check("no JS: UTF-8 for a Windows-1252 file is said, the file stays", (await serverResult(page)).step === "map"
        && await text(page, '#gk-import-c .gk-import-panel > [role="alert"]') === "The file is not valid UTF-8. Choose Windows-1252.");
      const valid = (await serverResult(page)).result.valid;
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="confirm"]')]);
      srv = await serverResult(page);
      check("no JS: Import ends in step done with the valid rows", srv.step === "done" && Object.keys(srv.result.rows).length === valid && srv.result.mapping[8] === "first");
      check("no JS: the kept file is gone", !fs.existsSync(path.join(store, `gk-import-${token}.csv`)));
      // Cancel.
      await page.goto(url);
      await page.setInputFiles("#gk-import-c-file", file("kunden.csv", sample));
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="upload"]')]);
      const token2 = await page.$eval('#gk-import-c input[name="c[token]"]', (i) => i.value);
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-c button[value="cancel"]')]);
      check("no JS: Cancel says so and forgets the file", await text(page, '#gk-import-c [role="status"]') === "The import was cancelled. Nothing was imported."
        && !fs.existsSync(path.join(store, `gk-import-${token2}.csv`)) && (await serverResult(page)).step === "upload");
      // Nothing chosen; the paste box; the file in the form.
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="upload"]')]);
      check("no JS: nothing chosen is said", await text(page, '#gk-import-s [role="alert"]') === "Choose a CSV file or paste its text.");
      await page.click("#gk-import-s .gk-import-paste summary");
      await page.fill("#gk-import-s-text", "Nr;Nachname;Mail\nK1;Huber;a@b.at\nK1;Gruber;c@d.at\n");
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="upload"]')]);
      check("no JS: storage(false) carries the file in the form, nothing on the server", await has(page, '#gk-import-s input[name="s[data]"]') && fs.readdirSync(store).length === 0
        && await text(page, "#gk-import-s .gk-import-errors li") === "Row 3 · Customer no.: Already in row 2. K1");
      await Promise.all([page.waitForNavigation(), page.click('#gk-import-s button[value="confirm"]')]);
      srv = await serverResult(page);
      check("no JS: and imports from it", srv.name === "s" && srv.step === "done" && Object.keys(srv.result.rows).join() === "2");
      check("no JS: a refused file goes back to step 1 in words", await (async () => {
        await page.goto(url);
        await page.setInputFiles("#gk-import-small-file", file("big.csv", "x".repeat(5000)));
        await Promise.all([page.waitForNavigation(), page.click('#gk-import-small button[value="upload"]')]);
        return (await text(page, '#gk-import-small [role="alert"]')) === "The file is 5 KB; at most 4 KB can be imported.";
      })());
      await context.close();
    }

    // ═══ 4. A phone, the accessibility tree, the keyboard ═══════════════════════════════════
    if (part("phone")) {
      const { context, page } = await open({ viewport: { width: 390, height: 844 } });
      await page.goto(url);
      await addFont(page);
      const wide = "Nr;Nachname;E-Mail;" + Array.from({ length: 12 }, (_, i) => "Spalte " + i).join(";") + "\n"
        + Array.from({ length: 8 }, (_, r) => `K${r};Huber;x${r}@y.at;` + Array.from({ length: 12 }, () => "ein recht langer Wert").join(";")).join("\n");
      await page.setInputFiles("#gk-import-c-file", file("wide.csv", wide));
      await page.waitForSelector("#gk-import-c .gk-import-panel");
      const m = await page.evaluate(() => {
        const p = document.querySelector("#gk-import-c .gk-import-preview");
        const cols = [...document.querySelectorAll("#gk-import-c .gk-import-col")].map((c) => Math.round(c.getBoundingClientRect().left));
        const btn = document.querySelector('#gk-import-c button[value="confirm"]').getBoundingClientRect();
        const fsz = getComputedStyle(document.querySelector("#gk-import-c .gk-import-col select")).fontSize;
        return { page: document.documentElement.scrollWidth, inner: p.scrollWidth > p.clientWidth, oneColumn: new Set(cols).size === 1,
          btnW: Math.round(btn.width), btnH: Math.round(btn.height), form: Math.round(document.getElementById("gk-import-c").getBoundingClientRect().width), fsz };
      });
      check("390px: the page does not scroll sideways, the preview does inside its box", m.page <= 390 && m.inner, JSON.stringify(m));
      check("390px: the mapping is one column of cards", m.oneColumn);
      check("390px: buttons a finger wide and the full width, fields 16px", m.btnH >= 44 && m.btnW >= m.form - 2 && m.fsz === "16px", JSON.stringify(m));
      if (SHOTS) await page.locator("#s-c").screenshot({ path: path.join(SHOTS, "import-390-light.png") });
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(url);
      await page.setInputFiles("#gk-import-c-file", file("kunden.csv", sample));
      await page.waitForSelector("#gk-import-c .gk-import-panel");
      const ax = async (sel) => {
        const cdp = await page.context().newCDPSession(page);
        try {
          const { root } = await cdp.send("DOM.getDocument", { depth: 0 });
          const { nodeId } = await cdp.send("DOM.querySelector", { nodeId: root.nodeId, selector: sel });
          const { nodes } = await cdp.send("Accessibility.getPartialAXTree", { nodeId, fetchRelatives: false });
          const n = nodes.find((x) => !x.ignored) || nodes[0];
          return { name: n.name ? n.name.value : "", role: n.role ? n.role.value : "" };
        } finally { await cdp.detach(); }
      };
      const lay = await page.evaluate(() => {
        // An address, and a note with spaces in it (row 3, the column Notiz).
        const lines = (sel) => {
          const cell = document.querySelector("#gk-import-c .gk-import-preview tbody " + sel);
          const r = document.createRange();
          r.selectNodeContents(cell.firstChild);
          return [cell.firstChild.textContent, new Set([...r.getClientRects()].map((x) => Math.round(x.top))).size];
        };
        const drop = document.querySelector("#gk-import-c .gk-import-drop");
        return { mail: lines("tr:nth-child(1) td:nth-child(6)"), note: lines("tr:nth-child(2) td:nth-child(11)"),
          drop: Math.round(drop.getBoundingClientRect().height), icon: getComputedStyle(drop.querySelector(".gk-import-drop-icon")).display };
      });
      check("1280px: a value keeps its line in the preview (the box scrolls instead)", lay.mail[0] === "anna.mueller@example.at" && lay.mail[1] === 1
        && lay.note[0] === "Rückruf; bitte vormittags Tel. nur Mo–Fr" && lay.note[1] === 1, JSON.stringify(lay));
      check("1280px: once a file is read, the drop box is a line, not a stage", lay.drop < 80 && lay.icon === "none", JSON.stringify(lay));
      const a1 = await ax("#gk-import-c-map-3");
      const a2 = await ax("#gk-import-c .gk-import-preview");
      const a3 = await ax("#gk-import-c .gk-import-summary");
      const a4 = await ax("#gk-import-c-file");
      const a5 = await ax("#gk-import-c");
      check("a11y: a column's select is named by the column", a1.role === "combobox" && a1.name === "Column E-Mail", JSON.stringify(a1));
      check("a11y: the preview is a named region", a2.role === "region" && a2.name === "Preview of the first 6 rows", JSON.stringify(a2));
      check("a11y: the summary is a status", a3.role === "status", JSON.stringify(a3));
      check("a11y: the file field is named", /Choose a CSV file, or drop it here\./.test(a4.name), JSON.stringify(a4));
      check("a11y: the form is named", a5.role === "form" && a5.name === "Import c", JSON.stringify(a5));
      await page.focus("#gk-import-c .gk-import-preview");
      check("keyboard: the preview takes the focus, and shows it", await page.$eval("#gk-import-c .gk-import-preview", (p) => getComputedStyle(p).outlineStyle !== "none"));
      await page.focus('#gk-import-c input[type="checkbox"][name="c[header]"]');
      await page.keyboard.press("Shift+Tab");
      await page.keyboard.press("Tab");
      check("keyboard: the header box shows its focus", await page.$eval('#gk-import-c input[type="checkbox"][name="c[header]"] + .gk-checkbox-custom', (b) => getComputedStyle(b).outlineStyle === "solid"));
      await context.close();
    }

    // ═══ 5. Light, both dark spellings, no theme and the six: contrast ══════════════════════
    for (const mode of part("look") ? MODES : []) {
      for (const theme of THEMES) {
        const tag = `${mode}${theme ? "/" + theme : ""}`;
        const { context, page } = await open();
        await page.goto(url + `?mode=${mode === "light" ? "" : mode}&theme=${theme}`);
        await addFont(page);
        await page.setInputFiles("#gk-import-c-file", file("kunden.csv", sample));
        await page.waitForSelector("#gk-import-c .gk-import-panel");
        await page.setInputFiles("#gk-import-small-file", file("big.csv", "x".repeat(5000)));
        await page.waitForSelector("#gk-import-small [data-gk-import-error]");
        // The importer 's' without its customer number: the message for a required field.
        await page.setInputFiles("#gk-import-s-file", file("nonr.csv", "Nachname;E-Mail\nHuber;a@b.at\n"));
        await page.waitForSelector("#gk-import-s .gk-import-missing");
        const res = await page.evaluate(new Function(PIXEL + `
          const q = (s) => document.querySelector(s);
          const out = {};
          const t = (name, el, pseudo) => { if (el) out[name] = ["text", textOn(el, pseudo)]; else out[name] = ["missing", 0]; };
          const g = (name, el, colour) => { if (el) { const gr = ground(el); out[name] = ["graphic", ratio(px(colour(getComputedStyle(el)), gr), gr)]; } else out[name] = ["missing", 0]; };
          t("message under a cell", q("#gk-import-c .gk-import-msg"));
          t("row check: errors", q("#gk-import-c .gk-import-badrow .gk-import-state"));
          t("row check: OK", q("#gk-import-c tr:not(.gk-import-badrow) .gk-import-state"));
          t("sample", q("#gk-import-c .gk-import-col-sample"));
          t("hint", q("#gk-import-c .gk-import-hint"));
          t("field of a column", q("#gk-import-c th:not(.gk-import-ignored) .gk-import-target"));
          t("ignored column", q("#gk-import-c th.gk-import-ignored .gk-import-target"));
          t("ignored cell", q("#gk-import-c td.gk-import-ignored"));
          t("ignored column name", q("#gk-import-c .gk-import-col.gk-import-ignored .gk-import-col-name"));
          t("cell with an error", q("#gk-import-c td.gk-import-bad"));
          t("source line", q("#gk-import-c .gk-import-source"));
          t("skip note", q("#gk-import-c .gk-import-skip"));
          t("value in the list", q("#gk-import-c .gk-import-errors q"));
          t("drop text", q("#gk-import-c .gk-import-drop-text"));
          t("summary", q("#gk-import-c .gk-import-summary .gk-message-content"));
          t("missing field", q("#gk-import-s .gk-import-missing .gk-message-content"));
          t("refusal", q("#gk-import-small [data-gk-import-error] .gk-message-content"));
          t("row number", q("#gk-import-c .gk-import-rowno"));
          t("file button", q("#gk-import-small-file"), "::file-selector-button");
          g("drop box edge", q("#gk-import-small .gk-import-drop"), (s) => s.borderTopColor);
          g("bar of a wrong cell", q("#gk-import-c td.gk-import-bad"), (s) => (s.boxShadow.match(/rgba?\\([^)]*\\)|oklch\\([^)]*\\)|color\\([^)]*\\)/) || ["transparent"])[0]);
          g("error symbol of a row", q("#gk-import-c .gk-import-badrow .gk-import-state > .material-icons"), (s) => s.color);
          g("OK symbol of a row", q("#gk-import-c tr:not(.gk-import-badrow) .gk-import-state > .material-icons"), (s) => s.color);
          g("ignored column frame", q("#gk-import-c .gk-import-col.gk-import-ignored"), (s) => s.borderTopColor);
          return out;
        `));
        const bad = Object.entries(res).filter(([, [kind, v]]) => kind === "missing" || v < (kind === "text" ? 4.5 : 3));
        check(`look ${tag}: text 4.5:1, edges, bars and symbols 3:1 (${Object.keys(res).length} measured)`, bad.length === 0,
          bad.map(([k, [kind, v]]) => `${k}: ${kind} ${v.toFixed(2)}`).join("; "));
        if (!theme && SHOTS) {
          await page.evaluate(() => ["#gk-import-small", "#gk-import-s"].forEach((s) => { document.querySelector(s).closest("section").hidden = true; }));
          await page.locator("#s-c").screenshot({ path: path.join(SHOTS, `import-1280-${mode}.png`) });
        }
        await context.close();
      }
    }
  } finally {
    await browser.close();
  }

  check("no script error on any page", pageErrors.length === 0, pageErrors.join(" | "));
  const failed = results.filter((r) => !r.ok);
  results.forEach((r) => { if (!r.ok) console.log("✗ " + r.name + (r.detail ? "\n    " + r.detail : "")); });
  console.log(`\n${results.length - failed.length} of ${results.length} cases hold` + (failed.length ? `, ${failed.length} fail` : ""));
  process.exit(failed.length ? 1 : 0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
