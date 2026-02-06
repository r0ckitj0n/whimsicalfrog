import React, { lazy } from 'react';
import { createPortal } from 'react-dom';
import { ADMIN_SECTION } from '../../core/constants.js';

const BusinessInfoManager = lazy(() => import('./settings/BusinessInfoManager.js').then(m => ({ default: m.BusinessInfoManager })));
const ShippingSettings = lazy(() => import('./settings/ShippingSettings.js').then(m => ({ default: m.ShippingSettings })));
const SquareSettings = lazy(() => import('./settings/SquareSettings.js').then(m => ({ default: m.SquareSettings })));
const CommunicationManager = lazy(() => import('./settings/CommunicationManager.js').then(m => ({ default: m.CommunicationManager })));
const DashboardConfig = lazy(() => import('./settings/DashboardConfig.js').then(m => ({ default: m.DashboardConfig })));
const AISettingsManager = lazy(() => import('./settings/AISettingsManager.js').then(m => ({ default: m.AISettingsManager })));
const CostBreakdownManager = lazy(() => import('./settings/CostBreakdownManager.js').then(m => ({ default: m.CostBreakdownManager })));
const AISuggestions = lazy(() => import('./settings/AISuggestions.js').then(m => ({ default: m.AISuggestions })));
const IntentHeuristicsManager = lazy(() => import('./settings/IntentHeuristicsManager.js').then(m => ({ default: m.IntentHeuristicsManager })));
const AttributesManager = lazy(() => import('./settings/AttributesManager.js').then(m => ({ default: m.AttributesManager })));
const BrandStyling = lazy(() => import('./settings/BrandStyling.js').then(m => ({ default: m.BrandStyling })));
const NewsletterManager = lazy(() => import('./settings/NewsletterManager.js').then(m => ({ default: m.NewsletterManager })));
const SocialMediaManager = lazy(() => import('./settings/SocialMediaManager.js').then(m => ({ default: m.SocialMediaManager })));
const MarketingSettings = lazy(() => import('./settings/MarketingSettings.js').then(m => ({ default: m.MarketingSettings })));
const ThemeWordsManager = lazy(() => import('./settings/ThemeWordsManager.js').then(m => ({ default: m.ThemeWordsManager })));
const CouponsManager = lazy(() => import('./settings/CouponsManager.js').then(m => ({ default: m.CouponsManager })));
const HelpGuides = lazy(() => import('./settings/HelpGuides.js').then(m => ({ default: m.HelpGuides })));
const UnifiedRoomManager = lazy(() => import('./settings/UnifiedRoomManager.js').then(m => ({ default: m.UnifiedRoomManager })));

interface SettingsSwitchProps {
    section: string;
    modalTitle: string;
    closeSettingsModal: () => void;
    searchParams: URLSearchParams;
}

export const SettingsSwitch: React.FC<SettingsSwitchProps> = ({
    section,
    modalTitle,
    closeSettingsModal,
    searchParams
}) => {
    return (
        <>
            {section === ADMIN_SECTION.BUSINESS_INFO && createPortal(
                <div id="business-info-react-root">
                    <BusinessInfoManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SHIPPING && createPortal(
                <div id="shipping-react-root">
                    <ShippingSettings onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SQUARE && createPortal(
                <div id="square-react-root">
                    <SquareSettings onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.COMMUNICATION && createPortal(
                <div id="communication-react-root">
                    <CommunicationManager
                        initialTab={((): 'settings' | 'templates' | 'history' => {
                            const tab = searchParams.get('tab');
                            if (tab === 'templates' || tab === 'history') return tab;
                            return 'settings';
                        })()}
                        onClose={closeSettingsModal}
                        title={modalTitle}
                    />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.DASHBOARD_CONFIG && createPortal(
                <div id="dashboard-config-react-root">
                    <DashboardConfig onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.AI_SETTINGS && createPortal(
                <div id="ai-settings-react-root">
                    <AISettingsManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.COST_BREAKDOWN && createPortal(
                <div id="cost-breakdown-react-root">
                    <CostBreakdownManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.PRICE_SUGGESTIONS && createPortal(
                <div id="price-suggestions-react-root">
                    <AISuggestions onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.INTENT_HEURISTICS && createPortal(
                <div id="intent-heuristics-react-root">
                    <IntentHeuristicsManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.ITEM_ATTRIBUTES && createPortal(
                <div id="item-attributes-react-root">
                    <AttributesManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.BRAND_STYLING && createPortal(
                <div id="brand-styling-react-root">
                    <BrandStyling onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.NEWSLETTERS && createPortal(
                <div id="newsletters-react-root">
                    <NewsletterManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.SOCIAL_MEDIA && createPortal(
                <div id="social-media-react-root">
                    <SocialMediaManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.MARKETING_SETTINGS && createPortal(
                <div id="marketing-settings-react-root">
                    <MarketingSettings onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.THEME_WORDS && createPortal(
                <div id="theme-words-react-root">
                    <ThemeWordsManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.COUPONS && createPortal(
                <div id="coupons-react-root">
                    <CouponsManager onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.HELP_GUIDES && createPortal(
                <div id="help-guides-react-root">
                    <HelpGuides onClose={closeSettingsModal} title={modalTitle} />
                </div>,
                document.body
            )}

            {/* Room Manager Specialized Modals */}
            {section === ADMIN_SECTION.ROOM_MANAGER && createPortal(
                <div id="room-manager-react-root">
                    <UnifiedRoomManager onClose={closeSettingsModal} initialTab="overview" title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.BACKGROUNDS && createPortal(
                <div id="backgrounds-react-root">
                    <UnifiedRoomManager onClose={closeSettingsModal} initialTab="visuals" title={modalTitle} />
                </div>,
                document.body
            )}

            {section === ADMIN_SECTION.AREA_MAPPER && createPortal(
                <div id="area-mapper-react-root">
                    <UnifiedRoomManager onClose={closeSettingsModal} initialTab="content" title={modalTitle} />
                </div>,
                document.body
            )}
        </>
    );
};
