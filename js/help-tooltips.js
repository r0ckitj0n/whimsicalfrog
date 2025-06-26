/**
 * Help Tooltips System - Complete Rewrite for Reliable Operation
 * This system provides hover tooltips for admin interface elements
 */

class TooltipSystem {
    constructor() {
        this.tooltips = new Map();
        this.activeTooltip = null;
        this.showDelay = 500; // ms delay before showing
        this.hideDelay = 100; // ms delay before hiding
        this.showTimeout = null;
        this.hideTimeout = null;
        this.isInitialized = false;
        
        if (window.debugTooltips) {
            console.log('🚀 TooltipSystem: Constructor called');
        }
        this.init();
    }
    
    async init() {
        try {
            if (window.debugTooltips) {
                console.log('🔄 TooltipSystem: Starting initialization');
            }
            
            // Load tooltip data
            await this.loadTooltips();
            
            // Wait for DOM if needed
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.attachTooltips());
            } else {
                this.attachTooltips();
            }
            
            this.isInitialized = true;
            console.log('✅ TooltipSystem: Fully initialized');
            
        } catch (error) {
            console.error('❌ TooltipSystem: Initialization failed:', error);
        }
    }
    
    async loadTooltips() {
        try {
            const pageContext = this.getPageContext();
            if (window.debugTooltips) {
                console.log(`📄 TooltipSystem: Loading tooltips for page context: ${pageContext}`);
            }
            
            // Load page-specific tooltips
            const pageResponse = await fetch(`/api/help_tooltips.php?action=get_tooltips&page=${pageContext}`);
            const pageData = await pageResponse.json();
            
            if (pageData.success && pageData.tooltips) {
                pageData.tooltips.forEach(tooltip => {
                    this.tooltips.set(tooltip.element_id, {
                        title: tooltip.title,
                        content: tooltip.content,
                        position: tooltip.position || 'top'
                    });
                });
                if (window.debugTooltips) {
                    console.log(`📋 TooltipSystem: Loaded ${pageData.tooltips.length} page tooltips`);
                }
            }
            
            // Load common tooltips
            const commonResponse = await fetch('/api/help_tooltips.php?action=get_tooltips&page=common');
            const commonData = await commonResponse.json();
            
            if (commonData.success && commonData.tooltips) {
                commonData.tooltips.forEach(tooltip => {
                    this.tooltips.set(tooltip.element_id, {
                        title: tooltip.title,
                        content: tooltip.content,
                        position: tooltip.position || 'top'
                    });
                });
                if (window.debugTooltips) {
                    console.log(`📋 TooltipSystem: Loaded ${commonData.tooltips.length} common tooltips`);
                }
            }
            
            if (window.debugTooltips) {
                console.log(`📊 TooltipSystem: Total tooltips loaded: ${this.tooltips.size}`);
            }
            
        } catch (error) {
            console.error('❌ TooltipSystem: Failed to load tooltips:', error);
        }
    }
    
    getPageContext() {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page');
        const section = urlParams.get('section');
        
        if (page === 'admin' && section) {
            return section;
        } else if (page) {
            return page;
        } else {
            return 'main';
        }
    }
    
    attachTooltips() {
        if (window.debugTooltips) {
            console.log('🔗 TooltipSystem: Attaching tooltips to elements');
        }
        
        let attachedCount = 0;
        
        this.tooltips.forEach((tooltipData, elementId) => {
            const element = document.getElementById(elementId);
            if (element) {
                this.attachTooltipToElement(element, tooltipData);
                attachedCount++;
                if (window.debugTooltips) {
                    console.log(`✅ TooltipSystem: Attached tooltip to ${elementId}: "${tooltipData.title}"`);
                }
            } else {
                // Only log missing elements in debug mode to reduce console noise
                if (window.debugTooltips) {
                    console.warn(`⚠️ TooltipSystem: Element not found: ${elementId}`);
                }
            }
        });
        
        console.log(`📎 TooltipSystem: Attached ${attachedCount} tooltips`);
    }
    
    attachTooltipToElement(element, tooltipData) {
        // Store tooltip data on element
        element._tooltipData = tooltipData;
        
        // Add event listeners
        element.addEventListener('mouseenter', (e) => this.handleMouseEnter(e));
        element.addEventListener('mouseleave', (e) => this.handleMouseLeave(e));
        element.addEventListener('focus', (e) => this.handleMouseEnter(e));
        element.addEventListener('blur', (e) => this.handleMouseLeave(e));
        
        // Add visual indicator
        element.style.cursor = 'pointer';
        element.setAttribute('data-has-tooltip', 'true');
    }
    
    handleMouseEnter(event) {
        const element = event.target;
        const tooltipData = element._tooltipData;
        
        if (!tooltipData) return;
        
        // Clear any existing timeouts
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
        
        // Hide any existing tooltip
        this.hideTooltip();
        
        // Show new tooltip with delay
        this.showTimeout = setTimeout(() => {
            this.showTooltip(element, tooltipData);
        }, this.showDelay);
    }
    
    handleMouseLeave(event) {
        // Clear show timeout
        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
            this.showTimeout = null;
        }
        
        // Hide tooltip with delay
        this.hideTimeout = setTimeout(() => {
            this.hideTooltip();
        }, this.hideDelay);
    }
    
    showTooltip(element, tooltipData) {
        try {
            // Create tooltip element
            const tooltip = this.createTooltipElement(tooltipData);
            
            // Add to DOM
            document.body.appendChild(tooltip);
            
            // Position tooltip
            this.positionTooltip(tooltip, element, tooltipData.position);
            
            // Show tooltip
            requestAnimationFrame(() => {
                tooltip.classList.add('show');
            });
            
            // Store reference
            this.activeTooltip = tooltip;
            
            if (window.debugTooltips) {
                console.log(`👁️ TooltipSystem: Showing tooltip "${tooltipData.title}" at position ${tooltipData.position}`);
            }
            
        } catch (error) {
            console.error('❌ TooltipSystem: Failed to show tooltip:', error);
        }
    }
    
    createTooltipElement(tooltipData) {
        const tooltip = document.createElement('div');
        tooltip.className = `help-tooltip tooltip-${tooltipData.position}`;
        
        // Create title element
        const title = document.createElement('div');
        title.className = 'tooltip-title';
        title.textContent = tooltipData.title;
        
        // Create content element
        const content = document.createElement('div');
        content.className = 'tooltip-content';
        content.textContent = tooltipData.content;
        
        // Assemble tooltip
        tooltip.appendChild(title);
        tooltip.appendChild(content);
        
        return tooltip;
    }
    
    positionTooltip(tooltip, element, position = 'top') {
        const elementRect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight
        };
        
        let left, top;
        
        switch (position) {
            case 'top':
                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                top = elementRect.top - tooltipRect.height - 10;
                break;
                
            case 'bottom':
                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                top = elementRect.bottom + 10;
                break;
                
            case 'left':
                left = elementRect.left - tooltipRect.width - 10;
                top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);
                break;
                
            case 'right':
                left = elementRect.right + 10;
                top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);
                break;
                
            default:
                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                top = elementRect.top - tooltipRect.height - 10;
        }
        
        // Ensure tooltip stays within viewport
        left = Math.max(10, Math.min(left, viewport.width - tooltipRect.width - 10));
        top = Math.max(10, Math.min(top, viewport.height - tooltipRect.height - 10));
        
        // Apply position
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
        
        if (window.debugTooltips) {
            console.log(`📍 TooltipSystem: Positioned tooltip at (${left}, ${top})`);
        }
    }
    
    hideTooltip() {
        if (this.activeTooltip) {
            const tooltip = this.activeTooltip;
            
            // Hide with animation
            tooltip.classList.remove('show');
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 200);
            
            this.activeTooltip = null;
            if (window.debugTooltips) {
                console.log('👋 TooltipSystem: Tooltip hidden');
            }
        }
    }
    
    // Public API methods
    addTooltip(elementId, title, content, position = 'top') {
        this.tooltips.set(elementId, { title, content, position });
        
        const element = document.getElementById(elementId);
        if (element) {
            this.attachTooltipToElement(element, { title, content, position });
        }
    }
    
    removeTooltip(elementId) {
        this.tooltips.delete(elementId);
        
        const element = document.getElementById(elementId);
        if (element) {
            element._tooltipData = null;
            element.removeAttribute('data-has-tooltip');
            element.style.cursor = '';
        }
    }
    
    destroy() {
        // Clear timeouts
        if (this.showTimeout) clearTimeout(this.showTimeout);
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
        
        // Hide active tooltip
        this.hideTooltip();
        
        // Clear data
        this.tooltips.clear();
        this.isInitialized = false;
        
        console.log('🗑️ TooltipSystem: Destroyed');
    }
}

// Initialize global tooltip system
let globalTooltipSystem = null;

// Initialize when DOM is ready
function initializeTooltipSystem() {
    if (!globalTooltipSystem) {
        globalTooltipSystem = new TooltipSystem();
        
        // Expose to global scope for debugging
        window.tooltipSystem = globalTooltipSystem;
    }
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTooltipSystem);
} else {
    initializeTooltipSystem();
}

// Legacy compatibility functions
function loadTooltips() {
    console.log('📞 Legacy function called: loadTooltips()');
    if (globalTooltipSystem) {
        return globalTooltipSystem.loadTooltips();
    }
}

function attachTooltips() {
    console.log('📞 Legacy function called: attachTooltips()');
    if (globalTooltipSystem) {
        globalTooltipSystem.attachTooltips();
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TooltipSystem, initializeTooltipSystem };
} 