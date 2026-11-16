// Downloads background music and sound effects into public/music and public/sfx (not committed).
// All from Mixkit: music under the "Stock Music Free License", sound effects under the
// "Sound Effects Free License" (commercial use OK, no attribution required).
// Usage: npm run audio-assets
import fs from "node:fs";
import path from "node:path";
import { root } from "./env.mjs";

export const MUSIC = {
  "hiphop-02": { url: "https://assets.mixkit.co/music/738/738.mp3", title: "Hip Hop 02 — Lily J" },
  "digital-clouds": { url: "https://assets.mixkit.co/music/175/175.mp3", title: "Digital Clouds — Alejandro Magaña" },
  "sleepy-cat": { url: "https://assets.mixkit.co/music/135/135.mp3", title: "Sleepy Cat — Alejandro Magaña" },
};
const sfx = (id) => `https://assets.mixkit.co/active_storage/sfx/${id}/${id}-preview.mp3`;
export const SFX = {
  whoosh: { url: sfx(1492), title: "Cinematic whoosh fast transition" },
  pop: { url: sfx(3005), title: "Explainer video pops whoosh light pop" },
  click: { url: sfx(2364), title: "Hard pop click" },
  message: { url: sfx(2354), title: "Message pop alert" },
  buzz: { url: sfx(950), title: "Game show wrong answer buzz" },
  stamp: { url: sfx(2182), title: "Wood hard hit" },
  ring: { url: sfx(1359), title: "Marimba ringtone" },
  success: { url: sfx(2865), title: "Success software tone" },
  tick: { url: sfx(2870), title: "Correct answer tone" },
  typing: { url: sfx(1393), title: "Smartphone typing" },
  impact: { url: sfx(1143), title: "Cinematic whoosh deep impact" },
  ui: { url: sfx(2568), title: "Cool interface click tone" },
};

for (const [dirName, list] of [["music", MUSIC], ["sfx", SFX]]) {
  const dir = path.join(root, "public", dirName);
  fs.mkdirSync(dir, { recursive: true });
  for (const [name, a] of Object.entries(list)) {
    const out = path.join(dir, `${name}.mp3`);
    if (fs.existsSync(out)) { console.log(`have ${dirName}/${name}`); continue; }
    const res = await fetch(a.url);
    if (!res.ok) { console.error(`failed ${dirName}/${name}: ${res.status}`); continue; }
    fs.writeFileSync(out, Buffer.from(await res.arrayBuffer()));
    console.log(`got ${dirName}/${name} (${a.title})`);
  }
}
