// "Website makeover" reel: a phone builds a small-business website step by step while a narrator talks,
// then the outcome (full-screen B-roll + bookings filling up), a care line, and a checklist CTA.
// Every animation has a sound; lo-fi music ducks under the voice. Used by cafe-makeover and hotel-makeover.
import React from "react";
import { Audio, Easing, interpolate, OffthreadVideo, random, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";

export type MakeoverConfig = {
  kind: "cafe" | "hotel";
  theme: { bg: string; bg2: string; cream: string; accent: string; ink: string };
  audio: string; // voice file in public/
  words: { word: string; start: number; end: number }[];
  starts: number[]; // first word index of each of the 10 lines
  cues: {
    domainEnd: number; // word where the domain finishes typing
    tagline: number;
    items: number[]; // one word index per menu/room item
    info: [number, number, number]; // location, timing, reviews
    book: number;
    wa: number;
    link: number;
    full: number;
    care: number;
    keyword: number;
  };
  coldOpen: { label: string; icon: "table" | "bed" };
  intro: string; // italic line over the blank site
  domain: string;
  brand: string;
  tagline: string;
  items: { name: string; price: string }[];
  info: [string, string, string];
  bookLabel: string;
  waMsg: string;
  igHandle: string;
  grid: { title: string; units: string[]; stamp: string };
  care: [string, string];
  keyword: string;
  checklist: { title: string; points: string[] };
  clips: { hero: string; full: string; care: string; fullPos?: string; carePos?: string; heroPos?: string };
  music: string;
  handle: string;
};

export const LEAD = 36; // 1.2 s cold open before the voice starts
const CL = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const FONT = "Inter, sans-serif";
const MONO = "JetBrains Mono, monospace";
const SERIF = "Instrument Serif, Georgia, serif";
const WA = "#25D366";

export const makeoverDuration = (c: MakeoverConfig) => LEAD + Math.round((c.words[c.words.length - 1].end + 2.4) * 30);

const useA = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return {
    frame,
    sp: (a: number, cfg = { damping: 13, stiffness: 170, mass: 0.7 }) => spring({ frame: frame - a, fps, config: cfg }),
    fade: (a: number, len = 6) => interpolate(frame - a, [0, len], [0, 1], CL),
  };
};

const Icon: React.FC<{ kind: "table" | "bed"; color: string; size?: number }> = ({ kind, color, size = 120 }) =>
  kind === "table" ? (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" stroke={color} strokeWidth="3" strokeLinecap="round">
      <ellipse cx="24" cy="20" rx="14" ry="5" />
      <path d="M24 25v14M16 42h16M6 18v16h6M42 18v16h-6" />
    </svg>
  ) : (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" stroke={color} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5 38V12M5 28h38v10M43 28v-6a6 6 0 0 0-6-6H22v12" />
      <rect x="9" y="19" width="9" height="6" rx="3" />
    </svg>
  );

