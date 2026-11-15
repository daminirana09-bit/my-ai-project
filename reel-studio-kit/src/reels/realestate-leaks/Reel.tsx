// Reel "realestate-leaks" (~59 s): 4 galtiyan that leak property leads -> 4 hal with a website.
// Dark navy + gold + coral look, word-synced headline, a visual per scene, background music that ducks
// under the voice, and a sound effect on every animation.
// Voice: projects/realestate-leaks (voice.txt/captions.txt) -> `npm run voice:timed` (George), sped up 7%.
// Music + SFX: `npm run audio-assets` (Mixkit free licenses) -> public/music, public/sfx.
import React from "react";
import { Audio, Easing, interpolate, random, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { brand as myBrand } from "../../brand";
import { ReelFrame, Scene, type ReelProps } from "../../components";
import { creamStory } from "../../presets";
import { sec, timing } from "../../timing";
import words from "../../../public/reels/realestate-leaks/captions.words.json";

export const t = timing(words);
export const AUDIO = "reels/realestate-leaks/vo.wav";
export const durationInFrames = t.duration(1.8);
const at = (i: number) => t.at(i, 0);

const C = { bg: "#070A12", card: "#121827", line: "#232B3D", text: "#F3F1EA", dim: "#8A93A6", gold: "#F5B83D", coral: "#FF5D5D", green: "#3DD68C", wa: "#25D366" };
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";

// ---------- timing ----------
const LINE_STARTS = [0, 7, 12, 20, 36, 53, 68, 82, 88, 97, 109, 123, 132, words.length];
const S = {
  hook: [0, at(20)],
  g1: [at(20), at(36)],
  g2: [at(36), at(53)],
  g3: [at(53), at(68)],
  g4: [at(68), at(82)],
  turn: [at(82), at(88)],
  h1: [at(88), at(97)],
  h2: [at(97), at(109)],
  h3: [at(109), at(123)],
  h4: [at(123), at(132)],
  cta: [at(132), durationInFrames],
} as const;

// ---------- sound design: one cue per animation ----------
type Cue = { at: number; name: string; vol?: number; len?: number };
const SFX: Cue[] = [
  // scene transitions
  ...[S.g1, S.g2, S.g3, S.g4, S.h1, S.h2, S.h3, S.h4, S.cta].map((s) => ({ at: s[0] - 4, name: "whoosh", vol: 0.35 })),
  // hook
  { at: 2, name: "pop", vol: 0.6 },
  { at: at(6), name: "tick", vol: 0.35 },
  { at: at(10), name: "buzz", vol: 0.3 },
  { at: at(12) + 6, name: "whoosh", vol: 0.4 },
  { at: at(18), name: "stamp", vol: 0.6 },
  // galti 1
  { at: at(26), name: "pop", vol: 0.6 },
  { at: at(30), name: "click", vol: 0.45 },
  { at: at(32), name: "click", vol: 0.45 },
  { at: at(33), name: "click", vol: 0.45 },
  { at: at(35) + 6, name: "stamp", vol: 0.75 },
  { at: at(35) + 7, name: "buzz", vol: 0.25 },
  // galti 2
  { at: at(39), name: "ring", vol: 0.35, len: 52 },
  { at: at(41), name: "pop", vol: 0.5 },
  { at: at(45), name: "buzz", vol: 0.35 },
  { at: at(49), name: "message", vol: 0.5 },
  // galti 3
  { at: at(55), name: "pop", vol: 0.55 },
  { at: at(60), name: "buzz", vol: 0.25 },
  { at: at(62), name: "buzz", vol: 0.25 },
  { at: at(65), name: "stamp", vol: 0.75 },
  // galti 4
  { at: at(70), name: "message", vol: 0.5 },
  { at: at(73), name: "ui", vol: 0.5 },
  { at: at(74), name: "ui", vol: 0.5 },
  { at: at(75), name: "ui", vol: 0.5 },
  { at: at(76) + 4, name: "stamp", vol: 0.75 },
  { at: at(80), name: "message", vol: 0.4 },
  // turn
  { at: at(82) - 2, name: "impact", vol: 0.55 },
  { at: at(86), name: "pop", vol: 0.6 },
  // hal 1
  { at: at(88) + 6, name: "pop", vol: 0.5 },
  { at: at(90), name: "tick", vol: 0.3 },
  { at: at(91), name: "tick", vol: 0.3 },
  { at: at(92), name: "tick", vol: 0.3 },
  { at: at(94), name: "tick", vol: 0.3 },
  // hal 2
  { at: at(100), name: "pop", vol: 0.5 },
  { at: at(102), name: "typing", vol: 0.35, len: 40 },
  { at: at(105), name: "click", vol: 0.5 },
  { at: at(107), name: "message", vol: 0.6 },
  // hal 3
  { at: at(112), name: "ui", vol: 0.55 },
  { at: at(116), name: "click", vol: 0.5 },
  { at: at(117), name: "pop", vol: 0.5 },
  { at: at(120), name: "success", vol: 0.45 },
  // hal 4
  { at: at(124), name: "pop", vol: 0.5 },
  { at: at(126), name: "ui", vol: 0.4 },
  { at: at(128), name: "tick", vol: 0.35 },
  { at: at(130), name: "success", vol: 0.45 },
  // cta
  { at: at(136), name: "pop", vol: 0.6 },
  { at: at(138), name: "impact", vol: 0.35 },
  { at: at(140), name: "message", vol: 0.45 },
];

const SoundTrack: React.FC = () => {
  const { fps } = useVideoConfig();
  const talking = (f: number) => (words as { start: number; end: number }[]).some((w) => f / fps >= w.start - 0.15 && f / fps <= w.end + 0.25);
  return (
    <>
      <Audio
        src={staticFile("music/hiphop-02.mp3")}
        volume={(f) => {
          const fadeIn = interpolate(f, [0, 20], [0, 1], CL);
          const fadeOut = interpolate(f, [durationInFrames - 30, durationInFrames], [1, 0], CL);
          return (talking(f) ? 0.09 : 0.2) * fadeIn * fadeOut;
        }}
      />
      {SFX.filter((c) => c.at >= 0).map((c, i) => (
        <Sequence key={i} from={c.at} durationInFrames={c.len ?? 60} layout="none">
          <Audio src={staticFile(`sfx/${c.name}.mp3`)} volume={c.vol ?? 0.5} />
        </Sequence>
      ))}
    </>
  );
};

// ---------- helpers ----------
const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    sp: (a: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - a, fps, config: cfg }),
    fade: (a: number, len = 5) => interpolate(frame - a, [0, len], [0, 1], CL),
  };
};

