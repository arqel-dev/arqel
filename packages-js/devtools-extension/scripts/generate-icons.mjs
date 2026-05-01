#!/usr/bin/env node
/**
 * Generate PNG icons for the extension from the master SVG.
 *
 * Run this once you have a rasterizer available (e.g. `sharp`, ImageMagick,
 * or `rsvg-convert`). Until then, the build step copies the SVG bytes into
 * the `.png` paths so the manifest validates and the unpacked extension can
 * be loaded for development.
 *
 * Example using sharp (add `sharp` to devDependencies before running):
 *
 *   import sharp from 'sharp';
 *   import { readFileSync, writeFileSync } from 'node:fs';
 *
 *   const svg = readFileSync(new URL('../src/icons/icon.svg', import.meta.url));
 *   for (const size of [16, 32, 48, 128]) {
 *     const png = await sharp(svg).resize(size, size).png().toBuffer();
 *     writeFileSync(new URL(`../src/icons/icon-${size}.png`, import.meta.url), png);
 *   }
 */
console.warn(
  '[arqel-devtools] generate-icons placeholder — install a rasterizer (sharp/rsvg) to produce real PNGs.',
);
