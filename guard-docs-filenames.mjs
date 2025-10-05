#!/usr/bin/env node
/**
 * Guard: documentation Markdown filenames must be UPPER_SNAKE_CASE
 * Policy: Basename must match /^[A-Z0-9_]+\.md$/
 *
 * Usage:
 *   node scripts/dev/guard-docs-filenames.mjs           # scan entire documentation/ tree
 *   node scripts/dev/guard-docs-filenames.mjs --staged  # check only staged files under documentation/
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

function listStaged() {
  try {
    const out = execSync('git diff --cached --name-only -z', { encoding: 'utf8' });
    return out.split('\u0000').filter(Boolean);
  } catch (e) {
    return [];
  }
}

function walkDir(dir, acc) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const ent of entries) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      walkDir(p, acc);
    } else if (ent.isFile()) {
      acc.push(p);
    }
  }
}

const args = process.argv.slice(2);
const onlyStaged = args.includes('--staged');
const repoRoot = process.cwd();
const docsDir = path.join(repoRoot, 'documentation');
const offenders = [];
const pattern = /^[A-Z0-9_]+\.md$/;

let candidates = [];
if (!fs.existsSync(docsDir) || !fs.statSync(docsDir).isDirectory()) {
  // Nothing to do
  process.exit(0);
}

if (onlyStaged) {
  const staged = listStaged();
  candidates = staged.filter(p => p.startsWith('documentation/') && p.toLowerCase().endsWith('.md'))
    .map(p => path.join(repoRoot, p));
} else {
  walkDir(docsDir, candidates);
  candidates = candidates.filter(p => p.toLowerCase().endsWith('.md'));
}

for (const filePath of candidates) {
  const rel = path.relative(repoRoot, filePath).split(path.sep).join('/');
  const base = path.basename(filePath);
  // Explicit exemption for documentation/README.md
  if (rel === 'documentation/README.md') {
    continue;
  }
  if (!pattern.test(base)) {
    offenders.push(rel);
  }
}

if (offenders.length) {
  console.error('\n⛔ Invalid documentation Markdown filenames detected.');
  console.error('   Filenames under documentation/ must match: ^[A-Z0-9_]+\\.md$ (UPPERCASE with underscores, no hyphens).');
  console.error(offenders.join('\n'));
  console.error('\nExample rename:');
  console.error('  git mv documentation/path/to/foo-bar.md documentation/path/to/FOO_BAR.md');
  process.exit(1);
}

process.exit(0);
