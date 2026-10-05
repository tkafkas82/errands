# Deploying ERRANDS to www.errands.gr

The **dynamic** deployment: real WordPress, wp-admin works, the collective can
log in and edit. The static export is kept as Appendix A, but it is not the plan.

Works on either panel. **Plesk is given first**, because errands.gr is on
Top.Host (formerly GRserver), which runs Plesk — cPanel follows in the same
step. Panels differ only in where the buttons are; the WordPress work is
identical.

Staying on the current host removes the riskiest part of this: **the domain
already resolves there, so there is no DNS change, no TTL lowering and no
waiting.** It also means Plesk can run the new site on PHP 8.3 while the old
WordPress 4.4.18 keeps its old PHP until you flip one setting.

Build the package first:

```powershell
cd "C:\Users\akafka\OneDrive - Netcompany\Documents\GitHub\errands"
node tools/build-media.js         # assemble the media library — done, 197/197
node tools/package-deploy.js      # build deploy/
```

---

## Panel cheat sheet

| Task | Plesk | cPanel |
| --- | --- | --- |
| PHP version | Websites & Domains → *Hosting Settings* (or *PHP Support*) | MultiPHP Manager |
| Add a subdomain | Websites & Domains → *Add Subdomain* | Domains → *Create A New Domain* |
| Install WordPress | WordPress Toolkit → *Install* | Softaculous / WordPress Manager |
| Upload & unzip | File Manager → *Extract Files* | File Manager → *Extract* |
| Show dotfiles | File Manager → gear → *Show hidden files* | Settings → *Show Hidden Files* |
| Document root | Websites & Domains → *Hosting Settings* → Document root | Domains → edit → Document Root |
| Database tool | Databases → *phpMyAdmin* | phpMyAdmin |
| Free SSL | SSL/TLS Certificates → *Let's Encrypt* | SSL/TLS Status → *Run AutoSSL* |
| Force HTTPS | Hosting Settings → *Permanent SEO-safe 301 redirect* | SSL/TLS Status → *Force HTTPS Redirect* |
| Shell | Web Hosting Access → *SSH* → `/bin/bash` | Terminal, or SSH Access |
| Backups | Backup Manager → *Back Up* | Backup / JetBackup |
| Permissions | File Manager → right-click → *Change Permissions* | File Manager → Permissions |

---

## What goes to the host

| | |
| --- | --- |
| `deploy/wordpress/errands-wordpress.zip` | 27 files, 0.2 MB. Extract **at the WordPress root** — the theme, the two mu-plugins and the importer all land where they belong. |
| `wp-content/uploads/` | 197 originals, 239 MB. Upload over SFTP/FTP. |

Inside the zip:

```
wp-content/
  themes/errands/                    the theme — 23 files, no build step, no plugins
  mu-plugins/
    errands-import-runner.php        creates the content without a shell. TEMPORARY.
    errands-legacy-redirects.php     301s the old ?p=<id> URLs. Permanent.
  errands-import/
    import.php  import.json          21 projects, the exact media list per project
```

`mu-plugins/` loads automatically and cannot be deactivated by accident — what
you want for the redirects, and convenient for the runner.

### Why the media is assembled rather than copied from the export

`errandsDist` holds 1122 files, but they are what the *pages reference*, not a
media library. Against the 197 originals the importer needs: **128 present, 61
only as the `-scaled` copy** WordPress makes of anything wider than 2560px, and
**8 absent** — only their derivatives were ever referenced. Point the importer
at that folder and it reports `not on disk` 69 times, and you bake a lossy
ceiling into a site that may want to re-crop later.

`tools/build-media.js` seeds the 128 real originals out of the export and pulls
the other 69 from the old site, which is still serving them. Already run: **197
of 197, 239.2 MB, complete.** Re-runnable; it skips what is there.

---

## 1. Check four things before touching anything

**a. PHP version available.**

> **Plesk** Websites & Domains → *Hosting Settings* → PHP version. Plesk sets
> this **per domain and per subdomain**, which is the whole reason the staging
> approach below is safe.
> **cPanel** MultiPHP Manager.

Target **8.2** or **8.3**. Top.Host documents running the current PHP with
version selection available. Nothing here actually needs 8.x — the theme and
importer use no PHP-8-only syntax, despite the conservative `Requires PHP: 8.0`
header — but do not deploy onto 5.6 or 7.0.

**b. Disk quota.** You need ~700 MB free: 239 MB of originals plus the
derivatives WordPress builds in seven sizes, including a 2200×1400 hero. Any
Top.Host Linux plan (8–40 GB) is ample. Check the subscription overview.

**c. Shell access.** Top.Host includes **SSH on Linux packages**, so §7a applies
and the import is one command.

