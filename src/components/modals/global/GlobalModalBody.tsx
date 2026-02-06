import React from 'react';

interface IPromptInputConfig {
    type?: string;
    placeholder?: string;
    defaultValue?: string;
}

interface GlobalModalBodyProps {
    message: string;
    subtitle?: string;
    details?: string;
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
    mode,
    inputValue,
    setInputValue,
    input,
    onConfirm
}) => {
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

            {details && (
                <div className="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-500 font-mono">
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
