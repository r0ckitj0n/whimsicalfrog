import React, { useEffect, Suspense } from 'react';
import useRoomManager from '../hooks/use-room-manager.js';
import '../styles/components/buttons/emojis.css';
import usePageRouter from '../hooks/use-page-router.js';
import useGlobalListeners from '../hooks/use-global-listeners.js';
import { useApp } from '../context/AppContext.js';
import { useAuthContext } from '../context/AuthContext.js';
import { useAuthModal } from '../hooks/useAuthModal.js';
import { useModalContext } from '../context/ModalContext.js';
import { useItemModal } from '../hooks/useItemModal.js';
import { useSiteHydration } from '../hooks/useSiteHydration.js';
import { ContactManager } from './ContactManager.js';
import { HeaderManager } from './HeaderManager.js';
import { ModalBridge } from '../ModalBridge.js';
import { NotificationBridge } from '../NotificationBridge.js';
import { NotificationContainer } from './ui/notifications/NotificationContainer.js';
import { SiteCoreBridge } from '../SiteCoreBridge.js';
import { AnalyticsBridge } from '../AnalyticsBridge.js';
import { Header } from './shell/Header.js';
import { Footer } from './shell/Footer.js';
import { GlobalProcessingOverlay } from './GlobalProcessingOverlay.js';
import { useSearchParams } from 'react-router-dom';
import { SETTINGS_MODAL_SECTIONS } from '../core/constants.js';
import { AdminLoading } from './admin/AdminLoading.js';
import { useAppEffects } from '../hooks/useAppEffects.js';
import { GlobalModalWrapper } from './modals/GlobalModalWrapper.js';
import { MainPageRenderer } from './MainPageRenderer.js';

