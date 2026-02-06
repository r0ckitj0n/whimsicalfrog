import React from 'react';
import { ISiteSettings } from '../../types/index.js';

interface FooterProps {
    settings: ISiteSettings | null;
    isSlim?: boolean;
}

export const Footer: React.FC<FooterProps> = ({ settings, isSlim }) => {
    if (!settings) return null;

    const currentYear = new Date().getFullYear();

    return (
        <footer className={`site-footer footer-theme-dark ${isSlim ? 'is-slim' : ''}`}>
            <div className="footer-container">
                {!isSlim && (
                    <div className="footer-grid-4">
                        {/* Column 1: Company Info */}
                        <div className="footer-section">
                            <div className="footer-logo">
                                <img src={settings.logo} alt={`${settings.name} Logo`} />
                            </div>
                            <h3 className="footer-heading">{settings.name}</h3>
                            <p className="footer-tagline">{settings.tagline}</p>
                            <p className="footer-text">
                                Creating unique, personalized items that bring joy and whimsy to your world.
                            </p>
                        </div>

                        {/* Column 2: Quick Links */}
                        <div className="footer-section">
                            <h3 className="footer-heading">Quick Links</h3>
                            <ul className="footer-nav">
                                <li><a href="/shop">Shop</a></li>
                                <li><a href="/about">About Us</a></li>
                                <li><a href="/contact">Contact</a></li>
                                <li><a href="/custom">Custom Orders</a></li>
                            </ul>
                        </div>

                        {/* Column 3: Support */}
                        <div className="footer-section">
                            <h3 className="footer-heading">Support</h3>
                            <ul className="footer-nav">
                                <li><a href="/privacy">Privacy Policy</a></li>
                                <li><a href="/terms">Terms of Service</a></li>
                                <li><a href="/shipping">Shipping Info</a></li>
                                <li><a href="/returns">Returns</a></li>
                            </ul>
                        </div>

                        {/* Column 4: Contact & Newsletter */}
                        <div className="footer-section">
                            <h3 className="footer-heading">Contact Us</h3>
                            <div className="footer-contact">
                                <div className="footer-contact-item">
                                    <span className="footer-contact-icon">✉️</span>
                                    <a href={`mailto:${settings.email}`} className="footer-contact-link">
                                        {settings.email}
                                    </a>
                                </div>
                            </div>

                            <div className="footer-newsletter">
                                <h4 className="footer-heading" style={{ marginTop: '20px' }}>Newsletter</h4>
                                <p className="footer-text">Get updates on new items and special offers!</p>
                                <form className="footer-newsletter-form" action="/api/newsletter_signup.php" method="POST">
                                    <input
                                        type="email"
                                        name="email"
                                        placeholder="Your email address"
                                        className="footer-newsletter-input"
                                        required
                                    />
                                    <button type="submit" className="footer-newsletter-button">Subscribe</button>
                                </form>
                            </div>
                        </div>
                    </div>
                )}

                {/* Social Icons (Hidden in Slim Mode) */}
                {!isSlim && (
                    <div className="footer-social">
                        {settings.social.facebook && (
                            <a href={settings.social.facebook} className="footer-social-icon" aria-label="Facebook">FB</a>
                        )}
                        {settings.social.instagram && (
                            <a href={settings.social.instagram} className="footer-social-icon" aria-label="Instagram">IG</a>
                        )}
                        {settings.social.twitter && (
                            <a href={settings.social.twitter} className="footer-social-icon" aria-label="Twitter">TW</a>
                        )}
                        {settings.social.pinterest && (
                            <a href={settings.social.pinterest} className="footer-social-icon" aria-label="Pinterest">PN</a>
                        )}
                    </div>
                )}

                {/* Copyright */}
                <div className="footer-copyright">
                    <p className="footer-copyright-text">
                        © {currentYear} {settings.name}. All rights reserved.
                    </p>
                    <div className="footer-copyright-links">
                        <a href="/privacy">Privacy Policy</a>
                        <a href="/terms">Terms of Service</a>
                        <a href="/sitemap">Sitemap</a>
                    </div>
                </div>
            </div>
        </footer>
    );
};
