// Step 1: script.json -> ElevenLabs voice (one request per scene) -> out/<slug>/timeline.json + voice.wav
// Usage: node scripts/voice.mjs [path/to/script.json] [--dry-run]
//   --dry-run: no API call, silent audio with estimated timing (check the layout without spending credits)
// The SDK reads ELEVENLABS_API_KEY from the environment; never put the key in code.
import { ElevenLabsClient } from "@elevenlabs/elevenlabs-js";
import { execFileSync } from "node:child_process";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";

const ROOT = path.resolve(import.meta.dirname, "..");
const argv = process.argv.slice(2);
const DRY = argv.includes("--dry-run");
const scriptPath = path.resolve(argv.find((a) => a.endsWith(".json")) || path.join(ROOT, "reel/script.json"));
const S = JSON.parse(fs.readFileSync(scriptPath, "utf8"));
const OUT = path.join(ROOT, "out", S.slug);
const AUDIO = path.join(OUT, DRY ? "audio-dry" : "audio");
fs.mkdirSync(AUDIO, { recursive: true });

const client = DRY ? null : new ElevenLabsClient();
const ttsTexts = S.scenes.map((sc) => sc.chunks.map((c) => c.tts).join(" "));

const isWord = (s) => /[\p{L}\p{N}]/u.test(s);
// Tokens with their character spans inside `str`, starting at `offset`.
function tokens(str, offset = 0) {
  const out = [];
  for (const m of str.matchAll(/\S+/g)) {
    if (isWord(m[0])) out.push({ w: m[0], a: offset + m.index, b: offset + m.index + m[0].length - 1 });
  }
  return out;
}

function probeDuration(file) {
  return parseFloat(
    execFileSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]).toString()
  );
}

async function synthScene(i) {
  const sc = S.scenes[i];
  const tag = String(i + 1).padStart(2, "0");
  const mp3 = path.join(AUDIO, `s${tag}.mp3`);
  const meta = path.join(AUDIO, `s${tag}.json`);
  const req = {
    text: ttsTexts[i],
    modelId: S.voice.model,
    languageCode: S.voice.language,
    outputFormat: "mp3_44100_128",
    voiceSettings: { stability: S.voice.stability, similarityBoost: S.voice.similarityBoost },
    previousText: ttsTexts[i - 1],
    nextText: ttsTexts[i + 1],
  };
  const hash = crypto.createHash("sha256").update(JSON.stringify([S.voice.voiceId, req])).digest("hex");

  if (DRY) {
    const est = (req.text.length * 0.075).toFixed(2);
    execFileSync("ffmpeg", ["-v", "error", "-y", "-f", "lavfi", "-i", "anullsrc=r=44100:cl=mono", "-t", est, mp3]);
    console.log(`  scene ${tag} ${sc.id}: dry run, ${est}s of silence`);
    return { mp3, alignment: null };
  }
  // Cache: re-running only spends credits on scenes whose text or voice changed.
  if (fs.existsSync(mp3) && fs.existsSync(meta)) {
    const cached = JSON.parse(fs.readFileSync(meta, "utf8"));
    if (cached.hash === hash) {
      console.log(`  scene ${tag} ${sc.id}: cached`);
      return { mp3, alignment: cached.alignment };
    }
  }
  console.log(`  scene ${tag} ${sc.id}: generating (${req.text.length} chars)`);
  const res = await client.textToSpeech.convertWithTimestamps(S.voice.voiceId, req);
  fs.writeFileSync(mp3, Buffer.from(res.audioBase64, "base64"));
  fs.writeFileSync(meta, JSON.stringify({ hash, alignment: res.alignment ?? null }));
  return { mp3, alignment: res.alignment ?? null };
}

