// Richer motion for reels: scene transitions, animated backdrop, kinetic titles, progress
// rings, a typing search mock, a price slash, a phone that builds a website, a burst and a
// comment-typing CTA. All of them follow the brand (src/brand.ts) and carry data-el so
// `npm run check` measures them like the basic components.
import React from "react";
import { Easing, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { useBrand } from "../brand-context";

const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const DANGER = "#E5484D"; // "no / wrong" marks
const WHATSAPP = "#25D366";
const MONO = "JetBrains Mono, monospace";
const SERIF = "Instrument Serif, Georgia, serif";
const clean = (s: string) => s.toLowerCase().replace(/[^\p{L}\p{N}]/gu, "");
const useT = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const sp = (at: number, config = { damping: 14, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - at, fps, config });
  const fade = (at: number, len = 5) => interpolate(frame - at, [0, len], [0, 1], CL);
  return { frame, fps, sp, fade };
};

/** Soft colour blobs drifting behind everything, plus a slowly moving dot grid. */
export const Backdrop: React.FC<{ grid?: boolean }> = ({ grid = true }) => {
  const b = useBrand();
  const { frame } = useT();
  const t = frame / 30;
  const blobs = [
    { c: b.colors.accent, x: 18 + Math.sin(t * 0.32) * 10, y: 22 + Math.cos(t * 0.27) * 8, r: 48 },
    { c: b.colors.accent2, x: 84 + Math.cos(t * 0.23) * 8, y: 62 + Math.sin(t * 0.31) * 10, r: 44 },
    { c: b.colors.accent, x: 60 + Math.sin(t * 0.19) * 14, y: 96, r: 40 },
  ];
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
      <div style={{ position: "absolute", inset: 0, background: blobs.map((o) => `radial-gradient(circle at ${o.x}% ${o.y}%, ${o.c}22, transparent ${o.r}%)`).join(",") }} />
      {grid ? <div
        style={{
          position: "absolute",
          inset: 0,
          opacity: 0.55,
          backgroundImage: `radial-gradient(${b.colors.text}1A 2.4px, transparent 2.6px)`,
          backgroundSize: "54px 54px",
          backgroundPosition: `0px ${-frame * 0.5}px`,
          maskImage: "linear-gradient(to bottom, black 0%, transparent 75%)",
          WebkitMaskImage: "linear-gradient(to bottom, black 0%, transparent 75%)",
        }}
      /> : null}
    </div>
  );
};

/** Like SceneBlock, but slides + un-blurs in and lifts + blurs out. Leave out `name` for an overlay that spans scenes. */
export const Scene: React.FC<{ name?: string; from: number; to: number; children: React.ReactNode }> = ({ name, from, to, children }) => {
  const { frame, sp } = useT();
  if (frame < from || frame >= to) return null;
  const enter = sp(from, { damping: 18, stiffness: 130, mass: 0.8 });
  const exit = interpolate(frame, [to - 7, to], [0, 1], { ...CL, easing: Easing.in(Easing.cubic) });
  return (
    <div
      {...(name ? { "data-scene": name } : {})}
      style={{
        position: "absolute",
        inset: 0,
        opacity: interpolate(frame - from, [0, 5], [0, 1], CL) * (1 - exit),
        transform: `translateY(${(1 - enter) * 60 - exit * 30}px)`,
        filter: `blur(${(1 - enter) * 8 + exit * 8}px)`,
      }}
    >
      {children}
    </div>
  );
};

/** Headline whose words drop in one by one; `accent` words get the accent colour and a marker swipe. */
export const WordsIn: React.FC<{ text: string; at: number; accent?: string[]; size?: number; stagger?: number; showHandle?: boolean; serif?: boolean }> = ({
  text,
  at,
  accent = [],
  size = 110,
  stagger = 3,
  showHandle = false,
  serif = false,
}) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const keys = new Set(accent.map(clean));
  return (
    <div data-el="title" data-kind="content" style={{ fontFamily: b.fontFamily, color: b.colors.text }}>
      {showHandle ? <div style={{ fontSize: 34, fontWeight: 600, opacity: 0.6 * fade(at), marginBottom: 12 }}>{b.handle}</div> : null}
      <div style={{ display: "flex", flexWrap: "wrap", gap: `0 ${size * 0.26}px`, fontSize: size, fontWeight: 800, lineHeight: 1.06, letterSpacing: -2 }}>
        {text.split(" ").map((w, i) => {
          const a = at + i * stagger;
          const s = sp(a);
          const isKey = keys.has(clean(w));
          const swipe = interpolate(frame - a - 5, [0, 8], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
          return (
            <span key={i} style={{ position: "relative", display: "inline-block", opacity: fade(a, 4), transform: `translateY(${(1 - s) * 70}px) rotate(${(1 - s) * 9}deg)`, transformOrigin: "left bottom" }}>
              {isKey && !serif ? (
                <span style={{ position: "absolute", left: 0, right: 0, bottom: size * 0.06, height: size * 0.32, background: `${b.colors.accent2}40`, borderRadius: 8, transform: `scaleX(${swipe})`, transformOrigin: "left" }} />
              ) : null}
              <span
                style={{
                  position: "relative",
                  color: isKey ? b.colors.accent : b.colors.text,
                  ...(isKey && serif ? { fontFamily: SERIF, fontStyle: "italic", fontWeight: 400, fontSize: size * 1.14, letterSpacing: 0 } : {}),
                }}
              >
                {w}
              </span>
            </span>
          );
        })}
      </div>
    </div>
  );
};

