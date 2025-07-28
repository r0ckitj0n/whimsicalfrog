// Shop page JavaScript functionality
// This file contains all the interactive elements for the shop page

document.addEventListener('DOMContentLoaded', function() {
    console.log('Shop page loaded successfully');

    // Debug layout information
    const shopPage = document.getElementById('shopPage');
    if (shopPage) {
        console.log('Shop page element found');
        console.log('Shop page dimensions:', {
            width: shopPage.offsetWidth,
            height: shopPage.offsetHeight,
            computedStyle: getComputedStyle(shopPage)
        });
    }

    // Category filtering functionality
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    console.log(`Found ${categoryButtons.length} category buttons`);
    console.log(`Found ${productCards.length} product cards`);

    // Add click event listeners to category buttons
    categoryButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            console.log(`Filtering by category: ${category}`);
            
            // Remove active class from all buttons
            categoryButtons.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');

            // Filter products
            productCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Recalculate card heights after filtering
            setTimeout(equalizeCardHeights, 100);
        });
    });

    // Enhanced function to equalize ALL card heights to the tallest card
    function equalizeCardHeights() {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;

        const cards = Array.from(grid.querySelectorAll('.product-card')).filter(card =>
            card.style.display !== 'none' && getComputedStyle(card).display !== 'none'
        );

        if (cards.length === 0) return;

        // Reset all card heights first to get natural heights
        cards.forEach(card => {
            card.style.height = 'auto';
            card.style.minHeight = 'auto';
        });

        // Allow a brief moment for the DOM to update with natural heights
        setTimeout(() => {
            // Find the tallest card among ALL visible cards with enhanced measurement
            let maxHeight = 0;
            cards.forEach(card => {
                // Get the full height including padding, border, and content
                const computedStyle = getComputedStyle(card);
                const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
                const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
                const borderTop = parseFloat(computedStyle.borderTopWidth) || 0;
                const borderBottom = parseFloat(computedStyle.borderBottomWidth) || 0;

                const contentHeight = card.scrollHeight;
                const totalHeight = Math.max(card.offsetHeight, contentHeight + paddingTop + paddingBottom + borderTop + borderBottom);

                if (totalHeight > maxHeight) {
                    maxHeight = totalHeight;
                }
            });

            // Add buffer for ultra-wide screens
            const screenWidth = window.innerWidth;
            if (screenWidth >= 2000) {
                maxHeight += 20; // Extra buffer for very wide screens
            } else if (screenWidth >= 1600) {
                maxHeight += 15; // Medium buffer for wide screens
            } else if (screenWidth >= 1400) {
                maxHeight += 10; // Small buffer for ultra-wide screens
            }

            // Set ALL cards to the height of the tallest card
            cards.forEach(card => {
                card.style.height = maxHeight + 'px';
                card.style.minHeight = maxHeight + 'px';
            });

            console.log(`Equalized ${cards.length} cards to height: ${maxHeight}px (screen width: ${screenWidth}px)`);
        }, 10);
    }

    // Enhanced window resize handler for ultra-wide screens
    let resizeTimeout;
    function handleResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            console.log('Window resized, recalculating card heights...');
            equalizeCardHeights();
        }, 250); // Debounce resize events
    }

    // Add resize event listener
    window.addEventListener('resize', handleResize);

    // Initial equalization
    equalizeCardHeights();

    // Add event listeners for Add to Cart buttons
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sku = this.getAttribute('data-sku');
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const customText = this.getAttribute('data-custom-text');
            
            console.log(`Add to cart clicked for ${name} (${sku})`);
            
            // Use the global modal system
            if (typeof showItemModal === 'function') {
                showItemModal(sku, name, price, customText);
            } else {
                console.error('Global modal function not available');
            }
        });
    });
});
