/**
 * Cart Stock Status Display
 * Shows stock status for each cart item
 */

(function() {
    'use strict';

    // Wait for WooCommerce Blocks to be ready
    const { registerPlugin } = wp.plugins;
    const { ExperimentalOrderMeta } = wc.blocksCheckout;
    const { useStoreCart } = wc.blocksCheckout;

    function addStockStatusToCartItems() {
        const cartData = wp.data.select('wc/store/cart').getCartData();
        
        if (!cartData || !cartData.extensions || !cartData.extensions['woo-lieferzeiten-manager']) {
            console.log('[WLM Stock] No cart data available yet');
            return;
        }

        const stockData = cartData.extensions['woo-lieferzeiten-manager'].cart_items_stock || {};
        console.log('[WLM Stock] Stock data:', stockData);

        // Find all cart item rows
        const cartItems = document.querySelectorAll('.wc-block-cart-items__row');
        console.log('[WLM Stock] Found ' + cartItems.length + ' cart items');

        cartItems.forEach(function(row, index) {
            // Get cart item key from data attribute or index
            const cartItemKeys = Object.keys(stockData);
            if (index >= cartItemKeys.length) return;
            
            const cartItemKey = cartItemKeys[index];
            const stock = stockData[cartItemKey];
            
            if (!stock) return;

            console.log('[WLM Stock] Processing item ' + index + ':', stock);

            // Find the product name element
            const productName = row.querySelector('.wc-block-components-product-name');
            if (!productName) {
                console.log('[WLM Stock] Product name not found for item ' + index);
                return;
            }

            // Check if stock status already added
            if (productName.querySelector('.wlm-cart-stock-status')) {
                return; // Already added
            }

            // Create stock status element
            const stockStatus = document.createElement('div');
            stockStatus.className = 'wlm-cart-stock-status';
            stockStatus.style.cssText = 'margin-top: 4px; font-size: 12px; display: flex; align-items: center; gap: 6px;';

            if (stock.in_stock) {
                // Green circle - In stock
                stockStatus.innerHTML = '<span style="display: inline-block; width: 8px; height: 8px; background: #4caf50; border-radius: 50%;"></span>' +
                                       '<span style="color: #4caf50;">Auf Lager</span>';
            } else if (stock.available_date_formatted) {
                // Yellow circle - Available later
                stockStatus.innerHTML = '<span style="display: inline-block; width: 8px; height: 8px; background: #ff9800; border-radius: 50%;"></span>' +
                                       '<span style="color: #666;">Wieder verfügbar ab ' + stock.available_date_formatted + '</span>';
            } else {
                // Red circle - Not available
                stockStatus.innerHTML = '<span style="display: inline-block; width: 8px; height: 8px; background: #f44336; border-radius: 50%;"></span>' +
                                       '<span style="color: #f44336;">Nicht verfügbar</span>';
            }

            // Insert after product name
            productName.appendChild(stockStatus);
            console.log('[WLM Stock] Added stock status to item ' + index);
        });
    }

    // Run when DOM is ready and after cart updates
    function init() {
        console.log('[WLM Stock] Initializing...');
        
        // Initial run
        setTimeout(addStockStatusToCartItems, 500);

        // Re-run on cart changes
        if (wp.data) {
            wp.data.subscribe(function() {
                addStockStatusToCartItems();
            });
        }

        // Also run on DOM changes (for dynamic updates)
        const observer = new MutationObserver(function() {
            addStockStatusToCartItems();
        });

        const cartContainer = document.querySelector('.wc-block-cart');
        if (cartContainer) {
            observer.observe(cartContainer, {
                childList: true,
                subtree: true
            });
        }
    }

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
