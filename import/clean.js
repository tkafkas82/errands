/**
 * Turn the scraped errands.gr feed content into clean import data.
 *
 * The old posts are 2011-era WordPress HTML: inline <img> tags wrapped in
 * lightbox links, Microsoft Word conditional-comment junk, stray &nbsp;
 * paragraphs and hardcoded width/height attributes.
 *
 * Here we split each post into:
 *   prose   - just the text, as clean semantic HTML
 *   images  - the ordered list of original (full-size) image files
 *   docs    - non-image attachments (there is one PDF)
 *
 * The theme rebuilds the gallery from `images`, so the prose no longer
 * carries layout.
 *
 * Usage: node clean.js  ->  writes import.json
 */

const fs = require('fs');
const path = require('path');

const posts = JSON.parse(fs.readFileSync(path.join(__dirname, 'posts.json'), 'utf8'));

/* ------------------------------------------------------------------ */
/* Entities                                                            */
/* ------------------------------------------------------------------ */

const NAMED = {
  nbsp: ' ', amp: '&', lt: '<', gt: '>', quot: '"', apos: "'",
  hellip: '\u2026', ldquo: '\u201c', rdquo: '\u201d',
  lsquo: '\u2018', rsquo: '\u2019', ndash: '\u2013', mdash: '\u2014',
  laquo: '\u00ab', raquo: '\u00bb', deg: '\u00b0', copy: '\u00a9',
  middot: '\u00b7', sup2: '\u00b2', euro: '\u20ac', bull: '\u2022',
  prime: '\u2032', Prime: '\u2033',
};

function decode(s) {
  return String(s)
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => cp(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d) => cp(parseInt(d, 10)))
    .replace(/&([a-zA-Z][a-zA-Z0-9]*);/g, (m, name) =>
      Object.prototype.hasOwnProperty.call(NAMED, name) ? NAMED[name] : m);
}

function cp(n) {
  // The old theme printed U+02D1 as a date separator; drop such oddities.
  if (n === 0x2d1) return '';
  try { return String.fromCodePoint(n); } catch (e) { return ''; }
}

/* ------------------------------------------------------------------ */
/* Media extraction                                                    */
/* ------------------------------------------------------------------ */

/** Resized variant -> original full-size path, relative to uploads/. */
function toOriginal(url) {
  const noSize = url.replace(/-(\d{2,5})x(\d{2,5})(\.[a-z0-9]+)$/i, '$3');
  const rel = noSize.split('/wp-content/uploads/')[1];
  return rel ? decodeURIComponent(rel) : null;
}

const IMAGE_EXT = /\.(jpe?g|png|gif|webp)$/i;

const UPLOADS_DIR = path.join(__dirname, '..', 'wp-content', 'uploads');

function extractMedia(html) {
  const images = [];
  const docs = [];
  const foreign = [];
  const seen = new Set();

  const push = (rel) => {
    if (!rel || seen.has(rel)) return;
    seen.add(rel);
    (IMAGE_EXT.test(rel) ? images : docs).push(rel);
  };

  // Order matters: walk the document in source order so the gallery keeps
  // the sequence the collective originally laid out.
  const re = /<(?:img|a)\b[^>]*?(?:src|href)="([^"]+)"[^>]*>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const u = m[1];
    if (!/\/wp-content\/uploads\//i.test(u)) continue;

    // A few refs point at other WordPress sites that happen to share the
    // /wp-content/uploads/ shape. Only treat those as local when the very
    // same file really is in our uploads folder; otherwise flag them rather
    // than silently pointing a gallery slot at an unrelated local image.
    const isOurs = /^https?:\/\/errands\.gr\//i.test(u) || !/^https?:\/\//i.test(u);
    const rel = toOriginal(u);

    if (!isOurs) {
      if (rel && fs.existsSync(path.join(UPLOADS_DIR, rel))) {
        push(rel);
      } else {
        foreign.push(u);
      }
      continue;
    }

    push(rel);
  }

  return { images, docs, foreign };
}

/* ------------------------------------------------------------------ */
/* Prose cleaning                                                      */
/* ------------------------------------------------------------------ */

