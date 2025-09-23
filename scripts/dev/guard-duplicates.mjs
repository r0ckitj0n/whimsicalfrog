#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const repoRoot = process.cwd();

// Directories to skip entirely
const SKIP_DIRS = new Set([
  'node_modules',
  '.git',
  'dist',
  'vendor',
  'logs',
  '.cache',
  'backups', // handled specially below to allow backups/duplicates
]);

function isUnderAllowedBackups(fullPath) {
  // Allow anything under backups/duplicates/
  const rel = path.relative(repoRoot, fullPath).replaceAll('\\', '/');
  return rel.startsWith('backups/duplicates/');
}

function isOffendingName(name) {
  // name is a single path segment
  // Offend if it ends with space + digit (2 or 3, but we generalize to any digit)
  // or if the file has a .bak extension
  if (/\s\d$/.test(name)) return true;
  if (name.toLowerCase().endsWith('.bak')) return true;
  return false;
}

function shouldSkipDir(relPath) {
  const parts = relPath.split(/[\\/]/).filter(Boolean);
  if (parts.length === 0) return false;
  const top = parts[0];
  if (SKIP_DIRS.has(top)) {
    // Allow backups/duplicates explicitly
    if (top === 'backups') {
      if (parts[1] === 'duplicates') {
        return false; // do not skip backups/duplicates
      }
    }
    return true;
  }
  return false;
}

const args = new Set(process.argv.slice(2));
const WRITE = args.has('--write');

const offenders = [];

function walk(currentDir) {
  const entries = fs.readdirSync(currentDir, { withFileTypes: true });
  for (const entry of entries) {
    const full = path.join(currentDir, entry.name);
    const rel = path.relative(repoRoot, full);

    // Skip disallowed backups (anything under backups except backups/duplicates)
    if (!isUnderAllowedBackups(full) && shouldSkipDir(rel)) {
      continue;
    }

    // Check entry name for offending pattern
    if (!isUnderAllowedBackups(full) && isOffendingName(entry.name)) {
      offenders.push(rel);
    }

    if (entry.isDirectory()) {
      walk(full);
    }
  }
}

walk(repoRoot);

function ensureDir(p){ fs.mkdirSync(p, { recursive: true }); }

function gitMv(src, dest){
  const res = spawnSync('git', ['mv', '--', src, dest], { cwd: repoRoot, stdio: 'inherit' });
  if (res.status !== 0) {
    ensureDir(path.dirname(dest));
    fs.renameSync(src, dest);
  }
}

if (offenders.length > 0) {
  if (!WRITE) {
    console.error('✖ Duplicate/backup guard failed. The following paths are not allowed outside backups/duplicates/:');
    for (const p of offenders) console.error(' -', p);
    process.exit(1);
  } else {
    let moved = 0;
    for (const rel of offenders) {
      const abs = path.join(repoRoot, rel);
      if (!fs.existsSync(abs)) continue;
      const dest = path.join(repoRoot, 'backups/duplicates', rel);
      console.log('Quarantining', rel, '->', path.relative(repoRoot, dest));
      ensureDir(path.dirname(dest));
      gitMv(abs, dest);
      moved++;
    }
    if (moved > 0) {
      spawnSync('git', ['add', '-f', '--', 'backups/duplicates'], { cwd: repoRoot, stdio: 'inherit' });
      console.log(`Moved ${moved} path(s) to backups/duplicates/`);
    } else {
      console.log('No offenders moved.');
    }
    process.exit(0);
  }
} else {
  console.log('✓ Duplicate/backup guard passed.');
}
