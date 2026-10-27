#!/usr/bin/env node
/**
 * The sortable container in a real browser (1.97.0).
 *
 * `php tests/run.php` reads the script and the stylesheet; it cannot drag anything. What only a browser
 * shows is held here, on real markup (ci/browser-fixture.php --sortierbar) that this file serves over
 * http, because a save needs an address and a server that answers it: the handles and their names, a
 * drag with the mouse and with a finger, the keyboard in a list and in a grid, what is sent to the
 * server (method, header, body), what the person sees when the server says yes, no or nothing, the
 * second drop that cancels the first request, the page that scrolls at the window's edge, the
 * containers that arrive later, reduced motion, light and both dark spellings, and a phone.
 *
 *     node ci/browser-sortierbar.js
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-sortierbar.js
 *     GK_SHOTS=/some/dir node ci/browser-sortierbar.js      # also saves pictures to look at
 *     GK_ICONFONT=/path/MaterialIcons.woff2 ...             # the icon font, for the pictures (none is shipped)
 *
 * Like ci/browser.js it stays outside the suite: GridKit has no dependencies. Exit code 0 when every
 * case holds. The server it starts listens on 127.0.0.1 on a free port, for the length of the run.
 */
const { execFileSync } = require("child_process");
const fs = require("fs");
const http = require("http");
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
const html = execFileSync(php, [path.join(__dirname, "browser-fixture.php"), "--sortierbar"], { maxBuffer: 64 * 1024 * 1024 });

// ── The server: the page, and an address that says yes, no, "not saved", nothing, or takes its time ──
const reqs = [];
let behave = "ok";   // ok | error | refuse | drop | slow
const server = http.createServer((req, res) => {
  if (req.method === "GET") {
    res.writeHead(req.url === "/" ? 200 : 404, { "Content-Type": "text/html; charset=utf-8" });
    res.end(req.url === "/" ? html : "");
    return;
  }
  let body = "";
  req.on("data", (c) => { body += c; });
  req.on("end", () => {
    const rec = { url: req.url, method: req.method, headers: req.headers, body, aborted: false, answered: false };
    reqs.push(rec);
    res.on("close", () => { if (!res.writableFinished) rec.aborted = true; });
    const answer = (status, text) => { if (res.destroyed) return; rec.answered = true; res.writeHead(status, { "Content-Type": "application/json" }); res.end(text); };
    if (behave === "drop") { req.socket.destroy(); return; }
    if (behave === "error") return answer(500, '{"error":"boom"}');
    if (behave === "refuse") return answer(200, '{"ok":false}');
    if (behave === "slow" && reqs.length === 1) { setTimeout(() => answer(200, '{"ok":true}'), 1500); return; }
    answer(200, '{"ok":true}');
  });
});

const results = [];
const pageErrors = [];
const check = (name, ok, why) => { results.push({ name, ok: !!ok, why: ok ? "" : why === undefined ? "" : typeof why === "string" ? why : JSON.stringify(why) }); };
const near = (a, b, tol = 1) => Math.abs(a - b) <= tol;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const waitFor = async (fn, ms = 5000) => { const end = Date.now() + ms; while (Date.now() < end) { if (await fn()) return true; await sleep(25); } return false; };

const shot = async (page, name) => {
  if (!SHOTS) return;
  fs.mkdirSync(SHOTS, { recursive: true });
  await page.screenshot({ path: path.join(SHOTS, name + ".png") });
};
const settle = (page) => page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
const mode = (page, m) => page.evaluate((m) => {
  document.body.classList.toggle("gk-dark", m === "dark-class");
  document.body.setAttribute("data-gk-mode", m === "dark-class" ? "light" : m);
  void document.body.offsetWidth;
  document.getAnimations().filter((a) => isFinite(a.effect.getComputedTiming().endTime)).forEach((a) => a.finish());
}, m);

/** The ids of the entries of a container, in the order the page shows them. */
const order = (page, sel) => page.evaluate((s) => [...document.querySelector(s).children].filter((e) => e.hasAttribute("data-gk-sort-id")).map((e) => e.getAttribute("data-gk-sort-id")), sel);
/** The centre of the handle of an entry (after it has been brought into the window). */
const handleAt = async (page, sel, id) => {
  const h = page.locator(`${sel} > [data-gk-sort-id="${id}"] > .gk-sort-griff`);
  await h.scrollIntoViewIfNeeded();
  const b = await h.boundingBox();
  return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
};
/** The centre of an entry. */
const entryAt = async (page, sel, id) => {
  const e = page.locator(`${sel} > [data-gk-sort-id="${id}"]`);
  const b = await e.boundingBox();
  return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
};
const events = (page) => page.evaluate(() => window.__ev.splice(0));
const live = (page, sel) => page.evaluate((s) => document.querySelector(s).nextElementSibling.querySelector("[aria-live]").textContent, sel);

