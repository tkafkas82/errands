/**
 * Pull the original errands.gr content and media.
 *
 * WordPress 4.4 predates the REST API content endpoints (those landed in 4.7),
 * so the posts come from the RSS feed, whose <content:encoded> carries the full
 * post body.
 *
 * Writes:
 *   posts.json                     raw scrape (title, date, link, content)
 *   ../wp-content/uploads/YYYY/MM  every referenced image at full size
 *
 * Usage:
 *   node scrape.js            # posts + media
 *   node scrape.js --posts    # posts only
 *   node scrape.js --media    # media only (reuses existing posts.json)
 */

const fs = require('fs');
const path = require('path');

const SITE = 'https://errands.gr';
const UA = {
  'User-Agent':
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
};

const POSTS_JSON = path.join(__dirname, 'posts.json');
const UPLOADS = path.join(__dirname, '..', 'wp-content', 'uploads');

const args = process.argv.slice(2);
const doPosts = args.length === 0 || args.includes('--posts');
const doMedia = args.length === 0 || args.includes('--media');

/* ------------------------------------------------------------------ */
/* Posts                                                               */
/* ------------------------------------------------------------------ */

const cdata = s => (s || '').replace(/^<!\[CDATA\[/, '').replace(/\]\]>$/, '');

function tag(block, name) {
  const m = block.match(new RegExp('<' + name + '>([\\s\\S]*?)<\\/' + name + '>'));
  return cdata(m ? m[1] : '');
}

async function scrapePosts() {
  const items = [];

  // The feed pages until one comes back empty or 404s.
  for (let page = 1; page <= 20; page++) {
    const url = `${SITE}/?feed=rss2&paged=${page}`;
    const res = await fetch(url, { headers: UA });

    if (!res.ok) {
      console.log(`  page ${page}: HTTP ${res.status} — stopping`);
      break;
    }

    const xml = await res.text();
    const found = [...xml.matchAll(/<item>([\s\S]*?)<\/item>/g)].map(m => m[1]);
    console.log(`  page ${page}: ${found.length} items`);

    if (!found.length) break;
    items.push(...found);
  }

  const posts = items
    .map(b => {
      const link = tag(b, 'link');
      const idm = link.match(/[?&]p=(\d+)/);
      return {
        id: idm ? +idm[1] : null,
        title: tag(b, 'title'),
        date: tag(b, 'pubDate'),
        link,
        content: tag(b, 'content:encoded'),
      };
    })
    .filter(p => p.id);

  // De-duplicate (paged feeds can overlap) and sort newest first.
  const byId = new Map(posts.map(p => [p.id, p]));
  const unique = [...byId.values()].sort((a, b) => new Date(b.date) - new Date(a.date));

  fs.writeFileSync(POSTS_JSON, JSON.stringify(unique, null, 1));
  console.log(`\nposts.json written — ${unique.length} posts`);

  return unique;
}

/* ------------------------------------------------------------------ */
/* Media                                                               */
/* ------------------------------------------------------------------ */

/** Strip WordPress's -WxH suffix to get the original upload. */
function toOriginal(url) {
  return url.replace(/-(\d{2,5})x(\d{2,5})(\.[a-z0-9]+)$/i, '$3');
}

/**
 * Every uploads URL referenced by the posts, mapped to {absoluteUrl, relPath}.
 *
 * Note the original content mixes absolute, protocol-relative and *relative*
 * URLs (one post uses ../wp-content/uploads/...), so anything matching the
 * uploads path is resolved against the site root rather than assumed absolute.
 */
function collectMedia(posts) {
  const out = new Map();

  const add = url => {
    if (!/\/wp-content\/uploads\//i.test(url)) return;

    // Only our own host. Other domains that share the WordPress path shape
    // are somebody else's media and are left alone.
    const foreign = /^https?:\/\//i.test(url) && !/^https?:\/\/errands\.gr\//i.test(url);
    if (foreign) return;

    const rel = decodeURIComponent(toOriginal(url).split('/wp-content/uploads/')[1] || '');
    if (!rel) return;

    out.set(rel, `${SITE}/wp-content/uploads/${rel.split('/').map(encodeURIComponent).join('/')}`);
  };

  for (const p of posts) {
    for (const m of p.content.matchAll(/(?:src|href)="([^"]+)"/gi)) add(m[1]);
    for (const m of p.content.matchAll(/srcset="([^"]+)"/gi)) {
      for (const part of m[1].split(',')) add(part.trim().split(/\s+/)[0]);
    }
  }

  // The header logo lives in uploads but is referenced by the theme, not a post.
  out.set('2011/03/ERRANDS-LOGO-BIG1.jpg', `${SITE}/wp-content/uploads/2011/03/ERRANDS-LOGO-BIG1.jpg`);

  return out;
}

async function scrapeMedia(posts) {
  const media = collectMedia(posts);
  const entries = [...media.entries()];
  console.log(`  ${entries.length} original files referenced`);

  let ok = 0;
  let skip = 0;
  const failed = [];

  const grab = async ([rel, url]) => {
    const dest = path.join(UPLOADS, rel);

    if (fs.existsSync(dest) && fs.statSync(dest).size > 0) {
      skip++;
      return;
    }

    fs.mkdirSync(path.dirname(dest), { recursive: true });

    for (let attempt = 1; attempt <= 3; attempt++) {
      try {
        const res = await fetch(url, { headers: UA });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const buf = Buffer.from(await res.arrayBuffer());
        if (!buf.length) throw new Error('empty response');

        fs.writeFileSync(dest, buf);
        ok++;
        return;
      } catch (e) {
        if (attempt === 3) failed.push(`${rel} :: ${e.message}`);
      }
    }
  };

  // Eight at a time: enough to saturate the link, gentle on a 2011 host.
  let i = 0;
  await Promise.all(
    Array.from({ length: 8 }, async () => {
      while (i < entries.length) {
        const idx = i++;
        await grab(entries[idx]);
        const done = ok + skip + failed.length;
        if (done % 40 === 0) console.log(`  ${done}/${entries.length}`);
      }
    })
  );

  console.log(`\ndownloaded ${ok}, already present ${skip}, failed ${failed.length}`);
  if (failed.length) console.log(failed.map(f => '  ' + f).join('\n'));
}

/* ------------------------------------------------------------------ */

(async () => {
  let posts;

  if (doPosts) {
    console.log('Fetching posts from the RSS feed');
    posts = await scrapePosts();
  } else {
    posts = JSON.parse(fs.readFileSync(POSTS_JSON, 'utf8'));
    console.log(`Reusing posts.json — ${posts.length} posts`);
  }

  if (doMedia) {
    console.log('\nFetching media');
    await scrapeMedia(posts);
  }

  console.log('\nNext: node clean.js   then   docker compose run --rm cli eval-file /import/import.php');
})();
