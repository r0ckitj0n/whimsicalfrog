import React from 'react';

interface OutputConsoleProps {
    output: unknown;
    onClear: () => void;
}

export const OutputConsole: React.FC<OutputConsoleProps> = ({ output, onClear }) => {
    return (
        <div className="bg-white rounded-3xl p-6 shadow-sm overflow-hidden flex flex-col gap-4 border border-slate-100">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                    <h3 className="text-sm font-black text-slate-800 uppercase tracking-widest">System Output</h3>
                </div>
                <button
                    onClick={onClear}
                    className="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-700 transition-colors"
                >
                    Clear Console
                </button>
            </div>
            <pre className="text-[11px] font-mono text-slate-700 p-4 bg-slate-50 rounded-2xl border border-slate-100 overflow-auto max-h-[300px] leading-relaxed custom-scrollbar">
                {output ? JSON.stringify(output, null, 2) : 'Ready for input...'}
            </pre>
        </div>
    );
};
