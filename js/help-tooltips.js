/**
 * Help Tooltips System
 * Provides contextual help for admin settings pages
 */

class HelpTooltipSystem {
    constructor() {
        this.tooltips = new Map();
        this.isEnabled = localStorage.getItem('help-tooltips-enabled') !== 'false';
        this.currentPageContext = this.detectPageContext();
        this.init();
    }

    /**
     * Initialize the tooltip system
     */
    async init() {
        try {
            await this.loadTooltips();
            this.createToggleButton();
            this.attachTooltips();
            this.setupEventListeners();
            this.updateHelpState();
        } catch (error) {
            console.warn('Failed to initialize help tooltip system:', error);
        }
    }

    /**
     * Detect the current page context for tooltips
     */
    detectPageContext() {
        const url = window.location.href;
        const params = new URLSearchParams(window.location.search);
        
        // Check for admin sections
        if (url.includes('admin')) {
            const section = params.get('section');
            if (section) {
                return section;
            }
            return 'admin';
        }
        
        // Check for specific pages
        if (url.includes('inventory')) return 'inventory';
        if (url.includes('orders')) return 'orders';
        if (url.includes('users')) return 'users';
        if (url.includes('analytics')) return 'analytics';
        if (url.includes('rooms')) return 'rooms';
        if (url.includes('shipping')) return 'shipping';
        if (url.includes('email')) return 'email';
        if (url.includes('payment')) return 'payment';
        if (url.includes('seo')) return 'seo';
        if (url.includes('security')) return 'security';
        if (url.includes('backup')) return 'backup';
        
        return 'admin';
    }

    /**
     * Load tooltips from the API
     */
    async loadTooltips() {
        try {
            const response = await fetch(`/api/help_tooltips.php?action=get&page_context=${this.currentPageContext}`);
            const data = await response.json();
            
            if (data.success) {
                data.tooltips.forEach(tooltip => {
                    this.tooltips.set(tooltip.element_id, tooltip);
                });
                console.log(`Loaded ${data.tooltips.length} help tooltips for context: ${this.currentPageContext}`);
            }
        } catch (error) {
            console.warn('Failed to load tooltips:', error);
        }
    }

    /**
     * Create the help toggle button
     */
    createToggleButton() {
        const toggleButton = document.createElement('button');
        toggleButton.className = 'admin-help-toggle';
        toggleButton.title = 'Toggle Help Tooltips';
        toggleButton.innerHTML = '?';
        toggleButton.addEventListener('click', () => this.toggleHelp());
        
        if (this.isEnabled) {
            toggleButton.classList.add('active');
        }
        
        document.body.appendChild(toggleButton);
    }

    /**
     * Attach tooltips to elements
     */
    attachTooltips() {
        this.tooltips.forEach((tooltip, elementId) => {
            const element = document.getElementById(elementId);
            if (element) {
                this.attachTooltipToElement(element, tooltip);
            }
        });
    }

    /**
     * Attach a tooltip to a specific element
     */
    attachTooltipToElement(element, tooltip) {
        // Check if element already has a tooltip container
        if (element.closest('.help-tooltip-container')) {
            return;
        }

        // Create tooltip container
        const container = document.createElement('div');
        container.className = 'help-tooltip-container';

        // Wrap the element
        element.parentNode.insertBefore(container, element);
        container.appendChild(element);

        // Create tooltip trigger
        const trigger = document.createElement('span');
        trigger.className = 'help-tooltip-trigger';
        trigger.setAttribute('data-element-id', tooltip.element_id);

        // Create tooltip content
        const tooltipDiv = document.createElement('div');
        tooltipDiv.className = `help-tooltip tooltip-${tooltip.position}`;
        
        const title = document.createElement('div');
        title.className = 'help-tooltip-title';
        title.textContent = tooltip.title;
        
        const content = document.createElement('div');
        content.className = 'help-tooltip-content';
        content.textContent = tooltip.content;
        
        tooltipDiv.appendChild(title);
        tooltipDiv.appendChild(content);

        // Add trigger and tooltip to container
        container.appendChild(trigger);
        container.appendChild(tooltipDiv);

        // Special handling for different element types
        this.handleSpecialElements(element, container);
    }

