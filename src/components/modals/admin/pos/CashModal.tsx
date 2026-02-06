import React, { useMemo } from 'react';

import { PAYMENT_METHOD } from '../../../../core/constants.js';

interface CashModalProps {
    isOpen: boolean;
    onClose: () => void;
    total: number;
    cashReceived: string;
    setCashReceived: (val: string | ((prev: string) => string)) => void;
    onCheckout: (method: typeof PAYMENT_METHOD.CASH) => void;
}

export const CashModal: React.FC<CashModalProps> = ({
    isOpen,
    onClose,
    total,
    cashReceived,
    setCashReceived,
    onCheckout
}) => {
    const changeDue = useMemo(() => {
        const received = parseFloat(cashReceived) || 0;
        return Math.max(0, received - total);
    }, [cashReceived, total]);

    const smartDenominations = useMemo(() => {
        if (total <= 0) return [1, 5, 10, 20];

        const options = new Set<number>();
        options.add(total); // Exact change

        // Next whole dollar
        options.add(Math.ceil(total));

        // Next increments
        [5, 10, 20, 50, 100].forEach(inc => {
            options.add(Math.ceil(total / inc) * inc);
        });

        return Array.from(options)
            .filter(v => v >= total)
            .sort((a, b) => a - b)
            .slice(0, 4);
    }, [total]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200"
            onClick={onClose}
        >
            <div
                className="bg-white rounded-[3rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 slide-in-from-bottom-4 duration-300"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-10 space-y-8">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-[var(--brand-accent)]/10 text-[var(--brand-accent)] rounded-2xl shadow-lg shadow-[var(--brand-accent)]/5">
                                <div className="admin-action-btn btn-icon--shopping-cart text-3xl" data-help-id="pos-payment-icon" />
                            </div>
                            <div>
                                <h2 className="text-2xl font-black text-gray-900 tracking-tight uppercase">Cash Payment</h2>
                                <div className="text-[10px] font-black text-[var(--brand-accent)] uppercase tracking-widest">Calculate Change</div>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-8">
                        <div className="space-y-1">
                            <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Total Amount</div>
                            <div className="text-4xl font-black text-gray-900 tracking-tight">${total.toFixed(2)}</div>
                        </div>
                        <div className="space-y-1">
                            <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1 text-right">Change Due</div>
                            <div className={`text-4xl font-black tracking-tight text-right ${changeDue > 0 ? 'text-[var(--brand-accent)]' : 'text-gray-300'}`}>
                                ${changeDue.toFixed(2)}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="relative">
                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xl text-[var(--brand-primary)]/40">#</span>
                            <input
                                type="number"
                                value={cashReceived}
                                onChange={e => setCashReceived(e.target.value)}
                                placeholder="Amount Received..."
                                className="form-input w-full pl-14 py-6 rounded-[2rem] text-3xl font-black border-[var(--brand-primary)]/20 bg-gray-50 focus:bg-white focus:ring-8 focus:ring-[var(--brand-primary)]/10 transition-all shadow-inner"
                                autoFocus
                            />
                            {cashReceived && (
                                <button
                                    onClick={() => setCashReceived('')}
                                    className="admin-action-btn btn-icon--clear absolute right-4 top-1/2 -translate-y-1/2"
                                    data-help-id="pos-cash-clear"
                                />
                            )}
                        </div>

                        <div className="grid grid-cols-4 gap-2">
                            {smartDenominations.map(v => (
                                <button
                                    key={v}
                                    onClick={() => setCashReceived(v.toFixed(2))}
                                    className="py-3 bg-transparent border-0 hover:bg-[var(--brand-primary)]/5 text-gray-900 rounded-2xl text-[10px] font-black transition-all active:scale-95"
                                >
                                    ${v % 1 === 0 ? v : v.toFixed(2)}
                                </button>
                            ))}
                        </div>
                    </div>

                    <button
                        onClick={() => onCheckout(PAYMENT_METHOD.CASH)}
                        disabled={parseFloat(cashReceived) < total}
                        className="btn btn-primary w-full py-6 rounded-[2rem] flex items-center justify-center gap-3 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 active:scale-95 disabled:grayscale disabled:opacity-50"
                    >
                        <span className="text-lg font-black uppercase tracking-widest">Complete Sale</span>
                    </button>
                </div>
            </div>
        </div>
    );
};
