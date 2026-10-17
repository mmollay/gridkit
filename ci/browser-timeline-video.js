#!/usr/bin/env node
/**
 * The timeline and the portrait video frame in a real browser (1.96.0).
 *
 * `php tests/run.php` reads the stylesheet; it cannot see a box. What only a browser shows is held
 * here, on real markup (ci/browser-fixture.php --timeline-video): the symbols of the five states and
 * where they sit, the line between the steps, the ring that turns and stops under reduced motion,
 * long words and chips on a phone, the colours in both spellings of dark mode, the keyboard; and of
 * the frame: the 9:16 at every width and window height, the limit, the picture in the box, the safe
 * zones (their height, that they catch no click, that a number can be changed), the two empty states.
 *
 *     node ci/browser-timeline-video.js
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-timeline-video.js
 *     GK_SHOTS=/some/dir node ci/browser-timeline-video.js      # also saves pictures to look at
 *
 * Like ci/browser.js it stays outside the suite: GridKit has no dependencies. Exit code 0 when every
 * case holds. The colours of every text in every theme are measured in ci/farben.js.
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
const SHOTS = process.env.GK_SHOTS || "";
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-timeline-video-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));
const fixture = path.join(dir, "timeline-video.html");
fs.writeFileSync(fixture, execFileSync(php, [path.join(__dirname, "browser-fixture.php"), "--timeline-video"], { maxBuffer: 64 * 1024 * 1024 }));

const results = [];
const pageErrors = [];
const check = (name, ok, why) => { results.push({ name, ok: !!ok, why: ok ? "" : why === undefined ? "" : typeof why === "string" ? why : JSON.stringify(why) }); };
const shot = async (page, name, sel) => {
  if (!SHOTS) return;
  fs.mkdirSync(SHOTS, { recursive: true });
  const target = sel ? page.locator(sel) : page;
  await target.screenshot({ path: path.join(SHOTS, name + ".png"), ...(sel ? {} : { fullPage: true }) });
};
const near = (a, b, tol = 1) => Math.abs(a - b) <= tol;
const STATES = ["ok", "failed", "running", "waiting", "skipped"];

/** Run in the page: switch the colour mode. "light" | "dark" (data-gk-mode) | "dark-class" (.gk-dark). */
const mode = (page, m) => page.evaluate((m) => {
  document.body.classList.toggle("gk-dark", m === "dark-class");
  document.body.setAttribute("data-gk-mode", m === "dark-class" ? "light" : m);
  // A mode change starts transitions; measure where they end, not in the middle.
  void document.body.offsetWidth;
  document.getAnimations().filter((a) => isFinite(a.effect.getComputedTiming().endTime)).forEach((a) => a.finish());
}, m);

/** Two animation frames: whatever a resize or a class change did to the layout has been laid out. */
const settle = (page) => page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
/** Bring an element into the window (elementFromPoint and the mouse only see what is in it). */
const intoView = (page, sel) => page.evaluate((s) => document.querySelector(s).scrollIntoView({ block: "center" }), sel).then(() => settle(page));

/** The box of an element by selector, in page pixels. */
const rect = (page, sel) => page.evaluate((s) => { const r = document.querySelector(s).getBoundingClientRect(); return { l: r.left, t: r.top, r: r.right, b: r.bottom, w: r.width, h: r.height }; }, sel);

