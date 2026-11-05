#!/usr/bin/env node
/**
 * DateRange and Calendar (1.120.0) in a real browser: js/calendar.js on the markup of
 * src/DateRange.php and src/Calendar.php.
 *
 * `php tests/run.php` runs no JavaScript: the markup the server writes is checked there
 * (tests/daterange.test.php, tests/calendar.test.php). Here: the browser counts every preset from
 * the server's today exactly as DateRange::presetRange() does (24 days on the edges of months,
 * quarters, years, leap years and clock changes) and writes the period in the words of
 * DateRange::text() (in English and German); a preset fills the dates, typing makes the select
 * "Custom", each date bounds the other, a filter form sends a preset at once, and what the browser
 * sends is read back by DateRange::fromRequest() (`php ci/browser-dates-fixture.php --read`) — with
 * the script and without. The calendar: the grid roles and the one tab stop, every key (arrows,
 * Home/End, PageUp/PageDown, Shift, Enter, Space, Tab into a day, Escape), a month loaded with the
 * focus kept on the day, the events and their veto, "+n more" one at a time, the API, the bars
 * aligned and joined, the accessibility tree, the agenda in a narrow column and at 390px with no
 * sideways scroll, the page without script, and contrast in 21 looks (plain and six themes × light,
 * data-gk-mode="dark" and .gk-dark).
 *
 * Every page is ci/browser-dates-fixture.php, served from http://gk.test/ by page.route(), so a
 * month link loads the fixture again in the same origin; the forms post to http://gk.test/….
 *
 *     node ci/browser-dates.js                      # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-dates.js
 *     GK_SHOTS=/some/dir node ci/browser-dates.js   # also writes screenshots there
 *     GK_VERBOSE=1 node ci/browser-dates.js         # prints each case as it runs (stderr)
 *
 * Exit code 0 when every case holds.
 */
const { execFileSync } = require("child_process");
const path = require("path");

let chromium;
try {
  ({ chromium } = require(process.env.GK_PLAYWRIGHT || "playwright"));
} catch (e) {
  console.error('Playwright not found. "npm i playwright" somewhere and point GK_PLAYWRIGHT at it.');
  process.exit(2);
}

const php = process.env.GK_PHP || "php";
const FIXTURE = path.join(__dirname, "browser-dates-fixture.php");
const run = (args) => execFileSync(php, [FIXTURE, ...args], { maxBuffer: 64 * 1024 * 1024 }).toString();
const pages = {};
const pageFor = (month, lang) => {
  const key = `${month || ""}|${lang || ""}`;
  if (!pages[key]) pages[key] = run([...(month ? [`--month=${month}`] : []), ...(lang ? [`--lang=${lang}`] : [])]);
  return pages[key];
};
const PARITY = JSON.parse(run(["--parity"]));
const read = (name, query) => JSON.parse(run(["--read", query, `--name=${name}`]));
const SHOTS = process.env.GK_SHOTS || "";
const TODAY = "2026-11-04";

/** Contrast of colours as the page paints them (a canvas reads any CSS colour the browser knows). */
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

