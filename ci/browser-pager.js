#!/usr/bin/env node
/**
 * One pager (1.118.0) in a real browser: the pager of Table::paginate() against the standalone
 * GridKit\Pagination it now looks like.
 *
 *   L  the look: every state of a page control (a page, the current page, an arrow, an arrow that
 *      leads nowhere), the count, the ellipsis and the bar measured on both and compared — in light
 *      mode and both dark spellings, under no theme and each of the six, at 1280px; the bar inside
 *      the table's card; the contrast of the count, the page numbers and the ellipsis (4.5:1),
 *      of the current page's box against the card and of the focus ring (3:1).
 *   P  390px: nothing wider than the screen, the count above the buttons, 34px controls.
 *   C  client paging of a setData() table: a page, the last, the first, the count, the rows, the
 *      status line, one pager, and the pager the script draws equal to the one Table.php writes
 *      for that page (and with the thousands separator, in English and German); a search goes
 *      back to page 1, a sort stays on its page.
 *   S  server paging: the AJAX reload asks for gk_page, brings the server's pager, a sort asks
 *      for page 1, the pager recedes while it loads; Pagination's links carry page and params.
 *   K  the keyboard and a screen reader: Enter and Space page, the focus stays on the control it
 *      was on (on the page just chosen where that control is disabled), after a reload too (once:
 *      it does not come back later), a focus moved elsewhere meanwhile is left there, a rebuild
 *      of another table does not take it, a rebuild that leaves no pager does not fail; the names
 *      in Chrome's accessibility tree.
 *   M  strings with & " ' < > and no thousands separator: the script's pager still the one Table.php
 *      writes, byte for byte.
 *   O  the script of 1.117.0 (js/gridkit.js without its 1.118.0 block, as the PawBot dashboard
 *      keeps it, or GK_OLD_JS): the new markup pages with it, client and server, first and last
 *      included; the pager it draws after a rebuild is the only one, and wears the same boxes.
 *
 * The page is ci/browser-pager-fixture.php.
 *
 *     node ci/browser-pager.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-pager.js
 *     GK_OLD_JS=/path/to/gridkit-1.117.0.js node ci/browser-pager.js    # O with that file
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-pager-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const build = (name, args) => {
  const file = path.join(dir, name + ".html");
  fs.writeFileSync(file, execFileSync(php, [path.join(__dirname, "browser-pager-fixture.php"), ...args], { maxBuffer: 64 * 1024 * 1024 }));
  return "file://" + file;
};
const THEMES = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
const MODES = ["light", "dark", "dark-class"];

let passed = 0;
const failures = [];
function check(name, ok, detail) {
  if (ok) passed++;
  else failures.push(name + (detail === undefined ? "" : "  " + JSON.stringify(detail).slice(0, 600)));
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Put into every page. */
const HELPERS = () => {
  window.__pg = {
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
    /** What stands behind an element: the backgrounds up the tree, composed down to the first opaque one. */
    ground(el) {
      const layers = [];
      for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
        const c = this.rgba(getComputedStyle(n).backgroundColor);
        if (c[3] > 0) layers.push(c);
        if (c[3] >= 1) break;
      }
      let out = [255, 255, 255, 1];
      for (let i = layers.length - 1; i >= 0; i--) out = this.over(layers[i], out);
      return out;
    },
    /** Text against its own box, and the box against what it stands on. */
    colours(el) {
      const s = getComputedStyle(el);
      const ground = this.ground(el.parentElement);
      const box = this.over(this.rgba(s.backgroundColor), ground);
      const fg = this.over(this.rgba(s.color), box);
      return { text: +this.contrast(fg, box).toFixed(2), box: +this.contrast(box, ground).toFixed(2), fg: s.color, bg: s.backgroundColor };
    },
    style(el) {
      if (!el) return null;
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      const icon = el.querySelector(".material-icons");
      return {
        w: Math.round(r.width * 10) / 10, h: Math.round(r.height * 10) / 10,
        radius: s.borderTopLeftRadius, bw: s.borderTopWidth, bs: s.borderTopStyle, bc: s.borderTopColor,
        bg: s.backgroundColor, fg: s.color, fs: s.fontSize, fw: s.fontWeight, ff: s.fontFamily, lh: s.lineHeight,
        op: s.opacity, pl: s.paddingLeft, pr: s.paddingRight, td: s.textDecorationLine, display: s.display,
        align: s.alignItems, justify: s.justifyContent, margin: s.margin, cursor: s.cursor, icon: icon ? getComputedStyle(icon).fontSize : null,
      };
    },
    /** The controls of one pager, by state. */
    states(root) {
      const q = (sel) => root.querySelector(sel);
      return {
        idle: this.style(q(".gk-pg:not(.gk-pg-active):not(.gk-pg-icon)")),
        active: this.style(q(".gk-pg-active")),
        iconOn: this.style(q(".gk-pg-icon:not(.gk-pg-off)")),
        iconOff: this.style(q(".gk-pg-icon.gk-pg-off")),
      };
    },
    wrap(id) { return document.querySelector('[data-gk-table="' + id + '"]'); },
    pager(id) { return this.wrap(id).querySelector(".gk-pagination"); },
    rows(id) { return [...this.wrap(id).querySelectorAll("tbody tr")].map((tr) => tr.children[0].textContent.trim()); },
    state(id) {
      const w = this.wrap(id);
      const p = w.querySelector(".gk-pagination");
      const cur = p && p.querySelector('[aria-current="page"]');
      const status = w.querySelector("[data-gk-table-status]");
      return {
        rows: this.rows(id), pagers: w.querySelectorAll(".gk-pagination").length,
        count: p && p.querySelector(".gk-rowpager-count") ? p.querySelector(".gk-rowpager-count").textContent : null,
        current: cur ? cur.textContent.trim() : null, bar: !!(p && p.matches(".gk-rowpager")),
        disabled: p ? [...p.querySelectorAll("[data-gk-page]:disabled")].map((b) => b.getAttribute("aria-label")) : [],
        status: status ? status.textContent : "",
      };
    },
    /** The server's pager for a page, from the template Table.php filled. */
    server(tpl) { return document.getElementById(tpl).content.querySelector(".gk-pagination").outerHTML; },
    focus() {
      const a = document.activeElement;
      const t = a && a.closest ? a.closest("[data-gk-table]") : null;
      return { label: a ? a.getAttribute("aria-label") : null, table: t ? t.dataset.gkTable : null, inPager: !!(a && a.closest && a.closest(".gk-pagination")),
               visible: !!(a && a.matches && a.matches(":focus-visible")), tag: a ? a.tagName : null };
    },
  };
};

