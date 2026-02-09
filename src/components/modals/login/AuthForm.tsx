import React from 'react';

interface IAuthFormData {
    username: string;
    password: string;
    email?: string;
    first_name?: string;
    last_name?: string;
    address_line_1?: string;
    address_line_2?: string;
    city?: string;
    state?: string;
    zip_code?: string;
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
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="flex flex-col">
                                <label>First Name</label>
                                <input
                                    type="text"
                                    name="first_name"
                                    value={formData.first_name || ''}
                                    onChange={handleInputChange}
                                    className="form-input"
                                    placeholder=""
                                />
                            </div>
                            <div className="flex flex-col">
                                <label>Last Name</label>
                                <input
                                    type="text"
                                    name="last_name"
                                    value={formData.last_name || ''}
                                    onChange={handleInputChange}
                                    className="form-input"
                                    placeholder=""
                                />
                            </div>
                        </div>

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

                        <div className="flex flex-col">
                            <label>Address Line 1</label>
                            <input
                                type="text"
                                name="address_line_1"
                                value={formData.address_line_1 || ''}
                                onChange={handleInputChange}
                                className="form-input"
                                placeholder=""
                            />
                        </div>

                        <div className="flex flex-col">
                            <label>Address Line 2</label>
                            <input
                                type="text"
                                name="address_line_2"
                                value={formData.address_line_2 || ''}
                                onChange={handleInputChange}
                                className="form-input"
                                placeholder=""
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="flex flex-col">
                                <label>City</label>
                                <input
                                    type="text"
                                    name="city"
                                    value={formData.city || ''}
                                    onChange={handleInputChange}
                                    className="form-input"
                                    placeholder=""
                                />
                            </div>
                            <div className="flex flex-col">
                                <label>State</label>
                                <input
                                    type="text"
                                    name="state"
                                    value={formData.state || ''}
                                    onChange={handleInputChange}
                                    className="form-input"
                                    placeholder=""
                                />
                            </div>
                            <div className="flex flex-col">
                                <label>ZIP</label>
                                <input
                                    type="text"
                                    name="zip_code"
                                    value={formData.zip_code || ''}
                                    onChange={handleInputChange}
                                    className="form-input"
                                    placeholder=""
                                />
                            </div>
                        </div>
                    </>
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
