#!/usr/bin/env bash
#
# Publish the static export to its own repository, so a static host's Git
# integration can deploy it.
#
#   ./publish.sh              # mirror the existing dist/ and push
#   ./publish.sh --export     # re-run the export first
#
# Why a separate working copy in .publish/ rather than `git init` inside dist/:
# export.sh begins with `rm -rf dist`, which would delete the repository on the
# next export. .publish/ holds the git history; dist/ stays disposable.
#
# Why the content lives in its own repo at all: the source repo keeps dist/
# gitignored (205MB), and a static host's Git integration cannot build this
# project anyway — building it would mean running WordPress, PHP and MySQL.
# With the exported files at a repo root, the host just serves them.
#
set -euo pipefail

cd "$(dirname "$0")"

REMOTE="${ERRANDS_DIST_REMOTE:-https://github.com/tkafkas82/errandsDist}"
BRANCH="main"
DIST="dist"
WORK=".publish"

say() { printf '\n\033[1;38;5;202m▸ %s\033[0m\n' "$1"; }

# Windows git holds the GitHub credentials (Git Credential Manager); the Linux
# git in this distro does not, so prefer git.exe when it is reachable.
if command -v git.exe >/dev/null 2>&1; then
	GIT=git.exe
else
	GIT=git
fi

if [ "${1:-}" = "--export" ]; then
	./export.sh
fi

if [ ! -d "$DIST" ]; then
	echo "No $DIST/ — run ./export.sh first." >&2
	exit 1
fi

say "Preparing $WORK"

if [ ! -d "$WORK/.git" ]; then
	mkdir -p "$WORK"
	( cd "$WORK" && "$GIT" init -q -b "$BRANCH" && "$GIT" remote add origin "$REMOTE" )
	# Pick up anything already on the remote so we add to it rather than
	# needing a force push.
	( cd "$WORK" && "$GIT" fetch -q origin "$BRANCH" 2>/dev/null && "$GIT" reset -q --mixed FETCH_HEAD ) || true
fi

say "Mirroring $DIST -> $WORK"
rsync -a --delete --exclude '.git' "$DIST"/ "$WORK"/

# Static-host and GitHub Pages housekeeping.
: > "$WORK/.nojekyll"

# Generated output: never let git rewrite line endings in it.
printf '* -text\n' > "$WORK/.gitattributes"

cat > "$WORK/README.md" <<'EOF'
# ERRANDS — static build output

Generated files. **Do not edit anything here by hand** — it is overwritten on
every publish.

Built from the WordPress source at <https://github.com/tkafkas82/errands> with:

    ./export.sh      # crawl the local site into dist/
    ./publish.sh     # mirror dist/ here and push

Serve this repository at a **domain root**. The URLs are root-relative
(`/wp-content/...`), so a subpath deploy such as GitHub Pages'
`/errandsDist/` would break them — for that, re-export with the base URL:

    ./export.sh https://tkafkas82.github.io/errandsDist

`robots.txt` disallows crawling, so this preview cannot compete with the real
errands.gr in search results. Remove it when that is no longer wanted.
EOF

say "Committing"
cd "$WORK"

"$GIT" add -A

if "$GIT" diff --cached --quiet 2>/dev/null; then
	echo "Nothing changed since the last publish."
	exit 0
fi

FILES="$("$GIT" diff --cached --name-only | wc -l)"
"$GIT" -c user.name="${GIT_AUTHOR_NAME:-tkafkas82}" \
	-c user.email="${GIT_AUTHOR_EMAIL:-tkafkas@gmail.com}" \
	commit -q -m "Static build $(date -u '+%Y-%m-%d %H:%M UTC') — ${FILES} file(s) changed"

say "Pushing to $REMOTE ($BRANCH)"
"$GIT" push -u origin "$BRANCH"

say "Done"
cat <<EOF

  Repository  $REMOTE
  Files       $(find . -type f -not -path './.git/*' | wc -l)
  Size        $(du -sh --exclude=.git . | cut -f1)

  Deploy it by importing that repo on Vercel / Cloudflare Pages / Netlify.
  No build command, no output directory — it is already built.

EOF
