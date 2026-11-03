#!/usr/bin/env node
/**
 * Table::densityToggle() (1.109.0) in a real browser: what a step does to every kind of table,
 * with and without css/themes.css, on a page that has a density of its own, after a reload,
 * after the client rebuild and the AJAX reload, from the keyboard, in dark mode, with storage
 * that throws, on a phone, without the script and with the copy of js/gridkit.js that has no
 * 1.109.0 block.
 *
 * The page is ci/browser-density-fixture.php: every table twice, with the control and without
 * it ("n-…"). At Normal a table must measure exactly as its twin, cell by cell — that is what
 * "the toggle changes nothing until it is used" means in pixels.
 *
 *     node ci/browser-density.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-density.js
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-density-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
const build = (name, args) => {
  const file = path.join(dir, name + ".html");
  fs.writeFileSync(file, execFileSync(php, [path.join(__dirname, "browser-density-fixture.php"), ...args], { maxBuffer: 64 * 1024 * 1024 }));
  return "file://" + file;
};
const PAGE = {
  plain: build("plain", []),
  noThemes: build("no-themes", ["--no-themes"]),
  konsole: build("konsole", ["--page=konsole"]),
  konsoleNoThemes: build("konsole-no-themes", ["--page=konsole", "--no-themes"]),
  weit: build("weit", ["--page=weit"]),
  oldJs: build("old-js", ["--old-js"]),
  late: build("late", ["--late"]),
};

/** Every table with the control; its twin is "n-" + key. */
const KEYS = ["md", "sm", "lg", "list", "link", "cmp", "pad", "vc", "xs", "grp", "scr", "scrs", "srv"];
const STEPS = ["konsole", "normal", "weit"];

/** Padding of the first data cell and the first header cell, "top right", per step at 1200px. */
const DESKTOP = {
  md: { konsole: ["8px 12px", "6px 12px"], normal: ["12px 16px", "10px 16px"], weit: ["16px 20px", "14px 20px"] },
  sm: { konsole: ["2px 6px", "2px 6px"], normal: ["6px 10px", "6px 10px"], weit: ["10px 14px", "10px 14px"] },
  lg: { konsole: ["12px 16px", "10px 16px"], normal: ["16px 20px", "14px 20px"], weit: ["20px 24px", "18px 24px"] },
  link: { konsole: ["8px 12px", "6px 12px"], normal: ["12px 16px", "10px 16px"], weit: ["16px 20px", "14px 20px"] },
  cmp: { konsole: ["4px 8px", "4px 8px"], normal: ["8px 12px", "8px 12px"], weit: ["12px 16px", "12px 16px"] },
  pad: { konsole: ["12px 16px", "10px 16px"], normal: ["16px 20px", "14px 20px"], weit: ["20px 24px", "18px 24px"] },
  vc: { konsole: ["2px 4px", "2px 4px"], normal: ["4px 8px", "4px 8px"], weit: ["8px 12px", "8px 12px"] },
  xs: { konsole: ["2px 4px", "2px 4px"], normal: ["4px 8px", "4px 8px"], weit: ["8px 12px", "8px 12px"] },
};
/** A list steps by its row (td height), not by its columns. */
const LIST_ROW = { konsole: 52, normal: 64, weit: 76 };
/** At 390px, outside card mode: md 10px 12px, sm 8px 10px at Normal. */
const PHONE = {
  scr: { konsole: "6px 8px", normal: "10px 12px", weit: "14px 16px" },
  scrs: { konsole: "4px 6px", normal: "8px 10px", weit: "12px 14px" },
};
/** A card at 390px: its padding (no link) and the space under it. */
const CARD = {
  konsole: { padding: "8px 12px 8px 12px", gap: "4px", field: "1px" },
  normal: { padding: "12px 16px 12px 16px", gap: "8px", field: "3px" },
  weit: { padding: "16px 20px 16px 20px", gap: "12px", field: "5px" },
};

