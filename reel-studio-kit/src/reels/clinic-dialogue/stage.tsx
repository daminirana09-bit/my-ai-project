// Top-half animations for clinic-dialogue (EP 02): one beat per spoken line.
import React from "react";
import { Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { Burst } from "../../components";

const C = { ink: "#1E1E24", lime: "#C8F06A", red: "#E5484D", green: "#22A06B", wa: "#25D366", waDark: "#128C7E", paper: "#FFFDF8" };
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";

type Line = { s: number; e: number };

const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    sp: (at: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - at, fps, config: cfg }),
    fade: (at: number, len = 6) => interpolate(frame - at, [0, len], [0, 1], CL),
  };
};

const card: React.CSSProperties = { background: C.paper, border: `5px solid ${C.ink}`, borderRadius: 28, boxShadow: `8px 8px 0 ${C.ink}`, fontFamily: FONT, color: C.ink };

/** Shows children only during [from, to) with a pop in and a quick shrink out. */
const Beat: React.FC<{ from: number; to: number; children: React.ReactNode; top?: number }> = ({ from, to, children, top = 330 }) => {
  const { frame, sp } = useA();
  if (frame < from || frame >= to) return null;
  const s = sp(from);
  const out = interpolate(frame, [to - 6, to], [0, 1], { ...CL, easing: Easing.in(Easing.quad) });
  return (
    <div data-scene-beat style={{ position: "absolute", left: 70, right: 130, top, display: "flex", flexDirection: "column", alignItems: "center", transform: `translateY(${(1 - s) * 50}px) scale(${(0.9 + 0.1 * s) * (1 - 0.15 * out)})`, opacity: Math.min(1, s * 1.6) * (1 - out) }}>
      {children}
    </div>
  );
};

const Sticker: React.FC<{ at: number; text: string; rot?: number; color?: string }> = ({ at, text, rot = -6, color = C.lime }) => {
  const { sp, fade } = useA();
  const s = sp(at, { damping: 9, stiffness: 220, mass: 0.6 });
  return (
    <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ marginTop: 26, opacity: fade(at, 3), transform: `rotate(${rot * s}deg) scale(${Math.min(1, 0.4 + 0.6 * s)})`, background: color, border: `4px solid ${C.ink}`, borderRadius: 14, padding: "10px 22px", fontFamily: FONT, fontWeight: 800, fontSize: 36, color: C.ink, boxShadow: `5px 5px 0 ${C.ink}` }}>
      {text}
    </div>
  );
};

const Mark: React.FC<{ ok: boolean; size?: number }> = ({ ok, size = 48 }) => (
  <span style={{ width: size, height: size, borderRadius: "50%", flex: "none", background: ok ? C.green : C.red, color: "#fff", display: "grid", placeItems: "center", fontSize: size * 0.55, fontWeight: 800, border: `3px solid ${C.ink}` }}>{ok ? "✓" : "✕"}</span>
);

const typed = (frame: number, text: string, from: number, to: number) => text.slice(0, Math.round(interpolate(frame, [from, Math.max(from + 1, to)], [0, text.length], CL)));

