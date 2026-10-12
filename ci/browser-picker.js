#!/usr/bin/env node
/**
 * The image picker in a real browser (1.94.0).
 *
 * `php tests/run.php` executes no JavaScript, so everything the picker DOES —
 * folding rows, the roving tab stop, picking from the enlarged view, where the
 * focus goes — is held here, on real component markup (ci/browser-fixture.php
 * --picker: four pickers of 1, 3, 12 and 30 images, and a plain gallery).
 *
 *     node ci/browser-picker.js
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-picker.js
 *     GK_SHOTS=/some/dir node ci/browser-picker.js      # also saves pictures to look at
 *
 * Like ci/browser.js it stays outside the suite: GridKit has no dependencies.
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
const SHOTS = process.env.GK_SHOTS || "";
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-picker-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));
const fixture = path.join(dir, "picker.html");
fs.writeFileSync(fixture, execFileSync(php, [path.join(__dirname, "browser-fixture.php"), "--picker"], { maxBuffer: 64 * 1024 * 1024 }));

const results = [];
const pageErrors = [];
const check = (name, ok, why) => { results.push({ name, ok: !!ok, why: ok ? "" : why === undefined ? "" : typeof why === "string" ? why : JSON.stringify(why) }); };
const shot = async (page, name) => { if (SHOTS) { fs.mkdirSync(SHOTS, { recursive: true }); await page.screenshot({ path: path.join(SHOTS, name + ".png") }); } };

/** What the page says about one picker: tiles, folded ones, rows, checked. */
const stand = (page, sel) => page.evaluate((s) => {
  const c = document.querySelector(s);
  const items = [...c.querySelectorAll(".gk-picker-item")];
  const sichtbar = items.filter((i) => i.offsetParent !== null);   // by what is laid out, not by the attribute: a [hidden] that CSS overrides must show up here
  const reihen = [...new Set(sichtbar.map((i) => i.offsetTop))].length;
  const nachReihe = {};
  sichtbar.forEach((i) => { nachReihe[i.offsetTop] = (nachReihe[i.offsetTop] || 0) + 1; });
  const knopf = c.querySelector("[data-gk-picker-more-btn]");
  const gewaehlt = [...c.querySelectorAll(".gk-picker-input:checked")].map((r) => r.value);
  return {
    gesamt: items.length, sichtbar: sichtbar.length, reihen, proReihe: Object.values(nachReihe)[0] || 0,
    knopf: knopf && !knopf.hidden ? { text: knopf.textContent.trim(), offen: knopf.getAttribute("aria-expanded") } : null,
    gewaehlt, status: (c.querySelector("[data-gk-picker-status]") || {}).textContent || null,
    breite: c.getBoundingClientRect().width, scrollBreite: document.documentElement.scrollWidth, fensterBreite: document.documentElement.clientWidth,
  };
}, sel);

const lb = (page) => page.evaluate(() => {
  const l = document.querySelector(".gk-lightbox");
  const offen = !!l && l.classList.contains("open");
  const q = (s) => (l ? l.querySelector(s) : null);
  const a = document.activeElement;
  const sichtbar = (e) => !!e && e.offsetParent !== null && getComputedStyle(e).visibility !== "hidden";
  const choose = q(".gk-lightbox-choose");
  return {
    offen, src: offen ? q(".gk-lightbox-img").getAttribute("src") : null, alt: offen ? q(".gk-lightbox-img").alt : null,
    caption: offen ? q(".gk-lightbox-caption").textContent : null, meta: q(".gk-lightbox-meta") ? q(".gk-lightbox-meta").textContent : null,
    zaehler: offen ? q(".gk-lightbox-counter").textContent : null,
    wahl: choose && sichtbar(choose) ? { text: choose.textContent.trim(), gedrueckt: choose.getAttribute("aria-pressed"), gesperrt: choose.disabled } : null,
    dismiss: sichtbar(q(".gk-lightbox-dismiss")),
    fokusIn: offen && l.contains(a), fokus: a ? (a.id || a.className || a.tagName) : null, body: document.body.style.overflow,
  };
});

