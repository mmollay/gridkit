import { readFileSync, writeFileSync, existsSync } from 'node:fs';
const src = 'gk-now.json';
if (!existsSync(src)) { console.error('gk-now.json fehlt.'); process.exit(1); }
const now = JSON.parse(readFileSync(src, 'utf8'));
const states = Object.keys(now);
for (const s of states) writeFileSync(`.design/verify/snapshots/now-${s}.json`, JSON.stringify(now[s]));

// Nur Eigenschaften vergleichen, die auch wirklich gerendert werden.
const RAND = { borderTopColor: 'borderTopWidth', borderBottomColor: 'borderBottomWidth' };
const IGNORIEREN = new Set(['outlineColor', 'borderRightColor', 'borderLeftColor']);
const zaehlt = (rec, p) => {
  if (IGNORIEREN.has(p)) return false;
  if (RAND[p]) { const w = rec[RAND[p]]; return w && w !== '0px'; }
  return true;
};

const cmp = (A, B) => {
  const keys = new Set([...Object.keys(A), ...Object.keys(B)]);
  const schichten = new Map(); let n = 0;
  for (const k of keys) {
    const pa = A[k] || {}, pb = B[k] || {};
    for (const p of new Set([...Object.keys(pa), ...Object.keys(pb)])) {
      if (pa[p] === pb[p]) continue;
      if (!zaehlt(pa, p) && !zaehlt(pb, p)) continue;
      n++;
      const sig = `${p}: ${pa[p] ?? '—'}  →  ${pb[p] ?? '—'}`;
      if (!schichten.has(sig)) schichten.set(sig, []);
      schichten.get(sig).push(k);
    }
  }
  return { n, schichten };
};

console.log('═══ REGRESSION gegen die Basis, gleicher Zustand ═══');
for (const s of states) {
  const base = JSON.parse(readFileSync(`.design/verify/snapshots/base-${s}.json`, 'utf8'));
  const r = cmp(base, now[s]);
  console.log(`\n── ${s}: ${r.n} gerenderte Abweichungen in ${r.schichten.size} verschiedenen Verschiebungen`);
  const sorted = [...r.schichten.entries()].sort((a, b) => b[1].length - a[1].length);
  for (const [sig, els] of sorted.slice(0, 26))
    console.log(`   ${String(els.length).padStart(4)}×  ${sig}`.padEnd(78) + `  z.B. ${els[0].slice(0, 34)}`);
  if (sorted.length > 26) console.log(`   … und ${sorted.length - 26} weitere Verschiebungsarten`);
  writeFileSync(`.design/verify/snapshots/diff-${s}.txt`, sorted.map(([sig, els]) => `${els.length}× ${sig}\n   ${els.slice(0,8).join(', ')}`).join('\n'));
}

console.log('\n\n═══ WIRKUNG: reagieren die Themes im Light Mode? ═══');
const B = (n) => JSON.parse(readFileSync(`.design/verify/snapshots/base-${n}.json`, 'utf8'));
const zaehleElemente = (r) => new Set([...r.schichten.values()].flat()).size;
for (const ziel of ['forest_light', 'rose_light']) {
  const alt = cmp(B('indigo_light'), B(ziel));
  const neu = cmp(now.indigo_light, now[ziel]);
  console.log(`\nindigo → ${ziel.replace('_light','')}`);
  console.log(`   vorher: ${String(alt.n).padStart(5)} Eigenschaften an ${String(zaehleElemente(alt)).padStart(4)} Elementen`);
  console.log(`   jetzt:  ${String(neu.n).padStart(5)} Eigenschaften an ${String(zaehleElemente(neu)).padStart(4)} Elementen`);
  console.log(`   Faktor: ${alt.n ? (neu.n / alt.n).toFixed(1) : '∞'}×`);
}
