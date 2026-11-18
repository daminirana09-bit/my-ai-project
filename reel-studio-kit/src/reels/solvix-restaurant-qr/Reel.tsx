// Reel "solvix-restaurant-qr" (~53 s): Solvix restaurant QR system, step by step (scan -> menu -> order -> kitchen ->
// wait -> call waiter -> pay -> review), dark stage in the Solvix logo colours (black + neon green).
// All screens are designed mock-ups of a demo restaurant ("Swaad Kitchen"), not a real client.
// Voice: George (free premade). Music + SFX: Mixkit free licences.
import React from "react";
import { Easing, Img, interpolate, random, staticFile } from "remotion";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { CL, CommentCTA, CornerBrand, Captions, FONT, LOGO, MONO, PhoneFrame, SolvixBackdrop, SoundTrack, StepLabel, SX, Tap, useA, type Cue } from "../../components/solvix";
import { timing } from "../../timing";
import words from "../../../public/reels/solvix-restaurant-qr/captions.words.json";

export const t = timing(words);
const at = (i: number) => t.at(i, 0);
export const durationInFrames = t.duration(0.9);
const LINES = [0, 16, 27, 38, 50, 61, 73, 89, 103, 109, 123];
const L = (i: number) => (i < LINES.length ? at(LINES[i]) : durationInFrames);
const HI = new Set([12, 20, 24, 30, 31, 34, 44, 47, 52, 55, 66, 71, 75, 76, 80, 81, 92, 97, 102, 104, 108, 111, 113, 114, 115, 120, 121, 122, 127]);

// ---------- layout ----------
const PH = { x: 255, y: 470, w: 500, h: 880 }; // phone
const SW = PH.w - 24; // screen width
const SH = PH.h - 24;
const CAP_TOP = 1392;

// ---------- small UI bits (inside the phone screen, light theme) ----------
const ink = "#141714";
const EMO = "'Noto Color Emoji', sans-serif";
const sub = "#6E766F";
const pill = (on = false): React.CSSProperties => ({ padding: "8px 16px", borderRadius: 999, fontSize: 20, fontWeight: 700, background: on ? SX.green2 : "#EEF0EC", color: on ? "#fff" : ink, whiteSpace: "nowrap" });
const Ring: React.FC<{ x: number; y: number; w: number; h: number; at: number }> = ({ x, y, w, h, at: a }) => {
  const { frame } = useA();
  if (frame < a) return null;
  return <div style={{ position: "absolute", left: x, top: y, width: w, height: h, borderRadius: 16, border: `4px solid ${SX.green}`, background: `${SX.green}18`, boxSizing: "border-box", opacity: interpolate(frame - a, [0, 5], [0, 1], CL), boxShadow: `0 0 24px ${SX.green}66` }} />;
};
const AppBar: React.FC<{ title: string; chip?: string }> = ({ title, chip }) => (
  <div style={{ position: "absolute", left: 24, right: 24, top: 64, display: "flex", alignItems: "center", justifyContent: "space-between", fontFamily: FONT }}>
    <div style={{ fontSize: 34, fontWeight: 800, color: ink, letterSpacing: -0.5 }}>{title}</div>
    {chip ? <div style={{ ...pill(true), fontFamily: MONO, fontSize: 16, letterSpacing: 2 }}>{chip}</div> : null}
  </div>
);

/** Paneer tikka on a skewer (drawn, no emoji has one). */
const Tikka: React.FC<{ size: number }> = ({ size }) => (
  <svg width={size} height={size} viewBox="0 0 100 100">
    <path d="M12 88 L 90 10" stroke="#8A6A45" strokeWidth="4" strokeLinecap="round" />
    {[[30, 70, "#F08A3C"], [47, 53, "#7BB661"], [62, 38, "#F2A24A"], [76, 24, "#E0603A"]].map(([x, y, c], k) => (
      <g key={k} transform={`rotate(-45 ${x} ${y})`}>
        <rect x={(x as number) - 11} y={(y as number) - 11} width="22" height="22" rx="5" fill={c as string} stroke="#5A3418" strokeWidth="2" />
        <path d={`M${(x as number) - 8} ${(y as number) - 4} h7 M${(x as number) + 1} ${(y as number) + 5} h6`} stroke="#5A3418" strokeWidth="2.5" strokeLinecap="round" opacity="0.6" />
      </g>
    ))}
  </svg>
);

const DISHES = [
  { e: "tikka", n: "Paneer Tikka", d: "Tandoor, mint chutney", p: 280 },
  { e: "🥘", n: "Dal Makhani", d: "Slow cooked, butter", p: 240 },
  { e: "🍚", n: "Veg Biryani", d: "Dum, raita", p: 260 },
  { e: "🫓", n: "Butter Naan", d: "Tandoor", p: 60 },
  { e: "🥛", n: "Masala Chaas", d: "Chilled", p: 70 },
  { e: "🍮", n: "Gulab Jamun", d: "2 pcs, warm", p: 90 },
];

