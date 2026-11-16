// Reel "hotel-makeover": WEBSITE MAKEOVER · EP 01 for a hotel / homestay (see src/components/makeover.tsx).
import React from "react";
import { brand as myBrand } from "../../brand";
import { type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";
import { MakeoverReel, makeoverDuration, type MakeoverConfig } from "../../components/makeover";
import words from "../../../public/reels/hotel-makeover/captions.words.json";

const config: MakeoverConfig = {
  kind: "hotel",
  theme: { bg: "#0E1714", bg2: "#1B2B25", cream: "#EEF2EA", accent: "#E9C46A", ink: "#10201A" },
  audio: "reels/hotel-makeover/vo.wav",
  words,
  starts: [0, 13, 20, 32, 48, 59, 69, 76, 85, 94],
  cues: { domainEnd: 19, tagline: 27, items: [33, 34, 36, 45], info: [48, 50, 53], book: 62, wa: 67, link: 75, full: 81, care: 90, keyword: 98 },
  coldOpen: { label: "ROOMS KHAALI?", icon: "bed" },
  intro: "agar main aapke hotel ki website banata…",
  domain: "pineviewstay.in",
  brand: "Pine View Stay",
  tagline: "Manali mein pahadon ke beech",
  items: [
    { name: "Deluxe room", price: "₹3,200" },
    { name: "Valley view", price: "₹4,500" },
    { name: "Family suite", price: "₹6,800" },
    { name: "Cottage", price: "₹8,500" },
  ],
  info: ["Old Manali · map", "Check-in 12 PM · out 11 AM", "4.6 · 214 Google reviews"],
  bookLabel: "Room book karo · WhatsApp",
  waMsg: "25–27 Dec, 2 log, valley view?",
  igHandle: "pineviewstay",
  grid: { title: "DEC 25 · ROOMS", units: ["101", "102", "103", "104", "201", "202", "203", "204"], stamp: "SOLD OUT" },
  care: ["Website hum sambhalenge,", "aap bas mehmaanon pe dhyaan do"],
  keyword: "HOTEL",
  checklist: { title: "Hotel Website Checklist", points: ["Yaad rehne wala domain", "Rooms photos + price", "Map + check-in + reviews", "WhatsApp room booking"] },
  clips: { hero: "broll/hotel-mountains.mp4", full: "broll/hotel-terrace.mp4", care: "broll/hotel-room.mp4" },
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
