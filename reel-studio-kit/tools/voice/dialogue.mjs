// Multi-voice dialogue: one ElevenLabs request per line, in that character's voice, with word timings.
// Usage: npm run voice:dialogue -- <reel-id>
// Reads  projects/<id>/dialogue.json:
//   { "voices": { "A": "<voice id>", "R": "<voice id>" }, "model": "eleven_v4", "lang": "hi", "gap": 0.3,
//     "lines": [ { "who": "A", "text": "Hinglish shown on screen", "tts": "देवनागरी जो बोला जाए" }, ... ] }
// Writes public/reels/<id>/vo.wav, captions.words.json (each word has "who" and "line"),
//        lines.json ([{ who, start, end, text }]) and mouth.<who>.json (mouth openness per frame per speaker).
// Lines are cached by content, so editing one line only re-voices that line.
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { root, requireKey } from "../env.mjs";
import { FPS, mouthFromAudio } from "../lipsync-lib.mjs";

const id = process.argv.slice(2).find((a) => !a.startsWith("--"));
if (!id) { console.error("Usage: npm run voice:dialogue -- <reel-id>"); process.exit(1); }
const key = requireKey("ELEVENLABS_API_KEY", "https://elevenlabs.io/app/settings/api-keys");
const D = JSON.parse(fs.readFileSync(path.join(root, "projects", id, "dialogue.json"), "utf8"));
const dir = path.join(root, "public/reels", id);
const cache = path.join(dir, "lines");
fs.mkdirSync(cache, { recursive: true });

const probe = (f) => parseFloat(spawnSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", f], { encoding: "utf8" }).stdout);
const isWord = (s) => /[\p{L}\p{N}]/u.test(s);

async function voiceLine(L, i) {
  const voice = D.voices[L.who];
  const body = { text: L.tts, model_id: D.model || "eleven_v4", language_code: D.lang || "hi", voice_settings: { stability: 0.4, similarity_boost: 0.8 } };
  const hash = crypto.createHash("sha256").update(JSON.stringify([voice, body])).digest("hex").slice(0, 16);
  const mp3 = path.join(cache, `${String(i).padStart(2, "0")}-${hash}.mp3`);
  const meta = mp3.replace(/\.mp3$/, ".json");
  if (fs.existsSync(mp3) && fs.existsSync(meta)) return { mp3, alignment: JSON.parse(fs.readFileSync(meta, "utf8")) };
  const res = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${voice}/with-timestamps?output_format=mp3_44100_128`, {
    method: "POST",
    headers: { "xi-api-key": key, "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (!res.ok) { console.error(`Line ${i + 1} (${L.who}): ElevenLabs error ${res.status} ${await res.text()}`); process.exit(1); }
  const j = await res.json();
  fs.writeFileSync(mp3, Buffer.from(j.audio_base64, "base64"));
  fs.writeFileSync(meta, JSON.stringify(j.alignment ?? null));
  console.log(`  line ${i + 1} (${L.who}): voiced, ${L.tts.length} chars`);
  return { mp3, alignment: j.alignment ?? null };
}

const gap = D.gap ?? 0.3;
let cursor = 0.2;
const words = [];
const lines = [];
const parts = [];
for (let i = 0; i < D.lines.length; i++) {
  const L = D.lines[i];
  const { mp3, alignment } = await voiceLine(L, i);
  const dur = probe(mp3);
  const text = L.tts;
  const ok = alignment && alignment.characters.join("") === text;
  const st = [], en = [];
  if (ok) alignment.characters.forEach((ch, k) => { for (let u = 0; u < ch.length; u++) { st.push(alignment.character_start_times_seconds[k]); en.push(alignment.character_end_times_seconds[k]); } });
  const at = (idx, end) => (ok ? (end ? en : st)[idx] : ((idx + (end ? 1 : 0)) / text.length) * dur);
  const tts = [...text.matchAll(/\S+/g)].filter((m) => isWord(m[0])).map((m) => ({ a: m.index, b: m.index + m[0].length - 1 }));
  const cap = [];
  for (const tok of L.text.split(/\s+/).filter(Boolean)) {
    if (isWord(tok)) cap.push(tok);
    else if (cap.length) cap[cap.length - 1] += tok;
  }
  const s0 = at(tts[0].a, false), s1 = at(tts[tts.length - 1].b, true);
  if (cap.length === tts.length) cap.forEach((w, k) => words.push({ word: w, start: cursor + at(tts[k].a, false), end: cursor + at(tts[k].b, true), who: L.who, line: i }));
  else {
    const tot = cap.reduce((s, w) => s + w.length, 0);
    let t = s0;
    for (const w of cap) { const d = ((s1 - s0) * w.length) / tot; words.push({ word: w, start: cursor + t, end: cursor + t + d, who: L.who, line: i }); t += d; }
  }
  lines.push({ who: L.who, start: cursor, end: cursor + dur, text: L.text });
  parts.push({ mp3, offset: cursor, who: L.who });
  cursor += dur + gap;
}
const total = cursor - gap + 0.4;

// One track with every line at its offset.
const out = path.join(dir, "vo.wav");
const args = ["-y", "-v", "error"];
parts.forEach((p) => args.push("-i", p.mp3));
const fc = parts.map((p, i) => `[${i}:a]adelay=${Math.round(p.offset * 1000)}|${Math.round(p.offset * 1000)}[a${i}]`);
fc.push(parts.map((_, i) => `[a${i}]`).join("") + `amix=inputs=${parts.length}:normalize=0,apad=whole_dur=${total.toFixed(2)},loudnorm=I=-16:TP=-1.5:LRA=11[o]`);
args.push("-filter_complex", fc.join(";"), "-map", "[o]", "-ac", "1", "-ar", "44100", out);
const r = spawnSync("ffmpeg", args, { encoding: "utf8" });
if (r.status !== 0) { console.error(r.stderr); process.exit(1); }

// Mouth per speaker: zero while the other one talks.
const frames = Math.ceil(total * FPS);
const mouths = {};
for (const who of Object.keys(D.voices)) mouths[who] = new Array(frames).fill(0);
for (const p of parts) {
  const m = mouthFromAudio(p.mp3);
  const o = Math.round(p.offset * FPS);
  m.forEach((v, k) => { if (o + k < frames) mouths[p.who][o + k] = v; });
}
for (const [who, m] of Object.entries(mouths)) fs.writeFileSync(path.join(dir, `mouth.${who}.json`), JSON.stringify(m));
const r3 = (n) => Math.round(n * 1000) / 1000;
fs.writeFileSync(path.join(dir, "captions.words.json"), JSON.stringify(words.map((w) => ({ ...w, start: r3(w.start), end: r3(w.end) })), null, 2) + "\n");
fs.writeFileSync(path.join(dir, "lines.json"), JSON.stringify(lines.map((l) => ({ ...l, start: r3(l.start), end: r3(l.end) })), null, 2) + "\n");
console.log(`Wrote ${out} (${total.toFixed(1)} s), ${lines.length} lines, ${words.length} words, mouths for ${Object.keys(mouths).join(", ")}.`);
