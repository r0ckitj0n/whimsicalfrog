/**
 * Help Tooltips System - TEST VERSION
 * Uses test API endpoints for debugging
 */

class HelpTooltipSystem {
    constructor() {
        this.tooltips = new Map();
        this.isEnabled = true;
        this.currentPageContext = 'settings'; // Force settings context for testing
        this.init();
    }

    /**
     * Initialize the tooltip system
     */
    async init() {
        try {
            console.log('🔧 Initializing help tooltip system...');
            
            // Use test API endpoint
            const statsResponse = await fetch('/api/help_tooltips_test.php?action=get_stats');
            const statsData = await statsResponse.json();
            
            console.log('📊 Stats response:', statsData);
            
            if (!statsData.success || !statsData.global_enabled) {
                console.log('❌ Help tooltips are globally disabled');
                return;
            }

            console.log('✅ Tooltips globally enabled, loading tooltips...');
            await this.loadTooltips();
            this.attachTooltips();
            this.setupEventListeners();
            console.log('✅ Help tooltip system initialized successfully');
        } catch (error) {
            console.error('❌ Failed to initialize help tooltip system:', error);
        }
    }

    /**
     * Load tooltips from the API
     */
    async loadTooltips(pageContext = null) {
        try {
            const context = pageContext || this.currentPageContext;
            console.log(`🔄 Loading tooltips for context: ${context}`);
            
            // Use test API endpoint
            const response = await fetch(`/api/help_tooltips_test.php?action=get_tooltips&page_context=${context}`);
            const data = await response.json();
            
            console.log('📥 Tooltips response:', data);
            
            if (data.success && data.tooltips) {
                data.tooltips.forEach(tooltip => {
                    this.tooltips.set(tooltip.element_id, tooltip);
                });
                console.log(`✅ Loaded ${data.tooltips.length} help tooltips for context: ${context}`);
                return data.tooltips;
            } else {
                console.log('❌ No tooltips loaded or API error');
                return [];
            }
        } catch (error) {
            console.error('❌ Failed to load tooltips:', error);
            return [];
        }
    }

    /**
     * Attach tooltips to elements
     */
    attachTooltips() {
        console.log('🔗 Attaching tooltips to elements...');
        let attachedCount = 0;
        
        this.tooltips.forEach((tooltip, elementId) => {
            const element = document.getElementById(elementId);
            if (element) {
                this.attachTooltipToElement(element, tooltip);
                attachedCount++;
                console.log(`✅ Attached tooltip to ${elementId}`);
            } else {
                console.log(`⚠️ Element not found: ${elementId}`);
            }
        });
        
        console.log(`🔗 Attached ${attachedCount} tooltips total`);
    }

    /**
     * Attach a tooltip to a specific element
     */
    attachTooltipToElement(element, tooltip) {
        // Check if element already has a tooltip
        if (element.hasAttribute('data-tooltip-attached')) {
            return;
        }

        // Mark element as having tooltip attached
        element.setAttribute('data-tooltip-attached', 'true');
        element.setAttribute('data-element-id', tooltip.element_id);

        // Create tooltip content
        const tooltipDiv = document.createElement('div');
        tooltipDiv.className = `help-tooltip tooltip-${tooltip.position}`;
        tooltipDiv.style.position = 'absolute';
        tooltipDiv.style.zIndex = '9999';
        tooltipDiv.style.display = 'none';
        
        const title = document.createElement('div');
        title.className = 'help-tooltip-title';
        title.textContent = tooltip.title || 'No Title';
        
        const content = document.createElement('div');
        content.className = 'help-tooltip-content';
        content.textContent = tooltip.content || 'No Content';
        
        tooltipDiv.appendChild(title);
        tooltipDiv.appendChild(content);
        
        console.log(`🏷️ Created tooltip for ${tooltip.element_id}: "${tooltip.title}"`);

        // Add tooltip to body
        document.body.appendChild(tooltipDiv);

        // Add hover event listeners
        element.addEventListener('mouseenter', (e) => {
            console.log(`🖱️ Mouse enter on ${tooltip.element_id}`);
            this.showTooltip(e.target, tooltipDiv, tooltip.position);
        });

        element.addEventListener('mouseleave', () => {
            console.log(`🖱️ Mouse leave on ${tooltip.element_id}`);
            this.hideTooltip(tooltipDiv);
        });

        // Store tooltip reference
        element._helpTooltip = tooltipDiv;
    }

    /**
     * Show tooltip at the correct position
     */
    showTooltip(element, tooltipDiv, position) {
        console.log(`👁️ Showing tooltip for position: ${position}`);
        
        // Make tooltip visible but transparent to measure dimensions
        tooltipDiv.style.visibility = 'visible';
        tooltipDiv.style.opacity = '0';
        tooltipDiv.style.display = 'block';
        
        // Force reflow
        tooltipDiv.offsetHeight;
        
        const rect = element.getBoundingClientRect();
        
        // Calculate position
        let top, left;
        
        switch (position) {
            case 'top':
                top = rect.top + window.scrollY - tooltipDiv.offsetHeight - 8;
                left = rect.left + window.scrollX + (rect.width / 2) - (tooltipDiv.offsetWidth / 2);
                break;
            case 'bottom':
                top = rect.bottom + window.scrollY + 8;
                left = rect.left + window.scrollX + (rect.width / 2) - (tooltipDiv.offsetWidth / 2);
                break;
            case 'left':
                top = rect.top + window.scrollY + (rect.height / 2) - (tooltipDiv.offsetHeight / 2);
                left = rect.left + window.scrollX - tooltipDiv.offsetWidth - 8;
                break;
            case 'right':
                top = rect.top + window.scrollY + (rect.height / 2) - (tooltipDiv.offsetHeight / 2);
                left = rect.right + window.scrollX + 8;
                break;
            default:
                top = rect.bottom + window.scrollY + 8;
                left = rect.left + window.scrollX + (rect.width / 2) - (tooltipDiv.offsetWidth / 2);
        }
        
        // Apply position
        tooltipDiv.style.top = Math.max(0, top) + 'px';
        tooltipDiv.style.left = Math.max(0, left) + 'px';
        
        // Make fully visible
        tooltipDiv.style.visibility = 'visible';
        tooltipDiv.style.opacity = '1';
        
        console.log(`✅ Tooltip positioned at top: ${top}px, left: ${left}px`);
    }

    /**
     * Hide tooltip
     */
    hideTooltip(tooltipDiv) {
        tooltipDiv.style.display = 'none';
        tooltipDiv.style.opacity = '0';
        console.log('👁️ Tooltip hidden');
    }

    /**
     * Setup global event listeners
     */
    setupEventListeners() {
        // Handle window resize
        window.addEventListener('resize', () => {
            this.repositionTooltips();
        });
        
        console.log('👂 Event listeners setup complete');
    }

    /**
     * Reposition all visible tooltips
     */
    repositionTooltips() {
        // Implementation for repositioning tooltips on resize
        console.log('🔄 Repositioning tooltips...');
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄 DOM Content Loaded - Creating tooltip system');
        window.helpTooltipSystem = new HelpTooltipSystem();
    });
} else {
    console.log('📄 DOM Already Ready - Creating tooltip system');
    window.helpTooltipSystem = new HelpTooltipSystem();
} 