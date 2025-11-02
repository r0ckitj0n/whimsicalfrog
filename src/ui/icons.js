// Minimal shared SVG icon system (Lucide-like)
// Exposes window.WF_Icons.applyIcons(root) and auto-observes DOM for [data-icon]

const ICONS = {
  plus: {
    box: 24,
    path: '<path d="M12 5v14"/><path d="M5 12h14"/>'
  },
  pencil: {
    box: 24,
    path: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'
  },
  'chevron-up': {
    box: 24,
    path: '<path d="m18 15-6-6-6 6"/>'
  },
  'chevron-down': {
    box: 24,
    path: '<path d="m6 9 6 6 6-6"/>'
  },
  star: {
    box: 24,
    path: '<polygon points="12 2 15 8.5 22 9.3 17 14 18.2 21 12 17.8 5.8 21 7 14 2 9.3 9 8.5 12 2"/>'
  },
  'trash-2': {
    box: 24,
    path: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'
  },
  save: {
    box: 24,
    path: '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>'
  },
  x: {
    box: 24,
    path: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'
  },
  shuffle: {
    box: 24,
    path: '<path d="M16 3h5v5"/><path d="M4 20 20 4"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/>'
  },
  copy: {
    box: 24,
    path: '<rect x="8" y="8" width="14" height="14" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2h8"/>'
  },
  target: {
    box: 24,
    path: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'
  },
  check: {
    box: 24,
    path: '<path d="M20 6 9 17l-5-5"/>'
  },
  download: {
    box: 24,
    path: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>'
  },
  'maximize-2': {
    box: 24,
    path: '<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>'
  },
  square: {
    box: 24,
    path: '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>'
  },
  undo: {
    box: 24,
    path: '<path d="M3 7v6h6"/><path d="M3 13a9 9 0 1 0 3-7.7L3 7"/>'
  },
  'refresh-ccw': {
    box: 24,
    path: '<path d="M3 2v6h6"/><path d="M21 12A9 9 0 1 1 6.3 4.7L3 8"/>'
  }
};

function createSvg(name, { size = 18, stroke = 2 } = {}) {
  const def = ICONS[name];
  if (!def) return null;
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', `0 0 ${def.box} ${def.box}`);
  svg.setAttribute('width', String(size));
  svg.setAttribute('height', String(size));
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', String(stroke));
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  svg.classList.add('icon');
  const tmp = document.createElement('template');
  tmp.innerHTML = def.path.trim();
  const nodes = tmp.content.cloneNode(true);
  svg.appendChild(nodes);
  return svg;
}

function applyIcons(scope) {
  const root = scope || document;
  const targets = root.querySelectorAll('[data-icon]');
  targets.forEach(el => {
    const name = el.getAttribute('data-icon');
    if (!name || !ICONS[name]) return;
    const sizeAttr = el.getAttribute('data-icon-size');
    const strokeAttr = el.getAttribute('data-icon-stroke');
    const size = sizeAttr ? Number(sizeAttr) : undefined;
    const stroke = strokeAttr ? Number(strokeAttr) : undefined;
    const svg = createSvg(name, { size, stroke });
    if (!svg) return;
    // Clear existing icon content but preserve aria-label/title
    while (el.firstChild) el.removeChild(el.firstChild);
    el.appendChild(svg);
    // Normalize button styling
    if (el.tagName === 'BUTTON' || el.classList.contains('btn')) {
      el.classList.add('btn-icon');
    }
  });
}

function observeMutations(){
  try {
    const mo = new MutationObserver(muts => {
      for (const m of muts) {
        if (m.type === 'childList') {
          m.addedNodes.forEach(n => {
            if (!(n instanceof Element)) return;
            if (n.hasAttribute && n.hasAttribute('data-icon')) applyIcons(n.parentElement || n);
            const nested = n.querySelectorAll && n.querySelectorAll('[data-icon]');
            if (nested && nested.length) applyIcons(n);
          });
        } else if (m.type === 'attributes' && m.target instanceof Element && m.target.hasAttribute('data-icon')) {
          applyIcons(m.target);
        }
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-icon'] });
  } catch (_) {}
}

(function init(){
  try {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => { applyIcons(document); observeMutations(); }, { once: true });
    } else { applyIcons(document); observeMutations(); }
  } catch(_) {}
})();

try { window.WF_Icons = { applyIcons }; } catch(_) {}
export default { applyIcons };
