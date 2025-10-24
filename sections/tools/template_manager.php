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
          <button id="tmRefresh" class="btn btn-secondary btn-sm">Refresh</button>
          <button id="tmNew" class="btn btn-primary btn-sm">New Template</button>
          <button id="tmAssignEdit" class="btn btn-secondary btn-sm" title="Map templates to email types">Edit Assignments</button>
          <button id="tmSeedDefaults" class="btn btn-secondary btn-sm" data-action="tm-seed-defaults" title="Create default templates for all categories">Create Defaults</button>
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
        <button type="button" class="admin-modal-close" data-action="tm-editor-close" aria-label="Close">×</button>
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
            <button class="btn btn-secondary btn-sm" data-action="tm-preview" data-id="${id}" title="Open a new window with rendered HTML">Preview HTML</button>
            <button class="btn btn-secondary btn-sm" data-action="tm-preview-inline" data-id="${id}" title="Preview inside a modal">Preview Inline</button>
            <button class="btn btn-secondary btn-sm" data-action="tm-send-test" data-id="${id}" title="Send a test email using this template">Send Test…</button>
            <button class="btn btn-secondary btn-sm" data-action="tm-edit" data-id="${id}" title="Edit template fields">Edit</button>
            <button class="btn btn-secondary btn-sm" data-action="tm-duplicate" data-id="${id}" title="Create a copy of this template">Duplicate</button>
            <button class="btn btn-secondary btn-sm" data-action="tm-archive" data-id="${id}" title="Archive (set inactive)">Archive</button>
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
        const email = window.prompt('Send test to email address:');
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
    function openEditor(t){
      try {
        if (editorOverlay.parentElement !== document.body) document.body.appendChild(editorOverlay);
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
      const footer = `<p style="margin-top:24px;color:#6b7280;font-size:12px">— ${brand} • <a href="${brandUrl}" style="color:#2563eb">${brandUrl}</a></p>`;

      const defs = [
        {
          type: 'order_confirmation',
          name: 'WF Order Confirmation (Default)',
          subject: 'Thank you for your order, {customer_name}! Order {order_id}',
          html: `
            <div style="font-family:var(--brand-font-primary, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);padding:20px">
              <h1 style="margin:0 0 8px;font-size:20px">Thank you for your order!</h1>
              <p style="margin:0 0 12px">Hi {customer_name}, we received your order <strong>{order_id}</strong> placed on {order_date}.</p>
              <p style="margin:0 0 12px">Order total: <strong>{order_total}</strong></p>
              <h2 style="margin:16px 0 8px;font-size:16px">Items</h2>
              <ul style="margin:0 0 12px;padding-left:16px">{items}</ul>
              <h2 style="margin:16px 0 8px;font-size:16px">Shipping Address</h2>
              <p style="white-space:pre-line;margin:0 0 12px">{shipping_address}</p>
              <p style="margin:12px 0">We'll email you a tracking link once your package ships.</p>
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
            <div style="font-family:var(--brand-font-primary, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);padding:20px">
              <h1 style="margin:0 0 8px;font-size:20px">New Order</h1>
              <p style="margin:0 0 8px"><strong>Order:</strong> {order_id} on {order_date}</p>
              <p style="margin:0 0 8px"><strong>Customer:</strong> {customer_name} ({customer_email})</p>
              <p style="margin:0 0 8px"><strong>Total:</strong> {order_total}</p>
              <h2 style="margin:16px 0 8px;font-size:16px">Items</h2>
              <ul style="margin:0 0 12px;padding-left:16px">{items}</ul>
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
            <div style="font-family:var(--brand-font-primary, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);padding:20px">
              <h1 style="margin:0 0 8px;font-size:20px">Welcome to ${brand}!</h1>
              <p style="margin:0 0 12px">We're glad you're here, {customer_name}. Click below to activate your account and start exploring new arrivals and specials.</p>
              <p style="margin:16px 0"><a href="{activation_url}" style="background:#111827;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none">Activate Account</a></p>
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
            <div style="font-family:var(--brand-font-primary, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);padding:20px">
              <h1 style="margin:0 0 8px;font-size:20px">Reset your password</h1>
              <p style="margin:0 0 12px">We received a request to reset your password. If you didn't request this, you can ignore this message.</p>
              <p style="margin:16px 0"><a href="{reset_url}" style="background:#111827;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none">Reset Password</a></p>
              <p style="margin:0 0 12px;color:#6b7280;font-size:12px">This link may expire soon for your security.</p>
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
            <div style="font-family:var(--brand-font-primary, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);padding:20px">
              <h1 style="margin:0 0 8px;font-size:20px">Hello from ${brand}</h1>
              <p style="margin:0 0 12px">{body}</p>
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
    function openPreview(title, html){
      try {
        if (previewOverlay.parentElement !== document.body) document.body.appendChild(previewOverlay);
        previewTitle.textContent = title || 'Preview';
        previewBody.innerHTML = '<iframe class="w-full h-full border" srcdoc="'+ (html||'').replaceAll('"','&quot;') +'"></iframe>';
        previewOverlay.classList.add('show');
        previewOverlay.classList.remove('hidden');
        previewOverlay.setAttribute('aria-hidden','false');
      } catch(_) {}
    }
    function closePreview(){
      previewOverlay.classList.add('hidden');
      previewOverlay.classList.remove('show');
      previewOverlay.setAttribute('aria-hidden','true');
      previewBody.innerHTML = '';
    }
    document.addEventListener('click', (ev)=>{
      const a = ev.target && ev.target.closest ? ev.target.closest('[data-action="tm-preview-close"]') : null;
      if (!a) return;
      ev.preventDefault();
      closePreview();
    });
  })();
  </script>
</div>

<!-- Inline Preview Modal -->
<div id="tmPreviewModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="tmPreviewTitle">
  <div class="admin-modal admin-modal-content admin-modal--lg">
    <div class="modal-header">
      <h2 id="tmPreviewTitle" class="admin-card-title">Preview</h2>
      <button type="button" class="admin-modal-close" data-action="tm-preview-close" aria-label="Close">×</button>
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
