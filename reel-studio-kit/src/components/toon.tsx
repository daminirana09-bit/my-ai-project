// Two flat cartoon characters drawn in SVG, with a talking mouth (from mouth.<who>.json), blinking,
// eyebrow moods, head bob while speaking and arm poses that blend smoothly.
//   <Toon who="aunty" ... />  Gupta Aunty: bun, bindi, gold glasses on a chain, navy blazer over a teal kurta, mustard dupatta
//   <Toon who="arjun" ... />  Arjun: curly hair, round black glasses, orange SOLVIX t-shirt
import React from "react";
import { interpolate, random, useCurrentFrame } from "remotion";

export type Pose = "rest" | "explain" | "point" | "hip" | "phone" | "chin" | "shock" | "thumbs" | "wave";
export type Mood = "calm" | "happy" | "doubt" | "shock" | "angry";
export type PoseCue = { at: number; pose: Pose; mood?: Mood };

// [left upper, left fore, right upper, right fore] in degrees, 0 = hanging down, positive = clockwise.
// "left"/"right" are as the viewer sees them.
const POSES: Record<Pose, [number, number, number, number]> = {
  rest: [10, 4, -10, -4],
  explain: [10, 4, -38, -78],
  point: [10, 4, -84, -6],
  hip: [34, 112, -10, -4],
  phone: [10, 4, 26, 128],
  chin: [10, 4, 22, 150],
  shock: [42, 118, -42, -118],
  thumbs: [10, 4, -28, -112],
  wave: [10, 4, -150, -20],
};

const OUT = "#1E1E24";
const SW = 5;

const lerp = (a: number, b: number, p: number) => a + (b - a) * p;

