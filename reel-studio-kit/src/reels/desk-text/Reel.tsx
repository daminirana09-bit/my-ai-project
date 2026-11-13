// Text-only "late-night desk" reels (no voice: add trending music in Instagram).
//   desk-hiring  : looking for digital marketers to work with
//   desk-website : business owners without a website -> ₹5,000 offer
import React from "react";
import { DeskScene, TextLines } from "../../components/desk";
import { SceneBlock, type ReelProps } from "../../components";
import { LayoutProbe } from "../../components/probe";
import { SafeZones } from "../../components/SafeZones";

export const durationInFrames = 9 * 30;

const Frame: React.FC<ReelProps & { lines: string[]; bold?: string[] }> = ({ lines, bold, debugSafeZones, probe }) => (
  <div style={{ position: "absolute", inset: 0 }}>
    <DeskScene />
    <SceneBlock name="text" from={0} to={durationInFrames}>
      <TextLines lines={lines} bold={bold} />
    </SceneBlock>
    {debugSafeZones ? <SafeZones /> : null}
    {probe ? <LayoutProbe /> : null}
  </div>
);

export const Hiring: React.FC<ReelProps> = (p) => (
  <Frame {...p} lines={["If you're someone", "doing digital marketing,", "social media management", "and ads running,", "comment below —", "we need you."]} bold={["we need you."]} />
);

export const Website: React.FC<ReelProps> = (p) => (
  <Frame {...p} lines={["Agar aapka business hai", "aur abhi tak", "website nahi hai —", "comment karo WEBSITE,", "₹5,000 mein", "hum bana denge."]} bold={["comment karo WEBSITE,"]} />
);
