// Account Settings Page module
import { ApiClient } from '../src/core/api-client.js';
(function AccountSettingsPage() {
  function getPageData() {
    const el = document.getElementById('account-settings-data');
    if (!el) return {};
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      console.error('[AccountSettings] Failed to parse page data', e);
      return {};
    }
  }

  async function updatePhpSession(updatedUser) {
    try {
      await ApiClient.request('/set_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updatedUser),
      });
    } catch (e) {
      console.warn('[AccountSettings] Failed to update PHP session', e);
    }
  }

  function bindForm() {
    const form = document.getElementById('accountSettingsForm');
    const errorMessage = document.getElementById('accountErrorMessage');
    const successMessage = document.getElementById('accountSuccessMessage');
    if (!form) return;

    const { userId } = getPageData();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Hide previous messages
      errorMessage?.classList.add('hidden');
      successMessage?.classList.add('hidden');

      const payload = {
        userId: userId || '',
        email: document.getElementById('email')?.value || '',
        firstName: document.getElementById('firstName')?.value || '',
        lastName: document.getElementById('lastName')?.value || '',
        currentPassword: document.getElementById('currentPassword')?.value || '',
        newPassword: document.getElementById('newPassword')?.value || '',
      };

      try {
        const data = await ApiClient.post('/functions/process_account_update.php', payload);
        if (!data || data.success !== true) {
          throw new Error((data && data.error) || 'Failed to update account');
        }

        // Show success
        if (successMessage) successMessage.classList.remove('hidden');

        // Clear password fields
        const cp = document.getElementById('currentPassword');
        const np = document.getElementById('newPassword');
        if (cp) cp.value = '';
        if (np) np.value = '';

        if (data.userData) {
          // Update sessionStorage
          const currentUser = JSON.parse(sessionStorage.getItem('user') || '{}');
          const updatedUser = {
            ...currentUser,
            email: data.userData.email,
            firstName: data.userData.firstName,
            lastName: data.userData.lastName,
          };
          try {
            sessionStorage.setItem('user', JSON.stringify(updatedUser));
          } catch {}

          // Update PHP session in background
          await updatePhpSession(updatedUser);

          // Reload after short delay
          setTimeout(() => window.location.reload(), 2000);
        }
      } catch (err) {
        if (errorMessage) {
          errorMessage.textContent = err?.message || String(err);
          errorMessage.classList.remove('hidden');
        }
      }
    });
  }

  function init() {
    bindForm();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