/** Card with a progress ring that fills to `value`% while the number counts up. */
export const Ring: React.FC<{ value: number; at: number; countFrames?: number; size?: number; label: React.ReactNode; sub?: React.ReactNode }> = ({
  value,
  at,
  countFrames = 24,
  size = 300,
  label,
  sub,
}) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  const v = interpolate(frame - at, [0, countFrames], [0, value], { ...CL, easing: Easing.out(Easing.cubic) });
  const done = sp(at + countFrames, { damping: 9, stiffness: 220, mass: 0.6 });
  const stroke = size * 0.11;
  const r = (size - stroke) / 2;
  const C = 2 * Math.PI * r;
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{
        opacity: fade(at),
        transform: `translateY(${(1 - s) * 40}px) scale(${0.8 + 0.2 * s})`,
        transformOrigin: "left center",
        background: b.colors.surface,
        borderRadius: b.radius,
        boxShadow: b.boxShadow,
        padding: 44,
        fontFamily: b.fontFamily,
        color: b.colors.text,
        display: "flex",
        alignItems: "center",
        gap: 40,
      }}
    >
      <div style={{ position: "relative", width: size, height: size, flex: "none", transform: `scale(${1 + 0.06 * Math.sin(done * Math.PI)})` }}>
        <svg width={size} height={size} style={{ transform: "rotate(-90deg)" }}>
          <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={`${b.colors.text}12`} strokeWidth={stroke} />
          <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={b.colors.accent} strokeWidth={stroke} strokeLinecap="round" strokeDasharray={C} strokeDashoffset={C * (1 - v / 100)} />
        </svg>
        <div style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", fontSize: size * 0.3, fontWeight: 800, color: b.colors.accent, fontVariantNumeric: "tabular-nums" }}>
          {Math.round(v)}%
        </div>
      </div>
      <div>
        <div style={{ fontSize: 50, fontWeight: 800, lineHeight: 1.1 }}>{label}</div>
        {sub ? <div style={{ fontSize: 30, fontWeight: 600, opacity: 0.55, marginTop: 18, lineHeight: 1.3 }}>{sub}</div> : null}
      </div>
    </div>
  );
};

const Icon: React.FC<{ ok: boolean; size?: number; draw?: number }> = ({ ok, size = 56, draw = 1 }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" style={{ flex: "none" }}>
    <circle cx="12" cy="12" r="12" fill={ok ? WHATSAPP : DANGER} />
    {ok ? (
      <path d="M6.5 12.5l3.6 3.6 7.4-8" fill="none" stroke="#fff" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round" strokeDasharray="20" strokeDashoffset={20 * (1 - draw)} />
    ) : (
      <path d="M8 8l8 8M16 8l-8 8" fill="none" stroke="#fff" strokeWidth="2.8" strokeLinecap="round" strokeDasharray="12" strokeDashoffset={12 * (1 - draw)} />
    )}
  </svg>
);