/** Press at `from`, pull past the threshold and on to `to`; with `hold` the button stays down. */
const pull = async (page, from, to, hold = false) => {
  await page.mouse.move(from.x, from.y);
  await page.mouse.down();
  await page.mouse.move(from.x + 3, from.y + (to.y >= from.y ? 8 : -8), { steps: 2 });
  await page.mouse.move(to.x, to.y, { steps: 14 });
  await settle(page);
  if (!hold) await page.mouse.up();
};

/** Contrast of two colours as the page paints them (a canvas reads any CSS colour the browser knows). */
const PIXEL = `
  const cv = document.createElement("canvas"); cv.width = cv.height = 1;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  const px = (css, under) => { ctx.clearRect(0, 0, 1, 1); if (under) { ctx.fillStyle = "rgb(" + under.join(",") + ")"; ctx.fillRect(0, 0, 1, 1); }
    ctx.fillStyle = css; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]]; };
  const lum = (c) => { const f = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const bg = (el) => getComputedStyle(el).backgroundColor;
`;
const contrastHandle = (page) => page.evaluate(new Function(`${PIXEL}
  const out = {};
  const handle = document.querySelector("#liste > [data-gk-sort-id] > .gk-sort-griff");
  const entry = handle.parentNode;
  const under = px(bg(entry), px(bg(document.body)));
  const face = px(bg(handle), under);
  out.icon = ratio(px(getComputedStyle(handle).color), face);
  return out;`));
const contrastLine = (page, cls) => page.evaluate(new Function("cls", `${PIXEL}
  const line = document.querySelector(cls);
  const under = px(bg(line), px(bg(document.body)));
  return ratio(px(getComputedStyle(line).color), under);`), cls);

