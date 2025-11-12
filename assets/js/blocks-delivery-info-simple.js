/**
 * Simple JavaScript solution for displaying delivery info in WooCommerce Blocks Checkout
 * No React Slot-Fills, just plain JavaScript + CSS
 */

(function() {
    'use strict';
    
    console.log('[WLM Simple] Script loaded');
    
    /**
     * Add delivery info to shipping methods
     */
    function addDeliveryInfo() {
        console.log('[WLM Simple] Looking for shipping methods...');
        
        // Find all shipping method labels
        const labels = document.querySelectorAll('.wc-block-components-totals-item__label');
        
        console.log('[WLM Simple] Found ' + labels.length + ' labels');
        
        labels.forEach((label, index) => {
            const labelText = label.textContent.trim();
            console.log('[WLM Simple] Label ' + index + ': ' + labelText);
            
            // Skip if not a shipping method (e.g., "Szacowana łączna kwota")
            const item = label.closest('.wc-block-components-totals-item');
            if (!item) return;
            
            // Check if this is a shipping method item (has radio input)
            const radio = item.querySelector('input[type="radio"]');
            if (!radio) {
                console.log('[WLM Simple] No radio input, skipping');
                return;
            }
            
            const rateId = radio.value;
            const methodId = rateId.replace(/:[0-9]+$/, '');
            
            console.log('[WLM Simple] Found shipping method: ' + methodId);
            
            // Check if delivery info already added
            if (item.querySelector('.wlm-delivery-info-simple')) {
                console.log('[WLM Simple] Delivery info already added, skipping');
                return;
            }
            
            // Get delivery info from Store API Extension
            if (window.wp && window.wp.data) {
                const store = window.wp.data.select('wc/store/cart');
                if (store) {
                    const cartData = store.getCartData();
                    const extensions = cartData?.extensions || {};
                    const wlmExtension = extensions['woo-lieferzeiten-manager'];
                    
                    console.log('[WLM Simple] WLM Extension:', wlmExtension);
                    
                    if (wlmExtension && wlmExtension.delivery_info) {
                        const deliveryInfo = wlmExtension.delivery_info[methodId];
                        
                        console.log('[WLM Simple] Delivery info for ' + methodId + ':', deliveryInfo);
                        
                        if (deliveryInfo) {
                            // Create delivery info container
                            const container = document.createElement('div');
                            container.className = 'wlm-delivery-info-simple';
                            
                            let html = '';
                            
                            // Delivery window
                            if (deliveryInfo.delivery_window) {
                                html += '<div class="wlm-delivery-window">';
                                html += '<strong>📦 Voraussichtliche Lieferung:</strong> ';
                                html += '<span>' + deliveryInfo.delivery_window + '</span>';
                                html += '</div>';
                            }
                            
                            // Express option
                            if (deliveryInfo.express_available) {
                                html += '<div class="wlm-express-option">';
                                
                                if (deliveryInfo.is_express_selected) {
                                    html += '<div class="wlm-express-active">';
                                    html += '✓ <strong>Express-Versand gewählt</strong><br>';
                                    html += 'Zustellung: <strong>' + (deliveryInfo.express_window || 'N/A') + '</strong>';
                                    html += '</div>';
                                } else {
                                    html += '<div class="wlm-express-button">';
                                    html += '⚡ <strong>Express-Versand</strong> (' + deliveryInfo.express_cost + ' €) – ';
                                    html += 'Zustellung: <strong>' + (deliveryInfo.express_window || 'N/A') + '</strong>';
                                    html += '</div>';
                                }
                                
                                html += '</div>';
                            }
                            
                            container.innerHTML = html;
                            
                            // Insert after the label's parent item
                            const description = item.querySelector('.wc-block-components-totals-item__description');
                            if (description) {
                                description.appendChild(container);
                            } else {
                                item.appendChild(container);
                            }
                            
                            console.log('[WLM Simple] Delivery info added!');
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Initialize
     */
    function init() {
        console.log('[WLM Simple] Initializing...');
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(addDeliveryInfo, 500);
            });
        } else {
            setTimeout(addDeliveryInfo, 500);
        }
        
        // Re-run when cart updates (WooCommerce Blocks uses React, so we need to watch for changes)
        if (window.wp && window.wp.data) {
            let previousCartData = null;
            
            window.wp.data.subscribe(function() {
                const store = window.wp.data.select('wc/store/cart');
                if (store) {
                    const cartData = store.getCartData();
                    
                    // Check if cart data changed
                    if (JSON.stringify(cartData) !== JSON.stringify(previousCartData)) {
                        previousCartData = cartData;
                        console.log('[WLM Simple] Cart data changed, re-adding delivery info...');
                        setTimeout(addDeliveryInfo, 300);
                    }
                }
            });
        }
    }
    
    init();
})();
