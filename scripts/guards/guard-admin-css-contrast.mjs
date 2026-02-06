#!/usr/bin/env node
/**
 * Guard: Admin CSS Contrast Safety
 * - Ensures that critical CSS overrides for admin modal input visibility are present.
 * - Prevents accidental regression of white-on-white text issues in admin tools.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const repoRoot = process.cwd();
const targetFile = 'src/styles/components/admin-modals.css';
const fullPath = path.join(repoRoot, targetFile);

// Critical CSS patterns that MUST exist
const REQUIRED_PATTERNS = [
  {
    name: 'Admin Modal Input Text Color Override',
    // Look for the specific high-specificity selector we added
    regex: /\.admin-modal\s+input:not\(\[type="submit"\]\)/,
    description: 'Missing selector targeting inputs in admin modals.'
  },
  {
    name: 'Admin Modal Table Text Color Override',
    regex: /\.admin-modal\s+\.admin-table\s+td/,
    description: 'Missing selector targeting table cells in admin modals.'
  },
  {
    name: 'Force Dark Text Color',
    // Look for color: #111827 !important or var(--admin-modal-text-color... !important
    regex: /color:\s*(?:#111827|var\(--admin-modal-text-color[^)]+\))\s*!important/,
    description: 'Missing "color: ... !important" enforcement for inputs/cells.'
  },
  {
    name: 'Force White Background',
    regex: /background-color:\s*#ffffff/,
    description: 'Missing "background-color: #ffffff" enforcement.'
  }
];

if (!fs.existsSync(fullPath)) {
  console.error(`[guard-admin-css-contrast] FATAL: ${targetFile} not found.`);
  process.exit(1);
}

const content = fs.readFileSync(fullPath, 'utf8');
const errors = [];

// Improved check: Look for the specific block introduced
const BLOCK_SIGNATURE = 'CRITICAL VISIBILITY FIX: Force dark text on inputs inside white modals';

if (!content.includes(BLOCK_SIGNATURE)) {
  errors.push(`Missing critical comment signature: "${BLOCK_SIGNATURE}"`);
}

for (const pattern of REQUIRED_PATTERNS) {
  if (!pattern.regex.test(content)) {
    errors.push(`${pattern.name}: ${pattern.description}`);
  }
}

if (errors.length > 0) {
  console.error('\n[guard-admin-css-contrast] ❌ Admin CSS safety check failed!');
  console.error(`File: ${targetFile}`);
  errors.forEach(err => console.error(` - ${err}`));
  console.error('\nPlease restore the critical visibility fix in src/styles/components/admin-modals.css');
  process.exit(1);
}

console.log('[guard-admin-css-contrast] ✅ Admin input visibility rules confirmed.');
process.exit(0);
