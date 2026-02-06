import React, { lazy } from 'react';
import type { IShopData, IReceiptData, IAboutData } from '../types/index.js';

const AdminAuthGuard = lazy(() => import('./admin/AdminAuthGuard.js').then(m => ({ default: m.AdminAuthGuard })));
const AdminConductor = lazy(() => import('./admin/AdminConductor.js').then(m => ({ default: m.AdminConductor })));
const MainRoom = lazy(() => import('./MainRoom.js').then(m => ({ default: m.MainRoom })));
const LandingPage = lazy(() => import('./LandingPage.js').then(m => ({ default: m.LandingPage })));
const ShopView = lazy(() => import('./storefront/ShopView.js').then(m => ({ default: m.ShopView })));
const ReceiptView = lazy(() => import('./storefront/ReceiptView.js').then(m => ({ default: m.ReceiptView })));
const AboutView = lazy(() => import('./storefront/AboutView.js').then(m => ({ default: m.AboutView })));

interface MainPageRendererProps {
    isLoginPath: boolean;
    pageAttr: string | null;
    isMainRoomVisible: boolean;
    isLandingPageVisible: boolean;
    isShopVisible: boolean;
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
            {(isShopVisible || (isBare && roomIdParam === 'S')) && shopData && (
                <ShopView
                    categories={shopData.categories}
                    current_page={shopData.current_page}
                    onOpenItem={openItemModal}
                />
            )}
            {receiptData && <ReceiptView data={receiptData} />}
            {aboutData && !isBare && <AboutView data={aboutData} />}
        </>
    );
};
