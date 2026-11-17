// Reel "solvix-crm" (~60 s): promo for the Pharma Sales CRM by Solvix Web.
// Main feature first: no language barrier between client and sales team (client writes in Tamil, Malayalam,
// Gujarati...; the CRM detects language + request and offers the reply in that language, with its meaning in
// the rep's language). Then 6 more fixes. All screens are REAL: the CRM run locally with its demo data; client
// messages and the away reply went through the CRM's own Inbound service, only the send to Meta was stubbed.
// Screens: public/reels/solvix-crm/shots (430x932 CSS px @2x). Voice: George, sped up 10%.
// Music + SFX: Mixkit free licences (npm run audio-assets).
import React from "react";
import { Audio, Easing, Img, interpolate, random, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { timing } from "../../timing";
import words from "../../../public/reels/solvix-crm/captions.words.json";

export const t = timing(words);
const at = (i: number) => t.at(i, 0);
export const durationInFrames = t.duration(0.85);
const LINES = [0, 12, 22, 35, 45, 59, 68, 76, 87, 96, 106, 119, 130, 135, 143, words.length];
const L = (i: number) => at(LINES[i]);

const C = { bg: "#070A08", ink: "#F2F5F1", dim: "#8E9A91", green: "#2BD94A", green2: "#1E7F3C", red: "#FF4D4D", card: "#111712", line: "#223026" };
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";
const shot = (n: string) => staticFile(`reels/solvix-crm/shots/${n}.jpg`);

const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    fps,
    sp: (a: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - a, fps, config: cfg }),
    fade: (a: number, len = 6) => interpolate(frame - a, [0, len], [0, 1], CL),
  };
};

