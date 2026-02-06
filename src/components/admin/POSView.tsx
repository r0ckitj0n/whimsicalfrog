import React, { useState, useEffect, useMemo } from 'react';
import { usePOS } from '../../hooks/admin/usePOS.js';
import { useSiteHydration } from '../../hooks/useSiteHydration.js';
import { RegisterHeader } from './pos/RegisterHeader.js';
import { ItemGrid } from './pos/ItemGrid.js';
import { ActiveCart } from './pos/ActiveCart.js';
import { CashModal } from '../modals/admin/pos/CashModal.js';
import { SquarePaymentModal } from '../modals/admin/pos/SquarePaymentModal.js';
import { ItemDetailsModal } from '../modals/ItemDetailsModal.js';

export const POSView: React.FC = () => {
    const {
        isLoading,
        items,
        cartItems,
        pricing,
        addToCart,
        removeFromCart,
        updateQuantity,
        applyCoupon,
        removeCoupon,
        processCheckout,
    } = usePOS();

    const { setReceiptOrderId } = useSiteHydration();
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const [coupon_code, setCouponCode] = useState('');
    const [cashModalOpen, setCashModalOpen] = useState(false);
    const [cashReceived, setCashReceived] = useState('');
    const [itemModalOpen, setItemModalOpen] = useState(false);
    const [selectedSku, setSelectedSku] = useState('');
    const [squareModalOpen, setSquareModalOpen] = useState(false);

    const categories = useMemo(() => Array.from(new Set(items.map(i => i.category).filter(Boolean))), [items]);

    const filteredItems = useMemo(() => items.filter(item => {
        const matchesSearch = !searchQuery ||
            item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            item.sku.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesCategory = !selectedCategory || item.category === selectedCategory;
        return matchesSearch && matchesCategory;
    }), [items, searchQuery, selectedCategory]);

    // Keyboard Shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('posSearchInput')?.focus();
            }
            if (e.key === 'F2') {
                e.preventDefault();
                setSearchQuery('');
                setSelectedCategory('');
            }
            if (e.key === 'F9') {
                e.preventDefault();
                if (cartItems.length > 0) setCashModalOpen(true);
            }
            if (e.key === 'Escape') {
                if (cashModalOpen) setCashModalOpen(false);
                if (squareModalOpen) setSquareModalOpen(false);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [cartItems.length, cashModalOpen]);

    const handleCheckout = async (method: string, token?: string) => {
        if (method === 'Square' && !token) {
            setSquareModalOpen(true);
            return;
        }

        const res = await processCheckout(method, token, parseFloat(cashReceived) || 0);
        if (res.success) {
            if (window.WFToast) window.WFToast.success(`Sale complete! Order #${res.order_id}`);
            setCashModalOpen(false);
            setSquareModalOpen(false);
            setCashReceived('');

            // Show receipt in modal instead of redirecting
            if (res.order_id) {
                setReceiptOrderId(String(res.order_id));
            }
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Checkout failed');
        }
    };

    const handleShowDetails = (sku: string) => {
        setSelectedSku(sku);
        setItemModalOpen(true);
    };

    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable full-screen mode: ${err.message}`);
            });
        } else {
            document.exitFullscreen();
        }
    };

    if (isLoading && items.length === 0) {
        return (
            <div className="h-screen w-screen flex flex-col items-center justify-center bg-gray-900 text-gray-400 gap-4">
                <span className="wf-emoji-loader text-6xl opacity-20">💰</span>
                <p className="text-lg font-black uppercase tracking-widest italic">Powering up the Register...</p>
            </div>
        );
    }

    return (
        <div className="pos-container h-screen flex flex-col bg-gray-900 overflow-hidden font-sans">
            <RegisterHeader onToggleFullscreen={toggleFullscreen} />

            <main className="pos-main flex-1 flex overflow-hidden">
                <div className="pos-left flex-[2] flex flex-col min-h-0 overflow-hidden">
                    <ItemGrid
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        selectedCategory={selectedCategory}
                        setSelectedCategory={setSelectedCategory}
                        categories={categories}
                        filteredItems={filteredItems}
                        onShowDetails={handleShowDetails}
                    />
                </div>

                <div className="pos-right flex-1 flex flex-col min-h-0 overflow-hidden bg-white border-l">
                    <ActiveCart
                        cartItems={cartItems}
                        pricing={pricing}
                        onUpdateQuantity={updateQuantity}
                        onRemoveFromCart={removeFromCart}
                        onClearCart={() => { }} // Hook needs clear method
                        onApplyCoupon={applyCoupon}
                        onRemoveCoupon={removeCoupon}
                        onCheckout={handleCheckout}
                        onOpenCashModal={() => setCashModalOpen(true)}
                        coupon_code={coupon_code}
                        setCouponCode={setCouponCode}
                        isLoading={isLoading}
                    />
                </div>
            </main>

            <CashModal
                isOpen={cashModalOpen}
                onClose={() => setCashModalOpen(false)}
                total={pricing?.total || 0}
                cashReceived={cashReceived}
                setCashReceived={setCashReceived}
                onCheckout={handleCheckout}
            />

            <SquarePaymentModal
                isOpen={squareModalOpen}
                onClose={() => setSquareModalOpen(false)}
                total={pricing?.total || 0}
                onPaymentComplete={(token) => handleCheckout('Square', token)}
            />

            <ItemDetailsModal
                sku={selectedSku}
                isOpen={itemModalOpen}
                onClose={() => setItemModalOpen(false)}
            />

        </div>
    );
};

export default POSView;
