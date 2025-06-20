<?php
// Account settings page
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=login');
    exit;
}

// Verify user is logged in
if (!isset($isLoggedIn) || !$isLoggedIn) {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Please login to access your account settings</h1></div>';
    exit;
}
?>

<section id="accountSettingsPage" class="max-w-md mx-auto mt-10 p-8 bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F] mb-6">Account Settings</h2>
    
    <div id="accountErrorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert"></div>
    <div id="accountSuccessMessage" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
        Your account has been updated successfully!
    </div>
    
    <form id="accountSettingsForm">
        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" readonly
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-gray-100 rounded-md shadow-sm sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Username cannot be changed</p>
        </div>
        
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="mb-4">
            <label for="firstName" class="block text-sm font-medium text-gray-700">First Name:</label>
            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['firstName'] ?? ''); ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="mb-4">
            <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name:</label>
            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userData['lastName'] ?? ''); ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        
        <div class="mb-6">
            <label for="currentPassword" class="block text-sm font-medium text-gray-700">Current Password:</label>
            <input type="password" id="currentPassword" name="currentPassword" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Required to save changes</p>
        </div>
        
        <div class="mb-6">
            <label for="newPassword" class="block text-sm font-medium text-gray-700">New Password:</label>
            <input type="password" id="newPassword" name="newPassword"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password</p>
        </div>
        
        <button type="submit" 
                class="w-full bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150">
            Save Changes
        </button>
    </form>
    
    <div class="mt-6 pt-6 border-t border-gray-200">
        <a href="/?page=shop" class="block text-center text-[#6B8E23] hover:text-[#556B2F]">
            ← Back to Shop
        </a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accountSettingsForm = document.getElementById('accountSettingsForm');
    const errorMessage = document.getElementById('accountErrorMessage');
    const successMessage = document.getElementById('accountSuccessMessage');
    
    accountSettingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide any previous messages
        errorMessage.classList.add('hidden');
        successMessage.classList.add('hidden');
        
        // Get form data
        const formData = {
            userId: <?php echo json_encode($userData['userId'] ?? ''); ?>,
            email: document.getElementById('email').value,
            firstName: document.getElementById('firstName').value,
            lastName: document.getElementById('lastName').value,
            currentPassword: document.getElementById('currentPassword').value,
            newPassword: document.getElementById('newPassword').value
        };
        
        try {
            // Use direct SQL query through process_account_update.php instead of API endpoint
            const response = await fetch('/process_account_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to update account');
            }
            
            // Show success message
            successMessage.classList.remove('hidden');
            
            // Clear password fields
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            
            // Update session data
            if (data.userData) {
                // Update session storage
                const currentUser = JSON.parse(sessionStorage.getItem('user') || '{}');
                const updatedUser = {
                    ...currentUser,
                    email: data.userData.email,
                    firstName: data.userData.firstName,
                    lastName: data.userData.lastName
                };
                sessionStorage.setItem('user', JSON.stringify(updatedUser));
                
                // Update PHP session
                await fetch('/set_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(updatedUser)
                });
                
                // Refresh page after a short delay to show updated info
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        } catch (error) {
            // Show error message
            errorMessage.textContent = error.message;
            errorMessage.classList.remove('hidden');
        }
    });
});
</script>
