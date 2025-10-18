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

import fsp from 'fs/promises';
import path from 'path';

const ROOTS = [
  'src/styles',
  'css',
  '.'
];

const Z_INDEX_SOURCE = path.resolve('src/styles/z-index.css');

const VAR_USAGE_REGEX = /var\(--(z-[a-z0-9-]+)(?:\s*,[^)]*)?\)/gi;

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

  let declaredTokens = new Set();
  try {
    const source = await fsp.readFile(Z_INDEX_SOURCE, 'utf8');
    const matches = source.matchAll(/--(z-[a-z0-9-]+)\s*:/gi);
    declaredTokens = new Set(Array.from(matches, m => m[1]));
  } catch (err) {
    console.error(`guard-z-index: failed to read token source ${Z_INDEX_SOURCE}`);
    console.error(err);
    process.exit(1);
  }

  const violations = [];
  const missingTokens = new Map();
  for (const file of allFiles) {
    const content = await fsp.readFile(file, 'utf8');
    const lines = content.split(/\r?\n/);
    lines.forEach((line, idx) => {
      if (customPropDecl.test(line)) return; // allow custom prop declarations
      if (!zIndexProp.test(line)) return;    // only check lines that set z-index
      const varMatches = line.match(VAR_USAGE_REGEX);
      if (varMatches) {
        for (const raw of varMatches) {
          const tokenName = raw.replace(VAR_USAGE_REGEX, '$1');
          if (!declaredTokens.has(tokenName)) {
            if (!missingTokens.has(file)) missingTokens.set(file, []);
            missingTokens.get(file).push({ line: idx + 1, token: tokenName, text: line.trim() });
          }
        }
        return;
      }
      if (containsVar.test(line)) return;    // allow generic var() usage (fallback for dynamic tokens)
      if (zIndexHardNumber.test(line)) {
        violations.push({ file, line: idx + 1, text: line.trim() });
      }
    });
  }

  if (violations.length || missingTokens.size) {
    console.error('\nZ-index guard failed. Follow src/styles/z-index.css tokens.');
    console.error('Allowances: var(--token[, fallback]) and custom property declarations like --token: 123;');
    if (violations.length) console.error('\nHard-coded z-index declarations:');
    for (const v of violations) {
      const rel = path.relative(process.cwd(), v.file);
      console.error(` - ${rel}:${v.line} :: ${v.text}`);
    }
    if (missingTokens.size) {
      console.error('\nUndefined z-index tokens referenced:');
      for (const [file, entries] of missingTokens.entries()) {
        const rel = path.relative(process.cwd(), file);
        entries.forEach(({ line, token, text }) => {
          console.error(` - ${rel}:${line} :: ${token} ( ${text} )`);
        });
      }
    }
    console.error(`\nTotal hard-coded violations: ${violations.length}.`);
    console.error(`Undefined token references: ${Array.from(missingTokens.values()).reduce((sum, items) => sum + items.length, 0)}`);
    process.exit(1);
  } else {
    console.log('guard-z-index: no hard-coded z-index found.');
  }
}

main().catch((err) => {
  console.error('guard-z-index failed:', err);
  process.exit(1);
});
