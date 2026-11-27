// D321: saves every বাংলা শিখি print page as a PDF, in a real browser (the web server cannot shape Bengali in a PDF).
// Usage: node scripts/learn-pdfs.mjs jobs.json http://127.0.0.1:8099 public/pdf/learn
// Playwright is installed by the deploy job in a temporary folder (as the marketing studio does, D182); run from there.
import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const require = createRequire(join(process.cwd(), 'noop.js'));
const { chromium } = require('playwright');
const [jobsFile, base, out] = process.argv.slice(2);
const jobs = JSON.parse(readFileSync(jobsFile, 'utf8'));
mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage();
let n = 0;
for (const [file, path] of Object.entries(jobs)) {
    const res = await page.goto(base + path, { waitUntil: 'networkidle' });
    if (!res || res.status() !== 200) { console.error(`${file}: HTTP ${res && res.status()}`); process.exitCode = 1; continue; }
    await page.evaluate(() => document.fonts.ready);
    await page.pdf({ path: join(out, file + '.pdf'), format: file.endsWith('-letter') ? 'Letter' : 'A4', printBackground: true, preferCSSPageSize: true });
    // a picture of the first page for the worksheets page and Google Images: the charts and each unit's first sheet (A4)
    if (/^(chart-[a-z]+|[a-z0-9-]+-1)-a4$/.test(file)) {
        await page.setViewportSize({ width: 794, height: 1123 });
        await page.emulateMedia({ media: 'print' });
        await page.screenshot({ path: join(out, 'preview-' + file.replace(/-a4$/, '') + '.jpg'), type: 'jpeg', quality: 80, clip: { x: 0, y: 0, width: 794, height: 1000 } });
        await page.emulateMedia({ media: 'screen' });
    }
    n++;
}
await browser.close();
console.log(`${n} PDFs saved in ${out}`);
