import { useState, useCallback, useMemo } from 'react';
import { ICoupon } from './useCoupons.js';
import { DISCOUNT_TYPE } from '../../core/constants.js';
import { isDraftDirty } from '../../core/utils.js';

interface UseCouponManagerLogicProps {
    saveCoupon: (coupon: ICoupon) => Promise<{ success: boolean; error?: string }>;
    deleteCoupon: (id: number) => Promise<boolean>;
}

export const useCouponManagerLogic = ({
    saveCoupon,
    deleteCoupon
}: UseCouponManagerLogicProps) => {
    const [editingCoupon, setEditingCoupon] = useState<ICoupon | null>(null);
    const [localCoupon, setLocalCoupon] = useState<ICoupon | null>(null);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

    const handleCreate = useCallback(() => {
        const empty: Partial<ICoupon> = {
            code: '',
            type: DISCOUNT_TYPE.PERCENTAGE,
            value: 0,
            is_active: true,
            usage_count: 0
        };
        setEditingCoupon(empty as ICoupon);
        setLocalCoupon(empty as ICoupon);
    }, []);

    const handleSave = useCallback(async () => {
        if (localCoupon) {
            const res = await saveCoupon(localCoupon);
            if (res?.success) {
                setEditingCoupon(null);
                setLocalCoupon(null);
                if (window.WFToast) window.WFToast.success('Coupon saved successfully');
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || 'Failed to save coupon');
            }
        }
    }, [localCoupon, saveCoupon]);

    const isDirty = useMemo(() => {
        return isDraftDirty(localCoupon, editingCoupon);
    }, [localCoupon, editingCoupon]);

    const handleEdit = useCallback((coupon: ICoupon) => {
        setEditingCoupon(coupon);
        setLocalCoupon(coupon);
    }, []);

    const handleDelete = useCallback(async (id: number) => {
        const success = await deleteCoupon(id);
        if (success) {
            if (window.WFToast) window.WFToast.success('Coupon deleted');
        } else {
            if (window.WFToast) window.WFToast.error('Failed to delete coupon');
        }
    }, [deleteCoupon]);

    return {
        editingCoupon,
        setEditingCoupon,
        localCoupon,
        setLocalCoupon,
        pendingDeleteId,
        setPendingDeleteId,
        handleCreate,
        handleSave,
        handleEdit,
        handleDelete,
        isDirty
    };
};
