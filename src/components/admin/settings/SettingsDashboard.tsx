import React from 'react';
import { useSearchParams } from 'react-router-dom';
import { SettingsCard } from './SettingsCard.js';
import { ADMIN_SECTION } from '../../../core/constants.js';

/**
 * SettingsDashboard
 * 
 * Replaces sections/partials/admin_settings/settings_grid.php.
 * Renders a categorized grid of settings cards.
 */
export const SettingsDashboard: React.FC = () => {
    const [, setSearchParams] = useSearchParams();

    const setSection = (section: string, tab?: string) => {
        setSearchParams(prev => {
            prev.set('section', section);
            if (tab) prev.set('tab', tab);
            else prev.delete('tab');
            return prev;
        });
    };

    return (
        <div className="settings-grid">
            {/* Business & Analytics */}
            <SettingsCard
                theme="emerald"
                title="Business Insights"
                description="Monitor your sales, check promotional effectiveness, and view key reports."
            >

                <button
                    type="button"
                    id="businessInfoBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.BUSINESS_INFO)}
                >
                    Business Information
                </button>
                <button
                    type="button"
                    id="costBreakdownSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.COST_BREAKDOWN)}
                >
                    Cost Suggestions
                </button>

                <button
                    type="button"
                    id="priceSuggestionsSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.PRICE_SUGGESTIONS)}
                >
                    Price Suggestions
                </button>
                <button
                    type="button"
                    id="shippingSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.SHIPPING)}
                >
                    Shipping Rates
                </button>

            </SettingsCard>

            {/* Communication */}
            <SettingsCard
                theme="orange"
                title="Customer Outreach"
                description="Set up emails and manage communication with your customers and staff."
            >
                <button
                    type="button"
                    id="couponManagerBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.COUPONS)}
                >
                    Coupon Manager
                </button>
                <button
                    type="button"
                    id="emailConfigBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.COMMUNICATION, 'settings')}
                >
                    E-mail
                </button>
                <button
                    type="button"
                    id="newslettersBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.NEWSLETTERS)}
                >
                    Newsletters
                </button>
                <button
                    type="button"
                    id="marketingManagerSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.MARKETING_SETTINGS)}
                >
                    Sales Nudge Kit
                </button>


                <button
                    type="button"
                    id="socialMediaBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.SOCIAL_MEDIA)}
                >
                    Social Media
                </button>
            </SettingsCard>

            {/* Content Management */}
            <SettingsCard
                theme="blue"
                title="Store Display"
                description="Control the way your products and categories appear to customers."
            >
                <button
                    type="button"
                    id="shoppingCartBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.SHOPPING_CART)}
                >
                    Cart Settings
                </button>

                <button
                    type="button"
                    id="inventoryAuditBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.INVENTORY_ARCHIVE)}
                >
                    Inventory Audit
                </button>

                <button
                    type="button"
                    id="itemOptionsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.ITEM_ATTRIBUTES)}
                >
                    Inventory Options
                </button>
                <button
                    type="button"
                    id="unifiedRoomManagerBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.ROOM_MANAGER)}
                >
                    Room Manager
                </button>
                <button
                    type="button"
                    id="themeWordsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.THEME_WORDS)}
                >
                    Theme Words
                </button>
            </SettingsCard>

            {/* Technical & System */}
            <SettingsCard
                theme="red"
                title="System Maintenance"
                description="Run diagnostics, view system logs, and manage backup and restore functions."
            >
                <button
                    type="button"
                    id="adminToolsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.ADVANCED_TOOLS)}
                >
                    Advanced Tools
                </button>
                <button
                    type="button"
                    id="aiSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.AI_SETTINGS)}
                >
                    AI Settings
                </button>

                <button
                    type="button"
                    id="brandStylingBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.BRAND_STYLING)}
                >
                    Brand Styling
                </button>
                <button
                    type="button"
                    id="dashboardConfigBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.DASHBOARD_CONFIG)}
                >
                    Dashboard Layout
                </button>

                <button
                    type="button"
                    id="squareSettingsBtn"
                    className="admin-settings-button"
                    onClick={() => setSection(ADMIN_SECTION.SQUARE)}
                >
                    Square Configuration
                </button>


            </SettingsCard>
        </div>
    );
};
