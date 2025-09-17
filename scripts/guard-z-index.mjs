#!/usr/bin/env node
/**
 * Guard: disallow hard-coded z-index values in CSS
 *
 * Fails the build if any CSS rule sets `z-index: <number>` directly.
 *
 * Allowed (ignored):
 *  - z-index using variables: `z-index: var(--something, 1000)` (fallbacks are allowed)
 *  - custom property declarations: `--some-token: 200;`
 *
 * Scanned roots:
 *  - src/styles/
 *  - css/
 *  - repo root for top-level *.css
 */

import fs from 'fs';
import fsp from 'fs/promises';
import path from 'path';

const ROOTS = [
  'src/styles',
  'css',
  '.'
];

const IGNORE_DIRS = new Set(['node_modules', 'dist', '.vite', 'backups', '.git', '.husky']);

const zIndexProp = /z-index\s*:/i;
const zIndexHardNumber = /z-index\s*:\s*-?\d+(\s*;|\s*!|\s*$)/i; // matches z-index: 123; or with !important
const containsVar = /var\s*\(/i;
const customPropDecl = /^\s*--[\w-]+\s*:/; // CSS custom property declaration

/** Recursively collect .css files under a root directory, respecting ignores */
async function collectCssFiles(root) {
  const results = [];
  async function walk(dir) {
    let entries;
    try { entries = await fsp.readdir(dir, { withFileTypes: true }); } catch { return; }
    for (const ent of entries) {
      const full = path.join(dir, ent.name);
      if (ent.isDirectory()) {
        if (IGNORE_DIRS.has(ent.name)) continue;
        await walk(full);
      } else if (ent.isFile()) {
        if (full.endsWith('.css')) results.push(full);
      }
    }
  }
  // If root is a file, include it if .css
  try {
    const stat = await fsp.stat(root);
    if (stat.isFile()) {
      if (root.endsWith('.css')) results.push(root);
      return results;
    }
  } catch {}
  await walk(root);
  return results;
}

async function main() {
  const allFiles = new Set();
  for (const r of ROOTS) {
    const files = await collectCssFiles(r);
    files.forEach(f => allFiles.add(path.resolve(f)));
  }

  const violations = [];
  for (const file of allFiles) {
    const content = await fsp.readFile(file, 'utf8');
    const lines = content.split(/\r?\n/);
    lines.forEach((line, idx) => {
      if (customPropDecl.test(line)) return; // allow custom prop declarations
      if (!zIndexProp.test(line)) return;    // only check lines that set z-index
      if (containsVar.test(line)) return;    // allow var() usage
      if (zIndexHardNumber.test(line)) {
        violations.push({ file, line: idx + 1, text: line.trim() });
      }
    });
  }

  if (violations.length) {
    console.error('\nHard-coded z-index detected. Use variables from src/styles/z-index.css instead.');
    console.error('Allowances: var(--token[, fallback]) and custom property declarations like --token: 123;');
    console.error('\nViolations:');
    for (const v of violations) {
      const rel = path.relative(process.cwd(), v.file);
      console.error(` - ${rel}:${v.line} :: ${v.text}`);
    }
    console.error(`\nTotal: ${violations.length} violation(s).`);
    process.exit(1);
  } else {
    console.log('guard-z-index: no hard-coded z-index found.');
  }
}

main().catch((err) => {
  console.error('guard-z-index failed:', err);
  process.exit(1);
});
