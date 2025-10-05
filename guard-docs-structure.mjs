#!/usr/bin/env node
import { readdirSync, statSync, readFileSync } from 'fs';
import { join, relative } from 'path';

const ROOT = process.cwd();
const DOCS = join(ROOT, 'documentation');
const TECH = join(DOCS, 'technical');
const FRONTEND = join(DOCS, 'frontend');
const INCLUDES = join(DOCS, 'includes');
const README = join(DOCS, 'README.md');
const ADMIN_GUIDE = join(DOCS, 'ADMIN_GUIDE.md');

// Allowlist of additional optional root-level docs
const OPTIONAL_ROOT_FILES = [
  'CONTRIBUTING.md',
  'CHANGELOG.md',
  'ARCHITECTURE.md',
  'SECURITY.md',
].map(name => join(DOCS, name));

const allowedRootFiles = new Set([README, ADMIN_GUIDE, ...OPTIONAL_ROOT_FILES]);
const allowedRootDirs = new Set([FRONTEND, INCLUDES, TECH]);

let violations = [];

function walk(dir) {
  for (const ent of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, ent.name);
    // Skip hidden files like .DS_Store anywhere under documentation
    if (ent.name.startsWith('.')) continue;
    if (dir === DOCS) {
      // root-level rules
      if (ent.isFile()) {
        if (!allowedRootFiles.has(full)) {
          violations.push(`Root docs must be minimal. Unexpected file at documentation/: ${relative(ROOT, full)}`);
        }
      } else if (ent.isDirectory()) {
        if (!allowedRootDirs.has(full)) {
          violations.push(`Unexpected root folder in documentation/: ${relative(ROOT, full)}`);
        }
      }
    }
    if (ent.isDirectory()) {
      walk(full);
    }
  }
}

try {
  // Verify required files and folders exist
  const req = [DOCS, TECH, FRONTEND, INCLUDES, README, ADMIN_GUIDE];
  for (const p of req) {
    try { statSync(p); } catch (_) { violations.push(`Missing required doc path: ${relative(ROOT, p)}`); }
  }

  walk(DOCS);

  if (violations.length) {
    console.error('Documentation structure violations found:');
    for (const v of violations) console.error(' -', v);
    process.exit(1);
  } else {
    console.log('OK: documentation structure passes.');
  }
} catch (e) {
  console.error('Docs guard error:', e?.message || e);
  process.exit(1);
}
