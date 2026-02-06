import { useState, useCallback } from 'react';

export const useItemModal = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [sku, setSku] = useState('');

    const open = useCallback((targetSku: string) => {
        if (!targetSku) return;
        setSku(targetSku);
        setIsOpen(true);
    }, []);

    const close = useCallback(() => {
        setIsOpen(false);
        // Don't clear SKU immediately to avoid flash of empty modal during exit animation
    }, []);

    return {
        isOpen,
        sku,
        open,
        close
    };
};
