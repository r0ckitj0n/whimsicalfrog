import React from 'react';
import type { ISocialProvider } from '../../../../../hooks/admin/useSocialMedia.js';
import type { AuthResult } from '../../../../../hooks/admin/useSocialAccountAuth.js';

interface AuthPreviewProps {
    selectedProvider: ISocialProvider;
    authResult: AuthResult;
    localLoading: boolean;
    onBack: () => void;
    onConfirmLink: () => void;
}

export const AuthPreview: React.FC<AuthPreviewProps> = ({
    selectedProvider,
    authResult,
    localLoading,
    onBack,
    onConfirmLink
}) => {
    return (
        <div className="max-w-md mx-auto py-12 flex flex-col items-center text-center animate-in fade-in slide-in-from-bottom-2">
            <div className="w-16 h-16 bg-[var(--brand-accent)]/10 rounded-full flex items-center justify-center mb-6">
                <span className="text-3xl">✅</span>
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Authorization Successful!</h3>
            <p className="text-gray-500 text-sm mb-8">
                Your {selectedProvider.name} account is ready to be linked.
            </p>

            <div
                className="w-full p-5 rounded-2xl border-2 mb-8"
                style={{
                    borderColor: `${selectedProvider.color}30`,
                    backgroundColor: `${selectedProvider.color}05`
                }}
            >
                <div className="flex items-center gap-4">
                    <div
                        className="w-14 h-14 rounded-xl flex items-center justify-center text-2xl flex-shrink-0"
                        style={{ backgroundColor: `${selectedProvider.color}20` }}
                    >
                        {selectedProvider.icon}
                    </div>
                    <div className="text-left">
                        <div className="font-bold text-gray-900">{authResult.accountName}</div>
                        <div className="text-xs text-gray-500 font-mono mt-1">ID: {authResult.accountId}</div>
                    </div>
                </div>
            </div>

            <div className="flex gap-3 w-full">
                <button
                    onClick={onBack}
                    disabled={localLoading}
                    className="flex-1 py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors"
                    type="button"
                >
                    Choose Different
                </button>
                <button
                    onClick={onConfirmLink}
                    disabled={localLoading}
                    className="flex-1 py-3 px-6 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-hover)] text-white font-bold rounded-xl transition-colors flex items-center justify-center gap-2"
                    type="button"
                >
                    {localLoading ? (
                        <>
                            <span className="wf-emoji-loader">🔄</span>
                            Linking...
                        </>
                    ) : (
                        <>Link Account</>
                    )}
                </button>
            </div>
        </div>
    );
};
