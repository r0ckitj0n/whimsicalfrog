// Account Settings Modal Controller
import { ApiClient } from '../core/api-client.js';

(function AccountSettingsModal() {
  if (window.__WF_ACCOUNT_SETTINGS_INSTALLED) return;
  window.__WF_ACCOUNT_SETTINGS_INSTALLED = true;

  const sel = (s, r = document) => r.querySelector(s);

  function ensureModalMarkup() {
    let modal = document.getElementById('accountSettingsModal');
    if (modal) return modal;
    const tpl = document.getElementById('accountSettingsModalTemplate');
    if (tpl && tpl.tagName === 'TEMPLATE') {
      const fragment = tpl.content.cloneNode(true);
      document.body.appendChild(fragment);
      modal = document.getElementById('accountSettingsModal');
      return modal;
    }
    return null;
  }

  const overlay = () => ensureModalMarkup();
  const successEl = () => sel('#accountSettingsSuccess');
  const errorEl = () => sel('#accountSettingsError');
  const addressesList = () => sel('#accountAddressesList');

  const requireString = (value, label, { allowEmpty = false } = {}) => {
    const exists = value !== undefined && value !== null;
    const str = exists ? String(value) : '';
    if (!allowEmpty && (!exists || str.trim() === '')) {
      throw new Error(`${label} is required`);
    }
    return str;
  };

  const getInputValue = (selector, label, { allowEmpty = false } = {}) => {
    const el = sel(selector);
    if (!el) throw new Error(`${label} field is missing from the DOM`);
    return requireString(el.value, label, { allowEmpty });
  };

  function getUserId() {
    const id = document.body?.dataset?.userId;
    if (id && String(id).trim() !== '') return String(id);
    try {
      const attrNode = document.querySelector('[data-user-id]');
      if (attrNode) {
        const candidate = attrNode.getAttribute('data-user-id');
        if (candidate && candidate.trim() !== '') {
          return candidate.trim();
        }
      }
    } catch (err) {
      console.warn('[AccountSettings] attribute fallback failed', err);
    }
    throw new Error('User ID is required. Please refresh or reauthenticate.');
  }

  async function fetchUserProfile(userId) {
    if (!userId) throw new Error('User ID is required to fetch profile');
    const tryGet = async (id) => {
      try {
        return await ApiClient.get(`/api/users.php?id=${encodeURIComponent(id)}`);
      } catch (_) { return null; }
    };
    let j = await tryGet(userId);
    if (!j || !j.id) {
      // Fallback: derive numeric id from whoami
      try {
        const who = await ApiClient.get('/api/whoami.php');
        const wid = who && (who.userId || who.userIdRaw || who.wfAuthParsedUserId);
        if (wid) {
          j = await tryGet(wid);
        }
      } catch (_) { /* noop */ }
    }
    if (!j || typeof j !== 'object' || !j.id) {
      throw new Error('Unable to load profile for current user');
    }
    return j;
  }

  function fillProfile(u) {
    if (!u || typeof u !== 'object') throw new Error('Invalid profile payload');
    sel('#acc_username').value = requireString(u.username, 'Username');
    sel('#acc_email').value = requireString(u.email, 'Email');
    sel('#acc_firstName').value = requireString(u.firstName ?? u.first_name, 'First name', { allowEmpty: true });
    sel('#acc_lastName').value = requireString(u.lastName ?? u.last_name, 'Last name', { allowEmpty: true });
    const pn = sel('#acc_phoneNumber');
    if (pn) pn.value = requireString(u.phoneNumber ?? u.phone_number, 'Phone number', { allowEmpty: true });
  }

  function clearAlerts() {
    const err = errorEl();
    if (err) {
      err.classList.add('hidden');
      err.textContent = '';
    }
    const ok = successEl();
    if (ok) ok.classList.add('hidden');
  }

  function showError(msg) {
    const el = errorEl();
    if (!el) {
      console.error('[AccountSettings] Error container missing; message:', msg);
      return;
    }
    try {
      el.textContent = requireString(msg, 'Error message', { allowEmpty: false });
    } catch (_) {
      el.textContent = 'An unexpected error occurred';
    }
    el.classList.remove('hidden');
  }

  function showSuccess(msg) {
    const el = successEl();
    if (!el) throw new Error('Account settings success container missing');
    if (msg) el.textContent = requireString(msg, 'Success message');
    el.classList.remove('hidden');
  }

  function openModal() {
    const o = overlay();
    if (!o) {
      console.error('[AccountSettings] Modal markup missing from DOM');
      return null;
    }
    try {
      if (o.parentElement && o.parentElement !== document.body) {
        document.body.appendChild(o);
      }
    } catch (err) {
      console.warn('[AccountSettings] Failed to reparent modal to body', err);
    }
    try {
      const isAdmin = (document.body?.getAttribute('data-page') || '').startsWith('admin');
      if (isAdmin) {
        o.classList.add('over-header');
        o.style.removeProperty('z-index');
      }
    } catch (_) {}
    o.classList.remove('hidden');
    o.classList.add('show');
    o.setAttribute('aria-hidden','false');
    // Ensure header offset
    // eslint-disable-next-line no-restricted-syntax
    try { o.style.paddingTop = getComputedStyle(document.documentElement).getPropertyValue('--wf-overlay-offset') || ''; } catch(_) {}
    // If this is the admin Settings variant (iframe-based), prime its src
    try {
      const frame = o.querySelector('#accountSettingsFrame');
      if (frame && (frame.getAttribute('src') === 'about:blank' || !frame.getAttribute('src'))) {
        const ds = frame.getAttribute('data-src') || '/sections/admin_router.php?section=account-settings&modal=1';
        frame.setAttribute('src', ds);
      }
    } catch (_) {}
    return o;
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
      if (!j || j.success !== true) {
        throw new Error(requireString(j?.error, 'Address error message'));
      }
      const tmpl = sel('#accountAddressItemTemplate');
      list.innerHTML = '';
      if (!tmpl) throw new Error('Address template missing');
      (j.addresses || []).forEach(addr => {
        const id = requireString(addr?.id, 'Address id');
        const name = requireString(addr?.addressName, 'Address name');
        const line1 = requireString(addr?.addressLine1, 'Address line 1');
        const city = requireString(addr?.city, 'City');
        const state = requireString(addr?.state, 'State');
        const zip = requireString(addr?.zipCode, 'ZIP code');
        const line2 = addr?.addressLine2 ? requireString(addr.addressLine2, 'Address line 2', { allowEmpty: true }) : '';
        const defaultBadge = addr?.isDefault ? '(default)' : '';
        const html = tmpl.innerHTML
          .replace('{{id}}', escapeHtml(id))
          .replace('{{address_name}}', escapeHtml(name))
          .replace('{{default_badge}}', defaultBadge)
          .replace('{{address_line1}}', escapeHtml(line1))
          .replace('{{address_line2_sep}}', line2 ? ', ' : '')
          .replace('{{address_line2}}', line2 ? escapeHtml(line2) : '')
          .replace('{{city}}', escapeHtml(city))
          .replace('{{state}}', escapeHtml(state))
          .replace('{{zip_code}}', escapeHtml(zip));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        list.appendChild(wrapper.firstElementChild);
      });
      if (!j.addresses || j.addresses.length === 0) {
        list.innerHTML = '<div class="text-sm text-gray-500">No addresses yet. Add your first address.</div>';
      }
    } catch (e) {
      list.innerHTML = '<div class="text-sm text-red-600">Failed to load addresses.</div>';
      showError(e.message || 'Failed to load addresses');
      throw e;
    }
  }

  function openAddressEditor(initial = null, onSave = null, onCancel = null) {
    const tmpl = sel('#accountAddressEditorTemplate');
    if (!tmpl) return null;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = tmpl.innerHTML;
    const editor = wrapper.firstElementChild;
    const set = (n, v, opts = {}) => {
      const el = sel(`[name="${n}"]`, editor);
      if (!el) throw new Error(`${n} field missing in address editor template`);
      if (v === undefined || v === null) {
        if (opts.allowEmpty) {
          el.value = '';
          return;
        }
        throw new Error(`${n} value is required`);
      }
      el.value = String(v);
    };
    if (initial) {
      set('address_name', initial.address_name);
      set('address_line1', initial.address_line1);
      set('address_line2', initial.address_line2, { allowEmpty: true });
      set('city', initial.city);
      set('state', initial.state);
      set('zip_code', initial.zip_code);
      const cb = sel('[name="is_default"]', editor);
      if (!cb) throw new Error('is_default checkbox missing in address editor');
      cb.checked = !!initial.is_default;
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
    const email = getInputValue('#acc_email', 'Email');
    const firstName = getInputValue('#acc_firstName', 'First name', { allowEmpty: true });
    const lastName = getInputValue('#acc_lastName', 'Last name', { allowEmpty: true });
    const phoneNumber = getInputValue('#acc_phoneNumber', 'Phone number', { allowEmpty: true });
    const currentPassword = getInputValue('#acc_currentPassword', 'Current password', { allowEmpty: true });
    const newPassword = getInputValue('#acc_newPassword', 'New password', { allowEmpty: true });

    try {
      // Update profile fields
      const payload = { userId, email, firstName, lastName, phoneNumber };
      const upd = await ApiClient.post('/api/update_user.php', payload);
      if (upd && upd.error) throw new Error(upd.error);

      // Password change if provided
      if (newPassword.trim() !== '') {
        if (currentPassword.trim() === '') throw new Error('Current password is required to change password');
        const pass = await ApiClient.post('/functions/process_account_update.php', {
          userId, email, firstName, lastName,
          currentPassword, newPassword
        });
        if (!pass || pass.success !== true) throw new Error(pass?.error || 'Failed to change password');
      }

      // Update client cache (best-effort)
      try {
        const cached = sessionStorage.getItem('user');
        if (cached) {
          const currentUser = JSON.parse(cached);
          const updatedUser = { ...currentUser, email, firstName, lastName, phoneNumber };
          sessionStorage.setItem('user', JSON.stringify(updatedUser));
        }
      } catch (_) {}

      showSuccess('Saved successfully.');
      window.dispatchEvent(new CustomEvent('wf:account-updated'));
    } catch (e) {
      showError(e?.message || 'Account update failed');
    } finally {
      // Clear password fields for safety
      const current = sel('#acc_currentPassword');
      const fresh = sel('#acc_newPassword');
      if (current) current.value = '';
      if (fresh) fresh.value = '';
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
    clearAlerts();
    const modalRef = openModal();
    try {
      if (!modalRef) {
        throw new Error('Account Settings modal is unavailable on this page');
      }
      // Admin Settings page uses an iframe for account settings; if present, do not run inline form logic
      const frame = document.getElementById('accountSettingsFrame');
      if (frame) {
        if (frame.getAttribute('src') === 'about:blank' || !frame.getAttribute('src')) {
          const ds = frame.getAttribute('data-src') || '/sections/admin_router.php?section=account-settings&modal=1';
          frame.setAttribute('src', ds);
        }
        return; // no further inline loading needed
      }
      const userId = getUserId();
      const u = await fetchUserProfile(userId);
      fillProfile(u);
      await loadAddresses(userId);
    } catch (e) {
      console.error('[AccountSettings] openAndLoad failed', e);
      try {
        showError(e.message || 'Failed to load account settings');
      } catch (_) {
        /* already logged */
      }
    }
  }

  // Capture-phase listener to beat page-level delegated handlers that stopPropagation
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (t && (t.closest('[data-action="open-account-settings"]') || t.id === 'accountSettingsBtn')) {
      e.preventDefault();
      e.stopPropagation();
      // Open quickly; async load handled separately
      openAndLoad();
    }
  }, true);

  

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
      const initialName = requireString(item.querySelector('.font-medium')?.childNodes?.[0]?.nodeValue, 'Address name');
      const initial = { address_name: initialName };
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
      const id = requireString(t.getAttribute('data-id'), 'Address id');
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
