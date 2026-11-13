// Reel "dentist-digital" (~45 s): a direct pitch to dentists — get found online, within the NDC rules.
// Script, sources: projects/dentist-digital/script.md. Word times: public/reels/dentist-digital/captions.words.json.
// Word numbers below refer to that file (0 = first word); they stay valid when the real voice is synced,
// because the caption text (and so the word list) does not change.
import React from "react";
import { brand as myBrand } from "../../brand";
import { useBrand } from "../../brand-context";
import {
  Backdrop,
  Badge,
  CommentsCTA,
  PhoneSite,
  ReelFrame,
  Ring,
  Scene,
  SceneLabel,
  ScheduleCard,
  SearchMock,
  Sticker,
  Tick,
  VoiceBubble,
  WordsIn,
  type ReelProps,
} from "../../components";
import { creamStory } from "../../presets";
import { CAPTION_BOTTOM, SAFE, SAFE_BOX } from "../../layout";
import { timing } from "../../timing";
import mouth from "../../../public/reels/dentist-digital/mouth.json";
import words from "../../../public/reels/dentist-digital/captions.words.json";

export const t = timing(words);
export const AUDIO = "reels/dentist-digital/vo.wav";
export const durationInFrames = t.duration(1.6);

// Scene cuts on spoken words (word index, fallback seconds).
const S = {
  hook: [0, t.at(11, 4.5)], //               "Doctor sahab, kaam aapka best hai — par naye patient kam kyun?"
  search: [t.at(11, 4.5), t.at(21, 8.5)], //  "Kyunki aaj patient pehle Google karta hai — dentist near me."
  data: [t.at(21, 8.5), t.at(38, 15.8)], //   "Ek US survey mein 84% ..."
  lost: [t.at(38, 15.8), t.at(49, 20.5)], //  "Wahan aapka clinic nahi dikha, toh patient doosre clinic chala gaya."
  rules: [t.at(49, 20.5), t.at(61, 25.6)], // "Aur galat promotion kiya, toh National Dental Commission action le sakta hai."
  offer: [t.at(61, 25.6), t.at(73, 30.6)], // "Isliye hum dentists ke liye rules ke andar digital setup banate hain —"
  list: [t.at(73, 30.6), t.at(82, 34.7)], //  "website, Google profile, online appointment, aur patient education posts."
  split: [t.at(82, 34.7), t.at(88, 37.6)], // "Aap patient sambhaliye, online hum sambhalenge."
  end: [t.at(88, 37.6), durationInFrames], // "Comment karo DENTIST — ..."
} as const;

const col = { position: "absolute", left: SAFE_BOX.x } as const;
const full = { ...col, top: 300, width: SAFE_BOX.width } as const;
const left = { ...col, width: 500 } as const;

const ListCard: React.FC<{ title: string; children: React.ReactNode }> = ({ title, children }) => {
  const b = useBrand();
  return (
    <div data-el="card" data-kind="content" style={{ background: b.colors.surface, borderRadius: b.radius, boxShadow: b.boxShadow, padding: "34px 30px", fontFamily: b.fontFamily, color: b.colors.text }}>
      <div style={{ fontSize: 32, fontWeight: 700, opacity: 0.55, marginBottom: 22 }}>{title}</div>
      {children}
    </div>
  );
};

const RoleCard: React.FC<{ at: number; who: string; what: string }> = ({ at, who, what }) => {
  const b = useBrand();
  return (
    <div style={{ display: "flex", alignItems: "baseline", gap: 28 }}>
      <div style={{ fontFamily: b.fontFamily, fontSize: 60, fontWeight: 800, color: `${b.colors.text}80`, width: 170 }}>{who}</div>
      <div style={{ flex: 1 }}>
        <WordsIn text={what} accent={[what]} serif at={at} size={120} />
      </div>
    </div>
  );
};

