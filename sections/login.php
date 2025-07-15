<?php
// Login page section
?>
<section id="loginPage" class="max-w-md bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F]">Login to Your Account</h2>
    <div id="errorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 rounded" role="alert"></div>
    <form id="loginForm">
        <div class="">
            <label for="username" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="username" name="username" required 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#87ac3a] focus:border-[#87ac3a] sm:text-sm">
        </div>
        <div class="">
            <label for="password" class="block text-sm font-medium text-gray-700">Password:</label>
            <input type="password" id="password" name="password" required 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#87ac3a] focus:border-[#87ac3a] sm:text-sm">
        </div>
        
        <!- Single Login Button ->
        <button type="submit" id="loginButton"
                class="w-full text-white font-bold rounded-md focus:outline-none focus:shadow-outline transition duration-150 login_button_bg login_button_border login_button_height login_button_size btn_cursor_pointer login_button_display login_button_visible">
            Login
        </button>
    </form>
    <p class="text-center text-sm text-gray-600">
        Don't have an account? 
        <a href="/?page=register" class="font-medium text-[#87ac3a] hover:text-[#a3cc4a]">
            Create one here
        </a>
    </p>
</section>


