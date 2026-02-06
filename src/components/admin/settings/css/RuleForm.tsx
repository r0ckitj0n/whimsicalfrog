import React from 'react';
import { ICssRule } from '../../../../hooks/admin/useCssRules.js';

interface RuleFormProps {
    newRule: ICssRule;
    onInputChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
    onSubmit: (e: React.FormEvent) => void;
    isLoading: boolean;
}

export const RuleForm: React.FC<RuleFormProps> = ({
    newRule,
    onInputChange,
    onSubmit,
    isLoading
}) => {
    return (
        <div className="bg-white border rounded-[2rem] p-8 shadow-sm space-y-6 sticky top-6">
            <div className="space-y-1">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Add Style Rule</h3>
                <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-relaxed">
                    Inject custom CSS directly into the document head across all environments.
                </p>
            </div>

            <form onSubmit={onSubmit} className="space-y-4">
                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Selector</label>
                    <input 
                        type="text" name="selector" required
                        value={newRule.selector}
                        onChange={onInputChange}
                        placeholder=".btn-primary"
                        className="form-input w-full py-3 px-4 bg-gray-50 border-transparent focus:bg-white transition-all rounded-xl shadow-inner text-sm font-mono"
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Property</label>
                        <input 
                            type="text" name="property" required
                            value={newRule.property}
                            onChange={onInputChange}
                            placeholder="color"
                            className="form-input w-full py-3 px-4 bg-gray-50 border-transparent focus:bg-white transition-all rounded-xl shadow-inner text-sm font-mono"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Value</label>
                        <input 
                            type="text" name="value" required
                            value={newRule.value}
                            onChange={onInputChange}
                            placeholder="#87ac3a"
                            className="form-input w-full py-3 px-4 bg-gray-50 border-transparent focus:bg-white transition-all rounded-xl shadow-inner text-sm font-mono"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3 p-3 bg-[var(--brand-primary)]/5 rounded-xl border border-[var(--brand-primary)]/10">
                    <input 
                        type="checkbox" name="important"
                        id="important-toggle"
                        checked={newRule.important}
                        onChange={onInputChange}
                        className="w-4 h-4 rounded text-[var(--brand-primary)] focus:ring-[var(--brand-primary)] cursor-pointer"
                    />
                    <label htmlFor="important-toggle" className="text-xs font-bold text-[var(--brand-primary)]/80 cursor-pointer">
                        Force override (!important)
                    </label>
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Purpose / Note</label>
                    <textarea 
                        name="note"
                        value={newRule.note}
                        onChange={onInputChange}
                        rows={2}
                        className="form-input w-full py-3 px-4 bg-gray-50 border-transparent focus:bg-white transition-all rounded-xl shadow-inner text-sm italic resize-none"
                        placeholder="Explain why this rule exists..."
                    />
                </div>

                <button 
                    type="submit"
                    disabled={isLoading || !newRule.selector.trim() || !newRule.property.trim()}
                    className="btn btn-primary w-full py-4 mt-2 flex items-center justify-center gap-3 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all"
                >
                    {isLoading ? 'Deploying...' : 'Deploy Rule'}
                </button>
            </form>
        </div>
    );
};
