// Reel "solvix-meta-update" (~49 s): Meta's new brand ad formats, explained by Riya (Solvix presenter, illustrated)
// over a laptop page with three phone mock-ups, a pointing hand and stickers. Solvix logo colours (black + neon green).
// Facts (Meta Brand Building Summit, 15-16 Oct 2026; see projects/solvix-meta-update/script.md for sources):
//   First Impression Ads = first ad slot in Feed, one brand a day, early US test.
//   Reels Trending Ads = ad right after the most-watched Reels on Facebook and Instagram.
//   Ads Super Burst = book a 3-24 hour window, optional category exclusivity, phased rollout.
// Voice: Sarah (free premade, female) -> female character. Music + SFX: Mixkit free licences.
import React from "react";
import { Easing, Img, interpolate, staticFile, useVideoConfig } from "remotion";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { CL, CommentCTA, CornerBrand, Captions, FONT, MONO, SolvixBackdrop, SoundTrack, SX, useA, type Cue } from "../../components/solvix";
import { Toon, type PoseCue } from "../../components/toon";
import { timing } from "../../timing";
import mouth from "../../../public/reels/solvix-meta-update/mouth.json";
import words from "../../../public/reels/solvix-meta-update/captions.words.json";

export const t = timing(words);
const at = (i: number) => t.at(i, 0);
export const durationInFrames = t.duration(0.9);
const LINES = [0, 12, 35, 52, 68, 83, 94, 111];
const L = (i: number) => (i < LINES.length ? at(LINES[i]) : durationInFrames);
const HI = new Set([7, 8, 13, 14, 15, 23, 24, 25, 29, 36, 37, 38, 42, 45, 53, 54, 55, 56, 60, 62, 63, 74, 80, 83, 85, 88, 92, 99, 100, 101, 105, 115, 116, 121]);
const CAP_TOP = 1318;

const W = words as { word: string; start: number; end: number }[];
const useSpeaking = () => {
  const { frame } = useA();
  const { fps } = useVideoConfig();
  return W.some((w) => frame / fps >= w.start - 0.05 && frame / fps <= w.end + 0.1);
};
const CUES: PoseCue[] = [
  { at: 0, pose: "explain", mood: "shock" },
  { at: at(6), pose: "explain", mood: "happy" },
  { at: L(1), pose: "point", mood: "happy" },
  { at: L(2), pose: "explain", mood: "calm" },
  { at: L(3), pose: "point", mood: "happy" },
  { at: L(5), pose: "explain", mood: "happy" },
  { at: L(7), pose: "thumbs", mood: "happy" },
];

// ---------- presenter ----------
const HookPresenter: React.FC = () => {
  const { frame, sp } = useA();
  const speaking = useSpeaking();
  if (frame >= L(1)) return null;
  const out = interpolate(frame, [L(1) - 8, L(1)], [0, 1], CL);
  const pill = sp(4, { damping: 10, stiffness: 200, mass: 0.6 });
  return (
    <div data-scene="hook" style={{ position: "absolute", inset: 0, opacity: 1 - out }}>
      <div data-el="title" data-kind="content" style={{ position: "absolute", left: 120, right: 180, top: 330, display: "flex", justifyContent: "center", transform: `scale(${Math.min(1, pill)}) rotate(-2deg)` }}>
        <div style={{ background: "#fff", color: "#111", borderRadius: 18, padding: "14px 26px", fontFamily: FONT, fontWeight: 800, fontSize: 38, whiteSpace: "nowrap", boxShadow: `0 0 0 6px ${SX.green}, 0 20px 50px rgba(0,0,0,.5)` }}>Meta Ads ka sabse bada update 😱</div>
      </div>
      <div style={{ position: "absolute", left: 505 - 241, top: 560, transform: `translateY(${(1 - Math.min(1, sp(0))) * 200}px)` }}>
        <Toon who="riya" mouth={mouth} cues={CUES} speaking={speaking} scale={1.15} lookRight={false} />
      </div>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 264, top: 600, width: 483, height: 700 }} />
    </div>
  );
};

