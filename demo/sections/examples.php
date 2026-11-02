<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\TableHeader;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;
?>
<!-- ===== EXAMPLES (merged: dashboard + skeleton + auth) ===== -->
<div class="demo-section" data-section="examples">
    <h2>Examples</h2>

    <div class="demo-card" style="border-left:3px solid var(--gk-primary);">
        <h3 style="margin:0 0 8px;">A whole application, not a mock-up</h3>
        <p class="demo-intro" style="margin-bottom:14px;">
            Everything below this box is a gallery — it shows what exists. If you would
            rather see what it is <em>for</em>, <code>examples/invoices/</code> is a
            working invoice manager: create, edit, delete, with search, filter, sort and
            paging answered by the server. About 300 lines, no database.
        </p>
        <a class="gk-btn gk-btn-filled gk-btn-primary gk-btn-sm"
           href="../examples/invoices/?lang=<?= $lang ?>">
            <span class="material-icons" style="font-size:16px;vertical-align:-3px;" aria-hidden="true">open_in_new</span>
            Open the example application
        </a>
    </div>

    <h3 style="margin: 32px 0 16px;">Dashboard Demo</h3>
    <div class="demo-card">
        <?php
        $dashStats = new StatCards('dash-stats');
        $dashStats->card('Invoices', 152, ['format' => 'number', 'color' => 'blue'])
            ->card('Revenue 2026', 127840.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Open', 18320.00, ['format' => 'currency', 'color' => 'orange'])
            ->card('Overdue', 4280.00, ['format' => 'currency', 'color' => 'red'])
            ->render();

        $dashChips = new FilterChips('dash-filter', 'dash_status');
        $dashChips->chip('', 'All')
            ->chip('paid', 'Paid', ['color' => 'green'])
            ->chip('pending', 'Pending', ['color' => 'orange'])
            ->chip('overdue', 'Overdue', ['color' => 'red'])
            ->render();

        $dashYears = new YearFilter('dash-years', 'dash_year');
        $dashYears->range(2024, 2026)->render();

        $invoices = [
            ['id' => 1, 'number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'amount' => 2400.00, 'date' => '2026-02-01', 'status' => 'paid'],
            ['id' => 2, 'number' => 'RE-2026-002', 'customer' => 'Technik AG', 'amount' => 5800.00, 'date' => '2026-02-05', 'status' => 'pending'],
            ['id' => 3, 'number' => 'RE-2026-003', 'customer' => 'Design Studio', 'amount' => 1200.00, 'date' => '2026-01-15', 'status' => 'overdue'],
            ['id' => 4, 'number' => 'RE-2026-004', 'customer' => 'Web Solutions', 'amount' => 3600.00, 'date' => '2026-02-10', 'status' => 'paid'],
            ['id' => 5, 'number' => 'RE-2026-005', 'customer' => 'Media House', 'amount' => 950.00, 'date' => '2026-02-12', 'status' => 'pending'],
        ];
        $dashTable = new Table('dashboard-invoices');
        $dashTable->setData($invoices)
            ->search(['number', 'customer'])
            ->column('number', 'Invoice No.', ['width' => '140px', 'sortable' => true])
            ->column('customer', 'Customer', ['sortable' => true])
            ->column('amount', 'Amount', ['format' => 'currency', 'align' => 'right'])
            ->column('date', 'Date', ['format' => 'date', 'width' => '120px'])
            ->column('status', 'Status', ['format' => 'label'])
            ->paginate(10)->render();
        ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Skeleton — Starting point for new projects</h3>
    <div class="demo-card">
        <p class="demo-intro"><code>skeleton.php</code> is the ready-made scaffolding for a new GridKit project. Just copy, adjust title + navigation, fill in sections — done.</p>
        <div style="background:var(--gk-primary-container);color:var(--gk-on-primary-container);border-radius:var(--gk-radius);padding:16px 20px;font-size:14px;line-height:1.7;">
            <strong>Quick start:</strong><br>
            <code style="font-size:13px;">cp /path/to/gridkit/skeleton.php my-project/index.php</code>
        </div>
    </div>
    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Anatomy of a GridKit Page</h3>
        <p style="margin:0 0 20px;font-size:13px;color:var(--gk-on-surface-variant);">Every page follows this structure — from <code>skeleton.php</code> to production.</p>

        <div class="anatomy-layout">
            <!-- Visual page mockup -->
            <div class="anatomy-mockup" style="border:2px solid var(--gk-outline-variant);border-radius:10px;overflow:hidden;font-size:11px;">
                <!-- Config bar -->
                <div style="background:var(--gk-primary);color:var(--gk-on-primary);padding:6px 10px;font-weight:600;font-size:10px;letter-spacing:0.04em;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                    <span class="material-icons" style="font-size:13px" aria-hidden="true">settings</span> Config
                </div>
                <div style="display:flex;flex:1;">
                    <!-- Sidebar mock -->
                    <div style="width:56px;background:var(--gk-surface-dim);border-right:1px solid var(--gk-outline-variant);display:flex;flex-direction:column;align-items:center;padding:10px 0;gap:6px;">
                        <span class="material-icons" style="font-size:16px;color:var(--gk-primary)" aria-hidden="true">widgets</span>
                        <div style="width:24px;height:2px;background:var(--gk-outline-variant);border-radius:1px;margin:2px 0;"></div>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)" aria-hidden="true">home</span>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)" aria-hidden="true">people</span>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)" aria-hidden="true">settings</span>
                        <div style="flex:1"></div>
                        <span style="font-size:9px;color:var(--gk-on-surface-variant);writing-mode:vertical-rl;transform:rotate(180deg);letter-spacing:0.1em;">SIDEBAR</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;">
                        <!-- Header mock -->
                        <div style="padding:8px 10px;border-bottom:1px solid var(--gk-outline-variant);display:flex;align-items:center;justify-content:space-between;background:var(--gk-surface);">
                            <span style="font-weight:600;font-size:11px;color:var(--gk-on-surface)">Header</span>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <span class="material-icons" style="font-size:13px;color:var(--gk-on-surface-variant)" aria-hidden="true">search</span>
                                <div style="width:16px;height:16px;border-radius:50%;background:var(--gk-primary-container)"></div>
                            </div>
                        </div>
                        <!-- Content mock -->
                        <div style="flex:1;padding:10px;background:var(--gk-surface-container);min-height:100px;">
                            <div style="font-size:9px;font-weight:600;color:var(--gk-on-surface-variant);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px;">Content</div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:4px;margin-bottom:6px;">
                                <div style="background:var(--gk-surface);border-radius:3px;height:18px;"></div>
                                <div style="background:var(--gk-surface);border-radius:3px;height:18px;"></div>
                            </div>
                            <div style="background:var(--gk-surface);border-radius:3px;height:40px;"></div>
                        </div>
                        <!-- Footer mock -->
                        <div style="padding:6px 10px;border-top:1px solid var(--gk-outline-variant);background:var(--gk-surface);font-size:9px;color:var(--gk-on-surface-variant);text-align:center;">
                            Footer · JS
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code reference -->
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-primary);border-radius:8px;color:var(--gk-on-primary);">
                    <span class="material-icons" style="font-size:18px" aria-hidden="true">settings</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">Configuration</div>
                        <code style="font-size:11px;opacity:0.85;word-break:break-all;">Theme::set('indigo') · Layout::bodyTag('gk-root')</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-secondary)" aria-hidden="true">menu</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Sidebar</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">->brand() · ->group() · ->item(label, url, icon)</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-tertiary, #7c3aed)" aria-hidden="true">web</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Header</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">->title() · ->fixed() · ->user(name, opts)</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);flex:1;">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-error)" aria-hidden="true">dashboard</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Content</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">&lt;main class="gk-main"&gt; · Tables, Forms, Cards…</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-outline)" aria-hidden="true">code</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Footer</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">&lt;script src="gridkit.js"&gt;</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Auth — Login Protection</h3>
    <div class="demo-card">
        <p style="color:var(--gk-on-surface-variant);margin:0 0 16px"><code>Auth</code> protects pages with session-based login. Passwords are stored as bcrypt hashes.</p>
        <div class="demo-code">Auth::protect();              // Protect page
Auth::login($user, $pass);   // Login attempt
Auth::logout('login.php');   // Clear session
Auth::check();               // Logged in?
Auth::user();                // Username
Auth::hashPassword('abc');  // bcrypt hash
Auth::renderLogin([...]);    // Login page</div>
    </div>
    <div class="demo-card" style="text-align:center">
        <p style="margin:0 0 12px;color:var(--gk-on-surface-variant)">Open the login page live demo:</p>
        <a href="login.php" target="_blank" class="gk-btn gk-btn-filled gk-btn-primary"><span class="material-icons" aria-hidden="true">lock_open</span> Open Login Demo</a>
        <p style="margin:12px 0 0;font-size:12px;color:var(--gk-on-surface-variant)">Credentials: <strong>demo</strong> / <strong>demo</strong></p>
    </div>
</div>

