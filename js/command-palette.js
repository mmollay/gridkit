/* GridKit command palette v1.122.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js
 * is one: the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites, so a block
 * that lives in gridkit.js would never reach it. Load it after gridkit.js (skeleton.php does). Nothing
 * here needs gridkit.js: the words come from window.GK_LANG, and GK.theme's scope is used when it is there.
 *
 *   <dialog class="gk-palette" data-gk-palette="main" …>              ← GridKit\CommandPalette writes it
 *     <input class="gk-palette-input" role="combobox" …>
 *     <div class="gk-palette-list" role="listbox"></div> …
 *     <script type="application/json" data-gk-palette-config> {…}
 *   <button class="gk-palette-trigger" data-gk-palette-open="main" hidden>   ← CommandPalette::trigger()
 *
 * Opened with its key (Ctrl+K, Cmd+K on a Mac — it owns that key while it is on the page), "/" when the
 * server allowed it, a trigger or GK.palette.open(). The modal <dialog> keeps the focus and makes the page
 * behind it inert; closing gives the focus back. The entries: the sidebar's links (read when it opens),
 * the server's items, what GK.palette.register() added, and records from the search URLs (same origin,
 * debounced, the stale answer dropped). Every label is put in as text.
 *
 * Events, on the <dialog>, bubbling:
 *   gk:paletteopen    { palette, source }                     after it opened (source: hotkey, slash, trigger, api)
 *   gk:paletteselect  { palette, item, query, newTab }        cancelable: nothing happens, it stays open
 *   gk:paletteaction  { palette, action, item, query }        an entry with an action was chosen; it has closed
 */
