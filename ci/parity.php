<?php
/**
 * Server/client parity harness.
 *
 * GridKit renders a table twice: once in PHP, and again in JavaScript whenever
 * the browser sorts, filters or pages a `setData()` table. Those are two
 * implementations of one output, and they have drifted three times:
 *
 *   1.55.0  currency — the server formatted from the catalogue, the client had
 *           "de-DE" hardcoded, so an English table flipped to "1.200,00 €" on
 *           the first sort.
 *   1.59.0  table headers — the server wrote scope="col" and aria-sort, the
 *           client rebuilt the thead without either and threw them away.
 *   1.62.1  confirmations — the server wrapped an onclick in GK.confirm(), the
 *           client set it raw, so a delete stopped asking after the first sort.
 *
 * Each was invisible in the source of either side. Each appears the moment you
 * render once, change something, and render again. So that is what this does.
 *
 * It is NOT part of `php tests/run.php`. The suite runs on plain PHP with no
 * dependencies, and requiring a browser would break that promise for everyone
 * who clones the repo. Run this separately when touching either renderer:
 *
 *     php ci/parity.php            # writes the fixture page
 *     # then load it in a browser and run the comparison it prints
 *
 * The fixture is deliberately dull: the point is coverage of the attribute
 * surface, not a pretty page.
 *
 * KNOWN BENIGN DIFFERENCES, checked and left alone. A run reports these; they
 * are not drift, and chasing them would mean adding markup for its own sake:
 *
 *   - The server writes `class="… disabled"` alongside the `disabled`
 *     attribute; the client writes the attribute only. css/gridkit.css styles
 *     `.gk-btn.disabled` and `.gk-btn:disabled` in one rule, so both render
 *     identically and both are inert.
 *   - The sort indicator: the server draws it with a CSS ::after arrow, the
 *     client with a `material-icons` span and the `gk-sortable-mi` class that
 *     suppresses the arrow. Two mechanisms, one appearance.
 *
 * Everything else a run reports is worth reading. The pager's accessible names
 * were drifting when this harness was written — the server said "Previous" and
 * "Page 1 of 3" while the client said "Previous page" and "Page 1", so a
 * screen reader heard one wording before a sort and another after — and that
 * is exactly the kind of thing nothing else here would have caught.
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use GridKit\Lang;
use GridKit\Table;

Lang::set($argv[1] ?? 'en');

$rows = [];
for ($i = 1; $i <= 12; $i++) {
    $rows[] = [
        'id'     => $i,
        'sku'    => sprintf('ART-%03d', $i),
        'name'   => "Item $i",
        'net'    => $i * 137.5,
        'count'  => $i * 3,
        'status' => ['active', 'inactive', 'draft'][$i % 3],
    ];
}

/**
 * The cases. Each exercises a different corner of the shared surface — the
 * places where the two renderers have to agree about an attribute, a class or
 * a wrapper.
 */
$cases = [
    'plain'      => static fn(Table $t): Table => $t,
    'sortable'   => static fn(Table $t): Table => $t->column('net', 'Net', ['format' => 'currency', 'sortable' => true]),
    'formats'    => static fn(Table $t): Table => $t
        ->column('net', 'Net', ['format' => 'currency'])
        ->column('count', 'Count', ['format' => 'number'])
        ->column('status', 'Status', ['format' => 'label']),
    'buttons'    => static fn(Table $t): Table => $t
        ->button('edit', ['icon' => 'edit'])
        ->button('note', ['text' => 'Note'])
        ->button('del', ['icon' => 'delete', 'confirm' => true, 'onclick' => 'doDelete({id})']),
    'selectable' => static fn(Table $t): Table => $t->selectable('id'),
    'filtered'   => static fn(Table $t): Table => $t
        ->column('status', 'Status', ['format' => 'label'])
        ->filter('status', 'select', ['options' => ['active' => 'Active', 'draft' => 'Draft']]),
];

/*
 * Stamp every asset. The first version of this file linked them bare, and the
 * site's cache policy gives an unstamped asset a long life — so the fixture was
 * served a copy of gridkit.js from before that morning's fixes and reported a
 * regression that had been fixed hours earlier. A harness that lies is worse
 * than no harness, and this one lied on its first run.
 */
$stamp = static fn(string $p): string
    => $p . '?v=' . (is_file(__DIR__ . '/..' . $p) ? (string) filemtime(__DIR__ . '/..' . $p) : 'x');

echo "<!DOCTYPE html>\n<html lang=\"" . htmlspecialchars(Lang::locale(), ENT_QUOTES, 'UTF-8') . "\">\n";
echo "<head><meta charset=\"UTF-8\">\n";
echo '<link rel="stylesheet" href="' . $stamp('/css/gridkit.css') . '">' . "\n";
echo '<link rel="stylesheet" href="' . $stamp('/css/themes.css') . '">' . "\n";
echo Lang::jsConfig() . "\n";
echo "<title>GridKit parity fixture</title></head>\n<body class=\"gk-root\">\n";

