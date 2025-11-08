import { ApiClient } from '../core/api-client.js';
import { attachSameOriginFallback, markOverlayResponsive } from '../modules/embed-autosize-parent.js';

// ---- Centralized toast helpers (safe if WFToast is missing) ----
function _wfToast(data, fallbackMsg = 'Updated successfully') {
  try { if (window?.WFToast?.toastFromData) return window.WFToast.toastFromData(data, fallbackMsg); } catch (_) {}
}
function _wfToastErr(message = 'Request failed') {
  try { if (window?.WFToast?.toastError) return window.WFToast.toastError(message); } catch (_) {}
}
function __unusedDelegatedPreamble(e, closest, target) {
        // Deprecated: legacy delegated preamble is no longer used
        // note: deprecated; kept for backward compatibility (no early return to satisfy ESLint no-unreachable)
        // File Manager toolbar actions
        const fmUp = closest('[data-action="fm-up"]');
        if (fmUp) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.navigateUp === 'function') window.navigateUp();
                else if (typeof navigateUp === 'function') navigateUp();
            } catch (_) {}
            return;
        }

        // Maintenance: Retry compact/repair after failure
        const retryBtn = closest('[data-action="retry-compact-repair"]');
        if (retryBtn) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined') {
                    if (typeof window.closeBackupProgressModal === 'function') {
                        window.closeBackupProgressModal();
                    }
                    if (typeof window.compactRepairDatabase === 'function') {
                        window.compactRepairDatabase();
                    } else {
                        document.dispatchEvent(new CustomEvent('wf:retry-compact-repair'));
                    }
                }
            } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Global Colors/Sizes/Genders edit/delete
        // -----------------------------
        const editColorBtn = closest('[data-action="edit-global-color"]');
        if (editColorBtn) {
            e.preventDefault();
            const id = parseInt(editColorBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalColor === 'function') window.editGlobalColor(id); } catch (_) {}
            return;
        }

        const deleteColorBtn = closest('[data-action="delete-global-color"]');
        if (deleteColorBtn) {
            e.preventDefault();
            const id = parseInt(deleteColorBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalColor === 'function') window.deleteGlobalColor(id); } catch (_) {}
            return;
        }

        const editSizeBtn = closest('[data-action="edit-global-size"]');
        if (editSizeBtn) {
            e.preventDefault();
            const id = parseInt(editSizeBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalSize === 'function') window.editGlobalSize(id); } catch (_) {}
            return;
        }

        const deleteSizeBtn = closest('[data-action="delete-global-size"]');
        if (deleteSizeBtn) {
            e.preventDefault();
            const id = parseInt(deleteSizeBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalSize === 'function') window.deleteGlobalSize(id); } catch (_) {}
            return;
        }

        const editGenderBtn = closest('[data-action="edit-global-gender"]');
        if (editGenderBtn) {
            e.preventDefault();
            const id = parseInt(editGenderBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalGender === 'function') window.editGlobalGender(id); } catch (_) {}
            return;
        }

        const deleteGenderBtn = closest('[data-action="delete-global-gender"]');
        if (deleteGenderBtn) {
            e.preventDefault();
            const id = parseInt(deleteGenderBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalGender === 'function') window.deleteGlobalGender(id); } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Cleanup/Optimize/Start Over actions
        // -----------------------------
        if (closest('[data-action="cleanup-stale-files"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.cleanupStaleFiles === 'function') window.cleanupStaleFiles(); } catch (_) {}
            return;
        }

        if (closest('[data-action="remove-unused-code"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.removeUnusedCode === 'function') window.removeUnusedCode(); } catch (_) {}
            return;
        }

        if (closest('[data-action="optimize-database"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.optimizeDatabase === 'function') window.optimizeDatabase(); } catch (_) {}
            return;
        }

        if (closest('[data-action="show-start-over-confirmation"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.showStartOverConfirmation === 'function') window.showStartOverConfirmation(); } catch (_) {}
            return;
        }

        if (closest('[data-action="execute-start-over"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.executeStartOver === 'function') window.executeStartOver(); } catch (_) {}
            return;
        }

        if (closest('[data-action="run-system-analysis"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.runSystemAnalysis === 'function') window.runSystemAnalysis(); } catch (_) {}
            return;
        }

        if (closest('[data-action="close-optimization-results"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeOptimizationResults === 'function') window.closeOptimizationResults(); } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Retry actions
        // -----------------------------
        if (closest('[data-action="retry-load-dashboard-config"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.loadDashboardConfiguration === 'function') window.loadDashboardConfiguration(); } catch (_) {}
            return;
        }

        const retryAvail = closest('[data-action="retry-load-available-sections"]');
        if (retryAvail) {
            e.preventDefault();
            const targetId = retryAvail.dataset.targetId;
            const el = document.getElementById(targetId);
            try { if (typeof window !== 'undefined' && typeof window.loadAvailableSections === 'function') window.loadAvailableSections(el); } catch (_) {}
            return;
        }

        const fmRefresh = closest('[data-action="fm-refresh"]');
        if (fmRefresh) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.refreshDirectory === 'function') window.refreshDirectory();
                else if (typeof refreshDirectory === 'function') refreshDirectory();
            } catch (_) {}
            return;
        }

        const fmNewFolder = closest('[data-action="fm-new-folder"]');
        if (fmNewFolder) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showCreateFolderDialog === 'function') window.showCreateFolderDialog();
                else if (typeof showCreateFolderDialog === 'function') showCreateFolderDialog();
            } catch (_) {}
            return;
        }

        const fmNewFile = closest('[data-action="fm-new-file"]');
        if (fmNewFile) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showCreateFileDialog === 'function') window.showCreateFileDialog();
                else if (typeof showCreateFileDialog === 'function') showCreateFileDialog();
            } catch (_) {}
            return;
        }

        // File Manager: specific item actions (check these BEFORE fm-item row handler)
        const fmViewBtn = closest('[data-action="fm-view-file"]');
        if (fmViewBtn) {
            e.preventDefault();
            e.stopPropagation();
            const path = fmViewBtn.dataset.path;
            try {
                if (typeof window !== 'undefined' && typeof window.viewFile === 'function') window.viewFile(path);
                else if (typeof viewFile === 'function') viewFile(path);
            } catch (_) {}
            return;
        }

        const fmEditBtn = closest('[data-action="fm-edit-file"]');
        if (fmEditBtn) {
            e.preventDefault();
            e.stopPropagation();
            const path = fmEditBtn.dataset.path;
            try {
                if (typeof window !== 'undefined' && typeof window.editFile === 'function') window.editFile(path);
                else if (typeof editFile === 'function') editFile(path);
            } catch (_) {}
            return;
        }

        const fmDeleteBtn = closest('[data-action="fm-delete-item"]');
        if (fmDeleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const path = fmDeleteBtn.dataset.path;
            const type = fmDeleteBtn.dataset.type;
            try {
                if (typeof window !== 'undefined' && typeof window.deleteItem === 'function') window.deleteItem(path, type);
                else if (typeof deleteItem === 'function') deleteItem(path, type);
            } catch (_) {}
            return;
        }

        // File Manager: row click (open dir or select file)
        const fmItem = closest('[data-action="fm-item"]');
        if (fmItem) {
            e.preventDefault();
            const type = (fmItem.dataset.type || '').toLowerCase();
            const path = fmItem.dataset.path;
            try {
                if (type === 'directory') {
                    if (typeof window !== 'undefined' && typeof window.loadDirectory === 'function') window.loadDirectory(path);
                    else if (typeof loadDirectory === 'function') loadDirectory(path);
                } else {
                    if (typeof window !== 'undefined' && typeof window.selectFile === 'function') window.selectFile(path);
                    else if (typeof selectFile === 'function') selectFile(path);
                }
            } catch (_) {}
            return;
        }

        // File Editor actions
        const fmSave = closest('[data-action="fm-save-file"]');
        if (fmSave) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.saveFile === 'function') window.saveFile();
                else if (typeof saveFile === 'function') saveFile();
            } catch (_) {}
            return;
        }

        const fmClose = closest('[data-action="fm-close-editor"]');
        if (fmClose) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEditor === 'function') window.closeEditor();
                else if (typeof closeEditor === 'function') closeEditor();
            } catch (_) {}
            return;
        }

        // Quick Access: Open System Config from Documentation Hub
        const quickOpenSysCfg = closest('[data-action="quick-open-system-config"]');
        if (quickOpenSysCfg) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openSystemConfigModal === 'function') {
                    window.openSystemConfigModal();
                } else if (typeof openSystemConfigModal === 'function') {
                    openSystemConfigModal();
                }
            } catch (_) {}
            try {
                if (typeof window !== 'undefined' && typeof window.closeDocumentationHubModal === 'function') {
                    window.closeDocumentationHubModal();
                } else if (typeof closeDocumentationHubModal === 'function') {
                    closeDocumentationHubModal();
                }
            } catch (_) {}
            return;
        }

        // Documentation Hub: open System Documentation
        const openSysDoc = closest('[data-action="open-system-documentation"]');
        if (openSysDoc) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openSystemDocumentationModal === 'function') window.openSystemDocumentationModal();
                else if (typeof openSystemDocumentationModal === 'function') openSystemDocumentationModal();
            } catch (_) {}
            try {
                if (typeof window !== 'undefined' && typeof window.closeDocumentationHubModal === 'function') window.closeDocumentationHubModal();
                else if (typeof closeDocumentationHubModal === 'function') closeDocumentationHubModal();
            } catch (_) {}
            return;
        }

        // Documentation Hub: open Help Documentation
        const openHelpDoc = closest('[data-action="open-help-documentation"]');
        if (openHelpDoc) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openHelpDocumentationModal === 'function') window.openHelpDocumentationModal();
                else if (typeof openHelpDocumentationModal === 'function') openHelpDocumentationModal();
            } catch (_) {}
            try {
                if (typeof window !== 'undefined' && typeof window.closeDocumentationHubModal === 'function') window.closeDocumentationHubModal();
                else if (typeof closeDocumentationHubModal === 'function') closeDocumentationHubModal();
            } catch (_) {}
            return;
        }

        // Documentation Hub: open Database Tables
        const openDbTables = closest('[data-action="open-database-tables"]');
        if (openDbTables) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openDatabaseTablesModal === 'function') window.openDatabaseTablesModal();
                else if (typeof openDatabaseTablesModal === 'function') openDatabaseTablesModal();
            } catch (_) {}
            try {
                if (typeof window !== 'undefined' && typeof window.closeDocumentationHubModal === 'function') window.closeDocumentationHubModal();
                else if (typeof closeDocumentationHubModal === 'function') closeDocumentationHubModal();
            } catch (_) {}
            return;
        }

        // System Documentation: export
        const exportDoc = closest('[data-action="export-documentation"]');
        if (exportDoc) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.exportDocumentation === 'function') window.exportDocumentation(); else if (typeof exportDocumentation === 'function') exportDocumentation(); } catch (_) {}
            return;
        }

        // System Documentation: refresh
        const refreshDoc = closest('[data-action="refresh-documentation"]');
        if (refreshDoc) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.refreshDocumentation === 'function') window.refreshDocumentation(); else if (typeof refreshDocumentation === 'function') refreshDocumentation(); } catch (_) {}
            return;
        }

        // Cleanup Confirmation: background click should fully close (cleanup state)
        if (target && target.id === 'cleanupConfirmationModal' && target.dataset && target.dataset.action === 'overlay-close') {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeCleanupConfirmation === 'function') window.closeCleanupConfirmation(); else if (typeof closeCleanupConfirmation === 'function') closeCleanupConfirmation(); } catch (_) {}
            return;
        }

        // Start Over Confirmation: background click should fully close
        if (target && target.id === 'startOverConfirmationModal' && target.dataset && target.dataset.action === 'overlay-close') {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeStartOverConfirmation === 'function') window.closeStartOverConfirmation(); else if (typeof closeStartOverConfirmation === 'function') closeStartOverConfirmation(); } catch (_) {}
            return;
        }

        // Cleanup Confirmation: explicit close button
        const closeCleanup = closest('[data-action="close-cleanup-confirmation"]');
        if (closeCleanup) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeCleanupConfirmation === 'function') window.closeCleanupConfirmation(); else if (typeof closeCleanupConfirmation === 'function') closeCleanupConfirmation(); } catch (_) {}
            return;
        }

        // Cleanup Confirmation: proceed
        const confirmCleanup = closest('[data-action="confirm-cleanup-action"]');
        if (confirmCleanup) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.confirmCleanupAction === 'function') window.confirmCleanupAction(); else if (typeof confirmCleanupAction === 'function') confirmCleanupAction(); } catch (_) {}
            return;
        }

        // Start Over Confirmation: explicit close button
        const closeStartOver = closest('[data-action="close-startover-confirmation"]');
        if (closeStartOver) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeStartOverConfirmation === 'function') window.closeStartOverConfirmation(); else if (typeof closeStartOverConfirmation === 'function') closeStartOverConfirmation(); } catch (_) {}
            return;
        }

        // Start Over Confirmation: proceed
        const proceedStartOver = closest('[data-action="startover-proceed"]');
        if (proceedStartOver) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.executeStartOver === 'function') window.executeStartOver(); else if (typeof executeStartOver === 'function') executeStartOver(); } catch (_) {}
            return;
        }

        // Open Website Backup Modal (files + db)
        const openBackup = closest('[data-action="open-backup-modal"]');
        if (openBackup) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showBackupModal === 'function') {
                    window.showBackupModal();
                } else if (typeof showBackupModal === 'function') {
                    showBackupModal();
                } else {
                    const el = document.getElementById('backupModal');
                    if (el && el.classList) el.classList.remove('hidden');
                }
            } catch (_) {}
            return;
        }

        // Open Database Backup Modal
        const openDbBackup = closest('[data-action="open-db-backup-modal"]');
        if (openDbBackup) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showDatabaseBackupModal === 'function') {
                    window.showDatabaseBackupModal();
                } else if (typeof showDatabaseBackupModal === 'function') {
                    showDatabaseBackupModal();
                } else {
                    const el = document.getElementById('databaseBackupModal');
                    if (el && el.classList) el.classList.remove('hidden');
                }
            } catch (_) {}
            return;
        }

        // Open Database Restore Modal
        const openDbRestore = closest('[data-action="open-db-restore-modal"]');
        if (openDbRestore) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showDatabaseRestoreModal === 'function') {
                    window.showDatabaseRestoreModal();
                } else if (typeof showDatabaseRestoreModal === 'function') {
                    showDatabaseRestoreModal();
                } else {
                    const el = document.getElementById('databaseRestoreModal');
                    if (el && el.classList) el.classList.remove('hidden');
                }
            } catch (_) {}
            return;
        }

        // Execute full-site Backup (files + db)
        const execBackup = closest('[data-action="execute-backup"]');
        if (execBackup) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.executeBackup === 'function') {
                    window.executeBackup();
                } else if (typeof executeBackup === 'function') {
                    executeBackup();
                }
            } catch (_) {}
            return;
        }

        // Open Attributes Modal
        const openAttributes = closest('[data-action="open-attributes"]');
        if (openAttributes) {
          e.preventDefault();
          try {
            const modal = document.getElementById('attributesModal');
            if (modal) {
              modal.classList.remove('hidden');
              modal.classList.add('show');
              modal.setAttribute('aria-hidden', 'false');
              // Call the initializer if it exists
              if (typeof window.initAttributesModal === 'function') {
                window.initAttributesModal(modal);
              }
              // Ensure iframe is primed and overlay is responsive for autosize
              try {
                const frame = modal.querySelector('#attributesFrame') || modal.querySelector('iframe');
                if (frame) {
                  if (!frame.getAttribute('src')) {
                    const ds = frame.getAttribute('data-src') || '/components/embeds/attributes_manager.php?modal=1';
                    frame.setAttribute('src', ds);
                  }
                  try { frame.classList.remove('wf-admin-embed-frame--tall','wf-embed--fill'); } catch(_) {}
                  try { if (!frame.hasAttribute('data-autosize')) frame.setAttribute('data-autosize','1'); } catch(_) {}
                  try { markOverlayResponsive(modal); } catch(_) {}
                  try { attachSameOriginFallback(frame, modal); } catch(_) {}
                } else {
                  try { markOverlayResponsive(modal); } catch(_) {}
                }
              } catch(_) {}
            }
          } catch (_) {}
          return;
        }

        // Execute Database-only Backup
        const execDbBackup = closest('[data-action="execute-db-backup"]');
        if (execDbBackup) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.executeDatabaseBackup === 'function') {
                    window.executeDatabaseBackup();
                } else if (typeof executeDatabaseBackup === 'function') {
                    executeDatabaseBackup();
                }
            } catch (_) {}
            return;
        }

        // Restore flow navigation and execution
        const restorePrev = closest('[data-action="restore-prev"]');
        if (restorePrev) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.previousRestoreStep === 'function') {
                    window.previousRestoreStep();
                } else if (typeof previousRestoreStep === 'function') {
                    previousRestoreStep();
                }
            } catch (_) {}
            return;
        }

        const restoreNext = closest('[data-action="restore-next"]');
        if (restoreNext) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.nextRestoreStep === 'function') {
                    window.nextRestoreStep();
                } else if (typeof nextRestoreStep === 'function') {
                    nextRestoreStep();
                }
            } catch (_) {}
            return;
        }

        const restoreExecute = closest('[data-action="restore-execute"]');
        if (restoreExecute) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.executeRestore === 'function') {
                    window.executeRestore();
                } else if (typeof executeRestore === 'function') {
                    executeRestore();
                }
            } catch (_) {}
            return;
        }

        // Trigger file selection dialog for restore
        const triggerFileDialog = closest('[data-action="trigger-backup-file-dialog"]');
        if (triggerFileDialog) {
            e.preventDefault();
            try {
                const overlay = triggerFileDialog.closest('.admin-modal-overlay') || document;
                const input = overlay.querySelector('#backupFile') || document.getElementById('backupFile');
                if (input && input.click) input.click();
            } catch (_) {}
            return;
        }

        // Clear selected restore file
        const restoreFileClear = closest('[data-action="restore-file-clear"]');
        if (restoreFileClear) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.clearFileSelection === 'function') {
                    window.clearFileSelection();
                } else if (typeof clearFileSelection === 'function') {
                    clearFileSelection();
                } else {
                    const overlay = restoreFileClear.closest('.admin-modal-overlay') || document;
                    const input = overlay.querySelector('#backupFile') || document.getElementById('backupFile');
                    if (input) input.value = '';
                }
            } catch (_) {}
            return;
        }
}

const _mapperIsDrawing = false;
let _mapperStartX, _mapperStartY;
const _mapperCurrentArea = null;
const _mapperAreaCount = 0;
const _mapperOriginalImageWidth = 1280;
const _mapperOriginalImageHeight = 896;



function _openSystemConfigModal() {
    // Centralized open to ensure scroll lock via WFModals
    if (typeof window.openModal === 'function') {
        window.openModal('systemConfigModal');
    } else {
        const modal = document.getElementById('systemConfigModal');
        if (modal) { modal.classList.remove('hidden'); modal.classList.add('show'); }
    }
    loadSystemConfiguration();
}

// -----------------------------
// Content Tone & Brand Voice Management (migrated)
// -----------------------------

// In-memory state
let contentToneOptions = [];
let brandVoiceOptions = [];
let contentToneOriginalIds = new Set();
let brandVoiceOriginalIds = new Set();

// ---- Content Tone ----
async function loadContentToneOptions() {
  try {
    const result = await ApiClient.get('/api/content_tone_options.php?action=get_active&admin_token=whimsical_admin_2024');
    if (result.success && Array.isArray(result.options) && result.options.length > 0) {
      contentToneOptions = result.options.map(o => ({ id: o.value, name: o.label, description: o.description }));
      contentToneOriginalIds = new Set(contentToneOptions.map(o => o.id));
      populateContentToneDropdown();
    } else {
      await initializeDefaultContentToneOptions();
    }
  } catch (error) {
    console.error('Error loading content tone options:', error.message);
    loadDefaultContentToneOptions();
  }
}

async function initializeDefaultContentToneOptions() {
  try {
    const result = await ApiClient.get('/api/content_tone_options.php?action=initialize_defaults&admin_token=whimsical_admin_2024', { method: 'POST' });
    if (result.success) {
      await loadContentToneOptions();
    } else {
      loadDefaultContentToneOptions();
    }
  } catch (error) {
    console.error('Error initializing default content tone options:', error.message);
    loadDefaultContentToneOptions();
  }
}

function loadDefaultContentToneOptions() {
  contentToneOptions = [
    { id: 'professional', name: 'Professional', description: 'Formal, business-focused tone' },
    { id: 'friendly', name: 'Friendly', description: 'Warm and approachable' },
    { id: 'casual', name: 'Casual', description: 'Relaxed and informal' },
    { id: 'energetic', name: 'Energetic', description: 'Enthusiastic and dynamic' },
    { id: 'sophisticated', name: 'Sophisticated', description: 'Elegant and refined' },
    { id: 'playful', name: 'Playful', description: 'Fun and lighthearted' },
    { id: 'authoritative', name: 'Authoritative', description: 'Expert and confident' },
    { id: 'conversational', name: 'Conversational', description: 'Natural and engaging' },
    { id: 'inspiring', name: 'Inspiring', description: 'Motivational and uplifting' },
    { id: 'trustworthy', name: 'Trustworthy', description: 'Reliable and credible' },
    { id: 'innovative', name: 'Innovative', description: 'Forward-thinking and creative' },
    { id: 'luxurious', name: 'Luxurious', description: 'Premium and exclusive' }
  ];
  contentToneOriginalIds = new Set(contentToneOptions.map(o => o.id));
  populateContentToneDropdown();
}

function populateContentToneDropdown() {
  const dropdown = document.getElementById('ai_content_tone');
  if (!dropdown) return;
  const currentValue = dropdown.value;
  dropdown.innerHTML = '<option value="">Select content tone...</option>';
  contentToneOptions.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt.id; el.textContent = opt.name; if (opt.description) el.title = opt.description;
    dropdown.appendChild(el);
  });
  if (currentValue) dropdown.value = currentValue;
}

function manageContentToneOptions() {
  showContentToneModal();
}

function showContentToneModal() {
  const existing = document.getElementById('contentToneModal');
  if (existing) existing.remove();
  const modalHtml = `
    <div id="contentToneModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost" data-action="overlay-close">
      <div class="admin-modal admin-modal--md bg-white rounded-lg flex flex-col" role="dialog" aria-modal="true" aria-labelledby="contentToneTitle">
        <div class="flex justify-between items-center border-b p-4">
          <h3 id="contentToneTitle" class="text-lg font-semibold text-gray-800">Manage Content Tone Options</h3>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="content-tone-close" aria-label="Close">×</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <p class="text-sm text-gray-600">Manage the content tone options available for AI content generation.</p>
              <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white rounded text-sm px-3 py-1" data-action="content-tone-add">Add Option</button>
            </div>
            <div id="contentToneList" class="space-y-2"></div>
          </div>
        </div>
        <div class="border-t flex justify-end space-x-2 p-3">
          <button type="button" class="text-gray-600 hover:text-gray-800 px-3 py-1" data-action="content-tone-close">Cancel</button>
          <button type="button" class="btn btn-primary px-3 py-1" data-action="content-tone-save">Save Changes</button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  displayContentToneOptions();
}

function closeContentToneModal() {
  const modal = document.getElementById('contentToneModal');
  if (modal) modal.remove();
}

function displayContentToneOptions() {
  const container = document.getElementById('contentToneList');
  if (!container) return;
  container.innerHTML = '';
  contentToneOptions.forEach((opt, index) => {
    const row = document.createElement('div');
    row.className = 'flex items-center space-x-3 bg-gray-50 rounded-lg p-2';
    row.innerHTML = `
      <input type="text" value="${opt.name || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="content-tone-update" data-index="${index}" data-field="name" placeholder="Name">
      <input type="text" value="${opt.description || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="content-tone-update" data-index="${index}" data-field="description" placeholder="Description (optional)">
      <button type="button" class="text-red-500 hover:text-red-700" data-action="content-tone-remove" data-index="${index}" aria-label="Remove">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </button>`;
    container.appendChild(row);
  });
}

function addContentToneOption() {
  contentToneOptions.push({ id: 'custom_' + Date.now(), name: 'New Tone', description: 'Custom content tone' });
  displayContentToneOptions();
}

function updateContentToneOption(index, field, value) {
  if (contentToneOptions[index]) {
    contentToneOptions[index][field] = value;
    if (field === 'name') {
      // Preserve original id for rename detection
      if (!contentToneOptions[index]._originalId) {
        contentToneOptions[index]._originalId = contentToneOptions[index].id;
      }
      contentToneOptions[index].id = (value || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
    }
  }
}

async function removeContentToneOption(index) {
  if (!isNaN(index) && index >= 0 && index < contentToneOptions.length) {
    const ok = await (window.showConfirmationModal && window.showConfirmationModal({
      title: 'Remove Option',
      message: 'Are you sure you want to remove this content tone option?',
      confirmText: 'Remove',
      confirmStyle: 'danger',
      icon: '⚠️',
      iconType: 'danger'
    }));
    if (ok) {
      contentToneOptions.splice(index, 1);
      displayContentToneOptions();
    }
  }
}

async function saveContentToneOptions() {
  try {
    // Determine renames and deletions
    const currentIds = new Set(contentToneOptions.map(o => o.id));
    const renamedOldIds = new Set();
    for (const o of contentToneOptions) {
      if (o._originalId && o._originalId !== o.id) renamedOldIds.add(o._originalId);
    }
    const toDelete = Array.from(contentToneOriginalIds).filter(id => !currentIds.has(id) && !renamedOldIds.has(id));

    // Persist current options
    for (const o of contentToneOptions) {
      if (o._originalId && o._originalId !== o.id && contentToneOriginalIds.has(o._originalId)) {
        // Rename -> delete old, add new
        await deleteContentToneOptionFromDB(o._originalId);
        await saveContentToneOption(o, true);
      } else if (contentToneOriginalIds.has(o.id)) {
        await saveContentToneOption(o, false);
      } else {
        await saveContentToneOption(o, true);
      }
    }

    // Delete removed options
    for (const id of toDelete) {
      await deleteContentToneOptionFromDB(id);
    }

    // Reload and refresh UI
    await loadContentToneOptions();
    populateContentToneDropdown();
    try { wfToast({ success: true, message: 'Updated successfully' }); } catch(_) {}
    closeContentToneModal();
  } catch (error) {
    try { wfToastErr('Error saving options: ' + error.message); } catch (_) {}
  }
}

async function saveContentToneOption(option, isNew = false) {
  try {
    const action = isNew ? 'add' : 'update';
    const result = await ApiClient.post(`/api/content_tone_options.php?action=${action}&admin_token=whimsical_admin_2024`, {
      id: option.id,
      value: option.value || option.id,
      label: option.name,
      description: option.description
    });
    try { wfToast(result); } catch(_) {}
    if (!result.success) { try { /* keep legacy */ showNotification('Content Tone Options', 'Failed to save option: ' + (result.error||result.message||''), 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { wfToastErr('Error saving option: ' + error.message); } catch (_) {}
    return false;
  }
}

async function deleteContentToneOptionFromDB(optionId) {
  try {
    const result = await ApiClient.get(`/api/content_tone_options.php?action=delete&id=${optionId}&admin_token=whimsical_admin_2024`, { method: 'DELETE' });
    try { wfToast(result); } catch(_) {}
    if (!result.success) { try { showNotification('Content Tone Options', 'Failed to delete option: ' + (result.error||result.message||''), 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { wfToastErr('Error deleting option: ' + error.message); } catch (_) {}
    return false;
  }
}

// ---- Brand Voice ----
async function loadBrandVoiceOptions() {
  try {
    const result = await ApiClient.get('/api/brand_voice_options.php?action=get_active&admin_token=whimsical_admin_2024');
    if (result.success && Array.isArray(result.options) && result.options.length > 0) {
      brandVoiceOptions = result.options.map(o => ({ id: o.value, name: o.label, description: o.description }));
      brandVoiceOriginalIds = new Set(brandVoiceOptions.map(o => o.id));
      populateBrandVoiceDropdown();
    } else {
      await initializeDefaultBrandVoiceOptions();
    }
  } catch (error) {
    console.error('Error loading brand voice options:', error.message);
    loadDefaultBrandVoiceOptions();
  }
}

async function initializeDefaultBrandVoiceOptions() {
  try {
    const result = await ApiClient.get('/api/brand_voice_options.php?action=initialize_defaults&admin_token=whimsical_admin_2024', { method: 'POST' });
    if (result.success) {
      await loadBrandVoiceOptions();
    } else {
      loadDefaultBrandVoiceOptions();
    }
  } catch (error) {
    console.error('Error initializing default brand voice options:', error.message);
    loadDefaultBrandVoiceOptions();
  }
}

function loadDefaultBrandVoiceOptions() {
  brandVoiceOptions = [
    { id: 'friendly_approachable', name: 'Friendly & Approachable', description: 'Warm, welcoming, and easy to connect with' },
    { id: 'professional_trustworthy', name: 'Professional & Trustworthy', description: 'Business-focused, reliable, and credible' },
    { id: 'playful_fun', name: 'Playful & Fun', description: 'Lighthearted, entertaining, and engaging' },
    { id: 'luxurious_premium', name: 'Luxurious & Premium', description: 'High-end, sophisticated, and exclusive' },
    { id: 'casual_relaxed', name: 'Casual & Relaxed', description: 'Laid-back, informal, and comfortable' },
    { id: 'authoritative_expert', name: 'Authoritative & Expert', description: 'Knowledgeable, confident, and commanding' },
    { id: 'warm_personal', name: 'Warm & Personal', description: 'Intimate, caring, and heartfelt' },
    { id: 'innovative_forward_thinking', name: 'Innovative & Forward-Thinking', description: 'Creative, cutting-edge, and progressive' },
    { id: 'energetic_dynamic', name: 'Energetic & Dynamic', description: 'Enthusiastic, vibrant, and exciting' },
    { id: 'sophisticated_elegant', name: 'Sophisticated & Elegant', description: 'Refined, polished, and tasteful' },
    { id: 'conversational_natural', name: 'Conversational & Natural', description: 'Dialogue-like, personal, and engaging' },
    { id: 'inspiring_motivational', name: 'Inspiring & Motivational', description: 'Uplifting, encouraging, and empowering' }
  ];
  brandVoiceOriginalIds = new Set(brandVoiceOptions.map(o => o.id));
  populateBrandVoiceDropdown();
}

function populateBrandVoiceDropdown() {
  const dropdown = document.getElementById('ai_brand_voice');
  if (!dropdown) return;
  const currentValue = dropdown.value;
  dropdown.innerHTML = '<option value="">Select brand voice...</option>';
  brandVoiceOptions.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt.id; el.textContent = opt.name; if (opt.description) el.title = opt.description;
    dropdown.appendChild(el);
  });
  if (currentValue) dropdown.value = currentValue;
}

function manageBrandVoiceOptions() {
  showBrandVoiceModal();
}

function showBrandVoiceModal() {
  const existing = document.getElementById('brandVoiceModal');
  if (existing) existing.remove();
  const modalHtml = `
    <div id="brandVoiceModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost" data-action="overlay-close">
      <div class="admin-modal admin-modal--md bg-white rounded-lg flex flex-col" role="dialog" aria-modal="true" aria-labelledby="brandVoiceTitle">
        <div class="flex justify-between items-center border-b p-4">
          <h3 id="brandVoiceTitle" class="text-lg font-semibold text-gray-800">Manage Brand Voice Options</h3>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="brand-voice-close" aria-label="Close">×</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <p class="text-sm text-gray-600">Manage the brand voice options available for AI content generation.</p>
              <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white rounded text-sm px-3 py-1" data-action="brand-voice-add">Add Option</button>
            </div>
            <div id="brandVoiceList" class="space-y-2"></div>
          </div>
        </div>
        <div class="border-t flex justify-end space-x-2 p-3">
          <button type="button" class="text-gray-600 hover:text-gray-800 px-3 py-1" data-action="brand-voice-close">Cancel</button>
          <button type="button" class="btn btn-primary px-3 py-1" data-action="brand-voice-save">Save Changes</button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  displayBrandVoiceOptions();
}

function closeBrandVoiceModal() {
  const modal = document.getElementById('brandVoiceModal');
  if (modal) modal.remove();
}

function displayBrandVoiceOptions() {
  const container = document.getElementById('brandVoiceList');
  if (!container) return;
  container.innerHTML = '';
  brandVoiceOptions.forEach((opt, index) => {
    const row = document.createElement('div');
    row.className = 'flex items-center space-x-3 bg-gray-50 rounded-lg p-2';
    row.innerHTML = `
      <input type="text" value="${opt.name || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="brand-voice-update" data-index="${index}" data-field="name" placeholder="Name">
      <input type="text" value="${opt.description || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="brand-voice-update" data-index="${index}" data-field="description" placeholder="Description (optional)">
      <button type="button" class="text-red-500 hover:text-red-700" data-action="brand-voice-remove" data-index="${index}" aria-label="Remove">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </button>`;
    container.appendChild(row);
  });
}

function addBrandVoiceOption() {
  brandVoiceOptions.push({ id: 'custom_' + Date.now(), name: 'New Voice', description: 'Custom brand voice' });
  displayBrandVoiceOptions();
}

