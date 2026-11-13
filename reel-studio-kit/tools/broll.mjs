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