export const Reel: React.FC<ReelProps> = ({ brand = { ...creamStory, handle: myBrand.handle }, debugSafeZones, probe }) => {
  const query = "dentist near me";
  const build0 = t.at(61, 25.6) + 12; // start assembling right after the phone lands
  return (
    <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
      <Backdrop grid={false} />

      {/* 1. Hook: the dentist's empty schedule */}
      <Scene name="hook" from={S.hook[0]} to={S.hook[1]}>
        <SceneLabel text="// 01 — the problem" at={0} />
        <div style={{ ...full, top: SAFE.top + 40 }}>
          <WordsIn text="Doctor sahab, naye patient kam kyun?" accent={["kam"]} serif at={0} size={100} stagger={2} showHandle />
        </div>
        <div style={{ ...full, top: 660 }}>
          <ScheduleCard
            at={t.at(2, 1)}
            title="Aaj ke appointments"
            slots={[{ time: "10:00" }, { time: "11:30", who: "Root canal · follow-up" }, { time: "1:00" }, { time: "4:30" }]}
          />
        </div>
      </Scene>

      {/* 2. The patient searches */}
      <Scene name="search" from={S.search[0]} to={S.search[1]}>
        <SceneLabel text="// 02 — the patient" at={S.search[0]} />
        <div style={full}>
          <SearchMock at={S.search[0]} query={query} typeTo={t.at(20, 8)} missAt={S.search[1] + 999} hitAt={S.search[1] + 999} you="" them="" />
        </div>
        <Sticker text="har patient yahi karta hai" at={t.at(15, 6.4)} rotate={-5} style={{ position: "absolute", left: 380, top: 560 }} />
      </Scene>

      {/* 3. 84% read reviews */}
      <Scene name="data" from={S.data[0]} to={S.data[1]}>
        <SceneLabel text="// 03 — the data" at={S.data[0]} />
        <div style={full}>
          <Ring value={84} at={S.data[0] + 4} countFrames={Math.max(12, t.at(25, 10.2) - S.data[0])} size={280} label="patients online reviews padhte hain" sub={<>doctor chunne se pehle<br />US survey · rater8</>} />
        </div>
        <Sticker text="pehle online, phir clinic" at={t.at(34, 14.2)} rotate={6} style={{ position: "absolute", left: 420, top: 760 }} />
      </Scene>

      {/* 4. Not found -> patient goes elsewhere */}
      <Scene name="lost" from={S.lost[0]} to={S.lost[1]}>
        <SceneLabel text="// 03 — the data" at={S.lost[0]} />
        <div style={full}>
          <SearchMock at={S.lost[0]} query={query} typeTo={S.lost[0] + 1} missAt={t.at(41, 17.2)} hitAt={t.at(45, 18.7)} you="Aapka clinic" them="Doosra Dental Clinic" />
        </div>
      </Scene>

      {/* 5. Twist: wrong promotion can bring action */}
      <Scene name="rules" from={S.rules[0]} to={S.rules[1]}>
        <SceneLabel text="// 04 — the rules" at={S.rules[0]} />
        <div style={{ ...col, top: 290 }}>
          <Badge text="RULES" at={S.rules[0]} />
        </div>
        <div style={{ ...full, top: 420 }}>
          <ListCard title="NDC advisory · Oct 2026">
            <Tick first ok={false} at={t.at(51, 21.3)}>Patient testimonials</Tick>
            <Tick ok={false} at={t.at(52, 21.7)}>Before / after photos</Tick>
            <Tick ok={false} at={t.at(54, 22.4)}>Jhoothe ya bade daave</Tick>
          </ListCard>
        </div>
        <Sticker text="action ho sakta hai" at={t.at(57, 24)} rotate={-6} style={{ position: "absolute", left: 480, top: 860 }} />
      </Scene>

      {/* 6. Offer: a clinic website builds on the phone */}
      <Scene name="offer" from={S.offer[0]} to={S.offer[1]}>
        <SceneLabel text="// 05 — the fix" at={S.offer[0]} />
        <div style={{ ...left, top: 300 }}>
          <WordsIn text="Rules ke andar digital setup" accent={["digital", "setup"]} serif at={S.offer[0]} size={74} stagger={2} />
        </div>
      </Scene>

      {/* 7. What is included */}
      <Scene name="list" from={S.list[0]} to={S.list[1]}>
        <SceneLabel text="// 05 — the fix" at={S.list[0]} />
        <div style={{ ...left, top: 300 }}>
          <ListCard title="Hum banayenge:">
            <Tick first at={t.at(73, 30.6)}>Website</Tick>
            <Tick at={t.at(74, 31.1)}>Google profile</Tick>
            <Tick at={t.at(76, 32)}>Online appointment</Tick>
            <Tick at={t.at(79, 33.2)}>Patient education posts</Tick>
          </ListCard>
        </div>
      </Scene>

      {/* Phone across offer + list */}
      <Scene from={S.offer[0]} to={S.split[0]}>
        <div style={{ position: "absolute", left: 600, top: 290 }}>
          <PhoneSite
            at={S.offer[0] + 4}
            name="Smile Dental Care"
            heroTitle="Dard-free dental care"
            heroSub="Mon–Sat · 10 AM – 8 PM"
            rows={[
              ["Consultation", "Book"],
              ["Cleaning", "Book"],
              ["Root canal", "Book"],
            ]}
            button="Appointment book karo"
            build={{ nav: build0, hero: build0 + 5, rates: build0 + 10, whatsapp: build0 + 15, map: build0 + 20 }}
            focus={[
              { at: t.at(73, 30.6), key: "phone" },
              { at: t.at(74, 31.1), key: "map" },
              { at: t.at(76, 32), key: "whatsapp" },
              { at: t.at(79, 33.2), key: "hero" },
            ]}
          />
        </div>
      </Scene>

      {/* 8. Split of roles */}
      <Scene name="split" from={S.split[0]} to={S.split[1]}>
        <SceneLabel text="// 05 — the fix" at={S.split[0]} />
        <div style={{ ...full, top: 380 }}>
          <RoleCard at={t.at(83, 35)} who="Aap:" what="patient" />
          <div style={{ height: 70 }} />
          <RoleCard at={t.at(85, 36)} who="Hum:" what="online" />
        </div>
      </Scene>

      {/* 9. CTA */}
      <Scene name="end" from={S.end[0]} to={S.end[1] + 30 /* no exit: last frame stays sharp for the loop */}>
        <SceneLabel text="// 06 — free check-up" at={S.end[0]} />
        <div style={{ ...full, top: SAFE.top + 40 }}>
          <WordsIn text="Comment karo" at={S.end[0]} size={100} />
        </div>
        <div style={{ ...full, top: 420 }}>
          <CommentsCTA at={t.at(89, 38)} keyword="DENTIST" dmAt={t.at(94, 39.6)} dmTitle="Free clinic check-up" dmLines={["Google", "Website", "Instagram"]} tag="FREE" />
        </div>
      </Scene>

      <div style={{ position: "absolute", left: SAFE_BOX.x, width: SAFE_BOX.width, bottom: CAPTION_BOTTOM }}>
        <VoiceBubble words={words} mouth={mouth} keywords={["Google", "84%", "clinic", "DENTIST", "rules", "free"]} />
      </div>
    </ReelFrame>
  );
};
