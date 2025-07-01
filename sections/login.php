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
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#87ac3a] focus:border-[#87ac3a] sm:text-sm">
        </div>
        <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700">Password:</label>
            <input type="password" id="password" name="password" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#87ac3a] focus:border-[#87ac3a] sm:text-sm">
        </div>
        
        <!-- Enhanced Login Button with Multiple Styling Approaches -->
        <button type="submit" id="loginButton"
                class="w-full text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150 mb-4"
                style="background-color: #87ac3a !important; border: 2px solid #556B2F !important; min-height: 48px !important; font-size: 16px !important; cursor: pointer !important; display: block !important; visibility: visible !important;"
                onmouseover="this.style.backgroundColor='#a3cc4a'"
                onmouseout="this.style.backgroundColor='#87ac3a'">
            🔑 Login to WhimsicalFrog
        </button>
        
        <!-- Fallback button with different styling -->
        <input type="submit" value="Login (Alternative)" 
               class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-md cursor-pointer mb-4"
               style="background-color: #87ac3a !important; color: white !important; border: none !important; min-height: 48px !important; font-size: 16px !important;">
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        Don't have an account? 
        <a href="/?page=register" class="font-medium text-[#87ac3a] hover:text-[#a3cc4a]">
            Create one here
        </a>
    </p>
</section>

<style>
/* Ensure login button visibility */
#loginButton {
    background: #87ac3a !important;
    color: white !important;
    border: 2px solid #556B2F !important;
    padding: 12px 16px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    width: 100% !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 999 !important;
    position: relative !important;
}

#loginButton:hover {
    background: #a3cc4a !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

#loginButton:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Additional button styling for better visibility */
.login-btn-container {
    margin: 20px 0;
    text-align: center;
}
</style>
