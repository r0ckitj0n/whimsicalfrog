import '../styles/main.css';
import '../styles/components/components-base.css';
import '../styles/admin-modals.css';
import '../styles/components/modal.css';
import '../styles/admin-settings.css';
import '../styles/embed-iframe.css';
import '../modules/embed-autosize-child.js';
import '../modules/overlay-manager.js';
import '../js/admin-marketing.js';

// Prefer inline overlays by default; rollback flags still respected
try { if (typeof window !== 'undefined') window.__WF_INLINE_STRICT = true; } catch(_) {}

(function(){
  const variants = ['btn-primary','btn-secondary','btn-danger','btn-info','btn-warning','btn-success','btn-link','btn-xs','btn-sm','btn-lg'];
  function ensureBtnBase(el){
    if (!el || !el.classList) return;
    if (el.classList.contains('btn')) return;
    for (const v of variants){ if (el.classList.contains(v)) { el.classList.add('btn'); break; } }
  }
  function scan(root){
    try {
      const nodes = root.querySelectorAll('button, a[role="button"], a.btn-primary, a.btn-secondary, a.btn-danger, a.btn-info, a.btn-warning, a.btn-success');
      nodes.forEach(ensureBtnBase);
    } catch(_){ }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
  } else {
    scan(document);
  }
  try {
    const root = document.body || document;
    const obs = new MutationObserver((muts)=>{
      for (const m of muts){
        m.addedNodes && m.addedNodes.forEach(n=>{ if (n.nodeType===1){ ensureBtnBase(n); try { scan(n); } catch(_){} } });
      }
    });
    obs.observe(root, { subtree: true, childList: true });
    window.__wfEnsureBtnBase = () => scan(document);
  } catch(_){ try { window.__wfEnsureBtnBase = () => scan(document); } catch(_){} }
})();
