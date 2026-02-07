import React, { useEffect, useState } from 'react';
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
    const [isCompactHeader, setIsCompactHeader] = useState(() =>
        typeof window !== 'undefined' ? window.matchMedia('(max-width: 1000px)').matches : false
    );

    // useHeader handles CSS variables and scroll effects
    const { headerHeight } = useHeader();

    const siteName = settings?.name || "Whimsical Frog";
    const siteTagline = settings?.tagline;
    const logoImage = settings?.logo || "/images/logos/logo-whimsicalfrog.webp";

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const mediaQuery = window.matchMedia('(max-width: 1000px)');

        const handleMediaChange = () => {
            setIsCompactHeader(mediaQuery.matches);
            if (!mediaQuery.matches) {
                setIsMenuOpen(false);
            }
        };

        handleMediaChange();

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleMediaChange);
            return () => mediaQuery.removeEventListener('change', handleMediaChange);
        }

        mediaQuery.addListener(handleMediaChange);
        return () => mediaQuery.removeListener(handleMediaChange);
    }, []);

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
                            imageHref="/"
                            titleHref="/room_main"
                            isTitleMenuButton={isCompactHeader}
                            onTitleClick={isCompactHeader ? () => setIsMenuOpen((prev) => !prev) : undefined}
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
