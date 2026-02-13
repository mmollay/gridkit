/* GridKit JS v1.0 – Vanilla, zero dependencies */
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
                // Clear errors
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
                    this.reload(wrap, { gk_sort: th.dataset.gkSort, gk_dir: th.dataset.gkDir, gk_page: 1 });
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
                        timer = setTimeout(() => this.reload(wrap, { gk_search: searchInput.value, gk_page: 1 }), 300);
                    });
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
                        // Replace table + pagination (everything after toolbar)
                        const toolbar = wrap.querySelector('.gk-toolbar');
                        const templates = wrap.querySelectorAll('template');
                        // Remove old table content
                        Array.from(wrap.children).forEach(ch => {
                            if (ch !== toolbar && ch.tagName !== 'TEMPLATE') ch.remove();
                        });
                        toolbar.insertAdjacentHTML('afterend', html);
                        // Re-bind URL params
                        const params = url.searchParams;
                        window.history.replaceState(null, '', url);
                    });
            },
            refreshAll() {
                document.querySelectorAll('[data-gk-table]').forEach(wrap => this.reload(wrap, {}));
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
