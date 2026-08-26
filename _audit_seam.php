<?php
declare(strict_types=1);
require_once __DIR__ . '/autoload.php';
use GridKit\{Lang, Layout, Table, Theme};
Lang::set('en');

$all = [];
for ($i = 1; $i <= 25; $i++) {
    $all[] = ['id' => $i, 'name' => 'Row ' . $i, 'amount' => $i * 10];
}
$page  = max(1, (int)($_GET['gk_page'] ?? 1));
$slice = array_slice($all, ($page - 1) * 5, 5);

$table = (new Table('audit'))
    ->rows($slice, count($all))          // server-driven, as documented
    ->column('name',   'Name',   ['sortable' => true])
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->toolbar(false)                     // documented option, used by demo/index.php
    ->paginate(5);

if (Table::isAjaxReload('audit')) { $table->render(); exit; }
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8">
<link rel="stylesheet" href="<?= Layout::asset('css/gridkit.css') ?>">
<link rel="stylesheet" href="<?= Layout::asset('css/themes.css') ?>">
</head>
<?= Theme::bodyTag('gk-root') ?>
<div style="padding:30px"><?php $table->render(); ?></div>
<script src="<?= Layout::asset('js/gridkit.js') ?>"></script>
</body></html>
