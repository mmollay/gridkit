#!/usr/bin/env node
/**
 * Do the alias variables still mean what their role means — in every theme, in
 * both modes?
 *
 * GridKit carries two names for the same colour: a role (`--gk-on-surface-variant`)
 * and an older alias (`--gk-text-muted`). In the light block the alias IS the role
 * (`--gk-text-muted: var(--gk-on-surface-variant)`), so the two can never drift.
 * In the dark block the alias was written as a literal — and `themes.css` moves the
 * role underneath it. The two names then point at two different colours, and which
 * one a component gets depends on which name its author happened to type.
 *
 * `tests/contrast.test.php` cannot see this: it matches strings in the stylesheet,
 * and a variable chain across two files only resolves in a browser.
 *
 *     node ci/farben.js                        # needs the "playwright" package
 *     GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/farben.js
 *
 * Exit code 0 when every asserted alias agrees with its role and muted text still
 * reads. Die vier Oberflächen-Aliase sind davon ausgenommen und stehen unter
 * ZURUECKGESTELLT — sie werden gemessen und gemeldet, aber nicht beanstandet.
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
const dir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-farben-"));
process.on("exit", () => fs.rmSync(dir, { recursive: true, force: true }));
process.on("SIGINT", () => process.exit(130));
for (const f of ["gridkit.css", "themes.css"]) fs.copyFileSync(path.join(WURZEL, "css", f), path.join(dir, f));
fs.writeFileSync(path.join(dir, "probe.html"), `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="gridkit.css"><link rel="stylesheet" href="themes.css"></head>
<body class="gk-root"><span id="p"></span></body></html>`);

/** Alias → Rolle. Im hellen Block steht genau diese Zuordnung als var()-Kette. */
const PAARE = [
  ["--gk-text",       "--gk-on-surface"],
  ["--gk-text-muted", "--gk-on-surface-variant"],
  ["--gk-text-subtle","--gk-outline"],
  ["--gk-border",     "--gk-outline-variant"],
];

/**
 * Bewusst zurückgestellt, nicht vergessen: in themes.css sind --gk-surface und
 * --gk-surface-container im dunklen Schema DIESELBE Farbe (#1e293b, Zeile 140 und
 * 144), in gridkit.css eine Stufe auseinander (#0d1117 / #161b22). Würden diese
 * vier ihrer Rolle folgen, fielen --gk-bg und --gk-bg-muted zusammen und jede
 * gedämpfte Fläche auf einer glatten verlöre ihre Kante. Das ist eine
 * Palettenentscheidung für alle SSI-Systeme. Bis dahin wird hier gemessen und
 * berichtet, aber nicht beanstandet.
 */
const ZURUECKGESTELLT = [
  ["--gk-bg",        "--gk-surface"],
  ["--gk-bg-muted",  "--gk-surface-container"],
  ["--gk-bg-subtle", "--gk-surface-container-low"],
  ["--gk-bg-hover",  "--gk-surface-container-high"],
];
const THEMEN = ["", "indigo", "ocean", "forest", "rose", "amber", "slate"];
/* Der dunkle Block gilt für [data-gk-mode="dark"] UND .gk-dark. Gemessen werden
   beide: themes.css führt in einem seiner Blöcke nur die eine Schreibweise mit,
   und was nicht gemessen wird, ist nicht geprüft. */
const MODI = ["light", "dark", "dark-klasse"];


/* ────────────────────────────────────────────────────────────────────────
 * Zweiter Abschnitt: laufen unter einem Thema zwei Farbsätze nebeneinander?
 *
 * Die Alias-Prüfung oben sieht nur Variablen. Die Dunkelmodus-Regeln der
 * Komponenten setzten daneben feste Werte aus GRIDKits eigener Palette. Unter
 * einem Thema gehören die zu keinem Themenwert — die Kopfzeile wechselte dadurch
 * beim Festkleben die Farbe, und das Suchfeld trug eine andere Grauschrift als das
 * Kennzahlen-Etikett daneben.
 *
 * Gemessen wird auf einer eigenen Seite mit GENAU den Bauteilen, deren Dunkelregeln
 * eine Farbe setzen — nicht auf der allgemeinen Komponenten-Prüfseite: die enthält
 * dreizehn davon gar nicht, und „nichts gefunden" hiesse dort nur „nichts gesucht".
 * Jede Probe muss gefunden werden, sonst ist der Fall rot.
 * ──────────────────────────────────────────────────────────────────────── */
