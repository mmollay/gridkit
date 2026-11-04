#!/usr/bin/env node
/**
 * Line items (GridKit\LineItems, 1.117.0) in a real browser.
 *
 * `php tests/run.php` checks the markup and the arithmetic of PHP (tests/line-items.test.php); this
 * checks what the script does with that markup, and that it arrives at the same figures:
 *
 *  - the totals of every editor of the fixture, three of them of 60 random lines each, equal what
 *    LineItems::totals() said, string for string, and the totals the script draws are the ones PHP
 *    drew, byte for byte;
 *  - lines typed by hand (random figures in every shape a person types, some of them no figures at
 *    all) give the totals PHP's parse() gives for the very POST the browser builds, and the same
 *    messages under the same fields;
 *  - add (button, Enter in the last field), duplicate, delete with Undo, the order by keyboard and
 *    by mouse (js/sortierbar.js), the names renumbered every time, the event and its detail;
 *  - limits, required fields and constraint messages, Enter not sending the form, a blank line not
 *    blocking it; the article search filling a line; a Form (AJAX) whose server answers with
 *    {"errors": {"fx[1][qty]": …}}; the read-only editor; a phone (cards, nothing sideways); the
 *    POST without script; contrast in light and dark (both spellings) and the six themes.
 *
 * The requests go to http://gk.test/… and are answered with page.route(): no server is needed.
 *
 *     node ci/browser-line-items.js                    # needs the "playwright" package, and php
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-line-items.js
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
const fixturePhp = path.join(__DIR(), "browser-line-items-fixture.php");
function __DIR() { return __dirname; }
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-line-items-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
const source = execFileSync(php, [fixturePhp], { maxBuffer: 64 * 1024 * 1024 }).toString();
fs.writeFileSync(fixture, source);

/** parse() of the editor `id` for a POST given as [[name, value], …]. */
const phpParse = (id, pairs) => JSON.parse(execFileSync(php, [fixturePhp, "--parse", id], { input: JSON.stringify(pairs), maxBuffer: 64 * 1024 * 1024 }).toString());

// A small random source of our own, so a failure can be run again: GK_SEED=… node ci/browser-line-items.js
let seed = Number(process.env.GK_SEED || 20261117);
const rnd = () => { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; };
const pick = (a) => a[Math.floor(rnd() * a.length)];
const int = (n) => Math.floor(rnd() * n);

