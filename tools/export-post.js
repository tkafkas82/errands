/**
 * Post-process the static export.
 *
 *   1. Rewrite absolute localhost URLs.
 *   2. Strip ?ver= cache-busting queries from local asset URLs.
 *   3. Copy exactly the local files the pages reference, straight off disk.
 *
 * Why copy rather than crawl: the full-size images behind the lightbox live in
 * `data-full` attributes, which no crawler follows; and the theme's own CSS and
 * JS are served with a ?ver= query, which cannot become a filename on a static
 * host (and is illegal in a Windows filename). Both are already on this disk,
 * so copying is exact and instant.
 *
 * Usage: node tools/export-post.js <dist> <origin> [base]
 *   dist    export directory, e.g. dist
 *   origin  http://localhost:8080
 *   base    public base URL, or empty for root-relative URLs
 */

const fs = require('fs');
const path = require('path');

const [, , DIST, ORIGIN, BASE_RAW] = process.argv;

if (!DIST || !ORIGIN) {
  console.error('usage: node tools/export-post.js <dist> <origin> [base]');
  process.exit(1);
}

const BASE = (BASE_RAW || '').replace(/\/$/, '');
const ROOT = path.resolve(__dirname, '..');

/* ------------------------------------------------------------------ */
/* Walk                                                                */
/* ------------------------------------------------------------------ */

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(p, out);
    else out.push(p);
  }
  return out;
}

const textFiles = walk(DIST).filter(f => /\.(html?|css|js|xml|json|txt)$/i.test(f));
console.log(`  ${textFiles.length} text files to process`);

/* ------------------------------------------------------------------ */
/* 1 + 2. Rewrite URLs, then strip asset version queries               */
/* ------------------------------------------------------------------ */

// wget's --convert-links fixes what it downloaded, but leaves absolute URLs for
// everything it did not — and does not touch canonical/og tags or the JSON of
// the localized search index. In that JSON the slashes are escaped, so the
// escaped form must be handled too or every search result would point at
// localhost.
const originEscaped = ORIGIN.replace(/\//g, '\\/');
const baseEscaped = BASE.replace(/\//g, '\\/');

// Matches a local asset URL followed by a query, in normal or JSON-escaped form.
const ASSET_QUERY_RE = /((?:\\?\/)wp-(?:content|includes)\/[^"'\s>]*?\.(?:css|js|svg|png|jpe?g|gif|ico|woff2?))\?[^"'\s>]*/gi;

let urlHits = 0;
let queryHits = 0;

for (const file of textFiles) {
  const before = fs.readFileSync(file, 'utf8');

  let after = before.split(ORIGIN).join(BASE);
  after = after.split(originEscaped).join(baseEscaped);
  urlHits += (before.split(ORIGIN).length - 1) + (before.split(originEscaped).length - 1);

  after = after.replace(ASSET_QUERY_RE, (m, keep) => {
    queryHits++;
    return keep;
  });

  if (after !== before) fs.writeFileSync(file, after);
}

console.log(`  rewrote ${urlHits} absolute URL(s) -> ${BASE || 'root-relative'}`);
console.log(`  stripped ${queryHits} asset version query string(s)`);

/* ------------------------------------------------------------------ */
/* 3. Copy every referenced local file                                 */
/* ------------------------------------------------------------------ */

// Uploads and theme assets both live under wp-content on this disk, so one pass
// handles them. `*` is excluded so WordPress's speculationrules block — which
// lists glob patterns like /wp-content/uploads/* — is not read as a filename.
const LOCAL_RE = /\/wp-content\/((?:uploads|themes)\/[^"'\s,)\\>*]+)/g;

const referenced = new Set();

for (const file of textFiles) {
  const text = fs.readFileSync(file, 'utf8');
  let m;
  while ((m = LOCAL_RE.exec(text)) !== null) referenced.add(m[1]);
}

let copied = 0;
const missing = [];

for (const rel of referenced) {
  // Directory-shaped matches come from the speculationrules globs
  // (/wp-content/themes/errands/*), not from real references.
  if (rel.endsWith('/')) continue;

  // URLs may be percent-encoded (several files have Greek names); the file on
  // disk carries the decoded name, and hosts resolve the encoded request to it.
  let decoded;
  try {
    decoded = decodeURIComponent(rel);
  } catch (e) {
    decoded = rel;
  }

  const from = path.join(ROOT, 'wp-content', decoded);
  const to = path.join(DIST, 'wp-content', decoded);

  if (!fs.existsSync(from) || fs.statSync(from).isDirectory()) {
    missing.push(decoded);
    continue;
  }

  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
  copied++;
}

console.log(`  local files referenced: ${referenced.size}, copied: ${copied}`);

if (missing.length) {
  console.log(`  !! ${missing.length} referenced file(s) not on disk:`);
  for (const m of missing.slice(0, 10)) console.log(`       ${m}`);
}

/* ------------------------------------------------------------------ */
/* 4. Checks                                                           */
/* ------------------------------------------------------------------ */

const problems = [];

for (const file of textFiles) {
  const text = fs.readFileSync(file, 'utf8');
  if (text.includes('localhost:')) problems.push(`localhost reference in ${path.relative(DIST, file)}`);
}

// The stylesheet and script are what make the site look like anything at all;
// a silent miss here produced an unstyled export once already.
for (const must of [
  'wp-content/themes/errands/style.css',
  'wp-content/themes/errands/assets/js/main.js',
  'wp-content/themes/errands/assets/favicon.svg',
  '404.html',
  'index.html',
]) {
  if (!fs.existsSync(path.join(DIST, must))) problems.push(`MISSING ${must}`);
}

// Keep crawlers off a preview so it cannot compete with the real errands.gr.
fs.writeFileSync(path.join(DIST, 'robots.txt'), 'User-agent: *\nDisallow: /\n');
console.log('  wrote robots.txt (crawling disallowed while this is a preview)');

if (problems.length) {
  console.log('\n  PROBLEMS:');
  for (const p of problems) console.log(`    ${p}`);
  process.exitCode = 1;
} else {
  console.log('  checks passed: no localhost refs, all critical assets present');
}