const QR: React.FC<{ size: number }> = ({ size }) => {
  const n = 21;
  const c = size / n;
  const finder = (x: number, y: number) => x < 7 && y < 7 ? true : x >= n - 7 && y < 7 ? true : x < 7 && y >= n - 7;
  const finderOn = (x: number, y: number) => { const fx = x >= n - 7 ? x - (n - 7) : x; const fy = y >= n - 7 ? y - (n - 7) : y; return fx === 0 || fy === 0 || fx === 6 || fy === 6 || (fx >= 2 && fx <= 4 && fy >= 2 && fy <= 4); };
  return (
    <svg width={size} height={size}>
      {Array.from({ length: n * n }, (_, k) => {
        const x = k % n, y = Math.floor(k / n);
        const on = finder(x, y) ? finderOn(x, y) : random(`qr${k}`) > 0.52;
        return on ? <rect key={k} x={x * c} y={y * c} width={c + 0.5} height={c + 0.5} fill="#111" /> : null;
      })}
    </svg>
  );
};

// ---------- phone screens ----------
const ScanScreen: React.FC<{ from: number }> = ({ from }) => {
  const { frame, sp } = useA();
  const scanY = 250 + ((frame - from) * 9) % 300;
  const note = sp(at(21) + 4, { damping: 12, stiffness: 180, mass: 0.6 });
  return (
    <div style={{ position: "absolute", inset: 0, background: "radial-gradient(circle at 50% 45%, #3B2A1C, #120D09 75%)" }}>
      <div style={{ position: "absolute", left: 48, right: 48, top: 70, textAlign: "center", fontFamily: FONT, color: "#fff", fontSize: 22, opacity: 0.8 }}>Table pe QR scan karo</div>
      <div style={{ position: "absolute", left: (SW - 300) / 2, top: 230, width: 300, height: 300, background: "#fff", borderRadius: 18, padding: 22, boxSizing: "border-box", transform: `rotate(-3deg)` }}>
        <QR size={256} />
        <div style={{ position: "absolute", left: 0, right: 0, bottom: -44, textAlign: "center", fontFamily: MONO, fontSize: 18, color: "#fff" }}>TABLE 5</div>
      </div>
      {[0, 1, 2, 3].map((k) => (
        <div key={k} style={{ position: "absolute", left: k % 2 ? SW / 2 + 130 : SW / 2 - 180, top: k < 2 ? 200 : 510, width: 50, height: 50, borderColor: SX.green, borderStyle: "solid", borderWidth: `${k < 2 ? 6 : 0}px ${k % 2 ? 6 : 0}px ${k >= 2 ? 6 : 0}px ${k % 2 ? 0 : 6}px`, borderRadius: 8 }} />
      ))}
      <div style={{ position: "absolute", left: SW / 2 - 170, width: 340, top: scanY, height: 4, background: SX.green, boxShadow: `0 0 18px ${SX.green}` }} />
      {frame >= at(21) + 4 ? (
        <div style={{ position: "absolute", left: 24, right: 24, top: 620, background: "rgba(255,255,255,.95)", borderRadius: 20, padding: "16px 18px", display: "flex", alignItems: "center", gap: 14, fontFamily: FONT, transform: `translateY(${(1 - Math.min(1, note)) * 60}px)`, opacity: Math.min(1, note * 1.5) }}>
          <div style={{ width: 52, height: 52, borderRadius: 12, background: SX.green2, color: "#fff", display: "grid", placeItems: "center", fontWeight: 800, fontSize: 26 }}>S</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 22, fontWeight: 800, color: ink }}>Swaad Kitchen · Menu</div>
            <div style={{ fontSize: 18, color: sub }}>swaadkitchen.in/table-5</div>
          </div>
          <div style={{ ...pill(true), fontSize: 18 }}>Open</div>
        </div>
      ) : null}
    </div>
  );
};

const MenuScreen: React.FC<{ from: number }> = ({ from }) => {
  const { sp } = useA();
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <AppBar title="Swaad Kitchen" chip="TABLE 5" />
      <div style={{ position: "absolute", left: 24, right: 24, top: 124, height: 50, borderRadius: 14, background: "#EEF0EC", color: sub, fontSize: 20, display: "flex", alignItems: "center", paddingLeft: 18 }}>Search dishes…</div>
      <div style={{ position: "absolute", left: 24, right: 0, top: 192, display: "flex", gap: 8 }}>
        {["Starters", "Main", "Breads", "Drinks"].map((c, k) => <div key={c} style={pill(k === 1)}>{c}</div>)}
      </div>
      {DISHES.map((d, k) => {
        const s = Math.min(1, sp(from + 3 + k * 3));
        return (
          <div key={d.n} style={{ position: "absolute", left: 24, right: 24, top: 258 + k * 96, height: 84, display: "flex", alignItems: "center", gap: 14, borderBottom: "1px solid #ECEEEA", opacity: s, transform: `translateX(${(1 - s) * 40}px)` }}>
            <div style={{ width: 64, height: 64, borderRadius: 16, background: "#FFF4E2", display: "grid", placeItems: "center", fontSize: 36, fontFamily: EMO }}>{d.e === "tikka" ? <Tikka size={50} /> : d.e}</div>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 23, fontWeight: 800, color: ink }}>{d.n}</div>
              <div style={{ fontSize: 17, color: sub }}>{d.d}</div>
            </div>
            <div style={{ fontSize: 22, fontWeight: 800, color: ink }}>₹{d.p}</div>
            <div style={{ width: 40, height: 40, borderRadius: 12, background: SX.green2, color: "#fff", display: "grid", placeItems: "center", fontSize: 28, fontWeight: 800 }}>+</div>
          </div>
        );
      })}
    </div>
  );
};

