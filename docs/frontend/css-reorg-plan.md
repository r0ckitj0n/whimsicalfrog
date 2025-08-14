# CSS Reorganization Plan (Non-Destructive Draft)

Generated: 2025-08-13T23:48:44.719Z

- Total CSS files analyzed: 21
- Total rules counted: 10020
- Files flagged by name (legacy/recovered/fix): 1

## Proposed Cohesive Structure

```
src/styles/
  base/
    reset.css (optional)
    site-base.css
    tokens.css (optional)
  utilities/
    z-index.css
    utilities-ui.css
  components/
    popup.css
    modal.css
    room-modal.css
    room-modal-header.css
    detailed-item-modal.css
    qty-button.css
    search-modal.css
  pages/
    room.css
    contact.css
    about.css
  admin/
    admin-base.css
    admin-inventory.css
    admin-db-tools.css
    admin-modals.css
  systems/
    sales.css
  main.css (imports only)
```

## Proposed Mapping for Flagged Files

- src/styles/legacy_missing_admin.css -> MOVE -> src/styles/admin/admin-base.css

## Duplicate Rules Across Files (Exact Matches)

- @keyframes room-modal-spin||0% in src/styles/components/room-modal.css, src/styles/site-base.css
- @keyframes room-modal-spin||100% in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 480px)||.room-modal-container in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 480px)||.room-modal-nav in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 480px)||.room-title-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .cart-text in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .header-center in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .header-left in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .header-right in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .logo-text in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 480px)||.site-header.universal-page-header .search-bar in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-dashboard in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-tab in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-table in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-table td in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-table th in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.admin-tabs in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.fullscreen-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- @media (max-width: 768px)||.metric-card in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- @media (max-width: 768px)||.page-content-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-modal-close in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-modal-container in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-modal-nav in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-title-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-title-overlay h2 in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.room-title-overlay p in src/styles/components/room-modal.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .header-center in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .header-content in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .header-left in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .header-right in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .logo-tagline in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .logo-text in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .mobile-menu-toggle in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 768px)||.site-header.universal-page-header .nav-links in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 900px)||.site-header.universal-page-header .header-center in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 900px)||.site-header.universal-page-header .header-left in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 900px)||.site-header.universal-page-header .header-right in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 900px)||.site-header.universal-page-header .nav-link in src/styles/components/components-base.css, src/styles/site-base.css
- @media (max-width: 900px)||.site-header.universal-page-header .nav-links in src/styles/components/components-base.css, src/styles/site-base.css
- ||.admin-form-group in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-input-sm in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-input-sm:focus in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-label in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-select-sm:focus in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-textarea in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-textarea:focus in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-modal-overlay:not(.show) in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-modal-overlay.show in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-tab in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab-analytics in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab-products in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab.active in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-table tbody tr:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-table tbody tr:last-child td in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tabs in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tabs-container in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert-icon in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.bg-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.bg-gradient-brand-horizontal in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-admin-primary in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.btn-admin-primary:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.btn-admin-secondary in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.btn-admin-secondary:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.btn-brand in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-brand:hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-small.btn-danger:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.card-standard in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.card-standard:hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.config-form-container.show in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.config-form-toggle in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.config-form-toggle:hover in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.content-wrapper in src/styles/components/components-base.css, src/styles/site-base.css
- ||.detailed-item-modal-container::-webkit-scrollbar-x in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal::-webkit-scrollbar-x in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.door-area in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area:hover in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area:hover .door-label in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area:hover .door-sign in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.area-1 in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.area-2 in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.area-3 in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.area-4 in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.area-5 in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.door-positioned in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.loading in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.touch-active in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area.touch-inactive in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area[tabindex="0"] in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-area[tabindex="0"]:focus in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-label in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-picture in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-positioned in src/styles/components/room-main.css, src/styles/site-base.css
- ||.door-sign in src/styles/components/room-main.css, src/styles/site-base.css
- ||.editable-field input in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.main-content in src/styles/components/components-base.css, src/styles/site-base.css
- ||.metric-card-change in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.metric-card-change.negative in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.metric-card-change.positive in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.metric-card-title in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.metric-card-value in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.metric-card:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.modal-actions in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.no-doors-message in src/styles/components/room-main.css, src/styles/site-base.css
- ||.page-header-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.room-content in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.room-modal-error-icon in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-error-retry in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-error-retry:hover in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav:disabled in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav:disabled:hover in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav:hover in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav.next in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav.prev in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-overlay.show .room-modal-container in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-title-overlay h2 in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-title-overlay p in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.rounded-brand in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.shadow-brand in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.shadow-brand-hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.site-header.universal-page-header .cart-icon in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .cart-link:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-logo in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-right .nav-link:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .logo-link:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .logo-text-container in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-auth-section in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-menu-toggle svg in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-menu-toggle:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-menu.active in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-nav-link:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-nav-link.active in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-nav-links in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .nav-link:hover in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .nav-link.active in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-container in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-input-container in src/styles/components/components-base.css, src/styles/site-base.css
- ||.status-disabled in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-enabled in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-item in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-item:last-child in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-label in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-section in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-section h3 in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.status-value in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.text-brand-primary in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.text-brand-secondary in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.text-shadow-dark in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.transition-smooth in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||#room-iframe in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||body.wf-no-scroll in src/styles/admin-modals.css, src/styles/room-main.css
- ||html.modal-open in src/styles/admin-modals.css, src/styles/main.css, src/styles/site-base.css