(async () => {
  const browser = await chromium.launch();
  try {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    page.setDefaultTimeout(20000);   // a shared build server is slow at times; a real fault fails a check, not the clock
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error" && !/Failed to load resource/.test(m.text())) pageErrors.push(m.text()); });
    await page.goto("file://" + fixture);
    await settle(page);

    // ═══ THE TIMELINE ═══════════════════════════════════════════════════════

    // ── T01 a list, with a name and five items; a screen reader reads the word of each state ─────────
    const tree = await page.locator("#tl").ariaSnapshot();
    const items = tree.split("\n").filter((l) => /^\s*- listitem/.test(l));
    check("T01 the accessibility tree has one list named \"Steps\" with five items", /^- list "Steps":/.test(tree) && items.length === 5, tree);
    check("T01 … and each item says its title AND the word of its state (the symbol is not announced)",
      ["Write the script", "Make the pictures", "Assemble the video", "Check the safe zones", "Upload"].every((t) => tree.includes(t))
      && ["Done", "Failed", "Running", "Waiting", "Skipped"].every((w) => tree.includes(w)), tree);
    const markerHidden = await page.evaluate(() => [...document.querySelectorAll("#tl .gk-timeline-marker")].every((m) => m.getAttribute("aria-hidden") === "true" && m.textContent === ""));
    check("T01 the markers are empty and hidden from a screen reader", markerHidden);
    const current = await page.evaluate(() => [...document.querySelectorAll("#tl [aria-current]")].map((e) => e.id));
    check("T01 exactly the running step is the current one", JSON.stringify(current) === '["s-running"]', current);

    // ── T02 five symbols, five different shapes ─────────────────────────────────────────────────
    const sym = await page.evaluate((states) => Object.fromEntries(states.map((s) => {
      const m = document.querySelector("#s-" + s + " .gk-timeline-marker");
      const one = (pseudo) => {
        const c = getComputedStyle(m, pseudo);
        return { content: c.content, w: parseFloat(c.width), h: parseFloat(c.height), bt: parseFloat(c.borderTopWidth), br: parseFloat(c.borderRightWidth),
                 bb: parseFloat(c.borderBottomWidth), bl: parseFloat(c.borderLeftWidth), radius: c.borderTopLeftRadius, transform: c.transform,
                 bg: c.backgroundColor, ml: parseFloat(c.marginLeft), mt: parseFloat(c.marginTop), display: c.display };
      };
      return [s, { before: one("::before"), after: one("::after"), size: m.getBoundingClientRect().width, bw: parseFloat(getComputedStyle(m).borderTopWidth),
                   style: getComputedStyle(m).borderTopStyle }];
    })), STATES);
    const signature = (o) => JSON.stringify([o.before.w, o.before.h, o.before.bt, o.before.br, o.before.bb, o.before.bl, o.before.radius, o.before.transform, o.after.content !== "none"]);
    const unique = new Set(STATES.map((s) => signature(sym[s])));
    check("T02 each of the five states draws a symbol (content, a size) and no two symbols are the same shape",
      STATES.every((s) => sym[s].before.content === '""' && sym[s].before.w + sym[s].before.h > 0) && unique.size === 5, sym);
    check("T02 the cross has a second stroke (::after), drawn — a height and a left edge, not display: none — and the others have one symbol",
      sym.failed.after.content === '""' && sym.failed.after.display !== "none" && sym.failed.after.h > 0 && sym.failed.after.bl > 0
      && STATES.filter((s) => s !== "failed").every((s) => sym[s].after.content === "none"), sym);
    check("T02 the marker is 28px and its edge is 2px; the waiting one has a dashed edge, the others a solid one",
      STATES.every((s) => sym[s].size === 28 && sym[s].bw === 2) && sym.waiting.style === "dashed" && STATES.filter((s) => s !== "waiting").every((s) => sym[s].style === "solid"), sym);
    // Centred: abs-positioned with inset 0 and margin auto — the used margin is half of what is left of the disc's inner 24px.
    const mitte = STATES.map((s) => [s, near(sym[s].before.ml, (24 - sym[s].before.w) / 2, 0.6), near(sym[s].before.mt, (24 - sym[s].before.h) / 2, 0.6)]);
    check("T02 every symbol sits in the middle of its disc, across and down", mitte.every(([, a, b]) => a && b), mitte);

    // ── T03 the line between the steps ──────────────────────────────────────────────────────────
    const line = await page.evaluate(() => [...document.querySelectorAll("#tl > li")].map((li) => {
      const c = getComputedStyle(li, "::before"), m = li.querySelector(".gk-timeline-marker").getBoundingClientRect(), r = li.getBoundingClientRect();
      return { content: c.content, w: parseFloat(c.width), h: parseFloat(c.height), left: parseFloat(c.left), top: parseFloat(c.top), markerMid: m.width / 2, liH: r.height };
    }));
    check("T03 a line under every marker but the last: 2px wide, through the middle of the markers, from under the marker to just above the next one",
      line.slice(0, 4).every((l) => l.content === '""' && l.w === 2 && near(l.left + l.w / 2, l.markerMid, 0.5) && near(l.top, 32, 0.5) && l.h > 4 && l.top + l.h <= l.liH)
      && line[4].content === "none", line);

    // ── T03b the first line of the title is level with the middle of the marker ───────────────────
    const lage = await page.evaluate(() => { const t = document.querySelector("#s-ok .gk-timeline-title").getBoundingClientRect(), m = document.querySelector("#s-ok .gk-timeline-marker").getBoundingClientRect();
      return { title: (t.top + t.bottom) / 2, marker: (m.top + m.bottom) / 2 }; });
    check("T03b the title lies level with the middle of its marker (within 2px)", Math.abs(lage.title - lage.marker) <= 2, lage);

    // ── T04 the colours of the markers, light ───────────────────────────────────────────────────
    const farbe = (page_, states) => page_.evaluate((states) => Object.fromEntries(states.map((s) => {
      const q = (sel) => document.querySelector("#s-" + s + " " + sel);
      const c = (el) => { const k = getComputedStyle(el); return [k.color, k.backgroundColor, k.borderTopColor].join(" | "); };
      return [s, { marker: c(q(".gk-timeline-marker")), disc: getComputedStyle(q(".gk-timeline-marker")).backgroundColor, word: getComputedStyle(q(".gk-timeline-state")).color, title: getComputedStyle(q(".gk-timeline-title")).color }];
    })), states);
    const hell = await farbe(page, STATES);
    check("T04 the three tones (ok, failed, running) are three different colours of word and of disc, and differ from the quiet ones",
      new Set(["ok", "failed", "running"].map((s) => hell[s].word)).size === 3 && new Set(["ok", "failed", "running"].map((s) => hell[s].marker)).size === 3
      && hell.waiting.word === hell.skipped.word && hell.waiting.word !== hell.ok.word, hell);
    check("T04 waiting and skipped are quiet: their title is the muted colour, the others' is the text colour",
      hell.waiting.title === hell.skipped.title && hell.waiting.title !== hell.ok.title && hell.ok.title === hell.failed.title && hell.ok.title === hell.running.title, hell);

    // ── T05 the running ring turns, nothing else does, and it stops when asked ────────────────────
    const lauf = await page.evaluate(() => document.getAnimations().filter((a) => a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest("#tl, #tl-long"))
      .map((a) => ({ name: a.animationName, pseudo: a.effect.pseudoElement, step: a.effect.target.closest("li").id, state: a.playState })));
    check("T05 exactly one animation runs in the timelines: gk-spin on the ring of the running step", lauf.length === 1 && lauf[0].name === "gk-spin" && lauf[0].pseudo === "::before"
      && lauf[0].step === "s-running" && lauf[0].state === "running", lauf);
    await page.emulateMedia({ reducedMotion: "reduce" });
    const still = await page.evaluate(() => {
      const c = getComputedStyle(document.querySelector("#s-running .gk-timeline-marker"), "::before");
      return { name: c.animationName, w: parseFloat(c.width), gap: c.borderRightColor, border: c.borderLeftColor,
               n: document.getAnimations().filter((a) => a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest("#tl")).length };
    });
    check("T05 reduced motion: the ring has no animation of its own (not just a shortened one) and is still a ring with a gap",
      still.name === "none" && still.n === 0 && still.w === 14 && still.gap === "rgba(0, 0, 0, 0)" && still.border !== "rgba(0, 0, 0, 0)", still);
    await page.emulateMedia({ reducedMotion: "no-preference" });

    // ── T06 the error is a block that cannot be missed ───────────────────────────────────────────
    const fehler = await page.evaluate(() => {
      const e = document.querySelector("#s-failed .gk-timeline-error"), c = getComputedStyle(e), body = document.querySelector("#s-failed .gk-timeline-body").getBoundingClientRect(), r = e.getBoundingClientRect();
      return { bg: c.backgroundColor, color: c.color, left: parseFloat(c.borderLeftWidth), weight: parseInt(c.fontWeight, 10), edge: c.borderTopColor, wide: r.width, bodyW: body.width, inside: r.right <= body.right + 0.5 };
    });
    check("T06 the message of a failed step: a tinted ground, text in its pair, a 4px edge on the left, semi-bold, as wide as the body",
      fehler.bg !== "rgba(0, 0, 0, 0)" && fehler.left === 4 && fehler.weight >= 600 && near(fehler.wide, fehler.bodyW, 1) && fehler.inside && fehler.color !== fehler.bg, fehler);

    // ── T07 wide: the time stands right of the title, on its row ───────────────────────────────────
    const breit = await page.evaluate(() => {
      const near = (a, b, tol) => Math.abs(a - b) <= tol;
      const b = document.querySelector("#s-ok .gk-timeline-body").getBoundingClientRect(), t = document.querySelector("#s-ok .gk-timeline-title").getBoundingClientRect(),
            z = document.querySelector("#s-ok .gk-timeline-time").getBoundingClientRect(), w = document.querySelector("#s-ok .gk-timeline-state").getBoundingClientRect();
      return { sameRow: Math.abs(z.top - t.top) < 12, atRight: near(z.right, b.right, 1), afterWord: w.left >= t.right, stateOnRow: Math.abs(w.top - t.top) < 12 };
    });
    check("T07 wide: title, state word and time are on one row, the time at the right edge", breit.sameRow && breit.atRight && breit.afterWord && breit.stateOnRow, breit);
    await shot(page, "timeline-light-1440", "#tl-box");

    // ── T08 dark: both spellings give the same colours, and they are not the light ones ────────────
    const dunkel = {};
    for (const m of ["dark", "dark-class"]) { await mode(page, m); dunkel[m] = await farbe(page, STATES); }
    const fehlerFarben = async () => page.evaluate(() => { const c = getComputedStyle(document.querySelector("#s-failed .gk-timeline-error")); return [c.color, c.backgroundColor, c.borderTopColor].join(" | "); });
    await mode(page, "dark"); const fehlerA = await fehlerFarben();
    await shot(page, "timeline-dark-1440", "#tl-box");
    await mode(page, "dark-class"); const fehlerB = await fehlerFarben();
    await mode(page, "light"); const fehlerHell = await fehlerFarben();
    check("T08 dark: [data-gk-mode=\"dark\"] and .gk-dark paint every marker, word and title the same", JSON.stringify(dunkel.dark) === JSON.stringify(dunkel["dark-class"]), dunkel);
    check("T08 dark is another set than light for all five states (marker, word, title) — and the disc of the three tones is another ground, not only its symbol", STATES.every((s) => dunkel.dark[s].marker !== hell[s].marker || s === "waiting" || s === "skipped")
      && ["ok", "failed", "running"].every((s) => dunkel.dark[s].disc !== hell[s].disc)
      && ["ok", "failed", "running"].every((s) => dunkel.dark[s].word !== hell[s].word) && STATES.every((s) => dunkel.dark[s].title !== hell[s].title), { hell, dark: dunkel.dark });
    check("T08 dark: the error block is the same in both spellings and is not the light one", fehlerA === fehlerB && fehlerA !== fehlerHell, { fehlerA, fehlerB, fehlerHell });

    // ── T09 forced colours: a symbol is a border, so it survives the repaint of the backgrounds ─────
    await page.emulateMedia({ forcedColors: "active" });
    const forced = await page.evaluate((states) => Object.fromEntries(states.filter((s) => s !== "waiting").map((s) => {
      const m = document.querySelector("#s-" + s + " .gk-timeline-marker"), c = getComputedStyle(m, "::before"), mc = getComputedStyle(m);
      const sides = ["Top", "Right", "Bottom", "Left"].map((x) => [parseFloat(c["border" + x + "Width"]), c["border" + x + "Color"]]).filter(([w, col]) => w > 0 && col !== "rgba(0, 0, 0, 0)");
      return [s, { visibleBorders: sides.length, markerBorder: mc.borderTopColor, markerBg: mc.backgroundColor, symbol: sides[0] ? sides[0][1] : null }];
    })), STATES);
    await page.emulateMedia({ forcedColors: "none" });
    check("T09 forced colours: ok, failed, running and skipped still draw their symbol with a visible border, and the disc keeps its edge",
      Object.values(forced).every((f) => f.visibleBorders >= 1 && f.markerBorder !== "rgba(0, 0, 0, 0)"), forced);

    // ── T10 the keyboard: the list adds no tab stop; a link inside a step is one, and shows its focus ─
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await page.focus("#before");
    const tabs = [];
    for (let i = 0; i < 3; i++) { await page.keyboard.press("Tab"); tabs.push(await page.evaluate(() => (document.activeElement && (document.activeElement.id || document.activeElement.tagName)) || "")); }
    check("T10 Tab goes from the button before the lists to the link in a step and on to the button after it — the steps are not stops",
      tabs[0] === "l-link" && tabs[1] !== "l-link", tabs);
    const fokus = await page.evaluate(() => { document.getElementById("l-link").focus(); const c = getComputedStyle(document.getElementById("l-link")); return { style: c.outlineStyle, width: c.outlineWidth, shadow: c.boxShadow }; });
    check("T10 the link in a step shows where the focus is", fokus.style !== "none" || fokus.shadow !== "none", fokus);
    await page.evaluate(() => document.activeElement && document.activeElement.blur());

    // ── T11 a phone: nothing pushes the page wide, long words and chips stay in the column ─────────
    for (const [w, h] of [[360, 800], [320, 700]]) {
      await page.setViewportSize({ width: w, height: h });
      await settle(page);
      const phone = await page.evaluate(() => {
        const out = { page: document.documentElement.scrollWidth - document.documentElement.clientWidth };
        for (const id of ["tl-box", "tl-long-box"]) { const b = document.getElementById(id); out[id] = b.scrollWidth - b.clientWidth; }
        const body = document.querySelector("#l-title .gk-timeline-body").getBoundingClientRect(), outside = [];
        for (const el of document.querySelectorAll("#l-title .gk-timeline-body *")) { if (el.getBoundingClientRect().right > body.right + 0.5) outside.push(el.className || el.tagName); }
        out.outside = outside;
        out.chips = [...document.querySelectorAll("#l-title .gk-timeline-chips .gk-label")].map((c) => ({ right: Math.round(c.getBoundingClientRect().right), lines: Math.round(c.getBoundingClientRect().height / 18) }));
        out.bodyRight = Math.round(body.right);
        return out;
      });
      check(`T11 ${w} px: no sideways scroll on the page or in either list, and nothing of a step with long words lies right of its body`,
        phone.page <= 0 && phone["tl-box"] <= 0 && phone["tl-long-box"] <= 0 && phone.outside.length === 0, phone);
      check(`T11 ${w} px: the long chips wrap onto lines inside the column`, phone.chips.length === 3 && phone.chips.every((c) => c.right <= phone.bodyRight) && phone.chips.some((c) => c.lines >= 2), phone);
    }
    // Something wide that cannot break in the detail of a step (an unbreakable 900px, clipped by its section): the body of the step keeps
    // the column, so the title and the time beside it are laid out in the column and not in 900px.
    await page.setViewportSize({ width: 360, height: 800 });
    await settle(page);
    const breitDrin = await page.evaluate(() => {
      const li = document.getElementById("l-wide").getBoundingClientRect(), body = document.querySelector("#l-wide .gk-timeline-body").getBoundingClientRect(),
            title = document.querySelector("#l-wide .gk-timeline-title").getBoundingClientRect();
      return { li: li.width, body: body.width, titleRight: title.right, liRight: li.right, titleLines: Math.round(title.height / 20) };
    });
    check("T11 360 px: a 900px unbreakable thing in a detail does not widen the body of its step — the body is the column, the title wraps inside it",
      breitDrin.body <= breitDrin.li - 42 + 1 && breitDrin.titleRight <= breitDrin.liRight + 0.5 && breitDrin.titleLines >= 2, breitDrin);
    const unter = await page.evaluate(() => {
      const t = document.querySelector("#s-ok .gk-timeline-title").getBoundingClientRect(), z = document.querySelector("#s-ok .gk-timeline-time").getBoundingClientRect();
      return { below: z.top >= t.bottom - 1, leftDiff: Math.abs(z.left - t.left) };
    });
    check("T11 360 px: the time goes under the title, on the left, instead of crowding it", unter.below && unter.leftDiff <= 1, unter);
    await shot(page, "timeline-360", "#tl-box");
    await shot(page, "timeline-long-360", "#tl-long-box");
    await page.setViewportSize({ width: 1440, height: 1000 });

    // ═══ THE PORTRAIT VIDEO FRAME ═══════════════════════════════════════════════════════════════

    const FRAMES = ["vf-video", "vf-safe", "vf-img", "vf-wide", "vf-busy", "vf-error"];
    const masse = () => page.evaluate((ids) => Object.fromEntries(ids.map((id) => {
      const f = document.getElementById(id), m = f.querySelector(".gk-video-frame-media"), r = m.getBoundingClientRect(), fr = f.getBoundingClientRect(), cap = f.querySelector(".gk-video-frame-caption").getBoundingClientRect();
      return [id, { w: r.width, h: r.height, ratio: r.width / r.height, frameW: fr.width, left: r.left, right: r.right, top: r.top, bottom: r.bottom, capTop: cap.top, capRight: cap.right, capW: cap.width }];
    })), FRAMES);

    // ── V01 9:16, whatever is inside, at the default size ────────────────────────────────────
    const v1 = await masse();
    check("V01 every frame is 9:16 (video, safe zones, a portrait and a landscape picture, busy, failed)", FRAMES.every((id) => near(v1[id].ratio, 9 / 16, 0.004)), v1);
    check("V01 … 75 % of a 1000px window high at most (750px), and as wide as 9/16 of that (421.9px) although the column is 928px", FRAMES.every((id) => near(v1[id].h, 750, 1) && near(v1[id].w, 421.875, 1)), v1);
    check("V01 the frame is exactly as wide as its box (a caption does not widen it) and the caption lies under the box", FRAMES.every((id) => near(v1[id].frameW, v1[id].w, 0.5) && v1[id].capTop >= v1[id].bottom - 0.5), v1);

    // ── V02 the limit follows the window, and the shape holds when it bites ───────────────────────
    const hoehen = {};
    for (const h of [600, 400, 2000]) {
      await page.setViewportSize({ width: 1440, height: h });
      hoehen[h] = await masse();
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    check("V02 a window 600px high: the frame is 450px high (75 %) and still 9:16; at 400px it is 300px", FRAMES.every((id) => near(hoehen[600][id].h, 450, 1) && near(hoehen[600][id].ratio, 9 / 16, 0.004) && near(hoehen[400][id].h, 300, 1)), hoehen);
    check("V02 a window 2000px high: the column (928px) is the limit — the frame is 843.75px wide (75 % of the height as width/16*9 = 843.75), 9:16",
      FRAMES.every((id) => near(hoehen[2000][id].w, 843.75, 1.5) && near(hoehen[2000][id].ratio, 9 / 16, 0.004)), hoehen[2000]);
    // A column narrower than the limit: the column wins, the shape stays.
    const schmal = {};
    for (const w of [300, 150]) {
      await page.evaluate((w) => { document.getElementById("vf-box").style.width = w + "px"; }, w);
      schmal[w] = await masse();
    }
    await page.evaluate(() => { document.getElementById("vf-box").style.width = ""; });
    check("V02 a column 300px wide: the frame is 300px wide, 533px high, 9:16; at 150px: 150 × 267", FRAMES.every((id) => near(schmal[300][id].w, 300, 1) && near(schmal[300][id].h, 533.3, 1.5) && near(schmal[150][id].w, 150, 1) && near(schmal[150][id].h, 266.7, 1.5)), schmal);

    // ── V03 the video and the pictures lie in the box, whole, and cannot make it taller ────────────
    const im = await page.evaluate(() => {
      const o = {};
      for (const [id, sel] of [["vf-video", "video"], ["vf-safe", "video"], ["vf-img", "img"], ["vf-wide", "img"]]) {
        const m = document.querySelector("#" + id + " .gk-video-frame-media"), el = m.querySelector(sel), r = el.getBoundingClientRect(), b = m.getBoundingClientRect(), c = getComputedStyle(el);
        o[id] = { dl: r.left - b.left, dt: r.top - b.top, dr: b.right - r.right, db: b.bottom - r.bottom, fit: c.objectFit, pos: c.position, bg: c.backgroundColor, display: c.display };
      }
      return o;
    });
    check("V03 the <video> and the <img> fill the box inside its 1px edge, in the box and not in the flow, shown whole (contain) on black",
      Object.values(im).every((o) => near(o.dl, 1, 0.6) && near(o.dt, 1, 0.6) && near(o.dr, 1, 0.6) && near(o.db, 1, 0.6) && o.fit === "contain" && o.pos === "absolute" && o.bg === "rgb(0, 0, 0)" && o.display === "block"), im);
    const roh = await page.evaluate(() => {
      const v = document.querySelector("#vf-video video"), m = document.querySelector("#vf-video .gk-video-frame-media"), c = getComputedStyle(m);
      return { controls: v.hasAttribute("controls"), inline: v.hasAttribute("playsinline"), autoplay: v.autoplay, paused: v.paused, radius: c.borderTopLeftRadius, overflow: c.overflow, name: v.getAttribute("aria-label") };
    });
    await page.waitForTimeout(800);
    const nochPaused = await page.evaluate(() => document.querySelector("#vf-video video").paused);
    check("V03 the video has controls and playsinline, no autoplay: still paused after the page has been up a second", roh.controls && roh.inline && roh.autoplay === false && roh.paused && nochPaused, { roh, nochPaused });
    check("V03 the box is rounded (16px) and cuts what lies in it", roh.radius === "16px" && roh.overflow === "hidden", roh);

    // ── V04 the safe zones: off until asked, a tenth and a fifth, no click caught, a number to change ──
    const zonen = await page.evaluate(() => {
      const z = (id) => {
        const m = document.querySelector("#" + id + " .gk-video-frame-media"), b = getComputedStyle(m, "::before"), a = getComputedStyle(m, "::after");
        return { top: b.content, bottom: a.content, topH: parseFloat(b.height), bottomH: parseFloat(a.height), inner: m.clientHeight, pe: [b.pointerEvents, a.pointerEvents], pos: [b.position, a.position],
                 edge: [b.borderBottomStyle, a.borderTopStyle], z: [b.zIndex, a.zIndex] };
      };
      return { off: z("vf-video"), on: z("vf-safe"), offBusy: z("vf-busy") };
    });
    check("V04 without gk-video-frame-safe nothing is drawn over the picture", zonen.off.top === "none" && zonen.off.bottom === "none" && zonen.offBusy.top === "none", zonen);
    check("V04 with it: the top zone is a tenth and the bottom zone a fifth of the picture, each ending in a dashed line, both over the video and catching no click",
      zonen.on.top === '""' && zonen.on.bottom === '""' && near(zonen.on.topH, zonen.on.inner * 0.1, 1) && near(zonen.on.bottomH, zonen.on.inner * 0.2, 1)
      && zonen.on.pe.every((p) => p === "none") && zonen.on.edge.every((e) => e === "dashed") && zonen.on.pos.every((p) => p === "absolute"), zonen);
    const gleich = await masse();
    check("V04 the zones take no room: the frame with them is exactly as large as the one without", near(gleich["vf-safe"].w, gleich["vf-video"].w, 0.1) && near(gleich["vf-safe"].h, gleich["vf-video"].h, 0.1), gleich);
    // The controls of a video lie in the bottom zone. A click there must reach them. (elementFromPoint and the
    // mouse only see what is in the window: bring the frame into it first.)
    await intoView(page, "#vf-safe .gk-video-frame-media");
    await page.waitForFunction(() => document.querySelector("#vf-safe video").readyState >= 2);   // the file is loaded: the controls are live
    const treffer = await page.evaluate(() => {
      const m = document.querySelector("#vf-safe .gk-video-frame-media"), r = m.getBoundingClientRect();
      const at = (x, y) => { const e = document.elementFromPoint(x, y); return e ? e.tagName + (e.className ? "." + e.className : "") : null; };
      return { bottom: at(r.left + r.width / 2, r.bottom - r.height * 0.1), top: at(r.left + r.width / 2, r.top + r.height * 0.05), mid: at(r.left + r.width / 2, r.top + r.height / 2) };
    });
    check("V04 a point in the bottom zone, one in the top zone and one in the middle all land on the <video>, not on the box or the guide lines",
      Object.values(treffer).every((t) => t === "VIDEO"), treffer);
    await page.evaluate(() => { document.querySelector("#vf-safe video").pause(); });
    const safeBox = await rect(page, "#vf-safe .gk-video-frame-media");
    await page.mouse.click(safeBox.l + 20, safeBox.b - 36);   // the play button of the browser's own controls, bottom left (36px up: measured at 32 to 40)
    const spielt = await page.waitForFunction(() => !document.querySelector("#vf-safe video").paused, null, { timeout: 3000 }).then(() => true, () => false);
    await page.evaluate(() => document.querySelector("#vf-safe video").pause());
    check("V04 a click on the play button of the video, inside the bottom zone, starts it", spielt);
    await page.addStyleTag({ content: "#vf-safe { --gk-video-safe-top: 25%; --gk-video-safe-bottom: 5%; }" });
    const eigen = await page.evaluate(() => { const m = document.querySelector("#vf-safe .gk-video-frame-media"); return { top: parseFloat(getComputedStyle(m, "::before").height) / m.clientHeight, bottom: parseFloat(getComputedStyle(m, "::after").height) / m.clientHeight }; });
    check("V04 a page can change the two numbers (--gk-video-safe-top / -bottom): 25 % and 5 % are drawn as asked", near(eigen.top, 0.25, 0.01) && near(eigen.bottom, 0.05, 0.01), eigen);
    await page.evaluate(() => { document.getElementById("vf-safe").removeAttribute("style"); });
    await shot(page, "video-frames-light-1440", "#vf-box");

    // ── V05 the caption, long words ───────────────────────────────────────────────────────────
    const cap = await page.evaluate(() => { const c = document.querySelector("#vf-wide .gk-video-frame-caption"); return { over: c.scrollWidth - c.clientWidth, lines: Math.round(c.getBoundingClientRect().height / 16), color: getComputedStyle(c).color }; });
    check("V05 a caption with a long address breaks inside the frame (two lines or more, nothing sideways)", cap.over <= 0 && cap.lines >= 2, cap);

    // ── V06 busy and failed: .gk-empty fills the box, in the box ───────────────────────────────
    const leer = await page.evaluate(() => Object.fromEntries(["vf-busy", "vf-error"].map((id) => {
      const near = (a, b, tol) => Math.abs(a - b) <= tol;
      const m = document.querySelector("#" + id + " .gk-video-frame-media"), e = m.querySelector(".gk-empty"), r = e.getBoundingClientRect(), b = m.getBoundingClientRect(), c = getComputedStyle(e), icon = getComputedStyle(m.querySelector(".gk-empty-icon"));
      const inner = m.querySelector(".gk-empty-inner").getBoundingClientRect();
      return [id, { dl: r.left - b.left, dt: r.top - b.top, dr: b.right - r.right, db: b.bottom - r.bottom, pad: c.padding, overflowX: e.scrollWidth - e.clientWidth, overflowY: c.overflowY,
                    centred: near((inner.left + inner.right) / 2, (r.left + r.right) / 2, 1) && near((inner.top + inner.bottom) / 2, (r.top + r.bottom) / 2, 2), edge: getComputedStyle(m).borderTopStyle,
                    edgeColor: getComputedStyle(m).borderTopColor, iconOpacity: icon.opacity, iconColor: icon.color, title: getComputedStyle(m.querySelector(".gk-empty-title")).color, role: e.getAttribute("role") }];
    })));
    check("V06 the empty state fills the box (inside its edge), keeps no 56px of padding, its text is centred, a dashed edge shows the box where nothing is",
      Object.values(leer).every((l) => near(l.dl, 1, 0.6) && near(l.dr, 1, 0.6) && near(l.dt, 1, 0.6) && near(l.db, 1, 0.6) && l.pad === "16px 12px" && l.centred && l.edge === "dashed"), leer);
    check("V06 the busy state is a status; neither state has sideways overflow even with a 230-character address in it", leer["vf-busy"].role === "status" && Object.values(leer).every((l) => l.overflowX <= 0 && l.overflowY === "auto"), leer);
    check("V06 the failed state is in the error colour (edge, symbol, title), the busy one is not", leer["vf-error"].iconColor === leer["vf-error"].title && leer["vf-error"].iconColor !== leer["vf-busy"].iconColor
      && leer["vf-error"].edgeColor !== leer["vf-busy"].edgeColor && leer["vf-error"].iconOpacity === "1" && leer["vf-busy"].iconOpacity !== "0", leer);
    // A frame as small as 120px: the message scrolls inside the box instead of being cut off.
    await page.evaluate(() => { document.getElementById("vf-box").style.width = "120px"; });
    const klein = await page.evaluate(() => { const e = document.querySelector("#vf-error .gk-empty"); return { over: e.scrollWidth - e.clientWidth, scrolls: e.scrollHeight > e.clientHeight }; });
    await page.evaluate(() => { document.getElementById("vf-box").style.width = ""; });
    check("V06 in a frame 120px wide the long message scrolls down inside the box and does not run out sideways", klein.over <= 0 && klein.scrolls, klein);
    const puls = await page.evaluate(() => document.getAnimations().filter((a) => a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest(".gk-video-frame"))
      .map((a) => ({ name: a.animationName, frame: a.effect.target.closest(".gk-video-frame").id })));
    check("V06 the busy symbol pulses (gk-dot-pulse) and nothing else in the frames moves", puls.length === 1 && puls[0].name === "gk-dot-pulse" && puls[0].frame === "vf-busy", puls);
    await page.emulateMedia({ reducedMotion: "reduce" });
    const ruhig = await page.evaluate(() => getComputedStyle(document.querySelector("#vf-busy .gk-empty-icon")).animationName);
    await page.emulateMedia({ reducedMotion: "no-preference" });
    check("V06 reduced motion: the busy symbol has no animation of its own", ruhig === "none", ruhig);

    // ── V07 dark: the same in both spellings, from the roles ───────────────────────────────────
    const farbenIm = () => page.evaluate(() => Object.fromEntries(["vf-busy", "vf-error"].map((id) => {
      const m = document.querySelector("#" + id + " .gk-video-frame-media"), g = (el) => getComputedStyle(el);
      return [id, [g(m).backgroundColor, g(m).borderTopColor, g(m.querySelector(".gk-empty-title")).color, g(m.querySelector(".gk-empty-hint")).color, g(m.querySelector(".gk-empty-icon")).color,
                   g(document.querySelector("#" + id + " .gk-video-frame-caption")).color].join(" | ")];
    })));
    const lightFarben = await farbenIm();
    await mode(page, "dark"); const darkA = await farbenIm();
    await shot(page, "video-frames-dark-1440", "#vf-box");
    await mode(page, "dark-class"); const darkB = await farbenIm();
    await mode(page, "light");
    check("V07 dark: [data-gk-mode=\"dark\"] and .gk-dark paint the empty states and the caption the same, and not as in light",
      JSON.stringify(darkA) === JSON.stringify(darkB) && darkA["vf-busy"] !== lightFarben["vf-busy"] && darkA["vf-error"] !== lightFarben["vf-error"], { lightFarben, darkA, darkB });

    // ── V08 a phone ───────────────────────────────────────────────────────────────────────────
    for (const [w, h] of [[360, 640], [320, 568]]) {
      await page.setViewportSize({ width: w, height: h });
      await settle(page);
      const ph = await masse();
      const quer = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      check(`V08 ${w}×${h}: no sideways scroll, each frame inside the window, 9:16, at most 75 % of the height`,
        quer <= 0 && FRAMES.every((id) => ph[id].right <= w && near(ph[id].ratio, 9 / 16, 0.004) && ph[id].h <= h * 0.75 + 1 && ph[id].capRight <= w + 0.5), { quer, ph });
      if (w === 360) await shot(page, "video-frames-360", "#vf-box");
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
  } finally {
    await browser.close();
  }

  for (const r of results) console.log((r.ok ? "ok    " : "FAIL  ") + r.name + (r.ok || !r.why ? "" : "\n        " + r.why.slice(0, 600)));
  if (pageErrors.length) console.log("page errors:", pageErrors.slice(0, 3));
  const failed = results.filter((r) => !r.ok).length + (pageErrors.length ? 1 : 0);
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error("aborted:", e.message.split("\n")[0]); process.exit(1); });
