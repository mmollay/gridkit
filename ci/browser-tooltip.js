#!/usr/bin/env node
/**
 * The tooltip engine (1.112.0) in a real browser: where a bubble goes at each edge of the
 * window and of a scrolling box, where its arrow points, when it opens and closes (delay,
 * focus, Escape, blur, scroll, touch), one at a time, what a screen reader is told
 * (aria-describedby, read through Chrome's own accessibility tree), the toggletip from the
 * keyboard and the mouse, the help icon's target, the contrast of every tone in light mode and
 * both dark spellings under every theme, the component options (Button, a setData() Table
 * before and after a sort, Form), a modal, content that arrives later, a phone, reduced motion
 * — and the page without the script, with the copy of js/gridkit.js that has no 1.112.0 block
 * and with the script added late.
 *
 * The page is ci/browser-tooltip-fixture.php.
 *
 *     node ci/browser-tooltip.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-tooltip.js
 *
 * GK_CSS / GK_JS are handed to the fixture (other files instead of css/gridkit.css and
 * js/gridkit.js): that is how the counter-checks ran, one rule taken out at a time.
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-tooltip-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const build = (name, args) => {
  const file = path.join(dir, name + ".html");
  fs.writeFileSync(file, execFileSync(php, [path.join(__dirname, "browser-tooltip-fixture.php"), ...args], { maxBuffer: 64 * 1024 * 1024 }));
  return "file://" + file;
};
const THEMES = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
const MODES = ["light", "dark", "dark-class"];
const TONES = ["neutral", "info", "success", "warning", "danger", "light"];

let passed = 0;
const failures = [];
function check(name, ok, detail) {
  if (ok) passed++;
  else failures.push(name + (detail === undefined ? "" : "  " + JSON.stringify(detail).slice(0, 400)));
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Put into every page: what a trigger's bubble looks like right now. */
const HELPERS = () => {
  const box = (r) => r && { l: r.left, t: r.top, r: r.right, b: r.bottom, w: r.width, h: r.height, cx: r.left + r.width / 2, cy: r.top + r.height / 2 };
  window.__tt = {
    bubbleEl(sel) {
      const t = document.querySelector(sel);
      if (!t) return null;
      if (t.hasAttribute("data-gk-toggletip")) {
        const n = t.nextElementSibling;
        return n && n.classList.contains("gk-toggletip") ? n : null;
      }
      if (t.hasAttribute("data-gk-tooltip-rich")) return document.querySelector(t.getAttribute("data-gk-tooltip-rich"));
      return document.querySelector(".gk-tip");
    },
    state(sel) {
      const t = document.querySelector(sel);
      const b = this.bubbleEl(sel);
      const shown = !!b && b.hasAttribute("data-gk-side") && !b.hidden && getComputedStyle(b).display !== "none";
      const a = b && b.querySelector(":scope > .gk-tip-arrow");
      const text = b ? [...b.childNodes].filter((n) => !(n.nodeType === 1 && n.matches(".gk-tip-arrow, kbd"))).map((n) => n.textContent).join("").trim() : "";
      return {
        open: shown, side: b && b.getAttribute("data-gk-side"), text, tone: b && b.getAttribute("data-gk-tone"),
        kbd: b && b.querySelector("kbd") ? b.querySelector("kbd").textContent : null,
        rect: b && shown ? box(b.getBoundingClientRect()) : null,
        trig: t ? box(t.getBoundingClientRect()) : null,
        arrow: a && shown ? box(a.getBoundingClientRect()) : null,
        vw: document.documentElement.clientWidth, vh: document.documentElement.clientHeight,
      };
    },
    /** Any CSS colour as [r, g, b, a], through a canvas: oklab() and color() included. */
    rgba(str) {
      const c = document.createElement("canvas");
      c.width = c.height = 1;
      const x = c.getContext("2d", { willReadFrequently: true });
      x.clearRect(0, 0, 1, 1);
      x.fillStyle = "#000";
      x.fillStyle = str;
      x.fillRect(0, 0, 1, 1);
      const d = x.getImageData(0, 0, 1, 1).data;
      return [d[0], d[1], d[2], d[3] / 255];
    },
    over(top, under) {
      const a = top[3];
      return [0, 1, 2].map((i) => top[i] * a + under[i] * (1 - a)).concat(1);
    },
    contrast(a, b) {
      const lum = (c) => {
        const [r, g, bl] = c.slice(0, 3).map((v) => {
          v /= 255;
          return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
      };
      const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
      return (x + 0.05) / (y + 0.05);
    },
    /** Text, kbd and link contrast of an open bubble against its own ground. */
    colours(sel) {
      const b = this.bubbleEl(sel);
      const s = getComputedStyle(b);
      const page = this.rgba(getComputedStyle(document.body).backgroundColor);
      const bg = this.over(this.rgba(s.backgroundColor), page);
      const fg = this.over(this.rgba(s.color), bg);
      const out = { text: this.contrast(fg, bg), bg: s.backgroundColor, fg: s.color };
      const k = b.querySelector("kbd");
      if (k) {
        const ks = getComputedStyle(k);
        const kbg = this.over(this.rgba(ks.backgroundColor), bg);
        out.kbd = this.contrast(this.over(this.rgba(ks.color), kbg), kbg);
      }
      const a = b.querySelector("a");
      if (a) out.link = this.contrast(this.over(this.rgba(getComputedStyle(a).color), bg), bg);
      out.line = s.borderTopColor;
      return out;
    },
  };
};

async function openPage(browser, url, ctxOpts = {}) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, ...ctxOpts });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(url, { waitUntil: "load" });
  await page.evaluate(HELPERS);
  await sleep(150);
  return { context, page, errors };
}