/** A search box that types `query`, then a "not found" row that shakes and a competitor row that slides in. */
export const SearchMock: React.FC<{ at: number; query: string; typeTo: number; missAt: number; hitAt: number; you: string; them: string }> = ({
  at,
  query,
  typeTo,
  missAt,
  hitAt,
  you,
  them,
}) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  const n = typeTo <= at + 4 ? query.length : Math.round(interpolate(frame, [at + 4, typeTo], [0, query.length], CL)); // typeTo at/before start = already typed
  const caret = frame < missAt && Math.floor(frame / 8) % 2 === 0;
  const miss = sp(missAt);
  const shake = frame >= missAt ? Math.sin((frame - missAt) * 1.7) * 16 * Math.max(0, 1 - (frame - missAt) / 16) : 0;
  const hit = sp(hitAt);
  const row: React.CSSProperties = { display: "flex", alignItems: "center", gap: 24, borderRadius: Math.max(12, b.radius * 0.6), padding: "22px 26px", marginTop: 22 };
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{
        opacity: fade(at),
        transform: `translateY(${(1 - s) * 40}px) scale(${0.85 + 0.15 * s})`,
        background: b.colors.surface,
        borderRadius: b.radius,
        boxShadow: b.boxShadow,
        padding: 40,
        fontFamily: b.fontFamily,
        color: b.colors.text,
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: 20, border: `4px solid ${b.colors.text}22`, borderRadius: 999, padding: "20px 30px", fontSize: 42, fontWeight: 600 }}>
        <svg width="40" height="40" viewBox="0 0 24 24" style={{ flex: "none" }}>
          <circle cx="10" cy="10" r="7" fill="none" stroke={b.colors.accent} strokeWidth="3" />
          <path d="M15.5 15.5 21 21" stroke={b.colors.accent} strokeWidth="3" strokeLinecap="round" />
        </svg>
        <span style={{ whiteSpace: "nowrap" }}>
          {query.slice(0, n)}
          <span style={{ opacity: caret ? 1 : 0, color: b.colors.accent }}>|</span>
        </span>
      </div>
      {you ? <div style={{ ...row, opacity: fade(missAt), transform: `translateX(${shake}px) scale(${0.9 + 0.1 * miss})`, border: `3px dashed ${DANGER}66`, background: `${DANGER}0D` }}>
        <Icon ok={false} draw={interpolate(frame - missAt, [2, 10], [0, 1], CL)} />
        <div>
          <div style={{ fontSize: 42, fontWeight: 800 }}>{you}</div>
          <div style={{ fontSize: 30, fontWeight: 600, color: DANGER }}>Koi result nahi mila</div>
        </div>
      </div> : null}
      {them ? <div style={{ ...row, opacity: fade(hitAt), transform: `translateX(${-(1 - hit) * 260}px)`, background: `${WHATSAPP}14`, border: `3px solid ${WHATSAPP}55` }}>
        <Icon ok draw={interpolate(frame - hitAt, [3, 12], [0, 1], CL)} />
        <div>
          <div style={{ fontSize: 42, fontWeight: 800 }}>{them}</div>
          <div style={{ fontSize: 30, fontWeight: 600, opacity: 0.65 }}>4.8 ★ · Open now · Website</div>
        </div>
      </div> : null}
    </div>
  );
};

/** "₹50,000?" in a card; a red line slashes through it, then the card shakes. */
export const PriceSlash: React.FC<{ text: string; at: number; slashAt: number }> = ({ text, at, slashAt }) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  const line = interpolate(frame - slashAt, [0, 7], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
  const shake = frame >= slashAt + 5 ? Math.sin((frame - slashAt) * 1.5) * 10 * Math.max(0, 1 - (frame - slashAt - 5) / 14) : 0;
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{
        position: "relative",
        display: "inline-block",
        opacity: fade(at) * interpolate(frame - slashAt, [0, 10], [1, 0.75], CL),
        transform: `translateY(${(1 - s) * 50}px) rotate(${(1 - s) * -8 + shake * 0.2}deg) translateX(${shake}px)`,
        background: b.colors.surface,
        borderRadius: b.radius,
        boxShadow: b.boxShadow,
        padding: "26px 48px",
        fontFamily: b.fontFamily,
        color: b.colors.text,
        fontSize: 140,
        fontWeight: 800,
        letterSpacing: -3,
      }}
    >
      {text}
      <div style={{ position: "absolute", left: 30, right: 30, top: "50%", height: 16, marginTop: -8, borderRadius: 8, background: DANGER, transform: `rotate(-7deg) scaleX(${line})`, transformOrigin: "left center" }} />
    </div>
  );
};

