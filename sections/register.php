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
        <div class="mb-6">
            <label for="registerLastName" class="block text-sm font-medium text-gray-700">Last Name:</label>
            <input type="text" id="registerLastName" name="lastName" 
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
    const errorMessage = document.getElementById('registerErrorMessage');
    const successMessage = document.getElementById('registerSuccessMessage');
    
    // Hide any previous messages
    errorMessage.classList.add('hidden');
    successMessage.classList.add('hidden');
    
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
                lastName 
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Registration failed');
        }
        
        // Show success message
        successMessage.classList.remove('hidden');
        
        // Clear form
        document.getElementById('registerForm').reset();
        
    } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
    }
});
</script>
