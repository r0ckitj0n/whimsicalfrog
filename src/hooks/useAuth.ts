import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../core/ApiClient.js';
import { IAuthState, IUserProfile, IWhoAmIResponse, ILoginResponse } from '../types/auth.js';
import logger from '../core/logger.js';

import { ROLE } from '../core/constants.js';

export const useAuth = () => {
    const [state, setState] = useState<IAuthState>({
        user: null,
        isLoggedIn: false,
        isAdmin: false,
        isLoading: true
    });

    const fetchWhoAmI = useCallback(async () => {
        try {
            const res = await ApiClient.get<IWhoAmIResponse>('/api/whoami.php');
            if (res && res.user_id) {
                const user: IUserProfile = {
                    id: res.user_id,
                    username: res.username || '',
                    email: res.email || '',
                    role: res.role || ROLE.CUSTOMER,
                    first_name: res.first_name,
                    last_name: res.last_name,
                    phone_number: res.phone_number
                };
                setState({
                    user,
                    isLoggedIn: true,
                    isAdmin: user.role.toLowerCase() === ROLE.ADMIN,
                    isLoading: false
                });
                return user;
            }
        } catch (err) {
            logger.error('[useAuth] fetchWhoAmI failed', err);
        }
        setState(prev => ({ ...prev, isLoading: false }));
        return null;
    }, []);

    useEffect(() => {
        fetchWhoAmI();
    }, [fetchWhoAmI]);

    const login = async (username: string, password: string): Promise<{ success: boolean; error?: string }> => {
        setState(prev => ({ ...prev, isLoading: true }));
        try {
            const res = await ApiClient.post<ILoginResponse>('/functions/process_login.php', { username, password });
            if (res && res.success) {
                await fetchWhoAmI();
                return { success: true };
            }
            return { success: false, error: res?.error || 'Invalid credentials' };
        } catch (err) {
            logger.error('[useAuth] login failed', err);
            return { success: false, error: 'Network error during login' };
        } finally {
            setState(prev => ({ ...prev, isLoading: false }));
        }
    };

    const logout = async () => {
        try {
            await ApiClient.get('/api/logout.php');

            // Client-side cookie deletion as a safety net
            // Delete all auth cookies for all possible domain variations
            const cookieNames = ['WF_AUTH', 'WF_AUTH_V', 'PHPSESSID'];
            const hostname = window.location.hostname;

            // Calculate possible domain variations
            const domains = ['', hostname];
            if (hostname !== 'localhost' && !hostname.match(/^\d+\.\d+\.\d+\.\d+$/)) {
                const parts = hostname.split('.');
                if (parts.length >= 2) {
                    domains.push('.' + parts.slice(-2).join('.'));
                }
                domains.push('.' + hostname);
            }

            // Delete each cookie for each domain variation
            cookieNames.forEach(name => {
                domains.forEach(domain => {
                    const domainAttr = domain ? `; domain=${domain}` : '';
                    // Set cookie to empty with past expiration
                    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${domainAttr}`;
                });
            });

            setState({
                user: null,
                isLoggedIn: false,
                isAdmin: false,
                isLoading: false
            });
        } catch (err) {
            logger.error('[useAuth] logout failed', err);
        }
    };

    const register = async (payload: Record<string, unknown>): Promise<{ success: boolean; error?: string }> => {
        setState(prev => ({ ...prev, isLoading: true }));
        try {
            const res = await ApiClient.post<ILoginResponse>('/functions/process_register.php', payload);
            if (res && res.success) {
                return { success: true };
            }
            return { success: false, error: res?.error || 'Registration failed' };
        } catch (err) {
            logger.error('[useAuth] register failed', err);
            return { success: false, error: 'Network error during registration' };
        } finally {
            setState(prev => ({ ...prev, isLoading: false }));
        }
    };

    return {
        ...state,
        login,
        logout,
        register,
        refresh: fetchWhoAmI
    };
};