const Backdrop: React.FC = () => {
  const { frame } = useA();
  const x1 = 25 + Math.sin(frame / 70) * 10, y1 = 30 + Math.cos(frame / 90) * 8;
  const x2 = 75 + Math.cos(frame / 80) * 10, y2 = 70 + Math.sin(frame / 60) * 8;
  return (
    <div style={{ position: "absolute", inset: 0, background: C.bg }}>
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(40% 30% at ${x1}% ${y1}%, ${C.gold}2E, transparent 70%), radial-gradient(42% 30% at ${x2}% ${y2}%, ${C.coral}26, transparent 70%)` }} />
      <div style={{ position: "absolute", inset: 0, opacity: 0.06, backgroundImage: "radial-gradient(#fff 0.8px, transparent 1px)", backgroundSize: "5px 5px", backgroundPosition: `${Math.floor(random(`g${frame}`) * 5)}px 0` }} />
      <div style={{ position: "absolute", inset: 0, background: "radial-gradient(ellipse at 50% 45%, transparent 55%, rgba(0,0,0,.6) 100%)" }} />
    </div>
  );
};

const TopBar: React.FC = () => {
  const { frame } = useA();
  return (
    <>
      <div style={{ position: "absolute", left: 60, right: 60, top: 196, height: 5, background: "#ffffff14", borderRadius: 3 }}>
        <div style={{ width: `${(frame / durationInFrames) * 100}%`, height: "100%", background: C.gold, borderRadius: 3 }} />
      </div>
      <div style={{ position: "absolute", left: 60, top: 140, fontFamily: FONT, fontWeight: 800, fontSize: 30, color: C.text }}>solvix_web</div>
      <div style={{ position: "absolute", right: 60, top: 146, fontFamily: MONO, fontSize: 22, color: C.dim }}>real estate · websites</div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 1490, textAlign: "center", fontFamily: FONT, fontSize: 24, fontWeight: 600, color: C.dim }}>
        <span style={{ color: C.gold }}>{myBrand.handle}</span> · websites for real estate
      </div>
    </>
  );
};

const Pill: React.FC<{ text: string; color: string; at: number }> = ({ text, color, at: a }) => {
  const { sp, fade } = useA();
  return (
    <div style={{ position: "absolute", left: 0, right: 0, top: 236, display: "flex", justifyContent: "center" }}>
      <div style={{ opacity: fade(a), transform: `scale(${0.7 + 0.3 * sp(a)})`, background: color, color: C.bg, fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, padding: "6px 16px", borderRadius: 8 }}>{text}</div>
    </div>
  );
};

// Word styles in the headline (word index -> style)
const GOLD_BOX = new Set([4, 11, 26, 86, 89, 101, 107, 112, 126, 138]);
const CORAL = new Set([18, 19, 30, 32, 35, 46, 49, 50, 60, 62, 63, 67, 74, 75, 80]);
const GOLD = new Set([90, 91, 92, 94, 102, 103, 104, 117, 118, 120, 128, 130, 131]);

/** The sentence being spoken, words appearing as they are said. */
const Headline: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const tt = frame / fps;
  const W = words as { word: string; start: number; end: number }[];
  let cur = -1;
  W.forEach((w, i) => { if (w.start <= tt) cur = i; });
  if (cur < 0) return null;
  let li = 0;
  for (let i = 0; i < LINE_STARTS.length - 1; i++) if (cur >= LINE_STARTS[i]) li = i;
  const ws = W.slice(LINE_STARTS[li], LINE_STARTS[li + 1]);
  const size = ws.length > 12 ? 50 : ws.length > 7 ? 58 : 66;
  return (
    <div data-el="title" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 300, display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "6px 14px", fontFamily: FONT, fontWeight: 800, fontSize: size, lineHeight: 1.15, color: C.text, textAlign: "center" }}>
      {ws.map((w, k) => {
        const idx = LINE_STARTS[li] + k;
        const shown = idx <= cur;
        const p = interpolate(tt - w.start, [0, 0.12], [0, 1], CL);
        const box = GOLD_BOX.has(idx);
        return (
          <span
            key={idx}
            style={{
              opacity: shown ? 0.25 + 0.75 * p : 0.12,
              transform: `translateY(${shown ? (1 - p) * 10 : 10}px)`,
              color: box ? C.bg : CORAL.has(idx) ? C.coral : GOLD.has(idx) ? C.gold : C.text,
              background: box && shown ? C.gold : "transparent",
              borderRadius: 8,
              padding: box ? "0 10px" : 0,
              whiteSpace: "nowrap",
            }}
          >
            {w.word}
          </span>
        );
      })}
    </div>
  );
};

const card: React.CSSProperties = { background: C.card, border: `2px solid ${C.line}`, borderRadius: 28, fontFamily: FONT, color: C.text, boxShadow: "0 30px 80px rgba(0,0,0,.45)" };

const Stamp: React.FC<{ at: number; text: string; color?: string; rot?: number; style?: React.CSSProperties }> = ({ at: a, text, color = C.coral, rot = -10, style }) => {
  const { frame, sp } = useA();
  if (frame < a) return null;
  const s = sp(a, { damping: 9, stiffness: 260, mass: 0.6 });
  return (
    <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", ...style, transform: `rotate(${rot}deg) scale(${1.8 - 0.8 * Math.min(1, s)})`, opacity: Math.min(1, s * 2), border: `7px solid ${color}`, color, borderRadius: 14, padding: "6px 24px", fontFamily: FONT, fontWeight: 800, fontSize: 64, letterSpacing: 4, background: "rgba(7,10,18,.75)" }}>
      {text}
    </div>
  );
};

const Chip: React.FC<{ at: number; text: string; color?: string; style?: React.CSSProperties }> = ({ at: a, text, color = C.text, style }) => {
  const { sp, fade } = useA();
  return (
    <div style={{ position: "absolute", ...style, opacity: fade(a, 4), transform: `scale(${Math.min(1, 0.5 + 0.5 * sp(a, { damping: 10, stiffness: 220, mass: 0.6 }))})`, background: "#fff", color: "#111", borderRadius: 16, padding: "10px 18px", fontFamily: FONT, fontWeight: 800, fontSize: 28, boxShadow: "0 10px 30px rgba(0,0,0,.4)", whiteSpace: "nowrap" }}>
      <span style={{ color }}>{text}</span>
    </div>
  );
};

const House: React.FC<{ size: number; color: string }> = ({ size, color }) => (
  <svg width={size} height={size} viewBox="0 0 24 24">
    <path d="M3 11 12 3l9 8v10H3z" fill={color} />
    <rect x="10" y="14" width="4" height="7" fill={C.bg} opacity={0.5} />
  </svg>
);

const Phone: React.FC<{ children: React.ReactNode; dim?: number; w?: number; h?: number }> = ({ children, dim = 0, w = 430, h = 720 }) => (
  <div data-el="card" data-kind="content" style={{ position: "relative", width: w, height: h, borderRadius: 54, background: "#05070C", border: `3px solid ${C.line}`, padding: 14, boxShadow: "0 40px 90px rgba(0,0,0,.6)" }}>
    <div style={{ position: "absolute", top: 22, left: "50%", width: 110, height: 28, marginLeft: -55, borderRadius: 20, background: "#000", zIndex: 3 }} />
    <div style={{ position: "relative", width: "100%", height: "100%", borderRadius: 42, overflow: "hidden", background: "#0E1320", filter: `brightness(${1 - 0.55 * dim})` }}>{children}</div>
  </div>
);

const center: React.CSSProperties = { position: "absolute", left: 0, right: 0, top: 580, display: "flex", justifyContent: "center" };

// ---------- scenes ----------
const Hook: React.FC = () => {
  const { frame, fade } = useA();
  const count = Math.round(interpolate(frame, [at(1), at(6)], [0, 5000], { ...CL, easing: Easing.out(Easing.cubic) }));
  const N = 80;
  const keep = new Set([27, 52]);
  const dimAt = at(10);
  const fallAt = at(12) + 6;
  return (
    <div data-el="card" data-kind="content" style={{ position: "absolute", left: 60, right: 120, top: 620, display: "flex", flexDirection: "column", alignItems: "center" }}>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(10, 64px)", gap: 14 }}>
        {Array.from({ length: N }, (_, i) => {
          const lit = frame >= 4 + i * 0.6 * (at(6) / 60) ? 1 : 0.15;
          const kept = keep.has(i);
          const dim = frame >= dimAt && !kept ? 0.18 : 1;
          const fall = frame >= fallAt && !kept ? (frame - fallAt - random(`d${i}`) * 10) : 0;
          const y = fall > 0 ? 0.6 * fall * fall : 0;
          const o = fall > 0 ? Math.max(0, 1 - fall / 30) : 1;
          return (
            <div key={i} style={{ transform: `translateY(${y}px)`, opacity: lit * dim * o }}>
              <House size={64} color={kept && frame >= dimAt ? C.gold : C.text} />
            </div>
          );
        })}
      </div>
      <div style={{ marginTop: 40, fontFamily: FONT, fontWeight: 800, fontSize: 92, color: frame >= dimAt ? C.gold : C.text, letterSpacing: -2 }}>
        {frame >= dimAt ? "2" : count.toLocaleString("en-IN")}
        <span style={{ fontSize: 34, color: C.dim, marginLeft: 16, letterSpacing: 0 }}>{frame >= dimAt ? "site visits" : "views"}</span>
      </div>
      <div style={{ opacity: fade(at(18)), marginTop: 6, fontFamily: MONO, fontSize: 26, color: C.coral }}>4,998 log kahan gaye?</div>
    </div>
  );
};

const G1: React.FC = () => {
  const { frame } = useA();
  const skip = at(35) + 6;
  return (
    <div style={center}>
      <div style={{ position: "relative" }}>
        <Phone dim={frame >= skip ? 1 : 0}>
          <div style={{ padding: "60px 22px", fontFamily: FONT, color: C.text }}>
            <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
              <div style={{ width: 84, height: 84, borderRadius: "50%", background: `linear-gradient(135deg, ${C.gold}, ${C.coral})` }} />
              <div>
                <div style={{ fontSize: 26, fontWeight: 800 }}>sharma.properties</div>
                <div style={{ fontSize: 20, color: C.dim }}>48 posts · 2.1K followers</div>
              </div>
            </div>
            <div style={{ fontSize: 22, marginTop: 14, lineHeight: 1.4 }}>Flats · Plots · Commercial<br />DM for details</div>
            <div style={{ marginTop: 16, background: C.coral, color: "#fff", textAlign: "center", fontWeight: 800, fontSize: 24, borderRadius: 12, padding: "12px 0" }}>Call 98•• ••• 410</div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 6, marginTop: 16 }}>
              {Array.from({ length: 6 }, (_, i) => (
                <div key={i} style={{ height: 118, borderRadius: 8, background: `linear-gradient(${120 + i * 30}deg, #2B3550, #1A2133)`, display: "grid", placeItems: "end center" }}>
                  <House size={46} color="#3D4966" />
                </div>
              ))}
            </div>
          </div>
        </Phone>
        <Chip at={at(30)} text="Price kya hai?" color={C.coral} style={{ left: -150, top: 120 }} />
        <Chip at={at(32)} text="Location?" color={C.coral} style={{ right: -120, top: 260 }} />
        <Chip at={at(33)} text="Photos?" color={C.coral} style={{ left: -110, top: 430 }} />
        <Stamp at={skip} text="SKIP" style={{ left: 90, top: 300 }} />
      </div>
    </div>
  );
};

