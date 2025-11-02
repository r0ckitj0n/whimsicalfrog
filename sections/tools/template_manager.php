<?php
// sections/tools/template_manager.php — Tailored Template Manager (read-only)
// Supports modal context via ?modal=1 for clean iframe embedding.

$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

$baseDir = realpath($root . '/templates');
$sub = isset($_GET['dir']) ? trim((string)$_GET['dir']) : '';
$relDir = $sub !== '' ? $sub : '';

// Prevent path traversal
$requestedPath = realpath($baseDir . '/' . $relDir);
if ($requestedPath === false || strpos($requestedPath, $baseDir) !== 0) {
  $requestedPath = $baseDir;
  $relDir = '';
}

function listDirectory($path) {
  $items = [];
  if (!is_dir($path) || !is_readable($path)) return $items;
  $dh = opendir($path);
  if (!$dh) return $items;
  while (($file = readdir($dh)) !== false) {
    if ($file === '.' || $file === '..') continue;
    $full = $path . '/' . $file;
    $isDir = is_dir($full);
    $items[] = [
      'name' => $file,
      'isDir' => $isDir,
      'size' => $isDir ? 0 : @filesize($full),
      'mtime' => @filemtime($full),
      'path' => $full,
    ];
  }
  closedir($dh);
  usort($items, function($a,$b){
    if ($a['isDir'] && !$b['isDir']) return -1;
    if (!$a['isDir'] && $b['isDir']) return 1;
    return strcasecmp($a['name'],$b['name']);
  });
  return $items;
}

