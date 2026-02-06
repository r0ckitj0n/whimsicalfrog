import React, { useEffect, useState } from 'react';
import { useSquare } from '../../../../hooks/useSquare.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import { ENVIRONMENT } from '../../../../core/constants.js';
import { ISquareSettingsResponse } from '../../../../types/payment.js';
import logger from '../../../../core/logger.js';

interface SquarePaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
    total: number;
    onPaymentComplete: (token: string) => void;
}

export const SquarePaymentModal: React.FC<SquarePaymentModalProps> = ({
    isOpen,
    onClose,
    total,
    onPaymentComplete
}) => {
    const [settings, setSettings] = useState<any>(null);
    const [isProcessing, setIsProcessing] = useState(false);
    const [sdkError, setSdkError] = useState<string | null>(null);

    const {
        loadSDK,
        initializeCard,
        tokenize,
        destroy
    } = useSquare(
        settings?.applicationId,
        settings?.locationId,
        settings?.environment
    );

    useEffect(() => {
        const fetchSettings = async () => {
            try {
                const res = await ApiClient.get<ISquareSettingsResponse>('/api/square_settings.php?action=get_settings');
                if (res?.settings) {
                    const s = res.settings;
                    const env = s.square_environment || ENVIRONMENT.SANDBOX;
                    setSettings({
                        enabled: !!(s.square_enabled === '1' || s.square_enabled === 1 || s.square_enabled === true),
                        environment: env,
                        applicationId: env === ENVIRONMENT.PRODUCTION ? s.square_production_application_id : s.square_sandbox_application_id,
                        locationId: env === ENVIRONMENT.PRODUCTION ? s.square_production_location_id : s.square_sandbox_location_id
                    });
                }
            } catch (err) {
                logger.error('[SquarePaymentModal] Failed to load Square settings', err);
                setSdkError('Failed to load payment settings');
            }
        };
        if (isOpen) fetchSettings();
    }, [isOpen]);

    useEffect(() => {
        let isMounted = true;
        if (isOpen && settings?.enabled) {
            const init = async () => {
                try {
                    logger.info('[SquarePaymentModal] Starting SDK init');
                    const loaded = await loadSDK();
                    if (!isMounted) return;

                    if (loaded) {
                        // Small buffer to ensure DOM container is ready and SDK is evaluated
                        await new Promise(r => setTimeout(r, 100));
                        if (!isMounted) return;

                        logger.info('[SquarePaymentModal] Attempting to attach card');
                        await initializeCard('#pos-card-container');
                    }
                } catch (err) {
                    if (isMounted) {
                        logger.error('[SquarePaymentModal] SDK init failed', err);
                        setSdkError('Failed to initialize Square SDK');
                    }
                }
            };
            init();
        }
        return () => {
            isMounted = false;
            destroy();
        };
    }, [isOpen, settings?.enabled, settings?.applicationId, settings?.locationId, loadSDK, initializeCard]);

    const handlePayment = async () => {
        setIsProcessing(true);
        setSdkError(null);
        try {
            const token = await tokenize();
            if (token) {
                onPaymentComplete(token);
            } else {
                setSdkError('Failed to validate card');
            }
        } catch (err: unknown) {
            setSdkError(err instanceof Error ? err.message : 'Payment processing failed');
        } finally {
            setIsProcessing(false);
        }
    };

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
                            <div className="p-3 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded-2xl shadow-lg shadow-[var(--brand-primary)]/5">
                                <span className="text-3xl">💳</span>
                            </div>
                            <div>
                                <h2 className="text-2xl font-black text-gray-900 tracking-tight uppercase">Credit Card</h2>
                                <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Secure Checkout</div>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>

                    <div className="p-6 bg-gray-50 rounded-[2rem] border border-gray-100 space-y-4">
                        <div className="flex justify-between items-baseline">
                            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Amount</span>
                            <span className="text-3xl font-black text-[var(--brand-primary)] tracking-tighter">${total.toFixed(2)}</span>
                        </div>

                        <div className="pt-4 border-t border-gray-200">
                            <div id="pos-card-container" className="min-h-[100px] bg-white rounded-2xl p-4 shadow-inner border border-gray-200"></div>
                        </div>

                        {sdkError && (
                            <div className="p-3 bg-red-50 border border-red-100 rounded-xl text-red-500 text-xs font-bold text-center">
                                {sdkError}
                            </div>
                        )}
                    </div>

                    <button
                        onClick={handlePayment}
                        disabled={isProcessing || !settings?.enabled || !!sdkError}
                        className="btn btn-primary w-full py-6 rounded-[2rem] flex items-center justify-center gap-3 bg-[var(--brand-primary)] border-0 text-white hover:bg-[var(--brand-primary)]/90 active:scale-95 disabled:grayscale disabled:opacity-50 transition-all shadow-xl shadow-[var(--brand-primary)]/20"
                    >
                        <span className="text-lg font-black uppercase tracking-widest">
                            {isProcessing ? 'Processing...' : 'Charge Card'}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    );
};
