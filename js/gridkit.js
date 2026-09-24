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
              wrap._gkPage = 1;
              wrap._gkFilters = {};
              wrap._gkRestoreSort = _savedSort && _savedSort.col ? true : false;
            } catch (e) {
              /* ignore */
            }
          }
        }

        if (isStatic && wrap._gkData && wrap._gkRestoreSort) {
          setTimeout(() => this.renderStatic(wrap), 0);
        }

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

        // Paging — after filtering, searching and sorting, so the page window
        // is a window onto what the user is actually looking at.
        const perPage = parseInt(data.perPage, 10) || 0;
        const total = rows.length;
        let page = wrap._gkPage || 1;
        const pages = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
        if (page > pages) page = pages;
        if (page < 1) page = 1;
        wrap._gkPage = page;
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
              return val ? e(new Date(val).toLocaleDateString("de-DE")) : "";
            case "datetime":
              return val
                ? e(
                    new Date(val).toLocaleString("de-DE", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                    }),
                  )
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
        if (hasLeft) html += actionsHead;
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
              const tdStyles = [];
              if (col.align) tdStyles.push("text-align:" + e(col.align));
              if (col.width && col.width !== "auto") tdStyles.push("width:" + e(col.width));
              if (col.minWidth) tdStyles.push("min-width:" + e(col.minWidth));
              if (col.maxWidth) tdStyles.push("max-width:" + e(col.maxWidth));
              if (col.nowrap || col.format === "number" || col.format === "currency") tdStyles.push("white-space:nowrap");
              const align = tdStyles.length ? ' style="' + tdStyles.join(";") + '"' : "";
              // Same order as Table::render writes them — a byte-for-byte
              // comparison of the two renderers is only possible if they agree.
              const tdCls = [];
              if (col.format === "number" || col.format === "currency")
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
          default:
            return (
              '<span class="material-icons" style="font-size:16px;vertical-align:middle;">' +
              name +
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
            window.history.replaceState(null, "", url);
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
      // An empty region hidden in the markup is shown now, before any message;
      // one with words in it the page hid on purpose stays as it is.
      _gkAnnounceRegion(el, el.textContent === "");
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
          window.history.replaceState(null, "", displayUrl);
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
      window.history.replaceState(null, "", displayUrl);
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
        var page = 1, query = "";
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
    // Defined further down, so absent on the very first GK.init() of an
    // already-loaded document; the ready-aware call beside its definition
    // covers that pass, and this one covers everything added later.
    if (GK.accordion) GK.accordion.init(root);
    if (GK.lightbox && GK.lightbox.init) GK.lightbox.init(root);
    if (GK.rowPager) GK.rowPager.init(root);
    GK.modal.upgradeStatic(root);
    if (GK.sheet) GK.sheet.upgrade(root);
    _gkAnnounceInit(root);
  };

  var _origInit = GK.init;
  GK.init = function () {
    _origInit.call(GK);
    GK.initContent(document);
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
    init(root) {
      var self = this;
      (root || document).querySelectorAll(".gk-tabs").forEach(function (wrap) {
        var nav = wrap.querySelector(".gk-tab-nav");
        if (!nav || nav._gkTabsA11y) return;
        nav._gkTabsA11y = true;
        var uid = "gk-tabs-m" + ++self._n;
        nav.setAttribute("role", "tablist");
        var btns = Array.prototype.slice.call(nav.querySelectorAll(".gk-tab-btn"));
        btns.forEach(function (b, i) {
          var panel = wrap.querySelector('.gk-tab-panel[data-tab="' + b.dataset.tab + '"]');
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
        nav.addEventListener("keydown", function (e) {
          var i = btns.indexOf(document.activeElement);
          if (i < 0) return;
          var to = -1;
          if (e.key === "ArrowRight") to = (i + 1) % btns.length;
          else if (e.key === "ArrowLeft") to = (i - 1 + btns.length) % btns.length;
          else if (e.key === "Home") to = 0;
          else if (e.key === "End") to = btns.length - 1;
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
    var tabs = btn.closest(".gk-tabs");
    if (!tabs) return;
    var target = btn.dataset.tab;
    tabs.querySelectorAll(".gk-tab-btn").forEach(function (b) {
      b.classList.remove("gk-active");
      // Only where init() put a role on: see the note above.
      if (b.getAttribute("role") === "tab") {
        b.setAttribute("aria-selected", "false");
        b.tabIndex = -1;
      }
    });
    tabs.querySelectorAll(".gk-tab-panel").forEach(function (p) {
      p.classList.remove("gk-active");
    });
    btn.classList.add("gk-active");
    if (btn.getAttribute("role") === "tab") {
      btn.setAttribute("aria-selected", "true");
      btn.tabIndex = 0;
    }
    var panel = tabs.querySelector('.gk-tab-panel[data-tab="' + target + '"]');
    if (panel) panel.classList.add("gk-active");
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
        '<img class="gk-lightbox-img" src="" alt="">' +
        '<button class="gk-lightbox-nav gk-lightbox-next" aria-label="' + _gkEsc(_t("lightbox_next")) + '">' +
        '<span class="material-icons" aria-hidden="true">chevron_right</span></button>' +
        // The caption and the counter change as you page through, and nothing
        // said so. Announced politely, they now read out on each step.
        '<div class="gk-lightbox-caption" aria-live="polite"></div>' +
        '<div class="gk-lightbox-counter" aria-live="polite"></div>';
      document.body.appendChild(lb);
      lb.querySelector(".gk-lightbox-close").addEventListener("click", closeLb);
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
        if (e.target === lb) closeLb();
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
      lb.querySelector(".gk-lightbox-counter").textContent =
        current + 1 + " / " + items.length;
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

    function closeLb() {
      if (lb) lb.classList.remove("open");
      document.body.style.overflow = "";
      // Back to the thumbnail it was opened from.
      _gkRestoreFocus(opener);
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

    // Expose for external use
    window.GK = window.GK || {};
    GK.lightbox = { open: showLb, close: closeLb, init: decorate };
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
    var msg = _t("field_required").replace("{name}", label);
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
