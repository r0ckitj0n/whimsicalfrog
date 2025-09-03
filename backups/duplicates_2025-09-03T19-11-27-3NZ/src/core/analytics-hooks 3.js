'use strict';

// Site-wide analytics hooks using window.wfAnalytics
// Non-invasive, resilient to missing DOM structures

(function initAnalyticsHooks(){
  try {
    if (typeof window === 'undefined') return;
    if (window.__wfAnalyticsHooks) return;
    window.__wfAnalyticsHooks = true;

    function track(name, props) {
      try { window.wfAnalytics && window.wfAnalytics.track && window.wfAnalytics.track(name, props || {}); } catch(_) {}
    }

    // 1) Page view on load
    function pageView() {
      try {
        track('wf:page_view', {
          title: document?.title || '',
          referrer: document?.referrer || '',
        });
      } catch(_) {}
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', pageView, { once: true });
    } else {
      setTimeout(pageView, 0);
    }

    // 2) Navigation clicks (internal links)
    document.addEventListener('click', (e) => {
      try {
        const a = e.target && (e.target.closest ? e.target.closest('a[href]') : null);
        if (!a) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        // Ignore hash-only navigations
        if (href === '#' || href.startsWith('#')) return;
        // Consider internal links only
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) return;
        track('wf:navigation_click', {
          href: url.pathname + url.search + url.hash,
          text: (a.textContent || '').trim().slice(0, 100),
          id: a.id || null,
          classes: a.className || '',
        });
      } catch(_) {}
    });

    // 3) Search submits (heuristic)
    function onSearchSubmit(form) {
      try {
        const qEl = form.querySelector('input[type="search"], input[name="q"], input[name="query"], input[name="s"]');
        const q = (qEl && qEl.value || '').trim();
        if (!q) return;
        track('wf:search_submit', { query: q, action: form.getAttribute('action') || window.location.pathname });
      } catch(_) {}
    }
    document.addEventListener('submit', (e) => {
      const form = e.target;
      try {
        if (!form || form.nodeName !== 'FORM') return;
        const isSearch = form.getAttribute('role') === 'search' ||
                         !!form.querySelector('input[type="search"], input[name="q"], input[name="query"], input[name="s"]');
        if (isSearch) onSearchSubmit(form);
      } catch(_) {}
    }, true);

    // 4) Cart lifecycle via global events from CartSystem
    window.addEventListener('cartUpdated', (e) => {
      try {
        const detail = e?.detail || {};
        const state = detail.state || {};
        const items = Array.isArray(state.items) ? state.items : [];
        const count = items.reduce((n, it) => n + (Number(it.quantity) || 0), 0);
        const subtotal = Number(state.total || state.subtotal || 0);
        track('wf:cart:updated', {
          action: detail.action || 'unknown',
          count,
          subtotal,
        });
      } catch(_) {}
    });

    // 5) Product item clicks (best-effort): elements carrying data-sku or data-item-id
    document.addEventListener('click', (e) => {
      try {
        const el = e.target && (e.target.closest ? e.target.closest('[data-sku], [data-item-id]') : null);
        if (!el) return;
        const sku = el.getAttribute('data-sku');
        const itemId = el.getAttribute('data-item-id');
        track('wf:product_click', { sku: sku || null, itemId: itemId || null });
      } catch(_) {}
    }, true);

    // 6) Generic form errors and successes (listen to custom events if emitted elsewhere)
    window.addEventListener('wf:form:success', (e) => track('wf:form:success', e?.detail || {}));
    window.addEventListener('wf:form:error', (e) => track('wf:form:error', e?.detail || {}));

    // 7) Error monitoring
    // JS runtime errors
    try {
      window.addEventListener('error', (e) => {
        try {
          // Resource error (script/img/css) vs runtime
          if (e?.error) {
            track('wf:error', { message: String(e.error?.message || e.message || 'error'), filename: e.filename || null, lineno: e.lineno || null, colno: e.colno || null, stack: String(e.error?.stack || '') });
          } else if (e?.target && (e.target.src || e.target.href)) {
            const el = e.target; const src = el.src || el.href || '';
            track('wf:error:resource', { tag: el.tagName, src });
          }
        } catch(_) {}
      }, true);
      window.addEventListener('unhandledrejection', (e) => {
        try { const reason = e?.reason; track('wf:error:unhandledrejection', { message: String(reason?.message || reason || 'unhandledrejection'), stack: String(reason?.stack || '') }); } catch(_) {}
      });
    } catch(_) {}

    // 8) Performance metrics (basic web vitals-lite)
    try {
      // TTFB from Navigation Timing
      const nav = (performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation')[0] : null;
      if (nav) {
        const ttfb = Math.max(0, nav.responseStart);
        track('wf:perf:ttfb', { ttfb });
      }

      // LCP
      let lcpValue = 0; let lcpReported = false;
      if ('PerformanceObserver' in window) {
        try {
          const lcpObserver = new PerformanceObserver((entryList) => {
            const entries = entryList.getEntries();
            const last = entries[entries.length - 1];
            if (last) lcpValue = last.startTime;
          });
          lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
          const reportLCP = () => { if (!lcpReported) { lcpReported = true; try { lcpObserver.disconnect(); } catch(_) {} track('wf:perf:lcp', { lcp: Math.round(lcpValue) }); } };
          document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') reportLCP(); }, { once: true });
          window.addEventListener('pagehide', reportLCP, { once: true });
        } catch(_) {}
      }

      // CLS
      let clsValue = 0; let clsReported = false;
      if ('PerformanceObserver' in window) {
        try {
          const clsObserver = new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
              const shift = entry;
              if (!shift.hadRecentInput) clsValue += shift.value || 0;
            }
          });
          clsObserver.observe({ type: 'layout-shift', buffered: true });
          const reportCLS = () => { if (!clsReported) { clsReported = true; try { clsObserver.disconnect(); } catch(_) {} track('wf:perf:cls', { cls: Number(clsValue.toFixed(4)) }); } };
          document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') reportCLS(); }, { once: true });
          window.addEventListener('pagehide', reportCLS, { once: true });
        } catch(_) {}
      }
    } catch(_) {}

  } catch (err) {
    try { console.warn('[wfAnalyticsHooks] init failed', err); } catch(_) {}
  }
})();
