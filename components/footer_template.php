<?php
/**
 * WhimsicalFrog Footer Template
 * Comprehensive footer component with multiple layout options
 * Uses CSS variables from Global CSS Rules system
 */

// Load footer settings from database (if available)
// $footerSettings = getFooterSettings(); // Implement this function as needed

// Default footer configuration
$footerConfig = [
    'layout' => '4-column', // Options: '4-column', '3-column', '2-column', 'single'
    'show_logo' => true,
    'show_social' => true,
    'show_newsletter' => true,
    'show_contact' => true,
    'show_links' => true,
    'theme' => 'dark' // Options: 'dark', 'light', 'brand'
];

// Footer content data
$footerData = [
    'company' => [
        'name' => 'Whimsical Frog',
        'tagline' => 'Custom Crafts & Creative Designs',
        'logo' => '/images/WhimsicalFrog_Logo.webp',
        'description' => 'Creating unique, personalized items that bring joy and whimsy to your world.'
    ],
    'contact' => [
        'address' => '123 Craft Lane, Creative City, CC 12345',
        'phone' => '(555) 123-FROG',
        'email' => 'hello@whimsicalfrog.us',
        'hours' => 'Mon-Fri 9AM-6PM'
    ],
    'social' => [
        'facebook' => 'https://facebook.com/whimsicalfrog',
        'instagram' => 'https://instagram.com/whimsicalfrog',
        'twitter' => 'https://twitter.com/whimsicalfrog',
        'pinterest' => 'https://pinterest.com/whimsicalfrog'
    ],
    'links' => [
        'About Us' => '/?page=about',
        'Shop' => '/?page=shop',
        'Custom Orders' => '/?page=custom',
        'Contact' => '/?page=contact',
        'Privacy Policy' => '/?page=privacy',
        'Terms of Service' => '/?page=terms',
        'Shipping Info' => '/?page=shipping',
        'Returns' => '/?page=returns'
    ]
];
?>

<!-- Footer CSS now loaded from database via main CSS system -->

