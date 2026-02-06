import React from 'react';
import { IDiscount } from '../../../../hooks/admin/useDiscounts.js';
import { DISCOUNT_TYPE } from '../../../../core/constants.js';

interface DiscountCardProps {
    discount: IDiscount;
    index: number;
    onEdit: (index: number) => void;
    onDelete: (index: number) => void;
}

export const DiscountCard: React.FC<DiscountCardProps> = ({
    discount,
    index,
    onEdit,
    onDelete
}) => {
    return (
        <div className={`group border rounded-xl p-4 transition-all hover:border-[var(--brand-accent)]/30 hover:shadow-md ${discount.active ? 'bg-white' : 'bg-gray-50 grayscale opacity-60'}`}>
            <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-2">
                    <div className="font-mono font-black text-[var(--brand-accent)] bg-[var(--brand-accent)]/10 px-2 py-1 rounded text-sm uppercase tracking-wider">
                        {discount.code}
                    </div>
                    {!discount.active && <span className="text-[10px] font-bold text-gray-400 uppercase">Disabled</span>}
                </div>
                <div className="flex gap-1">
                    <button
                        onClick={() => onEdit(index)}
                        className="admin-action-btn btn-icon--edit"
                        type="button"
                        data-help-id="settings-discount-edit"
                    />
                    <button
                        onClick={() => onDelete(index)}
                        className="admin-action-btn btn-icon--delete"
                        type="button"
                        data-help-id="settings-discount-delete"
                    />
                </div>
            </div>

            <div className="flex items-baseline gap-1 mb-4">
                <span className="text-2xl font-black text-gray-900">
                    {discount.type === DISCOUNT_TYPE.PERCENTAGE ? discount.value : `$${discount.value}`}
                </span>
                <span className="text-xs font-bold text-gray-500 uppercase">
                    {discount.type === DISCOUNT_TYPE.PERCENTAGE ? '% OFF' : 'Flat Discount'}
                </span>
            </div>

            <div className="space-y-2 text-[11px]">
                <div className="flex items-center gap-2 text-gray-500">
                    <div className="admin-action-btn btn-icon--shopping-cart text-[10px]" data-help-id="settings-discount-min-order" />
                    <span>Min. Order: <strong>${discount.minTotal || 0}</strong></span>
                </div>
                <div className="flex items-center gap-2 text-gray-500">
                    <div className="admin-action-btn btn-icon--calendar text-[10px]" data-help-id="settings-discount-expires" />
                    <span>Expires: <strong>{discount.expires || 'Never'}</strong></span>
                </div>
            </div>
        </div>
    );
};
