// Placeholder voice for building scenes before the real voice exists (no API, no credits).
// Usage: npm run voice:dry -- <reel-id> [--wps 2.4]
// Reads projects/<id>/captions.txt. Writes public/reels/<id>/vo.wav (silence), captions.words.json
// (estimated times, same word split as the caption sync) and mouth.json (closed).
// Replace with the real voice later: `npm run voice:timed` or `npm run voice -- <id> --provider own` + `npm run sync`.
import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { root } from "../env.mjs";

const args = process.argv.slice(2);
const id = args.find((a) => !a.startsWith("--"));
const opt = (n, d) => { const i = args.indexOf(`--${n}`); return i >= 0 ? args[i + 1] : d; };
if (!id) { console.error("Usage: npm run voice:dry -- <reel-id> [--wps 2.4]"); process.exit(1); }
const wps = parseFloat(opt("wps", "2.4"));

const lines = fs.readFileSync(path.join(root, "projects", id, "captions.txt"), "utf8").split(/\r?\n/).filter((l) => l.trim());
const words = [];
let t = 0.25;
for (const line of lines) {
  const toks = [];
  for (const tok of line.split(/\s+/).filter(Boolean)) {
    if (/[\p{L}\p{N}]/u.test(tok)) toks.push(tok);
    else if (toks.length) toks[toks.length - 1] += tok; // lone "—" sticks to the word before, like align.py
  }
  const avg = toks.reduce((s, w) => s + w.length, 0) / toks.length;
  for (const w of toks) {
    const d = (1 / wps) * (0.6 + 0.4 * (w.length / avg));
    words.push({ word: w, start: +t.toFixed(3), end: +(t + d * 0.9).toFixed(3) });
    t += d;
  }
  t += 0.3; // pause between lines
}
const dir = path.join(root, "public/reels", id);
fs.mkdirSync(dir, { recursive: true });
fs.writeFileSync(path.join(dir, "captions.words.json"), JSON.stringify(words, null, 2) + "\n");
fs.writeFileSync(path.join(dir, "mouth.json"), JSON.stringify(new Array(Math.ceil(t * 30)).fill(0)));
spawnSync("ffmpeg", ["-y", "-v", "error", "-f", "lavfi", "-i", "anullsrc=r=44100:cl=mono", "-t", t.toFixed(2), path.join(dir, "vo.wav")]);
console.log(`Dry run: ${words.length} words, ~${t.toFixed(1)} s of silence. Build scenes, then swap in the real voice.`);
