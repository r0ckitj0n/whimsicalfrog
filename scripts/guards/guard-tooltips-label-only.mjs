#!/usr/bin/env node
/**
 * Guard: Enforce label-only tooltips
 * - Forbid tooltip attributes on input/select/textarea (title, data-tooltip, data-help, data-help-id, data-hint)
 * - Encourages attaching help to labels or label-like elements instead
 * - Scans PHP, HTML, JS/TS/JSX/TSX files under app sources (excludes backups, node_modules, vendor)
 */

import fs from 'node:fs';
import path from 'node:path';
import { glob } from 'glob';

const ROOT = process.cwd();

const DEFAULT_INCLUDE = [
  'sections/**/*.php',
  'components/**/*.php',
  'partials/**/*.php',
  'templates/**/*.php',
  'api/**/*.php',
  '*.php',
  'src/**/*.{js,mjs,jsx,ts,tsx}',
  'public/**/*.html',
];
const DEFAULT_EXCLUDE = [
  '**/node_modules/**',
  '**/vendor/**',
  '**/backups/**',
  '**/documentation/**',
  '**/docs/**',
  '**/.git/**',
  '**/dist/**',
  '**/build/**',
  '**/.vite/**',
  '**/.cache/**',
];

const ATTR_PATTERNS = [
  'title',
  'data-tooltip',
  'data-help',
  'data-help-id',
  'data-hint',
];

// Regex: tag open for input/select/textarea with any tooltip-like attribute
const INPUT_ATTR_RE = new RegExp(
  String.raw`<(input|select|textarea)\b[^>]*\b(?:${ATTR_PATTERNS.map(a => a.replace(/-/g, '\\-')).join('|')})\s*=\s*(["\']).*?\2`,
  'i'
);

// Regex: CSS-style attribute selectors in JS/TS that target inputs with title
const JS_SELECTOR_RE = /input\[title|select\[title|textarea\[title|input\[data-(?:tooltip|help|help-id|hint)|select\[data-(?:tooltip|help|help-id|hint)|textarea\[data-(?:tooltip|help|help-id|hint)/i;

function read(file) {
  try { return fs.readFileSync(file, 'utf8'); } catch { return ''; }
}

function rel(p) { return path.relative(ROOT, p) || p; }

async function main() {
  const files = await glob(DEFAULT_INCLUDE, { ignore: DEFAULT_EXCLUDE, nodir: true, dot: false, cwd: ROOT, absolute: true });
  const violations = [];

  for (const file of files) {
    const text = read(file);
    if (!text) continue;

    // Quick skip for large binaries or minified chunks
    if (text.length > 1_200_000) continue;

    // HTML/PHP scanning
    if (/\.(php|html?)$/i.test(file)) {
      const lines = text.split(/\r?\n/);
      lines.forEach((line, idx) => {
        if (INPUT_ATTR_RE.test(line)) {
          violations.push({ file: rel(file), line: idx + 1, snippet: line.trim().slice(0, 220), type: 'markup' });
        }
      });
    }

    // JS/TS scanning for selector misuse
    if (/\.(js|mjs|jsx|ts|tsx)$/i.test(file)) {
      if (JS_SELECTOR_RE.test(text)) {
        // Try to extract a few offending lines
        const lines = text.split(/\r?\n/);
        lines.forEach((line, idx) => {
          if (JS_SELECTOR_RE.test(line)) {
            violations.push({ file: rel(file), line: idx + 1, snippet: line.trim().slice(0, 220), type: 'script' });
          }
        });
      }
    }
  }

  if (violations.length) {
    console.error('Tooltip policy violations detected (tooltips attached directly to fields):');
    violations.slice(0, 200).forEach(v => {
      console.error(` - ${v.file}:${v.line} [${v.type}] ${v.snippet}`);
    });
    if (violations.length > 200) {
      console.error(` ... and ${violations.length - 200} more`);
    }
    console.error('\nPolicy: Attach help to labels (label, .field-label, [data-field-label]) — not inputs/selects/textareas.');
    process.exit(1);
  } else {
    console.log('Tooltip guard passed: no input/select/textarea tooltips detected.');
  }
}

main().catch(err => { console.error('Guard failed:', err); process.exit(1); });
