<?php
use GridKit\FilterBuilder;
use GridKit\Table;

// The invoices of the demo, in memory: what a page would SELECT. The filter below is applied to them
// with FilterBuilder::matches(), and the same tree is shown as the SQL toSql() writes for it.
$fbCustomers = ['Ecklund & Partner', 'Müller Logistik', 'Nordlicht Media', 'Bäckerei Groß', 'Café Crème', 'Atelier Sonnfeld'];
$fbStatuses = ['open' => 'Open', 'paid' => 'Paid', 'overdue' => 'Overdue', 'draft' => 'Draft'];
$fbInvoices = [];
foreach (range(1, 24) as $i) {
    $status = array_keys($fbStatuses)[($i * 7) % 4];
    $fbInvoices[] = [
        'number'   => sprintf('INV-2026-%04d', 180 + $i),
        'customer' => $fbCustomers[($i * 5) % 6],
        'status'   => $status,
        'amount'   => round(80 + (($i * 337) % 2400) + ($i % 3) * 0.5, 2),
        'due'      => sprintf('2026-%02d-%02d', 8 + ($i % 5), 1 + ($i * 3) % 28),
        'tags'     => array_values(array_filter([$i % 3 === 0 ? 'vip' : null, $i % 4 === 1 ? 'eu' : null, $i % 5 === 2 ? 'reverse' : null])),
        'reminded' => $i % 4 === 3,
    ];
}

