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
                            html += '<td' + align + '>' + formatVal(val, col) + '</td>';
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
            zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('gk-dragover'); });
            zone.addEventListener('dragleave', function() { zone.classList.remove('gk-dragover'); });
            zone.addEventListener('drop', function() { zone.classList.remove('gk-dragover'); });
        });
    };

    // === RICHTEXT EDITOR ===
    GK.initRichtext = function() {
        document.querySelectorAll('.gk-richtext').forEach(function(editor) {
            if (editor._gkInit) return;
            editor._gkInit = true;
            var toolbar = editor.querySelector('.gk-richtext-toolbar');
            var content = editor.querySelector('.gk-richtext-content');
            var hidden = editor.querySelector('input[type="hidden"]');

            toolbar.querySelectorAll('.gk-richtext-btn').forEach(function(btn) {
                btn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    var cmd = btn.dataset.cmd;
                    var val = btn.dataset.val || null;
                    if (btn.dataset.prompt) {
                        val = prompt(btn.dataset.prompt);
                        if (!val) return;
                    }
                    if (cmd === 'formatBlock' && val) val = '<' + val + '>';
                    document.execCommand(cmd, false, val);
                    content.focus();
                });
            });

            // Sync to hidden input on input
            content.addEventListener('input', function() {
                if (hidden) hidden.value = content.innerHTML;
            });

            // Sync before form submit
            var form = editor.closest('form');
            if (form) {
                form.addEventListener('submit', function() {
                    if (hidden) hidden.value = content.innerHTML;
                });
            }
        });
    };

    // Extend init
    var _origInit = GK.init;
    GK.init = function() {
        _origInit.call(GK);
        GK.initRangeSliders();
        GK.initUploadZones();
        GK.initRichtext();
    };

    // Dropdown toggle (Header user menu etc.)
    document.addEventListener('click', function(e) {
        var dropdown = e.target.closest('[data-gk-dropdown]');
        document.querySelectorAll('[data-gk-dropdown].open').forEach(function(el) {
            if (el !== dropdown) el.classList.remove('open');
        });
        if (dropdown) dropdown.classList.toggle('open');
    });

    window.GridKit = GK;
    window.GK = GK;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => GK.init());
    } else {
        GK.init();
    }
})();