function updateBrandVoiceOption(index, field, value) {
  if (brandVoiceOptions[index]) {
    brandVoiceOptions[index][field] = value;
    if (field === 'name') {
      if (!brandVoiceOptions[index]._originalId) {
        brandVoiceOptions[index]._originalId = brandVoiceOptions[index].id;
      }
      brandVoiceOptions[index].id = (value || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
    }
  }
}

async function removeBrandVoiceOption(index) {
  if (!isNaN(index) && index >= 0 && index < brandVoiceOptions.length) {
    const ok = await (window.showConfirmationModal && window.showConfirmationModal({
      title: 'Remove Option',
      message: 'Are you sure you want to remove this brand voice option?',
      confirmText: 'Remove',
      confirmStyle: 'danger',
      icon: '⚠️',
      iconType: 'danger'
    }));
    if (ok) {
      brandVoiceOptions.splice(index, 1);
      displayBrandVoiceOptions();
    }
  }
}

async function saveBrandVoiceOptions() {
  try {
    const currentIds = new Set(brandVoiceOptions.map(o => o.id));
    const renamedOldIds = new Set();
    for (const o of brandVoiceOptions) {
      if (o._originalId && o._originalId !== o.id) renamedOldIds.add(o._originalId);
    }
    const toDelete = Array.from(brandVoiceOriginalIds).filter(id => !currentIds.has(id) && !renamedOldIds.has(id));

    for (const o of brandVoiceOptions) {
      if (o._originalId && o._originalId !== o.id && brandVoiceOriginalIds.has(o._originalId)) {
        await deleteBrandVoiceOptionFromDB(o._originalId);
        await saveBrandVoiceOption(o, true);
      } else if (brandVoiceOriginalIds.has(o.id)) {
        await saveBrandVoiceOption(o, false);
      } else {
        await saveBrandVoiceOption(o, true);
      }
    }

    for (const id of toDelete) {
      await deleteBrandVoiceOptionFromDB(id);
    }

    await loadBrandVoiceOptions();
    populateBrandVoiceDropdown();
    try { wfToast({ success: true, message: 'Updated successfully' }); } catch(_) {}
    closeBrandVoiceModal();
  } catch (error) {
    try { wfToastErr('Error saving options: ' + error.message); } catch (_) {}
  }
}

async function saveBrandVoiceOption(option, isNew = false) {
  try {
    const action = isNew ? 'add' : 'update';
    const result = await ApiClient.post(`/api/brand_voice_options.php?action=${action}&admin_token=whimsical_admin_2024`, {
      id: option.id,
      value: option.value || option.id,
      label: option.name,
      description: option.description
    });
    try { wfToast(result); } catch(_) {}
    if (!result.success) { try { showNotification('Brand Voice Options', 'Failed to save option: ' + (result.error||result.message||''), 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { wfToastErr('Error saving option: ' + error.message); } catch (_) {}
    return false;
  }
}

async function deleteBrandVoiceOptionFromDB(optionId) {
  try {
    const result = await ApiClient.get(`/api/brand_voice_options.php?action=delete&id=${optionId}&admin_token=whimsical_admin_2024`, { method: 'DELETE' });
    try { wfToast(result); } catch(_) {}
    if (!result.success) { try { showNotification('Brand Voice Options', 'Failed to delete option: ' + (result.error||result.message||''), 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { wfToastErr('Error deleting option: ' + error.message); } catch (_) {}
    return false;
  }
}

// -----------------------------
// AI Settings Modal & Model Management (migrated)
// -----------------------------

// Open/Close AI Settings modal (safe wrappers)
function openAISettingsModal() {
  const modal = document.getElementById('aiSettingsModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  try { if (typeof loadAISettings === 'function') loadAISettings(); } catch (_) {}
  try { loadAIProviders(); } catch (_) {}
  try { if (typeof loadContentToneOptions === 'function') loadContentToneOptions(); } catch (_) {}
  try { if (typeof loadBrandVoiceOptions === 'function') loadBrandVoiceOptions(); } catch (_) {}
}

function closeAISettingsModal() {
  const modal = document.getElementById('aiSettingsModal');
  if (!modal) return;
  modal.classList.add('hidden');
}

async function loadAIProviders() {
  try {
    const data = await ApiClient.get('/api/ai_settings.php?action=get_providers');
    if (data.success) {
      displayAIProviders(data.providers);
    } else {
      console.error('Failed to load AI providers:', data.error);
    }
  } catch (error) {
    console.error('Error loading AI providers:', error.message);
  }
}

function displayAIProviders(providers) {
  try {
    window.aiProviders = providers;
  } catch (e) {
    // Fallback: still render UI using current select/defaults even if API fails (e.g., incognito not logged in)
    try {
      const sel = document.getElementById('aiProvider');
      const provider = (sel && sel.value) || getDefaultAIProvider();
      renderAIProviderUI(provider, {});
    } catch(_) {}
  }
}

// Load current AI settings and populate the modal fields
async function loadAISettings() {
  try {
    const result = await ApiClient.get('/api/ai_settings.php?action=get_settings');
    const settings = (result && result.success && result.settings) ? result.settings : {};

    // Provider select
    const sel = document.getElementById('aiProvider');
    if (sel && settings.ai_provider) {
      try { sel.value = settings.ai_provider; } catch (_) {}
    }

    // Temperature and display
    const temp = document.getElementById('aiTemperature') || document.getElementById('ai_temperature');
    const tempLabel = document.getElementById('aiTemperatureValue');
    if (temp && typeof settings.ai_temperature !== 'undefined') {
      temp.value = settings.ai_temperature;
      if (tempLabel) tempLabel.textContent = String(settings.ai_temperature);
    }

    // Max tokens
    const maxTok = document.getElementById('aiMaxTokens') || document.getElementById('ai_max_tokens');
    if (maxTok && typeof settings.ai_max_tokens !== 'undefined') {
      maxTok.value = settings.ai_max_tokens;
    }

    // Timeout
    const timeout = document.getElementById('aiTimeout') || document.getElementById('ai_timeout');
    if (timeout && typeof settings.ai_timeout !== 'undefined') {
      timeout.value = settings.ai_timeout;
    }

    // Fallback to local
    const fallback = document.getElementById('fallbackToLocal') || document.getElementById('fallback_to_local');
    if (fallback && typeof settings.fallback_to_local !== 'undefined') {
      fallback.checked = !!settings.fallback_to_local;
    }

    // Render provider-specific fields and load models
    const provider = (sel && sel.value) || settings.ai_provider || getDefaultAIProvider();
    renderAIProviderUI(provider, settings);

    // Wire change listener once
    if (sel && !sel.__wfAIChangeWired) {
      sel.__wfAIChangeWired = true;
      sel.addEventListener('change', function () {
        const prov = sel.value || getDefaultAIProvider();
        renderAIProviderUI(prov, settings);
      });
    }
  } catch (_) {}
}

function renderAIProviderUI(provider, settings) {
  const container = document.getElementById('aiProviderSettings');
  if (!container) return;
  const prov = provider || 'jons_ai';
  const s = settings || {};
  let html = '';
  if (prov === 'openai') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="openai_api_key" class="block text-sm font-medium mb-1">OpenAI API Key</label>',
      '    <input id="openai_api_key" type="password" class="form-input w-full" placeholder="sk-..." />',
      '  </div>',
      '  <div>',
      '    <label for="openai_model" class="block text-sm font-medium mb-1">OpenAI Model</label>',
      '    <select id="openai_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'anthropic') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="anthropic_api_key" class="block text-sm font-medium mb-1">Anthropic API Key</label>',
      '    <input id="anthropic_api_key" type="password" class="form-input w-full" placeholder="anthropic-key" />',
      '  </div>',
      '  <div>',
      '    <label for="anthropic_model" class="block text-sm font-medium mb-1">Anthropic Model</label>',
      '    <select id="anthropic_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'google') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="google_api_key" class="block text-sm font-medium mb-1">Google API Key</label>',
      '    <input id="google_api_key" type="password" class="form-input w-full" placeholder="AIza..." />',
      '  </div>',
      '  <div>',
      '    <label for="google_model" class="block text-sm font-medium mb-1">Google Model</label>',
      '    <select id="google_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'meta') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="meta_api_key" class="block text-sm font-medium mb-1">Meta API Key</label>',
      '    <input id="meta_api_key" type="password" class="form-input w-full" placeholder="..." />',
      '  </div>',
      '  <div>',
      '    <label for="meta_model" class="block text-sm font-medium mb-1">Meta Model</label>',
      '    <select id="meta_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else {
    html = '<div class="text-sm text-gray-500">Using local AI. No keys required.</div>';
  }
  container.innerHTML = html;

  // Prefill non-sensitive model from settings
  try {
    if (prov === 'openai' && s.openai_model) {
      const el = document.getElementById('openai_model'); if (el) el.value = s.openai_model;
    } else if (prov === 'anthropic' && s.anthropic_model) {
      const el = document.getElementById('anthropic_model'); if (el) el.value = s.anthropic_model;
    } else if (prov === 'google' && s.google_model) {
      const el = document.getElementById('google_model'); if (el) el.value = s.google_model;
    } else if (prov === 'meta' && s.meta_model) {
      const el = document.getElementById('meta_model'); if (el) el.value = s.meta_model;
    }
  } catch(_) {}

  // Populate models list for the chosen provider
  try {
    if (typeof loadModelsForCurrentProvider === 'function') {
      const merged = Object.assign({}, s, { ai_provider: prov });
      loadModelsForCurrentProvider(merged);
    } else if (typeof refreshModels === 'function') {
      refreshModels(prov);
    }
  } catch(_) {}

  // Add "Saved" badge if secret is present
  try {
    const addBadge = (forId) => {
      const lbl = container.querySelector(`label[for="${forId}"]`);
      if (!lbl) return;
      const badge = document.createElement('span');
      badge.textContent = 'Saved';
      badge.className = 'ml-2 inline-block px-2 py-0.5 text-xs rounded bg-green-100 text-green-700 align-middle';
      lbl.appendChild(badge);
    };
    if (prov === 'openai' && s.openai_key_present) addBadge('openai_api_key');
    if (prov === 'anthropic' && s.anthropic_key_present) addBadge('anthropic_api_key');
    if (prov === 'google' && s.google_key_present) addBadge('google_api_key');
    if (prov === 'meta' && s.meta_key_present) addBadge('meta_api_key');
  } catch(_) {}
}

function toggleSection(section) {
  if (!section) return;
  const content = document.getElementById(`${section}-content`);
  const icon = document.getElementById(`${section}-icon`);
  if (content) content.classList.toggle('hidden');
  if (icon && content) icon.textContent = !content.classList.contains('hidden') ? '▼' : '▶';
}

function toggleProviderSections() {
  const spEl = document.querySelector('input[name="ai_provider"]:checked');
  const selectedProvider = (spEl && typeof spEl.value !== 'undefined' ? spEl.value : '') || getDefaultAIProvider();
  const providers = ['openai','anthropic','google','meta'];
  providers.forEach(p => {
    const sec = document.getElementById(`${p}_section`);
    if (!sec) return;
    if (selectedProvider === 'jons_ai') {
      sec.classList.add('hidden');
    } else {
      if (p === selectedProvider) sec.classList.remove('hidden');
      else sec.classList.add('hidden');
    }
  });
  try {
    if (typeof window.aiSettings !== 'undefined' && window.aiSettings) {
      loadModelsForCurrentProvider(window.aiSettings);
    } else if (selectedProvider && selectedProvider !== 'jons_ai') {
      refreshModels(selectedProvider);
    }
  } catch (_) {}
}

async function saveAISettings() {
  const _sp = document.querySelector('input[name="ai_provider"]:checked');
  const _selProvider = document.getElementById('aiProvider');
  const _openai_api_key = document.getElementById('openai_api_key');
  const _openai_model = document.getElementById('openai_model');
  const _anthropic_api_key = document.getElementById('anthropic_api_key');
  const _anthropic_model = document.getElementById('anthropic_model');
  const _google_api_key = document.getElementById('google_api_key');
  const _google_model = document.getElementById('google_model');
  const _meta_api_key = document.getElementById('meta_api_key');
  const _meta_model = document.getElementById('meta_model');
  const _ai_temperature = document.getElementById('ai_temperature') || document.getElementById('aiTemperature');
  const _ai_max_tokens = document.getElementById('ai_max_tokens') || document.getElementById('aiMaxTokens');
  const _ai_timeout = document.getElementById('ai_timeout') || document.getElementById('aiTimeout');
  const _fallback_to_local = document.getElementById('fallback_to_local') || document.getElementById('fallbackToLocal');
  const _ai_brand_voice = document.getElementById('ai_brand_voice');
  const _ai_content_tone = document.getElementById('ai_content_tone');
  const _ai_cost_temperature = document.getElementById('ai_cost_temperature');
  const _ai_price_temperature = document.getElementById('ai_price_temperature');
  const _ai_cost_multiplier_base = document.getElementById('ai_cost_multiplier_base');
  const _ai_price_multiplier_base = document.getElementById('ai_price_multiplier_base');
  const _ai_conservative_mode = document.getElementById('ai_conservative_mode');
  const _ai_market_research_weight = document.getElementById('ai_market_research_weight');
  const _ai_cost_plus_weight = document.getElementById('ai_cost_plus_weight');
  const _ai_value_based_weight = document.getElementById('ai_value_based_weight');

  const settings = {
    ai_provider: (_sp && _sp.value) || (_selProvider && _selProvider.value) || getDefaultAIProvider(),
    openai_api_key: (_openai_api_key && _openai_api_key.value) || '',
    openai_model: (_openai_model && _openai_model.value) || 'gpt-3.5-turbo',
    anthropic_api_key: (_anthropic_api_key && _anthropic_api_key.value) || '',
    anthropic_model: (_anthropic_model && _anthropic_model.value) || 'claude-3-haiku-20240307',
    google_api_key: (_google_api_key && _google_api_key.value) || '',
    google_model: (_google_model && _google_model.value) || 'gemini-pro',
    meta_api_key: (_meta_api_key && _meta_api_key.value) || '',
    meta_model: (_meta_model && _meta_model.value) || 'meta-llama/llama-3.1-70b-instruct',
    ai_temperature: parseFloat((_ai_temperature && _ai_temperature.value) || 0.7),
    ai_max_tokens: parseInt((_ai_max_tokens && _ai_max_tokens.value) || 1000, 10),
    ai_timeout: parseInt((_ai_timeout && _ai_timeout.value) || 30, 10),
    fallback_to_local: !!(_fallback_to_local && _fallback_to_local.checked),
    ai_brand_voice: (_ai_brand_voice && _ai_brand_voice.value) || '',
    ai_content_tone: (_ai_content_tone && _ai_content_tone.value) || 'professional',
    ai_cost_temperature: parseFloat((_ai_cost_temperature && _ai_cost_temperature.value) || 0.7),
    ai_price_temperature: parseFloat((_ai_price_temperature && _ai_price_temperature.value) || 0.7),
    ai_cost_multiplier_base: parseFloat((_ai_cost_multiplier_base && _ai_cost_multiplier_base.value) || 1.0),
    ai_price_multiplier_base: parseFloat((_ai_price_multiplier_base && _ai_price_multiplier_base.value) || 1.0),
    ai_conservative_mode: !!(_ai_conservative_mode && _ai_conservative_mode.checked),
    ai_market_research_weight: parseFloat((_ai_market_research_weight && _ai_market_research_weight.value) || 0.3),
    ai_cost_plus_weight: parseFloat((_ai_cost_plus_weight && _ai_cost_plus_weight.value) || 0.4),
    ai_value_based_weight: parseFloat((_ai_value_based_weight && _ai_value_based_weight.value) || 0.3)
  };

  try {
    const result = await ApiClient.post('/api/ai_settings.php?action=update_settings', settings);
    if (result.success) {
      showAISettingsSuccess('AI settings saved successfully!');
    } else {
      showAISettingsError('Failed to save AI settings: ' + result.error);
    }
  } catch (error) {
    showAISettingsError('Error saving AI settings: ' + error.message);
  }
}

function showAISettingsSuccess(message) {
  try { showNotification('AI Settings Saved', message, 'success'); } catch (_) { console.log(message); }
  setTimeout(() => { try { closeAISettingsModal(); } catch (_) {} }, 1500);
}

function showAISettingsError(message) {
  try { showNotification('AI Settings Error', message, 'error'); } catch (_) { console.error(message); }
}

async function testAIProvider() {
  const _sp = document.querySelector('input[name="ai_provider"]:checked');
  const _selProvider = document.getElementById('aiProvider');
  const selectedProvider = (_sp && _sp.value) || (_selProvider && _selProvider.value) || getDefaultAIProvider();
  try {
    try { showNotification('Testing AI Provider', `Testing ${selectedProvider} provider...`, 'info'); } catch (_) {}
    const result = await ApiClient.get(`/api/ai_settings.php?action=test_provider&provider=${selectedProvider}`);
    if (result.success) {
      try { showNotification('AI Provider Test', `✅ ${selectedProvider} provider test successful!`, 'success'); } catch (_) {}
    } else {
      try { showNotification('AI Provider Test', `❌ ${selectedProvider} provider test failed: ${result.message}`, 'error'); } catch (_) {}
    }
  } catch (error) {
    try { showNotification('AI Provider Test', `❌ Test failed: ${error.message}`, 'error'); } catch (_) { console.error(error); }
  }
}

// AI Model Loading Functions
let availableModels = {};

async function loadAllModels() {
  try {
    const result = await ApiClient.get('/api/get_ai_models.php?provider=all');
    if (result.success) {
      availableModels = result.models;
      populateModelDropdown('openai', availableModels.openai);
      populateModelDropdown('anthropic', availableModels.anthropic);
      populateModelDropdown('google', availableModels.google);
      populateModelDropdown('meta', availableModels.meta);
    } else {
      console.error('❌ Failed to load AI models:', result.error);
      loadFallbackModels();
    }
  } catch (error) {
    console.error('❌ Error loading AI models:', error.message);
    loadFallbackModels();
  }
}

async function loadAllModelsWithSelection(settings) {
  try {
    const result = await ApiClient.get('/api/get_ai_models.php?provider=all');
    if (result.success) {
      availableModels = result.models;
      populateModelDropdownWithSelection('openai', availableModels.openai, settings.openai_model);
      populateModelDropdownWithSelection('anthropic', availableModels.anthropic, settings.anthropic_model);
      populateModelDropdownWithSelection('google', availableModels.google, settings.google_model);
      populateModelDropdownWithSelection('meta', availableModels.meta, settings.meta_model);
    } else {
      console.error('❌ Failed to load AI models:', result.error);
      loadFallbackModelsWithSelection(settings);
    }
  } catch (error) {
    console.error('❌ Error loading AI models:', error.message);
    loadFallbackModelsWithSelection(settings);
  }
}

function getDefaultAIProvider() {
  if (typeof window.aiSettings !== 'undefined' && window.aiSettings) {
    const providers = ['jons_ai', 'openai', 'anthropic', 'google', 'meta'];
    for (const provider of providers) {
      if (window.aiSettings[`${provider}_api_key`]) return provider;
    }
  }
  return 'jons_ai';
}

async function loadModelsForCurrentProvider(settings) {
  const selectedProvider = settings.ai_provider || getDefaultAIProvider();
  if (selectedProvider === 'jons_ai') return;
  try {
    const result = await ApiClient.get(`/api/get_ai_models.php?provider=${selectedProvider}&admin_token=whimsical_admin_2024`);
    if (result.success) {
      availableModels[selectedProvider] = result.models;
      const modelKey = `${selectedProvider}_model`;
      populateModelDropdownWithSelection(selectedProvider, result.models, settings[modelKey]);
    } else {
      console.error(`❌ Failed to load ${selectedProvider} models:`, result.error);
      loadFallbackModelsForProviderWithSelection(selectedProvider, settings);
    }
  } catch (error) {
    console.error(`❌ Error loading ${selectedProvider} models:`, error.message);
    loadFallbackModelsForProviderWithSelection(selectedProvider, settings);
  }
}

async function refreshModels(provider) {
  try {
    try { showNotification('Refreshing Models', `Loading ${provider} models...`, 'info'); } catch (_) {}
    const result = await ApiClient.get(`/api/get_ai_models.php?provider=${provider}&admin_token=whimsical_admin_2024`);
    if (result.success) {
      availableModels[provider] = result.models;
      populateModelDropdown(provider, result.models);
      try { showNotification('Models Refreshed', `✅ ${provider} models updated`, 'success'); } catch (_) {}
    } else {
      try { showNotification('Models Error', `❌ Failed to load ${provider} models: ${result.error}`, 'error'); } catch (_) {}
      loadFallbackModelsForProvider(provider);
    }
  } catch (error) {
    try { showNotification('Models Error', `❌ Error loading ${provider} models: ${error.message}`, 'error'); } catch (_) {}
    loadFallbackModelsForProvider(provider);
  }
}

function populateModelDropdown(provider, models) {
  const selectElement = document.getElementById(`${provider}_model`);
  if (!selectElement) return;
  const currentValue = selectElement.value;
  selectElement.innerHTML = '';
  if (!models || models.length === 0) {
    selectElement.innerHTML = '<option value="">No models available</option>';
    return;
  }
  models.forEach(model => {
    const option = document.createElement('option');
    option.value = model.id;
    option.textContent = `${model.name} - ${model.description}`;
    option.title = model.description;
    selectElement.appendChild(option);
  });
  if (currentValue && selectElement.querySelector(`option[value="${currentValue}"]`)) {
    selectElement.value = currentValue;
  } else {
    selectElement.selectedIndex = 0;
  }
}

function populateModelDropdownWithSelection(provider, models, selectedValue) {
  const selectElement = document.getElementById(`${provider}_model`);
  if (!selectElement) return;
  selectElement.innerHTML = '';
  if (!models || models.length === 0) {
    selectElement.innerHTML = '<option value="">No models available</option>';
    return;
  }
  models.forEach(model => {
    const option = document.createElement('option');
    option.value = model.id;
    option.textContent = `${model.name} - ${model.description}`;
    option.title = model.description;
    if (model.id === selectedValue) option.selected = true;
    selectElement.appendChild(option);
  });
  if (!selectedValue || !selectElement.querySelector(`option[value="${selectedValue}"]`)) {
    selectElement.selectedIndex = 0;
  }
}

function loadFallbackModels() {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  availableModels = fallbackModels;
  populateModelDropdown('openai', fallbackModels.openai);
  populateModelDropdown('anthropic', fallbackModels.anthropic);
  populateModelDropdown('google', fallbackModels.google);
  populateModelDropdown('meta', fallbackModels.meta);
}

function loadFallbackModelsForProvider(provider) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ]
  };
  if (fallbackModels[provider]) {
    availableModels[provider] = fallbackModels[provider];
    populateModelDropdown(provider, fallbackModels[provider]);
  }
}

function loadFallbackModelsWithSelection(settings) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  availableModels = fallbackModels;
  populateModelDropdownWithSelection('openai', fallbackModels.openai, settings.openai_model);
  populateModelDropdownWithSelection('anthropic', fallbackModels.anthropic, settings.anthropic_model);
  populateModelDropdownWithSelection('google', fallbackModels.google, settings.google_model);
  populateModelDropdownWithSelection('meta', fallbackModels.meta, settings.meta_model);
}

function loadFallbackModelsForProviderWithSelection(provider, settings) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  if (fallbackModels[provider]) {
    availableModels[provider] = fallbackModels[provider];
    const modelKey = `${provider}_model`;
    populateModelDropdownWithSelection(provider, fallbackModels[provider], settings[modelKey]);
  }
}

async function loadSystemConfiguration() {
    const loadingDiv = document.getElementById('systemConfigLoading');
    const contentDiv = document.getElementById('systemConfigContent');
    
    // Show loading state
    if (loadingDiv && loadingDiv.classList) loadingDiv.classList.remove('hidden');
    
    try {
        const result = await ApiClient.get('/api/get_system_config.php');
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            if (loadingDiv && loadingDiv.classList) loadingDiv.classList.add('hidden');
            contentDiv.innerHTML = generateSystemConfigHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load system configuration');
        }

    } catch (error) {
        console.error('Error loading system configuration:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load system configuration</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button type="button" data-action="retry-load-system-config" class="bg-orange-500 text-white rounded hover:bg-orange-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateSystemConfigHTML(data) {
    const _lastOrderDate = data.statistics.last_order_date ? 
        new Date(data.statistics.last_order_date).toLocaleDateString() : 'No orders yet';
    
    return `
        <!-- Current System Architecture -->
        <div class="bg-green-50 border-l-4 border-green-400">
            <h4 class="font-semibold text-green-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Current System Architecture (Live Data)
            </h4>
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-semibold text-green-700">🎯 Primary Identifier</h5>
                        <p class="text-green-600"><strong>${data.system_info.primary_identifier}</strong> - Human-readable codes</p>
                        <p class="text-xs text-green-600">Format: ${data.system_info.sku_format}</p>
                        <p class="text-xs text-green-600">Examples: ${data.sample_skus.slice(0, 3).join(', ')}</p>
                    </div>
                    <div>
                        <h5 class="font-semibold text-green-700">🏷️ Main Entity</h5>
                        <p class="text-green-600"><strong>${data.system_info.main_entity}</strong></p>
                        <p class="text-xs text-green-600">All inventory and shop items</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comprehensive SKU Methodology Documentation -->
        <div class="bg-blue-50 border-l-4 border-blue-400">
            <h4 class="font-semibold text-blue-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" clip-rule="evenodd"></path>
                </svg>
                📖 Complete SKU & ID Methodology Documentation
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🏷️ SKU System Overview</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Primary Format:</strong> WF-[CATEGORY]-[NUMBER]</p>
                            <p>• <strong>Enhanced Format:</strong> WF-[CAT]-[GENDER]-[SIZE]-[COLOR]-[NUM]</p>
                            <p>• <strong>Database:</strong> SKU-only system (no legacy IDs)</p>
                            <p>• <strong>Generation:</strong> Automatic via API with sequential numbering</p>
                            <p>• <strong>Usage:</strong> Primary key across all tables</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🔄 Migration History</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>✅ <strong>Phase 1:</strong> Eliminated dual itemId/SKU system</p>
                            <p>✅ <strong>Phase 2:</strong> Migrated "products" → "items" terminology</p>
                            <p>✅ <strong>Phase 3:</strong> Fixed order ID generation (sequence-based)</p>
                            <p>✅ <strong>Phase 4:</strong> Implemented global color/size management</p>
                            <p>✅ <strong>Current:</strong> Pure SKU-only architecture</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🛠️ API Endpoints</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <code>/api/next_sku.php</code> - Generate new SKUs</p>
                            <p>• <code>/api/get_items.php</code> - Retrieve items by SKU</p>
                            <p>• <code>/api/get_item_images.php</code> - Item images</p>
                            <p>• <code>/api/add_order.php</code> - Create orders (fixed)</p>
                            <p>• <code>/api/update_inventory_field.php</code> - SKU updates</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">📊 Current Statistics</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Items:</strong> ${data.statistics.total_items} (${data.statistics.total_images} images)</p>
                            <p>• <strong>Orders:</strong> ${data.statistics.total_orders} (${data.statistics.total_order_items} items)</p>
                            <p>• <strong>Categories:</strong> ${data.statistics.categories_count} active</p>
                            <p>• <strong>Last Order:</strong> ${data.statistics.last_order_date ? new Date(data.statistics.last_order_date).toLocaleDateString() : 'None'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SKU Categories -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400">
            <h4 class="font-semibold text-yellow-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                </svg>
                Active Categories & SKU Codes
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                ${Object.entries(data.category_codes).map(([category, code]) => {
                    const isActive = data.categories.includes(category);
                    return `
                        <div class="text-center ${isActive ? 'bg-yellow-100' : 'bg-gray-100'} rounded">
                            <div class="font-semibold ${isActive ? 'text-yellow-700' : 'text-gray-500'}">${code}</div>
                            <div class="text-xs ${isActive ? 'text-yellow-600' : 'text-gray-400'}">${category}</div>
                            ${isActive ? '<div class="text-xs text-green-600">✅ Active</div>' : '<div class="text-xs text-gray-400">Inactive</div>'}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>



        <!-- ID Number Legend -->
        <div class="bg-orange-50 border-l-4 border-orange-400">
            <h4 class="font-semibold text-orange-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                ID Number Legend & Formats
            </h4>
            <div class="space-y-4">
                <!-- Customer IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        ${data.id_formats.recent_customers.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_customers.map(c => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${c.id}</code> (${c.username || 'No username'})`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">F14004</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs - Updated with Sequence Fix -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs - Sequence-Based System ✅
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][SequenceNum]</p>
                        ${data.id_formats.recent_orders.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_orders.map(o => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${o}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">01F30P75</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F30</strong> = June 30th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>75</strong> = Sequential number (eliminates duplicates)</p>
                        </div>
                        
                        <!-- Recent Fix Notice -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔧 Recent Fix Applied:</p>
                            <p class="text-xs text-green-600">• Replaced random number with sequence-based system</p>
                            <p class="text-xs text-green-600">• Eliminates "Duplicate entry" constraint violations</p>
                            <p class="text-xs text-green-600">• Sequential: 17F30P75 → 17F30P76 → 17F30P77</p>
                            <p class="text-xs text-green-600">• Robust for concurrent checkout processing</p>
                        </div>
                        
                        <!-- Shipping Codes -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">📦 Shipping Method Codes:</p>
                            <p class="text-xs text-blue-600">• <strong>P</strong> = Customer Pickup • <strong>L</strong> = Local Delivery</p>
                            <p class="text-xs text-blue-600">• <strong>U</strong> = USPS • <strong>F</strong> = FedEx • <strong>X</strong> = UPS</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs (SKUs) - Enhanced Documentation -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs (SKUs) - Complete System
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Primary Format:</strong> ${data.system_info.sku_format}</p>
                        ${data.sample_skus.length > 0 ? 
                            `<p><strong>Current Examples:</strong> ${data.sample_skus.slice(0, 5).map(sku => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${sku}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Examples:</strong> <code class="bg-orange-100 py-0\.5 rounded">WF-TS-001</code>, <code class="bg-orange-100 py-0\.5 rounded">WF-TU-002</code></p>`
                        }
                        
                        <!-- Enhanced SKU Format -->
                        <div class="bg-orange-50 rounded">
                            <p class="font-medium text-orange-700">Enhanced SKU Format (Optional):</p>
                            <p><strong>WF-[CATEGORY]-[GENDER]-[SIZE]-[COLOR]-[NUMBER]</strong></p>
                            <p class="text-xs">Example: <code class="bg-orange-100 py-0\.5 rounded">WF-TS-M-L-BLK-001</code> = WhimsicalFrog T-Shirt, Men's Large, Black, #001</p>
                        </div>
                        
                        <!-- Category Codes -->
                        <div class="text-xs text-orange-500">
                            <p class="font-medium">Category Codes:</p>
                            ${Object.entries(data.category_codes).map(([category, code]) => 
                                `<p>• <strong>${code}</strong> = ${category}</p>`
                            ).join('')}
                        </div>
                        
                        <!-- SKU Generation -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔄 Auto-Generation:</p>
                            <p class="text-xs text-green-600">• SKUs are automatically generated with sequential numbering</p>
                            <p class="text-xs text-green-600">• API: <code>/api/next_sku.php?cat=[CATEGORY]</code></p>
                            <p class="text-xs text-green-600">• Enhanced: <code>&gender=M&size=L&color=Black&enhanced=true</code></p>
                        </div>
                        
                        <!-- Database Integration -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">🗄️ Database Integration:</p>
                            <p class="text-xs text-blue-600">• Primary table: <code>items</code> (SKU as primary key)</p>
                            <p class="text-xs text-blue-600">• Images: <code>item_images</code> (linked via SKU)</p>
                            <p class="text-xs text-blue-600">• Orders: <code>order_items</code> (references SKU)</p>
                            <p class="text-xs text-blue-600">• Migration complete: No legacy ID columns</p>
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> OI[SequentialNumber]</p>
                        ${data.id_formats.recent_order_items.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_order_items.map(oi => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${oi}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">OI001</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>OI</strong> = Order Item prefix</p>
                            <p>• <strong>001</strong> = Sequential 3-digit number (001, 002, 003, etc.)</p>
                            <p class="italic">Simple, clean, and easy to reference!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    `;
}

function _closeSystemConfigModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('systemConfigModal');
    } else {
        const el = document.getElementById('systemConfigModal');
        if (el) { el.classList.remove('show'); el.classList.add('hidden'); }
    }
}

window.openDatabaseMaintenanceModal = function openDatabaseMaintenanceModal() {
    console.log('openDatabaseMaintenanceModal called');
    const modal = document.getElementById('databaseMaintenanceModal');
    if (!modal) {
      console.error('databaseMaintenanceModal element not found!');
      if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
        window.wfNotifications.show('Database Maintenance modal not found. Please refresh the page.', 'error', { title: 'Admin Settings' });
      } else if (window.showError) {
        window.showError('Database Maintenance modal not found. Please refresh the page.');
      } else {
        try { if (typeof window.showNotification === 'function') window.showNotification('Database Maintenance modal not found. Please refresh the page.', 'error', { title: 'Admin Settings' }); } catch(_) { /* no-op */ }
      }
      return;
    }
    console.log('Opening database maintenance modal...');
    if (typeof window.openModal === 'function') {
        window.openModal('databaseMaintenanceModal');
    } else {
        modal.classList.remove('hidden');
        modal.classList.add('show');
    }
    // Hide loading and show connection tab by default
    const dbLoading = document.getElementById('databaseMaintenanceLoading');
    if (dbLoading && dbLoading.classList) dbLoading.classList.add('hidden');
    switchDatabaseTab(document.querySelector('[data-tab="connection"]'), 'connection');
    // Also load the current configuration immediately
    loadCurrentDatabaseConfig();
}

async function _loadDatabaseInformation() {
    const loadingDiv = document.getElementById('databaseMaintenanceLoading');
    const contentDiv = document.getElementById('databaseMaintenanceContent');
    
    // Show loading state
    if (loadingDiv && loadingDiv.classList) loadingDiv.classList.remove('hidden');
    
    try {
        const result = await ApiClient.get('/api/get_database_info.php');
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            if (loadingDiv && loadingDiv.classList) loadingDiv.classList.add('hidden');
            contentDiv.innerHTML = generateDatabaseMaintenanceHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load database information');
        }
    } catch (error) {
        console.error('Error loading database information:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load database information</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button type="button" data-action="retry-load-db-info" class="bg-red-500 text-white rounded hover:bg-red-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateDatabaseMaintenanceHTML(data) {
    return `
        <!-- Database Schema -->
        <div class="bg-purple-50 border-l-4 border-purple-400">
            <h4 class="font-semibold text-purple-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                </svg>
                Database Tables & Structure (${data.total_active} Active + ${data.total_backup} Backup)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                ${Object.entries(data.organized || {}).map(([category, tables]) => {
                    const categoryLabels = {
                        'core_ecommerce': '🛒 Core E-commerce', 
                        'user_management': '👥 User Management', 
                        'inventory_cost': '💰 Inventory & Cost',
                        'product_categories': '🏷️ Product Categories',
                        'room_management': '🏠 Room Management',
                        'email_system': '📧 Email System',
                        'business_config': '⚙️ Business Config',
                        'system_logs': '📄 System Logs',
                        'backup_tables': '🗄️ Backup Tables'
                    };

                    const categoryLabel = categoryLabels[category] || `❓ Unknown Category: ${category}`;

                    return `
                        <div class="bg-white rounded border border-purple-200 p-3">
                            <h5 class="font-semibold text-purple-700">${categoryLabel}</h5>
                            <ul class="text-xs text-purple-600 mt-2 space-y-1">
                                ${tables.map(table => `
                                    <li class="flex justify-between items-center">
                                        <span>${table.name}</span>
                                        <span class="text-purple-500 font-mono">${table.rows} rows</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

// -----------------------------
// Database Maintenance Helpers
// -----------------------------

function getEvent(e) {
    // Normalize event across inline and programmatic invocations
    return e || (typeof window !== 'undefined' ? window.event : undefined);
}

function _showResult(element, success, message) {
    if (!element) return;
    element.className = success 
        ? 'px-3 py-2 bg-green-50 border border-green-200 rounded text-sm'
        : 'px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
    element.innerHTML = message;
    element.classList.remove('hidden');
}

async function scanDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = (evt && evt.target) ? evt.target : document.querySelector('[data-action="scan-db"], #scanDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Scanning...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Scanning PHP files for database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const result = await ApiClient.get('/api/convert_to_centralized_db.php?action=scan&format=json&admin_token=whimsical_admin_2024');
        if (result.success) {
            if (result.needs_conversion > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-yellow-800">⚠️ Files Need Conversion</div>
                        <div class="text-xs space-y-1 text-yellow-700">
                            <div>Total PHP files: ${result.total_files}</div>
                            <div>Files needing conversion: ${result.needs_conversion}</div>
                            <div class="">Files with direct PDO connections:</div>
                            <ul class="list-disc list-inside">
                                ${result.files.slice(0, 10).map(f => `<li>${f}</li>`).join('')}
                                ${result.files.length > 10 ? `<li>... and ${result.files.length - 10} more</li>` : ''}
                            </ul>
                        </div>
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">✅ All Files Use Centralized Database!</div>
                        <div class="text-xs text-green-700">Scanned ${result.total_files} PHP files - no conversion needed</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Scan failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Scan failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '📊 Scan Files';
        }
    }
}

async function convertDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = (evt && evt.target) ? evt.target : document.querySelector('[data-action="convert-db"], #convertDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    // Branded confirmation modal (no native confirm fallback)
    if (typeof window.showConfirmationModal !== 'function') {
        try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {}
        return;
    }
    const ok = await window.showConfirmationModal({
        title: 'Convert DB Connections',
        message: 'This will modify files with direct PDO connections and create backups. Continue?',
        confirmText: 'Convert',
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
    });
    if (!ok) return;
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Converting...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Converting files to use centralized database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const result = await ApiClient.get('/api/convert_to_centralized_db.php?action=convert&format=json&admin_token=whimsical_admin_2024');
        if (result.success) {
            if (result.converted > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">🎉 Conversion Completed!</div>
                        <div class="text-xs space-y-1 text-green-700">
                            <div>Files converted: ${result.converted}</div>
                            <div>Conversion failures: ${result.failed}</div>
                            <div class="">💾 Backups were created for all modified files</div>
                            <div class="text-yellow-700">⚠️ Please test your application to ensure everything works correctly</div>
                        </div>
                        ${result.results.filter(r => r.status === 'converted').length > 0 ? `
                            <details class="">
                                <summary class="cursor-pointer text-green-700 hover:text-green-900">View converted files</summary>
                                <ul class="list-disc list-inside text-xs">
                                    ${result.results.filter(r => r.status === 'converted').map(r => 
                                        `<li>${r.file} (${r.changes} changes)</li>`
                                    ).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-blue-800">ℹ️ No Files Needed Conversion</div>
                        <div class="text-xs text-blue-700">All files are already using centralized database connections</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Conversion failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Conversion failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '🔄 Convert All';
        }
    }
}

function openConversionTool() {
    window.open('/api/convert_to_centralized_db.php?admin_token=whimsical_admin_2024', '_blank');
}

function toggleDatabaseBackupTables() {
    const container = document.getElementById('databaseBackupTablesContainer');
    const icon = document.getElementById('databaseBackupToggleIcon');
    if (!container || !icon) return;
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        container.classList.add('hidden');
        icon.textContent = '▶';
    }
}

async function viewTable(tableName) {
    try {
        const modal = document.getElementById('tableViewModal');
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Loading ${tableName}...`;
        if (content) content.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        if (typeof window.openModal === 'function') {
            window.openModal('tableViewModal');
        } else if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('show');
        }

        const data = await ApiClient.post('/api/db_manager.php', { action: 'query', sql: `SELECT * FROM \`${tableName}\` LIMIT 100` });
        if (data.success && data.data) {
            if (title) title.textContent = `Table: ${tableName} (${data.row_count} records shown, max 100)`;
            if (!Array.isArray(data.data) || data.data.length === 0) {
                if (content) content.innerHTML = '<div class="text-center text-gray-500">Table is empty</div>';
                return;
            }
            const columns = Object.keys(data.data[0]);
            const tableHtml = `
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full bg-white border border-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                ${columns.map(col => `<th class="border-b text-left font-semibold text-gray-700">${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.data.map(row => `
                                <tr class="hover:bg-gray-50">
                                    ${columns.map(col => {
                                        let value = row[col];
                                        if (value === null) value = '<span class="text-gray-400">NULL</span>';
                                        else if (typeof value === 'string' && value.length > 50) value = value.substring(0, 50) + '...';
                                        return `<td class="border-b">${value}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            if (content) content.innerHTML = tableHtml;
        } else {
            if (title) title.textContent = `Error loading ${tableName}`;
            if (content) content.innerHTML = `<div class="text-red-600">Error: ${data.error || 'Failed to load table data'}</div>`;
        }
    } catch (error) {
        console.error('Error viewing table:', error);
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Error loading ${tableName}`;
        if (content) content.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
    }
}

function closeTableViewModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('tableViewModal');
    } else {
        const modal = document.getElementById('tableViewModal');
        if (modal) { modal.classList.remove('show'); modal.classList.add('hidden'); }
    }
}

async function getDatabaseTableCount() {
    try {
        const result = await ApiClient.get('/api/get_database_info.php');
        if (result.success && result.data) {
            return result.data.total_active || 'several';
        }
        return 'several';
    } catch (error) {
        return 'several';
    }
}

async function compactRepairDatabase() {
    const tableCount = await getDatabaseTableCount();
    if (typeof window.showConfirmationModal !== 'function') {
        try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {}
        return;
    }
    const confirmed = await window.showConfirmationModal({
        title: 'Database Compact & Repair',
        subtitle: 'Optimize and repair your database for better performance',
        message: 'This operation will create a safety backup first, then optimize and repair all database tables to improve performance and fix any corruption issues.',
        details: `
            <ul>
                <li>✅ Create automatic safety backup before optimization</li>
                <li>🔧 Optimize ${tableCount} database tables for better performance</li>
                <li>🛠️ Repair any table corruption or fragmentation issues</li>
                <li>⚡ Improve database speed and efficiency</li>
                <li>⏱️ Process typically takes 2-3 minutes</li>
            </ul>
        `,
        icon: '🔧',
        iconType: 'info',
        confirmText: 'Start Optimization',
        cancelText: 'Cancel'
    });
    if (!confirmed) return;

    if (typeof window.showBackupProgressModal === 'function') {
        window.showBackupProgressModal('🔧 Database Compact & Repair', 'database-repair');
    }
    const progressSteps = document.getElementById('backupProgressSteps');
    const progressTitle = document.getElementById('backupProgressTitle');
    const progressSubtitle = document.getElementById('backupProgressSubtitle');
    if (progressTitle) progressTitle.textContent = '🔧 Database Compact & Repair';
    if (progressSubtitle) progressSubtitle.textContent = 'Optimizing and repairing database tables...';

    try {
        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Creating safety backup...</p>
                        <p class="text-xs text-gray-500">Backing up database before optimization</p>
                    </div>
                </div>
            `;
        }
        const backupResult = await ApiClient.post('/api/backup_database.php', { destination: 'cloud' });
        if (!backupResult.success) {
            throw new Error('Failed to create safety backup: ' + (backupResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Optimizing database tables...</p>
                        <p class="text-xs text-gray-500">Running OPTIMIZE and REPAIR operations</p>
                    </div>
                </div>
            `;
        }
        const repairResult = await ApiClient.post('/api/compact_repair_database.php', {});
        if (!repairResult.success) {
            throw new Error('Database optimization failed: ' + (repairResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Database optimization complete</p>
                        <p class="text-xs text-gray-500">${repairResult.tables_processed || 0} tables optimized and repaired</p>
                    </div>
                </div>
            `;
        }

        if (typeof window.showBackupCompletionDetails === 'function') {
            window.showBackupCompletionDetails({
                success: true,
                filename: backupResult.filename,
                filepath: backupResult.filepath,
                size: backupResult.size,
                timestamp: backupResult.timestamp,
                destinations: ['Server'],
                tables_optimized: repairResult.tables_processed || 0,
                operation_type: 'Database Compact & Repair'
            });
        }
    } catch (error) {
        console.error('Database optimization error:', error);
        if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
            window.wfNotifications.show(error.message || 'Database optimization failed', 'error', { title: 'Database Maintenance' });
        } else if (typeof window.showError === 'function') {
            window.showError(error.message || 'Database optimization failed');
        } else { try { if (typeof window.showNotification === 'function') window.showNotification(error.message || 'Database optimization failed', 'error', { title: 'Database Maintenance' }); } catch(_) {}
        }
    }
}

// -----------------------------
// Credentials & SSL Utilities (migrated)
// -----------------------------

function renderResult(div, success, html) {
    if (!div) return;
    const base = 'px-3 py-2 border rounded text-sm';
    if (success) {
        div.className = `${base} bg-green-50 border-green-200`;
    } else {
        div.className = `${base} bg-red-50 border-red-200`;
    }
    div.innerHTML = html;
    div.classList.remove('hidden');
}

async function updateDatabaseConfig(ev) {
    try {
        const resultDiv = document.getElementById('credentialsUpdateResult');
        const button = (ev && ev.target) ? ev.target : document.activeElement;

        const updateData = {
            host: (document.getElementById('newHost') && document.getElementById('newHost').value) || '',
            database: (document.getElementById('newDatabase') && document.getElementById('newDatabase').value) || '',
            username: (document.getElementById('newUsername') && document.getElementById('newUsername').value) || '',
            password: (document.getElementById('newPassword') && document.getElementById('newPassword').value) || '',
            environment: (document.getElementById('environmentSelect') && document.getElementById('environmentSelect').value) || '',
            ssl_enabled: !!(document.getElementById('sslEnabled') && document.getElementById('sslEnabled').checked),
            ssl_cert: (document.getElementById('sslCertPath') && document.getElementById('sslCertPath').value) || ''
        };

        if (!updateData.host || !updateData.database || !updateData.username) {
            renderResult(resultDiv, false, 'Please fill in all required fields');
            return;
        }

        const confirmAction = async () => {
            if (button) { button.disabled = true; button.textContent = '💾 Updating...'; }
            if (resultDiv) {
                resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                resultDiv.innerHTML = '⏳ Updating configuration...';
                resultDiv.classList.remove('hidden');
            }
            try {
                const result = await ApiClient.post('/api/database_maintenance.php?action=update_config&admin_token=whimsical_admin_2024', updateData);
                if (result.success) {
                    renderResult(resultDiv, true, `
                        <div class="font-medium text-green-800">✅ Configuration Updated!</div>
                        <div class="text-xs text-green-700">Backup created: ${result.backup_created}</div>
                        <div class="text-xs text-yellow-700">⚠️ Please refresh the page to use new settings</div>
                    `);
                    setTimeout(() => { try { loadCurrentDatabaseConfig(); } catch(_) {} }, 2000);
                } else {
                    renderResult(resultDiv, false, `Update failed: ${result.message}`);
                }
            } catch (error) {
                renderResult(resultDiv, false, `Network error: ${error.message}`);
            } finally {
                if (button) { button.disabled = false; button.textContent = '💾 Update Credentials'; }
            }
        };

        if (typeof window.showConfirmationModal !== 'function') {
            try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {}
            return;
        }
        window.showConfirmationModal({
            title: 'Update database credentials?',
            message: `A backup will be created automatically for ${updateData.environment} environment(s).` ,
            confirmText: 'Yes, Update',
            cancelText: 'Cancel',
            onConfirm: confirmAction
        });
    } catch (err) {
        console.error('[AdminSettings] updateDatabaseConfig error', err);
    }
}

async function testSSLConnection(ev) {
    try {
        const resultDiv = document.getElementById('sslTestResult');
        const button = (ev && ev.target) ? ev.target : document.activeElement;

        const sslData = {
            host: (document.getElementById('testHost') && document.getElementById('testHost').value) || (document.getElementById('newHost') && document.getElementById('newHost').value) || '',
            database: (document.getElementById('testDatabase') && document.getElementById('testDatabase').value) || (document.getElementById('newDatabase') && document.getElementById('newDatabase').value) || '',
            username: (document.getElementById('testUsername') && document.getElementById('testUsername').value) || (document.getElementById('newUsername') && document.getElementById('newUsername').value) || '',
            password: (document.getElementById('testPassword') && document.getElementById('testPassword').value) || (document.getElementById('newPassword') && document.getElementById('newPassword').value) || '',
            ssl_enabled: true,
            ssl_cert: (document.getElementById('sslCertPath') && document.getElementById('sslCertPath').value) || ''
        };

        if (!sslData.ssl_cert) {
            renderResult(resultDiv, false, 'Please specify SSL certificate path');
            return;
        }

        if (button) { button.disabled = true; button.textContent = '🔄 Testing SSL...'; }
        if (resultDiv) {
            resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
            resultDiv.innerHTML = '⏳ Testing SSL connection...';
            resultDiv.classList.remove('hidden');
        }

        try {
            const result = await ApiClient.post('/api/database_maintenance.php?action=test_connection&admin_token=whimsical_admin_2024', sslData);
            if (result.success) {
                renderResult(resultDiv, true, `
                    <div class="font-medium text-green-800">🔒 SSL Connection Successful!</div>
                    <div class="text-xs space-y-1 text-green-700">
                        <div>SSL Certificate: Valid</div>
                        <div>Encryption: Active</div>
                        <div>MySQL Version: ${(result && result.info && result.info.mysql_version) ? result.info.mysql_version : ''}</div>
                    </div>
                `);
            } else {
                renderResult(resultDiv, false, `SSL connection failed: ${result.message}`);
            }
        } catch (error) {
            renderResult(resultDiv, false, `SSL test error: ${error.message}`);
        } finally {
            if (button) { button.disabled = false; button.textContent = '🔒 Test SSL Connection'; }
        }
    } catch (err) {
        console.error('[AdminSettings] testSSLConnection error', err);
    }
}

 
 // -----------------------------
 // Template Manager: Color & Size Templates (migrated)
 // -----------------------------
 
 // Scroll-lock helper to keep modal-open class consistent across all modals
 function updateModalScrollLock() {
     try {
         // Consider only overlays explicitly marked as visible
         const anyOpen = document.querySelectorAll('.admin-modal-overlay.show').length > 0;
         const html = document.documentElement;
         const body = document.body;
         if (anyOpen) {
             html.classList.add('modal-open');
             body.classList.add('modal-open');
         } else {
             html.classList.remove('modal-open');
             body.classList.remove('modal-open');
         }
     } catch (_) {}
 }
 
 // Template Manager: open/close and tab switching (migrated from inline PHP script)
 function openTemplateManagerModal() {
    const modal = document.getElementById('templateManagerModal');
    if (!modal) return;
    modal.classList.remove('hidden');

    // Load iframe content from data-src attribute
    const iframe = document.getElementById('templateManagerFrame');
    if (iframe && iframe.dataset.src) {
        iframe.src = iframe.dataset.src;
    }

    // Load all tabs' data similar to legacy behavior
    try { if (typeof loadColorTemplates === 'function') loadColorTemplates(); else if (typeof window !== 'undefined' && typeof window.loadColorTemplates === 'function') window.loadColorTemplates(); } catch (_) {}
    try { if (typeof loadSizeTemplates === 'function') loadSizeTemplates(); else if (typeof window !== 'undefined' && typeof window.loadSizeTemplates === 'function') window.loadSizeTemplates(); } catch (_) {}
    try { if (typeof loadCostTemplates === 'function') loadCostTemplates(); else if (typeof window !== 'undefined' && typeof window.loadCostTemplates === 'function') window.loadCostTemplates(); } catch (_) {}
    try { if (typeof loadSuggestionHistory === 'function') loadSuggestionHistory(); else if (typeof window !== 'undefined' && typeof window.loadSuggestionHistory === 'function') window.loadSuggestionHistory(); } catch (_) {}
    try { if (typeof loadEmailTemplates === 'function') loadEmailTemplates(); else if (typeof window !== 'undefined' && typeof window.loadEmailTemplates === 'function') window.loadEmailTemplates(); } catch (_) {}
    updateModalScrollLock();
}

function closeTemplateManagerModal() {
    const modal = document.getElementById('templateManagerModal');
    if (modal) {
        modal.classList.add('hidden');
        updateModalScrollLock();
    }

    // Clear iframe src to stop loading when modal is closed
    const iframe = document.getElementById('templateManagerFrame');
    if (iframe) {
        iframe.src = 'about:blank';
    }
}

function switchTemplateTab(tabName) {
    try {
        // Update tab buttons
        document.querySelectorAll('.css-category-tab').forEach(tab => tab.classList.remove('active'));
        const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');
        // Show/hide content
        document.querySelectorAll('.css-category-content').forEach(content => content.classList.add('hidden'));
        const content = document.getElementById(`${tabName}-tab`);
        if (content) content.classList.remove('hidden');
    } catch (_) {}
}

// Window shims for backward compatibility
 
 // -----------------------------
 // Template Assignment (Email)
 // -----------------------------
 // Compatibility shim: preserve legacy function name
 try {
     if (typeof window !== 'undefined') {
         window.changeTemplateAssignment = function(emailType) {
             try { openTemplateAssignmentModal(String(emailType || '')); } catch (_) {}
         };
     }
 } catch (_) {}

 // Email Template Preview
 async function previewEmailTemplate(templateId) {
     try {
         const id = encodeURIComponent(templateId);
         const data = await ApiClient.get(`/api/email_templates.php?action=preview&template_id=${id}`);
         if (data && data.success) {
             showEmailTemplatePreviewModal(data.preview);
         } else {
             if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to preview template' + (data && data.error ? `: ${data.error}` : ''));
             else console.error('[AdminSettings] Failed to preview template', data);
         }
     } catch (error) {
         console.error('[AdminSettings] previewEmailTemplate error', error);
         if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error previewing email template');
     }
 }

 function showEmailTemplatePreviewModal(preview) {
    const modal = document.getElementById('emailTemplatePreviewModal');
    const iframe = document.getElementById('emailPreviewFrame');
    const subjectSpan = document.getElementById('previewSubject');
    if (!modal || !iframe || !subjectSpan) return;

    // Normalize modal overlay to match unified admin behavior (full viewport, centered)
    try {
        modal.classList.add('admin-modal-overlay', 'wf-overlay-viewport', 'over-header', 'topmost');
        modal.classList.remove('items-start', 'admin-modal-offset-under-header');
    } catch (_) {}

    subjectSpan.textContent = (preview && preview.subject) ? preview.subject : '';
     try {
         const html = (preview && preview.html_content) ? preview.html_content : '';
         const blob = new Blob([html], { type: 'text/html' });
         const url = URL.createObjectURL(blob);
         iframe.src = url;
         // keep reference for cleanup on close
         modal.dataset.previewUrl = url;
     } catch (e) {
         console.warn('[AdminSettings] Failed to create preview blob URL', e);
     }

     modal.classList.remove('hidden');
    try { updateModalScrollLock(); } catch (_) {}
}

 function closeEmailTemplatePreviewModal() {
     const modal = document.getElementById('emailTemplatePreviewModal');
     if (!modal) return;
     const iframe = document.getElementById('emailPreviewFrame');
     const url = modal.dataset.previewUrl;
     if (url) {
         try { URL.revokeObjectURL(url); } catch (_) {}
         delete modal.dataset.previewUrl;
     }
     if (iframe) { try { iframe.src = 'about:blank'; } catch (_) {} }
     modal.classList.add('hidden');
    try { updateModalScrollLock(); } catch (_) {}
}

  // Window shims for email preview
  try {
      if (typeof window !== 'undefined') {
          window.previewEmailTemplate = previewEmailTemplate;
          window.showEmailTemplatePreviewModal = showEmailTemplatePreviewModal;
          window.closeEmailTemplatePreviewModal = closeEmailTemplatePreviewModal;
      }
  } catch (_) {}

  // Removed legacy header-offset positioning helpers (admin-modal-offset-under-header)

  // Delegated handlers for Email Template Preview modal
  document.addEventListener('click', function(ev) {
      const t = ev.target;
      if (!t || !t.closest) return;
      // Preview-specific close button
      if (t.closest('[data-action="close-email-preview-modal"]')) { ev.preventDefault(); closeEmailTemplatePreviewModal(); return; }
      // Generic admin modal close buttons (X)
      if (t.closest('[data-action="close-admin-modal"], .admin-modal-close')) {
          ev.preventDefault();
          const overlay = t.closest('.admin-modal-overlay');
          if (overlay) {
              overlay.classList.add('hidden');
              try { overlay._disposePositioner && overlay._disposePositioner(); } catch (_) {}
              try { updateModalScrollLock(); } catch (_) {}
          }
          return;
      }
      // Backdrop click (overlay area)
      if (t.classList && t.classList.contains('admin-modal-overlay')) {
          t.classList.add('hidden');
          try { t._disposePositioner && t._disposePositioner(); } catch (_) {}
          try { updateModalScrollLock(); } catch (_) {}
          return;
      }
  });

function showTestEmailModal(templateId) {
    let overlay = document.getElementById('testEmailSendModal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'testEmailSendModal';
        overlay.className = 'admin-modal-overlay wf-overlay-viewport over-header topmost hidden';
        overlay.innerHTML = `
          <div class="admin-modal bg-white rounded-lg w-full max-w-lg shadow-xl" role="dialog" aria-modal="true" aria-labelledby="testEmailTitle">
            <div class="flex items-center justify-between p-4 border-b">
              <h3 id="testEmailTitle" class="text-lg font-semibold text-gray-800">Send Test Email</h3>
              <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="p-4 space-y-3">
              <p class="text-sm text-gray-700">Enter an email address to send a test of this template.</p>
              <input id="testEmailInput" type="email" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" placeholder="name@example.com" aria-describedby="testEmailError" aria-invalid="false" />
              <p id="testEmailError" class="form-error hidden"></p>
            </div>
            <div class="flex items-center justify-end gap-2 p-4 border-t bg-gray-50">
              <button type="button" class="px-3 py-2 text-gray-700 hover:text-gray-900" data-action="close-test-email-modal">Cancel</button>
              <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" data-action="confirm-send-test-email">Send Test</button>
            </div>
          </div>`;
        document.body.appendChild(overlay);
    }
    overlay.dataset.templateId = String(templateId || '');
    try { lastFocusedBeforeTestModal = document.activeElement; } catch (_) {}
    overlay.classList.remove('hidden');
    try { updateModalScrollLock(); } catch (_) {}
    // Do not apply header-offset positioning on admin routes
    try {
        const isAdmin = !!document.body && document.body.matches('[data-page^="admin"]');
        if (!isAdmin) {
            setOverlayPosition(overlay);
            overlay._disposePositioner?.();
            overlay._disposePositioner = attachOverlayAutoPosition(overlay);
        }
    } catch(_) {}
    const input = overlay.querySelector('#testEmailInput');
    if (input) { try { input.focus(); } catch (_) {} }
}

  function closeTestEmailModal() {
      const overlay = document.getElementById('testEmailSendModal');
      if (!overlay) return;
      overlay.classList.add('hidden');
      try { updateModalScrollLock(); } catch (_) {}
      try { overlay._disposePositioner && overlay._disposePositioner(); } catch (_) {}
      // restore focus
      try { if (lastFocusedBeforeTestModal && lastFocusedBeforeTestModal.focus) lastFocusedBeforeTestModal.focus(); } catch (_) {}
  }

  async function sendTestEmailTemplate(templateId) {
      const id = String(templateId || '').trim();
      if (!id) {
          console.warn('[AdminSettings] sendTestEmailTemplate: missing template id; falling back to default template');
          // Fallback to a sane default template id so testing can proceed
          const fallbackId = 'order_confirmation';
          if (typeof window !== 'undefined' && typeof window.showTestEmailModal === 'function') {
              return showTestEmailModal(fallbackId);
          }
          if (typeof window !== 'undefined' && typeof window.showError === 'function') {
              window.showError('Missing template id');
          }
          return;
      }
      showTestEmailModal(id);
  }

  async function confirmSendTestEmail() {
      try {
          const overlay = document.getElementById('testEmailSendModal');
          if (!overlay) return;
          let id = String(overlay.dataset.templateId || '').trim();
          const input = overlay.querySelector('#testEmailInput');
          const testEmail = String((input && input.value) || '').trim();
          if (!id) {
              // Fallback default when legacy inline buttons did not provide data-id
              id = 'order_confirmation';
          }
          const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const err = overlay.querySelector('#testEmailError');
          if (!emailRe.test(testEmail)) {
              if (err) { err.textContent = 'Please enter a valid email address.'; err.classList.remove('hidden'); }
              if (input) { input.setAttribute('aria-invalid', 'true'); try { input.focus(); } catch (_) {} }
              if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Please enter a valid email address.');
              return;
          } else {
              if (err) { err.textContent = ''; err.classList.add('hidden'); }
              if (input) input.setAttribute('aria-invalid', 'false');
          }
          const btn = overlay.querySelector('[data-action="confirm-send-test-email"]');
          const prevText = btn ? btn.textContent : '';
          if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Sending...'; }
          if (input) input.disabled = true;
          const data = await ApiClient.post('/api/email_templates.php?action=send_test', { template_id: id, test_email: testEmail });
          if (data && data.success) {
              if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                  window.wfNotifications.show(data.message || 'Test email sent successfully.', 'success', { title: 'Test Email' });
              } else if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') {
                  window.showSuccess(data.message || 'Test email sent successfully.');
              } else if (typeof window !== 'undefined' && typeof window.showNotification === 'function') {
                  window.showNotification(data.message || 'Test email sent successfully.', 'success');
              }
              closeTestEmailModal();
          } else {
              const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to send test email.';
              if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                  window.wfNotifications.show(msg, 'error', { title: 'Test Email' });
              } else if (typeof window !== 'undefined' && typeof window.showError === 'function') {
                  window.showError(msg);
              } else if (typeof window !== 'undefined' && typeof window.showNotification === 'function') {
                  window.showNotification(msg, 'error');
              }
          }
          if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.textContent = prevText; }
          if (input) input.disabled = false;
      } catch (err) {
          console.error('[AdminSettings] confirmSendTestEmail error', err);
          if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
              window.wfNotifications.show('Error sending test email', 'error', { title: 'Test Email' });
          } else if (typeof window !== 'undefined' && typeof window.showError === 'function') {
              window.showError('Error sending test email');
          }
      } finally {
          const overlay = document.getElementById('testEmailSendModal');
          if (overlay) {
              const btn = overlay.querySelector('[data-action="confirm-send-test-email"]');
              const input = overlay.querySelector('#testEmailInput');
              if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); }
              if (input) input.disabled = false;
          }
      }
  }
 
  // Window shim for test email
  try {
      if (typeof window !== 'undefined') {
          window.sendTestEmailTemplate = sendTestEmailTemplate;
          // Back-compat generic name used in another handler block
          window.sendTestEmail = function(tid) { return sendTestEmailTemplate(tid); };
          window.showTestEmailModal = showTestEmailModal;
          window.closeTestEmailModal = closeTestEmailModal;
          window.confirmSendTestEmail = confirmSendTestEmail;
      }
  } catch (_) {}

  // Delegated handlers for Test Email modal
  document.addEventListener('click', function(ev) {
      const t = ev.target;
      if (!t || !t.closest) return;
      // Generic close handler for any admin modal close button
      const genericClose = t.closest('.admin-modal-close,[data-action="close-admin-modal"]');
      if (genericClose) {
          ev.preventDefault();
          const overlay = t.closest('.admin-modal-overlay');
          if (overlay) {
              overlay.classList.add('hidden');
              try { updateModalScrollLock(); } catch (_) {}
          }
          return;
      }
      const closeBtn = t.closest('[data-action="close-test-email-modal"]');
      if (closeBtn) { ev.preventDefault(); closeTestEmailModal(); return; }
      const confirmBtn = t.closest('[data-action="confirm-send-test-email"]');
      if (confirmBtn) { ev.preventDefault(); confirmSendTestEmail(); return; }
      const overlay = document.getElementById('testEmailSendModal');
      if (overlay && t === overlay) { ev.preventDefault(); closeTestEmailModal(); return; }
  });
 
  // -----------------------------
  // Email Template Edit/Create Modal (migrated)
  // -----------------------------
  function ensureEmailTemplateEditModal() {
      let overlay = document.getElementById('emailTemplateEditModal');
      if (overlay) return overlay;
      overlay = document.createElement('div');
      overlay.id = 'emailTemplateEditModal';
      overlay.className = 'admin-modal-overlay wf-overlay-viewport over-header topmost hidden';
      overlay.innerHTML = `
        <div class="admin-modal admin-modal-content admin-modal--lg bg-white rounded-lg flex flex-col shadow-xl" role="dialog" aria-modal="true" aria-labelledby="emailTemplateEditTitle">
          <div class="flex items-center justify-between p-4 border-b">
            <h3 id="emailTemplateEditTitle" class="text-lg font-semibold text-gray-800">Edit Email Template</h3>
            <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Template Name</label>
                <input id="et_name" type="text" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" aria-required="true" aria-describedby="et_name_error" aria-invalid="false" />
                <p id="et_name_error" class="form-error hidden"></p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select id="et_type" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" aria-required="true" aria-describedby="et_type_error" aria-invalid="false">
                  <option value="order_confirmation">Order Confirmation</option>
                  <option value="admin_notification">Admin Notification</option>
                  <option value="welcome">Welcome</option>
                  <option value="password_reset">Password Reset</option>
                  <option value="custom">Custom</option>
                </select>
                <p id="et_type_error" class="form-error hidden"></p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Subject</label>
              <input id="et_subject" type="text" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" aria-required="true" aria-describedby="et_subject_error" aria-invalid="false" />
              <p id="et_subject_error" class="form-error hidden"></p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">HTML Content</label>
              <textarea id="et_html" class="w-full h-56 border border-gray-300 rounded px-3 py-2 font-mono text-sm focus:outline-none focus:ring focus:border-blue-300" aria-required="true" aria-describedby="et_html_error" aria-invalid="false"></textarea>
              <p id="et_html_error" class="form-error hidden"></p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Text Content (optional)</label>
              <textarea id="et_text" class="w-full h-32 border border-gray-300 rounded px-3 py-2 font-mono text-sm focus:outline-none focus:ring focus:border-blue-300"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Description (optional)</label>
              <input id="et_desc" type="text" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" />
            </div>
            <div class="flex items-center gap-2">
              <input id="et_active" type="checkbox" class="h-4 w-4 text-blue-600 border-gray-300 rounded" />
              <label for="et_active" class="text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 p-4 border-t bg-gray-50">
            <button type="button" class="px-3 py-2 text-gray-700 hover:text-gray-900" data-action="close-email-template-modal">Cancel</button>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" data-action="save-email-template">Save</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);
      return overlay;
  }

  function showEmailTemplateEditModal(template = null) {
      const overlay = ensureEmailTemplateEditModal();
      overlay.dataset.templateId = template && template.id ? String(template.id) : '';
      const isEdit = !!(template && template.id);
      const title = overlay.querySelector('#emailTemplateEditTitle');
      if (title) title.textContent = isEdit ? 'Edit Email Template' : 'Create Email Template';
      const name = overlay.querySelector('#et_name');
      const type = overlay.querySelector('#et_type');
      const subject = overlay.querySelector('#et_subject');
      const html = overlay.querySelector('#et_html');
      const text = overlay.querySelector('#et_text');
      const desc = overlay.querySelector('#et_desc');
      const active = overlay.querySelector('#et_active');
      // clear previous errors
      ['et_name','et_type','et_subject','et_html'].forEach(id => {
          const input = overlay.querySelector('#' + id);
          const err = overlay.querySelector('#' + id + '_error');
          if (input) input.setAttribute('aria-invalid','false');
          if (err) { err.textContent = ''; err.classList.add('hidden'); }
      });
      if (name) name.value = (template && template.template_name) || '';
      if (type) type.value = (template && template.template_type) || 'custom';
      if (subject) subject.value = (template && template.subject) || '';
      if (html) html.value = (template && template.html_content) || '';
      if (text) text.value = (template && template.text_content) || '';
      if (desc) desc.value = (template && template.description) || '';
      if (active) active.checked = (template && String(template.is_active) !== '0');
      try { lastFocusedBeforeEditModal = document.activeElement; } catch (_) {}
      overlay.classList.remove('hidden');
      try { updateModalScrollLock(); } catch (_) {}
      if (name) { try { name.focus(); } catch (_) {} }
  }

  function closeEmailTemplateEditModal() {
      const overlay = document.getElementById('emailTemplateEditModal');
      if (!overlay) return;
      overlay.classList.add('hidden');
      try { updateModalScrollLock(); } catch (_) {}
      try { if (lastFocusedBeforeEditModal && lastFocusedBeforeEditModal.focus) lastFocusedBeforeEditModal.focus(); } catch (_) {}
  }

  async function editEmailTemplate(templateId) {
      try {
          const id = String(templateId || '').trim();
          if (!id) { if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Missing template id'); return; }
          const data = await ApiClient.get('/api/email_templates.php', { action: 'get_template', template_id: id });
          if (data && data.success && data.template) {
              showEmailTemplateEditModal(data.template);
          } else {
              if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to load template');
          }
      } catch (err) {
          console.error('[AdminSettings] editEmailTemplate error', err);
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error loading template');
      }
  }

  function createNewEmailTemplate() {
      showEmailTemplateEditModal(null);
  }

  function validateEmailTemplateForm(overlay) {
      const fields = [
          { id: 'et_name', label: 'Template Name' },
          { id: 'et_type', label: 'Type' },
          { id: 'et_subject', label: 'Subject' },
          { id: 'et_html', label: 'HTML Content' }
      ];
      let firstInvalid = null;
      for (const f of fields) {
          const el = overlay.querySelector('#' + f.id);
          const err = overlay.querySelector('#' + f.id + '_error');
          const value = (el && (el.value || '').trim()) || '';
          const invalid = !value;
          if (el) el.setAttribute('aria-invalid', invalid ? 'true' : 'false');
          if (err) {
              if (invalid) { err.textContent = `${f.label} is required.`; err.classList.remove('hidden'); }
              else { err.textContent = ''; err.classList.add('hidden'); }
          }
          if (invalid && !firstInvalid) firstInvalid = el;
      }
      if (firstInvalid) { try { firstInvalid.focus(); } catch (_) {} }
      return !firstInvalid;
  }

  async function saveEmailTemplate() {
      try {
          const overlay = ensureEmailTemplateEditModal();
          const id = String((overlay && overlay.dataset && overlay.dataset.templateId) ? overlay.dataset.templateId : '').trim();
          const _et_name = overlay ? overlay.querySelector('#et_name') : null;
          const _et_type = overlay ? overlay.querySelector('#et_type') : null;
          const _et_subject = overlay ? overlay.querySelector('#et_subject') : null;
          const _et_html = overlay ? overlay.querySelector('#et_html') : null;
          const _et_text = overlay ? overlay.querySelector('#et_text') : null;
          const _et_desc = overlay ? overlay.querySelector('#et_desc') : null;
          const _et_active = overlay ? overlay.querySelector('#et_active') : null;
          const name = _et_name && _et_name.value ? _et_name.value.trim() : '';
          const type = _et_type && _et_type.value ? _et_type.value : '';
          const subject = _et_subject && _et_subject.value ? _et_subject.value.trim() : '';
          const html = _et_html && _et_html.value ? _et_html.value : '';
          const text = _et_text && _et_text.value ? _et_text.value : '';
          const desc = _et_desc && _et_desc.value ? _et_desc.value : '';
          const isActive = !!(_et_active && _et_active.checked);
          if (!validateEmailTemplateForm(overlay)) { if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Please fix the highlighted errors.'); return; }
          const saveBtn = overlay.querySelector('[data-action="save-email-template"]');
          const prevText = saveBtn ? saveBtn.textContent : '';
          if (saveBtn) { saveBtn.disabled = true; saveBtn.setAttribute('aria-busy', 'true'); saveBtn.textContent = 'Saving...'; }
          const payload = {
              template_name: name,
              template_type: type,
              subject,
              html_content: html,
              text_content: text,
              description: desc,
              variables: [],
              is_active: isActive ? 1 : 0
          };
          if (id) payload.template_id = parseInt(id);
          const action = id ? 'update' : 'create';
          const data = await ApiClient.post(`/api/email_templates.php?action=${action}`, payload);
          if (data && data.success) {
              if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') window.showSuccess(id ? 'Template updated' : 'Template created');
              closeEmailTemplateEditModal();
              try { if (typeof loadEmailTemplates === 'function') loadEmailTemplates(); else if (typeof window !== 'undefined' && typeof window.loadEmailTemplates === 'function') window.loadEmailTemplates(); } catch (_) {}
          } else {
              const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to save template';
              if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError(msg);
          }
          if (saveBtn) { saveBtn.disabled = false; saveBtn.removeAttribute('aria-busy'); saveBtn.textContent = prevText; }
      } catch (err) {
          console.error('[AdminSettings] saveEmailTemplate error', err);
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error saving template');
      } finally {
          const overlay = document.getElementById('emailTemplateEditModal');
          if (overlay) {
              const saveBtn = overlay.querySelector('[data-action="save-email-template"]');
              if (saveBtn) { saveBtn.disabled = false; saveBtn.removeAttribute('aria-busy'); }
          }
      }
  }

  async function deleteEmailTemplate(templateId) {
      if (typeof window.showConfirmationModal !== 'function') { try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {} return; }
      const ok = await window.showConfirmationModal({
            title: 'Delete Email Template',
            message: 'Are you sure you want to delete this email template? This action cannot be undone.',
            confirmText: 'Delete',
            confirmStyle: 'danger',
            icon: '⚠️',
            iconType: 'danger'
      });
      if (!ok) return;
      try {
          const id = String(templateId || '').trim();
          if (!id) return;
          const data = await ApiClient.post('/api/email_templates.php?action=delete', { template_id: id });
          if (data && data.success) {
              if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') window.showSuccess('Template deleted');
              try { if (typeof loadEmailTemplates === 'function') loadEmailTemplates(); else if (typeof window !== 'undefined' && typeof window.loadEmailTemplates === 'function') window.loadEmailTemplates(); } catch (_) {}
          } else {
              const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to delete template';
              if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError(msg);
          }
      } catch (err) {
          console.error('[AdminSettings] deleteEmailTemplate error', err);
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error deleting template');
      }
  }

  // Window shims for email template editor
  try {
      if (typeof window !== 'undefined') {
          window.showEmailTemplateEditModal = showEmailTemplateEditModal;
          window.closeEmailTemplateEditModal = closeEmailTemplateEditModal;
          window.saveEmailTemplate = saveEmailTemplate;
          window.editEmailTemplate = editEmailTemplate;
          window.createNewEmailTemplate = createNewEmailTemplate;
          window.deleteEmailTemplate = deleteEmailTemplate;
      }
  } catch (_) {}

  // Delegated handlers for Email Template Edit modal
  document.addEventListener('click', function(ev) {
      const t = ev.target;
      if (!t || !t.closest) return;
      if (t.closest('[data-action="close-email-template-modal"]')) { ev.preventDefault(); closeEmailTemplateEditModal(); return; }
      if (t.closest('[data-action="save-email-template"]')) { ev.preventDefault(); saveEmailTemplate(); return; }
      const overlay = document.getElementById('emailTemplateEditModal');
      if (overlay && t === overlay) { ev.preventDefault(); closeEmailTemplateEditModal(); return; }
  });
 
  // -----------------------------
  // Email Template Assignment Modal
  // -----------------------------
  function ensureTemplateAssignmentModal() {
      let overlay = document.getElementById('templateAssignmentModal');
      if (overlay) return overlay;
      overlay = document.createElement('div');
      overlay.id = 'templateAssignmentModal';
      overlay.className = 'admin-modal-overlay wf-overlay-viewport over-header topmost hidden';
      overlay.innerHTML = `
        <div class="admin-modal bg-white rounded-lg w-full max-w-md shadow-xl" role="dialog" aria-modal="true" aria-labelledby="templateAssignmentTitle">
          <div class="flex items-center justify-between p-4 border-b">
            <h3 id="templateAssignmentTitle" class="text-lg font-semibold text-gray-800">Assign Email Template</h3>
            <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          </div>
          <div class="p-4 space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Email Type</label>
              <input id="assignmentEmailType" type="text" class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-100 text-gray-700" readonly />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Template</label>
              <select id="assignmentTemplateSelect" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300" aria-required="true" aria-describedby="assignmentTemplateError" aria-invalid="false"></select>
              <p id="assignmentTemplateError" class="form-error hidden"></p>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 p-4 border-t bg-gray-50">
            <button type="button" class="px-3 py-2 text-gray-700 hover:text-gray-900" data-action="close-template-assignment">Cancel</button>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" data-action="save-template-assignment">Save</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);
      return overlay;
  }

  async function populateTemplateAssignment(emailType) {
      const overlay = ensureTemplateAssignmentModal();
      const select = overlay.querySelector('#assignmentTemplateSelect');
      const typeInput = overlay.querySelector('#assignmentEmailType');
      if (typeInput) typeInput.value = emailType || '';
      if (!select) return;
      select.innerHTML = '<option value="">Loading...</option>';
      try {
          const [tplData, asgData] = await Promise.all([
              ApiClient.get('/api/email_templates.php', { action: 'get_all' }),
              ApiClient.get('/api/email_templates.php', { action: 'get_assignments' })
          ]);
          const templates = (tplData && tplData.templates) || [];
          const assignments = (asgData && (asgData.assignments || asgData.data || {})) || {};
          const currentId = assignments[emailType] || '';
          // Prefer same-type templates, but include all as fallback
          const sameType = templates.filter(t => String(t.template_type) === String(emailType));
          const options = (sameType.length ? sameType : templates).map(t => ({ id: t.id, name: `${t.template_name} (${t.template_type})` }));
          select.innerHTML = options.length ? options.map(o => `<option value="${o.id}">${o.name}</option>`).join('') : '<option value="">No templates available</option>';
          if (currentId) select.value = String(currentId);
          try { select.focus(); } catch (_) {}
      } catch (err) {
          console.error('[AdminSettings] populateTemplateAssignment error', err);
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error loading templates');
      }
  }

  function openTemplateAssignmentModal(emailType) {
      const overlay = ensureTemplateAssignmentModal();
      overlay.dataset.emailType = String(emailType || '');
      try { lastFocusedBeforeAssignModal = document.activeElement; } catch (_) {}
      overlay.classList.remove('hidden');
      try { updateModalScrollLock(); } catch (_) {}
      populateTemplateAssignment(String(emailType || ''));
  }

  function closeTemplateAssignmentModal() {
      const overlay = document.getElementById('templateAssignmentModal');
      if (!overlay) return;
      overlay.classList.add('hidden');
      try { updateModalScrollLock(); } catch (_) {}
      try { if (lastFocusedBeforeAssignModal && lastFocusedBeforeAssignModal.focus) lastFocusedBeforeAssignModal.focus(); } catch (_) {}
  }

  async function saveTemplateAssignment() {
      const overlay = document.getElementById('templateAssignmentModal');
      const select = document.getElementById('assignmentTemplateSelect');
      if (!overlay || !select) return;
      const emailType = overlay.dataset.emailType;
      const templateId = select.value;
      const err = overlay.querySelector('#assignmentTemplateError');
      if (!emailType) { if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Email type not specified'); return; }
      if (!templateId) {
          if (err) { err.textContent = 'Please select a template.'; err.classList.remove('hidden'); }
          select.setAttribute('aria-invalid', 'true');
          try { select.focus(); } catch (_) {}
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Please select a template');
          return;
      } else {
          if (err) { err.textContent = ''; err.classList.add('hidden'); }
          select.setAttribute('aria-invalid', 'false');
      }
      try {
          const saveBtn = overlay.querySelector('[data-action="save-template-assignment"]');
          const prevText = saveBtn ? saveBtn.textContent : '';
          if (saveBtn) { saveBtn.disabled = true; saveBtn.setAttribute('aria-busy', 'true'); saveBtn.textContent = 'Saving...'; }
          const data = await ApiClient.post('/api/email_templates.php?action=set_assignment', { email_type: emailType, template_id: templateId });
          if (data && data.success) {
              if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') window.showSuccess('Template assignment updated successfully!');
              closeTemplateAssignmentModal();
              try { if (typeof loadEmailTemplates === 'function') loadEmailTemplates(); else if (typeof window !== 'undefined' && typeof window.loadEmailTemplates === 'function') window.loadEmailTemplates(); } catch (_) {}
          } else {
              if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to update assignment' + (data && data.error ? ': ' + data.error : ''));
          }
          if (saveBtn) { saveBtn.disabled = false; saveBtn.removeAttribute('aria-busy'); saveBtn.textContent = prevText; }
      } catch (error) {
          console.error('[AdminSettings] saveTemplateAssignment error', error);
          if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error updating template assignment');
      } finally {
          const overlay = document.getElementById('templateAssignmentModal');
          if (overlay) {
              const saveBtn = overlay.querySelector('[data-action=\"save-template-assignment\"]');
              if (saveBtn) { saveBtn.disabled = false; saveBtn.removeAttribute('aria-busy'); }
          }
      }
  }

  // Window shims for assignment modal
  try {
      if (typeof window !== 'undefined') {
          window.openTemplateAssignmentModal = openTemplateAssignmentModal;
          window.closeTemplateAssignmentModal = closeTemplateAssignmentModal;
          window.saveTemplateAssignment = saveTemplateAssignment;
      }
  } catch (_) {}

  // Delegated handlers for Template Assignment modal and triggers
  document.addEventListener('click', function(ev) {
      const t = ev.target;
      if (!t || !t.closest) return;
      const openBtn = t.closest('[data-action="open-template-assignment"]');
      if (openBtn) { ev.preventDefault(); const type = openBtn.dataset.emailType || openBtn.dataset.type || ''; openTemplateAssignmentModal(type); return; }
      const closeBtn = t.closest('[data-action="close-template-assignment"]');
      if (closeBtn) { ev.preventDefault(); closeTemplateAssignmentModal(); return; }
      const saveBtn = t.closest('[data-action="save-template-assignment"]');
      if (saveBtn) { ev.preventDefault(); saveTemplateAssignment(); return; }
      const overlay = document.getElementById('templateAssignmentModal');
      if (overlay && t === overlay) { ev.preventDefault(); closeTemplateAssignmentModal(); return; }
  });
 
  // -----------------------------
  // Keyboard accessibility for modals
  // Esc: close. Enter: submit (where safe). Ctrl/Cmd+Enter: submit for edit modal.
  // -----------------------------
  document.addEventListener('keydown', function(ev) {
      const key = ev.key;
      if (!key) return;
      // Helper to check visibility (no 'hidden' class)
      const isVisible = (el) => el && !el.classList.contains('hidden');
      const activeTag = (document.activeElement && document.activeElement.tagName) || '';
      const _isTextarea = activeTag === 'TEXTAREA';

      const testOverlay = document.getElementById('testEmailSendModal');
      const editOverlay = document.getElementById('emailTemplateEditModal');
      const assignOverlay = document.getElementById('templateAssignmentModal');
      const previewOverlay = document.getElementById('emailTemplatePreviewModal');

      // Escape closes whichever modal is open
      if (key === 'Escape') {
          if (isVisible(testOverlay)) { ev.preventDefault(); closeTestEmailModal(); return; }
          if (isVisible(editOverlay)) { ev.preventDefault(); closeEmailTemplateEditModal(); return; }
          if (isVisible(assignOverlay)) { ev.preventDefault(); closeTemplateAssignmentModal(); return; }
          if (isVisible(previewOverlay)) { ev.preventDefault(); closeEmailTemplatePreviewModal(); return; }
      }
 
      // Enter behavior
      if (key === 'Enter' && !ev.shiftKey) {
          // Test email: Enter confirms (unless inside textarea; there is no textarea here)
          if (isVisible(testOverlay)) { ev.preventDefault(); confirmSendTestEmail(); return; }
          // Assignment: Enter saves (unless focused on a textarea - none in this modal)
          if (isVisible(assignOverlay)) { ev.preventDefault(); saveTemplateAssignment(); return; }
          // Edit modal: use Ctrl/Cmd+Enter to save to avoid accidental submits while editing textareas
          if (isVisible(editOverlay) && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); saveEmailTemplate(); return; }
      }
  });

  // Focus trap inside open modals (Tab cycles within)
  document.addEventListener('keydown', function(ev) {
      if (ev.key !== 'Tab') return;
      const isVisible = (el) => el && !el.classList.contains('hidden');
      const overlays = [
          document.getElementById('testEmailSendModal'),
          document.getElementById('emailTemplateEditModal'),
          document.getElementById('templateAssignmentModal')
      ];
      const current = overlays.find(isVisible);
      if (!current) return;
      const focusables = current.querySelectorAll(
          'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
      const list = Array.prototype.slice.call(focusables);
      if (!list.length) return;
      const first = list[0];
      const last = list[list.length - 1];
      if (ev.shiftKey) {
          if (document.activeElement === first || !current.contains(document.activeElement)) {
              last.focus();
              ev.preventDefault();
          }
      } else {
          if (document.activeElement === last || !current.contains(document.activeElement)) {
              first.focus();
              ev.preventDefault();
          }
      }
  });
 
  // ---------- Color Templates ----------
 let colorTemplatesCache = [];
 let colorTemplateColorIndex = 0;
 
 async function loadColorTemplates() {
     const loading = document.getElementById('colorTemplatesLoading');
     const list = document.getElementById('colorTemplatesList');
     if (!loading || !list) return;
     if (loading.classList) loading.classList.remove('hidden');
     if (list.classList) list.classList.add('hidden');
     try {
         const data = await ApiClient.get('/api/color_templates.php?action=get_all');
         if (data.success) {
             renderColorTemplates(data.templates);
             loadColorTemplateCategories(data.templates);
         } else {
             throw new Error(data.message || 'Failed to load color templates');
         }
     } catch (error) {
         console.error('Error loading color templates:', error);
         if (list) list.innerHTML = '<div class="text-red-600 text-center">Failed to load color templates</div>';
     } finally {
         if (loading && loading.classList) loading.classList.add('hidden');
         if (list && list.classList) list.classList.remove('hidden');
     }
 }
 
 function loadColorTemplateCategories(templates) {
     const categorySelect = document.getElementById('colorTemplateCategoryFilter');
     if (!categorySelect) return;
     const categories = [...new Set((templates || []).map(t => t.category))].sort();
     categorySelect.innerHTML = '<option value="">All Categories</option>';
     categories.forEach(category => {
         const option = document.createElement('option');
         option.value = category;
         option.textContent = category;
         categorySelect.appendChild(option);
     });
 }
 
 function filterColorTemplates() {
     renderColorTemplates();
 }
 
 function renderColorTemplates(templates = null) {
     const list = document.getElementById('colorTemplatesList');
     if (!list) return;
     if (!templates) {
         templates = colorTemplatesCache || [];
     } else {
         colorTemplatesCache = templates;
         // keep legacy global reference for any remaining consumers
         if (typeof window !== 'undefined') window.colorTemplatesCache = templates;
     }
     const _ctf = document.getElementById('colorTemplateCategoryFilter');
     const selectedCategory = (_ctf && _ctf.value) || '';
     const filtered = selectedCategory ? templates.filter(t => t.category === selectedCategory) : templates;
     if (filtered.length === 0) {
         list.innerHTML = '<div class="text-gray-500 text-center">No color templates found. Create your first template!</div>';
         return;
     }
     list.innerHTML = filtered.map(template => `
         <div class="bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
             <div class="flex items-start justify-between">
                 <div>
                     <h5 class="font-semibold text-gray-800">${template.template_name}</h5>
                     <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                     <div class="flex items-center space-x-4 text-xs text-gray-500">
                         <span class="bg-purple-100 text-purple-800 rounded">${template.category}</span>
                         <span>${template.color_count} colors</span>
                         <span>Created: ${new Date(template.created_at).toLocaleDateString()}</span>
                     </div>
                 </div>
                 <div class="flex space-x-2">
                     <button data-action="tm-edit-color-template" data-id="${template.id}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                     <button data-action="tm-delete-color-template" data-id="${template.id}" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                 </div>
             </div>
             <div class="template-preview" id="colorPreview${template.id}">
                 <div class="text-xs text-gray-500">Loading colors...</div>
             </div>
         </div>
     `).join('');
     filtered.forEach(t => loadColorTemplatePreview(t.id));
 }
 
 async function loadColorTemplatePreview(templateId) {
     try {
         const data = await ApiClient.get(`/api/color_templates.php?action=get_template&template_id=${templateId}`);
         if (data.success && data.template.colors) {
             const el = document.getElementById(`colorPreview${templateId}`);
             if (el) {
                 el.innerHTML = `
                     <div class="flex flex-wrap gap-2">
                         ${data.template.colors.map(color => `
                             <div class="flex items-center space-x-2 bg-gray-50 rounded">
                                 <div class="w-4 h-4 rounded border border-gray-300"></div>
                                 <span class="text-xs text-gray-700">${color.color_name}</span>
                             </div>
                         `).join('')}
                     </div>
                 `;
             }
         }
     } catch (err) {
         console.error('Error loading color template preview:', err);
     }
 }
 
 function createNewColorTemplate() {
     showColorTemplateEditModal();
 }
 
 async function editColorTemplate(templateId) {
     try {
         const data = await ApiClient.get(`/api/color_templates.php?action=get_template&template_id=${templateId}`);
         if (data.success) {
             showColorTemplateEditModal(data.template);
         } else {
             if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to load template: ' + data.message);
             else console.error('Failed to load template:', data.message);
         }
     } catch (err) {
         console.error('Error loading color template:', err);
         if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error loading color template');
     }
 }
 
 async function deleteColorTemplate(templateId) {
    if (typeof window.showConfirmationModal !== 'function') { 
        if (typeof window !== 'undefined' && typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); 
        return; 
    }
   const ok = await window.showConfirmationModal({
          title: 'Delete Color Template',
          message: 'Are you sure you want to delete this color template? This action cannot be undone.',
          confirmText: 'Delete',
          confirmStyle: 'danger',
          icon: '⚠️',
          iconType: 'danger'
   });
   if (!ok) return;
    try {
         const data = await ApiClient.get('/api/color_templates.php', {
             method: 'POST',
             headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
             body: `action=delete_template&template_id=${templateId}`
         });
         if (data.success) {
             if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') window.showSuccess('Color template deleted successfully!');
             loadColorTemplates();
         } else {
             if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to delete template: ' + data.message);
         }
     } catch (err) {
         console.error('Error deleting color template:', err);
         if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error deleting color template');
     }
 }
 
 function createColorTemplateEditModal() {
     const modalHTML = `
         <div id="colorTemplateEditModal" class="admin-modal-overlay hidden" data-action="close-color-template-modal">
            <div class="admin-modal-content content-section">
                <div class="admin-modal-header section-header">
                    <h2 id="colorTemplateEditTitle" class="modal-title">Edit Color Template</h2>
                    <button data-action="close-color-template-modal" class="admin-modal-close wf-admin-nav-button" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    <form id="colorTemplateEditForm" data-action="save-color-template">
                         <input type="hidden" id="colorTemplateId" name="template_id">
                         <div>
                             <h4 class="text-lg font-semibold text-gray-800">Template Details</h4>
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                 <div>
                                     <label class="block text-sm font-medium text-gray-700">Template Name *</label>
                                     <input type="text" id="colorTemplateName" name="template_name" required class="modal-input" placeholder="e.g., T-Shirt Colors">
                                 </div>
                                 <div>
                                     <label class="block text-sm font-medium text-gray-700">Category</label>
                                     <select id="colorTemplateCategory" name="category" class="modal-select">
                                         <option value="General">General</option>
                                         <option value="T-Shirts">T-Shirts</option>
                                         <option value="Tumblers">Tumblers</option>
                                         <option value="Artwork">Artwork</option>
                                         <option value="Sublimation">Sublimation</option>
                                         <option value="Window Wraps">Window Wraps</option>
                                     </select>
                                 </div>
                             </div>
                             <div>
                                 <label class="block text-sm font-medium text-gray-700">Description</label>
                                 <textarea id="colorTemplateDescription" name="description" rows="2" class="modal-input" placeholder="Optional description of this color template"></textarea>
                             </div>
                         </div>
                         <div>
                             <div class="flex items-center justify-between">
                                 <h4 class="text-lg font-semibold text-gray-800">Colors</h4>
                                 <button type="button" data-action="color-template-add-color" class="btn btn-primary">
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                     </svg>
                                     Add Color
                                 </button>
                             </div>
                             <div id="colorTemplateColors" class="space-y-3"></div>
                         </div>
                     </form>
                 </div>
                 <div class="modal-footer">
                     <button type="button" data-action="close-color-template-modal" class="btn btn-secondary">Cancel</button>
                     <button type="submit" form="colorTemplateEditForm" class="btn btn-primary">Save Template</button>
                 </div>
             </div>
         </div>`;
     document.body.insertAdjacentHTML('beforeend', modalHTML);
 }
 
 function showColorTemplateEditModal(template = null) {
     if (!document.getElementById('colorTemplateEditModal')) {
         createColorTemplateEditModal();
     }
     const modal = document.getElementById('colorTemplateEditModal');
     const form = document.getElementById('colorTemplateEditForm');
     const modalTitle = document.getElementById('colorTemplateEditTitle');
     const colorsContainer = document.getElementById('colorTemplateColors');
     if (!modal || !form || !modalTitle || !colorsContainer) return;
     form.reset();
     colorsContainer.innerHTML = '';
     colorTemplateColorIndex = 0;
     if (template) {
         modalTitle.textContent = 'Edit Color Template';
         document.getElementById('colorTemplateId').value = template.id;
         document.getElementById('colorTemplateName').value = template.template_name;
         document.getElementById('colorTemplateDescription').value = template.description || '';
         document.getElementById('colorTemplateCategory').value = template.category || 'General';
         if (template.colors && template.colors.length > 0) {
             template.colors.forEach(color => addColorToTemplate(color));
         }
     } else {
         modalTitle.textContent = 'Create New Color Template';
         document.getElementById('colorTemplateId').value = '';
         addColorToTemplate();
     }
     modal.classList.remove('hidden');
     updateModalScrollLock();
 }
 
 function closeColorTemplateEditModal() {
     const modal = document.getElementById('colorTemplateEditModal');
     if (modal) {
         modal.classList.add('hidden');
         updateModalScrollLock();
     }
 }
 
 function addColorToTemplate(colorData = null) {
     const container = document.getElementById('colorTemplateColors');
     if (!container) return;
     const index = colorTemplateColorIndex++;
     const colorHTML = `
         <div class="color-template-item border border-gray-200 rounded-lg bg-gray-50" data-index="${index}">
             <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                 <div>
                     <label class="block text-sm font-medium text-gray-700">Color Name *</label>
                     <input type="text" name="colors[${index}][color_name]" required class="w-full border border-gray-300 rounded text-sm" placeholder="e.g., Red" value="${colorData?.color_name || ''}">
                 </div>
                 <div>
                     <label class="block text-sm font-medium text-gray-700">Color Code</label>
                     <div class="flex items-center space-x-2">
                         <input type="color" name="colors[${index}][color_code]" class="w-12 h-8 border border-gray-300 rounded cursor-pointer" value="${colorData?.color_code || '#ff0000'}" data-action="color-picker-change" data-index="${index}">
                         <input type="text" name="colors[${index}][color_code_text]" class="flex-1 border border-gray-300 rounded text-sm font-mono" placeholder="#ff0000" value="${colorData?.color_code || '#ff0000'}" data-action="color-text-change" data-index="${index}">
                     </div>
                 </div>
                 <div>
                     <label class="block text-sm font-medium text-gray-700">Display Order</label>
                     <input type="number" name="colors[${index}][display_order]" min="0" class="w-full border border-gray-300 rounded text-sm" value="${colorData?.display_order || index}">
                 </div>
                 <div class="flex items-end">
                     <button type="button" data-action="color-template-remove-color" data-index="${index}" class="bg-red-500 text-white rounded text-sm hover:bg-red-600">Remove</button>
                 </div>
             </div>
         </div>`;
     container.insertAdjacentHTML('beforeend', colorHTML);
     if (colorData?.color_code) updateColorPreview(index, colorData.color_code);
 }
 
 function removeColorFromTemplate(index) {
     const item = document.querySelector(`[data-index="${index}"]`);
     if (item) item.remove();
 }
 
 function updateColorPreview(index, colorValue) {
     const textInput = document.querySelector(`input[name="colors[${index}][color_code_text]"]`);
     if (textInput) textInput.value = colorValue;
 }
 
 function updateColorPicker(index, colorValue) {
     const colorInput = document.querySelector(`input[name="colors[${index}][color_code]"]`);
     if (colorInput && /^#[0-9A-F]{6}$/i.test(colorValue)) colorInput.value = colorValue;
 }
 
 async function saveColorTemplate(event) {
     event.preventDefault();
     const form = event.target;
     const formData = new FormData(form);
     const templateId = formData.get('template_id');
     const isEdit = templateId && templateId !== '';
     const colors = [];
     const colorItems = document.querySelectorAll('.color-template-item');
     colorItems.forEach((item, idx) => {
         const colorName = item.querySelector('input[name*="[color_name]"]')?.value;
         const colorCode = item.querySelector('input[name*="[color_code]"]')?.value;
         const displayOrder = item.querySelector('input[name*="[display_order]"]')?.value;
         if (colorName) {
             colors.push({
                 color_name: colorName,
                 color_code: colorCode || '#000000',
                 display_order: parseInt(displayOrder) || idx
             });
         }
     });
     if (colors.length === 0) {
         if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Please add at least one color to the template');
         return;
     }
     const payload = {
         template_name: formData.get('template_name'),
         description: formData.get('description'),
         category: formData.get('category'),
         colors
     };
     if (isEdit) payload.template_id = parseInt(templateId);
     try {
         const action = isEdit ? 'update_template' : 'create_template';
         const data = await ApiClient.post(`/api/color_templates.php?action=${action}`, payload);
         if (data.success) {
             if (typeof window !== 'undefined' && typeof window.showSuccess === 'function') window.showSuccess(isEdit ? 'Color template updated successfully!' : 'Color template created successfully!');
             closeColorTemplateEditModal();
             loadColorTemplates();
         } else {
             if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Failed to save template: ' + data.message);
         }
     } catch (err) {
         console.error('Error saving color template:', err);
         if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Error saving color template');
     }
 }
 
// Temporary window shims for backward compatibility with inline handlers
if (typeof window !== 'undefined') {
    window.scanDatabaseConnections = scanDatabaseConnections;
    window.convertDatabaseConnections = convertDatabaseConnections;
    window.openConversionTool = openConversionTool;
    window.toggleDatabaseBackupTables = toggleDatabaseBackupTables;
    window.viewTable = viewTable;
    window.closeTableViewModal = closeTableViewModal;
    window.compactRepairDatabase = compactRepairDatabase;
    window.updateDatabaseConfig = updateDatabaseConfig;
    window.testSSLConnection = testSSLConnection;
    // Color Template shims
    window.loadColorTemplates = loadColorTemplates;
    window.renderColorTemplates = renderColorTemplates;
    window.filterColorTemplates = filterColorTemplates;
    window.createNewColorTemplate = createNewColorTemplate;
    window.editColorTemplate = editColorTemplate;
    window.deleteColorTemplate = deleteColorTemplate;
    window.createColorTemplateEditModal = createColorTemplateEditModal;
    window.showColorTemplateEditModal = showColorTemplateEditModal;
    window.closeColorTemplateEditModal = closeColorTemplateEditModal;
    window.addColorToTemplate = addColorToTemplate;
    window.removeColorFromTemplate = removeColorFromTemplate;
    window.updateColorPreview = updateColorPreview;
    window.updateColorPicker = updateColorPicker;
    window.saveColorTemplate = saveColorTemplate;
    // Size Template shims
    window.loadSizeTemplates = (typeof loadSizeTemplates === 'function') ? loadSizeTemplates : (typeof window.loadSizeTemplates === 'function' ? window.loadSizeTemplates : function(){});
    window.renderSizeTemplates = (typeof renderSizeTemplates === 'function') ? renderSizeTemplates : (typeof window.renderSizeTemplates === 'function' ? window.renderSizeTemplates : function(){});
    window.filterSizeTemplates = (typeof filterSizeTemplates === 'function') ? filterSizeTemplates : (typeof window.filterSizeTemplates === 'function' ? window.filterSizeTemplates : function(){});
    window.createNewSizeTemplate = (typeof createNewSizeTemplate === 'function') ? createNewSizeTemplate : (typeof window.createNewSizeTemplate === 'function' ? window.createNewSizeTemplate : function(){});
    window.editSizeTemplate = (typeof editSizeTemplate === 'function') ? editSizeTemplate : (typeof window.editSizeTemplate === 'function' ? window.editSizeTemplate : function(){});
    window.deleteSizeTemplate = (typeof deleteSizeTemplate === 'function') ? deleteSizeTemplate : (typeof window.deleteSizeTemplate === 'function' ? window.deleteSizeTemplate : function(){});
    window.createSizeTemplateEditModal = (typeof createSizeTemplateEditModal === 'function') ? createSizeTemplateEditModal : (typeof window.createSizeTemplateEditModal === 'function' ? window.createSizeTemplateEditModal : function(){});
    window.showSizeTemplateEditModal = (typeof showSizeTemplateEditModal === 'function') ? showSizeTemplateEditModal : (typeof window.showSizeTemplateEditModal === 'function' ? window.showSizeTemplateEditModal : function(){});
    window.closeSizeTemplateEditModal = (typeof closeSizeTemplateEditModal === 'function') ? closeSizeTemplateEditModal : (typeof window.closeSizeTemplateEditModal === 'function' ? window.closeSizeTemplateEditModal : function(){});
    window.addSizeToTemplate = (typeof addSizeToTemplate === 'function') ? addSizeToTemplate : (typeof window.addSizeToTemplate === 'function' ? window.addSizeToTemplate : function(){});
    window.removeSizeFromTemplate = (typeof removeSizeFromTemplate === 'function') ? removeSizeFromTemplate : (typeof window.removeSizeFromTemplate === 'function' ? window.removeSizeFromTemplate : function(){});
    window.saveSizeTemplate = (typeof saveSizeTemplate === 'function') ? saveSizeTemplate : (typeof window.saveSizeTemplate === 'function' ? window.saveSizeTemplate : function(){});
    // AI settings shims
    window.openAISettingsModal = openAISettingsModal;
    window.closeAISettingsModal = closeAISettingsModal;
    window.loadAISettings = loadAISettings;
    window.loadAIProviders = loadAIProviders;
    window.displayAIProviders = displayAIProviders;
    window.toggleSection = toggleSection;
    window.toggleProviderSections = toggleProviderSections;
    window.saveAISettings = saveAISettings;
    window.showAISettingsSuccess = showAISettingsSuccess;
    window.showAISettingsError = showAISettingsError;
    window.testAIProvider = testAIProvider;
    window.loadAllModels = loadAllModels;
    window.loadAllModelsWithSelection = loadAllModelsWithSelection;
    window.getDefaultAIProvider = getDefaultAIProvider;
    window.loadModelsForCurrentProvider = loadModelsForCurrentProvider;
    window.refreshModels = refreshModels;
    window.populateModelDropdown = populateModelDropdown;
    window.populateModelDropdownWithSelection = populateModelDropdownWithSelection;
    window.loadFallbackModels = loadFallbackModels;
    window.loadFallbackModelsForProvider = loadFallbackModelsForProvider;
    window.loadFallbackModelsWithSelection = loadFallbackModelsWithSelection;
    window.loadFallbackModelsForProviderWithSelection = loadFallbackModelsForProviderWithSelection;
    // Content Tone shims
    window.loadContentToneOptions = loadContentToneOptions;
    window.initializeDefaultContentToneOptions = initializeDefaultContentToneOptions;
    window.loadDefaultContentToneOptions = loadDefaultContentToneOptions;
    window.populateContentToneDropdown = populateContentToneDropdown;
    window.manageContentToneOptions = manageContentToneOptions;
    window.showContentToneModal = showContentToneModal;
    window.closeContentToneModal = closeContentToneModal;
    window.displayContentToneOptions = displayContentToneOptions;
    window.addContentToneOption = addContentToneOption;
    window.updateContentToneOption = updateContentToneOption;
    window.removeContentToneOption = removeContentToneOption;
    window.saveContentToneOptions = saveContentToneOptions;
    window.saveContentToneOption = saveContentToneOption;
    window.deleteContentToneOptionFromDB = deleteContentToneOptionFromDB;
    // Brand Voice shims
    window.loadBrandVoiceOptions = loadBrandVoiceOptions;
    window.initializeDefaultBrandVoiceOptions = initializeDefaultBrandVoiceOptions;
    window.loadDefaultBrandVoiceOptions = loadDefaultBrandVoiceOptions;
    window.populateBrandVoiceDropdown = populateBrandVoiceDropdown;
    window.manageBrandVoiceOptions = manageBrandVoiceOptions;
    window.showBrandVoiceModal = showBrandVoiceModal;
    window.closeBrandVoiceModal = closeBrandVoiceModal;
    window.displayBrandVoiceOptions = displayBrandVoiceOptions;
    window.addBrandVoiceOption = addBrandVoiceOption;
    window.updateBrandVoiceOption = updateBrandVoiceOption;
    window.removeBrandVoiceOption = removeBrandVoiceOption;
    window.saveBrandVoiceOptions = saveBrandVoiceOptions;
    window.saveBrandVoiceOption = saveBrandVoiceOption;
    window.deleteBrandVoiceOptionFromDB = deleteBrandVoiceOptionFromDB;
}

// -----------------------------
// Delegated Listeners (Progressive Migration)
// -----------------------------

let WF_AdminSettingsListenersInitialized = false;

// Performance guard: reduce heavy work for partial/minimal renders
function __wfIsAdminSettingsLightMode() {
    try {
        const params = new URLSearchParams(window.location.search || '');
        if (params.get('wf_noscripts') === '1') return true;
        if (params.get('wf_minimal') === '1') return true;
        if (params.has('wf_section') && params.get('wf_section')) return true;
        if (window.WF_ADMIN_LIGHT === 1 || window.WF_ADMIN_LIGHT === true) return true;
        // Default to light mode on Admin Settings unless explicitly overridden
        try {
            const body = document.body;
            const dp = body && body.getAttribute ? body.getAttribute('data-page') : '';
            const onSettings = !!(dp && (dp === 'admin/settings' || dp === 'admin')) || !!document.querySelector('.settings-page');
            if (onSettings && params.get('wf_full') !== '1') return true;
        } catch (_) {}
        return false;
    } catch (_) { return false; }
}

function __wfIsOnAdminSettingsPage() {
    try {
        const body = document.body;
        const dp = body && body.getAttribute ? body.getAttribute('data-page') : '';
        if (dp && (dp === 'admin/settings' || dp === 'admin')) return true;
        // Fallback: presence of .settings-page container
        return !!document.querySelector('.settings-page');
    } catch (_) { return true; }
}

function tagInlineHandlersForMigration(root = document) {
    // Add data-action tags based on existing inline onclick attributes to ease removal later
    try {
        const localRoot = (root && root.nodeType)
            ? (root instanceof Document ? (document.querySelector('.settings-page') || document) : root)
            : (document.querySelector('.settings-page') || document);
        const mappings = [
            { contains: 'scanDatabaseConnections', action: 'scan-db' },
            { contains: 'convertDatabaseConnections', action: 'convert-db' },
            { contains: 'openConversionTool', action: 'open-conversion-tool' },
            { contains: 'compactRepairDatabase', action: 'compact-repair' },
            { contains: 'toggleDatabaseBackupTables', action: 'toggle-backup-tables' },
            { contains: 'closeTableViewModal', action: 'close-table-view' },
            { contains: 'updateDatabaseConfig', action: 'update-db-config' },
            { contains: 'testSSLConnection', action: 'test-ssl' },
            // AI Settings modal & helpers
            { contains: 'closeAISettingsModal', action: 'ai-close-settings' },
            { contains: 'saveAISettings', action: 'ai-save-settings' },
            { contains: 'testAIProvider', action: 'ai-test-provider' },
            { contains: 'refreshModels', action: 'ai-refresh-models' },
            { contains: 'toggleSection', action: 'ai-toggle-section' },
            { contains: 'manageBrandVoiceOptions', action: 'ai-manage-brand-voice' },
            { contains: 'manageContentToneOptions', action: 'ai-manage-content-tone' },
            // Content Tone modal inline handlers
            { contains: 'closeContentToneModal', action: 'content-tone-close' },
            { contains: 'addContentToneOption', action: 'content-tone-add' },
            { contains: 'saveContentToneOptions', action: 'content-tone-save' },
            // Brand Voice modal inline handlers
            { contains: 'closeBrandVoiceModal', action: 'brand-voice-close' },
            { contains: 'addBrandVoiceOption', action: 'brand-voice-add' },
            { contains: 'saveBrandVoiceOptions', action: 'brand-voice-save' }
        ];
        const clickable = localRoot.querySelectorAll('[onclick], [onchange]');
        clickable.forEach(el => {
            const code = (el.getAttribute('onclick') || el.getAttribute('onchange') || '').toString();
            for (const map of mappings) {
                if (code.includes(map.contains)) {
                    if (!el.dataset.action) el.dataset.action = map.action;
                }
            }
            // Special handling: viewTable('<tableName>') -> data-action="view-table" + data-table
            if (code.includes('viewTable(')) {
                if (!el.dataset.action) el.dataset.action = 'view-table';
                try {
                    const m = code.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    const table = (m && (m[1] || m[2] || m[3] || '')).toString().trim().replace(/^`|`$/g, '').replace(/^\"|\"$/g, '').replace(/^'|'$/g, '');
                    if (table && !el.dataset.table) el.dataset.table = table;
                } catch (_) {}
            }
            // Extract arguments for Content Tone updates/removals
            if (code.includes('updateContentToneOption(')) {
                if (!el.dataset.action) el.dataset.action = 'content-tone-update';
                try {
                    const m = code.match(/updateContentToneOption\((\d+)\s*,\s*'([^']+)'/);
                    if (m) {
                        el.dataset.index = m[1];
                        el.dataset.field = m[2];
                    }
                } catch (_) {}
            }
            if (code.includes('removeContentToneOption(')) {
                if (!el.dataset.action) el.dataset.action = 'content-tone-remove';
                try {
                    const m = code.match(/removeContentToneOption\((\d+)\)/);
                    if (m) el.dataset.index = m[1];
                } catch (_) {}
            }
            // Extract arguments for Brand Voice updates/removals
            if (code.includes('updateBrandVoiceOption(')) {
                if (!el.dataset.action) el.dataset.action = 'brand-voice-update';
                try {
                    const m = code.match(/updateBrandVoiceOption\((\d+)\s*,\s*'([^']+)'/);
                    if (m) {
                        el.dataset.index = m[1];
                        el.dataset.field = m[2];
                    }
                } catch (_) {}
            }
            if (code.includes('removeBrandVoiceOption(')) {
                if (!el.dataset.action) el.dataset.action = 'brand-voice-remove';
                try {
                    const m = code.match(/removeBrandVoiceOption\((\d+)\)/);
                    if (m) el.dataset.index = m[1];
                } catch (_) {}
            }
        });
    } catch (e) {
        console.debug('[AdminSettings] tagInlineHandlersForMigration error', e);
    }
}

function stripInlineHandlersForMigration(root = document.querySelector('.settings-page') || document) {
    try {
        const selectors = [
            '[onclick*="scanDatabaseConnections"]',
            '[onclick*="convertDatabaseConnections"]',
            '[onclick*="openConversionTool"]',
            '[onclick*="compactRepairDatabase"]',
            '[onclick*="toggleDatabaseBackupTables"]',
            '[onclick*="closeTableViewModal"]',
            '[onclick*="viewTable("]',
            '[onclick*="updateDatabaseConfig"]',
            '[onclick*="testSSLConnection"]',
            // AI Settings & sections
            '[onclick*="closeAISettingsModal"]',
            '[onclick*="saveAISettings"]',
            '[onclick*="testAIProvider"]',
            '[onclick*="refreshModels"]',
            '[onclick*="toggleSection"]',
            // Content Tone modal
            '[onclick*="closeContentToneModal"]',
            '[onclick*="addContentToneOption"]',
            '[onclick*="saveContentToneOptions"]',
            '[onclick*="updateContentToneOption"]',
            '[onclick*="removeContentToneOption"]',
            // Brand Voice modal
            '[onclick*="closeBrandVoiceModal"]',
            '[onclick*="addBrandVoiceOption"]',
            '[onclick*="saveBrandVoiceOptions"]',
            '[onclick*="updateBrandVoiceOption"]',
            '[onclick*="removeBrandVoiceOption"]'
        ];
        root.querySelectorAll(selectors.join(',')).forEach(el => {
            // Preserve original inline handler for debugging/rollback visibility
            if (!el.dataset.onclickLegacy) {
                try { el.dataset.onclickLegacy = (el.getAttribute('onclick') || ''); } catch (_) {}
            }
            try { el.removeAttribute('onclick'); } catch (_) {}
        });
    } catch (e) {
        console.debug('[AdminSettings] stripInlineHandlersForMigration error', e);
    }
}

function initAdminSettingsDelegatedListeners() {
    if (WF_AdminSettingsListenersInitialized) return;
    WF_AdminSettingsListenersInitialized = true;

    // Tag existing inline handlers for smoother migration (opt-in only, and skip in light mode)
    if (__wfMigrationEnabled() && !__wfIsAdminSettingsLightMode()) {
        const runTagAndStrip = () => { try { tagInlineHandlersForMigration(); stripInlineHandlersForMigration(); } catch(_) {} };
        const schedule = () => {
            if ('requestIdleCallback' in window) {
                try { window.requestIdleCallback(runTagAndStrip, { timeout: 300 }); } catch(_) { setTimeout(runTagAndStrip, 0); }
            } else { setTimeout(runTagAndStrip, 0); }
        };
        if (document.readyState !== 'loading') schedule();
        else document.addEventListener('DOMContentLoaded', schedule, { once: true });
    }
    
    // Initialize SSL option visibility on load
    initSSLHandlers();
    
    // Observe future DOM changes (opt-in only, limited to settings-page, skip in light mode)
    if (__wfMigrationEnabled() && !__wfIsAdminSettingsLightMode()) {
        try {
            const obsRoot = document.querySelector('.settings-page');
            if (obsRoot) {
                let __wfMigLast = 0;
                const observer = new MutationObserver((mutations) => {
                    const now = Date.now();
                    if ((now - __wfMigLast) < 150) return;
                    __wfMigLast = now;
                    for (const m of mutations) {
                        if (m.type === 'childList') {
                            for (const node of m.addedNodes) {
                                if (node && node.nodeType === 1) {
                                    try { tagInlineHandlersForMigration(node); } catch(_) {}
                                    try { stripInlineHandlersForMigration(node); } catch(_) {}
                                    try { initSSLHandlers(node); } catch(_) {}
                                }
                            }
                        } else if (m.type === 'attributes' && m.attributeName === 'onclick') {
                            try { tagInlineHandlersForMigration(m.target); } catch(_) {}
                            try { stripInlineHandlersForMigration(m.target); } catch(_) {}
                        }
                    }
                });
                observer.observe(obsRoot, { childList: true, subtree: true, attributes: true, attributeFilter: ['onclick'] });
            }
        } catch (err) {
            console.debug('[AdminSettings] MutationObserver unavailable', err);
        }
    }

    // Delegated change handler (SSL checkbox)
    document.addEventListener('change', (e) => {
        const target = e.target;
        if (target && target.matches && target.matches('#sslEnabled')) {
            const sslOptions = document.getElementById('sslOptions');
            if (sslOptions) {
                if (target.checked) sslOptions.classList.remove('hidden');
                else sslOptions.classList.add('hidden');
            }
        }

        // AI Provider radio change -> toggle provider sections
        if (target && target.matches && target.matches('input[name="ai_provider"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.toggleProviderSections === 'function') {
                    window.toggleProviderSections();
                } else if (typeof toggleProviderSections === 'function') {
                    toggleProviderSections();
                }
            } catch (_) {}
        }

        // Content Tone inline input updates
        const ctUpdate = target && target.closest && target.closest('[data-action="content-tone-update"]');
        if (ctUpdate || (target && target.dataset && target.dataset.action === 'content-tone-update')) {
            const el = ctUpdate || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const field = el.dataset.field || 'name';
            const val = target.value;
            if (!isNaN(idx)) {
                const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
                if (fn) fn(idx, field, val);
            }
        }

        // Brand Voice inline input updates
        const bvUpdate = target && target.closest && target.closest('[data-action="brand-voice-update"]');
        if (bvUpdate || (target && target.dataset && target.dataset.action === 'brand-voice-update')) {
            const el = bvUpdate || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const field = el.dataset.field || 'name';
            const val = target.value;
            if (!isNaN(idx)) {
                const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
                if (fn) fn(idx, field, val);
            }
        }

        // Color Template: sync color picker -> text input
        const colorPicker = target && target.closest && target.closest('[data-action="color-picker-change"]');
        if (colorPicker || (target && target.dataset && target.dataset.action === 'color-picker-change')) {
            const el = colorPicker || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const val = target.value;
            if (!isNaN(idx)) {
                try { if (typeof window !== 'undefined' && typeof window.updateColorPreview === 'function') window.updateColorPreview(idx, val); else if (typeof updateColorPreview === 'function') updateColorPreview(idx, val); } catch (_) {}
            }
        }

        // Color Template: sync text input -> color picker
        const colorText = target && target.closest && target.closest('[data-action="color-text-change"]');
        if (colorText || (target && target.dataset && target.dataset.action === 'color-text-change')) {
            const el = colorText || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const val = target.value;
            if (!isNaN(idx)) {
                try { if (typeof window !== 'undefined' && typeof window.updateColorPicker === 'function') window.updateColorPicker(idx, val); else if (typeof updateColorPicker === 'function') updateColorPicker(idx, val); } catch (_) {}
            }
        }

        // Global CSS Rules: color input -> sync paired text input
        if (target && target.matches && target.matches('[data-action="css-rule-color-change"]')) {
            const ruleId = target.dataset.ruleId;
            try {
                if (typeof window !== 'undefined' && typeof window.updateColorValue === 'function') {
                    window.updateColorValue(target, ruleId);
                }
                // Persist the rule change as well
                const value = target.value;
                if (typeof window !== 'undefined' && typeof window.updateCSSRule === 'function') {
                    window.updateCSSRule(ruleId, value);
                }
            } catch (_) {}
            return;
        }

        // Global CSS Rules: text/select change -> persist change via API
        if (target && target.matches && target.matches('[data-action="css-rule-change"]')) {
            const ruleId = target.dataset.ruleId;
            const value = target.value;
            try {
                if (typeof window !== 'undefined' && typeof window.updateCSSRule === 'function') {
                    window.updateCSSRule(ruleId, value);
                }
            } catch (_) {}
            return;
        }

        // Global CSS Variables: update server-side variable via API
        if (target && target.matches && target.matches('[data-action="css-variable-change"]')) {
            const name = target.dataset.name;
            const category = target.dataset.category;
            const value = target.value;
            try { if (typeof window !== 'undefined' && typeof window.updateCSSVariable === 'function') window.updateCSSVariable(name, value, category); } catch (_) {}
            return;
        }

        // Dashboard: section width selector
        if (target && target.matches && target.matches('[data-action="dashboard-section-width-change"]')) {
            const sectionKey = target.dataset.sectionKey;
            const value = target.value;
            try {
                if (typeof window !== 'undefined' && typeof window.updateSectionWidth === 'function') {
                    window.updateSectionWidth(sectionKey, value);
                } else if (typeof updateSectionWidth === 'function') {
                    updateSectionWidth(sectionKey, value);
                }
            } catch (_) {}
            return;
        }

        // Generic: sync this field value to another field by id
        if (target && target.matches && target.matches('[data-action="sync-to"]')) {
            const id = target.dataset.targetId;
            const el = document.getElementById(id);
            if (el) { try { el.value = target.value; } catch(_){} }
            return;
        }

        // Template Manager: filters
        if (target && (target.matches && target.matches('[data-action="tm-filter-color-templates"]'))) {
            try { if (typeof window !== 'undefined' && typeof window.filterColorTemplates === 'function') window.filterColorTemplates(); else if (typeof filterColorTemplates === 'function') filterColorTemplates(); } catch (_) {}
            return;
        }
        if (target && (target.matches && target.matches('[data-action="tm-filter-size-templates"]'))) {
            try { if (typeof window !== 'undefined' && typeof window.filterSizeTemplates === 'function') window.filterSizeTemplates(); else if (typeof filterSizeTemplates === 'function') filterSizeTemplates(); } catch (_) {}
            return;
        }

        // Global Colors/Sizes: filter selects
        if (target && target.matches && target.matches('[data-action="filter-color-category"]')) {
            try { if (typeof window !== 'undefined' && typeof window.filterColorsByCategory === 'function') window.filterColorsByCategory(); } catch(_) {}
            return;
        }
        if (target && target.matches && target.matches('[data-action="filter-size-category"]')) {
            try { if (typeof window !== 'undefined' && typeof window.filterSizesByCategory === 'function') window.filterSizesByCategory(); } catch(_) {}
            return;
        }
    }, true);

    // Delegated input handler additions for Start Over confirmation input
    document.addEventListener('input', (e) => {
        const target = e.target;
        if (!target || !target.matches) return;
        if (target.matches('[data-action="startover-input"]')) {
            try { if (typeof window !== 'undefined' && typeof window.checkStartOverConfirmation === 'function') window.checkStartOverConfirmation(); else if (typeof checkStartOverConfirmation === 'function') checkStartOverConfirmation(); } catch (_) {}
            return;
        }
    }, true);

    // Delegated change handler (selects, etc.)
    document.addEventListener('change', (e) => {
        const target = e.target;
        if (!target || !target.matches) return;

        if (target.matches('[data-action="tm-filter-color-templates"]')) {
            try { filterColorTemplates(); } catch (_) {}
            return;
        }

        if (target.matches('[data-action="tm-filter-size-templates"]')) {
            try { filterSizeTemplates(); } catch (_) {}
            return;
        }

        // Database Tools: rows-per-page changed
        if (target.matches('[data-action="db-change-rows-per-page"]')) {
            try {
                if (typeof window !== 'undefined' && typeof window.changeRowsPerPage === 'function') {
                    window.changeRowsPerPage();
                } else if (typeof changeRowsPerPage === 'function') {
                    changeRowsPerPage();
                }
            } catch (_) {}
            return;
        }

        // Receipt Settings: update message field (title/content)
        if (target.matches('[data-action="receipt-update-field"]')) {
            const id = parseInt(target.dataset.messageId, 10);
            const field = target.dataset.field;
            const value = target.value;
            try {
                if (typeof window !== 'undefined' && typeof window.updateMessageField === 'function') {
                    window.updateMessageField(id, field, value);
                }
            } catch (_) {}
            return;
        }
    }, true);

    // Delegated submit handler (forms)
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form || !form.matches) return;

        // Save Room Settings
        if (form.matches('[data-action="save-room-settings"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.saveRoomSettings === 'function') {
                    window.saveRoomSettings(e);
                } else if (typeof saveRoomSettings === 'function') {
                    saveRoomSettings(e);
                }
            } catch (_) {}
        }

        // Save Color Template (migrating from onsubmit="saveColorTemplate(event)")
        if (form.matches('[data-action="save-color-template"]')) {
            e.preventDefault();
            try { saveColorTemplate(e); } catch (_) {}
        }

        // Save Size Template (migrating from onsubmit="saveSizeTemplate(event)")
        if (form.matches('[data-action="save-size-template"]')) {
            e.preventDefault();
            try { saveSizeTemplate(e); } catch (_) {}
        }

        // Global Genders: save
        if (form.matches('[data-action="save-global-gender"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveGlobalGender === 'function') window.saveGlobalGender(e); } catch(_) {}
        }
        // Global Colors: save
        if (form.matches('[data-action="save-global-color"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveGlobalColor === 'function') window.saveGlobalColor(e); } catch(_) {}
        }
        // Global Sizes: save
        if (form.matches('[data-action="save-global-size"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveGlobalSize === 'function') window.saveGlobalSize(e); } catch(_) {}
        }
    }, true);
    
    // -----------------------------
    // Background Manager (migrated)
    // -----------------------------
    const getBgModal = () => document.getElementById('backgroundManagerModal');
    const bgEls = () => {
        const modal = getBgModal();
        return {
            modal,
            roomSelect: modal ? modal.querySelector('#backgroundRoomSelect') : null,
            nameInput: modal ? modal.querySelector('#backgroundName') : null,
            fileInput: modal ? modal.querySelector('#backgroundFile') : null,
            list: modal ? modal.querySelector('#backgroundsList') : null,
            info: modal ? modal.querySelector('#currentBackgroundInfo') : null,
            preview: modal ? modal.querySelector('#currentBackgroundPreview') : null,
        };
    };
    const bgNotify = {
        ok: (m) => { try { if (typeof window.showSuccess === 'function') window.showSuccess(m); else if (typeof window.showNotification === 'function') window.showNotification(m, 'success'); } catch(_) {} },
        err: (m) => { try { if (typeof window.showError === 'function') window.showError(m); else if (typeof window.showNotification === 'function') window.showNotification(m, 'error'); } catch(_) {} },
    };
    const imgUrlFor = (bg) => {
        if (!bg) return '';
        const f = bg.webp_filename || bg.image_filename || '';
        if (!f) return '';
        // Images are stored under /images/ per backgrounds API comments
        return `/images/${encodeURIComponent(f)}`;
    };
    
    async function loadRoomOptionsIntoSelect() {
        const { roomSelect } = bgEls();
        if (!roomSelect) return;
        try {
            const data = await ApiClient.get('/api/get_room_data.php');
            const roomNumberMapping = (data && data.data && (data.data.roomTypeMapping || data.data.validRooms)) || {};
            const seen = new Set(Array.from(roomSelect.options).map(o => String(o.value)));
            const entries = Array.isArray(roomNumberMapping) ? roomNumberMapping.map(v => [v, v]) : Object.entries(roomNumberMapping);
            for (const [value, label] of entries) {
                const v = String(value);
                if (seen.has(v)) continue;
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = String(label || value);
                roomSelect.appendChild(opt);
                seen.add(v);
            }
        } catch (err) {
            console.warn('[BackgroundManager] Failed to load rooms', err);
        }
    }
    
    async function fetchBackgrounds(room) {
        return ApiClient.get('/api/backgrounds.php', { room });
    }
    async function fetchActiveBackground(room) {
        return ApiClient.get('/api/backgrounds.php', { room, active_only: true });
    }
    
    function renderBackgroundList(room, items) {
        const { list } = bgEls();
        if (!list) return;
        if (!items || !items.length) {
            list.innerHTML = '<div class="text-sm text-gray-500">No backgrounds yet.</div>';
            return;
        }
        list.innerHTML = items.map(b => {
            const url = imgUrlFor(b);
            const safeName = (b.background_name || '').replace(/"/g, '&quot;');
            const isActive = String(b.is_active) === '1' || b.is_active === 1;
            return `
              <div class="border rounded p-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  ${url ? `<img src="${url}" alt="${safeName}" class="w-12 h-12 object-cover rounded">` : ''}
                  <div>
                    <div class="font-medium">${safeName}${isActive ? ' <span class="text-xs text-green-600">(Active)</span>' : ''}</div>
                    <div class="text-xs text-gray-500">#${b.id}</div>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  ${url ? `<button class="px-2 py-1 text-blue-600" data-action="preview-background" data-image-url="${url}" data-background-name="${safeName}">Preview</button>` : ''}
                  <button class="px-2 py-1 text-green-600" data-action="apply-background" data-room="${room}" data-background-id="${b.id}">Apply</button>
                  <button class="px-2 py-1 text-red-600" data-action="delete-background" data-background-id="${b.id}" data-background-name="${safeName}">Delete</button>
                </div>
              </div>`;
        }).join('');
    }
    
    function renderActiveBackgroundInfo(room, bg) {
        const { info, preview } = bgEls();
        if (info) {
            if (bg && bg.background) bg = bg.background; // handle active_only response
            if (bg && bg.id) {
                info.textContent = `Active for ${room}: ${bg.background_name} (#${bg.id})`;
            } else {
                info.textContent = 'No active background set.';
            }
        }
        if (preview) {
            const url = imgUrlFor(bg && (bg.background || bg));
            if (url) {
                preview.innerHTML = `<img src="${url}" alt="Active Background" class="max-w-full h-auto">`;
            } else {
                preview.innerHTML = '<div class="text-gray-400 text-center p-6">No preview available</div>';
            }
        }
    }
    
    async function refreshBackgroundsUI(room) {
        const r = room || (bgEls().roomSelect ? bgEls().roomSelect.value : 'landing');
        try {
            const [listRes, activeRes] = await Promise.all([
                fetchBackgrounds(r),
                fetchActiveBackground(r)
            ]);
            const items = (listRes && (listRes.backgrounds || [])) || [];
            renderBackgroundList(r, items);
            renderActiveBackgroundInfo(r, activeRes && (activeRes.background || activeRes));
        } catch (err) {
            console.error('[BackgroundManager] refresh error', err);
            const { list, info } = bgEls();
            if (list) list.innerHTML = '<div class="text-red-600 text-sm">Failed to load backgrounds.</div>';
            if (info) info.textContent = 'Failed to load active background.';
        }
    }
    
    function openBackgroundManagerModal(initialRoom) {
        const { modal, roomSelect } = bgEls();
        if (!modal) return;
        modal.classList.remove('hidden');
        try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
        loadRoomOptionsIntoSelect().then(() => {
            if (roomSelect && initialRoom) roomSelect.value = initialRoom;
            refreshBackgroundsUI(roomSelect ? roomSelect.value : 'landing');
        });
    }
    function closeBackgroundManagerModal() {
        const { modal } = bgEls();
        if (!modal) return;
        modal.classList.add('hidden');
        try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
    }
    
    async function applyBackground(room, backgroundId) {
        if (!room || backgroundId == null) return;
        if (typeof window.showConfirmationModal !== 'function') { try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {} return; }
        { const ok = await window.showConfirmationModal({ title: 'Apply Background', message: 'Apply this background?', confirmText: 'Apply', confirmStyle: 'confirm', icon: 'ℹ️', iconType: 'info' }); if (!ok) return; }
        try {
            const data = await ApiClient.post('/api/backgrounds.php', { action: 'apply', room, background_id: backgroundId });
            if (data && data.success) {
                bgNotify.ok(data.message || 'Background applied');
                refreshBackgroundsUI(room);
            } else {
                bgNotify.err((data && (data.error || data.message)) || 'Failed to apply background');
            }
        } catch (err) {
            console.error('[BackgroundManager] apply error', err);
            bgNotify.err('Error applying background');
        }
    }
    async function deleteBackground(backgroundId, name) {
        if (backgroundId == null) return;
        if (typeof window.showConfirmationModal !== 'function') { try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {} return; }
        { const ok = await window.showConfirmationModal({ title: 'Delete Background', message: `Delete background "${name || ('#'+backgroundId)}"? This cannot be undone.`, confirmText: 'Delete', confirmStyle: 'danger', icon: '⚠️', iconType: 'danger' }); if (!ok) return; }
        try {
            const data = await ApiClient.post('/api/backgrounds.php?action=delete', { background_id: backgroundId });
            if (data && data.success) {
                bgNotify.ok(data.message || 'Background deleted');
                const { roomSelect } = bgEls();
                refreshBackgroundsUI(roomSelect ? roomSelect.value : '');
            } else {
                bgNotify.err((data && (data.error || data.message)) || 'Failed to delete background');
            }
        } catch (err) {
            console.error('[BackgroundManager] delete error', err);
            bgNotify.err('Error deleting background');
        }
    }
    function previewBackground(imageUrl, name) {
        if (!imageUrl) return;
        const overlay = document.createElement('div');
        overlay.id = 'imagePreviewModal';
        overlay.className = 'admin-modal-overlay show';
        overlay.setAttribute('aria-hidden', 'false');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
          <div class="admin-modal admin-modal--lg">
            <div class="modal-header">
              <h3 class="modal-title">${(name || 'Preview').replace(/</g,'&lt;')}</h3>
              <button class="admin-modal-close wf-admin-nav-button" data-action="close-preview" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
              <div class="p-3 flex items-center justify-center"><img src="${imageUrl}" alt="${(name||'').replace(/</g,'&lt;')}" class="max-w-full h-auto"></div>
            </div>
          </div>`;
        document.body.appendChild(overlay);
    }
    async function uploadBackground() {
        const { roomSelect, nameInput, fileInput } = bgEls();
        const room = roomSelect ? roomSelect.value : '';
        const name = nameInput ? nameInput.value.trim() : '';
        const file = fileInput ? fileInput.files[0] : null;
        if (!room) { bgNotify.err('Select a room first'); return; }
        if (!name) { bgNotify.err('Enter a background name'); return; }
        if (!file) { bgNotify.err('Please choose an image file to upload.'); return; }
        try {
            const fd = new FormData();
            fd.append('room', room);
            fd.append('background_name', name);
            fd.append('background_image', file);
            const res = await ApiClient.upload('/api/upload_background.php', fd);
            const data = await res.json().catch(() => ({}));
            if (data && data.success) {
                bgNotify.ok(data.message || 'Upload successful');
                // After upload, refresh list (server should save and return id)
                refreshBackgroundsUI(room);
                if (nameInput) nameInput.value = '';
                if (fileInput) fileInput.value = '';
            } else {
                bgNotify.err((data && (data.error || data.message)) || 'Upload failed.');
            }
        } catch (err) {
            console.error('[BackgroundManager] upload error', err);
            bgNotify.err('Upload failed.');
        }
    }
    
    // Window shims
    try {
        if (typeof window !== 'undefined') {
            window.openBackgroundManagerModal = openBackgroundManagerModal;
            window.closeBackgroundManagerModal = closeBackgroundManagerModal;
            window.loadBackgroundsForRoom = refreshBackgroundsUI;
            window.uploadBackground = uploadBackground;
            window.applyBackground = applyBackground;
            window.deleteBackground = deleteBackground;
            window.previewBackground = previewBackground;
        }
    } catch (_) {}
    
    // -----------------------------
    // Room Settings (migrated)
    // -----------------------------
    let currentEditingRoom = null;
    let coreRooms = [];
    
    async function loadRoomData() {
        try {
            const data = await ApiClient.get('/api/get_room_data.php');
            if (data && data.success && data.data) {
                coreRooms = Array.isArray(data.data.coreRooms) ? data.data.coreRooms : [];
            } else {
                coreRooms = [];
            }
        } catch (err) {
            console.warn('[RoomSettings] Failed to load room data', err);
            coreRooms = [];
        }
    }
    
    function openRoomSettingsModal() {
        const modal = document.getElementById('roomSettingsModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
        loadRoomSettings();
    }
    function closeRoomSettingsModal() {
        const modal = document.getElementById('roomSettingsModal');
        if (modal) modal.classList.add('hidden');
        currentEditingRoom = null;
        try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
    }
    
    async function loadRoomSettings() {
        try {
            const [roomData, roomSettings] = await Promise.all([
                ApiClient.get('/api/get_room_data.php'),
                ApiClient.get('/api/room_settings.php', { action: 'get_all' })
            ]);
            if (roomData && roomData.success && roomData.data && Array.isArray(roomData.data.coreRooms)) {
                coreRooms = roomData.data.coreRooms;
            } else {
                coreRooms = [];
            }
            if (roomSettings && roomSettings.success && Array.isArray(roomSettings.rooms)) {
                displayRoomSettingsList(roomSettings.rooms);
            } else {
                showRoomSettingsError('Failed to load room settings');
            }
        } catch (err) {
            showRoomSettingsError('Error loading room settings');
        }
    }
    
    function showRoomSettingsError(msg) {
        const container = document.getElementById('roomSettingsList');
        if (container) container.innerHTML = `<div class="text-red-600 text-sm">${(msg||'Error').replace(/</g,'&lt;')}</div>`;
    }
    
    function displayRoomSettingsList(rooms) {
        const container = document.getElementById('roomSettingsList');
        if (!container) return;
        if (!rooms || rooms.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500">
                    <p>No rooms found</p>
                    <button data-action="initialize-room-settings" class="bg-cyan-500 hover:bg-cyan-600 text-white rounded flex items-center text-left px-3 py-2 text-sm">Initialize Room Settings</button>
                </div>
            `;
            return;
        }
        container.innerHTML = '';
        rooms.forEach((room) => {
            const card = document.createElement('div');
            card.className = 'border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors';
            card.setAttribute('data-action', 'edit-room-settings');
            try { card.setAttribute('data-room', encodeURIComponent(JSON.stringify(room))); } catch(_) {}
            const isCore = Array.isArray(coreRooms) && coreRooms.includes(room.room_number);
            card.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-600">Room ${room.room_number}</span>
                            ${isCore ? '<span class="text-xs bg-blue-100 text-blue-800 rounded px-2">Core</span>' : ''}
                        </div>
                        <h4 class="font-semibold text-gray-800">${(room.room_name||'').replace(/</g,'&lt;')}</h4>
                        <p class="text-sm text-gray-600">${(room.door_label||'').replace(/</g,'&lt;')}</p>
                        <p class="text-xs text-gray-500">${(room.description||'No description').replace(/</g,'&lt;')}</p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <div>Order: ${room.display_order}</div>
                        <div class="text-cyan-600">Edit →</div>
                    </div>
                </div>`;
            container.appendChild(card);
        });
    }
    
    function editRoomSettings(room) {
        currentEditingRoom = room;
        const formContainer = document.getElementById('roomEditForm');
        if (!formContainer) return;
        const isCore = Array.isArray(coreRooms) && coreRooms.includes(room.room_number);
        formContainer.innerHTML = `
            <form data-action="save-room-settings" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room Number ${isCore ? '<span class="text-red-500">*</span>' : ''}</label>
                    <input type="number" id="editRoomNumber" value="${room.room_number}" class="w-full border border-gray-300 rounded-lg ${isCore ? 'bg-gray-100' : ''}" ${isCore ? 'readonly' : ''}>
                    ${isCore ? '<p class="text-xs text-gray-500">Core rooms cannot change numbers</p>' : ''}
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room Name <span class="text-red-500">*</span></label>
                    <input type="text" id="editRoomName" value="${(room.room_name||'').replace(/\"/g,'&quot;')}" class="w-full border border-gray-300 rounded-lg" required>
                    <p class="text-xs text-gray-500">This appears as the main title in the room</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Door Label <span class="text-red-500">*</span></label>
                    <input type="text" id="editDoorLabel" value="${(room.door_label||'').replace(/\"/g,'&quot;')}" class="w-full border border-gray-300 rounded-lg" required>
                    <p class="text-xs text-gray-500">This appears on door signs in the main room</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="editRoomDescription" rows="3" class="w-full border border-gray-300 rounded-lg">${(room.description||'').replace(/</g,'&lt;')}</textarea>
                    <p class="text-xs text-gray-500">This appears as subtitle text in the room</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Display Order</label>
                    <input type="number" id="editDisplayOrder" value="${room.display_order}" class="w-full border border-gray-300 rounded-lg" min="0">
                    <p class="text-xs text-gray-500">Lower numbers appear first in navigation</p>
                </div>
                <div>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" id="editShowSearchBar" ${room.show_search_bar ? 'checked' : ''} class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                        <span class="text-sm font-medium text-gray-700">Show Search Bar</span>
                    </label>
                </div>
                <div class="flex gap-3 border-t pt-3">
                    <button type="submit" class="flex-1 bg-cyan-500 hover:bg-cyan-600 text-white rounded-lg font-medium px-3 py-2">Save Changes</button>
                    <button type="button" data-action="cancel-room-edit" class="bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg px-3 py-2">Cancel</button>
                </div>
            </form>`;
    }
    
    async function initializeRoomSettings() {
        try {
            const data = await ApiClient.post('/api/room_settings.php', { action: 'initialize' }).catch(() => ({}));
            if (data && data.success) {
                await loadRoomSettings();
            } else {
                const msg = (data && (data.error || data.message)) || 'Failed to initialize room settings';
                if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                    window.wfNotifications.show(msg, 'error', { title: 'Room Settings' });
                } else if (typeof window.showError === 'function') {
                    window.showError(msg);
                } else { try { if (typeof window.showNotification === 'function') window.showNotification(msg, 'error'); } catch(_) {}
                }
            }
        } catch (err) {
            console.warn('[RoomSettings] initialize error', err);
        }
    }
    
    async function saveRoomSettings(e) {
        try {
            const form = e && e.target ? e.target : document.querySelector('form[data-action="save-room-settings"]');
            if (!form) return;
            const payload = {
                original_room_number: currentEditingRoom ? currentEditingRoom.room_number : undefined,
                room_number: Number(document.getElementById('editRoomNumber')?.value || 0),
                room_name: document.getElementById('editRoomName')?.value || '',
                door_label: document.getElementById('editDoorLabel')?.value || '',
                description: document.getElementById('editRoomDescription')?.value || '',
                display_order: Number(document.getElementById('editDisplayOrder')?.value || 0),
                show_search_bar: !!document.getElementById('editShowSearchBar')?.checked,
            };
            const data = await ApiClient.post('/api/room_settings.php', { action: 'save', ...payload }).catch(() => ({}));
            if (data && data.success) {
                try {
                    if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                        window.wfNotifications.show('Room settings saved', 'success', { title: 'Room Settings' });
                    } else if (typeof window.showSuccess === 'function') {
                        window.showSuccess('Room settings saved');
                    }
                } catch(_) {}
                await loadRoomSettings();
                currentEditingRoom = null;
            } else {
                const msg = (data && (data.error || data.message)) || 'Failed to save';
                try {
                    if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                        window.wfNotifications.show(msg, 'error', { title: 'Room Settings' });
                    } else if (typeof window.showError === 'function') {
                        window.showError(msg);
                    } else { try { if (typeof window.showNotification === 'function') window.showNotification(msg, 'error'); } catch(_) {}
                    }
                } catch(_) { try { if (typeof window.showNotification === 'function') window.showNotification(msg, 'error'); } catch(__) {}
                }
            }
        } catch (err) {
            console.warn('[RoomSettings] save error', err);
        }
    }
    
    // Window shims for compatibility
    try {
        if (typeof window !== 'undefined') {
            window.openRoomSettingsModal = openRoomSettingsModal;
            window.closeRoomSettingsModal = closeRoomSettingsModal;
            window.loadRoomSettings = loadRoomSettings;
            window.editRoomSettings = editRoomSettings;
            window.loadRoomData = loadRoomData;
        }
    } catch(_) {}
    
    // Delegated handlers specific to Room Settings
    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!t || !t.closest) return;
        const initBtn = t.closest('[data-action="initialize-room-settings"]');
        if (initBtn) {
            e.preventDefault();
            initializeRoomSettings();
            return;
        }
        const editCard = t.closest('[data-action="edit-room-settings"]');
        if (editCard && editCard.hasAttribute('data-room')) {
            e.preventDefault();
            try {
                const payload = JSON.parse(decodeURIComponent(editCard.getAttribute('data-room')));
                editRoomSettings(payload);
            } catch (err) { console.warn('[RoomSettings] bad data-room payload', err); }
            return;
        }
        const cancelBtn = t.closest('[data-action="cancel-room-edit"]');
        if (cancelBtn) {
            e.preventDefault();
            // Close modal or just reload list
            try { closeRoomSettingsModal(); } catch(_) {}
            return;
        }
    }, true);
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form || !form.matches) return;
        if (form.matches('form[data-action="save-room-settings"]')) {
            e.preventDefault();
            saveRoomSettings(e);
            return;
        }
    }, true);
    
    // -----------------------------
    // File Explorer (migrated)
    // -----------------------------
    let currentDirectory = '';
    let currentFile = null;
    let fileExplorerData = {};
    
    function openFileExplorerModal() {
        const modal = document.getElementById('fileExplorerModal');
        if (!modal) return;
        try { modal.classList.remove('hidden'); } catch(_) {}
        try { modal.classList.add('show'); } catch(_) {}
        try { modal.setAttribute('aria-hidden', 'false'); } catch(_) {}
        setTimeout(() => loadDirectory(''), 10);
    }
    function closeFileExplorerModal() {
        const modal = document.getElementById('fileExplorerModal');
        if (modal) {
            try { modal.classList.add('hidden'); } catch(_) {}
            try { modal.classList.remove('show'); } catch(_) {}
            try { modal.setAttribute('aria-hidden', 'true'); } catch(_) {}
        }
        closeEditor();
    }
    
    async function loadDirectory(path = '') {
        try {
            const result = await ApiClient.get("/api/file_manager.php", { action: "list", path });
            if (result && result.success) {
                currentDirectory = result.path || '';
                fileExplorerData = result;
                displayFileList(result.items || []);
                updatePathDisplay();
                updateUpButton();
            } else {
                try {
                    const msg = result?.error || 'Failed to load directory';
                    if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                        window.wfNotifications.show(msg, 'error', { title: 'File Explorer' });
                    } else if (typeof window.showError === 'function') {
                        window.showError(msg);
                    } else { try { if (typeof window.showNotification === 'function') window.showNotification(msg, 'error'); } catch(_) {}
                    }
                } catch(_) { try { if (typeof window.showNotification === 'function') window.showNotification(result?.error || 'Failed to load directory', 'error'); } catch(__) {}
                }
            }
        } catch (err) {
            console.warn('[FileExplorer] loadDirectory error', err);
            try { if (typeof window.showError === 'function') window.showError('Failed to load directory'); } catch(_) {}
        }
    }
    
    function displayFileList(items) {
        const listEl = document.getElementById('fileList');
        const loadingEl = document.getElementById('fileListLoading');
        if (!listEl) { console.warn('[FileExplorer] #fileList missing'); return; }
        if (loadingEl) { try { loadingEl.classList.add('hidden'); } catch(_) {} }
        if (!items || items.length === 0) {
            listEl.innerHTML = '<p class="text-gray-500 text-center">Directory is empty</p>';
            return;
        }
        let html = '<div class="space-y-1">';
        for (const item of items) {
            const icon = item.type === 'directory' ? '📁' : getFileIcon(item.extension);
            const sizeText = item.type === 'file' ? (item.size_formatted || '') : '';
            const modifiedDate = item.modified ? new Date(item.modified * 1000).toLocaleDateString() : '';
            html += `
                <div class="flex items-center justify-between hover:bg-gray-100 rounded cursor-pointer" data-action="fm-item" data-type="${item.type}" data-path="${item.path}">
                    <div class="flex items-center flex-1 gap-2">
                        <span>${icon}</span>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">${(item.name||'').replace(/</g,'&lt;')}</div>
                            <div class="text-xs text-gray-500">${sizeText} ${modifiedDate}</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-1">
                        ${item.type === 'file' && item.viewable ? `<button data-action="fm-view-file" data-path="${item.path}" class="text-xs bg-blue-500 hover:bg-blue-600 text-white rounded px-2 py-1">View</button>` : ''}
                        ${item.type === 'file' && item.editable ? `<button data-action="fm-edit-file" data-path="${item.path}" class="text-xs bg-green-500 hover:bg-green-600 text-white rounded px-2 py-1">Edit</button>` : ''}
                        <button data-action="fm-delete-item" data-path="${item.path}" data-type="${item.type}" class="text-xs bg-red-500 hover:bg-red-600 text-white rounded px-2 py-1">Delete</button>
                    </div>
                </div>`;
        }
        html += '</div>';
        listEl.innerHTML = html;
    }
    
    function getFileIcon(ext) {
        const icons = { php:'🐘', js:'📜', css:'🎨', html:'🌐', json:'📋', txt:'📄', md:'📝', png:'🖼️', jpg:'🖼️', jpeg:'🖼️', webp:'🖼️', svg:'🖼️', log:'📊', sh:'⚙️' };
        return icons[ext] || '📄';
    }
    function updatePathDisplay() {
        const fullPathElement = document.getElementById('fullPath');
        if (fullPathElement) {
            const basePath = '/Users/jongraves/Documents/Websites/WhimsicalFrog';
            const fullPath = currentDirectory === '' ? basePath : basePath + '/' + currentDirectory;
            fullPathElement.textContent = fullPath;
        }
    }
    function updateUpButton() {
        const upButton = document.getElementById('upButton');
        if (upButton) upButton.disabled = currentDirectory === '';
    }
    function navigateUp() {
        if (currentDirectory !== '' && fileExplorerData && fileExplorerData.parent !== null) {
            loadDirectory(fileExplorerData.parent === '.' ? '' : fileExplorerData.parent);
        }
    }
    function refreshDirectory() { loadDirectory(currentDirectory); }
    function selectFile(path) { showFileInfo(path); }
    
    async function viewFile(path) {
        try {
            const result = await ApiClient.get("/api/file_manager.php", { action: "read", path });
            if (result && result.success) {
                currentFile = { path: result.path, content: result.content, editable: result.editable, readonly: true };
                displayFileInEditor(result);
            } else {
                try { if (typeof window.showError === 'function') window.showError(result?.error || 'Failed to read file'); } catch(_) {}
            }
        } catch (err) {
            console.warn('[FileExplorer] viewFile error', err);
        }
    }
    async function editFile(path) {
        try {
            const result = await ApiClient.get('/api/file_manager.php', { action: 'read', path }).catch(() => ({}));
            if (result && result.success) {
                currentFile = { path: result.path, content: result.content, editable: result.editable, readonly: false };
                displayFileInEditor(result);
            } else {
                try { if (typeof window.showError === 'function') window.showError(result?.error || 'Failed to read file'); } catch(_) {}
            }
        } catch (err) {
            console.warn('[FileExplorer] editFile error', err);
        }
    }
    function displayFileInEditor(fileData) {
        const editorDiv = document.getElementById('fileEditor');
        const actionsDiv = document.getElementById('editorActions');
        if (!editorDiv || !actionsDiv) return;
        const isReadonly = (currentFile && currentFile.readonly) || !fileData.editable;
        editorDiv.innerHTML = `
            <div class="">
                <div class="flex items-center justify-between">
                    <h5 class="font-medium text-gray-800">${(fileData.filename||'').replace(/</g,'&lt;')}</h5>
                    <span class="text-xs text-gray-500">${isReadonly ? 'Read-only' : 'Editable'}</span>
                </div>
            </div>
            <textarea id="fileContent" class="w-full h-80 border border-gray-300 rounded font-mono text-sm resize-none" ${isReadonly ? 'readonly' : ''} placeholder="File content will appear here...">${fileData.content||''}</textarea>`;
        if (!isReadonly) actionsDiv.classList.remove('hidden'); else actionsDiv.classList.add('hidden');
        showFileInfo(fileData.path, fileData);
    }
    function showFileInfo(path, fileData = null) {
        const panel = document.getElementById('fileInfoPanel');
        if (fileData && panel) {
            const fileSizeEl = document.getElementById('fileSize');
            const fileModifiedEl = document.getElementById('fileModified');
            const filePermissionsEl = document.getElementById('filePermissions');
            const fileTypeEl = document.getElementById('fileType');
            if (fileSizeEl) fileSizeEl.textContent = formatFileSize(fileData.size);
            if (fileModifiedEl) fileModifiedEl.textContent = new Date(fileData.modified * 1000).toLocaleString();
            if (filePermissionsEl) filePermissionsEl.textContent = '-';
            if (fileTypeEl) fileTypeEl.textContent = (fileData.filename||'').split('.').pop().toUpperCase();
            panel.classList.remove('hidden');
        }
    }
    function formatFileSize(bytes) {
        if (typeof bytes !== 'number') return '-';
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' bytes';
    }
    async function saveFile() {
        if (!currentFile || currentFile.readonly) {
            try {
                const msg = 'No editable file selected';
                if (typeof window !== 'undefined' && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
                    window.wfNotifications.show(msg, 'error', { title: 'File Explorer' });
                } else if (typeof window.showError === 'function') {
                    window.showError(msg);
                } else { try { if (typeof window.showNotification === 'function') window.showNotification(msg, 'error'); } catch(_) {}
                }
            } catch(_) { try { if (typeof window.showNotification === 'function') window.showNotification('No editable file selected', 'error'); } catch(__) {}
            }
            return;
        }
        const content = (document.getElementById('fileContent')||{}).value || '';
        try {
            const result = await ApiClient.post('/api/file_manager.php?action=write', { path: currentFile.path, content });
            if (result && result.success) {
                try { if (typeof window.showSuccess === 'function') window.showSuccess('File saved successfully'); } catch(_) {}
                currentFile.content = content;
            } else {
                try { if (typeof window.showError === 'function') window.showError(result?.error || 'Failed to save file'); } catch(_) {}
            }
        } catch (err) {
            console.warn('[FileExplorer] saveFile error', err);
        }
    }
    function closeEditor() {
        const editorDiv = document.getElementById('fileEditor');
        const actionsDiv = document.getElementById('editorActions');
        const infoPanel = document.getElementById('fileInfoPanel');
        if (!editorDiv || !actionsDiv || !infoPanel) return;
        editorDiv.innerHTML = `
            <div class="text-center text-gray-500">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>Select a file to view or edit</p>
            </div>`;
        actionsDiv.classList.add('hidden');
        infoPanel.classList.add('hidden');
        currentFile = null;
    }
    async function deleteItem(path, type) {
        const itemType = type === 'directory' ? 'folder' : 'file';
        if (typeof window.showConfirmationModal !== 'function') { try { if (typeof window.showNotification === 'function') window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {} return; }
        { const ok = await window.showConfirmationModal({ title: 'Delete Item', message: `Delete this ${itemType}?\n\n${path}`, confirmText: 'Delete', confirmStyle: 'danger', icon: '⚠️', iconType: 'danger' }); if (!ok) return; }
        try {
            const res = await ApiClient.delete(`/api/file_manager.php?action=delete&path=${encodeURIComponent(path)}`);
            const result = await res.json();
            if (result && result.success) {
                try { if (typeof window.showSuccess === 'function') window.showSuccess(`${itemType[0].toUpperCase()+itemType.slice(1)} deleted successfully`); } catch(_) {}
                refreshDirectory();
                if (currentFile && currentFile.path === path) closeEditor();
            } else {
                try { if (typeof window.showError === 'function') window.showError(result?.error || `Failed to delete ${itemType}`); } catch(_) {}
            }
        } catch (err) {
            console.warn('[FileExplorer] deleteItem error', err);
        }
    }
    function showCreateFolderDialog() {
        const name = prompt('Enter folder name:');
        if (name && name.trim()) createFolder(name.trim());
    }
    function showCreateFileDialog() {
        const name = prompt('Enter file name (with extension):');
        if (name && name.trim()) createFile(name.trim());
    }
    async function createFolder(name) {
        const path = currentDirectory ? `${currentDirectory}/${name}` : name;
        try {
            const result = await ApiClient.post('/api/file_manager.php?action=mkdir', { path });
            if (result && result.success) { try { if (typeof window.showSuccess === 'function') window.showSuccess('Folder created successfully'); } catch(_) {} refreshDirectory(); }
            else { try { if (typeof window.showError === 'function') window.showError(result?.error || 'Failed to create folder'); } catch(_) {} }
        } catch (err) { console.warn('[FileExplorer] createFolder error', err); }
    }
    async function createFile(name) {
        const path = currentDirectory ? `${currentDirectory}/${name}` : name;
        try {
            const result = await ApiClient.post('/api/file_manager.php?action=write', { path, content: '' });
            if (result && result.success) { try { if (typeof window.showSuccess === 'function') window.showSuccess('File created successfully'); } catch(_) {} refreshDirectory(); }
            else { try { if (typeof window.showError === 'function') window.showError(result?.error || 'Failed to create file'); } catch(_) {} }
        } catch (err) { console.warn('[FileExplorer] createFile error', err); }
    }
    
    // Window shims
    try {
        if (typeof window !== 'undefined') {
            window.openFileExplorerModal = openFileExplorerModal;
            window.closeFileExplorerModal = closeFileExplorerModal;
            window.loadDirectory = loadDirectory;
            window.viewFile = viewFile;
            window.editFile = editFile;
            window.saveFile = saveFile;
            window.refreshDirectory = refreshDirectory;
            window.navigateUp = navigateUp;
            window.createFolder = createFolder;
            window.createFile = createFile;
            window.deleteItem = deleteItem;
        }
    } catch(_) {}
    
    // Delegated handlers for File Explorer
    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!t || !t.closest) return;
        const item = t.closest('[data-action="fm-item"]');
        if (item) {
            const type = item.getAttribute('data-type');
            const path = item.getAttribute('data-path') || '';
            if (type === 'directory') {
                e.preventDefault();
                loadDirectory(path);
                return;
            }
            if (type === 'file') {
                e.preventDefault();
                selectFile(path);
                return;
            }
        }
        const viewBtn = t.closest('[data-action="fm-view-file"]');
        if (viewBtn) { e.preventDefault(); viewFile(viewBtn.getAttribute('data-path')); return; }
        const editBtn = t.closest('[data-action="fm-edit-file"]');
        if (editBtn) { e.preventDefault(); editFile(editBtn.getAttribute('data-path')); return; }
        const delBtn = t.closest('[data-action="fm-delete-item"]');
        if (delBtn) { e.preventDefault(); deleteItem(delBtn.getAttribute('data-path'), delBtn.getAttribute('data-type')); return; }
        const upBtn = t.closest('#upButton');
        if (upBtn) { e.preventDefault(); navigateUp(); return; }
        const refreshBtn = t.closest('[data-action="fm-refresh"], #refreshButton');
        if (refreshBtn) { e.preventDefault(); refreshDirectory(); return; }
        const saveBtn = t.closest('[data-action="fm-save"], #saveFileButton');
        if (saveBtn) { e.preventDefault(); saveFile(); return; }
        const mkDirBtn = t.closest('[data-action="fm-create-folder"]');
        if (mkDirBtn) { e.preventDefault(); showCreateFolderDialog(); return; }
        const mkFileBtn = t.closest('[data-action="fm-create-file"]');
        if (mkFileBtn) { e.preventDefault(); showCreateFileDialog(); return; }
        const closeBtn = t.closest('[data-action="close-file-explorer"]');
        if (closeBtn) { e.preventDefault(); closeFileExplorerModal(); return; }
    }, true);
    
    // Delegated change handler (room select)
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t || !t.matches) return;
        if (t.matches('#backgroundRoomSelect')) {
            refreshBackgroundsUI(t.value);
        }
        // Help Hints: toggle global tooltips
        const helpToggle = t.closest('[data-action="help-toggle-global-tooltips"]');
        if (helpToggle) {
            try { if (typeof window !== 'undefined' && typeof window.toggleGlobalTooltips === 'function') window.toggleGlobalTooltips(); } catch (_) {}
            return;
        }
        // Help Hints: filter by page
        const helpFilter = t.closest('[data-action="help-filter-hints"]');
        if (helpFilter) {
            try { if (typeof window !== 'undefined' && typeof window.filterHelpHints === 'function') window.filterHelpHints(); } catch (_) {}
            return;
        }
        // Logs: refresh current log
        const logsRefresh = t.closest('[data-action="logs-refresh-current"]');
        if (logsRefresh) {
            try { if (typeof window !== 'undefined' && typeof window.refreshCurrentLog === 'function') window.refreshCurrentLog(); } catch (_) {}
            return;
        }
        // Help Hints: import file selected
        const helpImportFile = t.closest('[data-action="help-import-file"]');
        if (helpImportFile) {
            try { if (typeof window !== 'undefined' && typeof window.importHelpHints === 'function') window.importHelpHints(); } catch (_) {}
            return;
        }
    }, true);

    // Delegated submit handlers
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form || !form.matches) return;
        // Categories: add category
        if (form.closest('[data-action="add-category"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.addCategory === 'function') window.addCategory(e); } catch (_) {}
            return;
        }
        // Help Hints: save form
        if (form.closest('[data-action="help-save-form"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveHelpHint === 'function') window.saveHelpHint(); } catch (_) {}
            return;
        }
    }, true);

    // Delegated keydown: Enter in cart text input adds text
    document.addEventListener('keydown', (e) => {
        const t = e.target;
        if (!t || !t.matches) return;
        if (t.id === 'newCartButtonText' && (e.key === 'Enter' || e.keyCode === 13)) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.addCartButtonText === 'function') {
                    window.addCartButtonText();
                } else {
                    document.dispatchEvent(new CustomEvent('wf:cart-text-add'));
                }
            } catch (_) {}
        }
    }, true);

    // Delegated change: documentation filter dropdown
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t || !t.closest) return;
        const filter = t.closest('[data-action="docs-filter-change"]');
        if (filter) {
            try { if (typeof window !== 'undefined' && typeof window.filterDocuments === 'function') window.filterDocuments(); } catch (_) {}
        }
    }, true);

    // Delegated click handler
    document.addEventListener('click', (e) => {
        const target = e.target;

        // Helper to match closest element
        const closest = (sel) => target.closest(sel);
        const invokeCallback = (cb) => {
            try {
                if (!cb) return;
                if (typeof window !== 'undefined' && typeof cb === 'string' && typeof window[cb] === 'function') {
                    window[cb]();
                    return;
                }
                if (typeof cb === 'string') {
                    const f = new Function(`return (${cb});`);
                    const fn = f();
                    if (typeof fn === 'function') {
                        (function(){
                          try {
                            if (typeof window !== 'undefined' && (window.WF_DISABLE_ADMIN_SETTINGS_JS === 1 || window.WF_DISABLE_ADMIN_SETTINGS_JS === true)) {
                              return; // global kill switch for safety during debugging
                            }
                          } catch(_) {}
                        })();
                        fn();
                    }
                }
            } catch (err) {
                console.warn('[AdminSettings] modal callback failed', err);
            }
        };

        // -----------------------------
        // Generic overlay close (legacy path)
        // Only handle if bridge is NOT active. Require clicking the actual overlay backdrop.
        // -----------------------------
        try {
            if (!window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) {
                if (target && target.classList && (target.classList.contains('admin-modal-overlay') || target.classList.contains('modal-overlay'))) {
                    e.preventDefault();
                    target.classList.add('hidden');
                    updateModalScrollLock();
                    return;
                }
            }
        } catch (_) {}

        // -----------------------------
        // Generic close button for admin modals
        // -----------------------------
        const genericClose = closest('[data-action="close-admin-modal"], .admin-modal-close');
        if (genericClose) {
            // If bridge is active, let it handle modal close to avoid double-handling
            if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) {
                return; // bridge listener will process and stop propagation
            }
            e.preventDefault();
            try {
                const overlay = genericClose.closest('.admin-modal-overlay, .modal-overlay');
                if (overlay) overlay.classList.add('hidden');
                updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // Marketing Analytics: switch tabs
        const switchTabBtn = closest('[data-action="switch-marketing-tab"]');
        if (switchTabBtn) {
            e.preventDefault();
            try {
                const tab = switchTabBtn.dataset.tab;
                const tabs = ['campaigns','acquisition','roi','social'];
                tabs.forEach((name) => {
                    const btn = document.querySelector(`.marketing-tab-nav [data-action="switch-marketing-tab"][data-tab="${name}"]`);
                    if (btn) btn.classList.toggle('active', name === tab);
                    const panel = document.getElementById(`${name}-tab`);
                    if (panel) panel.classList.toggle('hidden', name !== tab);
                });
            } catch (_) {}
            return;
        }

        // Marketing Analytics: refresh data
        if (closest('[data-action="refresh-marketing-data"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.refreshMarketingData === 'function') {
                    window.refreshMarketingData();
                } else {
                    document.dispatchEvent(new CustomEvent('wf:refresh-marketing-data'));
                }
            } catch (_) {}
            return;
        }

        // Business Reports: switch tabs
        const switchReportsBtn = closest('[data-action="switch-reports-tab"]');
        if (switchReportsBtn) {
            e.preventDefault();
            try {
                const tab = switchReportsBtn.dataset.tab;
                const tabs = ['sales','inventory','financial','operational'];
                tabs.forEach((name) => {
                    const btn = document.querySelector(`nav [data-action="switch-reports-tab"][data-tab="${name}"]`);
                    if (btn) {
                        const isActive = name === tab;
                        btn.classList.toggle('text-blue-600', isActive);
                        btn.classList.toggle('border-blue-500', isActive);
                        btn.classList.toggle('border-transparent', !isActive);
                        btn.classList.toggle('text-gray-500', !isActive);
                    }
                    const panel = document.getElementById(`${name}-tab`);
                    if (panel) panel.classList.toggle('hidden', name !== tab);
                });
            } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Global Colors/Sizes/Genders edit/delete
        // -----------------------------
        const editColorBtn = closest('[data-action="edit-global-color"]');
        if (editColorBtn) {
            e.preventDefault();
            const id = parseInt(editColorBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalColor === 'function') window.editGlobalColor(id); } catch (_) {}
            return;
        }

        const deleteColorBtn = closest('[data-action="delete-global-color"]');
        if (deleteColorBtn) {
            e.preventDefault();
            const id = parseInt(deleteColorBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalColor === 'function') window.deleteGlobalColor(id); } catch (_) {}
            return;
        }

        const editSizeBtn = closest('[data-action="edit-global-size"]');
        if (editSizeBtn) {
            e.preventDefault();
            const id = parseInt(editSizeBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalSize === 'function') window.editGlobalSize(id); } catch (_) {}
            return;
        }

        const deleteSizeBtn = closest('[data-action="delete-global-size"]');
        if (deleteSizeBtn) {
            e.preventDefault();
            const id = parseInt(deleteSizeBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalSize === 'function') window.deleteGlobalSize(id); } catch (_) {}
            return;
        }

        const editGenderBtn = closest('[data-action="edit-global-gender"]');
        if (editGenderBtn) {
            e.preventDefault();
            const id = parseInt(editGenderBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editGlobalGender === 'function') window.editGlobalGender(id); } catch (_) {}
            return;
        }

        const deleteGenderBtn = closest('[data-action="delete-global-gender"]');
        if (deleteGenderBtn) {
            e.preventDefault();
            const id = parseInt(deleteGenderBtn.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteGlobalGender === 'function') window.deleteGlobalGender(id); } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Cleanup/Optimize/Start Over actions
        // -----------------------------
        if (closest('[data-action="cleanup-stale-files"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.cleanupStaleFiles === 'function') window.cleanupStaleFiles(); } catch (_) {}
            return;
        }

        if (closest('[data-action="remove-unused-code"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.removeUnusedCode === 'function') window.removeUnusedCode(); } catch (_) {}
            return;
        }

        if (closest('[data-action="optimize-database"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.optimizeDatabase === 'function') window.optimizeDatabase(); } catch (_) {}
            return;
        }

        if (closest('[data-action="show-start-over-confirmation"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.showStartOverConfirmation === 'function') window.showStartOverConfirmation(); } catch (_) {}
            return;
        }

        if (closest('[data-action="execute-start-over"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.executeStartOver === 'function') window.executeStartOver(); } catch (_) {}
            return;
        }

        if (closest('[data-action="run-system-analysis"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.runSystemAnalysis === 'function') window.runSystemAnalysis(); } catch (_) {}
            return;
        }

        if (closest('[data-action="close-optimization-results"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.closeOptimizationResults === 'function') window.closeOptimizationResults(); } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Retry actions
        // -----------------------------
        if (closest('[data-action="retry-load-dashboard-config"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.loadDashboardConfiguration === 'function') window.loadDashboardConfiguration(); } catch (_) {}
            return;
        }

        const retryAvail = closest('[data-action="retry-load-available-sections"]');
        if (retryAvail) {
            e.preventDefault();
            const targetId = retryAvail.dataset.targetId;
            const el = document.getElementById(targetId);
            try { if (typeof window !== 'undefined' && typeof window.loadAvailableSections === 'function') window.loadAvailableSections(el); } catch (_) {}
            return;
        }

        if (closest('[data-action="retry-load-doc-list"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.loadDocumentationList === 'function') window.loadDocumentationList(); } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Documentation, Logs, Backups
        // -----------------------------
        const viewDoc = closest('[data-action="view-document"]');
        if (viewDoc) {
            e.preventDefault();
            const index = parseInt(viewDoc.dataset.docIndex, 10);
            try { if (typeof window !== 'undefined' && typeof window.viewDocument === 'function') window.viewDocument(index); } catch (_) {}
            return;
        }

        const scrollSection = closest('[data-action="scroll-doc-section"]');
        if (scrollSection) {
            e.preventDefault();
            const anchor = scrollSection.dataset.anchor;
            try { if (typeof window !== 'undefined' && typeof window.scrollToSection === 'function') window.scrollToSection(anchor); } catch (_) {}
            return;
        }

        const selectLogBtn = closest('[data-action="select-log"]');
        if (selectLogBtn) {
            e.preventDefault();
            const type = selectLogBtn.dataset.logType;
            try { if (typeof window !== 'undefined' && typeof window.selectLog === 'function') window.selectLog(type); } catch (_) {}
            return;
        }

        const viewLogEntryBtn = closest('[data-action="view-log-entry"]');
        if (viewLogEntryBtn) {
            e.preventDefault();
            const logType = viewLogEntryBtn.dataset.logType;
            const entryId = viewLogEntryBtn.dataset.entryId;
            try { if (typeof window !== 'undefined' && typeof window.viewLogEntry === 'function') window.viewLogEntry(logType, entryId); } catch (_) {}
            return;
        }

        const selectServerBackupBtn = closest('[data-action="select-server-backup"]');
        if (selectServerBackupBtn) {
            e.preventDefault();
            const filename = selectServerBackupBtn.dataset.filename;
            const path = selectServerBackupBtn.dataset.path;
            try { if (typeof window !== 'undefined' && typeof window.selectServerBackup === 'function') window.selectServerBackup(filename, path); } catch (_) {}
            return;
        }

        // Cart Texts: add new text
        if (closest('[data-action="cart-text-add"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.addCartButtonText === 'function') {
                    window.addCartButtonText();
                } else {
                    document.dispatchEvent(new CustomEvent('wf:cart-text-add'));
                }
            } catch (_) {}
            return;
        }

        // Cart Texts: quick add preset
        const quickAdd = closest('[data-action="cart-text-quick-add"]');
        if (quickAdd) {
            e.preventDefault();
            const text = quickAdd.dataset.text || '';
            try {
                if (typeof window !== 'undefined' && typeof window.addQuickCartText === 'function') {
                    window.addQuickCartText(text);
                } else {
                    document.dispatchEvent(new CustomEvent('wf:cart-text-quick-add', { detail: { text } }));
                }
            } catch (_) {}
            return;
        }

        // Cart Texts: remove entry
        const removeBtn = closest('[data-action="cart-text-remove"]');
        if (removeBtn) {
            e.preventDefault();
            const idx = parseInt(removeBtn.dataset.index, 10);
            try {
                if (typeof window !== 'undefined' && typeof window.removeCartButtonText === 'function') {
                    window.removeCartButtonText(idx);
                } else {
                    document.dispatchEvent(new CustomEvent('wf:cart-text-remove', { detail: { index: idx } }));
                }
            } catch (_) {}
            return;
        }

        // -----------------------------
        // Admin Settings: Rooms editor card click -> edit room settings
        // -----------------------------
        const roomCard = closest('[data-action="edit-room-settings"]');
        if (roomCard) {
            e.preventDefault();
            try {
                let room = null;
                const payload = roomCard.dataset.room;
                if (payload) {
                    try { room = JSON.parse(decodeURIComponent(payload)); } catch(_) {}
                }
                if (!room && typeof window !== 'undefined' && typeof window.getRoomFromElement === 'function') {
                    room = window.getRoomFromElement(roomCard);
                }
                if (typeof window !== 'undefined' && typeof window.editRoomSettings === 'function') {
                    window.editRoomSettings(room);
                } else {
                    document.dispatchEvent(new CustomEvent('wf:edit-room-settings', { detail: { room } }));
                }
            } catch(_) {}
            return;
        }

        // Start inline edits for category name
        const startEditCategoryBtn = closest('[data-action="start-edit-category"]');
        if (startEditCategoryBtn) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.startEditCategory === 'function') window.startEditCategory(startEditCategoryBtn); } catch(_) {}
            return;
        }

        // Start inline edits for SKU code
        const startEditSkuBtn = closest('[data-action="start-edit-sku-code"]');
        if (startEditSkuBtn) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.startEditSkuCode === 'function') window.startEditSkuCode(startEditSkuBtn); } catch(_) {}
            return;
        }

        // Documentation: select table from list
        const selectTableBtn = closest('[data-action="docs-select-table"]');
        if (selectTableBtn) {
            e.preventDefault();
            const table = selectTableBtn.dataset.table;
            try { if (typeof window !== 'undefined' && typeof window.selectTable === 'function') window.selectTable(table); } catch(_) {}
            return;
        }

        // Cart Texts: reset to defaults
        if (closest('[data-action="cart-text-reset"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.resetToDefaults === 'function') {
                    window.resetToDefaults();
                } else {
                    document.dispatchEvent(new CustomEvent('wf:cart-text-reset'));
                }
            } catch (_) {}
            return;
        }

        // Optimization: generate suggestions
        if (closest('[data-action="opt-generate-suggestions"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.generateOptimizationSuggestions === 'function') window.generateOptimizationSuggestions(); } catch (_) {}
            return;
        }

        // Marketing Defaults: save
        if (closest('[data-action="marketing-save-defaults"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveMarketingDefaults === 'function') window.saveMarketingDefaults(); } catch (_) {}
            return;
        }

        // Business Settings: reset all to defaults
        if (closest('[data-action="business-reset-defaults"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.resetAllSettingsToDefaults === 'function') window.resetAllSettingsToDefaults(); } catch (_) {}
            return;
        }
        // Business Settings: save all
        if (closest('[data-action="business-save-all"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveAllBusinessSettings === 'function') window.saveAllBusinessSettings(); } catch (_) {}
            return;
        }

        // General Config: retry load
        if (closest('[data-action="general-config-retry"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.loadGeneralConfig === 'function') window.loadGeneralConfig(); } catch (_) {}
            return;
        }

        // Item selection toggles
        const toggleItem = closest('[data-action="toggle-item-selection"]');
        if (toggleItem) {
            e.preventDefault();
            const sku = toggleItem.dataset.sku;
            const add = String(toggleItem.dataset.select).toLowerCase() === 'true';
            try { if (typeof window !== 'undefined' && typeof window.toggleItemSelection === 'function') window.toggleItemSelection(sku, add); } catch (_) {}
            return;
        }

        // Generic close-modal targeting specific selector
        const closeModalBtn = closest('[data-action="close-modal"]');
        if (closeModalBtn) {
            e.preventDefault();
            const sel = closeModalBtn.dataset.target;
            if (sel) {
                const el = document.querySelector(sel);
                if (el) el.classList.add('hidden');
            }
            try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
            return;
        }

        // Global Genders/Colors/Sizes: switch tab
        const switchGlobal = closest('[data-action="switch-global-tab"]');
        if (switchGlobal) {
            e.preventDefault();
            const tab = switchGlobal.dataset.tab;
            try { if (typeof window !== 'undefined' && typeof window.switchGlobalTab === 'function') window.switchGlobalTab(tab); } catch (_) {}
            return;
        }

        // Global Gender: show/cancel add form (fallback UI if legacy funcs missing)
        if (closest('[data-action="show-add-gender-form"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showAddGenderForm === 'function') { window.showAddGenderForm(); }
                else {
                    const form = document.getElementById('addGenderForm');
                    if (form) form.classList.remove('hidden');
                }
            } catch(_) {}
            return;
        }
        if (closest('[data-action="cancel-add-gender"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.cancelAddGender === 'function') { window.cancelAddGender(); }
                else {
                    const form = document.getElementById('addGenderForm');
                    if (form) {
                        form.classList.add('hidden');
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach(i => { try { i.value = ''; } catch(_){} });
                    }
                }
            } catch(_) {}
            return;
        }

        // Sales Admin: open Create Sale modal
        const openCreateBtn = closest('[data-action="open-create-sale-modal"]');
        if (openCreateBtn) {
            e.preventDefault();
            try {
                const id = openCreateBtn.dataset && openCreateBtn.dataset.saleId ? parseInt(openCreateBtn.dataset.saleId, 10) : undefined;
                if (typeof window !== 'undefined' && typeof window.openCreateSaleModal === 'function') {
                    if (typeof id === 'number' && !Number.isNaN(id)) {
                        window.openCreateSaleModal(id);
                    } else {
                        window.openCreateSaleModal();
                    }
                } else {
                    const modal = document.getElementById('createSaleModal');
                    if (modal) modal.classList.remove('hidden');
                }
                updateModalScrollLock && updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // Generic: click overlay background to close (only if background clicked)
        const overlayEl = closest('[data-action="overlay-close"]');
        if (overlayEl && e.target === overlayEl) {
            e.preventDefault();
            overlayEl.classList.add('hidden');
            try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
            return;
        }

        // Generic: explicit close button inside modal headers
        if (closest('[data-action="close-admin-modal"]')) {
            e.preventDefault();
            const container = target.closest('#databaseTablesModal, .admin-modal-overlay, .fixed.inset-0');
            if (container) container.classList.add('hidden');
            try { if (typeof updateModalScrollLock === 'function') updateModalScrollLock(); } catch(_) {}
            try { if (typeof window !== 'undefined' && typeof window.closeDatabaseTablesModal === 'function') window.closeDatabaseTablesModal(); } catch(_) {}
            return;
        }

        // Square Settings: open modal
        if (closest('[data-action="open-square-settings"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openSquareSettingsModal === 'function') {
                    window.openSquareSettingsModal();
                } else {
                    const modal = document.getElementById('squareSettingsModal');
                    if (modal) {
                        try {
                            if (modal.parentElement && modal.parentElement !== document.body) {
                                document.body.appendChild(modal);
                            }
                            modal.classList.add('over-header');
                        } catch (_) {}
                        modal.classList.remove('hidden');
                        modal.classList.add('show');
                        modal.setAttribute('aria-hidden', 'false');
                    }
                }
                if (typeof updateModalScrollLock === 'function') updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // AI Provider (AI Settings): open modal
        if (closest('[data-action="open-ai-settings"], #aiSettingsBtn')) {
            e.preventDefault();
            try {
                const modal = document.getElementById('aiSettingsModal');
                if (modal) {
                    try {
                        if (modal.parentElement && modal.parentElement !== document.body) {
                            document.body.appendChild(modal);
                        }
                        modal.classList.add('over-header');
                    } catch (_) {}
                    modal.classList.remove('hidden');
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                }
                if (typeof updateModalScrollLock === 'function') updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // AI & Automation Tools: open modal
        if (closest('[data-action="open-ai-tools"], #aiToolsBtn')) {
            e.preventDefault();
            try {
                const modal = document.getElementById('aiToolsModal');
                if (modal) {
                    try {
                        if (modal.parentElement && modal.parentElement !== document.body) {
                            document.body.appendChild(modal);
                        }
                        modal.classList.add('over-header');
                    } catch (_) {}
                    modal.classList.remove('hidden');
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                }
                if (typeof updateModalScrollLock === 'function') updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // Square Settings: test connection
        if (closest('[data-action="square-test-connection"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.testSquareConnection === 'function') window.testSquareConnection(); } catch(_) {}
            return;
        }
        // Square Settings: save settings
        if (closest('[data-action="square-save-settings"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.saveSquareSettings === 'function') window.saveSquareSettings(); } catch(_) {}
            return;
        }
        // Square Settings: sync items now
        if (closest('[data-action="square-sync-items"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.syncItemsToSquare === 'function') window.syncItemsToSquare(); } catch(_) {}
            return;
        }

        // Receipt Settings: open modal
        if (closest('[data-action="open-receipt-settings"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openReceiptSettingsModal === 'function') {
                    window.openReceiptSettingsModal();
                } else {
                    const modal = document.getElementById('receiptSettingsModal');
                    if (modal) modal.classList.remove('hidden');
                }
                if (typeof updateModalScrollLock === 'function') updateModalScrollLock();
            } catch (_) {}
            return;
        }

        // Receipt Settings: switch tabs
        const receiptTabBtn = closest('[data-action="receipt-tab"]');
        if (receiptTabBtn) {
            e.preventDefault();
            try {
                const tab = receiptTabBtn.dataset.tab;
                const tabs = ['shipping','items','categories','default'];
                tabs.forEach((name) => {
                    const btn = document.querySelector(`[data-action="receipt-tab"][data-tab="${name}"]`);
                    if (btn) btn.classList.toggle('active', name === tab);
                    const panel = document.getElementById(`${name}Tab`);
                    if (panel) panel.classList.toggle('active', name === tab);
                    if (panel) panel.classList.toggle('hidden', name !== tab);
                });
                if (typeof window !== 'undefined' && typeof window.switchReceiptTab === 'function') {
                    window.switchReceiptTab(tab);
                }
            } catch (_) {}
            return;
        }

        // Receipt Settings: add entries
        if (closest('[data-action="receipt-add-shipping"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.addShippingMessage === 'function') window.addShippingMessage(); } catch(_) {}
            return;
        }
        if (closest('[data-action="receipt-add-item-count"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.addItemCountMessage === 'function') window.addItemCountMessage(); } catch(_) {}
            return;
        }
        if (closest('[data-action="receipt-add-category"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.addCategoryMessage === 'function') window.addCategoryMessage(); } catch(_) {}
            return;
        }

        // Receipt Settings: delete message item
        const delMsgBtn = closest('[data-action="receipt-delete-message"]');
        if (delMsgBtn) {
            e.preventDefault();
            const id = parseInt(delMsgBtn.dataset.messageId, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteReceiptMessage === 'function') window.deleteReceiptMessage(id); } catch(_) {}
            return;
        }

        // Receipt Settings: AI generate message content
        const genAIBtn = closest('[data-action="receipt-generate-ai"]');
        if (genAIBtn) {
            e.preventDefault();
            const id = parseInt(genAIBtn.dataset.messageId, 10);
            const type = genAIBtn.dataset.type;
            try { if (typeof window !== 'undefined' && typeof window.generateAIMessage === 'function') window.generateAIMessage(id, type); } catch(_) {}
            return;
        }

        // Sales Admin: select/deselect all items
        if (closest('[data-action="sale-select-all"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.selectAllItems === 'function') window.selectAllItems(); } catch (_) {}
            return;
        }
        if (closest('[data-action="sale-deselect-all"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.deselectAllItems === 'function') window.deselectAllItems(); } catch (_) {}
            return;
        }

        // Sales list: toggle active
        const saleToggle = closest('[data-action="sale-toggle-active"]');
        if (saleToggle) {
            e.preventDefault();
            const id = parseInt(saleToggle.dataset.saleId, 10);
            try { if (typeof window !== 'undefined' && typeof window.toggleSaleActive === 'function') window.toggleSaleActive(id); } catch (_) {}
            return;
        }
        // Sales list: delete sale
        const saleDelete = closest('[data-action="sale-delete"]');
        if (saleDelete) {
            e.preventDefault();
            const id = parseInt(saleDelete.dataset.saleId, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteSale === 'function') window.deleteSale(id); } catch (_) {}
            return;
        }
        // Sales list: edit sale (open create/edit modal with id)
        const saleEdit = closest('[data-action="sale-edit"]');
        if (saleEdit) {
            e.preventDefault();
            const id = parseInt(saleEdit.dataset.saleId, 10);
            try { if (typeof window !== 'undefined' && typeof window.openCreateSaleModal === 'function') window.openCreateSaleModal(id); } catch (_) {}
            return;
        }

        // Categories: start edit name
        const catEditName = closest('[data-action="category-edit-name"]');
        if (catEditName) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.startEditCategory === 'function') window.startEditCategory(catEditName); } catch (_) {}
            return;
        }
        // Categories: start edit SKU
        const catEditSku = closest('[data-action="category-edit-sku"]');
        if (catEditSku) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.startEditSkuCode === 'function') window.startEditSkuCode(catEditSku); } catch (_) {}
            return;
        }
        // Categories: delete category
        const catDelete = closest('[data-action="category-delete"]');
        if (catDelete) {
            e.preventDefault();
            const cat = catDelete.dataset.category;
            try { if (typeof window !== 'undefined' && typeof window.deleteCategory === 'function') window.deleteCategory(cat); } catch (_) {}
            return;
        }

        // Help Hints: show/hide form, bulk apply, export/import trigger
        if (closest('[data-action="help-show-form"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.showHelpHintForm === 'function') window.showHelpHintForm(); } catch (_) {}
            return;
        }
        if (closest('[data-action="help-hide-form"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.hideHelpHintForm === 'function') window.hideHelpHintForm(); } catch (_) {}
            return;
        }
        if (closest('[data-action="help-bulk-apply"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.bulkToggleHints === 'function') window.bulkToggleHints(); } catch (_) {}
            return;
        }
        if (closest('[data-action="help-export"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.exportHelpHints === 'function') window.exportHelpHints(); } catch (_) {}
            return;
        }
        if (closest('[data-action="help-trigger-import"]')) {
            e.preventDefault();
            const input = document.getElementById('importFile');
            if (input) input.click();
            return;
        }

        // Global Colors: show/cancel add form
        if (closest('[data-action="show-add-color-form"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showAddColorForm === 'function') { window.showAddColorForm(); }
                else {
                    const form = document.getElementById('addColorForm');
                    if (form) form.classList.remove('hidden');
                }
            } catch(_) {}
            return;
        }
        if (closest('[data-action="cancel-add-color"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.cancelAddColor === 'function') { window.cancelAddColor(); }
                else {
                    const form = document.getElementById('addColorForm');
                    if (form) {
                        form.classList.add('hidden');
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach(i => { try { i.value = ''; } catch(_){} });
                    }
                }
            } catch(_) {}
            return;
        }

        // Global Sizes: show/cancel add form
        if (closest('[data-action="show-add-size-form"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.showAddSizeForm === 'function') { window.showAddSizeForm(); }
                else {
                    const form = document.getElementById('addSizeForm');
                    if (form) form.classList.remove('hidden');
                }
            } catch(_) {}
            return;
        }
        if (closest('[data-action="cancel-add-size"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.cancelAddSize === 'function') { window.cancelAddSize(); }
                else {
                    const form = document.getElementById('addSizeForm');
                    if (form) {
                        form.classList.add('hidden');
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach(i => { try { i.value = ''; } catch(_){} });
                    }
                }
            } catch(_) {}
            return;
        }

        // Help Hints: row actions
        const helpEdit = closest('[data-action="help-edit-hint"]');
        if (helpEdit) {
            e.preventDefault();
            const id = parseInt(helpEdit.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.editHelpHint === 'function') window.editHelpHint(id); } catch (_) {}
            return;
        }
        const helpToggle = closest('[data-action="help-toggle-hint"]');
        if (helpToggle) {
            e.preventDefault();
            const id = parseInt(helpToggle.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.toggleHelpHint === 'function') window.toggleHelpHint(id); } catch (_) {}
            return;
        }
        const helpDelete = closest('[data-action="help-delete-hint"]');
        if (helpDelete) {
            e.preventDefault();
            const id = parseInt(helpDelete.dataset.id, 10);
            try { if (typeof window !== 'undefined' && typeof window.deleteHelpHint === 'function') window.deleteHelpHint(id); } catch (_) {}
            return;
        }

        // Logs: search all
        if (closest('[data-action="logs-search"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.searchAllLogs === 'function') window.searchAllLogs(); } catch (_) {}
            return;
        }
        // Logs: refresh/download/clear/cleanup/pagination
        if (closest('[data-action="logs-refresh"], [data-action="logs-refresh-current"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.refreshCurrentLog === 'function') window.refreshCurrentLog(); } catch (_) {}
            return;
        }
        if (closest('[data-action="logs-download"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.downloadCurrentLog === 'function') window.downloadCurrentLog(); } catch (_) {}
            return;
        }
        if (closest('[data-action="logs-clear"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.clearCurrentLog === 'function') window.clearCurrentLog(); } catch (_) {}
            return;
        }
        if (closest('[data-action="logs-cleanup"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.cleanupOldLogs === 'function') window.cleanupOldLogs(); } catch (_) {}
            return;
        }
        if (closest('[data-action="logs-prev-page"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.previousLogPage === 'function') window.previousLogPage(); } catch (_) {}
            return;
        }
        if (closest('[data-action="logs-next-page"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.nextLogPage === 'function') window.nextLogPage(); } catch (_) {}
            return;
        }

        // Docs: search, glossary, nav
        if (closest('[data-action="docs-search"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.searchDocumentation === 'function') window.searchDocumentation(); } catch (_) {}
            return;
        }
        if (closest('[data-action="docs-generate-glossary"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.generateGlossary === 'function') window.generateGlossary(); } catch (_) {}
            return;
        }
        if (closest('[data-action="docs-prev-doc"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.previousDocument === 'function') window.previousDocument(); } catch (_) {}
            return;
        }
        if (closest('[data-action="docs-next-doc"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.nextDocument === 'function') window.nextDocument(); } catch (_) {}
            return;
        }

        // Database Tools: execute query
        if (closest('[data-action="db-execute-query"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.executeQuery === 'function') window.executeQuery(); } catch (_) {}
            return;
        }

        // Database Tools: switch tabs (supports db-switch-tab and db-tab)
        const dbTab = closest('[data-action="db-switch-tab"], [data-action="db-tab"]');
        if (dbTab) {
            e.preventDefault();
            const tabName = dbTab.dataset.tab;
            if (!tabName) return;
            try {
                if (typeof window !== 'undefined' && typeof window.switchDatabaseTab === 'function') {
                    window.switchDatabaseTab(dbTab, tabName);
                } else if (typeof switchDatabaseTab === 'function') {
                    switchDatabaseTab(dbTab, tabName);
                } else {
                    document.querySelectorAll('.db-tab').forEach(t => t.classList.remove('active'));
                    dbTab.classList.add('active');
                    document.querySelectorAll('.db-tab-content').forEach(c => c.classList.add('hidden'));
                    const pane = document.getElementById(`${tabName}Tab`);
                    if (pane) pane.classList.remove('hidden');
                }
            } catch (_) {}
            return;
        }

        // Database Tools: pagination
        if (closest('[data-action="db-prev-page"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.previousPage === 'function') { try { window.event = e; } catch(_){} window.previousPage(); } } catch(_) {}
            return;
        }
        if (closest('[data-action="db-next-page"]')) {
            e.preventDefault();
            try { if (typeof window !== 'undefined' && typeof window.nextPage === 'function') { try { window.event = e; } catch(_){} window.nextPage(); } } catch(_) {}
            return;
        }

        // Database Tools: sorting
        const sortTh = closest('[data-action="db-sort"]');
        if (sortTh) {
            e.preventDefault();
            const table = sortTh.dataset.table;
            const col = sortTh.dataset.col;
            const order = sortTh.dataset.order || 'ASC';
            try { if (typeof window !== 'undefined' && typeof window.sortTableData === 'function') window.sortTableData(table, col, order); } catch(_) {}
            return;
        }

        // Database Tools: cell editing
        const tdEdit = closest('[data-action="db-start-cell-edit"]');
        if (tdEdit) {
            e.preventDefault();
            const table = tdEdit.dataset.table || '';
            try { if (typeof window !== 'undefined' && typeof window.startCellEdit === 'function') window.startCellEdit(tdEdit, table); } catch(_) {}
            return;
        }
        const dbSaveBtn = closest('[data-action="db-save-cell-edit"]');
        if (dbSaveBtn) {
            e.preventDefault();
            try { if (typeof window !== 'undefined') try { window.event = e; } catch(_){} } catch(_) {}
            try { if (typeof window !== 'undefined' && typeof window.saveCellEdit === 'function') window.saveCellEdit(dbSaveBtn, e); } catch(_) {}
            return;
        }
        const dbCancelBtn = closest('[data-action="db-cancel-cell-edit"]');
        if (dbCancelBtn) {
            e.preventDefault();
            try { if (typeof window !== 'undefined') try { window.event = e; } catch(_){} } catch(_) {}
            try { if (typeof window !== 'undefined' && typeof window.cancelCellEdit === 'function') window.cancelCellEdit(dbCancelBtn, e); } catch(_) {}
            return;
        }

        // Scan Files
        if (closest('[data-action="scan-db"]')) {
            e.preventDefault();
            scanDatabaseConnections(e);
            return;
        }

        // Convert All
        if (closest('[data-action="convert-db"]')) {
            e.preventDefault();
            convertDatabaseConnections(e);
            return;
        }

        // Open Conversion Tool
        if (closest('[data-action="open-conversion-tool"]')) {
            e.preventDefault();
            openConversionTool();
            return;
        }

        // Compact & Repair
        if (closest('[data-action="compact-repair"]')) {
            e.preventDefault();
            compactRepairDatabase();
            return;
        }

        // Toggle Backup Tables
        if (closest('[data-action="toggle-backup-tables"]')) {
            e.preventDefault();
            toggleDatabaseBackupTables();
            return;
        }

        // Close Table Viewer
        if (closest('[data-action="close-table-view"]')) {
            e.preventDefault();
            closeTableViewModal();
            return;
        }

        // View Table (needs argument)
        const viewBtn = closest('[data-action="view-table"]');
        if (viewBtn) {
            e.preventDefault();
            let tableName = viewBtn.dataset.table || viewBtn.dataset.tableName;
            if (!tableName && viewBtn.dataset.onclickLegacy) {
                try {
                    const m = viewBtn.dataset.onclickLegacy.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    tableName = (m && (m[1] || m[2] || m[3] || '')).toString().trim();
                } catch(_) {}
            }
            if (tableName) {
                viewTable(tableName);
            } else {
                console.warn('[AdminSettings] view-table clicked but no table name found');
            }
            return;
        }

        // Update DB Credentials
        if (closest('[data-action="update-db-config"]')) {
            e.preventDefault();
            updateDatabaseConfig(e);
            return;
        }

        // Test SSL Connection
        if (closest('[data-action="test-ssl"]')) {
            e.preventDefault();
            testSSLConnection(e);
            return;
        }

        // Perform Export
        if (closest('[data-action="perform-export"]')) {
            e.preventDefault();
            if (typeof window !== 'undefined' && typeof window.performExport === 'function') {
                window.performExport();
            } else if (typeof performExport === 'function') {
                performExport();
            } else {
                console.warn('[AdminSettings] performExport not found');
            }
            return;
        }

        // Import SQL
        if (closest('[data-action="import-sql"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importSQLFile === 'function') ? window.importSQLFile : (typeof importSQLFile === 'function' ? importSQLFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importSQLFile not found');
            return;
        }

        // Import CSV
        if (closest('[data-action="import-csv"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importCSVFile === 'function') ? window.importCSVFile : (typeof importCSVFile === 'function' ? importCSVFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importCSVFile not found');
            return;
        }

        // Import JSON
        if (closest('[data-action="import-json"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importJSONFile === 'function') ? window.importJSONFile : (typeof importJSONFile === 'function' ? importJSONFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importJSONFile not found');
            return;
        }

        // Open Database Maintenance Modal
        if (closest('[data-action="open-db-maintenance"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.openDatabaseMaintenanceModal === 'function') {
                    window.openDatabaseMaintenanceModal();
                } else if (typeof openDatabaseMaintenanceModal === 'function') {
                    openDatabaseMaintenanceModal();
                } else {
                    console.warn('[AdminSettings] openDatabaseMaintenanceModal not found');
                }
            } catch (err) {
                console.warn('[AdminSettings] open-db-maintenance error', err);
            }
            return;
        }

        // Database Maintenance: Switch Tabs
        const dbTabBtn = closest('[data-action="db-switch-tab"]');
        if (dbTabBtn) {
            e.preventDefault();
            const tabName = dbTabBtn.dataset.tab;
            if (!tabName) {
                console.warn('[AdminSettings] db-switch-tab clicked without data-tab');
                return;
            }
            try {
                if (typeof window !== 'undefined' && typeof window.switchDatabaseTab === 'function') {
                    window.switchDatabaseTab(dbTabBtn, tabName);
                } else if (typeof switchDatabaseTab === 'function') {
                    switchDatabaseTab(dbTabBtn, tabName);
                } else {
                    // Fallback: simple class toggle
                    document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
                    dbTabBtn.classList.add('active');
                    document.querySelectorAll('.db-tab-content').forEach(c => c.classList.add('hidden'));
                    const selected = document.getElementById(`${tabName}Tab`);
                    if (selected) selected.classList.remove('hidden');
                }
            } catch (err) {
                console.warn('[AdminSettings] switchDatabaseTab failed', err);
            }
            return;
        }

        // Database Maintenance: Test Connection
        const testConnBtn = closest('[data-action="test-db-connection"]');
        if (testConnBtn) {
            e.preventDefault();
            try {
                // Legacy function reads global `event`; ensure it's available
                try { if (typeof window !== 'undefined') window.event = e; } catch (_) {}
                if (typeof window !== 'undefined' && typeof window.testDatabaseConnection === 'function') {
                    window.testDatabaseConnection(e);
                } else if (typeof testDatabaseConnection === 'function') {
                    testDatabaseConnection(e);
                } else {
                    console.warn('[AdminSettings] testDatabaseConnection not found');
                }
            } catch (err) {
                console.warn('[AdminSettings] testDatabaseConnection error', err);
            }
            return;
        }

        // Database Maintenance: Refresh Stats
        const refreshStatsBtn = closest('[data-action="refresh-db-stats"]');
        if (refreshStatsBtn) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.refreshDatabaseStats === 'function') {
                    window.refreshDatabaseStats();
                } else if (typeof refreshDatabaseStats === 'function') {
                    refreshDatabaseStats();
                } else if (typeof window.loadDatabaseStats === 'function') {
                    window.loadDatabaseStats();
                } else if (typeof loadDatabaseStats === 'function') {
                    loadDatabaseStats();
                } else {
                    console.warn('[AdminSettings] refresh/load DatabaseStats functions not found');
                }
            } catch (err) {
                console.warn('[AdminSettings] refresh-db-stats error', err);
            }
            return;
        }

        // Custom Notification: OK button
        if (closest('[data-action="custom-notification-ok"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeCustomNotification === 'function') {
                    window.closeCustomNotification();
                } else if (typeof closeCustomNotification === 'function') {
                    closeCustomNotification();
                } else {
                    const overlay = closest('.admin-modal-overlay');
                    if (overlay) overlay.remove();
                }
            } catch (err) {
                const overlay = closest('.admin-modal-overlay');
                if (overlay) overlay.remove();
            }
            return;
        }

        // Query Console: Execute
        if (closest('[data-action="execute-query"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.executeQuery === 'function') {
                    window.executeQuery();
                } else if (typeof executeQuery === 'function') {
                    executeQuery();
                } else {
                    console.warn('[AdminSettings] executeQuery not found');
                }
            } catch (err) {
                console.warn('[AdminSettings] execute-query error', err);
            }
            return;
        }

        // Query Console: Clear
        if (closest('[data-action="clear-query"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.clearQuery === 'function') {
                    window.clearQuery();
                } else if (typeof clearQuery === 'function') {
                    clearQuery();
                } else {
                    const ta = document.getElementById('sqlQuery');
                    if (ta) ta.value = '';
                    const results = document.getElementById('queryResults');
                    if (results) results.classList.add('hidden');
                }
            } catch (err) {
                console.warn('[AdminSettings] clear-query error', err);
            }
            return;
        }

        // Query Console: Load Templates (toggle)
        if (closest('[data-action="load-query-templates"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.loadQueryTemplates === 'function') {
                    window.loadQueryTemplates();
                } else if (typeof loadQueryTemplates === 'function') {
                    loadQueryTemplates();
                } else {
                    const modal = document.getElementById('queryTemplatesModal');
                    if (modal) modal.classList.toggle('hidden');
                }
            } catch (err) {
                console.warn('[AdminSettings] load-query-templates error', err);
            }
            return;
        }

        // Query Console: Hide Templates
        if (closest('[data-action="hide-query-templates"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.hideQueryTemplates === 'function') {
                    window.hideQueryTemplates();
                } else if (typeof hideQueryTemplates === 'function') {
                    hideQueryTemplates();
                } else {
                    const modal = document.getElementById('queryTemplatesModal');
                    if (modal) modal.classList.add('hidden');
                }
            } catch (err) {
                console.warn('[AdminSettings] hide-query-templates error', err);
            }
            return;
        }

        // Query Console: Insert Template
        const tmplBtn = closest('[data-action="insert-template"]');
        if (tmplBtn) {
            e.preventDefault();
            const tpl = tmplBtn.dataset.template || '';
            try {
                if (typeof window !== 'undefined' && typeof window.insertTemplate === 'function') {
                    window.insertTemplate(tpl);
                } else if (typeof insertTemplate === 'function') {
                    insertTemplate(tpl);
                } else {
                    const ta = document.getElementById('sqlQuery');
                    if (ta) ta.value = tpl;
                    const modal = document.getElementById('queryTemplatesModal');
                    if (modal) modal.classList.add('hidden');
                }
            } catch (err) {
                console.warn('[AdminSettings] insert-template error', err);
            }
            return;
        }

        // -----------------------------
        // Logs tools
        // -----------------------------
        const logCleanupBtn = closest('[data-action="run-log-cleanup"]');
        if (logCleanupBtn) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.runLogCleanup === 'function') window.runLogCleanup();
                else if (typeof runLogCleanup === 'function') runLogCleanup();
            } catch (_) {}
            return;
        }

        const logDownloadBtn = closest('[data-action="download-logs"]');
        if (logDownloadBtn) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.downloadLogs === 'function') window.downloadLogs();
                else if (typeof downloadLogs === 'function') downloadLogs();
            } catch (_) {}
            return;
        }

        // -----------------------------
        // Database tools grid
        // -----------------------------
        const dbInit = closest('[data-action="db-init"]');
        if (dbInit) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.initializeDatabase === 'function') window.initializeDatabase(); else if (typeof initializeDatabase === 'function') initializeDatabase(); } catch (_) {} return; }

        const dbOptimize = closest('[data-action="db-optimize"]');
        if (dbOptimize) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.optimizeAllTables === 'function') window.optimizeAllTables(); else if (typeof optimizeAllTables === 'function') optimizeAllTables(); } catch (_) {} return; }

        const dbAnalyzeIdx = closest('[data-action="db-analyze-indexes"]');
        if (dbAnalyzeIdx) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.analyzeIndexes === 'function') window.analyzeIndexes(); else if (typeof analyzeIndexes === 'function') analyzeIndexes(); } catch (_) {} return; }

        const dbCleanup = closest('[data-action="db-cleanup"]');
        if (dbCleanup) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.cleanupDatabase === 'function') window.cleanupDatabase(); else if (typeof cleanupDatabase === 'function') cleanupDatabase(); } catch (_) {} return; }

        const dbRepair = closest('[data-action="db-repair"]');
        if (dbRepair) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.repairTables === 'function') window.repairTables(); else if (typeof repairTables === 'function') repairTables(); } catch (_) {} return; }

        const dbAnalyzeSize = closest('[data-action="db-analyze-size"]');
        if (dbAnalyzeSize) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.analyzeDatabaseSize === 'function') window.analyzeDatabaseSize(); else if (typeof analyzeDatabaseSize === 'function') analyzeDatabaseSize(); } catch (_) {} return; }

        const dbPerf = closest('[data-action="db-performance-monitor"]');
        if (dbPerf) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.showPerformanceMonitor === 'function') window.showPerformanceMonitor(); else if (typeof showPerformanceMonitor === 'function') showPerformanceMonitor(); } catch (_) {} return; }

        const dbFK = closest('[data-action="db-check-foreign-keys"]');
        if (dbFK) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.checkForeignKeys === 'function') window.checkForeignKeys(); else if (typeof checkForeignKeys === 'function') checkForeignKeys(); } catch (_) {} return; }

        const dbExport = closest('[data-action="db-show-export-tools"]');
        if (dbExport) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.showExportTools === 'function') window.showExportTools(); else if (typeof showExportTools === 'function') showExportTools(); } catch (_) {} return; }

        const dbImport = closest('[data-action="db-show-import-tools"]');
        if (dbImport) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.showImportTools === 'function') window.showImportTools(); else if (typeof showImportTools === 'function') showImportTools(); } catch (_) {} return; }

        const dbSchema = closest('[data-action="db-show-schema-browser"]');
        if (dbSchema) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.showSchemaBrowser === 'function') window.showSchemaBrowser(); else if (typeof showSchemaBrowser === 'function') showSchemaBrowser(); } catch (_) {} return; }

        const clearTools = closest('[data-action="clear-tool-results"]');
        if (clearTools) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.clearToolResults === 'function') window.clearToolResults();
                else if (typeof clearToolResults === 'function') clearToolResults();
                else {
                    const c = document.querySelector('#toolResultsContainer'); if (c) c.innerHTML = '';
                }
            } catch (_) {}
            return;
        }

        // -----------------------------
        // CSS Rules modal
        // -----------------------------
        const cssLoad = closest('[data-action="css-load-rules"]');
        if (cssLoad) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.loadCSSRules === 'function') window.loadCSSRules(); else if (typeof loadCSSRules === 'function') loadCSSRules(); } catch (_) {} return; }

        const cssTabBtn = closest('[data-action="css-tab"]');
        if (cssTabBtn) {
            e.preventDefault();
            const tab = cssTabBtn.dataset.tab;
            try { if (typeof window !== 'undefined' && typeof window.switchCSSTab === 'function') window.switchCSSTab(tab); else if (typeof switchCSSTab === 'function') switchCSSTab(tab); } catch (_) {}
            return;
        }

        const cssReset = closest('[data-action="css-reset-defaults"]');
        if (cssReset) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.resetCSSToDefaults === 'function') window.resetCSSToDefaults(); else if (typeof resetCSSToDefaults === 'function') resetCSSToDefaults(); } catch (_) {} return; }

        const cssPreview = closest('[data-action="css-preview"]');
        if (cssPreview) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.previewChanges === 'function') window.previewChanges(); else if (typeof previewChanges === 'function') previewChanges(); } catch (_) {} return; }

        const cssSave = closest('[data-action="css-save"]');
        if (cssSave) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.saveCSSRules === 'function') window.saveCSSRules(); else if (typeof saveCSSRules === 'function') saveCSSRules(); } catch (_) {} return; }

        // -----------------------------
 // Template Manager and Assignment
 // -----------------------------
        const tmOpen = closest('[data-action="open-template-manager"]');
        if (tmOpen) {
            e.preventDefault();
            try { if (typeof openTemplateManagerModal === 'function') openTemplateManagerModal(); else if (typeof window !== 'undefined' && typeof window.openTemplateManagerModal === 'function') window.openTemplateManagerModal(); } catch (_) {}
            return;
        }

        const tmSwitch = closest('[data-action="tm-switch-tab"]');
        if (tmSwitch) {
            e.preventDefault();
            const tab = tmSwitch.dataset.tab;
            try { if (typeof window !== 'undefined' && typeof window.switchTemplateTab === 'function') window.switchTemplateTab(tab); else if (typeof switchTemplateTab === 'function') switchTemplateTab(tab); } catch (_) {}
            return;
        }

        const tmClose = closest('[data-action="template-manager-close"]');
        if (tmClose) {
            e.preventDefault();
            try { if (typeof closeTemplateManagerModal === 'function') closeTemplateManagerModal(); else if (typeof window !== 'undefined' && typeof window.closeTemplateManagerModal === 'function') window.closeTemplateManagerModal(); } catch (_) {}
            return;
        }

        if (closest('[data-action="tm-create-color-template"]')) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.createNewColorTemplate === 'function') window.createNewColorTemplate(); else if (typeof createNewColorTemplate === 'function') createNewColorTemplate(); } catch (_) {} return; }
        if (closest('[data-action="tm-create-size-template"]')) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.createNewSizeTemplate === 'function') window.createNewSizeTemplate(); else if (typeof createNewSizeTemplate === 'function') createNewSizeTemplate(); } catch (_) {} return; }
        if (closest('[data-action="tm-create-cost-template"]')) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.createNewCostTemplate === 'function') window.createNewCostTemplate(); else if (typeof createNewCostTemplate === 'function') createNewCostTemplate(); else console.warn('[AdminSettings] createNewCostTemplate not found'); } catch (_) {} return; }
        if (closest('[data-action="tm-refresh-suggestion-history"]')) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.refreshSuggestionHistory === 'function') window.refreshSuggestionHistory(); else if (typeof refreshSuggestionHistory === 'function') refreshSuggestionHistory(); } catch (_) {} return; }
        if (closest('[data-action="tm-create-email-template"]')) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.createNewEmailTemplate === 'function') window.createNewEmailTemplate(); else if (typeof createNewEmailTemplate === 'function') createNewEmailTemplate(); } catch (_) {} return; }

        // Edit/Delete Color Templates (list items)
        const tmEditColor = closest('[data-action="tm-edit-color-template"]');
        if (tmEditColor) {
            e.preventDefault();
            const id = parseInt(tmEditColor.dataset.id || '-1', 10);
            if (!isNaN(id)) {
                try { editColorTemplate(id); } catch (_) {}
            }
            return;
        }

        const tmDeleteColor = closest('[data-action="tm-delete-color-template"]');
        if (tmDeleteColor) {
            e.preventDefault();
            const id = parseInt(tmDeleteColor.dataset.id || '-1', 10);
            if (!isNaN(id)) {
                try { deleteColorTemplate(id); } catch (_) {}
            }
            return;
        }

        // Edit/Delete Size Templates (list items)
        const tmEditSize = closest('[data-action="tm-edit-size-template"]');
        if (tmEditSize) {
            e.preventDefault();
            const id = parseInt(tmEditSize.dataset.id || '-1', 10);
            if (!isNaN(id)) {
                try { editSizeTemplate(id); } catch (_) {}
            }
            return;
        }

        const tmDeleteSize = closest('[data-action="tm-delete-size-template"]');
        if (tmDeleteSize) {
            e.preventDefault();
            const id = parseInt(tmDeleteSize.dataset.id || '-1', 10);
            if (!isNaN(id)) {
                try { deleteSizeTemplate(id); } catch (_) {}
            }
            return;
        }

        // Inside Color Template Modal
        if (closest('[data-action="color-template-add-color"]')) {
            e.preventDefault();
            try { addColorToTemplate(); } catch (_) {}
            return;
        }

        const colorRemoveBtn = closest('[data-action="color-template-remove-color"]');
        if (colorRemoveBtn) {
            e.preventDefault();
            const idx = parseInt(colorRemoveBtn.dataset.index || '-1', 10);
            if (!isNaN(idx)) {
                try { removeColorFromTemplate(idx); } catch (_) {}
            }
            return;
        }

        // Close Color Template Modal (buttons and overlay)
        const colorCloseBtn = target.closest('#colorTemplateEditModal [data-action="close-color-template-modal"]');
        if (colorCloseBtn) {
            e.preventDefault();
            try { if (typeof closeColorTemplateEditModal === 'function') closeColorTemplateEditModal(); else if (typeof window !== 'undefined' && typeof window.closeColorTemplateEditModal === 'function') window.closeColorTemplateEditModal(); } catch (_) {}
            return;
        }
        const colorOverlay = document.getElementById('colorTemplateEditModal');
        if (colorOverlay && target === colorOverlay) {
            e.preventDefault();
            try { if (typeof closeColorTemplateEditModal === 'function') closeColorTemplateEditModal(); else if (typeof window !== 'undefined' && typeof window.closeColorTemplateEditModal === 'function') window.closeColorTemplateEditModal(); } catch (_) {}
            return;
        }

        // Inside Size Template Modal
        if (closest('[data-action="size-template-add-size"]')) {
            e.preventDefault();
            try { addSizeToTemplate(); } catch (_) {}
            return;
        }

        const sizeRemoveBtn = closest('[data-action="size-template-remove-size"]');
        if (sizeRemoveBtn) {
            e.preventDefault();
            const idx = parseInt(sizeRemoveBtn.dataset.index || '-1', 10);
            if (!isNaN(idx)) {
                try { removeSizeFromTemplate(idx); } catch (_) {}
            }
            return;
        }

        // Close Size Template Modal (buttons and overlay)
        const sizeCloseBtn = target.closest('#sizeTemplateEditModal [data-action="close-size-template-modal"]');
        if (sizeCloseBtn) {
            e.preventDefault();
            try { if (typeof closeSizeTemplateEditModal === 'function') closeSizeTemplateEditModal(); else if (typeof window !== 'undefined' && typeof window.closeSizeTemplateEditModal === 'function') window.closeSizeTemplateEditModal(); } catch (_) {}
            return;
        }
        const sizeOverlay = document.getElementById('sizeTemplateEditModal');
        if (sizeOverlay && target === sizeOverlay) {
            e.preventDefault();
            try { if (typeof closeSizeTemplateEditModal === 'function') closeSizeTemplateEditModal(); else if (typeof window !== 'undefined' && typeof window.closeSizeTemplateEditModal === 'function') window.closeSizeTemplateEditModal(); } catch (_) {}
            return;
        }

        const saveTA = closest('[data-action="save-template-assignment"]');
        if (saveTA) { e.preventDefault(); try { if (typeof window !== 'undefined' && typeof window.saveTemplateAssignment === 'function') window.saveTemplateAssignment(); else if (typeof saveTemplateAssignment === 'function') saveTemplateAssignment(); } catch (_) {} return; }

        const tmEditCost = closest('[data-action="tm-edit-cost-template"]');
        if (tmEditCost) { e.preventDefault(); const id = parseInt(tmEditCost.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.editCostTemplate === 'function') window.editCostTemplate(id); else if (typeof editCostTemplate === 'function') editCostTemplate(id); else console.warn('[AdminSettings] editCostTemplate not found'); } catch (_) {} return; }

        const tmDeleteCost = closest('[data-action="tm-delete-cost-template"]');
        if (tmDeleteCost) { e.preventDefault(); const id = parseInt(tmDeleteCost.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.deleteCostTemplate === 'function') window.deleteCostTemplate(id); else if (typeof deleteCostTemplate === 'function') deleteCostTemplate(id); else console.warn('[AdminSettings] deleteCostTemplate not found'); } catch (_) {} return; }

        const tmPreviewEmail = closest('[data-action="tm-preview-email-template"]');
        if (tmPreviewEmail) { e.preventDefault(); const id = parseInt(tmPreviewEmail.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.previewEmailTemplate === 'function') window.previewEmailTemplate(id); else if (typeof previewEmailTemplate === 'function') previewEmailTemplate(id); } catch (_) {} return; }

        const tmSendTestEmail = closest('[data-action="tm-send-test-email"]');
        if (tmSendTestEmail) { e.preventDefault(); const id = parseInt(tmSendTestEmail.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.sendTestEmail === 'function') { const fn = window.sendTestEmail; if (typeof fn === 'function' && fn.length >= 1) fn(id); else fn(); } else if (typeof sendTestEmail === 'function') { if (sendTestEmail.length >= 1) sendTestEmail(id); else sendTestEmail(); } else { console.warn('[AdminSettings] sendTestEmail handler not found'); } } catch (_) {} return; }

        const tmEditEmail = closest('[data-action="tm-edit-email-template"]');
        if (tmEditEmail) { e.preventDefault(); const id = parseInt(tmEditEmail.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.editEmailTemplate === 'function') window.editEmailTemplate(id); else if (typeof editEmailTemplate === 'function') editEmailTemplate(id); } catch (_) {} return; }

        const tmDeleteEmail = closest('[data-action="tm-delete-email-template"]');
        if (tmDeleteEmail) { e.preventDefault(); const id = parseInt(tmDeleteEmail.dataset.id || '-1', 10); try { if (typeof window !== 'undefined' && typeof window.deleteEmailTemplate === 'function') window.deleteEmailTemplate(id); else if (typeof deleteEmailTemplate === 'function') deleteEmailTemplate(id); } catch (_) {} return; }

        

        // Close Color Template Modal (background overlay click or explicit close buttons)
        {
            // If a button with data-action was clicked
            const closeBtn = target && target.matches && target.matches('[data-action="close-color-template-modal"]');
            // If the click landed directly on the overlay element
            const overlay = document.getElementById('colorTemplateEditModal');
            const overlayClick = overlay && target === overlay;
            if (closeBtn || overlayClick) {
                e.preventDefault();
                try { if (typeof window !== 'undefined' && typeof window.closeColorTemplateEditModal === 'function') window.closeColorTemplateEditModal(); else if (typeof closeColorTemplateEditModal === 'function') closeColorTemplateEditModal(); else if (overlay) overlay.classList.add('hidden'); } catch (_) {}
                return;
            }
        }

        // Close Size Template Modal (background overlay click or explicit close buttons)
        {
            const closeBtn = target && target.matches && target.matches('[data-action="close-size-template-modal"]');
            const overlay = document.getElementById('sizeTemplateEditModal');
            const overlayClick = overlay && target === overlay;
            if (closeBtn || overlayClick) {
                e.preventDefault();
                try { if (typeof window !== 'undefined' && typeof window.closeSizeTemplateEditModal === 'function') window.closeSizeTemplateEditModal(); else if (typeof closeSizeTemplateEditModal === 'function') closeSizeTemplateEditModal(); else if (overlay) overlay.classList.add('hidden'); } catch (_) {}
                return;
            }
        }

        // Retry: Load System Configuration
        if (closest('[data-action="retry-load-system-config"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.loadSystemConfiguration === 'function') {
                    window.loadSystemConfiguration();
                } else if (typeof loadSystemConfiguration === 'function') {
                    loadSystemConfiguration();
                }
            } catch (_) {}
            return;
        }

        // Retry: Load Database Information
        if (closest('[data-action="retry-load-db-info"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.loadDatabaseInformation === 'function') {
                    window.loadDatabaseInformation();
                } else if (typeof _loadDatabaseInformation === 'function') {
                    _loadDatabaseInformation();
                }
            } catch (_) {}
            return;
        }

        // Close Admin Modal (icon or footer button)
        const closeBtn = closest('[data-action="close-admin-modal"]');
        if (closeBtn) {
            e.preventDefault();
            const overlay = closeBtn.closest('.admin-modal-overlay') || closeBtn.closest('.admin-modal');
            if (overlay) {
                const id = overlay.id || '';
                if (id === 'roomSettingsModal' && typeof window !== 'undefined' && typeof window.closeRoomSettingsModal === 'function') {
                    try { window.closeRoomSettingsModal(); } catch (_) { overlay.classList.add('hidden'); }
                } else if (id === 'roomCategoryMapperModal' && typeof window !== 'undefined' && typeof window.closeRoomCategoryMapperModal === 'function') {
                    try { window.closeRoomCategoryMapperModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'areaItemMapperModal' && typeof window !== 'undefined' && typeof window.closeAreaItemMapperModal === 'function') {
                    try { window.closeAreaItemMapperModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'systemConfigModal' && typeof window !== 'undefined' && typeof window.closeSystemConfigModal === 'function') {
                    try { window.closeSystemConfigModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'backupModal' && typeof window !== 'undefined' && typeof window.closeBackupModal === 'function') {
                    try { window.closeBackupModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'databaseBackupModal' && typeof window !== 'undefined' && typeof window.closeDatabaseBackupModal === 'function') {
                    try { window.closeDatabaseBackupModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'databaseRestoreModal' && typeof window !== 'undefined' && typeof window.closeDatabaseRestoreModal === 'function') {
                    try { window.closeDatabaseRestoreModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'databaseToolResultsModal' && typeof window !== 'undefined' && typeof window.closeDatabaseToolResultsModal === 'function') {
                    try { window.closeDatabaseToolResultsModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'tableViewModal' && typeof window !== 'undefined' && typeof window.closeTableViewModal === 'function') {
                    try { window.closeTableViewModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'fileExplorerModal' && typeof window !== 'undefined' && typeof window.closeFileExplorerModal === 'function') {
                    try { window.closeFileExplorerModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'cssRulesModal' && typeof window !== 'undefined' && typeof window.closeCSSRulesModal === 'function') {
                    try { window.closeCSSRulesModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'templateManagerModal' && typeof window !== 'undefined' && typeof window.closeTemplateManagerModal === 'function') {
                    try { window.closeTemplateManagerModal(); } catch (_) { overlay.remove(); }
                } else if (id === 'templateAssignmentModal' && typeof window !== 'undefined' && typeof window.closeTemplateAssignmentModal === 'function') {
                    try { window.closeTemplateAssignmentModal(); } catch (_) { overlay.remove(); }
                } else {
                    overlay.remove();
                }
            }
            return;
        }

        // Modal Cancel with optional callback
        const cancelBtn = closest('[data-action="modal-cancel"]');
        if (cancelBtn) {
            e.preventDefault();
            const overlay = cancelBtn.closest('.admin-modal-overlay') || cancelBtn.closest('.admin-modal');
            if (overlay) overlay.remove();
            const cb = cancelBtn.dataset.callback;
            if (cb) invokeCallback(cb);
            return;
        }

        // Modal Confirm with callback
        const confirmBtn = closest('[data-action="modal-confirm"]');
        if (confirmBtn) {
            e.preventDefault();
            const overlay = confirmBtn.closest('.admin-modal-overlay') || confirmBtn.closest('.admin-modal');
            if (overlay) overlay.remove();
            const cb = confirmBtn.dataset.callback;
            if (cb) invokeCallback(cb);
            return;
        }

        // Maintenance confirm modal actions
        const maintCancel = closest('[data-action="maintenance-cancel"]');
        if (maintCancel) {
            e.preventDefault();
            const overlay = maintCancel.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            try { if (typeof window !== 'undefined' && typeof window.maintenanceConfirmResolve === 'function') window.maintenanceConfirmResolve(false); } catch (_) {}
            return;
        }

        const maintContinue = closest('[data-action="maintenance-continue"]');
        if (maintContinue) {
            e.preventDefault();
            const overlay = maintContinue.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            try { if (typeof window !== 'undefined' && typeof window.maintenanceConfirmResolve === 'function') window.maintenanceConfirmResolve(true); } catch (_) {}
            return;
        }

        // Click on overlay background to close (only when clicking the overlay itself)
        if (target && target.matches && target.matches('[data-action="overlay-close"]')) {
            // Only close if the click target IS the overlay, not inner content
            if (e.target === target) {
                const id = target.id || '';
                try {
                    if (id === 'roomCategoryManagerModal' && typeof window.closeRoomCategoryManagerModal === 'function') {
                        window.closeRoomCategoryManagerModal();
                    } else if (id === 'backgroundManagerModal' && typeof window.closeBackgroundManagerModal === 'function') {
                        window.closeBackgroundManagerModal();
                    } else if (id === 'aiSettingsModal' && typeof window.closeAISettingsModal === 'function') {
                        window.closeAISettingsModal();
                    } else if (id === 'roomSettingsModal' && typeof window.closeRoomSettingsModal === 'function') {
                        // Hide the Room Settings modal rather than removing it from the DOM
                        window.closeRoomSettingsModal();
                    } else if (id === 'systemConfigModal' && typeof window.closeSystemConfigModal === 'function') {
                        window.closeSystemConfigModal();
                    } else if (id === 'backupModal' && typeof window.closeBackupModal === 'function') {
                        window.closeBackupModal();
                    } else if (id === 'databaseBackupModal' && typeof window.closeDatabaseBackupModal === 'function') {
                        window.closeDatabaseBackupModal();
                    } else if (id === 'databaseRestoreModal' && typeof window.closeDatabaseRestoreModal === 'function') {
                        window.closeDatabaseRestoreModal();
                    } else if (id === 'roomCategoryMapperModal' && typeof window.closeRoomCategoryMapperModal === 'function') {
                        window.closeRoomCategoryMapperModal();
                    } else if (id === 'areaItemMapperModal' && typeof window.closeAreaItemMapperModal === 'function') {
                        window.closeAreaItemMapperModal();
                    } else if (id === 'databaseToolResultsModal' && typeof window.closeDatabaseToolResultsModal === 'function') {
                        window.closeDatabaseToolResultsModal();
                    } else if (id === 'tableViewModal' && typeof window.closeTableViewModal === 'function') {
                        window.closeTableViewModal();
                    } else if (id === 'fileExplorerModal' && typeof window.closeFileExplorerModal === 'function') {
                        window.closeFileExplorerModal();
                    } else if (id === 'cssRulesModal' && typeof window.closeCSSRulesModal === 'function') {
                        window.closeCSSRulesModal();
                    } else if (id === 'templateManagerModal' && typeof window.closeTemplateManagerModal === 'function') {
                        window.closeTemplateManagerModal();
                    } else if (id === 'templateAssignmentModal' && typeof window.closeTemplateAssignmentModal === 'function') {
                        window.closeTemplateAssignmentModal();
                    } else {
                        target.remove();
                    }
                } catch (_) { target.remove(); }
            }
            return;
        }

        // Open Room-Category Manager for a specific room
        const openRCM = closest('[data-action="open-room-category-manager"]');
        if (openRCM) {
            e.preventDefault();
            const room = openRCM.dataset.room ? (isNaN(openRCM.dataset.room) ? openRCM.dataset.room : parseInt(openRCM.dataset.room, 10)) : null;
            if (typeof window.openRoomCategoryManagerModal === 'function') {
                window.openRoomCategoryManagerModal(room);
            } else if (typeof openRoomCategoryManagerModal === 'function') {
                openRoomCategoryManagerModal(room);
            }
            return;
        }

        // Add Room Category
        if (closest('[data-action="add-room-category"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addRoomCategory === 'function') ? window.addRoomCategory : (typeof addRoomCategory === 'function' ? addRoomCategory : null);
            if (fn) fn(); else console.warn('[AdminSettings] addRoomCategory not found');
            return;
        }

        // Open Background Manager
        const openBgMgr = closest('[data-action="open-background-manager"]');
        if (openBgMgr) {
            e.preventDefault();
            const room = openBgMgr.dataset.room || '';
            const fn = (typeof window !== 'undefined' && typeof window.openBackgroundManagerModal === 'function') ? window.openBackgroundManagerModal : (typeof openBackgroundManagerModal === 'function' ? openBackgroundManagerModal : null);
            if (fn) fn(room); else console.warn('[AdminSettings] openBackgroundManagerModal not found');
            return;
        }

        // Set Primary Category
        const setPrimaryBtn = closest('[data-action="set-primary-category"]');
        if (setPrimaryBtn) {
            e.preventDefault();
            const room = setPrimaryBtn.dataset.room;
            const catId = setPrimaryBtn.dataset.categoryId || setPrimaryBtn.dataset.categoryid;
            const fn = (typeof window !== 'undefined' && typeof window.setPrimaryCategory === 'function') ? window.setPrimaryCategory : (typeof setPrimaryCategory === 'function' ? setPrimaryCategory : null);
            if (fn && room != null && catId != null) {
                fn(isNaN(room) ? room : parseInt(room, 10), parseInt(catId, 10));
            } else {
                console.warn('[AdminSettings] setPrimaryCategory missing args or not found');
            }
            return;
        }

        // Remove Room Category
        const removeRCBtn = closest('[data-action="remove-room-category"]');
        if (removeRCBtn) {
            e.preventDefault();
            const id = removeRCBtn.dataset.assignmentId || removeRCBtn.dataset.assignmentid;
            const fn = (typeof window !== 'undefined' && typeof window.removeRoomCategory === 'function') ? window.removeRoomCategory : (typeof removeRoomCategory === 'function' ? removeRoomCategory : null);
            if (fn && id != null) fn(parseInt(id, 10)); else console.warn('[AdminSettings] removeRoomCategory not found');
            return;
        }

        // Upload Background
        if (closest('[data-action="upload-background"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.uploadBackground === 'function') ? window.uploadBackground : (typeof uploadBackground === 'function' ? uploadBackground : null);
            if (fn) fn(); else console.warn('[AdminSettings] uploadBackground not found');
            return;
        }

        // Apply Background
        const applyBgBtn = closest('[data-action="apply-background"]');
        if (applyBgBtn) {
            e.preventDefault();
            const room = applyBgBtn.dataset.room;
            const bgId = applyBgBtn.dataset.backgroundId || applyBgBtn.dataset.backgroundid;
            const fn = (typeof window !== 'undefined' && typeof window.applyBackground === 'function') ? window.applyBackground : (typeof applyBackground === 'function' ? applyBackground : null);
            if (fn && room != null && bgId != null) fn(room, parseInt(bgId, 10)); else console.warn('[AdminSettings] applyBackground missing args or not found');
            return;
        }

        // Delete Background
        const delBgBtn = closest('[data-action="delete-background"]');
        if (delBgBtn) {
            e.preventDefault();
            const id = delBgBtn.dataset.backgroundId || delBgBtn.dataset.backgroundid;
            const name = delBgBtn.dataset.backgroundName || delBgBtn.dataset.backgroundname || '';
            const fn = (typeof window !== 'undefined' && typeof window.deleteBackground === 'function') ? window.deleteBackground : (typeof deleteBackground === 'function' ? deleteBackground : null);
            if (fn && id != null) fn(parseInt(id, 10), name); else console.warn('[AdminSettings] deleteBackground missing args or not found');
            return;
        }

        // Preview Background
        const previewBtn = closest('[data-action="preview-background"]');
        if (previewBtn) {
            e.preventDefault();
            const url = previewBtn.dataset.imageUrl || previewBtn.dataset.imageurl;
            const name = previewBtn.dataset.backgroundName || previewBtn.dataset.backgroundname || '';
            const fn = (typeof window !== 'undefined' && typeof window.previewBackground === 'function') ? window.previewBackground : (typeof previewBackground === 'function' ? previewBackground : null);
            if (fn && url) fn(url, name); else console.warn('[AdminSettings] previewBackground missing args or not found');
            return;
        }

        // Close Preview Overlay
        const closePreview = closest('[data-action="close-preview"]');
        if (closePreview) {
            e.preventDefault();
            const overlay = closePreview.closest('.fixed') || closePreview.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            return;
        }

        // -----------------------------
        // Map History actions
        // -----------------------------
        const mapRestoreBtn = closest('[data-action="map-restore"]');
        if (mapRestoreBtn) {
            e.preventDefault();
            const id = parseInt(mapRestoreBtn.dataset.mapId || mapRestoreBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapRestoreBtn.dataset.mapName || mapRestoreBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const apply = String(mapRestoreBtn.dataset.apply || '').toLowerCase() === 'true';
            const fn = (typeof window !== 'undefined' && typeof window.restoreMap === 'function') ? window.restoreMap : (typeof restoreMap === 'function' ? restoreMap : null);
            if (fn && id >= 0) fn(id, name, apply); else console.warn('[AdminSettings] restoreMap not found or bad args');
            return;
        }

        const mapPreviewBtn = closest('[data-action="map-preview"]');
        if (mapPreviewBtn) {
            e.preventDefault();
            const id = parseInt(mapPreviewBtn.dataset.mapId || mapPreviewBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapPreviewBtn.dataset.mapName || mapPreviewBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const fn = (typeof window !== 'undefined' && typeof window.previewHistoricalMap === 'function') ? window.previewHistoricalMap : (typeof previewHistoricalMap === 'function' ? previewHistoricalMap : null);
            if (fn && id >= 0) fn(id, name); else console.warn('[AdminSettings] previewHistoricalMap not found or bad args');
            return;
        }

        const mapDeleteBtn = closest('[data-action="map-delete"]');
        if (mapDeleteBtn) {
            e.preventDefault();
            const id = parseInt(mapDeleteBtn.dataset.mapId || mapDeleteBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapDeleteBtn.dataset.mapName || mapDeleteBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const fn = (typeof window !== 'undefined' && typeof window.deleteHistoricalMap === 'function') ? window.deleteHistoricalMap : (typeof deleteHistoricalMap === 'function' ? deleteHistoricalMap : null);
            if (fn && id >= 0) fn(id, name); else console.warn('[AdminSettings] deleteHistoricalMap not found or bad args');
            return;
        }

        // -----------------------------
        // Email History and Email actions
        // -----------------------------
        const emailViewBtn = closest('[data-action="email-view"]');
        if (emailViewBtn) {
            e.preventDefault();
            const id = parseInt(emailViewBtn.dataset.emailId || emailViewBtn.dataset.emailid || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.viewEmailDetails === 'function') ? window.viewEmailDetails : (typeof viewEmailDetails === 'function' ? viewEmailDetails : null);
            if (fn && id >= 0) fn(id); else console.warn('[AdminSettings] viewEmailDetails not found or bad id');
            return;
        }

        const emailEditBtn = closest('[data-action="email-edit-resend"]');
        if (emailEditBtn) {
            e.preventDefault();
            const id = parseInt(emailEditBtn.dataset.emailId || emailEditBtn.dataset.emailid || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.editAndResendEmail === 'function') ? window.editAndResendEmail : (typeof editAndResendEmail === 'function' ? editAndResendEmail : null);
            if (fn && id >= 0) fn(id); else console.warn('[AdminSettings] editAndResendEmail not found or bad id');
            return;
        }

        if (closest('[data-action="email-history-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailHistoryModal === 'function') {
                    window.closeEmailHistoryModal();
                } else {
                    const m = document.getElementById('emailHistoryModal') || (document.querySelector('#emailHistoryModal'));
                    if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-history-filter"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.loadEmailHistory === 'function') ? window.loadEmailHistory : (typeof loadEmailHistory === 'function' ? loadEmailHistory : null);
            if (fn) fn(); else console.warn('[AdminSettings] loadEmailHistory not found');
            return;
        }

        const emailPageBtn = closest('[data-action="email-history-page"]');
        if (emailPageBtn) {
            e.preventDefault();
            const dir = emailPageBtn.dataset.direction || '';
            const fn = (typeof window !== 'undefined' && typeof window.loadEmailHistoryPage === 'function') ? window.loadEmailHistoryPage : (typeof loadEmailHistoryPage === 'function' ? loadEmailHistoryPage : null);
            if (fn && dir) fn(dir); else console.warn('[AdminSettings] loadEmailHistoryPage not found or dir missing');
            return;
        }

        if (closest('[data-action="email-edit-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailEditModal === 'function') {
                    window.closeEmailEditModal();
                } else {
                    const m = document.getElementById('emailEditModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-template-edit-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailEditModal === 'function') {
                    window.closeEmailEditModal();
                } else {
                    const m = document.getElementById('emailTemplateEditModal'); if (m) m.remove();
                }
                try { updateModalScrollLock(); } catch (_) {}
            } catch (_) {}
            return;
        }

        const dismissBtn = closest('[data-action="dismiss-notification"]');
        if (dismissBtn) {
            e.preventDefault();
            const box = dismissBtn.closest('.bg-green-100') || dismissBtn.closest('.shadow-lg') || (dismissBtn.parentElement && dismissBtn.parentElement.parentElement) || dismissBtn.closest('.admin-modal-overlay');
            if (box && box.remove) box.remove();
            return;
        }

        if (closest('[data-action="email-config-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailConfigModal === 'function') {
                    window.closeEmailConfigModal();
                } else {
                    const m = document.getElementById('emailConfigModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-send-test"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.sendTestEmail === 'function') ? window.sendTestEmail : (typeof sendTestEmail === 'function' ? sendTestEmail : null);
            if (fn) fn(); else console.warn('[AdminSettings] sendTestEmail not found');
            return;
        }

        if (closest('[data-action="template-manager-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeTemplateManagerModal === 'function') {
                    window.closeTemplateManagerModal();
                } else {
                    const overlay = document.getElementById('templateManagerModal') || (document.querySelector('#templateManagerModal')) || (document.querySelector('#emailTemplateManagerModal'));
                    const m = overlay || (document.querySelector('#templateManagerModal .admin-modal-overlay')) || (document.querySelector('.admin-modal-overlay'));
                    if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-template-edit-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailTemplateEditModal === 'function') {
                    window.closeEmailTemplateEditModal();
                } else {
                    const m = document.getElementById('emailTemplateEditModal'); if (m) m.remove();
                }
                try { updateModalScrollLock(); } catch (_) {}
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-template-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveEmailTemplate === 'function') ? window.saveEmailTemplate : (typeof saveEmailTemplate === 'function' ? saveEmailTemplate : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveEmailTemplate not found');
            return;
        }

        if (closest('[data-action="email-template-preview-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailTemplatePreviewModal === 'function') {
                    window.closeEmailTemplatePreviewModal();
                } else {
                    const m = document.getElementById('emailTemplatePreviewModal'); if (m) m.remove();
                }
                try { updateModalScrollLock(); } catch (_) {}
            } catch (_) {}
            return;
        }

        // Email Template list actions (Preview/Edit/Delete/Test)
        const previewTplBtn = closest('[data-action="tm-preview-email-template"]');
        if (previewTplBtn) {
            e.preventDefault();
            const id = previewTplBtn.dataset.id || previewTplBtn.getAttribute('data-id') || '';
            if (!id) { console.warn('[AdminSettings] preview: missing template id'); return; }
            try {
                if (typeof previewEmailTemplate === 'function') {
                    previewEmailTemplate(id);
                } else if (typeof window !== 'undefined' && typeof window.previewEmailTemplate === 'function') {
                    window.previewEmailTemplate(id);
                } else {
                    console.warn('[AdminSettings] previewEmailTemplate not found');
                }
            } catch (err) { console.error('[AdminSettings] preview handler error', err); }
            return;
        }

        const editTplBtn = closest('[data-action="tm-edit-email-template"]');
        if (editTplBtn) {
            e.preventDefault();
            const id = editTplBtn.dataset.id || editTplBtn.getAttribute('data-id') || '';
            if (!id) { console.warn('[AdminSettings] edit: missing template id'); return; }
            try {
                if (typeof window !== 'undefined' && typeof window.editEmailTemplate === 'function') {
                    window.editEmailTemplate(id);
                } else if (typeof window !== 'undefined' && typeof window.showEmailTemplateEditModal === 'function') {
                    // Fallback: fetch template then open editor
                    ApiClient.get('/api/email_templates.php', { action: 'get_template', template_id: id })
                        .then(data => {
                            if (data && data.success) window.showEmailTemplateEditModal(data.template);
                            else if (typeof window.showError === 'function') window.showError('Failed to load template');
                        })
                        .catch(err => console.error('[AdminSettings] edit fetch error', err));
                } else {
                    console.warn('[AdminSettings] editEmailTemplate/showEmailTemplateEditModal not found');
                }
            } catch (err) { console.error('[AdminSettings] edit handler error', err); }
            return;
        }

        const deleteTplBtn = closest('[data-action="tm-delete-email-template"]');
        if (deleteTplBtn) {
            e.preventDefault();
            const id = deleteTplBtn.dataset.id || deleteTplBtn.getAttribute('data-id') || '';
            if (!id) { console.warn('[AdminSettings] delete: missing template id'); return; }
            try {
                if (typeof window !== 'undefined' && typeof window.deleteEmailTemplate === 'function') {
                    window.deleteEmailTemplate(id);
                } else if (typeof deleteEmailTemplate === 'function') {
                    deleteEmailTemplate(id);
                } else {
                    console.warn('[AdminSettings] deleteEmailTemplate not found');
                }
            } catch (err) { console.error('[AdminSettings] delete handler error', err); }
            return;
        }

        const testTplBtn = closest('[data-action="tm-send-test-email"]');
        if (testTplBtn) {
            e.preventDefault();
            const id = testTplBtn.dataset.id || testTplBtn.getAttribute('data-id') || '';
            try {
                if (typeof window !== 'undefined' && typeof window.sendTestEmailTemplate === 'function') {
                    window.sendTestEmailTemplate(id);
                } else if (typeof window !== 'undefined' && typeof window.sendTestEmail === 'function') {
                    // Generic test path (no template specificity)
                    window.sendTestEmail();
                } else {
                    console.warn('[AdminSettings] send test email not implemented');
                    if (typeof window !== 'undefined' && typeof window.showError === 'function') window.showError('Send test email is not implemented yet.');
                }
            } catch (err) { console.error('[AdminSettings] test handler error', err); }
            return;
        }

        // -----------------------------
        // Area-Item Mapper actions
        // -----------------------------
        if (closest('[data-action="area-mapping-add"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.addAreaMapping === 'function') {
                    window.addAreaMapping();
                } else if (typeof addAreaMapping === 'function') {
                    addAreaMapping();
                }
            } catch (_) {}
            return;
        }

        const areaRemoveBtn = closest('[data-action="area-mapping-remove"]');
        if (areaRemoveBtn) {
            e.preventDefault();
            const id = parseInt(areaRemoveBtn.dataset.mappingId || areaRemoveBtn.dataset.mappingid || '-1', 10);
            try {
                if (typeof window !== 'undefined' && typeof window.removeAreaMapping === 'function') {
                    window.removeAreaMapping(id);
                } else if (typeof removeAreaMapping === 'function') {
                    removeAreaMapping(id);
                }
            } catch (_) {}
            return;
        }

        // -----------------------------
        // AI Settings Modal actions
        // -----------------------------
        if (closest('[data-action="ai-close-settings"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeAISettingsModal === 'function') {
                    window.closeAISettingsModal();
                } else {
                    const overlay = document.getElementById('aiSettingsModal');
                    if (overlay) overlay.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="ai-save-settings"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveAISettings === 'function') ? window.saveAISettings : (typeof saveAISettings === 'function' ? saveAISettings : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveAISettings not found');
            return;
        }

        if (closest('[data-action="ai-test-provider"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.testAIProvider === 'function') ? window.testAIProvider : (typeof testAIProvider === 'function' ? testAIProvider : null);
            if (fn) fn(); else console.warn('[AdminSettings] testAIProvider not found');
            return;
        }

        const aiToggle = closest('[data-action="ai-toggle-section"]');
        if (aiToggle) {
            e.preventDefault();
            const section = aiToggle.dataset.section;
            try {
                if (typeof window !== 'undefined' && typeof window.toggleSection === 'function') {
                    window.toggleSection(section);
                } else if (section) {
                    // Generic toggle fallback
                    const content = document.getElementById(`${section}-content`);
                    const icon = document.getElementById(`${section}-icon`);
                    if (content) content.classList.toggle('hidden');
                    if (icon) icon.textContent = content && !content.classList.contains('hidden') ? '▼' : '▶';
                }
            } catch (_) {}
            return;
        }

        const refreshBtn = closest('[data-action="ai-refresh-models"]');
        if (refreshBtn) {
            e.preventDefault();
            const provider = refreshBtn.dataset.provider;
            const fn = (typeof window !== 'undefined' && typeof window.refreshModels === 'function') ? window.refreshModels : (typeof refreshModels === 'function' ? refreshModels : null);
            if (fn && provider) fn(provider); else console.warn('[AdminSettings] refreshModels not found or provider missing');
            return;
        }

        if (closest('[data-action="ai-manage-brand-voice"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.manageBrandVoiceOptions === 'function') ? window.manageBrandVoiceOptions : (typeof manageBrandVoiceOptions === 'function' ? manageBrandVoiceOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] manageBrandVoiceOptions not found');
            return;
        }

        if (closest('[data-action="ai-manage-content-tone"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.manageContentToneOptions === 'function') ? window.manageContentToneOptions : (typeof manageContentToneOptions === 'function' ? manageContentToneOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] manageContentToneOptions not found');
            return;
        }

        // -----------------------------
        // Content Tone modal actions
        // -----------------------------
        if (closest('[data-action="content-tone-close"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.closeContentToneModal === 'function') ? window.closeContentToneModal : (typeof closeContentToneModal === 'function' ? closeContentToneModal : null);
            if (fn) fn();
            else {
                const m = document.getElementById('contentToneModal'); if (m) m.remove();
            }
            return;
        }
        if (closest('[data-action="content-tone-add"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addContentToneOption === 'function') ? window.addContentToneOption : (typeof addContentToneOption === 'function' ? addContentToneOption : null);
            if (fn) fn(); else console.warn('[AdminSettings] addContentToneOption not found');
            return;
        }
        if (closest('[data-action="content-tone-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveContentToneOptions === 'function') ? window.saveContentToneOptions : (typeof saveContentToneOptions === 'function' ? saveContentToneOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveContentToneOptions not found');
            return;
        }
        const ctRemove = closest('[data-action="content-tone-remove"]');
        if (ctRemove) {
            e.preventDefault();
            const idx = parseInt(ctRemove.dataset.index || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.removeContentToneOption === 'function') ? window.removeContentToneOption : (typeof removeContentToneOption === 'function' ? removeContentToneOption : null);
            if (fn && !isNaN(idx)) fn(idx); else console.warn('[AdminSettings] removeContentToneOption missing args or not found');
            return;
        }

        // -----------------------------
        // Brand Voice modal actions
        // -----------------------------
        if (closest('[data-action="brand-voice-close"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.closeBrandVoiceModal === 'function') ? window.closeBrandVoiceModal : (typeof closeBrandVoiceModal === 'function' ? closeBrandVoiceModal : null);
            if (fn) fn();
            else {
                const m = document.getElementById('brandVoiceModal'); if (m) m.remove();
            }
            return;
        }
        if (closest('[data-action="brand-voice-add"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addBrandVoiceOption === 'function') ? window.addBrandVoiceOption : (typeof addBrandVoiceOption === 'function' ? addBrandVoiceOption : null);
            if (fn) fn(); else console.warn('[AdminSettings] addBrandVoiceOption not found');
            return;
        }
        if (closest('[data-action="brand-voice-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveBrandVoiceOptions === 'function') ? window.saveBrandVoiceOptions : (typeof saveBrandVoiceOptions === 'function' ? saveBrandVoiceOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveBrandVoiceOptions not found');
            return;
        }
        const bvRemove = closest('[data-action="brand-voice-remove"]');
        if (bvRemove) {
            e.preventDefault();
            const idx = parseInt(bvRemove.dataset.index || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.removeBrandVoiceOption === 'function') ? window.removeBrandVoiceOption : (typeof removeBrandVoiceOption === 'function' ? removeBrandVoiceOption : null);
            if (fn && !isNaN(idx)) fn(idx); else console.warn('[AdminSettings] removeBrandVoiceOption missing args or not found');
            return;
        }
    }, true);

    // Live updates:
    // 1) numeric range displays via data-value-target (textContent)
    // 2) mirrored inputs via data-target-id (sync value without recursion)
    document.addEventListener('input', (e) => {
        const t = e.target;
        if (!t || !t.getAttribute) return;

        // 1) Update text content targets
        const valueTargetId = t.getAttribute('data-value-target');
        if (valueTargetId) {
            const out = document.getElementById(valueTargetId);
            if (out) out.textContent = t.value;
        }

        // 2) Mirror value to paired control
        const mirrorTargetId = t.getAttribute('data-target-id');
        if (mirrorTargetId) {
            const dest = document.getElementById(mirrorTargetId);
            if (dest && 'value' in dest) {
                if (dest.value !== t.value) {
                    // Prevent feedback loop by marking the destination during sync
                    if (!window.WF_InputMirrorSync) window.WF_InputMirrorSync = new WeakSet();
                    if (window.WF_InputMirrorSync.has(t)) return;
                    window.WF_InputMirrorSync.add(dest);
                    try { dest.value = t.value; } catch (_) {}
                    try { dest.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
                    window.WF_InputMirrorSync.delete(dest);
                }
            } else if (dest) {
                dest.textContent = t.value;
            }
        }

        // 3) Handle option editors in Content Tone and Brand Voice modals
        const action = t.getAttribute('data-action');
        if (action === 'css-search-input') {
            // CSS Rules modal live search
            const term = (t.value || '').toString();
            try {
                if (typeof window !== 'undefined' && typeof window.filterCSSRules === 'function') window.filterCSSRules(term);
                else if (typeof filterCSSRules === 'function') filterCSSRules(term);
            } catch (_) {}
        } else if (action === 'content-tone-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        } else if (action === 'brand-voice-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        }
    }, true);

    // Also respond to 'change' to mimic previous onchange behavior
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t || !t.getAttribute) return;
        const action = t.getAttribute('data-action');
        if (action === 'content-tone-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        } else if (action === 'brand-voice-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        } else if (action === 'backup-option-change') {
            try {
                if (typeof window !== 'undefined' && typeof window.updateBackupButton === 'function') window.updateBackupButton();
                else if (typeof updateBackupButton === 'function') updateBackupButton();
            } catch (_) {}
        } else if (action === 'db-backup-option-change') {
            try {
                if (typeof window !== 'undefined' && typeof window.updateDatabaseBackupButton === 'function') window.updateDatabaseBackupButton();
                else if (typeof updateDatabaseBackupButton === 'function') updateDatabaseBackupButton();
            } catch (_) {}
        } else if (action === 'tm-filter-color-templates') {
            try {
                if (typeof window !== 'undefined' && typeof window.filterColorTemplates === 'function') window.filterColorTemplates();
                else if (typeof filterColorTemplates === 'function') filterColorTemplates();
            } catch (_) {}
        } else if (action === 'tm-filter-size-templates') {
            try {
                if (typeof window !== 'undefined' && typeof window.filterSizeTemplates === 'function') window.filterSizeTemplates();
                else if (typeof filterSizeTemplates === 'function') filterSizeTemplates();
            } catch (_) {}
        } else if (action === 'tm-filter-email-templates') {
            try {
                if (typeof window !== 'undefined' && typeof window.filterEmailTemplates === 'function') window.filterEmailTemplates();
                else if (typeof filterEmailTemplates === 'function') filterEmailTemplates();
            } catch (_) {}
        } else if ((t.id && t.id === 'backupFile') || action === 'restore-file-change') {
            try {
                if (typeof window !== 'undefined' && typeof window.handleFileSelect === 'function') window.handleFileSelect(e);
                else if (typeof handleFileSelect === 'function') handleFileSelect(e);
            } catch (_) {}
        }
    }, true);
}

// Initialize listeners ASAP
if (typeof window !== 'undefined') {
    const bootInit = () => {
        if (!__wfIsOnAdminSettingsPage()) return;
        if (__wfIsAdminSettingsLightMode()) return; // skip heavy delegated init in light mode
        initAdminSettingsDelegatedListeners();
    };
    if (document.readyState !== 'loading') bootInit();
    else document.addEventListener('DOMContentLoaded', bootInit, { once: true });
}

// Hard guard to prevent legacy inline scripts or markup from auto-opening modals on page load
function forceHideAdminModalsOnLoad() {
    try {
        // Do not force-hide if a modal is already visible or user has interacted
        if (window.__wfModalUserInteracted) return;
        const allowHash = (window.location && typeof window.location.hash === 'string') ? window.location.hash : '';
        const selectors = [
            '.admin-modal-overlay',
            '.modal-overlay',
            '.room-modal-overlay',
            '.wf-revealco-overlay',
            '#global-confirmation-modal',
            '.confirmation-modal-overlay',
            '#searchModal',
            '.wf-login-overlay',
            '#quantityModal',
            '#detailedItemModal',
            '[id$="Modal"]'
        ];
        const nodes = document.querySelectorAll(selectors.join(', '));
        nodes.forEach((el) => {
            if (allowHash && el.id && allowHash === '#' + el.id.replace(/Modal$/,'_config')) return;
            try { el.classList.remove('show'); } catch (_) {}
            try { el.classList.add('hidden'); } catch (_) {}
        });
        if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
            window.WFModals.unlockScrollIfNoneOpen();
        } else {
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
        }
    } catch (_) {}
}

// Invoke the guard as soon as DOM is ready
if (typeof window !== 'undefined') {
    // Block programmatic modal opens during initial load; allow only user-initiated
    const WF_SQUELCH_MS = 2000; // still used for optional allowParam auto-lift timing
    const WF_SQUELCH_START = Date.now();
    const isSquelchActive = () => (Date.now() - WF_SQUELCH_START) < WF_SQUELCH_MS;
    // Expose for other closures in this module (legacy consumers)
    window.__wfIsSquelchActive = isSquelchActive;
    // Track if user has interacted with modals to avoid late force-hide
    if (typeof window.__wfModalUserInteracted === 'undefined') window.__wfModalUserInteracted = false;

    // Install short-lived guards that suppress programmatic modal opens during initial load
    const _installModalSquelchGuards = () => {};
    // Force-hide common non-overlay panels (search results, website logs) until user interacts
    const _installPanelSquelchGuards = () => {};
    const bootHide = () => {
        if (!__wfIsOnAdminSettingsPage()) return;
        // Disable squelch on Admin Settings to allow user-initiated modals to open reliably
        try {
            window.__wfModalUserInteracted = true;
            document.documentElement.removeAttribute('data-admin-squelch');
        } catch(_) {}
        // Do NOT install squelch guards on this page
        // installModalSquelchGuards();
        // installPanelSquelchGuards();
        // Skip legacy force-hide on admin settings entirely
    };
    if (document.readyState !== 'loading') bootHide();
    else document.addEventListener('DOMContentLoaded', bootHide, { once: true });
    // Also enforce on full load and shortly after to suppress late auto-opens
    window.addEventListener('load', () => {
        try { bootHide(); } catch(_) {}
        // One quick retry; abort if user interacted
        setTimeout(() => { try { bootHide(); } catch(_) {} }, 200);
        // Do not schedule any legacy force-hide on Admin Settings
        if (!__wfIsOnAdminSettingsPage()) {
            setTimeout(() => { try { forceHideAdminModalsOnLoad(); } catch(_) {} }, 1000);
            setTimeout(() => { try { forceHideAdminModalsOnLoad(); } catch(_) {} }, 3000);
        }
    }, { once: true });

    // Mark user interaction early to disable any further force-hides
    const markInteract = () => { window.__wfModalUserInteracted = true; };
    window.addEventListener('pointerdown', markInteract, { passive: true, once: true });
    window.addEventListener('click', markInteract, { passive: true, once: true });
    window.addEventListener('keydown', markInteract, { passive: true, once: true });
}

// Ensure all admin overlays open BELOW the fixed site header
function getHeaderHeight() {
    try {
        const header = document.querySelector('header') || document.querySelector('.site-header') || document.querySelector('#site-header') || document.querySelector('.universal-page-header');
        let h = 0;
        if (header) {
            const rect = header.getBoundingClientRect();
            h = Math.max(0, Math.round(rect.height));
        }
        // Fallback: compute from the first main content container top
        if (!h) {
            const candidates = [
                document.querySelector('#adminContent'),
                document.querySelector('.admin-content'),
                document.querySelector('main'),
                document.querySelector('#content'),
            ].filter(Boolean);
            const first = candidates[0];
            if (first) {
                const top = Math.round(first.getBoundingClientRect().top);
                if (Number.isFinite(top) && top > 0) h = top;
            }
        }
        // Clamp to a sane minimum if still zero (ensures we never overlay under header)
        if (!h) h = 64; // default header estimate
        return h;
    } catch (_) { return 0; }
}

function getHeaderZIndex() {
    try {
        const header = document.querySelector('header') || document.querySelector('.site-header') || document.querySelector('#site-header');
        if (!header) return null;
        const z = window.getComputedStyle(header).zIndex;
        const zi = parseInt(z, 10);
        return Number.isFinite(zi) ? zi : null;
    } catch (_) { return null; }
}

function offsetOverlayBelowHeader(el) {
    try {
        if (!el) return;
        el.classList.add('admin-modal-offset-under-header');
    } catch (_) {}
}

function applyHeaderOffsetToAllOverlays() {
    // Legacy helper disabled for admin routes to allow full-viewport centering
    try {
        const isAdmin = !!document.body && document.body.matches('[data-page^="admin"]');
        if (isAdmin) return; // no-op on admin
        const selectors = [
            '.admin-modal-overlay',
            '.modal-overlay',
            '.room-modal-overlay',
            '.wf-revealco-overlay',
            '#global-confirmation-modal',
            '.confirmation-modal-overlay',
            '#searchModal',
            '.wf-login-overlay',
            '#quantityModal',
            '#detailedItemModal',
            '[id$="Modal"]'
        ];
        document.querySelectorAll(selectors.join(', ')).forEach((el) => offsetOverlayBelowHeader(el));
    } catch (_) {}
}

function watchModalOpensForHeaderOffset() {
    try {
        let __wfHdrOffTimer = null;
        let __wfHdrOffBusy = false;
        let __wfHdrOffLast = 0;
        const observer = new MutationObserver((mutations) => {
            let needApply = false;
            for (const m of mutations) {
                if (m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'style')) {
                    const t = m.target;
                    if (!(t instanceof HTMLElement)) continue;
                    // Heuristic: overlays commonly toggle 'show' or 'hidden'
                    if (t.classList.contains('show') || !t.classList.contains('hidden')) {
                        needApply = true;
                        offsetOverlayBelowHeader(t);
                    }
                }
            }
            if (!needApply) return;
            const now = Date.now();
            const run = () => {
                if (__wfHdrOffBusy) return;
                __wfHdrOffBusy = true;
                try { applyHeaderOffsetToAllOverlays(); } finally { __wfHdrOffBusy = false; __wfHdrOffLast = Date.now(); }
            };
            if ((now - __wfHdrOffLast) < 200) {
                try { if (__wfHdrOffTimer) clearTimeout(__wfHdrOffTimer); } catch(_) {}
                __wfHdrOffTimer = setTimeout(run, 200);
                return;
            }
            run();
        });
        observer.observe(document.documentElement, { attributes: true, subtree: true, attributeFilter: ['class', 'style'] });
        // Keep a reference in case we need to disconnect later
        window.__wfModalHeaderObserver = observer;
    } catch (_) {}
}

if (typeof window !== 'undefined') {
    const onReady = () => {
        const __wfEnableHdrOffset = false; // finalized: disabled on Settings
        if (__wfEnableHdrOffset) {
            // Inject a strong style sheet that enforces top/height below header for overlays
            try {
                const existing = document.getElementById('wf-modal-offset-style');
                const style = existing || document.createElement('style');
                style.id = 'wf-modal-offset-style';
                const headerZ = getHeaderZIndex();
                const css = `
                :root { --wf-header-h: ${getHeaderHeight()}px; --wf-header-h-plus: calc(var(--wf-header-h) + 16px); --wf-header-z: ${Number.isFinite(headerZ) ? headerZ : 100}; --wf-modal-below-header-z: calc(var(--wf-header-z) - 1); }
                .admin-modal-overlay,
                .modal-overlay,
                .room-modal-overlay,
                .wf-revealco-overlay,
                #global-confirmation-modal,
                .confirmation-modal-overlay,
                #searchModal,
                .wf-login-overlay,
                #quantityModal,
                #detailedItemModal,
                [id$="Modal"] {
                    position: fixed !important;
                    top: var(--wf-header-h-plus) !important;
                    height: calc(100vh - var(--wf-header-h-plus)) !important;
                    left: 0 !important;
                    right: 0 !important;
                    overflow: auto !important;
                    box-sizing: border-box !important;
                    padding-top: 12px; /* ensure inner content and close button clear the site header */
                    z-index: var(--wf-modal-below-header-z) !important;
                }
                /* Try to ensure inner fixed dialogs respect the header too */
                .admin-modal-overlay [role="dialog"],
                .modal-overlay [role="dialog"],
                .room-modal-overlay [role="dialog"],
                .admin-modal-overlay .modal,
                .modal-overlay .modal,
                .room-modal-overlay .modal,
                .admin-modal-content {
                    margin-top: max(0px, var(--wf-header-h-plus));
                }
                .admin-modal-header,
                .admin-modal-overlay .section-header,
                .modal-overlay .section-header {
                    position: sticky;
                    top: 0;
                    z-index: 1;
                }
            `;
                style.textContent = css;
                if (!existing) document.head.appendChild(style);
            } catch (_) {}
            // No header offset on admin: overlays are full-viewport centered
            const onSettingsPage = !!document.querySelector('.settings-page');
            if (!onSettingsPage) {
                watchModalOpensForHeaderOffset();
            }
            // Re-apply on resize since header height can change with breakpoints
            window.addEventListener('resize', () => {
                // Refresh style block value without touching inline CSS variables
                try {
                    const h = getHeaderHeight();
                    const style = document.getElementById('wf-modal-offset-style');
                    if (style) style.textContent = style.textContent.replace(/--wf-header-h: \d+px;/, `--wf-header-h: ${h}px;`);
                } catch (_) {}
                applyHeaderOffsetToAllOverlays();
            });

            // Re-apply shortly after initial paint to catch late header sizing
            setTimeout(() => applyHeaderOffsetToAllOverlays(), 250);
            // Also re-apply on full window load (fonts/images can change header height)
            window.addEventListener('load', () => applyHeaderOffsetToAllOverlays(), { once: true });
        }

        // Delegated admin modal handlers
        const root = document;
        const findOverlayByKey = (key) => {
            try {
                if (!key) return null;
                const raw = String(key).replace(/Modal$/i, '');
                const norm = raw.toLowerCase().replace(/[^a-z0-9]+/g, '-');
                const camelFromNorm = norm.replace(/-([a-z0-9])/g, (_, c) => c.toUpperCase());
                const candidates = [
                    `#${raw}Modal`,            // preserve camelCase input
                    `#${norm}Modal`,
                    `#${norm}-modal`,
                    `#${camelFromNorm}Modal`,  // background-manager -> backgroundManagerModal
                ];
                for (const sel of candidates) {
                    const el = document.querySelector(sel);
                    if (el) return el;
                }
                // Fallback: any id ending with Modal that includes the raw or norm key
                const all = Array.from(document.querySelectorAll('[id$="Modal"]'));
                return all.find(el => {
                    const id = (el.id || '').toLowerCase();
                    return id.includes(norm) || id.includes(raw.toLowerCase());
                });
            } catch (_) { return null; }
        };

        const openOverlay = (el) => {
            if (!el) return;
            const squelchOn = (typeof window.__wfIsSquelchActive === 'function') ? window.__wfIsSquelchActive() : false;
            // Never suppress overlays on Admin Settings page
            if (squelchOn && !el.__wfUserInitiated && !__wfIsOnAdminSettingsPage()) {
                // Suppress auto-open during squelch window
                try { el.classList.remove('show'); } catch(_) {}
                try { el.classList.add('hidden'); } catch(_) {}
                return;
            }
            // Mark user interaction so late force-hide does not close it
            window.__wfModalUserInteracted = true;
            // Ensure overlays are appended to body to avoid ancestor overflow/transform issues
            try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
            // Force viewport anchoring utility classes
            try { el.classList.add('wf-overlay-viewport','over-header','topmost'); el.classList.remove('under-header'); } catch(_) {}
            // Normalize ARIA and visibility
            try { el.removeAttribute('hidden'); } catch(_) {}
            try { el.classList.remove('hidden'); } catch (_) {}
            try { el.classList.add('show'); } catch (_) {}
            try { el.setAttribute('aria-hidden','false'); } catch(_) {}
            // Ensure only the topmost modal scrolls
            try {
                document.documentElement.classList.add('wf-modal-scroll-lock');
                document.body.classList.add('wf-modal-scroll-lock');
                document.querySelectorAll('.admin-modal-overlay, .modal-overlay, .room-modal-overlay')
                  .forEach(n => n.classList.remove('wf-topmost'));
                el.classList.add('wf-topmost');
            } catch (_) {}
            // Unhide any nested hidden panels that should be visible by default
            try {
                el.querySelectorAll(':scope .hidden[data-default-visible], :scope [data-unhide-on-open]')
                  .forEach(n => n.classList.remove('hidden'));
            } catch (_) {}
            // Prevent background scroll
            try {
                document.documentElement.classList.add('modal-open');
                document.body.classList.add('modal-open');
            } catch (_) {}
            applyHeaderOffsetToAllOverlays();
            // Delegate to shared modal helper if present to align sizing/aria behavior
            try {
                if (typeof window.__wfShowModal === 'function') {
                    const id = el.id || el;
                    window.__wfShowModal(id);
                }
            } catch(_) {}
            // Optional initializer hook: init<ID>() or init<IdWithoutModal>() if defined
            try {
                const id = el.id || '';
                if (id) {
                    const clean = id.replace(/Modal$/,'');
                    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
                    const camel = clean.replace(/[-_](\w)/g, (_, c) => c.toUpperCase());
                    const fnNames = [
                        `init${cap(id)}`,
                        `init${cap(camel)}Modal`,
                        `init${cap(camel)}`,
                    ];
                    for (const fn of fnNames) {
                        if (typeof window[fn] === 'function') { try { window[fn](el); } catch(_) {} break; }
                    }
                }
            } catch (_) {}
        };

        const closeOverlay = (el) => {
            if (!el) return;
            try { el.classList.remove('show'); } catch (_) {}
            try { el.classList.add('hidden'); } catch (_) {}
            try { el.setAttribute('aria-hidden','true'); } catch(_) {}
            try { el.classList.remove('wf-topmost'); } catch (_) {}
            if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
                window.WFModals.unlockScrollIfNoneOpen();
            }
            // If none visible, unlock; else, assign topmost to the last visible overlay
            try {
                const overlays = Array.from(document.querySelectorAll('.admin-modal-overlay, .modal-overlay, .room-modal-overlay'));
                const visible = overlays.filter(n => n.classList && n.classList.contains('show'));
                if (visible.length === 0) {
                    document.documentElement.classList.remove('wf-modal-scroll-lock');
                    document.body.classList.remove('wf-modal-scroll-lock');
                } else {
                    overlays.forEach(n => n.classList.remove('wf-topmost'));
                    visible[visible.length - 1].classList.add('wf-topmost');
                }
            } catch (_) {}
        };

        root.addEventListener('click', (ev) => {
            // Any user click within settings should lift squelch immediately
            try {
                window.__wfModalUserInteracted = true;
                document.documentElement.removeAttribute('data-admin-squelch');
            } catch(_) {}
            const target = ev.target.closest('[data-action]');
            const clicked = ev.target.closest('button, a, [role="button"]');
            // 1) ID convention: some buttons lack data-action; infer from id: fooBtn -> #fooModal
            if (clicked && clicked.id && /Btn$/.test(clicked.id)) {
                const base = clicked.id.replace(/Btn$/, '');
                const el = findOverlayByKey(base);
                if (el) {
                    ev.preventDefault();
                    el.__wfUserInitiated = true; openOverlay(el); delete el.__wfUserInitiated;
                    return;
                }
                // Known dynamic creators
                if (/^loggingStatus$/i.test(base) && typeof window.createLoggingStatusModal === 'function') {
                    ev.preventDefault();
                    try { window.createLoggingStatusModal(); } catch(_) {}
                    const created = document.getElementById('loggingStatusModal');
                    if (created) { created.__wfUserInitiated = true; openOverlay(created); delete created.__wfUserInitiated; return; }
                }
                if (/^aiSettings$/i.test(base)) {
                    // Sometimes rendered later; try a short defer
                    setTimeout(() => {
                        const late = findOverlayByKey(base);
                        if (late) { late.__wfUserInitiated = true; openOverlay(late); delete late.__wfUserInitiated; }
                    }, 50);
                }
            }

            if (!target) return;
            const action = String(target.getAttribute('data-action') || '');
            // open-foo -> find #fooModal
            const m = action.match(/^open-(.+)$/);
            if (m) {
                ev.preventDefault();
                const el = findOverlayByKey(m[1]);
                if (el) { el.__wfUserInitiated = true; openOverlay(el); delete el.__wfUserInitiated; }
                else {
                    // Try known creators for certain keys
                    const key = m[1].toLowerCase();
                    if (key.includes('logging') && typeof window.createLoggingStatusModal === 'function') {
                        try { window.createLoggingStatusModal(); } catch(_) {}
                        const created = document.getElementById('loggingStatusModal');
                        if (created) { created.__wfUserInitiated = true; openOverlay(created); delete created.__wfUserInitiated; }
                    }
                }
                return;
            }
            if (action === 'close-admin-modal') {
                ev.preventDefault();
                const overlay = target.closest('.admin-modal-overlay, .modal-overlay, .room-modal-overlay, [id$="Modal"]');
                if (overlay) closeOverlay(overlay);
                return;
            }

            // AI Settings Modal handlers
            if (action === 'open-ai-settings') {
                ev.preventDefault();
                openAISettingsModal();
                return;
            }
            if (action === 'close-ai-settings') {
                ev.preventDefault();
                closeAISettingsModal();
                return;
            }
            if (action === 'save-ai-settings') {
                ev.preventDefault();
                saveAISettings();
                return;
            }
            if (action === 'test-ai-provider') {
                ev.preventDefault();
                testAIProvider();
                return;
            }
        });

    const bootDelegation = () => {
        if (!__wfIsOnAdminSettingsPage()) return;
        // Run delegated admin modal handlers even in light mode so buttons remain responsive.
        // Heavier initializations elsewhere remain gated by light mode.
    };
    if (document.readyState !== 'loading') bootDelegation();
    else document.addEventListener('DOMContentLoaded', bootDelegation, { once: true });
  }; // end onReady

  // Run onReady once DOM is ready
  if (document.readyState !== 'loading') onReady();
  else document.addEventListener('DOMContentLoaded', onReady, { once: true });
} // end if (typeof window !== 'undefined')

// Helper to initialize SSL checkbox-driven visibility
function initSSLHandlers(root = document) {
    try {
        const sslCheckbox = root.querySelector ? root.querySelector('#sslEnabled') : null;
        const sslOptions = root.querySelector ? root.querySelector('#sslOptions') : null;
        if (sslCheckbox && sslOptions) {
            sslOptions.classList.toggle('hidden', !sslCheckbox.checked);
        }
    } catch (_) {}
}
