<?php
use GridKit\Button;
use GridKit\PermissionMatrix;
?>
<!-- ===== PERMISSIONS (PermissionMatrix, 1.115.0) ===== -->
<div class="demo-section" data-section="permissions">
    <h2>Permissions <?= since('permission-matrix') ?></h2>
    <p class="demo-intro"><code>PermissionMatrix</code> puts roles in columns and permissions in rows, grouped by module. It is a plain form first: every cell posts <code>perm[role][permission]</code> as <code>0</code> or <code>1</code>, and <code>read()</code> on the server gives it back as role → permission → true/false. With the script: Support and Editor inherit Guest (ticked "via Guest", changed in Guest's column), Admin is locked, ticking "Publish pages" ticks what it needs and unticking "View pages" unticks what needs it. Tick a whole row, column or group; filter; fold a module; changed cells keep a dot until saved. One tab stop — the arrow keys move between cells, Space toggles. On a phone it shows one role at a time.</p>

    <h3 class="demo-h3" id="permissions-form">Roles and rights, saved with the form</h3>
        <?php
        showcase(function () {
            $rights = static fn (array $grants): PermissionMatrix => (new PermissionMatrix('rights'))
                ->caption('Roles and rights')
                ->role('admin', 'Admin', ['locked' => true, 'color' => 'red', 'description' => 'Owner of the installation'])
                ->role('accounting', 'Accounting', ['color' => 'green', 'description' => 'Invoices and payments'])
                ->role('editor', 'Editor', ['parent' => 'guest', 'color' => 'blue', 'description' => 'Pages and newsletters'])
                ->role('support', 'Support', ['parent' => 'guest', 'color' => 'orange', 'description' => 'Answers customers'])
                ->role('guest', 'Guest', ['color' => 'gray', 'description' => 'Reads, changes nothing'])
                ->group('invoices', 'Invoices')
                ->permission('invoices.view', 'View invoices')
                ->permission('invoices.edit', 'Create and edit invoices', ['requires' => 'invoices.view'])
                ->permission('invoices.send', 'Send invoices to customers', ['requires' => 'invoices.view'])
                ->permission('invoices.cancel', 'Cancel invoices', ['requires' => 'invoices.edit', 'danger' => true,
                    'description' => 'Writes a credit note; the invoice stays on record.'])
                ->permission('invoices.delete', 'Delete invoices', ['requires' => 'invoices.edit', 'danger' => true,
                    'description' => 'Invoices have to be kept for seven years.'])
                ->group('customers', 'Customers')
                ->permission('customers.view', 'View customers')
                ->permission('customers.edit', 'Edit customer data', ['requires' => 'customers.view'])
                ->permission('customers.export', 'Export the customer list', ['requires' => 'customers.view', 'danger' => true,
                    'description' => 'Personal data leaves the system as a CSV file.'])
                ->group('newsletter', 'Newsletter')
                ->permission('newsletter.view', 'View campaigns and lists')
                ->permission('newsletter.write', 'Write campaigns', ['requires' => 'newsletter.view'])
                ->permission('newsletter.send', 'Send to subscribers', ['requires' => 'newsletter.write', 'danger' => true])
                ->permission('newsletter.lists', 'Manage recipient lists', ['requires' => 'newsletter.view'])
                ->group('cms', 'CMS pages')
                ->permission('cms.view', 'View pages and drafts')
                ->permission('cms.edit', 'Edit pages', ['requires' => 'cms.view'])
                ->permission('cms.publish', 'Publish pages', ['requires' => 'cms.edit'])
                ->permission('cms.media', 'Upload images and files', ['requires' => 'cms.view'])
                ->group('users', 'Users & rights', ['collapsed' => true])
                ->permission('users.view', 'View users')
                ->permission('users.edit', 'Create and edit users', ['requires' => 'users.view', 'danger' => true])
                ->permission('users.roles', 'Change roles and rights', ['requires' => 'users.edit', 'danger' => true])
                ->permission('users.impersonate', 'Sign in as another user', ['requires' => 'users.view', 'danger' => true])
                ->grants($grants);

            $stored = [                                   // what your database holds
                'accounting' => ['invoices.view', 'invoices.edit', 'invoices.send', 'invoices.cancel',
                                 'customers.view', 'customers.edit', 'customers.export'],
                'editor'     => ['cms.edit', 'cms.publish', 'cms.media', 'newsletter.write'],
                'support'    => ['customers.edit', 'invoices.view', 'invoices.send'],
                'guest'      => ['customers.view', 'newsletter.view', 'cms.view'],
            ];

            if (isset($_POST['perm'])) {
                // role => permission => bool for every posted cell; what a ticked right needs is added.
                foreach ($rights($stored)->read($_POST['perm']) as $role => $cells) {
                    $stored[$role] = array_keys(array_filter($cells));   // … write it to your database here
                }
                $saved = [];
                foreach ($stored as $role => $list) $saved[] = $role . ': ' . (implode(', ', $list) ?: '—');
                echo '<p class="gk-message gk-message-success" role="status" style="overflow-wrap:anywhere">Read back and saved — '
                    . htmlspecialchars(implode(' · ', $saved), ENT_QUOTES) . '</p>';
            }

            echo '<form method="post" action="#permissions">';
            $rights($stored)->render();
            echo '<div class="demo-btn-row" style="margin-top:12px">'
                . Button::render('Save rights', ['type' => 'submit', 'icon' => 'check'])
                . '</div></form>';
        });
        ?>
    <p class="demo-intro" style="margin-top:12px" data-perm-log aria-live="off">Every change fires <code>gk:permchange</code> — the last one: <span>nothing yet</span>.</p>
    <script>
    document.addEventListener('gk:permchange', function (e) {
        if (e.detail.matrix !== 'rights') return;
        var d = e.detail, n = d.changes.length;
        document.querySelector('[data-perm-log] span').textContent = d.kind
            + (d.role ? ' · ' + d.role : '') + (d.permission ? ' · ' + d.permission : '')
            + ' → ' + d.changed.length + ' cell(s) changed, ' + n + ' not saved yet';
    });
    </script>

    <h3 class="demo-h3" id="permissions-server">On the server</h3>
    <div class="demo-code"><pre>// The form posts every cell: a hidden "0", and the checkbox's "1" when it is ticked.
//   perm[editor][cms.publish] = 1      perm[editor][cms.media] = 0      (Admin, locked: nothing)
// Build the matrix as for the page, then:
$map = $matrix->read($_POST['perm'] ?? []);     // ['editor' => ['cms.publish' => true, 'cms.media' => false, …], …]

// Without the definition at hand — only roles and keys you allow, only 0/1/true/false:
$map = PermissionMatrix::fromPost($_POST['perm'] ?? [], ['editor', 'guest'], ['cms.view', 'cms.edit']);

foreach ($map as $role => $cells) {
    foreach ($cells as $permission => $granted) {
        $granted ? $db->grant($role, $permission) : $db->revoke($role, $permission);
    }
}</pre></div>

    <h3 class="demo-h3" id="permissions-autosave">Saved as you go</h3>
    <p class="demo-intro"><code>saveUrl()</code> posts each change as JSON — <code>{"matrix":"pages","changes":{"author":{"cms.publish":true}}}</code> — and expects <code>{"ok":true}</code>. This demo's endpoint refuses to grant "Delete pages": the box goes back and a toast says why. Author inherits Contributor; the matrix is <code>height('260px')</code>, so its head stays in view while it scrolls.</p>
        <?php
        showcase(function () {
            (new PermissionMatrix('pages'))
                ->caption('Page rights')
                ->role('editor', 'Editor')
                ->role('author', 'Author', ['parent' => 'contributor'])
                ->role('contributor', 'Contributor')
                ->group('cms', 'CMS pages')
                ->permission('cms.view', 'View pages')
                ->permission('cms.edit', 'Edit pages', ['requires' => 'cms.view'])
                ->permission('cms.publish', 'Publish pages', ['requires' => 'cms.edit'])
                ->permission('cms.delete', 'Delete pages', ['requires' => 'cms.edit', 'danger' => true])
                ->grants(['editor' => ['cms.view', 'cms.edit', 'cms.publish'], 'contributor' => ['cms.view']])
                ->saveUrl('api/permissions.php')               // + ['headers' => ['X-CSRF-Token' => $token]]
                ->height('260px')
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="permissions-readonly">Read only</h3>
    <p class="demo-intro"><code>readonly()</code> shows who may do what and posts nothing. Filter and folding stay; the cells can still be reached with the arrow keys and say what they are.</p>
        <?php
        showcase(function () {
            (new PermissionMatrix('rights-view'))
                ->caption('Rights of the support team')
                ->role('support', 'Support', ['parent' => 'guest'])
                ->role('guest', 'Guest')
                ->group('customers', 'Customers')
                ->permission('customers.view', 'View customers')
                ->permission('customers.edit', 'Edit customer data', ['requires' => 'customers.view'])
                ->grants(['guest' => ['customers.view'], 'support' => ['customers.edit']])
                ->readonly()
                ->render();
        });
        ?>
</div>