/** A figure as a person might type it in German or English, sometimes with a slip. */
function typed(locale, max, decimals) {
  const n = int(max);
  const frac = decimals && rnd() < 0.6 ? String(int(10 ** decimals)).padStart(decimals, "0") : "";
  const de = locale === "de";
  const dec = de ? "," : ".";
  const thou = de ? "." : ",";
  const grouped = String(n).replace(/\B(?=(\d{3})+(?!\d))/g, thou);
  const shapes = [
    () => String(n) + (frac ? dec + frac : ""),
    () => grouped + (frac ? dec + frac : ""),
    () => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + (frac ? dec + frac : ""),
    () => String(n) + (frac ? "." + frac : ""),                 // the other decimal sign
    () => (frac ? String(n) + dec + frac : String(n)) + (de ? " €" : ""),
    () => " " + String(n) + (frac ? dec + frac.slice(0, 1) : "") + " ",
  ];
  if (rnd() < 0.06) return pick(["abc", "1e5", "12.34.5", "--3", "0x1F", "", "½", "1,2,3"]);
  return pick(shapes)();
}

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail) });
  try {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.setDefaultTimeout(8000);

    // ── The endpoints ──────────────────────────────────────────────────
    const posts = [];
    let ajaxReply = '{"ok":true}';
    await page.route("http://gk.test/**", async (route) => {
      const req = route.request();
      const url = req.url();
      if (url.startsWith("http://gk.test/articles")) {
        const q = new URL(url).searchParams.get("q") || "";
        const all = [
          { id: 7, name: "Consulting hour", sub: "A-007", unit: "h", price: "95.00", vat: "20.00" },   // a DECIMAL column
          { id: 9, name: "Handbook", sub: "B-009", unit: "pcs", price: 34.5, vat: "10" },
        ];
        return route.fulfill({ status: 200, contentType: "application/json", headers: { "Access-Control-Allow-Origin": "*" },
          body: JSON.stringify(all.filter((a) => a.name.toLowerCase().includes(q.toLowerCase()))) });
      }
      posts.push({ url, body: req.postData() || "", headers: req.headers() });
      if (url.startsWith("http://gk.test/ajax")) {
        return route.fulfill({ status: 200, contentType: "application/json", headers: { "Access-Control-Allow-Origin": "*" }, body: ajaxReply });
      }
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p id=posted>posted</p>" });
    });

    await page.goto("file://" + fixture);
    const listen = () => page.evaluate(() => {
      window._events = [];
      document.addEventListener("gk:lineitemschange", (e) => {
        window._events.push({ id: e.target.id, items: e.detail.items, totals: e.detail.totals });
      });
    });
    await listen();

    const ed = (id) => "#gk-lines-" + id;
    const rows = (id) => page.$$eval(ed(id) + " > .gk-lines-body > .gk-lines-row", (lis) => lis.length);
    const names = (id) => page.$$eval(ed(id) + " > .gk-lines-body > .gk-lines-row", (lis) =>
      lis.map((li) => [...li.querySelectorAll("[data-gk-lines-field]")].map((f) => f.name)));
    const values = (id, key) => page.$$eval(ed(id) + ` > .gk-lines-body > .gk-lines-row [data-gk-lines-field="${key}"]`, (fs) => fs.map((f) => f.value));
    const totalsOf = (id) => page.evaluate((s) => GK.lineItems.totals(s), ed(id));
    const events = () => page.evaluate(() => window._events.splice(0));
    const formPairs = (formId) => page.evaluate((f) => [...new FormData(document.getElementById(f)).entries()], formId);
    const phpTotals = await page.evaluate(() => JSON.parse(document.getElementById("php-totals").textContent));

    // ── The figures: PHP and the browser, to the cent ──────────────────
    check("every editor is started, the read-only one keeps no live state",
      await page.evaluate(() => [...document.querySelectorAll("[data-gk-lines]")].every((el) =>
        el.classList.contains("gk-lines-readonly") ? !el.hasAttribute("data-gk-lines-ready") : el.hasAttribute("data-gk-lines-ready"))));
    for (const id of Object.keys(phpTotals)) {
      const js = await totalsOf(id);
      check(`totals of "${id}" equal LineItems::totals()` + (id.startsWith("rn") ? " (60 random lines)" : ""),
        JSON.stringify(js) === JSON.stringify(phpTotals[id]), JSON.stringify(js).slice(0, 200) + "\n        " + JSON.stringify(phpTotals[id]).slice(0, 200));
    }
    // The script redraws the figures on start; what it draws must be what PHP drew.
    const drawn = await page.evaluate((src) => {
      const doc = new DOMParser().parseFromString(src, "text/html");
      return [...document.querySelectorAll("[data-gk-lines]")].map((el) => {
        const php = doc.getElementById(el.id);
        const strip = (n) => n ? n.outerHTML : "";
        const lineTotals = (root) => [...root.querySelectorAll("[data-gk-lines-out]")].map((o) => o.textContent).join("|");
        return { id: el.id, totals: strip(el.querySelector(".gk-lines-totals")) === strip(php.querySelector(".gk-lines-totals")),
          lines: lineTotals(el) === lineTotals(php), sample: lineTotals(el).slice(0, 80) };
      });
    }, source);
    check("the totals the script draws are byte for byte the ones PHP drew, in every editor", drawn.every((d) => d.totals), JSON.stringify(drawn.filter((d) => !d.totals)));
    check("…and so is every line total", drawn.every((d) => d.lines), JSON.stringify(drawn.filter((d) => !d.lines)));
    check("starting fires no change event", (await events()).length === 0);

    // ── Typed by hand: the browser's figures and messages against parse() of its POST ──
    for (const [id, locale, n] of [["inv", "de", 24], ["fx", "en", 18]]) {
      const before = await rows(id);
      for (let k = 0; k < n; k++) await page.click(ed(id) + ' [data-gk-lines-action="add"]');
      const lis = await page.$$(ed(id) + " > .gk-lines-body > .gk-lines-row");
      for (let k = before; k < lis.length; k++) {
        const li = lis[k];
        await (await li.$('[data-gk-lines-field="description"]')).fill(rnd() < 0.1 ? "" : "Typed " + k);
        await (await li.$('[data-gk-lines-field="qty"]')).fill(typed(locale, rnd() < 0.2 ? 5000 : 40, 3));
        await (await li.$('[data-gk-lines-field="price"]')).fill(typed(locale, rnd() < 0.2 ? 99999 : 2500, rnd() < 0.2 ? 3 : 2));
        await (await li.$('[data-gk-lines-field="discount"]')).fill(rnd() < 0.6 ? "" : typed(locale, 40, 2));
        await (await li.$('[data-gk-lines-field="vat"]')).selectOption({ index: int(2) });
      }
      if (id === "inv") {
        await page.fill("#inv-discount", typed(locale, 15, 1));
        await page.fill("#inv-shipping", typed(locale, 30, 2));
      }
      const pairs = await formPairs(id === "inv" ? "inv-form" : "fx-form");
      const server = phpParse(id, pairs);
      const js = await totalsOf(id);
      check(`${n} lines typed by hand into "${id}" (${locale}): the totals equal parse() of the POST the browser builds`,
        JSON.stringify(js) === JSON.stringify(server.totals), JSON.stringify(js).slice(0, 300) + "\n        " + JSON.stringify(server.totals).slice(0, 300));
      const jsItems = await page.evaluate((s) => GK.lineItems.items(s), ed(id));
      check(`…and the lines, in machine form, equal parse()'s items (${server.items.length} of ${await rows(id)})`,
        JSON.stringify(jsItems) === JSON.stringify(server.items), JSON.stringify(jsItems[0]) + "\n        " + JSON.stringify(server.items[0]));
      const messages = await page.evaluate((s) => {
        const out = {};
        document.querySelectorAll(s + " [data-gk-lines-field], " + s + " [data-gk-lines-adjust]").forEach((f) => {
          if (f.type !== "hidden" && f.validationMessage) out[f.name] = f.validationMessage;
        });
        return out;
      }, ed(id));
      const serverMsgs = Object.fromEntries(Object.entries(server.errors).filter(([k]) => k !== id));
      const sorted = (o) => JSON.stringify(Object.keys(o).sort().map((k) => [k, o[k]]));
      check(`…and every field the browser holds invalid is the one parse() names, with the same words (${Object.keys(serverMsgs).length})`,
        sorted(messages) === sorted(serverMsgs) && Object.keys(serverMsgs).length > 0, sorted(messages).slice(0, 300) + "\n        " + sorted(serverMsgs).slice(0, 300));
      const evs = await events();
      const last = evs[evs.length - 1];
      check("…typing fires gk:lineitemschange, its detail what GK.lineItems.items() and .totals() say",
        evs.length > n && last && last.id === ed(id).slice(1) && JSON.stringify(last.totals) === JSON.stringify(js) && JSON.stringify(last.items) === JSON.stringify(jsItems));
    }
    // GK.lineItems.calc() is LineItems::totals() without an editor: 300 more random sets in one go.
    const sets = [];
    for (let s = 0; s < 300; s++) {
      const rates = ["20", "10", "13", "0", "7.7", "5.5"];
      const lines = [];
      for (let k = int(8) + 1; k > 0; k--) {
        // Some figures carry more decimals than are kept (4 for a quantity and a price, 2 for a
        // percentage): the 5th, the 3rd, decides the rounding of the last one kept.
        lines.push({ qty: pick([String(int(100)), int(50) + "." + int(1000), "-" + int(5), int(3) + ".5", int(9) + "." + String(int(100000)).padStart(5, "0")]),
          price: pick([int(100000) / 100 + "", int(10000) + "." + String(int(10000)).padStart(4, "0"), "0.005", "-" + int(900) + ".99", "1.005", "0.12345" + int(10), "19.9999" + int(10)]),
          discount: pick(["", "0", String(int(100)), int(40) + "." + int(100), int(40) + "." + String(int(1000)).padStart(3, "0")]), vat: pick(rates) });
      }
      sets.push({ lines, opts: { pricesNet: rnd() < 0.6, rounding: pick(["line", "total"]),
        discount: rnd() < 0.5 ? null : String(int(20)) + "." + int(1000), shipping: rnd() < 0.5 ? null : int(50) + "." + int(100000), shippingVat: rnd() < 0.5 ? null : pick(rates) } });
    }
    fs.writeFileSync(path.join(dir, "sets.json"), JSON.stringify(sets));
    const phpSets = JSON.parse(execFileSync(php, ["-r",
      'require ' + JSON.stringify(path.join(__dirname, "..", "autoload.php")) + ';' +
      '$s = json_decode(file_get_contents($argv[1]), true); $o = [];' +
      'foreach ($s as $x) $o[] = GridKit\\LineItems::totals($x["lines"], $x["opts"]);' +
      'echo json_encode($o);', path.join(dir, "sets.json")], { maxBuffer: 64 * 1024 * 1024 }).toString());
    const jsSets = await page.evaluate((s) => s.map((x) => GK.lineItems.calc(x.lines, x.opts)), sets);
    const differ = jsSets.map((j, k) => JSON.stringify(j) === JSON.stringify(phpSets[k]) ? -1 : k).filter((k) => k >= 0);
    check(`GK.lineItems.calc() equals LineItems::totals() on ${sets.length} random sets (net and gross, line and total rounding, discount, shipping)`,
      differ.length === 0, differ.length ? JSON.stringify(sets[differ[0]]) + "\n        js  " + JSON.stringify(jsSets[differ[0]]) + "\n        php " + JSON.stringify(phpSets[differ[0]]) : "");
    // And reading a number: the same answer as LineItems::parseNumber() for every shape and slip.
    const inputs = ["1.234,50", "1234.5", "1 234,50", "1.234", "1,234", "0,500", "12.34.5", "1e5", "-€5", "\u22123,5", "€ 1.234,50", "1.234,50 €",
      ".5", "5.", ",", "--5", "20 %", "0x1A", "\u0661\u0662", "1'234.50", "+7", "1\u00A0234,5", "1\u2003234", "EUR 10", "10 CHF", "-0", "00012,3400",
      "123456789012345678", "1.2345678901234", "1,000,000.5", "1.000.000,5", "1.000,000.5", "1,234.5.6", "1.234,5,6", "", " ", "abc", "1,2,3", "1..2", "€€5", "5-", "Infinity", "NaN",
      "999.999.999.999.999", "x".repeat(70), "1" + " ".repeat(70) + "2"];
    for (let k = 0; k < 200; k++) inputs.push(typed(pick(["de", "en"]), pick([10, 1000, 10000000]), int(5)));
    fs.writeFileSync(path.join(dir, "numbers.json"), JSON.stringify(inputs));
    const phpNums = JSON.parse(execFileSync(php, ["-r",
      'require ' + JSON.stringify(path.join(__dirname, "..", "autoload.php")) + ';' +
      '$o = []; foreach (json_decode(file_get_contents($argv[1]), true) as $t) $o[] = [GridKit\\LineItems::parseNumber($t, "de"), GridKit\\LineItems::parseNumber($t, "en")];' +
      'echo json_encode($o);', path.join(dir, "numbers.json")]).toString());
    const jsNums = await page.evaluate((list) => list.map((t) => [GK.lineItems.parse(t, "de"), GK.lineItems.parse(t, "en")]), inputs);
    const numDiff = inputs.filter((t, k) => JSON.stringify(jsNums[k]) !== JSON.stringify(phpNums[k]));
    check(`GK.lineItems.parse() reads ${inputs.length} inputs (hostile ones among them) as LineItems::parseNumber() does, de and en`,
      numDiff.length === 0, numDiff.slice(0, 3).map((t) => JSON.stringify(t) + " js " + JSON.stringify(jsNums[inputs.indexOf(t)]) + " php " + JSON.stringify(phpNums[inputs.indexOf(t)])).join("\n        "));
    // Numbers, not strings: what a database or a JSON answer hands over (PHP gets them as floats and ints).
    const floats = [0.1 + 0.2, 1.005, 2.675, 19.9, 1e21, 1e15, 123456789.123456789, -0, 0.000049, 0.00005, 1 / 3, 99999999999999.99, -12.5, 7];
    for (let k = 0; k < 100; k++) floats.push(Math.round((rnd() - 0.3) * 10 ** int(9)) / 10 ** int(7));
    fs.writeFileSync(path.join(dir, "floats.json"), JSON.stringify(floats));
    const phpFloats = JSON.parse(execFileSync(php, ["-r",
      'require ' + JSON.stringify(path.join(__dirname, "..", "autoload.php")) + ';' +
      '$o = []; foreach (json_decode(file_get_contents($argv[1]), true) as $n) $o[] = GridKit\\LineItems::parseNumber(is_int($n) ? $n : (float) $n);' +
      'echo json_encode($o);', path.join(dir, "floats.json")]).toString());
    const jsFloats = await page.evaluate((list) => list.map((n) => GK.lineItems.parse(n)), floats);
    const floatDiff = floats.filter((n, k) => jsFloats[k] !== phpFloats[k]);
    check(`…and ${floats.length} numbers (floats from a database: 0.1 + 0.2, 1.005, 1/3 …) the same way`,
      floatDiff.length === 0, floatDiff.slice(0, 3).map((n) => n + " js " + jsFloats[floats.indexOf(n)] + " php " + phpFloats[floats.indexOf(n)]).join("; "));

    // ── Add, duplicate, delete, undo ───────────────────────────────────
    await page.reload();
    await listen();
    check("names are numbered 0..n-1 in the order on the screen", JSON.stringify((await names("inv")).map((r) => r[0])) === JSON.stringify(["inv[0][description]", "inv[1][description]", "inv[2][description]"]));
    await page.click(ed("inv") + ' [data-gk-lines-action="add"]');
    const afterAdd = await page.evaluate((s) => {
      const lis = document.querySelectorAll(s + " > .gk-lines-body > .gk-lines-row");
      const li = lis[lis.length - 1];
      const ids = [...document.querySelectorAll("[id]")].map((n) => n.id);
      return { n: lis.length, focus: document.activeElement === li.querySelector('[data-gk-lines-field="description"]'),
        qty: li.querySelector('[data-gk-lines-field="qty"]').value, pos: li.querySelector(".gk-lines-pos").textContent,
        name: li.querySelector('[data-gk-lines-field="qty"]').name, label: li.querySelector('label[for]').htmlFor === li.querySelector('[data-gk-lines-field="description"]').id,
        unique: new Set(ids).size === ids.length, group: li.querySelector(".gk-lines-line").getAttribute("aria-label"),
        required: li.querySelector('[data-gk-lines-field="description"]').required };
    }, ed("inv"));
    check("Add line: a fourth line at the end, numbered 4, its qty at the default, the caret in its description",
      afterAdd.n === 4 && afterAdd.focus && afterAdd.qty === "1" && afterAdd.pos === "4" && afterAdd.name === "inv[3][qty]" && afterAdd.group === "Line 4", JSON.stringify(afterAdd));
    check("…its labels point at its own fields, and no id on the page is there twice", afterAdd.label && afterAdd.unique);
    check("…a blank line requires nothing (it is not a line, parse() drops it)", afterAdd.required === false);
    const ev1 = await events();
    check("…and the editor says so: gk:lineitemschange", ev1.length === 1 && ev1[0].items.length === 3);
    // Enter in the last field of the last line adds one; Enter elsewhere sends nothing.
    await page.fill(ed("inv") + ' [data-gk-lines-field="description"] >> nth=3', "Typed with Enter");
    await page.fill(ed("inv") + ' [data-gk-lines-field="price"] >> nth=3', "10");
    await page.focus(ed("inv") + ' [data-gk-lines-field="price"] >> nth=3');
    const postsBefore = posts.length;
    await page.keyboard.press("Enter");
    await page.waitForTimeout(150);
    check("Enter in a field that is not the last sends no form and adds no line", posts.length === postsBefore && (await rows("inv")) === 4);
    await page.focus(ed("inv") + ' [data-gk-lines-field="vat"] >> nth=3');
    await page.keyboard.press("Enter");
    await page.waitForTimeout(150);
    const afterEnter = await page.evaluate((s) => {
      const lis = document.querySelectorAll(s + " > .gk-lines-body > .gk-lines-row");
      return { n: lis.length, focus: document.activeElement === lis[lis.length - 1].querySelector('[data-gk-lines-field="description"]') };
    }, ed("inv"));
    check("Enter in the last field of the last line adds a line and moves there, and sends nothing", afterEnter.n === 5 && afterEnter.focus && posts.length === postsBefore, JSON.stringify(afterEnter));
    // Duplicate line 1 (a textarea of two lines, a discount, a select).
    await page.selectOption(ed("inv") + ' [data-gk-lines-field="unit"] >> nth=0', "day");
    await events();
    await page.click(ed("inv") + ' [data-gk-lines-action="duplicate"] >> nth=0');
    const dup = await page.evaluate((s) => {
      const lis = [...document.querySelectorAll(s + " > .gk-lines-body > .gk-lines-row")];
      const v = (li, k) => li.querySelector(`[data-gk-lines-field="${k}"]`).value;
      return { n: lis.length, same: ["description", "qty", "unit", "price", "discount", "vat"].every((k) => v(lis[0], k) === v(lis[1], k)),
        unit: v(lis[1], "unit"), desc: v(lis[1], "description"), id0: !!lis[0].querySelector('[data-gk-lines-field="id"]'), id1: !!lis[1].querySelector('[data-gk-lines-field="id"]'),
        key: lis[0].getAttribute("data-gk-sort-id") !== lis[1].getAttribute("data-gk-sort-id"),
        focus: document.activeElement === lis[1].querySelector('[data-gk-lines-field="description"]'),
        total: lis[1].querySelector("[data-gk-lines-out]").textContent, handles: lis[1].querySelectorAll(":scope > .gk-sort-griff").length,
        names: lis.map((li) => li.querySelector('[data-gk-lines-field="qty"]').name) };
    }, ed("inv"));
    check("Duplicate: the copy right below, every value the same (the select and the two-line textarea too), the caret in it",
      dup.n === 6 && dup.same && dup.unit === "day" && dup.desc.includes("\n") && dup.focus, JSON.stringify(dup));
    check("…a new line of its own: no id of the original, a key of its own, one handle", dup.id0 && !dup.id1 && dup.key && dup.handles === 1, JSON.stringify(dup));
    check("…and every name after it moved up one", JSON.stringify(dup.names) === JSON.stringify([0, 1, 2, 3, 4, 5].map((i) => `inv[${i}][qty]`)));
    const totalsDup = await totalsOf("inv");
    check("…the totals count it", totalsDup.lines.length === 5 && totalsDup.lines[0].total === totalsDup.lines[1].total, JSON.stringify(totalsDup.lines));
    // Delete line 2 (the copy), then undo.
    await events();
    // outerHTML without the tooltip's doing: GK.tip turns a title into data-gk-tip on the first hover, and
    // the tooltip engine (1.112.0) points aria-describedby at its bubble (gk-tt-…).
    const rowHtml = (s, n) => page.evaluate(([s, n]) => {
      const li = document.querySelector(s + ` > .gk-lines-body > .gk-lines-row:nth-child(${n})`).cloneNode(true);
      li.querySelectorAll("[title], [data-gk-tip]").forEach((b) => { b.removeAttribute("title"); b.removeAttribute("data-gk-tip"); });
      li.querySelectorAll("[aria-describedby]").forEach((b) => {
        const rest = b.getAttribute("aria-describedby").split(/\s+/).filter((t) => t && !/^gk-tt-/.test(t));
        if (rest.length) b.setAttribute("aria-describedby", rest.join(" ")); else b.removeAttribute("aria-describedby");
      });
      return li.outerHTML;
    }, [s, n]);
    const delBefore = await rowHtml(ed("inv"), 2);
    await page.click(ed("inv") + ' [data-gk-lines-action="delete"] >> nth=1');
    const del = await page.evaluate((s) => ({
      n: document.querySelectorAll(s + " > .gk-lines-body > .gk-lines-row").length,
      toast: [...document.querySelectorAll(".gk-toast .gk-toast-text")].map((t) => t.textContent).pop(),
      undo: !!document.querySelector(".gk-toast [data-gk-lines-undo]"),
      focus: document.activeElement && document.activeElement.getAttribute("aria-label"),
    }), ed("inv"));
    check("Delete: the line goes, a toast says which one, with Undo", del.n === 5 && del.toast === "Line 2 deleted." && del.undo, JSON.stringify(del));
    check("…the caret stays on the delete button of the line that took its place", del.focus === "Delete line 2", del.focus);
    check("…the event follows", (await events()).length === 1);
    await page.click(".gk-toast [data-gk-lines-undo]");
    const undone = await page.evaluate((s) => ({
      n: document.querySelectorAll(s + " > .gk-lines-body > .gk-lines-row").length,
      focus: document.activeElement === document.querySelector(s + " > .gk-lines-body > .gk-lines-row:nth-child(2) [data-gk-lines-field=description]"),
      undoLeft: !!document.querySelector(".gk-toast:not(.gk-toast-out) [data-gk-lines-undo]"),
    }), ed("inv"));
    undone.html = await rowHtml(ed("inv"), 2);
    check("Undo puts it back where it was, as it was, the caret in it, the toast gone", undone.n === 6 && undone.html === delBefore && undone.focus && !undone.undoLeft, JSON.stringify({ n: undone.n, focus: undone.focus, same: undone.html === delBefore }));
    check("…and the totals are what they were", JSON.stringify(await totalsOf("inv")) === JSON.stringify(totalsDup));
    // GK.lineItems.add() from machine values.
    const added = await page.evaluate((s) => {
      const li = GK.lineItems.add(s, { description: "From script", qty: 2, price: "1234.5", vat: 10, unit: "kg", id: 99 });
      const v = (k) => li.querySelector(`[data-gk-lines-field="${k}"]`).value;
      return { price: v("price"), qty: v("qty"), vat: v("vat"), unit: v("unit"), id: v("id"), name: li.querySelector('[data-gk-lines-field="id"]').name };
    }, ed("inv"));
    check("GK.lineItems.add(): figures in machine form are written in the page's shape; an unknown unit is shown as it is; the id posts",
      added.price === "1.234,50" && added.qty === "2" && added.vat === "10" && added.unit === "kg" && added.id === "99" && added.name === "inv[6][id]", JSON.stringify(added));
    const unknownUnit = await page.evaluate((s) => [...document.querySelectorAll(s + ' [data-gk-lines-field="unit"]')][6].validationMessage, ed("inv"));
    check("…and marked: parse() refuses a unit the list does not offer, so the browser does too", unknownUnit === "Choose an entry of the list.", unknownUnit);
    await page.evaluate((s) => {
      const f = [...document.querySelectorAll(s + ' [data-gk-lines-field="unit"]')][6];
      f.value = "pcs";
      f.dispatchEvent(new Event("input", { bubbles: true }));
    }, ed("inv"));

    // ── The order: keyboard and mouse (js/sortierbar.js) ───────────────
    await events();
    const order = () => page.$$eval(ed("ro").replace("ro", "inv") + " > .gk-lines-body > .gk-lines-row [data-gk-lines-field=description]", (fs) => fs.map((f) => f.value.split("\n")[0]));
    const o1 = await order();
    await page.focus(ed("inv") + " > .gk-lines-body > .gk-lines-row:nth-child(1) > .gk-sort-griff");
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("ArrowDown");
    const midway = await page.$$eval(ed("inv") + " > .gk-lines-body > .gk-lines-row .gk-lines-pos", (ps) => ps.map((p) => p.textContent).join(","));
    await page.keyboard.press("Space");
    const o2 = await order();
    const kbNames = await names("inv");
    check("keyboard: Space, ArrowDown twice, Space moves line 1 to place 3", o2[2] === o1[0] && o2[0] === o1[1] && o2[1] === o1[2], JSON.stringify([o1.slice(0, 3), o2.slice(0, 3)]));
    check("…the numbers follow the line while it moves", midway === "1,2,3,4,5,6,7", midway);
    check("…the names are numbered again in the new order", kbNames.every((r, i) => r.every((n) => n.startsWith(`inv[${i}]`))));
    const kbEv = await events();
    check("…and the editor says so once, on putting it down", kbEv.length === 1 && kbEv[0].items[2].description.startsWith(o1[0]));
    const handle = await page.$(ed("inv") + " > .gk-lines-body > .gk-lines-row:nth-child(3) > .gk-sort-griff");
    const target = await page.$(ed("inv") + " > .gk-lines-body > .gk-lines-row:nth-child(1)");
    const hb = await handle.boundingBox();
    const tb = await target.boundingBox();
    await page.mouse.move(hb.x + hb.width / 2, hb.y + hb.height / 2);
    await page.mouse.down();
    await page.mouse.move(hb.x + hb.width / 2, hb.y - 10, { steps: 4 });
    await page.mouse.move(hb.x + hb.width / 2, tb.y + 4, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(300);
    const o3 = await order();
    check("mouse: the handle of line 3 dragged to the top puts it first", o3[0] === o2[2] && o3[1] === o2[0], JSON.stringify([o2.slice(0, 3), o3.slice(0, 3)]));
    check("…and the names follow", (await names("inv")).every((r, i) => r.every((n) => n.startsWith(`inv[${i}]`))));
    const slots = await page.evaluate((s) => [...document.querySelectorAll(s + " .gk-lines-row [data-gk-lines-cell] > .gk-field-error")].map((slot) => {
      const f = slot.parentNode.querySelector("[data-gk-lines-field]");
      return [slot.getAttribute("data-gk-error"), f && f.name, slot.id === f.getAttribute("aria-describedby") || f.classList.contains("gk-select-value-input")];
    }), ed("inv"));
    check(`after adding, duplicating, deleting and moving, every message line (${slots.length}) is keyed like its field, and the field points at it`,
      slots.length > 40 && slots.every(([key, name, pointed]) => key === name && pointed), JSON.stringify(slots.find(([k, n, p]) => k !== n || !p)));
    const ids = await page.evaluate(() => {
      const all = [...document.querySelectorAll("[id]")].map((n) => n.id);
      const labels = [...document.querySelectorAll("#gk-lines-inv label[for]")].every((l) => {
        const f = document.getElementById(l.htmlFor);
        return f && l.closest(".gk-lines-row") === f.closest(".gk-lines-row");
      });
      return { n: all.length, unique: new Set(all).size === all.length, labels };
    });
    check(`…no id on the page twice (${ids.n}), and every label names the field of its own line`, ids.unique && ids.labels, JSON.stringify(ids));

    // ── Validation, limits, the form ───────────────────────────────────
    const qty0 = ed("inv") + ' [data-gk-lines-field="qty"] >> nth=0';
    await page.fill(qty0, "abc");
    let msg = await page.$eval(qty0, (f) => ({ text: document.getElementById(f.getAttribute("aria-describedby")).textContent, invalid: f.getAttribute("aria-invalid") }));
    check("a figure that is none says nothing while it is typed", msg.text === "" && msg.invalid === null, JSON.stringify(msg));
    await page.keyboard.press("Tab");
    msg = await page.$eval(qty0, (f) => ({ text: document.getElementById(f.getAttribute("aria-describedby")).textContent, invalid: f.getAttribute("aria-invalid"), v: f.validationMessage }));
    check("…on leaving the field: the message under it, in the page's own number shape, and aria-invalid",
      msg.text === "Enter a number, such as 1.234,50." && msg.invalid === "true" && msg.v === msg.text, JSON.stringify(msg));
    await page.fill(qty0, "2,5");
    msg = await page.$eval(qty0, (f) => ({ text: document.getElementById(f.getAttribute("aria-describedby")).textContent, invalid: f.getAttribute("aria-invalid") }));
    check("…mended, it goes while typing", msg.text === "" && msg.invalid === null, JSON.stringify(msg));
    await page.fill(qty0, "1.234,5");
    await page.keyboard.press("Tab");
    check("a readable figure is written back in the page's shape on leaving", (await page.$eval(qty0, (f) => f.value)) === "1.234,5");
    await page.fill(qty0, "1");
    const price0 = ed("inv") + ' [data-gk-lines-field="price"] >> nth=0';
    await page.fill(price0, "12,345");
    await page.keyboard.press("Tab");
    check("a decimal too many: kept as typed (never rounded), with its message", (await page.$eval(price0, (f) => [f.value, document.getElementById(f.getAttribute("aria-describedby")).textContent].join("|"))) === "12,345|At most 2 decimal places.");
    const sendBlocked = posts.length;
    await page.click("#inv-submit");
    await page.waitForTimeout(200);
    check("the form does not go while a field is invalid", posts.length === sendBlocked);
    await page.fill(price0, "12,34");
    await page.keyboard.press("Tab");
    const blankBefore = await rows("inv");
    await page.click(ed("inv") + ' [data-gk-lines-action="add"]');
    const counting = (await page.evaluate((s) => GK.lineItems.items(s), ed("inv"))).length;
    const onScreen = await rows("inv");
    const screenOrder = (await order()).join("|");
    await page.click("#inv-submit");
    await page.waitForSelector("#posted");
    const sent = posts[posts.length - 1];
    const sentPairs = [...new URLSearchParams(sent.body).entries()];
    const sentOrder = sentPairs.filter(([k]) => /\[description\]$/.test(k));
    check("a blank line does not hold the form back; the POST is name[i][field], 0..n-1 in the order on the screen",
      onScreen === blankBefore + 1 && sentOrder.length === onScreen && sentOrder.every(([k], i) => k === `inv[${i}][description]`)
        && sentOrder.map(([, v]) => v.split(/\r?\n/)[0]).join("|") === screenOrder, sentPairs.slice(0, 3).join(" "));
    const sentParsed = phpParse("inv", sentPairs);
    check("…and parse() keeps the lines the browser counts (blank ones dropped) and finds nothing wrong",
      counting < onScreen && sentParsed.items.length === counting && Object.keys(sentParsed.errors).length === 0, `${counting} ${sentParsed.items.length} ` + JSON.stringify(sentParsed.errors));
    await page.goBack();
    await page.waitForSelector(ed("lim"));
    await listen();

    // Limits 1..3: one blank line to start, required; delete off at one line, add off at three.
    const lim0 = await page.evaluate((s) => {
      const el = document.querySelector(s);
      return { n: el.querySelectorAll(".gk-lines-row").length, req: el.querySelector('[data-gk-lines-field="description"]').required,
        del: el.querySelector('[data-gk-lines-action="delete"]').disabled, add: el.querySelector('[data-gk-lines-action="add"]').disabled };
    }, ed("lim"));
    check("limits(1, 3): one empty line to start, required (it is the one the limit asks for), not deletable", lim0.n === 1 && lim0.req && lim0.del && !lim0.add, JSON.stringify(lim0));
    const limPosts = posts.length;
    await page.click("#lim-submit");
    await page.waitForTimeout(200);
    const limMsg = await page.$eval(ed("lim") + ' [data-gk-lines-field="description"]', (f) => document.getElementById(f.getAttribute("aria-describedby")).textContent);
    check("…sending it empty: held back, and the message says what is missing", posts.length === limPosts && limMsg === "Description is required.", limMsg);
    await page.click(ed("lim") + ' [data-gk-lines-action="add"]');
    await page.click(ed("lim") + ' [data-gk-lines-action="add"]');
    const lim3 = await page.evaluate((s) => {
      const el = document.querySelector(s);
      return { n: el.querySelectorAll(".gk-lines-row").length, add: el.querySelector('[data-gk-lines-action="add"]').disabled,
        dup: [...el.querySelectorAll('[data-gk-lines-action="duplicate"]')].every((b) => b.disabled),
        del: [...el.querySelectorAll('[data-gk-lines-action="delete"]')].every((b) => !b.disabled) };
    }, ed("lim"));
    check("…at three lines Add and Duplicate are off, Delete is on", lim3.n === 3 && lim3.add && lim3.dup && lim3.del, JSON.stringify(lim3));
    const limApi = await page.evaluate((s) => GK.lineItems.add(s, { description: "fourth" }), ed("lim"));
    check("…and GK.lineItems.add() refuses a fourth", limApi === null && (await rows("lim")) === 3);
    // Empty: the empty state, and it goes with the first line.
    const emp = () => page.$eval(ed("emp") + " > [data-gk-lines-empty]", (e) => e.hidden);
    check("an editor without lines shows its empty state", (await emp()) === false);
    await page.click(ed("emp") + ' [data-gk-lines-action="add"]');
    check("…which goes with the first line", (await emp()) === true);
    await page.click(ed("emp") + ' [data-gk-lines-action="delete"]');
    check("…and comes back with the last one gone", (await emp()) === false);

    // ── The article search ─────────────────────────────────────────────
    await page.fill(ed("lk") + " .gk-ajax-search-input", "consult");
    await page.waitForSelector(ed("lk") + " .gk-select-option");
    await events();
    await page.click(ed("lk") + " .gk-select-option");
    await page.waitForTimeout(50);
    const lk = await page.evaluate((s) => {
      const li = document.querySelector(s + " .gk-lines-row");
      const v = (k) => li.querySelector(`[data-gk-lines-field="${k}"]`).value;
      return { article: v("article"), desc: v("description"), unit: v("unit"), price: v("price"), vat: v("vat"), qty: v("qty"),
        total: li.querySelector("[data-gk-lines-out]").textContent, gross: GK.lineItems.totals(s).gross };
    }, ed("lk"));
    check("picking an article fills its line: description, unit, price (in the page's shape), VAT, a quantity of 1",
      lk.article === "7" && lk.desc === "Consulting hour" && lk.unit === "h" && lk.price === "95.00" && lk.vat === "20" && lk.qty === "1", JSON.stringify(lk));
    check("…and the totals follow", lk.total === "€95.00" && lk.gross === "114.00", JSON.stringify(lk));
    const lkEv = await events();
    check("…with one event, not two (change and gk-select arrive together)", lkEv.length === 1, lkEv.length);
    await page.click(ed("lk") + ' [data-gk-lines-action="add"]');
    await page.fill(ed("lk") + " .gk-lines-row:nth-child(2) .gk-ajax-search-input", "hand");
    await page.waitForSelector(ed("lk") + " .gk-lines-row:nth-child(2) .gk-select-option");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("Enter");
    await page.waitForTimeout(50);
    const lk2 = await page.evaluate((s) => [...document.querySelectorAll(s + " .gk-lines-row:nth-child(2) [data-gk-lines-field]")].map((f) => f.value).join("|"), ed("lk"));
    check("…in a line added later too, by keyboard (a number price, a string rate)", lk2 === "9|Handbook|Handbook|1|pcs|34.50|10", lk2);
    const lkPairs = await page.evaluate((s) => [...document.querySelectorAll(s + " [name]")].map((f) => [f.name, f.value]), ed("lk"));
    const lkServer = phpParse("lk", lkPairs);
    const lkItems = await page.evaluate((s) => GK.lineItems.items(s), ed("lk"));
    check("…what it posts (the article's id, and its name under 'display') parse() reads as the browser does",
      JSON.stringify(lkItems) === JSON.stringify(lkServer.items) && lkServer.items[0].article === "7" && lkServer.items[0].article_name === "Consulting hour"
        && JSON.stringify(await totalsOf("lk")) === JSON.stringify(lkServer.totals), JSON.stringify(lkItems[0]) + "\n        " + JSON.stringify(lkServer.items[0]));

    // ── A Form (AJAX) and the server's messages ────────────────────────
    const fxLabel = await page.evaluate(() => {
      const el = document.querySelector("#gk-lines-fx");
      const label = document.getElementById(el.getAttribute("aria-labelledby"));
      return { label: label && label.textContent, forAttr: label && label.hasAttribute("for"), errors: document.querySelectorAll('#fx-form [data-gk-error="fx"]').length,
        rows: el.querySelectorAll(".gk-lines-row").length };
    });
    check("in a Form: the field's label names the editor (no dangling for=), one message line for the whole, values() fills the lines",
      fxLabel.label === "Lines" && !fxLabel.forAttr && fxLabel.errors === 1 && fxLabel.rows === 2, JSON.stringify(fxLabel));
    ajaxReply = JSON.stringify({ errors: { "fx[1][qty]": "Only 3 in stock.", fx: "Check the lines." } });
    await page.click('#fx-form [type="submit"]');
    await page.waitForFunction(() => document.querySelector('#fx-form [data-gk-error="fx[1][qty]"]').textContent !== "");
    const fxErr = await page.evaluate(() => {
      const f = document.querySelector('#fx-form [name="fx[1][qty]"]');
      return { msg: document.querySelector('#fx-form [data-gk-error="fx[1][qty]"]').textContent, invalid: f.getAttribute("aria-invalid"),
        whole: document.querySelector('#fx-form [data-gk-error="fx"]').textContent, focus: document.activeElement === f,
        slotOfField: document.getElementById(f.getAttribute("aria-describedby")).getAttribute("data-gk-error") };
    });
    check("an AJAX Form's {\"errors\": {\"fx[1][qty]\": …}} lands under that very field, marks it, and moves the caret there",
      fxErr.msg === "Only 3 in stock." && fxErr.invalid === "true" && fxErr.focus && fxErr.whole === "Check the lines." && fxErr.slotOfField === "fx[1][qty]", JSON.stringify(fxErr));

    // ── Read-only ──────────────────────────────────────────────────────
    const ro = await page.evaluate((s) => {
      const el = document.querySelector(s);
      return { fields: el.querySelectorAll("input, select, textarea, button").length, sort: el.querySelectorAll("[data-gk-sortierbar], .gk-sort-griff").length,
        names: el.querySelectorAll("[name]").length, sub: el.querySelector(".gk-lines-sub") && el.querySelector(".gk-lines-sub").textContent,
        cols: getComputedStyle(el.querySelector(".gk-lines-line")).gridTemplateColumns.split(" ").length,
        head: getComputedStyle(el.querySelector(".gk-lines-head")).gridTemplateColumns.split(" ").length };
    }, ed("ro"));
    check("read-only: the same layout, nothing to type into, no button, no handle, nothing that posts",
      ro.fields === 0 && ro.sort === 0 && ro.names === 0 && ro.cols === ro.head && ro.cols === 8, JSON.stringify(ro));
    check("…a description's second line beneath it, quieter", ro.sub === "Design, templates, 3 rounds of changes");

    // ── Light, dark (both spellings), the six themes: contrast ─────────
    const contrast = await page.evaluate(() => {
      const c = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
      const rgba = (f) => { c.clearRect(0, 0, 1, 1); c.fillStyle = "#000"; c.fillStyle = f; c.fillRect(0, 0, 1, 1); const d = c.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
      const over = (a, b) => [0, 1, 2].map((i) => a[i] * a[3] + b[i] * (1 - a[3]));
      const lum = (v) => { const f = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }; return 0.2126 * f(v[0]) + 0.7152 * f(v[1]) + 0.0722 * f(v[2]); };
      const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
      const ground = (el) => { const layers = []; for (let e = el; e; e = e.parentElement) layers.push(rgba(getComputedStyle(e).backgroundColor)); let g = [255, 255, 255]; for (const l of layers.reverse()) g = over(l, g); return g; };
      const textOf = (el) => { const g = ground(el); return ratio(over(rgba(getComputedStyle(el).color), g), g); };
      const probes = {
        "heading": "#gk-lines-inv .gk-lines-th",
        "position": "#gk-lines-inv .gk-lines-row .gk-lines-pos",
        "line total": "#gk-lines-inv [data-gk-lines-out]",
        "field text": '#gk-lines-inv [data-gk-lines-field="price"]',
        "sum label": "#gk-lines-inv .gk-lines-sum > dt",
        "sum figure": "#gk-lines-inv .gk-lines-sum > dd",
        "total": "#gk-lines-inv .gk-lines-grand > dd",
        "message": '#gk-lines-fx .gk-field-error:not(:empty)',
        "read-only detail": "#gk-lines-ro .gk-lines-sub",
        "empty title": "#gk-lines-emp .gk-empty-title",
        "empty hint": "#gk-lines-emp .gk-empty-hint",
      };
      const ui = { "delete button": '#gk-lines-inv [data-gk-lines-action="delete"]', "duplicate button": '#gk-lines-inv [data-gk-lines-action="duplicate"]' };
      const out = [];
      const modes = [["light", null], ["dark", "attr"], ["dark", "class"]];
      for (const theme of [null, "indigo", "ocean", "forest", "rose", "amber", "slate"]) {
        for (const [mode, how] of modes) {
          document.body.removeAttribute("data-gk-theme");
          if (theme) document.body.setAttribute("data-gk-theme", theme);
          document.body.setAttribute("data-gk-mode", how === "attr" ? "dark" : "light");
          document.body.classList.toggle("gk-dark", how === "class");
          void document.body.offsetWidth;
          document.getAnimations().forEach((a) => a.finish());
          for (const [name, sel] of Object.entries(probes)) {
            const el = document.querySelector(sel);
            if (el) out.push({ where: `${theme || "plain"}/${mode}${how ? "-" + how : ""}`, name, r: textOf(el), need: 4.5 });
          }
          for (const [name, sel] of Object.entries(ui)) {
            const el = document.querySelector(sel);
            const svg = el.querySelector("svg");
            const g = ground(el);
            out.push({ where: `${theme || "plain"}/${mode}${how ? "-" + how : ""}`, name, r: ratio(over(rgba(getComputedStyle(svg || el).color), g), g), need: 3 });
          }
        }
      }
      document.body.removeAttribute("data-gk-theme");
      document.body.setAttribute("data-gk-mode", "light");
      document.body.classList.remove("gk-dark");
      return out;
    });
    const low = contrast.filter((x) => x.r < x.need);
    check(`contrast: ${contrast.length} measurements (text ≥ 4.5:1, icons ≥ 3:1) in light, dark and .gk-dark, plain and six themes`,
      low.length === 0 && contrast.length >= 7 * 3 * 12, low.slice(0, 5).map((x) => `${x.where} ${x.name} ${x.r.toFixed(2)}`).join("; "));
    const darkField = await page.evaluate(() => {
      const f = document.querySelector('#gk-lines-inv [data-gk-lines-field="qty"]');
      const probe = document.createElement("span");
      f.parentNode.appendChild(probe);
      const out = [];
      for (const how of ["attr", "class", "light"]) {
        document.body.setAttribute("data-gk-mode", how === "attr" ? "dark" : "light");
        document.body.classList.toggle("gk-dark", how === "class");
        probe.style.backgroundColor = "var(--gk-surface-container)";
        out.push([getComputedStyle(f).backgroundColor, getComputedStyle(probe).backgroundColor]);
      }
      probe.remove();
      document.body.setAttribute("data-gk-mode", "light");
      return out;
    });
    check("dark, both spellings: the fields of a line sit on the raised surface (--gk-surface-container), as the table's inline edit does",
      darkField[0][0] === darkField[0][1] && darkField[1][0] === darkField[1][1] && darkField[2][0] !== darkField[2][1], JSON.stringify(darkField));

    // ── A phone: cards, nothing sideways ───────────────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(100);
    const phone = await page.evaluate(() => {
      const el = document.querySelector("#gk-lines-inv");
      const line = el.querySelector(".gk-lines-line");
      const label = el.querySelector(".gk-lines-label");
      const desc = el.querySelector('[data-gk-lines-cell="description"]').getBoundingClientRect();
      const cardR = line.getBoundingClientRect();
      const totals = el.querySelector(".gk-lines-totals").getBoundingClientRect();
      return { sw: document.documentElement.scrollWidth, cols: getComputedStyle(line).gridTemplateColumns.split(" ").length,
        head: getComputedStyle(el.querySelector(".gk-lines-head")).display, label: label.getBoundingClientRect().width,
        descFull: Math.abs(desc.width - (cardR.width - 24)) < 2, totalsIn: totals.right <= 390 && totals.left >= 0,
        font: getComputedStyle(el.querySelector('[data-gk-lines-field="qty"]')).fontSize,
        handle: (el.querySelector(".gk-sort-griff") || { getBoundingClientRect: () => ({ width: 0 }) }).getBoundingClientRect().width };
    });
    check("phone (390px): every line a card of two columns, the description across it, the heading row gone, the labels shown",
      phone.cols === 2 && phone.descFull && phone.head === "none" && phone.label > 20, JSON.stringify(phone));
    check("…nothing scrolls sideways, the totals stand inside the screen", phone.sw <= 390 && phone.totalsIn, JSON.stringify(phone));
    check("…fields at 16px (iOS does not zoom), the handle a finger's width", phone.font === "16px" && phone.handle >= 44, JSON.stringify(phone));
    await page.setViewportSize({ width: 1280, height: 900 });
    const wide = await page.evaluate(() => {
      const el = document.querySelector("#gk-lines-inv");
      const head = [...el.querySelectorAll(".gk-lines-head > *")].map((n) => Math.round(n.getBoundingClientRect().left));
      const cells = [...el.querySelector(".gk-lines-line").children].filter((n) => n.matches(".gk-lines-cell")).map((n) => Math.round(n.getBoundingClientRect().left));
      return { head, cells, label: el.querySelector(".gk-lines-label").getBoundingClientRect().width };
    });
    check("wide: each heading stands over its column, the labels are for a screen reader only",
      JSON.stringify(wide.head) === JSON.stringify(wide.cells) && wide.label <= 1, JSON.stringify(wide));

    // ── Without script ─────────────────────────────────────────────────
    const plain = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
    const p2 = await plain.newPage();
    const plainPosts = [];
    await p2.route("http://gk.test/**", (route) => { plainPosts.push(route.request().postData() || ""); return route.fulfill({ status: 200, contentType: "text/html", body: "<p id=posted>posted</p>" }); });
    await p2.goto("file://" + fixture);
    const hidden = await p2.$eval("#gk-lines-inv .gk-lines-tools", (t) => getComputedStyle(t).visibility);
    await p2.click("#inv-submit");
    await p2.waitForSelector("#posted");
    const plainPairs = [...new URLSearchParams(plainPosts[0] || "").entries()];
    const plainParsed = phpParse("inv", plainPairs);
    check("without script: the buttons that could not work are not shown; the form posts name[i][field] as drawn",
      hidden === "hidden" && plainPairs[0] && plainPairs[0][0] === "inv[0][description]" && plainPairs.some(([k]) => k === "inv[2][vat]") && plainPairs.some(([k]) => k === "inv_discount"), plainPairs.slice(0, 4).join(" "));
    check("…and the server computes from that POST the totals the page showed", JSON.stringify(plainParsed.totals) === JSON.stringify(phpTotals.inv), JSON.stringify(plainParsed.totals).slice(0, 200));
    await plain.close();
  } catch (err) {
    check("the run reaches its end", false, (err && err.stack ? err.stack : String(err)).split("\n").slice(0, 3).join(" | "));
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