/** Riya in a rounded bubble, top right, while she explains the screen. */
const Bubble: React.FC = () => {
  const { frame, sp } = useA();
  const speaking = useSpeaking();
  const from = L(1) - 6;
  if (frame < from) return null;
  const s = Math.min(1, sp(from, { damping: 12, stiffness: 170, mass: 0.7 }));
  const bw = 250, bh = 290;
  return (
    <div style={{ position: "absolute", left: 950 - bw, top: 300, width: bw, height: bh, transform: `scale(${0.5 + 0.5 * s})`, opacity: s, transformOrigin: "right top" }}>
      <div style={{ position: "absolute", inset: 0, borderRadius: 34, background: "linear-gradient(180deg, #1B2A1E, #0D140F)", border: `4px solid ${SX.green}`, boxShadow: `0 0 40px ${SX.green}55, 0 20px 50px rgba(0,0,0,.6)`, clipPath: "inset(0 round 34px)" }}>
        <div style={{ position: "absolute", left: (bw - 336) / 2, top: -18 }}>
          <Toon who="riya" mouth={mouth} cues={CUES} speaking={speaking} scale={0.8} lookRight={false} />
        </div>
      </div>
      <div style={{ position: "absolute", left: 14, bottom: 12, background: SX.green, color: "#031003", fontFamily: MONO, fontWeight: 700, fontSize: 16, borderRadius: 8, padding: "2px 8px" }}>RIYA · SOLVIX</div>
      <div data-el="sticker" data-kind="content" style={{ position: "absolute", inset: 0 }} />
    </div>
  );
};

