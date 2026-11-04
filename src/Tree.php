<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Tree — a tree view for page trees, categories and folders (1.116.0).
 *
 *   (new Tree('pages'))
 *       ->label('Pages')
 *       ->nodes($rows, ['id' => 'id', 'parent' => 'parent_id', 'label' => 'title'])
 *       ->select('multi')->name('pages')->selected([3, 7])
 *       ->moveUrl('/pages/move', ['headers' => ['X-CSRF-Token' => $csrf]])
 *       ->lazyUrl('/pages/children')
 *       ->searchable()
 *       ->render();
 *
 * Why it exists: the CMS among GridKit's users keeps its pages in a tree, the shop its categories,
 * the dashboard its folders — and each of them drew a nested <ul> of its own, none of which a
 * keyboard could walk or a screen reader could read as a tree. This is the one place for it.
 *
 * The server writes a plain nested list: links, checkboxes (multi) or radio buttons (single), every
 * node visible. That list works without any script — a link is followed, a ticked box is posted as
 * name[]=id. js/tree.js turns it into a WAI-ARIA tree view: roles, levels and positions, a roving
 * tabindex, the arrow keys, type-ahead, collapsing (remembered per tree in localStorage), the
 * tri-state of a parent, a filter, children loaded on demand and moving by drag and drop or by
 * keyboard. It is a file of its own for the reason js/sortierbar.js is: the PawBot dashboard keeps
 * its own copy of gridkit.js that no sync ever overwrites, so a block in gridkit.js would never
 * reach it.
 *
 * Every misuse the class can see (a duplicate id, a parent that does not exist, a cycle, an option
 * it does not know) is an E_USER_WARNING with a fallback that still renders — the tree a person
 * sees is never empty because one row was wrong.
 */
class Tree
{
    private const SELECT_MODES = ['none', 'single', 'multi'];

    /** What a node may carry besides its id, label and parent. */
    private const NODE_OPTS = ['icon', 'badge', 'count', 'href', 'muted', 'expanded', 'children',
        'actions', 'disabled', 'draggable'];

    /** The colours of a badge: GridKit's label colours. */
    private const BADGE_COLORS = ['gray', 'green', 'orange', 'red', 'blue'];

    private string $id;
    /**
     * The nodes in the order they were added: id => [id, label, parent, opts, row].
     * @var array<string, array{id:string,label:string,parent:?string,opts:array<string,mixed>,row:array<mixed>}>
     */
    private array $nodes = [];
    private string $select = 'none';
    private string $name = '';
    /** @var list<string> */
    private array $selected = [];
    /** @var list<string> */
    private array $expandTo = [];
    private string $lazyUrl = '';
    private string $moveUrl = '';
    /** @var array<string,string> */
    private array $moveHeaders = [];
    private bool $movable = false;
    private bool $searchable = false;
    private bool $toolbar = true;
    private bool $remember = true;
    private string $label = '';
    private ?\Closure $actions = null;

    /**
     * Worked out once per render: the children of each node (and of '' for the top level), in
     * order, with cycles and orphans already resolved.
     * @var array<string, list<string>>
     */
    private array $kids = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    // ── Building the tree ───────────────────────────────────────────────────

    /**
     * Rows of a flat list (each names its parent) or of nested arrays (each carries its children).
     *
     * $map names the field of each part of a node: 'id', 'parent', 'label' and 'children' (the
     * nested list, or the word 'lazy'). Every node option — icon, badge, count, href, muted,
     * expanded, actions, disabled, draggable — is read from the row under its own name, or under
     * the name the map gives it ('muted' => 'hidden'). A parent of null, '' or 0 is the top level
     * (0 only while no node has the id 0: a database's "no parent").
     *
     * @param iterable<array<mixed>> $rows
     * @param array<string,string> $map
     */
    public function nodes(iterable $rows, array $map = []): static
    {
        $map += ['id' => 'id', 'parent' => 'parent_id', 'label' => 'title', 'children' => 'children'];
        $this->addRows($rows, $map, null, false);
        return $this;
    }

