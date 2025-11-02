#!/usr/bin/env node
/**
 * Restore tooltips from the latest backup JSON (scripts/data/help-tooltips-*.json)
 * - Restores content/title/position for records that still exist in DB (by element_id + page_context)
 * - Skips records that do not currently exist (we will optionally re-add via DOM discovery script)
 * - Uses dev admin header on localhost
 */

import fs from 'node:fs';
import path from 'node:path';

const API_BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const IS_LOCAL = /localhost|127\.0\.0\.1/.test(API_BASE);
const API_URL = `${API_BASE}/api/help_tooltips.php`;
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';

function listBackups() {
  const primary = path.join(process.cwd(), 'backups', 'tooltips');
  const legacy = path.join(process.cwd(), 'scripts', 'data');
  let files = [];
  if (fs.existsSync(primary)) {
    files = files.concat(fs.readdirSync(primary).filter(f => /^help-tooltips-.*\.json$/i.test(f)).map(f => path.join(primary, f)));
  }
  if (fs.existsSync(legacy)) {
    files = files.concat(fs.readdirSync(legacy).filter(f => /^help-tooltips-.*\.json$/i.test(f)).map(f => path.join(legacy, f)));
  }
  files.sort();
  return files;
}

async function getJson(url) {
  const headers = { 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { headers });
  if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
  return res.json();
}

async function postJson(url, body) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify({ ...body, admin_token: ADMIN_TOKEN }) });
  if (!res.ok) throw new Error(`POST ${url} -> ${res.status}`);
  return res.json();
}

async function loadCurrentSet() {
  const contexts = ['settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing','db-status','db-web-manager','room-config-manager','cost-breakdown-manager'];
  const have = new Map(); // key => row
  for (const ctx of contexts) {
    try {
      const res = await getJson(`${API_URL}?action=get&page_context=${encodeURIComponent(ctx)}`);
      const rows = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      for (const r of rows) have.set(`${r.page_context}::${r.element_id}`, r);
    } catch {}
  }
  return have;
}

async function main() {
  const argv = new Set(process.argv.slice(2));
  const fileArg = (process.argv.find(a => a.startsWith('--file=')) || '').split('=')[1];
  const backups = listBackups() || [];
  let backupFile = null;
  if (fileArg) backupFile = path.isAbsolute(fileArg) ? fileArg : path.join(process.cwd(), fileArg);
  else if (argv.has('--previous')) backupFile = backups.length >= 2 ? backups[backups.length - 2] : (backups[0] || null);
  else backupFile = backups[backups.length - 1] || null;
  if (!backupFile) {
    console.error('[restore-tooltips] No backup file found under backups/tooltips or scripts/data');
    process.exit(1);
  }
  const backup = JSON.parse(fs.readFileSync(backupFile, 'utf8'));
  const byKey = new Map(backup.map(r => [`${r.page_context}::${r.element_id}`, r]));

  const current = await loadCurrentSet();

  let considered = 0, restored = 0, skipped = 0, errors = 0;
  for (const [key, cur] of current.entries()) {
    considered++;
    const prev = byKey.get(key);
    if (!prev) { skipped++; continue; }
    // Restore content/title/position from backup
    const payload = {
      element_id: cur.element_id,
      page_context: cur.page_context,
      title: prev.title || cur.title || '',
      content: prev.content || cur.content || '',
      position: prev.position || cur.position || 'top',
      is_active: cur.is_active ?? 1,
    };
    try {
      const res = await postJson(`${API_URL}?action=upsert`, payload);
      if (res && res.success) restored++; else errors++;
    } catch { errors++; }
  }
  console.log(`[restore-tooltips] Backup: ${backup.length}, Considered (current): ${considered}, Restored: ${restored}, Skipped(no backup): ${skipped}, Errors: ${errors}`);
}

main().catch(err => { console.error('[restore-tooltips] Fatal:', err); process.exit(1); });
