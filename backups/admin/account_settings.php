<?php
// Account settings page
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=login');
    exit;
}

// Verify user is logged in
if (!isset($isLoggedIn) || !$isLoggedIn) {
    echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access your account settings</h1></div>';
    exit;
}
?>

<section id="accountSettingsPage" class="max-w-md bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F]">Account Settings</h2>
    
    <div id="accountErrorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 rounded" role="alert"></div>
    <div id="accountSuccessMessage" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 rounded" role="alert">
        Your account has been updated successfully!
    </div>
    
    <form id="accountSettingsForm">
        <div class="">
            <label for="username" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" readonly
                   class="block w-full border border-gray-300 bg-gray-100 rounded-md shadow-sm sm:text-sm">
            <p class="text-xs text-gray-500">Username cannot be changed</p>
        </div>
        
        <div class="">
            <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="">
            <label for="firstName" class="block text-sm font-medium text-gray-700">First Name:</label>
            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['firstName'] ?? ''); ?>"
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="">
            <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name:</label>
            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userData['lastName'] ?? ''); ?>"
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="">
            <label for="currentPassword" class="block text-sm font-medium text-gray-700">Current Password:</label>
            <input type="password" id="currentPassword" name="currentPassword" required
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            <p class="text-xs text-gray-500">Required to save changes</p>
        </div>
        
        <div class="">
            <label for="newPassword" class="block text-sm font-medium text-gray-700">New Password:</label>
            <input type="password" id="newPassword" name="newPassword"
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            <p class="text-xs text-gray-500">Leave blank to keep current password</p>
        </div>
        
        <button type="submit" class="btn btn-primary w-full">
            Save Changes
        </button>
    </form>
    
    <div class="border-t border-gray-200">
        <a href="/?page=shop" class="block text-center text-[#6B8E23] hover:text-[#556B2F]">
            ← Back to Shop
        </a>
    </div>
</section>

<!-- Page Data for account settings module -->
<script type="application/json" id="account-settings-data">
<?= json_encode([
    'userId' => $userData['userId'] ?? null,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

<?php // Admin account settings script is loaded via app.js per-page imports ?>
