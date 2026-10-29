#!/usr/bin/env node
/**
 * Karteireiter, Reiter in Reitern, das Raster am Handy und das Stufenband in einem schmalen Kasten (1.101.0);
 * eine Reihe, die nicht in ihren Kasten passt, das Fehler-Etikett und gleich hohe Zellen (1.102.0).
 *
 * `php tests/run.php` liest das Stylesheet; ob ein Reiter am Handy abgeschnitten wird, ob ein Klick auf einen
 * äußeren Reiter die inneren zurücksetzt, ob drei Stufen in 340 px in eine Zeile passen und ob sechs Reiter in
 * einem Kasten von 1138, 900, 700, 560 und 340 px (hell und dunkel) ganz zu sehen sind, zeigt nur ein Browser.
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
<style>.probe-kasten { width: 340px; } .probe-eng { width: 190px; } .probe-var { max-width: 100%; }</style></head>
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
<div class="probe-var" id="kasten"><div class="gk-tabs" id="sechs">
  <div class="gk-tab-nav gk-tab-nav-karte">
    ${knopf("v", "Vorschau", true)}${knopf("s", "Skript", false)}${knopf("g", "Gespräch", false)}
    ${knopf("m", "Material &amp; Rechte", false)}${knopf("p", "Protokoll &amp; Kosten", false)}
    ${knopf("o", "Veröffentlichung", false, "", '<span class="gk-tab-badge gk-tab-badge-error">Fehler</span>')}
  </div>
  <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="v">Inhalt</div>
</div></div>
<div class="probe-eng" id="eng-links"><nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" aria-label="Ansichten">
  <a class="gk-tab-btn" href="#">Anmeldungen</a><a class="gk-tab-btn" href="#" aria-current="page">Statistik</a><a class="gk-tab-btn" href="#">Verlauf</a></nav></div>
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

/* Reiterposition (1.102.0): zwei kleine Seiten mit Verweis-Reitern unter einem Kopf, dessen Höhe je Seite verschieden ist.
   Sie liegen auf http://gk.test (page.route), denn sessionStorage und ein echter Seitenwechsel brauchen eine Herkunft. */
const POS_BASIS = "http://gk.test";
const posSeite = ({ kopf = 300, label = "Bereiche", ziel = "/b", attr = "", body = "", mitReihe = true, hoehe = 3000 } = {}) => `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${lies("css/gridkit.css")}</style><style>${lies("css/themes.css")}</style>
<style>.kopf { height: ${kopf}px; } .lang { height: ${hoehe}px; }</style></head>
<body class="gk-root"${body}><header class="kopf">Kopf</header>
${mitReihe ? `<nav class="gk-tab-nav gk-tab-nav-karte"${label ? ` aria-label="${label}"` : ""}${attr}>
  <a class="gk-tab-btn" href="/a" ${ziel === "/a" ? 'aria-current="page"' : ""} id="zu-a">Eins</a><a class="gk-tab-btn" href="${ziel}" id="zu-ziel">Zwei</a>
  <a class="gk-tab-btn" href="/b#oben" id="zu-anker">Drei</a><a class="gk-tab-btn" href="https://fremd.test/b" id="zu-fremd">Vier</a>
