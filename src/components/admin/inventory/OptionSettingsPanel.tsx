import React, { useState, useEffect } from 'react';
import { useOptionSettings, IOptionSettings } from '../../../hooks/admin/useOptionSettings.js';

interface OptionSettingsPanelProps {
    sku: string;
    isReadOnly?: boolean;
}

export const OptionSettingsPanel: React.FC<OptionSettingsPanelProps> = ({ sku, isReadOnly = false }) => {
    const {
        settings,
        isLoading,
        error,
        saveSettings,
        refresh
    } = useOptionSettings(sku);

    const [localSettings, setLocalSettings] = useState<IOptionSettings>(settings);

    useEffect(() => {
        setLocalSettings(settings);
    }, [settings]);

    const handleSave = async () => {
        const res = await saveSettings(localSettings);
        if (res.success) {
            if (window.WFToast) window.WFToast.success('Settings saved');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save');
        }
    };

    const handleCascadeChange = (index: number, value: string) => {
        const next = [...localSettings.cascade_order];
        next[index] = value;
        setLocalSettings({ ...localSettings, cascade_order: next });
    };

    const handleDimensionToggle = (dim: string) => {
        const next = localSettings.enabled_dimensions.includes(dim)
            ? localSettings.enabled_dimensions.filter(d => d !== dim)
            : [...localSettings.enabled_dimensions, dim];
        setLocalSettings({ ...localSettings, enabled_dimensions: next });
    };

    const handleGroupingChange = (val: string) => {
        try {
            const parsed = val.trim() ? JSON.parse(val) : {};
            setLocalSettings({ ...localSettings, grouping_rules: parsed });
        } catch (_) {
            // Keep the invalid string in state for the textarea, but it won't be valid JSON
        }
    };

    if (isLoading && !localSettings.cascade_order.length) {
        return (
            <div className="p-12 flex flex-col items-center justify-center text-gray-400 gap-2">
                <span className="wf-emoji-loader">⚙️</span>
                <p className="text-xs font-bold uppercase tracking-widest">Optimizing Cascade...</p>
            </div>
        );
    }

    return (
        <div className="bg-white/80 border border-amber-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div className="px-6 py-4 border-b border-amber-200 bg-amber-50/80 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div>
                        <h2 className="text-lg font-bold text-amber-900 tracking-tight">Option Cascade & Grouping</h2>
                        <p className="text-[10px] text-amber-700/70 font-black uppercase tracking-widest">Logic Flow Configuration</p>
                    </div>
                </div>
                {!isReadOnly && (
                    <div className="flex gap-2">
                        <button
                            onClick={refresh}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                        />
                        <button
                            onClick={handleSave}
                            disabled={isLoading}
                            className="admin-action-btn btn-icon--save"
                            data-help-id="common-save"
                        />
                    </div>
                )}
            </div>

            <div className="p-6 space-y-8 bg-white">
                {/* Cascade Order */}
                <section className="space-y-4">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">
                        Cascade Priority
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {[0, 1, 2].map(i => (
                            <div key={i} className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase">Priority {i + 1}</label>
                                <select
                                    value={localSettings.cascade_order[i] || ''}
                                    onChange={e => handleCascadeChange(i, e.target.value)}
                                    disabled={isReadOnly}
                                    className="form-select w-full text-sm font-bold text-gray-700 bg-gray-50 border-gray-100 focus:bg-white transition-all rounded-xl"
                                >
                                    <option value="">N/A (Skip)</option>
                                    <option value="gender">Style/Gender</option>
                                    <option value="size">Size</option>
                                    <option value="color">Color</option>
                                </select>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Enabled Dimensions */}
                <section className="space-y-4">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">
                        Active Dimensions
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {['gender', 'size', 'color'].map(dim => {
                            const isActive = localSettings.enabled_dimensions.includes(dim);
                            return (
                                <button
                                    key={dim}
                                    onClick={() => !isReadOnly && handleDimensionToggle(dim)}
                                    className={`px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-transparent border-0 ${isActive
                                        ? 'text-[var(--brand-primary)]'
                                        : 'text-gray-400 hover:text-[var(--brand-primary)]'
                                        }`}
                                >
                                    {dim === 'gender' ? 'Style' : dim}
                                </button>
                            );
                        })}
                    </div>
                </section>

                {/* Grouping Rules */}
                <section className="space-y-4">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">
                        Advanced Grouping (JSON)
                    </div>
                    <textarea
                        value={JSON.stringify(localSettings.grouping_rules, null, 2)}
                        onChange={e => handleGroupingChange(e.target.value)}
                        readOnly={isReadOnly}
                        className="form-input w-full h-32 font-mono text-xs bg-[var(--brand-dark)] text-[var(--brand-accent)] rounded-2xl p-4 shadow-inner"
                        placeholder="{}"
                    />
                </section>
            </div>

        </div>
    );
};