const DishScreen: React.FC<{ from: number }> = ({ from }) => {
  const { sp } = useA();
  const s = Math.min(1, sp(from, { damping: 15, stiffness: 150, mass: 0.8 }));
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT, background: "#FAFAF7" }}>
      <div style={{ position: "absolute", left: 0, right: 0, top: 0, height: 300, background: "linear-gradient(160deg, #FFE7C2, #FFD199)", display: "grid", placeItems: "center" }}><Tikka size={210} /></div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 270, bottom: 0, background: "#FAFAF7", borderRadius: "30px 30px 0 0", padding: "26px 26px", transform: `translateY(${(1 - s) * 200}px)` }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
          <div style={{ fontSize: 36, fontWeight: 800, color: ink }}>Paneer Tikka</div>
          <div style={{ fontSize: 34, fontWeight: 800, color: SX.green2 }}>₹280</div>
        </div>
        <div style={{ fontSize: 19, color: sub, marginTop: 4 }}>Tandoor mein bana, mint chutney ke saath</div>
        <div style={{ marginTop: 26, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div style={{ fontSize: 20, fontWeight: 700, color: sub, fontFamily: MONO, letterSpacing: 1 }}>SPICE LEVEL</div>
          <div style={{ fontSize: 30, fontFamily: EMO }}>🌶️🌶️<span style={{ opacity: 0.25 }}>🌶️</span></div>
        </div>
        <div style={{ marginTop: 18, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div style={{ fontSize: 20, fontWeight: 700, color: sub, fontFamily: MONO, letterSpacing: 1 }}>ALLERGY</div>
          <div style={{ display: "flex", gap: 8 }}><div style={{ ...pill(), background: "#FFE9E2", color: "#B23A1E" }}>Dairy</div><div style={{ ...pill(), background: "#FFE9E2", color: "#B23A1E" }}>Nuts</div></div>
        </div>
        <div style={{ marginTop: 18, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div style={{ fontSize: 20, fontWeight: 700, color: sub, fontFamily: MONO, letterSpacing: 1 }}>SIZE</div>
          <div style={{ display: "flex", gap: 8 }}><div style={pill()}>Half</div><div style={pill(true)}>Full</div></div>
        </div>
        <div style={{ position: "absolute", left: 26, right: 26, bottom: 36, height: 70, borderRadius: 18, background: SX.green2, color: "#fff", display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800 }}>Add to order · ₹280</div>
      </div>
      <Ring x={SW - 150} y={286} w={136} h={56} at={at(30)} />
      <Ring x={16} y={378} w={SW - 32} h={56} at={at(31)} />
      <Ring x={16} y={440} w={SW - 32} h={56} at={at(34)} />
    </div>
  );
};

const CartScreen: React.FC<{ from: number }> = () => {
  const { frame, sp } = useA();
  const placed = frame >= at(41) + 4;
  const items: [string, number, number][] = [["Paneer Tikka", 1, 280], ["Butter Naan", 2, 120], ["Masala Chaas", 1, 70]];
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <AppBar title="Your order" chip="TABLE 5" />
      {items.map(([n, q, p], k) => (
        <div key={n} style={{ position: "absolute", left: 24, right: 24, top: 150 + k * 82, height: 70, display: "flex", alignItems: "center", borderBottom: "1px solid #ECEEEA", fontSize: 24 }}>
          <div style={{ width: 44, height: 44, borderRadius: 10, background: "#EEF0EC", display: "grid", placeItems: "center", fontWeight: 800, marginRight: 14 }}>{q}×</div>
          <div style={{ flex: 1, fontWeight: 700, color: ink }}>{n}</div>
          <div style={{ fontWeight: 800, color: ink }}>₹{p}</div>
        </div>
      ))}
      <div style={{ position: "absolute", left: 24, right: 24, top: 410, display: "flex", justifyContent: "space-between", fontSize: 30, fontWeight: 800, color: ink }}><span>Total</span><span>₹470</span></div>
      <div style={{ position: "absolute", left: 24, right: 24, top: 520, height: 80, borderRadius: 20, background: placed ? "#E6F8E4" : SX.green2, color: placed ? SX.green2 : "#fff", display: "grid", placeItems: "center", fontSize: 28, fontWeight: 800, border: placed ? `3px solid ${SX.green2}` : "none" }}>
        {placed ? "✓ Order placed" : "Place order"}
      </div>
      <Tap x={SW / 2} y={560} at={at(41)} />
      {frame >= at(44) ? (
        <div style={{ position: "absolute", left: 60, right: 60, top: 650, background: "#141714", color: "#fff", borderRadius: 18, padding: "16px 20px", fontFamily: MONO, fontSize: 20, transform: `translateY(${-interpolate(frame - at(44), [8, 26], [0, 520], { ...CL, easing: Easing.in(Easing.cubic) })}px) scale(${Math.min(1, sp(at(44)))})` }}>
          <div style={{ color: SX.green }}>→ KITCHEN</div>
          <div style={{ marginTop: 4 }}>T5 · 3 items · 9:41 PM</div>
        </div>
      ) : null}
    </div>
  );
};

const WaitScreen: React.FC<{ from: number }> = ({ from }) => {
  const { frame } = useA();
  const secs = Math.max(0, 12 * 60 - Math.floor((frame - from) / 2));
  const mm = String(Math.floor(secs / 60)).padStart(2, "0");
  const ss = String(secs % 60).padStart(2, "0");
  const prog = 0.28 + (frame - from) / 2000;
  const R = 130;
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <div style={{ position: "absolute", left: 28, top: 70, fontFamily: MONO, fontSize: 16, letterSpacing: 2, color: sub }}>TABLE 5 · ORDER #T5-21</div>
      <div style={{ position: "absolute", left: 28, top: 100, fontSize: 40, fontWeight: 800, color: ink, lineHeight: 1.1 }}>Aapka khana<br /><span style={{ color: SX.green2 }}>ban raha hai</span></div>
      <svg width={320} height={320} style={{ position: "absolute", left: (SW - 320) / 2, top: 250 }}>
        <circle cx={160} cy={160} r={R} stroke="#ECEEEA" strokeWidth={18} fill="none" />
        <circle cx={160} cy={160} r={R} stroke={SX.green2} strokeWidth={18} fill="none" strokeLinecap="round" strokeDasharray={`${2 * Math.PI * R * prog} ${2 * Math.PI * R}`} transform="rotate(-90 160 160)" />
      </svg>
      <div style={{ position: "absolute", left: 0, right: 0, top: 350, textAlign: "center", fontFamily: MONO, fontSize: 64, fontWeight: 700, color: ink }}>{mm}:{ss}</div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 430, textAlign: "center", fontFamily: MONO, fontSize: 18, color: sub, letterSpacing: 2 }}>MIN BAAKI</div>
      <div style={{ position: "absolute", left: 28, right: 28, top: 620, display: "flex", justifyContent: "space-between", fontSize: 22, fontWeight: 700 }}>
        {[["Ordered", "✓", SX.green2], ["Cooking", "●", SX.amber], ["Ready", "○", "#A4ABA5"]].map(([l, i, c]) => <div key={l} style={{ color: c as string }}>{i} {l}</div>)}
      </div>
      <Ring x={(SW - 340) / 2} y={240} w={340} h={340} at={at(70)} />
    </div>
  );
};

