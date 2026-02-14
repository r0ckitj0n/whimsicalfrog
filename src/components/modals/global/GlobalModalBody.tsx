import React, { useEffect, useState } from 'react';

interface IPromptInputConfig {
    type?: string;
    placeholder?: string;
    defaultValue?: string;
}

interface GlobalModalBodyProps {
    message: string;
    subtitle?: string;
    messageClassName?: string;
    subtitleClassName?: string;
    details?: string;
    detailsCollapsible?: boolean;
    detailsLabel?: string;
    detailsDefaultOpen?: boolean;
    detailsActions?: Array<{
        label: string;
        href?: string;
        target?: '_self' | '_blank';
        onClick?: () => void;
        style?: 'secondary' | 'primary' | 'warning';
    }>;
    mode: string;
    inputValue: string;
    setInputValue: (val: string) => void;
    input?: IPromptInputConfig;
    onConfirm: () => void;
}

export const GlobalModalBody: React.FC<GlobalModalBodyProps> = ({
    message,
    subtitle,
    messageClassName,
    subtitleClassName,
    details,
    detailsCollapsible = false,
    detailsLabel = 'Details',
    detailsDefaultOpen = false,
    detailsActions = [],
    mode,
    inputValue,
    setInputValue,
    input,
    onConfirm
}) => {
    const [detailsOpen, setDetailsOpen] = useState(detailsDefaultOpen);

    useEffect(() => {
        setDetailsOpen(detailsDefaultOpen);
    }, [detailsDefaultOpen, details]);

    return (
        <div className="space-y-3">
            {subtitle && (
                <p className={subtitleClassName || "text-xs font-bold text-gray-400 uppercase tracking-widest"}>
                    {subtitle}
                </p>
            )}

            <div className={messageClassName || "text-sm text-gray-600 leading-relaxed font-medium"}>
                {message}
            </div>

            {details && detailsCollapsible && (
                <div className="pt-1">
                    <button
                        type="button"
                        onClick={() => setDetailsOpen(v => !v)}
                        className="w-full flex items-center justify-between px-3 py-2 rounded-xl border border-gray-100 bg-gray-50 hover:bg-gray-100 transition-colors"
                        aria-expanded={detailsOpen}
                    >
                        <span className="text-[10px] font-black uppercase tracking-widest text-gray-500">
                            {detailsLabel}
                        </span>
                        <span
                            className="w-7 h-7 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500"
                            aria-hidden="true"
                            title={detailsOpen ? 'Hide details' : 'Show details'}
                        >
                            ℹ
                        </span>
                    </button>
                    {detailsOpen && (
                        <div className="mt-2 space-y-2">
                            <div className="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-500 font-mono whitespace-pre-wrap">
                                {details}
                            </div>
                            {detailsActions.length > 0 && (
                                <div className="flex gap-2 flex-wrap">
                                    {detailsActions.map((action, idx) => {
                                        const style = action.style || 'secondary';
                                        const cls = style === 'primary'
                                            ? 'bg-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/90 text-white'
                                            : (style === 'warning'
                                                ? 'bg-[var(--brand-warning)] hover:bg-[var(--brand-warning)]/90 text-white'
                                                : 'bg-gray-100 hover:bg-gray-200 text-gray-700');
                                        const handle = () => {
                                            if (typeof action.onClick === 'function') action.onClick();
                                            if (action.href) window.open(action.href, action.target || '_self', 'noopener,noreferrer');
                                        };
                                        return (
                                            <button
                                                key={`${action.label}-${idx}`}
                                                type="button"
                                                onClick={handle}
                                                className={`px-3 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest transition-colors ${cls}`}
                                            >
                                                {action.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {details && !detailsCollapsible && (
                <div className="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-500 font-mono whitespace-pre-wrap">
                    {details}
                </div>
            )}

            {mode === 'prompt' && (
                <div className="mt-2">
                    <input
                        type={input?.type || 'text'}
                        value={inputValue}
                        onChange={e => setInputValue(e.target.value)}
                        placeholder={input?.placeholder}
                        autoFocus
                        className="form-input w-full py-2 text-sm shadow-inner bg-gray-50 focus:bg-white transition-all"
                        onKeyDown={e => e.key === 'Enter' && onConfirm()}
                    />
                </div>
            )}
        </div>
    );
};