export const Stage: React.FC<{ L: Line[]; wAt: (li: number, n: number) => number; handle: string }> = ({ L, wAt, handle }) => {
  const { frame, sp, fade } = useA();
  const from = (i: number) => Math.max(0, L[i].s - 4);
  const end = (i: number) => (i + 1 < L.length ? from(i + 1) : L[i].e + 40); // next beat starts as this one ends
  const tStart = L[10].s + 4;
  const tEnd = L[10].s + Math.round((L[10].e - L[10].s) * 0.62);
  const posted = tEnd + 8;
  const row = (at: number, ok: boolean, title: string, sub: string) => (
    <div style={{ display: "flex", alignItems: "center", gap: 18, marginTop: 14, opacity: fade(at, 4), transform: `translateX(${(1 - sp(at)) * -40}px)` }}>
      <Mark ok={ok} />
      <div>
        <div style={{ fontSize: 32, fontWeight: 800 }}>{title}</div>
        {sub ? <div style={{ fontSize: 22, fontWeight: 600, opacity: 0.6 }}>{sub}</div> : null}
      </div>
    </div>
  );

  return (
    <>
      <div style={{ position: "absolute", left: 0, right: 0, top: 236, display: "flex", justifyContent: "center", opacity: fade(0, 10) }}>
        <div style={{ background: C.lime, border: `4px solid ${C.ink}`, borderRadius: 12, padding: "6px 18px", fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, color: C.ink, boxShadow: `4px 4px 0 ${C.ink}` }}>SOLVIX STORIES · EP 02</div>
      </div>

      {/* 0: word of mouth */}
      <Beat from={from(0)} to={end(0)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 640, padding: 24 }}>
          <div style={{ fontSize: 26, fontWeight: 800, opacity: 0.55, marginBottom: 6 }}>Society group · 3 new</div>
          {["Neha ma'am bahut achhi dentist hain", "Haan, main bhi wahin jaati hoon", "Number bhejna please"].map((m, i) => {
            const at = L[0].s + 6 + i * 16;
            return (
              <div key={i} style={{ marginTop: 10, display: "flex", justifyContent: i % 2 ? "flex-end" : "flex-start", opacity: fade(at, 4), transform: `translateY(${(1 - sp(at)) * 16}px)` }}>
                <span style={{ background: i % 2 ? "#DCF8C6" : "#F1EFEA", border: `3px solid ${C.ink}`, borderRadius: 16, padding: "10px 16px", fontSize: 26, fontWeight: 700 }}>{m}</span>
              </div>
            );
          })}
        </div>
      </Beat>

      {/* 1: googled, not found */}
      <Beat from={from(1)} to={end(1)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 26 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 16, border: `4px solid ${C.ink}`, borderRadius: 999, padding: "14px 24px", fontSize: 36, fontWeight: 700 }}>
            <svg width="34" height="34" viewBox="0 0 24 24"><circle cx="10" cy="10" r="7" fill="none" stroke={C.ink} strokeWidth="3" /><path d="M15.5 15.5 21 21" stroke={C.ink} strokeWidth="3" strokeLinecap="round" /></svg>
            {typed(frame, "dr neha mehra dentist", L[1].s + 4, wAt(1, 7))}
            <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span>
          </div>
          {row(wAt(1, 8), false, "Koi website nahi mili", "na timing, na address")}
        </div>
        <Sticker at={wAt(1, 9)} text="“Google pe toh mili hi nahi!”" color="#FFD7D2" rot={4} />
      </Beat>

      {/* 2: before-after post idea */}
      <Beat from={from(2)} to={end(2)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 560, padding: 20 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 12, fontSize: 26, fontWeight: 800, marginBottom: 12 }}>
            <span style={{ width: 44, height: 44, borderRadius: "50%", background: "#E98AA8", border: `3px solid ${C.ink}` }} />
            drnehamehra <span style={{ marginLeft: "auto", fontSize: 30 }}>•••</span>
          </div>
          <div style={{ display: "flex", gap: 8 }}>
            {["BEFORE", "AFTER"].map((t, i) => (
              <div key={t} style={{ flex: 1, height: 200, borderRadius: 14, border: `3px solid ${C.ink}`, background: i ? "#F4FBFF" : "#E8E0C8", display: "grid", placeItems: "center", position: "relative" }}>
                <svg width="90" height="98" viewBox="0 0 24 26"><path d="M6 2c-3 0-5 3-4.6 6.5.3 2.6 1.6 4 2.2 6.6.6 2.8 1 9 3.1 9 1.8 0 1.6-5.6 3.3-5.6s1.5 5.6 3.3 5.6c2.1 0 2.5-6.2 3.1-9 .6-2.6 1.9-4 2.2-6.6C21 5 19 2 16 2c-2.2 0-2.9 1.3-5 1.3S8.2 2 6 2z" fill={i ? "#fff" : "#E2CF8E"} stroke={C.ink} strokeWidth="1.4" /></svg>
                <span style={{ position: "absolute", top: 8, left: 8, background: C.ink, color: "#fff", fontSize: 18, fontWeight: 800, padding: "2px 8px", borderRadius: 6 }}>{t}</span>
              </div>
            ))}
          </div>
          <div style={{ marginTop: 10, fontSize: 24, fontWeight: 700 }}>♡ 1,204 likes</div>
        </div>
      </Beat>

      {/* 3: NDC advisory */}
      <Beat from={from(3)} to={end(3)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 26 }}>
          <div style={{ fontSize: 26, fontWeight: 800, opacity: 0.55 }}>National Dental Commission · advisory, Oct 2026</div>
          {row(wAt(3, 6), false, "Patient testimonials", "")}
          {row(wAt(3, 8), false, "Before / after photos", "")}
          {row(wAt(3, 10), false, "Jhoothe ya bade daave", "")}
        </div>
        <Sticker at={L[3].s + 6} text="Post mat karo!" color="#FFD7D2" rot={-5} />
      </Beat>

      {/* 4: how will patients find me */}
      <Beat from={from(4)} to={end(4)} top={360}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 112, color: C.ink, letterSpacing: -3, paddingRight: 30, textAlign: "center", lineHeight: 1.05 }}>
          Ab patient<br />
          <span style={{ color: C.red }}>kaise aayenge</span>
          <span style={{ color: C.red, display: "inline-block", transform: `rotate(${Math.sin(frame / 4) * 12}deg)` }}>?</span>
        </div>
      </Beat>

      {/* 5: the clinic site */}
      <Beat from={from(5)} to={end(5)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 24 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontFamily: MONO, fontSize: 24, fontWeight: 600 }}>
            <span>drnehamehra.in</span>
            <span style={{ background: C.lime, border: `3px solid ${C.ink}`, borderRadius: 8, padding: "2px 10px", opacity: fade(wAt(5, 12), 4) }}>rules ke andar ✓</span>
          </div>
          {row(wAt(5, 2), true, "Timing", "Mon–Sat · 10 AM – 8 PM")}
          {row(wAt(5, 4), true, "Treatments ki jaankari", "Cleaning · Root canal · Braces")}
          {row(wAt(5, 7), true, "Online appointment", "Book karo, 2 click mein")}
          {row(wAt(5, 9), true, "Google profile + map", "Mehra Dental Care, Sector 21")}
        </div>
      </Beat>

      {/* 6: cost? */}
      <Beat from={from(6)} to={end(6)} top={380}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 120, color: C.ink, letterSpacing: -3 }}>
          ₹ <span style={{ color: C.red }}>{["?", "??", "???"][Math.floor(frame / 6) % 3]}</span>
        </div>
      </Beat>

      {/* 7: price */}
      <Beat from={from(7)} to={end(7)} top={360}>
        <Burst at={wAt(7, 1)} x={420} y={110} radius={260} />
        <div data-el="card" data-kind="content" style={{ ...card, padding: "18px 44px", transform: `rotate(${-4 + Math.sin(frame / 7) * 2}deg)`, background: C.lime }}>
          <div style={{ fontSize: 150, fontWeight: 800, letterSpacing: -5, lineHeight: 1 }}>₹5,000</div>
          <div style={{ fontSize: 40, fontWeight: 800, textAlign: "center" }}>se shuru</div>
        </div>
      </Beat>

      {/* 8: catchphrase */}
      <Beat from={from(8)} to={end(8)} top={360}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 100, color: C.red, letterSpacing: -3, textAlign: "center", lineHeight: 1.05, textShadow: `5px 5px 0 ${C.ink}`, transform: `translateX(${frame < L[8].s + 12 ? Math.sin(frame * 2) * 8 : 0}px)` }}>
          PEHLE KYUN
          <br />
          NAHI BATAYA?!
        </div>
      </Beat>

      {/* 9: CTA */}
      <Beat from={from(9)} to={end(9)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: "28px 30px", textAlign: "center" }}>
          <div style={{ fontSize: 46, fontWeight: 800 }}>Comment karo</div>
          <div style={{ display: "inline-block", margin: "16px 0", background: C.lime, border: `5px solid ${C.ink}`, borderRadius: 18, padding: "10px 40px", fontSize: 96, fontWeight: 800, letterSpacing: 6, boxShadow: `6px 6px 0 ${C.ink}`, transform: `scale(${1 + 0.03 * Math.sin(frame / 4)})` }}>CLINIC</div>
          <div style={{ fontSize: 30, fontWeight: 700, opacity: 0.65 }}>DM mein details · {handle}</div>
        </div>
      </Beat>

      {/* 10: she comments */}
      <Beat from={from(10)} to={end(10) + 60}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 600, padding: 24, borderRadius: 40 }}>
          <div style={{ fontSize: 26, fontWeight: 800, marginBottom: 14 }}>Comments</div>
          <div style={{ display: "flex", alignItems: "center", gap: 14, opacity: fade(posted, 5), transform: `translateY(${(1 - sp(posted)) * 20}px)`, marginBottom: 14 }}>
            <span style={{ width: 52, height: 52, borderRadius: "50%", background: "#E98AA8", border: `3px solid ${C.ink}` }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 22, fontWeight: 700, opacity: 0.55 }}>drnehamehra · abhi</div>
              <div style={{ fontSize: 34, fontWeight: 800 }}>CLINIC</div>
            </div>
            <span style={{ fontSize: 40, color: C.red, transform: `scale(${Math.min(1, sp(posted + 8, { damping: 7, stiffness: 240, mass: 0.5 }))})` }}>♥</span>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 12, border: `4px solid ${C.ink}`, borderRadius: 999, padding: "12px 12px 12px 26px" }}>
            <span style={{ flex: 1, fontSize: 36, fontWeight: 800, letterSpacing: 3 }}>
              {frame >= posted ? <span style={{ opacity: 0.35, letterSpacing: 0, fontWeight: 600 }}>Add a comment…</span> : typed(frame, "CLINIC", tStart, tEnd)}
              {frame < posted ? <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span> : null}
            </span>
            <span style={{ width: 64, height: 64, borderRadius: "50%", background: frame >= tEnd ? C.green : C.ink, color: "#fff", display: "grid", placeItems: "center", fontSize: 30, fontWeight: 800 }}>↑</span>
          </div>
        </div>
        <Sticker at={posted + 4} text="Comment posted ✓" rot={4} />
      </Beat>
    </>
  );
};
