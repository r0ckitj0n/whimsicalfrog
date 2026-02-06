import React from 'react';
import { createPortal } from 'react-dom';
import { ICoupon } from '../../../../../hooks/admin/useCoupons.js';
import { DISCOUNT_TYPE } from '../../../../../core/constants.js';

interface CouponEditModalProps {
    editingCoupon: ICoupon;
    localCoupon: ICoupon | null;
    setLocalCoupon: (coupon: ICoupon) => void;
    onSave: () => void;
    onCancel: () => void;
}

export const CouponEditModal: React.FC<CouponEditModalProps> = ({
    editingCoupon,
    localCoupon,
    setLocalCoupon,
    onSave,
    onCancel
}) => {
    return createPortal(
        <div
            className="admin-modal-overlay topmost-child show fixed inset-0 flex items-center justify-center p-8 bg-blue-900/20 backdrop-blur-sm animate-in fade-in duration-200"
            onClick={onCancel}
        >
            <div
                className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-300 border border-white"
                onClick={e => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
            >
                <div className="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-white">
                    <h3 className="text-lg font-black text-slate-800 uppercase tracking-tight">
                        {editingCoupon.id ? 'Edit Coupon' : 'New Coupon'}
                    </h3>
                    <button
                        type="button"
                        onClick={onCancel}
                        className="admin-action-btn btn-icon--close"
                        aria-label="Close"
                        data-help-id="common-close"
                    />
                </div>
                <form onSubmit={(e) => { e.preventDefault(); onSave(); }} className="p-10 space-y-8 bg-slate-50/50">
                    <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Coupon Code</label>
                        <input
                            type="text" required
                            value={localCoupon?.code || ''}
                            onChange={e => setLocalCoupon({ ...localCoupon!, code: e.target.value.toUpperCase() })}
                            className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-blue-600/5 shadow-sm font-black font-mono tracking-widest text-slate-800 transition-all"
                            placeholder="SUMMER2024"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-6">
                        <div className="space-y-3">
                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Type</label>
                            <div className="relative">
                                <select
                                    value={localCoupon?.type}
                                    onChange={e => setLocalCoupon({ ...localCoupon!, type: e.target.value as ICoupon['type'] })}
                                    className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-blue-600/5 shadow-sm font-bold text-slate-700 appearance-none transition-all cursor-pointer"
                                >
                                    <option value={DISCOUNT_TYPE.PERCENTAGE}>Percentage (%)</option>
                                    <option value={DISCOUNT_TYPE.FIXED}>Fixed Amount ($)</option>
                                </select>
                                <div className="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">▼</div>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Value</label>
                            <input
                                type="number" step="0.01" required
                                value={localCoupon?.value || 0}
                                onChange={e => setLocalCoupon({ ...localCoupon!, value: parseFloat(e.target.value) })}
                                className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-blue-600/5 shadow-sm font-bold text-slate-800 transition-all"
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-4 cursor-pointer p-4 bg-white border border-slate-100 rounded-2xl shadow-sm hover:border-blue-200 transition-all select-none group">
                        <div className={`w-10 h-6 rounded-full relative transition-all ${localCoupon?.is_active ? 'bg-blue-600' : 'bg-slate-200'}`}>
                            <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${localCoupon?.is_active ? 'left-5' : 'left-1'}`} />
                        </div>
                        <input
                            type="checkbox"
                            checked={localCoupon?.is_active || false}
                            onChange={e => setLocalCoupon({ ...localCoupon!, is_active: e.target.checked })}
                            className="hidden"
                        />
                        <span className="text-[11px] font-black text-slate-800 uppercase tracking-widest">Active and redeemable</span>
                    </label>
                </form>
            </div>
        </div>,
        document.body
    );
};