const G2: React.FC = () => {
  const { frame, fade, sp } = useA();
  const missed = frame >= at(45);
  const pulse = (frame - at(39)) % 30;
  return (
    <div style={center}>
      <div style={{ position: "relative" }}>
        <Phone>
          <div style={{ height: "100%", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", fontFamily: FONT, color: C.text, background: missed ? "#1B0E12" : "#0E1320" }}>
            <div style={{ fontSize: 24, color: missed ? C.coral : C.dim, fontWeight: 700 }}>{missed ? "MISSED CALL" : "incoming call…"}</div>
            <div style={{ fontSize: 40, fontWeight: 800, marginTop: 8 }}>Rohit</div>
            <div style={{ fontSize: 22, color: C.dim }}>2BHK enquiry</div>
            <div style={{ position: "relative", width: 150, height: 150, marginTop: 40 }}>
              <div style={{ position: "absolute", inset: 0, borderRadius: "50%", background: missed ? C.coral : C.green, display: "grid", placeItems: "center", boxShadow: !missed && frame >= at(39) ? `0 0 0 ${pulse}px rgba(61,214,140,${0.6 * (1 - pulse / 30)})` : "none" }}>
                <svg width="70" height="70" viewBox="0 0 24 24" style={{ transform: `rotate(${!missed && frame >= at(39) ? Math.sin(frame / 1.5) * 12 : 0}deg)` }}><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z" fill="#fff" /></svg>
              </div>
            </div>
          </div>
        </Phone>
        <Chip at={at(41)} text="Aap site pe the" color="#111" style={{ left: -170, top: 80 }} />
        <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ ...card, position: "absolute", right: -190, top: 470, width: 330, padding: "16px 20px", opacity: fade(at(49)), transform: `translateX(${(1 - sp(at(49))) * 120}px)` }}>
          <div style={{ fontSize: 20, color: C.dim, fontWeight: 700 }}>Doosra broker</div>
          <div style={{ fontSize: 28, fontWeight: 800, color: C.green }}>✓ Call picked</div>
        </div>
      </div>
    </div>
  );
};