/** Radial burst of lines and dots at frame `at` (decoration, not measured). */
export const Burst: React.FC<{ at: number; x: number; y: number; radius?: number }> = ({ at, x, y, radius = 220 }) => {
  const b = useBrand();
  const { frame } = useT();
  const p = interpolate(frame - at, [0, 18], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
  if (frame < at || p >= 1) return null;
  return (
    <svg width={radius * 2} height={radius * 2} style={{ position: "absolute", left: x - radius, top: y - radius, pointerEvents: "none" }}>
      {Array.from({ length: 14 }, (_, i) => {
        const a = (i / 14) * Math.PI * 2;
        const r0 = radius * (0.25 + 0.55 * p);
        const r1 = radius * (0.35 + 0.65 * p);
        const c = i % 2 ? b.colors.accent : b.colors.accent2;
        return i % 3 === 0 ? (
          <circle key={i} cx={radius + Math.cos(a) * r1} cy={radius + Math.sin(a) * r1} r={10 * (1 - p)} fill={c} />
        ) : (
          <line key={i} x1={radius + Math.cos(a) * r0} y1={radius + Math.sin(a) * r0} x2={radius + Math.cos(a) * r1} y2={radius + Math.sin(a) * r1} stroke={c} strokeWidth={9 * (1 - p)} strokeLinecap="round" />
        );
      })}
    </svg>
  );
};

type SiteKey = "nav" | "hero" | "rates" | "whatsapp" | "map";

/** A phone that slides in and assembles a small business website, section by section. `focus` pulses a section on a word. */
export const PhoneSite: React.FC<{
  at: number;
  build: Record<SiteKey, number>;
  focus?: { at: number; key: SiteKey | "phone" }[];
  name: string;
  heroTitle?: string;
  heroSub?: string;
  rows?: [string, string][];
  button?: string;
}> = ({
  at,
  build,
  focus = [],
  name,
  heroTitle = "Taaza mithai, roz",
  heroSub = "Since 1998 · Home delivery",
  rows = [
    ["Kaju katli", "₹880/kg"],
    ["Rasgulla", "₹320/kg"],
    ["Samosa", "₹20"],
  ],
  button = "WhatsApp karo",
}) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at, { damping: 16, stiffness: 120, mass: 0.9 });
  const pulse = (key: SiteKey | "phone") => {
    let v = 0;
    for (const f of focus) if (f.key === key && frame >= f.at) v = Math.max(v, Math.sin(Math.min(1, (frame - f.at) / 14) * Math.PI));
    return v;
  };
  const section = (key: SiteKey, children: React.ReactNode, style: React.CSSProperties = {}) => {
    const k = sp(build[key]);
    const shimmer = 0.5 + 0.5 * Math.sin(frame / 4);
    const p = pulse(key);
    return (
      <div style={{ position: "relative", marginTop: 14 }}>
        <div style={{ position: "absolute", inset: 0, borderRadius: 16, background: `${b.colors.text}${shimmer > 0.5 ? "14" : "0C"}`, opacity: 1 - fade(build[key], 6) }} />
        <div style={{ opacity: fade(build[key], 6), transform: `scale(${Math.min(1, 0.9 + 0.1 * k)})`, boxShadow: p > 0 ? `0 0 0 ${6 * p}px ${b.colors.accent2}` : "none", borderRadius: 16, ...style }}>
          {children}
        </div>
      </div>
    );
  };
  const pin = sp(build.map + 6, { damping: 8, stiffness: 200, mass: 0.6 });
  const ph = pulse("phone");
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{
        boxSizing: "border-box",
        width: 340,
        height: 650,
        borderRadius: 56,
        border: `14px solid ${b.colors.text}`,
        background: b.colors.surface,
        boxShadow: b.boxShadow,
        padding: "34px 16px 16px",
        overflow: "hidden",
        fontFamily: b.fontFamily,
        color: b.colors.text,
        opacity: fade(at),
        transform: `translateX(${(1 - s) * 420}px) rotate(${(1 - s) * 14 + ph * 2}deg) scale(${1 + 0.04 * ph})`,
        position: "relative",
      }}
    >
      <div style={{ position: "absolute", top: 10, left: "50%", width: 92, height: 22, marginLeft: -46, borderRadius: 20, background: b.colors.text }} />
      <div style={{ position: "absolute", top: 8, left: 24, fontSize: 18, fontWeight: 800 }}>9:41</div>
      {section(
        "nav",
        <div style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 21, fontWeight: 800, padding: "4px 2px" }}>
          <span style={{ width: 26, height: 26, borderRadius: "50%", background: b.colors.accent }} />
          {name}
          <span style={{ marginLeft: "auto", width: 24, height: 14, borderTop: `3px solid ${b.colors.text}`, borderBottom: `3px solid ${b.colors.text}` }} />
        </div>,
      )}
      {section(
        "hero",
        <div style={{ height: 150, borderRadius: 16, background: `linear-gradient(135deg, ${b.colors.accent}, ${b.colors.accent2})`, color: "#fff", padding: 18, display: "flex", flexDirection: "column", justifyContent: "flex-end" }}>
          <div style={{ fontSize: 26, fontWeight: 800, lineHeight: 1.1 }}>{heroTitle}</div>
          <div style={{ fontSize: 16, fontWeight: 600, opacity: 0.85 }}>{heroSub}</div>
        </div>,
      )}
      {section(
        "rates",
        <div style={{ border: `2px solid ${b.colors.text}18`, borderRadius: 16, padding: "10px 14px", fontSize: 17, fontWeight: 600 }}>
          {rows.map(([a, r]) => (
            <div key={a} style={{ display: "flex", justifyContent: "space-between", padding: "3px 0" }}>
              <span>{a}</span>
              <span style={{ color: b.colors.accent, fontWeight: 800 }}>{r}</span>
            </div>
          ))}
        </div>,
      )}
      {section(
        "whatsapp",
        <div style={{ background: WHATSAPP, color: "#fff", borderRadius: 999, padding: "13px 0", textAlign: "center", fontSize: 20, fontWeight: 800 }}>{button}</div>,
      )}
      {section(
        "map",
        <div
          style={{
            position: "relative",
            height: 112,
            borderRadius: 16,
            background: `${b.colors.accent}10`,
            backgroundImage: `linear-gradient(${b.colors.text}10 2px, transparent 2px), linear-gradient(90deg, ${b.colors.text}10 2px, transparent 2px)`,
            backgroundSize: "28px 28px",
          }}
        >
          <svg width="40" height="52" viewBox="0 0 24 32" style={{ position: "absolute", left: "50%", top: 18, marginLeft: -20, transform: `translateY(${(1 - pin) * -40}px)` }}>
            <path d="M12 0C5.4 0 0 5.2 0 11.6 0 20.3 12 32 12 32s12-11.7 12-20.4C24 5.2 18.6 0 12 0z" fill={DANGER} />
            <circle cx="12" cy="11.5" r="4.6" fill="#fff" />
          </svg>
        </div>,
      )}
    </div>
  );
};

