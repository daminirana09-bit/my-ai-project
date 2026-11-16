// Reel "solvix-crm" (~60 s): promo for the Pharma Sales CRM by Solvix Web.
// 6 problems of a pharma sales team -> each solved on REAL CRM screens (captured from the CRM running
// locally with its demo data; the WhatsApp away reply was produced by the CRM's own Inbound service).
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
const LINES = [0, 14, 24, 37, 43, 54, 61, 74, 83, 98, 103, 114, 124, 132, 140, words.length];
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
    // L2: every lead comes in by itself, tagged with its platform
    from: L(2), to: L(3),
    states: [
      { from: L(2), img: "02_integrations", zoom: 1.35, cx: 215, cy: 260, ring: { x: 18, y: 160, w: 394, h: 250, at: L(2) + 10 } },
      { from: at(28), img: "03b_leads_source_selected", zoom: 1.2, cx: 215, cy: 560, tap: { x: 215, y: 571, at: at(28) + 4 } },
      { from: at(31), img: "04_leads_indiamart", zoom: 1.3, cx: 300, cy: 690, tap: { x: 73, y: 691, at: at(31) - 2 }, ring: { x: 330, y: 650, w: 95, h: 70, at: at(33) } },
    ],
  },
  {
    // L4: today, overdue and next-7-days follow-ups
    from: L(4), to: L(5),
    states: [
      { from: L(4), img: "05_dashboard_followups", zoom: 1.25, cx: 215, cy: 700, ring: { x: 120, y: 600, w: 130, h: 270, at: at(46) } },
      { from: at(50), img: "08_activity_filled", zoom: 1.25, cx: 215, cy: 640, tap: { x: 62, y: 743, at: at(52) } },
      { from: at(53), img: "09b_activity_log", zoom: 1.2, cx: 215, cy: 500 },
    ],
  },
  {
    // L6: lead clash by phone / GSTIN, admin decides
    from: L(6), to: L(7),
    states: [
      { from: L(6), img: "06_lead_clash", zoom: 1.7, cx: 215, cy: 140, ring: { x: 10, y: 118, w: 410, h: 44, at: L(6) + 6 } },
      { from: at(66), img: "26_manager_notifications", zoom: 1.12, cx: 215, cy: 260, ring: { x: 12, y: 228, w: 405, h: 82, at: at(67) } },
      { from: at(70), img: "10_clashes", zoom: 1.2, cx: 215, cy: 330, tap: { x: 180, y: 345, at: at(71) } },
    ],
  },
  {
    // L8: away cover replies in the client's language, urgent goes to the manager
    from: L(8), to: L(9),
    states: [
      { from: L(8), img: "20_away_filled", zoom: 1.2, cx: 215, cy: 760, tap: { x: 104, y: 925, at: L(8) + 10 } },
      { from: L(8) + 18, img: "21_away_set", zoom: 1.2, cx: 215, cy: 380 },
      { from: at(85), img: "23_inbox_chat", zoom: 1.35, cx: 215, cy: 640, ring: { x: 100, y: 580, w: 320, h: 180, at: at(88) } },
      { from: at(94), img: "26_manager_notifications", zoom: 1.12, cx: 215, cy: 200, ring: { x: 12, y: 148, w: 405, h: 70, at: at(95) } },
    ],
  },
  {
    // L10: GST quotation PDF from the product list, one click to WhatsApp
    from: L(10), to: L(11),
    states: [
      { from: L(10), img: "11_quote_empty", zoom: 1.3, cx: 215, cy: 200, tap: { x: 215, y: 267, at: L(10) + 6 } },
      { from: at(104), img: "12_quote_filled", zoom: 1.25, cx: 215, cy: 420, ring: { x: 18, y: 300, w: 400, h: 130, at: at(106) } },
      { from: at(108), img: "13_quote_saved", zoom: 1.2, cx: 215, cy: 360, tap: { x: 200, y: 197, at: at(112) } },
    ],
  },
  {
    // L12: targets, attendance, analytics live
    from: L(12), to: L(13),
    states: [
      { from: L(12), img: "01_dashboard", zoom: 1.2, cx: 215, cy: 560, ring: { x: 10, y: 470, w: 410, h: 170, at: L(12) + 4 } },
      { from: at(125), img: "14_targets", zoom: 1.15, cx: 215, cy: 360 },
      { from: at(126), img: "15_attendance", zoom: 1.1, cx: 215, cy: 400 },
      { from: at(127), img: "16_analytics", zoom: 1.1, cx: 215, cy: 400 },
    ],
  },
];

