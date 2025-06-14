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
