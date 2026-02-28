/* GridKit JS v1.1 – Vanilla, zero dependencies */
(function() {
    'use strict';

    const GK = {
        // === MODAL ===
        modal: {
            stack: [],
            init() {
                document.addEventListener('keydown', e => { if (e.key === 'Escape' && this.stack.length) this.close(); });
            },
            _createOverlay() {
                var ov = document.createElement('div');
                ov.className = 'gk-modal-overlay';
                ov.style.zIndex = 9000 + this.stack.length * 10;
                ov.innerHTML = '<div class="gk-modal" data-gk-modal-container>' +
                    '<div class="gk-modal-header"><h3 class="gk-modal-title" data-gk-modal-title-el></h3>' +
                    '<button class="gk-modal-close" data-gk-modal-close>&times;</button></div>' +
                    '<div class="gk-modal-body" data-gk-modal-body></div></div>';
                ov.querySelector('[data-gk-modal-close]').addEventListener('click', () => this.close());
                ov.addEventListener('click', e => { if (e.target === ov) this.close(); });
                document.body.appendChild(ov);
                return ov;
            },
            open(title, url, params, size) {
                var ov = this._createOverlay();
                var container = ov.querySelector('[data-gk-modal-container]');
                var titleEl = ov.querySelector('[data-gk-modal-title-el]');
                var body = ov.querySelector('[data-gk-modal-body]');
                titleEl.textContent = title;
                container.className = 'gk-modal gk-modal-' + (size || 'medium');
                body.innerHTML = '';
                body.classList.add('gk-loading');
                this.stack.push(ov);

                var fd = new FormData();
                if (params) Object.entries(params).forEach(([k, v]) => fd.append(k, v));

                fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => {
                        body.classList.remove('gk-loading');
                        body.innerHTML = html;
                        GK.form.bind(body);
                        GK.table.init(body);
                    })
                    .catch(() => { body.classList.remove('gk-loading'); body.innerHTML = '<p style="color:var(--gk-danger)">Fehler beim Laden</p>'; });
            },
            close() {
                if (!this.stack.length) return;
                var ov = this.stack.pop();
                ov.remove();
            },
            closeAll() {
                while (this.stack.length) this.close();
            }
        },

        // === FORM AJAX ===
        form: {
            bind(root) {
                root.querySelectorAll('form[data-gk-ajax]').forEach(form => {
                    if (form._gkBound) return;
                    form._gkBound = true;
                    form.addEventListener('submit', e => {
                        e.preventDefault();
                        this.submit(form);
                    });
                });
            },
            submit(form) {
                form.querySelectorAll('.gk-field-error').forEach(el => el.textContent = '');
                form.querySelectorAll('.gk-has-error').forEach(el => el.classList.remove('gk-has-error'));

                const btn = form.querySelector('[type="submit"]');
                if (btn) { btn.disabled = true; btn._origText = btn.textContent; btn.textContent = '…'; }

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        GK.modal.close();
                        GK.table.refreshAll();
                    } else if (data.errors) {
                        Object.entries(data.errors).forEach(([field, msg]) => {
                            const errEl = form.querySelector(`[data-gk-error="${field}"]`);
                            if (errEl) errEl.textContent = msg;
                            const input = form.querySelector(`[name="${field}"]`);
                            if (input) input.classList.add('gk-has-error');
                        });
                    }
                })
                .catch(() => alert('Fehler beim Speichern'))
                .finally(() => { if (btn) { btn.disabled = false; btn.textContent = btn._origText; } });
            }
        },

        // === TABLE ===
        table: {
            init(root) {
                (root || document).querySelectorAll('[data-gk-table]').forEach(wrap => this.bindTable(wrap));
            },
            bindTable(wrap) {
                const id = wrap.dataset.gkTable;
                const isStatic = wrap.hasAttribute('data-gk-static');

                // Load static data if available
                if (isStatic) {
                    const scriptEl = wrap.querySelector('script[data-gk-data]');
                    if (scriptEl) {
                        try {
                            wrap._gkData = JSON.parse(scriptEl.textContent);
                            wrap._gkSort = { col: '', dir: 'asc' };
                            wrap._gkSearch = '';
                            wrap._gkFilters = {};
                        } catch (e) { /* ignore */ }
                    }
                }

                // Modal buttons
                wrap.addEventListener('click', e => {
                    const btn = e.target.closest('[data-gk-modal]');
                    if (!btn) return;
                    const modalId = btn.dataset.gkModal;
                    const tpl = wrap.querySelector(`[data-gk-modal-tpl="${modalId}"]`);
                    if (!tpl) return;
                    const params = btn.dataset.gkParams ? JSON.parse(btn.dataset.gkParams) : {};
                    GK.modal.open(tpl.dataset.gkModalTitle, tpl.dataset.gkModalUrl, params, tpl.dataset.gkModalSize);
                });

                // Sort
                wrap.addEventListener('click', e => {
                    const th = e.target.closest('[data-gk-sort]');
                    if (!th) return;
                    if (isStatic && wrap._gkData) {
                        const col = th.dataset.gkSort;
                        const dir = th.dataset.gkDir;
                        wrap._gkSort = { col, dir };
                        this.renderStatic(wrap);
                    } else {
                        this.reload(wrap, { gk_sort: th.dataset.gkSort, gk_dir: th.dataset.gkDir, gk_page: 1 });
                    }
                });

                // Pagination
                wrap.addEventListener('click', e => {
                    const btn = e.target.closest('[data-gk-page]');
                    if (!btn) return;
                    this.reload(wrap, { gk_page: btn.dataset.gkPage });
                });

                // Search
                const searchInput = wrap.querySelector('[data-gk-search]');
                if (searchInput) {
                    let timer;
                    searchInput.addEventListener('input', () => {
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
                wrap.querySelectorAll('[data-gk-filter]').forEach(sel => {
                    sel.addEventListener('change', () => {
                        if (isStatic && wrap._gkData) {
                            wrap._gkFilters[sel.dataset.gkFilter] = sel.value;
                            this.renderStatic(wrap);
                        } else {
                            const params = { gk_page: 1 };
                            params['gk_filter_' + sel.dataset.gkFilter] = sel.value;
                            this.reload(wrap, params);
                        }
                    });
                });

                // Multi-select
                if (wrap.hasAttribute('data-gk-selectable')) this.initSelectable(wrap);
            },

            initSelectable(wrap) {
                const selected = new Set();
                const bulkBar  = wrap.querySelector('.gk-bulk-bar');

                function getRowId(row) { return row.dataset.gkRowId; }

                function updateBar() {
                    if (!bulkBar) return;
                    const n = selected.size;
                    bulkBar.querySelector('.gk-bulk-count').textContent = n + ' ausgewählt';
                    bulkBar.style.display = n > 0 ? 'flex' : 'none';
                    wrap.querySelectorAll('tbody tr[data-gk-row-id]').forEach(tr => {
                        tr.classList.toggle('gk-row-selected', selected.has(getRowId(tr)));
                    });
                    const all = wrap.querySelectorAll('tbody tr[data-gk-row-id]');
                    const selAll = wrap.querySelector('[data-gk-select-all]');
                    if (selAll) selAll.indeterminate = n > 0 && n < all.length;
                    if (selAll) selAll.checked = n > 0 && n === all.length;
                }

                // Row checkboxes
                wrap.addEventListener('change', function(e) {
                    if (e.target.tagName !== 'INPUT' || e.target.type !== 'checkbox') return;
                    if (!e.target.closest('td.gk-cb-col')) return;
                    const tr = e.target.closest('tr[data-gk-row-id]');
                    if (!tr) return;
                    if (e.target.checked) selected.add(getRowId(tr));
                    else                  selected.delete(getRowId(tr));
                    updateBar();
                });

                // Select-all checkbox
                const selAll = wrap.querySelector('[data-gk-select-all]');
                if (selAll) {
                    selAll.addEventListener('change', function() {
                        wrap.querySelectorAll('tbody tr[data-gk-row-id]').forEach(tr => {
                            const cb = tr.querySelector('td.gk-cb-col input[type=checkbox]');
                            if (this.checked) { selected.add(getRowId(tr)); if (cb) cb.checked = true; }
                            else              { selected.delete(getRowId(tr)); if (cb) cb.checked = false; }
                        });
                        updateBar();
                    });
                }

                // Bulk delete
                const delBtn = bulkBar && bulkBar.querySelector('[data-gk-bulk-delete]');
                if (delBtn) {
                    delBtn.addEventListener('click', function() {
                        if (!selected.size) return;
                        const ids = [...selected];
                        GK.confirm(ids.length + ' Einträge wirklich löschen?', {title: 'Löschen', confirmText: 'Löschen', danger: true})
                            .then(ok => {
                                if (!ok) return;
                                wrap.dispatchEvent(new CustomEvent('gk:bulkdelete', { bubbles: true, detail: { ids, tableId: wrap.dataset.gkTable } }));
                            });
                    });
                }

                // Cancel
                const cancelBtn = bulkBar && bulkBar.querySelector('[data-gk-bulk-cancel]');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        selected.clear();
                        wrap.querySelectorAll('tbody input[type=checkbox]').forEach(cb => cb.checked = false);
                        if (selAll) { selAll.checked = false; selAll.indeterminate = false; }
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
                    if (val !== '') {
                        rows = rows.filter(r => String(r[col] ?? '') === val);
                    }
                });

                // Apply search
                const query = (wrap._gkSearch || '').toLowerCase().trim();
                if (query) {
                    rows = rows.filter(row => {
                        return colKeys.some(key => {
                            return String(row[key] ?? '').toLowerCase().includes(query);
                        });
                    });
                }

                // Apply sort
                const sort = wrap._gkSort || {};
                if (sort.col && columns[sort.col]) {
                    const col = sort.col;
                    const dir = sort.dir === 'desc' ? -1 : 1;
                    rows.sort((a, b) => {
                        let va = a[col] ?? '';
                        let vb = b[col] ?? '';
                        // Try numeric comparison
                        const na = parseFloat(va), nb = parseFloat(vb);
                        if (!isNaN(na) && !isNaN(nb)) return (na - nb) * dir;
                        return String(va).localeCompare(String(vb), 'de') * dir;
                    });
                }

                // Build HTML
                const e = s => {
                    const d = document.createElement('div');
                    d.textContent = String(s);
                    return d.innerHTML;
                };

                const formatVal = (val, col) => {
                    const fmt = col.format || null;
                    if (!fmt) return e(val);
                    switch (fmt) {
                        case 'currency': return e(parseFloat(val || 0).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €');
                        case 'percent': return e(parseInt(val || 0) + '%');
                        case 'date': return val ? e(new Date(val).toLocaleDateString('de-DE')) : '';
                        case 'datetime': return val ? e(new Date(val).toLocaleString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})) : '';
                        case 'boolean': return parseInt(val) ? '<span class="gk-bool gk-bool-yes">✓</span>' : '<span class="gk-bool gk-bool-no">–</span>';
                        case 'email': return val ? '<a href="mailto:' + e(val) + '">' + e(val) + '</a>' : '';
                        case 'label': return renderLabel(val, col.labels || {});
                        default: return e(val);
                    }
                };

                const renderLabel = (val, custom) => {
                    const v = String(val || '').toLowerCase().trim();
                    const map = {
                        green: ['aktiv','bezahlt','paid','ja','yes','1','true','gesendet','delivered'],
                        orange: ['offen','pending','entwurf','draft','warnung'],
                        red: ['storniert','cancelled','überfällig','overdue','fehler','error'],
                        gray: ['inaktiv','0','false','nein','no']
                    };
                    let color = custom[v] || null;
                    if (!color) {
                        for (const [c, vals] of Object.entries(map)) {
                            if (vals.includes(v)) { color = c; break; }
                        }
                    }
                    color = color || 'gray';
                    return '<span class="gk-label gk-label-' + e(color) + '">' + e(val) + '</span>';
                };

                // Determine next sort direction for headers
                const sortCol = sort.col || '';
                const sortDir = sort.dir || 'asc';

                let html = '<table class="gk-table"><thead><tr>';
                for (const [key, col] of Object.entries(columns)) {
                    const style = col.width ? ' style="width:' + e(col.width) + '"' : '';
                    const sortable = col.sortable || false;
                    let cls = '', attrs = '';
                    if (sortable) {
                        const newDir = (sortCol === key && sortDir === 'asc') ? 'desc' : 'asc';
                        attrs = ' data-gk-sort="' + e(key) + '" data-gk-dir="' + newDir + '"';
                        if (sortCol === key) {
                            cls = ' class="gk-sortable gk-sorted-' + sortDir + (col.hideOnMobile ? ' gk-hide-mobile' : '') + '"';
                        } else {
                            cls = ' class="gk-sortable' + (col.hideOnMobile ? ' gk-hide-mobile' : '') + '"';
                        }
                    } else if (col.hideOnMobile) {
                        cls = ' class="gk-hide-mobile"';
                    }
                    html += '<th' + cls + style + attrs + '>' + e(col.label) + '</th>';
                }
                const allBtns = data.buttons || {};
                const leftBtns = Object.fromEntries(Object.entries(allBtns).filter(([,b]) => (b.position || 'right') === 'left'));
                const rightBtns = Object.fromEntries(Object.entries(allBtns).filter(([,b]) => (b.position || 'right') === 'right'));
                const hasLeft = Object.keys(leftBtns).length > 0;
                const hasRight = Object.keys(rightBtns).length > 0;
                if (hasLeft) html += '<th class="gk-actions-col"></th>';
                if (hasRight) html += '<th class="gk-actions-col"></th>';
                html += '</tr></thead><tbody>';

                const renderBtnGroup = (btns, row) => {
                    let h = '';
                    for (const [bname, bopts] of Object.entries(btns)) {
                        const hasText = !!bopts.text;
                        let cls = hasText ? 'gk-btn gk-btn-icon-text' : 'gk-btn gk-btn-icon';
                        if (bopts['class']) cls += ' gk-btn-' + bopts['class'];
                        const params = {};
                        if (bopts.params) {
                            Object.entries(bopts.params).forEach(([pk, pcol]) => { params[pk] = row[pcol] ?? ''; });
                        }
                        let btnAttrs = '';
                        if (bopts.modal) btnAttrs += ' data-gk-modal="' + e(bopts.modal) + '"';
                        btnAttrs += " data-gk-params='" + e(JSON.stringify(params)) + "'";
                        const icon = bopts.icon ? GK.table.iconSvg(bopts.icon) : '';
                        const text = hasText ? '<span>' + e(bopts.text) + '</span>' : '';
                        h += '<button class="' + cls + '"' + btnAttrs + '>' + icon + text + '</button>';
                    }
                    return h;
                };

                if (rows.length === 0) {
                    const colspan = colKeys.length + (hasLeft ? 1 : 0) + (hasRight ? 1 : 0);
                    html += '<tr><td colspan="' + colspan + '" class="gk-empty">Keine Einträge gefunden</td></tr>';
                } else {
                    rows.forEach(row => {
                        html += '<tr>';
                        if (hasLeft) html += '<td class="gk-actions gk-actions-left"><div class="gk-btn-group">' + renderBtnGroup(leftBtns, row) + '</div></td>';
                        for (const [key, col] of Object.entries(columns)) {
                            const val = row[key] ?? '';
                            const align = col.align ? ' style="text-align:' + e(col.align) + '"' : '';
                            var hideCls = col.hideOnMobile ? ' class="gk-hide-mobile"' : '';
                            html += '<td' + hideCls + align + ' data-label="' + e(col.label) + '">' + formatVal(val, col) + '</td>';
                        }
                        if (hasRight) html += '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">' + renderBtnGroup(rightBtns, row) + '</div></td>';
                        html += '</tr>';
                    });
                }

                html += '</tbody></table>';

                // Replace table content (keep toolbar, templates, script)
                const oldTable = wrap.querySelector('.gk-table');
                const oldPag = wrap.querySelector('.gk-pagination');
                if (oldTable) oldTable.remove();
                if (oldPag) oldPag.remove();

                const toolbar = wrap.querySelector('.gk-toolbar');
                if (toolbar) {
                    toolbar.insertAdjacentHTML('afterend', html);
                } else {
                    wrap.insertAdjacentHTML('afterbegin', html);
                }
            },

            iconSvg(name) {
                switch (name) {
                    case 'pencil': case 'edit':
                        return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
                    case 'trash': case 'delete':
                        return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>';
                    case 'plus':
                        return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>';
                    default:
                        return '<span>' + name + '</span>';
                }
            },

            reload(wrap, overrides) {
                const id = wrap.dataset.gkTable;
                const url = new URL(window.location);
                if (overrides) Object.entries(overrides).forEach(([k, v]) => url.searchParams.set(k, v));
                url.searchParams.set('gk_table', id);

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => {
                        const toolbar = wrap.querySelector('.gk-toolbar');
                        const templates = wrap.querySelectorAll('template');
                        Array.from(wrap.children).forEach(ch => {
                            if (ch !== toolbar && ch.tagName !== 'TEMPLATE' && ch.tagName !== 'SCRIPT') ch.remove();
                        });
                        toolbar.insertAdjacentHTML('afterend', html);
                        window.history.replaceState(null, '', url);
                    });
            },
            refreshAll() {
                document.querySelectorAll('[data-gk-table]').forEach(wrap => {
                    if (wrap.hasAttribute('data-gk-static') && wrap._gkData) {
                        this.renderStatic(wrap);
                    } else {
                        this.reload(wrap, {});
                    }
                });
            }
        },

        init() {
            this.modal.init();
            this.table.init();
            if (this.sidebar && this.sidebar.init) { this.sidebar.init(); this.sidebar.restoreState(); }
            this.form.bind(document);
        }
    };

    // Toast system
    GK.toast = {
        container: null,
        ensure() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'gk-toast-container';
                document.body.appendChild(this.container);
            }
        },
        show(message, type, duration) {
            this.ensure();
            type = type || 'info';
            duration = duration || 3000;
            var icons = {success:'check_circle', error:'error', warning:'warning', info:'info'};
            var el = document.createElement('div');
            el.className = 'gk-toast gk-toast-' + type;
            el.innerHTML = '<span class="material-icons gk-toast-icon">' + (icons[type]||'info') + '</span>' +
                '<span>' + message + '</span>' +
                '<button class="gk-toast-close">&times;</button>';
            el.querySelector('.gk-toast-close').onclick = function() { el.classList.add('gk-toast-out'); setTimeout(function(){el.remove()},300); };
            this.container.appendChild(el);
            setTimeout(function() { if (el.parentNode) { el.classList.add('gk-toast-out'); setTimeout(function(){el.remove()},300); } }, duration);
        },
        success(msg, dur) { this.show(msg, 'success', dur); },
        error(msg, dur) { this.show(msg, 'error', dur); },
        warning(msg, dur) { this.show(msg, 'warning', dur); },
        info(msg, dur) { this.show(msg, 'info', dur); }
    };

    // Sidebar
    GK.sidebar = {
        el: null,
        overlay: null,
        init() {
            this.el = document.querySelector('[data-gk-sidebar]');
            this.overlay = document.querySelector('[data-gk-sidebar-overlay]');
            if (!this.el) return;
            // Group toggles
            this.el.querySelectorAll('[data-gk-toggle]').forEach(btn => {
                btn.addEventListener('click', () => {
                    var id = btn.getAttribute('data-gk-toggle');
                    var sub = document.getElementById(id);
                    if (!sub) return;
                    var collapsed = sub.classList.toggle('collapsed');
                    btn.classList.toggle('collapsed', collapsed);
                    try { localStorage.setItem('gk-nav-' + id, collapsed ? 'closed' : 'open'); } catch(e) {}
                });
                // Restore state
                var id = btn.getAttribute('data-gk-toggle');
                var sub = document.getElementById(id);
                if (!sub) return;
                var stored = localStorage.getItem('gk-nav-' + id);
                if (stored === 'closed') {
                    sub.classList.add('collapsed');
                    btn.classList.add('collapsed');
                } else if (stored === 'open') {
                    sub.classList.remove('collapsed');
                    btn.classList.remove('collapsed');
                }
            });
        },
        toggle() {
            if (!this.el) return;
            this.el.classList.toggle('open');
            if (this.overlay) this.overlay.classList.toggle('open');
        },
        close() {
            if (!this.el) return;
            this.el.classList.remove('open');
            if (this.overlay) this.overlay.classList.remove('open');
        },
        open() {
            if (!this.el) return;
            this.el.classList.add('open');
            if (this.overlay) this.overlay.classList.add('open');
        },
        collapse() {
            if (!this.el) return;
            this.el.classList.toggle('collapsed');
            try { localStorage.setItem('gk-sidebar-collapsed', this.el.classList.contains('collapsed') ? '1' : '0'); } catch(e) {}
        },
        restoreState() {
            if (!this.el) return;
            try { if (localStorage.getItem('gk-sidebar-collapsed') === '1') this.el.classList.add('collapsed'); } catch(e) {}
        }
    };

    // Confirm dialog (replaces window.confirm)
    GK.confirm = function(message, options) {
        options = options || {};
        return new Promise(function(resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'gk-confirm-overlay';
            var title = options.title || 'Bestätigung';
            var confirmText = options.confirmText || 'Bestätigen';
            var cancelText = options.cancelText || 'Abbrechen';
            var confirmClass = options.danger ? 'gk-btn gk-btn-danger' : 'gk-btn gk-btn-primary';
            overlay.innerHTML = '<div class="gk-confirm-box">' +
                '<div class="gk-confirm-header"><h3>' + title + '</h3></div>' +
                '<div class="gk-confirm-body"><p>' + message + '</p></div>' +
                '<div class="gk-confirm-footer">' +
                '<button class="gk-btn gk-confirm-cancel">' + cancelText + '</button>' +
                '<button class="' + confirmClass + ' gk-confirm-ok">' + confirmText + '</button>' +
                '</div></div>';
            document.body.appendChild(overlay);
            overlay.querySelector('.gk-confirm-cancel').onclick = function() { overlay.remove(); resolve(false); };
            overlay.querySelector('.gk-confirm-ok').onclick = function() { overlay.remove(); resolve(true); };
            overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.remove(); resolve(false); } });
            document.addEventListener('keydown', function handler(e) { if (e.key === 'Escape') { overlay.remove(); resolve(false); document.removeEventListener('keydown', handler); } });
            setTimeout(function() { overlay.querySelector('.gk-confirm-ok').focus(); }, 50);
        });
    };

    // === RANGE SLIDERS ===
    GK.initRangeSliders = function() {
        document.querySelectorAll('.gk-range').forEach(function(input) {
            if (input._gkInit) return;
            input._gkInit = true;
            var output = input.parentElement.querySelector('.gk-range-value');
            var update = function() {
                if (output) output.textContent = input.value;
                // Fill left side with primary color
                var pct = (input.value - input.min) / (input.max - input.min) * 100;
                input.style.background = 'linear-gradient(to right, var(--gk-primary) ' + pct + '%, var(--gk-neutral-200) ' + pct + '%)';
            };
            input.addEventListener('input', update);
            update();
        });
    };

    // === FILE UPLOAD ZONES ===
    GK.initUploadZones = function() {
        document.querySelectorAll('.gk-upload-zone').forEach(function(zone) {
            if (zone._gkInit) return;
            zone._gkInit = true;

            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                zone.classList.add('gk-dragover');
            });
            zone.addEventListener('dragleave', function(e) {
                if (!zone.contains(e.relatedTarget)) zone.classList.remove('gk-dragover');
            });
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                zone.classList.remove('gk-dragover');
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) GK._uploadZoneValidate(zone, files);
            });

            var input = zone.querySelector('.gk-upload-input');
            if (input) {
                input.addEventListener('change', function() {
                    if (this.files && this.files.length) GK._uploadZoneValidate(zone, this.files);
                    this.value = '';
                });
            }

            // Queue-Container anlegen falls noch nicht vorhanden
            if (!zone.nextElementSibling || !zone.nextElementSibling.classList.contains('gk-upload-queue')) {
                var q = document.createElement('div');
                q.className = 'gk-upload-queue';
                zone.insertAdjacentElement('afterend', q);
            }
        });
    };

    // ── Hilfsfunktionen ──────────────────────────────────────────
    GK._parseSize = function(str) {
        if (!str) return 0;
        var m = String(str).trim().match(/^([\d.]+)\s*(B|KB|MB|GB)?$/i);
        if (!m) return 0;
        var n = parseFloat(m[1]);
        var units = { B: 1, KB: 1024, MB: 1048576, GB: 1073741824 };
        return Math.round(n * (units[(m[2] || 'B').toUpperCase()] || 1));
    };

    GK._formatSize = function(bytes) {
        if (bytes >= 1048576) return (Math.round(bytes / 104857.6) / 10) + ' MB';
        if (bytes >= 1024)    return (Math.round(bytes / 102.4) / 10) + ' KB';
        return bytes + ' B';
    };

    // ── Validierung ───────────────────────────────────────────────
    GK._uploadZoneValidate = function(zone, fileList) {
        var cfg = {
            maxSize:      GK._parseSize(zone.dataset.gkMaxSize),
            minSize:      GK._parseSize(zone.dataset.gkMinSize),
            maxTotalSize: GK._parseSize(zone.dataset.gkMaxTotalSize),
            maxFiles:     parseInt(zone.dataset.gkMaxFiles)  || 0,
            accept:       (zone.dataset.gkAccept || '').toLowerCase().split(',').map(function(s) { return s.trim().replace(/^\./, ''); }).filter(Boolean),
        };

        var files    = Array.from(fileList);
        var accepted = [];
        var errors   = [];

        // Max Dateianzahl
        if (cfg.maxFiles > 0 && files.length > cfg.maxFiles) {
            errors.push('Max. ' + cfg.maxFiles + ' Datei' + (cfg.maxFiles > 1 ? 'en' : '') + ' erlaubt — ' + files.length + ' ausgewählt');
            files = files.slice(0, cfg.maxFiles);
        }

        files.forEach(function(f) {
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (cfg.accept.length && !cfg.accept.includes(ext)) {
                errors.push(f.name + ': Format nicht erlaubt (.'+ext+')');
                return;
            }
            if (cfg.maxSize > 0 && f.size > cfg.maxSize) {
                errors.push(f.name + ': zu groß (' + GK._formatSize(f.size) + ', max. ' + zone.dataset.gkMaxSize + ')');
                return;
            }
            if (cfg.minSize > 0 && f.size < cfg.minSize) {
                errors.push(f.name + ': zu klein (' + GK._formatSize(f.size) + ', min. ' + zone.dataset.gkMinSize + ')');
                return;
            }
            accepted.push(f);
        });

        // Max Gesamtgröße
        if (cfg.maxTotalSize > 0 && accepted.length) {
            var total = accepted.reduce(function(s, f) { return s + f.size; }, 0);
            if (total > cfg.maxTotalSize) {
                errors.push('Gesamtgröße ' + GK._formatSize(total) + ' überschreitet Maximum (' + zone.dataset.gkMaxTotalSize + ')');
                accepted = [];
            }
        }

        errors.forEach(function(msg) { GK.toast && GK.toast.error(msg); });
        if (!accepted.length) return;

        // Queue-Items anlegen + Event feuern
        var items = GK._uploadQueueAdd(zone, accepted);
        zone.dispatchEvent(new CustomEvent('gk:files', {
            bubbles: true,
            detail: { files: accepted, items: items, zone: zone }
        }));
    };

    // ── Queue UI ─────────────────────────────────────────────────
    GK._uploadQueueAdd = function(zone, files) {
        var queue = zone.nextElementSibling;
        if (!queue || !queue.classList.contains('gk-upload-queue')) return [];
        var withPreview = zone.hasAttribute('data-gk-preview');
        var items = [];

        files.forEach(function(file) {
            var id   = 'gkuq-' + Math.random().toString(36).slice(2, 9);
            var ext  = (file.name.split('.').pop() || '').toLowerCase();
            var isImg = /^(jpg|jpeg|png|gif|webp|svg)$/.test(ext);

            var item = document.createElement('div');
            item.className = 'gk-uq-item gk-uq-pending';
            item.dataset.gkUqId = id;

            // Thumb
            var thumb = document.createElement('div');
            thumb.className = 'gk-uq-thumb';
            if (isImg && withPreview) {
                var img = document.createElement('img');
                img.className = 'gk-uq-img';
                var reader = new FileReader();
                reader.onload = function(e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
                thumb.appendChild(img);
            } else {
                var icon = document.createElement('span');
                icon.className = 'material-icons gk-uq-icon';
                icon.textContent = GK._uploadFileIcon(ext);
                thumb.appendChild(icon);
            }
            item.appendChild(thumb);

            // Info
            var info = document.createElement('div');
            info.className = 'gk-uq-info';
            var name = document.createElement('span');
            name.className = 'gk-uq-name';
            name.textContent = file.name;
            name.title       = file.name;
            var size = document.createElement('span');
            size.className = 'gk-uq-size';
            size.textContent = GK._formatSize(file.size);
            info.appendChild(name);
            info.appendChild(size);
            item.appendChild(info);

            // Status
            var status = document.createElement('span');
            status.className = 'gk-uq-status';
            status.textContent = 'Bereit';
            item.appendChild(status);

            // Remove-Button (nur im Pending-State)
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'gk-uq-remove';
            rm.innerHTML = '<span class="material-icons" style="font-size:16px;">close</span>';
            rm.title = 'Entfernen';
            rm.addEventListener('click', function() {
                item.classList.add('gk-uq-removing');
                setTimeout(function() { item.remove(); }, 200);
            });
            item.appendChild(rm);

            queue.appendChild(item);
            items.push({ file: file, el: item, id: id });
        });

        return items;
    };

    GK._uploadFileIcon = function(ext) {
        var map = {
            pdf: 'picture_as_pdf',
            doc: 'description', docx: 'description',
            xls: 'table_chart',  xlsx: 'table_chart',
            zip: 'folder_zip',   rar: 'folder_zip', gz: 'folder_zip',
            mp3: 'audio_file',   wav: 'audio_file',
            mp4: 'video_file',   mov: 'video_file',
            txt: 'article',      csv: 'table_rows',
        };
        return map[ext] || 'insert_drive_file';
    };

    // ── Queue-Status-Helpers (für App-Code) ──────────────────────
    GK.uqSetUploading = function(item) {
        item.el.className = 'gk-uq-item gk-uq-uploading';
        item.el.querySelector('.gk-uq-status').innerHTML =
            '<span class="material-icons gk-spin" style="font-size:14px;vertical-align:middle;">sync</span> Lädt…';
        var rm = item.el.querySelector('.gk-uq-remove');
        if (rm) rm.style.display = 'none';
    };

    GK.uqSetDone = function(item, label) {
        item.el.className = 'gk-uq-item gk-uq-done';
        item.el.querySelector('.gk-uq-status').textContent = label || 'Hochgeladen';
        var rm = item.el.querySelector('.gk-uq-remove');
        if (rm) rm.style.display = 'none';
        setTimeout(function() {
            item.el.classList.add('gk-uq-removing');
            setTimeout(function() { item.el.remove(); }, 300);
        }, 2500);
    };

    GK.uqSetError = function(item, msg) {
        item.el.className = 'gk-uq-item gk-uq-error';
        item.el.querySelector('.gk-uq-status').textContent = msg || 'Fehler';
        var rm = item.el.querySelector('.gk-uq-remove');
        if (rm) rm.style.display = '';
    };

    // Legacy-Helpers (Rückwärtskompatibilität)
    GK.uploadZoneBusy = function(zone, label) {
        var idle = zone.querySelector('.gk-upload-idle');
        var prog = zone.querySelector('.gk-upload-progress');
        if (idle) idle.style.display = 'none';
        if (prog) prog.style.display = 'flex';
        if (label) { var l = zone.querySelector('.gk-upload-progress-label'); if (l) l.textContent = label; }
    };
    GK.uploadZoneIdle = function(zone) {
        var idle = zone.querySelector('.gk-upload-idle');
        var prog = zone.querySelector('.gk-upload-progress');
        if (idle) idle.style.display = '';
        if (prog) prog.style.display = 'none';
    };

    // === RICHTEXT EDITOR ===
    // gk-richtext wird nun via CKEditor5 initialisiert (siehe Form.php)
    GK.initRichtext = function() {};

    // Extend init
    var _origInit = GK.init;
    GK.init = function() {
        _origInit.call(GK);
        GK.initRangeSliders();
        GK.initUploadZones();
        GK.initRichtext();
        if (GK.selectSearch) GK.selectSearch.init();
        if (GK.multiSelect) GK.multiSelect.init();
        if (GK.ajaxSelect) GK.ajaxSelect.init();
    };

    // Dropdown toggle (Header user menu etc.)
    document.addEventListener('click', function(e) {
        var dropdown = e.target.closest('[data-gk-dropdown]');
        document.querySelectorAll('[data-gk-dropdown].open').forEach(function(el) {
            if (el !== dropdown) el.classList.remove('open');
        });
        if (dropdown) dropdown.classList.toggle('open');
    });

    // Layout System
    GK.layout = {
        set(mode) {
            document.body.dataset.gkLayout = mode;
            try { localStorage.setItem('gk-layout', mode); } catch(e) {}
        },
        restore() {
            try {
                var mode = localStorage.getItem('gk-layout');
                if (mode) document.body.dataset.gkLayout = mode;
            } catch(e) {}
        }
    };

    // Theme System
    GK.theme = {
        set(theme) {
            document.body.dataset.gkTheme = theme;
            try { localStorage.setItem('gk-theme', theme); } catch(e) {}
            document.querySelectorAll('[data-gk-set-theme]').forEach(b => {
                b.classList.toggle('gk-theme-active', b.dataset.gkSetTheme === theme);
            });
        },
        toggleMode() {
            var mode = document.body.dataset.gkMode === 'dark' ? 'light' : 'dark';
            document.body.dataset.gkMode = mode;
            try { localStorage.setItem('gk-mode', mode); } catch(e) {}
        },
        restore() {
            try {
                var theme = localStorage.getItem('gk-theme');
                var mode = localStorage.getItem('gk-mode');
                if (theme) this.set(theme);
                if (mode) document.body.dataset.gkMode = mode;
            } catch(e) {}
        }
    };

    // Auto-bind theme buttons
    document.addEventListener('click', function(e) {
        var themeBtn = e.target.closest('[data-gk-set-theme]');
        if (themeBtn) GK.theme.set(themeBtn.dataset.gkSetTheme);
        var modeBtn = e.target.closest('[data-gk-toggle-mode]');
        if (modeBtn) GK.theme.toggleMode();
    });

    // === SEARCHABLE SELECT ===
    GK.selectSearch = {
        init(root) {
            (root || document).querySelectorAll('[data-gk-select-search]').forEach(wrap => {
                if (wrap._gkBound) return;
                wrap._gkBound = true;
                var display = wrap.querySelector('.gk-select-display');
                var dropdown = wrap.querySelector('.gk-select-dropdown');
                var searchInput = dropdown.querySelector('input[type="text"]');
                var hidden = wrap.querySelector('input[type="hidden"]');
                var valueSpan = wrap.querySelector('.gk-select-value');
                var options = wrap.querySelectorAll('.gk-select-option');

                display.addEventListener('click', function() {
                    if (wrap.hasAttribute('data-disabled')) return;
                    display.classList.toggle('open');
                    if (display.classList.contains('open')) {
                        if (searchInput) { searchInput.value = ''; }
                        options.forEach(o => o.classList.remove('hidden'));
                        var empty = dropdown.querySelector('.gk-select-empty');
                        if (empty) empty.remove();
                        if (searchInput) setTimeout(() => searchInput.focus(), 50);
                    }
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase();
                        var found = 0;
                        options.forEach(o => {
                            var match = o.textContent.toLowerCase().includes(q);
                            o.classList.toggle('hidden', !match);
                            if (match) found++;
                        });
                        var empty = dropdown.querySelector('.gk-select-empty');
                        if (found === 0 && !empty) {
                            var e = document.createElement('div');
                            e.className = 'gk-select-empty';
                            e.textContent = 'Keine Treffer';
                            dropdown.querySelector('.gk-select-options').appendChild(e);
                        } else if (found > 0 && empty) empty.remove();
                    });
                }

                options.forEach(opt => {
                    opt.addEventListener('click', function() {
                        hidden.value = this.dataset.value;
                        valueSpan.textContent = this.textContent;
                        options.forEach(o => o.classList.remove('selected'));
                        this.classList.add('selected');
                        display.classList.remove('open');
                        hidden.dispatchEvent(new Event('change', {bubbles: true}));
                    });
                });

                document.addEventListener('click', function(e) {
                    if (!wrap.contains(e.target)) display.classList.remove('open');
                });
            });
        }
    };

    // === MULTI-SELECT ===
    GK.multiSelect = {
        init(root) {
            (root || document).querySelectorAll('[data-gk-multiselect]').forEach(wrap => {
                if (wrap._gkBound) return;
                wrap._gkBound = true;
                var display = wrap.querySelector('.gk-multiselect-display');
                var dropdown = wrap.querySelector('.gk-select-dropdown');
                var hidden = wrap.querySelector('input[type="hidden"]');
                var chipsContainer = wrap.querySelector('.gk-multiselect-chips');
                var searchInput = wrap.querySelector('.gk-multiselect-input');
                var optionsContainer = dropdown.querySelector('.gk-select-options');
                var allOptions = wrap.querySelectorAll('.gk-select-option');

                function getSelected() {
                    return hidden.value ? hidden.value.split(',').filter(Boolean) : [];
                }

                function updateHidden(vals) {
                    hidden.value = vals.join(',');
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                }

                function rebuildChips() {
                    // Remove old chips
                    wrap.querySelectorAll('.gk-chip-selected').forEach(c => c.remove());
                    var vals = getSelected();
                    vals.forEach(v => {
                        var opt = optionsContainer.querySelector('[data-value="' + v + '"]');
                        if (!opt) return;
                        var label = opt.textContent.replace('check', '').trim();
                        var chip = document.createElement('span');
                        chip.className = 'gk-chip-selected';
                        chip.dataset.value = v;
                        chip.innerHTML = label + ' <button type="button" class="gk-chip-remove">&times;</button>';
                        chip.querySelector('.gk-chip-remove').addEventListener('click', function(e) {
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
                    allOptions.forEach(o => {
                        var isSelected = vals.includes(o.dataset.value);
                        o.classList.toggle('selected', isSelected);
                        // Update check icon
                        var check = o.querySelector('.material-icons');
                        if (isSelected && !check) {
                            var s = document.createElement('span');
                            s.className = 'material-icons';
                            s.style.fontSize = '16px';
                            s.textContent = 'check';
                            o.insertBefore(s, o.firstChild);
                            o.insertBefore(document.createTextNode(' '), s.nextSibling);
                        } else if (!isSelected && check) {
                            if (check.nextSibling && check.nextSibling.nodeType === 3) check.nextSibling.remove();
                            check.remove();
                        }
                    });
                    rebuildChips();
                    updatePlaceholder();
                }

                function updatePlaceholder() {
                    if (!searchInput) return;
                    searchInput.placeholder = getSelected().length ? '' : (searchInput.dataset.placeholder || searchInput.getAttribute('placeholder') || '');
                }

                // Store original placeholder
                if (searchInput) searchInput.dataset.placeholder = searchInput.getAttribute('placeholder') || '';
                updatePlaceholder();

                display.addEventListener('click', function(e) {
                    if (e.target.closest('.gk-chip-remove')) return;
                    display.classList.toggle('open');
                    if (display.classList.contains('open') && searchInput) {
                        setTimeout(() => searchInput.focus(), 50);
                    }
                });

                allOptions.forEach(opt => {
                    opt.addEventListener('click', function() {
                        toggleValue(this.dataset.value);
                    });
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase();
                        var found = 0;
                        allOptions.forEach(o => {
                            var match = o.textContent.toLowerCase().includes(q);
                            o.classList.toggle('hidden', !match);
                            if (match) found++;
                        });
                        var empty = dropdown.querySelector('.gk-select-empty');
                        if (found === 0 && !empty) {
                            var e = document.createElement('div');
                            e.className = 'gk-select-empty';
                            e.textContent = 'Keine Treffer';
                            optionsContainer.appendChild(e);
                        } else if (found > 0 && empty) empty.remove();
                    });
                    searchInput.addEventListener('focus', function() {
                        display.classList.add('open');
                    });
                }

                document.addEventListener('click', function(e) {
                    if (!wrap.contains(e.target)) display.classList.remove('open');
                });
            });
        }
    };

    // === AJAX SELECT ===
    GK.ajaxSelect = {
        init(root) {
            (root || document).querySelectorAll('[data-gk-ajax-select]').forEach(wrap => {
                if (wrap._gkBound) return;
                wrap._gkBound = true;
                var input = wrap.querySelector('.gk-ajax-search-input');
                var hidden = wrap.querySelector('input[type="hidden"]');
                var dropdown = wrap.querySelector('.gk-select-dropdown');
                var optionsContainer = dropdown.querySelector('.gk-select-options');
                var loading = dropdown.querySelector('.gk-select-loading');
                var clearBtn = wrap.querySelector('.gk-ajax-clear');
                var url = wrap.dataset.url;
                var labelField = wrap.dataset.labelField || 'name';
                var valueField = wrap.dataset.valueField || 'id';
                var subtextField = wrap.dataset.subtextField || '';
                var minChars = parseInt(wrap.dataset.minChars) || 2;
                var searchParam = wrap.dataset.searchParam || 'q';
                var timer;

                input.addEventListener('input', function() {
                    var q = this.value.trim();
                    clearBtn.style.display = q ? '' : 'none';
                    if (q.length < minChars) { dropdown.style.display = 'none'; return; }
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        loading.style.display = '';
                        optionsContainer.innerHTML = '';
                        dropdown.style.display = 'block';
                        fetch(url + '?' + searchParam + '=' + encodeURIComponent(q))
                            .then(r => r.json())
                            .then(data => {
                                loading.style.display = 'none';
                                if (!data.length) {
                                    optionsContainer.innerHTML = '<div class="gk-select-empty">Keine Treffer</div>';
                                    return;
                                }
                                data.forEach(item => {
                                    var opt = document.createElement('div');
                                    opt.className = 'gk-select-option';
                                    opt.dataset.value = item[valueField];
                                    opt.dataset.json = JSON.stringify(item);
                                    var label = item[labelField] || '';
                                    var esc = function(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
                                    opt.innerHTML = '<div>' + esc(label) + '</div>';
                                    if (subtextField && item[subtextField]) {
                                        opt.innerHTML += '<div class="gk-select-option-sub">' + esc(item[subtextField]) + '</div>';
                                    }
                                    optionsContainer.appendChild(opt);
                                });
                            })
                            .catch(() => { loading.style.display = 'none'; });
                    }, 300);
                });

                optionsContainer.addEventListener('click', function(e) {
                    var opt = e.target.closest('.gk-select-option');
                    if (!opt) return;
                    hidden.value = opt.dataset.value;
                    input.value = opt.querySelector('div').textContent;
                    dropdown.style.display = 'none';
                    clearBtn.style.display = '';
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                    wrap.dispatchEvent(new CustomEvent('gk-select', {detail: JSON.parse(opt.dataset.json)}));
                });

                if (clearBtn) clearBtn.addEventListener('click', function() {
                    hidden.value = '';
                    input.value = '';
                    clearBtn.style.display = 'none';
                    dropdown.style.display = 'none';
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                });

                document.addEventListener('click', function(e) {
                    if (!wrap.contains(e.target)) dropdown.style.display = 'none';
                });
            });
        }
    };

    // === TABS ===
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.gk-tab-btn');
        if (!btn) return;
        var tabs = btn.closest('.gk-tabs');
        if (!tabs) return;
        var target = btn.dataset.tab;
        tabs.querySelectorAll('.gk-tab-btn').forEach(function(b) { b.classList.remove('gk-active'); });
        tabs.querySelectorAll('.gk-tab-panel').forEach(function(p) { p.classList.remove('gk-active'); });
        btn.classList.add('gk-active');
        var panel = tabs.querySelector('.gk-tab-panel[data-tab="' + target + '"]');
        if (panel) panel.classList.add('gk-active');
    });

    // === AJAX PAGINATION ===
    // Wraps table + pagination in [data-gk-ajax-table="id"].
    // Intercepts gk-page-btn link clicks, fetches new page, swaps innerHTML.
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a.gk-page-btn');
        if (!link || !link.href) return;
        var wrap = link.closest('[data-gk-ajax-table]');
        if (!wrap) return;
        e.preventDefault();
        var url = link.href;
        var id = wrap.getAttribute('data-gk-ajax-table');
        wrap.style.opacity = '0.5';
        wrap.style.pointerEvents = 'none';
        wrap.style.transition = 'opacity .15s';
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newWrap = doc.querySelector('[data-gk-ajax-table="' + id + '"]');
                if (newWrap) {
                    wrap.innerHTML = newWrap.innerHTML;
                    GK.table.init(wrap);
                    GK.form.bind(wrap);
                }
                wrap.style.opacity = '';
                wrap.style.pointerEvents = '';
                history.pushState(null, '', url);
            })
            .catch(function() {
                wrap.style.opacity = '';
                wrap.style.pointerEvents = '';
                window.location.href = url;
            });
    });

    window.GridKit = GK;
    window.GK = GK;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { GK.init(); GK.theme.restore(); GK.layout.restore(); });
    } else {
        GK.init();
        GK.theme.restore();
        GK.layout.restore();
    }
})();
