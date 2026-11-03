# GridKit — notes for coding sessions

PHP components for admin dashboards. No build step, no dependencies, PHP 8.2+.
Used by the SSI Panel, the PawBot dashboard, Company, EasyForm and TugendCoach, so a
change in behaviour reaches all of them: keep class names, options and rendered markup
as they are unless the task is to change them.

## This directory is the website

On the server the working copy is the document root of gridkit.at (see `.htaccess`).
Every tracked file that does not start with a dot is served. Tool notes live under
`.claude/`, never in the root. `.htaccess` answers 403 for dot-files and dot-directories.

## Tests

- `php tests/run.php` runs everything (about 8 s, no Composer, no PHPUnit).
  `php tests/run.php blocks` runs one file. A fresh shallow clone has no tags and fails
  one package test until `git fetch --depth=1 origin '+refs/tags/*:refs/tags/*'`.
- `ci/*.js` check behaviour in a real browser (`browser.js`, `browser-picker.js`,
  `browser-reiter.js`, `browser-reiter-varianten.js`, `browser-sortierbar.js`,
  `browser-1105.js` for bulk actions, CSV export and conditional fields,
  `browser-karten.js` for the compact table card on a phone,
  `browser-sticky.js` for `stickyHead()`/`stickyColumn()`) and
  contrast (`farben.js`). They are not part of the suite and need the `playwright`
  package. Run them when touching `js/gridkit.js` or the markup they exercise.
- `node ci/demo-coverage.js` opens the demo and fails when a class of `css/blocks.json` is
  neither shown nor listed, with a reason, in `ci/demo-coverage-allow.json`.
- `tests/golden-form` and `tests/golden-table` hold the markup of every field type and many
  tables, byte for byte (`tests/golden/*.json`); a deliberate change is recorded with
  `GK_UPDATE_GOLDEN=1 php tests/run.php golden-form` and its diff is read. A refactoring leaves them untouched.
- The demo is `demo/index.php` plus one file per section in `demo/sections/`. Tests that look for
  what the demo shows read it through `demoSource()` (in `tests/run.php`).
- Every option key the code reads must be named in `GRIDKIT_SKILL.md` (`tests/options-documented`),
  and so must every public method.
- The demo: `php -S 127.0.0.1:8099 -t .`, then `/demo/`.

## Release order

1. Edit the source.
2. `CHANGELOG.md`: a new entry, in English. Its first sentence is the card text of the
   "What's new" box in the demo, which is built from `CHANGELOG.md` and `css/blocks.json`.
3. `VERSION` last. It is the cache key of the assets (`gridkit.css?v=…`).
4. `bash ci/build-skill.sh` (stamps `GRIDKIT_SKILL.md`, rebuilds `skill/`), then
   `php tests/run.php`. 1.80.0 went out red because this step was forgotten.

A new `gk-` class goes into `css/blocks.json` with its version; a test enforces it.
`skill/` is generated, never edited by hand. The dashboard sync, the release and tags
are done by the owner on the server. The last tag is `v1.95.0`; later versions are untagged.

## Handle with care

According to the owner's `/gridkit` workflow the dashboard keeps its own versions of
`src/Auth.php`, `src/Sidebar.php` and `js/gridkit.js` (PawBot extensions) and its sync
never overwrites them. Do not restructure these three without asking.

## Trying a branch on the server

Push the branch, then on the server fetch it and test in a worktree next to the live
checkout (the live directory is the website, do not check branches out in it). Run git as
the owner of the directory, not as root.
