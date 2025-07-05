
<!-- Database-driven CSS for login -->
<style id="login-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadLoginCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=login');
            const cssText = await response.text();
            const styleElement = document.getElementById('login-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ login CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load login CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>login CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadLoginCSS);
</script>

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
        
        <!-- Single Login Button -->
        <button type="submit" id="loginButton"
                class="w-full text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-150"
                style="background-color: #87ac3a !important; border: 2px solid #556B2F !important; min-height: 48px !important; font-size: 16px !important; cursor: pointer !important; display: block !important; visibility: visible !important;"
                onmouseover="this.style.backgroundColor='#a3cc4a'"
                onmouseout="this.style.backgroundColor='#87ac3a'">
            Login
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">
        Don't have an account? 
        <a href="/?page=register" class="font-medium text-[#87ac3a] hover:text-[#a3cc4a]">
            Create one here
        </a>
    </p>
</section>


