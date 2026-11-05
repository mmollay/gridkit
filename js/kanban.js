/* GridKit kanban v1.124.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js is one:
 * the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites, so a block that
 * lives in gridkit.js would never reach it. Load it after gridkit.js (skeleton.php does). Nothing here
 * needs gridkit.js: the words come from window.GK_LANG, and GK.toast is used when it is there.
 *
 *   <div class="gk-kanban" data-gk-kanban="tickets" data-gk-kanban-move-url="…">   ← GridKit\Kanban writes it
 *     <section class="gk-kanban-board" aria-label="Tickets">
 *       <div class="gk-kanban-column" data-gk-kanban-column="new" data-gk-kanban-limit="5">
 *         <h3 class="gk-kanban-title">New</h3> <span class="gk-kanban-count">…</span>
 *         <ul class="gk-kanban-list"><li class="gk-kanban-card" data-gk-kanban-id="T-1">…</li></ul>
 *
 * The server's board works on its own: every card in view, a "Move to" form per card that posts. This
 * script adds a handle to every card that may move and with it dragging (mouse, pen, finger — the board
 * and the page scroll under the pointer), moving with the keyboard (Space lifts, the arrows move, Space
 * drops, Escape cancels) with every step spoken, the counts and limits kept up to date, the "Move to"
 * form sent as JSON instead of a page load, and every move posted one at a time with the old place given
 * back when a save fails.
 *
 * Events, on the .gk-kanban, bubbling:
 *   gk:kanbanmove   { board, id, from, to, index, fromIndex }   cancelable, before the move
 *   gk:kanbanmoved  { …the same, ok, reverted }                 after it is saved, or given back
 */
