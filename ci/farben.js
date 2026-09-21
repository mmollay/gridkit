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
  process.exit(schlecht ? 1 : 0);
})().catch((e) => { console.error("Abbruch:", e.message.split("\n")[0]); process.exit(2); });
