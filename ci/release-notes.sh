#!/usr/bin/env bash
# The body for a GitHub Release, cut from CHANGELOG.md.
#
# The Releases page still shows v1.2.3 while the code is far past it, and the
# thirty tags on GitHub are not releases — GitHub treats those as separate
# things. Creating one needs a title and a body; this prints the body.
#
#   bash ci/release-notes.sh            # the current VERSION
#   bash ci/release-notes.sh 1.42.0     # any released version
#   bash ci/release-notes.sh --since 1.4.0   # everything after a version,
#                                            # for one catch-up release
set -u
cd "$(dirname "$0")/.."

have() { grep -q "^## \\[$1\\]" CHANGELOG.md; }

if [ "${1:-}" = "--since" ]; then
    from="${2:?usage: ci/release-notes.sh --since <version>}"
    to=$(cat VERSION)
    # Without this the awk below simply never matches and prints the whole
    # file — which is how a --since 1.4.0 quietly returned 144 versions back
    # to 0.9.0, because 1.4.0 was the one tagged release with no entry.
    if ! have "$from"; then
        echo "ci/release-notes.sh: CHANGELOG.md has no entry for $from" >&2
        exit 1
    fi
    # Everything after $from, as it stands in CHANGELOG.md.
    body=$(awk -v from="## [$from]" '
        $0 ~ /^## \[/ { if (index($0, from) == 1) exit }
        { print }
    ' CHANGELOG.md | sed -n '/^## \[/,$p')
    printf '## GridKit %s\n\n' "$to"
    # GitHub refuses a release body over 125 000 characters, and 1.54.0 → 1.93.4
    # is 150 000. Past 120 000 bytes (the safe side of characters) the release
    # carries the headline of every change instead — each version with the
    # titles of its entries — and links to the whole text.
    if [ "$(printf '%s' "$body" | wc -c)" -le 120000 ]; then
        printf 'Everything since %s, in one release. The full history is in\n' "$from"
        printf '[CHANGELOG.md](https://github.com/mmollay/gridkit/blob/main/CHANGELOG.md).\n\n'
        printf '%s\n' "$body"
    else
        printf 'Everything since %s, in one release. The newest version in full, every earlier one with the titles of its changes;\n' "$from"
        printf 'the full text of each is in [CHANGELOG.md](https://github.com/mmollay/gridkit/blob/main/CHANGELOG.md).\n\n'
        printf '%s\n' "$body" | awk '
            /^## \[/ { n++ }
            n == 1    { print; next }
            /^## \[/ { if (n == 2) print "\n---\n\n### Earlier versions"; v = $0; sub(/^## \[/, "", v); sub(/\] - /, " — ", v); printf "\n#### %s\n", v; next }
            /^### /   { t = $0; sub(/^### /, "", t); printf "- %s\n", t }
        '
    fi
    exit 0
fi

version="${1:-$(cat VERSION)}"
if ! have "$version"; then
    echo "ci/release-notes.sh: CHANGELOG.md has no entry for $version" >&2
    exit 1
fi
awk -v want="## [$version]" '
    index($0, want) == 1 { on = 1; print; next }
    on && /^## \[/       { exit }
    on                   { print }
' CHANGELOG.md | sed -e 's/^---$//' -e '/./,$!d'