// Turn character alignment into caption chunks with per-word timings (scene-local seconds).
function timeChunks(sc, text, alignment, duration) {
  const ok = alignment && alignment.characters.join("") === text;
  if (!ok && !DRY) console.warn(`  ! alignment mismatch in "${sc.id}", using proportional timing`);
  // Map every string index to its alignment entry (an entry may hold more than one code unit).
  const starts = [];
  const ends = [];
  if (ok) {
    alignment.characters.forEach((ch, k) => {
      for (let u = 0; u < ch.length; u++) {
        starts.push(alignment.characterStartTimesSeconds[k]);
        ends.push(alignment.characterEndTimesSeconds[k]);
      }
    });
  }
  const at = (idx, end) =>
    ok ? (end ? ends : starts)[idx] : ((idx + (end ? 1 : 0)) / text.length) * duration;

  let pos = 0;
  return sc.chunks.map((c) => {
    const tts = tokens(c.tts, pos);
    pos += c.tts.length + 1; // +1 for the joining space
    const start = at(tts[0].a, false);
    const end = at(tts[tts.length - 1].b, true);

    const shown = c.text.split(/\s+/).filter(Boolean).map((raw) => ({
      w: raw.replace(/\*/g, ""),
      accent: raw.includes("*"),
    }));
    const spoken = shown.filter((x) => isWord(x.w));
    let k = 0;
    if (spoken.length === tts.length) {
      // Same word count: map each caption word onto its spoken word.
      for (const x of spoken) {
        x.start = at(tts[k].a, false);
        x.end = at(tts[k].b, true);
        k++;
      }
    } else {
      // Different counts (e.g. "₹48,000" vs "अड़तालीस हज़ार रुपये"): spread by word length.
      const total = spoken.reduce((s, x) => s + x.w.length, 0);
      let t = start;
      for (const x of spoken) {
        const d = ((end - start) * x.w.length) / total;
        x.start = t;
        x.end = t + d;
        t += d;
      }
    }
    // Punctuation-only tokens (like "—") inherit the previous word's timing.
    let prev = start;
    for (const x of shown) {
      if (x.start == null) x.start = x.end = prev;
      prev = x.end;
    }
    return { text: c.text, start, end, words: shown };
  });
}

const r3 = (n) => Math.round(n * 1000) / 1000;

async function main() {
  console.log(`Voice: ${S.title} (${S.scenes.length} scenes)`);
  const { sceneGap, outro } = S.video;
  let cursor = 0.25; // short lead-in before the first word
  const scenes = [];
  const audioInputs = [];

  for (let i = 0; i < S.scenes.length; i++) {
    const sc = S.scenes[i];
    const { mp3, alignment } = await synthScene(i);
    const dur = probeDuration(mp3);
    const chunks = timeChunks(sc, ttsTexts[i], alignment, dur);
    const shift = (x) => r3(x + cursor);
    scenes.push({
      id: sc.id,
      label: sc.label,
      tint: sc.tint,
      start: r3(cursor),
      end: r3(cursor + dur),
      chunks: chunks.map((c) => ({
        text: c.text,
        start: shift(c.start),
        end: shift(c.end),
        words: c.words.map((w) => ({ w: w.w, accent: w.accent, start: shift(w.start), end: shift(w.end) })),
      })),
    });
    audioInputs.push({ mp3, delayMs: Math.round(cursor * 1000) });
    cursor += dur + sceneGap;
  }
  const duration = r3(cursor - sceneGap + outro);

  // Lay every scene's audio onto one track at its start time.
  const voiceWav = path.join(OUT, "voice.wav");
  const args = ["-v", "error", "-y"];
  audioInputs.forEach((a) => args.push("-i", a.mp3));
  const filters = audioInputs.map((a, i) => `[${i}:a]adelay=${a.delayMs}|${a.delayMs}[a${i}]`);
  filters.push(
    audioInputs.map((_, i) => `[a${i}]`).join("") +
      `amix=inputs=${audioInputs.length}:normalize=0,apad=whole_dur=${duration}[out]`
  );
  args.push("-filter_complex", filters.join(";"), "-map", "[out]", "-ar", "44100", "-ac", "2", voiceWav);
  execFileSync("ffmpeg", args);

  const timeline = {
    title: S.title,
    handle: S.handle,
    width: S.video.width,
    height: S.video.height,
    fps: S.video.fps,
    duration,
    scenes,
  };
  fs.writeFileSync(path.join(OUT, "timeline.json"), JSON.stringify(timeline, null, 2));
  console.log(`Done: ${duration.toFixed(1)}s -> ${path.relative(ROOT, OUT)}/timeline.json, voice.wav`);
}

main().catch((e) => {
  console.error("Voice step failed:", e.statusCode ?? "", e.body ? JSON.stringify(e.body) : e.message);
  process.exit(1);
});
