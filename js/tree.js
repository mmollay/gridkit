/* GridKit tree v1.116.0 – Vanilla, zero dependencies. Its own file for the reason js/sortierbar.js is
 * one: the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites, so a block
 * that lives in gridkit.js would never reach it. Load it after gridkit.js (skeleton.php does). Nothing
 * here needs gridkit.js: the words come from window.GK_LANG, and GK.toast, GK.initContent and the
 * title tooltip are used when they are there.
 *
 *   <div class="gk-tree" data-gk-tree="pages" data-gk-tree-select="multi" …>   ← GridKit\Tree writes it
 *     <ul class="gk-tree-list">
 *       <li class="gk-tree-node" data-gk-tree-id="7"><div class="gk-tree-row">…</div>
 *           <ul class="gk-tree-group">…</ul></li>
 *
 * The server's list works on its own — links, checkboxes, radio buttons, every node in view. This
 * script makes it a WAI-ARIA tree view (role tree / treeitem / group, levels, positions, a roving
 * tabindex, the keys of the pattern, type-ahead) and adds what needs a script: collapsing, remembered
 * per tree id; the tri-state of a parent; the filter; children fetched on first expand; moving by drag
 * and drop or by keyboard, saved to a URL with the old place given back when the save fails.
 *
 * Events, on the .gk-tree, bubbling — for what the person does, not for calls of GK.tree:
 *   gk:treeselect  { tree, id, selected, ids }
 *   gk:treetoggle  { tree, id, expanded }
 *   gk:treemove    { tree, id, parent, index, oldParent, oldIndex }   cancelable, before the move
 *   gk:treemoved   { …the same, ok, reverted }                       after it is saved, or given back
 */