// ---------- phone states: which real screen, zoom focus (CSS px of a 430x932 screen), taps ----------
type State = { from: number; img: string; zoom?: number; cx?: number; cy?: number; tap?: { x: number; y: number; at: number }; ring?: { x: number; y: number; w: number; h: number; at: number } };
const PHONE_BEATS: { from: number; to: number; states: State[] }[] = [
  {
    // L2-L5: client writes in any language -> CRM detects language + request -> reply in that language, meaning in
    // the rep's app language (English / Hindi / Hinglish, chosen on the profile page) -> Bhejein
    from: L(2), to: L(6),
    states: [
      { from: L(2), img: "30_lang_inbox", zoom: 1.1, cx: 200, cy: 330, ring: { x: 22, y: 196, w: 250, h: 26, at: at(25) } },
      { from: at(29), img: "30b_lang_inbox_more", zoom: 1.1, cx: 200, cy: 360, ring: { x: 22, y: 210, w: 300, h: 296, at: at(31) } },
      { from: L(3), img: "31_lang_ta", zoom: 1.12, cx: 200, cy: 470, ring: { x: 38, y: 503, w: 354, h: 68, at: at(36) } },
      { from: at(40), img: "31_lang_ta", zoom: 1.15, cx: 205, cy: 590, ring: { x: 38, y: 567, w: 354, h: 42, at: at(41) } },
      // L4: the rep picks the app language: English -> Hindi -> Hinglish, same Tamil chat
      { from: L(4), img: "35_profile_lang_en", zoom: 1.15, cx: 200, cy: 330, ring: { x: 26, y: 324, w: 378, h: 48, at: at(46) }, tap: { x: 215, y: 349, at: at(47) } },
      { from: at(48), img: "31_lang_ta", zoom: 1.12, cx: 200, cy: 520, ring: { x: 28, y: 438, w: 374, h: 172, at: at(48) + 4 } },
      { from: at(50), img: "31_lang_ta_hi", zoom: 1.12, cx: 200, cy: 520, ring: { x: 28, y: 438, w: 374, h: 172, at: at(50) + 4 } },
      { from: at(53), img: "31_lang_ta_hing", zoom: 1.12, cx: 200, cy: 520, ring: { x: 28, y: 438, w: 374, h: 188, at: at(53) + 4 } },
      // L5: tap Bhejein -> reply goes out in Tamil
      { from: L(5), img: "31b_lang_ta_hing_send", zoom: 1.12, cx: 200, cy: 560, tap: { x: 74, y: 641, at: at(60) + 2 } },
      { from: at(61) + 4, img: "32_lang_ta_sent", zoom: 1.12, cx: 215, cy: 470, ring: { x: 104, y: 416, w: 302, h: 128, at: at(63) } },
    ],
  },
  {
    // L7: every lead comes in by itself, tagged with its platform
    from: L(7), to: L(8),
    states: [
      { from: L(7), img: "02_integrations", zoom: 1.35, cx: 215, cy: 260, ring: { x: 18, y: 160, w: 394, h: 250, at: L(7) + 10 } },
      { from: at(78), img: "03b_leads_source_selected", zoom: 1.2, cx: 215, cy: 560, tap: { x: 215, y: 571, at: at(78) + 4 } },
      { from: at(80), img: "04_leads_indiamart", zoom: 1.3, cx: 300, cy: 690, tap: { x: 73, y: 691, at: at(80) - 2 }, ring: { x: 330, y: 650, w: 95, h: 70, at: at(83) } },
    ],
  },
  {
    // L8: today, overdue and next-7-days follow-ups
    from: L(8), to: L(9),
    states: [
      { from: L(8), img: "05_dashboard_followups", zoom: 1.25, cx: 215, cy: 700, ring: { x: 120, y: 600, w: 130, h: 270, at: at(89) } },
      { from: at(92), img: "08_activity_filled", zoom: 1.25, cx: 215, cy: 640, tap: { x: 62, y: 743, at: at(93) + 4 } },
      { from: at(94), img: "09b_activity_log", zoom: 1.2, cx: 215, cy: 500 },
    ],
  },
  {
    // L9: lead clash by phone / GSTIN, admin decides
    from: L(9), to: L(10),
    states: [
      { from: L(9), img: "06_lead_clash", zoom: 1.7, cx: 215, cy: 140, ring: { x: 10, y: 118, w: 410, h: 44, at: L(9) + 6 } },
      { from: at(100), img: "26_manager_notifications", zoom: 1.12, cx: 215, cy: 260, ring: { x: 12, y: 228, w: 405, h: 82, at: at(101) } },
      { from: at(103), img: "10_clashes", zoom: 1.2, cx: 215, cy: 330, tap: { x: 180, y: 345, at: at(104) } },
    ],
  },
  {
    // L10: away cover replies in the client's language, urgent goes to the manager
    from: L(10), to: L(11),
    states: [
      { from: L(10), img: "20_away_filled", zoom: 1.2, cx: 215, cy: 760, tap: { x: 104, y: 925, at: L(10) + 8 } },
      { from: L(10) + 16, img: "21_away_set", zoom: 1.2, cx: 215, cy: 380 },
      { from: at(109), img: "23_inbox_chat", zoom: 1.35, cx: 215, cy: 640, ring: { x: 100, y: 580, w: 320, h: 180, at: at(111) } },
      { from: at(115), img: "26_manager_notifications", zoom: 1.12, cx: 215, cy: 200, ring: { x: 12, y: 148, w: 405, h: 70, at: at(116) } },
    ],
  },
  {
    // L11: GST quotation PDF from the product list, one click to WhatsApp
    from: L(11), to: L(12),
    states: [
      { from: L(11), img: "11_quote_empty", zoom: 1.3, cx: 215, cy: 200, tap: { x: 215, y: 267, at: L(11) + 6 } },
      { from: at(121), img: "12_quote_filled", zoom: 1.25, cx: 215, cy: 420, ring: { x: 18, y: 300, w: 400, h: 130, at: at(122) } },
      { from: at(124), img: "13_quote_saved", zoom: 1.2, cx: 215, cy: 360, tap: { x: 200, y: 197, at: at(128) } },
    ],
  },
  {
    // L12: targets, attendance, analytics live
    from: L(12), to: L(13),
    states: [
      { from: L(12), img: "01_dashboard", zoom: 1.2, cx: 215, cy: 560, ring: { x: 10, y: 470, w: 410, h: 170, at: L(12) + 4 } },
      { from: at(130) + 10, img: "14_targets", zoom: 1.15, cx: 215, cy: 360 },
      { from: at(131), img: "15_attendance", zoom: 1.1, cx: 215, cy: 400 },
      { from: at(132), img: "16_analytics", zoom: 1.1, cx: 215, cy: 400 },
    ],
  },
];

// the 14 languages that have ready replies in the CRM (crm/README: "14 Indian languages")
const LANG_TILES = (
  [
    ["English", "English", 68], ["हिन्दी", "Hindi", 69], ["Hinglish", "Hinglish", 70], ["தமிழ்", "Tamil", 71], ["తెలుగు", "Telugu", 72], ["বাংলা", "Bengali", 73],
    ["മലയാളം", "Malayalam", -1], ["ગુજરાતી", "Gujarati", -1], ["ಕನ್ನಡ", "Kannada", -1], ["मराठी", "Marathi", -1], ["ਪੰਜਾਬੀ", "Punjabi", -1], ["ଓଡ଼ିଆ", "Odia", -1],
    ["অসমীয়া", "Assamese", -1], ["اردو", "Urdu", -1],
  ] as [string, string, number][]
).map(([native, en, w], k) => ({ native, en, at: w >= 0 ? at(w) : at(73) + 8 + (k - 6) * 3 }));

