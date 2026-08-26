import { readFileSync, writeFileSync } from 'node:fs';
const css = readFileSync('css/gridkit.css', 'utf8');
const lines = css.split('\n');
const sections = [];
lines.forEach((l, i) => { const m = l.match(/^\/\* =+ (.+?) =+ \*\//); if (m) sections.push({ name: m[1].trim(), start: i + 1 }); });
sections.forEach((s, i) => { s.end = i + 1 < sections.length ? sections[i + 1].start - 1 : lines.length; });
const sectionOf = (ln) => (sections.find(s => ln >= s.start && ln <= s.end) || { name: '(Kopf)' }).name;

let sel = '', depth = 0, buf = [];
const hits = [];
lines.forEach((raw, idx) => {
  const ln = idx + 1, line = raw;
  if (/\{\s*$/.test(line) && depth === 0) { sel = buf.concat(line.replace(/\{\s*$/, '')).join(' ').trim(); buf = []; }
  else if (/,\s*$/.test(line) && depth === 0) { buf.push(line.trim()); }
  const opens = (line.match(/\{/g) || []).length, closes = (line.match(/\}/g) || []).length;
  const m = line.match(/^\s*([a-z-]+)\s*:\s*(.*?);?\s*$/);
  if (m && !/^--/.test(m[1])) {
    const hex = line.match(/#[0-9a-fA-F]{3,8}\b/g) || [];
    // farbige rgba/rgb (nicht rein grau/schwarz/weiss) — die sind ebenfalls themegebunden
    const rgba = (line.match(/rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+[^)]*\)/g) || [])
      .filter(v => { const [r, g, b] = v.match(/\d+/g).map(Number); return !(r === g && g === b); });
    if (hex.length || rgba.length)
      hits.push({ line: ln, section: sectionOf(ln), selector: sel.replace(/\s+/g, ' ').slice(0, 200), prop: m[1], raw: line, literale: [...hex, ...rgba] });
  }
  depth += opens - closes;
});
writeFileSync('.design/verify/hex-inventory.json', JSON.stringify({ sections: sections.map(s => ({ name: s.name, start: s.start, end: s.end })), hits }, null, 1));
const by = {}; for (const h of hits) (by[h.section] ||= []).push(h);
console.log('Deklarationen mit Farbliteral: ' + hits.length + '\n');
console.log(Object.entries(by).sort((a, b) => b[1].length - a[1].length).map(([k, v]) => String(v.length).padStart(4) + '  ' + k).join('\n'));
