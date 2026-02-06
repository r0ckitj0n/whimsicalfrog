import { ItemData } from './event-utils.js';

export function createModalStructure(): { overlay: HTMLElement; content: HTMLElement } {
  if (document.getElementById('roomModalOverlay')) {
    const overlay = document.getElementById('roomModalOverlay')!;
    const content = overlay.querySelector('.room-modal-container') as HTMLElement;
    return { overlay, content };
  }

  const overlay = document.createElement('div');
  overlay.id = 'roomModalOverlay';
  overlay.className = 'room-modal-overlay';

  const content = document.createElement('div');
  content.className = 'room-modal-container';

  const header = document.createElement('div');
  header.className = 'room-modal-header';

  const backBtnWrap = document.createElement('div');
  backBtnWrap.className = 'back-button-container';
  const backBtn = document.createElement('button');
  backBtn.className = 'room-modal-back-btn';
  backBtn.textContent = '← Back to Main Room';
  backBtnWrap.appendChild(backBtn);

  const titleOverlay = document.createElement('div');
  titleOverlay.className = 'room-title-overlay';
  titleOverlay.id = 'roomTitleOverlay';

  const roomTitle = document.createElement('h1');
  roomTitle.id = 'roomTitle';
  roomTitle.textContent = 'Loading…';

  const roomDesc = document.createElement('div');
  roomDesc.className = 'room-description';
  roomDesc.id = 'roomDescription';
  titleOverlay.append(roomTitle, roomDesc);

  header.append(backBtnWrap, titleOverlay);

  const loading = document.createElement('div');
  loading.id = 'roomModalLoading';
  loading.className = 'room-modal-loading';
  loading.innerHTML = `<div class="room-modal-spinner"></div><p class="room-modal-loading-text">Loading room…</p>`;

  const iframe = document.createElement('iframe');
  iframe.id = 'roomModalFrame';
  iframe.className = 'room-modal-iframe hidden';
  iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

  content.append(header, loading, iframe);
  overlay.appendChild(content);
  document.body.appendChild(overlay);

  return { overlay, content };
}

export function setupIframeListener(iframe: HTMLIFrameElement, onItemClick: (sku: string, data: ItemData | { sku: string }) => void): void {
  iframe.addEventListener('load', () => {
    try {
      const win = iframe.contentWindow;
      const doc = win && win.document;
      if (!doc) return;
      if (doc.documentElement.hasAttribute('data-wf-item-clicks')) return;
      doc.documentElement.setAttribute('data-wf-item-clicks', '1');

      const getData = (el: HTMLElement) => {
        if (!el) return null;
        const di = el.getAttribute && el.getAttribute('data-item');
        if (di) {
          try { return JSON.parse(di); } catch { /* data-item JSON parse failed */ }
        }
        const ds = el.dataset || {};
        if (ds.sku) {
          return {
            sku: ds.sku,
            name: ds.name || '',
            price: parseFloat(ds.price || ds.cost || '0'),
            description: ds.description || '',
            stock: parseInt((ds.stock_quantity ?? ds.stock ?? '0'), 10),
            category: ds.category || '',
            image: ds.image || ''
          };
        }
        return null;
      };

      doc.addEventListener('click', (e: MouseEvent) => {
        const t = e.target as HTMLElement;
        if (!t || typeof t.closest !== 'function') return;
        const trigger = t.closest('.room-item-icon, [data-sku], [data-item], img[data-sku], img[data-item]') as HTMLElement;
        if (!trigger) return;

        const roomTarget = trigger.dataset?.room_number || trigger.dataset?.room || t.dataset?.room_number || t.dataset?.room;
        if (roomTarget) {
          if (window.roomModalManager && typeof window.roomModalManager.openRoom === 'function') {
            e.preventDefault();
            e.stopPropagation();
            window.roomModalManager.openRoom(roomTarget);
          }
          return;
        }

        const iconEl = trigger.closest('.room-item-icon') as HTMLElement || trigger;
        const data = getData(iconEl) || (iconEl.dataset && iconEl.dataset.sku ? { sku: iconEl.dataset.sku } : null);
        const sku = (data && data.sku) || (iconEl.dataset && iconEl.dataset.sku);
        if (!sku) return;

        e.preventDefault();
        e.stopPropagation();
        onItemClick(sku, data || { sku });
      }, true);
    } catch { /* Iframe content access failed - possibly cross-origin */ }
  });
}
