import React from 'react';
import { IAccountSettingsFormData } from '../../../types/account.js';

interface CustomerProfilePreferencesFormProps {
    formData: IAccountSettingsFormData;
    handleInputChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => void;
    handleMarketingOptInChange: (checked: boolean) => void;
    isEditing: boolean;
    isSaving: boolean;
}

export const CustomerProfilePreferencesForm: React.FC<CustomerProfilePreferencesFormProps> = ({
    formData,
    handleInputChange,
    handleMarketingOptInChange,
    isEditing,
    isSaving
}) => {
    return (
        <section className="space-y-6">
            <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest pb-2 border-b">
                <span className="admin-view btn-icon--user" style={{ fontSize: '14px' }} /> Customer Profile
            </div>

            <div className="space-y-4">
                <div className="flex flex-col">
                    <label>Company</label>
                    <input
                        type="text"
                        name="company"
                        value={formData.company}
                        onChange={handleInputChange}
                        disabled={!isEditing || isSaving}
                        className="form-input"
                    />
                </div>

                <div className="flex flex-col">
                    <label>Job Title</label>
                    <input
                        type="text"
                        name="job_title"
                        value={formData.job_title}
                        onChange={handleInputChange}
                        disabled={!isEditing || isSaving}
                        className="form-input"
                    />
                </div>
            </div>

            <div className="pt-4 space-y-4 border-t border-gray-100">
                <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <span className="admin-view btn-icon--settings" style={{ fontSize: '12px' }} /> Preferences
                </div>

                <div className="flex flex-col">
                    <label>Preferred Contact</label>
                    <select
                        name="preferred_contact"
                        value={formData.preferred_contact}
                        onChange={handleInputChange}
                        disabled={!isEditing || isSaving}
                        className="form-input"
                    >
                        <option value="">-</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>

                <div className="flex flex-col">
                    <label>Preferred Language</label>
                    <input
                        type="text"
                        name="preferred_language"
                        value={formData.preferred_language}
                        onChange={handleInputChange}
                        disabled={!isEditing || isSaving}
                        className="form-input"
                    />
                </div>

                {!isEditing ? (
                    <div className="flex items-center gap-2 pt-1">
                        <span className="text-sm text-gray-700 font-semibold">Marketing Opt-in:</span>
                        <span className="text-sm text-gray-900">{formData.marketing_opt_in ? 'Yes' : 'No'}</span>
                    </div>
                ) : (
                    <label className="inline-flex items-center gap-2 pt-1 cursor-pointer">
                        <input
                            type="checkbox"
                            name="marketing_opt_in"
                            checked={formData.marketing_opt_in}
                            onChange={(e) => handleMarketingOptInChange(e.target.checked)}
                            disabled={isSaving}
                            className="rounded"
                            style={{ accentColor: 'var(--brand-primary)' }}
                        />
                        <span className="text-sm text-gray-700 font-semibold">Marketing Opt-in</span>
                    </label>
                )}
            </div>
        </section>
    );
};
