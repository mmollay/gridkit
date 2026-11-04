/* GridKit chart v1.121.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js is one:
 * the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites, so a block
 * there would never reach it. Load it after gridkit.js (skeleton.php does). Nothing here needs
 * gridkit.js: the words come from window.GK_LANG (Lang::jsConfig() hands chart.* over as chart_*).
 *
 *   <figure class="gk-chart gk-chart-bar" data-gk-chart='{…the config…}'>   ← GridKit\Chart writes it
 *     <figcaption class="gk-chart-title">…</figcaption>
 *     <div class="gk-chart-plot"><svg class="gk-chart-svg" viewBox="0 0 640 280" role="img">…</svg></div>
 *     <ul class="gk-chart-legend">…</ul>  <details class="gk-chart-data">…</details>
 *
 * The server's chart works on its own. This script:
 *   - draws it again at the width of its box (and whenever the box changes), so the text keeps
 *     its size on a phone. It draws with the code of src/Chart.php, ported line by line below:
 *     GK.chart.html(config) is Chart::html(), byte for byte (ci/browser-chart.js holds it);
 *   - shows the values of a category on hover, on a tap and on the keyboard (one tab stop, the
 *     arrow keys, Home, End; Enter selects), with a crosshair on a line or area chart, and says
 *     them through a live region;
 *   - makes the legend of two or more series buttons that switch a series off and on.
 *
 * Events, on the .gk-chart, bubbling — for what the person does, not for calls of GK.chart:
 *   gk:chartselect  { chart, index, label, series, values: [{ series, name, value, text }] }
 *   gk:charttoggle  { chart, series, name, visible }
 */
