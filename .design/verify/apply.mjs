import { readFileSync, writeFileSync } from 'node:fs';

const maps = JSON.parse(readFileSync('.design/verify/maps.json', 'utf8'));
const alle = maps.flatMap(m => m.aenderungen.map(a => ({ ...a, gruppe: m.gruppe })));

// ─── Entscheidungen aus der gegnerischen Pruefung ───────────────────────────
// Verworfen: gerenderte Verschlechterung (Kontrast, Sichtbarkeit) ohne Theming-Gewinn.
const VERWORFEN = new Map([
  [721,  'Kontrast 7,5:1 -> 2,6:1 (Tabellen-Sekundaertext)'],
  [997,  'Rahmen des neutralen Buttons wuerde deutlich dunkler (dL 0,16)'],
  [1640, 'Platzhalter-Kontrast 4,55:1 -> 2,56:1'],
  [2117, 'Gestrichelte Drop-Zone-Kante 1,23:1 — einzige Affordanz'],
  [4391, 'Avatar-Initialen wuerden stark abdunkeln (L 36 -> 17)'],
  [1825, 'Feldhinweis bekaeme exakt die Label-Farbe — Hierarchie weg'],
  [2271, '11px-Text auf kraeftigerer Flaeche; zudem Theme-Kollision in Forest'],
]);

// Ersetzt: Rolle war semantisch richtig, aber nicht wertneutral -> aus der Rolle ableiten.
const ERSETZT = new Map([
  [982,  'color: var(--gk-warning-text);'],
  [983,  'border-color: var(--gk-warning-text);'],
  [1021, 'color: var(--gk-warning-text);'],
  [3975, 'background: color-mix(in oklab, var(--gk-info) 8%, transparent);'],
  [3976, 'border-color: color-mix(in oklab, var(--gk-info) 25%, transparent);'],
  [3980, 'background: color-mix(in oklab, var(--gk-success) 8%, transparent);'],
  [3981, 'border-color: color-mix(in oklab, var(--gk-success) 25%, transparent);'],
  [3985, 'background: color-mix(in oklab, var(--gk-warning) 8%, transparent);'],
  [3986, 'border-color: color-mix(in oklab, var(--gk-warning) 25%, transparent);'],
  [3990, 'background: color-mix(in oklab, var(--gk-error) 8%, transparent);'],
  [3991, 'border-color: color-mix(in oklab, var(--gk-error) 25%, transparent);'],
  [395,  'box-shadow: 0 0 0 2px color-mix(in oklab, var(--gk-primary) 15%, transparent);'],
  [1649, 'box-shadow: 0 0 0 2px color-mix(in oklab, var(--gk-primary) 10%, transparent);'],
  [1948, 'box-shadow: 0 0 0 3px color-mix(in oklab, var(--gk-error) 10%, transparent);'],
  [2090, 'box-shadow: 0 0 0 6px color-mix(in oklab, var(--gk-primary) 15%, transparent);'],
  [1058, 'background: color-mix(in oklab, var(--gk-warning) 10%, transparent);'],
  [1063, 'background: color-mix(in oklab, var(--gk-warning) 18%, transparent);'],
]);

const tok = s => [...s.matchAll(/var\(\s*(--gk-[a-z0-9-]+)/g)].map(m => m[1]).join('+');
const istReinFallback = a => { const ta = tok(a.altText), tn = tok(a.neuText); return ta && ta === tn; };

const trocken = process.argv.includes('--dry');
const lines = readFileSync('css/gridkit.css', 'utf8').split('\n');
// Zeilenversatz je Abschnitt: die Token-Ergaenzungen nach der Inventur haben
// alles darunter verschoben (0 / +7 / +16, ueber Abschnittsmarker bestimmt).
const OFFSETS = JSON.parse(readFileSync('.design/verify/offsets.json', 'utf8'));
const INV = JSON.parse(readFileSync('.design/verify/hex-inventory.json', 'utf8'));
const sektionVon = (ln) => (INV.sections.find(s => ln >= s.start && ln <= s.end) || {}).name;

let angewandt = 0, uebersprungen = 0, verworfen = 0, ersetzt = 0;
const probleme = [];
const erwattetSicher = (i) => i + 1;

for (const a of alle.sort((x, y) => x.zeile - y.zeile)) {
  if (VERWORFEN.has(a.zeile)) { verworfen++; continue; }
  if (istReinFallback(a)) { uebersprungen++; continue; }

  // Exakter Versatz ueber den Abschnitt, danach nur noch +/-3 als Toleranz.
  const sek = sektionVon(a.zeile);
  const off = OFFSETS[sek];
  if (off === undefined) { probleme.push({ zeile: a.zeile, gruppe: a.gruppe, grund: `kein Versatz fuer Abschnitt "${sek}"`, altText: a.altText }); continue; }
  const erwartet = a.zeile + off - 1;
  const treffer = [];
  for (let d = 0; d <= 3; d++) {
    for (const i of (d === 0 ? [erwartet] : [erwartet - d, erwartet + d]))
      if (i >= 0 && i < lines.length && lines[i] === a.altText) treffer.push(i);
    if (treffer.length) break;
  }
  if (treffer.length !== 1) {
    probleme.push({ zeile: a.zeile, gruppe: a.gruppe, grund: treffer.length === 0 ? `altText nicht bei Z.${erwattetSicher(erwartet)}` : `${treffer.length}× mehrdeutig`, altText: a.altText });
    continue;
  }
  const idx = treffer[0];
  let neu = a.neuText;
  if (ERSETZT.has(a.zeile)) {
    const einzug = lines[idx].match(/^\s*/)[0];
    neu = einzug + ERSETZT.get(a.zeile);
    ersetzt++;
  }
  if (!trocken) lines[idx] = neu;
  angewandt++;
}

if (!trocken) writeFileSync('css/gridkit.css', lines.join('\n'));

console.log(`${trocken ? 'PROBELAUF' : 'ANGEWANDT'}`);
console.log(`  angewandt:      ${angewandt}   (davon ${ersetzt} mit korrigiertem Ersatz)`);
console.log(`  uebersprungen:  ${uebersprungen}  (reine Fallback-Streichung, kein gerenderter Effekt)`);
console.log(`  verworfen:      ${verworfen}   (Einwand der Pruefung)`);
console.log(`  ungeloest:      ${probleme.length}`);
for (const p of probleme) console.log(`     Z.${p.zeile} ${p.gruppe}: ${p.grund}  «${p.altText.trim().slice(0, 70)}»`);
