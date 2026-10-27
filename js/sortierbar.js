/* GridKit sortable v1.97.0 – Vanilla, zero dependencies. Its own file on purpose: the PawBot dashboard
 * keeps a changed copy of gridkit.js that no sync ever overwrites, so a block that lives in gridkit.js
 * would never reach it. Load it after gridkit.js (skeleton.php does); nothing here needs gridkit.js
 * except the catalogue in window.GK_LANG.
 *
 *   <div data-gk-sortierbar data-gk-sortierbar-url="/sites/order" data-gk-sortierbar-csrf="…">
 *     <article data-gk-sort-id="12" data-gk-sort-titel="survivaltraining.at">…</article>
 *     <article data-gk-sort-id="7">…<h3>ssi.at</h3>…</article>
 *   </div>
 *
 * The page writes the container and the ids. This script adds a handle to every entry, moves the
 * entries (mouse, pen and finger through pointer events; the keyboard on the handle), says what
 * happened in a live region and, with a URL, saves the new order. Without a URL it only sorts and
 * dispatches `gk:sortiert` – the page decides what the order is for.
 */
(function () {
  "use strict";

  var lang = window.GK_LANG || {};
  // Same shape as _t() in gridkit.js, but every {name} is replaced, not the first one only: the
  // announcements name the title twice in some languages.
  function t(key, params) {
    var text = lang[key] || key;
    for (var name in params || {}) text = text.split("{" + name + "}").join(params[name]);
    return text;
  }

  var CONTAINER = "[data-gk-sortierbar]";
  var ID = "data-gk-sort-id";
  var HANDLE = ".gk-sort-griff";
  var POS = ["left", "top", "width", "height"];   // the only style an entry gets, and only while it flies
  var THRESHOLD = 4;   // px the pointer travels before a press becomes a drag (a tap on the handle is not one)
  var EDGE = 64;       // px from the window edge where a drag scrolls the page
  var FLIP_MS = 200;   // = --gk-dur-2, the movement of an entry into its new place
  var OK_MS = 2500;    // how long "saved" stays on the screen
  var SAY_MS = 3000;   // "saved" follows the words of a drop that are younger than this
  var GLIDE = "gk-sort-glide";   // the name of the animations this file starts, so that it stops only its own
  var started = new WeakSet();
  var counter = 0;

  /* ── Reading the page ────────────────────────────────────────────────── */

  function entries(box) {
    return Array.prototype.filter.call(box.children, function (el) { return el.hasAttribute(ID); });
  }

  function ids(box) {
    return entries(box).map(function (el) { return String(el.getAttribute(ID)); });
  }

  function sameOrder(a, b) {
    return a.length === b.length && a.every(function (id, i) { return id === b[i]; });
  }

  // Text of a node as a reader hears it: icon ligatures and hidden parts do not count.
  function plainText(node) {
    var copy = node.cloneNode(true);
    copy.querySelectorAll(".material-icons, [aria-hidden=true]").forEach(function (n) { n.remove(); });
    return copy.textContent.replace(/\s+/g, " ").trim();
  }

  function titleOf(el, index) {
    var own = el.getAttribute("data-gk-sort-titel");
    if (own) return own;
    var heading = el.querySelector("h1, h2, h3, h4, h5, h6, [role=heading]");
    var text = heading ? plainText(heading) : (el.getAttribute("aria-label") || "");
    return text || String(index + 1);
  }

  function reduced() {
    return !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
  }

  /* ── Telling the person ──────────────────────────────────────────────── */

  // Emptied first, filled a moment later: the same words twice in a row (the same position at the end
  // of a row) are announced twice, not swallowed as "no change". With `append` the words follow the
  // ones just said, in one message: "put down at position 2 of 4. Order saved" – two messages a moment
  // apart would let the second wipe out the first for a screen reader.
  function announce(st, text, append) {
    var said = append && Date.now() - st.saidAt < SAY_MS ? st.said + " " : "";
    st.said = said + text;
    st.saidAt = Date.now();
    st.live.textContent = "";
    clearTimeout(st.sayTimer);
    st.sayTimer = setTimeout(function () { st.live.textContent = st.said; }, 20);
  }

  function position(st, el) {
    var list = entries(st.box);
    return { titel: titleOf(el, list.indexOf(el)), n: list.indexOf(el) + 1, m: list.length };
  }

  function mark(el, icon, text) {
    el.textContent = "";
    var glyph = document.createElement("span");
    glyph.className = "material-icons";
    glyph.setAttribute("aria-hidden", "true");
    glyph.textContent = icon;
    var words = document.createElement("span");
    words.textContent = text;
    el.appendChild(glyph);
    el.appendChild(words);
  }

  function showSaved(st) {
    mark(st.ok, "check", t("sortierbar_gespeichert"));
    st.ok.hidden = false;
    clearTimeout(st.okTimer);
    st.okTimer = setTimeout(function () { st.ok.hidden = true; }, OK_MS);
    announce(st, t("sortierbar_gespeichert"), true);
  }

  function showFailure(st) {
    mark(st.fail, "error", t("sortierbar_fehler"));
    st.fail.hidden = false;
  }

  function clearFailure(st) {
    st.fail.hidden = true;
    st.fail.textContent = "";
  }

  /* ── Moving entries ──────────────────────────────────────────────────── */

  function snapshot(list) {
    return list.map(function (el) { return el.getBoundingClientRect(); });
  }

  // Stops the glides of an earlier move, so that the next measure is the place in the layout and not
  // a point on the way there.
  function settle(list) {
    list.forEach(function (el) {
      if (el.getAnimations) el.getAnimations().forEach(function (a) { if (a.id === GLIDE) a.cancel(); });
    });
  }

  // First, last, invert, play: whatever moved glides from where it was to where it is. Not at all
  // under prefers-reduced-motion – the entry is simply there.
  function play(list, before) {
    if (reduced()) return;
    settle(list);
    list.forEach(function (el, i) {
      var now = el.getBoundingClientRect();
      var dx = before[i].left - now.left;
      var dy = before[i].top - now.top;
      if ((dx || dy) && el.animate) {
        el.animate(
          [{ transform: "translate(" + dx + "px, " + dy + "px)" }, { transform: "none" }],
          { duration: FLIP_MS, easing: "cubic-bezier(0.2, 0, 0, 1)" }
        ).id = GLIDE;
      }
    });
  }

  // Puts `el` at index `to`, and moves the entries it passes – never `el` itself. Moving a node takes
  // the focus off it; the handle is inside `el`, and a person on the keyboard must stay on it.
  function reorder(box, el, to) {
    var list = entries(box);
    var from = list.indexOf(el);
    var k;
    if (from < 0 || from === to) return;
    if (to > from) {
      for (k = from + 1; k <= to; k++) el.before(list[k]);
    } else {
      for (k = from - 1; k >= to; k--) el.after(list[k]);
    }
  }

  function moveTo(box, el, to) {
    var list = entries(box);
    var before = snapshot(list);
    reorder(box, el, to);
    play(list, before);
  }

  // The order the server knew last, put back. Several entries may differ (two drops in a row, the
  // first one cancelled by the second), so this is not a single move; it may move the focused entry,
  // so the focus is given back.
  function restore(st, wanted) {
    var list = entries(st.box);
    var before = snapshot(list);
    var focused = st.box.contains(document.activeElement) ? document.activeElement : null;
    var byId = {};
    list.forEach(function (el) { byId[String(el.getAttribute(ID))] = el; });
    wanted.forEach(function (id, i) {
      var want = byId[id];
      var here = entries(st.box)[i];
      if (want && here && want !== here) here.before(want);
    });
    if (focused) focused.focus({ preventScroll: true });
    play(list, before);
  }

  /* ── The handles ─────────────────────────────────────────────────────── */

  function makeHandle(st) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "gk-sort-griff";
    button.setAttribute("aria-pressed", "false");
    button.setAttribute("aria-describedby", st.helpId);
    var icon = document.createElement("span");
    icon.className = "material-icons";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = "drag_indicator";
    button.appendChild(icon);
    return button;
  }

  // A handle only where there is something to reorder: with fewer than two entries there is none at
  // all (not a hidden one – nothing to tab to). Idempotent, so it is safe to run on every change.
  function refresh(st) {
    var list = entries(st.box);
    var active = list.length >= 2;
    st.box.classList.toggle("gk-sort-aktiv", active);
    list.forEach(function (el, i) {
      var handle = Array.prototype.find.call(el.children, function (c) { return c.matches(HANDLE); });
      if (!active) {
        if (handle) handle.remove();
        return;
      }
      if (!handle) {
        handle = makeHandle(st);
        el.appendChild(handle);
      }
      var name = t("sortierbar_griff", { titel: titleOf(el, i) });
      if (handle.getAttribute("aria-label") !== name) handle.setAttribute("aria-label", name);
    });
  }

  /* ── Saving ──────────────────────────────────────────────────────────── */

  function fire(st, list, extra) {
    var detail = { ids: list, behaelter: st.box };
    for (var key in extra || {}) detail[key] = extra[key];
    st.box.dispatchEvent(new CustomEvent("gk:sortiert", { bubbles: true, detail: detail }));
  }

  // The answer counts as "saved" when the status is 2xx and a JSON body, if there is one, does not
  // say {"ok": false}. A body that is no JSON is no objection.
  function accepted(res) {
    if (!res.ok) return Promise.resolve(false);
    return res.json().then(
      function (body) { return !(body && (body.ok === false || body.success === false)); },
      function () { return true; }
    );
  }

  function failed(st) {
    var known = st.confirmed;
    restore(st, known);
    fire(st, ids(st.box), { zurueck: true });
    showFailure(st);
  }

  // One request at a time: a new drop cancels the one still on its way, and only the last answer counts.
  function save(st, list) {
    var headers = { "Content-Type": "application/json" };
    var token = st.box.getAttribute("data-gk-sortierbar-csrf");
    var url = st.box.getAttribute("data-gk-sortierbar-url");
    var control = new AbortController();
    if (st.saving) st.saving.abort();
    st.saving = control;
    clearFailure(st);
    if (token) headers[st.box.getAttribute("data-gk-sortierbar-csrf-header") || "X-CSRF-Token"] = token;
    fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: headers,
      body: JSON.stringify({ ids: list }),
      signal: control.signal
    }).then(accepted).then(function (good) {
      if (st.saving !== control) return;
      st.saving = null;
      if (good) {
        st.confirmed = list;
        showSaved(st);
      } else {
        failed(st);
      }
    }, function (err) {
      if ((err && err.name === "AbortError") || st.saving !== control) return;
      st.saving = null;
      failed(st);
    });
  }

  // An entry was set down in a new place: tell the page, and the server if there is an address.
  function commit(st) {
    var list = ids(st.box);
    fire(st, list);
    if (st.box.getAttribute("data-gk-sortierbar-url")) save(st, list);
    else st.confirmed = list;
  }

  /* ── The keyboard ────────────────────────────────────────────────────── */

  // How many entries stand in the first row: 1 for a column, all of them for a single row. Measured by
  // the layout (offsetTop), not by where an entry is drawn: one that is still gliding is not in its row yet.
  function rowLength(list) {
    var top = list[0].offsetTop;
    var n = 0;
    while (n < list.length && Math.abs(list[n].offsetTop - top) < list[0].offsetHeight / 2) n++;
    return n;
  }

  function targetIndex(st, el, key) {
    var list = entries(st.box);
    var at = list.indexOf(el);
    var cols;
    var row;
    var flip;
    var delta;
    if (key === "Home") return 0;
    if (key === "End") return list.length - 1;
    cols = rowLength(list);
    row = cols > 1 && cols < list.length ? cols : 1;
    flip = getComputedStyle(st.box).direction === "rtl" ? -1 : 1;
    delta = { ArrowRight: flip, ArrowLeft: -flip, ArrowDown: row, ArrowUp: -row }[key];
    return Math.max(0, Math.min(list.length - 1, at + delta));
  }

  function lift(st, handle, el) {
    st.key = { el: el, handle: handle, before: ids(st.box) };
    el.classList.add("gk-sort-zieht");
    handle.setAttribute("aria-pressed", "true");
    announce(st, t("sortierbar_aufgehoben", position(st, el)));
  }

  function release(st) {
    var k = st.key;
    st.key = null;
    k.el.classList.remove("gk-sort-zieht");
    k.handle.setAttribute("aria-pressed", "false");
    return k;
  }

  function putDown(st) {
    var k = release(st);
    announce(st, t("sortierbar_abgelegt", position(st, k.el)));
    if (!sameOrder(k.before, ids(st.box))) commit(st);
  }

  function giveBack(st) {
    var k = release(st);
    moveTo(st.box, k.el, k.before.indexOf(String(k.el.getAttribute(ID))));
    announce(st, t("sortierbar_abgebrochen", position(st, k.el)));
  }

  function onKey(st, e) {
    var handle = e.target.closest ? e.target.closest(HANDLE) : null;
    var el = handle && handle.parentNode;
    if (!handle || !el || el.parentNode !== st.box || e.ctrlKey || e.altKey || e.metaKey || st.drag) return;
    if (e.key === " " || e.key === "Enter") {
      e.preventDefault();
      if (st.key) putDown(st);
      else lift(st, handle, el);
    } else if (st.key && e.key === "Escape") {
      e.preventDefault();
      e.stopPropagation();
      giveBack(st);
    } else if (st.key && /^(Arrow(Left|Right|Up|Down)|Home|End)$/.test(e.key)) {
      e.preventDefault();
      moveTo(st.box, el, targetIndex(st, el, e.key));
      announce(st, t("sortierbar_position", position(st, el)));
      handle.scrollIntoView({ block: "nearest", inline: "nearest" });
    }
  }

  /* ── The pointer ─────────────────────────────────────────────────────── */

  function pageRect(el) {
    var r = el.getBoundingClientRect();
    return { l: r.left + window.scrollX, t: r.top + window.scrollY, r: r.right + window.scrollX, b: r.bottom + window.scrollY };
  }

  // The slot nearest to a point: the one the point is in, else the closest (the gap between cards
  // belongs to its nearer neighbour, and a point beyond the end belongs to the last slot).
  function nearestSlot(slots, x, y) {
    var best = 0;
    var bestDistance = Infinity;
    slots.forEach(function (s, i) {
      var dx = Math.max(s.l - x, 0, x - s.r);
      var dy = Math.max(s.t - y, 0, y - s.b);
      var distance = dx * dx + dy * dy;
      if (distance < bestDistance) {
        bestDistance = distance;
        best = i;
      }
    });
    return best;
  }

  // Where the gap sits: with `to` of the other entries before it.
  function moveGap(st) {
    var d = st.drag;
    var others = entries(st.box).filter(function (el) { return el !== d.el; });
    var moving = others.concat(d.gap);
    var before = snapshot(moving);
    if (others[d.to]) others[d.to].before(d.gap);
    else others[others.length - 1].after(d.gap);
    play(moving, before);
  }

  function retarget(st) {
    var d = st.drag;
    var to = nearestSlot(d.slots, d.x + window.scrollX, d.y + window.scrollY);
    if (to === d.to) return;
    d.to = to;
    moveGap(st);
  }

  function place(d) {
    d.el.style.left = (d.x - d.grabX + d.shiftX) + "px";
    d.el.style.top = (d.y - d.grabY + d.shiftY) + "px";
  }

  // The entry leaves the flow (position: fixed from .gk-sort-frei) and a gap of the same size, an
  // empty element of the same tag, holds its place; the entry follows the pointer.
  function beginDrag(st) {
    var d = st.drag;
    var list = entries(st.box);
    var r = d.el.getBoundingClientRect();
    var seen;
    d.started = true;
    d.from = list.indexOf(d.el);
    d.to = d.from;
    d.slots = list.map(pageRect);
    d.grabX = d.x0 - r.left;
    d.grabY = d.y0 - r.top;
    d.oldPos = POS.map(function (p) { return d.el.style[p]; });
    d.gap = document.createElement(d.el.tagName);
    d.gap.className = "gk-sort-platz";
    d.gap.setAttribute("aria-hidden", "true");
    d.gap.style.width = r.width + "px";
    d.gap.style.height = r.height + "px";
    d.el.before(d.gap);
    d.el.classList.add("gk-sort-zieht", "gk-sort-frei");
    d.el.style.width = r.width + "px";
    d.el.style.height = r.height + "px";
    d.el.style.left = r.left + "px";
    d.el.style.top = r.top + "px";
    // An ancestor with a transform makes "fixed" relative to itself; measure what is wrong once and correct it.
    seen = d.el.getBoundingClientRect();
    d.shiftX = r.left - seen.left;
    d.shiftY = r.top - seen.top;
    place(d);
    document.addEventListener("selectstart", st.noSelect);
    document.addEventListener("keydown", st.dragKey, true);
    tick(st);
  }

  // While a drag is held near the top or the bottom of the window the page scrolls under it.
  function tick(st) {
    var d = st.drag;
    var step = 0;
    var before;
    if (!d) return;
    if (d.y < EDGE) step = -Math.ceil((EDGE - d.y) / 4);
    else if (d.y > window.innerHeight - EDGE) step = Math.ceil((d.y - (window.innerHeight - EDGE)) / 4);
    if (step) {
      before = window.scrollY;
      window.scrollBy({ top: step, behavior: "instant" });
      if (window.scrollY !== before) retarget(st);
    }
    d.frame = requestAnimationFrame(function () { tick(st); });
  }

  function endDrag(st, cancelled) {
    var d = st.drag;
    var list;
    var before;
    st.drag = null;
    cancelAnimationFrame(d.frame);
    document.removeEventListener("selectstart", st.noSelect);
    document.removeEventListener("keydown", st.dragKey, true);
    if (d.handle.hasPointerCapture && d.handle.hasPointerCapture(d.pointer)) d.handle.releasePointerCapture(d.pointer);
    if (!d.started) return;
    list = entries(st.box);
    before = snapshot(list);
    d.gap.remove();
    d.el.classList.remove("gk-sort-zieht", "gk-sort-frei");
    POS.forEach(function (p, i) { d.el.style[p] = d.oldPos[i]; });
    if (!d.el.getAttribute("style")) d.el.removeAttribute("style");
    reorder(st.box, d.el, cancelled ? d.from : d.to);
    play(list, before);
    d.handle.focus({ preventScroll: true });
    if (cancelled) return;
    announce(st, t("sortierbar_abgelegt", position(st, d.el)));
    if (d.to !== d.from) commit(st);
  }

  function onDown(st, e) {
    var handle = e.target.closest ? e.target.closest(HANDLE) : null;
    var el = handle && handle.parentNode;
    if (!handle || !el || el.parentNode !== st.box || (st.drag && st.drag.started) || st.key) return;
    if (!e.isPrimary || (e.pointerType === "mouse" && e.button !== 0)) return;
    // Captured first: the pointer is followed even outside the handle and the window, and a press that
    // cannot be captured (the pointer is gone) leaves nothing behind.
    try {
      handle.setPointerCapture(e.pointerId);
    } catch (err) {
      return;
    }
    st.drag = { el: el, handle: handle, pointer: e.pointerId, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, started: false };
  }

  function onMove(st, e) {
    var d = st.drag;
    if (!d || e.pointerId !== d.pointer) return;
    d.x = e.clientX;
    d.y = e.clientY;
    if (!d.started) {
      if (Math.abs(d.x - d.x0) < THRESHOLD && Math.abs(d.y - d.y0) < THRESHOLD) return;
      beginDrag(st);
    }
    place(d);
    retarget(st);
  }

  function onUp(st, e, cancelled) {
    if (st.drag && e.pointerId === st.drag.pointer) endDrag(st, cancelled);
  }

  /* ── Setting a container up ──────────────────────────────────────────── */

  function create(tag, className) {
    var el = document.createElement(tag);
    el.className = className;
    return el;
  }

  // Under the container: the instructions (read with the handle), the live region, the line that
  // says "saved" and the line that says why not. Siblings of the container, never children – a <ul>
  // takes only <li>.
  function makeMessages(st) {
    var wrap = create("div", "gk-sort-meldungen");
    st.helpId = "gk-sort-hilfe-" + (++counter);
    var help = create("p", "gk-sr-only");
    help.id = st.helpId;
    help.textContent = t("sortierbar_hilfe");
    st.live = create("p", "gk-sr-only");
    st.live.setAttribute("aria-live", "polite");
    st.ok = create("p", "gk-sort-ok");
    st.ok.setAttribute("aria-hidden", "true");
    st.ok.hidden = true;
    st.fail = create("p", "gk-sort-fehler");
    st.fail.setAttribute("role", "alert");
    st.fail.hidden = true;
    [help, st.live, st.ok, st.fail].forEach(function (n) { wrap.appendChild(n); });
    st.box.after(wrap);
  }

  function setup(box) {
    var st;
    if (started.has(box)) return;
    started.add(box);
    st = { box: box, drag: null, key: null, saving: null, okTimer: 0, said: "", saidAt: 0, sayTimer: 0, confirmed: ids(box) };
    st.noSelect = function (e) { e.preventDefault(); };
    st.dragKey = function (e) {
      if (e.key !== "Escape" || !st.drag) return;
      e.preventDefault();
      e.stopPropagation();
      endDrag(st, true);
    };
    makeMessages(st);
    box.addEventListener("pointerdown", function (e) { onDown(st, e); });
    box.addEventListener("pointermove", function (e) { onMove(st, e); });
    box.addEventListener("pointerup", function (e) { onUp(st, e, false); });
    box.addEventListener("pointercancel", function (e) { onUp(st, e, true); });
    box.addEventListener("keydown", function (e) { onKey(st, e); });
    // A lifted entry is put back when the handle loses the focus (Tab, a click elsewhere, another window).
    box.addEventListener("focusout", function (e) { if (st.key && e.target === st.key.handle) giveBack(st); });
    // A long press on a finger would open the context menu in the middle of a drag.
    box.addEventListener("contextmenu", function (e) { if (e.target.closest && e.target.closest(HANDLE)) e.preventDefault(); });
    new MutationObserver(function () { refresh(st); }).observe(box, { childList: true });
    refresh(st);
  }

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(CONTAINER)) setup(root);
    root.querySelectorAll(CONTAINER).forEach(setup);
  }

  function start() {
    scan(document.documentElement);
    // A container that arrives later – a fragment, a modal, a card list drawn by script – is set up
    // without the page calling anything.
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  // Run now if the document is already parsed (defer, async, a fragment), otherwise when it is.
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
