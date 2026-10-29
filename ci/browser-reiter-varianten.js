#!/usr/bin/env node
/**
 * Reiter-Varianten (1.103.0): Stile (karte, linie, kapsel, text) × Ausrichtung (oben, links, rechts, unten) × Optionen
 * (Größe, volle Breite, Symbol oben, nur Symbol, neutral, deaktiviert), die vier Kombinationen und die Rückwärtsverträglichkeit.
 *
 * Gemessen im Browser (Chromium): Rollen, Pfeiltasten je Achse, Home/End, aria-orientation, Lage von Reihe und Fläche,
 * Handy-Rückfall der Seitenleiste, untere feste Leiste, Überläufe, Kontrast hell und dunkel, Strichseite, Optionen.
 * Rückwärtsverträglichkeit: die berechneten Werte aller älteren Reiterformen stehen in ci/reiter-bestand.json (Stand 1.102.0);
 * `GK_QUELLE=<Ordner mit css/ und js/ von 1.102.0> GK_BESTAND_SCHREIBEN=1 node ci/browser-reiter-varianten.js` schreibt die Datei neu
 * (nur nach einer gewollten Änderung der alten Formen; den Ordner liefert `git archive <Stand> css js`).
 * Aufruf: GK_PLAYWRIGHT=/pfad/zu/playwright node ci/browser-reiter-varianten.js — Exit 0, wenn alles hält.
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
// GK_QUELLE: ein Ordner mit css/ und js/ eines anderen Standes (so entsteht ci/reiter-bestand.json aus 1.102.0)
const QUELLE = process.env.GK_QUELLE || WURZEL;
const lies = (f) => fs.readFileSync(path.join(QUELLE, f), "utf8");
const BESTAND = path.join(__dirname, "reiter-bestand.json");
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-varianten-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));

const STILE = ["karte", "linie", "kapsel", "text"];
const AUSR = ["oben", "links", "rechts", "unten"];
// Die Seite der Strichs je Stil und Ausrichtung: [x, y] des inneren Schattens am gewählten Reiter.
const STRICH = {
  karte: { oben: [0, 3], links: [3, 0], rechts: [-3, 0], unten: [0, -3] },
  linie: { oben: [0, -3], links: [-3, 0], rechts: [3, 0], unten: [0, 3] },
  text: { oben: [0, -2], links: [-2, 0], rechts: [2, 0], unten: [0, 2] },
};

const sym = (n) => `<span class="material-icons gk-tab-icon" aria-hidden="true">${n}</span>`;
const WORTE = ["Eins", "Zwei", "Drei"];
/* Eine Reihe mit drei Reitern. Alle Reihen tragen dieselben data-tab-Schlüssel (k0..k2): ein Reiter darf nur die Fläche
   seiner eigenen Reihe finden. Bei „unten“ steht die Leiste nach den Flächen (Lesefolge: erst der Inhalt). */
const reihe = ({ id, stil, ausr = "oben", opt = "", n = 3, symbole = false, nurSymbol = false, zusatz = "", inhalt = (i) => `Inhalt ${id} ${i}`, tag = "button", aus = [], ariaAus = [], worte = WORTE }) => {
  const klassen = ["gk-tab-nav", `gk-tab-nav-${stil}`, ausr === "oben" ? "" : `gk-tab-nav-${ausr}`, opt].filter(Boolean).join(" ");
  const knoepfe = [...Array(n)].map((_, i) => {
    const an = i === 0 ? " gk-active" : "";
    const wort = nurSymbol ? "" : `<span>${worte[i]}</span>`;
    const label = nurSymbol ? ` aria-label="${worte[i]}" title="${worte[i]}"` : "";
    const aus_ = aus.includes(i) ? " disabled" : ariaAus.includes(i) ? ' aria-disabled="true"' : "";
    return `<button class="gk-tab-btn${an}" data-tab="k${i}"${label}${aus_}>${symbole || nurSymbol ? sym(["edit", "mail", "gavel"][i]) : ""}${wort}${i === 1 ? zusatz : ""}</button>`;
  }).join("");
  const flaechen = [...Array(n)].map((_, i) =>
    `<div class="gk-tab-panel${stil === "karte" ? " gk-tab-flaeche" : ""}${i === 0 ? " gk-active" : ""}" data-tab="k${i}">${inhalt(i)}</div>`).join("");
  const nav = `<div class="${klassen}" aria-label="Reihe ${id}">${knoepfe}</div>`;
  return `<div class="gk-tabs" id="${id}">${ausr === "unten" ? flaechen + nav : nav + flaechen}</div>`;
};

