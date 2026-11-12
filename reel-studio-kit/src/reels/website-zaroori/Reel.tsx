// Reel "website-zaroori" (~34 s): businesses without a website -> get one made for ₹5,000.
// Script, sources: projects/website-zaroori/script.md. Word times: public/reels/website-zaroori/captions.words.json
// (made by `npm run voice:timed -- website-zaroori`). Word numbers below refer to that file (0 = first word).
// Motion comes from src/components/fx.tsx (scene transitions, kinetic titles, rings, phone build, CTA).
import React from "react";
import { brand as myBrand } from "../../brand";
import { Avatar } from "../../avatars/Avatar";
import { useMouth } from "../../avatars/useMouth";
import type { PoseCue } from "../../avatars/SpritePlayer";
import {
  Backdrop,
  BigNumber,
  Burst,
  CommentCTA,
  Karaoke,
  PhoneSite,
  PriceSlash,
  ReelFrame,
  Ring,
  Scene,
  SearchMock,
  Sticker,
  Tick,
  WalkAway,
  WordsIn,
  type ReelProps,
} from "../../components";
import { useBrand } from "../../brand-context";
import { AVATAR_BOX, CAPTION_BOTTOM, SAFE, SAFE_BOX } from "../../layout";
import { timing } from "../../timing";
import mouth from "../../../public/reels/website-zaroori/mouth.json";
import words from "../../../public/reels/website-zaroori/captions.words.json";

export const t = timing(words);
export const AUDIO = "reels/website-zaroori/vo.wav";
export const durationInFrames = t.duration(1.6);

// Scene cuts on spoken words (word index, fallback seconds).
const S = {
  hook: [0, t.at(6, 3)], //                    "Website nahi hai? Toh customer gaya."
  survey: [t.at(6, 3), t.at(20, 9)], //         "Ek survey mein 39% ..."
  real: [t.at(20, 9), t.at(29, 13)], //         "Aur 45% ko ..."
  google: [t.at(29, 13), t.at(39, 17.6)], //    "Customer pehle Google karta hai ..."
  twist: [t.at(39, 17.6), t.at(45, 20)], //     "Aur website banwana mehenga nahi hai."
  offer: [t.at(45, 20), t.at(54, 24.3)], //     "Hum ... sirf ₹5,000 mein."
  features: [t.at(54, 24.3), t.at(65, 29.6)], // "Mobile pe perfect, WhatsApp button, ..."
  end: [t.at(65, 29.6), durationInFrames], //   "Comment karo WEBSITE — hum DM karenge."
} as const;

const poseCues: PoseCue[] = [
  { at: 0, pose: "shrug" },
  { at: 3, pose: "explain" },
  { at: 13, pose: "phone" },
  { at: 17.6, pose: "thinking" },
  { at: 20, pose: "present" },
  { at: 24.3, pose: "thumbs-up" },
  { at: 29.6, pose: "point-down" },
];

const col = { position: "absolute", left: SAFE_BOX.x } as const;
const full = { ...col, top: 300, width: SAFE_BOX.width } as const;
const left = { ...col, width: 500 } as const;

const FeatureCard: React.FC = () => {
  const b = useBrand();
  return (
    <div data-el="card" data-kind="content" style={{ background: b.colors.surface, borderRadius: b.radius, boxShadow: b.boxShadow, padding: "34px 30px", fontFamily: b.fontFamily }}>
      <div style={{ fontSize: 34, fontWeight: 700, opacity: 0.55, marginBottom: 22 }}>₹5,000 mein:</div>
      <Tick first at={t.at(54, 24.3)}>Mobile pe perfect</Tick>
      <Tick at={t.at(57, 25.8)}>WhatsApp button</Tick>
      <Tick at={t.at(59, 26.9)}>Google Map location</Tick>
      <Tick at={t.at(63, 28.6)}>Aapke rate</Tick>
    </div>
  );
};

