import { useAuthModal } from '../../hooks/useAuthModal.js';
import { useAuthContext } from '../../context/AuthContext.js';

interface UserCartProps {
    isLoggedIn: boolean;
    username?: string;
    isAdmin: boolean;
    cartCount: number;
    cartTotal: number;
    onToggleMenu: () => void;
    isMenuOpen: boolean;
}

export const UserCart: React.FC<UserCartProps> = ({
    isLoggedIn,
    username,
    isAdmin,
    cartCount,
    cartTotal,
    onToggleMenu,
    isMenuOpen
}) => {
    const { openLogin, openAccountSettings } = useAuthModal();
    const { logout } = useAuthContext();

    const handleLoginClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
        e.preventDefault();
        const rect = e.currentTarget.getBoundingClientRect();
        openLogin(undefined, rect);
    };

    const handleAccountClick = (e: React.MouseEvent) => {
        e.preventDefault();
        openAccountSettings();
    };

    const handleLogoutClick = (e: React.MouseEvent) => {
        e.preventDefault();
        logout();
    };

    return (
        <div className="header-right">
            {/* User Menu */}
            <div className="auth-links flex items-center gap-6">
                {isLoggedIn ? (
                    <div className="flex items-center gap-6">
                        <a
                            href="#"
                            className="font-title-primary text-lg text-[var(--brand-primary)] hover:opacity-80 transition-opacity lowercase"
                            onClick={handleAccountClick}
                        >
                            {username || 'User'}
                        </a>
                        {isAdmin && (
                            <a
                                href="/admin?section=settings"
                                className="font-title-primary text-lg text-[var(--brand-primary)] hover:opacity-80 transition-opacity capitalize"
                            >
                                Settings
                            </a>
                        )}
                        <a
                            href="#"
                            className="font-title-primary text-lg text-[var(--brand-primary)] hover:opacity-80 transition-opacity"
                            onClick={handleLogoutClick}
                        >
                            Logout
                        </a>
                    </div>
                ) : (
                    <a
                        href="/login"
                        className="font-title-primary text-lg text-[var(--brand-primary)] hover:opacity-80 transition-opacity"
                        onClick={handleLoginClick}
                    >
                        Login
                    </a>
                )}
            </div>

            {/* Cart Link */}
            <a href="/cart" className="cart-link" aria-label={`Shopping cart with ${cartCount} items`}>
                <div className="flex items-center gap-2">
                    <span className="cart-item-count font-title-primary text-lg text-[var(--brand-primary)] whitespace-nowrap">
                        {cartCount} items
                    </span>
                    <span className="admin-action-btn btn-icon--shopping-cart" style={{ width: '32px', height: '32px', fontSize: '24px' }} />
                </div>
            </a>

            {/* Mobile Menu Toggle */}
            <button
                className="mobile-menu-toggle"
                aria-label="Toggle mobile menu"
                aria-expanded={isMenuOpen}
                onClick={onToggleMenu}
            >
                <span className="admin-action-btn btn-icon--settings" style={{ width: '24px', height: '24px', fontSize: '20px' }} />
            </button>
        </div>
    );
};
