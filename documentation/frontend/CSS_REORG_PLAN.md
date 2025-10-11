# CSS Reorganization Plan (Non-Destructive Draft)

Generated: 2025-10-09T20:04:11.771Z

- Total CSS files analyzed: 48
- Total rules counted: 4447
- Files flagged by name (legacy/recovered/fix): 2

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

* None found

## Duplicate Rules Across Files (Exact Matches)

- @keyframes room-modal-spin||100% in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- @media (width <= 768px)||.popup-body in src/styles/components/room-iframe.css, src/styles/site-base.css
- @media (width <= 768px)||.popup-content in src/styles/components/room-iframe.css, src/styles/site-base.css
- @media (width <= 768px)||.room-title-overlay .room-description in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.admin-modal-content::-webkit-scrollbar in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content::-webkit-scrollbar-thumb in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content::-webkit-scrollbar-thumb:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content::-webkit-scrollbar-track in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header .modal-close in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header .modal-close-btn in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header .modal-close:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header .modal-title in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header h2 in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-overlay.hidden .admin-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.back-button-container in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.confirmation-modal in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-body in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button:disabled in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.cancel:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.cancel:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.danger:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-details in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-details li in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-details ul in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-icon in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-icon.danger in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-icon.info in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-icon.warning in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-overlay.show in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-overlay.show .confirmation-modal in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-subtitle in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-title in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.cursor-pointer in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.flex-wrap in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.fullscreen-modal-overlay.hidden in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.fullscreen-modal-overlay.hidden .fullscreen-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.hidden in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.is-hidden in src/styles/admin-db-status.css, src/styles/components/admin-tools.css
- ||.justify-between in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.loading-spinner.hidden in src/styles/admin-modals.css, src/styles/site-base.css
- ||.modal-button:disabled in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-danger in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-danger:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-danger:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-primary in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-primary:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-primary:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-secondary:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-secondary:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close.position-bottom-left in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close.position-bottom-right in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close.position-top-center in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close.position-top-left in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-overlay.hidden .compact-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-overlay.hidden .modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-overlay.hidden .room-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-subtitle in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.no-webp .room-bg-room1 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.no-webp .room-bg-room2 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.no-webp .room-bg-room3 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.pb-4 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.popup-add-to-cart-btn:active in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-add-to-cart-btn:hover in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-close in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-close:hover in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-modal-container in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-content-wrapper in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-modal-header in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-loading in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-overlay.popup-active in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-overlay.show .room-modal-container in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container h1 in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container h2 in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container h3 in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container p in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container span in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-overlay-wrapper .room-product-icon-img in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-overlay-wrapper.no-icon-bg .room-product-icon in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-page-header in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-product-icon-img in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-title-overlay:hover in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.w-full in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||#cartPage .modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||#dashboardSectionsTable col:nth-child(1) in src/styles/admin-modals.css, src/styles/components/admin-tools.css
- ||#dashboardSectionsTable col:nth-child(2) in src/styles/admin-modals.css, src/styles/components/admin-tools.css
- ||#dashboardSectionsTable col:nth-child(3) in src/styles/admin-modals.css, src/styles/components/admin-tools.css
- ||#dashboardSectionsTable col:nth-child(4) in src/styles/admin-modals.css, src/styles/components/admin-tools.css
- ||body:has(#cartPage) in src/styles/components/global-modals.css, src/styles/site-base.css

## Conflicting Rules Across Files (Same selector/media, different declarations)

- @keyframes room-modal-spin||0% in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- @media (width <= 768px)||.popup-image in src/styles/components/room-iframe.css, src/styles/site-base.css
- @media (width <= 768px)||.room-title-overlay in src/styles/components/room-iframe.css, src/styles/site-base.css
- @media (width <= 768px)||.room-title-overlay h1 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||:root in src/styles/admin-settings.css, src/styles/variables.css, src/styles/z-index.css
- ||.admin-content-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table td in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table th in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filter-button in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filter-section in src/styles/admin-filters.css, src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filters in src/styles/admin-filters.css, src/styles/components/aux-styles.css
- ||.admin-modal in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/admin/admin-legacy-modals.css
- ||.admin-modal .modal-body in src/styles/admin-modals.css, src/styles/admin/admin-legacy-modals.css
- ||.admin-modal-content in src/styles/admin-modals.css, src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content.business-section .admin-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content.content-section .admin-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content.technical-section .admin-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-content.visual-section .admin-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-overlay in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/components/global-modals.css
- ||.admin-modal-overlay.hidden in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/components/global-modals.css, src/styles/site-base.css
- ||.admin-modal-overlay.show in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/components/aux-styles.css, src/styles/components/global-modals.css
- ||.admin-tab-customers in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-dashboard in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-inventory in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-marketing in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-navigation in src/styles/admin-nav.css, src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-orders in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-pos in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-reports in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-tab-settings in src/styles/components/admin-nav.css, src/styles/site-base.css
- ||.admin-table-section in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-primary in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.btn-primary:hover in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.cart-item in src/styles/components/pos.css, src/styles/site-base.css
- ||.cart-item-price in src/styles/components/pos.css, src/styles/site-base.css
- ||.cart-summary in src/styles/components/pos.css, src/styles/site-base.css
- ||.compact-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.cancel in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.confirm in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.confirm:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.confirm:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.danger in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-button.danger:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-footer in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-header in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-icon.success in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-message in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-overlay in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.confirmation-modal-overlay.checkout-overlay in src/styles/site-base.css, src/styles/z-index.css
- ||.customer-modal .admin-modal in src/styles/admin-modals.css, src/styles/components/modal.css
- ||.customer-modal .modal-content in src/styles/admin-modals.css, src/styles/components/modal.css
- ||.customer-profile-header in src/styles/admin-modals.css, src/styles/site-base.css
- ||.delete-modal in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.delete-modal-actions in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.delete-modal-content in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.delete-modal-message in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.delete-modal-title in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.delete-modal.show in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.editable in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.editable:hover in src/styles/admin-inventory.css, src/styles/site-base.css
- ||.flex-row in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.fullscreen-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.fullscreen-modal-overlay in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.gap-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.help-documentation-container in src/styles/components/help-docs.css, src/styles/help-documentation.css
- ||.item-card in src/styles/components/pos.css, src/styles/site-base.css
- ||.item-card:hover in src/styles/components/pos.css, src/styles/site-base.css
- ||.item-meta in src/styles/components/pos.css, src/styles/site-base.css
- ||.item-popup in src/styles/components/popup.css, src/styles/components/room-iframe.css
- ||.item-popup.hidden in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup.measuring in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup.visible in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-price in src/styles/components/pos.css, src/styles/site-base.css
- ||.items-grid in src/styles/components/pos.css, src/styles/site-base.css
- ||.loading-spinner in src/styles/admin-modals.css, src/styles/components/aux-styles.css
- ||.mb-1 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mb-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.ml-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.ml-3 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.modal in src/styles/admin-settings.css, src/styles/site-base.css
- ||.modal-body in src/styles/components/aux-styles.css, src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button:focus in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-button.btn-secondary in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-close:hover in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-content in src/styles/admin-settings.css, src/styles/components/aux-styles.css, src/styles/components/global-modals.css
- ||.modal-footer in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-header in src/styles/components/aux-styles.css, src/styles/components/global-modals.css, src/styles/components/modal.css, src/styles/site-base.css
- ||.modal-overlay in src/styles/admin-settings.css, src/styles/components/aux-styles.css, src/styles/components/global-modals.css, src/styles/components/modal.css
- ||.modal-overlay.active in src/styles/admin-settings.css, src/styles/site-base.css
- ||.modal-overlay.hidden in src/styles/admin-settings.css, src/styles/components/global-modals.css, src/styles/site-base.css
- ||.modal-overlay.show in src/styles/admin-settings.css, src/styles/components/global-modals.css
- ||.modal-room-page in src/styles/components/aux-styles.css, src/styles/components/room-iframe.css
- ||.modal-sidebar in src/styles/components/modal.css, src/styles/site-base.css
- ||.modal-title in src/styles/components/global-modals.css, src/styles/components/modal.css, src/styles/site-base.css
- ||.mt-1 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mt-10 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mt-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mt-4 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mt-6 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.mt-8 in src/styles/admin-db-status.css, src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.my-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.order-detail in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-content in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-item in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-item:hover in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-panel in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-title in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-id in src/styles/components/modal.css, src/styles/site-base.css
- ||.popup-add-to-cart-btn in src/styles/components/popup.css, src/styles/components/room-iframe.css
- ||.popup-body in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-content in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-description in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-details in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-image in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-price in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.popup-title in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.px-3 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.py-2 in src/styles/components/admin-tools.css, src/styles/site-base.css
- ||.room-bg-main in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-bg-room1 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-bg-room2 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-bg-room3 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-bg-room4 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-bg-room5 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-modal-back-btn in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-back-btn:hover in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-content in src/styles/components/global-modals.css, src/styles/site-base.css
- ||.room-modal-iframe-container in src/styles/components/aux-styles.css, src/styles/components/room-iframe.css
- ||.room-modal-overlay in src/styles/admin-settings.css, src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-overlay.is-open in src/styles/admin-settings.css, src/styles/site-base.css
- ||.room-modal-overlay.show in src/styles/admin-settings.css, src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-spinner in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-modal-title-container in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-overlay-wrapper in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-overlay-wrapper .room-product-icon in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-overlay-wrapper .room-product-icon:hover in src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.room-product-icon in src/styles/components/room-iframe.css, src/styles/main.css, src/styles/site-base.css
- ||.room-product-icon:hover in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.room-title-overlay in src/styles/components/room-iframe.css, src/styles/components/room-main.css, src/styles/components/room-modal.css
- ||.room-title-overlay .room-description in src/styles/components/room-iframe.css, src/styles/components/room-main.css, src/styles/site-base.css
- ||.room-title-overlay h1 in src/styles/components/room-iframe.css, src/styles/site-base.css
- ||.settings-page in src/styles/admin-settings.css, src/styles/site-base.css
- ||.site-header.universal-page-header in src/styles/components/components-base.css, src/styles/components/room-modal.css, src/styles/room-modal.legacy.css
- ||.wf-admin-embed-frame in src/styles/admin-settings-extras.css, src/styles/admin-settings.css
- ||.wf-btn in src/styles/components/pos.css, src/styles/utilities-ui.css
- ||.wf-btn-primary in src/styles/components/pos.css, src/styles/utilities-ui.css
- ||.wf-error-notification in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-body in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-close in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-close:hover in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-container in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-content in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-icon in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-message in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification.is-visible in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification.slide-out in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-success-notification in src/styles/components/admin-tools.css, src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-tooltip in src/styles/components/tooltip.css, src/styles/components/tooltips.css
- ||#dashboardSectionsTable col:nth-child(5) in src/styles/admin-modals.css, src/styles/components/admin-tools.css
- ||#receiptModal .modal-body in src/styles/admin-modals.css, src/styles/main.css
- ||#receiptModal .modal-content in src/styles/admin-modals.css, src/styles/main.css
- ||body in src/styles/components/room-iframe.css, src/styles/main.css, src/styles/site-base.css
- ||body[data-page^='admin'] in src/styles/admin-settings.css, src/styles/site-base.css, src/styles/z-index.css
- ||body[data-page^='admin'] .admin-filter-form in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .admin-filter-section in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .admin-filters in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .page-content in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .settings-section in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||body[data-page^='admin'] #admin-section-content in src/styles/admin-settings.css, src/styles/main.css, src/styles/site-base.css
- ||body[data-page='admin/settings'] in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page='admin/settings'] #admin-section-content in src/styles/admin-settings.css, src/styles/site-base.css
- ||html in src/styles/components/room-iframe.css, src/styles/main.css, src/styles/site-base.css
- ||html:has(body[data-page^='admin']) in src/styles/admin-settings.css, src/styles/main.css, src/styles/site-base.css

## Notes

- This plan is non-destructive. Next step is to implement moves/merges per mapping, then re-run inventory to confirm no loss.
- Recovered and fixes buckets should be distributed into components/pages as indicated.
- Conflicts require human review to decide authoritative values.
