// Step 2: timeline.json + scenes/reel.html -> frames (Playwright) -> out/<slug>/reel.mp4 (ffmpeg)
// Usage: node scripts/render.mjs [path/to/script.json] [--preview] [--music path/to/music.mp3]
import { spawn, execFileSync } from "node:child_process";
import { createRequire } from "node:module";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { pathToFileURL } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const ROOT = path.resolve(import.meta.dirname, "..");
const args = process.argv.slice(2);
const flag = (name) => args.includes(name);
const opt = (name) => (args.includes(name) ? args[args.indexOf(name) + 1] : undefined);
const scriptPath = path.resolve(args.find((a) => a.endsWith(".json")) || path.join(ROOT, "reel/script.json"));
const S = JSON.parse(fs.readFileSync(scriptPath, "utf8"));
const OUT = path.join(ROOT, "out", S.slug);
const timeline = JSON.parse(fs.readFileSync(path.join(OUT, "timeline.json"), "utf8"));
const page_url = pathToFileURL(path.join(ROOT, "scenes/reel.html")).href;

const CSS_W = 540;
const scale = timeline.width / CSS_W;
const CSS_H = Math.round(timeline.height / scale);

async function openPage(browser) {
  const ctx = await browser.newContext({ viewport: { width: CSS_W, height: CSS_H }, deviceScaleFactor: scale });
  const page = await ctx.newPage();
  await page.addInitScript((tl) => { window.TIMELINE = tl; }, timeline);
  await page.goto(page_url);
  await page.evaluate(() => document.fonts.ready);
  const warnings = await page.evaluate(() => window.__warnings);
  return { page, warnings };
}

// Render frames [from, to) into one H.264 segment, piping JPEG screenshots straight into ffmpeg.
async function renderSegment(browser, from, to, file) {
  const { page } = await openPage(browser);
  const ff = spawn("ffmpeg", ["-v", "error", "-y", "-f", "image2pipe", "-framerate", String(timeline.fps), "-c:v", "mjpeg", "-i", "-",
    "-c:v", "libx264", "-preset", "medium", "-crf", "18", "-pix_fmt", "yuv420p", "-r", String(timeline.fps), file],
    { stdio: ["pipe", "inherit", "inherit"] });
  const done = new Promise((res, rej) => ff.on("close", (c) => (c === 0 ? res() : rej(new Error(`ffmpeg exited ${c}`)))));
  for (let f = from; f < to; f++) {
    await page.evaluate((t) => window.seek(t), f / timeline.fps);
    const buf = await page.screenshot({ type: "jpeg", quality: 93 });
    if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once("drain", r));
  }
  ff.stdin.end();
  await done;
  await page.context().close();
}

async function preview(browser) {
  // One still per scene (mid-scene) plus a contact sheet: quick look without a full render.
  const { page } = await openPage(browser);
  const dir = path.join(OUT, "preview");
  fs.mkdirSync(dir, { recursive: true });
  const shots = [];
  for (const sc of timeline.scenes) {
    for (const frac of [0.45, 0.95]) {
      const t = sc.start + (sc.end - sc.start) * frac;
      await page.evaluate((x) => window.seek(x), t);
      const f = path.join(dir, `${sc.id}-${Math.round(frac * 100)}.jpg`);
      await page.screenshot({ path: f, type: "jpeg", quality: 85 });
      shots.push(f);
    }
  }
  await page.context().close();
  const sheet = path.join(dir, "sheet.jpg");
  execFileSync("ffmpeg", ["-v", "error", "-y", ...shots.flatMap((f) => ["-i", f]), "-filter_complex",
    shots.map((_, i) => `[${i}:v]scale=270:-1[v${i}]`).join(";") + ";" + shots.map((_, i) => `[v${i}]`).join("") +
    `xstack=inputs=${shots.length}:layout=${shots.map((_, i) => `${(i % 6) * 270}_${Math.floor(i / 6) * 480}`).join("|")}`, sheet]);
  console.log(`Preview -> ${path.relative(ROOT, sheet)}`);
}

async function main() {
  const browser = await chromium.launch();
  const { page, warnings } = await openPage(browser);
  await page.context().close();
  for (const w of warnings) console.warn("  ! " + w);

  if (flag("--preview")) {
    await preview(browser);
    await browser.close();
    return;
  }

  const total = Math.ceil(timeline.duration * timeline.fps);
  const workers = Math.max(1, Math.min(4, os.cpus().length));
  const per = Math.ceil(total / workers);
  const segDir = path.join(OUT, "segments");
  fs.mkdirSync(segDir, { recursive: true });
  console.log(`Rendering ${total} frames (${timeline.duration}s @ ${timeline.fps}fps, ${timeline.width}x${timeline.height}) on ${workers} workers...`);
  const t0 = Date.now();
  const segs = [];
  await Promise.all(Array.from({ length: workers }, (_, i) => {
    const file = path.join(segDir, `seg${i}.mp4`);
    segs.push(file);
    return renderSegment(browser, i * per, Math.min(total, (i + 1) * per), file);
  }));
  await browser.close();
  console.log(`  frames done in ${((Date.now() - t0) / 1000).toFixed(0)}s`);

  const list = path.join(segDir, "list.txt");
  fs.writeFileSync(list, segs.map((s) => `file '${s}'`).join("\n"));
  const music = opt("--music");
  const final = path.join(OUT, "reel.mp4");
  const ff = ["-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", list, "-i", path.join(OUT, "voice.wav")];
  if (music) {
    // Music quietly under the voice, ducked further whenever the voice speaks.
    ff.push("-stream_loop", "-1", "-i", path.resolve(music), "-filter_complex",
      "[2:a]volume=0.22[m];[m][1:a]sidechaincompress=threshold=0.03:ratio=6:release=400[duck];[duck][1:a]amix=inputs=2:normalize=0[a]",
      "-map", "0:v", "-map", "[a]");
  } else {
    ff.push("-map", "0:v", "-map", "1:a");
  }
  ff.push("-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-t", String(timeline.duration), "-movflags", "+faststart", final);
  execFileSync("ffmpeg", ff);
  fs.rmSync(segDir, { recursive: true, force: true });
  console.log(`Reel -> ${path.relative(ROOT, final)}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
