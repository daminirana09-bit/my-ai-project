// Reel "clinic-dialogue" (~48 s): SOLVIX STORIES · EP 02 — Dr. Neha Mehra (dentist, female voice) and
// Arjun from Solvix Web talk about a clinic website that follows the NDC rules.
// Voices: projects/clinic-dialogue/dialogue.json -> `npm run voice:dialogue -- clinic-dialogue`
// (writes vo.wav, captions.words.json with who/line per word, lines.json, mouth.A.json, mouth.R.json).
import React from "react";
import { Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { brand as myBrand } from "../../brand";
import { ReelFrame, Scene, type ReelProps } from "../../components";
import { StoryOutro } from "../../components/cardstory";
import { Toon, type PoseCue } from "../../components/toon";
import { Stage } from "./stage";
import { creamStory } from "../../presets";
import { sec } from "../../timing";
import words from "../../../public/reels/clinic-dialogue/captions.words.json";
import lines from "../../../public/reels/clinic-dialogue/lines.json";
import mouthN from "../../../public/reels/clinic-dialogue/mouth.N.json";
import mouthR from "../../../public/reels/clinic-dialogue/mouth.R.json";

export const AUDIO = "reels/clinic-dialogue/vo.wav";
const L = lines.map((l) => ({ ...l, s: sec(l.start), e: sec(l.end) }));
export const durationInFrames = L[L.length - 1].e + 45;
const W = words as { word: string; start: number; end: number; who: string; line: number }[];
/** frame where word n of line li is spoken */
const wAt = (li: number, n: number) => {
  const ws = W.filter((w) => w.line === li);
  return sec((ws[Math.min(n, ws.length - 1)] ?? { start: lines[li].start }).start);
};

const C = { ink: "#1E1E24", lime: "#C8F06A", red: "#E5484D", green: "#22A06B", wa: "#25D366", blue: "#2F6BFF", wall1: "#EEF4EF", wall2: "#DCEBE2", desk: "#6B4A35" };
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";

const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    sp: (at: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - at, fps, config: cfg }),
    fade: (at: number, len = 6) => interpolate(frame - at, [0, len], [0, 1], CL),
  };
};

// ---------- set ----------
const Room: React.FC = () => {
  const { frame } = useA();
  return (
    <div style={{ position: "absolute", inset: 0, background: `linear-gradient(180deg, ${C.wall1} 0%, ${C.wall2} 70%)` }}>
      {/* tooth poster */}
      <div style={{ position: "absolute", left: 420, top: 1120, width: 230, height: 190, background: "#fff", border: `4px solid ${C.ink}`, borderRadius: 16, display: "grid", placeItems: "center" }}>
        <svg width="110" height="120" viewBox="0 0 24 26"><path d="M6 2c-3 0-5 3-4.6 6.5.3 2.6 1.6 4 2.2 6.6.6 2.8 1 9 3.1 9 1.8 0 1.6-5.6 3.3-5.6s1.5 5.6 3.3 5.6c2.1 0 2.5-6.2 3.1-9 .6-2.6 1.9-4 2.2-6.6C21 5 19 2 16 2c-2.2 0-2.9 1.3-5 1.3S8.2 2 6 2z" fill="#fff" stroke={C.ink} strokeWidth="1.4" /></svg>
        <div style={{ position: "absolute", bottom: 10, fontFamily: FONT, fontWeight: 800, fontSize: 20, color: C.ink }}>Smile bright!</div>
      </div>
      {/* dental lamp */}
      <div style={{ position: "absolute", left: 120, top: 1080, width: 12, height: 200, background: "#9AA5A0", border: `3px solid ${C.ink}`, transform: "rotate(-12deg)", transformOrigin: "bottom" }} />
      <div style={{ position: "absolute", left: 70, top: 1060, width: 120, height: 60, borderRadius: "60px 60px 14px 14px", background: "#F2F4F3", border: `4px solid ${C.ink}`, boxShadow: `0 0 ${30 + 10 * Math.sin(frame / 10)}px #FFF6B3` }} />
      {/* chair back */}
      <div style={{ position: "absolute", left: 760, top: 1150, width: 200, height: 260, borderRadius: "90px 90px 20px 20px", background: "#7FC8E8", border: `4px solid ${C.ink}` }} />
      {/* clock */}
      <div style={{ position: "absolute", left: 900, top: 1150, width: 80, height: 80, borderRadius: "50%", background: "#fff", border: `4px solid ${C.ink}` }}>
        <div style={{ position: "absolute", left: 38, top: 14, width: 4, height: 26, background: C.ink, transformOrigin: "2px 26px", transform: `rotate(${frame * 2}deg)` }} />
        <div style={{ position: "absolute", left: 38, top: 24, width: 4, height: 16, background: C.ink, transformOrigin: "2px 16px", transform: `rotate(${frame / 6}deg)` }} />
      </div>
    </div>
  );
};

