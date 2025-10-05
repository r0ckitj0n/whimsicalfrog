#!/usr/bin/env node
// zindex-migration.mjs
//
// Audits CSS for hardcoded z-index values and optionally migrates them
// to the unified variable system defined in src/styles/z-index.css.
//
// Usage:
//   node scripts/dev/zindex-migration.mjs            # dry run (report only)
//   node scripts/dev/zindex-migration.mjs --write    # apply safe replacements
//
// Strategy:
// - Scan css in: src/styles/** and css/** (globbed)
// - Find lines like: z-index: <number> not already using var(...)
// - Map well-known values to variables
// - For unknown values, report with file:line and leave unchanged (both modes)

import fs from 'node:fs';
import path from 'node:path';
import { glob } from 'glob';

const args = new Set(process.argv.slice(2));
const WRITE = args.has('--write');

const ROOT = process.cwd();
const PATTERNS = [
  'src/styles/**/*.css',
  'css/**/*.css',
];

// Known mappings (expand as needed)
const MAPPINGS = new Map([
  // Global popups / toasts
  [/\bz-index\s*:\s*(?:999999|1000000)\b/g, 'z-index: var(--z-global-popup, 100200)'],
  // Detailed item modal
  [/\bz-index\s*:\s*100300\b/g, 'z-index: var(--z-detailed-item-modal, 100300)'],
  // Global popup common
  [/\bz-index\s*:\s*100200\b/g, 'z-index: var(--z-global-popup, 100200)'],
  // Admin overlays
  [/\bz-index\s*:\s*10101\b/g, 'z-index: var(--wf-admin-overlay-content-z, 10101)'],
  [/\bz-index\s*:\s*10051\b/g, 'z-index: var(--z-overlay-content, 10051)'],
  [/\bz-index\s*:\s*10050\b/g, 'z-index: var(--wf-admin-overlay-z, var(--z-overlay, 10050))'],
  // Common admin tiers
  [/\bz-index\s*:\s*2000\b/g, 'z-index: var(--z-admin-nav, 2000)'],
  [/\bz-index\s*:\s*10000\b/g, 'z-index: var(--z-index-toast, 10000)'],
  [/\bz-index\s*:\s*420\b/g, 'z-index: var(--z-index-modal-content, 420)'],
  // Room modal ranges (leave most alone; cautious mapping for 2400-2500 if present)
  [/\bz-index\s*:\s*2500\b/g, 'z-index: var(--z-index-checkout-overlay, 2500)'],
  [/\bz-index\s*:\s*2450\b/g, 'z-index: var(--z-index-room-modal-header, 2450)'],
  [/\bz-index\s*:\s*2400\b/g, 'z-index: var(--z-index-room-modal, 2400)'],
]);

const HARD_CODED_RE = /z-index\s*:\s*(?!var\()([0-9]{2,})\b/g; // numbers, not var(...)

function applyMappings(content) {
  let changed = content;
  for (const [regex, replacement] of MAPPINGS) {
    changed = changed.replace(regex, replacement);
  }
  return changed;
}

function findHardcoded(content) {
  const out = [];
  const lines = content.split(/\r?\n/);
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    let m;
    HARD_CODED_RE.lastIndex = 0;
    while ((m = HARD_CODED_RE.exec(line))) {
      out.push({ line: i + 1, value: m[1], text: line.trim() });
    }
  }
  return out;
}

async function main() {
  const files = await glob(PATTERNS, { cwd: ROOT, nodir: true, absolute: true });
  let totalHard = 0;
  let totalChanged = 0;

  for (const file of files) {
    const raw = fs.readFileSync(file, 'utf8');
    const hard = findHardcoded(raw);
    if (hard.length) {
      totalHard += hard.length;
      console.log(`\n[Z-INDEX] ${path.relative(ROOT, file)} — ${hard.length} hardcoded occurrences`);
      for (const h of hard) {
        console.log(`  L${h.line}: ${h.text}`);
      }
      if (WRITE) {
        const changed = applyMappings(raw);
        if (changed !== raw) {
          fs.writeFileSync(file, changed, 'utf8');
          console.log(`  -> Updated via known mappings.`);
          totalChanged++;
        } else {
          console.log(`  -> No known mapping; left unchanged (manual review required).`);
        }
      } else {
        console.log(`  -> Dry-run (no changes).`);
      }
    }
  }

  console.log(`\n[Z-INDEX] Scan complete. Hardcoded occurrences: ${totalHard}. Files changed: ${totalChanged}${WRITE ? ' (write mode)' : ' (dry-run)'}\n`);
}

main().catch(err => {
  console.error('[Z-INDEX] Migration error:', err);
  process.exit(1);
});