const MATRIX = STILE.flatMap((s) => AUSR.map((a) => reihe({ id: `m-${s}-${a}`, stil: s, ausr: a })));
const OPTIONEN = [
  reihe({ id: "o-sm", stil: "linie", opt: "gk-tab-nav-sm" }), reihe({ id: "o-std", stil: "linie" }), reihe({ id: "o-lg", stil: "linie", opt: "gk-tab-nav-lg" }),
  reihe({ id: "o-fluid", stil: "kapsel", opt: "gk-tab-nav-fluid" }), reihe({ id: "o-oben", stil: "linie", opt: "gk-tab-nav-icon-oben", symbole: true }),
  reihe({ id: "o-nur", stil: "kapsel", opt: "gk-tab-nav-nur-icon", nurSymbol: true }), reihe({ id: "o-neutral", stil: "kapsel", opt: "gk-tab-nav-neutral" }),
  reihe({ id: "o-aus", stil: "linie", aus: [1] }), reihe({ id: "o-aria", stil: "linie", ariaAus: [1] }), reihe({ id: "o-etikett", stil: "linie", zusatz: '<span class="gk-tab-badge gk-tab-badge-error">Fehler</span>' }),
  reihe({ id: "o-fest", stil: "linie", ausr: "unten", opt: "gk-tab-nav-fest gk-tab-nav-icon-oben", symbole: true, inhalt: () => '<div class="hoch"></div>' }),
];
// Seitenreihen mit langen Wörtern: die Spalte darf nur bis --gk-tab-seitenbreite wachsen, die Wörter brechen um.
const LANG = ["Ein sehr langer Reitertext, der in keine schmale Seitenspalte passt", "Noch ein langer Reitertext mit vielen Wörtern zum Umbrechen", "Kurz"];
const SEITEN = ["links", "rechts"].map((a) => reihe({ id: `s-lang-${a}`, stil: "linie", ausr: a, worte: LANG }));
// Die vier Kombinationen. Außen und innen: gleiche data-tab-Schlüssel, damit eine Verwechslung auffällt.
const KOMBI = {
  c1: `<nav class="gk-tab-nav gk-tab-nav-linie" id="c1-links" aria-label="Verweise"><a class="gk-tab-btn" href="#a" aria-current="page">Seite A</a><a class="gk-tab-btn" href="#b">Seite B</a></nav>
    <div class="gk-tabs" id="c1"><div class="gk-tab-nav gk-tab-nav-karte">${[0, 1].map((i) => `<button class="gk-tab-btn${i ? "" : " gk-active"}" data-tab="k${i}"><span>Außen ${i}</span></button>`).join("")}</div>
    <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="k0">${reihe({ id: "c1-innen", stil: "linie" })}</div>
    <div class="gk-tab-panel gk-tab-flaeche" data-tab="k1">außen 1</div></div>`,
  c2: `<div class="gk-tabs" id="c2"><div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-links">${[0, 1, 2].map((i) => `<button class="gk-tab-btn${i ? "" : " gk-active"}" data-tab="k${i}"><span>Seite ${i}</span></button>`).join("")}</div>
    <div class="gk-tab-panel gk-active" data-tab="k0">${reihe({ id: "c2-innen", stil: "linie" })}</div>
    <div class="gk-tab-panel" data-tab="k1">seite 1</div><div class="gk-tab-panel" data-tab="k2">seite 2</div></div>`,
  c3: `<div class="gk-tabs" id="c3"><div class="gk-tab-panel gk-active" data-tab="k0">${reihe({ id: "c3-innen", stil: "karte" })}</div><div class="gk-tab-panel" data-tab="k1">zweite Seite</div>
    <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-unten gk-tab-nav-fest gk-tab-nav-icon-oben">${[0, 1].map((i) => `<button class="gk-tab-btn${i ? "" : " gk-active"}" data-tab="k${i}">${sym("home")}<span>Unten ${i}</span></button>`).join("")}</div></div>`,
  c4: `<div class="gk-tabs" id="c4"><div class="gk-tab-nav gk-tab-nav-kapsel">${[0, 1].map((i) => `<button class="gk-tab-btn${i ? "" : " gk-active"}" data-tab="k${i}"><span>Bereich ${i}</span></button>`).join("")}</div>
    <div class="gk-tab-panel gk-active" data-tab="k0">${reihe({ id: "c4-innen", stil: "karte" })}</div><div class="gk-tab-panel" data-tab="k1">bereich 1</div></div>`,
};
// Die älteren Formen, wie sie in 1.102.0 aussehen: sie dürfen sich durch 1.103.0 nicht ändern.
const ALT = {
  "alt-plain": `<div class="gk-tabs" id="alt-plain"><div class="gk-tab-nav"><button class="gk-tab-btn gk-active" data-tab="a"><span>Eins</span></button><button class="gk-tab-btn" data-tab="b"><span>Zwei</span></button></div><div class="gk-tab-panel gk-active" data-tab="a">a</div><div class="gk-tab-panel" data-tab="b">b</div></div>`,
  "alt-fill": `<div class="gk-tabs" id="alt-fill"><div class="gk-tab-nav gk-tab-nav-fill"><button class="gk-tab-btn gk-active" data-tab="a">${sym("edit")}<span>Eins</span></button><button class="gk-tab-btn" data-tab="b"><span>Zwei</span></button></div><div class="gk-tab-panel gk-active" data-tab="a">a</div><div class="gk-tab-panel" data-tab="b">b</div></div>`,
  "alt-secondary": `<div class="gk-tabs" id="alt-secondary"><div class="gk-tab-nav gk-tab-nav-secondary"><button class="gk-tab-btn gk-active" data-tab="a"><span>Eins</span></button><button class="gk-tab-btn" data-tab="b"><span>Zwei</span></button></div><div class="gk-tab-panel gk-active" data-tab="a">a</div><div class="gk-tab-panel" data-tab="b">b</div></div>`,
  "alt-karte": `<div class="gk-tabs" id="alt-karte"><div class="gk-tab-nav gk-tab-nav-karte"><button class="gk-tab-btn gk-active" data-tab="a">${sym("edit")}<span>Eins</span><span class="gk-tab-count">3</span></button><button class="gk-tab-btn" data-tab="b"><span>Zwei</span><span class="gk-tab-badge gk-tab-badge-error">Fehler</span></button></div><div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">a</div><div class="gk-tab-panel gk-tab-flaeche" data-tab="b">b</div></div>`,
  "alt-karte-secondary": `<div class="gk-tabs" id="alt-karte-secondary"><div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary"><button class="gk-tab-btn gk-active" data-tab="a"><span>Eins</span></button><button class="gk-tab-btn" data-tab="b"><span>Zwei</span></button></div><div class="gk-tab-panel gk-active" data-tab="a">a</div><div class="gk-tab-panel" data-tab="b">b</div></div>`,
  "alt-legacy": `<div class="gk-tabs-nav" id="alt-legacy"><button class="gk-tab gk-tab-active">Eins</button><button class="gk-tab">Zwei</button></div>`,
  "alt-links": `<nav class="gk-tab-nav gk-tab-nav-karte" id="alt-links" aria-label="Seiten"><a class="gk-tab-btn" href="#a" aria-current="page"><span>Eins</span></a><a class="gk-tab-btn" href="#b"><span>Zwei</span></a></nav><div class="gk-tab-flaeche" id="alt-links-fl">Inhalt</div>`,
};
const SEITE = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${lies("css/gridkit.css")}</style><style>${lies("css/themes.css")}</style>
<style>.kasten { width: 640px; max-width: 100%; margin: 0 0 24px; } .hoch { height: 1600px; } *, *::before, *::after { transition: none !important; animation: none !important; }</style></head>
<body class="gk-root"><main class="gk-container">
${MATRIX.map((m) => `<div class="kasten">${m}</div>`).join("\n")}
<div class="kasten" id="opt">${OPTIONEN.map((o) => `<div class="kasten">${o}</div>`).join("\n")}</div>
${SEITEN.map((k) => `<div class="kasten">${k}</div>`).join("\n")}
${Object.values(KOMBI).map((k) => `<div class="kasten">${k}</div>`).join("\n")}
<div class="kasten" id="alt">${Object.values(ALT).join("\n")}</div>
<div class="hoch"></div>
</main>
<script>${lies("js/gridkit.js")}</script></body></html>`;
const datei = path.join(dir, "varianten.html");
fs.writeFileSync(datei, SEITE);

const results = [];
const check = (name, ok, why) => results.push({ name, ok: !!ok, why: ok ? "" : JSON.stringify(why).slice(0, 400) });

// Im Browser: Farbe → [r,g,b,a], wirksamer Hintergrund, Kontrast.
const HILFEN = `(() => {
  const c = document.createElement("canvas"); c.width = c.height = 1; const x = c.getContext("2d", { willReadFrequently: true });
  const rgba = (s) => { x.clearRect(0, 0, 1, 1); x.fillStyle = "#000"; x.fillStyle = s; x.fillRect(0, 0, 1, 1); const d = x.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const lum = (v) => { const f = (u) => { u /= 255; return u <= 0.03928 ? u / 12.92 : Math.pow((u + 0.055) / 1.055, 2.4); }; return 0.2126 * f(v[0]) + 0.7152 * f(v[1]) + 0.0722 * f(v[2]); };
  const ueber = (a, b) => [0, 1, 2].map((i) => a[i] * a[3] + b[i] * (1 - a[3])).concat([1]);
  window.__bg = (el) => { const kette = []; for (let e = el; e; e = e.parentElement) kette.push(rgba(getComputedStyle(e).backgroundColor)); let v = [255, 255, 255, 1]; for (let i = kette.length - 1; i >= 0; i--) if (kette[i][3] > 0) v = ueber(kette[i], v); return v; };
  window.__kontrast = (el, eigenerBg) => { const bg = window.__bg(el); const fg = rgba(getComputedStyle(el).color); const f = ueber(fg, bg); const a = lum(f), b = lum(bg); return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05); };
  window.__rgba = rgba;
})();`;

const SIG = ["display", "flexDirection", "flexWrap", "alignItems", "justifyContent", "gap", "paddingTop", "paddingRight", "paddingBottom", "paddingLeft", "marginTop", "marginRight", "marginBottom", "marginLeft",
  "borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth", "borderTopColor", "borderRightColor", "borderBottomColor", "borderLeftColor", "outlineStyle", "cursor", "borderTopLeftRadius", "borderTopRightRadius", "borderBottomRightRadius", "borderBottomLeftRadius",
  "backgroundColor", "color", "fontSize", "fontWeight", "boxShadow", "minHeight", "gridTemplateColumns", "position", "textDecorationLine", "opacity"];

(async () => {
  const browser = await chromium.launch();
  const fehler = [];
  try {
    const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 } });
    const page = await ctx.newPage();
    page.on("pageerror", (e) => fehler.push(e.message));
    const lade = async (breite, dunkel) => {
      await page.setViewportSize({ width: breite, height: 900 });
      await page.goto("file://" + datei);
      await page.evaluate(HILFEN);
      if (dunkel) await page.evaluate(() => { document.documentElement.setAttribute("data-gk-mode", "dark"); document.body.setAttribute("data-gk-mode", "dark"); });
      await page.waitForTimeout(150);
    };
    const eigene = (id) => page.evaluate((id) => {
      const w = document.getElementById(id);
      return { tabs: [...w.querySelectorAll(":scope > .gk-tab-nav > .gk-tab-btn")].map((b) => b.getAttribute("aria-selected")),
        offen: [...w.querySelectorAll(":scope > .gk-tab-panel")].map((p) => getComputedStyle(p).display !== "none"),
        rolle: w.querySelector(":scope > .gk-tab-nav").getAttribute("role"), ori: w.querySelector(":scope > .gk-tab-nav").getAttribute("aria-orientation"),
        rollen: [...w.querySelectorAll(":scope > .gk-tab-nav > .gk-tab-btn")].map((b) => b.getAttribute("role")).join(), flaechen: [...w.querySelectorAll(":scope > .gk-tab-panel")].map((p) => p.getAttribute("role")).join() };
    }, id);
    const geo = (id) => page.evaluate((id) => {
      const w = document.getElementById(id), n = w.querySelector(":scope > .gk-tab-nav"), t = [...n.querySelectorAll(":scope > .gk-tab-btn")], p = [...w.querySelectorAll(":scope > .gk-tab-panel")].find((x) => getComputedStyle(x).display !== "none");
      const r = (e) => { const b = e.getBoundingClientRect(); return { l: b.left, t: b.top, r: b.right, b: b.bottom, w: b.width, h: b.height }; };
      return { nav: r(n), tabs: t.map(r), panel: p ? r(p) : null, uebernav: n.scrollWidth - n.clientWidth, seite: document.documentElement.scrollWidth - innerWidth };
    }, id);
    const druecke = async (id, nr, taste) => { await page.focus(`#${id} > .gk-tab-nav > .gk-tab-btn:nth-child(${nr})`); await page.keyboard.press(taste); await page.waitForTimeout(40); };

    // ── 1. Die Matrix: 4 Stile × 4 Ausrichtungen, hell und dunkel ─────────────────────────────────
    for (const dunkel of [false, true]) {
      await lade(1100, dunkel);
      const modus = dunkel ? "dunkel" : "hell";
      for (const s of STILE) for (const a of AUSR) {
        const id = `m-${s}-${a}`, name = `${s} ${a} (${modus})`;
        const st = await eigene(id), g = await geo(id), senkrecht = a === "links" || a === "rechts";
        check(`${name}: Rollen tablist/tab/tabpanel, genau ein gewählter Reiter`, st.rolle === "tablist" && st.rollen === "tab,tab,tab" && st.flaechen === "tabpanel,tabpanel,tabpanel" && st.tabs.filter((v) => v === "true").length === 1 && st.tabs[0] === "true" && st.offen.join() === "true,false,false", st);
        check(`${name}: aria-orientation ${senkrecht ? "vertical" : "horizontal"}`, st.ori === (senkrecht ? "vertical" : "horizontal"), st);
        const t = g.tabs;
        const lage = senkrecht ? t[1].t > t[0].b - 2 && Math.abs(t[1].l - t[0].l) <= 8 && (a === "links" ? g.panel.l >= g.nav.r - 2 : g.panel.r <= g.nav.l + 2)
          : t[1].l > t[0].r - 2 && Math.abs(t[1].t - t[0].t) <= 8 && (a === "unten" ? g.nav.t >= g.panel.b - 3 : g.panel.t >= g.nav.b - 3);
        check(`${name}: Lage — ${a === "links" ? "Reiter untereinander, Fläche rechts daneben" : a === "rechts" ? "Reiter untereinander, Fläche links daneben" : a === "unten" ? "Reiter nebeneinander unter der Fläche" : "Reiter nebeneinander über der Fläche"}`, lage, g);
        check(`${name}: kein Überlauf (Reihe, Seite)`, g.uebernav <= 1 && g.seite <= 1, g);
        if (s === "karte" && senkrecht) check(`${name}: die Fläche reicht mindestens so weit wie die Reiter (gestreckt, ${Math.round(g.panel.h)} von ${Math.round(g.nav.h)} px)`, g.panel.h >= g.nav.h - 1.5, g);
        if (!dunkel || s !== "karte") {
          // Pfeiltasten: Achse der Reihe wirkt, die andere nicht; Home/End.
          const weiter = senkrecht ? "ArrowDown" : "ArrowRight", zurueck = senkrecht ? "ArrowUp" : "ArrowLeft", quer = senkrecht ? "ArrowRight" : "ArrowDown";
          await druecke(id, 1, quer);
          const q = await eigene(id);
          await druecke(id, 1, weiter);
          const w = await eigene(id);
          await druecke(id, 2, "End");
          const e = await eigene(id);
          await druecke(id, 3, zurueck);
          const z = await eigene(id);
          await druecke(id, 2, "Home");
          const h = await eigene(id);
          check(`${name}: Pfeil ${weiter} wählt den nächsten und zeigt nur dessen Fläche, die Querachse nichts, End/Home/${zurueck} stimmen`,
            q.tabs[0] === "true" && w.tabs.join() === "false,true,false" && w.offen.join() === "false,true,false" && e.tabs[2] === "true" && z.tabs[1] === "true" && h.tabs[0] === "true" && h.offen.join() === "true,false,false", { q, w, e, z, h });
        }
        // Kontrast: gewählter und ruhender Reiter ≥ 4,5:1 (nach dem Tastenspiel steht der erste wieder da).
        const k = await page.evaluate((id) => { const b = [...document.querySelectorAll(`#${id} > .gk-tab-nav > .gk-tab-btn`)]; return { an: window.__kontrast(b[0]), aus: window.__kontrast(b[1]) }; }, id);
        check(`${name}: Kontrast gewählter ${k.an.toFixed(2)}:1, ruhender ${k.aus.toFixed(2)}:1 (je ≥ 4,5:1)`, k.an >= 4.5 && k.aus >= 4.5, k);
        // Die Seite des Strichs (oder die Füllung bei der Kapsel).
        const sh = await page.evaluate((id) => { const b = document.querySelector(`#${id} > .gk-tab-nav > .gk-tab-btn.gk-active`); const cs = getComputedStyle(b); return { schatten: cs.boxShadow, bg: window.__rgba(cs.backgroundColor), bgAus: window.__rgba(getComputedStyle(document.querySelector(`#${id} > .gk-tab-nav > .gk-tab-btn:not(.gk-active)`)).backgroundColor) }; }, id);
        if (s === "kapsel") check(`${name}: der gewählte Reiter ist gefüllt, die anderen nicht`, sh.bg[3] > 0.9 && sh.bgAus[3] < 0.2 && sh.schatten === "none", sh);
        else {
          const m = /(-?[\d.]+)px (-?[\d.]+)px/.exec(sh.schatten), [ex, ey] = STRICH[s][a];
          check(`${name}: der Strich liegt auf der richtigen Seite (Schatten ${ex}px ${ey}px innen)`, m && Math.abs(+m[1] - ex) < 0.01 && Math.abs(+m[2] - ey) < 0.01 && /inset/.test(sh.schatten), sh);
          // Die Ecken an der Strichseite sind gerade (sonst biegt sich der Strich an den Enden).
          if (s !== "karte") {
            const ec = await page.evaluate((id) => { const c = getComputedStyle(document.querySelector(`#${id} > .gk-tab-nav > .gk-tab-btn.gk-active`)); return { tl: c.borderTopLeftRadius, tr: c.borderTopRightRadius, br: c.borderBottomRightRadius, bl: c.borderBottomLeftRadius }; }, id);
            const gerade = ey < 0 ? [ec.bl, ec.br] : ey > 0 ? [ec.tl, ec.tr] : ex < 0 ? [ec.tr, ec.br] : [ec.tl, ec.bl];
            const rund = ey < 0 ? [ec.tl, ec.tr] : ey > 0 ? [ec.bl, ec.br] : ex < 0 ? [ec.tl, ec.bl] : [ec.tr, ec.br];
            check(`${name}: die Ecken an der Strichseite sind gerade, die gegenüberliegenden rund`, gerade.every((v) => parseFloat(v) === 0) && rund.every((v) => parseFloat(v) > 0), ec);
          }
        }
      }
    }

    // ── 2. Handy: die Seitenleisten fallen auf eine waagerechte Reihe zurück, 44 px Trefferfläche ──────
    for (const dunkel of [false, true]) {
      await lade(390, dunkel);
      const modus = dunkel ? "dunkel" : "hell";
      for (const s of STILE) for (const a of AUSR) {
        const id = `m-${s}-${a}`, st = await eigene(id), g = await geo(id);
        const hoch = Math.min(...g.tabs.map((t) => t.h));
        check(`Handy 390 px, ${s} ${a} (${modus}): waagerecht (aria-orientation), Fläche unter der Reihe${a === "unten" ? " ... bzw. die Leiste unter der Fläche" : ""}, Tabs ≥ 44 px hoch, kein Überlauf`,
          st.ori === "horizontal" && (a === "unten" ? g.nav.t >= g.panel.b - 3 : g.panel.t >= g.nav.b - 3) && hoch >= 43.5 && g.seite <= 1, { st, hoch, g });
      }
      if (!dunkel) {
        const id = "m-linie-links";
        await druecke(id, 1, "ArrowRight");
        const st = await eigene(id);
        check("Handy 390 px: in der zurückgefallenen Seitenleiste wirkt wieder Pfeil rechts", st.tabs[1] === "true", st);
      }
    }

    // ── 3. Optionen ─────────────────────────────────────────────────────────────────────────────
    await lade(1100, false);
    const hoehe = (id) => page.evaluate((id) => document.querySelector(`#${id} > .gk-tab-nav > .gk-tab-btn`).getBoundingClientRect().height, id);
    const [hs, hm, hl] = [await hoehe("o-sm"), await hoehe("o-std"), await hoehe("o-lg")];
    check(`Größe: -sm (${hs}px) < Standard (${hm}px) < -lg (${hl}px)`, hs < hm && hm < hl, { hs, hm, hl });
    const fl = await geo("o-fluid");
    check("volle Breite (-fluid): die Reiter teilen die Breite der Reihe gleichmäßig", Math.abs(fl.tabs.reduce((a, t) => a + t.w, 0) - fl.nav.w) <= 20 && Math.abs(fl.tabs[0].w - fl.tabs[2].w) <= 2, fl);
    const io = await page.evaluate(() => { const b = document.querySelector("#o-oben > .gk-tab-nav > .gk-tab-btn"), i = b.querySelector(".gk-tab-icon").getBoundingClientRect(), t = b.querySelector("span:not(.gk-tab-icon)").getBoundingClientRect(); return { iconT: i.top, textT: t.top, iconX: i.left + i.width / 2, textX: t.left + t.width / 2 }; });
    check("Symbol über dem Text (-icon-oben): Symbol höher, beide mittig", io.iconT < io.textT - 4 && Math.abs(io.iconX - io.textX) <= 3, io);
    const ni = await page.evaluate(() => [...document.querySelectorAll("#o-nur > .gk-tab-nav > .gk-tab-btn")].map((b) => { const r = b.getBoundingClientRect(); return { w: r.width, h: r.height, label: b.getAttribute("aria-label"), titel: b.getAttribute("title"), sichtbarerText: [...b.querySelectorAll("span:not(.gk-tab-icon)")].some((s) => s.getBoundingClientRect().width > 1) }; }));
    check("nur Symbol (-nur-icon): jeder Reiter hat aria-label und title, ist ≥ 44 px groß und zeigt keinen Text", ni.every((b) => b.label && b.titel && b.w >= 43.5 && b.h >= 43.5 && !b.sichtbarerText), ni);
    const nk = await page.evaluate(() => { const g = (id) => window.__rgba(getComputedStyle(document.querySelector(`#${id} > .gk-tab-nav > .gk-tab-btn.gk-active`)).backgroundColor); return { neutral: g("o-neutral"), primaer: g("m-kapsel-oben"), k: window.__kontrast(document.querySelector("#o-neutral > .gk-tab-nav > .gk-tab-btn.gk-active")) }; });
    check(`neutral (-neutral): der gewählte Reiter hat die Textfarbe statt der Hauptfarbe als Füllung, Kontrast ${nk.k.toFixed(2)}:1`, nk.neutral.join() !== nk.primaer.join() && nk.k >= 4.5, nk);
    await druecke("o-aus", 1, "ArrowRight");
    const au = await eigene("o-aus");
    const auf = await page.evaluate(() => { const b = document.querySelector("#o-aus > .gk-tab-nav > .gk-tab-btn:nth-child(2)"); return { aus: b.disabled, deckkraft: +getComputedStyle(b).opacity, cursor: getComputedStyle(b).cursor }; });
    check("deaktiviert: der Pfeil überspringt den gesperrten Reiter, er sieht gesperrt aus (Deckkraft, Zeiger)", au.tabs.join() === "false,false,true" && auf.aus && (auf.deckkraft < 1) && auf.cursor === "not-allowed", { au, auf });
    await page.evaluate(() => document.querySelector("#o-aria > .gk-tab-nav > .gk-tab-btn:nth-child(2)").click());
    const aa = await eigene("o-aria");
    check("deaktiviert (aria-disabled): ein Klick auf den gesperrten Reiter wechselt weder Reiter noch Fläche", aa.tabs.join() === "true,false,false" && aa.offen.join() === "true,false,false", aa);
    const sl = [await geo("s-lang-links"), await geo("s-lang-rechts")];
    check("Seitenreihe: die Spalte wächst mit den Wörtern nur bis --gk-tab-seitenbreite (14rem = 224 px), lange Wörter brechen um", sl.every((g) => g.nav.w <= 225 && g.nav.w >= 150 && g.tabs[0].h > 50 && g.seite <= 1), sl);
    const et = await page.evaluate(() => window.__kontrast(document.querySelector("#o-etikett .gk-tab-badge-error")));
    check(`Etikett im Linienreiter: Fehler-Etikett ${et.toFixed(2)}:1 (≥ 4,5:1)`, et >= 4.5, et);

    // ── 4. Die untere feste Leiste ──────────────────────────────────────────────────────────────
    await lade(390, false);
    const fest = await page.evaluate(() => { const n = document.querySelector("#o-fest > .gk-tab-nav"), cs = getComputedStyle(n); const b = n.querySelector(".gk-tab-btn"); return { pos: cs.position, bottom: cs.bottom, richtung: getComputedStyle(b).flexDirection }; });
    await page.evaluate(() => document.querySelector("#o-fest").scrollIntoView({ block: "start" }));
    await page.evaluate(() => scrollBy(0, -60));
    const unten = await page.evaluate(() => { const n = document.querySelector("#o-fest > .gk-tab-nav").getBoundingClientRect(), w = document.querySelector("#o-fest").getBoundingClientRect(); return { navB: n.bottom, fenster: innerHeight, wrapB: w.bottom, wrapT: w.top }; });
    check("feste Leiste (-fest): sticky am unteren Rand, Symbol über dem Text; im Fenster klebt sie am unteren Rand, solange ihr Kasten sichtbar ist", fest.pos === "sticky" && fest.bottom === "0px" && fest.richtung === "column" && Math.abs(unten.navB - Math.min(unten.fenster, unten.wrapB)) <= 2, { fest, unten });

    // ── 5. Die vier Kombinationen: nur die eigene Reihe wechselt ────────────────────────────────
    await lade(1100, false);
    const innen = (id) => eigene(id);
    // c1: Karteireiter außen + Linienreiter innen (der Fall der Landingpage) + eine Verweisreihe
    await page.click("#c1-innen > .gk-tab-nav > .gk-tab-btn:nth-child(2)");
    let a = await eigene("c1"), b = await innen("c1-innen");
    check("Kombination Karte außen + Linie innen: ein Klick innen wechselt nur die innere Reihe", a.tabs.join() === "true,false" && a.offen.join() === "true,false" && b.tabs.join() === "false,true,false" && b.offen.join() === "false,true,false", { a, b });
    await druecke("c1-innen", 2, "ArrowRight");
    await page.click("#c1 > .gk-tab-nav > .gk-tab-btn:nth-child(2)");
    a = await eigene("c1"); b = await innen("c1-innen");
    check("Kombination Karte außen + Linie innen: Pfeil innen lässt die äußere Reihe, ein Klick außen lässt die innere", a.tabs.join() === "false,true" && a.offen.join() === "false,true" && b.tabs.join() === "false,false,true", { a, b });
    await page.click("#c1 > .gk-tab-nav > .gk-tab-btn:nth-child(1)");
    const vr = await page.evaluate(() => ({ aktuell: document.querySelector("#c1-links .gk-tab-btn[aria-current]").textContent, rolle: document.getElementById("c1-links").getAttribute("role"), tabindex: [...document.querySelectorAll("#c1-links .gk-tab-btn")].map((x) => x.tabIndex).join() }));
    check("Verweisreihe (<a>) neben Schaltreihen (<button>): aria-current bleibt, keine Tab-Rolle, kein Roving-Tabindex", vr.aktuell === "Seite A" && vr.rolle !== "tablist" && vr.tabindex === "0,0", vr);
    // c2: Seitenleiste links + Linienreiter im Fenster der Fläche
    await page.click("#c2-innen > .gk-tab-nav > .gk-tab-btn:nth-child(3)");
    await druecke("c2", 1, "ArrowDown");
    a = await eigene("c2"); b = await innen("c2-innen");
    check("Kombination Seitenleiste links + Linie innen: Pfeil runter außen, die innere Reihe bleibt auf ihrem Reiter (Fläche der Seite 0 ist jetzt zu, ihre Reihe unberührt)", a.tabs.join() === "false,true,false" && a.offen.join() === "false,true,false" && b.tabs.join() === "false,false,true", { a, b });
    await druecke("c2", 2, "ArrowUp");
    b = await innen("c2-innen");
    const gk2 = await geo("c2");
    check("Kombination Seitenleiste links + Linie innen: zurück auf Seite 0, die innere Reihe steht noch; innen waagerecht, außen senkrecht", b.tabs.join() === "false,false,true" && gk2.tabs[1].t > gk2.tabs[0].b - 2 && (await innen("c2-innen")).ori === "horizontal" && (await eigene("c2")).ori === "vertical", { b, gk2 });
    // c3: untere Leiste + oberer Reiter in der Seite
    await page.click("#c3-innen > .gk-tab-nav > .gk-tab-btn:nth-child(2)");
    await page.click("#c3 > .gk-tab-nav > .gk-tab-btn:nth-child(2)");
    a = await eigene("c3"); b = await innen("c3-innen");
    check("Kombination untere Leiste + oberer Reiter: die untere Leiste wechselt nur die Seiten, der obere Reiter behält seinen Stand", a.tabs.join() === "false,true" && a.offen.join() === "false,true" && b.tabs.join() === "false,true,false", { a, b });
    // c4: Kapsel-Reihe über einem Karteireiter
    await page.click("#c4-innen > .gk-tab-nav > .gk-tab-btn:nth-child(3)");
    await druecke("c4", 1, "ArrowRight");
    a = await eigene("c4"); b = await innen("c4-innen");
    check("Kombination Kapsel über Karteireiter: Pfeil rechts auf der Kapsel wechselt nur den Bereich, der Karteireiter darunter behält seinen Stand", a.tabs.join() === "false,true" && a.offen.join() === "false,true" && b.tabs.join() === "false,false,true", { a, b });
    // keine gegenseitige Verformung: eine innere Reihe sieht in jeder Kombination gleich aus wie allein
    const gleich = await page.evaluate(() => { const p = (sel) => { const e = document.querySelector(sel), c = getComputedStyle(e); return [c.paddingTop, c.paddingLeft, c.borderBottomWidth, c.fontSize, c.borderTopLeftRadius].join("|"); }; return { frei: p("#m-linie-oben > .gk-tab-nav > .gk-tab-btn"), c1: p("#c1-innen > .gk-tab-nav > .gk-tab-btn"), c2: p("#c2-innen > .gk-tab-nav > .gk-tab-btn") }; });
    check("Kombination: die Linienreihe sieht in Karte und in der Seitenleiste aus wie allein (kein Überschreiben)", gleich.frei === gleich.c1 && gleich.frei === gleich.c2, gleich);

    // ── 6. Rückwärtsverträglichkeit: die älteren Formen bleiben, wie sie in 1.102.0 waren ─────────────
    const sig = {};
    for (const [breite, dunkel] of [[1100, false], [390, false], [1100, true]]) {
      await lade(breite, dunkel);
      sig[`${breite}-${dunkel ? "dunkel" : "hell"}`] = await page.evaluate((arg) => {
        const [ids, props] = arg, aus = {};
        for (const id of ids) {
          const w = document.getElementById(id), nav = w.matches(".gk-tab-nav, .gk-tabs-nav") ? w : w.querySelector(".gk-tab-nav");
          const teile = { nav, ruhend: nav.querySelector(".gk-tab-btn:not(.gk-active):not([aria-current]), .gk-tab:not(.gk-tab-active)"), aktiv: nav.querySelector(".gk-tab-btn.gk-active, .gk-tab-btn[aria-current], .gk-tab.gk-tab-active"), flaeche: document.getElementById(id + "-fl") || w.querySelector(".gk-tab-flaeche") };
          aus[id] = {};
          for (const [k, el] of Object.entries(teile)) if (el) { const cs = getComputedStyle(el); aus[id][k] = props.map((p) => cs[p]).join("|"); }
        }
        return aus;
      }, [Object.keys(ALT), SIG]);
    }
    if (process.env.GK_BESTAND_SCHREIBEN) { fs.writeFileSync(BESTAND, JSON.stringify({ props: SIG, signatur: sig }, null, 0) + "\n"); console.log("Bestand geschrieben:", BESTAND); }
    else {
      const alt = JSON.parse(fs.readFileSync(BESTAND, "utf8")), abweichend = [];
      for (const [fall, ids] of Object.entries(alt.signatur)) for (const [id, teile] of Object.entries(ids)) for (const [teil, wert] of Object.entries(teile)) {
        if (sig[fall]?.[id]?.[teil] !== wert) {
          const a1 = wert.split("|"), b1 = (sig[fall]?.[id]?.[teil] || "").split("|");
          abweichend.push(`${fall} ${id}/${teil}: ` + a1.map((v, i) => v !== b1[i] ? `${alt.props[i]} ${v} → ${b1[i]}` : null).filter(Boolean).join("; "));
        }
      }
      check(`Rückwärtsverträglichkeit: ${Object.keys(ALT).length} ältere Formen (Reihe, Reiter, gewählter Reiter, Fläche) × 3 Zustände unverändert gegenüber 1.102.0`, !abweichend.length, abweichend.slice(0, 4));
    }
    check("keine Skriptfehler", !fehler.length, fehler);
  } finally {
    await browser.close();
  }
  let rot = 0;
  for (const r of results) { if (!r.ok) rot++; console.log(`${r.ok ? "ok  " : "FAIL"}  ${r.name}${r.ok ? "" : "  " + r.why}`); }
  console.log(`\n${results.length - rot} von ${results.length} gehalten`);
  process.exit(rot ? 1 : 0);
})().catch((e) => { console.error("Abbruch:", e.message.split("\n")[0]); process.exit(2); });