export const MakeoverReel: React.FC<{ c: MakeoverConfig }> = ({ c }) => {
  const { frame, sp, fade } = useA();
  const { fps } = useVideoConfig();
  const T = c.theme;
  const W = c.words.map((w) => ({ ...w, start: w.start + LEAD / fps, end: w.end + LEAD / fps }));
  const at = (i: number) => Math.round(W[Math.min(i, W.length - 1)].start * fps);
  const lineAt = (l: number) => at(c.starts[l]);
  const dur = makeoverDuration(c);
  const outroAt = dur - 40;
  const phoneTo = lineAt(7);
  const igAt = lineAt(6);

  // ---------- sound design ----------
  type Cue = { at: number; name: string; vol?: number; len?: number };
  const cues: Cue[] = [
    { at: 0, name: "stamp", vol: 0.55 },
    { at: 6, name: "buzz", vol: 0.18 },
    { at: lineAt(0) - 3, name: "whoosh", vol: 0.35 },
    { at: lineAt(1), name: "ui", vol: 0.5 },
    { at: lineAt(1) + 8, name: "typing", vol: 0.32, len: Math.max(20, at(c.cues.domainEnd) - lineAt(1)) },
    { at: lineAt(2), name: "whoosh", vol: 0.35 },
    { at: at(c.cues.tagline), name: "pop", vol: 0.55 },
    ...c.cues.items.map((i) => ({ at: at(i), name: "tick", vol: 0.28 })),
    ...c.cues.info.map((i) => ({ at: at(i), name: "ui", vol: 0.5 })),
    { at: at(c.cues.book), name: "pop", vol: 0.6 },
    { at: at(c.cues.wa), name: "message", vol: 0.55 },
    { at: igAt, name: "whoosh", vol: 0.35 },
    { at: at(c.cues.link), name: "click", vol: 0.5 },
    { at: lineAt(7) - 3, name: "whoosh", vol: 0.4 },
    ...c.grid.units.map((_, k) => ({ at: lineAt(7) + 8 + k * 5, name: "ui", vol: 0.3 })),
    { at: at(c.cues.full), name: "stamp", vol: 0.75 },
    { at: at(c.cues.full) + 1, name: "impact", vol: 0.35 },
    { at: lineAt(8) - 3, name: "whoosh", vol: 0.4 },
    { at: lineAt(8) + 4, name: "ui", vol: 0.4 },
    { at: at(c.cues.care), name: "pop", vol: 0.5 },
    { at: lineAt(9) - 3, name: "whoosh", vol: 0.35 },
    { at: lineAt(9) + 6, name: "pop", vol: 0.5 },
    { at: at(c.cues.keyword), name: "typing", vol: 0.3, len: 26 },
    { at: at(c.cues.keyword) + 28, name: "message", vol: 0.55 },
    { at: outroAt, name: "impact", vol: 0.3 },
  ];
  const talking = (f: number) => W.some((w) => f / fps >= w.start - 0.15 && f / fps <= w.end + 0.25);

  // ---------- helpers ----------
  const typed = (s: string, a: number, b: number) => s.slice(0, Math.round(interpolate(frame, [a, Math.max(a + 1, b)], [0, s.length], CL)));
  const step = (frame >= igAt ? 6 : frame >= lineAt(5) ? 5 : frame >= lineAt(4) ? 4 : frame >= lineAt(3) ? 3 : frame >= lineAt(2) ? 2 : frame >= lineAt(1) ? 1 : 0);
  const scrollTargets = [0, 0, 0, 230, 470, 470];
  const scroll = (() => {
    let y = 0;
    for (let s = 1; s <= 5; s++) {
      const a = lineAt(s);
      const p = interpolate(frame, [a, a + 14], [0, 1], { ...CL, easing: Easing.inOut(Easing.cubic) });
      y = y + (scrollTargets[s] - y) * p;
    }
    return y;
  })();
  const pillText = ["", "STEP 1 · DOMAIN", "STEP 2 · HERO", c.kind === "cafe" ? "STEP 3 · MENU + PRICE" : "STEP 3 · ROOMS + PRICE", "STEP 4 · INFO", "STEP 5 · BOOKING", "STEP 6 · INSTAGRAM"][step];

  // ---------- caption strip ----------
  const t = frame / fps;
  let cur = -1;
  W.forEach((w, i) => { if (w.start <= t) cur = i; });
  const per = 4;
  const c0 = Math.max(0, Math.floor(Math.max(cur, 0) / per) * per);
  const capWords = cur >= 0 && t <= W[W.length - 1].end + 0.4 ? W.slice(c0, c0 + per) : [];

  const phoneVisible = frame >= lineAt(0) - 4 && frame < phoneTo;
  const phoneIn = sp(lineAt(0) - 4, { damping: 16, stiffness: 120, mass: 0.9 });
  const phoneOut = interpolate(frame, [phoneTo - 8, phoneTo], [0, 1], CL);

  return (
    <div style={{ position: "absolute", inset: 0, background: `linear-gradient(180deg, ${T.bg} 0%, ${T.bg2} 100%)`, overflow: "hidden" }}>
      {/* audio */}
      <Audio src={staticFile(c.music)} volume={(f) => (talking(f) ? 0.1 : 0.24) * interpolate(f, [0, 15], [0.4, 1], CL) * interpolate(f, [dur - 30, dur], [1, 0], CL)} />
      <Sequence from={LEAD} layout="none">
        <Audio src={staticFile(c.audio)} />
      </Sequence>
      {cues.filter((q) => q.at >= 0).map((q, i) => (
        <Sequence key={i} from={q.at} durationInFrames={q.len ?? 60} layout="none">
          <Audio src={staticFile(`sfx/${q.name}.mp3`)} volume={q.vol ?? 0.5} />
        </Sequence>
      ))}

      {/* warm glow */}
      <div style={{ position: "absolute", inset: 0, background: `radial-gradient(50% 30% at ${50 + Math.sin(frame / 80) * 12}% 40%, ${T.accent}22, transparent 70%)` }} />

      {/* top bar */}
      <div style={{ position: "absolute", left: 60, right: 60, top: 140, display: "flex", justifyContent: "space-between", fontFamily: MONO, fontSize: 24, letterSpacing: 2 }}>
        <span style={{ color: T.accent }}>WEBSITE MAKEOVER · EP 01</span>
        <span style={{ color: T.cream, opacity: 0.7 }}>SOLVIX WEB</span>
      </div>

      {/* cold open */}
      {frame < lineAt(0) + 2 ? (
        <div data-scene="cold-open" style={{ position: "absolute", inset: 0, opacity: 1 - fade(lineAt(0) - 4, 6) }}>
          <div data-el="card" data-kind="content" style={{ position: "absolute", left: 60, right: 120, top: 520, display: "flex", flexDirection: "column", alignItems: "center" }}>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 160px)", gap: 30, opacity: 0.55 }}>
              {Array.from({ length: 6 }, (_, i) => (
                <div key={i} style={{ transform: `translateY(${Math.sin(frame / 10 + i) * 2}px)` }}>
                  <Icon kind={c.coldOpen.icon} color={T.cream} size={160} />
                </div>
              ))}
            </div>
            <div style={{ marginTop: 50, fontFamily: FONT, fontWeight: 800, fontSize: 92, color: T.accent, letterSpacing: -2, whiteSpace: "nowrap", transform: `scale(${0.6 + 0.4 * Math.min(1, sp(0, { damping: 9, stiffness: 260, mass: 0.6 }))}) rotate(-3deg)`, textShadow: "0 10px 40px rgba(0,0,0,.5)" }}>{c.coldOpen.label}</div>
            <div style={{ marginTop: 10, fontFamily: MONO, fontSize: 30, color: T.cream, background: "rgba(0,0,0,.45)", padding: "6px 16px", borderRadius: 8, opacity: fade(8) }}>website nahi hai, toh?</div>
          </div>
        </div>
      ) : null}

      {/* the phone that builds the site */}
      {phoneVisible ? (
        <div data-scene="build" style={{ position: "absolute", inset: 0, opacity: 1 - phoneOut }}>
          {pillText ? (
            <div style={{ position: "absolute", left: 0, right: 0, top: 236, display: "flex", justifyContent: "center" }}>
              <div key={pillText} style={{ background: T.accent, color: T.ink, fontFamily: MONO, fontWeight: 600, fontSize: 24, letterSpacing: 2, padding: "6px 16px", borderRadius: 8 }}>{pillText}</div>
            </div>
          ) : null}
          {step === 0 ? (
            <div data-el="title" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 250, textAlign: "center", fontFamily: SERIF, fontStyle: "italic", fontSize: 58, color: T.cream, opacity: fade(lineAt(0)) }}>
              {c.intro}
              <div style={{ fontSize: 40, color: T.accent }}>ep. 01</div>
            </div>
          ) : null}
          <div
            {...(step >= 1 ? { "data-el": "card", "data-kind": "content" } : {})}
            style={{ position: "absolute", left: 290, top: 400, width: 500, height: 960, borderRadius: 60, background: "#0A0A0A", border: "3px solid #2A2A2A", padding: 14, boxShadow: "0 40px 100px rgba(0,0,0,.6)", transform: `translateY(${(1 - phoneIn) * 300}px)`, opacity: Math.min(1, phoneIn * 2) }}
          >
            <div style={{ position: "absolute", top: 24, left: "50%", width: 120, height: 30, marginLeft: -60, borderRadius: 20, background: "#000", zIndex: 5 }} />
            <div style={{ position: "relative", width: "100%", height: "100%", borderRadius: 48, clipPath: "inset(0 round 48px)", background: "#FFFDF8", fontFamily: FONT, color: "#1B1B1B" }}>
              {/* website */}
              <div style={{ position: "absolute", inset: 0, opacity: 1 - fade(igAt, 8) }}>
                <div style={{ position: "absolute", left: 0, right: 0, top: 0, transform: `translateY(${-scroll}px)` }}>
                  {/* address bar */}
                  <div style={{ margin: "66px 18px 14px", border: "2px solid #E3DED3", borderRadius: 999, padding: "12px 18px", fontFamily: MONO, fontSize: 24, color: "#444", minHeight: 52 }}>
                    {step >= 1 ? typed(c.domain, lineAt(1) + 8, at(c.cues.domainEnd)) : ""}
                    <span style={{ opacity: Math.floor(frame / 8) % 2 ? 0 : 1 }}>|</span>
                  </div>
                  {/* hero */}
                  <div style={{ margin: "0 18px", height: 360, borderRadius: 26, overflow: "hidden", position: "relative", background: "#DDD3C2" }}>
                    {step >= 2 ? (
                      <Sequence from={lineAt(2)} layout="none">
                        <OffthreadVideo src={staticFile(c.clips.hero)} muted startFrom={30} style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", opacity: fade(lineAt(2), 10) }} />
                      </Sequence>
                    ) : (
                      <div style={{ position: "absolute", inset: 0, background: `repeating-linear-gradient(45deg, #E8E0D2 0 20px, #E1D8C8 20px 40px)` }} />
                    )}
                    <div style={{ position: "absolute", left: 0, right: 0, bottom: 0, padding: "70px 20px 18px", background: "linear-gradient(transparent, rgba(0,0,0,.7))", color: "#fff", opacity: fade(at(c.cues.tagline), 6) }}>
                      <div style={{ fontSize: 38, fontWeight: 800 }}>{c.brand}</div>
                      <div style={{ fontSize: 24, fontWeight: 600, opacity: 0.9 }}>{c.tagline}</div>
                    </div>
                  </div>
                  {/* menu / rooms */}
                  <div style={{ margin: "22px 18px 0" }}>
                    <div style={{ fontSize: 26, fontWeight: 800, opacity: step >= 3 ? 1 : 0.15 }}>{c.kind === "cafe" ? "Menu" : "Rooms"}</div>
                    {c.items.map((it, k) => {
                      const a = at(c.cues.items[k]);
                      return (
                        <div key={it.name} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "14px 4px", borderBottom: "2px solid #EFE9DE", opacity: step >= 3 ? 0.25 + 0.75 * fade(a, 4) : 0.15 }}>
                          <span style={{ fontSize: 28, fontWeight: 700 }}>{it.name}</span>
                          <span style={{ fontSize: 26, fontWeight: 800, background: T.accent, color: T.ink, borderRadius: 10, padding: "4px 12px", transform: `scale(${frame >= a ? Math.min(1, 0.5 + 0.5 * sp(a, { damping: 9, stiffness: 240, mass: 0.6 })) : 0.6})`, opacity: frame >= a ? 1 : 0 }}>{it.price}</span>
                        </div>
                      );
                    })}
                  </div>
                  {/* info */}
                  <div style={{ margin: "22px 18px 0", background: "#F4EFE6", borderRadius: 22, padding: "8px 18px" }}>
                    {c.info.map((txt, k) => {
                      const a = at(c.cues.info[k]);
                      return (
                        <div key={txt} style={{ display: "flex", gap: 14, alignItems: "center", padding: "12px 0", opacity: step >= 4 ? 0.25 + 0.75 * fade(a, 4) : 0.15, transform: `translateX(${step >= 4 ? (1 - Math.min(1, sp(a))) * -20 : 0}px)` }}>
                          <span style={{ width: 40, height: 40, borderRadius: 12, background: T.accent, color: T.ink, display: "grid", placeItems: "center", fontWeight: 800, fontSize: 22 }}>{["⌖", "◷", "★"][k]}</span>
                          <span style={{ fontSize: 26, fontWeight: 700 }}>{txt}</span>
                        </div>
                      );
                    })}
                  </div>
                  <div style={{ height: 220 }} />
                </div>
                {/* sticky booking button */}
                {frame >= at(c.cues.book) ? (
                  <div style={{ position: "absolute", left: 18, right: 18, bottom: 26, transform: `translateY(${(1 - Math.min(1, sp(at(c.cues.book)))) * 120}px)` }}>
                    {frame >= at(c.cues.wa) ? (
                      <div style={{ marginBottom: 12, marginLeft: "auto", width: "82%", background: "#DCF8C6", borderRadius: 18, padding: "12px 16px", fontSize: 24, fontWeight: 700, transform: `scale(${Math.min(1, sp(at(c.cues.wa)))})`, transformOrigin: "right bottom" }}>
                        {c.waMsg} <span style={{ color: "#34B7F1" }}>✓✓</span>
                      </div>
                    ) : null}
                    <div style={{ background: WA, color: "#fff", borderRadius: 18, padding: "18px 0", textAlign: "center", fontSize: 30, fontWeight: 800, boxShadow: `0 0 0 ${6 + 6 * Math.sin(frame / 5)}px ${WA}33` }}>{c.bookLabel}</div>
                  </div>
                ) : null}
              </div>
              {/* instagram bio */}
              {frame >= igAt ? (
                <div style={{ position: "absolute", inset: 0, padding: "80px 22px", opacity: fade(igAt, 8), background: "#fff" }}>
                  <div style={{ textAlign: "center", fontSize: 26, fontWeight: 800 }}>{c.igHandle}</div>
                  <div style={{ display: "flex", alignItems: "center", gap: 20, marginTop: 24 }}>
                    <div style={{ width: 110, height: 110, borderRadius: "50%", background: `linear-gradient(135deg, ${T.accent}, ${T.bg2})`, border: "4px solid #fff", boxShadow: "0 0 0 3px #DD2A7B" }} />
                    <div style={{ display: "flex", gap: 22, fontSize: 22, textAlign: "center" }}>
                      {[["142", "posts"], ["3,820", "followers"], ["210", "following"]].map(([n, l]) => (
                        <div key={l}><div style={{ fontWeight: 800, fontSize: 28 }}>{n}</div>{l}</div>
                      ))}
                    </div>
                  </div>
                  <div style={{ marginTop: 18, fontSize: 24, lineHeight: 1.45 }}>
                    <b>{c.brand}</b><br />{c.tagline}<br />{c.info[1]}
                  </div>
                  <div style={{ marginTop: 8, display: "inline-block", fontSize: 26, fontWeight: 800, color: "#00376B", background: frame >= at(c.cues.link) ? `${T.accent}55` : "transparent", borderRadius: 8, padding: "2px 8px", transform: `scale(${frame >= at(c.cues.link) && frame < at(c.cues.link) + 8 ? 1.08 : 1})` }}>🔗 {c.domain}</div>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 4, marginTop: 30 }}>
                    {Array.from({ length: 9 }, (_, i) => <div key={i} style={{ aspectRatio: "1", background: `linear-gradient(${i * 40}deg, ${T.accent}66, ${T.bg2}55)` }} />)}
                  </div>
                </div>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      {/* outcome: full-screen B-roll + bookings filling */}
      {frame >= lineAt(7) - 4 && frame < lineAt(8) ? (
        <div data-scene="outcome" style={{ position: "absolute", inset: 0, opacity: fade(lineAt(7) - 4, 8) }}>
          <Sequence from={lineAt(7) - 4} layout="none">
            <OffthreadVideo src={staticFile(c.clips.full)} muted startFrom={30} style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", objectPosition: c.clips.fullPos ?? "50% 50%", filter: "brightness(.55)", transform: `scale(${interpolate(frame, [lineAt(7), lineAt(8)], [1.05, 1.15], CL)})` }} />
          </Sequence>
          <div data-el="card" data-kind="content" style={{ position: "absolute", left: 90, right: 150, top: 480, background: "rgba(16,12,10,.82)", border: `2px solid ${T.accent}55`, borderRadius: 28, padding: 28, fontFamily: FONT, color: T.cream }}>
            <div style={{ display: "flex", justifyContent: "space-between", fontFamily: MONO, fontSize: 22, letterSpacing: 2 }}>
              <span>{c.grid.title}</span>
              <span style={{ color: T.accent }}>{Math.max(0, c.grid.units.length - Math.max(0, Math.floor((frame - lineAt(7) - 8) / 5) + 1))} left</span>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12, marginTop: 18 }}>
              {c.grid.units.map((u, k) => {
                const on = frame >= lineAt(7) + 8 + k * 5;
                return (
                  <div key={u} style={{ height: 70, borderRadius: 12, display: "grid", placeItems: "center", fontSize: 26, fontWeight: 800, background: on ? T.accent : "transparent", color: on ? T.ink : T.cream, border: `2px solid ${on ? T.accent : "#ffffff33"}`, transform: `scale(${on && frame < lineAt(7) + 12 + k * 5 ? 1.08 : 1})` }}>{u}</div>
                );
              })}
            </div>
          </div>
          {frame >= at(c.cues.full) ? (
            <div data-el="sticker" data-kind="content" data-overlap-ok="card" style={{ position: "absolute", left: 60, right: 120, top: 860, display: "flex", justifyContent: "center" }}>
              <div style={{ transform: `rotate(-6deg) scale(${1.7 - 0.7 * Math.min(1, sp(at(c.cues.full), { damping: 9, stiffness: 260, mass: 0.6 }))})`, background: T.accent, color: T.ink, fontFamily: FONT, fontWeight: 800, fontSize: 96, letterSpacing: 2, padding: "6px 30px", borderRadius: 14, boxShadow: "0 20px 60px rgba(0,0,0,.5)" }}>{c.grid.stamp}</div>
            </div>
          ) : null}
        </div>
      ) : null}

      {/* care line over B-roll */}
      {frame >= lineAt(8) - 4 && frame < lineAt(9) ? (
        <div data-scene="care" style={{ position: "absolute", inset: 0, opacity: fade(lineAt(8) - 4, 8) }}>
          <Sequence from={lineAt(8) - 4} layout="none">
            <OffthreadVideo src={staticFile(c.clips.care)} muted startFrom={20} style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", objectPosition: c.clips.carePos ?? "50% 50%", filter: "brightness(.6)", transform: `scale(${interpolate(frame, [lineAt(8), lineAt(9)], [1.04, 1.12], CL)})` }} />
          </Sequence>
          <div data-el="title" data-kind="content" style={{ position: "absolute", left: 70, right: 130, top: 660, textAlign: "center", fontFamily: FONT, fontWeight: 800, color: T.cream, opacity: fade(lineAt(8) + 4, 6) }}>
            <div style={{ fontSize: 56 }}>{c.care[0]}</div>
            <div style={{ display: "inline-block", marginTop: 12, fontSize: 72, color: T.ink, background: T.accent, padding: "4px 24px", borderRadius: 14, opacity: fade(at(c.cues.care), 4), transform: `scale(${Math.min(1, 0.6 + 0.4 * sp(at(c.cues.care)))})` }}>{c.care[1]}</div>
          </div>
        </div>
      ) : null}

      {/* CTA */}
      {frame >= lineAt(9) - 4 && frame < outroAt ? (
        <div data-scene="cta" style={{ position: "absolute", inset: 0, opacity: fade(lineAt(9) - 4, 8) }}>
          <div data-el="card" data-kind="content" style={{ position: "absolute", left: 120, right: 180, top: 300, background: "#FFFDF8", borderRadius: 30, padding: 30, fontFamily: FONT, color: "#1B1B1B", transform: `rotate(-2deg) translateY(${(1 - Math.min(1, sp(lineAt(9) + 4))) * 80}px)`, boxShadow: "0 30px 80px rgba(0,0,0,.5)" }}>
            <div style={{ fontFamily: MONO, fontSize: 20, letterSpacing: 2, color: "#8A7F6E" }}>FREE CHECKLIST</div>
            <div style={{ fontSize: 40, fontWeight: 800, marginTop: 4 }}>{c.checklist.title}</div>
            {c.checklist.points.map((p, k) => (
              <div key={p} style={{ display: "flex", gap: 12, alignItems: "center", marginTop: 12, fontSize: 26, fontWeight: 700, opacity: fade(lineAt(9) + 10 + k * 4, 4) }}>
                <span style={{ width: 30, height: 30, borderRadius: 8, background: T.accent, color: T.ink, display: "grid", placeItems: "center", fontSize: 18, fontWeight: 800 }}>✓</span>
                {p}
              </div>
            ))}
          </div>
          <div data-el="title" data-kind="content" style={{ position: "absolute", left: 60, right: 120, top: 870, textAlign: "center", fontFamily: FONT, fontWeight: 800 }}>
            <div style={{ fontSize: 44, color: T.cream }}>COMMENT KARO</div>
            <div style={{ display: "inline-block", marginTop: 10, fontSize: 92, color: T.ink, background: T.accent, padding: "4px 30px", borderRadius: 16, letterSpacing: 4, transform: `scale(${Math.min(1.05, sp(at(c.cues.keyword), { damping: 8, stiffness: 200, mass: 0.6 }))})` }}>“{c.keyword}”</div>
          </div>
          <div data-el="card" data-kind="content" style={{ position: "absolute", left: 120, right: 180, top: 1090, fontFamily: FONT, opacity: fade(at(c.cues.keyword), 4) }}>
            <div style={{ background: "#fff", borderRadius: 999, padding: "14px 22px", fontSize: 30, fontWeight: 800, color: "#1B1B1B", display: "flex", justifyContent: "space-between" }}>
              <span>{frame >= at(c.cues.keyword) + 28 ? <span style={{ color: "#999", fontWeight: 600 }}>Add a comment…</span> : typed(c.keyword, at(c.cues.keyword), at(c.cues.keyword) + 24)}</span>
              <span style={{ color: "#3797F0" }}>Post</span>
            </div>
            {frame >= at(c.cues.keyword) + 28 ? (
              <div style={{ marginTop: 14, background: "#fff", borderRadius: 22, padding: "14px 18px", fontSize: 24, color: "#1B1B1B", transform: `scale(${Math.min(1, sp(at(c.cues.keyword) + 28))})`, transformOrigin: "left top" }}>
                <b>{c.handle.replace("@", "")}</b> · DM<br />
                Aapki free checklist ready hai ✓ <span style={{ background: T.accent, color: T.ink, borderRadius: 6, padding: "2px 8px", fontWeight: 800 }}>PDF</span>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}

      {/* outro */}
      {frame >= outroAt ? (
        <div data-scene="outro" style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", background: `linear-gradient(180deg, ${T.bg}, ${T.bg2})`, opacity: fade(outroAt, 8) }}>
          <div data-el="title" data-kind="content" style={{ textAlign: "center", fontFamily: FONT, transform: `scale(${0.85 + 0.15 * Math.min(1, sp(outroAt))})` }}>
            <div style={{ fontSize: 110, fontWeight: 800, color: T.cream, letterSpacing: -4 }}>
              solvix<span style={{ color: T.accent }}>web</span>
            </div>
            <div style={{ fontFamily: MONO, fontSize: 26, letterSpacing: 6, color: T.cream, opacity: 0.7 }}>WEBSITES THAT BRING CUSTOMERS</div>
          </div>
        </div>
      ) : null}

      {/* captions */}
      {capWords.length && frame < outroAt ? (
        <div data-el="captions" data-kind="caption" style={{ position: "absolute", left: 90, right: 150, top: 1395, display: "flex", justifyContent: "center" }}>
          <div style={{ display: "flex", gap: 10, flexWrap: "wrap", justifyContent: "center", background: "rgba(0,0,0,.55)", borderRadius: 16, padding: "8px 16px", fontFamily: FONT, fontWeight: 800, fontSize: 38 }}>
            {capWords.map((w, k) => {
              const idx = c0 + k;
              return <span key={idx} style={{ color: idx === cur ? T.accent : T.cream, opacity: idx <= cur ? 1 : 0.35 }}>{w.word.replace(/[—,.?:]+$/u, "")}</span>;
            })}
          </div>
        </div>
      ) : null}

      {/* subtle grain */}
      <div style={{ position: "absolute", inset: 0, opacity: 0.05, backgroundImage: "radial-gradient(#fff 0.8px, transparent 1px)", backgroundSize: "5px 5px", backgroundPosition: `${Math.floor(random(`g${frame}`) * 5)}px 0`, pointerEvents: "none" }} />
    </div>
  );
};
