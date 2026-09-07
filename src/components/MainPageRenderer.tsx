import React, { lazy } from 'react';
import type { IShopData, IReceiptData, IAboutData } from '../types/index.js';
import { MainRoom } from './MainRoom.js';
import { LandingPage } from './LandingPage.js';
import { ShopView } from './storefront/ShopView.js';
import { ProductDetailView } from './storefront/ProductDetailView.js';
import { ReceiptView } from './storefront/ReceiptView.js';
import { AboutView } from './storefront/AboutView.js';
import { PageLoadingFallback } from './ui/PageLoadingFallback.js';

const AdminAuthGuard = lazy(() => import('./admin/AdminAuthGuard.js').then(m => ({ default: m.AdminAuthGuard })));
const AdminConductor = lazy(() => import('./admin/AdminConductor.js').then(m => ({ default: m.AdminConductor })));

interface MainPageRendererProps {
    isLoginPath: boolean;
    pageAttr: string | null;
    isMainRoomVisible: boolean;
    isLandingPageVisible: boolean;
    isShopVisible: boolean;
    isProductVisible: boolean;
    isBare: boolean;
    roomIdParam: string | null;
    shopData: IShopData | null;
    receiptData: IReceiptData | null;
    aboutData: IAboutData | null;
    openItemModal: (sku: string) => void;
}

export const MainPageRenderer: React.FC<MainPageRendererProps> = ({
    isLoginPath,
    pageAttr,
    isMainRoomVisible,
    isLandingPageVisible,
    isShopVisible,
    isProductVisible,
    isBare,
    roomIdParam,
    shopData,
    receiptData,
    aboutData,
    openItemModal
}) => {
    if (isLoginPath) return null;

    return (
        <>
            {pageAttr?.startsWith('admin') && (
                <AdminAuthGuard>
                    <AdminConductor />
                </AdminAuthGuard>
            )}
            {(isMainRoomVisible || (isBare && roomIdParam === '0')) && (
                <MainRoom />
            )}
            {(isLandingPageVisible || (isBare && roomIdParam === 'A')) && (
                <LandingPage />
            )}
            {(isShopVisible || (isBare && roomIdParam === 'S')) && (
                shopData ? (
                    <ShopView
                        categories={shopData.categories}
                        current_page={shopData.current_page}
                        onOpenItem={openItemModal}
                    />
                ) : (
                    <PageLoadingFallback />
                )
            )}
            {isProductVisible && (
                shopData ? (
                    <ProductDetailView
                        categories={shopData.categories}
                        onOpenItem={openItemModal}
                    />
                ) : (
                    <PageLoadingFallback />
                )
            )}
            {receiptData && <ReceiptView data={receiptData} />}
            {aboutData && !isBare && <AboutView data={aboutData} />}
        </>
    );
};
