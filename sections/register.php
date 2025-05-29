<?php
// Registration page content
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        function onRecaptchaLoad() {
            console.log('reCAPTCHA v3 script loaded successfully');
            // Execute reCAPTCHA when the page loads
            grecaptcha.execute('6LdqsUUrAAAAAIaolqeDfneTir7TnxAdCwe_n3s0', {action: 'register'})
                .then(function(token) {
                    console.log('reCAPTCHA token received:', token);
                    // Store the token in a hidden input
                    document.getElementById('recaptcha-token').value = token;
                })
                .catch(function(error) {
                    console.error('reCAPTCHA error:', error);
                });
        }
    </script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LdqsUUrAAAAAIaolqeDfneTir7TnxAdCwe_n3s0&onload=onRecaptchaLoad" async defer></script>
    <style>
        .g-recaptcha {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<section class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-merienda text-[#556B2F]">
                Create Your Account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="/?page=login" class="font-medium text-[#6B8E23] hover:text-[#556B2F]">
                    sign in to your existing account
                </a>
            </p>
        </div>
        <form id="registerForm" class="mt-8 space-y-6" action="#" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="firstName" class="sr-only">First Name</label>
                        <input id="firstName" name="firstName" type="text" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-tl-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                               placeholder="First Name">
                    </div>
                    <div>
                        <label for="lastName" class="sr-only">Last Name</label>
                        <input id="lastName" name="lastName" type="text" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-tr-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                               placeholder="Last Name">
                    </div>
                </div>
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Username">
                </div>
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Email address">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
                <div>
                    <label for="confirmPassword" class="sr-only">Confirm Password</label>
                    <input id="confirmPassword" name="confirmPassword" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Confirm Password">
                </div>
            </div>

            <div class="rounded-md shadow-sm -space-y-px mt-6">
                <h3 class="text-lg font-medium text-[#556B2F] mb-4">Contact Information</h3>
                <div>
                    <label for="phoneNumber" class="sr-only">Phone Number</label>
                    <input id="phoneNumber" name="phoneNumber" type="tel" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Phone Number">
                </div>
            </div>

            <div class="rounded-md shadow-sm -space-y-px mt-6">
                <h3 class="text-lg font-medium text-[#556B2F] mb-4">Address</h3>
                <div>
                    <label for="addressLine1" class="sr-only">Address Line 1</label>
                    <input id="addressLine1" name="addressLine1" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Address Line 1">
                </div>
                <div>
                    <label for="addressLine2" class="sr-only">Address Line 2</label>
                    <input id="addressLine2" name="addressLine2" type="text" 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                           placeholder="Address Line 2 (Optional)">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="state" class="sr-only">State</label>
                        <input id="state" name="state" type="text" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                               placeholder="State">
                    </div>
                    <div>
                        <label for="zipCode" class="sr-only">Zip Code</label>
                        <input id="zipCode" name="zipCode" type="text" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23] focus:z-10 sm:text-sm" 
                               placeholder="Zip Code">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center py-4">
                <input type="hidden" id="recaptcha-token" name="recaptcha-token">
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-[#6B8E23] hover:bg-[#556B2F] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#6B8E23]">
                    Create Account
                </button>
            </div>

            <div id="errorMessage" class="hidden text-red-500 text-center text-sm"></div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking reCAPTCHA...');
});

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const firstName = document.getElementById('firstName').value;
    const lastName = document.getElementById('lastName').value;
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const phoneNumber = document.getElementById('phoneNumber').value;
    const addressLine1 = document.getElementById('addressLine1').value;
    const addressLine2 = document.getElementById('addressLine2').value;
    const state = document.getElementById('state').value;
    const zipCode = document.getElementById('zipCode').value;
    const errorMessage = document.getElementById('errorMessage');
    
    // Validate passwords match
    if (password !== confirmPassword) {
        errorMessage.textContent = 'Passwords do not match';
        errorMessage.classList.remove('hidden');
        return;
    }
    
    // Get reCAPTCHA token
    const recaptchaToken = document.getElementById('recaptcha-token').value;
    console.log('reCAPTCHA token:', recaptchaToken);
    
    if (!recaptchaToken) {
        errorMessage.textContent = 'Please wait while we verify your request';
        errorMessage.classList.remove('hidden');
        return;
    }
    
    try {
        const response = await fetch('http://localhost:3000/api/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                firstName,
                lastName,
                username,
                email,
                password,
                phoneNumber,
                addressLine1,
                addressLine2,
                state,
                zipCode,
                recaptchaToken
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Registration failed');
        }
        
        // Registration successful
        window.location.href = '/?page=login';
        
    } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
        // Refresh reCAPTCHA token
        grecaptcha.execute('6LdqsUUrAAAAAIaolqeDfneTir7TnxAdCwe_n3s0', {action: 'register'})
            .then(function(token) {
                document.getElementById('recaptcha-token').value = token;
            });
    }
});
</script>
</body>
</html> 