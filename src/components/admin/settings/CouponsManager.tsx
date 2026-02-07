import React, { useEffect } from 'react';
import { useCoupons } from '../../../hooks/admin/useCoupons.js';
import { useCouponManagerLogic } from '../../../hooks/admin/useCouponManagerLogic.js';
import { CouponTable } from './coupons/CouponTable.js';
import { CouponEditModal } from '../../modals/admin/settings/coupons/CouponEditModal.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface CouponsManagerProps {
    onClose?: () => void;
    title?: string;
}

export const CouponsManager: React.FC<CouponsManagerProps> = ({ onClose, title }) => {
    const api = useCoupons();
    const {
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
    } = useCouponManagerLogic({
        saveCoupon: api.saveCoupon,
        deleteCoupon: api.deleteCoupon
    });

    const initialFetchDone = React.useRef(false);
    useEffect(() => {
        if (!initialFetchDone.current) {
            initialFetchDone.current = true;
            api.fetchCoupons();
        }
    }, [api.fetchCoupons]);

    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty: Boolean(editingCoupon && isDirty),
        isBlocked: api.isLoading,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

    return (
        <div className="admin-modal-overlay over-header show topmost" role="dialog" aria-modal="true" onClick={(e) => e.target === e.currentTarget && void attemptClose()}>
            <div className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3"><span className="text-2xl">🎟️</span> {title || 'Coupon Manager'}</h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            {editingCoupon && <button onClick={handleSave} disabled={api.isLoading || !isDirty} className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`} data-help-id="common-save" type="button" />}
                            <button onClick={api.fetchCoupons} className="admin-action-btn btn-icon--refresh" data-help-id="common-refresh" type="button" />
                            <button onClick={handleCreate} className="admin-action-btn btn-icon--add" data-help-id="common-add" type="button" />
                            <button onClick={() => { void attemptClose(); }} className="admin-action-btn btn-icon--close" data-help-id="common-close" type="button" />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {api.error && <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3"><span className="text-lg">⚠️</span>{api.error}</div>}
                        <CouponTable
                            coupons={api.coupons}
                            isLoading={api.isLoading}
                            onEdit={handleEdit}
                            onDelete={handleDelete}
                            pendingDeleteId={pendingDeleteId}
                            setPendingDeleteId={setPendingDeleteId}
                        />
                    </div>
                </div>

                {editingCoupon && (
                    <CouponEditModal
                        editingCoupon={editingCoupon}
                        localCoupon={localCoupon}
                        setLocalCoupon={setLocalCoupon}
                        onSave={handleSave}
                        onCancel={() => { setEditingCoupon(null); setLocalCoupon(null); }}
                        isDirty={isDirty}
                        isSaving={api.isLoading}
                    />
                )}
            </div>
        </div>
    );
};
