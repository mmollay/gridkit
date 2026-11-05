/* GridKit filter builder v1.123.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js is
 * one: the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites. Load it after
 * gridkit.js (skeleton.php does); nothing here needs gridkit.js — the words come with the builder's
 * configuration, which GridKit\FilterBuilder writes.
 *
 *   <form class="gk-filter-builder" data-gk-filter-builder="{…configuration…}">   ← GridKit\FilterBuilder
 *     <fieldset class="gk-fb-group">… <li class="gk-fb-row">[field] [operator] [value] …
 *
 * The server's form works on its own: submit buttons add, duplicate and remove (name[cmd]) and the page
 * is drawn again. This script keeps the same state in memory and draws the same markup at once: rows
 * are added, duplicated and removed without a request, the operators follow the field's type and the
 * value fields follow the operator, the summary in words follows what is typed. Apply sends the state
 * as compact JSON in one parameter (?f=["and",["status","is","open"]]) — what FilterBuilder::fromRequest()
 * reads. Every check of the server is made here the same way (the browser test compares them).
 *
 * Events, on the form, bubbling:
 *   gk:filterchange  { name, tree, json, summary }          cancelable: Apply, before the form is sent
 *   gk:filtersave    { name, label, tree, json, summary }   cancelable: Save view, before it is stored
 */