export const Reel: React.FC<ReelProps> = ({ brand = myBrand, avatar = "flat", debugSafeZones, probe }) => {
  const m = useMouth(mouth, words);
  const build0 = t.at(49, 21.6) - 4;
  return (
    <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
      <Backdrop />

      {/* 1. Hook: words drop in, a customer walks away from the shop */}
      <Scene name="hook" from={S.hook[0]} to={S.hook[1]}>
        <div style={{ ...full, top: SAFE.top + 40 }}>
          <WordsIn text="Website nahi hai? Customer gaya." accent={["customer", "gaya"]} at={0} size={112} showHandle />
        </div>
        <div style={{ ...col, top: 690 }}>
          <WalkAway at={6} leaveAt={t.at(5, 2.2)} />
        </div>
      </Scene>

      {/* 2. 39% ring */}
      <Scene name="survey" from={S.survey[0]} to={S.survey[1]}>
        <div style={full}>
          <Ring value={39} at={S.survey[0] + 4} countFrames={Math.max(12, t.at(9, 4.2) - S.survey[0])} size={280} label="ne kharida hi nahi" sub={<>kyunki website nahi thi<br />US survey, 2025 · DreamHost</>} />
        </div>
        <Sticker text="customer gaya" at={t.at(17, 7.4)} rotate={6} style={{ position: "absolute", left: 540, top: 760 }} />
      </Scene>

      {/* 3. 45% ring */}
      <Scene name="real" from={S.real[0]} to={S.real[1]}>
        <div style={full}>
          <Ring value={45} at={S.real[0] + 3} countFrames={Math.max(12, t.at(21, 9.3) - S.real[0])} size={280} label="ko business asli nahi lagta" sub="US survey, 2025 · DreamHost" />
        </div>
        <Sticker text="asli hai?" at={t.at(25, 11.1)} rotate={-7} style={{ position: "absolute", left: 600, top: 760 }} />
      </Scene>

      {/* 4. Search: you are missing, the competitor shows up */}
      <Scene name="google" from={S.google[0]} to={S.google[1]}>
        <div style={full}>
          <SearchMock at={S.google[0]} query="mithai shop near me" typeTo={t.at(33, 14.5)} missAt={t.at(35, 15.5)} hitAt={t.at(38, 16.4)} you="Aapki dukaan" them="Competitor Sweets" />
        </div>
      </Scene>

      {/* 5. Twist: ₹50,000? gets slashed */}
      <Scene name="twist" from={S.twist[0]} to={S.twist[1]}>
        <div style={{ ...full, top: SAFE.top + 40 }}>
          <WordsIn text="Website mehengi hoti hai?" accent={["mehengi"]} at={S.twist[0]} size={104} />
        </div>
        <div style={{ ...col, top: 560 }}>
          <PriceSlash text="₹50,000?" at={t.at(41, 18.3)} slashAt={t.at(43, 19.1)} />
        </div>
        <Sticker text="bilkul nahi" at={t.at(43, 19.1) + 6} rotate={-8} style={{ position: "absolute", left: 540, top: 820 }} />
      </Scene>

      {/* 6. Offer: price counts up to ₹5,000 with a burst */}
      <Scene name="offer" from={S.offer[0]} to={S.offer[1]}>
        <div style={{ ...left, top: 300 }}>
          <WordsIn text="Aapke business ki website" accent={["website"]} at={S.offer[0]} size={70} stagger={2} />
        </div>
        <Burst at={t.at(52, 23.3)} x={300} y={680} />
        <div style={{ ...left, top: 610 }}>
          <BigNumber prefix="₹" to={5000} at={S.offer[0] + 10} countFrames={Math.max(10, t.at(52, 23.3) - S.offer[0] - 10)} size={124} />
        </div>
        <div style={{ ...left, top: 770 }}>
          <WordsIn text="sirf, ready-made" accent={["sirf"]} at={t.at(51, 23)} size={46} />
        </div>
      </Scene>

      {/* 7. Features: ticks draw while the phone highlights each part */}
      <Scene name="features" from={S.features[0]} to={S.features[1]}>
        <div style={{ ...left, top: 300 }}>
          <FeatureCard />
        </div>
      </Scene>

      {/* Phone that builds a website, kept on screen across offer + features */}
      <Scene from={S.offer[0]} to={S.end[0]}>
        <div style={{ position: "absolute", left: 600, top: 290 }}>
          <PhoneSite
            at={S.offer[0] + 2}
            name="Sharma Sweets"
            build={{ nav: build0, hero: build0 + 5, rates: build0 + 10, whatsapp: build0 + 15, map: build0 + 20 }}
            focus={[
              { at: t.at(54, 24.3), key: "phone" },
              { at: t.at(57, 25.8), key: "whatsapp" },
              { at: t.at(59, 26.9), key: "map" },
              { at: t.at(64, 28.9), key: "rates" },
            ]}
          />
        </div>
      </Scene>

      {/* 8. CTA: the keyword gets typed and posted */}
      <Scene name="end" from={S.end[0]} to={S.end[1] + 30 /* no exit: last frame stays sharp for the loop */}>
        <div style={full}>
          <CommentCTA at={S.end[0]} keyword="WEBSITE" lead="Website banwani hai?" sub="Hum DM karenge" typeAt={t.at(66, 30)} sendAt={t.at(68, 31.3)} />
        </div>
      </Scene>

      {avatar !== "none" ? (
        <div style={{ position: "absolute", left: AVATAR_BOX.x, top: AVATAR_BOX.y }}>
          <Avatar style={avatar} mouth={m} cues={poseCues} size={AVATAR_BOX.size} />
        </div>
      ) : null}
      <div style={{ position: "absolute", left: SAFE_BOX.x, width: SAFE_BOX.width, bottom: CAPTION_BOTTOM }}>
        <Karaoke words={words} perChunk={4} keywords={["website", "customer", "39%", "45%", "₹5,000", "Google"]} />
      </div>
    </ReelFrame>
  );
};