// ---------- sound ----------
type Cue = { at: number; name: string; vol?: number; len?: number };
const SFX: Cue[] = [
  { at: 2, name: "impact", vol: 0.3 },
  ...[0, 1, 2, 3, 4, 5].map((k) => ({ at: 8 + k * 5, name: "pop", vol: 0.4 })),
  { at: at(10), name: "whoosh", vol: 0.35 },
  { at: at(11), name: "success", vol: 0.45 },
  // problems: buzz at their start
  ...[1, 3, 5, 7, 9, 11].map((i) => ({ at: L(i), name: "buzz", vol: 0.25 })),
  ...[1, 3, 5, 7, 9, 11].map((i) => ({ at: L(i) + 2, name: "whoosh", vol: 0.3 })),
  { at: L(3) + 20, name: "stamp", vol: 0.6 },
  { at: L(5) + 18, name: "stamp", vol: 0.55 },
  { at: L(7) + 4, name: "ring", vol: 0.3, len: 45 },
  { at: L(9) + 6, name: "typing", vol: 0.25, len: 40 },
  // solutions: whoosh in, then a click/pop on every state, taps and rings
  ...PHONE_BEATS.map((b) => ({ at: b.from - 3, name: "whoosh", vol: 0.35 })),
  ...PHONE_BEATS.flatMap((b) => b.states.slice(1).map((s) => ({ at: s.from, name: "ui", vol: 0.45 }))),
  ...PHONE_BEATS.flatMap((b) => b.states.filter((s) => s.tap).map((s) => ({ at: s.tap!.at, name: "click", vol: 0.5 }))),
  ...PHONE_BEATS.flatMap((b) => b.states.filter((s) => s.ring).map((s) => ({ at: s.ring!.at, name: "tick", vol: 0.28 }))),
  { at: at(108) + 6, name: "pop", vol: 0.5 },
  { at: at(112) + 4, name: "message", vol: 0.55 },
  { at: at(94) + 2, name: "message", vol: 0.45 },
  { at: L(13), name: "whoosh", vol: 0.35 },
  { at: at(136), name: "success", vol: 0.4 },
  { at: L(14), name: "whoosh", vol: 0.35 },
  { at: at(144), name: "impact", vol: 0.4 },
  { at: at(144) + 6, name: "typing", vol: 0.3, len: 20 },
  { at: at(144) + 26, name: "message", vol: 0.5 },
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

const PROBLEM_LINES = [1, 3, 5, 7, 9, 11];
const SOLUTION_LINES = [2, 4, 6, 8, 10, 12];
const Pill: React.FC = () => {
  const { frame, sp } = useA();
  let li = 0;
  for (let i = 0; i < LINES.length - 1; i++) if (frame >= L(i)) li = i;
  const pi = PROBLEM_LINES.indexOf(li);
  const si = SOLUTION_LINES.indexOf(li);
  const text = pi >= 0 ? `PROBLEM ${pi + 1}/6` : si >= 0 ? `SOLVIX CRM · FIX ${si + 1}/6` : li === 13 ? "DATA SAFE" : li === 14 ? "FREE DEMO" : "6 PROBLEMS · 1 CRM";
  const col = pi >= 0 ? C.red : C.green;
  const s = sp(L(li), { damping: 12, stiffness: 220, mass: 0.6 });
  return (
    <div style={{ position: "absolute", left: 0, right: 0, top: 232, display: "flex", justifyContent: "center" }}>
      <div style={{ transform: `scale(${0.75 + 0.25 * s})`, background: col, color: pi >= 0 ? "#fff" : C.bg, fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, padding: "6px 18px", borderRadius: 8 }}>{text}</div>
    </div>
  );
};

const BOX = new Set([8, 11, 24, 25, 33, 34, 51, 62, 65, 83, 84, 88, 106, 108, 112, 124, 125, 127, 136, 144]);
const RED = new Set([15, 16, 17, 22, 39, 42, 60, 76, 81, 102, 119, 123]);
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
    <div data-scene="crm" data-el="card" data-kind="content" style={{ position: "absolute", left: 218, top: 520, width: SCREEN_W + 24, height: SCREEN_H + 24, borderRadius: 54, background: "#050505", border: "3px solid #2B332D", padding: 12, boxShadow: `0 40px 100px rgba(0,0,0,.7), 0 0 80px ${C.green}22`, transform: `translateY(${(1 - s) * 260 - out * 40}px) scale(${0.94 + 0.06 * s})`, opacity: Math.min(1, s * 1.6) * (1 - out) }}>
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

// ---------- problem graphics ----------
const ProblemStage: React.FC<{ li: number; children: React.ReactNode }> = ({ li, children }) => {
  const { frame, fade } = useA();
  if (frame < L(li) - 2 || frame >= L(li + 1)) return null;
  return (
    <div data-scene={`problem-${li}`} style={{ position: "absolute", left: 70, right: 130, top: 560, height: 820, opacity: fade(L(li) - 2, 6) * (1 - interpolate(frame, [L(li + 1) - 5, L(li + 1)], [0, 1], CL)) }}>
      {children}
    </div>
  );
};
const Chip: React.FC<{ text: string; x: number; y: number; at: number; color?: string; rot?: number }> = ({ text, x, y, at: a, color = C.ink, rot = 0 }) => {
  const { frame, sp } = useA();
  const s = sp(a, { damping: 10, stiffness: 200, mass: 0.6 });
  const jit = Math.sin(frame / 5 + x) * 4;
  return (
    <div style={{ position: "absolute", left: x, top: y + jit, transform: `rotate(${rot}deg) scale(${Math.min(1, s)})`, background: C.card, border: `2px solid ${C.line}`, color, borderRadius: 16, padding: "14px 20px", fontFamily: FONT, fontWeight: 800, fontSize: 32, whiteSpace: "nowrap", boxShadow: "0 16px 40px rgba(0,0,0,.5)" }}>{text}</div>
  );
};
const Stamp: React.FC<{ text: string; at: number; x: number; y: number }> = ({ text, at: a, x, y }) => {
  const { frame, sp } = useA();
  if (frame < a) return null;
  return (
    <div data-el="sticker" data-kind="content" style={{ position: "absolute", left: x, top: y, transform: `rotate(-8deg) scale(${1.7 - 0.7 * Math.min(1, sp(a, { damping: 9, stiffness: 260, mass: 0.6 }))})`, border: `7px solid ${C.red}`, color: C.red, borderRadius: 14, padding: "4px 22px", fontFamily: FONT, fontWeight: 800, fontSize: 72, letterSpacing: 3, background: "rgba(7,10,8,.8)", whiteSpace: "nowrap" }}>{text}</div>
  );
};

const Problems: React.FC = () => {
  const { frame } = useA();
  return (
    <>
      <ProblemStage li={1}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0 }}>
          <Chip text="IndiaMART" x={20} y={40} at={L(1) + 2} rot={-6} />
          <Chip text="JustDial" x={420} y={120} at={at(16)} rot={5} />
          <Chip text="Facebook" x={60} y={300} at={at(17)} rot={4} />
          <Chip text="Email" x={460} y={380} at={at(18)} rot={-5} />
          <Chip text="WhatsApp" x={130} y={540} at={at(19)} rot={-3} />
          <Chip text="Excel" x={480} y={620} at={at(20)} rot={7} />
          <div style={{ position: "absolute", left: 330, top: 230, fontFamily: FONT, fontWeight: 800, fontSize: 200, color: C.red, opacity: interpolate(frame - at(22), [0, 6], [0, 1], CL) }}>?</div>
        </div>
      </ProblemStage>
      <ProblemStage li={3}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", left: 140, top: 60, width: 520, padding: 34, background: "#FFE97A", color: "#2A2A12", fontFamily: FONT, fontWeight: 800, fontSize: 40, transform: `rotate(${-4 + interpolate(frame - L(3), [0, 30], [0, -10], CL)}deg) translateY(${interpolate(frame - L(3), [12, 30], [0, 140], CL)}px)`, boxShadow: "0 20px 50px rgba(0,0,0,.5)" }}>
          Kal call karna:<br />Shree Pharma — order
          <div style={{ fontSize: 26, fontWeight: 600, marginTop: 12 }}>(3 din pehle)</div>
        </div>
        <Stamp text="CLIENT GAYA" at={L(3) + 20} x={150} y={460} />
      </ProblemStage>
      <ProblemStage li={5}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0 }}>
          <Chip text="Amit" x={0} y={120} at={L(5) + 2} />
          <Chip text="Sunita" x={560} y={120} at={L(5) + 6} />
          <div style={{ position: "absolute", left: 250, top: 330, background: "#fff", color: "#111", borderRadius: 20, padding: "24px 30px", fontFamily: FONT, fontWeight: 800, fontSize: 38, transform: `translateX(${Math.sin(frame / 2.2) * 10}px)` }}>Shree Pharma</div>
          <svg width="840" height="420" style={{ position: "absolute", left: 0, top: 150 }}>
            <path d="M120 60 C 200 140, 250 170, 300 190" stroke={C.red} strokeWidth="6" fill="none" strokeDasharray="14 10" strokeDashoffset={-frame * 2} />
            <path d="M650 60 C 580 140, 540 170, 520 190" stroke={C.red} strokeWidth="6" fill="none" strokeDasharray="14 10" strokeDashoffset={-frame * 2} />
          </svg>
          <Stamp text="JHAGDA" at={L(5) + 18} x={210} y={520} />
        </div>
      </ProblemStage>
      <ProblemStage li={7}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0 }}>
          <svg width="160" height="160" viewBox="0 0 24 24" style={{ position: "absolute", left: 560, top: 0 }}><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z" fill="#F2E3A0" /></svg>
          <div style={{ position: "absolute", left: 60, top: 170, width: 640, background: "#fff", color: "#111", borderRadius: 26, padding: "22px 26px", fontFamily: FONT, transform: `translateX(${frame < L(7) + 30 ? Math.sin(frame * 1.4) * 6 : 0}px)`, boxShadow: "0 20px 60px rgba(0,0,0,.5)" }}>
            <div style={{ fontSize: 24, fontWeight: 700, color: "#25A244" }}>WhatsApp · 10:53 PM</div>
            <div style={{ fontSize: 34, fontWeight: 800, marginTop: 6 }}>Ramesh Medicos</div>
            <div style={{ fontSize: 30, marginTop: 4 }}>PCM 650 ka rate bhejo, urgent order…</div>
          </div>
          <Chip text="Amit: chhutti par" x={150} y={470} at={at(76)} color={C.red} />
        </div>
      </ProblemStage>
      <ProblemStage li={9}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", left: 60, top: 40, width: 640, background: "#fff", borderRadius: 16, overflow: "hidden", fontFamily: MONO, fontSize: 22, color: "#333" }}>
          {Array.from({ length: 7 }, (_, r) => (
            <div key={r} style={{ display: "flex", borderBottom: "1px solid #ddd" }}>
              {Array.from({ length: 5 }, (_, c2) => <div key={c2} style={{ flex: 1, height: 52, borderRight: "1px solid #ddd", background: r === 0 ? "#E8F5E9" : "#fff", display: "grid", placeItems: "center" }}>{r === 0 ? ["Item", "Qty", "Rate", "GST", "Amt"][c2] : random(`x${r}${c2}`) > 0.5 && frame > L(9) + r * 4 ? "…" : ""}</div>)}
            </div>
          ))}
        </div>
        <div data-el="sticker" data-kind="content" style={{ position: "absolute", left: 260, top: 470, display: "flex", alignItems: "center", gap: 18, fontFamily: FONT, fontWeight: 800, fontSize: 64, color: C.red }}>
          <svg width="90" height="90" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke={C.red} strokeWidth="2" /><path d={`M12 12 L ${12 + 7 * Math.sin(frame / 3)} ${12 - 7 * Math.cos(frame / 3)}`} stroke={C.red} strokeWidth="2" strokeLinecap="round" /></svg>
          1 ghanta
        </div>
      </ProblemStage>
      <ProblemStage li={11}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", left: 60, top: 80, width: 640, background: C.card, border: `2px solid ${C.line}`, borderRadius: 24, padding: 30, fontFamily: FONT, color: C.ink }}>
          <div style={{ fontSize: 30, fontWeight: 800 }}>Target · November</div>
          <div style={{ marginTop: 16, height: 26, borderRadius: 13, background: "#1E2A22" }}>
            <div style={{ width: `${30 + 20 * Math.sin(frame / 6)}%`, height: "100%", borderRadius: 13, background: C.red, opacity: 0.6 }} />
          </div>
          <div style={{ marginTop: 18, fontSize: 64, fontWeight: 800, color: C.red }}>?? %</div>
          <div style={{ marginTop: 10, fontSize: 30, color: C.dim }}>Kaun late aaya? Kaun field mein hai?</div>
        </div>
      </ProblemStage>
    </>
  );
};

