// Every reel the studio and the renderer know about. `npm run new -- <id>` adds a line here.
import type React from "react";
import type { ReelProps } from "../components";
import type { Word } from "../timing";
import { PRESETS } from "../presets";
import * as demo from "./demo/Reel";
import * as r_website_zaroori from "./website-zaroori/Reel";
import * as r_dentist_digital from "./dentist-digital/Reel";
import * as r_desk from "./desk-text/Reel";
// <new-reel-imports>

export type ReelEntry = {
  id: string;
  Component: React.FC<ReelProps>;
  durationInFrames: number;
  /** Extra compositions of the same reel with other props, e.g. another avatar style or brand preset. */
  variants?: { suffix: string; props: ReelProps }[];
};

export const REELS: ReelEntry[] = [
  {
    id: "Demo",
    Component: demo.Reel,
    durationInFrames: demo.durationInFrames,
    variants: [
      { suffix: "Illustrated", props: { avatar: "illustrated" } },
      { suffix: "Pixel", props: { avatar: "pixel" } },
      { suffix: "BoldDark", props: { brand: PRESETS.boldDark } },
      { suffix: "SoftPastel", props: { brand: PRESETS.softPastel } },
    ],
  },
  { id: "website-zaroori", Component: r_website_zaroori.Reel, durationInFrames: r_website_zaroori.durationInFrames },
  { id: "dentist-digital", Component: r_dentist_digital.Reel, durationInFrames: r_dentist_digital.durationInFrames },
  { id: "desk-hiring", Component: r_desk.Hiring, durationInFrames: r_desk.durationInFrames },
  ...r_desk.CLIENT_REELS.map((r) => ({ ...r, durationInFrames: r_desk.durationInFrames })),
  // <new-reel-entries>
];

export type { Word };
