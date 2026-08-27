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

Thirty tags are on GitHub; six Releases exist, the newest `v1.2.3`. GitHub
treats those as separate things — a tag is not a release, and the Releases page
is what most people look at first.

Creating one needs a tag, a title and a body. The tags are there; this prints
the body:

```bash
bash ci/release-notes.sh              # the current VERSION
bash ci/release-notes.sh 1.42.0       # any released version
bash ci/release-notes.sh --since 1.4.0   # one catch-up release covering
                                         # everything after the version
                                         # Packagist still serves
```

Then: **Releases → Draft a new release**, pick the tag, paste, publish. It
refuses a version `CHANGELOG.md` does not know, rather than printing the whole
file — which is what its first version did.
