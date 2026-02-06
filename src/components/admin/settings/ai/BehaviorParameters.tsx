import React from 'react';
import { IAISettings } from '../../../../hooks/admin/useAISettings.js';

interface BehaviorParametersProps {
    settings: IAISettings;
    onChange: (field: keyof IAISettings, value: unknown) => void;
}

export const BehaviorParameters: React.FC<BehaviorParametersProps> = ({ settings, onChange }) => {
    return (
        <div className="space-y-4">
            <label className="block text-xs font-black text-gray-400 uppercase tracking-widest leading-none mb-1.5 ml-1">3. Global AI Tuning</label>
            <div className="p-6 bg-brand-primary/5 rounded-2xl border-2 border-brand-primary/10 space-y-6">
                <h4 className="text-sm font-black text-brand-secondary tracking-tight">Behavior Parameters</h4>
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-black text-blue-900/40 uppercase tracking-widest ml-1">Temperature</label>
                        <input
                            type="number" step="0.1" min="0" max="1"
                            value={settings.ai_temperature}
                            onChange={e => onChange('ai_temperature', parseFloat(e.target.value))}
                            className="w-full p-3 bg-white border-2 border-white rounded-xl text-xs font-black text-blue-900 focus:border-blue-200 outline-none shadow-sm"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-black text-blue-900/40 uppercase tracking-widest ml-1">Max Tokens</label>
                        <input
                            type="number" step="100" min="100"
                            value={settings.ai_max_tokens}
                            onChange={e => onChange('ai_max_tokens', parseInt(e.target.value))}
                            className="w-full p-3 bg-white border-2 border-white rounded-xl text-xs font-black text-blue-900 focus:border-blue-200 outline-none shadow-sm"
                        />
                    </div>
                </div>
                <label className="flex items-center gap-3 cursor-pointer group">
                    <div className="relative">
                        <input
                            type="checkbox"
                            checked={settings.fallback_to_local}
                            onChange={e => onChange('fallback_to_local', e.target.checked)}
                            className="peer sr-only"
                        />
                        <div className="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:bg-brand-primary transition-all duration-300"></div>
                        <div className="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-all duration-300 peer-checked:translate-x-5"></div>
                    </div>
                    <span className="text-[11px] font-black text-brand-secondary/60 group-hover:text-brand-secondary transition-colors uppercase tracking-tight">Auto-fallback to Local AI</span>
                </label>
            </div>
        </div>
    );
};
