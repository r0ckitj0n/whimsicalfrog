/**
 * WhimsicalFrog Sales System Module
 * Handles sale checking, pricing, and product popups - Vite compatible
 * Recovered and consolidated from legacy files
 */

import '../styles/sales-system.css';

// Runtime-injected classes for popup positioning (no inline styles)
const SALES_PP_STYLE_ID = 'sales-popup-position-classes';
const salesPopupLefts = new Set();
const salesPopupTops = new Set();
function ensureSalesPopupLeftClass(px) {
  const p = Math.max(0, Math.round((Number(px) || 0) / 5) * 5);
  const cls = `pp-left-${p}`;
  if (salesPopupLefts.has(p)) return cls;
  let styleEl = document.getElementById(SALES_PP_STYLE_ID);
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = SALES_PP_STYLE_ID;
    document.head.appendChild(styleEl);
  }
  styleEl.appendChild(document.createTextNode(`.${cls}{left:${p}px;}`));
  salesPopupLefts.add(p);
  return cls;
}
function ensureSalesPopupTopClass(px) {
  const p = Math.max(0, Math.round((Number(px) || 0) / 5) * 5);
  const cls = `pp-top-${p}`;
  if (salesPopupTops.has(p)) return cls;
  let styleEl = document.getElementById(SALES_PP_STYLE_ID);
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = SALES_PP_STYLE_ID;
    document.head.appendChild(styleEl);
  }
  styleEl.appendChild(document.createTextNode(`.${cls}{top:${p}px;}`));
  salesPopupTops.add(p);
  return cls;
}

class SalesSystem {
    constructor() {
        this.hoverTimeout = null;
        this.hideTimeout = null;
        this.currentPopup = null;
        
        this.init();
    }

    init() {
        console.log('[Sales] System initializing...');
        this.setupEventListeners();
        this.initializePageSpecificFeatures();
    }

