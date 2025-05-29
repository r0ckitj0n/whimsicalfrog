<?php
// Login page section
?>
<section id="loginPage" class="max-w-md mx-auto mt-10 p-8 bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F] mb-6">Login to Your Account</h2>
    <div id="errorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert"></div>
    <form id="loginForm">
        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="username" name="username" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700">Password:</label>
            <input type="password" id="password" name="password" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] sm:text-sm">
        </div>
        <button type="submit" 
                class="w-full bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150">
            Login
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        Don't have an account? 
        <a href="/?page=register" class="font-medium text-[#6B8E23] hover:text-[#556B2F]">
            Create one here
        </a>
    </p>
</section>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('errorMessage');
    
    try {
        const response = await fetch('http://localhost:3000/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Login failed');
        }
        
        // Store user data in both session storage and PHP session
        sessionStorage.setItem('user', JSON.stringify(data));
        
        // Store in PHP session via AJAX
        await fetch('/set_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        // Check if there was a pending checkout
        const pendingCheckout = localStorage.getItem('pendingCheckout');
        if (pendingCheckout === 'true') {
            localStorage.removeItem('pendingCheckout');
            window.location.href = '/?page=cart';
        } else {
            // Redirect based on role
            if (data.role === 'Admin') {
                window.location.href = '/?page=admin';
            } else {
                window.location.href = '/?page=shop';
            }
        }
        
    } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
    }
});
</script> 