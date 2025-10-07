// Public page module: register
// Handles register form submission and success/redirect flow

import { ApiClient } from '../src/core/api-client.js';

console.log('[Page] register-page.js loaded');

function ready(fn) {
  if (document.readyState !== 'loading') fn();
  else document.addEventListener('DOMContentLoaded', fn, { once: true });
}

ready(() => {
  const form = document.getElementById('registerForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const username = document.getElementById('registerUsername')?.value;
    const email = document.getElementById('registerEmail')?.value;
    const password = document.getElementById('registerPassword')?.value;
    const firstName = document.getElementById('registerFirstName')?.value;
    const lastName = document.getElementById('registerLastName')?.value;
    const phone = document.getElementById('registerPhone')?.value;
    const addressLine1 = document.getElementById('registerAddress1')?.value;
    const addressLine2 = document.getElementById('registerAddress2')?.value;
    const city = document.getElementById('registerCity')?.value;
    const state = document.getElementById('registerState')?.value;
    const zipCode = document.getElementById('registerZipCode')?.value;

    const errorMessage = document.getElementById('registerErrorMessage');
    const successMessage = document.getElementById('registerSuccessMessage');

    // Hide any previous messages
    if (errorMessage) errorMessage.classList.add('hidden');
    if (successMessage) successMessage.classList.add('hidden');

    function isMobileDevice() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;
    }

    const registerUrl = '/process_register.php';
    try {
      const data = await ApiClient.request(registerUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username,
          email,
          password,
          firstName,
          lastName,
          phoneNumber: phone,
          addressLine1,
          addressLine2,
          city,
          state,
          zipCode
        })
      });

      if (data.success && data.autoLogin && data.userData) {
        sessionStorage.setItem('user', JSON.stringify(data.userData));
        const redirectUrl = isMobileDevice() ? '/?page=shop' : '/?page=room_main';
        const destinationName = isMobileDevice() ? 'shop' : 'main room';

        if (form) form.classList.add('hidden');
        if (successMessage) {
          successMessage.innerHTML = `
            <strong>Welcome ${data.userData.firstName || data.userData.username}!</strong><br>
            Registration successful! Redirecting you to the ${destinationName} in 5 seconds...<br><br>
            <a href="${redirectUrl}" class="inline-block bg-[#6B8E23] hover:bg-[#556B2F] text-black font-bold rounded-md focus:outline-none focus:shadow-outline transition duration-150">
              Go to ${destinationName} now →
            </a>
          `;
          successMessage.classList.remove('hidden');
        }

        setTimeout(() => { window.location.href = redirectUrl; }, 5000);
      } else {
        if (successMessage) successMessage.classList.remove('hidden');
      }

      form.reset();
    } catch (error) {
      if (errorMessage) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
      } else {
        console.error('[RegisterPage] Error:', error);
      }
    }
  });
});