const RowX: React.FC<{ at: number; label: string; ok?: boolean }> = ({ at: a, label, ok }) => {
  const { fade, sp } = useA();
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 18, marginTop: 18, opacity: fade(a, 4), transform: `translateX(${(1 - sp(a)) * -30}px)` }}>
      <span style={{ width: 46, height: 46, borderRadius: "50%", background: ok ? C.green : C.coral, color: "#fff", display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800 }}>{ok ? "✓" : "✕"}</span>
      <span style={{ fontSize: 34, fontWeight: 800 }}>{label}</span>
    </div>
  );
};

const G3: React.FC = () => (
  <div style={center}>
    <div style={{ position: "relative" }}>
      <div data-el="card" data-kind="content" style={{ ...card, width: 760, padding: 34 }}>
        <div style={{ fontSize: 24, color: C.dim, fontWeight: 700 }}>SITE VISIT</div>
        <div style={{ fontSize: 44, fontWeight: 800, marginTop: 6 }}>Sunday · 11:00 AM</div>
        <div style={{ fontSize: 28, color: C.dim }}>Green Valley Residency</div>
        <RowX at={at(60)} label="Reminder" />
        <RowX at={at(62)} label="Map pin" />
      </div>
      <Stamp at={at(65)} text="NO SHOW" style={{ right: -20, top: 150 }} rot={8} />
    </div>
  </div>
);