const ALLOWED = new Set(['p', 'em', 'i', 'strong', 'b', 'sup', 'sub', 'a',
  'ul', 'ol', 'li', 'br', 'h2', 'h3', 'h4', 'blockquote']);

function cleanProse(html) {
  let s = html;

  // 1. Word/IE conditional comments and every other HTML comment.
  s = s.replace(/<!--[\s\S]*?-->/g, '');

  // 2. Whole <style>/<script> blocks (post 283 carries a Word style sheet).
  s = s.replace(/<(style|script)\b[\s\S]*?<\/\1>/gi, '');

  // 3. Images and the links that only wrap an image.
  s = s.replace(/<a\b[^>]*>\s*(?:<img\b[^>]*>\s*)+<\/a>/gi, '');
  s = s.replace(/<img\b[^>]*>/gi, '');

  // 4. Links to media files lose the link but keep their text.
  s = s.replace(/<a\b[^>]*href="[^"]*\/wp-content\/uploads\/[^"]*"[^>]*>([\s\S]*?)<\/a>/gi, '$1');

  // 5. Word cruft: <span>/<font>/<o:p> wrappers, class/style/lang attributes.
  s = s.replace(/<\/?(?:span|font|o:p|v:\w+|w:\w+)\b[^>]*>/gi, '');

  // 6. Strip attributes from allowed tags, except href/title on <a>.
  s = s.replace(/<([a-z][a-z0-9]*)\b([^>]*)>/gi, (full, tag, attrs) => {
    const t = tag.toLowerCase();
    if (!ALLOWED.has(t)) return '';
    if (t === 'a') {
      const href = (attrs.match(/href="([^"]*)"/i) || [])[1];
      if (!href || /^#/.test(href)) return '';
      const abs = href.replace(/^http:\/\/errands\.gr/i, 'https://errands.gr');
      const ext = !/errands\.gr/i.test(abs);
      return `<a href="${abs}"${ext ? ' target="_blank" rel="noopener"' : ''}>`;
    }
    return `<${t}>`;
  });
  s = s.replace(/<\/([a-z][a-z0-9]*)>/gi, (full, tag) =>
    ALLOWED.has(tag.toLowerCase()) ? `</${tag.toLowerCase()}>` : '');

  // 7. Decode entities now that markup is settled.
  s = decode(s);

  // 8. Collapse whitespace, drop empty paragraphs and orphan <br> runs.
  s = s.replace(/[ \t\u00a0]+/g, ' ');
  s = s.replace(/(?:<br>\s*)+/gi, '<br>');
  s = s.replace(/<p>\s*(?:<br>)?\s*<\/p>/gi, '');
  s = s.replace(/<p>\s*<\/p>/gi, '');

  // 9. Re-wrap into tidy paragraphs.
  const blocks = s
    .split(/<\/p>|<p>/i)
    .map(b => b.trim())
    .filter(b => b && b.replace(/<[^>]+>/g, '').trim().length > 0);

  const out = blocks.map(b => {
    // Keep genuine block elements as they are.
    if (/^<(ul|ol|h2|h3|h4|blockquote)\b/i.test(b)) return b;
    return `<p>${b.replace(/^<br>|<br>$/gi, '').trim()}</p>`;
  });

  return out.join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
}

/* ------------------------------------------------------------------ */
/* Titles, slugs, series                                               */
/* ------------------------------------------------------------------ */

const GREEK_MAP = {
  α: 'a', β: 'v', γ: 'g', δ: 'd', ε: 'e', ζ: 'z', η: 'i', θ: 'th',
  ι: 'i', κ: 'k', λ: 'l', μ: 'm', ν: 'n', ξ: 'x', ο: 'o', π: 'p',
  ρ: 'r', σ: 's', ς: 's', τ: 't', υ: 'y', φ: 'f', χ: 'ch', ψ: 'ps',
  ω: 'o', ά: 'a', έ: 'e', ή: 'i', ί: 'i', ό: 'o', ύ: 'y', ώ: 'o',
  ϊ: 'i', ϋ: 'y', ΐ: 'i', ΰ: 'y',
};

function slugify(title) {
  // A "Greek / English" title slugs from the English half, which is what the
  // collective used in their own file names.
  const parts = title.split('/').map(p => p.trim()).filter(Boolean);
  const latin = parts.find(p => /[a-z]/i.test(p) && !/[\u0370-\u03ff]/.test(p));
  let base = latin || parts[0] || title;

  base = base.toLowerCase();
  base = base.replace(/[\u0370-\u03ff]/g, ch => GREEK_MAP[ch] || '');
  base = base.replace(/['\u2019\u2018]/g, '');
  base = base.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

  return base || 'project';
}

function seriesFor(title) {
  const t = title.toUpperCase();
  const out = [];
  if (/IMPROVISED\s+CITY/.test(t)) out.push('Improvised City');
  if (/WORKSHOP/.test(t)) out.push('Workshops');
  return out;
}

function excerptFrom(prose, words = 26) {
  const text = decode(prose.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
  const parts = text.split(' ');
  return parts.length <= words ? text : parts.slice(0, words).join(' ') + '\u2026';
}

/* ------------------------------------------------------------------ */
/* Build                                                               */
/* ------------------------------------------------------------------ */

// This post is the collective's own statement; it becomes the About page.
const ABOUT_ID = 477;

const out = { projects: [], about: null };

for (const p of posts) {
  const title = decode(p.title).replace(/\s+/g, ' ').trim();
  const prose = cleanProse(p.content);
  const { images, docs, foreign } = extractMedia(p.content);
  const date = new Date(p.date);

  if (foreign.length) {
    console.log(`!! ${p.id} references media on another host, skipped:\n   ${foreign.join('\n   ')}`);
  }

  const entry = {
    legacy_id: p.id,
    title,
    slug: slugify(title),
    date: date.toISOString().slice(0, 19).replace('T', ' '),
    prose,
    excerpt: excerptFrom(prose),
    images,
    docs,
    series: seriesFor(title),
  };

  if (p.id === ABOUT_ID) {
    entry.slug = 'about';
    out.about = entry;
  } else {
    out.projects.push(entry);
  }
}

fs.writeFileSync(path.join(__dirname, 'import.json'), JSON.stringify(out, null, 1));

/* ------------------------------------------------------------------ */
/* Report                                                             */
/* ------------------------------------------------------------------ */

console.log(`projects: ${out.projects.length}   about: ${out.about ? 'yes' : 'MISSING'}`);
console.log('');
console.log('id    date        imgs docs prose  series                 slug');
console.log('-'.repeat(94));

const all = out.about ? [out.about, ...out.projects] : out.projects;
for (const e of all) {
  console.log(
    String(e.legacy_id).padEnd(6),
    e.date.slice(0, 10),
    String(e.images.length).padStart(4),
    String(e.docs.length).padStart(4),
    String(e.prose.length).padStart(6),
    (e.series.join(',') || '-').padEnd(22),
    e.slug
  );
}

// Sanity checks worth seeing before importing.
const missingProse = all.filter(e => e.prose.length < 40);
const missingImg = all.filter(e => e.images.length === 0);
const slugs = all.map(e => e.slug);
const dupes = slugs.filter((s, i) => slugs.indexOf(s) !== i);

console.log('');
if (missingProse.length) console.log('!! little or no prose:', missingProse.map(e => e.legacy_id + ' ' + e.slug).join(', '));
if (missingImg.length) console.log('!! no images:', missingImg.map(e => e.legacy_id + ' ' + e.slug).join(', '));
if (dupes.length) console.log('!! duplicate slugs:', [...new Set(dupes)].join(', '));

const totalImgs = new Set(all.flatMap(e => e.images));
console.log(`unique image files referenced: ${totalImgs.size}`);

// Verify every referenced file actually exists on disk.
const uploads = path.join(__dirname, '..', 'wp-content', 'uploads');
const absent = [...totalImgs, ...all.flatMap(e => e.docs)]
  .filter(rel => !fs.existsSync(path.join(uploads, rel)));
console.log(absent.length ? `!! ${absent.length} referenced file(s) missing on disk:\n  ${absent.join('\n  ')}` : 'all referenced files present on disk');
