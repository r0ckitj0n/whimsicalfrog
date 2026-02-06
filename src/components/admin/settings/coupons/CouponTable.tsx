import React from 'react';
import { ICoupon } from '../../../../hooks/admin/useCoupons.js';
import { DISCOUNT_TYPE } from '../../../../core/constants.js';

interface CouponTableProps {
    coupons: ICoupon[];
    isLoading: boolean;
    onEdit: (coupon: ICoupon) => void;
    onDelete: (id: number) => Promise<void>;
    pendingDeleteId: number | null;
    setPendingDeleteId: (id: number | null) => void;
}

export const CouponTable: React.FC<CouponTableProps> = ({
    coupons,
    isLoading,
    onEdit,
    onDelete,
    pendingDeleteId,
    setPendingDeleteId
}) => {
    return (
        <div className="overflow-x-auto border border-slate-100 rounded-[2rem] bg-white shadow-sm overflow-hidden">
            <table className="min-w-full divide-y divide-slate-50">
                <thead className="bg-slate-50/50">
                    <tr>
                        <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Code</th>
                        <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Discount</th>
                        <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Usage</th>
                        <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th className="px-8 py-5 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-slate-50">
                    {coupons.map(coupon => (
                        <tr key={coupon.id} className="hover:bg-blue-50/30 transition-colors group">
                            <td className="px-8 py-4">
                                <span className="px-3 py-1 bg-slate-100 text-slate-800 rounded-lg text-xs font-black font-mono tracking-wider">{coupon.code}</span>
                            </td>
                            <td className="px-8 py-4">
                                <span className="text-sm font-bold text-slate-700">
                                    {coupon.type === DISCOUNT_TYPE.PERCENTAGE ? `${coupon.value}%` : `$${coupon.value}`}
                                </span>
                            </td>
                            <td className="px-8 py-4">
                                <span className="text-xs font-medium text-slate-500">
                                    {coupon.usage_count} {coupon.usage_limit ? `/ ${coupon.usage_limit}` : ''}
                                </span>
                            </td>
                            <td className="px-8 py-4">
                                <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest ${coupon.is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-100'}`}>
                                    {coupon.is_active ? 'Active' : 'Disabled'}
                                </span>
                            </td>
                            <td className="px-8 py-4 text-right whitespace-nowrap">
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => onEdit(coupon)}
                                        className="admin-action-btn btn-icon--edit"
                                        data-help-id="common-edit"
                                    />
                                    {pendingDeleteId === coupon.id ? (
                                        <div className="flex gap-1 animate-in slide-in-from-right-2">
                                            <button
                                                type="button"
                                                onClick={() => setPendingDeleteId(null)}
                                                className="px-2 py-1 text-[10px] font-bold text-slate-500 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => onDelete(coupon.id)}
                                                className="px-2 py-1 text-[10px] font-bold text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors"
                                            >
                                                Confirm
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setPendingDeleteId(coupon.id)}
                                            className="admin-action-btn btn-icon--delete"
                                            data-help-id="common-delete"
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {coupons.length === 0 && !isLoading && (
                        <tr>
                            <td colSpan={5} className="px-8 py-20 text-center">
                                <div className="text-4xl mb-4 opacity-20">📭</div>
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">No coupons found</p>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};
