// src/commerce/sales-checker.ts
// Modern ES-module replacement for legacy sales-checker.js
import { ApiClient } from '../core/ApiClient.js';

export interface SaleCheckResult {
  is_on_sale: boolean;
  discount_percentage?: number;
}

interface ISaleResponse {
  success: boolean;
  sale?: {
    discount_percentage: string | number;
  };
}

export async function checkItemSale(sku: string): Promise<SaleCheckResult> {
  try {
    const data = await ApiClient.get<ISaleResponse>('sales.php', { action: 'get_active_sales', item_sku: sku });
    if (data.success && data.sale) {
      return { is_on_sale: true, discount_percentage: parseFloat(String(data.sale.discount_percentage)) };
    }
  } catch (e) {
    try {
      const win = window as Window;
      if (win.WhimsicalFrog && typeof win.WhimsicalFrog.warn === 'function') {
        win.WhimsicalFrog.warn({ msg: '[SalesChecker] error checking sale', sku, err: e });
      }
    } catch { /* WhimsicalFrog.warn failed */ }
  }
  return { is_on_sale: false };
}

export const calculateSalePrice = (price: number, discount: number): number => price * (1 - discount / 100);

interface IItemWithPricing {
  sku: string;
  retail_price?: number;
  price: number;
  sale_price?: number;
  original_price?: number;
  is_on_sale: boolean;
  discount_percentage?: number;
}

export async function checkAndDisplaySalePrice(item: IItemWithPricing, priceEl: HTMLElement, unitPriceEl: HTMLElement | null = null): Promise<void> {
  if (!item || !priceEl) return;
  const sale = await checkItemSale(item.sku);
  const basePrice = parseFloat(String(item.retail_price || item.price));
  if (sale.is_on_sale && sale.discount_percentage && sale.discount_percentage > 0) {
    const sale_price = calculateSalePrice(basePrice, sale.discount_percentage);
    const html = `<span class="u-text-decoration-line-through u-color-999 u-font-size-0-9em">$${basePrice.toFixed(2)}</span>
      <span class="u-color-dc2626 u-font-weight-bold u-margin-left-5px">$${sale_price.toFixed(2)}</span>
      <span class="u-color-dc2626 u-font-size-0-8em u-margin-left-5px">(${Math.round(sale.discount_percentage)}% off)</span>`;
    priceEl.innerHTML = html;
    if (unitPriceEl) unitPriceEl.innerHTML = html;
    item.sale_price = sale_price;
    item.original_price = basePrice;
    item.is_on_sale = true;
    item.discount_percentage = sale.discount_percentage;
  } else {
    priceEl.textContent = `$${basePrice.toFixed(2)}`;
    if (unitPriceEl) unitPriceEl.textContent = `$${basePrice.toFixed(2)}`;
    item.is_on_sale = false;
  }
}

// Cache for badge content to avoid repeated API calls
let saleBadgeCache: string | null = null;

interface IBadge {
  active: boolean | number;
  weight: number;
  content: string;
}

interface IBadgeResponse {
  success: boolean;
  badges?: IBadge[];
}

async function getSaleBadgeText(): Promise<string> {
  if (saleBadgeCache) return saleBadgeCache;

  try {
    const data = await ApiClient.get<IBadgeResponse>('/api/badge_content_manager.php', { action: 'get_all', badge_type: 'sale' });

    if (data.success && data.badges && data.badges.length > 0) {
      // Get a weighted random badge text
      const badges = data.badges.filter(b => b.active === true || b.active === 1).sort((a, b) => b.weight - a.weight);
      if (badges.length > 0) {
        saleBadgeCache = badges[0].content; // Use highest weighted badge
        return saleBadgeCache!;
      }
    }
  } catch (error) {
    try {
      if (typeof window !== 'undefined' && window.WhimsicalFrog && typeof window.WhimsicalFrog.warn === 'function') {
        window.WhimsicalFrog.warn({ msg: 'Failed to fetch dynamic sale badge text', err: error });
      }
    } catch { /* WhimsicalFrog.warn failed */ }
  }

  // Fallback to default
  return 'SALE';
}

export async function addSaleBadgeToCard(card: HTMLElement, discount: number | string): Promise<void> {
  if (!card) return;
  const pct = typeof discount === 'string' ? parseFloat(discount) : discount;
  if (isNaN(pct) || pct <= 0) return;
  const existing = card.querySelector('.sale-badge');
  if (existing) existing.remove();

  const saleText = await getSaleBadgeText();
  const badge = document.createElement('div');
  badge.className = 'sale-badge';
  badge.innerHTML = `<span class="sale-text">${saleText}</span><span class="sale-percentage">${Math.round(pct)}% OFF</span>`;
  card.appendChild(badge);
}

// Legacy global bridge
if (typeof window !== 'undefined') {
  window.checkItemSale = checkItemSale;
  window.calculateSalePrice = calculateSalePrice;
  window.checkAndDisplaySalePrice = checkAndDisplaySalePrice as (item: unknown, priceEl: HTMLElement, unitPriceEl?: HTMLElement | null) => Promise<void>;
  window.addSaleBadgeToCard = addSaleBadgeToCard;

  // Auto-init shop page badges
  if (window.location.search.includes('page=shop')) {
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-sku]').forEach(async (card) => {
        const sku = card.getAttribute('data-sku');
        if (sku) {
          const sale = await checkItemSale(sku);
          if (sale.is_on_sale) await addSaleBadgeToCard(card as HTMLElement, sale.discount_percentage!);
        }
      });
    });
  }
}
