import React from 'react';

interface OutputConsoleProps {
    output: unknown;
    onClear: () => void;
}

export const OutputConsole: React.FC<OutputConsoleProps> = ({ output, onClear }) => {
    return (
        <div className="bg-slate-900 rounded-3xl p-6 shadow-xl overflow-hidden flex flex-col gap-4 border-4 border-slate-800">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                    <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-widest">System Output</h3>
                </div>
                <button
                    onClick={onClear}
                    className="text-[9px] font-black text-slate-500 uppercase hover:text-white transition-colors"
                >
                    Clear Console
                </button>
            </div>
            <pre className="text-[11px] font-mono text-blue-300/80 p-4 bg-slate-950/50 rounded-2xl overflow-auto max-h-[300px] leading-relaxed custom-scrollbar">
                {output ? JSON.stringify(output, null, 2) : 'Ready for input...'}
            </pre>
        </div>
    );
};
