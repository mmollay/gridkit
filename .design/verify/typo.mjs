import { readFileSync } from 'node:fs';
const css = readFileSync('css/gridkit.css', 'utf8');
const lines = css.split('\n');
const sections = [];
lines.forEach((l, i) => { const m = l.match(/^\/\* =+ (.+?) =+ \*\//); if (m) sections.push({ name: m[1].trim(), start: i + 1 }); });
sections.forEach((s, i) => { s.end = i + 1 < sections.length ? sections[i + 1].start - 1 : lines.length; });
const sectionOf = (ln) => (sections.find(s => ln >= s.start && ln <= s.end) || { name: '(Kopf)' }).name;

let sel = '', depth = 0, buf = [];
const hits = [];
lines.forEach((raw, idx) => {
  const ln = idx + 1;
  if (/\{\s*$/.test(raw) && depth === 0) { sel = buf.concat(raw.replace(/\{\s*$/, '')).join(' ').trim(); buf = []; }
  else if (/,\s*$/.test(raw) && depth === 0) buf.push(raw.trim());
  const m = raw.match(/^\s*font-size\s*:\s*([0-9.]+)px/);
  if (m) hits.push({ line: ln, px: parseFloat(m[1]), section: sectionOf(ln), selector: sel.replace(/\s+/g, ' ').slice(0, 90) });
  depth += (raw.match(/\{/g) || []).length - (raw.match(/\}/g) || []).length;
});
const by = {};
for (const h of hits) (by[h.px] ||= []).push(h);
// Vorschlag: 7 Rollen
const ROLLEN = [
  { token: '--gk-text-overline', px: 11, zeile: 1.3,  gewicht: 600 },
  { token: '--gk-text-caption',  px: 12, zeile: 1.45, gewicht: 400 },
  { token: '--gk-text-label',    px: 13, zeile: 1.4,  gewicht: 500 },
  { token: '--gk-text-body',     px: 14, zeile: 1.55, gewicht: 400 },
  { token: '--gk-text-subtitle', px: 16, zeile: 1.4,  gewicht: 600 },
  { token: '--gk-text-title',    px: 20, zeile: 1.3,  gewicht: 600 },
  { token: '--gk-text-display',  px: 32, zeile: 1.15, gewicht: 600 },
];
const naechste = (px) => ROLLEN.reduce((a, b) => Math.abs(b.px - px) < Math.abs(a.px - px) ? b : a);
console.log('px   n    →  Rolle                 Δ   betroffene Abschnitte');
console.log('-'.repeat(96));
let exakt = 0, verschoben = 0;
for (const px of Object.keys(by).map(Number).sort((a, b) => a - b)) {
  const r = naechste(px), d = px - r.px, n = by[px].length;
  if (d === 0) exakt += n; else verschoben += n;
  const abschnitte = [...new Set(by[px].map(h => h.section))].slice(0, 4).join(', ');
  console.log(String(px).padStart(3) + String(n).padStart(5) + '    →  ' + r.token.padEnd(20) + String(d > 0 ? '+' + d : d).padStart(4) + '   ' + abschnitte);
}
console.log(`\nGesamt ${hits.length} Deklarationen · ${exakt} treffen eine Rolle exakt · ${verschoben} verschieben sich`);
console.log('\nAusreisser (>2px Abweichung) im Detail:');
for (const px of Object.keys(by).map(Number)) {
  const r = naechste(px); if (Math.abs(px - r.px) <= 2) continue;
  for (const h of by[px]) console.log(`  ${String(px).padStart(3)}px  Z.${String(h.line).padStart(4)}  ${h.section.padEnd(22)} ${h.selector}`);
}
