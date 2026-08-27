#!/usr/bin/env bash
# The compatibility matrix the CI workflow would run, for a machine where CI
# does not (see ci/README.md). Runs the suite on every PHP the host has, and
# reports whether mbstring was present for each — README.md promises 8.2+ and
# says mbstring is used when available but never required.
#
#   bash ci/matrix.sh
set -u
cd "$(dirname "$0")/.."

found=0
fail=0
for bin in php8.2 php8.3 php8.4 php8.5 php; do
    command -v "$bin" >/dev/null 2>&1 || continue
    ver=$("$bin" -r 'echo PHP_VERSION;' 2>/dev/null) || continue
    # `php` is usually a symlink to one of the above — skip a repeat.
    case " ${seen:-} " in *" $ver "*) continue ;; esac
    seen="${seen:-} $ver"
    found=$((found + 1))

    mb=$("$bin" -r 'echo function_exists("mb_strtolower") ? "with mbstring   " : "without mbstring";')
    out=$("$bin" tests/run.php 2>&1 | grep -E '^ok —|^FAILED' | head -1)
    case "$out" in
        ok*) mark="ok " ;;
        *)   mark="FAIL"; fail=$((fail + 1)) ;;
    esac
    printf '  %-4s PHP %-8s %s  %s\n' "$mark" "$ver" "$mb" "$out"
done

echo
if [ "$found" -eq 0 ]; then
    echo "  no PHP found at all."
    exit 1
fi
if [ "$fail" -gt 0 ]; then
    echo "  $fail of $found failed."
    exit 1
fi
echo "  $found version(s), all green."
