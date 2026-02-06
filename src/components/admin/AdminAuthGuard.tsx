import React, { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthContext } from '../../context/AuthContext.js';
import { useAuthModal } from '../../hooks/useAuthModal.js';

interface AdminAuthGuardProps {
    children: React.ReactNode;
}

/**
 * Guard component that protects admin routes.
 * - Shows loading spinner while checking auth
 * - Opens login modal if not authenticated
 * - Redirects to home if authenticated but not admin
 * - Renders children if authenticated admin
 */
export const AdminAuthGuard: React.FC<AdminAuthGuardProps> = ({ children }) => {
    const { isLoggedIn, isAdmin, isLoading } = useAuthContext();
    const { openLogin, mode } = useAuthModal();
    const navigate = useNavigate();
    const hasTriggeredLogin = useRef(false);

    useEffect(() => {
        // Wait for auth state to load
        if (isLoading) return;

        // If not logged in and login modal not already open, prompt login
        if (!isLoggedIn && mode === 'none' && !hasTriggeredLogin.current) {
            hasTriggeredLogin.current = true;
            openLogin('/admin');
            return;
        }

        // If logged in but not admin, redirect to home
        if (isLoggedIn && !isAdmin) {
            navigate('/', { replace: true });
        }
    }, [isLoading, isLoggedIn, isAdmin, mode, openLogin, navigate]);

    // Show loading state
    if (isLoading) {
        return (
            <div className="wf-admin-loading">
                <div className="wf-spinner"></div>
                <p>Checking authentication...</p>
            </div>
        );
    }

    // If not logged in, render nothing (login modal is shown)
    if (!isLoggedIn) {
        return null;
    }

    // If logged in but not admin, render nothing (redirecting)
    if (!isAdmin) {
        return null;
    }

    // Authenticated admin - render children
    return <>{children}</>;
};

export default AdminAuthGuard;
