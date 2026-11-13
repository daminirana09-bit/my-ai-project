// "Late-night desk" aesthetic for text-only reels: dark room lit by a laptop, purple-blue glow on the
// wall, posters, a backlit keyboard, floating dust, film grain and a slow handheld push-in.
// Everything is drawn in code (no stock footage). Pair it with <TextLines> and add music in Instagram.
import React from "react";
import { interpolate, random, useCurrentFrame, useVideoConfig } from "remotion";

const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;

const Poster: React.FC<{ x: number; y: number; w: number; h: number; word?: string; size?: number; figure?: boolean }> = ({ x, y, w, h, word, size = 80, figure }) => (
  <div
    style={{
      position: "absolute",
      left: x,
      top: y,
      width: w,
      height: h,
      background: "linear-gradient(160deg, #13112a 0%, #07060f 70%)",
      boxShadow: "0 30px 60px rgba(0,0,0,.55), inset 0 0 0 2px rgba(120,110,255,.06)",
      overflow: "hidden",
    }}
  >
    {figure ? (
      <div style={{ position: "absolute", left: "50%", bottom: 0, width: w * 0.6, height: h * 0.75, marginLeft: -w * 0.3, background: "radial-gradient(circle at 50% 22%, #1d1a3a 0 16%, transparent 17%), radial-gradient(ellipse at 50% 100%, #1a1735 0 55%, transparent 56%)" }} />
    ) : null}
    {word ? (
      <div style={{ position: "absolute", left: 0, right: 0, bottom: h * 0.16, textAlign: "center", fontFamily: "Instrument Serif, Georgia, serif", fontSize: size, letterSpacing: 4, color: "rgba(150,140,255,.16)" }}>{word}</div>
    ) : null}
  </div>
);

/** What is on the laptop screen: a marketing dashboard + a node graph. */
const Screen: React.FC<{ frame: number }> = ({ frame }) => {
  const t = frame / 30;
  const pts = Array.from({ length: 9 }, (_, i) => 70 - i * 6 - Math.sin(i * 1.7 + t * 0.8) * 4);
  const line = pts.map((y, i) => `${i === 0 ? "M" : "L"}${i * 32} ${y}`).join(" ");
  const nodes = Array.from({ length: 16 }, (_, i) => {
    const a = (i / 16) * Math.PI * 2 + t * 0.15;
    const r = 34 + (i % 4) * 14;
    return { x: 80 + Math.cos(a) * r, y: 120 + Math.sin(a) * r * 1.25 };
  });
  return (
    <div style={{ position: "absolute", inset: 0, background: "#0d0f17", display: "flex", gap: 8, padding: 10, fontFamily: "Inter, sans-serif" }}>
      <div style={{ flex: 1.55, display: "flex", flexDirection: "column", gap: 8 }}>
        <div style={{ height: 14, display: "flex", gap: 6 }}>
          <span style={{ width: 40, background: "#e5484d", borderRadius: 3 }} />
          <span style={{ flex: 1, background: "#1b1e2a", borderRadius: 3 }} />
        </div>
        <div style={{ flex: 1, borderRadius: 6, background: "linear-gradient(135deg,#4b3a2c,#2a211b)", position: "relative", overflow: "hidden" }}>
          <svg width="100%" height="100%" viewBox="0 0 256 90" preserveAspectRatio="none" style={{ position: "absolute", inset: 0 }}>
            <path d={`${line} L256 90 L0 90 Z`} fill="rgba(110,231,183,.18)" />
            <path d={line} fill="none" stroke="#6ee7b7" strokeWidth="2.4" />
          </svg>
          <div style={{ position: "absolute", left: 8, top: 6, color: "#fff", fontSize: 11, fontWeight: 800, opacity: 0.85 }}>Reach +{Math.round(180 + t * 9)}%</div>
        </div>
        <div style={{ height: 34, display: "flex", gap: 6 }}>
          {[0.7, 0.45, 0.9, 0.6].map((h, i) => (
            <span key={i} style={{ flex: 1, background: "#1b1e2a", borderRadius: 4, position: "relative" }}>
              <span style={{ position: "absolute", left: 4, right: 4, bottom: 4, height: `${(h + 0.15 * Math.sin(t + i)) * 70}%`, background: "#7c6cff", borderRadius: 2 }} />
            </span>
          ))}
        </div>
      </div>
      <div style={{ flex: 0.55, display: "flex", flexDirection: "column", gap: 6 }}>
        {[0, 1, 2, 3, 4].map((i) => (
          <div key={i} style={{ flex: 1, display: "flex", gap: 5 }}>
            <span style={{ width: 34, borderRadius: 3, background: ["#c9a227", "#3b5bdb", "#e8590c", "#868e96", "#2f9e44"][i], opacity: 0.8 }} />
            <span style={{ flex: 1, display: "flex", flexDirection: "column", gap: 3, justifyContent: "center" }}>
              <span style={{ height: 4, background: "#2b2f3d", borderRadius: 2 }} />
              <span style={{ height: 4, width: "60%", background: "#2b2f3d", borderRadius: 2 }} />
            </span>
          </div>
        ))}
      </div>
      <div style={{ flex: 0.95, borderRadius: 6, background: "#0a0b12", border: "1px solid #1d2030" }}>
        <svg width="100%" height="100%" viewBox="0 0 160 240" preserveAspectRatio="none">
          {nodes.map((n, i) => (
            <line key={`l${i}`} x1="80" y1="120" x2={n.x} y2={n.y} stroke="rgba(160,170,255,.35)" strokeWidth="0.8" />
          ))}
          {nodes.map((n, i) => (
            <circle key={i} cx={n.x} cy={n.y} r={i % 3 ? 1.8 : 2.6} fill="#c7cbe8" />
          ))}
          <circle cx="80" cy="120" r={5 + Math.sin(t * 3) * 1} fill="#8b7cff" />
        </svg>
      </div>
    </div>
  );
};

