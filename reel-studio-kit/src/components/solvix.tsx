// Shared pieces for Solvix-branded reels in the logo's colours: black + neon green.
// Logo for dark backgrounds: public/brand/solvix-logo-dark.png (made from the original logo).
import React from "react";
import { Audio, Img, interpolate, random, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";

export const SX = { bg: "#050806", ink: "#F2F5F1", dim: "#8B978E", green: "#22E01C", green2: "#13A10E", card: "#0F1511", line: "#1F2B22", red: "#FF4D4D", amber: "#FFB020" };
export const FONT = "Inter, 'Noto Color Emoji', sans-serif";
export const MONO = "JetBrains Mono, monospace";
export const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
export const LOGO = "brand/solvix-logo-dark.png";

export const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    fps,
    sp: (a: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - a, fps, config: cfg }),
    fade: (a: number, len = 6) => interpolate(frame - a, [0, len], [0, 1], CL),
  };
};

/** Black stage with a slow neon-green glow and soft floating dots. */
export const SolvixBackdrop: React.FC<{ glowY?: number }> = ({ glowY = 55 }) => {
  const { frame } = useA();
  return (
    <div style={{ position: "absolute", inset: 0, background: SX.bg }}>
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(50% 32% at ${50 + Math.sin(frame / 80) * 12}% ${glowY + Math.cos(frame / 95) * 6}%, ${SX.green}2E, transparent 72%)` }} />
      {Array.from({ length: 22 }, (_, i) => {
        const x = random(`x${i}`) * 1080;
        const y = (random(`y${i}`) * 1920 - frame * (0.3 + random(`s${i}`) * 0.6)) % 1920;
        const r = 3 + random(`r${i}`) * 7;
        return <div key={i} style={{ position: "absolute", left: x, top: y < 0 ? y + 1920 : y, width: r, height: r, borderRadius: "50%", background: SX.green, opacity: 0.08 + random(`o${i}`) * 0.18, filter: "blur(2px)" }} />;
      })}
      <div style={{ position: "absolute", inset: 0, background: "radial-gradient(ellipse at 50% 50%, transparent 55%, rgba(0,0,0,.55))" }} />
    </div>
  );
};

/** Small logo + handle, top right inside the safe area. */
export const CornerBrand: React.FC<{ handle: string }> = ({ handle }) => (
  <div style={{ position: "absolute", right: 140, top: 236, display: "flex", alignItems: "center", gap: 10, opacity: 0.9 }}>
    <Img src={staticFile(LOGO)} style={{ width: 54, height: 54 }} />
    <div style={{ fontFamily: MONO, fontSize: 18, color: SX.dim, letterSpacing: 1 }}>{handle}</div>
  </div>
);

/** "● 01 SCAN" style step label, top left. */
export const StepLabel: React.FC<{ text: string; at: number; to: number }> = ({ text, at, to }) => {
  const { frame, fade } = useA();
  if (frame < at || frame >= to) return null;
  return (
    <div style={{ position: "absolute", left: 70, top: 248, display: "flex", alignItems: "center", gap: 12, fontFamily: MONO, fontSize: 24, letterSpacing: 3, color: SX.green, opacity: fade(at, 8) * interpolate(frame, [to - 6, to], [1, 0], CL) }}>
      <span style={{ width: 12, height: 12, borderRadius: "50%", background: SX.green, boxShadow: `0 0 14px ${SX.green}` }} />
      {text}
    </div>
  );
};

type W = { word: string; start: number; end: number };
/** Kinetic subtitles: the spoken line, words light up as they are said; words in `hi` turn neon green. */
export const Captions: React.FC<{ words: W[]; lines: number[]; top: number; hi: Set<number>; size?: number; left?: number; right?: number }> = ({ words, lines, top, hi, size = 50, left = 70, right = 130 }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const tt = frame / fps;
  let cur = -1;
  words.forEach((w, i) => { if (w.start <= tt) cur = i; });
  if (cur < 0) return null;
  let li = 0;
  for (let i = 0; i < lines.length; i++) if (cur >= lines[i]) li = i;
  const end = lines[li + 1] ?? words.length;
  if (tt > words[end - 1].end + 0.6 && li === lines.length - 1) return null;
  const ws = words.slice(lines[li], end);
  const fs = ws.length > 14 ? size * 0.82 : ws.length > 10 ? size * 0.9 : size;
  return (
    <div data-el="title" data-kind="content" style={{ position: "absolute", left, right, top, display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "2px 12px", fontFamily: FONT, fontWeight: 800, fontSize: fs, lineHeight: 1.2, textShadow: "0 4px 18px rgba(0,0,0,.8)" }}>
      {ws.map((w, k) => {
        const idx = lines[li] + k;
        const said = idx <= cur;
        const p = interpolate(tt - w.start, [0, 0.12], [0, 1], CL);
        return (
          <span key={idx} style={{ color: hi.has(idx) ? SX.green : SX.ink, opacity: said ? 0.3 + 0.7 * p : 0.22, transform: `translateY(${said ? (1 - p) * 10 : 10}px)`, whiteSpace: "nowrap" }}>
            {w.word.replace(/[—,.:!?"]+$/u, "").replace(/^"/, "")}
          </span>
        );
      })}
    </div>
  );
};

export type Cue = { at: number; name: string; vol?: number; len?: number };
/** Voice, ducked music and one-shot sound effects. */
export const SoundTrack: React.FC<{ voice: string; music: string; words: W[]; cues: Cue[]; duration: number; musicVol?: number }> = ({ voice, music, words, cues, duration, musicVol = 0.2 }) => {
  const { fps } = useVideoConfig();
  const talking = (f: number) => words.some((w) => f / fps >= w.start - 0.15 && f / fps <= w.end + 0.25);
  return (
    <>
      <Audio src={staticFile(music)} volume={(f) => (talking(f) ? musicVol * 0.4 : musicVol) * interpolate(f, [0, 15], [0.3, 1], CL) * interpolate(f, [duration - 25, duration], [1, 0], CL)} />
      <Audio src={staticFile(voice)} />
      {cues.map((c, i) => (
        <Sequence key={i} from={Math.max(0, Math.round(c.at))} durationInFrames={c.len ?? 60} layout="none">
          <Audio src={staticFile(`sfx/${c.name}.mp3`)} volume={c.vol ?? 0.5} />
        </Sequence>
      ))}
    </>
  );
};

/** Phone body with a notch. The probe box is a separate empty element so zoomed screen content is not read as overflow. */
export const PhoneFrame: React.FC<{ x: number; y: number; w: number; h: number; style?: React.CSSProperties; screenBg?: string; children: React.ReactNode }> = ({ x, y, w, h, style, screenBg = "#FAFAF7", children }) => (
  <div style={{ position: "absolute", left: x, top: y, width: w, height: h, borderRadius: 56, background: "#0A0A0A", border: "3px solid #2A332C", boxShadow: `0 40px 100px rgba(0,0,0,.75), 0 0 70px ${SX.green}22`, ...style }}>
    <div style={{ position: "absolute", left: 12, top: 12, right: 12, bottom: 12, borderRadius: 44, background: screenBg, clipPath: "inset(0 round 44px)" }}>{children}</div>
    <div style={{ position: "absolute", top: 24, left: "50%", width: 120, height: 30, marginLeft: -60, borderRadius: 20, background: "#000" }} />
    <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 0, pointerEvents: "none" }} />
  </div>
);

/** Green tap ripple at (x, y) inside its parent. */
export const Tap: React.FC<{ x: number; y: number; at: number }> = ({ x, y, at }) => {
  const { frame } = useA();
  if (frame < at || frame > at + 18) return null;
  const s = 70 * (0.4 + interpolate(frame - at, [0, 18], [0, 1.1], CL));
  return <div style={{ position: "absolute", left: x - s / 2, top: y - s / 2, width: s, height: s, borderRadius: "50%", background: `${SX.green}55`, border: `4px solid ${SX.green}`, boxSizing: "border-box", opacity: 1 - interpolate(frame - at, [6, 18], [0, 1], CL) }} />;
};

/** Comment box CTA: logo, question, keyword pill, the keyword being typed into a comment. */
export const CommentCTA: React.FC<{ at: number; keyword: string; question: string; handle: string; sub: string }> = ({ at, keyword, question, handle, sub }) => {
  const { frame, sp, fade } = useA();
  if (frame < at - 2) return null;
  const typed = Math.round(interpolate(frame, [at + 14, at + 34], [0, keyword.length], CL));
  const done = frame >= at + 40;
  return (
    <div data-scene="cta" style={{ position: "absolute", inset: 0, opacity: fade(at - 2, 6) }}>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 100, right: 160, top: 640, background: SX.card, border: `2px solid ${SX.green}66`, borderRadius: 36, padding: "34px 34px 30px", fontFamily: FONT, color: SX.ink, textAlign: "center", boxShadow: `0 0 120px ${SX.green}33` }}>
        <Img src={staticFile(LOGO)} style={{ width: 130, height: 130, transform: `scale(${Math.min(1, sp(at))})` }} />
        <div style={{ fontSize: 54, fontWeight: 800, marginTop: 4 }}>{question}</div>
        <div style={{ display: "inline-block", marginTop: 16, background: SX.green, color: "#031003", fontSize: 76, fontWeight: 800, letterSpacing: 4, padding: "2px 30px", borderRadius: 18, transform: `scale(${Math.min(1.04, 0.5 + 0.5 * sp(at + 6, { damping: 8, stiffness: 200, mass: 0.6 }))})` }}>{keyword}</div>
        <div style={{ marginTop: 22, display: "flex", alignItems: "center", gap: 12, background: "#18201A", borderRadius: 999, padding: "12px 22px", fontSize: 30, fontWeight: 700, textAlign: "left" }}>
          <span style={{ flex: 1 }}>{done ? <span style={{ color: SX.dim, fontWeight: 500 }}>Add a comment…</span> : keyword.slice(0, typed)}</span>
          <span style={{ color: SX.green }}>Post</span>
        </div>
        <div style={{ marginTop: 14, fontFamily: MONO, fontSize: 22, color: SX.green, opacity: done ? 1 : 0 }}>✓ {sub} · {handle}</div>
      </div>
    </div>
  );
};
