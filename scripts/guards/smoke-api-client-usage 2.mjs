#!/usr/bin/env node
// scripts/guards/smoke-api-client-usage.mjs
// Lightweight smoke test: ensure no direct fetch to /api or XMLHttpRequest in source modules

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');
const SRC_DIR = path.join(repoRoot, 'src');

const includeExts = new Set(['.js', '.mjs', '.ts', '.tsx']);
const allowlist = [
  /(^|\/)src\/core\/ApiClient\.ts$/, // canonical client allowed to use fetch/xhr
  /(^|\/)src\/core\/api-client\.js$/, // canonical client allowed to use fetch
  /(^|\/)admin\-inventory\.js$/, // XHR progress allowed here
  /(^|\/)src\/js\/admin\-inventory\.js$/, // XHR progress allowed here
];

function listFiles(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const abs = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...listFiles(abs));
    else {
      const ext = path.extname(entry.name);
      if (includeExts.has(ext)) out.push(abs);
    }
  }
  return out;
}

function isAllowlisted(relPath) {
  return allowlist.some((re) => re.test(relPath));
}

const files = listFiles(SRC_DIR);
const violations = [];

for (const abs of files) {
  const rel = abs.replace(repoRoot + path.sep, '').replaceAll('\\', '/');
  if (isAllowlisted(rel)) continue;
  let text;
  try { text = fs.readFileSync(abs, 'utf8'); } catch { continue; }
  const lines = text.split(/\r?\n/);
  lines.forEach((line, i) => {
    const trimmed = line.trim();
    if (trimmed.startsWith('//')) return; // ignore single-line comments
    if (/fetch\(\s*["'`]\/api\//.test(line)) {
      violations.push({ file: rel, line: i + 1, code: trimmed, rule: "direct fetch to /api" });
    }
    if (/new\s+XMLHttpRequest\s*\(/.test(line)) {
      violations.push({ file: rel, line: i + 1, code: trimmed, rule: "XMLHttpRequest usage" });
    }
  });
}

if (violations.length) {
  console.error('SMOKE FAIL: Detected direct API calls in source files (use ApiClient)');
  for (const v of violations) {
    console.error(`- [${v.rule}] ${v.file}:${v.line} :: ${v.code}`);
  }
  process.exit(1);
}

console.log('SMOKE OK: No direct /api fetch or XMLHttpRequest found in src/.');