// ---------- the laptop page (840 x 525 page px) ----------
const PG = { w: 840, h: 525 };
const PHONES = [150, 420, 690];
const MiniPhone: React.FC<{ cx: number; dark?: boolean; children: React.ReactNode }> = ({ cx, dark, children }) => (
  <div style={{ position: "absolute", left: cx - 88, top: 58, width: 176, height: 350, borderRadius: 26, background: "#0B0B0B", padding: 6, boxSizing: "border-box", boxShadow: "0 14px 30px rgba(0,0,0,.25)" }}>
    <div style={{ position: "relative", width: "100%", height: "100%", borderRadius: 21, background: dark ? "linear-gradient(170deg, #0E3B1A, #051208 70%)" : "#FFFFFF", clipPath: "inset(0 round 21px)", fontFamily: FONT }}>{children}</div>
    <div style={{ position: "absolute", top: 11, left: "50%", width: 44, height: 11, marginLeft: -22, borderRadius: 6, background: "#000" }} />
  </div>
);
const chip = (bg: string, fg: string): React.CSSProperties => ({ position: "absolute", background: bg, color: fg, fontSize: 10.5, fontWeight: 800, borderRadius: 999, padding: "3px 8px", whiteSpace: "nowrap", display: "flex", alignItems: "center", gap: 4 });
const AdCard: React.FC<{ top: number; label: string }> = ({ top, label }) => (
  <div style={{ position: "absolute", left: 8, right: 8, top, borderRadius: 10, background: "#fff", border: "1px solid #E6E8E4", overflow: "hidden" }}>
    <div style={{ display: "flex", alignItems: "center", gap: 5, padding: "6px 7px" }}>
      <div style={{ width: 16, height: 16, borderRadius: "50%", background: SX.green2 }} />
      <div style={{ fontSize: 9.5, fontWeight: 800, color: "#111" }}>Your Brand<div style={{ fontSize: 8, fontWeight: 500, color: "#888" }}>{label}</div></div>
    </div>
    <div style={{ height: 82, background: "linear-gradient(135deg, #22E01C, #0B6B12 60%, #052A09)" }} />
    <div style={{ margin: 6, borderRadius: 6, background: "#1877F2", color: "#fff", fontSize: 9.5, fontWeight: 800, textAlign: "center", padding: "4px 0" }}>Learn more</div>
  </div>
);
const Page: React.FC = () => (
  <div style={{ position: "absolute", left: 0, top: 0, width: PG.w, height: PG.h, background: "#F2F4F1", fontFamily: FONT }}>
    <div style={{ height: 34, background: "#fff", borderBottom: "1px solid #E3E6E1", display: "flex", alignItems: "center", padding: "0 14px", gap: 8 }}>
      <Img src={staticFile("reels/solvix-crm/solvix-logo.png")} style={{ width: 22, height: 22 }} />
      <div style={{ fontSize: 12, fontWeight: 800, color: "#111" }}>Solvix explains · Meta ke 3 naye brand ad formats</div>
      <div style={{ marginLeft: "auto", fontSize: 10, color: "#888", fontFamily: MONO }}>Oct 2026</div>
    </div>
    {/* 1. First Impression Ads */}
    <MiniPhone cx={PHONES[0]}>
      <div style={{ position: "absolute", left: 10, top: 24, fontSize: 13, fontWeight: 800, color: "#111" }}>Feed</div>
      <AdCard top={46} label="Sponsored" />
      <div style={{ ...chip(SX.green2, "#fff"), right: 8, top: 22 }}>1st ad in Feed</div>
      <div style={{ position: "absolute", left: 8, right: 8, top: 214, height: 90, borderRadius: 10, background: "#ECEEEA" }} />
    </MiniPhone>
    {/* 2. Reels Trending Ads */}
    <MiniPhone cx={PHONES[1]} dark>
      <div style={{ ...chip("rgba(255,255,255,.18)", "#fff"), left: 8, top: 24 }}>🔥 Trending</div>
      <div style={{ position: "absolute", left: 10, right: 10, top: 90, color: "#fff", fontSize: 22, fontWeight: 800, lineHeight: 1.05 }}>Match ka<br />last over</div>
      <div style={{ position: "absolute", left: 10, top: 150, color: "#cfe", fontSize: 10 }}>2.1M views</div>
      <div style={{ position: "absolute", left: 8, right: 8, top: 214, borderRadius: 10, background: "#fff", padding: 7 }}>
        <div style={{ fontSize: 8.5, fontFamily: MONO, color: "#888" }}>UP NEXT</div>
        <div style={{ display: "flex", alignItems: "center", gap: 5, marginTop: 3 }}><div style={{ width: 16, height: 16, borderRadius: "50%", background: SX.green2 }} /><div style={{ fontSize: 10, fontWeight: 800 }}>Your Brand ad</div></div>
        <div style={{ height: 40, borderRadius: 6, marginTop: 5, background: "linear-gradient(135deg, #22E01C, #0B6B12)" }} />
      </div>
    </MiniPhone>
    {/* 3. Ads Super Burst */}
    <MiniPhone cx={PHONES[2]} dark>
      <div style={{ ...chip("#fff", "#111"), left: 8, top: 26 }}>⏱ 3–24 hr burst</div>
      <div style={{ ...chip(SX.green, "#031003"), right: 6, top: 62 }}>🔒 Category exclusive</div>
      <div style={{ position: "absolute", left: 10, right: 10, top: 110, color: "#fff", fontSize: 11, fontFamily: MONO, letterSpacing: 1, opacity: 0.8 }}>BIG SALE · TONIGHT</div>
      <div style={{ position: "absolute", left: 10, right: 10, top: 126, color: "#fff", fontSize: 24, fontWeight: 800 }}>Starts now.</div>
      <div style={{ ...chip("#E9F9E6", SX.green2), left: 8, top: 172 }}>✓ Reserved</div>
      <AdCard top={204} label="Super Burst" />
      <div style={{ ...chip("#fff", "#111"), right: 6, top: 312 }}>📺 TV-like scale</div>
    </MiniPhone>
    {[["First Impression Ads", "Feed ka pehla ad"], ["Reels Trending Ads", "Trending Reels ke baad"], ["Ads Super Burst", "3–24 ghante ka slot"]].map(([a, b], k) => (
      <div key={a} style={{ position: "absolute", left: PHONES[k] - 130, width: 260, top: 420, textAlign: "center" }}>
        <div style={{ fontSize: 17, fontWeight: 800, color: "#111" }}>{a}</div>
        <div style={{ fontSize: 12, color: "#777", marginTop: 2 }}>{b}</div>
      </div>
    ))}
  </div>
);
const SummitPage: React.FC = () => (
  <div style={{ position: "absolute", left: 0, top: 0, width: PG.w, height: PG.h, background: "#F2F4F1", fontFamily: FONT, padding: "60px 70px", boxSizing: "border-box" }}>
    <div style={{ fontFamily: MONO, fontSize: 14, color: SX.green2, letterSpacing: 2 }}>15 OCT 2026 · ANNOUNCEMENT</div>
    <div style={{ fontSize: 40, fontWeight: 800, color: "#111", marginTop: 10, lineHeight: 1.1 }}>Meta Brand Building Summit</div>
    <div style={{ fontSize: 18, color: "#555", marginTop: 12 }}>Naye brand ad formats for Facebook &amp; Instagram</div>
    {[["First Impression Ads", "Early test · US · 1 brand / day"], ["Reels Trending Ads", "Sab advertisers ke liye · 15 topics"], ["Ads Super Burst", "Rollout phases mein"]].map(([a, b], k) => (
      <div key={a} style={{ marginTop: k ? 10 : 30, display: "flex", justifyContent: "space-between", background: "#fff", borderRadius: 12, padding: "14px 18px", border: "1px solid #E3E6E1" }}>
        <span style={{ fontSize: 18, fontWeight: 800, color: "#111" }}>{a}</span><span style={{ fontSize: 15, color: "#666" }}>{b}</span>
      </div>
    ))}
  </div>
);

