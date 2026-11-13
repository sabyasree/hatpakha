// The marketing studio (D182): draws the cards and reels the marketing team planned, with no person and no open page.
// Runs in GitHub Actions (.github/workflows/studio.yml), never on the web server:
//   node studio/render.mjs jobs.json out/
// jobs.json is what `php artisan hatpakha:studio list` prints: [{id, format, slides: [...], reel: {...}}].
// Each job gets out/<id>/1.jpg, 2.jpg… (cards, 1080×1350) and, for a reel, out/<id>/reel.mp4 (1080×1920) + cover.jpg.
import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const [jobsFile, outDir = 'out'] = process.argv.slice(2);
const jobs = JSON.parse(readFileSync(jobsFile, 'utf8'));
const FPS = 25;

/** Soft ticks for the countdown and a small chime for the answer: made here, so no music licence is involved. */
function audioArgs(kind, seconds) {
    const beeps = kind === 'riddle'
        ? [[6.2, 880, .07], [7.2, 880, .07], [8.2, 880, .07], [9.2, 880, .07], [10.2, 880, .07], [11.2, 1318, .5], [11.35, 1568, .6]]
        : [[.2, 1046, .5], [10.5, 1318, .5]];
    const inputs = ['-f', 'lavfi', '-t', String(seconds), '-i', 'anullsrc=r=44100:cl=stereo'];
    const filters = [];
    beeps.forEach(([at, hz, len], i) => {
        inputs.push('-f', 'lavfi', '-t', String(len), '-i', `sine=frequency=${hz}:sample_rate=44100`);
        filters.push(`[${i + 2}:a]volume=0.25,afade=t=out:st=0:d=${len},adelay=${Math.round(at * 1000)}:all=1[b${i}]`);
    });
    filters.push(`[1:a]${beeps.map((_, i) => `[b${i}]`).join('')}amix=inputs=${beeps.length + 1}:duration=first:normalize=0[a]`);
    return { inputs, filter: filters.join(';') };
}

async function reel(page, spec, file) {
    const seconds = await page.evaluate((k) => window.LENGTH[k] ?? 13, spec.t);
    const audio = audioArgs(spec.t, seconds);
    const ff = spawn('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-', ...audio.inputs,
        '-filter_complex', audio.filter, '-map', '0:v', '-map', '[a]', '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-pix_fmt', 'yuv420p',
        '-r', String(FPS), '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-movflags', '+faststart', '-t', String(seconds), file], { stdio: ['pipe', 'inherit', 'inherit'] });
    const done = new Promise((ok, fail) => ff.on('close', (code) => (code === 0 ? ok() : fail(new Error('ffmpeg exit ' + code)))));
    for (let f = 0; f < seconds * FPS; f++) {
        await page.evaluate(([s, t]) => window.draw(s, t), [spec, f / FPS]);
        const shot = await page.locator('#stage').screenshot({ type: 'jpeg', quality: 92 });
        if (!ff.stdin.write(shot)) await new Promise((r) => ff.stdin.once('drain', r));
    }
    ff.stdin.end();
    await done;
}

const browser = await chromium.launch();
let made = 0;
for (const job of jobs) {
    try {
        const dir = join(resolve(outDir), String(job.id));
        mkdirSync(dir, { recursive: true });
        const tall = job.format === 'reel';
        // 720 wide at 1.5× = 1080 pixels: the same picture, drawn faster.
        const page = await browser.newPage({ viewport: { width: 1080, height: tall ? 1920 : 1350 }, deviceScaleFactor: 1 });
        await page.goto(pathToFileURL(join(here, 'template.html')).href);
        await page.evaluate(() => window.ready);
        let n = 0;
        for (const slide of job.slides || []) {
            await page.setViewportSize({ width: 1080, height: 1350 });
            await page.evaluate((s) => window.draw(s, null), slide);
            await page.locator('#stage').screenshot({ path: join(dir, `${++n}.jpg`), type: 'jpeg', quality: 90 });
        }
        if (tall && job.reel) {
            await page.setViewportSize({ width: 1080, height: 1920 });
            await page.evaluate((s) => window.draw(s, 3), job.reel);
            await page.locator('#stage').screenshot({ path: join(dir, 'cover.jpg'), type: 'jpeg', quality: 90 });
            await reel(page, job.reel, join(dir, 'reel.mp4'));
        }
        await page.close();
        made++;
        console.log(`drawn: ${job.id} (${job.format})`);
    } catch (e) {
        console.error(`failed: ${job.id}: ${e.message}`);   // one bad job never stops the others
    }
}
await browser.close();
console.log(`${made} of ${jobs.length} drawn`);
