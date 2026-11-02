<?php
/**
 * Child process of tests/hostile-params.test.php: a static table with two rows and one row per
 * page, asked for the page given as the first argument.
 *
 *     php tests/fixtures/hostile-page.php 99999999999999999999
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/../../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');
$_GET = ['gk_page' => $argv[1] ?? '1'];

(new Table('t'))->setData([['a' => 1], ['a' => 2]])->column('a', 'A')->paginate(1)->render();
echo "\ndone\n";
