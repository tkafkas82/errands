/**
 * Package the site for the live host at www.errands.gr.
 *
 * Two things ship from here, because there are two ways to run this site live:
 *
 *   deploy/site/            the static export, corrected for a real domain —
 *                           upload to the document root, no PHP, no database
 *   deploy/wordpress/       theme + importer zips for a real WordPress install
 *
 * The static export itself is generated output (export.sh -> ../errandsDist)
 * and is overwritten on every publish, so the corrections live here rather than
 * in it: a crawlable robots.txt, a sitemap, and an .htaccess carrying the 404
 * page, the canonical host and 301s for the old ?p=<id> permalinks.
 *
 * Usage:
 *   node tools/package-deploy.js
 *   node tools/package-deploy.js --dist ../errandsDist --host www.errands.gr
 *   node tools/package-deploy.js --media      # also copy the 205MB media set
 *
 * The media is left out by default. It is 1122 files the static export already
 * holds on disk, FTP is the sane way to move it, and copying it would duplicate
 * 205MB inside OneDrive.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');

const args = process.argv.slice(2);
const flag = (name, fallback) => {
  const i = args.indexOf('--' + name);
  return i !== -1 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : fallback;
};

const HOST = flag('host', 'www.errands.gr');
const DIST = path.resolve(ROOT, flag('dist', path.join('..', 'errandsDist')));
const OUT = path.join(ROOT, 'deploy');
const SITE = path.join(OUT, 'site');
const WPDIR = path.join(OUT, 'wordpress');
const WITH_MEDIA = args.includes('--media');

const UPLOADS = path.join('wp-content', 'uploads');

/* Files in the export that belong to the dist repository, not to the site. */
const SKIP = new Set(['.git', '.gitattributes', '.nojekyll', 'README.md', 'robots.txt']);

const log = (...m) => console.log(...m);
const die = m => {
  console.error('\n  ' + m + '\n');
  process.exit(1);
};

/* ------------------------------------------------------------------ */
/* Checks                                                              */
/* ------------------------------------------------------------------ */

if (!fs.existsSync(path.join(DIST, 'index.html'))) {
  die(`No static export at ${DIST}\n  Run ./export.sh first, or pass --dist <path>.`);
}

/* ------------------------------------------------------------------ */
/* Copy the export, minus the media                                    */
/* ------------------------------------------------------------------ */

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(SITE, { recursive: true });
fs.mkdirSync(WPDIR, { recursive: true });

let copied = 0;

function copyTree(from, to, rel = '') {
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    const r = rel ? rel + '/' + entry.name : entry.name;

    if (!rel && SKIP.has(entry.name)) continue;
    if (!WITH_MEDIA && r === UPLOADS.replace(/\\/g, '/')) continue;

    const src = path.join(from, entry.name);
    const dst = path.join(to, entry.name);

    if (entry.isDirectory()) {
      fs.mkdirSync(dst, { recursive: true });
      copyTree(src, dst, r);
    } else {
      fs.copyFileSync(src, dst);
      copied++;
    }
  }
}

copyTree(DIST, SITE);
log(`site       ${copied} files copied from ${path.relative(ROOT, DIST)}`);

/* ------------------------------------------------------------------ */
/* Strip the endpoints a static build cannot answer                    */
/* ------------------------------------------------------------------ */

/*
 * Three things in every <head> do not survive the move to a static host:
 *
 * 1. Feed and REST autodiscovery — /feed/, /comments/feed/, the nested
 *    /projects/feed/ and /series/<slug>/feed/, /wp-json/… and the oEmbed
 *    endpoints. Nothing serves them. .htaccess still redirects the URLs for
 *    anyone who saved one, but the tags go: an advertised feed that answers
 *    with HTML is worse than no feed, because a reader subscribes and then
 *    reports an error.
 *
 * 2. The oEmbed hrefs carry the local origin percent-encoded
 *    (`url=http%3A%2F%2Flocalhost%3A8080%2F…`). export.sh checks for the
 *    literal string `localhost:` and so never saw these.
 *
 * 3. `wget --convert-links` rewrote every canonical into a relative *file*
 *    path — `<link rel="canonical" href="index.html">`. On /about/ that
 *    resolves to /about/index.html, a different URL from the one the world
 *    links to, which splits the ranking signals of a domain that has been
 *    indexed since 2011. They are rewritten to absolute URLs on the real host,
 *    and added to the pages the crawler left without one.
 */