// ---------- sound ----------
type Cue = { at: number; name: string; vol?: number; len?: number };
const SFX: Cue[] = [
  { at: 2, name: "impact", vol: 0.3 },
  // hook: client messages in three languages, the rep is lost
  ...[0, 4, 6].map((i) => ({ at: i === 0 ? 1 : at(i), name: "message", vol: 0.45 })),
  { at: at(10), name: "buzz", vol: 0.25 },
  // the language wall breaks
  { at: L(1), name: "whoosh", vol: 0.3 },
  { at: at(19), name: "impact", vol: 0.55 },
  { at: at(19) + 8, name: "success", vol: 0.4 },
  // 14 languages pop in
  ...LANG_TILES.map((x) => ({ at: x.at, name: "pop", vol: 0.32 })),
  // phones: whoosh in, then a click/pop on every state, taps and rings
  ...PHONE_BEATS.map((b) => ({ at: b.from - 3, name: "whoosh", vol: 0.35 })),
  ...PHONE_BEATS.flatMap((b) => b.states.slice(1).map((s) => ({ at: s.from, name: "ui", vol: 0.45 }))),
  ...PHONE_BEATS.flatMap((b) => b.states.filter((s) => s.tap).map((s) => ({ at: s.tap!.at, name: "click", vol: 0.5 }))),
  ...PHONE_BEATS.flatMap((b) => b.states.filter((s) => s.ring).map((s) => ({ at: s.ring!.at, name: "tick", vol: 0.28 }))),
  { at: at(61) + 4, name: "message", vol: 0.55 },
  { at: at(115) + 2, name: "message", vol: 0.45 },
  { at: at(124) + 6, name: "pop", vol: 0.5 },
  { at: at(128) + 4, name: "message", vol: 0.55 },
  { at: L(13), name: "whoosh", vol: 0.35 },
  { at: at(139), name: "success", vol: 0.4 },
  { at: L(14), name: "whoosh", vol: 0.35 },
  { at: at(147), name: "impact", vol: 0.4 },
  { at: at(147) + 6, name: "typing", vol: 0.3, len: 20 },
  { at: at(147) + 26, name: "message", vol: 0.5 },
];

const SoundTrack: React.FC = () => {
  const { fps } = useVideoConfig();
  const W = words as { start: number; end: number }[];
  const talking = (f: number) => W.some((w) => f / fps >= w.start - 0.15 && f / fps <= w.end + 0.25);
  return (
    <>
      <Audio src={staticFile("music/hiphop-02.mp3")} volume={(f) => (talking(f) ? 0.08 : 0.2) * interpolate(f, [0, 15], [0.3, 1], CL) * interpolate(f, [durationInFrames - 25, durationInFrames], [1, 0], CL)} />
      <Audio src={staticFile("reels/solvix-crm/vo.wav")} />
      {SFX.map((c, i) => (
        <Sequence key={i} from={Math.max(0, c.at)} durationInFrames={c.len ?? 60} layout="none">
          <Audio src={staticFile(`sfx/${c.name}.mp3`)} volume={c.vol ?? 0.5} />
        </Sequence>
      ))}
    </>
  );
};

