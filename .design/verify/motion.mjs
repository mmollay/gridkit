import { readFileSync } from 'node:fs';
const lines = readFileSync('css/gridkit.css', 'utf8').split('\n');
const sections = [];
lines.forEach((l, i) => { const m = l.match(/^\/\* =+ (.+?) =+ \*\//); if (m) sections.push({ name: m[1].trim(), start: i + 1 }); });
sections.forEach((s, i) => { s.end = i + 1 < sections.length ? sections[i + 1].start - 1 : lines.length; });
const sec = (ln) => (sections.find(s => ln >= s.start && ln <= s.end) || { name: '(Kopf)' }).name;

console.log('=== BEWEGUNG ===');
const tr = [];
lines.forEach((l, i) => { const m = l.match(/^\s*transition\s*:\s*(.+?);?\s*$/); if (m) tr.push({ line: i + 1, wert: m[1], section: sec(i + 1) }); });
const alls = tr.filter(t => /\ball\b/.test(t.wert));
const dauern = {};
for (const t of tr) for (const d of (t.wert.match(/[\d.]+m?s/g) || [])) dauern[d] = (dauern[d] || 0) + 1;
console.log(`transition-Deklarationen: ${tr.length}`);
console.log(`davon "transition: all": ${alls.length}   ← animiert auch Groesse und Position`);
console.log('Dauern:', Object.entries(dauern).sort((a,b)=>b[1]-a[1]).map(([k,v])=>`${k}×${v}`).join('  '));
const kurven = {};
for (const t of tr) { const m = t.wert.match(/(cubic-bezier\([^)]*\)|ease-in-out|ease-out|ease-in|linear|ease)/g) || ['(keine)']; for (const k of m) kurven[k]=(kurven[k]||0)+1; }
console.log('Kurven:', Object.entries(kurven).sort((a,b)=>b[1]-a[1]).map(([k,v])=>`${k}×${v}`).join('  '));
console.log('\n"transition: all" nach Abschnitt:');
const byS = {}; for (const a of alls) (byS[a.section] ||= []).push(a.line);
for (const [k,v] of Object.entries(byS)) console.log(`  ${k.padEnd(28)} Z. ${v.join(', ')}`);

console.log('\n\n=== ANIMATIONEN ===');
const anim = lines.map((l,i)=>({line:i+1,l})).filter(x=>/^\s*animation\s*:/.test(x.l));
console.log(`animation-Deklarationen: ${anim.length}`);
const rm = lines.findIndex(l => /prefers-reduced-motion/.test(l));
console.log(`prefers-reduced-motion-Bloecke: ${lines.filter(l=>/prefers-reduced-motion/.test(l)).length}` + (rm>=0?` (erster in Z. ${rm+1})`:''));

console.log('\n\n=== HOEHE / SCHATTEN ===');
const sh = [];
lines.forEach((l, i) => { const m = l.match(/^\s*box-shadow\s*:\s*(.+?);?\s*$/); if (m) sh.push({ line: i + 1, wert: m[1].trim(), section: sec(i + 1) }); });
const tokenisiert = sh.filter(s => /var\(--gk-/.test(s.wert));
console.log(`box-shadow-Deklarationen: ${sh.length}   davon ueber Token: ${tokenisiert.length}   literal: ${sh.length - tokenisiert.length}`);
const werte = {};
for (const s of sh) if (!/var\(--gk-/.test(s.wert)) werte[s.wert] = (werte[s.wert] || 0) + 1;
console.log('\nHaeufigste Literal-Schatten:');
for (const [k, v] of Object.entries(werte).sort((a,b)=>b[1]-a[1]).slice(0, 14)) console.log(`  ${String(v).padStart(3)}×  ${k.slice(0,88)}`);
console.log(`\nverschiedene Literal-Schatten insgesamt: ${Object.keys(werte).length}`);