(function () {
  "use strict";

  var SEL = "[data-gk-filter-builder]";
  var KIND = {
    contains: "one", not_contains: "one", equals: "one", not_equals: "one", starts: "one", ends: "one",
    empty: "none", not_empty: "none", eq: "one", ne: "one", lt: "one", lte: "one", gt: "one", gte: "one",
    between: "two", is: "one", is_not: "one", any_of: "list", none_of: "list",
    has_any: "list", has_all: "list", has_none: "list", "true": "none", "false": "none"
  };
  var TEXT_MAX = 200, LIST_MAX = 200, JSON_MAX = 8192, VISIT_MAX = 1000, SAY_MS = 450;
  var NUMBER_RE = /^-?\d{1,15}(?:[.,]\d{1,6})?$/;
  var ICON_ADD = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>';
  var ICON_COPY = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
  var ICON_REMOVE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  var ICON_ERROR = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>';
  var builders = new WeakMap();

  /* ── Small helpers ───────────────────────────────────────────────────── */

  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
  // Lang::t(): each {name} replaced in the order given, every occurrence.
  function tpl(text, params) {
    text = String(text);
    for (var k in params || {}) text = text.split("{" + k + "}").join(String(params[k]));
    return text;
  }
  function own(o, k) { return o !== null && typeof o === "object" && Object.prototype.hasOwnProperty.call(o, k); }
  function isObj(o) { return o !== null && typeof o === "object"; }
  // PHP reads a JSON object with the keys 0, 1, 2 … as a list; so does this.
  function listOf(o) {
    if (Array.isArray(o)) return o;
    if (!isObj(o)) return null;
    var keys = Object.keys(o);
    for (var i = 0; i < keys.length; i++) if (keys[i] !== String(i)) return null;
    return keys.map(function (k) { return o[k]; });
  }
  function values(o) { return Array.isArray(o) ? o.slice() : (isObj(o) ? Object.keys(o).map(function (k) { return o[k]; }) : []); }
  function isScalar(v) { return typeof v === "string" || typeof v === "number" || typeof v === "boolean"; }
  // A scalar as text; a number without exponent (FilterBuilder::str() writes the same digits).
  function str(v) {
    if (typeof v === "number") return isFinite(v) ? plain(v) : "";
    return String(v);
  }
  function chars(s) { return Array.from(s); }

  /* ── The state: the same rules as FilterBuilder.php ──────────────────── */

  function spec(cfg) {
    var fields = {}, order = [], ops = {};
    cfg.fields.forEach(function (f) {
      fields[f.key] = f;
      order.push(f.key);
      ops[f.key] = f.ops.map(function (o) { return o[0]; });
    });
    return { fields: fields, order: order, ops: ops, maxDepth: cfg.maxDepth, maxRows: cfg.maxRows };
  }

  function text(v, max) {
    v = String(v).replace(/[\x00-\x1F\x7F]/g, "").replace(/^ +| +$/g, "");
    var c = chars(v);
    return c.length > max ? c.slice(0, max).join("") : v;
  }

  function number(v) {
    if (typeof v === "number") {
      if (!isFinite(v) || Math.abs(v) >= 1e15) return null;
      if (Number.isInteger(v)) return v;
      v = Math.round(v * 1e6) / 1e6;
      return Math.floor(v) === v ? v : v;
    }
    if (typeof v !== "string") return null;
    v = v.replace(/^ +| +$/g, "");
    if (!NUMBER_RE.test(v)) return null;
    v = v.replace(",", ".");
    if (v.indexOf(".") < 0) { var i = parseInt(v, 10); return i === 0 ? 0 : i; }
    var n = parseFloat(v);
    return n === 0 ? 0 : n;
  }

  function daysIn(y, m) {
    if (m === 2) return (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28;
    return [4, 6, 9, 11].indexOf(m) >= 0 ? 30 : 31;
  }
  // DateRange::date(): a real day 'Y-m-d', years 1 to 9999.
  function date(v) {
    if (typeof v !== "string") return null;
    v = v.replace(/^[ \t\n\r]+|[ \t\n\r]+$/g, "");
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
    if (!m) return null;
    var y = +m[1], mo = +m[2], d = +m[3];
    return y >= 1 && mo >= 1 && mo <= 12 && d >= 1 && d <= daysIn(y, mo) ? v : null;
  }

  function optionKeys(f) { return f.options.map(function (o) { return o[0]; }); }

  function parseOne(f, v) {
    switch (f.type) {
      case "number":
      case "money":
        return number(v);
      case "date":
        return date(typeof v === "string" ? v : null);
      case "select":
      case "multiselect":
        if (!(typeof v === "string" || typeof v === "number")) return null;
        v = str(v);
        return optionKeys(f).indexOf(v) >= 0 ? v : null;
      default:
        if (!(typeof v === "string" || typeof v === "number")) return null;
        var t = text(str(v), TEXT_MAX);
        return t === "" ? null : t;
    }
  }

  function parseValue(f, kind, v) {
    if (kind === "list") {
      if (typeof v === "string" || typeof v === "number" || typeof v === "boolean") v = [v];
      if (!isObj(v)) return null;
      var keys = optionKeys(f), out = [];
      values(v).slice(0, LIST_MAX).forEach(function (x) {
        if (!(typeof x === "string" || typeof x === "number")) return;
        x = str(x);
        if (keys.indexOf(x) >= 0 && out.indexOf(x) < 0) out.push(x);
      });
      return out.length ? out : null;
    }
    if (kind === "two") {
      if (!isObj(v)) return null;
      var pair = values(v);
      if (pair.length !== 2) return null;
      var a = parseOne(f, pair[0]), b = parseOne(f, pair[1]);
      if (a === null || b === null) return null;
      return a > b ? [b, a] : [a, b];
    }
    return parseOne(f, v);
  }

  function rawValue(v, kind) {
    var clean = function (x) { return (typeof x === "string" || typeof x === "number") ? text(str(x), TEXT_MAX) : ""; };
    if (kind === "two" || kind === "list") {
      var out = (isObj(v) ? values(v).slice(0, kind === "two" ? 2 : LIST_MAX) : []).map(clean);
      if (kind === "two") { while (out.length < 2) out.push(""); return out; }
      return out.filter(function (s) { return s !== ""; });
    }
    return clean(v);
  }

  function errorCode(type, kind) {
    if (kind === "list") return "list";
    if (kind === "two") return "between";
    if (type === "number" || type === "money") return "number";
    if (type === "date") return "date";
    if (type === "select" || type === "multiselect") return "select";
    return "text";
  }

  function isJoin(s) { return typeof s === "string" && (s.toLowerCase() === "and" || s.toLowerCase() === "or"); }
  function isGroup(node) {
    if (own(node, "rules")) return true;
    var l = listOf(node);
    return !!l && isJoin(l[0]) && (l.length === 1 || isObj(l[1]));
  }

  function walkRule(node, sp, draft) {
    var field, op, value, has;
    var l = listOf(node);
    if (!l && own(node, "field")) {
      field = node.field; op = own(node, "op") ? node.op : null; value = own(node, "value") ? node.value : null; has = own(node, "value");
    } else if (l && l.length >= 2 && l.length <= 3) {
      field = l[0]; op = l[1]; value = l.length === 3 ? l[2] : null; has = l.length === 3;
    } else {
      return null;
    }
    if (typeof field !== "string" || !own(sp.fields, field)) return null;
    var f = sp.fields[field], ops = sp.ops[field], kind;
    if (typeof op !== "string" || ops.indexOf(op) < 0) {
      if (!draft) return null;
      // Kept for the form, which shows the field's first operator — incomplete, with the operator as it
      // came, so it is never applied unseen (FilterBuilder.php reads the same state the same way).
      kind = KIND[ops[0]];
      var r = { field: field, op: typeof op === "string" ? text(op, 40) : "" };
      if (kind !== "none") r.value = rawValue(null, kind);
      r.error = kind === "none" ? "op" : errorCode(f.type, kind);
      return r;
    }
    kind = KIND[op];
    if (kind === "none") return { field: field, op: op };
    var parsed = has && value !== null ? parseValue(f, kind, value) : null;
    if (parsed !== null) return { field: field, op: op, value: parsed };
    if (!draft) return null;
    return { field: field, op: op, value: rawValue(value, kind), error: errorCode(f.type, kind) };
  }

  function walkGroup(node, sp, draft, depth, st) {
    if (++st.visits > VISIT_MAX) return null;
    var join, children;
    if (own(node, "rules") && !Array.isArray(node)) {
      join = own(node, "join") ? node.join : "and";
      children = isObj(node.rules) ? values(node.rules) : [];
    } else {
      var l = listOf(node);
      if (!l || !isJoin(l[0]) || !(l.length === 1 || isObj(l[1]))) return null;
      join = l[0];
      children = l.slice(1);
    }
    join = typeof join === "string" && join.toLowerCase() === "or" ? "or" : "and";
    var out = [];
    for (var i = 0; i < children.length; i++) {
      var child = children[i];
      if (!isObj(child) || st.visits > VISIT_MAX) continue;
      if (isGroup(child)) {
        if (depth >= sp.maxDepth) { st.visits++; continue; }
        var g = walkGroup(child, sp, draft, depth + 1, st);
        if (g && g.rules.length) out.push(g);
        continue;
      }
      if (++st.visits > VISIT_MAX || st.rows >= sp.maxRows) continue;
      var r = walkRule(child, sp, draft);
      if (r) { out.push(r); st.rows++; }
    }
    return { join: join, rules: out };
  }

  function walk(node, sp, draft) {
    if (typeof node === "string") {
      try { node = new Blob([node]).size > JSON_MAX ? null : JSON.parse(node); } catch (e) { node = null; }
    }
    var g = isObj(node) ? walkGroup(node, sp, draft, 1, { rows: 0, visits: 0 }) : null;
    return g || { join: "and", rules: [] };
  }

  function compact(g, draft) {
    var out = [g.join || "and"];
    (g.rules || []).forEach(function (r) {
      if (r.rules) { out.push(compact(r, draft)); return; }
      if (!draft && r.error) return;
      out.push(own(r, "value") ? [r.field, r.op, r.value] : [r.field, r.op]);
    });
    return out;
  }
  function count(g) { return g.rules.reduce(function (n, r) { return n + (r.rules ? count(r) : 1); }, 0); }
  function incomplete(g) { return g.rules.reduce(function (n, r) { return n + (r.rules ? incomplete(r) : (r.error ? 1 : 0)); }, 0); }
  function blank(v) {
    if (Array.isArray(v)) return v.every(function (x) { return x === ""; });
    return v === "" || v === null || v === undefined;
  }
  function nodeAt(g, path) {
    var node = g;
    for (var i = 0; i < path.length; i++) {
      if (!node.rules || !node.rules[path[i]]) return null;
      node = node.rules[path[i]];
    }
    return node;
  }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  /* ── Words ───────────────────────────────────────────────────────────── */

  function plain(v) {
    if (Number.isInteger(v)) return Math.abs(v) < 1e21 ? String(v) : BigInt(v).toString();
    var s = v.toFixed(6).replace(/0+$/, "").replace(/\.$/, "");
    return s === "-0" ? "0" : s;
  }
  function formatNumber(v, money, w) {
    var neg = v < 0;
    var parts = plain(Math.abs(v)).split(".");
    var int = parts[0], frac = parts[1] || "";
    if (money && frac.length < 2) while (frac.length < 2) frac += "0";
    int = int.replace(/\B(?=(\d{3})+(?!\d))/g, w.thousands);
    return (neg ? "-" : "") + int + (frac !== "" ? w.decimal + frac : "");
  }
  function opLabel(f, op) {
    for (var i = 0; i < f.ops.length; i++) if (f.ops[i][0] === op) return f.ops[i][1];
    return op;
  }
  function ruleWords(r, sp, w) {
    var f = sp.fields[r.field];
    var s = f.label + " " + opLabel(f, r.op);
    if (!own(r, "value")) return s;
    var one = function (v) {
      switch (f.type) {
        case "text": return tpl(w.quote, { v: v });
        case "date":
          var p = v.split("-");
          return tpl(w.day, { month: w.months[+p[1] - 1] || p[1], d: +p[2], y: +p[0] });
        case "number": return formatNumber(v, false, w);
        case "money": return formatNumber(v, true, w);
        default:
          for (var i = 0; i < f.options.length; i++) if (f.options[i][0] === String(v)) return f.options[i][1];
          return String(v);
      }
    };
    if (r.op === "between") return s + " " + tpl(w.range, { a: one(r.value[0]), b: one(r.value[1]) });
    if (Array.isArray(r.value)) return s + " " + r.value.map(one).join(", ");
    return s + " " + one(r.value);
  }
  function words(g, sp, w) {
    return g.rules.map(function (r) { return r.rules ? "(" + words(r, sp, w) + ")" : ruleWords(r, sp, w); })
      .join(" " + (g.join === "or" ? w.or : w.and) + " ");
  }
  function summary(tree, sp, w) { return tree.rules.length ? words(tree, sp, w) : w.summary_none; }

  /* ── Markup: the same as FilterBuilder.php writes ────────────────────── */

  function num(path) { return path.map(function (i) { return i + 1; }).join("."); }
  function cmdName(prefix) { var at = prefix.indexOf("["); return (at < 0 ? prefix : prefix.slice(0, at)) + "[cmd]"; }
  function iconButton(cmd, prefix, path, label, icon, disabled) {
    return '<button type="submit" class="gk-fb-btn gk-fb-' + cmd + '" name="' + esc(cmdName(prefix)) + '" value="' + cmd + ":" + esc(path) + '"'
      + ' aria-label="' + esc(label) + '" title="' + esc(label) + '"' + (disabled ? " disabled" : "") + ">" + icon + "</button>";
  }

  function input(f, id, name, value, label, state) {
    value = (typeof value === "string" || typeof value === "number") ? (typeof value === "number" ? plain(value) : value) : "";
    if (f.type === "select" || f.type === "multiselect") {
      var out = '<select class="gk-filter gk-fb-input" id="' + esc(id) + '" name="' + esc(name) + '" aria-label="' + esc(label) + '"' + state + ">"
        + '<option value=""' + (value === "" ? " selected" : "") + ">" + esc(st_words.choose) + "</option>";
      f.options.forEach(function (o) {
        out += '<option value="' + esc(o[0]) + '"' + (o[0] === value ? " selected" : "") + ">" + esc(o[1]) + "</option>";
      });
      return out + "</select>";
    }
    var type = f.type === "date" ? "date" : "text";
    var mode = f.type === "number" || f.type === "money" ? ' inputmode="decimal"' : "";
    var ph = f.placeholder !== "" ? ' placeholder="' + esc(f.placeholder) + '"' : "";
    return '<input type="' + type + '" class="gk-filter gk-fb-input" id="' + esc(id) + '" name="' + esc(name) + '" value="' + esc(value) + '"'
      + mode + ph + ' aria-label="' + esc(label) + '"' + state + (type === "text" ? ' maxlength="' + TEXT_MAX + '"' : "") + ">";
  }
  var st_words = {}; // the words of the builder being drawn

  function ruleHtml(b, r, path, prefix, total, conj) {
    var sp = b.spec, w = b.words;
    var f = sp.fields[r.field];
    var n = num(path);
    var id = b.base + "-c" + path.join("-");
    var show = r.error && (b.loud || !blank(r.value));
    var err = show ? id + "-err" : "";
    var state = err ? ' aria-invalid="true" aria-describedby="' + esc(err) + '"' : "";
    var out = '<li class="gk-fb-row' + (err ? " gk-fb-invalid" : "") + '" id="' + esc(id) + '" data-gk-fb-path="' + esc(path.join(".")) + '">' + conj
      + '<span class="gk-fb-cells">';
    out += '<select class="gk-filter gk-fb-field" id="' + esc(id) + '-field" name="' + esc(prefix) + '[field]" aria-label="' + esc(tpl(w.field, { n: n })) + '">';
    sp.order.forEach(function (k) {
      out += '<option value="' + esc(k) + '"' + (k === r.field ? " selected" : "") + ">" + esc(sp.fields[k].label) + "</option>";
    });
    out += "</select>";
    out += '<select class="gk-filter gk-fb-op" id="' + esc(id) + '-op" name="' + esc(prefix) + '[op]" aria-label="' + esc(tpl(w.operator, { n: n })) + '">';
    var shown = sp.ops[r.field].indexOf(r.op) >= 0 ? r.op : sp.ops[r.field][0];   // an operator the field lacks: its first
    f.ops.forEach(function (o) {
      out += '<option value="' + esc(o[0]) + '"' + (o[0] === shown ? " selected" : "") + ">" + esc(o[1]) + "</option>";
    });
    out += "</select>";
    var kind = KIND[shown];
    if (kind !== "none") {
      out += '<span class="gk-fb-value">';
      var v = own(r, "value") ? r.value : null;
      if (kind === "list") {
        var chosen = (Array.isArray(v) ? v : []).map(String);
        out += '<select class="gk-filter gk-fb-input gk-fb-multi" id="' + esc(id) + '-value" name="' + esc(prefix) + '[value][]" multiple size="'
          + Math.min(4, Math.max(2, f.options.length)) + '"' + ' aria-label="' + esc(tpl(w.value, { n: n })) + '"' + state + ">";
        f.options.forEach(function (o) {
          out += '<option value="' + esc(o[0]) + '"' + (chosen.indexOf(o[0]) >= 0 ? " selected" : "") + ">" + esc(o[1]) + "</option>";
        });
        out += "</select>";
      } else if (kind === "two") {
        var pair = Array.isArray(v) ? v : ["", ""];
        out += input(f, id + "-value", prefix + "[value][0]", pair[0] === undefined ? "" : pair[0], tpl(w.value_from, { n: n }), state)
          + '<span class="gk-fb-between-and" aria-hidden="true">' + esc(w.between_and) + "</span>"
          + input(f, id + "-value2", prefix + "[value][1]", pair[1] === undefined ? "" : pair[1], tpl(w.value_to, { n: n }), state);
      } else {
        out += input(f, id + "-value", prefix + "[value]", v === null ? "" : v, tpl(w.value, { n: n }), state);
      }
      out += "</span>";
    }
    out += '</span><span class="gk-fb-actions">'
      + iconButton("dup", prefix, path.join("."), tpl(w.duplicate, { n: n }), ICON_COPY, total >= sp.maxRows)
      + iconButton("remove", prefix, path.join("."), tpl(w.remove, { n: n }), ICON_REMOVE, false)
      + "</span>";
    if (err) out += '<p class="gk-fb-error" id="' + esc(err) + '">' + ICON_ERROR + "<span>" + esc(w["err_" + r.error]) + "</span></p>";
    return out + "</li>";
  }

  function groupHtml(b, g, path, depth, prefix, total) {
    var sp = b.spec, w = b.words;
    var name = path.length ? tpl(w.group, { n: num(path) }) : w.label;
    var gid = b.base + "-g" + path.join("-");
    var out = '<fieldset class="gk-fb-group" id="' + esc(gid) + '" data-gk-fb-path="' + esc(path.join(".")) + '">'
      + '<legend class="gk-fb-legend' + (path.length ? "" : " gk-sr-only") + '">' + esc(name) + "</legend>"
      + '<div class="gk-fb-group-head">'
      + '<label class="gk-fb-join-label" for="' + esc(gid) + '-join">' + esc(w.match) + "</label>"
      + '<select class="gk-filter gk-fb-join" id="' + esc(gid) + '-join" name="' + esc(prefix) + '[join]">'
      + '<option value="and"' + (g.join === "and" ? " selected" : "") + ">" + esc(w.join_and) + "</option>"
      + '<option value="or"' + (g.join === "or" ? " selected" : "") + ">" + esc(w.join_or) + "</option>"
      + "</select>";
    if (path.length) {
      var n = num(path);
      out += '<span class="gk-fb-actions">'
        + iconButton("dup", prefix, path.join("."), tpl(w.duplicate_group, { n: n }), ICON_COPY, total >= sp.maxRows)
        + iconButton("remove", prefix, path.join("."), tpl(w.remove_group, { n: n }), ICON_REMOVE, false)
        + "</span>";
    }
    out += "</div>";
    if (!g.rules.length) {
      out += '<p class="gk-fb-empty">' + esc(w.empty) + "</p>";
    } else {
      out += '<ol class="gk-fb-rules">';
      g.rules.forEach(function (r, i) {
        var p = path.concat([i]);
        var conj = i === 0 ? "" : '<span class="gk-fb-conj" aria-hidden="true">' + esc(g.join === "or" ? w.or : w.and) + "</span>";
        if (r.rules) {
          out += '<li class="gk-fb-row gk-fb-row-group">' + conj + groupHtml(b, r, p, depth + 1, prefix + "[rules][" + i + "]", total) + "</li>";
        } else {
          out += ruleHtml(b, r, p, prefix + "[rules][" + i + "]", total, conj);
        }
      });
      out += "</ol>";
    }
    var where = path.length ? name : w.the_filter;
    var full = total >= sp.maxRows;
    out += '<div class="gk-fb-add">'
      + '<button type="submit" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm gk-fb-add-rule" name="' + esc(cmdName(prefix)) + '" value="add:' + esc(path.join(".")) + '"'
      + ' aria-label="' + esc(tpl(w.add_label, { group: where })) + '"' + (full ? " disabled" : "") + ">"
      + ICON_ADD + "<span>" + esc(w.add) + "</span></button>";
    if (depth < sp.maxDepth) {
      out += '<button type="submit" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm gk-fb-add-group" name="' + esc(cmdName(prefix)) + '" value="group:' + esc(path.join(".")) + '"'
        + ' aria-label="' + esc(tpl(w.add_group_label, { group: where })) + '"' + (full ? " disabled" : "") + ">"
        + ICON_ADD + "<span>" + esc(w.add_group) + "</span></button>";
    }
    if (full && !path.length) out += '<span class="gk-fb-limit">' + esc(tpl(w.max_rows, { n: sp.maxRows })) + "</span>";
    return out + "</div></fieldset>";
  }

  /* ── A builder on the page ───────────────────────────────────────────── */

  function setup(form) {
    if (builders.has(form)) return builders.get(form);
    var cfg;
    try { cfg = JSON.parse(form.getAttribute("data-gk-filter-builder") || "{}"); } catch (e) { return null; }
    if (!cfg || !cfg.fields || !cfg.name) return null;
    var b = {
      form: form, cfg: cfg, name: cfg.name, base: "gk-fb-" + cfg.name, spec: spec(cfg), words: cfg.words,
      loud: !!cfg.loud, sayTimer: 0
    };
    b.state = walk(cfg.state, b.spec, true);
    b.initial = cfg.initial;
    builders.set(form, b);
    draw(b, null);
    sayNow(b);
    drawLocalViews(b);
    form.addEventListener("submit", function (e) { onSubmit(b, e); });
    form.addEventListener("change", function (e) { onChange(b, e); });
    form.addEventListener("input", function (e) { onInput(b, e); });
    form.addEventListener("click", function (e) { onClick(b, e); });
    // Back from the next page (bfcache): the fields the last submit switched off work again.
    window.addEventListener("pageshow", function () { restore(b); });
    return b;
  }

  function tree(b) { return walk(b.state, b.spec, false); }
  function json(b) { return JSON.stringify(compact(tree(b), false)); }

  function draw(b, focus) {
    var old = b.form.querySelector(":scope > .gk-fb-group");
    if (!old) return;
    st_words = b.words;
    var tmp = document.createElement("div");
    tmp.innerHTML = groupHtml(b, b.state, [], 1, b.name, count(b.state));
    var fresh = tmp.firstElementChild;
    old.replaceWith(fresh);
    note(b);
    if (focus) {
      var el = document.getElementById(focus.id);
      if (el) {
        el.focus();
        if (focus.sel && typeof el.setSelectionRange === "function") {
          try { el.setSelectionRange(focus.sel[0], focus.sel[1]); } catch (e) { /* a date field has no caret */ }
        }
      }
    }
  }

  function note(b) {
    var el = b.form.querySelector("[data-gk-fb-note]");
    if (!el) return;
    var n = incomplete(b.state);
    if (!b.loud || n === 0) {
      if (n === 0) b.loud = false;
      el.hidden = true;
      el.lastElementChild.textContent = "";
      return;
    }
    el.lastElementChild.textContent = n === 1 ? b.words.incomplete_one : tpl(b.words.incomplete, { n: n });
    el.hidden = false;
  }

  function sayNow(b) {
    clearTimeout(b.sayTimer);
    var el = b.form.querySelector(".gk-fb-summary-text");
    var t = summary(tree(b), b.spec, b.words);
    if (el && el.textContent !== t) el.textContent = t;
  }
  function sayLater(b) {
    clearTimeout(b.sayTimer);
    b.sayTimer = setTimeout(function () { sayNow(b); }, SAY_MS);
  }

  function pathOf(el) {
    var host = el.closest("[data-gk-fb-path]");
    var p = host ? host.getAttribute("data-gk-fb-path") : "";
    return p === "" ? [] : p.split(".").map(Number);
  }

  function newRule(b) {
    var sp = b.spec, first = sp.order[0];
    var op = sp.ops[first][0], kind = KIND[op];
    var r = { field: first, op: op };
    if (kind !== "none") {
      r.value = kind === "two" ? ["", ""] : (kind === "list" ? [] : "");
      r.error = errorCode(sp.fields[first].type, kind);
    }
    return r;
  }

  /** The first control of a node: a condition's field, a group's join. */
  function firstControlId(b, path, node) {
    return node && node.rules ? b.base + "-g" + path.join("-") + "-join" : b.base + "-c" + path.join("-") + "-field";
  }

  // add, group, remove, dup — the commands of the server, and where the focus goes after each.
  function command(b, cmd, path) {
    var sp = b.spec, g = b.state, total = count(g);
    b.loud = false;   // as the server after add, group, dup and remove: a row just made is not called wrong
    if (!sp.order.length) return;
    if (cmd === "add" || cmd === "group") {
      var target = nodeAt(g, path);
      if (!target || !target.rules || total >= sp.maxRows) return;
      if (cmd === "add") target.rules.push(newRule(b));
      else if (path.length + 1 < sp.maxDepth) target.rules.push({ join: target.join === "and" ? "or" : "and", rules: [newRule(b)] });
      else return;
      var at = path.concat([target.rules.length - 1]);
      var made = target.rules[target.rules.length - 1];
      draw(b, { id: made.rules ? b.base + "-c" + at.concat([0]).join("-") + "-field" : firstControlId(b, at, made) });
      sayNow(b);
      return;
    }
    if (!path.length) return;
    var p = path.slice(), index = p.pop();
    var parent = nodeAt(g, p);
    if (!parent || !parent.rules[index]) return;
    if (cmd === "dup") {
      var copy = clone(parent.rules[index]);
      if (total + (copy.rules ? count(copy) : 1) > sp.maxRows) return;
      parent.rules.splice(index + 1, 0, copy);
      draw(b, { id: firstControlId(b, p.concat([index + 1]), copy) });
      sayNow(b);
      return;
    }
    parent.rules.splice(index, 1);
    // A group left empty goes with its last condition.
    while (p.length) {
      var up = nodeAt(g, p);
      if (!up || up.rules.length) break;
      index = p.pop();
      nodeAt(g, p).rules.splice(index, 1);
    }
    var holder = nodeAt(g, p);
    var focus;
    if (holder.rules[index]) focus = firstControlId(b, p.concat([index]), holder.rules[index]);
    else if (index > 0 && holder.rules[index - 1]) focus = firstControlId(b, p.concat([index - 1]), holder.rules[index - 1]);
    draw(b, focus ? { id: focus } : null);
    if (!focus) {
      var grp = document.getElementById(b.base + "-g" + p.join("-"));
      var add = grp && grp.querySelector(":scope > .gk-fb-add > .gk-fb-add-rule");
      if (add) add.focus();
    }
    sayNow(b);
  }

  /** What a control says, written into the state: true when the markup has to follow. */
  function read(b, el) {
    var cls = el.classList;
    var path = pathOf(el);
    var node = nodeAt(b.state, path);
    if (!node) return false;
    var sp = b.spec;
    // A condition that came with an operator its field lacks shows the first one: what is typed now is for that one.
    if (!cls.contains("gk-fb-join") && !cls.contains("gk-fb-op") && sp.ops[node.field] && sp.ops[node.field].indexOf(node.op) < 0) node.op = sp.ops[node.field][0];
    if (cls.contains("gk-fb-join")) {
      node.join = el.value === "or" ? "or" : "and";
      return true;
    }
    if (cls.contains("gk-fb-field")) {
      if (!own(sp.fields, el.value) || el.value === node.field) return false;
      var keep = own(node, "value") ? node.value : null;
      var oldType = sp.fields[node.field].type;
      node.field = el.value;
      // The operator stays when the new field knows it, the value when it still fits.
      if (sp.ops[node.field].indexOf(node.op) < 0) node.op = sp.ops[node.field][0];
      fit(b, node, oldType === sp.fields[node.field].type ? keep : null);
      return true;
    }
    if (cls.contains("gk-fb-op")) {
      if (sp.ops[node.field].indexOf(el.value) < 0) return false;
      var before = KIND[node.op];
      var was = own(node, "value") ? node.value : null;
      node.op = el.value;
      var now = KIND[node.op];
      var carry = null;
      if (before === now) carry = was;
      else if (before === "one" && now === "two") carry = [was === null ? "" : was, ""];
      else if (before === "two" && now === "one" && Array.isArray(was)) carry = was[0];
      else if (before === "one" && now === "list" && was !== null && was !== "") carry = [was];
      else if (before === "list" && now === "one" && Array.isArray(was) && was.length) carry = was[0];
      fit(b, node, carry);
      return before !== now || !!node.error;
    }
    if (cls.contains("gk-fb-input")) {
      var kind = KIND[node.op];
      var v;
      if (kind === "list") {
        v = Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; });
      } else if (kind === "two") {
        var row = document.getElementById(b.base + "-c" + path.join("-"));
        var a = row.querySelector("#" + CSS.escape(b.base + "-c" + path.join("-") + "-value"));
        var c = row.querySelector("#" + CSS.escape(b.base + "-c" + path.join("-") + "-value2"));
        v = [a ? a.value : "", c ? c.value : ""];
      } else {
        v = el.value;
      }
      var hadError = !!node.error;
      fit(b, node, v);
      return hadError !== !!node.error || (!!node.error && b.loud);
    }
    return false;
  }

  /** Puts a value into a condition the way the server reads it: parsed, or kept as typed with its error. */
  function fit(b, node, value) {
    var r = walkRule(value === null || value === undefined ? { field: node.field, op: node.op } : { field: node.field, op: node.op, value: value }, b.spec, true);
    delete node.value;
    delete node.error;
    if (!r) return;
    if (own(r, "value")) node.value = r.value;
    if (r.error) node.error = r.error;
  }

  function focusInfo(el) {
    var sel = null;
    try { if (typeof el.selectionStart === "number") sel = [el.selectionStart, el.selectionEnd]; } catch (e) { sel = null; }
    return { id: el.id, sel: sel };
  }

  function onChange(b, e) {
    var el = e.target;
    if (!el.closest(".gk-fb-group") || el.closest(".gk-fb-save")) return;
    if (el.classList.contains("gk-fb-input")) {
      // A value: the row is told what is wrong in place — the focus may be on its way to the next control.
      read(b, el);
      mark(b, pathOf(el), true);
    } else if (read(b, el)) {
      draw(b, focusInfo(el));
    }
    sayNow(b);
  }

  function onInput(b, e) {
    var el = e.target;
    if (!el.classList.contains("gk-fb-input") || el.tagName === "SELECT") return;
    if (!nodeAt(b.state, pathOf(el))) return;
    read(b, el);
    // While typing, a mark goes the moment the value is right; a new one waits until the field is left.
    mark(b, pathOf(el), false);
    sayLater(b);
  }

  /** The error of one condition, set on its row without drawing it again (ruleHtml writes the same). */
  function mark(b, path, add) {
    var node = nodeAt(b.state, path);
    var id = b.base + "-c" + path.join("-");
    var li = document.getElementById(id);
    if (!node || !li) return;
    var err = id + "-err";
    var p = document.getElementById(err);
    var show = !!node.error && (b.loud || !blank(node.value));
    if (show && !p && !add) show = false;
    li.classList.toggle("gk-fb-invalid", show);
    li.querySelectorAll(".gk-fb-input").forEach(function (input) {
      if (show) { input.setAttribute("aria-invalid", "true"); input.setAttribute("aria-describedby", err); }
      else { input.removeAttribute("aria-invalid"); input.removeAttribute("aria-describedby"); }
    });
    var msg = show ? b.words["err_" + node.error] : "";
    if (show && !p) {
      li.insertAdjacentHTML("beforeend", '<p class="gk-fb-error" id="' + esc(err) + '">' + ICON_ERROR + "<span>" + esc(msg) + "</span></p>");
    } else if (show) {
      p.lastElementChild.textContent = msg;
    } else if (p) {
      p.remove();
    }
    note(b);
  }

  function onClick(b, e) {
    var rm = e.target.closest(".gk-fb-view-remove");
    if (rm && b.form.contains(rm)) {
      e.preventDefault();
      removeLocalView(b, rm.getAttribute("data-gk-fb-view"));
    }
  }

  function firstInvalid(b) {
    var el = b.form.querySelector('.gk-fb-group [aria-invalid="true"]');
    return el;
  }

  function onSubmit(b, e) {
    var btn = e.submitter;
    var val = btn && btn.name === b.name + "[cmd]" ? btn.value : "apply";
    var m = /^(add|group|remove|dup):((?:\d{1,3}(?:\.\d{1,3}){0,5})?)$/.exec(val);
    e.preventDefault();
    if (m) {
      command(b, m[1], m[2] === "" ? [] : m[2].split(".").map(Number));
      return;
    }
    if (val === "reset") {
      reset(b);
      apply(b);
      return;
    }
    if (val === "save") {
      save(b);
      return;
    }
    apply(b);
  }

  /** Apply: incomplete conditions are shown, not sent; then gk:filterchange, then the form. */
  function apply(b) {
    if (incomplete(b.state) > 0) {
      b.loud = true;
      draw(b, null);
      var bad = firstInvalid(b);
      if (bad) bad.focus();
      return false;
    }
    var t = tree(b);
    var detail = { name: b.name, tree: t, json: JSON.stringify(compact(t, false)), summary: summary(t, b.spec, b.words) };
    var ev = new CustomEvent("gk:filterchange", { bubbles: true, cancelable: true, detail: detail });
    if (!b.form.dispatchEvent(ev)) return false;
    send(b, detail.json, null);
    return true;
  }

  /** Sends the form with the state as JSON in one parameter; the nested fields stay home. */
  function send(b, jsonText, saveTo) {
    restore(b);
    var form = b.form;
    Array.prototype.forEach.call(form.elements, function (el) {
      if (el.name && el.name.indexOf(b.name + "[") === 0 && !el.disabled) {
        el.disabled = true;
        el.setAttribute("data-gk-fb-off", "");
      }
    });
    var h = document.createElement("input");
    h.type = "hidden";
    h.name = b.name;
    h.value = jsonText;
    h.setAttribute("data-gk-fb-json", "");
    form.appendChild(h);
    var view = form.querySelector(".gk-fb-save-name");
    if (view && !saveTo) { view.disabled = true; view.setAttribute("data-gk-fb-off", ""); }
    if (saveTo) {
      var a = form.getAttribute("action"), m = form.getAttribute("method");
      form.setAttribute("action", saveTo);
      form.setAttribute("method", "post");
      HTMLFormElement.prototype.submit.call(form);
      if (a === null) form.removeAttribute("action"); else form.setAttribute("action", a);
      form.setAttribute("method", m || "get");
    } else {
      HTMLFormElement.prototype.submit.call(form);
    }
  }

  function restore(b) {
    b.form.querySelectorAll("[data-gk-fb-json]").forEach(function (h) { h.remove(); });
    b.form.querySelectorAll("[data-gk-fb-off]").forEach(function (el) { el.disabled = false; el.removeAttribute("data-gk-fb-off"); });
  }

  function reset(b) {
    b.state = walk(b.initial, b.spec, true);
    b.loud = true;
    draw(b, null);
    sayNow(b);
  }

  /* ── Saved views ─────────────────────────────────────────────────────── */

  function storeKey(b) { return "gk-filterbuilder-" + b.name; }
  function localViews(b) {
    try {
      var list = JSON.parse(window.localStorage.getItem(storeKey(b)) || "[]");
      return Array.isArray(list) ? list.filter(function (v) { return v && typeof v.label === "string" && typeof v.json === "string"; }) : [];
    } catch (e) { return []; }
  }
  function writeLocal(b, list) {
    try { window.localStorage.setItem(storeKey(b), JSON.stringify(list)); return true; } catch (e) { return false; }
  }
  function linkFor(b, jsonText) {
    var u = new URL(window.location.href);
    var action = b.form.getAttribute("action");
    if (action) {
      var target = new URL(action, window.location.href);
      u.pathname = target.pathname;
      u.hash = target.hash;
    }
    u.searchParams.set(b.name, jsonText);
    return u.pathname + u.search + u.hash;
  }

  function drawLocalViews(b) {
    if (!b.cfg.localViews) return;
    var nav = b.form.querySelector(".gk-fb-views");
    var list = nav && nav.querySelector(".gk-fb-view-list");
    if (!list) return;
    list.querySelectorAll("li[data-gk-fb-local]").forEach(function (li) { li.remove(); });
    var current = json(b);
    localViews(b).forEach(function (v) {
      var clean = JSON.stringify(compact(walk(v.json, b.spec, false), false));
      var li = document.createElement("li");
      li.setAttribute("data-gk-fb-local", "");
      li.innerHTML = '<a class="gk-fb-view" href="' + esc(linkFor(b, clean)) + '"'
        + (clean === current ? ' aria-current="true"' : "") + ">" + esc(v.label) + '<span class="gk-sr-only"> (' + esc(b.words.saved_here) + ")</span></a>"
        + '<button type="button" class="gk-fb-btn gk-fb-view-remove" data-gk-fb-view="' + esc(v.label) + '" aria-label="'
        + esc(tpl(b.words.view_remove, { name: v.label })) + '" title="' + esc(tpl(b.words.view_remove, { name: v.label })) + '">' + ICON_REMOVE + "</button>";
      list.appendChild(li);
    });
    nav.hidden = list.children.length === 0;
    var saveBox = b.form.querySelector(".gk-fb-save");
    if (saveBox) saveBox.hidden = false;
  }

  function removeLocalView(b, label) {
    var list = localViews(b).filter(function (v) { return v.label !== label; });
    writeLocal(b, list);
    drawLocalViews(b);
    var nav = b.form.querySelector(".gk-fb-views");
    var next = nav && !nav.hidden ? nav.querySelector(".gk-fb-view") : null;
    (next || b.form.querySelector(".gk-fb-save-name") || b.form.querySelector(".gk-fb-apply")).focus();
  }

  function save(b) {
    var input = b.form.querySelector(".gk-fb-save-name");
    var label = input ? text(input.value, 80) : "";
    if (!label) {
      if (input) {
        input.setCustomValidity(b.words.view_name_missing);
        input.reportValidity();
        input.addEventListener("input", function clear() { input.setCustomValidity(""); input.removeEventListener("input", clear); });
        input.focus();
      }
      return false;
    }
    if (incomplete(b.state) > 0) return apply(b);
    var t = tree(b);
    var detail = { name: b.name, label: label, tree: t, json: JSON.stringify(compact(t, false)), summary: summary(t, b.spec, b.words) };
    var ev = new CustomEvent("gk:filtersave", { bubbles: true, cancelable: true, detail: detail });
    if (!b.form.dispatchEvent(ev)) return true;
    if (b.cfg.saveUrl) {
      send(b, detail.json, b.cfg.saveUrl);
      return true;
    }
    if (b.cfg.localViews) {
      var list = localViews(b).filter(function (v) { return v.label !== label; });
      list.push({ label: label, json: detail.json });
      writeLocal(b, list.slice(-50));
      drawLocalViews(b);
      if (input) input.value = "";
      var say = b.form.querySelector(".gk-fb-summary-text");
      if (window.GK && GK.toast && GK.toast.success) GK.toast.success(tpl(b.words.view_saved, { name: label }));
      else if (say) say.textContent = tpl(b.words.view_saved, { name: label });
    }
    return true;
  }

  /* ── API ─────────────────────────────────────────────────────────────── */

  function get(el) {
    if (typeof el === "string") {
      el = document.getElementById(el) || document.getElementById("gk-fb-" + el) || document.querySelector(el);
    }
    if (!el || !el.matches || !el.matches(SEL)) return null;
    return setup(el);
  }

  window.GK = window.GK || {};
  window.GK.filterBuilder = {
    // The checked conditions as they stand (the documented shape) — what Apply would send.
    get: function (el) { var b = get(el); return b ? tree(b) : null; },
    // Any state, either shape or its JSON: drawn at once, nothing sent. Returns the checked tree.
    set: function (el, state) {
      var b = get(el);
      if (!b) return null;
      b.state = walk(state, b.spec, true);
      b.loud = true;
      draw(b, null);
      sayNow(b);
      return tree(b);
    },
    // Back to the builder's value(); nothing sent.
    reset: function (el) { var b = get(el); if (!b) return null; reset(b); return tree(b); },
    // As the Apply button: false when a condition is incomplete or gk:filterchange was cancelled.
    apply: function (el) { var b = get(el); return b ? apply(b) : false; },
    // The compact JSON of the checked conditions — the value of the URL parameter.
    json: function (el) { var b = get(el); return b ? json(b) : null; },
    // The conditions in words, as under the builder.
    summary: function (el) { var b = get(el); return b ? summary(tree(b), b.spec, b.words) : null; }
  };

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(SEL)) setup(root);
    root.querySelectorAll(SEL).forEach(setup);
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