// ---------- camera over the page ----------
type Cam = { at: number; z: number; cx: number; cy: number };
const CAMS: Cam[] = [
  { at: 0, z: 1, cx: 420, cy: 262 },
  { at: at(13), z: 2.3, cx: PHONES[0], cy: 150 },
  { at: at(26), z: 2.3, cx: PHONES[0], cy: 290 },
  { at: L(2), z: 2.3, cx: PHONES[1], cy: 160 },
  { at: at(42), z: 2.3, cx: PHONES[1], cy: 290 },
  { at: L(3), z: 2.3, cx: PHONES[2], cy: 140 },
  { at: at(80), z: 2.3, cx: PHONES[2], cy: 280 },
  { at: L(5), z: 1, cx: 420, cy: 262 },
];
const camAt = (f: number) => {
  let i = 0;
  CAMS.forEach((c, k) => { if (f >= c.at) i = k; });
  const a = CAMS[Math.max(0, i - 1)], b = CAMS[i];
  const p = i === 0 ? 1 : interpolate(f - b.at, [0, 16], [0, 1], { ...CL, easing: Easing.inOut(Easing.cubic) });
  return { z: a.z + (b.z - a.z) * p, cx: a.cx + (b.cx - a.cx) * p, cy: a.cy + (b.cy - a.cy) * p };
};
const LAP = { x: 100, y: 640, sw: 820, sh: 512 }; // screen box on stage
const toStage = (f: number, px: number, py: number) => {
  const c = camAt(f);
  const k = LAP.sw / PG.w;
  const z = c.z;
  const cx = Math.min(PG.w - PG.w / (2 * z), Math.max(PG.w / (2 * z), c.cx));
  const cy = Math.min(PG.h - PG.h / (2 * z), Math.max(PG.h / (2 * z), c.cy));
  return { x: LAP.x + (LAP.sw / 2 + (px - cx) * z * k), y: LAP.y + (LAP.sh / 2 + (py - cy) * z * k), z, cx, cy, k };
};

const Laptop: React.FC = () => {
  const { frame, sp, fade } = useA();
  const from = L(1) - 4, to = L(7);
  if (frame < from || frame >= to) return null;
  const s = Math.min(1, sp(from, { damping: 16, stiffness: 140, mass: 0.8 }));
  const out = interpolate(frame, [to - 8, to], [0, 1], CL);
  const o = toStage(frame, 0, 0);
  const summit = fade(L(6), 8);
  return (
    <div data-scene={frame < L(2) ? "first-impression" : frame < L(3) ? "trending" : frame < L(5) ? "super-burst" : frame < L(6) ? "use-cases" : "summit"} style={{ position: "absolute", inset: 0, opacity: s * (1 - out), transform: `translateY(${(1 - s) * 200}px)` }}>
      <div style={{ position: "absolute", left: LAP.x - 22, top: LAP.y - 22, width: LAP.sw + 44, height: LAP.sh + 44, borderRadius: 26, background: "#0C0D0C", border: "3px solid #2A332C", boxShadow: `0 40px 100px rgba(0,0,0,.75), 0 0 80px ${SX.green}22` }} />
      <div style={{ position: "absolute", left: LAP.x, top: LAP.y, width: LAP.sw, height: LAP.sh, clipPath: "inset(0 round 8px)", background: "#F2F4F1" }}>
        <div style={{ position: "absolute", left: 0, top: 0, transformOrigin: "0 0", transform: `translate(${o.x - LAP.x}px, ${o.y - LAP.y}px) scale(${o.z * o.k})` }}>
          <Page />
        </div>
        <div style={{ position: "absolute", left: 0, top: 0, transformOrigin: "0 0", transform: `scale(${LAP.sw / PG.w})`, opacity: summit }}>
          <SummitPage />
        </div>
      </div>
      <div style={{ position: "absolute", left: LAP.x - 70, top: LAP.y + LAP.sh + 22, width: LAP.sw + 140, height: 30, borderRadius: "0 0 30px 30px", background: "linear-gradient(180deg, #2B2F2C, #151816)", boxShadow: "0 20px 40px rgba(0,0,0,.6)" }} />
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: LAP.x - 22, top: LAP.y - 22, width: LAP.sw + 44, height: LAP.sh + 74 }} />
    </div>
  );
};

