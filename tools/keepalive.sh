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

# Only one holder at a time. The holder renames itself to $TAG via `exec -a`
# at the bottom, so this matches argv[0] of an existing instance. (A trailing
# `# tag` comment would not work — the shell strips comments, so the tag would
# never reach the process command line.)
if pgrep -f "$TAG" >/dev/null 2>&1; then
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

# Hold the distro open forever, renaming argv[0] to the tag so the guard above
# can find this instance.
exec -a "$TAG" sleep infinity