/** One checklist row: the tick draws, the text slides in. */
export const Tick: React.FC<{ at: number; children: React.ReactNode; first?: boolean; ok?: boolean }> = ({ at, children, first, ok = true }) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 18, marginTop: first ? 0 : 26, opacity: fade(at), transform: `translateX(${(1 - s) * -50}px)`, fontSize: 40, fontWeight: 800, lineHeight: 1.12, color: b.colors.text }}>
      <Icon ok={ok} size={50} draw={interpolate(frame - at, [3, 12], [0, 1], CL)} />
      <span>{children}</span>
    </div>
  );
};

/** Closing card: lead line, a comment box that types the keyword, a send tap, then the comment appears with a heart. */
export const CommentCTA: React.FC<{ at: number; keyword: string; lead: string; sub: string; typeAt: number; sendAt: number }> = ({ at, keyword, lead, sub, typeAt, sendAt }) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  const n = Math.round(interpolate(frame, [typeAt, sendAt - 2], [0, keyword.length], CL));
  const sent = frame >= sendAt;
  const tap = interpolate(frame - sendAt, [0, 12], [0, 1], CL);
  const posted = sp(sendAt + 4);
  const heart = sp(sendAt + 14, { damping: 7, stiffness: 240, mass: 0.5 });
  const glow = 1 + 0.04 * Math.sin(frame / 5);
  return (
    <div
      data-el="endcard"
      data-kind="content"
      style={{
        opacity: fade(at),
        transform: `translateY(${(1 - s) * 50}px) scale(${0.85 + 0.15 * s})`,
        background: b.colors.surface,
        borderRadius: b.radius,
        boxShadow: b.boxShadow,
        padding: "48px 44px",
        fontFamily: b.fontFamily,
        color: b.colors.text,
        textAlign: "center",
      }}
    >
      <div style={{ fontSize: 58, fontWeight: 800 }}>{lead}</div>
      <div style={{ display: "flex", alignItems: "center", gap: 18, marginTop: 34, border: `4px solid ${b.colors.text}1F`, borderRadius: 999, padding: "14px 14px 14px 32px", textAlign: "left" }}>
        <span style={{ flex: 1, fontSize: 44, fontWeight: 800, color: sent ? `${b.colors.text}55` : b.colors.text, letterSpacing: 2 }}>
          {sent ? "Add a comment…" : keyword.slice(0, n) || <span style={{ opacity: 0.4, letterSpacing: 0 }}>Add a comment…</span>}
        </span>
        <span style={{ position: "relative", width: 76, height: 76, borderRadius: "50%", background: b.colors.accent, display: "grid", placeItems: "center", transform: `scale(${sent ? 1 - 0.15 * Math.sin(tap * Math.PI) : 1})`, boxShadow: sent && tap < 1 ? `0 0 0 ${tap * 22}px ${b.colors.accent}${Math.round((1 - tap) * 120).toString(16).padStart(2, "0")}` : "none" }}>
          <svg width="36" height="36" viewBox="0 0 24 24">
            <path d="M12 19V5M5 11l7-7 7 7" fill="none" stroke={b.colors.bg} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 20, marginTop: 28, opacity: fade(sendAt + 4), transform: `translateY(${(1 - posted) * 30}px)`, textAlign: "left" }}>
        <span style={{ width: 64, height: 64, borderRadius: "50%", background: b.colors.accent2, flex: "none" }} />
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 28, fontWeight: 600, opacity: 0.55 }}>aap · abhi</div>
          <div style={{ display: "inline-block", marginTop: 4, background: b.colors.accent, color: b.colors.bg, fontSize: 48, fontWeight: 800, padding: "6px 22px", borderRadius: Math.max(12, b.radius * 0.5), boxShadow: `0 0 0 ${(glow - 1) * 200}px ${b.colors.accent}33`, letterSpacing: 2 }}>
            {keyword}
          </div>
        </div>
        <span style={{ fontSize: 56, color: DANGER, transform: `scale(${Math.min(1, heart)}) rotate(${(1 - Math.min(1, heart)) * -30}deg)`, flex: "none" }}>♥</span>
      </div>
      <div style={{ fontSize: 40, fontWeight: 700, marginTop: 30, opacity: 0.7 * fade(sendAt + 10) }}>{sub}</div>
      <div style={{ fontSize: 38, fontWeight: 800, marginTop: 10, color: b.colors.accent, opacity: fade(sendAt + 14) }}>{b.handle}</div>
    </div>
  );
};

