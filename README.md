# ERRANDS — local WordPress rebuild

A local WordPress site for [errands.gr](https://errands.gr/), rebuilt on a modern
stack with a new custom theme. The original is WordPress 4.4.18 running the 2011
`imbalance` theme from wpshower: a flat reverse-chronological blog where all 22
posts sit in one `Uncategorized` category, with images pasted inline at fixed
pixel widths and no pages at all.

This rebuild keeps the content and throws away the 2011 scaffolding.

---

## Running it

Docker lives inside the **Ubuntu** WSL distro on this machine, not on the Windows
host, so everything goes through `wsl -d Ubuntu`.

| Action | How |
| --- | --- |
| Start | double-click `start.bat` (opens the browser too) |
| Stop | double-click `stop.bat` |
| Start from WSL | `./up.sh` |
| Full rebuild from scratch | in WSL: `cd "/mnt/c/Users/PC/Desktop/bat files/Errands" && ./setup.sh` |

Always start through `start.bat` or `up.sh` rather than a bare
`docker compose up -d` — see the Database Error note under Troubleshooting for
why.

- Site: <http://localhost:8080>
- Admin: <http://localhost:8080/wp-admin> — `admin` / `errands`

Credentials live in `.env` and are local-only.

### Running wp-cli

```bash
cd "/mnt/c/Users/PC/Desktop/bat files/Errands"
docker compose run --rm cli <command>          # note: no leading "wp"

docker compose run --rm cli post list --post_type=project
docker compose run --rm cli media regenerate --yes
```

---

## What changed from the original

### Structure

The old site had one flat feed and no pages. This one has a real content model:

| Old | New |
| --- | --- |
| 21 posts in `Uncategorized` | `project` custom post type, archive at `/projects/` |
| 1 post that was really an about text | `About` page |
| — | `Exhibitions` page (from the group's own stated record) |
| — | `Index` page — everything by year, using `template-index.php` |
| No grouping | `project_series` taxonomy — 8 `Improvised City`, 1 `Workshops` |
| `?p=477` URLs | `/projects/errands-group/` style permalinks |
| Comments open, unused | Comments closed |

Series are assigned **only** where the project's own title states one, so nothing
is invented. The rest are untagged and can be grouped in the dashboard.

### Design

Editorial, gallery-first. The photography carries the site.

- Near-black / warm off-white palette; the original `#ff3706` red kept as the
  single accent. Light and dark, following the OS with a manual toggle.
- Inter + JetBrains Mono for meta labels. Inter covers Greek, which two of the
  project titles need.
- Cards tile in a 6-column grid on a repeating `[wide wide][std std std]`
  rhythm, so the archive reads as an edited page rather than a table of
  thumbnails. A trailing orphan card runs full width.
- Images no longer sit inline in the prose. Each project's images are real
  attachments, rebuilt as a gallery with a keyboard-navigable lightbox
  (arrows, Esc, swipe, neighbour preloading).
- Client-side filtering on the archive by series and by year — the whole
  archive is one request, so no round trip is needed for 21 cards.

Everything degrades without JavaScript: the grid shows every card and the
gallery figures still link to full-size images.

---

## Layout

```
Errands/
  docker-compose.yml        mariadb 11 + wordpress php8.3-apache + wp-cli
  .env / .env.example       local credentials and port (.env is gitignored)
  config/uploads.ini        PHP limits (64M uploads, 512M memory)
  setup.sh                  full rebuild, idempotent
  start.bat / stop.bat      Windows convenience wrappers
  import/
    scrape.js               pulls posts + media from the live site
    posts.json              raw scrape of the 22 posts (RSS content:encoded)
    clean.js                strips 2011 HTML -> import.json
    import.json             generated: prose + ordered media per project
    import.php              creates the WordPress content (via wp-cli)
    make-icon.php           rasterises the mark into PNG icons (GD)
  wp-content/
    themes/errands/         the theme (bind-mounted, edit from Windows)
    uploads/                196 originals + 1 PDF, plus WP derivatives
```

`wp-content/uploads/` is **gitignored** — it is ~300MB of originals before
WordPress adds its derivative sizes. Rebuild it from the live site with:

```bash
node import/scrape.js --media     # skips anything already downloaded
```

WordPress core and the database live in Docker named volumes (`wp_core`,
`db_data`). The theme and uploads are bind-mounted from this folder so they can
be edited and browsed directly from Windows — the mount is world-writable under
WSL, so WordPress can still generate thumbnails into it.

---

## Identity

The original logo was a 461×70 JPEG of flat text. Replaced with a drawn mark: an
ordered square frame with one solid volume displaced out of its corner — the
group's own subject, the thing that will not sit inside the system. Chunky
strokes on a 32-unit grid so it holds up at 16px in a browser tab.

- `inc/placeholder.php` → `errands_mark_svg()` — inline in the header, so it
  follows the palette. The displaced volume slides further out on hover.
- `assets/favicon.svg` — standalone, with its own `prefers-color-scheme` block
  since a favicon cannot inherit the page's CSS.
- `assets/favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png`,
  `icon-512.png` — same geometry drawn with GD for the slots that will not take
  an SVG. Regenerate with:

```bash
docker compose run --rm cli eval-file /import/make-icon.php
```

Theme icon tags step aside automatically if a Site Icon is set in
**Settings → General**.

## Generated covers

One project has no photograph, and the WordPress default would be a grey box.
Instead `errands_placeholder_svg()` composes a drawing: a blueprint grid, an
improvised skyline of outlined volumes with floor lines, cantilevered add-ons,
a halftone field, a ground line and one red mass.

The composition is seeded from the project slug, so a given project always gets
the same drawing and no two get the same one. It is line art built from CSS
custom properties, so it follows light/dark like everything else, and it renders
at four canvas ratios (`card`, `wide`, `hero`, `tile`) for the grid, the single
view and the index rail.

**It also covers failed image loads.** The drawing is always present in the
markup, sitting behind the photograph; if the image cannot be loaded, JS adds
`.is-broken` and the drawing takes over, so a tile is never blank. It has to be
inline rather than fetched on demand, because the usual reason an image fails is
that the server has gone away — at which point fetching a replacement would fail
too. Detection is a capture-phase `error` listener (resource errors do not
bubble) plus a `naturalWidth === 0` sweep for anything that failed before the
script ran.

## How the content was migrated

1. **Scrape** (`scrape.js`). WordPress 4.4 predates the REST content endpoints,
   so the posts came from the RSS feed's `content:encoded` (3 pages, 22 items).
2. **Media.** Every referenced image URL was mapped back to its original
   full-size file by stripping the `-WxH` suffix, then downloaded preserving the
   `YYYY/MM` path — 196 images plus one PDF, no failures.
3. **Clean** (`clean.js`). Removes Word conditional-comment junk (one post
   carried an entire Word stylesheet), inline `<img>` tags and their lightbox
   wrappers, `<span>`/`<font>` cruft, hardcoded dimensions and `&nbsp;`
   paragraphs. Emits prose plus the ordered image list per project.
4. **Import** (`import.php`). Creates the projects, registers the on-disk files
   as real attachments without copying them, generates thumbnails, sets the
   first image as the cover, assigns series, and builds the pages and menu.

Re-running is safe: each object records its origin in `_errands_legacy_id` /
`_errands_src` and is skipped if present. To rebuild the content:

```bash
docker compose run --rm -e ERRANDS_FORCE=1 cli eval-file /import/import.php
```

### Two things worth knowing

- **One project has no images.** `IMPROVISED CITY 8 – THE DEAD MAN'S BUTTON`
  had none on the original site either — it is text-only, not a failed import.
  The card falls back to a hatched placeholder.
- **Two odd media references** in the original content were handled explicitly:
  one relative URL (`../wp-content/uploads/…`) that an absolute-URL scraper
  misses, and one image hotlinked from another domain that happens to also exist
  in the group's own uploads. The cleaner only treats a foreign-domain URL as
  local when that exact file really is present, and reports it otherwise.

---

## Deployment

**This does not run on Vercel.** Vercel serves static files and serverless
JS/Python functions — there is no PHP runtime and no MySQL, so WordPress cannot
run there in any configuration.

Realistic options:

| Option | Notes |
| --- | --- |
| **Static export → Vercel** | Good fit. The site is read-only: comments are closed, no forms, no logins. Crawl the local site to flat HTML and deploy that. The one casualty is search, which is PHP — it would be replaced by a small client-side JSON index. Not implemented yet. |
| **Managed WordPress host** | Keeps the dashboard and search working with no code changes. The usual choice for handing a site back to non-developers. |
| **VPS with this compose file** | Closest to what is running locally. Add a reverse proxy and TLS, and move the credentials out of `.env`. |

Nothing about the theme is host-specific, so any of these works from the same
codebase.

## Editing the theme

Files are under `wp-content/themes/errands/` and take effect on reload — no build
step, no dependencies.

| File | Role |
| --- | --- |
| `style.css` | all styling; design tokens at the top |
| `functions.php` | post type, taxonomy, image sizes, gallery helpers |
| `front-page.php` | statement + selected work |
| `archive-project.php` | full archive with filters (also serves series) |
| `single-project.php` | hero, prose, facts, gallery, prev/next |
| `template-index.php` | page template: everything by year |
| `assets/js/main.js` | lightbox, filters, theme toggle, search overlay |

The hero copy is editable under **Appearance → Customize → ERRANDS front page**
rather than hardcoded.

## Troubleshooting

**WordPress shows "Database Error" even though the database is fine.**

WSL shuts its distro down when nothing holds it open. Docker then brings the
containers back on its own through `restart: unless-stopped` — but that policy
restarts each container independently and ignores the `depends_on:
service_healthy` ordering a real `compose up` honours. Two things follow:

- Apache can come back before MariaDB accepts connections, and
- if the database container is recreated on a new IP, the running PHP workers
  keep the old address cached, so every request fails while `wp-cli` and
  `docker compose exec` still connect perfectly.

That last asymmetry is the tell: if wp-cli works and the browser does not, it is
this. `up.sh` handles it by waiting for the health check and then bouncing the
web container so PHP re-resolves `db`. To fix it by hand:

```bash
docker compose restart wordpress
```

**Rapid requests from Windows return connection failures (`000`).** WSL2's
localhost forwarding drops under bursts of sequential connections. The site
itself is fine — test from inside WSL, or slow the requests down.

### Notes

- `wp eval-file` includes the script inside a *function* scope, so top-level
  variables in `import.php` are not globals. `errands_uploads_dir()` exists for
  that reason — a `global $uploads_dir` there reads null and every attachment
  silently fails.
- `WP_DEBUG` is defined by the Docker image from `WORDPRESS_DEBUG`. Redefining it
  in `WORDPRESS_CONFIG_EXTRA` emits a PHP warning on every request.
- `chmod(): Operation not permitted` from wp-cli is a harmless artifact of the
  Windows drvfs mount.
