import React, { lazy } from 'react';
import { createPortal } from 'react-dom';
import { ADMIN_SECTION, AdminSection } from '../../core/constants.js';
import type { TabType } from './settings/SiteMaintenance.js';

const SystemTools = lazy(() => import('./settings/SystemTools.js').then(m => ({ default: m.SystemTools })));
const SecretsManager = lazy(() => import('./settings/SecretsManager.js').then(m => ({ default: m.SecretsManager })));
const LogViewer = lazy(() => import('./settings/LogViewer.js').then(m => ({ default: m.LogViewer })));
const InventoryArchive = lazy(() => import('./settings/InventoryArchive.js').then(m => ({ default: m.InventoryArchive })));
const SiteMaintenance = lazy(() => import('./settings/SiteMaintenance.js').then(m => ({ default: m.SiteMaintenance })));
const ShoppingCartSettings = lazy(() => import('./settings/ShoppingCartSettings.js').then(m => ({ default: m.ShoppingCartSettings })));
const DbStatus = lazy(() => import('./settings/DbStatus.js').then(m => ({ default: m.DbStatus })));
const DbQueryConsole = lazy(() => import('./settings/DbQueryConsole.js').then(m => ({ default: m.DbQueryConsole })));
const InventoryAudit = lazy(() => import('./settings/InventoryArchive.js').then(m => ({ default: m.InventoryArchive })));
const CronManager = lazy(() => import('./settings/CronManager.js').then(m => ({ default: m.CronManager })));
const SessionViewer = lazy(() => import('./settings/SessionViewer.js').then(m => ({ default: m.SessionViewer })));
const AddressCheck = lazy(() => import('./settings/AddressDiagnostics.js').then(m => ({ default: m.AddressDiagnostics })));
const CartSimulation = lazy(() => import('./settings/CartSimulation.js').then(m => ({ default: m.CartSimulation })));
const MarketingCheck = lazy(() => import('./settings/MarketingSelfCheck.js').then(m => ({ default: m.MarketingSelfCheck })));
const SizingTools = lazy(() => import('./settings/ItemDimensionsTools.js').then(m => ({ default: m.ItemDimensionsTools })));
const CssCatalog = lazy(() => import('./settings/CssCatalog.js').then(m => ({ default: m.CssCatalog })));
const CssRulesManager = lazy(() => import('./settings/CssRulesManager.js').then(m => ({ default: m.CssRulesManager })));
const ActionIconsManager = lazy(() => import('./settings/ActionIconsManager.js').then(m => ({ default: m.ActionIconsManager })));

interface AdvancedToolsSwitchProps {
    section: AdminSection;
    modalTitle: string;
    closeSettingsModal: () => void;
    backToAdvancedTools: () => void;
    ADVANCED_TOOLS_SUB_SECTIONS: AdminSection[];
    searchParams: URLSearchParams;
}

export const AdvancedToolsSwitch: React.FC<AdvancedToolsSwitchProps> = ({
    section,
    modalTitle,
    closeSettingsModal,
    backToAdvancedTools,
    ADVANCED_TOOLS_SUB_SECTIONS,
    searchParams
}) => {
    return (
        <>
            {(section === ADMIN_SECTION.ADVANCED_TOOLS || ADVANCED_TOOLS_SUB_SECTIONS.includes(section)) && createPortal(
                <div id="advanced-tools-react-root">
                    <SystemTools
                        onClose={closeSettingsModal}
                        title={section === ADMIN_SECTION.ADVANCED_TOOLS ? modalTitle : 'Advanced Tools'}
                    />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.CSS_RULES && createPortal(
                <div id="css-rules-react-root">
                    <CssRulesManager onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.ACTION_ICONS && createPortal(
                <div id="action-icons-react-root">
                    <ActionIconsManager onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SECRETS && createPortal(
                <div id="secrets-react-root">
                    <SecretsManager onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.LOGS && createPortal(
                <div id="logs-react-root">
                    <LogViewer onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.INVENTORY_ARCHIVE && createPortal(
                <div id="inventory-archive-react-root">
                    <InventoryArchive onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SITE_MAINTENANCE && createPortal(
                <div id="site-maintenance-react-root">
                    <SiteMaintenance
                        initialTab={(searchParams.get('tab') as TabType) || 'status'}
                        onClose={backToAdvancedTools}
                        title={modalTitle}
                    />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SHOPPING_CART && createPortal(
                <div id="shopping_cart-react-root">
                    <ShoppingCartSettings onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.DB_STATUS && createPortal(
                <div id="db-status-react-root">
                    <DbStatus onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.DB_QUERY_CONSOLE && createPortal(
                <div id="db-query-console-react-root">
                    <DbQueryConsole onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.INVENTORY_AUDIT && createPortal(
                <div id="inventory-audit-react-root">
                    <InventoryAudit onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.CRON_MANAGER && createPortal(
                <div id="cron-manager-react-root">
                    <CronManager onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SESSION_VIEWER && createPortal(
                <div id="session-viewer-react-root">
                    <SessionViewer onClose={backToAdvancedTools} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.ADDRESS_CHECK && createPortal(
                <div id="address-check-react-root">
                    <AddressCheck onClose={backToAdvancedTools} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.CART_SIMULATION && createPortal(
                <div id="cart-simulation-react-root">
                    <CartSimulation onClose={backToAdvancedTools} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.MARKETING_CHECK && createPortal(
                <div id="marketing-check-react-root">
                    <MarketingCheck onClose={backToAdvancedTools} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SIZING_TOOLS && createPortal(
                <div id="sizing-tools-react-root">
                    <SizingTools onClose={backToAdvancedTools} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.CSS_CATALOG && createPortal(
                <div id="css-catalog-react-root">
                    <CssCatalog onClose={backToAdvancedTools} />
                </div>,
                document.body
            )}
        </>
    );
};
