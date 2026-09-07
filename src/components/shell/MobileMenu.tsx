import { useAuthModal } from '../../hooks/useAuthModal.js';
import { useAuthContext } from '../../context/AuthContext.js';
import { Link } from 'react-router-dom';

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
        <>
            <button
                type="button"
                className="mobile-menu-overlay"
                aria-label="Close menu"
                onClick={onClose}
            />

            <div className={`mobile-menu ${isOpen ? 'show' : ''}`} id="mobile-menu" role="navigation" aria-label="Mobile navigation">
                <div className="mobile-nav-links">
                    <Link to="/" className="mobile-nav-link" onClick={onClose}>Home</Link>
                    <Link to="/room_main" className="mobile-nav-link" onClick={onClose}>Main Room</Link>
                    <Link to="/shop" className="mobile-nav-link" onClick={onClose}>Shop</Link>
                    <Link to="/about" className="mobile-nav-link" onClick={onClose}>About</Link>
                    <Link to="/contact" className="mobile-nav-link" onClick={onClose}>Contact</Link>

                    <div className="mobile-auth-section">
                        {isLoggedIn ? (
                            <>
                                <a href="#" className="mobile-nav-link" onClick={handleAction(openAccountSettings)}>
                                    {username || 'User'}
                                </a>
                                {isAdmin && (
                                    <Link to="/admin?section=settings" className="mobile-nav-link" onClick={onClose}>
                                        Settings
                                    </Link>
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
        </>
    );
};
