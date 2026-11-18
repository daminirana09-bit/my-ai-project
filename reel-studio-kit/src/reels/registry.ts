// Every reel the studio and the renderer know about. `npm run new -- <id>` adds a line here.
import type React from "react";
import type { ReelProps } from "../components";
import type { Word } from "../timing";
import { PRESETS } from "../presets";
import * as demo from "./demo/Reel";
import * as r_website_zaroori from "./website-zaroori/Reel";
import * as r_dentist_digital from "./dentist-digital/Reel";
import * as r_desk from "./desk-text/Reel";
import * as r_pharma_dialogue from "./pharma-dialogue/Reel";
import * as r_clinic_dialogue from "./clinic-dialogue/Reel";
import * as r_pharma_trust from "./pharma-trust/Reel";
import * as r_realestate_leaks from "./realestate-leaks/Reel";
import * as r_cafe_makeover from "./cafe-makeover/Reel";
import * as r_hotel_makeover from "./hotel-makeover/Reel";
import * as r_google_profile from "./google-profile/Reel";
import * as r_solvix_crm from "./solvix-crm/Reel";
import * as r_solvix_meta_update from "./solvix-meta-update/Reel";
import * as r_solvix_restaurant_qr from "./solvix-restaurant-qr/Reel";
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
  { id: "pharma-dialogue", Component: r_pharma_dialogue.Reel, durationInFrames: r_pharma_dialogue.durationInFrames },
  { id: "clinic-dialogue", Component: r_clinic_dialogue.Reel, durationInFrames: r_clinic_dialogue.durationInFrames },
  { id: "pharma-trust", Component: r_pharma_trust.Reel, durationInFrames: r_pharma_trust.durationInFrames },
  { id: "realestate-leaks", Component: r_realestate_leaks.Reel, durationInFrames: r_realestate_leaks.durationInFrames },
  { id: "cafe-makeover", Component: r_cafe_makeover.Reel, durationInFrames: r_cafe_makeover.durationInFrames },
  { id: "hotel-makeover", Component: r_hotel_makeover.Reel, durationInFrames: r_hotel_makeover.durationInFrames },
  { id: "google-profile", Component: r_google_profile.Reel, durationInFrames: r_google_profile.durationInFrames },
  { id: "solvix-crm", Component: r_solvix_crm.Reel, durationInFrames: r_solvix_crm.durationInFrames },
  { id: "solvix-meta-update", Component: r_solvix_meta_update.Reel, durationInFrames: r_solvix_meta_update.durationInFrames },
  { id: "solvix-restaurant-qr", Component: r_solvix_restaurant_qr.Reel, durationInFrames: r_solvix_restaurant_qr.durationInFrames },
  // <new-reel-entries>
];

export type { Word };
