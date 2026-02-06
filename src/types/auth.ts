export interface IUserProfile {
    id: string | number;
    username: string;
    email: string;
    role: string;
    first_name?: string;
    last_name?: string;
    phone_number?: string;
}

export interface IAuthState {
    user: IUserProfile | null;
    isLoggedIn: boolean;
    isAdmin: boolean;
    isLoading: boolean;
}

// API Response Types
export interface IWhoAmIResponse {
    user_id: string | number;
    username?: string;
    email?: string;
    phone_number?: string;
    role?: string;
    first_name: string;
    last_name: string;
}

export interface ILoginResponse {
    success: boolean;
    error?: string;
}

export interface IRegisterData {
    username: string;
    password: string;
    email: string;
    first_name: string;
    last_name: string;
}

export interface ISecret {
    key: string;
    created_at?: string;
    updated_at?: string;
}
