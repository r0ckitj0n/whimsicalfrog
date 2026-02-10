export interface IUserProfile {
    id: string | number;
    username: string;
    email: string;
    role: string;
    first_name?: string;
    last_name?: string;
    phone_number?: string;
    address_line_1?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    company?: string;
    job_title?: string;
    preferred_contact?: string;
    preferred_language?: string;
    marketing_opt_in?: string | number | boolean;
    profile_completion_required?: boolean;
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
    address_line_1?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    company?: string;
    job_title?: string;
    preferred_contact?: string;
    preferred_language?: string;
    marketing_opt_in?: string | number | boolean;
    profile_completion_required?: boolean;
}

export interface ILoginResponse {
    success: boolean;
    error?: string;
    profile_completion_required?: boolean;
}

export interface ILoginResult {
    success: boolean;
    error?: string;
    profile_completion_required?: boolean;
}

export interface IRegisterData {
    username: string;
    password: string;
    email: string;
    first_name: string;
    last_name: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    zip_code: string;
    phone_number?: string;
}

export interface ICompleteProfileRequest {
    first_name: string;
    last_name: string;
    email: string;
    phone_number?: string;
    address_line_1: string;
    city: string;
    state: string;
    zip_code: string;
}

export interface ICompleteProfileResponse {
    success: boolean;
    error?: string;
    message?: string;
}

export interface ISecret {
    key: string;
    created_at?: string;
    updated_at?: string;
}
