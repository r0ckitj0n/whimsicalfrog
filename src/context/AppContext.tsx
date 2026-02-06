import React, { createContext, useContext, useState, useCallback } from 'react';
import { AuthModalMode } from '../hooks/useAuthModal.js';

export interface ITooltipData {
    id: number;
    element_id: string;
    page_context: string;
    title: string;
    content: string;
    position: 'top' | 'bottom' | 'left' | 'right';
    is_active: boolean;
}

export interface ActiveTooltip {
    target: HTMLElement;
    data: ITooltipData;
}

interface RoomMetadata {
    room_name?: string;
    category?: string;
    room_number?: string | number;
}

interface RoomBackground {
    webp_filename?: string;
    image_filename?: string;
}

interface RoomState {
    isOpen: boolean;
    currentRoom: string | null;
    isLoading: boolean;
    content: string;
    metadata: RoomMetadata;
    background: RoomBackground | null;
    panelColor?: string;
    renderContext: 'modal' | 'fullscreen' | 'fixed';
    targetAspectRatio: number | null;
}

interface AppContextType {
    isCartOpen: boolean;
    setIsCartOpen: (open: boolean) => void;
    policyModal: { isOpen: boolean; url: string; label: string };
    openPolicy: (url: string, label: string) => void;
    closePolicy: () => void;
    // Auth Modal State
    authMode: AuthModalMode;
    setAuthMode: (mode: AuthModalMode) => void;
    authReturnTo: string | null;
    setAuthReturnTo: (url: string | null) => void;
    authTriggerRect: DOMRect | null;
    setAuthTriggerRect: (rect: DOMRect | null) => void;
    // Room Modal State
    roomState: RoomState;
    setRoomState: React.Dispatch<React.SetStateAction<RoomState>>;
    // Hints State
    hintsEnabled: boolean;
    toggleHints: () => void;
    tooltips: ITooltipData[];
    setTooltips: React.Dispatch<React.SetStateAction<ITooltipData[]>>;
    activeTooltip: ActiveTooltip | null;
    setActiveTooltip: React.Dispatch<React.SetStateAction<ActiveTooltip | null>>;
    // Receipt State
    receiptOrderId: string | number | null;
    setReceiptOrderId: (id: string | number | null) => void;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

export const AppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [policyModal, setPolicyModal] = useState({
        isOpen: false,
        url: '',
        label: ''
    });

    const [authMode, setAuthMode] = useState<AuthModalMode>('none');
    const [authReturnTo, setAuthReturnTo] = useState<string | null>(null);
    const [authTriggerRect, setAuthTriggerRect] = useState<DOMRect | null>(null);

    const [roomState, setRoomState] = useState<RoomState>({
        isOpen: false,
        currentRoom: null,
        isLoading: false,
        content: '',
        metadata: {},
        background: null,
        panelColor: '',
        renderContext: 'modal',
        targetAspectRatio: null
    });

    const [receiptOrderId, setReceiptOrderId] = useState<string | number | null>(null);

    const [tooltips, setTooltips] = useState<ITooltipData[]>([]);
    const [activeTooltip, setActiveTooltip] = useState<ActiveTooltip | null>(null);

    const [hintsEnabled, setHintsEnabled] = useState(() => {
        if (typeof window === 'undefined') return true;
        const saved = localStorage.getItem('wf_tooltips_enabled');
        // Explicitly default to true if null, or if it's not the string 'false'
        return saved === null || saved === 'true';
    });

    const toggleHints = useCallback(() => {
        setHintsEnabled(prev => {
            const next = !prev;
            localStorage.setItem('wf_tooltips_enabled', String(next));
            if (next) {
                sessionStorage.setItem('wf_tooltips_session_enabled', 'true');
            } else {
                sessionStorage.removeItem('wf_tooltips_session_enabled');
            }
            return next;
        });
    }, []);

    const openPolicy = useCallback((url: string, label: string) => {
        setPolicyModal({ isOpen: true, url, label });
    }, []);

    const closePolicy = useCallback(() => {
        setPolicyModal(prev => ({ ...prev, isOpen: false }));
    }, []);

    return (
        <AppContext.Provider value={{
            isCartOpen,
            setIsCartOpen,
            policyModal,
            openPolicy,
            closePolicy,
            authMode,
            setAuthMode,
            authReturnTo,
            setAuthReturnTo,
            authTriggerRect,
            setAuthTriggerRect,
            roomState,
            setRoomState,
            hintsEnabled,
            toggleHints,
            tooltips,
            setTooltips,
            activeTooltip,
            setActiveTooltip,
            receiptOrderId: receiptOrderId,
            setReceiptOrderId: setReceiptOrderId
        }}>
            {children}
        </AppContext.Provider>
    );
};

export const useApp = () => {
    const context = useContext(AppContext);
    if (!context) {
        throw new Error('useApp must be used within an AppProvider');
    }
    return context;
};

export default AppContext;
