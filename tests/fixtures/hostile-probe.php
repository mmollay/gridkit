<?php
/**
 * Child process of tests/hostile-params.test.php: renders one component once for every
 * (parameter, hostile value) pair and writes one line per attempt BEFORE it renders, so
 * the parent can name the pair a hang was stuck on.
 *
 *     php tests/fixtures/hostile-probe.php Table
 *
 * Output lines:  "> param=input"  before, "ok" or "EXC <class>: <message>" after.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/../../autoload.php';

use GridKit\{FilterChips, Lang, PageSize, Pagination, Table, YearFilter};

Lang::set('en');

$inputs = [
    'array'   => ['x'],
    'nested'  => ['a' => ['b' => 'c']],
    'hugeint' => '99999999999999999999',
    'maxint'  => (string) PHP_INT_MAX,
    'neg'     => '-5',
    'zero'    => '0',
    'float'   => '1.5e3',
    'script'  => '<script>alert(1)</script>',
    'nullbyte' => "a\0b",
];
$params = ['gk_page', 'gk_sort', 'gk_dir', 'gk_search', 'gk_filter_c', 'gk_table', 'status', 'per_page', 'year', 'page'];

$render = match ($argv[1] ?? '') {
    'Table'       => fn() => (new Table('t'))->setData([['a' => 1], ['a' => 2], ['a' => 3]])
                        ->column('a', 'A', ['sortable' => true])->paginate(2)->search(['a'])
                        ->filter('c', 'select', ['options' => ['x' => 'X']])->render(),
    'TableRows'   => fn() => (new Table('t'))->rows([['a' => 1]], 50)->column('a', 'A')->paginate(10)->render(),
    'FilterChips' => fn() => (new FilterChips('f'))->chip('a', 'A')->chip('b', 'B')->render(),
    'PageSize'    => fn() => (new PageSize())->render(),
    'YearFilter'  => fn() => (new YearFilter('y'))->range(2020, 2026)->render(),
    'Pagination'  => fn() => print(Pagination::build(['totalPages' => 10, 'current' => 3])),
    default       => throw new InvalidArgumentException('unknown component'),
};

foreach ($params as $param) {
    foreach ($inputs as $name => $value) {
        echo "> $param=$name\n";
        flush();
        $_GET = [$param => $value];
        set_error_handler(static fn(int $no, string $msg): bool => true);
        ob_start();
        try {
            $render();
            $result = 'ok';
        } catch (Throwable $e) {
            $result = 'EXC ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 100);
        } finally {
            ob_end_clean();
            restore_error_handler();
        }
        echo "$result\n";
    }
}
echo "done\n";
