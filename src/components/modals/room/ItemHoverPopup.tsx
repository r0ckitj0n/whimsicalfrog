import React from 'react';

interface ItemPopupState {
    visible: boolean;
    sku: string;
    name: string;
    price: string | number;
    image: string;
    stock: number;
    x: number;
    y: number;
}

interface ItemHoverPopupProps {
    popup: ItemPopupState;
    onOpenItem?: (sku: string, data?: ItemPopupState) => void;
    setPopup: (popup: ItemPopupState | null) => void;
}

export const ItemHoverPopup: React.FC<ItemHoverPopupProps> = ({ popup, onOpenItem, setPopup }) => {
    const isSoldOut = popup.stock <= 0;

    return (
        <div
            className={`item-hover-popup ${isSoldOut ? 'sold-out' : ''}`}
            onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                if (onOpenItem) onOpenItem(popup.sku, popup);
                else window.dispatchEvent(new CustomEvent('wf:item:open', { detail: { sku: popup.sku, data: popup } }));
                setPopup(null);
            }}
            style={{
                position: 'fixed',
                left: `${popup.x}px`,
                top: `${popup.y}px`,
                transform: popup.y < window.innerHeight / 2 ? 'translate(-50%, 0)' : 'translate(-50%, -100%)',
                background: 'white',
                padding: '12px',
                borderRadius: '12px',
                boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.2)',
                zIndex: 'var(--wf-z-modal-elevated)',
                width: '200px',
                textAlign: 'center',
                border: '1px solid rgba(0,0,0,0.1)',
                pointerEvents: 'auto',
                cursor: 'pointer'
            }}
        >
            <div style={{ position: 'relative' }}>
                <img
                    src={popup.image}
                    alt={popup.name}
                    style={{
                        width: '100%',
                        height: 'auto',
                        maxHeight: '150px',
                        marginBottom: '8px',
                        borderRadius: '8px',
                        objectFit: 'contain',
                        filter: isSoldOut ? 'grayscale(1)' : 'none',
                        opacity: isSoldOut ? 0.7 : 1
                    }}
                />
                {isSoldOut && (
                    <div style={{
                        position: 'absolute',
                        top: '50%',
                        left: '50%',
                        transform: 'translate(-50%, -50%) rotate(-15deg)',
                        background: 'rgba(255, 0, 0, 0.8)',
                        color: 'white',
                        padding: '4px 12px',
                        borderRadius: '4px',
                        fontWeight: 'bold',
                        fontSize: '14px',
                        textTransform: 'uppercase',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
                        whiteSpace: 'nowrap',
                        pointerEvents: 'none'
                    }}>
                        Sold Out
                    </div>
                )}
            </div>
            <div style={{ fontWeight: 'bold', fontSize: '15px', marginBottom: '4px', color: '#333' }}>{popup.name}</div>
            <div style={{ color: 'var(--brand-primary)', fontWeight: 'bold', fontSize: '16px' }}>${popup.price}</div>
            <div style={{ marginTop: '8px', fontSize: '10px', color: '#999', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Click for Details</div>
        </div>
    );
};
