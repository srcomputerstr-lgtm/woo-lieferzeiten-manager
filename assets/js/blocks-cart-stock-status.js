/**
 * Cart Stock Status Display
 * Shows stock status for each cart item using CSS ::before
 * Shows SKU badge for each cart item using CSS ::after
 */
(function() {
    'use strict';

    (window.wlm_params?.debug) && console.log('[WLM Stock] Script loaded');

    function addStockStatusCSS() {
        // Only run in frontend (not in admin)
        if (!wp.data || !wp.data.select || !wp.data.select('wc/store/cart')) {
            return;
        }
        
        const cartData = wp.data.select('wc/store/cart').getCartData();
        
        if (!cartData || !cartData.extensions || !cartData.extensions['woo-lieferzeiten-manager']) {
            (window.wlm_params?.debug) && console.log('[WLM Stock] No cart data available yet');
            return;
        }

        const stockData = cartData.extensions['woo-lieferzeiten-manager'].cart_items_stock || {};
        (window.wlm_params?.debug) && console.log('[WLM Stock] Stock data:', stockData);

        // Find all cart item rows
        const cartItems = document.querySelectorAll('.wc-block-cart-items__row');
        (window.wlm_params?.debug) && console.log('[WLM Stock] Found ' + cartItems.length + ' cart items');

        if (cartItems.length === 0) {
            (window.wlm_params?.debug) && console.log('[WLM Stock] No cart items found yet');
            return;
        }

        // Remove existing style element
        const existingStyle = document.getElementById('wlm-cart-stock-styles');
        if (existingStyle) {
            existingStyle.remove();
        }

        // Create new style element
        const style = document.createElement('style');
        style.id = 'wlm-cart-stock-styles';
        let css = '';

        // Get cart item keys in order
        const cartItemKeys = Object.keys(stockData);
        
        cartItemKeys.forEach(function(cartItemKey, index) {
            const stock = stockData[cartItemKey];
            if (!stock) return;

            const rowIndex = index + 1; // nth-child is 1-indexed
            
            (window.wlm_params?.debug) && console.log('[WLM Stock] Processing item ' + rowIndex + ':', stock);

            let content = '';
            let color = '';
            let circleColor = '';

            if (stock.in_stock) {
                // Green circle - In stock
                circleColor = '#4caf50';
                color = '#4caf50';
                content = '🟢 Auf Lager';
            } else if (stock.available_date_formatted) {
                // Yellow circle - Available later
                circleColor = '#ff9800';
                color = '#666';
                content = '🟡 Wieder verfügbar ab ' + stock.available_date_formatted;
            } else {
                // Red circle - Not available
                circleColor = '#f44336';
                color = '#f44336';
                content = '🔴 Nicht verfügbar';
            }

            // CSS ::before fuer Lagerstatus
            css += '.wc-block-cart-items__row:nth-child(' + rowIndex + ') .wc-block-cart-item__quantity::before {\n';
            css += '    content: "' + content + '";\n';
            css += '    display: block;\n';
            css += '    margin-bottom: 8px;\n';
            css += '    font-size: 12px;\n';
            css += '    color: ' + color + ';\n';
            css += '    white-space: nowrap;\n';
            css += '}\n';

            // CSS ::after fuer SKU-Badge - exakt dasselbe Muster wie ::before
            if (stock.sku) {
                css += '.wc-block-cart-items__row:nth-child(' + rowIndex + ') .wc-block-cart-item__quantity::after {\n';
                css += '    content: "Art-Nr ' + stock.sku + '";\n';
                css += '    display: block;\n';
                css += '    margin-top: 8px;\n';
                css += '    font-size: 11px;\n';
                css += '    color: #888;\n';
                css += '    white-space: nowrap;\n';
                css += '    background: #efefef;\n';
                css += '    padding: 2px 6px;\n';
                css += '    border-radius: 3px;\n';
                css += '}\n';
            }
        });

        style.textContent = css;
        document.head.appendChild(style);
        
        (window.wlm_params?.debug) && console.log('[WLM Stock] Added CSS rules for ' + cartItemKeys.length + ' items');
        (window.wlm_params?.debug) && console.log('[WLM Stock] CSS:\n' + css);
    }

    // Run when DOM is ready and after cart updates
    function init() {
        (window.wlm_params?.debug) && console.log('[WLM Stock] Initializing...');
        
        // Initial run
        setTimeout(addStockStatusCSS, 500);

        // Re-run on cart changes
        if (wp.data) {
            wp.data.subscribe(function() {
                addStockStatusCSS();
            });
        }

        // Also run on DOM changes (for dynamic updates)
        const observer = new MutationObserver(function() {
            addStockStatusCSS();
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