(function () {
  "use strict";

  var lang = window.GK_LANG || {};
  // Every {name} is replaced, not the first one only (same as sortierbar.js).
  function t(key, params) {
    var text = lang[key] || key;
    for (var name in params || {}) text = text.split("{" + name + "}").join(params[name]);
    return text;
  }

  var TREE = "[data-gk-tree]";
  var THRESHOLD = 4;      // px the pointer travels before a press on the handle becomes a drag
  var EDGE = 56;          // px from the window edge where a drag scrolls the page
  var HOVER_OPEN = 700;   // ms a dragged node rests on a closed parent before it opens
  var TYPE_MS = 600;      // type-ahead: letters typed within this belong together
  var FILTER_MS = 120;    // the filter waits this long after the last key
  var SAY_MS = 3000;      // a message younger than this is followed, not replaced (see announce)
  var trees = new WeakMap();
  var counter = 0;

  /* ── Reading the tree ────────────────────────────────────────────────── */

  function isNode(el) {
    return !!el && el.nodeType === 1 && el.classList.contains("gk-tree-node");
  }
  function kids(container) {
    return container ? Array.prototype.filter.call(container.children, isNode) : [];
  }
  function child(el, cls) {
    for (var i = 0; el && i < el.children.length; i++) if (el.children[i].classList.contains(cls)) return el.children[i];
    return null;
  }
  function rowOf(li) { return child(li, "gk-tree-row"); }
  function groupOf(li) { return child(li, "gk-tree-group"); }
  function childNodes(li) { return kids(groupOf(li)); }
  function idOf(li) { return String(li.getAttribute("data-gk-tree-id")); }
  function labelOf(li) { var r = rowOf(li); return r ? r.querySelector(".gk-tree-label") : null; }
  // The label as it was written: the filter wraps a part of it in <mark>, the words stay the same.
  function labelText(li) {
    if (li._gkText == null) { var l = labelOf(li); li._gkText = l ? l.textContent.replace(/\s+/g, " ").trim() : idOf(li); }
    return li._gkText;
  }
  function isLazy(li) { return li.hasAttribute("data-gk-tree-lazy"); }
  function isParent(li) { return isLazy(li) || childNodes(li).length > 0; }
  function isOpen(li) { return li.classList.contains("gk-tree-open"); }
  function isDisabled(li) { return li.hasAttribute("data-gk-tree-disabled"); }
  function checkOf(li) { var r = rowOf(li); return r ? r.querySelector("input.gk-tree-check") : null; }
  function radioOf(li) { var r = rowOf(li); return r ? r.querySelector("input.gk-tree-radio") : null; }
  function allNodes(st) { return Array.prototype.slice.call(st.list.querySelectorAll(".gk-tree-node")); }
  function find(st, id) {
    id = String(id);
    var list = allNodes(st);
    for (var i = 0; i < list.length; i++) if (idOf(list[i]) === id) return list[i];
    return null;
  }
  function parentNode(st, li) {
    var ul = li.parentElement;
    return !ul || ul === st.list ? null : ul.parentElement;
  }
  function closestNode(st, el) {
    var li = el && el.closest ? el.closest(".gk-tree-node") : null;
    return li && st.list.contains(li) ? li : null;
  }
  function levelOf(st, li) {
    var n = 1;
    for (var p = parentNode(st, li); p; p = parentNode(st, p)) n++;
    return n;
  }
  function rtl(st) { return getComputedStyle(st.list).direction === "rtl"; }

  // The nodes a person can see, top to bottom: not filtered out, not inside a closed parent.
  function visibleNodes(st) {
    var out = [];
    (function walk(container) {
      kids(container).forEach(function (li) {
        if (li.hidden) return;
        out.push(li);
        if (isOpen(li)) walk(groupOf(li));
      });
    })(st.list);
    return out;
  }

  // Lower case, accents folded (ü → u), with the place in the original of every folded letter — the
  // filter finds "uber" in "Über uns" and still marks the right letters.
  function fold(s) {
    var text = "";
    var map = [];
    for (var i = 0; i < s.length; i++) {
      var f = s[i].normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
      for (var j = 0; j < f.length; j++) { text += f[j]; map.push(i); }
    }
    return { text: text, map: map };
  }

  /* ── Telling the person and the page ─────────────────────────────────── */

  // Emptied first and filled a moment later, so the same words twice are heard twice. With `append`
  // the words follow a message that is still young: "Team put down … Saved." is one message.
  function announce(st, text, append) {
    var said = append && Date.now() - st.saidAt < SAY_MS ? st.said + " " : "";
    st.said = said + text;
    st.saidAt = Date.now();
    st.live.textContent = "";
    clearTimeout(st.sayTimer);
    st.sayTimer = setTimeout(function () { st.live.textContent = st.said; }, 20);
  }

  function fire(st, name, detail, cancelable) {
    detail.tree = st.id;
    return st.root.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable: !!cancelable, detail: detail }));
  }

  function where(st, li) {
    var p = parentNode(st, li);
    var sib = kids(li.parentElement).filter(function (k) { return !k.hidden; });
    var params = { level: levelOf(st, li), n: sib.indexOf(li) + 1, m: sib.length };
    if (!p) return t("tree_where_top", params);
    params.parent = labelText(p);
    return t("tree_where", params);
  }

  // A failed save: the library's toast where gridkit.js is loaded (fixed on the screen, wherever
  // the tree was scrolled to, and read out), otherwise the alert line above the list.
  function showError(st, text) {
    if (window.GK && GK.toast && typeof GK.toast.error === "function") {
      GK.toast.error(text, 6000);
      return;
    }
    st.fail.textContent = text;
    st.fail.hidden = false;
  }
  function clearError(st) {
    st.fail.hidden = true;
    st.fail.textContent = "";
  }

  /* ── ARIA, focus and the parts of a row ──────────────────────────────── */

  // Once per node: the name of the treeitem (its label and the small things after it, never its
  // buttons or its children), and every control in the row out of the tab order — the row's own
  // controls come back into it while the row is the active one (setActive).
  function prepare(st, li) {
    if (li._gkPrepared) return;
    li._gkPrepared = true;
    var row = rowOf(li);
    if (!row) return;
    li.setAttribute("role", "treeitem");
    li.setAttribute("tabindex", "-1");
    var names = [];
    [".gk-tree-label", ".gk-tree-note", ".gk-tree-badge", ".gk-tree-count"].forEach(function (sel) {
      var el = row.querySelector(sel);
      if (!el) return;
      if (!el.id) el.id = "gk-tree-x" + (++counter);
      names.push(el.id);
    });
    if (names.length) li.setAttribute("aria-labelledby", names.join(" "));
    row.querySelectorAll("input.gk-tree-check, input.gk-tree-radio").forEach(function (input) {
      input.tabIndex = -1;
      input.setAttribute("aria-hidden", "true");
    });
    var label = labelOf(li);
    if (label && label.tagName === "A") label.tabIndex = -1;
    row.querySelectorAll(".gk-tree-actions a, .gk-tree-actions button").forEach(function (c) { c.tabIndex = -1; });
    if (st.movable && !isDisabled(li) && !li.hasAttribute("data-gk-tree-nodrag") && !child(row, "gk-tree-handle")) {
      var handle = document.createElement("button");
      handle.type = "button";
      handle.className = "gk-tree-handle";
      handle.tabIndex = -1;
      handle.setAttribute("aria-label", t("tree_handle", { label: labelText(li) }));
      handle.setAttribute("aria-describedby", st.helpId);
      handle.setAttribute("aria-pressed", "false");
      row.appendChild(handle);
    }
  }

  // Levels, positions, open or closed, which groups are groups: everything that follows from where a
  // node stands. Run after every change of shape — a move, a load, a filter.
  function sync(st) {
    (function walk(container, level) {
      var list = kids(container).filter(function (li) { return !li.hidden; });
      list.forEach(function (li, i) {
        var row = rowOf(li);
        var g = groupOf(li);
        prepare(st, li);
        li.setAttribute("aria-level", String(level));
        li.setAttribute("aria-setsize", String(list.length));
        li.setAttribute("aria-posinset", String(i + 1));
        if (row) row.setAttribute("data-gk-tree-level", String(level));
        if (isParent(li)) {
          li.setAttribute("aria-expanded", isOpen(li) ? "true" : "false");
        } else {
          li.removeAttribute("aria-expanded");
          li.classList.remove("gk-tree-open");
        }
        if (isDisabled(li)) li.setAttribute("aria-disabled", "true");
        if (g) {
          var has = kids(g).length > 0;
          if (has) g.setAttribute("role", "group");
          else g.removeAttribute("role");
          g.hidden = !has || !isOpen(li);
          walk(g, level + 1);
        }
      });
    })(st.list, 1);
    if (st.mode === "multi") ariaChecks(st);
    if (!st.active || !st.list.contains(st.active) || !isShown(st, st.active)) {
      var first = visibleNodes(st)[0];
      if (first) setActive(st, first, false);
    }
  }

  function isShown(st, li) {
    if (li.hidden) return false;
    for (var p = parentNode(st, li); p; p = parentNode(st, p)) if (p.hidden || !isOpen(p)) return false;
    return true;
  }

  function rowControls(li, index) {
    var row = rowOf(li);
    if (!row) return;
    row.querySelectorAll(".gk-tree-actions a, .gk-tree-actions button, .gk-tree-handle, .gk-tree-retry").forEach(function (c) {
      c.tabIndex = index;
    });
  }

  // The roving tabindex: one treeitem is in the tab order, and so are the buttons of its row.
  function setActive(st, li, focus) {
    if (st.active && st.active !== li && st.list.contains(st.active)) {
      st.active.setAttribute("tabindex", "-1");
      rowControls(st.active, -1);
    }
    st.active = li;
    li.setAttribute("tabindex", "0");
    rowControls(li, 0);
    if (focus) li.focus();
  }

  /* ── Opening and closing ─────────────────────────────────────────────── */

  // The open state as the eye and a screen reader get it, without anything else: no event, no
  // memory, no load. The filter opens and closes this way.
  function showOpen(st, li, open) {
    li.classList.toggle("gk-tree-open", open);
    if (isParent(li)) li.setAttribute("aria-expanded", open ? "true" : "false");
    var g = groupOf(li);
    if (g) g.hidden = !open || kids(g).length === 0;
    if (!open && st.active && st.active !== li && li.contains(st.active)) {
      setActive(st, li, st.list.contains(document.activeElement) && li.contains(document.activeElement));
    }
  }

  function setOpen(st, li, open, user) {
    if (!isParent(li)) return Promise.resolve(false);
    var changed = isOpen(li) !== open;
    if (changed) showOpen(st, li, open);
    if (changed) save(st);
    if (changed && user) fire(st, "gk:treetoggle", { id: idOf(li), expanded: open });
    if (open && isLazy(li)) return load(st, li);
    return Promise.resolve(changed);
  }

  function openPath(st, li) {
    for (var p = parentNode(st, li); p; p = parentNode(st, p)) if (!isOpen(p)) showOpen(st, p, true);
  }

  function setAll(st, open, user) {
    var waits = [];
    allNodes(st).forEach(function (li) {
      // "Expand all" opens what is loaded: a lazy branch may hold a thousand posts.
      if (isParent(li) && !(open && isLazy(li))) waits.push(setOpen(st, li, open, user));
    });
    sync(st);
    return Promise.all(waits);
  }

  /* ── Remembering what was open ───────────────────────────────────────── */

  function storeKey(st) { return "gk-tree:" + st.id; }

  function save(st) {
    if (!st.remember || st.filtering || st.restoring) return;
    var open = [];
    var closed = [];
    allNodes(st).forEach(function (li) {
      if (isParent(li)) (isOpen(li) ? open : closed).push(idOf(li));
    });
    try { localStorage.setItem(storeKey(st), JSON.stringify({ open: open, closed: closed })); } catch (e) { /* no storage: nothing is remembered */ }
  }

  // What the browser remembers wins over the server's 'expanded' for the nodes it names; a node it
  // has never seen keeps the server's state.
  function restoreOpen(st) {
    var saved = null;
    try { saved = JSON.parse(localStorage.getItem(storeKey(st)) || "null"); } catch (e) { saved = null; }
    if (!saved || typeof saved !== "object") return;
    var open = Array.isArray(saved.open) ? saved.open.map(String) : [];
    var closed = Array.isArray(saved.closed) ? saved.closed.map(String) : [];
    allNodes(st).forEach(function (li) {
      var id = idOf(li);
      if (open.indexOf(id) >= 0) li.classList.add("gk-tree-open");
      else if (closed.indexOf(id) >= 0) li.classList.remove("gk-tree-open");
    });
  }

  /* ── Children on demand ──────────────────────────────────────────────── */

  function ensureGroup(st, li) {
    var g = groupOf(li);
    if (!g) {
      g = document.createElement("ul");
      g.className = "gk-tree-group";
      g.hidden = true;
      li.appendChild(g);
    }
    return g;
  }

  // GET lazyUrl?parent=<id>, answered with the <li> of the children (Tree::renderBranch()). One
  // request per node at a time; a second expand waits for the first.
  function load(st, li) {
    if (li._gkLoading) return li._gkLoading;
    if (!st.lazyUrl) return Promise.resolve(false);
    clearLoadError(li);
    li.setAttribute("aria-busy", "true");
    var url = st.lazyUrl + (st.lazyUrl.indexOf("?") >= 0 ? "&" : "?") + "parent=" + encodeURIComponent(idOf(li));
    var p = fetch(url, { credentials: "same-origin", headers: { Accept: "text/html", "X-Requested-With": "XMLHttpRequest" } })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.text();
      })
      .then(function (html) { insertChildren(st, li, html); return true; }, function () { loadFailed(st, li); return false; })
      .then(function (ok) {
        li.removeAttribute("aria-busy");
        li._gkLoading = null;
        return ok;
      });
    li._gkLoading = p;
    return p;
  }

  function insertChildren(st, li, html) {
    var tpl = document.createElement("template");
    tpl.innerHTML = html;
    var incoming = kids(tpl.content);
    var inner = tpl.content.querySelector(".gk-tree-list, .gk-tree-group");
    if (!incoming.length && inner) incoming = kids(inner);
    var known = {};
    allNodes(st).forEach(function (n) { known[idOf(n)] = true; });
    var g = ensureGroup(st, li);
    // A node the tree already has is not taken twice — one that was moved in here before the branch
    // was loaded is in the answer as well.
    incoming.forEach(function (n) { if (!known[idOf(n)]) g.appendChild(n); });
    li.removeAttribute("data-gk-tree-lazy");
    kids(g).forEach(function (n) { n.querySelectorAll(".gk-tree-node").forEach(function (d) { prepare(st, d); }); prepare(st, n); });
    if (st.mode === "multi") {
      var box = checkOf(li);
      if (box && box.checked) cascade(li, true);
      takePending(st);
      deriveAll(st);
    } else if (st.mode === "single") {
      takePending(st);
    }
    // The new branch is known to the server: a move that fails later puts its nodes back here.
    st.confirmed = st.confirmed.filter(function (pair) { return pair[0] !== g; });
    st.confirmed.push([g, kids(g)]);
    if (!kids(g).length) li.classList.remove("gk-tree-open");
    sync(st);
    if (st.filtering) applyFilter(st);
    if (window.GK && typeof GK.initContent === "function") GK.initContent(g);
  }

  function clearLoadError(li) {
    var row = rowOf(li);
    var old = row && child(row, "gk-tree-loaderror");
    if (old) old.remove();
  }

  // The node closes again and says so in its row, with a button that tries once more; opening the
  // node again does the same.
  function loadFailed(st, li) {
    showOpen(st, li, false);
    var row = rowOf(li);
    clearLoadError(li);
    var box = document.createElement("span");
    box.className = "gk-tree-loaderror";
    var words = document.createElement("span");
    words.textContent = t("tree_load_error");
    var retry = document.createElement("button");
    retry.type = "button";
    retry.className = "gk-tree-retry";
    retry.textContent = t("tree_retry");
    retry.setAttribute("aria-describedby", labelOf(li) ? labelOf(li).id : "");
    retry.tabIndex = st.active === li ? 0 : -1;
    box.appendChild(words);
    box.appendChild(retry);
    var before = child(row, "gk-tree-actions") || child(row, "gk-tree-handle");
    row.insertBefore(box, before);
    announce(st, t("tree_load_error_named", { label: labelText(li) }));
  }

  /* ── Selecting ───────────────────────────────────────────────────────── */

  // A ticked parent ticks its branch — but not a disabled node, nor what is under it.
  function cascade(li, on) {
    childNodes(li).forEach(function (k) {
      if (isDisabled(k)) return;
      var box = checkOf(k);
      if (box) { box.checked = on; box.indeterminate = false; }
      cascade(k, on);
    });
  }

  // A parent is ticked when all its enabled children are, half-ticked when some are.
  function derive(li) {
    var box = checkOf(li);
    if (!box || isDisabled(li)) return;
    var enabled = childNodes(li).filter(function (k) { return !isDisabled(k) && checkOf(k); });
    if (!enabled.length) return;
    var all = enabled.every(function (k) { return checkOf(k).checked; });
    var some = enabled.some(function (k) { return checkOf(k).checked || checkOf(k).indeterminate; });
    box.checked = all;
    box.indeterminate = !all && some;
  }

  function deriveAll(st) {
    (function walk(container) {
      kids(container).forEach(function (li) {
        walk(groupOf(li));
        derive(li);
      });
    })(st.list);
  }

  function ariaChecks(st) {
    allNodes(st).forEach(function (li) {
      var box = checkOf(li);
      if (!box) return;
      li.setAttribute("aria-checked", box.indeterminate ? "mixed" : box.checked ? "true" : "false");
    });
  }

  // A selected id the server could not show (its branch was not loaded) travels as a hidden field;
  // once its node is here, the node takes over.
  function takePending(st) {
    st.root.querySelectorAll("input[data-gk-tree-pending]").forEach(function (input) {
      var li = find(st, input.value);
      if (!li) return;
      input.remove();
      if (st.mode === "multi") {
        var box = checkOf(li);
        if (box && !box.disabled) { box.checked = true; cascade(li, true); }
      } else {
        selectOne(st, li);
      }
    });
  }

  function check(st, li, on, user) {
    var box = checkOf(li);
    if (!box || box.disabled) return;
    box.checked = on;
    box.indeterminate = false;
    cascade(li, on);
    for (var p = parentNode(st, li); p; p = parentNode(st, p)) derive(p);
    ariaChecks(st);
    if (user) fire(st, "gk:treeselect", { id: idOf(li), selected: on, ids: selectedIds(st) });
  }

  function selectOne(st, li) {
    var prev = st.list.querySelector('[aria-selected="true"]');
    if (prev && prev !== li) prev.removeAttribute("aria-selected");
    st.root.querySelectorAll("input[data-gk-tree-pending]").forEach(function (input) { input.remove(); });
    if (li) {
      li.setAttribute("aria-selected", "true");
      var radio = radioOf(li);
      if (radio) radio.checked = true;
    } else if (prev) {
      var r = radioOf(prev);
      if (r) r.checked = false;
    }
    return prev !== li;
  }

  function select(st, li, user) {
    if (isDisabled(li)) return;
    if (selectOne(st, li) && user) fire(st, "gk:treeselect", { id: idOf(li), selected: true, ids: selectedIds(st) });
  }

  function selectedIds(st) {
    var ids = [];
    if (st.mode === "multi") {
      allNodes(st).forEach(function (li) { var box = checkOf(li); if (box && box.checked) ids.push(idOf(li)); });
    } else if (st.mode === "single") {
      var li = st.list.querySelector('[aria-selected="true"]');
      if (li) ids.push(idOf(li));
    }
    st.root.querySelectorAll("input[data-gk-tree-pending]").forEach(function (input) { ids.push(input.value); });
    return ids;
  }

  // What a click on the row, Enter or Space does: follow the link (Enter), select or tick the node, or
  // — in a tree without selection — open or close it.
  function activate(st, li, how) {
    var label = labelOf(li);
    if (how === "enter" && label && label.tagName === "A") { label.click(); return; }
    if (st.mode === "single" && !isDisabled(li)) select(st, li, true);
    else if (st.mode === "multi" && !isDisabled(li)) { var box = checkOf(li); if (box) check(st, li, !box.checked, true); }
    else if (isParent(li)) setOpen(st, li, !isOpen(li), true);
  }

  /* ── The filter ──────────────────────────────────────────────────────── */

  function unmark(li) {
    var label = labelOf(li);
    if (label && label.querySelector("mark")) label.textContent = labelText(li);
  }

  // Wraps every place of the query in <mark>, built from text nodes: the label is never parsed as HTML.
  function mark(li, q) {
    var text = labelText(li);
    var f = fold(text);
    var at = f.text.indexOf(q);
    var label = labelOf(li);
    if (at < 0 || !label) { unmark(li); return false; }
    label.textContent = "";
    var from = 0;
    while (at >= 0) {
      var start = f.map[at];
      var end = f.map[at + q.length - 1] + 1;
      if (start > from) label.appendChild(document.createTextNode(text.slice(from, start)));
      var m = document.createElement("mark");
      m.className = "gk-tree-hit";
      m.textContent = text.slice(start, end);
      label.appendChild(m);
      from = end;
      at = f.text.indexOf(q, at + q.length);
    }
    if (from < text.length) label.appendChild(document.createTextNode(text.slice(from)));
    return true;
  }

  // A node that matches is shown with its ancestors (opened) and its own branch (as it was); every
  // other node is hidden. The open state from before comes back when the field is emptied.
  function applyFilter(st) {
    var q = fold(st.search.value.trim()).text;
    if (!q) { clearFilter(st); return; }
    if (!st.filtering) {
      st.filtering = true;
      st.openBefore = allNodes(st).filter(isOpen).map(idOf);
      st.root.setAttribute("data-gk-tree-filtering", "");
    }
    var hits = 0;
    (function walk(container, inherited) {
      var below = false;
      kids(container).forEach(function (li) {
        var own = mark(li, q);
        if (own) hits++;
        var sub = walk(groupOf(li), inherited || own);
        li.hidden = !(inherited || own || sub);
        if (isParent(li)) showOpen(st, li, sub);
        if (own || sub) below = true;
      });
      return below;
    })(st.list, false);
    st.empty.hidden = hits > 0;
    sync(st);
    clearTimeout(st.countTimer);
    st.countTimer = setTimeout(function () {
      announce(st, hits === 0 ? t("tree_no_matches") : hits === 1 ? t("tree_match_one") : t("tree_matches", { n: hits }));
    }, 400);
  }

  function clearFilter(st) {
    if (!st.filtering) return;
    st.filtering = false;
    st.root.removeAttribute("data-gk-tree-filtering");
    var before = st.openBefore || [];
    allNodes(st).forEach(function (li) {
      li.hidden = false;
      unmark(li);
      if (isParent(li)) showOpen(st, li, before.indexOf(idOf(li)) >= 0);
    });
    st.empty.hidden = true;
    sync(st);
  }

  /* ── Moving ──────────────────────────────────────────────────────────── */

  function placeOf(st, li) {
    return { parent: parentNode(st, li), index: kids(li.parentElement).indexOf(li) };
  }

  // Why a node may not go into `parent`, or "" when it may: never into itself or its own branch,
  // never into a disabled node.
  function refusal(st, li, parent) {
    if (parent && (parent === li || li.contains(parent))) return "tree_into_itself";
    if (parent && isDisabled(parent)) return "tree_not_target";
    return "";
  }

  function refuse(st, li, parent, why) {
    announce(st, t(why, { label: why === "tree_not_target" ? labelText(parent) : labelText(li) }));
  }

  // The structure, as [container, its nodes] pairs: the last state the server agreed to.
  function snapshot(st) {
    var pairs = [[st.list, kids(st.list)]];
    st.list.querySelectorAll(".gk-tree-group").forEach(function (g) { pairs.push([g, kids(g)]); });
    return pairs;
  }

  function restore(st, pairs) {
    st.moving = true;
    var focused = st.list.contains(document.activeElement) ? document.activeElement : null;
    pairs.forEach(function (pair) {
      pair[1].forEach(function (li) { pair[0].appendChild(li); });
    });
    if (focused && document.activeElement !== focused) focused.focus({ preventScroll: true });
    st.moving = false;
    if (st.mode === "multi") deriveAll(st);
    sync(st);
  }

  // Puts `li` at `index` among the children of `parent` (null: the top level), counted without `li`
  // itself — the place it will have. Moving a node takes the focus off it; it is given back.
  function place(st, li, parent, index) {
    st.moving = true;
    var focused = li.contains(document.activeElement) ? document.activeElement : null;
    var container = parent ? ensureGroup(st, parent) : st.list;
    var others = kids(container).filter(function (k) { return k !== li; });
    container.insertBefore(li, others[index] || null);
    if (parent && !isOpen(parent)) { showOpen(st, parent, true); save(st); }
    if (focused && document.activeElement !== focused) focused.focus({ preventScroll: true });
    st.moving = false;
    if (st.mode === "multi") deriveAll(st);
    sync(st);
  }

  function moveDetail(st, li, from, to) {
    return {
      id: idOf(li),
      parent: to.parent ? idOf(to.parent) : null,
      index: to.index,
      oldParent: from.parent ? idOf(from.parent) : null,
      oldIndex: from.index
    };
  }

  // A move the person made (a drop, or a keyboard move put down): the page may still say no.
  function move(st, li, parent, index) {
    var why = refusal(st, li, parent);
    if (why) { refuse(st, li, parent, why); return Promise.resolve(false); }
    var ready = parent && isLazy(parent) ? load(st, parent) : Promise.resolve(true);
    return ready.then(function (ok) {
      if (!ok && parent && isLazy(parent)) return false;
      var from = placeOf(st, li);
      if (index == null) index = childNodes(parent).filter(function (k) { return k !== li; }).length;
      if (from.parent === parent && from.index === index) return false;
      var detail = moveDetail(st, li, from, { parent: parent, index: index });
      if (!fire(st, "gk:treemove", detail, true)) return false;
      place(st, li, parent, index);
      announce(st, t("tree_moved", { label: labelText(li), where: where(st, li) }));
      commit(st, detail);
      return true;
    });
  }

  function commit(st, detail) {
    clearError(st);
    if (!st.moveUrl) {
      st.confirmed = snapshot(st);
      fire(st, "gk:treemoved", Object.assign({}, detail, { ok: true, reverted: false }));
      return;
    }
    st.queue.push({ detail: detail, after: snapshot(st) });
    pump(st);
  }

  // The answer counts as "saved" when the status is 2xx and a JSON body, if there is one, does not
  // say {"ok": false}. Its "message", if any, is what the person reads when it is a refusal.
  function verdict(res) {
    return res.text().then(function (text) {
      var body = null;
      try { body = JSON.parse(text); } catch (e) { body = null; }
      var refused = !!body && (body.ok === false || body.success === false);
      return { ok: res.ok && !refused, message: body && typeof body.message === "string" ? body.message : "" };
    });
  }

  // One move on its way at a time, in the order they were made. Each one names a place that only
  // exists after the one before it; when one fails, it and every later one go back to what the server
  // agreed to last.
  function pump(st) {
    if (st.busy || !st.queue.length) return;
    var m = st.queue.shift();
    var headers = { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    for (var h in st.headers) headers[h] = st.headers[h];
    st.busy = true;
    fetch(st.moveUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: headers,
      body: JSON.stringify({ id: m.detail.id, parent: m.detail.parent, index: m.detail.index })
    }).then(verdict, function () { return { ok: false, message: "" }; }).then(function (r) {
      st.busy = false;
      if (r.ok) {
        st.confirmed = m.after;
        announce(st, t("tree_saved"), true);
        fire(st, "gk:treemoved", Object.assign({}, m.detail, { ok: true, reverted: false }));
        pump(st);
        return;
      }
      st.queue = [];
      if (st.lift) release(st);
      restore(st, st.confirmed);
      var li = find(st, m.detail.id);
      if (li) { openPath(st, li); sync(st); li.scrollIntoView({ block: "nearest" }); }
      showError(st, r.message || t("tree_save_error"));
      announce(st, r.message || t("tree_save_error"));
      fire(st, "gk:treemoved", Object.assign({}, m.detail, { ok: false, reverted: true }));
    });
  }

  /* ── Moving with the keyboard: the handle ────────────────────────────── */

  function lift(st, li, handle) {
    if (st.filtering) { announce(st, t("tree_filter_first")); return; }
    st.lift = { li: li, handle: handle, from: placeOf(st, li) };
    li.classList.add("gk-tree-lifted");
    handle.setAttribute("aria-pressed", "true");
    announce(st, t("tree_lifted", { label: labelText(li), where: where(st, li) }));
  }

  function release(st) {
    var k = st.lift;
    st.lift = null;
    k.li.classList.remove("gk-tree-lifted");
    k.handle.setAttribute("aria-pressed", "false");
    return k;
  }

  function putDown(st) {
    var k = release(st);
    var now = placeOf(st, k.li);
    if (now.parent === k.from.parent && now.index === k.from.index) {
      announce(st, t("tree_dropped", { label: labelText(k.li), where: where(st, k.li) }));
      return;
    }
    var detail = moveDetail(st, k.li, k.from, now);
    if (!fire(st, "gk:treemove", detail, true)) {
      place(st, k.li, k.from.parent, k.from.index);
      announce(st, t("tree_cancelled", { label: labelText(k.li) }));
      return;
    }
    announce(st, t("tree_dropped", { label: labelText(k.li), where: where(st, k.li) }));
    commit(st, detail);
  }

  function giveBack(st) {
    var k = release(st);
    place(st, k.li, k.from.parent, k.from.index);
    announce(st, t("tree_cancelled", { label: labelText(k.li) }));
  }

  // One step of a lifted node: Up and Down among its neighbours, Home and End to the ends, Right into
  // the neighbour above (as its last child), Left out of its parent (just after it).
  function step(st, key) {
    var li = st.lift.li;
    var at = placeOf(st, li);
    var sib = kids(li.parentElement);
    var i = at.index;
    var flip = rtl(st);
    var into = flip ? "ArrowLeft" : "ArrowRight";
    var out = flip ? "ArrowRight" : "ArrowLeft";
    var to = null;
    if (key === "ArrowUp" && i > 0) to = { parent: at.parent, index: i - 1 };
    else if (key === "ArrowDown" && i < sib.length - 1) to = { parent: at.parent, index: i + 1 };
    else if (key === "Home" && i > 0) to = { parent: at.parent, index: 0 };
    else if (key === "End" && i < sib.length - 1) to = { parent: at.parent, index: sib.length - 1 };
    else if (key === into && i > 0) {
      if (isDisabled(sib[i - 1])) { refuse(st, li, sib[i - 1], "tree_not_target"); return; }
      to = { parent: sib[i - 1], index: null };
    } else if (key === out && at.parent) {
      to = { parent: parentNode(st, at.parent), index: kids(at.parent.parentElement).indexOf(at.parent) + 1 };
    }
    if (!to) { announce(st, t("tree_edge")); return; }
    var ready = to.parent && isLazy(to.parent) ? load(st, to.parent) : Promise.resolve(true);
    ready.then(function () {
      if (!st.lift || st.lift.li !== li) return;
      if (to.parent && isLazy(to.parent)) return; // the branch did not load; its row says so
      var index = to.index == null ? childNodes(to.parent).filter(function (k) { return k !== li; }).length : to.index;
      place(st, li, to.parent, index);
      announce(st, where(st, li));
      st.lift.handle.scrollIntoView({ block: "nearest", inline: "nearest" });
    });
  }

  function onHandleKey(st, e) {
    var handle = e.target.closest(".gk-tree-handle");
    var li = closestNode(st, handle);
    if (!li || e.ctrlKey || e.altKey || e.metaKey || st.drag) return;
    if (e.key === " " || e.key === "Enter") {
      e.preventDefault();
      if (st.lift) putDown(st);
      else lift(st, li, handle);
    } else if (st.lift && e.key === "Escape") {
      e.preventDefault();
      e.stopPropagation();
      giveBack(st);
    } else if (st.lift && /^(Arrow(Up|Down|Left|Right)|Home|End)$/.test(e.key)) {
      e.preventDefault();
      step(st, e.key);
    }
  }

  /* ── Moving with the pointer ─────────────────────────────────────────── */

  // Where a drop at the pointer would put the node: before or after the row under it (its top and
  // bottom quarter), or into it (the middle). After an open parent is its first child.
  function dropAt(st, x, y) {
    var d = st.drag;
    var el = document.elementFromPoint(x, y);
    var row = el && el.closest ? el.closest(".gk-tree-row") : null;
    var target = row && st.list.contains(row) ? row.parentElement : null;
    if (!target) return null;
    if (target === d.li || d.li.contains(target)) return { target: target, zone: "inside", parent: target, index: null, why: "tree_into_itself" };
    var r = row.getBoundingClientRect();
    var zone = y - r.top < r.height * 0.25 ? "before" : r.bottom - y < r.height * 0.25 ? "after" : "inside";
    var visibleKids = childNodes(target).filter(function (k) { return !k.hidden && k !== d.li; });
    if (zone === "after" && isOpen(target) && visibleKids.length) zone = "first";
    var drop = { target: target, zone: zone };
    if (zone === "inside" || zone === "first") {
      drop.parent = target;
      drop.index = zone === "first" ? 0 : null;
    } else {
      drop.parent = parentNode(st, target);
      drop.index = kids(target.parentElement).filter(function (k) { return k !== d.li; }).indexOf(target) + (zone === "after" ? 1 : 0);
    }
    drop.why = refusal(st, d.li, drop.parent);
    return drop;
  }

  function showDrop(st, drop) {
    var d = st.drag;
    if (d.inside) d.inside.classList.remove("gk-tree-drop-inside");
    d.inside = null;
    d.line.hidden = true;
    d.ghost.classList.toggle("gk-tree-ghost-no", !!(drop && drop.why));
    if (!drop || drop.why) return;
    var row = rowOf(drop.target);
    if (drop.zone === "inside") {
      row.classList.add("gk-tree-drop-inside");
      d.inside = row;
      return;
    }
    // The line stands where the node would stand: at the start of the row's content (its toggle), one
    // step further in for a first child.
    var box = st.root.getBoundingClientRect();
    var r = row.getBoundingClientRect();
    var toggle = child(row, "gk-tree-toggle");
    var left = (toggle ? toggle.getBoundingClientRect().left : r.left) - box.left;
    if (drop.zone === "first") left += toggle ? toggle.getBoundingClientRect().width : 16;
    var top = (drop.zone === "before" ? r.top : r.bottom) - box.top;
    d.line.style.left = Math.max(0, left) + "px";
    d.line.style.width = Math.max(24, r.right - box.left - left) + "px";
    d.line.style.top = top + "px";
    d.line.hidden = false;
  }

  function retarget(st) {
    var d = st.drag;
    var drop = dropAt(st, d.x, d.y);
    d.drop = drop;
    showDrop(st, drop);
    // Resting on a closed parent opens it, so a node can be put deep into a tree in one drag.
    var rest = drop && drop.zone === "inside" && !drop.why && isParent(drop.target) && !isOpen(drop.target) ? drop.target : null;
    if (rest !== d.rest) {
      clearTimeout(d.restTimer);
      d.rest = rest;
      if (rest) d.restTimer = setTimeout(function () { if (st.drag === d && d.rest === rest) setOpen(st, rest, true, true); }, HOVER_OPEN);
    }
  }

  function beginDrag(st) {
    var d = st.drag;
    if (st.filtering) {
      announce(st, t("tree_filter_first"));
      endDrag(st, true);
      return;
    }
    d.started = true;
    d.li.classList.add("gk-tree-dragging");
    d.ghost = document.createElement("div");
    d.ghost.className = "gk-tree-ghost";
    d.ghost.setAttribute("aria-hidden", "true");
    d.ghost.textContent = labelText(d.li);
    document.body.appendChild(d.ghost);
    d.line = document.createElement("div");
    d.line.className = "gk-tree-drop-line";
    d.line.setAttribute("aria-hidden", "true");
    d.line.hidden = true;
    st.root.appendChild(d.line);
    document.addEventListener("selectstart", st.noSelect);
    document.addEventListener("keydown", st.dragKey, true);
    tick(st);
  }

  function follow(d) {
    if (!d.ghost) return;
    d.ghost.style.left = d.x + 14 + "px";
    d.ghost.style.top = d.y + 10 + "px";
  }

  // Held near the top or the bottom of the window, the page scrolls under the dragged node.
  function tick(st) {
    var d = st.drag;
    if (!d || !d.started) return;
    var stepBy = 0;
    if (d.y < EDGE) stepBy = -Math.ceil((EDGE - d.y) / 4);
    else if (d.y > window.innerHeight - EDGE) stepBy = Math.ceil((d.y - (window.innerHeight - EDGE)) / 4);
    if (stepBy) {
      var before = window.scrollY;
      window.scrollBy({ top: stepBy, behavior: "instant" });
      if (window.scrollY !== before) retarget(st);
    }
    d.frame = requestAnimationFrame(function () { tick(st); });
  }

  function endDrag(st, cancelled) {
    var d = st.drag;
    st.drag = null;
    if (!d) return;
    cancelAnimationFrame(d.frame);
    clearTimeout(d.restTimer);
    document.removeEventListener("selectstart", st.noSelect);
    document.removeEventListener("keydown", st.dragKey, true);
    if (d.handle.hasPointerCapture && d.handle.hasPointerCapture(d.pointer)) d.handle.releasePointerCapture(d.pointer);
    if (!d.started) return;
    d.li.classList.remove("gk-tree-dragging");
    if (d.inside) d.inside.classList.remove("gk-tree-drop-inside");
    if (d.ghost) d.ghost.remove();
    if (d.line) d.line.remove();
    setActive(st, d.li, true);
    if (cancelled || !d.drop) return;
    if (d.drop.why) { refuse(st, d.li, d.drop.parent, d.drop.why); return; }
    move(st, d.li, d.drop.parent, d.drop.index);
  }

  function onDown(st, e) {
    var handle = e.target.closest ? e.target.closest(".gk-tree-handle") : null;
    if (!handle || !st.list.contains(handle) || st.drag || st.lift) return;
    if (!e.isPrimary || (e.pointerType === "mouse" && e.button !== 0)) return;
    try { handle.setPointerCapture(e.pointerId); } catch (err) { return; }
    st.drag = { li: closestNode(st, handle), handle: handle, pointer: e.pointerId, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, started: false };
  }

  function onMove(st, e) {
    var d = st.drag;
    if (!d || e.pointerId !== d.pointer) return;
    d.x = e.clientX;
    d.y = e.clientY;
    if (!d.started) {
      if (Math.abs(d.x - d.x0) < THRESHOLD && Math.abs(d.y - d.y0) < THRESHOLD) return;
      beginDrag(st);
      if (!st.drag) return;
    }
    follow(d);
    retarget(st);
  }

  /* ── The keyboard on a treeitem ──────────────────────────────────────── */

  function typeahead(st, ch) {
    clearTimeout(st.typeTimer);
    st.typed += fold(ch).text;
    st.typeTimer = setTimeout(function () { st.typed = ""; }, TYPE_MS);
    var items = visibleNodes(st);
    if (!items.length) return;
    var q = st.typed;
    // The same letter again walks through the nodes that start with it.
    var same = q.split("").every(function (c) { return c === q[0]; });
    if (same) q = q[0];
    var start = items.indexOf(st.active);
    var skip = same || q.length === 1 ? 1 : 0;
    for (var k = 0; k < items.length; k++) {
      var li = items[(start + skip + k + items.length) % items.length];
      if (fold(labelText(li)).text.indexOf(q) === 0) { setActive(st, li, true); return; }
    }
  }

  function onKey(st, e) {
    var li = e.target;
    if (!isNode(li) || !st.list.contains(li) || e.altKey || e.ctrlKey || e.metaKey) return;
    var items;
    var i;
    var key = e.key;
    if (rtl(st) && (key === "ArrowLeft" || key === "ArrowRight")) key = key === "ArrowLeft" ? "ArrowRight" : "ArrowLeft";
    switch (key) {
      case "ArrowDown":
      case "ArrowUp":
        items = visibleNodes(st);
        i = items.indexOf(li) + (key === "ArrowDown" ? 1 : -1);
        if (items[i]) setActive(st, items[i], true);
        break;
      case "ArrowRight":
        if (isParent(li) && !isOpen(li)) setOpen(st, li, true, true);
        else if (isOpen(li)) {
          var first = childNodes(li).filter(function (k) { return !k.hidden; })[0];
          if (first) setActive(st, first, true);
        }
        break;
      case "ArrowLeft":
        if (isParent(li) && isOpen(li)) setOpen(st, li, false, true);
        else if (parentNode(st, li)) setActive(st, parentNode(st, li), true);
        break;
      case "Home":
        items = visibleNodes(st);
        if (items[0]) setActive(st, items[0], true);
        break;
      case "End":
        items = visibleNodes(st);
        if (items.length) setActive(st, items[items.length - 1], true);
        break;
      case "*":
        kids(li.parentElement).forEach(function (k) { if (isParent(k) && !k.hidden) setOpen(st, k, true, true); });
        sync(st);
        break;
      case "Enter":
        activate(st, li, "enter");
        break;
      case " ":
        activate(st, li, "space");
        break;
      default:
        if (key.length === 1 && /\S/.test(key)) typeahead(st, key);
        else return;
    }
    e.preventDefault();
  }

  /* ── The pointer on a row ────────────────────────────────────────────── */

  function onClick(st, e) {
    var li = closestNode(st, e.target);
    if (!li) return;
    var row = rowOf(li);
    if (!row || !row.contains(e.target)) return;
    if (e.target.closest(".gk-tree-toggle")) {
      setOpen(st, li, !isOpen(li), true);
      setActive(st, li, true);
      return;
    }
    if (e.target.closest(".gk-tree-retry")) { setOpen(st, li, true, true); return; }
    if (e.target.closest(".gk-tree-actions, .gk-tree-handle, input, a")) return;
    // The row itself, the name included: the <label> would tick its box natively and hand the focus to
    // a field that is hidden from a screen reader; the script does it and keeps the focus on the node.
    e.preventDefault();
    activate(st, li, "click");
    setActive(st, li, true);
  }

  function onChange(st, e) {
    var input = e.target;
    if (!input.matches || !input.matches("input.gk-tree-check, input.gk-tree-radio")) return;
    var li = closestNode(st, input);
    if (!li) return;
    if (st.mode === "multi") check(st, li, input.checked, true);
    else if (st.mode === "single") select(st, li, true);
    setActive(st, li, true);
  }

  // A truncated name shows in full under the pointer: the title is set only while the name is cut,
  // and GK.tip (gridkit.js) turns it into its own popup.
  function onOver(st, e) {
    var label = e.target.closest ? e.target.closest(".gk-tree-label") : null;
    var li = label && closestNode(st, label);
    if (!li) return;
    if (label.scrollWidth > label.clientWidth + 1) {
      if (label.hasAttribute("data-gk-tip")) label.setAttribute("data-gk-tip", labelText(li));
      else label.setAttribute("title", labelText(li));
    } else {
      label.removeAttribute("title");
      label.removeAttribute("data-gk-tip");
    }
  }

  /* ── Setting a tree up ───────────────────────────────────────────────── */

  function create(tag, className) {
    var el = document.createElement(tag);
    el.className = className;
    return el;
  }

  // The instructions for the handle, the live region, the line for a failed save (when there is no
  // toast) and "No matches" for the filter.
  function makeMessages(st) {
    st.helpId = "gk-tree-help-" + (++counter);
    var help = create("p", "gk-sr-only");
    help.id = st.helpId;
    help.textContent = t("tree_help");
    st.live = create("p", "gk-sr-only");
    st.live.setAttribute("aria-live", "polite");
    st.fail = create("p", "gk-tree-error");
    st.fail.setAttribute("role", "alert");
    st.fail.hidden = true;
    st.empty = create("p", "gk-tree-empty");
    st.empty.textContent = t("tree_no_matches");
    st.empty.hidden = true;
    st.list.before(st.fail);
    st.list.after(st.empty);
    st.root.appendChild(help);
    st.root.appendChild(st.live);
  }

  function headersOf(root) {
    try {
      var h = JSON.parse(root.getAttribute("data-gk-tree-headers") || "{}");
      return h && typeof h === "object" ? h : {};
    } catch (e) {
      return {};
    }
  }

  function setup(root) {
    if (trees.has(root)) return trees.get(root);
    var list = child(root, "gk-tree-list") || root.querySelector(".gk-tree-list");
    var st = {
      root: root,
      list: list,
      id: root.getAttribute("data-gk-tree") || root.id || "tree",
      mode: root.getAttribute("data-gk-tree-select") || "none",
      lazyUrl: root.getAttribute("data-gk-tree-lazy-url") || "",
      moveUrl: root.getAttribute("data-gk-tree-move-url") || "",
      headers: headersOf(root),
      movable: root.hasAttribute("data-gk-tree-movable") || root.hasAttribute("data-gk-tree-move-url"),
      remember: root.getAttribute("data-gk-tree-remember") !== "off",
      active: null, typed: "", typeTimer: 0, said: "", saidAt: 0, sayTimer: 0,
      queue: [], busy: false, drag: null, lift: null, filtering: false, moving: false
    };
    trees.set(root, st);
    if (!list) return st;
    makeMessages(st);
    list.setAttribute("role", "tree");
    list.setAttribute("aria-label", root.getAttribute("data-gk-tree-label") || st.id);
    if (st.mode === "multi") list.setAttribute("aria-multiselectable", "true");
    allNodes(st).forEach(function (li) { prepare(st, li); });

    if (st.remember) {
      st.restoring = true;
      restoreOpen(st);
      st.restoring = false;
    }
    var target = list.querySelector("[data-gk-tree-target]");
    if (target) openPath(st, target);
    if (st.mode === "multi") {
      allNodes(st).forEach(function (li) { var box = checkOf(li); if (box && box.checked) cascade(li, true); });
      deriveAll(st);
    } else if (st.mode === "single") {
      var chosen = list.querySelector("input.gk-tree-radio:checked");
      if (chosen) closestNode(st, chosen).setAttribute("aria-selected", "true");
    }
    sync(st);
    var first = target || list.querySelector('[aria-selected="true"]') || visibleNodes(st)[0];
    if (first && isShown(st, first)) setActive(st, first, false);
    st.confirmed = snapshot(st);

    st.noSelect = function (e) { e.preventDefault(); };
    st.dragKey = function (e) {
      if (e.key !== "Escape" || !st.drag) return;
      e.preventDefault();
      e.stopPropagation();
      endDrag(st, true);
    };
    list.addEventListener("keydown", function (e) {
      if (e.target.closest && e.target.closest(".gk-tree-handle")) onHandleKey(st, e);
      else onKey(st, e);
    });
    list.addEventListener("click", function (e) { onClick(st, e); });
    list.addEventListener("change", function (e) { onChange(st, e); });
    // A click on a box must not move the focus onto it (it is hidden from a screen reader).
    list.addEventListener("mousedown", function (e) {
      if (e.target.matches && e.target.matches("input.gk-tree-check, input.gk-tree-radio")) e.preventDefault();
    });
    list.addEventListener("focusin", function (e) {
      var li = closestNode(st, e.target);
      if (li && li !== st.active) setActive(st, li, false);
    });
    // A lifted node is put back when its handle loses the focus (Tab, a click elsewhere).
    list.addEventListener("focusout", function (e) {
      if (st.lift && !st.moving && e.target === st.lift.handle) giveBack(st);
    });
    list.addEventListener("mouseover", function (e) { onOver(st, e); });
    list.addEventListener("pointerdown", function (e) { onDown(st, e); });
    list.addEventListener("pointermove", function (e) { onMove(st, e); });
    list.addEventListener("pointerup", function (e) { if (st.drag && e.pointerId === st.drag.pointer) endDrag(st, false); });
    list.addEventListener("pointercancel", function (e) { if (st.drag && e.pointerId === st.drag.pointer) endDrag(st, true); });
    list.addEventListener("contextmenu", function (e) { if (e.target.closest && e.target.closest(".gk-tree-handle")) e.preventDefault(); });

    st.search = root.querySelector(".gk-tree-search");
    if (st.search) {
      st.search.addEventListener("input", function () {
        clearTimeout(st.filterTimer);
        st.filterTimer = setTimeout(function () { applyFilter(st); }, FILTER_MS);
      });
      st.search.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && st.search.value) {
          e.preventDefault();
          st.search.value = "";
          clearFilter(st);
        } else if (e.key === "ArrowDown") {
          var top = visibleNodes(st)[0];
          if (top) { e.preventDefault(); setActive(st, top, true); }
        }
      });
    }
    root.querySelectorAll("[data-gk-tree-all]").forEach(function (button) {
      button.addEventListener("click", function () {
        setAll(st, button.getAttribute("data-gk-tree-all") === "expand", true);
      });
    });
    root.setAttribute("data-gk-tree-ready", "");
    // Branches that start open (the server said so, or the browser remembers it) are fetched now.
    allNodes(st).forEach(function (li) { if (isLazy(li) && isOpen(li)) load(st, li); });
    return st;
  }

  /* ── The API ─────────────────────────────────────────────────────────── */

  // A tree by its element, its id (as given to new Tree()), an element id or a selector.
  function get(el) {
    var root = el;
    if (typeof el === "string") {
      root = null;
      document.querySelectorAll(TREE).forEach(function (r) { if (!root && r.getAttribute("data-gk-tree") === el) root = r; });
      if (!root) root = document.getElementById(el.replace(/^#/, ""));
      if (!root) { try { root = document.querySelector(el); } catch (e) { root = null; } }
    }
    if (root && root.closest) root = root.closest(TREE);
    return root ? setup(root) : null;
  }

  function each(st, ids) {
    return (Array.isArray(ids) ? ids : [ids]).map(function (id) { return find(st, id); }).filter(Boolean);
  }

  var api = {
    // Opens a node (and the path to it), or every loaded parent without an id. Resolves when a lazy
    // branch has arrived.
    expand: function (el, id) {
      var st = get(el);
      if (!st || !st.list) return Promise.resolve(false);
      if (id == null) return setAll(st, true, false);
      var li = find(st, id);
      if (!li) return Promise.resolve(false);
      openPath(st, li);
      var p = setOpen(st, li, true, false);
      sync(st);
      save(st);
      return p;
    },
    collapse: function (el, id) {
      var st = get(el);
      if (!st || !st.list) return Promise.resolve(false);
      if (id == null) return setAll(st, false, false);
      var li = find(st, id);
      if (!li) return Promise.resolve(false);
      var p = setOpen(st, li, false, false);
      sync(st);
      return p;
    },
    // Selects (or with on === false, unselects) one id or several; returns the selected ids.
    select: function (el, ids, on) {
      var st = get(el);
      if (!st || !st.list) return [];
      on = on !== false;
      each(st, ids).forEach(function (li) {
        if (st.mode === "multi") check(st, li, on, false);
        else if (st.mode === "single") {
          if (on) select(st, li, false);
          else if (li.getAttribute("aria-selected") === "true") selectOne(st, null);
        }
      });
      return selectedIds(st);
    },
    selected: function (el) {
      var st = get(el);
      return st && st.list ? selectedIds(st) : [];
    },
    // Fetches the children of a node again (lazyUrl) and opens it.
    reload: function (el, id) {
      var st = get(el);
      var li = st && st.list ? find(st, id) : null;
      if (!li || !st.lazyUrl) return Promise.resolve(false);
      var g = groupOf(li);
      if (g) kids(g).forEach(function (k) { k.remove(); });
      li.setAttribute("data-gk-tree-lazy", "");
      openPath(st, li);
      showOpen(st, li, true);
      sync(st);
      return load(st, li);
    }
  };
  window.GK = window.GK || {};
  window.GK.tree = api;

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(TREE)) setup(root);
    root.querySelectorAll(TREE).forEach(setup);
  }

  function start() {
    scan(document.documentElement);
    // A tree that arrives later — a fragment, a modal, AJAX navigation — is set up without a call.
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
