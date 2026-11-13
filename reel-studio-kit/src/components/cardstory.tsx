// "Card story" look: peach background, red eyebrow + two-line title (black / red), a rounded card with
// real B-roll footage and UI panels on top, word-box captions, handle + slide counter, and an outro.
// B-roll lives in public/broll/ (`npm run broll`).
import React from "react";
import { Easing, interpolate, OffthreadVideo, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";

export const STORY = {
  bg1: "#F9E9E3",
  bg2: "#F1D6CB",
  ink: "#151515",
  red: "#DD4B3E",
  green: "#2F9E5B",
  card: { left: 120, top: 600, width: 840, height: 760, radius: 44 },
};
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";

const useAnim = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    fps,
    sp: (at: number, config = { damping: 15, stiffness: 160, mass: 0.7 }) => spring({ frame: frame - at, fps, config }),
    fade: (at: number, len = 6) => interpolate(frame - at, [0, len], [0, 1], CL),
  };
};

export const StoryBackground: React.FC = () => {
  const { frame } = useAnim();
  return (
    <div style={{ position: "absolute", inset: 0, background: `linear-gradient(180deg, ${STORY.bg1} 0%, ${STORY.bg2} 100%)` }}>
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(60% 35% at ${50 + Math.sin(frame / 90) * 8}% 30%, rgba(255,255,255,.55), transparent 70%)` }} />
    </div>
  );
};

/** Eyebrow + two-line title. Lines rise out of a mask; a short red dash grows under them. */
export const StoryHeader: React.FC<{ at: number; eyebrow: string; line1: string; line2: string }> = ({ at, eyebrow, line1, line2 }) => {
  const { sp, fade, frame } = useAnim();
  const l1 = sp(at + 2);
  const l2 = sp(at + 6);
  const dash = interpolate(frame - at - 8, [0, 10], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
  const line = (txt: string, p: number, color: string) => (
    <div style={{ clipPath: "inset(0 -40px 0 -40px)", paddingBottom: 6 }}>
      <div style={{ transform: `translateY(${(1 - p) * 100}%)`, color, fontSize: 70, fontWeight: 800, lineHeight: 1.08, letterSpacing: -2 }}>{txt}</div>
    </div>
  );
  return (
    <div data-el="title" data-kind="content" style={{ position: "absolute", left: 120, right: 120, top: 300, fontFamily: FONT }}>
      <div style={{ fontSize: 26, fontWeight: 800, letterSpacing: 3, color: STORY.red, opacity: fade(at), marginBottom: 14 }}>{eyebrow.toUpperCase()}</div>
      {line(line1, l1, STORY.ink)}
      {line(line2, l2, STORY.red)}
      <div style={{ width: 90 * dash, height: 7, borderRadius: 4, background: STORY.red, marginTop: 14 }} />
    </div>
  );
};

/** Rounded card playing a B-roll clip from `from` (slow push-in), with overlay children on top. */
export const ClipCard: React.FC<{ src: string; from: number; to: number; startSec?: number; children?: React.ReactNode }> = ({ src, from, to, startSec = 1, children }) => {
  const { frame, fps, sp, fade } = useAnim();
  if (frame < from || frame >= to) return null;
  const s = sp(from, { damping: 18, stiffness: 140, mass: 0.8 });
  const zoom = interpolate(frame, [from, to], [1.04, 1.14], CL);
  const out = interpolate(frame, [to - 6, to], [0, 1], CL);
  const c = STORY.card;
  return (
    <div
      style={{
        position: "absolute",
        left: c.left,
        top: c.top,
        width: c.width,
        height: c.height,
        borderRadius: c.radius,
        overflow: "hidden",
        boxShadow: "0 30px 70px rgba(80,30,20,.22)",
        opacity: fade(from, 5) * (1 - out),
        transform: `scale(${0.94 + 0.06 * s})`,
        background: "#222",
      }}
    >
      <Sequence from={from} durationInFrames={to - from} layout="none">
        <OffthreadVideo src={staticFile(src)} muted startFrom={Math.round(startSec * fps)} style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", transform: `scale(${zoom})` }} />
      </Sequence>
      <div style={{ position: "absolute", inset: 0, background: "linear-gradient(180deg, rgba(0,0,0,0) 45%, rgba(0,0,0,.28) 100%)" }} />
      {children}
    </div>
  );
};

type Row = { title: string; sub?: string; mark?: "num" | "ok" | "no"; at: number };

/** White panel at the bottom of the card with rows that pop in; `activeAt` lists when each row gets highlighted. */
export const RowsPanel: React.FC<{ rows: Row[]; at: number; swapAt?: number; header?: string }> = ({ rows, at, swapAt, header }) => {
  const { frame, sp, fade } = useAnim();
  const s = sp(at);
  const rowH = 96;
  const swap = swapAt != null ? interpolate(frame - swapAt, [0, 14], [0, 1], { ...CL, easing: Easing.inOut(Easing.cubic) }) : 0;
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{
        position: "absolute",
        left: 36,
        right: 36,
        bottom: 36,
        background: "rgba(255,255,255,.97)",
        borderRadius: 26,
        padding: "18px 22px",
        boxShadow: "0 18px 40px rgba(0,0,0,.25)",
        fontFamily: FONT,
        color: STORY.ink,
        opacity: fade(at),
        transform: `translateY(${(1 - s) * 60}px)`,
      }}
    >
      {header ? <div style={{ fontSize: 24, fontWeight: 600, opacity: 0.5, padding: "2px 4px 10px" }}>{header}</div> : null}
      <div style={{ position: "relative", height: rows.length * rowH }}>
        {rows.map((r, i) => {
          // with swapAt, rows 0 and 1 trade places
          const pos = swapAt != null && i < 2 ? (i === 0 ? swap : 1 - swap) : i;
          const p = sp(r.at);
          const shown = frame >= r.at;
          const colour = r.mark === "no" ? STORY.red : r.mark === "ok" ? STORY.green : STORY.ink;
          return (
            <div
              key={i}
              style={{
                position: "absolute",
                left: 0,
                right: 0,
                top: pos * rowH,
                height: rowH - 12,
                display: "flex",
                alignItems: "center",
                gap: 18,
                padding: "0 18px",
                borderRadius: 16,
                border: `3px solid ${shown ? `${colour}55` : "transparent"}`,
                background: shown ? `${colour}0D` : "transparent",
                opacity: 0.35 + 0.65 * fade(r.at, 5),
                transform: `scale(${0.96 + 0.04 * p})`,
              }}
            >
              <span style={{ width: 46, height: 46, borderRadius: "50%", flex: "none", display: "grid", placeItems: "center", background: shown ? colour : "#D9D2CE", color: "#fff", fontSize: 24, fontWeight: 800 }}>
                {r.mark === "ok" ? "✓" : r.mark === "no" ? "✕" : String(Math.round(pos) + 1)}
              </span>
              <span style={{ display: "flex", flexDirection: "column" }}>
                <span style={{ fontSize: 34, fontWeight: 800, lineHeight: 1.1 }}>{r.title}</span>
                {r.sub ? <span style={{ fontSize: 22, fontWeight: 600, opacity: 0.55 }}>{r.sub}</span> : null}
              </span>
              {swapAt != null && i < 2 ? (
                <span style={{ marginLeft: "auto", fontSize: 30, fontWeight: 800, color: (i === 0) === swap > 0.5 ? STORY.red : STORY.green }}>{(i === 0) === swap > 0.5 ? "▼" : "▲"}</span>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
};

/** Big number badge in the card corner (e.g. 84%). */
export const CardStat: React.FC<{ at: number; value: number; suffix?: string; label: string; countFrames?: number }> = ({ at, value, suffix = "", label, countFrames = 20 }) => {
  const { frame, sp, fade } = useAnim();
  const v = interpolate(frame - at, [0, countFrames], [0, value], { ...CL, easing: Easing.out(Easing.cubic) });
  const s = sp(at, { damping: 10, stiffness: 200, mass: 0.6 });
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{ position: "absolute", left: 36, bottom: 36, background: "#fff", borderRadius: 26, padding: "20px 30px", fontFamily: FONT, opacity: fade(at), transform: `scale(${0.7 + 0.3 * s})`, transformOrigin: "left bottom", boxShadow: "0 18px 40px rgba(0,0,0,.25)" }}
    >
      <div style={{ fontSize: 120, fontWeight: 800, color: STORY.red, lineHeight: 1, letterSpacing: -3 }}>
        {Math.round(v)}
        {suffix}
      </div>
      <div style={{ fontSize: 28, fontWeight: 700, color: STORY.ink, marginTop: 6 }}>{label}</div>
    </div>
  );
};

/** A search box that types on the card. */
export const CardSearch: React.FC<{ at: number; query: string; typeTo: number }> = ({ at, query, typeTo }) => {
  const { frame, sp, fade } = useAnim();
  const n = typeTo <= at + 3 ? query.length : Math.round(interpolate(frame, [at + 3, typeTo], [0, query.length], CL));
  const s = sp(at);
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{ position: "absolute", left: 36, right: 36, top: 36, background: "#fff", borderRadius: 999, padding: "22px 30px", display: "flex", alignItems: "center", gap: 18, fontFamily: FONT, fontSize: 38, fontWeight: 700, color: STORY.ink, opacity: fade(at), transform: `translateY(${(1 - s) * -40}px)`, boxShadow: "0 14px 34px rgba(0,0,0,.25)" }}
    >
      <svg width="38" height="38" viewBox="0 0 24 24"><circle cx="10" cy="10" r="7" fill="none" stroke={STORY.red} strokeWidth="3" /><path d="M15.5 15.5 21 21" stroke={STORY.red} strokeWidth="3" strokeLinecap="round" /></svg>
      <span>
        {n === 0 ? <span style={{ opacity: 0.45 }}>Search…</span> : query.slice(0, n)}
        <span style={{ color: STORY.red, opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span>
      </span>
    </div>
  );
};

/** Captions: each word in its own black box; words not yet spoken are faded. */
export const WordBoxes: React.FC<{ words: { word: string; start: number; end: number }[]; perChunk?: number; top?: number }> = ({ words, perChunk = 3, top = 1420 }) => {
  const { frame, fps } = useAnim();
  const t = frame / fps;
  let cur = -1;
  for (let i = 0; i < words.length; i++) {
    if (words[i].start <= t) cur = i;
    else break;
  }
  if (cur < 0) return null;
  const c0 = Math.floor(cur / perChunk) * perChunk;
  const chunk = words.slice(c0, c0 + perChunk);
  if (t > chunk[chunk.length - 1].end + 0.6 && c0 + perChunk >= words.length) return null;
  return (
    <div data-el="captions" data-kind="caption" style={{ position: "absolute", left: 120, right: 120, top, display: "flex", justifyContent: "center", flexWrap: "wrap", gap: 10 }}>
      {chunk.map((w, i) => {
        const idx = c0 + i;
        const said = idx <= cur;
        const pop = interpolate(t - w.start, [0, 0.12], [0.85, 1], CL);
        return (
          <span key={idx} style={{ background: STORY.ink, color: "#fff", fontFamily: FONT, fontWeight: 800, fontSize: 46, padding: "6px 14px", borderRadius: 8, opacity: said ? 1 : 0.3, transform: `scale(${said ? pop : 0.92})` }}>
            {w.word.replace(/[—,.?]+$/u, "")}
          </span>
        );
      })}
    </div>
  );
};

/** Handle on the left, "03 / 07" on the right. */
export const StoryFooter: React.FC<{ handle: string; index: number; total: number }> = ({ handle, index, total }) => (
  <div style={{ position: "absolute", left: 120, right: 120, top: 1560, display: "flex", justifyContent: "space-between", fontFamily: FONT, fontSize: 26, fontWeight: 700, color: `${STORY.ink}99` }}>
    <span>
      <span style={{ color: STORY.red }}>●</span> {handle}
    </span>
    <span>
      {String(index).padStart(2, "0")} / {String(total).padStart(2, "0")}
    </span>
  </div>
);

/** Red call-to-action strip inside the card, plus a contact row. */
export const CardCTA: React.FC<{ at: number; text: string; contact: string }> = ({ at, text, contact }) => {
  const { sp, fade, frame } = useAnim();
  const s = sp(at, { damping: 10, stiffness: 200, mass: 0.6 });
  const pulse = 1 + 0.03 * Math.sin(frame / 4);
  return (
    <div data-el="card" data-kind="content" style={{ position: "absolute", left: 36, right: 36, bottom: 36, fontFamily: FONT, opacity: fade(at), transform: `translateY(${(1 - s) * 50}px)` }}>
      <div style={{ background: STORY.red, color: "#fff", fontSize: 40, fontWeight: 800, textAlign: "center", padding: "22px 20px", borderRadius: 20, boxShadow: `0 0 0 ${(pulse - 1) * 300}px ${STORY.red}33` }}>{text}</div>
      <div style={{ marginTop: 14, background: "#fff", borderRadius: 18, padding: "16px 24px", fontSize: 28, fontWeight: 700, color: STORY.ink }}>
        <span style={{ fontSize: 20, letterSpacing: 2, opacity: 0.5, marginRight: 14 }}>CONTACT</span>
        {contact}
      </div>
    </div>
  );
};

/** Last second: darken everything and show the handle. */
export const StoryOutro: React.FC<{ at: number; handle: string }> = ({ at, handle }) => {
  const { fade, sp } = useAnim();
  const o = fade(at, 8);
  if (o <= 0) return null;
  const s = sp(at + 4);
  return (
    <div style={{ position: "absolute", inset: 0, background: `rgba(20,16,15,${0.72 * o})`, display: "grid", placeItems: "center" }}>
      <div style={{ textAlign: "center", fontFamily: FONT, transform: `scale(${0.8 + 0.2 * s})`, opacity: o }}>
        <svg width="150" height="150" viewBox="0 0 24 24">
          <defs>
            <linearGradient id="ig" x1="0" y1="1" x2="1" y2="0">
              <stop offset="0" stopColor="#FEDA75" />
              <stop offset=".35" stopColor="#FA7E1E" />
              <stop offset=".65" stopColor="#D62976" />
              <stop offset="1" stopColor="#4F5BD5" />
            </linearGradient>
          </defs>
          <rect x="2" y="2" width="20" height="20" rx="6" fill="none" stroke="url(#ig)" strokeWidth="2" />
          <circle cx="12" cy="12" r="4.6" fill="none" stroke="url(#ig)" strokeWidth="2" />
          <circle cx="17.4" cy="6.6" r="1.3" fill="url(#ig)" />
        </svg>
        <div style={{ color: "#fff", fontSize: 46, fontWeight: 800, letterSpacing: 2, marginTop: 18 }}>{handle.toUpperCase()}</div>
        <div style={{ color: "#ffffffaa", fontSize: 30, fontWeight: 600, marginTop: 8 }}>Follow for more</div>
      </div>
    </div>
  );
};
