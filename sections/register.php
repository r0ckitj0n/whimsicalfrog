<?php
// Register page section
?>
<section id="registerPage" class="max-w-md mx-auto mt-10 p-8 bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F] mb-6">Create an Account</h2>
    <div id="registerErrorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert"></div>
    <div id="registerSuccessMessage" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
        Registration successful! You can now <a href="/?page=login" class="underline">login</a>.
    </div>
    <form id="registerForm">
        <div class="mb-4">
            <label for="registerUsername" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="registerUsername" name="username" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerEmail" class="block text-sm font-medium text-gray-700">Email:</label>
            <input type="email" id="registerEmail" name="email" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerPassword" class="block text-sm font-medium text-gray-700">Password:</label>
            <input type="password" id="registerPassword" name="password" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerFirstName" class="block text-sm font-medium text-gray-700">First Name:</label>
            <input type="text" id="registerFirstName" name="firstName" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerLastName" class="block text-sm font-medium text-gray-700">Last Name:</label>
            <input type="text" id="registerLastName" name="lastName" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerPhone" class="block text-sm font-medium text-gray-700">Phone Number:</label>
            <input type="tel" id="registerPhone" name="phoneNumber" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerAddress1" class="block text-sm font-medium text-gray-700">Address Line 1:</label>
            <input type="text" id="registerAddress1" name="addressLine1" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4">
            <label for="registerAddress2" class="block text-sm font-medium text-gray-700">Address Line 2 (Optional):</label>
            <input type="text" id="registerAddress2" name="addressLine2" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-4 grid grid-cols-2 gap-4">
            <div>
                <label for="registerCity" class="block text-sm font-medium text-gray-700">City:</label>
                <input type="text" id="registerCity" name="city" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            </div>
            <div>
                <label for="registerState" class="block text-sm font-medium text-gray-700">State:</label>
                <input type="text" id="registerState" name="state" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
            </div>
        </div>
        <div class="mb-6">
            <label for="registerZipCode" class="block text-sm font-medium text-gray-700">Zip Code:</label>
            <input type="text" id="registerZipCode" name="zipCode" 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <button type="submit" 
                class="w-full bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150">
            Register
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        Already have an account? 
        <a href="/?page=login" class="font-medium text-[#6B8E23] hover:text-[#556B2F]">
            Login here
        </a>
    </p>
</section>

<script>
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('registerUsername').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const firstName = document.getElementById('registerFirstName').value;
    const lastName = document.getElementById('registerLastName').value;
    const phone = document.getElementById('registerPhone').value;
    const addressLine1 = document.getElementById('registerAddress1').value;
    const addressLine2 = document.getElementById('registerAddress2').value;
    const city = document.getElementById('registerCity').value;
    const state = document.getElementById('registerState').value;
    const zipCode = document.getElementById('registerZipCode').value;
    const errorMessage = document.getElementById('registerErrorMessage');
    const successMessage = document.getElementById('registerSuccessMessage');
    
    // Hide any previous messages
    errorMessage.classList.add('hidden');
    successMessage.classList.add('hidden');
    
    // Function to detect mobile device
    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
               window.innerWidth <= 768;
    }
    
    // Use direct SQL queries through process_register.php instead of API endpoint
    const registerUrl = '/process_register.php';
    try {
        const response = await fetch(registerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
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
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Registration failed');
        }
        
        // Check if auto-login was successful
        if (data.success && data.autoLogin && data.userData) {
            // Store user data in sessionStorage for client-side persistence
            sessionStorage.setItem('user', JSON.stringify(data.userData));
            
            // Determine redirect URL based on device type
            const redirectUrl = isMobileDevice() ? '/?page=shop' : '/?page=main_room';
            const destinationName = isMobileDevice() ? 'shop' : 'main room';
            
            // Hide the form
            document.getElementById('registerForm').style.display = 'none';
            
            // Show brief success message before redirect
            successMessage.innerHTML = `
                <strong>Welcome ${data.userData.firstName || data.userData.username}!</strong><br>
                Registration successful! Redirecting you to the ${destinationName} in 5 seconds...<br><br>
                <a href="${redirectUrl}" class="inline-block mt-2 px-4 py-2 bg-[#6B8E23] hover:bg-[#556B2F] text-black font-bold rounded-md focus:outline-none focus:shadow-outline transition duration-150">
                    Go to ${destinationName} now →
                </a>
            `;
            successMessage.classList.remove('hidden');
            
            // Redirect after a short delay to show the success message
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 5000);
            
        } else {
            // Fallback - show success message with login link (shouldn't happen with auto-login)
            successMessage.classList.remove('hidden');
        }
        
        // Clear form
        document.getElementById('registerForm').reset();
        
    } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
    }
});
</script>
