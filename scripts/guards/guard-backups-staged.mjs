#!/usr/bin/env node
/**
 * Guard: block staging backup-like files outside backups/
 * - Flags files ending with " 2", " 3", .bak, .backup, .ffs_lock, or editor ~ suffixes
 * - Enforces tooltip snapshot JSON/CSV files live under backups/tooltips/
 */

import { execSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';

const repoRoot = process.cwd();
const rawArgs = process.argv.slice(2).filter(Boolean);

const BACKUP_PATTERNS = [
  /\s(?:[2-9]|[1-9]\d)(?:\.[^/\\]+)?$/i,
  /(\.bak(?:[._-]|$)|(?:^|[._ -])bak\d?(?:[-._]|$))/i,
  /\.backup(?:\.[^/\\]+)?$/i,
  /\.ffs_lock$/i,
  /~$/,
];

const TOOLTIP_SNAPSHOT = /tooltips\/[^/]+\.(json|csv)$/i;

function normalizePath(file) {
  if (!file) return '';
  const rel = path.isAbsolute(file)
    ? path.relative(repoRoot, file)
    : file;
  return rel.replace(/\\/g, '/');
}

function getStagedFiles() {
  try {
    const output = execSync('git diff --cached --name-only', {
      cwd: repoRoot,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    });
    return output.split(/\r?\n/).map(normalizePath).filter(Boolean);
  } catch {
    return [];
  }
}

const candidates = (rawArgs.length ? rawArgs : getStagedFiles())
  .map(normalizePath)
  .filter(Boolean);

const offenders = [];
const tooltipIssues = [];

for (const rel of candidates) {
  if (!rel || rel.startsWith('backups/')) continue;
  const basename = path.basename(rel);
  if (BACKUP_PATTERNS.some(pattern => pattern.test(basename))) {
    offenders.push(rel);
    continue;
  }
  if (TOOLTIP_SNAPSHOT.test(rel) && !rel.startsWith('backups/tooltips/')) {
    tooltipIssues.push(rel);
  }
}

if (!offenders.length && !tooltipIssues.length) {
  if (candidates.length) {
    console.log('[guard-backups-staged] OK: no stray backup artifacts in staged files.');
  }
  process.exit(0);
}

console.error('\n[guard-backups-staged] Blocked files detected:');
if (offenders.length) {
  console.error(' - Backup-like names must live under backups/ (or backups/duplicates/):');
  offenders.forEach(rel => console.error(`   • ${rel}`));
}
if (tooltipIssues.length) {
  console.error(' - Tooltip snapshot files must be stored in backups/tooltips/:');
  tooltipIssues.forEach(rel => console.error(`   • ${rel}`));
}
console.error('\nMove the files into the backups/ hierarchy (or delete them) and re-stage.');
process.exit(1);
