#!/usr/bin/env node
/*
 Guard: Repository hygiene
 - Flags root-level one-off scripts (e.g., *.sh, *.php, *.js, *.py) that should live under scripts/
 - Flags duplicate-suffixed files (" 2", " 3") and *.bak outside backups/duplicates/
 - Exits non-zero with actionable instructions
*/
import { glob } from 'glob';
import process from 'node:process';

const root = process.cwd();

const IGNORE_GLOBS = [
  '**/node_modules/**',
  '**/vendor/**',
  '**/dist/**',
  '**/.git/**',
  '**/.github/**',
  '**/.githooks/**',
  '**/backups/**',
  '**/reports/**',
  '**/logs/**',
  '**/sessions/**',
  '**/templates/wf-starter/**',
];

// Conservative: only flag shell/python scripts at repo root.
// Do NOT flag PHP/JS at root because many are runtime entry points.
const ROOT_SCRIPT_EXTS = ['sh', 'py'];
const ROOT_ALLOWLIST = new Set([
  // Known, intentionally-root scripts/tools
  'build.sh',
  'deploy.sh',
  'release.sh',
  'wait-for-function.js',
]);

function formatList(items) {
  return items.map(x => ` - ${x}`).join('\n');
}

async function findStrayRootScripts() {
  const patterns = [`*.{${ROOT_SCRIPT_EXTS.join(',')}}`];
  const files = new Set();
  for (const pat of patterns) {
    const matches = await glob(pat, { cwd: root, dot: false, ignore: IGNORE_GLOBS, nodir: true });
    matches.forEach(m => files.add(m));
  }
  // Filter allowlist and anything already under scripts/
  const offenders = Array.from(files).filter(f => !ROOT_ALLOWLIST.has(f) && !f.startsWith('scripts/'));
  return offenders;
}

async function findDuplicateSuffixedFiles() {
  const all = await glob('**/*', { cwd: root, dot: true, ignore: IGNORE_GLOBS, nodir: true });
  const dupRe = /(?:^|[\\/])[^\\/]+\s(?:2|3)(?:\.[^\\/]+)?$/; // files ending with " 2" or " 3"
  const bakRe = /(?:^|[\\/])[^\\/]+\.bak(?:\.[^\\/]+)?$/i;
  const offenders = [];
  for (const rel of all) {
    if (rel.startsWith('backups/duplicates/')) continue; // allowed quarantine
    if (dupRe.test(rel) || bakRe.test(rel)) offenders.push(rel);
  }
  return offenders;
}

async function main() {
  const strayRoot = await findStrayRootScripts();
  const dupes = await findDuplicateSuffixedFiles();

  const problems = [];
  if (strayRoot.length) {
    problems.push(`Root-level scripts that should be moved into scripts/ (or archived):\n${formatList(strayRoot)}`);
  }
  if (dupes.length) {
    problems.push(`Duplicate-suffixed or .bak files outside backups/duplicates/:\n${formatList(dupes)}`);
  }

  if (problems.length) {
    console.error('\n[guard:repo-hygiene] Issues found:\n');
    console.error(problems.join('\n\n'));
    console.error('\nRecommended actions:');
    console.error(' - Move reusable tools into scripts/ with appropriate subfolders (dev, db, guards, maintenance)');
    console.error(' - Move backups/duplicates into backups/duplicates/ preserving relative paths');
    console.error(' - Archive true one-offs into backups/one_offs/');
    process.exit(1);
  }

  console.log('[guard:repo-hygiene] OK');
}

main().catch(err => {
  console.error('[guard:repo-hygiene] Error:', err);
  process.exit(1);
});
