// Voice + word timings in one step, straight from ElevenLabs (no Whisper needed).
// Usage: npm run voice:timed -- <reel-id> [--voice <voice id>] [--model eleven_v4] [--lang hi]
// Reads  projects/<id>/voice.txt    (what is spoken; e.g. Devanagari for Hindi)
//        projects/<id>/captions.txt (what is shown; same LINES, same order)
// Writes public/reels/<id>/vo.wav, captions.words.json, mouth.json
// Good for Hinglish: the voice reads Devanagari, captions stay in Roman script.
import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { root, requireKey } from "../env.mjs";
import { writeMouthJson } from "../lipsync-lib.mjs";

const args = process.argv.slice(2);
const id = args.find((a) => !a.startsWith("--"));
const opt = (n, d) => { const i = args.indexOf(`--${n}`); return i >= 0 ? args[i + 1] : d; };
if (!id) { console.error("Usage: npm run voice:timed -- <reel-id> [--voice <id>] [--model eleven_v4] [--lang hi]"); process.exit(1); }

const key = requireKey("ELEVENLABS_API_KEY", "https://elevenlabs.io/app/settings/api-keys");
const voice = opt("voice", process.env.ELEVENLABS_VOICE_ID || "");
if (!voice) { console.error("Pass --voice <voice id> or set ELEVENLABS_VOICE_ID in .env."); process.exit(1); }
const model = opt("model", "eleven_v4");
const lang = opt("lang", "hi");

const readLines = (f) => fs.readFileSync(path.join(root, "projects", id, f), "utf8").split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
const spoken = readLines("voice.txt");
const shown = readLines("captions.txt");
if (spoken.length !== shown.length) {
  console.error(`voice.txt has ${spoken.length} lines but captions.txt has ${shown.length}. They must match line by line.`);
  process.exit(1);
}
const text = spoken.join("\n");

const res = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${voice}/with-timestamps?output_format=mp3_44100_128`, {
  method: "POST",
  headers: { "xi-api-key": key, "Content-Type": "application/json" },
  body: JSON.stringify({ text, model_id: model, language_code: lang, voice_settings: { stability: 0.45, similarity_boost: 0.8 } }),
});
if (!res.ok) { console.error("ElevenLabs error", res.status, await res.text()); process.exit(1); }
const j = await res.json();

const dir = path.join(root, "public/reels", id);
fs.mkdirSync(dir, { recursive: true });
const tmp = path.join(dir, "_raw.mp3");
const out = path.join(dir, "vo.wav");
fs.writeFileSync(tmp, Buffer.from(j.audio_base64, "base64"));
const ff = spawnSync("ffmpeg", ["-y", "-v", "error", "-i", tmp, "-ac", "1", "-ar", "44100", "-af", "loudnorm=I=-16:TP=-1.5:LRA=11", out], { encoding: "utf8" });
fs.rmSync(tmp);
if (ff.status !== 0) { console.error("ffmpeg failed:", ff.stderr); process.exit(1); }
const secs = parseFloat(spawnSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", out], { encoding: "utf8" }).stdout);

// Character index in `text` -> start/end seconds.
const al = j.alignment;
const ok = al && al.characters.join("") === text;
if (!ok) console.warn("Alignment did not match the text exactly; timings are spread evenly instead.");
const starts = [], ends = [];
if (ok) al.characters.forEach((ch, k) => { for (let u = 0; u < ch.length; u++) { starts.push(al.character_start_times_seconds[k]); ends.push(al.character_end_times_seconds[k]); } });
const at = (i, end) => (ok ? (end ? ends : starts)[i] : ((i + (end ? 1 : 0)) / text.length) * secs);

const isWord = (s) => /[\p{L}\p{N}]/u.test(s);
const words = [];
let offset = 0;
let exact = 0;
spoken.forEach((line, li) => {
  const tts = [...line.matchAll(/\S+/g)].filter((m) => isWord(m[0])).map((m) => ({ a: offset + m.index, b: offset + m.index + m[0].length - 1 }));
  offset += line.length + 1;
  const cap = shown[li].split(/\s+/).filter(isWord); // drop lone dashes etc.
  const lineStart = at(tts[0].a, false);
  const lineEnd = at(tts[tts.length - 1].b, true);
  if (cap.length === tts.length) {
    exact++;
    cap.forEach((w, k) => words.push({ word: w, start: at(tts[k].a, false), end: at(tts[k].b, true) }));
  } else {
    // e.g. "₹5,000" (1 word on screen) vs "पाँच हज़ार रुपये" (3 spoken): spread the line by word length.
    const total = cap.reduce((s, w) => s + w.length, 0);
    let t = lineStart;
    cap.forEach((w) => { const d = ((lineEnd - lineStart) * w.length) / total; words.push({ word: w, start: t, end: t + d }); t += d; });
  }
});
const r3 = (n) => Math.round(n * 1000) / 1000;
fs.writeFileSync(path.join(dir, "captions.words.json"), JSON.stringify(words.map((w) => ({ word: w.word, start: r3(w.start), end: r3(w.end) })), null, 2) + "\n");
const m = await writeMouthJson(out, path.join(dir, "mouth.json"));

console.log(`Wrote ${out} (${secs.toFixed(1)} s)${secs > 60 ? "  Warning: longer than 60 s." : ""}`);
console.log(`captions.words.json: ${words.length} words; ${exact}/${spoken.length} lines matched word for word, the rest spread by length. mouth.json: ${m.length} frames.`);
console.log(`Characters billed: about ${text.length}. Next: build the scenes, then npm run check -- ${id}`);