const DEAD_TAGS = [
  // Any <link rel="alternate"> pointing at a feed or the REST API, whatever
  // the attribute order: rss+xml, json+oembed, text/xml+oembed, plain JSON.
  /[ \t]*<link[^>]*rel="alternate"[^>]*href="(?:[^"]*\/feed\/|\/wp-json\/[^"]*)"[^>]*>\r?\n?/g,
  /[ \t]*<link[^>]*rel="https:\/\/api\.w\.org\/"[^>]*>\r?\n?/g
];

let stripped = 0;
let canonicals = 0;

function htmlFiles(dir) {
  let out = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) out = out.concat(htmlFiles(p));
    else if (e.name.endsWith('.html')) out.push(p);
  }
  return out;
}

const pages = htmlFiles(SITE);

/** The public URL a packaged file is served at. */
function routeOf(file) {
  const rel = path.relative(SITE, file).split(path.sep).join('/');
  if (rel === 'index.html') return '/';
  if (rel.endsWith('/index.html')) return '/' + rel.slice(0, -'index.html'.length);
  return '/' + rel;
}

for (const file of pages) {
  const before = fs.readFileSync(file, 'utf8');
  let after = before;

  for (const re of DEAD_TAGS) {
    after = after.replace(re, () => {
      stripped++;
      return '';
    });
  }

  // 404.html is not a canonical anything.
  if (path.basename(file) !== '404.html') {
    const tag = `<link rel="canonical" href="https://${HOST}${routeOf(file)}" />`;

    if (/<link[^>]*rel="canonical"[^>]*>/.test(after)) {
      after = after.replace(/<link[^>]*rel="canonical"[^>]*>/, tag);
    } else {
      after = after.replace('</head>', `\t${tag}\n</head>`);
    }
    canonicals++;
  }

  // Autodiscovery for the one feed that does exist here (written below).
  after = after.replace(
    '</head>',
    `\t<link rel="alternate" type="application/rss+xml" title="ERRANDS &raquo; Feed" href="https://${HOST}/feed/" />\n</head>`
  );

  if (after !== before) fs.writeFileSync(file, after);
}

log(`head       ${stripped} dead feed/REST tags stripped, ${canonicals} canonicals set, across ${pages.length} pages`);

/* ------------------------------------------------------------------ */
/* robots.txt — the export ships "Disallow: /" on purpose              */
/* ------------------------------------------------------------------ */

fs.writeFileSync(
  path.join(SITE, 'robots.txt'),
  [
    'User-agent: *',
    'Allow: /',
    '',
    `Sitemap: https://${HOST}/sitemap.xml`,
    ''
  ].join('\n')
);
log('robots.txt written (crawlable — the export blocks all crawlers)');

/* ------------------------------------------------------------------ */
/* sitemap.xml                                                         */
/* ------------------------------------------------------------------ */

/** Every page in the export, as a site-root path. */
function routes(dir, rel = '') {
  let out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === UPLOADS.split(path.sep)[0] && !rel) continue; // wp-content
    const r = rel ? rel + '/' + entry.name : entry.name;
    if (entry.isDirectory()) {
      out = out.concat(routes(path.join(dir, entry.name), r));
    } else if (entry.name === 'index.html') {
      out.push('/' + (rel ? rel + '/' : ''));
    }
  }
  return out;
}

const urls = routes(SITE).sort((a, b) => a.length - b.length || a.localeCompare(b));

const stamp = new Date().toISOString().slice(0, 10);
const priority = u => (u === '/' ? '1.0' : u === '/projects/' ? '0.9' : '0.7');

fs.writeFileSync(
  path.join(SITE, 'sitemap.xml'),
  '<?xml version="1.0" encoding="UTF-8"?>\n' +
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
    urls
      .map(
        u =>
          '\t<url>\n' +
          `\t\t<loc>https://${HOST}${u}</loc>\n` +
          `\t\t<lastmod>${stamp}</lastmod>\n` +
          `\t\t<priority>${priority(u)}</priority>\n` +
          '\t</url>\n'
      )
      .join('') +
    '</urlset>\n'
);
log(`sitemap    ${urls.length} URLs`);

