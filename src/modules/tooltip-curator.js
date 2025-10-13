/* Tooltip Curator: rewrites DB tooltip copy with helpful, unique snark.
 * - Understands common admin actions/modals to produce accurate references
 * - Ensures each tooltip sounds distinct via variant selection
 * - Uses ApiClient if available; falls back to fetch
 */

import { ApiClient } from '../core/api-client.js';

(function(){
  const API = {
    async list(page_context){
      const url = `/api/help_tooltips.php?action=get&page_context=${encodeURIComponent(page_context)}`;
      return await ApiClient.get(url);
    },
    async upsert(row){
      const url = `/api/help_tooltips.php?action=upsert`;
      return await ApiClient.post(url, row);
    }
  };

  const CONTEXTS_DEFAULT = ['settings','customers','inventory','orders','pos','reports','admin','common'];
  const CURATION_TAG = '[WF Curated v1]';

  const hash = (s) => {
    let h = 2166136261; for (let i=0;i<s.length;i++) { h ^= s.charCodeAt(i); h += (h<<1)+(h<<4)+(h<<7)+(h<<8)+(h<<24); }
    return (h>>>0);
  };

  const nice = (s) => (s||'').replace(/[-_]+/g,' ').replace(/\b([a-z])/g, m=>m.toUpperCase());

  function inferScope(page, id){
    const s = (String(id||'')+':'+String(page||'')).toLowerCase();
    if (s.includes('square')) return 'Square Settings';
    if (s.includes('css') || s.includes('style')) return 'CSS Rules';
    if (s.includes('business') || s.includes('brand')) return 'Business Info';
    if (s.includes('email')) return 'Email Settings';
    if (s.includes('attr') || s.includes('attribute')) return 'Attributes Manager';
    if (s.includes('room')) return 'Room Settings';
    if (s.includes('category')) return 'Category Manager';
    if (s.includes('customer') || s.includes('user')) return 'Customers';
    if (s.includes('order')) return 'Orders';
    if (s.includes('inventory') || s.includes('item') || s.includes('sku')) return 'Inventory';
    if (s.includes('pos')) return 'Point of Sale';
    if (s.includes('report')) return 'Reports';
    return nice(page||'Settings');
  }

  function classify(id){
    const raw = String(id||'');
    const s = raw.toLowerCase();
    if (s.startsWith('action:')) return { kind:'action', action:s.split(':')[1]||s, raw };
    if (/save(btn)?$/i.test(raw) || s.includes('save')) return { kind:'save', action:'save', raw };
    if (/(^|[-_])cancel(btn)?$/i.test(raw) || s.includes('cancel')) return { kind:'cancel', action:'cancel', raw };
    if (s.includes('duplicate')) return { kind:'duplicate', action:'duplicate', raw };
    if (s.includes('import')) return { kind:'import', action:'import', raw };
    if (s.includes('export')) return { kind:'export', action:'export', raw };
    if (s.includes('preview')) return { kind:'preview', action:'preview', raw };
    if (s.includes('reset')) return { kind:'reset', action:'reset', raw };
    if (s.includes('delete') || s.includes('remove')) return { kind:'delete', action:'delete', raw };
    if (s.includes('move-up')) return { kind:'move-up', action:'move-up', raw };
    if (s.includes('move-down')) return { kind:'move-down', action:'move-down', raw };
    if (s.includes('open')) return { kind:'open', action:'open', raw };
    return { kind:'generic', action:'', raw };
  }

  const VARIANTS = {
    save: [
      (scope) => ({ t:`Save ${scope}`, c:`Writes your changes to ${scope}. Like a seatbelt for settings—click it before things get bumpy.` }),
      (scope) => ({ t:`Apply Changes`, c:`Commits edits to ${scope} and makes them live. Drafts are for novels, not admin panels.` }),
      (scope) => ({ t:`Lock It In`, c:`Stores updates to ${scope}. If future-you disagrees, future-you can change it back.` }),
    ],
    cancel: [
      (scope) => ({ t:`Close ${scope}`, c:`Dismisses the ${scope} dialog without saving. Exit stage left—props stay where they were.` }),
      (scope) => ({ t:`Nevermind`, c:`Closes ${scope}. Changes that didn’t meet the Save button won’t be invited.` }),
      (scope) => ({ t:`Back Out Gracefully`, c:`Leaves ${scope} as-is. No hard feelings, no saved changes.` }),
    ],
    duplicate: [
      (scope) => ({ t:`Duplicate`, c:`Clones the selected ${scope} item. Because doing it twice by hand is a hobby, not a workflow.` }),
      (scope) => ({ t:`Make a Copy`, c:`Copies the current ${scope} item so you can tweak without breaking the original. Chaos, but contained.` }),
    ],
    import: [
      (scope) => ({ t:`Import`, c:`Brings external data into ${scope}. Check your columns—spreadsheets are unforgiving.` }),
      (scope) => ({ t:`Upload Into ${scope}`, c:`Loads data into ${scope}. Backup first. Adventure second.` }),
    ],
    export: [
      (scope) => ({ t:`Export`, c:`Sends ${scope} data to a file. Accountability in CSV form.` }),
    ],
    preview: [
      (scope) => ({ t:`Preview`, c:`Shows how ${scope} changes will look. Try before you buy—no commitment.` }),
    ],
    reset: [
      (scope) => ({ t:`Reset`, c:`Reverts ${scope} to defaults. The “oops” button—use sparingly, with beverages.` }),
    ],
    delete: [
      (scope) => ({ t:`Delete`, c:`Removes the selected ${scope} item. Gone means gone—triple-check before clicking.` }),
    ],
    'move-up': [
      (scope) => ({ t:`Move Up`, c:`Bumps this item higher in ${scope}. Because order matters and ego is real.` }),
    ],
    'move-down': [
      (scope) => ({ t:`Move Down`, c:`Drops this item lower in ${scope}. Sometimes second place builds character.` }),
    ],
    open: [
      (scope) => ({ t:`Open ${scope}`, c:`Opens the ${scope} panel. Step inside, adjust things, pretend nothing ever breaks.` }),
      (scope) => ({ t:`Launch ${scope}`, c:`Loads the ${scope} editor. Fasten your UI seatbelt.` }),
    ],
    generic: [
      (scope) => ({ t:`${scope}`, c:`Controls for ${scope}. Hover around—each button has a job and an attitude.` }),
      (scope) => ({ t:`${scope} Controls`, c:`Short tour of ${scope}: buttons do things; this one explains which.` }),
      (scope) => ({ t:`About ${scope}`, c:`You’re adjusting ${scope}. The rest of the UI will try not to take it personally.` }),
    ],
  };

  // Highly opinionated, explicit mappings for well-known controls
  // Priority order when curating: Exact element_id > action:* > page defaults > variants
  const EXPLICIT = {
    // Global/admin
    admin: {
      adminHelpToggleBtn: { t: 'Help Tooltips', c: 'Toggle contextual tips across the admin. On for guidance, off for bravery.' },
      adminHelpDocsBtn:   { t: 'Open Help Docs', c: 'Opens documentation in a modal: tips, patterns, and the occasional pep talk.' },
    },
    settings: {
      // Business Info
      'action:open-business-info': { t: 'Open Business Info', c: 'Name, contact, hours, and brand basics—your storefront’s ID card.' },
      'action:business-info-save': { t: 'Save Business Info', c: 'Writes your business profile. Accuracy beats mystique here.' },
      // CSS Rules
      'action:open-css-rules': { t: 'Open CSS Rules', c: 'Tweak styles from a safe editor—no spelunking required.' },
      'action:css-rules-save': { t: 'Save CSS Rules', c: 'Applies your CSS updates. If it looks weird, that’s called iteration.' },
      // Square
      'action:square-save-settings': { t: 'Save Square Settings', c: 'Stores your Square credentials/config. Payments prefer precision.' },
      'action:square-test-connection': { t: 'Test Square Connection', c: 'Checks your Square setup. Green lights mean swipe freely.' },
      'action:square-sync-items': { t: 'Sync Items from Square', c: 'Imports/refreshes items from Square. Coffee advised for big catalogs.' },
      'action:square-clear-token': { t: 'Clear Square Token', c: 'Removes stored token—great for resets, unforgiving to typos.' },
      // Attributes Manager
      'action:attr-add': { t: 'Add Attribute', c: 'Create a new attribute (e.g., Color, Size). Pick names a human would admire.' },
      'action:attr-add-form': { t: 'Create Attribute', c: 'Submits the form to add that attribute. Sensible > cryptic.' },
      'action:attr-save-order': { t: 'Save Attribute Order', c: 'Locks in the current order so options appear like you intended.' },
      'action:move-up': { t: 'Move Up', c: 'Shifts this row higher in the list. Click again to keep climbing.' },
      'action:move-down': { t: 'Move Down', c: 'Drops this row lower. It can take the hint.' },
    },
    inventory: {
      saveBtn: { t: 'Save Item', c: 'Commits changes to this item. Drafts are great, saved drafts are greater.' },
      duplicateBtn: { t: 'Duplicate Item', c: 'Make a copy so you can iterate without touching the original.' },
      importBtn: { t: 'Import Items', c: 'Bring in a CSV and let the catalog grow—validate columns first.' },
    },
    orders: {
      'action:delete-order': { t: 'Delete Order', c: 'Removes the order. Consider accounting before wielding this power.' },
    },
    customers: {
      saveBtn: { t: 'Save Customer', c: 'Writes customer updates. Clean records, happy inboxes.' },
    },
  };

  function scopeFromAction(action){
    const a = String(action||'').toLowerCase();
    if (a.startsWith('attr-')) return 'Attributes Manager';
    if (a.startsWith('css-') || a.includes('css')) return 'CSS Rules';
    if (a.startsWith('square-') || a.includes('square')) return 'Square Settings';
    if (a.includes('business')) return 'Business Info';
    if (a.includes('email')) return 'Email Settings';
    if (a.includes('order')) return 'Orders';
    if (a.includes('customer')) return 'Customers';
    return '';
  }

  function pickExplicit(page, id){
    const p = (page||'').toLowerCase();
    const byPage = EXPLICIT[p] || {};
    // Exact id match
    if (byPage[id]) return byPage[id];
    // action:* match
    if (id.toLowerCase().startsWith('action:') && byPage[id]) return byPage[id];
    // Try admin fallbacks
    if (EXPLICIT.admin && EXPLICIT.admin[id]) return EXPLICIT.admin[id];
    return null;
  }

  function curateRow(t){
    const page = t.page_context || 'settings';
    const id = String(t.element_id||'');
    const cls = classify(id);
    const explicitScope = cls.kind==='action' ? scopeFromAction(cls.action) : '';
    const scope = explicitScope || inferScope(page, id);
    // First: explicit mapping if present
    const direct = pickExplicit(page, id);
    let title, content;
    if (direct) {
      title = direct.t; content = direct.c;
    } else {
      // Otherwise choose a variant based on hashed id+page
      const pool = VARIANTS[cls.kind] || VARIANTS.generic;
      const pick = pool[ hash(id+page) % pool.length ];
      ({ t: title, c: content } = pick(scope));
    }

    // Fine-grained enhancements for specific actions
    const a = (cls.action||'').toLowerCase();
    if (cls.kind==='action' && !direct) {
      if (a==='attr-add') { title='Add Attribute'; content='Creates a new attribute (e.g., Color, Size) in Attributes Manager. Name it like someone else will actually read it.'; }
      if (a==='attr-add-form') { title='Create Attribute'; content='Submits the new attribute form. Sensible names beat cryptic ones every time.'; }
      if (a==='attr-save-order') { title='Save Attribute Order'; content='Locks in the current attribute order so options show up in a human-friendly sequence.'; }
      if (a==='move-up') { title='Move Up'; content='Shifts this row upward. Tap again to keep climbing—meritocracy-by-click.'; }
      if (a==='move-down') { title='Move Down'; content='Shifts this row downward. It can take the hint.'; }
      if (a==='open-css-rules') { title='Open CSS Rules'; content='Opens the CSS Rules editor so you can tweak styles without spelunking in files.'; }
      if (a==='css-rules-save') { title='Save CSS Rules'; content='Saves your rule changes and applies them. If something looks weird, that’s called iteration.'; }
      if (a==='open-business-info') { title='Open Business Info'; content='Opens your business profile panel: name, contact, hours, and brand basics.'; }
      if (a==='business-info-save') { title='Save Business Info'; content='Updates your business profile. Consistency beats perfection, but we’ll take both.'; }
      if (a==='open-email-settings') { title='Open Email Settings'; content='Opens the email configuration panel—sender, templates, and deliverability sanity.'; }
      if (a==='open-room-settings') { title='Open Room Settings'; content='Opens room configuration. Doors, backgrounds, and the vibe check.'; }
      if (a==='square-save-settings') { title='Save Square Settings'; content='Stores your Square credentials/config. Payments prefer accuracy over charisma.'; }
      if (a==='square-test-connection') { title='Test Square Connection'; content='Pings Square with your settings. If it responds, we’re friends.'; }
      if (a==='square-sync-items') { title='Sync Items from Square'; content='Imports/updates items from Square. Coffee recommended for large catalogs.'; }
      if (a==='square-clear-token') { title='Clear Square Token'; content='Removes the stored Square token. Useful for starting fresh, terrifying for typos.'; }
    }

    // Avoid overly long titles
    if (title.length > 80) title = title.slice(0,77)+'…';
    if (content.length > 280) content = content.slice(0,277)+'…';
    // Tag for traceability
    if (!content.includes(CURATION_TAG)) content = `${content} ${CURATION_TAG}`;

    return {
      element_id: id,
      page_context: page,
      title,
      content,
      position: t.position || 'top',
      is_active: 1
    };
  }

  async function curateAll({ contexts=CONTEXTS_DEFAULT, dryRun=false }={}){
    const originalsByCtx = {};
    const curated = [];
    for (const ctx of contexts) {
      const res = await API.list(ctx);
      const list = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      originalsByCtx[ctx] = list;
      for (const row of list) curated.push(curateRow(row));
    }
    if (dryRun) return { total: curated.length, rows: curated };
    // Only upsert changed entries (title/content differ)
    let ok=0, fail=0, skipped=0;
    const byKey = (arr) => {
      const m = new Map();
      for (const r of arr) m.set(`${r.page_context}::${r.element_id}`, r);
      return m;
    };
    const originals = byKey(Object.values(originalsByCtx).flat());
    for (const row of curated) {
      const key = `${row.page_context}::${row.element_id}`;
      const prev = originals.get(key);
      if (prev && prev.title === row.title && prev.content === row.content) { skipped++; continue; }
      const r = await API.upsert(row);
      if (r && r.success) ok++; else fail++;
      // Gentle pacing
      await new Promise(r => setTimeout(r, 10));
    }
    return { ok, fail, skipped, total: curated.length };
  }

  const Curator = {
    async preview(contexts){
      const out = await curateAll({ contexts, dryRun:true });
      console.group('[WF Tooltip Curator] Preview');
      console.table(out.rows.slice(0,20).map(r=>({ page:r.page_context, id:r.element_id, title:r.title })));
      console.info('Total curated (dry-run):', out.total);
      console.groupEnd();
      return out;
    },
    async curateAndUpsert(contexts){
      const res = await curateAll({ contexts, dryRun:false });
      console.info('[WF Tooltip Curator] Upsert complete:', res);
      try { window.__wfDebugTooltips && window.__wfDebugTooltips(); } catch(_) {}
      return res;
    },
    async curateAndUpsertVerbose(contexts){
      const ctxs = contexts && contexts.length ? contexts : CONTEXTS_DEFAULT;
      console.group('[WF Tooltip Curator] Starting verbose curation');
      console.info('Contexts:', ctxs.join(', '));
      const preview = await curateAll({ contexts: ctxs, dryRun:true });
      console.table(preview.rows.slice(0, 50).map(r => ({ page:r.page_context, id:r.element_id, title:r.title })));
      console.info('Will upsert total:', preview.total);
      console.groupEnd();
      const res = await curateAll({ contexts: ctxs, dryRun:false });
      console.info('[WF Tooltip Curator] Verbose upsert result:', res);
      try { window.__wfDebugTooltips && window.__wfDebugTooltips(); } catch(_) {}
      return res;
    }
  };

  try { window.WF_TooltipCurator = Curator; } catch(_) {}

  // Auto-run (DISABLED BY DEFAULT). To enable, set: localStorage.wf_tooltip_auto_curate = 'true'
  try {
    const allow = (localStorage.getItem('wf_tooltip_auto_curate') === 'true');
    if (allow && !sessionStorage.getItem('wf_tooltip_curated')) {
      // Force a comprehensive pass with verbose logging and all contexts
      const run = () => Curator.curateAndUpsertVerbose(CONTEXTS_DEFAULT);
      if ('requestIdleCallback' in window) {
        // @ts-ignore
        window.requestIdleCallback(() => run(), { timeout: 2000 });
      } else {
        setTimeout(() => run(), 1500);
      }
      try { sessionStorage.setItem('wf_tooltip_curated','1'); } catch(_) {}
    }
  } catch(_) {}
})();
