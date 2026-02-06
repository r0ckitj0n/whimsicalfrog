import React, { useState } from 'react';
import { useCartSimulation, IShopperProfile } from '../../../hooks/admin/useCartSimulation.js';

interface CartSimulationProps {
    onClose?: () => void;
}

export const CartSimulation: React.FC<CartSimulationProps> = ({ onClose }) => {
    const { isLoading, error, result, runSimulation, setResult } = useCartSimulation();
    const [profile, setProfile] = useState<IShopperProfile>({
        preferredCategory: '',
        budget: '',
        intent: '',
        device: '',
        region: ''
    });

    const handleRun = async (e: React.FormEvent) => {
        e.preventDefault();
        await runSimulation(profile);
    };

    const handleChange = (field: keyof IShopperProfile, value: string) => {
        setProfile({ ...profile, [field]: value });
    };

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🛒</span> Cart Simulation
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="common-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-6 flex flex-col min-h-[500px]">
                    {error && <div className="p-3 mb-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-lg">{error}</div>}

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Left: Profile Configuration */}
                        <div className="lg:col-span-1 space-y-6">
                            <div className="p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/10 rounded-xl">
                                <h4 className="font-bold text-[var(--brand-primary)] mb-4 text-sm uppercase tracking-wider">Shopper Profile</h4>
                                <form onSubmit={handleRun} className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-[10px] font-bold text-[var(--brand-primary)] uppercase">Preferred Category</label>
                                        <select
                                            value={profile.preferredCategory}
                                            onChange={e => handleChange('preferredCategory', e.target.value)}
                                            className="w-full p-2 border border-[var(--brand-primary)]/20 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/20 bg-white"
                                        >
                                            <option value="">Random / All</option>
                                            <option value="T-Shirts">T-Shirts</option>
                                            <option value="Tumblers">Tumblers</option>
                                            <option value="Home Decor">Home Decor</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-[10px] font-bold text-[var(--brand-primary)] uppercase">Budget Range</label>
                                        <select
                                            value={profile.budget}
                                            onChange={e => handleChange('budget', e.target.value)}
                                            className="w-full p-2 border border-[var(--brand-primary)]/20 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/20 bg-white"
                                        >
                                            <option value="">Default</option>
                                            <option value="budget">Conservative ($)</option>
                                            <option value="mid">Standard ($$)</option>
                                            <option value="premium">Premium ($$$)</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-[10px] font-bold text-[var(--brand-primary)] uppercase">Shopping Intent</label>
                                        <select
                                            value={profile.intent}
                                            onChange={e => handleChange('intent', e.target.value)}
                                            className="w-full p-2 border border-[var(--brand-primary)]/20 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/20 bg-white"
                                        >
                                            <option value="">Browsing</option>
                                            <option value="buying">Direct Purchase</option>
                                            <option value="comparison">Price Comparison</option>
                                            <option value="gift">Gift Hunting</option>
                                        </select>
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={isLoading}
                                        className="btn-text-primary w-full mt-4"
                                    >
                                        {isLoading ? 'Simulating...' : 'Run Simulation'}
                                    </button>
                                </form>
                            </div>
                        </div>

                        {/* Right: Results */}
                        <div className="lg:col-span-2 space-y-6">
                            {result ? (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    <div className="p-4 border rounded-xl bg-white shadow-sm">
                                        <h4 className="font-bold text-gray-800 mb-3 text-sm flex items-center gap-2">
                                            Current Cart (Seed)
                                        </h4>
                                        <div className="flex flex-wrap gap-2">
                                            {result.cart_skus.length > 0 ? result.cart_skus.map(sku => (
                                                <span key={sku} className="px-3 py-1 bg-gray-100 border rounded-full text-xs font-mono font-bold text-gray-700">
                                                    {sku}
                                                </span>
                                            )) : (
                                                <span className="text-sm text-gray-400 italic">Cart was empty</span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <h4 className="font-bold text-gray-800 text-sm flex items-center gap-2">
                                            AI-Driven Recommendations
                                        </h4>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            {result.recommendations.map(reco => (
                                                <div key={reco.sku} className="p-3 border rounded-xl bg-white flex gap-4 hover:shadow-md transition-shadow">
                                                    <div className="w-16 h-16 rounded-lg overflow-hidden bg-gray-50 flex-shrink-0 border">
                                                        <img
                                                            src={reco.image || '/images/items/placeholder.webp'}
                                                            alt={reco.name || 'Product Image'}
                                                            className="w-full h-full object-cover"
                                                            loading="lazy"
                                                        />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="text-sm font-bold text-gray-900 truncate" title={reco.name}>{reco.name}</div>
                                                        <div className="text-[10px] text-gray-400 font-mono mb-1">{reco.sku}</div>
                                                        <div className="text-sm font-black text-[var(--brand-primary)]">${Number(reco.price).toFixed(2)}</div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="h-full flex flex-col items-center justify-center text-gray-400 py-12">
                                    <div className="text-6xl mb-4">🔮</div>
                                    <p className="text-sm font-medium">Configure a shopper profile and run the simulation</p>
                                    <p className="text-xs mt-1">AI will predict items they might be interested in</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