const Keyboard: React.FC<{ frame: number }> = ({ frame }) => {
  const rows = [14, 14, 13, 12, 9];
  const glow = 0.85 + 0.08 * Math.sin(frame / 11);
  return (
    <div style={{ position: "absolute", left: 175, top: 1130, width: 560, height: 190, transform: "perspective(700px) rotateX(58deg) skewX(-4deg)", transformOrigin: "50% 0%" }}>
      {rows.map((n, r) => (
        <div key={r} style={{ display: "flex", gap: 7, marginBottom: 8, paddingLeft: r * 8 }}>
          {Array.from({ length: n }, (_, k) => {
            const wide = r === 4 && k === 4;
            return (
              <span
                key={k}
                style={{
                  width: wide ? 150 : 32,
                  height: 28,
                  borderRadius: 5,
                  border: `2px solid rgba(225,228,255,${glow})`,
                  boxShadow: `0 0 8px rgba(200,205,255,${glow * 0.7}), inset 0 0 6px rgba(200,205,255,.25)`,
                  background: "rgba(8,8,16,.9)",
                }}
              />
            );
          })}
        </div>
      ))}
    </div>
  );
};

/** The whole room. Put text on top of it. */
export const DeskScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const p = frame / durationInFrames;
  const push = 1 + 0.06 * p;
  const swayX = Math.sin(frame / 41) * 6 + Math.sin(frame / 17) * 1.5;
  const swayY = Math.cos(frame / 53) * 5;
  const flick = 0.92 + 0.05 * Math.sin(frame / 7) + 0.03 * random(`f${Math.floor(frame / 3)}`);
  const dust = Array.from({ length: 46 }, (_, i) => {
    const x0 = random(`dx${i}`) * 1080;
    const y0 = random(`dy${i}`) * 1500;
    const sp = 0.15 + random(`ds${i}`) * 0.35;
    return { x: x0 + Math.sin(frame / 50 + i) * 20, y: ((y0 - frame * sp) % 1500 + 1500) % 1500 + 150, r: 1 + random(`dr${i}`) * 2.4, o: 0.15 + random(`do${i}`) * 0.35 };
  });
  return (
    <div style={{ position: "absolute", inset: 0, background: "#030208", overflow: "hidden" }}>
      <div style={{ position: "absolute", inset: 0, transform: `translate(${swayX}px, ${swayY}px) scale(${push})`, transformOrigin: "50% 55%" }}>
        {/* wall + glow from the screen */}
        <div style={{ position: "absolute", inset: 0, background: "linear-gradient(180deg, #120c3c 0%, #0c0828 45%, #04030c 72%, #020106 100%)" }} />
        <div style={{ position: "absolute", inset: 0, opacity: flick, background: "radial-gradient(ellipse 62% 38% at 48% 52%, rgba(86,66,255,.62), rgba(52,36,190,.25) 45%, transparent 75%)" }} />
        {/* posters */}
        <Poster x={268} y={160} w={210} h={160} figure />
        <Poster x={505} y={170} w={215} h={330} word="BUILD" size={46} />
        <Poster x={250} y={340} w={250} h={480} word="FOCUS" size={72} />
        <Poster x={505} y={515} w={215} h={250} word="FOCUS." size={60} />
        <Poster x={50} y={540} w={195} h={300} word="STAY" size={58} />
        {/* desk */}
        <div style={{ position: "absolute", left: -100, right: -100, top: 1320, bottom: -100, background: "linear-gradient(180deg, #0a0816 0%, #030207 60%)" }} />
        <div style={{ position: "absolute", left: 100, top: 1290, width: 760, height: 260, opacity: flick, background: "radial-gradient(ellipse at 50% 30%, rgba(120,110,255,.22), transparent 70%)" }} />
        {/* laptop */}
        <div style={{ position: "absolute", left: 210, top: 840, width: 520, height: 290, background: "#050507", borderRadius: 10, padding: 9, boxShadow: `0 0 ${140 * flick}px rgba(110,100,255,.45)`, transform: "perspective(1400px) rotateY(-5deg)" }}>
          <div style={{ position: "relative", width: "100%", height: "100%", borderRadius: 4, overflow: "hidden", filter: `brightness(${0.95 + 0.05 * flick})` }}>
            <Screen frame={frame} />
          </div>
        </div>
        <div style={{ position: "absolute", left: 190, top: 1124, width: 570, height: 16, background: "linear-gradient(#1a1a22,#060608)", borderRadius: "0 0 14px 14px" }} />
        <Keyboard frame={frame} />
        {/* dust in the light */}
        {dust.map((d, i) => (
          <span key={i} style={{ position: "absolute", left: d.x, top: d.y, width: d.r, height: d.r, borderRadius: "50%", background: "#cfcaff", opacity: d.o * flick }} />
        ))}
      </div>
      {/* vignette + grain (not part of the camera move) */}
      <div style={{ position: "absolute", inset: 0, background: "radial-gradient(ellipse at 50% 48%, transparent 45%, rgba(0,0,0,.75) 100%)" }} />
      <div
        style={{
          position: "absolute",
          inset: 0,
          opacity: 0.09,
          backgroundImage: "radial-gradient(#fff 0.8px, transparent 1px)",
          backgroundSize: "5px 5px",
          backgroundPosition: `${Math.floor(random(`gx${frame}`) * 5)}px ${Math.floor(random(`gy${frame}`) * 5)}px`,
          mixBlendMode: "overlay",
        }}
      />
    </div>
  );
};