const G4: React.FC = () => {
  const { frame, fade } = useA();
  const days = ["1 din baad", "3 din baad", "1 hafta baad"];
  return (
    <div style={center}>
      <div style={{ position: "relative" }}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 680, padding: 0, overflow: "hidden" }}>
          <div style={{ background: "#0F5E54", padding: "16px 24px", fontSize: 28, fontWeight: 800 }}>Rohit · 2BHK</div>
          <div style={{ height: 420, padding: 22, background: "#0B141A", position: "relative" }}>
            <div style={{ marginLeft: "auto", width: 420, opacity: fade(at(70)), background: "#005C4B", borderRadius: 16, padding: "14px 18px", fontSize: 26, fontWeight: 700 }}>
              Brochure_GreenValley.pdf
              <div style={{ fontSize: 18, color: "#9FD6C9", textAlign: "right" }}>✓✓ seen</div>
            </div>
            {days.map((d, i) => (
              <div key={d} style={{ marginTop: 22, textAlign: "center", opacity: frame >= at(73 + i) ? 1 : 0 }}>
                <span style={{ background: "#1F2C34", borderRadius: 10, padding: "6px 14px", fontSize: 22, color: C.dim, fontFamily: MONO }}>{d} · koi message nahi</span>
              </div>
            ))}
          </div>
        </div>
        <Stamp at={at(76) + 4} text="DEAL GAYI" style={{ left: 120, top: 210 }} />
      </div>
    </div>
  );
};

