import React, { createContext, useContext, ReactNode, useMemo } from 'react';
import { useAuth } from '../hooks/useAuth.js';
import { IUserProfile, ILoginResult } from '../types/auth.js';

interface AuthContextType {
    user: IUserProfile | null;
    isLoggedIn: boolean;
    isAdmin: boolean;
    isLoading: boolean;
    login: (username: string, password: string) => Promise<ILoginResult>;
    logout: () => Promise<void>;
    register: (payload: Record<string, unknown>) => Promise<{ success: boolean; error?: string }>;
    refresh: () => Promise<IUserProfile | null>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);


export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
    const authHandlers = useAuth();

    const value = useMemo(() => ({
        ...authHandlers
    }), [authHandlers]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuthContext = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuthContext must be used within an AuthProvider');
    }
    return context;
};
