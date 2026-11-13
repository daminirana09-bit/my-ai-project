// Reel "dentist-digital" (~45 s): a direct pitch to dentists — get found online, within the NDC rules.
// Look: "card story" (src/components/cardstory.tsx) with stock B-roll from public/broll (`npm run broll`).
// Script, sources: projects/dentist-digital/script.md. Word times: public/reels/dentist-digital/captions.words.json.
// Word numbers below refer to that file (0 = first word); they stay valid when the voice is swapped,
// because the caption text (and so the word list) does not change.
import React from "react";
import { brand as myBrand } from "../../brand";
import { ReelFrame, Scene, type ReelProps } from "../../components";
import { CardCTA, CardSearch, CardStat, ClipCard, RowsPanel, StoryBackground, StoryFooter, StoryHeader, StoryOutro, WordBoxes } from "../../components/cardstory";
import { creamStory } from "../../presets";
import { timing } from "../../timing";
import words from "../../../public/reels/dentist-digital/captions.words.json";

export const t = timing(words);
export const AUDIO = "reels/dentist-digital/vo.wav";
export const durationInFrames = t.duration(2.2);

type Slide = { name: string; from: number; to: number; eyebrow: string; l1: string; l2: string; clip: string; startSec?: number };

const at = (i: number, fb: number) => t.at(i, fb);
const SL: Slide[] = [
  { name: "hook", from: 0, to: at(11, 4.5), eyebrow: "Clinic reality check", l1: "Kaam aapka best hai.", l2: "Phir patient kam kyun?", clip: "broll/dentist-ready.mp4", startSec: 2 },
  { name: "search", from: at(11, 4.5), to: at(21, 8.5), eyebrow: "Patient ka pehla step", l1: "Pehle Google,", l2: "phir clinic.", clip: "broll/researching.mp4", startSec: 1 },
  { name: "data", from: at(21, 8.5), to: at(38, 15.8), eyebrow: "Data · US survey", l1: "84% patients", l2: "reviews padhte hain.", clip: "broll/phone-scroll.mp4", startSec: 2 },
  { name: "lost", from: at(38, 15.8), to: at(49, 20.5), eyebrow: "Nuksaan", l1: "Aap nahi dikhe,", l2: "patient doosri jagah.", clip: "broll/reception.mp4", startSec: 1 },
  { name: "rules", from: at(49, 20.5), to: at(61, 25.6), eyebrow: "Dhyaan rahe", l1: "Galat promotion =", l2: "NDC action.", clip: "broll/xrays.mp4", startSec: 2 },
  { name: "setup", from: at(61, 25.6), to: at(73, 30.6), eyebrow: "Humara setup", l1: "Rules ke andar", l2: "digital setup.", clip: "broll/typing-close.mp4", startSec: 1 },
  { name: "list", from: at(73, 30.6), to: at(82, 34.7), eyebrow: "Kya milega", l1: "Sab kuch,", l2: "ek jagah.", clip: "broll/laptop-man.mp4", startSec: 8 },
  { name: "roles", from: at(82, 34.7), to: at(88, 37.6), eyebrow: "Aap + hum", l1: "Aap: patient.", l2: "Hum: online.", clip: "broll/dentist-checking.mp4", startSec: 2 },
  { name: "end", from: at(88, 37.6), to: durationInFrames, eyebrow: "Free clinic check-up", l1: "Comment karo", l2: "DENTIST", clip: "broll/laptop-man.mp4", startSec: 3 },
];

const overlay = (name: string, s: Slide): React.ReactNode => {
  switch (name) {
    case "hook":
      return (
        <RowsPanel
          at={s.from + 10}
          header="Aaj ke appointments"
          rows={[
            { title: "10:00 · khaali", mark: "no", at: at(4, 1.6) },
            { title: "11:30 · Root canal", mark: "ok", at: at(6, 2.3) },
            { title: "1:00 · khaali", mark: "no", at: at(9, 3.4) },
          ]}
        />
      );
    case "search":
      return <CardSearch at={s.from + 4} query="dentist near me" typeTo={at(20, 8)} />;
    case "data":
      return <CardStat at={at(24, 9.6)} value={84} suffix="%" label="doctor chunne se pehle reviews padhte hain · rater8" countFrames={Math.max(10, at(26, 10.4) - at(24, 9.6))} />;
    case "lost":
      return (
        <>
          <CardSearch at={s.from} query="dentist near me" typeTo={s.from} />
          <RowsPanel
            at={s.from + 6}
            swapAt={at(44, 18.3)}
            rows={[
              { title: "Aapka clinic", sub: "website nahi · timing nahi", mark: "num", at: s.from + 8 },
              { title: "Doosra Dental Clinic", sub: "website · timing · map", mark: "num", at: s.from + 12 },
            ]}
          />
        </>
      );
    case "rules":
      return (
        <RowsPanel
          at={s.from + 6}
          header="NDC advisory · Oct 2026"
          rows={[
            { title: "Patient testimonials", mark: "no", at: at(51, 21.3) },
            { title: "Before / after photos", mark: "no", at: at(54, 22.4) },
            { title: "Jhoothe ya bade daave", mark: "no", at: at(57, 24) },
          ]}
        />
      );
    case "setup":
      return <CardSearch at={s.from + 6} query="smiledentalcare.in" typeTo={at(71, 30)} />;
    case "list":
      return (
        <RowsPanel
          at={s.from + 2}
          rows={[
            { title: "Website", mark: "ok", at: at(73, 30.6) },
            { title: "Google profile", mark: "ok", at: at(74, 31.1) },
            { title: "Online appointment", mark: "ok", at: at(76, 32) },
            { title: "Patient education posts", mark: "ok", at: at(79, 33.2) },
          ]}
        />
      );
    case "end":
      return <CardCTA at={at(90, 38.6)} text="Comment karo: DENTIST" contact={myBrand.handle} />;
    default:
      return null;
  }
};

export const Reel: React.FC<ReelProps> = ({ brand = { ...creamStory, handle: myBrand.handle }, debugSafeZones, probe }) => (
  <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
    <StoryBackground />
    {SL.map((s, i) => (
      <React.Fragment key={s.name}>
        <ClipCard src={s.clip} from={s.from} to={i === SL.length - 1 ? s.to + 30 : s.to} startSec={s.startSec}>
          {overlay(s.name, s)}
        </ClipCard>
        <Scene name={s.name} from={s.from} to={i === SL.length - 1 ? s.to + 30 : s.to}>
          <StoryHeader at={s.from} eyebrow={s.eyebrow} line1={s.l1} line2={s.l2} />
          <StoryFooter handle={myBrand.handle} index={i + 1} total={SL.length} />
        </Scene>
      </React.Fragment>
    ))}
    <WordBoxes words={words} />
    <StoryOutro at={durationInFrames - 34} handle={myBrand.handle} />
  </ReelFrame>
);