const FREMDE_PALETTE = {
  "13,17,23": "#0d1117", "22,27,34": "#161b22", "33,38,45": "#21262d",
  "45,51,59": "#2d333b", "139,148,158": "#8b949e", "230,237,243": "#e6edf3",
  "72,79,88": "#484f58", "28,33,40": "#1c2128", "1,4,9": "#010409",
  "38,45,56": "#262d38", "58,66,80": "#3a4250", "165,180,252": "#a5b4fc",
};

/** Je Bauteil: Markup und der Wähler, dessen Textfarbe geprüft wird. */
const PROBEN = [
  [".gk-filter",              '<select class="gk-filter"><option>x</option></select>', "select.gk-filter"],
  [".gk-card-footer",         '<div class="gk-card"><div class="gk-card-footer">x</div></div>', ".gk-card-footer"],
  [".gk-upload-icon",         '<div class="gk-upload-zone"><span class="gk-upload-icon">x</span></div>', ".gk-upload-icon"],
  [".gk-upload-hint",         '<div class="gk-upload-zone"><div class="gk-upload-hint">x</div></div>', ".gk-upload-hint"],
  [".gk-field-hint",          '<div class="gk-field-hint">x</div>', ".gk-field-hint"],
  [".gk-breadcrumb a",        '<nav class="gk-breadcrumb"><a href="#">x</a></nav>', ".gk-breadcrumb a"],
  [".gk-breadcrumb-current",  '<nav class="gk-breadcrumb"><span class="gk-breadcrumb-current">x</span></nav>', ".gk-breadcrumb-current"],
  [".gk-accordion-trigger",   '<div class="gk-accordion"><button class="gk-accordion-trigger">x</button></div>', ".gk-accordion-trigger"],
  [".gk-accordion-body",      '<div class="gk-accordion"><div class="gk-accordion-body">x</div></div>', ".gk-accordion-body"],
  [".gk-label-text",          '<label class="gk-label-text">x</label>', ".gk-label-text"],
  [".gk-message",             '<div class="gk-message">x</div>', ".gk-message"],
  [".gk-btn-tonal neutral",   '<button class="gk-btn gk-btn-tonal gk-btn-neutral">x</button>', ".gk-btn-tonal"],
  [".gk-table thead th",      '<table class="gk-table"><thead><tr><th>x</th></tr></thead></table>', ".gk-table thead th"],
  [".gk-table-definition td", '<table class="gk-table gk-table-definition"><tbody><tr><td>x</td><td>y</td></tr></tbody></table>', ".gk-table-definition td:first-child"],
  ["input",                   '<input value="x">', "input"],
  [".gk-avatar",              '<div class="gk-avatar">AB</div>', ".gk-avatar"],
  [".gk-input",               '<input class="gk-input" value="x">', "input.gk-input"],
];