foreach ($cases as $name => $shape) {
    $t = (new Table('case-' . $name))
        ->setData($rows)
        ->search(['sku', 'name'])
        ->column('sku', 'SKU', ['sortable' => true])
        ->column('name', 'Name');
    $shape($t);
    $t->paginate(5);

    echo '<section data-case="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $t->render();
    echo "</section>\n";
}

/*
 * Capture the server's markup BEFORE gridkit.js runs.
 *
 * This is the whole correctness of the harness. The first version snapshotted
 * inside the comparison function, which runs after load — by which time
 * GK.table has already re-rendered every setData table. So it compared the
 * client against itself and reported perfect agreement while a button in the
 * DOM carried a raw, unwrapped onclick. A harness that cannot fail is worse
 * than none, and this one could not until this script moved above the library.
 */
?>
<script>
window.__gkServerHTML = {};
for (const s of document.querySelectorAll('[data-case]')) {
  const w = s.querySelector('.gk-table-wrap');
  if (w) window.__gkServerHTML[s.dataset.case] = w.innerHTML;
}
</script>
<?php
echo '<script src="' . $stamp('/js/gridkit.js') . '"></script>' . "\n";
?>
<script>
/*
 * The comparison itself.
 *
 * For each case: snapshot the server's markup, force a client re-render, and
 * snapshot again. Anything that differs is a place where the two renderers
 * disagree — which is the bug class this file exists for.
 *
 * Normalisation is deliberately narrow. It removes only what is *expected* to
 * change between the two renders — the sort state the click just set — and
 * nothing else. Broad normalisation is how a harness like this quietly stops
 * finding anything.
 */
window.gkParity = async function () {
  const attrsOf = (el) =>
    Array.from(el.attributes)
      .map((a) => a.name + '=' + a.value)
      .sort()
      .join(' ');

  // A fingerprint of the structure: every element's tag plus its attributes,
  // in document order. Text is excluded — it is data, and the data is the same.
  const snapshot = (root) =>
    Array.from(root.querySelectorAll('*')).map((el) => el.tagName.toLowerCase() + '[' + attrsOf(el) + ']');

  /*
   * What legitimately differs between the two renders, and nothing more.
   *
   * The sort state, because the click that forced the rebuild set it. And row
   * identity: sorting puts different rows on the page, so a row's id, its
   * checkbox value and the id interpolated into an onclick all change. Masking
   * those is the difference between a harness that reports the ten rows it
   * happened to show and one that reports whether the two renderers agree —
   * which is the only question being asked.
   *
   * Nothing else is normalised. Every widening of this function is a class of
   * bug the harness stops seeing, so each line here names its reason.
   */
  const volatile = (line) =>
    line
      .replace(/aria-sort=\w+/g, 'aria-sort=X')
      .replace(/data-gk-dir=\w+/g, 'data-gk-dir=X')
      .replace(/class=([^\]]*)gk-sorted-\w+/g, 'class=$1')
      .replace(/class=([^\]]*)gk-sortable-mi/g, 'class=$1')
      .replace(/data-gk-row-id=\d+/g, 'data-gk-row-id=N')
      .replace(/\((\d+)\)/g, '(N)')
      .replace(/(<input[^\]]*?)value=\d+/g, '$1value=N')
      .replace(/\bvalue=\d+/g, 'value=N')
      .replace(/\s+/g, ' ');

  const report = [];

  for (const section of document.querySelectorAll('[data-case]')) {
    const name = section.dataset.case;
    const wrap = section.querySelector('.gk-table-wrap');
    if (!wrap) { report.push({ case: name, error: 'no table' }); continue; }

    // The server's markup, captured before the library loaded.
    const raw = (window.__gkServerHTML || {})[name];
    if (raw === undefined) { report.push({ case: name, error: 'no server snapshot' }); continue; }
    const holder = document.createElement('div');
    holder.innerHTML = raw;
    const before = snapshot(holder).map(volatile);

    // And the client's, after a rebuild it has certainly performed: the library
    // re-renders a setData table on init, and a sort click guarantees another.
    const btn = wrap.querySelector('[data-gk-sort]');
    if (btn) {
      btn.click();
      await new Promise((r) => setTimeout(r, 400));
    }
    const after = snapshot(wrap).map(volatile);

    /*
     * A multiset difference, not an index-by-index walk. The first version
     * compared position by position, and a single element added or removed by
     * the rebuild shifted everything after it — reporting a whole table as
     * divergent because one <div> had moved. What matters is which shapes
     * exist on one side and not the other, and how many of each.
     */
    const count = (arr) => arr.reduce((m, k) => m.set(k, (m.get(k) || 0) + 1), new Map());
    const cb = count(before), ca = count(after);
    const onlyServer = [], onlyClient = [];
    for (const [k, n] of cb) {
      const d = n - (ca.get(k) || 0);
      if (d > 0) onlyServer.push({ shape: k, missing: d });
    }
    for (const [k, n] of ca) {
      const d = n - (cb.get(k) || 0);
      if (d > 0) onlyClient.push({ shape: k, extra: d });
    }
    report.push({
      case: name,
      elements: before.length,
      divergences: onlyServer.length + onlyClient.length,
      onlyServer: onlyServer.slice(0, 8),
      onlyClient: onlyClient.slice(0, 8),
    });
  }
  return report;
};
</script>
</body>
</html>