$items = listDirectory($requestedPath);
$crumbs = [];
if ($relDir !== '') {
  $parts = explode('/', $relDir);
  $build = '';
  foreach ($parts as $p) {
    $build = ltrim($build . '/' . $p, '/');
    $crumbs[] = $build;
  }
}

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_template_manager_footer_shutdown')) {
      function __wf_template_manager_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_template_manager_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div id="templateManagerRoot" class="p-3 admin-actions-icons">
  <?php if (!$inModal): ?>
  <div class="admin-card">
    <div class="flex items-center justify-between">
      <h1 class="admin-card-title">Template Manager</h1>
      <div class="text-sm text-gray-600">Design templates and assign them to email events</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Email Templates Panel -->
  <div id="tmPanelEmail" class="space-y-3">
    <div class="admin-card">
      <div class="flex items-start justify-between gap-4">
        <div class="text-sm">
          <div class="font-semibold mb-1">How this works</div>
          <ol class="list-decimal list-inside space-y-1">
            <li><b>Create or edit templates</b> with subject + HTML content.</li>
            <li><b>Assign templates</b> to email types (e.g., order_confirmation).</li>
            <li><b>Preview or send a test</b> to verify formatting.</li>
          </ol>
          <div class="mt-2 text-xs text-gray-600">Tip: “Assignments” control which template is used when the system sends each kind of email.</div>
          <div class="mt-2 text-sm text-gray-600">Manage templates below, then use "Edit Assignments" to map them to email types.</div>
        </div>
        <div id="tmEmailToolbar" class="admin-form-inline">
          <button id="tmRefresh" class="btn-icon btn-icon--refresh" title="Refresh" aria-label="Refresh"></button>
          <button id="tmNew" class="btn-icon btn-icon--add" title="New Template" aria-label="New Template"></button>
          <button id="tmAssignEdit" class="btn-icon btn-icon--settings" title="Edit Assignments" aria-label="Edit Assignments"></button>
          <button id="tmSeedDefaults" class="btn-icon btn-icon--sparkles" data-action="tm-seed-defaults" title="Create Defaults" aria-label="Create Defaults"></button>
        </div>
      </div>
    </div>
    <div class="admin-card">
      <table class="admin-table">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left p-2 w-8">ID</th>
            <th class="text-left p-2">Name</th>
            <th class="text-left p-2">Type</th>
            <th class="text-left p-2">Subject</th>
            <th class="text-left p-2 w-20">Active</th>
            <th class="text-left p-2">Assigned To</th>
            <th class="text-left p-2 w-48">Actions</th>
          </tr>
        </thead>
        <tbody id="tmEmailBody" class="divide-y">
          <tr><td colspan="7" class="p-3 text-center text-gray-500">Loading…</td></tr>
        </tbody>
      </table>
    </div>
    
  </div>

  

  <!-- Assignments Editor Panel -->
  <div id="tmPanelAssignEditor" class="hidden">
    <div class="admin-card">
      <h3 class="admin-card-title">Edit Assignments</h3>
      <div class="text-sm text-gray-600">Select which template should be used for each email type, then click Save.</div>
    </div>
    <div class="admin-card">
      <table class="admin-table">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left p-2">Email Type</th>
            <th class="text-left p-2">Assigned Template</th>
          </tr>
        </thead>
        <tbody id="tmAssignEditorBody" class="divide-y">
          <tr><td colspan="2" class="p-3 text-center text-gray-500">Loading…</td></tr>
        </tbody>
      </table>
    </div>
    <div class="admin-card admin-form-inline">
      <div class="flex gap-2 justify-end w-full">
        <button id="tmAssignSave" class="btn btn-primary btn-sm">Save Assignments</button>
        <button id="tmAssignCancel" class="btn btn-secondary btn-sm">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Template Editor Modal -->
  <div id="tmTemplateModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="tmEditorTitle">
    <div class="admin-modal admin-modal-content admin-modal--lg">
      <div class="modal-header">
        <h2 id="tmEditorTitle" class="admin-card-title">Template Editor</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="tm-editor-close" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <form id="tmEditorForm" class="space-y-2" data-action="prevent-submit">
          <input type="hidden" id="tmId" />
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold mb-1" for="tmName">Name</label>
              <input id="tmName" class="form-input w-full" type="text" required />
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1" for="tmType">Type</label>
              <select id="tmType" class="form-select w-full">
                <option value="order_confirmation">Order Confirmation</option>
                <option value="admin_notification">Admin Notification</option>
                <option value="welcome">Welcome</option>
                <option value="password_reset">Password Reset</option>
                <option value="custom">Custom</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="tmSubject">Subject</label>
            <input id="tmSubject" class="form-input w-full" type="text" required />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="tmHtml">HTML Content</label>
            <div class="flex items-center justify-between mb-1">
              <div class="text-[11px] text-gray-600">Click a variable to insert at cursor:</div>
              <div id="tmVarHelper" class="flex flex-wrap gap-1"></div>
            </div>
            <textarea id="tmHtml" class="form-textarea w-full h-48" required></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="tmText">Text Content (optional)</label>
            <textarea id="tmText" class="form-textarea w-full h-24"></textarea>
          </div>
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold mb-1" for="tmDesc">Description (optional)</label>
              <input id="tmDesc" class="form-input w-full" type="text" />
            </div>
            <label class="inline-flex items-center text-xs mt-6">
              <input id="tmActive" type="checkbox" class="mr-2" /> Active
            </label>
          </div>
          <div class="pt-2 flex gap-2 justify-end">
            <button type="button" class="btn btn-secondary btn-xs" data-action="tm-editor-cancel">Cancel</button>
            <button type="button" class="btn btn-secondary btn-xs" data-action="tm-preview-draft" title="Preview current content without saving">Preview Draft</button>
            <button type="submit" class="btn btn-primary btn-xs" id="tmSave">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  (function(){
    function notify(msg, type){
      try {
        if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show(msg, type||'info'); return; }
        if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification(msg, type||'info'); return; }
        if (typeof window.showNotification === 'function') { window.showNotification(msg, type||'info'); return; }
      } catch(_) {}
    }
    async function brandedConfirm(message, options){
      try {
        if (window.parent && typeof window.parent.showConfirmationModal === 'function') {
          return await window.parent.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
        }
        if (typeof window.showConfirmationModal === 'function') {
          return await window.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
        }
      } catch(_) {}
      notify('Confirmation UI unavailable. Action canceled.', 'error');
      return false;
    }
    // No tabs; Email Templates is the sole panel
    const sendStatus = (m, ok) => { try { if (window.parent && window.parent !== window) window.parent.postMessage({ source:'wf-tm', type:'status', message: m||'', ok: !!ok }, '*'); } catch(_) {} };

    // API helpers (prefer shared ApiClient)
    async function apiRequest(method, url, data=null, options={}){
      const A = (typeof window !== 'undefined') ? (window.ApiClient || null) : null;
      const m = String(method||'GET').toUpperCase();
      if (A && typeof A.request === 'function') {
        if (m === 'GET') return A.get(url, (options && options.params) || {});
        if (m === 'POST') return A.post(url, data||{}, options||{});
        if (m === 'PUT') return A.put(url, data||{}, options||{});
        if (m === 'DELETE') return A.delete(url, options||{});
        return A.request(url, { method: m, ...(options||{}) });
      }
      const isFormData = (typeof FormData !== 'undefined') && (data instanceof FormData);
      const headers = isFormData ? { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) }
                                 : { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
      const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
      if (!isFormData && data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
      if (isFormData && typeof cfg.body === 'undefined') cfg.body = data;
      const res = await fetch(url, cfg);
      return res.json().catch(()=>null);
    }
    const apiGet = (url, params) => apiRequest('GET', url, null, { params });
    const apiPost = (url, body, options) => apiRequest('POST', url, body, options);
    const body = document.getElementById('tmEmailBody');
    const abody = null; // removed read-only assignments table
    const btnRefresh = document.getElementById('tmRefresh');
    const btnAssignEdit = document.getElementById('tmAssignEdit');
    const panelAssignEditor = document.getElementById('tmPanelAssignEditor');

    // Editor modal refs
    const editorOverlay = document.getElementById('tmTemplateModal');
    const editorForm = document.getElementById('tmEditorForm');
    const fId = document.getElementById('tmId');
    const fName = document.getElementById('tmName');
    const fType = document.getElementById('tmType');
    const fSubject = document.getElementById('tmSubject');
    const fHtml = document.getElementById('tmHtml');
    const fText = document.getElementById('tmText');
    const fDesc = document.getElementById('tmDesc');
    const fActive = document.getElementById('tmActive');
    const assignEditorBody = document.getElementById('tmAssignEditorBody');

    async function loadTemplates(){
      if (!body) return;
      body.innerHTML = '<tr><td colspan="7" class="p-3 text-center text-gray-500">Loading…</td></tr>';
      const [tplJ, asgJ] = await Promise.all([
        apiGet('/api/email_templates.php?action=get_all'),
        apiGet('/api/email_templates.php?action=get_assignments')
      ]);
      if (!tplJ || !tplJ.success) { body.innerHTML = '<tr><td colspan="7" class="p-3 text-center text-red-600">Failed to load templates</td></tr>'; return; }

      // Auto-seed defaults if none exist (one-time per page load)
      try {
        if (!loadTemplates.__seedAttempted) {
          const existing = Array.isArray(tplJ.templates) ? tplJ.templates : [];
          if (existing.length === 0) {
            loadTemplates.__seedAttempted = true;
            await seedDefaultTemplates();
            const re = await apiGet('/api/email_templates.php?action=get_all');
            if (re && re.success) {
              tplJ.templates = re.templates || [];
            }
          }
        }
      } catch(_) {}
      // Map template_id -> [types]
      const usage = {};
      try {
        (asgJ?.assignments || []).forEach(a => {
          const tid = String(a.template_id || '');
          if (!tid) return;
          if (!usage[tid]) usage[tid] = [];
          if (a.email_type) usage[tid].push(String(a.email_type));
        });
      } catch(_) {}
      const rows = (tplJ.templates||[]).map(t => {
        const id = t.id;
        const name = (t.template_name||'').toString();
        const type = (t.template_type||'').toString();
        const subj = (t.subject||'').toString();
        const active = (String(t.is_active)==='1'||String(t.is_active).toLowerCase()==='true') ? 'Yes' : 'No';
        const assigned = (usage[String(id)]||[]);
        const assignedBadges = assigned.length
          ? assigned.map(z => `<span class=\"code-badge\">${z}</span>`).join('')
          : '<span class="text-gray-400">—</span>';
        return `<tr>
          <td class="p-2">${id}</td>
          <td class="p-2">${name}</td>
          <td class="p-2">${type}</td>
          <td class="p-2">${subj}</td>
          <td class="p-2">${active}</td>
          <td class="p-2">${assignedBadges}</td>
          <td class="p-2">
            <button class="btn-icon btn-icon--preview" data-action="tm-preview" data-id="${id}" title="Preview HTML" aria-label="Preview HTML"></button>
            <button class="btn-icon btn-icon--preview-inline" data-action="tm-preview-inline" data-id="${id}" title="Preview Inline" aria-label="Preview Inline"></button>
            <button class="btn-icon btn-icon--send" data-action="tm-send-test" data-id="${id}" title="Send Test" aria-label="Send Test"></button>
            <button class="btn-icon btn-icon--edit" data-action="tm-edit" data-id="${id}" title="Edit" aria-label="Edit"></button>
            <button class="btn-icon btn-icon--duplicate" data-action="tm-duplicate" data-id="${id}" title="Duplicate" aria-label="Duplicate"></button>
            <button class="btn-icon btn-icon--archive" data-action="tm-archive" data-id="${id}" title="Archive" aria-label="Archive"></button>
          </td>
        </tr>`;
      }).join('');
      body.innerHTML = rows || '<tr><td colspan="7" class="p-3 text-center text-gray-500">No templates found</td></tr>';
    }

    async function loadAssignments(){
      if (!abody) return;
      abody.innerHTML = '<tr><td colspan="3" class="p-3 text-center text-gray-500">Loading…</td></tr>';
      const j = await apiGet('/api/email_templates.php?action=get_assignments');
      if (!j || !j.success) { abody.innerHTML = '<tr><td colspan="3" class="p-3 text-center text-red-600">Failed to load assignments</td></tr>'; return; }
      const rows = (j.assignments||[]).map(a => {
        const type = (a.email_type||'').toString();
        const name = (a.template_name||'').toString();
        const subj = (a.subject||'').toString();
        return `<tr>
          <td class="p-2">${type}</td>
          <td class="p-2">${name||'-'}</td>
          <td class="p-2">${subj||'-'}</td>
        </tr>`;
      }).join('');
      abody.innerHTML = rows || '<tr><td colspan="3" class="p-3 text-center text-gray-500">No assignments</td></tr>';
    }

    // Actions
    document.addEventListener('click', async (ev) => {
      const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action]') : null;
      if (!btn) return;
      const action = btn.getAttribute('data-action');
      if (action === 'tm-seed-defaults') {
        ev.preventDefault();
        try {
          await seedDefaultTemplates();
          await loadTemplates();
          notify('Default templates created (or already present). Assignments updated.', 'success');
        } catch(_) {
          notify('Failed to create defaults', 'error');
        }
        return;
      }
      if (action === 'tm-preview') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await apiGet(`/api/email_templates.php?action=preview&template_id=${encodeURIComponent(id)}`);
          if (j && j.success && j.preview && j.preview.html_content) {
            const w = window.open('', '_blank');
            if (w) {
              w.document.open();
              w.document.write(`<title>${(j.preview.subject||'Preview')}</title>`);
              w.document.write(j.preview.html_content);
              w.document.close();
            }
          }
        } catch(_) {}
      } else if (action === 'tm-preview-inline') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await apiGet(`/api/email_templates.php?action=preview&template_id=${encodeURIComponent(id)}`);
          if (j && j.success && j.preview && j.preview.html_content) {
            openPreview(j.preview.subject||'Preview', j.preview.html_content);
          }
        } catch(_) {}
      } else if (action === 'tm-send-test') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        const email = (window.parent && typeof window.parent.showPromptModal === 'function')
          ? await window.parent.showPromptModal({ title: 'Send Test', message: 'Send test to email address:', inputType: 'email', placeholder: 'name@example.com', confirmText: 'Send', cancelText: 'Cancel' })
          : (typeof window.showPromptModal === 'function'
              ? await window.showPromptModal({ title: 'Send Test', message: 'Send test to email address:', inputType: 'email', placeholder: 'name@example.com', confirmText: 'Send', cancelText: 'Cancel' })
              : window.prompt('Send test to email address:'));
        if (!email) return;
        try {
          const j = await apiPost('/api/email_templates.php?action=send_test', { template_id: id, test_email: email });
          if (j && j.success) notify(j.message || 'Test sent', 'success'); else notify('Failed to send test', 'error');
        } catch(_) { notify('Failed to send test', 'error'); }
      } else if (action === 'tm-edit') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await apiGet(`/api/email_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
          if (j && j.success && j.template) {
            openEditor(j.template);
          }
        } catch(_) {}
      } else if (action === 'tm-duplicate') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await apiGet(`/api/email_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
          if (j && j.success && j.template) {
            const t = j.template;
            // Open editor with no id and a copied name
            openEditor({
              id: '',
              template_name: `${t.template_name || 'Template'} (Copy)`,
              template_type: t.template_type || 'custom',
              subject: t.subject || '',
              html_content: t.html_content || '',
              text_content: t.text_content || '',
              description: t.description || '',
              is_active: t.is_active
            });
          }
        } catch(_) {}
      } else if (action === 'tm-archive') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        if (!id) return;
        if (!(await brandedConfirm('Archive this template? This will set it to inactive.', { confirmText:'Archive', confirmStyle:'danger', iconType:'warning' }))) return;
        try {
          // Minimal update: set is_active=0
          const j = await apiPost('/api/email_templates.php?action=update', { template_id: id, is_active: 0 });
          if (j && j.success) loadTemplates(); else notify('Archive failed', 'error');
        } catch(_) { notify('Archive failed', 'error'); }
      } else if (action === 'tm-editor-close' || action === 'tm-editor-cancel') {
        ev.preventDefault(); closeEditor();
      } else if (action === 'tm-preview-draft') {
        ev.preventDefault();
        try {
          const subj = (document.getElementById('tmSubject')?.value || 'Preview');
          const html = (document.getElementById('tmHtml')?.value || '');
          openPreview(subj, html);
        } catch(_) {}
      }
    });

    btnRefresh && btnRefresh.addEventListener('click', loadTemplates);
    document.getElementById('tmNew')?.addEventListener('click', () => openEditor(null));
    btnAssignEdit && btnAssignEdit.addEventListener('click', async () => {
      // Build editor from types + templates + current assignments
      try {
        panelAssignEditor.classList.remove('hidden');
        assignEditorBody.innerHTML = '<tr><td colspan="2" class="p-3 text-center text-gray-500">Loading…</td></tr>';
        const [typesJ, templatesJ, assignsJ] = await Promise.all([
          apiGet('/api/email_templates.php?action=get_types'),
          apiGet('/api/email_templates.php?action=get_all'),
          apiGet('/api/email_templates.php?action=get_assignments'),
        ]);
        const types = Object.entries(typesJ?.types || {});
        const templates = (templatesJ?.templates || []);
        const byType = {};
        (assignsJ?.assignments || []).forEach(a => { byType[String(a.email_type)] = a; });
        const options = templates.map(t => `<option value="${t.id}">${(t.template_name||'')}	— ${(t.template_type||'')}</option>`).join('');
        const rows = types.map(([key, meta]) => {
          const assignedId = byType[key]?.template_id || '';
          return `<tr>
            <td class="p-2"><code>${key}</code><div class="text-[11px] text-gray-500">${meta?.name||''}</div></td>
            <td class="p-2"><select class="form-select w-full tm-assign-select" data-email-type="${key}">
              <option value="">— Select Template —</option>
              ${options}
            </select></td>
          </tr>`;
        }).join('');
        assignEditorBody.innerHTML = rows || '<tr><td colspan="2" class="p-3 text-center text-gray-500">No email types</td></tr>';
        // Set selected
        assignEditorBody.querySelectorAll('.tm-assign-select').forEach(sel => {
          const type = sel.getAttribute('data-email-type');
          const assignedId = byType[type]?.template_id || '';
          if (assignedId) sel.value = String(assignedId);
        });
      } catch(_) {
        assignEditorBody.innerHTML = '<tr><td colspan="2" class="p-3 text-center text-red-600">Failed to load assignment editor</td></tr>';
      }
    });
    document.getElementById('tmAssignCancel')?.addEventListener('click', () => {
      panelAssignEditor.classList.add('hidden');
    });
    document.getElementById('tmAssignSave')?.addEventListener('click', async () => {
      try {
        sendStatus('Saving…', true);
        const selects = Array.from(assignEditorBody.querySelectorAll('.tm-assign-select'));
        for (const sel of selects) {
          const emailType = sel.getAttribute('data-email-type');
          const templateId = sel.value;
          if (!emailType || !templateId) continue;
          await apiPost('/api/email_templates.php?action=set_assignment', { email_type: emailType, template_id: templateId });
        }
        panelAssignEditor.classList.add('hidden');
        sendStatus('Saved', true);
      } catch(_) { /* show toast if needed */ }
    });

    // Initial load
    loadTemplates();
    try { sendStatus('Loaded', true); } catch(_){}

    // Parent Save bridge: attempt to save active context
    try {
      window.addEventListener('message', function(ev){
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-tm-parent') return;
          if (d.type === 'save') {
            // If editor modal visible, submit; else if assignments panel visible, save assignments
            if (editorOverlay && !editorOverlay.classList.contains('hidden')) {
              editorForm?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            } else if (panelAssignEditor && !panelAssignEditor.classList.contains('hidden')) {
              document.getElementById('tmAssignSave')?.click();
            } else {
              sendStatus('Nothing to save', false);
            }
          }
        } catch(_) {}
      });
    } catch(_) {}

    // Editor helpers
    function __tmFindParentOverlay(){
      try {
        const pd = window.parent && window.parent.document;
        if (!pd) return null;
        const ifr = Array.from(pd.querySelectorAll('iframe')).find(f => {
          try { return f.contentWindow === window; } catch(_) { return false; }
        });
        if (!ifr) return null;
        const overlay = ifr.closest('.admin-modal-overlay');
        return overlay || null;
      } catch(_) { return null; }
    }
    function __tmEnsureParentOpen(dim){
      try {
        const ov = __tmFindParentOverlay();
        if (!ov) return;
        if (ov.id && window.parent && typeof window.parent.showModal === 'function') {
          try { window.parent.showModal(ov.id); } catch(_){}
        }
        if (dim) {
          try { ov.classList.add('wf-dim-backdrop'); } catch(_){}
        }
      } catch(_){}
    }
    function __tmClearParentDimIfNone(){
      try {
        const anyOpen = (editorOverlay && !editorOverlay.classList.contains('hidden')) || (previewOverlay && !previewOverlay.classList.contains('hidden'));
        if (anyOpen) return;
        const ov = __tmFindParentOverlay();
        if (ov) { try { ov.classList.remove('wf-dim-backdrop'); } catch(_){} }
      } catch(_){}
    }
    function openEditor(t){
      try {
        if (editorOverlay.parentElement !== document.body) document.body.appendChild(editorOverlay);
        __tmEnsureParentOpen(true);
        editorOverlay.classList.add('show');
        editorOverlay.classList.remove('hidden');
        editorOverlay.setAttribute('aria-hidden','false');
      } catch(_) {}
      fId.value = t?.id || '';
      fName.value = t?.template_name || '';
      fType.value = t?.template_type || 'custom';
      fSubject.value = t?.subject || '';
      fHtml.value = t?.html_content || '';
      fText.value = t?.text_content || '';
      fDesc.value = t?.description || '';
      fActive.checked = (String(t?.is_active)==='1' || String(t?.is_active).toLowerCase()==='true');
      renderVarHelper();
    }
    function closeEditor(){
      editorOverlay.classList.add('hidden');
      editorOverlay.classList.remove('show');
      editorOverlay.setAttribute('aria-hidden','true');
      __tmClearParentDimIfNone();
    }
    editorForm?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      // Basic validation
      if (!fName.value.trim()) { notify('Name is required', 'error'); return; }
      if (!fSubject.value.trim()) { notify('Subject is required', 'error'); return; }
      if (!fHtml.value.trim()) { notify('HTML Content is required', 'error'); return; }
      const payload = {
        template_id: fId.value || undefined,
        template_name: fName.value.trim(),
        template_type: fType.value,
        subject: fSubject.value.trim(),
        html_content: fHtml.value,
        text_content: fText.value,
        description: fDesc.value.trim(),
        variables: [],
        is_active: fActive.checked ? 1 : 0,
      };
      const isUpdate = !!fId.value;
      const url = isUpdate ? '/api/email_templates.php?action=update' : '/api/email_templates.php?action=create';
      try {
        sendStatus('Saving…', true);
        const j = await apiPost(url, payload);
        if (j && j.success) {
          closeEditor();
          loadTemplates();
          sendStatus('Saved', true);
        } else {
          notify('Save failed', 'error');
          sendStatus('Save failed', false);
        }
      } catch(_) { notify('Save failed', 'error'); }
    });

    // Variable helper
    const VARS_BY_TYPE = {
      order_confirmation: ['{{customer_name}}','{{order_id}}','{{order_total}}','{{order_items}}','{{billing_address}}','{{shipping_address}}'],
      admin_notification: ['{{subject}}','{{details}}','{{created_at}}'],
      welcome: ['{{customer_name}}','{{activation_link}}'],
      password_reset: ['{{customer_name}}','{{reset_link}}','{{expires_in}}'],
      custom: ['{{customer_name}}','{{body}}']
    };
    function insertAtCursor(textarea, text){
      try {
        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const before = textarea.value.substring(0, start);
        const after = textarea.value.substring(end);
        textarea.value = before + text + after;
        const pos = start + text.length;
        textarea.selectionStart = textarea.selectionEnd = pos;
        textarea.focus();
      } catch(_){}
    }
    function renderVarHelper(){
      const wrap = document.getElementById('tmVarHelper');
      if (!wrap) return;
      const type = fType.value || 'custom';
      const vars = VARS_BY_TYPE[type] || VARS_BY_TYPE.custom;
      wrap.innerHTML = vars.map(v => `<button type=\"button\" class=\"btn btn-sm\" data-action=\"tm-insert-var\" data-var=\"${v}\" title=\"Insert variable\">${v}</button>`).join(' ');
    }
    fType?.addEventListener('change', renderVarHelper);
    document.addEventListener('click', (ev)=>{
      const a = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-insert-var"]') : null;
      if (!a) return;
      ev.preventDefault();
      const v = a.getAttribute('data-var');
      if (v) insertAtCursor(fHtml, v);
    });

    // --- Defaults Seeder ---
    async function seedDefaultTemplates(){
      const existing = await apiGet('/api/email_templates.php?action=get_all');
      const templates = (existing && existing.templates) ? existing.templates : [];

      // Helper to check by exact name
      const existsByName = (name) => templates.some(t => String(t.template_name||'').toLowerCase() === String(name).toLowerCase());

      // Business context
      const brand = 'Whimsical Frog';
      const brandUrl = 'https://whimsicalfrog.us';
      const footer = `<p>— ${brand} • <a href="${brandUrl}">${brandUrl}</a></p>`;

      const defs = [
        {
          type: 'order_confirmation',
          name: 'WF Order Confirmation (Default)',
          subject: 'Thank you for your order, {customer_name}! Order {order_id}',
          html: `
            <div>
              <h1>Thank you for your order!</h1>
              <p>Hi {customer_name}, we received your order <strong>{order_id}</strong> placed on {order_date}.</p>
              <p>Order total: <strong>{order_total}</strong></p>
              <h2>Items</h2>
              <ul>{items}</ul>
              <h2>Shipping Address</h2>
              <pre>{shipping_address}</pre>
              <p>We'll email you a tracking link once your package ships.</p>
              ${footer}
            </div>
          `,
          text: 'Thank you for your order!\nOrder {order_id} on {order_date}\nTotal: {order_total}\nItems: (see HTML)\nShip to: {shipping_address}\n\n— '+brand+' ('+brandUrl+')',
          desc: 'Default customer order confirmation for Whimsical Frog'
        },
        {
          type: 'admin_notification',
          name: 'WF Admin Notification (Default)',
          subject: 'New order {order_id} from {customer_name}',
          html: `
            <div>
              <h1>New Order</h1>
              <p><strong>Order:</strong> {order_id} on {order_date}</p>
              <p><strong>Customer:</strong> {customer_name} ({customer_email})</p>
              <p><strong>Total:</strong> {order_total}</p>
              <h2>Items</h2>
              <ul>{items}</ul>
              ${footer}
            </div>
          `,
          text: 'New order {order_id} on {order_date}\nCustomer: {customer_name} ({customer_email})\nTotal: {order_total}\nItems: (see HTML)\n\n— '+brand,
          desc: 'Default internal notification for new orders'
        },
        {
          type: 'welcome',
          name: 'WF Welcome (Default)',
          subject: 'Welcome to '+brand+', {customer_name}',
          html: `
            <div>
              <h1>Welcome to ${brand}!</h1>
              <p>We're glad you're here, {customer_name}. Click below to activate your account and start exploring new arrivals and specials.</p>
              <p><a href="{activation_url}">Activate Account</a></p>
              ${footer}
            </div>
          `,
          text: 'Welcome to '+brand+'! Activate your account: {activation_url}\n\n— '+brand+' ('+brandUrl+')',
          desc: 'Default welcome email for new customers'
        },
        {
          type: 'password_reset',
          name: 'WF Password Reset (Default)',
          subject: brand+': Reset your password',
          html: `
            <div>
              <h1>Reset your password</h1>
              <p>We received a request to reset your password. If you didn't request this, you can ignore this message.</p>
              <p><a href="{reset_url}">Reset Password</a></p>
              <p>This link may expire soon for your security.</p>
              ${footer}
            </div>
          `,
          text: 'Reset your password: {reset_url}\nIf you did not request this, ignore this email.\n\n— '+brand,
          desc: 'Default password reset email'
        },
        {
          type: 'custom',
          name: 'WF Custom (Default)',
          subject: 'A note from '+brand,
          html: `
            <div>
              <h1>Hello from ${brand}</h1>
              <p>{body}</p>
              ${footer}
            </div>
          `,
          text: 'Hello from '+brand+'\n\n{body}\n\n— '+brand,
          desc: 'Generic custom template'
        }
      ];

      const createdIds = {};
      for (const d of defs){
        if (!existsByName(d.name)){
          const jr = await apiPost('/api/email_templates.php?action=create', {
              template_name: d.name,
              template_type: d.type,
              subject: d.subject,
              html_content: d.html,
              text_content: d.text,
              description: d.desc,
              is_active: 1
          });
          if (jr && jr.success && jr.template_id) {
            createdIds[d.type] = jr.template_id;
          }
        } else {
          // Look up the existing template id by name to allow assignment
          const t = templates.find(t => String(t.template_name||'').toLowerCase() === String(d.name).toLowerCase());
          if (t && t.id) createdIds[d.type] = t.id;
        }
      }

      // Assign defaults (skip custom)
      const assignable = ['order_confirmation','admin_notification','welcome','password_reset'];
      for (const et of assignable){
        const tid = createdIds[et];
        if (!tid) continue;
        await apiPost('/api/email_templates.php?action=set_assignment', { email_type: et, template_id: tid });
      }
    }

    // Inline Preview modal helpers
    const previewOverlay = document.getElementById('tmPreviewModal');
    const previewTitle = document.getElementById('tmPreviewTitle');
    const previewBody = document.getElementById('tmPreviewBody');
    let previewLast = { title: '', html: '' };
    let previewOptions = (function(){
      try {
        const o = JSON.parse(localStorage.getItem('wfEmailPreviewOptions')||'null');
        if (o && typeof o==='object') return {
          theme: (o.theme==='dark'?'dark':'light'),
          device: (o.device==='mobile' || o.device==='tablet' || o.device==='custom') ? o.device : 'desktop',
          customWidth: (typeof o.customWidth==='number' && o.customWidth>0 ? o.customWidth : 640),
          zoom: (o.zoom===0.8 || o.zoom===1 || o.zoom===1.2) ? o.zoom : 1,
          brand: (o.brand && typeof o.brand==='object') ? o.brand : null
        };
      } catch(_){ }
      return { theme:'light', device:'desktop', customWidth:640, zoom:1, brand:null };
    })();

    function updatePreviewControls(){
      try {
        const th = document.getElementById('tmPrevTheme');
        if (th) {
          th.setAttribute('aria-pressed', previewOptions.theme === 'dark' ? 'true' : 'false');
          th.textContent = previewOptions.theme === 'dark' ? '🌙 Dark' : '☀️ Light';
        }
        const bd = document.getElementById('tmPrevDesktop');
        const bt = document.getElementById('tmPrevTablet');
        const bm = document.getElementById('tmPrevMobile');
        const bc = document.getElementById('tmPrevCustom');
        if (bd) { bd.setAttribute('aria-pressed', previewOptions.device==='desktop' ? 'true' : 'false'); bd.textContent = '🖥️ Desktop'; }
        if (bt) { bt.setAttribute('aria-pressed', previewOptions.device==='tablet' ? 'true' : 'false'); bt.textContent = '📒 Tablet'; }
        if (bm) { bm.setAttribute('aria-pressed', previewOptions.device==='mobile' ? 'true' : 'false'); bm.textContent = '📱 Mobile'; }
        if (bc) { bc.setAttribute('aria-pressed', previewOptions.device==='custom' ? 'true' : 'false'); bc.textContent = '📏 Custom'; }
        const w = document.getElementById('tmPrevWidth');
        if (w) { w.value = String(previewOptions.customWidth||640); w.disabled = previewOptions.device!=='custom'; }
        const z80 = document.getElementById('tmPrevZoom80');
        const z100 = document.getElementById('tmPrevZoom100');
        const z120 = document.getElementById('tmPrevZoom120');
        if (z80) z80.setAttribute('aria-pressed', previewOptions.zoom===0.8 ? 'true':'false');
        if (z100) z100.setAttribute('aria-pressed', previewOptions.zoom===1 ? 'true':'false');
        if (z120) z120.setAttribute('aria-pressed', previewOptions.zoom===1.2 ? 'true':'false');
      } catch(_) {}
    }
    function openPreview(title, html){
      try {
        if (previewOverlay.parentElement !== document.body) document.body.appendChild(previewOverlay);
        previewTitle.textContent = title || 'Preview';
        const content = String(html || '');
        previewLast = { title: String(title||'Preview'), html: content };
        const bodyClass = [
          'email-preview',
          (previewOptions.theme === 'dark' ? 'theme-dark' : ''),
          (previewOptions.device==='mobile' ? 'emulate-mobile' : (previewOptions.device==='tablet' ? 'emulate-tablet' : (previewOptions.device==='custom' ? 'emulate-custom' : ''))),
          (previewOptions.zoom===0.8 ? 'zoom-80' : (previewOptions.zoom===1.2 ? 'zoom-120' : 'zoom-100'))
        ].filter(Boolean).join(' ');
        // Attempt to read brand colors from parent document CSS variables
        let brandCSSRoot = ':root{--brand:#2563eb;--text:#111827;--muted:#6b7280;--bg:#f3f4f6;--panel:#ffffff;--border:#e5e7eb}';
        try {
          const rs = getComputedStyle(document.documentElement);
          const b = (rs.getPropertyValue('--brand-color')||'').trim() || (rs.getPropertyValue('--wf-brand')||'').trim();
          const t = (rs.getPropertyValue('--text-color')||'').trim();
          const m = (rs.getPropertyValue('--muted-color')||'').trim();
          const bg = (rs.getPropertyValue('--email-bg')||'').trim();
          const panel = (rs.getPropertyValue('--email-panel')||'').trim();
          const border = (rs.getPropertyValue('--email-border')||'').trim();
          const v = {
            brand: b || '#2563eb',
            text: t || '#111827',
            muted: m || '#6b7280',
            bg: bg || '#f3f4f6',
            panel: panel || '#ffffff',
            border: border || '#e5e7eb'
          };
          // Apply preview brand overrides if provided
          if (previewOptions.brand && typeof previewOptions.brand==='object') {
            if (previewOptions.brand.brand) v.brand = previewOptions.brand.brand;
            if (previewOptions.brand.text) v.text = previewOptions.brand.text;
            if (previewOptions.brand.muted) v.muted = previewOptions.brand.muted;
            if (previewOptions.brand.bg) v.bg = previewOptions.brand.bg;
            if (previewOptions.brand.panel) v.panel = previewOptions.brand.panel;
            if (previewOptions.brand.border) v.border = previewOptions.brand.border;
          }
          brandCSSRoot = `:root{--brand:${v.brand};--text:${v.text};--muted:${v.muted};--bg:${v.bg};--panel:${v.panel};--border:${v.border}}`;
        } catch(_) { }
        const doc = '<!doctype html>'+
          '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'+
          '<style>'+[
            brandCSSRoot,
            'body.theme-dark{--brand:#60a5fa;--text:#e5e7eb;--muted:#9ca3af;--bg:#0b1220;--panel:#0f172a;--border:#1f2937}',
            'html,body{height:auto;min-height:100%}',
            'body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;line-height:1.6}',
            '.email-container{max-width:640px;margin:0 auto;background:var(--panel);border:1px solid var(--border);border-radius:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);padding:24px;box-sizing:border-box}',
            'body.emulate-mobile .email-container{max-width:420px}',
            'body.emulate-tablet .email-container{max-width:768px}',
            `body.emulate-custom .email-container{max-width:${Number(previewOptions.customWidth||640)}px}`,
            'body.zoom-80 .email-container{transform:scale(0.8);transform-origin:top center}',
            'body.zoom-100 .email-container{transform:scale(1);transform-origin:top center}',
            'body.zoom-120 .email-container{transform:scale(1.2);transform-origin:top center}',
            'h1{margin:0 0 12px;font-size:24px;line-height:1.25}',
            'h2{margin:20px 0 10px;font-size:18px;line-height:1.4}',
            'h3{margin:16px 0 8px;font-size:16px;line-height:1.4}',
            'p{margin:0 0 12px}',
            'small, .muted{color:var(--muted)}',
            'ul,ol{margin:0 0 12px;padding-left:20px}',
            'li{margin:4px 0}',
            'a{color:var(--brand);text-decoration:none}',
            'a:hover{text-decoration:underline}',
            'hr{border:0;border-top:1px solid var(--border);margin:16px 0}',
            'pre{white-space:pre-line;margin:0 0 12px;padding:0}',
            'code, kbd{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;background:#f9fafb;border:1px solid var(--border);border-radius:4px;padding:2px 4px}',
            'table{width:100%;border-collapse:collapse;margin:12px 0;border:1px solid var(--border)}',
            'th,td{padding:8px 10px;border-top:1px solid var(--border);text-align:left;vertical-align:top}',
            'thead th{background:#f9fafb;border-bottom:1px solid var(--border)}',
            'img{max-width:100%;height:auto;border:0}',
            'blockquote{margin:12px 0;padding:10px 12px;border-left:4px solid var(--brand);background:#f9fafb;border-radius:6px;color:#111827}',
            '.badge{display:inline-block;padding:2px 6px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px}',
            '.btn{display:inline-block;padding:10px 14px;border-radius:6px;background:#111827;color:#fff;text-decoration:none}',
            '.btn:hover{opacity:.9}',
            '.email-footer, footer{margin-top:24px;color:var(--muted);font-size:12px}',
            '@media print{',
              'body{background:#ffffff !important;color:#000000 !important;padding:0}',
              '.email-container{box-shadow:none;border:0;max-width:none;border-radius:0}',
              'a{color:#000;text-decoration:underline}',
              'a[href]::after{content:" (" attr(href) ")";font-size:12px;color:#6b7280}',
              'img{max-width:100%;height:auto}',
              'p,li{word-break:break-word}',
            '}'
          ].join('')+'</style></head><body class="'+ bodyClass +'"><div class="email-container">'+ content +'</div></body></html>';
        previewBody.innerHTML = '<iframe class="w-full h-full border" srcdoc="'+ doc.replaceAll('"','&quot;') +'"></iframe>';
        __tmEnsureParentOpen(true);
        previewOverlay.classList.add('show');
        previewOverlay.classList.remove('hidden');
        previewOverlay.setAttribute('aria-hidden','false');
        updatePreviewControls();
      } catch(_) {}
    }
    function closePreview(){
      previewOverlay.classList.add('hidden');
      previewOverlay.classList.remove('show');
      previewOverlay.setAttribute('aria-hidden','true');
      previewBody.innerHTML = '';
      __tmClearParentDimIfNone();
    }
    document.addEventListener('click', (ev)=>{
      const a = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-close"]') : null;
      if (!a) return;
      ev.preventDefault();
      closePreview();
    });

    document.addEventListener('click', (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-toggle-theme"]') : null;
      if (!t) return;
      ev.preventDefault();
      previewOptions.theme = (previewOptions.theme === 'dark') ? 'light' : 'dark';
      try { localStorage.setItem('wfEmailPreviewOptions', JSON.stringify(previewOptions)); } catch(_) {}
      updatePreviewControls();
      openPreview(previewLast.title, previewLast.html);
    });
    document.addEventListener('click', (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-set-device"]') : null;
      if (!t) return;
      ev.preventDefault();
      const d = t.getAttribute('data-device');
      if (!d) return;
      previewOptions.device = (d==='mobile' || d==='tablet' || d==='custom') ? d : 'desktop';
      try { localStorage.setItem('wfEmailPreviewOptions', JSON.stringify(previewOptions)); } catch(_) {}
      updatePreviewControls();
      openPreview(previewLast.title, previewLast.html);
    });

    document.addEventListener('input', (ev)=>{
      const inp = ev.target && ev.target.closest ? ev.target.closest('#tmPrevWidth') : null;
      if (!inp) return;
      try {
        const n = Math.max(280, Math.min(2000, parseInt(inp.value||'640',10)));
        if (!isNaN(n)) {
          previewOptions.customWidth = n;
          try { localStorage.setItem('wfEmailPreviewOptions', JSON.stringify(previewOptions)); } catch(_) {}
          updatePreviewControls();
          openPreview(previewLast.title, previewLast.html);
        }
      } catch(_) {}
    });

    document.addEventListener('click', (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-open"]') : null;
      if (!t) return;
      ev.preventDefault();
      try {
        const win = window.open('', '_blank');
        if (win) {
          const content = String(previewLast.html||'');
          // Rebuild doc with current options
          const iframeDoc = (function(){
            const bodyClass = 'email-preview' + (previewOptions.theme === 'dark' ? ' theme-dark' : '') + (previewOptions.device==='mobile' ? ' emulate-mobile' : (previewOptions.device==='tablet' ? ' emulate-tablet' : ''));
            let brandCSSRoot = ':root{--brand:#2563eb;--text:#111827;--muted:#6b7280;--bg:#f3f4f6;--panel:#ffffff;--border:#e5e7eb}';
            try {
              const rs = getComputedStyle(document.documentElement);
              const b = (rs.getPropertyValue('--brand-color')||'').trim() || (rs.getPropertyValue('--wf-brand')||'').trim();
              const t = (rs.getPropertyValue('--text-color')||'').trim();
              const m = (rs.getPropertyValue('--muted-color')||'').trim();
              const bg = (rs.getPropertyValue('--email-bg')||'').trim();
              const panel = (rs.getPropertyValue('--email-panel')||'').trim();
              const border = (rs.getPropertyValue('--email-border')||'').trim();
              const v = { brand: b||'#2563eb', text: t||'#111827', muted: m||'#6b7280', bg: bg||'#f3f4f6', panel: panel||'#ffffff', border: border||'#e5e7eb' };
              brandCSSRoot = `:root{--brand:${v.brand};--text:${v.text};--muted:${v.muted};--bg:${v.bg};--panel:${v.panel};--border:${v.border}}`;
            } catch(_){ }
            const css = [
              brandCSSRoot,
              'body.theme-dark{--brand:#60a5fa;--text:#e5e7eb;--muted:#9ca3af;--bg:#0b1220;--panel:#0f172a;--border:#1f2937}',
              'html,body{height:auto;min-height:100%}',
              'body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,\\'Segoe UI\\',Roboto,Arial,sans-serif;line-height:1.6}',
              '.email-container{max-width:640px;margin:0 auto;background:var(--panel);border:1px solid var(--border);border-radius:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);padding:24px;box-sizing:border-box}',
              'body.emulate-mobile .email-container{max-width:420px}','body.emulate-tablet .email-container{max-width:768px}', `body.emulate-custom .email-container{max-width:${Number(previewOptions.customWidth||640)}px}`,
              'body.zoom-80 .email-container{transform:scale(0.8);transform-origin:top center}','body.zoom-100 .email-container{transform:scale(1);transform-origin:top center}','body.zoom-120 .email-container{transform:scale(1.2);transform-origin:top center}',
              'h1{margin:0 0 12px;font-size:24px;line-height:1.25}','h2{margin:20px 0 10px;font-size:18px;line-height:1.4}','h3{margin:16px 0 8px;font-size:16px;line-height:1.4}',
              'p{margin:0 0 12px}','small, .muted{color:var(--muted)}','ul,ol{margin:0 0 12px;padding-left:20px}','li{margin:4px 0}',
              'a{color:var(--brand);text-decoration:none}','a:hover{text-decoration:underline}','hr{border:0;border-top:1px solid var(--border);margin:16px 0}',
              'pre{white-space:pre-line;margin:0 0 12px;padding:0}','code, kbd{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\\'Liberation Mono\\',\\'Courier New\\',monospace;background:#f9fafb;border:1px solid var(--border);border-radius:4px;padding:2px 4px}',
              'table{width:100%;border-collapse:collapse;margin:12px 0;border:1px solid var(--border)}','th,td{padding:8px 10px;border-top:1px solid var(--border);text-align:left;vertical-align:top}','thead th{background:#f9fafb;border-bottom:1px solid var(--border)}',
              'img{max-width:100%;height:auto;border:0}','blockquote{margin:12px 0;padding:10px 12px;border-left:4px solid var(--brand);background:#f9fafb;border-radius:6px;color:#111827}',
              '.badge{display:inline-block;padding:2px 6px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px}','.btn{display:inline-block;padding:10px 14px;border-radius:6px;background:#111827;color:#fff;text-decoration:none}','.btn:hover{opacity:.9}',
              '.email-footer, footer{margin-top:24px;color:var(--muted);font-size:12px}',
              '@media print{body{background:#ffffff !important;color:#000000 !important;padding:0}.email-container{box-shadow:none;border:0;max-width:none;border-radius:0}a{color:#000;text-decoration:underline}a[href]::after{content:" (" attr(href) ")";font-size:12px;color:#6b7280}img{max-width:100%;height:auto}p,li{word-break:break-word}}'
            ].join('');
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'+css+'</style></head><body class="'+ bodyClass +'"><div class="email-container">'+ content +'</div></body></html>';
          })();
          win.document.open();
          win.document.write(iframeDoc);
          win.document.close();
        }
      } catch(_) {}
    });

    document.addEventListener('click', (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-print"]') : null;
      if (!t) return;
      ev.preventDefault();
      try {
        const iframe = document.querySelector('#tmPreviewBody iframe');
        if (iframe && iframe.contentWindow) { iframe.contentWindow.focus(); iframe.contentWindow.print(); }
      } catch(_) {}
    });

    document.addEventListener('click', (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-zoom"]') : null;
      if (!t) return;
      ev.preventDefault();
      const z = t.getAttribute('data-zoom');
      const map = { '0.8':0.8, '1':1, '1.2':1.2 };
      if (!(z in map)) return;
      previewOptions.zoom = map[z];
      try { localStorage.setItem('wfEmailPreviewOptions', JSON.stringify(previewOptions)); } catch(_) {}
      updatePreviewControls();
      openPreview(previewLast.title, previewLast.html);
    });

    document.addEventListener('click', async (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-brand"]') : null;
      if (!t) return;
      ev.preventDefault();
      try {
        const promptFn = (window.parent && typeof window.parent.showPromptModal==='function') ? window.parent.showPromptModal : (typeof window.showPromptModal==='function' ? window.showPromptModal : null);
        const current = previewOptions.brand ? JSON.stringify(previewOptions.brand, null, 2) : '{\n  "brand": "#2563eb",\n  "text": "#111827",\n  "muted": "#6b7280",\n  "bg": "#f3f4f6",\n  "panel": "#ffffff",\n  "border": "#e5e7eb"\n}';
        let value = null;
        if (promptFn) {
          value = await promptFn({ title:'Brand Overrides (JSON)', message:'Set preview-only brand variables (JSON object). Keys: brand, text, muted, bg, panel, border', inputType:'textarea', placeholder: current, initialValue: current, confirmText:'Apply' });
        } else {
          value = window.prompt('Brand overrides (JSON):', current);
        }
        if (!value) return;
        try {
          const obj = JSON.parse(value);
          if (obj && typeof obj==='object') {
            previewOptions.brand = obj;
            try { localStorage.setItem('wfEmailPreviewOptions', JSON.stringify(previewOptions)); } catch(_) {}
            openPreview(previewLast.title, previewLast.html);
          }
        } catch(_) {}
      } catch(_) {}
    });

    document.addEventListener('click', async (ev)=>{
      const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-copy-html"]') : null;
      if (!t) return;
      ev.preventDefault();
      try {
        const txt = String(previewLast.html||'');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(txt);
        } else {
          const ta = document.createElement('textarea');
          ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        }
      } catch(_) {}
    });
  })();
  </script>