async function openPage(browser, url, width = 1280) {
  const context = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.addInitScript(HELPERS);
  await page.goto(url);
  await sleep(80);
  return { context, page, errors };
}

/** Name, role and properties as Chrome's accessibility tree has them. */
async function ax(page, sel) {
  const cdp = await page.context().newCDPSession(page);
  try {
    const { root } = await cdp.send("DOM.getDocument", { depth: 0 });
    const { nodeId } = await cdp.send("DOM.querySelector", { nodeId: root.nodeId, selector: sel });
    if (!nodeId) return null;
    const { nodes } = await cdp.send("Accessibility.getPartialAXTree", { nodeId, fetchRelatives: false });
    const n = nodes.find((x) => !x.ignored) || nodes[0];
    const props = {};
    (n.properties || []).forEach((p) => { props[p.name] = p.value && p.value.value; });
    return { name: n.name ? n.name.value : "", role: n.role ? n.role.value : "", props };
  } finally {
    await cdp.detach();
  }
}

/** Keys whose values differ between two style objects. */
function diff(a, b, keys) {
  if (!a || !b) return ["missing"];
  return (keys || Object.keys(a)).filter((k) => a[k] !== b[k]).map((k) => `${k}: ${a[k]} ≠ ${b[k]}`);
}

const btn = (id, label) => `[data-gk-table="${id}"] .gk-pagination [aria-label="${label}"]`;