// ---------- hook, data-safe split, CTA ----------
const Hook: React.FC = () => {
  const { frame, sp } = useA();
  if (frame >= L(1)) return null;
  const items = ["Bikhri leads", "Bhoole follow-up", "Lead clash", "Rep chhutti par", "Slow quotation", "Target ka pata nahi"];
  const crm = sp(at(10), { damping: 11, stiffness: 180, mass: 0.7 });
  return (
    <div data-scene="hook" data-el="card" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 560 }}>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, opacity: 1 - 0.75 * Math.min(1, crm) }}>
        {items.map((s, k) => (
          <div key={s} style={{ background: C.card, border: `2px solid ${C.red}66`, borderRadius: 18, padding: "18px 20px", fontFamily: FONT, fontWeight: 800, fontSize: 30, color: C.ink, transform: `scale(${Math.min(1, sp(8 + k * 5, { damping: 10, stiffness: 220, mass: 0.6 }))})` }}>
            <span style={{ color: C.red }}>✕ </span>{s}
          </div>
        ))}
      </div>
      {frame >= at(10) ? (
        <div style={{ position: "absolute", left: 0, right: 0, top: 120, display: "flex", justifyContent: "center", transform: `scale(${Math.min(1, 0.4 + 0.6 * crm)})` }}>
          <div style={{ display: "flex", alignItems: "center", gap: 20, background: "#fff", borderRadius: 28, padding: "20px 34px", boxShadow: `0 0 90px ${C.green}66` }}>
            <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 110, height: 110 }} />
            <div style={{ fontFamily: FONT, color: "#111" }}>
              <div style={{ fontSize: 56, fontWeight: 800, letterSpacing: -1 }}>1 CRM</div>
              <div style={{ fontSize: 26, fontWeight: 700, color: C.green2 }}>sab solve</div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
};

