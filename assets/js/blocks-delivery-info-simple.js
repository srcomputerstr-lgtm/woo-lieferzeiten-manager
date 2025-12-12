/**
 * Simple CSS ::after solution for displaying delivery info in WooCommerce Blocks Checkout
 * Matches shipping method labels by name and adds CSS rules
 */

(function() {
    'use strict';
    
    (window.wlm_params?.debug) && console.log('[WLM CSS] Script loaded');
    
    // Create style element for dynamic CSS rules
    let styleElement = null;
    
    /**
     * Add CSS ::after rules for delivery info
     */
    function addDeliveryInfoCSS() {
        (window.wlm_params?.debug) && console.log('[WLM CSS] Adding delivery info CSS rules...');
        
        // Get delivery info from Store API Extension
        if (!window.wp || !window.wp.data) {
            (window.wlm_params?.debug) && console.log('[WLM CSS] wp.data not available yet');
            return;
        }
        
        const store = window.wp.data.select('wc/store/cart');
        if (!store) {
            (window.wlm_params?.debug) && console.log('[WLM CSS] Store not available yet');
            return;
        }
        
        const cartData = store.getCartData();
        const extensions = cartData?.extensions || {};
        const wlmExtension = extensions['woo-lieferzeiten-manager'];
        
        (window.wlm_params?.debug) && console.log('[WLM CSS] WLM Extension:', wlmExtension);
        
        if (!wlmExtension || !wlmExtension.delivery_info) {
            (window.wlm_params?.debug) && console.log('[WLM CSS] No delivery info available');
            return;
        }
        
        const deliveryInfo = wlmExtension.delivery_info;
        const cartItemsStock = wlmExtension.cart_items_stock || {};
        (window.wlm_params?.debug) && console.log('[WLM CSS] Delivery info:', deliveryInfo);
        (window.wlm_params?.debug) && console.log('[WLM CSS] Cart items stock:', cartItemsStock);
        
        // Update hidden checkout fields with delivery timeframe
        updateCheckoutDeliveryFields(cartData, deliveryInfo);
        
        // DEBUG: Show all methods with their data
        (window.wlm_params?.debug) && console.log('[WLM DEBUG] === ALLE VERSANDMETHODEN IN DATEN ===');
        Object.keys(deliveryInfo).forEach(function(methodId) {
            const info = deliveryInfo[methodId];
            (window.wlm_params?.debug) && console.log('[WLM DEBUG] Method ID: ' + methodId);
            (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Name: ' + (info.method_name || 'NICHT GESETZT'));
            (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Lieferzeitraum: ' + (info.delivery_window || 'NICHT GESETZT'));
            (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Express: ' + (info.express_window || 'NICHT GESETZT'));
            (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Alle Daten:', info);
        });
        (window.wlm_params?.debug) && console.log('[WLM DEBUG] === ENDE ===');
        
        // Find all shipping method labels in totals section
        const totalsLabels = document.querySelectorAll('.wc-block-components-totals-item__label');
        (window.wlm_params?.debug) && console.log('[WLM CSS] Found ' + totalsLabels.length + ' totals labels');
        
        // Find all shipping option labels (radio buttons)
        const shippingLabels = document.querySelectorAll('label[for^="radio-control-"]');
        (window.wlm_params?.debug) && console.log('[WLM CSS] Found ' + shippingLabels.length + ' shipping option labels');
        
        // Create or update style element
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'wlm-delivery-info-css';
            document.head.appendChild(styleElement);
        }
        
        // Build CSS rules
        let cssRules = '';
        let matchedCount = 0;
        
        // Process shipping option labels (radio buttons)
        shippingLabels.forEach(function(label) {
            const forAttr = label.getAttribute('for');
            if (!forAttr) return;
            
            // Extract method ID from for attribute
            // Format: radio-control-0-wlm_method_1762783567431 or radio-control-0-wlm_method_1762783567431_express:10
            const parts = forAttr.split('-');
            if (parts.length < 4) return;
            
            // Get everything after "radio-control-0-"
            const methodPart = parts.slice(3).join('-');
            // Remove instance ID suffix (e.g. ":10")
            const methodId = methodPart.split(':')[0];
            
            (window.wlm_params?.debug) && console.log('[WLM CSS] Shipping label for="' + forAttr + '" -> methodId="' + methodId + '"');
            
            // Find matching delivery info
            const info = deliveryInfo[methodId];
            if (info && info.delivery_window) {
                const isExpressRate = info.is_express_rate || false;
                const icon = isExpressRate ? '⚡' : '📦';
                const prefix = isExpressRate ? 'Express-Lieferung' : 'Voraussichtliche Lieferung';
                
                (window.wlm_params?.debug) && console.log('[WLM CSS] ✅ Match for shipping option: ' + methodId + ' - ' + info.delivery_window);
                
                // Add CSS rule for this specific label using for attribute
                cssRules += 'label[for="' + forAttr + '"] {\n';
                cssRules += '    position: relative;\n';
                cssRules += '    display: block;\n';
                cssRules += '    padding-bottom: 20px;\n';
                cssRules += '}\n';
                // Add padding to radio option container for delivery info space
                cssRules += '.wc-block-checkout__shipping-option .wc-block-components-radio-control__option {\n';
                cssRules += '    padding: .875em .875em 1.75em 3.5em !important;\n';
                cssRules += '}\n';
                cssRules += 'label[for="' + forAttr + '"]::before {\n';
                cssRules += '    content: "\\A' + icon + ' ' + prefix + ': ' + info.delivery_window + '";\n';
                cssRules += '    position: absolute;\n';
                cssRules += '    bottom: 10px;\n';
                cssRules += '    font-size: 12px;\n';
                cssRules += '    line-height: 1.5;\n';
                cssRules += '    white-space: pre-line;\n';
                cssRules += '    color: #666;\n';
                cssRules += '}\n';
                
                matchedCount++;
            }
        });
        
        // Process totals labels (for summary section)
        totalsLabels.forEach(function(label, index) {
            const labelText = label.textContent.trim();
            (window.wlm_params?.debug) && console.log('[WLM CSS] Label ' + index + ': "' + labelText + '"');
            
            // Try to find matching method in delivery info
            Object.keys(deliveryInfo).forEach(function(methodId) {
                const info = deliveryInfo[methodId];
                const methodName = info.method_name || '';
                
                // Only log if method name exists
                if (methodName) {
                    (window.wlm_params?.debug) && console.log('[WLM CSS] Comparing "' + labelText + '" with "' + methodName + '"');
                }
                
                // Match by exact method name
                if (labelText === methodName) {
                    const isExpressRate = info.is_express_rate || false;
                    (window.wlm_params?.debug) && console.log('[WLM DEBUG] ✅ MATCH GEFUNDEN!');
                    (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Label im Checkout: "' + labelText + '"');
                    (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Method Name: "' + methodName + '"');
                    (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Lieferzeitraum: ' + (info.delivery_window || 'FEHLT'));
                    (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - Express: ' + (info.express_window || 'FEHLT'));
                    
                    // Build content string
                    let content = '';
                    
                    if (isExpressRate) {
                        // For Express rates, show delivery window as Express delivery
                        if (info.delivery_window) {
                            content += '\\A⚡ Express-Lieferung: ' + info.delivery_window;
                        }
                    } else {
                        // For normal rates, show normal delivery window
                        if (info.delivery_window) {
                            content += '\\A📦 Voraussichtliche Lieferung: ' + info.delivery_window;
                        }
                        
                        // Check if Express is available for this method
                        const expressMethodId = methodId + '_express';
                        const expressInfo = deliveryInfo[expressMethodId];
                        
                        // Only show Express hint in Cart OR in Checkout summary when no method is selected yet
                        const isCheckout = document.body.classList.contains('woocommerce-checkout');
                        const isCart = document.body.classList.contains('woocommerce-cart');
                        const showExpressHint = isCart || !isCheckout; // Show in Cart, hide in Checkout summary
                        
                        if (expressInfo && expressInfo.delivery_window && showExpressHint) {
                            // Extract end date from Express window (e.g. "Mi, 12.11. – Do, 13.11." -> "Do, 13.11.")
                            const expressWindow = expressInfo.delivery_window;
                            const parts = expressWindow.split('–');
                            const expressEndDate = parts.length > 1 ? parts[1].trim() : expressWindow;
                            content += '\\A⚡ Express-Lieferung bis ' + expressEndDate + ' im Checkout verfügbar';
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
                                (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - CSS Position: nth-child(' + position + ')');
                                (window.wlm_params?.debug) && console.log('[WLM DEBUG]   - CSS Content wird gesetzt: ' + content);
                                
                                // Add CSS rules using nth-child
                                cssRules += `
/* Make label relative for absolute positioning of ::after */
.wc-block-components-totals-shipping .wc-block-components-totals-item:nth-child(${position}) .wc-block-components-totals-item__label {
    position: relative;
    display: inline-block;
    margin-bottom: 50px; /* Space for delivery info */
}

/* Delivery info as ::after with absolute positioning */
.wc-block-components-totals-shipping .wc-block-components-totals-item:nth-child(${position}) .wc-block-components-totals-item__label::after {
    content: "${content}";
    position: absolute;
    left: 0;
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.5;
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
        
        // Add cart stock status CSS
        const cartItems = document.querySelectorAll('.wc-block-cart-items__row');
        (window.wlm_params?.debug) && console.log('[WLM CSS] Found ' + cartItems.length + ' cart items');
        
        const cartItemKeys = Object.keys(cartItemsStock);
        cartItemKeys.forEach(function(cartItemKey, index) {
            const stock = cartItemsStock[cartItemKey];
            if (!stock) return;
            
            const rowIndex = index + 1; // nth-child is 1-indexed
            (window.wlm_params?.debug) && console.log('[WLM CSS] Adding stock status for row ' + rowIndex + ':', stock);
            
            let content = '';
            let color = '';
            
            // Use the complete message from backend if available
            if (stock.message) {
                // Determine color based on in_stock status
                if (stock.in_stock) {
                    color = '#4caf50'; // Green
                    content = stock.message;
                } else if (stock.available_date_formatted) {
                    color = '#ff9800'; // Yellow
                    content = stock.message;
                } else {
                    color = '#f44336'; // Red
                    content = stock.message;
                }
            } else {
                // Fallback to old logic if message is not available
                if (stock.in_stock) {
                    color = '#4caf50';
                    content = 'Auf Lager';
                } else if (stock.available_date_formatted) {
                    color = '#ff9800';
                    content = 'Wieder verfügbar ab ' + stock.available_date_formatted;
                } else {
                    color = '#f44336';
                    content = 'Nicht verfügbar';
                }
            }
            
            // Add CSS rule for this specific row with colored circle via background
            cssRules += `
.wc-block-cart-items__row:nth-child(${rowIndex}) .wc-block-cart-item__quantity::before {
    content: "${content}";
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    color: ${color};
    white-space: nowrap;
    padding-left: 18px;
    background: radial-gradient(circle, ${color} 5px, transparent 5px);
    background-size: 10px 10px;
    background-position: left center;
    background-repeat: no-repeat;
}
`;
        });
        
        // Apply CSS rules
        styleElement.textContent = cssRules;
        (window.wlm_params?.debug) && console.log('[WLM CSS] CSS rules applied (' + matchedCount + ' matches + ' + cartItemKeys.length + ' stock statuses):', cssRules);
    }
    
    /**
     * Update hidden checkout fields with delivery timeframe
     */
    function updateCheckoutDeliveryFields(cartData, deliveryInfo) {
        // Only on checkout page
        if (!document.body.classList.contains('woocommerce-checkout')) {
            return;
        }
        
        // Get selected shipping method from cart data
        const shippingRates = cartData.shippingRates?.[0]?.shipping_rates || [];
        const selectedMethod = shippingRates.find(rate => rate.selected);
        
        if (!selectedMethod) {
            (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] No shipping method selected');
            return;
        }
        
        // Extract method ID from rate ID (format: "wlm_method_123:1")
        const methodId = selectedMethod.rate_id.split(':')[0];
        
        (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] Selected method ID:', methodId);
        
        // Check if this is a WLM method
        if (!methodId.startsWith('wlm_method_')) {
            (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] Not a WLM method, skipping');
            return;
        }
        
        // Get delivery info for this method
        const info = deliveryInfo[methodId];
        if (!info) {
            (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] No delivery info for method:', methodId);
            return;
        }
        
        (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] Delivery info for selected method:', info);
        
        // Extract data
        const earliestDate = info.earliest_date || '';
        const latestDate = info.latest_date || '';
        const deliveryWindow = info.delivery_window || '';
        const methodName = info.method_name || '';
        const shipByDate = info.ship_by_date || '';
        
        // Find or create hidden fields
        let earliestField = document.getElementById('wlm_earliest_delivery');
        let latestField = document.getElementById('wlm_latest_delivery');
        let windowField = document.getElementById('wlm_delivery_window');
        let methodField = document.getElementById('wlm_shipping_method_name');
        
        // Create fields if they don't exist
        if (!earliestField) {
            earliestField = document.createElement('input');
            earliestField.type = 'hidden';
            earliestField.name = 'wlm_earliest_delivery';
            earliestField.id = 'wlm_earliest_delivery';
            document.querySelector('form.wc-block-checkout__form')?.appendChild(earliestField);
        }
        
        if (!latestField) {
            latestField = document.createElement('input');
            latestField.type = 'hidden';
            latestField.name = 'wlm_latest_delivery';
            latestField.id = 'wlm_latest_delivery';
            document.querySelector('form.wc-block-checkout__form')?.appendChild(latestField);
        }
        
        if (!windowField) {
            windowField = document.createElement('input');
            windowField.type = 'hidden';
            windowField.name = 'wlm_delivery_window';
            windowField.id = 'wlm_delivery_window';
            document.querySelector('form.wc-block-checkout__form')?.appendChild(windowField);
        }
        
        if (!methodField) {
            methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = 'wlm_shipping_method_name';
            methodField.id = 'wlm_shipping_method_name';
            document.querySelector('form.wc-block-checkout__form')?.appendChild(methodField);
        }
        
        // Update values
        if (earliestField) earliestField.value = earliestDate;
        if (latestField) latestField.value = latestDate;
        if (windowField) windowField.value = deliveryWindow;
        if (methodField) methodField.value = methodName;
        
        (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] Updated hidden fields:', {
            earliest: earliestDate,
            latest: latestDate,
            window: deliveryWindow,
            method: methodName,
            ship_by: shipByDate
        });
        
        // Save to session via AJAX
        if (earliestDate && latestDate && window.wlm_params) {
            jQuery.ajax({
                url: window.wlm_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wlm_save_delivery_to_session',
                    nonce: window.wlm_params.nonce,
                    earliest: earliestDate,
                    latest: latestDate,
                    window: deliveryWindow,
                    method_name: methodName,
                    ship_by: shipByDate
                },
                success: function(response) {
                    (window.wlm_params?.debug) && console.log('[WLM Checkout Fields] Saved to session:', response);
                },
                error: function(xhr, status, error) {
                    (window.wlm_params?.debug) && console.error('[WLM Checkout Fields] Failed to save to session:', error);
                }
            });
        }
    }
    
    /**
     * Initialize
     */
    function init() {
        (window.wlm_params?.debug) && console.log('[WLM CSS] Initializing...');
        
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
                        (window.wlm_params?.debug) && console.log('[WLM CSS] Cart data changed, re-adding CSS...');
                        setTimeout(addDeliveryInfoCSS, 300);
                    }
                }
            });
        }
    }
    
    init();
})();
