# Security Policy

## Supported versions

GridKit is developed on `main` and released from tags. Only the latest release
receives fixes — there are no maintained branches for older versions.

| Version | Supported |
|---------|-----------|
| 1.28.x  | ✅ |
| < 1.28  | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately through
[GitHub's private advisory form](https://github.com/mmollay/gridkit/security/advisories/new),
or by email to **office@ssi.at** with `GridKit security` in the subject.

Please include:

- what an attacker can do, and what access they need to start
- the smallest reproduction you have — a code snippet is ideal
- the GridKit version and PHP version

You can expect an acknowledgement within **five working days**. GridKit is
maintained by one person, so a fix may take longer than that; you will be told
where it stands rather than left waiting.

Please give a reasonable window for a fix before publishing. If you would like
credit in the advisory, say so and name how you want to be credited.

## What counts as a vulnerability

GridKit renders HTML from data your application hands it. In scope:

- **Escaping failures** — any path where a value reaches the page unescaped and
  the caller had no way to know. `Header::title($x, raw: true)` is documented as
  a deliberate opt-out and is not a finding.
- **Injection through GridKit's own parameters** — sort keys, filter names,
  pagination parameters and search terms all arrive from the URL.
- **Auth flaws** in `GridKit\Auth` — session handling, the remember-me cookie,
  password verification.
- **Path traversal** in anything that resolves a file path, such as
  `Modal`'s AJAX URLs or `Layout::asset()`.

Out of scope:

- SQL injection in a query **you** built and passed to `Table::query()`.
  GridKit does not parse your SQL; bind your parameters.
- Anything requiring an already-compromised server or database.
- Missing headers (CSP, HSTS) on your deployment — GridKit does not set them.
- The bundled CKEditor build under `assets/`. Report those to
  [CKSource](https://github.com/ckeditor/ckeditor5/security).
