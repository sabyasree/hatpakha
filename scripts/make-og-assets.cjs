// Share-picture assets (D88). Run locally with the dev server up (php artisan serve --port=8123):
//   node scripts/make-og-assets.cjs
// Bengali needs a real text shaper, which the server's GD lacks, so every piece of text on a share picture
// is drawn here once with the site's own fonts. The server only lays a cover photo under an overlay.
// Uses the Playwright that is preinstalled in the dev container (not a project dependency).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const base = process.env.BASE || 'http://127.0.0.1:8123';
const out = __dirname + '/../public/images/og/';
const sections = JSON.parse(execSync(`php ${__dirname}/../artisan tinker --execute='echo App\\Models\\Section::get(["slug","name_bn"])->toJson(JSON_UNESCAPED_UNICODE);'`).toString());
const fan = (() => { let p = ''; const n = 16; for (let k = 0; k < n; k++) { const a1 = Math.PI + Math.PI * k / n, a2 = Math.PI + Math.PI * (k + 1) / n;
    const pt = (a) => `${(100 + 98 * Math.cos(a)).toFixed(2)} ${(100 + 98 * Math.sin(a)).toFixed(2)}`;
    p += `<path d="M100 100 L${pt(a1)} A98 98 0 0 1 ${pt(a2)} Z" fill="${k % 2 ? '#F08D90' : '#CB1B32'}"/>`; }
  return `<svg viewBox="0 0 200 100">${p}<path d="M62 100 A38 38 0 0 1 138 100 Z" fill="#EFC08D"/></svg>`; })();
const head = `<link rel="stylesheet" href="${base}/fonts/fonts.css"><style>*{margin:0;box-sizing:border-box}body{width:1200px;height:630px;font-family:'Anek Bangla',sans-serif;color:#13212C}</style>`;
const card = (name) => `${head}<body style="background:#FFFBF4;position:relative;overflow:hidden">
  <div style="position:absolute;right:-60px;bottom:-4px;width:620px">${fan}</div>
  <div style="position:absolute;left:72px;top:64px"><img src="${base}/images/logo-wordmark.png" style="height:84px"></div>
  <div style="position:absolute;left:72px;top:200px;font-size:${[...name].length > 8 ? 112 : 150}px;font-weight:800;line-height:1.1;max-width:640px">${name}</div>
  <div style="position:absolute;left:76px;bottom:70px;font-size:30px;font-weight:600;color:#4D5A63">সাহিত্য পত্রিকা · ২০১২ থেকে<br><span style="font-size:26px">ISSN 2321-3035 · hatpakha.com</span></div>
  <div style="position:absolute;left:0;right:0;bottom:0;height:18px;background:#CB1B32"></div></body>`;
const overlay = (name) => `${head}<body style="background:transparent;position:relative">
  <div style="position:absolute;left:0;right:0;bottom:0;height:260px;background:linear-gradient(to top,rgba(19,33,44,.55),rgba(19,33,44,0))"></div>
  <div style="position:absolute;left:40px;bottom:40px;display:flex;align-items:center;gap:18px;background:#FFFBF4;border:3px solid #13212C;border-radius:22px;padding:14px 22px;box-shadow:6px 6px 0 #13212C">
    <img src="${base}/images/logo-wordmark.png" style="height:52px">
    ${name ? `<span style="background:#CB1B32;color:#fff;font-weight:800;font-size:34px;line-height:1;padding:12px 22px 10px;border-radius:999px">${name}</span>` : ''}
  </div></body>`;
(async () => {
  require('fs').mkdirSync(out, { recursive: true });
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1200, height: 630 } });
  const shot = async (html, file, transparent) => { await p.setContent(html, { waitUntil: 'networkidle' }); await p.evaluate(() => document.fonts.ready);
    await p.screenshot(transparent ? { path: out + file, omitBackground: true } : { path: out + file, type: 'jpeg', quality: 88 }); console.log(file); };
  for (const s of [...sections, { slug: 'default', name_bn: '' }, { slug: 'collection', name_bn: 'সংকলন' }]) {
    await shot(card(s.name_bn || 'হাতপাখা'), `card-${s.slug}.jpg`);
    await shot(overlay(s.name_bn), `overlay-${s.slug}.png`, true);
  }
  await b.close(); })();
