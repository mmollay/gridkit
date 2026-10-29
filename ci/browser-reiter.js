#!/usr/bin/env node
/**
 * Karteireiter, Reiter in Reitern, das Raster am Handy und das Stufenband in einem schmalen Kasten (1.101.0).
 *
 * `php tests/run.php` liest das Stylesheet; ob ein Reiter am Handy abgeschnitten wird, ob ein Klick auf einen
 * äußeren Reiter die inneren zurücksetzt und ob drei Stufen in 340 px in eine Zeile passen, zeigt nur ein Browser.
 *
 *     node ci/browser-reiter.js
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/browser-reiter.js
 *     GK_SHOTS=/some/dir node ci/browser-reiter.js       # legt dazu Bilder ab
 *
 * Wie ci/browser.js außerhalb der Suite: GridKit hat keine Abhängigkeiten. Exit-Code 0, wenn alles hält.
 */
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

const WURZEL = path.join(__dirname, "..");
const SHOTS = process.env.GK_SHOTS || "";
const lies = (f) => fs.readFileSync(path.join(WURZEL, f), "utf8");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-reiter-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));

const knopf = (key, wort, an, symbol = "", zusatz = "") =>
  `<button class="gk-tab-btn${an ? " gk-active" : ""}" data-tab="${key}">`
  + (symbol ? `<span class="material-icons gk-tab-icon" aria-hidden="true">${symbol}</span>` : "")
  + `<span>${wort}</span>${zusatz}</button>`;
/* Außen und innen tragen absichtlich dieselben data-tab-Schlüssel: ein Reiter darf nur die Fläche seiner
   eigenen Reihe finden, nie die gleichnamige einer anderen Ebene. */
const SEITE = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${lies("css/gridkit.css")}</style><style>${lies("css/themes.css")}</style>
<style>.probe-kasten { width: 340px; } .probe-eng { width: 190px; }</style></head>
<body class="gk-root"><main class="gk-container">
<div class="gk-tabs" id="aussen">
  <div class="gk-tab-nav gk-tab-nav-karte">${knopf("a", "Außen A", true)}${knopf("b", "Außen B", false)}</div>
  <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">
    <div class="gk-tabs" id="innen">
      <div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary">${knopf("a", "Innen A", true)}${knopf("b", "Innen B", false)}</div>
      <div class="gk-tab-panel gk-active" data-tab="a">innen a</div>
      <div class="gk-tab-panel" data-tab="b">innen b</div>
    </div>
  </div>
  <div class="gk-tab-panel gk-tab-flaeche" data-tab="b">außen b</div>
</div>
<div class="gk-tabs" id="viele">
  <div class="gk-tab-nav gk-tab-nav-karte">
    ${knopf("1", "Anmeldung", true, "edit_note")}${knopf("2", "Bestätigungsseite", false, "task_alt", '<span class="gk-tab-badge">Angepasst</span>')}
    ${knopf("3", "Willkommensmail", false, "mail")}${knopf("4", "Erinnerungen", false, "alarm", '<span class="gk-tab-count">12</span>')}
    ${knopf("5", "Abmeldung", false, "logout")}${knopf("6", "Rechtliches", false, "gavel")}
  </div>
  <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="1">Inhalt</div>
</div>
<nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" id="unter" aria-label="Ansichten">
  <a class="gk-tab-btn" href="#">Anmeldungen</a><a class="gk-tab-btn" href="#" aria-current="page">Statistik</a><a class="gk-tab-btn" href="#">Verlauf</a>
</nav>
<div class="probe-kasten" id="stufen-kasten"><ol class="gk-steps" id="stufen" aria-label="Weg">
  <li class="gk-steps-item gk-steps-done">Änderungen<span class="gk-sr-only"> (erledigt)</span></li>
  <li class="gk-steps-item gk-steps-current" aria-current="step">Freigabe</li>
  <li class="gk-steps-item">Veröffentlichen</li></ol></div>
<div class="probe-eng"><ol class="gk-steps" id="stufen-eng" aria-label="Weg">
  <li class="gk-steps-item gk-steps-done">Änderungen</li><li class="gk-steps-item gk-steps-current" aria-current="step">Freigabe</li>
  <li class="gk-steps-item">Veröffentlichen</li></ol></div>