> **Plesk** Web Hosting Access → *Access to the server over SSH* → `/bin/bash`
> **cPanel** Terminal, or SSH Access

If there is no shell, §7b does the same job from wp-admin.

**d. Where the old site lives.** WordPress 4.4.18 is still serving errands.gr.
**Do not install over it.** Build at a staging location and flip at the end
(§9). A subdomain is cleanest, because the flip is then a document-root change
rather than a site-URL change:

> **Plesk** Websites & Domains → *Add Subdomain* → `new` → document root
> `/httpdocs/new` (or its own folder). Then set **that subdomain** to PHP 8.3
> while `errands.gr` keeps whatever the old site needs.
> **cPanel** Domains → *Create A New Domain* → `new.errands.gr`.

## 2. Back up the old site

> **Plesk** Backup Manager → *Back Up* → include *User files and databases* →
> then download it. Plesk's WordPress Toolkit can also snapshot the old install
> on its own.
> **cPanel** Backup → *Download a Home Directory Backup* and *Download a MySQL
> Database Backup*; or JetBackup → full account backup.

Keep it off the server. The content is already scraped into this repo, but the
old install also holds the 2011-era theme and the original database, and nothing
in this procedure can put those back.

## 3. Install WordPress at the staging location

> **Plesk** WordPress Toolkit → *Install* → pick the `new.errands.gr`
> subdomain. Uncheck any plugin/theme set it offers to add — this site needs
> none.
> **cPanel** Softaculous / WordPress Manager → Install into the staging
> directory, **not** the live one.

Site name `ERRANDS` (the importer sets this anyway), your own admin user, a
strong password, your email.

Then add to `wp-config.php`, above the `/* That is all */` line:

```php
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'FS_METHOD', 'direct' );
```

Leave `DISALLOW_FILE_EDIT` until after the import.

## 4. Upload the files

**The zip.** Upload `deploy/wordpress/errands-wordpress.zip` into the WordPress
root, then extract it in place.

> **Plesk** File Manager → navigate into the subdomain's root → *Upload* →
> right-click the zip → *Extract Files* → tick *Replace existing files*.
> **cPanel** File Manager → Upload → right-click → *Extract*.

It merges into `wp-content/`: files are added or replaced, nothing else is
touched. Turn on *Show hidden files* if you want to confirm what arrived.

**The media.** SFTP is the right tool for 197 files. FileZilla → upload
`wp-content/uploads` into the WordPress root's `wp-content/`.

- transfer type **binary**
- **force UTF-8** — Site Manager → Charset → *Force UTF-8* — or the three Greek
  filenames (`κάτοψη-ισογείου-νέα-t.jpg`, `Φωτογραφίες-2-t.jpg`, `ΦΕΚ-1.pdf`)
  arrive mangled and the importer reports them `not on disk`

Directories `755`, files `644`. Expect **8 month folders**: `2011/03`,
`2012/05`, `2012/11`, `2013/05`, `2013/11`, `2014/03`, `2015/02`, `2015/09`.

## 5. Activate the theme

**Appearance → Themes → ERRANDS → Activate.**

**Do this before the import.** The `project` post type and the `project_series`
taxonomy are registered in the theme's `functions.php`. With another theme
active, the importer creates 21 posts of a type WordPress does not know about:
present in the database, 404 on the site. The runner in §7b checks this and
warns you; wp-cli in §7a does not.

## 6. Set the permalink structure

**Settings → Permalinks → Post name → Save.** The importer sets the option too,
but saving this page is what writes the rewrite rules.

**On Plesk, check this actually took effect.** Top.Host fronts Apache with
nginx. That is fine as long as Plesk is proxying to Apache, because Apache is
what reads `.htaccess`. If nginx is serving directly, `.htaccess` is ignored and
pretty permalinks 404 no matter how often you re-save:

> **Plesk** Websites & Domains → *Apache & nginx Settings* → leave **Proxy
> mode** enabled. If you must run nginx-only, the rewrite has to move into
> *Additional nginx directives* instead.

This is the single most common Plesk-specific WordPress failure, and it looks
exactly like a broken permalink setting.

## 7. Create the content

197 attachments, each resized into seven sizes, some from 6 MB originals.

### 7a. With SSH — the path for this host

```bash
cd ~/httpdocs/new                         # the WordPress root
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info                    # confirm PHP 8.x and the WP version
php wp-cli.phar eval-file wp-content/errands-import/import.php
```

If `php` is an old binary, call the versioned one. On Plesk the PHP builds live
under `/opt/plesk/php/8.3/bin/php`; on cPanel,
`/opt/cpanel/ea-php83/root/usr/bin/php`.

Expected: 2 series terms, 21 projects, **197 media attached**, the About /
Exhibitions / Index pages, the Primary menu, `no problems`. Idempotent — a
re-run skips what exists. To rebuild from scratch:
`ERRANDS_FORCE=1 php wp-cli.phar eval-file …`.

