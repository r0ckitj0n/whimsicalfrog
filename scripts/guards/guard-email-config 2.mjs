#!/usr/bin/env node
import { execSync } from 'node:child_process';
import path from 'node:path';

function runRg(pattern, cwd) {
  try {
    const cmd = `rg -n --hidden --smart-case --glob '!**/backups/**' --glob '!**/dist/**' --glob '!**/vendor/**' --glob '!**/.git/**' "${pattern}" api includes sections`;
    const out = execSync(cmd, { cwd, stdio: ['ignore', 'pipe', 'pipe'] }).toString();
    return out.trim();
  } catch (e) {
    const out = e?.stdout?.toString?.() || '';
    const err = e?.stderr?.toString?.() || '';
    if (out.trim() === '' && (e.status === 1 || e.code === 1)) return '';
    if (err && /command not found|not recognized/.test(err)) {
      console.warn('[guard-email-config] ripgrep (rg) not found; skipping scan.');
      return '';
    }
    return out.trim();
  }
}

const repoRoot = path.resolve(process.cwd());
// Only flag usage of deprecated keys when they are tied to business_settings
// (DB storage) or explicit BusinessSettings::set calls. Do NOT flag email_logs
// columns or constants like FROM_EMAIL.
const _legacyKeys = ['from_email', 'from_name', 'admin_email', 'smtp_password'];
const allowedFiles = new Set([
  'api/save_email_config.php',
  'includes/email_helper.php', // may reference for migration/purge
]);

const patterns = [
  // SQL touching business_settings together with the legacy key
  '(?i)(business_settings|setting_key)[^\n]*\b(from_email|from_name|admin_email|smtp_password)\b',
  // Explicit BusinessSettings::set('from_email', ...)
  'BusinessSettings::set\(\s*[\"\'](from_email|from_name|admin_email|smtp_password)[\"\']',
];

const violations = [];
for (const pat of patterns) {
  const results = runRg(pat, repoRoot);
  if (!results) continue;
  const lines = results.split('\n').filter(Boolean);
  for (const line of lines) {
    const file = line.split(':', 1)[0];
    if (allowedFiles.has(file)) continue;
    // Ignore comments mentioning purge or documentation contexts
    if (/purge|README|DOCUMENT/i.test(line)) continue;
    // Only flag PHP files (avoid SQL schemas and logs viewers)
    if (!file.endsWith('.php')) continue;
    violations.push(line);
  }
}

if (violations.length) {
  console.error('\n[guard-email-config] Deprecated email keys found in code. Remove usage and source from Business Information instead.');
  for (const v of violations) console.error(' - ' + v);
  process.exit(1);
}

console.log('[guard-email-config] OK: no deprecated email keys used in code (outside allowed files).');
