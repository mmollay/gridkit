#!/usr/bin/env node
/**
 * Charts (GridKit\Chart + js/chart.js, 1.121.0) in a real browser.
 *
 *   R  the renderer twice: GK.chart.html(config) against Chart::html() for every chart of the page
 *      and 400 random ones (every type, gaps, zeros, negatives, hidden series, five formats, both
 *      locales, odd widths and heights, & " ' < > and emoji in the words) — byte for byte, in
 *      English and in German.
 *   P  the page: the SVG the script draws after fitting a chart to a box of 480 and of 358px, after
 *      GK.chart.update() (new values, another type, a donut of one part, hostile strings) and after
 *      a series is switched off in the legend is the SVG Chart.php writes for that state, byte for
 *      byte; nothing the strings carry runs.
 *   L  the look: light, [data-gk-mode="dark"] and .gk-dark, without a theme and in each of the six:
 *      every series colour ≥ 3:1 against the card, the page, a hover row and the tooltip; the axis
 *      text, the legend, a series switched off, the tooltip's words, the table and the "Show as
 *      table" link ≥ 4.5:1; the focus ring ≥ 3:1.
 *   W  390px: no page scrolls sideways, the chart is drawn at its box's width (one px of the
 *      viewBox is one px), its text is 12px on the screen, the legend wraps, the table scrolls in
 *      its own box. Without the script: the chart scales, keeps 560px under a 560px box and
 *      scrolls inside its box — never the page.
 *   K  the keyboard: one tab stop, the arrows, Home, End, Enter (gk:chartselect), Escape; the live
 *      region says the category and its values; the legend's switches (Space, aria-pressed,
 *      gk:charttoggle, the focus stays); a donut's parts.
 *   T  the pointer: the tooltip of the category under the pointer, every series in it, the
 *      crosshair of a line chart on its point, the tooltip inside the plot, resting on it keeps it,
 *      leaving hides it; a click on a bar is gk:chartselect with its series; a donut's slice.
 *   M  reduced motion: no transition; print: no tooltip, dashes on; forced colours: the system ink.
 *   B  without js/gridkit.js: the script needs nothing of it.
 *
 * The page is ci/browser-chart-fixture.php.
 *
 *     node ci/browser-chart.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-chart.js
 *     GK_SHOTS=/some/dir node ci/browser-chart.js   # also saves pictures to look at
 *
 * GK_CSS / GK_CHART_JS are handed to the fixture (other files instead of css/gridkit.css and
 * js/chart.js): that is how the counter-checks ran, one rule taken out at a time.
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
const shots = process.env.GK_SHOTS || "";
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-chart-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
let built = 0;
const build = (args) => {
  const file = path.join(dir, "f" + built++ + ".html");
  fs.writeFileSync(file, execFileSync(php, [path.join(__dirname, "browser-chart-fixture.php"), ...args], { maxBuffer: 256 * 1024 * 1024 }));
  return "file://" + file;
};
const THEMES = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
const MODES = ["light", "dark", "dark-class"];

let passed = 0;
const failures = [];
function check(name, ok, detail) {
  if (ok) passed++;
  else failures.push(name + (detail === undefined ? "" : "  " + JSON.stringify(detail).slice(0, 700)));
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Put into every page. */
const HELPERS = () => {
  window.__c = {
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
    over(fg, bg) { return [0, 1, 2].map((i) => fg[i] * fg[3] + bg[i] * (1 - fg[3])); },
    lum(c) {
      const f = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
      return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
    },
    contrast(a, b) { const x = this.lum(a), y = this.lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); },
    /** The colour a CSS colour value resolves to inside an element (a var(), a color-mix()). */
    resolve(el, value) {
      const p = document.createElement("span");
      p.style.color = value;
      el.appendChild(p);
      const v = getComputedStyle(p).color;
      p.remove();
      return this.rgba(v);
    },
    /** The opaque ground under an element: its own background, else its parent's, down to the page. */
    ground(el) {
      const layers = [];
      for (let e = el; e; e = e.parentElement) {
        const b = this.rgba(getComputedStyle(e).backgroundColor);
        if (b[3] > 0) { layers.push(b); if (b[3] >= 1) break; }
      }
      let g = [255, 255, 255];
      for (let i = layers.length - 1; i >= 0; i--) g = this.over(layers[i], g);
      return g;
    },
    fig(name) { return document.getElementById("gk-chart-" + name); },
    svg(name) { return this.fig(name).querySelector(".gk-chart-plot > svg").outerHTML; },
    tplSvg(id) {
      const t = document.getElementById("tpl-" + id);
      return t.content.querySelector(".gk-chart-plot > svg").outerHTML;
    },
    tip(name) {
      const t = this.fig(name).querySelector(".gk-chart-tip");
      return t && !t.hidden ? t.textContent : null;
    },
  };
};

async function open(browser, args, opts = {}) {
  const ctx = await browser.newContext({ viewport: { width: opts.width || 1280, height: opts.height || 900 }, reducedMotion: opts.reducedMotion || "no-preference" });
  const page = await ctx.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });
  await page.goto(build(args), { waitUntil: "load" });
  await page.evaluate(HELPERS);
  await sleep(120);
  return { page, ctx, errors };
}

