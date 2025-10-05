#!/usr/bin/env node
/**
 * Guard staged CSS files for:
 * - Legacy greens (#6b8e23, #789a34)
 * - font-family not using variables (var(--font-...))
 * - Raw numeric z-index outside token files (z-index.css, variables.css)
 */

import fs from 'node:fs';

const files = process.argv.slice(2).filter(f => f.endsWith('.css'));
if (files.length === 0) process.exit(0);

const isExcluded = (file) => {
  if (file.includes('/backups/')) return true;
  if (file.includes('site-base.css.backup')) return true;
  return false;
};

const allowRawZIndexFile = (file) => {
  return file.endsWith('/src/styles/z-index.css') || file.endsWith('z-index.css') || file.endsWith('/src/styles/variables.css') || file.endsWith('variables.css');
};

const LEGACY_GREEN = /(#[6bB]8[eE]23|#789a34)/;
const FONT_FAMILY = /font-family\s*:\s*([^;]+);/g;
const HAS_FONT_VAR = /var\(--font-/;
const RAW_Z_INDEX = /z-index\s*:\s*\d+/;

let hadError = false;

for (const file of files) {
  if (isExcluded(file)) continue;
  let content = '';
  try {
    content = fs.readFileSync(file, 'utf8');
  } catch (e) {
    continue;
  }

  // A) Legacy greens
  if (LEGACY_GREEN.test(content)) {
    console.error(`[brand-colors] ${file}: contains legacy green (#6b8e23 or #789a34)`);
    hadError = true;
  }

  // B) font-family without variables (allow email/core utilities via lint-staged config, not here)
  let m;
  FONT_FAMILY.lastIndex = 0;
  while ((m = FONT_FAMILY.exec(content)) !== null) {
    const decl = m[0];
    if (!HAS_FONT_VAR.test(decl)) {
      console.error(`[fonts] ${file}: font-family without variables -> ${decl.trim()}`);
      hadError = true;
      break; // report once per file
    }
  }

  // C) Raw numeric z-index (allow token files)
  if (!allowRawZIndexFile(file) && RAW_Z_INDEX.test(content)) {
    console.error(`[z-index] ${file}: raw numeric z-index found`);
    hadError = true;
  }
}

process.exit(hadError ? 1 : 0);
