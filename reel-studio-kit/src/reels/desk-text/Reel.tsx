// Text-only "late-night desk" reels (no voice: add trending music in Instagram).
// One component, many texts. Each client reel targets one kind of business with its own comment keyword,
// so each can get its own comment-to-DM automation.
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

type DeskText = { id: string; keyword: string; lines: string[]; bold: string };

// Reach (not clients): marketers comment.
export const Hiring: React.FC<ReelProps> = (p) => (
  <Frame {...p} lines={["If you're someone", "doing digital marketing,", "social media management", "and ads running,", "comment below —", "we need you."]} bold={["we need you."]} />
);

// Clients: one per business type.
export const CLIENT_TEXTS: DeskText[] = [
  { id: "desk-website", keyword: "WEBSITE", lines: ["Agar aapka business hai", "aur abhi tak", "website nahi hai —", "comment karo WEBSITE,", "₹5,000 mein", "hum bana denge."], bold: "comment karo WEBSITE," },
  { id: "desk-google", keyword: "GOOGLE", lines: ["Customer aapko", "Google pe dhoondh raha hai —", "par aap mil nahi rahe.", "Comment karo GOOGLE,", "free mein batayenge", "kya theek karna hai."], bold: "Comment karo GOOGLE," },
  { id: "desk-clinic", keyword: "CLINIC", lines: ["Doctor ho,", "aur clinic ki website", "abhi tak nahi hai?", "Comment karo CLINIC —", "rules ke andar", "hum bana denge."], bold: "Comment karo CLINIC —" },
  { id: "desk-menu", keyword: "MENU", lines: ["Restaurant ya cafe hai?", "Menu, location, WhatsApp —", "sab ek website pe.", "Comment karo MENU,", "₹5,000 mein ready."], bold: "Comment karo MENU," },
  { id: "desk-salon", keyword: "SALON", lines: ["Salon ya parlour hai?", "Rate list website pe,", "booking WhatsApp pe.", "Comment karo SALON —", "₹5,000 mein", "hum bana denge."], bold: "Comment karo SALON —" },
  { id: "desk-guess", keyword: "WEBSITE", lines: ["Website na hone se", "roz kitne customer", "chale jaate hain?", "Comment mein guess karo.", "Aur WEBSITE likho —", "₹5,000 mein bana denge."], bold: "Aur WEBSITE likho —" },
];

export const CLIENT_REELS = CLIENT_TEXTS.map((d) => ({
  id: d.id,
  Component: ((p: ReelProps) => <Frame {...p} lines={d.lines} bold={[d.bold]} />) as React.FC<ReelProps>,
}));
