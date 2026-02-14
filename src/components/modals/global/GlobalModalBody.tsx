import React, { useEffect, useState } from 'react';

interface IPromptInputConfig {
    type?: string;
    placeholder?: string;
    defaultValue?: string;
}

interface GlobalModalBodyProps {
    message: string;
    subtitle?: string;
    details?: string;
    detailsCollapsible?: boolean;
    detailsLabel?: string;
    detailsDefaultOpen?: boolean;
    mode: string;
    inputValue: string;
    setInputValue: (val: string) => void;
    input?: IPromptInputConfig;
    onConfirm: () => void;
}

export const GlobalModalBody: React.FC<GlobalModalBodyProps> = ({
    message,
    subtitle,
    details,
    detailsCollapsible = false,
    detailsLabel = 'Details',
    detailsDefaultOpen = false,
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
                <p className="text-xs font-bold text-gray-400 uppercase tracking-widest">
                    {subtitle}
                </p>
            )}

            <div className="text-sm text-gray-600 leading-relaxed font-medium">
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
                        <div className="mt-2 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-500 font-mono whitespace-pre-wrap">
                            {details}
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
