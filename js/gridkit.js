/* GridKit JS v1.0.0 – Vanilla, zero dependencies */
(function () {
  "use strict";

  // i18n — load from window.GK_LANG (set by Lang::jsConfig()) or use defaults
  const _lang = window.GK_LANG || {};
  function _t(key, params) {
    var text = _lang[key] || key;
    if (params) {
      for (var k in params) {
        text = text.replace("{" + k + "}", params[k]);
      }
    }
    return text;
  }

  /*
   * Numbers, built the way the server builds them: the separators come out of
   * the catalogue (format.decimal / format.thousands), not out of a locale
   * name. A cell the browser redraws after a sort then matches the one PHP
   * rendered, character for character. Before this both sides were hardcoded to
   * "de-DE", so an English table flipped "€1,200.00" to "1.200,00 €" on the
   * first click. With no catalogue loaded the English shape is the floor, which
   * is the same fallback Lang::t() uses.
   */
  /*
   * HTML-escape, at module scope because two places need it and only one
   * had it: renderStatic declared its own `e` as a local const, so the
   * pager it calls threw ReferenceError the moment that pager tried to
   * escape a label. One implementation, reachable from both.
   */
  function _gkEsc(s) {
    const d = document.createElement("div");
    d.textContent = String(s == null ? "" : s);
    // textContent escapes < > and &, but NOT quotes — and every attribute this
    // file writes is quoted. A row value holding an apostrophe closed
    // data-gk-params='…' and everything after it became attributes of the
    // element: onmouseover and all. htmlspecialchars(ENT_QUOTES) on the server
    // has always escaped both; this side did not.
    return d.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  /*
   * Is this a target we are willing to link to? An allow list, not a deny list —
   * "java\tscript:" defeated the latter, because the browser strips control
   * characters before it reads the scheme. Table::safeTarget() in PHP is the
   * same rule; keep them in step.
   */
  /*
   * A row value the way PHP casts it to a string: (string) false is "", true is
   * "1", null is "". JavaScript's own String() writes "false" and "true", which
   * made a boolean column render differently on each side.
   */
  function _gkAlsText(v) {
    if (v === false) return "";
    if (v === true) return "1";
    return String(v == null ? "" : v);
  }

  function _gkSafeTarget(href) {
    var rein = String(href).replace(/[\u0000-\u0020]/g, "");
    if (rein === "" || rein.indexOf("//") === 0) return null;
    if (/^(\/|\.\/|\.\.\/|\?|#)/.test(rein)) return rein;
    if (/^(https?|mailto|tel):/i.test(rein)) return rein;
    return rein.indexOf(":") > -1 ? null : rein;
  }

  /*
   * A target template filled from the row and held against the allow list —
   * Table::fillTarget() in PHP, and shared the same way: row buttons, linked
   * cells and row links. encodeURIComponent leaves !'()* alone where PHP's
   * rawurlencode escapes them, so those five are escaped here, or the two
   * renderers drift on any value carrying one of them. A lone surrogate makes
   * encodeURIComponent throw, and this runs in the middle of drawing a table:
   * one bad character in one cell drops that link, not the whole table.
   */
  function _gkFillTarget(template, row) {
    try {
      return _gkSafeTarget(String(template).replace(/\{(\w+)\}/g, function (_, k) {
        return encodeURIComponent(String(row[k] ?? "")).replace(/[!'()*]/g, function (c) {
          return "%" + c.charCodeAt(0).toString(16).toUpperCase();
        });
      }));
    } catch (err) {
      return null;
    }
  }

  /*
   * A list column's priority as its class — gk-col-p2 … gk-col-p5, or nothing.
   * Table::column() has already reduced 'priority' to an int in that range or
   * dropped it, so this only has to write what arrived; the range check keeps a
   * hand-built data block from inventing a class no rule answers.
   * Twin of the two lines in Table::render() that write the same class.
   */
  function _gkPriorityClass(col) {
    const p = col.priority;
    return Number.isInteger(p) && p >= 2 && p <= 5 ? "gk-col-p" + p : "";
  }

  /*
   * What a row control carries in data-gk-params — Table::rowParams() in PHP:
   * the mapped fields, plus the row's own id unless the map names one. The
   * client once sent "{}" after a sort, and an edit modal came up as if for a
   * new record.
   */
  function _gkRowParams(map, row) {
    var params = {};
    Object.keys(map || {}).forEach(function (pk) { params[pk] = row[map[pk]] ?? ""; });
    if (!Object.prototype.hasOwnProperty.call(params, "id") && row.id !== undefined) params.id = row.id;
    return params;
  }

  /*
   * What a list showed, kept in the history entry (1.128.0). Search for "LoA", open a row, press
   * Back: the browser put "LoA" back into the search box (form restoration) but the rows were drawn
   * without it — every row again, under a box that claimed a filter. A setData() table and a
   * RowPager started from an empty query and never read the box. They now write what they show
   * (search, filters, page) into history.state under "gkTable:<id>" and read it back when they are
   * bound: Back shows the list as it was left, a fresh visit (a new history entry) starts clean.
   * Other keys in history.state (GK.navigate's gkNav) are kept. Safari throttles replaceState:
   * a refused write costs the memory, never the list.
   */
  // A date cell's markup (1.128.0): the shown text, the ISO value in datetime — local time, like
  // the text beside it. An unreadable date stays the plain text it always was.
  function _gkTimeTag(d, shown, withTime) {
    if (isNaN(d.getTime())) return shown;
    const p = (n) => String(n).padStart(2, "0");
    const iso = d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) +
      (withTime ? "T" + p(d.getHours()) + ":" + p(d.getMinutes()) : "");
    return '<time datetime="' + iso + '">' + shown + "</time>";
  }
  var _gkBack = {
    read: function (id) {
      try {
        var s = window.history.state;
        var v = s && typeof s === "object" ? s["gkTable:" + id] : null;
        return v && typeof v === "object" ? v : null;
      } catch (e) { return null; }
    },
    write: function (id, value) {
      try {
        var s = window.history.state;
        var next = s && typeof s === "object" && !Array.isArray(s) ? Object.assign({}, s) : {};
        next["gkTable:" + id] = value;
        window.history.replaceState(next, "");
      } catch (e) { /* throttled or refused: the list stays as it is */ }
    },
  };

  /*
   * Out-of-band updates: a <template data-gk-replace="css-selector"> inside the
   * fresh markup replaces an element OUTSIDE it — the summary cards above a
   * table, a status select beside it, the pager below. Both paths that swap
   * markup use this: the AJAX table (GK.table) and the live table, which could
   * not do it at all until 1.86.0. The SSI Panel carried the loop for the live
   * table in its own layout for that reason.
   *
   * Returns true when something was replaced, so the caller can re-bind the
   * widgets that came with the new markup.
   */
  function _gkApplyReplacements(root) {
    var wurzel = root || document;
    var ersetzt = false;
    var gesehen = {};
    wurzel.querySelectorAll("template[data-gk-replace]").forEach(function (tpl) {
      var sel = tpl.getAttribute("data-gk-replace");
      // Per template, so a typo in one view costs one replacement and not the
      // whole reload: this runs inside the live table's promise chain, where a
      // throw would swallow the event, the URL update and the re-binding.
      try {
        if (gesehen[sel] && window.console) {
          console.warn("GridKit: two templates replace " + sel + " — the last one wins.");
        }
        gesehen[sel] = true;
        var target = sel ? document.querySelector(sel) : null;
        // A template is meant for something OUTSIDE the markup being swapped.
        // Pointing it at the container detaches the very node the caller still
        // holds: the event then reaches nobody and the fresh rows stay unbound.
        if (target && (target === wurzel || (wurzel.contains && wurzel.contains(target)))) {
          if (window.console) console.warn("GridKit: " + sel + " points inside the swapped markup — skipped.");
          tpl.remove();
          return;
        }
        // No element in the template means "nothing to put here" — leaving the
        // target alone is the safe reading. Writing an empty string would delete
        // it for good, and only a full page load would bring it back.
        if (target && tpl.content && tpl.content.firstElementChild) {
          // The focus would go with the old node. A page filters by keyboard
          // through exactly such a replaced select, and losing it drops the user
          // at the top of the page. The control is found again by its id — the
          // same control, not merely the first one that can take focus.
          var aktiv = document.activeElement;
          var fokusId = target.contains(aktiv) && aktiv.id ? aktiv.id : null;
          var div = document.createElement("div");
          div.appendChild(tpl.content.cloneNode(true));
          target.outerHTML = div.innerHTML;
          if (fokusId) {
            var wieder = document.getElementById(fokusId);
            if (wieder && wieder.focus) {
              try { wieder.focus({ preventScroll: true }); } catch (e) { wieder.focus(); }
            }
          }
          ersetzt = true;
        }
      } catch (e) {
        if (window.console) console.error("GridKit: replacing " + sel + " failed", e);
      }
      tpl.remove();
    });
    return ersetzt;
  }

  function _gkNumber(value, decimals) {
    var n = parseFloat(value);
    if (isNaN(n)) return value == null ? "" : String(value);
    var dec = _lang.format_decimal || ".";
    var tho = _lang.format_thousands || ",";
    var parts = Math.abs(n).toFixed(decimals).split(".");
    // No "-0,00": the sign stays only while a digit other than zero is left —
    // what PHP's number_format() has done since 8.0.
    var neg = n < 0 && /[1-9]/.test(parts.join(""));
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, tho);
    return (neg ? "-" : "") + parts[0] + (parts[1] ? dec + parts[1] : "");
  }

  /*
   * The currency template carries the symbol AND the side it sits on —
   * "€{value}" in English, "{value} €" in German — so there is no separate rule
   * here about where the symbol belongs.
   */
  function _gkCurrency(value) {
    return (_lang.format_currency || "€{value}").replace(
      "{value}",
      _gkNumber(value || 0, 2),
    );
  }

  /*
   * Focus handling for anything that lies on top of the page.
   *
   * These lived as methods on GK.modal, which was fine while the modal was the
   * only overlay in the library. It is not: the lightbox is a full-screen
   * dialog too, and it had none of this — open an image and your focus stayed
   * on the page underneath, tabbing through controls hidden behind the
   * picture. Rather than a second copy that would drift from the first, one
   * set here and both overlays use it.
   */

  /** Everything inside `root` a keyboard can reach, in document order. */
  function _gkFocusable(root) {
    return Array.prototype.filter.call(
      root.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]),' +
          " select:not([disabled]), textarea:not([disabled]), [tabindex]",
      ),
      function (el) {
        return (
          el.getAttribute("tabindex") !== "-1" &&
          el.offsetParent !== null &&
          !el.closest('[aria-hidden="true"]')
        );
      },
    );
  }

  /*
   * Tab must not leave the dialog. Without this the focus ring walked out of
   * the overlay into the page behind it — which the overlay covers, so the
   * user was tabbing through controls they could neither see nor click, with
   * no way back except Escape.
   */
  function _gkTrap(ov, e) {
    if (e.key !== "Tab") return;
    var items = _gkFocusable(ov);
    if (!items.length) {
      e.preventDefault();
      return;
    }
    var first = items[0];
    var last = items[items.length - 1];
    // The focus can sit on something inside that is not in the tab order — a
    // side sheet starts on its title. From there Shift+Tab walked straight out
    // of the dialog, because only the first and the last control were checked.
    // Wrap only when no control lies that way; otherwise the browser's own
    // order is right.
    var cur = document.activeElement;
    if (items.indexOf(cur) === -1) {
      var way = e.shiftKey ? Node.DOCUMENT_POSITION_PRECEDING : Node.DOCUMENT_POSITION_FOLLOWING;
      var ahead = items.some(function (el) { return (cur.compareDocumentPosition(el) & way) !== 0; });
      if (!ahead) {
        e.preventDefault();
        (e.shiftKey ? last : first).focus();
      }
      return;
    }
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  /*
   * Back to whatever opened the overlay — but only if it is still on the page
   * and still focusable; a row button whose table has reloaded is not. Left
   * out, the focus ring stays on a control inside a dialog that is no longer
   * on screen and the next Tab restarts at the top of the document.
   *
   * The modal and the lightbox both need this and both had written it out;
   * the wording differed already (isConnected here, document.contains there),
   * which is how two copies begin to answer differently.
   */
  function _gkRestoreFocus(el) {
    if (!el || !el.isConnected || typeof el.focus !== "function") return;
    try {
      el.focus({ preventScroll: true });
    } catch (err) {
      el.focus();
    }
  }

  /*
   * Focus starts inside the dialog, on the first thing that can take it. It
   * used to stay on whatever opened it, which sits behind the overlay: press
   * Tab and you were walking the page underneath.
   */
  function _gkFocusInto(ov, fallbackSelector) {
    var items = _gkFocusable(ov);
    var target = items[0] || (fallbackSelector ? ov.querySelector(fallbackSelector) : ov);
    if (!target) return;
    if (!items.length && !target.hasAttribute("tabindex")) {
      target.setAttribute("tabindex", "-1");
    }
    try {
      target.focus({ preventScroll: true });
    } catch (err) {
      target.focus();
    }
  }

  /*
   * A close button with nothing to say gets a name. A hand-written &times; was
   * read as "multiplication sign". A button that SAYS something keeps its own
   * words: an aria-label differing from the visible text breaks voice control
   * (WCAG 2.5.3) — only a bare cross or an icon ligature has nothing to say.
   * The static modal and the side sheet both need this; one copy, not two.
   */
  function _gkNameCloseButton(btn) {
    if (btn.hasAttribute("aria-label") || btn.hasAttribute("title")) return;
    var text = btn.cloneNode(true);
    text.querySelectorAll(".material-icons, [aria-hidden=true]").forEach(function (i) { i.remove(); });
    var words = text.textContent.replace(/[×✕✖]/g, "").trim();
    if (words !== "") return;
    btn.setAttribute("aria-label", _t("close"));
  }

  // A percentage as Table::percent() writes it: digits as given, the locale's
  // decimal sign, decimals when asked, a space before the sign. parseInt() used
  // to cut "12.5" to "12%" on the first sort while the card above said "12,5 %".
  // Nothing, a placeholder without a digit ("–") and a value that already ends
  // in % come back as they are; text with a digit that is not a number ("12,5")
  // only gets the sign. Halves: toFixed() and number_format() can round an
  // exact .5 differently once binary floats are involved — a known hairline.
  function _gkPercent(val, decimals) {
    var s = val == null ? "" : String(val).trim();
    if (s === "" || s.slice(-1) === "%" || !/\d/.test(s)) return s;
    if (!/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(s)) return s + " %";
    if (decimals !== undefined && decimals !== null && decimals !== "") {
      return _gkNumber(s, parseInt(decimals, 10) || 0) + " %";
    }
    return s.replace(".", _lang.format_decimal || ".") + " %";
  }

  // Where a header cell stands: an explicit align wins, a number or currency
  // column is right-aligned without one. Table::headerAlignClass() in PHP is the
  // same rule — keep them in step, or the head jumps on the first sort.
  function thAlignClass(col) {
    var align = col.align || "";
    if (align === "right") return "gk-text-right";
    if (align === "center") return "gk-text-center";
    if (align) return "";
    return col.format === "number" || col.format === "currency" ? "gk-td-num" : "";
  }

  // Is something open ON TOP of the modal? Each of these closes itself on
  // Escape through a listener of its own; the modal must then stay. Only layers
  // whose listener is registered AFTER the modal's belong here — they still
  // stand when this is asked. The header dropdown's listener is older: it has
  // already closed by then, so it claims the key with preventDefault instead.
  function _gkLayerAbove() {
    return !!document.querySelector(
      '.gk-confirm-overlay, .gk-lightbox.open, #gk-beleg-modal.is-open, .gk-search-overlay',
    );
  }

  const GK = {
    // === MODAL ===
    modal: {
      stack: [],
      init() {
        document.addEventListener("keydown", (e) => {
          // It used to ask only "is a modal open?": Escape in a searchable select
          // closed the list AND the modal with everything typed into it, and
          // Escape on a GK.confirm over a modal answered "cancel" and took the
          // modal along. defaultPrevented covers widgets inside the modal that
          // handled the key themselves; the layer check covers what lies above.
          if (e.key !== "Escape" || !this.stack.length) return;
          if (e.defaultPrevented || _gkLayerAbove()) return;
          this.close();
        });
      },
      _createOverlay() {
        // Unique per overlay: modals stack, and two dialogs sharing a title id
        // would both point aria-labelledby at whichever came first.
        var titleId = "gk-modal-title-" + (this._seq = (this._seq || 0) + 1);
        var ov = document.createElement("div");
        // gk-modal-open marks an overlay that is on screen — the one hook a
        // page's own CSS can use ("is a modal open?") whichever way the overlay
        // is hidden. A dynamic overlay exists only while it is open.
        ov.className = "gk-modal-overlay gk-modal-open";
        ov._gkDynamic = true;   // show() must refuse one of ours: close() has to remove it
        ov.style.zIndex = 9000 + this.stack.length * 10;
        ov.innerHTML =
          // role and aria-modal: without them this is a div lying on top of
          // the page. A screen reader's virtual cursor walked straight past it
          // into the content behind, which is still there and still readable.
          // aria-modal says everything else is out of bounds while this is
          // open; aria-labelledby is what gives the dialog a name at all.
          '<div class="gk-modal" data-gk-modal-container role="dialog"' +
          ' aria-modal="true" aria-labelledby="' + titleId + '">' +
          '<div class="gk-modal-header"><h3 class="gk-modal-title" id="' + titleId +
          '" data-gk-modal-title-el></h3>' +
          // A screen reader read this button as "multiplication sign".
          '<button class="gk-modal-close" data-gk-modal-close aria-label="' +
          _t("close") +
          '"><span aria-hidden="true">&times;</span></button></div>' +
          '<div class="gk-modal-body" data-gk-modal-body></div></div>';
        ov.querySelector("[data-gk-modal-close]").addEventListener(
          "click",
          () => this.close(),
        );
        ov.addEventListener("click", (e) => {
          if (e.target === ov) this.close();
        });
        document.body.appendChild(ov);
        return ov;
      },
      /**
       * Hand-written modals get what _createOverlay() builds in.
       *
       * Many pages write `<div class="gk-modal-overlay"><div class="gk-modal">`
       * themselves and show it with style.display. They got none of the above:
       * the close button — usually a bare &times; — was read as "multiplication
       * sign", and nothing said a dialog had opened. Idempotent; only fills in
       * what the page left out. No aria-modal: these dialogs do not take the
       * focus, and declaring the rest of the page out of bounds while the focus
       * is still in it strands screen reader users.
       */
      upgradeStatic(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll(".gk-modal-close").forEach(_gkNameCloseButton);
        scope.querySelectorAll(".gk-modal-overlay > .gk-modal").forEach(function (box) {
          if (box.hasAttribute("role")) return;
          // The name first. A dialog without one is worse than the neutral div
          // it was: the role announces "dialog" and then has nothing to add.
          var named = box.hasAttribute("aria-label") || box.hasAttribute("aria-labelledby");
          if (!named) {
            // Pages put their heading in a header of their own making as often
            // as in .gk-modal-header — so any heading inside counts.
            var heading = box.querySelector(".gk-modal-title, .gk-modal-header h1, .gk-modal-header h2, .gk-modal-header h3, .gk-modal-header h4")
              || box.querySelector("h1, h2, h3, h4, h5, h6");
            if (!heading) return;
            if (!heading.id) heading.id = "gk-static-modal-title-" + (GK.modal._seq = (GK.modal._seq || 0) + 1);
            box.setAttribute("aria-labelledby", heading.id);
          }
          box.setAttribute("role", "dialog");
        });
      },
      /** Everything inside `root` a keyboard can reach, in document order. */
      _focusable(root) {
        return _gkFocusable(root);
      },

      /*
       * Tab must not leave the dialog. Without this the focus ring walked out
       * of the modal into the page behind it — which the overlay covers, so the
       * user was tabbing through controls they could neither see nor click,
       * with no way back except Escape.
       */
      _trap(ov, e) {
        _gkTrap(ov, e);
      },

      /*
       * Focus starts inside the dialog, on the first thing that can take it —
       * the close button, while the body is still loading. It used to stay on
       * whatever opened the modal, which sits behind the overlay: press Tab and
       * you were walking the page underneath.
       */
      _focusInto(ov) {
        _gkFocusInto(ov, "[data-gk-modal-container]");
      },

      open(title, url, params, size) {
        // Remembered so close() can put the caret back where it came from.
        // Losing it drops focus to the top of the document, and a keyboard user
        // has to cross the whole page again to reach the row they were on.
        var opener =
          document.activeElement && document.activeElement !== document.body
            ? document.activeElement
            : null;
        var ov = this._createOverlay();
        ov._gkOpener = opener;
        ov.addEventListener("keydown", this._trap.bind(this, ov));
        var container = ov.querySelector("[data-gk-modal-container]");
        var titleEl = ov.querySelector("[data-gk-modal-title-el]");
        var body = ov.querySelector("[data-gk-modal-body]");
        titleEl.textContent = title;
        container.className = "gk-modal gk-modal-" + (size || "medium");
        body.innerHTML = "";
        body.classList.add("gk-loading");
        this.stack.push(ov);
        this._focusInto(ov);

        var fd = new FormData();
        if (params) Object.entries(params).forEach(([k, v]) => fd.append(k, v));

        fetch(url, {
          method: "POST",
          body: fd,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        })
          .then((r) => {
            // The body of a 500 or of a firewall's 403 is not the form.
            if (!r.ok) throw new Error("HTTP " + r.status);
            return r.text();
          })
          .then((html) => {
            body.classList.remove("gk-loading");
            body.innerHTML = html;
            // Modal content is injected after DOMContentLoaded, so every widget
            // binder that GK.init() ran for the page runs again here — the one
            // list, not a hand-written subset (which had no tabs, no row pager
            // and no accordion until 1.82.0).
            GK.initContent(body);
            // The body arrives after the frame, so focus lands on the close
            // button first and moves on to the real first field once one exists.
            if (!body.contains(document.activeElement)) GK.modal._focusInto(ov);
          })
          .catch(() => {
            body.classList.remove("gk-loading");
            body.innerHTML =
              '<p style="color:var(--gk-danger)">' +
              _t("error_loading", {}) +
              "</p>";
          });
      },
      close() {
        if (!this.stack.length) return;
        var ov = this.stack.pop();
        // A static overlay belongs to the page, not to us. Taking it out of the
        // DOM — which is right for one we built ourselves — would mean it never
        // opens again; hide it instead.
        if (ov._gkStatic) return this._hideStatic(ov);
        var opener = ov._gkOpener;
        ov.remove();
        _gkRestoreFocus(opener);
      },
      closeAll() {
        while (this.stack.length) this.close();
      },

      /*
       * A modal whose markup already stands on the page. open() builds its
       * overlay and removes it again; show() only switches one that is already
       * there — two jobs, two methods, so neither has to guess. Pages used to
       * write this themselves: the SSI Panel alone had 83 overlays, none with a
       * dialog role or a focus trap, most of them hidden with an inline style.
       *
       *   GK.modal.show('#reg-overlay', { focus: '#reg-name', onClose: fn })
       *   GK.modal.hide('#reg-overlay')   // or hide() for the topmost one
       *
       * What comes with it: the dialog role and a name (upgradeStatic), the
       * focus trap, Escape through the one document listener — including its
       * deference to a confirm or an open list above it — the backdrop click,
       * every close button inside, and the focus handed back to whatever opened
       * it. Hiding uses the hidden attribute, so the page needs no CSS of its own.
       */
      show(target, opts) {
        var ov = typeof target === "string" ? document.querySelector(target) : target;
        if (!ov || !ov.classList || !ov.classList.contains("gk-modal-overlay")) return null;
        // One we built ourselves belongs to close(), which has to REMOVE it.
        // Marking it static would leave it hidden in the DOM for good.
        if (ov._gkDynamic) return null;
        opts = opts || {};
        var offen = ov.classList.contains("gk-modal-open");
        // Without the stack the document's Escape listener ignores it (it asks
        // "is anything open?" first), and a second show() would stack it twice.
        if (this.stack.indexOf(ov) === -1) this.stack.push(ov);
        ov._gkStatic = true;
        ov._gkOnClose = typeof opts.onClose === "function" ? opts.onClose : null;
        if (!offen) {
          // Only on the way in, and never something inside the dialog: a second
          // show() while it is open would make a field in it "what opened it".
          var active = document.activeElement;
          ov._gkOpener = active && active !== document.body && !ov.contains(active) ? active : null;
        }
        this.upgradeStatic(ov);
        var box = ov.querySelector(".gk-modal");
        // aria-modal only here, never in upgradeStatic: it declares the rest of
        // the page out of bounds, which is only true while the dialog holds the
        // focus. An overlay that upgradeStatic merely named does not. Taken off
        // again in _hideStatic.
        if (box) box.setAttribute("aria-modal", "true");
        // Guards against stacking listeners on every show(). Note for whoever
        // changes this: removing the guard is NOT visible in behaviour — hide()
        // is idempotent and the trap does the same thing twice — so no browser
        // case can catch it. It is a leak, not a bug in what the user sees.
        if (!ov._gkStaticBound) {
          ov._gkStaticBound = true;
          ov.addEventListener("keydown", this._trap.bind(this, ov));
          ov.addEventListener("click", function (e) {
            var t = e.target;
            if (t === ov) return GK.modal.hide(ov);              // the backdrop
            var hit = t.closest && t.closest(".gk-modal-close, [data-gk-modal-close]");
            // Only a close button of THIS overlay: a modal inside a modal, or a
            // page button that happens to carry the class, must not close it.
            if (hit && hit.closest(".gk-modal-overlay") === ov) GK.modal.hide(ov);
          });
        }
        ov.removeAttribute("hidden");
        ov.style.display = "";   // pages hid it with an inline style; CSS alone cannot beat that
        ov.classList.add("gk-modal-open");
        // Same ladder open() uses, so the stack order is the order on screen —
        // a dynamic modal opened over this one has to lie in front of it.
        ov.style.zIndex = 9000 + (this.stack.length - 1) * 10;
        // A page may hide its overlay with a class of its own, which none of the
        // above clears. Trapping the focus in something nobody can see leaves
        // the next Escape answering a dialog the user cannot even find.
        var sichtbar = typeof ov.checkVisibility === "function"
          ? ov.checkVisibility()
          : getComputedStyle(ov).display !== "none";
        if (!sichtbar) {
          this.hide(ov);
          if (window.console) {
            console.warn("GK.modal.show: " + (ov.id ? "#" + ov.id : "the overlay") +
              " stays invisible — a class of the page's own is hiding it. Remove it, or hide the modal with the hidden attribute.");
          }
          return null;
        }
        var first = opts.focus
          ? typeof opts.focus === "string" ? ov.querySelector(opts.focus) : opts.focus
          : null;
        if (first && first.focus) {
          try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
        } else {
          this._focusInto(ov);
        }
        return ov;
      },

      /** Close a static modal. Without an argument: the topmost open one. */
      hide(target) {
        var ov = target
          ? typeof target === "string" ? document.querySelector(target) : target
          : this.stack[this.stack.length - 1];
        if (!ov || !ov.classList) return;
        // hide() on one of ours means close(): it has to leave the DOM.
        if (!ov._gkStatic) return this.close();
        var i = this.stack.indexOf(ov);
        // Nothing to close — and nothing to announce. onClose used to run on
        // every call, so a second way out fired the page's reload twice.
        if (i === -1 && !ov.classList.contains("gk-modal-open")) return;
        if (i > -1) this.stack.splice(i, 1);
        this._hideStatic(ov);
      },

      _hideStatic(ov) {
        ov.classList.remove("gk-modal-open");
        ov.setAttribute("hidden", "hidden");
        var box = ov.querySelector(".gk-modal");
        if (box) box.removeAttribute("aria-modal");   // only true while it holds the focus
        // Only when the focus is actually in here: closing a modal underneath an
        // open one would otherwise pull the caret out behind the open dialog.
        if (ov.contains(document.activeElement)) _gkRestoreFocus(ov._gkOpener);
        var cb = ov._gkOnClose;
        ov._gkOnClose = null;   // one close, one call
        if (typeof cb === "function") cb();
      },
    },

    // === FORM AJAX ===
    form: {
      bind(root) {
        root.querySelectorAll("form[data-gk-ajax]").forEach((form) => {
          if (form._gkBound) return;
          form._gkBound = true;
          form.addEventListener("submit", (e) => {
            e.preventDefault();
            this.submit(form);
          });
        });
      },
      submit(form) {
        form
          .querySelectorAll(".gk-field-error")
          .forEach((el) => (el.textContent = ""));
        form.querySelectorAll(".gk-has-error").forEach((el) => {
          el.classList.remove("gk-has-error");
          // The class was the only thing cleared, so a field that had failed
          // once stayed aria-invalid for the rest of the page's life — telling
          // a screen reader it was still wrong long after it had been fixed.
          el.removeAttribute("aria-invalid");
        });

        const btn = form.querySelector('[type="submit"]');
        if (btn) {
          btn.disabled = true;
          // textContent flattens the button: the Material Icons glyph and
          // the label became the single string "saveSave", permanently,
          // from the first submit onwards. innerHTML puts back what was
          // there. Safe: it is the element's own already-parsed markup,
          // read and written back, so nothing new enters.
          btn._origHTML = btn.innerHTML;
          btn.textContent = "…";
        }

        fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          headers: { "X-Requested-With": "XMLHttpRequest" },
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.ok) {
              // A form in a side sheet closes that sheet — not whatever modal
              // happens to be on top of the stack, which may belong to
              // something else entirely. The nearest container decides.
              const box = form.closest(".gk-modal-overlay, .gk-sheet");
              if (box && box.classList.contains("gk-sheet")) GK.sheet.close(box);
              else GK.modal.close();
              GK.table.refreshAll();
              // The skill has promised this toast since 1.10; nothing showed it.
              if (data.message) GK.toast.success(data.message);
            } else if (data.errors) {
              Object.entries(data.errors).forEach(([field, msg]) => {
                const errEl = form.querySelector(`[data-gk-error="${field}"]`);
                if (errEl) errEl.textContent = msg;
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                  input.classList.add("gk-has-error");
                  // The class turns the border red, which used to be the whole
                  // of what a failed field communicated. aria-invalid says it
                  // to a screen reader; the message itself is announced by the
                  // role=alert on the container the field already points at.
                  input.setAttribute("aria-invalid", "true");
                }
              });
              // Move to the first field that failed. Without this the messages
              // appear somewhere below and the caret stays where it was, which
              // on a long form is off-screen from the thing needing attention.
              const firstBad = form.querySelector(".gk-has-error");
              if (firstBad && typeof firstBad.focus === "function") {
                firstBad.focus();
              }
            } else {
              // {ok:false, error:"…"} used to change nothing on screen: the
              // button came back, and neither that nor why it failed was said.
              GK.toast.error(data.error || data.message || _t("error_saving"));
            }
          })
          .catch(() => GK.toast.error(_t("error_saving")))
          .finally(() => {
            if (btn) {
              btn.disabled = false;
              btn.innerHTML = btn._origHTML;
            }
          });
      },
    },

    // === TABLE ===
    table: {
      init(root) {
        (root || document)
          .querySelectorAll("[data-gk-table]")
          .forEach((wrap) => this.bindTable(wrap));
      },
      bindTable(wrap) {
        if (wrap._gkTableBound) return;
        wrap._gkTableBound = true;
        const id = wrap.dataset.gkTable;
        const isStatic = wrap.hasAttribute("data-gk-static");

        // Load static data if available
        if (isStatic) {
          const scriptEl = wrap.querySelector("script[data-gk-data]");
          if (scriptEl) {
            try {
              wrap._gkData = JSON.parse(scriptEl.textContent);
              let _savedSort = null;
              try {
                _savedSort = JSON.parse(
                  localStorage.getItem("gk-sort-" + id) || "null",
                );
              } catch (e) {}
              wrap._gkSort =
                _savedSort && _savedSort.col
                  ? _savedSort
                  : { col: "", dir: "asc" };
              wrap._gkSearch = "";
              wrap._gkPage = 1;
              wrap._gkFilters = {};
              wrap._gkRestoreSort = _savedSort && _savedSort.col ? true : false;
            } catch (e) {
              /* ignore */
            }
          }
        }

        // Back to this list (1.128.0, see _gkBack): what the history entry kept, else what the
        // browser restored into the box and the selects. Either way the rows follow the box.
        let restoreView = false;
        if (isStatic && wrap._gkData) {
          const kept = _gkBack.read(id) || {};
          const box = wrap.querySelector("[data-gk-search]");
          const q = typeof kept.q === "string" ? kept.q : box ? box.value : "";
          if (box) box.value = q;
          wrap._gkSearch = q;
          const keptFilters = kept.f && typeof kept.f === "object" ? kept.f : {};
          wrap.querySelectorAll("[data-gk-filter]").forEach((sel) => {
            const key = sel.dataset.gkFilter;
            if (Object.prototype.hasOwnProperty.call(keptFilters, key)) sel.value = String(keptFilters[key]);
            // sel.value, not the kept value: an option that no longer exists reads "".
            if (sel.value !== "") wrap._gkFilters[key] = sel.value;
          });
          const p = parseInt(kept.p, 10);
          if (p > 1) wrap._gkPage = p;
          restoreView = q !== "" || Object.keys(wrap._gkFilters).length > 0 || wrap._gkPage > 1;
        }

        if (isStatic && wrap._gkData && (wrap._gkRestoreSort || restoreView)) {
          setTimeout(() => this.renderStatic(wrap), 0);
        }

        // A locked row button (lockedIf, 1.128.0) acts on nothing; a click or Enter says why.
        wrap.addEventListener("click", (e) => {
          const locked = e.target.closest("[data-gk-locked]");
          if (!locked || !wrap.contains(locked)) return;
          e.preventDefault();
          if (GK.toast && GK.toast.info) GK.toast.info(locked.getAttribute("data-gk-locked"));
        });

        // Modal buttons
        wrap.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-gk-modal]");
          if (!btn) return;
          const modalId = btn.dataset.gkModal;
          const tpl = wrap.querySelector(`[data-gk-modal-tpl="${modalId}"]`);
          if (!tpl) return;
          const params = btn.dataset.gkParams
            ? JSON.parse(btn.dataset.gkParams)
            : {};
          const show = () =>
            GK.modal.open(
              tpl.dataset.gkModalTitle,
              tpl.dataset.gkModalUrl,
              params,
              tpl.dataset.gkModalSize,
            );

          const ask = btn.dataset.gkConfirm;
          if (ask) {
            GK.confirm(ask, { danger: true }).then((ok) => ok && show());
          } else {
            show();
          }
        });

        // Row buttons with neither a modal nor an onclick. Until 1.32 these
        // rendered and did nothing at all — including the delete button in the
        // README's headline example. They now fire `gk:rowaction`, the same
        // shape `gk:bulkdelete` uses, so the application decides what happens.
        wrap.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-gk-action]");
          if (!btn) return;
          if (btn.hasAttribute("data-gk-modal") || btn.hasAttribute("onclick")) return;
          // Carries a target: the handler below owns it, question included.
          if (btn.hasAttribute("data-gk-href")) return;

          const fire = () =>
            wrap.dispatchEvent(
              new CustomEvent("gk:rowaction", {
                bubbles: true,
                detail: {
                  action: btn.dataset.gkAction,
                  params: btn.dataset.gkParams ? JSON.parse(btn.dataset.gkParams) : {},
                  tableId: wrap.dataset.gkTable,
                },
              }),
            );

          const ask = btn.dataset.gkConfirm;
          if (ask) {
            GK.confirm(ask, { danger: true }).then((ok) => ok && fire());
          } else {
            fire();
          }
        });

        // A row button with a target AND a question: not an <a>, so no middle
        // click, Ctrl-click or Enter can slip past the question, and a page whose
        // JavaScript never loaded does nothing rather than navigating unasked.
        wrap.addEventListener("click", (e) => {
          const b = e.target.closest("[data-gk-href]");
          if (!b) return;
          const ziel = b.getAttribute("data-gk-href");
          const frage = b.getAttribute("data-gk-confirm");
          const gehen = () => { window.location.href = ziel; };
          if (!frage) return gehen();
          if (!GK.confirm) return window.confirm(frage) && gehen();
          GK.confirm(frage, { danger: true }).then((ok) => ok && gehen()).catch(() => {});
        });

        // The sortable control is a real <button> now and answers Enter and
        // Space by itself. This stays for the other things carrying
        // data-gk-sort — SortLink's anchors among them — and preventDefault is
        // what stops it firing twice on a button: it suppresses the native
        // activation, so exactly one click reaches the sort. Measured with a
        // real key press, not assumed.
        wrap.addEventListener("keydown", (e) => {
          if (e.key !== "Enter" && e.key !== " ") return;
          const th = e.target.closest("[data-gk-sort]");
          if (!th) return;
          e.preventDefault();
          th.click();
        });

        // Sort
        wrap.addEventListener("click", (e) => {
          const th = e.target.closest("[data-gk-sort]");
          if (!th) return;
          if (isStatic && wrap._gkData) {
            const col = th.dataset.gkSort;
            const dir = th.dataset.gkDir;
            wrap._gkSort = { col, dir };
            try {
              localStorage.setItem(
                "gk-sort-" + id,
                JSON.stringify({ col, dir }),
              );
            } catch (e) {}
            this.renderStatic(wrap);
          } else {
            this.reload(wrap, {
              gk_sort: th.dataset.gkSort,
              gk_dir: th.dataset.gkDir,
              gk_page: 1,
            });
          }
        });

        // Pagination. The search handler below has always checked isStatic;
        // this one never did, so a static table's page button fired a server
        // reload — for a table whose rows are already all in the browser.
        wrap.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-gk-page]");
          if (!btn || btn.disabled) return;
          if (isStatic && wrap._gkData) {
            wrap._gkPage = parseInt(btn.dataset.gkPage, 10) || 1;
            this.renderStatic(wrap);
            return;
          }
          this.reload(wrap, { gk_page: btn.dataset.gkPage });
        });

        // Search
        const searchInput = wrap.querySelector("[data-gk-search]");
        if (searchInput) {
          let timer;
          searchInput.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
              if (isStatic && wrap._gkData) {
                wrap._gkSearch = searchInput.value;
                wrap._gkPage = 1;   // a new search starts at the beginning
                this.renderStatic(wrap);
              } else {
                this.reload(wrap, { gk_search: searchInput.value, gk_page: 1 });
              }
            }, 300);
          });
        }

        // Filters
        wrap.querySelectorAll("[data-gk-filter]").forEach((sel) => {
          sel.addEventListener("change", () => {
            if (isStatic && wrap._gkData) {
              wrap._gkFilters[sel.dataset.gkFilter] = sel.value;
              wrap._gkPage = 1;
              this.renderStatic(wrap);
            } else {
              const params = { gk_page: 1 };
              params["gk_filter_" + sel.dataset.gkFilter] = sel.value;
              this.reload(wrap, params);
            }
          });
        });

        // "Reset filters" from the empty state. Delegated, because the
        // button is created anew on every reload.
        wrap.addEventListener("click", (ev) => {
          const button = ev.target.closest("[data-gk-reset-filters]");
          if (!button || !wrap.contains(button)) return;
          ev.preventDefault();
          const params = { gk_page: 1, gk_search: "" };
          const resetInput = wrap.querySelector("[data-gk-search]");
          if (resetInput) resetInput.value = "";
          wrap.querySelectorAll("[data-gk-filter]").forEach((sel) => {
            sel.value = "";
            params["gk_filter_" + sel.dataset.gkFilter] = "";
          });
          if (isStatic && wrap._gkData) {
            wrap._gkSearch = "";
            wrap._gkFilters = {};
            this.renderStatic(wrap);
          } else {
            this.reload(wrap, params);
          }
        });

        // Multi-select
        if (wrap.hasAttribute("data-gk-selectable")) this.initSelectable(wrap);
      },

      initSelectable(wrap) {
        if (wrap._gkSelectableBound) return;
        wrap._gkSelectableBound = true;
        // Keep the Set on the wrap so that renderStatic (client mode) still knows
        // the selection across re-renders (sort/search/filter) and restores it.
        const selected = wrap._gkSelected || (wrap._gkSelected = new Set());
        const bulkBar = wrap.querySelector(".gk-bulk-bar");
        let lastRangeId = null;
        let shiftHeld = false;

        function getRowId(row) {
          return row.dataset.gkRowId;
        }

        function rowVisible(tr) {
          return tr.style.display !== "none";
        }

        function selectableRows(onlyVisible) {
          return [...wrap.querySelectorAll("tbody tr[data-gk-row-id]")].filter(
            (tr) => !onlyVisible || rowVisible(tr),
          );
        }

        function updateBar() {
          const n = selected.size;
          wrap.dispatchEvent(
            new CustomEvent("gk:selectionchange", {
              bubbles: true,
              detail: {
                ids: [...selected],
                tableId: wrap.dataset.gkTable || "",
                count: n,
              },
            }),
          );
          selectableRows(false).forEach((tr) => {
            tr.classList.toggle("gk-row-selected", selected.has(getRowId(tr)));
          });
          const visible = selectableRows(true);
          const visSel = visible.filter((tr) => selected.has(getRowId(tr))).length;
          const selAll = wrap.querySelector("[data-gk-select-all]");
          if (selAll) selAll.indeterminate = visSel > 0 && visSel < visible.length;
          if (selAll) selAll.checked = visible.length > 0 && visSel === visible.length;
          if (!bulkBar) return;
          const countEl = bulkBar.querySelector(".gk-bulk-count");
          if (countEl) {
            countEl.textContent = _t("selected", { n: n });
          }
          bulkBar.style.display = n > 0 ? "flex" : "none";
        }
        // Make it reachable for renderStatic (mirror the selection after a re-render).
        wrap._gkUpdateBar = updateBar;

        wrap.addEventListener(
          "click",
          function (e) {
            shiftHeld = !!e.shiftKey;
          },
          true,
        );

        // Row checkboxes
        wrap.addEventListener("change", function (e) {
          if (e.target.tagName !== "INPUT" || e.target.type !== "checkbox")
            return;
          if (!e.target.closest("td.gk-cb-col")) return;
          const tr = e.target.closest("tr[data-gk-row-id]");
          if (!tr) return;
          const id = getRowId(tr);
          if (shiftHeld && lastRangeId && lastRangeId !== id) {
            const rows = selectableRows(true);
            const ids = rows.map(getRowId);
            const a = ids.indexOf(lastRangeId);
            const b = ids.indexOf(id);
            if (a >= 0 && b >= 0) {
              const from = Math.min(a, b);
              const to = Math.max(a, b);
              const check = e.target.checked;
              for (let i = from; i <= to; i++) {
                const r = rows[i];
                const cb = r.querySelector("td.gk-cb-col input[type=checkbox]");
                if (check) selected.add(getRowId(r));
                else selected.delete(getRowId(r));
                if (cb) cb.checked = check;
              }
            }
          } else if (e.target.checked) {
            selected.add(id);
          } else {
            selected.delete(id);
          }
          lastRangeId = id;
          shiftHeld = false;
          updateBar();
        });

        // A click ON the checkbox column (next to the box in the same cell counts)
        // toggles the selection. Clicks on other columns do NOTHING — otherwise the
        // user gets confused by bulk action bars popping up unintentionally (e.g.
        // when all they want is to look at a tracking cell).
        wrap.addEventListener("click", function (e) {
          const cell = e.target.closest("td.gk-cb-col");
          if (!cell) return;
          // Do not fire twice when the checkbox was already toggled natively
          if (e.target.matches('input[type=checkbox]')) return;
          const tr = cell.closest("tbody tr[data-gk-row-id]");
          if (!tr) return;
          const cb = cell.querySelector("input[type=checkbox]");
          if (!cb || cb.disabled) return;
          // Ignore clicks on <label>, which toggle the checkbox natively
          if (e.target.closest("label")) return;

          cb.checked = !cb.checked;
          if (cb.checked) selected.add(getRowId(tr));
          else selected.delete(getRowId(tr));
          updateBar();
        });

        // Select-all checkbox — delegated on wrap so that it survives the table
        // being re-rendered by renderStatic (a new thead checkbox).
        wrap.addEventListener("change", function (e) {
          if (!e.target.matches("[data-gk-select-all]")) return;
          const checked = e.target.checked;
          selectableRows(true).forEach((tr) => {
            const cb = tr.querySelector("td.gk-cb-col input[type=checkbox]");
            if (checked) {
              selected.add(getRowId(tr));
              if (cb) cb.checked = true;
            } else {
              selected.delete(getRowId(tr));
              if (cb) cb.checked = false;
            }
          });
          updateBar();
        });

        // Bulk delete
        const delBtn =
          bulkBar && bulkBar.querySelector("[data-gk-bulk-delete]");
        if (delBtn) {
          delBtn.addEventListener("click", function () {
            if (!selected.size) return;
            const ids = [...selected];
            GK.confirm(_t("confirm_delete"), {
              title: _t("confirm_ok"),
              confirmText: _t("confirm_ok"),
              danger: true,
            }).then((ok) => {
              if (!ok) return;
              wrap.dispatchEvent(
                new CustomEvent("gk:bulkdelete", {
                  bubbles: true,
                  detail: { ids, tableId: wrap.dataset.gkTable },
                }),
              );
            });
          });
        }

        // Cancel
        const cancelBtn =
          bulkBar && bulkBar.querySelector("[data-gk-bulk-cancel]");
        if (cancelBtn) {
          cancelBtn.addEventListener("click", function () {
            selected.clear();
            wrap
              .querySelectorAll("tbody input[type=checkbox]")
              .forEach((cb) => (cb.checked = false));
            const selAllBtn = wrap.querySelector("[data-gk-select-all]");
            if (selAllBtn) {
              selAllBtn.checked = false;
              selAllBtn.indeterminate = false;
            }
            updateBar();
          });
        }
      },

      // Client-side render for static data
      renderStatic(wrap) {
        const data = wrap._gkData;
        if (!data) return;

        let rows = data.rows.slice();
        const columns = data.columns;
        const colKeys = Object.keys(columns);

        // Apply filters
        const filters = wrap._gkFilters || {};
        Object.entries(filters).forEach(([col, val]) => {
          if (val !== "") {
            rows = rows.filter((r) => String(r[col] ?? "") === val);
          }
        });

        // Apply search
        const query = (wrap._gkSearch || "").toLowerCase().trim();
        const searchKeys =
          data.search && data.search.length ? data.search : colKeys;
        if (query) {
          rows = rows.filter((row) => {
            return searchKeys.some((key) => {
              return String(row[key] ?? "")
                .toLowerCase()
                .includes(query);
            });
          });
        }

        // Apply sort
        const sort = wrap._gkSort || {};
        if (sort.col && columns[sort.col]) {
          const col = sort.col;
          const dir = sort.dir === "desc" ? -1 : 1;
          rows.sort((a, b) => {
            let va = a[col] ?? "";
            let vb = b[col] ?? "";
            // Try numeric comparison
            const na = parseFloat(va),
              nb = parseFloat(vb);
            if (!isNaN(na) && !isNaN(nb)) return (na - nb) * dir;
            return String(va).localeCompare(String(vb), "de") * dir;
          });
        }

        wrap._gkMatching = rows; // what the search and the filters left, sorted — CSV export reads it
        // Paging — after filtering, searching and sorting, so the page window
        // is a window onto what the user is actually looking at.
        const perPage = parseInt(data.perPage, 10) || 0;
        const total = rows.length;
        let page = wrap._gkPage || 1;
        const pages = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
        if (page > pages) page = pages;
        if (page < 1) page = 1;
        wrap._gkPage = page;
        // What is drawn now is what Back brings back (1.128.0, see _gkBack).
        _gkBack.write(wrap.dataset.gkTable, { q: wrap._gkSearch || "", f: Object.assign({}, filters), p: page });
        const totalRows = rows.length;   // after search and filter, before the page slice — what the footer counts
        if (perPage > 0) rows = rows.slice((page - 1) * perPage, page * perPage);

        // Build HTML
        const e = _gkEsc;

        const formatVal = (val, col) => {
          const fmt = col.format || null;
          if (!fmt) return e(val);
          switch (fmt) {
            case "currency":
              // Built from the same catalogue strings the server formats with —
              // format.decimal, format.thousands, format.currency — instead of a
              // hardcoded "de-DE". With the locale nailed down here, the first
              // sort or filter on an English table quietly rewrote every
              // "€1,200.00" as "1.200,00 €". The template carries the symbol and
              // the side it sits on, so both shapes come out right.
              return e(_gkCurrency(val));
            case "percent":
              return e(_gkPercent(val, col.decimals));
            case "date":
              // KNOWN GAP, deliberately left: currency and number above now
              // build from the catalogue, dates do not. The server renders
              // format.date, a PHP format string ("M j, Y" in English,
              // "d.m.Y" in German), which JavaScript cannot consume — and the
              // token that needs month names has none in the catalogue to
              // read. Reproducing it per locale with toLocaleDateString options
              // would be the second source of truth this file keeps warning
              // about. So a date column redrawn in the browser still comes back
              // German. Fix needs localized month names first.
              // Since 1.128.0 inside <time datetime="…">, as Table.php writes it.
              return val ? _gkTimeTag(new Date(val), e(new Date(val).toLocaleDateString("de-DE")), false) : "";
            case "datetime":
              return val
                ? _gkTimeTag(new Date(val), e(
                    new Date(val).toLocaleString("de-DE", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                    }),
                  ), true)
                : "";
            case "boolean":
              return parseInt(val)
                ? '<span class="gk-bool gk-bool-yes">✓</span>'
                : '<span class="gk-bool gk-bool-no">–</span>';
            case "email":
              return val
                ? '<a href="mailto:' + e(val) + '">' + e(val) + "</a>"
                : "";
            case "html":
              return String(val || "");
            case "label":
              return renderLabel(val, col.labels || {});
            case "number": {
              const blankZero = col.blankZero !== false;
              const n = parseFloat(val);
              if (
                blankZero &&
                (val === null || val === "" || val === undefined || n === 0)
              ) {
                return '<span class="gk-num gk-num-empty">—</span>';
              }
              const dec = col.decimals || 0;
              const text = isNaN(n)
                ? String(val)
                : _gkNumber(n, dec);
              return '<span class="gk-num">' + e(text) + "</span>";
            }
            default:
              return e(val);
          }
        };

        /*
         * The label colour comes from the server's own table, which travels in
         * the data block — this side used to carry a shorter copy that knew no
         * "blue" and half the words, so a green status turned grey on the first
         * sort. A 'labels' entry may also be {color, text}, which the copy could
         * not read at all: the cell showed the stored value instead of its text.
         */
        const renderLabel = (val, custom) => {
          // Same key as Table::labelKey(): NBSP counts as a space there too.
          const v = String(val == null ? "" : val).replace(/\u00a0/g, " ").trim().toLowerCase();
          const eintrag = (custom || {})[v];
          let color = null;
          let text = val;
          if (eintrag && typeof eintrag === "object") {
            color = eintrag.color || null;
            // null means "no text of its own" — the server reads it that way,
            // and this side used to render an empty label for it.
            if (eintrag.text !== undefined && eintrag.text !== null) text = eintrag.text;
          } else if (typeof eintrag === "string" && eintrag !== "") {
            color = eintrag;
          }
          if (!color) {
            for (const [c, vals] of Object.entries(data.labelColors || {})) {
              if (vals.indexOf(v) > -1) { color = c; break; }
            }
          }
          return '<span class="gk-label gk-label-' + e(color || "gray") + '">' + e(text) + "</span>";
        };

        // Determine next sort direction for headers
        const sortCol = sort.col || "";
        const sortDir = sort.dir || "asc";

        const selectable = wrap.hasAttribute("data-gk-selectable");
        const rowIdField = data.rowId || "id";
        const selSet = wrap._gkSelected || new Set();

        let html =
          // nowrap() and footer() travel in the data block: the rebuild wrote a bare
          // <table class="gk-table"> and no <tfoot>, so the first sort of a static
          // table unwrapped its cells and took its totals row away.
          '<table class="gk-table' + (data.nowrap ? " gk-table-nowrap" : "") + '">' +
          (data.caption ? '<caption class="gk-sr-only">' + e(data.caption) + "</caption>" : "") +
          "<thead><tr>";
        if (selectable)
          html +=
            // Same names and scope as Table.php writes — the first sort or
            // search rebuilds the table, and until 1.80.3 it took them away.
            '<th scope="col" class="gk-cb-col"><input type="checkbox" data-gk-select-all aria-label="' +
            e(_lang["select_all"] || "Select all") + '" title="' + e(_lang["select_all"] || "Select all") + '"></th>';
        // The cell over the left buttons stands where a row has them: before the first column,
        // as Table.php writes it since 1.110.1 (it stood at the end, every heading one place early).
        if (Object.values(data.buttons || {}).some((b) => (b.position || "right") === "left"))
          html += '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">' + _gkEsc(_t("actions")) + "</span></th>";
        for (const [key, col] of Object.entries(columns)) {
          // width, min-width, max-width and nowrap, as Table.php writes them on a header cell.
          const thStyles = [];
          if (col.width && col.width !== "auto") thStyles.push("width:" + e(col.width));
          if (col.minWidth) thStyles.push("min-width:" + e(col.minWidth));
          if (col.maxWidth) thStyles.push("max-width:" + e(col.maxWidth));
          if (col.nowrap) thStyles.push("white-space:nowrap");
          const style = thStyles.length ? ' style="' + thStyles.join(";") + '"' : "";
          const sortable = col.sortable || false;
          const prio = _gkPriorityClass(col);
          let cls = "",
            attrs = "",
            sortBtn = "",
            sortIcon = "";
          if (sortable) {
            const newDir =
              sortCol === key && sortDir === "asc" ? "desc" : "asc";
            // Same shape the server renders: aria-sort on the header, the
            // control a real <button> inside it. The client used to emit
            // neither, so a table redrawn in the browser lost both the sort
            // state and any way to sort it without a mouse.
            attrs =
              ' aria-sort="' +
              (sortCol === key
                ? sortDir === "asc"
                  ? "ascending"
                  : "descending"
                : "none") +
              '"';
            sortBtn = ' data-gk-sort="' + e(key) + '" data-gk-dir="' + newDir + '"';
            // gk-sortable-mi = Material icon indicator (consistent with SortLink);
            // suppresses the ::after arrow of .gk-sortable.
            const base =
              "gk-sortable gk-sortable-mi" +
              (col.hideOnMobile ? " gk-hide-mobile" : "") +
              (prio ? " " + prio : "");
            cls =
              ' class="' +
              base +
              (sortCol === key ? " gk-sorted-" + sortDir : "") +
              '"';
            const iconName =
              sortCol === key
                ? sortDir === "asc"
                  ? "arrow_upward"
                  : "arrow_downward"
                : "unfold_more";
            const iconCls =
              sortCol === key ? "gk-sort-icon is-active" : "gk-sort-icon";
            sortIcon =
              ' <span class="material-icons ' +
              iconCls +
              '">' +
              iconName +
              "</span>";
          } else if (col.hideOnMobile || prio) {
            cls = ' class="' + [col.hideOnMobile ? "gk-hide-mobile" : "", prio].filter(Boolean).join(" ") + '"';
          }
          // Same rule as Table::headerAlignClass(): the header stands where its
          // column stands.
          const alignCls = thAlignClass(col);
          if (alignCls) {
            cls = cls
              ? cls.replace(/"$/, " " + alignCls + '"')
              : ' class="' + alignCls + '"';
          }
          const label = e(col.label) + sortIcon;
            // scope="col", the same as the server writes. Without it here the
            // first sort or filter stripped the association off every header —
            // PHP got it right and the client quietly undid it.
          html +=
            '<th scope="col"' +
            cls +
            style +
            attrs +
            ">" +
            (sortBtn
              ? '<button type="button" class="gk-sort-btn"' +
                sortBtn +
                ">" +
                label +
                "</button>"
              : label) +
            "</th>";
        }
        const allBtns = data.buttons || {};
        const leftBtns = Object.fromEntries(
          Object.entries(allBtns).filter(
            ([, b]) => (b.position || "right") === "left",
          ),
        );
        const rightBtns = Object.fromEntries(
          Object.entries(allBtns).filter(
            ([, b]) => (b.position || "right") === "right",
          ),
        );
        const hasLeft = Object.keys(leftBtns).length > 0;
        const hasRight = Object.keys(rightBtns).length > 0;
        const actionsHead =
          '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">' +
          _gkEsc(_t("actions")) +
          "</span></th>";
        if (hasRight) html += actionsHead;
        html += "</tr></thead><tbody>";

        const renderBtnGroup = (btns, row) => {
          let h = "";
          for (const [bname, bopts] of Object.entries(btns)) {
            // showIf: skip button if row field is falsy
            if (bopts.showIf && !row[bopts.showIf]) continue;
            // hideIf: skip button if row field is truthy
            if (bopts.hideIf && row[bopts.hideIf]) continue;
            const hasText = !!bopts.text;
            // lockedIf (1.128.0): the same locked button Table::renderLockedButton writes.
            const lockVal = bopts.lockedIf ? row[bopts.lockedIf] : null;
            if (lockVal && lockVal !== "0") {
              const reason = typeof lockVal === "string" && lockVal.trim() !== "" ? lockVal.trim() : _t("locked");
              let lname = bopts.aria || bopts.title || _t("action_" + bname);
              if (lname === "action_" + bname) {
                lname = bname.replace(/[_-]+/g, " ");
                lname = lname.charAt(0).toUpperCase() + lname.slice(1);
              }
              h += '<button type="button" class="' +
                (hasText ? "gk-btn gk-btn-icon-text gk-btn-text gk-btn-neutral" : "gk-btn gk-btn-icon-only gk-btn-text gk-btn-neutral gk-btn-sm") +
                ' gk-btn-locked" aria-disabled="true" data-gk-locked="' + e(reason) + '" aria-label="' + e(lname + " — " + reason) +
                '" title="' + e(reason) + '">' + GK.table.iconSvg("lock") + (hasText ? "<span>" + e(bopts.text) + "</span>" : "") + "</button>";
              continue;
            }
            // Mirror PHP renderButtons: variant=text, color=neutral, size=sm by default
            const colorMap = {
              danger: "danger",
              success: "success",
              warning: "warning",
              primary: "primary",
            };
            // The server reads 'color' first and falls back to 'class'; this side
            // read 'class' only, so a button declared with the documented
            // 'color' => 'danger' turned grey on the first sort.
            const color = colorMap[bopts.color || bopts["class"]] || "neutral";
            // Same classes as Table::renderButtons: gk-btn-sm belongs to the
            // icon-only button. The client put it on text buttons too, so every
            // labelled row button shrank on the first sort.
            let cls = hasText
              ? "gk-btn gk-btn-icon-text gk-btn-text gk-btn-" + color
              : "gk-btn gk-btn-icon-only gk-btn-text gk-btn-" + color + " gk-btn-sm";
            const params = _gkRowParams(bopts.params, row);
            // 'href' => '/users/{id}': a real link, exactly as Table::renderButtons
            // writes it (_gkFillTarget).
            let href = null;
            // Same three conditions as Table::renderButtons: a value of "0" is a
            // target like any other (bopts.href alone would call it falsy), and
            // modal or onclick keep what they had.
            if (bopts.href !== undefined && bopts.href !== null && String(bopts.href) !== ""
                && !bopts.modal && !bopts.onclick) {
              href = _gkFillTarget(bopts.href, row);
            }
            // A link with a question is a button carrying its target: a middle
            // click, a Ctrl-click and Enter all bypass a click handler on an <a>.
            const fragtVorher = href !== null && bopts.confirm;
            const zielAttr = fragtVorher ? ' data-gk-href="' + e(href) + '"' : "";
            if (fragtVorher) href = null;
            // Going somewhere is not a row action: with data-gk-action the
            // delegated handler would ask its own question on top of this one.
            const alsAktion = href === null && !fragtVorher;
            let btnAttrs = (href === null ? ' type="button"' : ' href="' + e(href) + '"') + zielAttr;
            // A link carries no data-gk-action: the delegated handler would fire
            // gk:rowaction on top of the navigation.
            if (alsAktion) btnAttrs += ' data-gk-action="' + e(bname) + '"';
            if (bopts.modal && href === null)
              btnAttrs += ' data-gk-modal="' + e(bopts.modal) + '"';

            // The same name the server-side renderer gives these buttons, so
            // a static table and a live one sound identical. Icon-only means
            // the whole content is an <svg>: with no aria-label the button
            // announced as "button", and one of them deletes the row.
            if (!hasText) {
              var aria =
                bopts.aria ||
                bopts.title ||
                _t("action_" + bname);
              if (aria === "action_" + bname) {
                aria = bname.replace(/[_-]+/g, " ");
                aria = aria.charAt(0).toUpperCase() + aria.slice(1);
              }
              btnAttrs += ' aria-label="' + e(aria) + '"';
            }
            // The confirmation. Table::renderButtons emits data-gk-confirm on
            // the server; this re-render did not, so a delete button asked on
            // first load and stopped asking after any sort, search or page
            // change — the click went straight through to gk:rowaction with no
            // question. Verified in a browser from a real Composer install.
            if (bopts.confirm) {
              btnAttrs +=
                ' data-gk-confirm="' +
                e(typeof bopts.confirm === "string" ? bopts.confirm : _t("confirm_delete_row")) +
                //   confirm_delete is the BULK wording ("Really delete
                //   entries?"); one row gets the same sentence the server
                //   uses, table.confirm_delete.
                '"';
            }
            if (bopts.title) btnAttrs += ' title="' + e(bopts.title) + '"';
            btnAttrs += " data-gk-params='" + e(JSON.stringify(params)) + "'";
            if (bopts.onclick && href === null) {
              let oc = String(bopts.onclick).replace(/\{(\w+)\}/g, (_, k) =>
                JSON.stringify(row[k] ?? null),
              );
              // Wrap it exactly as Table::renderButtons does. An inline handler runs
              // before any delegated listener could stop it, so data-gk-confirm — which
              // is what the delegated path reads — cannot hold this back. It did not:
              // a button with both `onclick` and `confirm` asked on the server-rendered
              // page and then, from the first sort onwards, deleted without asking.
              if (bopts.confirm) {
                const msg =
                  typeof bopts.confirm === "string" ? bopts.confirm : _t("confirm_delete_row");
                oc =
                  "GK.confirm(" +
                  JSON.stringify(msg) +
                  ",{danger:true}).then(function(ok){if(ok){" +
                  oc +
                  "}})";
              }
              btnAttrs += " onclick='" + oc.replace(/'/g, "&#39;") + "'";
            }
            const icon = bopts.icon ? GK.table.iconSvg(bopts.icon) : "";
            const text = hasText ? "<span>" + e(bopts.text) + "</span>" : "";
            const tag = href === null ? "button" : "a";
            h +=
              "<" + tag + ' class="' +
              cls +
              '"' +
              btnAttrs +
              ">" +
              icon +
              text +
              "</" + tag + ">";
          }
          return h;
        };

        if (rows.length === 0) {
          const colspan =
            colKeys.length +
            (hasLeft ? 1 : 0) +
            (hasRight ? 1 : 0) +
            (selectable ? 1 : 0);
          // The same empty state as on the server side: a statement, a piece
          // of context and — when the view has been narrowed — a way out.
          const narrowed =
            !!(wrap._gkSearch && wrap._gkSearch !== "") ||
            Object.values(wrap._gkFilters || {}).some((v) => v !== "");
          html +=
            '<tr class="gk-empty-row"><td colspan="' + colspan + '" class="gk-empty">' +
            '<div class="gk-empty-inner">' +
            '<span class="material-icons gk-empty-icon" aria-hidden="true">' +
            (narrowed ? "search_off" : "inbox") + "</span>" +
            '<span class="gk-empty-title">' +
            _t(narrowed ? "no_matches" : "no_entries") + "</span>" +
            '<span class="gk-empty-hint">' +
            (_lang[narrowed ? "no_matches_hint" : "empty_hint"] || "") + "</span>" +
            (narrowed
              ? '<span class="gk-empty-action"><button type="button" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm" data-gk-reset-filters>' +
                (_lang["reset_filters"] || "Reset filters") + "</button></span>"
              : "") +
            "</div></td></tr>";
        } else {
          const groupBy = data.groupBy || null;
          const groupCounts = {};
          if (groupBy && groupBy.column) {
            rows.forEach((row) => {
              const gk = String(row[groupBy.column] ?? "");
              groupCounts[gk] = (groupCounts[gk] || 0) + 1;
            });
          }
          const groupSpan =
            colKeys.length +
            (hasLeft ? 1 : 0) +
            (hasRight ? 1 : 0) +
            (selectable ? 1 : 0);
          let lastGroup = null;
          /*
           * rowLink(): the one control a row carries, as Table::rowTarget()
           * writes it — the same tags, attributes and order, or the row changes
           * on the first sort. null when the row cannot have one: its main
           * cell shows nothing, the target is not allowed, or the column writes
           * a link of its own ('html', 'email').
           */
          const rowLink = data.rowLink && columns[data.rowLink.column] ? data.rowLink : null;
          const rowTarget = (row) => {
            if (!rowLink) return null;
            const col = columns[rowLink.column];
            if (col.format === "html" || col.format === "email") return null;
            const roh = row[rowLink.column] ?? "";
            const box = document.createElement("div");
            box.innerHTML = formatVal(typeof roh === "boolean" ? (roh ? "1" : "") : roh, col);
            const shown = box.textContent.trim();
            if (shown === "" || shown === "—") return null;
            if (rowLink.sheet) {
              const params = _gkRowParams(rowLink.params, row);
              return [
                '<button type="button" class="gk-row-target" data-gk-sheet="' + e(rowLink.sheet) + '"' +
                  (rowLink.url ? ' data-gk-sheet-url="' + e(rowLink.url) + '"' : "") +
                  ' data-gk-sheet-title="' + e(shown) + '"' +
                  " data-gk-params='" + e(JSON.stringify(params)) + "'" +
                  ' aria-haspopup="dialog">',
                "</button>",
              ];
            }
            if (rowLink.href === undefined || rowLink.href === null || String(rowLink.href) === "") return null;
            const ziel = _gkFillTarget(rowLink.href, row);
            return ziel === null ? null : ['<a class="gk-row-target" href="' + e(ziel) + '">', "</a>"];
          };
          rows.forEach((row) => {
            if (groupBy && groupBy.column) {
              const gk = String(row[groupBy.column] ?? "");
              if (gk !== lastGroup) {
                const gLabel = (groupBy.labels && groupBy.labels[gk]) || gk;
                html +=
                  '<tr class="gk-table-group"><td colspan="' +
                  groupSpan +
                  '"><span class="gk-table-group-name">' +
                  e(gLabel) +
                  '</span><span class="gk-table-group-n">' +
                  (groupCounts[gk] || 0) +
                  "</span></td></tr>";
                lastGroup = gk;
              }
            }
            const rid = selectable ? String(row[rowIdField] ?? "") : "";
            const target = rowTarget(row);
            html += "<tr" + (target ? ' class="gk-row-link"' : "") +
              (selectable ? ' data-gk-row-id="' + e(rid) + '"' : "") + ">";
            if (selectable)
              html +=
                // value: the server has always written the row id here. Page
                // code that reads the checked boxes straight out of the DOM —
                // a form post, a bulk action — got empty values after a sort.
                '<td class="gk-cb-col"><input type="checkbox" aria-label="' +
                e(_lang["select_row"] || "Select row") + '" value="' + e(rid) + '"' +
                (selSet.has(rid) ? " checked" : "") +
                "></td>";
            if (hasLeft)
              html +=
                '<td class="gk-actions gk-actions-left"><div class="gk-btn-group">' +
                renderBtnGroup(leftBtns, row) +
                "</div></td>";
            for (const [key, col] of Object.entries(columns)) {
              // (string) true is "1" and (string) false is "" in PHP; JavaScript
              // would write "true" and "false". A boolean column showed different
              // text on each side, and the false one was linked here and empty there.
              const rohWert = row[key] ?? "";
              const val = typeof rohWert === "boolean" ? (rohWert ? "1" : "") : rohWert;
              // text-align and nowrap, as Table.php writes them on a cell: a
              // column's own nowrap, and a number or currency cell never wraps.
              // Alignment and nowrap are classes since 1.128.0, as in Table::renderCell; only
              // width, min-width and max-width stay a style.
              const tdStyles = [];
              const tdCls = [];
              const isNum = col.format === "number" || col.format === "currency";
              if (["left", "center", "right"].indexOf(col.align) >= 0) tdCls.push("gk-text-" + col.align);
              else if (col.align) tdStyles.push("text-align:" + e(col.align));
              if (col.width && col.width !== "auto") tdStyles.push("width:" + e(col.width));
              if (col.minWidth) tdStyles.push("min-width:" + e(col.minWidth));
              if (col.maxWidth) tdStyles.push("max-width:" + e(col.maxWidth));
              if (col.nowrap || isNum) tdCls.push("gk-nowrap");
              const align = tdStyles.length ? ' style="' + tdStyles.join(";") + '"' : "";
              // Same order as Table::render writes them — a byte-for-byte
              // comparison of the two renderers is only possible if they agree.
              if (isNum)
                tdCls.push("gk-td-num");
              if (col.hideOnMobile) tdCls.push("gk-hide-mobile");
              // A list's column that gives way: header and cells together.
              const tdPrio = _gkPriorityClass(col);
              if (tdPrio) tdCls.push(tdPrio);
              // The server writes this too — without it a muted column changed
              // its text colour on the first sort.
              if (col.muted) tdCls.push("gk-td-muted");
              const hideCls = tdCls.length
                ? ' class="' + tdCls.join(" ") + '"'
                : "";
              // href and sub, exactly as Table::cellContent() writes them: a
              // linked value keeps its raw form for search and sort, and the
              // second line is always text, whatever the cell's format is.
              let inhalt = formatVal(val, col);
              // The row's own control takes the place of a cell link — one
              // control per cell, never one inside another.
              const isTarget = target && key === rowLink.column;
              if (isTarget) inhalt = target[0] + inhalt + target[1];
              if (col.href && !isTarget && _gkAlsText(val).trim() !== "") {
                let ziel = _gkFillTarget(col.href, row);
                // 'html' means the caller writes the markup, links included:
                // wrapping it would produce nested anchors, and the browser hands
                // the visible text after the inner <a> to a foreign target.
                if (col.format === "html") ziel = null;
                // e() around a target that is already URL-encoded changes nothing
                // a browser could show — it is the second line of defence, not
                // the first, and no browser case can catch its removal.
                if (ziel !== null) inhalt = '<a href="' + e(ziel) + '" class="gk-cell-link">' + inhalt + "</a>";
              }
              if (col.sub) {
                const unter = _gkAlsText(row[col.sub]).trim();
                if (unter !== "") inhalt += '<div class="gk-cell-sub">' + e(unter) + "</div>";
              }
              html +=
                "<td" +
                hideCls +
                align +
                ' data-label="' +
                e(col.label) +
                '">' +
                inhalt +
                "</td>";
            }
            if (hasRight)
              html +=
                '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">' +
                renderBtnGroup(rightBtns, row) +
                "</div></td>";
            html += "</tr>";
          });
        }

        html += "</tbody>";
        const footerCells = Array.isArray(data.footer) ? data.footer : [];
        const loadTime = data.loadTimeMs == null ? null : parseInt(data.loadTimeMs, 10);
        if (footerCells.length || loadTime !== null) {
          // Same markup as Table::render() writes for footer() and loadTime():
          // the style is built from a fixed vocabulary, the text is escaped like
          // every cell, the time sits in the columns the cells leave. Until
          // 1.82.0 a loadTime() row on its own was not written at all.
          const timeText = loadTime === null ? "" : loadTime < 1000 ? loadTime + " ms" : _gkNumber(loadTime / 1000, 2) + " s";
          const colCount = (selectable ? 1 : 0) + (hasLeft ? 1 : 0) + Object.keys(columns).length + (hasRight ? 1 : 0);
          html += '<tfoot><tr class="gk-table-footer">';
          if (!footerCells.length) {
            html += '<td colspan="' + colCount + '" class="gk-table-meta">' + totalRows + " " + e(_lang.pagination_entries || "entries") + " · " + timeText + "</td>";
          }
          footerCells.forEach(function (cell) {
            const c = typeof cell === "string" ? { text: cell } : cell || {};
            const align = ["left", "center", "right"].indexOf(c.align) >= 0 ? c.align : "left";
            let style = "text-align:" + align + ";";
            if (c.bold) style += "font-weight:600;";
            if (align === "right") style += "color:var(--gk-primary);";
            html += '<td colspan="' + (parseInt(c.colspan, 10) || 1) + '" style="' + style + '">' + e(c.text == null ? "" : c.text) + "</td>";
          });
          // The server pads the row to the full width; so does this, or the
          // footer of a rebuilt table ends short of the last column.
          const used = footerCells.reduce((n, cell) => n + ((typeof cell === "string" ? 1 : parseInt((cell || {}).colspan, 10)) || 1), 0);
          if (footerCells.length && colCount - used > 0) {
            html += loadTime === null
              ? '<td colspan="' + (colCount - used) + '"></td>'
              : '<td colspan="' + (colCount - used) + '" class="gk-table-meta">' + timeText + "</td>";
          }
          html += "</tr></tfoot>";
        }
        html += "</table>";

        // The pager. A static table has every row in the browser, so paging is
        // a slice — but renderStatic used to drop the pager on every rebuild
        // and never put it back, and the page buttons fired a server reload
        // the page often could not answer. Both are handled here now.
        if (perPage > 0 && total > perPage) {
          html += GK.table._staticPager(page, Math.ceil(total / perPage));
        }

        // Replace table content (keep toolbar, templates, script)
        const oldTable = wrap.querySelector(".gk-table");
        const oldPag = wrap.querySelector(".gk-pagination");
        if (oldTable) oldTable.remove();
        if (oldPag) oldPag.remove();

        const toolbar = wrap.querySelector(".gk-toolbar");
        if (toolbar) {
          toolbar.insertAdjacentHTML("afterend", html);
        } else {
          wrap.insertAdjacentHTML("afterbegin", html);
        }
        // Mirror the selection again after the rebuild (checkbox/highlight/bulk bar).
        if (selectable && typeof wrap._gkUpdateBar === "function")
          wrap._gkUpdateBar();

        GK.table._announce(
          wrap,
          // Not tr[data-gk-row-id]: rows carry that only when the table has a
          // key, so a keyless table counted zero and announced "No matches"
          // while showing twelve rows.
          wrap.querySelectorAll("tbody tr:not(.gk-empty-row)").length,
          total,
          page,
          perPage > 0 ? Math.ceil(total / perPage) : 1,
        );
      },

      /** The pager for a static table, matching what Table renders server-side. */
      /*
       * Say what just happened, for people who are not watching it happen.
       *
       * Sorting, filtering and paging swap the rows in place. On screen that is
       * self-evident; to a screen reader the table simply became different data
       * with no announcement — no way to tell whether the filter had worked, or
       * how much was left. The region is polite, so it waits for a pause rather
       * than cutting across whatever is being read.
       */
      _announce(wrap, shown, total, page, pages) {
        const el = wrap.querySelector("[data-gk-table-status]");
        if (!el) return;
        const msg =
          shown === 0
            ? _t("empty_filtered") || "No matches"
            : _t("status", { n: total, page: page, pages: pages });
        // Only on a real change: rewriting the same text does not re-announce
        // in some readers and needlessly repeats in others.
        if (el.textContent !== msg) el.textContent = msg;
      },

      _staticPager(page, pages) {
        const btn = (label, target, disabled, active) =>
          // The active page used to ADD "gk-btn-filled gk-btn-primary" on top of
          // "gk-btn-text gk-btn-neutral" rather than replacing it. Two variants on
          // one button fight in the cascade: .gk-btn-text.gk-btn-neutral is declared
          // later and won the colour, .gk-btn-filled.gk-btn-primary won the
          // background, and the current page rendered as slate rgb(71,85,105) on
          // grey rgb(87,96,106) — 1.19:1, effectively invisible — with square
          // corners instead of a pill. Emit exactly ONE variant, the same pair the
          // server's Pagination sends (tonal/primary active, text/neutral idle),
          // so a client redraw cannot disagree with the server's first paint.
          '<button type="button" class="gk-btn '
          + (active ? "gk-btn-tonal gk-btn-primary" : "gk-btn-text gk-btn-neutral")
          + " gk-btn-sm "
          + (typeof label === "number" ? "gk-btn-pill" : "gk-btn-icon-only")
          + '" data-gk-page="' + target + '"'
          + (disabled ? " disabled" : "")
          // A page button used to be the digit alone: it announced as
          // "3, button", with no hint of what 3 meant, and the page you were
          // on was marked by colour and nothing else.
          + (typeof label === "number"
              ? ' aria-label="' + _gkEsc(_t("pagination_page_of", { page: label, total: pages })) + '"'
                + (active ? ' aria-current="page"' : "")
              : ' aria-label="' + label.aria + '"')
          + ">"
          + (typeof label === "number"
              ? label
              : '<span class="material-icons" aria-hidden="true" style="font-size:16px">'
                + label.icon + "</span>")
          + "</button>";

        // nav, matching the server: a landmark that can be jumped to and
        // skipped past, rather than loose digits inside the table.
        let out = '<nav class="gk-pagination" aria-label="' + _gkEsc(_t("pagination_aria")) + '">';
        out += btn({ icon: "chevron_left", aria: _t("pagination_prev") }, page - 1, page <= 1, false);

        // A window around the current page, plus the first and the last.
        const set = new Set([1, pages]);
        for (let i = page - 2; i <= page + 2; i++) if (i >= 1 && i <= pages) set.add(i);
        const list = [...set].sort((a, b) => a - b);
        let last = 0;
        list.forEach((n) => {
          if (last && n - last > 1) out += '<span class="gk-pagination-gap">…</span>';
          out += btn(n, n, false, n === page);
          last = n;
        });

        out += btn({ icon: "chevron_right", aria: _t("pagination_next") }, page + 1, page >= pages, false);
        out += "</nav>";
        return out;
      },

      /*
       * The same markup GridKit\Icon::svg() writes in PHP, attribute for
       * attribute — including stroke-linecap and stroke-linejoin, which this
       * side left out: every button icon grew square corners on the first sort.
       */
      iconSvg(name) {
        switch (name) {
          case "pencil":
          case "edit":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
          case "trash":
          case "delete":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>';
          case "plus":
          case "add":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>';
          case "lock":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
          case "eye":
          case "visibility":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
          case "download":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>';
          case "upload":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>';
          case "copy":
          case "content_copy":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
          case "mail":
          case "email":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
          case "search":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
          case "settings":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
          case "open_in_new":
          case "external":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
                    case "auto_awesome":
          case "generate":
          case "wand":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4M19 17v4M3 5h4M17 19h4"/></svg>';
          case "login":
          case "impersonate":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
          case "print":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 6,2 18,2 18,9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>';
          case "check":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
          case "close":
          case "x":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
          case "arrow_back":
          case "arrow_left":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
          case "send":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
          case "lock_open":
          case "unlock":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>';
          case "attach_file":
          case "paperclip":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
          case "link_off":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" y1="12" x2="12" y2="12"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';
          case "refresh":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
          case "zoom_in":
          case "zoom":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>';
          case "image":
          case "photo":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
          default:
            return (
              '<span class="material-icons" style="font-size:16px;vertical-align:middle;">' +
              _gkEsc(name) +
              "</span>"
            );
        }
      },

      reload(wrap, overrides) {
        const id = wrap.dataset.gkTable;
        const url = new URL(window.location);
        if (overrides)
          Object.entries(overrides).forEach(([k, v]) =>
            url.searchParams.set(k, v),
          );
        url.searchParams.set("gk_table", id);

        // Visible feedback for as long as the reload is running. The existing
        // rows stay where they are and recede — no jumping, and the user can
        // see that something is happening. aria-busy says the same thing to
        // screen readers.
        wrap.setAttribute("data-gk-loading", "");
        wrap.setAttribute("aria-busy", "true");
        // Overtaking requests: only the last one may write the result.
        // finish() returns true exactly once — otherwise the catch branch would
        // run a second time after a successful render and replace the content
        // just inserted with the error message.
        const run = (wrap._gkRun = (wrap._gkRun || 0) + 1);
        let settled = false;
        const finish = () => {
          if (wrap._gkRun !== run || settled) return false;
          settled = true;
          wrap.removeAttribute("data-gk-loading");
          wrap.removeAttribute("aria-busy");
          return true;
        };

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then((r) => {
            if (!r.ok) {
              const error = new Error("HTTP " + r.status);
              error.gkTransport = true;
              throw error;
            }
            return r.text();
          }, (networkError) => {
            networkError.gkTransport = true;
            throw networkError;
          })
          .then((html) => {
            if (!finish()) return;
            const toolbar = wrap.querySelector(".gk-toolbar");
            // The bulk bar lives beside the toolbar, not inside the table, so
            // the sweep below took it out on the first page or filter change
            // and it never came back — selection kept working internally with
            // nothing on screen to act on it.
            const bulkBar = wrap.querySelector(".gk-bulk-bar");
            const templates = wrap.querySelectorAll("template");
            Array.from(wrap.children).forEach((ch) => {
              if (
                ch !== toolbar &&
                ch !== bulkBar &&
                ch.tagName !== "TEMPLATE" &&
                ch.tagName !== "SCRIPT"
              )
                ch.remove();
            });
            // toolbar(false) is a documented option, and a table built with
            // it has no .gk-toolbar — so this threw on the first sort, page
            // click or GK.table.refresh(), after the loop above had already
            // removed the old rows. The wrapper was left empty, and because
            // the error is not a transport error the catch branch skips the
            // "Try again" fallback: a blank space and no way back short of
            // reloading. renderStatic() has guarded this all along, twelve
            // hundred lines up; this path never did.
            const anchor = bulkBar || toolbar;
            if (anchor) anchor.insertAdjacentHTML("afterend", html);
            else wrap.insertAdjacentHTML("afterbegin", html);
            _gkApplyReplacements(wrap);
            window.history.replaceState(window.history.state, "", url);
          })
          .catch((err) => {
            // Only transport errors lead to the error display. If the render
            // path throws (Safari throttles history.replaceState after about
            // 100 calls per 30 s, outerHTML can throw as well), the data has
            // long since been inserted correctly — replacing it with
            // "could not be loaded" would simply be wrong.
            if (!err || !err.gkTransport) {
              finish();
              if (window.console) console.error("GridKit: failed to insert the table", err);
              return;
            }
            if (!finish()) return;
            // Before this, a failed request stayed silent: the table kept on
            // showing the old data without anyone noticing.
            const body = wrap.querySelector(".gk-table tbody");
            const columnCount = wrap.querySelectorAll(".gk-table thead th").length || 1;
            if (body) {
              body.innerHTML =
                '<tr class="gk-empty-row"><td colspan="' + columnCount + '" class="gk-empty">' +
                '<div class="gk-empty-inner">' +
                '<span class="material-icons gk-empty-icon" aria-hidden="true">cloud_off</span>' +
                '<span class="gk-empty-title">' + (_lang["load_error"] || "The table could not be loaded.") + "</span>" +
                '<span class="gk-empty-action"><button type="button" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm" data-gk-retry>' +
                (_lang["retry"] || "Try again") + "</button></span>" +
                "</div></td></tr>";
              const button = body.querySelector("[data-gk-retry]");
              if (button) button.addEventListener("click", () => this.reload(wrap, {}));
            }
            wrap.dispatchEvent(new CustomEvent("gk-table-error", { bubbles: true, detail: { error: err } }));
          });
      },
      // One table by its id — the call you make after a modal save or a
      // delete. GRIDKIT_SKILL.md has documented this since 1.10; it never
      // existed, so every agent that followed the documentation wrote
      // GK.table.refresh('products') and got a TypeError.
      // Returns false when no table with that id is on the page.
      refresh(id, overrides) {
        const wrap = document.querySelector(
          '[data-gk-table="' + String(id).replace(/"/g, '\\"') + '"]',
        );
        if (!wrap) return false;
        this._refresh(wrap, overrides);
        return true;
      },

      refreshAll(overrides) {
        document
          .querySelectorAll("[data-gk-table]")
          .forEach((wrap) => this._refresh(wrap, overrides));
      },

      // Static tables re-render from the data they already hold; live ones
      // go back to the server. Both spellings of "refresh" want this choice.
      _refresh(wrap, overrides) {
        if (wrap.hasAttribute("data-gk-static") && wrap._gkData) {
          this.renderStatic(wrap);
        } else {
          this.reload(wrap, overrides || {});
        }
      },
    },

    init() {
      this.modal.init();
      this.table.init();
      if (this.sidebar && this.sidebar.init) {
        this.sidebar.init();
        this.sidebar.restoreState();
      }
      if (this.navigate && this.navigate.init) {
        this.navigate.init();
      }
      this.form.bind(document);
    },
  };

  // Toast system
  GK.toast = {
    container: null,
    ensure() {
      if (!this.container) {
        this.container = document.createElement("div");
        this.container.className = "gk-toast-container";
        /*
         * A toast is this library's entire feedback channel — "Saved.",
         * "Error while saving." — and it was announced to nobody at all. A
         * live region reads it out without moving focus, which is precisely
         * what a toast is for. Politely: it waits for a pause rather than
         * cutting into whatever is being read.
         */
        this.container.setAttribute("role", "status");
        this.container.setAttribute("aria-live", "polite");
        document.body.appendChild(this.container);
      }
    },
    show(message, type, duration) {
      this.ensure();
      type = type || "info";
      duration = duration || 3000;
      var icons = {
        success: "check_circle",
        error: "error",
        warning: "warning",
        info: "info",
      };
      var el = document.createElement("div");
      el.className = "gk-toast gk-toast-" + type;
      /*
       * The markup is literal and the message is not. It used to be pasted
       * into innerHTML, so `GK.toast.error(err.message)` — the obvious way to
       * use this — handed whatever the server said straight to the HTML
       * parser. Nothing in the documentation ever offered markup in a toast;
       * every example is a sentence. Text is text.
       *
       * The icon is aria-hidden or the ligature is read aloud as
       * "check_circle", and the close button had no name at all: what a
       * screen reader met was a button called "times".
       */
      el.innerHTML =
        '<span class="material-icons gk-toast-icon" aria-hidden="true">' +
        (icons[type] || "info") +
        "</span>" +
        '<span class="gk-toast-text"></span>' +
        '<button class="gk-toast-close" aria-label="' +
        _gkEsc(_t("close")) +
        '"><span aria-hidden="true">&times;</span></button>';
      el.querySelector(".gk-toast-text").textContent = message;
      el.querySelector(".gk-toast-close").onclick = function () {
        el.classList.add("gk-toast-out");
        setTimeout(function () {
          el.remove();
        }, 300);
      };
      this.container.appendChild(el);
      setTimeout(function () {
        if (el.parentNode) {
          el.classList.add("gk-toast-out");
          setTimeout(function () {
            el.remove();
          }, 300);
        }
      }, duration);
    },
    success(msg, dur) {
      this.show(msg, "success", dur);
    },
    error(msg, dur) {
      this.show(msg, "error", dur);
    },
    warning(msg, dur) {
      this.show(msg, "warning", dur);
    },
    info(msg, dur) {
      this.show(msg, "info", dur);
    },
  };

  /*
   * Announcements (since 1.93.0) — the line that says "Saved." where the
   * person is working, read out by a screen reader.
   *
   * A live region is only heard when it was in the accessibility tree before
   * its words changed. The usual way to write a status line — `hidden` until
   * the first message, then text and visibility in one step — creates the
   * region in the same moment it speaks, and VoiceOver and NVDA often stay
   * silent. So a .gk-announce region is never hidden: empty, the stylesheet
   * clips it to nothing (no box), and this only changes the words in it.
   *
   * The same words twice are no change at all, and nothing is read. For a
   * repeat — a second save, the same error again — the region is emptied and
   * the words come back a moment later: two changes, and the second is heard.
   *
   *   GK.announce(el, "Saved.", "success");   // el: element, id or selector
   *   GK.announce(el, "");                     // empty again, no box
   *
   * The tone (info, success, warning, error; default info) sets the
   * .gk-message-* colour when the region is a .gk-message. Text is text —
   * set as textContent, never parsed as HTML. GK.melde is the same function.
   */
  var _gkTones = ["info", "success", "warning", "error"];
  var _gkRepeatDelay = 150;

  function _gkAnnounceRegion(el, unhide) {
    el.classList.add("gk-announce");
    if (!el.hasAttribute("role")) el.setAttribute("role", "status");
    if (!el.hasAttribute("aria-live")) el.setAttribute("aria-live", el.getAttribute("role") === "alert" ? "assertive" : "polite");
    if (!el.hasAttribute("aria-atomic")) el.setAttribute("aria-atomic", "true");
    // A hidden region is no region. Taken off here, the next message is heard
    // — this one may not be; write the region without `hidden`.
    if (unhide && el.hidden) el.hidden = false;
  }

  GK.announce = function (target, text, tone) {
    var el = target;
    if (typeof target === "string") {
      el = document.getElementById(target.replace(/^#/, ""));
      if (!el) {
        try { el = document.querySelector(target); } catch (e) { el = null; }
      }
    }
    if (!el || !el.classList) return null;
    _gkAnnounceRegion(el, true);
    clearTimeout(el._gkAnnounceTimer);

    var words = text == null ? "" : String(text);
    var t = _gkTones.indexOf(tone) >= 0 ? tone : "info";
    if (el.classList.contains("gk-message")) {
      _gkTones.forEach(function (n) {
        el.classList.toggle("gk-message-" + n, words !== "" && n === t);
      });
    }
    if (words === "") {
      el.textContent = "";
      return el;
    }
    if (el.textContent === words) {
      el.textContent = "";
      el._gkAnnounceTimer = setTimeout(function () {
        el.textContent = words;
      }, _gkRepeatDelay);
    } else {
      el.textContent = words;
    }
    return el;
  };
  GK.melde = GK.announce;

  function _gkAnnounceInit(root) {
    (root || document).querySelectorAll(".gk-announce").forEach(function (el) {
      // `:empty` matches no child at all — a line break between the tags, as a
      // PHP or TS template writes it, is a text node, and the region drew the
      // very empty box it exists to avoid. Nothing but white space is no words:
      // emptied here, so the clip applies.
      var blank = !el.textContent.trim();
      if (blank && el.firstChild) el.textContent = "";
      // An empty region hidden in the markup is shown now, before any message;
      // one with words in it the page hid on purpose stays as it is.
      _gkAnnounceRegion(el, blank);
    });
  }

  // Sidebar
  GK.sidebar = {
    el: null,
    overlay: null,
    init() {
      this.el = document.querySelector("[data-gk-sidebar]");
      this.overlay = document.querySelector("[data-gk-sidebar-overlay]");
      if (!this.el) return;
      // Group toggles
      this.el.querySelectorAll("[data-gk-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => {
          var id = btn.getAttribute("data-gk-toggle");
          var sub = document.getElementById(id);
          if (!sub) return;
          var collapsed = sub.classList.toggle("collapsed");
          btn.classList.toggle("collapsed", collapsed);
          btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
          try {
            localStorage.setItem("gk-nav-" + id, collapsed ? "closed" : "open");
          } catch (e) {}
        });
        // Restore state
        var id = btn.getAttribute("data-gk-toggle");
        var sub = document.getElementById(id);
        if (!sub) return;
        var stored = localStorage.getItem("gk-nav-" + id);
        // The restore path is where the attribute drifts. The server renders
        // aria-expanded from the active page, localStorage remembers what this
        // visitor last did, and the two disagree the moment someone closes a
        // group and comes back. Setting only the class here would leave a
        // button that says "expanded" over a submenu that is shut.
        if (stored === "closed") {
          sub.classList.add("collapsed");
          btn.classList.add("collapsed");
          btn.setAttribute("aria-expanded", "false");
        } else if (stored === "open") {
          sub.classList.remove("collapsed");
          btn.classList.remove("collapsed");
          btn.setAttribute("aria-expanded", "true");
        }
      });
    },
    toggle() {
      if (!this.el) return;
      this.el.classList.toggle("open");
      if (this.overlay) this.overlay.classList.toggle("open");
      this._expanded();
    },
    close() {
      if (!this.el) return;
      this.el.classList.remove("open");
      if (this.overlay) this.overlay.classList.remove("open");
      this._expanded();
    },
    open() {
      if (!this.el) return;
      this.el.classList.add("open");
      if (this.overlay) this.overlay.classList.add("open");
      this._expanded();
    },
    /*
     * The header's menu button has said aria-expanded="false" since it was
     * written, and nothing ever changed it: open, the sidebar was announced as
     * closed. Every toggle that carries the attribute now follows the sidebar.
     */
    _expanded() {
      var open = this.el.classList.contains("open") ? "true" : "false";
      document.querySelectorAll('[data-gk-sidebar-action="toggle"][aria-expanded]').forEach(function (b) {
        b.setAttribute("aria-expanded", open);
      });
    },
    collapse() {
      if (!this.el) return;
      this.el.classList.toggle("collapsed");
      try {
        localStorage.setItem(
          "gk-sidebar-collapsed",
          this.el.classList.contains("collapsed") ? "1" : "0",
        );
      } catch (e) {}
    },
    restoreState() {
      if (!this.el) return;
      try {
        if (localStorage.getItem("gk-sidebar-collapsed") === "1")
          this.el.classList.add("collapsed");
      } catch (e) {}
    },
  };

  /*
   * The sidebar's controls, by delegation (1.92.0). Sidebar.php and Header.php
   * wrote onclick="GK.sidebar.toggle()" and friends — inline script, which a
   * Content-Security-Policy without 'unsafe-inline' refuses, and which every
   * page building its own shell copied (Vespera's admin carried three). They
   * write data-gk-sidebar-action now, and this one listener calls the same
   * GK.sidebar methods, which stay the API.
   */
  var _gkSidebarActions = { toggle: 1, close: 1, collapse: 1 };
  document.addEventListener("click", function (e) {
    var ctl = e.target.closest && e.target.closest("[data-gk-sidebar-action]");
    if (!ctl || e.defaultPrevented) return;
    var action = ctl.getAttribute("data-gk-sidebar-action");
    if (!_gkSidebarActions[action]) return;
    // A link written as the control must not also navigate.
    if (ctl.tagName === "A") e.preventDefault();
    GK.sidebar[action]();
  });

  // AJAX Navigation (SPA-lite)
  GK.navigate = {
    contentSelector: '[data-gk-content]',
    progressEl: null,

    init: function () {
      var sidebar = document.querySelector('[data-gk-ajax-nav]');
      if (!sidebar) return;

      var self = this;
      sidebar.querySelectorAll('.gk-sidebar-nav a[href]').forEach(function (link) {
        var href = link.getAttribute('href');
        // Only intercept internal links
        if (!href || href.startsWith('#') || href.startsWith('javascript')) return;
        if (href.startsWith('http') && !href.startsWith(location.origin)) return;
        if (link.target === '_blank') return;
        // Anchors on the CURRENT page (e.g. /reports#chat):
        // let the browser scroll natively — the AJAX loader would only re-render
        // the page and drop the fragment ("a click with no effect").
        var hashPos = href.indexOf('#');
        if (hashPos > -1 && (hashPos === 0 || href.slice(0, hashPos) === location.pathname)) return;

        // onclick instead of addEventListener — preventDefault IMMEDIATELY
        link.onclick = function (e) {
          if (e.ctrlKey || e.metaKey || e.shiftKey) return true;
          e.preventDefault();
          e.stopPropagation();
          self.load(href, true);
          return false;
        };
      });

      // Pure hash jumps (anchor clicks, back/forward between anchors) do NOT
      // change pathname+search — popstate must not trigger an AJAX reload then
      // (visible as an endless loading bar after every anchor click).
      this._lastPath = location.pathname + location.search;
      window.addEventListener('popstate', function () {
        var cur = location.pathname + location.search;
        if (cur === self._lastPath) return;
        self.load(location.href, false);
      });
    },

    load: function (url, pushState) {
      var self = this;
      var content = document.querySelector(this.contentSelector);
      if (!content) { location.href = url; return; }

      this.showProgress();

      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-GK-Ajax', '1');
      xhr.onload = function () {
        var res = { ok: xhr.status >= 200 && xhr.status < 400, text: function () { return xhr.responseText; } };
        if (!res.ok) { self.hideProgress(); location.href = url; return; }
        var html = xhr.responseText;
        // The styles first, the content after: _render stays synchronous on
        // purpose — consumers wrap it to re-initialise their own widgets.
        self._syncHead(html, function (cleanUp) {
          self._render(html, url, content, pushState);
          cleanUp();
        });
      };
      xhr.onerror = function () { self.hideProgress(); };
      xhr.send();
    },

    /**
     * Bring the target page's own styles along.
     *
     * Navigation swaps [data-gk-content] and the title — nothing else. A page
     * that links a stylesheet of its own in <head> arrived without it: reached
     * through the sidebar it was unstyled, reached by reload it was fine, which
     * is exactly the kind of fault nobody can reproduce on request. The SSI
     * Panel has 24 such pages.
     *
     * Missing stylesheets and <style> blocks from the new document's head are
     * added and awaited (1.5 s at most) BEFORE the content is swapped, so there
     * is no flash. What an earlier navigation added and the new page does not
     * carry is removed afterwards. Nothing else in <head> is touched: scripts
     * inject styles of their own there (editors, maps), and those must stay.
     */
    _syncHead: function (html, done) {
      var wanted = [], pending = 0, finished = false, timer = null;
      // By path, not by full address: consumers hang a cache key on their
      // stylesheets (?v=<mtime>). Compared in full, a file saved while a tab was
      // open counted as missing — a second copy went to the END of <head>, behind
      // stylesheets it is meant to precede, and the cascade tipped over. A tab
      // keeps the version it loaded until it reloads, as it always has.
      var key = function (el) {
        if (el.tagName !== 'LINK') return 'style:' + el.textContent;
        var u = new URL(el.getAttribute('href'), location.href);
        return 'link:' + u.origin + u.pathname;
      };
      var cleanUp = function () {
        document.head.querySelectorAll('[data-gk-nav-asset]').forEach(function (el) {
          if (wanted.indexOf(key(el)) === -1) el.remove();
        });
      };
      var go = function () {
        if (finished) return;
        finished = true;
        clearTimeout(timer);
        done(cleanUp);
      };
      try {
        var doc = new DOMParser().parseFromString(html || '', 'text/html');
        var have = {};
        document.head.querySelectorAll('link[rel~="stylesheet"][href], style').forEach(function (el) { have[key(el)] = true; });
        doc.head.querySelectorAll('link[rel~="stylesheet"][href], style').forEach(function (el) {
          var k = key(el);
          wanted.push(k);
          if (have[k]) return;
          have[k] = true;
          var copy = document.createElement(el.tagName.toLowerCase());
          Array.from(el.attributes).forEach(function (a) { copy.setAttribute(a.name, a.value); });
          copy.setAttribute('data-gk-nav-asset', '');
          if (el.tagName === 'STYLE') {
            copy.textContent = el.textContent;
          } else {
            pending++;
            copy.onload = copy.onerror = function () { if (--pending === 0) go(); };
          }
          document.head.appendChild(copy);
        });
      } catch (e) {
        if (window.console) console.warn('GK.navigate: head sync', e);
      }
      if (pending === 0) go();
      else timer = setTimeout(go, 1500);
    },

    _render: function (html, url, content, pushState) {
      var self = this;
      if (!html) { self.hideProgress(); return; }
      try {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var newContent = doc.querySelector(self.contentSelector);
        if (!newContent) { self.hideProgress(); location.href = url; return; }
        content.innerHTML = newContent.innerHTML;
        content.querySelectorAll('script').forEach(function (oldScript) {
          try {
            var newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function (attr) {
              newScript.setAttribute(attr.name, attr.value);
            });
            if (!oldScript.src) {
              newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            }
            oldScript.parentNode.replaceChild(newScript, oldScript);
          } catch (e) {
            console.warn('GK.navigate: script exec', e);
          }
        });
        var newTitle = doc.querySelector('title');
        if (newTitle) document.title = newTitle.textContent;
        // "Du bist hier" (Header::ortszeile, 1.100.0) stands in the header, outside
        // the swapped block: without this it kept naming the page one came from.
        var newLine = doc.querySelector('header .gk-ortszeile');
        var oldLine = document.querySelector('header .gk-ortszeile');
        if (newLine && oldLine) oldLine.replaceWith(document.importNode(newLine, true));
        if (pushState) history.pushState({ gkNav: true }, '', url);
        self._lastPath = location.pathname + location.search;
        self.updateActive(url);
        // Respect the target anchor from the URL (page change WITH a fragment) —
        // otherwise you always land at the top of the page after the content swap.
        var frag = (url.split('#')[1] || '');
        var anchor = frag ? document.getElementById(frag) : null;
        if (anchor) anchor.scrollIntoView(); else window.scrollTo(0, 0);
        try {
          // Each swap decides anew: a redirect that never navigated (a host's
          // beforeunload dialog, an iframe) must not mute every later swap.
          if (typeof GK.liveTable !== 'undefined') GK.liveTable._redirecting = false;
          // Every widget, tables and tooltips included — defined below this
          // point, hence the guard.
          if (typeof GK.initContent === 'function') GK.initContent(content);
          // BelegModal sits inside [data-gk-content] and is re-rendered on the
          // swap → the close button loses its listener. Bind it again.
          if (typeof GK.belegModal !== 'undefined' && GK.belegModal._init) GK.belegModal._init();
        } catch (e) {
          if (window.console) console.warn('GK.navigate: widget init after swap', e);
        }
        // A live table with a remembered filter answers the swap with a full
        // load of its own (restoreSession). Nothing to announce on a page that
        // is on its way out — and the progress bar stays for the load to come.
        if (typeof GK.liveTable !== 'undefined' && GK.liveTable._redirecting) return;
        // For page code that binds its own things after a swap.
        document.dispatchEvent(new CustomEvent('gk-ajax-nav', { detail: { url: url, content: content } }));
      } catch (err) {
        console.warn('GK.navigate: render error', err);
      }
      self.hideProgress();
    },

    updateActive: function (url) {
      var path = new URL(url, location.origin).pathname;
      var sidebar = document.querySelector('[data-gk-ajax-nav]');
      if (!sidebar) return;

      sidebar.querySelectorAll('.gk-sidebar-item.active, .gk-sidebar-subitem.active')
        .forEach(function (el) { el.classList.remove('active'); });

      var bestMatch = null;
      var bestLen = 0;
      sidebar.querySelectorAll('.gk-sidebar-nav a[href]').forEach(function (link) {
        var linkPath = link.getAttribute('href');
        if (path.startsWith(linkPath) && linkPath.length > bestLen) {
          bestMatch = link;
          bestLen = linkPath.length;
        }
      });
      if (bestMatch) bestMatch.classList.add('active');
    },

    createProgress: function () {},
    showProgress: function () {},
    hideProgress: function () {}
  };

  /*
   * Confirm dialog (replaces window.confirm)
   *
   * Four things were wrong, and the last one is not an accessibility problem:
   *
   *   - No role, no aria-modal, no accessible name. A box on top of the page,
   *     as far as anything reading it was concerned.
   *   - Focus landed on the OK button, and then Tab walked straight out of the
   *     dialog into the page behind it — the same overlay the modal and the
   *     lightbox now hold focus inside.
   *   - Closing gave focus back to nobody, so the next Tab started at the top
   *     of the document, far from whatever the person had been doing.
   *   - The Escape listener was added to `document` on every call and removed
   *     only in the Escape branch. Answer with a button and it stayed
   *     registered, holding the closure and the removed overlay, one more on
   *     every confirm for as long as the page lived.
   *
   * The title and the message are set as text. They were pasted into
   * innerHTML, and a confirm message is exactly where a record's name ends up
   * — `GK.confirm('Delete ' + row.name + '?')`.
   */
  GK.confirm = function (message, options) {
    options = options || {};
    return new Promise(function (resolve) {
      var opener = document.activeElement;
      var overlay = document.createElement("div");
      overlay.className = "gk-confirm-overlay";
      // Same counter idea the modal uses for its title id — two confirms in
      // one page must not point aria-labelledby at the same heading.
      var titleId = "gk-confirm-title-" + (GK.confirm._seq = (GK.confirm._seq || 0) + 1);
      overlay.setAttribute("role", "dialog");
      overlay.setAttribute("aria-modal", "true");
      overlay.setAttribute("aria-labelledby", titleId);
      var title = options.title || _t("confirm_title");
      var confirmText = options.confirmText || _t("confirm_ok");
      var cancelText = options.cancelText || _t("confirm_cancel");
      var confirmClass = options.danger
        ? "gk-btn gk-btn-danger"
        : "gk-btn gk-btn-primary";
      overlay.innerHTML =
        '<div class="gk-confirm-box">' +
        '<div class="gk-confirm-header"><h3 id="' + titleId + '"></h3></div>' +
        '<div class="gk-confirm-body"><p></p></div>' +
        '<div class="gk-confirm-footer">' +
        '<button class="gk-btn gk-confirm-cancel"></button>' +
        '<button class="' +
        confirmClass +
        ' gk-confirm-ok"></button>' +
        "</div></div>";
      overlay.querySelector("h3").textContent = title;
      overlay.querySelector(".gk-confirm-body p").textContent = message;
      overlay.querySelector(".gk-confirm-cancel").textContent = cancelText;
      overlay.querySelector(".gk-confirm-ok").textContent = confirmText;
      document.body.appendChild(overlay);

      // One way out, so the listener cannot outlive the dialog and focus
      // cannot be left behind on any of the four paths.
      function close(answer) {
        document.removeEventListener("keydown", onKey);
        overlay.remove();
        _gkRestoreFocus(opener);
        resolve(answer);
      }
      function onKey(e) {
        if (e.key === "Escape") close(false);
      }

      overlay.querySelector(".gk-confirm-cancel").onclick = function () {
        close(false);
      };
      overlay.querySelector(".gk-confirm-ok").onclick = function () {
        close(true);
      };
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) close(false);
      });
      overlay.addEventListener("keydown", function (e) {
        _gkTrap(overlay, e);
      });
      document.addEventListener("keydown", onKey);
      setTimeout(function () {
        overlay.querySelector(".gk-confirm-ok").focus();
      }, 50);
    });
  };

  /*
   * === SIDE SHEET ===
   *
   * A row shows values; the side sheet is where a record is read in full and
   * changed. Two admin lists put a select, a switch and a label into every
   * cell because there was nowhere else to put them (Vespera, 23.09.2026).
   *
   *   GK.sheet.open('user-sheet', { returnFocus: el })   // id, "#id" or element
   *   GK.sheet.close()
   *   <button data-gk-sheet="user-sheet" data-gk-params='{"id":7}'>…</button>
   *
   * On a wide screen the sheet docks beside the list and the list stays in
   * use: a non-modal dialog. No aria-modal there — it would declare the page
   * out of bounds while the page is still being used — and no focus trap.
   * Below 769px it covers the screen and IS modal: aria-modal, the trap, and
   * the page behind stops scrolling (that part is CSS, keyed to the hidden
   * attribute). The width decides, as in the stylesheet, and crossing it while
   * the sheet is open switches the behaviour along.
   *
   * One at a time: opening another closes the first without handing the focus
   * back, because the focus goes into the new one. Escape is heard on the sheet
   * itself, not on the document — so it closes the sheet only while the focus
   * is in it, a widget inside that handled the key keeps it (defaultPrevented),
   * and a modal or confirm opened from the sheet lies outside it and answers
   * for itself.
   */
  var _gkSheetMq = typeof window.matchMedia === "function"
    ? window.matchMedia("(max-width: 768px)")
    : null;

  GK.sheet = {
    _open: null,
    _seq: 0,

    /** A sheet by element, id or "#id". */
    _find(target) {
      if (target && target.nodeType === 1) return target;
      if (typeof target !== "string" || target === "") return null;
      var el = document.getElementById(target.replace(/^#/, ""));
      if (!el) {
        try { el = document.querySelector(target); } catch (err) { el = null; }
      }
      return el;
    },

    /** Full screen and modal right now? The stylesheet's breakpoint, asked live. */
    _modal() {
      return !!(_gkSheetMq && _gkSheetMq.matches);
    },

    /** The open sheet — null as well when AJAX navigation took it out of the page. */
    _current() {
      if (this._open && !this._open.isConnected) this._open = null;
      return this._open;
    },

    /**
     * Role, name and a named close button, where the page left them out.
     * Idempotent: runs on page load, for AJAX content (GK.initContent) and
     * before every open. A sheet the server rendered open is taken on as the
     * open one, so Escape and the close button work on it too.
     */
    upgrade(root) {
      var scope = root && root.querySelectorAll ? root : document;
      var sheets = Array.prototype.slice.call(scope.querySelectorAll(".gk-sheet"));
      if (scope.classList && scope.classList.contains("gk-sheet")) sheets.unshift(scope);
      var self = this;
      sheets.forEach(function (sheet) {
        if (!sheet.hasAttribute("role")) sheet.setAttribute("role", "dialog");
        if (!sheet.hasAttribute("aria-label") && !sheet.hasAttribute("aria-labelledby")) {
          // A dialog without a name is worse than none: "dialog", and nothing after it.
          var heading = sheet.querySelector(".gk-sheet-title") || sheet.querySelector("h1, h2, h3, h4, h5, h6");
          if (heading) {
            if (!heading.id) heading.id = "gk-sheet-title-" + ++self._seq;
            sheet.setAttribute("aria-labelledby", heading.id);
          }
        }
        sheet.querySelectorAll(".gk-sheet-close").forEach(_gkNameCloseButton);
        self._bind(sheet);
        if (!sheet.hidden && sheet.isConnected && !self._current()) {
          self._open = sheet;
          self._syncModal();
        }
      });
    },

    _bind(sheet) {
      if (sheet._gkSheetBound) return;
      sheet._gkSheetBound = true;
      var self = this;
      sheet.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          if (e.defaultPrevented || self._current() !== sheet) return;
          // A layer opened from the sheet answers first, even while the focus
          // has not reached it yet — GK.confirm moves it there 50ms late, and
          // an Escape in between closed the question AND the sheet. A modal
          // counts when it came after the sheet, not one the sheet lies over.
          if (_gkLayerAbove() || (GK.modal && GK.modal.stack.length > (sheet._gkOverModals || 0))) return;
          // Claimed: a modal underneath must not close along with it.
          e.preventDefault();
          self.close(sheet);
          return;
        }
        if (self._current() === sheet && self._modal()) _gkTrap(sheet, e);
      });
      sheet.addEventListener("click", function (e) {
        var hit = e.target.closest && e.target.closest(".gk-sheet-close, [data-gk-sheet-close]");
        // Only a close button of THIS sheet — the rule the static modal follows.
        if (!hit || hit.closest(".gk-sheet") !== sheet) return;
        // Rendered by a server as a plain link, it closes in place here.
        e.preventDefault();
        self.close(sheet);
      });
    },

    /*
     * Where the focus starts: the title, when there is one. A sheet opens on
     * every row click, and the modal's way — the first control, the close
     * button — drew a focus ring on it each time a mouse opened it. The title
     * is what a screen reader should read first anyway; one Tab reaches the
     * close button. Focusable by script only (tabindex -1), so it never enters
     * the tab order, and the trap wraps from it (see _gkTrap).
     */
    _focusStart(sheet) {
      var title = sheet.querySelector(".gk-sheet-title");
      if (!title) return _gkFocusInto(sheet);
      if (!title.hasAttribute("tabindex")) title.setAttribute("tabindex", "-1");
      try { title.focus({ preventScroll: true }); } catch (err) { title.focus(); }
    },

    /** aria-modal only while it is true; a sheet that turns modal takes the focus. */
    _syncModal() {
      var sheet = this._current();
      if (!sheet) return;
      if (this._modal()) {
        sheet.setAttribute("aria-modal", "true");
        if (!sheet.contains(document.activeElement)) this._focusStart(sheet);
      } else {
        sheet.removeAttribute("aria-modal");
      }
    },

    /**
     * Show a sheet. opts: returnFocus (default: what has the focus now),
     * focus (selector or element inside, default: the title — _focusStart),
     * params (handed to gk:sheetopen and posted with url), url (its answer
     * fills .gk-sheet-body), title (replaces .gk-sheet-title's text).
     * Returns the sheet, or null when there is no .gk-sheet by that name.
     */
    open(target, opts) {
      var sheet = this._find(target);
      if (!sheet || !sheet.classList || !sheet.classList.contains("gk-sheet")) return null;
      opts = opts || {};
      var prev = this._current();
      var active = document.activeElement;
      var back = opts.returnFocus || (active && active !== document.body ? active : null);
      // Opened from inside the sheet on screen: that element is about to be
      // hidden, or belongs to the sheet itself. The focus goes back to what
      // opened the first one.
      if (prev && back && prev.contains(back)) back = prev._gkReturn || null;
      if (prev && prev !== sheet) this._hide(prev, false);
      // The same sheet for another row: the old row is no longer the one shown.
      this._unmark(sheet);
      sheet._gkReturn = back;
      var row = back && back.closest ? back.closest("tr.gk-row-link") : null;
      if (row) {
        row.setAttribute("aria-current", "true");
        sheet._gkRow = row;
      }

      this.upgrade(sheet);
      if (opts.title != null) {
        var titleEl = sheet.querySelector(".gk-sheet-title");
        if (titleEl) titleEl.textContent = opts.title;
      }
      sheet.removeAttribute("hidden");
      this._open = sheet;
      // Opened from inside a modal it has to lie above it, or it opens unseen
      // underneath and takes the focus there. Same ladder as the modals.
      var stack = GK.modal && GK.modal.stack ? GK.modal.stack.length : 0;
      sheet.style.zIndex = stack ? String(9000 + stack * 10 + 5) : "";
      sheet._gkOverModals = stack;
      if (this._modal()) sheet.setAttribute("aria-modal", "true");
      else sheet.removeAttribute("aria-modal");

      var params = opts.params || {};
      // Before the focus moves: a page that fills the sheet here — its title
      // included — has it read out filled, not with the record before.
      sheet.dispatchEvent(new CustomEvent("gk:sheetopen", {
        bubbles: true,
        detail: { opener: back, params: params },
      }));
      var first = opts.focus
        ? typeof opts.focus === "string" ? sheet.querySelector(opts.focus) : opts.focus
        : null;
      if (first && first.focus) {
        try { first.focus({ preventScroll: true }); } catch (err) { first.focus(); }
      } else if (!sheet.contains(document.activeElement)) {
        this._focusStart(sheet);
      }
      if (opts.url) this._load(sheet, opts.url, params);
      return sheet;
    },

    /** Close the open sheet. With an argument: only if that is the open one. */
    close(target) {
      var sheet = target ? this._find(target) : this._current();
      if (!sheet || sheet !== this._current()) return;
      this._hide(sheet, true);
    },

    _unmark(sheet) {
      if (sheet._gkRow) {
        sheet._gkRow.removeAttribute("aria-current");
        sheet._gkRow = null;
      }
    },

    _hide(sheet, giveBack) {
      var active = document.activeElement;
      // Back to the opener only when the focus is in the sheet (or nowhere):
      // someone who clicked into the page beside a docked sheet stays there.
      var focusInside = !active || active === document.body || sheet.contains(active);
      sheet.setAttribute("hidden", "");
      sheet.removeAttribute("aria-modal");
      sheet.style.zIndex = "";
      // An answer still on its way must not fill a sheet that has closed.
      sheet._gkLoad = (sheet._gkLoad || 0) + 1;
      this._unmark(sheet);
      if (this._open === sheet) this._open = null;
      var back = sheet._gkReturn;
      sheet._gkReturn = null;
      if (giveBack && focusInside) _gkRestoreFocus(back);
      sheet.dispatchEvent(new CustomEvent("gk:sheetclose", {
        bubbles: true,
        detail: { opener: back || null },
      }));
    },

    /*
     * The body from a URL, as GK.modal.open() loads a modal's: POST with the
     * params, X-Requested-With, and the widgets inside bound. Clicking through
     * rows fast sends several requests; only the last one may fill the sheet.
     */
    _load(sheet, url, params) {
      var body = sheet.querySelector(".gk-sheet-body");
      if (!body) return;
      var n = (sheet._gkLoad = (sheet._gkLoad || 0) + 1);
      body.innerHTML = "";
      body.classList.add("gk-loading");
      body.setAttribute("aria-busy", "true");
      var fd = new FormData();
      Object.keys(params || {}).forEach(function (k) { fd.append(k, params[k]); });
      var done = function () {
        body.classList.remove("gk-loading");
        body.removeAttribute("aria-busy");
      };
      fetch(url, {
        method: "POST",
        body: fd,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(function (r) {
          // The body of a 500 or of a firewall's 403 is not the record.
          if (!r.ok) throw new Error("HTTP " + r.status);
          return r.text();
        })
        .then(function (html) {
          if (sheet._gkLoad !== n) return;
          done();
          body.innerHTML = html;
          GK.initContent(body);
        })
        .catch(function () {
          if (sheet._gkLoad !== n) return;
          done();
          body.innerHTML = '<p class="gk-text-danger">' + _gkEsc(_t("error_loading")) + "</p>";
        });
    },
  };

  if (_gkSheetMq) {
    var _gkSheetMqChange = function () { GK.sheet._syncModal(); };
    if (typeof _gkSheetMq.addEventListener === "function") _gkSheetMq.addEventListener("change", _gkSheetMqChange);
    else if (typeof _gkSheetMq.addListener === "function") _gkSheetMq.addListener(_gkSheetMqChange);
  }

  // A trigger in the markup: data-gk-sheet names the sheet, data-gk-params,
  // data-gk-sheet-url and data-gk-sheet-title fill in the options. Delegated,
  // so rows a table rebuilds keep working. An <a href> trigger keeps its
  // address for Ctrl-click, Shift-click and a page without JavaScript — only
  // its plain click opens the sheet. A button has no such second meaning.
  document.addEventListener("click", function (e) {
    if (e.defaultPrevented || e.button !== 0) return;
    var t = e.target.closest && e.target.closest("[data-gk-sheet]");
    if (!t) return;
    if (t.tagName === "A" && t.hasAttribute("href") && (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey)) return;
    var params = {};
    try { params = t.dataset.gkParams ? JSON.parse(t.dataset.gkParams) : {}; } catch (err) { params = {}; }
    var opened = GK.sheet.open(t.getAttribute("data-gk-sheet"), {
      returnFocus: t,
      params: params,
      url: t.getAttribute("data-gk-sheet-url") || null,
      title: t.hasAttribute("data-gk-sheet-title") ? t.getAttribute("data-gk-sheet-title") : null,
    });
    if (opened) e.preventDefault();
  });

  // A sheet belongs to the page it was opened on.
  document.addEventListener("gk-ajax-nav", function () {
    var open = GK.sheet._current();
    if (open) GK.sheet._hide(open, false);
  });

  /*
   * === ROW LINKS === (tr.gk-row-link)
   *
   * A click anywhere in the row is a click on its one control, .gk-row-target,
   * with the button and the modifier keys it came with — so Ctrl-click and a
   * middle click open a link target in a new tab, as they would on the link
   * itself (measured in Chromium). What the row leaves alone: a click on a
   * control of its own (a checkbox, a row button, a second link), a click in
   * the selection or the action column, and the end of a text selection —
   * someone copying an address out of a row must not be taken elsewhere.
   */
  function _gkRowClick(e) {
    if (e.defaultPrevented) return;
    var row = e.target.closest && e.target.closest("tr.gk-row-link");
    if (!row) return;
    var own = e.target.closest(
      "a[href], button, input, select, textarea, label, summary, [tabindex], [contenteditable], td.gk-cb-col, td.gk-actions",
    );
    if (own && row.contains(own)) return;
    var sel = window.getSelection ? window.getSelection() : null;
    if (sel && !sel.isCollapsed && String(sel).trim() !== "" && row.contains(sel.anchorNode)) return;
    var target = row.querySelector(".gk-row-target");
    if (!target) return;
    if (target.tagName === "A" && target.hasAttribute("href")) {
      target.dispatchEvent(new MouseEvent("click", {
        bubbles: true, cancelable: true, view: window, button: e.button,
        ctrlKey: e.ctrlKey, metaKey: e.metaKey, shiftKey: e.shiftKey, altKey: e.altKey,
      }));
    } else if (e.button === 0) {
      target.click();
    }
  }
  document.addEventListener("click", function (e) { if (e.button === 0) _gkRowClick(e); });
  document.addEventListener("auxclick", function (e) { if (e.button === 1) _gkRowClick(e); });

  /*
   * === SUMMARY ROWS === (tr.gk-table-more)
   *
   * One row standing for many: its .gk-table-more-toggle shows and hides every
   * element its aria-controls names — usually a <tbody hidden> right after it.
   * The hidden attribute is the state of the rows, aria-expanded the state of
   * the toggle; this is the one place that moves both.
   */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest && e.target.closest(".gk-table-more-toggle");
    if (!btn || e.defaultPrevented) return;
    var parts = (btn.getAttribute("aria-controls") || "").split(/\s+/)
      .map(function (id) { return id ? document.getElementById(id) : null; })
      .filter(Boolean);
    if (!parts.length) return;
    var open = btn.getAttribute("aria-expanded") !== "true";
    parts.forEach(function (p) {
      if (open) p.removeAttribute("hidden");
      else p.setAttribute("hidden", "");
    });
    btn.setAttribute("aria-expanded", open ? "true" : "false");
  });

  // === RANGE SLIDERS ===
  GK.initRangeSliders = function () {
    document.querySelectorAll(".gk-range").forEach(function (input) {
      if (input._gkInit) return;
      input._gkInit = true;
      var output = input.parentElement.querySelector(".gk-range-value");
      var update = function () {
        if (output) output.textContent = input.value;
        // Fill left side with primary color
        var pct = ((input.value - input.min) / (input.max - input.min)) * 100;
        input.style.background =
          "linear-gradient(to right, var(--gk-primary) " +
          pct +
          "%, var(--gk-neutral-200) " +
          pct +
          "%)";
      };
      input.addEventListener("input", update);
      update();
    });
  };

  // === FILE UPLOAD ZONES ===
  GK.initUploadZones = function () {
    document.querySelectorAll(".gk-upload-zone").forEach(function (zone) {
      if (zone._gkInit) return;
      zone._gkInit = true;

      zone.addEventListener("dragover", function (e) {
        e.preventDefault();
        zone.classList.add("gk-dragover");
      });
      zone.addEventListener("dragleave", function (e) {
        if (!zone.contains(e.relatedTarget))
          zone.classList.remove("gk-dragover");
      });
      zone.addEventListener("drop", function (e) {
        e.preventDefault();
        zone.classList.remove("gk-dragover");
        var files = e.dataTransfer && e.dataTransfer.files;
        if (files && files.length) GK._uploadZoneValidate(zone, files);
      });

      var input = zone.querySelector(".gk-upload-input");
      if (input) {
        input.addEventListener("change", function () {
          if (this.files && this.files.length)
            GK._uploadZoneValidate(zone, this.files);
          this.value = "";
        });
      }

      // Create queue container if not present
      if (
        !zone.nextElementSibling ||
        !zone.nextElementSibling.classList.contains("gk-upload-queue")
      ) {
        var q = document.createElement("div");
        q.className = "gk-upload-queue";
        zone.insertAdjacentElement("afterend", q);
      }
    });
  };

  // ── Helper functions ─────────────────────────────────────────
  GK._parseSize = function (str) {
    if (!str) return 0;
    var m = String(str)
      .trim()
      .match(/^([\d.]+)\s*(B|KB|MB|GB)?$/i);
    if (!m) return 0;
    var n = parseFloat(m[1]);
    var units = { B: 1, KB: 1024, MB: 1048576, GB: 1073741824 };
    return Math.round(n * (units[(m[2] || "B").toUpperCase()] || 1));
  };

  GK._formatSize = function (bytes) {
    if (bytes >= 1048576) return Math.round(bytes / 104857.6) / 10 + " MB";
    if (bytes >= 1024) return Math.round(bytes / 102.4) / 10 + " KB";
    return bytes + " B";
  };

  // ── Validation ───────────────────────────────────────────────
  GK._uploadZoneValidate = function (zone, fileList) {
    var cfg = {
      maxSize: GK._parseSize(zone.dataset.gkMaxSize),
      minSize: GK._parseSize(zone.dataset.gkMinSize),
      maxTotalSize: GK._parseSize(zone.dataset.gkMaxTotalSize),
      maxFiles: parseInt(zone.dataset.gkMaxFiles) || 0,
      accept: (zone.dataset.gkAccept || "")
        .toLowerCase()
        .split(",")
        .map(function (s) {
          return s.trim().replace(/^\./, "");
        })
        .filter(Boolean),
    };

    var files = Array.from(fileList);
    var accepted = [];
    var errors = [];

    // A single-file field. The <input> carries the native `multiple`
    // attribute, but a drop never goes through the input — the files come
    // straight off the DataTransfer — so without this a drop of five files
    // onto a single-file field queued all five.
    if (!zone.hasAttribute("data-gk-multiple") && files.length > 1) {
      errors.push(_t("one_file_only", { m: files.length }));
      files = files.slice(0, 1);
    }

    // Max file count
    if (cfg.maxFiles > 0 && files.length > cfg.maxFiles) {
      errors.push(_t("max_files", { n: cfg.maxFiles, m: files.length }));
      files = files.slice(0, cfg.maxFiles);
    }

    files.forEach(function (f) {
      var ext = (f.name.split(".").pop() || "").toLowerCase();
      if (cfg.accept.length && !cfg.accept.includes(ext)) {
        errors.push(_t("format_not_allowed", { name: f.name, ext: ext }));
        return;
      }
      if (cfg.maxSize > 0 && f.size > cfg.maxSize) {
        errors.push(
          _t("too_large", {
            name: f.name,
            size: GK._formatSize(f.size),
            max: zone.dataset.gkMaxSize,
          }),
        );
        return;
      }
      if (cfg.minSize > 0 && f.size < cfg.minSize) {
        errors.push(
          _t("too_small", {
            name: f.name,
            size: GK._formatSize(f.size),
            min: zone.dataset.gkMinSize,
          }),
        );
        return;
      }
      accepted.push(f);
    });

    // Max total size
    if (cfg.maxTotalSize > 0 && accepted.length) {
      var total = accepted.reduce(function (s, f) {
        return s + f.size;
      }, 0);
      if (total > cfg.maxTotalSize) {
        errors.push(
          _t("total_size_exceeded", {
            size: GK._formatSize(total),
            max: zone.dataset.gkMaxTotalSize,
          }),
        );
        accepted = [];
      }
    }

    errors.forEach(function (msg) {
      GK.toast && GK.toast.error(msg);
    });
    if (!accepted.length) return;

    // Create queue items + fire event
    var items = GK._uploadQueueAdd(zone, accepted);
    zone.dispatchEvent(
      new CustomEvent("gk:files", {
        bubbles: true,
        detail: { files: accepted, items: items, zone: zone },
      }),
    );
  };

  // ── Queue UI ─────────────────────────────────────────────────
  GK._uploadQueueAdd = function (zone, files) {
    var queue = zone.nextElementSibling;
    if (!queue || !queue.classList.contains("gk-upload-queue")) return [];
    var withPreview = zone.hasAttribute("data-gk-preview");
    var items = [];

    files.forEach(function (file) {
      var id = "gkuq-" + Math.random().toString(36).slice(2, 9);
      var ext = (file.name.split(".").pop() || "").toLowerCase();
      var isImg = /^(jpg|jpeg|png|gif|webp|svg)$/.test(ext);

      var item = document.createElement("div");
      item.className = "gk-uq-item gk-uq-pending";
      item.dataset.gkUqId = id;

      // Thumb
      var thumb = document.createElement("div");
      thumb.className = "gk-uq-thumb";
      if (isImg && withPreview) {
        var img = document.createElement("img");
        img.className = "gk-uq-img";
        var reader = new FileReader();
        reader.onload = function (e) {
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        thumb.appendChild(img);
      } else {
        var icon = document.createElement("span");
        icon.className = "material-icons gk-uq-icon";
        icon.textContent = GK._uploadFileIcon(ext);
        thumb.appendChild(icon);
      }
      item.appendChild(thumb);

      // Info
      var info = document.createElement("div");
      info.className = "gk-uq-info";
      var name = document.createElement("span");
      name.className = "gk-uq-name";
      name.textContent = file.name;
      name.title = file.name;
      var size = document.createElement("span");
      size.className = "gk-uq-size";
      size.textContent = GK._formatSize(file.size);
      info.appendChild(name);
      info.appendChild(size);
      item.appendChild(info);

      // Status
      var status = document.createElement("span");
      status.className = "gk-uq-status";
      status.textContent = _t("ready");
      item.appendChild(status);

      // Remove button (only in the pending state)
      var rm = document.createElement("button");
      rm.type = "button";
      rm.className = "gk-uq-remove";
      rm.innerHTML =
        '<span class="material-icons" style="font-size:16px;">close</span>';
      rm.title = _t("remove");
      rm.addEventListener("click", function () {
        item.classList.add("gk-uq-removing");
        setTimeout(function () {
          item.remove();
        }, 200);
      });
      item.appendChild(rm);

      queue.appendChild(item);
      items.push({ file: file, el: item, id: id });
    });

    return items;
  };

  GK._uploadFileIcon = function (ext) {
    var map = {
      pdf: "picture_as_pdf",
      doc: "description",
      docx: "description",
      xls: "table_chart",
      xlsx: "table_chart",
      zip: "folder_zip",
      rar: "folder_zip",
      gz: "folder_zip",
      mp3: "audio_file",
      wav: "audio_file",
      mp4: "video_file",
      mov: "video_file",
      txt: "article",
      csv: "table_rows",
    };
    return map[ext] || "insert_drive_file";
  };

  // ── Queue status helpers (for app code) ──────────────────────
  GK.uqSetUploading = function (item) {
    item.el.className = "gk-uq-item gk-uq-uploading";
    item.el.querySelector(".gk-uq-status").innerHTML =
      '<span class="material-icons gk-spin" style="font-size:14px;vertical-align:middle;">sync</span> ' +
      _t("uploading");
    var rm = item.el.querySelector(".gk-uq-remove");
    if (rm) rm.style.display = "none";
  };

  GK.uqSetDone = function (item, label) {
    item.el.className = "gk-uq-item gk-uq-done";
    item.el.querySelector(".gk-uq-status").textContent =
      label || _t("uploaded");
    var rm = item.el.querySelector(".gk-uq-remove");
    if (rm) rm.style.display = "none";
    setTimeout(function () {
      item.el.classList.add("gk-uq-removing");
      setTimeout(function () {
        item.el.remove();
      }, 300);
    }, 2500);
  };

  GK.uqSetError = function (item, msg) {
    item.el.className = "gk-uq-item gk-uq-error";
    item.el.querySelector(".gk-uq-status").textContent =
      msg || _t("error_upload");
    var rm = item.el.querySelector(".gk-uq-remove");
    if (rm) rm.style.display = "";
  };

  // Legacy helpers (backwards compatibility)
  GK.uploadZoneBusy = function (zone, label) {
    var idle = zone.querySelector(".gk-upload-idle");
    var prog = zone.querySelector(".gk-upload-progress");
    if (idle) idle.style.display = "none";
    if (prog) prog.style.display = "flex";
    if (label) {
      var l = zone.querySelector(".gk-upload-progress-label");
      if (l) l.textContent = label;
    }
  };
  GK.uploadZoneIdle = function (zone) {
    var idle = zone.querySelector(".gk-upload-idle");
    var prog = zone.querySelector(".gk-upload-progress");
    if (idle) idle.style.display = "";
    if (prog) prog.style.display = "none";
  };

  // === RICHTEXT EDITOR ===
  // gk-richtext is now initialised via CKEditor5 (see Form.php)
  GK.initRichtext = function () {};

  // === LIVE TABLE ===
  //
  // AJAX-filtered table views. Search + filter + sort + pagination without a
  // full-page reload. The cursor stays put while typing, the URL is kept in
  // sync via replaceState.
  //
  // Usage (example):
  //   <div id="my-tbl" data-gk-live-table="/invoices">
  //     <!-- table, sort headers, pagination — all AJAX-swappable -->
  //   </div>
  //   <input data-gk-live-input="my-tbl" name="q">
  //   <select data-gk-live-input="my-tbl" name="status">...</select>
  //
  // On X-Requested-With: XMLHttpRequest or ?partial=1 the controller must
  // deliver the container content only (no layout).
  //
  GK.liveTable = {
    init: function (root) {
      var r = root || document;
      r.querySelectorAll("[data-gk-live-table]").forEach(function (c) {
        GK.liveTable.bind(c);
        GK.liveTable.hoistPager(c);
        GK.liveTable.restoreSession(c);
      });
      r.querySelectorAll("[data-gk-live-input]").forEach(function (inp) {
        GK.liveTable.bindInput(inp);
      });
      GK.liveTable.patchNavSelects(r);
      GK.liveTable.bindOutsidePager();
    },
    // The server pager belongs as a sibling BELOW .gk-table-wrap (like invoices).
    // If it still sits inside the live container (older views), lift it out here.
    hoistPager: function (container) {
      if (!container || !container.querySelector) return;
      var incoming = container.querySelector("[data-gk-pager]");
      if (!incoming) return;
      var key = incoming.getAttribute("data-gk-pager") || container.id || "";
      var existing = null;
      if (key) {
        document.querySelectorAll("[data-gk-pager=\"" + key + "\"]").forEach(function (el) {
          if (el !== incoming) existing = el;
        });
      }
      var wrap = container.closest(".gk-table-wrap") || container;
      if (existing) existing.replaceWith(incoming);
      else wrap.after(incoming);
    },
    // Clicks on the lifted-out pager (outside the live container) go via AJAX.
    bindOutsidePager: function () {
      if (document._gkLivePagerBound) return;
      document._gkLivePagerBound = true;
      document.addEventListener("click", function (e) {
        var a = e.target.closest("[data-gk-live-pager] a.gk-pg[href]");
        if (!a) return;
        if (a.target === "_blank" || e.ctrlKey || e.metaKey || e.shiftKey) return;
        var nav = a.closest("[data-gk-live-pager]");
        var id = nav && nav.getAttribute("data-gk-live-pager");
        var container = id ? document.getElementById(id) : null;
        if (!container || !container.dataset.gkLiveTable) return;
        var urlObj;
        try { urlObj = new URL(a.getAttribute("href"), window.location.origin); } catch (_) { return; }
        e.preventDefault();
        e.stopPropagation();
        GK.liveTable.loadUrl(container, urlObj);
      });
    },
    // Session persistence: when the URL carries no filters (sidebar click),
    // restore the state stored for the current session.
    // IMPORTANT: a full redirect instead of AJAX, so that all the outer elements
    // (dropdowns, pagination) are rendered correctly by PHP.
    restoreSession: function (container) {
      if (container._gkLiveRestored) return;
      container._gkLiveRestored = true;
      try {
        var saved = sessionStorage.getItem("gkLive:" + container.id);
        if (!saved) return;
        if (window.location.search && window.location.search.length > 1) return;
        var baseUrl = container.dataset.gkLiveTable || window.location.pathname;
        var restored = baseUrl + (saved.charAt(0) === "?" ? saved : "?" + saved);
        var urlObj = new URL(restored, window.location.origin);
        if (urlObj.search) {
          GK.liveTable._redirecting = true;   // GK.navigate reads this after a swap
          window.location.replace(restored);
        }
      } catch (e) {}
    },
    saveSession: function (container) {
      try { sessionStorage.setItem("gkLive:" + container.id, window.location.search); } catch (e) {}
    },
    bind: function (container) {
      if (container._gkLiveBound) return;
      container._gkLiveBound = true;
      // Save the session right away when the URL carries filters (e.g. a direct link)
      if (window.location.search && window.location.search.length > 1) {
        GK.liveTable.saveSession(container);
      }
      container.addEventListener("click", function (e) {
        var a = e.target.closest("a[href]");
        if (!a) return;
        var href = a.getAttribute("href");
        if (!href || href.startsWith("#") || href.startsWith("javascript:")) return;
        if (a.target === "_blank" || e.ctrlKey || e.metaKey || e.shiftKey) return;
        var baseUrl = container.dataset.gkLiveTable;
        if (!baseUrl) return;
        var urlObj;
        try { urlObj = new URL(href, window.location.origin); } catch (_) { return; }
        var basePath = new URL(baseUrl, window.location.origin).pathname;
        if (urlObj.pathname !== basePath) return;
        e.preventDefault();
        e.stopPropagation();
        GK.liveTable.loadUrl(container, urlObj);
      });
    },
    // Self mode (data-gk-live-self): the response is the WHOLE page (a controller
    // without a partial branch). We cut out the container of the same name and
    // swap only its content. That makes every list live without rebuilding
    // partials/controllers — the search input sits outside the container and so
    // keeps the focus.
    // Returns false when there is nothing sensible to insert.
    applyHtml: function (container, html) {
      // A response that STARTS as a whole document is a page, not a list.
      var wholePage = /^\s*(<!doctype|<html[\s>])/i.test(html);
      if (container.hasAttribute("data-gk-live-self")) {
        try {
          var doc = new DOMParser().parseFromString(html, "text/html");
          var fresh = container.id ? doc.getElementById(container.id) : null;
          // A whole page without our container is some OTHER page — an error
          // page, a login, a redirect target. It used to be written into the
          // list, sidebar and all. A bare fragment is still inserted: a
          // controller with a partial branch may answer with one.
          if (!fresh && wholePage) return false;
          container.innerHTML = fresh ? fresh.innerHTML : html;
          return true;
        } catch (e) {}
      }
      // Partial mode expects a fragment. A whole page means the controller has
      // no partial branch (use data-gk-live-self) or answered with another page.
      if (wholePage) {
        if (window.console) console.error("GridKit: " + (container.dataset.gkLiveTable || "the live table") +
          " answered with a whole page instead of a fragment — add a partial branch or data-gk-live-self.");
        return false;
      }
      container.innerHTML = html;
      return true;
    },
    loadUrl: function (container, urlObj) {
      var fetchParams = new URLSearchParams(urlObj.searchParams);
      fetchParams.set("partial", "1");
      var displayParams = new URLSearchParams(urlObj.searchParams);
      displayParams.delete("partial");
      var displayUrl = urlObj.pathname + (displayParams.toString() ? "?" + displayParams.toString() : "");
      GK.liveTable.request(container, urlObj.pathname + "?" + fetchParams.toString(), displayUrl);
    },
    // The one request path of the live table; loadUrl and reload differ only in
    // how they build the two addresses.
    //
    // Until 1.80.1 both wrote whatever came back into the container: the 401
    // JSON of an expired session showed up as raw text where the invoices had
    // been, a 500 or a firewall's 403 page landed inside the list, a network
    // failure did nothing at all, and an older answer could overwrite a newer
    // one. GK.table.reload has handled all four for a long time — same rules.
    request: function (container, fetchUrl, displayUrl) {
      container.classList.add("gk-live-loading");
      container.setAttribute("aria-busy", "true");
      // One counter per container, shared by every caller: a pager click must
      // not be overtaken by the filter request that was sent before it.
      var run = (container._gkRun = (container._gkRun || 0) + 1);
      var settled = false;
      var finish = function () {
        if (container._gkRun !== run || settled) return false;
        settled = true;
        container.classList.remove("gk-live-loading");
        container.removeAttribute("aria-busy");
        return true;
      };
      fetch(fetchUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (r) {
          // A backend that answers an expired session with a redirect hands
          // fetch a login page with status 200. It is not the list either.
          var strayed = false;
          if (r.redirected) {
            try {
              strayed = new URL(r.url).pathname !== new URL(fetchUrl, window.location.href).pathname;
            } catch (e) { strayed = false; }
          }
          if (!r.ok || strayed) {
            var error = new Error("HTTP " + r.status);
            error.gkTransport = true;
            error.status = strayed ? 401 : r.status;
            throw error;
          }
          return r.text();
        }, function (networkError) {
          networkError.gkTransport = true;
          throw networkError;
        })
        .then(function (html) {
          if (container._gkRun !== run) return; // overtaken by a newer request
          if (GK.liveTable.applyHtml(container, html) === false) {
            var error = new Error("the response is a page without #" + container.id);
            error.gkTransport = true;
            throw error;
          }
          if (!finish()) return;
          GK.liveTable.hoistPager(container);
          // Before the event, so a page's own listener sees the finished state.
          _gkApplyReplacements(container);
          // The one list, not a hand-picked subset of it: what came back carries
          // its own widgets — a searchable select, a tab strip, an AJAX form —
          // and what was REPLACED sits outside the container, so this runs over
          // the document. That also covers [data-gk-live-input], because
          // GK.initContent calls GK.liveTable.init, which binds them. Every init
          // is idempotent; measured at 0.15 ms on a 2500-node invoice list.
          if (typeof GK.initContent === "function") GK.initContent(document);
          window.history.replaceState(window.history.state, "", displayUrl);
          GK.liveTable.saveSession(container);
          container.dispatchEvent(new CustomEvent("gk-live-reloaded", { bubbles: true }));
          GK.liveTable.init(container);
        })
        .catch(function (err) {
          // Only a transport error means "not loaded". If inserting throws
          // (Safari throttles history.replaceState), the rows are already in.
          if (!err || !err.gkTransport) {
            finish();
            if (window.console) console.error("GridKit: failed to insert the live table", err);
            return;
          }
          if (!finish()) return;
          // The session is gone: a full load is what takes the user to the
          // login page — and back to this list afterwards.
          if (err.status === 401) { window.location.reload(); return; }
          // The old rows stay; they are better than an error page in their place.
          GK.toast.error(_lang["load_error"] || "The table could not be loaded.");
          container.dispatchEvent(new CustomEvent("gk-table-error", { bubbles: true, detail: { error: err } }));
        });
    },
    bindInput: function (input) {
      if (input._gkLiveBound) return;
      input._gkLiveBound = true;
      var containerId = input.dataset.gkLiveInput;
      var container = document.getElementById(containerId);
      if (!container) return;
      var textLike = ["text", "search", "url", "email", "tel", "password"];
      var evName = input.tagName === "INPUT" && textLike.indexOf(input.type) >= 0 ? "input" : "change";
      var timer = null;
      input.addEventListener(evName, function () {
        GK.liveTable.syncUrl(container);
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { GK.liveTable.reload(container); }, 250);
      });
    },
    syncUrl: function (container) {
      var baseUrl = container.dataset.gkLiveTable || window.location.pathname;
      var params = GK.liveTable.collectParams(container);
      var displayUrl = baseUrl + (params.toString() ? "?" + params.toString() : "");
      window.history.replaceState(window.history.state, "", displayUrl);
      GK.liveTable.saveSession(container);
    },
    patchNavSelects: function (root) {
      var r = root || document;
      r.querySelectorAll("select[data-gk-years]").forEach(function (sel) {
        if (sel._gkLivePatched) return;
        sel._gkLivePatched = true;
        var base = sel.dataset.base || window.location.pathname;
        var param = sel.dataset.param || "year";
        sel.onchange = function () {
          var u = new URL(base, window.location.origin);
          var cur = new URLSearchParams(window.location.search);
          cur.forEach(function (v, k) { if (v !== "") u.searchParams.set(k, v); });
          u.searchParams.set(param, sel.value);
          window.location.href = u.toString();
        };
      });
    },
    collectParams: function (container) {
      var params = new URLSearchParams(window.location.search);
      document.querySelectorAll('[data-gk-live-input="' + container.id + '"]').forEach(function (inp) {
        var name = inp.name || inp.dataset.gkName;
        if (!name) return;
        var val = inp.type === "checkbox" ? (inp.checked ? "1" : "") : inp.value.trim();
        if (val === "" || val === "0") params.delete(name);
        else params.set(name, val);
      });
      // Filter change → back to page 1
      params.delete("page");
      return params;
    },
    reload: function (container) {
      // Robust: accepts an element OR an id string. Several views called
      // reload('exp-live') -> "container.dataset is undefined". With no live
      // container found, fall back to a full reload instead of a JS error.
      if (typeof container === "string") container = document.getElementById(container);
      if (!container || !container.dataset || !container.dataset.gkLiveTable) {
        window.location.reload();
        return;
      }
      var baseUrl = container.dataset.gkLiveTable;
      var params = GK.liveTable.collectParams(container);
      params.set("partial", "1");
      var fetchUrl = baseUrl + "?" + params.toString();
      var displayUrl = baseUrl + "?" + new URLSearchParams(
        Array.from(params.entries()).filter(function (pair) { return pair[0] !== "partial"; })
      ).toString();
      GK.liveTable.request(container, fetchUrl, displayUrl);
    },
  };

  // Extend init
  // ── RowPager: client-side pagination (+ optional search) for rendered tables ──
  // Markup: <table data-gk-rows="25"> … </table>  (rows >25 → the pager appears).
  // Optionally filterable: data-gk-search="#such-input" → the search filters the rows
  // (full text, case-insensitive) AND paginates the matches. Replaces bespoke xxxFilter().
  GK.rowPager = {
    init(root) {
      (root || document).querySelectorAll("table[data-gk-rows]").forEach(function (tbl) {
        if (tbl._gkRowPager) return;
        var per = parseInt(tbl.getAttribute("data-gk-rows"), 10) || 25;
        var tbody = tbl.tBodies[0];
        if (!tbody) return;
        var allRows = Array.prototype.filter.call(tbody.rows, function (r) {
          return !r.hasAttribute("data-gk-rowpager-skip");
        });
        var searchSel = tbl.getAttribute("data-gk-search");
        var searchEl = searchSel ? document.querySelector(searchSel) : null;
        if (!searchEl && allRows.length <= per) return; // nothing to do
        tbl._gkRowPager = true;
        // Back to this list (1.128.0, see _gkBack): the kept page and search, else what the browser
        // restored into the box — the rows follow the box instead of showing everything under it.
        var backKey = tbl.id || searchSel ? "rows:" + (tbl.id || searchSel) : "";
        var kept = (backKey && _gkBack.read(backKey)) || {};
        var page = parseInt(kept.p, 10) > 1 ? parseInt(kept.p, 10) : 1;
        if (searchEl && typeof kept.q === "string") searchEl.value = kept.q;
        var query = searchEl ? (searchEl.value || "").toLowerCase().trim() : "";
        var host = tbl.closest(".gk-table-wrap") || tbl;
        var pager = document.createElement("div");
        pager.className = "gk-rowpager";
        host.parentNode.insertBefore(pager, host.nextSibling);
        function active() {
          if (!query) return allRows;
          return allRows.filter(function (r) { return r.textContent.toLowerCase().indexOf(query) !== -1; });
        }
        function render() {
          var rows = active();
          var pages = Math.max(1, Math.ceil(rows.length / per));
          if (page > pages) page = pages;
          var start = (page - 1) * per, end = start + per;
          allRows.forEach(function (r) { r.style.display = "none"; });
          rows.slice(start, end).forEach(function (r) { r.style.display = ""; });
          pager.innerHTML = rows.length > per ? GK.rowPager._html(page, pages, rows.length, per) : "";
          if (backKey) _gkBack.write(backKey, { q: searchEl ? searchEl.value || "" : "", p: page });
        }
        pager.addEventListener("click", function (e) {
          var b = e.target.closest("[data-gp]");
          if (!b) return;
          var v = b.getAttribute("data-gp");
          var pages = Math.max(1, Math.ceil(active().length / per));
          if (v === "prev") page = Math.max(1, page - 1);
          else if (v === "next") page = Math.min(pages, page + 1);
          else page = Math.min(pages, Math.max(1, parseInt(v, 10)));
          render();
          host.scrollIntoView({ block: "nearest" });
        });
        if (searchEl) {
          searchEl.addEventListener("input", function () {
            query = (this.value || "").toLowerCase().trim();
            page = 1;
            render();
          });
        }
        render();
      });
    },
    _html(page, pages, total, per) {
      var win = 2, set = [1, pages];
      for (var i = page - win; i <= page + win; i++) if (i >= 1 && i <= pages) set.push(i);
      set = set.filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
      var from = (page - 1) * per + 1, to = Math.min(total, page * per);
      var h = '<span class="gk-rowpager-info">' + from + "–" + to + " " + (_lang["rowpager_of"] || "of") + " " + total + "</span><span class=\"gk-rowpager-nav\">";
      h += '<button class="gk-pg gk-pg-icon' + (page <= 1 ? " gk-pg-off" : "") + '" data-gp="prev"><span class="material-icons">chevron_left</span></button>';
      var prev = 0;
      set.forEach(function (p) {
        if (prev && p - prev > 1) h += '<span class="gk-pg-gap">…</span>';
        h += '<button class="gk-pg' + (p === page ? " gk-pg-active" : "") + '" data-gp="' + p + '">' + p + "</button>";
        prev = p;
      });
      h += '<button class="gk-pg gk-pg-icon' + (page >= pages ? " gk-pg-off" : "") + '" data-gp="next"><span class="material-icons">chevron_right</span></button></span>';
      return h;
    },
  };

  // ── Tabs: <div data-gk-tabs> with <div data-gk-tabpanel="key" data-gk-tab-title="…"> ──
  // The nav buttons are generated from the panels; the first panel is active.
  //
  // What this generated was a row of <button class="gk-tab"> and nothing more:
  // no tablist, no tab roles, no aria-selected, and panels that were ordinary
  // divs shown and hidden with style.display. Which tab was current existed
  // only as gk-tab-active — a colour and an underline. So the whole widget
  // reached a screen reader as unrelated buttons sitting above unrelated text:
  // nothing tied a button to the panel it reveals, nothing said which one was
  // current, and the arrow keys — how a tablist is actually operated — did
  // nothing at all. Since this variant builds its own markup, it can simply
  // build the right markup.
  GK.tabs = {
    _n: 0,
    init(root) {
      var self = this;
      (root || document).querySelectorAll("[data-gk-tabs]").forEach(function (wrap) {
        if (wrap._gkTabs) return;
        wrap._gkTabs = true;
        var panels = Array.prototype.slice.call(wrap.querySelectorAll("[data-gk-tabpanel]"));
        if (!panels.length) return;
        var uid = "gk-tabs-" + ++self._n;
        var nav = document.createElement("div");
        nav.className = "gk-tabs-nav";
        nav.setAttribute("role", "tablist");
        var tabs = [];
        panels.forEach(function (p, i) {
          var key = p.getAttribute("data-gk-tabpanel");
          var on = i === 0;
          // A panel may already carry an id something else on the page links
          // to; only supply one where there is none.
          if (!p.id) p.id = uid + "-panel-" + i;
          var b = document.createElement("button");
          b.type = "button";
          b.className = "gk-tab" + (on ? " gk-tab-active" : "");
          b.id = uid + "-tab-" + i;
          b.setAttribute("role", "tab");
          b.setAttribute("data-gk-tab", key);
          b.setAttribute("aria-selected", on ? "true" : "false");
          b.setAttribute("aria-controls", p.id);
          // Roving tabindex: the set is ONE tab stop and the arrows move
          // within it. Six tabs must not cost six presses to walk past.
          b.tabIndex = on ? 0 : -1;
          b.innerHTML = p.getAttribute("data-gk-tab-title") || key;
          nav.appendChild(b);
          tabs.push(b);
          p.setAttribute("role", "tabpanel");
          p.setAttribute("aria-labelledby", b.id);
          p.style.display = on ? "" : "none";
        });
        wrap.insertBefore(nav, wrap.firstChild);

        // One place decides what "this tab is the current one" means, so the
        // class, the attribute, the tab stop and the panels cannot drift
        // apart — which is exactly how the state got lost in the first place.
        function select(b, focus) {
          var key = b.getAttribute("data-gk-tab");
          tabs.forEach(function (x) {
            var on = x === b;
            x.classList.toggle("gk-tab-active", on);
            x.setAttribute("aria-selected", on ? "true" : "false");
            x.tabIndex = on ? 0 : -1;
          });
          panels.forEach(function (p) {
            p.style.display = p.getAttribute("data-gk-tabpanel") === key ? "" : "none";
          });
          if (focus) b.focus();
        }

        nav.addEventListener("click", function (e) {
          var b = e.target.closest("[data-gk-tab]");
          if (b) select(b, false);
        });
        nav.addEventListener("keydown", function (e) {
          var i = tabs.indexOf(document.activeElement);
          if (i < 0) return;
          var to = -1;
          if (e.key === "ArrowRight") to = (i + 1) % tabs.length;
          else if (e.key === "ArrowLeft") to = (i - 1 + tabs.length) % tabs.length;
          else if (e.key === "Home") to = 0;
          else if (e.key === "End") to = tabs.length - 1;
          if (to < 0) return;
          e.preventDefault();
          select(tabs[to], true);
        });
      });
    },
  };

  // Re-apply RowPager after a live table reload (the container content was swapped).
  document.addEventListener("gk-live-reloaded", function (e) {
    if (GK.rowPager) GK.rowPager.init(e.target || document);
    if (GK.table) GK.table.init(e.target || document);
    GK.modal.upgradeStatic(e.target || document);
    if (GK.sheet) GK.sheet.upgrade(e.target || document);
  });

  /**
   * Bind every widget inside `root` — the one list, used on page load, for a
   * modal's body and after AJAX navigation.
   *
   * Navigation used to re-bind tables and tooltips only. On a page reached
   * through the sidebar a searchable select did not open, an AJAX form posted
   * natively and showed its JSON as a page, the client-side pager was missing —
   * until a reload. Every init here is idempotent (each marks what it bound),
   * so calling this twice on the same content is harmless.
   */
  GK.initContent = function (root) {
    root = root && root.querySelectorAll ? root : document;
    GK.initRangeSliders();
    GK.initUploadZones();
    GK.initRichtext();
    if (GK.form && GK.form.bind) GK.form.bind(root);
    // Tables and tooltips were bound beside this list, not in it: a table that
    // page code inserted and handed to initContent() got no sort, search or
    // paging — although the skill says this call is all it takes.
    if (GK.table && GK.table.init) GK.table.init(root);
    if (GK.tooltip && GK.tooltip.init) GK.tooltip.init();
    if (GK.selectSearch) GK.selectSearch.init(root);
    if (GK.multiSelect) GK.multiSelect.init(root);
    if (GK.ajaxSelect) GK.ajaxSelect.init(root);
    if (GK.liveTable) GK.liveTable.init(root);
    if (GK.tabs) GK.tabs.init(root);
    if (GK.tabsMarkup) GK.tabsMarkup.init(root);
    if (GK.tabsFit) GK.tabsFit.init(root);
    // Defined further down, so absent on the very first GK.init() of an
    // already-loaded document; the ready-aware call beside its definition
    // covers that pass, and this one covers everything added later.
    if (GK.accordion) GK.accordion.init(root);
    if (GK.lightbox && GK.lightbox.init) GK.lightbox.init(root);
    if (GK.picker) GK.picker.init(root);
    if (GK.rowPager) GK.rowPager.init(root);
    GK.modal.upgradeStatic(root);
    if (GK.sheet) GK.sheet.upgrade(root);
    _gkAnnounceInit(root);
  };

  var _origInit = GK.init;
  GK.init = function () {
    _origInit.call(GK);
    GK.initContent(document);
    // After everything that can change the layout, still before the first paint.
    if (GK.tabsScroll) GK.tabsScroll.restore();
  };

  /*
   * Dropdown toggle (header user menu and friends).
   *
   * Three things were wrong, and together they meant the user menu — which
   * contains Sign out — could not be operated without a mouse at all:
   *
   *   - the trigger is a div with role="button" and tabindex="0", and only
   *     `click` was handled. A div does not synthesise a click from Enter or
   *     Space the way a real button does, so the keyboard did nothing.
   *   - aria-expanded was written once as "false" and never updated, so it
   *     stated the opposite of the truth whenever the menu was open. That is
   *     worse than omitting it.
   *   - Escape did not close it, and closing left focus nowhere.
   */
  function _gkDropdownSet(el, open) {
    el.classList.toggle("open", open);
    el.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function _gkCloseDropdowns(except) {
    document.querySelectorAll("[data-gk-dropdown].open").forEach(function (el) {
      if (el !== except) _gkDropdownSet(el, false);
    });
  }

  document.addEventListener("click", function (e) {
    var dropdown = e.target.closest("[data-gk-dropdown]");
    _gkCloseDropdowns(dropdown);
    // A click on something inside the open menu is the user choosing an item —
    // toggling there would close and immediately reopen it.
    if (dropdown && !e.target.closest(".gk-dropdown-menu")) {
      _gkDropdownSet(dropdown, !dropdown.classList.contains("open"));
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      var open = document.querySelector("[data-gk-dropdown].open");
      if (open) {
        // Claimed: a modal underneath must not close along with the menu.
        e.preventDefault();
        _gkDropdownSet(open, false);
        if (typeof open.focus === "function") open.focus();
      }
      return;
    }
    if (e.key !== "Enter" && e.key !== " ") return;
    var trigger = e.target.closest("[data-gk-dropdown]");
    // Only the trigger itself: Enter on a link inside the menu must follow it.
    if (!trigger || e.target !== trigger) return;
    e.preventDefault();
    var willOpen = !trigger.classList.contains("open");
    _gkCloseDropdowns(trigger);
    _gkDropdownSet(trigger, willOpen);
  });

  // The "..." menu of a line with a brand (Header::ortszeile 'marke', 1.126.0) is a <details>: a click outside and Escape close it
  // (Escape gives the focus back to its button); a click on one of its links follows the link and the page change replaces the line.
  document.addEventListener("click", function (e) {
    document.querySelectorAll(".gk-ortszeile-mehr details[open]").forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute("open");
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    var d = document.querySelector(".gk-ortszeile-mehr details[open]");
    if (!d) return;
    e.preventDefault();
    d.removeAttribute("open");
    var s = d.querySelector("summary");
    if (s) s.focus();
  });

  // Layout System
  GK.layout = {
    set(mode) {
      document.body.dataset.gkLayout = mode;
      try {
        localStorage.setItem("gk-layout", mode);
      } catch (e) {}
    },
    restore() {
      try {
        var mode = localStorage.getItem("gk-layout");
        if (mode) document.body.dataset.gkLayout = mode;
      } catch (e) {}
    },
  };

  // Theme System
  //
  // localStorage belongs to the BROWSER, not to the logged-in user. Without a
  // namespace the next user on the same machine inherits the colour profile of
  // the previous one — reported on 2026-07-31: the colour was switched at one
  // client, then someone logged in at the tax adviser and it was set there too.
  //
  // That is why the host system can set a namespace:
  //   GK.theme.init({ scope: 'u' + userId })
  // Without a namespace everything behaves as it did before.
  GK.theme = {
    // The namespace can also be supplied by the host system — exactly like
    // window.GK_LANG for the translations. That is necessary because GridKit
    // restores the profile itself on load, before any of your own code can run.
    scope: String(window.GK_THEME_SCOPE || ""),

    init(options) {
      this.scope = String((options && options.scope) || "");
      this.restore();
      return this;
    },

    /** Key within the user's namespace. */
    _key(name) {
      return this.scope ? name + ":" + this.scope : name;
    },

    /**
     * Mark the swatch that is in effect — the class AND aria-pressed, in one
     * place. Theme.php renders aria-pressed on the active dot; nothing here
     * ever moved it again. Both the click and the restore-on-load moved only
     * the class, so from the first colour change onwards the picker told a
     * screen reader that a different colour was selected than the one that
     * was — and a stored preference made it wrong on every single page load,
     * before anyone had touched anything.
     *
     * The two call sites below ran the same loop in two copies. That is how
     * the attribute came to be missing from both.
     */
    _mark(theme) {
      document.querySelectorAll("[data-gk-set-theme]").forEach(function (b) {
        var on = b.dataset.gkSetTheme === theme;
        b.classList.toggle("gk-theme-active", on);
        b.setAttribute("aria-pressed", on ? "true" : "false");
      });
    },

    set(theme) {
      document.body.dataset.gkTheme = theme;
      try {
        localStorage.setItem(this._key("gk-theme"), theme);
      } catch (e) {}
      this._mark(theme);
    },
    toggleMode() {
      var mode = document.body.dataset.gkMode === "dark" ? "light" : "dark";
      document.body.dataset.gkMode = mode;
      try {
        localStorage.setItem(this._key("gk-mode"), mode);
      } catch (e) {}
      this._markMode(mode);
    },

    /** Dark is the pressed state of the one button that switches between them. */
    _markMode(mode) {
      document.querySelectorAll("[data-gk-toggle-mode]").forEach(function (b) {
        b.setAttribute("aria-pressed", mode === "dark" ? "true" : "false");
      });
    },
    restore() {
      try {
        var theme = localStorage.getItem(this._key("gk-theme"));
        var mode = localStorage.getItem(this._key("gk-mode"));

        // A stored preference wins; nothing stored means the page keeps what
        // the server rendered. Writing "" here discarded Theme::set() on every
        // first visit, in every private window, and for every user who had
        // never picked a theme — so a site set to dark mode in PHP opened in
        // light mode for everyone who had not been there before.
        if (theme) this.set(theme);
        if (mode) document.body.dataset.gkMode = mode;

        // Mark the swatch matching whatever is actually in effect, which may
        // be the server's choice rather than a stored one.
        this._mark(document.body.dataset.gkTheme || "");
        this._markMode(document.body.dataset.gkMode || "light");
      } catch (e) {}
    },
  };

  // Auto-bind theme buttons
  document.addEventListener("click", function (e) {
    var themeBtn = e.target.closest("[data-gk-set-theme]");
    if (themeBtn) GK.theme.set(themeBtn.dataset.gkSetTheme);
    var modeBtn = e.target.closest("[data-gk-toggle-mode]");
    if (modeBtn) GK.theme.toggleMode();
  });

  // === SEARCHABLE SELECT ===
  GK.selectSearch = {
    init(root) {
      (root || document)
        .querySelectorAll("[data-gk-select-search]")
        .forEach((wrap) => {
          if (wrap._gkBound) return;
          wrap._gkBound = true;
          var display = wrap.querySelector(".gk-select-display");
          var dropdown = wrap.querySelector(".gk-select-dropdown");
          var searchInput = dropdown.querySelector('input[type="text"]');
          // The value carrier stopped being type="hidden" in 1.42 so that a
          // required select can actually be validated by the browser. Both
          // spellings are accepted, so a page still holding an older cached
          // copy of the markup keeps working.
          var hidden =
            wrap.querySelector("input.gk-select-value-input") ||
            wrap.querySelector('input[type="hidden"]');
          var valueSpan = wrap.querySelector(".gk-select-value");
          var options = wrap.querySelectorAll(".gk-select-option");

          // Open and close in one place, so the keyboard path and the mouse
          // path cannot drift apart, and aria-expanded always tells the truth.
          var setOpen = function (open) {
            display.classList.toggle("open", open);
            display.setAttribute("aria-expanded", open ? "true" : "false");
            if (!open) return;
            if (searchInput) searchInput.value = "";
            options.forEach((o) => o.classList.remove("hidden"));
            var empty = dropdown.querySelector(".gk-select-empty");
            if (empty) empty.remove();
            if (searchInput) setTimeout(() => searchInput.focus(), 50);
          };

          display.addEventListener("click", function () {
            if (wrap.hasAttribute("data-disabled")) return;
            setOpen(!display.classList.contains("open"));
          });

          // The markup puts this div in the tab order with tabindex="0", which
          // is a promise that it can be operated. Until 1.42 only a click was
          // bound: you could Tab to the control and then no key did anything.
          display.addEventListener("keydown", function (ev) {
            if (wrap.hasAttribute("data-disabled")) return;
            var k = ev.key;
            if (k === "Enter" || k === " " || k === "Spacebar" || k === "ArrowDown") {
              ev.preventDefault();
              setOpen(true);
            } else if (k === "Escape" && display.classList.contains("open")) {
              ev.preventDefault();
              setOpen(false);
              display.focus();
            }
          });

          // Escape from inside the search box returns to the control rather
          // than leaving the list open behind you.
          if (searchInput) {
            searchInput.addEventListener("keydown", function (ev) {
              if (ev.key === "Escape") {
                ev.preventDefault();
                setOpen(false);
                display.focus();
              }
            });
          }

          if (searchInput) {
            searchInput.addEventListener("input", function () {
              var q = this.value.toLowerCase();
              var found = 0;
              options.forEach((o) => {
                var match = o.textContent.toLowerCase().includes(q);
                o.classList.toggle("hidden", !match);
                if (match) found++;
              });
              var empty = dropdown.querySelector(".gk-select-empty");
              if (found === 0 && !empty) {
                var e = document.createElement("div");
                e.className = "gk-select-empty";
                e.textContent = _t("no_matches");
                dropdown.querySelector(".gk-select-options").appendChild(e);
              } else if (found > 0 && empty) empty.remove();
            });
          }

          options.forEach((opt) => {
            opt.addEventListener("click", function () {
              hidden.value = this.dataset.value;
              valueSpan.textContent = this.textContent;
              // aria-selected as well as the class. The server renders both;
              // choosing here moved only the class, so from the first pick
              // onwards the listbox went on reporting the option the page had
              // loaded with instead of the one the person had just chosen.
              options.forEach((o) => {
                o.classList.remove("selected");
                o.setAttribute("aria-selected", "false");
              });
              this.classList.add("selected");
              this.setAttribute("aria-selected", "true");
              setOpen(false);
              hidden.dispatchEvent(new Event("change", { bubbles: true }));
            });
          });

          // Through setOpen as well, or a click elsewhere would close the list
          // while aria-expanded went on saying "true".
          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target) && display.classList.contains("open")) setOpen(false);
          });
        });
    },
  };

  // === MULTI-SELECT ===
  GK.multiSelect = {
    init(root) {
      (root || document)
        .querySelectorAll("[data-gk-multiselect]")
        .forEach((wrap) => {
          if (wrap._gkBound) return;
          wrap._gkBound = true;
          var display = wrap.querySelector(".gk-multiselect-display");
          var dropdown = wrap.querySelector(".gk-select-dropdown");
          // Same as the searchable select: since 1.43.0 the value carrier is a
          // real control, so a required field can actually be validated. Only
          // one of the three lookups was updated — the other two then read
          // null.value and threw, and because this runs inside GK.init the
          // throw took every binder after it down with it.
          var hidden =
            wrap.querySelector("input.gk-select-value-input") ||
            wrap.querySelector('input[type="hidden"]');
          var chipsContainer = wrap.querySelector(".gk-multiselect-chips");
          var searchInput = wrap.querySelector(".gk-multiselect-input");
          var optionsContainer = dropdown.querySelector(".gk-select-options");
          var allOptions = wrap.querySelectorAll(".gk-select-option");

          function getSelected() {
            return hidden.value ? hidden.value.split(",").filter(Boolean) : [];
          }

          function updateHidden(vals) {
            hidden.value = vals.join(",");
            hidden.dispatchEvent(new Event("change", { bubbles: true }));
          }

          function rebuildChips() {
            // Remove old chips
            wrap
              .querySelectorAll(".gk-chip-selected")
              .forEach((c) => c.remove());
            var vals = getSelected();
            vals.forEach((v) => {
              var opt = optionsContainer.querySelector(
                '[data-value="' + v + '"]',
              );
              if (!opt) return;
              var label = opt.textContent.replace("check", "").trim();
              var chip = document.createElement("span");
              chip.className = "gk-chip-selected";
              chip.dataset.value = v;
              chip.innerHTML =
                label +
                ' <button type="button" class="gk-chip-remove">&times;</button>';
              chip
                .querySelector(".gk-chip-remove")
                .addEventListener("click", function (e) {
                  e.stopPropagation();
                  toggleValue(v);
                });
              if (searchInput) chipsContainer.insertBefore(chip, searchInput);
              else chipsContainer.appendChild(chip);
            });
          }

          function toggleValue(val) {
            var vals = getSelected();
            var idx = vals.indexOf(val);
            if (idx >= 0) vals.splice(idx, 1);
            else vals.push(val);
            updateHidden(vals);
            // Update option states
            allOptions.forEach((o) => {
              var isSelected = vals.includes(o.dataset.value);
              o.classList.toggle("selected", isSelected);
              o.setAttribute("aria-selected", isSelected ? "true" : "false");
              // Update check icon
              var check = o.querySelector(".material-icons");
              if (isSelected && !check) {
                var s = document.createElement("span");
                s.className = "material-icons";
                s.style.fontSize = "16px";
                s.textContent = "check";
                o.insertBefore(s, o.firstChild);
                o.insertBefore(document.createTextNode(" "), s.nextSibling);
              } else if (!isSelected && check) {
                if (check.nextSibling && check.nextSibling.nodeType === 3)
                  check.nextSibling.remove();
                check.remove();
              }
            });
            rebuildChips();
            updatePlaceholder();
          }

          function updatePlaceholder() {
            if (!searchInput) return;
            searchInput.placeholder = getSelected().length
              ? ""
              : searchInput.dataset.placeholder ||
                searchInput.getAttribute("placeholder") ||
                "";
          }

          // Store original placeholder
          if (searchInput)
            searchInput.dataset.placeholder =
              searchInput.getAttribute("placeholder") || "";
          updatePlaceholder();

          display.addEventListener("click", function (e) {
            if (e.target.closest(".gk-chip-remove")) return;
            display.classList.toggle("open");
            if (display.classList.contains("open") && searchInput) {
              setTimeout(() => searchInput.focus(), 50);
            }
          });

          allOptions.forEach((opt) => {
            opt.addEventListener("click", function () {
              toggleValue(this.dataset.value);
            });
          });

          if (searchInput) {
            searchInput.addEventListener("input", function () {
              var q = this.value.toLowerCase();
              var found = 0;
              allOptions.forEach((o) => {
                var match = o.textContent.toLowerCase().includes(q);
                o.classList.toggle("hidden", !match);
                if (match) found++;
              });
              var empty = dropdown.querySelector(".gk-select-empty");
              if (found === 0 && !empty) {
                var e = document.createElement("div");
                e.className = "gk-select-empty";
                e.textContent = _t("no_matches");
                optionsContainer.appendChild(e);
              } else if (found > 0 && empty) empty.remove();
            });
            searchInput.addEventListener("focus", function () {
              display.classList.add("open");
            });
          }

          // The multi select had no key handling at all: Escape with its list
          // open went straight to the modal underneath and closed that, with
          // the list still hanging open. Only an OPEN list claims the key, so a
          // closed one leaves Escape to the modal.
          wrap.addEventListener("keydown", function (e) {
            if (e.key !== "Escape" || !display.classList.contains("open")) return;
            e.preventDefault();
            display.classList.remove("open");
            if (typeof display.focus === "function") display.focus();
          });

          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target)) display.classList.remove("open");
          });
        });
    },
  };

  // === AJAX SELECT ===
  GK.ajaxSelect = {
    init(root) {
      (root || document)
        .querySelectorAll("[data-gk-ajax-select]")
        .forEach((wrap) => {
          if (wrap._gkBound) return;
          wrap._gkBound = true;
          var input = wrap.querySelector(".gk-ajax-search-input");
          // Same as the searchable select: since 1.43.0 the value carrier is a
          // real control, so a required field can actually be validated. Only
          // one of the three lookups was updated — the other two then read
          // null.value and threw, and because this runs inside GK.init the
          // throw took every binder after it down with it.
          var hidden =
            wrap.querySelector("input.gk-select-value-input") ||
            wrap.querySelector('input[type="hidden"]');
          var dropdown = wrap.querySelector(".gk-select-dropdown");
          var optionsContainer = dropdown.querySelector(".gk-select-options");
          var loading = dropdown.querySelector(".gk-select-loading");
          var clearBtn = wrap.querySelector(".gk-ajax-clear");
          var url = wrap.dataset.url;
          var labelField = wrap.dataset.labelField || "name";
          var valueField = wrap.dataset.valueField || "id";
          var subtextField = wrap.dataset.subtextField || "";
          var minChars = parseInt(wrap.dataset.minChars) || 2;
          var searchParam = wrap.dataset.searchParam || "q";
          var timer;
          var display = wrap.querySelector(".gk-select-display");
          var activeIdx = -1;

          /*
           * aria-expanded was written once, by PHP, and six places in here changed
           * the dropdown's visibility without touching it — so the combobox
           * reported "closed" with a list of results on screen. One place decides
           * now, and it also clears the active option, which three of those six
           * sites had to remember separately.
           */
          // A flag, not a guess from the inline style: the list is hidden by
          // CSS until first use, so style.display starts as "" — not "none".
          var isOpen = false;
          function setOpen(open) {
            isOpen = !!open;
            dropdown.style.display = open ? "block" : "none";
            if (display) display.setAttribute("aria-expanded", open ? "true" : "false");
            if (!open) {
              activeIdx = -1;
              input.removeAttribute("aria-activedescendant");
            }
          }

          input.addEventListener("input", function () {
            var q = this.value.trim();
            clearBtn.style.display = q ? "" : "none";
            if (q.length < minChars) {
              setOpen(false);
              return;
            }
            clearTimeout(timer);
            timer = setTimeout(function () {
              loading.style.display = "";
              optionsContainer.innerHTML = "";
              setOpen(true);
              fetch(url + "?" + searchParam + "=" + encodeURIComponent(q))
                .then((r) => r.json())
                .then((data) => {
                  loading.style.display = "none";
                  if (!data.length) {
                    optionsContainer.innerHTML =
                      '<div class="gk-select-empty">' +
                      _t("no_matches") +
                      "</div>";
                    return;
                  }
                  data.forEach((item, i) => {
                    var opt = document.createElement("div");
                    opt.className = "gk-select-option";
                    // The container is a listbox; its children were plain
                    // divs, so it was announced as a listbox with nothing in
                    // it. The id is what aria-activedescendant points at.
                    opt.setAttribute("role", "option");
                    opt.setAttribute("aria-selected", "false");
                    opt.id = (optionsContainer.id || "gk-ajax") + "-opt-" + i;
                    opt.dataset.value = item[valueField];
                    opt.dataset.json = JSON.stringify(item);
                    var label = item[labelField] || "";
                    var esc = function (s) {
                      var d = document.createElement("div");
                      d.textContent = s;
                      return d.innerHTML;
                    };
                    opt.innerHTML = "<div>" + esc(label) + "</div>";
                    if (subtextField && item[subtextField]) {
                      opt.innerHTML +=
                        '<div class="gk-select-option-sub">' +
                        esc(item[subtextField]) +
                        "</div>";
                    }
                    optionsContainer.appendChild(opt);
                  });
                })
                .catch(() => {
                  loading.style.display = "none";
                });
            }, 300);
          });

          function getOptions() {
            return Array.from(optionsContainer.querySelectorAll(".gk-select-option"));
          }

          /*
           * Arrow keys moved a grey bar and nothing else: the active option was a
           * background colour, which a screen reader does not see. The global search
           * has always announced its active result with aria-activedescendant; this
           * one never did.
           */
          function highlightOption(opts) {
            opts.forEach(function (el, i) {
              var on = i === activeIdx;
              el.style.background = on ? "var(--gk-surface-container, #f1f5f9)" : "";
              el.style.fontWeight = on ? "600" : "";
              el.setAttribute("aria-selected", on ? "true" : "false");
            });
            if (opts[activeIdx]) {
              opts[activeIdx].scrollIntoView({ block: "nearest" });
              input.setAttribute("aria-activedescendant", opts[activeIdx].id);
            } else {
              input.removeAttribute("aria-activedescendant");
            }
          }

          function selectOption(opt) {
            if (!opt) return;
            var item = JSON.parse(opt.dataset.json);
            hidden.value = opt.dataset.value;
            input.value = item[labelField] || opt.querySelector("div").textContent;
            setOpen(false);
            clearBtn.style.display = "";
            hidden.dispatchEvent(new Event("change", { bubbles: true }));
            wrap.dispatchEvent(new CustomEvent("gk-select", { detail: item }));
          }

          optionsContainer.addEventListener("click", function (e) {
            var opt = e.target.closest(".gk-select-option");
            if (opt) selectOption(opt);
          });

          input.addEventListener("keydown", function (e) {
            if (!isOpen) return;
            // Before the options are asked for: a list showing "loading" or "no
            // results" has none, and Escape never reached its branch below.
            // preventDefault tells a modal underneath that the key is taken.
            if (e.key === "Escape") {
              e.preventDefault();
              setOpen(false);
              return;
            }
            var opts = getOptions();
            if (!opts.length) return;
            if (e.key === "ArrowDown") {
              e.preventDefault();
              activeIdx = Math.min(activeIdx + 1, opts.length - 1);
              highlightOption(opts);
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              activeIdx = Math.max(activeIdx - 1, 0);
              highlightOption(opts);
            } else if (e.key === "Enter" && activeIdx >= 0) {
              e.preventDefault();
              selectOption(opts[activeIdx]);
            }
          });

          if (clearBtn)
            clearBtn.addEventListener("click", function () {
              hidden.value = "";
              input.value = "";
              clearBtn.style.display = "none";
              setOpen(false);
              hidden.dispatchEvent(new Event("change", { bubbles: true }));
            });

          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target)) setOpen(false);
          });
        });
    },
  };

  /*
   * === TABS (authored markup) ===
   *
   * The second of two tab systems in this file, and the one the demo teaches:
   * `.gk-tabs > .gk-tab-nav > .gk-tab-btn[data-tab]` with matching
   * `.gk-tab-panel[data-tab]`. Here the page author writes the markup, so the
   * roles cannot be built in the way GK.tabs builds its own — they have to be
   * put on afterwards. Without them this was, to a screen reader, a row of
   * buttons above some text with nothing connecting the two, no statement of
   * which one is current, and no arrow-key movement.
   *
   * Decorating is deliberately conservative: an id is only supplied where
   * there is none, and the click handler below touches aria-selected only on
   * buttons this actually decorated (role="tab"). Markup inserted later and
   * never passed through init() therefore keeps working exactly as before
   * rather than acquiring half a set of attributes.
   */
  GK.tabsMarkup = {
    _n: 0,
    /* The tabs and panels of THIS .gk-tabs, never those of a .gk-tabs inside one of its panels:
       a click on an outer tab used to clear every inner one too (1.101.0). */
    own(wrap, sel) {
      return Array.prototype.filter.call(wrap.querySelectorAll(sel), function (el) {
        return el.closest(".gk-tabs") === wrap;
      });
    },
    panel(wrap, key) {
      return this.own(wrap, ".gk-tab-panel").filter(function (p) {
        return p.dataset.tab === key;
      })[0] || null;
    },
    init(root) {
      var self = this;
      (root || document).querySelectorAll(".gk-tabs").forEach(function (wrap) {
        var nav = GK.tabsMarkup.own(wrap, ".gk-tab-nav")[0];
        if (!nav || nav._gkTabsA11y) return;
        nav._gkTabsA11y = true;
        var uid = "gk-tabs-m" + ++self._n;
        nav.setAttribute("role", "tablist");
        var btns = GK.tabsMarkup.own(wrap, ".gk-tab-btn");
        btns.forEach(function (b, i) {
          var panel = GK.tabsMarkup.panel(wrap, b.dataset.tab);
          var on = b.classList.contains("gk-active");
          if (!b.id) b.id = uid + "-tab-" + i;
          b.setAttribute("role", "tab");
          b.setAttribute("aria-selected", on ? "true" : "false");
          b.tabIndex = on ? 0 : -1;
          if (panel) {
            if (!panel.id) panel.id = uid + "-panel-" + i;
            b.setAttribute("aria-controls", panel.id);
            panel.setAttribute("role", "tabpanel");
            panel.setAttribute("aria-labelledby", b.id);
          }
        });
        // A side row (.gk-tab-nav-links / -rechts) is a column from 601px up and a row below it: the
        // orientation is read from the layout, so the arrow keys follow what is on the screen (1.103.0).
        var vertical = function () {
          return getComputedStyle(nav).flexDirection === "column";
        };
        var orient = function () {
          nav.setAttribute("aria-orientation", vertical() ? "vertical" : "horizontal");
        };
        orient();
        if (window.ResizeObserver) new ResizeObserver(orient).observe(nav);
        // A disabled tab is stepped over; Home/End land on the first/last one that is not.
        var step = function (from, dir) {
          for (var k = 1; k <= btns.length; k++) {
            var j = (from + dir * k + btns.length * btns.length) % btns.length;
            if (!btns[j].disabled && btns[j].getAttribute("aria-disabled") !== "true") return j;
          }
          return -1;
        };
        nav.addEventListener("keydown", function (e) {
          var i = btns.indexOf(document.activeElement);
          if (i < 0) return;
          var to = -1;
          var col = vertical();
          if (e.key === (col ? "ArrowDown" : "ArrowRight")) to = step(i, 1);
          else if (e.key === (col ? "ArrowUp" : "ArrowLeft")) to = step(i, -1);
          else if (e.key === "Home") to = step(-1, 1);
          else if (e.key === "End") to = step(btns.length, -1);
          if (to < 0) return;
          e.preventDefault();
          btns[to].focus();
          btns[to].click();
        });
      });
    },
  };

  document.addEventListener("click", function (e) {
    var btn = e.target.closest(".gk-tab-btn");
    if (!btn) return;
    if (btn.getAttribute("aria-disabled") === "true") {
      e.preventDefault();
      return;
    }
    var tabs = btn.closest(".gk-tabs");
    if (!tabs) return;
    var target = btn.dataset.tab;
    GK.tabsMarkup.own(tabs, ".gk-tab-btn").forEach(function (b) {
      b.classList.remove("gk-active");
      // Only where init() put a role on: see the note above.
      if (b.getAttribute("role") === "tab") {
        b.setAttribute("aria-selected", "false");
        b.tabIndex = -1;
      }
    });
    GK.tabsMarkup.own(tabs, ".gk-tab-panel").forEach(function (p) {
      p.classList.remove("gk-active");
    });
    btn.classList.add("gk-active");
    if (btn.getAttribute("role") === "tab") {
      btn.setAttribute("aria-selected", "true");
      btn.tabIndex = 0;
    }
    var panel = GK.tabsMarkup.panel(tabs, target);
    if (panel) panel.classList.add("gk-active");
  });

  /*
   * === TABS: a Karteireiter row that does not fit becomes a grid (1.102.0) ===
   *
   * A row (.gk-tab-nav-karte) is one line while it fits the box it stands in, and the grid of framed
   * cells (the phone shape) when it does not: `.gk-tab-nav-raster` is put on and taken off here. The
   * window width cannot decide this — six tabs beside a to-do column are too wide at 1280px and just
   * fit at 1440px, and no fixed width suits every set of words, badges and counts. So the row is
   * measured as a row (the class off, its own overflow decides, the class back on, all before the
   * next paint) at start, when the row or one of its tabs changes size (a font arrives, a badge
   * appears) and when a hidden row is shown. Without a script, or a row that fits, nothing changes.
   * The re-measure waits one frame: a change of the row's own size from inside the observer would end
   * in "ResizeObserver loop completed with undelivered notifications".
   */
  GK.tabsFit = {
    cls: "gk-tab-nav-raster",
    fit(nav) {
      nav.classList.remove(this.cls);
      if (nav.scrollWidth > nav.clientWidth) nav.classList.add(this.cls);
    },
    init(root) {
      var self = this;
      // a side row (column) never becomes the grid: from 601px it is a column, below it the phone grid is there anyway
      (root || document).querySelectorAll(".gk-tab-nav-karte:not(.gk-tab-nav-links):not(.gk-tab-nav-rechts)").forEach(function (nav) {
        if (nav._gkTabsFit) return;
        nav._gkTabsFit = true;
        self.fit(nav);
        if (!window.ResizeObserver) return;
        var queued = false;
        var watch = new ResizeObserver(function () {
          if (queued) return;
          queued = true;
          requestAnimationFrame(function () {
            queued = false;
            self.fit(nav);
          });
        });
        watch.observe(nav);
        Array.prototype.forEach.call(nav.children, function (tab) {
          watch.observe(tab);
        });
      });
    },
  };

  /*
   * === TABS: a link tab keeps its place on the page (1.102.0) ===
   *
   * The tabs of a page are often real links (<a class="gk-tab-btn" href="…"> in a .gk-tab-nav, the
   * current one with aria-current="page"): every click loads a page that starts at the top, so whoever
   * had scrolled down to the tabs lands at the top again while only the content below them changed.
   * Here the click writes down where the row stood in the window (sessionStorage, a few lines), and the
   * page it lands on scrolls so that the same row stands in the same place again — as if only the
   * content under the tabs changed. No fetch, no swapping of page parts: the page brings its own scripts.
   * Left alone: a link with a # target, another origin, target/download, a click with Shift/Ctrl/Meta/
   * Alt or the middle button, an address with a # anchor, a memory older than 15 s, back/forward and
   * reload (the browser keeps those itself), a row that is not on the new page. A row, or any
   * ancestor, with data-gk-tab-scroll="off" opts out. Every storage access is guarded: a browser that
   * blocks sessionStorage just behaves as before. The scroll jumps (no smooth scrolling).
   */
  GK.tabsScroll = {
    key: "gk-tab-scroll",
    maxAge: 15000,
    // The rows of link tabs, in page order: a row is named by its aria-label, else by its place here.
    rows() {
      return Array.prototype.filter.call(document.querySelectorAll(".gk-tab-nav"), function (nav) {
        return nav.querySelector("a.gk-tab-btn");
      });
    },
    off(el) {
      return !!el.closest('[data-gk-tab-scroll="off"]');
    },
    // A click on a link tab: write down the target and where its row stands in the window.
    remember(link) {
      var nav = link.closest(".gk-tab-nav");
      try {
        sessionStorage.setItem(
          this.key,
          JSON.stringify({
            url: link.pathname + link.search,
            label: nav.getAttribute("aria-label") || "",
            index: this.rows().indexOf(nav),
            top: nav.getBoundingClientRect().top,
            t: Date.now(),
          }),
        );
      } catch (e) {}
    },
    // Read the memory and delete it: it counts once, whatever came of it.
    take() {
      try {
        var raw = sessionStorage.getItem(this.key);
        sessionStorage.removeItem(this.key);
        return raw ? JSON.parse(raw) : null;
      } catch (e) {
        return null;
      }
    },
    // At the start of the page the row it lands on goes back to where the finger was.
    restore() {
      var m = this.take();
      if (!m || typeof m.top !== "number" || location.hash) return;
      var nav = window.performance && performance.getEntriesByType && performance.getEntriesByType("navigation")[0];
      var age = Date.now() - m.t;
      if ((nav && nav.type !== "navigate") || !(age >= 0 && age <= this.maxAge)) return;
      if (m.url !== location.pathname + location.search) return;
      var rows = this.rows();
      var row = m.label
        ? rows.filter(function (r) {
            return r.getAttribute("aria-label") === m.label;
          })[0]
        : rows[m.index];
      if (!row || this.off(row)) return;
      var top = window.scrollY + row.getBoundingClientRect().top - m.top;
      try {
        window.scrollTo({ top: top, behavior: "instant" });
      } catch (e) {
        window.scrollTo(0, top);
      }
    },
  };
  document.addEventListener("click", function (e) {
    var link = e.target.closest && e.target.closest("a.gk-tab-btn");
    if (!link || e.defaultPrevented || e.button !== 0 || e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) return;
    if (!link.closest(".gk-tab-nav") || link.hasAttribute("download") || (link.target && link.target !== "_self")) return;
    var href = link.getAttribute("href");
    if (!href || href.indexOf("#") !== -1 || link.origin !== location.origin || GK.tabsScroll.off(link)) return;
    GK.tabsScroll.remember(link);
  });

  // === AJAX PAGINATION ===
  // Wraps table + pagination in [data-gk-ajax-table="id"].
  // Intercepts gk-page-btn link clicks, fetches new page, swaps innerHTML.
  document.addEventListener("click", function (e) {
    var link = e.target.closest("a.gk-page-btn");
    if (!link || !link.href) return;
    var wrap = link.closest("[data-gk-ajax-table]");
    if (!wrap) return;
    e.preventDefault();
    var url = link.href;
    var id = wrap.getAttribute("data-gk-ajax-table");
    wrap.style.opacity = "0.5";
    wrap.style.pointerEvents = "none";
    wrap.style.transition = "opacity .15s";
    fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
      .then(function (r) {
        return r.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, "text/html");
        var newWrap = doc.querySelector('[data-gk-ajax-table="' + id + '"]');
        if (newWrap) {
          wrap.innerHTML = newWrap.innerHTML;
          GK.table.init(wrap);
          GK.form.bind(wrap);
        }
        wrap.style.opacity = "";
        wrap.style.pointerEvents = "";
        history.pushState(null, "", url);
      })
      .catch(function () {
        wrap.style.opacity = "";
        wrap.style.pointerEvents = "";
        window.location.href = url;
      });
  });

  // ════════════════════════════════════════════════════════════════════
  // GK.belegModal — global PDF/receipt preview modal (since v1.15.0)
  // ════════════════════════════════════════════════════════════════════
  GK.belegModal = {
    // Set this to the route that detaches a document. Empty by default: a
    // library must not know your application's URLs.
    unlinkUrl: "",
    _el: function () { return document.getElementById("gk-beleg-modal"); },

    /**
     * Opens the modal with the given URL.
     *
     * @param {string} url
     * @param {object} [opts]
     * @param {string} [opts.title]            Header title (default: "Beleg")
     * @param {boolean}[opts.autoPrint]        Prints the iframe as soon as it loaded
     * @param {number} [opts.unlinkExpenseId]  Shows the "unlink receipt" button
     * @param {function}[opts.onUnlink]        Callback after a successful unlink
     */
    open: function (url, opts) {
      if (!url) return;
      opts = opts || {};
      var overlay = this._el();
      if (!overlay) {
        console.warn("GK.belegModal: container not found. Did you call BelegModal::container()?");
        // Fallback: open it directly in the browser
        window.open(url, "_blank");
        return;
      }
      var q = function (sel) { return overlay.querySelector(sel); };
      var titleEl = q("[data-gk-beleg-title]");
      var frame   = q("[data-gk-beleg-frame]");
      var openBtn = q("[data-gk-beleg-open]");
      var dlBtn   = q("[data-gk-beleg-download]");
      var mobBtn  = q("[data-gk-beleg-mobile-open]");
      var unlink  = q("[data-gk-beleg-unlink]");

      if (titleEl) titleEl.textContent = opts.title || _lang["doc_title"] || "Document";
      if (openBtn) openBtn.href = url;
      if (dlBtn)   dlBtn.href   = url;
      if (mobBtn)  mobBtn.href  = url;

      // Unlink button: only visible when unlinkExpenseId is set
      if (unlink) {
        if (opts.unlinkExpenseId) {
          unlink.classList.remove("gk-hidden");
          unlink.onclick = function () {
            if (!confirm(_lang["doc_unlink_confirm"] || "Really detach this document?")) return;

            // Where to POST. Until 1.41 this was hardcoded to
            // "/faktura/api/beleg/unlink" — a route from the author's own
            // invoicing application, shipped inside a general-purpose
            // library. On anyone else's site that is a 404, and the .json()
            // that followed rejected without ever showing an error.
            //
            // Set it once, at startup:
            //   GK.belegModal.unlinkUrl = "/api/documents/unlink";
            // or per call: GK.belegModal.open(url, { unlinkUrl: … }).
            var endpoint = opts.unlinkUrl || GK.belegModal.unlinkUrl;

            // With no endpoint the component does what the rest of GridKit
            // does with a destructive action: it reports the intent and lets
            // the application decide what detaching means.
            if (!endpoint) {
              var handled = !overlay.dispatchEvent(
                new CustomEvent("gk:belegunlink", {
                  bubbles: true,
                  cancelable: true,
                  detail: { id: opts.unlinkExpenseId, url: url },
                }),
              );
              if (!handled) {
                console.warn(
                  "GK.belegModal: nothing handled gk:belegunlink and no " +
                    "unlinkUrl is set — the detach button did nothing. Set " +
                    "GK.belegModal.unlinkUrl, or preventDefault() the event.",
                );
              }
              return;
            }

            var fail = function (msg) {
              if (window.GK && GK.toast) GK.toast.error(msg);
              else alert(msg);
            };
            fetch(endpoint, {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "expense_id=" + encodeURIComponent(opts.unlinkExpenseId)
            }).then(function (r) { return r.json(); }).then(function (d) {
              if (d.ok) {
                GK.belegModal.close();
                (opts.onUnlink || function () { location.reload(); })();
              } else {
                fail(d.error || _t("error_saving"));
              }
            }).catch(function () {
              fail(_t("error_saving"));
            });
          };
        } else {
          unlink.classList.add("gk-hidden");
          unlink.onclick = null;
        }
      }

      // Load the iframe on desktop only — mobile shows a call to action
      var isMobile = window.matchMedia("(max-width: 768px)").matches;
      if (frame) {
        frame.src = isMobile ? "about:blank" : url;
        if (opts.autoPrint && !isMobile) {
          frame.onload = function () {
            try { frame.contentWindow.print(); } catch (e) { console.warn(e); }
          };
        } else if (frame.onload) {
          frame.onload = null;
        }
      }

      overlay.classList.add("is-open");
      document.body.style.overflow = "hidden";
    },

    close: function () {
      var overlay = this._el();
      if (!overlay) return;
      var frame = overlay.querySelector("[data-gk-beleg-frame]");
      overlay.classList.remove("is-open");
      if (frame) frame.src = "about:blank";
      document.body.style.overflow = "";
    },

    _init: function () {
      var overlay = this._el();
      if (!overlay) return;
      var self = this;
      // Idempotent: a repeated _init (e.g. after AJAX nav) must not stack listeners.
      if (!overlay.dataset.gkBelegBound) {
        overlay.dataset.gkBelegBound = "1";
        // Click outside to close
        overlay.addEventListener("click", function (e) {
          if (e.target === overlay) self.close();
        });
      }
      // Close button(s): re-rendered by the swap → onclick (overwrites itself, no
      // stacking) instead of addEventListener.
      overlay.querySelectorAll("[data-gk-beleg-close]").forEach(function (btn) {
        btn.onclick = function () { self.close(); };
      });
      // Bind ESC globally only once
      if (!GK.belegModal._escBound) {
        GK.belegModal._escBound = true;
        document.addEventListener("keydown", function (e) {
          var ov = GK.belegModal._el();
          if (e.key === "Escape" && ov && ov.classList.contains("is-open")) GK.belegModal.close();
        });
      }
    }
  };

  // Backwards-compat aliases (Panel still uses openBelegModal/closeBelegModal)
  window.openBelegModal  = function (url, title, opts) {
    opts = opts || {};
    if (title) opts.title = title;
    GK.belegModal.open(url, opts);
  };
  window.closeBelegModal = function () { GK.belegModal.close(); };

  window.GridKit = GK;
  // Make translating possible OUTSIDE this capsule as well. Components that sit
  // below })(); (tooltip, search, …) cannot reach the private _t otherwise —
  // GK.search.init() died on exactly that with "ReferenceError: _t is not
  // defined" and registered not a single listener (found 2026-07-30).
  GK.t = _t;

  window.GK = GK;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      GK.init();
      GK.theme.restore();
      GK.layout.restore();
      GK.belegModal._init();
    });
  } else {
    GK.init();
    GK.theme.restore();
    GK.layout.restore();
    GK.belegModal._init();
  }

  /*
   * === ACCORDION ===
   *
   * Three faults, and the third is why the first two survived so long.
   *
   * 1. No state anywhere but the `open` class. The trigger is a real <button>
   *    that opens and closes a panel and it said neither whether it was open
   *    nor what it controls — the fourth disclosure widget in this file with
   *    that same gap, after the sidebar groups, the theme picker and the tabs.
   *
   * 2. A closed panel was hidden with `max-height: 0; overflow: hidden`, which
   *    hides it from the EYE only. The text stayed in the accessibility tree,
   *    so a screen reader read every panel whether open or shut and the
   *    accordion did nothing for it at all; and any link or button inside a
   *    closed panel stayed in the tab order, so Tab moved focus into a
   *    zero-height box and it vanished. css/gridkit.css now carries
   *    `visibility` alongside the max-height, which takes closed content out
   *    of both. It flips at the end of the closing animation and immediately
   *    on opening, so nothing about the movement changes.
   *
   * 3. This ran at parse time, one line below the block that defers GK.init()
   *    until DOMContentLoaded — so with the script in <head> it queried a
   *    document that had no accordions in it yet and bound nothing, silently.
   *    Markup added later (a live reload, a modal) got nothing either, for the
   *    same reason. It is an init() now, called when the document is ready and
   *    again from GK.init(), and it is idempotent so both is fine.
   */
  GK.accordion = {
    _n: 0,
    init(root) {
      var self = this;
      (root || document).querySelectorAll(".gk-accordion").forEach(function (acc) {
        if (acc._gkAccordion) return;
        acc._gkAccordion = true;
        var uid = "gk-acc-" + ++self._n;

        acc.querySelectorAll(".gk-accordion-item").forEach(function (item, i) {
          var trigger = item.querySelector(".gk-accordion-trigger");
          var content = item.querySelector(".gk-accordion-content");
          if (!trigger) return;
          var open = item.classList.contains("open");
          if (!trigger.id) trigger.id = uid + "-trigger-" + i;
          trigger.setAttribute("aria-expanded", open ? "true" : "false");
          if (content) {
            if (!content.id) content.id = uid + "-panel-" + i;
            trigger.setAttribute("aria-controls", content.id);
            content.setAttribute("role", "region");
            content.setAttribute("aria-labelledby", trigger.id);
          }
        });

        // One place decides what "this item is open" means, so the class and
        // the attribute cannot part company — including on the items that
        // single-open mode closes as a side effect, which is exactly the kind
        // of second path that gets forgotten.
        function setOpen(item, open) {
          item.classList.toggle("open", open);
          var t = item.querySelector(".gk-accordion-trigger");
          if (t) t.setAttribute("aria-expanded", open ? "true" : "false");
        }

        acc.querySelectorAll(".gk-accordion-trigger").forEach(function (trigger) {
          trigger.addEventListener("click", function () {
            var item = this.closest(".gk-accordion-item");
            var isOpen = item.classList.contains("open");
            // Optional: close others (single-open mode)
            if (acc.dataset.gkSingle !== undefined) {
              acc.querySelectorAll(".gk-accordion-item.open").forEach(function (i) {
                setOpen(i, false);
              });
            }
            setOpen(item, !isOpen);
          });
        });
      });
    },
  };

  // _gkReady, not a bare listener: the library already has this helper and a
  // test enforces its use, because a plain DOMContentLoaded never fires for a
  // script loaded with `async` or pulled in by an AJAX fragment. Writing the
  // guard out by hand here is precisely what that test caught.
  _gkReady(function () { GK.accordion.init(); });

  /*
   * === GALLERY LAZY LOADING ===
   *
   * A tile marked data-lazy carries its picture in `data-src` and gets a real
   * `src` only when it comes near the viewport. That means the observer is not
   * an optimisation the page can do without: a tile nobody observes never
   * loads its image at all, and the gallery stays empty.
   *
   * Which is what happened when the script sat in <head>. This collected the
   * tiles at parse time, one block below the accordion — the same fault, but
   * with a visible consequence rather than a quiet one. It runs when the
   * document is ready now, and again from GK.init() for galleries that arrive
   * later; observe() on an element already being observed does nothing, so
   * running twice is harmless.
   */
  var galleryObs = null;
  function _gkGalleryLazy(root) {
    if (!("IntersectionObserver" in window)) return;
    if (!galleryObs) {
      galleryObs = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) {
              var img = e.target.querySelector("img[data-src]");
              if (img) {
                img.src = img.dataset.src;
                img.onload = function () {
                  e.target.classList.add("loaded");
                };
              }
              galleryObs.unobserve(e.target);
            }
          });
        },
        { rootMargin: "200px" },
      );
    }
    (root || document)
      .querySelectorAll(".gk-gallery-item[data-lazy]")
      .forEach(function (item) {
        galleryObs.observe(item);
      });
  }
  _gkReady(function () { _gkGalleryLazy(); });

  // === LIGHTBOX ===
  (function () {
    var lb = null,
      items = [],
      current = 0,
      // What the lightbox was opened from, so focus can go back to it.
      opener = null;

    /*
     * The lightbox is a full-screen dialog and was not built as one.
     *
     *   - No role and no aria-modal: to a screen reader it was a div lying on
     *     the page, and everything behind it stayed readable.
     *   - No focus handling at all. Opening an image left focus on the page
     *     underneath, so Tab walked through controls hidden behind the
     *     picture, invisible and unclickable, with no way back but Escape.
     *   - The three buttons had no accessible name. Their material-icons spans
     *     were not aria-hidden either, so what was announced was the ligature
     *     text: "close", "chevron_left", "chevron_right".
     *   - The <img> had no alt, so the one thing the dialog exists to show was
     *     the one thing that was not described.
     *
     * The focus helpers are the same ones the modal uses — see _gkFocusable
     * above — rather than a second copy that would drift from the first.
     */
    function createLightbox() {
      if (lb) return;
      lb = document.createElement("div");
      lb.className = "gk-lightbox";
      lb.setAttribute("role", "dialog");
      lb.setAttribute("aria-modal", "true");
      lb.setAttribute("aria-label", _t("lightbox_title"));
      lb.tabIndex = -1;
      lb.innerHTML =
        '<button class="gk-lightbox-close" aria-label="' + _gkEsc(_t("close")) + '">' +
        '<span class="material-icons" aria-hidden="true">close</span></button>' +
        '<button class="gk-lightbox-nav gk-lightbox-prev" aria-label="' + _gkEsc(_t("lightbox_prev")) + '">' +
        '<span class="material-icons" aria-hidden="true">chevron_left</span></button>' +
        '<button class="gk-lightbox-nav gk-lightbox-next" aria-label="' + _gkEsc(_t("lightbox_next")) + '">' +
        '<span class="material-icons" aria-hidden="true">chevron_right</span></button>' +
        // The picture sits in a stage that takes the height the foot leaves it.
        '<div class="gk-lightbox-stage"><img class="gk-lightbox-img" src="" alt=""></div>' +
        '<div class="gk-lightbox-foot">' +
        // The caption and the counter change as you page through, and nothing
        // said so. Announced politely, they now read out on each step.
        '<div class="gk-lightbox-caption" aria-live="polite"></div>' +
        '<div class="gk-lightbox-meta" aria-live="polite"></div>' +
        // What can be done with the picture — only when an entry offers it
        // (an image picker's "select this one"); a plain gallery shows none.
        '<div class="gk-lightbox-actions" hidden>' +
        '<button type="button" class="gk-lightbox-choose"></button>' +
        '<button type="button" class="gk-lightbox-dismiss">' + _gkEsc(_t("close")) + '</button>' +
        "</div></div>" +
        '<div class="gk-lightbox-counter" aria-live="polite"></div>';
      document.body.appendChild(lb);
      lb.querySelector(".gk-lightbox-close").addEventListener("click", function () {
        closeLb();
      });
      lb.querySelector(".gk-lightbox-dismiss").addEventListener("click", function () {
        closeLb();
      });
      lb.querySelector(".gk-lightbox-choose").addEventListener("click", function () {
        var entry = items[current];
        if (!entry || !entry.choose || entry.disabled) return;
        // The entry does the picking and says where the focus goes — the
        // library knows nothing about radios.
        closeLb(entry.choose());
      });
      lb.querySelector(".gk-lightbox-prev").addEventListener(
        "click",
        function () {
          navigate(-1);
        },
      );
      lb.querySelector(".gk-lightbox-next").addEventListener(
        "click",
        function () {
          navigate(1);
        },
      );
      lb.addEventListener("click", function (e) {
        // The dark area around the picture is the stage's, not the overlay's.
        if (e.target === lb || e.target.classList.contains("gk-lightbox-stage")) closeLb();
      });
      lb.addEventListener("keydown", function (e) {
        _gkTrap(lb, e);
      });
      document.addEventListener("keydown", function (e) {
        if (!lb.classList.contains("open")) return;
        if (e.key === "Escape") closeLb();
        if (e.key === "ArrowLeft") navigate(-1);
        if (e.key === "ArrowRight") navigate(1);
      });
    }

    /*
     * One place puts a picture on screen. showLb() and navigate() each carried
     * their own copy of these four lines, which is how the <img> came to have
     * no alt in either of them.
     */
    function render() {
      var item = items[current];
      var img = lb.querySelector(".gk-lightbox-img");
      img.src = item.src;
      // The caption if there is one, otherwise at least which picture this is.
      img.alt = item.caption || _t("lightbox_image", { n: current + 1, m: items.length });
      lb.querySelector(".gk-lightbox-caption").textContent = item.caption || "";
      lb.querySelector(".gk-lightbox-meta").textContent = item.meta || "";
      // The dialog is named by the picture it shows, so opening it says which one.
      lb.setAttribute("aria-label", item.caption || _t("lightbox_title"));
      lb.querySelector(".gk-lightbox-counter").textContent =
        current + 1 + " / " + items.length;
      renderActions(item);
    }

    /*
     * The row under the picture: "select this one" and a plain "Close". Shown
     * when the entries can be picked (an image picker's), otherwise absent, so a
     * gallery that only looks at pictures is exactly what it was. On the picture
     * that is already picked the button says so and stays pressed — it does not
     * pretend there is something left to do — and on one that cannot be picked
     * it is disabled.
     */
    function renderActions(item) {
      var bar = lb.querySelector(".gk-lightbox-actions");
      var pick = items.some(function (e) { return !!e.choose; });
      bar.hidden = !pick;
      lb.classList.toggle("gk-lightbox-pick", pick);
      if (!pick) return;
      var btn = lb.querySelector(".gk-lightbox-choose");
      var picked = !!(item.selected && item.selected());
      var held = document.activeElement === btn;
      btn.disabled = !item.choose || !!item.disabled;
      // A button that disables itself under the focus drops it on the page behind the dialog (and the
      // Tab trap, which listens on the dialog, never hears the next key): the focus moves to "Close".
      if (held && btn.disabled) lb.querySelector(".gk-lightbox-dismiss").focus();
      btn.setAttribute("aria-pressed", picked ? "true" : "false");
      btn.innerHTML = (GK.table && GK.table.iconSvg ? GK.table.iconSvg("check") : "") + "<span></span>";
      btn.querySelector("span").textContent = picked
        ? item.chosenLabel || _t("lightbox_chosen")
        : item.chooseLabel || _t("lightbox_choose");
    }

    function showLb(idx) {
      createLightbox();
      current = idx;
      render();
      lb.querySelector(".gk-lightbox-prev").style.display =
        items.length > 1 ? "" : "none";
      lb.querySelector(".gk-lightbox-next").style.display =
        items.length > 1 ? "" : "none";
      lb.classList.add("open");
      document.body.style.overflow = "hidden";
      _gkFocusInto(lb);
    }

    /*
     * Closing gives the focus back. To the tile it was opened from — or, when the
     * entries know better, to where the person is now: after paging from picture
     * 3 to picture 8 the place they know is picture 8, not 3. `back` is that
     * place, handed over when something was picked (the radio that took the
     * pick); without it the entry names its own (entry.returnTo) and the opener
     * is the last resort. A target that is not on screen (a folded tile) takes
     * no focus, so each is checked before it is used.
     */
    function closeLb(back) {
      var entry = items[current];
      if (lb) lb.classList.remove("open");
      document.body.style.overflow = "";
      var seen = function (el) { return !!el && el.isConnected && el.offsetParent !== null; };
      var first = opener;
      var target = back && back.nodeType === 1 ? back : null;
      if (!seen(target) && entry && entry.returnTo) target = entry.returnTo();
      if (seen(target)) opener = target;
      // Back to the thumbnail it was opened from.
      _gkRestoreFocus(opener);
      // The place the person knows may not be the one they started from (after paging from 3 to 29
      // the zoom button of 29 can be far below the fold): focus without scrolling left a ring nobody saw.
      if (opener && opener !== first && opener.scrollIntoView) {
        try { opener.scrollIntoView({ block: "nearest", inline: "nearest" }); } catch (err) { /* old browser: the focus is where it is */ }
      }
      opener = null;
    }

    function navigate(dir) {
      current = (current + dir + items.length) % items.length;
      render();
    }

    /*
     * A gallery tile is a <div>: not focusable, not operable, so the lightbox
     * could not be opened by keyboard AT ALL. Only a mouse ever reached it.
     *
     * The markup is written by the page author, so the tile is made operable
     * here rather than demanded of them: role, a tab stop, and a name taken
     * from the caption it already carries. Enter and Space then open it, the
     * way a button does.
     */
    function decorate(root) {
      // A gallery that arrives later needs both halves: its tiles made
      // operable AND its pictures observed. One call does both, so a caller
      // cannot remember one and forget the other.
      _gkGalleryLazy(root);
      (root || document)
        .querySelectorAll(".gk-gallery-item[data-lightbox]")
        .forEach(function (item) {
          if (item._gkGallery) return;
          item._gkGallery = true;
          if (!item.hasAttribute("role")) item.setAttribute("role", "button");
          if (!item.hasAttribute("tabindex")) item.tabIndex = 0;
          if (!item.hasAttribute("aria-label")) {
            var img = item.querySelector("img");
            var name = item.dataset.caption || (img ? img.alt : "") || "";
            item.setAttribute(
              "aria-label",
              name ? _t("lightbox_open_named", { name: name }) : _t("lightbox_open"),
            );
          }
        });
    }

    function openFrom(galleryItem) {
      var gallery = galleryItem.closest(".gk-gallery, .gk-gallery-masonry");
      if (!gallery) return;
      var allItems = gallery.querySelectorAll(
        ".gk-gallery-item[data-lightbox]",
      );
      items = [];
      var clickIdx = 0;
      allItems.forEach(function (item, i) {
        var img = item.querySelector("img");
        items.push({
          src:
            item.dataset.lightbox ||
            (img ? img.dataset.full || img.dataset.src || img.src : ""),
          caption: item.dataset.caption || (img ? img.alt : "") || "",
        });
        if (item === galleryItem) clickIdx = i;
      });
      opener = galleryItem;
      showLb(clickIdx);
    }

    // Click on gallery items
    document.addEventListener("click", function (e) {
      var galleryItem = e.target.closest(".gk-gallery-item[data-lightbox]");
      if (!galleryItem) return;
      e.preventDefault();
      openFrom(galleryItem);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" && e.key !== " " && e.key !== "Spacebar") return;
      var galleryItem = e.target.closest
        ? e.target.closest(".gk-gallery-item[data-lightbox]")
        : null;
      if (!galleryItem) return;
      // Space scrolls the page otherwise, which is the wrong answer to
      // "activate the thing I have focused".
      e.preventDefault();
      openFrom(galleryItem);
    });

    _gkReady(function () {
      decorate();
    });

    /*
     * Open the viewer on a list the caller built: [{src, caption, meta,
     * choose(), selected(), disabled, returnTo(), chooseLabel, chosenLabel}],
     * starting at `index`, with `from` as the place the focus returns to. Only
     * src is required. An entry with choose() can be picked from the viewer —
     * choose() does it and returns the element that should have the focus
     * afterwards. The image picker uses this; the gallery's own tiles come in
     * through openFrom() and are the same thing with only src and caption.
     */
    function show(entries, index, from) {
      if (!entries || !entries.length) return;
      items = entries;
      opener = from || document.activeElement;
      showLb(Math.max(0, Math.min(index || 0, entries.length - 1)));
    }

    /** The viewer is open and something changed behind it (a picture was picked, one arrived): draw the buttons again. */
    function refreshLb() {
      if (lb && lb.classList.contains("open") && items[current]) renderActions(items[current]);
    }

    // Expose for external use
    window.GK = window.GK || {};
    GK.lightbox = { open: showLb, close: closeLb, init: decorate, show: show, refresh: refreshLb };
  })();

  // === IMAGE PICKER (1.94.0) ===
  /*
   * Choose one image out of many (GridKit\ImagePicker writes the markup). The
   * tiles are native radios, so picking, the arrow keys, the form and the
   * screen reader work without this file. What it adds:
   *
   *   - the fold: only the first `rows` rows show, then "Show all N". The tiles
   *     are counted by what the browser laid out (the grid's resolved columns),
   *     not by a guess about the width, and again when the width changes. The
   *     picked tile and the one that has the focus never fold away;
   *   - a roving tab stop for the magnifiers: the zoom button of ONE tile — the
   *     one with the focus, else the picked one — is in the tab order, the
   *     others are not (thirty magnifiers would be thirty stops between the radio
   *     group and the next field; the arrow keys already move between tiles, and
   *     the viewer reaches every picture). A caption action stays in the tab
   *     order on every tile: the focused tile is always the picked one, which an
   *     application will not let you remove;
   *   - the status line ("Selected: …");
   *   - the viewer: the zoom button opens the library's lightbox on the large
   *     versions, with "select this image" in it (GK.lightbox.show);
   *   - gk:pickeraction: a caption button fires it and does nothing else. The
   *     application decides — and asks "Really?" if it deletes.
   *
   * GK.picker.add() and .remove() are for a tile that arrives or goes after the
   * first paint (an upload); tileHtml() writes the same markup PHP does.
   */
  (function () {
    var KEYS = { zoom: "picker_zoom", choose: "lightbox_choose", more: "picker_more", less: "picker_less", selected: "picker_selected", none: "picker_none" };

    /** The words: the container's own (the application's), else the library's in the active language. */
    function label(c, key) {
      var own = c.getAttribute("data-gk-picker-label-" + key);
      return own !== null && own !== "" ? own : _t(KEYS[key]);
    }

    function tiles(c) {
      return Array.prototype.slice.call(c.querySelectorAll(".gk-picker-item"));
    }

    function tileOf(el) {
      return el && el.closest ? el.closest(".gk-picker-item") : null;
    }

    function inputOf(tile) {
      return tile.querySelector(".gk-picker-input");
    }

    function titleOf(tile) {
      var t = tile.querySelector(".gk-picker-title");
      return t ? t.textContent : "";
    }

    function status(c) {
      var el = c.querySelector("[data-gk-picker-status]");
      if (!el) return;
      var r = c.querySelector(".gk-picker-input:checked");
      // A function as the replacement: a title with "$&" or "$$" in it is text, not a replacement pattern.
      el.textContent = r ? label(c, "selected").replace("{name}", function () { return titleOf(tileOf(r)); }) : label(c, "none");
    }

    /*
     * Which tile's magnifier is in the tab order. `focused` is the tile the focus
     * is in right now; without it the picked tile, else the first one shown. Only
     * the magnifiers are rationed — the viewer reaches every picture, and the
     * arrow keys move between tiles. A caption action ("Remove") stays in the tab
     * order on EVERY tile: with a native radio group the tile that has the focus
     * is always the picked one, and an application will not let the picked image
     * be removed, so rationing the actions would have made the others
     * unreachable from the keyboard (found in review).
     */
    function rove(c, focused) {
      var list = tiles(c);
      var checked = c.querySelector(".gk-picker-input:checked");
      var active = focused || (checked && tileOf(checked)) || list.filter(function (t) { return !t.hidden; })[0] || null;
      list.forEach(function (t) {
        t.querySelectorAll(".gk-picker-zoom").forEach(function (b) {
          b.setAttribute("tabindex", t === active ? "0" : "-1");
        });
      });
    }

    /*
     * Fold: show the first `rows` rows. How many tiles that is comes from what
     * the browser laid out — the number of columns the grid resolved to
     * (getComputedStyle(grid).gridTemplateColumns) — and not from a guess about
     * the width. Nothing has to be shown to be counted: the columns are read from
     * the grid whatever is folded, so folding and unfolding never lays the whole
     * list out first. (A browser may already have asked for the lazy pictures of
     * tiles that this script folds a moment later — the script usually comes at
     * the end of the page, after the first layout. Measured in a real page: all
     * the pictures were asked for. That is why the tile's picture is the
     * application's SMALL one: folded tiles cost a few kilobytes, not a megabyte.)
     * The picked tile and the one holding the focus are never folded away — they
     * must stay where the person can see them. With nothing beyond the rows,
     * there is no button.
     */
    function fold(c) {
      var rows = parseInt(c.getAttribute("data-gk-picker-rows") || "2", 10);
      var btn = c.querySelector("[data-gk-picker-more-btn]");
      var grid = c.querySelector(".gk-picker-grid");
      var list = tiles(c);
      var cols = grid ? Math.max(1, getComputedStyle(grid).gridTemplateColumns.split(" ").filter(Boolean).length) : 1;
      var shown = rows > 0 ? rows * cols : list.length;
      var foldable = rows > 0 && list.length > shown;
      // Nothing to fold (the list shrank, the window widened): drop an old "open", or pictures that arrive
      // later would show up unfolded without anyone having asked.
      if (!foldable) c.removeAttribute("data-gk-picker-open");
      var open = c.hasAttribute("data-gk-picker-open");
      list.forEach(function (t, i) {
        t.hidden = foldable && !open && i >= shown && !t.querySelector(".gk-picker-input:checked") && !t.contains(document.activeElement);
      });
      if (!btn) return;
      btn.hidden = !foldable;
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      btn.textContent = open ? label(c, "less") : label(c, "more").replace("{n}", String(list.length));
    }

    var pending = new WeakMap();

    /** One refresh a frame, however many things asked for it. */
    function refresh(c) {
      if (pending.has(c)) return;
      pending.set(c, requestAnimationFrame(function () {
        pending.delete(c);
        fold(c);
        status(c);
        rove(c, tileOf(document.activeElement) && c.contains(document.activeElement) ? tileOf(document.activeElement) : null);
      }));
    }

    function refreshNow(c) {
      if (pending.has(c)) {
        cancelAnimationFrame(pending.get(c));
        pending.delete(c);
      }
      fold(c);
      status(c);
      rove(c, tileOf(document.activeElement) && c.contains(document.activeElement) ? tileOf(document.activeElement) : null);
    }

    function init(root) {
      Array.prototype.forEach.call((root && root.querySelectorAll ? root : document).querySelectorAll("[data-gk-picker]"), function (c) {
        if (c._gkPicker) return;
        c._gkPicker = true;
        refreshNow(c);
        var grid = c.querySelector(".gk-picker-grid");
        if (!grid) return;
        // New tiles (add(), or the application's own markup) and a new width
        // both change what folds. Only the WIDTH is watched: hiding tiles changes
        // the height, and refreshing on that would chase its own tail.
        if (window.MutationObserver) new MutationObserver(function () { refresh(c); }).observe(grid, { childList: true });
        if (window.ResizeObserver) {
          var width = grid.getBoundingClientRect().width;
          new ResizeObserver(function () {
            var w = grid.getBoundingClientRect().width;
            if (w !== width) { width = w; refresh(c); }
          }).observe(grid);
        }
      });
    }

    /** What the viewer is shown for one tile — see GK.lightbox.show. */
    function entry(c, tile) {
      var input = inputOf(tile);
      var more = [tile.getAttribute("data-meta"), tile.getAttribute("data-note")].filter(Boolean).join(" — ");
      return {
        src: tile.getAttribute("data-lightbox"),
        caption: tile.getAttribute("data-caption") || titleOf(tile),
        meta: more,
        chooseLabel: label(c, "choose"),
        disabled: input.disabled,
        selected: function () { return input.checked; },
        choose: function () {
          // A click, not input.checked = true: the page hears the same events a tap on the tile makes.
          if (!input.checked && !input.disabled) input.click();
          return input;
        },
        returnTo: function () {
          var z = tile.querySelector(".gk-picker-zoom");
          return z && z.offsetParent !== null ? z : c.querySelector("[data-gk-picker-more-btn]:not([hidden])");
        },
      };
    }

    function zoom(btn) {
      var tile = tileOf(btn);
      var c = tile.closest("[data-gk-picker]");
      var list = tiles(c).filter(function (t) { return t.hasAttribute("data-lightbox"); });
      GK.lightbox.show(list.map(function (t) { return entry(c, t); }), list.indexOf(tile), btn);
    }

    /** The tile's markup — the same GridKit\ImagePicker::tileHtml writes (ci/browser-picker.js compares them). */
    function tileHtml(it, o) {
      var e = _gkEsc;
      var icon = function (name) { return GK.table && GK.table.iconSvg ? GK.table.iconSvg(name) : ""; };
      var n = String(o.id).replace(/[^A-Za-z0-9_-]/g, "") + "-" + (o.index || 1);   // the same cut PHP makes
      var value = it.value == null ? "" : String(it.value);
      var title = it.title ? String(it.title) : value;
      var thumb = it.thumb ? String(it.thumb) : "";
      var full = it.full ? String(it.full) : "";
      var meta = it.meta ? String(it.meta) : "";
      var note = it.note ? String(it.note) : "";
      var badge = it.badge ? String(it.badge) : "";
      var alt = it.alt ? String(it.alt) : title;
      var zoomLabel = o.zoom || _t("picker_zoom");
      var described = [meta ? n + "-d" : "", note ? n + "-n" : ""].filter(Boolean).join(" ");
      var attrs = "";
      if (full) {
        attrs += ' data-lightbox="' + e(full) + '" data-caption="' + e(alt) + '"' +
          (meta ? ' data-meta="' + e(meta) + '"' : "") + (note ? ' data-note="' + e(note) + '"' : "");
      }
      Object.keys(it.data || {}).forEach(function (k) {
        var key = k.toLowerCase().replace(/[^a-z0-9-]/g, "");
        if (key && it.data[k] != null && typeof it.data[k] !== "object") attrs += " data-" + key + '="' + e(it.data[k]) + '"';
      });
      var h = '<div class="gk-picker-item"' + attrs + ">" +
        '<div class="gk-picker-media">' +
        '<input class="gk-picker-input" type="radio" id="' + n + '" name="' + e(o.name) + '" value="' + e(value) + '"' +
        ' aria-labelledby="' + n + '-t"' + (described ? ' aria-describedby="' + described + '"' : "") +
        (it.checked ? " checked" : "") + (it.disabled ? " disabled" : "") + ">" +
        (thumb
          ? '<img class="gk-picker-img" src="' + e(thumb) + '" alt="" loading="lazy" decoding="async">'
          : '<span class="gk-picker-ph" aria-hidden="true">' + icon("image") + "</span>") +
        '<span class="gk-picker-check" aria-hidden="true">' + icon("check") + "</span>" +
        (badge ? '<span class="gk-picker-badge" aria-hidden="true">' + e(badge) + "</span>" : "");
      if (full) {
        h += '<button type="button" class="gk-picker-zoom" data-gk-picker-zoom aria-label="' + e(zoomLabel + ": " + title) +
          '" title="' + e(zoomLabel) + '">' + icon("zoom_in") + "</button>";
      }
      h += "</div>" +
        '<div class="gk-picker-caption">' +
        '<label class="gk-picker-title" id="' + n + '-t" for="' + n + '" title="' + e(title) + '">' + e(title) + "</label>";
      (it.actions || []).forEach(function (a) {
        var name = a.name == null ? "" : String(a.name);
        var lab = a.label == null ? name : String(a.label);
        h += '<button type="button" class="gk-picker-action" data-gk-picker-action="' + e(name) + '" aria-label="' + e(lab + ": " + title) +
          '" title="' + e(lab) + '">' + icon(a.icon ? String(a.icon) : "delete") + "</button>";
      });
      h += "</div>" +
        (note ? '<p class="gk-picker-note" id="' + n + '-n">' + e(note) + "</p>" : "") +
        (meta ? '<span class="gk-sr-only" id="' + n + '-d">' + e(meta) + "</span>" : "") +
        "</div>";
      return h;
    }

    GK.picker = {
      init: init,
      refresh: refreshNow,
      tileHtml: tileHtml,

      /**
       * Append a tile to a picker (an upload that arrived). {select: true} picks it as a click would
       * — one change event for the page — and {focus: true} puts the focus on it. Returns the tile.
       */
      add: function (c, data, opts) {
        opts = opts || {};
        var grid = c.querySelector(".gk-picker-grid");
        var id = c.getAttribute("data-gk-picker-id") || c.id || "gk-picker";
        var index = tiles(c).length + 1;
        while (document.getElementById(id + "-" + index)) index++;
        var holder = document.createElement("template");
        holder.innerHTML = tileHtml(data, { name: c.getAttribute("data-gk-picker-name"), id: id, index: index, zoom: label(c, "zoom") });
        var tile = holder.content.firstElementChild;
        grid.appendChild(tile);
        var input = inputOf(tile);
        // A picture that arrives while the viewer is open (an upload that took a while) must not take the
        // focus out from under it — the page behind the dialog is not where the person is.
        var viewerOpen = !!document.querySelector(".gk-lightbox.open");
        if (opts.select && !input.disabled) input.click();
        refreshNow(c);
        if (viewerOpen && GK.lightbox && GK.lightbox.refresh) GK.lightbox.refresh();
        if (opts.focus && !viewerOpen) input.focus();
        return tile;
      },

      /** Take the tile with this value out. The focus is the caller's to place. */
      remove: function (c, value) {
        var gone = tiles(c).filter(function (t) { return inputOf(t).value === String(value); });
        gone.forEach(function (t) { t.remove(); });
        refreshNow(c);
        return gone.length;
      },
    };

    document.addEventListener("click", function (e) {
      var t = e.target;
      if (!t.closest) return;
      var z = t.closest("[data-gk-picker-zoom]");
      if (z) { zoom(z); return; }
      var a = t.closest("[data-gk-picker-action]");
      if (a) {
        var tile = tileOf(a);
        var input = tile && inputOf(tile);
        a.dispatchEvent(new CustomEvent("gk:pickeraction", {
          bubbles: true,
          detail: { action: a.getAttribute("data-gk-picker-action"), value: input ? input.value : "", item: tile, picker: a.closest("[data-gk-picker]") },
        }));
        return;
      }
      var m = t.closest("[data-gk-picker-more-btn]");
      if (m) {
        var c = m.closest("[data-gk-picker]");
        if (c.hasAttribute("data-gk-picker-open")) c.removeAttribute("data-gk-picker-open");
        else c.setAttribute("data-gk-picker-open", "");
        refreshNow(c);
      }
    });

    document.addEventListener("change", function (e) {
      var t = e.target;
      if (!t.classList || !t.classList.contains("gk-picker-input")) return;
      var c = t.closest("[data-gk-picker]");
      if (c) refreshNow(c);
      if (GK.lightbox && GK.lightbox.refresh) GK.lightbox.refresh();
    });

    document.addEventListener("focusin", function (e) {
      var c = e.target.closest ? e.target.closest("[data-gk-picker]") : null;
      if (c) rove(c, tileOf(e.target));
    });

    document.addEventListener("focusout", function (e) {
      var c = e.target.closest ? e.target.closest("[data-gk-picker]") : null;
      if (c && !(e.relatedTarget && c.contains(e.relatedTarget))) rove(c, null);
    });

    _gkReady(function () { GK.picker.init(); });
  })();
})();

// Run now if the document is already parsed, otherwise on DOMContentLoaded.
// A bare addEventListener("DOMContentLoaded") never fires when the script is
// loaded with `async`, injected into a page after load, or pulled in by an
// AJAX fragment — the tooltip then silently does nothing and there is no error
// to go on. The main bootstrap has always guarded this; these two did not.
function _gkReady(fn) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fn);
  } else {
    fn();
  }
}

// === TOOLTIP (Rich) ===
GK.tooltip = {
  init() {
    document.querySelectorAll("[data-gk-tooltip-rich]").forEach((el) => {
      if (el._gkTipRich) return;
      el._gkTipRich = true;
      const targetId = el.getAttribute("data-gk-tooltip-rich");
      const tip = document.querySelector(targetId);
      if (!tip) return;
      tip.classList.add("gk-tooltip-content");

      /*
       * mouseenter and mouseleave were the only two events here, so the rich
       * tooltip existed for pointers and for nobody else: with a keyboard you
       * could never see it, and there was nothing joining the trigger to the
       * text — a screen reader was never told the description was there at
       * all. Three things fix that, and the third is a rule people forget:
       * content shown on hover or focus has to be dismissible without moving
       * away from it, which is what Escape is for.
       */
      if (tip.id) {
        const described = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
        if (described.indexOf(tip.id) === -1) {
          described.push(tip.id);
          el.setAttribute("aria-describedby", described.join(" "));
        }
      }
      // A trigger a keyboard cannot land on cannot show a tooltip on focus.
      // Same call as the gallery tile: make the element the markup already
      // treats as interactive actually reachable.
      if (!el.hasAttribute("tabindex") && !el.matches("a[href], button, input, select, textarea")) {
        el.tabIndex = 0;
      }

      const show = () => {
        const rect = el.getBoundingClientRect();
        tip.style.position = "fixed";
        tip.style.left = rect.left + "px";
        tip.style.top = rect.bottom + 6 + "px";

        // Keep within viewport
        tip.classList.add("visible");
        const tipRect = tip.getBoundingClientRect();
        if (tipRect.right > window.innerWidth - 8) {
          tip.style.left = window.innerWidth - tipRect.width - 8 + "px";
        }
        if (tipRect.bottom > window.innerHeight - 8) {
          tip.style.top = rect.top - tipRect.height - 6 + "px";
        }
      };
      const hide = () => tip.classList.remove("visible");

      el.addEventListener("mouseenter", show);
      el.addEventListener("focus", show);

      el.addEventListener("mouseleave", () => {
        // Keep visible if mouse moves to tooltip itself
        setTimeout(() => {
          if (!tip.matches(":hover") && !el.matches(":hover")) hide();
        }, 100);
      });
      el.addEventListener("blur", hide);
      el.addEventListener("keydown", (e) => {
        if (e.key === "Escape") hide();
      });

      tip.addEventListener("mouseleave", () => {
        if (!el.matches(":hover")) hide();
      });
    });
  },
};
_gkReady(() => GK.tooltip.init());

// === TOOLTIP (Global) — upgrades native title attributes to GK popups ===
// Every element with a title gets a styled popup on hover (300 ms delay, above
// the element, clamped to the viewport, \n = line break). On the first hover the
// title is moved to data-gk-tip (which suppresses the browser's own popup).
// Opt-out: data-gk-tip-off on the element or on one of its ancestors.
GK.tip = {
  el: null,
  cur: null,
  timer: null,
  ensure() {
    if (this.el) return this.el;
    var d = document.createElement("div");
    d.className = "gk-tip";
    d.hidden = true;
    document.body.appendChild(d);
    this.el = d;
    return d;
  },
  show(target) {
    var text = target.getAttribute("data-gk-tip");
    if (!text) return;
    var d = this.ensure();
    d.textContent = text;
    d.hidden = false;
    d.style.left = "0px";
    d.style.top = "0px";
    var r = target.getBoundingClientRect();
    var tw = d.offsetWidth, th = d.offsetHeight;
    var x = Math.min(Math.max(8, r.left + r.width / 2 - tw / 2), window.innerWidth - tw - 8);
    var y = r.top - th - 8;
    if (y < 4) y = r.bottom + 8;
    d.style.left = x + "px";
    d.style.top = y + "px";
  },
  hide() {
    clearTimeout(this.timer);
    this.timer = null;
    this.cur = null;
    if (this.el) this.el.hidden = true;
  },
  init() {
    var self = this;
    document.addEventListener("mouseover", function (e) {
      var t = e.target && e.target.closest ? e.target.closest("[title], [data-gk-tip]") : null;
      if (!t || self.cur === t) return;
      if (t.closest("[data-gk-tip-off]")) return;
      var title = t.getAttribute("title");
      if (title) {
        t.setAttribute("data-gk-tip", title);
        // For an icon-only control the title IS the accessible name. Taking it
        // away to stop the browser drawing its own tooltip left the button
        // nameless — a screen reader announced "button" and nothing else, and
        // it happened on the first hover, so the markup looked correct in
        // every static check. Hand the name to aria-label before removing it,
        // unless the element already has a name of its own.
        if (!t.getAttribute("aria-label") && !t.getAttribute("aria-labelledby")) {
          t.setAttribute("aria-label", title);
        }
        t.removeAttribute("title");
      }
      if (!t.getAttribute("data-gk-tip")) return;
      self.cur = t;
      clearTimeout(self.timer);
      self.timer = setTimeout(function () { if (self.cur === t) self.show(t); }, 300);
    });
    document.addEventListener("mouseout", function (e) {
      if (self.cur && (!e.relatedTarget || !self.cur.contains(e.relatedTarget))) self.hide();
    });
    ["scroll", "click", "keydown"].forEach(function (ev) {
      document.addEventListener(ev, function () { self.hide(); }, true);
    });
  },
};
_gkReady(() => GK.tip.init());

// A rich-text field marked required. CKEditor writes into a hidden input and
// hides the rest, so the browser can neither validate nor focus it: the red
// star beside the label was the only thing saying the field mattered. Checked
// here, in the capture phase, so it also holds for a plain non-AJAX form.
document.addEventListener(
  "submit",
  function (e) {
    var form = e.target;
    if (!form || form.nodeName !== "FORM") return;
    var missing = null;
    form.querySelectorAll("[data-gk-required-rich]").forEach(function (el) {
      if (missing) return;
      // Strip the markup CKEditor leaves behind for an "empty" document.
      var text = String(el.value || "")
        .replace(/<[^>]*>/g, "")
        .replace(/&nbsp;|\s/g, "");
      if (text === "") missing = el;
    });
    if (!missing) return;
    e.preventDefault();
    e.stopPropagation();
    var label = missing.getAttribute("data-gk-required-rich") || "";
    var msg = _tOr("field_required", "{name} is required.").replace("{name}", label); // _t lives inside the closure above: here it threw before the toast
    if (window.GK && GK.toast) GK.toast.error(msg);
    else alert(msg);
    var wrap = missing.previousElementSibling;
    var editable = wrap && wrap.querySelector(".ck-editor__editable");
    if (editable) editable.focus();
    else if (wrap && wrap.scrollIntoView) wrap.scrollIntoView({ block: "center" });
  },
  true,
);


// === SEARCH (GK.search) =================================================
// System-wide quick search. GridKit supplies only the control element — WHAT
// gets found is decided by each system through the configured address.
//
//   GK.search.init({ url: '/api/search', hotkey: 'ctrl+k', minLength: 2 })
//
// Server response:
//   { groups: [ { title: 'Transactions',
//                 items: [ { title, subtitle, amount, url, icon } ] } ] }
//
// The German key names — gruppen / titel / treffer / untertitel / betrag —
// are still read as a fallback so endpoints written before 1.39 keep working.
// When a translation is missing, _t returns the KEY. That is usable for internal
// purposes, but not in the control element: there it literally read
// "search_error" instead of a message (reported 2026-07-31). This helper takes
// the replacement text as soon as no real translation is available.
function _tOr(key, fallback) {
  var value = GK.t(key);
  return value && value !== key ? value : fallback;
}

GK.search = {
  cfg: null,
  overlay: null,
  input: null,
  list: null,
  hits: [],
  active: -1,
  timer: null,
  controller: null,
  lastFocus: null,

  init(options) {
    this.cfg = Object.assign(
      {
        url: "/api/search",
        hotkey: "ctrl+k",
        minLength: 2,
        placeholder: _tOr("search_placeholder", "Search …"),
        hint: _tOr("search_hint", "Type to search."),
        empty: _tOr("search_empty", "Nothing found."),
        error: _tOr("search_error", "Search unavailable."),
      },
      options || {},
    );

    var self = this;
    var combo = String(this.cfg.hotkey).toLowerCase();
    document.addEventListener("keydown", function (e) {
      var mod = combo.indexOf("ctrl") >= 0 && (e.ctrlKey || e.metaKey);
      var key = combo.split("+").pop();
      if (mod && e.key.toLowerCase() === key) {
        e.preventDefault();
        self.open();
      }
    });
    // Usable without a keyboard as well.
    document.addEventListener("click", function (e) {
      var trigger = e.target.closest && e.target.closest("[data-gk-search]");
      if (!trigger) return;
      // A Table's own filter box carries data-gk-search too — Table::search()
      // has always rendered <input class="gk-search" data-gk-search>. So on a
      // page with both, clicking the table's box opened the global overlay on
      // top of it, and the obvious fix (dropping the attribute) killed the
      // table's own filtering instead. The overlay belongs to the page-level
      // trigger; a table's box belongs to its table.
      if (trigger.closest("[data-gk-table]") || trigger.closest(".gk-toolbar")) return;
      e.preventDefault();
      self.open();
    });
  },

  open() {
    if (this.overlay) return;
    if (!this.cfg) this.init();
    this.lastFocus = document.activeElement;

    var ov = document.createElement("div");
    ov.className = "gk-search-overlay";
    ov.innerHTML =
      '<div class="gk-search-box" role="combobox" aria-expanded="true" aria-haspopup="listbox">' +
      '<input class="gk-search gk-search-feld" type="search" autocomplete="off" spellcheck="false"' +
      ' aria-autocomplete="list" aria-controls="gk-search-liste"' +
      // Straight into an attribute. A placeholder holding a double quote
      // ended the attribute and everything after it became markup — the
      // module has had its own esc() all along, four methods further down.
      ' placeholder="' + this.esc(this.cfg.placeholder) + '">' +
      '<div class="gk-search-liste" id="gk-search-liste" role="listbox"></div>' +
      "</div>";
    document.body.appendChild(ov);

    this.overlay = ov;
    this.input = ov.querySelector(".gk-search-feld");
    this.list = ov.querySelector(".gk-search-liste");
    this.showNotice(this.cfg.hint);

    var self = this;
    ov.addEventListener("click", function (e) { if (e.target === ov) self.close(); });
    this.input.addEventListener("input", function () { self.entprellt(); });
    this.input.addEventListener("keydown", function (e) { self.onKey(e); });
    // With the focus outside the input (a click on the hit list) nothing heard
    // Escape any more — except a modal underneath, which closed instead.
    this._esc = function (e) {
      if (e.key !== "Escape" || e.defaultPrevented) return;
      e.preventDefault();
      self.close();
    };
    document.addEventListener("keydown", this._esc);
    setTimeout(function () { self.input.focus(); }, 20);
  },

  close() {
    if (!this.overlay) return;
    if (this._esc) { document.removeEventListener("keydown", this._esc); this._esc = null; }
    if (this.controller) { this.controller.abort(); this.controller = null; }
    clearTimeout(this.timer);
    this.overlay.remove();
    this.overlay = this.input = this.list = null;
    this.hits = [];
    this.active = -1;
    if (this.lastFocus && this.lastFocus.focus) this.lastFocus.focus();
  },

  onKey(e) {
    if (e.key === "Escape") { e.preventDefault(); this.close(); return; }
    if (e.key === "Tab") { e.preventDefault(); return; }   // focus stays trapped
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      if (!this.hits.length) return;
      this.active += e.key === "ArrowDown" ? 1 : -1;
      if (this.active < 0) this.active = this.hits.length - 1;
      if (this.active >= this.hits.length) this.active = 0;
      this.highlight();
      return;
    }
    if (e.key === "Enter" && this.active >= 0 && this.hits[this.active]) {
      e.preventDefault();
      var url = this.hits[this.active].url;
      if (url) location.href = url;
    }
  },

  entprellt() {
    var self = this;
    clearTimeout(this.timer);
    var q = this.input.value.trim();
    if (q.length < this.cfg.minLength) {
      if (this.controller) { this.controller.abort(); this.controller = null; }
      this.hits = []; this.active = -1;
      this.showNotice(this.cfg.hint);
      return;
    }
    this.timer = setTimeout(function () { self.suche(q); }, 200);
  },

  suche(q) {
    var self = this;
    // Abort a request already in flight — otherwise an old response overtakes the new one.
    if (this.controller) this.controller.abort();
    this.controller = new AbortController();
    this.showSpinner();

    fetch(this.cfg.url + (this.cfg.url.indexOf("?") >= 0 ? "&" : "?") + "q=" + encodeURIComponent(q), {
      headers: { "X-Requested-With": "XMLHttpRequest" },
      signal: this.controller.signal,
    })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (d) { self.show(d.groups || d.gruppen || [], q); })
      .catch(function (err) {
        if (err.name === "AbortError") return;
        self.showNotice(self.cfg.error);
      });
  },

    show(groups, q) {
    this.hits = [];
    this.active = -1;
    var html = "";
    var self = this;

      groups.forEach(function (g) {
        // The response contract used German key names — an English-facing
        // library asking for `gruppen`, `titel`, `treffer`. English is the
        // documented shape now; the German names keep working, so an endpoint
        // written against the old one does not break.
        var items = g.items || g.treffer || [];
        if (!items.length) return;
        html += '<div class="gk-search-gruppe">' + self.esc(g.title || g.titel || "") + "</div>";
        items.forEach(function (t) {
        var i = self.hits.length;
        self.hits.push(t);
        html +=
          '<a class="gk-search-treffer" role="option" id="gk-t' + i + '"' +
          ' data-i="' + i + '" href="' + self.esc(t.url || "#") + '">' +
          (t.icon ? '<span class="material-icons gk-search-icon">' + self.esc(t.icon) + "</span>" : "") +
          '<span class="gk-search-text"><span class="gk-search-titel">' +
          self.mark(t.title || t.titel || "", q) + "</span>" +
          ((t.subtitle || t.untertitel) ? '<span class="gk-search-unter">' + self.mark(t.subtitle || t.untertitel, q) + "</span>" : "") +
          "</span>" +
          ((t.amount || t.betrag) ? '<span class="gk-search-betrag">' + self.esc(t.amount || t.betrag) + "</span>" : "") +
          "</a>";
      });
    });

    if (!this.hits.length) { this.showNotice(this.cfg.empty); return; }
    this.list.innerHTML = html;
    this.list.querySelectorAll(".gk-search-treffer").forEach(function (el) {
      el.addEventListener("mouseenter", function () {
        self.active = parseInt(el.dataset.i, 10);
        self.highlight();
      });
    });
    this.active = 0;
    this.highlight();
  },

  highlight() {
    var self = this;
    this.list.querySelectorAll(".gk-search-treffer").forEach(function (el, i) {
      el.classList.toggle("ist-active", i === self.active);
      if (i === self.active) {
        el.scrollIntoView({ block: "nearest" });
        self.input.setAttribute("aria-activedescendant", el.id);
      }
    });
  },

  /*
   * A notice is a sentence: the hint, the empty result, the error. It was
   * pasted into innerHTML, which was safe only because every caller happened
   * to pass a config string — until the day someone sets `error` from what the
   * search endpoint replied. Text is text.
   *
   * The spinner is the one caller that genuinely wants an element, so it has
   * its own method instead of a shared door left open for it.
   */
  showNotice(text) {
    this.hits = [];
    this.active = -1;
    if (!this.list) return;
    this.list.innerHTML = '<div class="gk-search-hinweis"></div>';
    this.list.firstChild.textContent = text;
  },

  showSpinner() {
    this.hits = [];
    this.active = -1;
    if (this.list) {
      this.list.innerHTML =
        '<div class="gk-search-hinweis"><span class="gk-search-laedt"></span></div>';
    }
  },

  esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  },

  /** Highlight the search term in the match — on the escaped text, never on the raw one. */
  mark(text, q) {
    var e = this.esc(text);
    if (!q) return e;
    var muster = q.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    return e.replace(new RegExp("(" + muster + ")", "ig"), "<mark>$1</mark>");
  },
};

/* ══════════════════════════════════════════════════════════════════════════
 * 1.105.0 — additions that need script, in one block at the end of the file.
 *
 * Each is opt-in markup: a page that does not use it never meets this code, and a copy of this
 * file without the block (the PawBot dashboard keeps its own) renders the same pages as before —
 * the bulk bar shows only its delete button, no CSV button reacts, every conditional field stays visible.
 *
 *   1. Table bulk actions     [data-gk-bulk-action]   → the event gk:bulkaction
 *   2. Table CSV export       [data-gk-export-csv]    → a file, or ?gk_export=csv for a server table
 *   3. Conditional form field [data-gk-show-if]       → shown, or hidden and disabled
 * ══════════════════════════════════════════════════════════════════════════ */

// 1 ── bulk actions: the same shape gk:bulkdelete has, so the application decides what happens.
document.addEventListener("click", function (ev) {
  var btn = ev.target.closest ? ev.target.closest("[data-gk-bulk-action]") : null;
  if (!btn) return;
  var wrap = btn.closest("[data-gk-table]");
  if (!wrap) return;
  var ids = wrap._gkSelected ? Array.from(wrap._gkSelected) : [];
  if (!ids.length) return;
  var fire = function () {
    wrap.dispatchEvent(
      new CustomEvent("gk:bulkaction", {
        bubbles: true,
        detail: { action: btn.getAttribute("data-gk-bulk-action"), ids: ids, tableId: wrap.dataset.gkTable },
      }),
    );
  };
  var question = btn.getAttribute("data-gk-confirm");
  if (question && GK.confirm) {
    GK.confirm(question, { danger: btn.hasAttribute("data-gk-danger") }).then(function (ok) {
      if (ok) fire();
    });
  } else {
    fire();
  }
});

// 2 ── CSV export. Same rules as Table::csv(): RFC 4180, a byte-order mark, and a leading
// apostrophe on a cell a spreadsheet would read as a formula (not a plain number).
function _gkCsvCell(value, delimiter) {
  var s =
    value === null || value === undefined
      ? ""
      : typeof value === "boolean"
        ? value ? "1" : "0"
        : typeof value === "object"
          ? JSON.stringify(value)
          : String(value);
  if (s !== "" && /^[=+\-@\t\r]/.test(s) && !/^[+-]?\d+(?:[.,]\d+)?$/.test(s)) s = "'" + s;
  if (s.indexOf(delimiter) > -1 || /["\r\n]/.test(s)) s = '"' + s.replace(/"/g, '""') + '"';
  return s;
}

/** The CSV text of a static table, or null when the table holds no rows in the browser. */
GK.tableCsv = function (wrap, delimiter) {
  var data = wrap && wrap._gkData;
  if (!data) return null;
  delimiter = delimiter || ",";
  var rows = wrap._gkMatching || data.rows;
  var keys = Object.keys(data.columns).filter(function (k) { return data.columns[k].export !== false; });
  var lines = [keys.map(function (k) { return _gkCsvCell(data.columns[k].label, delimiter); }).join(delimiter)];
  rows.forEach(function (row) {
    lines.push(keys.map(function (k) { return _gkCsvCell(row[k], delimiter); }).join(delimiter));
  });
  return "\uFEFF" + lines.join("\r\n") + "\r\n";
};

document.addEventListener("click", function (ev) {
  var btn = ev.target.closest ? ev.target.closest("[data-gk-export-csv]") : null;
  if (!btn) return;
  var wrap = btn.closest("[data-gk-table]");
  if (!wrap) return;
  var csv = GK.tableCsv(wrap, btn.getAttribute("data-gk-export-delimiter") || ",");
  if (csv === null) {
    // A server-driven table: the page answers ?gk_export=csv (Table::wantsCsv(), Table::sendCsv()).
    var url = new URL(window.location.href);
    url.searchParams.set("gk_export", "csv");
    window.location.href = url.toString();
    return;
  }
  var link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
  link.download = btn.getAttribute("data-gk-export-csv") || "export.csv";
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
});

// 3 ── conditional fields: `data-gk-show-if='{"field":"kind","equals":"company"}'` on a .gk-field.
// A field that is not shown is hidden AND disabled, so it is neither posted nor validated and
// cannot take the focus; what was disabled before stays disabled when it is shown again.

/** What the controlling field holds: a string, or a list of strings for a group of checkboxes. */
function _gkShowIfValue(form, name) {
  var els = Array.prototype.filter.call(form.elements, function (el) {
    return el.name === name || el.name === name + "[]";
  });
  if (!els.length) return "";
  // A controlling field that is itself hidden holds nothing: a chain of conditions folds away together.
  var wrapper = els[0].closest("[data-gk-show-if]");
  if (wrapper && wrapper.hidden) return "";
  var first = els[0];
  if (first.type === "radio") {
    var on = els.filter(function (el) { return el.checked; })[0];
    return on ? on.value : "";
  }
  if (first.type === "checkbox") {
    var ticked = els.filter(function (el) { return el.checked; }).map(function (el) { return el.value; });
    return els.length > 1 || first.name.slice(-2) === "[]" ? ticked : ticked.length ? ticked[0] : "";
  }
  // A multiselect keeps its choices as a comma list in one hidden input.
  if (first.closest("[data-gk-multiselect]")) return first.value === "" ? [] : first.value.split(",");
  return first.value;
}

function _gkShowIfMatch(cond, value) {
  var vals = Array.isArray(value) ? value : [value];
  if ("equals" in cond) return vals.indexOf(String(cond.equals)) > -1;
  if ("not" in cond) return vals.indexOf(String(cond.not)) === -1;
  if ("in" in cond) return vals.some(function (v) { return cond.in.map(String).indexOf(v) > -1; });
  if ("filled" in cond) return vals.some(function (v) { return v !== ""; }) === !!cond.filled;
  return true;
}

function _gkShowIfApply(form) {
  form.querySelectorAll("[data-gk-show-if]").forEach(function (box) {
    var cond;
    try { cond = JSON.parse(box.getAttribute("data-gk-show-if")); } catch (e) { return; }
    if (!cond || !cond.field) return;
    var show = _gkShowIfMatch(cond, _gkShowIfValue(form, cond.field));
    if (box.hidden === !show) return;
    box.hidden = !show;
    box.querySelectorAll("input, select, textarea, button").forEach(function (c) {
      if (!show) {
        if (c.disabled) c.setAttribute("data-gk-was-disabled", "");
        c.disabled = true;
      } else if (c.hasAttribute("data-gk-was-disabled")) {
        c.removeAttribute("data-gk-was-disabled");
      } else {
        c.disabled = false;
      }
    });
  });
}

function _gkShowIfInit(root) {
  var forms = [];
  if (root.closest && root.closest("form")) forms.push(root.closest("form"));
  if (root.querySelectorAll) forms = forms.concat(Array.prototype.slice.call(root.querySelectorAll("form")));
  forms.forEach(function (form) {
    if (form.querySelector("[data-gk-show-if]")) _gkShowIfApply(form);
  });
}

["input", "change"].forEach(function (type) {
  document.addEventListener(type, function (ev) {
    var form = ev.target.closest ? ev.target.closest("form") : null;
    if (form && form.querySelector("[data-gk-show-if]")) _gkShowIfApply(form);
  });
});

// Forms that arrive later (a modal, a live reload) go through GK.initContent; the first pass is now.
(function () {
  var original = GK.initContent;
  if (typeof original === "function") {
    GK.initContent = function (root) {
      var result = original.apply(GK, arguments);
      _gkShowIfInit(root || document);
      return result;
    };
  }
  _gkReady(function () { _gkShowIfInit(document); });
})();

// === 1.107.0 — sticky head and column (Table::stickyHead / stickyColumn) ===
/*
 * The stylesheet does the sticking. What it cannot know is how wide the cells in front of the
 * sticky column are: the checkbox and the left buttons stick as well, each at the right edge of
 * the one before, and only a measurement says where that is. This block measures the header
 * row and writes the sums on the box as --gk-table-sticky-left-2 and -3: when the page is
 * ready, after every rebuild or reload, and whenever one of those cells changes its width
 * (ResizeObserver — a window resize, a font that arrives late, a column the sort widened).
 *
 * Beside that it marks a box scrolled sideways (data-gk-scrolled-x, which draws the line at the
 * edge of the column), keeps a box's scroll position across a rebuild or an AJAX reload, and
 * puts a setData() table back into its box: GK.table.renderStatic() removes the old table and
 * writes the new one after the toolbar, which left the box empty and the table outside it.
 *
 * Opt-in markup (.gk-table-sticky-head, .gk-table-sticky-col) — a page without it never meets
 * this code. A copy of the file without the block (the PawBot dashboard keeps its own) still
 * renders a sticky table: the head and the first cell stick, the cell after a checkbox at 36px,
 * a cell after the left buttons stays put; a sorted setData() table then scrolls with the page.
 */
(function () {
  if (!window.GK || !GK.table) return;

  var STICKY = ".gk-table-sticky-head, .gk-table-sticky-col";

  function boxOf(wrap) {
    return wrap.querySelector(":scope > .gk-table-sticky-box");
  }

  /** The left edge of each sticky cell after the first, from the widths of the header cells
   *  before it, and the width of the box (the empty state spans it). Returns what the
   *  ResizeObserver watches: the box and the cells whose width counts. */
  function place(box) {
    box.style.setProperty("--gk-table-sticky-w", box.clientWidth + "px");
    var n = parseInt(box.getAttribute("data-gk-sticky-col"), 10) || 0;
    var row = box.querySelector(":scope > .gk-table > thead > tr") ||
      box.querySelector(":scope > .gk-table > tbody > tr:not(.gk-table-group):not(.gk-table-more):not(.gk-empty-row)");
    var cells = row && n > 1 ? Array.prototype.slice.call(row.children, 0, n - 1) : [];
    var left = 0;
    cells.forEach(function (cell, i) {
      // The rendered width, fractions included: offsetWidth rounds, and half a pixel per
      // cell adds up to a gap the scrolled content shows through.
      left += cell.getBoundingClientRect().width;
      box.style.setProperty("--gk-table-sticky-left-" + (i + 2), left + "px");
    });
    return [box].concat(cells);
  }

  /** The line at the edge of the column belongs to a box that is scrolled sideways. */
  function mark(box) {
    if (box.scrollLeft !== 0) box.setAttribute("data-gk-scrolled-x", "");
    else box.removeAttribute("data-gk-scrolled-x");
  }

  /** (Re)bind one wrap: idempotent, run again whenever its children change. */
  function bind(wrap) {
    var st = wrap._gkSticky || (wrap._gkSticky = { x: 0, y: 0, box: null });
    var box = boxOf(wrap);
    if (box !== st.box) {
      // A box this wrap has not had before: the first run, or the one an AJAX reload wrote.
      // It takes over the scroll position of the one it replaces.
      st.box = box;
      if (box) {
        box.addEventListener("scroll", function () {
          if (st.box !== box) return;
          st.x = box.scrollLeft;
          st.y = box.scrollTop;
          mark(box);
        }, { passive: true });
        box.scrollLeft = st.x;
        box.scrollTop = st.y;
      }
      if (!st.mo && window.MutationObserver) {
        // The wrap's children change on a reload (a new box) and on a rebuild of a setData()
        // table (a new table, the box moved to it) — then the cells to measure are new.
        st.mo = new MutationObserver(function () { bind(wrap); });
        st.mo.observe(wrap, { childList: true });
      }
    }
    if (st.ro) st.ro.disconnect();
    st.ro = null;
    if (!box) return;
    mark(box);
    var watched = place(box);
    if (window.ResizeObserver) {
      st.ro = new ResizeObserver(function () { place(box); });
      watched.forEach(function (el) { st.ro.observe(el); });
    }
  }

  /** Every sticky table with a box under root. A head that sticks to the page needs no script. */
  function init(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var wraps = Array.prototype.slice.call(scope.querySelectorAll(STICKY));
    if (scope.matches && scope.matches(STICKY)) wraps.unshift(scope);
    wraps.forEach(function (wrap) {
      if (boxOf(wrap)) bind(wrap);
    });
  }

  // A setData() table rebuilt in the browser: back into its box, the box where the table now
  // stands, the scroll position as it was. Wrapped, not changed — every rebuild goes through
  // this.renderStatic(), so it goes through here. The wrap's observer measures the new head.
  var render = GK.table.renderStatic;
  if (typeof render === "function") {
    GK.table.renderStatic = function (wrap) {
      var box = wrap && wrap.querySelector ? boxOf(wrap) : null;
      var x = box ? box.scrollLeft : 0;
      var y = box ? box.scrollTop : 0;
      var result = render.apply(this, arguments);
      var table = box ? wrap.querySelector(":scope > .gk-table") : null;
      if (table) {
        table.parentNode.insertBefore(box, table);
        box.appendChild(table);
        box.scrollLeft = x;
        box.scrollTop = y;
      }
      return result;
    };
  }

  // Tables that arrive later (a modal, a live reload) go through GK.initContent.
  var original = GK.initContent;
  if (typeof original === "function") {
    GK.initContent = function (root) {
      var result = original.apply(GK, arguments);
      init(root || document);
      return result;
    };
  }
  _gkReady(function () { init(document); });
})();
// === 1.108.0 — column toggle (Table::columnToggle) ===
/* ══════════════════════════════════════════════════════════════════════════
 * The menu of Table::columnToggle(): a native popover in the toolbar with one checkbox per column.
 *
 * The hiding itself is CSS (css/gridkit.css, "1.108.0 — column toggle"): an unticked checkbox hides
 * the cells at its place in a row, so a sort, a search, a page or an AJAX reload, which write the
 * rows anew, need nobody to hide anything again. This block does what a stylesheet cannot:
 *
 *   1. remember the choice per table id — localStorage "gk-cols-<id>", only what differs from the
 *      page's own default, so a column added later, or a default changed later, still reaches a
 *      returning user; storage that is missing or throws remembers nothing and breaks nothing;
 *   2. aria-expanded on the button, the menu under its button, the focus into it and back, the
 *      arrow keys, Escape claimed (a modal underneath stays open), the menu closed when the focus
 *      leaves it — opening, light dismiss and the Escape of a page without this block are the
 *      browser's own;
 *   3. after every redraw of the rows (renderStatic, an AJAX reload, rows put in by other code):
 *      mark the hidden cells .gk-col-hidden — the title of a card passes over them, and a column
 *      past the twentieth place is hidden by the mark alone — and narrow what spans the row: group
 *      headings, the empty state, the footer;
 *   4. keep one column: the last one shown cannot be unticked;
 *   5. the CSV of a setData() table leaves hidden columns out; the export of a server table names
 *      them (?gk_hidden=…) and Table::sendCsv() leaves them out.
 *
 * Without this block (the PawBot dashboard keeps its own copy of the file) the menu still opens
 * and its checkboxes still hide and show columns; nothing is remembered, and a group heading keeps
 * its full colspan, which a browser draws without a gap.
 * ══════════════════════════════════════════════════════════════════════════ */
(function () {
  var STORE = "gk-cols-";

  function menuOf(wrap) {
    return wrap.querySelector(":scope > .gk-toolbar .gk-col-toggle-menu");
  }

  /** The checkboxes that can hide a column: 'toggle' => false and the rowLink() column have no place. */
  function boxesOf(wrap) {
    var menu = menuOf(wrap);
    return menu ? Array.prototype.slice.call(menu.querySelectorAll("input[data-gk-col-pos]")) : [];
  }

  function buttonOf(menu) {
    return menu.parentNode ? menu.parentNode.querySelector("[popovertarget]") : null;
  }

  /** The column menu that is open, or null — also where a browser does not know :popover-open. */
  function openMenu() {
    try {
      return document.querySelector(".gk-col-toggle-menu:popover-open");
    } catch (e) {
      return null;
    }
  }

  function close(menu, focusButton) {
    try {
      menu.hidePopover();
    } catch (e) {}
    var btn = buttonOf(menu);
    if (focusButton && btn) btn.focus();
  }

  function stored(id) {
    try {
      var v = JSON.parse(localStorage.getItem(STORE + id) || "null");
      return v && typeof v === "object" && !Array.isArray(v) ? v : {};
    } catch (e) {
      return {};
    }
  }

  function store(wrap) {
    var diff = {};
    var n = 0;
    boxesOf(wrap).forEach(function (cb) {
      if (cb.checked !== cb.defaultChecked) {
        diff[cb.getAttribute("data-gk-col")] = cb.checked;
        n++;
      }
    });
    try {
      if (n) localStorage.setItem(STORE + wrap.dataset.gkTable, JSON.stringify(diff));
      else localStorage.removeItem(STORE + wrap.dataset.gkTable);
    } catch (e) {}
  }

  /** The keys of the columns the user has hidden. */
  function hiddenKeys(wrap) {
    return boxesOf(wrap)
      .filter(function (cb) { return !cb.checked; })
      .map(function (cb) { return cb.getAttribute("data-gk-col"); });
  }

  /**
   * At least one column stays. Without a column that cannot be hidden, the last one shown cannot be
   * unticked — and when nothing would show at all (every column 'hidden', or a stored choice that
   * says so), the first one comes back.
   */
  function keepOne(wrap) {
    var menu = menuOf(wrap);
    if (!menu) return;
    var fixed = menu.querySelectorAll("input[data-gk-col]:not([data-gk-col-pos])").length;
    var boxes = boxesOf(wrap);
    if (!fixed && boxes.length && !boxes.some(function (cb) { return cb.checked; })) boxes[0].checked = true;
    var on = boxes.filter(function (cb) { return cb.checked; });
    boxes.forEach(function (cb) {
      var last = !fixed && on.length === 1 && cb === on[0];
      if (cb.disabled !== last) cb.disabled = last;
    });
  }

  /**
   * Row by row, by the places each cell takes (a colspan takes several): a cell whose every place
   * is hidden gets .gk-col-hidden; a cell spanning hidden places loses them from its colspan, which
   * it keeps in data-gk-span for when they come back. Rows written by renderStatic() or by the
   * server carry the full colspan again, and are narrowed again. The header row reads its places
   * from data-gk-col-head: until 1.110.1 it wrote the cell over the left buttons at its end, so
   * its places differed from a row's; since then the two numbers are the same.
   */
  function apply(wrap) {
    // In the box of stickyColumn() / stickyHead('…') (1.107.0) when the table has one.
    var table = wrap.querySelector(":scope > .gk-table, :scope > .gk-table-sticky-box > .gk-table");
    if (!table) return;
    var hiddenCells = {};
    var hiddenHeads = {};
    boxesOf(wrap).forEach(function (cb) {
      if (cb.checked) return;
      hiddenCells[parseInt(cb.getAttribute("data-gk-col-pos"), 10)] = true;
      hiddenHeads[parseInt(cb.getAttribute("data-gk-col-head"), 10)] = true;
    });
    Array.prototype.forEach.call(table.rows, function (tr) {
      var hidden = tr.parentNode === table.tHead ? hiddenHeads : hiddenCells;
      var pos = 1;
      Array.prototype.forEach.call(tr.cells, function (cell) {
        var span = parseInt(cell.getAttribute("data-gk-span"), 10) || cell.colSpan || 1;
        var gone = 0;
        for (var p = pos; p < pos + span; p++) if (hidden[p]) gone++;
        pos += span;
        var off = gone === span;
        if (cell.classList.contains("gk-col-hidden") !== off) cell.classList.toggle("gk-col-hidden", off);
        if (span > 1 && !off) {
          if (gone && !cell.hasAttribute("data-gk-span")) cell.setAttribute("data-gk-span", String(span));
          if (cell.colSpan !== span - gone) cell.colSpan = span - gone;
        }
      });
    });
  }

  /**
   * Under its button, from the button's left edge, moved left where it would leave the window, and
   * above the button where there is more room there. A popover stands where the browser puts it —
   * the middle of the window — until it is told otherwise.
   */
  function place(menu) {
    var btn = buttonOf(menu);
    if (!btn) return;
    var r = btn.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var vh = document.documentElement.clientHeight;
    var s = menu.style;
    s.position = "fixed";
    s.inset = "auto";
    s.margin = "0";
    // Measured only now, its old place cleared: where it stood before, the window's edge squeezed
    // it, and a squeezed menu measured narrow would stay squeezed instead of moving left.
    var w = menu.offsetWidth || 240; // 0 before it is shown (beforetoggle): about its least width
    var h = menu.scrollHeight || 0;
    var below = vh - r.bottom - 12;
    var above = r.top - 12;
    s.left = Math.round(Math.max(8, Math.min(r.left, vw - w - 8))) + "px";
    if (h > below && above > below) {
      s.bottom = Math.round(vh - r.top + 4) + "px";
      s.maxHeight = Math.round(Math.max(120, Math.min(480, above))) + "px";
    } else {
      s.top = Math.round(r.bottom + 4) + "px";
      s.maxHeight = Math.round(Math.max(120, Math.min(480, below))) + "px";
    }
  }

  function follow() {
    var menu = openMenu();
    if (menu) place(menu);
  }

  function bind(wrap) {
    if (wrap._gkColumns) return;
    var menu = menuOf(wrap);
    if (!menu) return;
    wrap._gkColumns = true;
    var choice = stored(wrap.dataset.gkTable || "");
    boxesOf(wrap).forEach(function (cb) {
      var key = cb.getAttribute("data-gk-col");
      // The page's default unless a choice is stored — also over the state a browser restores
      // into form controls on a reload, which would otherwise be a third source of truth.
      cb.checked = typeof choice[key] === "boolean" ? choice[key] : cb.defaultChecked;
    });
    var btn = buttonOf(menu);
    if (btn) btn.setAttribute("aria-expanded", "false");
    keepOne(wrap);
    apply(wrap);
    // Every redraw of the rows: renderStatic() replaces the <table>, an AJAX reload everything but
    // the toolbar, a page's own code a <tbody> or a row. The callback runs before the next paint.
    if (typeof MutationObserver === "function") {
      new MutationObserver(function (records) {
        for (var i = 0; i < records.length; i++) {
          for (var j = 0; j < records[i].addedNodes.length; j++) {
            if (/^(TABLE|THEAD|TBODY|TFOOT|TR|TD|TH)$/.test(records[i].addedNodes[j].nodeName)) {
              apply(wrap);
              return;
            }
          }
        }
      }).observe(wrap, { childList: true, subtree: true });
    }
  }

  function init(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope.matches && scope.matches("[data-gk-table]")) bind(scope);
    scope.querySelectorAll("[data-gk-table]").forEach(bind);
  }

  document.addEventListener("change", function (e) {
    var cb = e.target;
    if (!cb.matches || !cb.matches(".gk-col-toggle-menu input[data-gk-col-pos]")) return;
    var wrap = cb.closest("[data-gk-table]");
    if (!wrap) return;
    keepOne(wrap);
    store(wrap);
    apply(wrap);
  });

  // beforetoggle and toggle do not bubble: heard on the way down. beforetoggle comes at once, for
  // every way a popover opens or closes (the button, Escape, a click elsewhere, hidePopover()), so
  // aria-expanded never lags; toggle comes a task later, once the menu has a size and can take focus.
  document.addEventListener("beforetoggle", function (e) {
    var menu = e.target;
    if (!menu.classList || !menu.classList.contains("gk-col-toggle-menu")) return;
    var open = e.newState === "open";
    var btn = buttonOf(menu);
    if (btn) btn.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      place(menu);
      window.addEventListener("scroll", follow, true);
      window.addEventListener("resize", follow);
    } else {
      window.removeEventListener("scroll", follow, true);
      window.removeEventListener("resize", follow);
    }
  }, true);
  document.addEventListener("toggle", function (e) {
    var menu = e.target;
    if (e.newState !== "open" || !menu.classList || !menu.classList.contains("gk-col-toggle-menu")) return;
    place(menu); // its own size is known now
    var first = menu.querySelector("input:not(:disabled)");
    if (first && !menu.contains(document.activeElement)) first.focus();
  }, true);

  // Escape: closed here and claimed, so GK.modal (which asks defaultPrevented) keeps a modal the
  // table stands in. The focus goes back to the button.
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape" || e.defaultPrevented) return;
    var menu = openMenu();
    if (!menu) return;
    e.preventDefault();
    close(menu, true);
  }, true);

  // Up and down (round the ends), Home and End move between the checkboxes that can be ticked.
  var MOVES = ["Home", "ArrowUp", "ArrowDown", "End"];
  document.addEventListener("keydown", function (e) {
    var menu = e.target.closest ? e.target.closest(".gk-col-toggle-menu") : null;
    var move = MOVES.indexOf(e.key);
    if (!menu || move < 0) return;
    var items = Array.prototype.slice.call(menu.querySelectorAll("input:not(:disabled)"));
    if (!items.length) return;
    var i = items.indexOf(e.target);
    var n = items.length;
    var next = move === 0 ? 0 : move === 3 ? n - 1 : (i + (move === 2 ? 1 : -1) + n) % n;
    e.preventDefault();
    items[next].focus();
  });

  // Tab out of the menu closes it: a popover stays open where the focus has gone, over the page.
  document.addEventListener("focusout", function (e) {
    var box = e.target.closest ? e.target.closest(".gk-col-toggle") : null;
    var to = e.relatedTarget;
    if (!box || !to || box.contains(to)) return;
    var menu = box.querySelector(".gk-col-toggle-menu");
    if (menu && menu === openMenu()) close(menu, false);
  });

  // The CSV holds what the table shows. A setData() table builds it from its columns (1.105.0);
  // the hidden ones are taken out of the copy handed over.
  if (typeof GK.tableCsv === "function") {
    var buildCsv = GK.tableCsv;
    GK.tableCsv = function (wrap, delimiter) {
      var data = wrap && wrap._gkData;
      var off = data ? hiddenKeys(wrap) : [];
      if (!off.length) return buildCsv.apply(this, arguments);
      var columns = {};
      Object.keys(data.columns).forEach(function (k) {
        if (off.indexOf(k) < 0) columns[k] = data.columns[k];
      });
      return buildCsv.call(this, { _gkData: Object.assign({}, data, { columns: columns }), _gkMatching: wrap._gkMatching }, delimiter);
    };
  }

  // A server table has no rows here: its button reloads the page with ?gk_export=csv, and the
  // columns the user hid go along as ?gk_hidden=…, which Table::sendCsv() leaves out. Heard on the
  // way down, before the handler of 1.105.0 — and only when there is a column to name.
  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest ? ev.target.closest("[data-gk-export-csv]") : null;
    var wrap = btn ? btn.closest("[data-gk-table]") : null;
    if (!wrap || wrap._gkData) return;
    var off = hiddenKeys(wrap);
    if (!off.length) return;
    ev.preventDefault();
    ev.stopImmediatePropagation();
    var url = new URL(window.location.href);
    url.searchParams.set("gk_export", "csv");
    url.searchParams.set("gk_hidden", off.join(","));
    window.location.href = url.toString();
  }, true);

  // Tables come through GK.table.init: GK.init() calls it when the document is ready, and
  // GK.initContent() for what arrives later — a modal, a live reload, AJAX navigation. The pass
  // below is for a script that arrives after the document is ready: GK.init() has then run inside
  // the main block, before this one wrapped anything.
  if (GK.table && typeof GK.table.init === "function") {
    var initTables = GK.table.init;
    GK.table.init = function (root) {
      var result = initTables.apply(this, arguments);
      init(root);
      return result;
    };
  }
  init(document);
})();
// === 1.109.0 — density toggle (Table::densityToggle) ===
/*
 * The control Table::densityToggle() writes into the toolbar: [data-gk-density-toggle], a group of
 * [data-gk-set-density] buttons for the steps konsole, normal and weit. The step goes on the
 * table's wrap as data-gk-density, which css/gridkit.css reads (block "1.109.0 — density toggle").
 * The wrap outlives every redraw: renderStatic() after a sort, a search or a page, and the AJAX
 * reload of a server table, replace what stands below the toolbar and leave the wrap and the
 * toolbar alone, so nothing here has to run again after them.
 *
 * The step a table starts at: the one stored for its id, else the page's own (Theme::density()
 * on <html> or <body>), else normal. A stored step is a difference from the page: choosing the
 * page's own step removes it, so the table follows the page again when the page changes. On a
 * page without a density of its own, Normal leaves the wrap without the attribute, and the table
 * is exactly the table without the toggle.
 *
 * The control is rendered hidden and shown here: a copy of this file without the block (the
 * PawBot dashboard keeps its own) leaves it hidden and the table as it was. localStorage can be
 * missing or throw (a private window, blocked site data), so every access is wrapped and the step
 * then holds for this page view only. The key is "gk-density-<table id>" in the namespace of
 * GK.theme (window.GK_THEME_SCOPE, GK.theme.init({ scope })): the next person at a shared
 * computer does not inherit it.
 */
(function () {
  var STEPS = ["konsole", "normal", "weit"];

  function storageKey(id) {
    var name = "gk-density-" + id;
    return GK.theme && typeof GK.theme._key === "function" ? GK.theme._key(name) : name;
  }

  function storedStep(id) {
    try {
      var step = window.localStorage.getItem(storageKey(id));
      return STEPS.indexOf(step) > -1 ? step : null;
    } catch (e) {
      return null;
    }
  }

  function storeStep(id, step) {
    try {
      if (step === null) window.localStorage.removeItem(storageKey(id));
      else window.localStorage.setItem(storageKey(id), step);
    } catch (e) {}
  }

  /** The page's step: the nearest data-gk-density above the wrap, where Theme::density() puts it. */
  function pageStep(wrap) {
    var host = wrap.parentElement ? wrap.parentElement.closest("[data-gk-density]") : null;
    var step = host ? host.getAttribute("data-gk-density") : "";
    return step === "konsole" || step === "weit" ? step : "normal";
  }

  function applyStep(wrap, step) {
    if (step === "normal" && pageStep(wrap) === "normal") wrap.removeAttribute("data-gk-density");
    else wrap.setAttribute("data-gk-density", step);
    wrap.querySelectorAll("[data-gk-density-toggle] [data-gk-set-density]").forEach(function (btn) {
      btn.setAttribute("aria-pressed", btn.getAttribute("data-gk-set-density") === step ? "true" : "false");
    });
  }

  function initDensity(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll("[data-gk-density-toggle]").forEach(function (group) {
      var wrap = group.closest("[data-gk-table]");
      if (!wrap || group._gkDensityBound) return;
      group._gkDensityBound = true;
      applyStep(wrap, storedStep(wrap.getAttribute("data-gk-table")) || pageStep(wrap));
      group.hidden = false;
    });
  }

  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest ? ev.target.closest("[data-gk-set-density]") : null;
    var group = btn ? btn.closest("[data-gk-density-toggle]") : null;
    var wrap = group ? group.closest("[data-gk-table]") : null;
    if (!wrap) return;
    var step = btn.getAttribute("data-gk-set-density");
    if (STEPS.indexOf(step) < 0) return;
    storeStep(wrap.getAttribute("data-gk-table"), step === pageStep(wrap) ? null : step);
    applyStep(wrap, step);
  });

  // Tables that arrive later — GK.initContent (AJAX navigation, a modal, a live table) and the
  // pager of [data-gk-ajax-table] — all go through GK.table.init, so this rides along with it.
  // The first pass is _gkReady: GK.init() has already run when the document was ready before
  // this file was.
  if (GK.table && typeof GK.table.init === "function") {
    var tableInit = GK.table.init;
    GK.table.init = function (root) {
      var result = tableInit.apply(this, arguments);
      initDensity(root);
      return result;
    };
  }
  _gkReady(function () { initDensity(document); });
})();
// === 1.110.0 — inline editing (Table::editable) ===
/*
 * A cell of a column with 'edit' (Table::editable()) turns into a field on a click, or on Enter or F2
 * while it has the focus. Enter, Tab and leaving the field save; Escape puts the old value back.
 * Saving fires gk:celledit on the table: cancelable, and answerable with detail.respondWith(). When
 * the table names a URL, GridKit then POSTs { id, column, value } as JSON there.
 *
 * Everything hangs off markup PHP writes (data-gk-editable on the wrap, data-gk-edit on a cell) and
 * the listeners are delegated, so a reloaded table needs no binding. A copy of this file without the
 * block (the PawBot dashboard keeps its own) shows the same table read-only: the cells take the focus,
 * nothing opens, and the first sort drops the attributes.
 *
 * Two functions are wrapped, none is changed:
 *   GK.table.renderStatic  after a rebuild of a setData() table the rows get data-gk-row-id and the
 *                          editable cells their attributes, exactly as Table::renderRow() and
 *                          Table::editAttrs() write them; before it, a field still open is saved.
 *   GK.table.reload        before the AJAX reload of a server table, a field still open is saved.
 * A saved value is formatted by renderStatic itself, run on a table of one cell off the page: one
 * implementation of every column format, not a second one here.
 */
(function () {
  "use strict";
  if (!window.GK || !GK.table || typeof GK.table.renderStatic !== "function") return;

  function _t(key, fallback) {
    var lang = window.GK_LANG || {};
    return lang[key] || fallback;
  }

  /** PHP's (string): true is "1", false and null are "" — Table::rawText() is the other side. */
  function asText(v) {
    if (v === true) return "1";
    if (v === false || v === null || v === undefined || typeof v === "object") return "";
    return String(v);
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  /** What Table::editConfig() wrote on the wrap: url, key, hint, columns (and headers, labelColors). */
  function config(wrap) {
    var src = wrap && wrap.getAttribute ? wrap.getAttribute("data-gk-editable") : null;
    if (!src) return null;
    if (wrap._gkEditSrc !== src) {
      wrap._gkEditSrc = src;
      try { wrap._gkEditCfg = JSON.parse(src); } catch (e) { wrap._gkEditCfg = null; }
    }
    return wrap._gkEditCfg && wrap._gkEditCfg.columns ? wrap._gkEditCfg : null;
  }

  /** A number column's value is a number, or null when it is empty; every other column's is text. */
  function valueOf(type, raw) {
    var s = asText(raw);
    if (type !== "number") return s;
    if (s.trim() === "") return null;
    var n = Number(s);
    return isFinite(n) ? n : null;
  }

  function isStatic(wrap) {
    return wrap.hasAttribute("data-gk-static") && !!wrap._gkData && Array.isArray(wrap._gkData.rows);
  }

  /** The rows of a setData() table that stand for this record — normally one. */
  function rowsOf(wrap, cfg, id) {
    return isStatic(wrap) ? wrap._gkData.rows.filter(function (r) { return r && asText(r[cfg.key]) === id; }) : [];
  }

  /** The cell of a record and column, wherever a rebuild or a reload has put it by now. */
  function cellOf(wrap, id, column) {
    var rows = wrap.querySelectorAll(".gk-table > tbody > tr[data-gk-row-id]");
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].getAttribute("data-gk-row-id") !== id) continue;
      var cells = rows[i].querySelectorAll(":scope > td[data-gk-edit]");
      for (var j = 0; j < cells.length; j++) {
        if (cells[j].getAttribute("data-gk-edit") === column) return cells[j];
      }
    }
    return null;
  }

  function pendingKey(id, column) {
    return id + "\u0000" + column;
  }

  function focusCell(td) {
    if (!td || !td.isConnected) return;
    try { td.focus({ preventScroll: true }); } catch (e) { td.focus(); }
  }

  /** Said in the table's own live region (role=status), where a sort says what it did. */
  function announce(wrap, msg) {
    var el = wrap.querySelector("[data-gk-table-status]");
    // The same words twice in a row are not read twice: a no-break space makes them new.
    if (el) el.textContent = el.textContent === msg ? msg + " " : msg;
  }

  var renderStatic = GK.table.renderStatic;

  /*
   * The markup of one cell, written by renderStatic itself: a table of one row and one column, built
   * off the page. Whatever a format does (a currency, a label and its colour, a number's em dash)
   * happens exactly as on every sort. null when it cannot be had.
   */
  function formatted(wrap, cfg, column, row) {
    var data = isStatic(wrap) ? wrap._gkData : null;
    var col = data ? data.columns && data.columns[column] : cfg.columns[column] && cfg.columns[column].column;
    if (!col) return null;
    var one = {};
    one[column] = col;
    var scratch = document.createElement("div");
    scratch._gkEditScratch = true; // a wrapper of renderStatic can tell this table from a real one
    scratch._gkData = {
      rows: [row], columns: one, perPage: 0, search: [], buttons: {}, footer: [], loadTimeMs: null,
      caption: "", groupBy: null, rowLink: null, nowrap: false,
      labelColors: (data ? data.labelColors : cfg.labelColors) || null,
    };
    try {
      renderStatic.call(GK.table, scratch);
    } catch (e) {
      return null;
    }
    var td = scratch.querySelector("tbody td[data-label]");
    return td ? td.innerHTML : null;
  }

  /** The second line ('sub') of a server table's cell: the browser does not have its field. */
  function subLine(html) {
    var box = document.createElement("div");
    box.innerHTML = html;
    var sub = box.querySelector(".gk-cell-sub");
    return sub ? sub.outerHTML : "";
  }

  /** The next (step 1) or previous (-1) editable cell of the table that is shown and not saving. */
  function neighbour(wrap, td, step) {
    var cells = Array.prototype.slice.call(wrap.querySelectorAll(".gk-table > tbody > tr > td[data-gk-edit]"));
    for (var i = cells.indexOf(td) + step; i >= 0 && i < cells.length; i += step) {
      if (cells[i].offsetParent !== null && cells[i].getAttribute("aria-busy") !== "true") return cells[i];
    }
    return null;
  }

  /** Turn a cell into its field. false when it cannot be edited now (saving, no id, no raw value). */
  function open(td) {
    var wrap = td.closest("[data-gk-table]");
    var cfg = config(wrap);
    var column = td.getAttribute("data-gk-edit");
    var spec = cfg ? cfg.columns[column] : null;
    var id = td.parentElement ? td.parentElement.getAttribute("data-gk-row-id") : null;
    if (!spec || !id || td._gkEdit || td.getAttribute("aria-busy") === "true") return false;

    // The value as stored, not as shown: a currency cell reads "€1,200.50", its field "1200.5".
    var raw;
    if (isStatic(wrap)) {
      var rows = rowsOf(wrap, cfg, id);
      if (!rows.length) return false;
      raw = rows[0][column];
    } else {
      if (!td.hasAttribute("data-gk-raw")) return false;
      raw = td.getAttribute("data-gk-raw");
    }

    var text = asText(raw);
    var control;
    if (spec.type === "select") {
      control = document.createElement("select");
      var options = (spec.options || []).slice();
      // A stored value that is not on the list stays choosable: leaving the field must not change it.
      if (!options.some(function (o) { return String(o[0]) === text; })) options.unshift([text, text]);
      options.forEach(function (o) {
        var option = document.createElement("option");
        option.value = String(o[0]);
        option.textContent = String(o[1]);
        control.appendChild(option);
      });
    } else {
      control = document.createElement("input");
      control.type = spec.type === "number" ? "number" : "text";
      if (spec.type === "number") {
        control.step = "any";
        control.inputMode = "decimal";
      }
      control.autocomplete = "off";
    }
    // .value, never markup: whatever the record holds is text in the field.
    control.value = text;
    control.setAttribute("aria-label", td.getAttribute("data-label") || column);

    var s = {
      wrap: wrap, cfg: cfg, td: td, id: id, column: column, type: spec.type, control: control,
      oldHtml: td.innerHTML, oldValue: valueOf(spec.type, raw), done: false, keyed: false,
    };
    td._gkEdit = s;
    td.setAttribute("data-gk-editing", "");
    // The value stays under the field, hidden: the cell keeps its width and height, so opening a
    // field does not move a column (the field lies over it — see the CSS block of 1.110.0).
    var was = document.createElement("span");
    was.setAttribute("data-gk-edit-was", "");
    while (td.firstChild) was.appendChild(td.firstChild);
    td.appendChild(was);
    td.appendChild(control);
    bind(s);
    control.focus();
    if (typeof control.select === "function") control.select();
    return true;
  }

  function bind(s) {
    var c = s.control;
    c.addEventListener("keydown", function (e) {
      if (e.isComposing) return;
      if (e.key === "Enter") {
        // Claimed, so that a form around the table is not submitted.
        e.preventDefault();
        e.stopPropagation();
        finish(s, "commit", "enter");
      } else if (e.key === "Escape") {
        // Claimed and stopped here: a modal or a sheet around the table must not close with it.
        e.preventDefault();
        e.stopPropagation();
        finish(s, "cancel", "escape");
      } else if (e.key === "Tab") {
        e.preventDefault();
        finish(s, "commit", e.shiftKey ? "prev" : "next");
      } else {
        s.keyed = true;
      }
    });
    c.addEventListener("blur", function () {
      // The window lost the focus, not the field: it is still open when the person comes back.
      if (!document.hasFocus()) return;
      finish(s, "commit", "blur");
    });
    if (s.type === "select") {
      // A choice made with the pointer is the decision; one made with the arrow keys is browsing
      // until Enter, Tab or leaving the field (an arrow key changes a closed select at once).
      c.addEventListener("change", function () {
        if (!s.keyed) finish(s, "commit", "pick");
      });
    }
  }

  /** Close a field: "commit" saves what it holds, "cancel" puts the old value back. */
  function finish(s, mode, how) {
    if (s.done) return;
    var td = s.td;
    var c = s.control;
    var value = null;
    if (mode === "commit") {
      if (s.type === "number" && c.validity && c.validity.badInput) {
        if (GK.toast) GK.toast.error(_t("edit_number", "Enter a number."));
        if (how === "enter" || how === "next" || how === "prev") {
          c.setAttribute("aria-invalid", "true");
          return; // the field stays open with what was typed
        }
        mode = "cancel";
      } else {
        value = valueOf(s.type, c.value);
      }
    }
    s.done = true;
    td._gkEdit = null;
    td.removeAttribute("data-gk-editing");
    if (mode === "cancel" || value === s.oldValue) {
      // Nothing changed: the cell gets its own markup back, nothing is sent.
      td.innerHTML = s.oldHtml;
      after(s, how);
      return;
    }
    // What was chosen, as text, while it is saved; the formatted value follows the answer.
    td.textContent = s.type === "select" && c.selectedIndex > -1 ? c.options[c.selectedIndex].textContent : c.value;
    after(s, how);
    save(s, value);
  }

  /** Where the focus goes once a field is closed: back to its cell, or on to the next field. */
  function after(s, how) {
    if ((how === "next" || how === "prev") && s.td.isConnected) {
      var next = neighbour(s.wrap, s.td, how === "next" ? 1 : -1);
      if (next && open(next)) return;
    }
    if (how === "enter" || how === "escape" || how === "pick" || how === "next" || how === "prev") focusCell(s.td);
  }

  function save(s, value) {
    var wrap = s.wrap;
    var pending = wrap._gkEditPending || (wrap._gkEditPending = {});
    // Busy until the answer: the cell cannot be opened again, not even after a rebuild (markRows).
    pending[pendingKey(s.id, s.column)] = true;
    s.td.setAttribute("aria-busy", "true");

    var answer = null;
    var detail = {
      tableId: wrap.getAttribute("data-gk-table") || "",
      id: s.id,
      column: s.column,
      value: value,
      oldValue: s.oldValue,
      respondWith: function (result) {
        answer = Promise.resolve(result);
      },
    };
    var go = wrap.dispatchEvent(new CustomEvent("gk:celledit", { bubbles: true, cancelable: true, detail: detail }));
    detail.respondWith = function () {
      if (window.console) console.warn("GridKit: respondWith() after gk:celledit was handled does nothing — call it in the listener.");
    };
    if (!go) {
      failed(s, null, true);
      return;
    }
    if (!answer) answer = s.cfg.url ? post(s.cfg, s.id, s.column, value) : Promise.resolve({ ok: true });
    answer
      .then(normalize, function () { return { ok: false, error: null }; })
      .then(function (res) {
        if (res.ok) saved(s, value, res);
        else failed(s, res.error, false);
      })
      .catch(function (err) {
        failed(s, null, false);
        if (window.console) console.error("GridKit: showing the saved cell failed", err);
      });
  }

  function post(cfg, id, column, value) {
    var headers = { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    Object.keys(cfg.headers || {}).forEach(function (h) { headers[h] = String(cfg.headers[h]); });
    return fetch(cfg.url, {
      method: "POST",
      credentials: "same-origin",
      headers: headers,
      body: JSON.stringify({ id: id, column: column, value: value }),
    }).then(function (r) {
      return r.text().then(function (body) {
        var json = null;
        try { json = JSON.parse(body); } catch (e) { /* not JSON: a failure below */ }
        // A refusal with a reason (422 and { ok: false, error }) keeps its reason; any other status fails.
        return r.ok ? json : { ok: false, error: json && typeof json === "object" ? json.error : null };
      });
    });
  }

  /** The answer, from the server or from respondWith(): { ok: true, value?, display? } or a failure. */
  function normalize(a) {
    if (a === true) a = { ok: true };
    if (a && typeof a === "object" && a.ok === true) {
      return {
        ok: true,
        hasValue: Object.prototype.hasOwnProperty.call(a, "value"),
        value: a.value,
        display: typeof a.display === "string" ? a.display : null,
      };
    }
    var why = a && typeof a === "object" && typeof a.error === "string" ? a.error.trim() : "";
    return { ok: false, error: why || null };
  }

  function saved(s, sent, res) {
    var wrap = s.wrap;
    var value = res.hasValue ? res.value : sent;
    delete wrap._gkEditPending[pendingKey(s.id, s.column)];
    var td = cellOf(wrap, s.id, s.column);
    var html = null;
    if (isStatic(wrap)) {
      // The data block is the table: a later sort, search or page shows the new value. The row
      // stays where it is until then — a row that jumps away while one works on it is lost.
      var rows = rowsOf(wrap, s.cfg, s.id);
      rows.forEach(function (r) { r[s.column] = value; });
      if (rows.length) html = formatted(wrap, s.cfg, s.column, rows[0]);
    } else if (td) {
      // A server table: the server owns the value, a reload shows what it stored. Until then its
      // `display`, or the value formatted the way the column does.
      td.setAttribute("data-gk-raw", asText(value));
      if (res.display !== null) {
        html = res.display;
      } else {
        var row = {};
        row[s.column] = value;
        html = formatted(wrap, s.cfg, s.column, row);
        if (html !== null) html += subLine(s.oldHtml);
      }
    }
    if (td) {
      td.removeAttribute("aria-busy");
      if (!td._gkEdit) td.innerHTML = html !== null ? html : esc(asText(value));
    }
    announce(wrap, _t("edit_saved", "Saved."));
  }

  function failed(s, why, quiet) {
    var wrap = s.wrap;
    if (wrap._gkEditPending) delete wrap._gkEditPending[pendingKey(s.id, s.column)];
    var td = cellOf(wrap, s.id, s.column);
    if (td) {
      td.removeAttribute("aria-busy");
      // The same cell still shows what was typed. A rebuilt or reloaded one shows the stored value.
      if (td === s.td && !td._gkEdit) td.innerHTML = s.oldHtml;
    }
    // Text, never markup: GK.toast writes the server's words with textContent.
    if (!quiet && GK.toast) GK.toast.error(why || _t("edit_error", "The change could not be saved."), 6000);
  }

  /** Before rows are redrawn: an open field is saved, the cell that has the focus is remembered. */
  function beforeRedraw(wrap) {
    var active = document.activeElement;
    var td = active && active.closest ? active.closest("td[data-gk-edit]") : null;
    var focus = td && wrap.contains(td) && td.parentElement
      ? { id: td.parentElement.getAttribute("data-gk-row-id"), column: td.getAttribute("data-gk-edit") }
      : null;
    wrap.querySelectorAll("td[data-gk-editing]").forEach(function (c) {
      if (c._gkEdit) finish(c._gkEdit, "commit", "rebuild");
    });
    return focus;
  }

  /*
   * After renderStatic: what Table::renderRow() and Table::editAttrs() write on the server, in the
   * same order, so a rebuilt table is the table PHP sent. The rows on the page are the rows the
   * search and the filters left (_gkMatching), cut to the page; a row's cells of the columns are the
   * ones with data-label, in the order of the data block's columns.
   */
  function markRows(wrap, cfg) {
    var data = wrap._gkData;
    if (!data || !data.columns) return;
    var keys = Object.keys(data.columns);
    var perPage = parseInt(data.perPage, 10) || 0;
    var page = wrap._gkPage || 1;
    var all = wrap._gkMatching || data.rows;
    var shown = perPage > 0 ? all.slice((page - 1) * perPage, page * perPage) : all;
    var pending = wrap._gkEditPending || {};
    var trs = Array.prototype.filter.call(wrap.querySelectorAll(".gk-table > tbody > tr"), function (tr) {
      return !!tr.querySelector(":scope > td[data-label]");
    });
    trs.forEach(function (tr, i) {
      var row = shown[i];
      if (!row) return;
      if (!tr.hasAttribute("data-gk-row-id")) tr.setAttribute("data-gk-row-id", asText(row[cfg.key]));
      var id = tr.getAttribute("data-gk-row-id");
      if (asText(row[cfg.key]) === "") return; // a row without its key is not editable (Table::editAttrs)
      var tds = tr.querySelectorAll(":scope > td[data-label]");
      Object.keys(cfg.columns).forEach(function (column) {
        var td = tds[keys.indexOf(column)];
        if (!td) return;
        td.setAttribute("data-gk-edit", column);
        td.setAttribute("tabindex", "0");
        td.setAttribute("aria-describedby", cfg.hint);
        if (pending[pendingKey(id, column)]) td.setAttribute("aria-busy", "true");
      });
    });
  }

  GK.table.renderStatic = function (wrap) {
    var cfg = wrap && !wrap._gkEditScratch ? config(wrap) : null;
    var focus = cfg ? beforeRedraw(wrap) : null;
    var result = renderStatic.apply(this, arguments);
    if (cfg) {
      markRows(wrap, cfg);
      if (focus) focusCell(cellOf(wrap, focus.id, focus.column));
    }
    return result;
  };

  var reload = GK.table.reload;
  if (typeof reload === "function") {
    GK.table.reload = function (wrap) {
      if (config(wrap)) beforeRedraw(wrap);
      return reload.apply(this, arguments);
    };
  }

  // A click opens a cell; the end of a text selection does not (the rule a row link follows).
  document.addEventListener("click", function (e) {
    var td = e.target.closest ? e.target.closest("td[data-gk-edit]") : null;
    if (!td || td._gkEdit || e.defaultPrevented) return;
    var own = e.target.closest("a[href], button, input, select, textarea, label");
    if (own && td.contains(own)) return;
    var sel = window.getSelection ? window.getSelection() : null;
    if (sel && !sel.isCollapsed && String(sel).trim() !== "" && td.contains(sel.anchorNode)) return;
    open(td);
  });

  // Enter or F2 on the cell itself (it is in the tab order and says it can be edited).
  document.addEventListener("keydown", function (e) {
    if ((e.key !== "Enter" && e.key !== "F2") || e.defaultPrevented) return;
    var td = e.target;
    if (!td || !td.matches || !td.matches("td[data-gk-edit]")) return;
    if (open(td)) e.preventDefault();
  });
})();
// === 1.111.0 — tabs: API, deep links, lazy panels, events (GK.tabs.*) ===
/*
 * What a row of tabs could not do before: be named from outside (a link to its third tab, a reload
 * that keeps it, a person who comes back to the tab they left), load a heavy panel only when somebody
 * looks at it, tell the page that a switch is about to happen — and let the page say no, because the
 * panel holds unsaved changes — and be steered from script.
 *
 * It all hangs off the authored shape (.gk-tabs > .gk-tab-nav > .gk-tab-btn[data-tab] with
 * .gk-tab-panel[data-tab]): what GridKit\Tabs writes, and what a page writes by hand. Tabs adds an
 * attribute only for what was asked for:
 *   data-gk-tabs-hash      the chosen tab stays in the address as #<id>=<key>. The fragment is a list
 *                          joined by "&": the pair of every row and anything else a page keeps there
 *                          (an anchor, a section of its own) stay as they are. Written with replaceState:
 *                          a switch is no navigation, so Back leaves the page as it always did; a link to
 *                          "#<id>=<key>" and Back/Forward between such links switch the tab (hashchange).
 *   data-gk-tabs-remember  the last tab, per row id, in localStorage ("gk-tab-<id>" in the scope of
 *                          GK.theme, so the next person at a shared computer does not inherit it)
 *   data-gk-tabs-param     the query parameter the server read the tab from; a switch rewrites it in
 *                          place while the address carries it, so a reload shows the tab in view
 *   data-gk-tabs-chosen    where the server's choice came from: "page" (Tabs::active(): nothing overrides
 *                          it) or "url" (the parameter: only the fragment, which is newer, overrides it)
 *   data-gk-tab-url        a lazy panel, fetched on its first view (with data-gk-tab-reload: every view)
 * On load the order is: the page's decision, the fragment, the parameter, the memory, the markup.
 *
 * Every switch — a click, the arrow keys (GK.tabsMarkup moves the focus and clicks), GK.tabs.select()
 * and the address — goes through change(): gk:tabchange first (cancelable), then the switch, then
 * gk:tabshown. The tab a page opens with fires nothing.
 *
 * Nothing that exists is changed:
 *   GK.tabsMarkup.init  is wrapped: once the roles are on, a row is set up here. GK.initContent() reaches
 *                       every row that arrives later (a modal, a loaded panel, AJAX navigation) the same way.
 *   GK.tabs             (the object of the generated shape) gains select/current/badge/count/reload.
 *   the old switch      (a bubbling click listener on document) stays. The listener below runs before it,
 *                       in the capture phase: it asks first and switches itself. A refusal ends the click
 *                       there, so the old switch never sees it; after a switch it has nothing left to change.
 * A copy of this file without the block (the PawBot dashboard keeps its own) switches tabs as before;
 * a lazy panel shows the link Tabs writes into it, and a long row scrolls as it did.
 *
 * A line, pill or text row that is wider than its box (GK.tabsOverflow) keeps scrolling sideways, as
 * since 1.103.0, and shows where more tabs are: a round arrow on the side that has more, which pages
 * the row. Measured on the row itself, like GK.tabsFit. Scroll arrows, not a "More" menu: every tab
 * stays a tab of the one tablist — the arrow keys reach all of them (the focus scrolls the tab into
 * view), a screen reader hears "tab 5 of 8", nothing moves into a second list with other roles. The
 * arrows are pointer aids, aria-hidden and outside the tab order. The card style keeps its grid.
 */
(function () {
  "use strict";
  if (!window.GK || !GK.tabsMarkup || typeof GK.tabsMarkup.init !== "function") return;
  if (!GK.tabs) GK.tabs = { init: function () {} };

  function _t(key, fallback) {
    var lang = window.GK_LANG || {};
    return lang[key] || fallback;
  }

  function own(wrap, sel) {
    return GK.tabsMarkup.own(wrap, sel);
  }

  function off(btn) {
    return !!btn && (btn.disabled || btn.getAttribute("aria-disabled") === "true");
  }

  /** A row by element or id: a .gk-tabs (or anything in it), or a row of links outside one. */
  function rowOf(target) {
    var el = typeof target === "string" ? document.getElementById(target) : target;
    if (!el || el.nodeType !== 1) return null;
    if (el.matches(".gk-tab-nav") && !el.closest(".gk-tabs")) {
      return { wrap: null, tabs: Array.prototype.slice.call(el.querySelectorAll(".gk-tab-btn")) };
    }
    var wrap = el.matches(".gk-tabs") ? el : el.closest(".gk-tabs");
    return wrap ? { wrap: wrap, tabs: own(wrap, ".gk-tab-btn") } : null;
  }

  function tabOf(row, key) {
    if (!row || key === null || key === undefined) return null;
    key = String(key);
    return row.tabs.filter(function (b) { return b.getAttribute("data-tab") === key; })[0] || null;
  }

  function currentOf(wrap) {
    var on = own(wrap, ".gk-tab-btn").filter(function (b) { return b.classList.contains("gk-active"); })[0];
    return on ? on.getAttribute("data-tab") : null;
  }

  /** The tab for a key, when it can be chosen: it exists, switches a panel and is not disabled. */
  function usable(wrap, key) {
    var b = tabOf({ tabs: own(wrap, ".gk-tab-btn") }, key);
    return b && !off(b) && !b.matches("a[href]") ? b : null;
  }

  /** The state of a switch, exactly as the click handler of GK.tabsMarkup sets it (roles only where init put them). */
  function apply(wrap, btn) {
    var panel = GK.tabsMarkup.panel(wrap, btn.getAttribute("data-tab"));
    own(wrap, ".gk-tab-btn").forEach(function (b) {
      var on = b === btn;
      b.classList.toggle("gk-active", on);
      if (b.getAttribute("role") === "tab") {
        b.setAttribute("aria-selected", on ? "true" : "false");
        b.tabIndex = on ? 0 : -1;
      }
    });
    own(wrap, ".gk-tab-panel").forEach(function (p) {
      p.classList.toggle("gk-active", p === panel);
    });
    return panel;
  }

  function fire(wrap, type, detail, cancelable) {
    return wrap.dispatchEvent(new CustomEvent(type, { bubbles: true, cancelable: !!cancelable, detail: detail }));
  }

  // ── The address: #<id>=<key>, beside everything else in the fragment ──────────────────────────
  var address = {
    decode: function (s) {
      try { return decodeURIComponent(s); } catch (e) { return null; }
    },
    parts: function () {
      var h = location.hash.replace(/^#/, "");
      return h ? h.split("&") : [];
    },
    read: function (id) {
      var self = this, found = null;
      this.parts().forEach(function (part) {
        var i = part.indexOf("=");
        if (i > 0 && self.decode(part.slice(0, i)) === id) found = self.decode(part.slice(i + 1));
      });
      return found;
    },
    write: function (id, key) {
      var self = this, pair = encodeURIComponent(id) + "=" + encodeURIComponent(key), done = false;
      var parts = this.parts().map(function (part) {
        var i = part.indexOf("=");
        if (i > 0 && self.decode(part.slice(0, i)) === id) {
          done = true;
          return pair;
        }
        return part;
      });
      if (!done) parts.push(pair);
      var next = "#" + parts.join("&");
      if (next === location.hash) return;
      // replaceState: no history entry, and no hashchange for this very switch.
      try { history.replaceState(history.state, "", location.href.replace(/#.*$/, "") + next); } catch (e) {}
    },
  };

  // ── The memory: localStorage, guarded — a private window or blocked site data just forgets ─────
  var memory = {
    name: function (id) {
      var n = "gk-tab-" + id;
      return GK.theme && typeof GK.theme._key === "function" ? GK.theme._key(n) : n;
    },
    read: function (id) {
      try { return window.localStorage.getItem(this.name(id)); } catch (e) { return null; }
    },
    write: function (id, key) {
      try { window.localStorage.setItem(this.name(id), key); } catch (e) {}
    },
  };

  // ── The query parameter the server read (Tabs::param()) ───────────────────────────────────────
  var param = {
    read: function (wrap) {
      var name = wrap.getAttribute("data-gk-tabs-param");
      if (!name) return null;
      try { return new URLSearchParams(location.search).get(name); } catch (e) { return null; }
    },
    write: function (wrap, key) {
      var name = wrap.getAttribute("data-gk-tabs-param");
      if (!name) return;
      try {
        var url = new URL(location.href);
        // Only where the address already names the tab: a parameter added out of nothing would change
        // what the server sees (a live table reads a bare address as a fresh visit).
        if (!url.searchParams.has(name) || url.searchParams.get(name) === key) return;
        url.searchParams.set(name, key);
        history.replaceState(history.state, "", url.href);
      } catch (e) {}
    },
  };

  function persist(wrap, key) {
    if (wrap.id && wrap.hasAttribute("data-gk-tabs-hash")) address.write(wrap.id, key);
    if (wrap.id && wrap.hasAttribute("data-gk-tabs-remember")) memory.write(wrap.id, key);
    param.write(wrap, key);
  }

  // ── Lazy panels ───────────────────────────────────────────────────────────────────────────────
  var lazy = {
    /** On the screen: the panel and every panel around it is the active one of its row. */
    shown: function (panel) {
      for (var p = panel; p; p = p.parentElement ? p.parentElement.closest(".gk-tab-panel") : null) {
        if (!p.classList.contains("gk-active")) return false;
      }
      return true;
    },
    /** A panel that has just come into view: load it the first time (with data-gk-tab-reload: each time it
        is chosen), and the lazy panels inside it that are now in view too. */
    show: function (panel, chosen) {
      var self = this;
      if (panel.hasAttribute("data-gk-tab-url")) {
        if ((chosen && panel.hasAttribute("data-gk-tab-reload")) || (!panel._gkTabLoaded && !panel._gkTabLoading)) this.load(panel);
      }
      panel.querySelectorAll(".gk-tab-panel.gk-active[data-gk-tab-url]").forEach(function (inner) {
        if (self.shown(inner) && !inner._gkTabLoaded && !inner._gkTabLoading) self.load(inner);
      });
    },
    clear: function (panel, box) {
      while (panel.firstChild) panel.removeChild(panel.firstChild);
      panel.appendChild(box);
    },
    /** The first load: lines of skeleton, and the word for a screen reader. A reload keeps the old content (dimmed). */
    wait: function (panel) {
      var box = document.createElement("div");
      box.className = "gk-tab-loading";
      var say = document.createElement("span");
      say.className = "gk-sr-only";
      say.textContent = _t("tab_loading", "Loading…");
      box.appendChild(say);
      for (var i = 0; i < 3; i++) {
        var line = document.createElement("span");
        line.className = "gk-skeleton";
        line.setAttribute("aria-hidden", "true");
        box.appendChild(line);
      }
      this.clear(panel, box);
    },
    /** The failure, in the words and the shape of a table that could not load — never the server's own answer. */
    fail: function (panel) {
      var self = this, box = document.createElement("div");
      box.className = "gk-empty";
      box.setAttribute("role", "alert");
      box.innerHTML = '<div class="gk-empty-inner"><span class="material-icons gk-empty-icon" aria-hidden="true">cloud_off</span>' +
        '<span class="gk-empty-title"></span><span class="gk-empty-action">' +
        '<button type="button" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm"></button></span></div>';
      box.querySelector(".gk-empty-title").textContent = _t("tab_load_error", "This tab could not be loaded.");
      var again = box.querySelector("button");
      again.textContent = _t("retry", "Try again");
      again.addEventListener("click", function () { self.load(panel); });
      this.clear(panel, box);
      return again;
    },
    /** Fetch the panel. Resolves to true when the new content is in; an older answer never overwrites a newer one. */
    load: function (panel) {
      var self = this, wrap = panel.closest(".gk-tabs"), key = panel.getAttribute("data-tab");
      var run = (panel._gkTabRun || 0) + 1, status = 0;
      var focused = panel.contains(document.activeElement);
      panel._gkTabRun = run;
      panel._gkTabLoading = true;
      panel.setAttribute("aria-busy", "true");
      if (!panel._gkTabLoaded) this.wait(panel);
      var target = null;
      try { target = new URL(panel.getAttribute("data-gk-tab-url") || "", location.href); } catch (e) {}
      // Same origin only: the answer becomes markup on this page.
      var request = target && target.origin === location.origin && /^https?:$/.test(target.protocol)
        ? fetch(target.href, { headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin", mode: "same-origin" })
        : Promise.reject(new Error("a lazy tab loads from this origin only"));
      var done = function (ok) {
        panel._gkTabLoading = false;
        panel.removeAttribute("aria-busy");
        if (wrap) fire(wrap, "gk:tabload", { tabsId: wrap.id || null, key: key, panel: panel, ok: ok, status: status }, false);
        return ok;
      };
      return request
        .then(function (r) {
          status = r.status;
          if (!r.ok) throw new Error("HTTP " + r.status);
          return r.text();
        })
        .then(function (html) {
          // A whole page is not a panel: a login page after the session ended, an address that is a page.
          if (/^\s*<!doctype|<html[\s>]/i.test(html)) throw new Error("a whole page came back");
          if (panel._gkTabRun !== run) return false;
          panel.innerHTML = html;
          panel._gkTabLoaded = true;
          // Widgets inside work as on a page of their own; a widget that fails to start does not turn the
          // content that did arrive into "could not be loaded".
          try { GK.initContent(panel); } catch (e) { if (window.console) console.error("GridKit: a widget in a loaded tab did not start", e); }
          if (focused) {
            if (!panel.hasAttribute("tabindex")) panel.setAttribute("tabindex", "-1");
            panel.focus();
          }
          return done(true);
        })
        .catch(function (err) {
          if (panel._gkTabRun !== run) return false;
          panel._gkTabLoaded = false;
          var again = self.fail(panel);
          if (focused) again.focus();
          if (window.console) console.warn("GridKit: a lazy tab did not load —", err && err.message);
          return done(false);
        });
    },
  };

  // ── Overflow of a line, pill or text row ──────────────────────────────────────────────────────
  var ROWS = ".gk-tab-nav-linie, .gk-tab-nav-kapsel, .gk-tab-nav-text";
  GK.tabsOverflow = {
    cls: "gk-tab-nav-overflow",
    init: function (root) {
      var self = this, scope = root && root.querySelectorAll ? root : document;
      scope.querySelectorAll(ROWS).forEach(function (nav) {
        if (nav._gkTabsOverflow || !nav.classList.contains("gk-tab-nav")) return;
        nav._gkTabsOverflow = true;
        self.fit(nav);
        nav.addEventListener("scroll", function () { self.edges(nav); }, { passive: true });
        if (!window.ResizeObserver) return;
        // As GK.tabsFit: the re-measure waits one frame (a change of size from inside the observer
        // ends in "ResizeObserver loop completed with undelivered notifications").
        var queued = false;
        var watch = new ResizeObserver(function () {
          if (queued) return;
          queued = true;
          requestAnimationFrame(function () {
            queued = false;
            self.fit(nav);
          });
        });
        watch.observe(nav);
        Array.prototype.forEach.call(nav.children, function (tab) {
          if (tab.classList.contains("gk-tab-btn")) watch.observe(tab);
        });
      });
    },
    /** Measured as it stands: the arrows take no width of their own, so they never decide the overflow. */
    fit: function (nav) {
      var was = nav.classList.contains(this.cls);
      var over = nav.scrollWidth > nav.clientWidth + 1;
      nav.classList.toggle(this.cls, over);
      if (over && !nav._gkTabArrows) this.arrows(nav);
      this.edges(nav);
      if (over && !was) this.reveal(nav, nav.querySelector(':scope > .gk-tab-btn.gk-active, :scope > .gk-tab-btn[aria-current="page"]'));
    },
    arrows: function (nav) {
      nav._gkTabArrows = ["start", "end"].map(function (side) {
        var arrow = document.createElement("span");
        arrow.className = "gk-tab-arrow gk-tab-arrow-" + side;
        arrow.setAttribute("aria-hidden", "true");
        arrow.addEventListener("click", function (e) {
          e.preventDefault();
          var rtl = getComputedStyle(nav).direction === "rtl";
          var step = Math.max(nav.clientWidth * 0.8, 80) * (side === "start" ? -1 : 1) * (rtl ? -1 : 1);
          var still = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
          try { nav.scrollBy({ left: step, behavior: still ? "auto" : "smooth" }); } catch (err) { nav.scrollLeft += step; }
        });
        // At the end of the row in the markup, so every tab keeps its place (:nth-child included);
        // the stylesheet puts the start arrow first with `order`.
        nav.appendChild(arrow);
        return arrow;
      });
    },
    /** An arrow only on a side that has more — measured, so a row that fits again hides both. */
    edges: function (nav) {
      var a = nav._gkTabArrows;
      if (!a) return;
      var pos = Math.abs(nav.scrollLeft), max = nav.scrollWidth - nav.clientWidth;
      a[0].hidden = pos <= 1;
      a[1].hidden = pos >= max - 1;
    },
    /** The chosen tab whole in view and clear of the arrows (the row's scroll-padding says how far). */
    reveal: function (nav, btn) {
      if (!nav || !btn || !nav.classList.contains(this.cls)) return;
      var pad = parseFloat(getComputedStyle(nav).scrollPaddingInlineStart) || 0;
      var n = nav.getBoundingClientRect(), b = btn.getBoundingClientRect();
      if (b.left < n.left + pad) nav.scrollLeft -= n.left + pad - b.left;
      else if (b.right > n.right - pad) nav.scrollLeft += b.right - n.right + pad;
    },
  };

  /** After a switch, and for the tab a row opens with: load what is now in view, show the tab in its row. */
  function settle(btn, panel, chosen) {
    if (panel && lazy.shown(panel)) lazy.show(panel, chosen);
    GK.tabsOverflow.reveal(btn.parentElement, btn);
  }

  /**
   * The one way a tab changes. Returns true when the tab is the current one afterwards. force: the
   * event cannot be refused (a switch the page has already confirmed).
   */
  function change(wrap, btn, source, force) {
    var to = btn.getAttribute("data-tab"), from = currentOf(wrap);
    if (from === to) return true;
    var panel = GK.tabsMarkup.panel(wrap, to);
    var detail = { tabsId: wrap.id || null, from: from, to: to, panel: panel, source: source };
    if (!fire(wrap, "gk:tabchange", detail, !force)) return false;
    // A listener chose a tab itself (GK.tabs.select inside it): that choice stands.
    if (currentOf(wrap) !== from) return currentOf(wrap) === to;
    apply(wrap, btn);
    persist(wrap, to);
    settle(btn, panel, true);
    fire(wrap, "gk:tabshown", { tabsId: detail.tabsId, from: from, to: to, panel: panel, source: source }, false);
    return true;
  }

  /**
   * A deep link into an inner row shows the panels around it too — unless an outer row was told otherwise:
   * on load by the page or by a pair of its own, later (a link, Back) by a pair of its own in the address.
   */
  function revealAround(wrap, viaChange) {
    var panel = wrap.parentElement ? wrap.parentElement.closest(".gk-tab-panel") : null;
    for (; panel; panel = panel.parentElement ? panel.parentElement.closest(".gk-tab-panel") : null) {
      var outer = panel.closest(".gk-tabs");
      if (!outer) return;
      var named = outer.id && outer.hasAttribute("data-gk-tabs-hash") && address.read(outer.id) !== null;
      if (viaChange ? named : outer._gkTabsFrom === "page" || outer._gkTabsFrom === "address") return;
      var b = usable(outer, panel.getAttribute("data-tab"));
      if (!b || panel.classList.contains("gk-active")) continue;
      if (viaChange) {
        if (!change(outer, b, "address", false)) return;
      } else {
        apply(outer, b);
      }
    }
  }

  /** The tab a row opens with: the page's decision, the fragment, the parameter, the memory, the markup. */
  function setup(wrap) {
    if (wrap._gkTabsApi || !own(wrap, ".gk-tab-nav")[0]) return false;
    wrap._gkTabsApi = true;
    var id = wrap.id, chosen = wrap.getAttribute("data-gk-tabs-chosen"), pick = null, from = "markup";
    if (chosen === "page") {
      from = "page";
    } else {
      var named = id && wrap.hasAttribute("data-gk-tabs-hash") ? usable(wrap, address.read(id)) : null;
      var asked = usable(wrap, param.read(wrap));
      var kept = id && wrap.hasAttribute("data-gk-tabs-remember") ? usable(wrap, memory.read(id)) : null;
      if (named) { pick = named; from = "address"; }
      else if (asked) { pick = asked; from = "url"; }
      else if (chosen === "url") from = "url";
      else if (kept) { pick = kept; from = "memory"; }
    }
    wrap._gkTabsFrom = from;
    if (pick && pick.getAttribute("data-tab") !== currentOf(wrap)) apply(wrap, pick);
    if (from === "address") revealAround(wrap, false);
    // The address names a tab the row did not take (the page decided, or the tab is disabled or gone):
    // it says what is shown instead.
    if (id && wrap.hasAttribute("data-gk-tabs-hash")) {
      var cur = currentOf(wrap), said = address.read(id);
      if (said !== null && cur !== null && said !== cur) address.write(id, cur);
    }
    return true;
  }

  function init(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var fresh = Array.prototype.filter.call(scope.querySelectorAll(".gk-tabs"), setup);
    GK.tabsOverflow.init(scope);
    // Loaded once every row has made its choice: a panel a deep link switches away from is never fetched.
    fresh.forEach(function (wrap) {
      var key = currentOf(wrap), btn = tabOf({ tabs: own(wrap, ".gk-tab-btn") }, key);
      if (btn) settle(btn, GK.tabsMarkup.panel(wrap, key), false);
    });
  }

  // Click and keyboard: ahead of the old switch (see above).
  document.addEventListener("click", function (e) {
    var btn = e.target && e.target.closest ? e.target.closest(".gk-tab-btn") : null;
    if (!btn || !btn.hasAttribute("data-tab") || off(btn) || btn.matches("a[href]")) return;
    var wrap = btn.closest(".gk-tabs");
    if (!wrap) return;
    var to = btn.getAttribute("data-tab");
    if (currentOf(wrap) === to) return;
    if (!change(wrap, btn, "user", false)) {
      e.preventDefault();
      e.stopPropagation();
      // The arrow keys moved the focus before they clicked: it goes back to the tab that stays.
      var stays = tabOf({ tabs: own(wrap, ".gk-tab-btn") }, currentOf(wrap));
      if (stays && document.activeElement === btn) stays.focus();
      return;
    }
    // A gk:tabshown listener moved on to another tab: the old switch must not undo that.
    if (currentOf(wrap) !== to) e.stopPropagation();
  }, true);

  window.addEventListener("hashchange", function () {
    document.querySelectorAll(".gk-tabs[data-gk-tabs-hash]").forEach(function (wrap) {
      var said = wrap.id ? address.read(wrap.id) : null;
      if (said === null) return;
      var b = usable(wrap, said), cur = currentOf(wrap);
      if (b && b.getAttribute("data-tab") === cur) return;
      // A key no tab can take (unknown, disabled), or a switch that was refused: the address says what is shown.
      if (b && change(wrap, b, "address", false)) revealAround(wrap, true);
      else if (cur !== null) address.write(wrap.id, currentOf(wrap));
    });
  });

  var api = {
    /** Switch to a tab, as a click would (gk:tabchange can refuse it). opts: { focus, force }. */
    select(target, key, opts) {
      var row = rowOf(target);
      if (!row || !row.wrap) return false;
      var btn = usable(row.wrap, key);
      if (!btn) return false;
      var ok = change(row.wrap, btn, "api", !!(opts && opts.force));
      if (ok && opts && opts.focus) btn.focus();
      return ok;
    },
    /** The key of the current tab (of a row of links: the one with aria-current="page"), or null. */
    current(target) {
      var row = rowOf(target);
      if (!row) return null;
      if (row.wrap) return currentOf(row.wrap);
      var on = row.tabs.filter(function (b) { return b.getAttribute("aria-current") === "page"; })[0];
      return on ? on.getAttribute("data-tab") : null;
    },
    /** Put, change or (text null or "") take away the badge of a tab. opts: { tone: "error" }. */
    badge(target, key, text, opts) {
      var btn = tabOf(rowOf(target), key);
      if (!btn) return false;
      var el = btn.querySelector(":scope > .gk-tab-badge");
      if (text === null || text === undefined || text === "") {
        if (el) el.remove();
      } else {
        if (!el) {
          el = document.createElement("span");
          btn.insertBefore(el, btn.querySelector(":scope > .gk-tab-count"));
        }
        el.className = "gk-tab-badge" + (opts && opts.tone === "error" ? " gk-tab-badge-error" : "");
        el.textContent = String(text);
      }
      named(btn);
      return true;
    },
    /** Put, change or (n null or "") take away the count of a tab. */
    count(target, key, n) {
      var btn = tabOf(rowOf(target), key);
      if (!btn) return false;
      var el = btn.querySelector(":scope > .gk-tab-count");
      if (n === null || n === undefined || n === "") {
        if (el) el.remove();
      } else {
        if (!el) {
          el = document.createElement("span");
          el.className = "gk-tab-count";
          btn.appendChild(el);
        }
        el.textContent = String(n);
      }
      named(btn);
      return true;
    },
    /** Fetch a lazy panel again now (key: the current tab when left out). Resolves to true once the content is in. */
    reload(target, key) {
      var row = rowOf(target);
      if (!row || !row.wrap) return Promise.resolve(false);
      var k = key === undefined || key === null ? currentOf(row.wrap) : String(key);
      var panel = k === null ? null : GK.tabsMarkup.panel(row.wrap, k);
      if (!panel || !panel.hasAttribute("data-gk-tab-url")) return Promise.resolve(false);
      return lazy.load(panel);
    },
  };
  Object.keys(api).forEach(function (name) { GK.tabs[name] = api[name]; });

  /** An icon-only tab is named by its aria-label: the badge and the count belong in it, as Tabs writes it. */
  function named(btn) {
    var nav = btn.parentElement;
    if (!nav || !nav.classList.contains("gk-tab-nav-nur-icon") || !btn.hasAttribute("aria-label")) return;
    var word = "";
    Array.prototype.forEach.call(btn.childNodes, function (n) {
      if (n.nodeType === 3 || (n.nodeType === 1 && !n.matches(".gk-tab-icon, .gk-tab-badge, .gk-tab-count"))) word += n.textContent;
    });
    var parts = [word.trim()];
    [":scope > .gk-tab-badge", ":scope > .gk-tab-count"].forEach(function (sel) {
      var el = btn.querySelector(sel);
      if (el && el.textContent.trim() !== "") parts.push(el.textContent.trim());
    });
    btn.setAttribute("aria-label", parts.filter(Boolean).join(", "));
  }

  // Every row GK.tabsMarkup gives its roles to — on load and through GK.initContent() later.
  var markupInit = GK.tabsMarkup.init;
  GK.tabsMarkup.init = function (root) {
    var result = markupInit.apply(this, arguments);
    init(root);
    return result;
  };
  // A script that arrives after the document is ready: GK.init() has run before the wrap above.
  _gkReady(function () { init(document); });
})();
// === 1.112.0 — tooltip engine (GK.tooltip, GK.tip, toggletips) ===
/*
 * One engine for every tooltip GridKit has. There were three, each with its own idea of where to
 * go and when to close: the CSS-only data-gk-tooltip (an ::after, cut off at the window edge, for
 * a mouse only, not in the accessibility tree), GK.tip for title attributes (above, clamped, no
 * keyboard) and the rich data-gk-tooltip-rich (below, a 100 ms timer). All of them, and the new
 * toggletip, now share:
 *
 *   placement  the preferred side (data-gk-tooltip-pos: top, bottom, left, right, auto), the
 *              opposite side when it does not fit, then the other two; shifted along the edge
 *              to stay inside the window and inside the visible part of a box that scrolls; the
 *              arrow keeps pointing at the trigger after the shift.
 *   opening    hover after a delay (data-gk-tooltip-delay, 300 ms; none when the pointer moves
 *              on from a tooltip just shown), keyboard focus at once (:focus-visible — a mouse
 *              click on a button is not asking for one), a tap on a trigger that is not itself
 *              a control. A button or link keeps its click: nothing here prevents it.
 *   closing    Escape, blur, the pointer leaving trigger and bubble (100 ms of grace, so it can
 *              cross into the bubble and rest there, WCAG 1.4.13), the trigger scrolling out of
 *              sight, a tap elsewhere. One tooltip at a time, one toggletip at a time.
 *   reading    a plain or title tooltip describes its trigger (aria-describedby) through a
 *              hidden copy of its text, so a screen reader has it before anything is shown.
 *
 * Toggletip (data-gk-toggletip="text" or "#id", and the .gk-help icon Tooltip::help() writes):
 * opens on a click, Enter or Space, stays open, may hold links and buttons. Its bubble is a live
 * region (role=status) placed right after the trigger: the text is read out when it opens and
 * the focus STAYS on the trigger — a help text is read, not operated, and moving the focus into a
 * popup would take a person out of the form they were filling in. Tab from the trigger walks into
 * the bubble's links, because they come next in the document; Escape closes it and brings the
 * focus back; so do a click outside, a second click and the focus moving on past it.
 *
 * Progressive enhancement: this block marks <html data-gk-tt>, and only then does the stylesheet
 * drop the CSS-only ::after (and the rich tooltip's .visible) — so nothing shows twice, and a
 * page with an older copy of this file (the PawBot dashboard keeps its own) keeps the CSS-only
 * tooltip and GK.tip exactly as they were.
 *
 * Nothing above is changed. Replaced or wrapped from here:
 *   GK.tooltip.init        the rich tooltip's binding is now this engine (plus show, hide, update)
 *   GK.tip.show/hide/init  on this engine. When the old GK.tip.init already ran — a script added
 *                          after the page was complete — its listeners are left without effect
 *                          (see the note at GK.tip below).
 *   GK.table.renderStatic  a column's help icon and a row button's tooltip come back after a
 *                          rebuild, exactly as Table.php wrote them (tooltipHtml / tooltipAttrs)
 *   GK.table.init          a table that arrives later has its tooltips bound with it
 */
(function () {
  "use strict";
  if (!window.GK || !GK.tooltip || GK.tooltip._engine) return;

  var doc = document;
  var SIDES = ["top", "bottom", "left", "right"];
  var OPPOSITE = { top: "bottom", bottom: "top", left: "right", right: "left" };
  var TONES = ["neutral", "info", "success", "warning", "danger", "light"];
  var OPEN_DELAY = 300;  // ms on hover before a tooltip opens
  var CLOSE_DELAY = 100; // ms of grace after the pointer leaves
  var WARM = 300;        // ms after a close in which the next hover opens at once
  var GAP = 8;           // px between trigger and bubble; the arrow sits in it
  var EDGE = 8;          // px a bubble keeps from the edge of the window or box
  var MARK = "__gk_tt__:";
  var CONTROL = "a[href], area[href], button, input, select, textarea, summary, label, iframe, " +
    "[contenteditable=''], [contenteditable='true'], [role='button'], [role='link'], [role='checkbox'], " +
    "[role='switch'], [role='tab'], [role='menuitem'], [role='option'], [onclick]";
  var FOCUSABLE = "a[href], area[href], button:not(:disabled), input:not(:disabled):not([type='hidden']), " +
    "select:not(:disabled), textarea:not(:disabled), summary, iframe, [contenteditable=''], " +
    "[contenteditable='true'], [tabindex]:not([tabindex='-1'])";
  var TRIGGER = "[data-gk-toggletip], [data-gk-tooltip-rich], [data-gk-tooltip], [title], [data-gk-tip]";

  // From the first moment: the CSS-only ::after must not flash before the engine is bound.
  doc.documentElement.setAttribute("data-gk-tt", "");

  var cur = null;     // the open tooltip: { el, kind, bubble, o, reasons }
  var toggle = null;  // the open toggletip: { el, bubble, o, src, home, wasHidden }
  var pending = null, openTimer = 0, closeTimer = 0, lastClosed = 0;
  var suppressed = null; // closed with Escape or a click: not again until the pointer leaves
  var lastPointer = "mouse";
  var shared = null;  // the one bubble of plain and title tooltips

  function attr(el, name) {
    var v = el.getAttribute(name);
    return v == null ? "" : v;
  }
  function each(root, sel, fn) {
    if (root.nodeType === 1 && root.matches(sel)) fn(root);
    root.querySelectorAll(sel).forEach(fn);
  }
  function clamp(v, lo, hi) {
    return hi < lo ? lo : Math.min(Math.max(v, lo), hi);
  }
  function inside(c, node) {
    return !!(c && node && (c.el.contains(node) || c.bubble.contains(node)));
  }

  // ── What a trigger is ─────────────────────────────────────────────────

  /** The element a '#id' toggletip shows, or null for a text. */
  function ref(spec) {
    if (!/^#[A-Za-z][\w:.-]*$/.test(spec)) return null;
    return doc.getElementById(spec.slice(1));
  }

  function richOf(el) {
    try { return doc.querySelector(attr(el, "data-gk-tooltip-rich").trim()); } catch (e) { return null; }
  }

  /** toggle, rich, plain or tip — the toggletip first: a .gk-help carries data-gk-tooltip too,
   *  as its fallback for a page without this block. */
  function kindOf(el) {
    if (el.hasAttribute("data-gk-toggletip")) return attr(el, "data-gk-toggletip").trim() ? "toggle" : null;
    if (el.hasAttribute("data-gk-tooltip-rich")) return richOf(el) ? "rich" : null;
    if (attr(el, "data-gk-tooltip").trim()) return "plain";
    if (el.hasAttribute("title") || el.hasAttribute("data-gk-tip")) {
      if (el.closest("[data-gk-tip-off]")) return null;
      if (!(attr(el, "title") || attr(el, "data-gk-tip")).trim()) return null;
      return "tip";
    }
    return null;
  }

  /** The innermost trigger at or above a node: { el, kind } or null. */
  function triggerOf(node) {
    var el = node && node.nodeType === 1 ? node : node && node.parentElement;
    while (el && el.closest) {
      el = el.closest(TRIGGER);
      if (!el) return null;
      var k = kindOf(el);
      if (k) return { el: el, kind: k };
      el = el.parentElement;
    }
    return null;
  }

  /** The options of a trigger, from data-gk-tooltip-*; anything unknown is the default. */
  function optionsOf(el) {
    var pos = attr(el, "data-gk-tooltip-pos").trim();
    if (pos !== "auto" && SIDES.indexOf(pos) < 0) pos = "top";
    var tone = attr(el, "data-gk-tooltip-tone").trim();
    if (TONES.indexOf(tone) < 0) tone = "";
    var delay = parseInt(attr(el, "data-gk-tooltip-delay"), 10);
    delay = delay >= 0 ? Math.min(delay, 10000) : OPEN_DELAY;
    var max = attr(el, "data-gk-tooltip-max").trim();
    if (/^\d+(\.\d+)?$/.test(max)) max += "px";
    else if (!/^\d+(\.\d+)?(px|rem|em|ch|vw|%)$/.test(max)) max = "";
    return {
      pos: pos, tone: tone, delay: delay, max: max,
      kbd: attr(el, "data-gk-tooltip-kbd").trim(),
      wrap: el.hasAttribute("data-gk-tooltip-wrap"),
    };
  }

  /** A control of its own between the node and the trigger (null: anywhere above). */
  function isControl(node, trigger) {
    for (var el = node && node.nodeType === 1 ? node : node && node.parentElement; el; el = el.parentElement) {
      if (el.matches(CONTROL) || (el.hasAttribute("tabindex") && !el._gkTtTab)) return true;
      if (el === trigger) return false;
    }
    return false;
  }

  function focusVisible(el) {
    try { return el.matches(":focus-visible"); } catch (e) { return true; }
  }

  // ── Reading: the description ──────────────────────────────────────────

  var descHost = null, descByText = Object.create(null), descSeq = 0;

  /** The id of a hidden element holding this text; one per distinct text, shared. */
  function descriptionId(text) {
    var node = descByText[text];
    if (node && node.isConnected) return node.id;
    if (!descHost || !descHost.isConnected) {
      descHost = doc.createElement("div");
      descHost.hidden = true;
      descHost.setAttribute("data-gk-tt-descriptions", "");
      doc.body.appendChild(descHost);
    }
    node = doc.createElement("span");
    node.id = "gk-tt-d" + ++descSeq;
    node.textContent = text;
    descHost.appendChild(node);
    descByText[text] = node;
    return node.id;
  }

  /** Join an element to the text with aria-describedby, replacing what this engine set before. */
  function describe(el, text) {
    var id = descriptionId(text);
    var ids = attr(el, "aria-describedby").split(/\s+/).filter(Boolean);
    if (el._gkTtDesc === id && ids.indexOf(id) >= 0) return;
    var prev = el._gkTtDesc;
    ids = ids.filter(function (x) { return x !== prev && x !== id; });
    ids.push(id);
    el.setAttribute("aria-describedby", ids.join(" "));
    el._gkTtDesc = id;
  }

  /** Text a screen reader would name the element by, roughly: icons and hidden parts left out. */
  function ownText(el) {
    var out = "";
    (function walk(n) {
      for (var c = n.firstChild; c; c = c.nextSibling) {
        if (c.nodeType === 3) out += c.nodeValue;
        else if (c.nodeType === 1) {
          if (c.hidden || c.getAttribute("aria-hidden") === "true") continue;
          if (c.matches(".material-icons, .material-symbols-outlined, .material-symbols-rounded")) continue;
          if (c.matches("img")) { out += attr(c, "alt"); continue; }
          if (attr(c, "aria-label")) { out += attr(c, "aria-label"); continue; }
          if (c.matches("svg")) continue;
          walk(c);
        }
      }
    })(el);
    return out.trim();
  }

  /** The element that takes the focus for a trigger: itself, a control inside it, or itself made
   *  focusable (tabindex=0) — a trigger the keyboard cannot reach cannot show a tooltip on focus. */
  function focusTarget(el) {
    if (el.matches(FOCUSABLE) || el.hasAttribute("tabindex")) return el;
    var inner = el.querySelector(FOCUSABLE);
    if (inner) return inner;
    if (el.matches("button, input, select, textarea")) return el; // disabled: nothing to add
    el.tabIndex = 0;
    el._gkTtTab = true;
    return el;
  }

  function descText(text, o) {
    return o.kbd ? text + " (" + o.kbd + ")" : text;
  }

  function prepPlain(el) {
    var text = attr(el, "data-gk-tooltip").trim();
    var o = optionsOf(el);
    var key = text + "\u0000" + o.kbd;
    if (el._gkTtPlain === key) return;
    el._gkTtPlain = key;
    describe(focusTarget(el), descText(text, o));
  }

  /**
   * A title, taken over: moved to data-gk-tip so the browser draws no popup of its own (GK.tip's
   * rule since 1.23.0). For a control with no name of its own the title WAS its name and becomes
   * aria-label, as before; for one with a name — a button with text, a labelled input — it was
   * the description and stays one, through aria-describedby. It used to become aria-label there
   * too, and replaced the visible name: "Save" was announced as "Save the current draft".
   */
  function prepTip(el) {
    var title = attr(el, "title");
    if (!title) return;
    el.setAttribute("data-gk-tip", title);
    el.removeAttribute("title");
    var named = !!(attr(el, "aria-labelledby") || (el.labels && el.labels.length) ||
      (attr(el, "aria-label") && !el._gkTtNamed));
    if (!named && (el._gkTtNamed || !ownText(el))) {
      el.setAttribute("aria-label", title);
      el._gkTtNamed = true;
    } else {
      describe(el, title);
    }
  }

  function prepRich(el) {
    var tip = richOf(el);
    if (!tip) return null;
    tip.classList.add("gk-tooltip-content");
    if (!tip.querySelector(":scope > .gk-tip-arrow")) tip.appendChild(arrowEl());
    if (tip.id) {
      var ids = attr(el, "aria-describedby").split(/\s+/).filter(Boolean);
      if (ids.indexOf(tip.id) < 0) {
        ids.push(tip.id);
        el.setAttribute("aria-describedby", ids.join(" "));
      }
    }
    if (!el.hasAttribute("tabindex") && !el.matches("a[href], button, input, select, textarea")) {
      el.tabIndex = 0;
      el._gkTtTab = true;
    }
    el._gkTipRich = true; // the mark the old binding used: it must not bind this one again
    return tip;
  }

  /** A toggletip trigger: a button to the keyboard, and its live region right after it. */
  function prepToggle(el) {
    var st = el._gkTt;
    if (st && st.bubble.isConnected && el.nextElementSibling === st.bubble) return st;
    if (!el.matches("button, a[href], input, select, textarea, summary")) {
      if (!el.hasAttribute("role")) el.setAttribute("role", "button");
      if (!el.hasAttribute("tabindex")) el.tabIndex = 0;
    }
    if (!el.hasAttribute("aria-expanded")) el.setAttribute("aria-expanded", "false");
    var next = el.nextElementSibling;
    var bubble = next && next.classList.contains("gk-toggletip") && (!next._gkTtOwner || next._gkTtOwner === el) ? next : null;
    if (!bubble) {
      bubble = doc.createElement("span");
      bubble.className = "gk-toggletip";
      bubble.setAttribute("role", "status");
      el.insertAdjacentElement("afterend", bubble);
    }
    bubble._gkTtOwner = el;
    var src = ref(attr(el, "data-gk-toggletip").trim());
    if (src && src.tagName !== "TEMPLATE" && !src.closest(".gk-toggletip")) src.hidden = true;
    // A live region is only heard when it was in the accessibility tree before its words
    // changed (GK.announce, 1.93.0). One made this instant waits a moment before it speaks.
    el._gkTt = { bubble: bubble, born: Date.now() };
    return el._gkTt;
  }

  function prepare(root) {
    root = root && root.querySelectorAll ? root : doc;
    each(root, "[data-gk-toggletip]", prepToggle);
    each(root, "[data-gk-tooltip-rich]", function (el) { if (!el.hasAttribute("data-gk-toggletip")) prepRich(el); });
    each(root, "[data-gk-tooltip]", function (el) {
      if (!el.hasAttribute("data-gk-toggletip") && attr(el, "data-gk-tooltip").trim()) prepPlain(el);
    });
  }

  // ── The bubbles ───────────────────────────────────────────────────────

  function arrowEl() {
    var a = doc.createElement("span");
    a.className = "gk-tip-arrow";
    a.setAttribute("aria-hidden", "true");
    return a;
  }

  /** Where the shared bubble lives: an open <dialog> is in the top layer and would cover body;
   *  a themed or dark part of the page lends it its colours. */
  function hostFor(el) {
    return el.closest("dialog[open]") || el.closest("[data-gk-mode], .gk-dark, [data-gk-theme]") || doc.body;
  }

  function sharedBubble(host) {
    if (!shared) {
      shared = doc.createElement("div");
      shared.className = "gk-tip";
      shared.setAttribute("role", "tooltip");
      shared.hidden = true;
    }
    if (shared.parentNode !== host) host.appendChild(shared);
    return shared;
  }

  function fill(bubble, content, o) {
    bubble.textContent = "";
    if (typeof content === "string") bubble.appendChild(doc.createTextNode(content));
    else if (content) bubble.appendChild(content);
    if (o.kbd) {
      var k = doc.createElement("kbd");
      k.textContent = o.kbd;
      bubble.appendChild(k);
    }
    bubble.appendChild(arrowEl());
  }

  function look(bubble, o) {
    if (o.tone) bubble.setAttribute("data-gk-tone", o.tone);
    else bubble.removeAttribute("data-gk-tone");
    var max = o.max || (o.wrap ? "280px" : "");
    bubble.style.maxWidth = max ? "min(" + max + ", calc(100vw - " + 2 * EDGE + "px))" : "";
  }

  // ── Placement ─────────────────────────────────────────────────────────

  /** Does this box hold position:fixed descendants (and so clip them, when it clips)? */
  function holdsFixed(s) {
    return s.transform !== "none" || s.filter !== "none" || (s.backdropFilter && s.backdropFilter !== "none") ||
      s.perspective !== "none" || /paint|layout|strict|content/.test(s.contain || "") ||
      /transform|filter|perspective/.test(s.willChange || "");
  }

  function viewport() {
    var de = doc.documentElement;
    return { left: 0, top: 0, right: de.clientWidth || window.innerWidth, bottom: de.clientHeight || window.innerHeight };
  }

  /**
   * The room a bubble may use: the window, narrowed by every box around the trigger that
   * scrolls (to its visible part) and, for a bubble that stays in place, by a box that holds it
   * and clips. With all = true by every box that clips at all — what "can the trigger still be
   * seen" asks. Stops at a fixed box: what lies outside it does not scroll it away.
   */
  function areaOf(el, all, inPlace) {
    var de = doc.documentElement;
    var b = viewport();
    for (var p = el.parentElement; p && p !== doc.body && p !== de; p = p.parentElement) {
      var s = getComputedStyle(p);
      var ov = s.overflowX + " " + s.overflowY;
      if (/auto|scroll|hidden|clip/.test(ov) && (all || (inPlace && holdsFixed(s)) ||
          (/auto|scroll/.test(ov) && (p.scrollHeight > p.clientHeight + 1 || p.scrollWidth > p.clientWidth + 1)))) {
        var r = p.getBoundingClientRect();
        var l = r.left + p.clientLeft, t = r.top + p.clientTop;
        b = {
          left: Math.max(b.left, l), top: Math.max(b.top, t),
          right: Math.min(b.right, l + p.clientWidth), bottom: Math.min(b.bottom, t + p.clientHeight),
        };
      }
      if (s.position === "fixed") break;
    }
    return b;
  }

  function room(side, r, box) {
    return side === "top" ? r.top - box.top : side === "bottom" ? box.bottom - r.bottom
      : side === "left" ? r.left - box.left : box.right - r.right;
  }

  function fits(side, r, w, h, box) {
    var vertical = side === "top" || side === "bottom";
    var need = (vertical ? h : w) + GAP + EDGE;
    var cross = vertical ? box.right - box.left - 2 * EDGE >= w : box.bottom - box.top - 2 * EDGE >= h;
    return room(side, r, box) >= need && cross;
  }

  /** The sides to try, best first: the preferred, its opposite, then the other two by room.
   *  'auto': above or below, whichever has more room, then right or left. */
  function order(pos, r, box) {
    var byRoom = function (a, b) { return room(a, r, box) >= room(b, r, box) ? [a, b] : [b, a]; };
    if (pos === "auto") return byRoom("top", "bottom").concat(byRoom("right", "left"));
    var vertical = pos === "top" || pos === "bottom";
    return [pos, OPPOSITE[pos]].concat(vertical ? byRoom("right", "left") : byRoom("top", "bottom"));
  }

  function chooseSide(pos, r, w, h, box) {
    var sides = order(pos, r, box);
    for (var i = 0; i < sides.length; i++) if (fits(sides[i], r, w, h, box)) return sides[i];
    return null;
  }

  /**
   * Put a bubble beside its trigger. It is measured where it stands at left/top 0: inside a box
   * that holds position:fixed (a transform, a filter) that origin is the box, not the window, and
   * the difference is taken off — that is what keeps a toggletip in a modal where it belongs.
   */
  function place(c) {
    var el = c.el, b = c.bubble, o = c.o;
    if (!b.hasAttribute("data-gk-side")) b.setAttribute("data-gk-side", "top");
    b.style.left = "0px";
    b.style.top = "0px";
    b.style.width = "";
    var origin = b.getBoundingClientRect();
    // Whole pixels: the width is set to this below, and a fraction rounded the other way put a
    // bubble at the edge half a pixel past it.
    var w = Math.ceil(origin.width), h = Math.ceil(origin.height);
    var r = el.getBoundingClientRect();
    var box = areaOf(el, false, c === toggle);
    var side = chooseSide(o.pos, r, w, h, box);
    if (!side) {
      // Nothing fits the box: the window alone, then the side with the most room.
      box = viewport();
      side = chooseSide(o.pos, r, w, h, box);
      if (!side) {
        var best = -Infinity;
        order(o.pos, r, box).forEach(function (s) {
          var spare = room(s, r, box) - (s === "top" || s === "bottom" ? h : w);
          if (spare > best) { best = spare; side = s; }
        });
      }
    }
    var x, y;
    if (side === "top" || side === "bottom") {
      y = side === "top" ? r.top - GAP - h : r.bottom + GAP;
      x = clamp(r.left + r.width / 2 - w / 2, box.left + EDGE, box.right - EDGE - w);
    } else {
      x = side === "left" ? r.left - GAP - w : r.right + GAP;
      y = clamp(r.top + r.height / 2 - h / 2, box.top + EDGE, box.bottom - EDGE - h);
    }
    b.setAttribute("data-gk-side", side);
    // The width it was measured at: near an edge a fixed box would otherwise shrink and wrap its
    // text again, taller than it was measured.
    b.style.width = w + "px";
    b.style.left = Math.round(x - origin.left) + "px";
    b.style.top = Math.round(y - origin.top) + "px";

    var a = b.querySelector(":scope > .gk-tip-arrow");
    if (a) {
      var border = parseFloat(getComputedStyle(b).borderLeftWidth) || 0;
      // At the middle of the trigger, wherever the shift put the bubble — never past a corner.
      if (side === "top" || side === "bottom") {
        a.style.top = "";
        a.style.left = Math.round(clamp(r.left + r.width / 2 - x, 12, w - 12) - 5 - border) + "px";
      } else {
        a.style.left = "";
        a.style.top = Math.round(clamp(r.top + r.height / 2 - y, 10, h - 10) - 5 - border) + "px";
      }
    }
  }

  /** Can the trigger still be seen — in the window and in every box around it? */
  function seen(el) {
    if (!el.isConnected) return false;
    var r = el.getBoundingClientRect();
    if (!r.width && !r.height) return false;
    var box = areaOf(el, true, false);
    return r.right > box.left && r.left < box.right && r.bottom > box.top && r.top < box.bottom;
  }

  // ── Open and close ────────────────────────────────────────────────────

  function cancelOpen() {
    clearTimeout(openTimer);
    openTimer = 0;
    pending = null;
  }
  function cancelClose() {
    clearTimeout(closeTimer);
    closeTimer = 0;
  }

  function open(t, reason) {
    if (t.kind === "toggle") return openToggle(t.el);
    cancelOpen();
    if (cur && cur.el === t.el) {
      cur.reasons[reason] = true;
      cancelClose();
      return;
    }
    close();
    var o = optionsOf(t.el), bubble;
    if (t.kind === "rich") {
      bubble = prepRich(t.el);
      if (!bubble) return;
    } else {
      var text;
      if (t.kind === "tip") {
        prepTip(t.el);
        text = attr(t.el, "data-gk-tip");
      } else {
        prepPlain(t.el);
        text = attr(t.el, "data-gk-tooltip").trim();
      }
      bubble = sharedBubble(hostFor(t.el));
      fill(bubble, text, o);
      bubble.hidden = false;
    }
    look(bubble, o);
    cur = { el: t.el, kind: t.kind, bubble: bubble, o: o, reasons: {} };
    cur.reasons[reason] = true;
    place(cur);
  }

  function close() {
    cancelClose();
    if (!cur) return;
    var c = cur;
    cur = null;
    c.bubble.removeAttribute("data-gk-side");
    if (c.kind === "rich") c.bubble.classList.remove("visible");
    else c.bubble.hidden = true;
    lastClosed = Date.now();
  }

  /** A reason to stay open is gone; close when none is left — after the grace, for the pointer. */
  function release(reason, delay) {
    if (!cur || !cur.reasons[reason]) return;
    var c = cur;
    var go = function () {
      if (cur !== c) return;
      delete c.reasons[reason];
      if (!Object.keys(c.reasons).length) close();
    };
    if (!delay) return go();
    cancelClose();
    closeTimer = setTimeout(go, delay);
  }

  function openToggle(el) {
    var st = prepToggle(el);
    if (toggle && toggle.el === el) return;
    closeToggle();
    cancelOpen();
    close();
    var o = optionsOf(el);
    var t = { el: el, bubble: st.bubble, o: o, src: null, home: null, wasHidden: false };
    toggle = t;
    el.setAttribute("aria-expanded", "true");
    var show = function () {
      if (toggle !== t) return;
      var spec = attr(el, "data-gk-toggletip").trim();
      var src = ref(spec), content;
      if (src && src.tagName === "TEMPLATE") {
        content = src.content.cloneNode(true);
      } else if (src) {
        // The element itself moves in — its links keep their listeners, its ids stay unique —
        // and goes back where it was on close.
        t.src = src;
        t.home = doc.createComment("gk-toggletip");
        src.parentNode.insertBefore(t.home, src);
        t.wasHidden = src.hidden;
        src.hidden = false;
        content = src;
      } else {
        content = spec;
      }
      fill(t.bubble, content, o);
      look(t.bubble, o);
      place(t);
    };
    var wait = 150 - (Date.now() - st.born);
    if (wait > 0) setTimeout(show, wait);
    else show();
  }

  function closeToggle(refocus) {
    if (!toggle) return;
    var t = toggle;
    toggle = null;
    t.el.setAttribute("aria-expanded", "false");
    if (t.src) {
      t.src.hidden = t.wasHidden;
      if (t.home && t.home.parentNode) t.home.parentNode.replaceChild(t.src, t.home);
      else if (t.src.parentNode === t.bubble) t.bubble.removeChild(t.src);
    }
    t.bubble.textContent = "";
    t.bubble.removeAttribute("data-gk-side");
    t.bubble.removeAttribute("data-gk-tone");
    t.bubble.removeAttribute("style");
    if (refocus && t.el.isConnected) {
      try { t.el.focus({ preventScroll: true }); } catch (e) { t.el.focus(); }
    }
  }

  function toggleOf(el) {
    if (toggle && toggle.el === el) closeToggle();
    else openToggle(el);
  }

  /** Whatever is open whose trigger has left the page (a rebuilt table) closes with it. */
  function dropDetached() {
    if (cur && !cur.el.isConnected) close();
    if (toggle && !toggle.el.isConnected) closeToggle();
  }

  // ── Listeners: delegated, so content that arrives later needs no binding to work ──

  doc.addEventListener("pointerover", function (e) {
    if (e.pointerType === "touch") return;
    dropDetached();
    if (cur && cur.bubble.contains(e.target)) {
      cur.reasons.hover = true;
      cancelClose();
      return;
    }
    var t = triggerOf(e.target);
    if (cur && t && cur.el === t.el) {
      cur.reasons.hover = true;
      cancelClose();
      // An older copy of this file, loaded late, may still move a rich tooltip on mouseenter.
      if (t.kind === "rich") requestAnimationFrame(function () { if (cur && cur.el === t.el) place(cur); });
      return;
    }
    if (!t || t.kind === "toggle" || suppressed === t.el) return;
    if (pending && pending.el === t.el) return;
    cancelOpen();
    // The title goes now, before the browser starts its own popup.
    if (t.kind === "tip") prepTip(t.el);
    var o = optionsOf(t.el);
    var warm = (cur && cur.reasons.hover) || Date.now() - lastClosed < WARM;
    if (warm || o.delay === 0) return open(t, "hover");
    pending = t;
    openTimer = setTimeout(function () {
      var p = pending;
      pending = null;
      if (p && p.el.isConnected) open(p, "hover");
    }, o.delay);
  }, true);

  doc.addEventListener("pointerout", function (e) {
    if (e.pointerType === "touch") return;
    var to = e.relatedTarget;
    if (pending && !(to && pending.el.contains(to))) cancelOpen();
    if (suppressed && !(to && suppressed.contains(to))) suppressed = null;
    if (!cur || !cur.reasons.hover || !inside(cur, e.target) || inside(cur, to)) return;
    release("hover", CLOSE_DELAY);
  }, true);

  doc.addEventListener("focusin", function (e) {
    var t = triggerOf(e.target);
    if (!t || t.kind === "toggle") return;
    // The trigger itself, or the control a wrapping trigger holds — not every field that happens
    // to sit inside an element with a title.
    if (t.el !== e.target && t.el.querySelector(FOCUSABLE) !== e.target) return;
    // Only a focus a person can see asks for a tooltip: Tab does, a mouse click on a button
    // does not (it would cover what was just clicked).
    if (!focusVisible(e.target)) return;
    suppressed = null;
    open(t, "focus");
  }, true);

  doc.addEventListener("focusout", function (e) {
    var to = e.relatedTarget;
    if (cur && cur.reasons.focus && inside(cur, e.target) && !inside(cur, to)) release("focus");
    // A toggletip closes when the focus moves on past it — not when it goes nowhere (a click
    // on the bubble's own text takes the focus to the body).
    if (toggle && to && inside(toggle, e.target) && !inside(toggle, to)) closeToggle();
  }, true);

  doc.addEventListener("pointerdown", function (e) {
    lastPointer = e.pointerType || "mouse";
    dropDetached();
    if (cur && !inside(cur, e.target)) close();
    else if (cur && lastPointer !== "touch" && cur.el.contains(e.target) && isControl(e.target, null)) {
      // A click on a button closes its tooltip, as GK.tip always did: what it said is done.
      suppressed = cur.el;
      close();
    }
  }, true);

  doc.addEventListener("keydown", function (e) {
    lastPointer = "keyboard";
    // A toggletip on an element that is not a button: Enter and Space, as on a button.
    if ((e.key === "Enter" || e.key === " ") && e.target && e.target.matches &&
        e.target.matches("[data-gk-toggletip]:not(button, a[href], input, select, textarea, summary)")) {
      e.preventDefault();
      toggleOf(e.target);
    }
  }, true);

  // Escape is claimed (preventDefault) when it closed something the keyboard was on: a modal or
  // a sheet ignores a key that was handled, so it stays, and the next Escape closes it. A
  // tooltip the mouse merely rests on closes without taking the key.
  window.addEventListener("keydown", function (e) {
    if (e.key !== "Escape" && e.key !== "Esc") return;
    if (toggle) {
      closeToggle(inside(toggle, doc.activeElement));
      e.preventDefault();
      return;
    }
    if (cur) {
      var keyboard = !!cur.reasons.focus || cur.el.contains(doc.activeElement);
      suppressed = cur.el;
      close();
      if (keyboard) e.preventDefault();
    }
  }, true);

  doc.addEventListener("click", function (e) {
    var tg = e.target && e.target.closest ? e.target.closest("[data-gk-toggletip]") : null;
    if (tg) {
      toggleOf(tg);
      return;
    }
    if (toggle && !inside(toggle, e.target)) closeToggle();
    if (lastPointer !== "touch") return;
    // A tap. On a trigger that is only a trigger it shows (or, the second time, hides) the
    // tooltip; on a button or a link it does nothing here — the click is theirs, untouched.
    var t = triggerOf(e.target);
    if (!t || t.kind === "toggle" || isControl(e.target, t.el)) return;
    if (cur && cur.el === t.el) close();
    else open(t, "touch");
  });

  var frame = 0;
  function follow() {
    if (frame || (!cur && !toggle)) return;
    frame = requestAnimationFrame(function () {
      frame = 0;
      if (cur) { if (seen(cur.el)) place(cur); else close(); }
      if (toggle) { if (seen(toggle.el)) place(toggle); else closeToggle(); }
    });
  }
  window.addEventListener("scroll", follow, true);
  window.addEventListener("resize", follow);
  window.addEventListener("blur", function () {
    cancelOpen();
    if (cur && !cur.reasons.focus) close();
  });

  // ── API ───────────────────────────────────────────────────────────────

  GK.tooltip._engine = true;
  /** Bind the tooltips in root (default: the document). Idempotent; GK.initContent calls it. */
  GK.tooltip.init = function (root) {
    if (doc.body) prepare(root);
  };
  /** Open the tooltip (or toggletip) of an element now, without a delay. */
  GK.tooltip.show = function (el) {
    var k = el && el.nodeType === 1 ? kindOf(el) : null;
    if (k) open({ el: el, kind: k }, "api");
  };
  /** Close what is open: the tooltip and the toggletip. */
  GK.tooltip.hide = function () {
    cancelOpen();
    close();
    closeToggle();
  };
  /** Place what is open again — after its text or the layout around it changed. */
  GK.tooltip.update = function () {
    if (cur) place(cur);
    if (toggle) place(toggle);
  };

  /*
   * GK.tip, on the same engine: the title handling (data-gk-tip, data-gk-tip-off, \n for a line
   * break) is unchanged, the popup is this one. When this file was added to a page that was
   * already complete, the old GK.tip.init has run and its listeners stay on the document — they
   * cannot be removed. They decide through GK.tip.cur and show through GK.tip.show: cur now
   * always answers "something else", so their timer never calls show, and hide only closes a
   * title tooltip this API or the mouse opened (their scroll, click and keydown close it, as
   * they always did).
   */
  var elsewhere = { contains: function () { return true; } };
  try {
    Object.defineProperty(GK.tip, "cur", { configurable: true, get: function () { return elsewhere; }, set: function () {} });
  } catch (e) { /* a frozen GK.tip keeps its own */ }
  GK.tip.show = function (target) {
    if (target && target.nodeType === 1 && (target.hasAttribute("title") || target.hasAttribute("data-gk-tip"))) {
      open({ el: target, kind: "tip" }, "api");
    }
  };
  GK.tip.hide = function () {
    clearTimeout(GK.tip.timer);
    GK.tip.timer = null;
    if (cur && cur.kind === "tip" && (cur.reasons.api || cur.reasons.hover)) close();
  };
  GK.tip.init = function () {};

  // ── Tables ────────────────────────────────────────────────────────────

  /** A row button's name, as the rebuild gives it: 'aria', 'title', the catalogue, the name. */
  function rowButtonName(name, b) {
    if (b.aria) return String(b.aria);
    if (b.title) return String(b.title);
    var lang = window.GK_LANG || {};
    if (lang["action_" + name]) return lang["action_" + name];
    var s = String(name).replace(/[_-]+/g, " ");
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  /** Copy the attributes of a string like ' data-gk-tooltip="…" data-gk-tooltip-pos="…"'. */
  function setAttrs(el, attrs) {
    var tpl = doc.createElement("template");
    tpl.innerHTML = "<b" + attrs + "></b>";
    var b = tpl.content.firstChild;
    if (!b) return;
    for (var i = 0; i < b.attributes.length; i++) el.setAttribute(b.attributes[i].name, b.attributes[i].value);
  }

  if (GK.table && typeof GK.table.renderStatic === "function") {
    var renderStatic = GK.table.renderStatic;
    /*
     * The rebuild of a setData() table writes its row buttons and header cells itself and knows
     * no 'tooltip'. Table.php puts its own markup into the data block (tooltipAttrs on a button,
     * tooltipHtml on a column). A button with one is handed to the rebuild with a marked title,
     * which is then swapped for those attributes; each column's help icon goes back into its
     * header cell. The rebuild is not changed, and a table without tooltips is not touched.
     */
    GK.table.renderStatic = function (wrap) {
      var data = wrap && wrap._gkData;
      var buttons = data && data.buttons;
      var marked = null;
      if (buttons && typeof buttons === "object") {
        Object.keys(buttons).forEach(function (name) {
          var b = buttons[name];
          if (!b || !b.tooltipAttrs) return;
          marked = marked || Object.assign({}, buttons);
          marked[name] = Object.assign({}, b, { title: MARK + name, aria: rowButtonName(name, b) });
        });
      }
      if (marked) data.buttons = marked;
      var result;
      try {
        result = renderStatic.apply(this, arguments);
      } finally {
        if (marked) data.buttons = buttons;
      }
      if (data && wrap.querySelectorAll) {
        if (marked) {
          wrap.querySelectorAll('[title^="' + MARK + '"]').forEach(function (el) {
            var b = buttons[attr(el, "title").slice(MARK.length)];
            el.removeAttribute("title");
            if (b && b.tooltipAttrs) setAttrs(el, b.tooltipAttrs);
          });
        }
        var cols = data.columns || {};
        var keys = Object.keys(cols);
        if (keys.some(function (k) { return cols[k] && cols[k].tooltipHtml; })) {
          var table = wrap.querySelector("table.gk-table");
          var ths = table ? Array.prototype.filter.call(table.querySelectorAll(":scope > thead > tr > th"), function (th) {
            return !th.matches(".gk-cb-col, .gk-actions-col");
          }) : [];
          keys.forEach(function (k, i) {
            var th = ths[i];
            if (th && cols[k] && cols[k].tooltipHtml && !th.querySelector(".gk-help")) {
              th.insertAdjacentHTML("beforeend", cols[k].tooltipHtml);
            }
          });
        }
        dropDetached();
        prepare(wrap);
      }
      return result;
    };
  }
  if (GK.table && typeof GK.table.init === "function") {
    var tableInit = GK.table.init;
    GK.table.init = function (root) {
      var result = tableInit.apply(this, arguments);
      if (doc.body) prepare(root);
      return result;
    };
  }

  _gkReady(function () { prepare(doc); });
})();

// === 1.113.0 — tabs in a form (Form::tab) ===
/*
 * Form::tab() renders the authored tab markup inside a <form>, with a hidden input in front of it:
 *
 *   <input type="hidden" name="_gk_tab" value="address" data-gk-form-tab data-gk-tab-error="{n} error"
 *          data-gk-tab-errors="{n} errors" [data-gk-tab-remember] [data-gk-tab-chosen]>
 *   <div class="gk-tabs"> <div class="gk-tab-nav …">…</div> <div class="gk-tab-panel" data-tab="…">…</div> … </div>
 *
 * Switching is the existing tab script's job (GK.tabsMarkup and its click handler). This block adds what a
 * form needs on top of it:
 *   1. A submit the browser blocks for a field in a hidden tab opens that tab first. The browser fires
 *      `invalid` at every rejected control in tree order and only then shows its message at the first one
 *      it can focus — a control in a display:none panel is not one, so the submit failed in silence. The
 *      tab of the FIRST rejected control is opened while those events run, and the browser itself then
 *      focuses the control and shows its own message. Once per submit: a second switch would show a
 *      different tab from the one the message is in. Only for a real submit (a submit button, Enter,
 *      requestSubmit(), reportValidity()) — a page that calls checkValidity() to grey out a button would
 *      otherwise make the tabs jump while somebody types.
 *   2. An error badge on every tab with something wrong in it — a message under a field (server, AJAX), or,
 *      after the first submit, a field the browser rejects — with the count in words, kept current on
 *      input and change and gone when the tab is clean. PHP writes the same badge for errors(), so it is
 *      there without this block.
 *   3. The chosen tab goes into the hidden input, so a form shown again after a failed save opens where
 *      the user was (Form reads it back through values()). With tabsRemember() it is also kept per form id
 *      in localStorage and opened next time, unless the page chose a tab (an error, a posted tab, activeTab()).
 *   4. Errors an AJAX form gets back (GK.form writes them under the fields): when the first of them is in a
 *      hidden tab, that tab opens and the field takes the focus GK.form could not give it.
 *   5. A required rich-text field the check further up (data-gk-required-rich) finds empty in a hidden tab:
 *      its tab opens, so the toast points at something on screen.
 *
 * A copy of this file without the block (the PawBot dashboard keeps its own) still switches the tabs and
 * shows the badges PHP wrote; the jump, the live badges, remembering and following the user's clicks into
 * the hidden input are what it lacks. Nothing existing is changed: GK.initContent is wrapped, as 1.105.0 does.
 */
(function () {
  "use strict";
  if (!window.GK) return;

  var MARK = "input[data-gk-form-tab]";
  var STORE = "gk-form-tab-";

  function own(frame, sel) {
    return Array.prototype.filter.call(frame.querySelectorAll(sel), function (el) {
      return el.closest(".gk-tabs") === frame;
    });
  }
  function buttonFor(st, key) {
    return own(st.frame, ".gk-tab-btn").filter(function (b) { return b.dataset.tab === key; })[0] || null;
  }
  function on(el) { return el.classList.contains("gk-active"); }
  function enabled(b) { return !b.disabled && b.getAttribute("aria-disabled") !== "true"; }

  /** The panel of THIS form's tabs that holds el — not one of a page's own tabs around the form. */
  function panelOf(st, el) {
    var p = el.closest(".gk-tab-panel");
    return p && p.closest(".gk-tabs") === st.frame ? p : null;
  }

  function word(st, n) {
    var one = st.input.getAttribute("data-gk-tab-error") || "{n} error";
    var many = st.input.getAttribute("data-gk-tab-errors") || "{n} errors";
    return (n === 1 ? one : many).replace("{n}", String(n));
  }

  function read(form) {
    try { return window.localStorage.getItem(STORE + form.id); } catch (e) { return null; }
  }
  function write(form, key) {
    try { window.localStorage.setItem(STORE + form.id, key); } catch (e) { /* private mode, blocked storage */ }
  }

  /** The hidden input follows the tab on screen; with tabsRemember() so does the browser's note. */
  function sync(st) {
    var b = own(st.frame, ".gk-tab-btn").filter(on)[0];
    if (!b) return;
    st.input.value = b.dataset.tab;
    if (st.remember && st.form.id) write(st.form, b.dataset.tab);
  }

  /**
   * Open a tab the way a user does: GK.tabs.select() where a newer script has it, else a click on the tab.
   * A disabled tab takes no click, and a field in it can still hold the submit up — then the classes are set
   * here, exactly as the click handler sets them, because a message nobody can reach is worse.
   */
  function open(st, b) {
    if (!b || on(b)) return;
    if (GK.tabs && typeof GK.tabs.select === "function") {
      try { GK.tabs.select(st.frame, b.dataset.tab); } catch (e) { /* an older or a different signature */ }
    }
    if (!on(b)) b.click();
    if (!on(b)) {
      own(st.frame, ".gk-tab-btn").forEach(function (x) {
        x.classList.toggle("gk-active", x === b);
        if (x.getAttribute("role") === "tab") {
          x.setAttribute("aria-selected", x === b ? "true" : "false");
          x.tabIndex = x === b ? 0 : -1;
        }
      });
      own(st.frame, ".gk-tab-panel").forEach(function (p) {
        p.classList.toggle("gk-active", p.dataset.tab === b.dataset.tab);
      });
    }
    sync(st);
  }

  function richEmpty(el) {
    return String(el.value || "").replace(/<[^>]*>/g, "").replace(/&nbsp;|\s/g, "") === "";
  }

  /** A field counts once: a message under it, or — after the first submit — a control the browser rejects. */
  function wrong(st, field) {
    var slot = field.querySelector(".gk-field-error");
    if (slot && slot.textContent.trim() !== "") return true;
    if (!st.checked) return false;
    var bad = Array.prototype.some.call(field.querySelectorAll("input, select, textarea"), function (c) {
      return c.willValidate && c.validity && !c.validity.valid;
    });
    if (bad) return true;
    var rich = field.querySelector("[data-gk-required-rich]");
    return !!rich && richEmpty(rich);
  }

  /** The fields of the form in DOM order, without those showIf has put away (a sheet around the form may be hidden too). */
  function fields(st) {
    return Array.prototype.filter.call(st.form.querySelectorAll(".gk-field"), function (f) {
      var away = f.closest("[hidden]");
      return !away || !st.form.contains(away);
    });
  }

  function badges(st) {
    var count = {};
    fields(st).forEach(function (f) {
      var p = panelOf(st, f);
      if (p && wrong(st, f)) count[p.dataset.tab] = (count[p.dataset.tab] || 0) + 1;
    });
    own(st.frame, ".gk-tab-btn").forEach(function (b) {
      var n = count[b.dataset.tab] || 0;
      var badge = b.querySelector(".gk-tab-badge-error");
      if (!n) {
        if (badge) badge.remove();
        return;
      }
      if (!badge) {
        badge = document.createElement("span");
        badge.className = "gk-tab-badge gk-tab-badge-error";
        b.appendChild(badge);
      }
      var text = word(st, n);
      if (badge.textContent !== text) badge.textContent = text;
    });
  }

  function later(st) {
    clearTimeout(st.timer);
    st.timer = setTimeout(function () { badges(st); }, 0);
  }

  /** What GK.form would have focused (the control it marked), else the first control a person can reach. */
  function focusField(field) {
    var c = field.querySelector(".gk-has-error, [aria-invalid='true']") ||
      field.querySelector("input:not([type=hidden]):not([tabindex='-1']), select, textarea, [tabindex='0']");
    if (c && typeof c.focus === "function") c.focus();
  }

  /** A submit is coming: set by a submit button's click (Enter in a field is one), requestSubmit() and reportValidity(). */
  function intend(form) {
    form._gkTabIntent = true;
    clearTimeout(form._gkTabIntentT);
    form._gkTabIntentT = setTimeout(function () { form._gkTabIntent = false; }, 0);
  }

  function setup(form) {
    if (!form || form.nodeName !== "FORM") return null;
    if (form._gkFormTabs !== undefined) return form._gkFormTabs;
    var input = form.querySelector(MARK);
    var frame = input && input.nextElementSibling;
    if (!frame || !frame.classList.contains("gk-tabs")) {
      form._gkFormTabs = null;
      return null;
    }
    var st = {
      form: form,
      input: input,
      frame: frame,
      remember: input.hasAttribute("data-gk-tab-remember"),
      checked: false,
      round: false,
      timer: 0,
    };
    form._gkFormTabs = st;

    // A remembered tab, unless the page chose one: an error, the tab posted back, activeTab().
    if (st.remember && !input.hasAttribute("data-gk-tab-chosen")) {
      var b = buttonFor(st, read(form) || "");
      if (b && enabled(b)) open(st, b);
    }
    // A script that validates by itself is as much a submit as a click on the button.
    ["requestSubmit", "reportValidity"].forEach(function (name) {
      var native = form[name];
      if (typeof native !== "function") return;
      form[name] = function () {
        intend(form);
        return native.apply(form, arguments);
      };
    });
    // Messages written under the fields by script (GK.form's AJAX answer): count them, and open the tab of
    // the first one when it is hidden — but only when that first one is among those just written, so a page
    // that writes a message under the field the user is in does not pull them away to an older one.
    if (window.MutationObserver) {
      var mo = new MutationObserver(function (records) {
        var fresh = [];
        records.forEach(function (r) {
          var el = r.target.nodeType === 1 ? r.target : r.target.parentNode;
          var slot = el && el.closest ? el.closest(".gk-field-error") : null;
          if (slot && slot.textContent.trim() !== "" && fresh.indexOf(slot) < 0) fresh.push(slot);
        });
        badges(st);
        if (!fresh.length) return;
        var first = fields(st).filter(function (f) {
          var s = f.querySelector(".gk-field-error");
          return s && s.textContent.trim() !== "";
        })[0];
        if (!first || fresh.indexOf(first.querySelector(".gk-field-error")) < 0) return;
        var p = panelOf(st, first);
        if (!p || on(p)) return;
        open(st, buttonFor(st, p.dataset.tab));
        focusField(first);
      });
      form.querySelectorAll(".gk-field-error").forEach(function (slot) {
        mo.observe(slot, { childList: true, characterData: true, subtree: true });
      });
    }
    badges(st);
    return st;
  }

  function init(root) {
    root = root && root.querySelectorAll ? root : document;
    var inputs = Array.prototype.slice.call(root.querySelectorAll(MARK));
    if (root.matches && root.matches(MARK)) inputs.push(root);
    inputs.forEach(function (input) { setup(input.form); });
  }

  // 1 ── the first control the browser rejects, in a hidden tab: open it before the browser looks for one to focus.
  // Enter in a field is covered here too: the browser submits by a click on the form's first submit button.
  document.addEventListener(
    "click",
    function (e) {
      var b = e.target.closest ? e.target.closest("button, input") : null;
      if (b && b.form && (b.type === "submit" || b.type === "image")) intend(b.form);
    },
    true,
  );
  document.addEventListener(
    "invalid",
    function (e) {
      var form = e.target.form;
      var st = form ? setup(form) : null;
      if (!st || !form._gkTabIntent) return;
      st.checked = true;
      later(st);
      if (st.round) return;
      st.round = true;
      setTimeout(function () { st.round = false; }, 0);
      var p = panelOf(st, e.target);
      if (p && !on(p)) open(st, buttonFor(st, p.dataset.tab));
    },
    true,
  );

  // 5 ── the rich-text check (capture, further up) stopped the submit: open the tab it points at.
  document.addEventListener(
    "submit",
    function (e) {
      var st = setup(e.target);
      if (!st) return;
      st.checked = true;
      later(st);
      if (!e.defaultPrevented) return;
      var missing = Array.prototype.filter.call(st.form.querySelectorAll("[data-gk-required-rich]"), richEmpty)[0];
      var p = missing ? panelOf(st, missing) : null;
      if (!p || on(p)) return;
      open(st, buttonFor(st, p.dataset.tab));
      var wrap = missing.previousElementSibling;
      var editable = wrap && wrap.querySelector(".ck-editor__editable");
      if (editable) editable.focus();
      else if (wrap && wrap.scrollIntoView) wrap.scrollIntoView({ block: "center" });
    },
    true,
  );

  // 2 ── live badges while the user puts things right (after showIf has shown or hidden its fields).
  ["input", "change"].forEach(function (type) {
    document.addEventListener(type, function (e) {
      var form = e.target && e.target.form;
      var st = form ? setup(form) : null;
      if (st) badges(st);
    });
  });

  // 3 ── a tab the user chose goes into the hidden input; gk:tabchange covers a switch made by GK.tabs.select().
  function chosen(e) {
    var t = e.target && e.target.closest ? e.target : null;
    if (!t || (e.type === "click" && !t.closest(".gk-tab-btn"))) return;
    var el = t.closest(".gk-tabs");
    var form = el ? el.closest("form") : null;
    var st = form ? setup(form) : null;
    if (st && el === st.frame) sync(st);
  }
  document.addEventListener("click", chosen);
  document.addEventListener("gk:tabchange", chosen);

  GK.formTabs = {
    init: init,
    /** Count the badges again — after a script changed what is valid (setCustomValidity) or wrote a message. */
    refresh: function (form) {
      var st = setup(form);
      if (st) badges(st);
    },
  };

  // Forms that arrive later (a modal, AJAX navigation) go through GK.initContent; the first pass is now.
  var original = GK.initContent;
  if (typeof original === "function") {
    GK.initContent = function (root) {
      var result = original.apply(GK, arguments);
      init(root || document);
      return result;
    };
  }
  _gkReady(function () { init(document); });
})();

// === 1.114.0 — colour system: theme switcher ===
/*
 * The mode control of Theme::switcher() as one group of three: light | dark | auto (follow the
 * system). The PHP renders the light/dark toggle as it always did and the auto button hidden; this
 * block shows the auto button and marks the group (data-gk-mode-ready, data-gk-mode-state), and the
 * stylesheet then draws the two glyphs of the toggle as two segments with one thumb under the mode
 * in effect. A copy of gridkit.js without this block (the PawBot dashboard keeps its own) keeps the
 * plain toggle, which works exactly as before.
 *
 * Auto is stored beside the mode, as "gk-mode-auto" = "1" in the namespace of GK.theme (scope).
 * "gk-mode" keeps the light or dark in effect, so GK.theme.restore() — which knows nothing of auto —
 * still sets a sensible mode on load, and this block then corrects it to what the system says now
 * and follows it while the page is open.
 *
 * A click on one of the two glyphs chooses that mode (the base handler would toggle, which on the
 * glyph already in effect is the opposite of what was clicked); Enter or Space on the toggle still
 * toggles. Choosing a mode, or GK.theme.toggleMode() from the page's own code, ends auto.
 *
 * GK.theme.setMode("light" | "dark" | "auto") does the same from code.
 */
(function () {
  var GK = window.GK;
  if (!GK || !GK.theme || typeof GK.theme._key !== "function") return;
  var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

  function autoKey() {
    return GK.theme._key("gk-mode-auto");
  }
  function isAuto() {
    try {
      return window.localStorage.getItem(autoKey()) === "1";
    } catch (e) {
      return false;
    }
  }
  function storeAuto(on) {
    try {
      if (on) window.localStorage.setItem(autoKey(), "1");
      else window.localStorage.removeItem(autoKey());
    } catch (e) {}
  }
  function systemMode() {
    return media && media.matches ? "dark" : "light";
  }
  function current() {
    return document.body && document.body.dataset.gkMode === "dark" ? "dark" : "light";
  }

  /** Every switcher on the page: ready, which of the three is in effect, the auto button shown and pressed or not. */
  function mark() {
    var auto = isAuto();
    var state = auto ? "auto" : current();
    document.querySelectorAll(".gk-theme-switcher").forEach(function (sw) {
      var btn = sw.querySelector("[data-gk-mode-auto]");
      if (!btn) return;
      btn.hidden = false;
      btn.setAttribute("aria-pressed", auto ? "true" : "false");
      sw.setAttribute("data-gk-mode-ready", "");
      sw.setAttribute("data-gk-mode-state", state);
    });
  }

  /** Light or dark, written the way GK.theme writes it, so restore() finds it on the next page. */
  function apply(mode) {
    if (!document.body) return;
    document.body.dataset.gkMode = mode;
    try {
      window.localStorage.setItem(GK.theme._key("gk-mode"), mode);
    } catch (e) {}
    if (typeof GK.theme._markMode === "function") GK.theme._markMode(mode);
    mark();
  }

  var api = {
    /** "light", "dark" or "auto" (follow the system and keep following it). */
    setMode: function (mode) {
      if (mode === "auto") {
        storeAuto(true);
        apply(systemMode());
        return;
      }
      storeAuto(false);
      apply(mode === "dark" ? "dark" : "light");
    },
  };
  GK.theme.setMode = api.setMode;

  // The page's own call (and the base click handler, which calls it) ends auto.
  var toggle = GK.theme.toggleMode;
  if (typeof toggle === "function") {
    GK.theme.toggleMode = function () {
      storeAuto(false);
      var out = toggle.apply(this, arguments);
      mark();
      return out;
    };
  }

  // Captured before the base handler: a glyph chooses its own mode instead of toggling.
  document.addEventListener(
    "click",
    function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      if (t.closest("[data-gk-mode-auto]")) {
        e.stopImmediatePropagation();
        GK.theme.setMode(isAuto() ? current() : "auto");
        return;
      }
      if (!t.closest(".gk-theme-switcher[data-gk-mode-ready] [data-gk-toggle-mode]")) return;
      var glyph = t.closest(".gk-mode-light") ? "light" : t.closest(".gk-mode-dark") ? "dark" : null;
      if (!glyph) return;
      e.stopImmediatePropagation();
      GK.theme.setMode(glyph);
    },
    true
  );

  if (media) {
    var follow = function () {
      if (isAuto()) apply(systemMode());
    };
    if (typeof media.addEventListener === "function") media.addEventListener("change", follow);
    else if (typeof media.addListener === "function") media.addListener(follow);
  }

  function start() {
    if (isAuto()) apply(systemMode());
    else mark();
  }
  // After GK.theme.restore(), which runs on DOMContentLoaded too and was registered first.
  _gkReady(start);
})();
// === 1.115.0 — permission matrix (GridKit\PermissionMatrix) ===
/*
 * What PermissionMatrix::render() writes is a form: every cell a hidden "0" and a checkbox "1" under
 * perm[role][permission], an inherited or locked cell a disabled checkbox. This block makes it a
 * matrix one works in, and changes nothing that the form posts except through those same fields:
 *
 *   - Inheritance stays live. A role with a parent shows what the parent has as ticked "via <parent>",
 *     not removable there; tick something for the parent and it appears in the child at once. The
 *     inherited checkbox has no name, so the cell posts its hidden 0 (inheritance follows from the
 *     parent and is not stored); disabled becomes aria-disabled, so the arrow keys still reach it.
 *   - `requires`: ticking a permission ticks what it needs (through what that needs); unticking a
 *     permission unticks what needs it, in that role and in the roles that inherit from it. Both are
 *     said in the status line, so nothing changes out of sight.
 *   - Bulk toggles for a row (all roles), a column (one role, the rows in view), a group (one role, or
 *     all roles), each mixed while only some are granted. Ticked → untick what can be unticked;
 *     mixed or empty → tick what can be ticked. Locked and inherited cells are left alone.
 *   - Filter, fold, the count of changes and Reset; changed cells marked until saved (or commit()).
 *   - A grid: one tab stop, the arrow keys move between cells (Home/End, Ctrl+Home/End, PageUp/Down),
 *     Space toggles. On a phone (≤ 768px) one role at a time, chosen in a select.
 *   - gk:permchange on the matrix after every change, cancelable: preventDefault() puts it back.
 *     With saveUrl() each change is POSTed as JSON ({ matrix, changes: {role: {permission: bool}} })
 *     and put back, with a toast, when the answer is not { ok: true }.
 *
 * GK.permMatrix.changes(el), .reset(el), .commit(el) and .init(root). A copy of this file without the
 * block (the PawBot dashboard keeps its own) leaves the form: the controls that need the script stay
 * hidden ([data-gk-perm-js]) and every cell posts as it is ticked.
 */
(function () {
  "use strict";
  if (!window.GK || GK.permMatrix) return;

  function fmt(text, params) {
    text = String(text == null ? "" : text);
    if (params) Object.keys(params).forEach(function (k) { text = text.split("{" + k + "}").join(String(params[k])); });
    return text;
  }

  /** Lower case, accents off: "Lösch" finds "löschen" and "Losch" finds both. */
  function norm(s) {
    return String(s == null ? "" : s).toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
  }

  function shown(el) {
    return !!el && el.getClientRects().length > 0;
  }

  function rootOf(el) {
    if (typeof el === "string") {
      var key = el;
      el = document.getElementById(key) ||
        Array.prototype.find.call(document.querySelectorAll("[data-gk-perm]"), function (r) { return r.getAttribute("data-gk-perm") === key; });
    }
    return el && el.closest ? el.closest("[data-gk-perm]") : null;
  }

  function stateOf(el) {
    var root = rootOf(el);
    return root ? setup(root) : null;
  }

  // ── the model ─────────────────────────────────────────────────────────

  function editable(s, r) {
    return !s.cfg.readonly && !!s.roles[r] && !s.roles[r].locked;
  }

  /** Has the role the permission — itself, by being locked, or through its parents? */
  function eff(s, r, p) {
    for (var k = r, n = 0; k && s.roles[k] && n < 64; k = s.roles[k].parent, n++) {
      if (s.roles[k].locked || s.own[k][p]) return true;
    }
    return false;
  }

  /** The nearest ancestor that has it, or null. */
  function source(s, r, p) {
    for (var k = s.roles[r].parent, n = 0; k && s.roles[k] && n < 64; k = s.roles[k].parent, n++) {
      if (s.roles[k].locked || s.own[k][p]) return k;
    }
    return null;
  }

  /** Everything p needs, through what that needs. */
  function needs(s, p) {
    var seen = {}, out = [], todo = ((s.perms[p] && s.perms[p].requires) || []).slice();
    seen[p] = true;
    while (todo.length) {
      var q = todo.shift();
      if (seen[q] || !s.perms[q]) continue;
      seen[q] = true;
      out.push(q);
      todo.push.apply(todo, s.perms[q].requires || []);
    }
    return out;
  }

  /** Everything that needs p, through what needs that. */
  function needers(s, p) {
    var seen = {}, out = [], todo = [p];
    seen[p] = true;
    while (todo.length) {
      var q = todo.shift();
      s.permOrder.forEach(function (k) {
        if (!seen[k] && (s.perms[k].requires || []).indexOf(q) > -1) { seen[k] = true; out.push(k); todo.push(k); }
      });
    }
    return out;
  }

  /** The role and every role that inherits from it. */
  function heirs(s, r) {
    var out = [r];
    for (var i = 0; i < out.length; i++) {
      s.roleOrder.forEach(function (k) {
        if (s.roles[k].parent === out[i] && out.indexOf(k) < 0) out.push(k);
      });
    }
    return out;
  }

  function snapshot(s) {
    var o = {};
    s.roleOrder.forEach(function (r) { o[r] = Object.assign({}, s.own[r]); });
    return o;
  }

  function diffOf(s, before) {
    var out = [];
    s.roleOrder.forEach(function (r) {
      s.permOrder.forEach(function (p) {
        if (!!before[r][p] !== !!s.own[r][p]) out.push({ role: r, permission: p, granted: !!s.own[r][p] });
      });
    });
    return out;
  }

  /** What differs from the last saved state (the page as the server wrote it, or commit()). */
  function pending(s) {
    var out = [];
    s.roleOrder.forEach(function (r) {
      if (!editable(s, r)) return;
      s.permOrder.forEach(function (p) {
        if (s.cells[r][p] && !!s.own[r][p] !== !!s.was[r][p]) out.push({ role: r, permission: p, granted: !!s.own[r][p] });
      });
    });
    return out;
  }

  /** "View invoices, Edit invoices" — or "Editor: View invoices, Guest: …" when more than one role is involved. */
  function names(s, list) {
    var roles = {};
    list.forEach(function (c) { roles[c[0]] = true; });
    var many = Object.keys(roles).length > 1;
    var words = list.map(function (c) { return (many ? s.roles[c[0]].label + ": " : "") + s.perms[c[1]].label; });
    return words.length > 6 ? words.slice(0, 6).join(", ") + ", …" : words.join(", ");
  }

  /**
   * Set the cells, then keep `requires` whole: what a granted cell needs is granted with it; what
   * needs a revoked cell goes with it — in that role and its heirs, which lost it as well. Only the
   * permissions this change touches are looked at: an old gap elsewhere in the data stays as it is.
   */
  function apply(s, targets, granted) {
    var also = [], touched = [];
    // Parents first: a right granted to "all roles" goes to Guest and is inherited by Support and
    // Editor, rather than given to each of them as well — three own grants nobody asked for.
    var depth = function (r) { for (var n = 0, k = r; k && s.roles[k] && n < 64; k = s.roles[k].parent) n++; return n; };
    targets = targets.slice().sort(function (a, b) { return depth(a[0]) - depth(b[0]); });
    targets.forEach(function (t) {
      var r = t[0], p = t[1];
      if (!editable(s, r) || !s.cells[r][p]) return;
      if (granted && !eff(s, r, p)) { s.own[r][p] = true; touched.push(t); }
      else if (!granted && s.own[r][p]) { s.own[r][p] = false; touched.push(t); }
    });
    if (granted) {
      touched.forEach(function (t) {
        needs(s, t[1]).forEach(function (q) {
          if (s.cells[t[0]][q] && !eff(s, t[0], q)) { s.own[t[0]][q] = true; also.push([t[0], q]); }
        });
      });
    } else {
      var roles = {}, perms = {};
      touched.forEach(function (t) {
        heirs(s, t[0]).forEach(function (r) { roles[r] = true; });
        needers(s, t[1]).forEach(function (p) { perms[p] = true; });
      });
      for (var again = true, n = 0; again && n < 64; n++) {
        again = false;
        Object.keys(roles).forEach(function (r) {
          if (!editable(s, r)) return;
          Object.keys(perms).forEach(function (p) {
            if (!s.own[r][p]) return;
            var gap = (s.perms[p].requires || []).some(function (q) { return !eff(s, r, q); });
            if (gap) { s.own[r][p] = false; also.push([r, p]); again = true; }
          });
        });
      }
    }
    return also;
  }

  // ── what it looks like ────────────────────────────────────────────────

  function fieldName(s, r, p) {
    return s.cfg.name + "[" + r + "][" + p + "]";
  }

  function note(cell, text) {
    var el = cell.td.querySelector(":scope > .gk-perm-note");
    if (!text) { if (el) el.remove(); return; }
    if (!el) {
      el = document.createElement("span");
      el.className = "gk-perm-note";
      el.setAttribute("aria-hidden", "true");
      cell.td.appendChild(el);
    }
    el.textContent = text;
  }

  /** The cell's description: inherited from, changed, needed by — shared texts by id, the last one its own. */
  function describe(s, cell, from, changed, needed) {
    var ids = [];
    if (from) ids.push(s.inhIds[from]);
    if (changed && s.changedId) ids.push(s.changedId);
    var own = cell.td.querySelector(":scope > [data-gk-perm-needed]");
    if (needed.length) {
      if (!own) {
        own = document.createElement("span");
        own.hidden = true;
        own.id = cell.box.id + "-n";
        own.setAttribute("data-gk-perm-needed", "");
        cell.td.appendChild(own);
      }
      own.textContent = fmt(s.t.needed_by, { list: needed.join(", ") });
      ids.push(own.id);
    } else if (own) {
      own.remove();
    }
    ids = ids.filter(Boolean);
    if (ids.length) cell.box.setAttribute("aria-describedby", ids.join(" "));
    else cell.box.removeAttribute("aria-describedby");
  }

  function render(s) {
    if (s.cfg.readonly) return;
    var total = s.permOrder.length;
    s.roleOrder.forEach(function (r) {
      var count = 0;
      s.permOrder.forEach(function (p) {
        var has = eff(s, r, p);
        if (has) count++;
        var cell = s.cells[r][p];
        if (!cell || !cell.box || !editable(s, r)) return;
        var from = s.own[r][p] ? null : source(s, r, p);
        var changed = !!s.own[r][p] !== !!s.was[r][p];
        cell.box.checked = has;
        cell.td.classList.toggle("gk-perm-inherited", !!from);
        cell.td.classList.toggle("gk-perm-changed", changed);
        if (from) {
          cell.box.removeAttribute("name");
          cell.box.setAttribute("aria-disabled", "true");
        } else {
          cell.box.name = fieldName(s, r, p);
          cell.box.removeAttribute("aria-disabled");
        }
        note(cell, from ? fmt(s.t.via, { role: s.roles[from].label }) : "");
        var needed = [];
        if (!from && has) {
          s.permOrder.forEach(function (k) {
            if ((s.perms[k].requires || []).indexOf(p) > -1 && eff(s, r, k)) needed.push(s.perms[k].label);
          });
        }
        describe(s, cell, from, changed, needed);
      });
      var head = s.heads[r];
      var countEl = head && head.querySelector("[data-gk-perm-count]");
      if (countEl) countEl.textContent = fmt(s.t.count, { n: count, total: total });
    });
    bulkStates(s);
    changeBar(s);
  }

  /** The rows on screen: matching the filter, in a group that is open. */
  function rowInView(s, p) {
    var tr = s.rows[p];
    return !!tr && !tr.hasAttribute("data-gk-perm-out") && !tr.parentElement.hasAttribute("data-gk-perm-folded");
  }

  function rowMatches(s, p) {
    var tr = s.rows[p];
    return !!tr && !tr.hasAttribute("data-gk-perm-out");
  }

  /** The cells a bulk toggle stands for, as [role, permission]. */
  function scope(s, box) {
    var kind = box.getAttribute("data-gk-perm-bulk");
    var editRoles = s.roleOrder.filter(function (r) { return editable(s, r); });
    var out = [];
    if (kind === "role") {
      var r = box.closest("th").getAttribute("data-gk-perm-role");
      if (!editable(s, r)) return [];
      s.permOrder.forEach(function (p) { if (rowInView(s, p)) out.push([r, p]); });
    } else if (kind === "group" || kind === "group-all") {
      var g = box.closest("tbody").getAttribute("data-gk-perm-group");
      var roles = kind === "group" ? [box.closest("td").getAttribute("data-gk-perm-role")] : editRoles;
      roles.forEach(function (rk) {
        if (!editable(s, rk)) return;
        s.permOrder.forEach(function (p) { if (s.perms[p].group === g && rowMatches(s, p)) out.push([rk, p]); });
      });
    } else if (kind === "row") {
      var perm = box.closest("tr").getAttribute("data-gk-perm-perm");
      editRoles.forEach(function (rk) { out.push([rk, perm]); });
    }
    return out.filter(function (c) { return !!s.cells[c[0]][c[1]]; });
  }

  function bulkStates(s) {
    s.bulks.forEach(function (box) {
      var cells = scope(s, box);
      var n = cells.filter(function (c) { return eff(s, c[0], c[1]); }).length;
      box.checked = cells.length > 0 && n === cells.length;
      box.indeterminate = n > 0 && n < cells.length;
      if (cells.length) box.removeAttribute("aria-disabled");
      else box.setAttribute("aria-disabled", "true");
    });
  }

  function changeBar(s) {
    var el = s.root.querySelector("[data-gk-perm-changes]");
    var n = pending(s).length;
    if (el) {
      var text;
      if (s.cfg.save) text = s.inflight ? s.t.saving : (s.savedOnce ? s.t.saved : s.t.changes_none);
      else text = n === 0 ? s.t.changes_none : n === 1 ? s.t.changes_one : fmt(s.t.changes, { n: n });
      el.textContent = text;
      el.toggleAttribute("data-gk-perm-dirty", n > 0);
    }
    var reset = s.root.querySelector("[data-gk-perm-reset]");
    if (reset) reset.disabled = n === 0;
  }

  function announce(s, text) {
    var live = s.root.querySelector("[data-gk-perm-status]");
    if (!live) return;
    live.textContent = "";
    clearTimeout(s.liveTimer);
    if (text) s.liveTimer = setTimeout(function () { live.textContent = text; }, 60);
  }

  // ── changing it ───────────────────────────────────────────────────────

  /**
   * After the model changed: draw, tell the page (gk:permchange, cancelable), say what came along
   * with it, save. A listener's preventDefault() puts everything back as it was before.
   */
  function finish(s, before, kind, primary, granted, also) {
    var diff = diffOf(s, before);
    render(s);
    if (!diff.length) {
      if (kind !== "reset") announce(s, s.t.nothing);
      return false;
    }
    var ev = new CustomEvent("gk:permchange", {
      bubbles: true,
      cancelable: kind !== "revert",
      detail: {
        matrix: s.cfg.id,
        role: primary.role || null,
        permission: primary.permission || null,
        granted: granted,
        kind: kind,
        changed: diff,
        changes: pending(s),
      },
    });
    if (!s.root.dispatchEvent(ev)) {
      s.own = before;
      render(s);
      return false;
    }
    if (also && also.length) {
      announce(s, fmt(granted ? s.t.also_granted : s.t.also_removed, { list: names(s, also) }));
    } else if (kind === "reset") {
      announce(s, s.t.reset_done);
    }
    if (s.cfg.save && kind !== "revert") save(s, diff);
    return true;
  }

  function change(s, targets, granted, kind, primary) {
    var before = snapshot(s);
    var also = apply(s, targets, granted);
    return finish(s, before, kind, primary, granted, also);
  }

  function reset(s) {
    var before = snapshot(s);
    s.roleOrder.forEach(function (r) {
      if (!editable(s, r)) return;
      s.permOrder.forEach(function (p) { if (s.cells[r][p]) s.own[r][p] = !!s.was[r][p]; });
    });
    return finish(s, before, "reset", {}, null, []);
  }

  function commit(s) {
    s.roleOrder.forEach(function (r) {
      s.permOrder.forEach(function (p) { s.was[r][p] = !!s.own[r][p]; });
    });
    render(s);
  }

  /** One request at a time, in order; a failed one puts its cells back (those not changed again since). */
  function save(s, diff) {
    var body = { matrix: s.cfg.id, changes: {} };
    diff.forEach(function (d) { (body.changes[d.role] = body.changes[d.role] || {})[d.permission] = d.granted; });
    var busy = function (on) {
      diff.forEach(function (d) {
        var c = s.cells[d.role][d.permission];
        if (c && c.box) { if (on) c.box.setAttribute("aria-busy", "true"); else c.box.removeAttribute("aria-busy"); }
      });
    };
    s.inflight++;
    busy(true);
    changeBar(s);
    var headers = Object.assign({ "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }, s.cfg.headers || {});
    s.queue = s.queue.then(function () {
      return fetch(s.cfg.save, { method: "POST", credentials: "same-origin", headers: headers, body: JSON.stringify(body) })
        .then(function (res) {
          return res.json().then(function (d) { return { ok: res.ok, d: d }; }, function () { return { ok: res.ok, d: null }; });
        })
        .then(function (o) {
          return o.ok && o.d && o.d.ok === true ? null : String((o.d && (o.d.error || o.d.message)) || s.t.save_error);
        }, function () {
          return s.t.save_error;
        })
        .then(function (error) {
          s.inflight--;
          busy(false);
          if (error === null) {
            diff.forEach(function (d) { s.was[d.role][d.permission] = d.granted; });
            s.savedOnce = true;
            render(s);
            if (!s.inflight && GK.toast) GK.toast.success(s.t.saved, 1500);
            return;
          }
          var before = snapshot(s);
          diff.forEach(function (d) {
            if (!!s.own[d.role][d.permission] === d.granted) s.own[d.role][d.permission] = !d.granted;
          });
          finish(s, before, "revert", {}, null, []);
          if (GK.toast) GK.toast.error(error, 6000);
          else announce(s, error);
        })
        // A step that throws (a listener of the revert event, say) must not stop every save after it.
        .catch(function (e) { if (window.console) console.error(e); });
    });
  }

  /** Why a cell does not change: locked, inherited, or a read-only matrix. */
  function explain(s, box) {
    var td = box.closest("td");
    var r = td && td.getAttribute("data-gk-perm-role");
    var tr = box.closest("tr[data-gk-perm-perm]");
    var text;
    if (s.cfg.readonly) text = s.t.readonly_hint;
    else if (r && s.roles[r] && s.roles[r].locked) text = fmt(s.t.locked_hint, { role: s.roles[r].label });
    else if (r && tr) {
      var from = source(s, r, tr.getAttribute("data-gk-perm-perm"));
      text = from ? fmt(s.t.inherited_hint, { role: s.roles[from].label }) : s.t.nothing;
    } else text = s.t.nothing;
    if (GK.toast) GK.toast.info(text, 4000);
    else announce(s, text);
  }

  // ── filter and folding ────────────────────────────────────────────────

  function fold(s, tbody, folded) {
    tbody.toggleAttribute("data-gk-perm-folded", folded);
    var btn = tbody.querySelector(".gk-perm-fold");
    if (btn) btn.setAttribute("aria-expanded", folded ? "false" : "true");
  }

  function foldAllLabel(s) {
    var btn = s.root.querySelector("[data-gk-perm-foldall]");
    if (!btn) return;
    var open = s.groups.some(function (tb) { return !tb.hasAttribute("data-gk-perm-folded"); });
    var span = btn.querySelector("span:last-child") || btn;
    span.textContent = open ? s.t.fold_all : s.t.unfold_all;
  }

  function filter(s, query) {
    var words = norm(query).split(/\s+/).filter(Boolean);
    var searching = words.length > 0;
    // A search shows what it finds, folded or not; the folds come back when it is cleared.
    if (searching && !s.searching) {
      s.foldedBefore = s.groups.filter(function (tb) { return tb.hasAttribute("data-gk-perm-folded"); });
      s.groups.forEach(function (tb) { fold(s, tb, false); });
    } else if (!searching && s.searching) {
      s.groups.forEach(function (tb) { fold(s, tb, (s.foldedBefore || []).indexOf(tb) > -1); });
    }
    s.searching = searching;
    var hits = 0;
    s.bodies.forEach(function (tb) {
      var g = tb.getAttribute("data-gk-perm-group");
      var any = false;
      tb.querySelectorAll(":scope > tr[data-gk-perm-perm]").forEach(function (tr) {
        var text = (s.groupText[g] || "") + " " + (s.text[tr.getAttribute("data-gk-perm-perm")] || "");
        var hit = words.every(function (w) { return text.indexOf(w) > -1; });
        tr.toggleAttribute("data-gk-perm-out", !hit);
        if (hit) { any = true; hits++; }
      });
      tb.toggleAttribute("data-gk-perm-out", !any);
    });
    var empty = s.root.querySelector("[data-gk-perm-empty]");
    if (empty) empty.hidden = hits > 0;
    foldAllLabel(s);
    render(s);
    roving(s);
    clearTimeout(s.filterTimer);
    s.filterTimer = setTimeout(function () {
      announce(s, searching ? fmt(s.t.shown, { n: hits, total: s.permOrder.length }) : "");
    }, 400);
  }

  // ── a phone: one role at a time ───────────────────────────────────────

  function pick(s, key) {
    if (!s.roles[key]) key = s.roleOrder.filter(function (r) { return editable(s, r); })[0] || s.roleOrder[0];
    s.pick = key;
    s.table.querySelectorAll("[data-gk-perm-role]").forEach(function (el) {
      el.classList.toggle("gk-perm-col-on", el.getAttribute("data-gk-perm-role") === key);
    });
    var select = s.root.querySelector("[data-gk-perm-pick]");
    if (select && select.value !== key) select.value = key;
    roving(s);
    measure(s);
  }

  // ── the grid ──────────────────────────────────────────────────────────

  /** What takes the focus in a cell: its control, or the cell itself. */
  function itemOf(cell) {
    var ctl = cell.querySelector('input:not([type="hidden"]), button, select');
    return ctl && shown(ctl) ? ctl : cell;
  }

  function rowsOf(s) {
    return Array.prototype.filter.call(s.table.querySelectorAll(":scope > thead > tr, :scope > tbody > tr"), shown);
  }

  function cellsOf(tr) {
    return Array.prototype.filter.call(tr.children, shown);
  }

  function focusTo(s, el) {
    if (!el) return;
    if (s.current && s.current !== el) s.current.setAttribute("tabindex", "-1");
    el.setAttribute("tabindex", "0");
    s.current = el;
    el.focus();
  }

  /** Keep the grid's one tab stop on something that can be reached. */
  function roving(s) {
    if (s.current && shown(s.current) && s.table.contains(s.current)) return;
    var first = Array.prototype.find.call(s.table.querySelectorAll(":scope > tbody > tr .gk-perm-check"), shown) ||
      Array.prototype.find.call(s.table.querySelectorAll(":scope > tbody > tr > *"), shown) ||
      s.table.querySelector("thead th");
    if (s.current) s.current.setAttribute("tabindex", "-1");
    s.current = first || null;
    if (first) first.setAttribute("tabindex", "0");
  }

  /** The cell a key moves to, as [row, column] in the rows and cells on screen; null for any other key. */
  function step(key, ctrl, ri, ci, last) {
    switch (key) {
      case "ArrowRight": return [ri, ci + 1];
      case "ArrowLeft": return [ri, Math.max(0, ci - 1)];
      case "ArrowDown": return [Math.min(last, ri + 1), ci];
      case "ArrowUp": return [Math.max(0, ri - 1), ci];
      case "Home": return [ctrl ? 0 : ri, 0];
      case "End": return [ctrl ? last : ri, Infinity];
      case "PageDown": return [Math.min(last, ri + 10), ci];
      case "PageUp": return [Math.max(0, ri - 10), ci];
      default: return null;
    }
  }

  function onKey(s, e) {
    if (e.altKey || e.metaKey || e.defaultPrevented) return;
    var cell = e.target.closest ? e.target.closest("th, td") : null;
    if (!cell || cell.closest("table") !== s.table) return;
    // Space on a header cell (a permission's name) would scroll the box; it toggles only a box.
    if (e.key === " " && e.target === cell) { e.preventDefault(); return; }
    var rows = rowsOf(s);
    var ri = rows.indexOf(cell.parentElement);
    if (ri < 0) return;
    var to = step(e.key, e.ctrlKey, ri, cellsOf(rows[ri]).indexOf(cell), rows.length - 1);
    if (!to) return;
    var nr = to[0], nc = to[1];
    var target = cellsOf(rows[nr]);
    e.preventDefault();
    focusTo(s, itemOf(target[Math.min(nc, target.length - 1)]));
  }

  /** The focus scrolls a cell into view below the sticky head and right of the sticky column. */
  function measure(s) {
    var box = s.root.querySelector(".gk-perm-box");
    var head = s.table.tHead && s.table.tHead.rows[0];
    if (!box || !head) return;
    box.style.scrollPaddingTop = head.offsetHeight + "px";
    box.style.scrollPaddingLeft = (head.cells[0] ? head.cells[0].offsetWidth : 0) + "px";
  }

  // ── setting up ────────────────────────────────────────────────────────

  function setup(root) {
    if (root._gkPerm) return root._gkPerm;
    var cfg;
    try { cfg = JSON.parse(root.getAttribute("data-gk-perm-config") || ""); } catch (e) { return null; }
    var table = root.querySelector(".gk-perm-table");
    if (!cfg || !table) return null;
    var s = {
      root: root, cfg: cfg, t: cfg.i18n || {}, table: table,
      roles: {}, roleOrder: [], perms: {}, permOrder: [], cells: {}, own: {}, was: {},
      rows: {}, heads: {}, text: {}, groupText: {}, inhIds: {}, bulks: [], groups: [], bodies: [],
      inflight: 0, queue: Promise.resolve(), current: null, searching: false,
    };
    root._gkPerm = s;
    (cfg.roles || []).forEach(function (r) {
      s.roles[r.key] = r; s.roleOrder.push(r.key); s.cells[r.key] = {}; s.own[r.key] = {}; s.was[r.key] = {};
    });
    (cfg.perms || []).forEach(function (p) { s.perms[p.key] = p; s.permOrder.push(p.key); });
    root.querySelectorAll("[data-gk-perm-inh]").forEach(function (el) { s.inhIds[el.getAttribute("data-gk-perm-inh")] = el.id; });
    var changedHint = root.querySelector("[data-gk-perm-changed-hint]");
    s.changedId = changedHint ? changedHint.id : "";
    table.querySelectorAll(":scope > thead th[data-gk-perm-role]").forEach(function (th) { s.heads[th.getAttribute("data-gk-perm-role")] = th; });
    // Every <tbody> is filtered; only those with a heading row fold (the permissions without a group
    // have no button to open them again).
    s.bodies = Array.prototype.slice.call(table.querySelectorAll(":scope > tbody[data-gk-perm-group]"));
    s.groups = s.bodies.filter(function (tb) { return !!tb.querySelector(":scope > tr.gk-perm-group-row"); });
    s.bodies.forEach(function (tb) {
      var g = tb.getAttribute("data-gk-perm-group");
      var head = tb.querySelector(".gk-perm-group-head");
      s.groupText[g] = norm(((cfg.groups || {})[g] || "") + " " + (head ? head.textContent : ""));
    });
    table.querySelectorAll(":scope > tbody > tr[data-gk-perm-perm]").forEach(function (tr) {
      var p = tr.getAttribute("data-gk-perm-perm");
      if (!s.perms[p]) return;
      s.rows[p] = tr;
      var name = tr.querySelector(".gk-perm-name");
      s.text[p] = norm(p + " " + (name ? name.textContent : s.perms[p].label));
      tr.querySelectorAll(":scope > td[data-gk-perm-role]").forEach(function (td) {
        var r = td.getAttribute("data-gk-perm-role");
        if (!s.roles[r]) return;
        var box = td.querySelector(".gk-perm-check");
        s.cells[r][p] = { td: td, box: box };
        if (!box) return;
        // Disabled checkboxes cannot take the focus, and a grid has to reach every cell: the
        // locked, inherited and read-only ones say so with aria-disabled instead (no name, nothing posted).
        if (box.disabled) { box.disabled = false; box.setAttribute("aria-disabled", "true"); }
        if (!editable(s, r)) return;
        // A browser that restored the form (Back) shows what was ticked before: that counts as a change.
        s.own[r][p] = !td.classList.contains("gk-perm-inherited") && box.checked;
        s.was[r][p] = box.getAttribute("data-gk-perm-was") === "1";
      });
    });
    s.bulks = Array.prototype.slice.call(table.querySelectorAll(".gk-perm-bulk"));

    // A grid: one tab stop, the arrow keys inside. Without the script it is a table of checkboxes.
    table.setAttribute("role", "grid");
    if (cfg.readonly) table.setAttribute("aria-readonly", "true");
    table.querySelectorAll('input:not([type="hidden"]), button').forEach(function (el) { el.setAttribute("tabindex", "-1"); });
    root.querySelectorAll("[data-gk-perm-js]").forEach(function (el) { el.hidden = false; });
    root.querySelectorAll("[data-gk-perm-nojs]").forEach(function (el) { el.hidden = true; });
    root.querySelectorAll("[data-gk-perm-mixed]").forEach(function (el) { el.indeterminate = true; });
    s.groups.forEach(function (tb) { if (tb.hasAttribute("data-gk-perm-start-folded")) fold(s, tb, true); });
    foldAllLabel(s);
    root.setAttribute("data-gk-perm-ready", "");

    root.addEventListener("click", function (e) {
      var t = e.target;
      if (!t.closest) return;
      var box = t.closest('input[type="checkbox"]');
      if (box && s.table.contains(box)) {
        if (box.getAttribute("aria-disabled") === "true") { e.preventDefault(); explain(s, box); }
        return;
      }
      // The whole cell is the target, not only its 18px box.
      var td = t.closest("td.gk-perm-cell, td.gk-perm-all");
      if (td && s.table.contains(td) && !t.closest("a, button, input, label, select")) {
        var inner = td.querySelector(".gk-perm-check, .gk-perm-bulk");
        // A box that cannot change says why when it is clicked itself; its padding stays quiet.
        if (inner && shown(inner) && inner.getAttribute("aria-disabled") !== "true") { inner.click(); focusTo(s, inner); }
        return;
      }
      var foldBtn = t.closest(".gk-perm-fold");
      if (foldBtn && s.table.contains(foldBtn)) {
        fold(s, foldBtn.closest("tbody"), foldBtn.getAttribute("aria-expanded") === "true");
        foldAllLabel(s);
        render(s);
        return;
      }
      if (t.closest("[data-gk-perm-foldall]")) {
        var open = s.groups.some(function (tb) { return !tb.hasAttribute("data-gk-perm-folded"); });
        s.groups.forEach(function (tb) { fold(s, tb, open); });
        foldAllLabel(s);
        render(s);
        roving(s);
        return;
      }
      if (t.closest("[data-gk-perm-reset]")) reset(s);
    });

    root.addEventListener("change", function (e) {
      var el = e.target;
      if (el.matches("[data-gk-perm-pick]")) { pick(s, el.value); return; }
      if (!s.table.contains(el) || el.getAttribute("aria-disabled") === "true") return;
      if (el.classList.contains("gk-perm-check")) {
        var r = el.closest("td").getAttribute("data-gk-perm-role");
        var p = el.closest("tr").getAttribute("data-gk-perm-perm");
        change(s, [[r, p]], el.checked, "cell", { role: r, permission: p });
      } else if (el.classList.contains("gk-perm-bulk")) {
        var kind = el.getAttribute("data-gk-perm-bulk");
        var cell = el.closest("td, th");
        var primary = {
          role: kind === "role" || kind === "group" ? cell.getAttribute("data-gk-perm-role") : null,
          permission: kind === "row" ? el.closest("tr").getAttribute("data-gk-perm-perm") : null,
        };
        // A mixed box is unticked underneath: a click ticks it, and so grants; a ticked one revokes.
        change(s, scope(s, el), el.checked, kind, primary);
      }
    });

    var search = root.querySelector("[data-gk-perm-search]");
    if (search) {
      search.addEventListener("input", function () { filter(s, search.value); });
      if (search.value) filter(s, search.value);
    }

    table.addEventListener("keydown", function (e) { onKey(s, e); });
    table.addEventListener("focusin", function (e) {
      var t = e.target;
      if (t === s.current || !t.closest || t.closest("table") !== s.table) return;
      if (s.current) s.current.setAttribute("tabindex", "-1");
      t.setAttribute("tabindex", "0");
      s.current = t;
    });

    var form = root.closest("form");
    if (form) form.addEventListener("reset", function () { setTimeout(function () { reset(s); }, 0); });

    var select = root.querySelector("[data-gk-perm-pick]");
    pick(s, select ? select.value : "");
    render(s);
    roving(s);
    measure(s);
    return s;
  }

  var resizeTimer = null;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      document.querySelectorAll("[data-gk-perm]").forEach(function (root) { if (root._gkPerm) measure(root._gkPerm); });
    }, 150);
  });

  GK.permMatrix = {
    /** Bind every matrix under root (idempotent): the page's on load, later ones through GK.initContent. */
    init: function (root) {
      var scopeEl = root && root.querySelectorAll ? root : document;
      if (scopeEl.matches && scopeEl.matches("[data-gk-perm]")) setup(scopeEl);
      scopeEl.querySelectorAll("[data-gk-perm]").forEach(setup);
    },
    /** What is not saved yet: [{ role, permission, granted }], in the matrix's order. */
    changes: function (el) {
      var s = stateOf(el);
      return s ? pending(s) : [];
    },
    /** Back to the last saved state. Returns false when there was nothing to undo or a listener said no. */
    reset: function (el) {
      var s = stateOf(el);
      return s ? reset(s) : false;
    },
    /** The current state is saved (an AJAX form saved it): the marks and the count go. */
    commit: function (el) {
      var s = stateOf(el);
      if (s) commit(s);
    },
  };

  if (typeof GK.initContent === "function") {
    var initContent = GK.initContent;
    GK.initContent = function (root) {
      var result = initContent.apply(this, arguments);
      GK.permMatrix.init(root);
      return result;
    };
  }
  _gkReady(function () { GK.permMatrix.init(document); });
})();
// === 1.117.0 — line items (GridKit\LineItems) ===
/*
 * The editor of the lines of an invoice, an offer or an order (LineItems::render()). PHP writes every
 * line and its fields (name[i][field]), the totals and an empty line in a <template>; this block makes
 * it live: the totals follow every keystroke, a line is added (the button, or Enter in the last field
 * of the last line), duplicated and deleted (with Undo in a toast), and the order comes from
 * js/sortierbar.js, which the <ol> asks for with data-gk-sortierbar. After every change the names are
 * numbered again, so the POST is always name[0..n-1][field] in the order on the screen, and
 * gk:lineitemschange fires on the editor with { items, totals }.
 *
 * The arithmetic is LineItems::totals() written again, rule for rule, in BigInt: scaled integers
 * (quantity and price to 4 decimals, percentages to 2), rounding half away from zero, the same
 * order of steps. ci/browser-line-items.js holds both to the cent on random lines. So is reading
 * a number (LineItems::parseNumber()) and writing one. Change one side, change the other.
 *
 * Without this block (a copy of gridkit.js that has no such block, or no script at all) the editor
 * is a set of fields that post; the server computes the totals after the POST. Nothing outside this
 * block is changed or wrapped: it listens on the document and finds its editors by data-gk-lines.
 */
(function () {
  "use strict";
  if (!window.GK) return;

  var ROOT = "[data-gk-lines]";
  var SCALE = { qty: 4, price: 4, discount: 2, vat: 2 };
  var NUMERIC = { number: true, money: true, percent: true };
  var BLANKABLE = { text: true, textarea: true, number: true, money: true, percent: true, lookup: true };
  var HAS_BIG = typeof BigInt === "function";
  // No BigInt literals (1n): a browser without BigInt must still parse the rest of this file.
  var ZERO = HAS_BIG ? BigInt(0) : 0;
  var ONE = HAS_BIG ? BigInt(1) : 1;
  var TWO = HAS_BIG ? BigInt(2) : 2;
  var keyCounter = 0;

  /* ── Numbers: LineItems::parseNumber(), ::machine() and the formatting, to the letter ── */

  // The spaces by name, as PHP lists them (\s means more in JavaScript than in PCRE).
  var SPACES = /[ \t\n\x0B\f\r\u00A0\u2007\u2009\u202F'\u2019]+/g;
  var CUR = "(?:€|\\$|£|EUR|USD|GBP|CHF)";
  var EDGES = new RegExp("^" + CUR + "|(?:" + CUR + "|%)$", "gi");
  var LEAD = new RegExp("^" + CUR, "i");
  var encoder = typeof TextEncoder === "function" ? new TextEncoder() : null;

  /** PHP's trim(): the six characters it strips, not every Unicode space. */
  function phpTrim(s) {
    return String(s).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, "");
  }

  function count(s, ch) {
    return s.split(ch).length - 1;
  }

  function groupsOk(s, sep) {
    var parts = s.split(sep);
    if (!/^\d{1,3}$/.test(parts[0])) return false;
    for (var k = 1; k < parts.length; k++) if (!/^\d{3}$/.test(parts[k])) return false;
    return true;
  }

  /** The decimal sign of a language, as LineItems::localeFamily() decides it. */
  function decOf(locale) {
    return String(locale || "en").slice(0, 2).toLowerCase() === "de" ? "," : ".";
  }

  /** A number in machine form ("-1234.5") or null: an int, a finite float, or such a string. */
  function machine(v) {
    if (typeof v === "number") {
      if (!isFinite(v)) return null;
      v = v.toFixed(12).replace(/0+$/, "").replace(/\.$/, "");
    }
    if (typeof v !== "string") return null;
    var m = /^[ \t\n\x0B\f\r]*([+-]?)(\d{1,15})(?:\.(\d{1,20}))?[ \t\n\x0B\f\r]*$/.exec(v);
    if (!m) return null;
    var int = m[2].replace(/^0+/, "");
    var frac = (m[3] || "").replace(/0+$/, "");
    var out = (int === "" ? "0" : int) + (frac === "" ? "" : "." + frac);
    return out === "0" || m[1] !== "-" ? out : "-" + out;
  }

  /** What a person typed, in machine form, or null. dec is the decimal sign of the page. */
  function parseNumber(input, dec) {
    if (typeof input === "number") return machine(input);
    if (typeof input !== "string") return null;
    if ((encoder ? encoder.encode(input).length : unescape(encodeURIComponent(input)).length) > 64) return null;
    var s = input.replace(SPACES, "").replace(EDGES, "");
    var sign = "";
    var marks = [["-", "-"], ["+", ""], ["\u2212", "-"]];
    for (var k = 0; k < marks.length; k++) {
      if (s.indexOf(marks[k][0]) === 0) {
        sign = marks[k][1];
        s = s.slice(marks[k][0].length).replace(LEAD, "");
        break;
      }
    }
    if (!/^[0-9.,]+$/.test(s) || !/[0-9]/.test(s)) return null;
    var dots = count(s, ".");
    var commas = count(s, ",");
    var int;
    var frac = "";
    var parts;
    if (dots && commas) {
      var decSep = s.lastIndexOf(".") > s.lastIndexOf(",") ? "." : ",";
      var thou = decSep === "." ? "," : ".";
      if (count(s, decSep) !== 1) return null;
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
        if (/^[1-9]\d{0,2}$/.test(int) && /^\d{3}$/.test(frac) && sep !== dec) {
          int += frac;
          frac = "";
        }
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

  /** A machine number with the page's signs, at least minDec decimals, never rounded. */
  function display(canon, minDec, fmt) {
    var neg = canon.charAt(0) === "-";
    var parts = (neg ? canon.slice(1) : canon).split(".");
    var int = parts[0];
    var frac = parts[1] || "";
    while (frac.length < minDec) frac += "0";
    var grouped = "";
    for (var k = 0; k < int.length; k++) {
      if (k > 0 && (int.length - k) % 3 === 0) grouped += fmt.thou;
      grouped += int.charAt(k);
    }
    return (neg ? "-" : "") + grouped + (frac !== "" ? fmt.dec + frac : "");
  }

  function money(amount, fmt) {
    var neg = amount.charAt(0) === "-";
    return (neg ? "-" : "") + fmt.money.split("{value}").join(display(neg ? amount.slice(1) : amount, 2, fmt));
  }

  /* ── The arithmetic: LineItems::totals() ─────────────────────────────── */

  function toScaled(canon, scale) {
    var neg = canon.charAt(0) === "-";
    var parts = (neg ? canon.slice(1) : canon).split(".");
    var frac = parts[1] || "";
    var head = frac.slice(0, scale);
    while (head.length < scale) head += "0";
    var mag = BigInt(parts[0] + head);
    if (frac.length > scale && frac.charAt(scale) >= "5") mag += ONE;
    return neg ? -mag : mag;
  }

  function fromScaled(n, scale, minDec) {
    var neg = n < ZERO;
    var mag = (neg ? -n : n).toString();
    while (mag.length < scale + 1) mag = "0" + mag;
    var int = mag.slice(0, mag.length - scale);
    var frac = mag.slice(mag.length - scale).replace(/0+$/, "");
    while (frac.length < (minDec || 0)) frac += "0";
    var out = int + (frac === "" ? "" : "." + frac);
    return neg ? "-" + out : out;
  }

  /** n / d, half away from zero (d > 0). */
  function divRound(n, d) {
    var neg = n < ZERO;
    var m = neg ? -n : n;
    var q = m / d;
    if ((m % d) * TWO >= d) q += ONE;
    return neg ? -q : q;
  }

  /** LineItems::totals($rows, $opts), the same shape, the same strings. */
  function calc(rows, opts) {
    opts = opts || {};
    var net = opts.pricesNet !== false;
    var mode = opts.rounding === "total" ? "total" : "line";
    var keys = { qty: "qty", price: "price", discount: "discount", vat: "vat" };
    Object.keys(opts.keys || {}).forEach(function (k) { keys[k] = opts.keys[k]; });
    var dec = typeof opts.locale === "string" ? decOf(opts.locale) : null;
    var read = function (v) { return dec === null ? machine(v) : parseNumber(v, dec); };
    var scaled = function (row, role) {
      var key = keys[role];
      if (key === null || key === undefined) return null;
      var c = read(row[key] === undefined ? null : row[key]);
      return toScaled(c === null ? "0" : c, SCALE[role]);
    };
    var lineScale = BigInt("10000000000");
    var hundredPct = BigInt(10000);
    var groups = {};
    var order = [];
    var lines = [];
    (rows || []).forEach(function (row) {
      row = row && typeof row === "object" ? row : {};
      var q = scaled(row, "qty");
      var p = scaled(row, "price");
      var d = scaled(row, "discount");
      var r = scaled(row, "vat");
      if (q === null) q = hundredPct;
      if (p === null) p = ZERO;
      if (d === null) d = ZERO;
      if (r === null) r = ZERO;
      var exact = q * p * (hundredPct - d);
      var cents = divRound(exact, lineScale);
      lines.push({ total: fromScaled(cents, 2, 2), vat: fromScaled(r, 2, 0) });
      var key = r.toString();
      if (!groups[key]) { groups[key] = [ZERO, ZERO]; order.push(key); }
      groups[key] = [groups[key][0] + exact, groups[key][1] + cents];
    });

    var sums = {};
    var rates = [];
    var subtotal = ZERO;
    order.forEach(function (key) {
      sums[key] = mode === "line" ? groups[key][1] : divRound(groups[key][0], lineScale);
      rates.push(key);
      subtotal += sums[key];
    });

    var discount = ZERO;
    var percent = opts.discount !== undefined && opts.discount !== null ? toScaled(machine(opts.discount) || "0", 2) : ZERO;
    if (percent !== ZERO) {
      rates.forEach(function (key) {
        var off = divRound(sums[key] * percent, hundredPct);
        sums[key] -= off;
        discount -= off;
      });
    }

    var shipping = opts.shipping !== undefined && opts.shipping !== null
      ? divRound(toScaled(machine(opts.shipping) || "0", 4), BigInt(100)) : ZERO;
    if (shipping !== ZERO) {
      var rate = opts.shippingVat !== undefined && opts.shippingVat !== null ? machine(opts.shippingVat) : null;
      if (rate !== null) {
        rate = toScaled(rate, 2);
      } else {
        rate = ZERO;
        rates.forEach(function (key) { if (BigInt(key) > rate) rate = BigInt(key); });
      }
      var rk = rate.toString();
      if (!(rk in sums)) { sums[rk] = ZERO; rates.push(rk); }
      sums[rk] += shipping;
    }

    rates.sort(function (a, b) { var x = BigInt(a); var y = BigInt(b); return x < y ? 1 : x > y ? -1 : 0; });
    var vat = [];
    var vatTotal = ZERO;
    var netTotal = ZERO;
    var grossTotal = ZERO;
    rates.forEach(function (key) {
      var r = BigInt(key);
      var sum = sums[key];
      var base;
      var amount;
      var gross;
      if (net) {
        base = sum;
        amount = divRound(sum * r, hundredPct);
        gross = sum + amount;
      } else {
        gross = sum;
        amount = divRound(sum * r, hundredPct + r);
        base = sum - amount;
      }
      netTotal += base;
      grossTotal += gross;
      vatTotal += amount;
      if (base === ZERO && amount === ZERO) return;
      vat.push({ rate: fromScaled(r, 2, 0), base: fromScaled(base, 2, 2), amount: fromScaled(amount, 2, 2) });
    });

    return {
      lines: lines,
      subtotal: fromScaled(subtotal, 2, 2),
      discount: fromScaled(discount, 2, 2),
      shipping: fromScaled(shipping, 2, 2),
      net: fromScaled(netTotal, 2, 2),
      vat: vat,
      vatTotal: fromScaled(vatTotal, 2, 2),
      gross: fromScaled(grossTotal, 2, 2),
      pricesNet: net,
      rounding: mode,
    };
  }

  /* ── Reading an editor ───────────────────────────────────────────────── */

  function cfgOf(el) {
    var src = el.getAttribute("data-gk-lines");
    if (el._gkLinesSrc !== src) {
      el._gkLinesSrc = src;
      try { el._gkLinesCfg = JSON.parse(src); } catch (e) { el._gkLinesCfg = null; }
      if (el._gkLinesCfg) {
        el._gkLinesCfg.byKey = {};
        el._gkLinesCfg.columns.forEach(function (c) { el._gkLinesCfg.byKey[c.key] = c; });
      }
    }
    return el._gkLinesCfg;
  }

  function bodyOf(el) {
    return el.querySelector(":scope > .gk-lines-body");
  }

  function rowsOf(el) {
    var body = bodyOf(el);
    return body ? Array.prototype.filter.call(body.children, function (li) { return li.classList.contains("gk-lines-row"); }) : [];
  }

  function fieldOf(li, key) {
    return li.querySelector('[data-gk-lines-field="' + key + '"]');
  }

  /** The fields of a line a person can reach, in order (not the hidden ones). */
  function fieldsOf(li) {
    return Array.prototype.filter.call(li.querySelectorAll("[data-gk-lines-field]"), function (f) {
      return f.type !== "hidden" && !f.classList.contains("gk-select-value-input");
    });
  }

  /** The first place in a line to put the caret: its first field, or the search of a lookup. */
  function firstFocusable(li) {
    var f = li.querySelector("textarea[data-gk-lines-field], input[data-gk-lines-field]:not([type=hidden]):not(.gk-select-value-input), select[data-gk-lines-field], .gk-ajax-search-input");
    return f;
  }

  /**
   * One line as parse() reads it: numbers in machine form, or as typed when they are none; text
   * trimmed like PHP trims it; the id when the line has one.
   */
  function readRow(el, cfg, li) {
    var out = {};
    var id = fieldOf(li, "id");
    if (id) out.id = phpTrim(id.value);
    cfg.columns.forEach(function (c) {
      if (c.type === "computed") return;
      var raw;
      if (cfg.readonly) {
        var cell = li.querySelector('[data-gk-lines-cell="' + c.key + '"]');
        raw = cell ? cell.getAttribute("data-gk-lines-value") || "" : "";
        out[c.key] = NUMERIC[c.type] ? (machine(raw) === null ? raw : machine(raw)) : raw;
        return;
      }
      var f = fieldOf(li, c.key);
      raw = f ? f.value : "";
      var t = phpTrim(raw);
      if (NUMERIC[c.type]) {
        var canon = t === "" ? null : parseNumber(t, cfg.fmt.dec);
        out[c.key] = t === "" ? "" : canon === null ? t : canon;
      } else {
        out[c.key] = t;
      }
      // The words an article search shows, posted with it under its 'display' key.
      if (c.type === "lookup" && c.display) {
        var shown = fieldOf(li, c.display);
        out[c.display] = shown ? phpTrim(shown.value) : "";
      }
    });
    return out;
  }

  /** LineItems::isBlank(): every field empty or at its default. */
  function isBlank(row, cfg) {
    return cfg.columns.every(function (c) {
      if (!BLANKABLE[c.type]) return true;
      var v = row[c.key] === undefined || row[c.key] === null ? "" : phpTrim(row[c.key]);
      if (NUMERIC[c.type] && machine(v) !== null) v = machine(v);
      return v === "" || v === c["default"];
    });
  }

  /** LineItems::numbersOf(): the figures the totals read. */
  function numbersOf(row, cfg) {
    var out = {};
    cfg.columns.forEach(function (c) {
      if (c.role && c.role !== "total") out[c.key] = machine(row[c.key] === undefined ? null : row[c.key]);
    });
    return out;
  }

  function roleKey(cfg, role) {
    for (var k = 0; k < cfg.columns.length; k++) if (cfg.columns[k].role === role) return cfg.columns[k].key;
    return null;
  }

  /** The overall discount or the shipping as typed, in machine form: "0" when it cannot be read. */
  function adjustValue(el, cfg, what) {
    if (!cfg[what]) return null;
    var f = el.querySelector('[data-gk-lines-adjust="' + what + '"]');
    if (!f) {
      var row = el.querySelector('[data-gk-lines-sum="' + what + '"]');
      return row && row.getAttribute("data-gk-lines-value") ? row.getAttribute("data-gk-lines-value") : "0";
    }
    var t = phpTrim(f.value);
    return (t === "" ? null : parseNumber(t, cfg.fmt.dec)) || "0";
  }

  function totalsOptions(el, cfg) {
    return {
      pricesNet: cfg.net,
      rounding: cfg.rounding,
      discount: adjustValue(el, cfg, "discount"),
      shipping: adjustValue(el, cfg, "shipping"),
      shippingVat: cfg.shippingVat,
      keys: { qty: roleKey(cfg, "qty"), price: roleKey(cfg, "price"), discount: roleKey(cfg, "discount"), vat: roleKey(cfg, "vat") },
    };
  }

  /** Everything at once: the lines as read, the ones that count, their totals, every line's own total. */
  function compute(el) {
    var cfg = cfgOf(el);
    var lis = rowsOf(el);
    var rows = lis.map(function (li) { return readRow(el, cfg, li); });
    var blank = rows.map(function (r) { return isBlank(r, cfg); });
    var opts = totalsOptions(el, cfg);
    var items = rows.filter(function (r, k) { return !blank[k]; });
    var totals = HAS_BIG ? calc(items.map(function (r) { return numbersOf(r, cfg); }), opts) : null;
    var lineTotals = HAS_BIG ? calc(rows.map(function (r) { return numbersOf(r, cfg); }), opts).lines : null;
    var totalKey = roleKey(cfg, "total");
    if (totals && totalKey) items.forEach(function (item, k) { item[totalKey] = totals.lines[k].total; });
    return { cfg: cfg, lis: lis, rows: rows, blank: blank, items: items, totals: totals, lineTotals: lineTotals };
  }

  /* ── Checking a field: the messages parse() would give ──────────────── */

  function say(text, params) {
    var out = String(text || "");
    Object.keys(params || {}).forEach(function (k) { out = out.split("{" + k + "}").join(params[k]); });
    return out;
  }

  /** LineItems::numberProblem(), or "" when the figure can be taken. */
  function numberProblem(cfg, spec, canon) {
    if (canon === null) return say(cfg.text.err_number, { example: cfg.text.example });
    var dot = canon.indexOf(".");
    if (dot >= 0 && canon.length - dot - 1 > spec.decimals) return say(cfg.text.err_decimals, { n: spec.decimals });
    if (!HAS_BIG) return "";
    var num = toScaled(canon, 4);
    if (spec.min !== null && spec.min !== undefined && num < toScaled(spec.min, 4)) return say(cfg.text.err_min, { min: display(spec.min, 0, cfg.fmt) });
    if (spec.max !== null && spec.max !== undefined && num > toScaled(spec.max, 4)) return say(cfg.text.err_max, { max: display(spec.max, 0, cfg.fmt) });
    return "";
  }

  var ADJUST = {
    discount: { decimals: 2, min: "0", max: "100" },
    shipping: { decimals: 2, min: "0", max: null },
  };

  /** What is wrong with a field, as a sentence; "" when nothing is. Sets it as the field's validity. */
  function check(el, cfg, f) {
    var msg = "";
    var t = phpTrim(f.value);
    var adjust = f.getAttribute("data-gk-lines-adjust");
    var c = adjust ? null : cfg.byKey[f.getAttribute("data-gk-lines-field")];
    if (adjust) {
      if (t !== "") msg = numberProblem(cfg, ADJUST[adjust], parseNumber(t, cfg.fmt.dec));
    } else if (c) {
      if (t === "" && f.required) msg = say(cfg.text.err_required, { name: c.label });
      else if (t !== "" && NUMERIC[c.type]) msg = numberProblem(cfg, c, parseNumber(t, cfg.fmt.dec));
      // A value the list does not offer (an old unit, a rate no longer in use): parse() refuses it.
      else if (c.type === "select" && f.selectedOptions && f.selectedOptions[0] && f.selectedOptions[0].hasAttribute("data-gk-lines-unknown")) msg = cfg.text.err_option;
    }
    if (f.setCustomValidity) f.setCustomValidity(msg);
    return msg;
  }

  /** The line a field's message goes into: the one of its cell (the article search points at it from
   *  its visible part, not from the value it posts), or the one it names. */
  function slotOf(f) {
    var cell = f.closest ? f.closest("[data-gk-lines-cell]") : null;
    var slot = cell ? cell.querySelector(":scope > .gk-field-error") : null;
    return slot || document.getElementById(f.getAttribute("aria-describedby") || "");
  }

  /** Writes a message under a field, or takes it away; the field says so to a screen reader. */
  function show(f, msg) {
    var slot = slotOf(f);
    if (slot) slot.textContent = msg;
    f.classList.toggle("gk-has-error", msg !== "");
    if (msg) f.setAttribute("aria-invalid", "true");
    else f.removeAttribute("aria-invalid");
  }

  /** A figure that can be read, written back in the page's shape (1234,5 → 1.234,50): what was
   *  understood is what the person sees. Never rounded — a decimal too many stays, with its message. */
  function reformat(cfg, f) {
    var adjust = f.getAttribute("data-gk-lines-adjust");
    var c = adjust ? null : cfg.byKey[f.getAttribute("data-gk-lines-field")];
    if (!adjust && !(c && NUMERIC[c.type])) return;
    var t = phpTrim(f.value);
    if (t === "") return;
    var canon = parseNumber(t, cfg.fmt.dec);
    if (canon === null) return;
    var minDec = adjust ? (adjust === "shipping" ? 2 : 0) : c.type === "money" ? c.decimals : 0;
    var text = display(canon, minDec, cfg.fmt);
    if (f.value !== text) f.value = text;
  }

  /* ── Writing an editor ───────────────────────────────────────────────── */

  /** Names, ids of messages, the number, the words: everything that says where a line stands. */
  function renumber(el) {
    var cfg = cfgOf(el);
    if (!cfg) return;
    rowsOf(el).forEach(function (li, i) {
      var n = i + 1;
      var pos = li.querySelector(".gk-lines-pos");
      if (pos && pos.textContent !== String(n)) pos.textContent = String(n);
      var group = li.querySelector(".gk-lines-line");
      if (group) group.setAttribute("aria-label", say(cfg.text.line, { n: n }));
      if (cfg.readonly) return;
      li.querySelectorAll("[data-gk-lines-field]").forEach(function (f) {
        var name = cfg.name + "[" + i + "][" + f.getAttribute("data-gk-lines-field") + "]";
        if (f.name !== name) f.name = name;
        // The message line is keyed like the field, so an AJAX Form's {"errors": {"items[2][qty]": …}}
        // still finds it after a move. (Only a column's own field: the words of an article search
        // share the cell, not the key.)
        var slot = f.type === "hidden" || !cfg.byKey[f.getAttribute("data-gk-lines-field")] ? null : slotOf(f);
        if (slot && slot.getAttribute("data-gk-error") !== name) slot.setAttribute("data-gk-error", name);
      });
      var dup = li.querySelector('[data-gk-lines-action="duplicate"]');
      var del = li.querySelector('[data-gk-lines-action="delete"]');
      if (dup) dup.setAttribute("aria-label", say(cfg.text.duplicate, { n: n }));
      if (del) del.setAttribute("aria-label", say(cfg.text["delete"], { n: n }));
      var first = li.querySelector("textarea[data-gk-lines-field], input[type=text][data-gk-lines-field]:not(.gk-select-value-input)");
      var text = first && !first.hasAttribute("inputmode") ? phpTrim(first.value.split("\n")[0]) : "";
      li.setAttribute("data-gk-sort-titel", text ? say(cfg.text.line_titled, { n: n, text: text }) : say(cfg.text.line, { n: n }));
    });
  }

  /** A line gets a key of its own: every id in it, and what points at one, moves along. */
  function rekey(li, cfg, key) {
    var old = cfg.base + "-" + li.getAttribute("data-gk-sort-id") + "-";
    var now = cfg.base + "-" + key + "-";
    li.setAttribute("data-gk-sort-id", key);
    [li].concat(Array.prototype.slice.call(li.querySelectorAll("*"))).forEach(function (node) {
      ["id", "for", "aria-describedby", "aria-labelledby", "aria-controls"].forEach(function (a) {
        var v = node.getAttribute(a);
        if (v && v.indexOf(old) >= 0) node.setAttribute(a, v.split(old).join(now));
      });
    });
  }

  function nextKey() {
    keyCounter++;
    return "c" + keyCounter;
  }

  /** A row's figures, rewritten from the totals; the VAT lines rebuilt when the rates change. */
  function paint(el, state) {
    var cfg = state.cfg;
    var fmt = cfg.fmt;
    var totalKey = roleKey(cfg, "total");
    if (state.lineTotals && totalKey) {
      state.lis.forEach(function (li, k) {
        var out = li.querySelector('[data-gk-lines-out="' + totalKey + '"]');
        var text = money(state.lineTotals[k].total, fmt);
        if (out && out.textContent !== text) out.textContent = text;
      });
    }
    var t = state.totals;
    if (!t) return;
    ["subtotal", "discount", "shipping", "net", "gross"].forEach(function (what) {
      var dd = el.querySelector('[data-gk-lines-sum="' + what + '"] > dd');
      var text = money(t[what], fmt);
      if (dd && dd.textContent !== text) dd.textContent = text;
    });
    var dl = el.querySelector(".gk-lines-totals");
    var grand = el.querySelector('[data-gk-lines-sum="gross"]');
    if (!dl || !grand) return;
    var have = Array.prototype.map.call(dl.querySelectorAll("[data-gk-lines-vat]"), function (d) { return d.getAttribute("data-gk-lines-vat"); });
    var want = t.vat.map(function (v) { return v.rate; });
    if (have.join("|") !== want.join("|")) {
      dl.querySelectorAll("[data-gk-lines-vat]").forEach(function (d) { d.remove(); });
      t.vat.forEach(function (v) {
        // The markup of LineItems::vatRowsHtml(), element for element.
        var row = document.createElement("div");
        row.className = "gk-lines-sum";
        row.setAttribute("data-gk-lines-vat", v.rate);
        var dt = document.createElement("dt");
        var dd = document.createElement("dd");
        dd.className = "gk-td-num";
        row.appendChild(dt);
        row.appendChild(dd);
        dl.insertBefore(row, grand);
      });
    }
    t.vat.forEach(function (v) {
      var row = dl.querySelector('[data-gk-lines-vat="' + v.rate + '"]');
      if (!row) return;
      var label = say(cfg.net ? cfg.text.vat_rate : cfg.text.vat_incl, { rate: display(v.rate, 0, fmt) });
      var amount = money(v.amount, fmt);
      if (row.firstChild.textContent !== label) row.firstChild.textContent = label;
      if (row.lastChild.textContent !== amount) row.lastChild.textContent = amount;
    });
  }

  /**
   * After every change: figures, VAT lines, which fields are required (a blank line is not a line,
   * so nothing in it is — except the first ones limits() asks for), what the buttons may do, the
   * empty state. Then, when asked, the event and a word for a screen reader.
   */
  function update(el, emit) {
    var state = compute(el);
    var cfg = state.cfg;
    paint(el, state);
    if (cfg.readonly) return state;
    var nonBlank = state.blank.filter(function (b) { return !b; }).length;
    var spare = Math.max(0, cfg.min - nonBlank);
    state.lis.forEach(function (li, k) {
      var required = !state.blank[k] || spare-- > 0;
      li.querySelectorAll("[data-gk-lines-required]").forEach(function (f) {
        if (f.required !== required) f.required = required;
      });
    });
    el.querySelectorAll("[data-gk-lines-field], [data-gk-lines-adjust]").forEach(function (f) {
      if (f.type === "hidden") return;
      var msg = check(el, cfg, f);
      // A message already on screen follows the field; a new one waits until the field is left.
      if (f.getAttribute("aria-invalid") === "true" && f._gkLinesShown) show(f, msg);
    });
    var count = state.lis.length;
    var full = cfg.max !== null && cfg.max !== undefined && count >= cfg.max;
    el.querySelectorAll('[data-gk-lines-action="add"], [data-gk-lines-action="duplicate"]').forEach(function (b) { b.disabled = full; });
    el.querySelectorAll('[data-gk-lines-action="delete"]').forEach(function (b) { b.disabled = count <= cfg.min; });
    var empty = el.querySelector(":scope > [data-gk-lines-empty]");
    if (empty) empty.hidden = count > 0;
    if (emit) {
      el.dispatchEvent(new CustomEvent("gk:lineitemschange", { bubbles: true, detail: { items: state.items, totals: state.totals } }));
      announce(el, state);
    }
    return state;
  }

  /** The total, said once the typing stops — not on every keystroke. */
  function announce(el, state) {
    var live = el.querySelector(":scope > [data-gk-lines-live]");
    if (!live || !state.totals) return;
    clearTimeout(el._gkLinesSay);
    el._gkLinesSay = setTimeout(function () {
      var text = say(state.cfg.text.said_total, { amount: money(state.totals.gross, state.cfg.fmt) });
      if (live.textContent !== text) live.textContent = text;
    }, 900);
  }

  var growable = typeof CSS !== "undefined" && CSS.supports && CSS.supports("field-sizing", "content");

  /** A textarea as tall as its text, where CSS cannot say so itself (field-sizing). */
  function grow(t) {
    if (growable || !t || t.tagName !== "TEXTAREA") return;
    t.style.height = "auto";
    t.style.height = t.scrollHeight + 2 + "px";
  }

  /** What a line needs once it is in the page: the article search bound, the textareas sized. */
  function wake(li) {
    if (GK.ajaxSelect && li.querySelector("[data-gk-ajax-select]")) GK.ajaxSelect.init(li);
    li.querySelectorAll("textarea[data-gk-lines-field]").forEach(grow);
  }

  /** Puts a value in a field from machine form, as LineItems::items() would have drawn it. */
  function setField(li, cfg, key, value) {
    var c = cfg.byKey[key];
    var f = fieldOf(li, key);
    if (!c || !f || value === undefined || value === null) return;
    if (c.type === "lookup" || c.type === "computed") return;
    var text;
    if (NUMERIC[c.type]) {
      var canon = machine(value);
      text = canon === null ? String(value) : display(canon, c.type === "money" ? c.decimals : 0, cfg.fmt);
    } else if (c.type === "select") {
      text = c.role === "vat" && machine(value) !== null ? machine(value) : String(value);
      if (!Array.prototype.some.call(f.options, function (o) { return o.value === text; })) {
        // A value the list does not know is shown and marked, as on the server (selectHtml()).
        var o = document.createElement("option");
        o.value = text;
        o.textContent = c.role === "vat" && machine(text) !== null ? display(text, 0, cfg.fmt) + "\u00A0%" : text;
        o.setAttribute("data-gk-lines-unknown", "");
        f.appendChild(o);
      }
    } else {
      text = String(value);
    }
    f.value = text;
    if (c.type === "readonly") {
      var out = f.parentNode.querySelector(".gk-lines-out");
      if (out) out.textContent = text;
    }
    grow(f);
  }

  function focus(node) {
    if (!node) return;
    try { node.focus({ preventScroll: false }); } catch (e) { node.focus(); }
  }

  /** A new line from the template (at the end, or after `after`), filled from `values`. */
  function addRow(el, values, after) {
    var cfg = cfgOf(el);
    var body = bodyOf(el);
    var tpl = el.querySelector(":scope > template[data-gk-lines-template]");
    if (!cfg || cfg.readonly || !body || !tpl) return null;
    if (cfg.max !== null && cfg.max !== undefined && rowsOf(el).length >= cfg.max) {
      if (GK.toast) GK.toast.warning(say(cfg.text.err_max_rows, { n: cfg.max }));
      return null;
    }
    var li = tpl.content.firstElementChild.cloneNode(true);
    rekey(li, cfg, nextKey());
    if (values) {
      Object.keys(values).forEach(function (k) { if (k !== "id") setField(li, cfg, k, values[k]); });
      if (values.id !== undefined && values.id !== null) {
        var id = document.createElement("input");
        id.type = "hidden";
        id.setAttribute("data-gk-lines-field", "id");
        id.value = String(values.id);
        li.querySelector(".gk-lines-line").appendChild(id);
      }
    }
    body.insertBefore(li, after ? after.nextSibling : null);
    wake(li);
    renumber(el);
    update(el, true);
    return li;
  }

  function duplicateRow(el, li) {
    var cfg = cfgOf(el);
    if (cfg.max !== null && cfg.max !== undefined && rowsOf(el).length >= cfg.max) return null;
    var copy = li.cloneNode(true);
    // What cloneNode leaves behind or takes along by mistake: the handle sortierbar puts in (it adds
    // its own), the line's id (a copy is a new line), the state of the article search, and what was
    // typed into a select or a textarea, which is not an attribute.
    Array.prototype.forEach.call(copy.querySelectorAll(":scope > .gk-sort-griff"), function (h) { h.remove(); });
    copy.classList.remove("gk-sort-zieht");
    var id = fieldOf(copy, "id");
    if (id) id.remove();
    var from = li.querySelectorAll("[data-gk-lines-field]");
    var to = copy.querySelectorAll("[data-gk-lines-field]");
    for (var k = 0; k < from.length && k < to.length; k++) to[k].value = from[k].value;
    var search = li.querySelector(".gk-ajax-search-input");
    var searchCopy = copy.querySelector(".gk-ajax-search-input");
    if (search && searchCopy) searchCopy.value = search.value;
    copy.querySelectorAll(".gk-select-dropdown").forEach(function (d) { d.style.display = ""; });
    copy.querySelectorAll('[aria-expanded="true"]').forEach(function (d) { d.setAttribute("aria-expanded", "false"); });
    rekey(copy, cfg, nextKey());
    li.parentNode.insertBefore(copy, li.nextSibling);
    wake(copy);
    renumber(el);
    update(el, true);
    focus(firstFocusable(copy));
    return copy;
  }

  function deleteRow(el, li, trigger) {
    var cfg = cfgOf(el);
    var rows = rowsOf(el);
    if (rows.length <= cfg.min) return;
    var index = rows.indexOf(li);
    var n = index + 1;
    var next = rows[index + 1] || rows[index - 1] || null;
    li.remove();
    renumber(el);
    update(el, true);
    // The caret stays where the hand is: on the same button of the line that took this one's place.
    var same = next && trigger ? next.querySelector('[data-gk-lines-action="' + trigger + '"]') : null;
    focus(same && !same.disabled ? same : next ? firstFocusable(next) : el.querySelector('[data-gk-lines-action="add"]'));
    offerUndo(el, cfg, say(cfg.text.deleted, { n: n }), function () {
      if (li.isConnected) return;
      if (cfg.max !== null && cfg.max !== undefined && rowsOf(el).length >= cfg.max) {
        if (GK.toast) GK.toast.warning(say(cfg.text.err_max_rows, { n: cfg.max }));
        return;
      }
      var body = bodyOf(el);
      body.insertBefore(li, rowsOf(el)[index] || null);
      renumber(el);
      update(el, true);
      focus(firstFocusable(li));
    });
  }

  /**
   * "Line 2 deleted." with Undo, in GridKit's own toast. GK.toast.show() draws the toast and returns
   * nothing, so the button goes into the newest toast it drew — in front of the close button.
   */
  function offerUndo(el, cfg, text, undo) {
    if (!GK.toast || typeof GK.toast.show !== "function") return;
    GK.toast.show(text, "info", 8000);
    var toast = GK.toast.container && GK.toast.container.lastElementChild;
    if (!toast) return;
    var button = document.createElement("button");
    button.type = "button";
    button.setAttribute("data-gk-lines-undo", "");
    button.textContent = cfg.text.undo;
    button.addEventListener("click", function () {
      undo();
      var close = toast.querySelector(".gk-toast-close");
      if (close) close.click();
      else toast.remove();
    });
    toast.insertBefore(button, toast.querySelector(".gk-toast-close"));
  }

  /* ── Listening ───────────────────────────────────────────────────────── */

  /**
   * One update for what arrives as several events at once: an article picked fires change on the
   * value it posts and then gk-select; a cleared one only change. A microtask later, once.
   */
  function soon(el) {
    if (el._gkLinesSoon) return;
    el._gkLinesSoon = true;
    Promise.resolve().then(function () {
      el._gkLinesSoon = false;
      renumber(el);
      update(el, true);
    });
  }

  /**
   * The listeners of one editor, on the editor itself: a click anywhere else on the page runs none
   * of this. (On the document they were measurable in the timing of js/sortierbar.js's own checks,
   * ci/browser-sortierbar.js S06, which reads the order between a drop and the answer to it.)
   */
  function listen(el) {
    // A node of this editor, not of one nested in it.
    var own = function (node) { return !!(node && node.closest && node.closest(ROOT) === el); };

    el.addEventListener("click", function (e) {
      var button = e.target.closest ? e.target.closest("[data-gk-lines-action]") : null;
      if (!own(button) || button.disabled) return;
      var action = button.getAttribute("data-gk-lines-action");
      var li = button.closest(".gk-lines-row");
      var added;
      if (action === "add") {
        added = addRow(el, null, null);
        if (added) focus(firstFocusable(added));
      } else if (action === "duplicate" && li) duplicateRow(el, li);
      else if (action === "delete" && li) deleteRow(el, li, "delete");
    });

    el.addEventListener("input", function (e) {
      var f = e.target;
      if (!own(f) || !f.matches("[data-gk-lines-field], [data-gk-lines-adjust]")) return;
      grow(f);
      if (f.getAttribute("aria-invalid") === "true") f._gkLinesShown = true;
      if (f.hasAttribute("data-gk-lines-field")) renumber(el);
      update(el, true);
    });

    // Leaving a field: the figure in the page's shape, and its message if there is one.
    el.addEventListener("change", function (e) {
      var f = e.target;
      if (!own(f) || !f.matches("input[data-gk-lines-field], input[data-gk-lines-adjust], textarea[data-gk-lines-field]")) return;
      var cfg = cfgOf(el);
      if (f.classList.contains("gk-select-value-input")) {
        soon(el);
        return;
      }
      reformat(cfg, f);
      f._gkLinesShown = true;
      show(f, check(el, cfg, f));
    });

    // A form that is sent with something wrong: each field says why (the browser shows one bubble).
    // invalid does not bubble: hence the capture.
    el.addEventListener("invalid", function (e) {
      var f = e.target;
      if (!own(f)) return;
      f._gkLinesShown = true;
      show(f, check(el, cfgOf(el), f) || f.validationMessage);
    }, true);

    // Enter in a field of a line does not send the form; in the last field of the last line it adds one.
    el.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" || e.defaultPrevented || e.isComposing || e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) return;
      var f = e.target;
      if (!own(f) || !f.matches("input, select") || f.matches("[type=button], [type=submit]")) return;
      e.preventDefault();
      var li = f.closest(".gk-lines-row");
      if (!li || !f.hasAttribute("data-gk-lines-field")) return;
      var rows = rowsOf(el);
      var fields = fieldsOf(li);
      if (li === rows[rows.length - 1] && f === fields[fields.length - 1]) {
        reformat(cfgOf(el), f);
        focus(firstFocusable(addRow(el, null, null) || li));
      }
    });

    // An article picked in a lookup column fills its line. GK.ajaxSelect fires gk-select on the select,
    // without bubbling: hence the capture.
    el.addEventListener("gk-select", function (e) {
      var wrap = e.target;
      var li = wrap && wrap.closest ? wrap.closest(".gk-lines-row") : null;
      var cell = wrap && wrap.closest ? wrap.closest("[data-gk-lines-cell]") : null;
      if (!own(li) || !cell) return;
      var cfg = cfgOf(el);
      var col = cfg.byKey[cell.getAttribute("data-gk-lines-cell")];
      var item = e.detail || {};
      if (!col || !col.fill) return;
      Object.keys(col.fill).forEach(function (target) {
        if (Object.prototype.hasOwnProperty.call(item, col.fill[target])) setField(li, cfg, target, item[col.fill[target]]);
      });
      var qtyKey = roleKey(cfg, "qty");
      var qty = qtyKey ? fieldOf(li, qtyKey) : null;
      if (qty && phpTrim(qty.value) === "") qty.value = display("1", 0, cfg.fmt);
      // A field that carried a message is checked again at once: the article may have mended it.
      fieldsOf(li).forEach(function (f) { if (f.getAttribute("aria-invalid") === "true") f._gkLinesShown = true; });
      soon(el);
    }, true);

    // A line put in a new place by js/sortierbar.js.
    el.addEventListener("gk:sortiert", function (e) {
      if (e.target !== bodyOf(el)) return;
      renumber(el);
      update(el, true);
    });
  }

  /* ── Starting ────────────────────────────────────────────────────────── */

  function setup(el) {
    if (el._gkLinesReady) return;
    var cfg = cfgOf(el);
    if (!cfg) return;
    el._gkLinesReady = true;
    if (cfg.readonly) return;
    el.setAttribute("data-gk-lines-ready", "");
    listen(el);
    rowsOf(el).forEach(function (li) { li.querySelectorAll("textarea[data-gk-lines-field]").forEach(grow); });
    var body = bodyOf(el);
    // While a line is moved with the keyboard, its number follows it.
    if (body) new MutationObserver(function () { renumber(el); }).observe(body, { childList: true });
    update(el, false);
  }

  function scan(root) {
    if (!root || root.nodeType !== 1) return;
    if (root.matches(ROOT)) setup(root);
    root.querySelectorAll(ROOT).forEach(setup);
  }

  function resolve(target) {
    var node = typeof target === "string" ? document.querySelector(target) : target;
    var el = node && node.closest ? node.closest(ROOT) : null;
    if (el) scan(el);
    return el;
  }

  GK.lineItems = {
    /** LineItems::totals() of what the editor holds now. */
    totals: function (target) {
      var el = resolve(target);
      return el ? compute(el).totals : null;
    },
    /** The lines that count, as parse() returns them as 'items'. */
    items: function (target) {
      var el = resolve(target);
      return el ? compute(el).items : [];
    },
    /** A new line at the end, filled from machine values ({ description: "…", qty: 2, price: "12.5" }). */
    add: function (target, row) {
      var el = resolve(target);
      return el ? addRow(el, row || {}, null) : null;
    },
    /** LineItems::totals($rows, $opts) without an editor. */
    calc: calc,
    /** LineItems::parseNumber($text, $locale). */
    parse: function (text, locale) {
      return parseNumber(text, decOf(locale));
    },
  };

  // An editor that arrives later — a modal, a sheet, AJAX navigation — comes through GK.initContent,
  // like every other component (wrapped, not changed: the sticky head of 1.107.0 does the same).
  // Not a MutationObserver on the whole document: one there slowed every change of the page by
  // enough to move the timing of js/sortierbar.js's own checks (ci/browser-sortierbar.js, S06).
  var initContent = GK.initContent;
  if (typeof initContent === "function") {
    GK.initContent = function (root) {
      var result = initContent.apply(GK, arguments);
      scan(root && root.nodeType === 1 ? root : document.documentElement);
      return result;
    };
  }
  _gkReady(function () { scan(document.documentElement); });
})();
// === 1.118.0 — one pager (Table::paginate) ===
/*
 * The pager of a paginated table is the bar of GridKit\Pagination now: Table::renderPager() writes
 * .gk-rowpager with the count on the left ("6–10 of 12", pagination.range) and the .gk-pg buttons
 * on the right, first and last included; css/gridkit.css dresses it in the block "1.118.0 — one
 * pager". This block writes the same markup, attribute for attribute, when renderStatic() rebuilds
 * a setData() table after a sort, a search, a filter or a page (ci/browser-pager.js compares the
 * two). A server table's AJAX reload brings the server's own pager and needs nothing from here.
 *
 * Nothing above is changed. Replaced or wrapped from here:
 *   GK.table._staticPager   the new markup. renderStatic() hands it the page and the number of
 *                           pages, and the count needs more: the rows that match (wrap._gkMatching,
 *                           which renderStatic sets before it asks) and the page size. So the
 *                           wrap being drawn is kept aside while renderStatic runs.
 *   GK.table.renderStatic   that, and the focus. A page button pressed from the keyboard went
 *   GK.table.reload         with the pager it stood in, and the focus fell to the page: a person
 *                           paging with Enter started again from the top after every page. It goes
 *                           to the same control of the new pager now, or to the page just chosen
 *                           when that control is disabled (Next on the last page). A reload lands
 *                           later; a MutationObserver on the wrap sees the old pager go, as the
 *                           sticky head's sees the rows go, and gives the focus back once, and only
 *                           if nothing else has taken it in the meantime.
 *
 * The click handler stays as it was: it reads data-gk-page and skips a disabled button, and the
 * new buttons carry both. A copy of this file without the block (the PawBot dashboard keeps its
 * own) pages a table from the new Table.php all the same: its renderStatic() removes
 * .gk-pagination, which the new nav carries, before it writes its own pager, and the stylesheet
 * gives that older pager the same boxes.
 */
(function () {
  "use strict";
  if (!window.GK || !GK.table || typeof GK.table.renderStatic !== "function") return;

  function text(key, fallback, params) {
    var out = (window.GK_LANG || {})[key] || fallback;
    for (var k in params || {}) out = out.replace("{" + k + "}", params[k]);
    return out;
  }

  /** htmlspecialchars(ENT_QUOTES), as Table.php escapes. */
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c];
    });
  }

  /** A whole number as number_format() writes it there, with the catalogue's separator (which may be none). */
  function num(n) {
    var sep = (window.GK_LANG || {}).format_thousands;
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, typeof sep === "string" ? sep : ",");
  }

  /** Table::renderPager() in the browser. The page is never past the end here: renderStatic() keeps it in range. */
  function pagerHtml(page, pages, total, perPage) {
    var count = perPage > 0 && total > 0
      ? text("pagination_range", "{from}–{to} of {total}", {
          from: num((page - 1) * perPage + 1),
          to: num(Math.min(total, page * perPage)),
          total: num(total),
        })
      : "";
    function icon(glyph, target, key, fallback, off) {
      // A title on the ones that lead somewhere, as PHP writes it (1.118.1).
      return '<button type="button" class="gk-pg gk-pg-icon' + (off ? " gk-pg-off" : "") + '" data-gk-page="' + target + '"' +
        ' aria-label="' + esc(text(key, fallback)) + '"' +
        (off ? " disabled" : ' title="' + esc(text(key, fallback)) + '"') + ">" +
        '<span class="material-icons" aria-hidden="true">' + glyph + "</span></button>";
    }
    var html = '<nav class="gk-pagination gk-rowpager" aria-label="' + esc(text("pagination_aria", "Page navigation")) + '">' +
      '<span class="gk-rowpager-info"><span class="gk-rowpager-count">' + esc(count) + "</span></span>" +
      '<div class="gk-rowpager-nav">' +
      icon("first_page", 1, "pagination_first", "First page", page <= 1) +
      icon("chevron_left", Math.max(1, page - 1), "pagination_prev", "Previous", page <= 1);
    var show = [1, pages];
    for (var i = page - 2; i <= page + 2; i++) if (i >= 1 && i <= pages) show.push(i);
    show = show.filter(function (n, at) { return show.indexOf(n) === at; }).sort(function (a, b) { return a - b; });
    var previous = 0;
    show.forEach(function (n) {
      if (previous && n - previous > 1) html += '<span class="gk-pg-gap">…</span>';
      html += '<button type="button" class="gk-pg' + (n === page ? " gk-pg-active" : "") + '" data-gk-page="' + n + '"' +
        ' aria-label="' + esc(text("pagination_page_of", "Page {page} of {total}", { page: n, total: pages })) + '"' +
        (n === page ? ' aria-current="page"' : "") + ">" + n + "</button>";
      previous = n;
    });
    return html +
      icon("chevron_right", Math.min(pages, page + 1), "pagination_next", "Next", page >= pages) +
      icon("last_page", pages, "pagination_last", "Last page", page >= pages) +
      "</div></nav>";
  }

  var drawing = null; // the wrap renderStatic() is drawing

  // Called on its own, outside a rebuild, it has no rows to count: the bar with an empty count.
  GK.table._staticPager = function (page, pages) {
    var data = drawing && drawing._gkData;
    var perPage = data ? parseInt(data.perPage, 10) || 0 : 0;
    var total = drawing && drawing._gkMatching ? drawing._gkMatching.length : 0;
    return pagerHtml(page, pages, total, perPage);
  };

  /** The control of this wrap's pager that has the focus: its pager and its glyph ("" for a page number). */
  function focused(wrap) {
    var el = document.activeElement;
    if (!el || !el.matches(".gk-pagination [data-gk-page]") || !wrap.contains(el)) return null;
    var glyph = el.querySelector(".material-icons");
    return { nav: el.closest(".gk-pagination"), glyph: glyph ? glyph.textContent : "" };
  }

  /** The same control in the wrap's new pager, or the current page where that one is switched off. */
  function refocus(wrap, was) {
    var nav = wrap.querySelector(".gk-pagination");
    if (!nav) return; // the rows fit one page now: no pager, nothing to go back to
    var target = null;
    if (was.glyph) {
      nav.querySelectorAll("[data-gk-page]").forEach(function (b) {
        var g = b.querySelector(".material-icons");
        if (g && g.textContent === was.glyph) target = b;
      });
    }
    if (!target || target.disabled) target = nav.querySelector('[aria-current="page"]');
    target.focus();
  }

  var renderStatic = GK.table.renderStatic;
  GK.table.renderStatic = function (wrap) {
    var was = focused(wrap);
    var outer = drawing;
    drawing = wrap;
    try {
      var result = renderStatic.apply(this, arguments);
    } finally {
      drawing = outer;
    }
    if (was) refocus(wrap, was);
    return result;
  };

  var reload = GK.table.reload;
  GK.table.reload = function (wrap) {
    var was = (wrap._gkPagerFocus = focused(wrap));
    if (was && !wrap._gkPagerWatch) {
      wrap._gkPagerWatch = new MutationObserver(function () {
        var w = wrap._gkPagerFocus;
        if (!w || w.nav.isConnected) return; // not landed yet
        wrap._gkPagerFocus = null;
        var el = document.activeElement;
        // Taken by something else in the meantime (the search field, a dialog): left there.
        if (el && el !== document.body) return;
        refocus(wrap, w);
      });
      wrap._gkPagerWatch.observe(wrap, { childList: true });
    }
    return reload.apply(this, arguments);
  };
})();