(async () => {
  const browser = await chromium.launch();
  try {
    // ── R: the renderer twice ───────────────────────────────────────────
    for (const lang of ["en", "de"]) {
      const { page, ctx, errors } = await open(browser, ["--lang=" + lang, "--cases=400", "--seed=" + (lang === "en" ? 7 : 11)]);
      const r = await page.evaluate(() => {
        const cases = JSON.parse(document.getElementById("gk-cases").textContent);
        const bad = [];
        let n = 0;
        for (const c of cases) {
          n++;
          const mine = GK.chart.html(c.config);
          if (mine !== c.html) {
            let i = 0;
            while (i < mine.length && mine[i] === c.html[i]) i++;
            bad.push({ name: c.name, at: i, php: c.html.slice(Math.max(0, i - 60), i + 60), js: mine.slice(Math.max(0, i - 60), i + 60) });
          }
        }
        // The charts of the page: data-gk-chart as Chart.php wrote it, drawn again by the script.
        document.querySelectorAll("template").forEach((t) => {
          const fig = t.content.querySelector(".gk-chart");
          if (!fig) return;
          n++;
          const cfg = JSON.parse(fig.getAttribute("data-gk-chart"));
          const tpl = document.createElement("template");
          tpl.innerHTML = GK.chart.html(cfg);
          if (tpl.content.firstChild.outerHTML !== fig.outerHTML) bad.push({ name: t.id });
        });
        const types = new Set(cases.map((c) => c.config.type));
        return { n, bad, types: [...types].sort().join(",") };
      });
      check(`R1 ${lang}: GK.chart.html(config) is Chart::html(), byte for byte, for ${r.n} charts`, r.bad.length === 0 && r.n > 400, r.bad.slice(0, 3));
      check(`R2 ${lang}: the random charts hold every type`, r.types === "area,bar,donut,line,sparkline", r.types);
      check(`R3 ${lang}: no error on the page`, errors.length === 0, errors);
      await ctx.close();
    }

    // ── P: the page, fitted, updated, switched off ──────────────────────
    {
      const { page, ctx, errors } = await open(browser, ["--lang=de"]);
      const fit = await page.evaluate(() => ({
        same: ["bar", "line", "area", "hbar", "stack", "donut", "empty", "one", "zero"].map((n) =>
          [n, __c.fig(n).querySelector(".gk-chart-plot > svg").getAttribute("viewBox")]),
        narrow: __c.fig("narrow").querySelector(".gk-chart-plot > svg").getAttribute("viewBox"),
        fitted: [...document.querySelectorAll(".gk-chart")].every((f) => f.hasAttribute("data-gk-chart-fit") || f.classList.contains("gk-chart-type-donut")),
      }));
      check("P1: a chart in a 640px box keeps the server's width; one in a 480px box is drawn at 480",
        fit.same.every(([n, v]) => n === "donut" ? v === "0 0 220 220" : v.startsWith("0 0 640 ")) && fit.narrow === "0 0 480 280" && fit.fitted, fit);
      const narrowSvg = await page.evaluate(() => {
        const mine = __c.svg("narrow");
        return { mine: mine.replace(/gk-chart-narrow-/g, "gk-chart-bar-"), php: __c.tplSvg("bar-480") };
      });
      check("P2: the SVG the script fits to 480px is what Chart.php writes with width(480), byte for byte", narrowSvg.mine === narrowSvg.php,
        narrowSvg.mine.slice(0, 300));
      // GK.chart.update() for each state with data: the SVG equals the server's for that state.
      const upd = await page.evaluate(() => {
        const out = [];
        document.querySelectorAll("template[data-update]").forEach((t) => {
          if (!t.getAttribute("data-update")) return;
          const name = t.getAttribute("data-chart");
          GK.chart.update(name, JSON.parse(t.getAttribute("data-update")));
          out.push({ state: t.id, same: __c.svg(name) === t.content.querySelector(".gk-chart-plot > svg").outerHTML,
            mine: __c.svg(name).slice(0, 200), legend: __c.fig(name).querySelectorAll(".gk-chart-key").length });
        });
        return { out, pwned: window.__pwned || 0, scripts: document.querySelectorAll(".gk-chart script, .gk-chart img").length };
      });
      for (const u of upd.out) check(`P3 ${u.state}: after GK.chart.update() the SVG is Chart.php's for the same data, byte for byte`, u.same, u.mine);
      check("P4: nothing the strings carry runs or becomes an element", upd.pwned === 0 && upd.scripts === 0, upd);
      const dn = await page.evaluate(() => { const r = __c.fig("donut").querySelector(".gk-chart-svg").getBoundingClientRect();
        const l = __c.fig("donut").querySelector(".gk-chart-legend").getBoundingClientRect(); return { w: r.width, h: r.height, beside: l.left >= r.right }; });
      check("P4b: a donut is as wide as it is high (220px), its legend beside it", dn.w === 220 && dn.h === 220 && dn.beside, dn);
      check("P5: no error on the page", errors.length === 0, errors);
      await ctx.close();
    }
    {
      // A series switched off in the legend; fitted to 358px on a phone.
      const { page, ctx } = await open(browser, ["--lang=de"]);
      await page.click('#gk-chart-bar [data-gk-chart-key="1"] .gk-chart-toggle');
      const off = await page.evaluate(() => ({ same: __c.svg("bar") === __c.tplSvg("bar-off"),
        pressed: document.querySelector('#gk-chart-bar [data-gk-chart-key="1"] .gk-chart-toggle').getAttribute("aria-pressed"),
        focus: document.activeElement.className, ev: window.__events.filter((e) => e.type === "gk:charttoggle").map((e) => e.detail) }));
      check("P6: switched off in the legend, the SVG is Chart.php's with 'hidden' => true, byte for byte", off.same, off);
      check("P7: the switch says aria-pressed=false, keeps the focus, and gk:charttoggle tells the page",
        off.pressed === "false" && off.focus === "gk-chart-toggle" && off.ev.length === 1 && off.ev[0].series === 1 && off.ev[0].visible === false && off.ev[0].name === "2026", off);
      await page.click('#gk-chart-bar [data-gk-chart-key="1"] .gk-chart-toggle');
      const kept = await page.evaluate(() => {
        const d = __c.fig("bar").querySelector("details.gk-chart-data");
        d.open = true;
        GK.chart.toggle("bar", 0, false);
        const after = __c.fig("bar").querySelector("details.gk-chart-data").open;
        GK.chart.toggle("bar", 0, true);
        __c.fig("bar").querySelector("details.gk-chart-data").open = false;
        return after;
      });
      check("P7b: an open table stays open when the chart is drawn again", kept === true, kept);
      const on = await page.evaluate(() => ({
        back: __c.fig("bar").querySelectorAll('[data-gk-chart-s="1"]').length, api: GK.chart.toggle("bar", 2, false),
        afterApi: __c.fig("bar").querySelectorAll('[data-gk-chart-s="2"]').length, events: window.__events.length }));
      check("P8: switched on again it is back; GK.chart.toggle() switches without an event", on.back === 1 && on.api === false && on.afterApi === 0 && on.events === 2, on);
      await ctx.close();

      const phone = await open(browser, ["--lang=de"], { width: 390, height: 844 });
      const p = await phone.page.evaluate(() => {
        const box = document.getElementById("box-bar");
        const w = __c.fig("bar").querySelector(".gk-chart-plot").clientWidth;
        return { w, vb: __c.fig("bar").querySelector(".gk-chart-plot > svg").getAttribute("viewBox") };
      });
      const tplW = p.w;
      check("W0: on a phone the bar chart is drawn at its box's width", p.vb === `0 0 ${tplW} 280`, p);
      if (tplW === 358 || tplW === 480) {
        const same = await phone.page.evaluate((w) => __c.svg("bar") === __c.tplSvg("bar-" + w), tplW);
        check(`P9: fitted to ${tplW}px it is Chart.php's width(${tplW}), byte for byte`, same);
      }
      // The box changes after the load: the chart follows.
      await phone.page.evaluate(() => { document.getElementById("box-line").style.width = "300px"; document.getElementById("box-line").style.maxWidth = "none"; });
      await sleep(150);
      const rs = await phone.page.evaluate(() => __c.fig("line").querySelector(".gk-chart-plot > svg").getAttribute("viewBox"));
      check("P10: a box that narrows after the load: the chart is drawn again at its new width", rs === "0 0 300 280", rs);
      await phone.ctx.close();
    }

    // ── L: the look, light and dark, seven times ─────────────────────────
    for (const theme of THEMES) {
      for (const mode of MODES) {
        const { page, ctx, errors } = await open(browser, ["--mode=" + mode, ...(theme ? ["--theme=" + theme] : [])]);
        const tag = `${theme || "(no theme)"} ${mode}`;
        // Open a tooltip (keyboard on the line chart), so its ground and words can be measured.
        await page.focus("#gk-chart-line .gk-chart-plot");
        await page.keyboard.press("ArrowRight");
        await sleep(60);
        const m = await page.evaluate(() => {
          const fig = __c.fig("line");
          const card = __c.ground(document.getElementById("box-line"));
          const pageBg = __c.ground(document.body);
          const hover = __c.resolve(fig, "var(--gk-surface-container-high)");
          const tip = fig.querySelector(".gk-chart-tip");
          const tipBg = __c.ground(tip);
          const grounds = { card, page: pageBg, hover, tooltip: tipBg };
          const series = [1, 2, 3, 4, 5].map((n) => {
            const line = fig.querySelector(`.gk-chart-c${n} .gk-chart-line`);
            const col = __c.rgba(getComputedStyle(line).stroke);
            const r = {};
            for (const [k, g] of Object.entries(grounds)) r[k] = +__c.contrast(col.slice(0, 3), g).toFixed(2);
            return { n, col: col.slice(0, 3), ...r };
          });
          // The bars and the donut wear the same colours as the lines.
          const bar = __c.rgba(getComputedStyle(__c.fig("bar").querySelector(".gk-chart-c2 .gk-chart-bar")).fill);
          const lineC2 = __c.rgba(getComputedStyle(fig.querySelector(".gk-chart-c2 .gk-chart-line")).stroke);
          const slice = __c.rgba(getComputedStyle(__c.fig("donut").querySelector(".gk-chart-other")).fill);
          const text = (el, bg) => +__c.contrast(__c.over(__c.rgba(getComputedStyle(el).color), bg || __c.ground(el)), bg || __c.ground(el)).toFixed(2);
          const fillText = (el, bg) => +__c.contrast(__c.over(__c.rgba(getComputedStyle(el).fill), bg), bg).toFixed(2);
          const texts = {
            axis: fillText(fig.querySelector(".gk-chart-axis text"), card),
            title: text(fig.querySelector(".gk-chart-title")),
            legend: text(fig.querySelector(".gk-chart-key-label")),
            summary: text(fig.querySelector(".gk-chart-data > summary")),
            tipTitle: text(tip.querySelector(".gk-chart-tip-title"), tipBg),
            tipValue: text(tip.querySelector(".gk-chart-tip-value"), tipBg),
            tipName: text(tip.querySelector(".gk-chart-tip-name"), tipBg),
            donutTotal: fillText(__c.fig("donut").querySelector(".gk-chart-total"), __c.ground(document.getElementById("box-donut"))),
            donutValue: text(__c.fig("donut").querySelector(".gk-chart-key-value")),
            empty: fillText(__c.fig("empty").querySelector(".gk-chart-empty"), __c.ground(document.getElementById("box-empty"))),
          };
          const plot = fig.querySelector(".gk-chart-plot");
          const pcs = getComputedStyle(plot);
          const ring = pcs.outlineStyle === "solid" && pcs.outlineWidth === "2px" && plot.matches(":focus-visible")
            ? +__c.contrast(__c.rgba(pcs.outlineColor).slice(0, 3), card).toFixed(2) : 0;
          const keys = [...tip.querySelectorAll(".gk-chart-tip-row")].map((r) => +__c.contrast(__c.rgba(getComputedStyle(r.querySelector(".gk-chart-tip-key")).backgroundColor).slice(0, 3), tipBg).toFixed(2));
          // The colours are the roles: --gk-series-N, in light mixed towards --gk-on-surface.
          const dark = document.body.matches('[data-gk-mode="dark"], .gk-dark');
          const mix = { 2: 94, 3: 87, 4: 75, 5: 85 };
          const derived = series.map((s) => {
            const want = __c.resolve(fig, dark || s.n === 1 ? `var(--gk-series-${s.n})` : `color-mix(in oklab, var(--gk-series-${s.n}) ${mix[s.n]}%, var(--gk-on-surface))`);
            return Math.max(...[0, 1, 2].map((i) => Math.abs(want[i] - s.col[i]))) <= 1;
          });
          // The grid is a hairline of --gk-outline-variant, the zero line --gk-outline: quieter than every mark.
          const grid = fig.querySelector(".gk-chart-gridline:not(.gk-chart-zero)");
          const zeroLine = __c.fig("bar").querySelector(".gk-chart-gridline.gk-chart-zero");
          const same = (a, b) => Math.max(...[0, 1, 2].map((i) => Math.abs(a[i] - b[i]))) <= 1;
          const gridOk = getComputedStyle(grid).strokeWidth === "1px" && getComputedStyle(grid).shapeRendering === "crispedges"
            && same(__c.rgba(getComputedStyle(grid).stroke), __c.resolve(fig, "var(--gk-outline-variant)"))
            && same(__c.rgba(getComputedStyle(zeroLine).stroke), __c.resolve(fig, "var(--gk-outline)"));
          // An area is a wash of its series' colour (stacked: a firmer band); a sparkline's area a wash too.
          const band = getComputedStyle(__c.fig("area").querySelector(".gk-chart-c2 .gk-chart-area"));
          const wash = getComputedStyle(document.querySelector("#box-spark .gk-chart-spark .gk-chart-area"));
          const areaOk = band.fillOpacity === "0.35" && same(__c.rgba(band.fill), __c.resolve(__c.fig("area"), "var(--gk-chart-2)"))
            && wash.fillOpacity === "0.12" && same(__c.rgba(wash.fill), __c.resolve(document.querySelector("#box-spark .gk-chart-spark"), "var(--gk-chart-3)"));
          // A point: its series' colour with a 2px ring of the ground.
          const dot = __c.fig("line").querySelector('.gk-chart-c2 .gk-chart-dot[data-gk-chart-i="2"]');
          const dcs = getComputedStyle(dot);
          const dotOk = same(__c.rgba(dcs.fill), __c.resolve(fig, "var(--gk-chart-2)")) && same(__c.rgba(dcs.stroke), card)
            && dcs.strokeWidth === "2px" && dcs.opacity === "1" && dcs.paintOrder.startsWith("stroke");
          // ->dashes(): a pattern per slot from 2 on, on the line and on its legend key; none without it.
          const dash = (root, n, el = ".gk-chart-line") => getComputedStyle(root.querySelector(`.gk-chart-c${n} ${el}`)).strokeDasharray;
          const legend = fig.querySelector(".gk-chart-legend");
          const dashOk = dash(fig, 1) === "none" && dash(fig, 2) === "6px, 4px" && dash(fig, 3) === "2px, 3px" && dash(fig, 4) === "10px, 3px, 2px, 3px"
            && dash(fig, 5) === "14px, 4px" && dash(legend, 2) === "6px, 4px" && dash(__c.fig("area"), 2) === "none";
          // Sparklines: inline in a sentence, a block under a card's figure; slot colour, a lighter line.
          const inSentence = document.querySelector("#box-spark p .gk-chart-spark"), inCard = document.querySelector("#box-spark .gk-stat-content > .gk-chart-spark");
          const sc = getComputedStyle(inSentence), cc = getComputedStyle(inCard), sl = getComputedStyle(inSentence.querySelector(".gk-chart-line"));
          const sparkOk = sc.display === "inline-block" && sc.verticalAlign === "middle" && cc.display === "block" && cc.marginTop === "6px"
            && sl.strokeWidth === "1.5px" && getComputedStyle(inSentence.querySelector(".gk-chart-dot")).strokeWidth === "1.5px"
            && same(__c.rgba(sl.stroke), __c.resolve(inSentence, "var(--gk-chart-2)")) && same(__c.rgba(getComputedStyle(inCard.querySelector(".gk-chart-line")).stroke), __c.resolve(inCard, "var(--gk-chart-1)"));
          return { series, texts, ring, keys, derived, gridOk, areaOk, dotOk, dashOk, sparkOk, same: bar.join() === lineC2.join(), other: +__c.contrast(slice.slice(0, 3), card).toFixed(2), card, tipBg };
        });
        const weakest = m.series.map((s) => Math.min(s.card, s.page, s.hover, s.tooltip));
        check(`L1 ${tag}: every series colour ≥ 3:1 against card, page, hover row and tooltip`, weakest.every((w) => w >= 3), m.series);
        check(`L2 ${tag}: a bar wears the colour of the line of its slot; "Other" ≥ 3:1`, m.same && m.other >= 3, m);
        check(`L10 ${tag}: an area is a wash of its series' colour (0.12), a stacked band firmer (0.35)`, m.areaOk);
        check(`L11 ${tag}: a point wears its series' colour with a 2px ring of the card's ground`, m.dotOk);
        check(`L12 ${tag}: dashes(): a pattern per slot on the line and its legend key; a chart without it has none`, m.dashOk);
        check(`L16 ${tag}: a sparkline: inline in a sentence, under the figure in a card, in its slot's colour, a 1.5px line`, m.sparkOk);
        check(`L9 ${tag}: the grid is a 1px hairline of --gk-outline-variant, the zero line --gk-outline`, m.gridOk);
        check(`L8 ${tag}: the series colours are derived from the roles (--gk-series-N${mode === "light" ? ", mixed towards --gk-on-surface" : ""})`, m.derived.every(Boolean), m.derived);
        const lowText = Object.entries(m.texts).filter(([, v]) => !(v >= 4.5));
        check(`L3 ${tag}: axis, title, legend, link, tooltip, total, empty ≥ 4.5:1`, lowText.length === 0, m.texts);
        check(`L4 ${tag}: the focus ring ≥ 3:1, the tooltip's line keys ≥ 3:1`, m.ring >= 3 && m.keys.length === 5 && m.keys.every((k) => k >= 3), { ring: m.ring, keys: m.keys });
        // A series switched off: its name still reads.
        await page.click('#gk-chart-bar [data-gk-chart-key="0"] .gk-chart-toggle');
        const off = await page.evaluate(() => {
          const l = document.querySelector('#gk-chart-bar [data-gk-chart-key="0"] .gk-chart-key-label');
          return +__c.contrast(__c.over(__c.rgba(getComputedStyle(l).color), __c.ground(l)), __c.ground(l)).toFixed(2);
        });
        check(`L5 ${tag}: the name of a series switched off ≥ 4.5:1`, off >= 4.5, off);
        await page.mouse.move(2, 2);
        await sleep(30);
        const lg = await page.evaluate(() => {
          const fig = __c.fig("bar"), ul = fig.querySelector(".gk-chart-legend"), b = ul.querySelector(".gk-chart-toggle");
          const offLi = fig.querySelector('[data-gk-chart-key="0"]'), onLi = fig.querySelector('[data-gk-chart-key="1"]');
          const cs = (el) => getComputedStyle(el);
          const same = (a, c) => Math.max(...[0, 1, 2].map((i) => Math.abs(a[i] - c[i]))) <= 1;
          const card = __c.ground(document.getElementById("box-bar"));
          const tip = __c.fig("line").querySelector(".gk-chart-tip");
          const dark = document.body.matches('[data-gk-mode="dark"], .gk-dark');
          return {
            legend: cs(ul).display === "flex" && cs(ul).flexWrap === "wrap" && cs(ul).listStyleType === "none" && cs(ul).paddingLeft === "0px",
            keymark: cs(onLi.querySelector(".gk-chart-keymark")).flexShrink === "0" && cs(onLi.querySelector(".gk-chart-key-label")).overflowWrap === "anywhere",
            toggle: parseFloat(cs(b).minHeight) >= 24 && cs(b).cursor === "pointer" && cs(b).backgroundColor === "rgba(0, 0, 0, 0)" && cs(b).borderTopStyle === "none",
            off: cs(offLi.querySelector(".gk-chart-key-label")).textDecorationLine === "line-through" && cs(offLi.querySelector(".gk-chart-keymark")).opacity === "0.35"
              && same(__c.rgba(cs(offLi.querySelector(".gk-chart-key-label")).color), __c.resolve(fig, "var(--gk-on-surface-variant)"))
              && cs(onLi.querySelector(".gk-chart-key-label")).textDecorationLine === "none" && cs(onLi.querySelector(".gk-chart-keymark")).opacity === "1",
            data: cs(fig.querySelector(".gk-chart-data")).marginTop === "8px" && cs(fig.querySelector(".gk-chart-data")).fontSize === "13px",
            summary: same(__c.rgba(cs(fig.querySelector(".gk-chart-data > summary")).color), __c.resolve(fig, "var(--gk-primary)")) && parseFloat(cs(fig.querySelector(".gk-chart-data > summary")).minHeight) >= 24,
            table: cs(fig.querySelector(".gk-chart-table td.gk-num")).textAlign === "right" && cs(fig.querySelector(".gk-chart-table th[scope=row]")).fontWeight === "500"
              && cs(fig.querySelector(".gk-chart-table-wrap")).overflowX === "auto",
            tipGround: same(__c.rgba(cs(tip).backgroundColor), __c.resolve(tip.parentElement, dark ? "var(--gk-surface-container-high)" : "var(--gk-surface-container-lowest)")),
          };
        });
        check(`L13 ${tag}: legend, switches, a series switched off (struck through, muted, its mark faint), the table and its link, the tooltip's ground`,
          Object.values(lg).every(Boolean), lg);
        // The keyboard rings of a switch and of "Show as table": 2px, ≥ 3:1 — reached with Tab from the plot.
        const ringOf = () => page.evaluate(() => { const c = getComputedStyle(document.activeElement), card = __c.ground(document.getElementById("box-bar"));
          return c.outlineStyle === "solid" && c.outlineWidth === "2px" ? +__c.contrast(__c.rgba(c.outlineColor).slice(0, 3), card).toFixed(2) : 0; });
        await page.focus("#gk-chart-bar .gk-chart-plot");
        await page.keyboard.press("Tab");
        const rings = await ringOf();
        for (let i = 0; i < 3; i++) await page.keyboard.press("Tab");
        const atSummary = await page.evaluate(() => document.activeElement.tagName);
        const ring2 = atSummary === "SUMMARY" ? await ringOf() : -1;
        check(`L14 ${tag}: the focus ring of a legend switch and of "Show as table" is 2px and ≥ 3:1`, rings >= 3 && ring2 >= 3, { rings, ring2 });
        // A switch under the pointer: a ground of its own.
        const hb = await page.evaluate(() => { const r = document.querySelector('#gk-chart-bar [data-gk-chart-key="1"] .gk-chart-toggle').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
        await page.mouse.move(hb.x, hb.y);
        await sleep(30);
        const hov = await page.evaluate(() => { const b = document.querySelector('#gk-chart-bar [data-gk-chart-key="1"] .gk-chart-toggle');
          const want = __c.resolve(b, "var(--gk-surface-container-high)"), got = __c.rgba(getComputedStyle(b).backgroundColor);
          return Math.max(...[0, 1, 2].map((i) => Math.abs(want[i] - got[i]))) <= 1 && got[3] === 1; });
        check(`L15 ${tag}: a legend switch under the pointer takes the hover ground`, hov);
        await page.mouse.move(2, 2);
        check(`L6 ${tag}: no error on the page`, errors.length === 0, errors);
        if (!theme && mode !== "dark-class") {
          // Table text, opened.
          const t = await page.evaluate(() => {
            const d = __c.fig("bar").querySelector("details.gk-chart-data");
            d.open = true;
            const td = d.querySelector("td"), th = d.querySelector("th");
            const g = __c.ground(td);
            return [td, th].map((el) => +__c.contrast(__c.over(__c.rgba(getComputedStyle(el).color), g), g).toFixed(2));
          });
          check(`L7 ${tag}: the table's words ≥ 4.5:1`, t.every((v) => v >= 4.5), t);
        }
        if (shots && (theme === "" || mode === "light")) {
          await page.evaluate(() => { __c.fig("bar").querySelector("details.gk-chart-data").open = false; });
          await page.screenshot({ path: path.join(shots, `chart-${theme || "default"}-${mode}.png`), fullPage: true });
        }
        await ctx.close();
      }
    }

    // ── W: 390px, with and without the script ───────────────────────────
    {
      const { page, ctx, errors } = await open(browser, ["--lang=de"], { width: 390, height: 844 });
      const w = await page.evaluate(() => {
        const out = { scroll: document.documentElement.scrollWidth, charts: [] };
        document.querySelectorAll(".gk-chart:not(.gk-chart-type-donut)").forEach((f) => {
          const svg = f.querySelector(".gk-chart-plot > svg");
          const vb = svg.viewBox.baseVal.width;
          const r = svg.getBoundingClientRect();
          const t = svg.querySelector(".gk-chart-axis text");
          out.charts.push({ id: f.id, vb, w: Math.round(r.width), box: f.querySelector(".gk-chart-plot").clientWidth,
            scale: +(r.width / vb).toFixed(3), font: t ? getComputedStyle(t).fontSize : null });
        });
        const legend = __c.fig("line").querySelector(".gk-chart-legend");
        out.legendWraps = legend.scrollWidth <= legend.clientWidth + 1 && legend.getBoundingClientRect().height > 40;
        const d = __c.fig("bar").querySelector("details.gk-chart-data");
        d.open = true;
        const wrap = d.querySelector(".gk-chart-table-wrap");
        out.table = { inner: wrap.scrollWidth, outer: wrap.clientWidth, scroll: document.documentElement.scrollWidth };
        return out;
      });
      check("W1: at 390px nothing scrolls the page sideways", w.scroll <= 390 && w.table.scroll <= 390, w);
      check("W2: every chart is drawn at its box's width: one px of the viewBox is one px", w.charts.every((c) => c.vb === c.box && Math.abs(c.w - c.box) <= 1), w.charts);
      check("W3: its axis text is 12px on the screen (12px at a scale of 1)", w.charts.every((c) => c.scale === 1 && (c.font === null || c.font === "12px")), w.charts);
      check("W4: the legend wraps; a wide table scrolls inside its own box", w.legendWraps && w.table.inner >= w.table.outer, w);
      const cap = await page.evaluate(() => { const t = getComputedStyle(__c.fig("bar").querySelector(".gk-chart-title")); return [t.fontSize, t.fontWeight, t.marginBottom]; });
      check("W4b: the caption is a heading line: 16px, 600, 8px above the plot", cap.join() === "16px,600,8px", cap);
      // A donut in a box narrower than itself shrinks with it.
      const small = await page.evaluate(() => { const box = document.getElementById("box-donut"); box.style.width = "150px"; box.style.maxWidth = "none";
        const r = __c.fig("donut").querySelector(".gk-chart-svg").getBoundingClientRect(), b = box.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height), right: r.right, boxRight: b.right - 20 }; });
      check("W4c: a donut in a 150px box shrinks to it, round", small.w <= 150 && small.w === small.h && small.right <= small.boxRight + 0.5, small);
      check("W5: no error on the page", errors.length === 0, errors);
      if (shots) {
        await page.evaluate(() => { __c.fig("bar").querySelector("details.gk-chart-data").open = false; });
        await page.screenshot({ path: path.join(shots, "chart-390.png"), fullPage: true });
      }
      await ctx.close();

      const nojs = await open(browser, ["--no-js"], { width: 390, height: 844 });
      const n = await nojs.page.evaluate(() => {
        const f = __c.fig("bar");
        const svg = f.querySelector("svg");
        const plot = f.querySelector(".gk-chart-plot");
        return { scroll: document.documentElement.scrollWidth, svgW: Math.round(svg.getBoundingClientRect().width), plot: plot.clientWidth,
          plotScroll: plot.scrollWidth, role: svg.getAttribute("role"), title: svg.querySelector("title").textContent,
          desc: svg.querySelector("desc").textContent, table: f.querySelectorAll("details.gk-chart-data td").length,
          legendButtons: f.querySelectorAll(".gk-chart-toggle").length, tabindex: plot.getAttribute("tabindex") };
      });
      check("W6: without the script the chart is the server's: an image with a title and a summary, a table, no switches",
        n.role === "img" && n.title === "Revenue per month" && /12 categories from Jan to Dec/.test(n.desc) && n.table === 36 && n.legendButtons === 0 && n.tabindex === null, n);
      check("W7: without the script it keeps 560px under a 560px box and scrolls inside it, never the page",
        n.svgW === 560 && n.plotScroll >= 560 && n.plot < 560 && n.scroll <= 390, n);
      await nojs.ctx.close();
      const nojsWide = await open(browser, ["--no-js"], { width: 1280 });
      const nw = await nojsWide.page.evaluate(() => {
        const box = document.getElementById("box-bar");
        box.style.width = "900px";
        box.style.maxWidth = "none";
        const svg = __c.fig("bar").querySelector("svg");
        const r = svg.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height), vb: svg.getAttribute("viewBox"), display: getComputedStyle(svg).display };
      });
      check("W8: without the script, in a 900px box, the SVG of viewBox 640 scales to the box, keeping its proportions", nw.w === 900 && nw.h === Math.round(280 * 900 / 640) && nw.vb === "0 0 640 280" && nw.display === "block", nw);
      await nojsWide.ctx.close();
    }

    // ── K: the keyboard ─────────────────────────────────────────────────
    {
      const { page, ctx, errors } = await open(browser, ["--lang=en"]);
      await page.focus("#gk-chart-bar .gk-chart-plot");
      const k0 = await page.evaluate(() => ({ role: document.activeElement.getAttribute("role"), label: document.activeElement.getAttribute("aria-label"),
        desc: document.getElementById(document.activeElement.getAttribute("aria-describedby")).textContent, tip: __c.tip("bar"),
        tipHidden: __c.fig("bar").querySelector(".gk-chart-tip").getAttribute("aria-hidden"),
        live: __c.fig("bar").querySelector(".gk-chart-live").getAttribute("aria-live") }));
      check("K1: the plot is one tab stop (role application), named by the title and how to use it, described by the summary; focus shows the first category",
        k0.role === "application" && /^Revenue per month\. Arrow keys/.test(k0.label) && /^Bar chart, 12 categories/.test(k0.desc) && /^Jan/.test(k0.tip || ""), k0);
      check("K1b: the tooltip is hidden from a screen reader (the live region speaks), and the live region is polite", k0.tipHidden === "true" && k0.live === "polite", k0);
      await page.keyboard.press("ArrowRight");
      await sleep(30);
      const k1 = await page.evaluate(() => ({ tip: __c.tip("bar"), live: __c.fig("bar").querySelector(".gk-chart-live").textContent,
        on: [...__c.fig("bar").querySelectorAll(".gk-chart-on")].map((m) => m.getAttribute("data-gk-chart-i")) }));
      check("K2: → moves to Feb: the tooltip, its marks, the live region with every series", /^Feb/.test(k1.tip) &&
        k1.live === "Feb. 2025: 7.900,00\u00a0€. 2026: 8.800,00\u00a0€. Plan: 9.000,00\u00a0€." && k1.on.length === 3 && k1.on.every((i) => i === "1"), k1);
      await page.keyboard.press("End");
      const kEnd = await page.evaluate(() => __c.tip("bar"));
      await page.keyboard.press("Home");
      const kHome = await page.evaluate(() => __c.tip("bar"));
      await page.keyboard.press("ArrowLeft");
      const kLeft = await page.evaluate(() => __c.tip("bar"));
      check("K3: End, Home, and ← at the start stays there", /^Dec/.test(kEnd) && /^Jan/.test(kHome) && /^Jan/.test(kLeft), [kEnd, kHome, kLeft]);
      await page.keyboard.press("ArrowRight");
      await page.keyboard.press("Enter");
      await page.keyboard.press(" ");
      const ev = await page.evaluate(() => window.__events.filter((e) => e.type === "gk:chartselect").map((e) => e.detail));
      check("K4: Enter and Space are gk:chartselect: chart, index, label, every series' value and text",
        ev.length === 2 && ev[0].chart === "bar" && ev[0].index === 1 && ev[0].label === "Feb" && ev[0].series === null &&
        ev[0].values.length === 3 && ev[0].values[0].value === 7900 && ev[0].values[0].text === "7.900,00\u00a0€", ev);
      await page.keyboard.press("Escape");
      const kEsc = await page.evaluate(() => ({ tip: __c.tip("bar"), focus: document.activeElement.classList.contains("gk-chart-plot") }));
      check("K5: Escape closes the tooltip and the focus stays", kEsc.tip === null && kEsc.focus, kEsc);
      // The line chart skips a category without values only when every series has none (W4 has two).
      await page.focus("#gk-chart-line .gk-chart-plot");
      for (let i = 0; i < 3; i++) await page.keyboard.press("ArrowRight");
      const kLine = await page.evaluate(() => ({ tip: __c.tip("line"), cross: !__c.fig("line").querySelector(".gk-chart-cross").hidden }));
      check("K6: a line chart: W4 holds a gap (—) for Partners, the crosshair shows", /^W4/.test(kLine.tip) && /—/.test(kLine.tip) && kLine.cross, kLine);
      // Tab from the plot reaches the legend's switches; Space switches.
      await page.focus("#gk-chart-bar .gk-chart-plot");
      await page.keyboard.press("Tab");
      const tab = await page.evaluate(() => ({ cls: document.activeElement.className, name: document.activeElement.textContent, tip: __c.tip("bar") }));
      await page.keyboard.press("Space");
      const sw = await page.evaluate(() => ({ pressed: document.activeElement.getAttribute("aria-pressed"), cls: document.activeElement.className,
        live: __c.fig("bar").querySelector(".gk-chart-live").textContent, s0: __c.fig("bar").querySelectorAll('[data-gk-chart-s="0"]').length }));
      check("K7: Tab goes on to the legend (the tooltip closes); Space switches 2025 off; the focus and the words follow",
        tab.cls === "gk-chart-toggle" && tab.name === "2025" && tab.tip === null && sw.pressed === "false" && sw.cls === "gk-chart-toggle" && sw.live === "2025 hidden" && sw.s0 === 0, [tab, sw]);
      // A donut: its parts, "Other" included.
      await page.focus("#gk-chart-donut .gk-chart-plot");
      await page.keyboard.press("End");
      const kd = await page.evaluate(() => ({ tip: __c.tip("donut"), live: __c.fig("donut").querySelector(".gk-chart-live").textContent }));
      // E-mail (60) and Other (25) fold into the sixth part: 85 of 3125.
      check("K8: a donut's keys walk its parts; the last is the folded Other with its value and share", /^Other/.test(kd.tip) && kd.live === "Other. 85 · 2,7\u00a0%.", kd);
      // A category without any value is stepped over.
      await page.evaluate(() => GK.chart.update("zero", { series: [{ name: "Refunds", values: [1, null, 3] }] }));
      await page.focus("#gk-chart-zero .gk-chart-plot");
      await page.keyboard.press("ArrowRight");
      const skip = await page.evaluate(() => __c.tip("zero"));
      check("K8b: → steps over a category without a value (Q2) to Q3", /^Q3/.test(skip || ""), skip);
      const empty = await page.evaluate(() => __c.fig("empty").querySelector(".gk-chart-plot").getAttribute("tabindex"));
      check("K9: a chart without values is no tab stop", empty === null, empty);
      check("K10: no error on the page", errors.length === 0, errors);
      await ctx.close();
    }

    // ── T: the pointer ──────────────────────────────────────────────────
    {
      const { page, ctx, errors } = await open(browser, ["--lang=en"]);
      const geo = await page.evaluate(() => {
        const svg = __c.fig("line").querySelector(".gk-chart-plot > svg");
        const r = svg.getBoundingClientRect();
        const dot = svg.querySelector('.gk-chart-c1 [data-gk-chart-i="4"]').getBoundingClientRect();
        return { left: r.left, top: r.top, w: r.width, h: r.height, dot: { x: dot.left + dot.width / 2, y: dot.top + dot.height / 2 } };
      });
      await page.mouse.move(geo.dot.x + 6, geo.top + geo.h - 40);
      await sleep(50);
      const t1 = await page.evaluate(() => {
        const f = __c.fig("line");
        const tip = f.querySelector(".gk-chart-tip"), cross = f.querySelector(".gk-chart-cross"), plot = f.querySelector(".gk-chart-plot");
        const pr = plot.getBoundingClientRect(), tr = tip.getBoundingClientRect(), cr = cross.getBoundingClientRect();
        return { tip: __c.tip("line"), rows: tip.querySelectorAll(".gk-chart-tip-row").length, crossX: cr.left, crossHidden: cross.hidden,
          inside: tr.left >= pr.left - 1 && tr.right <= pr.right + 1 && tr.top >= pr.top - 1 && tr.bottom <= pr.bottom + 1, tr: [tr.left, tr.right, tr.top, tr.bottom] };
      });
      const lay = await page.evaluate(() => { const f = __c.fig("line"), t = getComputedStyle(f.querySelector(".gk-chart-tip"));
        return { pos: t.position, bg: __c.rgba(t.backgroundColor)[3], shadow: t.boxShadow !== "none", border: t.borderTopStyle,
          plotH: f.querySelector(".gk-chart-plot").getBoundingClientRect().height, svgH: f.querySelector(".gk-chart-svg").getBoundingClientRect().height }; });
      check("T1c: the tooltip floats over the plot (absolute, an opaque card with an edge and a shadow) and moves nothing", lay.pos === "absolute" && lay.bg === 1 && lay.shadow && lay.border === "solid" && Math.abs(lay.plotH - lay.svgH) < 1, lay);
      check("T1: the pointer near W5 opens its tooltip with every series; the crosshair stands on the point; the tooltip stays inside the plot",
        /^W5/.test(t1.tip) && t1.rows === 5 && !t1.crossHidden && Math.abs(t1.crossX - geo.dot.x) <= 1.5 && t1.inside, { t1, dot: geo.dot });
      // The last category: the tooltip turns to the left of the crosshair and stays inside the plot.
      const last = await page.evaluate(() => { const r = __c.fig("line").querySelector('.gk-chart-c1 [data-gk-chart-i="7"]').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
      await page.mouse.move(last.x - 4, geo.top + geo.h - 40);
      await sleep(40);
      const tl = await page.evaluate(() => { const f = __c.fig("line"), t = f.querySelector(".gk-chart-tip").getBoundingClientRect(), p = f.querySelector(".gk-chart-plot").getBoundingClientRect();
        return { tip: __c.tip("line"), right: t.right, left: t.left, plotRight: p.right, plotLeft: p.left }; });
      check("T1b: at the last category the tooltip turns left of the crosshair, inside the plot", /^W8/.test(tl.tip || "") && tl.right <= last.x && tl.left >= tl.plotLeft, { tl, last });
      await page.mouse.move(geo.dot.x + 6, geo.top + geo.h - 40);
      await sleep(40);
      // Up to the tooltip's height inside W5's band, then across into it: the way to it is W5's.
      const tipBox = await page.evaluate(() => { const r = __c.fig("line").querySelector(".gk-chart-tip").getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
      await page.mouse.move(geo.dot.x + 6, tipBox.y, { steps: 6 });
      await page.mouse.move(tipBox.x, tipBox.y, { steps: 8 });
      await sleep(40);
      const rest = await page.evaluate(() => __c.tip("line"));
      check("T2: the pointer can rest on the tooltip without it moving or closing (WCAG 1.4.13)", /^W5/.test(rest || ""), rest);
      // Onto the legend (inside the figure, outside the plot): the tooltip closes.
      const key = await page.evaluate(() => { const r = __c.fig("line").querySelector(".gk-chart-key-label").getBoundingClientRect(); return { x: r.left + 3, y: r.top + r.height / 2 }; });
      await page.mouse.move(key.x, key.y, { steps: 3 });
      await sleep(40);
      check("T2b: from the plot onto its legend closes the tooltip", (await page.evaluate(() => __c.tip("line"))) === null);
      await page.mouse.move(geo.dot.x + 6, geo.top + geo.h - 40);
      await page.mouse.move(5, 5);
      await sleep(40);
      check("T3: leaving the chart closes it", (await page.evaluate(() => __c.tip("line"))) === null);
      // Forty days: a category is narrower than the gap to its tooltip. The way to it still belongs to it.
      await page.evaluate(() => {
        GK.chart.update("area", { stacked: false, labels: Array.from({ length: 40 }, (_, i) => "D" + (i + 1)),
          series: [{ name: "A", values: Array.from({ length: 40 }, (_, i) => 100 + i) }] });
        __c.fig("area").scrollIntoView({ block: "center" });
      });
      const d = await page.evaluate(() => { const r = __c.fig("area").querySelector('[data-gk-chart-i="10"]').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
      await page.mouse.move(d.x, d.y);
      await sleep(40);
      const t10 = await page.evaluate(() => { const r = __c.fig("area").querySelector(".gk-chart-tip").getBoundingClientRect(); return { tip: __c.tip("area"), x: r.left + r.width / 2, y: r.top + r.height / 2, left: r.left }; });
      // One px per frame (Chrome merges the pointer moves of a frame), through the strip of the next
      // category that lies between the crosshair and the tooltip, onto the tooltip.
      for (let x = Math.ceil(d.x); x <= t10.left + 6; x++) {
        await page.mouse.move(x, d.y);
        await sleep(17);
      }
      const t10b = await page.evaluate(() => __c.tip("area"));
      const dots = await page.evaluate(() => {
        const a = __c.fig("area").querySelectorAll(".gk-chart-dot");
        const on = getComputedStyle(a[10]), off = getComputedStyle(a[4]);
        return { on: on.opacity, scale: on.transform, off: off.opacity };
      });
      check("T3c: on forty categories the points hide, the active one shows, a little larger", dots.on === "1" && /^matrix\(1\.35/.test(dots.scale) && dots.off === "0", dots);
      const band = await page.evaluate(() => { const a = __c.fig("area").querySelectorAll(".gk-chart-dot"); return a[11].getBoundingClientRect().left - a[10].getBoundingClientRect().left; });
      check("T3b: forty narrow categories: the pointer crosses the next ones on its way to the tooltip and it stays", /^D11/.test(t10.tip || "") && t10.left - d.x >= 10 && t10.left - d.x > band / 2 && /^D11/.test(t10b || ""), { t10, t10b, band, dot: d });
      // A bar: click is gk:chartselect with its series.
      const bar = await page.evaluate(() => { __c.fig("bar").scrollIntoView({ block: "center" }); const r = __c.fig("bar").querySelector('.gk-chart-c2 [data-gk-chart-i="3"]').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.bottom - 4 }; });
      await page.mouse.click(bar.x, bar.y);
      await sleep(250);
      const tb = await page.evaluate(() => ({ tip: __c.tip("bar"), ev: window.__events.filter((e) => e.type === "gk:chartselect").pop(),
        dim: getComputedStyle(__c.fig("bar").querySelector('.gk-chart-c1 [data-gk-chart-i="0"]')).opacity,
        lit: getComputedStyle(__c.fig("bar").querySelector('.gk-chart-c1 [data-gk-chart-i="3"]')).opacity }));
      const ring = await page.evaluate(() => getComputedStyle(__c.fig("bar").querySelector(".gk-chart-plot")).outlineStyle);
      check("T4b: a click focuses the plot without a ring (the ring is for the keyboard)", ring === "none", ring);
      check("T4: a click on a bar is gk:chartselect with its series and category; the other bars step back",
        /^Apr/.test(tb.tip) && tb.ev && tb.ev.detail.series === 1 && tb.ev.detail.index === 3 && tb.dim < 1 && tb.lit === "1", tb);
      // A donut's slice.
      // The middle of Marketplace: (1240 + 860 / 2) / 3125 of the turn from the top, half way into the ring.
      const pts = await page.evaluate(() => {
        __c.fig("donut").scrollIntoView({ block: "center" });
        const svg = __c.fig("donut").querySelector(".gk-chart-svg"), r = svg.getBoundingClientRect();
        const S = svg.viewBox.baseVal.width, k = r.width / S, R = S / 2 - 2, mid = (R + R * 0.62) / 2;
        const a = -Math.PI / 2 + 2 * Math.PI * (1240 + 430) / 3125;
        return { x: r.left + (S / 2 + mid * Math.cos(a)) * k, y: r.top + (S / 2 + mid * Math.sin(a)) * k };
      });
      await page.mouse.move(pts.x, pts.y);
      await sleep(40);
      const td = await page.evaluate(() => __c.tip("donut"));
      check("T5: a donut slice under the pointer shows its part, value and share", td === "Marketplace860 · 27,5\u00a0%", td);
      // A stack: its total under the series.
      const sq = await page.evaluate(() => { __c.fig("stack").scrollIntoView({ block: "center" }); const r = __c.fig("stack").querySelector('[data-gk-chart-i="0"]').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + 4 }; });
      await page.mouse.move(sq.x, sq.y);
      await sleep(40);
      const st = await page.evaluate(() => { const t = __c.fig("stack").querySelector(".gk-chart-tip"); const tot = t.querySelector(".gk-chart-tip-total");
        return { tip: __c.tip("stack"), total: tot ? tot.textContent : null, rows: t.querySelectorAll(".gk-chart-tip-row").length }; });
      check("T6b: a stack's tooltip ends with its total (5.200 € − 4.100 €)", st.rows === 3 && st.total === "1.100,00\u00a0€Total", st);
      // Hostile strings in a tooltip are text.
      await page.evaluate(() => GK.chart.update("bar", { labels: ['<img src=x onerror="window.__pwned=3">', "b"], series: [{ name: "<b>x</b>", values: [1, 2] }] }));
      const hb = await page.evaluate(() => { __c.fig("bar").scrollIntoView({ block: "center" }); const r = __c.fig("bar").querySelector('[data-gk-chart-i="0"]').getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.bottom - 3 }; });
      await page.mouse.move(hb.x, hb.y);
      await sleep(40);
      const hz = await page.evaluate(() => ({ tip: __c.tip("bar"), pwned: window.__pwned || 0, els: __c.fig("bar").querySelectorAll(".gk-chart-tip img, .gk-chart-tip b").length }));
      check("T6: a label with markup is text in the tooltip", hz.tip.startsWith('<img src=x onerror="window.__pwned=3">') && hz.pwned === 0 && hz.els === 0, hz);
      check("T7: no error on the page", errors.length === 0, errors);
      await ctx.close();
    }

    // ── M: motion, print, forced colours ────────────────────────────────
    {
      const r = await open(browser, [], { reducedMotion: "reduce" });
      const reduce = await r.page.evaluate(() => getComputedStyle(__c.fig("bar").querySelector(".gk-chart-bar")).transitionDuration);
      await r.ctx.close();
      const n = await open(browser, []);
      const normal = await n.page.evaluate(() => getComputedStyle(__c.fig("bar").querySelector(".gk-chart-bar")).transitionDuration);
      // GridKit's own reduced-motion rule leaves 0.01ms everywhere; the chart's 0.12s is not there.
      check("M1: reduced motion: no transition (otherwise a short one)", parseFloat(reduce) < 0.001 && normal.startsWith("0.12s"), { reduce, normal });
      await n.page.focus("#gk-chart-line .gk-chart-plot");
      // Five series on a chart without dashes(): print and forced colours give each slot its pattern.
      await n.page.evaluate(() => GK.chart.update("area", { series: [1, 2, 3, 4, 5].map((k) => ({ name: "S" + k, values: [k, k + 1, k, k + 2, k, k + 1, k] })) }));
      await n.page.emulateMedia({ media: "print" });
      const pr = await n.page.evaluate(() => ({ tip: getComputedStyle(__c.fig("line").querySelector(".gk-chart-tip")).display,
        dash: getComputedStyle(__c.fig("area").querySelector(".gk-chart-c2 .gk-chart-line")).strokeDasharray,
        adjust: getComputedStyle(__c.fig("bar").querySelector(".gk-chart-svg")).printColorAdjust,
        dashes: [3, 4, 5].map((n) => getComputedStyle(__c.fig("area").querySelector(`.gk-chart-c${n} .gk-chart-line`)).strokeDasharray),
        cross: getComputedStyle(__c.fig("line").querySelector(".gk-chart-cross")).display,
        keymark: getComputedStyle(__c.fig("line").querySelector(".gk-chart-keymark")).printColorAdjust,
        spark: getComputedStyle(document.querySelector("#box-spark .gk-chart-spark")).printColorAdjust,
        brk: getComputedStyle(__c.fig("bar")).breakInside }));
      check("M2: print: no tooltip or crosshair, dashes on every chart, colours kept, a chart not split across pages",
        pr.tip === "none" && pr.cross === "none" && pr.dash !== "none" && pr.dashes.every((d) => d !== "none") && pr.adjust === "exact" && pr.keymark === "exact" && pr.spark === "exact" && pr.brk === "avoid", pr);
      await n.page.emulateMedia({ media: "screen", forcedColors: "active" });
      const fc = await n.page.evaluate(() => {
        const bar = getComputedStyle(__c.fig("bar").querySelector(".gk-chart-bar")).fill;
        const probe = document.createElement("span");
        probe.style.color = "CanvasText";
        document.body.appendChild(probe);
        const ink = getComputedStyle(probe).color;
        probe.remove();
        probe.style.color = "GrayText";
        document.body.appendChild(probe);
        const gray = getComputedStyle(probe).color;
        probe.remove();
        const fig = __c.fig("line");
        return { bar, ink, gray, dash: getComputedStyle(__c.fig("area").querySelector(".gk-chart-c2 .gk-chart-line")).strokeDasharray,
          line: getComputedStyle(fig.querySelector(".gk-chart-c3 .gk-chart-line")).stroke, text: getComputedStyle(fig.querySelector(".gk-chart-axis text")).fill,
          grid: getComputedStyle(fig.querySelector(".gk-chart-gridline:not(.gk-chart-zero)")).stroke,
          zero: getComputedStyle(fig.querySelector(".gk-chart-gridline.gk-chart-zero")).stroke, area: getComputedStyle(__c.fig("area").querySelector(".gk-chart-area")).fill,
          key: getComputedStyle(fig.querySelector(".gk-chart-tip-key") || document.body).backgroundColor,
          dashes: [3, 4, 5].map((n) => getComputedStyle(__c.fig("area").querySelector(`.gk-chart-c${n} .gk-chart-line`)).strokeDasharray) };
      });
      check("M3: forced colours: the marks, lines, areas and text in the system's ink, the grid in GrayText, the zero line in the ink, the series told apart by dashes",
        fc.bar === fc.ink && fc.line === fc.ink && fc.text === fc.ink && fc.area === fc.ink && fc.grid === fc.gray && fc.zero === fc.ink && fc.key === fc.ink && fc.dash !== "none" && fc.dashes.every((d) => d !== "none"), fc);
      await n.ctx.close();
    }

    // ── B: without js/gridkit.js ────────────────────────────────────────
    {
      const { page, ctx, errors } = await open(browser, ["--bare", "--lang=de"]);
      await page.focus("#gk-chart-bar .gk-chart-plot");
      await page.keyboard.press("ArrowRight");
      const b = await page.evaluate(() => ({ gk: typeof GK.toast, tip: __c.tip("bar"), keys: document.querySelectorAll("#gk-chart-bar .gk-chart-toggle").length }));
      check("B1: js/chart.js alone: keys, tooltip and switches work without js/gridkit.js", b.gk === "undefined" && /^Feb/.test(b.tip || "") && b.keys === 3, b);
      check("B2: no error on the page", errors.length === 0, errors);
      await ctx.close();
    }
  } finally {
    await browser.close();
  }

  console.log("");
  if (failures.length) {
    console.log(`FAILED — ${failures.length} problem(s), ${passed} check(s) passed\n`);
    for (const f of failures) console.log("  ✗ " + f);
    console.log("");
    process.exit(1);
  }
  console.log(`ok — ${passed} checks passed\n`);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
