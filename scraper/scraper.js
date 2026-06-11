/**
 * VistecPrints DecoNetwork Product Scraper
 * Run: node scraper.js
 * Output: ../products.json + images saved to ../images/{category}/
 */

const puppeteer = require('puppeteer');
const axios     = require('axios');
const fs        = require('fs');
const path      = require('path');

const BASE_URL = 'https://www.vistecprints.com';

const CATEGORIES = [
  { label: 'Fun Print Design',   slug: 'fun',      url: BASE_URL + '/shop/category/Fun-Print-Design?c=5410316'   },
  { label: 'Anime Print Design', slug: 'anime',    url: BASE_URL + '/shop/category/Anime-Print-Design?c=5410381' },
  { label: 'Boots on the Ground',slug: 'boots',    url: BASE_URL + '/shop/category/Boots-on-the-ground?c=5413596'},
  { label: 'No Kings',           slug: 'no_kings', url: BASE_URL + '/shop/category/No-kings?c=5490566'           },
  { label: 'Jesus Christ',       slug: 'jesus',    url: BASE_URL + '/shop/category/Jesus-Christ?c=5974736'       },
];

// ── Helpers ──────────────────────────────────────────────────────────────────

function detectGarment(name) {
  const n = name.toLowerCase();
  if (n.includes('hoodie'))                         return 'Hoodie';
  if (n.includes('sweatshirt') || n.includes('crewneck')) return 'Sweatshirt';
  if (n.includes('t-shirt') || n.includes('tee') || n.includes('shirt')) return 'T-Shirt';
  if (n.includes('boot'))                           return 'Boots';
  if (n.includes('hat') || n.includes('cap'))       return 'Hat';
  if (n.includes('tank'))                           return 'Tank Top';
  if (n.includes('jacket') || n.includes('zip'))   return 'Jacket';
  return 'Custom Print';
}

function sanitizeFilename(str) {
  return str.replace(/[^a-z0-9_\-]/gi, '_').toLowerCase().slice(0, 40);
}

async function downloadImage(imgUrl, destPath) {
  const response = await axios({ url: imgUrl, responseType: 'stream', timeout: 15000,
    headers: { 'User-Agent': 'Mozilla/5.0' } });
  await new Promise((resolve, reject) => {
    const writer = fs.createWriteStream(destPath);
    response.data.pipe(writer);
    writer.on('finish', resolve);
    writer.on('error', reject);
  });
}

// ── Scrape one category page ──────────────────────────────────────────────────

async function scrapeCategory(browser, cat) {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
  await page.setViewport({ width: 1280, height: 900 });

  console.log(`  Loading: ${cat.url}`);
  await page.goto(cat.url, { waitUntil: 'networkidle2', timeout: 40000 });

  // Scroll to bottom to trigger lazy loading
  await page.evaluate(async () => {
    await new Promise(resolve => {
      let total = 0;
      const step = 300;
      const id = setInterval(() => {
        window.scrollBy(0, step);
        total += step;
        if (total >= document.body.scrollHeight) { clearInterval(id); resolve(); }
      }, 100);
    });
  });
  await new Promise(r => setTimeout(r, 2000)); // wait for images to load

  const products = await page.evaluate((baseUrl) => {
    const results = [];

    // DecoNetwork product cards — try multiple selectors
    const cards = document.querySelectorAll(
      '.product-list-item, .product_list_item, li.product, [class*="product-item"], [class*="productItem"]'
    );

    const processCard = (el) => {
      const nameEl  = el.querySelector('h4, h3, [class*="product-name"], [class*="productName"]');
      const imgEl   = el.querySelector('img');
      const linkEl  = el.querySelector('a[href*="view_product"], a[href*="shop"]') || el.querySelector('a');
      const priceEl = el.querySelector('[class*="price"], .price, [itemprop="price"]');

      if (!nameEl || !imgEl) return;

      const imgSrc = imgEl.src || imgEl.dataset.src || imgEl.dataset.lazySrc ||
                     imgEl.getAttribute('data-original') || imgEl.getAttribute('data-srcset') || '';

      if (imgSrc.includes('placeholder') || imgSrc === '') return;

      results.push({
        name:  nameEl.textContent.trim(),
        img:   imgSrc.startsWith('http') ? imgSrc : baseUrl + imgSrc,
        href:  linkEl ? (linkEl.href || '') : '',
        price: priceEl ? priceEl.textContent.trim().replace(/\s+/g,' ') : '',
      });
    };

    if (cards.length > 0) {
      cards.forEach(processCard);
    } else {
      // Fallback: find all <li> that contain an h4 and an img
      document.querySelectorAll('li').forEach(li => {
        if (li.querySelector('h4') && li.querySelector('img')) processCard(li);
      });
    }

    return results;
  }, BASE_URL);

  await page.close();
  return products;
}