/* ------------------------------------------------------------------ */
/* feed/index.xml                                                      */
/* ------------------------------------------------------------------ */

/*
 * The theme's footer advertises an RSS feed on every page, and WordPress served
 * one at /feed/. A static crawl cannot, so that link would 404 on all 29 pages.
 * Rather than cut a feature the design offers, the feed is written out here from
 * the same content the importer uses. Served as index.xml via DirectoryIndex.
 */
const importJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'import', 'import.json'), 'utf8'));

const xml = s =>
  String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const items = importJson.projects
  .slice()
  .sort((a, b) => (a.date < b.date ? 1 : -1))
  .map(p => {
    const link = `https://${HOST}/projects/${p.slug}/`;
    return (
      '\t<item>\n' +
      `\t\t<title>${xml(p.title)}</title>\n` +
      `\t\t<link>${link}</link>\n` +
      `\t\t<guid isPermaLink="true">${link}</guid>\n` +
      `\t\t<pubDate>${new Date(p.date.replace(' ', 'T') + 'Z').toUTCString()}</pubDate>\n` +
      // A literal ]]> inside the excerpt would close the CDATA early; the fix
      // is to split it across two sections.
      `\t\t<description><![CDATA[${(p.excerpt || '').replace(/]]>/g, ']]]]><![CDATA[>')}]]></description>\n` +
      '\t</item>\n'
    );
  });

fs.mkdirSync(path.join(SITE, 'feed'), { recursive: true });
fs.writeFileSync(
  path.join(SITE, 'feed', 'index.xml'),
  '<?xml version="1.0" encoding="UTF-8"?>\n' +
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">\n' +
    '<channel>\n' +
    '\t<title>ERRANDS</title>\n' +
    `\t<link>https://${HOST}/</link>\n` +
    '\t<description>Re-animating failed utopias</description>\n' +
    '\t<language>en</language>\n' +
    `\t<lastBuildDate>${new Date().toUTCString()}</lastBuildDate>\n` +
    `\t<atom:link href="https://${HOST}/feed/" rel="self" type="application/rss+xml" />\n` +
    items.join('') +
    '</channel>\n</rss>\n'
);
log(`feed       ${items.length} items at /feed/`);

/* ------------------------------------------------------------------ */
/* .htaccess                                                           */
/* ------------------------------------------------------------------ */

/**
 * The old site's permalinks were https://errands.gr/?p=<id>. Those URLs are in
 * print and on other people's pages. The importer recorded every old id, so the
 * mapping is exact rather than guessed.
 */
const legacy = importJson.projects.map(p => [p.legacy_id, `/projects/${p.slug}/`]);
if (importJson.about) legacy.push([importJson.about.legacy_id, '/about/']);
legacy.sort((a, b) => a[0] - b[0]);