(function () {
  "use strict";

  var lang = window.GK_LANG || {};
  function t(key, params) {
    var text = lang[key] || key;
    for (var name in params || {}) text = text.split("{" + name + "}").join(params[name]);
    return text;
  }

  var BOARD = "[data-gk-kanban]";
  var THRESHOLD = 4;   // px the pointer travels before a press becomes a drag
  var EDGE = 56;       // px from the edge of the board (sideways) or the window (up and down) where a drag scrolls
  var SAY_MS = 3000;   // a message younger than this is followed, not replaced (see announce)
  var boards = new WeakMap();
  var counter = 0;

  /* ── Reading the board ───────────────────────────────────────────────── */

  function columnsOf(st) {
    return Array.prototype.filter.call(st.board.querySelectorAll(".gk-kanban-column"), function (c) {
      return c.closest(BOARD) === st.root;
    });
  }
  function listOf(col) { return col.querySelector(".gk-kanban-list"); }
  function cardsIn(col) {
    var list = listOf(col);
    return list ? Array.prototype.filter.call(list.children, function (c) { return c.classList.contains("gk-kanban-card"); }) : [];
  }
  function keyOf(col) { return String(col.getAttribute("data-gk-kanban-column")); }
  function idOf(card) { return String(card.getAttribute("data-gk-kanban-id")); }
  function columnOf(card) { return card.closest(".gk-kanban-column"); }
  function words(el) { return el ? el.textContent.replace(/\s+/g, " ").trim() : ""; }
  function titleOf(card) { return words(card.querySelector(".gk-kanban-card-title")) || idOf(card); }
  function nameOf(col) { return words(col.querySelector(".gk-kanban-title")) || keyOf(col); }
  function limitOf(col) {
    var n = parseInt(col.getAttribute("data-gk-kanban-limit") || "", 10);
    return n > 0 ? n : null;
  }
  function canMove(card) { return !card.hasAttribute("data-gk-kanban-nodrag"); }
  function find(st, id) {
    id = String(id);
    var all = st.board.querySelectorAll(".gk-kanban-card");
    for (var i = 0; i < all.length; i++) if (idOf(all[i]) === id && all[i].closest(BOARD) === st.root) return all[i];
    return null;
  }
  function findColumn(st, key) {
    key = String(key);
    var cols = columnsOf(st);
    for (var i = 0; i < cols.length; i++) if (keyOf(cols[i]) === key) return cols[i];
    return null;
  }
  function placeOf(card) {
    var col = columnOf(card);
    return { col: col, index: cardsIn(col).indexOf(card) };
  }
  function rtl(st) { return getComputedStyle(st.board).direction === "rtl"; }

  // A column at its limit takes no other card in 'block' mode.
  function isFull(st, card, col) {
    var limit = limitOf(col);
    if (limit === null || columnOf(card) === col) return false;
    return cardsIn(col).length >= limit;
  }
  function refusedFull(st, card, col) { return st.wip === "block" && isFull(st, card, col); }

  /* ── Telling the person and the page ─────────────────────────────────── */

  // Emptied first and filled a moment later, so the same words twice are heard twice. With `append`
  // the words follow a message that is still young: "… moved … Saved." is one message.
  function announce(st, text, append) {
    var said = append && Date.now() - st.saidAt < SAY_MS ? st.said + " " : "";
    st.said = said + text;
    st.saidAt = Date.now();
    st.live.textContent = "";
    clearTimeout(st.sayTimer);
    st.sayTimer = setTimeout(function () { st.live.textContent = st.said; }, 20);
  }

  function fire(st, name, detail, cancelable) {
    detail.board = st.id;
    return st.root.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable: !!cancelable, detail: detail }));
  }

  function where(card) {
    var at = placeOf(card);
    return { column: nameOf(at.col), n: at.index + 1, m: cardsIn(at.col).length };
  }

  // A failed save: the library's toast where gridkit.js is loaded, otherwise the line above the board.
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

  /* ── Counts, limits and the "Move to" lists ──────────────────────────── */

  function setCount(col, n, limit) {
    var box = col.querySelector(".gk-kanban-count");
    if (!box) return;
    var shown = limit !== null ? n + " / " + limit : String(n);
    var said = limit !== null ? t(n === 1 ? "kanban_count_limit_one" : "kanban_count_limit", { n: n, limit: limit })
      : t(n === 1 ? "kanban_count_one" : "kanban_count", { n: n });
    var parts = box.children;
    if (parts.length === 2) {
      parts[0].textContent = shown;
      parts[1].textContent = said;
    } else {
      box.textContent = shown;
    }
  }

  // After every change of place: the count of every column, its mark when it is over its limit, the
  // words of an empty column, and what each card's "Move to" list offers.
  function refresh(st) {
    var cols = columnsOf(st);
    var counts = {};
    cols.forEach(function (col) {
      var n = cardsIn(col).length;
      var limit = limitOf(col);
      var over = limit !== null && n > limit;
      counts[keyOf(col)] = n;
      setCount(col, n, limit);
      col.classList.toggle("gk-kanban-over", over);
      var note = col.querySelector(".gk-kanban-over-note");
      if (note) note.hidden = !over;
      var empty = col.querySelector(".gk-kanban-empty");
      if (empty) empty.hidden = n > 0;
    });
    st.board.querySelectorAll(".gk-kanban-move-select").forEach(function (select) {
      var card = select.closest(".gk-kanban-card");
      if (!card) return;
      var mine = keyOf(columnOf(card));
      Array.prototype.forEach.call(select.options, function (o) {
        var col = findColumn(st, o.value);
        if (!o.hasAttribute("data-gk-kanban-name")) o.setAttribute("data-gk-kanban-name", col ? nameOf(col) : o.textContent);
        var full = !!col && st.wip === "block" && o.value !== mine && limitOf(col) !== null && counts[o.value] >= limitOf(col);
        o.disabled = full;
        o.selected = o.value === mine;
        o.textContent = o.getAttribute("data-gk-kanban-name") + (full ? " " + t("kanban_full_mark") : "");
      });
    });
  }

  /* ── Places ──────────────────────────────────────────────────────────── */

  // Every column's cards, as [list, cards] pairs: the last state the server agreed to.
  function snapshot(st) {
    return columnsOf(st).map(function (col) { return [listOf(col), cardsIn(col)]; });
  }

  function keepFocus(st, fn) {
    st.moving = true;
    var focused = st.root.contains(document.activeElement) ? document.activeElement : null;
    try { fn(); } finally {
      if (focused && document.activeElement !== focused && document.contains(focused)) focused.focus({ preventScroll: true });
      st.moving = false;
    }
  }

  function restore(st, pairs) {
    keepFocus(st, function () {
      pairs.forEach(function (pair) {
        pair[1].forEach(function (card) { pair[0].appendChild(card); });
      });
    });
    refresh(st);
  }

  // Puts `card` at `index` among the cards of `col`, counted without the card itself — the place it
  // will have.
  function place(st, card, col, index) {
    keepFocus(st, function () {
      var list = listOf(col);
      var others = cardsIn(col).filter(function (c) { return c !== card; });
      list.insertBefore(card, others[index] || null);
    });
    refresh(st);
  }

  function detailOf(card, from, to) {
    return { id: idOf(card), from: keyOf(from.col), to: keyOf(to.col), index: to.index, fromIndex: from.index };
  }

  function sayMoved(st, card) {
    var w = where(card);
    var text = t("kanban_moved", { title: titleOf(card), column: w.column, n: w.n, m: w.m });
    var col = columnOf(card);
    var limit = limitOf(col);
    if (limit !== null && w.m > limit) text += " " + t("kanban_over", { column: w.column, n: w.m, limit: limit });
    announce(st, text);
  }

  function sayFull(st, col) {
    announce(st, t("kanban_full", { column: nameOf(col), limit: limitOf(col) }));
  }

  // A move a person made (a drop, the "Move to" form, GK.kanban.move): the page may still say no.
  // Resolves with { ok, reverted } once the server has answered (at once without a URL), or false
  // when nothing was moved.
  function move(st, card, col, index) {
    if (!st.movable || !canMove(card) || !col) return Promise.resolve(false);
    if (refusedFull(st, card, col)) { sayFull(st, col); return Promise.resolve(false); }
    var from = placeOf(card);
    var max = cardsIn(col).filter(function (c) { return c !== card; }).length;
    if (index == null || index > max) index = max;
    if (index < 0) index = 0;
    if (from.col === col && from.index === index) return Promise.resolve(false);
    var detail = detailOf(card, from, { col: col, index: index });
    if (!fire(st, "gk:kanbanmove", detail, true)) return Promise.resolve(false);
    place(st, card, col, index);
    sayMoved(st, card);
    return commit(st, detail);
  }

  function commit(st, detail) {
    clearError(st);
    return new Promise(function (resolve) {
      if (!st.moveUrl) {
        st.confirmed = snapshot(st);
        var r = { ok: true, reverted: false };
        fire(st, "gk:kanbanmoved", Object.assign({}, detail, r));
        resolve(r);
        return;
      }
      st.queue.push({ detail: detail, after: snapshot(st), resolve: resolve });
      pump(st);
    });
  }

  // "Saved" is a 2xx whose JSON, if there is one, does not say {"ok": false}. Its "message", if any, is
  // what the person reads when it is a refusal.
  function verdict(res) {
    return res.text().then(function (text) {
      var body = null;
      try { body = JSON.parse(text); } catch (e) { body = null; }
      var refused = !!body && (body.ok === false || body.success === false);
      return { ok: res.ok && !refused, message: body && typeof body.message === "string" ? body.message : "" };
    });
  }

  // One move on its way at a time, in the order they were made: each names a place that only exists
  // after the one before it. When one fails, it and every later one still waiting go back to what the
  // server agreed to last.
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
      body: JSON.stringify({ id: m.detail.id, column: m.detail.to, index: m.detail.index })
    }).then(verdict, function () { return { ok: false, message: "" }; }).then(function (r) {
      st.busy = false;
      if (r.ok) {
        st.confirmed = m.after;
        announce(st, t("kanban_saved"), true);
        var ok = { ok: true, reverted: false };
        fire(st, "gk:kanbanmoved", Object.assign({}, m.detail, ok));
        m.resolve(ok);
        pump(st);
        return;
      }
      var dropped = [m].concat(st.queue);
      st.queue = [];
      if (st.lift) release(st);
      restore(st, st.confirmed);
      var card = find(st, m.detail.id);
      if (card) card.scrollIntoView({ block: "nearest", inline: "nearest" });
      showError(st, r.message || t("kanban_save_error"));
      announce(st, r.message || t("kanban_save_error"));
      var back = { ok: false, reverted: true };
      dropped.forEach(function (d) {
        fire(st, "gk:kanbanmoved", Object.assign({}, d.detail, back));
        d.resolve(back);
      });
    });
  }

  /* ── Moving with the keyboard: the handle ────────────────────────────── */

  function lift(st, card, handle) {
    st.lift = { card: card, handle: handle, from: placeOf(card) };
    card.classList.add("gk-kanban-lifted");
    handle.setAttribute("aria-pressed", "true");
    var w = where(card);
    announce(st, t("kanban_lifted", { title: titleOf(card), where: t("kanban_where", w) }));
  }

  function release(st) {
    var k = st.lift;
    st.lift = null;
    k.card.classList.remove("gk-kanban-lifted");
    k.handle.setAttribute("aria-pressed", "false");
    return k;
  }

  function putDown(st) {
    var k = release(st);
    var now = placeOf(k.card);
    if (now.col === k.from.col && now.index === k.from.index) {
      announce(st, t("kanban_dropped", { title: titleOf(k.card), where: t("kanban_where", where(k.card)) }));
      return;
    }
    var detail = detailOf(k.card, k.from, now);
    if (!fire(st, "gk:kanbanmove", detail, true)) {
      place(st, k.card, k.from.col, k.from.index);
      var w = where(k.card);
      announce(st, t("kanban_cancelled", { title: titleOf(k.card), column: w.column, n: w.n, m: w.m }));
      return;
    }
    sayMoved(st, k.card);
    commit(st, detail);
  }

  function giveBack(st) {
    var k = release(st);
    place(st, k.card, k.from.col, k.from.index);
    var w = where(k.card);
    announce(st, t("kanban_cancelled", { title: titleOf(k.card), column: w.column, n: w.n, m: w.m }));
  }

  // One step of a lifted card: Up and Down within its column, Left and Right to the column beside it (at
  // the same height, or its end), Home and End to the ends of its column.
  function step(st, key) {
    var card = st.lift.card;
    var at = placeOf(card);
    var n = cardsIn(at.col).length;
    var cols = columnsOf(st);
    var ci = cols.indexOf(at.col);
    var flip = rtl(st);
    var to = null;
    if (key === "ArrowUp" && at.index > 0) to = { col: at.col, index: at.index - 1 };
    else if (key === "ArrowDown" && at.index < n - 1) to = { col: at.col, index: at.index + 1 };
    else if (key === "Home" && at.index > 0) to = { col: at.col, index: 0 };
    else if (key === "End" && at.index < n - 1) to = { col: at.col, index: n - 1 };
    else if (key === "ArrowLeft" || key === "ArrowRight") {
      var next = cols[ci + ((key === "ArrowRight") !== flip ? 1 : -1)];
      if (next) {
        if (refusedFull(st, card, next)) { sayFull(st, next); return; }
        to = { col: next, index: Math.min(at.index, cardsIn(next).length) };
      }
    }
    if (!to) { announce(st, t("kanban_edge")); return; }
    place(st, card, to.col, to.index);
    announce(st, t("kanban_where", where(card)));
    st.lift.handle.scrollIntoView({ block: "nearest", inline: "nearest" });
  }

  function onHandleKey(st, e) {
    var handle = e.target.closest(".gk-kanban-handle");
    var card = handle && handle.closest(".gk-kanban-card");
    if (!card || e.ctrlKey || e.altKey || e.metaKey || st.drag) return;
    if (e.key === " " || e.key === "Enter") {
      e.preventDefault();
      if (st.lift) putDown(st);
      else lift(st, card, handle);
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

  // Where a drop at the pointer would put the card: the column under it, before the first card whose
  // middle is below the pointer (or at the end).
  function dropAt(st, x, y) {
    var d = st.drag;
    var el = document.elementFromPoint(x, y);
    var col = el && el.closest ? el.closest(".gk-kanban-column") : null;
    if (!col || col.closest(BOARD) !== st.root) return null;
    var others = cardsIn(col).filter(function (c) { return c !== d.card; });
    var index = others.length;
    for (var i = 0; i < others.length; i++) {
      var r = others[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) { index = i; break; }
    }
    return { col: col, index: index, others: others, full: refusedFull(st, d.card, col) };
  }

  function showDrop(st, drop) {
    var d = st.drag;
    if (d.target && (!drop || d.target !== drop.col)) d.target.classList.remove("gk-kanban-target", "gk-kanban-target-no");
    d.target = drop ? drop.col : null;
    d.ghost.classList.toggle("gk-kanban-ghost-no", !!(drop && drop.full));
    d.line.hidden = true;
    if (!drop) return;
    drop.col.classList.add("gk-kanban-target");
    drop.col.classList.toggle("gk-kanban-target-no", drop.full);
    if (drop.full) return;
    var from = placeOf(d.card);
    if (drop.col === from.col && drop.index === from.index) return;   // where it is: nothing to show
    // The line stands in the gap where the card would go: above the card it would come before, or
    // below the last one, or at the top of an empty list.
    var list = listOf(drop.col);
    if (d.line.parentNode !== drop.col) drop.col.appendChild(d.line);
    var box = drop.col.getBoundingClientRect();
    var lr = list.getBoundingClientRect();
    var top;
    if (drop.others.length === 0) top = lr.top - box.top + 4;
    else if (drop.index < drop.others.length) top = drop.others[drop.index].getBoundingClientRect().top - box.top - 4;
    else top = drop.others[drop.others.length - 1].getBoundingClientRect().bottom - box.top + 4;
    d.line.style.top = top + "px";
    d.line.style.left = lr.left - box.left + "px";
    d.line.style.width = lr.width + "px";
    d.line.hidden = false;
  }

  function retarget(st) {
    var d = st.drag;
    d.drop = dropAt(st, d.x, d.y);
    showDrop(st, d.drop);
  }

  function beginDrag(st) {
    var d = st.drag;
    d.started = true;
    var r = d.card.getBoundingClientRect();
    d.dx = d.x0 - r.left;
    d.dy = d.y0 - r.top;
    d.card.classList.add("gk-kanban-dragging");
    var ghost = d.card.cloneNode(true);
    ghost.removeAttribute("id");
    ghost.querySelectorAll("[id]").forEach(function (el) { el.removeAttribute("id"); });
    ghost.querySelectorAll("a, button, summary, select, input").forEach(function (el) { el.setAttribute("tabindex", "-1"); });
    ghost.className = "gk-kanban-card gk-kanban-ghost";
    ghost.setAttribute("aria-hidden", "true");
    ghost.style.width = r.width + "px";
    // The ghost lives in the board's wrapper, so the board's look (theme, mode, compact) reaches it.
    st.root.appendChild(ghost);
    d.ghost = ghost;
    d.line = document.createElement("div");
    d.line.className = "gk-kanban-drop";
    d.line.setAttribute("aria-hidden", "true");
    d.line.hidden = true;
    st.root.classList.add("gk-kanban-busy");
    document.addEventListener("selectstart", st.noSelect);
    document.addEventListener("keydown", st.dragKey, true);
    tick(st);
  }

  function follow(d) {
    if (!d.ghost) return;
    d.ghost.style.transform = "translate(" + (d.x - d.dx) + "px," + (d.y - d.dy) + "px) rotate(2deg)";
  }

  // Held near the side of the board it scrolls sideways under the card; near the top or the bottom of
  // the window the page scrolls.
  function tick(st) {
    var d = st.drag;
    if (!d || !d.started) return;
    var moved = false;
    var b = st.board.getBoundingClientRect();
    var side = 0;
    if (d.x < b.left + EDGE) side = -Math.ceil((b.left + EDGE - d.x) / 3);
    else if (d.x > b.right - EDGE) side = Math.ceil((d.x - (b.right - EDGE)) / 3);
    if (side) {
      var left = st.board.scrollLeft;
      st.board.scrollLeft = left + side;
      if (st.board.scrollLeft !== left) moved = true;
    }
    var up = 0;
    if (d.y < EDGE) up = -Math.ceil((EDGE - d.y) / 4);
    else if (d.y > window.innerHeight - EDGE) up = Math.ceil((d.y - (window.innerHeight - EDGE)) / 4);
    if (up) {
      var before = window.scrollY;
      window.scrollBy({ top: up, behavior: "instant" });
      if (window.scrollY !== before) moved = true;
    }
    if (moved) retarget(st);
    d.frame = requestAnimationFrame(function () { tick(st); });
  }

  function endDrag(st, cancelled) {
    var d = st.drag;
    st.drag = null;
    if (!d) return;
    cancelAnimationFrame(d.frame);
    document.removeEventListener("selectstart", st.noSelect);
    document.removeEventListener("keydown", st.dragKey, true);
    try { if (d.el.hasPointerCapture && d.el.hasPointerCapture(d.pointer)) d.el.releasePointerCapture(d.pointer); } catch (e) { /* gone */ }
    if (!d.started) return;
    d.card.classList.remove("gk-kanban-dragging");
    st.root.classList.remove("gk-kanban-busy");
    if (d.target) d.target.classList.remove("gk-kanban-target", "gk-kanban-target-no");
    if (d.ghost) d.ghost.remove();
    if (d.line) d.line.remove();
    if (cancelled) { var w = where(d.card); announce(st, t("kanban_cancelled", { title: titleOf(d.card), column: w.column, n: w.n, m: w.m })); return; }
    // A full column in 'block' is refused (and said) by move().
    if (d.drop) move(st, d.card, d.drop.col, d.drop.index);
  }

  // A press on a handle starts a drag (mouse, pen, finger). A mouse may also take a card by its body —
  // not by a link, a button or the form in it; a finger on the body scrolls the board.
  function onDown(st, e) {
    if (st.drag || st.lift || !e.isPrimary || (e.pointerType === "mouse" && e.button !== 0)) return;
    var handle = e.target.closest ? e.target.closest(".gk-kanban-handle") : null;
    var card = null;
    var el = handle;
    if (handle) {
      card = handle.closest(".gk-kanban-card");
    } else if (e.pointerType === "mouse" && !e.target.closest("a, button, summary, select, input, textarea, label, details[open] form")) {
      card = e.target.closest(".gk-kanban-card");
      el = card;
      if (card && !card.querySelector(".gk-kanban-handle")) card = null;
    }
    if (!card || !st.board.contains(card)) return;
    try { el.setPointerCapture(e.pointerId); } catch (err) { return; }
    st.drag = { card: card, el: el, pointer: e.pointerId, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, started: false };
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
    follow(d);
    retarget(st);
  }

  /* ── Setting a board up ──────────────────────────────────────────────── */

  function prepare(st, card) {
    if (card._gkKanban || !st.movable || !canMove(card)) return;
    card._gkKanban = true;
    var head = card.querySelector(".gk-kanban-card-head") || card;
    var handle = document.createElement("button");
    handle.type = "button";
    handle.className = "gk-kanban-handle";
    handle.setAttribute("aria-label", t("kanban_handle", { title: titleOf(card) }));
    handle.setAttribute("aria-describedby", st.helpId);
    handle.setAttribute("aria-pressed", "false");
    head.insertBefore(handle, head.firstChild);
  }

  function headersOf(root) {
    try {
      var h = JSON.parse(root.getAttribute("data-gk-kanban-headers") || "{}");
      return h && typeof h === "object" ? h : {};
    } catch (e) {
      return {};
    }
  }

  function create(tag, className) {
    var el = document.createElement(tag);
    el.className = className;
    return el;
  }

  function setup(root) {
    if (boards.has(root)) return boards.get(root);
    var board = root.querySelector(".gk-kanban-board");
    var st = {
      root: root,
      board: board,
      id: root.getAttribute("data-gk-kanban") || root.id || "kanban",
      moveUrl: root.getAttribute("data-gk-kanban-move-url") || "",
      headers: headersOf(root),
      movable: root.hasAttribute("data-gk-kanban-movable") || root.hasAttribute("data-gk-kanban-move-url"),
      wip: root.getAttribute("data-gk-kanban-wip") === "block" ? "block" : "warn",
      said: "", saidAt: 0, sayTimer: 0,
      queue: [], busy: false, drag: null, lift: null, moving: false
    };
    boards.set(root, st);
    if (!board) return st;
    st.helpId = "gk-kanban-help-" + (++counter);
    var help = create("p", "gk-sr-only");
    help.id = st.helpId;
    help.textContent = t("kanban_help");
    st.live = create("p", "gk-sr-only");
    st.live.setAttribute("aria-live", "polite");
    st.fail = create("p", "gk-kanban-error");
    st.fail.setAttribute("role", "alert");
    st.fail.hidden = true;
    board.before(st.fail);
    root.appendChild(help);
    root.appendChild(st.live);

    board.querySelectorAll(".gk-kanban-card").forEach(function (card) { prepare(st, card); });
    refresh(st);
    st.confirmed = snapshot(st);

    st.noSelect = function (e) { e.preventDefault(); };
    st.dragKey = function (e) {
      if (e.key !== "Escape" || !st.drag) return;
      e.preventDefault();
      e.stopPropagation();
      endDrag(st, true);
    };
    root.addEventListener("keydown", function (e) {
      if (e.target.closest && e.target.closest(".gk-kanban-handle")) onHandleKey(st, e);
    });
    // A lifted card goes back when its handle loses the focus (Tab, a click elsewhere).
    root.addEventListener("focusout", function (e) {
      if (st.lift && !st.moving && e.target === st.lift.handle) giveBack(st);
    });
    root.addEventListener("pointerdown", function (e) { onDown(st, e); });
    root.addEventListener("pointermove", function (e) { onMove(st, e); });
    root.addEventListener("pointerup", function (e) { if (st.drag && e.pointerId === st.drag.pointer) endDrag(st, false); });
    root.addEventListener("pointercancel", function (e) { if (st.drag && e.pointerId === st.drag.pointer) endDrag(st, true); });
    root.addEventListener("contextmenu", function (e) { if (e.target.closest && e.target.closest(".gk-kanban-handle")) e.preventDefault(); });
    // The "Move to" form, sent as JSON instead of a page load: the card goes to the end of the column.
    root.addEventListener("submit", function (e) {
      var form = e.target.closest ? e.target.closest(".gk-kanban-move-form") : null;
      if (!form || !st.movable) return;
      e.preventDefault();
      var card = form.closest(".gk-kanban-card");
      var select = form.querySelector("select");
      var col = select ? findColumn(st, select.value) : null;
      var details = form.closest("details");
      var summary = details ? details.querySelector("summary") : null;
      if (details) details.open = false;
      if (summary) summary.focus({ preventScroll: true });
      if (card && col) move(st, card, col, null);
    });
    root.setAttribute("data-gk-kanban-ready", "");
    return st;
  }

  /* ── The API ─────────────────────────────────────────────────────────── */

  // A board by its element, its id (as given to new Kanban()), an element id or a selector.
  function get(el) {
    var root = el;
    if (typeof el === "string") {
      root = null;
      document.querySelectorAll(BOARD).forEach(function (r) { if (!root && r.getAttribute("data-gk-kanban") === el) root = r; });
      if (!root) root = document.getElementById(el.replace(/^#/, ""));
      if (!root) { try { root = document.querySelector(el); } catch (e) { root = null; } }
    }
    if (root && root.closest) root = root.closest(BOARD);
    return root ? setup(root) : null;
  }

  var api = {
    // Moves a card the way a person would: the veto of gk:kanbanmove, the save, the way back. Resolves
    // with { ok, reverted }, or false when nothing moved. With { save: false } the card is only put there
    // (a change the server already knows, from another person): no event, no request.
    move: function (el, id, column, index, opts) {
      var st = get(el);
      if (!st || !st.board) return Promise.resolve(false);
      var card = find(st, id);
      var col = findColumn(st, column);
      if (!card || !col) return Promise.resolve(false);
      if (opts && opts.save === false) {
        var max = cardsIn(col).filter(function (c) { return c !== card; }).length;
        place(st, card, col, index == null || index > max ? max : Math.max(0, index));
        // The server knows it already: the state it agreed to has the card there too.
        if (!st.busy && !st.queue.length) st.confirmed = snapshot(st);
        else st.confirmed.forEach(function (pair) {
          var i = pair[1].indexOf(card);
          if (i >= 0) pair[1].splice(i, 1);
          if (pair[0] === listOf(col)) pair[1].splice(Math.min(placeOf(card).index, pair[1].length), 0, card);
        });
        return Promise.resolve({ ok: true, reverted: false });
      }
      return move(st, card, col, index == null ? null : index);
    },
    // The ids of the cards of one column, in order — or of every column, as { key: [ids] }.
    cards: function (el, column) {
      var st = get(el);
      if (!st || !st.board) return column == null ? {} : [];
      if (column != null) {
        var col = findColumn(st, column);
        return col ? cardsIn(col).map(idOf) : [];
      }
      var out = {};
      columnsOf(st).forEach(function (c) { out[keyOf(c)] = cardsIn(c).map(idOf); });
      return out;
    },
    // Every column: { key, label, count, limit, over }.
    columns: function (el) {
      var st = get(el);
      if (!st || !st.board) return [];
      return columnsOf(st).map(function (c) {
        var n = cardsIn(c).length;
        var limit = limitOf(c);
        return { key: keyOf(c), label: nameOf(c), count: n, limit: limit, over: limit !== null && n > limit };
      });
    },
    // After the page changed the board itself (a card added or taken out): handles, counts, limits.
    refresh: function (el) {
      var st = get(el);
      if (!st || !st.board) return;
      st.board.querySelectorAll(".gk-kanban-card").forEach(function (card) { prepare(st, card); });
      refresh(st);
      if (!st.busy && !st.queue.length) st.confirmed = snapshot(st);
    }
  };
  window.GK = window.GK || {};
  window.GK.kanban = api;

  /* ── Starting ────────────────────────────────────────────────────────── */

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(BOARD)) setup(root);
    root.querySelectorAll(BOARD).forEach(setup);
  }

  function start() {
    scan(document.documentElement);
    // A board that arrives later — a fragment, a modal, AJAX navigation — is set up without a call.
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
