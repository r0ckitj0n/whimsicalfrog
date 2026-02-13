import React from 'react';

interface GlobalModalFooterProps {
    confirmText?: string;
    cancelText?: string;
    confirmStyle?: 'confirm' | 'danger' | 'secondary' | 'primary' | 'warning';
    showCancel?: boolean;
    extraActions?: Array<{
        label: string;
        href?: string;
        target?: '_self' | '_blank';
        onClick?: () => void;
        style?: 'secondary' | 'primary' | 'warning';
    }>;
    onConfirm: () => void;
    onCancel: () => void;
}

export const GlobalModalFooter: React.FC<GlobalModalFooterProps> = ({
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    confirmStyle = 'confirm',
    showCancel = true,
    extraActions = [],
    onConfirm,
    onCancel
}) => {
    const getConfirmBtnClass = () => {
        switch (confirmStyle) {
            case 'danger': return 'bg-[var(--brand-error)] hover:bg-[var(--brand-error)]/90 text-white shadow-lg shadow-[var(--brand-error-bg)]';
            case 'secondary': return 'bg-gray-100 hover:bg-gray-200 text-gray-700';
            case 'warning': return 'bg-[var(--brand-warning)] hover:bg-[var(--brand-warning)]/90 text-white shadow-lg';
            case 'primary': return 'bg-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/90 text-white shadow-[var(--brand-primary)]/10';
            case 'confirm':
            default: return 'bg-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/90 text-white shadow-[var(--brand-primary)]/10';
        }
    };

    const getActionBtnClass = (style?: 'secondary' | 'primary' | 'warning') => {
        switch (style) {
            case 'primary':
                return 'bg-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/90 text-white shadow-[var(--brand-primary)]/10';
            case 'warning':
                return 'bg-[var(--brand-warning)] hover:bg-[var(--brand-warning)]/90 text-white shadow-lg';
            case 'secondary':
            default:
                return 'bg-gray-100 hover:bg-gray-200 text-gray-700';
        }
    };

    return (
        <div className="flex gap-3 pt-2 flex-wrap">
            {showCancel && (
                <button
                    onClick={onCancel}
                    className="flex-1 min-w-[140px] px-4 py-2.5 rounded-xl text-sm font-black uppercase tracking-widest text-gray-400 hover:bg-[var(--brand-secondary)] hover:text-white transition-all border border-transparent"
                    data-help-id="modal-cancel"
                >
                    {cancelText}
                </button>
            )}

            {extraActions.map((action, idx) => {
                const handle = () => {
                    if (typeof action.onClick === 'function') action.onClick();
                    if (action.href) window.open(action.href, action.target || '_self', 'noopener,noreferrer');
                };
                return (
                    <button
                        key={`${action.label}-${idx}`}
                        onClick={handle}
                        className={`flex-1 min-w-[140px] px-4 py-2.5 rounded-xl text-sm font-black uppercase tracking-widest transition-all shadow-lg ${getActionBtnClass(action.style)}`}
                    >
                        {action.label}
                    </button>
                );
            })}

            <button
                onClick={onConfirm}
                className={`flex-1 min-w-[140px] px-4 py-2.5 rounded-xl text-sm font-black uppercase tracking-widest transition-all shadow-lg ${getConfirmBtnClass()}`}
            >
                {confirmText}
            </button>
        </div>
    );
};
