<?php
/**
 * Small readers of css/gridkit.css for the tests that hold a block's rules
 * (timeline.test.php, video-frame.test.php). Not a test: the runner only loads
 * *.test.php. The names are their own — contrast.test.php and message-sm.test.php
 * keep theirs, and a second declaration of one of those would be a fatal error.
 */

declare(strict_types=1);

if (!function_exists('gkCssText')) {

    /** The whole core stylesheet. */
    function gkCssText(): string
    {
        return (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    }

    /** The stylesheet without its comments — the words in a comment must not count as a rule. */
    function gkCssBare(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', gkCssText());
    }

    /** The body of the first top-level rule whose selector list is exactly $selector ('' if there is none). */
    function gkCssRule(string $selector, ?string $css = null): string
    {
        $css ??= gkCssText();
        return preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';
    }

    /** "a: b; c: d" => ['a' => 'b', 'c' => 'd'] */
    function gkCssDecl(string $body): array
    {
        $out = [];
        foreach (explode(';', $body) as $d) {
            if (str_contains($d, ':')) {
                [$p, $v] = explode(':', $d, 2);
                $out[trim($p)] = trim($v);
            }
        }
        return $out;
    }

    /**
     * The declarations that reach $selector from the top-level rules (column 0, not inside @media or @supports),
     * the later rule overriding the earlier — a selector that stands in a list ("a,\nb {") gets the list's body too.
     */
    function gkCssDeclFor(string $selector): array
    {
        $out = [];
        preg_match_all('/(?:^|\n)([^\s@{}][^{}]*)\{([^{}]*)\}/', gkCssBare(), $m, PREG_SET_ORDER);
        foreach ($m as [, $list, $body]) {
            if (in_array($selector, array_map('trim', explode(',', $list)), true)) {
                $out = array_replace($out, gkCssDecl($body));
            }
        }
        return $out;
    }

    /** Every rule whose selector list names $needle, comments stripped: [[selector list, body], …]. */
    function gkCssRulesNaming(string $needle): array
    {
        preg_match_all('/(?:^|\n)([^{}\n]*' . preg_quote($needle, '/') . '[^{}]*)\{([^}]*)\}/', gkCssBare(), $m, PREG_SET_ORDER);
        return array_map(static fn (array $r): array => [trim($r[1]), $r[2]], $m);
    }

    /** The WCAG contrast ratio of two #rrggbb colours. */
    function gkCssRatio(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $h = ltrim($hex, '#');
            $ch = static fn (int $v): float => ($c = $v / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            return 0.2126 * $ch((int) hexdec(substr($h, 0, 2))) + 0.7152 * $ch((int) hexdec(substr($h, 2, 2)))
                 + 0.0722 * $ch((int) hexdec(substr($h, 4, 2)));
        };
        [$la, $lb] = [$lum($a), $lum($b)];
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * The hex value of a role in the light block (:root) or the dark block, following one level of var(--gk-x).
     * '' when the role is not a plain hex there (an rgba tint, a theme-derived oklch).
     */
    function gkCssRole(string $role, bool $dark): string
    {
        $css = gkCssText();
        if ($dark) {
            preg_match('/\n\[data-gk-mode="dark"\],\n\.gk-dark \{(.*?)\n\}/s', $css, $m);
        } else {
            preg_match('/\n:root,\n\.gk-root,\n\[data-gk-theme\] \{(.*?)\n\}/s', $css, $m);
        }
        $block = $m[1] ?? '';
        if (!preg_match('/--gk-' . preg_quote($role, '/') . ':\s*([^;]+);/', $block, $v)) {
            return '';
        }
        $value = trim($v[1]);
        if (preg_match('/^var\(--gk-([a-z0-9-]+)\)$/', $value, $ref)) {
            return gkCssRole($ref[1], $dark) ?: ($dark ? gkCssRole($ref[1], false) : '');
        }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '';
    }
}
