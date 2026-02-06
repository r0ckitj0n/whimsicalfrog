import React from 'react';

interface ProfileFormData {
    email: string;
    first_name: string;
    last_name: string;
    phone_number: string;
    currentPassword?: string;
    newPassword?: string;
}

interface ProfileFormProps {
    formData: ProfileFormData;
    handleInputChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    handleSaveProfile: (e: React.FormEvent) => void;
    isSaving: boolean;
    isEditing: boolean;
}

export const ProfileForm: React.FC<ProfileFormProps> = ({
    formData,
    handleInputChange,
    handleSaveProfile,
    isSaving,
    isEditing
}) => {
    return (
        <section className="space-y-6">
            <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest pb-2 border-b">
                <span className="admin-view btn-icon--user" style={{ fontSize: '14px' }} /> Profile Information
            </div>

            <form onSubmit={handleSaveProfile} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col">
                        <label>First Name</label>
                        <input
                            type="text" name="first_name"
                            value={formData.first_name}
                            onChange={handleInputChange}
                            disabled={!isEditing}
                            className="form-input"
                            autoComplete="given-name"
                        />
                    </div>
                    <div className="flex flex-col">
                        <label>Last Name</label>
                        <input
                            type="text" name="last_name"
                            value={formData.last_name}
                            onChange={handleInputChange}
                            disabled={!isEditing}
                            className="form-input"
                            autoComplete="family-name"
                        />
                    </div>
                </div>

                <div className="flex flex-col">
                    <label>Email Address</label>
                    <input
                        type="email" name="email" required
                        value={formData.email}
                        onChange={handleInputChange}
                        disabled={!isEditing}
                        className="form-input"
                        autoComplete="email"
                    />
                </div>

                <div className="flex flex-col">
                    <label>Phone Number</label>
                    <input
                        type="tel" name="phone_number"
                        value={formData.phone_number}
                        onChange={handleInputChange}
                        disabled={!isEditing}
                        className="form-input"
                    />
                </div>

                <div className="pt-4 space-y-4 border-t border-gray-100">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        <span className="admin-view btn-icon--settings" style={{ fontSize: '12px' }} /> Security
                    </div>
                    <div className="flex flex-col">
                        <label>Current Password</label>
                        <input
                            type="password" name="currentPassword"
                            value={formData.currentPassword}
                            onChange={handleInputChange}
                            disabled={!isEditing}
                            className="form-input"
                            placeholder="Required to change password"
                            autoComplete="current-password"
                        />
                    </div>
                    <div className="flex flex-col">
                        <label>New Password</label>
                        <input
                            type="password" name="newPassword"
                            value={formData.newPassword}
                            onChange={handleInputChange}
                            disabled={!isEditing}
                            className="form-input"
                            placeholder="Leave blank to keep current"
                            autoComplete="new-password"
                        />
                    </div>
                </div>
            </form>
        </section>
    );
};
