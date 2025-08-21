// Deprecated duplicate. Forward-load the canonical script at /js/detailed-item-modal.js.
(function () {
  try {
    if (window.showDetailedModalComponent) return;
    var s = document.createElement('script');
    s.src = '/js/detailed-item-modal.js';
    s.onload = function(){ console.log('[WF] Loaded canonical detailed-item-modal.js via shim'); };
    s.onerror = function(){ console.warn('[WF] Failed to load canonical detailed-item-modal.js from shim'); };
    document.body.appendChild(s);
  } catch (e) {
    // no-op
  }
})();