const CallScreen: React.FC<{ from: number }> = () => {
  const { frame } = useA();
  const called = frame >= at(77) + 2;
  const pulse = 1 + Math.sin(frame / 4) * 0.04;
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <div style={{ position: "absolute", left: 28, top: 70, fontFamily: MONO, fontSize: 16, letterSpacing: 2, color: sub }}>TABLE 5</div>
      <div style={{ position: "absolute", left: 28, top: 100, fontSize: 42, fontWeight: 800, color: ink, lineHeight: 1.1 }}>Kuch<br /><span style={{ color: SX.green2 }}>chahiye?</span></div>
      <div style={{ position: "absolute", left: (SW - 260) / 2, top: 300, width: 260, height: 260, borderRadius: "50%", background: called ? SX.green2 : "#1DB817", boxShadow: `0 0 0 ${18 + Math.sin(frame / 4) * 8}px ${SX.green}33, 0 0 0 ${44 + Math.sin(frame / 4) * 10}px ${SX.green}14`, display: "grid", placeItems: "center", color: "#fff", fontSize: 32, fontWeight: 800, textAlign: "center", whiteSpace: "pre-line", transform: `scale(${called ? 1 : pulse})` }}>
        {called ? "✓ Waiter\naa raha hai" : "🛎️\nCall waiter"}
      </div>
      <div style={{ position: "absolute", left: 28, right: 28, top: 640, display: "flex", gap: 10, justifyContent: "center" }}>{["Paani", "Napkins", "Bill"].map((x) => <div key={x} style={pill()}>{x}</div>)}</div>
      <Tap x={SW / 2} y={430} at={at(77)} />
    </div>
  );
};

const PayScreen: React.FC<{ from: number }> = () => {
  const { frame } = useA();
  const split = frame >= at(92);
  const opt = frame >= at(102) ? 2 : frame >= at(100) ? 1 : frame >= at(99) ? 0 : -1;
  const paid = frame >= at(102) + 14;
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <AppBar title="Your bill" chip="TABLE 5" />
      <div style={{ position: "absolute", left: 24, right: 24, top: 140, fontSize: 21, color: sub, lineHeight: 1.9 }}>
        {[["Paneer Tikka", 280], ["Butter Naan ×2", 120], ["Masala Chaas", 70]].map(([n, p]) => <div key={n as string} style={{ display: "flex", justifyContent: "space-between" }}><span>{n}</span><span>₹{p}</span></div>)}
      </div>
      <div style={{ position: "absolute", left: 24, right: 24, top: 270, display: "flex", justifyContent: "space-between", fontSize: 30, fontWeight: 800, color: ink }}><span>Total</span><span>₹470</span></div>
      <div style={{ position: "absolute", left: 24, right: 24, top: 330, display: "flex", alignItems: "center", gap: 10 }}>
        <div style={{ fontFamily: MONO, fontSize: 16, color: sub, letterSpacing: 2, marginRight: 6 }}>SPLIT</div>
        {["1", "2 log", "3 log"].map((x, k) => <div key={x} style={pill(split ? k === 1 : k === 0)}>{x}</div>)}
        <div style={{ marginLeft: "auto", fontSize: 24, fontWeight: 800, color: SX.green2, opacity: split ? 1 : 0 }}>₹235 each</div>
      </div>
      {["💵  Cash", "💳  Card", "📱  UPI"].map((x, k) => (
        <div key={x} style={{ position: "absolute", left: 24, right: 24, top: 410 + k * 84, height: 70, borderRadius: 16, border: `3px solid ${opt === k ? SX.green2 : "#E3E6E1"}`, background: opt === k ? "#E9F9E6" : "#fff", display: "flex", alignItems: "center", padding: "0 20px", fontSize: 25, fontWeight: 700, color: ink, boxSizing: "border-box" }}>
          <span style={{ flex: 1 }}>{x}</span>{opt === k ? <span style={{ color: SX.green2 }}>✓</span> : null}
        </div>
      ))}
      <div style={{ position: "absolute", left: 24, right: 24, top: 680, height: 76, borderRadius: 18, background: paid ? "#E9F9E6" : SX.green2, color: paid ? SX.green2 : "#fff", display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800, border: paid ? `3px solid ${SX.green2}` : "none" }}>{paid ? "✓ Paid · receipt bhej di" : "Pay ₹235"}</div>
      <Tap x={SW / 2} y={718} at={at(102) + 8} />
    </div>
  );
};

