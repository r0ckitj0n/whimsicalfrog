import { useEffect, useCallback } from 'react';

interface IItemPopupState {
    visible: boolean;
    sku: string;
    name: string;
    price: string | number;
    image: string;
    stock: number;
    x: number;
    y: number;
}

interface UseRoomModalEffectsProps {
    isOpen: boolean;
    content: string;
    bodyRef: React.RefObject<HTMLDivElement>;
    popup: IItemPopupState | null;
    setPopup: (popup: IItemPopupState | null) => void;
    onClose: () => void;
    onOpenItem?: (sku: string, data?: IItemPopupState) => void;
}

export const useRoomModalEffects = ({
    isOpen,
    content,
    bodyRef,
    popup,
    setPopup,
    onClose,
    onOpenItem
}: UseRoomModalEffectsProps) => {

    const handleMouseOver = useCallback((e: MouseEvent) => {
        const target = e.target as HTMLElement;
        const itemEl = target.closest('.room-item') as HTMLElement;

        if (itemEl && itemEl.dataset.item) {
            try {
                const data = JSON.parse(itemEl.dataset.item);
                const rect = itemEl.getBoundingClientRect();
                const popupWidth = 200;
                const estimatedPopupHeight = 250;
                const padding = 15;

                let x = rect.left + rect.width / 2;
                let y = rect.top - padding;

                if (x - popupWidth / 2 < padding) x = popupWidth / 2 + padding;
                else if (x + popupWidth / 2 > window.innerWidth - padding) x = window.innerWidth - popupWidth / 2 - padding;

                if (y - estimatedPopupHeight < padding) {
                    y = rect.bottom + padding;
                    if (y + estimatedPopupHeight > window.innerHeight - padding) y = window.innerHeight - estimatedPopupHeight - padding;
                }

                setPopup({
                    visible: true,
                    sku: data.sku,
                    name: data.name,
                    price: data.price,
                    image: data.image,
                    stock: typeof data.stock_quantity !== 'undefined' ? Number(data.stock_quantity) : 1,
                    x: x,
                    y: y
                });
            } catch (err) { /* Popup position calculation failed */ }
        }
    }, [setPopup]);

    const handleMouseOut = useCallback((e: MouseEvent) => {
        const related = e.relatedTarget as HTMLElement;
        if (related && (related.closest('.room-item') || related.closest('.item-hover-popup'))) return;
        setPopup(null);
    }, [setPopup]);

    const handleClick = useCallback((e: MouseEvent) => {
        const target = e.target as HTMLElement;
        const itemEl = target.closest('.room-item') as HTMLElement;
        const popupEl = target.closest('.item-hover-popup') as HTMLElement;

        if (!itemEl && !popupEl) return;

        if (itemEl?.tagName === 'A' && !itemEl.dataset.action) {
            return;
        }

        const action = itemEl?.dataset.action || '';
        const roomTarget = itemEl?.dataset.room_number || itemEl?.dataset.room;

        if (roomTarget || action === 'openRoom') {
            e.preventDefault();
            e.stopPropagation();
            const targetRoom = roomTarget || itemEl?.dataset.room || '';
            if (targetRoom) {
                onClose();
                const fullPageRoomUrls: Record<string, string> = {
                    'A': '/',
                    '0': '/room_main',
                    'S': '/shop',
                    'X': '/admin/settings'
                };

                if (fullPageRoomUrls[targetRoom]) {
                    window.location.href = fullPageRoomUrls[targetRoom];
                } else {
                    if (window.roomModalManager?.show) {
                        setTimeout(() => window.roomModalManager?.show(targetRoom), 100);
                    } else if (window.openRoom) {
                        const openRoom = window.openRoom;
                        setTimeout(() => openRoom?.(targetRoom), 100);
                    } else {
                        window.location.href = `/room_main?room=${targetRoom}`;
                    }
                }
            }
            return;
        }

        if (action === 'navigateToCategory') {
            e.preventDefault();
            e.stopPropagation();
            const categoryId = itemEl?.dataset.categoryId || '';
            if (categoryId) {
                window.location.href = `/shop?category=${categoryId}`;
            }
            return;
        }

        if (action === 'openModal') {
            e.preventDefault();
            e.stopPropagation();
            const modalId = itemEl?.dataset.modalId || '';
            if (modalId) {
                const normalizedId = modalId.replace(/^modal-/, '');

                if (normalizedId === 'cart' && typeof window.openCartModal === 'function') {
                    window.openCartModal();
                } else if (normalizedId === 'login' && typeof window.openLoginModal === 'function') {
                    window.openLoginModal();
                } else if (normalizedId === 'account-settings' && typeof window.openAccountSettings === 'function') {
                    window.openAccountSettings?.();
                } else if (normalizedId === 'payment' && window.WF_PaymentModal?.open) {
                    window.WF_PaymentModal.open();
                } else if (normalizedId === 'contact') {
                    window.location.href = '/contact';
                } else if (typeof window.showModal === 'function') {
                    window.showModal(modalId);
                }
            }
            return;
        }

        if (action && action !== 'openItemModal' && !action.startsWith('navigate')) {
            e.preventDefault();
            e.stopPropagation();

            switch (action) {
                case 'open-cart':
                    if (typeof window.openCartModal === 'function') window.openCartModal();
                    break;
                case 'open-login':
                    if (typeof window.openLoginModal === 'function') window.openLoginModal();
                    break;
                case 'open-account-settings':
                    if (typeof window.openAccountSettings === 'function') window.openAccountSettings?.();
                    break;
                case 'go-back':
                case 'action-go-back':
                    window.history.back();
                    break;
                case 'go-forward':
                case 'action-go-forward':
                    window.history.forward();
                    break;
                case 'go-home':
                case 'action-go-home':
                    window.location.href = '/';
                    break;
                case 'go-shop':
                case 'action-go-shop':
                    window.location.href = '/shop';
                    break;
                case 'scroll-to-top':
                case 'action-scroll-to-top':
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    break;
                case 'refresh-page':
                case 'action-refresh-page':
                    window.location.reload();
                    break;
                default:
                    if (typeof window.performAction === 'function') {
                        window.performAction(action);
                    }
            }
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        let sku = '';
        let data = null;

        if (itemEl) {
            sku = itemEl.dataset.sku || '';
            try {
                data = itemEl.dataset.item ? JSON.parse(itemEl.dataset.item) : { sku };
            } catch (err) {
                data = { sku };
            }
        } else if (popupEl && popup) {
            sku = popup.sku;
            data = { sku: popup.sku, name: popup.name, price: popup.price, image: popup.image };
        }

        if (sku) {
            if (onOpenItem) onOpenItem(sku, data);
            else window.showGlobalItemModal?.(sku, data);
            setPopup(null);
        }
    }, [onClose, onOpenItem, popup, setPopup]);

    useEffect(() => {
        if (!isOpen || !bodyRef.current) return;

        const body = bodyRef.current;

        const scripts = body.querySelectorAll('script');
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            if (script.src) {
                newScript.src = script.src;
            } else {
                newScript.textContent = script.textContent;
            }
            document.head.appendChild(newScript).parentNode?.removeChild(newScript);
        });

        body.querySelectorAll('img').forEach(img => {
            const sku = (img.closest('[data-sku]') as HTMLElement)?.dataset.sku;
            window.setupImageErrorHandling?.(img as HTMLImageElement, sku);
        });

        body.addEventListener('mouseover', handleMouseOver);
        body.addEventListener('mouseout', handleMouseOut);
        body.addEventListener('click', handleClick);

        return () => {
            body.removeEventListener('mouseover', handleMouseOver);
            body.removeEventListener('mouseout', handleMouseOut);
            body.removeEventListener('click', handleClick);
        };
    }, [isOpen, content, handleMouseOver, handleMouseOut, handleClick, bodyRef]);

};