<!-- Main Footer -->
<footer class="site-footer footer-theme-<?php echo $footerConfig['theme']; ?>">
    <div class="footer-container">
        
        <?php if ($footerConfig['layout'] === '4-column'): ?>
        <!-- 4-Column Layout -->
        <div class="footer-grid-4">
            
            <!-- Company Info Column -->
            <div class="footer-section">
                <?php if ($footerConfig['show_logo']): ?>
                <div class="footer-logo">
                    <img src="<?php echo $footerData['company']['logo']; ?>" alt="<?php echo $footerData['company']['name']; ?> Logo">
                </div>
                <?php endif; ?>
                
                <h3 class="footer-heading"><?php echo $footerData['company']['name']; ?></h3>
                <p class="footer-tagline"><?php echo $footerData['company']['tagline']; ?></p>
                <p class="footer-text"><?php echo $footerData['company']['description']; ?></p>
            </div>
            
            <!-- Quick Links Column -->
            <div class="footer-section">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-nav">
                    <?php foreach (array_slice($footerData['links'], 0, 4) as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Support Column -->
            <div class="footer-section">
                <h3 class="footer-heading">Support</h3>
                <ul class="footer-nav">
                    <?php foreach (array_slice($footerData['links'], 4) as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Contact & Newsletter Column -->
            <div class="footer-section">
                <?php if ($footerConfig['show_contact']): ?>
                <h3 class="footer-heading">Contact Us</h3>
                <div class="footer-contact">
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📍</span>
                        <span><?php echo $footerData['contact']['address']; ?></span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">🕒</span>
                        <span><?php echo $footerData['contact']['hours']; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter">
                                    <h4 class="footer-heading">Newsletter</h4>
                <p class="footer-text">Get updates on new products and special offers!</p>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Your email address" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Subscribe</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php elseif ($footerConfig['layout'] === '3-column'): ?>
        <!-- 3-Column Layout -->
        <div class="footer-grid-3">
            
            <!-- Company Info Column -->
            <div class="footer-section">
                <?php if ($footerConfig['show_logo']): ?>
                <div class="footer-logo">
                    <img src="<?php echo $footerData['company']['logo']; ?>" alt="<?php echo $footerData['company']['name']; ?> Logo">
                </div>
                <?php endif; ?>
                
                <h3 class="footer-heading"><?php echo $footerData['company']['name']; ?></h3>
                <p class="footer-tagline"><?php echo $footerData['company']['tagline']; ?></p>
                <p class="footer-text"><?php echo $footerData['company']['description']; ?></p>
            </div>
            
            <!-- Links Column -->
            <div class="footer-section">
                <h3 class="footer-heading">Navigation</h3>
                <ul class="footer-nav">
                    <?php foreach ($footerData['links'] as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Contact Column -->
            <div class="footer-section">
                <h3 class="footer-heading">Get In Touch</h3>
                <?php if ($footerConfig['show_contact']): ?>
                <div class="footer-contact">
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter">
                    <h4 class="footer-heading">Stay Updated</h4>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Email address" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Join</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php elseif ($footerConfig['layout'] === '2-column'): ?>
        <!-- 2-Column Layout -->
        <div class="footer-grid-2">
            
            <!-- Left Column -->
            <div class="footer-section">
                <?php if ($footerConfig['show_logo']): ?>
                <div class="footer-logo footer-text-left">
                    <img src="<?php echo $footerData['company']['logo']; ?>" alt="<?php echo $footerData['company']['name']; ?> Logo">
                </div>
                <?php endif; ?>
                
                <h3 class="footer-heading"><?php echo $footerData['company']['name']; ?></h3>
                <p class="footer-tagline"><?php echo $footerData['company']['tagline']; ?></p>
                <p class="footer-text"><?php echo $footerData['company']['description']; ?></p>
                
                <?php if ($footerConfig['show_contact']): ?>
                <div class="footer-contact footer-mt-medium">
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column -->
            <div class="footer-section">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-nav">
                    <?php foreach ($footerData['links'] as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter footer-mt-medium">
                    <h4 class="footer-heading">Newsletter</h4>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Your email" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Subscribe</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php else: ?>
        <!-- Single Column Layout -->
        <div class="footer-single">
            <?php if ($footerConfig['show_logo']): ?>
            <div class="footer-logo">
                <img src="<?php echo $footerData['company']['logo']; ?>" alt="<?php echo $footerData['company']['name']; ?> Logo">
            </div>
            <?php endif; ?>
            
            <h3 class="footer-heading"><?php echo $footerData['company']['name']; ?></h3>
            <p class="footer-tagline"><?php echo $footerData['company']['tagline']; ?></p>
            <p class="footer-text"><?php echo $footerData['company']['description']; ?></p>
            
            <?php if ($footerConfig['show_links']): ?>
            <ul class="footer-nav-horizontal footer-mt-medium">
                <?php foreach ($footerData['links'] as $text => $url): ?>
                <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php if ($footerConfig['show_newsletter']): ?>
            <div class="footer-newsletter footer-mt-medium">
                <h4 class="footer-heading">Stay Connected</h4>
                <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                    <input type="email" name="email" placeholder="Enter your email address" class="footer-newsletter-input" required>
                    <button type="submit" class="footer-newsletter-button">Subscribe</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Social Media Icons (shown for all layouts) -->
        <?php if ($footerConfig['show_social']): ?>
        <hr class="footer-divider">
        <div class="footer-social">
            <?php if (!empty($footerData['social']['facebook'])): ?>
            <a href="<?php echo $footerData['social']['facebook']; ?>" class="footer-social-icon" target="_blank" rel="noopener" aria-label="Facebook">
                📘
            </a>
            <?php endif; ?>
            
            <?php if (!empty($footerData['social']['instagram'])): ?>
            <a href="<?php echo $footerData['social']['instagram']; ?>" class="footer-social-icon" target="_blank" rel="noopener" aria-label="Instagram">
                📷
            </a>
            <?php endif; ?>
            
            <?php if (!empty($footerData['social']['twitter'])): ?>
            <a href="<?php echo $footerData['social']['twitter']; ?>" class="footer-social-icon" target="_blank" rel="noopener" aria-label="Twitter">
                🐦
            </a>
            <?php endif; ?>
            
            <?php if (!empty($footerData['social']['pinterest'])): ?>
            <a href="<?php echo $footerData['social']['pinterest']; ?>" class="footer-social-icon" target="_blank" rel="noopener" aria-label="Pinterest">
                📌
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Copyright Section -->
        <div class="footer-copyright">
            <p class="footer-copyright-text">
                © <?php echo date('Y'); ?> <?php echo $footerData['company']['name']; ?>. All rights reserved.
            </p>
            <div class="footer-copyright-links">
                <a href="/?page=privacy">Privacy Policy</a>
                <a href="/?page=terms">Terms of Service</a>
                <a href="/?page=sitemap">Sitemap</a>
            </div>
        </div>
        
    </div>
</footer>

<!-- JavaScript for Newsletter Signup (Optional) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.footer-newsletter-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[name="email"]').value;
            const button = this.querySelector('.footer-newsletter-button');
            const originalText = button.textContent;
            
            // Show loading state
            button.textContent = 'Subscribing...';
            button.disabled = true;
            
            // Submit form via AJAX
            fetch('/api/newsletter_signup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = 'Subscribed!';
                    button.style.backgroundColor = 'var(--footer-link-hover-color)';
                    this.querySelector('input[name="email"]').value = '';
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                        button.style.backgroundColor = '';
                    }, 3000);
                } else {
                    throw new Error(data.message || 'Subscription failed');
                }
            })
            .catch(error => {
                button.textContent = 'Try Again';
                button.disabled = false;
                console.error('Newsletter signup error:', error);
                
                setTimeout(() => {
                    button.textContent = originalText;
                }, 3000);
            });
        });
    });
});
</script> 