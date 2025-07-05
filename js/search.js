// Search functionality for WhimsicalFrog
class SearchModal {
    constructor() {
        this.modal = null;
        this.searchInput = null;
        this.isSearching = false;
        this.currentSearchTerm = '';
        this.currentResults = []; // Store current search results
        this.init();
    }

    init() {
        // Create the search modal HTML
        this.createModalHTML();
        
        // Get references to elements
        this.modal = document.getElementById('searchModal');
        this.searchInput = document.getElementById('headerSearchInput');
        
        // Bind event listeners
        this.bindEvents();
    }

    createModalHTML() {
        const modalHTML = `
            <!-- Search Results Modal -->
            <div id="searchModal" class="search-modal-overlay" style="display: none;">
                <div class="search-modal-content">
                    <div class="search-modal-header">
                        <h2 class="search-modal-title">Search Results</h2>
                        <button class="search-modal-close" onclick="searchModal.close()">&times;</button>
                    </div>
                    <div class="search-modal-results">
                        <!-- Search results will be populated here -->
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to the document body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    bindEvents() {
        if (this.searchInput) {
            // Handle Enter key press
            this.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const searchTerm = this.searchInput.value.trim();
                    if (searchTerm) {
                        this.performSearch(searchTerm);
                    }
                }
            });

            // Handle search icon click (if exists)
            const searchIcon = this.searchInput.parentElement.querySelector('svg');
            if (searchIcon) {
                searchIcon.addEventListener('click', () => {
                    const searchTerm = this.searchInput.value.trim();
                    if (searchTerm) {
                        this.performSearch(searchTerm);
                    }
                });
            }
        }

        // Close modal when clicking outside
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
        }

        // Handle Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    }

    async performSearch(searchTerm) {
        if (this.isSearching) return;
        
        this.isSearching = true;
        this.currentSearchTerm = searchTerm;
        
        // Show modal with loading state
        this.showLoading();
        this.open();

        try {
            const response = await fetch(`/api/search_items.php?q=${encodeURIComponent(searchTerm)}`);
            const data = await response.json();

            if (data.success) {
                this.displayResults(data);
            } else {
                this.displayError(data.message || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.displayError('An error occurred while searching. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }

    showLoading() {
        const modalBody = document.querySelector('.search-modal-results');
        modalBody.innerHTML = `
            <div class="search-loading">
                <div class="spinner"></div>
                <p>Searching for "${this.currentSearchTerm}"...</p>
            </div>
        `;
    }

    displayResults(data) {
        // Store results for later use
        this.currentResults = data.results || [];
        
        const modalBody = document.querySelector('.search-modal-results');
        
        if (data.results.length === 0) {
            modalBody.innerHTML = `
                <div class="search-no-results">
                    <div class="search-no-results-icon">🔍</div>
                    <h3 class="search-no-results-title">No results found</h3>
                    <p class="search-no-results-text">
                        We couldn't find any items matching "<strong>${data.search_term}</strong>".
                        <br>Try different keywords or browse our categories.
                    </p>
                </div>
            `;
            return;
        }

        let resultsHTML = `
            <div class="search-results-header">
                <p class="search-results-count">
                    Found <strong>${data.count}</strong> result${data.count !== 1 ? 's' : ''} for 
                    "<span class="search-results-term">${data.search_term}</span>"
                </p>
            </div>
            <div class="search-results-grid">
        `;

        data.results.forEach(item => {
            const stockClass = item.in_stock ? 'in-stock' : 'out-of-stock';
            const isOutOfStock = !item.in_stock;
            const addToCartButton = isOutOfStock 
                ? `<button class="search-add-to-cart-btn disabled" disabled>Out of Stock</button>`
                : `<button class="search-add-to-cart-btn" onclick="searchModal.addToCart('${item.sku}', event)">Add to Cart</button>`;
            
            resultsHTML += `
                <div class="search-result-item">
                    <div class="search-result-clickable" onclick="searchModal.viewItemDetails('${item.sku}')">
                        <img src="${item.image_url}" alt="${item.name}" class="search-result-image" 
                             onerror="this.src='/images/items/placeholder.webp'">
                        <div class="search-result-content">
                            <h3 class="search-result-name">${item.name}</h3>
                            <span class="search-result-category">${item.category}</span>
                            <p class="search-result-description">${item.description || 'No description available'}</p>
                            <div class="search-result-footer">
                                <span class="search-result-price">${item.formatted_price}</span>
                                <span class="search-result-stock ${stockClass}">${item.stock_status}</span>
                            </div>
                        </div>
                    </div>
                    <div class="search-result-actions">
                        ${addToCartButton}
                        <button class="search-view-details-btn" onclick="searchModal.viewItemDetails('${item.sku}')">View Details</button>
                    </div>
                </div>
            `;
        });

        resultsHTML += `</div>`;
        modalBody.innerHTML = resultsHTML;
    }

    displayError(message) {
        const modalBody = document.querySelector('.search-modal-results');
        modalBody.innerHTML = `
            <div class="search-error">
                <div class="search-error-icon">⚠️</div>
                <h3 class="search-error-title">Search Error</h3>
                <p class="search-error-text">${message}</p>
            </div>
        `;
    }

    selectItem(sku) {
        // Close the search modal
        this.close();
        
        // Clear the search input
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        
        // Open the quantity modal for the selected item
        if (typeof openQuantityModal === 'function') {
            // Find the item data from the current search results
            const itemElement = document.querySelector(`[onclick="searchModal.selectItem('${sku}')"]`);
            if (itemElement) {
                const itemData = this.extractItemDataFromElement(itemElement, sku);
                openQuantityModal(itemData);
            }
        } else {
            // Fallback: redirect to shop page with the item highlighted
            window.location.href = `/?page=shop&highlight=${sku}`;
        }
    }

    extractItemDataFromElement(element, sku) {
        const name = element.querySelector('.search-result-name').textContent;
        const price = element.querySelector('.search-result-price').textContent.replace('$', '');
        const image = element.querySelector('.search-result-image').src;
        const inStock = element.querySelector('.search-result-stock').classList.contains('in-stock');
        
        return {
            sku: sku,
            name: name,
            price: parseFloat(price),
            image: image,
            inStock: inStock
        };
    }

    open() {
        if (this.modal) {
            this.modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    close() {
        if (this.modal) {
            this.modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    isOpen() {
        return this.modal && this.modal.style.display === 'block';
    }

    addToCart(sku, event) {
        // Prevent event bubbling to avoid triggering the view details
        event.stopPropagation();
        
        // Find the item data from the search results
        const itemElement = event.target.closest('.search-result-item');
        if (!itemElement) return;
        
        const itemData = this.extractItemDataFromSearchResult(itemElement, sku);
        
        // Add to cart with quantity 1
        if (typeof window.cart !== 'undefined' && window.cart.addItem) {
            window.cart.addItem(itemData.sku, itemData.name, itemData.price, itemData.image, 1);
            
            // Show success message
            this.showAddToCartSuccess(itemData.name);
        } else {
            console.error('Cart system not available');
        }
    }

    viewItemDetails(sku) {
        // Find the item data from stored results
        const itemData = this.currentResults.find(item => item.sku === sku);
        if (!itemData) {
            console.error('Item not found in search results:', sku);
            return;
        }
        
        // Close the search modal
        this.close();
        
        // Clear the search input
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        
        // Check what page we're on and use the appropriate function
        const currentPage = new URLSearchParams(window.location.search).get('page');
        
        if (currentPage === 'shop' && typeof showItemDetails === 'function') {
            // On shop page, use showItemDetails
            showItemDetails(sku);
        } else {
            // On other pages, use the global modal
            showGlobalItemModal(sku, {
                sku: sku,
                name: itemData.name,
                itemName: itemData.name,
                itemId: itemData.sku
            });
        }
    }

    extractItemDataFromSearchResult(element, sku) {
        const name = element.querySelector('.search-result-name').textContent;
        const price = element.querySelector('.search-result-price').textContent.replace('$', '');
        const image = element.querySelector('.search-result-image').src;
        const inStock = element.querySelector('.search-result-stock').classList.contains('in-stock');
        
        return {
            sku: sku,
            name: name,
            price: parseFloat(price),
            image: image,
            inStock: inStock
        };
    }

    showAddToCartSuccess(itemName) {
        // Create a temporary success message
        const successMessage = document.createElement('div');
        successMessage.className = 'search-add-to-cart-success';
        successMessage.innerHTML = `
            <div class="success-icon">✓</div>
            <div class="success-text">Added "${itemName}" to cart!</div>
        `;
        
        // Add to the modal body
        const modalBody = document.getElementById('searchModalBody');
        modalBody.appendChild(successMessage);
        
        // Remove after 3 seconds
        setTimeout(() => {
            if (successMessage.parentNode) {
                successMessage.parentNode.removeChild(successMessage);
            }
        }, 3000);
    }
}

// Initialize search modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize search modal if search input exists (not on admin pages)
    if (document.getElementById('headerSearchInput')) {
        window.searchModal = new SearchModal();
        console.log('Search modal initialized');
    }
});

// Make searchModal globally available
window.SearchModal = SearchModal;
