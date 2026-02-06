import React from 'react';

export const OrderLoadingOverlay: React.FC = () => {
    return (
        <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                <span className="wf-emoji-loader">🐸</span>
                <p className="text-gray-500 font-medium text-dark-forced">Retrieving order details...</p>
            </div>
        </div>
    );
};