/** The real quotation PDF (rendered from the CRM's own PDF) slides in next to the phone. */
const QuotePdf: React.FC = () => {
  const { frame, sp } = useA();
  const a = at(108) + 4;
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
  if (frame < L(13) - 2 || frame >= L(14)) return null;
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
      {mini("17_admin_leads", "Admin", "sab leads · 12", L(13), 0)}
      {mini("18_rep_leads", "Rep (Amit)", "sirf apni · 3", at(135), 540 - 150 + 20)}
    </div>
  );
};

const CTA: React.FC = () => {
  const { frame, sp, fade } = useA();
  if (frame < L(14) - 2) return null;
  const typedLen = Math.round(interpolate(frame, [at(144) + 6, at(144) + 20], [0, 3], CL));
  return (
    <div data-scene="cta" style={{ position: "absolute", inset: 0, opacity: fade(L(14) - 2, 6) }}>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 90, right: 150, top: 580, background: "#fff", borderRadius: 32, padding: 34, fontFamily: FONT, color: "#111", textAlign: "center", boxShadow: `0 0 100px ${C.green}44` }}>
        <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 120, height: 120 }} />
        <div style={{ fontSize: 44, fontWeight: 800, marginTop: 6 }}>Free demo chahiye?</div>
        <div style={{ display: "inline-block", marginTop: 16, background: C.green, color: "#05140A", fontSize: 96, fontWeight: 800, letterSpacing: 6, padding: "4px 36px", borderRadius: 18, transform: `scale(${Math.min(1.05, 0.5 + 0.5 * sp(at(144), { damping: 8, stiffness: 200, mass: 0.6 }))})` }}>CRM</div>
        <div style={{ marginTop: 20, display: "flex", alignItems: "center", gap: 12, background: "#F2F4F2", borderRadius: 999, padding: "12px 20px", fontSize: 30, fontWeight: 800, textAlign: "left" }}>
          <span style={{ flex: 1 }}>{frame >= at(144) + 26 ? <span style={{ color: "#999", fontWeight: 600 }}>Add a comment…</span> : "CRM".slice(0, typedLen)}</span>
          <span style={{ color: "#3797F0" }}>Post</span>
        </div>
        {frame >= at(144) + 26 ? (
          <div style={{ marginTop: 12, textAlign: "left", fontSize: 24, fontWeight: 700, color: C.green2, transform: `scale(${Math.min(1, sp(at(144) + 26))})`, transformOrigin: "left" }}>✓ Demo link DM mein · {myBrand.handle}</div>
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
    <Problems />
    {PHONE_BEATS.map((b, i) => <Phone key={i} beat={b} />)}
    <QuotePdf />
    <DataSafe />
    <CTA />
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);
