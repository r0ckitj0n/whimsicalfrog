#!/usr/bin/env node
/**
 * Guard image naming and pairing rules
 *
 * Rules:
 * 1) No spaces in filenames under images/ or src/assets/.
 * 2) No uppercase letters outside images/items/ (SKU images are uppercase-allowed).
 * 3) For these folders, require both webp and png variants for each basename:
 *    - images/backgrounds/
 *    - images/items/
 *    - images/signs/
 *    - images/logos/
 *    (Other folders are ignored for pairing rules.)
 * 4) backgrounds must be lowercase and start with background_ (e.g., background_room1.webp)
 */

import fs from 'fs/promises';
import path from 'path';

const ROOT = process.cwd();
const TARGET_DIRS = [
  path.join(ROOT, 'images'),
  path.join(ROOT, 'src', 'assets'),
];

const PAIR_DIRS = new Set([
  path.join(ROOT, 'images', 'backgrounds'),
  path.join(ROOT, 'images', 'items'),
  path.join(ROOT, 'images', 'signs'),
  path.join(ROOT, 'images', 'logos'),
]);

const IMG_EXTS = new Set(['.png', '.webp']);

/** Recursively list files */
async function listFiles(dir) {
  const res = [];
  try {
    const entries = await fs.readdir(dir, { withFileTypes: true });
    for (const ent of entries) {
      const full = path.join(dir, ent.name);
      if (ent.isDirectory()) {
        // Skip common noisy directories
        if (ent.name === 'dist' || ent.name === '.git' || ent.name === 'node_modules' || ent.name === 'vendor') continue;
        res.push(...await listFiles(full));
      } else {
        res.push(full);
      }
    }
  } catch (e) {
    // Directory might not exist; that's fine
  }
  return res;
}

function isUnder(p, parent) {
  const rel = path.relative(parent, p);
  return !!rel && !rel.startsWith('..') && !path.isAbsolute(rel);
}

function hasUppercase(str) { return /[A-Z]/.test(str); }
function hasSpace(str) { return /\s/.test(str); }

(async () => {
  const files = [];
  for (const dir of TARGET_DIRS) {
    files.push(...await listFiles(dir));
  }

  const imageFiles = files.filter(f => IMG_EXTS.has(path.extname(f).toLowerCase()));

  const violations = [];

  // 1) No spaces
  for (const f of imageFiles) {
    const base = path.basename(f);
    if (hasSpace(base)) {
      violations.push(`[spaces] ${path.relative(ROOT, f)}`);
    }
  }

  // 2) No uppercase outside images/items/
  for (const f of imageFiles) {
    const rel = path.relative(ROOT, f);
    const base = path.basename(f);
    const allowUpper = isUnder(f, path.join(ROOT, 'images', 'items'));
    if (!allowUpper && hasUppercase(base)) {
      violations.push(`[uppercase-disallowed] ${rel}`);
    }
  }

  // 3) Pairing rules (webp <-> png)
  // Build a map per pair-dir: basename -> Set(ext)
  const pairMaps = new Map();
  for (const dir of PAIR_DIRS) pairMaps.set(dir, new Map());

  for (const f of imageFiles) {
    for (const pdir of PAIR_DIRS) {
      if (isUnder(f, pdir)) {
        const baseNoExt = path.basename(f, path.extname(f));
        const ext = path.extname(f).toLowerCase();
        const map = pairMaps.get(pdir);
        if (!map.has(baseNoExt)) map.set(baseNoExt, new Set());
        map.get(baseNoExt).add(ext);
      }
    }
  }

  for (const [dir, map] of pairMaps.entries()) {
    for (const [base, exts] of map.entries()) {
      const hasPng = exts.has('.png');
      const hasWebp = exts.has('.webp');
      if (!hasPng || !hasWebp) {
        violations.push(`[missing-pair:${path.relative(ROOT, dir)}] ${base} -> ${hasWebp ? '' : 'webp '} ${hasPng ? '' : 'png '}`.trim());
      }
    }
  }

  // 4) Backgrounds naming: lowercase and start with background-
  const bgDir = path.join(ROOT, 'images', 'backgrounds');
  for (const f of imageFiles) {
    if (isUnder(f, bgDir)) {
      const baseNoExt = path.basename(f, path.extname(f));
      if (baseNoExt !== baseNoExt.toLowerCase()) {
        violations.push(`[backgrounds-lowercase] ${path.relative(ROOT, f)}`);
      }
      if (!baseNoExt.startsWith('background-')) {
        violations.push(`[backgrounds-prefix] ${path.relative(ROOT, f)} (must start with background-)`);
      }
    }
  }

  // 5) Global: disallow underscores in image filenames under images/ and src/assets/
  for (const f of imageFiles) {
    const rel = path.relative(ROOT, f);
    const base = path.basename(f);
    if (base.includes('_')) {
      violations.push(`[no-underscores] ${rel}`);
    }
  }

  if (violations.length > 0) {
    console.error('Image guard failed with the following findings:');
    for (const v of violations) console.error(`- ${v}`);
    process.exit(1);
  }
  console.log('Image guard passed.');
})();
