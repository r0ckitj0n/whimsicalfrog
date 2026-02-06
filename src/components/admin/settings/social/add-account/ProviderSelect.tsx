import React from 'react';
import type { ISocialProvider } from '../../../../../hooks/admin/useSocialMedia.js';

interface ProviderSelectProps {
    providers: ISocialProvider[];
    onProviderClick: (provider: ISocialProvider) => void;
    onCancel: () => void;
    isLoading: boolean;
}

export const ProviderSelect: React.FC<ProviderSelectProps> = ({
    providers,
    onProviderClick,
    onCancel,
    isLoading
}) => {
    return (
        <div className="max-w-3xl mx-auto py-8 flex flex-col items-center text-center animate-in fade-in slide-in-from-bottom-2">
            <div className="w-16 h-16 bg-[var(--brand-primary)]/5 rounded-full flex items-center justify-center mb-6">
                <span className="text-3xl">🔗</span>
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Connect a Social Account</h3>
            <p className="text-gray-500 text-sm max-w-md mb-8 leading-relaxed">
                Link your social media accounts to publish content and manage your presence across platforms.
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 w-full">
                {providers.map(provider => (
                    <button
                        key={provider.id}
                        onClick={() => onProviderClick(provider)}
                        disabled={isLoading}
                        className="group relative flex flex-col items-start p-5 bg-white border-2 border-gray-100 hover:border-[var(--brand-primary)]/30 rounded-2xl transition-all hover:shadow-lg hover:shadow-[var(--brand-primary)]/5 text-left"
                        style={{ '--provider-color': provider.color } as React.CSSProperties}
                        type="button"
                    >
                        <div
                            className="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-3 transition-transform group-hover:scale-110"
                            style={{ backgroundColor: `${provider.color}15` }}
                        >
                            {provider.icon}
                        </div>
                        <div className="font-bold text-gray-900 text-sm mb-1">{provider.name}</div>
                        <div className="text-xs text-gray-500 leading-relaxed">{provider.description}</div>
                        <div
                            className="absolute top-4 right-4 w-2 h-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                            style={{ backgroundColor: provider.color }}
                        />
                    </button>
                ))}
            </div>

            <button
                onClick={onCancel}
                className="mt-6 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                type="button"
            >
                Cancel
            </button>
        </div>
    );
};
