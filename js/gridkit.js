/* GridKit JS v1.1 – Vanilla, zero dependencies */
(function() {
    'use strict';

    const GK = {
        // === MODAL ===
        modal: {
            overlay: null,
            init() {
                this.overlay = document.querySelector('[data-gk-modal-overlay]');
                if (!this.overlay) return;
                this.overlay.querySelector('[data-gk-modal-close]').addEventListener('click', () => this.close());
                this.overlay.addEventListener('click', e => { if (e.target === this.overlay) this.close(); });
                document.addEventListener('keydown', e => { if (e.key === 'Escape' && this.overlay.style.display !== 'none') this.close(); });
            },
            open(title, url, params, size) {
                if (!this.overlay) return;
                const container = this.overlay.querySelector('[data-gk-modal-container]');
                const titleEl = this.overlay.querySelector('[data-gk-modal-title-el]');
                const body = this.overlay.querySelector('[data-gk-modal-body]');
                titleEl.textContent = title;
                container.className = 'gk-modal gk-modal-' + (size || 'medium');
                body.innerHTML = '';
                body.classList.add('gk-loading');
                this.overlay.style.display = '';

                const fd = new FormData();
                if (params) Object.entries(params).forEach(([k, v]) => fd.append(k, v));

                fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => { body.classList.remove('gk-loading'); body.innerHTML = html; GK.form.bind(body); })
                    .catch(() => { body.classList.remove('gk-loading'); body.innerHTML = '<p style="color:var(--gk-danger)">Fehler beim Laden</p>'; });
            },
            close() {
                if (this.overlay) this.overlay.style.display = 'none';
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
            init() {
                document.querySelectorAll('[data-gk-table]').forEach(wrap => this.bindTable(wrap));
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
                            cls = ' class="gk-sortable gk-sorted-' + sortDir + '"';
                        } else {
                            cls = ' class="gk-sortable"';
                        }
                    }
                    html += '<th' + cls + style + attrs + '>' + e(col.label) + '</th>';
                }
                const hasButtons = data.buttons && Object.keys(data.buttons).length > 0;
                if (hasButtons) html += '<th class="gk-actions-col"></th>';
                html += '</tr></thead><tbody>';

                if (rows.length === 0) {
                    const colspan = colKeys.length + (hasButtons ? 1 : 0);
                    html += '<tr><td colspan="' + colspan + '" class="gk-empty">Keine Einträge gefunden</td></tr>';
                } else {
                    rows.forEach(row => {
                        html += '<tr>';
                        for (const [key, col] of Object.entries(columns)) {
                            const val = row[key] ?? '';
                            const align = col.align ? ' style="text-align:' + e(col.align) + '"' : '';
                            html += '<td' + align + '>' + formatVal(val, col) + '</td>';
                        }
                        if (hasButtons) {
                            html += '<td class="gk-actions">';
                            for (const [bname, bopts] of Object.entries(data.buttons)) {
                                let cls = 'gk-btn gk-btn-icon';
                                if (bopts['class']) cls += ' gk-btn-' + bopts['class'];
                                const params = {};
                                if (bopts.params) {
                                    Object.entries(bopts.params).forEach(([pk, pcol]) => {
                                        params[pk] = row[pcol] ?? '';
                                    });
                                }
                                let btnAttrs = '';
                                if (bopts.modal) btnAttrs += ' data-gk-modal="' + e(bopts.modal) + '"';
                                btnAttrs += " data-gk-params='" + e(JSON.stringify(params)) + "'";
                                const icon = GK.table.iconSvg(bopts.icon || bname);
                                html += '<button class="' + cls + '"' + btnAttrs + '>' + icon + '</button>';
                            }
                            html += '</td>';
                        }
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
            this.form.bind(document);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => GK.init());
    } else {
        GK.init();
    }

    window.GridKit = GK;
})();
