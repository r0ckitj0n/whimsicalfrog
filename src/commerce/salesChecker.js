// src/commerce/salesChecker.js
// Modern ES-module replacement for legacy sales-checker.js
import { apiGet } from '../core/apiClient.js';

export async function checkItemSale(sku) {
  try {
    const data = await apiGet('sales.php', { action: 'get_active_sales', item_sku: sku });
    if (data.success && data.sale) {
      return { isOnSale: true, discountPercentage: parseFloat(data.sale.discount_percentage) };
    }
  } catch (e) {
    console.warn('[SalesChecker] error checking sale', sku, e);
  }
  return { isOnSale: false };
}

export const calculateSalePrice = (price, discount) => price * (1 - discount / 100);

export async function checkAndDisplaySalePrice(item, priceEl, unitPriceEl = null) {
  if (!item || !priceEl) return;
  const sale = await checkItemSale(item.sku);
  const basePrice = parseFloat(item.retailPrice || item.price);
  if (sale.isOnSale && sale.discountPercentage > 0) {
    const salePrice = calculateSalePrice(basePrice, sale.discountPercentage);
    const html = `<span class="u-text-decoration-line-through u-color-999 u-font-size-0-9em">$${basePrice.toFixed(2)}</span>
      <span class="u-color-dc2626 u-font-weight-bold u-margin-left-5px">$${salePrice.toFixed(2)}</span>
      <span class="u-color-dc2626 u-font-size-0-8em u-margin-left-5px">(${Math.round(sale.discountPercentage)}% off)</span>`;
    priceEl.innerHTML = html;
    if (unitPriceEl) unitPriceEl.innerHTML = html;
    Object.assign(item, { salePrice, originalPrice: basePrice, isOnSale: true, discountPercentage: sale.discountPercentage });
  } else {
    priceEl.textContent = `$${basePrice.toFixed(2)}`;
    if (unitPriceEl) unitPriceEl.textContent = `$${basePrice.toFixed(2)}`;
    item.isOnSale = false;
  }
}

// Cache for badge content to avoid repeated API calls
let saleBadgeCache = null;

async function getSaleBadgeText() {
  if (saleBadgeCache) return saleBadgeCache;

  try {
    const data = await apiGet('/api/badge_content_manager.php', { action: 'get_all', badge_type: 'sale' });

    if (data.success && data.badges && data.badges.length > 0) {
      // Get a weighted random badge text
      const badges = data.badges.filter(b => b.active).sort((a, b) => b.weight - a.weight);
      if (badges.length > 0) {
        saleBadgeCache = badges[0].content; // Use highest weighted badge
        return saleBadgeCache;
      }
    }
  } catch (error) {
    console.warn('Failed to fetch dynamic sale badge text:', error);
  }

  // Fallback to default
  return 'SALE';
}

export async function addSaleBadgeToCard(card, discount) {
  if (!card) return;
  const pct = parseFloat(discount);
  if (isNaN(pct) || pct <= 0) return;
  const existing = card.querySelector('.sale-badge');
  if (existing) existing.remove();

  const saleText = await getSaleBadgeText();
  const badge = document.createElement('div');
  badge.className = 'sale-badge';
  badge.innerHTML = `<span class="sale-text">${saleText}</span><span class="sale-percentage">${Math.round(pct)}% OFF</span>`;
  card.appendChild(badge);
}

// Auto-init shop page badges
if (window.location.search.includes('page=shop')) {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sku]').forEach(async card => {
      const sku = card.getAttribute('data-sku');
      const sale = await checkItemSale(sku);
      if (sale.isOnSale) await addSaleBadgeToCard(card, sale.discountPercentage);
    });
  });
}

// Legacy global bridge
Object.assign(window, { checkItemSale, calculateSalePrice, checkAndDisplaySalePrice, addSaleBadgeToCard });