const htaccess = `# ERRANDS — static site at https://${HOST}/
# Generated by tools/package-deploy.js. Re-generate rather than editing here.

Options -Indexes
# index.xml is what makes /feed/ serve the generated RSS.
DirectoryIndex index.html index.xml

ErrorDocument 404 /404.html

<IfModule mod_rewrite.c>
RewriteEngine On

# Canonical scheme. The X-Forwarded-Proto check matters behind a CDN or a
# proxying load balancer, where %{HTTPS} reads "off" on an HTTPS request and a
# bare check loops forever. If the host's own "Force HTTPS Redirect" toggle is
# already on, drop this first rule rather than stacking two redirect chains.
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# Canonical host.
RewriteCond %{HTTP_HOST} !^${HOST.replace(/\./g, '\\.')}$ [NC]
RewriteRule ^(.*)$ https://${HOST}/$1 [R=301,L]

# The 2011 site's permalinks: /?p=<id> -> the rebuilt page.
# The trailing "?" on each target drops the old query string.
${legacy
  .map(
    ([id, url]) =>
      `RewriteCond %{QUERY_STRING} ^p=${id}$\nRewriteRule ^$ ${url}? [R=301,L]`
  )
  .join('\n')}

# WordPress endpoints the static build cannot answer, for anyone who saved one.
# /feed/ is excluded — it is a real file here — but the per-archive feeds
# WordPress used to serve (/projects/feed/, /series/<slug>/feed/) fold into it.
RewriteRule ^(.+)/feed/?$ /feed/ [R=301,L]
RewriteRule ^comments/feed/?$ /feed/ [R=301,L]
RewriteRule ^wp-json(/.*)?$ / [R=301,L]
RewriteRule ^xmlrpc\\.php$ / [R=301,L]
</IfModule>

<IfModule mod_expires.c>
ExpiresActive On
# Media filenames carry their dimensions, so a long life is safe.
ExpiresByType image/jpeg              "access plus 1 year"
ExpiresByType image/png               "access plus 1 year"
ExpiresByType image/svg+xml           "access plus 1 year"
ExpiresByType application/pdf         "access plus 1 month"
ExpiresByType text/css                "access plus 1 week"
ExpiresByType application/javascript  "access plus 1 week"
ExpiresByType text/html               "access plus 1 hour"
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml application/xml
</IfModule>

<IfModule mod_headers.c>
Header set X-Content-Type-Options "nosniff"
Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
`;

fs.writeFileSync(path.join(SITE, '.htaccess'), htaccess);
log(`.htaccess  404 page, canonical host, ${legacy.length} legacy 301s, cache headers`);

/* ------------------------------------------------------------------ */
/* Media                                                               */
/* ------------------------------------------------------------------ */

if (WITH_MEDIA) {
  const n = fs.readdirSync(path.join(SITE, UPLOADS), { recursive: true, withFileTypes: true })
    .filter(e => e.isFile()).length;
  log(`media      ${n} files copied into deploy/site/${UPLOADS.replace(/\\/g, '/')}`);
} else {
  log(`media      skipped — upload it from ${path.relative(ROOT, path.join(DIST, UPLOADS))} (--media to include)`);
}

/* ------------------------------------------------------------------ */
/* The WordPress route                                                 */
/* ------------------------------------------------------------------ */

/**
 * Locate bsdtar, which ships with Windows 11 and flags zip entry names UTF-8.
 *
 * It has to be bsdtar specifically: GNU tar cannot write a zip at all, and in
 * Git Bash a bare `tar.exe` resolves to Git's GNU build, which shadows the one
 * in System32. PowerShell 5.1's Compress-Archive is not a fallback either — it
 * writes entry names in the legacy codepage and mangles the Greek filenames.
 */
function findBsdtar() {
  const root = process.env.SystemRoot || 'C:\\Windows';
  for (const bin of [path.join(root, 'System32', 'tar.exe'), 'tar.exe', 'tar']) {
    try {
      const v = execFileSync(bin, ['--version'], { stdio: 'pipe' }).toString();
      if (v.includes('bsdtar')) return bin;
    } catch (e) {
      /* next candidate */
    }
  }
  return null;
}

const BSDTAR = findBsdtar();

function zip(out, cwd, ...items) {
  if (!BSDTAR) return false;
  try {
    execFileSync(BSDTAR, ['-a', '-c', '-f', out, '-C', cwd, ...items], { stdio: 'pipe' });
    return true;
  } catch (e) {
    console.error('  zip failed: ' + path.basename(out) + ' — ' + (e.stderr || e.message).toString().trim());
    return false;
  }
}

const zips = [];

/*
 * Staged as the tree it has to end up as, so the whole thing is one upload and
 * one Extract at the WordPress root, with every file already where it belongs:
 *
 *   wp-content/themes/errands/            the theme
 *   wp-content/mu-plugins/…-runner.php    loads automatically, cannot be
 *                                         deactivated by accident
 *   wp-content/errands-import/            what the runner reads
 *
 * Extracting adds files and replaces same-named ones; nothing else in
 * wp-content is touched.
 */
const stage = path.join(WPDIR, 'wp-content');

