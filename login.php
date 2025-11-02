<?php
// Login page section
?>
<section id="loginPage" class="login-page-container">
    <div class="login-form-wrapper">
        <h2 class="login-title">Login to Your Account</h2>
        <div id="errorMessage" class="login-error-message hidden" role="alert"></div>
        <form id="loginForm" class="login-form">
          <div class="form-group">
            <label for="username" class="form-label">Username:</label>
            <input type="text" id="username" name="username" required autocomplete="username"
                       class="form-input login-input">
          </div>
          <div class="form-group">
            <label for="password" class="form-label">Password:</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="form-input login-input">
          </div>

            <!-- Single Login Button -->
            <div class="form-group">
                <button type="submit" id="loginButton" class="login-button">
                    Login
                </button>
            </div>
        </form>
        <p class="login-register-link">
            Don't have an account?
            <a href="/register" class="login-link">
                Create one here
            </a>
        </p>
    </div>
</section>


