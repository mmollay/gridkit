#!/usr/bin/env node
/**
 * The kanban board (GridKit\Kanban + js/kanban.js, 1.124.0) in a real browser.
 *
 * `php tests/run.php` runs no JavaScript: the markup the server writes is checked there
 * (tests/kanban.test.php). What the script does with it is checked here — the handles and the words
 * for a screen reader, dragging with the mouse (by the handle and by the card) with the drop line and
 * the framed column, moving with the keyboard (Space, the arrows, Home/End, Escape) and what is
 * spoken, a finger on the handle and on the card, the board scrolling under a card held at its edge,
 * the counts and the limits ('block' refuses, 'warn' marks), the save as JSON, one at a time, and the
 * way back after a 500, a {"ok":false}, no network and a refusal in the queue, the veto of
 * gk:kanbanmove, the "Move to" form sent as JSON, GK.kanban, the page without script and with an old
 * gridkit.js that has no kanban.js (the form posts), 390px without a sideways scroll and with 44px
 * targets, light and dark (both spellings) under the six themes.
 *
 * The moves (POST http://gk.test/move) are answered with page.route(): no server is needed. The page is
 * ci/browser-kanban-fixture.php.
 *
 *     node ci/browser-kanban.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-kanban.js
 *     GK_SHOTS=/some/dir node ci/browser-kanban.js   # also writes screenshots there
 *     GK_VERBOSE=1 node ci/browser-kanban.js          # prints each case as it runs (stderr)
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-kanban-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const fixture = path.join(dir, "fixture.html");
const oldScript = path.join(dir, "old-script.html");
const build = (args) => execFileSync(php, [path.join(__dirname, "browser-kanban-fixture.php"), ...args], { maxBuffer: 64 * 1024 * 1024 }).toString();
fs.writeFileSync(fixture, build([]));
fs.writeFileSync(oldScript, build(["old-script"]));
const SHOTS = process.env.GK_SHOTS || "";

const B = (id) => `[data-gk-kanban="${id}"]`;
const C = (board, id) => `${B(board)} [data-gk-kanban-id="${id}"]`;
const COL = (board, key) => `${B(board)} [data-gk-kanban-column="${key}"]`;
const H = (board, id) => `${C(board, id)} .gk-kanban-handle`;

/** Contrast of two colours as the page paints them (a canvas reads any CSS colour the browser knows). */
const PIXEL = `
  const cv = document.createElement("canvas"); cv.width = cv.height = 1;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  const px = (css, under) => { ctx.clearRect(0, 0, 1, 1); if (under) { ctx.fillStyle = "rgb(" + under.join(",") + ")"; ctx.fillRect(0, 0, 1, 1); }
    ctx.fillStyle = css; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
  const lum = (c) => { const f = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const ground = (el) => { const chain = []; for (let e = el; e; e = e.parentElement) chain.unshift(e);
    let c = [255, 255, 255]; chain.forEach((e) => { const b = getComputedStyle(e).backgroundColor; c = px(b, c); }); return c; };
  const textOn = (el) => ratio(px(getComputedStyle(el).color, ground(el)), ground(el));
`;

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : typeof detail === "string" ? detail : JSON.stringify(detail) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + JSON.stringify(detail)));
  };
  try {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.setDefaultTimeout(8000);

    // ── The endpoint ──────────────────────────────────────────────────────
    const requests = [];
    let moveReply = { status: 200, body: '{"ok":true}' };
    const held = [];
    await context.route("http://gk.test/**", async (route) => {
      const req = route.request();
      const u = new URL(req.url());
      let body = null;
      try { body = JSON.parse(req.postData() || "null"); } catch (e) { body = req.postData(); }
      requests.push({ path: u.pathname, method: req.method(), body, headers: req.headers() });
      const cors = { "Access-Control-Allow-Origin": "*", "Access-Control-Allow-Headers": "*" };
      if (req.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors, body: "" });
      if (u.pathname === "/move") {
        const r = moveReply;
        if (r.hold) await new Promise((res) => held.push(res));
        if (r.abort) return route.abort("failed");
        return route.fulfill({ status: r.status || 200, contentType: "application/json", headers: cors, body: r.body });
      }
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" });
    });

    const arm = () => page.evaluate(() => {
      window._ev = [];
      window._cancel = null;
      ["gk:kanbanmove", "gk:kanbanmoved"].forEach((n) =>
        document.addEventListener(n, (e) => window._ev.push(Object.assign({ type: n }, e.detail))));
      document.addEventListener("gk:kanbanmove", (e) => { if (window._cancel && window._cancel(e.detail)) e.preventDefault(); });
    });
    const fresh = async () => {
      moveReply = { status: 200, body: '{"ok":true}' };
      await page.goto("file://" + fixture);
      await page.waitForSelector(`${B("tickets")}[data-gk-kanban-ready]`);
      await arm();
      requests.splice(0);
    };
    const events = () => page.evaluate(() => window._ev.splice(0));
    const take = () => requests.splice(0).filter((r) => r.method !== "OPTIONS");
    const wait = (ms) => page.waitForTimeout(ms);
    const live = async (board) => { await wait(80); return page.$eval(`${B(board)} > p[aria-live]`, (p) => p.textContent); };
    const ids = (board, key) => page.$$eval(`${COL(board, key)} .gk-kanban-list > .gk-kanban-card`, (cs) => cs.map((c) => c.getAttribute("data-gk-kanban-id")));
    const count = (board, key) => page.$eval(`${COL(board, key)} .gk-kanban-count`, (c) => c.children[0].textContent + "|" + c.children[1].textContent);
    const centre = async (sel) => {
      const l = page.locator(sel).first();
      await l.scrollIntoViewIfNeeded();
      const b = await l.boundingBox();
      return { x: b.x + b.width / 2, y: b.y + b.height / 2, top: b.y, bottom: b.y + b.height, left: b.x, right: b.x + b.width };
    };
    const settle = async () => { for (let i = 0; i < 40; i++) { if (await page.evaluate(() => true)) await wait(25); } };
    // A drag with the mouse from `from` to (x, y), in steps; `during` looks at the page before the release.
    const drag = async (from, to, during) => {
      await page.mouse.move(from.x, from.y);
      await page.mouse.down();
      const steps = 12;
      for (let i = 1; i <= steps; i++) await page.mouse.move(from.x + (to.x - from.x) * i / steps, from.y + (to.y - from.y) * i / steps);
      await wait(60);
      const seen = during ? await during() : null;
      await page.mouse.up();
      await wait(60);
      return seen;
    };
    const dragState = (at) => page.evaluate((at) => {
      const line = document.querySelector(".gk-kanban-drop");
      const ghost = document.querySelector(".gk-kanban-ghost");
      const gr = ghost ? ghost.getBoundingClientRect() : null;
      const lr = line ? line.getBoundingClientRect() : null;
      return {
        // The copy is under the pointer; the line, when it shows, is level with what `at` names (a y in the window).
        ghostUnder: !!gr && !!at && at.x >= gr.left - 2 && at.x <= gr.right + 2 && at.y >= gr.top - 12 && at.y <= gr.bottom + 12,
        lineOff: lr && at && at.lineY != null ? Math.abs(lr.top + lr.height / 2 - at.lineY) : null,
        line: !!line && !line.hidden && getComputedStyle(line).display !== "none",
        lineIn: line ? line.parentElement.getAttribute("data-gk-kanban-column") : null,
        lineH: line ? line.getBoundingClientRect().height : 0,
        ghost: !!ghost, ghostNo: !!ghost && ghost.classList.contains("gk-kanban-ghost-no"), ghostHidden: !!ghost && ghost.getAttribute("aria-hidden") === "true",
        ghostIds: ghost ? ghost.querySelectorAll("[id]").length : -1,
        target: [...document.querySelectorAll(".gk-kanban-target")].map((c) => c.getAttribute("data-gk-kanban-column")),
        targetNo: [...document.querySelectorAll(".gk-kanban-target-no")].map((c) => c.getAttribute("data-gk-kanban-column")),
        dragging: [...document.querySelectorAll(".gk-kanban-dragging")].map((c) => c.getAttribute("data-gk-kanban-id")),
        busy: document.querySelector(".gk-kanban-busy") ? document.querySelector(".gk-kanban-busy").getAttribute("data-gk-kanban") : null,
      };
    }, at || null);

    await fresh();

    // ── What the script adds ──────────────────────────────────────────────
    const setup = await page.evaluate(() => {
      const t = document.querySelector('[data-gk-kanban="tickets"]');
      const h = t.querySelector('[data-gk-kanban-id="T-1"] .gk-kanban-handle');
      const help = h ? document.getElementById(h.getAttribute("aria-describedby")) : null;
      return {
        handles: [...t.querySelectorAll(".gk-kanban-handle")].map((x) => x.closest(".gk-kanban-card").getAttribute("data-gk-kanban-id")),
        name: h && h.getAttribute("aria-label"), type: h && h.type, pressed: h && h.getAttribute("aria-pressed"),
        help: help ? help.textContent : "", helpHidden: help ? help.classList.contains("gk-sr-only") : false,
        live: !!t.querySelector(":scope > p[aria-live=polite]"),
        first: h ? h === h.parentElement.firstElementChild : false,
        region: t.querySelector(".gk-kanban-board").getAttribute("aria-label"),
      };
    });
    check("every card that may move gets a handle — a button named after the card, first in its head; the locked card none",
      JSON.stringify(setup.handles) === JSON.stringify(["T-1", "T-2", "T-3", "T-4", "T-5", "T-7"]) && setup.name === "Move: Printer on floor 2 offline"
      && setup.type === "button" && setup.pressed === "false" && setup.first, setup);
    check("…described by the keys, with a polite live region, inside a region named after the board",
      /Space lifts the card/.test(setup.help) && setup.helpHidden && setup.live && setup.region === "Support tickets", setup);
    const snap = await page.locator(`${B("tickets")} .gk-kanban-board`).ariaSnapshot();
    check("a screen reader finds the region, a heading and a list per column, the count in words",
      /region "Support tickets"/.test(snap) && /heading "New" \[level=3\]/.test(snap) && /list "In progress 2 cards, limit 2"/.test(snap)
      && /list "New 3 cards"/.test(snap), snap.split("\n").slice(0, 14).join(" | "));

    // ── The mouse: by the handle, across columns ──────────────────────────
    let from = await centre(H("tickets", "T-3"));
    let review = await centre(`${C("tickets", "T-6")}`);
    let seen = await drag(from, { x: review.x, y: review.top + 4 }, () => dragState({ x: review.x, y: review.top + 4, lineY: review.top - 4 }));
    check("dragging shows a copy under the pointer (no ids, hidden from a screen reader), a line where the card would go and the framed column",
      seen.ghost && seen.ghostUnder && seen.ghostHidden && seen.ghostIds === 0 && seen.line && seen.lineIn === "review" && seen.lineH >= 3 && seen.lineOff !== null && seen.lineOff <= 3
      && JSON.stringify(seen.target) === '["review"]' && JSON.stringify(seen.dragging) === '["T-3"]' && seen.busy === "tickets", seen);
    await wait(150);
    let after = await dragState();
    check("…and takes all of it away on the release", !after.ghost && !after.line && after.target.length === 0 && after.dragging.length === 0 && !after.busy, after);
    check("a drop across columns puts the card before the card under the pointer's upper half",
      JSON.stringify(await ids("tickets", "review")) === '["T-3","T-6"]' && JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2"]');
    check("the counts follow: New 2, Review 2 of 3 — for the eye and in words",
      (await count("tickets", "new")) === "2|2 cards" && (await count("tickets", "review")) === "2 / 3|2 cards, limit 3");
    let req = take();
    check("the move is posted as JSON {id, column, index} with the headers of moveUrl()",
      req.length === 1 && req[0].method === "POST" && JSON.stringify(req[0].body) === '{"id":"T-3","column":"review","index":0}'
      && req[0].headers["x-csrf-token"] === "tok-kanban" && /application\/json/.test(req[0].headers["content-type"])
      && req[0].headers["x-requested-with"] === "XMLHttpRequest", req);
    let ev = await events();
    check("gk:kanbanmove before, gk:kanbanmoved {ok} after — with board, id, from, to, index, fromIndex",
      ev.length === 2 && ev[0].type === "gk:kanbanmove" && ev[0].board === "tickets" && ev[0].id === "T-3" && ev[0].from === "new" && ev[0].to === "review"
      && ev[0].index === 0 && ev[0].fromIndex === 2 && ev[1].type === "gk:kanbanmoved" && ev[1].ok === true && ev[1].reverted === false, ev);
    check("the live region says where it went, and that it was saved",
      (await live("tickets")) === "Card VPN drops every hour moved to column Review, position 1 of 2. Saved.", await live("tickets"));
    const selectNow = await page.$eval(`${C("tickets", "T-3")} .gk-kanban-move-select`, (s) => s.value);
    check("the card's \"Move to\" list follows its column", selectNow === "review", selectNow);

    // ── The mouse: by the body of a card, within a column; a link is not followed ─
    from = await centre(`${C("tickets", "T-1")} .gk-kanban-text`);
    let below = await centre(C("tickets", "T-2"));
    await drag(from, { x: below.x, y: below.bottom - 3 });
    const selected = await page.evaluate(() => String(getSelection()));
    check("a mouse takes a card by its body too: T-1 after T-2, and no text is selected on the way", JSON.stringify(await ids("tickets", "new")) === '["T-2","T-1"]' && selected === "", selected);
    req = take();
    check("…posted with its index in the column", req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-1","column":"new","index":1}', req);
    await events();
    from = await centre(`${C("tickets", "T-1")} .gk-kanban-card-title`);
    await drag(from, { x: from.x, y: from.y - 120 });
    check("a press on the title link is no drag (the link is a link)", JSON.stringify(await ids("tickets", "new")) === '["T-2","T-1"]' && take().length === 0);
    await page.evaluate(() => { history.replaceState(null, "", location.pathname); });
    // A small move is a click, not a drag.
    from = await centre(H("tickets", "T-2"));
    await page.mouse.move(from.x, from.y); await page.mouse.down(); await page.mouse.move(from.x + 2, from.y + 2);
    const small = await dragState();
    await page.mouse.up();
    check("a press that travels less than 4px is no drag and moves nothing", !small.ghost && small.dragging.length === 0
      && JSON.stringify(await ids("tickets", "new")) === '["T-2","T-1"]' && take().length === 0 && (await events()).length === 0, small);

    // ── A full column in 'block' ──────────────────────────────────────────
    from = await centre(H("tickets", "T-2"));
    let doing = await centre(C("tickets", "T-4"));
    seen = await drag(from, { x: doing.x, y: doing.y }, dragState);
    check("over a full column ('block'): the column and the copy say no, no line", seen.ghostNo && JSON.stringify(seen.targetNo) === '["doing"]' && !seen.line, seen);
    check("…and the drop is refused: no move, no request, no event", JSON.stringify(await ids("tickets", "doing")) === '["T-4","T-5"]' && take().length === 0 && (await events()).length === 0);
    check("…in words", (await live("tickets")) === "Column In progress is full: 2 cards.", await live("tickets"));
    const disabled = await page.$$eval(`${C("tickets", "T-2")} .gk-kanban-move-select option`, (os) => os.map((o) => o.value + (o.disabled ? ":off" : "") + "=" + o.textContent));
    check("the \"Move to\" list offers a full column disabled, marked full", disabled.includes("doing:off=In progress (full)") && disabled.includes("review=Review"), disabled);

    // Escape during a drag.
    from = await centre(H("tickets", "T-2"));
    let done = await centre(C("tickets", "T-7"));
    await page.mouse.move(from.x, from.y); await page.mouse.down();
    for (let i = 1; i <= 8; i++) await page.mouse.move(from.x + (done.x - from.x) * i / 8, from.y + (done.y - from.y) * i / 8);
    await page.keyboard.press("Escape");
    await page.mouse.up();
    await wait(80);
    check("Escape during a drag cancels it: nothing moves, nothing is left behind",
      JSON.stringify(await ids("tickets", "new")) === '["T-2","T-1"]' && take().length === 0 && !(await dragState()).ghost);

    // ── The keyboard ──────────────────────────────────────────────────────
    await events();
    await page.focus(H("tickets", "T-1"));
    await page.keyboard.press("Space");
    check("Space lifts the card: pressed, framed, spoken",
      (await page.$eval(H("tickets", "T-1"), (h) => h.getAttribute("aria-pressed"))) === "true"
      && (await page.$eval(C("tickets", "T-1"), (c) => c.classList.contains("gk-kanban-lifted")))
      && (await live("tickets")) === "Card Printer on floor 2 offline lifted. Column New, position 2 of 2.", await live("tickets"));
    await page.keyboard.press("ArrowUp");
    check("Up moves it within the column, and says where", JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2"]'
      && (await live("tickets")) === "Column New, position 1 of 2.", await live("tickets"));
    await page.keyboard.press("ArrowUp");
    check("…at the top it says it cannot go further", (await live("tickets")) === "It cannot go further this way.");
    await page.keyboard.press("ArrowRight");
    check("Right into a full column is refused and said", JSON.stringify(await ids("tickets", "doing")) === '["T-4","T-5"]'
      && (await live("tickets")) === "Column In progress is full: 2 cards.", await live("tickets"));
    await page.keyboard.press("ArrowLeft");
    check("Left at the first column: the edge", (await live("tickets")) === "It cannot go further this way.");
    await page.keyboard.press("Escape");
    await events();
    // A full column still lets its own cards change places.
    await page.focus(H("tickets", "T-4"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("Space");
    await wait(200);
    req = take();
    check("in a full column ('block') a card of its own still changes places", JSON.stringify(await ids("tickets", "doing")) === '["T-5","T-4"]'
      && req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-4","column":"doing","index":1}', req);
    await events();
    from = await centre(H("tickets", "T-4"));
    const t5 = await centre(C("tickets", "T-5"));
    seen = await drag(from, { x: t5.x, y: t5.top + 4 }, dragState);
    await wait(200);
    req = take();
    check("…and by a drop: no refusal over its own full column", !seen.ghostNo && seen.targetNo.length === 0 && seen.line
      && JSON.stringify(await ids("tickets", "doing")) === '["T-4","T-5"]' && req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-4","column":"doing","index":0}', { seen, req });
    await events();
    await page.focus(H("tickets", "T-1"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowUp");
    check("nothing is posted while the card is lifted", take().length === 0 && (await events()).length === 0);
    const focusKept = await page.evaluate(() => document.activeElement && document.activeElement.classList.contains("gk-kanban-handle")
      && document.activeElement.closest(".gk-kanban-card").getAttribute("data-gk-kanban-id"));
    check("the focus stays on the handle through every step", focusKept === "T-1", focusKept);
    await page.keyboard.press("Escape");
    check("Escape gives it back and says so", JSON.stringify(await ids("tickets", "new")) === '["T-2","T-1"]'
      && (await page.$eval(H("tickets", "T-1"), (h) => h.getAttribute("aria-pressed"))) === "false"
      && (await live("tickets")) === "Cancelled. Card Printer on floor 2 offline is back in column New, position 2 of 2." && take().length === 0, await live("tickets"));

    // A card from Done, two columns to the left, down to the end.
    await page.focus(H("tickets", "T-7"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowLeft");
    check("Left goes to the column beside it, at the same height", JSON.stringify(await ids("tickets", "review")) === '["T-7","T-3","T-6"]'
      && (await live("tickets")) === "Column Review, position 1 of 3.", await live("tickets"));
    await page.keyboard.press("End");
    check("End: the end of the column", JSON.stringify(await ids("tickets", "review")) === '["T-3","T-6","T-7"]');
    await page.keyboard.press("Home");
    check("Home: the top of the column", JSON.stringify(await ids("tickets", "review")) === '["T-7","T-3","T-6"]');
    await page.keyboard.press("End");
    await page.keyboard.press("ArrowUp");
    check("End, then Up", JSON.stringify(await ids("tickets", "review")) === '["T-3","T-7","T-6"]');
    check("the counts follow every step (Review 3 of 3, Done empty, its words shown)",
      (await count("tickets", "review")) === "3 / 3|3 cards, limit 3" && (await count("tickets", "done")) === "0|0 cards"
      && (await page.$eval(`${COL("tickets", "done")} .gk-kanban-empty`, (p) => !p.hidden && getComputedStyle(p).display !== "none")));
    await page.keyboard.press("Space");
    await wait(200);
    req = take();
    check("Space puts it down: one request, from where it was lifted to where it lies",
      req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-7","column":"review","index":1}', req);
    ev = await events();
    check("…with both events", ev.length === 2 && ev[0].from === "done" && ev[0].to === "review" && ev[0].index === 1 && ev[0].fromIndex === 0 && ev[1].ok === true, ev);
    check("…and the words", (await live("tickets")) === "Card Wi-Fi in the meeting room moved to column Review, position 2 of 3. Saved.", await live("tickets"));
    const nowFull = await page.$$eval(`${C("tickets", "T-1")} .gk-kanban-move-select option`, (os) => os.map((o) => o.value + (o.disabled ? ":off" : "") + "=" + o.textContent));
    check("the \"Move to\" lists follow: Review, full now, is disabled and marked; Done, empty now, is open",
      nowFull.includes("review:off=Review (full)") && nowFull.includes("done=Done"), nowFull);
    // Enter lifts as well; Tab away gives the card back.
    await page.focus(H("tickets", "T-7"));
    await page.keyboard.press("Enter");
    await page.keyboard.press("ArrowRight");
    await page.focus("#after");
    await wait(50);
    check("Enter lifts too; leaving the handle gives the card back", JSON.stringify(await ids("tickets", "review")) === '["T-3","T-7","T-6"]'
      && (await page.$eval(H("tickets", "T-7"), (h) => h.getAttribute("aria-pressed"))) === "false" && take().length === 0);
    // The page says no.
    await page.evaluate(() => { window._cancel = (d) => d.id === "T-7"; });
    await page.focus(H("tickets", "T-7"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("Space");
    check("gk:kanbanmove vetoed: the card goes back, nothing is posted, it is said",
      JSON.stringify(await ids("tickets", "review")) === '["T-3","T-7","T-6"]' && take().length === 0
      && (await live("tickets")) === "Cancelled. Card Wi-Fi in the meeting room is back in column Review, position 2 of 3.", await live("tickets"));
    from = await centre(H("tickets", "T-7"));
    done = await centre(`${COL("tickets", "done")} .gk-kanban-empty`);
    await drag(from, done);
    check("…a vetoed drop too", JSON.stringify(await ids("tickets", "review")) === '["T-3","T-7","T-6"]' && take().length === 0);
    ev = await events();
    check("…and no gk:kanbanmoved for a vetoed move", ev.length === 2 && ev.every((e) => e.type === "gk:kanbanmove"), ev);
    await page.evaluate(() => { window._cancel = null; });

    // ── Saving fails: the way back ────────────────────────────────────────
    await fresh();
    moveReply = { status: 500, body: '{"ok":false,"message":"The board is locked by an audit."}' };
    from = await centre(H("tickets", "T-2"));
    done = await centre(C("tickets", "T-7"));
    await drag(from, { x: done.x, y: done.bottom - 2 });
    await wait(300);
    check("a 500: the card is back where the server last agreed", JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2","T-3"]'
      && JSON.stringify(await ids("tickets", "done")) === '["T-7"]' && (await count("tickets", "new")) === "3|3 cards");
    const toast = await page.evaluate(() => [...document.querySelectorAll(".gk-toast")].map((t) => t.textContent).join(" | "));
    check("…the answer's message in a toast", /The board is locked by an audit\./.test(toast), toast);
    ev = await events();
    check("…and gk:kanbanmoved {ok: false, reverted: true}", ev.length === 2 && ev[1].type === "gk:kanbanmoved" && ev[1].ok === false && ev[1].reverted === true && ev[1].id === "T-2", ev);
    check("…the live region says so", (await live("tickets")) === "The board is locked by an audit.", await live("tickets"));
    take();

    // A queue: the first move waits, the second is made, the first fails — both go back, the second is never sent.
    moveReply = { status: 409, body: '{"ok":false}', hold: true };
    from = await centre(H("tickets", "T-1"));
    done = await centre(C("tickets", "T-7"));
    await drag(from, { x: done.x, y: done.bottom - 2 });
    await wait(100);
    from = await centre(H("tickets", "T-3"));
    let rev = await centre(C("tickets", "T-6"));
    await drag(from, { x: rev.x, y: rev.bottom - 2 });
    await wait(100);
    const mid = { done: await ids("tickets", "done"), review: await ids("tickets", "review"), sent: requests.filter((r) => r.method === "POST").length };
    check("a second move waits for the first: both shown, one on its way", JSON.stringify(mid.done) === '["T-7","T-1"]' && JSON.stringify(mid.review) === '["T-6","T-3"]' && mid.sent === 1, mid);
    held.splice(0).forEach((r) => r());
    await wait(400);
    req = take();
    check("the first is refused ({\"ok\":false}): both go back, the second is never sent",
      JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2","T-3"]' && JSON.stringify(await ids("tickets", "review")) === '["T-6"]'
      && JSON.stringify(await ids("tickets", "done")) === '["T-7"]' && req.length === 1, { req, n: await ids("tickets", "new") });
    ev = (await events()).filter((e) => e.type === "gk:kanbanmoved");
    check("…each with gk:kanbanmoved reverted", ev.length === 2 && ev.every((e) => e.ok === false && e.reverted === true) && ev[0].id === "T-1" && ev[1].id === "T-3", ev);
    const toast2 = await page.evaluate(() => [...document.querySelectorAll(".gk-toast")].map((t) => t.textContent).join(" | "));
    check("…a refusal without a message reads the catalogue's words", /The move could not be saved\. The card is back in its place\./.test(toast2), toast2);
    // No network at all.
    moveReply = { abort: true };
    await page.focus(H("tickets", "T-3"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("Space");
    await wait(300);
    check("no network: the card goes back too", JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2","T-3"]' && JSON.stringify(await ids("tickets", "doing")) === '["T-4","T-5"]');
    const focusBack = await page.evaluate(() => document.activeElement && document.activeElement.closest(".gk-kanban-card") && document.activeElement.closest(".gk-kanban-card").getAttribute("data-gk-kanban-id"));
    check("…and the focus stays on its handle", focusBack === "T-3", focusBack);
    take(); await events();
    // A 200 that says no.
    moveReply = { status: 200, body: '{"ok":false,"message":"Someone else moved it first."}' };
    await page.focus(H("tickets", "T-3"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowUp");
    await page.keyboard.press("Space");
    await wait(300);
    ev = (await events()).filter((e) => e.type === "gk:kanbanmoved");
    check("a 200 whose JSON says {\"ok\": false} is a refusal: back, with its message", JSON.stringify(await ids("tickets", "new")) === '["T-1","T-2","T-3"]'
      && ev.length === 1 && ev[0].reverted === true && (await live("tickets")) === "Someone else moved it first.", { ev, live: await live("tickets") });
    take();

    // ── "Move to", sent as JSON ───────────────────────────────────────────
    moveReply = { status: 200, body: '{"ok":true}' };
    await page.click(`${C("tickets", "T-2")} .gk-kanban-move-toggle`);
    await page.selectOption(`${C("tickets", "T-2")} .gk-kanban-move-select`, "done");
    await page.click(`${C("tickets", "T-2")} .gk-kanban-move-button`);
    await wait(250);
    req = take();
    check("the \"Move to\" form, with the script: no page load, the card at the end of the column, JSON posted",
      page.url().startsWith("file:") && JSON.stringify(await ids("tickets", "done")) === '["T-7","T-2"]' && req.length === 1
      && JSON.stringify(req[0].body) === '{"id":"T-2","column":"done","index":1}', req);
    const closed = await page.evaluate(() => {
      const d = document.querySelector('[data-gk-kanban-id="T-2"] details.gk-kanban-move');
      return { open: d.open, focus: document.activeElement === d.querySelector("summary") };
    });
    check("…it closes and the focus is on its toggle", !closed.open && closed.focus, closed);
    await events();

    // ── 'warn': a full column takes the card and is marked ────────────────
    from = await centre(H("plain", "c2"));
    let live1 = await centre(C("plain", "c3"));
    await drag(from, { x: live1.x, y: live1.bottom - 2 });
    await wait(80);
    const over = await page.evaluate(() => {
      const col = document.querySelector('[data-gk-kanban="plain"] [data-gk-kanban-column="live"]');
      const note = col.querySelector(".gk-kanban-over-note");
      return { cls: col.classList.contains("gk-kanban-over"), note: !note.hidden && getComputedStyle(note).display !== "none", text: note.textContent };
    });
    check("'warn': the move into a full column is made, the column marked over its limit — in words",
      JSON.stringify(await ids("plain", "live")) === '["c3","c2"]' && over.cls && over.note && over.text === "Over limit" && (await count("plain", "live")) === "2 / 1|2 cards, limit 1", over);
    check("…and said", (await live("plain")) === "Card Black Friday moved to column Live, position 2 of 2. Column Live is over its limit: 2 of 1.", await live("plain"));
    ev = await events();
    check("without a URL: nothing is posted, gk:kanbanmoved {ok} at once", take().length === 0 && ev.length === 2 && ev[1].ok === true, ev);
    await page.focus(H("plain", "c2"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("Space");
    const unmarked = await page.$eval(COL("plain", "live"), (c) => c.classList.contains("gk-kanban-over") || !c.querySelector(".gk-kanban-over-note").hidden
      || getComputedStyle(c.querySelector(".gk-kanban-over-note")).display !== "none");
    check("…and the mark goes when the column is back within its limit", !unmarked && JSON.stringify(await ids("plain", "ended")) === '["c4","c2"]');
    check("compact(): a card's text is left out", (await page.$$(`${C("plain", "c1")} .gk-kanban-text`)).length === 0);
    await events();

    // ── The board scrolls under a card held at its edge ───────────────────
    await page.setViewportSize({ width: 900, height: 900 });
    const wb = await centre(`${B("wide")} .gk-kanban-board`);
    from = await centre(H("wide", "o0"));
    const sl0 = await page.$eval(`${B("wide")} .gk-kanban-board`, (b) => b.scrollLeft);
    await page.mouse.move(from.x, from.y); await page.mouse.down();
    for (let i = 1; i <= 10; i++) await page.mouse.move(from.x + (wb.right - 10 - from.x) * i / 10, from.y + 10);
    await wait(900);
    const sl1 = await page.$eval(`${B("wide")} .gk-kanban-board`, (b) => b.scrollLeft);
    const pageX = await page.evaluate(() => window.scrollX);
    await page.mouse.up();
    await wait(100);
    const landed = await page.evaluate(() => GK.kanban.cards("wide"));
    const where0 = Object.keys(landed).find((k) => landed[k].includes("o0"));
    check("held at the right edge of the board, the board scrolls sideways (the page does not), and the card lands in a later column",
      sl1 > sl0 + 200 && pageX === 0 && where0 && where0 !== "received", { sl0, sl1, pageX, where0 });
    await page.setViewportSize({ width: 1280, height: 900 });
    await events();

    // ── GK.kanban ─────────────────────────────────────────────────────────
    await fresh();
    const api = await page.evaluate(async () => {
      const out = {};
      out.cards = GK.kanban.cards("tickets");
      out.col = GK.kanban.cards("tickets", "doing");
      out.columns = GK.kanban.columns("tickets").map((c) => [c.key, c.label, c.count, c.limit, c.over].join(":"));
      out.byEl = GK.kanban.cards(document.querySelector('[data-gk-kanban="tickets"] .gk-kanban-card'), "done");
      out.quiet = await GK.kanban.move("tickets", "T-7", "new", 0, { save: false });
      out.events = window._ev.length;
      out.saved = await GK.kanban.move("tickets", "T-7", "review", null);
      out.full = await GK.kanban.move("tickets", "T-1", "doing", 0);
      out.locked = await GK.kanban.move("tickets", "T-6", "done", 0);
      out.unknown = await GK.kanban.move("tickets", "nope", "done", 0);
      const li = document.querySelector('[data-gk-kanban-id="T-3"]').cloneNode(true);
      li.setAttribute("data-gk-kanban-id", "T-9");
      li.querySelectorAll(".gk-kanban-handle").forEach((h) => h.remove());
      li._gkKanban = undefined;
      document.querySelector('[data-gk-kanban="tickets"] [data-gk-kanban-column="done"] .gk-kanban-list').appendChild(li);
      GK.kanban.refresh("tickets");
      out.added = GK.kanban.columns("tickets").find((c) => c.key === "done").count;
      out.addedHandle = !!li.querySelector(".gk-kanban-handle");
      return out;
    });
    req = take();
    check("GK.kanban.cards() and columns() read the board, by its id or any element in it",
      JSON.stringify(api.cards) === '{"new":["T-1","T-2","T-3"],"doing":["T-4","T-5"],"review":["T-6"],"done":["T-7"]}'
      && JSON.stringify(api.col) === '["T-4","T-5"]' && JSON.stringify(api.byEl) === '["T-7"]'
      && api.columns.join(",") === "new:New:3::false,doing:In progress:2:2:false,review:Review:1:3:false,done:Done:1::false", api);
    check("GK.kanban.move(…, {save: false}) only puts the card there: no event, no request",
      api.quiet && api.quiet.ok === true && api.events === 0, api);
    check("GK.kanban.move() saves like a person's move and resolves after the answer; a full column, a locked card and an unknown id move nothing",
      api.saved && api.saved.ok === true && req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-7","column":"review","index":1}'
      && api.full === false && api.locked === false && api.unknown === false, { api, req });
    check("GK.kanban.refresh() gives a card the page added its handle and counts it", api.added === 1 && api.addedHandle, api);

    // ── A finger ─────────────────────────────────────────────────────────
    const touchCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    await touchCtx.route("http://gk.test/**", (route) => {
      const r = route.request();
      if (r.method() === "OPTIONS") return route.fulfill({ status: 204, headers: { "Access-Control-Allow-Origin": "*", "Access-Control-Allow-Headers": "*" }, body: "" });
      requests.push({ path: new URL(r.url()).pathname, method: r.method(), body: JSON.parse(r.postData() || "null"), headers: r.headers() });
      return route.fulfill({ status: 200, contentType: "application/json", headers: { "Access-Control-Allow-Origin": "*" }, body: '{"ok":true}' });
    });
    const tp = await touchCtx.newPage();
    tp.on("pageerror", (e) => pageErrors.push(e.message));
    await tp.goto("file://" + fixture);
    await tp.waitForSelector(`${B("tickets")}[data-gk-kanban-ready]`);
    take();
    const cdp = await touchCtx.newCDPSession(tp);
    const touchDrag = async (fromSel, to) => {
      const l = tp.locator(fromSel).first();
      await l.scrollIntoViewIfNeeded();
      const b = await l.boundingBox();
      const f = { x: b.x + b.width / 2, y: b.y + b.height / 2 };
      await cdp.send("Input.dispatchTouchEvent", { type: "touchStart", touchPoints: [{ x: f.x, y: f.y }] });
      for (let i = 1; i <= 12; i++) {
        await cdp.send("Input.dispatchTouchEvent", { type: "touchMove", touchPoints: [{ x: f.x + (to.x - f.x) * i / 12, y: f.y + (to.y - f.y) * i / 12 }] });
        await tp.waitForTimeout(16);
      }
      await tp.waitForTimeout(60);
      await cdp.send("Input.dispatchTouchEvent", { type: "touchEnd", touchPoints: [] });
      await tp.waitForTimeout(150);
    };
    const tIds = (key) => tp.$$eval(`${COL("tickets", key)} .gk-kanban-list > .gk-kanban-card`, (cs) => cs.map((c) => c.getAttribute("data-gk-kanban-id")));
    const t3 = await tp.locator(C("tickets", "T-3")).boundingBox();
    await touchDrag(H("tickets", "T-1"), { x: t3.x + t3.width / 2, y: t3.y + t3.height - 3 });
    req = take();
    check("a finger on the handle drags the card (here: below T-3) and it is saved",
      JSON.stringify(await tIds("new")) === '["T-2","T-3","T-1"]' && req.length === 1 && JSON.stringify(req[0].body) === '{"id":"T-1","column":"new","index":2}', { ids: await tIds("new"), req });
    const t2 = await tp.locator(C("tickets", "T-2")).boundingBox();
    await touchDrag(`${C("tickets", "T-3")} .gk-kanban-due`, { x: t2.x + t2.width / 2, y: t2.y + 4 });
    check("a finger on the body of a card does not drag it (the board scrolls)", JSON.stringify(await tIds("new")) === '["T-2","T-3","T-1"]' && take().length === 0);
    const targets = await tp.evaluate(() => {
      const size = (sel) => { const el = document.querySelector(sel); const r = el.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; };
      return {
        handle: size('[data-gk-kanban-id="T-1"] .gk-kanban-handle'),
        title: Math.max(size('[data-gk-kanban-id="T-3"] a.gk-kanban-card-title')[1] * 0, parseFloat(getComputedStyle(document.querySelector('[data-gk-kanban-id="T-3"] a.gk-kanban-card-title')).minHeight) || 0),
        move: size('[data-gk-kanban-id="T-1"] .gk-kanban-move-toggle')[1],
        add: size('[data-gk-kanban="tickets"] .gk-kanban-add')[1],
        page: document.documentElement.scrollWidth,
      };
    });
    check("on a touch screen every target of a card is 44px: handle, title link, \"Move\", \"Add card\"",
      targets.handle[0] >= 44 && targets.handle[1] >= 44 && targets.title >= 44 && targets.move >= 44 && targets.add >= 44 && targets.page <= 390, targets);
    await touchCtx.close();

    // ── 390px ─────────────────────────────────────────────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    await wait(100);
    const phone = await page.evaluate(() => {
      const b = document.querySelector('[data-gk-kanban="tickets"] .gk-kanban-board');
      const col = b.querySelector(".gk-kanban-column");
      const cs = getComputedStyle(b);
      return {
        page: document.documentElement.scrollWidth, boardScrolls: b.scrollWidth > b.clientWidth, overflow: cs.overflowX,
        snap: cs.scrollSnapType, align: getComputedStyle(col).scrollSnapAlign,
        share: col.getBoundingClientRect().width / b.clientWidth, boardRight: b.getBoundingClientRect().right,
      };
    });
    check("390px: the page never scrolls sideways — the board does, in lanes of 85% that snap, the next one showing",
      phone.page <= 390 && phone.boardScrolls && phone.overflow === "auto" && /x mandatory/.test(phone.snap) && phone.align.startsWith("start")
      && phone.share > 0.78 && phone.share < 0.9 && phone.boardRight <= 390, phone);
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, "kanban-390.png"), fullPage: true });
    {
      const board = `${B("tickets")} .gk-kanban-board`;
      const bb = await centre(board);
      const f = await centre(H("tickets", "T-2"));
      const before = await page.$eval(board, (b) => b.scrollLeft);
      await page.mouse.move(f.x, f.y); await page.mouse.down();
      for (let i = 1; i <= 10; i++) await page.mouse.move(f.x + (bb.right - 8 - f.x) * i / 10, f.y + 4);
      await wait(900);
      const held = await page.$eval(board, (b) => b.scrollLeft);
      await page.keyboard.press("Escape");
      await page.mouse.up();
      await wait(400);
      check("390px: a card held at the edge scrolls the snapping lanes too (no snapping while it is held)", held > before + 150, { before, held });
      await page.$eval(board, (b) => { b.scrollLeft = 0; });
    }
    // Sideways at every width, from 320 to 1600.
    const sideways = [];
    for (const w of [320, 360, 414, 600, 601, 768, 1024, 1280, 1600]) {
      await page.setViewportSize({ width: w, height: 900 });
      const sw = await page.evaluate(() => document.documentElement.scrollWidth);
      if (sw > w) sideways.push(w + ":" + sw);
    }
    check("no width from 320 to 1600 scrolls the page sideways", sideways.length === 0, sideways);
    await page.setViewportSize({ width: 1280, height: 900 });

    // ── Contrast: light, dark in both spellings, six themes ──────────────
    await page.evaluate(() => GK.kanban.move("plain", "c2", "live", null, { save: false }));
    const measure = () => page.evaluate(new Function(`${PIXEL}
      const q = (s) => document.querySelector(s);
      const ui = (el) => ratio(px(getComputedStyle(el).color, ground(el)), ground(el));
      const card = (id) => q('[data-gk-kanban-id="' + id + '"]');
      const token = (el, name) => { const p = document.createElement("span"); p.style.color = "var(" + name + ")"; el.appendChild(p);
        const c = getComputedStyle(p).color; p.remove(); return px(c); };
      return {
        heading: textOn(q('[data-gk-kanban-column="new"] .gk-kanban-title')),
        desc: textOn(q('[data-gk-kanban-column="new"] .gk-kanban-desc')),
        count: textOn(q('[data-gk-kanban-column="new"] .gk-kanban-count')),
        overCount: textOn(q('[data-gk-kanban="plain"] [data-gk-kanban-column="live"] .gk-kanban-count')),
        overNote: textOn(q('[data-gk-kanban="plain"] [data-gk-kanban-column="live"] .gk-kanban-over-note')),
        title: textOn(card("T-1").querySelector(".gk-kanban-card-title")),
        text: textOn(card("T-1").querySelector(".gk-kanban-text")),
        overdue: textOn(card("T-1").querySelector(".gk-kanban-overdue time")),
        today: textOn(card("T-2").querySelector(".gk-kanban-due-today time")),
        due: textOn(card("T-3").querySelector(".gk-kanban-due time")),
        cardcount: textOn(card("T-1").querySelector(".gk-kanban-cardcount")),
        avatar: textOn(card("T-1").querySelector(".gk-kanban-avatar")),
        move: textOn(card("T-1").querySelector(".gk-kanban-move-word")),
        add: textOn(q(".gk-kanban-add")),
        empty: textOn(q('[data-gk-kanban="tickets"] .gk-kanban-empty') || q(".gk-kanban-empty")),
        handle: ui(card("T-1").querySelector(".gk-kanban-handle")),
        ring: ratio(token(card("T-1"), "--gk-primary"), ground(card("T-1"))),
        cardEdge: lum(ground(card("T-1"))) > lum(ground(q('[data-gk-kanban-column="new"]'))) ? ratio(ground(card("T-1")), ground(q('[data-gk-kanban-column="new"]'))) : 0,
        overdueGround: ratio(ground(card("T-1").querySelector(".gk-kanban-overdue")), ground(card("T-1"))),
      };`));
    // The empty words of a column are shown only in an empty one: make one for the measure.
    await page.evaluate(() => { const p = document.querySelector('[data-gk-kanban="tickets"] [data-gk-kanban-column="done"] .gk-kanban-empty'); p.hidden = false; });
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
          // A card is raised: lighter than its column — in dark a full step (surface-container-high), not the surface.
          const edge = k === "cardEdge" && mode !== "light" ? 1.08 : 1.02;
          if (k === "cardEdge" || k === "overdueGround") { if (!(v > edge)) low.push(`${theme || "plain"}/${mode} ${k} ${v.toFixed(3)}`); continue; }
          const need = ["handle", "ring"].includes(k) ? 3 : 4.5;
          if (!(v >= need)) low.push(`${theme || "plain"}/${mode} ${k} ${v.toFixed(2)}`);
        }
        if (SHOTS && !theme && mode !== "dark-class") await page.screenshot({ path: path.join(SHOTS, `kanban-1280-${mode}.png`), clip: { x: 0, y: 0, width: 1280, height: 900 } });
      }
    }
    check("contrast in 21 looks (plain + six themes × light, dark, .gk-dark): text 4.5:1, handle and focus ring 3:1, a card lighter than its column, overdue on a ground of its own",
      low.length === 0, low.join("; "));
    if (process.env.GK_VERBOSE) console.error("        lowest: " + Object.entries(lowest).map(([k, v]) => k + " " + v.toFixed(2)).join(", "));
    await page.evaluate(() => { document.body.removeAttribute("data-gk-theme"); document.body.removeAttribute("data-gk-mode"); document.body.classList.remove("gk-dark"); });

    // ── Without a script ─────────────────────────────────────────────────
    const plainCtx = await browser.newContext({ viewport: { width: 1280, height: 900 }, javaScriptEnabled: false });
    const formPosts = [];
    await plainCtx.route("http://gk.test/**", (route) => {
      const r = route.request();
      formPosts.push({ method: r.method(), path: new URL(r.url()).pathname, body: r.postData(), type: r.headers()["content-type"] || "" });
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" });
    });
    const np = await plainCtx.newPage();
    await np.goto("file://" + fixture);
    const nos = await np.evaluate(() => ({
      hidden: [...document.querySelectorAll(".gk-kanban-card")].filter((c) => c.offsetParent === null).length,
      cards: document.querySelectorAll(".gk-kanban-card").length,
      handles: document.querySelectorAll(".gk-kanban-handle").length,
      forms: document.querySelectorAll('[data-gk-kanban="tickets"] .gk-kanban-move-form').length,
      link: document.querySelector('[data-gk-kanban-id="T-1"] a.gk-kanban-card-title').getAttribute("href"),
      add: document.querySelector('[data-gk-kanban-column="review"] .gk-kanban-add').getAttribute("href"),
      locked: document.querySelectorAll('[data-gk-kanban-id="T-6"] .gk-kanban-move').length,
    }));
    check("without script every card is in view, with its link, no handles, a \"Move to\" form on each card that may move",
      nos.hidden === 0 && nos.cards === 19 && nos.handles === 0 && nos.forms === 6 && nos.link === "#ticket-T-1" && nos.locked === 0
      && nos.add === "http://gk.test/new?status=review", nos);
    await np.click(`${C("tickets", "T-1")} .gk-kanban-move-toggle`);
    await np.selectOption(`${C("tickets", "T-1")} .gk-kanban-move-select`, "done");
    await np.click(`${C("tickets", "T-1")} .gk-kanban-move-button`);
    await np.waitForURL(/gk\.test\/move/);
    const sent = formPosts.find((p) => p.path === "/move");
    check("…and the form posts id, the hidden fields and the column — a plain form, no index",
      !!sent && sent.method === "POST" && /application\/x-www-form-urlencoded/.test(sent.type)
      && new URLSearchParams(sent.body).toString() === "id=T-1&_token=tok-kanban&column=done", sent);
    await plainCtx.close();

    // ── An old gridkit.js without kanban.js ───────────────────────────────
    const oldCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const oldPosts = [];
    await oldCtx.route("http://gk.test/**", (route) => {
      const r = route.request();
      oldPosts.push({ method: r.method(), path: new URL(r.url()).pathname, body: r.postData() });
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" });
    });
    const op = await oldCtx.newPage();
    op.on("pageerror", (e) => pageErrors.push("old-script: " + e.message));
    await op.goto("file://" + oldScript);
    const oldState = await op.evaluate(() => ({ handles: document.querySelectorAll(".gk-kanban-handle").length, ready: !!document.querySelector("[data-gk-kanban-ready]"), gk: !!window.GK, api: !!(window.GK && GK.kanban) }));
    await op.click(`${C("tickets", "T-4")} .gk-kanban-move-toggle`);
    await op.selectOption(`${C("tickets", "T-4")} .gk-kanban-move-select`, "review");
    await op.click(`${C("tickets", "T-4")} .gk-kanban-move-button`);
    await op.waitForURL(/gk\.test\/move/);
    check("a page whose gridkit.js has no kanban.js: no handles, and the \"Move to\" form posts as a plain form",
      oldState.handles === 0 && !oldState.ready && oldState.gk && !oldState.api && oldPosts.length === 1
      && new URLSearchParams(oldPosts[0].body).get("column") === "review" && new URLSearchParams(oldPosts[0].body).get("id") === "T-4", { oldState, oldPosts });
    await oldCtx.close();
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