const Turn: React.FC = () => {
  const { frame, sp } = useA();
  const s = sp(at(86), { damping: 9, stiffness: 200, mass: 0.7 });
  const flash = interpolate(frame - at(82), [0, 6, 14], [0, 0.35, 0], CL);
  return (
    <>
      <div style={{ position: "absolute", inset: 0, background: C.gold, opacity: flash }} />
      <div data-el="title" data-kind="content" style={{ position: "absolute", left: 60, right: 120, top: 700, textAlign: "center", fontFamily: FONT, fontWeight: 800, padding: "0 30px" }}>
        <div style={{ fontSize: 70, color: C.text }}>AB YAHI SAB,</div>
        <div style={{ fontSize: 132, color: C.gold, letterSpacing: -3, transform: `scale(${0.6 + 0.4 * Math.min(1.1, s)})`, textShadow: `0 0 60px ${C.gold}66` }}>WEBSITE</div>
      </div>
    </>
  );
};

const H1: React.FC = () => {
  const { fade, sp } = useA();
  const tag = (a: number, label: string, style: React.CSSProperties) => (
    <div style={{ position: "absolute", ...style, opacity: fade(a, 4), transform: `scale(${Math.min(1, 0.5 + 0.5 * sp(a))})`, background: C.gold, color: C.bg, fontFamily: FONT, fontWeight: 800, fontSize: 26, borderRadius: 12, padding: "8px 14px", whiteSpace: "nowrap" }}>{label}</div>
  );
  return (
    <div style={center}>
      <Phone>
        <div style={{ fontFamily: FONT, color: C.text }}>
          <div style={{ height: 360, background: "linear-gradient(180deg, #F5B83D33, #121827), linear-gradient(90deg, #2B3550, #1A2133)", position: "relative", display: "grid", placeItems: "end center" }}>
            <svg width="300" height="240" viewBox="0 0 30 24"><rect x="3" y="6" width="10" height="18" fill="#3D4966" /><rect x="14" y="2" width="12" height="22" fill="#4C5A7A" />{Array.from({ length: 12 }, (_, i) => <rect key={i} x={16 + (i % 3) * 3.3} y={4 + Math.floor(i / 3) * 4.6} width="2" height="2.6" fill="#F5B83D" opacity="0.8" />)}</svg>
            {tag(at(90), "₹68 L", { left: 16, top: 70 })}
            {tag(at(92), "1/12 photos", { right: 16, top: 70 })}
            {tag(at(94), "▶ Video tour", { right: 16, top: 290 })}
          </div>
          <div style={{ padding: 22 }}>
            <div style={{ fontSize: 34, fontWeight: 800 }}>2 BHK · 1,150 sq.ft</div>
            <div style={{ fontSize: 24, color: C.gold, marginTop: 6, opacity: fade(at(91)) }}>● Sector 45, Gurugram</div>
            <div style={{ marginTop: 18, background: C.wa, color: "#fff", textAlign: "center", fontWeight: 800, fontSize: 26, borderRadius: 14, padding: "14px 0" }}>Enquire on WhatsApp</div>
          </div>
        </div>
      </Phone>
    </div>
  );
};

const typed = (frame: number, s: string, a: number, b: number) => s.slice(0, Math.round(interpolate(frame, [a, Math.max(a + 1, b)], [0, s.length], CL)));

