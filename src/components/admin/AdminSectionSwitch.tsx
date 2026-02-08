import React, { Suspense, lazy } from 'react';
import { createPortal } from 'react-dom';
import { ADMIN_SECTION, AdminSection } from '../../core/constants.js';
import { AdminLoading } from './AdminLoading.js';

const InventoryManager = lazy(() => import('./inventory/InventoryManager.js').then(m => ({ default: m.InventoryManager })));
const OrdersManager = lazy(() => import('./orders/OrdersManager.js').then(m => ({ default: m.OrdersManager })));
const CustomersManager = lazy(() => import('./customers/CustomersManager.js').then(m => ({ default: m.CustomersManager })));
const CategoriesManager = lazy(() => import('./categories/CategoriesManager.js').then(m => ({ default: m.CategoriesManager })));
const POSView = lazy(() => import('./POSView.js').then(m => ({ default: m.POSView })));
const AdminDashboard = lazy(() => import('./dashboard/AdminDashboard.js').then(m => ({ default: m.AdminDashboard })));
const ReportsManager = lazy(() => import('./settings/ReportsManager.js').then(m => ({ default: m.ReportsManager })));
const MarketingSection = lazy(() => import('./settings/MarketingSection.js').then(m => ({ default: m.MarketingSection })));
const SettingsDashboard = lazy(() => import('./settings/SettingsDashboard.js').then(m => ({ default: m.SettingsDashboard })));
import { AdvancedToolsSwitch } from './AdvancedToolsSwitch.js';
import { SettingsSwitch } from './SettingsSwitch.js';

interface AdminSectionSwitchProps {
    section: AdminSection;
    modalTitle: string;
    closeSettingsModal: () => void;
    backToAdvancedTools: () => void;
    isSettingsModalSection: boolean;
    ADVANCED_TOOLS_SUB_SECTIONS: AdminSection[];
    searchParams: URLSearchParams;
    page: string;
}

export const AdminSectionSwitch: React.FC<AdminSectionSwitchProps> = ({
    section,
    modalTitle,
    closeSettingsModal,
    backToAdvancedTools,
    isSettingsModalSection,
    ADVANCED_TOOLS_SUB_SECTIONS,
    searchParams,
    page
}) => {
    return (
        <Suspense fallback={<AdminLoading />}>
            {/* Advanced Tools Sections */}
            <AdvancedToolsSwitch
                section={section}
                modalTitle={modalTitle}
                closeSettingsModal={closeSettingsModal}
                backToAdvancedTools={backToAdvancedTools}
                ADVANCED_TOOLS_SUB_SECTIONS={ADVANCED_TOOLS_SUB_SECTIONS}
                searchParams={searchParams}
            />

            {/* General Settings Modals */}
            <SettingsSwitch
                section={section}
                modalTitle={modalTitle}
                closeSettingsModal={closeSettingsModal}
                backToAdvancedTools={backToAdvancedTools}
                searchParams={searchParams}
            />

            {/* Inventory Section */}
            {section === ADMIN_SECTION.INVENTORY && (
                <div id="inventory-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <InventoryManager />
                </div>
            )}

            {/* POS Section */}
            {(page === 'admin/pos' || section === ADMIN_SECTION.POS) && (
                <div id="pos-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <POSView />
                </div>
            )}

            {/* Dashboard Section */}
            {((section as string) === ADMIN_SECTION.DASHBOARD || (section as string) === '' || !section) && !isSettingsModalSection && (section as string) !== (ADMIN_SECTION.SETTINGS as string) && !searchParams.get('edit') && (
                <div id="dashboard-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <AdminDashboard />
                </div>
            )}

            {/* Orders Section */}
            {section === ADMIN_SECTION.ORDERS && (
                <div id="orders-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <OrdersManager />
                </div>
            )}

            {/* Customers Section */}
            {section === ADMIN_SECTION.CUSTOMERS && (
                <div id="customers-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <CustomersManager />
                </div>
            )}

            {/* Categories Section */}
            {section === ADMIN_SECTION.CATEGORIES && createPortal(
                <div id="categories-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <CategoriesManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {/* Reports Section */}
            {section === ADMIN_SECTION.REPORTS && (
                <div id="reports-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <ReportsManager />
                </div>
            )}

            {/* Marketing Analytics Section */}
            {section === ADMIN_SECTION.MARKETING && (
                <div id="marketing-react-root" className="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <MarketingSection />
                </div>
            )}

            {/* Settings Section */}
            {(section === ADMIN_SECTION.SETTINGS || isSettingsModalSection) && (
                <div id="settings-react-root">
                    <SettingsDashboard />
                </div>
            )}
        </Suspense>
    );
};
