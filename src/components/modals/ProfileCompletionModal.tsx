import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useAuthContext } from '../../context/AuthContext.js';
import { useAuthModal } from '../../hooks/useAuthModal.js';
import { useApp } from '../../context/AppContext.js';
import { useNotificationContext } from '../../context/NotificationContext.js';
import { ApiClient } from '../../core/ApiClient.js';
import { buildPostAuthRedirectPlan } from '../../core/auth-redirect.js';
import { ICompleteProfileRequest, ICompleteProfileResponse } from '../../types/auth.js';

interface ProfileCompletionModalProps {
    isOpen: boolean;
}

export const ProfileCompletionModal: React.FC<ProfileCompletionModalProps> = ({ isOpen }) => {
    const { user, refresh } = useAuthContext();
    const { returnTo, close } = useAuthModal();
    const { setIsCartOpen } = useApp();
    const { success: showSuccess, error: showError } = useNotificationContext();
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [formData, setFormData] = useState<ICompleteProfileRequest>({
        first_name: user?.first_name || '',
        last_name: user?.last_name || '',
        email: user?.email || '',
        phone_number: user?.phone_number || '',
        address_line_1: user?.address_line_1 || '',
        address_line_2: user?.address_line_2 || '',
        city: user?.city || '',
        state: user?.state || '',
        zip_code: user?.zip_code || ''
    });

    useEffect(() => {
        if (!isOpen || !user) return;
        setFormData({
            first_name: user.first_name || '',
            last_name: user.last_name || '',
            email: user.email || '',
            phone_number: user.phone_number || '',
            address_line_1: user.address_line_1 || '',
            address_line_2: user.address_line_2 || '',
            city: user.city || '',
            state: user.state || '',
            zip_code: user.zip_code || ''
        });
        setError(null);
    }, [isOpen, user]);

    const missingFieldLabel = useMemo(() => {
        if (!formData.first_name.trim()) return 'first name';
        if (!formData.last_name.trim()) return 'last name';
        if (!formData.email.trim()) return 'email';
        if (!formData.address_line_1.trim()) return 'address';
        if (!formData.city.trim()) return 'city';
        if (!formData.state.trim()) return 'state';
        if (!formData.zip_code.trim()) return 'ZIP code';
        return null;
    }, [formData]);

    if (!isOpen || !user) return null;

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setError(null);
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSaving) return;

        if (missingFieldLabel) {
            const message = `Please enter your ${missingFieldLabel}.`;
            setError(message);
            showError(message, { duration: 4000 });
            return;
        }

        setIsSaving(true);
        setError(null);

        try {
            const res = await ApiClient.post<ICompleteProfileResponse>('/api/complete_profile.php', formData);
            if (!res?.success) {
                throw new Error(res?.error || 'Unable to complete your profile right now.');
            }

            await refresh();
            showSuccess('Profile completed. Welcome to the store!', { duration: 4500 });
            close();

            const plan = buildPostAuthRedirectPlan(returnTo);
            if (plan.openCart) {
                setIsCartOpen(true);
            } else if (plan.redirectPath) {
                const redirectPath = plan.redirectPath;
                setTimeout(() => {
                    window.location.href = redirectPath;
                }, 250);
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unable to complete your profile right now.';
            setError(message);
            showError(message, { duration: 5000 });
        } finally {
            setIsSaving(false);
        }
    };

    return createPortal(
        <div
            className="wf-modal-overlay show"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 'calc(var(--wf-z-modal) + 120)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.86)',
                backdropFilter: 'blur(6px)',
                padding: '20px'
            }}
        >
            <div
                className="wf-modal-card animate-in zoom-in-95 slide-in-from-bottom-4 duration-300"
                style={{
                    width: '100%',
                    maxWidth: '860px',
                    maxHeight: 'calc(100vh - 40px)',
                    backgroundColor: '#ffffff',
                    borderRadius: '20px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.55)',
                    overflow: 'hidden',
                    display: 'flex',
                    flexDirection: 'column'
                }}
            >
                <div
                    style={{
                        padding: '1.25rem 1.5rem',
                        background: 'var(--bg-gradient-brand, linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%))',
                        color: '#ffffff'
                    }}
                >
                    <h2
                        style={{
                            margin: 0,
                            fontFamily: "'Merienda', cursive",
                            fontSize: '1.45rem',
                            fontWeight: 800
                        }}
                    >
                        Welcome to Whimsical Frog
                    </h2>
                    <p style={{ margin: '0.45rem 0 0 0', fontSize: '0.95rem', fontWeight: 700, opacity: 0.95 }}>
                        Please complete your profile before continuing.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="flex-1 min-h-0 p-6 overflow-y-auto">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">First Name</label>
                            <input type="text" name="first_name" className="form-input" value={formData.first_name} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">Last Name</label>
                            <input type="text" name="last_name" className="form-input" value={formData.last_name} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">Email</label>
                            <input type="email" name="email" className="form-input" value={formData.email} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">Phone Number</label>
                            <input type="tel" name="phone_number" className="form-input" value={formData.phone_number || ''} onChange={handleChange} />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">Address 1</label>
                            <input type="text" name="address_line_1" className="form-input" value={formData.address_line_1} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">Address 2</label>
                            <input type="text" name="address_line_2" className="form-input" value={formData.address_line_2 || ''} onChange={handleChange} />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">City</label>
                            <input type="text" name="city" className="form-input" value={formData.city || ''} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">State</label>
                            <input type="text" name="state" className="form-input" value={formData.state || ''} onChange={handleChange} required />
                        </div>
                        <div className="flex flex-col">
                            <label className="text-sm font-bold text-gray-700">ZIP</label>
                            <input type="text" name="zip_code" className="form-input" value={formData.zip_code || ''} onChange={handleChange} required />
                        </div>

                        {error && (
                            <div className="rounded-xl bg-rose-50 text-rose-700 px-3 py-2 text-sm font-semibold md:col-span-2">
                                {error}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={isSaving}
                            className="btn btn-primary w-full py-4 uppercase tracking-wider font-black md:col-span-2"
                        >
                            {isSaving ? 'Saving...' : 'Save and Continue'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
};

export default ProfileCompletionModal;
