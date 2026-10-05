/**
 * Assemble a WordPress-ready media library in wp-content/uploads.
 *
 * The importer needs the 197 *original* uploads named exactly as import.json
 * lists them. The static export is not a substitute: it holds what the pages
 * reference, which is mostly derivatives. Measured against the manifest:
 *
 *   128  originals present
 *    61  present only as the "-scaled" copy WordPress makes of anything wider
 *        than 2560px — the pages link the scaled file, so that is what got
 *        crawled
 *     8  absent altogether; only their derivatives were ever referenced
 *
 * Point the importer at the export and it reports `not on disk` 69 times, and
 * the sizes it did get are a lossy ceiling for a site that will want to re-crop
 * later. So: seed the real originals out of the export, then pull the rest from
 * the old site, which is still serving them.
 *
 * Usage:
 *   node tools/build-media.js                  # seed, then download the gaps
 *   node tools/build-media.js --no-download    # seed and report only
 *   node tools/build-media.js --dist <path>
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

const DIST = path.resolve(ROOT, flag('dist', path.join('..', 'errandsDist')));
const SRC = path.join(DIST, 'wp-content', 'uploads');
const DEST = path.join(ROOT, 'wp-content', 'uploads');
const DOWNLOAD = !args.includes('--no-download');

const data = JSON.parse(fs.readFileSync(path.join(ROOT, 'import', 'import.json'), 'utf8'));

/** Every file the importer will look for, exactly as it will look for it. */
const required = [
  ...new Set([
    ...data.projects.flatMap(p => p.images),
    ...data.projects.flatMap(p => p.docs || []),
    ...((data.about && data.about.images) || []),
    ...((data.about && data.about.docs) || [])
  ])
];

const mb = n => (n / 1048576).toFixed(1) + ' MB';

/* ------------------------------------------------------------------ */
/* Seed from the export                                                */
/* ------------------------------------------------------------------ */

let already = 0;
let seeded = 0;
let seededBytes = 0;
const gaps = [];

for (const rel of required) {
  const dest = path.join(DEST, rel);

  if (fs.existsSync(dest) && fs.statSync(dest).size > 0) {
    already++;
    continue;
  }

  const src = path.join(SRC, rel);

  if (fs.existsSync(src) && fs.statSync(src).size > 0) {
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(src, dest);
    seededBytes += fs.statSync(dest).size;
    seeded++;
  } else {
    gaps.push(rel);
  }
}

console.log(`required   ${required.length} originals`);
if (already) console.log(`present    ${already} already in wp-content/uploads`);
console.log(`seeded     ${seeded} copied from the export (${mb(seededBytes)})`);
console.log(`gaps       ${gaps.length} not in the export`);

/* ------------------------------------------------------------------ */
/* Fill the gaps from the old site                                     */
/* ------------------------------------------------------------------ */

if (gaps.length && DOWNLOAD) {
  console.log('\n  fetching the rest with import/scrape.js --media\n');
  try {
    execFileSync(process.execPath, [path.join(ROOT, 'import', 'scrape.js'), '--media'], {
      stdio: 'inherit',
      cwd: ROOT
    });
  } catch (e) {
    console.error('\n  scrape.js failed — run it by hand: node import/scrape.js --media');
  }
} else if (gaps.length) {
  console.log('\n  --no-download: run `node import/scrape.js --media` to fetch them');
}

/* ------------------------------------------------------------------ */
/* Verify against the manifest                                         */
/* ------------------------------------------------------------------ */

const missing = [];
let bytes = 0;

for (const rel of required) {
  const f = path.join(DEST, rel);
  if (fs.existsSync(f) && fs.statSync(f).size > 0) bytes += fs.statSync(f).size;
  else missing.push(rel);
}

// Anything the importer does not ask for is a derivative that WordPress will
// rebuild on the host. Uploading them would only make the transfer bigger.
let extra = 0;
if (fs.existsSync(DEST)) {
  const all = fs.readdirSync(DEST, { recursive: true, withFileTypes: true }).filter(e => e.isFile());
  extra = all.length - (required.length - missing.length);
}

console.log('');
console.log(`library    ${required.length - missing.length} of ${required.length} originals, ${mb(bytes)}`);
if (extra > 0) console.log(`extra      ${extra} other files in uploads (derivatives — harmless, not needed)`);

if (missing.length) {
  console.log(`\n  ${missing.length} still missing — the importer will report these as "not on disk":`);
  missing.slice(0, 12).forEach(m => console.log('    ' + m));
  if (missing.length > 12) console.log(`    … and ${missing.length - 12} more`);
  process.exitCode = 1;
} else {
  console.log('\n  complete — every file the importer needs is on disk');
}
