// Overlay Manager: thin abstraction over global helpers with safe fallbacks
// Exposes window.OverlayManager { open(id), close(id), isVisible(id) }

(function(){
  function basicOpen(id){
    const el = document.getElementById(id);
    if (!el) return false;
    try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
    try { el.classList.remove('hidden'); } catch(_) {}
    try { el.removeAttribute('hidden'); } catch(_) {}
    try { el.classList.add('show'); } catch(_) {}
    try { el.setAttribute('aria-hidden','false'); el.setAttribute('role','dialog'); el.setAttribute('aria-modal','true'); } catch(_) {}
    try { const header = el.querySelector('.modal-header [id]'); if (header && header.id) el.setAttribute('aria-labelledby', header.id); } catch(_) {}
    try { document.documentElement.classList.add('wf-admin-modal-open'); document.body.classList.add('wf-admin-modal-open'); } catch(_) {}
    try { const f = el.querySelector('[autofocus], button, [href], input, select, textarea'); if (f && f.focus) f.focus(); } catch(_) {}
    return true;
  }
  function basicClose(id){
    const el = document.getElementById(id);
    if (!el) return false;
    try { el.setAttribute('hidden',''); } catch(_) {}
    try { el.classList.add('hidden'); } catch(_) {}
    try { el.classList.remove('show'); } catch(_) {}
    try { el.setAttribute('aria-hidden','true'); } catch(_) {}
    try {
      const anyVisible = document.querySelector('.admin-modal-overlay.show:not(.hidden)');
      if (!anyVisible) { document.documentElement.classList.remove('wf-admin-modal-open'); document.body.classList.remove('wf-admin-modal-open'); }
    } catch(_) {}
    return true;
  }
  const api = {
    open(id){
      if (!id) return false;
      if (typeof window.__wfShowModal === 'function') return window.__wfShowModal(id);
      return basicOpen(id);
    },
    close(id){
      if (!id) return false;
      if (typeof window.__wfHideModal === 'function') return window.__wfHideModal(id);
      return basicClose(id);
    },
    isVisible(id){
      const el = document.getElementById(id);
      return !!(el && el.classList && el.classList.contains('show') && !el.classList.contains('hidden'));
    }
  };
  try { if (typeof window !== 'undefined') window.OverlayManager = api; } catch(_) {}
})();
