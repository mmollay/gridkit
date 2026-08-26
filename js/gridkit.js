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

        // A sortable header is reachable by Tab and carries role="button", so
        // it has to answer to Enter and Space like one. Space would otherwise
        // scroll the page.
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
                : n.toLocaleString("de-DE", {
                    minimumFractionDigits: dec,
                    maximumFractionDigits: dec,
                  });
              return '<span class="gk-num">' + e(text) + "</span>";
            }
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

        const selectable = wrap.hasAttribute("data-gk-selectable");
        const rowIdField = data.rowId || "id";
        const selSet = wrap._gkSelected || new Set();

        let html = '<table class="gk-table"><thead><tr>';
        if (selectable)
          html +=
            '<th class="gk-cb-col"><input type="checkbox" data-gk-select-all></th>';
        for (const [key, col] of Object.entries(columns)) {
          const style = col.width ? ' style="width:' + e(col.width) + '"' : "";
          const sortable = col.sortable || false;
          let cls = "",
            attrs = "",
            sortIcon = "";
          if (sortable) {
            const newDir =
              sortCol === key && sortDir === "asc" ? "desc" : "asc";
            attrs =
              ' data-gk-sort="' + e(key) + '" data-gk-dir="' + newDir + '"';
            // gk-sortable-mi = Material icon indicator (consistent with SortLink);
            // suppresses the ::after arrow of .gk-sortable.
            const base =
              "gk-sortable gk-sortable-mi" +
              (col.hideOnMobile ? " gk-hide-mobile" : "");
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
          } else if (col.hideOnMobile) {
            cls = ' class="gk-hide-mobile"';
          }
          html +=
            "<th" + cls + style + attrs + ">" + e(col.label) + sortIcon + "</th>";
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
            if (bopts.title) btnAttrs += ' title="' + e(bopts.title) + '"';
            btnAttrs += " data-gk-params='" + e(JSON.stringify(params)) + "'";
            if (bopts.onclick) {
              const oc = String(bopts.onclick).replace(/\{(\w+)\}/g, (_, k) =>
                JSON.stringify(row[k] ?? null),
              );
              btnAttrs += " onclick='" + oc.replace(/'/g, "&#39;") + "'";
            }
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
            html += selectable
              ? '<tr data-gk-row-id="' + e(rid) + '">'
              : "<tr>";
            if (selectable)
              html +=
                '<td class="gk-cb-col"><input type="checkbox"' +
                (selSet.has(rid) ? " checked" : "") +
                "></td>";
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
              const tdCls = [];
              if (col.hideOnMobile) tdCls.push("gk-hide-mobile");
              if (col.format === "number" || col.format === "currency")
                tdCls.push("gk-td-num");
              const hideCls = tdCls.length
                ? ' class="' + tdCls.join(" ") + '"'
                : "";
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
        // Mirror the selection again after the rebuild (checkbox/highlight/bulk bar).
        if (selectable && typeof wrap._gkUpdateBar === "function")
          wrap._gkUpdateBar();
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
            // Out-of-band updates: <template data-gk-replace="css-selector">
            // Replaces elements OUTSIDE the container (e.g. StatCards).
            wrap.querySelectorAll("template[data-gk-replace]").forEach(function(tpl) {
              var sel = tpl.getAttribute("data-gk-replace");
              var target = document.querySelector(sel);
              if (target && tpl.content) {
                var div = document.createElement("div");
                div.appendChild(tpl.content.cloneNode(true));
                target.outerHTML = div.innerHTML;
              }
              tpl.remove();
            });
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
        self._lastPath = location.pathname + location.search;
        self.updateActive(url);
        // Respect the target anchor from the URL (page change WITH a fragment) —
        // otherwise you always land at the top of the page after the content swap.
        var frag = (url.split('#')[1] || '');
        var anchor = frag ? document.getElementById(frag) : null;
        if (anchor) anchor.scrollIntoView(); else window.scrollTo(0, 0);
        try {
          if (typeof GK.table !== 'undefined' && GK.table.init) GK.table.init();
          if (typeof GK.tooltip !== 'undefined' && GK.tooltip.init) GK.tooltip.init();
          // BelegModal sits inside [data-gk-content] and is re-rendered on the
          // swap → the close button loses its listener. Bind it again.
          if (typeof GK.belegModal !== 'undefined' && GK.belegModal._init) GK.belegModal._init();
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
    applyHtml: function (container, html) {
      if (container.hasAttribute("data-gk-live-self")) {
        try {
          var doc = new DOMParser().parseFromString(html, "text/html");
          var fresh = container.id ? doc.getElementById(container.id) : null;
          container.innerHTML = fresh ? fresh.innerHTML : html;
          return;
        } catch (e) {}
      }
      container.innerHTML = html;
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
          GK.liveTable.applyHtml(container, html);
          GK.liveTable.hoistPager(container);
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
      container.classList.add("gk-live-loading");
      fetch(fetchUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          GK.liveTable.applyHtml(container, html);
          GK.liveTable.hoistPager(container);
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
  GK.tabs = {
    init(root) {
      (root || document).querySelectorAll("[data-gk-tabs]").forEach(function (wrap) {
        if (wrap._gkTabs) return;
        wrap._gkTabs = true;
        var panels = Array.prototype.slice.call(wrap.querySelectorAll("[data-gk-tabpanel]"));
        if (!panels.length) return;
        var nav = document.createElement("div");
        nav.className = "gk-tabs-nav";
        panels.forEach(function (p, i) {
          var key = p.getAttribute("data-gk-tabpanel");
          var b = document.createElement("button");
          b.type = "button";
          b.className = "gk-tab" + (i === 0 ? " gk-tab-active" : "");
          b.setAttribute("data-gk-tab", key);
          b.innerHTML = p.getAttribute("data-gk-tab-title") || key;
          nav.appendChild(b);
          p.style.display = i === 0 ? "" : "none";
        });
        wrap.insertBefore(nav, wrap.firstChild);
        nav.addEventListener("click", function (e) {
          var b = e.target.closest("[data-gk-tab]");
          if (!b) return;
          var key = b.getAttribute("data-gk-tab");
          nav.querySelectorAll(".gk-tab").forEach(function (x) { x.classList.toggle("gk-tab-active", x === b); });
          panels.forEach(function (p) { p.style.display = p.getAttribute("data-gk-tabpanel") === key ? "" : "none"; });
        });
      });
    },
  };

  // Re-apply RowPager after a live table reload (the container content was swapped).
  document.addEventListener("gk-live-reloaded", function (e) {
    if (GK.rowPager) GK.rowPager.init(e.target || document);
    if (GK.table) GK.table.init(e.target || document);
  });

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
    if (GK.tabs) GK.tabs.init();
    if (GK.rowPager) GK.rowPager.init();
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

    set(theme) {
      document.body.dataset.gkTheme = theme;
      try {
        localStorage.setItem(this._key("gk-theme"), theme);
      } catch (e) {}
      document.querySelectorAll("[data-gk-set-theme]").forEach((b) => {
        b.classList.toggle("gk-theme-active", b.dataset.gkSetTheme === theme);
      });
    },
    toggleMode() {
      var mode = document.body.dataset.gkMode === "dark" ? "light" : "dark";
      document.body.dataset.gkMode = mode;
      try {
        localStorage.setItem(this._key("gk-mode"), mode);
      } catch (e) {}
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
        var active = document.body.dataset.gkTheme || "";
        document.querySelectorAll("[data-gk-set-theme]").forEach(function (b) {
          b.classList.toggle("gk-theme-active", b.dataset.gkSetTheme === active);
        });
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
              options.forEach((o) => o.classList.remove("selected"));
              this.classList.add("selected");
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

          var activeIdx = -1;

          function getOptions() {
            return Array.from(optionsContainer.querySelectorAll(".gk-select-option"));
          }

          function highlightOption(opts) {
            opts.forEach(function (el, i) {
              el.style.background = i === activeIdx ? "var(--gk-surface-container, #f1f5f9)" : "";
              el.style.fontWeight = i === activeIdx ? "600" : "";
            });
            if (opts[activeIdx]) opts[activeIdx].scrollIntoView({ block: "nearest" });
          }

          function selectOption(opt) {
            if (!opt) return;
            var item = JSON.parse(opt.dataset.json);
            hidden.value = opt.dataset.value;
            input.value = item[labelField] || opt.querySelector("div").textContent;
            dropdown.style.display = "none";
            activeIdx = -1;
            clearBtn.style.display = "";
            hidden.dispatchEvent(new Event("change", { bubbles: true }));
            wrap.dispatchEvent(new CustomEvent("gk-select", { detail: item }));
          }

          optionsContainer.addEventListener("click", function (e) {
            var opt = e.target.closest(".gk-select-option");
            if (opt) selectOption(opt);
          });

          input.addEventListener("keydown", function (e) {
            if (dropdown.style.display === "none") return;
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
            } else if (e.key === "Escape") {
              dropdown.style.display = "none";
              activeIdx = -1;
            }
          });

          if (clearBtn)
            clearBtn.addEventListener("click", function () {
              hidden.value = "";
              input.value = "";
              clearBtn.style.display = "none";
              dropdown.style.display = "none";
              activeIdx = -1;
              hidden.dispatchEvent(new Event("change", { bubbles: true }));
            });

          document.addEventListener("click", function (e) {
            if (!wrap.contains(e.target)) { dropdown.style.display = "none"; activeIdx = -1; }
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
      var ausloeser = e.target.closest && e.target.closest("[data-gk-search]");
      if (ausloeser) {
        e.preventDefault();
        self.open();
      }
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
      ' placeholder="' + this.cfg.placeholder + '">' +
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
    setTimeout(function () { self.input.focus(); }, 20);
  },

  close() {
    if (!this.overlay) return;
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
    this.showNotice('<span class="gk-search-laedt"></span>');

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

  showNotice(text) {
    this.hits = [];
    this.active = -1;
    if (this.list) this.list.innerHTML = '<div class="gk-search-hinweis">' + text + "</div>";
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
