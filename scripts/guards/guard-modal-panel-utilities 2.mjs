#!/usr/bin/env node
/**
 * Guard: Disallow Tailwind-like width/height utilities on modal panels.
 *
 * Flags occurrences of w-[..vw]/h-[..vh] on:
 *  - admin modal panels: elements/classes containing 'admin-modal' or 'admin-modal-content'
 *  - public modal panels: elements/classes containing 'modal-content'
 *
 * Scans PHP/HTML/JS files (ignores backups, dist, node_modules, public).
 */
import { globSync } from 'glob';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname;

const files = globSync('**/*.{php,html,htm,js,mjs,cjs}', {
  cwd: ROOT,
  dot: false,
  ignore: [
    'node_modules/**',
    'dist/**',
    'public/**',
    'backups/**',
    'backups*/**',
    'logs/**',
  ],
});

const violations = [];

// Match class attributes like class="... admin-modal ... w-[80vw] ..."
const reHtmlDouble = /class\s*=\s*"([^"]*)"/g;
const reHtmlSingle = /class\s*=\s*'([^']*)'/g;
// Match JS className = '...'
const reJsClassName = /className\s*=\s*(["'])(.*?)\1/g;

function checkClassValue(val, file, lineNo) {
  const hasPanel = /(\badmin-modal\b|\badmin-modal-content\b|\bmodal-content\b)/.test(val);
  const hasUtility = /(\bw-\[[0-9]+(?:vw|vh)\]|\bh-\[[0-9]+(?:vw|vh)\])/i.test(val);
  if (hasPanel && hasUtility) {
    violations.push({ file, line: lineNo, classValue: val });
  }
}

function stripComments(src, ext) {
  let out = src;
  if (ext === '.js' || ext === '.mjs' || ext === '.cjs') {
    // Remove block comments first
    out = out.replace(/\/\*[\s\S]*?\*\//g, '');
    // Remove single-line comments, but avoid protocol like https://
    out = out.replace(/(^|[^:])\/\/.*$/gm, '$1');
  }
  if (ext === '.php' || ext === '.html' || ext === '.htm') {
    // Remove HTML comments
    out = out.replace(/<!--([\s\S]*?)-->/g, '');
  }
  return out;
}

for (const relPath of files) {
  const filePath = path.join(ROOT, relPath);
  let src = '';
  try { src = readFileSync(filePath, 'utf8'); } catch { continue; }

  // Skip scanning this guard file itself to avoid self-flagging examples
  if (/guard-modal-panel-utilities\.mjs$/.test(relPath)) continue;

  const ext = path.extname(relPath).toLowerCase();
  const cleaned = stripComments(src, ext);

  const lines = cleaned.split(/\r?\n/);
  lines.forEach((line, idx) => {
    for (const re of [reHtmlDouble, reHtmlSingle]) {
      let m;
      re.lastIndex = 0;
      while ((m = re.exec(line)) !== null) {
        checkClassValue(m[1], relPath, idx + 1);
      }
    }
    let m;
    reJsClassName.lastIndex = 0;
    while ((m = reJsClassName.exec(line)) !== null) {
      checkClassValue(m[2], relPath, idx + 1);
    }
  });
}

if (violations.length) {
  console.error('\n[guard-modal-panel-utilities] Found Tailwind width/height utilities on modal panels:');
  for (const v of violations) {
    console.error(` - ${v.file}:${v.line} -> ${v.classValue}`);
  }
  console.error('\nFix guidance: replace w-[..vw]/h-[..vh] on modal panels with tokenized classes:');
  console.error(' * Admin: .admin-modal--md|lg|xl|full or semantic aliases');
  console.error(' * Public: .site-modal--md|lg|xl|full');
  process.exitCode = 1;
} else {
  console.log('[guard-modal-panel-utilities] OK: no width/height utilities found on modal panels.');
}