const H2: React.FC = () => {
  const { frame, fade, sp } = useA();
  const fields: [string, string, number][] = [["Naam", "Rohit", at(102)], ["Budget", "₹60–70 L", at(103)], ["Location", "Sector 45", at(104)]];
  const sent = frame >= at(105);
  return (
    <div style={{ ...center, flexDirection: "column", alignItems: "center" }}>
      <div data-el="card" data-kind="content" style={{ ...card, width: 700, padding: 30 }}>
        <div style={{ fontSize: 32, fontWeight: 800, marginBottom: 12 }}>Enquiry form</div>
        {fields.map(([k, v, a]) => (
          <div key={k} style={{ display: "flex", gap: 16, alignItems: "center", border: `2px solid ${C.line}`, borderRadius: 14, padding: "14px 18px", marginBottom: 12, fontSize: 30 }}>
            <span style={{ width: 140, color: C.dim, fontWeight: 700 }}>{k}</span>
            <span style={{ fontWeight: 800 }}>{typed(frame, v, a, a + 12)}</span>
          </div>
        ))}
        <div style={{ textAlign: "center", background: sent ? C.green : C.gold, color: C.bg, borderRadius: 14, padding: "16px 0", fontSize: 30, fontWeight: 800, transform: `scale(${sent && frame < at(105) + 6 ? 0.94 : 1})` }}>{sent ? "✓ Sent" : "Submit"}</div>
      </div>
      <div data-el="sticker" data-kind="content" style={{ marginTop: 26, width: 700, display: "flex", alignItems: "center", gap: 16, background: "#fff", color: "#111", borderRadius: 22, padding: "16px 20px", opacity: fade(at(107), 4), transform: `translateY(${(1 - sp(at(107))) * -40}px)`, fontFamily: FONT }}>
        <span style={{ width: 58, height: 58, borderRadius: 14, background: C.wa, flex: "none" }} />
        <div>
          <div style={{ fontSize: 22, fontWeight: 700, color: "#666" }}>WhatsApp · abhi</div>
          <div style={{ fontSize: 28, fontWeight: 800 }}>New lead: Rohit · 2BHK · ₹65 L</div>
        </div>
      </div>
    </div>
  );
};

const H3: React.FC = () => {
  const { frame, fade, sp } = useA();
  const chip = (label: string, on: boolean) => (
    <span style={{ padding: "12px 22px", borderRadius: 14, fontSize: 28, fontWeight: 800, background: on ? C.gold : "transparent", color: on ? C.bg : C.text, border: `2px solid ${on ? C.gold : C.line}` }}>{label}</span>
  );
  return (
    <div style={center}>
      <div data-el="card" data-kind="content" style={{ ...card, width: 760, padding: 30 }}>
        <div style={{ fontSize: 24, color: C.dim, fontWeight: 700 }}>BOOK A SITE VISIT</div>
        <div style={{ display: "flex", gap: 12, marginTop: 16 }}>{chip("Sat 22", false)}{chip("Sun 23", frame >= at(112))}{chip("Mon 24", false)}</div>
        <div style={{ display: "flex", gap: 12, marginTop: 14 }}>{chip("10:00", false)}{chip("11:00", frame >= at(116))}{chip("12:00", false)}</div>
        <div style={{ marginTop: 20, height: 150, borderRadius: 18, border: `2px solid ${C.line}`, backgroundImage: `linear-gradient(${C.line} 2px, transparent 2px), linear-gradient(90deg, ${C.line} 2px, transparent 2px)`, backgroundSize: "36px 36px", position: "relative", opacity: fade(at(117)) }}>
          <svg width="46" height="60" viewBox="0 0 24 32" style={{ position: "absolute", left: "50%", top: 30, marginLeft: -23, transform: `translateY(${(1 - Math.min(1, sp(at(117), { damping: 8, stiffness: 200, mass: 0.6 }))) * -40}px)` }}>
            <path d="M12 0C5.4 0 0 5.2 0 11.6 0 20.3 12 32 12 32s12-11.7 12-20.4C24 5.2 18.6 0 12 0z" fill={C.coral} />
            <circle cx="12" cy="11.5" r="4.6" fill="#fff" />
          </svg>
        </div>
        <div style={{ marginTop: 18, display: "inline-flex", gap: 12, alignItems: "center", background: `${C.green}22`, border: `2px solid ${C.green}`, borderRadius: 14, padding: "10px 18px", fontSize: 28, fontWeight: 800, color: C.green, opacity: fade(at(120)) }}>✓ Reminder set · Sun 10:00</div>
      </div>
    </div>
  );
};