const ReviewScreen: React.FC<{ from: number }> = ({ from }) => {
  const { frame, sp } = useA();
  const stars = Math.min(5, Math.max(0, Math.floor((frame - (at(106) - 4)) / 4)));
  const done = frame >= at(108) + 6;
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT }}>
      <div style={{ position: "absolute", left: 28, top: 90, fontSize: 44, fontWeight: 800, color: ink, lineHeight: 1.1 }}>Khana kaisa<br /><span style={{ color: SX.green2 }}>laga?</span></div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 330, display: "flex", justifyContent: "center", gap: 10 }}>
        {[0, 1, 2, 3, 4].map((k) => <div key={k} style={{ fontSize: 66, color: k < stars ? "#FFB020" : "#DADDD8", transform: `scale(${k < stars ? 1 + 0.15 * Math.max(0, 1 - (frame - (at(106) - 4 + k * 4)) / 6) : 1})` }}>★</div>)}
      </div>
      <div style={{ position: "absolute", left: 28, right: 28, top: 460, height: 120, borderRadius: 16, background: "#F1F3EF", padding: 16, fontSize: 21, color: sub, boxSizing: "border-box" }}>{stars >= 5 ? "Paneer tikka zabardast tha!" : "Kuch likhna hai? (optional)"}</div>
      {done ? <div style={{ position: "absolute", left: 60, right: 60, top: 640, height: 70, borderRadius: 18, background: SX.green2, color: "#fff", display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800, transform: `scale(${Math.min(1, sp(at(108) + 6))})` }}>Thank you! 🙏</div> : null}
    </div>
  );
};

type Screen = { from: number; C: React.FC<{ from: number }>; bg?: string };
const PHONES: { name: string; from: number; to: number; screens: Screen[] }[] = [
  { name: "scan-menu", from: L(1), to: L(3), screens: [{ from: L(1), C: ScanScreen, bg: "#120D09" }, { from: at(23), C: MenuScreen }, { from: L(2), C: DishScreen }] },
  { name: "order", from: L(3), to: L(4), screens: [{ from: L(3), C: CartScreen }] },
  { name: "wait-call", from: L(5), to: at(78), screens: [{ from: L(5), C: WaitScreen }, { from: L(6), C: CallScreen }] },
  { name: "pay-review", from: L(7), to: L(9), screens: [{ from: L(7), C: PayScreen }, { from: L(8), C: ReviewScreen }] },
];
const Phone: React.FC<{ p: (typeof PHONES)[number] }> = ({ p }) => {
  const { frame, sp } = useA();
  if (frame < p.from - 4 || frame >= p.to) return null;
  const s = sp(p.from - 4, { damping: 16, stiffness: 140, mass: 0.8 });
  const out = interpolate(frame, [p.to - 6, p.to], [0, 1], CL);
  let idx = 0;
  p.screens.forEach((sc, i) => { if (frame >= sc.from) idx = i; });
  return (
    <div data-scene={p.name} style={{ position: "absolute", inset: 0 }}>
      <PhoneFrame x={PH.x} y={PH.y} w={PH.w} h={PH.h} screenBg={p.screens[idx].bg ?? "#FAFAF7"} style={{ transform: `translateY(${(1 - s) * 300 - out * 40}px) rotate(${(1 - s) * 6}deg)`, opacity: Math.min(1, s * 1.6) * (1 - out) }}>
        {p.screens.map((sc, i) => {
          if (i < idx - 1 || i > idx) return null;
          const vis = i === idx ? interpolate(frame - sc.from, [0, 6], [0, 1], CL) : 1;
          return (
            <div key={i} style={{ position: "absolute", inset: 0, opacity: vis, background: sc.bg ?? "#FAFAF7" }}>
              <sc.C from={sc.from} />
            </div>
          );
        })}
      </PhoneFrame>
    </div>
  );
};

