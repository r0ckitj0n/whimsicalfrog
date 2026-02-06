import React from 'react';

interface IAuthFormData {
    username: string;
    password: string;
    email?: string;
}

interface AuthFormProps {
    mode: 'login' | 'register';
    formData: IAuthFormData;
    isLoading: boolean;
    error: string | null;
    success: string | null;
    handleInputChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    handleSubmit: (e: React.FormEvent) => void;
}

export const AuthForm: React.FC<AuthFormProps> = ({
    mode,
    formData,
    isLoading,
    error,
    success,
    handleInputChange,
    handleSubmit
}) => {
    return (
        <div className="flex-1 overflow-y-auto p-6">
            <form onSubmit={handleSubmit} className="wf-login-form flex flex-col gap-6">
                <div className="flex flex-col">
                    <label>Username</label>
                    <input
                        type="text" name="username" required
                        value={formData.username}
                        onChange={handleInputChange}
                        className="form-input"
                        placeholder=""
                    />
                </div>

                {mode === 'register' && (
                    <div className="flex flex-col">
                        <label>Email</label>
                        <input
                            type="email" name="email" required
                            value={formData.email}
                            onChange={handleInputChange}
                            className="form-input"
                            placeholder=""
                        />
                    </div>
                )}

                <div className="flex flex-col">
                    <label>Password</label>
                    <input
                        type="password" name="password" required
                        value={formData.password}
                        onChange={handleInputChange}
                        className="form-input"
                        placeholder=""
                        autoComplete="current-password"
                    />
                </div>

                <button
                    type="submit"
                    disabled={isLoading}
                    className="btn btn-primary w-full py-4 flex items-center justify-center gap-2 uppercase tracking-widest font-black"
                >
                    {isLoading ? (
                        <span className="wf-emoji-loader" style={{ fontSize: '16px' }}>⏳</span>
                    ) : mode === 'login' ? (
                        <span className="btn-icon--user" style={{ fontSize: '16px' }} aria-hidden="true" />
                    ) : (
                        <span className="btn-icon--add" style={{ fontSize: '16px' }} aria-hidden="true" />
                    )}
                    <span>{mode === 'login' ? "Let's Go" : 'Create Account'}</span>
                </button>
            </form>

            {error && (
                <div className="wf-login-feedback is-error animate-in slide-in-from-top-2 mt-4" style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '12px', background: '#fff1f2', color: '#e11d48', borderRadius: '12px', fontSize: '14px', fontWeight: 700 }}>
                    <span className="btn-icon--warning flex-shrink-0" style={{ fontSize: '16px' }} aria-hidden="true" />
                    <span>{error}</span>
                </div>
            )}

            {success && (
                <div className="wf-login-feedback is-success animate-in slide-in-from-top-2 mt-4" style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '12px', background: '#f0fdf4', color: '#16a34a', borderRadius: '12px', fontSize: '14px', fontWeight: 700 }}>
                    <span className="btn-icon--check" style={{ fontSize: '16px' }} aria-hidden="true" />
                    <span>{success}</span>
                </div>
            )}
        </div>
    );
};