    /**
     * Handle special element types (labels, buttons, etc.)
     */
    handleSpecialElements(element, container) {
        if (element.tagName === 'LABEL') {
            container.classList.add('form-label-with-help');
        } else if (element.tagName === 'BUTTON') {
            container.classList.add('button-with-help');
        }

        // For input elements, attach to their label if it exists
        if (element.tagName === 'INPUT' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
            const label = document.querySelector(`label[for="${element.id}"]`);
            if (label && !label.closest('.help-tooltip-container')) {
                // Move the tooltip to the label instead
                const labelContainer = document.createElement('div');
                labelContainer.className = 'help-tooltip-container form-label-with-help';
                
                label.parentNode.insertBefore(labelContainer, label);
                labelContainer.appendChild(label);
                
                const trigger = container.querySelector('.help-tooltip-trigger');
                const tooltip = container.querySelector('.help-tooltip');
                
                labelContainer.appendChild(trigger);
                labelContainer.appendChild(tooltip);
                
                // Remove the original container
                container.parentNode.insertBefore(element, container);
                container.remove();
            }
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Handle dynamic content
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        this.attachTooltipsToNewElements(node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Handle keyboard accessibility
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideAllTooltips();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.repositionTooltips();
        });
    }

    /**
     * Attach tooltips to newly added elements
     */
    attachTooltipsToNewElements(container) {
        this.tooltips.forEach((tooltip, elementId) => {
            const element = container.querySelector ? container.querySelector(`#${elementId}`) : null;
            if (element && !element.closest('.help-tooltip-container')) {
                this.attachTooltipToElement(element, tooltip);
            }
        });
    }

    /**
     * Toggle help system on/off
     */
    toggleHelp() {
        this.isEnabled = !this.isEnabled;
        localStorage.setItem('help-tooltips-enabled', this.isEnabled.toString());
        this.updateHelpState();
    }

    /**
     * Update the help system state
     */
    updateHelpState() {
        const toggleButton = document.querySelector('.admin-help-toggle');
        
        if (this.isEnabled) {
            document.body.classList.remove('help-disabled');
            if (toggleButton) {
                toggleButton.classList.add('active');
                toggleButton.title = 'Disable Help Tooltips';
            }
        } else {
            document.body.classList.add('help-disabled');
            if (toggleButton) {
                toggleButton.classList.remove('active');
                toggleButton.title = 'Enable Help Tooltips';
            }
        }
    }

    /**
     * Hide all visible tooltips
     */
    hideAllTooltips() {
        document.querySelectorAll('.help-tooltip').forEach(tooltip => {
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        });
    }

    /**
     * Reposition tooltips on window resize
     */
    repositionTooltips() {
        // Force recalculation of tooltip positions
        document.querySelectorAll('.help-tooltip').forEach(tooltip => {
            const container = tooltip.closest('.help-tooltip-container');
            if (container) {
                // Temporarily hide and show to trigger position recalculation
                const originalDisplay = tooltip.style.display;
                tooltip.style.display = 'none';
                setTimeout(() => {
                    tooltip.style.display = originalDisplay;
                }, 10);
            }
        });
    }

    /**
     * Add a new tooltip programmatically
     */
    addTooltip(elementId, title, content, position = 'top') {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn(`Element with ID ${elementId} not found`);
            return;
        }

        const tooltip = {
            element_id: elementId,
            title: title,
            content: content,
            position: position
        };

        this.tooltips.set(elementId, tooltip);
        this.attachTooltipToElement(element, tooltip);
    }

    /**
     * Remove a tooltip
     */
    removeTooltip(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            const container = element.closest('.help-tooltip-container');
            if (container) {
                const parent = container.parentNode;
                parent.insertBefore(element, container);
                container.remove();
            }
        }
        this.tooltips.delete(elementId);
    }

    /**
     * Update tooltip content
     */
    updateTooltip(elementId, title, content, position) {
        const tooltip = this.tooltips.get(elementId);
        if (tooltip) {
            tooltip.title = title || tooltip.title;
            tooltip.content = content || tooltip.content;
            tooltip.position = position || tooltip.position;

            // Update the DOM
            const element = document.getElementById(elementId);
            if (element) {
                const container = element.closest('.help-tooltip-container');
                if (container) {
                    const tooltipDiv = container.querySelector('.help-tooltip');
                    const titleDiv = tooltipDiv.querySelector('.help-tooltip-title');
                    const contentDiv = tooltipDiv.querySelector('.help-tooltip-content');
                    
                    if (titleDiv) titleDiv.textContent = tooltip.title;
                    if (contentDiv) contentDiv.textContent = tooltip.content;
                    
                    // Update position class
                    tooltipDiv.className = `help-tooltip tooltip-${tooltip.position}`;
                }
            }
        }
    }

    /**
     * Get tooltip data
     */
    getTooltip(elementId) {
        return this.tooltips.get(elementId);
    }

    /**
     * Get all tooltips
     */
    getAllTooltips() {
        return Array.from(this.tooltips.values());
    }
}

// Initialize the help tooltip system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on admin pages
    if (window.location.href.includes('admin') || 
        document.body.classList.contains('admin-page')) {
        window.helpTooltipSystem = new HelpTooltipSystem();
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HelpTooltipSystem;
} 