// ---------- floor map (hook + call waiter) ----------
const TABLES = [[220, 760], [505, 760], [790, 760], [220, 1100], [505, 1100], [790, 1100]] as const;
const KITCHEN = [505, 560] as const;
const lerpPath = (pts: readonly (readonly [number, number])[], p: number) => {
  const seg = Math.min(pts.length - 2, Math.floor(p * (pts.length - 1)));
  const f = p * (pts.length - 1) - seg;
  return [pts[seg][0] + (pts[seg + 1][0] - pts[seg][0]) * f, pts[seg][1] + (pts[seg + 1][1] - pts[seg][1]) * f];
};
const Floor: React.FC<{ mode: "hook" | "call"; from: number; to: number }> = ({ mode, from, to }) => {
  const { frame, sp, fade } = useA();
  if (frame < from - 2 || frame >= to) return null;
  const out = interpolate(frame, [to - 6, to], [1, 0], CL);
  const site = frame >= at(10);
  const ALERTS = [[0, at(2)], [2, at(4)], [3, at(6)], [5, at(8)]] as const;
  // waiter: hook = wanders between tables, call = kitchen -> table 5
  const hookPath = [[505, 930], [300, 900], [300, 640], [700, 640], [700, 930], [505, 930]] as const;
  const callPath = [[505, 600], [360, 640], [360, 930], [505, 990]] as const;
  const p = mode === "hook" ? ((frame - from) / 150) % 1 : interpolate(frame, [at(79), at(86)], [0, 1], { ...CL, easing: Easing.inOut(Easing.cubic) });
  const [wx, wy] = lerpPath(mode === "hook" ? hookPath : callPath, p);
  const callSecs = Math.max(0, Math.floor((frame - at(79)) / 30));
  return (
    <div data-scene={`floor-${mode}`} style={{ position: "absolute", inset: 0, opacity: fade(from - 2, 8) * out }}>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 70, top: 470, width: 880, height: 880, borderRadius: 40, border: `2px solid ${SX.line}`, background: "linear-gradient(180deg, #0B110D, #070A08)" }}>
        <div style={{ position: "absolute", left: 0, right: 0, top: 24, textAlign: "center", fontFamily: MONO, fontSize: 22, letterSpacing: 6, color: SX.dim }}>KITCHEN</div>
        <div style={{ position: "absolute", left: 380, width: 120, top: 62, height: 6, borderRadius: 3, background: SX.line }} />
      </div>
      <svg width={1080} height={1920} style={{ position: "absolute", left: 0, top: 0 }}>
        {mode === "call" ? <polyline points={callPath.map((q) => q.join(",")).join(" ")} fill="none" stroke={SX.green} strokeWidth={5} strokeDasharray="14 10" strokeDashoffset={-frame * 2} opacity={0.7} /> : null}
      </svg>
      {TABLES.map(([x, y], k) => {
        const alert = mode === "hook" ? ALERTS.find((a) => a[0] === k) : k === 4 ? [4, at(78)] : undefined;
        const aOn = alert && frame >= alert[1] && !(mode === "hook" && site);
        const as = alert ? Math.min(1, sp(alert[1], { damping: 9, stiffness: 240, mass: 0.5 })) : 0;
        const ph = mode === "hook" && site ? Math.min(1, sp(at(10) + k * 3, { damping: 10, stiffness: 220, mass: 0.6 })) : 0;
        const hot = mode === "call" && k === 4;
        return (
          <div key={k} style={{ position: "absolute", left: x - 85, top: y - 85, width: 170, height: 170 }}>
            {[0, 1, 2, 3].map((c) => <div key={c} style={{ position: "absolute", width: 34, height: 34, borderRadius: 10, background: "#1A231D", left: [68, 136, 68, 0][c], top: [0, 68, 136, 68][c] }} />)}
            <div style={{ position: "absolute", left: 25, top: 25, width: 120, height: 120, borderRadius: "50%", background: "#131B16", border: `3px solid ${hot && frame >= at(78) ? SX.red : ph ? SX.green : "#2A372D"}`, display: "grid", placeItems: "center", fontFamily: MONO, fontSize: 26, fontWeight: 700, color: SX.dim, boxShadow: hot && frame >= at(78) ? `0 0 ${24 + Math.sin(frame / 3) * 12}px ${SX.red}` : ph ? `0 0 26px ${SX.green}88` : "none" }}>T{k + 1}</div>
            {aOn ? <div style={{ position: "absolute", left: 112, top: -18, width: 58, height: 58, borderRadius: "50%", background: SX.red, color: "#fff", display: "grid", placeItems: "center", fontFamily: FONT, fontWeight: 800, fontSize: 34, transform: `scale(${as})` }}>{hot ? "🛎" : "!"}</div> : null}
            {ph ? <div style={{ position: "absolute", left: 116, top: -22, width: 46, height: 76, borderRadius: 10, background: "#0A0A0A", border: `3px solid ${SX.green}`, transform: `scale(${ph})`, display: "grid", placeItems: "center", color: SX.green, fontFamily: MONO, fontSize: 14, fontWeight: 700 }}>QR</div> : null}
          </div>
        );
      })}
      <div style={{ position: "absolute", left: wx - 26, top: wy - 26, width: 52, height: 52, borderRadius: "50%", background: mode === "call" ? SX.green : "#E8E8E8", border: "4px solid #050806", boxShadow: `0 0 20px ${mode === "call" ? SX.green : "#fff"}88`, display: "grid", placeItems: "center", fontFamily: FONT, fontWeight: 800, fontSize: 22, color: "#050806" }}>W</div>
      {mode === "call" && frame >= at(79) ? (
        <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 380, top: 1240, width: 250, textAlign: "center", fontFamily: MONO, fontSize: 24, letterSpacing: 2, color: SX.ink, background: "#101712", border: `2px solid ${SX.green}`, borderRadius: 12, padding: "8px 0" }}>WAITER · 00:{String(callSecs).padStart(2, "0")}</div>
      ) : null}
      {mode === "call" && frame >= at(85) ? (
        <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 600, top: 960, background: SX.red, color: "#fff", fontFamily: FONT, fontWeight: 800, fontSize: 24, borderRadius: 12, padding: "8px 14px", transform: `scale(${Math.min(1, sp(at(85)))})` }}>T5 · Call waiter</div>
      ) : null}
    </div>
  );
};

