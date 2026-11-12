// Fonts are loaded from local files in public/fonts/ (no internet needed, works behind
// proxies). Remotion waits for them before it renders a frame.
// Want another font? Put its .woff2 files in public/fonts/ (e.g. from the @fontsource
// npm packages or Google Fonts), add an entry to FACES and a name to FONTS.
import { continueRender, delayRender, staticFile } from "remotion";

const FACES: { family: string; file: string; weights: number[] }[] = [
  { family: "Inter", file: "inter", weights: [400, 600, 800] },
  { family: "Space Grotesk", file: "space-grotesk", weights: [400, 600, 700] },
  { family: "Nunito", file: "nunito", weights: [400, 600, 800] },
];

if (typeof document !== "undefined" && typeof FontFace !== "undefined") {
  const handle = delayRender("Loading local fonts");
  Promise.all(
    FACES.flatMap(({ family, file, weights }) =>
      weights.map((w) => {
        const face = new FontFace(family, `url(${staticFile(`fonts/${file}-latin-${w}-normal.woff2`)}) format("woff2")`, {
          weight: String(w),
          style: "normal",
        });
        (document.fonts as unknown as { add(f: FontFace): void }).add(face);
        return face.load();
      }),
    ),
  )
    .catch((e) => console.error("Font failed to load:", e))
    .finally(() => continueRender(handle));
}

export const FONTS = {
  Inter: "Inter",
  SpaceGrotesk: "Space Grotesk",
  Nunito: "Nunito",
};

export type FontName = keyof typeof FONTS;
