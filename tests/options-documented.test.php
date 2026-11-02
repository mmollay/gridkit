<?php
/**
 * Every option the code reads is in the skill.
 *
 * `llms.txt` says of GRIDKIT_SKILL.md: "It is the whole surface; there is no second docs
 * site to search." That holds for methods (141 of 142 were named in it) but it did not hold
 * for options: `clearable`, `maxFiles`, `minSize`, `labelField`, `minChars`, `hideOnMobile`,
 * `blankZero`, `dateFormat`, `iconPosition`, `fullWidth` and `liveInput` were read by the code,
 * shown in the demo or not, and written down nowhere an assistant would look.
 *
 * The keys are read out of the source — `$opts['x']`, `$f['x']`, `$col['x']` and the other
 * names the components give the array they are handed — and each has to appear as a word in
 * GRIDKIT_SKILL.md. A new option that nobody documents fails here, not in somebody's project.
 */

declare(strict_types=1);

/** Keys that are plumbing, not options a caller sets. */
const OPTIONS_INTERNAL = [
    'children',      // Sidebar: built from ->group()/->item() nesting
    'index',         // ImagePicker: position inside the grid, set by the component
    'liveInput',     // TableHeader: the stored form of the public option 'live'
];

/** @return array<string,callable> */
return [

'every option key the source reads is named in the skill' => function (): void {
    $skill = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    $vars = 'opts|o|f|col|bopts|a|cfg|options|item|opt|c|b|s|p|cell|entry|setting|tab|u|btn|link';
    $generic = ['label', 'name', 'type', 'value', 'id', 'class', 'href', 'text', 'title', 'url'];

    $classes = ['Table', 'Form', 'Button', 'Header', 'Sidebar', 'Select', 'TableHeader', 'Pagination',
                'ImagePicker', 'ActionGroup', 'Auth', 'StatCards', 'FilterChips', 'PageSize', 'YearFilter', 'Layout'];
    foreach ($classes as $class) {
        $src = (string) file_get_contents(__DIR__ . "/../src/$class.php");
        preg_match_all('/\$(?:' . $vars . ')\[[\'"]([A-Za-z_]+)[\'"]\]/', $src, $m);
        $keys = array_diff(array_unique($m[1]), $generic, OPTIONS_INTERNAL);
        foreach ($keys as $key) {
            if (strlen($key) < 3) continue;
            T::ok(preg_match('/(?<![A-Za-z0-9_])' . preg_quote($key, '/') . '(?![A-Za-z0-9_])/', $skill) === 1,
                "$class reads the option `$key` and GRIDKIT_SKILL.md never names it");
        }
    }
},

];
