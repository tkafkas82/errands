#!/usr/bin/env bash
#
# Bring up the local ERRANDS WordPress site from nothing.
#
# Run inside WSL (Ubuntu), where Docker lives:
#   cd "/mnt/c/Users/PC/Desktop/bat files/Errands" && ./setup.sh
#
set -euo pipefail

cd "$(dirname "$0")"

set -a
# shellcheck disable=SC1091
. ./.env
set +a

say() { printf '\n\033[1;38;5;202m▸ %s\033[0m\n' "$1"; }
wp() { docker compose run --rm cli "$@"; }

say "Starting containers"
docker compose up -d

say "Waiting for the database"
until docker compose exec -T db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; do
	sleep 2
done

if wp core is-installed >/dev/null 2>&1; then
	say "WordPress already installed — skipping core install"
else
	say "Installing WordPress"
	wp core install \
		--url="$WP_URL" \
		--title="$WP_TITLE" \
		--admin_user="$WP_ADMIN_USER" \
		--admin_password="$WP_ADMIN_PASSWORD" \
		--admin_email="$WP_ADMIN_EMAIL" \
		--skip-email
fi

say "Activating the ERRANDS theme"
wp theme activate errands

say "Rebuilding the content from import.json"
wp eval-file /import/import.php

say "Flushing permalinks"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

say "Done"
cat <<EOF

  Site   $WP_URL
  Admin  $WP_URL/wp-admin   ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)

EOF
