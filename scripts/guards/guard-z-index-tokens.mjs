#!/usr/bin/env node

// Guard: enforce z-index token usage across CSS
// Rules:
//  - Disallow raw numeric z-index declarations (e.g., z-index: 9999) outside allowlists
//  - Disallow deprecated variables: --wf-admin-overlay-z and any var(--z-index-*) usage outside z-index.css
//  - Allow any var(--z-*) tokens (e.g., --z-admin-overlay, --z-overlay-topmost)

import fs from 'fs';
import path from 'path';

const ROOT = process.cwd();
const INCLUDE_DIRS = ['src/styles'];
const EXCLUDES = ['node_modules', '.git', 'dist'];
const TOKENS_FILE = path.join(ROOT, 'src/styles/z-index.css');

// Regexes
const RE_NUMERIC_Z = /z-index\s*:\s*(\d+)/i;
const RE_DEPRECATED_VAR = /z-index\s*:\s*var\(\s*--(?:wf-admin-overlay-z|z-index-[^)]+)\s*\)/i;
const RE_ANY_VAR = /z-index\s*:\s*var\(\s*--([^\s,)]+)\s*\)/i;

let errors = 0;

function walk(dir, out = []) {
  const ents = fs.readdirSync(dir, { withFileTypes: true });
  for (const ent of ents) {
    const p = path.join(dir, ent.name);
    if (EXCLUDES.some((x) => p.includes(x))) continue;
    if (ent.isDirectory()) walk(p, out);
    else if (ent.isFile() && /\.(css|scss)$/i.test(ent.name)) out.push(p);
  }
  return out;
}

function checkFile(file) {
  const text = fs.readFileSync(file, 'utf8');
  const rel = path.relative(ROOT, file);

  // Skip the tokens definition file for deprecated var checks
  const isTokensFile = path.resolve(file) === path.resolve(TOKENS_FILE);

  // Rule: Disallow numeric z-index
  const numericMatches = text.match(new RegExp(RE_NUMERIC_Z, 'gi')) || [];
  numericMatches.forEach((m) => {
    // Allow "0" as neutral in some contexts
    const val = Number(String(m).replace(/[^0-9]/g, ''));
    if (val === 0) return;
    errors++;
    console.error(`[z-index-guard] Numeric z-index detected in ${rel}: ${m.trim()}`);
  });

  // Rule: Disallow deprecated variables (outside tokens file)
  if (!isTokensFile) {
    const depMatches = text.match(new RegExp(RE_DEPRECATED_VAR, 'gi')) || [];
    depMatches.forEach((m) => {
      errors++;
      console.error(`[z-index-guard] Deprecated z-index variable in ${rel}: ${m.trim()}`);
    });
  }

  // Optional: Suggest tokens when any var is used
  const anyVarMatches = text.match(new RegExp(RE_ANY_VAR, 'gi')) || [];
  anyVarMatches.forEach((m) => {
    const name = (m.match(/--([^\s,)]+)/) || [])[1] || '';
    if (!name) return;
    // Accept token prefix --z-*, flag others (excluding custom calc contexts)
    if (!name.startsWith('z-')) {
      // Ignore known safe CSS vars unrelated to z-index
      if (/^(brand-|wf-|admin-)/.test(name)) return;
      // If this is inside tokens file, skip
      if (isTokensFile) return;
      // Warn only (not error) to help gradual migration
      console.warn(`[z-index-guard] Non-token z-index variable used in ${rel}: --${name}`);
    }
  });
}

for (const dir of INCLUDE_DIRS) {
  const abs = path.join(ROOT, dir);
  if (!fs.existsSync(abs)) continue;
  walk(abs).forEach(checkFile);
}

if (errors) {
  const strict = process.env.WF_ZINDEX_STRICT === '1';
  const msg = `[z-index-guard] Found ${errors} z-index issue(s).${strict ? ' (strict mode)' : ' (non-strict, warning only)'}`;
  if (strict) {
    console.error(msg);
    process.exit(1);
  } else {
    console.warn(msg);
    process.exit(0);
  }
} else {
  console.log('[z-index-guard] All good: z-index tokens in use.');
}