/** Small shop with a "no website" tag; a customer walks out of frame. */
export const WalkAway: React.FC<{ at: number; leaveAt: number }> = ({ at, leaveAt }) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  const walk = interpolate(frame, [leaveAt - 10, leaveAt + 26], [0, 1], { ...CL, easing: Easing.inOut(Easing.quad) });
  const bob = Math.abs(Math.sin(frame / 3.2)) * 10 * (walk > 0 && walk < 1 ? 1 : 0);
  return (
    <div style={{ position: "relative", width: 900, height: 300 }}>
      <div
        data-el="card"
        data-kind="content"
        style={{
          position: "absolute",
          left: 0,
          top: 0,
          width: 320,
          opacity: fade(at),
          transform: `translateY(${(1 - s) * 40}px) scale(${0.8 + 0.2 * s})`,
          background: b.colors.surface,
          borderRadius: b.radius,
          boxShadow: b.boxShadow,
          overflow: "hidden",
          fontFamily: b.fontFamily,
        }}
      >
        <div style={{ height: 52, background: `repeating-linear-gradient(90deg, ${b.colors.accent} 0 40px, ${b.colors.surface} 40px 80px)` }} />
        <div style={{ padding: "18px 22px 22px", fontSize: 38, fontWeight: 800, color: b.colors.text }}>Aapki dukaan</div>
        <div style={{ margin: "0 22px 22px", display: "inline-block", fontSize: 26, fontWeight: 800, color: "#fff", background: DANGER, borderRadius: 10, padding: "6px 14px" }}>No website</div>
      </div>
      <svg width="90" height="150" viewBox="0 0 60 100" style={{ position: "absolute", left: 380 + walk * 480, top: 120 - bob, opacity: fade(at + 6) * (1 - interpolate(walk, [0.75, 1], [0, 1], CL)) }}>
        <circle cx="30" cy="16" r="14" fill={b.colors.text} />
        <rect x="14" y="34" width="32" height="40" rx="14" fill={b.colors.accent} />
        <rect x="16" y="70" width="10" height="28" rx="5" fill={b.colors.text} transform={`rotate(${Math.sin(frame / 3.2) * 18 * (walk > 0 && walk < 1 ? 1 : 0)} 21 72)`} />
        <rect x="34" y="70" width="10" height="28" rx="5" fill={b.colors.text} transform={`rotate(${-Math.sin(frame / 3.2) * 18 * (walk > 0 && walk < 1 ? 1 : 0)} 39 72)`} />
      </svg>
      <svg width="200" height="40" style={{ position: "absolute", left: 470, top: 210, opacity: fade(leaveAt - 10) * (1 - walk * 0.6) }}>
        <path d="M0 20h170M150 4l20 16-20 16" fill="none" stroke={`${b.colors.text}55`} strokeWidth="6" strokeDasharray="14 12" strokeDashoffset={-frame * 2} strokeLinecap="round" />
      </svg>
    </div>
  );
};


/** Code-comment style scene label (top left) and a "voice: AI" tag (top right). Decoration, not measured. */
export const SceneLabel: React.FC<{ text: string; at: number; tag?: string }> = ({ text, at, tag = "voice: AI" }) => {
  const b = useBrand();
  const { fade } = useT();
  const o = fade(at, 8);
  return (
    <>
      <div style={{ position: "absolute", left: 60, top: 150, fontFamily: MONO, fontSize: 26, color: `${b.colors.text}80`, opacity: o }}>{text}</div>
      <div style={{ position: "absolute", right: 130, top: 140, fontFamily: MONO, fontSize: 22, color: `${b.colors.text}80`, border: `2px solid ${b.colors.text}26`, borderRadius: 999, padding: "6px 18px", opacity: o }}>{tag}</div>
    </>
  );
};

/** Bold warning badge with a hard shadow, e.g. "PROBLEM". */
export const Badge: React.FC<{ text: string; at: number; color?: string }> = ({ text, at, color = "#F5C542" }) => {
  const b = useBrand();
  const { sp, fade } = useT();
  const s = sp(at, { damping: 10, stiffness: 220, mass: 0.6 });
  return (
    <div
      data-el="badge"
      data-kind="content"
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: 12,
        opacity: fade(at, 3),
        transform: `scale(${0.5 + 0.5 * s})`,
        transformOrigin: "left center",
        background: color,
        color: b.colors.text,
        border: `4px solid ${b.colors.text}`,
        boxShadow: `6px 6px 0 ${b.colors.text}`,
        borderRadius: 14,
        padding: "10px 22px",
        fontFamily: b.fontFamily,
        fontSize: 40,
        fontWeight: 800,
        letterSpacing: 1,
      }}
    >
      <svg width="34" height="34" viewBox="0 0 24 24">
        <path d="M12 2 1 22h22L12 2z" fill={b.colors.text} />
        <rect x="11" y="9" width="2" height="7" fill={color} />
        <rect x="11" y="17.5" width="2" height="2" fill={color} />
      </svg>
      {text}
    </div>
  );
};

