# Contributing to GridKit

Thanks for your interest in GridKit — here is how you can help.

By taking part you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Quick Start

```bash
git clone https://github.com/mmollay/gridkit.git
cd gridkit
# Open demo/index.php in your browser via a local PHP server
php -S localhost:8080
```

## Running the tests

```bash
php tests/run.php          # everything
php tests/run.php lang     # one file, e.g. tests/lang.test.php
```

No Composer and no PHPUnit — GridKit promises "no dependencies", and a test
suite that needed a package manager would quietly break that promise. The runner
is about sixty lines of plain PHP.

The suite must be green before a pull request is reviewed. CI runs it on PHP
8.2, 8.3 and 8.4, plus one job with `mbstring` disabled.

## Ways to Contribute

### Report Bugs
- Use [GitHub Issues](https://github.com/mmollay/gridkit/issues) with the **Bug Report** template
- Include browser, PHP version, and steps to reproduce

### Suggest Features
- Open an issue with the **Feature Request** template
- Describe the use case, not just the solution

### Add Translations
GridKit has built-in i18n. Adding a language is the easiest way to contribute:

1. Copy `lang/en.php` to `lang/{locale}.php`
2. Translate all string values
3. Submit a PR

See `lang/en.php` for all available keys.

### Fix Bugs / Add Features
1. Fork the repository
2. Create a feature branch: `git checkout -b feat/my-feature`
3. Make your changes
4. Test in the demo: `demo/index.php`
5. Submit a Pull Request

## Code Style

- **PHP:** PSR-12, PHP 8.2+ features welcome
- **CSS:** Use CSS Custom Properties (`--gk-*`), no preprocessors
- **JS:** Vanilla JS, no dependencies, event delegation pattern
- **No build process** — changes work immediately

## Architecture Principles

- **Zero dependencies** — don't add npm packages, Composer packages, or external libraries
- **One CSS + one JS file** — keep it simple
- **Component pattern** — PHP classes with fluent API (`->method()->method()->render()`)
- **i18n** — every user-facing string goes through `Lang::t()` (PHP) or `_t()`
  (JS), and into **both** `lang/en.php` and `lang/de.php`

  One trap worth knowing: PHP default parameter values must be constant, so
  `function search($name, $placeholder = Lang::t('...'))` does not compile. The
  workaround that looks harmless — writing the German text as the default — ships
  German to every user regardless of locale. GridKit did exactly that for a long
  time. Leave the default empty and resolve it at render time:

  ```php
  public function search(string $name, string $placeholder = ''): self { ... }
  // then, in render():
  $text = $placeholder !== '' ? $placeholder : Lang::t('table.search');
  ```

  `tests/lang.test.php` renders every component under the `en` locale and fails
  on a German word, so this cannot come back unnoticed.

## Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR
- `php tests/run.php` passes
- Update `CHANGELOG.md` under `[Unreleased]`
- Test in light and dark mode
- Test responsive (mobile + desktop)
- Don't bump the version — maintainers handle releases

## Questions?

Open an issue or check the [Live Demo](https://gridkit.at/demo/) and [Agent Skill](GRIDKIT_SKILL.md) for reference.
