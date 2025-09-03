'use strict';

// Unified analytics adapter: window.wfAnalytics
// - Provides a single track() API that fans out to available providers
// - Adds basic page/user context
// - Forwards WhimsicalFrog CustomEvents (prefix `wf:`) to track()
// Safe to include multiple times; will no-op on re-init.

(function initWfAnalytics(){
  try {
    if (typeof window === 'undefined') return;
    if (window.wfAnalytics && window.wfAnalytics.__initialized) return;

    const ctx = {
      get page() { try { return window.location?.pathname || '/'; } catch(_) { return '/'; } },
      get url() { try { return window.location?.href || ''; } catch(_) { return ''; } },
      get userId() {
        try {
          const d = document && document.body && document.body.dataset;
          return d?.userIdRaw || d?.userId || null;
        } catch(_) { return null; }
      },
    };

    function withContext(props) {
      const base = props && typeof props === 'object' ? { ...props } : {};
      if (!('page' in base)) base.page = ctx.page;
      if (!('url' in base)) base.url = ctx.url;
      if (!('userId' in base) && ctx.userId) base.userId = ctx.userId;
      return base;
    }

    // Helpers
    function getCurrency() {
      try {
        const d = document && document.body && document.body.dataset;
        return (d?.currency || d?.currencyCode || 'USD').toUpperCase();
      } catch(_) { return 'USD'; }
    }

    function toItemArray(props) {
      try {
        const sku = props?.sku || props?.id || props?.itemId || props?.baseSku;
        const quantity = Number(props?.quantity || props?.qty || 1);
        const price = Number(props?.price || props?.value || 0);
        const name = props?.name || props?.title || undefined;
        const item = { item_id: String(sku || ''), item_name: name, price, quantity };
        return sku ? [item] : [];
      } catch(_) { return []; }
    }

    function mapForGA4(event, props) {
      const currency = getCurrency();
      const items = toItemArray(props);
      const total = Number(props?.total || props?.value || props?.price || 0);
      const orderId = props?.orderId || props?.transaction_id || undefined;
      switch (event) {
        case 'wf:page_view':
          return { name: 'page_view', params: {} };
        case 'wf:product_view':
          return { name: 'view_item', params: { currency, value: total || (items[0]?.price || 0), items } };
        case 'wf:product_add_attempt':
        case 'wf:cart:add':
          return { name: 'add_to_cart', params: { currency, value: total || (items[0]?.price || 0), items } };
        case 'wf:cart:remove':
          return { name: 'remove_from_cart', params: { currency, items } };
        case 'wf:checkout:order_start':
          return { name: 'begin_checkout', params: { currency, value: total || 0, items } };
        case 'wf:checkout:order_success':
          return { name: 'purchase', params: { currency, value: total || 0, transaction_id: orderId || String(Date.now()), items } };
        default:
          return null;
      }
    }

    function mapForSegment(event, props) {
      // Keep original name for Segment; many workspaces map in CDP. Provide ecomm aliases too.
      switch (event) {
        case 'wf:product_view': return { name: 'Product Viewed', properties: props };
        case 'wf:product_add_attempt':
        case 'wf:cart:add': return { name: 'Product Added', properties: props };
        case 'wf:cart:remove': return { name: 'Product Removed', properties: props };
        case 'wf:checkout:order_start': return { name: 'Checkout Started', properties: props };
        case 'wf:checkout:order_success': return { name: 'Order Completed', properties: props };
        default: return null;
      }
    }

    // Providers (all optional)
    function toSegment(event, props) {
      try {
        if (!(window.analytics && typeof window.analytics.track === 'function')) return;
        const mapped = mapForSegment(event, props);
        if (mapped) { window.analytics.track(mapped.name, withContext(mapped.properties || {})); return; }
        window.analytics.track(event, props);
      } catch(_) {}
    }
    function toGtag(event, props) {
      try {
        if (typeof window.gtag !== 'function') return;
        const mapped = mapForGA4(event, props);
        if (mapped) { window.gtag('event', mapped.name, mapped.params); return; }
        window.gtag('event', event, props);
      } catch(_) {}
    }
    function toDataLayer(event, props) {
      try {
        const dl = (window.dataLayer || (window.dataLayer = []));
        // Push raw event for flexibility
        dl.push({ event, ...props });
        // Additionally push GA4 ecommerce-mapped events where applicable
        const mapped = mapForGA4(event, props);
        if (mapped) {
          // Reset ecommerce object as recommended by GA4
          dl.push({ ecommerce: null });
          dl.push({ event: mapped.name, ecommerce: mapped.params });
        }
      } catch(_) {}
    }
    function toPlausible(event, props) {
      try { if (window.plausible) window.plausible(event, { props }); } catch(_) {}
    }
    function toPostHog(event, props) {
      try { if (window.posthog && typeof window.posthog.capture === 'function') window.posthog.capture(event, props); } catch(_) {}
    }

    function track(eventName, props = {}) {
      const name = String(eventName || '').trim();
      if (!name) return;
      const payload = withContext(props);
      // Fan-out
      toSegment(name, payload);
      toGtag(name, payload);
      toDataLayer(name, payload);
      toPlausible(name, payload);
      toPostHog(name, payload);
      try { console.debug('[wfAnalytics]', name, payload); } catch(_) {}
    }

    function identify(userId, traits = {}) {
      try { if (window.analytics && typeof window.analytics.identify === 'function') window.analytics.identify(userId, traits); } catch(_) {}
      try { if (window.posthog && typeof window.posthog.identify === 'function') window.posthog.identify(userId, traits); } catch(_) {}
    }

    // Listen for WhimsicalFrog CustomEvents and forward to track()
    function forwardCustomEvent(e) {
      try {
        const t = e?.type || 'wf:event';
        const detail = e?.detail || {};
        track(t, detail);
      } catch(_) {}
    }

    // Attach once
    try {
      window.addEventListener('wf:ready', forwardCustomEvent);
      window.addEventListener('wf:login-success', forwardCustomEvent);
      window.addEventListener('wf:logout', forwardCustomEvent);
      // Checkout milestones (from payment-modal.js)
      const checkoutEvents = [
        'wf:checkout:open',
        'wf:checkout:order_summary_viewed',
        'wf:checkout:address_list_viewed',
        'wf:checkout:address_select',
        'wf:checkout:address_add',
        'wf:checkout:address_add_error',
        'wf:checkout:shipping_method_change',
        'wf:checkout:payment_method_select',
        'wf:checkout:pricing_updated',
        'wf:checkout:pricing_error',
        'wf:checkout:order_start',
        'wf:checkout:order_success',
        'wf:checkout:order_failure',
      ];
      checkoutEvents.forEach(ev => window.addEventListener(ev, forwardCustomEvent));
    } catch(_) {}

    window.wfAnalytics = {
      track,
      identify,
      __initialized: true,
    };

    // Emit ready event
    try { window.dispatchEvent(new CustomEvent('wf:analytics-ready', { detail: { provider: 'wfAnalytics' } })); } catch(_) {}
    try { console.log('[wfAnalytics] initialized'); } catch(_) {}
  } catch (err) {
    try { console.warn('[wfAnalytics] init failed', err); } catch(_) {}
  }
})();
