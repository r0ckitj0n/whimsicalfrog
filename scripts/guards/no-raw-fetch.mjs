#!/usr/bin/env node
// scripts/guards/no-raw-fetch.mjs
// Fails if raw HTTP calls are detected outside the allowlist. Use the unified ApiClient instead.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');

const includeExts = new Set(['.js', '.mjs', '.ts', '.tsx']);
const ignoreDirs = new Set([
  'node_modules',
  'dist',
  'vendor',
  'backups',
  'reports/restore_backups',
  'scripts/dev',
  'scripts/guards', // ignore guard scripts themselves
]);

const patterns = [
  { name: 'fetch', regex: /(^|[^.\w])fetch\s*\(/ },
  { name: 'XMLHttpRequest', regex: /XMLHttpRequest/ },
  { name: '$.ajax', regex: /\$\s*\.ajax\s*\(/ },
  { name: 'axios', regex: /(^|[^.\w])axios\s*\(/ },
  { name: 'new Request', regex: /new\s+Request\s*\(/ },
  { name: 'sendBeacon', regex: /navigator\.sendBeacon\s*\(/ },
];

// Allow only these exceptions by file+pattern name.
const allowlist = [
  // Analytics beacon is allowed
  { fileRe: /(^|\/)src\/js\/analytics\.js$/, names: ['sendBeacon'] },
  { fileRe: /(^|\/)analytics\.js$/, names: ['sendBeacon'] },
  // Internal client implementations may use fetch
  { fileRe: /(^|\/)src\/core\/api-client\.js$/, names: ['fetch'] },
  { fileRe: /(^|\/)src\/core\/api-client\.js$/, names: ['XMLHttpRequest'] },
  { fileRe: /(^|\/)src\/modules\/api-client\.js$/, names: ['fetch'] },
  { fileRe: /(^|\/)whimsical-frog-core-unified\.js$/, names: ['fetch'] },
  { fileRe: /(^|\/)src\/js\/whimsical-frog-core-unified\.js$/, names: ['fetch'] },
  { fileRe: /(^|\/)templates\/wf-starter\/src\/core\/apiClient\.js$/, names: ['fetch'] },
  // Inventory upload uses XHR for reliable progress reporting
  { fileRe: /(^|\/)admin\-inventory\.js$/, names: ['XMLHttpRequest'] },
  { fileRe: /(^|\/)src\/js\/admin\-inventory\.js$/, names: ['XMLHttpRequest'] },
];

function isIgnored(rel) {
  if (rel.startsWith('.git/')) return true;
  for (const d of ignoreDirs) {
    if (rel === d || rel.startsWith(`${d}/`)) return true;
  }
  return false;
}

function walk(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const abs = path.join(dir, entry.name);
    const rel = path.relative(repoRoot, abs).replaceAll('\\', '/');
    if (isIgnored(rel)) continue;
    if (entry.isDirectory()) {
      out.push(...walk(abs));
    } else {
      const ext = path.extname(entry.name);
      if (includeExts.has(ext)) out.push({ abs, rel });
    }
  }
  return out;
}

function allowed(rel, name) {
  // Also skip seed/dev tooling files that are not part of runtime
  if (/^scripts\/dev\//.test(rel)) return true;
  if (/(^|\/)seed\-admin\-tooltips\.mjs$/.test(rel)) return true;
  if (/(^|\/)audit\-[^/]+\.mjs$/.test(rel)) return true;
  if (/(^|\/)export\-admin\-tooltips\.mjs$/.test(rel)) return true;
  if (/(^|\/)extract_css_classes\.mjs$/.test(rel)) return true;
  if (/(^|\/)scripts\/guards\/no\-raw\-fetch\.mjs$/.test(rel)) return true;
  return allowlist.some(({ fileRe, names }) => fileRe.test(rel) && names.includes(name));
}

const files = walk(repoRoot);
const violations = [];

for (const { abs, rel } of files) {
  let text;
  try {
    text = fs.readFileSync(abs, 'utf8');
  } catch {
    continue;
  }
  const lines = text.split(/\r?\n/);
  patterns.forEach(({ name, regex }) => {
    lines.forEach((line, i) => {
      const trimmed = line.trim();
      if (trimmed.startsWith('//')) return; // ignore single-line comments
      if (regex.test(line) && !allowed(rel, name)) {
        violations.push({ file: rel, line: i + 1, name, code: line.trim() });
      }
    });
  });
}

if (violations.length) {
  console.error('ERROR: Raw HTTP calls detected (use unified ApiClient instead):');
  for (const v of violations) {
    console.error(`- [${v.name}] ${v.file}:${v.line} :: ${v.code}`);
  }
  console.error(`\nAllowed exceptions:`);
  for (const e of allowlist) console.error(`- ${e.fileRe} => ${e.names.join(', ')}`);
  process.exit(1);
} else {
  console.log('OK: No raw HTTP calls detected outside allowlist.');
}