Then delete `wp-cli.phar`. CLI has no execution-time limit, so there is no
second pass.

### 7b. Without a shell — the runner

**Tools → ERRANDS import.** Three numbered steps on one page.

1. **Content.** Projects, pages, menu and the 197 attachment *records*, with
   image derivatives switched off — nothing is decoded, so it finishes in
   seconds instead of dying at `max_execution_time`. Safe to re-run.
2. **Derivatives.** The sizes, 5 attachments per request, continuing on its own
   until the queue drains — roughly 10–25 minutes unattended, and **resumable**:
   each finished attachment is marked, so an interrupted run never repeats work.
   If the host kills requests early, lower the batch in `wp-config.php`:
   `define( 'ERRANDS_SIZE_BATCH', 2 );`
3. **Audit.** Expected against actual attachments for all 21 projects, plus a
   count of files not found on disk. This catches the one failure that hides
   itself: a content pass interrupted halfway leaves a project that *exists*, so
   a re-run skips it, permanently short of images. **Clear incomplete
   project(s)** deletes just those records — files on disk untouched — so step 1
   rebuilds them.

Work down the page until the audit says every project matches.

**Then delete the runner** — `wp-content/mu-plugins/errands-import-runner.php`
and the whole `wp-content/errands-import/` folder. Keep
`errands-legacy-redirects.php`.

## 8. Verify

The importer sets the title, the tagline *Re-animating failed utopias*,
`Europe/Athens`, date format `j F Y`, closed comments and `/%postname%/`
permalinks. Then:

1. **Settings → Permalinks → Save Changes** again, now that the post type
   exists — this is what makes `/projects/` resolve. Skip it and everything
   except the home page 404s. If they still 404, see the proxy-mode note in §6.
2. Walk the routes: `/`, `/projects/`, `/projects/<slug>/`,
   `/series/improvised-city/`, `/index/`, `/exhibitions/`, `/about/`, and a
   deliberate 404.
3. Check the JavaScript: the lightbox (arrows, Esc, swipe), the archive's series
   and year filters, the search overlay, the light/dark toggle.
4. **Appearance → Menus** — Primary assigned to the *Primary menu* location with
   Projects / Exhibitions / Index / About.
5. **Appearance → Customize → ERRANDS front page** — the hero copy lives here,
   not in the template.
6. **Media → Library** should show 197 items. Spot-check the PDF and a
   Greek-named image at full size.
7. `IMPROVISED CITY 8 – THE DEAD MAN'S BUTTON` has no photographs on purpose and
   draws a generated cover. A drawing is correct; a blank tile is a broken image.

## 9. Point www.errands.gr at it

### Same host — the case here

No DNS work at all. Repoint the domain's document root at the new install:

> **Plesk** Websites & Domains → `errands.gr` → *Hosting Settings* → set
> **Document root** to the staging folder, and set **PHP version** to 8.3 on the
> main domain too. Move the old install's files aside first (File Manager →
> create `old-site/` and drag `wp-admin`, `wp-includes`, `wp-content`,
> `wp-*.php`, `.htaccess` into it) so nothing stale is served.
> **cPanel** Domains → edit `errands.gr` → Document Root.

Then fix the two URLs — with SSH:

```bash
php wp-cli.phar search-replace 'https://new.errands.gr' 'https://www.errands.gr' --all-tables --precise
```

Without one: **Settings → General** → set *WordPress Address* and *Site Address*
to `https://www.errands.gr`, save, log in again, then run **Better Search
Replace** once over the same pair to catch URLs inside post content and
serialized options. Attachment URLs live in the database, so this is not
optional — skip it and every image still points at the staging subdomain.

Finally remove the staging subdomain so the site is not reachable at two
addresses.

### Different host — if you ever move

Lower the TTL a day ahead at the registrar, then point `@` and `www` at the new
host's IP. The nameservers are currently the host's (`ns211/ns212.grserver.gr`),
so moving hosts means changing nameservers at the registrar. Keep the old
account alive for a week.

### SSL, either way

> **Plesk** SSL/TLS Certificates → *Install a free basic certificate provided by
> Let's Encrypt* → include `www`. Then Hosting Settings → **Permanent SEO-safe
> 301 redirect from HTTP to HTTPS**.
> **cPanel** SSL/TLS Status → *Run AutoSSL* → wait → **Force HTTPS Redirect**.

Then the non-www canonical, above `# BEGIN WordPress` in `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP_HOST} ^errands\.gr$ [NC]
RewriteRule ^(.*)$ https://www.errands.gr/$1 [R=301,L]
```