const Desk: React.FC = () => (
  <div style={{ position: "absolute", left: 0, right: 0, top: 1540, bottom: 0 }}>
    <div style={{ position: "absolute", left: 0, right: 0, top: 0, height: 26, background: "#8A6248", borderTop: `5px solid ${C.ink}`, borderBottom: `5px solid ${C.ink}` }} />
    <div style={{ position: "absolute", left: 0, right: 0, top: 26, bottom: 0, background: C.desk }} />
    {[
      [60, "Dr. Neha Mehra", "Dentist"],
      [700, "Arjun", "Solvix Web"],
    ].map(([x, n, r]) => (
      <div key={n as string} style={{ position: "absolute", left: x as number, top: 44, padding: "8px 18px", background: "#F7F3EA", border: `4px solid ${C.ink}`, borderRadius: 10, fontFamily: FONT, color: C.ink }}>
        <span style={{ fontWeight: 800, fontSize: 26 }}>{n}</span>
        <span style={{ fontSize: 20, fontWeight: 600, opacity: 0.6, marginLeft: 10 }}>{r}</span>
      </div>
    ))}
    <div style={{ position: "absolute", left: 0, right: 0, top: 150, textAlign: "center", fontFamily: FONT, fontWeight: 800, fontSize: 34, letterSpacing: 6, color: "#F7E7D6", opacity: 0.85 }}>
      <span style={{ color: C.lime }}>✚</span> MEHRA DENTAL CARE
    </div>
  </div>
);

// ---------- poses per line ----------
const N_CUES: PoseCue[] = [
  { at: 0, pose: "hip", mood: "calm" },
  { at: L[1].s, pose: "rest", mood: "doubt" },
  { at: L[2].s, pose: "phone", mood: "happy" },
  { at: L[3].s, pose: "rest", mood: "shock" },
  { at: L[4].s, pose: "shock", mood: "shock" },
  { at: L[5].s, pose: "chin", mood: "calm" },
  { at: L[6].s, pose: "hip", mood: "doubt" },
  { at: L[7].s, pose: "rest", mood: "happy" },
  { at: L[8].s, pose: "explain", mood: "happy" },
  { at: L[9].s, pose: "rest", mood: "happy" },
  { at: L[10].s, pose: "phone", mood: "happy" },
];
const R_CUES: PoseCue[] = [
  { at: 0, pose: "rest", mood: "calm" },
  { at: L[1].s, pose: "explain", mood: "happy" },
  { at: L[2].s, pose: "rest", mood: "calm" },
  { at: L[3].s, pose: "wave", mood: "shock" },
  { at: L[4].s, pose: "rest", mood: "calm" },
  { at: L[5].s, pose: "phone", mood: "happy" },
  { at: L[6].s, pose: "rest", mood: "calm" },
  { at: L[7].s, pose: "thumbs", mood: "happy" },
  { at: L[8].s, pose: "rest", mood: "happy" },
  { at: L[9].s, pose: "point", mood: "happy" },
  { at: L[10].s, pose: "thumbs", mood: "happy" },
];

