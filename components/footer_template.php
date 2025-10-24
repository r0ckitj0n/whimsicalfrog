<?php
/**
 * WhimsicalFrog Footer Template
 * Comprehensive footer component with multiple layout options
 * Uses CSS variables from Global CSS Rules system
 */

require_once dirname(__DIR__) . '/api/config.php';
@require_once dirname(__DIR__) . '/includes/site_settings.php';
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
        'name' => (function_exists('wf_site_name') ? wf_site_name() : ((defined('SITE_NAME') ? SITE_NAME : 'Your Site'))),
        'tagline' => (function_exists('wf_site_tagline') ? wf_site_tagline() : ''),
        'logo' => (function_exists('wf_brand_logo_path') ? wf_brand_logo_path() : (defined('BRAND_LOGO_PATH') ? BRAND_LOGO_PATH : '/images/logos/logo-whimsicalfrog.webp')),
        'description' => 'Creating unique, personalized items that bring joy and whimsy to your world.'
    ],
    'contact' => [
        'address' => '123 Craft Lane, Creative City, CC 12345',
        'phone' => '(555) 123-FROG',
        'email' => (function_exists('wf_business_email') ? wf_business_email() : ((defined('APP_URL') && APP_URL ? ('hello@' . parse_url(APP_URL, PHP_URL_HOST)) : 'hello@example.com'))),
        'hours' => 'Mon-Fri 9AM-6PM'
    ],
    'social' => (function_exists('wf_social_links') ? wf_social_links() : [
        'facebook' => (function_exists('wf_env') ? wf_env('SOCIAL_FACEBOOK', '') : ''),
        'instagram' => (function_exists('wf_env') ? wf_env('SOCIAL_INSTAGRAM', '') : ''),
        'twitter' => (function_exists('wf_env') ? wf_env('SOCIAL_TWITTER', '') : ''),
        'pinterest' => (function_exists('wf_env') ? wf_env('SOCIAL_PINTEREST', '') : ''),
    ]),
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

// Convert legacy querystring links (/?page=xyz) to clean paths (/xyz)
foreach ($footerData['links'] as $text => $url) {
    if (strpos($url, '/?page=') === 0) {
        $slug = substr($url, strlen('/?page='));
        // Remove any query parameters after slug
        $slug = strtok($slug, '&');
        $footerData['links'][$text] = '/' . $slug;
    }
}

?>

<!-- Footer CSS managed by Vite (main.css). Legacy link removed. -->

<!-- Main Footer -->
<footer class="site-footer footer-theme-<?php echo $footerConfig['theme']; ?>">
    <div class="footer-container">
        
        <?php if ($footerConfig['layout'] === '4-column'): ?>
        <!- 4-Column Layout ->
        <div class="footer-grid-4">
            
            <!- Company Info Column ->
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
            
            <!- Quick Links Column ->
            <div class="footer-section">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-nav">
                    <?php foreach (array_slice($footerData['links'], 0, 4) as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!- Support Column ->
            <div class="footer-section">
                <h3 class="footer-heading">Support</h3>
                <ul class="footer-nav">
                    <?php foreach (array_slice($footerData['links'], 4) as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!- Contact & Newsletter Column ->
            <div class="footer-section">
                <?php if ($footerConfig['show_contact']): ?>
                <h3 class="footer-heading">Contact Us</h3>
                <div class="footer-contact">
                    <?php if (!empty($footerData['contact']['address'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📍</span>
                        <span><?php echo $footerData['contact']['address']; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerData['contact']['phone'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerData['contact']['email'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerData['contact']['hours'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">🕒</span>
                        <span><?php echo $footerData['contact']['hours']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter">
                    <h4 class="footer-heading footer_newsletter_heading">Newsletter</h4>
                    <p class="footer-text footer_newsletter_text">Get updates on new items and special offers!</p>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Your email address" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Subscribe</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php elseif ($footerConfig['layout'] === '3-column'): ?>
        <!- 3-Column Layout ->
        <div class="footer-grid-3">
            
            <!- Company Info Column ->
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
            
            <!- Links Column ->
            <div class="footer-section">
                <h3 class="footer-heading">Navigation</h3>
                <ul class="footer-nav">
                    <?php foreach ($footerData['links'] as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!- Contact Column ->
            <div class="footer-section">
                <h3 class="footer-heading">Get In Touch</h3>
                <?php if ($footerConfig['show_contact']): ?>
                <div class="footer-contact">
                    <?php if (!empty($footerData['contact']['phone'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerData['contact']['email'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter">
                    <h4 class="footer-heading footer_newsletter_heading">Stay Updated</h4>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Email address" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Join</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php elseif ($footerConfig['layout'] === '2-column'): ?>
        <!- 2-Column Layout ->
        <div class="footer-grid-2">
            
            <!- Left Column ->
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
                    <?php if (!empty($footerData['contact']['phone'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <a href="tel:<?php echo str_replace(['(', ')', ' ', '-'], '', $footerData['contact']['phone']); ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['phone']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerData['contact']['email'])): ?>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <a href="mailto:<?php echo $footerData['contact']['email']; ?>" class="footer-contact-link">
                            <?php echo $footerData['contact']['email']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!- Right Column ->
            <div class="footer-section">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-nav">
                    <?php foreach ($footerData['links'] as $text => $url): ?>
                    <li><a href="<?php echo $url; ?>" class="footer-link"><?php echo $text; ?></a></li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($footerConfig['show_newsletter']): ?>
                <div class="footer-newsletter footer-mt-medium">
                    <h4 class="footer-heading footer_newsletter_heading">Newsletter</h4>
                    <form class="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                        <input type="email" name="email" placeholder="Your email" class="footer-newsletter-input" required>
                        <button type="submit" class="footer-newsletter-button">Subscribe</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <?php else: ?>
        <!- Single Column Layout ->
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
                <h4 class="footer-heading footer_newsletter_heading">Stay Connected</h4>
                <form class="footer-newsletter-form footer_newsletter_form_centered" action="/api/newsletter_signup.php" method="POST">
                    <input type="email" name="email" placeholder="Enter your email address" class="footer-newsletter-input" required>
                    <button type="submit" class="footer-newsletter-button">Subscribe</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!- Social Media Icons (shown for all layouts) ->
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
        
        <!- Copyright Section ->
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

<!-- Newsletter JS moved to Vite module: src/modules/footer-newsletter.js -->