(async () => {
  const browser = await chromium.launch();
  try {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    page.setDefaultTimeout(6000);
    page.on("pageerror", (e) => pageErrors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error" && !/Failed to load resource/.test(m.text())) pageErrors.push(m.text()); });
    await page.goto("file://" + fixture);
    await page.evaluate(() => { window.__aenderungen = []; document.addEventListener("change", (e) => { if (e.target.classList && e.target.classList.contains("gk-picker-input")) window.__aenderungen.push(e.target.value); }); });

    // ── P01 the shape of the grid at 1, 3, 12 and 30 images ────────────────
    const s1 = await stand(page, "#p1"), s3 = await stand(page, "#p3"), s12 = await stand(page, "#p12"), s30 = await stand(page, "#p30");
    check("P01 one image: one tile, nothing to fold", s1.gesamt === 1 && s1.sichtbar === 1 && s1.knopf === null, s1);
    check("P01 three images: three tiles in one row, nothing to fold", s3.sichtbar === 3 && s3.reihen === 1 && s3.knopf === null, s3);
    check("P01 twelve images at 1440 px: at least four tiles a row, two rows shown, a button for the rest",
      s12.proReihe >= 4 && s12.reihen === 2 && s12.sichtbar < 12 && s12.knopf && /^Show all 12$/.test(s12.knopf.text) && s12.knopf.offen === "false", s12);
    check("P01 thirty images: two rows shown (the checked 21st stays visible too), the button names the count and the words the caller gave",
      s30.knopf && s30.knopf.text === "Show all 30 images" && s30.reihen >= 2 && s30.sichtbar <= 2 * s30.proReihe + 1, s30);
    await shot(page, "p-desktop-1440");

    // ── P02 show all, and back ───────────────────────────────────────────
    await page.click("#p12 [data-gk-picker-more-btn]");
    const alle = await stand(page, "#p12");
    check("P02 \"Show all\": every tile is there, the button says how to fold, aria-expanded true",
      alle.sichtbar === 12 && alle.knopf && alle.knopf.text === "Show fewer" && alle.knopf.offen === "true", alle);
    await shot(page, "p-desktop-1440-all12");
    await page.click("#p12 [data-gk-picker-more-btn]");
    const wieder = await stand(page, "#p12");
    check("P02 and back: folded again, the button says \"Show all 12\"", wieder.sichtbar === s12.sichtbar && wieder.knopf.text === "Show all 12" && wieder.knopf.offen === "false", wieder);

    // ── P03 the checked tile beyond the fold stays in sight ──────────────
    const dort = await page.evaluate(() => { const r = document.querySelector('#p30 .gk-picker-input[value="img-21.webp"]'); const i = r.closest(".gk-picker-item"); return { checked: r.checked, hidden: i.hidden, sichtbar: i.offsetParent !== null }; });
    check("P03 the checked image (21 of 30) is not folded away", dort.checked && !dort.hidden && dort.sichtbar, dort);
    const dortOffen = await page.evaluate(() => { const i = document.querySelector('#p30 .gk-picker-input[value="img-22.webp"]').closest(".gk-picker-item"); return i.hidden; });
    check("P03 the image next to it is folded, as the fold says", dortOffen === true);

    // ── P04 picking with the mouse ───────────────────────────────────────
    await page.evaluate(() => { window.__aenderungen.length = 0; });
    await page.click("#p12 .gk-picker-item:nth-child(3) .gk-picker-media");
    await page.waitForTimeout(300);   // the ring eases in over 120 ms: measure it when it has arrived
    const nachKlick = await page.evaluate(() => {
      const r = [...document.querySelectorAll("#p12 .gk-picker-input")];
      const g = r.filter((x) => x.checked);
      const check = g[0].closest(".gk-picker-item").querySelector(".gk-picker-check");
      const ring = (x) => getComputedStyle(x).boxShadow;
      const andere = r.find((x) => !x.checked);
      return { gewaehlt: g.map((x) => x.value), geaendert: window.__aenderungen.slice(), sichtbarHaken: getComputedStyle(check).display !== "none",
        ringGewaehlt: ring(g[0]), ringAndere: ring(andere), hakenAnderer: getComputedStyle(andere.closest(".gk-picker-item").querySelector(".gk-picker-check")).display };
    });
    check("P04 a click on a tile picks it (one radio checked, one change event for the page)", JSON.stringify(nachKlick.gewaehlt) === '["img-3.webp"]' && JSON.stringify(nachKlick.geaendert) === '["img-3.webp"]', nachKlick);
    check("P04 the picked tile shows a check mark and a thicker ring; the others have neither — a shape, not only a colour",
      nachKlick.sichtbarHaken && nachKlick.hakenAnderer === "none" && /3px/.test(nachKlick.ringGewaehlt) && !/3px/.test(nachKlick.ringAndere), nachKlick);
    const status = await page.evaluate(() => document.querySelector("#p12 [data-gk-picker-status]").textContent.trim());
    check("P04 the status line follows: \"Selected: Image 3\"", status === "Selected: Image 3", status);
    await page.click("#p12 .gk-picker-item:nth-child(1) label");
    check("P04 a click on the title picks too", (await stand(page, "#p12")).gewaehlt[0] === "img-1.webp");
    await page.click("#p12 .gk-picker-item:nth-child(7) .gk-picker-media", { force: true });
    check("P04 a disabled tile cannot be picked", (await stand(page, "#p12")).gewaehlt[0] === "img-1.webp");

    // ── P05 the keyboard: one tab stop into the group, arrows move, buttons follow ──
    await page.focus("#b12");
    await page.keyboard.press("Tab");
    const t1 = await page.evaluate(() => ({ el: document.activeElement.className, wert: document.activeElement.value, ring: getComputedStyle(document.activeElement).outlineWidth, stil: getComputedStyle(document.activeElement).outlineStyle, vis: document.activeElement.matches(":focus-visible") }));
    check("P05 Tab from before the group lands on the checked radio, and the ring is 3 px", t1.el.includes("gk-picker-input") && t1.wert === "img-1.webp" && t1.vis && t1.ring === "3px" && t1.stil !== "none", t1);
    await page.keyboard.press("ArrowRight");
    const t2 = await page.evaluate(() => ({ wert: document.activeElement.value, gewaehlt: [...document.querySelectorAll("#p12 .gk-picker-input:checked")].map((r) => r.value) }));
    check("P05 ArrowRight moves to the next tile and picks it (the native radio group)", t2.wert === "img-2.webp" && t2.gewaehlt[0] === "img-2.webp", t2);
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    const t3 = await page.evaluate(() => document.activeElement.value);
    check("P05 the arrows skip the disabled tile (7) and land on 8", t3 === "img-8.webp", t3);
    const stops = await page.evaluate(() => {
      const zoom = [...document.querySelectorAll("#p12 .gk-picker-zoom")].filter((b) => b.tabIndex === 0).map((b) => b.closest(".gk-picker-item").querySelector("input").value);
      const acts = [...document.querySelectorAll("#p12 .gk-picker-action")];
      return { zoom, aktionen: acts.length, aktionenImTab: acts.filter((b) => b.tabIndex === 0).length };
    });
    check("P05 only the tile that has the focus has its magnifier in the tab order (twelve tiles are not twelve extra tab stops) …", JSON.stringify(stops.zoom) === '["img-8.webp"]', stops);
    check("P05 … but EVERY remove button stays in it: with native radios the focused tile is the picked one, which an application will not let you remove — rationing them would leave the others out of reach", stops.aktionen === 4 && stops.aktionenImTab === 4, stops);
    await page.keyboard.press("Tab");
    const t4 = await page.evaluate(() => ({ cls: document.activeElement.className, aria: document.activeElement.getAttribute("aria-label") }));
    check("P05 Tab from the radio reaches its zoom button, which says what it enlarges", /gk-picker-zoom/.test(t4.cls) && t4.aria === "Enlarge: Image 8", t4);
    await page.keyboard.press("Tab");
    check("P05 then the remove button of the next tile that has one (9; tile 12 is folded away and takes no stop)", (await page.evaluate(() => document.activeElement.getAttribute("aria-label"))) === "Remove: Image 9");
    await page.keyboard.press("Tab");
    check("P05 then the fold button", /gk-picker-more/.test(await page.evaluate(() => document.activeElement.className)));
    await page.keyboard.press("Tab");
    check("P05 and on to what follows the group", (await page.evaluate(() => document.activeElement.id)) === "a12");
    for (let i = 0; i < 4; i++) await page.keyboard.press("Shift+Tab");
    check("P05 Shift+Tab walks back to the radio that is picked", (await page.evaluate(() => document.activeElement.value)) === "img-8.webp");
    await page.keyboard.press("Shift+Tab");
    check("P05 and one more reaches the remove button of a tile BEFORE it (6) — a tile that is not the picked one can be removed from the keyboard", (await page.evaluate(() => document.activeElement.getAttribute("aria-label"))) === "Remove: Image 6");
    await page.focus('#p12 input[value="img-8.webp"]');

    // ── P06 the viewer: open, content, trap, page, close, focus back ──────
    await page.keyboard.press("Tab");   // the zoom button of tile 8
    await page.keyboard.press("Enter");
    const v1 = await lb(page);
    check("P06 Enter on the zoom button opens the viewer on that image, large (not the thumbnail)", v1.offen && v1.src.includes("base64") && v1.zaehler === "7 / 11" && v1.caption === "Image 8", v1);
    const grossThumb = await page.evaluate(() => { const t = document.querySelector('#p12 [data-lightbox][data-caption="Image 8"]'); return { gross: t.dataset.lightbox === document.querySelector(".gk-lightbox-img").getAttribute("src"), klein: t.querySelector("img").getAttribute("src") !== document.querySelector(".gk-lightbox-img").getAttribute("src") }; });
    check("P06 the viewer loads the tile's large version, which is not the one on the tile", grossThumb.gross && grossThumb.klein, grossThumb);
    check("P06 under the picture: the title as caption, the origin as a second line", v1.caption === "Image 8" && v1.meta === "AI generated", v1);
    check("P06 the image has an alt (the caption)", v1.alt === "Image 8", v1);
    check("P06 the focus is inside the viewer and the page behind cannot scroll", v1.fokusIn && v1.body === "hidden", v1);
    check("P06 the viewer offers the pick button (this one is already picked, the arrows picked it: \"Selected\", pressed) and a visible \"Close\" next to it", v1.wahl && v1.wahl.text === "Selected" && v1.wahl.gedrueckt === "true" && v1.dismiss, v1);
    await page.waitForTimeout(350);   // the viewer fades in over 200 ms
    await shot(page, "p-viewer-1440");
    let drin = true;
    for (let i = 0; i < 14; i++) { await page.keyboard.press("Tab"); if (!(await lb(page)).fokusIn) { drin = false; break; } }
    check("P06 Tab never leaves the viewer (14 presses)", drin);
    drin = true;
    for (let i = 0; i < 14; i++) { await page.keyboard.press("Shift+Tab"); if (!(await lb(page)).fokusIn) { drin = false; break; } }
    check("P06 Shift+Tab never leaves it either", drin);
    await page.keyboard.press("ArrowRight");
    const v2 = await lb(page);
    check("P06 ArrowRight shows the next image: counter, caption and the large version change", v2.zaehler === "8 / 11" && v2.caption === "Image 9" && v2.src !== v1.src, v2);
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press("ArrowLeft");
    const v3 = await lb(page);
    check("P06 ArrowLeft pages back, through the disabled image too (it has a large version: it can be looked at, not picked)", v3.caption === "Image 7" && v3.zaehler === "6 / 11", v3);
    await page.keyboard.press("Escape");
    const v4 = await lb(page);
    check("P06 Escape closes the viewer and gives the page its scroll back", !v4.offen && v4.body === "", v4);
    const fokusNach = await page.evaluate(() => ({ aria: document.activeElement.getAttribute("aria-label"), cls: document.activeElement.className }));
    check("P06 the focus goes to the zoom button of the image that was last shown (a place the person knows), not to the top of the page",
      /gk-picker-zoom/.test(fokusNach.cls) && fokusNach.aria === "Enlarge: Image 7", fokusNach);

    // A small picture in a picker's viewer fills the stage (the plain gallery keeps showing natural size).
    await page.evaluate(() => {
      const kleiner = "data:image/svg+xml;base64," + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="112"><rect width="100%" height="100%" fill="teal"/></svg>');
      window.__klein = { src: kleiner, caption: "Tiny", choose() { return null; } };
      GK.lightbox.show([window.__klein], 0, document.getElementById("before"));
    });
    await page.waitForTimeout(350);
    const gross = await page.evaluate(() => { const i = document.querySelector(".gk-lightbox-img").getBoundingClientRect(); const st = document.querySelector(".gk-lightbox-stage").getBoundingClientRect(); return { b: Math.round(i.width), stage: Math.round(st.width), h: Math.round(i.height), stageH: Math.round(st.height) }; });
    check("P06 in a picker's viewer a 200 px picture is enlarged to the stage (width or height fills it), not shown as a stamp", gross.b > 600 && (gross.b >= gross.stage - 2 || gross.h >= gross.stageH - 2), gross);
    await page.keyboard.press("Escape");
    await page.evaluate(() => GK.lightbox.show([{ src: window.__klein.src, caption: "Plain" }], 0, document.getElementById("before")));
    await page.waitForTimeout(350);
    const natuerlich = await page.evaluate(() => Math.round(document.querySelector(".gk-lightbox-img").getBoundingClientRect().width));
    check("P06 a viewer without \"select\" (a plain gallery) still shows natural size: 200 px", natuerlich <= 210, natuerlich);
    await page.keyboard.press("Escape");

    // ── P07 opening with the mouse, picking from the viewer ──────────────
    await page.evaluate(() => { window.__aenderungen.length = 0; });
    await page.click('#p12 [data-lightbox][data-caption="Image 9"] .gk-picker-zoom');
    const w1 = await lb(page);
    check("P07 a click on the zoom button opens it on that image", w1.offen && w1.caption === "Image 9", w1);
    await page.click(".gk-lightbox-choose");
    const w2 = await lb(page);
    const w3 = await page.evaluate(() => ({ gewaehlt: [...document.querySelectorAll("#p12 .gk-picker-input:checked")].map((r) => r.value), geaendert: window.__aenderungen.slice(), fokus: document.activeElement.value || document.activeElement.className, status: document.querySelector("#p12 [data-gk-picker-status]").textContent.trim() }));
    check("P07 \"Select this image\" picks it: one change event, viewer closed, the status line follows", !w2.offen && w3.gewaehlt[0] === "img-9.webp" && JSON.stringify(w3.geaendert) === '["img-9.webp"]' && w3.status === "Selected: Image 9", [w2, w3]);
    check("P07 and the focus lands on the radio that was picked", w3.fokus === "img-9.webp", w3);
    await page.click('#p12 [data-lightbox][data-caption="Image 9"] .gk-picker-zoom');
    const w4 = await lb(page);
    check("P07 on the image that is already picked the button says so (\"Selected\", pressed) and picks nothing again", w4.wahl && w4.wahl.text === "Selected" && w4.wahl.gedrueckt === "true", w4);
    await page.click(".gk-lightbox-dismiss");
    const w5 = await lb(page);
    check("P07 the visible \"Close\" button closes it, the focus goes back to the zoom button", !w5.offen && /gk-picker-zoom/.test(await page.evaluate(() => document.activeElement.className)), w5);
    await page.click('#p12 [data-lightbox][data-caption="Image 9"] .gk-picker-zoom');
    await page.click(".gk-lightbox", { position: { x: 10, y: 400 } });
    check("P07 a click on the dark area closes it too", !(await lb(page)).offen);

    // ── P08 the viewer pages through ALL images, folded ones included; the focus never falls to the page top ──
    await page.click('#p12 [data-lightbox][data-caption="Image 9"] .gk-picker-zoom');
    for (let i = 0; i < 2; i++) await page.keyboard.press("ArrowRight");
    const f1 = await lb(page);
    await page.keyboard.press("Escape");
    const f2 = await page.evaluate(() => ({ tag: document.activeElement.tagName, cls: document.activeElement.className, innen: !!document.activeElement.closest("#p12") }));
    check("P08 paging to an image that is folded away and closing leaves the focus in the picker (on its button), not on the page", f1.caption === "Image 11" && f2.innen && f2.tag !== "BODY", [f1, f2]);

    // ── P09 an image without a large version, and a disabled one ─────────
    await page.click("#p12 [data-gk-picker-more-btn]");
    const ohne = await page.evaluate(() => { const i = document.querySelector('#p12 input[value="img-6.webp"]').closest(".gk-picker-item"); return { zoom: !!i.querySelector(".gk-picker-zoom"), ph: !!i.querySelector(".gk-picker-ph"), img: !!i.querySelector("img"), phSichtbar: i.querySelector(".gk-picker-ph").offsetHeight > 20, note: (i.querySelector(".gk-picker-note") || {}).textContent }; });
    check("P09 an image with no preview: a drawn placeholder, a note, no zoom button", ohne.ph && !ohne.img && !ohne.zoom && ohne.phSichtbar && ohne.note === "No preview", ohne);
    const gesperrt = await page.evaluate(() => { const i = document.querySelector('#p12 input[value="img-7.webp"]').closest(".gk-picker-item"); const r = i.querySelector("input"); return { disabled: r.disabled, deckkraft: parseFloat(getComputedStyle(i.querySelector(".gk-picker-img")).opacity), note: i.querySelector(".gk-picker-note").textContent }; });
    check("P09 a disabled tile is dimmed and says why", gesperrt.disabled && gesperrt.deckkraft < 0.7 && gesperrt.note === "Not released yet", gesperrt);
    await page.click('#p12 [data-lightbox][data-caption="Image 5"] .gk-picker-zoom');
    await page.keyboard.press("ArrowRight");
    const g1 = await lb(page);
    check("P09 the viewer skips the image that has no large version: from 5 the next is 7 (6 has none), and 7 — disabled — can be looked at but not picked: its button is disabled", g1.caption === "Image 7" && g1.wahl && g1.wahl.gesperrt === true, g1);
    await page.click(".gk-lightbox-choose", { force: true }).catch(() => null);
    check("P09 … and a click on it picks nothing (the radio of 7 stays unchecked, the viewer stays open)", (await page.evaluate(() => document.querySelector('#p12 input[value="img-7.webp"]').checked)) === false && (await lb(page)).offen);
    await page.keyboard.press("Escape");
    await page.click("#p12 [data-gk-picker-more-btn]");   // fold again for the next cases

    // ── P10 an action: an event, never an effect ─────────────────────────
    const ev = await page.evaluate(() => new Promise((resolve) => {
      const c = document.getElementById("p12");
      const seen = [];
      c.addEventListener("gk:pickeraction", (e) => seen.push({ action: e.detail.action, value: e.detail.value, tile: e.detail.item === e.target.closest(".gk-picker-item") || e.detail.item.classList.contains("gk-picker-item"), bubbles: e.bubbles }));
      document.querySelector('#p12 input[value="img-3.webp"]').closest(".gk-picker-item").querySelector("[data-gk-picker-action]").click();
      setTimeout(() => resolve({ seen, tiles: c.querySelectorAll(".gk-picker-item").length }), 50);
    }));
    check("P10 an action fires gk:pickeraction with the action, the value and the tile; the library removes nothing itself",
      ev.seen.length === 1 && ev.seen[0].action === "remove" && ev.seen[0].value === "img-3.webp" && ev.seen[0].tile && ev.seen[0].bubbles && ev.tiles === 12, ev);
    await page.evaluate(() => GK.picker.remove(document.getElementById("p12"), "img-3.webp"));
    const nachWeg = await stand(page, "#p12");
    check("P10 GK.picker.remove(container, value) takes the tile out and the count follows", nachWeg.gesamt === 11 && nachWeg.knopf.text === "Show all 11", nachWeg);

    // ── P11 adding a tile from script: the same markup as PHP, picked, focused ──
    const neu = await page.evaluate(() => {
      window.__aenderungen.length = 0;
      const c = document.getElementById("p12");
      const el = GK.picker.add(c, { value: "img-99.webp", title: "Fresh upload", thumb: document.querySelector("#p12 img").src, full: document.querySelector("#p12 img").src, meta: "Own image",
        actions: [{ name: "remove", label: "Remove" }] }, { select: true, focus: true });
      const r = el.querySelector("input");
      return { checked: r.checked, fokus: document.activeElement === r, geaendert: window.__aenderungen.slice(), sichtbar: el.offsetParent !== null, status: c.querySelector("[data-gk-picker-status]").textContent.trim(),
        gruppe: r.name, id: r.id, labelFor: el.querySelector("label").htmlFor === r.id };
    });
    check("P11 GK.picker.add(…, {select, focus}) appends a tile that is picked, focused and in the group; the page hears one change",
      neu.checked && neu.fokus && JSON.stringify(neu.geaendert) === '["img-99.webp"]' && neu.sichtbar && neu.gruppe === "twelve" && neu.status === "Selected: Fresh upload" && neu.labelFor, neu);
    const gleich = await page.evaluate(() => {
      const norm = (el) => el.outerHTML.replace(/\sid="[^"]*"/g, "").replace(/\sfor="[^"]*"/g, "").replace(/\saria-(labelledby|describedby)="[^"]*"/g, "").replace(/\sdata-gk-[a-z-]*seq="[^"]*"/g, "").replace(/\s(checked|hidden|tabindex)(="[^"]*")?/g, "").replace(/\sstyle=""/g, "").replace(/\s+/g, " ");
      const php = document.querySelector('#p12 input[value="img-4.webp"]').closest(".gk-picker-item");
      const c = document.getElementById("p12");
      const el = GK.picker.add(c, { value: "img-4.webp", title: "Image 4", thumb: php.querySelector("img").getAttribute("src"), full: php.dataset.lightbox, meta: "AI generated", badge: "AI" }, {});
      const a = norm(php), b = norm(el);
      el.remove();
      let i = 0; while (i < a.length && a[i] === b[i]) i++;
      return { gleich: a === b, ab: i, php: a.slice(Math.max(0, i - 60), i + 80), skript: b.slice(Math.max(0, i - 60), i + 80) };
    });
    check("P11 a tile the script builds has the markup PHP writes (compared without ids)", gleich.gleich, gleich);

    // ── P12 two pickers on one page stay apart ───────────────────────────
    await page.click("#p3 .gk-picker-item:nth-child(1) .gk-picker-media");
    const zwei = await page.evaluate(() => ({ drei: [...document.querySelectorAll("#p3 .gk-picker-input:checked")].map((r) => r.value), zwoelf: [...document.querySelectorAll("#p12 .gk-picker-input:checked")].map((r) => r.value), dreissig: [...document.querySelectorAll("#p30 .gk-picker-input:checked")].map((r) => r.value) }));
    check("P12 picking in one picker leaves the others alone", zwei.drei[0] === "img-1.webp" && zwei.zwoelf[0] === "img-99.webp" && zwei.dreissig[0] === "img-21.webp", zwei);
    const ids = await page.evaluate(() => { const a = [...document.querySelectorAll("[id]")].map((e) => e.id); return a.length - new Set(a).size; });
    check("P12 no id is used twice on the page", ids === 0, ids);

    // ── P13 a plain gallery is as it was ─────────────────────────────────
    await page.click("#gal .gk-gallery-item:nth-child(1)");
    const pg = await lb(page);
    check("P13 a plain gallery tile still opens the viewer, with no \"Select\" button and no extra close button", pg.offen && pg.wahl === null && !pg.dismiss && pg.caption === "Plain one" && pg.zaehler === "1 / 2", pg);
    await page.keyboard.press("Escape");
    check("P13 and Escape gives the focus back to the tile", (await page.evaluate(() => document.activeElement.className)).includes("gk-gallery-item"));

    // ── P14 sizes: no sideways scroll, touch targets, long titles ────────
    const lang = await page.evaluate(() => { const t = document.querySelector('#p30 .gk-picker-input[value="img-2.webp"]').closest(".gk-picker-item").querySelector(".gk-picker-title"); const i = t.closest(".gk-picker-item"); const cs = getComputedStyle(t); return { hoehe: t.getBoundingClientRect().height, zeile: parseFloat(cs.lineHeight), breiteTitel: t.getBoundingClientRect().width, breiteKachel: i.getBoundingClientRect().width, overflow: cs.textOverflow, klammer: cs.webkitLineClamp }; });
    check("P14 a long title stops after two lines (an ellipsis, the whole of it the tooltip) and does not widen its tile", lang.hoehe <= lang.zeile * 2.2 && lang.hoehe > lang.zeile * 1.5 && lang.klammer === "2" && lang.breiteTitel <= lang.breiteKachel + 1, lang);
    const ziel = await page.evaluate(() => { const z = document.querySelector("#p12 .gk-picker-zoom").getBoundingClientRect(); const a = document.querySelector("#p12 .gk-picker-action").getBoundingClientRect(); return { zoom: [z.width, z.height], action: [a.width, a.height] }; });
    check("P14 the zoom button is at least 44 x 44 px (a finger), the caption action at least 32 x 32 with a mouse", ziel.zoom[0] >= 44 && ziel.zoom[1] >= 44 && ziel.action[0] >= 32 && ziel.action[1] >= 32, ziel);
    const ver = await page.evaluate(() => { const f = document.querySelector("#p12 .gk-picker-media").getBoundingClientRect(); const a = document.querySelector("#p12 .gk-picker-action").getBoundingClientRect(); return { rahmenUnten: f.bottom, aktionOben: a.top }; });
    check("P14 the action that removes does not reach onto the picture a tap picks (its box starts below the picture)", ver.aktionOben >= ver.rahmenUnten - 0.5, ver);
    const portrait = await page.evaluate(() => { const m = document.querySelector('#p12 input[value="img-5.webp"]').closest(".gk-picker-media"); const i = m.querySelector("img"); const r = m.getBoundingClientRect(), ir = i.getBoundingClientRect(); return { verhaeltnis: r.width / r.height, bildGleich: Math.abs(ir.width - r.width) < 1 && Math.abs(ir.height - r.height) < 1, fit: getComputedStyle(i).objectFit }; });
    check("P14 every tile is 16:9, a portrait picture is cropped to it, not squeezed", Math.abs(portrait.verhaeltnis - 16 / 9) < 0.05 && portrait.bildGleich && portrait.fit === "cover", portrait);

    // ── P15 colour: dark mode ────────────────────────────────────────────
    await page.evaluate(() => { document.body.setAttribute("data-gk-mode", "dark"); });
    await page.waitForTimeout(800);   // the page's own colours ease over; measure when they have arrived
    const dunkel = await page.evaluate(() => {
      // A colour the browser reports as oklch(...) (relative colour syntax) is turned into RGB by painting it.
      const rgb = (c) => { const x = document.createElement("canvas").getContext("2d"); x.fillStyle = "#000"; x.fillStyle = c; x.fillRect(0, 0, 1, 1); return Array.from(x.getImageData(0, 0, 1, 1).data).slice(0, 3); };
      const kontrast = (a, b) => { const l = (c) => { const m = rgb(c).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]; }; const x = l(a), y = l(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
      const t = document.querySelector("#p3 .gk-picker-title"); const bg = (() => { for (let n = t; n; n = n.parentElement) { const c = getComputedStyle(n).backgroundColor; if (!/rgba\(0, 0, 0, 0\)|transparent/.test(c)) return c; } return "rgb(0,0,0)"; })();
      const st = document.querySelector("#p3 [data-gk-picker-status]");
      const bgs = (() => { for (let n = st; n; n = n.parentElement) { const c = getComputedStyle(n).backgroundColor; if (!/rgba\(0, 0, 0, 0\)|transparent/.test(c)) return c; } return "rgb(0,0,0)"; })();
      const note = document.querySelector("#p12 .gk-picker-note");
      return { titel: kontrast(getComputedStyle(t).color, bg), status: kontrast(getComputedStyle(st).color, bgs), ring: getComputedStyle(document.querySelector("#p3 .gk-picker-input:checked")).boxShadow };
    });
    check("P15 dark mode: the title and the status line read at 4.5:1 or better, the ring is drawn", dunkel.titel >= 4.5 && dunkel.status >= 4.5 && /3px/.test(dunkel.ring), dunkel);
    await shot(page, "p-dark-1440");
    await page.evaluate(() => { document.body.removeAttribute("data-gk-mode"); });

    // ── P16 reduced motion: nothing moves ────────────────────────────────
    await page.emulateMedia({ reducedMotion: "reduce" });
    const ruhig = await page.evaluate(() => {
      const zeiten = (e) => getComputedStyle(e).transitionDuration.split(",").map((s) => parseFloat(s) || 0);
      const alle = [...document.querySelectorAll(".gk-picker-item, .gk-picker-img, .gk-picker-input, .gk-picker-zoom, .gk-picker-check, .gk-picker-action")];
      return { max: Math.max(...alle.flatMap(zeiten)) };
    });
    check("P16 with prefers-reduced-motion nothing in the picker transitions (the library's rule leaves 0.01 ms)", ruhig.max <= 0.001, ruhig);
    await page.click('#p3 [data-lightbox][data-caption="Image 1"] .gk-picker-zoom');
    const lbRuhe = await page.evaluate(() => { const l = document.querySelector(".gk-lightbox"); return { l: getComputedStyle(l).animationName, i: getComputedStyle(l.querySelector(".gk-lightbox-img")).animationName }; });
    check("P16 and the viewer does not animate in", lbRuhe.l === "none" && lbRuhe.i === "none", lbRuhe);
    await page.keyboard.press("Escape");
    await page.emulateMedia({ reducedMotion: "no-preference" });

    // ── P17 tabs: the order is the reading order ─────────────────────────
    await page.focus("#b12");
    const reihenfolge = [];
    for (let i = 0; i < 9; i++) { await page.keyboard.press("Tab"); reihenfolge.push(await page.evaluate(() => { const a = document.activeElement; return a.matches("input") ? "radio:" + a.value : a.matches(".gk-picker-zoom") ? "zoom" : a.matches(".gk-picker-action") ? "action" : a.matches("[data-gk-picker-more-btn]") ? "more" : a.id || a.tagName; })); }
    check("P17 tab order through the twelve: radio, zoom, (action), fold button, what follows — and no more than that for the group",
      reihenfolge.includes("more") && reihenfolge.includes("a12") && reihenfolge.filter((x) => x === "zoom").length <= 1 && reihenfolge.filter((x) => x.startsWith("radio:")).length === 1, reihenfolge);

    // ── P20 hostile text: every place the caller's words land, from script as well as from PHP ──────────────────────
    await page.evaluate(() => { window.__xss = undefined; });
    const boese = await page.evaluate(() => {
      const h = `<img src=x onerror="window.__xss=1">"'$&$$ \${x} </label></div><script>window.__xss=2</script>`;
      window.__boese = h;
      const c = document.getElementById("p3");
      const thumb = document.querySelector("#p3 img").getAttribute("src");
      const t = GK.picker.add(c, { value: "x-boese", title: h, alt: h, meta: h, note: h, badge: h, thumb, full: thumb, data: { "x-y": h }, actions: [{ name: h, label: h, icon: h }] }, { select: true });
      const r = t.querySelector("input");
      return { gewaehlt: r.checked, titel: t.querySelector(".gk-picker-title").textContent, status: document.querySelector("#p3 [data-gk-picker-status]").textContent, daten: t.getAttribute("data-x-y"),
        fremdes: c.querySelectorAll('img[src="x"], script').length, aktion: t.querySelector(".gk-picker-action").getAttribute("data-gk-picker-action"), hinweis: t.querySelector(".gk-picker-note").textContent, marke: t.querySelector(".gk-picker-badge").textContent };
    });
    check("P20 GK.picker.add with hostile text everywhere (markup, quotes, $&, $$, </label>): it is all text — no element appears, nothing runs", (await page.evaluate(() => window.__xss)) === undefined && boese.fremdes === 0 && boese.titel.includes("<img src=x") && boese.hinweis.includes("<img") && boese.marke.includes("<img"), boese);
    check("P20 the status line says the title literally — \"$&\" and \"$$\" are not replacement patterns", boese.status === "Selected: " + (await page.evaluate(() => window.__boese)), boese.status);
    check("P20 the data attribute and the action name keep the text, whole", boese.daten === (await page.evaluate(() => window.__boese)) && boese.aktion === (await page.evaluate(() => window.__boese)), boese);
    await page.click('#p3 .gk-picker-item:last-child .gk-picker-zoom');
    const vb = await lb(page);
    check("P20 the viewer shows the caption, the origin line and the alt literally, and nothing in it runs", vb.offen && vb.caption === (await page.evaluate(() => window.__boese)) && vb.alt === vb.caption && vb.meta.startsWith(vb.caption) && (await page.evaluate(() => window.__xss)) === undefined, { offen: vb.offen, caption: vb.caption && vb.caption.slice(0, 60), alt: vb.alt && vb.alt.slice(0, 20), meta: vb.meta && vb.meta.slice(0, 20) });
    const dialog = await page.evaluate(() => ({ name: document.querySelector(".gk-lightbox").getAttribute("aria-label") }));
    check("P20 the dialog is named by the picture it shows (so opening it says which one)", dialog.name === vb.caption, dialog);
    await page.keyboard.press("Escape");
    await page.evaluate(() => GK.picker.remove(document.getElementById("p3"), "x-boese"));

    // ── P21 the focus after paging far: the ring is in sight ────────────────────────────────────────────────────────
    await page.click("#p30 [data-gk-picker-more-btn]");
    await page.evaluate(() => document.querySelector('#p30 .gk-picker-item:first-child').scrollIntoView({ block: "start" }));
    await page.click('#p30 .gk-picker-item:first-child .gk-picker-zoom');
    await page.keyboard.press("ArrowLeft");   // wraps to the last picture (30), far below
    await page.keyboard.press("Escape");
    const fern = await page.evaluate(() => { const e = document.activeElement; const r = e.getBoundingClientRect(); return { aria: e.getAttribute("aria-label"), oben: Math.round(r.top), unten: Math.round(r.bottom), fenster: innerHeight }; });
    check("P21 after paging from the first to the last of thirty and Escape, the focus is on the zoom button of the LAST — and it is scrolled into view (a ring nobody can see is no ring)", fern.aria === "Enlarge: Image 30" && fern.oben >= 0 && fern.unten <= fern.fenster, fern);
    await page.click("#p30 [data-gk-picker-more-btn]");
    await page.evaluate(() => window.scrollTo(0, 0));

    // ── P22 the focus survives a disabled button, and a picture that arrives under an open viewer ────────────────────
    await page.click('#p12 [data-lightbox][data-caption="Image 5"] .gk-picker-zoom');
    await page.focus(".gk-lightbox-choose");
    await page.keyboard.press("ArrowRight");   // 5 → 7, which is disabled: the pick button disables under the focus
    const verloren = await page.evaluate(() => ({ drin: document.querySelector(".gk-lightbox").contains(document.activeElement), tag: document.activeElement.tagName, cls: document.activeElement.className, titel: document.querySelector(".gk-lightbox-caption").textContent }));
    check("P22 paging with the focus on \"Select this image\" onto a picture that cannot be picked does not drop the focus on the page behind: it moves to \"Close\"", verloren.titel === "Image 7" && verloren.drin && /gk-lightbox-dismiss/.test(verloren.cls), verloren);
    await page.evaluate(() => {
      const c = document.getElementById("p12");
      GK.picker.add(c, { value: "spaet", title: "Late arrival", thumb: document.querySelector("#p12 img").src, full: document.querySelector("#p12 img").src }, { select: true, focus: true });
    });
    const spaet = await page.evaluate(() => ({ drin: document.querySelector(".gk-lightbox").contains(document.activeElement), offen: document.querySelector(".gk-lightbox").classList.contains("open"), gewaehlt: document.querySelector('#p12 input[value="spaet"]').checked }));
    check("P22 a picture that arrives while the viewer is open (an upload that took a while) is picked but does NOT take the focus out from under the dialog", spaet.drin && spaet.offen && spaet.gewaehlt, spaet);
    await page.keyboard.press("Escape");
    await page.evaluate(() => GK.picker.remove(document.getElementById("p12"), "spaet"));
    await page.click('#p12 input[value="img-1.webp"]');

    // ── P23 a short screen: the picture keeps room ────────────────────────────────────────────────────────────────────
    await page.setViewportSize({ width: 667, height: 375 });
    await page.click('#p3 [data-lightbox] .gk-picker-zoom');
    await page.waitForTimeout(350);
    const kurz = await page.evaluate(() => { const i = document.querySelector(".gk-lightbox-img").getBoundingClientRect(), f = document.querySelector(".gk-lightbox-foot").getBoundingClientRect(), c = document.querySelector(".gk-lightbox-choose").getBoundingClientRect();
      return { bildHoehe: Math.round(i.height), fuss: Math.round(f.height), wahlImFenster: c.bottom <= innerHeight + 0.5 && c.top >= 0, ueber: i.bottom > f.top + 1 }; });
    check("P23 on a screen 375 px high (a phone held sideways) the picture keeps at least 200 px, caption and buttons share a line, the button is on screen and not over the picture", kurz.bildHoehe >= 200 && kurz.wahlImFenster && !kurz.ueber, kurz);
    await page.keyboard.press("Escape");
    await page.setViewportSize({ width: 1440, height: 900 });

    // ── P18 the phone: 390 and 360 px ────────────────────────────────────
    for (const w of [390, 360]) {
      await page.setViewportSize({ width: w, height: 844 });
      await page.waitForTimeout(250);   // the fold follows the width one frame later
      const m12 = await stand(page, "#p12"), m30 = await stand(page, "#p30"), m3 = await stand(page, "#p3");
      check(`P18 ${w} px: no sideways scroll, two or three tiles a row, the thirty fold into a short list`,
        m12.scrollBreite <= m12.fensterBreite && m30.scrollBreite <= m30.fensterBreite && m12.proReihe >= 2 && m12.proReihe <= 3 && m30.sichtbar <= 2 * m30.proReihe + 1, { m12, m30, m3 });
      const lesbar = await page.evaluate(() => { const t = document.querySelector("#p12 .gk-picker-title"); return { schrift: parseFloat(getComputedStyle(t).fontSize) }; });
      check(`P18 ${w} px: the tile title is at least 12 px`, lesbar.schrift >= 12, lesbar);
      await page.click('#p12 [data-lightbox][data-caption="Image 1"] .gk-picker-zoom');
      const mv = await page.evaluate(() => {
        const l = document.querySelector(".gk-lightbox"), i = l.querySelector(".gk-lightbox-img").getBoundingClientRect(), c = l.querySelector(".gk-lightbox-choose").getBoundingClientRect(), cap = l.querySelector(".gk-lightbox-caption").getBoundingClientRect();
        const w = window.innerWidth, h = window.innerHeight;
        const im = (r) => r.left >= -0.5 && r.right <= w + 0.5 && r.top >= -0.5 && r.bottom <= h + 0.5;
        return { bild: im(i), wahl: im(c), caption: im(cap), ueberlappung: i.bottom > c.top + 1 && i.top < c.bottom && i.right > c.left && i.left < c.right, scroll: document.documentElement.scrollWidth <= document.documentElement.clientWidth };
      });
      check(`P18 ${w} px: the viewer fits the screen — picture, caption and the pick button inside, the button not over the picture`, mv.bild && mv.wahl && mv.caption && !mv.ueberlappung && mv.scroll, mv);
      if (w === 390) { await page.waitForTimeout(350); await shot(page, "p-viewer-390"); }
      await page.keyboard.press("Escape");
      if (w === 390) { await page.evaluate(() => document.getElementById("s12").scrollIntoView()); await shot(page, "p-phone-390"); }
    }

    // ── P19 a touch screen: the caption action grows to a finger ─────────
    const touch = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    const tp = await touch.newPage();
    await tp.goto("file://" + fixture);
    const finger = await tp.evaluate(() => ({ grob: matchMedia("(pointer: coarse)").matches, action: (() => { const a = document.querySelector("#p12 .gk-picker-action").getBoundingClientRect(); return [a.width, a.height]; })(), zoom: (() => { const z = document.querySelector("#p12 .gk-picker-zoom").getBoundingClientRect(); return [z.width, z.height]; })() }));
    check("P19 on a touch screen both buttons are at least 44 x 44 px", finger.grob && finger.action[0] >= 44 && finger.action[1] >= 44 && finger.zoom[0] >= 44 && finger.zoom[1] >= 44, finger);
    await tp.tap("#p12 .gk-picker-item:nth-child(4) .gk-picker-media");
    check("P19 a tap on a tile picks it", (await stand(tp, "#p12")).gewaehlt[0] === "img-4.webp");
    await touch.close();
    await ctx.close();
  } finally {
    await browser.close();
  }
  report();
})().catch((e) => {
  // What ran before the break still counts: a case that cannot continue (a layer that did not close, so the next click is blocked) must not hide the cases already red.
  results.push({ name: "the run reached its end: " + e.message.split("\n")[0], ok: false, why: "" });
  report();
});

function report() {
  for (const r of results) console.log((r.ok ? "ok    " : "FAIL  ") + r.name + (r.ok || !r.why ? "" : "\n      " + r.why.slice(0, 600)));
  if (pageErrors.length) console.log("page errors:", pageErrors.slice(0, 3));
  const failed = results.filter((r) => !r.ok).length + (pageErrors.length ? 1 : 0);
  console.log(failed ? `\n${failed} problem(s)` : `\nok — ${results.length} cases`);
  process.exit(failed ? 1 : 0);
}
