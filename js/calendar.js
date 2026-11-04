/* GridKit dates v1.120.0 – Vanilla, zero dependencies. GridKit\Calendar and GridKit\DateRange in the
 * browser. A file of its own for the reason js/tree.js is one: the PawBot dashboard keeps a changed copy
 * of gridkit.js that no sync ever overwrites, so a block that lives in gridkit.js would never reach it.
 * Load it after gridkit.js (skeleton.php does). Nothing here needs gridkit.js: every word comes from
 * the markup the server wrote, so the browser writes what PHP would.
 *
 * DateRange — <div class="gk-daterange" data-gk-daterange="{today, min, max, months, text, submit}">:
 *   a preset fills the two dates (counted from the server's today, on the calendar, so a day of 23 or
 *   25 hours changes nothing), typing a date switches the select to "custom", the period is written in
 *   words, and name[shown] is left out of the POST (the three are kept in step here). In a filter form
 *   (DateRange::filter()) a chosen preset is sent at once.
 *     gk:daterange  { name, from, to, preset, text }   on the .gk-daterange, bubbling
 *
 * Calendar — <div class="gk-cal" data-gk-cal="id" data-gk-cal-month="2026-11" …>: the table becomes a
 *   grid for the keyboard (role grid, a roving tabindex: one tab stop, then the events of that day):
 *   arrows move by a day and a week, Home/End to the ends of the week, PageUp/PageDown by a month
 *   (with Shift by a year), Enter and Space choose the day. A day outside the grid, and the month
 *   links, go to that month (the page loads it; the day keeps the focus). "+3 more" closes on Escape
 *   and on a click elsewhere, one at a time.
 *     gk:calendarnav     { calendar, month, url, date }        cancelable, before another month loads
 *     gk:calendarselect  { calendar, date, event, events }     cancelable, a day or an event chosen
 */
