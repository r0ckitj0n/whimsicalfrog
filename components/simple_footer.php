<?php
/**
 * Simple Footer Component for WhimsicalFrog
 * Easy to include on any page with: <?php include 'components/simple_footer.php'; ?>
 */
?>

<!-- Simple Footer -->
<footer class="site-footer">
    <div class="footer-container">
        
        <!-- 3-Column Layout (automatically becomes single column on mobile) -->
        <div class="footer-grid-3">
            
            <!-- Company Info -->
            <div class="footer-section">
                <h3 class="footer-heading">Whimsical Frog</h3>
                <p class="footer-tagline">Custom Crafts & Creative Designs</p>
                <p class="footer-text">Creating unique, personalized items that bring joy and whimsy to your world.</p>
            </div>
            
            <!-- Quick Links -->
            <div class="footer-section">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-nav">
                    <li><a href="/?page=shop" class="footer-link">Shop</a></li>
                    <li><a href="/?page=about" class="footer-link">About Us</a></li>
                    <li><a href="/?page=contact" class="footer-link">Contact</a></li>
                    <li><a href="/?page=custom" class="footer-link">Custom Orders</a></li>
                </ul>
            </div>
            
            <!-- Contact Info -->
            <div class="footer-section">
                <h3 class="footer-heading">Get In Touch</h3>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">📞</span>
                    <a href="tel:555-123-FROG" class="footer-contact-link">(555) 123-FROG</a>
                </div>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">✉️</span>
                    <a href="mailto:hello@whimsicalfrog.us" class="footer-contact-link">hello@whimsicalfrog.us</a>
                </div>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">🕒</span>
                    <span>Mon-Fri 9AM-6PM</span>
                </div>
            </div>
            
        </div>
        
        <!-- Social Media Icons -->
        <hr class="footer-divider">
        <div class="footer-social">
            <a href="#" class="footer-social-icon" aria-label="Facebook">📘</a>
            <a href="#" class="footer-social-icon" aria-label="Instagram">📷</a>
            <a href="#" class="footer-social-icon" aria-label="Twitter">🐦</a>
            <a href="#" class="footer-social-icon" aria-label="Pinterest">📌</a>
        </div>
        
        <!-- Copyright -->
        <div class="footer-copyright">
            <p class="footer-copyright-text">
                © <?php echo date('Y'); ?> Whimsical Frog. All rights reserved.
            </p>
            <div class="footer-copyright-links">
                <a href="/?page=privacy">Privacy Policy</a>
                <a href="/?page=terms">Terms of Service</a>
            </div>
        </div>
        
    </div>
</footer>

<!-- 
USAGE INSTRUCTIONS:
===================

1. To use this footer on any page, simply add this line before the closing </body> tag:
   <?php include 'components/simple_footer.php'; ?>

2. The footer will automatically use the colors and styling from your Global CSS Rules.

3. To customize the footer appearance, go to:
   Admin Settings > Global CSS Rules > Footer tab

4. Available customizations include:
   - Background color
   - Text colors
   - Link colors and hover effects
   - Font sizes
   - Padding and spacing
   - Border styles
   - Social icon colors

5. The footer is fully responsive and will automatically adapt to mobile devices.

6. To add your own social media links, replace the "#" placeholders with your actual URLs.

7. To change the company information, edit the text directly in this file.

8. For more complex footer layouts, see components/footer_template.php for examples of:
   - 4-column layout
   - 2-column layout  
   - Single column layout
   - Newsletter signup
   - Logo integration
--> 