// Downloads the stock B-roll clips used by the card-story reels into public/broll/ (not committed).
// All clips are from Mixkit under the "Stock Video Free License" (commercial use OK, no attribution
// required). Check each new clip's license on its Mixkit page before adding it: "Restricted" clips are
// personal-use only.
// Usage: npm run broll
import fs from "node:fs";
import path from "node:path";
import { root } from "./env.mjs";

export const CLIPS = {
  "dentist-ready": { id: 12148, page: "dentist-getting-ready-for-work-12148" },
  "phone-scroll": { id: 4801, page: "young-man-sitting-scrolling-on-his-cell-phone-4801" },
  "researching": { id: 4713, page: "woman-researching-on-the-computer-4713" },
  "reception": { id: 4766, page: "clinic-staff-at-a-hospital-reception-4766" },
  "xrays": { id: 12140, page: "checking-dental-x-rays-12140" },
  "typing-close": { id: 1781, page: "typing-on-a-laptop-close-up-1781" },
  "laptop-man": { id: 308, page: "man-working-on-his-laptop-308" },
  "dentist-checking": { id: 12146, page: "dentist-checking-teeth-12146" },
  // cafe
  "cafe-tea-prep": { id: 41864, page: "table-of-a-coffee-shop-a-girl-preparing-tea-41864" },
  "cafe-waiter": { id: 205, page: "a-waiter-serves-coffee-to-a-customer-205" },
  "cafe-busy": { id: 4385, page: "customers-in-a-minimalist-style-restaurant-4385" },
  "cafe-drinking": { id: 223, page: "woman-drinking-coffee-in-a-cafe-223" },
  "cafe-counter": { id: 41222, page: "two-girls-chatting-at-the-counter-of-a-coffee-shop-41222" },
  // hotel
  "hotel-room": { id: 4198, page: "pan-shot-of-the-interior-of-a-hotel-room-4198" },
  "hotel-mountains": { id: 41545, page: "flying-over-tree-covered-mountains-41545" },
  "hotel-clouds": { id: 4695, page: "clouds-covering-the-mountains-4695" },
  "hotel-terrace": { id: 4029, page: "interior-of-a-room-with-terrace-4029" },
};

const dir = path.join(root, "public/broll");
fs.mkdirSync(dir, { recursive: true });
for (const [name, c] of Object.entries(CLIPS)) {
  const out = path.join(dir, `${name}.mp4`);
  if (fs.existsSync(out)) { console.log(`have ${name}`); continue; }
  const res = await fetch(`https://assets.mixkit.co/videos/${c.id}/${c.id}-720.mp4`);
  if (!res.ok) { console.error(`failed ${name}: ${res.status} (https://mixkit.co/free-stock-video/${c.page}/)`); continue; }
  fs.writeFileSync(out, Buffer.from(await res.arrayBuffer()));
  console.log(`got ${name} (${(fs.statSync(out).size / 1e6).toFixed(1)} MB)`);
}