/** Centered lines that fade + un-blur in one after another (Instagram text style). */
export const TextLines: React.FC<{ lines: string[]; startSec?: number; everySec?: number; size?: number; top?: number; bold?: string[] }> = ({
  lines,
  startSec = 0.5,
  everySec = 0.55,
  size = 52,
  top = 450,
  bold = [],
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return (
    <div
      data-el="title"
      data-kind="content"
      style={{ position: "absolute", left: 90, right: 150, top, textAlign: "center", fontFamily: "Inter, sans-serif", color: "#F2F1FF", transform: "rotate(-1.2deg)" }}
    >
      {lines.map((l, i) => {
        const a = (startSec + i * everySec) * fps;
        const p = interpolate(frame - a, [0, 12], [0, 1], CL);
        return (
          <div
            key={i}
            style={{
              fontSize: size,
              fontWeight: bold.includes(l) ? 800 : 600,
              fontStyle: i === 0 ? "italic" : "normal",
              lineHeight: 1.2,
              opacity: p,
              filter: `blur(${(1 - p) * 8}px)`,
              transform: `translateY(${(1 - p) * 14}px)`,
              textShadow: "0 2px 14px rgba(0,0,0,.7)",
            }}
          >
            {l}
          </div>
        );
      })}
    </div>
  );
};