$demoFilter = static function () use ($fbStatuses): FilterBuilder {
    return (new FilterBuilder('f'))
        ->field('status', 'Status', ['type' => 'select', 'options' => $fbStatuses])
        ->field('amount', 'Amount', ['type' => 'money'])
        ->field('customer', 'Customer', ['placeholder' => 'Name'])
        ->field('due', 'Due', ['type' => 'date'])
        ->field('tags', 'Tags', ['type' => 'multiselect', 'options' => ['vip' => 'VIP', 'eu' => 'EU customer', 'reverse' => 'Reverse charge']])
        ->field('reminded', 'Reminded', ['type' => 'bool'])
        ->value(['join' => 'and', 'rules' => [
            ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'overdue']],
            ['field' => 'amount', 'op' => 'gt', 'value' => 500],
        ]])
        ->views([
            ['label' => 'My open invoices', 'state' => '["and",["status","is","open"]]'],
            ['label' => 'Overdue or reminded', 'state' => '["or",["status","is","overdue"],["reminded","true"]]'],
            ['label' => 'Large EU invoices', 'state' => '["and",["amount","gt",1000],["tags","has_any",["eu"]]]'],
        ])
        ->localViews()
        ->action('#filterbuilder')
        ->preserve(['lang']);
};
$fb = $demoFilter();
$fbTree = $fb->tree();
$fbRows = array_values(array_filter($fbInvoices, static fn (array $r): bool => FilterBuilder::matches($fbTree, $r, $fb)));
$fbSql = FilterBuilder::toSql($fbTree, $fb, [
    'amount' => 'i.total',
    'tags'   => static fn (array $rule): array => [
        'sql' => ($rule['op'] === 'has_none' ? 'NOT ' : '') . 'EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = i.id AND t.tag IN ('
            . implode(', ', array_fill(0, count($rule['value']), '?')) . ')'
            . ($rule['op'] === 'has_all' ? ' GROUP BY t.invoice_id HAVING COUNT(DISTINCT t.tag) = ' . count($rule['value']) : '') . ')',
        'params' => $rule['value'],
    ],
]);
?>
<!-- ===== FILTER BUILDER (1.123.0) ===== -->
<div class="demo-section" data-section="filterbuilder">
    <style>.demo-section[data-section="filterbuilder"] .demo-intro code { overflow-wrap: anywhere; }</style>
    <h2>Filter builder <?= since('filter-builder') ?></h2>
    <p class="demo-intro">Conditions a person combines freely: <em>Status is any of Open, Overdue</em> AND <em>Amount &gt; 500</em>,
        or a group of OR conditions inside. <code>GridKit\FilterBuilder</code> writes a plain form that works without
        script — nested fieldsets, submit buttons that add, duplicate and remove; <code>js/filter-builder.js</code> makes the
        same form instant and sends the state as compact JSON in one parameter (<code>?f=["and",…]</code>). The page
        reads it with <code>FilterBuilder::fromRequest()</code> or <code>-&gt;tree()</code>, checked against the fields, and queries
        with <code>FilterBuilder::toSql()</code> (placeholders only) or filters rows in memory with <code>FilterBuilder::matches()</code>.</p>

    <h3 class="demo-h3" id="filterbuilder-try">Invoices, filtered</h3>
    <div class="demo-card">
        <?php $fb->render(); ?>
    </div>

    <h3 class="demo-h3" id="filterbuilder-result"><?= count($fbRows) ?> of <?= count($fbInvoices) ?> invoices</h3>
    <div class="demo-card">
        <?php
        (new Table('demo-fb-invoices'))
            ->setData(array_map(static fn (array $r): array => $r + ['tag_list' => implode(', ', $r['tags']), 'reminded_text' => $r['reminded'] ? 'yes' : '—'], $fbRows))
            ->column('number', 'Invoice')
            ->column('customer', 'Customer')
            ->column('status', 'Status', ['format' => 'label'])
            ->column('amount', 'Amount', ['format' => 'currency'])
            ->column('due', 'Due', ['format' => 'date'])
            ->column('tag_list', 'Tags')
            ->column('reminded_text', 'Reminded')
            ->stickyColumn()          // seven columns: a box of its own, not a page that scrolls sideways
            ->render();
        ?>
    </div>

    <h3 class="demo-h3" id="filterbuilder-sql">The same filter as SQL</h3>
    <p class="demo-intro"><code>FilterBuilder::toSql($tree, $fields, $columnMap)</code>: column names only from the page's map (or the
        field key), every value a placeholder. Text is compared with <code>LIKE ? ESCAPE '!'</code>, a date column may hold times,
        a multiselect is the page's own callable — here a tag table.</p>
    <div class="demo-pair">
    <div class="demo-code"><pre id="demo-fb-sql"><?= htmlspecialchars("WHERE " . $fbSql['sql'] . "\n\nparams: " . json_encode($fbSql['params'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre></div>
    <div class="demo-code"><pre><?= htmlspecialchars(<<<'PHP'
$fb = (new FilterBuilder('f'))
    ->field('status', 'Status', ['type' => 'select', 'options' => $statuses])
    ->field('amount', 'Amount', ['type' => 'money'])
    ->field('customer', 'Customer')
    ->field('due', 'Due', ['type' => 'date'])
    ->field('tags', 'Tags', ['type' => 'multiselect', 'options' => $tags])
    ->field('reminded', 'Reminded', ['type' => 'bool'])
    ->views($savedViews)          // the page stores them
    ->localViews();               // and this browser too

$tree = $fb->tree();              // checked: unknown fields, operators, values left out
['sql' => $where, 'params' => $params] = FilterBuilder::toSql($tree, $fb, ['amount' => 'i.total', 'tags' => $tagCallable]);
// or, in memory:
$rows = array_filter($rows, fn ($r) => FilterBuilder::matches($tree, $r, $fb));
$fb->render();
PHP, ENT_QUOTES, 'UTF-8') ?></pre></div>
    </div>

    <h3 class="demo-h3" id="filterbuilder-mistakes">A mistake, a group, the limit</h3>
    <p class="demo-intro">What comes back from Apply with a value missing: the condition stays in the form, is marked and
        said in words — not by the red alone — and is not applied. A group joins its conditions the other way (OR inside
        AND); <code>maxRows(3)</code> is reached, so “Condition”, “Group” and duplicate are disabled and the limit is said.</p>
    <div class="demo-card">
        <?php
        (new FilterBuilder('f2'))
            ->field('amount', 'Amount', ['type' => 'money'])
            ->field('status', 'Status', ['type' => 'select', 'options' => $fbStatuses])
            ->field('customer', 'Customer')
            ->maxRows(3)
            ->request(['f2' => $_GET['f2'] ?? '["and",["amount","between",["100",""]],["or",["status","is","open"],["customer","contains","eck"]]]'])
            ->action('#filterbuilder-mistakes')
            ->preserve(['lang'])
            ->render();
        ?>
    </div>

    <h3 class="demo-h3" id="filterbuilder-local">Nothing yet, views kept in this browser</h3>
    <p class="demo-intro">No conditions: every row is shown, and the builder says so. <code>localViews()</code> keeps a saved
        view in <code>localStorage</code> when the page has no <code>saveUrl()</code>; each one comes with a button that removes it.</p>
    <div class="demo-card">
        <?php
        (new FilterBuilder('g'))
            ->field('name', 'Name')
            ->field('active', 'Active', ['type' => 'bool'])
            ->label('People filter')
            ->localViews()
            ->action('#filterbuilder-local')
            ->preserve(['lang'])
            ->render();
        ?>
    </div>

    <h3 class="demo-h3" id="filterbuilder-events">Events and the API</h3>
    <p class="demo-intro">Apply fires <code>gk:filterchange</code> (cancelable — a page can filter live instead of loading),
        Save view <code>gk:filtersave</code>; here the views are kept in this browser. <code>GK.filterBuilder.get('f')</code>,
        <code>set()</code>, <code>reset()</code>, <code>json()</code> and <code>summary()</code> reach it from a script.</p>
    <div class="demo-btn-row">
        <button type="button" class="gk-btn gk-btn-outlined gk-btn-sm" id="demo-fb-set"><span>GK.filterBuilder.set('f', …)</span></button>
        <button type="button" class="gk-btn gk-btn-outlined gk-btn-sm" id="demo-fb-get"><span>GK.filterBuilder.json('f')</span></button>
    </div>
    <pre class="demo-log" id="demo-fb-log" aria-label="Event log">Events appear here.</pre>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var log = document.getElementById('demo-fb-log');
        var say = function (t) { log.textContent = t; };
        document.addEventListener('gk:filterchange', function (e) { say('gk:filterchange ' + e.detail.json + '\n' + e.detail.summary); });
        document.addEventListener('gk:filtersave', function (e) { say('gk:filtersave "' + e.detail.label + '" ' + e.detail.json); });
        document.getElementById('demo-fb-set').addEventListener('click', function () {
            GK.filterBuilder.set('f', '["and",["customer","contains","eck"],["or",["amount","between",[100,900]],["due","lt","2026-10-01"]]]');
            say('set → ' + GK.filterBuilder.summary('f'));
        });
        document.getElementById('demo-fb-get').addEventListener('click', function () { say(GK.filterBuilder.json('f')); });
    });
    </script>
</div>