(async () => {
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  const url = "http://127.0.0.1:" + server.address().port + "/";
  const browser = await chromium.launch();
  const addFont = async (page) => {
    if (!FONT || !fs.existsSync(FONT)) return;
    const b64 = fs.readFileSync(FONT).toString("base64");
    await page.addStyleTag({ content: `@font-face{font-family:"Material Icons";src:url(data:font/woff2;base64,${b64}) format("woff2")}`
      + `.material-icons{font-family:"Material Icons";font-weight:400;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;direction:ltr;font-feature-settings:"liga"}` });
  };
  try {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    page.setDefaultTimeout(20000);
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error" && !/Failed to load resource|ERR_|net::/.test(m.text())) pageErrors.push(m.text()); });
    await page.goto(url);
    await addFont(page);
    await page.evaluate(() => {
      window.__ev = [];
      document.addEventListener("gk:sortiert", (e) => window.__ev.push({ ids: e.detail.ids, box: e.detail.behaelter && e.detail.behaelter.id, target: e.target.id, back: !!e.detail.zurueck }));
    });
    await settle(page);

    // ═══ THE HANDLES ════════════════════════════════════════════════════════

    // ── S01 a handle in every entry of a container of two or more, none in a container of one ─────────
    const counts = await page.evaluate(() => Object.fromEntries(["liste", "raster", "ohne", "eigener", "einzel"].map((id) => {
      const box = document.getElementById(id);
      return [id, { handles: box.querySelectorAll(":scope > [data-gk-sort-id] > .gk-sort-griff").length, entries: box.querySelectorAll(":scope > [data-gk-sort-id]").length, aktiv: box.classList.contains("gk-sort-aktiv") }];
    })));
    check("S01 every entry of a container with two or more has one handle, and the container is marked",
      ["liste", "raster", "ohne", "eigener"].every((id) => counts[id].handles === counts[id].entries && counts[id].entries >= 2 && counts[id].aktiv), counts);
    check("S01 … and the container with one entry has no handle and no mark", counts.einzel.handles === 0 && !counts.einzel.aktiv, counts.einzel);
    const props = await page.evaluate(() => [...document.querySelectorAll("#liste .gk-sort-griff")].map((h) => {
      const r = h.getBoundingClientRect();
      return { tag: h.tagName, type: h.getAttribute("type"), name: h.getAttribute("aria-label"), pressed: h.getAttribute("aria-pressed"),
               w: r.width, h: r.height, icon: h.querySelector(".material-icons") && h.querySelector(".material-icons").textContent,
               iconHidden: h.querySelector(".material-icons") && h.querySelector(".material-icons").getAttribute("aria-hidden"),
               desc: document.getElementById(h.getAttribute("aria-describedby")) && document.getElementById(h.getAttribute("aria-describedby")).textContent };
    }));
    check("S01 the handle is a <button type=button> named \"Move: <heading>\", not pressed, with the instructions as its description",
      props.length === 4 && props.every((p) => p.tag === "BUTTON" && p.type === "button" && p.pressed === "false" && /^Move: (Alpha|Bravo|Charlie|Delta)$/.test(p.name) && /Space/.test(p.desc || "")), props);
    check("S01 … at least 44 × 44 px, with the symbol drag_indicator hidden from a screen reader",
      props.every((p) => p.w >= 43.9 && p.h >= 43.9 && p.icon === "drag_indicator" && p.iconHidden === "true"), props);
    const names = await page.evaluate(() => [...document.querySelectorAll("#raster .gk-sort-griff")].map((h) => h.getAttribute("aria-label")));
    check("S01 data-gk-sort-titel names the entry, else its first heading", names[3] === "Move: Deike Pfeil" && names[0] === "Move: survivaltraining.at", names);
    const tree = await page.locator("#liste").ariaSnapshot();
    check("S01 the accessibility tree has the four buttons by name", ["Alpha", "Bravo", "Charlie", "Delta"].every((n) => tree.includes(`button "Move: ${n}"`)), tree);
    const pos = await page.evaluate(() => { const h = document.querySelector("#liste .gk-sort-griff"); const r = h.getBoundingClientRect(); const e = h.parentNode.getBoundingClientRect(); return { top: r.top - e.top, right: e.right - r.right }; });
    check("S01 the handle sits in the top right corner of its entry, 8 px from the edges", near(pos.top, 9, 2) && near(pos.right, 9, 2), pos);

    // ── S02 containers that arrive later, and entries that come and go ───────────────────────────
    await page.evaluate(() => {
      const box = document.createElement("ul");
      box.id = "spaet";
      box.setAttribute("data-gk-sortierbar", "");
      box.innerHTML = ["1", "2", "3"].map((i) => `<li data-gk-sort-id="${i}"><h3>Late ${i}</h3></li>`).join("");
      document.getElementById("slot").appendChild(box);
    });
    await settle(page);
    const late = await page.evaluate(() => document.querySelectorAll("#spaet > li > .gk-sort-griff").length);
    check("S02 a container written into the page later gets its handles without a call", late === 3, late);
    const wrappers = () => page.evaluate(() => document.querySelectorAll(".gk-sort-meldungen").length);
    const w1 = await wrappers();
    await page.evaluate(() => document.body.appendChild(document.getElementById("spaet")));   // moved: not set up twice
    await settle(page);
    const twice = await page.evaluate(() => document.querySelectorAll("#spaet > li > .gk-sort-griff").length);
    check("S02 a container that is moved in the page is not set up a second time (still 3 handles, no second wrapper for the lines)", twice === 3 && (await wrappers()) === w1, { twice, w1, w2: await wrappers() });
    await page.evaluate(() => { const l = document.createElement("li"); l.setAttribute("data-gk-sort-id", "s2"); l.innerHTML = "<h3>Solo two</h3>"; document.getElementById("einzel").appendChild(l); });
    await settle(page);
    const grown = await page.evaluate(() => document.querySelectorAll("#einzel > li > .gk-sort-griff").length);
    await page.evaluate(() => document.querySelector('#einzel > [data-gk-sort-id="s2"]').remove());
    await settle(page);
    const shrunk = await page.evaluate(() => [document.querySelectorAll("#einzel .gk-sort-griff").length, document.getElementById("einzel").classList.contains("gk-sort-aktiv")]);
    check("S02 a second entry added brings the handles (2), taking it away takes them (0, unmarked)", grown === 2 && shrunk[0] === 0 && shrunk[1] === false, { grown, shrunk });

    await shot(page, "sortierbar-hell-1440");

    // ═══ THE MOUSE ══════════════════════════════════════════════════════════

    // ── S03 drag the first row down to the third place: while it flies, then after ───────────────
    reqs.length = 0;
    behave = "ok";
    const from = await handleAt(page, "#liste", "10");
    const to = await entryAt(page, "#liste", "30");
    await pull(page, from, to, true);
    const flying = await page.evaluate(() => {
      const el = document.querySelector('#liste > [data-gk-sort-id="10"]');
      const cs = getComputedStyle(el);
      return { zieht: el.classList.contains("gk-sort-zieht"), frei: el.classList.contains("gk-sort-frei"), position: cs.position,
               gaps: document.querySelectorAll("#liste > .gk-sort-platz").length, gapTag: (document.querySelector("#liste > .gk-sort-platz") || {}).tagName,
               gapEmpty: (document.querySelector("#liste > .gk-sort-platz") || {}).childElementCount === 0,
               gapDashed: getComputedStyle(document.querySelector("#liste > .gk-sort-platz")).borderTopStyle,
               shadow: cs.boxShadow !== "none", outline: cs.outlineStyle,
               children: [...document.getElementById("liste").children].map((c) => c.getAttribute("data-gk-sort-id") || "gap"),
               overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth };
    });
    check("S03 while the first row flies: .gk-sort-zieht + .gk-sort-frei, fixed, with a shadow, and one dashed empty <li> gap",
      flying.zieht && flying.frei && flying.position === "fixed" && flying.shadow && flying.gaps === 1 && flying.gapTag === "LI" && flying.gapEmpty && flying.gapDashed === "dashed", flying);
    check("S03 … the gap has moved to the third place: before the fourth row, the flying row itself still where it was in the markup", JSON.stringify(flying.children) === JSON.stringify(["10", "20", "30", "gap", "40"]), flying.children);
    await shot(page, "sortierbar-gezogen-liste-hell");
    await page.mouse.up();
    await settle(page);
    const after = await page.evaluate(() => {
      const el = document.querySelector('#liste > [data-gk-sort-id="10"]');
      return { zieht: el.classList.contains("gk-sort-zieht"), frei: el.classList.contains("gk-sort-frei"), gaps: document.querySelectorAll(".gk-sort-platz").length,
               style: el.getAttribute("style"), focus: document.activeElement === el.querySelector(".gk-sort-griff"), pos: getComputedStyle(el).position };
    });
    check("S03 after the drop the order is 20 30 10 40, the classes and the gap are gone, no style attribute is left",
      JSON.stringify(await order(page, "#liste")) === '["20","30","10","40"]' && !after.zieht && !after.frei && after.gaps === 0 && after.style === null && after.pos === "relative", after);
    check("S03 … the focus is on the handle of the moved row", after.focus);
    const ev = await events(page);
    check("S03 … gk:sortiert reached the document once, from the container, with the ids as text in the new order",
      ev.length === 1 && ev[0].target === "liste" && ev[0].box === "liste" && JSON.stringify(ev[0].ids) === '["20","30","10","40"]' && !ev[0].back, ev);

    // ── S04 what is sent: a POST with the token, as JSON, all ids as text in the new order ───────────
    await waitFor(() => reqs.length >= 1);
    const r0 = reqs[0] || {};
    check("S04 one POST to the address of the container", reqs.length === 1 && r0.method === "POST" && r0.url === "/api/liste", reqs.map((r) => r.method + " " + r.url));
    check("S04 … Content-Type application/json, X-CSRF-Token tok123, body {\"ids\":[\"20\",\"30\",\"10\",\"40\"]}",
      /^application\/json/.test(r0.headers["content-type"] || "") && r0.headers["x-csrf-token"] === "tok123" && r0.body === '{"ids":["20","30","10","40"]}', r0);
    // ── S05 the answer yes: "Order saved" for a moment, no failure line ─────────────────────────
    await waitFor(() => page.evaluate(() => !document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok").hidden));
    const okLine = await page.evaluate(() => { const o = document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok"); const f = document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler"); return { text: o.textContent.replace(/\s+/g, " ").trim(), hidden: o.hidden, failHidden: f.hidden, icon: !!o.querySelector(".material-icons") }; });
    check("S05 the saved line says \"Order saved\" beside a symbol, the failure line stays hidden", okLine.hidden === false && /Order saved/.test(okLine.text) && okLine.icon && okLine.failHidden === true, okLine);
    check("S05 … and the live region says it too", /Order saved/.test(await live(page, "#liste")), await live(page, "#liste"));
    check("S05 … the saved line goes away by itself after a few seconds", await waitFor(() => page.evaluate(() => document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok").hidden), 6000));

    // ── S06 the answer no (500, {"ok":false}) and no answer at all: the row goes back, a sentence says so ─
    const failures = [["error", "a 500"], ["refuse", "{\"ok\":false}"], ["drop", "a dropped connection"]];
    for (const [how, label] of failures) {
      behave = how;
      reqs.length = 0;
      await events(page);
      const before = JSON.stringify(await order(page, "#liste"));
      const a = await handleAt(page, "#liste", "20");
      const b = await entryAt(page, "#liste", "40");
      await pull(page, a, b);
      const sawMove = JSON.stringify(await order(page, "#liste")) !== before;
      await waitFor(() => page.evaluate(() => !document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler").hidden));
      await settle(page);
      const f = await page.evaluate(() => { const l = document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler"); return { text: l.textContent.replace(/\s+/g, " ").trim(), hidden: l.hidden, role: l.getAttribute("role"), icon: !!l.querySelector(".material-icons"), box: l.getBoundingClientRect().height }; });
      const evs = await events(page);
      check(`S06 ${label}: the row moved at the drop, and went back to ${before}`, sawMove && JSON.stringify(await order(page, "#liste")) === before, { sawMove, now: await order(page, "#liste") });
      check(`S06 ${label}: a visible sentence with a symbol and role=alert says it could not be saved`,
        f.hidden === false && f.role === "alert" && f.icon && f.box > 20 && /The order could not be saved\. Please try again\./.test(f.text), f);
      check(`S06 ${label}: gk:sortiert fired for the drop and again, marked as back, for the return`, evs.length === 2 && evs[1].back === true && JSON.stringify(evs[1].ids) === before, evs);
      if (how === "error") { await page.evaluate(() => document.querySelector("#liste").scrollIntoView({ block: "start" })); await sleep(400); await shot(page, "sortierbar-fehler-hell"); }
    }
    // the failure line leaves with the next try that works
    behave = "ok";
    reqs.length = 0;
    await pull(page, await handleAt(page, "#liste", "10"), await entryAt(page, "#liste", "40"));
    await waitFor(() => reqs.length >= 1);
    await waitFor(() => page.evaluate(() => document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler").hidden));
    check("S06 the next try that works takes the failure line away", await page.evaluate(() => document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler").hidden));

    // ── S07 two drops quickly: the first request is cancelled, the second carries the order ────────
    behave = "slow";
    reqs.length = 0;
    await pull(page, await handleAt(page, "#liste", "20"), await entryAt(page, "#liste", "40"));
    await waitFor(() => reqs.length >= 1);
    await pull(page, await handleAt(page, "#liste", "30"), await entryAt(page, "#liste", "40"));
    await waitFor(() => reqs.length >= 2);
    await waitFor(() => reqs[1] && reqs[1].answered);
    await sleep(200);
    const finalOrder = await order(page, "#liste");
    check("S07 two requests were made, the first was cancelled by the browser before it was answered, the second was answered",
      reqs.length === 2 && reqs[0].aborted === true && reqs[0].answered === false && reqs[1].answered === true, reqs.map((r) => ({ aborted: r.aborted, answered: r.answered })));
    check("S07 … the second carries the order on the page", reqs[1] && JSON.parse(reqs[1].body).ids.join() === finalOrder.join(), { sent: reqs[1] && reqs[1].body, finalOrder });
    behave = "ok";

    // ── S08 no address: it sorts and fires the event, and sends nothing ───────────────────────────
    reqs.length = 0;
    await events(page);
    await pull(page, await handleAt(page, "#ohne", "x"), await entryAt(page, "#ohne", "z"));
    await sleep(300);
    const ev8 = await events(page);
    const noLines = await page.evaluate(() => { const w = document.querySelector("#ohne + .gk-sort-meldungen"); return w ? [...w.querySelectorAll(".gk-sort-ok, .gk-sort-fehler")].every((l) => l.hidden) : true; });
    check("S08 a container without an address: the order changes, the event fires, no request is sent, no line is shown",
      JSON.stringify(await order(page, "#ohne")) === '["y","z","x"]' && ev8.length === 1 && ev8[0].box === "ohne" && reqs.length === 0 && noLines, { ev8, n: reqs.length });

    // ── S09 the token goes in the header the page names ─────────────────────────────────────────
    reqs.length = 0;
    await pull(page, await handleAt(page, "#eigener", "p"), await entryAt(page, "#eigener", "q"));
    await waitFor(() => reqs.length >= 1);
    check("S09 data-gk-sortierbar-csrf-header: the token travels as X-My-Token, not as X-CSRF-Token",
      reqs.length === 1 && reqs[0].headers["x-my-token"] === "abc" && !("x-csrf-token" in reqs[0].headers) && reqs[0].body === '{"ids":["q","p"]}', reqs[0]);

    // ── S10 a link in a card keeps working; the body of a card is not a handle ────────────────────
    await page.locator("#open-b").scrollIntoViewIfNeeded();
    const rasterBefore = JSON.stringify(await order(page, "#raster"));
    const body = await entryAt(page, "#raster", "b");
    await page.mouse.move(body.x, body.y + 20);
    await page.mouse.down();
    await page.mouse.move(body.x + 150, body.y + 140, { steps: 8 });
    await page.mouse.up();
    await settle(page);
    check("S10 pulling a card by its body moves nothing", JSON.stringify(await order(page, "#raster")) === rasterBefore, await order(page, "#raster"));
    await page.locator("#open-b").click();
    check("S10 a link in a card can still be clicked", (await page.evaluate(() => location.hash)) === "#open-b");

    // ── S11 a card dragged across the grid, with the picture of it ───────────────────────────────
    reqs.length = 0;
    await events(page);
    await page.locator("#raster").scrollIntoViewIfNeeded();
    await page.evaluate(() => getSelection().removeAllRanges());   // S10 pulled a card by its body, which selects text: start clean
    await pull(page, await handleAt(page, "#raster", "a"), await entryAt(page, "#raster", "e"), true);
    await sleep(350);   // the neighbours have glided into their places: the picture shows a rest, not a frame of the move
    const gridFly = await page.evaluate(() => ({ selected: String(getSelection()), children: [...document.getElementById("raster").children].map((c) => c.getAttribute("data-gk-sort-id") || (c.classList.contains("gk-sort-platz") ? "gap" : "?")), over: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
    await shot(page, "sortierbar-gezogen-raster-hell");
    check("S11 pulling a card by its handle selects no text on the way", gridFly.selected === "", gridFly.selected);
    await page.mouse.up();
    await settle(page);
    check("S11 a card pulled from the first place to the fifth ends up there: b c d e a f", JSON.stringify(await order(page, "#raster")) === '["b","c","d","e","a","f"]', { gridFly, now: await order(page, "#raster") });
    await waitFor(() => reqs.length >= 1);
    check("S11 … and it is saved to the address of the grid with its token", reqs.length === 1 && reqs[0].url === "/api/raster" && reqs[0].headers["x-csrf-token"] === "tok456" && reqs[0].body === '{"ids":["b","c","d","e","a","f"]}', reqs[0]);

    // ═══ THE KEYBOARD ═══════════════════════════════════════════════════════

    // ── S12 list: Space lifts, arrows move, Space puts down, Escape gives back ─────────────────────
    behave = "ok";
    reqs.length = 0;
    await events(page);
    const id12 = (await order(page, "#liste"))[2];   // whichever row stands third now
    const hb = `#liste > [data-gk-sort-id="${id12}"] > .gk-sort-griff`;
    await page.locator(hb).focus();
    await page.keyboard.press("Space");
    await sleep(60);
    const lifted = await page.evaluate((h) => { const b = document.querySelector(h); return { pressed: b.getAttribute("aria-pressed"), entry: b.parentNode.classList.contains("gk-sort-zieht"), focus: document.activeElement === b }; }, hb);
    check("S12 Space on a handle lifts the row: pressed, the lifted look, the focus stays", lifted.pressed === "true" && lifted.entry && lifted.focus, lifted);
    check("S12 … and the live region says where it is", /Position \d of 4/.test(await live(page, "#liste")), await live(page, "#liste"));
    const startOrder = JSON.stringify(await order(page, "#liste"));
    await page.keyboard.press("Home");
    await sleep(40);
    check("S12 Home jumps to the first place", (await order(page, "#liste")).indexOf(id12) === 0, await order(page, "#liste"));
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("ArrowDown");
    await sleep(60);
    const moved = await page.evaluate((h) => ({ focus: document.activeElement === document.querySelector(h), order: [...document.getElementById("liste").children].filter((e) => e.hasAttribute("data-gk-sort-id")).map((e) => e.getAttribute("data-gk-sort-id")) }), hb);
    check("S12 ArrowDown twice moves the row two places down and the focus is still on its handle", moved.focus && moved.order.indexOf(id12) === 2, moved);
    check("S12 … nothing was sent while it is in the air", reqs.length === 0 && (await events(page)).length === 0);
    check("S12 … and the live region says \"Position 3 of 4\"", /Position 3 of 4/.test(await live(page, "#liste")), await live(page, "#liste"));
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("ArrowDown");   // at the end already
    await sleep(40);
    check("S12 ArrowDown at the end stays at the end", (await order(page, "#liste")).indexOf(id12) === 3, await order(page, "#liste"));
    await page.keyboard.press("End");
    await sleep(40);
    check("S12 End jumps to the last place", (await order(page, "#liste")).indexOf(id12) === 3, await order(page, "#liste"));
    await page.keyboard.press("Escape");
    await sleep(60);
    const esc = await page.evaluate((h) => ({ pressed: document.querySelector(h).getAttribute("aria-pressed"), focus: document.activeElement === document.querySelector(h) }), hb);
    check("S12 Escape gives the row back where it was lifted, not pressed any more, focus unchanged",
      JSON.stringify(await order(page, "#liste")) === startOrder && esc.pressed === "false" && esc.focus && reqs.length === 0 && (await events(page)).length === 0, { esc, now: await order(page, "#liste") });
    check("S12 … and the live region says it was cancelled", /Cancelled/.test(await live(page, "#liste")), await live(page, "#liste"));
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowUp");
    await page.keyboard.press("Space");
    await waitFor(() => reqs.length >= 1);
    const ev12 = await events(page);
    check("S12 Space, ArrowUp, Space: the row is one place up, put down, saved once, and the event fired once",
      reqs.length === 1 && ev12.length === 1 && JSON.parse(reqs[0].body).ids.join() === (await order(page, "#liste")).join() && (await order(page, "#liste")).join() !== JSON.parse(startOrder).join(), { reqs: reqs.length, ev12 });
    await waitFor(async () => /put down at position/.test(await live(page, "#liste")), 1500);
    check("S12 … and the live region says it was put down, then that the order is saved, in one message", /put down at position \d of 4\. Order saved/.test(await live(page, "#liste")), await live(page, "#liste"));
    await page.keyboard.press("Space");
    await page.keyboard.press("Tab");
    await sleep(60);
    check("S12 Tab away from a lifted row puts it back (a lifted row never stays behind)", await page.evaluate((h) => document.querySelector(h).getAttribute("aria-pressed") === "false" && !document.querySelector(".gk-sort-zieht"), hb));

    // ── S13 grid: left/right is one place, up/down is one row ─────────────────────────────────
    const h13 = "#raster > [data-gk-sort-id=\"c\"] > .gk-sort-griff";   // order now b c d e a f
    await page.locator("#raster").scrollIntoViewIfNeeded();
    await page.locator(h13).focus();
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowRight");
    await sleep(40);
    const right = (await order(page, "#raster")).indexOf("c");
    await page.keyboard.press("ArrowDown");
    await sleep(40);
    const down = (await order(page, "#raster")).indexOf("c");
    await page.keyboard.press("ArrowUp");
    await sleep(40);
    const up = (await order(page, "#raster")).indexOf("c");
    await page.keyboard.press("ArrowLeft");
    await sleep(40);
    const left = (await order(page, "#raster")).indexOf("c");
    check("S13 in a grid of three columns ArrowRight is one place (index 1 → 2), ArrowDown one row (→ 5), ArrowUp back (→ 2), ArrowLeft one place (→ 1)",
      right === 2 && down === 5 && up === 2 && left === 1, { right, down, up, left });
    await page.keyboard.press("Escape");
    await sleep(40);
    await page.evaluate(() => { document.getElementById("raster").dir = "rtl"; });
    await settle(page);
    await page.locator(h13).focus();
    await page.keyboard.press("Space");
    await page.keyboard.press("ArrowLeft");
    await sleep(40);
    const rtlLeft = (await order(page, "#raster")).indexOf("c");
    await page.keyboard.press("Escape");
    await sleep(40);
    await page.evaluate(() => { document.getElementById("raster").dir = "ltr"; });
    check("S13 in a right-to-left grid ArrowLeft is the next place (index 1 → 2)", rtlLeft === 2, rtlLeft);

    // ── S14 reduced motion: the neighbours do not glide ─────────────────────────────────────────
    const glide = () => page.evaluate(() => {
      const h = document.querySelector('#liste > [data-gk-sort-id] > .gk-sort-griff');
      h.focus();
      h.dispatchEvent(new KeyboardEvent("keydown", { key: " ", bubbles: true }));
      h.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowDown", bubbles: true }));
      const n = document.getAnimations().filter((a) => a.id === "gk-sort-glide").length;
      h.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
      return n;
    });
    await page.emulateMedia({ reducedMotion: "no-preference" });
    const withMotion = await glide();
    await sleep(500);   // the animations of the first run have ended
    await page.emulateMedia({ reducedMotion: "reduce" });
    const withoutMotion = await glide();
    await page.emulateMedia({ reducedMotion: "no-preference" });
    check("S14 the neighbours glide into their places (an animation runs), and under prefers-reduced-motion none does", withMotion > 0 && withoutMotion === 0, { withMotion, withoutMotion });

    // ═══ A LONG PAGE ════════════════════════════════════════════════════════

    // ── S15 the page scrolls when the row is held at the window's edge ────────────────────────
    await page.evaluate(() => document.getElementById("lang").scrollIntoView({ block: "start" }));
    await settle(page);
    const y0 = await page.evaluate(() => window.scrollY);
    await pull(page, await handleAt(page, "#lang", "n1"), { x: 700, y: 985 }, true);
    const scrolled = await waitFor(() => page.evaluate((y) => window.scrollY > y + 150, y0), 4000);
    await page.mouse.up();
    await settle(page);
    const langOrder = await order(page, "#lang");
    check("S15 held at the lower edge of the window the page scrolls on its own, and the row lands far below its place", scrolled && langOrder.indexOf("n1") > 5, { scrolled, at: langOrder.indexOf("n1") });
    behave = "ok";
    await page.evaluate(() => window.scrollTo(0, 0));

    // ═══ COLOURS ════════════════════════════════════════════════════════════

    // ── S16 light, dark, dark class: the handle and the two lines can be read ─────────────────────
    await page.evaluate(() => document.querySelector("#liste").scrollIntoView({ block: "start" }));
    const farben = {};
    for (const m of ["light", "dark", "dark-class"]) {
      await mode(page, m);
      await settle(page);
      const f = {};
      f.handle = await contrastHandle(page);
      // the lines are hidden by default; show them as the script does, with its own words, to measure
      await page.evaluate(() => { document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok").hidden = false; document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok").textContent = "Order saved"; const fl = document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler"); fl.hidden = false; fl.textContent = "The order could not be saved. Please try again."; });
      f.ok = await contrastLine(page, "#liste + .gk-sort-meldungen .gk-sort-ok");
      f.fail = await contrastLine(page, "#liste + .gk-sort-meldungen .gk-sort-fehler");
      // a lifted handle
      await page.locator("#liste .gk-sort-griff").first().focus();
      await page.keyboard.press("Space");
      f.pressed = (await contrastHandle(page)).icon;
      if (m === "dark") await shot(page, "sortierbar-dunkel-angehoben");
      await page.keyboard.press("Escape");
      await page.evaluate(() => { document.querySelector("#liste + .gk-sort-meldungen .gk-sort-ok").hidden = true; document.querySelector("#liste + .gk-sort-meldungen .gk-sort-fehler").hidden = true; });
      farben[m] = f;
    }
    await mode(page, "light");
    const weakest = (key) => Math.min(...Object.values(farben).map((f) => (key === "icon" ? f.handle.icon : f[key])));
    check("S16 the symbol of the handle is at least 3:1 against its face in light, dark and dark class (weakest " + weakest("icon").toFixed(2) + ")", weakest("icon") >= 3, farben);
    check("S16 … the lifted handle too (weakest " + weakest("pressed").toFixed(2) + ")", weakest("pressed") >= 3, farben);
    check("S16 … the saved line and the failure line are at least 4.5:1 (weakest " + Math.min(weakest("ok"), weakest("fail")).toFixed(2) + ")", weakest("ok") >= 4.5 && weakest("fail") >= 4.5, farben);
    await mode(page, "dark");
    await page.evaluate(() => document.documentElement.scrollTo(0, 0));
    await shot(page, "sortierbar-dunkel-1440");
    await mode(page, "light");

    // ═══ A PHONE ════════════════════════════════════════════════════════════

    // ── S17 390 wide: no sideways scroll, the handles inside the window, 44 px ────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    await settle(page);
    const phone = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      handles: [...document.querySelectorAll("#liste .gk-sort-griff, #raster .gk-sort-griff")].map((h) => { const r = h.getBoundingClientRect(); return { right: r.right, w: r.width, h: r.height }; }),
    }));
    check("S17 at 390 px wide there is no sideways scroll, every handle is inside the window and 44 px", phone.overflow <= 0 && phone.handles.every((h) => h.right <= 390 && h.w >= 43.9 && h.h >= 43.9), phone);
    await page.evaluate(() => window.scrollTo(0, 0));
    await shot(page, "sortierbar-handy-390-hell");
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.close();
    await ctx.close();

    // ═══ A FINGER ═══════════════════════════════════════════════════════════

    // ── S18 touch: the handle drags, the body of a card scrolls the page and moves nothing ─────────
    const touchCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
    const tp = await touchCtx.newPage();
    tp.setDefaultTimeout(20000);
    tp.on("pageerror", (e) => pageErrors.push(e.message));
    await tp.goto(url);
    await addFont(tp);
    await settle(tp);
    const cdp = await touchCtx.newCDPSession(tp);
    const touch = async (type, x, y) => cdp.send("Input.dispatchTouchEvent", { type, touchPoints: type === "touchEnd" ? [] : [{ x, y, id: 1 }] });
    behave = "ok";
    reqs.length = 0;
    const tf = await handleAt(tp, "#liste", "10");
    const tt = await entryAt(tp, "#liste", "30");
    await touch("touchStart", tf.x, tf.y);
    for (let i = 1; i <= 14; i++) { await touch("touchMove", tf.x, tf.y + (tt.y - tf.y) * i / 14); await sleep(16); }
    await touch("touchEnd");
    await settle(tp);
    await waitFor(() => reqs.length >= 1);
    check("S18 a finger on the handle drags the row from the first to the third place and it is saved",
      JSON.stringify(await order(tp, "#liste")) === '["20","30","10","40"]' && reqs.length === 1 && reqs[0].body === '{"ids":["20","30","10","40"]}', { order: await order(tp, "#liste"), n: reqs.length });
    await tp.evaluate(() => window.scrollTo(0, 0));
    const y1 = await tp.evaluate(() => window.scrollY);
    const bodyAt = await entryAt(tp, "#liste", "40");
    await touch("touchStart", bodyAt.x, bodyAt.y + 10);
    for (let i = 1; i <= 10; i++) { await touch("touchMove", bodyAt.x, bodyAt.y + 10 - 20 * i); await sleep(16); }
    await touch("touchEnd");
    await sleep(300);
    check("S18 a finger on the body of a row does not move it, and the page scrolls under it", JSON.stringify(await order(tp, "#liste")) === '["20","30","10","40"]' && (await tp.evaluate(() => window.scrollY)) > y1, { order: await order(tp, "#liste"), y1, y: await tp.evaluate(() => window.scrollY) });
    const touchAction = await tp.evaluate(() => ({ handle: getComputedStyle(document.querySelector("#liste .gk-sort-griff")).touchAction, entry: getComputedStyle(document.querySelector("#liste [data-gk-sort-id]")).touchAction, box: getComputedStyle(document.getElementById("liste")).touchAction }));
    check("S18 touch-action: none is on the handle only, the row and the container keep auto", touchAction.handle === "none" && touchAction.entry === "auto" && touchAction.box === "auto", touchAction);
    await tp.evaluate(() => window.scrollTo(0, 0));
    await shot(tp, "sortierbar-touch-390");
    await touchCtx.close();
  } finally {
    await browser.close();
    server.close();
  }

  for (const r of results) console.log((r.ok ? "ok    " : "FAIL  ") + r.name + (r.ok || !r.why ? "" : "\n        " + r.why.slice(0, 700)));
  if (pageErrors.length) console.log("page errors:", pageErrors.slice(0, 3));
  const failed = results.filter((r) => !r.ok).length + (pageErrors.length ? 1 : 0);
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error("aborted:", e.message.split("\n")[0]); try { server.close(); } catch (x) { /* done */ } process.exit(1); });
