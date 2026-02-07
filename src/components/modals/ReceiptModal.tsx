import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { ApiClient } from '../../core/ApiClient.js';
import { ReceiptView } from '../storefront/ReceiptView.js';
import { IReceiptData } from '../../types/index.js';

interface ReceiptModalProps {
    order_id: string | number | null;
    isOpen: boolean;
    onClose: () => void;
}

/**
 * ReceiptModal v1.3.0
 * Fetches JSON data and renders the ReceiptView component directly.
 */
export const ReceiptModal: React.FC<ReceiptModalProps> = ({ order_id, isOpen, onClose }) => {
    const [receiptData, setReceiptData] = useState<IReceiptData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && order_id) {
            const loadReceipt = async () => {
                setIsLoading(true);
                setError(null);
                try {
                    const url = `/api/pos_receipt.php?order_id=${encodeURIComponent(order_id)}&_t=${Date.now()}`;
                    const res = await ApiClient.get<any>(url);

                    // The JsonResponseParser may flatten {success:true, data:{...}} into {success:true, ...data}
                    const receipt = res?.data || (res?.success ? res : null);

                    if (receipt && (receipt.items || receipt.order_id)) {
                        setReceiptData(receipt as IReceiptData);
                    } else {
                        throw new Error('Failed to load receipt data');
                    }
                } catch (err) {
                    console.error('[ReceiptModal] Failed to load receipt', err);
                    const msg = err instanceof Error ? err.message : 'Unknown error';
                    setError(`CRITICAL_LOAD_ERROR: ${msg}`);
                } finally {
                    setIsLoading(false);
                }
            };
            loadReceipt();
        } else if (!isOpen) {
            setReceiptData(null);
            setError(null);
        }
    }, [isOpen, order_id]);

    useEffect(() => {
        if (isOpen) {
            // Ensure data-page is set to receipt for proper ReceiptView rendering logic
            if (!document.body.getAttribute('data-page')) {
                document.body.setAttribute('data-page', 'receipt');
            }
        }
    }, [isOpen]);

    if (!isOpen) return null;

    const handlePrint = () => {
        window.print();
    };

    const modalContent = (
        <div
            className="wf-modal-overlay show receipt-modal print:hidden"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 'var(--wf-z-modal)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(8px)',
                width: '100vw',
                height: '100vh',
                padding: '2.5vh 2.5vw',
                boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className="wf-modal-card my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 overflow-hidden flex flex-col"
                style={{
                    maxWidth: '800px',
                    width: '100%',
                    maxHeight: '100%',
                    backgroundColor: 'white',
                    borderRadius: '24px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
                    position: 'relative'
                }}
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="wf-modal-header" style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '1.25rem 2rem',
                    background: '#faf8f5',
                    borderBottom: '1px solid #e5e2dc',
                    flexShrink: 0
                }}>
                    <h2 style={{
                        margin: 0,
                        color: '#374151',
                        fontFamily: "'Merienda', cursive",
                        fontSize: '1.5rem',
                        fontWeight: 700,
                        fontStyle: 'italic'
                    }}>
                        Order Receipt
                    </h2>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handlePrint}
                            className="admin-action-btn btn-icon--print"
                            data-help-id="orders-action-print"
                            aria-label="Print receipt"
                            title="Print"
                        />
                        <button
                            type="button"
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                            aria-label="Close receipt"
                            title="Close"
                        />
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-0 relative">
                    {isLoading ? (
                        <div className="p-24 flex flex-col items-center justify-center gap-4">
                            <div className="animate-spin text-[var(--brand-accent)]">
                                <span className="text-4xl">⏳</span>
                            </div>
                            <p className="text-gray-500 font-medium italic">Generating your receipt...</p>
                        </div>
                    ) : error ? (
                        <div className="p-24 text-center">
                            <p className="text-[var(--brand-error)] font-bold">{error}</p>
                            <button onClick={onClose} className="mt-4 text-brand-primary underline">Close</button>
                        </div>
                    ) : receiptData ? (
                        <div className="receipt-content-wrapper">
                            <ReceiptView data={receiptData} />
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default ReceiptModal;