// ---------- chrome ----------
const Backdrop: React.FC = () => {
  const { frame } = useA();
  return (
    <div style={{ position: "absolute", inset: 0, background: C.bg }}>
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(45% 28% at ${50 + Math.sin(frame / 70) * 15}% ${55 + Math.cos(frame / 90) * 8}%, ${C.green}26, transparent 70%)` }} />
      <div style={{ position: "absolute", inset: 0, opacity: 0.08, backgroundImage: `linear-gradient(${C.green}55 1px, transparent 1px), linear-gradient(90deg, ${C.green}55 1px, transparent 1px)`, backgroundSize: "60px 60px", maskImage: "radial-gradient(ellipse at 50% 50%, black 20%, transparent 75%)" }} />
    </div>
  );
};

const TopBar: React.FC = () => {
  const { frame } = useA();
  return (
    <>
      <div style={{ position: "absolute", left: 60, top: 128, display: "flex", alignItems: "center", gap: 14, fontFamily: FONT }}>
        <div style={{ width: 62, height: 62, borderRadius: 14, background: "#fff", display: "grid", placeItems: "center", overflow: "hidden" }}>
          <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 58, height: 58 }} />
        </div>
        <div>
          <div style={{ fontSize: 30, fontWeight: 800, color: C.ink, letterSpacing: -0.5 }}>Pharma Sales CRM</div>
          <div style={{ fontFamily: MONO, fontSize: 18, color: C.green, letterSpacing: 2 }}>BY SOLVIX WEB</div>
        </div>
      </div>
      <div style={{ position: "absolute", left: 60, right: 60, top: 206, height: 4, background: "#ffffff14", borderRadius: 2 }}>
        <div style={{ width: `${(frame / durationInFrames) * 100}%`, height: "100%", background: C.green, borderRadius: 2 }} />
      </div>
    </>
  );
};

const Pill: React.FC = () => {
  const { frame, sp } = useA();
  let li = 0;
  for (let i = 0; i < LINES.length - 1; i++) if (frame >= L(i)) li = i;
  const bad = li === 0;
  const text = bad ? "LANGUAGE BARRIER" : li === 4 ? "APP: ENGLISH · हिन्दी · HINGLISH" : li <= 6 ? "MAIN FEATURE · HAR BHASHA" : li <= 12 ? `SOLVIX CRM · FIX ${li - 6}/6` : li === 13 ? "DATA SAFE" : "FREE DEMO";
  const col = bad ? C.red : C.green;
  const s = sp(L(li), { damping: 12, stiffness: 220, mass: 0.6 });
  return (
    <div style={{ position: "absolute", left: 0, right: 0, top: 232, display: "flex", justifyContent: "center" }}>
      <div style={{ transform: `scale(${0.75 + 0.25 * s})`, background: col, color: bad ? "#fff" : C.bg, fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, padding: "6px 18px", borderRadius: 8 }}>{text}</div>
    </div>
  );
};

const BOX = new Set([1, 4, 6, 19, 28, 39, 41, 60, 66, 74, 75, 80, 84, 93, 102, 114, 117, 124, 128, 132, 134, 140, 147]);
const RED = new Set([10, 11, 17, 18, 99, 107]);
const Headline: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const tt = frame / fps;
  const W = words as { word: string; start: number; end: number }[];
  let cur = -1;
  W.forEach((w, i) => { if (w.start <= tt) cur = i; });
  if (cur < 0) return null;
  let li = 0;
  for (let i = 0; i < LINES.length - 1; i++) if (cur >= LINES[i]) li = i;
  const ws = W.slice(LINES[li], LINES[li + 1]);
  const size = ws.length > 12 ? 44 : ws.length > 8 ? 50 : 58;
  return (
    <div data-el="title" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 296, display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "4px 12px", fontFamily: FONT, fontWeight: 800, fontSize: size, lineHeight: 1.15, color: C.ink }}>
      {ws.map((w, k) => {
        const idx = LINES[li] + k;
        const shown = idx <= cur;
        const p = interpolate(tt - w.start, [0, 0.12], [0, 1], CL);
        const box = BOX.has(idx);
        return (
          <span key={idx} style={{ opacity: shown ? 0.25 + 0.75 * p : 0.12, transform: `translateY(${shown ? (1 - p) * 8 : 8}px)`, color: box ? C.bg : RED.has(idx) ? C.red : C.ink, background: box && shown ? C.green : "transparent", borderRadius: 8, padding: box ? "0 10px" : 0, whiteSpace: "nowrap" }}>
            {w.word.replace(/[—,.:]+$/u, "")}
          </span>
        );
      })}
    </div>
  );
};

// ---------- phone with real screens ----------
const SCREEN_W = 560;
const SCREEN_H = 960; // visible window; the screenshot (IMG_H tall) is panned so the focus point sits in view
const K = SCREEN_W / 430; // CSS px -> screen px
const IMG_H = 932 * K;
const Phone: React.FC<{ beat: (typeof PHONE_BEATS)[number] }> = ({ beat }) => {
  const { frame, sp } = useA();
  if (frame < beat.from - 4 || frame >= beat.to) return null;
  const s = sp(beat.from - 4, { damping: 16, stiffness: 140, mass: 0.8 });
  const out = interpolate(frame, [beat.to - 6, beat.to], [0, 1], CL);
  let idx = 0;
  beat.states.forEach((st, i) => { if (frame >= st.from) idx = i; });
  return (
    <div data-scene={`crm-${beat.from}`} data-el="card" data-kind="content" style={{ position: "absolute", left: 218, top: 520, width: SCREEN_W + 24, height: SCREEN_H + 24, borderRadius: 54, background: "#050505", border: "3px solid #2B332D", padding: 12, boxShadow: `0 40px 100px rgba(0,0,0,.7), 0 0 80px ${C.green}22`, transform: `translateY(${(1 - s) * 260 - out * 40}px) scale(${0.94 + 0.06 * s})`, opacity: Math.min(1, s * 1.6) * (1 - out) }}>
      <div style={{ position: "relative", width: SCREEN_W, height: SCREEN_H, borderRadius: 42, clipPath: "inset(0 round 42px)", background: "#F4F6F4" }}>
        {beat.states.map((st, i) => {
          if (i < idx - 1 || i > idx) return null;
          const vis = i === idx ? interpolate(frame - st.from, [0, 6], [0, 1], CL) : 1;
          const z = interpolate(frame - st.from, [0, 22], [1, st.zoom ?? 1], { ...CL, easing: Easing.inOut(Easing.cubic) });
          const ox = (st.cx ?? 215) * K;
          const oy = (st.cy ?? 466) * K;
          const shift = Math.min(0, Math.max(SCREEN_H - IMG_H, SCREEN_H / 2 - oy)); // pan so the focus is in view
          // zoom about the focus point without transforms, so nothing spills outside the screen box
          const mx = (x: number) => ox + (x * K - ox) * z;
          const my = (y: number) => oy + shift + (y * K - oy) * z;
          return (
            <div key={i} style={{ position: "absolute", inset: 0, opacity: vis, backgroundImage: `url(${shot(st.img)})`, backgroundRepeat: "no-repeat", backgroundSize: `${SCREEN_W * z}px ${IMG_H * z}px`, backgroundPosition: `${ox * (1 - z)}px ${oy + shift - oy * z}px` }}>
              <Img src={shot(st.img)} style={{ position: "absolute", width: 1, height: 1, opacity: 0 }} />
              {st.ring && frame >= st.ring.at ? (() => {
                const x0 = Math.max(3, mx(st.ring.x)), x1 = Math.min(SCREEN_W - 3, mx(st.ring.x + st.ring.w));
                const y0 = Math.max(3, my(st.ring.y)), y1 = Math.min(SCREEN_H - 3, my(st.ring.y + st.ring.h));
                return <div style={{ position: "absolute", left: x0, top: y0, width: Math.max(0, x1 - x0), height: Math.max(0, y1 - y0), borderRadius: 14, border: `4px solid ${C.green}`, background: `${C.green}14`, opacity: interpolate(frame - st.ring.at, [0, 5], [0, 1], CL), boxSizing: "border-box" }} />;
              })() : null}
              {st.tap && frame >= st.tap.at && frame < st.tap.at + 18 ? (() => {
                const size = 80 * (0.4 + interpolate(frame - st.tap.at, [0, 18], [0, 1.1], CL));
                const cx = Math.min(SCREEN_W - size / 2, Math.max(size / 2, mx(st.tap.x)));
                const cy = Math.min(SCREEN_H - size / 2, Math.max(size / 2, my(st.tap.y)));
                return <div style={{ position: "absolute", left: cx - size / 2, top: cy - size / 2, width: size, height: size, borderRadius: "50%", background: "rgba(43,217,74,.35)", border: `4px solid ${C.green}`, boxSizing: "border-box", opacity: 1 - interpolate(frame - st.tap.at, [6, 18], [0, 1], CL) }} />;
              })() : null}
            </div>
          );
        })}
      </div>
      <div style={{ position: "absolute", top: 22, left: "50%", width: 110, height: 28, marginLeft: -55, borderRadius: 20, background: "#000" }} />
    </div>
  );
};

// ---------- language story graphics ----------
const Stage: React.FC<{ li: number; name: string; children: React.ReactNode }> = ({ li, name, children }) => {
  const { frame, fade } = useA();
  const end = L(li + 1);
  if (frame < L(li) - 2 || frame >= end) return null;
  return (
    <div data-scene={name} style={{ position: "absolute", left: 70, right: 130, top: 560, height: 820, opacity: fade(L(li) - 2, 6) * (1 - interpolate(frame, [end - 5, end], [0, 1], CL)) }}>
      {children}
    </div>
  );
};

// the same client messages that were sent through the CRM in the demo
const HOOK_MSGS = [
  { who: "Murugan Pharma", text: "வணக்கம், Azithromycin 500 விலை பட்டியல் அனுப்புங்கள்.", lang: "Tamil", x: 0, y: 0, w: 0 },
  { who: "Kerala Med Agencies", text: "നമസ്കാരം, PCM 650 ന്റെ വില എത്രയാണ്?", lang: "Malayalam", x: 150, y: 215, w: 4 },
  { who: "Shree Krishna Medical", text: "નમસ્તે, Cetirizine ના ભાવ મોકલો.", lang: "Gujarati", x: 40, y: 400, w: 6 },
];
const Hook: React.FC = () => {
  const { frame, sp } = useA();
  return (
    <Stage li={0} name="hook">
      <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0 }}>
        {HOOK_MSGS.map((m) => {
          const s = Math.min(1, sp(m.w === 0 ? -6 : at(m.w) - 2, { damping: 12, stiffness: 200, mass: 0.6 }));
          return (
            <div key={m.who} style={{ position: "absolute", left: m.x, top: m.y, width: 690, background: "#fff", color: "#111", borderRadius: "8px 28px 28px 28px", padding: "18px 24px", fontFamily: FONT, boxShadow: "0 20px 50px rgba(0,0,0,.5)", opacity: s, transform: `scale(${0.6 + 0.4 * s})`, transformOrigin: "left top" }}>
              <div style={{ display: "flex", justifyContent: "space-between", fontSize: 22, fontWeight: 700 }}>
                <span style={{ color: "#25A244" }}>WhatsApp · {m.who}</span>
                <span style={{ color: "#999", fontFamily: MONO, fontSize: 18 }}>{m.lang}</span>
              </div>
              <div style={{ fontSize: 34, fontWeight: 600, marginTop: 6, lineHeight: 1.35 }}>{m.text}</div>
            </div>
          );
        })}
        {frame >= at(8) ? (
          <div style={{ position: "absolute", left: 180, top: 630, display: "flex", alignItems: "center", gap: 18, transform: `scale(${Math.min(1, sp(at(8)))})` }}>
            <div style={{ background: C.card, border: `2px solid ${C.red}88`, borderRadius: 18, padding: "16px 24px", fontFamily: FONT, fontWeight: 800, fontSize: 36, color: C.ink }}>Sales rep</div>
            <div style={{ fontFamily: FONT, fontWeight: 800, fontSize: 110, color: C.red, transform: `translateY(${Math.sin(frame / 3) * 8}px)` }}>???</div>
          </div>
        ) : null}
      </div>
    </Stage>
  );
};

/** "Language barrier" wall that breaks on "khatam", then the CRM steps in. */
const Barrier: React.FC = () => {
  const { frame, sp } = useA();
  const hit = at(19);
  const br = interpolate(frame - hit, [0, 14], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
  const shake = frame < hit && frame > at(17) ? Math.sin(frame * 2.1) * 4 : 0;
  const brick = { background: "#8A3B2B", backgroundImage: "linear-gradient(#5A2219 4px, transparent 4px), linear-gradient(90deg, #5A2219 4px, transparent 4px)", backgroundSize: "120px 60px, 120px 120px", boxShadow: "inset 0 0 40px rgba(0,0,0,.4)" } as const;
  const logo = sp(hit + 4, { damping: 11, stiffness: 180, mass: 0.7 });
  return (
    <Stage li={1} name="barrier">
      <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0 }}>
        {[0, 1].map((k) => (
          <div key={k} style={{ ...brick, position: "absolute", top: 80, left: k === 0 ? 60 : 440, width: 380, height: 380, borderRadius: k === 0 ? "18px 0 0 18px" : "0 18px 18px 0", display: "grid", placeItems: "center", opacity: 1 - br, transform: `translateX(${(k === 0 ? -1 : 1) * br * 25 + shake}px) rotate(${(k === 0 ? -1 : 1) * br * 6}deg)` }}>
            <div style={{ fontFamily: FONT, fontWeight: 800, fontSize: 58, color: "#fff", letterSpacing: 2, textShadow: "0 4px 0 rgba(0,0,0,.4)" }}>{k === 0 ? "LANGUAGE" : "BARRIER"}</div>
          </div>
        ))}
        {frame >= hit + 4 ? (
          <div style={{ position: "absolute", left: 0, right: 0, top: 150, display: "flex", justifyContent: "center", transform: `scale(${Math.min(1, 0.4 + 0.6 * logo)})` }}>
            <div style={{ display: "flex", alignItems: "center", gap: 20, background: "#fff", borderRadius: 28, padding: "20px 34px", boxShadow: `0 0 90px ${C.green}66` }}>
              <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 110, height: 110 }} />
              <div style={{ fontFamily: FONT, color: "#111" }}>
                <div style={{ fontSize: 52, fontWeight: 800, letterSpacing: -1 }}>Har bhasha</div>
                <div style={{ fontSize: 28, fontWeight: 700, color: C.green2 }}>client ↔ sales team</div>
              </div>
            </div>
          </div>
        ) : null}
        {frame >= hit + 10 ? (
          <div style={{ position: "absolute", left: 0, right: 0, top: 420, display: "flex", justifyContent: "center", gap: 14, flexWrap: "wrap" }}>
            {["தமிழ்", "മലയാളം", "ગુજરાતી", "বাংলা", "తెలుగు", "हिन्दी", "English"].map((w, k) => (
              <span key={w} style={{ background: C.card, border: `2px solid ${C.green}66`, color: C.ink, borderRadius: 999, padding: "8px 20px", fontFamily: FONT, fontWeight: 700, fontSize: 32, opacity: interpolate(frame - (hit + 10 + k * 2), [0, 5], [0, 1], CL) }}>{w}</span>
            ))}
          </div>
        ) : null}
      </div>
    </Stage>
  );
};

const LangGrid: React.FC = () => {
  const { frame, sp } = useA();
  const done = at(74);
  return (
    <Stage li={6} name="languages">
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 0, right: 0, top: 0, display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 14 }}>
        {LANG_TILES.map((x) => {
          const s = Math.min(1, sp(x.at, { damping: 10, stiffness: 220, mass: 0.6 }));
          return (
            <div key={x.en} style={{ height: 124, borderRadius: 18, background: C.card, border: `2px solid ${frame >= done ? C.green : C.line}`, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", opacity: s, transform: `scale(${0.5 + 0.5 * s})` }}>
              <div style={{ fontFamily: FONT, fontWeight: 700, fontSize: 42, color: C.ink, lineHeight: 1.2 }}>{x.native}</div>
              <div style={{ fontFamily: MONO, fontSize: 18, color: C.dim, letterSpacing: 1 }}>{x.en.toUpperCase()}</div>
            </div>
          );
        })}
        {frame >= done ? (
          <div style={{ height: 124, borderRadius: 18, background: C.green, display: "flex", alignItems: "center", justifyContent: "center", gap: 10, fontFamily: FONT, fontWeight: 800, color: "#05140A", transform: `scale(${Math.min(1, 0.4 + 0.6 * sp(done))})` }}>
            <span style={{ fontSize: 64 }}>14</span><span style={{ fontSize: 28 }}>bhashayein</span>
          </div>
        ) : null}
      </div>
    </Stage>
  );
};

/** During L4: which language the rep's app is in, switching with the screens. */
const AppLang: React.FC = () => {
  const { frame, sp } = useA();
  if (frame < at(48) - 2 || frame >= L(5)) return null;
  const cur = frame >= at(53) ? 2 : frame >= at(50) ? 1 : 0;
  const since = [at(48), at(50), at(53)][cur];
  return (
    <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 768, width: 182, top: 660, display: "flex", flexDirection: "column", gap: 12, fontFamily: FONT }}>
      {["English", "हिन्दी", "Hinglish"].map((l, k) => (
        <div key={l} style={{ background: k === cur ? C.green : C.card, color: k === cur ? "#05140A" : C.dim, border: `2px solid ${k === cur ? C.green : C.line}`, borderRadius: 14, padding: "8px 6px", fontWeight: 800, fontSize: 26, textAlign: "center", whiteSpace: "nowrap", boxShadow: "0 12px 30px rgba(0,0,0,.5)", transform: `scale(${k === cur ? 0.9 + 0.1 * Math.min(1, sp(since)) : 0.9})` }}>{k === cur ? "✓ " : ""}{l}</div>
      ))}
    </div>
  );
};

/** The real quotation PDF (rendered from the CRM's own PDF) slides in next to the phone. */
const QuotePdf: React.FC = () => {
  const { frame, sp } = useA();
  const a = at(124) + 4;
  if (frame < a || frame >= L(11)) return null;
  const s2 = sp(a, { damping: 14, stiffness: 160, mass: 0.7 });
  const out = interpolate(frame, [L(11) - 6, L(11)], [0, 1], CL);
  return (
    <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 590, top: 760, width: 360, transform: `translateX(${(1 - s2) * 300}px) rotate(${6 - 2 * s2}deg)`, opacity: Math.min(1, s2 * 1.5) * (1 - out), boxShadow: "0 30px 80px rgba(0,0,0,.6)", borderRadius: 10, overflow: "hidden", background: "#fff" }}>
      <Img src={shot("25_quote_pdf")} style={{ width: 360, display: "block" }} />
      <div style={{ position: "absolute", right: 10, top: 10, background: C.red, color: "#fff", fontFamily: FONT, fontWeight: 800, fontSize: 22, borderRadius: 6, padding: "2px 10px" }}>PDF</div>
    </div>
  );
};

const DataSafe: React.FC = () => {
  const { frame, sp, fade } = useA();
  if (frame < L(13) - 6 || frame >= L(14)) return null;
  const mini = (img: string, label: string, sub: string, a: number, x: number) => (
    <div style={{ position: "absolute", left: x, top: 0, width: 300, opacity: fade(a, 6), transform: `translateY(${(1 - Math.min(1, sp(a))) * 60}px)` }}>
      <div style={{ borderRadius: 34, background: "#050505", border: "3px solid #2B332D", padding: 8 }}>
        <div style={{ borderRadius: 26, overflow: "hidden", height: 560 }}>
          <Img src={shot(img)} style={{ width: "100%", display: "block" }} />
        </div>
      </div>
      <div style={{ marginTop: 14, textAlign: "center", fontFamily: FONT, fontWeight: 800, fontSize: 32, color: C.ink }}>{label}</div>
      <div style={{ textAlign: "center", fontFamily: MONO, fontSize: 20, color: C.green }}>{sub}</div>
    </div>
  );
  return (
    <div data-scene="data-safe" data-el="card" data-kind="content" style={{ position: "absolute", left: 90, right: 150, top: 560, height: 740 }}>
      {mini("17_admin_leads", "Admin", "sab leads · 12", L(13) - 8, 0)}
      {mini("18_rep_leads", "Rep (Amit)", "sirf apni · 3", at(137), 540 - 150 + 20)}
    </div>
  );
};

const CTA: React.FC = () => {
  const { frame, sp, fade } = useA();
  if (frame < L(14) - 2) return null;
  const typedLen = Math.round(interpolate(frame, [at(147) + 6, at(147) + 20], [0, 3], CL));
  return (
    <div data-scene="cta" style={{ position: "absolute", inset: 0, opacity: fade(L(14) - 2, 6) }}>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 90, right: 150, top: 580, background: "#fff", borderRadius: 32, padding: 34, fontFamily: FONT, color: "#111", textAlign: "center", boxShadow: `0 0 100px ${C.green}44` }}>
        <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 120, height: 120 }} />
        <div style={{ fontSize: 44, fontWeight: 800, marginTop: 6 }}>Free demo chahiye?</div>
        <div style={{ display: "inline-block", marginTop: 16, background: C.green, color: "#05140A", fontSize: 96, fontWeight: 800, letterSpacing: 6, padding: "4px 36px", borderRadius: 18, transform: `scale(${Math.min(1.05, 0.5 + 0.5 * sp(at(147), { damping: 8, stiffness: 200, mass: 0.6 }))})` }}>CRM</div>
        <div style={{ marginTop: 20, display: "flex", alignItems: "center", gap: 12, background: "#F2F4F2", borderRadius: 999, padding: "12px 20px", fontSize: 30, fontWeight: 800, textAlign: "left" }}>
          <span style={{ flex: 1 }}>{frame >= at(147) + 26 ? <span style={{ color: "#999", fontWeight: 600 }}>Add a comment…</span> : "CRM".slice(0, typedLen)}</span>
          <span style={{ color: "#3797F0" }}>Post</span>
        </div>
        {frame >= at(147) + 26 ? (
          <div style={{ marginTop: 12, textAlign: "left", fontSize: 24, fontWeight: 700, color: C.green2, transform: `scale(${Math.min(1, sp(at(147) + 26))})`, transformOrigin: "left" }}>✓ Demo link DM mein · {myBrand.handle}</div>
        ) : null}
      </div>
    </div>
  );
};

export const Reel: React.FC<ReelProps> = ({ debugSafeZones, probe }) => (
  <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
    <Backdrop />
    <SoundTrack />
    <TopBar />
    <Pill />
    <Headline />
    <Hook />
    <Barrier />
    <LangGrid />
    {PHONE_BEATS.map((b, i) => <Phone key={i} beat={b} />)}
    <AppLang />
    <QuotePdf />
    <DataSafe />
    <CTA />
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);