    /**
     * One node. $parent is the id of its parent, null for the top level; the parent may be added
     * later. Options: see NODE_OPTS and GRIDKIT_SKILL.md ("Tree").
     *
     * @param array<string,mixed> $opts
     */
    public function node(string $id, string $label, ?string $parent = null, array $opts = []): static
    {
        $this->addNode($id, $label, $parent, $opts, []);
        return $this;
    }

    /** 'none' (default), 'single' — one node, a radio button without script — or 'multi' — checkboxes, tri-state parents. */
    public function select(string $mode): static
    {
        if (!in_array($mode, self::SELECT_MODES, true)) {
            $this->warn("select('$mode') knows 'none', 'single' and 'multi' — nothing can be selected.");
            $mode = 'none';
        }
        $this->select = $mode;
        return $this;
    }

    /**
     * The nodes selected when the page opens. In 'multi' a parent selects its whole branch, and a
     * parent whose children are all selected is selected itself — the rule the script follows.
     *
     * @param iterable<int|string> $ids
     */
    public function selected(iterable $ids): static
    {
        foreach ($ids as $id) {
            if (is_scalar($id)) $this->selected[] = (string) $id;
        }
        $this->selected = array_values(array_unique($this->selected));
        return $this;
    }

    /** The field name the selection is posted under: name[]=id (multi), name=id (single). Default: the tree's id. */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /** Opens the path to a node (its ancestors) and makes it the node the keyboard starts on. */
    public function expandTo(string $id): static
    {
        $this->expandTo[] = $id;
        return $this;
    }

    /**
     * Where the children of a node with 'children' => 'lazy' come from: GET url?parent=<id>,
     * answered with the <li> of those children — Tree::renderBranch() writes them.
     */
    public function lazyUrl(string $url): static
    {
        $this->lazyUrl = $url;
        return $this;
    }

