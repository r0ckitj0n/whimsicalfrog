import { useEffect } from 'react';
import { initSelectAutoSort } from '../core/select-auto-sort.js';
import { initBodyBackground } from '../core/body-background.js';
import { initRoomIcons, startRoomIconsObserver } from '../core/room-icons.js';
import { ADMIN_SECTION } from '../core/constants.js';

import { AuthModalMode } from './useAuthModal.js';

interface UseAppEffectsProps {
    isCartOpen: boolean;
    policyModalOpen: boolean;
    isPaymentModalOpen: boolean;
    isRoomOpen: boolean;
    isItemModalOpen: boolean;
    receiptOrderId: string | number | null;
    isContextModalOpen: boolean;
    authMode: AuthModalMode;
    section: string;
    setAuthMode: (mode: AuthModalMode) => void;
    isRoomLoading: boolean;
    openRoom: (roomId: string) => void;
    is_bare: boolean;
    SETTINGS_MODAL_SECTIONS: readonly string[];
}

export const useAppEffects = ({
    isCartOpen,
    policyModalOpen,
    isPaymentModalOpen,
    isRoomOpen,
    isItemModalOpen,
    receiptOrderId,
    isContextModalOpen,
    authMode,
    section,
    setAuthMode,
    isRoomLoading,
    openRoom,
    is_bare,
    SETTINGS_MODAL_SECTIONS
}: UseAppEffectsProps) => {
    // 1. Centralized Body Class Management for Modals
    useEffect(() => {
        const isSettingsModal = SETTINGS_MODAL_SECTIONS.includes(section);
        const isCategoriesModal = section === ADMIN_SECTION.CATEGORIES;
        const isAuthModal = authMode !== 'none';

        const anyModalOpen =
            isCartOpen ||
            policyModalOpen ||
            isPaymentModalOpen ||
            isRoomOpen ||
            isItemModalOpen ||
            !!receiptOrderId ||
            isContextModalOpen ||
            isAuthModal ||
            isSettingsModal ||
            isCategoriesModal;

        if (anyModalOpen) {
            document.body.classList.add('wf-modal-open');
        } else {
            document.body.classList.remove('wf-modal-open');
        }

        return () => {
            document.body.classList.remove('wf-modal-open');
        };
    }, [
        isCartOpen,
        policyModalOpen,
        isPaymentModalOpen,
        isRoomOpen,
        isItemModalOpen,
        receiptOrderId,
        isContextModalOpen,
        authMode,
        section,
        SETTINGS_MODAL_SECTIONS
    ]);

    // 2. Deep Linking / Routing Logic
    useEffect(() => {
        const path = window.location.pathname.toLowerCase();
        if (path.includes('/login') && authMode === 'none') {
            setAuthMode('login');
        }

        const params = new URLSearchParams(window.location.search);
        const roomId = params.get('room_id') || params.get('room');
        if (roomId && !isRoomOpen && !isRoomLoading) {
            const fullPageRoomUrls: Record<string, string> = {
                'A': '/',           // Landing page
                '0': '/room_main',  // Main room
                'S': '/shop',       // Shop
                'X': '/admin/settings' // Settings
            };

            if (fullPageRoomUrls[roomId] && !is_bare) {
                window.location.href = fullPageRoomUrls[roomId];
            } else if (!is_bare || !fullPageRoomUrls[roomId]) {
                openRoom(roomId);
            }
        }
    }, [authMode, setAuthMode, isRoomOpen, isRoomLoading, openRoom, is_bare]);

    // 3. Core Initialization
    useEffect(() => {
        initSelectAutoSort();
        initBodyBackground();
        initRoomIcons();
        const observer = startRoomIconsObserver();
        return () => {
            if (observer) observer.disconnect();
        };
    }, []);
};
