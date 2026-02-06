import React from 'react';

interface RoomEnvironmentSectionProps {
    bgUrl: string;
    onBgUrlChange: (val: string) => void;
    iconPanelColor: string;
    onIconPanelColorChange: (val: string) => void;
    renderContext: string;
    onRenderContextChange: (val: string) => void;
    targetAspectRatio: number;
    onTargetAspectRatioChange: (val: number) => void;
}

export const RoomEnvironmentSection: React.FC<RoomEnvironmentSectionProps> = ({
    bgUrl,
    onBgUrlChange,
    iconPanelColor,
    onIconPanelColorChange,
    renderContext,
    onRenderContextChange,
    targetAspectRatio,
    onTargetAspectRatioChange
}) => {
    // Current width and height based on aspect ratio (defaulting to 1024x768 base)
    const baseWidth = 1024;
    const currentWidth = renderContext === 'fixed' ? Math.round(targetAspectRatio * 768) || 1024 : 1024;
    const currentHeight = renderContext === 'fixed' ? Math.round(1024 / targetAspectRatio) || 768 : 768;

    const handleWidthChange = (w: number) => {
        if (w > 0) {
            onTargetAspectRatioChange(w / currentHeight);
        }
    };

    const handleHeightChange = (h: number) => {
        if (h > 0) {
            onTargetAspectRatioChange(currentWidth / h);
        }
    };
    // Determine dropdown value for icon panel color
    const getIconPanelDropdownValue = () => {
        if (iconPanelColor === 'var(--brand-primary)') return 'brand-primary';
        if (iconPanelColor === 'var(--brand-secondary)') return 'brand-secondary';
        if (iconPanelColor === 'transparent') return 'transparent';
        return 'custom';
    };

    const handleIconPanelDropdownChange = (value: string) => {
        if (value === 'brand-primary') onIconPanelColorChange('var(--brand-primary)');
        else if (value === 'brand-secondary') onIconPanelColorChange('var(--brand-secondary)');
        else if (value === 'transparent') onIconPanelColorChange('transparent');
        else onIconPanelColorChange('#ffffff');
    };

    const isCustomColor = iconPanelColor !== 'var(--brand-primary)' &&
        iconPanelColor !== 'var(--brand-secondary)' &&
        iconPanelColor !== 'transparent';

    return (
        <section className="space-y-4">
            <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest pb-2 border-b">
                Room & Environment
            </div>
            <div className="space-y-4">

                <div className="space-y-2 bg-white p-3 rounded-xl border border-gray-100">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">Scale Mode</label>
                    <select
                        value={renderContext}
                        onChange={(e) => onRenderContextChange(e.target.value)}
                        className="w-full p-2.5 bg-slate-50 border-0 rounded-xl text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500/20"
                    >
                        <option value="modal">Modal (4:3)</option>
                        <option value="fullscreen">Full Screen (dynamic)</option>
                        <option value="fixed">Fixed</option>
                    </select>
                    {renderContext === 'fixed' && (
                        <div className="pt-2 space-y-3 border-t border-gray-100 mt-2">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="text-[9px] font-bold text-gray-400 uppercase">Width (px)</label>
                                    <input
                                        type="number"
                                        value={currentWidth}
                                        onChange={(e) => handleWidthChange(parseInt(e.target.value) || 0)}
                                        placeholder="1024"
                                        className="w-full p-2 bg-slate-50 border-0 rounded-lg text-sm font-bold text-gray-700 focus:ring-2 focus:ring-blue-500/20"
                                    />
                                </div>
                                <div>
                                    <label className="text-[9px] font-bold text-gray-400 uppercase">Height (px)</label>
                                    <input
                                        type="number"
                                        value={currentHeight}
                                        onChange={(e) => handleHeightChange(parseInt(e.target.value) || 0)}
                                        placeholder="768"
                                        className="w-full p-2 bg-slate-50 border-0 rounded-lg text-sm font-bold text-gray-700 focus:ring-2 focus:ring-blue-500/20"
                                    />
                                </div>
                            </div>
                            <p className="text-[10px] text-blue-500/80 font-bold italic">
                                Aspect Ratio: {targetAspectRatio.toFixed(3)}
                            </p>
                        </div>
                    )}
                    <p className="text-[10px] text-gray-400 leading-relaxed font-bold">
                        {renderContext === 'fullscreen'
                            ? 'Scales to fill the entire browser viewport dynamically.'
                            : renderContext === 'fixed'
                                ? 'Renders at a fixed pixel size regardless of browser dimensions.'
                                : 'Renders in a 4:3 aspect ratio modal window.'
                        }
                    </p>
                </div>

                <div className="space-y-1">
                    <label className="text-xs font-bold text-gray-500 uppercase">Background URL</label>
                    <input
                        type="text"
                        value={bgUrl}
                        onChange={(e) => onBgUrlChange(e.target.value)}
                        placeholder="/images/backgrounds/..."
                        className="form-input w-full text-sm py-1.5"
                    />
                </div>

                <div className="space-y-2 bg-white p-3 rounded-xl border border-gray-100">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">Icon Panel Color</label>
                    <select
                        value={getIconPanelDropdownValue()}
                        onChange={(e) => handleIconPanelDropdownChange(e.target.value)}
                        className="w-full p-2.5 bg-slate-50 border-0 rounded-xl text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500/20"
                    >
                        <option value="brand-primary">Primary Brand Color</option>
                        <option value="brand-secondary">Secondary Brand Color</option>
                        <option value="transparent">Transparent</option>
                        <option value="custom">Custom Hex Color</option>
                    </select>
                    {isCustomColor && (
                        <div className="flex gap-2 items-center pt-1">
                            <div
                                className="relative w-10 h-10 rounded-lg overflow-hidden shadow-inner border border-gray-100 flex-shrink-0"
                                style={{ backgroundColor: iconPanelColor || '#ffffff' }}
                            >
                                <input
                                    type="color"
                                    value={iconPanelColor || '#ffffff'}
                                    onChange={(e) => onIconPanelColorChange(e.target.value)}
                                    className="absolute -inset-2 w-[150%] h-[150%] cursor-pointer opacity-0"
                                />
                            </div>
                            <input
                                type="text"
                                value={iconPanelColor}
                                onChange={(e) => onIconPanelColorChange(e.target.value)}
                                placeholder="#FFFFFF"
                                className="flex-1 p-2 bg-slate-50 border-0 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20"
                            />
                        </div>
                    )}
                    <p className="text-[10px] text-gray-400 font-bold leading-tight">Controls the background tint of the interactive icon panels in-game.</p>
                </div>
            </div>
        </section>
    );
};
