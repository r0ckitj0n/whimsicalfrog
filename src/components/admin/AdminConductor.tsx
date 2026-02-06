import React, { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ADMIN_SECTION, AdminSection, PAGE, getModalTitle } from '../../core/constants.js';
import '../../styles/entries/admin-core.css';
import { AdminNavigation } from './AdminNavigation.js';
import { useTooltips } from '../../hooks/admin/useTooltips.js';
import { AdminSectionSwitch } from './AdminSectionSwitch.js';

// Sections that should render as modal overlays on top of the SettingsDashboard
const SETTINGS_MODAL_SECTIONS: AdminSection[] = [
    ADMIN_SECTION.BUSINESS_INFO,
    ADMIN_SECTION.SHIPPING,
    ADMIN_SECTION.SQUARE,
    ADMIN_SECTION.COMMUNICATION,
    ADMIN_SECTION.DASHBOARD_CONFIG,
    ADMIN_SECTION.AREA_MAPPER,
    ADMIN_SECTION.AI_SETTINGS,
    ADMIN_SECTION.COST_BREAKDOWN,
    ADMIN_SECTION.PRICE_SUGGESTIONS,
    ADMIN_SECTION.AUTOMATION,
    ADMIN_SECTION.INTENT_HEURISTICS,
    ADMIN_SECTION.ITEM_ATTRIBUTES,
    ADMIN_SECTION.BRAND_STYLING,
    ADMIN_SECTION.NEWSLETTERS,
    ADMIN_SECTION.SOCIAL_MEDIA,
    ADMIN_SECTION.MARKETING_SETTINGS,
    ADMIN_SECTION.THEME_WORDS,
    ADMIN_SECTION.COUPONS,
    ADMIN_SECTION.SECRETS,
    ADMIN_SECTION.LOGS,
    ADMIN_SECTION.INVENTORY_ARCHIVE,
    ADMIN_SECTION.SITE_MAINTENANCE,
    ADMIN_SECTION.ADVANCED_TOOLS,
    ADMIN_SECTION.SHOPPING_CART,
    ADMIN_SECTION.HELP_GUIDES,
    ADMIN_SECTION.ROOM_MANAGER,
    ADMIN_SECTION.CATEGORIES,
    ADMIN_SECTION.BACKGROUNDS,
    ADMIN_SECTION.DB_STATUS,
    ADMIN_SECTION.DB_QUERY_CONSOLE,
    ADMIN_SECTION.INVENTORY_AUDIT,
    ADMIN_SECTION.CRON_MANAGER,
    ADMIN_SECTION.SESSION_VIEWER,
    ADMIN_SECTION.ADDRESS_CHECK,
    ADMIN_SECTION.CART_SIMULATION,
    ADMIN_SECTION.MARKETING_CHECK,
    ADMIN_SECTION.SIZING_TOOLS,
    ADMIN_SECTION.CSS_CATALOG,
];

// Sections that are launched from the Advanced Tools hub and should keep it open behind them
const ADVANCED_TOOLS_SUB_SECTIONS = [
    ADMIN_SECTION.LOGS,
    ADMIN_SECTION.SECRETS,
    ADMIN_SECTION.AUTOMATION,
    ADMIN_SECTION.DB_STATUS,
    ADMIN_SECTION.DB_QUERY_CONSOLE,
    ADMIN_SECTION.INVENTORY_AUDIT,
    ADMIN_SECTION.ACTION_ICONS,
    ADMIN_SECTION.SITE_MAINTENANCE,
    ADMIN_SECTION.CSS_RULES,
    ADMIN_SECTION.BRAND_STYLING,
    ADMIN_SECTION.SHOPPING_CART,
    ADMIN_SECTION.CRON_MANAGER,
    ADMIN_SECTION.SESSION_VIEWER,
    ADMIN_SECTION.ADDRESS_CHECK,
    ADMIN_SECTION.CART_SIMULATION,
    ADMIN_SECTION.MARKETING_CHECK,
    ADMIN_SECTION.SIZING_TOOLS,
    ADMIN_SECTION.CSS_CATALOG,
];

