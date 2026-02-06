import React from 'react';
import type { ISocialProvider, AuthMethodConfig } from '../../../../../hooks/admin/useSocialMedia.js';

interface CredentialsFormProps {
    selectedProvider: ISocialProvider;
    selectedAuthMethod: AuthMethodConfig;
    displayName: string;
    setDisplayName: (val: string) => void;
    credentials: Record<string, string>;
    setCredentials: (creds: Record<string, string>) => void;
    localLoading: boolean;
    onCredentialSubmit: (e: React.FormEvent) => void;
    onBack: () => void;
}

export const CredentialsForm: React.FC<CredentialsFormProps> = ({
    selectedProvider,
    selectedAuthMethod,
    displayName,
    setDisplayName,
    credentials,
    setCredentials,
    localLoading,
    onCredentialSubmit,
    onBack
}) => {
    return (
        <div className="max-w-lg mx-auto py-8 animate-in fade-in slide-in-from-bottom-2">
            <div className="flex items-center gap-4 mb-8 pb-6 border-b">
                <div
                    className="w-14 h-14 rounded-xl flex items-center justify-center text-2xl"
                    style={{ backgroundColor: `${selectedProvider.color}15` }}
                >
                    {selectedProvider.icon}
                </div>
                <div>
                    <div className="text-lg font-bold text-gray-900">{selectedProvider.name}</div>
                    <div className="text-xs text-gray-500">{selectedAuthMethod.label}</div>
                </div>
            </div>

            <form onSubmit={onCredentialSubmit} className="space-y-5">
                {/* Display Name */}
                <div className="space-y-1">
                    <label className="text-xs font-bold text-gray-600 block">Display Name</label>
                    <input
                        type="text"
                        required
                        value={displayName}
                        onChange={e => setDisplayName(e.target.value)}
                        className="form-input w-full py-3"
                        placeholder="A friendly name for this account"
                    />
                </div>

                {/* Dynamic fields from auth method */}
                {selectedAuthMethod.fields.map((field) => (
                    <div key={field.name} className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">
                            {field.label}
                            {field.required && <span className="text-red-500 ml-1">*</span>}
                        </label>
                        <input
                            type={field.type}
                            required={field.required}
                            value={credentials[field.name] || ''}
                            onChange={e => setCredentials({ ...credentials, [field.name]: e.target.value })}
                            className="form-input w-full py-3 font-mono text-sm"
                            placeholder={field.placeholder}
                        />
                    </div>
                ))}

                <div className="pt-4 flex gap-3">
                    <button
                        type="button"
                        onClick={onBack}
                        disabled={localLoading}
                        className="flex-1 py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors"
                    >
                        Back
                    </button>
                    <button
                        type="submit"
                        disabled={localLoading}
                        className="flex-1 py-3 px-6 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-hover)] text-white font-bold rounded-xl transition-colors flex items-center justify-center gap-2"
                    >
                        {localLoading ? (
                            <>
                                <span className="wf-emoji-loader">🔄</span>
                                Connecting...
                            </>
                        ) : (
                            'Connect Account'
                        )}
                    </button>
                </div>
            </form>

            <div className="mt-8 p-4 bg-gray-50 border border-gray-100 rounded-xl">
                <div className="flex items-start gap-3">
                    <span className="text-xl">🛡️</span>
                    <div className="text-xs text-gray-500 leading-relaxed">
                        <strong className="text-gray-700">Security Note:</strong> Your credentials are encrypted and stored securely. We never share your API keys with third parties.
                    </div>
                </div>
            </div>
        </div>
    );
};
