# Reel maker

Script → ElevenLabs voice → animated 9:16 Instagram Reel (MP4, 1080×1920).

## Files
- `reel/script.json` — the Reel content. Each chunk is one caption line: `text` is what viewers read (Hinglish, `*stars*` = accent colour), `tts` is what the voice speaks (Devanagari).
- `scripts/voice.mjs` — makes the voice for each scene with ElevenLabs (with word timings) → `out/<slug>/voice.wav` + `timeline.json`. Scenes whose text hasn't changed are reused from cache, so re-runs don't spend credits again.
- `scenes/reel.html` — all the animation (phone, chat, cards, karaoke captions). Animations are tied to the spoken words.
- `scripts/render.mjs` — renders every frame with Playwright and joins it with the voice using ffmpeg → `out/<slug>/reel.mp4`.

## Run
```bash
npm install
npm run voice:dry   # no API call: silent audio, rough timing (free layout check)
npm run preview     # still frames -> out/<slug>/preview/sheet.jpg
npm run voice       # real voice with ElevenLabs (uses credits)
npm run render      # final MP4 -> out/<slug>/reel.mp4
node scripts/render.mjs --music path/to/music.mp3   # optional background music, ducked under the voice
```

Needs Node 22+, ffmpeg and Playwright (Chromium). The ElevenLabs key comes from the `ELEVENLABS_API_KEY` environment variable — never put it in code.
Change `handle` in `reel/script.json` to your Instagram handle.