/** Captions as a dark "live transcript" bubble: label + waveform, words light up as they are spoken. */
export const VoiceBubble: React.FC<{ words: { word: string; start: number; end: number }[]; mouth: number[]; keywords?: string[]; perChunk?: number; label?: string; size?: number }> = ({
  words,
  mouth,
  keywords = [],
  perChunk = 5,
  label = "voice · AI",
  size = 54,
}) => {
  const b = useBrand();
  const { frame, fps, sp } = useT();
  const t = frame / fps;
  let current = -1;
  for (let i = 0; i < words.length; i++) {
    if (words[i].start <= t) current = i;
    else break;
  }
  if (current < 0) return null;
  const chunkStart = Math.floor(current / perChunk) * perChunk;
  const chunk = words.slice(chunkStart, chunkStart + perChunk);
  const last = chunk[chunk.length - 1];
  if (t > last.end + 0.5 && chunkStart + perChunk >= words.length) return null;
  const keys = new Set(keywords.map(clean));
  const enter = sp(Math.round(words[chunkStart].start * fps), { damping: 16, stiffness: 220, mass: 0.6 });
  const level = mouth[frame] ?? 0;
  return (
    <div data-el="captions" data-kind="caption" style={{ display: "flex", justifyContent: "flex-end" }}>
      <div
        style={{
          maxWidth: 860,
          background: b.colors.text,
          color: b.colors.bg,
          borderRadius: b.radius,
          boxShadow: `0 20px 50px ${b.colors.text}40`,
          padding: "22px 34px 26px",
          fontFamily: b.fontFamily,
          transform: `translateY(${(1 - enter) * 26}px)`,
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 14, fontFamily: MONO, fontSize: 24, color: `${b.colors.bg}99`, marginBottom: 8 }}>
          <span style={{ width: 14, height: 14, borderRadius: "50%", background: b.colors.accent }} />
          {label}
          <span style={{ display: "flex", alignItems: "center", gap: 5, height: 30, marginLeft: 6 }}>
            {[0.6, 1, 0.75, 0.9, 0.5].map((k, i) => (
              <span key={i} style={{ width: 6, borderRadius: 3, background: b.colors.accent, height: 6 + 24 * Math.min(1, level * k * (0.7 + 0.3 * Math.sin(frame / 2 + i))) }} />
            ))}
          </span>
        </div>
        <div style={{ display: "flex", flexWrap: "wrap", gap: "0 0.28em", fontSize: size, fontWeight: 800, lineHeight: 1.22 }}>
          {chunk.map((w, i) => {
            const idx = chunkStart + i;
            const spoken = idx <= current;
            const isKey = keys.has(clean(w.word));
            const now = idx === current && t <= w.end + 0.1;
            return (
              <span key={idx} style={{ color: isKey || now ? b.colors.accent : b.colors.bg, opacity: spoken ? 1 : 0.28 }}>
                {w.word}
              </span>
            );
          })}
        </div>
      </div>
    </div>
  );
};