(async () => {
  const browser = await chromium.launch();
  try {
    // ── L: the look, in every mode and theme ───────────────────────────
    for (const mode of MODES) {
      for (const theme of THEMES) {
        const tag = `${mode}${theme ? "/" + theme : ""}`;
        const { context, page, errors } = await openPage(browser, build(`look-${mode}-${theme || "none"}`, [`--mode=${mode}`, `--theme=${theme}`]));
        const m = await page.evaluate(() => {
          const P = window.__pg;
          const st = P.pager("st"), big = P.pager("big"), alone = document.querySelector("#alone .gk-rowpager"), many = document.querySelector("#many .gk-rowpager");
          const info = (n) => P.style(n.querySelector(".gk-rowpager-info"));
          const count = (n) => P.style(n.querySelector(".gk-rowpager-count"));
          const nav = (n) => P.style(n.querySelector(".gk-rowpager-nav"));
          const gap = (n) => P.style(n.querySelector(".gk-pg-gap"));
          const r = (n) => n.getBoundingClientRect();
          const wr = r(P.wrap("st")), sr = r(st), cr = r(st.querySelector(".gk-rowpager-count")), nr = r(st.querySelector(".gk-rowpager-nav"));
          const padX = parseFloat(getComputedStyle(st).paddingLeft);
          const tr = r(P.wrap("st").querySelector(".gk-table")), wb = parseFloat(getComputedStyle(P.wrap("st")).borderBottomWidth);
          const firstCell = r(P.wrap("st").querySelector("tbody td"));
          return {
            st: P.states(st), alone: P.states(alone),
            info: [info(st), info(alone)], count: [count(st), count(alone)], nav: [nav(st), nav(alone)], gap: [gap(big), gap(many)],
            bar: Object.fromEntries([["st", st], ["alone", alone]].map(([k, n]) => {
              const s = getComputedStyle(n);
              return [k, [s.display, s.justifyContent, s.alignItems, s.columnGap, s.flexWrap]];
            })),
            line: { width: getComputedStyle(st).borderTopWidth, style: getComputedStyle(st).borderTopStyle, colour: getComputedStyle(st).borderTopColor,
                    toolbar: getComputedStyle(P.wrap("st").querySelector(".gk-toolbar")).borderBottomColor },
            place: {
              inCard: sr.left >= wr.left - 0.5 && sr.right <= wr.right + 0.5 && sr.bottom <= wr.bottom + 0.5,
              // Right under the rows and down to the card's edge: no margin of .gk-rowpager's around it.
              closes: Math.abs(sr.top - tr.bottom) <= 0.5 && Math.abs(sr.bottom - (wr.bottom - wb)) <= 0.5,
              countLeft: Math.abs(cr.left - (sr.left + padX)), navRight: Math.abs(sr.right - padX - nr.right),
              oneLine: Math.abs((cr.top + cr.bottom) / 2 - (nr.top + nr.bottom) / 2), withColumn: Math.abs(cr.left - (firstCell.left + parseFloat(getComputedStyle(P.wrap("st").querySelector("tbody td")).paddingLeft))),
            },
            contrast: {
              count: P.colours(st.querySelector(".gk-rowpager-count")).text,
              idle: P.colours(st.querySelector(".gk-pg:not(.gk-pg-active):not(.gk-pg-icon)")).text,
              icon: P.colours(st.querySelector(".gk-pg-icon:not(.gk-pg-off)")).text,
              active: P.colours(st.querySelector(".gk-pg-active")),
              gap: P.colours(big.querySelector(".gk-pg-gap")).text,
              aloneActive: P.colours(alone.querySelector(".gk-pg-active")),
              aloneCount: P.colours(alone.querySelector(".gk-rowpager-count")).text,
            },
          };
        });
        for (const s of ["idle", "active", "iconOn", "iconOff"]) {
          check(`L1 ${tag}: a ${s} control of the table looks as Pagination's`, diff(m.st[s], m.alone[s]).length === 0, diff(m.st[s], m.alone[s]));
        }
        check(`L2 ${tag}: the count as Pagination's (size, colour, weight)`, diff(m.count[0], m.count[1], ["fs", "fg", "fw", "ff"]).length === 0 && diff(m.info[0], m.info[1], ["fs", "fg", "display", "align"]).length === 0,
          diff(m.count[0], m.count[1], ["fs", "fg", "fw", "ff"]).concat(diff(m.info[0], m.info[1], ["fs", "fg", "display", "align"])));
        check(`L3 ${tag}: the ellipsis as Pagination's`, diff(m.gap[0], m.gap[1], ["fs", "fg", "pl", "pr", "fw"]).length === 0, diff(m.gap[0], m.gap[1], ["fs", "fg", "pl", "pr", "fw"]));
        check(`L4 ${tag}: the bar as Pagination's: count left, buttons right, 12px apart, buttons 4px apart`,
          JSON.stringify(m.bar.st) === JSON.stringify(m.bar.alone) && m.bar.st[1] === "space-between" && diff(m.nav[0], m.nav[1], ["display", "align"]).length === 0, m.bar);
        check(`L5 ${tag}: inside the card, right under the rows, closing the card, a line above it like the toolbar's below it`, m.place.inCard && m.place.closes && m.line.width === "1px" && m.line.style === "solid" && m.line.colour === m.line.toolbar, { ...m.place, ...m.line });
        check(`L6 ${tag}: the count in line with the first column, the buttons at the far side, on one line`,
          m.place.countLeft <= 0.5 && m.place.navRight <= 0.5 && m.place.oneLine <= 1 && m.place.withColumn <= 1, m.place);
        const c = m.contrast;
        check(`L7 ${tag}: the count, the page numbers, the arrows, the ellipsis ≥ 4.5:1`, c.count >= 4.5 && c.idle >= 4.5 && c.icon >= 4.5 && c.gap >= 4.5, c);
        check(`L8 ${tag}: the current page's text ≥ 4.5:1 on its box, its box ≥ 3:1 on the card`, c.active.text >= 4.5 && c.active.box >= 3, c.active);
        check(`L9 ${tag}: Pagination meets the same (it is the model)`, c.aloneActive.text >= 4.5 && c.aloneActive.box >= 3 && c.aloneCount >= 4.5, { a: c.aloneActive, n: c.aloneCount });
        // Hover and focus, on both.
        await page.hover(btn("st", "Page 2 of 3"));
        const hoverSt = await page.evaluate(() => getComputedStyle(document.querySelector('[data-gk-table="st"] [aria-label="Page 2 of 3"]')).backgroundColor);
        await page.hover('#alone a.gk-pg[href$="page=2"]');
        const hoverAlone = await page.evaluate(() => getComputedStyle(document.querySelector('#alone a.gk-pg[href$="page=2"]')).backgroundColor);
        check(`L10 ${tag}: the same hover`, hoverSt === hoverAlone, [hoverSt, hoverAlone]);
        await page.mouse.move(5, 5);
        await page.focus(btn("st", "Page 2 of 3"));
        await page.keyboard.press("Shift+Tab");
        await page.keyboard.press("Tab");
        const ring = await page.evaluate(() => {
          const a = document.activeElement, s = getComputedStyle(a), P = window.__pg;
          const line = P.rgba(s.outlineColor);
          return { label: a.getAttribute("aria-label"), visible: a.matches(":focus-visible"), style: s.outlineStyle, width: s.outlineWidth, offset: s.outlineOffset,
                   contrast: +P.contrast(P.over(line, P.ground(a.parentElement)), P.ground(a.parentElement)).toFixed(2) };
        });
        await page.focus('#alone a.gk-pg[href$="page=2"]');
        await page.keyboard.press("Shift+Tab");
        await page.keyboard.press("Tab");
        const ringAlone = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return [s.outlineStyle, s.outlineWidth, s.outlineOffset, s.outlineColor]; });
        check(`L11 ${tag}: a focused control shows a ring ≥ 3:1, the same on both`, ring.visible && ring.label === "Page 2 of 3" && ring.style === "solid" && ring.width === "2px" && ring.contrast >= 3
          && JSON.stringify(ringAlone.slice(0, 3)) === JSON.stringify([ring.style, ring.width, ring.offset]), { ring, ringAlone });
        check(`L12 ${tag}: no script error`, errors.length === 0, errors);
        await context.close();
      }
    }

    // ── P: a phone ─────────────────────────────────────────────────────
    for (const mode of ["light", "dark"]) {
      const { context, page, errors } = await openPage(browser, build(`phone-${mode}`, [`--mode=${mode}`]), 390);
      const ph = await page.evaluate(() => {
        const P = window.__pg;
        const out = { scroll: document.documentElement.scrollWidth, pagers: {} };
        const pagers = { st: P.pager("st"), srv: P.pager("srv"), big: P.pager("big"), alone: document.querySelector("#alone .gk-rowpager"), many: document.querySelector("#many .gk-rowpager") };
        for (const [k, n] of Object.entries(pagers)) {
          const box = (n.closest(".gk-table-wrap") || n).getBoundingClientRect();
          const count = n.querySelector(".gk-rowpager-count").getBoundingClientRect();
          const nav = n.querySelector(".gk-rowpager-nav").getBoundingClientRect();
          const ctrls = [...n.querySelectorAll(".gk-pg")].map((b) => b.getBoundingClientRect());
          out.pagers[k] = {
            inside: ctrls.every((b) => b.left >= box.left - 0.5 && b.right <= box.right + 0.5) && n.scrollWidth <= n.clientWidth + 1,
            above: count.bottom <= nav.top + 1,
            size: [...new Set(ctrls.map((b) => Math.round(b.height)))].concat([Math.min(...ctrls.map((b) => Math.round(b.width)))]),
          };
        }
        return out;
      });
      check(`P1 ${mode} 390px: the page does not scroll sideways`, ph.scroll <= 390, ph.scroll);
      for (const [k, v] of Object.entries(ph.pagers)) {
        check(`P2 ${mode} 390px ${k}: every control inside, the count above the buttons, 34px controls`, v.inside && v.above && v.size[0] === 34 && v.size.length === 2 && v.size[1] >= 34, v);
      }
      // Same look at 390 on both.
      const same = await page.evaluate(() => { const P = window.__pg; return [P.states(P.pager("st")), P.states(document.querySelector("#alone .gk-rowpager"))]; });
      check(`P3 ${mode} 390px: the controls still look as Pagination's`, ["idle", "active", "iconOn", "iconOff"].every((s) => diff(same[0][s], same[1][s]).length === 0), same);
      check(`P4 ${mode} 390px: no script error`, errors.length === 0, errors);
      await context.close();
    }

    // ── C: client paging, S: server paging, K: keyboard and names ─────
    for (const lang of ["en", "de"]) {
      const T = lang === "en"
        ? { p: (n, m) => `Page ${n} of ${m}`, next: "Next", prev: "Previous", first: "First page", last: "Last page", nav: "Page navigation", of: "of", status: (n, m) => `page ${n} of ${m}` }
        : { p: (n, m) => `Seite ${n} von ${m}`, next: "Weiter", prev: "Zurück", first: "Erste Seite", last: "Letzte Seite", nav: "Seitennavigation", of: "von", status: (n, m) => `Seite ${n} von ${m}` };
      const { context, page, errors } = await openPage(browser, build(`paging-${lang}`, [`--lang=${lang}`]));
      const st = () => page.evaluate(() => window.__pg.state("st"));
      let s = await st();
      check(`C1 ${lang}: the first page as the server wrote it`, s.bar && s.count === `1–5 ${T.of} 12` && s.current === "1" && s.rows.join() === "Item 1,Item 2,Item 3,Item 4,Item 5"
        && JSON.stringify(s.disabled) === JSON.stringify([T.first, T.prev]), s);
      await page.click(btn("st", T.p(2, 3)));
      s = await st();
      check(`C2 ${lang}: page 2 — its rows, its count, its current page, one pager`, s.bar && s.pagers === 1 && s.count === `6–10 ${T.of} 12` && s.current === "2"
        && s.rows.join() === "Item 6,Item 7,Item 8,Item 9,Item 10" && s.disabled.length === 0, s);
      check(`C3 ${lang}: the status line says so`, s.status.includes(T.status(2, 3)), s.status);
      const twin2 = await page.evaluate(() => [window.__pg.pager("st").outerHTML, window.__pg.server("tpl-st-2")]);
      check(`C4 ${lang}: the pager the script drew is the one Table.php writes for page 2, byte for byte`, twin2[0] === twin2[1], twin2);
      await page.click(btn("st", T.last));
      s = await st();
      const twin3 = await page.evaluate(() => [window.__pg.pager("st").outerHTML, window.__pg.server("tpl-st-3")]);
      check(`C5 ${lang}: the last page — two rows, next and last switched off, as Table.php writes it`, s.count === `11–12 ${T.of} 12` && s.current === "3"
        && s.rows.join() === "Item 11,Item 12" && JSON.stringify(s.disabled) === JSON.stringify([T.next, T.last]) && twin3[0] === twin3[1], { s, twin3 });
      await page.click(btn("st", T.first));
      s = await st();
      check(`C6 ${lang}: back to the first page`, s.current === "1" && s.count === `1–5 ${T.of} 12`, s);
      // A sort keeps its page (as before), a search goes back to page 1.
      await page.click(btn("st", T.p(2, 3)));
      await page.click('[data-gk-table="st"] [data-gk-sort="amount"]');
      s = await st();
      check(`C7 ${lang}: a sort stays on its page, the count with it`, s.current === "2" && s.count === `6–10 ${T.of} 12` && s.pagers === 1, s);
      await page.click(btn("st", T.last));
      await page.fill('[data-gk-table="st"] [data-gk-search]', "odd");
      await sleep(450);
      s = await st();
      check(`C8 ${lang}: a search goes back to page 1 and counts the matches`, s.current === "1" && s.count === `1–5 ${T.of} 6` && s.rows.length === 5 && s.pagers === 1, s);
      await page.fill('[data-gk-table="st"] [data-gk-search]', "Item 1");
      await sleep(450);
      s = await st();
      check(`C9 ${lang}: matches that fit one page: no pager`, s.pagers === 0 && s.rows.length === 4, s);
      await page.fill('[data-gk-table="st"] [data-gk-search]', "");
      await sleep(450);
      // The long one: thousands, the window, the server's markup.
      await page.click(btn("big", T.last));
      const big13 = await page.evaluate(() => ({ s: window.__pg.state("big"), mine: window.__pg.pager("big").outerHTML, server: window.__pg.server("tpl-big-13") }));
      const thousand = lang === "en" ? "1,201–1,234 of 1,234" : "1.201–1.234 von 1.234";
      check(`C10 ${lang}: 1,234 rows, the last page: the separator of the language, as Table.php writes it`, big13.s.count === thousand && big13.mine === big13.server, big13);
      await page.click(btn("big", T.p(11, 13)));
      await page.click(btn("big", T.p(9, 13)));
      await page.click(btn("big", T.p(7, 13)));
      const big7 = await page.evaluate(() => ({ n: [...window.__pg.pager("big").querySelectorAll(".gk-pg:not(.gk-pg-icon)")].map((b) => +b.textContent),
        gaps: window.__pg.pager("big").querySelectorAll(".gk-pg-gap").length, mine: window.__pg.pager("big").outerHTML, server: window.__pg.server("tpl-big-7") }));
      check(`C11 ${lang}: page 7 of 13: first, last, two either side, a gap each way — as Table.php writes it`,
        big7.n.join() === "1,5,6,7,8,9,13" && big7.gaps === 2 && big7.mine === big7.server, big7);
      // Called on its own, after all those rebuilds: no table is being drawn, so nothing to count.
      const lone = await page.evaluate(() => GK.table._staticPager(2, 3));
      check(`C13 ${lang}: GK.table._staticPager() on its own draws the bar with an empty count, not the last table's`,
        lone.includes('<span class="gk-rowpager-count"></span>') && lone.includes('aria-current="page">2</button>') && !/NaN|undefined/.test(lone), lone);

      // ── S ──
      const sv = () => page.evaluate(() => ({ ...window.__pg.state("srv"), log: window.__fetchLog.slice() }));
      await page.click(btn("srv", T.p(2, 10)));
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 11");
      let r = await sv();
      check(`S1 ${lang}: a server table asks for its page and shows the server's pager`, /gk_page=2/.test(r.log[0]) && /gk_table=srv/.test(r.log[0])
        && r.bar && r.pagers === 1 && r.current === "2" && r.count === `11–20 ${T.of} 95`, r);
      await page.click(btn("srv", T.last));
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 91");
      r = await sv();
      check(`S2 ${lang}: the last page`, /gk_page=10/.test(r.log[r.log.length - 1]) && r.current === "10" && r.count === `91–95 ${T.of} 95`, r);
      await page.evaluate(() => { window.__fetchDelay = 400; });
      await page.click(btn("srv", T.p(9, 10)));
      await sleep(60);
      const loading = await page.evaluate(() => { const s = getComputedStyle(window.__pg.pager("srv")); return { op: s.opacity, pe: s.pointerEvents, busy: window.__pg.wrap("srv").getAttribute("aria-busy") }; });
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 81");
      const loaded = await page.evaluate(() => getComputedStyle(window.__pg.pager("srv")).opacity);
      check(`S3 ${lang}: while it loads the pager recedes and takes no click, then comes back`, parseFloat(loading.op) < 1 && loading.pe === "none" && loading.busy === "true" && loaded === "1", { loading, loaded });
      await page.evaluate(() => { window.__fetchDelay = 0; });
      await page.click('[data-gk-table="srv"] [data-gk-sort="name"]');
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 1");
      r = await sv();
      check(`S4 ${lang}: a sort of a server table asks for page 1`, /gk_page=1(&|$)/.test(r.log[r.log.length - 1]) && /gk_sort=name/.test(r.log[r.log.length - 1]) && r.current === "1", r.log.slice(-1));
      const links = await page.evaluate(() => [...document.querySelectorAll("#alone a.gk-pg")].map((a) => a.getAttribute("href")));
      check(`S5 ${lang}: Pagination's links carry the page and keep the params`, links.length === 5 && links.every((h) => /^\/list\?q=a\+b&page=\d+$/.test(h)) && links.includes("/list?q=a+b&page=3"), links);

      // ── K ──
      await page.focus(btn("st", T.next));
      await page.keyboard.press("Enter");
      let f = await page.evaluate(() => ({ ...window.__pg.focus(), s: window.__pg.state("st") }));
      check(`K1 ${lang}: Enter on Next pages, and the focus stays on Next`, f.s.current === "2" && f.table === "st" && f.inPager && f.label === T.next, f);
      await page.keyboard.press(" ");
      f = await page.evaluate(() => ({ ...window.__pg.focus(), s: window.__pg.state("st") }));
      check(`K2 ${lang}: Space too; on the last page, where Next is off, the focus goes to the page`, f.s.current === "3" && f.label === T.p(3, 3) && f.inPager, f);
      await page.keyboard.press("Shift+Tab");
      await page.keyboard.press("Enter");
      f = await page.evaluate(() => ({ ...window.__pg.focus(), s: window.__pg.state("st") }));
      check(`K3 ${lang}: Shift+Tab to page 2 and Enter: page 2, the focus on it, visibly`, f.s.current === "2" && f.label === T.p(2, 3) && f.visible, f);
      await page.focus(btn("srv", T.next));
      await page.keyboard.press("Enter");
      await page.waitForFunction(() => window.__pg.state("srv").current === "2");
      f = await page.evaluate(() => window.__pg.focus());
      check(`K4 ${lang}: a server table: after the reload the focus is on the new Next`, f.table === "srv" && f.label === T.next && f.inPager, f);
      await page.evaluate(() => { window.__fetchDelay = 300; });
      await page.focus(btn("srv", T.next));
      await page.keyboard.press("Enter");
      await page.focus('[data-gk-table="st"] [data-gk-search]');
      await page.waitForFunction(() => window.__pg.state("srv").current === "3");
      await sleep(50);
      f = await page.evaluate(() => window.__pg.focus());
      check(`K5 ${lang}: a focus moved elsewhere while it loads is left there`, f.tag === "INPUT" && f.table === "st", f);
      // Later, with the focus on nothing, the wrap changes again (other code puts something in it).
      await page.evaluate(() => { document.activeElement.blur(); window.__pg.wrap("srv").appendChild(document.createElement("template")); });
      await sleep(30);
      f = await page.evaluate(() => window.__pg.focus());
      check(`K10 ${lang}: and it does not come back later, when the wrap changes again`, f.tag === "BODY", f);
      // The wrap changes while the reload is under way: the focus still waits for the new pager.
      await page.focus(btn("srv", T.next));
      await page.keyboard.press("Enter");
      await page.evaluate(() => window.__pg.wrap("srv").appendChild(document.createElement("template")));
      await page.waitForFunction(() => window.__pg.state("srv").current === "4");
      await sleep(30);
      f = await page.evaluate(() => window.__pg.focus());
      check(`K4b ${lang}: a change to the wrap while it loads does not end the wait`, f.table === "srv" && f.label === T.next && f.inPager, f);
      await page.evaluate(() => { window.__fetchDelay = 0; });
      // Another table rebuilt (a refresh from code) while a page control has the focus: it stays.
      await page.focus(btn("big", T.next));
      await page.evaluate(() => GK.table.renderStatic(window.__pg.wrap("st")));
      f = await page.evaluate(() => window.__pg.focus());
      check(`K11 ${lang}: a rebuild of another table leaves the focus where it is`, f.table === "big" && f.label === T.next, f);
      // A rebuild from code that leaves one page while a page control has the focus: no pager to go back to.
      await page.focus(btn("st", T.p(1, 3)));
      const gone = await page.evaluate(() => {
        const w = window.__pg.wrap("st");
        w._gkSearch = "Item 1";
        try {
          GK.table.renderStatic(w);
          return { ok: true, pagers: w.querySelectorAll(".gk-pagination").length, rows: window.__pg.rows("st").length };
        } catch (e) {
          return { ok: false, error: e.message };
        } finally {
          w._gkSearch = "";
          w._gkPage = 2;
          GK.table.renderStatic(w);
        }
      });
      check(`K12 ${lang}: the rows fit one page after a rebuild, the focus was in the pager: no pager, no error`, gone.ok && gone.pagers === 0 && gone.rows === 4, gone);
      // The accessibility tree.
      const names = {
        nav: await ax(page, '[data-gk-table="st"] nav.gk-pagination'),
        first: await ax(page, btn("st", T.first)),
        page: await ax(page, '[data-gk-table="st"] .gk-pagination [aria-current="page"]'),
      };
      check(`K6 ${lang}: a navigation landmark with its name`, names.nav && names.nav.role === "navigation" && names.nav.name === T.nav, names.nav);
      check(`K7 ${lang}: an arrow is named for what it does, not for its glyph`, names.first && names.first.role === "button" && names.first.name === T.first, names.first);
      check(`K8 ${lang}: the current page by its number and the count of pages`, names.page && names.page.role === "button" && names.page.name === T.p(2, 3), names.page);
      check(`C12/S6/K9 ${lang}: no script error`, errors.length === 0, errors);
      await context.close();
    }

    // ── M: strings that need escaping, no thousands separator ───────────
    {
      const { context, page, errors } = await openPage(browser, build("marks", ["--marks"]));
      const num = (id, n) => `[data-gk-table="${id}"] .gk-pagination [data-gk-page="${n}"]`;
      await page.click(num("st", 2) + ":not(.gk-pg-icon)");
      let m = await page.evaluate(() => ({ s: window.__pg.state("st"), mine: window.__pg.pager("st").outerHTML, server: window.__pg.server("tpl-st-2"),
        name: window.__pg.pager("st").getAttribute("aria-label"), page: window.__pg.pager("st").querySelector('[aria-current="page"]').getAttribute("aria-label") }));
      check(`M1: & " ' < > in the strings — the script's pager is Table.php's, byte for byte`, m.s.current === "2" && m.mine === m.server
        && m.s.count === "6–10 <of> 12 'rows'" && m.name === 'Pages & "rows"' && m.page === 'Page 2 of 3 & "more"', m);
      await page.click(`[data-gk-table="big"] .gk-pagination .gk-pg-icon[data-gk-page="13"]`);
      m = await page.evaluate(() => ({ s: window.__pg.state("big"), mine: window.__pg.pager("big").outerHTML, server: window.__pg.server("tpl-big-13") }));
      check(`M2: no thousands separator in the catalogue: none in the count, as Table.php writes it`, m.s.count === "1201–1234 <of> 1234 'rows'" && m.mine === m.server, m);
      check(`M3: no script error`, errors.length === 0, errors);
      await context.close();
    }

    // ── O: the script of 1.117.0 ───────────────────────────────────────
    for (const mode of ["light", "dark"]) {
      const { context, page, errors } = await openPage(browser, build(`old-${mode}`, ["--old-js", `--mode=${mode}`]));
      const has = await page.evaluate(() => ({ block: typeof GK !== "undefined" && !!GK.table && typeof GK.table._staticPager === "function" && GK.table._staticPager.toString().includes("gk-pagination-gap") }));
      check(`O0 ${mode}: the page runs the script of 1.117.0 (its own _staticPager)`, has.block, has);
      let s = await page.evaluate(() => ({ ...window.__pg.state("st"), h: window.__pg.pager("st").getBoundingClientRect().height }));
      check(`O1 ${mode}: the first paint is the new bar`, s.bar && s.count === "1–5 of 12" && s.current === "1", s);
      await page.click(btn("st", "Page 2 of 3"));
      await page.mouse.move(5, 5); // not over a button: its hover is not what is compared
      let o = await page.evaluate(() => {
        const P = window.__pg, p = P.pager("st"), alone = document.querySelector("#alone .gk-rowpager");
        const q = (sel) => P.style(p.querySelector(sel));
        const last = [...p.querySelectorAll("[data-gk-page]")].pop().getBoundingClientRect();
        return { s: P.state("st"), old: !!p.querySelector(".gk-btn[data-gk-page]"), h: p.getBoundingClientRect().height,
          idle: q('.gk-btn[data-gk-page]:not([aria-current="page"]):not(.gk-btn-icon-only)'), active: q('[aria-current="page"]'), icon: q(".gk-btn-icon-only"),
          alone: P.states(alone), right: Math.abs(p.getBoundingClientRect().right - parseFloat(getComputedStyle(p).paddingRight) - last.right),
          contrast: P.colours(p.querySelector('[aria-current="page"]')), idleText: P.colours(p.querySelector('.gk-btn[data-gk-page]:not([aria-current="page"]):not(.gk-btn-icon-only)')).text };
      });
      check(`O2 ${mode}: its rebuild pages: page 2, its rows, and only its own pager`, o.s.current === "2" && o.s.rows.join() === "Item 6,Item 7,Item 8,Item 9,Item 10" && o.s.pagers === 1 && o.old, o.s);
      const LOOK = ["h", "radius", "bw", "bs", "bc", "bg", "fg", "fs", "fw", "pl", "pr", "cursor"];
      check(`O3 ${mode}: its page buttons wear the boxes of .gk-pg`, diff(o.idle, o.alone.idle, LOOK).length === 0 && o.idle.w >= 34, diff(o.idle, o.alone.idle, LOOK));
      check(`O4 ${mode}: its current page the box of .gk-pg-active`, diff(o.active, o.alone.active, LOOK).length === 0, diff(o.active, o.alone.active, LOOK));
      check(`O5 ${mode}: its arrows the box of .gk-pg-icon, the glyph at 18px`, diff(o.icon, o.alone.iconOn, LOOK.concat(["w", "icon"])).length === 0, diff(o.icon, o.alone.iconOn, LOOK.concat(["w", "icon"])));
      check(`O6 ${mode}: on the right, as high as the bar it replaced`, o.right <= 0.5 && Math.abs(o.h - s.h) <= 1, { right: o.right, h: o.h, was: s.h });
      check(`O7 ${mode}: its contrast holds`, o.contrast.text >= 4.5 && o.contrast.box >= 3 && o.idleText >= 4.5, o.contrast);
      await page.click(btn("st", "Next"));
      o = await page.evaluate(() => { const P = window.__pg, p = P.pager("st"); return { s: P.state("st"), off: P.style(p.querySelector("[data-gk-page]:disabled")), alone: P.states(document.querySelector("#alone .gk-rowpager")).iconOff }; });
      check(`O8 ${mode}: its own Next pages on; its switched-off arrow fades as .gk-pg-off does`, o.s.current === "3" && o.s.rows.join() === "Item 11,Item 12" && o.off && o.off.op === o.alone.op, { s: o.s, off: o.off && o.off.op, want: o.alone.op });
      // Its hover, and its ellipsis (a long table: 1 2 3 4 … 13).
      await page.hover(btn("st", "Page 1 of 3"));
      const hov = await page.evaluate(() => { const s = getComputedStyle(document.querySelector('[data-gk-table="st"] [aria-label="Page 1 of 3"]')); return [s.backgroundColor, s.color]; });
      await page.hover('#alone a.gk-pg[href$="page=2"]');
      const hovAlone = await page.evaluate(() => { const s = getComputedStyle(document.querySelector('#alone a.gk-pg[href$="page=2"]')); return [s.backgroundColor, s.color]; });
      check(`O9 ${mode}: the hover of .gk-pg, background and text`, JSON.stringify(hov) === JSON.stringify(hovAlone), [hov, hovAlone]);
      await page.click(btn("big", "Page 2 of 13"));
      await page.mouse.move(5, 5);
      const gap = await page.evaluate(() => {
        const P = window.__pg;
        // How far the dots stand from the middle of the buttons beside them.
        const off = (g) => {
          const range = document.createRange();
          range.selectNodeContents(g);
          const t = range.getBoundingClientRect(), b = g.parentElement.querySelector("[data-gk-page], .gk-pg").getBoundingClientRect();
          return Math.abs((t.top + t.bottom) / 2 - (b.top + b.bottom) / 2);
        };
        const mine = P.pager("big").querySelector(":scope > span"), theirs = document.querySelector("#many .gk-pg-gap");
        return [P.style(mine), P.style(theirs), P.state("big").current, off(mine), off(theirs)];
      });
      check(`O10 ${mode}: its ellipsis that of .gk-pg-gap, in line with the buttons`, gap[2] === "2" && diff(gap[0], gap[1], ["fg", "pl", "pr"]).length === 0 && gap[3] <= 1.5 && gap[4] <= 1.5,
        { diff: diff(gap[0], gap[1], ["fg", "pl", "pr"]), off: [gap[3], gap[4]] });
      // A narrow card on a wide screen (a table in a side column): its buttons wrap inside it, as .gk-rowpager-nav's do.
      const narrow = await page.evaluate(() => {
        const P = window.__pg, w = P.wrap("big"), p = P.pager("big");
        w.style.width = "240px";
        const box = p.getBoundingClientRect();
        const out = [...p.querySelectorAll("[data-gk-page]")].filter((b) => { const r = b.getBoundingClientRect(); return r.left < box.left - 0.5 || r.right > box.right + 0.5; }).length;
        const lines = new Set([...p.querySelectorAll("[data-gk-page]")].map((b) => Math.round(b.getBoundingClientRect().top))).size;
        w.style.width = "";
        return { out, lines };
      });
      check(`O17 ${mode}: in a narrow card its buttons wrap inside it`, narrow.out === 0 && narrow.lines > 1, narrow);
      // "Last page", which only the new markup has, pages with the old script.
      await page.reload();
      await sleep(80);
      await page.click(btn("st", "Last page"));
      s = await page.evaluate(() => window.__pg.state("st"));
      check(`O11 ${mode}: "Last page" of the new bar pages a setData() table with the old script`, s.current === "3" && s.rows.join() === "Item 11,Item 12" && s.pagers === 1, s);
      // The server table: the old reload brings the new bar, so first and last stay.
      await page.click(btn("srv", "Page 2 of 10"));
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 11");
      const sv = await page.evaluate(() => ({ ...window.__pg.state("srv"), log: window.__fetchLog.slice() }));
      check(`O12 ${mode}: a server table pages with it, and shows the server's bar`, /gk_page=2/.test(sv.log[0]) && sv.bar && sv.current === "2" && sv.count === "11–20 of 95" && sv.pagers === 1, sv);
      await page.click(btn("srv", "Last page"));
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 91");
      check(`O13 ${mode}: its "Last page"`, (await page.evaluate(() => window.__pg.state("srv").current)) === "10");
      await page.click(btn("srv", "First page"));
      await page.waitForFunction(() => window.__pg.state("srv").rows[0] === "Item 1");
      check(`O14 ${mode}: its "First page"`, (await page.evaluate(() => window.__pg.state("srv").current)) === "1");
      await page.focus(btn("srv", "Next"));
      await page.keyboard.press("Enter");
      await page.waitForFunction(() => window.__pg.state("srv").current === "2");
      check(`O15 ${mode}: Enter pages with it`, (await page.evaluate(() => window.__fetchLog.length)) === 4);
      check(`O16 ${mode}: no script error`, errors.length === 0, errors);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  if (failures.length) {
    console.log(failures.map((f) => "FAIL  " + f).join("\n"));
    console.log(`\n${failures.length} failed, ${passed} passed`);
    process.exit(1);
  }
  console.log(`ok — ${passed} cases`);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
