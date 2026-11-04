/* GridKit import v1.119.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js is one:
 * the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites, so a block that
 * lives there would never reach it. Load it after gridkit.js (skeleton.php does); nothing here needs
 * gridkit.js — the words, the fields and the limits come in the importer's own config.
 *
 *   <form class="gk-import" data-gk-import="customers" data-gk-import-step="upload" …>   ← GridKit\Import
 *     a script element of type application/json, data-gk-import-config: {fields, limits, words}
 *     <div class="gk-import-upload"> file input, pasted text </div>
 *
 * Without this script the form is a multi-step POST: upload → mapping and preview → confirm. With it,
 * a file that is chosen or dropped (or text that is pasted) is read here with FileReader, decoded,
 * split and checked by the same rules as Import.php — the separator, the quotation mark, the header
 * row, Windows-1252, the mapping by name and alias, every type, required and unique — and the
 * mapping and the preview appear at once and follow every change. The form then posts the file and
 * the mapping once; the server reads and checks it all again and never takes a row from here.
 *
 * Events, on the form, bubbling:
 *   gk:importread   { import, filename, bytes, meta, columns, total }   a file or text was read
 *   gk:importmap    { import, mapping, source }                         the mapping was set: auto, user, api
 *   gk:importready  { import, result }                                  the rows were checked (after every change)
 *   gk:importerror  { import, message }                                 a file was refused
 */