## Conflicting Rules Across Files (Same selector/media, different declarations)

- ||:root in src/styles/site-base.css, src/styles/z-index.css
- ||.admin-dashboard in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-input in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-input:focus in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-select in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-select-sm in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-form-select:focus in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-header-card in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-modal-overlay in src/styles/admin-modals.css, src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-settings-button in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-tab-customers in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab-marketing in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab-orders in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-tab-settings in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-table in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-table td in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.admin-table th in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert-error in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert-info in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert-success in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.alert-warning in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.back-button-container in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.bg-container.mode-fullscreen in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.bg-gradient-brand-primary in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-dark in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-dark:hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-light:hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-link in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-small in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.btn-small.btn-danger in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.chart-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.color-swatch in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.config-form-container in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.css-preview-heading in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.detailed-item-modal in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css, src/styles/site-base.css
- ||.detailed-item-modal .btn--qty in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .btn--qty svg in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .flex:has(.btn--qty) in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .input--qty in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .modal-body in src/styles/components/detailed-item-modal.css, src/styles/components/modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .modal-content in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal .quantity-selector in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal * in src/styles/components/modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal-container in src/styles/components/detailed-item-modal.css, src/styles/components/modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal-container .btn--qty in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal-container .input--qty in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal-container .modal-body in src/styles/components/detailed-item-modal.css, src/styles/components/qty-button.css
- ||.detailed-item-modal-container .modal-content in src/styles/components/detailed-item-modal.css, src/styles/components/modal.css, src/styles/components/qty-button.css
- ||.editable-field in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.editable-field:hover in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.editable-field.editing in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.error-message in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.form-error in src/styles/admin-modals.css, src/styles/site-base.css
- ||.form-feedback.invalid-feedback in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.fullscreen-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.header-container in src/styles/components/room-main.css, src/styles/components/room-modal.css
- ||.header-gradient-brand in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.item-popup in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup-enhanced in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup-legacy in src/styles/components/popup.css, src/styles/site-base.css
- ||.loading-spinner in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.logging-status-grid in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.main-room-section in src/styles/components/room-main.css, src/styles/main.css, src/styles/site-base.css
- ||.main-room-section::before in src/styles/components/room-main.css, src/styles/site-base.css
- ||.metric-card in src/styles/legacy_missing_admin.css, src/styles/site-base.css
- ||.modal-content in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.modal-overlay in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.modal-room-page in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.nav-arrow in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.nav-arrow svg in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.nav-arrow:active in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.nav-arrow:hover in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.nav-arrow.left in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.nav-arrow.right in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.overlay-gradient-bottom in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.overlay-gradient-top in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.page-content in src/styles/components/components-base.css, src/styles/main.css, src/styles/site-base.css
- ||.page-content-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.popup-add-btn in src/styles/components/popup.css, src/styles/site-base.css
- ||.popup-add-to-cart in src/styles/components/popup.css, src/styles/site-base.css
- ||.popup-add-to-cart-btn in src/styles/components/popup.css, src/styles/site-base.css
- ||.popup-content:hover in src/styles/components/popup.css, src/styles/site-base.css
- ||.room-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.room-modal-close in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-close:hover in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-container in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-error in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-error-message in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-error-title in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-header in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-iframe in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-iframe-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.room-modal-loading in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-loading-text in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-nav in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-overlay.popup-active in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-overlay.show in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-spinner in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-modal-title in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-overlay-wrapper in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-page-header in src/styles/components/room-main.css, src/styles/components/room-modal.css, src/styles/site-base.css
- ||.room-page-header * in src/styles/components/room-main.css, src/styles/site-base.css
- ||.room-title-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- ||.sale-badge in src/styles/sales-system.css, src/styles/site-base.css
- ||.search-loading in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-modal-body in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-modal-close in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-modal-content in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-modal-header in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-modal-title in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-no-results in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-result-description in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.search-result-item in src/styles/components/search-modal.css, src/styles/site-base.css
- ||.settings-grid in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.settings-section in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.settings-section .section-content in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.site-header in src/styles/components/room-main.css, src/styles/components/room-modal.css
- ||.site-header.universal-page-header in src/styles/components/components-base.css, src/styles/components/room-modal.css, src/styles/site-base.css
- ||.site-header.universal-page-header .auth-links in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .cart-count in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .cart-link in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .cart-total in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-center in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-container in src/styles/components/components-base.css, src/styles/components/room-modal.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-content in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-left in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-right in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .header-right .nav-link in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .logo-link in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .logo-tagline in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .logo-text in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-menu in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-menu-toggle in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .mobile-nav-link in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .nav-link in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .nav-links in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-bar in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-bar::placeholder in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-bar:focus in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .search-icon in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .user-menu in src/styles/components/components-base.css, src/styles/site-base.css
- ||.site-header.universal-page-header .welcome-message in src/styles/components/components-base.css, src/styles/site-base.css
- ||.wf-btn in src/styles/login-modal.css, src/styles/utilities-ui.css
- ||.wf-btn-primary in src/styles/login-modal.css, src/styles/utilities-ui.css
- ||#detailedItemModal) in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body in src/styles/components/room-modal.css, src/styles/main.css, src/styles/site-base.css
- ||body.modal-open in src/styles/admin-modals.css, src/styles/main.css, src/styles/site-base.css
- ||body.modal-open .modal-content:not(.room-modal-container) in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open .modal-overlay:not(.room-modal-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open .modal:not(.room-modal-overlay in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open #itemPopup.in-room-modal in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open #productModal in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open #productModal .modal-content in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open #productModal #modalContent in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open #quantityModal in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.modal-open:has(.room-modal-overlay.show) #detailedItemModal in src/styles/components/room-modal.css, src/styles/site-base.css
- ||body.room-modal-open #detailedItemModal in src/styles/components/room-modal.css, src/styles/site-base.css
- ||button in src/styles/components/room-modal.css, src/styles/site-base.css
- ||html in src/styles/components/room-modal.css, src/styles/main.css, src/styles/site-base.css
- ||img in src/styles/main.css, src/styles/site-base.css

## Notes

- This plan is non-destructive. Next step is to implement moves/merges per mapping, then re-run inventory to confirm no loss.
- Recovered and fixes buckets should be distributed into components/pages as indicated.
- Conflicts require human review to decide authoritative values.
