#!/usr/bin/env node
/**
 * Prune stale tooltips by scanning the codebase for element presence
 * - Dry-run lists tooltips whose element_id cannot be found in code
 * - Apply will soft-delete (is_active=0) those tooltips via API
 * - Heuristics:
 *    - If element_id starts with "action:", search for data-action="..."
 *    - Else search for id="...", for="...", data-action="...", usages in JS strings
 */

import fs from 'node:fs';
import { glob } from 'glob';

const API_BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const IS_LOCAL = /localhost|127\.0\.0\.1/.test(API_BASE);
const API_URL = `${API_BASE}/api/help_tooltips.php`;
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';

const args = new Set(process.argv.slice(2));
const APPLY = args.has('--apply');

const INCLUDES = [
  'sections/**/*.php',
  'components/**/*.php',
  'partials/**/*.php',
  'templates/**/*.php',
  'api/**/*.php',
  '*.php',
  'src/**/*.{js,mjs,ts,tsx,jsx}',
  'public/**/*.html'
];
const EXCLUDES = [
  '**/node_modules/**',
  '**/vendor/**',
  '**/backups/**',
  '**/documentation/**',
  '**/logs/**',
  '**/.git/**',
  '**/dist/**',
  '**/build/**',
  '**/.vite/**'
];

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

function buildSearchTokens(elementId) {
  const id = String(elementId || '');
  let action = id.toLowerCase();
  if (action.startsWith('action:')) action = action.slice(7);
  const tokens = new Set();
  // direct id or data-action
  tokens.add(`id="${id}"`);
  tokens.add(`for="${id}"`);
  tokens.add(`data-action="${id}"`);
  tokens.add(`data-action='${id}'`);
  tokens.add(`"${id}"`); // JS string presence
  // action-only search
  tokens.add(`data-action="${action}"`);
  tokens.add(`data-action='${action}'`);
  tokens.add(`"${action}"`);
  // label css selector in code snippets
  tokens.add(`label[for="${id}"]`);
  tokens.add(`label[for='${id}']`);
  return Array.from(tokens);
}

function fileListCache() {
  let cache = null;
  return async () => {
    if (cache) return cache;
    const files = await glob(INCLUDES, { ignore: EXCLUDES, dot: false, nodir: true, absolute: true });
    cache = files;
    return files;
  };
}

const getFiles = fileListCache();

function read(file) {
  try { return fs.readFileSync(file, 'utf8'); } catch { return ''; }
}

async function elementAppearsInCode(elementId) {
  const files = await getFiles();
  const tokens = buildSearchTokens(elementId);
  // quick pass: if any token exists in any file, consider present
  for (const f of files) {
    const text = read(f);
    if (!text) continue;
    for (const t of tokens) {
      if (text.includes(t)) return true;
    }
  }
  return false;
}

async function loadCurrentTooltips() {
  const contexts = ['settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing','db-status','db-web-manager','room-config-manager','cost-breakdown-manager'];
  const rows = [];
  for (const ctx of contexts) {
    try {
      const res = await getJson(`${API_URL}?action=get&page_context=${encodeURIComponent(ctx)}`);
      const list = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      rows.push(...list);
    } catch {}
  }
  return rows;
}

async function main() {
  const rows = await loadCurrentTooltips();
  const stale = [];

  for (const r of rows) {
    const present = await elementAppearsInCode(r.element_id);
    if (!present) stale.push(r);
  }

  if (!APPLY) {
    console.log(`[prune-tooltips] Total: ${rows.length}, Stale: ${stale.length}`);
    for (const s of stale.slice(0, 200)) {
      console.log(` - [${s.page_context}] ${s.element_id} (id=${s.id ?? '?'})`);
    }
    if (stale.length > 200) console.log(` ... and ${stale.length - 200} more`);
    console.log('Run with --apply to soft-delete stale tooltips (is_active=0)');
    return;
  }

  let deleted = 0, errors = 0;
  for (const s of stale) {
    try {
      const payload = {
        element_id: s.element_id,
        page_context: s.page_context,
        title: s.title || '',
        content: s.content || '',
        position: s.position || 'top',
        is_active: 0,
      };
      const res = await postJson(`${API_URL}?action=upsert`, payload);
      if (res && res.success) deleted++; else errors++;
    } catch { errors++; }
  }
  console.log(`[prune-tooltips] Deleted ${deleted}/${stale.length} stale tooltips. Errors: ${errors}`);
}

main().catch(err => { console.error('[prune-tooltips] Fatal:', err); process.exit(1); });
