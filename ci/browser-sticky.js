#!/usr/bin/env node
/**
 * Table::stickyHead() and Table::stickyColumn() (1.107.0) in a real browser, light and dark.
 *
 * `php tests/run.php` holds the markup and the rules (tests/sticky.test.php); whether a cell
 * sticks, and whether it hides what scrolls under it, only shows in a browser. Measured on
 * ci/browser-sticky-fixture.php:
 *
 *   - the head: on the page at the top of the window, under a fixed GridKit header at 56px,
 *     at --gk-table-sticky-top; in a box at the top of the box, the box as high as asked;
 *   - the column: the first n cells of every row (checkbox, left buttons, first column) at the
 *     left edge of the box and at the offsets the script measured, after scrolling sideways;
 *     the line at its edge only while scrolled; head and column meeting in a cell above both;
 *   - opaque: every cell that does not stick is painted magenta, the box is scrolled, and a
 *     screenshot of the sticky part may not hold one magenta pixel — plain, selected, hovered,
 *     focused, striped, inverted, minimal, bordered, the dark rows, the row of an open sheet;
 *   - a setData() table sorted, searched and paged in the browser stays in its box, scrolled
 *     where it was; a server table reloaded by AJAX gets its box back, measured, scrolled;
 *     a cell that grows moves the offsets, a table added later and a script added late are
 *     measured; Tab reaches the box, the arrow keys scroll it;
 *   - at rest a sticky table looks exactly like its twin without the options (pixels);
 *   - card mode at 390px is untouched (every cell of a twin, box and all), mobile('scroll')
 *     scrolls with its column, a list keeps its head;
 *   - a copy of gridkit.js without the 1.107.0 block still renders without an error.
 *
 *     node ci/browser-sticky.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-sticky.js
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
const script = path.join(__dirname, "browser-sticky-fixture.php");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-sticky-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const html = execFileSync(php, [script], { maxBuffer: 64 * 1024 * 1024 }).toString("utf8");
const fragment = execFileSync(php, [script, "--fragment"], { maxBuffer: 64 * 1024 * 1024 }).toString("utf8");
const fixture = path.join(dir, "fixture.html");
fs.writeFileSync(fixture, html);
// The same page with a gridkit.js that ends before the 1.107.0 block — the PawBot dashboard's copy.
const marker = "// === 1.107.0 — sticky head and column";
const cut = html.indexOf(marker);
const older = path.join(dir, "older.html");
fs.writeFileSync(older, cut > 0 ? html.slice(0, cut) + "</script></body></html>" : html);
// The page without its script, for a gridkit.js that arrives after the page is ready.
const gridkitJs = fs.readFileSync(path.join(__dirname, "..", "js", "gridkit.js"), "utf8");
const scriptAt = html.lastIndexOf("<script>");
const bare = path.join(dir, "bare.html");
fs.writeFileSync(bare, html.slice(0, scriptAt) + "</body></html>");

/** Helpers inside the page, as window.GKT. */
const HELPERS = `window.GKT = {
  wrap: (id) => document.querySelector('[data-gk-table="' + id + '"]'),
  box: (id) => GKT.wrap(id).querySelector(":scope > .gk-table-sticky-box"),
  r: (el) => el.getBoundingClientRect(),
  n: (id) => parseInt(GKT.box(id).getAttribute("data-gk-sticky-col") || "0", 10),
  rows: (id) => [...GKT.wrap(id).querySelectorAll(".gk-table tbody tr")].filter((tr) => !tr.matches(".gk-table-group, .gk-table-more, .gk-empty-row")),
  sticky: (el) => getComputedStyle(el).position === "sticky",
  // Every cell that does not stick, magenta: whatever of it shows through a sticky cell is found.
  // Inside them too: a button is position: relative and would show above a sticky cell
  // without a z-index of its own — at full colour (a resting row button is greyed out).
  paint(id) {
    GKT.wrap(id).querySelectorAll(".gk-table tr:not(.gk-table-group):not(.gk-empty-row) > :is(th, td)").forEach((c) => {
      if (GKT.sticky(c)) return;
      [c, ...c.querySelectorAll("*")].forEach((e) => {
        Object.assign(e.style, { background: "rgb(255,0,255)", color: "rgb(255,0,255)", filter: "none", opacity: "1" });
        e.dataset.painted = "";
      });
    });
  },
  unpaint(id) {
    GKT.wrap(id).querySelectorAll("[data-painted]").forEach((c) => {
      Object.assign(c.style, { background: "", color: "", filter: "", opacity: "" });
      delete c.dataset.painted;
    });
  },
  // The sticky part of the box: the column from the head down, and the head across.
  clips(id) {
    const b = GKT.box(id), br = GKT.r(b), n = GKT.n(id);
    const head = b.querySelector("thead tr"), hr = GKT.r(head.children[0]);
    const bottom = Math.min(br.top + b.clientHeight, GKT.r(b.querySelector("table")).bottom) - 1;
    const out = {};
    if (n) {
      const edge = GKT.r(head.children[n - 1]).right;
      out.column = { x: br.left + b.clientLeft, y: hr.top, width: edge - br.left - b.clientLeft, height: bottom - hr.top };
    }
    if (GKT.sticky(head.children[head.children.length - 1])) {
      out.head = { x: br.left + b.clientLeft, y: hr.top, width: b.clientWidth, height: hr.height };
    }
    return out;
  },
  // Where the header cells stand — the row itself stays behind when they stick.
  headClip(id) {
    const ths = [...GKT.wrap(id).querySelectorAll("thead th")], a = GKT.r(ths[0]), z = GKT.r(ths[ths.length - 1]);
    return { x: a.left, y: a.top, width: z.right - a.left, height: a.height };
  },
  bg: (el) => getComputedStyle(el).backgroundColor,
  // The alpha of a computed colour: rgb(), rgba() or color(srgb … / a).
  alpha(c) {
    let m = c.match(/rgba\\(.*,\\s*([\\d.]+)\\)/); if (m) return +m[1];
    m = c.match(/\\/\\s*([\\d.]+)\\)/); if (m) return +m[1];
    return c === "transparent" ? 0 : 1;
  },
};`;

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail: detail === undefined ? "" : typeof detail === "string" ? detail : JSON.stringify(detail) });
  const near = (a, b, tol = 0.6) => Math.abs(a - b) <= tol;

  const open = async (file, width, height, mode) => {
    const page = await browser.newPage({ viewport: { width, height } });
    page.on("pageerror", (e) => pageErrors.push(`${path.basename(file)} ${width}px ${mode}: ${e.message}`));
    await page.goto("file://" + file);
    await page.addScriptTag({ content: HELPERS });
    if (mode === "dark") await page.evaluate(() => { document.body.dataset.gkMode = "dark"; });
    if (mode === "dark-class") await page.evaluate(() => { document.body.classList.add("gk-dark"); });
    await page.mouse.move(0, 0);
    await page.waitForTimeout(50);
    return page;
  };

  /** Pixels of a screenshot that are magenta (what does not stick, showing through). */
  const magenta = async (page, clip) => {
    if (!clip || clip.width < 1 || clip.height < 1) return -1;
    const buf = await page.screenshot({ clip });
    return page.evaluate(async (b64) => {
      const img = new Image();
      img.src = "data:image/png;base64," + b64;
      await img.decode();
      const c = document.createElement("canvas");
      c.width = img.width;
      c.height = img.height;
      const x = c.getContext("2d");
      x.drawImage(img, 0, 0);
      const d = x.getImageData(0, 0, c.width, c.height).data;
      let n = 0;
      for (let i = 0; i < d.length; i += 4) if (d[i] > 170 && d[i + 1] < 100 && d[i + 2] > 170) n++;
      return n;
    }, buf.toString("base64"));
  };

  /** Share of pixels that differ clearly between two screenshots of the same size. */
  const differ = async (page, a, b) => page.evaluate(async ([a, b]) => {
    const load = async (b64) => {
      const img = new Image();
      img.src = "data:image/png;base64," + b64;
      await img.decode();
      const c = document.createElement("canvas");
      c.width = img.width;
      c.height = img.height;
      c.getContext("2d").drawImage(img, 0, 0);
      return c.getContext("2d").getImageData(0, 0, c.width, c.height);
    };
    const x = await load(a), y = await load(b);
    if (x.width !== y.width || x.height !== y.height) return `size ${x.width}x${x.height} against ${y.width}x${y.height}`;
    let n = 0;
    for (let i = 0; i < x.data.length; i += 4) {
      if (Math.max(Math.abs(x.data[i] - y.data[i]), Math.abs(x.data[i + 1] - y.data[i + 1]), Math.abs(x.data[i + 2] - y.data[i + 2])) > 60) n++;
    }
    return n / (x.width * x.height);
  }, [a.toString("base64"), b.toString("base64")]);

  /** Scroll a box, then: where its sticky cells stand, against where they have to. */
  const column = async (page, id, x) => page.evaluate(async ([id, x]) => {
    const b = GKT.box(id), n = GKT.n(id);
    b.scrollIntoView({ block: "center" });
    b.scrollLeft = x;
    // the scroll event (which marks the box) comes with the next frame
    await new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done)));
    const inner = GKT.r(b).left + b.clientLeft;
    const vars = [0, 0, parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-2")) || 0, parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-3")) || 0];
    const head = b.querySelector("thead tr");
    const widths = [...head.children].slice(0, n).map((c) => GKT.r(c).width);
    const want = widths.map((w, i) => widths.slice(0, i).reduce((s, v) => s + v, 0));
    const rows = [head, ...GKT.rows(id)];
    return {
      n,
      scrolled: b.scrollLeft,
      vars: vars.slice(2, n + 1),
      want: want.slice(1),
      // per row: the offset of each sticky cell from the inner left edge of the box
      at: rows.map((tr) => [...tr.children].slice(0, n).map((c) => GKT.r(c).left - inner)),
      want0: want,
      widths,
      next: rows.map((tr) => (tr.children[n] ? GKT.r(tr.children[n]).left - inner : null)),
      line: getComputedStyle(head.children[n - 1], "::after").content,
      lineBody: GKT.rows(id).length ? getComputedStyle(GKT.rows(id)[0].children[n - 1], "::after").content : "none",
      hit: (() => {
        const tr = GKT.rows(id)[1] || GKT.rows(id)[0];
        const c = tr.children[n - 1], r = GKT.r(c);
        const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return !!el && (el === c || c.contains(el));
      })(),
    };
  }, [id, x]);

  const columnCases = async (page, mode, id, n, opts = {}) => {
    const c0 = await column(page, id, 0);
    check(`${mode} ${id}: ${n} cell(s) of every row stick`, c0.n === n, c0.n);
    check(`${mode} ${id}: the script wrote the left edge of each cell after the first`, c0.vars.length === c0.want.length && c0.vars.every((v, i) => near(v, c0.want[i])), { vars: c0.vars, want: c0.want });
    check(`${mode} ${id}: no line at the edge of the column before it is scrolled`, c0.line === "none" && c0.lineBody === "none", [c0.line, c0.lineBody]);
    const c = await column(page, id, 300);
    check(`${mode} ${id}: the box scrolls sideways`, c.scrolled > 100, c.scrolled);
    const bad = c.at.map((row, i) => row.every((left, j) => near(left, c.want0[j])) ? null : i).filter((i) => i !== null);
    check(`${mode} ${id}: after scrolling, every sticky cell of the head and the rows stands at its edge`, bad.length === 0, { rows: bad, at: c.at[bad[0]], want: c.want0 });
    const edge = c.want0[n - 1] + c.widths[n - 1];
    check(`${mode} ${id}: the cell after the column scrolled away under it`, c.next.every((v) => v === null || v < edge - 100), { next: c.next.slice(0, 3), edge });
    check(`${mode} ${id}: the sticky cell is on top where it stands`, c.hit);
    const lined = !opts.noLine;
    check(`${mode} ${id}: ${lined ? "a line" : "no line of its own (the table has borders)"} at the edge of the column while scrolled`,
      lined ? c.line !== "none" && c.lineBody !== "none" : c.line === "none", [c.line, c.lineBody]);
    const back = await column(page, id, 0);
    check(`${mode} ${id}: scrolled back, the line is gone`, back.scrolled === 0 && back.line === "none" && back.lineBody === "none", [back.line, back.lineBody]);
    return c;
  };

  /** Paint what does not stick, scroll, count magenta in the sticky part. */
  const opaque = async (page, mode, id, what, x = 300, y = 0, before) => {
    // At the given position and as far as the box goes: what is at the far end comes under the column there.
    for (const at of [x, "the far end"]) {
      await page.evaluate(([id, x, y]) => { GKT.paint(id); const b = GKT.box(id); b.scrollLeft = typeof x === "number" ? x : b.scrollWidth; b.scrollTop = y; }, [id, at, y]);
      if (before) await before();
      await page.waitForTimeout(30);
      const clips = await page.evaluate((id) => GKT.clips(id), id);
      for (const [part, clip] of Object.entries(clips)) {
        const n = await magenta(page, clip);
        check(`${mode} ${id}: nothing shows through the sticky ${part} — ${what}, scrolled to ${typeof at === "number" ? at + "px" : at}`, n === 0, `${n} magenta pixels`);
      }
      await page.evaluate((id) => { GKT.unpaint(id); const b = GKT.box(id); b.scrollLeft = 0; b.scrollTop = 0; }, id);
    }
  };

  /** One part of the run: an exception is a failed case, not the end of the run. */
  const section = async (name, fn) => {
    try {
      await fn();
    } catch (e) {
      check(`${name}: ran to the end`, false, String(e && e.message || e).split("\n")[0]);
    }
  };

  try {
    for (const mode of ["light", "dark"]) {
      const page = await open(fixture, 1000, 700, mode);

      // 1.110.3: the box is the containing block of what is positioned in it. The name of the
      // empty header cell over the right buttons (.gk-sr-only, absolute) does not stick under
      // stickyColumn() alone; with the box unpositioned it stood outside the box's clipping and
      // made the whole page scroll sideways.
      // (This page holds wide twins without a box on purpose, so the page itself scrolls; what is
      // checked is where each positioned thing in a box is placed against.)
      const escaped = await page.evaluate(() => [...document.querySelectorAll(".gk-table-sticky-box")].flatMap((box) =>
        [...box.querySelectorAll("*")].filter((el) => getComputedStyle(el).position === "absolute" && !(el.offsetParent && box.contains(el.offsetParent)))
          .map((el) => box.closest("[data-gk-table]").dataset.gkTable + " " + el.className)));
      check(`${mode}: what is positioned in a box is placed against the box, and clipped by it`, escaped.length === 0, escaped.slice(0, 4));

      // ── The head on the page ───────────────────────────────────────────
      await section(`${mode} The head on the page`, async () => {
        const pageHead = async (label, want) => {
          const r = await page.evaluate(() => {
            const w = GKT.wrap("hp");
            window.scrollTo(0, w.getBoundingClientRect().top + window.scrollY + 400);
            const ths = [...w.querySelectorAll("thead th")];
            const r = GKT.r(ths[1]);
            const el = document.elementFromPoint(r.left + 5, r.top + r.height / 2);
            return { tops: ths.map((th) => GKT.r(th).top), hit: !!el && ths.some((th) => th === el || th.contains(el)), wrapTop: GKT.r(w).top };
          });
          check(`${mode} hp: ${label}, every header cell stands at ${want}px`, r.wrapTop < -300 && r.tops.every((t) => near(t, want)), r);
          check(`${mode} hp: ${label}, the header is on top of the rows`, r.hit);
        };
        await pageHead("the page scrolled 400px into the table", 0);
        await page.evaluate(() => { const h = document.createElement("header"); h.className = "gk-header gk-header-fixed"; h.id = "fixed-header"; h.textContent = "Header"; document.body.prepend(h); });
        await pageHead("under a fixed GridKit header", 56);
        await page.evaluate(() => { document.getElementById("fixed-header").remove(); GKT.wrap("hp").style.setProperty("--gk-table-sticky-top", "20px"); });
        await pageHead("with --gk-table-sticky-top: 20px on the wrap", 20);
        await page.evaluate(() => GKT.wrap("hp").style.removeProperty("--gk-table-sticky-top"));
        {
          await page.evaluate(() => { GKT.paint("hp"); const w = GKT.wrap("hp"); window.scrollTo(0, GKT.r(w).top + window.scrollY + 400); });
          const clip = await page.evaluate(() => GKT.headClip("hp"));
          const n = await magenta(page, clip);
          check(`${mode} hp: nothing shows through the head stuck to the page`, n === 0, `${n} magenta pixels`);
          await page.evaluate(() => { GKT.unpaint("hp"); window.scrollTo(0, 0); });
        }
        {
          // The head stuck looks like the head at rest: its line went with it.
          const shot = async () => {
            const clip = await page.evaluate(() => GKT.headClip("hp"));
            return page.screenshot({ clip });
          };
          await page.evaluate(() => window.scrollTo(0, GKT.r(GKT.wrap("hp")).top + window.scrollY - 100));
          const rest = await shot();
          await page.evaluate(() => window.scrollTo(0, GKT.r(GKT.wrap("hp")).top + window.scrollY + 400));
          const stuck = await shot();
          const d = await differ(page, rest, stuck);
          check(`${mode} hp: stuck, the head looks as it does at rest — its line included`, d < 0.005, d);
          await page.evaluate(() => window.scrollTo(0, 0));
        }
      });

      // ── The head in a box ──────────────────────────────────────────────
      await section(`${mode} The head in a box`, async () => {
        {
          const r = await page.evaluate(() => {
            const b = GKT.box("hb");
            b.scrollIntoView();
            b.scrollTop = 200;
            const ths = [...b.querySelectorAll("thead th")];
            return { max: getComputedStyle(b).maxHeight, h: GKT.r(b).height, scrolled: b.scrollTop, top: GKT.r(b).top + b.clientTop, tops: ths.map((th) => GKT.r(th).top), sticky: GKT.wrap("hb").querySelectorAll(":scope > .gk-table").length };
          });
          check(`${mode} hb: the box is 240px high at most`, r.max === "240px" && r.h <= 240.5, r);
          check(`${mode} hb: the table is in the box, not beside it`, r.sticky === 0, r.sticky);
          check(`${mode} hb: scrolled 200px, the head stands at the top of the box`, r.scrolled === 200 && r.tops.every((t) => near(t, r.top)), r);
          await opaque(page, mode, "hb", "the head in its box", 0, 200);
          const fixed = await page.evaluate(() => {
            const h = document.createElement("header");
            h.className = "gk-header gk-header-fixed";
            h.textContent = "Header";
            document.body.prepend(h);
            const b = GKT.box("hb");
            b.scrollTop = 120;
            const top = GKT.r(b.querySelector("thead th")).top - GKT.r(b).top - b.clientTop;
            h.remove();
            return top;
          });
          check(`${mode} hb: under a fixed GridKit header the head in a box still stops at the top of the box`, near(fixed, 0), fixed);
          const corners = await page.evaluate(() => ({
            alone: getComputedStyle(GKT.box("hb")).borderTopLeftRadius,
            toolbar: getComputedStyle(GKT.box("bo")).borderTopLeftRadius,
            bottom: getComputedStyle(GKT.box("bo")).borderBottomRightRadius,
          }));
          check(`${mode} hb/bo: the box takes the round corners of the wrap below, and above only without a toolbar`,
            corners.alone === "12px" && corners.toolbar === "0px" && corners.bottom === "12px", corners);
          await page.focus("#before-hb");
          await page.keyboard.press("Tab");
          const focus = await page.evaluate(() => {
            const b = GKT.box("hb"), s = getComputedStyle(b);
            return { box: document.activeElement === b, outline: s.outlineStyle, width: s.outlineWidth, role: b.getAttribute("role") };
          });
          check(`${mode} hb: Tab reaches the box, and it shows the focus`, focus.box && focus.outline === "solid" && focus.width === "2px" && focus.role === "region", focus);
          await page.keyboard.press("ArrowDown");
          await page.waitForTimeout(150);
          const keyed = await page.evaluate(() => GKT.box("hb").scrollTop);
          check(`${mode} hb: the arrow keys scroll the focused box`, keyed > 0, keyed);
          await page.evaluate(() => { document.activeElement.blur(); GKT.box("hb").scrollTop = 0; });
        }
      });

      // ── The column ─────────────────────────────────────────────────────
      await section(`${mode} The column`, async () => {
        await page.evaluate(() => GKT.box("sc").scrollIntoView());
        await columnCases(page, mode, "sc", 3);
        await columnCases(page, mode, "lb", 2);
        await columnCases(page, mode, "cb", 2);
        await columnCases(page, mode, "nc", 1);
        await columnCases(page, mode, "ms", 2);
        await columnCases(page, mode, "gf", 2);
        await columnCases(page, mode, "rl", 1);
        await columnCases(page, mode, "v-bordered", 2, { noLine: true });
        for (const id of ["sc", "lb", "cb", "nc", "ms", "gf", "rl", "v-striped", "v-inverted", "v-minimal", "v-bordered"]) {
          await page.evaluate((id) => GKT.box(id).scrollIntoView(), id);
          await opaque(page, mode, id, "rows at rest");
        }
        // The footer: the cells before its first wide one stick with their columns.
        {
          const f = await page.evaluate(() => {
            const b = GKT.box("sc"); b.scrollLeft = 300;
            const inner = GKT.r(b).left + b.clientLeft;
            const head = [...b.querySelectorAll("thead th")].slice(0, 3).map((c) => GKT.r(c).left - inner);
            const foot = [...b.querySelectorAll("tfoot td")].map((c) => ({ left: GKT.r(c).left - inner, sticky: GKT.sticky(c) }));
            b.scrollLeft = 0;
            const g = GKT.box("gf"); g.scrollLeft = 300;
            const gfoot = [...g.querySelectorAll("tfoot td")].map((c) => ({ left: GKT.r(c).left - GKT.r(g).left - g.clientLeft, sticky: GKT.sticky(c) }));
            const groups = [...g.querySelectorAll("tr.gk-table-group > td")].map((c) => GKT.sticky(c));
            g.scrollLeft = 0;
            return { head, foot, gfoot, groups };
          });
          check(`${mode} sc: the footer's first three cells stick under their columns, the fourth does not`,
            f.foot.slice(0, 3).every((c, i) => c.sticky && near(c.left, f.head[i])) && !f.foot[3].sticky, f);
          check(`${mode} gf: a footer cell two columns wide sticks at the edge, the cell after it does not`, f.gfoot[0].sticky && near(f.gfoot[0].left, 0) && !f.gfoot[1].sticky, f.gfoot);
          check(`${mode} gf: group headings span the row and do not stick`, f.groups.length === 2 && f.groups.every((s) => !s), f.groups);
        }
        await page.evaluate(() => GKT.box("rl").scrollIntoView());
        await opaque(page, mode, "rl", "rows scrolled under the head, a row link's last cell and its button among them", 300, 60);
        await page.evaluate(() => GKT.box("ms").scrollIntoView());
        await opaque(page, mode, "ms", "the buttons at the far end", 300, 0);
      });

      // ── The script follows ─────────────────────────────────────────────
      await section(`${mode} The script follows`, async () => {
        // A cell in front of the column changes its width: the ResizeObserver moves the next one.
        const rs = await page.evaluate(async () => {
          const b = GKT.box("lb"), th = b.querySelector("thead th");
          b.scrollIntoView();
          const before = parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-2"));
          th.style.minWidth = "180px";
          await new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done)));
          const after = parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-2"));
          const want = GKT.r(th).width;
          th.style.minWidth = "";
          return { before, after, want };
        });
        check(`${mode} lb: a cell in front of the column grows, the offset after it follows`, rs.after > rs.before + 50 && near(rs.after, rs.want), rs);
        // A table that arrives later is bound by GK.initContent().
        const late = await page.evaluate(() => {
          const src = GKT.wrap("cb"), holder = document.createElement("div");
          holder.innerHTML = src.outerHTML.replace('data-gk-table="cb"', 'data-gk-table="cb-late"');
          const box = holder.querySelector(".gk-table-sticky-box");
          box.removeAttribute("style");
          document.body.appendChild(holder);
          const before = box.style.getPropertyValue("--gk-table-sticky-left-2");
          GK.initContent(holder);
          const after = parseFloat(box.style.getPropertyValue("--gk-table-sticky-left-2"));
          const want = GKT.r(box.querySelector("thead th")).width;
          holder.remove();
          return { before, after, want };
        });
        check(`${mode} a table that arrives later is measured by GK.initContent()`, late.before === "" && near(late.after, late.want), late);
      });

      // ── Every state of a row, opaque ───────────────────────────────────
      await section(`${mode} Every state of a row, opaque`, async () => {
        {
          await page.evaluate(() => GKT.box("sc").scrollIntoView());
          await page.evaluate(() => { const tr = GKT.rows("sc")[1]; tr.querySelector(".gk-cb-col input").click(); });
          const sel = await page.evaluate(() => { const tr = GKT.rows("sc")[1]; return { cls: tr.className, bg: GKT.bg(tr.children[2]), plain: GKT.bg(GKT.rows("sc")[2].children[2]) }; });
          check(`${mode} sc: a selected row tints its sticky cells too (opaque)`, /gk-row-selected/.test(sel.cls) && sel.bg !== sel.plain, sel);
          await opaque(page, mode, "sc", "a selected row");
          await page.evaluate(() => GKT.rows("sc")[1].querySelector(".gk-cb-col input").click());

          // Hover: the pointer over the name of row 4.
          const hover = async () => {
            const p = await page.evaluate(() => { const r = GKT.r(GKT.rows("sc")[3].children[2]); return { x: r.left + 20, y: r.top + r.height / 2 }; });
            await page.mouse.move(p.x, p.y);
          };
          await opaque(page, mode, "sc", "a hovered row", 300, 0, hover);
          await hover();
          const hv = await page.evaluate(() => ({ bg: GKT.bg(GKT.rows("sc")[3].children[2]), want: getComputedStyle(GKT.rows("sc")[3].children[5]).backgroundColor }));
          check(`${mode} sc: a hovered row paints its sticky cells as its other cells`, hv.bg === hv.want && GKT_alpha(hv.bg) === 1, hv);
          await page.mouse.move(0, 0);

          // Focus inside a row.
          await opaque(page, mode, "sc", "a row with the focus inside", 300, 0, async () => {
            await page.evaluate(() => GKT.rows("sc")[4].querySelector(".gk-actions-right button").focus({ preventScroll: true }));
          });
          await page.evaluate(() => document.activeElement && document.activeElement.blur());

          // The row of an open side sheet.
          await page.evaluate(() => { GKT.box("rl").scrollIntoView(); GKT.rows("rl")[1].setAttribute("aria-current", "true"); });
          const cur = await page.evaluate(() => { const c = GKT.rows("rl")[1].children[0]; return { bg: GKT.bg(c), img: getComputedStyle(c).backgroundImage, plain: GKT.bg(GKT.rows("rl")[2].children[0]) }; });
          check(`${mode} rl: the row of an open sheet keeps its tint on the sticky cell, over an opaque surface`, GKT_alpha(cur.bg) === 1 && /gradient/.test(cur.img), cur);
          await opaque(page, mode, "rl", "the row of an open side sheet");
          await page.evaluate(() => GKT.rows("rl")[1].removeAttribute("aria-current"));

          // Even rows: striped paints them, dark mode paints them.
          const even = await page.evaluate(() => ({
            striped: [GKT.bg(GKT.rows("v-striped")[1].children[1]), getComputedStyle(GKT.rows("v-striped")[1]).backgroundColor],
            plain: [GKT.bg(GKT.rows("sc")[1].children[2]), getComputedStyle(GKT.rows("sc")[1]).backgroundColor, GKT.bg(GKT.wrap("sc"))],
            inverted: [GKT.bg(GKT.rows("v-inverted")[0].children[1]), GKT.bg(GKT.wrap("v-inverted")), GKT.bg(GKT.wrap("v-inverted").querySelector("thead th")), getComputedStyle(GKT.wrap("v-inverted").querySelector("thead th:last-child")).backgroundColor],
            minimal: GKT.bg(GKT.wrap("v-minimal").querySelector("thead th:last-child")),
          }));
          check(`${mode} v-striped: the sticky cell of an even row has the row's colour`, even.striped[0] === even.striped[1], even.striped);
          // The dark row colour is the role --gk-surface-container-low (1.114.0; it was GitHub's #1c2128, rgb(28, 33, 40)).
          const darkRow = await page.evaluate(() => { const s = document.createElement("span"); s.style.color = "var(--gk-surface-container-low)";
            GKT.wrap("sc").appendChild(s); const c = getComputedStyle(s).color; s.remove(); return c; });
          if (mode === "dark") check(`dark sc: the sticky cell of an even row has the dark row colour`, even.plain[0] === even.plain[1] && even.plain[0] === darkRow, { plain: even.plain, darkRow });
          else check(`light sc: the sticky cell of a plain row has the surface of the wrap`, even.plain[0] === even.plain[2], even.plain);
          if (mode === "light") check(`light v-inverted: sticky cells have the inverted surface, the head its own`, even.inverted[0] === "rgb(30, 41, 59)" && even.inverted[2] === "rgb(15, 23, 42)" && even.inverted[3] === even.inverted[2], even.inverted);
          if (mode === "light") {
            const inv = await page.evaluate(() => [GKT.bg(GKT.rows("v-inverted")[0].children[1]), GKT.bg(GKT.rows("v-inverted")[1].children[1])]);
            check("light v-inverted: the sticky cell of an even row is lighter, as the row is", inv[0] !== inv[1] && GKT_alpha(inv[1]) === 1, inv);
          }
          check(`${mode} v-minimal: the head of a minimal table is opaque when it sticks`, GKT_alpha(even.minimal) === 1, even.minimal);
        }
      });

      // ── Head and column at once ────────────────────────────────────────
      await section(`${mode} Head and column at once`, async () => {
        {
          const r = await page.evaluate(() => {
            const b = GKT.box("bo");
            b.scrollIntoView();
            b.scrollLeft = 300;
            b.scrollTop = 500;
            const br = GKT.r(b), th = b.querySelectorAll("thead th");
            const corner = th[2], cr = GKT.r(corner);
            const el = document.elementFromPoint(cr.left + 5, cr.top + cr.height / 2);
            return {
              max: getComputedStyle(b).maxHeight, vh: window.innerHeight * 0.7, h: br.height,
              top: cr.top - br.top - b.clientTop, left: cr.left - br.left - b.clientLeft,
              want: parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-3")),
              hit: !!el && (el === corner || corner.contains(el)), z: getComputedStyle(corner).zIndex,
            };
          });
          check(`${mode} bo: head and column together scroll in a box of 70vh`, r.max === "70vh" || near(parseFloat(r.max), r.vh, 1), r);
          check(`${mode} bo: scrolled both ways, the corner cell stands at the top left of the box`, near(r.top, 0) && near(r.left, r.want), r);
          check(`${mode} bo: the corner cell lies above the head and the column`, r.hit && r.z === "3", r);
          await opaque(page, mode, "bo", "head and column scrolled both ways", 300, 500);
        }
      });

      // ── A setData() table rebuilt in the browser ───────────────────────
      await section(`${mode} A setData() table rebuilt in the browser`, async () => {
        {
          const state = () => page.evaluate(() => {
            const w = GKT.wrap("st"), b = GKT.box("st");
            const kids = [...w.children].map((c) => c.className || c.tagName);
            // By its class, not its whole class attribute: since 1.118.0 the pager is "gk-pagination gk-rowpager".
            const pagerAt = [...w.children].findIndex((c) => c.classList.contains("gk-pagination"));
            const head = b ? b.querySelector("thead tr") : null;
            const widths = head ? [...head.children].slice(0, 3).map((c) => GKT.r(c).width) : [];
            return {
              inBox: !!b && !!b.querySelector(":scope > .gk-table"),
              loose: w.querySelectorAll(":scope > .gk-table").length,
              // (a search with a page of matches has no pager)
              pagerAfterBox: pagerAt === -1 || pagerAt > kids.indexOf("gk-table-sticky-box"),
              pager: pagerAt > -1,
              x: b ? b.scrollLeft : -1,
              y: b ? b.scrollTop : -1,
              vars: b ? [parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-2")), parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-3"))] : [],
              want: [widths[0], widths[0] + widths[1]],
              rows: GKT.rows("st").length,
              first: GKT.rows("st")[0] ? GKT.rows("st")[0].children[2].textContent : "",
            };
          });
          await page.evaluate(() => { const b = GKT.box("st"); b.scrollIntoView(); b.scrollLeft = 250; b.scrollTop = 120; });
          const rebuilt = async (what, s, wantX) => {
            check(`${mode} st: ${what} — the table is back in its box`, s.inBox && s.loose === 0, s);
            check(`${mode} st: ${what} — the pager stands below the box`, s.pagerAfterBox, s);
            check(`${mode} st: ${what} — the box is where it was scrolled`, near(s.x, wantX, 1), [s.x, wantX]);
            check(`${mode} st: ${what} — the offsets fit the rebuilt head`, s.vars.every((v, i) => near(v, s.want[i])), s);
          };
          await page.click('[data-gk-table="st"] [data-gk-sort="amount"]');
          await page.waitForTimeout(50);
          let s = await state();
          await rebuilt("sorted", s, 250);
          check(`${mode} st: sorted, the pager is there`, s.pager, s);
          check(`${mode} st: sorted, the box kept its vertical position`, near(s.y, 120, 1), s.y);
          await page.fill('[data-gk-table="st"] [data-gk-search]', "number 1");
          await page.waitForTimeout(450);
          s = await state();
          await rebuilt("searched", s, 250);
          check(`${mode} st: searched, the rows are the matches`, s.rows === 11, s.rows);
          await page.fill('[data-gk-table="st"] [data-gk-search]', "");
          await page.waitForTimeout(450);
          await page.click('[data-gk-table="st"] [data-gk-page="2"]');
          await page.waitForTimeout(50);
          s = await state();
          await rebuilt("paged", s, 250);
          check(`${mode} st: paged, the pager is there`, s.pager, s);
          const c = await column(page, "st", 300);
          check(`${mode} st: after the rebuilds the column still sticks at its offsets`, c.at.every((row) => row.every((left, j) => near(left, c.want0[j]))), c.at[0]);
          await opaque(page, mode, "st", "after the rebuilds", 300, 100);
          // Empty: a search that finds nothing says so where the box shows it.
          await page.fill('[data-gk-table="st"] [data-gk-search]', "nothing like this");
          await page.waitForTimeout(450);
          const e = await page.evaluate(() => {
            const b = GKT.box("st"), t = b.querySelector(".gk-empty-title");
            // Without rows only the short header labels make the table wide; columns with a
            // minWidth (or a wide screen's worth of them) make it much wider.
            b.querySelector("table").style.minWidth = "3000px";
            b.scrollLeft = 0;
            const r0 = GKT.r(t), br = GKT.r(b);
            b.scrollLeft = b.scrollWidth;
            const r1 = GKT.r(t);
            const out = { inside0: r0.left >= br.left && r0.right <= br.left + b.clientWidth, mid: (r0.left + r0.right) / 2 - br.left - b.clientWidth / 2, inside1: r1.left >= br.left && r1.right <= br.left + b.clientWidth, scrolled: b.scrollLeft };
            b.querySelector("table").style.minWidth = "";
            b.scrollLeft = 0;
            return out;
          });
          check(`${mode} st: an empty result stands in the middle of the box, not of the wide table`, e.inside0 && near(e.mid, 0, 2), e);
          check(`${mode} st: … and stays in view when the box is scrolled sideways`, e.scrolled > 1500 && e.inside1, e);
          await page.fill('[data-gk-table="st"] [data-gk-search]', "");
          await page.waitForTimeout(450);
        }
      });

      // ── A server table reloaded by AJAX ────────────────────────────────
      await section(`${mode} A server table reloaded by AJAX`, async () => {
        {
          await page.evaluate((body) => {
            window.__gkFetch = window.fetch;
            window.fetch = () => Promise.resolve(new Response(body, { status: 200, headers: { "Content-Type": "text/html" } }));
          }, fragment);
          const before = await page.evaluate(() => { const b = GKT.box("sv"); b.scrollIntoView(); b.scrollLeft = 200; b.scrollTop = 90; window.__oldBox = b; return [b.scrollLeft, b.scrollTop]; });
          await page.waitForTimeout(50); // the scroll event records the position
          await page.click('[data-gk-table="sv"] [data-gk-sort="name"]');
          await page.waitForFunction(() => GKT.box("sv") && GKT.box("sv") !== window.__oldBox && !GKT.wrap("sv").hasAttribute("data-gk-loading"));
          await page.waitForTimeout(50);
          const r = await page.evaluate(() => {
            const b = GKT.box("sv"), head = b.querySelector("thead tr");
            return {
              first: GKT.rows("sv")[0].children[1].textContent,
              inBox: !!b.querySelector(":scope > .gk-table"),
              x: b.scrollLeft, y: b.scrollTop,
              v: parseFloat(b.style.getPropertyValue("--gk-table-sticky-left-2")),
              want: GKT.r(head.children[0]).width,
              top: GKT.r(head.children[3]).top - GKT.r(b).top - b.clientTop,
            };
          });
          check(`${mode} sv: the reload wrote the sorted rows into a new box`, r.inBox && /Customer number 9 /.test(r.first), r);
          check(`${mode} sv: the new box is scrolled where the old one was`, near(r.x, before[0], 1) && near(r.y, before[1], 1), [r.x, r.y, before]);
          check(`${mode} sv: the new box got its offsets`, near(r.v, r.want), r);
          check(`${mode} sv: the head of the reloaded table sticks at the top of its box`, near(r.top, 0), r.top);
          await opaque(page, mode, "sv", "after the reload", 200, 90);
          await page.evaluate(() => { window.fetch = window.__gkFetch; });
        }
      });

      // ── At rest: like the twin without the options ─────────────────────
      await section(`${mode} At rest: like the twin without the options`, async () => {
        // In light, pixel for pixel. Dark lines are translucent, and collapsed borders of
        // translucent colours make Chromium give the head and the footer row their whole line
        // instead of half of it: the plain dark table is 2px taller than the plain light one.
        // Separate borders do not do that, so in dark every cell is compared within 2px.
        if (mode === "dark") {
          for (const v of ["default", "bordered", "celled", "striped"]) {
            const r = await page.evaluate(([a, b]) => {
              const cells = (id) => { const w = GKT.wrap(id), wr = GKT.r(w); return [...w.querySelectorAll(".gk-table th, .gk-table td")].map((c) => { const r = GKT.r(c); return [r.left - wr.left, r.top - wr.top, r.width, r.height]; }); };
              const x = cells(a), y = cells(b);
              let worst = x.length === y.length ? 0 : 99;
              x.forEach((c, i) => c.forEach((v, j) => { worst = Math.max(worst, Math.abs(v - ((y[i] || [])[j] ?? 99))); }));
              return { worst, h: [GKT.r(GKT.wrap(a)).height, GKT.r(GKT.wrap(b)).height] };
            }, [`rest-${v}`, `rest-${v}-plain`]);
            check(`dark rest-${v}: at rest every cell is where it is in the table without the options (within 2px)`, r.worst <= 2, r);
          }
        } else for (const v of ["default", "bordered", "celled", "striped"]) {
          const shot = async (id) => {
            const el = await page.$(`[data-gk-table="${id}"]`);
            await el.scrollIntoViewIfNeeded();
            // the same place in the window for both: no difference of a fraction of a pixel
            await page.evaluate((id) => { const w = GKT.wrap(id); window.scrollTo(0, GKT.r(w).top + window.scrollY - 40); }, id);
            await page.waitForTimeout(30);
            return el.screenshot();
          };
          const a = await shot(`rest-${v}`);
          const b = await shot(`rest-${v}-plain`);
          const d = await differ(page, a, b);
          check(`${mode} rest-${v}: at rest it looks exactly like the same table without the options`, d === 0, d);
        }
        if (mode === "light") {
          const shot = async (id) => {
            const el = await page.$(`[data-gk-table="${id}"]`);
            await page.evaluate((id) => { const w = GKT.wrap(id); window.scrollTo(0, GKT.r(w).top + window.scrollY - 40); }, id);
            await page.waitForTimeout(30);
            return el.screenshot();
          };
          const d = await differ(page, await shot("rest-page"), await shot("rest-default-plain"));
          check(`${mode} rest-page: a head on the page looks at rest like the table without it`, d === 0, d);
        }
      });

      await page.close();
    }

    // ── The other dark spelling ──────────────────────────────────────────
    {
      const page = await open(fixture, 1000, 700, "dark-class");
      // The dark surfaces are roles (1.114.0; they were GitHub's #161b22 and #1c2128): the card level for head and
      // cells, the recessed step for an even row — resolved here, so the check holds the relation, not the hex.
      const r = await page.evaluate(() => {
        const role = (v) => { const s = document.createElement("span"); s.style.color = `var(${v})`; GKT.wrap("sc").appendChild(s); const c = getComputedStyle(s).color; s.remove(); return c; };
        return { head: GKT.bg(GKT.wrap("bo").querySelector("thead th:last-child")), cell: GKT.bg(GKT.rows("sc")[0].children[2]), even: GKT.bg(GKT.rows("sc")[1].children[2]),
          surface: role("--gk-surface"), low: role("--gk-surface-container-low") };
      });
      check(".gk-dark: the head and the sticky cells take the dark surfaces", r.head === r.surface && r.cell === r.surface && r.even === r.low && r.surface !== r.low, r);
      await page.evaluate(() => GKT.box("sc").scrollIntoView());
      await opaque(page, "gk-dark", "sc", "rows at rest");
      await page.close();
    }

    // ── A phone ──────────────────────────────────────────────────────────
    for (const mode of ["light", "dark"]) {
      const page = await open(fixture, 390, 844, mode);
      const twin = async (a, b) => page.evaluate(([a, b]) => {
        const sig = (id) => {
          const w = GKT.wrap(id), wr = GKT.r(w);
          // what is on screen: the head of a card table is not (display: none)
          const cells = [...w.querySelectorAll(".gk-table th, .gk-table td")].filter((c) => c.getClientRects().length > 0).map((c) => {
            const r = GKT.r(c), s = getComputedStyle(c), after = getComputedStyle(c, "::after");
            return [Math.round((r.left - wr.left) * 10), Math.round((r.top - wr.top) * 10), Math.round(r.width * 10), Math.round(r.height * 10),
              s.position, s.backgroundColor, s.backgroundImage, s.zIndex, s.borderTopWidth, s.borderBottomWidth, s.borderRadius, s.boxShadow, after.content].join("|");
          });
          const inner = w.querySelector(".gk-empty-inner");
          return { h: Math.round(wr.height * 10), cells, empty: inner ? [Math.round((GKT.r(inner).left - wr.left) * 10), Math.round(GKT.r(inner).width * 10)].join("|") : "" };
        };
        const x = sig(a), y = sig(b);
        const diff = x.cells.map((c, i) => (c === y.cells[i] ? null : [i, c, y.cells[i]])).filter(Boolean);
        const b0 = GKT.box(a);
        return { same: x.h === y.h && x.cells.length === y.cells.length && diff.length === 0 && x.empty === y.empty, h: [x.h, y.h], n: [x.cells.length, y.cells.length], diff: diff.slice(0, 2), empty: [x.empty, y.empty],
          box: b0 ? { overflow: getComputedStyle(b0).overflowY, max: getComputedStyle(b0).maxHeight } : null };
      }, [a, b]);
      // A box that was scrolled sideways before the window narrowed may still carry its mark,
      // and a row may be the open sheet's: card mode does not show either differently.
      await page.evaluate(() => {
        ["cd", "cd-rl"].forEach((id) => GKT.box(id).setAttribute("data-gk-scrolled-x", ""));
        ["cd-rl", "cd-rl-plain"].forEach((id) => GKT.rows(id)[1].setAttribute("aria-current", "true"));
      });
      for (const [a, b] of [["cd", "cd-plain"], ["cd-box", "cd-box-plain"], ["cd-empty", "cd-empty-plain"], ["cd-rl", "cd-rl-plain"]]) {
        const r = await twin(a, b);
        check(`${mode} 390px ${a}: card mode is untouched — every cell where and as its twin without the options`, r.same, r);
        check(`${mode} 390px ${a}: in card mode the box neither scrolls nor has a height`, r.box && r.box.overflow === "visible" && r.box.max === "none", r.box);
      }
      await page.evaluate(() => GKT.box("ms").scrollIntoView());
      const ms = await page.evaluate(() => { const b = GKT.box("ms"); return { sw: b.scrollWidth, cw: b.clientWidth, card: GKT.wrap("ms").classList.contains("gk-table-mobile-card") }; });
      check(`${mode} 390px ms: mobile('scroll') scrolls sideways in its box`, !ms.card && ms.sw > ms.cw + 100, ms);
      const c = await column(page, "ms", 200);
      check(`${mode} 390px ms: the checkbox and the first column stick at their edges`, c.at.every((row) => row.every((left, j) => near(left, c.want0[j]))), c.at[0]);
      await opaque(page, `${mode} 390px`, "ms", "mobile('scroll')", 200);
      const li = await page.evaluate(() => { const b = GKT.box("li"); b.scrollIntoView(); b.scrollTop = 150; const th = b.querySelector("thead th"); return { top: GKT.r(th).top - GKT.r(b).top - b.clientTop, y: b.scrollTop, list: GKT.wrap("li").classList.contains("gk-table-list") }; });
      check(`${mode} 390px li: a list keeps its head at the top of its box`, li.list && li.y > 100 && near(li.top, 0), li);
      await page.close();
    }

    // ── Wide screen, the card twins are tables again and stick ───────────
    {
      const page = await open(fixture, 1000, 700, "light");
      await page.evaluate(() => GKT.box("cd").scrollIntoView());
      const c = await column(page, "cd", 300);
      check("1000px cd: a card-mode table is a table on a wide screen, and its column sticks", c.scrolled > 100 && c.at.every((row) => row.every((left, j) => near(left, c.want0[j]))), c.at[0]);
      await page.close();
    }

    // ── gridkit.js loaded after the page was ready ───────────────────────
    // GK.init() then runs at once, before this block exists, so its GK.initContent() call does
    // not reach it: the block's own first pass has to bind the tables.
    {
      const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
      page.on("pageerror", (e) => pageErrors.push(`late script: ${e.message}`));
      await page.goto("file://" + bare);
      await page.addScriptTag({ content: gridkitJs });
      await page.addScriptTag({ content: HELPERS });
      const r = await page.evaluate(() => ({ ready: document.readyState, v: parseFloat(GKT.box("sc").style.getPropertyValue("--gk-table-sticky-left-3")), want: GKT.r(GKT.box("sc").querySelector("thead th")).width + GKT.r(GKT.box("sc").querySelectorAll("thead th")[1]).width }));
      check("gridkit.js added after the page was ready measures the tables all the same", r.ready === "complete" && near(r.v, r.want), r);
      await page.close();
    }

    // ── gridkit.js without the 1.107.0 block ─────────────────────────────
    {
      const page = await open(older, 1000, 700, "light");
      const r = await page.evaluate(() => {
        const b = GKT.box("sc");
        b.scrollIntoView();
        b.scrollLeft = 300;
        const inner = GKT.r(b).left + b.clientLeft, tr = GKT.rows("sc")[0];
        return { vars: b.style.cssText, cells: [...tr.children].slice(0, 3).map((c) => Math.round(GKT.r(c).left - inner)), scrolled: b.scrollLeft };
      });
      check("older gridkit.js: no offsets are written", !/sticky-left/.test(r.vars), r.vars);
      check("older gridkit.js: the checkbox sticks at 0, the cell after it at 36px, the column after the left buttons scrolls", r.cells[0] === 0 && r.cells[1] === 36 && r.cells[2] < 0, r);
      await page.click('[data-gk-table="st"] [data-gk-sort="amount"]');
      await page.waitForTimeout(50);
      const s = await page.evaluate(() => GKT.rows("st").length);
      check("older gridkit.js: a setData() table still sorts", s === 15, s);
      await page.close();
    }
  } finally {
    await browser.close();
  }

  let failed = 0;
  for (const r of results) {
    console.log((r.ok ? "ok    " : "FAIL  ") + r.name);
    if (!r.ok) { failed++; if (r.detail) console.log("        " + r.detail.slice(0, 600)); }
  }
  if (pageErrors.length) { failed++; console.log("FAIL  script errors on the page:\n        " + pageErrors.join("\n        ")); }
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
})();

/** The alpha of a computed colour, on this side. */
function GKT_alpha(c) {
  let m = String(c).match(/rgba\(.*,\s*([\d.]+)\)/);
  if (m) return +m[1];
  m = String(c).match(/\/\s*([\d.]+)\)/);
  if (m) return +m[1];
  return c === "transparent" ? 0 : 1;
}
