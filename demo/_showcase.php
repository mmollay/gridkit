<?php
/**
 * Show an example and the code that produced it — the same code, not a copy.
 *
 * The demo used to carry hand-written blocks next to its examples:
 *
 *     <div class="demo-code"><pre>$table->size('sm');  // compact</pre></div>
 *
 * Those are a second copy of the truth, and the copy is the one that rots. The
 * blocks below are read out of this file at request time, using the line
 * numbers PHP itself reports for the closure, so what a reader sees is by
 * construction what just ran. Change the example and the listing changes with
 * it; there is nothing to keep in step.
 *
 * Usage:
 *
 *     showcase(function () use ($rows) {
 *         (new Table('t'))->setData($rows)->column('name', 'Name')->render();
 *     });
 *
 * The closure's `use (...)` clause is deliberately not shown: it is plumbing
 * for this page, not part of what a reader would write.
 */

declare(strict_types=1);

/**
 * Read a closure's body back out of the file it was written in.
 *
 * getStartLine() is the line the `function` keyword sits on and getEndLine()
 * the closing brace, so the body is everything strictly between them. The
 * result is dedented by the smallest indentation any of its lines has, which
 * turns the eight or twelve spaces this file happens to use into a listing that
 * starts at column zero.
 */
function showcaseSource(callable $fn): string
{
    $r = new ReflectionFunction($fn);
    $file = $r->getFileName();
    if ($file === false) {
        return '';
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return '';
    }

    $body = array_slice(
        $lines,
        $r->getStartLine(),                       // skip the `function (…) {` line
        max(0, $r->getEndLine() - $r->getStartLine() - 1)
    );

    // Drop blank lines at either end before measuring, or a leading empty line
    // would make the smallest indentation zero and dedent nothing.
    while ($body && trim($body[0]) === '') array_shift($body);
    while ($body && trim(end($body)) === '') array_pop($body);
    if (!$body) {
        return '';
    }

    $indent = null;
    foreach ($body as $line) {
        if (trim($line) === '') continue;
        $width = strlen($line) - strlen(ltrim($line));
        $indent = $indent === null ? $width : min($indent, $width);
    }

    return implode("\n", array_map(
        static fn(string $l): string => $indent ? substr($l, $indent) : $l,
        $body
    ));
}

/**
 * Render the example, then the code beneath it in a disclosure.
 *
 * The disclosure is a <details>, so it works with no JavaScript at all and is
 * announced correctly without any ARIA of its own — the element already means
 * "expandable section" to a screen reader, which a div with a click handler
 * does not.
 *
 * @param callable $fn    The example. Whatever it echoes becomes the output.
 * @param string   $label Optional heading for the disclosure.
 */
function showcase(callable $fn, string $label = ''): void
{
    $source = showcaseSource($fn);

    ob_start();
    $fn();
    $output = (string) ob_get_clean();

    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $summary = $label !== '' ? $label : \GridKit\Lang::t('demo.show_code');

    echo '<div class="demo-showcase">';
    echo '<div class="demo-showcase-output">' . $output . '</div>';
    echo '<details class="demo-showcase-code">';
    echo '<summary>'
       . '<span class="material-icons" aria-hidden="true">code</span>'
       . '<span>' . $e($summary) . '</span>'
       . '</summary>';
    echo '<div class="demo-showcase-src">';
    echo '<button type="button" class="demo-showcase-copy" data-demo-copy>'
       . $e(\GridKit\Lang::t('demo.copy')) . '</button>';
    echo '<pre><code>' . $e($source) . '</code></pre>';
    echo '</div>';
    echo '</details>';
    echo '</div>';
}
