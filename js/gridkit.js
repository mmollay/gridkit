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

  const GK = {
    // === MODAL ===
    modal: {
      stack: [],
      init() {
        document.addEventListener("keydown", (e) => {
          if (e.key === "Escape" && this.stack.length) this.close();
        });
      },
      _createOverlay() {
        var ov = document.createElement("div");
        ov.className = "gk-modal-overlay";
        ov.style.zIndex = 9000 + this.stack.length * 10;
        ov.innerHTML =
          '<div class="gk-modal" data-gk-modal-container>' +
          '<div class="gk-modal-header"><h3 class="gk-modal-title" data-gk-modal-title-el></h3>' +
          '<button class="gk-modal-close" data-gk-modal-close>&times;</button></div>' +
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
      open(title, url, params, size) {
        var ov = this._createOverlay();
        var container = ov.querySelector("[data-gk-modal-container]");
        var titleEl = ov.querySelector("[data-gk-modal-title-el]");
        var body = ov.querySelector("[data-gk-modal-body]");
        titleEl.textContent = title;
        container.className = "gk-modal gk-modal-" + (size || "medium");
        body.innerHTML = "";
        body.classList.add("gk-loading");
        this.stack.push(ov);

        var fd = new FormData();
        if (params) Object.entries(params).forEach(([k, v]) => fd.append(k, v));

        fetch(url, {
          method: "POST",
          body: fd,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        })
          .then((r) => r.text())
          .then((html) => {
            body.classList.remove("gk-loading");
            body.innerHTML = html;
            GK.form.bind(body);
            GK.table.init(body);
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
        ov.remove();
      },
      closeAll() {
        while (this.stack.length) this.close();
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
        form
          .querySelectorAll(".gk-has-error")
          .forEach((el) => el.classList.remove("gk-has-error"));

        const btn = form.querySelector('[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn._origText = btn.textContent;
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
              GK.modal.close();
              GK.table.refreshAll();
            } else if (data.errors) {
              Object.entries(data.errors).forEach(([field, msg]) => {
                const errEl = form.querySelector(`[data-gk-error="${field}"]`);
                if (errEl) errEl.textContent = msg;
                const input = form.querySelector(`[name="${field}"]`);
                if (input) input.classList.add("gk-has-error");
              });
            }
          })
          .catch(() => alert(_t("error_saving")))
          .finally(() => {
            if (btn) {
              btn.disabled = false;
              btn.textContent = btn._origText;
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
          GK.modal.open(
            tpl.dataset.gkModalTitle,
            tpl.dataset.gkModalUrl,
            params,
            tpl.dataset.gkModalSize,
          );
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

        // Pagination
        wrap.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-gk-page]");
          if (!btn) return;
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
              this.renderStatic(wrap);
            } else {
              const params = { gk_page: 1 };
              params["gk_filter_" + sel.dataset.gkFilter] = sel.value;
              this.reload(wrap, params);
            }
          });
        });

        // Multi-select
        if (wrap.hasAttribute("data-gk-selectable")) this.initSelectable(wrap);
      },

      initSelectable(wrap) {
        const selected = new Set();
        const bulkBar = wrap.querySelector(".gk-bulk-bar");

        function getRowId(row) {
          return row.dataset.gkRowId;
        }

        function updateBar() {
          if (!bulkBar) return;
          const n = selected.size;
          bulkBar.querySelector(".gk-bulk-count").textContent = _t("selected", {
            n: n,
          });
          bulkBar.style.display = n > 0 ? "flex" : "none";
          wrap.querySelectorAll("tbody tr[data-gk-row-id]").forEach((tr) => {
            tr.classList.toggle("gk-row-selected", selected.has(getRowId(tr)));
          });
          const all = wrap.querySelectorAll("tbody tr[data-gk-row-id]");
          const selAll = wrap.querySelector("[data-gk-select-all]");
          if (selAll) selAll.indeterminate = n > 0 && n < all.length;
          if (selAll) selAll.checked = n > 0 && n === all.length;
        }

        // Row checkboxes
        wrap.addEventListener("change", function (e) {
          if (e.target.tagName !== "INPUT" || e.target.type !== "checkbox")
            return;
          if (!e.target.closest("td.gk-cb-col")) return;
          const tr = e.target.closest("tr[data-gk-row-id]");
          if (!tr) return;
          if (e.target.checked) selected.add(getRowId(tr));
          else selected.delete(getRowId(tr));
          updateBar();
        });

        // Select-all checkbox
        const selAll = wrap.querySelector("[data-gk-select-all]");
        if (selAll) {
          selAll.addEventListener("change", function () {
            wrap.querySelectorAll("tbody tr[data-gk-row-id]").forEach((tr) => {
              const cb = tr.querySelector("td.gk-cb-col input[type=checkbox]");
              if (this.checked) {
                selected.add(getRowId(tr));
                if (cb) cb.checked = true;
              } else {
                selected.delete(getRowId(tr));
                if (cb) cb.checked = false;
              }
            });
            updateBar();
          });
        }

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
            if (selAll) {
              selAll.checked = false;
              selAll.indeterminate = false;
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
        if (query) {
          rows = rows.filter((row) => {
            return colKeys.some((key) => {
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

        // Build HTML
        const e = (s) => {
          const d = document.createElement("div");
          d.textContent = String(s);
          return d.innerHTML;
        };

        const formatVal = (val, col) => {
          const fmt = col.format || null;
          if (!fmt) return e(val);
          switch (fmt) {
            case "currency":
              return e(
                parseFloat(val || 0).toLocaleString("de-DE", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                }) + " €",
              );
            case "percent":
              return e(parseInt(val || 0) + "%");
            case "date":
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
            default:
              return e(val);
          }
        };

        const renderLabel = (val, custom) => {
          const v = String(val || "")
            .toLowerCase()
            .trim();
          const map = {
            green: [
              "aktiv",
              "bezahlt",
              "paid",
              "ja",
              "yes",
              "1",
              "true",
              "gesendet",
              "delivered",
            ],
            orange: ["offen", "pending", "entwurf", "draft", "warnung"],
            red: [
              "storniert",
              "cancelled",
              "überfällig",
              "overdue",
              "fehler",
              "error",
            ],
            gray: ["inaktiv", "0", "false", "nein", "no"],
          };
          let color = custom[v] || null;
          if (!color) {
            for (const [c, vals] of Object.entries(map)) {
              if (vals.includes(v)) {
                color = c;
                break;
              }
            }
          }
          color = color || "gray";
          return (
            '<span class="gk-label gk-label-' +
            e(color) +
            '">' +
            e(val) +
            "</span>"
          );
        };

        // Determine next sort direction for headers
        const sortCol = sort.col || "";
        const sortDir = sort.dir || "asc";

        let html = '<table class="gk-table"><thead><tr>';
        for (const [key, col] of Object.entries(columns)) {
          const style = col.width ? ' style="width:' + e(col.width) + '"' : "";
          const sortable = col.sortable || false;
          let cls = "",
            attrs = "";
          if (sortable) {
            const newDir =
              sortCol === key && sortDir === "asc" ? "desc" : "asc";
            attrs =
              ' data-gk-sort="' + e(key) + '" data-gk-dir="' + newDir + '"';
            if (sortCol === key) {
              cls =
                ' class="gk-sortable gk-sorted-' +
                sortDir +
                (col.hideOnMobile ? " gk-hide-mobile" : "") +
                '"';
            } else {
              cls =
                ' class="gk-sortable' +
                (col.hideOnMobile ? " gk-hide-mobile" : "") +
                '"';
            }
          } else if (col.hideOnMobile) {
            cls = ' class="gk-hide-mobile"';
          }
          html += "<th" + cls + style + attrs + ">" + e(col.label) + "</th>";
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
        if (hasLeft) html += '<th class="gk-actions-col"></th>';
        if (hasRight) html += '<th class="gk-actions-col"></th>';
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
            const color = colorMap[bopts["class"]] || "neutral";
            let cls = hasText
              ? "gk-btn gk-btn-icon-text gk-btn-text gk-btn-" +
                color +
                " gk-btn-sm"
              : "gk-btn gk-btn-icon-only gk-btn-text gk-btn-" +
                color +
                " gk-btn-sm";
            const params = {};
            if (bopts.params) {
              Object.entries(bopts.params).forEach(([pk, pcol]) => {
                params[pk] = row[pcol] ?? "";
              });
            }
            let btnAttrs = ' type="button"';
            btnAttrs += ' data-gk-action="' + e(bname) + '"';
            if (bopts.modal)
              btnAttrs += ' data-gk-modal="' + e(bopts.modal) + '"';
            if (bopts.title) btnAttrs += ' title="' + e(bopts.title) + '"';
            btnAttrs += " data-gk-params='" + e(JSON.stringify(params)) + "'";
            const icon = bopts.icon ? GK.table.iconSvg(bopts.icon) : "";
            const text = hasText ? "<span>" + e(bopts.text) + "</span>" : "";
            h +=
              '<button class="' +
              cls +
              '"' +
              btnAttrs +
              ">" +
              icon +
              text +
              "</button>";
          }
          return h;
        };

        if (rows.length === 0) {
          const colspan =
            colKeys.length + (hasLeft ? 1 : 0) + (hasRight ? 1 : 0);
          html +=
            '<tr><td colspan="' +
            colspan +
            '" class="gk-empty">' +
            _t("no_entries") +
            "</td></tr>";
        } else {
          rows.forEach((row) => {
            html += "<tr>";
            if (hasLeft)
              html +=
                '<td class="gk-actions gk-actions-left"><div class="gk-btn-group">' +
                renderBtnGroup(leftBtns, row) +
                "</div></td>";
            for (const [key, col] of Object.entries(columns)) {
              const val = row[key] ?? "";
              const align = col.align
                ? ' style="text-align:' + e(col.align) + '"'
                : "";
              var hideCls = col.hideOnMobile ? ' class="gk-hide-mobile"' : "";
              html +=
                "<td" +
                hideCls +
                align +
                ' data-label="' +
                e(col.label) +
                '">' +
                formatVal(val, col) +
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

        html += "</tbody></table>";

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
      },

      iconSvg(name) {
        switch (name) {
          case "pencil":
          case "edit":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
          case "trash":
          case "delete":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>';
          case "plus":
          case "add":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>';
          case "eye":
          case "visibility":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
          case "download":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>';
          case "upload":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>';
          case "copy":
          case "content_copy":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
          case "mail":
          case "email":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
          case "search":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
          case "settings":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
          case "open_in_new":
          case "external":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
          case "auto_awesome":
          case "generate":
          case "wand":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg>';
          case "login":
          case "impersonate":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
          case "print":
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 6,2 18,2 18,9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>';
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

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then((r) => r.text())
          .then((html) => {
            const toolbar = wrap.querySelector(".gk-toolbar");
            const templates = wrap.querySelectorAll("template");
            Array.from(wrap.children).forEach((ch) => {
              if (
                ch !== toolbar &&
                ch.tagName !== "TEMPLATE" &&
                ch.tagName !== "SCRIPT"
              )
                ch.remove();
            });
            toolbar.insertAdjacentHTML("afterend", html);
            window.history.replaceState(null, "", url);
          });
      },
      refreshAll() {
        document.querySelectorAll("[data-gk-table]").forEach((wrap) => {
          if (wrap.hasAttribute("data-gk-static") && wrap._gkData) {
            this.renderStatic(wrap);
          } else {
            this.reload(wrap, {});
          }
        });
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
      el.innerHTML =
        '<span class="material-icons gk-toast-icon">' +
        (icons[type] || "info") +
        "</span>" +
        "<span>" +
        message +
        "</span>" +
        '<button class="gk-toast-close">&times;</button>';
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
          try {
            localStorage.setItem("gk-nav-" + id, collapsed ? "closed" : "open");
          } catch (e) {}
        });
        // Restore state
        var id = btn.getAttribute("data-gk-toggle");
        var sub = document.getElementById(id);
        if (!sub) return;
        var stored = localStorage.getItem("gk-nav-" + id);
        if (stored === "closed") {
          sub.classList.add("collapsed");
          btn.classList.add("collapsed");
        } else if (stored === "open") {
          sub.classList.remove("collapsed");
          btn.classList.remove("collapsed");
        }
      });
    },
    toggle() {
      if (!this.el) return;
      this.el.classList.toggle("open");
      if (this.overlay) this.overlay.classList.toggle("open");
    },
    close() {
      if (!this.el) return;
      this.el.classList.remove("open");
      if (this.overlay) this.overlay.classList.remove("open");
    },
    open() {
      if (!this.el) return;
      this.el.classList.add("open");
      if (this.overlay) this.overlay.classList.add("open");
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
        // Nur interne Links abfangen
        if (!href || href.startsWith('#') || href.startsWith('javascript')) return;
        if (href.startsWith('http') && !href.startsWith(location.origin)) return;
        if (link.target === '_blank') return;

        // onclick statt addEventListener — preventDefault SOFORT
        link.onclick = function (e) {
          if (e.ctrlKey || e.metaKey || e.shiftKey) return true;
          e.preventDefault();
          e.stopPropagation();
          self.load(href, true);
          return false;
        };
      });

      window.addEventListener('popstate', function () {
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
        self._render(html, url, content, pushState);
      };
      xhr.onerror = function () { self.hideProgress(); };
      xhr.send();
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
        self.updateActive(url);
        window.scrollTo(0, 0);
        try {
          if (typeof GK.table !== 'undefined' && GK.table.init) GK.table.init();
          if (typeof GK.tooltip !== 'undefined' && GK.tooltip.init) GK.tooltip.init();
        } catch (e) {}
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

  // Confirm dialog (replaces window.confirm)
  GK.confirm = function (message, options) {
    options = options || {};
    return new Promise(function (resolve) {
      var overlay = document.createElement("div");
      overlay.className = "gk-confirm-overlay";
      var title = options.title || _t("confirm_title");
      var confirmText = options.confirmText || _t("confirm_ok");
      var cancelText = options.cancelText || _t("confirm_cancel");
      var confirmClass = options.danger
        ? "gk-btn gk-btn-danger"
        : "gk-btn gk-btn-primary";
      overlay.innerHTML =
        '<div class="gk-confirm-box">' +
        '<div class="gk-confirm-header"><h3>' +
        title +
        "</h3></div>" +
        '<div class="gk-confirm-body"><p>' +
        message +
        "</p></div>" +
        '<div class="gk-confirm-footer">' +
        '<button class="gk-btn gk-confirm-cancel">' +
        cancelText +
        "</button>" +
        '<button class="' +
        confirmClass +
        ' gk-confirm-ok">' +
        confirmText +
        "</button>" +
        "</div></div>";
      document.body.appendChild(overlay);
      overlay.querySelector(".gk-confirm-cancel").onclick = function () {
        overlay.remove();
        resolve(false);
      };
      overlay.querySelector(".gk-confirm-ok").onclick = function () {
        overlay.remove();
        resolve(true);
      };
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) {
          overlay.remove();
          resolve(false);
        }
      });
      document.addEventListener("keydown", function handler(e) {
        if (e.key === "Escape") {
          overlay.remove();
          resolve(false);
          document.removeEventListener("keydown", handler);
        }
      });
      setTimeout(function () {
        overlay.querySelector(".gk-confirm-ok").focus();
      }, 50);
    });
  };

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

  // ── Hilfsfunktionen ──────────────────────────────────────────
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

  // ── Validierung ───────────────────────────────────────────────
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

    // Max Dateianzahl
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
          f.name +
            ": too large (" +
            GK._formatSize(f.size) +
            ", max. " +
            zone.dataset.gkMaxSize +
            ")",
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

      // Remove-Button (nur im Pending-State)
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

  // ── Queue-Status-Helpers (für App-Code) ──────────────────────
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

  // Legacy-Helpers (Rückwärtskompatibilität)
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
  // gk-richtext wird nun via CKEditor5 initialisiert (siehe Form.php)
  GK.initRichtext = function () {};

  // === LIVE TABLE ===
  //
  // AJAX-gefilterte Tabellen-Views. Search + Filter + Sort + Pagination ohne
  // Full-Page-Reload. Cursor bleibt beim Tippen, URL wird via replaceState
  // synchron gehalten.
  //
  // Usage (Beispiel):
  //   <div id="my-tbl" data-gk-live-table="/invoices">
  //     <!-- Tabelle, Sort-Header, Pagination — alles AJAX-swappable -->
  //   </div>
  //   <input data-gk-live-input="my-tbl" name="q">
  //   <select data-gk-live-input="my-tbl" name="status">...</select>
  //
  // Controller muss bei X-Requested-With: XMLHttpRequest oder ?partial=1 nur
  // den Container-Inhalt liefern (kein Layout).
  //
  GK.liveTable = {
    init: function (root) {
      var r = root || document;
      r.querySelectorAll("[data-gk-live-table]").forEach(function (c) {
        GK.liveTable.bind(c);
        GK.liveTable.restoreSession(c);
      });
      r.querySelectorAll("[data-gk-live-input]").forEach(function (inp) {
        GK.liveTable.bindInput(inp);
      });
      GK.liveTable.patchNavSelects(r);
    },
    // Session-Persistenz: wenn URL keine Filter hat (Sidebar-Klick), restauriere
    // den gespeicherten Stand der aktuellen Session.
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
          window.history.replaceState(null, "", restored);
          GK.liveTable.loadUrl(container, urlObj);
        }
      } catch (e) {}
    },
    saveSession: function (container) {
      try { sessionStorage.setItem("gkLive:" + container.id, window.location.search); } catch (e) {}
    },
    bind: function (container) {
      if (container._gkLiveBound) return;
      container._gkLiveBound = true;
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
    loadUrl: function (container, urlObj) {
      var fetchParams = new URLSearchParams(urlObj.searchParams);
      fetchParams.set("partial", "1");
      var displayParams = new URLSearchParams(urlObj.searchParams);
      displayParams.delete("partial");
      var displayUrl = urlObj.pathname + (displayParams.toString() ? "?" + displayParams.toString() : "");
      container.classList.add("gk-live-loading");
      fetch(urlObj.pathname + "?" + fetchParams.toString(), { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          container.innerHTML = html;
          window.history.replaceState(null, "", displayUrl);
          GK.liveTable.saveSession(container);
          container.dispatchEvent(new CustomEvent("gk-live-reloaded", { bubbles: true }));
          GK.liveTable.init(container);
        })
        .catch(function () {})
        .finally(function () { container.classList.remove("gk-live-loading"); });
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
      return params;
    },
    reload: function (container) {
      var baseUrl = container.dataset.gkLiveTable;
      var params = GK.liveTable.collectParams(container);
      params.set("partial", "1");
      var fetchUrl = baseUrl + "?" + params.toString();
      var displayUrl = baseUrl + "?" + new URLSearchParams(
        Array.from(params.entries()).filter(function (pair) { return pair[0] !== "partial"; })
      ).toString();
      container.classList.add("gk-live-loading");
      fetch(fetchUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          container.innerHTML = html;
          window.history.replaceState(null, "", displayUrl);
          GK.liveTable.saveSession(container);
          container.dispatchEvent(new CustomEvent("gk-live-reloaded", { bubbles: true }));
          GK.liveTable.init(container);
        })
        .catch(function () {})
        .finally(function () { container.classList.remove("gk-live-loading"); });
    },
  };

  // Extend init
  var _origInit = GK.init;
  GK.init = function () {
    _origInit.call(GK);
    GK.initRangeSliders();
    GK.initUploadZones();
    GK.initRichtext();
    if (GK.selectSearch) GK.selectSearch.init();
    if (GK.multiSelect) GK.multiSelect.init();
    if (GK.ajaxSelect) GK.ajaxSelect.init();
    if (GK.liveTable) GK.liveTable.init();
  };

  // Dropdown toggle (Header user menu etc.)
  document.addEventListener("click", function (e) {
    var dropdown = e.target.closest("[data-gk-dropdown]");
    document.querySelectorAll("[data-gk-dropdown].open").forEach(function (el) {
      if (el !== dropdown) el.classList.remove("open");
    });
    if (dropdown) dropdown.classList.toggle("open");
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
  GK.theme = {
    set(theme) {
      document.body.dataset.gkTheme = theme;
      try {
        localStorage.setItem("gk-theme", theme);
      } catch (e) {}
      document.querySelectorAll("[data-gk-set-theme]").forEach((b) => {
        b.classList.toggle("gk-theme-active", b.dataset.gkSetTheme === theme);
      });
    },
    toggleMode() {
      var mode = document.body.dataset.gkMode === "dark" ? "light" : "dark";
      document.body.dataset.gkMode = mode;
      try {
        localStorage.setItem("gk-mode", mode);
      } catch (e) {}
    },
    restore() {
      try {
        var theme = localStorage.getItem("gk-theme");
        var mode = localStorage.getItem("gk-mode");
        if (theme) this.set(theme);
        if (mode) document.body.dataset.gkMode = mode;
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
          var hidden = wrap.querySelector('input[type="hidden"]');
          var valueSpan = wrap.querySelector(".gk-select-value");
          var options = wrap.querySelectorAll(".gk-select-option");

          display.addEventListener("click", function () {
            if (wrap.hasAttribute("data-disabled")) return;
            display.classList.toggle("open");
            if (display.classList.contains("open")) {
              if (searchInput) {
                searchInput.value = "";
              }
              options.forEach((o) => o.classList.remove("hidden"));
              var empty = dropdown.querySelector(".gk-select-empty");
              if (empty) empty.remove();
              if (searchInput) setTimeout(() => searchInput.focus(), 50);
            }
          });

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
              options.forEach((o) => o.classList.remove("selected"));
              this.classList.add("selected");
              display.classList.remove("open");
              hidden.dispatchEvent(new Event("change", { bubbles: true }));
            });
          });

          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target)) display.classList.remove("open");
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
          var hidden = wrap.querySelector('input[type="hidden"]');
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
          var hidden = wrap.querySelector('input[type="hidden"]');
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

          input.addEventListener("input", function () {
            var q = this.value.trim();
            clearBtn.style.display = q ? "" : "none";
            if (q.length < minChars) {
              dropdown.style.display = "none";
              return;
            }
            clearTimeout(timer);
            timer = setTimeout(function () {
              loading.style.display = "";
              optionsContainer.innerHTML = "";
              dropdown.style.display = "block";
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
                  data.forEach((item) => {
                    var opt = document.createElement("div");
                    opt.className = "gk-select-option";
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

          optionsContainer.addEventListener("click", function (e) {
            var opt = e.target.closest(".gk-select-option");
            if (!opt) return;
            hidden.value = opt.dataset.value;
            input.value = opt.querySelector("div").textContent;
            dropdown.style.display = "none";
            clearBtn.style.display = "";
            hidden.dispatchEvent(new Event("change", { bubbles: true }));
            wrap.dispatchEvent(
              new CustomEvent("gk-select", {
                detail: JSON.parse(opt.dataset.json),
              }),
            );
          });

          if (clearBtn)
            clearBtn.addEventListener("click", function () {
              hidden.value = "";
              input.value = "";
              clearBtn.style.display = "none";
              dropdown.style.display = "none";
              hidden.dispatchEvent(new Event("change", { bubbles: true }));
            });

          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target)) dropdown.style.display = "none";
          });
        });
    },
  };

  // === TABS ===
  document.addEventListener("click", function (e) {
    var btn = e.target.closest(".gk-tab-btn");
    if (!btn) return;
    var tabs = btn.closest(".gk-tabs");
    if (!tabs) return;
    var target = btn.dataset.tab;
    tabs.querySelectorAll(".gk-tab-btn").forEach(function (b) {
      b.classList.remove("gk-active");
    });
    tabs.querySelectorAll(".gk-tab-panel").forEach(function (p) {
      p.classList.remove("gk-active");
    });
    btn.classList.add("gk-active");
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

  window.GridKit = GK;
  window.GK = GK;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      GK.init();
      GK.theme.restore();
      GK.layout.restore();
    });
  } else {
    GK.init();
    GK.theme.restore();
    GK.layout.restore();
  }

  // === ACCORDION ===
  document.querySelectorAll(".gk-accordion").forEach(function (acc) {
    acc.querySelectorAll(".gk-accordion-trigger").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var item = this.closest(".gk-accordion-item");
        var isOpen = item.classList.contains("open");
        // Optional: close others (single-open mode)
        if (acc.dataset.gkSingle !== undefined) {
          acc.querySelectorAll(".gk-accordion-item.open").forEach(function (i) {
            i.classList.remove("open");
          });
        }
        if (!isOpen) item.classList.add("open");
        else item.classList.remove("open");
      });
    });
  });

  // === GALLERY LAZY LOADING ===
  if ("IntersectionObserver" in window) {
    var galleryObs = new IntersectionObserver(
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
    document
      .querySelectorAll(".gk-gallery-item[data-lazy]")
      .forEach(function (item) {
        galleryObs.observe(item);
      });
  }

  // === LIGHTBOX ===
  (function () {
    var lb = null,
      items = [],
      current = 0;

    function createLightbox() {
      if (lb) return;
      lb = document.createElement("div");
      lb.className = "gk-lightbox";
      lb.innerHTML =
        '<button class="gk-lightbox-close"><span class="material-icons">close</span></button>' +
        '<button class="gk-lightbox-nav gk-lightbox-prev"><span class="material-icons">chevron_left</span></button>' +
        '<img class="gk-lightbox-img" src="">' +
        '<button class="gk-lightbox-nav gk-lightbox-next"><span class="material-icons">chevron_right</span></button>' +
        '<div class="gk-lightbox-caption"></div>' +
        '<div class="gk-lightbox-counter"></div>';
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
      document.addEventListener("keydown", function (e) {
        if (!lb.classList.contains("open")) return;
        if (e.key === "Escape") closeLb();
        if (e.key === "ArrowLeft") navigate(-1);
        if (e.key === "ArrowRight") navigate(1);
      });
    }

    function showLb(idx) {
      createLightbox();
      current = idx;
      var item = items[current];
      lb.querySelector(".gk-lightbox-img").src = item.src;
      lb.querySelector(".gk-lightbox-caption").textContent = item.caption || "";
      lb.querySelector(".gk-lightbox-counter").textContent =
        current + 1 + " / " + items.length;
      lb.querySelector(".gk-lightbox-prev").style.display =
        items.length > 1 ? "" : "none";
      lb.querySelector(".gk-lightbox-next").style.display =
        items.length > 1 ? "" : "none";
      lb.classList.add("open");
      document.body.style.overflow = "hidden";
    }

    function closeLb() {
      if (lb) lb.classList.remove("open");
      document.body.style.overflow = "";
    }

    function navigate(dir) {
      current = (current + dir + items.length) % items.length;
      var item = items[current];
      lb.querySelector(".gk-lightbox-img").src = item.src;
      lb.querySelector(".gk-lightbox-caption").textContent = item.caption || "";
      lb.querySelector(".gk-lightbox-counter").textContent =
        current + 1 + " / " + items.length;
    }

    // Click on gallery items
    document.addEventListener("click", function (e) {
      var galleryItem = e.target.closest(".gk-gallery-item[data-lightbox]");
      if (!galleryItem) return;
      e.preventDefault();
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
      showLb(clickIdx);
    });

    // Expose for external use
    window.GK = window.GK || {};
    GK.lightbox = { open: showLb, close: closeLb };
  })();
})();

// === TOOLTIP (Rich) ===
GK.tooltip = {
  init() {
    document.querySelectorAll("[data-gk-tooltip-rich]").forEach((el) => {
      const targetId = el.getAttribute("data-gk-tooltip-rich");
      const tip = document.querySelector(targetId);
      if (!tip) return;
      tip.classList.add("gk-tooltip-content");

      el.addEventListener("mouseenter", () => {
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
      });

      el.addEventListener("mouseleave", (e) => {
        // Keep visible if mouse moves to tooltip itself
        setTimeout(() => {
          if (!tip.matches(":hover") && !el.matches(":hover")) {
            tip.classList.remove("visible");
          }
        }, 100);
      });

      tip.addEventListener("mouseleave", () => {
        if (!el.matches(":hover")) {
          tip.classList.remove("visible");
        }
      });
    });
  },
};
document.addEventListener("DOMContentLoaded", () => GK.tooltip.init());
