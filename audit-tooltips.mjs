#!/usr/bin/env node
/**
 * Audit help_tooltips for duplicates and anomalies (READ-ONLY)
 * - Detects any accidental duplicates by element_id (should not exist due to unique index)
 * - Detects same (title+content+position) repeated across many element_ids (potential content duplication)
 * - Detects element_ids that look like data-action names and those that look like IDs
 * - Outputs a JSON report to stdout
 */

const API_BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const API_URL = `${API_BASE}/api/help_tooltips.php?action=list_all`;
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';

async function getJson(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
  return res.json();
}

function normalizeText(s) {
  return (s || '').trim().replace(/\s+/g, ' ').toLowerCase();
}

async function main() {
  const data = await getJson(`${API_URL}&admin_token=${encodeURIComponent(ADMIN_TOKEN)}`);
  const rows = data.tooltips || [];

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