/** Everything a step changes, measured per table in the page. */
function measure() {
  const out = {};
  const shown = (el) => el.offsetParent !== null && getComputedStyle(el).display !== "none";
  const pad = (el) => (el ? getComputedStyle(el).paddingTop + " " + getComputedStyle(el).paddingRight : null);
  document.querySelectorAll("[data-gk-table]").forEach((w) => {
    const id = w.dataset.gkTable;
    const cells = [...w.querySelectorAll(".gk-table th, .gk-table td")].filter(shown).map((el) => {
      const s = getComputedStyle(el);
      return [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft, Math.round(el.getBoundingClientRect().height * 10) / 10].join(" ");
    });
    // Left and right of every cell: a list's columns do not take a step.
    const sides = [...w.querySelectorAll(".gk-table th, .gk-table td")].filter(shown).map((el) => getComputedStyle(el).paddingLeft + " " + getComputedStyle(el).paddingRight);
    const dataRows = [...w.querySelectorAll(".gk-table tbody tr")].filter((tr) => shown(tr) && !tr.matches(".gk-table-group, .gk-table-more, .gk-empty-row"));
    const firstTd = dataRows[0] ? [...dataRows[0].children].find((td) => !td.matches(".gk-cb-col, .gk-actions")) : null;
    const th = [...w.querySelectorAll(".gk-table thead th")].find((t) => !t.matches(".gk-cb-col") && shown(t));
    const label = w.querySelector(".gk-table .gk-label");
    const group = w.querySelector("[data-gk-density-toggle]");
    const cards = w.matches(".gk-table-mobile-card") && dataRows[0] && getComputedStyle(dataRows[0]).display === "grid"
      ? dataRows.map((tr) => {
          const s = getComputedStyle(tr);
          const tds = [...tr.children].filter(shown);
          const head = tds.filter((td) => getComputedStyle(td).gridRowStart === "1");
          const fields = tds.filter((td) => !head.includes(td));
          return {
            h: Math.round(tr.getBoundingClientRect().height * 10) / 10,
            padding: [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft].join(" "),
            gap: s.marginBottom,
            link: tr.matches(".gk-row-link"),
            field: fields.length ? getComputedStyle(fields[0]).paddingTop : null,
            headBottoms: [...new Set(head.map((td) => Math.round(td.getBoundingClientRect().bottom)))].length,
            // The head keeps its own spacing: the checkbox, the buttons and the title above their rule.
            headPads: head.map((td) => getComputedStyle(td).paddingTop + " " + getComputedStyle(td).paddingBottom),
            overflow: tr.scrollWidth - tr.clientWidth,
          };
        })
      : null;
    out[id] = {
      density: w.getAttribute("data-gk-density"),
      cells,
      sides,
      rows: dataRows.map((tr) => Math.round(tr.getBoundingClientRect().height * 10) / 10),
      td: pad(firstTd),
      tdHeight: firstTd ? Math.round(firstTd.getBoundingClientRect().height) : null,
      th: pad(th),
      radius: label ? getComputedStyle(label).borderTopLeftRadius : null,
      buttons: [...w.querySelectorAll(".gk-table tbody .gk-btn")].filter(shown).map((b) => {
        const r = b.getBoundingClientRect();
        const c = b.closest("td").getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height), inside: r.top >= c.top - 0.5 && r.bottom <= c.bottom + 0.5 };
      }),
      cards,
      control: group
        ? {
            shown: shown(group),
            pressed: [...group.querySelectorAll('[aria-pressed="true"]')].map((b) => b.getAttribute("data-gk-set-density")),
            itemHeight: Math.round(group.querySelector("button").getBoundingClientRect().height),
            height: Math.round(group.getBoundingClientRect().height),
            width: Math.round(group.getBoundingClientRect().width),
            // The buttons side by side and the group's border: what the control needs, no more.
            ownWidth: Math.round([...group.children].reduce((s, b) => s + b.getBoundingClientRect().width, 0) + 2),
          }
        : null,
      searchHeight: w.querySelector(".gk-search") ? Math.round(w.querySelector(".gk-search").getBoundingClientRect().height) : null,
    };
  });
  return out;
}

