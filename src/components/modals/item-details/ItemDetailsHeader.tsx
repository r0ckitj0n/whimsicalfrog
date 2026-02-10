import React from 'react';

interface ItemDetailsHeaderProps {
    title: string;
    onClose: () => void;
    isMobileLayout?: boolean;
}

export const ItemDetailsHeader: React.FC<ItemDetailsHeaderProps> = ({ title, onClose, isMobileLayout = false }) => {
    if (!isMobileLayout) {
        return (
            <div className="wf-modal-header" style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '1.25rem 2rem',
                background: 'var(--bg-gradient-brand, linear-gradient(135deg, #87ac3a 0%, #769632 100%))',
                borderBottom: '1px solid rgba(255,255,255,0.1)',
                flexShrink: 0,
                zIndex: 10
            }}>
                <div className="flex items-center gap-3">
                    <span className="btn-icon--shopping-bag text-white" style={{ fontSize: '24px' }} aria-hidden="true" />
                    <h2 className="admin-card-title" style={{
                        margin: 0,
                        color: '#ffffff',
                        fontFamily: "'Merienda', cursive",
                        fontSize: '1.5rem',
                        fontWeight: 700,
                        textShadow: '0 2px 4px rgba(0,0,0,0.1)'
                    }}>
                        {title}
                    </h2>
                </div>
                <button
                    onClick={onClose}
                    className="admin-action-btn btn-icon--close"
                    aria-label="Close"
                    data-help-id="modal-close"
                />
            </div>
        );
    }

    return (
        <div className="wf-modal-header sticky top-0 z-20 flex shrink-0 items-center justify-between border-b border-white/15 bg-[var(--brand-primary)] px-4 py-3 sm:px-6 sm:py-4">
            <div className="min-w-0 pr-3">
                <div className="flex items-center gap-2 sm:gap-3">
                    <span className="btn-icon--shopping-bag text-lg text-white sm:text-2xl" aria-hidden="true" />
                    <h2 className="admin-card-title truncate font-merienda text-base font-bold text-white drop-shadow-sm sm:text-xl">
                        {title}
                    </h2>
                </div>
            </div>
            <button
                onClick={onClose}
                className="admin-action-btn btn-icon--close"
                aria-label="Close"
                data-help-id="modal-close"
            />
        </div>
    );
};
