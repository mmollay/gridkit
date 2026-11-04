#!/bin/bash
# GridKit: deliver main to the live checkout (gridkit.at) once the suite is green.
#
# Installed on the server as /home/pawbot/bin/gridkit-deploy.sh and run by pawbot's cron every
# minute (ci/README.md, "Automatic delivery"). Quiet when there is nothing new. For a new commit
# on origin/main it
#   1. runs `php tests/run.php` on that commit in a worktree NEXT TO the live checkout — never in
#      the website itself, which is the document root of gridkit.at;
#   2. only when the suite is green, moves the live checkout forward (--ff-only: a local change
#      there stops the delivery instead of being overwritten);
#   3. tags v<VERSION> when that tag does not exist yet and pushes it (Packagist picks it up).
#      An existing tag is never moved: Packagist keeps the first commit a version pointed at.
# A commit whose tests failed is tried once, not every minute; the next commit is tried again.
#
# Started by hand (a terminal), it says what it does and why it does nothing, and tries a commit
# whose tests failed once more; from cron or systemd it stays quiet. GK_VERBOSE=1/0 forces either.
#
# Settings (environment, for tests and other servers):
#   GK_LIVE   the live checkout            (default /home/pawbot/projects/gridkit)
#   GK_CHECK  the worktree the suite runs in (default /home/pawbot/projects/gridkit-deploy-check)
#   GK_STATE  where it remembers things    (default ~/.gridkit-deploy)
#   GK_PHP    the PHP binary               (default php)
set -euo pipefail

LIVE=${GK_LIVE:-/home/pawbot/projects/gridkit}
CHECK=${GK_CHECK:-/home/pawbot/projects/gridkit-deploy-check}
STATE=${GK_STATE:-$HOME/.gridkit-deploy}
PHP=${GK_PHP:-php}
if [ -n "${GK_VERBOSE:-}" ]; then VERBOSE=$GK_VERBOSE; elif [ -t 1 ]; then VERBOSE=1; else VERBOSE=0; fi

say() { echo "$(date '+%F %T') $*"; }
note() { [ "$VERBOSE" = 1 ] && say "$*" || true; }

mkdir -p "$STATE"
exec 9>"$STATE/lock"
if ! flock -n 9; then note "another run is still at work, nothing to do"; exit 0; fi

# The suite reads HTML with DOMDocument and counts letters with mbstring: without them it is red
# for a reason that is not GridKit's. Say so, instead of a log full of failed tests.
missing=$("$PHP" -r 'foreach (["dom", "mbstring"] as $x) if (!extension_loaded($x)) echo "$x ";' 2>/dev/null || echo "php")
if [ -n "$missing" ]; then
    say "NOT delivered: $PHP lacks: $missing— install it (e.g. apt install php8.2-xml php8.2-mbstring) or set GK_PHP"
    exit 1
fi

# Tag the VERSION of the live checkout, push the tag once; a failed push is tried again next run.
tag() {
    local v="v$(tr -d '[:space:]' < VERSION)"
    git rev-parse -q --verify "refs/tags/$v" >/dev/null || git tag "$v"
    if [ "$(cat "$STATE/pushed" 2>/dev/null)" != "$v" ]; then
        if git push -q origin "refs/tags/$v"; then
            echo "$v" > "$STATE/pushed"
            say "tagged $v"
        else
            say "could not push $v, trying again next run"
        fi
    fi
}

cd "$LIVE"
git fetch -q --tags origin main
new=$(git rev-parse FETCH_HEAD)

if [ "$(git rev-parse HEAD)" = "$new" ]; then
    note "up to date ($(git rev-parse --short HEAD), $(tr -d '[:space:]' < VERSION))"
    tag
    exit 0
fi
if [ "$(cat "$STATE/failed" 2>/dev/null)" = "$new" ]; then
    if [ "$VERBOSE" != 1 ]; then exit 0; fi
    note "$(git rev-parse --short "$new") failed its tests before; trying again"
fi
note "testing $(git rev-parse --short "$new") in $CHECK"

short=$(git rev-parse --short "$new")
if [ ! -e "$CHECK/.git" ]; then
    git worktree add -q --detach "$CHECK" "$new"
fi
git -C "$CHECK" checkout -q -f --detach "$new"
git -C "$CHECK" clean -q -f -d

if ! (cd "$CHECK" && "$PHP" tests/run.php > "$STATE/tests.log" 2>&1); then
    echo "$new" > "$STATE/failed"
    say "NOT delivered $short: the tests failed — $STATE/tests.log"
    exit 1
fi

git merge -q --ff-only "$new"
say "delivered $short ($(tr -d '[:space:]' < VERSION))"
tag
