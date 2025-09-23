#!/usr/bin/env node
/**
 * Export help_tooltips to versioned JSON under scripts/data/
 */

import fs from 'node:fs';
import path from 'node:path';

const API_BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const API_URL = `${API_BASE}/api/help_tooltips.php?action=list_all`;
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';

async function getJson(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
  return res.json();
}

async function main() {
  const data = await getJson(`${API_URL}&admin_token=${encodeURIComponent(ADMIN_TOKEN)}`);
  if (!data?.success) throw new Error('API returned unsuccessful');

  const outDir = path.join(process.cwd(), 'scripts', 'data');
  fs.mkdirSync(outDir, { recursive: true });
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const outFile = path.join(outDir, `help-tooltips-${stamp}.json`);
  fs.writeFileSync(outFile, JSON.stringify(data.tooltips || [], null, 2), 'utf8');
  console.log(`[export-tooltips] Wrote ${outFile} (${(data.tooltips || []).length} records)`);
}

main().catch(err => { console.error('[export-tooltips] Fatal:', err); process.exit(1); });
