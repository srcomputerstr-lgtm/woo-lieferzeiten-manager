/**
 * WooCommerce Blocks Delivery Info Slot Fill
 * 
 * Uses ExperimentalOrderShippingPackages to display delivery windows
 * and express options below shipping methods in Cart/Checkout blocks.
 */

(function() {
    'use strict';
    
    const { registerPlugin } = wp.plugins;
    const { ExperimentalOrderShippingPackages } = wc.blocksCheckout;
    const { createElement: el, Fragment, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect } = wp.data;
    
    (window.wlm_params?.debug) && console.log('[WLM Blocks] Script loaded');
    (window.wlm_params?.debug) && console.log('[WLM Blocks] Available globals:', {
        wp: typeof wp,
        wc: typeof wc,
        'wp.data': typeof wp.data,
        'wp.plugins': typeof wp.plugins,
        'wc.blocksCheckout': typeof wc.blocksCheckout
    });
    
    /**
     * Delivery Info Component
     * Renders delivery window and express option for selected shipping method
     */
    const DeliveryInfoSlotFill = () => {
        (window.wlm_params?.debug) && console.log('[WLM Blocks] DeliveryInfoSlotFill render started');
        
        // Get cart data from WooCommerce store
        const cartData = useSelect((select) => {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] useSelect callback running');
            
            // Check if store is available
            const storeSelect = select('wc/store/cart');
            (window.wlm_params?.debug) && console.log('[WLM Blocks] Store select:', storeSelect);
            
            if (!storeSelect) {
                console.warn('[WLM Blocks] wc/store/cart not available');
                return null;
            }
            
            // Get cart data
            const cart = storeSelect.getCartData();
            (window.wlm_params?.debug) && console.log('[WLM Blocks] Cart data from store:', cart);
            
            return cart;
        }, []);
        
        // Debug effect
        useEffect(() => {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] Component mounted/updated');
            (window.wlm_params?.debug) && console.log('[WLM Blocks] Cart data:', cartData);
        }, [cartData]);
        
        if (!cartData) {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] No cart data available yet');
            return null;
        }
        
        // Extract cart and extensions
        const cart = cartData;
        const extensions = cartData.extensions || {};
        
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Extensions:', extensions);
        (window.wlm_params?.debug) && console.log('[WLM Blocks] WLM Extension:', extensions['woo-lieferzeiten-manager']);
        
        if (!cart) {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] No cart data available');
            return null;
        }
        
        // Get selected shipping method
        const shippingRates = cart.shippingRates?.[0]?.shipping_rates || [];
        const selectedMethod = shippingRates.find(rate => rate.selected);
        
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Shipping rates:', shippingRates);
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Selected method:', selectedMethod);
        
        if (!selectedMethod) {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] No shipping method selected');
            return null;
        }
        
        // Extract method ID from rate ID (format: "wlm_method_123:1")
        const methodId = selectedMethod.rate_id.split(':')[0];
        
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Method ID:', methodId);
        
        // Check if this is a WLM method
        if (!methodId.startsWith('wlm_method_')) {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] Not a WLM method, skipping');
            return null;
        }
        
        // Get delivery info from extensions (namespace: woo-lieferzeiten-manager)
        const allDeliveryInfo = extensions?.['woo-lieferzeiten-manager']?.delivery_info || {};
        const deliveryInfo = allDeliveryInfo[methodId] || {};
        
        (window.wlm_params?.debug) && console.log('[WLM Blocks] All delivery info:', allDeliveryInfo);
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Delivery info for method:', deliveryInfo);
        
        if (!deliveryInfo.delivery_window && !deliveryInfo.express_available) {
            (window.wlm_params?.debug) && console.log('[WLM Blocks] No delivery info available for this method');
            return null;
        }
        
        (window.wlm_params?.debug) && console.log('[WLM Blocks] Rendering delivery info UI');
        
        // Render delivery window using Slot Fill
        return el(
            ExperimentalOrderShippingPackages.Fill,
            null,
            el(
                'div',
                { 
                    className: 'wlm-blocks-delivery-info',
                    style: {
                        marginTop: '12px',
                        padding: '12px',
                        backgroundColor: '#f7f7f7',
                        borderLeft: '3px solid #2271b1',
                        fontSize: '14px'
                    }
                },
                [
                    // Delivery Window
                    deliveryInfo.delivery_window && el(
                        'div',
                        { 
                            key: 'delivery-window',
                            className: 'wlm-delivery-window',
                            style: { marginBottom: '8px' }
                        },
                        [
                            el(
                                'strong',
                                { key: 'label' },
                                __('Voraussichtliche Lieferung:', 'woo-lieferzeiten-manager')
                            ),
                            ' ',
                            el(
                                'span',
                                { key: 'value' },
                                deliveryInfo.delivery_window
                            )
                        ]
                    ),
                    
                    // Express Option
                    deliveryInfo.express_available && el(
                        'div',
                        { 
                            key: 'express-option',
                            className: 'wlm-express-option'
                        },
                        deliveryInfo.is_express_selected
                            ? el(
                                'div',
                                { 
                                    className: 'wlm-express-active',
                                    style: {
                                        padding: '8px',
                                        backgroundColor: '#d4edda',
                                        borderRadius: '4px',
                                        color: '#155724'
                                    }
                                },
                                [
                                    el('span', { key: 'check' }, '✓ '),
                                    el('strong', { key: 'text' }, __('Express-Versand gewählt', 'woo-lieferzeiten-manager')),
                                    el('br', { key: 'br' }),
                                    el('span', { key: 'window' }, [
                                        __('Zustellung: ', 'woo-lieferzeiten-manager'),
                                        el('strong', { key: 'date' }, deliveryInfo.express_window || 'N/A')
                                    ])
                                ]
                            )
                            : el(
                                'button',
                                {
                                    type: 'button',
                                    className: 'wlm-activate-express',
                                    'data-method-id': methodId,
                                    style: {
                                        width: '100%',
                                        padding: '10px',
                                        backgroundColor: '#2271b1',
                                        color: '#fff',
                                        border: 'none',
                                        borderRadius: '4px',
                                        cursor: 'pointer',
                                        fontSize: '14px'
                                    },
                                    onClick: () => {
                                        (window.wlm_params?.debug) && console.log('[WLM Blocks] Express button clicked for method:', methodId);
                                        // Trigger AJAX to activate express
                                        if (window.WLM_Frontend) {
                                            window.WLM_Frontend.activateExpress(methodId);
                                        }
                                    }
                                },
                                [
                                    '⚡ ',
                                    __('Express-Versand', 'woo-lieferzeiten-manager'),
                                    ' (' + deliveryInfo.express_cost + ' €) – ',
                                    __('Zustellung: ', 'woo-lieferzeiten-manager'),
                                    el('strong', { key: 'date' }, deliveryInfo.express_window || 'N/A')
                                ]
                            )
                    )
                ].filter(Boolean) // Remove null elements
            )
        );
    };
    
    /**
     * Register the plugin
     */
    (window.wlm_params?.debug) && console.log('[WLM Blocks] Registering plugin...');
    
    registerPlugin('wlm-delivery-info-slot-fill', {
        render: DeliveryInfoSlotFill,
        scope: 'woocommerce-checkout'
    });
    
    (window.wlm_params?.debug) && console.log('[WLM Blocks] Plugin registered: wlm-delivery-info-slot-fill');
    
})();
