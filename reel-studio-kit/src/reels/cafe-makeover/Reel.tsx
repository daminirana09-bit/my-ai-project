// Reel "cafe-makeover": WEBSITE MAKEOVER · EP 01 for a cafe (see src/components/makeover.tsx).
import React from "react";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { MakeoverReel, makeoverDuration, type MakeoverConfig } from "../../components/makeover";
import words from "../../../public/reels/cafe-makeover/captions.words.json";

const config: MakeoverConfig = {
  kind: "cafe",
  theme: { bg: "#17110D", bg2: "#2A1C13", cream: "#F6EBDD", accent: "#F2A33A", ink: "#1A120C" },
  audio: "reels/cafe-makeover/vo.wav",
  words,
  starts: [0, 13, 20, 31, 44, 54, 64, 71, 80, 89],
  cues: { domainEnd: 19, tagline: 26, items: [32, 33, 35, 41], info: [44, 45, 48], book: 57, wa: 62, link: 70, full: 76, care: 85, keyword: 93 },
  coldOpen: { label: "TABLES KHAALI?", icon: "table" },
  intro: "agar main aapke cafe ki website banata…",
  domain: "kulhadcorner.in",
  brand: "Kulhad Corner",
  tagline: "C-Scheme ka cozy chai cafe",
  items: [
    { name: "Kulhad chai", price: "₹30" },
    { name: "Bun maska", price: "₹45" },
    { name: "Masala Maggi", price: "₹70" },
    { name: "Cold coffee", price: "₹110" },
  ],
  info: ["C-Scheme, Jaipur · map", "Roz 8 AM – 11 PM", "4.7 · 128 Google reviews"],
  bookLabel: "Table book karo · WhatsApp",
  waMsg: "Sat 7 PM, 4 log?",
  igHandle: "kulhadcorner",
  grid: { title: "SATURDAY · TABLES", units: ["T1", "T2", "T3", "T4", "T5", "T6", "T7", "T8"], stamp: "HOUSEFULL" },
  care: ["Website hum sambhalenge,", "aap bas chai pe dhyaan do"],
  keyword: "CAFE",
  checklist: { title: "Cafe Website Checklist", points: ["Yaad rehne wala domain", "Menu price ke saath", "Map + timing + reviews", "WhatsApp table booking"] },
  clips: { hero: "broll/cafe-tea-prep.mp4", full: "broll/cafe-busy.mp4", care: "broll/cafe-waiter.mp4", fullPos: "72% 50%" },
  music: "music/sleepy-cat.mp3",
  handle: myBrand.handle,
};

export const durationInFrames = makeoverDuration(config);

export const Reel: React.FC<ReelProps> = ({ debugSafeZones, probe }) => (
  <div style={{ position: "absolute", inset: 0 }}>
    <MakeoverReel c={config} />
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);
