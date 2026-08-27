#!/usr/bin/env bash
#
# Export the local WordPress site to flat files in dist/, ready for any static
# host (Cloudflare Pages, Netlify, Vercel, GitHub Pages).
#
# The site is read-only — comments are closed, there are no forms or logins —
# so nothing is lost in the translation. Search is handled in the browser from
# an index baked into every page, so it keeps working without PHP.
#
# Run inside WSL:
#   ./export.sh                         # root-relative URLs (deploy at domain root)
#   ./export.sh https://errands.pages.dev   # absolute URLs, for canonical tags
#
set -euo pipefail

cd "$(dirname "$0")"

BASE="${1:-}"
PORT="$(grep -E '^WP_PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '[:space:]')"
PORT="${PORT:-8080}"
ORIGIN="http://localhost:${PORT}"
DIST="dist"

say() { printf '\n\033[1;38;5;202m▸ %s\033[0m\n' "$1"; }

say "Making sure the site is up"
./up.sh >/dev/null

say "Crawling $ORIGIN"

rm -rf "$DIST"
mkdir -p "$DIST"

# Media is deliberately excluded from the crawl: the files are already on this
# disk, so copying them locally is far faster than fetching them over HTTP, and
# it lets us copy *exactly* what the pages reference (including the full-size
# originals behind the lightbox, which are in data-full attributes that no
# crawler follows).
#
# Also skipped: anything with a query string, the admin, and endpoints that
# cannot exist without PHP.
wget \
	--recursive --level=inf \
	--page-requisites \
	--convert-links \
	--no-host-directories \
	--no-parent \
	--restrict-file-names=nocontrol \
	--domains=localhost \
	--reject-regex '(\?|/wp-admin|/wp-login|/wp-json|xmlrpc|/feed/|/wp-content/uploads/)' \
	--directory-prefix="$DIST" \
	--no-verbose \
	"$ORIGIN/" || true   # wget exits 8 on any 404 it meets while crawling

say "Rendering the 404 page"
curl -s "$ORIGIN/this-page-does-not-exist-$(date +%s 2>/dev/null || echo x)/" -o "$DIST/404.html" || true

say "Copying referenced media and rewriting URLs"

# Node is installed on the Windows side, not in this distro. WSL interop puts
# node.exe on PATH, and since the project lives under /mnt/c its cwd resolves to
# the same directory, so relative paths behave normally.
if command -v node >/dev/null 2>&1; then
	NODE=node
elif command -v node.exe >/dev/null 2>&1; then
	NODE=node.exe
else
	echo "Neither node nor node.exe found — cannot post-process." >&2
	exit 1
fi

"$NODE" tools/export-post.js "$DIST" "$ORIGIN" "$BASE"

say "Done"
du -sh "$DIST"
printf '  files: %s\n\n' "$(find "$DIST" -type f | wc -l)"
cat <<EOF
  Preview locally:
      cd $DIST && python3 -m http.server 8081

  Deploy (pick one):
      npx wrangler pages deploy $DIST --project-name errands
      npx netlify deploy --dir=$DIST --prod
      npx vercel deploy --prebuilt $DIST

EOF