(function () {
  "use strict";

  var IMPORT = "[data-gk-import]";
  var DELIMS = [";", ",", "\t", "|"];
  var DELIM_WORDS = { ";": "semicolon", ",": "comma", "\t": "tab", "|": "pipe" };
  var ENCODINGS = ["UTF-8", "Windows-1252", "UTF-16LE", "UTF-16BE"];
  var MAX_COLUMNS = 200;
  var HARD_ROWS = 100000;
  var HARD_BYTES = 20971520;
  var SAMPLE = 50;
  var TEXT_MS = 350;
  var TRUE_WORDS = ["1", "x", "ja", "j", "yes", "y", "true", "wahr", "on"];
  var FALSE_WORDS = ["0", "nein", "n", "no", "false", "falsch", "off"];
  // Import::FOLD, letter for letter: how a column name meets a field name.
  var FOLD = {
    "ß": "ss", "ẞ": "ss", "æ": "ae", "Æ": "ae", "œ": "oe", "Œ": "oe", "þ": "th", "Þ": "th",
    "à": "a", "á": "a", "â": "a", "ã": "a", "å": "a", "À": "a", "Á": "a", "Â": "a", "Ã": "a", "Å": "a",
    "ç": "c", "Ç": "c", "ð": "d", "Ð": "d", "ñ": "n", "Ñ": "n", "ý": "y", "ÿ": "y", "Ý": "y", "Ÿ": "y",
    "è": "e", "é": "e", "ê": "e", "ë": "e", "È": "e", "É": "e", "Ê": "e", "Ë": "e",
    "ì": "i", "í": "i", "î": "i", "ï": "i", "Ì": "i", "Í": "i", "Î": "i", "Ï": "i",
    "ò": "o", "ó": "o", "ô": "o", "õ": "o", "ø": "o", "Ò": "o", "Ó": "o", "Ô": "o", "Õ": "o", "Ø": "o",
    "ù": "u", "ú": "u", "û": "u", "Ù": "u", "Ú": "u", "Û": "u"
  };
  var UMLAUT_AE = { "ä": "ae", "ö": "oe", "ü": "ue", "Ä": "ae", "Ö": "oe", "Ü": "ue" };
  var UMLAUT_A = { "ä": "a", "ö": "o", "ü": "u", "Ä": "a", "Ö": "o", "Ü": "u" };
  var states = new WeakMap();
  var counter = 0;

  /* ── Words and small shapes ──────────────────────────────────────────── */

  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
  // Lang::t(): every {name} replaced.
  function fill(text, params) {
    text = String(text);
    for (var name in params || {}) text = text.split("{" + name + "}").join(String(params[name]));
    return text;
  }
  function say(cfg, key, params) {
    var t = (cfg && cfg.t) || {};
    return fill(t[key] != null ? t[key] : key, params);
  }
  // number_format($n, 0, '', thousands)
  function num(cfg, n) {
    var s = String(Math.abs(Math.trunc(n)));
    var out = "";
    while (s.length > 3) { out = (cfg.thousands || "") + s.slice(-3) + out; s = s.slice(0, -3); }
    return (n < 0 ? "-" : "") + s + out;
  }
  function size(cfg, bytes) {
    if (bytes < 1024) return say(cfg, "size_bytes", { n: num(cfg, bytes) });
    if (bytes < 1048576) return say(cfg, "size_kb", { n: num(cfg, Math.max(1, Math.round(bytes / 1024))) });
    var mb = Math.round(bytes / 1048576 * 10) / 10;
    return say(cfg, "size_mb", { n: mb === Math.floor(mb) ? num(cfg, mb) : mb.toFixed(1).replace(".", cfg.decimal || ".") });
  }
  function rowsText(cfg, n) {
    return n === 1 ? say(cfg, "rows_one") : say(cfg, "rows_n", { n: num(cfg, n) });
  }
  // Import::trim(): the six spaces of PHP's trim() without NUL, and the no-break space.
  function trim(s) {
    return String(s).replace(/^[ \t\n\r\x0B\f ]+|[ \t\n\r\x0B\f ]+$/g, "");
  }
  // Import::cut(): spaces collapsed, at most max characters (code points), then an ellipsis.
  function cut(s, max) {
    s = String(s).replace(/[ \t\n\r\x0B\f]+/g, " ");
    var chars = Array.from(s);
    if (chars.length <= max) return s;
    return chars.slice(0, max - 1).join("").replace(/[ \t\n\r\0\x0B]+$/, "") + "…";
  }
  function asciiLower(s) {
    return String(s).replace(/[A-Z]/g, function (c) { return c.toLowerCase(); });
  }
  // Import::lower(): A–Z and À–Þ (not ×).
  function lower(s) {
    return asciiLower(s).replace(/[À-Þ]/g, function (c) {
      return c === "×" ? c : String.fromCharCode(c.charCodeAt(0) + 0x20);
    });
  }
  function folds(s) {
    var out = [];
    [UMLAUT_AE, UMLAUT_A].forEach(function (uml) {
      var f = "";
      for (var i = 0; i < s.length; i++) {
        var c = s[i];
        f += uml[c] != null ? uml[c] : FOLD[c] != null ? FOLD[c] : c;
      }
      f = asciiLower(f).replace(/[^a-z0-9]+/g, "");
      if (f !== "" && out.indexOf(f) < 0) out.push(f);
    });
    return out;
  }
  function meets(a, b) {
    for (var i = 0; i < a.length; i++) if (b.indexOf(a[i]) >= 0) return true;
    return false;
  }
  // hash('fnv1a32', …) over the UTF-8 bytes: the columns a mapping was drawn for.
  function signature(columns) {
    var bytes = new TextEncoder().encode(columns.length + "\x1e" + columns.join("\x1f"));
    var h = 0x811c9dc5;
    for (var i = 0; i < bytes.length; i++) {
      h = (h ^ bytes[i]) >>> 0;
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return ("0000000" + h.toString(16)).slice(-8);
  }

  /* ── Bytes to text: Import::decode(), with TextDecoder ───────────────── */

  function utf8(bytes) {
    try { return new TextDecoder("utf-8", { fatal: true, ignoreBOM: true }).decode(bytes); } catch (e) { return null; }
  }
  function startsWith(bytes, list) {
    if (bytes.length < list.length) return false;
    for (var i = 0; i < list.length; i++) if (bytes[i] !== list[i]) return false;
    return true;
  }
  function guessUtf16(bytes) {
    var n = Math.min(bytes.length, 4096) & ~1;
    if (n < 4) return null;
    var even = 0, odd = 0, any = false;
    for (var i = 0; i < n; i += 2) {
      if (bytes[i] === 0) { even++; any = true; }
      if (bytes[i + 1] === 0) { odd++; any = true; }
    }
    if (!any) return null;
    var pairs = n / 2;
    if (odd * 10 >= pairs * 3 && even * 20 <= pairs) return "UTF-16LE";
    if (even * 10 >= pairs * 3 && odd * 20 <= pairs) return "UTF-16BE";
    return null;
  }
  function decode(bytes, encoding, cfg) {
    var bom = false;
    var enc = encoding;
    if (startsWith(bytes, [0xEF, 0xBB, 0xBF]) && (enc === "auto" || enc === "UTF-8")) {
      bom = true;
      bytes = bytes.subarray(3);
      if (enc === "auto") enc = utf8(bytes) !== null ? "UTF-8" : "Windows-1252";
    } else if (startsWith(bytes, [0xFF, 0xFE]) && (enc === "auto" || enc === "UTF-16LE")) {
      bom = true;
      bytes = bytes.subarray(2);
      enc = "UTF-16LE";
    } else if (startsWith(bytes, [0xFE, 0xFF]) && (enc === "auto" || enc === "UTF-16BE")) {
      bom = true;
      bytes = bytes.subarray(2);
      enc = "UTF-16BE";
    }
    if (enc === "auto") enc = guessUtf16(bytes) || (utf8(bytes) !== null ? "UTF-8" : "Windows-1252");
    var text;
    if (enc === "UTF-8") text = utf8(bytes);
    else text = new TextDecoder(enc === "Windows-1252" ? "windows-1252" : enc.toLowerCase(), { ignoreBOM: true }).decode(bytes);
    if (text === null) return { text: "", encoding: enc, bom: bom, error: say(cfg, "err_utf8") };
    if (text.indexOf("\0") >= 0) return { text: "", encoding: enc, bom: bom, error: say(cfg, "err_binary") };
    return { text: text, encoding: enc, bom: bom, error: null };
  }
  function encodingName(e) {
    e = String(e == null ? "" : e).trim().toUpperCase().replace(/_/g, "-");
    if (e === "AUTO" || e === "") return "auto";
    if (e === "UTF-8" || e === "UTF8") return "UTF-8";
    if (["WINDOWS-1252", "CP1252", "ISO-8859-1", "LATIN1", "LATIN-1"].indexOf(e) >= 0) return "Windows-1252";
    if (e === "UTF-16LE" || e === "UTF-16") return "UTF-16LE";
    if (e === "UTF-16BE") return "UTF-16BE";
    return null;
  }

  /* ── Text to rows: Import::parseCsv() ───────────────────────────────── */

  function records(text, d, q, limit, info) {
    var len = text.length;
    var pos = 0;
    var row = 0;
    var out = [];
    info = info || {};
    info.unclosed = null;
    info.tooWide = false;
    // strcspn($text, $d . "\r\n", $pos)
    function span(from) {
      var i = from;
      while (i < len) {
        var c = text[i];
        if (c === d || c === "\r" || c === "\n") break;
        i++;
      }
      return i - from;
    }
    while (pos < len && out.length < limit) {
      row++;
      var cells = [];
      var empty = true;
      for (;;) {
        var cell;
        var n;
        if (pos < len && text[pos] === q) {
          pos++;
          cell = "";
          for (;;) {
            var next = text.indexOf(q, pos);
            if (next < 0) {
              cell += text.slice(pos);
              pos = len;
              if (info.unclosed === null) info.unclosed = row;
              break;
            }
            cell += text.slice(pos, next);
            pos = next + 1;
            if (pos < len && text[pos] === q) { cell += q; pos++; continue; }
            break;
          }
          n = span(pos);
          cell += text.slice(pos, pos + n);
          pos += n;
          cell = cell.replace(/\r\n|\r/g, "\n");
        } else {
          n = span(pos);
          cell = text.slice(pos, pos + n);
          pos += n;
        }
        cells.push(cell);
        if (empty && trim(cell) !== "") empty = false;
        if (cells.length > MAX_COLUMNS) { info.tooWide = true; return out; }
        if (pos >= len) break;
        var c = text[pos];
        pos++;
        if (c === d) {
          if (pos >= len) { cells.push(""); break; }
          continue;
        }
        if (c === "\r" && pos < len && text[pos] === "\n") pos++;
        break;
      }
      if (!empty) out.push([row, cells]);
    }
    return out;
  }
  function count(text, re) {
    var m = text.match(re);
    return m ? m.length : 0;
  }
  function detectQuote(text) {
    var score = {};
    ['"', "'"].forEach(function (q) {
      var opens = (text.charAt(0) === q ? 1 : 0) + count(text, new RegExp("[;,\\t|\\r\\n]" + q, "g"));
      var closes = (text.length && text.charAt(text.length - 1) === q ? 1 : 0) + count(text, new RegExp(q + "[;,\\t|\\r\\n]", "g"));
      score[q] = Math.min(opens, closes);
    });
    return score["'"] > score['"'] && score["'"] >= 2 ? "'" : '"';
  }
  function better(a, b) {
    var l = a[0] * b[1];
    var r = b[0] * a[1];
    if (l !== r) return l > r;
    return a[2] > b[2];
  }
  function detectDelimiter(text, quote) {
    var best = null;
    var bestScore = null;
    DELIMS.forEach(function (d) {
      var info = {};
      var counts = records(text, d, quote, SAMPLE, info).map(function (r) { return r[1].length; });
      if (info.tooWide) counts.push(MAX_COLUMNS + 1);
      if (!counts.length) return;
      // array_count_values() keeps the order the widths first appear in, and so does this.
      var order = [];
      var freq = {};
      counts.forEach(function (w) { if (!freq[w]) { freq[w] = 0; order.push(w); } freq[w]++; });
      var mode = 0;
      var hits = 0;
      order.forEach(function (w) {
        if (freq[w] > hits || (freq[w] === hits && w > mode)) { mode = w; hits = freq[w]; }
      });
      if (mode < 2) return;
      var score = [hits, counts.length, mode];
      if (bestScore === null || better(score, bestScore)) { best = d; bestScore = score; }
    });
    if (best !== null) return best;
    var fewest = ";";
    var least = null;
    DELIMS.forEach(function (d) {
      var n = text.split(d).length - 1;
      if (least === null || n < least) { least = n; fewest = d; }
    });
    return fewest;
  }
  function lineEnding(text) {
    var crlf = text.split("\r\n").length - 1;
    var lf = text.split("\n").length - 1 - crlf;
    var cr = text.split("\r").length - 1 - crlf;
    if (crlf + lf + cr === 0) return "";
    if (crlf >= lf && crlf >= cr) return "CRLF";
    return lf >= cr ? "LF" : "CR";
  }
  function looksLikeHeader(cells, names) {
    var known = {};
    names.forEach(function (n) { if (typeof n === "string") folds(n).forEach(function (f) { known[f] = true; }); });
    for (var i = 0; i < cells.length; i++) {
      var f = folds(cells[i]);
      for (var j = 0; j < f.length; j++) if (known[f[j]]) return true;
    }
    for (var k = 0; k < cells.length; k++) {
      var t = trim(cells[k]);
      if (t === "") continue;
      if (parseNumber(t, ".") !== null || date(t) !== null || isEmail(t)) return false;
    }
    return true;
  }

  function toBytes(source) {
    if (source instanceof Uint8Array) return source;
    if (source instanceof ArrayBuffer) return new Uint8Array(source);
    if (ArrayBuffer.isView(source)) return new Uint8Array(source.buffer, source.byteOffset, source.byteLength);
    return new TextEncoder().encode(String(source == null ? "" : source));
  }

  /** Import::parseCsv($bytes, $opts): { columns, rows: [[row, cells], …], meta, error }. */
  function parseCsv(source, opts, cfg) {
    opts = opts || {};
    cfg = cfg || { t: {} };
    var bytes = toBytes(source);
    var maxRows = Math.max(1, Math.min(HARD_ROWS, opts.maxRows | 0 || 10000));
    var maxBytes = Math.max(1, Math.min(HARD_BYTES, opts.maxBytes | 0 || 5242880));
    var meta = { bytes: bytes.length, encoding: null, bom: false, delimiter: null, enclosure: null,
      lineEnding: null, header: false, sep: false, unclosed: null };
    var out = { columns: [], rows: [], meta: meta, error: null };
    if (bytes.length > maxBytes) {
      out.error = say(cfg, "err_size", { size: size(cfg, bytes.length), max: size(cfg, maxBytes) });
      return out;
    }
    var decoded = decode(bytes, encodingName(opts.encoding || "auto") || "auto", cfg);
    meta.encoding = decoded.encoding;
    meta.bom = decoded.bom;
    if (decoded.error !== null) { out.error = decoded.error; return out; }
    var text = decoded.text;

    var delimiter = opts.delimiter === "tab" ? "\t" : opts.delimiter;
    var m = /^sep=([;,\t|])(\r\n|\n|\r|$)/.exec(text);
    if (m) {
      meta.sep = true;
      if (DELIMS.indexOf(delimiter) < 0) delimiter = m[1];
      text = text.slice(m[0].length);
    }
    meta.lineEnding = lineEnding(text);
    var quote = opts.enclosure === '"' || opts.enclosure === "'" ? opts.enclosure : detectQuote(text);
    if (DELIMS.indexOf(delimiter) < 0) delimiter = detectDelimiter(text, quote);
    meta.enclosure = quote;
    meta.delimiter = delimiter;

    var info = {};
    var recs = records(text, delimiter, quote, maxRows + 2, info);
    meta.unclosed = info.unclosed;
    if (info.tooWide) { out.error = say(cfg, "err_columns", { max: num(cfg, MAX_COLUMNS) }); return out; }
    var hasHeader = !recs.length ? false
      : typeof opts.header === "boolean" ? opts.header : looksLikeHeader(recs[0][1], Array.isArray(opts.names) ? opts.names : []);
    meta.header = hasHeader;
    var head = hasHeader ? recs.shift()[1] : [];
    if (recs.length > maxRows) { out.error = say(cfg, "err_rows", { max: num(cfg, maxRows) }); return out; }

    var width = head.length;
    recs.forEach(function (r) { width = Math.max(width, r[1].length); });
    outer: while (width > 0) {
      var i = width - 1;
      if (trim(head[i] || "") !== "") break;
      for (var k = 0; k < recs.length; k++) if (trim(recs[k][1][i] || "") !== "") break outer;
      width--;
    }
    for (var c = 0; c < width; c++) {
      var name = trim(head[c] || "");
      out.columns.push(name !== "" ? name : say(cfg, "column_n", { n: c + 1 }));
    }
    out.rows = recs.map(function (r) {
      var cells = r[1].slice(0, width);
      while (cells.length < width) cells.push("");
      return [r[0], cells];
    });
    return out;
  }

  /* ── Checking: Import::typed() and result() ─────────────────────────── */

  // LineItems::parseNumber(), to the letter (js/gridkit.js has the same; the dashboard's copy may not).
  var SPACES = /[ \t\n\x0B\f\r    '’]+/g;
  var CUR = "(?:€|\\$|£|EUR|USD|GBP|CHF)";
  var EDGES = new RegExp("^" + CUR + "|(?:" + CUR + "|%)$", "gi");
  var LEAD = new RegExp("^" + CUR, "i");
  function groupsOk(s, sep) {
    var parts = s.split(sep);
    if (!/^\d{1,3}$/.test(parts[0])) return false;
    for (var k = 1; k < parts.length; k++) if (!/^\d{3}$/.test(parts[k])) return false;
    return true;
  }
  function parseNumber(input, dec) {
    if (typeof input !== "string") return null;
    if (new TextEncoder().encode(input).length > 64) return null;
    var s = input.replace(SPACES, "").replace(EDGES, "");
    var sign = "";
    var marks = [["-", "-"], ["+", ""], ["−", "-"]];
    for (var k = 0; k < marks.length; k++) {
      if (s.indexOf(marks[k][0]) === 0) {
        sign = marks[k][1];
        s = s.slice(marks[k][0].length).replace(LEAD, "");
        break;
      }
    }
    if (!/^[0-9.,]+$/.test(s) || !/[0-9]/.test(s)) return null;
    var dots = s.split(".").length - 1;
    var commas = s.split(",").length - 1;
    var int;
    var frac = "";
    var parts;
    if (dots && commas) {
      var decSep = s.lastIndexOf(".") > s.lastIndexOf(",") ? "." : ",";
      var thou = decSep === "." ? "," : ".";
      if (s.split(decSep).length - 1 !== 1) return null;
      parts = s.split(decSep);
      int = parts[0];
      frac = parts[1];
      if (frac.indexOf(thou) >= 0 || !groupsOk(int, thou)) return null;
      int = int.split(thou).join("");
    } else if (dots + commas) {
      var sep = dots ? "." : ",";
      if (dots + commas > 1) {
        if (!groupsOk(s, sep)) return null;
        int = s.split(sep).join("");
      } else {
        parts = s.split(sep);
        int = parts[0];
        frac = parts[1];
        if (/^[1-9]\d{0,2}$/.test(int) && /^\d{3}$/.test(frac) && sep !== dec) { int += frac; frac = ""; }
      }
    } else {
      int = s;
    }
    int = int.replace(/^0+/, "");
    frac = frac.replace(/0+$/, "");
    if (int === "") int = "0";
    if (int.length > 15 || frac.length > 12) return null;
    var out = frac === "" ? int : int + "." + frac;
    return out === "0" ? "0" : sign + out;
  }
  var EMAIL = /^(?=.{1,254}$)(?=[^@]{1,64}@)[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+(?:[A-Za-z]{2,63}|xn--[A-Za-z0-9-]{1,59})$/;
  function isEmail(s) { return EMAIL.test(s); }
  function date(s) {
    var m, d, mo, y;
    if ((m = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(s))) { d = m[1]; mo = m[2]; y = m[3]; }
    else if ((m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s))) { y = m[1]; mo = m[2]; d = m[3]; }
    else if ((m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(s))) { d = m[1]; mo = m[2]; y = m[3]; }
    else return null;
    d = +d; mo = +mo; y = +y;
    var leap = (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
    var days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (y < 1 || mo < 1 || mo > 12 || d < 1 || d > days[mo - 1]) return null;
    function pad(n, w) { n = String(n); while (n.length < w) n = "0" + n; return n; }
    return pad(y, 4) + "-" + pad(mo, 2) + "-" + pad(d, 2);
  }
  function typed(cfg, f, raw) {
    if (raw === "") {
      if (f.required) return [null, say(cfg, "err_required", { field: f.label })];
      return [f.type === "bool" ? false : null, null];
    }
    var de = cfg.locale === "de";
    var n;
    switch (f.type) {
      case "email":
        if (!isEmail(raw)) return [null, say(cfg, "err_email")];
        var at = raw.lastIndexOf("@");
        return [raw.slice(0, at) + "@" + asciiLower(raw.slice(at + 1)), null];
      case "number":
        n = parseNumber(raw, de ? "," : ".");
        return n === null ? [null, say(cfg, "err_number", { example: de ? "1.234,5" : "1,234.5" })] : [n, null];
      case "money":
        n = parseNumber(raw, de ? "," : ".");
        if (n === null) return [null, say(cfg, "err_money", { example: de ? "1.234,50" : "1,234.50" })];
        var dot = n.indexOf(".");
        var frac = dot < 0 ? "" : n.slice(dot + 1);
        if (frac.length > 2) return [null, say(cfg, "err_decimals")];
        while (frac.length < 2) frac += "0";
        return [(dot < 0 ? n : n.slice(0, dot)) + "." + frac, null];
      case "date":
        var dt = date(raw);
        return dt === null ? [null, say(cfg, "err_date")] : [dt, null];
      case "bool":
        var w = asciiLower(raw);
        if (TRUE_WORDS.indexOf(w) >= 0) return [true, null];
        if (FALSE_WORDS.indexOf(w) >= 0) return [false, null];
        return [null, say(cfg, "err_bool")];
      case "select":
        var i;
        for (i = 0; i < f.options.length; i++) if (f.options[i][0] === raw) return [f.options[i][0], null];
        var fr = folds(raw);
        for (i = 0; i < f.options.length; i++) {
          if (meets(fr, folds(f.options[i][0]).concat(folds(f.options[i][1])))) return [f.options[i][0], null];
        }
        var labels = f.options.slice(0, 8).map(function (o) { return o[1]; });
        return [null, say(cfg, "err_option", { options: labels.join(", ") + (f.options.length > 8 ? ", …" : "") })];
    }
    return [raw, null];
  }
  function uniqueKey(type, value) {
    if (typeof value === "boolean") return value ? "1" : "0";
    var s = String(value);
    if (type === "email") return asciiLower(s);
    if (type === "text") return lower(s);
    return s;
  }

  /**
   * Import::mapping(): the person's choice (for these columns), else map() of the page, else by
   * name — key and label first, then the aliases, columns left to right. A field chosen for two
   * columns keeps the first.
   */
  function mapping(cfg, columns, hasHeader, chosen) {
    var map = columns.map(function () { return null; });
    var byKey = {};
    cfg.fields.forEach(function (f) { byKey[f.key] = f; });
    if (chosen) {
      map = map.map(function (_, i) { var k = chosen[i] || ""; return byKey[k] ? k : null; });
    } else if (hasHeader) {
      var taken = {};
      [false, true].forEach(function (aliases) {
        columns.forEach(function (column, i) {
          if (map[i] !== null) return;
          var fc = folds(column);
          if (!fc.length) return;
          for (var j = 0; j < cfg.fields.length; j++) {
            var f = cfg.fields[j];
            if (taken[f.key]) continue;
            var names = aliases ? f.aliases : [f.key, f.label];
            for (var k = 0; k < names.length; k++) {
              if (meets(fc, folds(names[k]))) { map[i] = f.key; taken[f.key] = true; return; }
            }
          }
        });
      });
    }
    var notes = [];
    var first = {};
    map.forEach(function (key, i) {
      if (key === null) return;
      if (first[key] != null) {
        notes.push(say(cfg, "twice", { field: byKey[key].label, column: columns[first[key]] }));
        map[i] = null;
        return;
      }
      first[key] = i;
    });
    return { map: map, notes: notes };
  }

  /** Import::result() for what this browser read: the same counts, rows, messages and order. */
  function check(cfg, parsed, chosen, filename) {
    var r = { rows: {}, errors: {}, total: 0, valid: 0, invalid: 0, columns: parsed.columns, mapping: [],
      missing: [], notes: [], meta: parsed.meta, error: parsed.error };
    if (parsed.error !== null) return r;
    r.meta = Object.assign({}, parsed.meta, { filename: filename || "" });
    var m = mapping(cfg, parsed.columns, parsed.meta.header, chosen);
    r.mapping = m.map;
    if (parsed.meta.unclosed !== null) r.notes.push(say(cfg, "unclosed", { row: num(cfg, parsed.meta.unclosed) }));
    r.notes = r.notes.concat(m.notes);
    var colOf = {};
    m.map.forEach(function (key, i) { if (key !== null) colOf[key] = i; });
    cfg.fields.forEach(function (f) { if (f.required && colOf[f.key] == null) r.missing.push(f.key); });
    var seen = {};
    parsed.rows.forEach(function (pair) {
      var row = pair[0];
      var cells = pair[1];
      var values = {};
      var errors = {};
      var bad = false;
      cfg.fields.forEach(function (f) {
        if (colOf[f.key] == null) {
          if (f.required) { errors[f.key] = say(cfg, "err_required", { field: f.label }); bad = true; }
          return;
        }
        var res = typed(cfg, f, trim(cells[colOf[f.key]] || ""));
        if (res[1] !== null) { errors[f.key] = res[1]; bad = true; return; }
        if (f.unique && res[0] !== null) {
          var u = uniqueKey(f.type, res[0]);
          seen[f.key] = seen[f.key] || {};
          if (Object.prototype.hasOwnProperty.call(seen[f.key], u)) {
            errors[f.key] = say(cfg, "err_duplicate", { row: num(cfg, seen[f.key][u]) });
            bad = true;
            return;
          }
          seen[f.key][u] = row;
        }
        values[f.key] = res[0];
      });
      r.total++;
      if (!bad) { r.rows[row] = values; r.valid++; } else { r.errors[row] = errors; r.invalid++; }
    });
    return r;
  }

  /* ── Drawing: the markup Import.php writes for the map step ─────────── */

  function message(tone, icon, text, role, extra) {
    return '<div class="gk-message gk-message-' + tone + " gk-message-sm" + (extra ? " " + extra : "") + '"'
      + (role ? ' role="' + role + '"' : "") + ">"
      + '<span class="material-icons" aria-hidden="true">' + icon + "</span>"
      + '<div class="gk-message-content">' + esc(text) + "</div></div>";
  }
  function submit(name, step, label, variant, color, icon, disabled, extra) {
    return '<button type="submit" class="gk-btn gk-btn-' + variant + " gk-btn-" + color + '" name="' + esc(name) + '[step]" value="' + step + '"'
      + (disabled ? " disabled" : "") + (extra || "") + ">"
      + (icon ? '<span class="material-icons" style="font-size:18px" aria-hidden="true">' + esc(icon) + "</span>" : "")
      + "<span>" + esc(label) + "</span></button>";
  }
  function fieldOf(cfg, key) {
    for (var i = 0; i < cfg.fields.length; i++) if (cfg.fields[i].key === key) return cfg.fields[i];
    return null;
  }
  function rowKeys(obj) {
    return Object.keys(obj).map(Number).sort(function (a, b) { return a - b; });
  }
  function summaryParts(cfg, r) {
    if (r.total === 0) return ["warning", "info", say(cfg, "summary_none")];
    if (r.invalid === 0) return ["success", "check_circle", say(cfg, "summary_ok", { rows: rowsText(cfg, r.total) })];
    return ["warning", "error", say(cfg, "summary_errors", { rows: rowsText(cfg, r.total), bad: num(cfg, r.invalid) })];
  }
  function confirmText(cfg, r) {
    var n = r.valid;
    if (r.invalid === 0) return n === 1 ? say(cfg, "confirm_one") : say(cfg, "confirm_n", { n: num(cfg, n) });
    return n === 1 ? say(cfg, "confirm_valid_one") : say(cfg, "confirm_valid_n", { n: num(cfg, n) });
  }
  function missingHtml(cfg, r) {
    if (!r.missing.length) return "";
    var names = r.missing.map(function (k) { return fieldOf(cfg, k).label; });
    return message("error", "error", say(cfg, "missing", { fields: names.join(", ") }), "", "gk-import-missing");
  }
  function previewHtml(st, r) {
    var cfg = st.cfg;
    var rows = st.parsed.rows;
    if (!rows.length) return "";
    var base = "gk-import-" + cfg.name;
    var shown = rows.slice(0, cfg.preview);
    var caption = rows.length > shown.length ? say(cfg, "preview", { n: num(cfg, shown.length) }) : say(cfg, "preview_all");
    var h = '<div class="gk-table-wrap gk-import-preview" role="region" tabindex="0" aria-labelledby="' + esc(base + "-caption") + '">'
      + '<table class="gk-table"><caption class="gk-sr-only" id="' + esc(base + "-caption") + '">' + esc(caption) + "</caption>"
      + '<thead><tr><th scope="col">' + esc(say(cfg, "row")) + '</th><th scope="col">' + esc(say(cfg, "status")) + "</th>";
    r.columns.forEach(function (column, i) {
      var key = r.mapping[i];
      h += '<th scope="col"' + (key === null ? ' class="gk-import-ignored"' : "") + ">" + esc(column)
        + '<span class="gk-import-target">' + esc(key === null ? say(cfg, "ignored") : "→ " + fieldOf(cfg, key).label) + "</span></th>";
    });
    h += "</tr></thead><tbody>";
    shown.forEach(function (pair) {
      var row = pair[0];
      var cells = pair[1];
      var errors = r.errors[row] || {};
      var keys = Object.keys(errors);
      var byColumn = {};
      var other = [];
      keys.forEach(function (key) {
        var col = r.mapping.indexOf(key);
        if (col >= 0) byColumn[col] = errors[key]; else other.push(errors[key]);
      });
      h += "<tr" + (keys.length ? ' class="gk-import-badrow"' : "") + '><th scope="row" class="gk-import-rowno">' + esc(num(cfg, row)) + "</th>";
      if (!keys.length) {
        h += '<td class="gk-import-state"><span class="material-icons" aria-hidden="true">check_circle</span>' + esc(say(cfg, "ok")) + "</td>";
      } else {
        h += '<td class="gk-import-state"><span class="material-icons" aria-hidden="true">error</span>'
          + esc(keys.length === 1 ? say(cfg, "row_errors_one") : say(cfg, "row_errors_n", { n: num(cfg, keys.length) }));
        other.forEach(function (msg) { h += '<span class="gk-import-msg">' + esc(msg) + "</span>"; });
        h += "</td>";
      }
      r.columns.forEach(function (_, i) {
        var cls = [];
        if (r.mapping[i] === null) cls.push("gk-import-ignored");
        if (byColumn[i] != null) cls.push("gk-import-bad");
        h += "<td" + (cls.length ? ' class="' + cls.join(" ") + '"' : "") + ">" + esc(cut(cells[i] || "", 80));
        if (byColumn[i] != null) h += '<span class="gk-import-msg"><span class="material-icons" aria-hidden="true">error</span>' + esc(byColumn[i]) + "</span>";
        h += "</td>";
      });
      h += "</tr>";
    });
    return h + "</tbody></table></div>";
  }
  function errorsHtml(st, r) {
    var cfg = st.cfg;
    var rowsAt = {};
    st.parsed.rows.forEach(function (pair) { rowsAt[pair[0]] = pair[1]; });
    var bad = rowKeys(r.errors);
    if (!bad.length || r.missing.length) return "";
    var base = "gk-import-" + cfg.name;
    var h = '<div class="gk-import-errors"><p class="gk-import-errors-title" id="' + esc(base + "-errors") + '">'
      + esc(say(cfg, "errors", { n: num(cfg, bad.length) })) + "</p>"
      + '<ul aria-labelledby="' + esc(base + "-errors") + '">';
    bad.slice(0, 100).forEach(function (row) {
      var errors = r.errors[row];
      Object.keys(errors).forEach(function (key) {
        var col = r.mapping.indexOf(key);
        var value = col >= 0 ? cut(trim((rowsAt[row] || [])[col] || ""), 40) : "";
        h += "<li><strong>" + esc(say(cfg, "row_n", { row: num(cfg, row) })) + "</strong> · "
          + esc(fieldOf(cfg, key).label) + ": " + esc(errors[key]) + (value !== "" ? " <q>" + esc(value) + "</q>" : "") + "</li>";
      });
    });
    h += "</ul>";
    if (bad.length > 100) h += '<p class="gk-import-more">' + esc(say(cfg, "errors_more", { n: num(cfg, bad.length - 100) })) + "</p>";
    return h + "</div>";
  }
  function actionsHtml(st, r) {
    var cfg = st.cfg;
    var ready = !r.missing.length && r.valid > 0;
    var h = '<div class="gk-import-actions">'
      + submit(cfg.name, "confirm", confirmText(cfg, r), "filled", "primary", "download_done", !ready)
      // Taken over from the server (no file box on the page): Cancel goes back to the server, as drawn.
      + (st.kept ? submit(cfg.name, "cancel", say(cfg, "cancel"), "text", "neutral", "", false, " formnovalidate")
        : '<button type="button" class="gk-btn gk-btn-text gk-btn-neutral" data-gk-import-cancel><span>' + esc(say(cfg, "cancel")) + "</span></button>");
    if (ready && r.invalid > 0) h += '<span class="gk-import-skip">' + esc(say(cfg, "skip_note")) + "</span>";
    return h + "</div>";
  }

  function panelHtml(st) {
    var cfg = st.cfg;
    var r = st.result;
    var meta = r.meta;
    var base = "gk-import-" + cfg.name;
    var name = esc(cfg.name);
    var h = '<div class="gk-import-panel">';
    var what = st.source.name !== "" ? st.source.name : say(cfg, "pasted");
    h += '<p class="gk-import-source"><span class="material-icons" aria-hidden="true">description</span>'
      + "<strong>" + esc(what) + "</strong> · " + esc(size(cfg, meta.bytes)) + " · " + esc(rowsText(cfg, r.total)) + "</p>";
    if (st.warning) h += message("error", "error", st.warning, "alert");
    if (r.notes.length) {
      h += '<div class="gk-message gk-message-warning gk-message-sm gk-import-notes"><span class="material-icons" aria-hidden="true">warning</span>'
        + '<div class="gk-message-content"><ul>' + r.notes.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul></div></div>";
    }
    h += '<fieldset class="gk-import-options"><legend>' + esc(say(cfg, "options")) + "</legend>"
      + '<label class="gk-import-option"><span class="gk-label-text">' + esc(say(cfg, "delimiter")) + "</span>"
      + '<select class="gk-input" name="' + name + '[delimiter]">';
    DELIMS.forEach(function (d) {
      h += '<option value="' + (d === "\t" ? "tab" : esc(d)) + '"' + (meta.delimiter === d ? " selected" : "") + ">"
        + esc(say(cfg, "delim_" + DELIM_WORDS[d])) + "</option>";
    });
    h += "</select></label>"
      + '<label class="gk-import-option"><span class="gk-label-text">' + esc(say(cfg, "encoding")) + "</span>"
      + '<select class="gk-input" name="' + name + '[encoding]">';
    ENCODINGS.forEach(function (enc) {
      h += '<option value="' + esc(enc) + '"' + (meta.encoding === enc ? " selected" : "") + ">"
        + esc(say(cfg, "enc_" + enc.toLowerCase().replace(/-/g, ""))) + "</option>";
    });
    h += "</select></label>"
      + '<input type="hidden" name="' + name + '[header]" value="0">'
      + '<label class="gk-checkbox-wrap gk-import-option"><input type="checkbox" name="' + name + '[header]" value="1"'
      + (meta.header ? " checked" : "") + '><span class="gk-checkbox-custom"></span>'
      + '<span class="gk-checkbox-text">' + esc(say(cfg, "header")) + "</span></label></fieldset>";

    h += '<fieldset class="gk-import-map"><legend>' + esc(say(cfg, "map")) + "</legend>";
    r.columns.forEach(function (column, i) {
      var key = r.mapping[i];
      var sample = [];
      for (var k = 0; k < st.parsed.rows.length && sample.length < 2; k++) {
        var v = trim(st.parsed.rows[k][1][i] || "");
        if (v !== "") sample.push(cut(v, 24));
      }
      var sid = base + "-sample-" + i;
      h += '<div class="gk-import-col' + (key === null ? " gk-import-ignored" : "") + '">'
        + '<label class="gk-import-col-name" for="' + esc(base + "-map-" + i) + '"><span class="gk-sr-only">'
        + esc(say(cfg, "column")) + " </span>" + esc(column) + "</label>"
        + '<select class="gk-input" id="' + esc(base + "-map-" + i) + '" name="' + name + "[map][" + i + ']" aria-describedby="' + esc(sid) + '">'
        + '<option value="">' + esc(say(cfg, "ignore")) + "</option>";
      cfg.fields.forEach(function (f) {
        h += '<option value="' + esc(f.key) + '"' + (key === f.key ? " selected" : "") + ">" + esc(f.label)
          + (f.required ? " " + esc(say(cfg, "required_mark")) : "") + "</option>";
      });
      h += '</select><span class="gk-import-col-sample" id="' + esc(sid) + '">'
        + esc(sample.length ? say(cfg, "sample", { values: sample.join(", ") }) : say(cfg, "empty_column")) + "</span></div>";
    });
    h += "</fieldset>";
    var s = summaryParts(cfg, r);
    h += '<div data-gk-import-live>' + missingHtml(cfg, r) + message(s[0], s[1], s[2], "status", "gk-import-summary")
      + previewHtml(st, r) + errorsHtml(st, r) + actionsHtml(st, r) + "</div>";
    h += '<input type="hidden" name="' + name + '[cols]" value="' + esc(signature(r.columns)) + '">';
    if (st.kept) {
      Object.keys(st.kept).forEach(function (k) {
        h += '<input type="hidden" name="' + name + "[" + k + ']" value="' + esc(st.kept[k]) + '"' + (k === "data" ? " data-gk-import-data" : "") + ">";
      });
    }
    return h + "</div>";
  }

  /* ── The flow in the browser ─────────────────────────────────────────── */

  function emit(st, type, detail) {
    detail.import = st.form;
    st.form.dispatchEvent(new CustomEvent(type, { bubbles: true, detail: detail }));
  }

  function setStep(st, step) {
    var at = { upload: 0, map: 1, done: 2 }[step] || 0;
    st.form.setAttribute("data-gk-import-step", step);
    var list = st.form.querySelector(".gk-steps");
    if (!list) return;
    Array.prototype.forEach.call(list.children, function (li, i) {
      li.classList.toggle("gk-steps-done", i < at);
      li.classList.toggle("gk-steps-current", i === at);
      if (i === at) li.setAttribute("aria-current", "step"); else li.removeAttribute("aria-current");
      var sr = li.querySelector(".gk-sr-only");
      if (i < at && !sr) {
        sr = document.createElement("span");
        sr.className = "gk-sr-only";
        sr.textContent = " " + say(st.cfg, "step_done_sr");
        li.appendChild(sr);
      } else if (i >= at && sr) sr.remove();
    });
  }

  function names(cfg) {
    var out = [];
    cfg.fields.forEach(function (f) { out.push(f.key, f.label); out = out.concat(f.aliases); });
    return out;
  }

  function parseNow(st) {
    var o = st.opts;
    st.parsed = parseCsv(st.source.bytes, { delimiter: o.delimiter, enclosure: o.enclosure, encoding: o.encoding,
      header: o.header, maxRows: st.cfg.maxRows, maxBytes: st.cfg.maxBytes, names: names(st.cfg) }, st.cfg);
    return st.parsed;
  }

  function showError(st, text) {
    var box = st.form.querySelector("[data-gk-import-error]");
    if (!text) { if (box) box.remove(); return; }
    if (!box) {
      var host = st.form.querySelector(".gk-import-upload");
      if (!host) return;
      var tpl = document.createElement("template");
      tpl.innerHTML = message("error", "error", text, "alert").replace("<div ", "<div data-gk-import-error ");
      box = tpl.content.firstChild;
      host.parentNode.insertBefore(box, host);
    } else {
      box.querySelector(".gk-message-content").textContent = text;
    }
  }

  function removePanel(st) {
    var p = st.form.querySelector(".gk-import-panel");
    if (p) p.remove();
    var start = st.form.querySelector("[data-gk-import-start]");
    if (start) start.hidden = false;
  }

  /** Draws the panel from st (source, options, mapping); keeps the focus on the control that had it. */
  function draw(st, focusName) {
    var old = st.form.querySelector(".gk-import-panel");
    var tpl = document.createElement("template");
    tpl.innerHTML = panelHtml(st);
    var panel = tpl.content.firstChild;
    if (old) old.replaceWith(panel);
    else {
      var upload = st.form.querySelector(".gk-import-upload");
      var start = st.form.querySelector("[data-gk-import-start]");
      var after = start || upload;
      if (after) after.parentNode.insertBefore(panel, after.nextSibling);
      else st.form.appendChild(panel);
    }
    var start2 = st.form.querySelector("[data-gk-import-start]");
    if (start2) start2.hidden = true;
    st.panel = panel;
    setStep(st, "map");
    if (focusName) {
      var el = panel.querySelector('[name="' + focusName.replace(/"/g, '\\"') + '"]:not([type="hidden"])');
      if (el) el.focus();
    }
  }

  /** After a change of the mapping: the select's own column, and everything below the mapping. */
  function redrawLive(st) {
    var cfg = st.cfg;
    var r = st.result;
    var panel = st.panel;
    if (!panel) return;
    panel.querySelectorAll(".gk-import-col").forEach(function (col, i) {
      col.classList.toggle("gk-import-ignored", r.mapping[i] === null);
      var sel = col.querySelector("select");
      if (sel && sel.value !== (r.mapping[i] || "")) sel.value = r.mapping[i] || "";
    });
    var notes = panel.querySelector(".gk-import-notes");
    var notesHtml = r.notes.length ? '<div class="gk-message gk-message-warning gk-message-sm gk-import-notes"><span class="material-icons" aria-hidden="true">warning</span>'
      + '<div class="gk-message-content"><ul>' + r.notes.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul></div></div>" : "";
    var tpl = document.createElement("template");
    tpl.innerHTML = notesHtml;
    if (notes) {
      if (notesHtml) notes.replaceWith(tpl.content.firstChild); else notes.remove();
    } else if (notesHtml) {
      panel.querySelector(".gk-import-options").before(tpl.content.firstChild);
    }
    var live = panel.querySelector("[data-gk-import-live]");
    var summary = live.querySelector(".gk-import-summary");
    var s = summaryParts(cfg, r);
    // The summary is a live region: its words change in place, so a screen reader hears them.
    summary.className = "gk-message gk-message-" + s[0] + " gk-message-sm gk-import-summary";
    summary.querySelector(".material-icons").textContent = s[1];
    summary.querySelector(".gk-message-content").textContent = s[2];
    var missing = live.querySelector(".gk-import-missing");
    tpl.innerHTML = missingHtml(cfg, r);
    if (missing) { if (tpl.content.firstChild) missing.replaceWith(tpl.content.firstChild); else missing.remove(); }
    else if (tpl.content.firstChild) live.insertBefore(tpl.content.firstChild, summary);
    var rest = previewHtml(st, r) + errorsHtml(st, r) + actionsHtml(st, r);
    while (summary.nextSibling) summary.nextSibling.remove();
    tpl.innerHTML = rest;
    live.appendChild(tpl.content);
    var sig = panel.querySelector('input[name="' + cfg.name + '[cols]"]');
    if (sig) sig.value = signature(r.columns);
  }

  function evaluate(st, chosen, source) {
    st.result = check(st.cfg, st.parsed, chosen, st.source.name);
    st.chosen = st.result.mapping.map(function (k) { return k || ""; });
    emit(st, "gk:importmap", { mapping: st.result.mapping.slice(), source: source });
    emit(st, "gk:importready", { result: st.result });
    return st.result;
  }

  /** Reads bytes as the importer's source: parse, map by name, check, draw. */
  function take(st, bytes, name, pasted) {
    st.source = { bytes: bytes, name: name || "", pasted: !!pasted };
    st.opts = { delimiter: st.cfg.delimiter, enclosure: st.cfg.enclosure, encoding: pasted ? "UTF-8" : st.cfg.encoding, header: st.cfg.header };
    st.warning = null;
    var p = parseNow(st);
    if (p.error !== null) return refuse(st, check(st.cfg, p, null, st.source.name));
    showError(st, "");
    // From now on the options are what was found: the server reads the file with exactly these.
    st.opts = { delimiter: p.meta.delimiter, enclosure: p.meta.enclosure, encoding: p.meta.encoding, header: p.meta.header };
    emit(st, "gk:importread", { filename: st.source.name, bytes: bytes.length, meta: p.meta, columns: p.columns.slice(), total: p.rows.length });
    evaluate(st, null, "auto");
    draw(st);
    return st.result;
  }

  /** A refused file: back to step 1, the reason in words; the result says it as result() would. */
  function refuse(st, r) {
    st.result = r;
    removePanel(st);
    setStep(st, "upload");
    showError(st, r.error);
    emit(st, "gk:importerror", { message: r.error });
    return r;
  }

  function readFile(st, file) {
    return new Promise(function (resolve) {
      if (file.size > st.cfg.maxBytes) {
        // Refused before a byte is read — what Import::parseCsv() says of a file that size.
        resolve(refuse(st, { rows: {}, errors: {}, total: 0, valid: 0, invalid: 0, columns: [], mapping: [], missing: [], notes: [],
          meta: { bytes: file.size, encoding: null, bom: false, delimiter: null, enclosure: null, lineEnding: null, header: false, sep: false, unclosed: null },
          error: say(st.cfg, "err_size", { size: size(st.cfg, file.size), max: size(st.cfg, st.cfg.maxBytes) }) }));
        return;
      }
      var reader = new FileReader();
      reader.onload = function () { resolve(take(st, new Uint8Array(reader.result), file.name, false)); };
      reader.onerror = function () {
        var msg = say(st.cfg, "err_read");
        showError(st, msg);
        emit(st, "gk:importerror", { message: msg });
        resolve(null);
      };
      reader.readAsArrayBuffer(file);
    });
  }

  // What a form posts for a textarea: line breaks as CRLF. The browser reads what the server will.
  function textBytes(text) {
    return new TextEncoder().encode(String(text).replace(/\r\n|\r|\n/g, "\r\n"));
  }

  function fileInput(st) { return st.form.querySelector('.gk-import-drop input[type="file"]'); }
  function textArea(st) { return st.form.querySelector(".gk-import-text"); }

  function reset(st) {
    var input = fileInput(st);
    var area = textArea(st);
    if (input) input.value = "";
    if (area) area.value = "";
    st.source = null;
    st.result = null;
    st.parsed = null;
    st.panel = null;
    showError(st, "");
    removePanel(st);
    setStep(st, "upload");
  }

  /** A changed option re-reads the file; a changed mapping only checks again. */
  function onChange(st, e) {
    var el = e.target;
    if (!st.panel || !st.panel.contains(el) || !el.name) return;
    var name = el.name.slice(st.cfg.name.length);
    if (/^\[map\]\[\d+\]$/.test(name)) {
      var chosen = Array.prototype.map.call(st.panel.querySelectorAll(".gk-import-map select"), function (s) { return s.value; });
      // A field takes one column: choosing it here lets go of it elsewhere.
      var at = +name.match(/\d+/)[0];
      if (el.value !== "") chosen.forEach(function (k, i) { if (i !== at && k === el.value) chosen[i] = ""; });
      evaluate(st, chosen, "user");
      redrawLive(st);
      return;
    }
    if (name === "[delimiter]" || name === "[encoding]" || name === "[header]") {
      var panel = st.panel;
      var d = panel.querySelector('select[name$="[delimiter]"]').value;
      var before = { delimiter: st.opts.delimiter, encoding: st.opts.encoding, header: st.opts.header };
      st.opts.delimiter = d === "tab" ? "\t" : d;
      st.opts.encoding = panel.querySelector('select[name$="[encoding]"]').value;
      st.opts.header = panel.querySelector('input[type="checkbox"][name$="[header]"]').checked;
      st.warning = null;
      var columns = st.parsed.columns;
      var p = parseNow(st);
      if (p.error !== null) {
        // The choice made the file unreadable: keep it as it was read, and say why.
        st.warning = p.error;
        st.opts.delimiter = before.delimiter;
        st.opts.encoding = before.encoding;
        st.opts.header = before.header;
        p = parseNow(st);
      }
      // The same columns keep the person's mapping, as on the server (the signature decides there).
      var keep = signature(p.columns) === signature(columns);
      evaluate(st, keep ? st.chosen : null, keep ? "user" : "auto");
      draw(st, el.name);
    }
  }

  function setup(form) {
    if (states.has(form)) return states.get(form);
    var cfgEl = form.querySelector("script[data-gk-import-config]");
    var cfg;
    try { cfg = JSON.parse(cfgEl ? cfgEl.textContent : "null"); } catch (e) { cfg = null; }
    if (!cfg || !Array.isArray(cfg.fields)) return null;
    var st = { form: form, cfg: cfg, source: null, parsed: null, result: null, panel: null, opts: null, chosen: null, kept: null, warning: null };
    states.set(form, st);
    if (!form.id) form.id = "gk-import-auto-" + (++counter);

    form.addEventListener("change", function (e) {
      var input = fileInput(st);
      if (input && e.target === input) {
        var area = textArea(st);
        if (area) area.value = "";
        if (input.files && input.files[0]) readFile(st, input.files[0]);
        else reset(st);
        return;
      }
      onChange(st, e);
    });
    var timer = null;
    form.addEventListener("input", function (e) {
      var area = textArea(st);
      if (!area || e.target !== area) return;
      clearTimeout(timer);
      timer = setTimeout(function () {
        var input = fileInput(st);
        if (trim(area.value) === "") { if (!input || !input.files || !input.files.length) reset(st); return; }
        if (input) input.value = "";
        take(st, textBytes(area.value), "", true);
      }, TEXT_MS);
    });
    form.addEventListener("click", function (e) {
      var cancel = e.target.closest && e.target.closest("[data-gk-import-cancel]");
      if (!cancel || !form.contains(cancel)) return;
      e.preventDefault();
      reset(st);
      var input = fileInput(st);
      if (input) input.focus();
    });

    // Drag and drop: the zone takes a file the way the file input would.
    var drop = form.querySelector(".gk-import-drop");
    if (drop) {
      var depth = 0;
      drop.addEventListener("dragenter", function (e) { if (hasFiles(e)) { depth++; drop.classList.add("gk-import-dragover"); e.preventDefault(); } });
      drop.addEventListener("dragover", function (e) { if (hasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = "copy"; } });
      drop.addEventListener("dragleave", function () { if (--depth <= 0) { depth = 0; drop.classList.remove("gk-import-dragover"); } });
      drop.addEventListener("drop", function (e) {
        depth = 0;
        drop.classList.remove("gk-import-dragover");
        if (!hasFiles(e)) return;
        e.preventDefault();
        var file = e.dataTransfer.files[0];
        var input = fileInput(st);
        var area = textArea(st);
        if (area) area.value = "";
        // The input must hold the file, or the form would post nothing. Every current browser lets it.
        try { if (input) input.files = e.dataTransfer.files; } catch (err) { /* see below */ }
        if (input && input.files && input.files[0] === file) { readFile(st, file); return; }
        if (input && input.files && input.files.length) { readFile(st, input.files[0]); return; }
        // A browser that does not: the text goes into the text box and is posted from there.
        var reader = new FileReader();
        reader.onload = function () {
          var bytes = new Uint8Array(reader.result);
          var d = decode(bytes, "auto", st.cfg);
          if (d.error !== null || !area) { readFile(st, file); return; }
          area.value = d.text;
          take(st, textBytes(d.text), file.name, true);
        };
        reader.readAsArrayBuffer(file);
      });
    }
    adopt(st);
    return st;
  }

  /**
   * Puts a file where the form posts it from: the file field (DataTransfer), or — on a step 2 the
   * server drew with storage(false) — the field that carries the file. The text box takes the text
   * where a browser lets no script fill a file field.
   */
  function hold(st, file) {
    var input = fileInput(st);
    var area = textArea(st);
    if (input) {
      try {
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
      } catch (e) { /* below */ }
      if (input.files && input.files.length === 1) {
        if (area) area.value = "";
        return Promise.resolve(true);
      }
    }
    return new Promise(function (resolve) {
      var reader = new FileReader();
      reader.onload = function () {
        var bytes = new Uint8Array(reader.result);
        if (st.kept) {
          var bin = "";
          for (var i = 0; i < bytes.length; i += 8192) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 8192));
          st.kept = { data: btoa(bin), filename: file.name || "" };
        } else if (area) {
          var d = decode(bytes, "auto", st.cfg);
          if (d.error === null) area.value = d.text;
        }
        resolve(false);
      };
      reader.onerror = function () { resolve(false); };
      reader.readAsArrayBuffer(file);
    });
  }

  function hasFiles(e) {
    var types = e.dataTransfer && e.dataTransfer.types;
    return !!types && Array.prototype.indexOf.call(types, "Files") >= 0;
  }

  /**
   * A map step the server drew with storage(false) carries the file in the form: the script takes
   * it over — the same options and mapping — and from then on checks every change at once.
   */
  function adopt(st) {
    var panel = st.form.querySelector(".gk-import-panel");
    var data = panel && panel.querySelector("[data-gk-import-data]");
    if (!data) return;
    var bin;
    try { bin = atob(data.value); } catch (e) { return; }
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    var nameInput = panel.querySelector('input[name="' + st.cfg.name + '[filename]"]');
    var d = panel.querySelector('select[name$="[delimiter]"]');
    var enc = panel.querySelector('select[name$="[encoding]"]');
    var head = panel.querySelector('input[type="checkbox"][name$="[header]"]');
    st.source = { bytes: bytes, name: nameInput ? nameInput.value : "", pasted: false };
    st.opts = { delimiter: d ? (d.value === "tab" ? "\t" : d.value) : "auto", enclosure: st.cfg.enclosure,
      encoding: enc ? enc.value : "auto", header: head ? head.checked : null };
    st.kept = { data: data.value, filename: nameInput ? nameInput.value : "" };
    var p = parseNow(st);
    if (p.error !== null) return;
    var chosen = Array.prototype.map.call(panel.querySelectorAll(".gk-import-map select"), function (s) { return s.value; });
    st.panel = panel;
    st.result = check(st.cfg, p, chosen.length === p.columns.length ? chosen : null, st.source.name);
    st.chosen = st.result.mapping.map(function (k) { return k || ""; });
    draw(st);
  }

  /* ── The API ─────────────────────────────────────────────────────────── */

  function get(el) {
    if (typeof el === "string") {
      el = document.getElementById(el) || document.getElementById("gk-import-" + el)
        || document.querySelector('[data-gk-import="' + el.replace(/"/g, '\\"') + '"]') || document.querySelector(el);
    }
    var form = el && el.closest ? el.closest(IMPORT) : null;
    return form ? setup(form) : null;
  }
  function plain(r) {
    if (!r) return null;
    return JSON.parse(JSON.stringify(r));
  }

  var api = {
    // Reads a File, a Blob, bytes or text as if it had been chosen; a Promise of the result.
    // The file goes where the person's would — the file field, the text box, or what a server's
    // step 2 carries — so Import posts it.
    read: function (el, source, name) {
      var st = get(el);
      if (!st) return Promise.resolve(null);
      if (typeof source === "string" && !name) {
        var area = textArea(st);
        if (area) area.value = source;
        var input = fileInput(st);
        if (input) input.value = "";
        return Promise.resolve(plain(take(st, textBytes(source), "", true)));
      }
      var file = typeof Blob !== "undefined" && source instanceof Blob
        ? (source instanceof File && !name ? source : new File([source], name || source.name || "import.csv", { type: source.type }))
        : new File([typeof source === "string" ? source : toBytes(source)], name || "import.csv", { type: "text/csv" });
      return hold(st, file).then(function () { return readFile(st, file); }).then(plain);
    },
    // Sets the mapping (column index or name => field key, null to ignore); without one, returns it.
    map: function (el, mapping) {
      var st = get(el);
      if (!st || !st.result) return null;
      if (mapping === undefined) return st.result.mapping.slice();
      var chosen = st.result.columns.map(function () { return ""; });
      Object.keys(mapping).forEach(function (k) {
        var i = /^\d+$/.test(k) ? +k : st.result.columns.indexOf(k);
        if (i >= 0 && i < chosen.length) chosen[i] = mapping[k] || "";
      });
      evaluate(st, chosen, "api");
      redrawLive(st);
      return plain(st.result);
    },
    // What the server's result() would say about what this browser holds.
    result: function (el) {
      var st = get(el);
      return st ? plain(st.result) : null;
    },
    reset: function (el) {
      var st = get(el);
      if (st) reset(st);
    },
    // Import::parseCsv() without an importer: bytes or text, the same options.
    parse: function (source, opts) {
      var o = opts || {};
      var cfg = { t: o.t || {}, thousands: o.thousands || ",", decimal: o.decimal || "." };
      var p = parseCsv(source, o, cfg);
      var rows = {};
      p.rows.forEach(function (pair) { rows[pair[0]] = pair[1]; });
      return { columns: p.columns, rows: rows, meta: p.meta, error: p.error };
    },
    // The rules themselves, for a page that checks a value the way an import would.
    check: function (el, key, value) {
      var st = get(el);
      var f = st ? fieldOf(st.cfg, key) : null;
      if (!f) return null;
      var res = typed(st.cfg, f, trim(String(value == null ? "" : value)));
      return { value: res[0], error: res[1] };
    }
  };
  window.GK = window.GK || {};
  window.GK.import = api;

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(IMPORT)) setup(root);
    root.querySelectorAll(IMPORT).forEach(setup);
  }

  function start() {
    scan(document.documentElement);
    // An importer that arrives later — a modal, a fragment — is set up without a call.
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
