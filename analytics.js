/**
 * WhimsicalFrog Analytics Tracker
 * Comprehensive user behavior tracking system
 */

class AnalyticsTracker {
    constructor() {
        this.sessionStartTime = Date.now();
        this.pageStartTime = Date.now();
        this.lastScrollPosition = 0;
        this.maxScrollDepth = 0;
        this.interactions = [];
        this.isTracking = true;
        
        // Initialize tracking
        this.init();
    }

    parseUtmFromUrl(href) {
        try {
            const url = new URL(href, window.location.origin);
            const p = url.searchParams;
            return {
                utm_source: p.get('utm_source') || '',
                utm_medium: p.get('utm_medium') || '',
                utm_campaign: p.get('utm_campaign') || '',
                utm_term: p.get('utm_term') || '',
                utm_content: p.get('utm_content') || ''
            };
        } catch(_) { return {}; }
    }
    
    init() {
        // Track initial visit
        this.trackVisit();
        
        // Track page view
        this.trackPageView();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Track page exit
        this.setupPageExitTracking();
        
        // Send periodic updates
        this.startPeriodicTracking();
    }
    
    trackVisit() {
        const utm = this.parseUtmFromUrl(window.location.href);
        const data = {
            landing_page: window.location.href,
            referrer: document.referrer,
            timestamp: Date.now(),
            utm_source: utm.utm_source || '',
            utm_medium: utm.utm_medium || '',
            utm_campaign: utm.utm_campaign || '',
            utm_term: utm.utm_term || '',
            utm_content: utm.utm_content || ''
        };
        
        this.sendData('track_visit', data);
    }
    
    trackPageView() {
        const data = {
            page_url: window.location.href,
            page_title: document.title,
            page_type: this.getPageType(),
            item_sku: this.getItemSku(),
            timestamp: Date.now()
        };
        
        this.sendData('track_page_view', data);
    }
    
    getPageType() {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || 'landing';
        
        if (page === 'shop') return 'shop';
        if (page.startsWith('room')) return 'product_room';
        if (page === 'cart') return 'cart';
        if (page === 'admin') return 'admin';
        if (page === 'landing') return 'landing';
        
        return 'other';
    }
    
    getItemSku() {
        // Try to extract item SKU from various sources
        const params = new URLSearchParams(window.location.search);
        
        // Check URL parameters
        if (params.get('product')) return params.get('product');
        if (params.get('sku')) return params.get('sku');
        if (params.get('item')) return params.get('item');
        if (params.get('edit')) return params.get('edit');
        
        // Check for item elements on page
        const itemElements = document.querySelectorAll('[data-product-id], [data-sku], [data-item-sku]');
        if (itemElements.length > 0) {
            return itemElements[0].dataset.productId || itemElements[0].dataset.sku || itemElements[0].dataset.itemSku;
        }
        
        return null;
    }
    
