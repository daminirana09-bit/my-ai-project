// Reel "website-zaroori" (~33 s): businesses without a website -> get one made for ₹5,000.
// Script, sources: projects/website-zaroori/script.md. Word times: public/reels/website-zaroori/captions.words.json
// (made by `npm run voice:timed -- website-zaroori`). Word numbers below refer to that file (0 = first word).
import React from "react";
import { brand as myBrand } from "../../brand";
import { Avatar } from "../../avatars/Avatar";
import { useMouth } from "../../avatars/useMouth";
import type { PoseCue } from "../../avatars/SpritePlayer";
import { BigNumber, Card, EndCard, Karaoke, Line, ReelFrame, SceneBlock, Sticker, Title, type ReelProps } from "../../components";
import { AVATAR_BOX, CAPTION_BOTTOM, SAFE, SAFE_BOX } from "../../layout";
import { timing } from "../../timing";
import mouth from "../../../public/reels/website-zaroori/mouth.json";
import words from "../../../public/reels/website-zaroori/captions.words.json";

export const t = timing(words);
export const AUDIO = "reels/website-zaroori/vo.wav";
export const durationInFrames = t.duration();

// Scene cuts on spoken words (word index, fallback seconds).
const S = {
  hook: [0, t.at(6, 3)], //            "Website nahi hai? Toh customer gaya."
  survey: [t.at(6, 3), t.at(20, 9)], // "Ek survey mein 39% ..."
  real: [t.at(20, 9), t.at(29, 13)], // "Aur 45% ko ..."
  google: [t.at(29, 13), t.at(39, 17.6)], // "Customer pehle Google karta hai ..."
  twist: [t.at(39, 17.6), t.at(45, 20)], // "Aur website banwana mehenga nahi hai."
  offer: [t.at(45, 20), t.at(54, 24.3)], // "Hum ... sirf ₹5,000 mein."
  features: [t.at(54, 24.3), t.at(65, 29.6)], // "Mobile pe perfect, WhatsApp button, ..."
  end: [t.at(65, 29.6), t.duration()], // "Comment karo WEBSITE — hum DM karenge."
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

const top = 300;
const box: React.CSSProperties = { position: "absolute", left: SAFE_BOX.x, top, width: SAFE_BOX.width };

export const Reel: React.FC<ReelProps> = ({ brand = myBrand, avatar = "flat", debugSafeZones, probe }) => {
  const m = useMouth(mouth, words);
  const c = brand.colors;
  const small: React.CSSProperties = { fontSize: 30, fontWeight: 600, opacity: 0.55, marginTop: 28 };
  const no = <span style={{ color: "#E5484D" }}>✕</span>;
  const yes = <span style={{ color: c.accent2 }}>✓</span>;
  return (
    <ReelFrame brand={brand} audio={AUDIO} debugSafeZones={debugSafeZones} probe={probe}>
      {/* 1. Hook */}
      <SceneBlock name="hook" from={S.hook[0]} to={S.hook[1]}>
        <div style={{ ...box, top: SAFE.top + 40 }}>
          <Title text="Website nahi hai? Customer gaya." highlight="Customer gaya." at={t.at(0)} size={112} />
        </div>
        <Sticker text="no website = no trust" at={t.at(4, 1.7)} rotate={-6} style={{ position: "absolute", left: 360, top: 700 }} />
      </SceneBlock>

      {/* 2. 39% did not buy */}
      <SceneBlock name="survey" from={S.survey[0]} to={S.survey[1]}>
        <div style={box}>
          <Card at={S.survey[0]}>
            <BigNumber to={39} suffix="%" at={S.survey[0] + 4} countFrames={t.at(9, 4.2) - S.survey[0]} size={230} tracked={false} label={<>ne kharida<br />hi nahi</>} />
            <div style={{ fontSize: 44, fontWeight: 700, marginTop: 28 }}>kyunki website nahi thi</div>
            <div style={small}>US survey, 2025 · DreamHost</div>
          </Card>
        </div>
        <Sticker text="customer gaya" at={t.at(17, 7.4)} rotate={6} style={{ position: "absolute", left: 560, top: 800 }} />
      </SceneBlock>

      {/* 3. 45% feel it is not real */}
      <SceneBlock name="real" from={S.real[0]} to={S.real[1]}>
        <div style={box}>
          <Card at={S.real[0]}>
            <BigNumber to={45} suffix="%" at={S.real[0] + 3} countFrames={Math.max(10, t.at(21, 9.3) - S.real[0])} size={230} tracked={false} label={<>ko business<br />asli nahi lagta</>} />
            <div style={small}>US survey, 2025 · DreamHost</div>
          </Card>
        </div>
        <Sticker text="asli hai?" at={t.at(25, 11.1)} rotate={-7} style={{ position: "absolute", left: 600, top: 760 }} />
      </SceneBlock>

      {/* 4. Customers google first */}
      <SceneBlock name="google" from={S.google[0]} to={S.google[1]}>
        <div style={box}>
          <Card at={S.google[0]}>
            <Line first at={S.google[0] + 4}>
              <span style={{ opacity: 0.55 }}>Google:</span> "cake shop near me"
            </Line>
            <Line at={t.at(34, 15.2)}>{no} Aapki dukaan: nahi mili</Line>
            <Line at={t.at(37, 16.3)}>{yes} Competitor: mil gaya</Line>
          </Card>
        </div>
      </SceneBlock>

      {/* 5. Twist: it is not expensive */}
      <SceneBlock name="twist" from={S.twist[0]} to={S.twist[1]}>
        <div style={{ ...box, top: SAFE.top + 40 }}>
          <Title text="Website mehengi hoti hai?" highlight="mehengi" at={t.at(39, 17.6)} size={104} showHandle={false} />
        </div>
        <div style={{ position: "absolute", left: SAFE_BOX.x, top: 560 }}>
          <Card at={t.at(41, 18.3)} style={{ padding: "28px 44px" }}>
            <div style={{ fontSize: 120, fontWeight: 800, textDecoration: "line-through", textDecorationColor: "#E5484D", textDecorationThickness: 12, opacity: 0.8 }}>₹50,000?</div>
          </Card>
        </div>
        <Sticker text="bilkul nahi" at={t.at(43, 19.1)} rotate={-8} style={{ position: "absolute", left: 600, top: 760 }} />
      </SceneBlock>

      {/* 6. Offer */}
      <SceneBlock name="offer" from={S.offer[0]} to={S.offer[1]}>
        <div style={box}>
          <Card at={S.offer[0]}>
            <div style={{ fontSize: 52, fontWeight: 700 }}>Aapke business ki website</div>
            <div style={{ height: 24 }} />
            <BigNumber prefix="₹" to={5000} at={S.offer[0] + 6} countFrames={Math.max(10, t.at(52, 23.3) - S.offer[0] - 6)} size={200} tracked={false} />
            <div style={{ fontSize: 48, fontWeight: 700, marginTop: 16 }}>mein ready</div>
          </Card>
        </div>
        <Sticker text="sirf" at={t.at(51, 23)} rotate={-10} style={{ position: "absolute", left: 640, top: 250 }} />
      </SceneBlock>

      {/* 7. What you get */}
      <SceneBlock name="features" from={S.features[0]} to={S.features[1]}>
        <div style={box}>
          <Card at={S.features[0]}>
            <Line first at={t.at(54, 24.3)}>{yes} Mobile pe perfect</Line>
            <Line at={t.at(57, 25.8)}>{yes} WhatsApp button</Line>
            <Line at={t.at(59, 26.9)}>{yes} Google Map location</Line>
            <Line at={t.at(62, 28.3)}>{yes} Aapke rate</Line>
          </Card>
        </div>
        <Sticker text="₹5,000 mein" at={t.at(62, 28.3)} rotate={7} style={{ position: "absolute", left: 600, top: 250 }} />
      </SceneBlock>

      {/* 8. CTA */}
      <SceneBlock name="end" from={S.end[0]} to={S.end[1]}>
        <div style={box}>
          <EndCard keyword="WEBSITE" lead="Website banwani hai?" sub="Hum DM karenge" at={t.at(65, 29.6)} />
        </div>
      </SceneBlock>

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
