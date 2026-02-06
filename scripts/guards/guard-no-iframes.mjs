import { readFileSync } from 'fs';
import { glob } from 'glob';
import path from 'path';

const root = process.cwd();
const allow = new Set([
  path.join('src','modules','admin-settings-lightweight.js'),
  path.join('sections','tools','template_manager.php'),
]);
const ignoreGlobs = [
  '**/node_modules/**',
  '**/backups/**',
  '**/dist/**',
  '**/.git/**',
  '**/.husky/**',
  '**/documentation/**',
  '**/docs/**',
  '**/*.md',
  '**/*.svg',
  '**/*.json',
  '**/*.lock',
];

const patterns = ['**/*.{php,js,html}'];

const violations = [];
const files = await glob(patterns, { ignore: ignoreGlobs, cwd: root, nodir: true });
for (const rel of files) {
  const isAllowed = allow.has(rel.replace(/\\/g,'/'));
  const abs = path.join(root, rel);
  let txt = '';
  try { txt = readFileSync(abs, 'utf8'); } catch { continue; }
  const re = /<\s*iframe\b/i;
  if (re.test(txt)) {
    if (!isAllowed) {
      violations.push(rel);
    }
  }
}

if (violations.length) {
  console.error('\n[guard-no-iframes] Disallowed <iframe> usage detected in:');
  for (const f of violations) console.error(' -', f);
  console.error('\nAllowed files:', Array.from(allow).join(', '));
  console.error('\nUse inline HTML modals. Room Map editor and Template Manager preview are the only allowed iframe usages.');
  process.exit(1);
}
console.log('[guard-no-iframes] OK: no disallowed iframes found');
