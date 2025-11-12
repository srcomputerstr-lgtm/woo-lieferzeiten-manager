/**
 * Simple CSS ::after solution for displaying delivery info in WooCommerce Blocks Checkout
 * Matches shipping method labels by name and adds CSS rules
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
        
        // Find all shipping method labels
        const labels = document.querySelectorAll('.wc-block-components-totals-item__label');
        console.log('[WLM CSS] Found ' + labels.length + ' labels');
        
        // Create or update style element
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'wlm-delivery-info-css';
            document.head.appendChild(styleElement);
        }
        
        // Build CSS rules
        let cssRules = '';
        let matchedCount = 0;
        
        // For each label, try to match with delivery info
        labels.forEach(function(label, index) {
            const labelText = label.textContent.trim();
            console.log('[WLM CSS] Label ' + index + ': "' + labelText + '"');
            
            // Try to find matching method in delivery info
            Object.keys(deliveryInfo).forEach(function(methodId) {
                const info = deliveryInfo[methodId];
                const methodName = info.method_name || '';
                
                console.log('[WLM CSS] Comparing "' + labelText + '" with "' + methodName + '"');
                
                // Check if this is an Express rate
                const isExpressRate = labelText.endsWith(' - Express');
                const baseLabel = isExpressRate ? labelText.replace(' - Express', '') : labelText;
                
                // Match by method name (compare base label without " - Express")
                if (baseLabel === methodName) {
                    console.log('[WLM CSS] MATCH! Label "' + labelText + '" = Method "' + methodName + '"');
                    
                    // Build content string
                    let content = '';
                    
                    if (isExpressRate) {
                        // For Express rates, only show express delivery window
                        if (info.express_window) {
                            content += '\\A⚡ Express-Lieferung: ' + info.express_window;
                        }
                    } else {
                        // For normal rates, only show normal delivery window
                        if (info.delivery_window) {
                            content += '\\A📦 Voraussichtliche Lieferung: ' + info.delivery_window;
                        }
                    }
                    
                    if (content) {
                        // Get parent container to find position
                        const parent = label.closest('.wc-block-components-totals-shipping');
                        if (parent) {
                            const allItems = parent.querySelectorAll('.wc-block-components-totals-item');
                            let position = -1;
                            
                            allItems.forEach(function(item, idx) {
                                const itemLabel = item.querySelector('.wc-block-components-totals-item__label');
                                if (itemLabel === label) {
                                    position = idx + 1; // CSS nth-child is 1-indexed
                                }
                            });
                            
                            if (position > 0) {
                                console.log('[WLM CSS] Found position: ' + position);
                                
                                // Add CSS rules using nth-child
                                cssRules += `
/* Make parent item relative for absolute positioning */
.wc-block-components-totals-shipping .wc-block-components-totals-item:nth-child(${position}) {
    position: relative;
    padding-bottom: 45px; /* Space for delivery info */
}

/* Delivery info as ::after with absolute positioning */
.wc-block-components-totals-shipping .wc-block-components-totals-item:nth-child(${position}) .wc-block-components-totals-item__label::after {
    content: "${content}";
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 6px;
    font-size: 11px;
    line-height: 1.6;
    white-space: pre-line;
    color: #666;
}
`;
                                matchedCount++;
                            }
                        }
                    }
                }
            });
        });
        
        // Apply CSS rules
        styleElement.textContent = cssRules;
        console.log('[WLM CSS] CSS rules applied (' + matchedCount + ' matches):', cssRules);
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
