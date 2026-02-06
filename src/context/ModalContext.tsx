import React, { createContext, useContext, ReactNode, useMemo } from 'react';
import { useModals } from '../hooks/useModals.js';
import { IModalState, IModalOptions } from '../types/modals.js';

interface ModalContextType {
    modal: IModalState | null;
    show: (options?: IModalOptions) => Promise<unknown>;
    close: (value?: unknown) => void;
    confirm: (options: IModalOptions) => Promise<boolean>;
    alert: (options: IModalOptions) => Promise<void>;
    prompt: (options: IModalOptions) => Promise<string | null>;
}

const ModalContext = createContext<ModalContextType | undefined>(undefined);


export const ModalProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
    const modalHandlers = useModals();

    const value = useMemo(() => ({
        ...modalHandlers
    }), [modalHandlers]);

    return (
        <ModalContext.Provider value={value}>
            {children}
        </ModalContext.Provider>
    );
};

export const useModalContext = () => {
    const context = useContext(ModalContext);
    if (!context) {
        throw new Error('useModalContext must be used within a ModalProvider');
    }
    return context;
};
