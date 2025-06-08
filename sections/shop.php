<?php
/*
// Shop page section - now displays all items

// Fetch all products from Node API (MySQL)
$apiBase = 'https://whimsicalfrog.us';
$productsJson = @file_get_contents($apiBase . '/api/products');
$allProducts = $productsJson ? json_decode($productsJson, true) : [];
*/
?>
<section id="shopPage" class="p-2 bg-white rounded-lg shadow-lg">
    <h2 class="text-4xl font-merienda text-center text-[#556B2F] mb-8">All Our Whimsical Wares</h2>
    <div id="allProductsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <!-- Products will be loaded here by JavaScript -->
        <p id="loadingMessage" class="text-center text-gray-700 col-span-full">Loading products...</p>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Determine the API base URL dynamically.
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    const apiBase = isLocal ? 'http://localhost:3000' : 'https://whimsicalfrog.us';

    const productsGrid = document.getElementById('allProductsGrid');
    const loadingMessage = document.getElementById('loadingMessage');

    fetch(`${apiBase}/api/products`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.statusText}`);
            }
            return response.json();
        })
        .then(products => {
            loadingMessage.style.display = 'none'; // Hide loading message
            if (products && products.length > 0) {
                products.forEach(product => {
                    const productId = product.id || 'N/A';
                    const productName = product.name || 'Unnamed Product';
                    const productPrice = parseFloat(product.price || 0.00);
                    const productDescription = product.description || 'No description available.';
                    const productImage = product.image || 'images/placeholder.png';
                    
                    const escapedNameJS = productName.replace(/'/g, "\\'").replace(/"/g, '&quot;');

                    const productElement = document.createElement('div');
                    productElement.className = 'product-item bg-white p-3 rounded-lg shadow-md flex flex-col';
                    productElement.innerHTML = `
                        <img src="${productImage}" 
                             alt="${productName}" 
                             class="w-full h-40 object-cover rounded-md mb-3">
                        <h3 class="text-lg font-merienda text-[#556B2F] mb-1 truncate" title="${productName}">${productName}</h3>
                        <p class="text-gray-600 text-xs mb-2 flex-grow clamp-3-lines">${productDescription}</p>
                        <div class="flex justify-between items-center mt-auto">
                            <span class="text-md font-semibold text-[#6B8E23]">$${productPrice.toFixed(2)}</span>
                            <button onclick="addToCart('${productId}', '${escapedNameJS}', ${productPrice}, '${productImage}')" 
                                    class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-semibold py-1 px-2 text-sm rounded-md transition duration-150 cursor-pointer">
                                Add to Cart
                            </button>
                        </div>
                    `;
                    productsGrid.appendChild(productElement);
                });
            } else {
                productsGrid.innerHTML = '<p class="text-center text-gray-700 col-span-full">No products found. Please check back later.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching products:', error);
            loadingMessage.style.display = 'none';
            productsGrid.innerHTML = `<p class="text-center text-red-500 col-span-full">Failed to load products. ${error.message}</p>`;
        });
});

// Add to cart function (ensure cart.js is loaded and window.cart is available)
function addToCart(id, name, price, image) {
    console.log('Adding to cart from shop.php:', { id, name, price, image });
    try {
        if (typeof window.cart === 'undefined') {
            console.error('Cart not initialized. Ensure cart.js is loaded before this script.');
            // Display a user-friendly message on the page itself if possible
            const alertBox = document.getElementById('customAlertBox');
            const alertMessage = document.getElementById('customAlertMessage');
            if(alertBox && alertMessage) {
                alertMessage.textContent = 'Shopping cart is not available. Please refresh the page. If the problem persists, contact support.';
                alertBox.style.backgroundColor = '#f8d7da';
                alertBox.style.color = '#721c24';
                alertBox.style.display = 'block';
            } else {
                alert('Shopping cart is not available. Please refresh the page and try again.');
            }
            return;
        }
        window.cart.addItem({ id, name, price, image });
        // Optionally, show a success message or update cart icon directly
        const alertBox = document.getElementById('customAlertBox');
        const alertMessage = document.getElementById('customAlertMessage');
        if(alertBox && alertMessage) {
            alertMessage.textContent = `Added '${name}' to cart!`;
            alertBox.style.backgroundColor = '#d4edda'; // Green for success
            alertBox.style.color = '#155724';
            alertBox.style.display = 'block';
            setTimeout(() => { alertBox.style.display = 'none'; }, 3000); // Hide after 3 seconds
        } else {
            // Fallback alert if custom alert box is not found
            alert(`Added '${name}' to cart!`);
        }
        // The 'cartUpdated' event should be dispatched by cart.js
    } catch (error) {
        console.error('Error adding item to cart:', error);
        const alertBox = document.getElementById('customAlertBox');
        const alertMessage = document.getElementById('customAlertMessage');
        if(alertBox && alertMessage) {
            alertMessage.textContent = 'There was an error adding the item to your cart. Please try again.';
            alertBox.style.backgroundColor = '#f8d7da';
            alertBox.style.color = '#721c24';
            alertBox.style.display = 'block';
        } else {
            alert('There was an error adding the item to your cart. Please try again.');
        }
    }
}
</script>

<style>
/* Styles for product items in the grid */
.product-item {
    display: flex;
    flex-direction: column;
    background-color: white;
    padding: 0.75rem; /* p-3 */
    border-radius: 0.5rem; /* rounded-lg */
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
    transition: box-shadow 0.3s ease-in-out;
}

.product-item:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-lg */
}

.product-item img {
    width: 100%;
    height: 10rem; /* h-40 */
    object-fit: cover;
    border-radius: 0.375rem; /* rounded-md */
    margin-bottom: 0.75rem; /* mb-3 */
}

.product-item h3 {
    font-size: 1.125rem; /* text-lg */
    font-family: 'Merienda', cursive;
    color: #556B2F;
    margin-bottom: 0.25rem; /* mb-1 */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-item p.clamp-3-lines {
    color: #4B5563; /* text-gray-600 */
    font-size: 0.75rem; /* text-xs */
    margin-bottom: 0.5rem; /* mb-2 */
    flex-grow: 1;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    line-height: 1.4; /* Adjust for better readability */
    max-height: calc(1.4em * 3); /* Fallback for browsers not supporting -webkit-line-clamp */
}

.product-item .flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto; /* Pushes this to the bottom */
}

.product-item span.text-md {
    font-size: 1rem; /* Tailwind's text-md is 1rem by default */
}

.product-item button {
    padding-top: 0.25rem; /* py-1 */
    padding-bottom: 0.25rem; /* py-1 */
    padding-left: 0.5rem; /* px-2 */
    padding-right: 0.5rem; /* px-2 */
    font-size: 0.875rem; /* text-sm */
}

/* Custom alert box styles (if not already globally defined) */
.custom-alert {
    display: none;
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    padding: 15px;
    border: 1px solid transparent;
    border-radius: 8px;
    z-index: 2000; /* Ensure it's above other content, including modals if any */
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    min-width: 250px;
}

/* Fallback for browsers that don't support -webkit-line-clamp well */
@supports not (-webkit-line-clamp: 3) {
  .product-item p.clamp-3-lines {
    max-height: 4.2em; /* 3 lines * 1.4em line-height */
    position: relative;
  }
  .product-item p.clamp-3-lines::after {
    content: "...";
    position: absolute;
    right: 0;
    bottom: 0;
    background: white; /* Match product item background */
    padding-left: 0.25em;
  }
}
</style> 