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
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div id="templateManagerRoot" class="p-3">
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
    <div class="admin-card text-sm">
      <div class="font-semibold mb-1">How this works</div>
      <ol class="list-decimal list-inside space-y-1">
        <li><b>Create or edit templates</b> with subject + HTML content.</li>
        <li><b>Assign templates</b> to email types (e.g., order_confirmation).</li>
        <li><b>Preview or send a test</b> to verify formatting.</li>
      </ol>
      <div class="mt-2 text-xs text-gray-600">Tip: “Assignments” control which template is used when the system sends each kind of email.</div>
    </div>
    <div class="admin-card text-sm text-gray-600">Manage templates below, then use "Edit Assignments" to map them to email types.</div>
    <div id="tmEmailToolbar" class="admin-card admin-form-inline">
      <button id="tmRefresh" class="btn btn-secondary btn-sm">Refresh</button>
      <button id="tmNew" class="btn btn-primary btn-sm">New Template</button>
      <button id="tmAssignEdit" class="btn btn-secondary btn-sm" title="Map templates to email types">Edit Assignments</button>
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
    // No tabs; Email Templates is the sole panel

    // API helpers
    const api = (u, opts={}) => fetch(u, { credentials: 'include', ...opts }).then(r => r.json()).catch(()=>null);
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
        api('/api/email_templates.php?action=get_all'),
        api('/api/email_templates.php?action=get_assignments')
      ]);
      if (!tplJ || !tplJ.success) { body.innerHTML = '<tr><td colspan="7" class="p-3 text-center text-red-600">Failed to load templates</td></tr>'; return; }
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
      const j = await api('/api/email_templates.php?action=get_assignments');
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
      if (action === 'tm-preview') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await api(`/api/email_templates.php?action=preview&template_id=${encodeURIComponent(id)}`);
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
          const j = await api(`/api/email_templates.php?action=preview&template_id=${encodeURIComponent(id)}`);
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
          const r = await fetch('/api/email_templates.php?action=send_test', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: id, test_email: email })
          });
          const j = await r.json();
          if (j && j.success) alert(j.message || 'Test sent'); else alert('Failed to send test');
        } catch(_) { alert('Failed to send test'); }
      } else if (action === 'tm-edit') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await api(`/api/email_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
          if (j && j.success && j.template) {
            openEditor(j.template);
          }
        } catch(_) {}
      } else if (action === 'tm-duplicate') {
        ev.preventDefault();
        const id = btn.getAttribute('data-id');
        try {
          const j = await api(`/api/email_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
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
        if (!confirm('Archive this template? This will set it to inactive.')) return;
        try {
          // Minimal update: set is_active=0
          const r = await fetch('/api/email_templates.php?action=update', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: id, is_active: 0 })
          });
          const j = await r.json();
          if (j && j.success) loadTemplates(); else alert('Archive failed');
        } catch(_) { alert('Archive failed'); }
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
          api('/api/email_templates.php?action=get_types'),
          api('/api/email_templates.php?action=get_all'),
          api('/api/email_templates.php?action=get_assignments'),
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
        const selects = Array.from(assignEditorBody.querySelectorAll('.tm-assign-select'));
        for (const sel of selects) {
          const emailType = sel.getAttribute('data-email-type');
          const templateId = sel.value;
          if (!emailType || !templateId) continue;
          await fetch('/api/email_templates.php?action=set_assignment', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email_type: emailType, template_id: templateId })
          });
        }
        panelAssignEditor.classList.add('hidden');
      } catch(_) { /* show toast if needed */ }
    });

    // Initial load
    loadTemplates();

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
      if (!fName.value.trim()) { alert('Name is required'); return; }
      if (!fSubject.value.trim()) { alert('Subject is required'); return; }
      if (!fHtml.value.trim()) { alert('HTML Content is required'); return; }
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
        const r = await fetch(url, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const j = await r.json();
        if (j && j.success) {
          closeEditor();
          loadTemplates();
        } else {
          alert('Save failed');
        }
      } catch(_) { alert('Save failed'); }
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
