<?php
/**
 * Account Settings Modal Component
 * Renders a global modal for managing user profile, password, and addresses.
 */

if (!function_exists('renderAccountSettingsModal')) {
    function renderAccountSettingsModal(): string {
        ob_start();
        ?>
<div id="accountSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="accountSettingsTitle">
  <div class="admin-modal admin-modal-content w-[90vw] max-w-[880px] max-h-[85vh] overflow-hidden">
    <div class="modal-header">
      <h2 id="accountSettingsTitle" class="admin-card-title">Account Settings</h2>
      <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
    </div>
    <div class="modal-body overflow-auto p-4 space-y-4">
      <div id="accountSettingsAlerts" class="space-y-2">
        <div id="accountSettingsError" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 rounded px-3 py-2 text-sm" role="alert"></div>
        <div id="accountSettingsSuccess" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 rounded px-3 py-2 text-sm" role="alert">Saved successfully.</div>
      </div>

      <form id="accountSettingsForm" class="space-y-3">
        <fieldset class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label for="acc_username" class="block text-sm font-medium text-gray-700">Username</label>
            <input id="acc_username" name="username" type="text" class="block w-full border border-gray-300 bg-gray-100 rounded-md shadow-sm sm:text-sm" readonly>
          </div>
          <div>
            <label for="acc_email" class="block text-sm font-medium text-gray-700">Email</label>
            <input id="acc_email" name="email" type="email" class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm" required>
          </div>
          <div>
            <label for="acc_firstName" class="block text-sm font-medium text-gray-700">First Name</label>
            <input id="acc_firstName" name="firstName" type="text" class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm">
          </div>
          <div>
            <label for="acc_lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input id="acc_lastName" name="lastName" type="text" class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm">
          </div>
          <div>
            <label for="acc_phoneNumber" class="block text-sm font-medium text-gray-700">Phone Number</label>
            <input id="acc_phoneNumber" name="phoneNumber" type="tel" class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" placeholder="(555) 555-5555">
          </div>
        </fieldset>

        <fieldset class="border-t border-gray-200 pt-3">
          <legend class="text-sm font-semibold text-gray-800 mb-2">Change Password</legend>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label for="acc_currentPassword" class="block text-sm font-medium text-gray-700">Current Password</label>
              <input id="acc_currentPassword" name="currentPassword" type="password" class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" autocomplete="current-password" placeholder="Required to change password">
            </div>
            <div>
              <label for="acc_newPassword" class="block text-sm font-medium text-gray-700">New Password</label>
              <input id="acc_newPassword" name="newPassword" type="password" class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" autocomplete="new-password" placeholder="Leave blank to keep current">
            </div>
          </div>
        </fieldset>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn" data-action="account-settings-save">Save Changes</button>
        </div>
      </form>

      <section class="border-t border-gray-200 pt-3">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-base font-semibold">Addresses</h3>
          <button type="button" class="btn" data-action="account-address-add">Add Address</button>
        </div>
        <div id="accountAddressesList" class="divide-y divide-gray-200"></div>
      </section>

      <!-- Address Editor (inline) -->
      <template id="accountAddressEditorTemplate">
        <form class="wf-address-editor space-y-2 p-3 bg-gray-50 rounded border border-gray-200" data-action-scope="address-editor">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div>
              <label class="block text-sm font-medium">Label</label>
              <input name="address_name" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm" placeholder="Home, Work" required>
            </div>
            <div>
              <label class="block text-sm font-medium">Address Line 1</label>
              <input name="address_line1" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm" required>
            </div>
            <div>
              <label class="block text-sm font-medium">Address Line 2</label>
              <input name="address_line2" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm">
            </div>
            <div>
              <label class="block text-sm font-medium">City</label>
              <input name="city" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm" required>
            </div>
            <div>
              <label class="block text-sm font-medium">State</label>
              <input name="state" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm" required>
            </div>
            <div>
              <label class="block text-sm font-medium">Zip Code</label>
              <input name="zip_code" type="text" class="w-full border border-gray-300 rounded-md sm:text-sm" required>
            </div>
          </div>
          <div class="flex items-center justify-between pt-1">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Set as default</label>
            <div class="flex gap-2">
              <button class="btn" data-action="address-save">Save</button>
              <button type="button" class="btn btn-secondary" data-action="address-cancel">Cancel</button>
            </div>
          </div>
        </form>
      </template>

      <template id="accountAddressItemTemplate">
        <div class="wf-address-item py-3 flex items-start justify-between gap-3">
          <div class="text-sm">
            <div class="font-medium">{{address_name}} <span class="text-xs text-gray-500">{{default_badge}}</span></div>
            <div>{{address_line1}}{{address_line2_sep}}{{address_line2}}</div>
            <div>{{city}}, {{state}} {{zip_code}}</div>
          </div>
          <div class="flex gap-2 shrink-0">
            <button type="button" class="btn btn-secondary" data-action="address-edit" data-id="{{id}}">Edit</button>
            <button type="button" class="btn btn-secondary" data-action="address-default" data-id="{{id}}">Make Default</button>
            <button type="button" class="btn btn-danger" data-action="address-delete" data-id="{{id}}">Delete</button>
          </div>
        </div>
      </template>
    </div>
  </div>
</div>
        <?php
        return ob_get_clean();
    }
}