/** A pointing finger that comes in from below and taps a spot on the page. */
const TAPS: { at: number; px: number; py: number }[] = [
  { at: at(23), px: PHONES[0] + 50, py: 86 },
  { at: at(45), px: PHONES[1], py: 255 },
  { at: at(62), px: PHONES[2] - 40, py: 92 },
  { at: at(74), px: PHONES[2] + 30, py: 128 },
];
const Hand: React.FC = () => {
  const { frame } = useA();
  const tp = TAPS.find((x) => frame >= x.at - 14 && frame < x.at + 22);
  if (!tp) return null;
  const p = toStage(frame, tp.px, tp.py);
  const inn = interpolate(frame, [tp.at - 14, tp.at], [1, 0], { ...CL, easing: Easing.out(Easing.cubic) });
  const outp = interpolate(frame, [tp.at + 10, tp.at + 22], [0, 1], CL);
  const press = frame >= tp.at && frame < tp.at + 6 ? 0.92 : 1;
  const ripple = frame >= tp.at ? interpolate(frame - tp.at, [0, 16], [0, 1], CL) : 0;
  return (
    <>
      {ripple > 0 ? <div style={{ position: "absolute", left: p.x - 50 * ripple, top: p.y - 50 * ripple, width: 100 * ripple, height: 100 * ripple, borderRadius: "50%", border: `5px solid ${SX.green}`, opacity: 1 - ripple }} /> : null}
      <svg width={170} height={260} viewBox="0 0 170 260" style={{ position: "absolute", left: p.x - 38, top: p.y - 6 + (inn + outp) * 360, transform: `scale(${press}) rotate(-12deg)`, transformOrigin: "38px 6px", filter: "drop-shadow(0 16px 20px rgba(0,0,0,.5))" }}>
        <path d="M28 6 C 40 -2, 52 6, 52 18 L 52 110 L 60 104 C 74 96, 88 104, 86 118 L 96 112 C 110 106, 122 116, 118 130 L 126 126 C 140 122, 150 134, 146 150 L 136 210 C 130 236, 108 252, 82 252 L 66 252 C 40 252, 22 236, 16 212 L 4 160 C 0 144, 14 134, 26 142 L 28 146 Z" fill="#C98E6A" stroke="#3A2416" strokeWidth="4" />
        <path d="M30 14 C 36 10, 44 12, 46 18" stroke="#fff" strokeWidth="5" opacity="0.6" fill="none" strokeLinecap="round" />
      </svg>
    </>
  );
};