// ---------- kitchen display (tablet) ----------
const Kitchen: React.FC = () => {
  const { frame, sp } = useA();
  const from = L(4), to = L(5);
  if (frame < from - 4 || frame >= to) return null;
  const s = sp(from - 4, { damping: 16, stiffness: 140, mass: 0.8 });
  const out = interpolate(frame, [to - 6, to], [0, 1], CL);
  const moved = frame >= at(57);
  const tm = (base: number) => { const v = base + Math.floor((frame - from) / 30); return `${String(Math.floor(v / 60)).padStart(2, "0")}:${String(v % 60).padStart(2, "0")}`; };
  const T = (id: string, items: string[], time: string, tone: string, glow = false) => (
    <div style={{ background: "#101712", border: `2px solid ${glow ? SX.green : SX.line}`, borderRadius: 14, padding: "12px 14px", fontFamily: FONT, color: SX.ink, boxShadow: glow ? `0 0 24px ${SX.green}66` : "none", marginBottom: 12 }}>
      <div style={{ display: "flex", justifyContent: "space-between", fontFamily: MONO, fontSize: 20, fontWeight: 700 }}><span>{id}</span><span style={{ color: tone }}>{time}</span></div>
      {items.map((x) => <div key={x} style={{ fontSize: 18, color: SX.dim, marginTop: 4 }}>{x}</div>)}
    </div>
  );
  const t5 = T("T5", ["Paneer Tikka ×1", "Butter Naan ×2", "Masala Chaas ×1"], moved ? tm(0) : "NEW", moved ? SX.amber : SX.green, true);
  return (
    <div data-scene="kitchen" style={{ position: "absolute", inset: 0, opacity: Math.min(1, s * 1.6) * (1 - out) }}>
      <div style={{ position: "absolute", left: 80, top: 560, width: 860, height: 700, borderRadius: 40, background: "#0A0A0A", border: "3px solid #2A332C", padding: 16, boxSizing: "border-box", transform: `translateY(${(1 - s) * 260}px)`, boxShadow: `0 40px 100px rgba(0,0,0,.75), 0 0 70px ${SX.green}22` }}>
        <div data-el="card" data-kind="content" style={{ position: "absolute", inset: 16, borderRadius: 26, background: "#070B08", clipPath: "inset(0 round 26px)" }}>
          <div style={{ display: "flex", justifyContent: "space-between", padding: "18px 24px", fontFamily: MONO, fontSize: 20, letterSpacing: 3, color: SX.dim, borderBottom: `1px solid ${SX.line}` }}>
            <span>KITCHEN DISPLAY</span><span style={{ color: SX.green }}>● LIVE · 9:41 PM</span>
          </div>
          <div style={{ display: "flex", gap: 14, padding: 18 }}>
            {[
              ["NEW", SX.green, !moved ? [t5] : []],
              ["COOKING", SX.amber, [...(moved ? [t5] : []), T("T2", ["Veg Biryani ×2", "Raita ×2"], tm(372), SX.amber), T("T3", ["Dal Makhani ×1"], tm(220), SX.amber)]],
              ["READY", SX.green, [T("T1", ["Gulab Jamun ×2"], "✓", SX.green)]],
            ].map(([h, c, list]) => (
              <div key={h as string} style={{ flex: 1 }}>
                <div style={{ fontFamily: MONO, fontSize: 18, letterSpacing: 3, color: c as string, marginBottom: 12 }}>{h as string}</div>
                {list as React.ReactNode[]}
              </div>
            ))}
          </div>
          <div style={{ position: "absolute", left: 18, right: 18, bottom: 16, fontFamily: MONO, fontSize: 18, color: SX.dim, display: "flex", gap: 16 }}>
            {["Tandoor", "Curry", "Drinks"].map((x) => <span key={x} style={{ border: `1px solid ${SX.line}`, borderRadius: 8, padding: "4px 10px" }}>{x}</span>)}
          </div>
                  </div>
      </div>
    </div>
  );
};

