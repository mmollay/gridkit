<?php
require __DIR__ . '/autoload.php';
use GridKit\Table;

function tbl(string $id, string $label): string {
    return (new Table($id))
        ->column('id', 'ID')->column('name', 'Name')
        ->button('delete', ['icon' => 'delete'])
        ->setData([['id' => 1, 'name' => $label]])
        ->render();
}

if (isset($_GET['section'])) { echo '<div data-gk-content>' . tbl('orders', 'Orders') . '</div>'; exit; }
?><!doctype html><html><head><meta charset="utf-8"><title>repro</title></head>
<body class="gk-root">
<div data-gk-content><?= tbl('dashboard', 'Dashboard') ?></div>
<script src="/js/gridkit.js"></script>
<script>
window.__log = [];
// EXACT pattern from GRIDKIT_SKILL.md line 289-292 (bind once at page render)
var tableId = 'dashboard';
document.querySelector('[data-gk-table=' + tableId + ']')
  .addEventListener('gk:rowaction', function (e) { window.__log.push('BOUND-ONCE ' + e.detail.tableId + ':' + e.detail.action); });
// Delegated alternative (event has bubbles:true)
document.addEventListener('gk:rowaction', function (e) { window.__log.push('DELEGATED ' + e.detail.tableId + ':' + e.detail.action); });

// Simulate an ajaxNav section change exactly as GK.navigate._render does
window.__nav = function () {
  return fetch('/_repro_rowaction.php?section=orders').then(r => r.text()).then(html => {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var content = document.querySelector('[data-gk-content]');
    content.innerHTML = doc.querySelector('[data-gk-content]').innerHTML;
    GK.table.init();          // what _render() calls
    return true;
  });
};
</script>
</body></html>
