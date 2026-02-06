import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';

interface LayoutSectionProps {
    editTokens: Partial<IBrandingTokens>;
    onChange: (key: keyof IBrandingTokens, value: string) => void;
}

export const LayoutSection: React.FC<LayoutSectionProps> = ({
    editTokens,
    onChange
}) => {
    return (
        <div className="space-y-10 animate-in fade-in slide-in-from-bottom-2">
            {/* Modal & Button Layout */}
            <section>
                <h3 className="text-sm font-black text-gray-700 mb-4 uppercase tracking-wider flex items-center gap-2">
                    <span className="text-lg">📐</span> Modal & Button Layout
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Modal Radius</label>
                        <input
                            type="text"
                            value={(editTokens.business_admin_modal_radius as string) || ''}
                            onChange={(e) => onChange('business_admin_modal_radius', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="12px"
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Button Height</label>
                        <input
                            type="text"
                            value={(editTokens.business_button_height as string) || ''}
                            onChange={(e) => onChange('business_button_height', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="40px"
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Modal Shadow</label>
                        <input
                            type="text"
                            value={(editTokens.business_admin_modal_shadow as string) || ''}
                            onChange={(e) => onChange('business_admin_modal_shadow', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="0 10px 25px rgba(0,0,0,0.1)"
                        />
                    </div>
                </div>
            </section>

            {/* Shadow Scale */}
            <section>
                <h3 className="text-sm font-black text-gray-700 mb-4 uppercase tracking-wider flex items-center gap-2">
                    <span className="text-lg">🌑</span> Shadow Scale
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Shadow SM</label>
                        <input
                            type="text"
                            value={(editTokens.business_shadow_sm as string) || ''}
                            onChange={(e) => onChange('business_shadow_sm', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="0 1px 3px rgba(0, 0, 0, 0.1)"
                        />
                        <div className="h-8 bg-white rounded-lg" style={{ boxShadow: editTokens.business_shadow_sm || '0 1px 3px rgba(0, 0, 0, 0.1)' }} />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Shadow MD</label>
                        <input
                            type="text"
                            value={(editTokens.business_shadow_md as string) || ''}
                            onChange={(e) => onChange('business_shadow_md', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="0 4px 6px -1px rgba(0, 0, 0, 0.1)"
                        />
                        <div className="h-8 bg-white rounded-lg" style={{ boxShadow: editTokens.business_shadow_md || '0 4px 6px -1px rgba(0, 0, 0, 0.1)' }} />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Shadow LG</label>
                        <input
                            type="text"
                            value={(editTokens.business_shadow_lg as string) || ''}
                            onChange={(e) => onChange('business_shadow_lg', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="0 12px 20px -8px rgba(0, 0, 0, 0.1)"
                        />
                        <div className="h-8 bg-white rounded-lg" style={{ boxShadow: editTokens.business_shadow_lg || '0 12px 20px -8px rgba(0, 0, 0, 0.1)' }} />
                    </div>
                </div>
            </section>

            {/* Transitions */}
            <section>
                <h3 className="text-sm font-black text-gray-700 mb-4 uppercase tracking-wider flex items-center gap-2">
                    <span className="text-lg">⚡</span> Transition Speeds
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Fast (hover effects)</label>
                        <input
                            type="text"
                            value={(editTokens.business_transition_fast as string) || ''}
                            onChange={(e) => onChange('business_transition_fast', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="all 0.2s ease"
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Normal (UI changes)</label>
                        <input
                            type="text"
                            value={(editTokens.business_transition_normal as string) || ''}
                            onChange={(e) => onChange('business_transition_normal', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="all 0.3s ease"
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Smooth (modals)</label>
                        <input
                            type="text"
                            value={(editTokens.business_transition_smooth as string) || ''}
                            onChange={(e) => onChange('business_transition_smooth', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="all 0.3s cubic-bezier(0.4, 0, 0.2, 1)"
                        />
                    </div>
                </div>
            </section>

            {/* Hover Effects */}
            <section>
                <h3 className="text-sm font-black text-gray-700 mb-4 uppercase tracking-wider flex items-center gap-2">
                    <span className="text-lg">👆</span> Hover Lift Effects
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Standard Lift</label>
                        <input
                            type="text"
                            value={(editTokens.business_hover_lift as string) || ''}
                            onChange={(e) => onChange('business_hover_lift', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="-2px"
                        />
                        <p className="text-xs text-gray-400">Negative value moves element up on hover</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Large Lift</label>
                        <input
                            type="text"
                            value={(editTokens.business_hover_lift_lg as string) || ''}
                            onChange={(e) => onChange('business_hover_lift_lg', e.target.value)}
                            className="form-input text-sm w-full"
                            placeholder="-4px"
                        />
                        <p className="text-xs text-gray-400">For prominent cards and featured items</p>
                    </div>
                </div>
            </section>
        </div>
    );
};
