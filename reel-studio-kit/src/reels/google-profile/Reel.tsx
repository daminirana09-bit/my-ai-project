// Reel "google-profile" (~43 s): "Google Maps pe apni dukaan, FREE — sirf 3 steps" (Google Business Profile).
// Peach "steps" look: big ₹0 card, 3-step tracker, browser mocks, setup-guide CTA, Instagram outro.
// Voice: George (voice:timed). Music "Digital Clouds" + SFX: Mixkit free licences (npm run audio-assets).
// Steps follow Google's help page "Add or claim your Business Profile" (see projects/google-profile/script.md).
import React from "react";
import { Audio, Easing, interpolate, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { StoryOutro } from "../../components/cardstory";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { timing } from "../../timing";
import words from "../../../public/reels/google-profile/captions.words.json";

export const t = timing(words);
export const durationInFrames = t.duration(2.6);
const at = (i: number) => t.at(i, 0);
const LINES = [0, 7, 14, 17, 26, 33, 41, 51, 62, 68, 80, words.length];

const C = { ink: "#1C1714", orange: "#E8572E", soft: "#FBE9E1", card: "#FFFFFF", line: "#EFE3DC", green: "#1E9E5A", blue: "#2F6BFF", muted: "#8C7F78" };
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";
const SERIF = "Instrument Serif, Georgia, serif";

const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    fps,
    sp: (a: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - a, fps, config: cfg }),
    fade: (a: number, len = 6) => interpolate(frame - a, [0, len], [0, 1], CL),
  };
};
const typed = (frame: number, s: string, a: number, b: number) => s.slice(0, Math.round(interpolate(frame, [a, Math.max(a + 1, b)], [0, s.length], CL)));

// sections (frames)
const SEC = {
  zero: [0, at(14)],
  steps: [at(14), at(17)],
  s1: [at(17), at(33)],
  s2: [at(33), at(41)],
  s3: [at(41), at(51)],
  live: [at(51), at(62)],
  extra: [at(62), at(68)],
  guide: [at(68), at(80)],
  web: [at(80), durationInFrames],
} as const;
const OUTRO = durationInFrames - 36;

// ---------- sound ----------
type Cue = { at: number; name: string; vol?: number; len?: number };
const SFX: Cue[] = [
  { at: 0, name: "pop", vol: 0.55 },
  { at: at(4), name: "ui", vol: 0.4 },
  { at: at(6), name: "success", vol: 0.45 },
  { at: at(9), name: "buzz", vol: 0.2 },
  { at: at(12), name: "tick", vol: 0.35 },
  ...[0, 1, 2].map((k) => ({ at: at(14) + 4 + k * 8, name: "pop", vol: 0.45 })),
  { at: at(17) - 3, name: "whoosh", vol: 0.35 },
  { at: at(18), name: "typing", vol: 0.3, len: 30 },
  { at: at(24), name: "typing", vol: 0.3, len: 24 },
  { at: at(26), name: "pop", vol: 0.5 },
  { at: at(31), name: "click", vol: 0.5 },
  { at: at(33) - 3, name: "whoosh", vol: 0.35 },
  ...[34, 35, 36, 39].map((i) => ({ at: at(i), name: "tick", vol: 0.3 })),
  { at: at(41) - 3, name: "whoosh", vol: 0.35 },
  ...[44, 45, 47].map((i) => ({ at: at(i), name: "ui", vol: 0.45 })),
  { at: at(49), name: "click", vol: 0.5 },
  { at: at(51) - 3, name: "whoosh", vol: 0.35 },
  { at: at(51) + 4, name: "stamp", vol: 0.5 },
  { at: at(53), name: "success", vol: 0.5 },
  { at: at(56), name: "pop", vol: 0.45 },
  { at: at(62), name: "pop", vol: 0.45 },
  { at: at(64), name: "tick", vol: 0.35 },
  { at: at(68) - 3, name: "whoosh", vol: 0.35 },
  ...[0, 1, 2].map((k) => ({ at: at(68) + 8 + k * 6, name: "tick", vol: 0.3 })),
  { at: at(74), name: "typing", vol: 0.3, len: 24 },
  { at: at(78), name: "message", vol: 0.55 },
  { at: at(80) - 3, name: "whoosh", vol: 0.35 },
  { at: at(85), name: "pop", vol: 0.55 },
  { at: OUTRO, name: "impact", vol: 0.3 },
];

