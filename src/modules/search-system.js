/**
 * WhimsicalFrog Search System Module
 * Handles search modal and functionality - Vite compatible
 * Recovered and consolidated from legacy files
 */

class SearchSystem {
    constructor() {
        this.modal = null;
        this.searchInput = null;
        this.isSearching = false;
        this.currentSearchTerm = '';
        this.currentResults = [];
        
        this.init();
    }

    init() {
        console.log('[Search] System initializing...');
        this.createModalHTML();
        this.setupReferences();
        this.bindEvents();
    }

    createModalHTML() {
        // Check if search modal already exists
        if (document.getElementById('searchModal')) {
            console.log('[Search] Using existing search modal');
            return;
        }

        const modalHTML = `
            <div id="searchModal" class="search-modal" style="display: none;">
                <div class="search-modal-content">
                    <div class="search-modal-header">
                        <h2 class="search-modal-title">Search Results</h2>
                        <button class="search-modal-close" type="button">&times;</button>
                    </div>
                    <div class="search-modal-body" id="searchModalBody">
                        <!-- Search results will be populated here -->
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add basic styling
        const style = document.createElement('style');
        style.textContent = `
            .search-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 9998;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .search-modal-content {
                background: white;
                border-radius: 8px;
                max-width: 90vw;
                max-height: 90vh;
                overflow: auto;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                min-width: 500px;
            }
            .search-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #eee;
                background: #f8f9fa;
                border-radius: 8px 8px 0 0;
            }
            .search-modal-title {
                margin: 0;
                font-size: 18px;
            }
            .search-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .search-modal-body {
                padding: 20px;
                min-height: 200px;
            }
            .search-result-item {
                border-bottom: 1px solid #eee;
                padding: 15px 0;
            }
            .search-result-item:last-child {
                border-bottom: none;
            }
            .search-result-title {
                font-weight: bold;
                margin-bottom: 5px;
                color: #007bff;
                text-decoration: none;
            }
            .search-result-title:hover {
                text-decoration: underline;
            }
            .search-result-description {
                color: #666;
                font-size: 14px;
            }
            .search-loading {
                text-align: center;
                padding: 40px;
                color: #666;
            }
            .search-no-results {
                text-align: center;
                padding: 40px;
                color: #666;
            }
        `;
        document.head.appendChild(style);
    }

    setupReferences() {
        this.modal = document.getElementById('searchModal');
        this.searchInput = document.getElementById('headerSearchInput') || document.querySelector('.search-input, input[type="search"]');
        
        if (!this.searchInput) {
            console.log('[Search] No search input found, search functionality disabled');
        }
    }

    bindEvents() {
        if (!this.searchInput) return;

        // Handle Enter key press
        this.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = this.searchInput.value.trim();
                if (searchTerm) {
                    this.performSearch(searchTerm);
                }
            }
        });

        // Handle search button clicks
        document.addEventListener('click', (e) => {
            if (e.target.matches('.search-button, [data-action="search"]')) {
                e.preventDefault();
                const searchTerm = this.searchInput.value.trim();
                if (searchTerm) {
                    this.performSearch(searchTerm);
                }
            }
        });

        // Modal close events
        if (this.modal) {
            const closeBtn = this.modal.querySelector('.search-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }

            // Close on overlay click
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
        }

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    }

    async performSearch(searchTerm) {
        if (this.isSearching) {
            console.log('[Search] Already searching, skipping request');
            return;
        }

        console.log(`[Search] Performing search for: "${searchTerm}"`);
        this.currentSearchTerm = searchTerm;
        this.isSearching = true;

        // Show loading state
        this.open();
        this.setContent('<div class="search-loading">Searching...</div>');

        try {
            // Build search API URL - using working search_items.php API
            const searchUrl = `/api/search_items.php?q=${encodeURIComponent(searchTerm)}`;
            
            const response = await fetch(searchUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Search request failed: ${response.status}`);
            }

            const results = await response.json();
            this.currentResults = results;
            this.displayResults(results, searchTerm);

            // Emit search completed event
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.emit('search:completed', { 
                    term: searchTerm, 
                    results: results 
                });
            }

        } catch (error) {
            console.error('[Search] Error performing search:', error);
            this.setContent(`
                <div class="search-no-results">
                    <h3>Search Error</h3>
                    <p>Unable to perform search. Please try again later.</p>
                </div>
            `);
        } finally {
            this.isSearching = false;
        }
    }

    displayResults(results, searchTerm) {
        if (!results || results.length === 0) {
            this.setContent(`
                <div class="search-no-results">
                    <h3>No Results Found</h3>
                    <p>No items found for "${searchTerm}". Try different keywords.</p>
                </div>
            `);
            return;
        }

        let html = `<div class="search-results-header">
            <p>Found ${results.length} result${results.length !== 1 ? 's' : ''} for "${searchTerm}"</p>
        </div>`;

        results.forEach(result => {
            html += `
                <div class="search-result-item">
                    <a href="${result.url || '#'}" class="search-result-title">
                        ${this.escapeHtml(result.title || result.name || 'Untitled')}
                    </a>
                    <div class="search-result-description">
                        ${this.escapeHtml(result.description || result.excerpt || '')}
                        ${result.price ? `<br><strong>Price: $${parseFloat(result.price).toFixed(2)}</strong>` : ''}
                    </div>
                </div>
            `;
        });

        this.setContent(html);
    }

    setContent(html) {
        const body = this.modal?.querySelector('.search-modal-body');
        if (body) {
            body.innerHTML = html;
            this.initializeResultsContent();
        }
    }

    initializeResultsContent() {
        const body = this.modal?.querySelector('.search-modal-body');
        if (!body) return;

        // Setup result links
        const resultLinks = body.querySelectorAll('.search-result-title');
        resultLinks.forEach(link => {
            link.addEventListener('click', (_e) => {
                // Close search modal when clicking a result
                this.close();
            });
        });
    }

    open() {
        if (this.modal) {
            this.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Focus search input in modal if it exists
            const modalSearchInput = this.modal.querySelector('input[type="search"], .search-input');
            if (modalSearchInput) {
                setTimeout(() => modalSearchInput.focus(), 100);
            }
        }
    }

    close() {
        if (this.modal) {
            this.modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Emit search closed event
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.emit('search:closed', { 
                term: this.currentSearchTerm 
            });
        }
    }

    isOpen() {
        return this.modal && this.modal.style.display !== 'none';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API methods
    search(term) {
        if (this.searchInput) {
            this.searchInput.value = term;
        }
        return this.performSearch(term);
    }

    getCurrentTerm() {
        return this.currentSearchTerm;
    }

    getCurrentResults() {
        return [...this.currentResults];
    }

    clearResults() {
        this.currentResults = [];
        this.currentSearchTerm = '';
        if (this.searchInput) {
            this.searchInput.value = '';
        }
    }
}

// Export for ES6 modules
export default SearchSystem;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.SearchSystem = SearchSystem;
}
