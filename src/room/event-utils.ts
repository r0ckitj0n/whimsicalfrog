import logger from '../core/logger.js';

export interface ItemData {
  sku: string;
  name: string;
  price: number;
  description: string;
  stock: number;
  category: string;
  image: string;
  [key: string]: string | number | boolean | undefined;
}

interface WFRoomWindow {
  showGlobalPopup?: (el: HTMLElement, data: ItemData) => void;
  hideGlobalPopup?: () => void;
  scheduleHideGlobalPopup?: (ms: number) => void;
  cancelHideGlobalPopup?: () => void;
  hideGlobalPopupImmediate?: () => void;
  showGlobalItemModal?: (sku: string, data?: ItemData) => void;
  showItemDetailsModal?: (sku: string, data?: ItemData) => void;
  showItemDetails?: (sku: string, data?: ItemData) => void;
}

/** Extract item data from various legacy sources on an icon element. */
export function extractItemData(icon: HTMLElement): ItemData | null {
  if (icon.dataset.item) {
    try {
      const raw = icon.dataset.item;
      const parsed = JSON.parse(raw!) as ItemData;
      // Prefer dataset stock_quantity/stock over JSON (dataset reflects current inventory)
      const stockFromDs = parseInt((icon.dataset.stock_quantity ?? icon.dataset.stock ?? ''), 10);
      if (!Number.isNaN(stockFromDs)) parsed.stock = stockFromDs;
      if (parsed.price == null && (icon.dataset.price || icon.dataset.cost)) {
        parsed.price = parseFloat(icon.dataset.price || icon.dataset.cost || '0');
      }
      if (!parsed.image && icon.dataset.image) parsed.image = icon.dataset.image;
      if (!parsed.category && icon.dataset.category) parsed.category = icon.dataset.category;
      return parsed;
    } catch (e) {
      logger.warn('[eventManager] invalid JSON in data-item', e);
    }
  }
  if (icon.dataset.sku) {
    return {
      sku: icon.dataset.sku,
      name: icon.dataset.name || '',
      price: parseFloat(icon.dataset.price || icon.dataset.cost || '0'),
      description: icon.dataset.description || '',
      stock: parseInt((icon.dataset.stock_quantity ?? icon.dataset.stock ?? '0'), 10),
      category: icon.dataset.category || '',
      image: icon.dataset.image || ''
    };
  }
  const attr = icon.getAttribute('onmouseenter');
  if (attr) {
    const match = attr.match(/showGlobalPopup\(this,\s*(.+)\)/);
    if (match) {
      try {
        const jsonString = match[1]
          .replace(/&quot;/g, '"')
          .replace(/&#039;/g, "'");
        return JSON.parse(jsonString) as ItemData;
      } catch (e) {
        logger.warn('[eventManager] Failed to parse inline JSON', e);
      }
    }
  }
  return null;
}

// Use global popup scheduling API where available
export function getPopupApi() {
  const win = window as unknown as WFRoomWindow;
  const par = (parent !== window) ? (parent as unknown as WFRoomWindow) : null;
  
  return {
    show: win.showGlobalPopup || (par && par.showGlobalPopup),
    hide: win.hideGlobalPopup || (par && par.hideGlobalPopup),
    scheduleHide: win.scheduleHideGlobalPopup || (par && par.scheduleHideGlobalPopup),
    cancelHide: win.cancelHideGlobalPopup || (par && par.cancelHideGlobalPopup),
  };
}