    /**
     * Nodes can be moved, and every move is posted here as JSON {"id", "parent", "index"} (parent
     * null for the top level, index counted from 0 among the new siblings). Option `headers`: sent
     * with every move, e.g. ['X-CSRF-Token' => $token]. A refusal puts the node back.
     *
     * @param array{headers?:array<string,string>} $opts
     */
    public function moveUrl(string $url, array $opts = []): static
    {
        $this->moveUrl = $url;
        $this->movable = true;
        $headers = $opts['headers'] ?? [];
        if (!is_array($headers)) {
            $this->warn("moveUrl() takes 'headers' as an array of name => value — no header is sent.");
            $headers = [];
        }
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) $this->moveHeaders[$name] = (string) $value;
        }
        return $this;
    }

    /** Nodes can be moved without a moveUrl(): the page hears gk:treemove and decides what a move means. */
    public function movable(bool $on = true): static
    {
        $this->movable = $on;
        return $this;
    }

    /** A filter field above the tree: it hides what does not match, keeps the ancestors of a match and marks the match. */
    public function searchable(bool $on = true): static
    {
        $this->searchable = $on;
        return $this;
    }

    /** The bar above the tree (filter, expand all, collapse all). false leaves it out. */
    public function toolbar(bool $show = true): static
    {
        $this->toolbar = $show;
        return $this;
    }

    /** Whether the browser remembers which nodes were open (localStorage, per tree id). Default on. */
    public function remember(bool $on = true): static
    {
        $this->remember = $on;
        return $this;
    }

    /** The accessible name of the tree ("Pages"). Without it the catalogue's "Tree". */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * The row buttons of every node: fn(array $node): array of ActionGroup items (icon, label,
     * href, onclick, title, color, variant, size, pill, disabled, class, showIf). $node holds id,
     * label, parent, level, children (their number), opts and row (what nodes() was given).
     */
    public function actions(callable $fn): static
    {
        $this->actions = \Closure::fromCallable($fn);
        return $this;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /** Prints the tree. */
    public function render(): void
    {
        echo $this->html();
    }

    /**
     * Prints the <li> of the children of $parent and their descendants — the answer to a lazyUrl()
     * request. Configure the tree as on the page (select, name, actions, movable) so that the
     * children look and post the same.
     */
    public function renderBranch(string $parent): void
    {
        $this->resolve($parent);
        $checked = $this->checkedSet();
        $out = '';
        foreach ($this->kids[$parent] ?? [] as $id) $out .= $this->renderNode($id, 1, $checked, []);
        echo $out;
    }

    private function html(): string
    {
        $this->resolve(null);
        $e = self::escaper();
        $checked = $this->checkedSet();
        $open = $this->openSet();
        $treeKey = self::key($this->id);
        $name = $this->label !== '' ? $this->label : Lang::t('tree.label');

        $attrs = ' class="gk-tree" id="gk-tree-' . $e($treeKey) . '" data-gk-tree="' . $e($this->id) . '"'
            . ' data-gk-tree-select="' . $this->select . '" data-gk-tree-label="' . $e($name) . '"';
        if ($this->lazyUrl !== '') $attrs .= ' data-gk-tree-lazy-url="' . $e($this->lazyUrl) . '"';
        if ($this->movable) $attrs .= ' data-gk-tree-movable';
        if ($this->moveUrl !== '') $attrs .= ' data-gk-tree-move-url="' . $e($this->moveUrl) . '"';
        if ($this->moveHeaders) {
            $attrs .= " data-gk-tree-headers='" . $e((string) json_encode($this->moveHeaders, JSON_UNESCAPED_UNICODE)) . "'";
        }
        if (!$this->remember) $attrs .= ' data-gk-tree-remember="off"';

        $html = '<div' . $attrs . '>';
        $hasParents = false;
        foreach ($this->kids as $parent => $list) {
            if ($parent !== '' && $list !== []) { $hasParents = true; break; }
        }
        foreach ($this->nodes as $n) {
            if (($n['opts']['children'] ?? null) === 'lazy') { $hasParents = true; break; }
        }
        if ($this->toolbar && ($this->searchable || $hasParents)) {
            $html .= $this->renderToolbar($name, $treeKey, $hasParents);
        }

        if (($this->kids[''] ?? []) === []) {
            return $html . '<p class="gk-tree-empty">' . $e(Lang::t('tree.empty')) . '</p>' . $this->pendingInputs($checked) . '</div>';
        }
        $html .= '<ul class="gk-tree-list" id="gk-tree-' . $e($treeKey) . '-list">';
        foreach ($this->kids[''] as $id) $html .= $this->renderNode($id, 1, $checked, $open);
        return $html . '</ul>' . $this->pendingInputs($checked) . '</div>';
    }

    /** The filter field and the two buttons. Hidden without script by the stylesheet: they do nothing then. */
    private function renderToolbar(string $name, string $treeKey, bool $hasParents): string
    {
        $e = self::escaper();
        $html = '<div class="gk-tree-toolbar">';
        if ($this->searchable) {
            $placeholder = Lang::t('tree.search');
            $html .= '<input type="search" class="gk-search gk-tree-search" autocomplete="off"'
                . ' placeholder="' . $e($placeholder) . '" aria-label="' . $e(Lang::t('tree.search_aria', ['label' => $name])) . '"'
                . ' aria-controls="gk-tree-' . $e($treeKey) . '-list">';
        }
        if ($hasParents) {
            foreach (['expand' => 'unfold_more', 'collapse' => 'unfold_less'] as $what => $icon) {
                $html .= Button::render(Lang::t('tree.' . $what . '_all'), [
                    'variant' => 'text', 'color' => 'neutral', 'size' => 'sm', 'icon' => $icon,
                    'data' => ['gk-tree-all' => $what],
                ]);
            }
        }
        return $html . '</div>';
    }

    /**
     * One node and its branch. $level counts from 1. Everything the script needs to know about a node
     * is on its <li> (data-gk-tree-*), so a branch written later by renderBranch() is read the same way.
     *
     * @param array<string,bool> $checked
     * @param array<string,bool> $open
     */
    private function renderNode(string $id, int $level, array $checked, array $open): string
    {
        $e = self::escaper();
        $n = $this->nodes[$id];
        $o = $n['opts'];
        $kids = $this->kids[$id] ?? [];
        $lazy = ($o['children'] ?? null) === 'lazy' && $kids === [];
        $disabled = !empty($o['disabled']);
        $base = 'gk-tree-' . self::key($this->id) . '-' . self::key($id);
        // A lazy node may start open: the script then loads its children at once.
        $isOpen = ($kids !== [] || $lazy) && (isset($open[$id]) || !empty($o['expanded']));

        $cls = 'gk-tree-node';
        if ($isOpen) $cls .= ' gk-tree-open';
        if (!empty($o['muted'])) $cls .= ' gk-tree-muted';
        if ($disabled) $cls .= ' gk-tree-disabled';
        $li = '<li class="' . $cls . '" data-gk-tree-id="' . $e($id) . '"';
        if ($lazy) $li .= ' data-gk-tree-lazy';
        if ($disabled) $li .= ' data-gk-tree-disabled';
        if (($o['draggable'] ?? true) === false) $li .= ' data-gk-tree-nodrag';
        if (in_array($id, $this->expandTo, true)) $li .= ' data-gk-tree-target';
        $li .= '>';

        // The row: toggle, box, symbol, name, the small things after the name, the buttons.
        $row = '<div class="gk-tree-row" data-gk-tree-level="' . $level . '"><span class="gk-tree-toggle" aria-hidden="true"></span>';
        $hasHref = isset($o['href']) && !$disabled;
        $inputId = $base . '-c';
        if ($this->select !== 'none') {
            $isChecked = isset($checked[$id]);
            $row .= '<input type="' . ($this->select === 'multi' ? 'checkbox' : 'radio') . '"'
                . ' class="' . ($this->select === 'multi' ? 'gk-tree-check' : 'gk-tree-radio') . '"'
                . ' id="' . $e($inputId) . '" name="' . $e($this->fieldName()) . '" value="' . $e($id) . '"'
                . ($isChecked ? ' checked' : '') . ($disabled ? ' disabled' : '')
                . ($hasHref ? ' aria-labelledby="' . $e($base . '-l') . '"' : '') . '>';
        }
        if (isset($o['icon'])) {
            $row .= '<span class="material-icons gk-tree-icon" aria-hidden="true">' . $e($o['icon']) . '</span>';
        }
        $labelId = $e($base . '-l');
        $text = $e($n['label']);
        if ($hasHref) {
            $row .= '<a class="gk-tree-label" id="' . $labelId . '" href="' . $e($o['href']) . '">' . $text . '</a>';
        } elseif ($this->select !== 'none' && !$disabled) {
            $row .= '<label class="gk-tree-label" id="' . $labelId . '" for="' . $e($inputId) . '">' . $text . '</label>';
        } else {
            $row .= '<span class="gk-tree-label" id="' . $labelId . '">' . $text . '</span>';
        }
        // Muted is a shape (italic) and words, never a colour alone: the words are read, not shown.
        if (!empty($o['muted'])) {
            $note = is_string($o['muted']) ? $o['muted'] : Lang::t('tree.muted');
            $row .= '<span class="gk-sr-only gk-tree-note" id="' . $e($base . '-n') . '">(' . $e($note) . ')</span>';
        }
        if (isset($o['badge'])) {
            $row .= '<span class="gk-label gk-label-' . $o['badge']['color'] . ' gk-tree-badge" id="' . $e($base . '-b') . '">'
                . $e($o['badge']['text']) . '</span>';
        }
        if (isset($o['count'])) {
            $row .= '<span class="gk-tree-count" id="' . $e($base . '-k') . '">' . $e($o['count']) . '</span>';
        }
        $row .= $this->renderActions($n, $level, count($kids), $base . '-l');
        $row .= '</div>';

        $group = '';
        if ($kids !== [] || $lazy) {
            $group = '<ul class="gk-tree-group" id="' . $e($base . '-g') . '">';
            foreach ($kids as $kid) $group .= $this->renderNode($kid, $level + 1, $checked, $open);
            $group .= '</ul>';
        }
        return $li . $row . $group . '</li>';
    }

    /**
     * The row buttons: the node's own 'actions', then what actions() returns for it. Drawn with
     * Button::render() the way ActionGroup draws them — the same defaults, the same classes — and
     * described by the node's name, so "Edit" is heard as "Edit, Team" and not as one of forty edits.
     *
     * @param array{id:string,label:string,parent:?string,opts:array<string,mixed>,row:array<mixed>} $n
     */
    private function renderActions(array $n, int $level, int $children, string $labelId): string
    {
        $items = $n['opts']['actions'] ?? [];
        if ($this->actions !== null) {
            $more = ($this->actions)([
                'id' => $n['id'], 'label' => $n['label'], 'parent' => $n['parent'], 'level' => $level,
                'children' => $children, 'opts' => $n['opts'], 'row' => $n['row'],
            ]);
            if (is_array($more)) {
                $items = array_merge($items, $more);
            } else {
                $this->warn("actions() returned " . get_debug_type($more) . " for node '{$n['id']}' — it returns an array of actions. The node has none of them.");
            }
        }
        $html = '';
        foreach ($items as $a) {
            if (!is_array($a) || (array_key_exists('showIf', $a) && !$a['showIf'])) continue;
            $label = $a['label'] ?? '';
            $label = is_scalar($label) ? (string) $label : '';
            if ($label === '' && empty($a['icon'])) continue;
            $opts = [
                'variant' => $a['variant'] ?? ($label === '' ? 'text' : 'outlined'),
                'color'   => $a['color'] ?? 'neutral',
                'size'    => $a['size'] ?? 'sm',
                'attrs'   => ['aria-describedby' => $labelId],
            ];
            foreach (['icon', 'href', 'onclick', 'title'] as $k) {
                if (!empty($a[$k]) && is_scalar($a[$k])) $opts[$k] = (string) $a[$k];
            }
            if (!empty($a['disabled'])) $opts['disabled'] = true;
            $class = trim((!empty($a['pill']) ? 'gk-btn-pill ' : '') . (is_string($a['class'] ?? null) ? $a['class'] : ''));
            if ($class !== '') $opts['class'] = $class;
            $html .= Button::render($label, $opts);
        }
        return $html === '' ? '' : '<div class="gk-action-group gk-tree-actions">' . $html . '</div>';
    }

    /**
     * A selected id that is not in the tree: in a tree with lazy branches it may be in one that has not
     * been loaded, and must still be posted — as a hidden field the script drops once its node arrives.
     * In a tree without lazy branches it is a mistake.
     *
     * @param array<string,bool> $checked
     */
    private function pendingInputs(array $checked): string
    {
        if ($this->select === 'none') return '';
        $e = self::escaper();
        $hasLazy = false;
        foreach ($this->nodes as $n) {
            if (($n['opts']['children'] ?? null) === 'lazy') { $hasLazy = true; break; }
        }
        $html = '';
        foreach ($this->selected as $id) {
            if (isset($this->nodes[$id])) continue;
            if (!$hasLazy) {
                $this->warn("selected() names '$id', which the tree does not have — it was left out.");
                continue;
            }
            $html .= '<input type="hidden" name="' . $e($this->fieldName()) . '" value="' . $e($id) . '" data-gk-tree-pending>';
            if ($this->select === 'single') break;
        }
        return $html;
    }

    // ── Working the tree out ────────────────────────────────────────────────

    /**
     * The children of every node, in the order the nodes were added. A parent the tree does not have
     * makes an orphan a top-level node; a node that is its own ancestor is cut loose and becomes one
     * too. With $branch, the children of that id are the top level (renderBranch()).
     */
    private function resolve(?string $branch): void
    {
        $this->kids = ['' => []];
        if ($branch !== null) $this->kids[$branch] = [];
        foreach ($this->nodes as $n) {
            $id = $n['id'];
            $p = $n['parent'];
            if ($p === null || $p === '' || ($p === '0' && !isset($this->nodes['0']))) {
                $this->kids[''][] = $id;
            } elseif ($p === $id) {
                $this->warn("node '$id' is its own parent — it is shown at the top level.");
                $this->kids[''][] = $id;
            } elseif (isset($this->nodes[$p]) || $p === $branch) {
                $this->kids[$p][] = $id;
            } else {
                $this->warn("node '$id' names the parent '$p', which the tree does not have — it is shown at the top level.");
                $this->kids[''][] = $id;
            }
        }

        // What cannot be reached from the top (or, for a branch, from the parent asked for) is in a
        // cycle or under one: the first node of each cycle, in the order they were added, is lifted
        // to the top level.
        $seen = [];
        $walk = function (string $id) use (&$walk, &$seen): void {
            $seen[$id] = true;
            foreach ($this->kids[$id] ?? [] as $kid) {
                if (!isset($seen[$kid])) $walk($kid);
            }
        };
        foreach ($this->kids[''] as $id) $walk($id);
        if ($branch !== null) foreach ($this->kids[$branch] as $id) $walk($id);
        // Ids are read from the records, never from the keys: PHP turns the key '7' into the int 7.
        foreach ($this->nodes as $n) {
            $id = $n['id'];
            if (isset($seen[$id])) continue;
            // Follow the parents until one repeats: that one is on the cycle.
            $path = [];
            $at = $id;
            while (!isset($path[$at]) && isset($this->nodes[$at])) {
                $path[$at] = true;
                $at = (string) $this->nodes[$at]['parent'];
            }
            if (!isset($path[$at])) {
                // The chain ended at a node that is not there: no cycle, only a node the steps above
                // did not place. It is not lost — it goes to the top level, like an orphan.
                $this->warn("node '$id' could not be placed — it is shown at the top level.");
                $p = (string) $n['parent'];
                if (isset($this->kids[$p])) $this->kids[$p] = array_values(array_diff($this->kids[$p], [$id]));
                $this->kids[''][] = $id;
                $walk($id);
                continue;
            }
            $ring = [];
            $cur = $at;
            do {
                $ring[] = $cur;
                $cur = (string) $this->nodes[$cur]['parent'];
            } while ($cur !== $at);
            $first = $at;
            foreach ($this->nodes as $m) {
                if (in_array($m['id'], $ring, true)) { $first = $m['id']; break; }
            }
            $this->warn("nodes '" . implode("', '", $ring) . "' are each other's ancestors — '$first' is shown at the top level.");
            $p = (string) $this->nodes[$first]['parent'];
            $this->kids[$p] = array_values(array_diff($this->kids[$p] ?? [], [$first]));
            $this->kids[''][] = $first;
            $walk($first);
        }

        // A branch is a slice of the tree: the node to open is elsewhere, and that is no mistake.
        foreach ($branch === null ? $this->expandTo : [] as $target) {
            if (!isset($this->nodes[$target])) {
                $this->warn("expandTo('$target'): the tree has no such node — nothing was opened.");
            }
        }
    }

    /** The ancestors of the expandTo() nodes: they are open whatever their own 'expanded' says. @return array<string,bool> */
    private function openSet(): array
    {
        $parentOf = [];
        foreach ($this->kids as $p => $list) {
            foreach ($list as $kid) $parentOf[$kid] = (string) $p;
        }
        $open = [];
        foreach ($this->expandTo as $target) {
            $at = $parentOf[$target] ?? '';
            $guard = 0;
            while ($at !== '' && isset($this->nodes[$at]) && $guard++ < 10000) {
                $open[$at] = true;
                $at = $parentOf[$at] ?? '';
            }
        }
        return $open;
    }

    /**
     * The nodes that are ticked when the page opens. 'single': the first selected id the tree has.
     * 'multi': a selected node ticks its branch (but no disabled node in it), then a parent whose
     * enabled children are all ticked is ticked itself — the same two steps js/tree.js takes, so
     * the page posts the same before and after the script has run.
     *
     * @return array<string,bool>
     */
    private function checkedSet(): array
    {
        if ($this->select === 'none') return [];
        $present = array_values(array_filter($this->selected, fn (string $id): bool => isset($this->nodes[$id])));
        if ($this->select === 'single') {
            if (count($present) > 1) {
                $this->warn("select('single') with " . count($present) . " selected nodes — '{$present[0]}' is the one.");
            }
            return $present ? [$present[0] => true] : [];
        }
        $checked = [];
        $down = function (string $id) use (&$down, &$checked): void {
            $checked[$id] = true;
            foreach ($this->kids[$id] ?? [] as $kid) {
                if (empty($this->nodes[$kid]['opts']['disabled'])) $down($kid);
            }
        };
        foreach ($present as $id) $down($id);
        $up = function (string $id) use (&$up, &$checked): void {
            $enabled = 0;
            $ticked = 0;
            foreach ($this->kids[$id] ?? [] as $kid) {
                $up($kid);
                if (!empty($this->nodes[$kid]['opts']['disabled'])) continue;
                $enabled++;
                if (isset($checked[$kid])) $ticked++;
            }
            if ($enabled > 0 && empty($this->nodes[$id]['opts']['disabled'])) {
                if ($ticked === $enabled) $checked[$id] = true;
                else unset($checked[$id]);
            }
        };
        foreach ($this->kids[''] ?? [] as $id) $up($id);
        foreach ($this->kids as $p => $list) {
            if ($p !== '' && !isset($this->nodes[$p])) foreach ($list as $id) $up($id); // a branch's top
        }
        return $checked;
    }

    private function fieldName(): string
    {
        $name = $this->name !== '' ? $this->name : $this->id;
        return $this->select === 'multi' ? $name . '[]' : $name;
    }

    // ── Taking nodes in ─────────────────────────────────────────────────────

    /**
     * @param iterable<mixed> $rows
     * @param array<string,string> $map
     */
    private function addRows(iterable $rows, array $map, ?string $parent, bool $nested): void
    {
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("nodes() row $i is " . get_debug_type($row) . ', not an array — it was left out.');
                continue;
            }
            $rawId = $row[$map['id']] ?? null;
            if (!is_scalar($rawId) || (string) $rawId === '') {
                $this->warn("nodes() row $i has no '{$map['id']}' — it was left out.");
                continue;
            }
            $id = (string) $rawId;
            $rawLabel = $row[$map['label']] ?? null;
            if (!is_scalar($rawLabel) || (string) $rawLabel === '') {
                $this->warn("node '$id' has no '{$map['label']}' — its id is shown instead.");
                $rawLabel = $id;
            }
            $p = $parent;
            if (!$nested) {
                $rawParent = $row[$map['parent']] ?? null;
                $p = is_scalar($rawParent) && (string) $rawParent !== '' ? (string) $rawParent : null;
            }
            $opts = [];
            foreach (self::NODE_OPTS as $opt) {
                if ($opt === 'children') continue;
                $field = $map[$opt] ?? $opt;
                if (array_key_exists($field, $row)) $opts[$opt] = $row[$field];
            }
            $children = $row[$map['children']] ?? null;
            if ($children === 'lazy') $opts['children'] = 'lazy';
            if (!$this->addNode($id, (string) $rawLabel, $p, $opts, $row)) continue;
            if (is_array($children)) $this->addRows($children, $map, $id, true);
        }
    }

    /**
     * Checks and stores one node. An option of the wrong type is dropped with a warning; the node
     * still shows. Returns false when the node was refused (its id is taken).
     *
     * @param array<string,mixed> $opts
     * @param array<mixed> $row
     */
    private function addNode(string $id, string $label, ?string $parent, array $opts, array $row): bool
    {
        if ($id === '') {
            $this->warn("a node with an empty id was left out ('$label').");
            return false;
        }
        if (isset($this->nodes[$id])) {
            $this->warn("the id '$id' is taken — the second node ('$label') was left out.");
            return false;
        }
        $clean = [];
        $children = null;
        foreach ($opts as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, self::NODE_OPTS, true)) {
                $this->warn("node '$id' has the option '$key', which a tree node does not know — it was ignored.");
                continue;
            }
            switch ($key) {
                case 'icon':
                    if (is_string($value) && $value !== '') $clean['icon'] = $value;
                    elseif ($value !== null && $value !== '') $this->badOpt($id, $key, $value, 'a Material icon name');
                    break;
                case 'href':
                    if (!is_string($value) || $value === '') {
                        if ($value !== null && $value !== '') $this->badOpt($id, $key, $value, 'a URL');
                    } elseif (preg_match('/^\s*javascript:/i', (string) preg_replace('/[\x00-\x20]+/', '', $value))) {
                        // A page tree is fed from a database a person writes into: a javascript: URL there
                        // would run in every editor's browser on a click. The label stays plain text.
                        $this->warn("node '$id' has a javascript: href — it was left out, the label is plain text.");
                    } else {
                        $clean['href'] = $value;
                    }
                    break;
                case 'count':
                    if (is_int($value) || is_float($value) || (is_string($value) && $value !== '')) $clean['count'] = (string) $value;
                    elseif ($value !== null && $value !== '') $this->badOpt($id, $key, $value, 'a number');
                    break;
                case 'badge':
                    if (is_scalar($value) && !is_bool($value) && (string) $value !== '') {
                        $clean['badge'] = ['text' => (string) $value, 'color' => 'gray'];
                    } elseif (is_array($value) && is_scalar($value['text'] ?? null) && (string) $value['text'] !== '') {
                        $color = $value['color'] ?? 'gray';
                        if (!in_array($color, self::BADGE_COLORS, true)) {
                            $this->badOpt($id, 'badge color', $color, implode(', ', self::BADGE_COLORS));
                            $color = 'gray';
                        }
                        $clean['badge'] = ['text' => (string) $value['text'], 'color' => $color];
                    } elseif ($value !== null && $value !== '') {
                        $this->badOpt($id, $key, $value, "text, or ['text' => …, 'color' => …]");
                    }
                    break;
                case 'muted':
                    if (is_string($value) && $value !== '' && self::flag($value) === null) $clean['muted'] = $value;
                    else $this->setFlag($clean, $id, $key, $value);
                    break;
                case 'expanded':
                case 'disabled':
                case 'draggable':
                    $this->setFlag($clean, $id, $key, $value);
                    break;
                case 'children':
                    $children = $value;
                    if ($value === 'lazy') $clean['children'] = 'lazy';
                    elseif (!is_array($value) && $value !== null) $this->badOpt($id, $key, $value, "'lazy' or an array of nodes");
                    break;
                case 'actions':
                    if (is_array($value)) $clean['actions'] = array_values($value);
                    elseif ($value !== null) $this->badOpt($id, $key, $value, 'an array of actions');
                    break;
            }
        }
        $this->nodes[$id] = ['id' => $id, 'label' => $label, 'parent' => $parent, 'opts' => $clean, 'row' => $row];
        if (is_array($children)) {
            $this->addRows($children, ['id' => 'id', 'parent' => 'parent_id', 'label' => 'title', 'children' => 'children'], $id, true);
        }
        return true;
    }

    /**
     * A yes/no option. From a database it arrives as 1, '0' or 'yes'; anything else is dropped with
     * a warning ('expanded' => 'no' is true to PHP and would open the node it meant to close).
     *
     * @param array<string,mixed> $clean
     */
    private function setFlag(array &$clean, string $id, string $key, mixed $value): void
    {
        if ($value === null) return;
        $flag = self::flag($value);
        if ($flag === null) {
            $this->badOpt($id, $key, $value, 'true or false');
            return;
        }
        if ($flag || $key === 'draggable') $clean[$key] = $flag;
    }

    private static function flag(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if ($value === 0 || $value === 1) return (bool) $value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) return false;
        }
        return null;
    }

    private function badOpt(string $id, string $key, mixed $value, string $wants): void
    {
        $shown = is_scalar($value) ? var_export($value, true) : get_debug_type($value);
        $this->warn("node '$id' has '$key' => $shown — it takes $wants. It was ignored.");
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: tree '{$this->id}': $message", E_USER_WARNING);
    }

    /** A node id as a piece of an HTML id: as it is when it is safe, else a short hash of it. */
    private static function key(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) ? $id : 'h' . substr(md5($id), 0, 12);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