// ---------- overview ----------
const ICONS = {
  table: <svg width="74" height="74" viewBox="0 0 24 24" fill="none" stroke={SX.green} strokeWidth="1.6"><rect x="7" y="2" width="10" height="20" rx="2" /><path d="M10 18h4" /><rect x="9.5" y="6" width="5" height="5" /></svg>,
  kitchen: <svg width="74" height="74" viewBox="0 0 24 24" fill="none" stroke={SX.green} strokeWidth="1.6"><rect x="2" y="4" width="20" height="13" rx="2" /><path d="M8 21h8M12 17v4M6 8h4M6 11h6M14 8h4" /></svg>,
  staff: <svg width="74" height="74" viewBox="0 0 24 24" fill="none" stroke={SX.green} strokeWidth="1.6"><circle cx="6" cy="7" r="3" /><circle cx="18" cy="7" r="3" /><circle cx="12" cy="17" r="3" /><path d="M8.5 8.5 10.5 15M15.5 8.5 13.5 15" /></svg>,
};
const Overview: React.FC = () => {
  const { frame, sp, fade } = useA();
  const from = L(9), to = L(10);
  if (frame < from - 2 || frame >= to) return null;
  const out = interpolate(frame, [to - 6, to], [1, 0], CL);
  const all = frame >= at(120);
  const cards: [keyof typeof ICONS, string, number][] = [["table", "TABLE", at(116)], ["kitchen", "KITCHEN", at(117)], ["staff", "STAFF", at(119)]];
  return (
    <div data-scene="overview" style={{ position: "absolute", inset: 0, opacity: fade(from - 2, 8) * out }}>
      <div data-el="card" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 500, height: 840 }}>
        <div style={{ display: "flex", flexDirection: "column", alignItems: "center", transform: `scale(${Math.min(1, 0.5 + 0.5 * sp(from))})` }}>
          <Img src={staticFile(LOGO)} style={{ width: 200, height: 200 }} />
          <div style={{ fontFamily: FONT, fontWeight: 800, fontSize: 64, color: SX.ink, letterSpacing: -1, marginTop: 6 }}>Restaurant</div>
          <div style={{ fontFamily: FONT, fontWeight: 800, fontSize: 64, color: SX.green, letterSpacing: -1, marginTop: -8 }}>QR System</div>
        </div>
        <svg width={880} height={140} style={{ position: "absolute", left: 0, top: 400 }}>
          {[150, 440, 730].map((x, k) => <path key={x} d={`M440 0 C 440 70, ${x} 60, ${x} 140`} stroke={SX.green} strokeWidth={3} fill="none" opacity={frame >= cards[k][2] ? 0.8 : 0} strokeDasharray="10 8" strokeDashoffset={-frame * 1.5} />)}
        </svg>
        <div style={{ position: "absolute", left: 0, right: 0, top: 560, display: "flex", justifyContent: "space-between" }}>
          {cards.map(([ic, label, a]) => (
            <div key={label} style={{ width: 270, height: 230, borderRadius: 26, background: SX.card, border: `2px solid ${all ? SX.green : SX.line}`, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 14, transform: `scale(${Math.min(1, sp(a, { damping: 10, stiffness: 220, mass: 0.6 }))})`, boxShadow: all ? `0 0 40px ${SX.green}55` : "none" }}>
              {ICONS[ic]}
              <div style={{ fontFamily: MONO, fontSize: 26, letterSpacing: 4, color: SX.ink }}>{label}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

// ---------- sound ----------
const SFX: Cue[] = [
  { at: 2, name: "impact", vol: 0.3 },
  ...[2, 4, 6, 8].map((i) => ({ at: at(i), name: "buzz", vol: 0.18 })),
  { at: at(10), name: "whoosh", vol: 0.35 },
  ...[0, 1, 2, 3, 4, 5].map((k) => ({ at: at(10) + k * 3, name: "pop", vol: 0.28 })),
  ...[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((i) => ({ at: L(i) - 3, name: "whoosh", vol: 0.3 })),
  { at: at(21) + 4, name: "message", vol: 0.5 },
  { at: at(23), name: "ui", vol: 0.45 },
  ...[30, 31, 34].map((i) => ({ at: at(i), name: "tick", vol: 0.3 })),
  { at: at(41), name: "click", vol: 0.55 },
  { at: at(41) + 4, name: "success", vol: 0.4 },
  { at: at(44) + 8, name: "whoosh", vol: 0.4 },
  { at: at(55), name: "tick", vol: 0.3 },
  { at: at(57), name: "pop", vol: 0.4 },
  { at: L(5), name: "typing", vol: 0.12, len: 40 },
  { at: at(70), name: "tick", vol: 0.3 },
  { at: at(77), name: "click", vol: 0.55 },
  { at: at(77) + 2, name: "ring", vol: 0.35, len: 40 },
  { at: at(85), name: "pop", vol: 0.4 },
  { at: at(92), name: "pop", vol: 0.35 },
  ...[99, 100, 102].map((i) => ({ at: at(i), name: "click", vol: 0.4 })),
  { at: at(102) + 14, name: "success", vol: 0.45 },
  ...[0, 1, 2, 3, 4].map((k) => ({ at: at(106) - 4 + k * 4, name: "pop", vol: 0.3 })),
  { at: L(9), name: "impact", vol: 0.4 },
  ...[116, 117, 119].map((i) => ({ at: at(i), name: "pop", vol: 0.4 })),
  { at: at(120), name: "success", vol: 0.4 },
  { at: at(127), name: "impact", vol: 0.4 },
  { at: L(10) + 14, name: "typing", vol: 0.3, len: 22 },
  { at: L(10) + 40, name: "message", vol: 0.5 },
];

const STEPS: [string, number, number][] = [
  ["01 SCAN", L(1), at(23)], ["02 MENU", at(23), L(3)], ["03 ORDER", L(3), L(4)], ["04 KITCHEN", L(4), L(5)], ["05 WAIT", L(5), L(6)],
  ["06 CALL", L(6), L(7)], ["07 PAY", L(7), L(8)], ["08 REVIEW", L(8), L(9)],
];

export const Reel: React.FC<ReelProps> = ({ debugSafeZones, probe }) => (
  <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
    <SolvixBackdrop />
    <SoundTrack voice="reels/solvix-restaurant-qr/vo.wav" music="music/digital-clouds.mp3" words={words} cues={SFX} duration={durationInFrames} />
    <CornerBrand handle={myBrand.handle} />
    {STEPS.map(([s, a, b]) => <StepLabel key={s} text={s} at={a} to={b} />)}
    <Floor mode="hook" from={0} to={L(1)} />
    {PHONES.map((p) => <Phone key={p.name} p={p} />)}
    <Kitchen />
    <Floor mode="call" from={at(78)} to={L(7)} />
    <Overview />
    <CommentCTA at={L(10)} keyword="SOFTWARE" question="Details chahiye?" handle={myBrand.handle} sub="Details DM mein" />
    <Captions words={words} lines={LINES} top={CAP_TOP} hi={HI} />
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);
