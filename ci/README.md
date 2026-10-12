# Continuous integration

`github-actions.yml` runs `php tests/run.php` on PHP 8.2, 8.3 and 8.4, plus one
job with `mbstring` switched off — the configuration GridKit documents as
supported.

It is not at `.github/workflows/` because the token this repository is pushed
with has no `workflow` scope, and GitHub refuses a push that creates or edits a
workflow file without it. So the file ships here instead, one move away from
being live.

**To turn CI on**, from a clone with normal push rights:

```bash
mkdir -p .github/workflows
git mv ci/github-actions.yml .github/workflows/ci.yml
git commit -m "ci: enable the workflow"
git push
```

The first run starts on that push. Nothing else needs configuring — the suite
has no dependencies, so there is no cache, no lockfile and no install step.

## Until then: the same matrix, locally

```bash
bash ci/matrix.sh
```

It runs the suite on every PHP the machine has and says which of them had
mbstring, because README.md promises 8.2+ and says mbstring is used when
present but never required. Both halves of that claim went unverified until
1.50.0 — everything had only ever run on one interpreter.

```
  ok   PHP 8.2.33   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.4.24   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.5.9    without mbstring  ok — 2098 assertions passed
```

## The Releases page

Fifty-eight tags (v1.55.0 … v1.93.4) had no Release object: Packagist and the
tags were current while GitHub's Releases page, which is what most people look
at first, ended at v1.54.0. A tag is not a release — GitHub keeps them apart.

`ci/github-release.yml` closes that gap and keeps it closed once it is switched on
(below):

- **Push a tag `v<VERSION>`** — the workflow checks the tag against `VERSION`,
  cuts the notes of that version from `CHANGELOG.md`
  (`bash ci/release-notes.sh <version>`), adds the line "Live demo:
  https://gridkit.at/demo/" and creates the Release "GridKit <version>". If the
  Release exists already, it does nothing.
- **Run workflow** (Actions → Release → Run workflow, input `since`, default
  `1.54.0`) — ONE catch-up Release for the tag of the current `VERSION`: the
  notes of the newest version in full and every version since `since` as a list
  of the titles of its changes (`bash ci/release-notes.sh --since 1.54.0`).
  GitHub refuses a Release text over 125 000 characters and the whole of 1.54.0
  → 1.93.4 is 150 000, so the script falls back to titles once the full text
  passes 120 000 bytes. The tag has to be on GitHub already; run it again and it
  replaces the notes of that Release instead of adding another.

The workflow needs `permissions: contents: write` and nothing else (the job's
own `GITHUB_TOKEN`).

**Switching it on** is the same step as for `github-actions.yml` below, for the
same reason: GitHub refuses a push that creates a file under
`.github/workflows/` unless the token has the `workflow` scope, and
`.gitignore` keeps that folder out of commits made from this machine (a tracked
workflow once blocked every push for three days). So the file is committed here
and moved there by whoever has the rights:

```bash
mkdir -p .github/workflows
cp ci/github-release.yml .github/workflows/release.yml
git add -f .github/workflows/release.yml
git commit -m "ci: create GitHub Releases from tags"
git push                      # with a token that has the `workflow` scope
git tag v1.94.0 && git push origin v1.94.0     # the Release for this version
```

Or: GitHub → Add file → Create new file → `.github/workflows/release.yml`, paste
`ci/github-release.yml`. Then, once: Actions → Release → Run workflow (`since`
1.54.0) for the catch-up Release, which replaces the notes of the Release the
tag push made. What to look for: the Releases page lists "GridKit 1.94.0" as
the latest, with the notes of 1.94.0 in full, the titles of 1.93.4 … 1.55.0
below them and the line "Live demo: https://gridkit.at/demo/".

To print a body by hand — for a Release made in the web form instead:

```bash
bash ci/release-notes.sh              # the current VERSION
bash ci/release-notes.sh 1.42.0       # any released version
bash ci/release-notes.sh --since 1.54.0   # the catch-up text
```

It refuses a version `CHANGELOG.md` does not know, rather than printing the
whole file — which is what its first version did.

## Behaviour in a real browser

`php tests/run.php` executes no JavaScript; every check of `js/gridkit.js` in it
compares strings. `ci/browser.js` drives real component markup in headless
Chromium instead — which layer Escape closes, what the client-side rebuild of a
table keeps, what a hand-written modal is given:

```bash
node ci/browser.js                     # needs the "playwright" package
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser.js
```

Like `ci/parity.php` it stays outside the suite: GridKit has no dependencies,
and the suite keeps that promise. Run it when touching `js/gridkit.js`.

## Colour variables that resolve across two files

A theme lives in `css/themes.css`, the roles and their older aliases in
`css/gridkit.css`. Whether `--gk-text-muted` still means what
`--gk-on-surface-variant` means is therefore a question no string comparison can
answer — the chain only resolves in a browser, with both files loaded.
`ci/farben.js` resolves every pair across six themes, in light mode and in both
spellings of dark mode, and reports the WCAG ratio of normal and muted text
against the ground:

```bash
node ci/farben.js                      # needs the "playwright" package
GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/farben.js
```

It found the fault 1.89.0 fixes: in dark mode the aliases were literals, and
`themes.css` had moved the roles underneath them. Four pairs are asserted; the
four surface aliases stand under `ZURUECKGESTELLT` — measured and reported, but
not failed, until the surface ladder in `themes.css` is decided. Run it when
touching a colour variable in either file.

Like `ci/browser.js` and `ci/parity.php` it stays outside the suite.