// ---------- stickers below the laptop ----------
const STRIP_Y = 1218;
const StripChip: React.FC<{ at: number; x: number; text: string; tone?: "green" | "red" | "amber" | "dark" }> = ({ at: a, x, text, tone = "dark" }) => {
  const { frame, sp } = useA();
  if (frame < a) return null;
  const s = Math.min(1, sp(a, { damping: 10, stiffness: 220, mass: 0.6 }));
  const bg = tone === "green" ? SX.green : tone === "red" ? "#2A1212" : tone === "amber" ? SX.amber : SX.card;
  const fg = tone === "green" || tone === "amber" ? "#031003" : SX.ink;
  const bd = tone === "red" ? SX.red : tone === "dark" ? SX.line : "transparent";
  return <div style={{ position: "absolute", left: x, top: STRIP_Y, transform: `scale(${s})`, background: bg, color: fg, border: `3px solid ${bd}`, borderRadius: 18, padding: "12px 20px", fontFamily: FONT, fontWeight: 800, fontSize: 32, whiteSpace: "nowrap", boxShadow: "0 14px 34px rgba(0,0,0,.5)" }}>{text}</div>;
};
const Strip: React.FC<{ from: number; to: number; name: string; children: React.ReactNode }> = ({ from, to, children }) => {
  const { frame } = useA();
  if (frame < from || frame >= to) return null;
  return (
    <div style={{ position: "absolute", inset: 0, opacity: interpolate(frame, [to - 6, to], [1, 0], CL) }}>
      <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 70, right: 130, top: STRIP_Y, height: 74 }} />
      {children}
    </div>
  );
};
const Stickers: React.FC = () => (
  <>
    <Strip from={at(28)} to={L(2)} name="us">
      <StripChip at={at(28)} x={150} text="🇺🇸 US test" tone="amber" />
      <StripChip at={at(31) + 4} x={420} text="1 brand / din" />
    </Strip>
    <Strip from={at(47)} to={L(3)} name="fbig">
      <StripChip at={at(47)} x={170} text="Facebook ✓" tone="green" />
      <StripChip at={at(49)} x={470} text="Instagram ✓" tone="green" />
    </Strip>
    <Strip from={at(60)} to={L(4)} name="hours">
      <StripChip at={at(60)} x={200} text="⏱ 3 – 24 ghante" tone="green" />
      <StripChip at={at(66)} x={560} text="TV jaisa reach" />
    </Strip>
    <Strip from={at(76)} to={L(5)} name="exclusive">
      <StripChip at={at(76)} x={90} text="Rival ad ✕" tone="red" />
      <StripChip at={at(78)} x={360} text="Rival ad ✕" tone="red" />
      <StripChip at={at(80)} x={630} text="Aap ✓" tone="green" />
    </Strip>
    <Strip from={at(83)} to={L(6)} name="uses">
      <StripChip at={at(83)} x={80} text="🚀 Launch" />
      <StripChip at={at(85)} x={350} text="🏷️ Sale" />
      <StripChip at={at(87)} x={560} text="🎟️ Event" />
    </Strip>
    <Strip from={at(105)} to={L(7)} name="rollout">
      <StripChip at={at(105)} x={170} text="Rollout: dheere dheere" tone="amber" />
    </Strip>
  </>
);

// ---------- sound ----------
const SFX: Cue[] = [
  { at: 2, name: "impact", vol: 0.35 },
  { at: 4, name: "pop", vol: 0.4 },
  { at: at(7), name: "whoosh", vol: 0.3 },
  ...[1, 2, 3, 4, 5, 6, 7].map((i) => ({ at: L(i) - 3, name: "whoosh", vol: 0.3 })),
  ...CAMS.slice(1).map((c) => ({ at: c.at, name: "ui", vol: 0.35 })),
  ...TAPS.map((x) => ({ at: x.at, name: "click", vol: 0.55 })),
  ...[at(28), at(31) + 4, at(47), at(49), at(60), at(66), at(83), at(85), at(87), at(105)].map((a) => ({ at: a, name: "pop", vol: 0.35 })),
  ...[at(76), at(78)].map((a) => ({ at: a, name: "buzz", vol: 0.22 })),
  { at: at(80), name: "success", vol: 0.4 },
  { at: L(6), name: "ui", vol: 0.4 },
  { at: L(7) + 14, name: "typing", vol: 0.3, len: 22 },
  { at: L(7) + 40, name: "message", vol: 0.5 },
];

export const Reel: React.FC<ReelProps> = ({ debugSafeZones, probe }) => (
  <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
    <SolvixBackdrop glowY={50} />
    <SoundTrack voice="reels/solvix-meta-update/vo.wav" music="music/hiphop-02.mp3" words={words} cues={SFX} duration={durationInFrames} />
    <CornerBrand handle={myBrand.handle} />
    <HookPresenter />
    <Laptop />
    <Hand />
    <Stickers />
    <Bubble />
    <CommentCTA at={L(7)} keyword="ADS" question="Meta ads chalwane hain?" handle={myBrand.handle} sub="Solvix team DM karegi" />
    <Captions words={words} lines={LINES} top={CAP_TOP} hi={HI} size={46} />
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);
