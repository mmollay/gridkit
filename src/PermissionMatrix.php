<?php
declare(strict_types=1);

namespace GridKit;

/**
 * PermissionMatrix (1.115.0) — roles as columns, permissions as rows grouped by module, one checkbox
 * where they meet: the screen of every user and rights administration.
 *
 *     (new PermissionMatrix('rights'))
 *         ->role('admin', 'Admin', ['locked' => true])
 *         ->role('editor', 'Editor', ['parent' => 'guest'])
 *         ->role('guest', 'Guest')
 *         ->group('cms', 'CMS pages')
 *         ->permission('cms.view', 'View pages')
 *         ->permission('cms.edit', 'Edit pages', ['requires' => 'cms.view'])
 *         ->grants(['guest' => ['cms.view'], 'editor' => ['cms.edit']])
 *         ->render();
 *
 * It is a plain form first. Every cell posts `perm[role][permission]` as "0" or "1" (a hidden "0"
 * in front of the checkbox, the checkbox's "1" wins when it is ticked), so the server can tell an
 * unticked box from one that was never on the page; read it back with read() or fromPost(). The
 * script (block "1.115.0 — permission matrix" of js/gridkit.js) adds what a form cannot do: the
 * inherited cells following their parent role live, `requires`, the bulk toggles, the filter,
 * folding, the marks of what changed, grid keyboard navigation, one role at a time on a phone and,
 * with saveUrl(), saving each change as it is made. A copy of gridkit.js without that block (the
 * PawBot dashboard keeps its own) shows the form: the controls that need the script stay hidden.
 *
 * What a role has is its own grants plus, with 'parent', everything its parent has (and the
 * parent's parent). An inherited right is shown ticked with "via <parent>" and cannot be removed
 * in the child's column — it is removed in the parent's. A locked role (an administrator) has
 * every permission and nothing about it can be changed; it posts nothing.
 */
class PermissionMatrix
{
    /** The colours a role's name can carry — those of .gk-label. */
    private const COLORS = ['blue', 'green', 'orange', 'red', 'gray'];

    /** The lock of a locked role, the chevron of a group and the mark of a risky right; inline, no icon font. */
    private const SVG_LOCK = '<svg class="gk-perm-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
    private const SVG_CHEVRON = '<svg class="gk-perm-chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>';

    private string $id;
    private string $name = 'perm';
    private string $caption = '';
    private bool $readonly = false;
    private string $saveUrl = '';
    /** @var array<string,string> */
    private array $saveHeaders = [];
    private string $height = '';

    /** @var array<string,array{label:string,description:string,parent:string,locked:bool,color:string,icon:string}> */
    private array $roles = [];
    /** @var array<string,array{label:string,description:string,icon:string,collapsed:bool}> */
    private array $groups = [];
    /** @var array<string,array{label:string,description:string,danger:bool,requires:list<string>,group:string}> */
    private array $permissions = [];
    /** The group permission() adds to: the last group(), '' before the first. */
    private string $currentGroup = '';
    /** @var array<string,array<string,true>> role => permission => true, as given; checked at render */
    private array $granted = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * A role, as a column. Options: `description` (a line under the name), `parent` (the key of a role
     * whose rights this one inherits), `locked` (true: every permission, read-only — an administrator),
     * `color` (blue, green, orange, red or gray: the name as a label of that colour), `icon` (a Material
     * icon name).
     *
     * @param array{description?:string,parent?:string,locked?:bool,color?:string,icon?:string} $opts
     */
    public function role(string $key, string $label, array $opts = []): static
    {
        if (!$this->validKey($key, 'role')) return $this;
        if (isset($this->roles[$key])) {
            trigger_error("GridKit: PermissionMatrix role '$key' is declared twice — the second one replaces the first.", E_USER_WARNING);
        }
        $color = (string) ($opts['color'] ?? '');
        if ($color !== '' && !in_array($color, self::COLORS, true)) {
            trigger_error("GridKit: PermissionMatrix role '$key' has 'color' => " . self::shown($color)
                . ' — it takes ' . implode(', ', self::COLORS) . '. The name stays plain.', E_USER_WARNING);
            $color = '';
        }
        if (array_key_exists('locked', $opts) && !is_bool($opts['locked'])) {
            trigger_error("GridKit: PermissionMatrix role '$key' has 'locked' => " . self::shown($opts['locked'])
                . ' — it takes true or false. The role can be changed.', E_USER_WARNING);
            $opts['locked'] = false;
        }
        $this->roles[$key] = [
            'label'       => $label,
            'description' => (string) ($opts['description'] ?? ''),
            'parent'      => is_string($opts['parent'] ?? null) ? $opts['parent'] : '',
            'locked'      => ($opts['locked'] ?? false) === true,
            'color'       => $color,
            'icon'        => (string) ($opts['icon'] ?? ''),
        ];
        return $this;
    }

