import { useState, useEffect } from 'react';
import { usePayment } from '../hooks/usePayment.js';
import { useAuthContext } from '../context/AuthContext.js';
import { useCustomers } from '../hooks/admin/useCustomers.js';
import { useCart } from '../hooks/use-cart.js';
import { useSquare } from '../hooks/useSquare.js';
import { ApiClient } from '../core/ApiClient.js';
import { ICustomerAddress } from '../types/admin/customers.js';
import { PAYMENT_METHOD, ENVIRONMENT } from '../core/constants.js';
import { ISquareSettings, ISquareSettingsResponse } from '../types/payment.js';

/**
 * Hook for managing payment modal state and actions.
 * Extracted from PaymentModal.tsx
 */
export const usePaymentModal = (isOpen: boolean, onClose: () => void) => {
    const { user } = useAuthContext();
    const { items } = useCart();
    const {
        isLoading,
        error,
        pricing,
        selected_address_id,
        setSelectedAddressId,
        shipping_method,
        setShippingMethod,
        payment_method,
        setPaymentMethod,
        placeOrder,
        setError
    } = usePayment();

    const { fetchCustomerAddresses } = useCustomers();
    const [addresses, setAddresses] = useState<ICustomerAddress[]>([]);
    const [isPlacingOrder, setIsPlacingOrder] = useState(false);
    const [squareSettings, setSquareSettings] = useState<ISquareSettings | null>(null);
    const {
        enabled: squareEnabled,
        applicationId: squareAppId,
        locationId: squareLocId,
        environment: squareEnv
    } = squareSettings || {};

    const {
        loadSDK,
        initializeCard,
        tokenize,
        destroy: destroySquare
    } = useSquare(
        squareAppId,
        squareLocId,
        squareEnv
    );

    useEffect(() => {
        const fetchSquareSettings = async () => {
            try {
                const res = await ApiClient.get<ISquareSettingsResponse>('/api/square_settings.php?action=get_settings');
                if (res?.settings) {
                    const s = res.settings;
                    const env = s.square_environment || ENVIRONMENT.SANDBOX;
                    setSquareSettings({
                        enabled: !!(s.square_enabled === '1' || s.square_enabled === 1 || s.square_enabled === true),
                        environment: env,
                        applicationId: env === ENVIRONMENT.PRODUCTION ? s.square_production_application_id : s.square_sandbox_application_id,
                        locationId: env === ENVIRONMENT.PRODUCTION ? s.square_production_location_id : s.square_sandbox_location_id
                    });
                }
            } catch (err) {
                console.warn('[PaymentModal] Failed to load Square settings', err);
            }
        };
        fetchSquareSettings();
    }, []);

    useEffect(() => {
        if (isOpen && payment_method === PAYMENT_METHOD.SQUARE && squareEnabled) {
            const init = async () => {
                await loadSDK();
                // Small buffer to ensure DOM container is ready, matching POS implementation
                await new Promise(r => setTimeout(r, 100));
                await initializeCard('#pm-card-container');
            };
            init();
        }
        return () => {
            if (payment_method === PAYMENT_METHOD.SQUARE) destroySquare();
        };
    }, [isOpen, payment_method, squareEnabled, squareAppId, squareLocId, loadSDK, initializeCard, destroySquare]);

    useEffect(() => {
        if (isOpen && user) {
            const loadAddresses = async () => {
                const addr = await fetchCustomerAddresses(user.id);
                setAddresses(addr);
                if (addr.length > 0 && !selected_address_id) {
                    const def = addr.find(a => a.is_default === true || a.is_default === 1);
                    setSelectedAddressId(def ? def.id : addr[0].id);
                }
            };
            loadAddresses();
        }
    }, [isOpen, user, fetchCustomerAddresses, selected_address_id, setSelectedAddressId]);


    const handlePlaceOrder = async () => {
        setIsPlacingOrder(true);
        let token: string | undefined;
        if (payment_method === PAYMENT_METHOD.SQUARE) {
            try {
                const selectedAddress = addresses.find(a => String(a.id) === String(selected_address_id ?? ''));
                const billingPostal = (selectedAddress?.zip_code || '').trim();
                const square_token = await tokenize(
                    billingPostal
                        ? {
                            billingContact: {
                                addressLines: [selectedAddress?.address_line_1, selectedAddress?.address_line_2].filter(Boolean) as string[],
                                city: selectedAddress?.city || '',
                                state: selectedAddress?.state || '',
                                postalCode: billingPostal,
                                countryCode: 'US'
                            }
                        }
                        : undefined
                );
                token = square_token ?? undefined;
            } catch (err: unknown) {
                const message = err instanceof Error ? err.message : 'Card validation failed';
                setError(message);
                setIsPlacingOrder(false);
                return;
            }
        }

        const res = await placeOrder(token);
        if (res.success) {
            onClose();
            if (window.showSuccess) {
                window.showSuccess('Order placed successfully! Opening receipt...');
            }
            setTimeout(() => {
                const orderId = String(res.order_id || '');
                if (!orderId) return;

                // Open receipt in-app to avoid full-page /receipt route issues.
                if (window.WF_ReceiptModal?.open) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('order_id', orderId);
                    window.history.pushState({}, '', url.toString());
                    window.WF_ReceiptModal.open(orderId);
                    return;
                }

                // Last-resort fallback if receipt bridge is unavailable.
                window.location.href = `/room_main?order_id=${encodeURIComponent(orderId)}`;
            }, 300);
        }
        setIsPlacingOrder(false);
    };

    return {
        user,
        items,
        isLoading,
        error,
        pricing,
        selected_address_id,
        setSelectedAddressId,
        shipping_method,
        setShippingMethod,
        payment_method,
        setPaymentMethod,
        addresses,
        isPlacingOrder,
        handlePlaceOrder
    };
};
