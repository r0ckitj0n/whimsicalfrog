import { useAuthModal } from '../../hooks/useAuthModal.js';
import { useAuthContext } from '../../context/AuthContext.js';

interface MobileMenuProps {
    isOpen: boolean;
    isLoggedIn: boolean;
    isAdmin: boolean;
    username?: string;
    onClose: () => void;
}

export const MobileMenu: React.FC<MobileMenuProps> = ({
    isOpen,
    isLoggedIn,
    isAdmin: _propIsAdmin, // Keep in props but ignore to avoid breaking changes if needed elsewhere
    username,
    onClose
}) => {
    const { openLogin, openRegister, openAccountSettings } = useAuthModal();
    const { logout, isAdmin } = useAuthContext();

    if (!isOpen) return null;

    const handleAction = (fn: () => void) => (e: React.MouseEvent) => {
        e.preventDefault();
        fn();
        onClose();
    };

    return (
        <div className={`mobile-menu ${isOpen ? 'show' : ''}`} id="mobile-menu" role="navigation" aria-label="Mobile navigation">
            <div className="mobile-nav-links">
                <a href="/" className="mobile-nav-link" onClick={onClose}>Home</a>
                <a href="/shop" className="mobile-nav-link" onClick={onClose}>Shop</a>
                <a href="/about" className="mobile-nav-link" onClick={onClose}>About</a>
                <a href="/contact" className="mobile-nav-link" onClick={onClose}>Contact</a>

                <div className="mobile-auth-section">
                    {isLoggedIn ? (
                        <>
                            <a href="#" className="mobile-nav-link" onClick={handleAction(openAccountSettings)}>
                                {username || 'User'}
                            </a>
                            {isAdmin && (
                                <a href="/admin?section=settings" className="mobile-nav-link" onClick={onClose}>
                                    Settings
                                </a>
                            )}
                            <a href="#" className="mobile-nav-link" onClick={handleAction(logout)}>Logout</a>
                        </>
                    ) : (
                        <>
                            <a href="/login" className="mobile-nav-link" onClick={handleAction(openLogin)}>Login</a>
                            <a href="/login" className="mobile-nav-link" onClick={handleAction(() => openRegister())}>Register</a>
                        </>
                    )}
                </div>
            </div>

            <div className="mobile-search">
                <form action="/shop" method="GET" role="search">
                    <input
                        type="search"
                        name="q"
                        className="search-bar"
                        placeholder="Search"
                        aria-label="Search"
                    />
                </form>
            </div>
        </div>
    );
};
