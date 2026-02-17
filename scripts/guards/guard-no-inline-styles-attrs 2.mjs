#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { globSync } from 'glob';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const repoRoot = path.resolve(__dirname, '../../');

const defaultIgnores = [
  '**/node_modules/**',
  '**/.git/**',
  '**/dist/**',
  '**/backups/**',
  '**/*.backup*',
  '**/vendor/**',
  'templates/email/**',
  'templates/wf-starter/**',
  'documentation/**',
  'reports/**',
];

// Target template-like files
const patterns = [
  '**/*.php',
  '**/*.html',
  '**/*.htm',
];

function scanFile(fp) {
  const txt = fs.readFileSync(fp, 'utf8');
  const lines = txt.split(/\r?\n/);
  const hits = [];
  // Only match style attributes that are inside an HTML tag context on the same line
  const re = /<[^>]*?\bstyle\s*=\s*(["']).*?\1/ig;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (!line) continue;
    re.lastIndex = 0;
    if (re.test(line)) {
      // Allowlist emails by path handled above; also allow inline <style> tags (only attributes are blocked)
      hits.push({ line: i + 1, text: line.trim().slice(0, 200) });
    }
  }
  return hits;
}

function main() {
  const allowEmail = new Set(['templates/email']);
  const allowFiles = new Set([]);
  const files = patterns
    .map((pat) => globSync(pat, { cwd: repoRoot, nodir: true, ignore: defaultIgnores }))
    .flat()
    // Skip email template directory explicitly (handled in ignore, but double-guard)
    .filter((f) => ![...allowEmail].some((p) => f.startsWith(p + '/')))
    .filter((f) => !allowFiles.has(f));

  const violations = [];
  for (const rel of files) {
    // Allowlist specific files if needed
    if (rel === 'partials/header.php') continue; // header JS fallback may embed style attributes in script output later
    try {
      const abs = path.join(repoRoot, rel);
      const hits = scanFile(abs);
      if (hits.length) {
        violations.push({ file: rel, hits });
      }
    } catch (e) {
      // ignore read errors
    }
  }

  if (violations.length) {
    console.error('[Guard] Inline style attributes are forbidden (except email templates).');
    for (const v of violations) {
      console.error('  ' + v.file);
      for (const h of v.hits.slice(0, 5)) {
        console.error(`    L${h.line}: ${h.text}`);
      }
      if (v.hits.length > 5) console.error(`    ... and ${v.hits.length - 5} more`);
    }
    process.exit(1);
  } else {
    console.log('[Guard] OK: No inline style attributes found.');
  }
}

main();