/** The WCAG ratios of the control's words against what they stand on, in the page. */
function controlContrast(id) {
  const ctx = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
  const rgb = (c) => { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = "#000"; ctx.fillStyle = c; ctx.fillRect(0, 0, 1, 1); const d = ctx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const lum = ([r, g, b]) => [r, g, b].map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
  const ground = (el) => { for (let e = el; e; e = e.parentElement) { const c = rgb(getComputedStyle(e).backgroundColor); if (c[3] > 0.99) return c; } return [255, 255, 255, 1]; };
  const over = (top, under) => top[3] >= 0.99 ? top : top.map((v, i) => (i < 3 ? v * top[3] + under[i] * (1 - top[3]) : 1));
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
  const group = document.querySelector(`[data-gk-table="${id}"] [data-gk-density-toggle]`);
  return [...group.querySelectorAll("button")].map((b) => {
    const under = ground(b.parentElement);
    const bg = over(rgb(getComputedStyle(b).backgroundColor), under);
    return { step: b.getAttribute("data-gk-set-density"), pressed: b.getAttribute("aria-pressed") === "true", text: Math.round(ratio(over(rgb(getComputedStyle(b).color), bg), bg) * 100) / 100 };
  });
}

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const pageErrors = [];
  const check = (name, ok, detail) => results.push({ name, ok: !!ok, detail });
  const open = async (url, opts = {}) => {
    const context = await browser.newContext({ viewport: { width: opts.width || 1200, height: 900 }, javaScriptEnabled: opts.js !== false });
    if (opts.init) await context.addInitScript(opts.init);
    const page = await context.newPage();
    page.on("pageerror", (e) => (opts.errors || pageErrors).push(`${opts.name || url}: ${e.message}`));
    await page.goto(url);
    if (opts.late) await page.waitForFunction(() => window.GK && document.querySelector("[data-gk-density-toggle]:not([hidden])"), null, { timeout: 5000 }).catch(() => {});
    return { page, context };
  };
  /** Click one step on every table that has the control, or on the tables named. */
  const choose = (page, step, ids = KEYS) => page.evaluate(([s, list]) => list.forEach((id) => {
    const b = document.querySelector(`[data-gk-table="${id}"] [data-gk-set-density="${s}"]`);
    if (b) b.click();
  }), [step, ids]);
  const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

  try {
    // ── 1. 1200px, with and without themes.css: the start, the twins, every step ─────────────
    const desktop = {};
    for (const [name, url] of [["themes.css", PAGE.plain], ["gridkit.css only", PAGE.noThemes]]) {
      const { page, context } = await open(url, { name });
      const m0 = await page.evaluate(measure);
      check(`${name}: every control is shown, at Normal, and the wrap carries no step`,
        KEYS.every((k) => m0[k].control && m0[k].control.shown && same(m0[k].control.pressed, ["normal"]) && m0[k].density === null),
        JSON.stringify(KEYS.map((k) => [k, m0[k].control, m0[k].density])));
      check(`${name}: a twin has no control`, KEYS.every((k) => m0["n-" + k].control === null));
      for (const k of KEYS) {
        check(`${name} ${k}: at Normal every cell measures as in the twin`, same(m0[k].cells, m0["n-" + k].cells) && same(m0[k].rows, m0["n-" + k].rows),
          JSON.stringify([m0[k].cells.slice(0, 4), m0["n-" + k].cells.slice(0, 4)]));
      }
      check(`${name}: the control is as tall as the search beside it`, m0.md.control.height === m0.md.searchHeight, `${m0.md.control.height} / ${m0.md.searchHeight}`);
      const by = {};
      for (const step of STEPS) {
        await choose(page, step);
        const m = (by[step] = await page.evaluate(measure));
        check(`${name} ${step}: the button is pressed and the wrap carries the step`,
          KEYS.every((k) => same(m[k].control.pressed, [step]) && m[k].density === (step === "normal" ? null : step)),
          JSON.stringify(KEYS.map((k) => [k, m[k].control.pressed, m[k].density])));
        for (const [k, want] of Object.entries(DESKTOP)) {
          check(`${name} ${step} ${k}: a cell is ${want[step][0]}, a header ${want[step][1]}`, m[k].td === want[step][0] && m[k].th === want[step][1], `${m[k].td} / ${m[k].th}`);
        }
        check(`${name} ${step} list: a row is ${LIST_ROW[step]}px`, m.list.tdHeight === LIST_ROW[step], m.list.tdHeight);
        check(`${name} ${step}: the labels are ${step === "konsole" ? "4px" : "round"}`,
          KEYS.filter((k) => m[k].radius !== null).every((k) => m[k].radius === (step === "konsole" ? "4px" : "9999px")),
          JSON.stringify(KEYS.map((k) => m[k].radius)));
        check(`${name} ${step}: a row link is at least 44px`, m.link.rows.every((h) => h >= 44) && m.list.rows.every((h) => h >= 44), JSON.stringify([m.link.rows, m.list.rows]));
        check(`${name} ${step}: every row button keeps its size and stays inside its cell`,
          KEYS.every((k) => same(m[k].buttons.map((b) => [b.w, b.h]), m0[k].buttons.map((b) => [b.w, b.h])) && m[k].buttons.every((b) => b.inside)),
          JSON.stringify(KEYS.map((k) => [k, m[k].buttons])));
        check(`${name} ${step}: the twins did not move`, KEYS.every((k) => same(m["n-" + k].cells, m0["n-" + k].cells)));
        check(`${name} ${step}: a list's columns keep their sides`, same(m.list.sides, m0["n-list"].sides), JSON.stringify([m.list.sides, m0["n-list"].sides]));
      }
      for (const k of ["md", "sm", "lg", "list", "cmp", "pad", "vc", "xs", "grp"]) {
        const h = STEPS.map((s) => by[s][k].rows[0]);
        check(`${name} ${k}: Compact is lower than Normal, Normal lower than Roomy`, h[0] < h[1] && h[1] < h[2], h.join(" < "));
      }
      desktop[name] = by;
      await context.close();
    }
    const strip = (by) => Object.fromEntries(STEPS.map((s) => [s, Object.fromEntries(KEYS.map((k) => [k, [by[s][k].cells, by[s][k].radius]]))]));
    check("without themes.css every step measures as with it", same(strip(desktop["themes.css"]), strip(desktop["gridkit.css only"])));

    // ── 2. A page with a density of its own ──────────────────────────────────────────────────
    for (const [name, url, themes] of [["konsole page", PAGE.konsole, true], ["konsole page, gridkit.css only", PAGE.konsoleNoThemes, false]]) {
      const { page, context } = await open(url, { name });
      let m = await page.evaluate(measure);
      check(`${name}: the control starts at Compact, and the table is compact`,
        KEYS.every((k) => same(m[k].control.pressed, ["konsole"]) && m[k].density === "konsole") && m.md.td === "8px 12px" && m.md.radius === "4px",
        JSON.stringify([m.md.control.pressed, m.md.density, m.md.td, m.md.radius]));
      await choose(page, "normal");
      m = await page.evaluate(measure);
      // Normal here is every table as its twin on a page without a density, the page's 4px labels undone.
      const plain = desktop[themes ? "themes.css" : "gridkit.css only"].normal;
      for (const k of KEYS) {
        check(`${name} ${k}: Normal is the default rhythm, measured as on a page without a density`,
          m[k].density === "normal" && same(m[k].cells, plain["n-" + k].cells) && m[k].radius === plain["n-" + k].radius,
          JSON.stringify([m[k].density, m[k].td, m[k].radius, plain["n-" + k].td]));
      }
      check(`${name}: Normal differs from the page, so it is stored`, (await page.evaluate(() => localStorage.getItem("gk-density-md"))) === "normal");
      await choose(page, "weit", ["md"]);
      m = await page.evaluate(measure);
      check(`${name}: Roomy is roomy and its labels round`, m.md.td === "16px 20px" && m.md.radius === "9999px", `${m.md.td} ${m.md.radius}`);
      await choose(page, "konsole", ["md"]);
      check(`${name}: choosing the page's own step forgets the choice`, (await page.evaluate(() => localStorage.getItem("gk-density-md"))) === null);
      if (themes) {
        check(`${name}: a twin keeps the page's density as before (labels 4px, padding of its size)`,
          m["n-md"].radius === "4px" && m["n-md"].td === "12px 16px", `${m["n-md"].radius} ${m["n-md"].td}`);
      }
      await context.close();
    }
    {
      const { page, context } = await open(PAGE.weit, { name: "weit page" });
      const m = await page.evaluate(measure);
      check("weit page: the control starts at Roomy, and the table is roomy",
        same(m.md.control.pressed, ["weit"]) && m.md.density === "weit" && m.md.td === "16px 20px", JSON.stringify([m.md.control.pressed, m.md.td]));
      await context.close();
    }

    // ── 3. Remembered per table id, in the namespace of GK.theme ─────────────────────────────
    {
      const { page, context } = await open(PAGE.plain, { name: "reload" });
      await choose(page, "weit", ["md"]);
      await choose(page, "konsole", ["sm"]);
      await page.reload();
      let m = await page.evaluate(measure);
      check("after a reload each table has its own step back, the others are at Normal",
        same(m.md.control.pressed, ["weit"]) && m.md.td === "16px 20px" && same(m.sm.control.pressed, ["konsole"]) && m.sm.td === "2px 6px"
          && same(m.lg.control.pressed, ["normal"]) && m.lg.density === null,
        JSON.stringify([m.md.control.pressed, m.md.td, m.sm.control.pressed, m.lg.control.pressed]));
      await choose(page, "normal", ["md"]);
      check("Normal on a page without a density is not stored", (await page.evaluate(() => localStorage.getItem("gk-density-md"))) === null);
      await page.reload();
      m = await page.evaluate(measure);
      check("… and after a reload the table is at Normal again", same(m.md.control.pressed, ["normal"]) && m.md.density === null);
      // Something else under the key (an older value, a hand edit) is not a step.
      await page.evaluate(() => localStorage.setItem("gk-density-md", "tight"));
      await page.reload();
      m = await page.evaluate(measure);
      check("a stored value that is not a step is ignored", same(m.md.control.pressed, ["normal"]) && m.md.density === null, JSON.stringify([m.md.control.pressed, m.md.density]));
      await context.close();
    }
    {
      const { page, context } = await open(PAGE.plain, { name: "scope", init: () => { window.GK_THEME_SCOPE = "u7"; } });
      await choose(page, "weit", ["md"]);
      const keys = await page.evaluate(() => Object.keys(localStorage));
      check("the key carries the namespace of GK.theme", same(keys, ["gk-density-md:u7"]), JSON.stringify(keys));
      await context.close();
    }

    // ── 4. The redraws: sort and search (renderStatic), the AJAX reload, a table that arrives later
    {
      const fetchStub = () => {
        window.fetch = function () {
          const body = JSON.parse(document.getElementById("gk-fixture").textContent).fragment;
          return Promise.resolve(new Response(body, { status: 200 }));
        };
      };
      const { page, context } = await open(PAGE.plain, { name: "redraw", init: fetchStub });
      await choose(page, "konsole", ["md"]);
      await page.click('[data-gk-table="md"] [data-gk-sort="name"]');
      let m = await page.evaluate(measure);
      const first = await page.evaluate(() => document.querySelector('[data-gk-table="md"] tbody tr td:nth-child(3)').textContent);
      check("after a sort the table is rebuilt and still compact", first.startsWith("Anton") && m.md.density === "konsole" && m.md.td === "8px 12px" && same(m.md.control.pressed, ["konsole"]),
        JSON.stringify([first, m.md.density, m.md.td]));
      await page.fill('[data-gk-table="md"] [data-gk-search]', "ber");
      await page.waitForFunction(() => document.querySelectorAll('[data-gk-table="md"] tbody tr[data-gk-row-id]').length === 1, null, { timeout: 3000 }).catch(() => {});
      m = await page.evaluate(measure);
      check("after a search the table is rebuilt and still compact", m.md.rows.length === 1 && m.md.density === "konsole" && m.md.td === "8px 12px", JSON.stringify([m.md.rows, m.md.td]));
      // A button with a step the stylesheet does not know (written by hand) changes nothing.
      await page.evaluate(() => {
        const g = document.querySelector('[data-gk-table="md"] [data-gk-density-toggle]');
        g.insertAdjacentHTML("beforeend", '<button type="button" class="gk-segmented-item" data-gk-set-density="tight">Tight</button>');
        g.lastElementChild.click();
      });
      m = await page.evaluate(measure);
      check("a step the stylesheet does not know is ignored", m.md.density === "konsole" && same(m.md.control.pressed, ["konsole"])
        && (await page.evaluate(() => localStorage.getItem("gk-density-md"))) === "konsole", JSON.stringify([m.md.density, m.md.control.pressed]));

      await choose(page, "weit", ["srv"]);
      await page.evaluate(() => GK.table.refresh("srv"));
      await page.waitForFunction(() => /Berta/.test(document.querySelector('[data-gk-table="srv"] tbody').textContent), null, { timeout: 3000 }).catch(() => {});
      m = await page.evaluate(measure);
      const toolbars = await page.evaluate(() => document.querySelectorAll('[data-gk-table="srv"] .gk-toolbar').length);
      check("after an AJAX reload the new rows are roomy and the control is still there",
        m.srv.td === "16px 20px" && m.srv.density === "weit" && m.srv.control.shown && same(m.srv.control.pressed, ["weit"]) && toolbars === 1,
        JSON.stringify([m.srv.td, m.srv.density, m.srv.control, toolbars]));

      await page.evaluate(() => localStorage.setItem("gk-density-later", "konsole"));
      await page.evaluate(() => {
        const host = document.getElementById("later-host");
        host.innerHTML = JSON.parse(document.getElementById("gk-fixture").textContent).later;
        GK.initContent(host);
      });
      m = await page.evaluate(measure);
      check("a table handed to GK.initContent() later gets its control and its stored step",
        m.later.control.shown && same(m.later.control.pressed, ["konsole"]) && m.later.density === "konsole" && m.later.td === "8px 12px",
        JSON.stringify([m.later.control, m.later.density, m.later.td]));
      await context.close();
    }

    // ── 5. The keyboard and the names ────────────────────────────────────────────────────────
    {
      const { page, context } = await open(PAGE.plain, { name: "keyboard" });
      const names = await page.evaluate(() => {
        const g = document.querySelector('[data-gk-table="md"] [data-gk-density-toggle]');
        return { role: g.getAttribute("role"), label: g.getAttribute("aria-label"), buttons: [...g.querySelectorAll("button")].map((b) => [b.type, b.textContent]) };
      });
      check("the control is a group with a name, and its buttons have names",
        names.role === "group" && names.label === "Row spacing" && same(names.buttons, [["button", "Compact"], ["button", "Normal"], ["button", "Roomy"]]), JSON.stringify(names));
      await page.focus('[data-gk-table="md"] [data-gk-search]');
      const at = () => page.evaluate(() => [document.activeElement.getAttribute("data-gk-set-density"), getComputedStyle(document.activeElement).outlineStyle]);
      await page.keyboard.press("Tab");
      const a = await at();
      await page.keyboard.press(" ");
      let m = await page.evaluate(measure);
      check("Tab from the search reaches Compact, it shows a focus ring, and Space presses it",
        a[0] === "konsole" && a[1] !== "none" && same(m.md.control.pressed, ["konsole"]) && m.md.td === "8px 12px", JSON.stringify([a, m.md.control.pressed]));
      await page.keyboard.press("Tab");
      await page.keyboard.press("Tab");
      const b = await at();
      await page.keyboard.press("Enter");
      m = await page.evaluate(measure);
      check("Tab moves on to Roomy and Enter presses it; the focus stays on it", b[0] === "weit" && same(m.md.control.pressed, ["weit"]) && (await at())[0] === "weit",
        JSON.stringify([b, m.md.control.pressed]));
      await context.close();
    }

    // ── 6. Dark mode, both spellings, with and without themes.css ────────────────────────────
    for (const [name, url] of [["themes.css", PAGE.plain], ["gridkit.css only", PAGE.noThemes]]) {
      for (const spelling of ["[data-gk-mode=dark]", ".gk-dark"]) {
        const { page, context } = await open(url, { name: `dark ${spelling} ${name}` });
        await page.evaluate((s) => {
          if (s === ".gk-dark") { document.body.classList.add("gk-dark"); document.body.removeAttribute("data-gk-mode"); }
          else document.body.dataset.gkMode = "dark";
        }, spelling);
        await choose(page, "konsole", ["md"]);
        // The buttons fade between their colours (--gk-transition-state); read them once they have.
        await page.waitForTimeout(500);
        const m = await page.evaluate(measure);
        const ratios = await page.evaluate(controlContrast, "md");
        check(`dark ${spelling}, ${name}: a step works as in light mode`, m.md.td === "8px 12px" && m.md.radius === "4px", `${m.md.td} ${m.md.radius}`);
        check(`dark ${spelling}, ${name}: every word of the control reads at 4.5:1 or more, the pressed one too`,
          ratios.every((r) => r.text >= 4.5) && ratios.some((r) => r.pressed), JSON.stringify(ratios));
        await context.close();
      }
    }
    {
      const { page, context } = await open(PAGE.plain, { name: "light contrast" });
      await page.waitForTimeout(500);
      const ratios = await page.evaluate(controlContrast, "md");
      check("light: every word of the control reads at 4.5:1 or more", ratios.every((r) => r.text >= 4.5), JSON.stringify(ratios));
      await context.close();
    }

    // ── 7. Storage that is missing or throws ─────────────────────────────────────────────────
    for (const [name, init] of [
      ["localStorage throws on access", () => { Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new DOMException("denied", "SecurityError"); } }); }],
      ["localStorage throws on write", () => { Storage.prototype.setItem = function () { throw new DOMException("full", "QuotaExceededError"); }; }],
    ]) {
      const errors = [];
      const { page, context } = await open(PAGE.plain, { name, init, errors });
      await choose(page, "konsole", ["md"]);
      let m = await page.evaluate(measure);
      check(`${name}: the control is shown and a step still applies to this page view`,
        m.md.control.shown && same(m.md.control.pressed, ["konsole"]) && m.md.td === "8px 12px", JSON.stringify([m.md.control, m.md.td]));
      // Nothing stored to fall back on: binding the page again (a live table, AJAX navigation)
      // must not undo the step that is only in the page.
      await page.evaluate(() => GK.initContent(document)).catch((e) => errors.push("GK.initContent: " + e.message));
      m = await page.evaluate(measure);
      check(`${name}: GK.initContent() again leaves the chosen step alone`, same(m.md.control.pressed, ["konsole"]) && m.md.td === "8px 12px", JSON.stringify([m.md.control.pressed, m.md.td]));
      check(`${name}: no script error`, errors.length === 0, errors.join(" | "));
      await context.close();
    }

    // ── 8. A phone: card mode, scroll mode, a list, the control ──────────────────────────────
    {
      const { page, context } = await open(PAGE.plain, { name: "phone", width: 390 });
      const m0 = await page.evaluate(measure);
      const cardKeys = KEYS.filter((k) => m0[k].cards);
      check("390px: the card tables are in card mode", ["md", "sm", "lg", "link", "cmp", "pad", "vc", "xs", "grp", "srv"].every((k) => cardKeys.includes(k)), cardKeys.join(","));
      for (const k of KEYS) {
        check(`390px ${k}: at Normal it measures as its twin`, same(m0[k].cells, m0["n-" + k].cells) && same(m0[k].cards, m0["n-" + k].cards),
          JSON.stringify([m0[k].cards && m0[k].cards[0], m0["n-" + k].cards && m0["n-" + k].cards[0]]));
      }
      check("390px: the control's buttons are 44px tall and the control is as wide as its buttons, not stretched across the toolbar",
        m0.md.control.itemHeight >= 44 && Math.abs(m0.md.control.width - m0.md.control.ownWidth) <= 1, JSON.stringify(m0.md.control));
      const by = {};
      for (const step of STEPS) {
        await choose(page, step);
        const m = (by[step] = await page.evaluate(measure));
        const plain = m.md.cards;
        check(`390px ${step}: the head of a card keeps its own spacing (checkbox, buttons, title)`,
          cardKeys.every((k) => same(m[k].cards.map((c) => c.headPads), m0["n-" + k].cards.map((c) => c.headPads))),
          JSON.stringify(cardKeys.map((k) => [k, m[k].cards[0].headPads, m0["n-" + k].cards[0].headPads])));
        check(`390px ${step}: a list's columns keep their sides`, same(m.list.sides, m0["n-list"].sides), JSON.stringify([m.list.sides, m0["n-list"].sides]));
        check(`390px ${step}: a card is ${CARD[step].padding}, ${CARD[step].gap} apart, a field ${CARD[step].field} above and below`,
          plain.every((c) => c.padding === CARD[step].padding && c.gap === CARD[step].gap && c.field === CARD[step].field), JSON.stringify(plain[0]));
        check(`390px ${step}: a card with a link keeps 40px on the right for its chevron`, m.link.cards.every((c) => c.link && c.padding.split(" ")[1] === "40px"), JSON.stringify(m.link.cards[0]));
        check(`390px ${step}: every card keeps its head on one line and nothing runs out of it`,
          cardKeys.every((k) => m[k].cards.every((c) => c.headBottoms === 1 && c.overflow <= 1)), JSON.stringify(cardKeys.map((k) => [k, m[k].cards[0]])));
        for (const [k, want] of Object.entries(PHONE)) check(`390px ${step} ${k} (scroll mode): a cell is ${want[step]}`, m[k].td === want[step], m[k].td);
        check(`390px ${step} list: a row is ${LIST_ROW[step]}px`, m.list.tdHeight === LIST_ROW[step], m.list.tdHeight);
      }
      for (const k of ["md", "sm", "lg", "cmp"]) {
        const h = STEPS.map((s) => by[s][k].cards[0].h);
        check(`390px ${k}: a compact card is lower than a normal one, a normal one lower than a roomy one`, h[0] < h[1] && h[1] < h[2], h.join(" < "));
      }
      await context.close();
    }

    // ── 9. No script, the older script, a script that comes late ─────────────────────────────
    {
      // The page's scripts are off; Playwright's own evaluation still runs.
      const { page, context } = await open(PAGE.plain, { name: "no js", js: false });
      const m = await page.evaluate(measure);
      const hidden = await page.$$eval("[data-gk-density-toggle]", (gs) => gs.map((g) => g.hidden));
      check("without JavaScript every control is hidden, no table carries a step, every table is its twin",
        hidden.length === KEYS.length && hidden.every(Boolean) && KEYS.every((k) => !m[k].control.shown && m[k].density === null && same(m[k].cells, m["n-" + k].cells)),
        JSON.stringify([hidden, KEYS.map((k) => [k, m[k].control.shown, m[k].density])]));
      await context.close();
    }
    {
      const errors = [];
      const { page, context } = await open(PAGE.oldJs, { name: "old js", errors });
      const m = await page.evaluate(measure);
      check("with a gridkit.js that has no 1.109.0 block the control stays hidden and the tables are their twins",
        KEYS.every((k) => !m[k].control.shown && m[k].density === null && same(m[k].cells, m["n-" + k].cells)), JSON.stringify(KEYS.map((k) => [k, m[k].control.shown])));
      await page.click('[data-gk-table="md"] [data-gk-sort="name"]');
      const first = await page.evaluate(() => document.querySelector('[data-gk-table="md"] tbody tr td:nth-child(3)').textContent);
      check("… and the older script still sorts it, without an error", first.startsWith("Anton") && errors.length === 0, first + " " + errors.join(" | "));
      await context.close();
    }
    {
      const { page, context } = await open(PAGE.late, { name: "late", late: true });
      await choose(page, "konsole", ["md"]);
      await page.reload();
      await page.waitForFunction(() => window.GK && document.querySelector("[data-gk-density-toggle]:not([hidden])"), null, { timeout: 5000 }).catch(() => {});
      const m = await page.evaluate(measure);
      check("a script added after the document was ready still shows the control and applies the stored step",
        m.md.control.shown && same(m.md.control.pressed, ["konsole"]) && m.md.td === "8px 12px" && m.sm.control.shown, JSON.stringify([m.md.control, m.md.td]));
      await context.close();
    }
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