/** Name and description as Chrome's accessibility tree has them. */
async function ax(page, sel) {
  const cdp = await page.context().newCDPSession(page);
  try {
    const { root } = await cdp.send("DOM.getDocument", { depth: 0 });
    const { nodeId } = await cdp.send("DOM.querySelector", { nodeId: root.nodeId, selector: sel });
    if (!nodeId) return null;
    const { nodes } = await cdp.send("Accessibility.getPartialAXTree", { nodeId, fetchRelatives: false });
    const n = nodes.find((x) => !x.ignored) || nodes[0];
    return { name: n.name ? n.name.value : "", description: n.description ? n.description.value : "", role: n.role ? n.role.value : "" };
  } finally {
    await cdp.detach();
  }
}

const state = (page, sel) => page.evaluate((s) => window.__tt.state(s), sel);
const within = (r, s, pad = 0) => r && r.l >= -0.5 + pad && r.t >= -0.5 + pad && r.r <= s.vw + 0.5 - pad && r.b <= s.vh + 0.5 - pad;
/** Away from every trigger, and cold: no tooltip shown in the last 300 ms. */
async function rest(page) {
  await page.mouse.move(640, 760);
  await sleep(450);
}

(async () => {
  const PAGE = {
    light: build("light", []),
    dark: build("dark", ["--mode=dark"]),
    darkClass: build("dark-class", ["--mode=dark-class"]),
    noJs: build("no-js", ["--no-js"]),
    oldJs: build("old-js", ["--old-js"]),
    late: build("late", ["--late"]),
  };
  const browser = await chromium.launch();
  try {
    // ── The light page, with a mouse ─────────────────────────────────────
    {
      const { context, page, errors } = await openPage(browser, PAGE.light);

      const flag = await page.evaluate(() => ({
        tt: document.documentElement.hasAttribute("data-gk-tt"),
        after: getComputedStyle(document.querySelector("#plain"), "::after").content,
        tips: document.querySelectorAll(".gk-tip").length,
      }));
      check("A1 the engine marks <html data-gk-tt> and the CSS-only ::after is off", flag.tt && flag.after === "none", flag);
      check("A2 no bubble before the first tooltip", flag.tips === 0, flag);

      // Delay: 300 ms by default, 0 and 800 on request; the grace on leaving.
      await rest(page);
      await page.hover("#plain");
      await sleep(150);
      const early = await state(page, "#plain");
      await sleep(300);
      const later = await state(page, "#plain");
      check("D1 a hover opens after the delay, not before (150 ms: closed, 450 ms: open)", !early.open && later.open && later.text === "Plain text", { early: early.open, later });
      check("D2 the default side is top: the bubble above the trigger, the arrow below it", later.side === "top" && later.rect.b <= later.trig.t - 4 && later.arrow.cy > later.rect.b - 6, later);
      check("D3 the arrow points at the middle of the trigger", Math.abs(later.arrow.cx - later.trig.cx) <= 1.5, { arrow: later.arrow, trig: later.trig });
      await page.mouse.move(640, 760);
      await sleep(40);
      const grace = await state(page, "#plain");
      await sleep(200);
      const gone = await state(page, "#plain");
      check("D4 leaving: still open in the grace period, closed after it", grace.open && !gone.open, { grace: grace.open, gone: gone.open });

      await rest(page);
      await page.hover("#delay0");
      await sleep(40);
      check("D5 data-gk-tooltip-delay=0 opens at once", (await state(page, "#delay0")).open);
      await rest(page);
      await page.hover("#delay800");
      await sleep(550);
      const d800a = await state(page, "#delay800");
      await sleep(450);
      const d800b = await state(page, "#delay800");
      check("D6 data-gk-tooltip-delay=800: closed at 550 ms, open at 1000 ms", !d800a.open && d800b.open, { a: d800a.open, b: d800b.open });

      // The pointer may cross into the bubble and rest there (WCAG 1.4.13).
      const into = await state(page, "#delay800");
      if (into.rect) {
        await page.mouse.move(into.rect.cx, into.rect.cy, { steps: 4 });
        await sleep(300);
      }
      check("D7 the pointer resting on the bubble keeps it open", !!into.rect && (await state(page, "#delay800")).open, into.rect);
      await rest(page);

      // One at a time: moving on opens the next at once and closes the first.
      await page.hover("#plain");
      await sleep(400);
      await page.hover("#other");
      await sleep(60);
      const other = await state(page, "#other");
      const visible = await page.evaluate(() => [...document.querySelectorAll(".gk-tip, .gk-tooltip-content, .gk-toggletip")].filter((b) => b.hasAttribute("data-gk-side")).length);
      check("O1 one tooltip at a time; moving on from an open one shows the next at once", other.open && other.text === "The other one" && visible === 1, { other, visible });
      check("O2 pos=bottom opens below", other.side === "bottom" && other.rect.t >= other.trig.b + 4, other);
      await page.evaluate(() => GK.tooltip.show(document.querySelector("#rich")));
      await page.evaluate(() => GK.tooltip.show(document.querySelector("#plain")));
      const pair = await page.evaluate(() => [...document.querySelectorAll(".gk-tip, .gk-tooltip-content, .gk-toggletip")].filter((b) => b.hasAttribute("data-gk-side")).length);
      check("O3 one at a time across forms: a plain tooltip closes an open rich one", pair === 1, pair);
      await page.evaluate(() => GK.tooltip.hide());
      await rest(page);

      // The edges of the window.
      const EDGES = {
        "#edge-top": "bottom", "#edge-bottom": "top", "#edge-left": "right", "#edge-right": "left",
        "#corner-tl": "bottom", "#corner-br": "top", "#auto-top": "bottom", "#auto-bottom": "top",
      };
      for (const [sel, side] of Object.entries(EDGES)) {
        await page.evaluate((s) => GK.tooltip.show(document.querySelector(s)), sel);
        const s = await state(page, sel);
        check(`P1 ${sel} flips to ${side} and stays inside the window`, s.open && s.side === side && within(s.rect, s, 7.5), s);
        const vertical = side === "top" || side === "bottom";
        const pointsAt = vertical ? Math.abs(s.arrow.cx - s.trig.cx) <= 1.5 : Math.abs(s.arrow.cy - s.trig.cy) <= 1.5;
        const faces = side === "bottom" ? s.arrow.cy < s.rect.t + 1 : side === "top" ? s.arrow.cy > s.rect.b - 1
          : side === "right" ? s.arrow.cx < s.rect.l + 1 : s.arrow.cx > s.rect.r - 1;
        check(`P2 ${sel}: the arrow is on the side facing the trigger and points at it`, pointsAt && faces, { arrow: s.arrow, trig: s.trig, rect: s.rect });
      }
      {
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#corner-tl")));
        const s = await state(page, "#corner-tl");
        check("P3 a bubble wider than the room beside a corner is shifted to 8px from the edge", Math.abs(s.rect.l - 8) <= 1 && s.rect.cx > s.trig.cx + 20, s);
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#auto-right")));
        const a = await state(page, "#auto-right");
        check("P4 pos=auto at the right edge goes above or below, shifted to stay inside", (a.side === "top" || a.side === "bottom") && a.rect.r <= a.vw - 7.5, a);
      }
      await page.evaluate(() => GK.tooltip.hide());
      {
        await page.evaluate(() => document.querySelector("#xf").scrollIntoView({ block: "center" }));
        await page.click("#xf .gk-help");
        await sleep(30);
        const x = await state(page, "#xf .gk-help");
        check("P5 inside a transformed box (which holds position:fixed) the toggletip still sits at its icon", x.open && x.side === "top" && Math.abs(x.arrow.cx - x.trig.cx) <= 1.5 && x.rect.b <= x.trig.t - 4 && x.rect.b >= x.trig.t - 12, x);
        await page.keyboard.press("Escape");
        await page.evaluate(() => window.scrollTo(0, 0));
      }

      // A box that scrolls: its visible part is the room.
      {
        await page.evaluate(() => document.querySelector("#box").scrollIntoView({ block: "center" }));
        const boxR = await page.evaluate(() => { const b = document.querySelector("#box"), r = b.getBoundingClientRect(); return { l: r.left + b.clientLeft, t: r.top + b.clientTop, r: r.left + b.clientLeft + b.clientWidth, b: r.top + b.clientTop + b.clientHeight }; });
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#box-top")));
        const t = await state(page, "#box-top");
        check("S1 at the top of a scrolling box, pos=top flips below, inside the box", t.open && t.side === "bottom" && t.rect.t >= boxR.t && t.rect.b <= boxR.b, { t, boxR });
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#box-right")));
        const r = await state(page, "#box-right");
        check("S2 at the right of a scrolling box the bubble shifts left to stay inside it", r.open && r.rect.r <= boxR.r - 7.5 && r.rect.l >= boxR.l + 7.5, { r: r.rect, boxR });
        check("S3 ... and its arrow still points at the trigger", Math.abs(r.arrow.cx - r.trig.cx) <= 1.5, { arrow: r.arrow, trig: r.trig });
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#box-scroll")));
        const before = await state(page, "#box-scroll");
        await page.evaluate(() => { document.querySelector("#box").scrollTop = 20; });
        await sleep(120);
        const moved = await state(page, "#box-scroll");
        check("S4 a small scroll moves the bubble with its trigger", moved.open && Math.abs((before.rect.t - moved.rect.t) - 20) <= 1.5, { before: before.rect, moved: moved.rect });
        await page.evaluate(() => { document.querySelector("#box").scrollTop = 260; });
        await sleep(120);
        check("S5 scrolled out of the box's visible part, it closes", !(await state(page, "#box-scroll")).open);
        await page.evaluate(() => { document.querySelector("#box").scrollTop = 0; window.scrollTo(0, 0); });
      }

      // The keyboard.
      {
        await page.evaluate(() => {
          window.__esc = [];
          document.addEventListener("keydown", (e) => { if (e.key === "Escape") window.__esc.push(e.defaultPrevented); });
          document.querySelector("#delay0").focus();
        });
        await page.keyboard.press("Shift+Tab");
        await sleep(30);
        const f = await state(page, "#plain");
        const focused = await page.evaluate(() => document.activeElement.id);
        check("F1 keyboard focus opens at once (no delay), the trigger was made focusable", focused === "plain" && f.open && f.text === "Plain text", { focused, f });
        await page.keyboard.press("Tab");
        await sleep(30);
        const next = await state(page, "#delay0");
        check("F2 Tab on: the next one opens, the first closes (one at a time)", next.open && next.text === "No delay", next);
        await page.keyboard.press("Escape");
        await sleep(30);
        const esc = await page.evaluate(() => window.__esc.slice());
        check("F3 Escape closes it, and claims the key (a modal around it would stay)", !(await state(page, "#delay0")).open && esc[esc.length - 1] === true, esc);
        await page.keyboard.press("Tab");
        await sleep(30);
        check("F4 ... and the next Tab opens the next one", (await state(page, "#delay800")).open);
        await page.evaluate(() => document.activeElement.blur());
        await sleep(30);
        check("F5 blur closes it", !(await state(page, "#delay800")).open);
        // A mouse click on a button: no tooltip from the focus it gives.
        await rest(page);
        await page.click("#btn-click");
        await sleep(120);
        const clicked = await state(page, "#btn-click");
        check("F6 a mouse click focuses a button but opens no tooltip, and closes one that was open", !clicked.open, clicked);
        await page.evaluate(() => document.activeElement.blur());
      }

      // What a screen reader is told.
      {
        await rest(page);
        await page.hover("#title-btn");
        await sleep(400);
        await page.hover("#title-icon");
        await sleep(400);
        const cases = {
          "#plain": { description: "Plain text" },
          "#kbd": { description: "Save (Ctrl+S)" },
          "#btn-tip": { name: "Save", description: "Saves the draft (Ctrl+S)" },
          "#btn-icon": { name: "Delete", description: "Deletes the record for good" },
          "#wrapped": { name: "Inner", description: "Wrapper tip" },
          "#rich": { description: "Rich tooltip with a link." },
          "#title-btn": { name: "Save", description: "Save the current draft" },
          "#title-icon": { name: "Delete row" },
        };
        for (const [sel, want] of Object.entries(cases)) {
          const got = await ax(page, sel);
          // Chrome puts a space between a link and the full stop after it in a hidden subtree.
          const tidy = (x) => String(x).replace(/\s+([.,])/g, "$1");
          const ok = got && (want.name === undefined || got.name === want.name) && (want.description === undefined || tidy(got.description) === want.description);
          check(`R1 ${sel}: name and description in the accessibility tree`, ok, { want, got });
        }
        const btnTitle = await page.evaluate(() => ({ title: document.querySelector("#btn-tip").getAttribute("title"), icon: document.querySelector("#btn-icon").getAttribute("aria-label") }));
        check("R2 Button 'tooltip' writes no title (no second popup) and keeps the icon button's name", btnTitle.title === null && btnTitle.icon === "Delete", btnTitle);
      }

      // title, through GK.tip on the engine.
      {
        await rest(page);
        await page.hover("#title-span");
        await sleep(400);
        const s = await state(page, "#title-span");
        const attrs = await page.evaluate(() => { const e = document.querySelector("#title-span"); return { title: e.getAttribute("title"), tip: e.getAttribute("data-gk-tip") }; });
        const lineH = await page.evaluate(() => parseFloat(getComputedStyle(document.querySelector(".gk-tip")).lineHeight));
        check("T1 a title becomes a GridKit bubble; the title moves to data-gk-tip", s.open && s.text === "Title text\nline two" && attrs.title === null && attrs.tip === "Title text\nline two", { s, attrs });
        check("T2 a line break in a title breaks the line", s.rect.h > lineH * 1.8, { h: s.rect.h, lineH });
        await rest(page);
        await page.hover("#tip-off");
        await sleep(450);
        const off = await page.evaluate(() => ({ title: document.querySelector("#tip-off").getAttribute("title"), shown: [...document.querySelectorAll(".gk-tip")].some((b) => !b.hidden) }));
        check("T3 data-gk-tip-off: the title stays the browser's own, no bubble", off.title === "The browser's own" && !off.shown, off);
        await page.evaluate(() => GK.tip.show(document.querySelector("#title-btn")));
        check("T4 GK.tip.show() opens it at once", (await state(page, "#title-btn")).open);
        await page.evaluate(() => GK.tip.hide());
        check("T5 GK.tip.hide() closes it", !(await state(page, "#title-btn")).open);
      }

      // The options.
      {
        await rest(page);
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#kbd")));
        const k = await state(page, "#kbd");
        check("K1 data-gk-tooltip-kbd: a <kbd> after the text", k.open && k.text === "Save" && k.kbd === "Ctrl+S", k);
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#max")));
        const m = await state(page, "#max");
        check("K2 data-gk-tooltip-max=120: no wider than 120px", m.open && m.rect.w <= 120.5, m.rect);
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#wrap")));
        const w = await state(page, "#wrap");
        check("K3 data-gk-tooltip-wrap: no wider than 280px", w.open && w.rect.w <= 280.5 && w.rect.h > 20, w.rect);
        for (const t of TONES) {
          await page.evaluate((s) => GK.tooltip.show(document.querySelector(s)), "#tone-" + t);
          const s = await state(page, "#tone-" + t);
          check(`K4 data-gk-tooltip-tone=${t} reaches the bubble`, s.open && s.tone === t, s);
        }
        await page.evaluate(() => GK.tooltip.hide());
      }

      // Rich.
      {
        await rest(page);
        await page.hover("#rich");
        await sleep(400);
        const r = await state(page, "#rich");
        check("H1 rich: the element is the bubble, placed beside its trigger with an arrow", r.open && r.side === "top" && r.rect.b <= r.trig.t - 4 && !!r.arrow, r);
        await page.mouse.move(r.trig.cx, r.trig.t - 4, { steps: 2 });
        // Without the grace the bubble is gone before the pointer gets there: report, do not throw.
        await page.hover("#rich-link", { timeout: 1500 }).catch(() => {});
        await sleep(300);
        check("H2 rich: the pointer can move into it and onto its link", (await state(page, "#rich")).open);
        await rest(page);
        check("H3 rich: leaving both closes it", !(await state(page, "#rich")).open);
      }

      // Toggletip.
      {
        await rest(page);
        await page.click("#tt-text");
        await sleep(30);
        const a = await state(page, "#tt-text");
        const aria = await page.evaluate(() => { const t = document.querySelector("#tt-text"); return { expanded: t.getAttribute("aria-expanded"), role: t.nextElementSibling.getAttribute("role") }; });
        check("G1 a click opens a toggletip; it says so (aria-expanded), its bubble is a live region", a.open && a.text === "Toggle text" && a.side === "bottom" && aria.expanded === "true" && aria.role === "status", { a, aria });
        await page.mouse.move(640, 760);
        await sleep(400);
        check("G2 it does not close when the pointer leaves", (await state(page, "#tt-text")).open);
        await page.click("#tt-text");
        await sleep(30);
        check("G3 a second click closes it", !(await state(page, "#tt-text")).open);
        await page.click("#tt-text");
        await page.click("h1");
        await sleep(30);
        check("G4 a click outside closes it", !(await state(page, "#tt-text")).open);
        // Keyboard: Enter on a button, Space on a span, Tab into its links, Escape back.
        await page.focus("#tt-text");
        await page.keyboard.press("Enter");
        await sleep(30);
        check("G5 Enter opens it, the focus stays on the trigger", (await state(page, "#tt-text")).open && (await page.evaluate(() => document.activeElement.id)) === "tt-text");
        await page.keyboard.press("Tab");
        await sleep(30);
        const afterTab = await page.evaluate(() => document.activeElement.id);
        check("G6 Tab past a toggletip with nothing to focus moves on and closes it", afterTab === "tt-span" && !(await state(page, "#tt-text")).open, afterTab);
        await page.keyboard.press(" ");
        await sleep(30);
        const sp = await state(page, "#tt-span");
        const moved = await page.evaluate(() => ({ inBubble: !!document.querySelector("#tt-span + .gk-toggletip #tt-content"), hidden: document.querySelector("#tt-content").hidden }));
        check("G7 Space opens a toggletip on a span; '#id' content moves in and shows", sp.open && sp.text.startsWith("Content with") && moved.inBubble && !moved.hidden, { sp, moved });
        await page.keyboard.press("Tab");
        const l1 = await page.evaluate(() => document.activeElement.id);
        await page.keyboard.press("Tab");
        const l2 = await page.evaluate(() => document.activeElement.id);
        check("G8 Tab walks into its link and its button; it stays open", l1 === "tt-link" && l2 === "tt-btn" && (await state(page, "#tt-span")).open, { l1, l2 });
        await page.keyboard.press("Escape");
        await sleep(30);
        const back = await page.evaluate(() => ({ focus: document.activeElement.id, home: document.querySelector("#tt-content").parentElement.tagName, hidden: document.querySelector("#tt-content").hidden, expanded: document.querySelector("#tt-span").getAttribute("aria-expanded") }));
        check("G9 Escape closes it, the focus returns to the trigger, the content goes back hidden", !(await state(page, "#tt-span")).open && back.focus === "tt-span" && back.home === "MAIN" && back.hidden && back.expanded === "false", back);
        // The help icon.
        const help = await page.evaluate(() => { const h = document.querySelector("#help-host .gk-help"), r = h.getBoundingClientRect(); return { w: r.width, h: r.height, name: h.getAttribute("aria-label"), type: h.type }; });
        check("G10 .gk-help is a 24px button named after what it explains", help.w === 24 && help.h === 24 && help.name === "More about VAT" && help.type === "button", help);
        await page.click("#help-host .gk-help");
        await sleep(30);
        const hs = await state(page, "#help-host .gk-help");
        check("G11 .gk-help opens its toggletip", hs.open && hs.text === "Reduced rate for food and books.", hs);
        await page.keyboard.press("Escape");
        // One toggletip at a time.
        await page.click("#tt-text");
        await page.click("#help-host .gk-help");
        await sleep(30);
        check("G12 one toggletip at a time", !(await state(page, "#tt-text")).open && (await state(page, "#help-host .gk-help")).open);
        await page.keyboard.press("Escape");
        // ... also when nothing moves the focus (Safari does not focus a button on a click).
        await page.evaluate(() => { GK.tooltip.show(document.querySelector("#tt-text")); GK.tooltip.show(document.querySelector("#help-host .gk-help")); });
        await sleep(30);
        check("G13 one toggletip at a time without a focus move", !(await state(page, "#tt-text")).open && (await state(page, "#help-host .gk-help")).open);
        await page.evaluate(() => GK.tooltip.hide());
      }

      // Table: the help icon in the header, the row button tooltip, before and after a sort.
      {
        await page.evaluate(() => document.querySelector("#table-host").scrollIntoView({ block: "center" }));
        const head = () => page.evaluate(() => {
          const th = [...document.querySelectorAll("#table-host thead th")].find((t) => t.textContent.includes("Name"));
          const sort = th.querySelector(".gk-sort-btn"), help = th.querySelector(".gk-help");
          const sr = sort.getBoundingClientRect(), hr = help ? help.getBoundingClientRect() : null, tr = th.getBoundingClientRect();
          return { sorted: th.getAttribute("aria-sort"), helpInSort: !!sort.querySelector(".gk-help"), help: !!help,
                   sameLine: !!hr && Math.abs((hr.top + hr.height / 2) - (sr.top + sr.height / 2)) <= 4 && hr.left >= sr.right - 1 && hr.right <= tr.right + 0.5 };
        });
        const rowBtns = () => page.evaluate(() => [...document.querySelectorAll("#table-host tbody button[data-gk-action]")].map((b) => ({ a: b.dataset.gkAction, tip: b.getAttribute("data-gk-tooltip"), tone: b.getAttribute("data-gk-tooltip-tone"), title: b.getAttribute("title"), name: b.getAttribute("aria-label") })));
        const h0 = await head();
        check("B1 a column 'tooltip' is a help icon beside the sort button, not inside it, on one line, inside its cell", h0.help && !h0.helpInSort && h0.sameLine, h0);
        await page.click("#table-host thead .gk-help");
        await sleep(30);
        const h1 = await head();
        check("B2 a click on it opens its toggletip and does not sort", (await state(page, "#table-host thead .gk-help")).open && h1.sorted === "none", h1);
        await page.keyboard.press("Escape");
        const b0 = await rowBtns();
        const okBtns = (list) => list.length === 6 && list.every((b) => b.title === null && (b.a === "edit" ? b.tip === "Edit this person" && b.name === "Edit" : b.tip === "Removes the person for good" && b.tone === "danger" && b.name === "Remove"));
        check("B3 a row button 'tooltip': its attributes instead of a title; the name stays", okBtns(b0), b0);
        await page.click("#table-host thead .gk-sort-btn");
        await sleep(120);
        const h2 = await head();
        const b2 = await rowBtns();
        check("B4 after a sort (the client rebuild) the header keeps its help icon", h2.sorted === "ascending" && h2.help && !h2.helpInSort && h2.sameLine, h2);
        check("B5 after a sort the row buttons keep their tooltips, still without a title", okBtns(b2), b2);
        const helpThere = (await page.locator("#table-host thead .gk-help").count()) > 0;
        if (helpThere) {
          await page.click("#table-host thead .gk-help");
          await sleep(200);
        }
        check("B6 the help icon of the rebuilt header opens", helpThere && (await state(page, "#table-host thead .gk-help")).open);
        await page.keyboard.press("Escape");
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#table-host tbody button[data-gk-action='del']")));
        await sleep(30);
        const del = await state(page, "#table-host tbody button[data-gk-action='del']");
        check("B7 the rebuilt row button's tooltip opens with its tone", del.open && del.text === "Removes the person for good" && del.tone === "danger", del);
        await page.evaluate(() => GK.tooltip.hide());
      }

      // Form: the help icon beside the label, the field's name unchanged.
      {
        const f = await page.evaluate(() => {
          const row = document.querySelector("#form-host .gk-label-row");
          return { row: !!row, label: row && !!row.querySelector("label[for=email]"), help: row && !!row.querySelector(".gk-help"), inLabel: !!document.querySelector("label .gk-help"),
                   cb: !!document.querySelector("#form-host .gk-checkbox-wrap + .gk-help"), plainRow: !!document.querySelector("label[for=plain]").closest(".gk-label-row") };
        });
        check("C1 Form 'help': label and icon in a .gk-label-row, the icon never inside the label", f.row && f.label && f.help && !f.inLabel && !f.plainRow, f);
        check("C2 a checkbox's help follows the checkbox", f.cb, f);
        const name = await ax(page, "#email");
        check("C3 the field's name is its label alone", name && name.name === "Email", name);
        await page.click("#form-host .gk-label-row .gk-help");
        await sleep(30);
        const s = await state(page, "#form-host .gk-label-row .gk-help");
        check("C4 the help opens its text", s.open && s.text === "We never share it.", s);
        await page.keyboard.press("Escape");
      }

      // Content that arrives later.
      {
        await page.evaluate(() => {
          const host = document.querySelector("#later-host");
          host.innerHTML = JSON.parse(document.getElementById("gk-fixture").textContent).later;
          GK.initContent(host);
        });
        const later = await ax(page, "#later-plain");
        const tt = await page.evaluate(() => { const t = document.querySelector("#later-tt"); return { expanded: t.getAttribute("aria-expanded"), live: !!(t.nextElementSibling && t.nextElementSibling.matches(".gk-toggletip[role=status]")) }; });
        check("L1 after GK.initContent(): the plain tooltip describes its trigger, the toggletip has its live region", later && later.description === "Arrived later" && tt.expanded === "false" && tt.live, { later, tt });
        // A live region just made waits 150 ms before it speaks (see prepToggle).
        await page.click("#later-tt");
        await sleep(200);
        check("L2 ... and the toggletip opens", (await state(page, "#later-tt")).open);
        await page.keyboard.press("Escape");
      }

      // A modal: above it, and Escape closes the toggletip first.
      {
        await page.click("#open-modal");
        await sleep(200);
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#m-tip")));
        const s = await state(page, "#m-tip");
        const top = await page.evaluate((r) => { const e = document.elementFromPoint(r.cx, r.cy); return !!(e && e.closest(".gk-tip")); }, s.rect);
        check("M1 a tooltip in a modal shows above it", s.open && top, s);
        await page.evaluate(() => GK.tooltip.hide());
        await page.click("#m-help .gk-help");
        await sleep(30);
        const h = await state(page, "#m-help .gk-help");
        const hTop = h.rect && await page.evaluate((r) => { const e = document.elementFromPoint(r.cx, r.cy); return !!(e && e.closest(".gk-toggletip")); }, h.rect);
        check("M2 a toggletip in a modal is placed beside its icon and on top", h.open && hTop && Math.abs(h.rect.cx - h.trig.cx) < h.rect.w / 2 + 1, h);
        await page.keyboard.press("Escape");
        await sleep(60);
        const after1 = await page.evaluate(() => ({ modal: !document.querySelector("#m").hidden }));
        check("M3 Escape closes the toggletip and the modal stays", !(await state(page, "#m-help .gk-help")).open && after1.modal, after1);
        await page.keyboard.press("Escape");
        await sleep(200);
        check("M4 the next Escape closes the modal", await page.evaluate(() => document.querySelector("#m").hidden || getComputedStyle(document.querySelector("#m")).display === "none"));
      }

      check("Z1 no script error on the light page", errors.length === 0, errors);
      await context.close();
    }

    // ── Reduced motion ───────────────────────────────────────────────────
    for (const reduce of [false, true]) {
      const { context, page } = await openPage(browser, PAGE.light, { reducedMotion: reduce ? "reduce" : "no-preference" });
      await page.evaluate(() => GK.tooltip.show(document.querySelector("#plain")));
      await page.click("#tt-text");
      await sleep(30);
      const anim = await page.evaluate(() => [getComputedStyle(document.querySelector(".gk-tip")).animationName, getComputedStyle(document.querySelector("#tt-text + .gk-toggletip")).animationName]);
      check(`V1 prefers-reduced-motion: ${reduce ? "reduce — no animation" : "no-preference — the fade"}`, reduce ? anim.every((a) => a === "none") : anim.every((a) => a === "gk-tip-in"), anim);
      await context.close();
    }

    // ── A phone: 390px, with a finger ────────────────────────────────────
    {
      const { context, page, errors } = await openPage(browser, PAGE.light, { viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true });
      const sw0 = await page.evaluate(() => document.documentElement.scrollWidth);
      for (const sel of ["#edge-left", "#edge-right", "#corner-tl", "#corner-br", "#auto-right", "#plain", "#wrap", "#tt-text"]) {
        if (sel === "#tt-text") {
          await page.evaluate(() => GK.tooltip.hide());
          await page.tap(sel);
          await sleep(40);
        } else {
          await page.evaluate((s) => GK.tooltip.show(document.querySelector(s)), sel);
        }
        const s = await state(page, sel);
        const sw = await page.evaluate(() => document.documentElement.scrollWidth);
        check(`N1 390px: ${sel} stays inside the screen and widens nothing`, s.open && within(s.rect, s, 7.5) && sw <= sw0, { rect: s.rect, vw: s.vw, sw, sw0 });
      }
      await page.evaluate(() => GK.tooltip.hide());
      // Touch.
      await page.evaluate(() => document.querySelector("#plain").scrollIntoView({ block: "center" }));
      await page.tap("#plain");
      await sleep(40);
      check("N2 a tap on a trigger that is only a trigger shows its tooltip", (await state(page, "#plain")).open);
      await page.tap("#plain");
      await sleep(40);
      check("N3 a second tap hides it", !(await state(page, "#plain")).open);
      await page.tap("#plain");
      await page.tap("h1");
      await sleep(40);
      check("N4 a tap elsewhere hides it", !(await state(page, "#plain")).open);
      await page.evaluate(() => document.querySelector("#btn-click").scrollIntoView({ block: "center" }));
      await page.tap("#btn-click");
      await sleep(400);
      const clicks = await page.evaluate(() => window.clicks || 0);
      check("N5 a tap on a button with a tooltip is its click, counted once, no bubble", clicks === 1 && !(await state(page, "#btn-click")).open, { clicks });
      await page.tap("#link-tip");
      await sleep(100);
      check("N6 a tap on a link with a tooltip follows the link", (await page.evaluate(() => location.hash)) === "#went");
      await page.evaluate(() => document.querySelector("#title-span").scrollIntoView({ block: "center" }));
      await page.tap("#title-span");
      await sleep(40);
      const ts = await state(page, "#title-span");
      check("N7 a tap on an element with a title shows the title (the browser shows none on touch)", ts.open && ts.text.startsWith("Title text"), ts);
      // The help icon to a finger: 44px.
      await page.evaluate(() => document.querySelector("#help-host").scrollIntoView({ block: "center" }));
      const hit = await page.evaluate(() => {
        const h = document.querySelector("#help-host .gk-help"), r = h.getBoundingClientRect();
        const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        const at = (x, y) => { const e = document.elementFromPoint(x, y); return !!(e && e.closest(".gk-help") === h); };
        return { coarse: matchMedia("(pointer: coarse)").matches, w: r.width, edges: [at(cx - 21, cy), at(cx + 21, cy), at(cx, cy - 21), at(cx, cy + 21)], beyond: at(cx + 24, cy) };
      });
      check("N8 .gk-help: 24px to look at, 44px to a finger", hit.coarse && hit.w === 24 && hit.edges.every(Boolean) && !hit.beyond, hit);
      await page.tap("#help-host .gk-help");
      await sleep(40);
      check("N9 a tap on the help icon opens its toggletip", (await state(page, "#help-host .gk-help")).open);
      check("Z2 no script error on the phone", errors.length === 0, errors);
      await context.close();
    }

    // ── Contrast: every tone, the toggletip, the rich tooltip — light, both dark spellings, every theme ──
    const lowest = {};
    const note = (what, v) => { if (v !== undefined && (lowest[what] === undefined || v < lowest[what])) lowest[what] = v; };
    for (const mode of MODES) {
      for (const theme of THEMES) {
        const url = build(`c-${mode}-${theme || "none"}`, [`--mode=${mode}`, ...(theme ? [`--theme=${theme}`] : [])]);
        const { context, page } = await openPage(browser, url);
        const where = `${mode}${theme ? "/" + theme : ""}`;
        for (const t of TONES) {
          await page.evaluate((s) => GK.tooltip.show(document.querySelector(s)), "#tone-" + t);
          const c = await page.evaluate((s) => window.__tt.colours(s), "#tone-" + t);
          check(`X1 ${where}: tone ${t} — text and kbd at 4.5:1 or more`, c.text >= 4.5 && c.kbd >= 4.5, c);
          note(`tone ${t} text`, c.text);
          note(`tone ${t} kbd`, c.kbd);
        }
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#plain")));
        const plain = await page.evaluate(() => window.__tt.colours("#plain"));
        check(`X2 ${where}: the default bubble at 4.5:1 or more`, plain.text >= 4.5, plain);
        note("default bubble", plain.text);
        if (mode !== "light") check(`X3 ${where}: on a dark page the slate bubble has a light edge`, plain.line !== "rgba(0, 0, 0, 0)", plain);
        await page.evaluate(() => GK.tooltip.hide());
        await page.click("#tt-span");
        await sleep(30);
        const tt = await page.evaluate(() => window.__tt.colours("#tt-span"));
        check(`X4 ${where}: the toggletip card — text and link at 4.5:1 or more`, tt.text >= 4.5 && tt.link >= 4.5, tt);
        note("toggletip text", tt.text);
        note("toggletip link", tt.link);
        await page.keyboard.press("Escape");
        await page.evaluate(() => GK.tooltip.show(document.querySelector("#rich")));
        const rich = await page.evaluate(() => window.__tt.colours("#rich"));
        check(`X5 ${where}: the rich tooltip's text at 4.5:1 or more`, rich.text >= 4.5, rich);
        note("rich text", rich.text);
        await context.close();
      }
    }
    console.log("lowest contrast over light, dark, dark-class and the seven theme states:");
    for (const [k, v] of Object.entries(lowest)) console.log(`  ${k.padEnd(22)} ${v.toFixed(2)}:1`);

    // ── No script: the CSS-only tooltip exactly as it was ────────────────
    {
      const { context, page } = await openPage(browser, PAGE.noJs);
      const before = await page.evaluate(() => ({ tt: document.documentElement.hasAttribute("data-gk-tt"), content: getComputedStyle(document.querySelector("#plain"), "::after").content, opacity: getComputedStyle(document.querySelector("#plain"), "::after").opacity }));
      await page.hover("#plain");
      await sleep(700);
      const after = await page.evaluate(() => ({ opacity: getComputedStyle(document.querySelector("#plain"), "::after").opacity, tips: document.querySelectorAll(".gk-tip").length }));
      check("J1 no script: the ::after carries the text, hidden until hovered, shown on hover", !before.tt && before.content === '"Plain text"' && before.opacity === "0" && after.opacity === "1" && after.tips === 0, { before, after });
      await page.hover("#help-host .gk-help");
      await sleep(700);
      const help = await page.evaluate(() => { const s = getComputedStyle(document.querySelector("#help-host .gk-help"), "::after"); return { content: s.content, opacity: s.opacity }; });
      check("J2 no script: the help icon falls back to the CSS-only tooltip on hover", help.content === '"Reduced rate for food and books."' && help.opacity === "1", help);
      await context.close();
    }

    // ── The copy of js/gridkit.js without the 1.112.0 block ──────────────
    {
      const { context, page, errors } = await openPage(browser, PAGE.oldJs);
      const css = await page.evaluate(() => ({ tt: document.documentElement.hasAttribute("data-gk-tt"), content: getComputedStyle(document.querySelector("#plain"), "::after").content }));
      check("J3 old script: no engine mark, the CSS-only ::after as before", !css.tt && css.content === '"Plain text"', css);
      await page.hover("#title-span");
      await sleep(500);
      const tip = await page.evaluate(() => { const b = document.querySelector(".gk-tip"); return b && { hidden: b.hidden, text: b.textContent, side: b.getAttribute("data-gk-side") }; });
      check("J4 old script: GK.tip still shows a title its own way", tip && !tip.hidden && tip.text === "Title text\nline two" && tip.side === null, tip);
      await rest(page);
      await page.hover("#rich");
      await sleep(200);
      const rich = await page.evaluate(() => { const c = document.querySelector("#rich-content"); return { visible: c.classList.contains("visible"), display: getComputedStyle(c).display }; });
      check("J5 old script: the rich tooltip still opens its old way", rich.visible && rich.display === "block", rich);
      check("Z3 no script error with the old script", errors.length === 0, errors);
      await context.close();
    }

    // ── The script added after the page was complete ─────────────────────
    {
      const { context, page, errors } = await openPage(browser, PAGE.late);
      await sleep(200);
      await page.hover("#title-span");
      await sleep(600);
      const tips = await page.evaluate(() => [...document.querySelectorAll(".gk-tip")].filter((b) => !b.hidden).map((b) => ({ text: b.textContent, side: b.getAttribute("data-gk-side") })));
      check("J6 late script: one bubble for a title, the engine's (the old GK.tip stays silent)", tips.length === 1 && tips[0].side === "top" && tips[0].text.startsWith("Title text"), tips);
      await rest(page);
      await page.hover("#rich");
      await sleep(500);
      const r = await state(page, "#rich");
      check("J7 late script: the rich tooltip is placed by the engine, above its trigger", r.open && r.side === "top" && r.rect.b <= r.trig.t - 4, r);
      await rest(page);
      await page.click("#tt-text");
      await sleep(200);
      check("J8 late script: the toggletip works", (await state(page, "#tt-text")).open);
      await page.keyboard.press("Escape");
      // The old listeners are inert: their own 300 ms timer does not open a title that asks for 1000.
      await rest(page);
      await page.hover("#title-slow");
      await sleep(600);
      const slow1 = await state(page, "#title-slow");
      await sleep(600);
      const slow2 = await state(page, "#title-slow");
      check("J9 late script: the old GK.tip timer stays silent — a 1000 ms delay is kept", !slow1.open && slow2.open && slow2.text === "Slow title", { at600: slow1.open, at1200: slow2.open });
      await rest(page);
      await page.hover("#rich");
      await sleep(100);
      const early = await page.evaluate(() => getComputedStyle(document.querySelector("#rich-content")).display);
      check("J10 late script: the old rich binding shows nothing of its own before the engine's delay", early === "none", early);
      check("Z4 no script error with the late script", errors.length === 0, errors);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  for (const f of failures) console.log("FAIL  " + f);
  console.log(`\n${passed} passed, ${failures.length} failed`);
  process.exit(failures.length ? 1 : 0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