// ---------- speech bubble (captions) ----------
const Bubble: React.FC = () => {
  const { frame, sp } = useA();
  const t = frame / 30;
  let li = -1;
  for (let i = 0; i < lines.length; i++) if (t >= lines[i].start - 0.05 && t <= lines[i].end + 0.25) li = i;
  if (li < 0) return null;
  const ws = W.filter((w) => w.line === li);
  let cur = -1;
  ws.forEach((w, i) => { if (w.start <= t) cur = i; });
  const per = 5;
  const c0 = Math.max(0, Math.floor(Math.max(cur, 0) / per) * per);
  const chunk = ws.slice(c0, c0 + per);
  const left = lines[li].who === "N";
  const s = sp(sec(lines[li].start) - 2, { damping: 14, stiffness: 220, mass: 0.6 });
  return (
    <div data-el="captions" data-kind="caption" style={{ position: "absolute", bottom: 1920 - 975, ...(left ? { left: 70 } : { right: 130 }), maxWidth: 640, transform: `scale(${0.7 + 0.3 * s})`, transformOrigin: left ? "20% 100%" : "80% 100%", opacity: Math.min(1, s * 1.5) }}>
      <div style={{ background: "#fff", border: `5px solid ${C.ink}`, borderRadius: 28, padding: "18px 26px", boxShadow: `6px 6px 0 ${C.ink}`, fontFamily: FONT, fontSize: 44, fontWeight: 800, lineHeight: 1.25, color: C.ink, display: "flex", flexWrap: "wrap", gap: "4px 12px" }}>
        {chunk.map((w, i) => {
          const idx = c0 + i;
          const said = idx <= cur;
          const now = idx === cur;
          return (
            <span key={idx} style={{ flexShrink: 0, whiteSpace: "nowrap", opacity: said ? 1 : 0.25, background: now ? (left ? "#FFD7D2" : C.lime) : "transparent", borderRadius: 8, padding: "0 6px", margin: "0 -6px" }}>
              {w.word}
            </span>
          );
        })}
      </div>
      <svg width="60" height="40" style={{ position: "absolute", top: "100%", marginTop: -5, overflow: "visible", ...(left ? { left: 26 } : { right: 26 }) }}>
        <path d={left ? "M0 0 L 20 34 L 46 0" : "M14 0 L 40 34 L 60 0"} fill="#fff" stroke={C.ink} strokeWidth="5" strokeLinejoin="round" />
        <rect x={left ? 2 : 16} y={-6} width="42" height="8" fill="#fff" />
      </svg>
    </div>
  );
};

export const Reel: React.FC<ReelProps> = ({ brand = { ...creamStory, handle: myBrand.handle }, debugSafeZones, probe }) => {
  const { frame } = useA();
  const t = frame / 30;
  const speaking = (who: string) => lines.some((l) => l.who === who && t >= l.start && t <= l.end);
  return (
    <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
      <Room />
      <Scene name="talk" from={0} to={durationInFrames + 30}>
        <div style={{ position: "absolute", left: -20, top: 1010 }}>
          <Toon who="neha" mouth={mouthN} cues={N_CUES} lookRight speaking={speaking("N")} scale={1.25} />
        </div>
        <div style={{ position: "absolute", left: 575, top: 1010 }}>
          <Toon who="arjun" mouth={mouthR} cues={R_CUES} lookRight={false} speaking={speaking("R")} scale={1.25} />
        </div>
      </Scene>
      <Stage L={L} wAt={wAt} handle={myBrand.handle} />
      <Desk />
      <Bubble />
      <StoryOutro at={durationInFrames - 34} handle={myBrand.handle} />
    </ReelFrame>
  );
};