</div>

<!-- Inline Preview Modal -->
<div id="tmPreviewModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="tmPreviewTitle">
  <div class="admin-modal admin-modal-content admin-modal--lg">
    <div class="modal-header">
      <h2 id="tmPreviewTitle" class="admin-card-title">Preview</h2>
      <div class="admin-form-inline ml-auto flex items-center gap-2">
        <div class="flex items-center gap-1">
          <button type="button" id="tmPrevTheme" class="admin-action-button btn btn-xs btn-icon btn-icon--theme" data-action="tm-preview-toggle-theme" aria-pressed="false" title="Toggle theme" aria-label="Toggle theme"></button>
        </div>
        <div class="flex items-center gap-1" role="group" aria-label="Device presets">
          <button type="button" id="tmPrevDesktop" class="admin-action-button btn btn-xs btn-icon btn-icon--desktop" data-action="tm-preview-set-device" data-device="desktop" aria-pressed="false" title="Desktop width" aria-label="Desktop width"></button>
          <button type="button" id="tmPrevTablet" class="admin-action-button btn btn-xs btn-icon btn-icon--tablet" data-action="tm-preview-set-device" data-device="tablet" aria-pressed="false" title="Tablet width" aria-label="Tablet width"></button>
          <button type="button" id="tmPrevMobile" class="admin-action-button btn btn-xs btn-icon btn-icon--mobile" data-action="tm-preview-set-device" data-device="mobile" aria-pressed="false" title="Mobile width" aria-label="Mobile width"></button>
          <button type="button" id="tmPrevCustom" class="admin-action-button btn btn-xs btn-icon btn-icon--ruler" data-action="tm-preview-set-device" data-device="custom" aria-pressed="false" title="Custom width" aria-label="Custom width"></button>
          <label for="tmPrevWidth" class="sr-only">Custom width (px)</label>
          <input id="tmPrevWidth" type="number" min="280" max="2000" step="10" class="form-input w-24" value="640" aria-label="Custom width in pixels" />
        </div>
        <div class="flex items-center gap-1" role="group" aria-label="Zoom">
          <button type="button" id="tmPrevZoom80" class="btn btn-secondary btn-xs" data-action="tm-preview-zoom" data-zoom="0.8" aria-pressed="false" title="Zoom 80%" aria-label="Zoom 80%">80%</button>
          <button type="button" id="tmPrevZoom100" class="btn btn-secondary btn-xs" data-action="tm-preview-zoom" data-zoom="1" aria-pressed="true" title="Zoom 100%" aria-label="Zoom 100%">100%</button>
          <button type="button" id="tmPrevZoom120" class="btn btn-secondary btn-xs" data-action="tm-preview-zoom" data-zoom="1.2" aria-pressed="false" title="Zoom 120%" aria-label="Zoom 120%">120%</button>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="admin-action-button btn btn-xs btn-icon btn-icon--brand" data-action="tm-preview-brand" title="Brand overrides" aria-label="Brand overrides"></button>
          <button type="button" class="admin-action-button btn btn-xs btn-icon btn-icon--copy" data-action="tm-preview-copy-html" title="Copy HTML" aria-label="Copy HTML"></button>
          <button type="button" class="admin-action-button btn btn-xs btn-icon btn-icon--external" data-action="tm-preview-open" title="Open in new window" aria-label="Open in new window"></button>
          <button type="button" class="admin-action-button btn btn-xs btn-icon btn-icon--print" data-action="tm-preview-print" title="Print" aria-label="Print"></button>
        </div>
      </div>
      <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="tm-preview-close" aria-label="Close">×</button>
    </div>
    <div class="modal-body p-0 site-modal-body--xl">
      <div id="tmPreviewBody" class="w-full"></div>
    </div>
  </div>
</div>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>