const SoundTrack: React.FC = () => {
  const { fps } = useVideoConfig();
  const talking = (f: number) => (words as { start: number; end: number }[]).some((w) => f / fps >= w.start - 0.15 && f / fps <= w.end + 0.25);
  return (
    <>
      <Audio src={staticFile("music/digital-clouds.mp3")} volume={(f) => (talking(f) ? 0.09 : 0.22) * interpolate(f, [0, 15], [0.3, 1], CL) * interpolate(f, [durationInFrames - 30, durationInFrames], [1, 0], CL)} />
      <Audio src={staticFile("reels/google-profile/vo.wav")} />
      {SFX.map((c, i) => (
        <Sequence key={i} from={Math.max(0, c.at)} durationInFrames={c.len ?? 60} layout="none">
          <Audio src={staticFile(`sfx/${c.name}.mp3`)} volume={c.vol ?? 0.5} />
        </Sequence>
      ))}
    </>
  );
};

// ---------- pieces ----------
const Pin: React.FC<{ size?: number; color?: string }> = ({ size = 56, color = C.orange }) => (
  <svg width={size} height={size * 1.3} viewBox="0 0 24 32">
    <path d="M12 0C5.4 0 0 5.2 0 11.6 0 20.3 12 32 12 32s12-11.7 12-20.4C24 5.2 18.6 0 12 0z" fill={color} />
    <circle cx="12" cy="11.5" r="4.6" fill="#fff" />
  </svg>
);

const Header: React.FC = () => (
  <div style={{ position: "absolute", left: 70, right: 130, top: 236, display: "flex", alignItems: "center", gap: 16, fontFamily: FONT }}>
    <div style={{ width: 70, height: 70, borderRadius: 18, background: C.card, display: "grid", placeItems: "center", boxShadow: "0 8px 20px rgba(120,60,30,.12)" }}>
      <Pin size={34} />
    </div>
    <div style={{ fontSize: 46, fontWeight: 800, color: C.ink, letterSpacing: -1 }}>Google Business Profile</div>
  </div>
);

const Card: React.FC<{ children: React.ReactNode; style?: React.CSSProperties; from: number; to: number; name: string }> = ({ children, style, from, to, name }) => {
  const { frame, sp } = useA();
  if (frame < from - 4 || frame >= to) return null;
  const s = sp(from - 4, { damping: 16, stiffness: 160, mass: 0.8 });
  const out = interpolate(frame, [to - 6, to], [0, 1], CL);
  return (
    <div
      data-scene={name}
      data-el="card"
      data-kind="content"
      style={{ position: "absolute", left: 70, right: 130, top: 340, minHeight: 420, background: C.card, borderRadius: 34, boxShadow: "0 30px 70px rgba(120,60,30,.16)", padding: 34, fontFamily: FONT, color: C.ink, transform: `translateY(${(1 - s) * 50 - out * 30}px) scale(${0.95 + 0.05 * s})`, opacity: Math.min(1, s * 1.5) * (1 - out), ...style }}
    >
      {children}
    </div>
  );
};