    /**
     * A module: the permissions declared after it belong to it, under a heading row that folds and
     * carries the bulk toggles of the group. Options: `description`, `icon`, `collapsed` (true: starts
     * folded — only with the script; without it every row shows). Permissions declared before the first
     * group stand above it without a heading.
     *
     * @param array{description?:string,icon?:string,collapsed?:bool} $opts
     */
    public function group(string $key, string $label, array $opts = []): static
    {
        if (!$this->validKey($key, 'group')) return $this;
        if (isset($this->groups[$key])) {
            trigger_error("GridKit: PermissionMatrix group '$key' is declared twice — its permissions are added to the first.", E_USER_WARNING);
        } else {
            $this->groups[$key] = [
                'label'       => $label,
                'description' => (string) ($opts['description'] ?? ''),
                'icon'        => (string) ($opts['icon'] ?? ''),
                'collapsed'   => ($opts['collapsed'] ?? false) === true,
            ];
        }
        $this->currentGroup = $key;
        return $this;
    }

    /**
     * A permission, as a row of the last group(). Options: `description`, `danger` (true: a risky right
     * — deleting invoices, managing users — marked "Risky" in words, not colour alone), `requires` (a
     * key or a list of keys this one needs: ticking it ticks them, unticking one of them unticks it).
     *
     * @param array{description?:string,danger?:bool,requires?:string|list<string>} $opts
     */
    public function permission(string $key, string $label, array $opts = []): static
    {
        if (!$this->validKey($key, 'permission')) return $this;
        if (isset($this->permissions[$key])) {
            trigger_error("GridKit: PermissionMatrix permission '$key' is declared twice — the second one was left out.", E_USER_WARNING);
            return $this;
        }
        $requires = $opts['requires'] ?? [];
        if (is_string($requires)) $requires = [$requires];
        if (!is_array($requires)) {
            trigger_error("GridKit: PermissionMatrix permission '$key' has 'requires' => " . self::shown($requires)
                . ' — it takes a permission key or a list of them. It requires nothing.', E_USER_WARNING);
            $requires = [];
        }
        $this->permissions[$key] = [
            'label'       => $label,
            'description' => (string) ($opts['description'] ?? ''),
            'danger'      => ($opts['danger'] ?? false) === true,
            'requires'    => array_values(array_unique(array_filter($requires, 'is_string'))),
            'group'       => $this->currentGroup,
        ];
        return $this;
    }

    /** What a role has been given itself: `->grant('editor', 'cms.view', 'cms.edit')`. Adds to earlier calls. */
    public function grant(string $role, string ...$permissions): static
    {
        foreach ($permissions as $p) $this->granted[$role][$p] = true;
        return $this;
    }

    /**
     * The current state in one go, as it comes out of a database: role => list of permission keys
     * (`['editor' => ['cms.view', 'cms.edit']]`). Adds to grant() and to earlier calls. A role or
     * permission the matrix does not have is reported when the matrix renders, and left out.
     *
     * @param array<string,list<string>> $map
     */
    public function grants(array $map): static
    {
        foreach ($map as $role => $list) {
            if (!is_array($list)) {
                trigger_error('GridKit: PermissionMatrix grants() takes role => list of permissions; '
                    . self::shown((string) $role) . ' has ' . get_debug_type($list) . ' and was left out.', E_USER_WARNING);
                continue;
            }
            foreach ($list as $p) {
                if (is_string($p) || is_int($p)) $this->granted[(string) $role][(string) $p] = true;
            }
        }
        return $this;
    }

