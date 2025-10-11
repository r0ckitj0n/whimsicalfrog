// Account Settings Modal Controller
import { ApiClient } from '../core/api-client.js';

(function AccountSettingsModal() {
  if (window.__WF_ACCOUNT_SETTINGS_INSTALLED) return;
  window.__WF_ACCOUNT_SETTINGS_INSTALLED = true;

  const sel = (s, r = document) => r.querySelector(s);

  const overlay = () => sel('#accountSettingsModal');
  const successEl = () => sel('#accountSettingsSuccess');
  const errorEl = () => sel('#accountSettingsError');
  const addressesList = () => sel('#accountAddressesList');

  function getUserId() {
    try {
      const bid = document.body?.dataset?.userId;
      if (bid) return bid;
      // Fallback from sessionStorage
      const u = JSON.parse(sessionStorage.getItem('user') || '{}');
      return u.userId || u.id || '';
    } catch (_) { return ''; }
  }

  async function fetchUserProfile(userId) {
    try {
      if (!userId) return null;
      const j = await ApiClient.get(`/api/users.php?id=${encodeURIComponent(userId)}`);
      return j || null;
    } catch (e) {
      console.warn('[AccountSettings] fetchUserProfile failed', e);
      return null;
    }
  }

  function fillProfile(u) {
    try {
      sel('#acc_username').value = u?.username ?? '';
      sel('#acc_email').value = u?.email ?? '';
      sel('#acc_firstName').value = u?.firstName ?? '';
      sel('#acc_lastName').value = u?.lastName ?? '';
      const pn = sel('#acc_phoneNumber');
      if (pn) pn.value = u?.phoneNumber ?? '';
    } catch (_) {}
  }

  function clearAlerts() {
    try { errorEl()?.classList.add('hidden'); errorEl().textContent=''; } catch(_){ }
    try { successEl()?.classList.add('hidden'); } catch(_){ }
  }

  function showError(msg) {
    try { const el = errorEl(); if (!el) return; el.textContent = String(msg || 'An error occurred'); el.classList.remove('hidden'); } catch(_){ }
  }

  function showSuccess(msg) {
    try { const el = successEl(); if (!el) return; if (msg) el.textContent = msg; el.classList.remove('hidden'); } catch(_){ }
  }

  function openModal() {
    const o = overlay(); if (!o) return;
    o.classList.remove('hidden');
    o.classList.add('show');
    o.setAttribute('aria-hidden','false');
    // Ensure header offset
    // eslint-disable-next-line no-restricted-syntax
    try { o.style.paddingTop = getComputedStyle(document.documentElement).getPropertyValue('--wf-overlay-offset') || ''; } catch(_) {}
  }

  function closeModal() {
    const o = overlay(); if (!o) return;
    o.classList.add('hidden');
    o.classList.remove('show');
    o.setAttribute('aria-hidden','true');
  }

  async function loadAddresses(userId) {
    const list = addressesList(); if (!list) return;
    list.innerHTML = '<div class="text-sm text-gray-500">Loading addresses…</div>';
    try {
      const j = await ApiClient.get(`/api/customer_addresses.php?action=get_addresses&user_id=${encodeURIComponent(userId)}`);
      if (!j || j.success !== true) throw new Error(j?.error || 'Failed to load addresses');
      const tmpl = sel('#accountAddressItemTemplate');
      list.innerHTML = '';
      (j.addresses || []).forEach(addr => {
        if (!tmpl) return;
        const html = tmpl.innerHTML
          .replace('{{id}}', String(addr.id))
          .replace('{{address_name}}', escapeHtml(addr.address_name || ''))
          .replace('{{default_badge}}', addr.is_default ? '(default)' : '')
          .replace('{{address_line1}}', escapeHtml(addr.address_line1 || ''))
          .replace('{{address_line2_sep}}', addr.address_line2 ? ', ' : '')
          .replace('{{address_line2}}', escapeHtml(addr.address_line2 || ''))
          .replace('{{city}}', escapeHtml(addr.city || ''))
          .replace('{{state}}', escapeHtml(addr.state || ''))
          .replace('{{zip_code}}', escapeHtml(addr.zip_code || ''));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        list.appendChild(wrapper.firstElementChild);
      });
      if (!j.addresses || j.addresses.length === 0) {
        list.innerHTML = '<div class="text-sm text-gray-500">No addresses yet. Add your first address.</div>';
      }
    } catch (e) {
      list.innerHTML = '<div class="text-sm text-red-600">Failed to load addresses.</div>';
      console.warn('[AccountSettings] loadAddresses failed', e);
    }
  }

  function openAddressEditor(initial = null, onSave = null, onCancel = null) {
    const tmpl = sel('#accountAddressEditorTemplate');
    if (!tmpl) return null;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = tmpl.innerHTML;
    const editor = wrapper.firstElementChild;
    const set = (n, v) => { const el = sel(`[name="${n}"]`, editor); if (el) el.value = v ?? ''; };
    if (initial) {
      set('address_name', initial.address_name);
      set('address_line1', initial.address_line1);
      set('address_line2', initial.address_line2);
      set('city', initial.city);
      set('state', initial.state);
      set('zip_code', initial.zip_code);
      try { const cb = sel('[name="is_default"]', editor); if (cb) cb.checked = !!initial.is_default; } catch(_){ }
    }
    editor.addEventListener('click', (e) => {
      const t = e.target;
      if (t.closest('[data-action="address-cancel"]')) { e.preventDefault(); if (onCancel) onCancel(); editor.remove(); }
      if (t.closest('[data-action="address-save"]')) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(editor).entries());
        data.is_default = (sel('[name="is_default"]', editor)?.checked ? 1 : 0);
        if (onSave) onSave(data, editor);
      }
    });
    addressesList()?.prepend(editor);
    return editor;
  }

  async function handleSaveProfile(userId) {
    clearAlerts();
    const email = sel('#acc_email')?.value || '';
    const firstName = sel('#acc_firstName')?.value || '';
    const lastName = sel('#acc_lastName')?.value || '';
    const phoneNumber = sel('#acc_phoneNumber')?.value || '';
    const currentPassword = sel('#acc_currentPassword')?.value || '';
    const newPassword = sel('#acc_newPassword')?.value || '';

    try {
      // Update profile fields
      const payload = { userId, email, firstName, lastName, phoneNumber };
      const upd = await ApiClient.post('/api/update_user.php', payload);
      if (upd && upd.error) throw new Error(upd.error);

      // Password change if provided
      if (newPassword) {
        if (!currentPassword) throw new Error('Current password is required to change password');
        const pass = await ApiClient.post('/functions/process_account_update.php', {
          userId, email, firstName, lastName,
          currentPassword, newPassword
        });
        if (!pass || pass.success !== true) throw new Error(pass?.error || 'Failed to change password');
      }

      // Update client cache (best-effort)
      try {
        const currentUser = JSON.parse(sessionStorage.getItem('user') || '{}');
        const updatedUser = { ...currentUser, email, firstName, lastName, phoneNumber };
        sessionStorage.setItem('user', JSON.stringify(updatedUser));
      } catch (_) {}

      showSuccess('Saved successfully.');
      window.dispatchEvent(new CustomEvent('wf:account-updated'));
    } catch (e) {
      showError(e?.message || String(e));
    } finally {
      // Clear password fields for safety
      try { sel('#acc_currentPassword').value = ''; sel('#acc_newPassword').value = ''; } catch(_){}
    }
  }

  function escapeHtml(s) {
    try {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    } catch (_) { return s; }
  }

  async function openAndLoad() {
    const userId = getUserId();
    clearAlerts();
    openModal();
    const u = await fetchUserProfile(userId);
    fillProfile(u);
    await loadAddresses(userId);
  }

  // Delegated global click handlers
  document.addEventListener('click', async (e) => {
    const t = e.target;
    const openBtn = t.closest('[data-action="open-account-settings"]');
    const closeBtn = t.closest('[data-action="close-admin-modal"]');

    if (openBtn) {
      e.preventDefault();
      await openAndLoad();
      return;
    }

    if (closeBtn && t.closest('#accountSettingsModal')) {
      e.preventDefault();
      closeModal();
      return;
    }

    // Overlay click to close
    const ov = t.closest('.admin-modal-overlay');
    if (ov && ov.id === 'accountSettingsModal' && t === ov) {
      e.preventDefault();
      closeModal();
      return;
    }

    // Save profile
    if (t.closest('[data-action="account-settings-save"]')) {
      e.preventDefault();
      await handleSaveProfile(getUserId());
      return;
    }

    // Address actions
    if (t.closest('[data-action="account-address-add"]')) {
      e.preventDefault();
      const userId = getUserId();
      const editor = openAddressEditor(null, async (data, editorEl) => {
        try {
          const body = { ...data, user_id: userId };
          const res = await ApiClient.post('/api/customer_addresses.php?action=add_address', body);
          if (!res || res.success !== true) throw new Error(res?.error || 'Failed to add address');
          editorEl.remove();
          await loadAddresses(userId);
          showSuccess('Address added.');
        } catch (e) { showError(e?.message || String(e)); }
      }, () => {});
      if (editor) {
        // Focus first field
        try { editor.querySelector('input[name="address_name"]').focus(); } catch(_) {}
      }
      return;
    }

    if (t.closest('[data-action="address-edit"]')) {
      e.preventDefault();
      const userId = getUserId();
      const id = t.getAttribute('data-id');
      const item = t.closest('.wf-address-item');
      if (!id || !item) return;
      // const lines = item.querySelectorAll('div');
      // Render editor above item with best-effort prefill by parsing the template content; ideally we would fetch one record, but API lacks single-get.
      const initial = { address_name: item.querySelector('.font-medium')?.childNodes?.[0]?.nodeValue?.trim() || '' };
      openAddressEditor(initial, async (data, editorEl) => {
        try {
          const body = { id, ...data };
          const res = await ApiClient.post('/api/customer_addresses.php?action=update_address', body);
          if (!res || res.success !== true) throw new Error(res?.error || 'Failed to update address');
          editorEl.remove();
          await loadAddresses(userId);
          showSuccess('Address updated.');
        } catch (e) { showError(e?.message || String(e)); }
      }, () => {});
      return;
    }

    if (t.closest('[data-action="address-delete"]')) {
      e.preventDefault();
      const userId = getUserId();
      const id = t.getAttribute('data-id');
      if (!id) return;
      if (!confirm('Delete this address?')) return;
      try {
        const res = await ApiClient.get(`/api/customer_addresses.php?action=delete_address&id=${encodeURIComponent(id)}`);
        if (!res || res.success !== true) throw new Error(res?.error || 'Failed to delete address');
        await loadAddresses(userId);
        showSuccess('Address deleted.');
      } catch (e) { showError(e?.message || String(e)); }
      return;
    }

    if (t.closest('[data-action="address-default"]')) {
      e.preventDefault();
      const userId = getUserId();
      const id = t.getAttribute('data-id');
      if (!id) return;
      try {
        const res = await ApiClient.get(`/api/customer_addresses.php?action=set_default&id=${encodeURIComponent(id)}`);
        if (!res || res.success !== true) throw new Error(res?.error || 'Failed to set default');
        await loadAddresses(userId);
        showSuccess('Default address updated.');
      } catch (e) { showError(e?.message || String(e)); }
      return;
    }
  });
})();