export const AppShell: React.FC = () => {
    const { isLoggedIn, user } = useAuthContext();
    usePageRouter();
    useGlobalListeners();

    const {
        isCartOpen,
        setIsCartOpen,
        policyModal,
        closePolicy
    } = useApp();

    useEffect(() => {
        if (typeof window === 'undefined') return;
        window.openCartModal = () => setIsCartOpen(true);
        window.closeCartModal = () => setIsCartOpen(false);
        window.WF_CartModal = {
            open: () => setIsCartOpen(true),
            close: () => setIsCartOpen(false)
        };

        const linkId = 'wf-dynamic-icon-css';
        let link = document.getElementById(linkId) as HTMLLinkElement;
        if (!link) {
            link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.href = '/api/admin_icon_map.php?action=get_css';
            document.head.appendChild(link);
        } else {
            link.href = '/api/admin_icon_map.php?action=get_css';
        }
    }, [setIsCartOpen]);

    useEffect(() => {
        if (typeof window === 'undefined' || !isLoggedIn) return;
        if (!window.__WF_PENDING_CHECKOUT_AFTER_LOGIN) return;

        window.__WF_PENDING_CHECKOUT_AFTER_LOGIN = false;
        setTimeout(() => {
            if (typeof window.openPaymentModal === 'function') {
                window.openPaymentModal();
                return;
            }
            if (window.WF_PaymentModal?.open) {
                window.WF_PaymentModal.open();
                return;
            }
            window.showError?.('Login succeeded, but checkout could not open because payment modal APIs are missing.');
        }, 75);
    }, [isLoggedIn]);

    const {
        isOpen: isRoomOpen,
        currentRoom,
        content: roomContent,
        metadata: roomMetadata,
        background: roomBackground,
        panelColor: roomPanelColor,
        renderContext: roomRenderContext,
        targetAspectRatio: roomTargetAspectRatio,
        isLoading: isRoomLoading,
        openRoom,
        closeRoom
    } = useRoomManager();

    const {
        isOpen: isItemModalOpen,
        sku: itemModalSku,
        open: openItemModal,
        close: closeItemModal
    } = useItemModal();

    const {
        mode: authMode,
        setMode: setAuthMode,
        openProfileCompletion,
        close: closeAuthModal
    } = useAuthModal();

    useEffect(() => {
        if (!isLoggedIn || !user?.profile_completion_required) return;
        if (authMode === 'profile-completion') return;
        openProfileCompletion();
    }, [isLoggedIn, user?.profile_completion_required, authMode, openProfileCompletion]);

    const {
        shop_data,
        receipt_data,
        about_data,
        contact_data,
        site_settings,
        receipt_order_id,
        setReceiptOrderId,
        is_payment_modal_open,
        set_is_payment_modal_open,
        is_bare
    } = useSiteHydration();

    const { modal } = useModalContext();

    const handleCloseReceipt = () => {
        setReceiptOrderId(null);
        if (window.location.pathname.includes('/receipt')) {
            const lastRoom = localStorage.getItem('wf_last_room') || '0';
            window.location.href = `/room_main?room_id=${lastRoom}`;
            return;
        }
        const url = new URL(window.location.href);
        if (url.searchParams.has('order_id')) {
            url.searchParams.delete('order_id');
            window.history.replaceState({}, '', url.toString());
        }
    };

    const [searchParams] = useSearchParams();
    const section = searchParams.get('section') || '';
    const roomIdParam = searchParams.get('room_id');

    const isContextModalOpen = Boolean(modal?.isOpen);

    useAppEffects({
        isCartOpen,
        policyModalOpen: policyModal.isOpen,
        isPaymentModalOpen: Boolean(is_payment_modal_open),
        isRoomOpen,
        isItemModalOpen,
        receiptOrderId: receipt_order_id,
        isContextModalOpen,
        authMode,
        section,
        setAuthMode,
        isRoomLoading,
        openRoom,
        is_bare: Boolean(is_bare),
        SETTINGS_MODAL_SECTIONS
    });

    const pageAttr = document.body.getAttribute('data-page');

    if (!site_settings) {
        return <AdminLoading />;
    }

    const isLoginPath = window.location.pathname.includes('/login');
    const isPOS = pageAttr === 'admin/pos' ||
        window.location.pathname.includes('/pos') ||
        window.location.search.includes('section=pos');

    const isAdmin = pageAttr?.startsWith('admin');
    const containerClasses = `wf-app-container ${isAdmin ? 'flex flex-col h-screen min-h-0 overflow-hidden' : ''}`;

    const currentPath = window.location.pathname;
    const isLandingPageVisible = Boolean(!isPOS && !isAdmin && (
        roomIdParam === 'A' ||
        (!roomIdParam && (currentPath === '/' || currentPath === '/index.html') && !section)
    ));
    const isMainRoomVisible = Boolean(!isPOS && !isAdmin && (
        roomIdParam === '0' ||
        (currentPath.includes('/room_main') && !section)
    ));
    const isShopVisible = Boolean(!isPOS && shop_data && (
        currentPath.includes('/shop') || roomIdParam === 'S'
    ));

    return (
        <div className={containerClasses}>
            {!isPOS && !is_bare && <Header settings={site_settings} />}
            <SiteCoreBridge />
            <AnalyticsBridge />
            <NotificationBridge />
            <ModalBridge />

            <HeaderManager />
            <ContactManager businessData={contact_data || undefined} />

            <Suspense fallback={<AdminLoading />}>
                <MainPageRenderer
                    isLoginPath={isLoginPath}
                    pageAttr={pageAttr}
                    isMainRoomVisible={isMainRoomVisible}
                    isLandingPageVisible={isLandingPageVisible}
                    isShopVisible={isShopVisible}
                    isBare={Boolean(is_bare)}
                    roomIdParam={roomIdParam}
                    shopData={shop_data}
                    receiptData={receipt_data}
                    aboutData={about_data}
                    openItemModal={openItemModal}
                />

                <GlobalModalWrapper
                    authMode={authMode}
                    closeAuthModal={closeAuthModal}
                    isCartOpen={isCartOpen}
                    setIsCartOpen={setIsCartOpen}
                    policyModal={policyModal}
                    closePolicy={closePolicy}
                    isPaymentModalOpen={Boolean(is_payment_modal_open)}
                    setIsPaymentModalOpen={set_is_payment_modal_open}
                    isRoomOpen={isRoomOpen}
                    closeRoom={closeRoom}
                    currentRoom={currentRoom}
                    roomContent={roomContent}
                    roomMetadata={roomMetadata}
                    roomBackground={roomBackground}
                    roomPanelColor={roomPanelColor || ''}
                    roomRenderContext={roomRenderContext === 'fixed' ? 'modal' : roomRenderContext}
                    roomTargetAspectRatio={roomTargetAspectRatio}
                    isRoomLoading={isRoomLoading}
                    openItemModal={openItemModal}
                    isBare={Boolean(is_bare)}
                    itemModalSku={itemModalSku as string || ''}
                    isItemModalOpen={isItemModalOpen}
                    closeItemModal={closeItemModal}
                    receiptOrderId={receipt_order_id ? String(receipt_order_id) : null}
                    setReceiptOrderId={handleCloseReceipt}
                />

                {!pageAttr?.startsWith('admin') && !is_bare && !((pageAttr === 'landing' || window.location.pathname === '/') && !isLoggedIn && authMode === 'none') && (
                    <Footer
                        settings={site_settings}
                        isSlim={pageAttr === 'contact' || pageAttr === 'about'}
                    />
                )}
            </Suspense>

            <GlobalProcessingOverlay />
            <NotificationContainer />
            <div id="receipt-view-container"></div>
        </div>
    );
};
