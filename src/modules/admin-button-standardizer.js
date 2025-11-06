// Standardize admin buttons: convert common text/grey buttons into icon-only or brand primary
// Also installs humorous tooltips for missing titles/aria-labels

(function(){
  if (typeof document === 'undefined') return;
  function isSettingsPage(){
    try {
      const b = document.body;
      const dp = (b && (b.getAttribute('data-page')||b.dataset.page||'')) || '';
      return !!(document.querySelector('.settings-page') || dp === 'admin/settings' || dp === 'admin-settings');
    } catch(_) { return false; }
  }
  function isMarketingPage(){
    try {
      const b = document.body;
      const dp = (b && (b.getAttribute('data-page')||b.dataset.page||'')) || '';
      if (dp.includes('admin/marketing') || dp.includes('admin-marketing')) return true;
      return !!document.querySelector('.admin-marketing-page');
    } catch(_) { return false; }
  }

  function boot(){
    const body = document.body || document.documentElement;
    const isAdmin = (body && ((body.getAttribute('data-page')||'').startsWith('admin') || body.getAttribute('data-is-admin') === 'true'));
    if (!isAdmin) return;
    // Hard opt-out on Settings page
    if (isSettingsPage()) {
      // Defensive: if anything was pre-transformed, restore labels from attributes
      try {
        const scope = document.querySelector('.settings-page') || document;
        scope.querySelectorAll('.btn-icon, .btn.btn-icon, .settings-page .btn').forEach((el)=>{
          if (!el) return;
          // If text missing, try to restore from aria-label/title
          const hasText = !!(el.textContent && el.textContent.trim());
          if (!hasText) {
            const lbl = el.getAttribute('aria-label') || el.getAttribute('title') || '';
            if (lbl) el.textContent = lbl;
          }
          el.classList.remove('btn-icon');
        });
      } catch(_) {}
      return;
    }
    // Opt-out on Marketing page: preserve labels (gear/plus/warning should include words here)
    if (isMarketingPage()) {
      return;
    }

    // Initial sweep and observer (non-Settings pages only)
    try { processAll(document); } catch(_){ }
    try {
      const obs = new MutationObserver((muts)=>{
        for (const m of muts){
          (m.addedNodes||[]).forEach((n)=>{
            if (n && n.querySelectorAll) processAll(n);
          });
        }
      });
      const root = document.body || document.documentElement;
      obs.observe(root, { subtree:true, childList:true });
    } catch(_){ }

  const ICON_MAP = {
    add: 'btn-icon btn-icon--add',
    create: 'btn-icon btn-icon--add',
    new: 'btn-icon btn-icon--add',
    edit: 'btn-icon btn-icon--edit',
    rename: 'btn-icon btn-icon--edit',
    duplicate: 'btn-icon btn-icon--duplicate',
    copy: 'btn-icon btn-icon--duplicate',
    delete: 'btn-icon btn-icon--delete',
    remove: 'btn-icon btn-icon--delete',
    view: 'btn-icon btn-icon--view',
    preview: 'btn-icon btn-icon--preview',
    'preview inline': 'btn-icon btn-icon--preview-inline',
    refresh: 'btn-icon btn-icon--refresh',
    reload: 'btn-icon btn-icon--refresh',
    send: 'btn-icon btn-icon--send',
    archive: 'btn-icon btn-icon--archive',
    settings: 'btn-icon btn-icon--settings',
    config: 'btn-icon btn-icon--settings',
    download: 'btn-icon btn-icon--download',
    upload: 'btn-icon btn-icon--upload',
    external: 'btn-icon btn-icon--external',
    link: 'btn-icon btn-icon--link',
    info: 'btn-icon btn-icon--info',
    help: 'btn-icon btn-icon--help',
    print: 'btn-icon btn-icon--print',
    up: 'btn-icon btn-icon--up',
    down: 'btn-icon btn-icon--down'
  };

  const HUMOROUS_TIPS = {
    add: 'Add it like it was meant to be there all along.',
    create: 'Summon a brand-new thing. Abracadabra.',
    new: 'Make another one. Because one is never enough.',
    edit: 'Polish it until it shines—or breaks.',
    rename: 'Witness protection program for labels.',
    duplicate: 'Copy pasta. Delicious and efficient.',
    copy: 'Yes, copy. We all do it.',
    delete: 'Send it on a permanent vacation.',
    remove: 'Make it disappear. Poof.',
    view: 'Feast your eyes (no touching).',
    preview: 'Look, but don’t commit… yet.',
    'preview inline': 'A sneak peek without leaving your cozy chair.',
    refresh: 'Shake the Etch A Sketch. Better now?',
    reload: 'Reload like it’s 2007 and Flash just crashed.',
    send: 'Fire it off. Carrier pigeons sold separately.',
    archive: 'Not gone, just “resting” in a box.',
    settings: 'Here be dragons. Adjust with care.',
    config: 'Twiddle the knobs. What’s the worst that could happen?',
    download: 'Yoink it to your device.',
    upload: 'Yeet it into the cloud.',
    external: 'Open in a new universe (tab).',
    link: 'Bridge two worlds with a click.',
    info: 'Because knowledge is power. And power is… info.',
    help: 'Press for help. Sarcasm included.',
    print: 'Murder a tree responsibly.',
    up: 'Up. Like morale after coffee.',
    down: 'Down. Like morale without coffee.',
    cancel: 'Back away slowly. No harm done.',
    close: 'Shut it down, gracefully-ish.',
    save: 'Capture your brilliance before it evaporates.',
    submit: 'Launch sequence initiated.',
    apply: 'Make it so, Number One.',
    confirm: 'You sure? Pinky swear?',
    back: 'Reverse, reverse.',
    next: 'Onward, brave soldier.',
    previous: 'Time travel, but less exciting.',
    finish: 'Stick a fork in it.',
    start: 'Begin the chaos.'
  };

  const SPECIALTY_LABELS = new Set(['cancel','close','save','submit','apply','confirm','back','next','previous','finish','start']);

  function normalize(s){ return String(s||'').trim().toLowerCase(); }

  function classify(el){
    // Prefer explicit data-action value
    const da = normalize(el.getAttribute('data-action'));
    if (da && ICON_MAP[da]) return da;
    // Text-based fallback
    const text = normalize(el.textContent || el.value || el.getAttribute('aria-label') || el.getAttribute('title'));
    if (ICON_MAP[text]) return text;
    // Heuristics for compounds
    if (/preview/.test(text)) return text.includes('inline') ? 'preview inline' : 'preview';
    if (/download/.test(text)) return 'download';
    if (/upload/.test(text)) return 'upload';
    if (/duplicate|copy/.test(text)) return 'duplicate';
    if (/delete|remove/.test(text)) return 'delete';
    if (/edit|rename/.test(text)) return 'edit';
    if (/new|add|create/.test(text)) return 'add';
    if (/archive/.test(text)) return 'archive';
    if (/refresh|reload/.test(text)) return 'refresh';
    if (/settings|config/.test(text)) return 'settings';
    if (/view|open/.test(text)) return 'view';
    if (/help|\?/.test(text)) return 'help';
    if (/info|about/.test(text)) return 'info';
    if (/print/.test(text)) return 'print';
    if (/up/.test(text)) return 'up';
    if (/down/.test(text)) return 'down';
    return '';
  }

  function ensureTooltip(el, key){
    const hasTitle = !!el.getAttribute('title');
    const hasAria = !!el.getAttribute('aria-label');
    const tip = HUMOROUS_TIPS[key] || HUMOROUS_TIPS.info;
    if (!hasTitle) el.setAttribute('title', tip);
    if (!hasAria) el.setAttribute('aria-label', tip);
  }

  function toIconButton(el, key){
    // Preserve original intent in tooltip
    ensureTooltip(el, key);
    // Remove text content for icon-only experience
    el.textContent = '';
    // Strip generic classes that force paddings
    el.classList.remove('btn','btn-secondary','btn-danger','btn-sm','btn-xs');
    // Apply icon classes
    const cls = ICON_MAP[key];
    if (cls) cls.split(/\s+/).forEach(c => c && el.classList.add(c));
  }

  function toSpecialtyPrimary(el){
    // Brand primary with label, not icon-only
    const label = (el.textContent||'').trim() || 'Action';
    const key = normalize(label);
    const tip = HUMOROUS_TIPS[key] || `${label}`;
    if (!el.getAttribute('title')) el.setAttribute('title', tip);
    if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', tip);
    // Normalize classes
    el.classList.add('btn','btn-primary');
    el.classList.remove('btn-secondary');
  }

  function processAll(root){
    const candidates = root.querySelectorAll('button, a[role="button"], .btn, [data-action]');
    candidates.forEach((el)=>{
      try{
        // Skip Settings page scope entirely
        if (isSettingsPage() && document.querySelector('.settings-page')?.contains(el)) return;
        if (el.hasAttribute('data-preserve-label')) return; // opt-out
        const key = classify(el);
        if (key){
          toIconButton(el, key);
          return;
        }
        const t = normalize(el.textContent||'');
        if (SPECIALTY_LABELS.has(t)) {
          toSpecialtyPrimary(el);
          return;
        }
        // Fallback: ensure everything has a tooltip
        const label = (el.getAttribute('aria-label') || el.getAttribute('title') || (el.textContent||'').trim());
        if (!el.getAttribute('title')) el.setAttribute('title', label ? label : 'Press this shiny button. What could go wrong?');
        if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', label ? label : 'Action');
      }catch(_){/* noop */}
    });
  }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