const H4: React.FC = () => {
  const { frame, fade } = useA();
  const rows: [string, string, string, string][] = [
    ["Rohit", "2BHK", "Visit Sun", C.green],
    ["Priya", "Plot", "New", C.gold],
    ["Aman", "3BHK", "Follow-up aaj", C.coral],
    ["Neha", "Shop", "Brochure sent", C.dim],
  ];
  return (
    <div style={center}>
      <div data-el="card" data-kind="content" style={{ ...card, width: 780, padding: 26 }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <span style={{ fontFamily: MONO, fontSize: 24, color: C.dim }}>leads.sheet</span>
          <span style={{ background: C.coral, color: "#fff", borderRadius: 10, padding: "6px 14px", fontSize: 24, fontWeight: 800, opacity: fade(at(130)) }}>Follow-ups aaj: 3</span>
        </div>
        {rows.map(([n, typ, st, col], i) => (
          <div key={n} style={{ display: "flex", alignItems: "center", gap: 18, marginTop: 14, padding: "14px 16px", borderRadius: 14, background: i === 2 && frame >= at(128) ? `${C.coral}22` : "#0D1220", border: `2px solid ${i === 2 && frame >= at(128) ? C.coral : C.line}`, opacity: fade(at(124) + i * 4, 4) }}>
            <span style={{ width: 130, fontSize: 30, fontWeight: 800 }}>{n}</span>
            <span style={{ width: 110, fontSize: 26, color: C.dim }}>{typ}</span>
            <span style={{ marginLeft: "auto", fontSize: 24, fontWeight: 800, color: col }}>{st}</span>
          </div>
        ))}
      </div>
    </div>
  );
};

const CTA: React.FC = () => {
  const { frame, sp, fade } = useA();
  return (
    <div data-el="card" data-kind="content" style={{ ...card, position: "absolute", left: 90, right: 150, top: 640, padding: "40px 30px", textAlign: "center" }}>
      <div style={{ fontSize: 34, fontWeight: 700, color: C.dim }}>Real estate mein ho?</div>
      <div style={{ fontSize: 48, fontWeight: 800, opacity: fade(at(136)) }}>Comment karo</div>
      <div style={{ display: "inline-block", margin: "18px 0", background: C.gold, color: C.bg, borderRadius: 20, padding: "12px 40px", fontSize: 100, fontWeight: 800, letterSpacing: 4, opacity: fade(at(138), 4), transform: `scale(${Math.min(1.05, sp(at(138), { damping: 8, stiffness: 200, mass: 0.6 })) * (1 + 0.025 * Math.sin(frame / 5))})`, boxShadow: `0 0 70px ${C.gold}55` }}>PROPERTY</div>
      <div style={{ fontSize: 30, fontWeight: 700, color: C.dim, opacity: fade(at(140)) }}>details DM mein · {myBrand.handle}</div>
    </div>
  );
};

export const Reel: React.FC<ReelProps> = ({ brand = { ...creamStory, handle: myBrand.handle }, debugSafeZones, probe }) => (
  <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
    <Backdrop />
    <SoundTrack />
    <TopBar />
    <Headline />
    <Scene name="hook" from={S.hook[0]} to={S.hook[1]}><Hook /></Scene>
    <Scene name="galti-1" from={S.g1[0]} to={S.g1[1]}><Pill text="GALTI 1/4 · PROFILE" color={C.coral} at={S.g1[0]} /><G1 /></Scene>
    <Scene name="galti-2" from={S.g2[0]} to={S.g2[1]}><Pill text="GALTI 2/4 · CALL" color={C.coral} at={S.g2[0]} /><G2 /></Scene>
    <Scene name="galti-3" from={S.g3[0]} to={S.g3[1]}><Pill text="GALTI 3/4 · SITE VISIT" color={C.coral} at={S.g3[0]} /><G3 /></Scene>
    <Scene name="galti-4" from={S.g4[0]} to={S.g4[1]}><Pill text="GALTI 4/4 · FOLLOW-UP" color={C.coral} at={S.g4[0]} /><G4 /></Scene>
    <Scene name="turn" from={S.turn[0]} to={S.turn[1]}><Turn /></Scene>
    <Scene name="hal-1" from={S.h1[0]} to={S.h1[1]}><Pill text="HAL 1/4 · LISTINGS" color={C.gold} at={S.h1[0]} /><H1 /></Scene>
    <Scene name="hal-2" from={S.h2[0]} to={S.h2[1]}><Pill text="HAL 2/4 · ENQUIRY" color={C.gold} at={S.h2[0]} /><H2 /></Scene>
    <Scene name="hal-3" from={S.h3[0]} to={S.h3[1]}><Pill text="HAL 3/4 · BOOKING" color={C.gold} at={S.h3[0]} /><H3 /></Scene>
    <Scene name="hal-4" from={S.h4[0]} to={S.h4[1]}><Pill text="HAL 4/4 · LEADS" color={C.gold} at={S.h4[0]} /><H4 /></Scene>
    <Scene name="cta" from={S.cta[0]} to={S.cta[1] + 30}><Pill text="SOLVIX WEB" color={C.gold} at={S.cta[0]} /><CTA /></Scene>
  </ReelFrame>
);

export const _sec = sec;