/** Name and role as Chrome's accessibility tree has them. */
async function ax(page, sel) {
  const cdp = await page.context().newCDPSession(page);
  try {
    const { root } = await cdp.send("DOM.getDocument", { depth: 0 });
    const { nodeId } = await cdp.send("DOM.querySelector", { nodeId: root.nodeId, selector: sel });
    if (!nodeId) return null;
    const { nodes } = await cdp.send("Accessibility.getPartialAXTree", { nodeId, fetchRelatives: false });
    const n = nodes.find((x) => !x.ignored) || nodes[0];
    return { name: n.name ? n.name.value : "", role: n.role ? n.role.value : "" };
  } finally {
    await cdp.detach();
  }
}

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const check = (name, ok, detail) => {
    results.push({ name, ok: !!ok, detail: detail === undefined ? "" : String(detail) });
    if (process.env.GK_VERBOSE) console.error((ok ? "ok    " : "FAIL  ") + name + (ok || detail === undefined ? "" : "\n        " + detail));
  };
  const requests = [];
  const open = async (opts) => {
    const context = await browser.newContext(Object.assign({ viewport: { width: 1280, height: 900 } }, opts || {}));
    const errors = [];
    await context.route("http://gk.test/**", async (route) => {
      const u = new URL(route.request().url());
      if (u.pathname === "/" || u.pathname === "/cal") {
        return route.fulfill({ status: 200, contentType: "text/html; charset=utf-8", body: pageFor(u.searchParams.get("month"), u.searchParams.get("lang")) });
      }
      requests.push({ path: u.pathname, query: u.search.replace(/^\?/, ""), url: route.request().url() });
      return route.fulfill({ status: 200, contentType: "text/html", body: "<p>sent</p>" });
    });
    const page = await context.newPage();
    page.on("pageerror", (e) => errors.push(e.message));
    page.setDefaultTimeout(8000);
    await page.goto("http://gk.test/");
    return { context, page, errors };
  };
  const take = () => requests.splice(0);
  const lastTo = (p) => { const r = requests.filter((x) => x.path === p); return r.length ? r[r.length - 1] : null; };

  try {
    // ════ A. DateRange with the script ═══════════════════════════════════════
    let { context, page, errors } = await open();
    const arm = () => page.evaluate(() => {
      window._ev = [];
      ["gk:daterange", "gk:calendarnav", "gk:calendarselect"].forEach((n) =>
        document.addEventListener(n, (e) => window._ev.push(Object.assign({ type: n }, e.detail))));
    });
    const events = () => page.evaluate(() => window._ev.splice(0));
    await arm();
    const val = (id) => page.$eval(id, (el) => el.value);
    const pick = (name) => ({ sel: `#gk-daterange-${name}-preset`, from: `#gk-daterange-${name}-from`, to: `#gk-daterange-${name}-to`, out: `#gk-daterange-${name} .gk-daterange-text` });
    const P = pick("plain");

    const ready = await page.$$eval(".gk-daterange", (l) => l.map((d) => d.hasAttribute("data-gk-daterange-ready") + "/" + d.querySelector("[data-gk-daterange-shown]").disabled));
    check("every picker is set up, and name[shown] is left out of the POST", ready.length === 4 && ready.every((x) => x === "true/true"), ready.join(" "));

    await page.selectOption(P.sel, "last-month");
    check("a preset fills the dates", (await val(P.from)) === "2026-10-01" && (await val(P.to)) === "2026-10-31", `${await val(P.from)} ${await val(P.to)}`);
    check("and writes the period in words", (await page.textContent(P.out)) === "Oct 1 – 31, 2026", await page.textContent(P.out));
    let ev = await events();
    check("gk:daterange: { name, from, to, preset, text }", ev.length === 1 && ev[0].type === "gk:daterange" && ev[0].name === "plain" && ev[0].from === "2026-10-01"
      && ev[0].to === "2026-10-31" && ev[0].preset === "last-month" && ev[0].text === "Oct 1 – 31, 2026", JSON.stringify(ev));
    check("a preset in a page's own form is not sent by itself", lastTo("/plain") === null);

    // Every preset of the picker, against the server's.
    const bad = [];
    for (const p of PARITY.presets.filter((x) => x.today === TODAY)) {
      await page.selectOption(P.sel, p.preset);
      const got = [await val(P.from), await val(P.to)];
      if (got.join() !== [p.range.from, p.range.to].join()) bad.push(`${p.preset}: ${got} ≠ ${p.range.from},${p.range.to}`);
    }
    check("every preset of the select gives the days DateRange::presetRange() gives", bad.length === 0, bad.join("; "));

    // Typing a date.
    await page.selectOption(P.sel, "this-month");
    await events();
    await page.fill(P.from, "2026-11-03");
    check("typing a date makes the select Custom", (await val(P.sel)) === "custom", await val(P.sel));
    check("the words follow", (await page.textContent(P.out)) === "Nov 3 – 30, 2026", await page.textContent(P.out));
    check("each date bounds the other: from.max = to, to.min = from",
      (await page.$eval(P.from, (e) => e.max)) === "2026-11-30" && (await page.$eval(P.to, (e) => e.min)) === "2026-11-03");
    await page.fill(P.to, "2026-11-01");
    const invalid = await page.$eval(P.to, (e) => !e.checkValidity());
    check("an end before the start: the browser itself refuses it, and no words are written", invalid && (await page.textContent(P.out)) === "", await page.textContent(P.out));
    await page.fill(P.to, "2026-11-20");
    ev = await events();
    check("a typed date fires gk:daterange too", ev.some((e) => e.type === "gk:daterange" && e.preset === "custom" && e.from === "2026-11-03" && e.to === "2026-11-20"), JSON.stringify(ev));
    take();
    await page.click("#plain-submit");
    await page.waitForURL("http://gk.test/plain**");
    let r = lastTo("/plain");
    check("the POST carries preset, from and to, never shown", r && /plain%5Bpreset%5D=custom/.test(r.query) && !/shown/.test(r.query), r && r.query);
    check("and the server reads what the person typed", r && JSON.stringify(read("plain", r.query)) === JSON.stringify({ from: "2026-11-03", to: "2026-11-20", preset: "custom" }), r && JSON.stringify(read("plain", r.query)));

    // The filter form: a preset is sent at once, with the kept parameter; typed dates wait for Apply.
    await page.goto("http://gk.test/");
    await arm();
    take();
    const F = pick("period");
    await page.selectOption(F.sel, "last-month");
    await page.waitForURL("http://gk.test/list**");
    r = lastTo("/list");
    check("a filter sends a chosen preset at once, with its kept parameter", r && /status=open/.test(r.query) && /period%5Bpreset%5D=last-month/.test(r.query) && !/shown/.test(r.query), r && r.query);
    check("and the server reads last month", r && read("period", r.query).from === "2026-10-01" && read("period", r.query).preset === "last-month", r && JSON.stringify(read("period", r.query)));
    await page.goto("http://gk.test/");
    take();
    await page.fill(F.from, "2026-01-05");
    await page.fill(F.to, "2026-01-25");
    await page.waitForTimeout(150);
    check("typed dates are not sent by themselves", lastTo("/list") === null);
    await page.click("#gk-daterange-period .gk-daterange-apply");
    await page.waitForURL("http://gk.test/list**");
    r = lastTo("/list");
    check("Apply sends them, and the server reads them", r && JSON.stringify(read("period", r.query)) === JSON.stringify({ from: "2026-01-05", to: "2026-01-25", preset: "custom" }), r && r.query);

    // The Form field.
    await page.goto("http://gk.test/");
    await arm();
    take();
    const R = { sel: "#report-preset", from: "#report-from", to: "#report-to", out: "#report .gk-daterange-text" };
    await page.selectOption(R.sel, "last-7-days");
    await page.waitForTimeout(100);
    check("in a Form a preset is not sent by itself", lastTo("/report") === null);
    check("the Form field fills its dates", (await val(R.from)) === "2026-10-29" && (await val(R.to)) === "2026-11-04");
    await events();
    await page.evaluate(() => GK.dateRange.set("report", "this-month"));
    check("set() holds a preset within max (today): this month so far", (await val(R.from)) === "2026-11-01" && (await val(R.to)) === "2026-11-04" && (await val(R.sel)) === "custom",
      `${await val(R.from)} ${await val(R.to)} ${await val(R.sel)}`);
    check("set() fires nothing", (await events()).length === 0);
    await page.fill(R.from, "");
    await page.click("#report-form button[type=submit]");
    await page.waitForTimeout(150);
    check("a required Form field: the browser keeps an empty date from being sent", lastTo("/report") === null);
    await page.selectOption(R.sel, "last-year");
    await page.click("#report-form button[type=submit]");
    await page.waitForURL("http://gk.test/report**");
    r = lastTo("/report");
    check("the Form posts report[…], and the server reads last year", r && JSON.stringify(read("report", r.query)) === JSON.stringify({ from: "2025-01-01", to: "2025-12-31", preset: "last-year" }), r && r.query);

    // German words, the API, parity.
    await page.goto("http://gk.test/");
    await arm();
    await page.selectOption("#gk-daterange-de-range-preset", "last-month");
    check("German: 1.–31. Okt. 2026", (await page.textContent("#gk-daterange-de-range .gk-daterange-text")) === "1.–31. Okt. 2026",
      await page.textContent("#gk-daterange-de-range .gk-daterange-text"));
    const api = await page.evaluate(() => {
      const a = GK.dateRange.set("plain", { from: "2026-05-10", to: "2026-05-01" });
      const b = GK.dateRange.get("gk-daterange-plain");
      const c = GK.dateRange.set(document.querySelector("#gk-daterange-plain select"), "this-year");
      return { a, b, c, txt: document.querySelector("#gk-daterange-plain .gk-daterange-text").textContent };
    });
    check("GK.dateRange.set()/get(): dates in order and custom; a preset by key; by name, id or element",
      api.a.from === "2026-05-01" && api.a.to === "2026-05-10" && api.a.preset === "custom" && api.b.preset === "custom"
      && api.c.from === "2026-01-01" && api.c.preset === "this-year" && api.txt === "Jan 1 – Dec 31, 2026", JSON.stringify(api));
    const parity = await page.evaluate((p) => {
      const bad = [];
      p.presets.forEach((x) => { const r = GK.dateRange.presetRange(x.preset, x.today); if (JSON.stringify(r) !== JSON.stringify(x.range)) bad.push(`${x.preset}@${x.today}: ${JSON.stringify(r)} ≠ ${JSON.stringify(x.range)}`); });
      p.texts.forEach((x) => { const t = GK.dateRange.text(x.from, x.to, x.lang === "de" ? "de-range" : "plain"); if (t !== x.text) bad.push(`${x.lang} ${x.from}…${x.to}: "${t}" ≠ "${x.text}"`); });
      return { bad, n: p.presets.length + p.texts.length };
    }, PARITY);
    check(`the browser and PHP agree on ${parity.n} preset ranges and periods in words (en, de)`, parity.bad.length === 0, parity.bad.slice(0, 5).join("; "));
    check("no script error on the pickers", errors.length === 0, errors.join("; "));
    await context.close();

    // ════ B. Calendar with the script ════════════════════════════════════════
    ({ context, page, errors } = await open());
    await arm();
    const C = '[data-gk-cal="cal"]';
    const day = (d) => `${C} td[data-gk-cal-date="${d}"]`;
    const focused = () => page.evaluate(() => {
      const a = document.activeElement;
      return a && a.getAttribute ? (a.getAttribute("data-gk-cal-date") || a.tagName + "." + a.className) : "";
    });
    const roles = await page.evaluate((C) => {
      const t = document.querySelector(C + " .gk-cal-grid");
      const cells = [...t.querySelectorAll("td.gk-cal-day")];
      const zero = cells.filter((c) => c.tabIndex === 0).map((c) => c.getAttribute("data-gk-cal-date"));
      const links = [...t.querySelectorAll("a[href], summary, [role=button]")];
      const tabbable = links.filter((l) => l.tabIndex === 0).map((l) => l.closest("td").getAttribute("data-gk-cal-date"));
      return { grid: t.getAttribute("role"), cells: cells.every((c) => c.getAttribute("role") === "gridcell"), zero, tabbable: [...new Set(tabbable)],
        ready: document.querySelector(C).hasAttribute("data-gk-cal-ready"), buttons: [...t.querySelectorAll("span.gk-cal-event[role=button]")].length };
    }, C);
    check("the table is a grid of gridcells", roles.grid === "grid" && roles.cells && roles.ready, JSON.stringify(roles));
    check("one tab stop: today, and only today's links", roles.zero.join() === TODAY && roles.tabbable.join() === TODAY, JSON.stringify(roles));
    check("an event without a link is a button", roles.buttons > 0);

    await page.focus(day(TODAY));
    const keys = [["ArrowRight", "2026-11-05"], ["ArrowDown", "2026-11-12"], ["ArrowLeft", "2026-11-11"], ["ArrowUp", "2026-11-04"],
      ["Home", "2026-11-01"], ["End", "2026-11-07"], ["ArrowDown", "2026-11-14"], ["ArrowDown", "2026-11-21"], ["ArrowDown", "2026-11-28"], ["ArrowDown", "2026-12-05"]];
    const keyBad = [];
    for (const [k, want] of keys) {
      await page.keyboard.press(k);
      const got = await focused();
      if (got !== want) keyBad.push(`${k} → ${got}, not ${want}`);
    }
    check("arrows by a day and a week, Home and End to the ends of the week, into the days after the month", keyBad.length === 0, keyBad.join("; "));
    check("the tab stop follows the focus", (await page.$$eval(`${C} td[tabindex="0"]`, (l) => l.map((c) => c.getAttribute("data-gk-cal-date")))).join() === "2026-12-05");
    await events();
    // Past the edge of the grid: that month loads, the day keeps the focus.
    await page.keyboard.press("ArrowRight");
    await page.waitForURL("http://gk.test/cal?**");
    await page.waitForFunction(() => document.activeElement && document.activeElement.getAttribute("data-gk-cal-date") === "2026-12-06");
    check("past the grid: the next month loads, with the kept parameter", /user=7/.test(page.url()) && /month=2026-12/.test(page.url()), page.url());
    check("and the same day has the focus there", (await focused()) === "2026-12-06", await focused());
    await page.keyboard.press("PageUp");
    await page.waitForURL(/month=2026-11/);
    await page.waitForFunction(() => document.activeElement && document.activeElement.getAttribute("data-gk-cal-date") === "2026-11-06");
    check("PageUp: the month before, the same day", (await focused()) === "2026-11-06");
    await page.keyboard.press("Shift+PageDown");
    await page.waitForURL(/month=2027-11/);
    await page.waitForFunction(() => document.activeElement && document.activeElement.getAttribute("data-gk-cal-date") === "2027-11-06");
    check("Shift+PageDown: a year on", (await focused()) === "2027-11-06");
    await page.goto("http://gk.test/");
    await arm();
    // The veto.
    await page.evaluate(() => document.addEventListener("gk:calendarnav", (e) => e.preventDefault()));
    await page.focus(day("2026-11-30"));
    await page.keyboard.press("PageDown");
    await page.waitForTimeout(200);
    ev = await events();
    check("gk:calendarnav: { calendar, month, url, date }, cancelable — the page stays",
      page.url() === "http://gk.test/" && ev.length === 1 && ev[0].calendar === "cal" && ev[0].month === "2026-12" && ev[0].date === "2026-12-30"
      && /month=2026-12/.test(ev[0].url), JSON.stringify(ev) + " " + page.url());
    await page.click(`${C} .gk-cal-next`);
    await page.waitForTimeout(200);
    check("the month links ask too: cancelled, no load", page.url() === "http://gk.test/" && (await events()).some((e) => e.type === "gk:calendarnav" && e.month === "2026-12"));
    const nav = await page.evaluate(() => GK.calendar.go("cal", "2027-02"));
    check("GK.calendar.go() returns false when it was cancelled", nav === false);
    // Enter, Space, the day link.
    await page.goto("http://gk.test/");
    await arm();
    await page.focus(day("2026-11-12"));
    await page.keyboard.press(" ");
    ev = await events();
    check("Space chooses the day: gk:calendarselect with every event of the day",
      ev.length === 1 && ev[0].type === "gk:calendarselect" && ev[0].date === "2026-11-12" && ev[0].event === null
      && ev[0].events.map((x) => x.title).join() === "Winter campaign,Breakfast,Release,Dentist,Review,Yoga", JSON.stringify(ev));
    check("and does not follow the day link", page.url() === "http://gk.test/");
    await page.evaluate(() => document.addEventListener("gk:calendarselect", (e) => { if (e.detail.date === "2026-11-13") e.preventDefault(); }, { once: true }));
    await page.focus(day("2026-11-13"));
    await page.keyboard.press("Enter");
    await page.waitForTimeout(150);
    check("Enter, refused by gk:calendarselect: the day link is not followed", page.url() === "http://gk.test/");
    take();
    await page.focus(day("2026-11-13"));
    await page.keyboard.press("Enter");
    await page.waitForURL("http://gk.test/day**");
    check("Enter follows the day link", /date=2026-11-13/.test(page.url()), page.url());
    // Tab into a day and out of the grid.
    await page.goto("http://gk.test/");
    await arm();
    await page.focus(day(TODAY));
    const tabs = [];
    for (let i = 0; i < 4; i++) {
      await page.keyboard.press("Tab");
      tabs.push(await page.evaluate(() => { const a = document.activeElement; return a.id || (a.textContent || "").replace(/\s+/g, " ").trim().slice(0, 60); }));
    }
    check("Tab from the day: its number link, its events, then out of the grid",
      tabs.join(" | ") === "4Wednesday, November 4, 2026, today | 09:30 Team meeting | 14:00 Call with <Acme> | after", tabs.join(" | "));
    await page.keyboard.press("Shift+Tab");
    await page.keyboard.press("Shift+Tab");
    await page.keyboard.press("Shift+Tab");
    await page.keyboard.press("Shift+Tab");
    check("Shift+Tab back to the day", (await focused()) === TODAY, await focused());
    // An event that is a button.
    await page.click(`${C} [data-gk-cal-event="call"]`);
    ev = await events();
    check("a click on an event without a link: gk:calendarselect with the event",
      ev.length === 1 && ev[0].event && ev[0].event.title === "Call with <Acme>" && ev[0].event.id === "call" && ev[0].date === TODAY, JSON.stringify(ev));
    await page.focus(`${C} [data-gk-cal-event="call"]`);
    await page.keyboard.press("Enter");
    ev = await events();
    check("and Enter on it", ev.length === 1 && ev[0].event && ev[0].event.id === "call", JSON.stringify(ev));
    // A click on a day.
    await page.click(day("2026-11-17"), { position: { x: 60, y: 90 } });
    ev = await events();
    check("a click on the free part of a day chooses it and takes the focus", ev.length === 1 && ev[0].date === "2026-11-17" && (await focused()) === "2026-11-17", JSON.stringify(ev));
    // "+n more".
    const more = (d) => `${day(d)} details.gk-cal-more`;
    await page.click(`${more("2026-11-12")} > summary`);
    const list = await page.$$eval(`${more("2026-11-12")} .gk-cal-more-list .gk-cal-event-title`, (l) => l.map((x) => x.textContent));
    check('"+4 more" opens the whole day in time order', (await page.$eval(more("2026-11-12"), (d) => d.open)) && list.join() === "Winter campaign,Breakfast,Release,Dentist,Review,Yoga", list.join());
    const box = await page.$eval(`${more("2026-11-12")} .gk-cal-more-list`, (el) => { const r = el.getBoundingClientRect(); return { right: r.right, left: r.left, w: innerWidth }; });
    check("the open day stays in the window", box.left >= 0 && box.right <= box.w, JSON.stringify(box));
    await page.click(`${more("2026-11-25")} > summary`);
    // The first one closes on the `toggle` event of the second, which the browser fires a task later:
    // wait for that state instead of reading it in the same instant.
    await page.waitForFunction(() => document.querySelectorAll("details.gk-cal-more[open]").length === 1, null, { timeout: 2000 }).catch(() => {});
    check("one open at a time", !(await page.$eval(more("2026-11-12"), (d) => d.open)) && (await page.$eval(more("2026-11-25"), (d) => d.open)));
    const box2 = await page.$eval(`${more("2026-11-25")} .gk-cal-more-list`, (el) => { const r = el.getBoundingClientRect(); return { right: r.right, left: r.left, w: innerWidth }; });
    check("in a right-hand column it opens towards the start", box2.right <= box2.w && box2.left >= 0, JSON.stringify(box2));
    const over = await page.evaluate((sel) => {
      const d = document.querySelector(sel);
      d.open = false;
      const before = d.closest("tr").getBoundingClientRect().height;
      d.open = true;
      const after = d.closest("tr").getBoundingClientRect().height;
      const list = d.querySelector(".gk-cal-more-list").getBoundingClientRect();
      return { before, after, below: list.bottom > d.closest("td").getBoundingClientRect().bottom };
    }, more("2026-11-25"));
    check("the open day lies over the grid: its week keeps its height", over.before === over.after && over.below, JSON.stringify(over));
    await page.focus(`${more("2026-11-25")} .gk-cal-more-list a`);
    await page.keyboard.press("Escape");
    check("Escape closes it and gives the focus back to it",
      !(await page.$eval(more("2026-11-25"), (d) => d.open)) && (await page.evaluate(() => document.activeElement.tagName)) === "SUMMARY");
    await page.click(`${more("2026-11-25")} > summary`);
    await page.click("#before");
    check("a click elsewhere closes it", !(await page.$eval(more("2026-11-25"), (d) => d.open)));
    // Today, the API.
    await page.focus(day("2026-11-20"));
    await page.click(`${C} .gk-cal-goto-today`);
    await page.waitForTimeout(100);
    check('"Today" in this month moves the focus, loads nothing', page.url() === "http://gk.test/" && (await focused()) === TODAY);
    const capi = await page.evaluate(() => ({
      month: GK.calendar.month("cal"), f1: GK.calendar.focus("cal", "2026-11-20"), active: document.activeElement.getAttribute("data-gk-cal-date"),
      f2: GK.calendar.focus("#gk-cal-cal", "2030-01-01"), ev: GK.calendar.events(document.querySelector("#gk-cal-cal"), "2026-11-09").map((e) => e.id + ":" + e.start + ".." + e.end),
    }));
    check("GK.calendar.month/focus/events", capi.month === "2026-11" && capi.f1 === true && capi.active === "2026-11-20" && capi.f2 === false
      && capi.ev.join() === "fair:2026-11-07..2026-11-10,campaign:2026-11-08..2026-11-18", JSON.stringify(capi));
    // Layout: a bar is straight and joined, its title runs across.
    const bars = await page.evaluate((C) => {
      // The segments in the days, not the copies in a closed "+n more".
      const segs = (id) => [...document.querySelectorAll(C + ' .gk-cal-grid [data-gk-cal-event="' + id + '"]')]
        .filter((s) => !s.closest(".gk-cal-more-list")).map((s) => s.getBoundingClientRect());
      const fair = segs("fair"), camp = segs("campaign");
      const sameTop = (rs) => rs.every((r) => Math.abs(r.top - rs[0].top) < 0.5);
      const joined = (rs) => rs.slice(1).every((r, i) => Math.abs(r.left - rs[i].right) <= 1);
      const head = document.querySelector(C + ' td[data-gk-cal-date="2026-11-08"] [data-gk-cal-event="campaign"]');
      const title = head.querySelector(".gk-cal-event-title").getBoundingClientRect();
      return { fairTop: sameTop(fair.slice(1)), fairJoined: joined(fair.slice(1)), campTop: sameTop(camp.slice(0, 7)), campJoined: joined(camp.slice(0, 7)),
        titleRuns: title.width > head.getBoundingClientRect().width * 3, scroll: document.documentElement.scrollWidth <= innerWidth };
    }, C);
    check("a bar across days is straight (one top) and joined (no gap) in its week, its title runs across",
      bars.fairTop && bars.fairJoined && bars.campTop && bars.campJoined && bars.titleRuns, JSON.stringify(bars));
    check("1280px: nothing scrolls sideways", bars.scroll);
    const grounds = [];
    for (const m of ["light", "dark"]) {
      await page.evaluate((m) => { if (m === "dark") document.body.setAttribute("data-gk-mode", "dark"); else document.body.removeAttribute("data-gk-mode"); }, m);
      // The month's days are transparent on the table's ground; the days around paint their own.
      grounds.push(await page.evaluate((C) => {
        const table = getComputedStyle(document.querySelector(C + " .gk-cal-grid")).backgroundColor;
        const own = getComputedStyle(document.querySelector(C + ' td[data-gk-cal-date="2026-12-02"]')).backgroundColor;
        return [table, /^rgba\(.*,\s*0\)$|^transparent$/.test(own) ? table : own];
      }, C));
    }
    await page.evaluate(() => document.body.removeAttribute("data-gk-mode"));
    check("the days of the months around stand on another ground, light and dark", grounds.every(([a, b]) => a !== b), JSON.stringify(grounds));
    // The accessibility tree.
    const a1 = await ax(page, `${C} .gk-cal-grid`);
    const a2 = await ax(page, day(TODAY));
    const a3 = await ax(page, `${C} [data-gk-cal-event="call"]`);
    const a4 = await ax(page, `${C} .gk-cal-prev`);
    check("Chrome reads a grid named by the label and the month", a1 && a1.role === "grid" && a1.name === "Appointments November 2026", JSON.stringify(a1));
    check("a day: a gridcell, its full date and 'today' first", a2 && a2.role === "gridcell" && a2.name.startsWith("Wednesday, November 4, 2026, today"), JSON.stringify(a2));
    check("an event without a link: a button named by its time and title", a3 && a3.role === "button" && a3.name === "14:00 Call with <Acme>", JSON.stringify(a3));
    check("an arrow: a link named Previous month", a4 && a4.role === "link" && a4.name === "Previous month", JSON.stringify(a4));
    // The narrow column: the agenda.
    const narrow = await page.evaluate(() => {
      const n = document.querySelector('[data-gk-cal="narrow"]');
      const e = document.querySelector('[data-gk-cal="empty"]');
      return { grid: getComputedStyle(n.querySelector(".gk-cal-grid")).display, agenda: getComputedStyle(n.querySelector(".gk-cal-agenda")).display,
        days: [...n.querySelectorAll(".gk-cal-agenda-day time")].map((t) => t.getAttribute("datetime")),
        wide: getComputedStyle(document.querySelector('[data-gk-cal="cal"] .gk-cal-agenda')).display,
        empty: e.querySelector(".gk-cal-empty") && getComputedStyle(e.querySelector(".gk-cal-empty")).display };
    });
    check("a calendar in a 380px column shows the agenda, not the grid; a wide one the grid",
      narrow.grid === "none" && narrow.agenda === "block" && narrow.wide === "none" && narrow.days.join() === "2026-11-04,2026-11-07,2026-11-08,2026-11-09" && narrow.empty === "block", JSON.stringify(narrow));
    await events();
    const agendaButton = '[data-gk-cal="narrow"] .gk-cal-agenda-day:nth-child(3) .gk-cal-event[role="button"]';
    check("in the agenda an event without a link is a button in the tab order", (await page.$eval(agendaButton, (b) => b.tabIndex)) === 0);
    await page.click(agendaButton);
    await page.focus(agendaButton);
    await page.keyboard.press(" ");
    ev = await events();
    check("and chooses that event, on that day, with a click and with Space",
      ev.length === 2 && ev.every((x) => x.calendar === "narrow" && x.date === "2026-11-08" && x.event && x.event.title === "Trade fair"), JSON.stringify(ev));
    if (SHOTS) {
      await (await page.$("#s-cal")).screenshot({ path: path.join(SHOTS, "dates-calendar-1280.png") });
      await (await page.$("#s-filter")).screenshot({ path: path.join(SHOTS, "dates-filter-1280.png") });
    }
    check("no script error on the calendar", errors.length === 0, errors.join("; "));

    // ════ C. Contrast: plain and six themes × light, dark, .gk-dark ══════════
    await page.click(`${more("2026-11-12")} > summary`);
    const measure = () => page.evaluate(new Function(`${PIXEL}
      const q = (s) => document.querySelector(s);
      const C = '[data-gk-cal="cal"] ';
      const token = (el, name) => { const p = document.createElement("span"); p.style.color = "var(" + name + ")"; el.appendChild(p);
        const c = getComputedStyle(p).color; p.remove(); return px(c); };
      const disc = q(C + 'td.gk-cal-today .gk-cal-daynum time > [aria-hidden]');
      const out = {
        rangeText: textOn(q("#gk-daterange-plain .gk-daterange-text")),
        rangeSep: textOn(q("#gk-daterange-plain .gk-daterange-sep")),
        rangeSelect: textOn(q("#gk-daterange-plain-preset")),
        rangeDate: textOn(q("#gk-daterange-plain-from")),
        title: textOn(q(C + ".gk-cal-title")),
        weekday: textOn(q(C + "thead th.gk-cal-wd span")),
        weekNo: textOn(q(C + "tbody th.gk-cal-wk span")),
        dayNum: textOn(q(C + 'td[data-gk-cal-date="2026-11-05"] .gk-cal-daynum [aria-hidden]')),
        outside: textOn(q(C + 'td[data-gk-cal-date="2026-12-02"] .gk-cal-daynum [aria-hidden]')),
        today: textOn(disc),
        blue: textOn(q(C + '[data-gk-cal-event="m1"] .gk-cal-event-title')),
        green: textOn(q(C + '[data-gk-cal-event="call"] .gk-cal-event-title')),
        red: textOn(q(C + 'td[data-gk-cal-date="2026-11-06"] .gk-label-red .gk-cal-event-title')),
        orange: textOn(q(C + 'td[data-gk-cal-date="2026-11-07"] [data-gk-cal-event="fair"] .gk-cal-event-title')),
        gray: textOn(q(C + 'td[data-gk-cal-date="2026-11-20"] .gk-label-gray .gk-cal-event-title')),
        accent: textOn(q(C + 'td[data-gk-cal-date="2026-11-27"] .gk-cal-accent .gk-cal-event-title')),
        time: textOn(q(C + '[data-gk-cal-event="m1"] .gk-cal-time')),
        more: textOn(q(C + 'td[data-gk-cal-date="2026-11-25"] .gk-cal-more > summary')),
        openList: textOn(q(C + 'td[data-gk-cal-date="2026-11-12"] .gk-cal-more-list .gk-cal-more-date')),
        agendaDate: textOn(q('[data-gk-cal="narrow"] .gk-cal-agenda-day:not(.gk-cal-today) .gk-cal-agenda-date time')),
        agendaToday: textOn(q('[data-gk-cal="narrow"] .gk-cal-agenda-day.gk-cal-today .gk-cal-agenda-date time')),
        todayMark: textOn(q('[data-gk-cal="narrow"] .gk-cal-today-mark')),
        empty: textOn(q('[data-gk-cal="empty"] .gk-cal-empty')),
        disc: ratio(px(getComputedStyle(disc).backgroundColor, ground(disc.closest("td"))), ground(disc.closest("td"))),
        ring: ratio(token(q(C + 'td[data-gk-cal-date="2026-11-05"]'), "--gk-primary"), ground(q(C + 'td[data-gk-cal-date="2026-11-05"]'))),
      };
      return out;`));
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
          const need = ["disc", "ring"].includes(k) ? 3 : 4.5;
          if (!(v >= need)) low.push(`${theme || "plain"}/${mode} ${k} ${v.toFixed(2)}`);
        }
        if (SHOTS && !theme && mode !== "dark-class") await (await page.$("#s-cal")).screenshot({ path: path.join(SHOTS, `dates-calendar-${mode}.png`) });
      }
    }
    check("contrast in 21 looks: every text 4.5:1 (events of all six colours, outside days, the open day, the agenda), the today disc and the focus ring 3:1",
      low.length === 0, low.join("; "));
    if (process.env.GK_VERBOSE) console.error("        lowest: " + Object.entries(lowest).map(([k, v]) => k + " " + v.toFixed(2)).join(", "));
    await context.close();

    // ════ D. A phone, 390px ═══════════════════════════════════════════════════
    ({ context, page, errors } = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }));
    const phone = await page.evaluate(() => {
      const inView = (sel) => [...document.querySelectorAll(sel)].every((el) => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 0.5; });
      const cal = document.querySelector('[data-gk-cal="cal"]');
      return {
        scroll: document.documentElement.scrollWidth <= innerWidth,
        grid: getComputedStyle(cal.querySelector(".gk-cal-grid")).display,
        agenda: getComputedStyle(cal.querySelector(".gk-cal-agenda")).display,
        fields: inView(".gk-daterange select, .gk-daterange input[type=date], .gk-daterange-apply"),
        font: [...document.querySelectorAll(".gk-daterange input[type=date], .gk-daterange select")].every((e) => parseFloat(getComputedStyle(e).fontSize) >= 16),
        events: inView('[data-gk-cal="cal"] .gk-cal-agenda .gk-cal-event'),
        days: cal.querySelectorAll(".gk-cal-agenda-day").length,
      };
    });
    check("390px: the agenda instead of the grid, nothing sideways, every field and event in view, 16px fields",
      phone.scroll && phone.grid === "none" && phone.agenda === "block" && phone.fields && phone.font && phone.events && phone.days === 17, JSON.stringify(phone));
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, "dates-390.png"), fullPage: true });
    check("no script error on a phone", errors.length === 0, errors.join("; "));
    await context.close();

    // ════ E. Without script ══════════════════════════════════════════════════
    ({ context, page, errors } = await open({ javaScriptEnabled: false }));
    const nojs = await page.$$eval(".gk-daterange", (l) => l.map((d) => d.hasAttribute("data-gk-daterange-ready")));
    check("without script nothing is set up", nojs.every((x) => !x));
    take();
    await page.selectOption("#gk-daterange-period-preset", "last-month");
    await page.waitForTimeout(150);
    check("without script a preset is not sent by itself", lastTo("/list") === null);
    await page.click("#gk-daterange-period .gk-daterange-apply");
    await page.waitForURL("http://gk.test/list**");
    r = lastTo("/list");
    check("without script: the select changed, the form carries shown, the server reads the preset",
      r && /period%5Bshown%5D=2026-11-01%2F2026-11-30/.test(r.query) && JSON.stringify(read("period", r.query)) === JSON.stringify({ from: "2026-10-01", to: "2026-10-31", preset: "last-month" }),
      r && r.query + " → " + JSON.stringify(read("period", r.query)));
    await page.goto("http://gk.test/");
    await page.fill("#gk-daterange-period-from", "2026-02-02");
    await page.fill("#gk-daterange-period-to", "2026-02-09");
    await page.click("#gk-daterange-period .gk-daterange-apply");
    await page.waitForURL("http://gk.test/list**");
    r = lastTo("/list");
    check("without script: the dates typed over, the select left — the server reads the dates",
      r && JSON.stringify(read("period", r.query)) === JSON.stringify({ from: "2026-02-02", to: "2026-02-09", preset: "custom" }), r && r.query);
    await page.goto("http://gk.test/");
    const plainNoJs = await page.evaluate(() => ({
      role: document.querySelector('[data-gk-cal="cal"] .gk-cal-grid').getAttribute("role"),
      tabbable: [...document.querySelectorAll('[data-gk-cal="cal"] td')].filter((t) => t.hasAttribute("tabindex")).length,
      text: document.querySelector("#gk-daterange-plain .gk-daterange-text").textContent,
    }));
    check("without script: a table of links (no grid role, no tabindex), the words the server wrote", plainNoJs.role === null && plainNoJs.tabbable === 0 && plainNoJs.text === "Nov 1 – 30, 2026", JSON.stringify(plainNoJs));
    await page.click('[data-gk-cal="cal"] td[data-gk-cal-date="2026-11-12"] details > summary');
    check('without script "+4 more" opens', await page.$eval('[data-gk-cal="cal"] td[data-gk-cal-date="2026-11-12"] details', (d) => d.open && getComputedStyle(d.querySelector(".gk-cal-more-list")).display !== "none"));
    await page.click('[data-gk-cal="cal"] .gk-cal-next');
    await page.waitForURL(/month=2026-12/);
    check("without script the month links load the month", (await page.$eval('[data-gk-cal="cal"]', (c) => c.getAttribute("data-gk-cal-month"))) === "2026-12" && /user=7/.test(page.url()), page.url());
    await context.close();
  } catch (e) {
    check("the run finished", false, e && e.stack ? e.stack.split("\n").slice(0, 4).join(" | ") : String(e));
  } finally {
    await browser.close();
  }

  const failed = results.filter((x) => !x.ok);
  for (const x of results) console.log((x.ok ? "  ok   " : "  FAIL ") + x.name + (x.ok || !x.detail ? "" : "\n         " + x.detail));
  console.log(`\n${results.length - failed.length} of ${results.length} cases hold.`);
  process.exit(failed.length ? 1 : 0);
})();
