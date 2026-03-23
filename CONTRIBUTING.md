# Contributing to GRIDKit

Thanks for your interest in GRIDKit! Here's how you can help.

## Quick Start

```bash
git clone https://github.com/mmollay/gridkit.git
cd gridkit
# Open demo/index.php in your browser via a local PHP server
php -S localhost:8080
```

## Ways to Contribute

### Report Bugs
- Use [GitHub Issues](https://github.com/mmollay/gridkit/issues) with the **Bug Report** template
- Include browser, PHP version, and steps to reproduce

### Suggest Features
- Open an issue with the **Feature Request** template
- Describe the use case, not just the solution

### Add Translations
GRIDKit has built-in i18n. Adding a language is the easiest way to contribute:

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
- **i18n** — all user-facing strings must use `Lang::t()` (PHP) or `_t()` (JS)

## Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR
- Update `CHANGELOG.md` under `[Unreleased]`
- Test in light and dark mode
- Test responsive (mobile + desktop)
- Don't bump the version — maintainers handle releases

## Questions?

Open an issue or check the [Live Demo](https://gridkit.ssi.at/demo/) and [Agent Skill](GRIDKIT_SKILL.md) for reference.
