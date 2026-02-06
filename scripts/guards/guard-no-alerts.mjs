import { readFileSync } from 'fs';
import { glob } from 'glob';
import path from 'path';

const root = process.cwd();
const allow = new Set([]);

const ignoreGlobs = [
  '**/node_modules/**',
  '**/backups/**',
  '**/.git/**',
  '**/.husky/**',
  '**/scripts/guards/**',
  '**/documentation/**',
  '**/docs/**',
  '**/*.md',
  '**/*.svg',
  '**/*.json',
  '**/*.lock',
  '**/*.map',
];

const patterns = ['**/*.{php,js,html,mjs,cjs}'];

const violations = [];
const files = await glob(patterns, { ignore: ignoreGlobs, cwd: root, nodir: true });
for (const rel of files) {
  const norm = rel.replace(/\\/g,'/');
  const isAllowed = allow.has(norm);
  const abs = path.join(root, rel);
  let txt = '';
  try { txt = readFileSync(abs, 'utf8'); } catch { continue; }
  // Strip comments to avoid false positives from commented legacy code
  const src = txt
    // remove block comments
    .replace(/\/\*[\s\S]*?\*\//g, '')
    // remove line comments (//...)
    .replace(/(^|[^:])\/\/.*$/gm, '$1');
  // naive scan for alert( — after stripping comments
  const re = /\balert\s*\(/;
  if (re.test(src)) {
    if (!isAllowed) violations.push(norm);
  }
}

if (violations.length) {
  console.error('\n[guard-no-alerts] Disallowed alert() usage detected in:');
  for (const f of violations) console.error(' -', f);
  console.error('\nAllowed files (legacy, to be refactored later):');
  for (const f of allow) console.error(' -', f.replace(/\\/g,'/'));
  console.error('\nUse wfNotifications or showNotification instead of alert().');
  process.exit(1);
}
console.log('[guard-no-alerts] OK: no disallowed alert() found');