/** Comment list that fills with the keyword, then a DM preview card. */
export const CommentsCTA: React.FC<{ at: number; keyword: string; dmAt: number; dmTitle: string; dmLines: string[]; tag?: string }> = ({ at, keyword, dmAt, dmTitle, dmLines, tag = "₹5,000" }) => {
  const b = useBrand();
  const { sp, fade } = useT();
  const people = [
    { name: "@ra***", c: b.colors.accent },
    { name: "@pr***", c: b.colors.accent2 },
    { name: "@ne***", c: "#F5A524" },
    { name: "aap · abhi", c: b.colors.text },
  ];
  const card: React.CSSProperties = { background: b.colors.surface, borderRadius: b.radius, boxShadow: b.boxShadow, fontFamily: b.fontFamily, color: b.colors.text };
  const s = sp(at);
  const d = sp(dmAt);
  const tg = sp(dmAt + 8, { damping: 8, stiffness: 220, mass: 0.6 });
  return (
    <div>
      <div data-el="card" data-kind="content" style={{ ...card, padding: "30px 36px", opacity: fade(at), transform: `translateY(${(1 - s) * 40}px)` }}>
        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 32, fontWeight: 800, marginBottom: 10 }}>
          Comments <span style={{ fontSize: 26, fontWeight: 600, opacity: 0.45 }}>Newest</span>
        </div>
        {people.map((p, i) => {
          const a = at + 6 + i * 7;
          const r = sp(a);
          const heart = sp(a + 5, { damping: 7, stiffness: 240, mass: 0.5 });
          return (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 20, padding: "12px 0", opacity: fade(a, 4), transform: `translateX(${-(1 - r) * 40}px)` }}>
              <span style={{ width: 54, height: 54, borderRadius: "50%", background: p.c, flex: "none" }} />
              <span style={{ fontFamily: MONO, fontSize: 28, opacity: 0.6, width: 230 }}>{p.name}</span>
              <span style={{ background: b.colors.accent, color: "#fff", fontSize: 30, fontWeight: 800, padding: "6px 16px", borderRadius: 10, letterSpacing: 1 }}>{keyword}</span>
              <span style={{ marginLeft: "auto", fontSize: 40, color: i === 3 ? b.colors.accent : `${b.colors.text}30`, transform: `scale(${Math.min(1, heart)})` }}>♥</span>
            </div>
          );
        })}
      </div>
      <div style={{ position: "relative", marginTop: 34 }}>
        <div data-el="card" data-kind="content" style={{ ...card, padding: "26px 32px", opacity: fade(dmAt), transform: `translateY(${(1 - d) * 50}px)`, border: `3px solid ${b.colors.accent}55` }}>
          <div style={{ fontFamily: MONO, fontSize: 24, opacity: 0.55 }}>{b.handle} · New message</div>
          <div style={{ display: "flex", alignItems: "center", gap: 22, marginTop: 12 }}>
            <span style={{ width: 84, height: 84, borderRadius: 18, background: b.colors.text, display: "grid", placeItems: "center", flex: "none" }}>
              <svg width="44" height="44" viewBox="0 0 24 24"><g stroke={b.colors.accent} strokeWidth="2.6" strokeLinecap="round">{[0, 30, 60, 90, 120, 150].map((r) => <line key={r} x1="12" y1="3" x2="12" y2="21" transform={`rotate(${r} 12 12)`} />)}</g></svg>
            </span>
            <div>
              <div style={{ fontSize: 40, fontWeight: 800 }}>{dmTitle}</div>
              <div style={{ fontFamily: MONO, fontSize: 24, opacity: 0.6, marginTop: 4 }}>{dmLines.map((l) => `✓ ${l}`).join("  ")}</div>
            </div>
          </div>
        </div>
        <div data-overlap-ok="card" style={{ position: "absolute", right: -6, top: -26, transform: `rotate(${8 * tg}deg) scale(${Math.min(1, tg)})`, background: b.colors.accent2, color: "#fff", fontFamily: b.fontFamily, fontWeight: 800, fontSize: 30, padding: "8px 18px", borderRadius: 10, border: `3px solid ${b.colors.text}`, boxShadow: `4px 4px 0 ${b.colors.text}` }}>
          {tag}
        </div>
      </div>
    </div>
  );
};


/** "Today's appointments" card: slots pop in one by one, empty ones shake a little. */
export const ScheduleCard: React.FC<{ at: number; title: string; slots: { time: string; who?: string }[]; stagger?: number }> = ({ at, title, slots, stagger = 5 }) => {
  const b = useBrand();
  const { frame, sp, fade } = useT();
  const s = sp(at);
  return (
    <div
      data-el="card"
      data-kind="content"
      style={{ opacity: fade(at), transform: `translateY(${(1 - s) * 40}px)`, background: b.colors.surface, borderRadius: b.radius, boxShadow: b.boxShadow, padding: "30px 34px", fontFamily: b.fontFamily, color: b.colors.text }}
    >
      <div style={{ fontSize: 32, fontWeight: 800, marginBottom: 12 }}>{title}</div>
      {slots.map((sl, i) => {
        const a = at + 6 + i * stagger;
        const r = sp(a);
        const empty = !sl.who;
        const wob = empty && frame > a + 8 ? Math.sin((frame - a) / 3) * 3 * Math.max(0, 1 - (frame - a - 8) / 30) : 0;
        return (
          <div key={i} style={{ display: "flex", alignItems: "center", gap: 22, padding: "14px 0", borderTop: i ? `2px solid ${b.colors.text}10` : "none", opacity: fade(a, 4), transform: `translateX(${-(1 - r) * 40 + wob}px)` }}>
            <span style={{ fontFamily: MONO, fontSize: 28, opacity: 0.6, width: 120 }}>{sl.time}</span>
            {empty ? (
              <span style={{ flex: 1, height: 44, borderRadius: 10, border: `3px dashed ${DANGER}66`, display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800, color: DANGER }}>khaali</span>
            ) : (
              <span style={{ flex: 1, fontSize: 32, fontWeight: 800 }}>{sl.who}</span>
            )}
          </div>
        );
      })}
    </div>
  );
};