    setupEventListeners() {
        // Track clicks
        document.addEventListener('click', (e) => {
            this.trackInteraction('click', e);
        });
        
        // Track form submissions
        document.addEventListener('submit', (e) => {
            this.trackInteraction('form_submit', e);
        });
        
        // Track scroll behavior
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.trackScroll();
            }, 100);
        });
        
        // Track search interactions
        const searchInputs = document.querySelectorAll('input[type="search"], input[name*="search"]');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length > 2) {
                    this.trackInteraction('search', e);
                }
            });
        });
        
        // Track cart actions
        this.setupCartTracking();
        
        // Track item interactions
        this.setupItemTracking();
    }
    
    setupCartTracking() {
        // Track add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart-btn') || 
                e.target.closest('.add-to-cart-btn')) {
                
                const button = e.target.classList.contains('add-to-cart-btn') ? 
                              e.target : e.target.closest('.add-to-cart-btn');
                
                const productSku = button.dataset.productId || button.dataset.sku;
                
                this.trackCartAction('add', productSku);
                this.trackInteraction('cart_add', e, { item_sku: productSku });
            }
        });
        
        // Track cart removal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-from-cart') || 
                e.target.closest('.remove-from-cart')) {
                
                const button = e.target.classList.contains('remove-from-cart') ? 
                              e.target : e.target.closest('.remove-from-cart');
                
                const productSku = button.dataset.productId || button.dataset.sku;
                
                this.trackCartAction('remove', productSku);
                this.trackInteraction('cart_remove', e, { item_sku: productSku });
            }
        });
        
        // Track checkout process
        const checkoutButtons = document.querySelectorAll('[onclick*="checkout"], .checkout-btn');
        checkoutButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.trackInteraction('checkout_start', null);
            });
        });
    }
    
    setupItemTracking() {
        // Track item views with time spent
        const itemElements = document.querySelectorAll('.product-card, .product-item, .item-card, .item-item');
        
        itemElements.forEach(element => {
            let viewStartTime = null;
            
            // Track when item comes into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        viewStartTime = Date.now();
                    } else if (viewStartTime) {
                        const viewTime = Date.now() - viewStartTime;
                        const productSku = element.dataset.productId || element.dataset.sku;
                        
                        if (productSku && viewTime > 1000) { // Only track if viewed for more than 1 second
                            this.trackItemView(productSku, viewTime);
                        }
                        viewStartTime = null;
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(element);
        });
        
        // Track item clicks
        document.addEventListener('click', (e) => {
            const itemElement = e.target.closest('.product-card, .product-item, .item-card, .item-item');
            if (itemElement) {
                const productSku = itemElement.dataset.productId || itemElement.dataset.sku;
                if (productSku) {
                    this.trackInteraction('click', e, { 
                        item_sku: productSku,
                        element_type: 'item'
                    });
                }
            }
        });
    }
    
    trackInteraction(type, event, additionalData = {}) {
        if (!this.isTracking) return;
        
        let elementInfo = {};
        
        if (event && event.target) {
            elementInfo = {
                element_type: event.target.tagName.toLowerCase(),
                element_id: event.target.id,
                element_text: event.target.textContent?.substring(0, 100) || '',
                element_class: event.target.className
            };
        }
        
        const data = {
            page_url: window.location.href,
            interaction_type: type,
            ...elementInfo,
            interaction_data: {
                timestamp: Date.now(),
                page_x: event?.clientX || 0,
                page_y: event?.clientY || 0,
                ...additionalData
            },
            item_sku: additionalData.item_sku || this.getItemSku()
        };
        
        this.sendData('track_interaction', data);
    }
    
    trackScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = Math.round((scrollTop / documentHeight) * 100);
        
        if (scrollPercent > this.maxScrollDepth) {
            this.maxScrollDepth = scrollPercent;
            
            // Track significant scroll milestones
            if (scrollPercent >= 25 && this.maxScrollDepth < 25) {
                this.trackInteraction('scroll', null, { scroll_depth: 25 });
            } else if (scrollPercent >= 50 && this.maxScrollDepth < 50) {
                this.trackInteraction('scroll', null, { scroll_depth: 50 });
            } else if (scrollPercent >= 75 && this.maxScrollDepth < 75) {
                this.trackInteraction('scroll', null, { scroll_depth: 75 });
            } else if (scrollPercent >= 90 && this.maxScrollDepth < 90) {
                this.trackInteraction('scroll', null, { scroll_depth: 90 });
            }
        }
    }
    
    trackItemView(productSku, timeSpent) {
        const data = {
            item_sku: productSku,
            time_on_page: Math.round(timeSpent / 1000) // Convert to seconds
        };
        
        this.sendData('track_item_view', data);
    }
    
    trackCartAction(action, productSku) {
        if (!productSku) return;
        
        const data = {
            item_sku: productSku,
            action: action
        };
        
        this.sendData('track_cart_action', data);
    }
    
    setupPageExitTracking() {
        // Track when user leaves the page
        window.addEventListener('beforeunload', () => {
            this.trackPageExit();
        });
        
        // Track when page becomes hidden (tab switch, minimize, etc.)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.trackPageExit();
            } else {
                // Page became visible again, restart tracking
                this.pageStartTime = Date.now();
            }
        });
    }
    
    trackPageExit() {
        const timeOnPage = Math.round((Date.now() - this.pageStartTime) / 1000);
        
        const data = {
            page_url: window.location.href,
            time_on_page: timeOnPage,
            scroll_depth: this.maxScrollDepth,
            item_sku: this.getItemSku()
        };
        
        // Use sendBeacon for reliable exit tracking
        this.sendDataSync('track_page_view', data);
    }
    
    startPeriodicTracking() {
        // Send periodic updates every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                this.trackPageView();
            }
        }, 30000);
    }
    
    sendData(action, data) {
        if (!this.isTracking) return;
        
        apiPost(`/api/analytics_tracker.php?action=${action}`, data).catch(error => {
            console.warn('Analytics tracking failed:', error);
        });
    }
    
    sendDataSync(action, data) {
        // For exit tracking, use sendBeacon for reliability
        const formData = new FormData();
        formData.append('action', action);
        formData.append('data', JSON.stringify(data));
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(`/api/analytics_tracker.php?action=${action}`, formData);
        } else {
            // Fallback for older browsers
            this.sendData(action, data);
        }
    }
    
    // Public methods for manual tracking
    trackConversion(value = 0, orderId = null) {
        const data = {
            conversion_value: value,
            order_id: orderId,
            page_url: window.location.href
        };
        
        this.trackInteraction('checkout_complete', null, data);
    }
    
    trackCustomEvent(eventName, eventData = {}) {
        this.trackInteraction('custom', null, {
            event_name: eventName,
            ...eventData
        });
    }
    
    // Privacy controls
    enableTracking() {
        this.isTracking = true;
    }
    
    disableTracking() {
        this.isTracking = false;
    }
}

// Initialize analytics when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if user has opted out of tracking
    if (localStorage.getItem('analytics_opt_out') !== 'true') {
        window.analyticsTracker = new AnalyticsTracker();
    }
});

// Utility functions for manual tracking
window.trackConversion = function(value, orderId) {
    if (window.analyticsTracker) {
        window.analyticsTracker.trackConversion(value, orderId);
    }
};

window.trackCustomEvent = function(eventName, eventData) {
    if (window.analyticsTracker) {
        window.analyticsTracker.trackCustomEvent(eventName, eventData);
    }
};

// Privacy controls
window.optOutOfAnalytics = function() {
    localStorage.setItem('analytics_opt_out', 'true');
    if (window.analyticsTracker) {
        window.analyticsTracker.disableTracking();
    }
};

window.optInToAnalytics = function() {
    localStorage.removeItem('analytics_opt_out');
    if (!window.analyticsTracker) {
        window.analyticsTracker = new AnalyticsTracker();
    } else {
        window.analyticsTracker.enableTracking();
    }
}; 