const parts = [
  [path.join(ROOT, 'wp-content', 'themes', 'errands'), path.join(stage, 'themes', 'errands')],
  [
    path.join(ROOT, 'import', 'errands-import-runner.php'),
    path.join(stage, 'mu-plugins', 'errands-import-runner.php')
  ],
  [
    path.join(ROOT, 'import', 'errands-legacy-redirects.php'),
    path.join(stage, 'mu-plugins', 'errands-legacy-redirects.php')
  ],
  [path.join(ROOT, 'import', 'import.php'), path.join(stage, 'errands-import', 'import.php')],
  [path.join(ROOT, 'import', 'import.json'), path.join(stage, 'errands-import', 'import.json')]
];

for (const [from, to] of parts) {
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.cpSync(from, to, { recursive: true });
}

if (zip(path.join(WPDIR, 'errands-wordpress.zip'), WPDIR, 'wp-content')) {
  zips.push('wordpress/errands-wordpress.zip');
}

// Zipped without a wrapping folder, so extracting it in cPanel File Manager
// drops the pages straight into the document root.
if (zip(path.join(OUT, 'errands-static-site.zip'), SITE, '.')) {
  zips.push('errands-static-site.zip');
}

if (zips.length) {
  log('zips       ' + zips.join(', '));
} else {
  log('zips       skipped — no bsdtar found; upload the folders over FTP instead');
  log('           (looked for bsdtar in %SystemRoot%\\System32 and on PATH)');
}

/* ------------------------------------------------------------------ */
/* Manifest                                                            */
/* ------------------------------------------------------------------ */

function measure(dir) {
  let files = 0;
  let bytes = 0;
  const walk = d => {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else {
        files++;
        bytes += fs.statSync(p).size;
      }
    }
  };
  walk(dir);
  return { files, mb: (bytes / 1048576).toFixed(1) };
}

const site = measure(SITE);
const wp = measure(stage);

/* The media library the importer will look for, assembled by build-media.js. */
const required = [
  ...new Set([
    ...importJson.projects.flatMap(p => p.images),
    ...importJson.projects.flatMap(p => p.docs || []),
    ...((importJson.about && importJson.about.images) || []),
    ...((importJson.about && importJson.about.docs) || [])
  ])
];

const libDir = path.join(ROOT, UPLOADS);
const onDisk = required.filter(r => fs.existsSync(path.join(libDir, r)));
const libMb = onDisk
  .reduce((n, r) => n + fs.statSync(path.join(libDir, r)).size, 0) / 1048576;

const manifest = [
  `ERRANDS deployment package`,
  `built    ${new Date().toISOString()}`,
  `host     https://${HOST}/`,
  `source   ${DIST}`,
  ``,
  `-- the dynamic site (wp-admin) ------------------------------------`,
  ``,
  `deploy/wordpress/errands-wordpress.zip   ${wp.files} files, ${wp.mb} MB`,
  `  extract at the WordPress root; everything lands in place:`,
  `    wp-content/themes/errands/           the theme`,
  `    wp-content/mu-plugins/               the import runner (delete after)`,
  `    wp-content/errands-import/           import.php + import.json`,
  ``,
  `media library                            ${onDisk.length} of ${required.length} originals, ${libMb.toFixed(1)} MB`,
  `  upload wp-content/uploads/ over FTP, UTF-8 forced (3 Greek filenames)`,
  onDisk.length === required.length
    ? `  complete`
    : `  INCOMPLETE — run: node tools/build-media.js`,
  ``,
  `-- the static fallback -------------------------------------------`,
  ``,
  `deploy/site/                 ${site.files} files, ${site.mb} MB  -> document root`,
  `  ${urls.length} pages, robots.txt (crawlable), sitemap.xml, feed, .htaccess`,
  ``,
  `-- old permalinks ------------------------------------------------`,
  ``,
  `${legacy.length} legacy 301s (in .htaccess for the static route;`,
  `mu-plugins/errands-legacy-redirects.php ships in the zip for the dynamic one)`,
  ...legacy.map(([id, url]) => `  /?p=${String(id).padEnd(5)} -> ${url}`),
  ``
].join('\n');

fs.writeFileSync(path.join(OUT, 'MANIFEST.txt'), manifest);

log('');
log(`done       deploy/  —  ${site.files} site files, ${site.mb} MB (media excluded)`);
log('           read deploy/MANIFEST.txt, then DEPLOY.md');