(function () {
  "use strict";

  var lang = window.GK_LANG || {};
  // The English words, for a page without Lang::jsConfig(): a bare key is no label.
  var EN = {
    palette_label: "Command palette",
    palette_placeholder: "Type a command or search…",
    palette_close: "Close",
    palette_results: "Results",
    palette_help: "Up and Down move through the results, Enter opens one, Control and Enter opens a link in a new tab, Escape closes.",
    palette_foot_move: "move",
    palette_foot_open: "open",
    palette_foot_tab: "new tab",
    palette_foot_close: "close",
    palette_recent: "Recent",
    palette_pages: "Pages",
    palette_commands: "Commands",
    palette_records: "Records",
    palette_current: "Current page",
    palette_count: "{n} results",
    palette_count_one: "1 result",
    palette_none: "No results for “{q}”",
    palette_more: "{n} more — keep typing to narrow it down",
    palette_loading: "Searching…",
    palette_no_records: "No records found.",
    palette_error: "The records could not be searched.",
    palette_retry: "Try again"
  };
  // Every {name} is replaced, not the first one only (same as tree.js).
  function t(key, params) {
    var text = lang[key] || EN[key] || key;
    for (var name in params || {}) text = text.split("{" + name + "}").join(params[name]);
    return text;
  }

  var PALETTE = "[data-gk-palette]";
  var TRIGGER = "[data-gk-palette-open]";
  var SAY_MS = 450;       // the count is spoken this long after the list last changed
  var MAX_TEXT = 300;     // a label, hint or amount longer than this is cut: a list row, not a document
  var states = [];        // the palettes on the page, in the order they were set up
  var sources = [];       // what GK.palette.register() added, for every palette
  var counter = 0;
  var MAC = (function () {
    var p = "";
    try { p = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || ""; } catch (e) { p = ""; }
    return /mac|iphone|ipad|ipod/i.test(p);
  })();

  /* ── Text ────────────────────────────────────────────────────────────── */

  function str(v) {
    if (typeof v === "number" && isFinite(v)) v = String(v);
    if (typeof v !== "string") return "";
    v = v.replace(/\s+/g, " ").trim();
    return v.length > MAX_TEXT ? v.slice(0, MAX_TEXT - 1) + "…" : v;
  }
  function icon(v) { return typeof v === "string" && /^[a-z0-9_]{1,40}$/.test(v) ? v : ""; }
  // A link a click would run instead of follow. Browsers drop tabs, line breaks and control characters
  // inside a scheme ("java\tscript:"), so they go before the scheme is read — as in CommandPalette.php.
  function safeHref(v) {
    v = typeof v === "string" ? v.trim() : "";
    if (!v) return "";
    return /^(javascript|vbscript|data):/i.test(v.replace(/[\u0000-\u0020\u007f]+/g, "")) ? "" : v;
  }
  function words(v) {
    if (typeof v === "string") v = [v];
    return Array.isArray(v) ? v.map(str).filter(Boolean) : [];
  }

  // Lower case, accents folded, with the place in the original of every folded letter, so a match is
  // marked on the letters that were written. Two foldings: "Müller" is "muller" and "mueller", so
  // both spellings find it; ß is ss, æ ae, ø o.
  var SPECIAL = { "ß": "ss", "ẞ": "ss", "æ": "ae", "Æ": "ae", "œ": "oe", "Œ": "oe", "ø": "o", "Ø": "o",
    "đ": "d", "Đ": "d", "ł": "l", "Ł": "l", "þ": "th", "Þ": "th", "ı": "i" };
  var GERMAN = { "ä": "ae", "ö": "oe", "ü": "ue", "Ä": "ae", "Ö": "oe", "Ü": "ue" };
  function fold(s, german) {
    var text = "";
    var map = [];
    for (var i = 0; i < s.length; i++) {
      var ch = s[i];
      var f = (german && GERMAN[ch]) || SPECIAL[ch] || ch.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
      for (var j = 0; j < f.length; j++) { text += f[j]; map.push(i); }
    }
    return { text: text, map: map, orig: s };
  }
  function foldQuery(q) {
    return fold(String(q || "").normalize("NFC"), false).text.replace(/\s+/g, " ").trim();
  }
  function foldings(s) {
    var a = fold(s, false);
    var b = fold(s, true);
    return a.text === b.text ? [a] : [a, b];
  }

  // A letter that starts a word: the first, one after a space or a sign, or a capital after a small
  // letter ("invoiceList"). The second letter of a folded "ae" starts nothing.
  function wordStart(f, j) {
    if (j === 0) return true;
    if (f.map[j] === f.map[j - 1]) return false;
    if (!/[a-z0-9]/.test(f.text[j - 1])) return true;
    var o = f.orig[f.map[j]];
    var p = f.orig[f.map[j - 1]];
    return o !== o.toLowerCase() && p === p.toLowerCase() && p !== p.toUpperCase();
  }
  function run(from, n) { var out = []; for (var i = 0; i < n; i++) out.push(from + i); return out; }

  /*
   * Where the query stands in a folded text, and how well: { tier, score, pos } or null.
   *   tier 3  the text starts with it                       "inv"      → Invoices
   *   tier 2  a word starts with it, or every word of it    "inv"      → New invoice, "ne in" → New invoice
   *   tier 1  it is inside a word                           "voice"    → Invoices
   *   tier 0  its letters, in order, word by word           "ninv"     → New invoice
   * The score orders within a tier: earlier and tighter is better, a shorter text is better.
   */
  function place(f, q) {
    var text = f.text;
    var n = text.length;
    var m = q.length;
    if (!m || m > n) return null;
    if (text.lastIndexOf(q, 0) === 0) return { tier: 3, score: 100 - (n - m) * 0.1, pos: run(0, m) };
    for (var at = text.indexOf(q, 1); at !== -1; at = text.indexOf(q, at + 1)) {
      if (wordStart(f, at)) return { tier: 2, score: 80 - at * 0.5 - (n - m) * 0.05, pos: run(at, m) };
    }
    var parts = q.split(" ");
    if (parts.length > 1) {
      var pos = [];
      var from = 0;
      var ok = parts.every(function (w) {
        for (var k = text.indexOf(w, from); k !== -1; k = text.indexOf(w, k + 1)) {
          if (wordStart(f, k)) { pos = pos.concat(run(k, w.length)); from = k + w.length; return true; }
        }
        return false;
      });
      if (ok) return { tier: 2, score: 70 - pos[0] * 0.5 - (n - m) * 0.05, pos: pos };
    }
    var inside = text.indexOf(q);
    if (inside !== -1) return { tier: 1, score: 50 - inside * 0.5 - (n - m) * 0.05, pos: run(inside, m) };
    var sub = letters(f, q.replace(/ /g, ""));
    return sub ? { tier: 0, score: sub.score - (n - m) * 0.02, pos: sub.pos } : null;
  }

  /*
   * The letters of q in order, placed where they score best. Not anywhere: the first one starts a word,
   * and a letter in another word than the one before starts that word — inside a word letters may be
   * skipped ("invce" → invoice, "ninv" → N(ew) inv(oice), "tre" is not t(oggle da)r(k mod)e). A letter
   * that starts a word counts 4, one straight after the one before 3 more, every letter skipped costs
   * 0.2 — so "ni" marks N(ew) I(nvoice), not the "i" of "New". One pass over the text per letter:
   *   D[i][j]   the best score of q[0..i] with q[i] at j
   *   A[i][j]   the best D[i][k], k ≤ j, less what was skipped since k        (AI: that k)
   *   W[i][j]   the same, k in the word j is in                              (WI: that k)
   */
  function letters(f, q) {
    var t = f.text;
    var n = t.length;
    var m = q.length;
    if (!m || m > n) return null;
    var NONE = -Infinity;
    var start = [];
    for (var s = 0; s < n; s++) start.push(wordStart(f, s));
    var D = [], P = [], A = [], AI = [], W = [], WI = [];
    for (var i = 0; i < m; i++) {
      var d = [], p = [], a = [], ai = [], w = [], wi = [];
      var all = NONE, allAt = -1, word = NONE, wordAt = -1;
      for (var j = 0; j < n; j++) {
        var v = NONE;
        var prev = -1;
        if (t[j] === q[i]) {
          var gain = start[j] ? 4 : 1;
          if (i === 0) {
            if (start[j]) v = gain - j * 0.05;
          } else if (j > 0) {
            if (D[i - 1][j - 1] > NONE) { v = D[i - 1][j - 1] + gain + 3; prev = j - 1; }
            // From further back: from anywhere to the start of a word, from the same word to any letter.
            var from = start[j] ? A[i - 1][j - 1] : W[i - 1][j - 1];
            if (from > NONE && from + gain > v) { v = from + gain; prev = start[j] ? AI[i - 1][j - 1] : WI[i - 1][j - 1]; }
          }
        }
        d.push(v);
        p.push(prev);
        if (all > NONE) all -= 0.2;
        if (v > all) { all = v; allAt = j; }
        if (start[j]) { word = NONE; wordAt = -1; } else if (word > NONE) word -= 0.2;
        if (v > word) { word = v; wordAt = j; }
        a.push(all); ai.push(allAt); w.push(word); wi.push(wordAt);
      }
      D.push(d); P.push(p); A.push(a); AI.push(ai); W.push(w); WI.push(wi);
    }
    var end = -1;
    var top = NONE;
    for (var e = 0; e < n; e++) if (D[m - 1][e] > top) { top = D[m - 1][e]; end = e; }
    if (end < 0) return null;
    var pos = [];
    for (var k = m - 1, at = end; k >= 0; k--) { pos.unshift(at); at = P[k][at]; }
    return { score: top, pos: pos };
  }

  // An entry against the query: { value, hits } — hits are places in the label — or null. The label
  // counts fully; keywords and the hint a tier and a half less, and mark nothing.
  function score(item, q) {
    var best = null;
    item._fold.forEach(function (f) {
      var r = place(f, q);
      if (!r) return;
      var value = r.tier * 1000 + r.score;
      if (!best || value > best.value) {
        var hits = {};
        r.pos.forEach(function (p) { hits[f.map[p]] = true; });
        best = { value: value, hits: hits };
      }
    });
    item._also.forEach(function (f) {
      var r = place(f, q);
      if (!r) return;
      var value = r.tier * 1000 + r.score - 1500;
      if (!best || value > best.value) best = { value: value, hits: {} };
    });
    return best;
  }
  // The marks alone, for a record the server chose: the server's order stays.
  function marks(item, q) {
    var hits = {};
    item._fold.some(function (f) {
      var r = place(f, q);
      if (r) r.pos.forEach(function (p) { hits[f.map[p]] = true; });
      return !!r;
    });
    return hits;
  }

  /* ── Entries ─────────────────────────────────────────────────────────── */

  /*
   * One shape for every entry, whatever brought it:
   *   { key, label, href, action, icon, group, hint, meta, shortcut, kind, source, element, data }
   * key is what the recent list remembers it by: the link, or "action:" and the name.
   */
  function entry(raw, source, group, extra) {
    if (!raw || typeof raw !== "object") return null;
    var label = str(raw.label != null ? raw.label : raw.title).normalize("NFC");
    var href = safeHref(raw.href != null ? raw.href : raw.url);
    var action = typeof raw.action === "string" && /^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/.test(raw.action) ? raw.action : "";
    if (!label || (!href && !action)) return null;
    if (href) action = "";
    var it = {
      key: href ? href : "action:" + action,
      label: label,
      href: href,
      action: action,
      icon: icon(raw.icon),
      group: str(raw.group) || group || "",
      hint: str(raw.hint != null ? raw.hint : raw.subtitle).normalize("NFC"),
      meta: "",
      shortcut: str(raw.shortcut),
      keywords: words(raw.keywords),
      kind: href ? "link" : "action",
      source: source,
      element: null,
      data: null
    };
    for (var k in extra || {}) it[k] = extra[k];
    if (!it.group) it.group = it.kind === "action" ? t("palette_commands") : t("palette_pages");
    return prepare(it);
  }
  function prepare(it) {
    it._fold = foldings(it.label);
    it._also = it.keywords.concat(it.hint ? [it.hint] : []).map(function (w) { return fold(w.normalize("NFC"), false); });
    return it;
  }
  // What a listener gets: the entry without the parts the palette keeps for itself.
  function view(it) {
    return { label: it.label, href: it.href || null, action: it.action || null, group: it.group, hint: it.hint,
      icon: it.icon, kind: it.kind, source: it.source, element: it.element, data: it.data };
  }

  // The links of the sidebar, as they stand when the palette opens: its label (data-label, else
  // .gk-sidebar-label, else the text), the heading above it, its symbol; a submenu's entry says
  // which entry it belongs to.
  function sidebarEntries(selector) {
    var out = [];
    if (!selector) return out;
    var navs;
    try { navs = document.querySelectorAll(selector); } catch (e) { return out; }
    Array.prototype.forEach.call(navs, function (nav) {
      if (nav.closest(PALETTE)) return;
      var heading = "";
      var walker = document.createTreeWalker(nav, NodeFilter.SHOW_ELEMENT);
      for (var el = walker.nextNode(); el; el = walker.nextNode()) {
        if (el.classList.contains("gk-sidebar-group-label")) { heading = str(el.textContent); continue; }
        if (el.tagName !== "A" || !el.hasAttribute("href")) continue;
        var raw = el.getAttribute("href");
        if (!raw || raw === "#") continue;
        var labelEl = el.querySelector(".gk-sidebar-label");
        var label = el.getAttribute("data-label") || (labelEl ? labelEl.textContent : el.textContent);
        var symbol = el.querySelector(".gk-sidebar-icon, .material-icons");
        var parent = el.closest(".gk-sidebar-subitems");
        var toggle = parent && parent.previousElementSibling;
        var hint = toggle && toggle.getAttribute("data-label") ? toggle.getAttribute("data-label") : "";
        if (el.getAttribute("aria-current") === "page") hint = hint ? hint + " · " + t("palette_current") : t("palette_current");
        var it = entry({ label: label, href: raw, icon: symbol ? symbol.textContent.trim() : "", hint: hint }, "sidebar",
          heading || t("palette_pages"), { element: el });
        if (it) out.push(it);
      }
    });
    return out;
  }

  // Everything that is not fetched: the sidebar, the server's items, what a script registered.
  function collect(st) {
    var out = sidebarEntries(st.cfg.sidebar);
    (st.cfg.items || []).forEach(function (raw) {
      var it = entry(raw, "page", "");
      if (it) out.push(it);
    });
    sources.forEach(function (src) {
      if (src.remote) return;
      var list = src.items;
      if (typeof list === "function") {
        try { list = list(); } catch (e) { list = []; if (window.console) console.error(e); }
      }
      (Array.isArray(list) ? list : []).forEach(function (raw) {
        var it = entry(raw, "script", src.group, { data: raw });
        if (it) out.push(it);
      });
    });
    return out;
  }

  /* ── The records: fetched, debounced, the stale answer dropped ───────── */

  function sameOrigin(url) {
    try { return new URL(url, location.href).origin === location.origin; } catch (e) { return false; }
  }

  // A search source from the server's config or from register({ url | search }).
  function remoteSource(raw, fromScript) {
    var src = {
      url: "", fn: null, group: str(raw.group), param: str(raw.param) || "q",
      minLength: typeof raw.minLength === "number" && raw.minLength >= 0 ? Math.floor(raw.minLength) : 2,
      debounce: typeof raw.debounce === "number" && raw.debounce >= 0 ? raw.debounce : 250,
      origin: fromScript ? "script" : "page"
    };
    if (typeof raw.search === "function") src.fn = raw.search;
    else if (typeof raw.url === "string" && raw.url && safeHref(raw.url)) {
      if (!sameOrigin(raw.url)) {
        if (window.console) console.warn("GridKit palette: " + raw.url + " is on another origin — it is not searched.");
        return null;
      }
      src.url = raw.url;
    } else return null;
    return src;
  }

  // The live state of each search source in one palette.
  function remotes(st) {
    var list = (st.cfg.search || []).map(function (raw) { return remoteSource(raw, false); });
    sources.forEach(function (s) { if (s.remote) list.push(s.remote); });
    return list.filter(Boolean).map(function (src) {
      return { src: src, state: "idle", q: "", items: [], timer: 0, ctrl: null, seq: 0 };
    });
  }

  // What came back, read leniently: {groups:[{title, items}]} (GK.search's shape, its German names too),
  // {items:[…]}, or a bare list. A record needs a title and a link.
  function records(data, r) {
    var out = [];
    var groups = Array.isArray(data) ? [{ items: data }]
      : data && typeof data === "object"
        ? (Array.isArray(data.groups) ? data.groups : Array.isArray(data.gruppen) ? data.gruppen : Array.isArray(data.items) ? [{ items: data.items }] : [])
        : [];
    groups.forEach(function (g) {
      if (!g || typeof g !== "object") return;
      var title = str(g.title || g.titel) || r.src.group || t("palette_records");
      var list = Array.isArray(g.items) ? g.items : Array.isArray(g.treffer) ? g.treffer : [];
      list.forEach(function (rec) {
        if (!rec || typeof rec !== "object") return;
        var it = entry({ label: rec.title != null ? rec.title : rec.label != null ? rec.label : rec.titel,
          href: rec.url != null ? rec.url : rec.href, icon: rec.icon,
          hint: rec.subtitle != null ? rec.subtitle : rec.hint != null ? rec.hint : rec.untertitel },
        "search", title, { kind: "record", data: rec, meta: str(rec.amount != null ? rec.amount : rec.betrag) });
        if (it && it.href) { it.group = title; out.push(it); }
      });
    });
    return out;
  }

  function ask(st, r) {
    var q = r.q;
    var my = ++r.seq;
    if (r.ctrl) r.ctrl.abort();
    r.ctrl = typeof AbortController === "function" ? new AbortController() : null;
    var signal = r.ctrl ? r.ctrl.signal : undefined;
    r.state = "loading";
    paintStatus(st);
    var p;
    if (r.src.fn) {
      p = Promise.resolve().then(function () { return r.src.fn(q, signal); });
    } else {
      var u = new URL(r.src.url, location.href);
      u.searchParams.set(r.src.param, q);
      p = fetch(u.toString(), {
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        signal: signal
      }).then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      });
    }
    p.then(function (data) {
      if (my !== r.seq || r.q !== q || !st.isOpen) return;   // a newer question was asked meanwhile
      r.ctrl = null;
      r.items = records(data, r);
      r.state = "done";
      refresh(st, true);
    }, function (err) {
      if (my !== r.seq || !st.isOpen) return;
      if (err && err.name === "AbortError") return;
      r.ctrl = null;
      r.items = [];
      r.state = "error";
      refresh(st, true);
    });
  }

  // q is what was typed (trimmed): the server gets it as it was written and folds it its own way.
  function searchAll(st, q) {
    var folded = foldQuery(q);
    st.remote.forEach(function (r) {
      clearTimeout(r.timer);
      if (r.ctrl) { r.ctrl.abort(); r.ctrl = null; }
      r.seq++;
      if (!q || q.length < r.src.minLength) {
        r.state = "idle";
        r.items = [];
        r.q = q;
        return;
      }
      // What came back for the last query and still matches stays until the new answer is there.
      r.items = r.items.filter(function (it) { return score(it, folded); });
      r.q = q;
      r.state = "wait";
      r.timer = setTimeout(function () { ask(st, r); }, r.src.debounce);
    });
  }
  function stopAll(st) {
    st.remote.forEach(function (r) {
      clearTimeout(r.timer);
      if (r.ctrl) { r.ctrl.abort(); r.ctrl = null; }
      r.seq++;
      r.state = "idle";
      r.items = [];
      r.q = "";
    });
  }

  /* ── Remembered: the recent entries ──────────────────────────────────── */

  // Per palette and in GK.theme's scope (GK.theme.init({ scope }), window.GK_THEME_SCOPE): the next person
  // at a shared computer does not find the last one's customers. Storage that throws just forgets.
  function storeKey(st) {
    var name = "gk-palette-" + st.id;
    var theme = window.GK && window.GK.theme;
    if (theme && typeof theme._key === "function") return theme._key(name);
    return window.GK_THEME_SCOPE ? name + ":" + window.GK_THEME_SCOPE : name;
  }
  function readRecent(st) {
    if (!st.cfg.recent) return [];
    var raw;
    try { raw = window.localStorage.getItem(storeKey(st)); } catch (e) { return []; }
    var list;
    try { list = JSON.parse(raw || "[]"); } catch (e) { return []; }
    if (!Array.isArray(list)) return [];
    var out = [];
    list.forEach(function (r) {
      if (!r || typeof r !== "object" || out.length >= 20) return;
      var kind = r.t === "record" || r.t === "action" ? r.t : "link";
      var it = entry({ label: r.l, href: kind === "action" ? "" : r.h, action: kind === "action" ? r.a : "", icon: r.i, hint: r.s },
        "recent", t("palette_recent"), { kind: kind });
      if (it) out.push(it);
    });
    return out;
  }
  function remember(st, it) {
    if (!st.cfg.recent) return;
    var list = readRecent(st).filter(function (r) { return r.key !== it.key; });
    list.unshift(it);
    var stored = list.slice(0, st.cfg.recent).map(function (r) {
      var o = { t: r.kind, l: r.label };
      if (r.href) o.h = r.href;
      if (r.action) o.a = r.action;
      if (r.icon) o.i = r.icon;
      if (r.hint && r.kind === "record") o.s = r.hint;
      return o;
    });
    try { window.localStorage.setItem(storeKey(st), JSON.stringify(stored)); } catch (e) { /* forgets */ }
  }

  // The recent list as it is shown: an entry this page still has is shown as the page has it now; an
  // action this page does not have is left out (its listener is not here); a link or a record stays.
  function recentShown(st) {
    var byKey = {};
    st.local.forEach(function (it) { if (!byKey[it.key]) byKey[it.key] = it; });
    var out = [];
    st.recent.forEach(function (r) {
      var live = byKey[r.key];
      if (live) out.push(live);
      else if (r.kind !== "action") out.push(r);
    });
    return out.slice(0, st.cfg.recent);
  }

  /* ── The list ────────────────────────────────────────────────────────── */

  // Groups of entries, each { name, items, more }: with no query the recent ones first, then every
  // group in the order its first entry came; with a query the best match first. Then the records, in the
  // order the server sent them. At most `max` under one heading.
  function compute(st) {
    var q = st.query;
    var max = st.cfg.max;
    var groups = [];
    var byName = {};
    var seen = {};
    // A heading of records stays apart from a heading of the page with the same name: records come last.
    function add(it, hits, record) {
      seen[it.key] = true;
      var id = it.group + (record ? "\u0000r" : "");
      var g = byName[id];
      if (!g) {
        g = byName[id] = { name: it.group, items: [], more: 0 };
        groups.push(g);
      }
      g.items.push({ it: it, hits: hits });
    }
    if (!q) {
      var rec = recentShown(st);
      rec.forEach(function (it) { add(Object.assign({}, it, { group: t("palette_recent") }), {}); });
      // What is in the recent list is not shown a second time below it.
      st.local.forEach(function (it) { if (!seen[it.key]) add(it, {}); });
    } else {
      var pool = st.local.slice();
      var have = {};
      pool.forEach(function (it) { have[it.key] = true; });
      st.recent.forEach(function (r) { if (r.kind === "record" && !have[r.key]) pool.push(r); });
      var found = [];
      pool.forEach(function (it, i) {
        var s = score(it, q);
        if (s) found.push({ it: it, s: s, i: i });
      });
      found.sort(function (a, b) {
        return b.s.value - a.s.value || a.it.label.length - b.it.label.length || a.i - b.i;
      });
      // Added best first, so the groups stand in the order of their best match.
      found.forEach(function (f) { if (!seen[f.it.key]) add(f.it, f.s.hits); });
    }
    st.remote.forEach(function (r) {
      r.items.forEach(function (it) {
        if (seen[it.key]) return;
        add(it, q ? marks(it, q) : {}, true);
      });
    });
    groups.forEach(function (g) {
      if (g.items.length > max) { g.more = g.items.length - max; g.items = g.items.slice(0, max); }
    });
    return groups;
  }

  function paint(el, text, hits) {
    el.textContent = "";
    var i = 0;
    while (i < text.length) {
      var on = !!hits[i];
      var j = i;
      while (j < text.length && !!hits[j] === on) j++;
      var part = text.slice(i, j);
      if (on) {
        var mk = document.createElement("mark");
        mk.className = "gk-palette-hit";
        mk.textContent = part;
        el.appendChild(mk);
      } else el.appendChild(document.createTextNode(part));
      i = j;
    }
  }

  function optionEl(st, row, n) {
    var it = row.it;
    var el = document.createElement(it.href ? "a" : "div");
    el.className = "gk-palette-option";
    el.id = st.base + "-o" + n;
    el.setAttribute("role", "option");
    el.setAttribute("aria-selected", "false");
    if (it.href) {
      el.setAttribute("href", it.href);
      el.tabIndex = -1;
    }
    var sym = document.createElement("span");
    sym.className = "gk-palette-icon" + (it.icon ? " material-icons" : "");
    sym.setAttribute("aria-hidden", "true");
    sym.textContent = it.icon;
    el.appendChild(sym);
    var text = document.createElement("span");
    text.className = "gk-palette-text";
    var label = document.createElement("span");
    label.className = "gk-palette-label";
    paint(label, it.label, row.hits);
    text.appendChild(label);
    if (it.hint) {
      var hint = document.createElement("span");
      hint.className = "gk-palette-hint";
      hint.textContent = it.hint;
      text.appendChild(hint);
    }
    el.appendChild(text);
    if (it.meta) {
      var meta = document.createElement("span");
      meta.className = "gk-palette-meta";
      meta.textContent = it.meta;
      el.appendChild(meta);
    }
    if (it.shortcut) {
      var kbd = document.createElement("kbd");
      kbd.className = "gk-palette-shortcut";
      kbd.textContent = it.shortcut;
      el.appendChild(kbd);
    }
    return el;
  }

  // Draws the list. `keep`: the person may have moved on the list while records arrived — the entry
  // they are on stays theirs.
  function refresh(st, keep) {
    var activeKey = keep && st.moved && st.options[st.active] ? st.options[st.active].key : null;
    var groups = compute(st);
    var list = st.list;
    var top = list.scrollTop;
    list.textContent = "";
    st.options = [];
    var n = 0;
    groups.forEach(function (g, gi) {
      var box = document.createElement("div");
      box.className = "gk-palette-group";
      box.setAttribute("role", "group");
      var head = document.createElement("div");
      head.className = "gk-palette-group-label";
      head.id = st.base + "-g" + gi;
      head.setAttribute("role", "presentation");
      head.textContent = g.name;
      box.setAttribute("aria-labelledby", head.id);
      box.appendChild(head);
      g.items.forEach(function (row) {
        box.appendChild(optionEl(st, row, n++));
        st.options.push(row.it);
      });
      if (g.more) {
        var more = document.createElement("div");
        more.className = "gk-palette-more";
        more.setAttribute("aria-hidden", "true");
        more.textContent = t("palette_more", { n: g.more });
        box.appendChild(more);
      }
      list.appendChild(box);
    });
    var next = 0;
    if (activeKey) {
      for (var i = 0; i < st.options.length; i++) if (st.options[i].key === activeKey) { next = i; break; }
      list.scrollTop = top;
    } else {
      st.moved = false;
      list.scrollTop = 0;
    }
    st.active = -1;
    setActive(st, st.options.length ? next : -1, !activeKey);
    paintStatus(st);
    sayLater(st);
  }

  function setActive(st, i, scroll) {
    var old = st.active >= 0 ? document.getElementById(st.base + "-o" + st.active) : null;
    if (old) old.setAttribute("aria-selected", "false");
    st.active = i;
    var el = i >= 0 ? document.getElementById(st.base + "-o" + i) : null;
    if (!el) {
      st.input.removeAttribute("aria-activedescendant");
      return;
    }
    el.setAttribute("aria-selected", "true");
    st.input.setAttribute("aria-activedescendant", el.id);
    if (scroll !== false) el.scrollIntoView({ block: "nearest" });
  }

  function pending(st) {
    return st.remote.some(function (r) { return r.state === "wait" || r.state === "loading"; });
  }

  // The line under the list: searching, no records, failed (with a way to ask again). And the empty state.
  function paintStatus(st) {
    var busy = pending(st);
    var failed = st.remote.some(function (r) { return r.state === "error"; });
    var answered = st.remote.filter(function (r) { return r.state === "done"; });
    var found = answered.some(function (r) { return r.items.length > 0; });
    var s = st.status;
    s.textContent = "";
    s.removeAttribute("data-gk-state");
    st.list.setAttribute("aria-busy", busy ? "true" : "false");
    if (busy) {
      s.setAttribute("data-gk-state", "loading");
      s.appendChild(document.createTextNode(t("palette_loading")));
    } else if (failed) {
      s.setAttribute("data-gk-state", "error");
      var span = document.createElement("span");
      span.textContent = t("palette_error");
      s.appendChild(span);
      var retry = document.createElement("button");
      retry.type = "button";
      retry.className = "gk-palette-retry";
      retry.textContent = t("palette_retry");
      s.appendChild(retry);
    } else if (st.query && answered.length && !found && st.options.length) {
      s.setAttribute("data-gk-state", "empty");
      s.textContent = t("palette_no_records");
    }
    s.hidden = !s.hasAttribute("data-gk-state");
    var none = !!st.query && !st.options.length && !busy;
    st.empty.hidden = !none;
    st.empty.textContent = none ? t("palette_none", { q: st.input.value.trim() }) : "";
  }

  // The number, spoken once the list has settled — not at every key. Emptied first, so the same words
  // twice are heard twice.
  function sayLater(st) {
    clearTimeout(st.sayTimer);
    st.sayTimer = setTimeout(function () {
      if (!st.isOpen) return;
      var text;
      if (st.options.length) text = st.options.length === 1 ? t("palette_count_one") : t("palette_count", { n: st.options.length });
      else if (pending(st)) return;
      else text = st.query ? t("palette_none", { q: st.input.value.trim() }) : "";
      if (!st.status.hidden && st.status.getAttribute("data-gk-state") === "error") text = (text ? text + ". " : "") + t("palette_error");
      st.live.textContent = "";
      setTimeout(function () { st.live.textContent = text; }, 30);
    }, SAY_MS);
  }

  /* ── Opening, choosing, closing ──────────────────────────────────────── */

  function fire(st, name, detail, cancelable) {
    detail.palette = st.id;
    return st.root.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable: !!cancelable, detail: detail }));
  }

  function open(st, how, query) {
    if (st.isOpen) {
      if (typeof query === "string") { st.input.value = query; onInput(st); }
      st.input.focus();
      return st.root;
    }
    var a = document.activeElement;
    st.opener = a && a !== document.body && !st.root.contains(a) ? a : null;
    st.local = collect(st);
    st.recent = readRecent(st);
    st.remote = remotes(st);
    st.input.value = typeof query === "string" ? query : "";
    st.query = foldQuery(st.input.value);
    st.moved = false;
    try {
      if (!st.root.open) st.root.showModal();
    } catch (e) {
      st.root.setAttribute("open", "");
    }
    st.isOpen = true;
    st.input.setAttribute("aria-expanded", "true");
    refresh(st, false);
    searchAll(st, typed(st));
    paintStatus(st);
    st.input.focus();
    st.input.select();
    fire(st, "gk:paletteopen", { source: how || "api" });
    return st.root;
  }

  function close(st) {
    if (!st.isOpen) return;
    st.isOpen = false;
    stopAll(st);
    clearTimeout(st.sayTimer);
    st.live.textContent = "";
    if (st.root.open) {
      try { st.root.close(); } catch (e) { st.root.removeAttribute("open"); }
    }
    st.input.setAttribute("aria-expanded", "false");
    st.input.removeAttribute("aria-activedescendant");
    st.list.textContent = "";
    st.options = [];
    st.active = -1;
    var back = st.opener;
    st.opener = null;
    if (!back || !back.isConnected) back = document.querySelector('[data-gk-palette-open="' + cssEscape(st.id) + '"]:not([hidden])');
    if (back && typeof back.focus === "function") {
      try { back.focus({ preventScroll: true }); } catch (e) { back.focus(); }
    }
  }

  function cssEscape(s) {
    return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/["\\]/g, "\\$&");
  }

  // An entry was chosen. gk:paletteselect may refuse it; else it is remembered, the palette closes and
  // the entry is followed: a sidebar link by a click on that link (so the page's own navigation, AJAX
  // or not, sees it), any other link by the address, an action by gk:paletteaction.
  function choose(st, it, newTab) {
    if (!it) return false;
    var q = st.input.value.trim();
    newTab = !!newTab && !!it.href;
    if (!fire(st, "gk:paletteselect", { item: view(it), query: q, newTab: newTab }, true)) return false;
    remember(st, it);
    close(st);
    if (it.action) {
      fire(st, "gk:paletteaction", { action: it.action, item: view(it), query: q });
    } else if (newTab) {
      window.open(it.href, "_blank", "noopener");
    } else if (it.element && it.element.isConnected) {
      it.element.click();
    } else {
      location.assign(it.href);
    }
    return true;
  }

  function typed(st) { return st.input.value.replace(/\s+/g, " ").trim(); }

  function onInput(st) {
    st.query = foldQuery(st.input.value);
    st.moved = false;
    searchAll(st, typed(st));
    refresh(st, false);
  }

  function step(st) {
    var opt = st.list.querySelector(".gk-palette-option");
    var h = opt ? opt.getBoundingClientRect().height : 0;
    return h ? Math.max(1, Math.floor(st.list.clientHeight / h) - 1) : 5;
  }

  function onKey(st, e) {
    if (e.isComposing || e.keyCode === 229) return;
    var last = st.options.length - 1;
    var go = function (i) { st.moved = true; setActive(st, i); };
    switch (e.key) {
      case "ArrowDown":
        if (last >= 0) go(st.active >= last ? 0 : st.active + 1);
        break;
      case "ArrowUp":
        if (last >= 0) go(st.active <= 0 ? last : st.active - 1);
        break;
      case "Home":
        if (e.shiftKey || last < 0) return;
        go(0);
        break;
      case "End":
        if (e.shiftKey || last < 0) return;
        go(last);
        break;
      case "PageDown":
        if (last >= 0) go(Math.min(last, Math.max(0, st.active) + step(st)));
        break;
      case "PageUp":
        if (last >= 0) go(Math.max(0, st.active - step(st)));
        break;
      case "Enter":
        if (st.active >= 0) choose(st, st.options[st.active], e.ctrlKey || e.metaKey);
        break;
      default:
        return;
    }
    e.preventDefault();
  }

  // Escape closes — and is the palette's: a modal underneath must not close with it. Tab goes round
  // inside: the field, a "Try again", the close button.
  function onDialogKey(st, e) {
    if (e.key === "Escape") {
      e.preventDefault();
      e.stopPropagation();
      close(st);
      return;
    }
    if (e.key !== "Tab") return;
    var stops = Array.prototype.filter.call(st.root.querySelectorAll("input, button"), function (el) {
      return !el.disabled && el.getClientRects().length > 0;
    });
    if (!stops.length) return;
    var i = stops.indexOf(document.activeElement);
    var next = e.shiftKey ? (i <= 0 ? stops.length - 1 : i - 1) : (i === -1 || i === stops.length - 1 ? 0 : i + 1);
    e.preventDefault();
    stops[next].focus();
  }

  /* ── Keys of the page ────────────────────────────────────────────────── */

  function parseHotkey(s) {
    if (typeof s !== "string" || !s) return null;
    var parts = s.toLowerCase().split("+");
    var key = parts.pop();
    var hk = { ctrl: false, meta: false, alt: false, shift: false, key: key };
    parts.forEach(function (p) {
      if (p === "mod") { if (MAC) hk.meta = true; else hk.ctrl = true; }
      else if (p in hk && p !== "key") hk[p] = true;
    });
    return key && (hk.ctrl || hk.meta || hk.alt) ? hk : null;
  }
  var CODES = { Slash: "/", Period: ".", Comma: ",", Semicolon: ";" };
  function keyOf(e) {
    var c = e.code || "";
    if (/^Key[A-Z]$/.test(c)) return c.slice(3).toLowerCase();
    if (/^Digit[0-9]$/.test(c)) return c.slice(5);
    return CODES[c] || "";
  }
  // The modifiers exactly — Ctrl+Shift+K is the browser's, not the palette's — and the key by its letter or,
  // on a keyboard that writes another alphabet, by its place.
  function pressed(hk, e) {
    if (!hk || e.ctrlKey !== hk.ctrl || e.metaKey !== hk.meta || e.altKey !== hk.alt || e.shiftKey !== hk.shift) return false;
    return (e.key || "").toLowerCase() === hk.key || keyOf(e) === hk.key;
  }
  function editable(el) {
    if (!el || el.nodeType !== 1) return false;
    if (el.isContentEditable) return true;
    var tag = el.tagName;
    if (tag === "TEXTAREA" || tag === "SELECT") return true;
    if (tag !== "INPUT") return false;
    return !/^(checkbox|radio|button|submit|reset|range|color|file|image)$/i.test(el.type || "");
  }

  // Its key belongs to the palette while it is on the page: taken first (capture, on window), so the global
  // search (GK.search, also on Ctrl+K) does not open as well. Inside a rich-text editor the editor keeps it
  // (Ctrl+K makes a link there). "/" opens it from anywhere but a field.
  function onPageKey(e) {
    if (e.defaultPrevented || e.isComposing) return;
    for (var i = 0; i < states.length; i++) {
      var st = states[i];
      if (!st.root.isConnected) continue;
      if (st.hotkey && pressed(st.hotkey, e)) {
        if (!st.isOpen && e.target && e.target.isContentEditable) return;
        e.preventDefault();
        e.stopPropagation();
        if (e.repeat) return;
        if (st.isOpen) close(st);
        else open(st, "hotkey");
        return;
      }
      if (st.cfg.slash && !st.isOpen && e.key === "/" && !e.ctrlKey && !e.metaKey && !e.altKey && !editable(e.target)
        && !document.querySelector("dialog[open]")) {
        e.preventDefault();
        e.stopPropagation();
        open(st, "slash");
        return;
      }
    }
  }

  // The words of a key on this computer: ⌘K on a Mac, Ctrl K elsewhere.
  function keyWords(combo) {
    var mac = { mod: "⌘", meta: "⌘", ctrl: "⌃", alt: "⌥", shift: "⇧" };
    var pc = { mod: "Ctrl", ctrl: "Ctrl", meta: "Meta", alt: "Alt", shift: "Shift" };
    var parts = String(combo).toLowerCase().split("+");
    var key = parts.pop().toUpperCase();
    if (MAC) {
      // The Mac's own order: ⌃ ⌥ ⇧ ⌘, then the key, without spaces.
      var order = ["ctrl", "alt", "shift", "mod", "meta"];
      return order.filter(function (m) { return parts.indexOf(m) !== -1; }).map(function (m) { return mac[m]; })
        .filter(function (v, i, a) { return a.indexOf(v) === i; }).join("") + key;
    }
    return parts.map(function (m) { return pc[m] || m; }).concat([key]).join(" ");
  }
  function ariaKeys(combo) {
    var names = { mod: MAC ? "Meta" : "Control", ctrl: "Control", meta: "Meta", alt: "Alt", shift: "Shift" };
    var parts = String(combo).toLowerCase().split("+");
    var key = parts.pop().toUpperCase();
    return parts.map(function (m) { return names[m] || m; }).concat([key]).join("+");
  }

  // A trigger the script can serve: shown (it is hidden without the script), announced as opening a
  // dialog, its key in this computer's words.
  function enhanceTrigger(b) {
    if (b._gkPalette) return;
    b._gkPalette = true;
    b.hidden = false;
    b.setAttribute("aria-haspopup", "dialog");
    var kbd = b.querySelector("[data-gk-palette-hotkey]");
    if (kbd) {
      var combo = kbd.getAttribute("data-gk-palette-hotkey");
      kbd.textContent = combo === "/" ? "/" : keyWords(combo);
      var slash = (b.getAttribute("aria-keyshortcuts") || "").split(" ").indexOf("/") !== -1;
      if (combo !== "/") b.setAttribute("aria-keyshortcuts", ariaKeys(combo) + (slash ? " /" : ""));
    }
  }

  /* ── Setting up ──────────────────────────────────────────────────────── */

  function config(root) {
    var cfg = {};
    var el = root.querySelector("script[data-gk-palette-config]");
    if (el) {
      try { cfg = JSON.parse(el.textContent || "{}") || {}; } catch (e) { cfg = {}; }
    }
    if (!cfg || typeof cfg !== "object" || Array.isArray(cfg)) cfg = {};
    return {
      hotkey: typeof cfg.hotkey === "string" ? cfg.hotkey : "",
      slash: cfg.slash === true,
      sidebar: typeof cfg.sidebar === "string" ? cfg.sidebar : "",
      recent: typeof cfg.recent === "number" && cfg.recent >= 0 ? Math.min(20, Math.floor(cfg.recent)) : 5,
      max: typeof cfg.max === "number" && cfg.max >= 1 ? Math.min(50, Math.floor(cfg.max)) : 6,
      items: Array.isArray(cfg.items) ? cfg.items : [],
      search: Array.isArray(cfg.search) ? cfg.search : []
    };
  }

  function setup(root) {
    for (var i = 0; i < states.length; i++) if (states[i].root === root) return states[i];
    var input = root.querySelector(".gk-palette-input");
    var list = root.querySelector(".gk-palette-list");
    if (!input || !list) return null;
    var st = {
      root: root,
      id: root.getAttribute("data-gk-palette") || "main",
      base: root.id || "gk-palette-x" + ++counter,
      cfg: config(root),
      input: input,
      list: list,
      status: root.querySelector(".gk-palette-status"),
      empty: root.querySelector(".gk-palette-empty"),
      live: root.querySelector("[data-gk-palette-live]"),
      isOpen: false, opener: null, query: "", local: [], recent: [], remote: [], options: [], active: -1, moved: false, sayTimer: 0
    };
    if (!root.id) root.id = st.base;
    if (!st.status) { st.status = document.createElement("div"); st.status.className = "gk-palette-status"; st.status.hidden = true; root.appendChild(st.status); }
    if (!st.empty) { st.empty = document.createElement("p"); st.empty.className = "gk-palette-empty"; st.empty.hidden = true; root.appendChild(st.empty); }
    if (!st.live) { st.live = document.createElement("p"); st.live.className = "gk-sr-only"; st.live.setAttribute("aria-live", "polite"); root.appendChild(st.live); }
    st.hotkey = parseHotkey(st.cfg.hotkey);
    states.push(st);

    input.addEventListener("input", function () { onInput(st); });
    input.addEventListener("keydown", function (e) { onKey(st, e); });
    root.addEventListener("keydown", function (e) { onDialogKey(st, e); });
    // Escape, as the browser sends it to a modal dialog: closed the palette's way, with the focus given back.
    root.addEventListener("cancel", function (e) { e.preventDefault(); close(st); });
    // Closed from outside (dialog.close(), a form with method="dialog"): the palette follows. The event is
    // queued — after a quick Escape and Ctrl+K it arrives when the dialog is open again, and is ignored then.
    root.addEventListener("close", function () { if (st.isOpen && !root.open) close(st); });
    // A press on the backdrop: the dialog itself is hit only outside its content.
    root.addEventListener("click", function (e) {
      if (e.target !== root) return;
      var r = root.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) close(st);
    });
    var shut = root.querySelector(".gk-palette-close");
    if (shut) shut.addEventListener("click", function () { close(st); });
    st.status.addEventListener("click", function (e) {
      if (!e.target.closest(".gk-palette-retry")) return;
      st.remote.forEach(function (r) { if (r.state === "error") { r.state = "wait"; ask(st, r); } });
      input.focus();
    });
    // The pointer: hovering marks, a press does not take the focus out of the field, a click chooses. A
    // link pressed with Ctrl, Cmd, Shift or the middle button goes where the browser sends it — as a link.
    list.addEventListener("mousemove", function (e) {
      var o = e.target.closest(".gk-palette-option");
      if (!o) return;
      var i = Number(o.id.slice(o.id.lastIndexOf("-o") + 2));
      if (i !== st.active) { st.moved = true; setActive(st, i, false); }
    });
    list.addEventListener("mousedown", function (e) { if (e.target.closest(".gk-palette-option")) e.preventDefault(); });
    list.addEventListener("click", function (e) {
      var o = e.target.closest(".gk-palette-option");
      if (!o) return;
      var it = st.options[Number(o.id.slice(o.id.lastIndexOf("-o") + 2))];
      if (!it) return;
      var aside = e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1;
      if (aside && it.href) {
        if (!fire(st, "gk:paletteselect", { item: view(it), query: st.input.value.trim(), newTab: true }, true)) e.preventDefault();
        else remember(st, it);
        return;
      }
      e.preventDefault();
      choose(st, it, false);
    });
    list.addEventListener("auxclick", function (e) {
      var o = e.button === 1 ? e.target.closest("a.gk-palette-option") : null;
      var it = o ? st.options[Number(o.id.slice(o.id.lastIndexOf("-o") + 2))] : null;
      if (!it) return;
      if (!fire(st, "gk:paletteselect", { item: view(it), query: st.input.value.trim(), newTab: true }, true)) e.preventDefault();
      else remember(st, it);
    });
    if (MAC) root.querySelectorAll("[data-gk-palette-mod]").forEach(function (k) { k.textContent = "⌘"; });
    return st;
  }

  // A palette for a page whose server wrote none: GK.palette.open() works anyway — the sidebar and what
  // a script registered, no key of its own.
  function build() {
    var id = "gk-palette-auto";
    var d = document.createElement("dialog");
    d.className = "gk-palette";
    d.id = id;
    d.setAttribute("data-gk-palette", "auto");
    d.setAttribute("aria-label", t("palette_label"));
    var svg = function (path) {
      return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">' + path + "</svg>";
    };
    d.innerHTML = '<div class="gk-palette-head">' + svg('<circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.2" y2="16.2"/>')
      + '<input type="text" class="gk-palette-input" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go">'
      + '<button type="button" class="gk-palette-close">' + svg('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>') + "</button></div>"
      + '<div class="gk-palette-list" role="listbox"></div><p class="gk-palette-empty" hidden></p><div class="gk-palette-status" hidden></div>'
      + '<p class="gk-sr-only"></p><p class="gk-sr-only" aria-live="polite" data-gk-palette-live></p>'
      + '<div class="gk-palette-foot" aria-hidden="true"><span><kbd class="gk-palette-kbd">↑</kbd><kbd class="gk-palette-kbd">↓</kbd> </span>'
      + '<span><kbd class="gk-palette-kbd">↵</kbd> </span><span><kbd class="gk-palette-kbd" data-gk-palette-mod>Ctrl</kbd><kbd class="gk-palette-kbd">↵</kbd> </span>'
      + '<span><kbd class="gk-palette-kbd">Esc</kbd> </span></div>'
      + '<script type="application/json" data-gk-palette-config>{"sidebar":".gk-sidebar-nav"}<\/script>';
    // The words as text, never as markup.
    var input = d.querySelector("input");
    input.id = id + "-input";
    input.setAttribute("aria-controls", id + "-list");
    input.setAttribute("aria-describedby", id + "-help");
    input.setAttribute("aria-label", t("palette_placeholder"));
    input.placeholder = t("palette_placeholder");
    var shut = d.querySelector(".gk-palette-close");
    shut.setAttribute("aria-label", t("palette_close"));
    shut.title = t("palette_close");
    var list = d.querySelector(".gk-palette-list");
    list.id = id + "-list";
    list.setAttribute("aria-label", t("palette_results"));
    var help = d.querySelector("p.gk-sr-only");
    help.id = id + "-help";
    help.textContent = t("palette_help");
    var foot = d.querySelectorAll(".gk-palette-foot > span");
    ["palette_foot_move", "palette_foot_open", "palette_foot_tab", "palette_foot_close"].forEach(function (k, i) {
      foot[i].lastChild.textContent = " " + t(k);
    });
    document.body.appendChild(d);
    return setup(d);
  }

  // A palette by its id (as given to new CommandPalette()), its element or a selector; the first one
  // without. null when there is none and none could be built.
  function find(which, make) {
    var root = null;
    if (which && typeof which === "object" && which.nodeType === 1) root = which.closest(PALETTE);
    else if (typeof which === "string" && which) {
      document.querySelectorAll(PALETTE).forEach(function (d) { if (!root && d.getAttribute("data-gk-palette") === which) root = d; });
      if (!root) { try { root = document.querySelector(which); } catch (e) { root = null; } }
      if (root) root = root.closest(PALETTE);
    } else {
      root = document.querySelector(PALETTE);
    }
    if (root) return setup(root);
    return make && document.body ? build() : null;
  }

  /* ── The API ─────────────────────────────────────────────────────────── */

  var api = {
    // Opens the palette: GK.palette.open(), GK.palette.open('inv') with a query typed in, or
    // GK.palette.open({ id, query }). Returns the <dialog>, or null.
    open: function (opts) {
      if (typeof opts === "string") opts = { query: opts };
      opts = opts && typeof opts === "object" ? opts : {};
      var st = find(opts.id, true);
      return st ? open(st, "api", typeof opts.query === "string" ? opts.query : undefined) : null;
    },
    // Closes it (the open one, or the one named).
    close: function (id) {
      if (id) { var st = find(id, false); if (st) close(st); return; }
      states.forEach(function (s) { close(s); });
    },
    /*
     * More entries, from a script, for every palette of the page. Returns a function that takes them away.
     *   GK.palette.register([{ label, href | action, icon, group, keywords, hint, shortcut }, …])
     *   GK.palette.register({ group: 'Tools', items: () => [...] })         asked each time it opens
     *   GK.palette.register({ group: 'People', url: '/api/people', minLength: 2, debounce: 250, param: 'q' })
     *   GK.palette.register({ group: 'Orders', search: (query, signal) => fetch(…).then(r => r.json()) })
     * An entry of a script reaches the events with the object it was registered as, under item.data.
     */
    register: function (source) {
      var src = null;
      if (Array.isArray(source)) src = { items: source, group: "" };
      else if (source && typeof source === "object") {
        if (Array.isArray(source.items) || typeof source.items === "function") src = { items: source.items, group: str(source.group) };
        else if (typeof source.url === "string" || typeof source.search === "function") {
          var remote = remoteSource(source, true);
          if (remote) src = { remote: remote, group: remote.group };
        }
      }
      if (!src) {
        if (window.console) console.warn("GridKit palette: register() takes a list of entries, { items }, { url } or { search } — nothing was added.");
        return function () {};
      }
      sources.push(src);
      states.forEach(function (st) {
        if (!st.isOpen) return;
        st.local = collect(st);
        if (src.remote) st.remote.push({ src: src.remote, state: "idle", q: "", items: [], timer: 0, ctrl: null, seq: 0 });
        refresh(st, true);
        if (src.remote) searchAll(st, typed(st));
      });
      return function () {
        var i = sources.indexOf(src);
        if (i !== -1) sources.splice(i, 1);
        states.forEach(function (st) {
          if (!st.isOpen) return;
          st.local = collect(st);
          st.remote = st.remote.filter(function (r) {
            if (r.src !== src.remote) return true;
            clearTimeout(r.timer);
            if (r.ctrl) r.ctrl.abort();
            return false;
          });
          refresh(st, true);
        });
      };
    }
  };
  window.GK = window.GK || {};
  window.GK.palette = api;

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(node) {
    if (node.nodeType !== 1) return;
    if (node.matches(PALETTE)) setup(node);
    node.querySelectorAll(PALETTE).forEach(setup);
    if (node.matches(TRIGGER)) enhanceTrigger(node);
    node.querySelectorAll(TRIGGER).forEach(enhanceTrigger);
  }

  function start() {
    scan(document.documentElement);
    window.addEventListener("keydown", onPageKey, true);
    // A trigger opens its palette. A link that is one (fallback()) still goes where it points when it is
    // pressed with Ctrl, Cmd, Shift or the middle button.
    document.addEventListener("click", function (e) {
      var b = e.target.closest ? e.target.closest(TRIGGER) : null;
      if (!b || e.defaultPrevented) return;
      if (b.tagName === "A" && (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0)) return;
      e.preventDefault();
      var st = find(b.getAttribute("data-gk-palette-open"), false) || find(null, true);
      if (st) open(st, "trigger");
    });
    // A palette or a trigger that arrives later — a fragment, AJAX navigation — is set up without a call.
    new MutationObserver(function (records) {
      records.forEach(function (r) {
        // The palette's own list is redrawn at every key: nothing in it is a palette or a trigger.
        if (r.target.nodeType === 1 && r.target.closest(".gk-palette-list")) return;
        r.addedNodes.forEach(scan);
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