<nav class="gk-tab-nav" id="alt"><a class="gk-tab-btn" href="#" aria-current="page">Alt an</a><a class="gk-tab-btn" href="#">Alt aus</a></nav>
</main><script>${lies("js/gridkit.js")}</script></body></html>`;
const datei = path.join(dir, "reiter.html");
fs.writeFileSync(datei, SEITE);

const results = [];
const check = (name, ok, why) => results.push({ name, ok: !!ok, why: ok ? "" : JSON.stringify(why) });

(async () => {
  const browser = await chromium.launch();
  const fehler = [];
  try {
    // ── Verschachtelt (breit) ─────────────────────────────────────────────
    const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    p.on("pageerror", (e) => fehler.push(e.message));
    await p.goto("file://" + datei);
    const zustand = () => p.evaluate(() => {
      const an = (id) => [...document.querySelectorAll(`#${id} > .gk-tab-nav > .gk-tab-btn`)].filter((b) => b.classList.contains("gk-active")).map((b) => b.textContent.trim());
      const flaeche = (id) => [...document.querySelectorAll(`#${id} > .gk-tab-panel`)].filter((x) => x.classList.contains("gk-active")).map((x) => x.dataset.tab);
      const innenB = [...document.querySelectorAll("#innen .gk-tab-btn")][1];
      const innenFl = document.querySelector('#innen > .gk-tab-panel[data-tab="b"]');
      return { aussen: an("aussen"), innen: an("innen"), aussenFl: flaeche("aussen"), innenFl: flaeche("innen"),
        innenSel: innenB.getAttribute("aria-selected"), innenTab: innenB.tabIndex,
        innenControls: innenB.getAttribute("aria-controls") === innenFl.id, innenRolle: innenFl.getAttribute("role") };
    });
    const start = await zustand();
    check("verschachtelt: der innere Reiter B zeigt auf SEINE Fläche, nicht auf die gleichnamige außen",
      start.innenControls && start.innenRolle === "tabpanel", start);
    await p.click("#innen .gk-tab-btn:nth-child(2)");
    await p.click("#aussen > .gk-tab-nav .gk-tab-btn:nth-child(2)");
    const mitte = await zustand();
    check("verschachtelt: ein Klick auf einen äußeren Reiter lässt innen genau einen Reiter gewählt (B)",
      mitte.innen.length === 1 && mitte.innen[0] === "Innen B" && mitte.innenFl.join() === "b", mitte);
    check("… außen ist B gewählt und genau eine äußere Fläche offen", mitte.aussen.join() === "Außen B" && mitte.aussenFl.join() === "b", mitte);
    await p.click("#aussen > .gk-tab-nav .gk-tab-btn:nth-child(1)");
    const zurueck = await zustand();
    check("verschachtelt: zurück auf außen A steht innen noch B, mit aria-selected und Tabstopp",
      zurueck.innen.join() === "Innen B" && zurueck.innenFl.join() === "b" && zurueck.innenSel === "true" && zurueck.innenTab === 0, zurueck);
    await p.focus("#aussen > .gk-tab-nav .gk-tab-btn:nth-child(1)");
    await p.keyboard.press("ArrowRight");
    const pfeil = await zustand();
    check("Pfeil rechts außen wechselt außen und lässt innen in Ruhe", pfeil.aussen.join() === "Außen B" && pfeil.innen.join() === "Innen B", pfeil);
    const breit = await p.evaluate(() => {
      const css = (s, pseudo) => getComputedStyle(document.querySelector(s), pseudo || null);
      return { altLinie: css("#alt").borderBottomWidth, altPad: css("#alt .gk-tab-btn").paddingLeft,
        strich: css("#stufen .gk-steps-item", "::after").width, kartePad: css("#viele .gk-tab-btn").paddingLeft };
    });
    check("breit: die bisherige Reiterzeile ohne Bestellung bleibt wie sie war (2px Linie, 18px Rand)", breit.altLinie === "2px" && breit.altPad === "18px", breit);
    check("breit: das Stufenband behält seinen 24-px-Strich", breit.strich === "24px", breit);
    if (SHOTS) await p.screenshot({ path: path.join(SHOTS, "reiter-1440.png"), fullPage: true });
    await p.close();

    // ── Handy ─────────────────────────────────────────────────────────────
    const h = await browser.newPage({ viewport: { width: 390, height: 844 } });
    h.on("pageerror", (e) => fehler.push(e.message));
    await h.goto("file://" + datei);
    const handy = await h.evaluate(() => {
      const r = (el) => el.getBoundingClientRect();
      const nav = (s) => {
        const n = document.querySelector(s);
        const b = [...n.querySelectorAll(":scope > .gk-tab-btn")];
        return { display: getComputedStyle(n).display, spalten: getComputedStyle(n).gridTemplateColumns.split(" ").length,
          minHoehe: Math.min(...b.map((x) => r(x).height)),
          abgeschnitten: b.filter((x) => x.scrollWidth > x.clientWidth + 1 || r(x).right > r(n).right + 1 || r(x).left < r(n).left - 1).map((x) => x.textContent.trim()),
          navSchiebt: n.scrollWidth > n.clientWidth + 1 };
      };
      const st = (id) => {
        const ol = document.getElementById(id);
        const li = [...ol.children];
        const after = getComputedStyle(li[1], "::after");
        const clip = getComputedStyle(ol).clipPath;
        const links = r(ol).left + (parseFloat((clip.match(/inset\(\S+ \S+ \S+ ([\d.]+)px/) || [])[1]) || 0);
        const zeilenAnfang = li.filter((x, i) => i === 0 || r(x).top > r(li[i - 1]).top + 2);
        const strich = parseFloat(after.width) + parseFloat(after.marginLeft) + parseFloat(after.marginRight);
        return { tops: li.map((x) => Math.round(r(x).top)), vorn: after.order === "-1",
          /* Ein Strich, der vorn an einem Zeilenanfang sitzt, liegt ganz im abgeschnittenen Rand. */
          haengt: zeilenAnfang.filter((x) => r(x).left + strich > links + 1).length,
          strichPx: parseFloat(after.width), ueber: ol.scrollWidth - ol.clientWidth };
      };
      return { viele: nav("#viele > .gk-tab-nav"), unter: nav("#unter"), stufen: st("stufen"), eng: st("stufen-eng"),
        seite: document.documentElement.scrollWidth - innerWidth };
    });
    check("Handy: Seitenreiter als Raster aus zwei Spalten", handy.viele.display === "grid" && handy.viele.spalten === 2, handy.viele);
    check("Handy: jeder Seitenreiter ≥ 44 px hoch, keiner abgeschnitten, die Zeile schiebt nicht",
      handy.viele.minHoehe >= 44 && !handy.viele.abgeschnitten.length && !handy.viele.navSchiebt, handy.viele);
    check("Handy: Unterreiter ≥ 44 px, nichts abgeschnitten, kein Schieben", handy.unter.minHoehe >= 44 && !handy.unter.abgeschnitten.length && !handy.unter.navSchiebt, handy.unter);
    check("Handy: kein waagrechter Seitenüberlauf", handy.seite <= 0, handy.seite);
    check("Handy: drei Stufen in 340 px stehen in einer Zeile", new Set(handy.stufen.tops).size === 1 && handy.stufen.ueber <= 0, handy.stufen);
    check("Handy: der Strich ist kürzer als die 24 px am Schirm", handy.stufen.strichPx < 24, handy.stufen);
    check("Handy: bricht das Band doch um (190 px), hängt kein Strich am Zeilenende oder -anfang",
      new Set(handy.eng.tops).size > 1 && handy.eng.vorn && handy.eng.haengt === 0, handy.eng);
    const aria = await h.evaluate(() => [...document.querySelectorAll("#stufen > li")].map((x) => x.getAttribute("aria-current")));
    check("Handy: Reihenfolge und aria-current der Stufen bleiben", aria.join() === ",step,", aria);
    if (SHOTS) await h.screenshot({ path: path.join(SHOTS, "reiter-390.png"), fullPage: true });
    await h.close();
    check("keine Skriptfehler", !fehler.length, fehler);
  } finally {
    await browser.close();
  }
  let rot = 0;
  for (const r of results) { if (!r.ok) rot++; console.log(`${r.ok ? "ok  " : "FAIL"}  ${r.name}${r.ok ? "" : "  " + r.why}`); }
  console.log(`\n${results.length - rot} von ${results.length} gehalten`);
  process.exit(rot ? 1 : 0);
})().catch((e) => { console.error("Abbruch:", e.message.split("\n")[0]); process.exit(2); });
