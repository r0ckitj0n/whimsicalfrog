#!/usr/bin/env node
/*
  Guard: disallow committing backup-like files outside the backups/ directory.
  Mirrors CI check in .github/workflows/ci.yml.

  Detects:
    - *.bak
    - *.backup
    - *.backup.*
    - filenames with trailing numeric suffix like: "name 2.ext"

  Usage:
    - Via lint-staged: node scripts/dev/guard-backups-staged.mjs <staged-files>
    - Standalone with git: node scripts/dev/guard-backups-staged.mjs --git
*/

import { execSync } from 'node:child_process';
import path from 'node:path';

function listStagedFilesViaGit() {
  try {
    const out = execSync('git diff --cached --name-only --diff-filter=ACMRT', { encoding: 'utf8' });
    return out.split(/\r?\n/).filter(Boolean);
  } catch (e) {
    return [];
  }
}

function getRepoRoot() {
  try {
    const out = execSync('git rev-parse --show-toplevel', { encoding: 'utf8' }).trim();
    return out || process.cwd();
  } catch {
    return process.cwd();
  }
}

function toRepoRelative(p, repoRoot) {
  if (!p) return p;
  // If absolute, make it relative to repo; otherwise normalize './' prefix
  const rel = path.isAbsolute(p) ? path.relative(repoRoot, p) : p.replace(/^\.\/?/, '');
  return rel.replace(/\\/g, '/');
}

function isInBackups(anyPath, repoRoot) {
  const rel = toRepoRelative(anyPath, repoRoot);
  return rel.startsWith('backups/');
}

function isBackupLike(filePath) {
  const base = path.basename(filePath);
  // *.bak or *.backup or *.backup.*
  if (/\.bak(\.|$)/i.test(base)) return true;
  if (/\.backup(\.|$)/i.test(base)) return true;
  // "name 2.ext" (basename contains space then digits before extension)
  if (/\s\d+\.[^./]+$/i.test(base)) return true;
  return false;
}

function main() {
  const repoRoot = getRepoRoot();
  const args = process.argv.slice(2);
  let files = args.filter(a => !a.startsWith('-'));

  if (args.includes('--git') || files.length === 0) {
    files = listStagedFilesViaGit();
  }

  if (!files.length) process.exit(0);

  const offenders = files
    .map(f => toRepoRelative(f, repoRoot))
    .filter(f => f && !isInBackups(f, repoRoot) && isBackupLike(f))
    .sort();

  if (offenders.length) {
    console.error('[Guard] Stray backup-like files staged outside backups/:');
    offenders.forEach(f => console.error(' -', f));
    console.error('\nMove these into backups/ before committing.');
    process.exit(1);
  }
}

main();
