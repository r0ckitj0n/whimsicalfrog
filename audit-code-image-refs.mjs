#!/usr/bin/env node
/**
 * Audit code-referenced image paths for existence on disk.
 *
 * Scans PHP/JS/CSS/HTML for:
 * - url(...) values
 * - quoted string literals that look like image paths (images/... or /images/...)
 *
 * Excludes dist/, node_modules/, vendor/, .git/ and backups/duplicates/.
 * Safe for CI.
 */

import fs from 'fs/promises';
import path from 'path';
import { glob } from 'glob';

const ROOT = process.cwd();

const INCLUDE_GLOBS = [
  '**/*.php',
  '**/*.phtml',
  '**/*.html',
  '**/*.htm',
  '**/*.js',
  '**/*.mjs',
  '**/*.cjs',
  '**/*.css',
];

const EXCLUDE = [
  '**/dist/**',
  '**/node_modules/**',
  '**/vendor/**',
  '**/.git/**',
  '**/backups/duplicates/**',
  '**/documentation/**',
];

const IMAGE_EXTS = ['png', 'webp', 'jpg', 'jpeg', 'svg'];

// Ignore known dynamic placeholders and legacy doc examples
const IGNORE_PATTERNS = [
  /^images\/items\/\{\$.*\}$/i,                 // images/items/{$...}
  /^images\/items\/\{.*\}$/i,                    // images/items/{...}
  /^images\/signs\/sign_door_room</i,             // partial from PHP echo in templates
  /^images\/backgrounds\/room\d+\.webp$/i,       // old placeholder example
  /^images\/logo\.png$/i,                          // generic example path
  /^images\/WhimsicalFrog_Logo\.webp$/i,          // legacy/example path
  /^images\/my_image\.png$/i,                      // docs example
  /^images\/placeholder\.png$/i,                   // generic example outside images/items/
];

function normalizePath(p) {
  // drop protocol/host and leading slashes
  let s = p.trim().replace(/^https?:\/\/[^/]+\//i, '');
  s = s.replace(/^\/*/, '');
  // strip query/hash
  s = s.replace(/[?#].*$/, '');
  return s;
}

function stripComments(content) {
  // Remove block comments /* ... */ (JS/CSS/PHP)
  let s = content.replace(/\/\*[\s\S]*?\*\//g, '');
  // Remove line comments starting with //
  s = s.replace(/^\s*\/\/.*$/gm, '');
  // Remove line comments starting with # (common in PHP)
  s = s.replace(/^\s*#.*$/gm, '');
  // Remove HTML comments <!-- ... -->
  s = s.replace(/<!--([\s\S]*?)-->/g, '');
  return s;
}

function collectImageRefs(content) {
  const refs = new Set();
  const text = stripComments(content);

  // 1) CSS url(...) patterns
  const urlRe = /url\(\s*(["']?)([^\)"']+?)\1\s*\)/g; // captures url('...') or url(...)
  for (const m of text.matchAll(urlRe)) {
    const candidate = m[2];
    if (/^\/?images\//i.test(candidate) && IMAGE_EXTS.some(ext => candidate.toLowerCase().includes('.' + ext))) {
      refs.add(normalizePath(candidate));
    }
  }

  // 2) Quoted strings that look like image paths
  const strRe = /(["'])(\/?images\/[^\1\n\r]+?)\1/g;
  for (const m of text.matchAll(strRe)) {
    const candidate = m[2];
    if (IMAGE_EXTS.some(ext => candidate.toLowerCase().includes('.' + ext))) {
      refs.add(normalizePath(candidate));
    }
  }

  return refs;
}

async function fileExists(rel) {
  try {
    await fs.access(path.join(ROOT, rel));
    return true;
  } catch {
    return false;
  }
}

(async () => {
  const files = await glob(INCLUDE_GLOBS, { ignore: EXCLUDE, cwd: ROOT, dot: true, nodir: true });
  const allRefs = new Set();

  for (const rel of files) {
    const full = path.join(ROOT, rel);
    const content = await fs.readFile(full, 'utf8');
    const refs = collectImageRefs(content);
    refs.forEach(r => allRefs.add(r));
  }

  const missing = [];
  for (const ref of allRefs) {
    // Skip dynamic templating and obvious non-static refs
    if (/[{}$<>]/.test(ref)) continue;
    if (IGNORE_PATTERNS.some(rx => rx.test(ref))) continue;

    const ok = await fileExists(ref);
    if (!ok) missing.push(ref);

    // Pairing checks for references in guarded directories
    const lower = ref.toLowerCase();
    const inBackgrounds = lower.startsWith('images/backgrounds/');
    const inItems = lower.startsWith('images/items/');
    const inSigns = lower.startsWith('images/signs/');
    const inLogos = lower.startsWith('images/logos/');
    if (inBackgrounds || inItems || inSigns || inLogos) {
      if (lower.endsWith('.webp')) {
        const sister = ref.replace(/\.webp$/i, '.png');
        const sisterOk = await fileExists(sister);
        if (!sisterOk) missing.push(`${sister} (missing png pair)`);
      } else if (lower.endsWith('.png')) {
        const sister = ref.replace(/\.png$/i, '.webp');
        const sisterOk = await fileExists(sister);
        if (!sisterOk) missing.push(`${sister} (missing webp pair)`);
      }
    }
  }

  if (missing.length > 0) {
    console.error('Code image reference audit found missing files:');
    for (const m of missing.sort()) console.error('- ' + m);
    process.exit(1);
  }

  console.log(`Code image reference audit passed. Checked ${allRefs.size} unique references.`);
})();
