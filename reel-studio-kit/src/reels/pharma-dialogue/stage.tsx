// Top-half animations for pharma-dialogue: one beat per spoken line.
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
  const end = (i: number) => (i + 1 < L.length ? L[i + 1].s : L[i].e + 40);
  const from = (i: number) => Math.max(0, L[i].s - 4);

  // 0: incoming call
  const ring = Math.sin(frame / 1.6) * 6 * (frame < L[0].e ? 1 : 0);
  // 3: WhatsApp flood, then catalogue
  const flood = ["Rate list bhejo", "Stock hai?", "Good morning", "Scheme kya hai?", "PDF dobara bhejo", "Ok"];
  const siteAt = wAt(3, 6);
  // 5: form
  const submitAt = wAt(5, 5);
  // 10: typing PHARMA
  const tStart = L[10].s + 4;
  const tEnd = L[10].s + Math.round((L[10].e - L[10].s) * 0.62);
  const posted = tEnd + 8;

  return (
    <>
      <div style={{ position: "absolute", left: 0, right: 0, top: 236, display: "flex", justifyContent: "center", opacity: fade(0, 10) }}>
        <div style={{ background: C.lime, border: `4px solid ${C.ink}`, borderRadius: 12, padding: "6px 18px", fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, color: C.ink, boxShadow: `4px 4px 0 ${C.ink}` }}>SOLVIX STORIES · EP 01</div>
      </div>

      <Beat from={from(0)} to={end(0)}>
        <div data-el="card" data-kind="content" style={{ ...card, display: "flex", alignItems: "center", gap: 26, padding: "26px 34px" }}>
          <div style={{ width: 96, height: 96, borderRadius: "50%", background: C.wa, border: `4px solid ${C.ink}`, display: "grid", placeItems: "center", transform: `rotate(${ring}deg)` }}>
            <svg width="50" height="50" viewBox="0 0 24 24"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z" fill="#fff" /></svg>
          </div>
          <div>
            <div style={{ fontSize: 26, fontWeight: 700, opacity: 0.55 }}>Incoming call…</div>
            <div style={{ fontSize: 44, fontWeight: 800 }}>Distributor · Indore</div>
          </div>
        </div>
        <Sticker at={wAt(0, 9)} text="“phone pe hi order!”" />
      </Beat>

      <Beat from={from(1)} to={end(1)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 26 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 16, border: `4px solid ${C.ink}`, borderRadius: 999, padding: "14px 24px", fontSize: 38, fontWeight: 700 }}>
            <svg width="34" height="34" viewBox="0 0 24 24"><circle cx="10" cy="10" r="7" fill="none" stroke={C.ink} strokeWidth="3" /><path d="M15.5 15.5 21 21" stroke={C.ink} strokeWidth="3" strokeLinecap="round" /></svg>
            {typed(frame, "gupta pharma", L[1].s + 4, wAt(1, 6))}
            <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span>
          </div>
          {[
            { ok: false, t: "Gupta Pharma", s: "website nahi mili", at: wAt(1, 7) },
            { ok: true, t: "Sharma Life Sciences", s: "website · products · contact", at: wAt(1, 8) },
          ].map((r, i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 18, marginTop: 18, opacity: fade(r.at, 4), transform: `translateX(${(1 - sp(r.at)) * -40}px)` }}>
              <Mark ok={r.ok} />
              <div>
                <div style={{ fontSize: 34, fontWeight: 800 }}>{r.t}</div>
                <div style={{ fontSize: 24, fontWeight: 600, color: r.ok ? C.green : C.red }}>{r.s}</div>
              </div>
            </div>
          ))}
        </div>
      </Beat>

      <Beat from={from(2)} to={end(2)}>
        <div data-el="card" data-kind="content" style={{ ...card, display: "flex", alignItems: "center", gap: 24, padding: "26px 34px", transform: `rotate(${Math.sin(frame / 9) * 1.5}deg)` }}>
          <div style={{ width: 90, height: 112, background: C.red, border: `4px solid ${C.ink}`, borderRadius: 12, display: "grid", placeItems: "center", color: "#fff", fontWeight: 800, fontSize: 28 }}>PDF</div>
          <div>
            <div style={{ fontSize: 34, fontWeight: 800, fontFamily: MONO }}>catalogue_final_v3(2).pdf</div>
            <div style={{ fontSize: 26, fontWeight: 600, opacity: 0.55 }}>18.4 MB · 64 pages</div>
          </div>
        </div>
      </Beat>

      <Beat from={from(3)} to={siteAt}>
        <div style={{ position: "relative" }}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 640, padding: 0, clipPath: "inset(-12px -14px -14px -12px round 28px)" }}>
          <div style={{ background: C.waDark, color: "#fff", padding: "16px 24px", fontSize: 30, fontWeight: 800 }}>Distributors Group</div>
          <div style={{ position: "relative", height: 330, background: "#ECE5DD", clipPath: "inset(0)" }}>
            {[{ pdf: true, txt: "catalogue_final_v3(2).pdf" }, ...flood.map((f) => ({ pdf: false, txt: f }))].map((m, i) => {
              const at = L[3].s + 6 + i * 6;
              const shift = flood.filter((_, k) => frame >= L[3].s + 6 + (k + 1) * 6).length * 62;
              const y = 250 - shift + i * 62;
              return (
                <div key={i} style={{ position: "absolute", left: i % 2 ? 160 : 20, top: y, opacity: i === 0 ? 1 : fade(at, 3), background: m.pdf ? "#fff" : i % 2 ? "#DCF8C6" : "#fff", border: m.pdf ? `3px solid ${C.red}` : "none", borderRadius: 14, padding: "10px 16px", fontSize: 24, fontWeight: m.pdf ? 800 : 600, color: m.pdf ? C.red : C.ink }}>
                  {m.pdf ? "PDF · " : ""}
                  {m.txt}
                </div>
              );
            })}
          </div>
        </div>
        <div style={{ position: "absolute", right: -10, top: -40 }}>
          <Sticker at={L[3].s + 30} text="PDF kho gaya!" color="#FFD7D2" rot={5} />
        </div>
        </div>
      </Beat>

      <Beat from={siteAt} to={end(3)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: 24 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontFamily: MONO, fontSize: 24, fontWeight: 600, marginBottom: 14 }}>
            <span>guptapharma.in/products</span>
            <span style={{ background: C.lime, border: `3px solid ${C.ink}`, borderRadius: 8, padding: "2px 10px" }}>64 products</span>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
            {[
              ["Gupcet-10", "Cetirizine 10 mg"],
              ["Paragup-650", "Paracetamol 650 mg"],
              ["Azigup-500", "Azithromycin 500 mg"],
              ["Pantagup-40", "Pantoprazole 40 mg"],
            ].map(([n, c], i) => (
              <div key={n} style={{ border: `3px solid ${C.ink}`, borderRadius: 16, padding: "14px 16px", background: "#fff", opacity: fade(siteAt + 4 + i * 4, 4), transform: `scale(${Math.min(1, 0.8 + 0.2 * sp(siteAt + 4 + i * 4))})` }}>
                <div style={{ fontSize: 30, fontWeight: 800 }}>{n}</div>
                <div style={{ fontSize: 22, fontWeight: 600, opacity: 0.6 }}>{c}</div>
              </div>
            ))}
          </div>
        </div>
      </Beat>

      <Beat from={from(4)} to={end(4)} top={380}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 116, color: C.ink, letterSpacing: -4, paddingRight: 30 }}>
          Franchise<span style={{ color: C.red, display: "inline-block", transform: `rotate(${Math.sin(frame / 4) * 12}deg)` }}>?</span>
        </div>
      </Beat>

      <Beat from={from(5)} to={end(5)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 640, padding: 26 }}>
          <div style={{ fontSize: 30, fontWeight: 800, marginBottom: 12 }}>Franchise enquiry</div>
          {[
            ["Naam", "Rakesh Verma", 0],
            ["City", "Indore", 1],
            ["Phone", "98•• ••• 210", 2],
          ].map(([k, v, i]) => {
            const a = L[5].s + 6 + (i as number) * 12;
            return (
              <div key={k as string} style={{ display: "flex", gap: 14, alignItems: "center", border: `3px solid ${C.ink}33`, borderRadius: 12, padding: "10px 16px", marginBottom: 10, fontSize: 28 }}>
                <span style={{ width: 90, fontWeight: 700, opacity: 0.5 }}>{k}</span>
                <span style={{ fontWeight: 700 }}>{typed(frame, v as string, a, a + 10)}</span>
              </div>
            );
          })}
          <div style={{ marginTop: 6, textAlign: "center", background: frame >= submitAt ? C.green : C.ink, color: "#fff", borderRadius: 14, padding: "14px 0", fontSize: 30, fontWeight: 800, transform: `scale(${frame >= submitAt && frame < submitAt + 6 ? 0.94 : 1})` }}>
            {frame >= submitAt ? "✓ Sent" : "Submit"}
          </div>
        </div>
        <div data-el="sticker" data-kind="content" style={{ marginTop: 22, width: 640, display: "flex", alignItems: "center", gap: 16, background: "#fff", border: `4px solid ${C.ink}`, borderRadius: 20, padding: "14px 18px", boxShadow: `6px 6px 0 ${C.ink}`, opacity: fade(submitAt + 6, 4), transform: `translateY(${(1 - sp(submitAt + 6)) * -30}px)`, fontFamily: FONT }}>
          <span style={{ width: 56, height: 56, borderRadius: 14, background: C.wa, border: `3px solid ${C.ink}`, flex: "none" }} />
          <div>
            <div style={{ fontSize: 22, fontWeight: 700, opacity: 0.55 }}>WhatsApp · abhi</div>
            <div style={{ fontSize: 28, fontWeight: 800 }}>New franchise lead: Rakesh, Indore</div>
          </div>
        </div>
      </Beat>

      <Beat from={from(6)} to={end(6)} top={380}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 120, color: C.ink, letterSpacing: -3 }}>
          ₹ <span style={{ color: C.red }}>{["?", "??", "???"][Math.floor(frame / 6) % 3]}</span>
        </div>
      </Beat>

      <Beat from={from(7)} to={end(7)} top={360}>
        <Burst at={wAt(7, 2)} x={420} y={110} radius={260} />
        <div data-el="card" data-kind="content" style={{ ...card, padding: "18px 44px", transform: `rotate(${-4 + Math.sin(frame / 7) * 2}deg)`, background: C.lime }}>
          <div style={{ fontSize: 150, fontWeight: 800, letterSpacing: -5, lineHeight: 1 }}>₹5,000</div>
          <div style={{ fontSize: 40, fontWeight: 800, textAlign: "center" }}>se shuru</div>
        </div>
      </Beat>

      <Beat from={from(8)} to={end(8)} top={380}>
        <div data-el="title" data-kind="content" style={{ fontFamily: FONT, fontWeight: 800, fontSize: 170, color: C.red, letterSpacing: -4, transform: `translateX(${frame < L[8].s + 12 ? Math.sin(frame * 2) * 8 : 0}px)`, textShadow: `6px 6px 0 ${C.ink}` }}>
          ARRE!
        </div>
      </Beat>

      <Beat from={from(9)} to={end(9)}>
        <div data-el="card" data-kind="content" style={{ ...card, width: "100%", padding: "28px 30px", textAlign: "center" }}>
          <div style={{ fontSize: 46, fontWeight: 800 }}>Comment karo</div>
          <div style={{ display: "inline-block", margin: "16px 0", background: C.lime, border: `5px solid ${C.ink}`, borderRadius: 18, padding: "10px 40px", fontSize: 96, fontWeight: 800, letterSpacing: 6, boxShadow: `6px 6px 0 ${C.ink}`, transform: `scale(${1 + 0.03 * Math.sin(frame / 4)})` }}>PHARMA</div>
          <div style={{ fontSize: 30, fontWeight: 700, opacity: 0.65 }}>DM mein details · {handle}</div>
        </div>
      </Beat>

      <Beat from={from(10)} to={end(10) + 60}>
        <div data-el="card" data-kind="content" style={{ ...card, width: 600, padding: 24, borderRadius: 40 }}>
          <div style={{ fontSize: 26, fontWeight: 800, marginBottom: 14 }}>Comments</div>
          <div style={{ display: "flex", alignItems: "center", gap: 14, opacity: fade(posted, 5), transform: `translateY(${(1 - sp(posted)) * 20}px)`, marginBottom: 14 }}>
            <span style={{ width: 52, height: 52, borderRadius: "50%", background: "#1F8A84", border: `3px solid ${C.ink}` }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 22, fontWeight: 700, opacity: 0.55 }}>guptapharma · abhi</div>
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