Check `https://errands.gr/?p=875` lands on `/projects/legalizing-architecture/`.
That is `errands-legacy-redirects.php` working, and it covers all 22 old
permalinks — they are in print and in biennial catalogues.

## 10. After launch

- **`define( 'DISALLOW_FILE_EDIT', true );`** in `wp-config.php` now the theme
  is in place.
- **Search Console** — submit `https://www.errands.gr/`. No sitemap plugin
  needed; WordPress 5.5+ serves `/wp-sitemap.xml` itself.
- **Backups.** The host's, plus something off-server. The database is now the
  only copy of the live content; the repo holds the 2015 snapshot it was built
  from.
- **Updates.** Core, and that is the whole list — no plugins beyond the two
  mu-plugins, and nothing depends on a plugin API.
- **Theme edits** are SFTP under `wp-content/themes/errands/`: no build step, no
  dependencies. Change them in this repo first and commit, or the next
  deployment loses the edit.
- **Caching.** Top.Host's SpeedCache is enough. Avoid plugins that concatenate
  or defer scripts — they break the lightbox.

## What tends to go wrong

| Symptom | Cause |
| --- | --- |
| Everything except the home page 404s | Permalinks not re-saved after the post type existed (§8.1) |
| …and re-saving does not fix it | On Plesk, nginx serving directly so `.htaccess` is ignored — proxy mode (§6) |
| Projects imported but invisible | The theme was not active when the importer ran (§5) |
| `not on disk: 2015/09/…` | Media missing, or a Greek filename mangled by a non-UTF-8 transfer (§4) |
| The derivatives pass stalls or 500s | Host killing long requests: lower `ERRANDS_SIZE_BATCH` and click again — progress is kept |
| A project is short of images and a re-run skips it | Interrupted content pass. Audit → **Clear incomplete**, then step 1 (§7b.3) |
| Images still point at the staging subdomain | The search-replace in §9 was skipped |
| Thumbnails soft or missing | Derivatives pass never finished — check the audit, or `media regenerate` |
| Mixed-content warnings | `http://` URLs inside post content — one more search-replace pass |

---

# Appendix A — the static fallback

`deploy/site/` is the same site as a flat file tree: 39 files, 1.4 MB plus the
media, no PHP, no database, no wp-admin. Same URLs as the dynamic site, so
switching between them costs no redirects. Useful if the host turns out too slow
for the derivatives pass, or as a holding page.

`tools/package-deploy.js` corrects four things the raw export gets wrong on a
real domain, on every build, so a future `export.sh` cannot lose them:

- **`robots.txt` said `Disallow: /`** — deliberate for a preview, catastrophic
  as errands.gr. Now crawlable, with a 28-URL `sitemap.xml`.
- **The oEmbed tags leaked the local origin**, percent-encoded as
  `url=http%3A%2F%2Flocalhost%3A8080%2F…`, on 24 pages. `export.sh` checks for
  the literal string `localhost:` and never saw them. 164 dead feed and REST
  tags stripped.
- **Every canonical pointed at a file, not a URL** — `wget --convert-links` left
  `href="index.html"`, so `/about/` declared `/about/index.html` canonical,
  splitting the ranking signals of a domain indexed since 2011. All 28 are now
  absolute.
- **The footer advertises an RSS feed that did not exist.** Generated from the
  same content the importer uses: 21 items at `/feed/`, served through
  `DirectoryIndex`.

Its `.htaccess` carries the 404 page, a proxy-safe HTTPS canonical, cache
headers and the same 22 legacy `?p=` redirects. Verified: 1135 local references,
0 missing, 0 `localhost`, both XML files parse, every route returns the right
status and content type.

Deploy it by extracting `deploy/errands-static-site.zip` into the document root
(no wrapper folder) and putting the media at `wp-content/uploads/`.

**This route depends entirely on `.htaccess`,** so the Plesk proxy-mode note in
§6 is not optional here: nginx-only would silently drop the 404 page, the
redirects and the `/feed/` DirectoryIndex.

# Appendix B — if you ever do move host

Compared in September 2026, for a ~700 MB read-only site:

- **Papaki** — same group as Top.Host. Greek datacentre and support, SSH on all
  plans, daily backups with 30-day history. ~€2.48/mo promotional, renewing
  around €90.90/year.
- **Krystal** (UK) — from £3.99/mo with no renewal hike, WP-CLI and SSH, daily
  backups, B-Corp. Predictable rather than cheapest.
- **Hostinger** — $2.99/mo only if you prepay 48 months, renewing at $10.99.
  SSH is Business-tier, which §7b makes unnecessary.

Avoid intro-price traps (ChemiCloud is $2.95 rising to $13.95 at renewal), free
tiers, and VPS like Hetzner or Netcup — cheap, but you become the sysadmin.
Compare renewal prices, and expect 24% Greek VAT on ex-VAT figures.