async function komponentenMessen(browser, mitThemes, schreibweise) {
  const uDir = fs.mkdtempSync(path.join(os.tmpdir(), "gk-farben-k-"));
  try {
    for (const f of ["gridkit.css", "themes.css"]) fs.copyFileSync(path.join(WURZEL, "css", f), path.join(uDir, f));
    const rumpf = PROBEN.map(([name, html]) => `<div data-probe="${name}">${html}</div>`).join("\n")
      + '<div class="gk-header" id="kopf">K</div><div class="gk-header gk-header-sticky" id="klebt">K</div>'
      + '<input class="gk-input" id="ph" placeholder="Platzhalter">'
      + '<input class="gk-input" id="ph-ro" placeholder="Platzhalter" readonly>'
      + '<input class="gk-search" id="ph-suche" placeholder="Suchen">';
    fs.writeFileSync(path.join(uDir, "probe.html"), `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="gridkit.css">${mitThemes ? '<link rel="stylesheet" href="themes.css">' : ""}</head>
<body class="gk-root">${rumpf}</body></html>`);

    const page = await browser.newPage({ viewport: { width: 1280, height: 1400 } });
    await page.goto("file://" + path.join(uDir, "probe.html"));
    const fund = await page.evaluate(([proben, fremd, mitThemes, schreibweise]) => {
      document.body.setAttribute("data-gk-theme", "indigo");
      if (schreibweise === "klasse") document.body.classList.add("gk-dark");
      else document.body.setAttribute("data-gk-mode", schreibweise === "hell" ? "light" : "dark");
      const c = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
      const rgba = (f) => { c.clearRect(0,0,1,1); c.fillStyle = "#000"; c.fillStyle = f; c.fillRect(0,0,1,1);
        const d = c.getImageData(0,0,1,1).data; return [d[0], d[1], d[2], d[3] / 255]; };
      const schluessel = (v) => v.slice(0, 3).join(",");
      const ueber = (vorn, hinten) => [0,1,2].map((i) => Math.round(vorn[i] * vorn[3] + hinten[i] * (1 - vorn[3])));
      const lum = (v) => { const f = (x) => { x /= 255; return x <= 0.03928 ? x/12.92 : Math.pow((x+0.055)/1.055, 2.4); };
        return 0.2126*f(v[0]) + 0.7152*f(v[1]) + 0.0722*f(v[2]); };
      const kontrast = (a, b) => { const la = lum(a), lb = lum(b); return (Math.max(la,lb)+0.05)/(Math.min(la,lb)+0.05); };

      /* Nachweisen, dass dunkel gemessen wird — und, wenn themes.css dabei ist,
         dass es auch greift. Ohne diesen Beweis wäre eine leere Trefferliste
         nur die Aussage, dass nichts gesucht wurde. */
      const sonde = document.createElement("span"); document.body.appendChild(sonde);
      const aufloesen = (v) => { sonde.style.color = `var(${v})`; return rgba(getComputedStyle(sonde).color); };
      const grund = schluessel(aufloesen("--gk-surface"));
      const erwartet = schreibweise === "hell" ? "255,255,255" : (mitThemes ? "30,41,59" : "13,17,23");
      if (grund !== erwartet) { sonde.remove(); return { fehler: `Grund ist rgb(${grund}), erwartet ${erwartet}` }; }
      /* Und greift das THEMA auch? themes.css setzt die dunklen Oberflächen ohne
         Themenbedingung — der Grund oben beweist nur, dass die Datei geladen ist.
         Erst ein Akzent, der sich zwischen zwei Themen unterscheidet, beweist das
         Attribut. */
      let themaGreift = true;
      if (mitThemes) {
        const a = schluessel(aufloesen("--gk-primary"));
        document.body.setAttribute("data-gk-theme", "forest");
        const b = schluessel(aufloesen("--gk-primary"));
        document.body.setAttribute("data-gk-theme", "indigo");
        themaGreift = a !== b;
      }
      if (!themaGreift) { sonde.remove(); return { fehler: "data-gk-theme wirkt nicht — indigo und forest ergeben denselben Akzent" }; }

      /* Die Rollenwerte dieser Konfiguration. Ohne themes.css SIND die Rollen genau
         die Werte, die früher als Literale dastanden — dort wäre „fremde Palette"
         ein Fehlalarm. Fremd ist eine Farbe nur, wenn sie zu KEINER Rolle gehört. */
      const rollen = new Set(["--gk-on-surface", "--gk-on-surface-variant", "--gk-outline",
        "--gk-primary", "--gk-surface", "--gk-surface-container", "--gk-surface-container-low",
        "--gk-surface-container-high", "--gk-outline-variant"].map((v) => schluessel(aufloesen(v))));
      sonde.remove();

      const fehlend = [], fremdfarben = [];
      for (const [name, , wahl] of proben) {
        const el = document.querySelector(`[data-probe="${name}"] ${wahl}`) || document.querySelector(`[data-probe="${name}"]`);
        if (!el) { fehlend.push(name); continue; }
        const k = schluessel(rgba(getComputedStyle(el).color));
        if (fremd[k] && !rollen.has(k)) fremdfarben.push(`${name} · color = ${fremd[k]}`);
      }

      /* Kopfzeile: festgeklebt muss DIESELBE FARBE tragen wie nicht festgeklebt.
         Verglichen werden die Farbkanäle, nicht das zusammengerechnete Bild — die
         festgeklebte Fassung ist absichtlich durchscheinend (backdrop-filter), und
         was dahinter durchkommt, hängt vom Inhalt ab und ist kein Fehler. Vor der
         Korrektur trug sie rgb(13,17,23) gegen eine Kopfzeile rgb(30,41,59). */
      const farbe = (id) => rgba(getComputedStyle(document.getElementById(id)).backgroundColor);
      const k1 = farbe("kopf"), k2 = farbe("klebt");
      const kopf = { normal: schluessel(k1), klebend: schluessel(k2),
                     durchsicht: k2[3].toFixed(2) };

      /* Platzhalter: halbdurchsichtige Schrift über dem eigenen Feldgrund — und der
         Feldgrund wechselt. Fokussiert steht .gk-input auf var(--gk-surface), nur
         lesbar auf var(--gk-surface-container-low). Gelesen wird der Platzhalter
         gerade dann, wenn das Feld fokussiert und noch leer ist; 75 % der Rolle
         hätten genau dort 3,90:1 ergeben. */
      const messenPh = (id) => {
        const feld = document.getElementById(id);
        const g = rgba(getComputedStyle(feld).backgroundColor);
        const s = rgba(getComputedStyle(feld, "::placeholder").color);
        const w = ueber(s, g);
        return { wert: `rgb(${w})`, grund: `rgb(${g.slice(0,3)})`, kontrast: kontrast(w, g) };
      };
      const platzhalter = { ruhend: messenPh("ph"), nurLesbar: messenPh("ph-ro"), suche: messenPh("ph-suche") };
      document.getElementById("ph").focus();
      platzhalter.fokussiert = messenPh("ph");
      document.getElementById("ph").blur();

      return { geprueft: proben.length - fehlend.length, fehlend, fremdfarben, kopf, platzhalter };
    }, [PROBEN, FREMDE_PALETTE, mitThemes, schreibweise]);
    await page.close();
    return fund;
  } finally {
    fs.rmSync(uDir, { recursive: true, force: true });
  }
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto("file://" + path.join(dir, "probe.html"));

  const messen = await page.evaluate(([paare, zurueck, themen, modi]) => {
    /* Über ein Canvas, nicht über die Zeichenkette: getComputedStyle liefert je
       nach Farbraum rgb(), color(srgb …) oder oklch() — die Zahlen dahinter sind
       das, worauf es ankommt. */
    const leinwand = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
    const zuRgb = (farbe) => {
      leinwand.clearRect(0, 0, 1, 1);
      leinwand.fillStyle = "#000";
      leinwand.fillStyle = farbe;
      leinwand.fillRect(0, 0, 1, 1);
      const d = leinwand.getImageData(0, 0, 1, 1).data;
      return [d[0], d[1], d[2], d[3] / 255];
    };
    const aufloesen = (variable) => {
      const el = document.getElementById("p");
      el.style.color = `var(${variable})`;
      return zuRgb(getComputedStyle(el).color);
    };
    const leuchtkraft = ([r, g, b]) => {
      const k = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
      return 0.2126 * k(r) + 0.7152 * k(g) + 0.0722 * k(b);
    };
    const verhaeltnis = (a, b) => {
      const la = leuchtkraft(a), lb = leuchtkraft(b);
      return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    };

    const ergebnis = [];
    for (const thema of themen) {
      for (const modus of modi) {
        thema ? document.body.setAttribute("data-gk-theme", thema) : document.body.removeAttribute("data-gk-theme");
        document.body.classList.toggle("gk-dark", modus === "dark-klasse");
        document.body.setAttribute("data-gk-mode", modus === "dark-klasse" ? "light" : modus);
        const zeile = { thema: thema || "(ohne Thema)", modus, abweichungen: [], offen: [], kontrast: {} };
        for (const [alias, rolle] of paare) {
          const a = aufloesen(alias), r = aufloesen(rolle);
          /* Alpha gehört dazu: --gk-border/--gk-outline-variant ist das einzige Paar
             mit Durchsichtigkeit, und ein undurchsichtiges #ffffff statt
             rgba(255,255,255,0.08) hätte in den RGB-Kanälen allein gar nicht auffallen
             können. */
          if (a.join(",") !== r.join(",")) {
            const zeig = (v) => `rgba(${v[0]},${v[1]},${v[2]},${v[3].toFixed(3)})`;
            zeile.abweichungen.push(`${alias} = ${zeig(a)} aber ${rolle} = ${zeig(r)}`);
          }
        }
        for (const [alias, rolle] of zurueck) {
          const a = aufloesen(alias), r = aufloesen(rolle);
          if (a.join(",") !== r.join(",")) zeile.offen.push(`${alias} ≠ ${rolle}`);
        }
        const grund = aufloesen("--gk-surface");
        zeile.kontrast.text = verhaeltnis(aufloesen("--gk-text"), grund);
        zeile.kontrast.muted = verhaeltnis(aufloesen("--gk-text-muted"), grund);
        ergebnis.push(zeile);
      }
    }
    return ergebnis;
  }, [PAARE, ZURUECKGESTELLT, THEMEN, MODI]);

  const mitThemes = await komponentenMessen(browser, true, "attribut");
  const ohneThemes = await komponentenMessen(browser, false, "attribut");
  const klassenSchreibweise = await komponentenMessen(browser, false, "klasse");
  const hell = await komponentenMessen(browser, true, "hell");
  await browser.close();

  let schlecht = 0;
  for (const z of messen) {
    const kopf = `${z.thema.padEnd(12)} ${z.modus.padEnd(5)}`;
    if (z.abweichungen.length) {
      schlecht++;
      console.log(`FAIL  ${kopf}  ${z.abweichungen.length} Alias/Rolle-Paar(e) weichen ab`);
      z.abweichungen.forEach((a) => console.log(`        ${a}`));
    } else {
      console.log(`ok    ${kopf}  alle ${PAARE.length} Aliase stimmen mit ihrer Rolle überein` +
        `   Text ${z.kontrast.text.toFixed(2)}:1 · gedämpft ${z.kontrast.muted.toFixed(2)}:1` +
        (z.offen.length ? `   (zurückgestellt: ${z.offen.length} von ${ZURUECKGESTELLT.length})` : ""));
    }
    /* Gedämpfter Text ist Fliesstext-Grösse und muss AA erfüllen. */
    if (z.kontrast.muted < 4.5) {
      schlecht++;
      console.log(`FAIL  ${kopf}  gedämpfter Text steht bei ${z.kontrast.muted.toFixed(2)}:1, AA verlangt 4,5`);
    }
  }
  console.log(schlecht ? `\n${schlecht} Beanstandung(en)` : `\nok — ${messen.length} Kombinationen aus Thema und Modus`);

  for (const [wie, k] of [["mit themes.css", mitThemes], ["ohne themes.css", ohneThemes],
                          ["ohne themes.css, .gk-dark", klassenSchreibweise],
                          ["helles Schema", hell]]) {
    console.log(`\nKomponenten, ${wie}:`);
    if (k.fehler) { console.log(`FAIL  ${k.fehler}`); schlecht++; continue; }

    /* Jede Probe muss gefunden worden sein — eine leere Trefferliste aus einer
       Seite ohne die Bauteile wäre kein Ergebnis, sondern ein Versäumnis. */
    if (k.fehlend.length) { console.log(`FAIL  nicht gefunden: ${k.fehlend.join(", ")}`); schlecht++; }
    else console.log(`ok    alle ${k.geprueft} Bauteile gefunden und gemessen`);

    if (k.fremdfarben.length) { schlecht++;
      console.log(`FAIL  ${k.fremdfarben.length} Bauteil(e) mit einer Farbe aus GRIDKits eigener Palette`);
      k.fremdfarben.forEach((z) => console.log(`        ${z}`));
    } else console.log("ok    keine Textfarbe stammt aus GRIDKits eigener Palette");

    const gleich = k.kopf.normal === k.kopf.klebend;
    if (!gleich) schlecht++;
    console.log(`${gleich ? "ok  " : "FAIL"}  Kopfzeile rgb(${k.kopf.normal}), festgeklebt rgb(${k.kopf.klebend}) bei ${k.kopf.durchsicht} Deckung`);

    for (const [zustand, ph] of Object.entries(k.platzhalter)) {
      const liest = ph.kontrast >= 4.5;
      if (!liest) schlecht++;
      console.log(`${liest ? "ok  " : "FAIL"}  Platzhalter ${zustand.padEnd(11)} ${ph.wert} auf ${ph.grund} → ${ph.kontrast.toFixed(2)}:1`);
    }
  }

  process.exit(schlecht ? 1 : 0);
})().catch((e) => { console.error("Abbruch:", e.message.split("\n")[0]); process.exit(2); });
