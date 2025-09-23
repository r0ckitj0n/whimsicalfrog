# Admin Modal Markup Guide

## Primary Overlay + Panel (Template)
Replace ENTITY with inventory/order/customer.

```html
<div id="ENTITYModalOuter" class="admin-modal-overlay fixed inset-0 z-50 bg-black/50 flex items-start justify-center overflow-y-auto" data-action="close-ENTITY-editor-on-overlay" role="dialog" aria-modal="true" aria-hidden="false">
  <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-5xl">
    <div class="modal-header flex justify-between items-center p-4 border-b border-gray-200">
      <h2 class="text-lg font-bold text-green-700">Edit ENTITY</h2>
      <a href="/admin/ENTITY" class="text-gray-500 hover:text-gray-700" aria-label="Close" data-action="close-ENTITY-editor">&times;</a>
    </div>
    <div class="modal-body p-4">
      <form id="ENTITYForm" method="POST" action="#" class="flex flex-col gap-6">
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="btn" data-action="close-ENTITY-editor">Cancel</button>
          <button type="submit" class="btn btn-primary" data-action="save-ENTITY">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
```

## Confirmation Modal (Reusable)
IDs must match JS: confirmationModalTitle/Message/Confirm/Cancel.

```html
<div id="confirmationModal" class="admin-modal-overlay hidden fixed inset-0 z-50 bg-black/50 flex items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-md">
    <div class="modal-header p-3 border-b border-gray-200">
      <h4 id="confirmationModalTitle" class="text-base font-semibold text-gray-800">Confirm Action</h4>
    </div>
    <div class="modal-body p-4">
      <p id="confirmationModalMessage" class="text-sm text-gray-700">Are you sure?</p>
    </div>
    <div class="modal-footer p-3 border-t border-gray-200 flex justify-end gap-2">
      <button type="button" id="confirmationModalCancel" class="btn">Cancel</button>
      <button type="button" id="confirmationModalConfirm" class="btn btn-primary">Confirm</button>
    </div>
  </div>
</div>
```

## Notes
- Use classes for styling; keep data-action strictly for JS behavior.
- Overlay: fixed inset-0 z-50 bg-black/50; Panel: white rounded shadow, max width.
- Replace ENTITY consistently across IDs and actions.
- On save, post form via AJAX and redirect to canonical editor URL.