(function () {
  "use strict";

  /* ── Days ───────────────────────────────────────────────────────────── */

  function daysIn(y, m) {
    if (m === 2) return (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28;
    return [4, 6, 9, 11].indexOf(m) >= 0 ? 30 : 31;
  }
  function pad(n, w) { n = String(n); while (n.length < w) n = "0" + n; return n; }
  function ymd(y, m, d) { return pad(y, 4) + "-" + pad(m, 2) + "-" + pad(d, 2); }
  // A day 'Y-m-d' that exists (years 1 to 9999) as {y, m, d}, or null — DateRange::date() in PHP.
  function parse(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(typeof s === "string" ? s.trim() : "");
    if (!m) return null;
    var y = +m[1], mo = +m[2], d = +m[3];
    return y >= 1 && mo >= 1 && mo <= 12 && d >= 1 && d <= daysIn(y, mo) ? { y: y, m: mo, d: d } : null;
  }
  // A day plus n days, on the calendar. setUTCFullYear, not Date.UTC: that one reads 0026 as 1926.
  function addDays(s, n) {
    var p = parse(s);
    if (!p) return s;
    var t = new Date(0);
    t.setUTCFullYear(p.y, p.m - 1, p.d + n);
    var y = t.getUTCFullYear();
    if (y < 1) return "0001-01-01";
    if (y > 9999) return "9999-12-31";
    return ymd(y, t.getUTCMonth() + 1, t.getUTCDate());
  }
  function addMonths(s, n) {
    var p = parse(s);
    if (!p) return s;
    var k = p.y * 12 + (p.m - 1) + n;
    var y = Math.floor(k / 12), m = k % 12 + 1;
    if (y < 1) return "0001-01-01";
    if (y > 9999) return "9999-12-31";
    return ymd(y, m, Math.min(p.d, daysIn(y, m)));
  }
  function fill(text, params) {
    for (var k in params) text = text.split("{" + k + "}").join(params[k]);
    return text;
  }

  // The days a preset covers, counted from today — DateRange::presetRange() in PHP, the same answer.
  function presetRange(key, today) {
    var p = parse(today);
    if (!p) return null;
    var y = p.y, m = p.m, q = Math.floor((m - 1) / 3) * 3 + 1, r;
    switch (key) {
      case "today": r = [today, today]; break;
      case "yesterday": r = [addDays(today, -1), addDays(today, -1)]; break;
      case "last-7-days": r = [addDays(today, -6), today]; break;
      case "last-30-days": r = [addDays(today, -29), today]; break;
      case "this-month": r = [ymd(y, m, 1), ymd(y, m, daysIn(y, m))]; break;
      case "last-month": r = m === 1 ? [ymd(y - 1, 12, 1), ymd(y - 1, 12, 31)] : [ymd(y, m - 1, 1), ymd(y, m - 1, daysIn(y, m - 1))]; break;
      case "this-quarter": r = [ymd(y, q, 1), ymd(y, q + 2, daysIn(y, q + 2))]; break;
      case "last-quarter": r = q === 1 ? [ymd(y - 1, 10, 1), ymd(y - 1, 12, 31)] : [ymd(y, q - 3, 1), ymd(y, q - 1, daysIn(y, q - 1))]; break;
      case "this-year": r = [ymd(y, 1, 1), ymd(y, 12, 31)]; break;
      case "last-year": r = [ymd(y - 1, 1, 1), ymd(y - 1, 12, 31)]; break;
      default: return null;
    }
    var first = "0001-01-01";
    return { from: r[0] < first ? first : r[0], to: r[1] < first ? first : r[1] };
  }

  // A period in words — DateRange::text() in PHP, with the words and shapes the server wrote.
  var ENGLISH = {
    months: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    text: { day: "{month} {d}, {y}", month: "{month} {d1} – {d2}, {y}", year: "{month1} {d1} – {month2} {d2}, {y}",
      years: "{month1} {d1}, {y1} – {month2} {d2}, {y2}" }
  };
  function text(from, to, cfg) {
    cfg = cfg && cfg.months && cfg.text ? cfg : ENGLISH;
    var a = parse(from), b = parse(to);
    if (!a || !b) return "";
    if (to < from) { var t = a; a = b; b = t; }
    var mo = cfg.months;
    if (a.y === b.y && a.m === b.m && a.d === b.d) return fill(cfg.text.day, { month: mo[a.m - 1], d: a.d, y: a.y });
    if (a.y === b.y && a.m === b.m) return fill(cfg.text.month, { month: mo[a.m - 1], d1: a.d, d2: b.d, y: a.y });
    if (a.y === b.y) return fill(cfg.text.year, { month1: mo[a.m - 1], d1: a.d, month2: mo[b.m - 1], d2: b.d, y: a.y });
    return fill(cfg.text.years, { month1: mo[a.m - 1], d1: a.d, y1: a.y, month2: mo[b.m - 1], d2: b.d, y2: b.y });
  }

  function fire(el, name, detail, cancelable) {
    var ev = new CustomEvent(name, { bubbles: true, cancelable: !!cancelable, detail: detail });
    el.dispatchEvent(ev);
    return !ev.defaultPrevented;
  }

  /* ── DateRange ──────────────────────────────────────────────────────── */

  var RANGE = "[data-gk-daterange]";
  var ranges = new WeakMap();

  function setupRange(root) {
    if (ranges.has(root)) return ranges.get(root);
    var cfg;
    try { cfg = JSON.parse(root.getAttribute("data-gk-daterange") || "{}"); } catch (e) { cfg = {}; }
    var st = {
      root: root,
      cfg: cfg,
      select: root.querySelector(".gk-daterange-preset"),
      from: root.querySelector(".gk-daterange-from"),
      to: root.querySelector(".gk-daterange-to"),
      out: root.querySelector(".gk-daterange-text")
    };
    ranges.set(root, st);
    if (!st.from || !st.to) return st;
    // The three are kept in step from here on: the preset that comes back is counted from today again.
    var shown = root.querySelector("[data-gk-daterange-shown]");
    if (shown) shown.disabled = true;

    if (st.select) {
      st.select.addEventListener("change", function () {
        var key = st.select.value;
        if (key !== "custom") {
          var r = clamp(st, presetRange(key, cfg.today));
          if (r) { st.from.value = r.from; st.to.value = r.to; }
        }
        update(st);
        fire(root, "gk:daterange", detail(st));
        if (cfg.submit && key !== "custom") send(st);
      });
    }
    [st.from, st.to].forEach(function (input) {
      input.addEventListener("input", function () {
        if (st.select && st.select.value !== "custom" && hasCustom(st)) st.select.value = "custom";
        update(st);
      });
      input.addEventListener("change", function () {
        if (st.select && st.select.value !== "custom" && hasCustom(st)) st.select.value = "custom";
        update(st);
        fire(root, "gk:daterange", detail(st));
      });
    });
    update(st);
    root.setAttribute("data-gk-daterange-ready", "");
    return st;
  }
  function hasCustom(st) {
    return !!st.select.querySelector('option[value="custom"]');
  }
  function clamp(st, r) {
    if (!r) return null;
    var min = st.cfg.min || "0001-01-01", max = st.cfg.max || "9999-12-31";
    var f = r.from < min ? min : r.from > max ? max : r.from;
    var t = r.to < min ? min : r.to > max ? max : r.to;
    return { from: f, to: t };
  }
  // The words, and each date bounded by the other: the browser itself refuses an end before the start.
  function update(st) {
    var min = st.cfg.min || "0001-01-01", max = st.cfg.max || "9999-12-31";
    var f = parse(st.from.value) ? st.from.value : "", t = parse(st.to.value) ? st.to.value : "";
    st.from.max = t && t < max ? t : max;
    st.to.min = f && f > min ? f : min;
    if (st.out) st.out.textContent = f && t && f <= t ? text(f, t, st.cfg) : "";
  }
  function detail(st) {
    var name = st.from.name.replace(/\[from\]$/, "");
    return { name: name, from: st.from.value, to: st.to.value, preset: st.select ? st.select.value : "custom",
      text: st.out ? st.out.textContent : "" };
  }
  function send(st) {
    var form = st.root.closest("form");
    if (!form) return;
    if (typeof form.requestSubmit === "function") form.requestSubmit();
    else form.submit();
  }
  function getRange(el) {
    var root = el;
    if (typeof el === "string") {
      root = document.getElementById(el) || document.getElementById("gk-daterange-" + el);
      if (!root) { try { root = document.querySelector(el); } catch (e) { root = null; } }
    }
    if (root && root.closest) root = root.closest(RANGE);
    return root ? setupRange(root) : null;
  }

  /* ── Calendar ───────────────────────────────────────────────────────── */

  var CAL = "[data-gk-cal]";
  var cals = new WeakMap();
  var FOCUS_KEY = "gk-cal:";

  function cellsOf(st) { return Array.prototype.slice.call(st.table.querySelectorAll("td.gk-cal-day")); }
  function cellFor(st, date) { return st.table.querySelector('td.gk-cal-day[data-gk-cal-date="' + date + '"]'); }
  function dateOf(td) { return td.getAttribute("data-gk-cal-date"); }
  function inner(td) { return td.querySelectorAll("a[href], summary, .gk-cal-event[role=\"button\"]"); }
  function rtl(st) { return getComputedStyle(st.table).direction === "rtl"; }

  // One tab stop: the active day, then its own links, events and "+n more"; every other day's are -1.
  function rove(st, td) {
    cellsOf(st).forEach(function (c) {
      var on = c === td;
      c.tabIndex = on ? 0 : -1;
      Array.prototype.forEach.call(inner(c), function (x) { x.tabIndex = on ? 0 : -1; });
    });
    st.active = td;
  }
  function focusDay(st, td) {
    if (!td) return false;
    rove(st, td);
    td.focus();
    return true;
  }

  function eventData(el) {
    var title = el.querySelector(".gk-cal-event-title");
    return {
      id: el.getAttribute("data-gk-cal-event") || "",
      title: title ? title.textContent.replace(/\s+/g, " ").trim() : "",
      href: el.tagName === "A" ? el.getAttribute("href") : null,
      start: el.getAttribute("data-gk-cal-start") || "",
      end: el.getAttribute("data-gk-cal-end") || ""
    };
  }
  // The events of a day: the open list of "+n more" when the day has one (it holds them all), else the chips.
  function eventsOf(st, date) {
    var td = cellFor(st, date);
    if (!td) return [];
    var list = td.querySelector(".gk-cal-more-list");
    var els = list ? list.querySelectorAll(".gk-cal-event") : td.querySelectorAll(".gk-cal-events > .gk-cal-event");
    return Array.prototype.map.call(els, eventData);
  }

  // The day an event stands in: its cell of the grid, or its day of the agenda.
  function dayOfEvent(el) {
    var td = el.closest("td.gk-cal-day");
    if (td) return dateOf(td);
    var li = el.closest(".gk-cal-agenda-day");
    var t = li ? li.querySelector("time[datetime]") : null;
    return t ? t.getAttribute("datetime") : null;
  }

  function select(st, date, eventEl) {
    return fire(st.root, "gk:calendarselect", {
      calendar: st.id, date: date, event: eventEl ? eventData(eventEl) : null, events: eventsOf(st, date)
    }, true);
  }

  // To another month: the page loads it, the day that had the focus gets it there again.
  function go(st, month, date) {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month || "") || month === "0000-00") return false;
    var url = st.urlTemplate.split("0000-00").join(month);
    if (!fire(st.root, "gk:calendarnav", { calendar: st.id, month: month, url: url, date: date || null }, true)) return false;
    if (date) { try { sessionStorage.setItem(FOCUS_KEY + st.id, date); } catch (e) { /* no storage: no focus */ } }
    window.location.assign(url);
    return true;
  }

  function moveTo(st, date) {
    var td = cellFor(st, date);
    if (td) return focusDay(st, td);
    return go(st, date.slice(0, 7), date);
  }

  function onKey(st, e) {
    var td = e.target;
    if (!td.classList || !td.classList.contains("gk-cal-day")) return;
    var date = dateOf(td), back = rtl(st) ? 1 : -1, row, target = null;
    switch (e.key) {
      case "ArrowRight": target = addDays(date, -back); break;
      case "ArrowLeft": target = addDays(date, back); break;
      case "ArrowDown": target = addDays(date, 7); break;
      case "ArrowUp": target = addDays(date, -7); break;
      case "Home":
      case "End":
        row = td.parentElement.querySelectorAll("td.gk-cal-day");
        target = dateOf(row[e.key === "Home" ? 0 : row.length - 1]);
        break;
      case "PageUp":
      case "PageDown":
        target = addMonths(date, (e.key === "PageUp" ? -1 : 1) * (e.shiftKey ? 12 : 1));
        e.preventDefault();
        go(st, target.slice(0, 7), target);
        return;
      case "Enter":
      case " ":
        e.preventDefault();
        if (select(st, date, null) && e.key === "Enter") {
          var link = td.querySelector(".gk-cal-daylink");
          if (link) link.click();
        }
        return;
      default: return;
    }
    e.preventDefault();
    if (target && target !== date) moveTo(st, target);
  }

  function closeMore(st, except) {
    Array.prototype.forEach.call(st.root.querySelectorAll("details.gk-cal-more[open]"), function (d) {
      if (d !== except) d.open = false;
    });
  }

  function setupCal(root) {
    if (cals.has(root)) return cals.get(root);
    var st = {
      root: root,
      id: root.getAttribute("data-gk-cal") || "",
      month: root.getAttribute("data-gk-cal-month") || "",
      today: root.getAttribute("data-gk-cal-today") || "",
      urlTemplate: root.getAttribute("data-gk-cal-url") || "",
      table: root.querySelector(".gk-cal-grid"),
      active: null
    };
    cals.set(root, st);
    if (!st.table) return st;

    st.table.setAttribute("role", "grid");
    cellsOf(st).forEach(function (td) { td.setAttribute("role", "gridcell"); });
    // An event without a link becomes a button: it is chosen with the mouse and the keyboard alike. In the
    // grid the roving tab stop decides whether it is reached; in the agenda it is always.
    Array.prototype.forEach.call(root.querySelectorAll("span.gk-cal-event"), function (el) {
      el.setAttribute("role", "button");
      if (!el.closest(".gk-cal-grid")) el.tabIndex = 0;
    });

    var start = null;
    try {
      var saved = sessionStorage.getItem(FOCUS_KEY + st.id);
      if (saved) { sessionStorage.removeItem(FOCUS_KEY + st.id); start = cellFor(st, saved); }
    } catch (e) { /* no storage */ }
    var first = start || cellFor(st, st.today) || cellFor(st, st.month + "-01") || cellsOf(st)[0];
    rove(st, first);
    if (start) start.focus();

    st.table.addEventListener("keydown", function (e) { onKey(st, e); });
    st.table.addEventListener("focusin", function (e) {
      var td = e.target.closest ? e.target.closest("td.gk-cal-day") : null;
      if (td && td !== st.active) rove(st, td);
    });
    st.table.addEventListener("click", function (e) {
      var td = e.target.closest ? e.target.closest("td.gk-cal-day") : null;
      if (!td) return;
      // A link, an event, "+n more" and the open list do what they do; the rest of the day chooses the day.
      if (e.target.closest("a[href], summary, .gk-cal-more-list, .gk-cal-event")) return;
      focusDay(st, td);
      select(st, dateOf(td), null);
    });
    // An event that is a button, in the grid or the agenda: a click, Enter and Space choose it.
    root.addEventListener("click", function (e) {
      var ev = e.target.closest ? e.target.closest(".gk-cal-event[role=\"button\"]") : null;
      if (!ev || !root.contains(ev)) return;
      var td = ev.closest("td.gk-cal-day");
      if (td) rove(st, td);
      select(st, dayOfEvent(ev), ev);
    });
    root.addEventListener("keydown", function (e) {
      var ev = e.target.closest ? e.target.closest(".gk-cal-event[role=\"button\"]") : null;
      if (!ev || (e.key !== "Enter" && e.key !== " ")) return;
      e.preventDefault();
      e.stopPropagation();
      select(st, dayOfEvent(ev), ev);
    });

    // "+n more": one open at a time; Escape closes it and gives the focus back to it.
    root.addEventListener("toggle", function (e) {
      if (e.target.matches && e.target.matches("details.gk-cal-more") && e.target.open) closeMore(st, e.target);
    }, true);
    root.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var open = e.target.closest ? e.target.closest("details.gk-cal-more[open]") : null;
      if (!open) return;
      e.preventDefault();
      open.open = false;
      var s = open.querySelector("summary");
      if (s) s.focus();
    });

    // The month links: the page may take the move over (gk:calendarnav); Today in this month only
    // moves the focus.
    Array.prototype.forEach.call(root.querySelectorAll("a[data-gk-cal-go]"), function (a) {
      a.addEventListener("click", function (e) {
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var month = a.getAttribute("data-gk-cal-go");
        if (month === st.month && a.classList.contains("gk-cal-goto-today")) {
          e.preventDefault();
          focusDay(st, cellFor(st, st.today));
          return;
        }
        if (!fire(root, "gk:calendarnav", { calendar: st.id, month: month, url: a.href, date: null }, true)) e.preventDefault();
      });
    });
    root.setAttribute("data-gk-cal-ready", "");
    return st;
  }

  document.addEventListener("click", function (e) {
    if (e.target.closest && e.target.closest("details.gk-cal-more")) return;
    Array.prototype.forEach.call(document.querySelectorAll("details.gk-cal-more[open]"), function (d) { d.open = false; });
  });

  function getCal(el) {
    var root = el;
    if (typeof el === "string") {
      root = null;
      document.querySelectorAll(CAL).forEach(function (r) { if (!root && r.getAttribute("data-gk-cal") === el) root = r; });
      if (!root) root = document.getElementById(el.replace(/^#/, ""));
      if (!root) { try { root = document.querySelector(el); } catch (e) { root = null; } }
    }
    if (root && root.closest) root = root.closest(CAL);
    return root ? setupCal(root) : null;
  }

  /* ── The API ────────────────────────────────────────────────────────── */

  window.GK = window.GK || {};
  window.GK.dateRange = {
    // { from, to, preset } of a picker (its element, its id, the name it posts under, or a selector).
    get: function (el) {
      var st = getRange(el);
      if (!st || !st.from) return null;
      return { from: st.from.value, to: st.to.value, preset: st.select ? st.select.value : "custom" };
    },
    // A preset key, or { from, to } (custom). Fires no event: it is the page's own doing.
    set: function (el, value) {
      var st = getRange(el);
      if (!st || !st.from) return null;
      if (typeof value === "string") {
        var r = clamp(st, presetRange(value, st.cfg.today));
        if (!r) return api.get(el);
        st.from.value = r.from;
        st.to.value = r.to;
        if (st.select) st.select.value = st.select.querySelector('option[value="' + value + '"]') ? value : "custom";
      } else if (value && parse(value.from) && parse(value.to)) {
        var c = clamp(st, value.to < value.from ? { from: value.to, to: value.from } : value);
        st.from.value = c.from;
        st.to.value = c.to;
        if (st.select && hasCustom(st)) st.select.value = "custom";
      }
      update(st);
      return api.get(el);
    },
    presetRange: function (key, today) { return presetRange(key, today); },
    // The period in words, with the words of a picker on the page (or English).
    text: function (from, to, el) {
      var st = el ? getRange(el) : (document.querySelector(RANGE) ? getRange(document.querySelector(RANGE)) : null);
      return text(from, to, st ? st.cfg : null);
    }
  };
  var api = window.GK.dateRange;

  window.GK.calendar = {
    // The month shown, 'Y-m'.
    month: function (el) { var st = getCal(el); return st ? st.month : null; },
    // Moves the focus to a day of the grid; false when the grid does not show it.
    focus: function (el, date) {
      var st = getCal(el);
      if (!st || !st.table) return false;
      return focusDay(st, cellFor(st, String(date)));
    },
    // Loads another month ('Y-m'), after gk:calendarnav; false when it was cancelled or is no month.
    go: function (el, month) {
      var st = getCal(el);
      return st ? go(st, String(month), null) : false;
    },
    // The events of a day: [{ id, title, href, start, end }].
    events: function (el, date) {
      var st = getCal(el);
      return st && st.table ? eventsOf(st, String(date)) : [];
    }
  };

  /* ── Starting ───────────────────────────────────────────────────────── */

  function scan(node) {
    if (node.nodeType !== 1) return;
    if (node.matches(RANGE)) setupRange(node);
    if (node.matches(CAL)) setupCal(node);
    node.querySelectorAll(RANGE).forEach(setupRange);
    node.querySelectorAll(CAL).forEach(setupCal);
  }
  function start() {
    scan(document.documentElement);
    // A picker or a calendar that arrives later — a fragment, a modal, AJAX navigation — is set up too.
    new MutationObserver(function (records) {
      records.forEach(function (r) { r.addedNodes.forEach(scan); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
