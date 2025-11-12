/**
 * Simple CSS ::after solution for displaying delivery info in WooCommerce Blocks Checkout
 * NO DOM manipulation - only CSS rules!
 */

(function() {
    'use strict';
    
    console.log('[WLM CSS] Script loaded');
    
    // Create style element for dynamic CSS rules
    let styleElement = null;
    
    /**
     * Add CSS ::after rules for delivery info
     */
    function addDeliveryInfoCSS() {
        console.log('[WLM CSS] Adding delivery info CSS rules...');
        
        // Get delivery info from Store API Extension
        if (!window.wp || !window.wp.data) {
            console.log('[WLM CSS] wp.data not available yet');
            return;
        }
        
        const store = window.wp.data.select('wc/store/cart');
        if (!store) {
            console.log('[WLM CSS] Store not available yet');
            return;
        }
        
        const cartData = store.getCartData();
        const extensions = cartData?.extensions || {};
        const wlmExtension = extensions['woo-lieferzeiten-manager'];
        
        console.log('[WLM CSS] WLM Extension:', wlmExtension);
        
        if (!wlmExtension || !wlmExtension.delivery_info) {
            console.log('[WLM CSS] No delivery info available');
            return;
        }
        
        const deliveryInfo = wlmExtension.delivery_info;
        console.log('[WLM CSS] Delivery info:', deliveryInfo);
        
        // Create or update style element
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'wlm-delivery-info-css';
            document.head.appendChild(styleElement);
        }
        
        // Build CSS rules
        let cssRules = '';
        
        // For each shipping method with delivery info
        Object.keys(deliveryInfo).forEach(function(methodId) {
            const info = deliveryInfo[methodId];
            
            console.log('[WLM CSS] Processing method:', methodId, info);
            
            // Build content string
            let content = '';
            
            if (info.delivery_window) {
                content += '\\A📦 Voraussichtliche Lieferung: ' + info.delivery_window;
            }
            
            if (info.express_available && info.express_window) {
                if (content) content += '\\A';
                content += '⚡ Express-Versand (' + info.express_cost + ' €) – Zustellung: ' + info.express_window;
            }
            
            if (content) {
                // Find all shipping method labels and add data attribute
                const labels = document.querySelectorAll('.wc-block-components-totals-item__label');
                labels.forEach(function(label) {
                    const labelText = label.textContent.trim();
                    
                    // Check if this label belongs to our method
                    // We need to match by label text since we can't easily get method ID from DOM
                    // For now, add to all shipping method labels (will be refined)
                    const item = label.closest('.wc-block-components-radio-control__option');
                    if (item) {
                        // Add data attribute for CSS targeting
                        label.setAttribute('data-wlm-method', methodId);
                        
                        console.log('[WLM CSS] Added data attribute to label:', labelText, methodId);
                    }
                });
                
                // Add CSS rule for this method
                cssRules += `
.wc-block-components-totals-item__label[data-wlm-method="${methodId}"]::after {
    content: "${content}";
    display: block;
    margin-top: 8px;
    padding: 12px;
    background-color: #f7f7f7;
    border-left: 3px solid #2271b1;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-line;
    color: #333;
}
`;
            }
        });
        
        // Apply CSS rules
        styleElement.textContent = cssRules;
        console.log('[WLM CSS] CSS rules applied:', cssRules);
    }
    
    /**
     * Initialize
     */
    function init() {
        console.log('[WLM CSS] Initializing...');
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(addDeliveryInfoCSS, 500);
            });
        } else {
            setTimeout(addDeliveryInfoCSS, 500);
        }
        
        // Re-run when cart updates
        if (window.wp && window.wp.data) {
            let previousCartData = null;
            
            window.wp.data.subscribe(function() {
                const store = window.wp.data.select('wc/store/cart');
                if (store) {
                    const cartData = store.getCartData();
                    
                    // Check if cart data changed
                    if (JSON.stringify(cartData) !== JSON.stringify(previousCartData)) {
                        previousCartData = cartData;
                        console.log('[WLM CSS] Cart data changed, re-adding CSS...');
                        setTimeout(addDeliveryInfoCSS, 300);
                    }
                }
            });
        }
    }
    
    init();
})();
