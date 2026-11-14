// Top-half animations for pharma-trust (EP 03): one beat per spoken line.
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
        <div style={{ background: C.lime, border: `4px solid ${C.ink}`, borderRadius: 12, padding: "6px 18px", fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, color: C.ink, boxShadow: `4px 4px 0 ${C.ink}` }}>SOLVIX STORIES · EP 03</div>
      </div>

      {/* 0: 20 years */}
      <Beat from={from(0)} to={end(0)}>
        <div data-el="card" data-kind="content" style={{ ...card, display: "flex", alignItems: "center", gap: 26, padding: "26px 34px" }}>
          <div style={{ width: 110, height: 110, borderRadius: 22, background: "#4A4E57", border: `4px solid ${C.ink}`, display: "grid", placeItems: "center", color: "#fff", fontWeight: 800, fontSize: 30 }}>BH</div>
          <div>
            <div style={{ fontSize: 44, fontWeight: 800 }}>Bansal Healthcare</div>
            <div style={{ fontSize: 28, fontWeight: 700, opacity: 0.55 }}>Est. 2006 · Baddi</div>
          </div>
        </div>
        <Sticker at={wAt(0, 6)} text="20 saal!" />
      </Beat>

      {/* 1: distributor checks */}
      <Beat from={from(1)} to={end(1)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 26 }}>
          <div style={{ fontSize: 24, fontWeight: 800, opacity: 0.55, marginBottom: 10 }}>Naya distributor · Lucknow</div>
          <div style={{ display: "flex", alignItems: "center", gap: 16, border: `4px solid ${C.ink}`, borderRadius: 999, padding: "14px 24px", fontSize: 36, fontWeight: 700 }}>
            <svg width="34" height="34" viewBox="0 0 24 24"><circle cx="10" cy="10" r="7" fill="none" stroke={C.ink} strokeWidth="3" /><path d="M15.5 15.5 21 21" stroke={C.ink} strokeWidth="3" strokeLinecap="round" /></svg>
            {typed(frame, "bansal healthcare", L[1].s + 4, wAt(1, 5))}
            <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span>
          </div>
        </div>
        <Sticker at={wAt(1, 7)} text="“Asli company hai?”" color="#FFF1B8" rot={4} />
      </Beat>

      {/* 2: we are real */}
      <Beat from={from(2)} to={end(2)} top={360}>
        <div data-el="title" data-kind="content" style={{ display: "flex", alignItems: "center", gap: 24, fontFamily: FONT, fontWeight: 800, fontSize: 104, color: C.ink, letterSpacing: -3 }}>
          <span style={{ width: 130, height: 130, borderRadius: "50%", border: `8px solid ${C.green}`, color: C.green, display: "grid", placeItems: "center", fontSize: 80, transform: `rotate(${-12 * (1 - sp(L[2].s + 4))}deg) scale(${Math.min(1, sp(L[2].s + 4, { damping: 8, stiffness: 220, mass: 0.6 }))})` }}>✓</span>
          Hum asli hain!
        </div>
      </Beat>

      {/* 3: what Google shows */}
      <Beat from={from(3)} to={end(3)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 26 }}>
          <div style={{ fontSize: 36, fontWeight: 800 }}>Bansal Healthcare</div>
          <div style={{ fontSize: 24, fontWeight: 600, opacity: 0.55 }}>No reviews · no website</div>
          {row(wAt(3, 6), false, "98•• ••• 410", "purana number · 2016 ka")}
          {row(wAt(3, 11), false, "bansalpharma1987@gmail.com", "Gmail wali email")}
        </div>
        <Sticker at={wAt(3, 12)} text="bharosa?" color="#FFD7D2" rot={-5} />
      </Beat>

      {/* 4: so what now */}
      <Beat from={from(4)} to={end(4)} top={380}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 116, color: C.ink, letterSpacing: -3, paddingRight: 30 }}>
          Ab kya<span style={{ color: C.red }}> karein</span>
          <span style={{ color: C.red, display: "inline-block", transform: `rotate(${Math.sin(frame / 4) * 12}deg)` }}>?</span>
        </div>
      </Beat>

      {/* 5: the company site + email */}
      <Beat from={from(5)} to={end(5)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 24 }}>
          <div style={{ fontFamily: MONO, fontSize: 24, fontWeight: 600 }}>bansalhealthcare.com</div>
          {row(wAt(5, 2), true, "Company profile", "since 2006 · plant · team")}
          {row(wAt(5, 4), true, "Product list", "naam + composition")}
          {row(wAt(5, 6), true, "Contact page", "address · map · enquiry form")}
          <div style={{ marginTop: 16, display: "inline-flex", alignItems: "center", gap: 12, background: C.lime, border: `4px solid ${C.ink}`, borderRadius: 14, padding: "10px 18px", fontFamily: MONO, fontSize: 26, fontWeight: 600, opacity: fade(wAt(5, 9), 4), transform: `scale(${Math.min(1, 0.6 + 0.4 * sp(wAt(5, 9)))})` }}>
            ✉ info@bansalhealthcare.com
          </div>
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
          <div style={{ display: "inline-block", margin: "16px 0", background: C.lime, border: `5px solid ${C.ink}`, borderRadius: 18, padding: "10px 40px", fontSize: 96, fontWeight: 800, letterSpacing: 6, boxShadow: `6px 6px 0 ${C.ink}`, transform: `scale(${1 + 0.03 * Math.sin(frame / 4)})` }}>PHARMA</div>
          <div style={{ fontSize: 30, fontWeight: 700, opacity: 0.65 }}>DM mein details · {handle}</div>
        </div>
      </Beat>

      {/* 10: she comments */}
      <Beat from={from(10)} to={end(10) + 60}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 600, padding: 24, borderRadius: 40 }}>
          <div style={{ fontSize: 26, fontWeight: 800, marginBottom: 14 }}>Comments</div>
          <div style={{ display: "flex", alignItems: "center", gap: 14, opacity: fade(posted, 5), transform: `translateY(${(1 - sp(posted)) * 20}px)`, marginBottom: 14 }}>
            <span style={{ width: 52, height: 52, borderRadius: "50%", background: "#4A4E57", border: `3px solid ${C.ink}` }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 22, fontWeight: 700, opacity: 0.55 }}>bansalhealthcare · abhi</div>
              <div style={{ fontSize: 34, fontWeight: 800 }}>PHARMA</div>
            </div>
            <span style={{ fontSize: 40, color: C.red, transform: `scale(${Math.min(1, sp(posted + 8, { damping: 7, stiffness: 240, mass: 0.5 }))})` }}>♥</span>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 12, border: `4px solid ${C.ink}`, borderRadius: 999, padding: "12px 12px 12px 26px" }}>
            <span style={{ flex: 1, fontSize: 36, fontWeight: 800, letterSpacing: 3 }}>
              {frame >= posted ? <span style={{ opacity: 0.35, letterSpacing: 0, fontWeight: 600 }}>Add a comment…</span> : typed(frame, "PHARMA", tStart, tEnd)}
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
