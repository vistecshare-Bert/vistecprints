const fs = require('fs');
const path = require('path');

const raw = JSON.parse(fs.readFileSync(path.join(__dirname, 'raw', 'deconetwork-catalog.json'), 'utf8'));

const GILDAN_CODES = new Set([
  '5000','5000B','5000L','2000','2000B','2000L','2000T','2200','2300','2400','2410',
  '5400','5400B','64000','64000B','64000L','64200','64200L','64400','64V00','64V00L',
  '18000','18000B','18200','18200B','18400','18500','18500B','18600','18600B','18900',
  '42000','42000B','42400','5V00L','5100P','5300','5700','8000','8000B','8300','8400',
  '8800','8800B','H000','SF000','SF500','SF500B','SF600','SF008','SF100',
  '64220LCVC','64000BCVC','64000CVC','64001LCVC','64440CVC','64800','64800L',
  '85800','64PLSMA','980','3000','3000B','65000','65000B','65000L','75000',
  '19000','19500','G2400','G5200','5286','4820','CE10L','48400Y','4411','202LS',
  '3311','498L','245Y','G2400','2030','2030B'
]);

const JERZEES_CODES = new Set(['29LSR','29BLR','29M','562','996','363M','29M']);

const TRANSFERS_CODES = new Set(['A3','A4','A5','A6','A7','A8','6X6','10X10']);

const SOLS_PREFIXES = /^0[0-9]{4}$/;

function detectBrand(p) {
  const n = p.name.toUpperCase().trim();
  const g = (p.group || '').toLowerCase();

  if (n.startsWith('BC') || n.startsWith('CV') || n === '3001CVC') return 'Bella+Canvas';
  if (n.startsWith('NL')) return 'Next Level';
  if (n.startsWith('CC')) return 'Comfort Colors';
  if (/^R\d/.test(n)) return 'Richardson';
  if (n.startsWith('PA')) return 'Paragon';
  if (n === '6606') return 'Yupoong';
  if (n.startsWith('AT') && /^AT\d/.test(n)) return 'AWDis';
  if (n.startsWith('NS')) return 'Native Spirit';
  if (n.startsWith('YK')) return 'Yoko';
  if (n.startsWith('JT') || n === 'T1100B') return 'Tee Jays';
  if (n.startsWith('FB')) return 'FNB';
  if (n.startsWith('SM') || n === 'SF130') return 'SF Clothing';
  if (TRANSFERS_CODES.has(n)) return 'Custom Transfers';
  if (n === 'TEST01' || n === '0101') return 'Test Products';
  if (JERZEES_CODES.has(n) || g.includes('jerzees')) return 'Jerzees';
  if (SOLS_PREFIXES.test(n) || g.includes("sol's")) return "SOL'S";
  if (GILDAN_CODES.has(n) || g.includes('gildan')) return 'Gildan';
  if (g.includes('bella+canvas') || g.includes('bella canvas')) return 'Bella+Canvas';
  if (g.includes('next level')) return 'Next Level';
  if (g.includes('comfort colors')) return 'Comfort Colors';
  if (g.includes('richardson')) return 'Richardson';
  if (g.includes('yupoong')) return 'Yupoong';
  if (g.includes('paragon')) return 'Paragon';
  if (g.includes('awdis')) return 'AWDis';

  // Augusta Sportswear — numeric/alphanumeric codes, sports apparel
  if (/^\d+[A-Z]?$/.test(n) || /^[0-9]{3,4}[A-Z]{0,3}$/.test(n)) {
    return 'Augusta Sportswear';
  }

  return 'Other';
}

const brands = {};
for (const p of raw) {
  const brand = detectBrand(p);
  if (!brands[brand]) brands[brand] = [];
  brands[brand].push({
    id: p.id,
    code: p.name,
    name: p.group,
    img: p.img
  });
}

const outDir = path.join(__dirname, 'brands');

for (const [brand, products] of Object.entries(brands)) {
  const filename = brand.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.json';
  const data = { brand, count: products.length, products };
  fs.writeFileSync(path.join(outDir, filename), JSON.stringify(data, null, 2));
  console.log(`${brand}: ${products.length} products → ${filename}`);
}

// Write index
const index = Object.entries(brands)
  .map(([brand, products]) => ({ brand, count: products.length }))
  .sort((a, b) => b.count - a.count);

fs.writeFileSync(path.join(__dirname, 'catalog-index.json'), JSON.stringify({
  total: raw.length,
  generated: new Date().toISOString(),
  brands: index
}, null, 2));

console.log(`\nTotal: ${raw.length} products across ${index.length} brands`);
