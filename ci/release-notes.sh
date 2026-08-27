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
    printf '## GridKit %s\n\n' "$to"
    printf 'Everything since %s, in one release. The full history is in\n' "$from"
    printf '[CHANGELOG.md](CHANGELOG.md).\n\n' 
    awk -v from="## [$from]" '
        $0 ~ /^## \[/ { if (index($0, from) == 1) exit }
        { print }
    ' CHANGELOG.md | sed -n '/^## \[/,$p'
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