/** Blend poses: each cue eases in over 8 frames from the previous pose. */
const usePose = (cues: PoseCue[], frame: number) => {
  let prev = cues[0];
  let cur = cues[0];
  for (const c of cues) if (frame >= c.at) { prev = cur; cur = c; }
  const p = interpolate(frame - cur.at, [0, 8], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const e = 1 - Math.pow(1 - p, 3);
  const a = POSES[prev.pose], b = POSES[cur.pose];
  return { angles: a.map((v, i) => lerp(v, b[i], e)) as [number, number, number, number], mood: cur.mood ?? "calm", pose: cur.pose, poseP: e };
};

const Arm: React.FC<{ x: number; y: number; up: number; fore: number; sleeve: string; skin: string; flip?: boolean; holdPhone?: boolean }> = ({ x, y, up, fore, sleeve, skin, holdPhone }) => (
  <g transform={`translate(${x} ${y}) rotate(${up})`}>
    <rect x={-22} y={-6} width={44} height={128} rx={22} fill={sleeve} stroke={OUT} strokeWidth={SW} />
    <g transform={`translate(0 112) rotate(${fore})`}>
      <rect x={-19} y={-8} width={38} height={112} rx={19} fill={sleeve} stroke={OUT} strokeWidth={SW} />
      <circle cx={0} cy={112} r={23} fill={skin} stroke={OUT} strokeWidth={SW} />
      {holdPhone ? (
        <g transform="translate(-20 70)">
          <rect x={0} y={0} width={46} height={80} rx={9} fill="#2B2D42" stroke={OUT} strokeWidth={SW} />
          <rect x={6} y={8} width={34} height={60} rx={4} fill="#9AD1FF" />
        </g>
      ) : null}
    </g>
  </g>
);

const Eye: React.FC<{ cx: number; cy: number; blink: number; gaze: number; mood: Mood }> = ({ cx, cy, blink, gaze, mood }) => {
  const ry = (mood === "shock" ? 19 : 15) * (1 - blink);
  return (
    <g>
      <ellipse cx={cx} cy={cy} rx={mood === "shock" ? 17 : 15} ry={Math.max(1.5, ry)} fill="#fff" stroke={OUT} strokeWidth={4} />
      {blink < 0.6 ? <circle cx={cx + gaze} cy={cy + 1} r={mood === "shock" ? 6 : 7.5} fill={OUT} /> : null}
    </g>
  );
};

const Brows: React.FC<{ y: number; mood: Mood; color: string }> = ({ y, mood, color }) => {
  const lift = mood === "shock" ? -14 : mood === "doubt" ? -6 : mood === "happy" ? -4 : 0;
  const tilt = mood === "angry" ? 12 : mood === "doubt" ? -8 : 0;
  return (
    <g stroke={color} strokeWidth={8} strokeLinecap="round" fill="none">
      <path d={`M150 ${y + lift + tilt * -0.3} Q 172 ${y + lift - 10} 194 ${y + lift + tilt}`} />
      <path d={`M226 ${y + lift + tilt} Q 248 ${y + lift - 10 - (mood === "doubt" ? 6 : 0)} 270 ${y + lift + tilt * -0.3}`} />
    </g>
  );
};

const Mouth: React.FC<{ open: number; mood: Mood; y: number }> = ({ open, mood, y }) => {
  if (open < 0.06) {
    if (mood === "shock") return <ellipse cx={210} cy={y} rx={10} ry={12} fill="#7A2E2E" stroke={OUT} strokeWidth={4} />;
    const curve = mood === "happy" ? 14 : mood === "angry" || mood === "doubt" ? -6 : 8;
    return <path d={`M188 ${y} Q 210 ${y + curve} 232 ${y}`} fill="none" stroke={OUT} strokeWidth={5} strokeLinecap="round" />;
  }
  const ry = 5 + 20 * Math.min(1, open);
  return (
    <g>
      <ellipse cx={210} cy={y + ry * 0.35} rx={20 + 4 * open} ry={ry} fill="#7A2E2E" stroke={OUT} strokeWidth={4} />
      <ellipse cx={210} cy={y + ry * 0.9} rx={11} ry={Math.max(2, ry * 0.35)} fill="#E46C6C" />
    </g>
  );
};

export const Toon: React.FC<{ who: "aunty" | "arjun" | "doctor"; mouth: number[]; cues: PoseCue[]; lookRight?: boolean; speaking?: boolean; scale?: number }> = ({ who, mouth, cues, lookRight = true, speaking = false, scale = 1 }) => {
  const frame = useCurrentFrame();
  const { angles, mood, pose } = usePose(cues, frame);
  const m = mouth[frame] ?? 0;
  // blink every ~3.3 s (offset per character), 5 frames long
  const period = who === "aunty" ? 100 : who === "doctor" ? 107 : 113;
  const ph = (frame + (who === "aunty" ? 37 : who === "doctor" ? 61 : 0)) % period;
  const blink = ph < 5 ? Math.sin((ph / 5) * Math.PI) : 0;
  const breathe = Math.sin(frame / 22) * 3;
  const bob = speaking ? Math.sin(frame / 3.1) * 3 * Math.min(1, m * 2) : 0;
  const tilt = (speaking ? Math.sin(frame / 17) * 2.5 : lookRight ? 3 : -3) + (mood === "shock" ? -4 : 0);
  const gaze = lookRight ? 4 : -4;

  const A = who === "aunty";
  const D = who === "doctor";
  const skin = A ? "#C68863" : D ? "#B97A56" : "#A86E4C";
  const sleeve = A ? "#22314F" : D ? "#F7F7F2" : "#F26B3A";
  const hair = A ? "#2B2B30" : D ? "#CFCFD6" : "#18181C";
  return (
    <svg width={420 * scale} height={640 * scale} viewBox="0 0 420 640" style={{ overflow: "visible" }}>
      <g transform={`translate(0 ${breathe})`}>
        {/* torso */}
        {D ? (
          <g>
            <path d="M86 332 Q 210 278 334 332 L 360 660 L 60 660 Z" fill="#9CC9F0" stroke={OUT} strokeWidth={SW} />
            <path d="M86 332 Q 150 300 184 298 L 204 470 L 150 660 L 60 660 Z" fill={sleeve} stroke={OUT} strokeWidth={SW} />
            <path d="M334 332 Q 270 300 236 298 L 216 470 L 270 660 L 360 660 Z" fill={sleeve} stroke={OUT} strokeWidth={SW} />
            <rect x={262} y={430} width={52} height={60} rx={6} fill="#EDEDE6" stroke={OUT} strokeWidth={4} />
            <rect x={276} y={404} width={8} height={40} rx={3} fill="#2F6BFF" stroke={OUT} strokeWidth={3} />
            <path d="M160 316 Q 210 352 260 316 L 252 296 Q 210 322 168 296 Z" fill="#7FC8E8" stroke={OUT} strokeWidth={4} />
          </g>
        ) : A ? (
          <g>
            <path d="M88 330 Q 210 270 332 330 L 360 660 L 60 660 Z" fill="#1F8A84" stroke={OUT} strokeWidth={SW} />
            <path d="M88 330 Q 150 300 186 300 L 210 460 L 120 660 L 60 660 Z" fill={sleeve} stroke={OUT} strokeWidth={SW} />
            <path d="M332 330 Q 270 300 234 300 L 210 460 L 300 660 L 360 660 Z" fill={sleeve} stroke={OUT} strokeWidth={SW} />
            <path d="M120 320 Q 200 420 300 660 L 250 660 Q 170 450 96 340 Z" fill="#E2A93B" stroke={OUT} strokeWidth={SW} />
          </g>
        ) : (
          <g>
            <path d="M84 332 Q 210 280 336 332 L 362 660 L 58 660 Z" fill={sleeve} stroke={OUT} strokeWidth={SW} />
            <path d="M178 300 Q 210 330 242 300" fill="none" stroke={OUT} strokeWidth={SW} />
            <text x={210} y={392} textAnchor="middle" fontFamily="Inter, sans-serif" fontWeight={800} fontSize={30} fill="#fff" letterSpacing={3}>
              SOLVIX
            </text>
          </g>
        )}
        {/* neck */}
        <rect x={186} y={262} width={48} height={52} fill={skin} stroke={OUT} strokeWidth={SW} />
        {/* arms (behind hands drawn after torso) */}
        <Arm x={98} y={340} up={angles[0]} fore={angles[1]} sleeve={sleeve} skin={skin} />
        <Arm x={322} y={340} up={angles[2]} fore={angles[3]} sleeve={sleeve} skin={skin} holdPhone={pose === "phone"} />
        {/* head */}
        <g transform={`translate(0 ${bob}) rotate(${tilt} 210 250)`}>
          {A ? <circle cx={210} cy={58} r={44} fill={hair} stroke={OUT} strokeWidth={SW} /> : null}
          {/* ears */}
          <circle cx={130} cy={182} r={20} fill={skin} stroke={OUT} strokeWidth={SW} />
          <circle cx={290} cy={182} r={20} fill={skin} stroke={OUT} strokeWidth={SW} />
          {A ? (
            <>
              <circle cx={130} cy={210} r={7} fill="#E2B23B" stroke={OUT} strokeWidth={3} />
              <circle cx={290} cy={210} r={7} fill="#E2B23B" stroke={OUT} strokeWidth={3} />
            </>
          ) : null}
          <ellipse cx={210} cy={172} rx={82} ry={98} fill={skin} stroke={OUT} strokeWidth={SW} />
          {/* hair */}
          {A ? (
            <>
              <path d="M128 168 Q 120 80 210 74 Q 300 80 292 168 Q 268 112 210 108 Q 152 112 128 168 Z" fill={hair} stroke={OUT} strokeWidth={SW} />
              <path d="M228 80 Q 262 92 276 128" stroke="#9A9AA6" strokeWidth={7} fill="none" strokeLinecap="round" />
              <circle cx={210} cy={140} r={6} fill="#D7263D" />
            </>
          ) : D ? (
            <g fill={hair} stroke={OUT} strokeWidth={4}>
              <path d="M130 196 Q 118 140 150 108 Q 160 128 156 150 Q 150 172 152 200 Z" />
              <path d="M290 196 Q 302 140 270 108 Q 260 128 264 150 Q 270 172 268 200 Z" />
              <path d="M186 82 Q 210 70 236 82" fill="none" strokeWidth={5} />
            </g>
          ) : (
            <g fill={hair} stroke={OUT} strokeWidth={4}>
              {[
                [140, 120, 26], [168, 96, 28], [200, 86, 30], [234, 90, 28], [262, 106, 26], [282, 132, 20], [130, 146, 18], [186, 110, 22], [222, 112, 22],
              ].map(([x, y, r], i) => (
                <circle key={i} cx={x} cy={y} r={r} />
              ))}
            </g>
          )}
          {who === "arjun" ? <path d="M138 214 Q 210 300 282 214 Q 270 262 210 268 Q 150 262 138 214 Z" fill="#000" opacity={0.12} /> : null}
          <Brows y={146} mood={mood} color={hair} />
          <Eye cx={172} cy={178} blink={blink} gaze={gaze} mood={mood} />
          <Eye cx={248} cy={178} blink={blink} gaze={gaze} mood={mood} />
          {/* glasses */}
          {D ? null : <g fill="none" stroke={A ? "#B8860B" : OUT} strokeWidth={5}>
            <circle cx={172} cy={178} r={28} />
            <circle cx={248} cy={178} r={28} />
            <path d="M200 178 Q 210 170 220 178" />
            <path d="M144 176 L 128 172 M 276 176 L 292 172" />
          </g>}
          {A ? <path d="M132 176 Q 120 250 150 300 M 288 176 Q 300 250 270 300" fill="none" stroke="#B8860B" strokeWidth={2.5} strokeDasharray="3 4" /> : null}
          <path d="M210 190 Q 202 214 212 218" fill="none" stroke={OUT} strokeWidth={4} strokeLinecap="round" />
          <Mouth open={m} mood={mood} y={238} />
          {D ? <path d="M168 230 Q 186 210 210 222 Q 234 210 252 230 Q 232 240 210 232 Q 188 240 168 230 Z" fill={hair} stroke={OUT} strokeWidth={4} /> : null}
          {mood === "shock" && random(`s${Math.floor(frame / 4)}`) >= 0 ? (
            <g stroke="#D7263D" strokeWidth={6} strokeLinecap="round">
              <path d="M84 70 L 104 92 M 72 110 L 98 116 M 336 70 L 316 92 M 348 110 L 322 116" />
            </g>
          ) : null}
        </g>
      </g>
    </svg>
  );
};
