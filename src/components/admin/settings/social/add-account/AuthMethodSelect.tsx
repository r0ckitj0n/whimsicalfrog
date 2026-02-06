import React from 'react';
import type { ISocialProvider, AuthMethodConfig } from '../../../../../hooks/admin/useSocialMedia.js';

interface AuthMethodSelectProps {
    selectedProvider: ISocialProvider;
    onAuthMethodSelect: (method: AuthMethodConfig) => void;
    onBack: () => void;
}

export const AuthMethodSelect: React.FC<AuthMethodSelectProps> = ({
    selectedProvider,
    onAuthMethodSelect,
    onBack
}) => {
    return (
        <div className="max-w-lg mx-auto py-8 flex flex-col items-center text-center animate-in fade-in slide-in-from-bottom-2">
            <div
                className="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6"
                style={{ backgroundColor: `${selectedProvider.color}15` }}
            >
                {selectedProvider.icon}
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Connect {selectedProvider.name}</h3>
            <p className="text-gray-500 text-sm mb-8">Choose how you'd like to authenticate:</p>

            <div className="w-full space-y-3">
                {selectedProvider.authMethods.map((method: AuthMethodConfig, idx: number) => (
                    <button
                        key={idx}
                        onClick={() => onAuthMethodSelect(method)}
                        className={`w-full p-5 border-2 rounded-2xl text-left transition-all hover:shadow-md ${method.preferred
                            ? 'border-[var(--brand-primary)]/30 bg-[var(--brand-primary)]/5 hover:border-[var(--brand-primary)]'
                            : 'border-gray-100 hover:border-gray-300 bg-white'
                            }`}
                        type="button"
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <span className="text-2xl">
                                    {method.type === 'oauth' && '🔐'}
                                    {method.type === 'api_key' && '🔑'}
                                    {method.type === 'username_password' && '👤'}
                                </span>
                                <div>
                                    <div className="font-bold text-gray-900">{method.label}</div>
                                    <div className="text-xs text-gray-500 mt-0.5">
                                        {method.type === 'oauth' && 'Secure sign-in through the platform'}
                                        {method.type === 'api_key' && `Enter your API credentials manually`}
                                        {method.type === 'username_password' && 'Use your account username and password'}
                                    </div>
                                </div>
                            </div>
                            {method.preferred && (
                                <span className="px-2 py-1 text-[10px] font-bold uppercase tracking-wide bg-[var(--brand-accent)] text-white rounded-full">
                                    Preferred
                                </span>
                            )}
                        </div>
                    </button>
                ))}
            </div>

            <button
                onClick={onBack}
                className="mt-8 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                type="button"
            >
                ← Choose different platform
            </button>
        </div>
    );
};
