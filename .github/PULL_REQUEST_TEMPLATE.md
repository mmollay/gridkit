## What this changes

<!-- One or two sentences. What is different after this PR that was not before? -->

## Why

<!-- The problem being solved. Link an issue if there is one: Fixes #123 -->

## How it was verified

<!-- Tick what applies. `php tests/run.php` must pass before review. -->

- [ ] `php tests/run.php` passes
- [ ] Checked in a browser (say which, and whether light and dark mode)
- [ ] Checked in both languages (`?lang=en` and `?lang=de`)
- [ ] Added or extended a test that fails without this change

## Notes for the reviewer

<!-- Anything surprising: a decision you went back and forth on, a trade-off you
     made, a piece you would like a second opinion on. -->

---

<sub>New UI strings go through `Lang::t()` and into **both** `lang/en.php` and
`lang/de.php` — the test suite fails if the two catalogues drift apart. Default
parameter values cannot call `Lang::t()`; leave them empty and resolve at render
time.</sub>
