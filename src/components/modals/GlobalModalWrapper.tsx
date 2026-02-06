import React, { lazy, Suspense } from 'react';
import type { IRoomMetadata, IRoomBackground } from '../../types/room.js';

const LoginModal = lazy(() => import('./LoginModal.js').then(m => ({ default: m.LoginModal })));
const CartModal = lazy(() => import('./CartModal.js').then(m => ({ default: m.CartModal })));
const AccountSettingsModal = lazy(() => import('./AccountSettingsModal.js').then(m => ({ default: m.AccountSettingsModal })));
const PolicyModal = lazy(() => import('./PolicyModal.js').then(m => ({ default: m.PolicyModal })));
const PaymentModal = lazy(() => import('./PaymentModal.js').then(m => ({ default: m.PaymentModal })));
const RoomModal = lazy(() => import('./RoomModal.js').then(m => ({ default: m.RoomModal })));
const ItemDetailsModal = lazy(() => import('./ItemDetailsModal.js').then(m => ({ default: m.ItemDetailsModal })));
const ReceiptModal = lazy(() => import('./ReceiptModal.js').then(m => ({ default: m.ReceiptModal })));
const GlobalModal = lazy(() => import('./GlobalModal.js').then(m => ({ default: m.GlobalModal })));
const TooltipManager = lazy(() => import('../TooltipManager.js').then(m => ({ default: m.TooltipManager })));
const Tooltip = lazy(() => import('../Tooltip.js').then(m => ({ default: m.Tooltip })));

interface IPolicyModalState {
    isOpen: boolean;
    url: string;
    label: string;
}

interface GlobalModalWrapperProps {
    authMode: string;
    closeAuthModal: () => void;
    isCartOpen: boolean;
    setIsCartOpen: (open: boolean) => void;
    policyModal: IPolicyModalState;
    closePolicy: () => void;
    isPaymentModalOpen: boolean;
    setIsPaymentModalOpen: (open: boolean) => void;
    isRoomOpen: boolean;
    closeRoom: () => void;
    currentRoom: string | null;
    roomContent: string;
    roomMetadata: IRoomMetadata;
    roomBackground: IRoomBackground | null;
    roomPanelColor: string;
    roomRenderContext: 'modal' | 'fullscreen';
    roomTargetAspectRatio: number | string | null;
    isRoomLoading: boolean;
    openItemModal: (sku: string) => void;
    isBare: boolean;
    itemModalSku: string;
    isItemModalOpen: boolean;
    closeItemModal: () => void;
    receiptOrderId: string | null;
    setReceiptOrderId: (id: string | null) => void;
}

export const GlobalModalWrapper: React.FC<GlobalModalWrapperProps> = ({
    authMode,
    closeAuthModal,
    isCartOpen,
    setIsCartOpen,
    policyModal,
    closePolicy,
    isPaymentModalOpen,
    setIsPaymentModalOpen,
    isRoomOpen,
    closeRoom,
    currentRoom,
    roomContent,
    roomMetadata,
    roomBackground,
    roomPanelColor,
    roomRenderContext,
    roomTargetAspectRatio,
    isRoomLoading,
    openItemModal,
    isBare,
    itemModalSku,
    isItemModalOpen,
    closeItemModal,
    receiptOrderId,
    setReceiptOrderId
}) => {
    return (
        <Suspense fallback={null}>
            <LoginModal
                isOpen={authMode === 'login' || authMode === 'register'}
                initialMode={authMode === 'register' ? 'register' : 'login'}
                onClose={closeAuthModal}
            />

            <CartModal
                isOpen={isCartOpen}
                onClose={() => setIsCartOpen(false)}
            />

            <AccountSettingsModal
                isOpen={authMode === 'account-settings'}
                onClose={closeAuthModal}
            />

            <PolicyModal
                isOpen={policyModal.isOpen}
                onClose={closePolicy}
                url={policyModal.url}
                label={policyModal.label}
            />

            <PaymentModal
                isOpen={isPaymentModalOpen}
                onClose={() => setIsPaymentModalOpen(false)}
            />

            <RoomModal
                isOpen={isRoomOpen}
                onClose={closeRoom}
                room_number={currentRoom}
                content={roomContent}
                metadata={roomMetadata}
                background={roomBackground}
                panelColor={roomPanelColor}
                renderContext={roomRenderContext}
                targetAspectRatio={roomTargetAspectRatio}
                isLoading={isRoomLoading}
                onOpenItem={openItemModal}
                is_bare={isBare}
            />

            <ItemDetailsModal
                sku={itemModalSku}
                isOpen={isItemModalOpen}
                onClose={closeItemModal}
            />

            <ReceiptModal
                order_id={receiptOrderId}
                isOpen={!!receiptOrderId}
                onClose={() => setReceiptOrderId(null)}
            />

            <TooltipManager />
            <Tooltip />
            <GlobalModal />
        </Suspense>
    );
};
