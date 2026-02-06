import React, { useState } from 'react';
import { useAuthContext } from '../../context/AuthContext.js';
import { useCart } from '../../hooks/use-cart.js';
import { Logo } from './Logo.js';
import { Navigation } from './Navigation.js';
import { SearchBar } from './SearchBar.js';
import { UserCart } from './UserCart.js';
import { MobileMenu } from './MobileMenu.js';
import { useHeader } from '../../hooks/useHeader.js';
import { PAGE, ISiteSettings } from '../../types/index.js';

interface HeaderProps {
    settings: ISiteSettings | null;
}

/**
 * Site Header Component
 * Full React replacement for header_template.php.
 */
export const Header: React.FC<HeaderProps> = ({ settings }) => {
    const { isLoggedIn, user, isAdmin } = useAuthContext();
    const { count, total } = useCart();
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    // useHeader handles CSS variables and scroll effects
    const { headerHeight } = useHeader();

    const siteName = settings?.name || "Whimsical Frog";
    const siteTagline = settings?.tagline;
    const logoImage = settings?.logo || "/images/logos/logo-whimsicalfrog.webp";

    // Replicate PHP logic: Hide header on landing page for guest users
    const page = document.body.getAttribute('data-page');
    const isLanding = page === PAGE.LANDING || window.location.pathname === '/';
    if (isLanding && !isLoggedIn) {
        return null;
    }

    return (
        <header className="site-header universal-page-header" role="banner">
            <div className="header-container">
                <div className="header-content">
                    {/* Left Section: Logo and Navigation */}
                    <div className="header-left">
                        <Logo
                            siteName={siteName}
                            siteTagline={siteTagline}
                            logoImage={logoImage}
                            onClick={() => setIsMenuOpen(!isMenuOpen)}
                        />
                        <Navigation />
                    </div>

                    {/* Middle Section: Responsive Search Bar */}
                    <SearchBar />

                    {/* Right Section: User Menu, Cart */}
                    <UserCart
                        isLoggedIn={isLoggedIn}
                        username={user?.username}
                        isAdmin={isAdmin}
                        cartCount={count}
                        cartTotal={total}
                        onToggleMenu={() => setIsMenuOpen(!isMenuOpen)}
                        isMenuOpen={isMenuOpen}
                    />
                </div>

                {/* Mobile Menu */}
                <MobileMenu
                    isOpen={isMenuOpen}
                    isLoggedIn={isLoggedIn}
                    isAdmin={isAdmin}
                    username={user?.username}
                    onClose={() => setIsMenuOpen(false)}
                />
            </div>
        </header>
    );
};