// ── Scrape individual product page for price ──────────────────────────────────

async function scrapePrice(browser, href) {
  if (!href) return '';
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const price = await page.evaluate(() => {
      const el = document.querySelector('[class*="price"], .price, [itemprop="price"], [class*="Price"]');
      return el ? el.textContent.trim().replace(/\s+/g,' ') : '';
    });
    await page.close();
    const match = price.match(/\$[\d,]+(\.\d{2})?/);
    return match ? match[0] : price;
  } catch { return ''; }
}

// ── Main ──────────────────────────────────────────────────────────────────────

async function main() {
  const outDir = path.join(__dirname, '..');
  const existingPath = path.join(outDir, 'products.json');

  // Load existing products so we don't lose anything
  let existing = [];
  if (fs.existsSync(existingPath)) {
    try { existing = JSON.parse(fs.readFileSync(existingPath, 'utf8')); } catch {}
  }

  console.log('\n🚀 Starting VistecPrints scraper...\n');

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const allProducts = [];
  let total = 0;

  for (const cat of CATEGORIES) {
    console.log(`\n📦 Category: ${cat.label}`);

    const imgDir = path.join(outDir, 'images', cat.slug);
    if (!fs.existsSync(imgDir)) fs.mkdirSync(imgDir, { recursive: true });

    let rawProducts = [];
    try {
      rawProducts = await scrapeCategory(browser, cat);
      console.log(`  Found ${rawProducts.length} products`);
    } catch (e) {
      console.error(`  ❌ Failed to scrape ${cat.label}: ${e.message}`);
      continue;
    }

    for (const p of rawProducts) {
      const safeName = sanitizeFilename(p.name);
      const id       = `${cat.slug}_${safeName}_${Date.now().toString(36)}`;
      let   imgPath  = p.img;

      // Download image locally
      if (p.img && p.img.startsWith('http')) {
        try {
          const ext      = (p.img.match(/\.(jpg|jpeg|png|webp|gif)/i) || ['.jpg'])[0];
          const filename = `${safeName}${ext}`;
          const dest     = path.join(imgDir, filename);
          if (!fs.existsSync(dest)) {
            await downloadImage(p.img, dest);
            console.log(`  ✓ Downloaded: ${filename}`);
          } else {
            console.log(`  ↩ Already exists: ${filename}`);
          }
          imgPath = `images/${cat.slug}/${filename}`;
        } catch (e) {
          console.warn(`  ⚠ Image download failed (keeping URL): ${e.message}`);
        }
      }

      // Get price — try from page text, then visit product page
      let price = '';
      const priceMatch = p.price.match(/\$[\d,]+(\.\d{2})?/);
      if (priceMatch) {
        price = priceMatch[0];
      } else if (p.href) {
        price = await scrapePrice(browser, p.href);
      }

      allProducts.push({
        id,
        name:    p.name,
        cat:     cat.slug,
        badge:   cat.label,
        img:     imgPath,
        price:   price || 'Contact for price',
        garment: detectGarment(p.name),
      });
      total++;
    }
  }

  await browser.close();

  // Merge with any existing manual products (don't overwrite)
  const existingIds = new Set(allProducts.map(p => p.name.toLowerCase()));
  const keptExisting = existing.filter(p => !existingIds.has(p.name.toLowerCase()));
  const final = [...allProducts, ...keptExisting];

  fs.writeFileSync(existingPath, JSON.stringify(final, null, 2));
  console.log(`\n✅ Done! ${total} products scraped, ${keptExisting.length} existing kept.`);
  console.log(`📄 Saved to: ${existingPath}`);
  console.log('\nNext step: upload products.json to public_html/rebuild/ on HostGator.\n');
}

main().catch(err => { console.error('Fatal error:', err); process.exit(1); });
