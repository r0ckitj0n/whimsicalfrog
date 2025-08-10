import '../css/main.css';
import '../css/admin.css';
import '../css/admin-styles.css';
import '../css/global-modals.css';
import './admin-common.js';
import './admin-customers.js';
import './admin-pos.js';
import './admin-orders.js';
import './admin-reports.js';

// --- Admin Page Specific Logic ---

/**
 * Ensures only one modal is visible at a time using a MutationObserver.
 */
function initializeModalObserver() {
    const modalObserver = new MutationObserver(mutations => {
        mutations.forEach(m => {
            if (m.type === 'attributes' && m.attributeName === 'class') {
                const el = m.target;
                if (el.classList.contains('admin-modal-overlay') && !el.classList.contains('hidden')) {
                    document.querySelectorAll('.admin-modal-overlay').forEach(other => {
                        if (other !== el) other.classList.add('hidden');
                    });
                }
            }
        });
    });

    document.querySelectorAll('.admin-modal-overlay').forEach(el => {
        modalObserver.observe(el, { attributes: true, attributeFilter: ['class'] });
    });
}

// --- Event Listeners for Admin Buttons ---
function bindAdminButtonEvents() {
    const eventMap = {
        'dashboardConfigBtn': openDashboardConfigModal,
        'categoriesBtn': openCategoriesModal,
        'globalColorSizeBtn': openGlobalColorSizeModal,
        'roomsBtn': openRoomSettingsModal,
        'roomCategoryBtn': openRoomCategoryManagerModal,
        'templateManagerBtn': openTemplateManagerModal,
        'cssRulesBtn': openCSSRulesModal,
        'backgroundManagerBtn': openBackgroundManagerModal,
        'roomMapperBtn': openRoomMapperModal,
        'areaItemMapperBtn': openAreaItemMapperModal,
        'marketingAnalyticsBtn': openMarketingAnalyticsModal,
        'businessReportsBtn': openBusinessReportsModal,
        'salesAdminBtn': openSalesAdminModal,
        'cartButtonTextBtn': openCartButtonTextModal,
        'squareSettingsBtn': openSquareSettingsModal,
        'emailConfigBtn': openEmailConfigModal,
        'emailHistoryBtn': openEmailHistoryModal,
        'fixSampleEmailBtn': fixSampleEmail,
        'loggingStatusBtn': openLoggingStatusModal,
        'receiptSettingsBtn': openReceiptSettingsModal,
        'systemConfigBtn': openSystemConfigModal,
        'databaseTablesBtn': openDatabaseTablesModal,
        'fileExplorerBtn': openFileExplorerModal,
        'website-logs-btn': openWebsiteLogsModal,
        'aiSettingsBtn': openAISettingsModal,
        'help-hints-btn': openHelpHintsModal,
        'databaseMaintenanceBtn': openDatabaseMaintenanceModal,
        'documentationBtn': openDocumentationHubModal,
        'systemCleanupBtn': openSystemCleanupModal
    };

    for (const [id, func] of Object.entries(eventMap)) {
        const button = document.getElementById(id);
        if (button && typeof func === 'function') {
            button.addEventListener('click', func);
        } else if (button) {
            console.warn(`Function for button #${id} is not defined.`);
        }
    }
}

// --- Initialize everything on DOMContentLoaded ---
document.addEventListener('DOMContentLoaded', () => {
    // --- Global Event Listeners (runs on all pages) ---
    document.body.addEventListener('click', (e) => {
        // Generic print button handler
        if (e.target.closest('.js-print-button')) {
            window.print();
            return;
        }

        // Close modal if clicking on the overlay background
        if (e.target.classList.contains('admin-modal-overlay')) {
            e.target.classList.add('hidden');
        }
    });

    // ESC key closes any open modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.admin-modal-overlay:not(.hidden)').forEach(o => o.classList.add('hidden'));
        }
    });

    // --- Admin Settings Page Specific Initialization ---
    if (document.querySelector('.settings-page')) {
        console.log('Admin settings page detected. Initializing admin scripts.');
        document.querySelectorAll('.admin-modal-overlay').forEach(overlay => overlay.classList.add('hidden'));
        initializeModalObserver();
        bindAdminButtonEvents();
    }
});
