# CSS Reorganization Plan (Non-Destructive Draft)

Generated: 2025-09-26T19:29:30.786Z

- Total CSS files analyzed: 38
- Total rules counted: 3996
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

* None found

## Duplicate Rules Across Files (Exact Matches)

- ||.loading-spinner.hidden in src/styles/admin-modals.css, src/styles/site-base.css
- ||.wf-notification-body in src/styles/components/notifications.css, src/styles/site-base.css

## Conflicting Rules Across Files (Same selector/media, different declarations)

- ||:root in src/styles/admin-settings.css, src/styles/variables.css, src/styles/z-index.css
- ||.admin-content-container in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table td in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-data-table th in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filter-button in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filter-section in src/styles/admin-filters.css, src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.admin-filters in src/styles/admin-filters.css, src/styles/components/aux-styles.css
- ||.admin-modal in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/admin/admin-legacy-modals.css
- ||.admin-modal-content in src/styles/admin-modals.css, src/styles/site-base.css
- ||.admin-modal-overlay in src/styles/admin-modals.css, src/styles/admin-settings.css
- ||.admin-modal-overlay.hidden in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/site-base.css
- ||.admin-modal-overlay.show in src/styles/admin-modals.css, src/styles/admin-settings.css, src/styles/components/aux-styles.css
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
- ||.item-popup.hidden in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup.measuring in src/styles/components/popup.css, src/styles/site-base.css
- ||.item-popup.visible in src/styles/components/popup.css, src/styles/site-base.css
- ||.loading-spinner in src/styles/admin-modals.css, src/styles/components/aux-styles.css
- ||.modal in src/styles/admin-settings.css, src/styles/site-base.css
- ||.modal-body in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||.modal-content in src/styles/admin-settings.css, src/styles/components/aux-styles.css
- ||.modal-header in src/styles/components/aux-styles.css, src/styles/components/modal.css, src/styles/site-base.css
- ||.modal-overlay in src/styles/admin-settings.css, src/styles/components/aux-styles.css, src/styles/components/modal.css
- ||.modal-overlay.active in src/styles/admin-settings.css, src/styles/site-base.css
- ||.modal-overlay.hidden in src/styles/admin-settings.css, src/styles/site-base.css
- ||.modal-sidebar in src/styles/components/modal.css, src/styles/site-base.css
- ||.modal-title in src/styles/components/modal.css, src/styles/site-base.css
- ||.mt-8 in src/styles/admin-db-status.css, src/styles/site-base.css
- ||.order-detail in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-content in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-item in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-item:hover in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-panel in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-history-title in src/styles/components/modal.css, src/styles/site-base.css
- ||.order-id in src/styles/components/modal.css, src/styles/site-base.css
- ||.room-modal-overlay in src/styles/admin-settings.css, src/styles/components/room-modal.css
- ||.room-modal-overlay.is-open in src/styles/admin-settings.css, src/styles/site-base.css
- ||.room-modal-overlay.show in src/styles/admin-settings.css, src/styles/components/room-modal.css
- ||.room-title-overlay in src/styles/components/room-main.css, src/styles/components/room-modal.css
- ||.room-title-overlay .room-description in src/styles/components/room-main.css, src/styles/site-base.css
- ||.settings-page in src/styles/admin-settings.css, src/styles/site-base.css
- ||.site-header.universal-page-header in src/styles/components/components-base.css, src/styles/components/room-modal.css
- ||.wf-admin-embed-frame in src/styles/admin-settings-extras.css, src/styles/admin-settings.css
- ||.wf-error-notification in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-close in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-close:hover in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-container in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-content in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-icon in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification-message in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification.is-visible in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-notification.slide-out in src/styles/components/notifications.css, src/styles/site-base.css
- ||.wf-success-notification in src/styles/components/notifications.css, src/styles/site-base.css
- ||#receiptModal .modal-content in src/styles/admin-modals.css, src/styles/main.css
- ||body in src/styles/main.css, src/styles/site-base.css
- ||body[data-page^='admin'] in src/styles/admin-settings.css, src/styles/site-base.css, src/styles/z-index.css
- ||body[data-page^='admin'] .admin-filter-form in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .admin-filter-section in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .admin-filters in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .page-content in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page^='admin'] .settings-section in src/styles/components/aux-styles.css, src/styles/site-base.css
- ||body[data-page^='admin'] #admin-section-content in src/styles/admin-settings.css, src/styles/main.css, src/styles/site-base.css
- ||body[data-page='admin/settings'] in src/styles/admin-settings.css, src/styles/site-base.css
- ||body[data-page='admin/settings'] #admin-section-content in src/styles/admin-settings.css, src/styles/site-base.css
- ||html in src/styles/main.css, src/styles/site-base.css
- ||html:has(body[data-page^='admin']) in src/styles/admin-settings.css, src/styles/main.css, src/styles/site-base.css

## Notes

- This plan is non-destructive. Next step is to implement moves/merges per mapping, then re-run inventory to confirm no loss.
- Recovered and fixes buckets should be distributed into components/pages as indicated.
- Conflicts require human review to decide authoritative values.
