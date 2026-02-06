import React from 'react';

interface RegisterHeaderProps {
    onToggleFullscreen: () => void;
}

export const RegisterHeader: React.FC<RegisterHeaderProps> = ({ onToggleFullscreen }) => {
    return (
        <header className="pos-header">
            <div className="pos-title">
                <span className="text-2xl">🛒</span>
                WhimsicalFrog POS
            </div>
            <div className="pos-header-actions">
                <a href="/admin?section=dashboard" className="pos-header-link">
                    <span className="text-lg">←</span>
                    Back to Admin
                </a>
                <button
                    onClick={onToggleFullscreen}
                    className="pos-fullscreen-btn"
                >
                    <span className="text-xs">🖥️</span>
                    Fullscreen
                </button>
            </div>
        </header>
    );
};
