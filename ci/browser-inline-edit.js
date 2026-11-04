#!/usr/bin/env node
/**
 * Inline editing (Table::editable(), 1.110.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup of an editable cell is checked there
 * (tests/table-inline-edit.test.php); what the script does with it is checked here — opening a
 * cell with the pointer and the keyboard, Enter, Escape, Tab and leaving the field, the event and
 * its veto, the request and every kind of answer (ok, ok with a value, ok with display markup, a
 * refusal with a reason, 500, broken JSON, no network, a slow one), the rebuild of a setData()
 * table after a sort, a page and a search, a server table, a selection and a row link beside the
 * editable cells, a card on a phone and dark mode. The table PHP sends and the table the client
 * rebuilds must carry the same row ids and the same cell attributes, in the same order.
 *
 * The POSTs go to http://gk.test/cell and are answered with page.route(): no server is needed.
 *
 *     node ci/browser-inline-edit.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-inline-edit.js
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-inline-edit-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, execFileSync(php, [path.join(__dirname, "browser-inline-edit-fixture.php")], { maxBuffer: 64 * 1024 * 1024 }));

/** The selector of one editable cell. */
const cell = (table, id, column) => `[data-gk-table="${table}"] .gk-table > tbody > tr[data-gk-row-id="${id}"] > td[data-gk-edit="${column}"]`;

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail) });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.setDefaultTimeout(8000); // a wait that does not end is a failure, soon

    // ── The endpoint ───────────────────────────────────────────────────
    // Each case says what the next answer is; a held answer waits until release().
    const requests = [];
    let reply = { status: 200, body: '{"ok":true}' };
    let release = null;
    await page.route("http://gk.test/cell", async (route) => {
      const req = route.request();
      let body = null;
      try { body = JSON.parse(req.postData() || "null"); } catch (e) { body = req.postData(); }
      requests.push({ method: req.method(), body, headers: req.headers() });
      const r = reply;
      if (r.hold) await new Promise((res) => { release = res; });
      if (r.abort) return route.abort("failed");
      return route.fulfill({
        status: r.status || 200,
        contentType: "application/json",
        headers: { "Access-Control-Allow-Origin": "*" },
        body: r.body,
      });
    });

    await page.goto("file://" + fixture);
    await page.evaluate(() => {
      window._edits = [];
      window._escapes = 0;
      document.addEventListener("gk:celledit", (e) => {
        const d = Object.assign({}, e.detail);
        d.hasRespondWith = typeof d.respondWith === "function";
        delete d.respondWith;
        window._edits.push(d);
      });
      document.addEventListener("keydown", (e) => { if (e.key === "Escape") window._escapes++; });
    });

    const edits = () => page.evaluate(() => window._edits.slice());
    const toasts = () => page.$$eval(".gk-toast .gk-toast-text", (els) => els.map((e) => e.textContent));
    const clearToasts = () => page.evaluate(() => document.querySelectorAll(".gk-toast").forEach((t) => t.remove()));
    const html = (sel) => page.$eval(sel, (el) => el.innerHTML);
    const settled = (sel) => page.waitForFunction((s) => {
      const td = document.querySelector(s);
      return td && !td.hasAttribute("aria-busy") && !td.hasAttribute("data-gk-editing");
    }, sel);
    const dataOf = (table, id, column) => page.evaluate(([t, i, c]) => {
      const w = document.querySelector(`[data-gk-table="${t}"]`);
      const r = w._gkData.rows.find((x) => String(x.id) === i);
      return r ? r[c] : undefined;
    }, [table, id, column]);
    const fieldIn = (sel) => page.$(sel + " > input, " + sel + " > select");
    const active = () => page.evaluate(() => {
      const a = document.activeElement;
      const td = a && a.closest ? a.closest("td[data-gk-edit]") : null;
      return {
        tag: a ? a.tagName : "",
        type: a && a.type ? a.type : "",
        cell: td ? td.parentElement.getAttribute("data-gk-row-id") + "/" + td.getAttribute("data-gk-edit") : "",
      };
    });
    /** Type into the open field of a cell, replacing what it holds. */
    const typeInto = async (sel, text) => {
      await page.fill(sel + " > input", text);
    };

    // ── The markup ─────────────────────────────────────────────────────
    const a11y = await page.$eval(cell("inv", "1", "amount"), (td) => ({
      tabindex: td.getAttribute("tabindex"),
      hint: (document.getElementById(td.getAttribute("aria-describedby")) || {}).textContent,
      hidden: (document.getElementById(td.getAttribute("aria-describedby")) || {}).hidden,
    }));
    check("an editable cell is in the tab order and points at the sentence that says it can be edited",
      a11y.tabindex === "0" && a11y.hint === "Editable: press Enter to change it." && a11y.hidden === true, JSON.stringify(a11y));
    check("a cell of a column without 'edit' is not editable",
      !(await page.$('[data-gk-table="sel"] td[data-label="Amount"][data-gk-edit]')) &&
      !(await page.$('[data-gk-table="sel"] td[data-label="Amount"][tabindex]')));

    // The table PHP sent and the table the client rebuilds (no sort, same page) carry the same ids
    // and the same cell tags, attribute for attribute and in the same order.
    for (const t of ["inv", "pg", "sel", "rl", "evt"]) {
      const snap = () => page.$eval(`[data-gk-table="${t}"]`, (w) =>
        [...w.querySelectorAll(".gk-table > tbody > tr")].map((tr) =>
          (tr.getAttribute("data-gk-row-id") ?? "-") + " | " +
          [...tr.querySelectorAll(":scope > td[data-label]")].map((td) => td.outerHTML.slice(0, td.outerHTML.indexOf(">") + 1)).join(" ")));
      const server = await snap();
      await page.evaluate((id) => GK.table.renderStatic(document.querySelector(`[data-gk-table="${id}"]`)), t);
      const client = await snap();
      check(`the rebuilt table writes the row ids and cell attributes PHP wrote (${t})`,
        server.length > 0 && JSON.stringify(server) === JSON.stringify(client),
        JSON.stringify(server[0]) + "\n        " + JSON.stringify(client[0]));
    }

    // ── Opening and closing ────────────────────────────────────────────
    const amount1 = cell("inv", "1", "amount");
    const before = await html(amount1);
    const geometry = () => page.$eval('[data-gk-table="inv"]', (w) => ({
      columns: [...w.querySelectorAll("thead th")].map((th) => Math.round(th.getBoundingClientRect().width)).join(),
      row: Math.round(w.querySelector('tr[data-gk-row-id="1"]').getBoundingClientRect().height),
    }));
    const closed = await geometry();
    await page.click(amount1);
    const opened = await geometry();
    check("opening a field moves no column and no row", JSON.stringify(opened) === JSON.stringify(closed),
      JSON.stringify(closed) + " → " + JSON.stringify(opened));
    const under = await page.$eval(amount1, (td) => {
      const was = td.querySelector("[data-gk-edit-was]");
      const i = td.querySelector("input").getBoundingClientRect();
      const c = td.getBoundingClientRect();
      return { hidden: was && getComputedStyle(was).visibility === "hidden", gapRight: Math.round(c.right - i.right), line: getComputedStyle(td).backgroundImage };
    });
    check("…the value stays under the field, hidden, and no line is drawn under the open field",
      under.hidden && under.line === "none", JSON.stringify(under));
    check("…a number field keeps to the right edge of its cell, as the number did", under.gapRight === 4, JSON.stringify(under));
    let field = await page.$eval(amount1 + " > input", (i) => ({ type: i.type, value: i.value, label: i.getAttribute("aria-label"), focused: document.activeElement === i }));
    check("a click opens the field with the raw value, not the formatted one (€1,200.50 → 1200.5)",
      field.type === "number" && field.value === "1200.5" && field.label === "Amount" && field.focused, JSON.stringify(field));
    check("the open cell is marked data-gk-editing", await page.$eval(amount1, (td) => td.hasAttribute("data-gk-editing")));
    await page.keyboard.press("Escape");
    check("Escape puts the cell back as it was, byte for byte, and fires nothing",
      (await html(amount1)) === before && (await edits()).length === 0 && requests.length === 0);
    check("…the focus is back on the cell", (await active()).cell === "1/amount", JSON.stringify(await active()));
    check("…and Escape went no further than the field (a modal around it stays open)",
      (await page.evaluate(() => window._escapes)) === 0);

    await page.keyboard.press("Enter");
    check("Enter on a focused cell opens it", !!(await fieldIn(amount1)));
    await page.keyboard.press("Enter");
    check("Enter on an unchanged value closes the field and sends nothing",
      !(await fieldIn(amount1)) && (await html(amount1)) === before && (await edits()).length === 0 && requests.length === 0);
    await page.keyboard.press("F2");
    check("F2 on a focused cell opens it", !!(await fieldIn(amount1)));
    await page.keyboard.press("Escape");

    await page.focus(cell("inv", "1", "name"));
    await page.keyboard.press("Tab");
    check("the cells are reachable with Tab: from Name to Amount of the same row",
      (await active()).cell === "1/amount", JSON.stringify(await active()));

    // ── Saving with a URL ──────────────────────────────────────────────
    reply = { hold: true, status: 200, body: '{"ok":true}' };
    await page.click(amount1);
    await typeInto(amount1, "1500.25");
    await page.keyboard.press("Enter");
    await page.waitForFunction(() => window._edits.length === 1);
    const e1 = (await edits())[0];
    check("gk:celledit carries tableId, id, column, value and oldValue (numbers for a number column)",
      e1.tableId === "inv" && e1.id === "1" && e1.column === "amount" && e1.value === 1500.25 && e1.oldValue === 1200.5 && e1.hasRespondWith,
      JSON.stringify(e1));
    await page.waitForFunction(() => true);
    await new Promise((r) => setTimeout(r, 50));
    const busy = await page.$eval(amount1, (td) => ({ busy: td.getAttribute("aria-busy"), text: td.textContent }));
    check("while it is saved the cell is aria-busy and shows what was typed",
      busy.busy === "true" && busy.text === "1500.25", JSON.stringify(busy));
    check("…and looks it (faded)", Number(await page.$eval(amount1, (td) => getComputedStyle(td).opacity)) < 1);
    await page.click(amount1);
    check("a busy cell does not open again (no second edit, no second request)",
      !(await fieldIn(amount1)) && requests.length === 1, requests.length);
    await page.evaluate(() => GK.table.refresh("inv"));
    const rebuiltBusy = await page.$eval(amount1, (td) => td.getAttribute("aria-busy"));
    await page.click(amount1);
    check("…not even after a rebuild of the table while it saves",
      rebuiltBusy === "true" && !(await fieldIn(amount1)) && requests.length === 1, rebuiltBusy);
    const r1 = requests[0];
    check("the request is a POST of { id, column, value } as JSON",
      r1.method === "POST" && JSON.stringify(r1.body) === JSON.stringify({ id: "1", column: "amount", value: 1500.25 }), JSON.stringify(r1.body));
    check("…with the headers: JSON, X-Requested-With and the CSRF token of 'headers'",
      /application\/json/.test(r1.headers["content-type"] || "") && r1.headers["x-requested-with"] === "XMLHttpRequest" && r1.headers["x-csrf-token"] === "tok-123",
      JSON.stringify(r1.headers));
    release();
    await settled(amount1);
    check("{ ok: true }: the cell shows the value formatted like the column (€1,500.25)",
      (await page.$eval(amount1, (td) => td.textContent)) === "€1,500.25", await page.$eval(amount1, (td) => td.textContent));
    check("…the data block holds the new value", (await dataOf("inv", "1", "amount")) === 1500.25);
    check("…and the table's live region says so",
      (await page.$eval('[data-gk-table-status="inv"]', (el) => el.textContent.trim())) === "Saved.");
    check("…the focus is back on the cell after Enter", (await active()).cell === "1/amount", JSON.stringify(await active()));

    reply = { status: 200, body: '{"ok":true,"value":1600}' };
    await page.click(amount1);
    await typeInto(amount1, "1599.99");
    await page.keyboard.press("Enter");
    await settled(amount1);
    check("{ ok: true, value }: the stored value wins (1599.99 sent, 1600 shown and kept)",
      (await page.$eval(amount1, (td) => td.textContent)) === "€1,600.00" && (await dataOf("inv", "1", "amount")) === 1600);

    // A sort rebuilds the table from its data: the new value stays, the cells stay editable.
    await page.click('[data-gk-table="inv"] [data-gk-sort="amount"]');
    await page.waitForSelector('[data-gk-table="inv"] th.gk-sorted-asc');
    const sorted = await page.$eval('[data-gk-table="inv"]', (w) => ({
      ids: [...w.querySelectorAll(".gk-table > tbody > tr")].map((tr) => tr.getAttribute("data-gk-row-id")).join(),
      amount: w.querySelector('tr[data-gk-row-id="1"] > td[data-gk-edit="amount"]').textContent,
      editable: w.querySelectorAll('td[data-gk-edit][tabindex="0"][aria-describedby]').length,
    }));
    check("after a sort the new value is still there, the rows carry their ids and every cell is editable",
      sorted.amount === "€1,600.00" && sorted.ids === "5,2,3,4,1" && sorted.editable === 25, JSON.stringify(sorted));

    // Failures: the old value comes back and the reason is said, as text.
    const failure = async (name, r, expectToast) => {
      await clearToasts();
      reply = r;
      const sel = cell("inv", "2", "amount");
      const was = await html(sel);
      await page.click(sel);
      await typeInto(sel, "77");
      await page.keyboard.press("Enter");
      await settled(sel);
      await page.waitForSelector(".gk-toast");
      const t = await toasts();
      check(name, (await html(sel)) === was && (await dataOf("inv", "2", "amount")) === 9.9 && t.length === 1 && t[0] === expectToast,
        JSON.stringify({ toasts: t, now: await html(sel) }));
    };
    await failure("500: the old value comes back and the toast says it could not be saved",
      { status: 500, body: "<h1>Internal Server Error</h1>" }, "The change could not be saved.");
    await failure("{ ok: false, error }: the server's reason is shown as text, never as markup",
      { status: 200, body: '{"ok":false,"error":"<b>Too high</b>"}' }, "<b>Too high</b>");
    check("…no element of the server's text reached the page", !(await page.$(".gk-toast b")));
    await failure("422 with a reason: the reason is shown", { status: 422, body: '{"ok":false,"error":"Must be positive"}' }, "Must be positive");
    await failure("a 200 that is not JSON is a failure", { status: 200, body: "<html>login</html>" }, "The change could not be saved.");
    await failure("{ ok: \"yes\" } is not ok", { status: 200, body: '{"ok":"yes"}' }, "The change could not be saved.");
    await failure("a 500 is a failure even with { ok: true } in its body", { status: 500, body: '{"ok":true}' }, "The change could not be saved.");
    await failure("no network is a failure", { abort: true }, "The change could not be saved.");
    reply = { status: 200, body: '{"ok":true}' };
    await clearToasts();

    // Leaving the field saves; a second line stays; what is typed is text.
    const name1 = cell("inv", "1", "name");
    await page.click(name1);
    await typeInto(name1, "Ada King");
    const n0 = requests.length;
    await page.click("#elsewhere");
    await settled(name1);
    check("leaving the field (blur) saves it",
      requests.length === n0 + 1 && requests[n0].body.value === "Ada King" && (await dataOf("inv", "1", "name")) === "Ada King");
    check("…and the second line ('sub') is still under it",
      (await page.$eval(name1, (td) => td.firstChild.textContent + "|" + td.querySelector(".gk-cell-sub").textContent)) === "Ada King|ada@example.org",
      await html(name1));

    const evil = '<img src=x onerror="window._xss=1">';
    await page.click(name1);
    await typeInto(name1, evil);
    await page.keyboard.press("Enter");
    await settled(name1);
    await new Promise((r) => setTimeout(r, 100));
    check("markup typed into a field is text: no element, no script, the event carries it as typed",
      !(await page.$(name1 + " img")) && (await page.evaluate(() => window._xss)) === undefined &&
      (await page.$eval(name1, (td) => td.firstChild.textContent)) === evil && (await edits()).pop().value === evil);

    // Tab and Shift+Tab move from field to field.
    await page.click(cell("inv", "2", "name"));
    await page.keyboard.press("Tab");
    check("Tab in a field opens the next editable cell", (await active()).cell === "2/amount" && (await active()).tag === "INPUT", JSON.stringify(await active()));
    await page.keyboard.press("Shift+Tab");
    check("Shift+Tab opens the previous one", (await active()).cell === "2/name" && (await active()).tag === "INPUT", JSON.stringify(await active()));
    await page.keyboard.press("Escape");

    // Tab passes over a cell that is still saving.
    reply = { hold: true, status: 200, body: '{"ok":true}' };
    await page.click(cell("inv", "3", "amount"));
    await typeInto(cell("inv", "3", "amount"), "96");
    await page.keyboard.press("Enter");
    await page.waitForFunction((s) => document.querySelector(s).getAttribute("aria-busy") === "true", cell("inv", "3", "amount"));
    await page.click(cell("inv", "3", "name"));
    await page.keyboard.press("Tab");
    check("Tab passes over a cell that is still saving", (await active()).cell === "3/status", JSON.stringify(await active()));
    await page.keyboard.press("Escape");
    release();
    await settled(cell("inv", "3", "amount"));
    reply = { status: 200, body: '{"ok":true}' };

    // The end of a text selection is not a request to edit (the rule a row link follows).
    const name3sel = cell("inv", "3", "name");
    await page.evaluate((s) => {
      const td = document.querySelector(s);
      const r = document.createRange();
      r.selectNodeContents(td);
      getSelection().removeAllRanges();
      getSelection().addRange(r);
      td.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, button: 0 }));
    }, name3sel);
    check("a click that ends a text selection in a cell does not open it", !(await fieldIn(name3sel)));
    await page.evaluate(() => getSelection().removeAllRanges());

    // One field at a time: opening another one, even from a script, saves the first (it loses the focus).
    const name4 = cell("inv", "4", "name");
    await page.click(name4);
    await typeInto(name4, "Dörte Ü.");
    const nA = requests.length;
    await page.evaluate((s) => document.querySelector(s).click(), cell("inv", "5", "name"));
    await settled(name4);
    check("opening another field (even from a script) saves the open one first",
      requests.length === nA + 1 && requests[nA].body.value === "Dörte Ü." && !!(await fieldIn(cell("inv", "5", "name"))) && !(await fieldIn(name4)),
      JSON.stringify(requests.slice(nA).map((r) => r.body)));
    await page.keyboard.press("Escape");

    // Selects.
    const status2 = cell("inv", "2", "status");
    await page.click(status2);
    const sel2 = await page.$eval(status2 + " > select", (s) => ({ value: s.value, options: [...s.options].map((o) => o.value + "=" + o.textContent).join() }));
    check("a select takes its options from the labels of a label column, text and all",
      sel2.value === "open" && sel2.options === "paid=Paid,open=Open,void=void", JSON.stringify(sel2));
    const n1 = requests.length;
    await page.selectOption(status2 + " > select", "paid");
    await settled(status2);
    check("choosing an option saves it at once and the cell shows the label (colour and text)",
      requests.length === n1 + 1 && requests[n1].body.value === "paid" &&
      (await html(status2)) === '<span class="gk-label gk-label-green">Paid</span>', await html(status2));

    const status3 = cell("inv", "3", "status");
    await page.focus(status3);
    await page.keyboard.press("Enter");
    const n2 = requests.length;
    await page.keyboard.press("ArrowUp"); // 'void' is the last option: up is a change
    check("an arrow key in a select browses and does not save", requests.length === n2 && !!(await fieldIn(status3)));
    await page.keyboard.press("Enter");
    await settled(status3);
    check("…Enter saves what it shows", requests.length === n2 + 1 && requests[n2].body.column === "status", JSON.stringify(requests[n2] && requests[n2].body));

    const kind1 = cell("inv", "1", "kind");
    await page.click(kind1);
    const kinds = await page.$eval(kind1 + " > select", (s) => [...s.options].map((o) => o.value + "=" + o.textContent).join());
    check("a select takes its options from 'options' (value => text)", kinds === "b2b=Business,b2c=Consumer", kinds);
    await page.selectOption(kind1 + " > select", "b2c");
    await settled(kind1);
    check("…and a plain column shows the value it stores", (await html(kind1)) === "b2c");
    const kind5 = cell("inv", "5", "kind");
    await page.click(kind5);
    const k5 = await page.$eval(kind5 + " > select", (s) => ({ value: s.value, options: [...s.options].map((o) => o.value + "=" + o.textContent).join() }));
    const n7 = requests.length;
    await page.click("#elsewhere");
    await settled(kind5);
    check("a stored value that is not on the list is offered first and chosen, so leaving the field changes nothing",
      k5.value === "gov" && k5.options === "gov=gov,b2b=Business,b2c=Consumer" && requests.length === n7 && (await html(kind5)) === "gov", JSON.stringify(k5));

    // Numbers.
    const qty1 = cell("inv", "1", "qty");
    await page.click(qty1);
    await typeInto(qty1, "");
    await page.keyboard.press("Enter");
    await settled(qty1);
    check("an emptied number field sends null, and the cell shows the column's empty value",
      requests[requests.length - 1].body.value === null && (await page.$eval(qty1, (td) => td.textContent)) === "—");
    await clearToasts();
    await page.click(qty1);
    await page.keyboard.type("--");
    const n3 = requests.length;
    await page.keyboard.press("Enter");
    const invalid = await page.$eval(qty1, (td) => ({ open: !!td.querySelector("input"), invalid: td.querySelector("input") && td.querySelector("input").getAttribute("aria-invalid") }));
    check("what is not a number is not sent: the field stays open, marked invalid, and says why",
      invalid.open && invalid.invalid === "true" && requests.length === n3 && (await toasts()).includes("Enter a number."), JSON.stringify(invalid));
    await page.keyboard.press("Escape");
    await clearToasts();

    // A rebuild while a field is open saves it and keeps the focus on the cell.
    const name3 = cell("inv", "3", "name");
    await page.click(name3);
    await typeInto(name3, "Cyd");
    const n4 = requests.length;
    await page.evaluate(() => GK.table.refresh("inv"));
    await page.waitForFunction((n) => window._edits.length && window._edits[window._edits.length - 1].value === "Cyd", n4);
    await settled(name3);
    check("a rebuild while a field is open saves it and leaves the focus on its cell",
      requests.length === n4 + 1 && (await active()).cell === "3/name" && (await page.$eval(name3, (td) => td.firstChild.textContent)) === "Cyd",
      JSON.stringify(await active()));

    // ── Event only ─────────────────────────────────────────────────────
    await page.evaluate(() => {
      document.querySelector('[data-gk-table="evt"]').addEventListener("gk:celledit", (e) => {
        if (e.detail.value === "veto") e.preventDefault();
        if (e.detail.value === "refuse") e.detail.respondWith(Promise.resolve({ ok: false, error: "No." }));
        if (e.detail.value === "lower") e.detail.respondWith(new Promise((r) => setTimeout(() => r({ ok: true, value: "LOWER" }), 50)));
        if (e.detail.value === "reject") e.detail.respondWith(Promise.reject(new Error("offline")));
      });
    });
    const evName = cell("evt", "2", "name");
    const n5 = requests.length;
    await page.click(evName);
    await typeInto(evName, "Bobby");
    await page.keyboard.press("Enter");
    await settled(evName);
    check("without a URL nothing is sent, the event fires and the value is taken",
      requests.length === n5 && (await edits()).pop().value === "Bobby" && (await html(evName)) === "Bobby" && (await dataOf("evt", "2", "name")) === "Bobby");
    check("Enter in a field inside a form does not submit the form", (await page.evaluate(() => window._submitted)) === undefined);
    const evQty = cell("evt", "1", "qty");
    await page.click(evQty);
    await page.keyboard.type("--");
    await page.keyboard.press("Enter");
    check("…not even when the field stays open (a number that is not one)",
      !!(await fieldIn(evQty)) && (await page.evaluate(() => window._submitted)) === undefined);
    await page.keyboard.press("Escape");
    await clearToasts();

    await page.click(evName);
    await typeInto(evName, "veto");
    await page.keyboard.press("Enter");
    await settled(evName);
    check("preventDefault(): the old value comes back, nothing is kept, nothing is said",
      (await html(evName)) === "Bobby" && (await dataOf("evt", "2", "name")) === "Bobby" && (await toasts()).length === 0);

    await page.click(evName);
    await typeInto(evName, "refuse");
    await page.keyboard.press("Enter");
    await settled(evName);
    await page.waitForSelector(".gk-toast");
    check("respondWith() a refusal: the old value comes back and the reason is shown",
      (await html(evName)) === "Bobby" && (await toasts()).join() === "No.", JSON.stringify(await toasts()));
    await clearToasts();

    await page.click(evName);
    await typeInto(evName, "lower");
    await page.keyboard.press("Enter");
    check("respondWith() a promise: the cell is busy until it settles", (await page.$eval(evName, (td) => td.getAttribute("aria-busy"))) === "true");
    await settled(evName);
    check("…and its value wins", (await html(evName)) === "LOWER" && (await dataOf("evt", "2", "name")) === "LOWER");

    await page.click(evName);
    await typeInto(evName, "reject");
    await page.keyboard.press("Enter");
    await settled(evName);
    await page.waitForSelector(".gk-toast");
    check("respondWith() a rejected promise is a failure", (await html(evName)) === "LOWER" && (await toasts()).join() === "The change could not be saved.");
    await clearToasts();

    // ── Paged and searched ─────────────────────────────────────────────
    await page.fill('[data-gk-table="pg"] [data-gk-search]', "eve");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="pg"] tbody tr[data-gk-row-id]').length === 1);
    const pgEve = cell("pg", "5", "amount");
    check("after a search the row that is left carries its own id", !!(await page.$(pgEve)));
    await page.click(pgEve);
    await typeInto(pgEve, "42");
    await page.keyboard.press("Enter");
    await settled(pgEve);
    check("…and a change is saved under it", requests[requests.length - 1].body.id === "5" && (await dataOf("pg", "5", "amount")) === 42);
    await page.fill('[data-gk-table="pg"] [data-gk-search]', "");
    await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="pg"] tbody tr[data-gk-row-id]').length === 2);
    await page.click('[data-gk-table="pg"] .gk-pagination [data-gk-page="3"]');
    await page.waitForSelector(cell("pg", "5", "amount"));
    check("on page 3 the row of id 5 shows the value saved while it was found by a search",
      (await page.$eval(cell("pg", "5", "amount"), (td) => td.textContent)) === "€42.00");

    // ── A selection and a row link beside the editable cells ───────────
    await page.click('[data-gk-table="sel"] tr[data-gk-row-id="2"] td.gk-cb-col input');
    check("the selection still works on a table that is also editable",
      await page.isVisible('[data-gk-table="sel"] .gk-bulk-bar'));
    const selName = cell("sel", "2", "name");
    await page.click(selName);
    await typeInto(selName, "Bob B.");
    await page.keyboard.press("Enter");
    await settled(selName);
    check("…and a change is saved under the selection's key", requests[requests.length - 1].body.id === "2");
    const narrow = cell("sel", "1", "qty");
    await page.click(narrow);
    const nb = await page.$eval(narrow, (td) => {
      const i = td.querySelector("input").getBoundingClientRect();
      const c = td.getBoundingClientRect();
      return { field: Math.round(i.width), cell: Math.round(c.width), gapRight: Math.round(c.right - i.right) };
    });
    check("a number field wider than its narrow column keeps the number's right edge and grows to the left",
      nb.field > nb.cell && nb.gapRight === 4, JSON.stringify(nb));
    await page.keyboard.press("Escape");

    const rlAmount = cell("rl", "1", "amount");
    await page.click(rlAmount);
    check("a click on an editable cell of a row with a link opens the field, not the link",
      !!(await fieldIn(rlAmount)) && (await page.evaluate(() => location.hash)) === "");
    await page.keyboard.press("Escape");
    await page.click('[data-gk-table="rl"] tr[data-gk-row-id="1"] td[data-label="Name"]');
    check("…a click on the rest of the row still follows it", (await page.evaluate(() => location.hash)) === "#row-1");

    // ── A server table ─────────────────────────────────────────────────
    const srvAmount = cell("srv", "1", "amount");
    check("a server table carries the raw value in data-gk-raw", (await page.$eval(srvAmount, (td) => td.getAttribute("data-gk-raw"))) === "1200.5");
    await page.click(srvAmount);
    check("…and the field opens with it", (await page.$eval(srvAmount + " > input", (i) => i.value)) === "1200.5");
    await typeInto(srvAmount, "77");
    await page.keyboard.press("Enter");
    await settled(srvAmount);
    check("{ ok: true } on a server table: the client formats the value like the column (€77.00) and keeps it as the raw value",
      (await page.$eval(srvAmount, (td) => td.textContent + "|" + td.getAttribute("data-gk-raw"))) === "€77.00|77");

    const srvName = cell("srv", "1", "name");
    await page.click(srvName);
    await typeInto(srvName, "Zed");
    await page.keyboard.press("Enter");
    await settled(srvName);
    check("…a cell with a second line keeps it (the browser does not have that field)",
      (await html(srvName)) === 'Zed<div class="gk-cell-sub">ada@example.org</div>', await html(srvName));

    reply = { status: 200, body: '{"ok":true,"value":"Zoe","display":"<strong class=\\"shown\\">Zoe</strong> <a href=\\"#checked\\">(checked)</a>"}' };
    await page.click(srvName);
    await typeInto(srvName, "zoe");
    await page.keyboard.press("Enter");
    await settled(srvName);
    check("{ ok: true, display }: the server's markup is the cell",
      (await html(srvName)) === '<strong class="shown">Zoe</strong> <a href="#checked">(checked)</a>' && (await page.$eval(srvName, (td) => td.getAttribute("data-gk-raw"))) === "Zoe");
    reply = { status: 200, body: '{"ok":true}' };
    await page.click(srvName + " a");
    check("…a link in that markup is followed, the cell does not open",
      (await page.evaluate(() => location.hash)) === "#checked" && !(await fieldIn(srvName)));

    const srvStatus = cell("srv", "2", "status");
    await page.click(srvStatus);
    await page.selectOption(srvStatus + " > select", "paid");
    await settled(srvStatus);
    check("a label cell of a server table is drawn with its colour and text after a save",
      (await html(srvStatus)) === '<span class="gk-label gk-label-green">Paid</span>', await html(srvStatus));

    // Last for this table: its AJAX reload cannot be answered from file://, the rows give way to the error.
    const srvName3 = cell("srv", "3", "name");
    await page.click(srvName3);
    await typeInto(srvName3, "Cyrus");
    const n6 = requests.length;
    // Synchronously, before the request: the answer replaces the rows, and the field with them.
    const firedAtOnce = await page.evaluate(() => {
      const n = window._edits.length;
      GK.table.refresh("srv");
      return window._edits.length - n === 1 && window._edits[window._edits.length - 1].value === "Cyrus";
    });
    await page.waitForFunction((n) => true, n6);
    await new Promise((r) => setTimeout(r, 300));
    check("the reload of a server table saves a field that is still open first, before it asks for the rows",
      firedAtOnce && requests.length >= n6 + 1 && requests[n6].body.id === "3" && requests[n6].body.value === "Cyrus", JSON.stringify(requests.slice(n6).map((r) => r.body)));

    // A row without its key.
    const keyless = await page.$eval('[data-gk-table="evt"]', (w) => {
      const tr = w.querySelector('.gk-table > tbody > tr[data-gk-row-id=""]');
      return tr ? { cells: tr.querySelectorAll("td[data-gk-edit], td[tabindex]").length } : null;
    });
    await page.click('[data-gk-table="evt"] tr[data-gk-row-id=""] td[data-label="Name"]');
    check("a row without its key carries an empty id and no editable cell, and a click opens nothing",
      keyless && keyless.cells === 0 && !(await page.$('[data-gk-table="evt"] tr[data-gk-row-id=""] input')), JSON.stringify(keyless));

    // ── Look: the affordance, the field, light and dark ────────────────
    const lineOf = (sel) => page.$eval(sel, (td) => getComputedStyle(td).backgroundImage);
    // The colours are roles, resolved on the page: since 1.114.0 the checks hold the role, not the hex it had
    // (muted text was rgb(71, 85, 105), the accent rgb(99, 102, 241), the dark field GitHub's #161b22).
    const role = (v) => page.evaluate((v) => { const s = document.createElement("span"); s.style.color = `var(${v})`;
      document.querySelector('[data-gk-table="inv"]').appendChild(s); const c = getComputedStyle(s).color; s.remove(); return c; }, v);
    const has = (text, colour) => text.includes(colour);
    check("an editable cell shows its dotted line without a hover (muted text colour, --gk-on-surface-variant)",
      /repeating-linear-gradient/.test(await lineOf(cell("inv", "4", "name"))) && has(await lineOf(cell("inv", "4", "name")), await role("--gk-on-surface-variant")),
      await lineOf(cell("inv", "4", "name")));
    check("a cell that cannot be edited has none", (await page.$eval('[data-gk-table="sel"] td[data-label="Amount"]', (td) => getComputedStyle(td).backgroundImage)) === "none");
    await page.hover(cell("inv", "4", "name"));
    check("under the pointer the line turns solid and primary",
      (await lineOf(cell("inv", "4", "name"))).startsWith(`linear-gradient(${await role("--gk-primary")}`), await lineOf(cell("inv", "4", "name")));
    await page.focus(cell("inv", "4", "name"));
    await page.keyboard.press("Shift+Tab");
    await page.keyboard.press("Tab");
    const ring = await page.$eval(cell("inv", "4", "name"), (td) => getComputedStyle(td).outlineStyle + " " + getComputedStyle(td).outlineWidth);
    check("a cell focused with the keyboard has a focus ring", ring === "solid 2px", ring);
    await page.click(cell("inv", "4", "name"));
    const lightField = await page.$eval(cell("inv", "4", "name") + " > input", (i) => {
      const s = getComputedStyle(i);
      return { bg: s.backgroundColor, border: s.borderTopColor, color: s.color };
    });
    check("the field in light mode: surface, a primary border", lightField.bg === await role("--gk-surface") && lightField.border === await role("--gk-primary"), JSON.stringify(lightField));
    await page.keyboard.press("Escape");

    // On the body, where .gk-root sets the light tokens (as ci/browser-karten.js does).
    await page.mouse.move(0, 0);
    await page.evaluate(() => { document.body.dataset.gkMode = "dark"; });
    const darkMuted = await role("--gk-on-surface-variant");
    check("dark mode: the line takes the dark muted colour (--gk-on-surface-variant of the dark ladder)",
      has(await lineOf(cell("inv", "4", "name")), darkMuted) && darkMuted !== "rgb(86, 89, 101)", await lineOf(cell("inv", "4", "name")));
    await page.click(cell("inv", "4", "name"));
    const darkField = await page.$eval(cell("inv", "4", "name") + " > input", (i) => {
      const s = getComputedStyle(i);
      return { bg: s.backgroundColor, border: s.borderTopColor, color: s.color };
    });
    const darkStep = await role("--gk-surface-container-high");
    check("dark mode: the field steps up one container (--gk-surface-container-high), light text, a primary border",
      darkField.bg === darkStep && darkField.bg !== await role("--gk-surface") && darkField.color === await role("--gk-on-surface")
        && darkField.border === await role("--gk-primary"), JSON.stringify({ darkField, darkStep }));
    await page.keyboard.press("Escape");
    await page.evaluate(() => { delete document.body.dataset.gkMode; document.body.classList.add("gk-dark"); });
    await page.click(cell("inv", "4", "name"));
    check("…the same with the class .gk-dark",
      (await page.$eval(cell("inv", "4", "name") + " > input", (i) => getComputedStyle(i).backgroundColor)) === darkStep);
    await page.keyboard.press("Escape");
    await page.evaluate(() => document.body.classList.remove("gk-dark"));

    // ── A card on a phone ──────────────────────────────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    const card = cell("inv", "2", "amount");
    const cardTd = await page.$eval(card, (td) => ({
      display: getComputedStyle(td).display,
      line: getComputedStyle(td).backgroundImage,
      at: getComputedStyle(td).backgroundPosition,
    }));
    check("at 390px the table is cards and an editable field of a card shows its line, in the 3px under it",
      cardTd.display === "flex" && /repeating-linear-gradient/.test(cardTd.line) && cardTd.at === "0px calc(100% + 2px)", JSON.stringify(cardTd));
    await page.click(card);
    const box = await page.$eval(card, (td) => {
      const i = td.querySelector("input").getBoundingClientRect();
      const tr = td.closest("tr").getBoundingClientRect();
      const c = td.getBoundingClientRect();
      const label = parseFloat(getComputedStyle(td, "::before").width);
      const gap = parseFloat(getComputedStyle(td).columnGap) || 0;
      return { left: i.left, right: i.right, width: i.width, trLeft: tr.left, trRight: tr.right, tdRight: c.right, labelRight: c.left + label, gap };
    });
    check("…a click opens the field beside the label, in the place of the value, inside the card",
      box.width > 120 && Math.abs(box.left - (box.labelRight + box.gap)) < 1 && Math.abs(box.right - box.tdRight) < 1 &&
      box.right <= box.trRight + 0.5 && box.labelRight - box.trLeft > 40, JSON.stringify(box));
    await typeInto(card, "12");
    await page.keyboard.press("Enter");
    await settled(card);
    check("…Enter saves it there", (await page.$eval(card, (td) => td.textContent)) === "€12.00");
    const title = cell("inv", "3", "name");
    await page.click(title);
    const tbox = await page.$eval(title, (td) => {
      const i = td.querySelector("input").getBoundingClientRect();
      const c = td.getBoundingClientRect();
      return { w: i.width, cw: c.width };
    });
    check("…the title of a card opens across its width", tbox.w >= tbox.cw - 1 && tbox.w > 150, JSON.stringify(tbox));
    await page.keyboard.press("Escape");

    await page.emulateMedia({ forcedColors: "active" });
    check("forced colours (which drop the line): the cell is underlined instead",
      /underline/.test(await page.$eval(cell("inv", "4", "name"), (td) => getComputedStyle(td).textDecorationLine)));
    await page.emulateMedia({ forcedColors: "none" });
  } catch (err) {
    // A wait that never ends is a failure like any other, said with the case it stopped in.
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
