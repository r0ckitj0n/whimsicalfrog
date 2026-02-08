import React from 'react';

interface ItemDetailsHeaderProps {
    title: string;
    onClose: () => void;
}

export const ItemDetailsHeader: React.FC<ItemDetailsHeaderProps> = ({ title, onClose }) => {
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
