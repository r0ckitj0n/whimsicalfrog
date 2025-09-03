// Admin Settings Wrapper: conditional sandbox to isolate lockups
// URL flags examples (use on the settings route):
//  - ?disable_settings_module=1        -> do not load admin-settings.js at all
//  - ?wf_block_click_listeners=1       -> block adding 'click' listeners during module init
//  - ?wf_block_wheel_listeners=1       -> block 'wheel' and 'touchmove' listeners during init
//  - ?wf_block_key_listeners=1         -> block 'keydown'/'keyup' listeners during init
//  - ?wf_block_capture_true=1          -> block listeners that request {capture:true}
//  - ?wf_block_mutation_observer=1     -> stub MutationObserver
//  - ?wf_block_perf_observer=1         -> stub PerformanceObserver
//  - ?wf_block_setinterval=1           -> stub setInterval (no-ops)
//  - ?wf_block_animationframe=1        -> stub requestAnimationFrame (no-ops)

(function() {
  try {
    const params = new URLSearchParams(window.location.search || '');

    // Respect global module disable switch (handled earlier in app.js)
    if (params.get('disable_settings_module') === '1') {
      console.warn('[AdminSettingsWrapper] Skipping admin-settings.js due to ?disable_settings_module=1');
      return; // early exit, nothing else to do
    }

    // Decide what to block during module evaluation/initialization
    const block = {
      click: params.get('wf_block_click_listeners') === '1',
      wheel: params.get('wf_block_wheel_listeners') === '1',
      key: params.get('wf_block_key_listeners') === '1',
      capture: params.get('wf_block_capture_true') === '1',
      mutation: params.get('wf_block_mutation_observer') === '1',
      perf: params.get('wf_block_perf_observer') === '1',
      interval: params.get('wf_block_setinterval') === '1',
      raf: params.get('wf_block_animationframe') === '1',
    };

    // Safe mode is now opt-in only via ?wf_safe_mode=1
    // Normal behavior: do NOT block primitives unless explicitly requested.
    const isSettingsRoute = (location && /\/admin\/settings\b/.test(location.pathname));
    const safeDefault = isSettingsRoute && params.get('wf_safe_mode') === '1';
    const SAFE_MS = 5000; // 5 seconds
    const timers = [];
    const safeRestoreFns = [];

    if (safeDefault) {
      if (!block.mutation) block.mutation = true;
      if (!block.interval) block.interval = true;
      if (!block.raf) block.raf = true;
    }

    const filtersActive = Object.values(block).some(Boolean);
    const restoreFns = [];

    function wrapAddEventListener(target, label) {
      const original = target.addEventListener;
      function filteredAddEventListener(type, listener, options) {
        try {
          const typeStr = String(type || '');
          const opts = (typeof options === 'boolean') ? { capture: options } : (options || {});

          if (block.capture && opts && opts.capture === true) {
            console.warn(`[AdminSettingsWrapper] Blocked ${label}.addEventListener(${typeStr}) with capture:true`);
            return; // skip
          }
          if (block.click && typeStr === 'click') {
            console.warn(`[AdminSettingsWrapper] Blocked ${label}.addEventListener(click)`);
            return;
          }
          if (block.wheel && (typeStr === 'wheel' || typeStr === 'touchmove' || typeStr === 'touchstart' || typeStr === 'touchend' || typeStr === 'pointermove')) {
            console.warn(`[AdminSettingsWrapper] Blocked ${label}.addEventListener(${typeStr})`);
            return;
          }
          if (block.key && (typeStr === 'keydown' || typeStr === 'keyup' || typeStr === 'keypress')) {
            console.warn(`[AdminSettingsWrapper] Blocked ${label}.addEventListener(${typeStr})`);
            return;
          }
        } catch (_) {}
        return original.call(this, type, listener, options);
      }
      target.addEventListener = filteredAddEventListener;
      restoreFns.push(() => { try { target.addEventListener = original; } catch(_) {} });
    }

    if (filtersActive) {
      console.warn('[AdminSettingsWrapper] Install listener filters:', block);
      try { wrapAddEventListener(window, 'window'); } catch(_) {}
      try { wrapAddEventListener(document, 'document'); } catch(_) {}
      try { wrapAddEventListener(EventTarget.prototype, 'EventTarget'); } catch(_) {}
    }

    if (!filtersActive) {
      console.info('[AdminSettingsWrapper] Heavy mode enabled (no blocking). Use ?wf_safe_mode=1 to enable temporary guards.');
    }

    if (block.mutation && typeof window.MutationObserver === 'function') {
      console.warn('[AdminSettingsWrapper] Stubbing MutationObserver');
      const OriginalMutationObserver = window.MutationObserver;
      window.MutationObserver = function() { return { observe() {}, disconnect() {}, takeRecords() { return []; } }; };
      const restore = () => { try { window.MutationObserver = OriginalMutationObserver; } catch(_) {} };
      restoreFns.push(restore);
      if (safeDefault) safeRestoreFns.push(restore);
    }

    if (block.perf && typeof window.PerformanceObserver === 'function') {
      console.warn('[AdminSettingsWrapper] Stubbing PerformanceObserver');
      const OriginalPO = window.PerformanceObserver;
      window.PerformanceObserver = function() { return { observe() {}, disconnect() {} }; };
      restoreFns.push(() => { try { window.PerformanceObserver = OriginalPO; } catch(_) {} });
    }

    if (block.interval) {
      console.warn('[AdminSettingsWrapper] Stubbing setInterval');
      const _setInterval = window.setInterval;
      window.setInterval = function() { return 0; };
      const restore = () => { try { window.setInterval = _setInterval; } catch(_) {} };
      restoreFns.push(restore);
      if (safeDefault) safeRestoreFns.push(restore);
    }

    if (block.raf) {
      console.warn('[AdminSettingsWrapper] Stubbing requestAnimationFrame');
      const _raf = window.requestAnimationFrame;
      window.requestAnimationFrame = function() { return 0; };
      const restore = () => { try { window.requestAnimationFrame = _raf; } catch(_) {} };
      restoreFns.push(restore);
      if (safeDefault) safeRestoreFns.push(restore);
    }

    // Auto-restore safe defaults after SAFE_MS unless user opted in to keep heavy blocking
    if (safeDefault && SAFE_MS > 0) {
      timers.push(setTimeout(() => {
        try { safeRestoreFns.forEach(fn => { try { fn(); } catch(_) {} }); } catch(_) {}
      }, SAFE_MS));
    }

    // Short-lived guard: prevent auto-opening modals during initial load unless user interacts
    (function installAutoOpenGuard(){
      try {
        const GUARD_MS = 1500;
        const start = Date.now();
        let userInteracted = false;
        const markInteract = () => { userInteracted = true; cleanup(); };
        window.addEventListener('pointerdown', markInteract, { once: true, capture: true });
        window.addEventListener('keydown', markInteract, { once: true, capture: true });

        const shouldGuard = () => !userInteracted && (Date.now() - start) < GUARD_MS;

        // Guard window.openModal if present/added later
        const _defineGuardedOpen = () => {
          try {
            if (!shouldGuard()) return;
            const w = window;
            const orig = w.openModal;
            if (orig && !orig.__wfGuarded) {
              const wrapped = function(id){
                if (shouldGuard()) {
                  console.warn('[AdminSettingsWrapper] Blocked auto-open modal:', id);
                  return; // ignore until user interaction or timeout
                }
                return orig.apply(this, arguments);
              };
              wrapped.__wfGuarded = true;
              w.openModal = wrapped;
              restoreFns.push(() => { try { w.openModal = orig; } catch(_){} });
            }
          } catch(_) {}
        };

        // Patch Element.prototype.classList.add to ignore adding 'show' on overlay-like modals briefly
        const CL = (Element.prototype && Element.prototype.classList);
        if (CL && CL.add && !CL.add.__wfGuarded) {
          const origAdd = CL.add;
          const isOverlayEl = (el) => {
            try {
              if (!el || !el.id) return false;
              const id = String(el.id || '');
              const cls = String(el.className || '');
              return /Modal$/.test(id) || /(\b|_)(modal-overlay|admin-modal-overlay|room-modal-overlay)(\b|_)/.test(cls);
            } catch(_) { return false; }
          };
          const wrappedAdd = function(){
            try {
              if (shouldGuard() && arguments && arguments.length) {
                const el = this && this.ownerElement ? this.ownerElement : (this && this["__ownerEl"]) || null;
                // Attempt to derive the element from TokenList; fallback via hidden property injection
                const tokenArgs = Array.prototype.slice.call(arguments);
                if (!el) {
                  // best-effort: many engines bind classList to a specific element accessible via this.value; skip if unknown
                }
                const addingShow = tokenArgs.some(t => String(t) === 'show');
                if (addingShow) {
                  // If this looks like an overlay modal, block
                  const targetEl = (this && this._owner) || (this && this.__owner) || undefined; // unlikely
                  const maybeEl = (targetEl && targetEl.nodeType === 1) ? targetEl : undefined;
                  if (maybeEl ? isOverlayEl(maybeEl) : true) {
                    console.warn('[AdminSettingsWrapper] Blocked auto-add of .show on modal');
                    return; // swallow during guard window
                  }
                }
              }
            } catch(_) {}
            return origAdd.apply(this, arguments);
          };
          wrappedAdd.__wfGuarded = true;
          // Note: TokenList lacks a standard way to reach the element; this guard is best-effort.
          CL.add = wrappedAdd;
          restoreFns.push(() => { try { CL.add = origAdd; } catch(_){} });
        }

        // Periodically attempt to guard openModal in case it is defined later
        const guardTimer = setInterval(_defineGuardedOpen, 50);

        function cleanup(){
          try { clearInterval(guardTimer); } catch(_) {}
        }

        // Auto cleanup after GUARD_MS
        setTimeout(() => { cleanup(); }, GUARD_MS + 50);

        // Run once immediately
        _defineGuardedOpen();
      } catch(_) {}
    })();

    // Now load the heavy module under these guards
    import('./admin-settings.js')
      .then(() => {
        console.log('[AdminSettingsWrapper] admin-settings.js loaded');
        // Keep filters in place; do not auto-restore, as the module may add late listeners
      })
      .catch(err => {
        console.error('[AdminSettingsWrapper] Failed to load admin-settings.js', err);
        // Restore on failure
        restoreFns.forEach(fn => { try { fn(); } catch(_) {} });
      });

  } catch (e) {
    console.error('[AdminSettingsWrapper] Wrapper failed', e);
  }
})();
