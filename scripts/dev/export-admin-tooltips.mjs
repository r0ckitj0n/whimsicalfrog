#!/usr/bin/env node
/**
 * Export help_tooltips to versioned JSON under scripts/data/
 */

import fs from 'node:fs';
import path from 'node:path';

const API_BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const IS_LOCAL = /localhost|127\.0\.0\.1/.test(API_BASE);
const API_URL = `${API_BASE}/api/help_tooltips.php`;

async function getJson(url) {
  const headers = { 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { headers });
  if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
  return res.json();
}

async function main() {
  // Aggregate via public 'get' per context to avoid admin-only list_all
  const contexts = [
    'settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing',
    'db-status','db-web-manager','room-config-manager','cost-breakdown-manager'
  ];
  const seen = new Set();
  const all = [];
  for (const ctx of contexts) {
    try {
      const res = await getJson(`${API_URL}?action=get&page_context=${encodeURIComponent(ctx)}`);
      const rows = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      for (const r of rows) {
        const key = `${(r.page_context||ctx)}::${r.element_id}`;
        if (seen.has(key)) continue;
        seen.add(key);
        all.push(r);
      }
    } catch (_) {
      // non-fatal: context may not exist
    }
  }

  const outDir = path.join(process.cwd(), 'backups', 'tooltips');
  fs.mkdirSync(outDir, { recursive: true });
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const outFile = path.join(outDir, `help-tooltips-${stamp}.json`);
  fs.writeFileSync(outFile, JSON.stringify(all, null, 2), 'utf8');
  console.log(`[export-tooltips] Wrote ${outFile} (${all.length} records)`);
}

main().catch(err => { console.error('[export-tooltips] Fatal:', err); process.exit(1); });