</nav><div class="gk-tab-flaeche"><div class="lang">Inhalt</div></div>` : '<div class="lang">Keine Reiter</div>'}
<script>${lies("js/gridkit.js")}</script></body></html>`;
const POS_SEITEN = {
  "/a": posSeite({ kopf: 300 }), "/b": posSeite({ kopf: 520, ziel: "/a" }),
  "/a-aus": posSeite({ kopf: 300, attr: ' data-gk-tab-scroll="off"' }), "/a-aus-vorfahr": posSeite({ kopf: 300, body: ' data-gk-tab-scroll="off"' }),
  "/a-ohnename": posSeite({ kopf: 300, label: "", ziel: "/b-ohnename" }), "/b-ohnename": posSeite({ kopf: 520, label: "", ziel: "/a" }),
  "/a-ohnereihe": posSeite({ kopf: 300, ziel: "/c" }), "/c": posSeite({ mitReihe: false }),
  "/a-kurz": posSeite({ kopf: 300, ziel: "/kurz" }), "/kurz": posSeite({ kopf: 520, ziel: "/a", hoehe: 100 }),
};
const posKontext = async (browser, vorweg) => {
  const ctx = await browser.newContext({ viewport: { width: 1000, height: 700 } });
  if (vorweg) await ctx.addInitScript(vorweg);
  await ctx.route(POS_BASIS + "/**", (route) => {
    const pfad = new URL(route.request().url()).pathname;
    const html = POS_SEITEN[pfad];
    return route.fulfill(html ? { status: 200, contentType: "text/html; charset=utf-8", body: html } : { status: 404, body: "" });
  });
  const seite = await ctx.newPage();
  const fehlerSeite = [];
  seite.on("pageerror", (e) => fehlerSeite.push(e.message));
  return { ctx, seite, fehlerSeite };
};
const posReihe = (seite) => seite.evaluate(() => ({ oben: Math.round(document.querySelector(".gk-tab-nav").getBoundingClientRect().top), y: Math.round(scrollY),
  merk: (() => { try { return sessionStorage.getItem("gk-tab-scroll"); } catch (e) { return "gesperrt"; } })() }));

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

    // ── Eine Reihe, die nicht in ihren Kasten passt (1.102.0) ─────────────
    // Das Fenster bleibt 1440 px breit, nur der Kasten wird schmaler: es entscheidet die Breite der Reihe, nicht der Schirm.
    const passt = ["#aussen > .gk-tab-nav", "#innen > .gk-tab-nav", "#unter"];
    const ruhig = await p.evaluate((ids) => ids.map((i) => { const n = document.querySelector(i); return { i, raster: n.classList.contains("gk-tab-nav-raster"), anzeige: getComputedStyle(n).display }; }), passt);
    check("breit: eine Reihe, die passt, bekommt kein Raster und bleibt eine Zeile (außen, innen, Linkreihe)", ruhig.every((x) => !x.raster && x.anzeige === "flex"), ruhig);

    const kasten = (w, dunkel) => p.evaluate(async ([w, dunkel]) => {
      const k = document.getElementById("kasten");
      k.style.width = w + "px";
      if (dunkel) k.setAttribute("data-gk-mode", "dark"); else k.removeAttribute("data-gk-mode");
      await new Promise((ok) => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(ok, 30))));
      const nav = k.querySelector(".gk-tab-nav");
      const b = [...nav.querySelectorAll(":scope > .gk-tab-btn")];
      const r = (el) => el.getBoundingClientRect();
      const hoehen = b.map((x) => r(x).height);
      const an = b.filter((x) => x.getAttribute("aria-selected") === "true");
      return { w, dunkel, natur: Math.round(r(b[b.length - 1]).right - r(nav).left),   /* ab hier schneidet die Zeile den letzten Reiter ab */
        raster: nav.classList.contains("gk-tab-nav-raster"), anzeige: getComputedStyle(nav).display,
        spalten: getComputedStyle(nav).gridTemplateColumns.split(" ").length, polster: getComputedStyle(b[0]).paddingLeft,
        abgeschnitten: b.filter((x) => x.scrollWidth > x.clientWidth + 1 || r(x).right > r(nav).right + 1 || r(x).left < r(nav).left - 1).map((x) => x.textContent.trim()),
        schiebt: nav.scrollWidth > nav.clientWidth + 1, navBreite: Math.round(r(nav).width),
        minH: Math.min(...hoehen), maxH: Math.max(...hoehen), gewaehlt: an.length,
        gewicht: an[0] ? getComputedStyle(an[0]).fontWeight : "", akzent: an[0] ? getComputedStyle(an[0]).boxShadow !== "none" : false,
        seite: document.documentElement.scrollWidth - innerWidth };
    }, [w, dunkel]);
    const natur = (await kasten(3000, false)).natur;
    check("Kasten: die sechs Reiter brauchen als Zeile weniger als 3000 und mehr als 340 px (gemessen: " + natur + " px)", natur > 340 && natur < 3000, natur);
    const zustaende = new Set();
    for (const dunkel of [false, true]) {
      /* Die fünf Breiten des Auftrags, dazu die Grenze selbst: 6 px über und unter der gemessenen Zeilenbreite. */
      for (const w of [1138, 900, natur + 6, natur - 6, 700, 560, 340]) {
        const m = await kasten(w, dunkel);
        const wie = `${w} px ${dunkel ? "dunkel" : "hell"}`;
        if (Math.abs(natur - w) > 3) {
          const soll = natur > w;
          zustaende.add(soll);
          check(`Kasten ${wie}: ${soll ? "die Reihe passt nicht und wird das Raster" : "die Reihe passt und bleibt eine Zeile"}`, m.raster === soll, m);
        }
        check(`Kasten ${wie}: alle sechs Reiter ganz zu sehen, nichts schiebt, die Seite läuft nicht über`, !m.abgeschnitten.length && !m.schiebt && m.seite <= 0, m);
        check(`Kasten ${wie}: genau ein Reiter gewählt, fett und mit Akzent`, m.gewaehlt === 1 && Number(m.gewicht) >= 700 && m.akzent, m);
        if (m.raster) {
          check(`Kasten ${wie}: Raster aus ≥ 2 Spalten, Zellen ≥ 44 px und alle gleich hoch (auch mit Etikett)`,
            m.anzeige === "grid" && m.spalten >= 2 && m.minH >= 44 && m.maxH - m.minH <= 1, m);
        } else {
          check(`Kasten ${wie}: die Zeile ist die von 1.101.0 (flex, 18 px Rand)`, m.anzeige === "flex" && m.polster === "18px", m);
        }
        if (SHOTS) await p.locator("#kasten").screenshot({ path: path.join(SHOTS, `kasten-${w}-${dunkel ? "dunkel" : "hell"}.png`) });
      }
    }
    check("Kasten: beide Zustände (Zeile und Raster) kommen in den Breiten vor", zustaende.has(true) && zustaende.has(false), [...zustaende]);
    // Pfeiltasten und aria-selected wie zuvor, im Raster (340 px) und in der Zeile (breit).
    for (const w of [340, 3000]) {
      await kasten(w, false);
      await p.focus("#sechs > .gk-tab-nav .gk-tab-btn:nth-child(1)");
      await p.keyboard.press("ArrowRight");
      const k1 = await p.evaluate(() => [...document.querySelectorAll("#sechs > .gk-tab-nav > .gk-tab-btn")].map((x) => x.getAttribute("aria-selected")).join());
      await p.keyboard.press("End");
      const k2 = await p.evaluate(() => [...document.querySelectorAll("#sechs > .gk-tab-nav > .gk-tab-btn")].map((x) => x.getAttribute("aria-selected")).join());
      check(`Kasten ${w} px: Pfeil rechts und Ende wählen den nächsten und den letzten Reiter (aria-selected)`,
        k1 === "false,true,false,false,false,false" && k2 === "false,false,false,false,false,true", [k1, k2]);
    }
    const rueck = await kasten(3000, false);
    check("Kasten: wird der Kasten wieder breit, ist die Zeile wieder da (das Raster ist umkehrbar)", !rueck.raster && rueck.anzeige === "flex", rueck);
    // Eine Linkreihe in 190 px und Inhalt, der später kommt (GK.initContent), werden genauso gemessen.
    const eng = await p.evaluate(() => {
      const r = (el) => el.getBoundingClientRect();
      const n = document.querySelector("#eng-links > nav");
      const box = document.createElement("div");
      box.style.width = "190px";
      box.innerHTML = '<nav class="gk-tab-nav gk-tab-nav-karte"><button class="gk-tab-btn" data-tab="x">Ein langer Reiter</button><button class="gk-tab-btn" data-tab="y">Noch ein langer Reiter</button></nav>';
      document.body.append(box);
      GK.initContent(box);
      const spaeter = box.firstElementChild;
      return { linkRaster: n.classList.contains("gk-tab-nav-raster"), linkMin: Math.min(...[...n.children].map((x) => r(x).height)),
        linkRaus: [...n.children].filter((x) => r(x).right > r(n).right + 1).length,
        spaeterRaster: spaeter.classList.contains("gk-tab-nav-raster"), spaeterSchiebt: spaeter.scrollWidth > spaeter.clientWidth + 1 };
    });
    check("Kasten: eine Linkreihe in 190 px wird das Raster (Zellen ≥ 44 px, nichts ragt heraus)", eng.linkRaster && eng.linkMin >= 44 && eng.linkRaus === 0, eng);
    check("Kasten: später geladener Inhalt (GK.initContent) wird gemessen", eng.spaeterRaster && !eng.spaeterSchiebt, eng);
    // ── Die Gestalt der Karteireiter (1.102.0): kleine Ecken, Unterreiter als Linienreiter ─────────────
    const gestalt = await p.evaluate(async () => {
      const css = (el, pseudo) => getComputedStyle(el, pseudo || null);
      const r = (el) => el.getBoundingClientRect();
      const farbe = (v) => { const e = document.createElement("i"); e.style.color = `var(${v})`; document.body.append(e); const c = css(e).color; e.remove(); return c; };
      const tabs = [...document.querySelectorAll("#aussen > .gk-tab-nav > .gk-tab-btn")];
      const an = tabs.find((b) => b.classList.contains("gk-active")), aus = tabs.find((b) => !b.classList.contains("gk-active"));   /* welcher es ist, hängt vom Pfeil-Test davor ab */
      const flaeche = document.querySelector("#aussen > .gk-tab-panel.gk-active");
      const ecken = (el) => [css(el).borderTopLeftRadius, css(el).borderTopRightRadius, css(el).borderBottomRightRadius, css(el).borderBottomLeftRadius].join(" ");
      const erg = { tabEcken: ecken(aus), flaecheEcken: ecken(flaeche),
        /* die Ecke, an der der gewählte Reiter in die Fläche übergeht: kein Spalt, kein Versatz, keine Linie */
        spalt: Math.round(r(flaeche).top - r(an).bottom), linieUnten: css(an).borderBottomColor === css(flaeche).backgroundColor, linkeKante: Math.round(r(an).left - r(flaeche).left) };
      // Der Token wirkt von einem Vorfahren aus.
      document.getElementById("aussen").style.setProperty("--gk-tab-radius", "9px");
      erg.tokenEcken = [ecken(aus), ecken(flaeche)];
      document.getElementById("aussen").style.removeProperty("--gk-tab-radius");
      // Unterreiter: ein Linienreiter. Ruhende Reiter ohne Kasten und ohne Füllung, der gewählte mit 3-px-Strich.
      const zeile = [...document.querySelectorAll("#unter > .gk-tab-btn")];
      const ruh = zeile[0], gew = zeile[1];
      const ohneKasten = (b) => ["Top", "Left", "Right"].every((s) => css(b)[`border${s}Width`] === "0px");
      erg.unter = { ruhOhneKasten: ohneKasten(ruh), ruhFuellung: css(ruh).backgroundColor, ruhSchatten: css(ruh).boxShadow,
        ruhStrich: css(ruh).borderBottomColor, gewOhneKasten: ohneKasten(gew), gewStrichBreite: css(gew).borderBottomWidth,
        gewStrichFarbe: css(gew).borderBottomColor, primaer: farbe("--gk-primary"), gewGewicht: css(gew).fontWeight, gewSchrift: css(gew).color, primaerText: farbe("--gk-primary-text"),
        gewFuellung: css(gew).backgroundColor, minH: Math.min(...zeile.map((b) => r(b).height)),
        mitStrich: zeile.filter((b) => css(b).borderBottomColor !== "rgba(0, 0, 0, 0)").length,
        linie: css(document.getElementById("unter")).borderBottomWidth, erstesRuhFuellung: css(aus).backgroundColor, erstesRuhRahmen: css(aus).borderTopWidth,
        schriftRuh: css(ruh).color, schriftGew: css(gew).color };
      // Hover: nur eine zarte Tönung, kein Rahmen; der Fokusring liegt innen.
      ruh.focus({ focusVisible: true });
      erg.fokus = { stil: css(ruh).outlineStyle, breite: css(ruh).outlineWidth, versatz: css(ruh).outlineOffset };
      ruh.blur();
      return erg;
    });
    check("Gestalt: die Ecken der Reiter sind oben 4 px, unten 0; die Fläche 0 4 4 4 (kein Knopf-Radius)",
      gestalt.tabEcken === "4px 4px 0px 0px" && gestalt.flaecheEcken === "0px 4px 4px 4px", gestalt);
    check("Gestalt: der Radius ist ein Token, den ein Vorfahr ändert (--gk-tab-radius)",
      gestalt.tokenEcken[0] === "9px 9px 0px 0px" && gestalt.tokenEcken[1] === "0px 9px 9px 9px", gestalt.tokenEcken);
    check("Gestalt: der gewählte Reiter geht ohne Spalt, ohne Linie und ohne Versatz in die Fläche über",
      gestalt.spalt <= 1 && gestalt.spalt >= -1 && gestalt.linieUnten && gestalt.linkeKante >= 12, gestalt);
    check("Unterreiter: im Ruhezustand ohne Kasten, ohne Füllung und ohne Schatten (ein Linienreiter, kein Karteireiter)",
      gestalt.unter.ruhOhneKasten && gestalt.unter.ruhFuellung === "rgba(0, 0, 0, 0)" && gestalt.unter.ruhSchatten === "none" && gestalt.unter.ruhStrich === "rgba(0, 0, 0, 0)", gestalt.unter);
    check("Unterreiter: der gewählte hat einen 3-px-Strich in der Hauptfarbe, fette Schrift in der Textfarbe der Hauptfarbe, keinen Kasten, keine Füllung",
      gestalt.unter.gewOhneKasten && gestalt.unter.gewStrichBreite === "3px" && gestalt.unter.gewStrichFarbe === gestalt.unter.primaer
        && Number(gestalt.unter.gewGewicht) >= 600 && gestalt.unter.gewSchrift === gestalt.unter.primaerText && gestalt.unter.gewFuellung === "rgba(0, 0, 0, 0)", gestalt.unter);
    check("Unterreiter: genau ein Strich in der Zeile, darunter eine dünne Linie; ≥ 44 px hoch", gestalt.unter.mitStrich === 1 && gestalt.unter.linie === "1px" && gestalt.unter.minH >= 44, gestalt.unter);
    check("Die Ebenen unterscheiden sich: ein ruhender Seitenreiter hat Rahmen und graue Füllung, ein ruhender Unterreiter beides nicht",
      gestalt.unter.erstesRuhRahmen !== "0px" && gestalt.unter.erstesRuhFuellung !== "rgba(0, 0, 0, 0)", gestalt.unter);
    check("Unterreiter: der Fokusring ist da (2 px, durchgezogen) und liegt innen (Versatz −2 px)",
      gestalt.fokus.stil === "solid" && gestalt.fokus.breite === "2px" && gestalt.fokus.versatz === "-2px", gestalt.fokus);
    await p.hover("#unter > .gk-tab-btn:nth-child(1)");
    await p.waitForTimeout(300);   /* die Tönung blendet über 0,15 s ein */
    const hover = await p.evaluate(() => {
      const zeile = [...document.querySelectorAll("#unter > .gk-tab-btn")];
      const f = (b) => getComputedStyle(b).backgroundColor;
      /* color-mix gibt der Browser als color(…) / oklab(…) mit "/ alpha" zurück, ein Rot-Grün-Blau mit rgba(…) */
      const alpha = (c) => { const m = c.match(/\/\s*([\d.]+%?)\s*\)$/); if (m) return m[1].endsWith("%") ? parseFloat(m[1]) / 100 : Number(m[1]); const q = c.match(/rgba\(([^)]+)\)/); return q ? Number(q[1].split(",")[3]) : 1; };
      return { getoent: zeile.filter((b) => f(b) !== "rgba(0, 0, 0, 0)").length, alpha: alpha(f(zeile[0])), rahmen: getComputedStyle(zeile[0]).borderTopWidth,
        ecken: getComputedStyle(zeile[0]).borderTopLeftRadius };
    });
    check("Unterreiter: der Hover ist nur eine zarte Tönung (höchstens 10 %) auf genau einem Reiter, ohne Rahmen, mit den kleinen Ecken oben",
      hover.getoent === 1 && hover.alpha > 0 && hover.alpha <= 0.1 && hover.rahmen === "0px" && hover.ecken === "4px", hover);
    await p.mouse.move(0, 0);
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
          minHoehe: Math.min(...b.map((x) => r(x).height)), maxHoehe: Math.max(...b.map((x) => r(x).height)),
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
      const zelle = (el) => ({ rahmenOben: getComputedStyle(el).borderTopWidth, rahmenUnten: getComputedStyle(el).borderBottomWidth, fuellung: getComputedStyle(el).backgroundColor, ecken: getComputedStyle(el).borderTopLeftRadius });
      const unterZellen = [...document.querySelectorAll("#unter > .gk-tab-btn")].map(zelle);
      const seitenZelle = zelle(document.querySelector("#viele > .gk-tab-nav > .gk-tab-btn:nth-child(2)"));
      return { unterZellen, seitenZelle, viele: nav("#viele > .gk-tab-nav"), unter: nav("#unter"), stufen: st("stufen"), eng: st("stufen-eng"),
        seite: document.documentElement.scrollWidth - innerWidth };
    });
    check("Handy: Seitenreiter als Raster aus zwei Spalten", handy.viele.display === "grid" && handy.viele.spalten === 2, handy.viele);
    check("Handy: jeder Seitenreiter ≥ 44 px hoch, keiner abgeschnitten, die Zeile schiebt nicht",
      handy.viele.minHoehe >= 44 && !handy.viele.abgeschnitten.length && !handy.viele.navSchiebt, handy.viele);
    check("Handy: alle Zellen eines Rasters sind gleich hoch, auch mit Etikett und Zähler (1.102.0)",
      handy.viele.maxHoehe - handy.viele.minHoehe <= 1 && handy.unter.maxHoehe - handy.unter.minHoehe <= 1, [handy.viele, handy.unter]);
    check("Handy: Unterreiter ≥ 44 px, nichts abgeschnitten, kein Schieben", handy.unter.minHoehe >= 44 && !handy.unter.abgeschnitten.length && !handy.unter.navSchiebt, handy.unter);
    check("Handy: die Unterreiter bleiben ein Linienreiter — kein Rahmen ringsum, nur eine Linie unten (1 px), die gewählte Zelle 3 px; die Seitenreiter bleiben gerahmte Zellen mit 4 px Ecken",
      handy.unterZellen.every((z) => z.rahmenOben === "0px" && z.fuellung === "rgba(0, 0, 0, 0)") && handy.unterZellen[0].rahmenUnten === "1px" && handy.unterZellen[1].rahmenUnten === "3px"
        && handy.seitenZelle.rahmenOben === "1px" && handy.seitenZelle.ecken === "4px", handy);
    check("Handy: kein waagrechter Seitenüberlauf", handy.seite <= 0, handy.seite);
    check("Handy: drei Stufen in 340 px stehen in einer Zeile", new Set(handy.stufen.tops).size === 1 && handy.stufen.ueber <= 0, handy.stufen);
    check("Handy: der Strich ist kürzer als die 24 px am Schirm", handy.stufen.strichPx < 24, handy.stufen);
    check("Handy: bricht das Band doch um (190 px), hängt kein Strich am Zeilenende oder -anfang",
      new Set(handy.eng.tops).size > 1 && handy.eng.vorn && handy.eng.haengt === 0, handy.eng);
    const aria = await h.evaluate(() => [...document.querySelectorAll("#stufen > li")].map((x) => x.getAttribute("aria-current")));
    check("Handy: Reihenfolge und aria-current der Stufen bleiben", aria.join() === ",step,", aria);
    if (SHOTS) await h.screenshot({ path: path.join(SHOTS, "reiter-390.png"), fullPage: true });
    await h.close();
    // ── Reiterposition (1.102.0): ein Verweis-Reiter behält seine Stelle im Fenster ───────────────────
    const klickUndWarte = async (seite, auswahl, optionen, ziel) => {
      await Promise.all([seite.waitForURL(POS_BASIS + ziel, { timeout: 8000 }), seite.click(auswahl, optionen)]);
      await seite.waitForLoadState("load");
      await seite.waitForTimeout(80);
    };
    {
      // Der Hauptfall: gescrollt, geklickt — die Reiterzeile steht auf ±2 px am selben Fensterort, obwohl der Kopf darüber höher ist.
      const { ctx, seite, fehlerSeite } = await posKontext(browser);
      await seite.goto(POS_BASIS + "/a");
      await seite.evaluate(() => scrollTo(0, 200));
      const vorher = await posReihe(seite);
      await klickUndWarte(seite, "#zu-ziel", {}, "/b");
      const nachher = await posReihe(seite);
      check("Reiterposition: nach Scrollen und Klick steht die Reiterzeile auf ±2 px am selben Fensterort (Kopf 300 → 520 px)",
        Math.abs(nachher.oben - vorher.oben) <= 2 && nachher.y > 300, { vorher, nachher });
      check("Reiterposition: die Merkung ist danach gelöscht, der Browser behält sie nicht", nachher.merk === null, nachher);
      // Zurück und Neuladen bleiben dem Browser überlassen: nichts wird gemerkt, nichts verschoben, kein Fehler.
      await seite.goBack(); await seite.waitForLoadState("load");
      const zurueck = await posReihe(seite);
      check("Reiterposition: Zurück schreibt und verschiebt nichts (der Browser stellt die Stelle selbst her)", zurueck.merk === null && Math.abs(zurueck.oben - vorher.oben) <= 2, { vorher, zurueck });
      // Enter auf dem Reiter wirkt wie der Klick.
      await seite.evaluate(() => scrollTo(0, 150));
      const v2 = await posReihe(seite);
      await seite.focus("#zu-ziel");
      await Promise.all([seite.waitForURL(POS_BASIS + "/b", { timeout: 8000 }), seite.keyboard.press("Enter")]);
      await seite.waitForLoadState("load"); await seite.waitForTimeout(80);
      const n2 = await posReihe(seite);
      check("Reiterposition: Enter auf dem Reiter wirkt wie der Klick", Math.abs(n2.oben - v2.oben) <= 2 && n2.y > 300, { v2, n2 });
      check("Reiterposition: keine Skriptfehler im Hauptfall", fehlerSeite.length === 0, fehlerSeite);
      await ctx.close();
    }
    {
      // Nichts merken: Reiter mit #-Ziel, fremde Herkunft, Strg-Klick, Mittelklick, Umschalt-Klick.
      const { ctx, seite, fehlerSeite } = await posKontext(browser);
      const rein = async (auswahl, optionen) => { await seite.goto(POS_BASIS + "/a"); await seite.evaluate(() => scrollTo(0, 200)); await seite.click(auswahl, optionen).catch((e) => fehlerSeite.push(String(e).slice(0, 80))); await seite.waitForTimeout(150); return posReihe(seite); };
      const anker = await rein("#zu-anker", {});
      check("Reiterposition: ein Reiter mit #-Ziel merkt nichts (die Seite springt zum Anker, wie der Browser es tut)", anker.merk === null, anker);
      await seite.goto(POS_BASIS + "/a");
      const strg = await rein("#zu-ziel", { modifiers: ["Control"] });
      check("Reiterposition: Strg-Klick merkt nichts", strg.merk === null && strg.y === 200, strg);
      const umschalt = await rein("#zu-ziel", { modifiers: ["Shift"] });
      check("Reiterposition: Umschalt-Klick merkt nichts", umschalt.merk === null, umschalt);
      await ctx.pages().filter((x) => x !== seite).reduce((a, x) => a.then(() => x.close()), Promise.resolve());
      await seite.goto(POS_BASIS + "/a"); await seite.evaluate(() => scrollTo(0, 200));
      await seite.click("#zu-ziel", { button: "middle" }).catch(() => {});
      await seite.waitForTimeout(150);
      check("Reiterposition: der Mittelklick merkt nichts", (await posReihe(seite)).merk === null, await posReihe(seite));
      await ctx.pages().filter((x) => x !== seite).reduce((a, x) => a.then(() => x.close()), Promise.resolve());
      // Fremde Herkunft: der Klick wird erst NACH GridKit abgefangen (window, Bubble), damit kein Netz gebraucht wird und GridKits Herkunftsprüfung entscheidet.
      await seite.goto(POS_BASIS + "/a"); await seite.evaluate(() => { scrollTo(0, 200); window.addEventListener("click", (e) => e.preventDefault()); });
      await seite.click("#zu-fremd");
      check("Reiterposition: ein Reiter zu einer anderen Herkunft merkt nichts", (await posReihe(seite)).merk === null, await posReihe(seite));
      check("Reiterposition: keine Skriptfehler", fehlerSeite.length === 0, fehlerSeite);
      await ctx.close();
    }
    for (const [name, start, ziel] of [["data-gk-tab-scroll=\"off\" an der Reihe", "/a-aus", "/b"], ["data-gk-tab-scroll=\"off\" an einem Vorfahren", "/a-aus-vorfahr", "/b"]]) {
      const { ctx, seite } = await posKontext(browser);
      await seite.goto(POS_BASIS + start); await seite.evaluate(() => scrollTo(0, 200));
      await klickUndWarte(seite, "#zu-ziel", {}, ziel);
      const aus = await posReihe(seite);
      check(`Reiterposition: ${name} schaltet es ab (die Seite beginnt oben)`, aus.y === 0, aus);
      await ctx.close();
    }
    {
      // Ohne Merkung, mit altem Eintrag, mit #-Anker in der Adresse, mit fremder Zieladresse, mit fehlender Reihe: nichts bewegt sich.
      const { ctx, seite, fehlerSeite } = await posKontext(browser);
      const eintrag = (extra) => JSON.stringify({ url: "/b", label: "Bereiche", index: 0, top: 100, t: Date.now(), ...extra });
      const probe = async (name, adresse, wert) => {
        await seite.goto(POS_BASIS + "/a");
        await seite.evaluate(([k, v]) => { if (v !== null) sessionStorage.setItem(k, v); }, ["gk-tab-scroll", wert]);
        await seite.goto(POS_BASIS + adresse);
        await seite.waitForTimeout(80);
        const z = await posReihe(seite);
        check(`Reiterposition: ${name} — nichts wird gescrollt, der Eintrag ist danach weg`, z.y === 0 && z.merk === null, z);
      };
      await probe("ohne Merkung", "/b", null);
      await probe("mit #-Anker in der Adresse", "/b#x", eintrag({}));
      await probe("mit einer Merkung, die älter ist als 15 s", "/b", eintrag({ t: Date.now() - 16000 }));
      await probe("mit einer Merkung für eine andere Adresse", "/b", eintrag({ url: "/anders" }));
      await probe("mit einer Merkung für eine Reihe, die es hier nicht gibt", "/b", eintrag({ label: "Gibt es nicht" }));
      await probe("mit unlesbarer Merkung", "/b", "{kaputt");
      await probe("mit einem Merkwert, der keine Zahl ist", "/b", eintrag({ top: "oben" }));
      await seite.goto(POS_BASIS + "/a"); await seite.evaluate(([k, v]) => sessionStorage.setItem(k, v), ["gk-tab-scroll", eintrag({ url: "/c", label: "Bereiche" })]);
      await seite.goto(POS_BASIS + "/c");
      const ohneReihe = await seite.evaluate(() => ({ y: Math.round(scrollY), merk: sessionStorage.getItem("gk-tab-scroll") }));
      check("Reiterposition: eine Zielseite ohne Reiterzeile bleibt oben, ohne Fehler", ohneReihe.y === 0 && ohneReihe.merk === null && fehlerSeite.length === 0, { ohneReihe, fehlerSeite });
      await ctx.close();
    }
    {
      // Eine Reihe ohne aria-label wird an ihrer Reihenfolge erkannt; eine kürzere neue Seite rastet natürlich ein.
      const { ctx, seite, fehlerSeite } = await posKontext(browser);
      await seite.goto(POS_BASIS + "/a-ohnename"); await seite.evaluate(() => scrollTo(0, 200));
      const v = await posReihe(seite);
      await klickUndWarte(seite, "#zu-ziel", {}, "/b-ohnename");
      const n = await posReihe(seite);
      check("Reiterposition: eine Reihe ohne aria-label wird an ihrer Reihenfolge erkannt", Math.abs(n.oben - v.oben) <= 2 && n.y > 300, { v, n });
      await seite.goto(POS_BASIS + "/a-kurz"); await seite.evaluate(() => scrollTo(0, 200));
      await klickUndWarte(seite, "#zu-ziel", {}, "/kurz");
      const kurz = await seite.evaluate(() => ({ y: Math.round(scrollY), max: Math.round(document.documentElement.scrollHeight - innerHeight) }));
      check("Reiterposition: ist die neue Seite kürzer, rastet der Browser natürlich ein (nicht über das Ende hinaus), ohne Fehler", kurz.y <= Math.max(0, kurz.max) && fehlerSeite.length === 0, { kurz, fehlerSeite });
      await ctx.close();
    }
    {
      // sessionStorage gesperrt: der Reiter führt trotzdem hin, es gibt keinen Fehler, die Seite beginnt oben.
      const { ctx, seite, fehlerSeite } = await posKontext(browser, "Object.defineProperty(window, 'sessionStorage', { get() { throw new DOMException('gesperrt', 'SecurityError'); } });");
      await seite.goto(POS_BASIS + "/a"); await seite.evaluate(() => scrollTo(0, 200));
      await klickUndWarte(seite, "#zu-ziel", {}, "/b");
      const gesperrt = await seite.evaluate(() => Math.round(scrollY));
      check("Reiterposition: bei gesperrtem sessionStorage führt der Reiter hin, ohne Fehler und ohne Verschiebung", gesperrt === 0 && fehlerSeite.length === 0, { gesperrt, fehlerSeite });
      await ctx.close();
    }
    // Ohne Skript ändert sich nichts: die Zeile bleibt eine Zeile, wie in 1.101.0 (sie schiebt dann in sich, wie dort).
    const ohne = await browser.newPage({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
    await ohne.goto("file://" + datei);
    const navO = ohne.locator("#eng-links > nav");
    const klasse = (await navO.getAttribute("class")) || "";
    const nb = await navO.boundingBox();
    const lb = await navO.locator(".gk-tab-btn").last().boundingBox();
    await ohne.close();
    check("ohne Skript: die Linkreihe in 190 px bekommt kein Raster und bleibt die Zeile von 1.101.0",
      !klasse.includes("gk-tab-nav-raster") && lb.x + lb.width > nb.x + nb.width + 1, { klasse, nb, lb });
    check("keine Skriptfehler", !fehler.length, fehler);
  } finally {
    await browser.close();
  }
  let rot = 0;
  for (const r of results) { if (!r.ok) rot++; console.log(`${r.ok ? "ok  " : "FAIL"}  ${r.name}${r.ok ? "" : "  " + r.why}`); }
  console.log(`\n${results.length - rot} von ${results.length} gehalten`);
  process.exit(rot ? 1 : 0);
})().catch((e) => { console.error("Abbruch:", e.message.split("\n")[0]); process.exit(2); });
