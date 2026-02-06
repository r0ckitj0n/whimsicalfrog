import { useState, useCallback } from 'react';
import { IModalState, IModalOptions } from '../types/modals.js';

export const useModals = () => {
    const [modal, setModal] = useState<IModalState | null>(null);

    const show = useCallback(<T = unknown>(options: IModalOptions = {}): Promise<T> => {
        return new Promise((resolve) => {
            setModal({
                ...options,
                isOpen: true,
                resolve: resolve as (value: unknown) => void
            });
        });
    }, []);

    const close = useCallback((value?: unknown) => {
        if (!modal) return;
        
        setModal(prev => prev ? { ...prev, isOpen: false } : null);
        
        // Delay resolution to allow closing animation if any
        setTimeout(() => {
            modal.resolve(value);
            setModal(null);
        }, 300);
    }, [modal]);

    const confirm = useCallback((options: IModalOptions): Promise<boolean> => {
        return show<boolean>({ ...options, mode: 'confirm' });
    }, [show]);

    const alert = useCallback((options: IModalOptions): Promise<void> => {
        return show<void>({ ...options, mode: 'alert', showCancel: false });
    }, [show]);

    const prompt = useCallback((options: IModalOptions): Promise<string | null> => {
        return show<string | null>({ ...options, mode: 'prompt' });
    }, [show]);

    return {
        modal,
        show,
        close,
        confirm,
        alert,
        prompt
    };
};