    /**
     * The name the cells post under (default `perm`): `perm[role][permission]`. A nested name such as
     * `user[rights]` is allowed and read back as `$_POST['user']['rights']`. Anything PHP would not
     * read back as that array (a dot, a space, an unclosed bracket) is refused with a warning.
     */
    public function name(string $name): static
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?:\[[^\[\]\x00-\x1F]+\])*$/', $name) !== 1) {
            trigger_error('GridKit: PermissionMatrix::name() takes a form field name such as \'perm\' or \'user[rights]\' — '
                . self::shown($name) . ' would not come back as that array, so the cells post as \'perm\'.', E_USER_WARNING);
            return $this;
        }
        $this->name = $name;
        return $this;
    }

    /** What the matrix is, for a screen reader (a <caption>) and as the name of its scroll box. */
    public function caption(string $text): static
    {
        $this->caption = $text;
        return $this;
    }

    /** Show the rights, change nothing: no field is posted, no bulk toggle is drawn. Filter and folding stay. */
    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;
        return $this;
    }

    /**
     * Save every change as it is made (with the script): GridKit POSTs `{"matrix": id, "changes":
     * {role: {permission: true|false}}}` as JSON to $url and expects `{"ok": true}`. Anything else puts
     * the cells back and shows the answer's `error` (or a sentence of its own) in a toast. Option
     * `headers` adds request headers — typically `['X-CSRF-Token' => $token]`. Read the body with
     * read($body['changes']) or fromPost(). Without the script the fields post with the surrounding
     * form as before. A URL that is not http(s) or relative is refused with a warning.
     *
     * @param array{headers?:array<string,string>} $opts
     */
    public function saveUrl(string $url, array $opts = []): static
    {
        $clean = preg_replace('/[\x00-\x20]/', '', $url) ?? '';
        $safe = $clean !== '' && !str_starts_with($clean, '//')
            && (preg_match('/^(https?:|\/|\.\/|\.\.\/|\?)/i', $clean) === 1 || !str_contains($clean, ':'));
        if (!$safe) {
            trigger_error('GridKit: PermissionMatrix::saveUrl() takes a relative or http(s) URL — '
                . self::shown($url) . ' was left out; changes post with the form.', E_USER_WARNING);
            return $this;
        }
        $this->saveUrl = $url;
        $headers = $opts['headers'] ?? [];
        $this->saveHeaders = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                // A header name is a token; a value has no line break. Anything else would make
                // fetch() throw in the browser, and every change would fail to save.
                if (is_string($k) && preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $k) === 1 && is_scalar($v)
                    && !preg_match('/[\r\n]/', (string) $v)) {
                    $this->saveHeaders[$k] = (string) $v;
                } else {
                    trigger_error('GridKit: PermissionMatrix::saveUrl() header ' . self::shown((string) $k) . ' was left out — a header is a name and a one-line value.', E_USER_WARNING);
                }
            }
        }
        return $this;
    }

    /**
     * How high the matrix may grow before it scrolls in its own box, with the role row and the
     * permission column staying in view (default 70vh). A CSS length such as '480px' or '60vh';
     * anything else is refused with a warning and the default holds.
     */
    public function height(string $maxHeight): static
    {
        $maxHeight = trim($maxHeight);
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|vh|dvh|svh|lvh|rem|em)$/', $maxHeight, $m) !== 1 || (float) $m[1] <= 0) {
            trigger_error('GridKit: PermissionMatrix::height() takes a CSS length such as \'480px\' or \'60vh\' — '
                . self::shown($maxHeight) . ' was left out, the box is 70vh high.', E_USER_WARNING);
            return $this;
        }
        $this->height = $maxHeight;
        return $this;
    }

    /**
     * The posted matrix, cleaned: role => permission => bool, for exactly the cells that were posted —
     * a "0" is false, a "1" is true, a cell that was not on the page is not in the result (so a matrix
     * that showed one module cannot wipe the others). Accepts the form's `$_POST['perm']` and the
     * `changes` of a saveUrl() request. Roles and permissions that are not in the lists, values that
     * are not 0/1/true/false, and anything that is not an array are dropped.
     *
     *     $map = PermissionMatrix::fromPost($_POST['perm'] ?? [], ['editor', 'guest'], ['cms.view', 'cms.edit']);
     *     foreach ($map as $role => $cells) foreach ($cells as $permission => $granted) { … }
     *
     * Leave a locked role out of $roles: it posts nothing, and nothing posted for it should count.
     * read() does this with the matrix's own roles and permissions, and adds what `requires` needs.
     *
     * @param list<string> $roles
     * @param list<string> $permissions
     * @return array<string,array<string,bool>>
     */
    public static function fromPost(mixed $posted, array $roles, array $permissions): array
    {
        if (!is_array($posted)) return [];
        $roleSet = array_fill_keys(array_map('strval', array_filter($roles, 'is_scalar')), true);
        $permSet = array_fill_keys(array_map('strval', array_filter($permissions, 'is_scalar')), true);
        $out = [];
        foreach ($posted as $role => $cells) {
            $role = (string) $role;
            if (!isset($roleSet[$role]) || !is_array($cells)) continue;
            foreach ($cells as $perm => $value) {
                $perm = (string) $perm;
                if (!isset($permSet[$perm])) continue;
                $bool = self::postedBool($value);
                if ($bool !== null) $out[$role][$perm] = $bool;
            }
        }
        return $out;
    }

    /**
     * fromPost() with this matrix's roles (the locked ones and a readonly() matrix's left out) and
     * permissions, and with `requires` made whole: a permission granted without what it needs gets
     * it added, as ticking it does in the browser. The form posts every cell, so this is all the
     * server needs — build the matrix as for the page, then `$matrix->read($_POST['perm'] ?? [])`.
     * What is needed counts as there when the role inherits it from its parent.
     *
     * @return array<string,array<string,bool>>
     */
    public function read(mixed $posted): array
    {
        if ($this->readonly) return [];
        $roles = $this->resolvedRoles(false);
        $editable = array_keys(array_filter($roles, static fn (array $r): bool => !$r['locked']));
        $map = self::fromPost($posted, $editable, array_keys($this->permissions));
        $requires = $this->resolvedRequires(false);

        // What each role has: the posted cells, else what grants() said, plus what its parents have.
        $own = $this->resolvedGrants($roles, false);
        foreach ($map as $role => $cells) {
            foreach ($cells as $perm => $granted) {
                if ($granted) $own[$role][$perm] = true;
                else unset($own[$role][$perm]);
            }
        }
        foreach ($map as $role => $cells) {
            foreach (array_keys(array_filter($cells)) as $perm) {
                foreach (self::closure((string) $perm, $requires) as $need) {
                    if (!self::has((string) $role, $need, $roles, $own)) {
                        $map[$role][$need] = true;
                        $own[$role][$need] = true;
                    }
                }
            }
        }
        return $map;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $roles = $this->resolvedRoles(true);
        $requires = $this->resolvedRequires(true);
        $own = $this->resolvedGrants($roles, true);
        $base = preg_replace('/\s+/', '-', $this->id) ?: 'gk-perm';
        $editable = !$this->readonly;
        $editableRoles = array_keys(array_filter($roles, static fn (array $r): bool => !$r['locked']));
        $bulk = $editable && $editableRoles !== [];
        $total = count($this->permissions);

        if ($editable) $this->warnInputVars(count($editableRoles) * $total);

        // What the script reads: the relations it keeps whole, the field name it writes back, and its
        // words in the page's language — so it needs no Lang::jsConfig() to speak it.
        $config = [
            'id'       => $this->id,
            'name'     => $this->name,
            'readonly' => $this->readonly,
            'save'     => $editable && $this->saveUrl !== '' ? $this->saveUrl : null,
            'headers'  => $this->saveHeaders ?: new \stdClass(),
            'roles'    => array_map(static fn (string $k, array $r): array => [
                'key' => (string) $k, 'label' => $r['label'], 'parent' => $r['parent'] !== '' ? $r['parent'] : null, 'locked' => $r['locked'],
            ], array_keys($roles), $roles),
            'perms'    => array_map(static fn (string $k, array $p): array => [
                'key' => (string) $k, 'label' => $p['label'], 'group' => $p['group'], 'requires' => $requires[$k] ?? [], 'danger' => $p['danger'],
            ], array_keys($this->permissions), $this->permissions),
            'groups'   => array_map(static fn (array $g): string => $g['label'], $this->groups) ?: new \stdClass(),
            'i18n'     => $this->scriptStrings(),
        ];

        echo '<div class="gk-perm" id="' . $e($base) . '" data-gk-perm="' . $e($this->id) . '"'
           . ($this->readonly ? ' data-gk-perm-readonly' : '')
           . ' data-gk-perm-config="' . $e(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) . '">';

        $this->renderToolbar($base, $editable, $e);
        $this->renderPicker($base, $roles, $editableRoles, $e);

        $caption = $this->caption !== '' ? $this->caption : Lang::t('perm.caption');
        echo '<div class="gk-perm-box" role="region" tabindex="0" aria-labelledby="' . $e($base) . '-caption"'
           . ($this->height !== '' ? ' style="--gk-perm-height:' . $e($this->height) . '"' : '') . '>'
           . '<table class="gk-table gk-perm-table" id="' . $e($base) . '-table">'
           . '<caption id="' . $e($base) . '-caption" class="gk-sr-only">' . $e($caption) . '</caption>';
        $this->renderHead($roles, $own, $bulk, $e);

        $byGroup = [];
        foreach ($this->permissions as $key => $p) $byGroup[$p['group']][] = (string) $key;
        $cellNo = 0;
        foreach ($byGroup as $groupKey => $keys) {
            $this->renderGroup($base, (string) $groupKey, $keys, $roles, $own, $requires, $bulk, $cellNo, $e);
        }
        echo '</table></div>';

        echo '<p class="gk-perm-empty" data-gk-perm-empty hidden>' . $e(Lang::t('perm.none_found')) . '</p>';
        $this->renderLegend($roles, $editable, $e);
        echo '<div class="gk-sr-only" role="status" aria-live="polite" data-gk-perm-status></div>';

        // The descriptions cells point at (aria-describedby): hidden, so they are not read as loose
        // sentences, and shared, so a column of a hundred inherited cells carries one id, not a hundred texts.
        echo '<div hidden>';
        foreach ($roles as $key => $r) {
            $i = self::roleIndex($roles)[(string) $key];
            if ($r['locked']) {
                echo '<span id="' . $e($base) . '-d-locked-' . $i . '">' . $e(Lang::t('perm.locked_hint', ['role' => $r['label']])) . '</span>';
            }
            echo '<span id="' . $e($base) . '-d-inh-' . $i . '" data-gk-perm-inh="' . $e($key) . '">'
               . $e(Lang::t('perm.inherited_hint', ['role' => $r['label']])) . '</span>';
        }
        echo '<span id="' . $e($base) . '-d-changed" data-gk-perm-changed-hint>' . $e(Lang::t('perm.changed_hint')) . '</span>';
        echo '</div>';

        echo '</div>';
    }

    // ── Rendering ──────────────────────────────────────────────────────────

    /** Filter, fold all, the count of changes and Reset — all need the script, so all start hidden. */
    private function renderToolbar(string $base, bool $editable, \Closure $e): void
    {
        $filter = Lang::t('perm.filter');
        echo '<div class="gk-toolbar gk-perm-toolbar" data-gk-perm-js hidden>'
           . '<input type="search" class="gk-search" data-gk-perm-search aria-label="' . $e($filter) . '"'
           . ' placeholder="' . $e($filter) . '" aria-controls="' . $e($base) . '-table" autocomplete="off">';
        if ($this->groups !== []) {
            echo Button::render(Lang::t('perm.fold_all'), [
                'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
                'data' => ['gk-perm-foldall' => ''],
            ]);
        }
        echo '<div class="gk-toolbar-spacer"></div>';
        if ($editable) {
            echo '<span class="gk-perm-changes" data-gk-perm-changes>' . $e(Lang::t('perm.changes_none')) . '</span>';
            if ($this->saveUrl === '') {
                echo Button::render(Lang::t('perm.reset'), [
                    'variant' => 'outlined', 'color' => 'neutral', 'size' => 'sm', 'disabled' => true,
                    'data' => ['gk-perm-reset' => ''],
                ]);
            }
        }
        echo '</div>';
    }

    /** One role at a time on a phone: the select the script shows there (CSS keeps it out of a wide screen). */
    private function renderPicker(string $base, array $roles, array $editableRoles, \Closure $e): void
    {
        if ($roles === []) return;
        $first = $editableRoles[0] ?? array_key_first($roles);
        echo '<div class="gk-perm-picker" data-gk-perm-js hidden>'
           . '<label for="' . $e($base) . '-pick">' . $e(Lang::t('perm.role')) . '</label>'
           . '<select class="gk-filter" id="' . $e($base) . '-pick" data-gk-perm-pick>';
        foreach ($roles as $key => $r) {
            echo '<option value="' . $e($key) . '"' . ($key === $first ? ' selected' : '') . '>' . $e($r['label']) . '</option>';
        }
        echo '</select></div>';
    }

    /** The role row: the corner, the "All roles" column (script only) and one header per role. */
    private function renderHead(array $roles, array $own, bool $bulk, \Closure $e): void
    {
        $total = count($this->permissions);
        echo '<thead><tr>'
           . '<th scope="col" class="gk-perm-corner">' . $e(Lang::t('perm.permission')) . '</th>';
        if ($bulk) {
            echo '<th scope="col" class="gk-perm-all" data-gk-perm-js hidden>' . $e(Lang::t('perm.all_roles')) . '</th>';
        }
        foreach ($roles as $key => $r) {
            $count = 0;
            foreach (array_keys($this->permissions) as $p) {
                if (self::has((string) $key, (string) $p, $roles, $own)) $count++;
            }
            $name = $e($r['label']);
            if ($r['color'] !== '') $name = '<span class="gk-label gk-label-' . $r['color'] . '">' . $name . '</span>';
            $icon = $r['icon'] !== '' ? Icon::svg($r['icon'], 16) : '';
            echo '<th scope="col" class="gk-perm-role' . ($r['locked'] ? ' gk-perm-locked' : '') . '" data-gk-perm-role="' . $e($key) . '">'
               . '<span class="gk-perm-role-name">' . $icon . $name . '</span>';
            if ($r['description'] !== '') {
                echo '<span class="gk-perm-role-desc" title="' . $e($r['description']) . '">' . $e($r['description']) . '</span>';
            }
            if ($r['locked']) {
                echo '<span class="gk-perm-role-note">' . self::SVG_LOCK . $e(Lang::t('perm.locked')) . '</span>';
            } elseif ($r['parent'] !== '') {
                echo '<span class="gk-perm-role-note">' . $e(Lang::t('perm.inherits', ['role' => $roles[$r['parent']]['label']])) . '</span>';
            }
            echo '<span class="gk-perm-role-foot">';
            if ($bulk && !$r['locked']) {
                echo '<input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="role" data-gk-perm-js hidden'
                   . ' aria-label="' . $e(Lang::t('perm.bulk_role', ['role' => $r['label']])) . '">';
            }
            echo '<span class="gk-perm-count" data-gk-perm-count>' . $e(Lang::t('perm.count', ['n' => $count, 'total' => $total])) . '</span>'
               . '</span></th>';
        }
        echo '</tr></thead>';
    }

    /**
     * One module: a <tbody> with its heading row (fold button, count, the group toggles) and a row per
     * permission. Permissions without a group get no heading row.
     *
     * @param list<string> $keys
     */
    private function renderGroup(string $base, string $groupKey, array $keys, array $roles, array $own, array $requires, bool $bulk, int &$cellNo, \Closure $e): void
    {
        $g = $this->groups[$groupKey] ?? null;
        echo '<tbody data-gk-perm-group="' . $e($groupKey) . '"'
           . ($g !== null && $g['collapsed'] ? ' data-gk-perm-start-folded' : '') . '>';
        if ($g !== null) {
            $label = $e($g['label']);
            $icon = $g['icon'] !== '' ? Icon::svg($g['icon'], 16) : '';
            echo '<tr class="gk-perm-group-row"><th scope="rowgroup" class="gk-perm-group-head">'
               // Two names: the fold button once the script runs, plain text before (a button that does
               // nothing is worse than none).
               . '<button type="button" class="gk-perm-fold" aria-expanded="true" data-gk-perm-js hidden>'
               .   self::SVG_CHEVRON . $icon . '<span class="gk-table-group-name">' . $label . '</span></button>'
               . '<span class="gk-perm-group-plain" data-gk-perm-nojs>' . $icon . '<span class="gk-table-group-name">' . $label . '</span></span>'
               . '<span class="gk-table-group-n">' . count($keys) . '</span>'
               . ($g['description'] !== '' ? '<span class="gk-perm-desc">' . $e($g['description']) . '</span>' : '')
               . '</th>';
            if ($bulk) {
                echo '<td class="gk-perm-all" data-gk-perm-js hidden><input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="group-all"'
                   . ' aria-label="' . $e(Lang::t('perm.bulk_group_all', ['group' => $g['label']])) . '"></td>';
            }
            foreach ($roles as $rk => $r) {
                echo '<td class="gk-perm-cell" data-gk-perm-role="' . $e($rk) . '">';
                if ($bulk && !$r['locked']) {
                    echo '<input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="group" data-gk-perm-js hidden'
                       . ' aria-label="' . $e(Lang::t('perm.bulk_group', ['role' => $r['label'], 'group' => $g['label']])) . '">';
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        foreach ($keys as $pk) {
            $this->renderRow($base, $pk, $roles, $own, $requires, $bulk, $cellNo, $e);
        }
        echo '</tbody>';
    }

    /** One permission: its name cell, the "All roles" toggle, and a cell per role. */
    private function renderRow(string $base, string $pk, array $roles, array $own, array $requires, bool $bulk, int &$cellNo, \Closure $e): void
    {
        $p = $this->permissions[$pk];
        echo '<tr class="gk-perm-row' . ($p['danger'] ? ' gk-perm-danger' : '') . '" data-gk-perm-perm="' . $e($pk) . '">'
           . '<th scope="row" class="gk-perm-name"><span class="gk-perm-label">' . $e($p['label']) . '</span>';
        if ($p['danger']) {
            echo ' <span class="gk-label gk-label-red gk-perm-risk">' . $e(Lang::t('perm.risky')) . '</span>';
        }
        if ($p['description'] !== '') echo '<span class="gk-perm-desc">' . $e($p['description']) . '</span>';
        if (($requires[$pk] ?? []) !== []) {
            $names = array_map(fn (string $k): string => $this->permissions[$k]['label'], $requires[$pk]);
            echo '<span class="gk-perm-needs">' . $e(Lang::t('perm.needs', ['list' => implode(', ', $names)])) . '</span>';
        }
        echo '</th>';
        if ($bulk) {
            echo '<td class="gk-perm-all" data-gk-perm-js hidden><input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="row"'
               . ' aria-label="' . $e(Lang::t('perm.bulk_row', ['perm' => $p['label']])) . '"></td>';
        }
        $index = self::roleIndex($roles);
        foreach ($roles as $rk => $r) {
            $rk = (string) $rk;
            $cellNo++;
            $id = $base . '-c' . $cellNo;
            $label = $r['label'] . ': ' . $p['label'];
            $mine = isset($own[$rk][$pk]);
            $from = $mine || $r['locked'] ? null : self::source($rk, $pk, $roles, $own);
            $classes = 'gk-perm-cell' . ($r['locked'] ? ' gk-perm-locked' : '') . ($from !== null ? ' gk-perm-inherited' : '');
            $field = $e($this->name . '[' . $rk . '][' . $pk . ']');
            echo '<td class="' . $classes . '" data-gk-perm-role="' . $e($rk) . '">';
            if ($r['locked']) {
                // Every right, nothing to post: a locked role cannot be changed by a form.
                echo '<input type="checkbox" class="gk-perm-check" id="' . $e($id) . '" checked disabled'
                   . ' aria-label="' . $e($label) . '" aria-describedby="' . $e($base) . '-d-locked-' . $index[$rk] . '">';
            } elseif ($this->readonly) {
                echo '<input type="checkbox" class="gk-perm-check" id="' . $e($id) . '"'
                   . ($mine || $from !== null ? ' checked' : '') . ' disabled aria-label="' . $e($label) . '"'
                   . ($from !== null ? ' aria-describedby="' . $e($base) . '-d-inh-' . $index[$from] . '"' : '') . '>';
            } elseif ($from !== null) {
                // Inherited: shown ticked and not removable here. The cell posts 0 — the heir does not
                // have it itself (an own grant would be shown as the role's own); inheritance follows
                // from 'parent' and is not stored.
                echo '<input type="hidden" name="' . $field . '" value="0">'
                   . '<input type="checkbox" class="gk-perm-check" id="' . $e($id) . '" checked disabled data-gk-perm-was="0"'
                   . ' aria-label="' . $e($label) . '" aria-describedby="' . $e($base) . '-d-inh-' . $index[$from] . '">';
            } else {
                // The hidden "0" first: an unticked box posts nothing, so without it a cleared cell and
                // a cell that was never on the page would read the same. A ticked box's "1" comes later
                // under the same name, and PHP keeps the last.
                echo '<input type="hidden" name="' . $field . '" value="0">'
                   . '<input type="checkbox" class="gk-perm-check" id="' . $e($id) . '" name="' . $field . '" value="1"'
                   . ($mine ? ' checked' : '') . ' data-gk-perm-was="' . ($mine ? '1' : '0') . '" aria-label="' . $e($label) . '">';
            }
            if ($from !== null) {
                echo '<span class="gk-perm-note" aria-hidden="true">' . $e(Lang::t('perm.via', ['role' => $roles[$from]['label']])) . '</span>';
            }
            echo '</td>';
        }
        echo '</tr>';
    }

    /**
     * What the marks mean, drawn with the classes the cells use. The swatches are inert (they look like
     * the controls, they are not ones); the marks that only the script makes are hidden without it.
     */
    private function renderLegend(array $roles, bool $editable, \Closure $e): void
    {
        $hasLocked = array_filter($roles, static fn (array $r): bool => $r['locked']) !== [];
        $hasParent = array_filter($roles, static fn (array $r): bool => $r['parent'] !== '') !== [];
        $hasDanger = array_filter($this->permissions, static fn (array $p): bool => $p['danger']) !== [];
        $hasNeeds  = array_filter($this->permissions, static fn (array $p): bool => $p['requires'] !== []) !== [];
        $swatch = static fn (string $cls, string $inner = ''): string =>
            '<span class="gk-perm-swatch' . $cls . '" inert><input type="checkbox" class="gk-perm-check" tabindex="-1" checked>' . $inner . '</span>';

        echo '<ul class="gk-perm-legend" aria-label="' . $e(Lang::t('perm.legend')) . '">'
           . '<li>' . $swatch('') . $e(Lang::t('perm.legend_granted')) . '</li>';
        if ($hasParent) {
            echo '<li>' . $swatch(' gk-perm-inherited', '<span class="gk-perm-note">' . $e(Lang::t('perm.via', ['role' => '…'])) . '</span>')
               . $e(Lang::t('perm.legend_inherited')) . '</li>';
        }
        if ($hasLocked) {
            echo '<li>' . $swatch(' gk-perm-locked') . $e(Lang::t('perm.legend_locked')) . '</li>';
        }
        if ($editable) {
            echo '<li data-gk-perm-js hidden>' . $swatch(' gk-perm-changed') . $e(Lang::t('perm.legend_changed')) . '</li>'
               . '<li data-gk-perm-js hidden><span class="gk-perm-swatch" inert><input type="checkbox" class="gk-perm-bulk" tabindex="-1" data-gk-perm-mixed></span>'
               . $e(Lang::t('perm.legend_mixed')) . '</li>';
        }
        if ($hasDanger) {
            echo '<li><span class="gk-label gk-label-red gk-perm-risk">' . $e(Lang::t('perm.risky')) . '</span> ' . $e(Lang::t('perm.legend_risky')) . '</li>';
        }
        if ($hasNeeds) {
            echo '<li><span class="gk-perm-needs">' . $e(Lang::t('perm.needs', ['list' => '…'])) . '</span> ' . $e(Lang::t('perm.legend_needs')) . '</li>';
        }
        echo '</ul>';
    }

    /** The words the script says, in the page's language. */
    private function scriptStrings(): array
    {
        $keys = ['changes_none', 'changes_one', 'changes', 'saving', 'saved', 'save_error', 'fold_all', 'unfold_all',
                 'count', 'via', 'needed_by', 'also_granted', 'also_removed', 'shown', 'inherited_hint', 'locked_hint',
                 'readonly_hint', 'nothing', 'reset_done'];
        $out = [];
        foreach ($keys as $k) $out[$k] = Lang::t('perm.' . $k);
        return $out;
    }

    // ── The model ──────────────────────────────────────────────────────────

    /**
     * The roles, with each 'parent' checked: one that names no role, or that would make a role its own
     * ancestor, is dropped (with a warning when $warn) — the role then inherits nothing.
     *
     * @return array<string,array{label:string,description:string,parent:string,locked:bool,color:string,icon:string}>
     */
    private function resolvedRoles(bool $warn): array
    {
        $roles = $this->roles;
        foreach ($roles as $key => $r) {
            if ($r['parent'] === '') continue;
            if (!isset($roles[$r['parent']])) {
                if ($warn) trigger_error("GridKit: PermissionMatrix role '$key' has 'parent' => " . self::shown($r['parent'])
                    . ' — no such role. It inherits nothing.', E_USER_WARNING);
                $roles[$key]['parent'] = '';
                continue;
            }
            $seen = [$key => true];
            for ($p = $r['parent']; $p !== ''; $p = $roles[$p]['parent']) {
                if (isset($seen[$p])) {
                    if ($warn) trigger_error("GridKit: PermissionMatrix role '$key' would inherit from itself (parent chain "
                        . implode(' → ', array_keys($seen)) . " → $p). It inherits nothing.", E_USER_WARNING);
                    $roles[$key]['parent'] = '';
                    break;
                }
                $seen[$p] = true;
            }
        }
        return $roles;
    }

    /**
     * Each permission's 'requires', with keys that name no permission (and itself) dropped.
     *
     * @return array<string,list<string>>
     */
    private function resolvedRequires(bool $warn): array
    {
        $out = [];
        foreach ($this->permissions as $key => $p) {
            $key = (string) $key;
            $out[$key] = [];
            foreach ($p['requires'] as $need) {
                if ($need === $key) continue;
                if (!isset($this->permissions[$need])) {
                    if ($warn) trigger_error("GridKit: PermissionMatrix permission '$key' requires " . self::shown($need)
                        . ' — no such permission. That requirement was left out.', E_USER_WARNING);
                    continue;
                }
                $out[$key][] = $need;
            }
        }
        return $out;
    }

    /**
     * What each role was given itself, without the roles and permissions the matrix does not have
     * (reported when $warn) and without anything for a locked role, which has everything anyway.
     *
     * @return array<string,array<string,true>>
     */
    private function resolvedGrants(array $roles, bool $warn): array
    {
        $out = [];
        foreach ($this->granted as $role => $perms) {
            if (!isset($roles[$role])) {
                if ($warn) trigger_error('GridKit: PermissionMatrix grants a role it does not have, ' . self::shown((string) $role) . ' — left out.', E_USER_WARNING);
                continue;
            }
            foreach (array_keys($perms) as $p) {
                $p = (string) $p;
                if (!isset($this->permissions[$p])) {
                    if ($warn) trigger_error("GridKit: PermissionMatrix grants '$role' a permission it does not have, " . self::shown($p) . ' — left out.', E_USER_WARNING);
                    continue;
                }
                if (!$roles[$role]['locked']) $out[$role][$p] = true;
            }
        }
        return $out;
    }

    /**
     * Each role's position, by its key as a string. PHP makes a key such as "7" an integer key of the
     * array; the ids the cells point at must not depend on which of the two a loop happens to hold.
     *
     * @return array<string,int>
     */
    private static function roleIndex(array $roles): array
    {
        return array_flip(array_map('strval', array_keys($roles)));
    }

    /** Does the role have the permission — itself, by being locked, or through a parent? */
    private static function has(string $role, string $perm, array $roles, array $own): bool
    {
        for ($r = $role; $r !== ''; $r = $roles[$r]['parent']) {
            if ($roles[$r]['locked'] || isset($own[$r][$perm])) return true;
        }
        return false;
    }

    /** The nearest ancestor a role inherits a permission from, or null when none of them has it. */
    private static function source(string $role, string $perm, array $roles, array $own): ?string
    {
        for ($r = $roles[$role]['parent']; $r !== ''; $r = $roles[$r]['parent']) {
            if ($roles[$r]['locked'] || isset($own[$r][$perm])) return $r;
        }
        return null;
    }

    /**
     * Everything a permission needs, through the needs of what it needs; a loop ends where it began.
     *
     * @param array<string,list<string>> $requires
     * @return list<string>
     */
    private static function closure(string $perm, array $requires): array
    {
        $seen = [$perm => true];
        $todo = $requires[$perm] ?? [];
        $out = [];
        while ($todo !== []) {
            $next = array_shift($todo);
            if (isset($seen[$next])) continue;
            $seen[$next] = true;
            $out[] = $next;
            foreach ($requires[$next] ?? [] as $n) $todo[] = $n;
        }
        return $out;
    }

    /** A posted cell as a boolean: "1", 1, true, "0", 0, false, "" — anything else is no answer. */
    private static function postedBool(mixed $value): ?bool
    {
        if ($value === true || $value === 1 || $value === '1') return true;
        if ($value === false || $value === 0 || $value === '0' || $value === '') return false;
        return null;
    }

    /**
     * A key becomes part of a field name (`perm[role][permission]`) and of attributes. PHP reads a
     * bracket inside a key as the end of it, and a key with spaces around it as a different key, so
     * those are refused; everything else (dots, slashes, umlauts) arrives as written.
     */
    private function validKey(string $key, string $what): bool
    {
        if ($key !== '' && trim($key) === $key && preg_match('/^[^\[\]\x00-\x1F\x7F]+$/u', $key) === 1) return true;
        trigger_error("GridKit: PermissionMatrix $what key " . self::shown($key)
            . ' cannot be a field name (empty, brackets, control characters or spaces around it) — it was left out.', E_USER_WARNING);
        return false;
    }

    /**
     * A form posts two fields for each cell it can change (and PHP reads no more than max_input_vars
     * of them, dropping the rest without a word). Said out loud while it is still a warning.
     */
    private function warnInputVars(int $cells): void
    {
        $limit = (int) ini_get('max_input_vars');
        if ($limit > 0 && $cells * 2 > $limit) {
            trigger_error("GridKit: PermissionMatrix '{$this->id}' can post " . ($cells * 2) . " fields ($cells cells), more than"
                . " max_input_vars ($limit) — PHP would drop the rest silently. Raise max_input_vars, or save with saveUrl().", E_USER_WARNING);
        }
    }

    /** A value quoted for a warning: JSON with every special character escaped, so the log and a page with display_errors get no markup from it. */
    private static function shown(mixed $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
