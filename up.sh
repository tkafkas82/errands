#!/usr/bin/env bash
#
# Bring the stack up and wait until the site actually answers.
#
# Why this is not just `docker compose up -d`:
#
# WSL shuts its distro down when nothing holds it open. Docker then brings the
# containers back on its own via `restart: unless-stopped` — but that policy
# restarts each container independently and ignores the `depends_on:
# service_healthy` ordering that a real `compose up` honours. So Apache can come
# back before MariaDB is accepting connections, and worse, if the database
# container is recreated with a new IP, the already-running PHP workers keep the
# old address cached and every request returns WordPress's "Database Error".
#
# The fix is cheap: wait for the database to report healthy, then bounce the web
# container so it re-resolves `db`, then confirm the site returns 200.
#
set -euo pipefail

cd "$(dirname "$0")"

PORT="$(grep -E '^WP_PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '[:space:]')"
PORT="${PORT:-8080}"
URL="http://localhost:${PORT}"

docker compose up -d

printf 'Waiting for the database'
for _ in $(seq 1 90); do
	if [ "$(docker inspect errands-db --format '{{.State.Health.Status}}' 2>/dev/null)" = "healthy" ]; then
		printf ' healthy\n'
		break
	fi
	printf '.'
	sleep 1
done

# Bounce the web container so PHP re-resolves the database host.
docker compose restart wordpress >/dev/null

printf 'Waiting for the site'
code=000
for _ in $(seq 1 60); do
	code="$(curl -s -o /dev/null -w '%{http_code}' "$URL/" || echo 000)"
	if [ "$code" = "200" ] || [ "$code" = "301" ] || [ "$code" = "302" ]; then
		printf ' up\n'
		break
	fi
	printf '.'
	sleep 1
done

if [ "$code" != "200" ] && [ "$code" != "301" ] && [ "$code" != "302" ]; then
	printf '\nSite is still returning %s. Check: docker compose logs wordpress --tail=40\n' "$code"
	exit 1
fi

cat <<EOF

  Site   $URL
  Admin  $URL/wp-admin

EOF
