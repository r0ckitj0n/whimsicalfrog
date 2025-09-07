#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const ROOT = process.cwd();
const RUNTIME_ROOTS = ['api', 'admin', 'includes', 'src', 'templates'];
const PATTERNS = [
  'debug', 'smoke', 'test', 'sample', 'old', 'deprecated', 'unused', 'tmp', 'temp', 'wip'
];

function walk(dir, out) {
  const entries = fs.existsSync(dir) ? fs.readdirSync(dir, { withFileTypes: true }) : [];
  for (const e of entries) {
    const full = path.join(dir, e.name);
    const rel = path.relative(ROOT, full).replaceAll('\\', '/');
    if (rel.startsWith('backups/')) continue; // ignore quarantined
    if (e.isDirectory()) {
      walk(full, out);
    } else {
      const name = e.name.toLowerCase();
      if (PATTERNS.some(p => name.includes(p))) out.push(rel);
    }
  }
}

const offenders = [];
for (const root of RUNTIME_ROOTS) {
  walk(path.join(ROOT, root), offenders);
}

if (offenders.length) {
  console.error('✖ Legacy scan found potential one-off/legacy files in runtime directories:');
  for (const f of offenders) console.error(' -', f);
  process.exit(1);
}
console.log('✓ Legacy scan passed: no suspicious files found in runtime directories.');
