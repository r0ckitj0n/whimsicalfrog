#!/usr/bin/env node
/*
 * remove_curation_tags.mjs
 * One-time cleanup: strips the "[WF Curated v1]" suffix from tooltip contents across contexts.
 * Target API base: http://localhost:8080
 */

const BASE = process.env.WF_DEV_ORIGIN || 'http://localhost:8080';
const TAG = '[WF Curated v1]';
const CONTEXTS = (process.env.WF_TIP_CONTEXTS || 'settings,customers,inventory,orders,pos,reports,admin,common')
  .split(',')
  .map(s => s.trim())
  .filter(Boolean);

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

function stripTag(s) {
  if (!s) return '';
  const esc = TAG.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return String(s).replace(new RegExp(`\\s*${esc}`, 'g'), '').trim();
}

async function apiGet(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error(`GET ${url} => ${res.status}`);
  return await res.json();
}

async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error(`POST ${url} => ${res.status}`);
  return await res.json();
}

(async () => {
  let scanned = 0, updated = 0, skipped = 0, errors = 0;
  console.log(`[WF Tooltip Cleanup] Starting. Base: ${BASE} Contexts: ${CONTEXTS.join(', ')}`);
  for (const ctx of CONTEXTS) {
    try {
      const listRes = await apiGet(`${BASE}/api/help_tooltips.php?action=get&page_context=${encodeURIComponent(ctx)}`);
      const tooltips = (listRes && listRes.success && Array.isArray(listRes.tooltips)) ? listRes.tooltips : [];
      for (const row of tooltips) {
        scanned++;
        const cleaned = stripTag(row.content);
        if (cleaned === (row.content || '')) { skipped++; continue; }
        const payload = {
          element_id: row.element_id,
          page_context: row.page_context || ctx,
          title: row.title,
          content: cleaned,
          position: row.position || 'top',
          is_active: row.is_active ? 1 : 1,
        };
        try {
          const upsertRes = await apiPost(`${BASE}/api/help_tooltips.php?action=upsert`, payload);
          if (upsertRes && upsertRes.success) updated++; else errors++;
        } catch (e) {
          errors++;
          console.warn('[WF Tooltip Cleanup] Upsert error for', payload.element_id, e.message);
        }
        await sleep(10);
      }
    } catch (e) {
      errors++;
      console.warn(`[WF Tooltip Cleanup] Failed to process context ${ctx}:`, e.message);
    }
  }
  console.log('[WF Tooltip Cleanup] Done:', { scanned, updated, skipped, errors });
  process.exit(errors ? 1 : 0);
})();