/**
 * AdminConductor manages the mounting of specialized React admin views.
 * It detects the current admin section and provides the appropriate interface.
 */
export const AdminConductor: React.FC = () => {
    const page = document.body?.getAttribute('data-page') || '';
    const [searchParams, setSearchParams] = useSearchParams();
    const section = (searchParams.get('section') || (searchParams.get('room_id') === 'X' ? ADMIN_SECTION.SETTINGS : '')) as AdminSection;

    // Helper to close a settings modal and return to the settings dashboard
    const closeSettingsModal = () => {
        setSearchParams(prev => {
            prev.set('section', ADMIN_SECTION.SETTINGS);
            prev.delete('tab');
            prev.delete('edit');
            prev.delete('view');
            return prev;
        });
    };

    // Helper to return from a sub-tool back to the Advanced Tools hub
    const backToAdvancedTools = () => {
        setSearchParams(prev => {
            prev.set('section', ADMIN_SECTION.ADVANCED_TOOLS);
            prev.delete('tab');
            return prev;
        });
    };

    // Check if we're in a settings modal section
    const isSettingsModalSection = SETTINGS_MODAL_SECTIONS.includes(section);

    // Derive the modal title from the button text in SettingsDashboard
    const modalTitle = getModalTitle(section, searchParams.get('tab'));

    // Tooltip Integration
    const { enabled, fetchTooltips } = useTooltips();

    // Fetch common admin tooltips and section-specific ones
    useEffect(() => {
        if (!enabled) return;
        fetchTooltips('admin');
        if (section) {
            fetchTooltips(`admin/${section}`);
        }
    }, [enabled, section, fetchTooltips]);



    // Ensure body data-page attribute stays synchronized with the current section.
    useEffect(() => {
        const isPOSSection = section === ADMIN_SECTION.POS;

        if (isPOSSection) {
            document.body.setAttribute('data-page', 'admin/pos');
            document.body.classList.add('pos-body');
            document.documentElement.classList.add('pos-html');
        } else if (!section) {
            document.body.setAttribute('data-page', 'admin');
            document.body.classList.remove('pos-body');
            document.documentElement.classList.remove('pos-html');
        } else if (section === ADMIN_SECTION.SETTINGS || isSettingsModalSection) {
            document.body.setAttribute('data-page', 'admin/settings');
            document.body.classList.remove('pos-body');
            document.documentElement.classList.remove('pos-html');
        } else if (section) {
            document.body.setAttribute('data-page', `admin/${section}`);
            document.body.classList.remove('pos-body');
            document.documentElement.classList.remove('pos-html');
        }

        return () => {
            document.body.classList.remove('pos-body');
            document.documentElement.classList.remove('pos-html');
        };
    }, [section, isSettingsModalSection]);

    if (!page.startsWith(PAGE.ADMIN)) return null;

    const is_bare = searchParams.get('bare') === '1';
    const isPOS = page === 'admin/pos' || section === ADMIN_SECTION.POS;

    return (
        <div id="admin-section-content" className={`admin-react-conductor w-full !max-w-none flex flex-col flex-1 min-h-0 ${isPOS ? 'h-screen' : ''}`}>
            {!isPOS && !is_bare && <AdminNavigation />}
            <AdminSectionSwitch
                section={section}
                modalTitle={modalTitle}
                closeSettingsModal={closeSettingsModal}
                backToAdvancedTools={backToAdvancedTools}
                isSettingsModalSection={isSettingsModalSection}
                ADVANCED_TOOLS_SUB_SECTIONS={ADVANCED_TOOLS_SUB_SECTIONS}
                searchParams={searchParams}
                page={page}
            />
        </div>
    );
};

