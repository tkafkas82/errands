#!/usr/bin/env bash
#
# Hold the Ubuntu distro open and keep the ERRANDS stack serving.
#
# Belt and braces alongside vmIdleTimeout=-1 in %USERPROFILE%\.wslconfig:
# that setting stops WSL shutting the VM down for being idle, and this holds a
# live session so nothing reaps the distro regardless. Started at logon by
# tools/wsl-keepalive.vbs via a Scheduled Task.
#
# Safe to run more than once — the guard below exits if an instance is already
# holding the distro.

set -u

PROJECT="/mnt/c/Users/PC/Desktop/bat files/Errands"
TAG="errands-keepalive"
PIDFILE="/tmp/errands-keepalive.pid"

# Only one holder at a time.
#
# A pidfile rather than `pgrep -f "$TAG"`: a substring match on the full command
# line also matches any *other* process that merely mentions the tag — including
# a shell running `pgrep -af errands-keepalive` to check on it, which reports two
# holders when there is one. /tmp is empty on a fresh boot, which is correct:
# a newly booted distro has no holder.
if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null; then
	exit 0
fi

# Wait for the Docker socket; systemd starts it at boot but not instantly.
for _ in $(seq 1 60); do
	docker info >/dev/null 2>&1 && break
	sleep 1
done

# Bring the site up. up.sh waits for the database and bounces the web
# container so PHP re-resolves it. Never fail the keep-alive over this.
if [ -x "$PROJECT/up.sh" ]; then
	( cd "$PROJECT" && ./up.sh ) >/dev/null 2>&1 || true
fi

# Hold the distro open forever.
#
# `exec` replaces this shell without changing the PID, so $$ recorded now is the
# holder's PID. `exec -a` renames argv[0] so the process is recognisable in ps.
echo $$ > "$PIDFILE"
exec -a "$TAG" sleep infinity
