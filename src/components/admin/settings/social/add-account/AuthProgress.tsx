import React from 'react';
import type { ISocialProvider } from '../../../../../hooks/admin/useSocialMedia.js';

interface AuthProgressProps {
    selectedProvider: ISocialProvider;
    onBack: () => void;
}

export const AuthProgress: React.FC<AuthProgressProps> = ({
    selectedProvider,
    onBack
}) => {
    return (
        <div className="max-w-md mx-auto py-16 flex flex-col items-center text-center animate-in fade-in">
            <div
                className="w-20 h-20 rounded-2xl flex items-center justify-center text-4xl mb-6 animate-pulse"
                style={{ backgroundColor: `${selectedProvider.color}15` }}
            >
                {selectedProvider.icon}
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Connecting to {selectedProvider.name}</h3>
            <p className="text-gray-500 text-sm mb-8">
                Please complete the authorization in the popup window...
            </p>
            <div className="flex items-center gap-2 text-sm text-gray-400">
                <span className="wf-emoji-loader">🔄</span>
                <span>Waiting for authorization</span>
            </div>
            <button
                onClick={onBack}
                className="mt-8 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                type="button"
            >
                Cancel and go back
            </button>
        </div>
    );
};