    // Check if an item is on sale
    async checkItemSale(itemSku) {
        try {
            const response = await fetch(`sales.php?action=get_active_sales&item_sku=${itemSku}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.sale) {
                return {
                    isOnSale: true,
                    discountPercentage: parseFloat(data.sale.discount_percentage),
                    salePrice: null, // Will be calculated based on original price
                    originalPrice: null // Will be set by calling function
                };
            }
            return { isOnSale: false };
        } catch (error) {
            console.log('[Sales] Error checking sale for', itemSku, error);
            return { isOnSale: false };
        }
    }

    // Calculate sale price based on discount percentage
    calculateSalePrice(originalPrice, discountPercentage) {
        return originalPrice * (1 - discountPercentage / 100);
    }

    // Enhanced function to check and display sale pricing
    async checkAndDisplaySalePrice(item, priceElement, unitPriceElement = null, _context = 'popup') {
        if (!item || !priceElement) return;
        
        try {
            const saleData = await this.checkItemSale(item.sku);
            
            if (saleData.isOnSale && saleData.discountPercentage) {
                const originalPrice = parseFloat(item.retailPrice || item.price);
                const validDiscountPercentage = parseFloat(saleData.discountPercentage);
                
                // Validate the discount percentage
                if (isNaN(validDiscountPercentage) || validDiscountPercentage <= 0) {
                    console.error('[Sales] Invalid discount percentage:', saleData.discountPercentage);
                    // Fall back to regular price display
                    const price = parseFloat(item.retailPrice || item.price);
                    priceElement.textContent = `$${price.toFixed(2)}`;
                    if (unitPriceElement) {
                        unitPriceElement.textContent = `$${price.toFixed(2)}`;
                    }
                    return;
                }

                const salePrice = this.calculateSalePrice(originalPrice, validDiscountPercentage);
                
                // Display sale pricing with strikethrough original price
                const discountText = `${validDiscountPercentage}% off`;
                priceElement.innerHTML = `
                    <span class="sale-price">$${salePrice.toFixed(2)}</span>
                    <span class="original-price">$${originalPrice.toFixed(2)}</span>
                    <span class="discount-badge">${discountText}</span>
                `;
                
                if (unitPriceElement) {
                    unitPriceElement.innerHTML = `
                        <span class="sale-price">$${salePrice.toFixed(2)}</span>
                        <span class="original-price">$${originalPrice.toFixed(2)}</span>
                    `;
                }
                
                // Update item data for cart system
                item.salePrice = salePrice;
                item.originalPrice = originalPrice;
                item.price = salePrice; // Use sale price for cart
                
            } else {
                // Regular price display
                const price = parseFloat(item.retailPrice || item.price);
                priceElement.textContent = `$${price.toFixed(2)}`;
                if (unitPriceElement) {
                    unitPriceElement.textContent = `$${price.toFixed(2)}`;
                }
            }
        } catch (error) {
            console.error('[Sales] Error in checkAndDisplaySalePrice:', error);
            // Fallback to regular price
            const price = parseFloat(item.retailPrice || item.price);
            priceElement.textContent = `$${price.toFixed(2)}`;
            if (unitPriceElement) {
                unitPriceElement.textContent = `$${price.toFixed(2)}`;
            }
        }
    }

    // Add sale badge to product cards
    addSaleBadgeToCard(itemCard, discountPercentage) {
        if (!itemCard || !discountPercentage) return;

        // Remove existing sale badge
        const existingBadge = itemCard.querySelector('.sale-badge');
        if (existingBadge) {
            existingBadge.remove();
        }

        // Create new sale badge
        const saleBadge = document.createElement('div');
        saleBadge.className = 'sale-badge';
        saleBadge.textContent = `${discountPercentage}% OFF`;

        // Ensure parent has relative positioning
        itemCard.classList.add('has-sale-badge');

        itemCard.appendChild(saleBadge);
    }

    // Product hover popup functionality
    hidePopup() {
        if (this.currentPopup) {
            this.currentPopup.classList.remove('show');
        }
    }

    keepPopupVisible() {
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
    }

    async updatePopupContent(popup, item) {
        if (!popup || !item) return;

        const popupContent = popup.querySelector('.popup-content') || popup;
        
        // Create loading state
        popupContent.innerHTML = '<div class="popup-loading">Loading...</div>';

        try {
            // Check for sale pricing
            const saleData = await this.checkItemSale(item.sku);
            let priceHtml = '';
            
            if (saleData.isOnSale && saleData.discountPercentage) {
                const originalPrice = parseFloat(item.retailPrice || item.price);
                const salePrice = this.calculateSalePrice(originalPrice, saleData.discountPercentage);
                priceHtml = `
                    <div class="popup-price">
                        <span class="sale-price">$${salePrice.toFixed(2)}</span>
                        <span class="original-price">$${originalPrice.toFixed(2)}</span>
                        <span class="discount-badge">${saleData.discountPercentage}% OFF</span>
                    </div>
                `;
            } else {
                const price = parseFloat(item.retailPrice || item.price);
                priceHtml = `<div class="popup-price">$${price.toFixed(2)}</div>`;
            }

            // Update popup content
            popupContent.innerHTML = `
                <div class="popup-header">
                    <h3 class="popup-title">${this.escapeHtml(item.name || item.sku)}</h3>
                </div>
                <div class="popup-body">
                    ${item.image ? `<img src="${item.image}" alt="${this.escapeHtml(item.name || item.sku)}" class="popup-image">` : ''}
                    ${priceHtml}
                    ${item.description ? `<p class="popup-description">${this.escapeHtml(item.description)}</p>` : ''}
                    <button class="add-to-cart-popup" data-sku="${item.sku}">Add to Cart</button>
                </div>
            `;

        } catch (error) {
            console.error('[Sales] Error updating popup content:', error);
            popupContent.innerHTML = `
                <div class="popup-error">
                    <h3>${this.escapeHtml(item.name || item.sku)}</h3>
                    <p>Unable to load details</p>
                </div>
            `;
        }
    }

    positionPopup(element, popup) {
        if (!element || !popup) return;

        const rect = element.getBoundingClientRect();
        const popupRect = popup.getBoundingClientRect();
        
        let top = rect.bottom + window.scrollY + 10;
        let left = rect.left + window.scrollX;

        // Adjust if popup would go off-screen
        if (left + popupRect.width > window.innerWidth) {
            left = window.innerWidth - popupRect.width - 10;
        }
        
        if (top + popupRect.height > window.innerHeight + window.scrollY) {
            top = rect.top + window.scrollY - popupRect.height - 10;
        }

        const leftCls = ensureSalesPopupLeftClass(left);
        const topCls = ensureSalesPopupTopClass(top);
        // Remove previous classes if any
        if (popup.dataset.ppLeftClass && popup.dataset.ppLeftClass !== leftCls) {
            popup.classList.remove(popup.dataset.ppLeftClass);
        }
        if (popup.dataset.ppTopClass && popup.dataset.ppTopClass !== topCls) {
            popup.classList.remove(popup.dataset.ppTopClass);
        }
        popup.classList.add(leftCls, topCls);
        popup.dataset.ppLeftClass = leftCls;
        popup.dataset.ppTopClass = topCls;
    }

    setupEventListeners() {
        // Product hover events
        document.addEventListener('mouseenter', async (e) => {
            if (!e.target || typeof e.target.closest !== 'function') return;
            const productElement = e.target.closest('[data-sku], .product-card, .product-item');
            if (!productElement) return;

            const sku = productElement.dataset.sku;
            if (!sku) return;

            // Clear any existing timeouts
            if (this.hoverTimeout) {
                clearTimeout(this.hoverTimeout);
            }
            if (this.hideTimeout) {
                clearTimeout(this.hideTimeout);
            }

            this.hoverTimeout = setTimeout(async () => {
                // Create or get popup
                let popup = document.getElementById(`popup-${sku}`);
                if (!popup) {
                    popup = document.createElement('div');
                    popup.id = `popup-${sku}`;
                    popup.className = 'product-popup';
                    document.body.appendChild(popup);
                }

                // Get item data
                const item = {
                    sku,
                    name: productElement.dataset.name || productElement.querySelector('.product-name, .item-name')?.textContent?.trim(),
                    price: productElement.dataset.price || productElement.querySelector('.product-price, .item-price')?.textContent?.replace(/[^0-9.]/g, ''),
                    image: productElement.dataset.image || productElement.querySelector('img')?.src,
                    description: productElement.dataset.description
                };

                await this.updatePopupContent(popup, item);
                this.positionPopup(productElement, popup);
                popup.classList.add('show');
                this.currentPopup = popup;

                // Setup popup hover events
                popup.addEventListener('mouseenter', () => this.keepPopupVisible());
                popup.addEventListener('mouseleave', () => {
                    this.hideTimeout = setTimeout(() => this.hidePopup(), 300);
                });

            }, 500); // 500ms delay before showing popup

        }, true);

        document.addEventListener('mouseleave', (e) => {
            if (!e.target || typeof e.target.closest !== 'function') return;
            const productElement = e.target.closest('[data-sku], .product-card, .product-item');
            if (!productElement) return;

            if (this.hoverTimeout) {
                clearTimeout(this.hoverTimeout);
                this.hoverTimeout = null;
            }

            this.hideTimeout = setTimeout(() => this.hidePopup(), 300);
        }, true);
    }

    initializePageSpecificFeatures() {
        // Shop page specific functionality
        // Updated to support clean URLs instead of legacy query parameters
        const currentPage = window.WF_PAGE_INFO?.page || (
            window.location.pathname === '/' ? 'landing' : 
            window.location.pathname.replace(/^\//, '').split('/')[0]
        ) || 'landing';
        if (currentPage === 'shop' || window.location.pathname.includes('shop')) {
            this.initializeShopPage();
        }

        // Room page specific functionality
        this.setupRoomHover();
    }

    async initializeShopPage() {
        console.log('[Sales] Initializing shop page features...');
        
        // Process all product cards for sales
        const productCards = document.querySelectorAll('[data-sku], .product-card');
        for (const card of productCards) {
            const sku = card.dataset.sku;
            if (sku) {
                try {
                    const saleData = await this.checkItemSale(sku);
                    if (saleData.isOnSale && saleData.discountPercentage) {
                        this.addSaleBadgeToCard(card, saleData.discountPercentage);
                        
                        // Update pricing in the card
                        const priceElement = card.querySelector('.product-price, .item-price');
                        if (priceElement) {
                            const originalPrice = parseFloat(priceElement.textContent.replace(/[^0-9.]/g, ''));
                            const salePrice = this.calculateSalePrice(originalPrice, saleData.discountPercentage);
                            priceElement.innerHTML = `
                                <span class="sale-price">$${salePrice.toFixed(2)}</span>
                                <span class="original-price">$${originalPrice.toFixed(2)}</span>
                            `;
                        }
                    }
                } catch (error) {
                    console.error('[Sales] Error processing card for SKU:', sku, error);
                }
            }
        }
    }

    setupRoomHover() {
        // Room-specific hover functionality can be implemented here
        console.log('[Sales] Room hover functionality ready');
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API methods
    async getItemSaleInfo(sku) {
        return await this.checkItemSale(sku);
    }

    calculateDiscount(originalPrice, discountPercentage) {
        return this.calculateSalePrice(originalPrice, discountPercentage);
    }

    addSaleBadge(element, discountPercentage) {
        this.addSaleBadgeToCard(element, discountPercentage);
    }
}

// Export for ES6 modules
export default SalesSystem;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.SalesSystem = SalesSystem;
    
    // Global functions for backward compatibility
    window.checkItemSale = async (sku) => {
        if (window.WF_Sales) {
            return await window.WF_Sales.checkItemSale(sku);
        }
        return { isOnSale: false };
    };
    
    window.calculateSalePrice = (originalPrice, discountPercentage) => {
        if (window.WF_Sales) {
            return window.WF_Sales.calculateSalePrice(originalPrice, discountPercentage);
        }
        return originalPrice;
    };
}
