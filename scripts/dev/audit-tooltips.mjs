#!/usr/bin/env node
/**
 * Audit help_tooltips for duplicates and anomalies (READ-ONLY)
 * - Detects any accidental duplicates by element_id (should not exist due to unique index)
 * - Detects same (title+content+position) repeated across many element_ids (potential content duplication)
 * - Detects element_ids that look like data-action names and those that look like IDs
 * - Outputs a JSON report to stdout
 */

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

function normalizeText(s) {
  return (s || '').trim().replace(/\s+/g, ' ').toLowerCase();
}

async function main() {
  // Aggregate via per-context public GET to avoid list_all in dev
  const contexts = [
    'settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing',
    'db-status','db-web-manager','room-config-manager','cost-breakdown-manager'
  ];
  const seen = new Set();
  const rows = [];
  for (const ctx of contexts) {
    try {
      const res = await getJson(`${API_URL}?action=get&page_context=${encodeURIComponent(ctx)}`);
      const list = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      for (const r of list) {
        const key = `${(r.page_context||ctx)}::${r.element_id}`;
        if (seen.has(key)) continue;
        seen.add(key);
        rows.push(r);
      }
    } catch (_) {
      // ignore missing contexts in audit
    }
  }

  const byElement = new Map();
  const dupElementIds = [];
  for (const r of rows) {
    if (byElement.has(r.element_id)) dupElementIds.push(r);
    else byElement.set(r.element_id, r);
  }

  // Content signature: title+content+position
  const byContentSig = new Map();
  for (const r of rows) {
    const sig = `${normalizeText(r.title)}|||${normalizeText(r.content)}|||${(r.position||'').toLowerCase()}`;
    const arr = byContentSig.get(sig) || [];
    arr.push(r);
    byContentSig.set(sig, arr);
  }
  const repeatedContentGroups = [...byContentSig.values()].filter(arr => arr.length > 3); // threshold: appears > 3 times

  // Heuristic: element_id that look like data-actions (kebab or contains dash)
  const actionLike = rows.filter(r => /[-]/.test(r.element_id));
  // Heuristic: element_id that look like DOM ids (camelCase or end with Btn/Modal)
  const idLike = rows.filter(r => /[A-Za-z]+(?:Btn|Modal|Form|Tab|Card)$/.test(r.element_id));

  const report = {
    total: rows.length,
    duplicate_element_id_count: dupElementIds.length,
    duplicate_element_ids: dupElementIds.map(r => r.element_id),
    repeated_content_groups: repeatedContentGroups.map(arr => ({ count: arr.length, sample: arr.slice(0,5).map(x => ({element_id:x.element_id,page_context:x.page_context,title:x.title})) })),
    action_like_count: actionLike.length,
    id_like_count: idLike.length,
  };

  console.log(JSON.stringify(report, null, 2));
}

main().catch(err => { console.error('[audit-tooltips] Fatal:', err); process.exit(1); });
