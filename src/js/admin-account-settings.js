// Admin Account Settings Vite module
// Consumes #account-settings-data and wires form submission to existing backend endpoint.
import { ApiClient } from '../core/api-client.js';
(function(){
  const payloadEl = document.getElementById('account-settings-data');
  const form = document.getElementById('accountSettingsForm');
  const errorEl = document.getElementById('accountErrorMessage');
  const successEl = document.getElementById('accountSuccessMessage');
  if (!form || !payloadEl) return;
  // Reserved for future defaults from embedded JSON (kept for forward compatibility)
  try { JSON.parse(payloadEl.textContent || '{}'); } catch(_) {}

  const show = (el) => { if (el) { el.classList.remove('hidden'); el.setAttribute('aria-hidden', 'false'); } };
  const hide = (el) => { if (el) { el.classList.add('hidden'); el.setAttribute('aria-hidden', 'true'); } };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hide(errorEl); hide(successEl);
    const fd = new FormData(form);
    // Expect server to authenticate current user and validate currentPassword
    try {
      const data = await ApiClient.upload('/functions/process_account_update.php', fd);
      if (!data || data?.success === false) {
        if (errorEl) errorEl.textContent = data?.message || 'Failed to update account. Please verify your current password and try again.';
        show(errorEl);
      } else {
        if (successEl) successEl.textContent = data?.message || 'Your account has been updated successfully!';
        show(successEl);
      }
    } catch (err) {
      if (errorEl) errorEl.textContent = 'Network error. Please try again later.';
      show(errorEl);
    }
  });
})();
