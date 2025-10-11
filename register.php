<?php
// Register page section
?>
<section id="registerPage" class="max-w-md bg-white rounded-lg shadow-xl">
    <h2 class="text-3xl font-merienda text-center text-[#556B2F]">Create an Account</h2>
    <div id="registerErrorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 rounded" role="alert"></div>
    <div id="registerSuccessMessage" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 rounded" role="alert">
        Registration successful! You can now <a href="/?page=login" class="underline">login</a>.
    </div>
    <form id="registerForm">
        <div class="">
            <label for="registerUsername" class="block text-sm font-medium text-gray-700">Username:</label>
            <input type="text" id="registerUsername" name="username" required 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerEmail" class="block text-sm font-medium text-gray-700">Email:</label>
            <input type="email" id="registerEmail" name="email" required 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerPassword" class="block text-sm font-medium text-gray-700">Password:</label>
            <input type="password" id="registerPassword" name="password" required 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerFirstName" class="block text-sm font-medium text-gray-700">First Name:</label>
            <input type="text" id="registerFirstName" name="firstName" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerLastName" class="block text-sm font-medium text-gray-700">Last Name:</label>
            <input type="text" id="registerLastName" name="lastName" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerPhone" class="block text-sm font-medium text-gray-700">Phone Number:</label>
            <input type="tel" id="registerPhone" name="phoneNumber" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerAddress1" class="block text-sm font-medium text-gray-700">Address Line 1:</label>
            <input type="text" id="registerAddress1" name="addressLine1" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="">
            <label for="registerAddress2" class="block text-sm font-medium text-gray-700">Address Line 2 (Optional):</label>
            <input type="text" id="registerAddress2" name="addressLine2" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="registerCity" class="block text-sm font-medium text-gray-700">City:</label>
                <input type="text" id="registerCity" name="city" 
                       class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
            </div>
            <div>
                <label for="registerState" class="block text-sm font-medium text-gray-700">State:</label>
                <input type="text" id="registerState" name="state" 
                       class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
            </div>
        </div>
        <div class="">
            <label for="registerZipCode" class="block text-sm font-medium text-gray-700">Zip Code:</label>
            <input type="text" id="registerZipCode" name="zipCode" 
                   class="block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#BF5700] focus:border-[#BF5700] sm:text-sm">
        </div>
        <button type="submit" 
                class="w-full bg-[#87ac3a] hover:bg-[#BF5700] text-white font-bold rounded-md focus:outline-none focus:shadow-outline transition duration-150">
            Register
        </button>
    </form>
    <p class="text-center text-sm text-gray-600">
        Already have an account? 
        <a href="/?page=login" class="font-medium text-[#BF5700] hover:text-[#A04000]">
            Login here
        </a>
    </p>
</section>
<!-- Inline register scripts removed; logic migrated to Vite module: js/pages/register-page.js -->