(function () {
  "use strict";

  /* ── Words: GK_LANG's chart_*, English when the page has none (lang/en.php, checked by a test) ── */

  var EN = {
    label: "Chart",
    series: "Series {n}",
    type_line: "Line chart",
    type_area: "Area chart",
    type_area_stacked: "Stacked area chart",
    type_bar: "Bar chart",
    type_bar_stacked: "Stacked bar chart",
    type_donut: "Donut chart",
    summary: "{type}, {n} categories from {first} to {last}.",
    summary_one: "{type}, one category: {first}.",
    summary_series: "{name}: from {first} to {last}, lowest {min} ({minAt}), highest {max} ({maxAt}).",
    summary_none: "{name}: no values.",
    summary_total: "Highest total {max} ({maxAt}).",
    summary_donut: "{type}, total {total}: {parts}.",
    part: "{label} {value} ({share})",
    empty: "No data",
    total: "Total",
    other: "Other",
    category: "Category",
    value: "Value",
    share: "Share",
    show_table: "Show as table",
    trend: "Trend",
    spark: "{label}: {n} values from {first} to {last}, lowest {min}, highest {max}.",
    spark_empty: "{label}: no data.",
    hint: "Arrow keys move between the values, Enter selects one.",
    shown: "{name} shown",
    hidden: "{name} hidden"
  };
  function word(key) {
    var lang = window.GK_LANG || {};
    var v = lang["chart_" + key];
    return typeof v === "string" ? v : EN[key];
  }
  // {name} replaced in one pass: a label that holds "{n}" stays as it is (Chart::fill()).
  function fill(text, params) {
    return text.replace(/\{(\w+)\}/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(params, k) ? String(params[k]) : m;
    });
  }

  /* ── The config, checked as Chart::buildConfig() checks it ──────────────── */

  var TYPES = ["line", "area", "bar", "donut", "sparkline"];
  var KINDS = ["number", "money", "percent"];
  var TABLES = ["toggle", "open", "hidden"];
  var SLOTS = 5;
  var MAX_LABELS = 2000;
  var MAX_ABS = 1e15;
  var MIN_ABS = 1e-9;
  var LOCALES = {
    en: [".", ",", "before", "%", ["K", "M", "B"]],
    de: [",", ".", "after", "\u00a0%", ["\u00a0Tsd.", "\u00a0Mio.", "\u00a0Mrd."]]
  };
  var SYMBOLS = { EUR: "\u20ac", USD: "$", GBP: "\u00a3" };
  var CH = 7, GAP = 2, BAR_MAX = 24, RADIUS = 4;
  // PHP's is_numeric(): optional whitespace, a sign, digits with a point, an exponent.
  var NUMERIC = /^[ \t\n\r\v\f]*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?[ \t\n\r\v\f]*$/;

  function warn(id, msg) {
    if (window.console && console.warn) console.warn("GridKit: chart '" + id + "': " + msg);
  }

  // Chart::clean(): no lone surrogates, no control characters, tabs and breaks as spaces, trimmed.
  function clean(s) {
    return String(s)
      .replace(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(^|[^\uD800-\uDBFF])[\uDC00-\uDFFF]/g, function (m, pre) {
        return (pre || "") + "\ufffd";
      })
      .replace(/[\t\n\r]/g, " ")
      .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, "")
      .replace(/^ +| +$/g, "");
  }

  function family(loc) {
    var f = String(loc || "").slice(0, 2).toLowerCase();
    return LOCALES[f] ? f : "en";
  }

  function isInt(v) { return typeof v === "number" && isFinite(v) && Math.floor(v) === v; }

  function normalize(raw) {
    raw = raw || {};
    var id = clean(raw.id == null ? "chart" : raw.id);
    var type = raw.type == null ? "line" : raw.type;
    if (TYPES.indexOf(type) < 0) { warn(id, "type '" + type + "' is not a chart type. It is line."); type = "line"; }
    var axis = type === "line" || type === "area" || type === "bar";

    var stacked = raw.stacked === true;
    if (stacked && type !== "bar" && type !== "area") { warn(id, "stacked applies to bar and area charts."); stacked = false; }
    var horizontal = raw.horizontal === true;
    if (horizontal && type !== "bar") { warn(id, "horizontal applies to bar charts."); horizontal = false; }
    var zero = raw.zero !== false;
    if (!zero && type !== "line") { if (axis) warn(id, "zero false applies to line charts."); zero = true; }
    var markers = raw.markers === true, dashes = raw.dashes === true;
    if ((markers || dashes) && type !== "line" && type !== "area") { warn(id, "markers and dashes apply to line and area charts."); markers = dashes = false; }

    var series = Array.isArray(raw.series) ? raw.series.slice() : [];
    var max = type === "donut" || type === "sparkline" ? 1 : SLOTS;
    if (series.length > max) { warn(id, "only " + max + " series are drawn."); series = series.slice(0, max); }

    var f = raw.format || {};
    var kind = KINDS.indexOf(f.kind) >= 0 ? f.kind : "number";
    var cur = typeof f.currency === "string" ? f.currency.trim().toUpperCase() : "EUR";
    if (!/^[A-Z]{3}$/.test(cur)) { warn(id, "currency takes an ISO code like EUR."); cur = "EUR"; }
    var locale = f.locale != null ? family(f.locale) : family(document.documentElement.getAttribute("lang"));
    var decimals = isInt(f.decimals) && f.decimals >= 0 && f.decimals <= 6 ? f.decimals : null;

    var longest = 0;
    series.forEach(function (s) { longest = Math.max(longest, s && Array.isArray(s.values) ? s.values.length : 0); });
    var labels = [];
    var given = Array.isArray(raw.labels);
    if (!given) {
      for (var i = 1; i <= Math.min(longest, MAX_LABELS); i++) labels.push(String(i));
    } else {
      raw.labels.forEach(function (l) {
        if (l == null) l = "";
        else if (typeof l === "boolean") l = l ? "1" : "";
        else if (typeof l === "number") l = canon(l);
        else if (typeof l === "object") { warn(id, "a label is text."); l = ""; }
        labels.push(clean(l));
      });
    }
    if (labels.length > MAX_LABELS) { warn(id, "at most " + MAX_LABELS + " labels are drawn."); labels = labels.slice(0, MAX_LABELS); }
    var n = labels.length;

    var out = [];
    series.forEach(function (s, si) {
      s = s || {};
      var name = clean(s.name == null ? "" : s.name);
      if (name === "") name = fill(word("series"), { n: si + 1 });
      var src = Array.isArray(s.values) ? s.values : [];
      var values = [];
      for (var vi = 0; vi < Math.min(src.length, n); vi++) values.push(value(src[vi], id, name, vi, type === "donut"));
      if (src.length > n && given) warn(id, "series '" + name + "' has more values than labels.");
      while (values.length < n) values.push(null);
      var color = si + 1;
      if (s.color != null) {
        if (isInt(s.color) && s.color >= 1 && s.color <= SLOTS) color = s.color;
        else warn(id, "series '" + name + "': color takes a slot from 1 to " + SLOTS + ".");
      }
      var hidden = s.hidden === true;
      if (type === "donut" || type === "sparkline") hidden = false;
      out.push({ name: name, values: values, color: color, hidden: hidden });
    });

    var spark = type === "sparkline";
    var lim = spark ? [24, 1000, 96, 12, 200, 28] : [200, 4000, 640, 120, 1200, 280];
    var width = isInt(raw.width) ? raw.width : lim[2];
    if (width < lim[0] || width > lim[1]) { warn(id, "width is outside " + lim[0] + " \u2026 " + lim[1] + "."); width = Math.max(lim[0], Math.min(lim[1], width)); }
    var height = isInt(raw.height) ? raw.height : lim[5];
    if (height < lim[3] || height > lim[4]) { warn(id, "height is outside " + lim[3] + " \u2026 " + lim[4] + "."); height = Math.max(lim[3], Math.min(lim[4], height)); }

    return {
      id: id,
      type: type,
      title: clean(raw.title == null ? "" : raw.title),
      showTitle: raw.showTitle !== false,
      desc: clean(raw.desc == null ? "" : raw.desc),
      labels: labels,
      series: out,
      format: { kind: kind, currency: cur, locale: locale, decimals: decimals },
      width: width,
      height: height,
      stacked: stacked,
      horizontal: horizontal,
      zero: zero,
      markers: markers,
      dashes: dashes,
      table: TABLES.indexOf(raw.table) >= 0 ? raw.table : "toggle",
      area: spark && raw.area === true
    };
  }

  // Chart::value(): a decimal string, or null for a gap.
  function value(v, id, name, i, donut) {
    if (v == null || v === "") return null;
    var f;
    if (typeof v === "string" && NUMERIC.test(v)) f = Number(v.replace(/^[ \t\n\r\v\f]+|[ \t\n\r\v\f]+$/g, ""));
    else if (typeof v === "number") f = v;
    else { warn(id, "series '" + name + "', value " + i + " is not a number. It is a gap."); return null; }
    if (!isFinite(f)) { warn(id, "series '" + name + "', value " + i + " is not finite. It is a gap."); return null; }
    if (Math.abs(f) > MAX_ABS) { warn(id, "series '" + name + "', value " + i + " is beyond \u00b11e15."); f = f < 0 ? -MAX_ABS : MAX_ABS; }
    if (Math.abs(f) < MIN_ABS) f = 0;
    if (donut && f < 0) { warn(id, "series '" + name + "', value " + i + " is negative. It counts as 0."); f = 0; }
    return canon(f);
  }

  /* ── Drawing: Chart::draw() and its helpers, line by line ─────────────── */

  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  function draw(c, width) {
    if (c.type === "sparkline") return spark(c);
    var key = keyOf(c.id);
    var title = c.title !== "" ? c.title : word("label");
    var cls = "gk-chart gk-chart-type-" + c.type + (c.stacked ? " gk-chart-stacked" : "") +
      (c.horizontal ? " gk-chart-horizontal" : "") + (c.dashes ? " gk-chart-dashes" : "");
    return '<figure class="' + cls + '" id="gk-chart-' + key + '" data-gk-chart="' + esc(JSON.stringify(c)) + '">' +
      '<figcaption class="gk-chart-title' + (c.showTitle ? "" : " gk-sr-only") + '">' + esc(title) + "</figcaption>" +
      '<div class="gk-chart-plot">' + svg(c, width == null ? c.width : width, null) + "</div>" +
      legend(c) + dataTable(c, title) + "</figure>";
  }

  // geo, when given, is filled with what the pointer and the keys need: where each category is.
  function svg(c, width, geo) {
    var key = keyOf(c.id);
    var title = c.title !== "" ? c.title : word("label");
    var W = c.type === "donut" ? c.height : width;
    var H = c.height;
    var empty = isEmpty(c);
    var desc = c.desc !== "" ? c.desc : (empty ? word("empty") : summary(c));
    var body;
    geo = geo || {};
    geo.W = W; geo.H = H; geo.empty = empty;
    if (empty) body = emptyBody(W, H);
    else if (c.type === "donut") body = donutBody(c, W, geo);
    else if (c.horizontal) body = hbarBody(c, W, H, geo);
    else body = axisBody(c, W, H, geo);
    return '<svg class="gk-chart-svg" viewBox="0 0 ' + W + " " + H + '" width="' + W + '" height="' + H + '"' +
      ' role="img" aria-labelledby="gk-chart-' + key + '-t" aria-describedby="gk-chart-' + key + '-d">' +
      '<title id="gk-chart-' + key + '-t">' + esc(title) + "</title>" +
      '<desc id="gk-chart-' + key + '-d">' + esc(desc) + "</desc>" +
      body + "</svg>";
  }

  function isEmpty(c) {
    for (var s = 0; s < c.series.length; s++) {
      for (var i = 0; i < c.series[s].values.length; i++) if (c.series[s].values[i] !== null) return false;
    }
    return true;
  }

  function emptyBody(W, H) {
    return '<text class="gk-chart-empty" x="' + num(W / 2) + '" y="' + num(H / 2 + 4) + '" text-anchor="middle">' + esc(word("empty")) + "</text>";
  }

  function visible(c) {
    var out = [];
    c.series.forEach(function (s, i) {
      if (!s.hidden) out.push({ name: s.name, values: s.values, color: s.color, hidden: s.hidden, index: i });
    });
    return out;
  }

  function valueAxis(c, vis, target) {
    var vals = [];
    var n = c.labels.length;
    var i;
    if (c.stacked) {
      for (i = 0; i < n; i++) {
        var pos = 0, neg = 0;
        for (var j = 0; j < vis.length; j++) {
          var v = vis[j].values[i] === null ? 0 : Number(vis[j].values[i]);
          if (v > 0) pos += v; else neg += v;
        }
        vals.push(pos);
        vals.push(neg);
      }
    } else {
      vis.forEach(function (s) { s.values.forEach(function (v) { if (v !== null) vals.push(Number(v)); }); });
    }
    if (c.zero) vals.push(0);
    if (vals.length === 0) vals = [0, 1];
    var lo = vals[0], hi = vals[0];
    for (i = 1; i < vals.length; i++) { if (vals[i] < lo) lo = vals[i]; if (vals[i] > hi) hi = vals[i]; }
    var mag = Math.max(Math.abs(lo), Math.abs(hi));
    if (mag === 0) {
      hi = 1;
    } else if (hi - lo < mag * 1e-6) {
      var d = mag / 10;
      lo -= d;
      hi += d;
      if (c.zero) { lo = Math.min(lo, 0); hi = Math.max(hi, 0); }
    }
    return nice(lo, hi, target, c.format);
  }

  function nice(lo, hi, target, format) {
    var rough = (hi - lo) / target;
    var e = 0, p = 1;
    if (rough >= 1) {
      while (p * 10 <= rough) { p *= 10; e++; }
    } else {
      while (p > rough) { p /= 10; e--; }
    }
    var m = rough / p;
    var mant = m <= 1 ? 1 : (m <= 2 ? 2 : (m <= 5 ? 5 : 10));
    if (mant === 10) { mant = 1; e++; }
    var step = exact(mant, e);
    var kLo = Math.floor(lo / step + 1e-9);
    var kHi = Math.ceil(hi / step - 1e-9);
    if (kHi <= kLo) kHi = kLo + 1;
    var ticks = [];
    var maxAbs = Math.max(Math.abs(exact(kLo * mant, e)), Math.abs(exact(kHi * mant, e)));
    var tier = maxAbs >= 1e10 ? 3 : (maxAbs >= 1e7 ? 2 : (maxAbs >= 1e4 ? 1 : 0));
    for (var k = kLo; k <= kHi; k++) {
      var v = exact(k * mant, e);
      var text;
      if (tier > 0 && v !== 0) {
        text = compose(upTo(canon(exact(k * mant, e - 3 * tier)), Math.max(0, 3 * tier - e), format) +
          LOCALES[format.locale][4][tier - 1], v < 0, format);
      } else {
        text = compose(upTo(canon(v), Math.max(0, -e), format), v < 0, format);
      }
      ticks.push({ v: v, text: text });
    }
    return { lo: exact(kLo * mant, e), hi: exact(kHi * mant, e), ticks: ticks };
  }

  function exact(int, e) {
    var p = 1;
    for (var i = 0; i < Math.abs(e); i++) p *= 10;
    return e >= 0 ? int * p : int / p;
  }

  function axisBody(c, W, H, geo) {
    var vis = visible(c);
    var labels = c.labels;
    var n = labels.length;
    var top = 12;
    var bottom = H - 28;
    var plotH = bottom - top;
    var target = Math.max(2, Math.min(8, Math.floor(plotH / 44)));
    var ax = valueAxis(c, vis, target);
    var longest = 0;
    ax.ticks.forEach(function (t) { longest = Math.max(longest, len(t.text)); });
    var left = Math.max(28, longest * CH + 14);
    var right = W - 12;
    if (right - left < 40) left = right - 40;
    var plotW = right - left;
    var span = ax.hi - ax.lo;
    var y = function (v) { return top + (ax.hi - v) / span * plotH; };
    var band = plotW / n;
    var cx = function (i) { return left + band * i + band / 2; };
    geo.axis = "x"; geo.start = left; geo.band = band; geo.n = n; geo.top = top; geo.bottom = bottom; geo.cx = cx;

    var out = '<g class="gk-chart-grid">';
    ax.ticks.forEach(function (t) {
      var ty = num(y(t.v));
      out += '<line class="gk-chart-gridline' + (t.v === 0 ? " gk-chart-zero" : "") + '" x1="' + num(left) + '" y1="' + ty +
        '" x2="' + num(right) + '" y2="' + ty + '"/>';
    });
    out += '</g><g class="gk-chart-axis">';
    ax.ticks.forEach(function (t) {
      out += '<text x="' + num(left - 8) + '" y="' + num(y(t.v) + 4) + '" text-anchor="end">' + esc(t.text) + "</text>";
    });
    var long = 0;
    labels.forEach(function (l) { long = Math.max(long, len(l)); });
    var need = Math.min(long, 16) * CH + 8;
    var step = Math.max(1, Math.ceil(need / band));
    var chars = Math.max(2, Math.floor((band * step - 6) / CH));
    for (var i = 0; i < n; i += step) out += label(labels[i], chars, cx(i), H - 10, "middle");
    out += '</g><g class="gk-chart-marks">';

    var base = y(Math.max(ax.lo, Math.min(0, ax.hi)));
    if (c.type === "bar") out += bars(c, vis, n, band, cx, y, base);
    else out += lines(c, vis, n, band, cx, y, base);
    return out + "</g>";
  }

  function label(text, chars, x, y, anchor) {
    var shown = cut(text, chars);
    return '<text x="' + num(x) + '" y="' + num(y) + '" text-anchor="' + anchor + '">' + esc(shown) +
      (shown !== text ? "<title>" + esc(text) + "</title>" : "") + "</text>";
  }

  function bars(c, vis, n, band, cx, y, base) {
    var k = vis.length;
    var out = "";
    if (k === 0) return out;
    var single = c.stacked || k === 1;
    var barW = single ? Math.min(BAR_MAX, band * 0.64) : Math.min(BAR_MAX, (band * 0.72 - GAP * (k - 1)) / k);
    if (barW < 1) barW = 1;
    var groupW = single ? barW : barW * k + GAP * (k - 1);

    var from = [], to = [], first = [], last = [];
    var i, j;
    vis.forEach(function () { from.push({}); to.push({}); first.push({}); last.push({}); });
    if (c.stacked) {
      for (i = 0; i < n; i++) {
        var pos = 0, neg = 0, lastPos = -1, lastNeg = -1, seenPos = false, seenNeg = false;
        for (j = 0; j < k; j++) {
          var v0 = vis[j].values[i] === null ? 0 : Number(vis[j].values[i]);
          if (v0 > 0) {
            from[j][i] = pos; pos += v0; to[j][i] = pos;
            first[j][i] = !seenPos; seenPos = true; lastPos = j;
          } else if (v0 < 0) {
            from[j][i] = neg; neg += v0; to[j][i] = neg;
            first[j][i] = !seenNeg; seenNeg = true; lastNeg = j;
          }
        }
        if (lastPos >= 0) last[lastPos][i] = true;
        if (lastNeg >= 0) last[lastNeg][i] = true;
      }
    }

    for (j = 0; j < k; j++) {
      var s = vis[j];
      out += '<g class="gk-chart-series gk-chart-c' + s.color + '" data-gk-chart-s="' + s.index + '">';
      for (i = 0; i < n; i++) {
        if (s.values[i] === null) continue;
        var v = Number(s.values[i]);
        if (v === 0) continue;
        var x = cx(i) - groupW / 2 + (single ? 0 : j * (barW + GAP));
        var y0, y1, up, round, isFirst;
        if (c.stacked) {
          y0 = y(from[j][i]);
          y1 = y(to[j][i]);
          up = v > 0;
          if (!first[j][i]) y0 = up ? y0 - GAP : y0 + GAP;
          round = last[j][i] === true;
          isFirst = first[j][i];
        } else {
          y0 = base;
          y1 = y(v);
          up = v > 0;
          round = true;
          isFirst = true;
        }
        var h = up ? y0 - y1 : y1 - y0;
        if (h < 1) {
          if (!isFirst) continue;
          y1 = up ? y0 - 1 : y0 + 1;
          h = 1;
        }
        out += '<path class="gk-chart-bar" data-gk-chart-i="' + i + '" d="' +
          barPath(x, barW, y0, y1, round ? Math.min(RADIUS, barW / 2, h) : 0, false) + '"/>';
      }
      out += "</g>";
    }
    return out;
  }

  function barPath(a, w, b0, b1, r, horizontal) {
    var n = num, s, edge;
    if (!horizontal) {
      if (r <= 0) return "M" + n(a) + "," + n(b0) + "V" + n(b1) + "H" + n(a + w) + "V" + n(b0) + "Z";
      var up = b1 < b0;
      s = up ? "1" : "0";
      edge = up ? b1 + r : b1 - r;
      return "M" + n(a) + "," + n(b0) + "V" + n(edge) +
        "A" + n(r) + "," + n(r) + " 0 0 " + s + " " + n(a + r) + "," + n(b1) +
        "H" + n(a + w - r) +
        "A" + n(r) + "," + n(r) + " 0 0 " + s + " " + n(a + w) + "," + n(edge) +
        "V" + n(b0) + "Z";
    }
    if (r <= 0) return "M" + n(b0) + "," + n(a) + "H" + n(b1) + "V" + n(a + w) + "H" + n(b0) + "Z";
    var right = b1 > b0;
    s = right ? "1" : "0";
    edge = right ? b1 - r : b1 + r;
    return "M" + n(b0) + "," + n(a) + "H" + n(edge) +
      "A" + n(r) + "," + n(r) + " 0 0 " + s + " " + n(b1) + "," + n(a + r) +
      "V" + n(a + w - r) +
      "A" + n(r) + "," + n(r) + " 0 0 " + s + " " + n(edge) + "," + n(a + w) +
      "H" + n(b0) + "Z";
  }

  function lines(c, vis, n, band, cx, y, base) {
    var out = "";
    var area = c.type === "area";
    var pos = [], neg = [], i;
    for (i = 0; i < n; i++) { pos.push(0); neg.push(0); }
    var dots = c.markers || band >= 24;
    vis.forEach(function (s) {
      var vals = [], lows = [];
      for (var i = 0; i < n; i++) {
        var raw = s.values[i];
        if (c.stacked) {
          var v = raw === null ? 0 : Number(raw);
          if (v >= 0) { lows[i] = pos[i]; pos[i] += v; vals[i] = pos[i]; }
          else { lows[i] = neg[i]; neg[i] += v; vals[i] = neg[i]; }
        } else {
          vals[i] = raw === null ? null : Number(raw);
        }
      }
      var runs = [], run = [];
      for (i = 0; i < n; i++) {
        if (vals[i] === null) { if (run.length) runs.push(run); run = []; }
        else run.push(i);
      }
      if (run.length) runs.push(run);

      out += '<g class="gk-chart-series gk-chart-c' + s.color + '" data-gk-chart-s="' + s.index + '">';
      var d;
      if (area) {
        runs.forEach(function (r) {
          if (r.length < 2) return;
          var d = "";
          r.forEach(function (i, k) { d += (k === 0 ? "M" : "L") + num(cx(i)) + "," + num(y(vals[i])); });
          if (c.stacked) {
            for (var k = r.length - 1; k >= 0; k--) d += "L" + num(cx(r[k])) + "," + num(y(lows[r[k]]));
          } else {
            d += "L" + num(cx(r[r.length - 1])) + "," + num(base) + "L" + num(cx(r[0])) + "," + num(base);
          }
          out += '<path class="gk-chart-area" d="' + d + 'Z"/>';
        });
      }
      d = "";
      runs.forEach(function (r) {
        if (r.length < 2) return;
        r.forEach(function (i, k) { d += (k === 0 ? "M" : "L") + num(cx(i)) + "," + num(y(vals[i])); });
      });
      if (d !== "") out += '<path class="gk-chart-line" d="' + d + '"/>';
      runs.forEach(function (r) {
        r.forEach(function (i) {
          var cls = "gk-chart-dot" + (dots ? " gk-chart-dot-on" : (r.length === 1 ? " gk-chart-dot-on" : ""));
          out += marker(cls, i, cx(i), y(vals[i]), c.markers ? s.color : 1);
        });
      });
      out += "</g>";
    });
    return out;
  }

  function marker(cls, i, x, y, shape) {
    var a = ' class="' + cls + '"' + (i === null ? "" : ' data-gk-chart-i="' + i + '"');
    var n = num;
    switch (shape) {
      case 2: return "<path" + a + ' d="M' + n(x - 3.5) + "," + n(y - 3.5) + "H" + n(x + 3.5) + "V" + n(y + 3.5) + "H" + n(x - 3.5) + 'Z"/>';
      case 3: return "<path" + a + ' d="M' + n(x) + "," + n(y - 4.5) + "L" + n(x + 4.5) + "," + n(y) + "L" + n(x) + "," + n(y + 4.5) + "L" + n(x - 4.5) + "," + n(y) + 'Z"/>';
      case 4: return "<path" + a + ' d="M' + n(x) + "," + n(y - 4.5) + "L" + n(x + 4.5) + "," + n(y + 3.5) + "L" + n(x - 4.5) + "," + n(y + 3.5) + 'Z"/>';
      case 5: return "<path" + a + ' d="M' + n(x) + "," + n(y + 4.5) + "L" + n(x + 4.5) + "," + n(y - 3.5) + "L" + n(x - 4.5) + "," + n(y - 3.5) + 'Z"/>';
      default: return "<circle" + a + ' cx="' + n(x) + '" cy="' + n(y) + '" r="4"/>';
    }
  }

  function hbarBody(c, W, H, geo) {
    var vis = visible(c);
    var labels = c.labels;
    var n = labels.length;
    var long = 0;
    labels.forEach(function (l) { long = Math.max(long, len(l)); });
    var left = Math.max(40, Math.min(Math.min(long, 24) * CH + 12, Math.floor(W * 2 / 5)));
    var top = 8;
    var bottom = H - 28;
    var plotH = bottom - top;
    var plotW0 = W - left - 12;
    var target = Math.max(2, Math.min(8, Math.floor(plotW0 / 90)));
    var ax = valueAxis(c, vis, target);
    var tickLong = 0;
    ax.ticks.forEach(function (t) { tickLong = Math.max(tickLong, len(t.text)); });
    var right = W - Math.max(12, Math.floor(tickLong * CH / 2) + 4);
    if (right - left < 40) right = left + 40;
    var plotW = right - left;
    var span = ax.hi - ax.lo;
    var x = function (v) { return left + (v - ax.lo) / span * plotW; };
    var band = plotH / n;
    var cy = function (i) { return top + band * i + band / 2; };
    geo.axis = "y"; geo.start = top; geo.band = band; geo.n = n; geo.left = left; geo.right = right; geo.cx = cy;

    var out = '<g class="gk-chart-grid">';
    ax.ticks.forEach(function (t) {
      var tx = num(x(t.v));
      out += '<line class="gk-chart-gridline' + (t.v === 0 ? " gk-chart-zero" : "") + '" x1="' + tx + '" y1="' + num(top) +
        '" x2="' + tx + '" y2="' + num(bottom) + '"/>';
    });
    out += '</g><g class="gk-chart-axis">';
    ax.ticks.forEach(function (t) {
      out += '<text x="' + num(x(t.v)) + '" y="' + num(H - 10) + '" text-anchor="middle">' + esc(t.text) + "</text>";
    });
    var step = Math.max(1, Math.ceil(16 / band));
    var chars = Math.max(2, Math.floor((left - 12) / CH));
    for (var i = 0; i < n; i += step) out += label(labels[i], chars, left - 8, cy(i) + 4, "end");
    out += '</g><g class="gk-chart-marks">';

    var k = vis.length;
    if (k > 0) {
      var base = x(Math.max(ax.lo, Math.min(0, ax.hi)));
      var single = c.stacked || k === 1;
      var barW = single ? Math.min(BAR_MAX, band * 0.64) : Math.min(BAR_MAX, (band * 0.72 - GAP * (k - 1)) / k);
      if (barW < 1) barW = 1;
      var groupW = single ? barW : barW * k + GAP * (k - 1);
      var pos = [], neg = [], seenPos = [], seenNeg = [], lastPos = [], lastNeg = [];
      for (i = 0; i < n; i++) { pos.push(0); neg.push(0); seenPos.push(false); seenNeg.push(false); lastPos.push(-1); lastNeg.push(-1); }
      var j, s, v;
      if (c.stacked) {
        for (j = 0; j < k; j++) {
          for (i = 0; i < n; i++) {
            v = vis[j].values[i] === null ? 0 : Number(vis[j].values[i]);
            if (v > 0) lastPos[i] = j;
            else if (v < 0) lastNeg[i] = j;
          }
        }
      }
      for (j = 0; j < k; j++) {
        s = vis[j];
        out += '<g class="gk-chart-series gk-chart-c' + s.color + '" data-gk-chart-s="' + s.index + '">';
        for (i = 0; i < n; i++) {
          if (s.values[i] === null) continue;
          v = Number(s.values[i]);
          if (v === 0) continue;
          var a = cy(i) - groupW / 2 + (single ? 0 : j * (barW + GAP));
          var right2 = v > 0;
          var b0, b1, isFirst, round;
          if (c.stacked) {
            if (right2) {
              b0 = x(pos[i]); pos[i] += v; b1 = x(pos[i]);
              isFirst = !seenPos[i]; seenPos[i] = true; round = lastPos[i] === j;
            } else {
              b0 = x(neg[i]); neg[i] += v; b1 = x(neg[i]);
              isFirst = !seenNeg[i]; seenNeg[i] = true; round = lastNeg[i] === j;
            }
            if (!isFirst) b0 = right2 ? b0 + GAP : b0 - GAP;
          } else {
            b0 = base;
            b1 = x(v);
            isFirst = true;
            round = true;
          }
          var h = right2 ? b1 - b0 : b0 - b1;
          if (h < 1) {
            if (!isFirst) continue;
            b1 = right2 ? b0 + 1 : b0 - 1;
            h = 1;
          }
          out += '<path class="gk-chart-bar" data-gk-chart-i="' + i + '" d="' +
            barPath(a, barW, b0, b1, round ? Math.min(RADIUS, barW / 2, h) : 0, true) + '"/>';
        }
        out += "</g>";
      }
    }
    return out + "</g>";
  }

  function parts(c) {
    var values = c.series[0] ? c.series[0].values : [];
    var out = [];
    var other = 0;
    c.labels.forEach(function (l, i) {
      var v = values[i] === null || values[i] === undefined ? 0 : Number(values[i]);
      if (i < SLOTS) out.push({ label: l, value: v, canon: canon(v), slot: i + 1, index: i });
      else other += v;
    });
    if (c.labels.length > SLOTS) out.push({ label: word("other"), value: other, canon: canon(other), slot: "other", index: SLOTS });
    return out;
  }

  function donutBody(c, S, geo) {
    var ps = parts(c);
    var total = 0;
    ps.forEach(function (p) { total += p.value; });
    var ctr = S / 2;
    var R = ctr - 2;
    var r = R * 0.62;
    var n = num;
    geo.axis = "donut"; geo.n = ps.length; geo.parts = ps; geo.ctr = ctr; geo.R = R; geo.r = r;
    var out = '<g class="gk-chart-marks">';
    if (total <= 0) {
      out += '<circle class="gk-chart-track" cx="' + n(ctr) + '" cy="' + n(ctr) + '" r="' + n((R + r) / 2) + '" stroke-width="' + n(R - r) + '"/>';
    }
    var nonZero = 0;
    ps.forEach(function (p) { if (p.value > 0) nonZero++; });
    var start = -Math.PI / 2;
    var tau = 2 * Math.PI;
    var cum = 0;
    geo.angles = [];
    ps.forEach(function (p) {
      if (total <= 0 || p.value <= 0) { geo.angles.push(null); return; }
      var a0 = start + tau * (cum / total);
      cum += p.value;
      var a1 = start + tau * (cum / total);
      geo.angles.push([a0, a1]);
      var cls = "gk-chart-slice gk-chart-" + (p.slot === "other" ? "other" : "c" + p.slot);
      var attr = ' class="' + cls + '" data-gk-chart-i="' + p.index + '"';
      if (nonZero === 1) {
        out += "<path" + attr + ' fill-rule="evenodd" d="M' + n(ctr) + "," + n(ctr - R) + "A" + n(R) + "," + n(R) + " 0 1 1 " + n(ctr) + "," + n(ctr + R) +
          "A" + n(R) + "," + n(R) + " 0 1 1 " + n(ctr) + "," + n(ctr - R) + "Z" +
          "M" + n(ctr) + "," + n(ctr - r) + "A" + n(r) + "," + n(r) + " 0 1 0 " + n(ctr) + "," + n(ctr + r) +
          "A" + n(r) + "," + n(r) + " 0 1 0 " + n(ctr) + "," + n(ctr - r) + 'Z"/>';
        return;
      }
      var po = 1 / R;
      var pi = 1 / r;
      if (a1 - a0 <= 2 * pi) return;
      var o0 = a0 + po, o1 = a1 - po, i0 = a0 + pi, i1 = a1 - pi;
      out += "<path" + attr + ' d="M' + n(ctr + R * cos(o0)) + "," + n(ctr + R * sin(o0)) +
        "A" + n(R) + "," + n(R) + " 0 " + (o1 - o0 > Math.PI ? "1" : "0") + " 1 " + n(ctr + R * cos(o1)) + "," + n(ctr + R * sin(o1)) +
        "L" + n(ctr + r * cos(i1)) + "," + n(ctr + r * sin(i1)) +
        "A" + n(r) + "," + n(r) + " 0 " + (i1 - i0 > Math.PI ? "1" : "0") + " 0 " + n(ctr + r * cos(i0)) + "," + n(ctr + r * sin(i0)) +
        'Z"/>';
    });
    out += "</g>";
    var text = formatValue(canon(total), c.format);
    if (len(text) * 11 > 2 * r - 8) text = compact(total, c.format);
    if (len(text) * 11 <= 2 * r - 8) {
      out += '<text class="gk-chart-total" x="' + n(ctr) + '" y="' + n(ctr + 2) + '" text-anchor="middle">' + esc(text) + "</text>" +
        '<text class="gk-chart-total-label" x="' + n(ctr) + '" y="' + n(ctr + 20) + '" text-anchor="middle">' + esc(word("total")) + "</text>";
    }
    return out;
  }

  function summary(c) {
    var f = c.format;
    var labels = c.labels;
    var n = labels.length;
    var type = word("type_" + c.type + (c.stacked ? "_stacked" : ""));
    if (c.type === "donut") {
      var ps = parts(c);
      var total = 0;
      ps.forEach(function (p) { total += p.value; });
      var list = ps.map(function (p) {
        return fill(word("part"), { label: p.label, value: formatValue(p.canon, f), share: share(p.value, total, f) });
      });
      return fill(word("summary_donut"), { type: type, total: formatValue(canon(total), f), parts: list.join("; ") });
    }
    var out = n === 1
      ? fill(word("summary_one"), { type: type, first: labels[0] })
      : fill(word("summary"), { type: type, n: String(n), first: labels[0], last: labels[n - 1] });
    var vis = visible(c);
    vis.forEach(function (s) {
      var first = null, last = null, min = null, max = null, minAt = 0, maxAt = 0;
      s.values.forEach(function (v, i) {
        if (v === null) return;
        var fv = Number(v);
        if (first === null) first = v;
        last = v;
        if (min === null || fv < Number(min)) { min = v; minAt = i; }
        if (max === null || fv > Number(max)) { max = v; maxAt = i; }
      });
      out += " " + (first === null
        ? fill(word("summary_none"), { name: s.name })
        : fill(word("summary_series"), {
          name: s.name, first: formatValue(first, f), last: formatValue(last, f),
          min: formatValue(min, f), minAt: labels[minAt], max: formatValue(max, f), maxAt: labels[maxAt]
        }));
    });
    if (c.stacked) {
      var best = null, at = 0;
      labels.forEach(function (_, i) {
        var sum = 0;
        vis.forEach(function (s) { sum += s.values[i] === null ? 0 : Number(s.values[i]); });
        if (best === null || sum > best) { best = sum; at = i; }
      });
      if (best !== null && vis.length) out += " " + fill(word("summary_total"), { max: formatValue(canon(best), f), maxAt: labels[at] });
    }
    return out;
  }

  function legend(c) {
    if (isEmpty(c)) return "";
    var out;
    if (c.type === "donut") {
      var ps = parts(c);
      var total = 0;
      ps.forEach(function (p) { total += p.value; });
      out = '<ul class="gk-chart-legend">';
      ps.forEach(function (p) {
        out += '<li class="gk-chart-key gk-chart-' + (p.slot === "other" ? "other" : "c" + p.slot) + '">' +
          keyMark("donut", 1, false) +
          '<span class="gk-chart-key-label">' + esc(p.label) + "</span>" +
          '<span class="gk-chart-key-value">' + esc(formatValue(p.canon, c.format)) + " \u00b7 " + esc(share(p.value, total, c.format)) + "</span></li>";
      });
      return out + "</ul>";
    }
    if (c.series.length < 2) return "";
    out = '<ul class="gk-chart-legend">';
    c.series.forEach(function (s, i) {
      out += '<li class="gk-chart-key gk-chart-c' + s.color + (s.hidden ? " gk-chart-key-off" : "") + '" data-gk-chart-key="' + i + '">' +
        keyMark(c.type, s.color, c.markers) +
        '<span class="gk-chart-key-label">' + esc(s.name) + "</span></li>";
    });
    return out + "</ul>";
  }

  function keyMark(type, slot, markers) {
    var inner = type === "line"
      ? '<line class="gk-chart-line" x1="1" y1="6" x2="23" y2="6"/>' + (markers ? marker("gk-chart-dot gk-chart-dot-on", null, 12, 6, slot) : "")
      : '<rect class="gk-chart-swatch" x="6" y="1" width="12" height="10" rx="2"/>';
    return '<svg class="gk-chart-keymark" viewBox="0 0 24 12" width="24" height="12" aria-hidden="true">' + inner + "</svg>";
  }

  function dataTable(c, title) {
    var f = c.format;
    var numA = ' class="gk-num"';
    var head = '<th scope="col">' + esc(word("category")) + "</th>";
    var rows = "";
    if (c.type === "donut") {
      var values = c.series[0] ? c.series[0].values : [];
      var total = 0;
      values.forEach(function (v) { total += v === null ? 0 : Number(v); });
      head += '<th scope="col"' + numA + ">" + esc(c.series[0] ? c.series[0].name : word("value")) + '</th><th scope="col"' + numA + ">" + esc(word("share")) + "</th>";
      c.labels.forEach(function (l, i) {
        var v = values[i] === undefined ? null : values[i];
        rows += '<tr><th scope="row">' + esc(l) + "</th><td" + numA + ">" + esc(formatValue(v, f)) + "</td><td" + numA + ">" +
          esc(share(v === null ? 0 : Number(v), total, f)) + "</td></tr>";
      });
      rows += '<tr><th scope="row">' + esc(word("total")) + "</th><td" + numA + ">" + esc(formatValue(canon(total), f)) + "</td><td" + numA + ">" +
        esc(share(total, total, f)) + "</td></tr>";
    } else {
      c.series.forEach(function (s) { head += '<th scope="col"' + numA + ">" + esc(s.name) + "</th>"; });
      if (c.stacked) head += '<th scope="col"' + numA + ">" + esc(word("total")) + "</th>";
      c.labels.forEach(function (l, i) {
        rows += '<tr><th scope="row">' + esc(l) + "</th>";
        var sum = 0;
        c.series.forEach(function (s) {
          rows += "<td" + numA + ">" + esc(formatValue(s.values[i], f)) + "</td>";
          sum += s.values[i] === null ? 0 : Number(s.values[i]);
        });
        if (c.stacked) rows += "<td" + numA + ">" + esc(formatValue(canon(sum), f)) + "</td>";
        rows += "</tr>";
      });
    }
    var table = '<div class="gk-chart-table-wrap"><table class="gk-table gk-chart-table"><caption class="gk-sr-only">' + esc(title) + "</caption>" +
      "<thead><tr>" + head + "</tr></thead><tbody>" + rows + "</tbody></table></div>";
    if (c.table === "hidden") return '<div class="gk-chart-data gk-sr-only">' + table + "</div>";
    return '<details class="gk-chart-data"' + (c.table === "open" ? " open" : "") + "><summary>" + esc(word("show_table")) + "</summary>" + table + "</details>";
  }

  function spark(c) {
    var W = c.width, H = c.height;
    var s = c.series[0] || { name: "", values: [], color: 1 };
    var f = c.format;
    var lbl = c.title !== "" ? c.title : word("trend");
    var vals = s.values;
    var n = vals.length;
    var first = null, last = null, min = null, max = null, count = 0;
    vals.forEach(function (v) {
      if (v === null) return;
      count++;
      if (first === null) first = v;
      last = v;
      if (min === null || Number(v) < Number(min)) min = v;
      if (max === null || Number(v) > Number(max)) max = v;
    });
    var name = first === null
      ? fill(word("spark_empty"), { label: lbl })
      : fill(word("spark"), {
        label: lbl, n: String(count), first: formatValue(first, f), last: formatValue(last, f),
        min: formatValue(min, f), max: formatValue(max, f)
      });
    var out = '<svg class="gk-chart-spark gk-chart-c' + s.color + '" viewBox="0 0 ' + W + " " + H + '" width="' + W + '" height="' + H + '" role="img">' +
      "<title>" + esc(name) + "</title>";
    if (first !== null) {
      var pad = 3;
      var lo = Number(min), hi = Number(max);
      var x = function (i) { return n === 1 ? W / 2 : pad + (W - 2 * pad) * i / (n - 1); };
      var y = function (v) { return hi === lo ? H / 2 : pad + (hi - v) / (hi - lo) * (H - 2 * pad); };
      var d = "", area = "", run = [];
      var flush = function () {
        if (run.length >= 2) {
          var part = "";
          run.forEach(function (p, k) { part += (k === 0 ? "M" : "L") + num(x(p[0])) + "," + num(y(p[1])); });
          d += part;
          area += part + "L" + num(x(run[run.length - 1][0])) + "," + num(H) + "L" + num(x(run[0][0])) + "," + num(H) + "Z";
        }
        run = [];
      };
      var lastI = 0;
      vals.forEach(function (v, i) {
        if (v === null) { flush(); return; }
        run.push([i, Number(v)]);
        lastI = i;
      });
      flush();
      if (c.area && area !== "") out += '<path class="gk-chart-area" d="' + area + '"/>';
      if (d !== "") out += '<path class="gk-chart-line" d="' + d + '"/>';
      out += '<circle class="gk-chart-dot gk-chart-dot-on" cx="' + num(x(lastI)) + '" cy="' + num(y(Number(last))) + '" r="2.5"/>';
    }
    return out + "</svg>";
  }

  /* ── Numbers as text: Chart::canon(), num(), formatValue() … ───────────── */

  function canon(x) {
    if (x === 0 || !isFinite(x)) return "0";
    var s = String(x);
    var neg = s.charAt(0) === "-";
    if (neg) s = s.slice(1);
    var ePos = s.search(/e/i), exp = 0;
    if (ePos >= 0) { exp = parseInt(s.slice(ePos + 1), 10); s = s.slice(0, ePos); }
    var dot = s.indexOf(".");
    var digits = dot < 0 ? s : s.slice(0, dot) + s.slice(dot + 1);
    var point = (dot < 0 ? s.length : dot) + exp;
    var trimmed = digits.replace(/^0+/, "");
    point -= digits.length - trimmed.length;
    digits = trimmed.replace(/0+$/, "");
    if (digits === "") return "0";
    var int, frac;
    if (point <= 0) { int = "0"; frac = new Array(-point + 1).join("0") + digits; }
    else if (point >= digits.length) { int = digits + new Array(point - digits.length + 1).join("0"); frac = ""; }
    else { int = digits.slice(0, point); frac = digits.slice(point); }
    return (neg ? "-" : "") + int + (frac !== "" ? "." + frac : "");
  }

  function num(x) {
    var n = Math.floor(Math.abs(x) * 100 + 0.5);
    if (n === 0) return "0";
    var int = Math.floor(n / 100);
    var frac = n - int * 100;
    var s = (x < 0 ? "-" : "") + int;
    if (frac === 0) return s;
    return s + "." + ((frac < 10 ? "0" : "") + frac).replace(/0+$/, "");
  }

  function roundDigits(c, d) {
    var s = c.replace(/^-/, "");
    var dot = s.indexOf(".");
    var int = dot < 0 ? s : s.slice(0, dot);
    var frac = dot < 0 ? "" : s.slice(dot + 1);
    if (frac.length > d) {
      var up = frac.charAt(d) >= "5";
      frac = frac.slice(0, d);
      if (up) {
        var all = (int + frac).split("");
        var i = all.length - 1;
        while (i >= 0 && all[i] === "9") { all[i] = "0"; i--; }
        if (i < 0) all.unshift("1");
        else all[i] = String.fromCharCode(all[i].charCodeAt(0) + 1);
        all = all.join("");
        int = all.slice(0, all.length - d);
        frac = all.slice(all.length - d);
      }
    } else {
      while (frac.length < d) frac += "0";
    }
    return [int, frac, (int + frac).replace(/0/g, "") === ""];
  }

  function upTo(c, d, f) {
    var r = roundDigits(c, d);
    return group(r[0], r[1].replace(/0+$/, ""), f);
  }

  function group(int, frac, f) {
    var loc = LOCALES[f.locale];
    var out = "";
    for (var i = 0; i < int.length; i++) {
      if (i > 0 && (int.length - i) % 3 === 0) out += loc[1];
      out += int.charAt(i);
    }
    return out + (frac !== "" ? loc[0] + frac : "");
  }

  function compose(digits, neg, f) {
    var loc = LOCALES[f.locale];
    var text;
    if (f.kind === "money") {
      var sym = SYMBOLS[f.currency] || f.currency;
      var space = /^[A-Z]+$/.test(sym) ? "\u00a0" : "";
      text = loc[2] === "after" ? digits + "\u00a0" + sym : sym + space + digits;
    } else if (f.kind === "percent") {
      text = digits + loc[3];
    } else {
      text = digits;
    }
    return (neg ? "-" : "") + text;
  }

  function formatValue(c, f) {
    if (c === null || c === undefined) return "\u2014";
    var d = f.decimals, r;
    if (d !== null) r = roundDigits(c, d);
    else if (f.kind === "money") r = roundDigits(c, 2);
    else {
      r = roundDigits(c, f.kind === "percent" ? 1 : 2);
      r[1] = r[1].replace(/0+$/, "");
    }
    return compose(group(r[0], r[1], f), !r[2] && c.charAt(0) === "-", f);
  }

  function compact(v, f) {
    var a = Math.abs(v);
    var tier = a >= 1e10 ? 3 : (a >= 1e7 ? 2 : (a >= 1e4 ? 1 : 0));
    if (tier === 0) return formatValue(canon(v), f);
    var scaled = canon(a / exact(1, 3 * tier));
    return compose(upTo(scaled, 1, f) + LOCALES[f.locale][4][tier - 1], v < 0, f);
  }

  function share(v, total, f) {
    if (total <= 0) return "\u2014";
    var p = canon(v / total * 100);
    return compose(upTo(p, 1, f), false, { kind: "percent", currency: f.currency, locale: f.locale, decimals: f.decimals });
  }

  /* ── Text helpers ──────────────────────────────────────────────────────── */

  function len(s) { return Array.from(s).length; }
  function cut(s, max) {
    var a = Array.from(s);
    return a.length <= max ? s : a.slice(0, max - 1).join("") + "\u2026";
  }
  // Chart::key(): the id as a piece of an HTML id, or FNV-1a over its UTF-8 bytes.
  function keyOf(id) {
    if (/^[A-Za-z0-9_-]{1,64}$/.test(id)) return id;
    var bytes = unescape(encodeURIComponent(id));
    var h = 0x811c9dc5;
    for (var i = 0; i < bytes.length; i++) {
      h ^= bytes.charCodeAt(i);
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return "h" + ("0000000" + h.toString(16)).slice(-8);
  }
  // Chart::sin(): a Taylor series on a quarter turn — the same digits as PHP, not Math.sin's.
  function sin(x) {
    var tau = 2 * Math.PI;
    x -= tau * Math.floor(x / tau + 0.5);
    if (x > Math.PI / 2) x = Math.PI - x;
    else if (x < -Math.PI / 2) x = -Math.PI - x;
    var s = x * x;
    return x * (1 + s * (-1 / 6 + s * (1 / 120 + s * (-1 / 5040 + s * (1 / 362880 + s * (-1 / 39916800 +
      s * (1 / 6227020800 + s * (-1 / 1307674368000 + s * (1 / 355687428096000)))))))));
  }
  function cos(x) { return sin(x + Math.PI / 2); }

  /* ── The chart on the page ─────────────────────────────────────────────── */

  var CHART = ".gk-chart[data-gk-chart]";
  var charts = new WeakMap();
  var MIN_FIT = 200;

  function get(el) {
    if (typeof el === "string") {
      el = document.getElementById("gk-chart-" + keyOf(el)) || document.getElementById(el) || document.querySelector(el);
    }
    var fig = el && el.closest ? el.closest(CHART) : null;
    return fig ? setup(fig) : null;
  }

  function setup(fig) {
    var st = charts.get(fig);
    if (st) return st;
    var cfg;
    try { cfg = normalize(JSON.parse(fig.getAttribute("data-gk-chart"))); } catch (e) { return null; }
    st = { fig: fig, cfg: cfg, width: cfg.width, active: -1, geo: null, pointer: false };
    charts.set(fig, st);
    fig.setAttribute("data-gk-chart-ready", "");
    enhance(st);
    fit(st);
    if (window.ResizeObserver) {
      var raf = 0;
      st.ro = new ResizeObserver(function () {
        if (raf) return;
        raf = requestAnimationFrame(function () { raf = 0; fit(st); });
      });
      st.ro.observe(fig);
    }
    fig.addEventListener("click", function (e) { onClick(st, e); });
    return st;
  }

  // The parts the script adds around the server's markup: focus and keys on the plot, a crosshair,
  // the tooltip, a live region, and the legend's buttons.
  function enhance(st) {
    var fig = st.fig, c = st.cfg;
    st.plot = fig.querySelector(".gk-chart-plot");
    if (!st.plot) return;
    st.svg = st.plot.querySelector("svg");
    st.geo = measure(c, st.width);
    var title = c.title !== "" ? c.title : word("label");
    var empty = isEmpty(c);
    if (!empty) {
      st.plot.tabIndex = 0;
      st.plot.setAttribute("role", "application");
      st.plot.setAttribute("aria-label", title + ". " + word("hint"));
      st.plot.setAttribute("aria-describedby", "gk-chart-" + keyOf(c.id) + "-d");
    }
    st.cross = add(st.plot, "div", "gk-chart-cross");
    st.tip = add(st.plot, "div", "gk-chart-tip");
    st.tip.setAttribute("aria-hidden", "true");
    st.live = add(st.plot, "div", "gk-chart-live gk-sr-only");
    st.live.setAttribute("aria-live", "polite");
    hide(st);

    if (!st.bound) {
      st.bound = true;
      fig.addEventListener("pointermove", function (e) { onMove(st, e); });
      fig.addEventListener("pointerdown", function (e) { if (e.pointerType !== "mouse") onMove(st, e); });
      fig.addEventListener("pointerleave", function () { if (st.pointer) { st.pointer = false; if (document.activeElement !== st.plot) clear(st); } });
      fig.addEventListener("keydown", function (e) { onKey(st, e); });
      fig.addEventListener("focusin", function (e) { if (e.target === st.plot && st.active < 0) show(st, firstIndex(st), false); });
      fig.addEventListener("focusout", function (e) { if (e.target === st.plot && !st.pointer) clear(st); });
    }

    // The legend: a button per series, aria-pressed — two series or more (a donut's lists its parts).
    if (c.type !== "donut" && c.series.length > 1) {
      fig.querySelectorAll(".gk-chart-legend > .gk-chart-key[data-gk-chart-key]").forEach(function (li) {
        if (li.querySelector(".gk-chart-toggle")) return;
        var i = parseInt(li.getAttribute("data-gk-chart-key"), 10);
        var b = document.createElement("button");
        b.type = "button";
        b.className = "gk-chart-toggle";
        b.setAttribute("aria-pressed", c.series[i] && c.series[i].hidden ? "false" : "true");
        while (li.firstChild) b.appendChild(li.firstChild);
        li.appendChild(b);
      });
    }
  }

  function add(parent, tag, cls) {
    var el = parent.querySelector(":scope > ." + cls.split(" ")[0]);
    if (!el) {
      el = document.createElement(tag);
      el.className = cls;
      parent.appendChild(el);
    }
    return el;
  }

  function measure(c, width) {
    var geo = {};
    svg(c, width, geo);
    return geo;
  }

  // Draw the chart again at the width of its box: one px of the viewBox is one px on the screen.
  function fit(st) {
    if (!st.plot || st.cfg.type === "donut") return;
    var w = Math.floor(st.plot.clientWidth);
    if (w < 1) return;
    w = Math.max(MIN_FIT, Math.min(4000, w));
    st.fig.setAttribute("data-gk-chart-fit", "");
    if (w === st.width) return;
    st.width = w;
    redrawSvg(st);
  }

  function redrawSvg(st) {
    var geo = {};
    var html = svg(st.cfg, st.width, geo);
    var tpl = document.createElement("template");
    tpl.innerHTML = html;
    var fresh = tpl.content.firstChild;
    if (st.svg && st.svg.parentNode === st.plot) st.plot.replaceChild(fresh, st.svg);
    else st.plot.insertBefore(fresh, st.plot.firstChild);
    st.svg = fresh;
    st.geo = geo;
    if (st.active >= 0) show(st, Math.min(st.active, geo.n - 1), false);
  }

  // The whole figure again (update(), a series switched off): the server's markup, then the script's parts.
  function redraw(st) {
    var focus = document.activeElement;
    var where = focus === st.plot ? "plot" : (focus && focus.classList && focus.classList.contains("gk-chart-toggle") && st.fig.contains(focus)
      ? focus.closest("[data-gk-chart-key]").getAttribute("data-gk-chart-key") : null);
    var details = st.fig.querySelector(".gk-chart-data");
    var open = details && details.tagName === "DETAILS" ? details.open : null;
    var tpl = document.createElement("template");
    tpl.innerHTML = draw(st.cfg, st.cfg.type === "donut" ? st.cfg.width : st.width);
    var fresh = tpl.content.firstChild;
    st.fig.className = fresh.className;
    st.fig.setAttribute("data-gk-chart", fresh.getAttribute("data-gk-chart"));
    while (st.fig.firstChild) st.fig.removeChild(st.fig.firstChild);
    while (fresh.firstChild) st.fig.appendChild(fresh.firstChild);
    var active = st.active;
    st.active = -1;
    enhance(st);
    var nd = st.fig.querySelector("details.gk-chart-data");
    if (nd && open !== null && st.cfg.table === "toggle") nd.open = open;
    if (where === "plot" && st.plot.tabIndex === 0) st.plot.focus();
    else if (where !== null) {
      var b = st.fig.querySelector('[data-gk-chart-key="' + where + '"] .gk-chart-toggle');
      if (b) b.focus();
    }
    if (active >= 0 && (where === "plot" || st.pointer) && !st.geo.empty) show(st, Math.min(active, st.geo.n - 1), false);
  }

  /* ── Hover, keys, tooltip ──────────────────────────────────────────────── */

  function scale(st) {
    var r = st.svg.getBoundingClientRect();
    return { r: r, k: r.width / st.geo.W, ky: r.height / st.geo.H };
  }

  function indexAt(st, e) {
    var g = st.geo;
    if (!g || g.empty || !st.svg) return -1;
    if (g.axis === "donut") {
      var mark = e.target && e.target.closest ? e.target.closest("[data-gk-chart-i]") : null;
      return mark && st.svg.contains(mark) ? parseInt(mark.getAttribute("data-gk-chart-i"), 10) : -1;
    }
    var s = scale(st);
    var u = g.axis === "x" ? (e.clientX - s.r.left) / s.k : (e.clientY - s.r.top) / s.ky;
    var i = Math.floor((u - g.start) / g.band);
    return i >= 0 && i < g.n ? i : -1;
  }

  function onMove(st, e) {
    if (st.tip && st.tip.contains(e.target)) return;   // the pointer may rest on the tooltip (WCAG 1.4.13)
    if (onTheWay(st, e)) return;
    if (!st.plot || !st.plot.contains(e.target)) {
      if (st.pointer) { st.pointer = false; if (document.activeElement !== st.plot) clear(st); }
      return;
    }
    st.pointer = true;
    var i = indexAt(st, e);
    if (i < 0) { if (document.activeElement !== st.plot) clear(st); return; }
    if (i !== st.active) show(st, i, false);
  }

  // The way from a category to its tooltip belongs to that category: at the tooltip's height,
  // between the crosshair and the tooltip's near edge, the pointer crosses the next category
  // without the tooltip running away from it — so it can be reached and rested on (WCAG 1.4.13).
  function onTheWay(st, e) {
    if (st.active < 0 || !st.tip || st.tip.hidden || st.anchor == null) return false;
    var t = st.tip.getBoundingClientRect(), p = st.plot.getBoundingClientRect();
    var ax = p.left + st.anchor;
    var near = t.left >= ax ? t.left : t.right;
    return e.clientY >= t.top && e.clientY <= t.bottom &&
      e.clientX >= Math.min(ax, near) && e.clientX <= Math.max(ax, near);
  }

  function onClick(st, e) {
    var b = e.target.closest ? e.target.closest(".gk-chart-toggle") : null;
    if (b && st.fig.contains(b)) {
      var k = parseInt(b.closest("[data-gk-chart-key]").getAttribute("data-gk-chart-key"), 10);
      toggle(st, k, b.getAttribute("aria-pressed") !== "true", true);
      return;
    }
    if (!st.plot || !st.plot.contains(e.target) || (st.tip && st.tip.contains(e.target))) return;
    var i = indexAt(st, e);
    if (i < 0) return;
    var mark = e.target.closest ? e.target.closest("[data-gk-chart-i]") : null;
    var group = mark ? mark.closest("[data-gk-chart-s]") : null;
    var series = st.cfg.type === "donut" ? 0 : (group ? parseInt(group.getAttribute("data-gk-chart-s"), 10) : null);
    show(st, i, false);
    select(st, i, series);
  }

  function onKey(st, e) {
    if (e.target !== st.plot) return;
    var g = st.geo;
    if (!g || g.empty) return;
    var i = st.active, n = g.n;
    var next = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
    if (e.key in next) i = i < 0 ? firstIndex(st) : stepIndex(st, i, next[e.key]);
    else if (e.key === "Home") i = stepIndex(st, -1, 1);
    else if (e.key === "End") i = stepIndex(st, n, -1);
    else if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      if (st.active >= 0) select(st, st.active, st.cfg.type === "donut" ? 0 : null);
      return;
    } else if (e.key === "Escape") {
      if (st.active >= 0) { e.preventDefault(); clear(st); }
      return;
    } else return;
    e.preventDefault();
    if (i >= 0) show(st, i, true);
  }

  // The next category that has a value (a donut: a part), or the one at the end.
  function hasValue(st, i) {
    var c = st.cfg;
    if (c.type === "donut") return st.geo.parts && st.geo.parts[i] !== undefined;
    for (var s = 0; s < c.series.length; s++) if (!c.series[s].hidden && c.series[s].values[i] !== null) return true;
    return false;
  }
  function stepIndex(st, i, d) {
    var n = st.geo.n;
    for (var j = i + d; j >= 0 && j < n; j += d) if (hasValue(st, j)) return j;
    return i >= 0 && i < n ? i : (d > 0 ? 0 : n - 1);
  }
  function firstIndex(st) { return stepIndex(st, -1, 1); }

  // What a category holds: [{ series, name, value, text, color }] (a donut: its part).
  function valuesAt(st, i) {
    var c = st.cfg;
    if (c.type === "donut") {
      var ps = st.geo.parts || parts(c);
      var p = ps[i];
      if (!p) return [];
      var total = 0;
      ps.forEach(function (q) { total += q.value; });
      return [{ series: 0, name: p.label, value: p.value, text: formatValue(p.canon, c.format) + " \u00b7 " + share(p.value, total, c.format), color: p.slot }];
    }
    var out = [];
    c.series.forEach(function (s, k) {
      if (s.hidden) return;
      var v = s.values[i];
      out.push({ series: k, name: s.name, value: v === null ? null : Number(v), text: formatValue(v, c.format), color: s.color });
    });
    return out;
  }

  function labelAt(st, i) {
    if (st.cfg.type === "donut") { var p = (st.geo.parts || [])[i]; return p ? p.label : ""; }
    return st.cfg.labels[i] == null ? "" : st.cfg.labels[i];
  }

  function show(st, i, speak) {
    if (!st.svg || !st.geo || st.geo.empty || i < 0) return;
    st.active = i;
    st.plot.classList.add("gk-chart-active");
    st.svg.querySelectorAll(".gk-chart-on").forEach(function (m) { m.classList.remove("gk-chart-on"); });
    st.svg.querySelectorAll('[data-gk-chart-i="' + i + '"]').forEach(function (m) { m.classList.add("gk-chart-on"); });

    var c = st.cfg, g = st.geo, s = scale(st);
    var pr = st.plot.getBoundingClientRect();
    var ox = s.r.left - pr.left, oy = s.r.top - pr.top;
    var rows = valuesAt(st, i);

    // The tooltip: the category, then each value before its series (the value is what is read).
    var tip = st.tip;
    tip.textContent = "";
    var head = document.createElement("div");
    head.className = "gk-chart-tip-title";
    head.textContent = labelAt(st, i);
    tip.appendChild(head);
    rows.forEach(function (r) {
      var row = document.createElement("div");
      row.className = "gk-chart-tip-row gk-chart-" + (r.color === "other" ? "other" : "c" + r.color);
      var key = document.createElement("span");
      key.className = "gk-chart-tip-key";
      var val = document.createElement("span");
      val.className = "gk-chart-tip-value";
      val.textContent = r.text;
      row.appendChild(key);
      row.appendChild(val);
      if (c.type !== "donut") {
        var nm = document.createElement("span");
        nm.className = "gk-chart-tip-name";
        nm.textContent = r.name;
        row.appendChild(nm);
      }
      tip.appendChild(row);
    });
    if (c.stacked && rows.length > 1) {
      var sum = 0;
      rows.forEach(function (r) { sum += r.value === null ? 0 : r.value; });
      var tr = document.createElement("div");
      tr.className = "gk-chart-tip-row gk-chart-tip-total";
      var tv = document.createElement("span");
      tv.className = "gk-chart-tip-value";
      tv.textContent = formatValue(canon(sum), c.format);
      var tn = document.createElement("span");
      tn.className = "gk-chart-tip-name";
      tn.textContent = word("total");
      tr.appendChild(document.createElement("span")).className = "gk-chart-tip-key";
      tr.appendChild(tv);
      tr.appendChild(tn);
      tip.appendChild(tr);
    }
    tip.hidden = false;

    // Where: beside the category (a donut: beside the part), inside the plot, never under the pointer.
    var ax, ay, edge = null;
    st.svg.querySelectorAll(".gk-chart-marks .gk-chart-on").forEach(function (m) {
      var b = m.getBoundingClientRect();
      edge = edge === null ? { top: b.top, right: b.right } : { top: Math.min(edge.top, b.top), right: Math.max(edge.right, b.right) };
    });
    if (g.axis === "x") {
      ax = ox + g.cx(i) * s.k;
      ay = edge ? edge.top - pr.top : oy + g.top * s.ky;
      if (c.type !== "bar") {
        st.cross.hidden = false;
        st.cross.style.left = Math.round(ax) + "px";
        st.cross.style.top = Math.round(oy + g.top * s.ky) + "px";
        st.cross.style.height = Math.max(0, Math.round((g.bottom - g.top) * s.ky)) + "px";
      }
    } else if (g.axis === "y") {
      ay = oy + g.cx(i) * s.ky;
      ax = edge ? edge.right - pr.left : ox + g.left * s.k;
    } else {
      var a = g.angles[i];
      var mid = a ? (a[0] + a[1]) / 2 : -Math.PI / 2;
      ax = ox + (g.ctr + g.R * Math.cos(mid)) * s.k;
      ay = oy + (g.ctr + g.R * Math.sin(mid)) * s.ky;
    }
    var w = tip.offsetWidth, h = tip.offsetHeight;
    var pw = st.plot.clientWidth, ph = st.plot.clientHeight;
    var left = ax + 12;
    if (left + w > pw - 4) left = ax - 12 - w;
    if (left < 4) left = Math.max(4, Math.min(pw - w - 4, ax - w / 2));
    var tTop = Math.max(4, Math.min(ph - h - 4, ay - h / 2));
    tip.style.left = Math.round(left) + "px";
    tip.style.top = Math.round(tTop) + "px";
    st.anchor = ax;

    if (speak) {
      st.live.textContent = labelAt(st, i) + ". " + rows.map(function (r) {
        return c.type === "donut" ? r.text : r.name + ": " + r.text;
      }).join(". ") + ".";
    }
  }

  function hide(st) {
    if (st.tip) st.tip.hidden = true;
    if (st.cross) st.cross.hidden = true;
  }

  function clear(st) {
    st.active = -1;
    hide(st);
    if (st.plot) st.plot.classList.remove("gk-chart-active");
    if (st.svg) st.svg.querySelectorAll(".gk-chart-on").forEach(function (m) { m.classList.remove("gk-chart-on"); });
  }

  function select(st, i, series) {
    st.fig.dispatchEvent(new CustomEvent("gk:chartselect", {
      bubbles: true,
      detail: {
        chart: st.cfg.id, index: i, label: labelAt(st, i), series: series,
        values: valuesAt(st, i).map(function (r) { return { series: r.series, name: r.name, value: r.value, text: r.text }; })
      }
    }));
  }

  function toggle(st, k, on, byPerson) {
    var s = st.cfg.series[k];
    if (!s || st.cfg.type === "donut" || s.hidden === !on) return;
    s.hidden = !on;
    redraw(st);
    var b = st.fig.querySelector('[data-gk-chart-key="' + k + '"] .gk-chart-toggle');
    if (byPerson) {
      st.live.textContent = fill(word(on ? "shown" : "hidden"), { name: s.name });
      st.fig.dispatchEvent(new CustomEvent("gk:charttoggle", {
        bubbles: true, detail: { chart: st.cfg.id, series: k, name: s.name, visible: on }
      }));
    }
    return b;
  }

  /* ── The API ───────────────────────────────────────────────────────────── */

  var api = {
    // Sets up every chart in root (default: the page). A chart inserted later is set up by itself.
    init: function (root) { scan(root || document.documentElement); },
    // New data — any key of the config: labels, series, title, type, format … — drawn at once.
    update: function (el, data) {
      var st = get(el);
      if (!st) return null;
      var raw = JSON.parse(JSON.stringify(st.cfg));
      Object.keys(data || {}).forEach(function (k) { if (k !== "id") raw[k] = data[k]; });
      if (raw.type === "sparkline") { warn(st.cfg.id, "a chart does not become a sparkline: GK.chart.html() draws one."); raw.type = st.cfg.type; }
      st.cfg = normalize(raw);
      redraw(st);
      fit(st);
      return st.fig;
    },
    // The chart's config as it is now (a copy): what GK.chart.update() and GK.chart.html() take.
    data: function (el) {
      var st = get(el);
      return st ? JSON.parse(JSON.stringify(st.cfg)) : null;
    },
    // Switches series k on (true) or off (false); without visible, the other way round.
    toggle: function (el, k, visible) {
      var st = get(el);
      if (!st || !st.cfg.series[k]) return false;
      toggle(st, k, visible === undefined ? st.cfg.series[k].hidden : !!visible, false);
      return !st.cfg.series[k].hidden;
    },
    // The markup Chart::html() writes for a config — byte for byte.
    html: function (config) { return draw(normalize(config)); }
  };
  window.GK = window.GK || {};
  window.GK.chart = api;

  /* ── Starting ──────────────────────────────────────────────────────────── */

  function scan(root) {
    if (!root || root.nodeType !== 1) return;
    if (root.matches(CHART)) setup(root);
    root.querySelectorAll(CHART).forEach(setup);
  }

  function start() {
    scan(document.documentElement);
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
