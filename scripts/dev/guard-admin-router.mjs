#!/usr/bin/env node
import { readdirSync, statSync, readFileSync } from 'fs';
import { join } from 'path';

const ROOT = process.cwd();
const EXCLUDES = new Set([
  'node_modules', '.git', '.cache', 'vendor', 'dist', '.husky', '.github', 'logs', 'documentation',
]);

const LEGACY_RE = /\/admin\/admin\.php(\?|$)/; // match /admin/admin.php and with query

const violations = [];
function walk(dir) {
  const items = readdirSync(dir, { withFileTypes: true });
  for (const ent of items) {
    const name = ent.name;
    if (EXCLUDES.has(name)) continue;
    const full = join(dir, name);
    try {
      const st = statSync(full);
      if (st.isDirectory()) {
        walk(full);
      } else if (st.isFile()) {
        // Skip binary-like files by extension
        if (/\.(png|jpg|jpeg|gif|webp|ico|ttf|woff|woff2|eot|svg|heic|heif|bmp)$/i.test(name)) continue;
        // Skip backups/ and reports/ by path if present
        if (full.includes('/backups/')) continue;
        const text = readFileSync(full, 'utf8');
        if (LEGACY_RE.test(text)) {
          // Record lines with matches
          const lines = text.split(/\r?\n/);
          lines.forEach((ln, idx) => {
            if (LEGACY_RE.test(ln)) {
              violations.push(`${full}:${idx + 1}: ${ln.trim()}`);
            }
          });
        }
      }
    } catch (e) {
      // ignore unreadable entries
    }
  }
}

walk(ROOT);

if (violations.length > 0) {
  console.error('\nLegacy admin router references found (use /admin/?section=...):');
  violations.forEach(v => console.error(' -', v));
  process.exit(1);
} else {
  console.log('OK: no references to /admin/admin.php found.');
}