const Tracker: React.FC<{ step: number; doneAt: number[] }> = ({ step, doneAt }) => {
  const { frame, sp } = useA();
  const labels = ["Naam / claim", "Details", "Verify"];
  return (
    <div style={{ marginBottom: 22 }}>
      <div style={{ fontFamily: MONO, fontSize: 20, letterSpacing: 2, color: C.orange }}>SIRF 3 STEPS</div>
      <div style={{ display: "flex", alignItems: "center", marginTop: 12 }}>
        {labels.map((l, k) => {
          const done = frame >= doneAt[k];
          const active = k + 1 === step;
          return (
            <React.Fragment key={l}>
              <div style={{ display: "flex", flexDirection: "column", alignItems: "center", width: 150 }}>
                <span style={{ width: 52, height: 52, borderRadius: "50%", display: "grid", placeItems: "center", fontWeight: 800, fontSize: 24, color: "#fff", background: done ? C.green : active ? C.orange : "#E3D7D0", transform: `scale(${active ? 1 + 0.06 * Math.sin(frame / 5) : 1})` }}>{done ? "✓" : k + 1}</span>
                <span style={{ marginTop: 6, fontSize: 20, fontWeight: 700, color: C.muted }}>{l}</span>
              </div>
              {k < 2 ? <div style={{ flex: 1, height: 4, borderRadius: 2, background: frame >= doneAt[k] ? C.green : "#E3D7D0", marginBottom: 28, transform: `scaleX(${Math.min(1, frame >= doneAt[k] ? sp(doneAt[k]) : 1)})`, transformOrigin: "left" }} /> : null}
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
};

const Browser: React.FC<{ url: string; children: React.ReactNode }> = ({ url, children }) => (
  <div style={{ border: `2px solid ${C.line}`, borderRadius: 22, overflow: "hidden" }}>
    <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "12px 16px", background: "#FAF5F2", borderBottom: `2px solid ${C.line}` }}>
      {["#FF5F57", "#FEBC2E", "#28C840"].map((c) => <span key={c} style={{ width: 14, height: 14, borderRadius: "50%", background: c }} />)}
      <span style={{ marginLeft: 10, fontFamily: MONO, fontSize: 22, color: "#5A4F49" }}>{url}</span>
    </div>
    <div style={{ padding: 22 }}>{children}</div>
  </div>
);

const Field: React.FC<{ label: string; value: string; on: boolean }> = ({ label, value, on }) => (
  <div style={{ display: "flex", alignItems: "center", gap: 14, border: `2px solid ${on ? C.green : C.line}`, borderRadius: 14, padding: "12px 16px", marginTop: 12, background: on ? "#EFF9F3" : "#fff" }}>
    <span style={{ width: 140, fontSize: 22, fontWeight: 700, color: C.muted }}>{label}</span>
    <span style={{ fontSize: 28, fontWeight: 800, flex: 1 }}>{on ? value : ""}</span>
    {on ? <span style={{ color: C.green, fontWeight: 800, fontSize: 26 }}>✓</span> : null}
  </div>
);

// ---------- caption with keyword boxes ----------
const KEY = new Set([1, 4, 6, 9, 13, 15, 18, 24, 31, 34, 39, 42, 47, 58, 62, 64, 70, 74, 80, 85]);
const Caption: React.FC = () => {
  const { frame, fps } = useA();
  const tt = frame / fps;
  const W = words as { word: string; start: number; end: number }[];
  let cur = -1;
  W.forEach((w, i) => { if (w.start <= tt) cur = i; });
  if (cur < 0 || frame >= OUTRO) return null;
  let li = 0;
  for (let i = 0; i < LINES.length - 1; i++) if (cur >= LINES[i]) li = i;
  const lineWords = W.slice(LINES[li], LINES[li + 1]).map((w, k) => ({ ...w, idx: LINES[li] + k }));
  const per = 4;
  const rel = cur - LINES[li];
  const c0 = Math.floor(rel / per) * per;
  const chunk = lineWords.slice(c0, c0 + per);
  return (
    <div data-el="captions" data-kind="caption" style={{ position: "absolute", left: 70, right: 130, top: 990, display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "8px 14px", fontFamily: FONT, fontWeight: 800, fontSize: 58, color: C.ink }}>
      {chunk.map((w) => {
        const shown = w.idx <= cur;
        const p = interpolate(tt - w.start, [0, 0.12], [0, 1], CL);
        const key = KEY.has(w.idx);
        const long = w.word.length > 18;
        return (
          <span key={w.idx} style={{ opacity: shown ? 0.3 + 0.7 * p : 0.2, transform: `translateY(${shown ? (1 - p) * 8 : 8}px)`, background: key && shown ? C.orange : "transparent", color: key && shown ? "#fff" : C.ink, borderRadius: 10, padding: key ? "0 12px" : 0, fontSize: long ? 40 : undefined, whiteSpace: "nowrap" }}>
            {w.word.replace(/[—,.:]+$/u, "")}
          </span>
        );
      })}
    </div>
  );
};

/** Small reference card under the caption (what this step is about). */
const Note: React.FC = () => {
  const { frame, fade } = useA();
  const notes: [number, number, string, string, string][] = [
    [at(3), at(14), "₹0", "Google pe listing", "free"],
    [at(17), at(33), "1", "business.google.com/create", "naam / claim"],
    [at(33), at(41), "2", "Business details", "category · address · phone · website"],
    [at(41), at(51), "3", "Verify", "Google jo tarika de"],
    [at(51), at(68), "✓", "Search + Maps pe live", "photos + timing bhi"],
  ];
  const n = notes.find(([a, b]) => frame >= a && frame < b);
  if (!n) return null;
  return (
    <div data-el="sticker" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 1150, display: "flex", alignItems: "center", gap: 18, background: C.card, borderRadius: 22, padding: "16px 22px", fontFamily: FONT, boxShadow: "0 12px 30px rgba(120,60,30,.12)", opacity: fade(n[0], 6) }}>
      <span style={{ minWidth: 52, height: 52, padding: "0 8px", borderRadius: 26, background: C.orange, color: "#fff", display: "grid", placeItems: "center", fontWeight: 800, fontSize: 26 }}>{n[2]}</span>
      <div>
        <div style={{ fontSize: 28, fontWeight: 800, color: C.ink }}>{n[3]}</div>
        <div style={{ fontSize: 22, fontWeight: 600, color: C.muted }}>{n[4]}</div>
      </div>
    </div>
  );
};

// ---------- reel ----------
export const Reel: React.FC<ReelProps> = ({ debugSafeZones, probe }) => {
  const { frame, sp, fade } = useA();
  const zero = sp(4, { damping: 8, stiffness: 220, mass: 0.6 });
  const fill = interpolate(frame, [4, at(5)], [0, 1], { ...CL, easing: Easing.out(Easing.cubic) });
  const doneAt = [at(33), at(41), at(51)];
  const step = frame >= at(41) ? 3 : frame >= at(33) ? 2 : 1;
  return (
    <div style={{ position: "absolute", inset: 0, background: "linear-gradient(180deg, #F8E6DC 0%, #F1D2C2 100%)", overflow: "hidden" }}>
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(55% 30% at ${50 + Math.sin(frame / 90) * 10}% 35%, rgba(255,255,255,.6), transparent 70%)` }} />
      <SoundTrack />
      <Header />

      {/* ₹0 hero card */}
      <Card name="free" from={0} to={SEC.zero[1]}>
        <div style={{ fontFamily: MONO, fontSize: 22, letterSpacing: 3, color: C.orange, textAlign: "center" }}>GOOGLE MAPS PE APNI DUKAAN</div>
        <div style={{ display: "flex", alignItems: "baseline", justifyContent: "center", gap: 18, marginTop: 10 }}>
          <span style={{ fontSize: 210, fontWeight: 800, letterSpacing: -8, lineHeight: 1, color: C.orange, display: "inline-block", transform: `scale(${Math.min(1.05, 0.4 + 0.6 * zero)})` }}>₹0</span>
        </div>
        <div style={{ textAlign: "center", fontFamily: SERIF, fontStyle: "italic", fontSize: 54, color: C.ink }}>listing fee</div>
        <div style={{ marginTop: 16, height: 10, borderRadius: 5, background: "#F2E6DF" }}>
          <div style={{ width: `${fill * 100}%`, height: "100%", borderRadius: 5, background: C.orange }} />
        </div>
        {frame >= at(6) ? (
          <div style={{ position: "absolute", right: 30, top: 30, background: C.blue, color: "#fff", fontWeight: 800, fontSize: 34, borderRadius: 12, padding: "6px 16px", transform: `rotate(6deg) scale(${Math.min(1, sp(at(6), { damping: 8, stiffness: 240, mass: 0.6 }))})` }}>FREE</div>
        ) : null}
        <div style={{ display: "flex", gap: 14, justifyContent: "center", marginTop: 22 }}>
          {[["✕ koi fees nahi", at(9), C.orange], ["✓ bas Google account", at(12), C.green]].map(([txt, a, col]) => (
            <span key={txt as string} style={{ fontSize: 26, fontWeight: 800, color: col as string, border: `2px solid ${col}`, borderRadius: 12, padding: "8px 14px", opacity: fade(a as number, 4), transform: `scale(${Math.min(1, 0.6 + 0.4 * sp(a as number))})` }}>{txt as string}</span>
          ))}
        </div>
      </Card>

      {/* sirf 3 steps */}
      <Card name="3-steps" from={SEC.steps[0]} to={SEC.steps[1]}>
        <div style={{ textAlign: "center", fontSize: 64, fontWeight: 800, marginTop: 20 }}>
          Sirf <span style={{ background: C.orange, color: "#fff", borderRadius: 14, padding: "0 16px" }}>3 steps</span>
        </div>
        <div style={{ display: "flex", justifyContent: "center", gap: 40, marginTop: 50 }}>
          {[1, 2, 3].map((k) => (
            <span key={k} style={{ width: 110, height: 110, borderRadius: "50%", background: C.soft, border: `4px solid ${C.orange}`, display: "grid", placeItems: "center", fontSize: 52, fontWeight: 800, color: C.orange, transform: `scale(${Math.min(1, sp(at(14) + 4 + (k - 1) * 8, { damping: 9, stiffness: 220, mass: 0.6 }))})` }}>{k}</span>
          ))}
        </div>
      </Card>

      {/* step 1: create / claim */}
      <Card name="step-1" from={SEC.s1[0]} to={SEC.s1[1]}>
        <Tracker step={step} doneAt={doneAt} />
        <Browser url={typed(frame, "business.google.com/create", at(18), at(19))}>
          <div style={{ fontSize: 30, fontWeight: 800 }}>Business ka naam</div>
          <div style={{ marginTop: 12, border: `2px solid ${C.blue}`, borderRadius: 14, padding: "14px 18px", fontSize: 32, fontWeight: 700 }}>
            {typed(frame, "Kulhad Corner", at(24), at(25) + 6)}
            <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1, color: C.blue }}>|</span>
          </div>
          {frame >= at(26) ? (
            <div style={{ marginTop: 14, border: `2px solid ${C.line}`, borderRadius: 14, padding: "14px 18px", display: "flex", alignItems: "center", gap: 14, opacity: fade(at(26), 5) }}>
              <Pin size={30} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 26, fontWeight: 800 }}>Kulhad Corner · Jaipur</div>
                <div style={{ fontSize: 20, color: C.muted, fontWeight: 600 }}>Google pe pehle se hai</div>
              </div>
              <span style={{ background: frame >= at(31) ? C.green : C.orange, color: "#fff", borderRadius: 12, padding: "10px 16px", fontSize: 24, fontWeight: 800, transform: `scale(${frame >= at(31) && frame < at(31) + 6 ? 0.92 : 1})` }}>{frame >= at(31) ? "✓ Claimed" : "Claim karo"}</span>
            </div>
          ) : null}
        </Browser>
      </Card>

      {/* step 2: details */}
      <Card name="step-2" from={SEC.s2[0]} to={SEC.s2[1]}>
        <Tracker step={step} doneAt={doneAt} />
        <Field label="Category" value="Cafe" on={frame >= at(34)} />
        <Field label="Address" value="C-Scheme, Jaipur" on={frame >= at(35)} />
        <Field label="Phone" value="98•• ••• 410" on={frame >= at(36)} />
        <Field label="Website" value="kulhadcorner.in" on={frame >= at(39)} />
      </Card>

      {/* step 3: verify */}
      <Card name="step-3" from={SEC.s3[0]} to={SEC.s3[1]}>
        <Tracker step={step} doneAt={doneAt} />
        <div style={{ fontSize: 30, fontWeight: 800 }}>Verify kaise karein?</div>
        <div style={{ display: "flex", gap: 14, marginTop: 16 }}>
          {[["Phone", at(44)], ["Email", at(45)], ["Video", at(47)]].map(([l, a], k) => {
            const chosen = frame >= at(49) && k === 0;
            return (
              <div key={l as string} style={{ flex: 1, border: `3px solid ${chosen ? C.green : C.line}`, background: chosen ? "#EFF9F3" : "#fff", borderRadius: 18, padding: "22px 10px", textAlign: "center", opacity: fade(a as number, 4), transform: `translateY(${(1 - Math.min(1, sp(a as number))) * 20}px)` }}>
                <div style={{ fontSize: 30, fontWeight: 800 }}>{l as string}</div>
                <div style={{ fontSize: 20, color: chosen ? C.green : C.muted, fontWeight: 700, marginTop: 4 }}>{chosen ? "✓ chuna" : "option"}</div>
              </div>
            );
          })}
        </div>
        <div style={{ marginTop: 16, fontSize: 22, color: C.muted, fontWeight: 600, opacity: fade(at(48), 5) }}>Kaunsa tarika milega, ye Google tay karta hai.</div>
      </Card>

      {/* live on maps */}
      <Card name="live" from={SEC.live[0]} to={SEC.extra[1]} style={{ padding: 0, clipPath: "inset(0 round 34px)" }}>
        <div style={{ position: "relative", height: 260, background: "#EAF2E8", backgroundImage: "linear-gradient(#D5E4D2 2px, transparent 2px), linear-gradient(90deg, #D5E4D2 2px, transparent 2px)", backgroundSize: "44px 44px" }}>
          <div style={{ position: "absolute", left: "50%", top: 40, marginLeft: -28, transform: `translateY(${(1 - Math.min(1, sp(at(51) + 4, { damping: 8, stiffness: 200, mass: 0.6 }))) * -120}px)` }}>
            <Pin size={56} />
          </div>
          {frame >= at(53) ? (
            <div style={{ position: "absolute", right: 20, top: 20, background: C.green, color: "#fff", fontWeight: 800, fontSize: 26, borderRadius: 12, padding: "6px 14px", transform: `scale(${Math.min(1, sp(at(53)))})` }}>VERIFIED ✓</div>
          ) : null}
        </div>
        <div style={{ padding: 26 }}>
          <div style={{ fontSize: 38, fontWeight: 800 }}>Kulhad Corner</div>
          <div style={{ fontSize: 24, color: C.muted, fontWeight: 600 }}>Cafe · C-Scheme, Jaipur {frame >= at(64) ? <b style={{ color: C.green }}>· Open · 8 AM – 11 PM</b> : null}</div>
          <div style={{ display: "flex", gap: 10, marginTop: 14 }}>
            {["Directions", "Call", "Website"].map((b, k) => (
              <span key={b} style={{ fontSize: 24, fontWeight: 800, color: C.blue, border: `2px solid ${C.blue}55`, borderRadius: 999, padding: "8px 16px", opacity: fade(at(56) + k * 3, 4) }}>{b}</span>
            ))}
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8, marginTop: 16 }}>
            {[0, 1, 2, 3].map((k) => (
              <div key={k} style={{ height: 90, borderRadius: 12, background: frame >= at(62) + k * 3 ? `linear-gradient(${k * 50}deg, #E8572E99, #F2A33A99)` : "#F2ECE8", transition: "none" }} />
            ))}
          </div>
        </div>
      </Card>

      {/* guide CTA */}
      <Card name="guide" from={SEC.guide[0]} to={SEC.guide[1]}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div style={{ fontSize: 40, fontWeight: 800 }}>
            Full setup <span style={{ fontFamily: SERIF, fontStyle: "italic", fontWeight: 400, color: C.orange, fontSize: 48 }}>guide</span>
          </div>
          <span style={{ background: C.blue, color: "#fff", fontWeight: 800, fontSize: 24, borderRadius: 10, padding: "4px 12px" }}>FREE</span>
        </div>
        {["business.google.com/create pe naam / claim", "Category, address, phone, website", "Verify + photos + timing"].map((s, k) => (
          <div key={s} style={{ display: "flex", alignItems: "center", gap: 14, marginTop: 16, fontSize: 26, fontWeight: 700, opacity: fade(at(68) + 8 + k * 6, 4) }}>
            <span style={{ width: 40, height: 40, borderRadius: "50%", background: C.orange, color: "#fff", display: "grid", placeItems: "center", fontWeight: 800 }}>{k + 1}</span>
            <span style={{ flex: 1 }}>{s}</span>
            <span style={{ color: C.green, fontWeight: 800 }}>✓</span>
          </div>
        ))}
        <div style={{ marginTop: 24, display: "flex", alignItems: "center", gap: 12, background: "#FAF5F2", borderRadius: 999, padding: "12px 20px", fontSize: 30, fontWeight: 800 }}>
          <span style={{ flex: 1 }}>Comment “{typed(frame, "GOOGLE", at(74), at(74) + 18)}”</span>
          <span style={{ width: 52, height: 52, borderRadius: "50%", background: C.ink, color: "#fff", display: "grid", placeItems: "center" }}>➤</span>
        </div>
        {frame >= at(78) ? (
          <div style={{ marginTop: 14, fontSize: 24, fontWeight: 700, color: C.green, transform: `scale(${Math.min(1, sp(at(78)))})`, transformOrigin: "left" }}>✓ Guide DM mein bhej di — {myBrand.handle}</div>
        ) : null}
      </Card>

      {/* website soft sell */}
      <Card name="website" from={SEC.web[0]} to={OUTRO + 10}>
        <div style={{ fontSize: 46, fontWeight: 800, textAlign: "center" }}>
          Website bhi <span style={{ fontFamily: SERIF, fontStyle: "italic", fontWeight: 400, color: C.orange, fontSize: 56 }}>chahiye?</span>
        </div>
        <div style={{ marginTop: 24, border: `2px solid ${C.line}`, borderRadius: 22, overflow: "hidden", opacity: fade(at(83), 6) }}>
          <div style={{ height: 150, background: "linear-gradient(135deg, #E8572E, #F2A33A)", display: "grid", placeItems: "center", color: "#fff", fontWeight: 800, fontSize: 40 }}>kulhadcorner.in</div>
          <div style={{ display: "flex", justifyContent: "space-between", padding: "16px 20px", fontSize: 26, fontWeight: 700 }}>
            <span>Menu · Map · Booking</span>
            <span style={{ color: C.green }}>✓ live</span>
          </div>
        </div>
        <div style={{ marginTop: 22, textAlign: "center", fontSize: 32, fontWeight: 800, color: C.orange, opacity: fade(at(85), 4), transform: `scale(${Math.min(1, 0.6 + 0.4 * sp(at(85)))})` }}>Hum bana denge · {myBrand.handle}</div>
      </Card>

      <Caption />
      <Note />
      <StoryOutro at={OUTRO} handle={myBrand.handle} />
      {debugSafeZones ? <SafeZones /> : null}
      {probe ? <LayoutProbe /> : null}
    </div>
  );
};
