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
    const { createElement: el, Fragment } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect } = wp.data;
    
    /**
     * Delivery Info Component
     * Renders delivery window and express option for selected shipping method
     */
    const DeliveryInfoSlotFill = () => {
        console.log('[WLM Blocks] DeliveryInfoSlotFill rendered');
        
        // Get cart data from WooCommerce store
        const { cart, extensions } = useSelect((select) => {
            const store = select('wc/store/cart');
            return {
                cart: store.getCartData(),
                extensions: store.getCartData()?.extensions || {}
            };
        });
        
        console.log('[WLM Blocks] Cart data:', cart);
        console.log('[WLM Blocks] Extensions:', extensions);
        
        if (!cart) {
            console.log('[WLM Blocks] No cart data available');
            return null;
        }
        
        // Get selected shipping method
        const shippingRates = cart.shippingRates?.[0]?.shipping_rates || [];
        const selectedMethod = shippingRates.find(rate => rate.selected);
        
        console.log('[WLM Blocks] Shipping rates:', shippingRates);
        console.log('[WLM Blocks] Selected method:', selectedMethod);
        
        if (!selectedMethod) {
            console.log('[WLM Blocks] No shipping method selected');
            return null;
        }
        
        // Extract method ID from rate ID (format: "wlm_method_123:1")
        const methodId = selectedMethod.rate_id.split(':')[0];
        
        console.log('[WLM Blocks] Method ID:', methodId);
        
        // Check if this is a WLM method
        if (!methodId.startsWith('wlm_method_')) {
            console.log('[WLM Blocks] Not a WLM method, skipping');
            return null;
        }
        
        // Get delivery info from extensions (namespace: woo-lieferzeiten-manager)
        const allDeliveryInfo = extensions?.['woo-lieferzeiten-manager']?.delivery_info || {};
        const deliveryInfo = allDeliveryInfo[methodId] || {};
        
        console.log('[WLM Blocks] Delivery info from extensions:', deliveryInfo);
        
        if (!deliveryInfo.delivery_window && !deliveryInfo.express_available) {
            console.log('[WLM Blocks] No delivery info available');
            return null;
        }
        
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
                                        el('strong', { key: 'date' }, deliveryInfo.express_window)
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
                                        console.log('[WLM Blocks] Express button clicked for method:', methodId);
                                        // Trigger AJAX to activate express
                                        if (window.WLM_Frontend) {
                                            window.WLM_Frontend.activateExpress(methodId);
                                        }
                                    }
                                },
                                [
                                    '⚡ ',
                                    __('Express-Versand', 'woo-lieferzeiten-manager'),
                                    ' (' + deliveryInfo.express_cost_formatted + ') – ',
                                    __('Zustellung: ', 'woo-lieferzeiten-manager'),
                                    el('strong', { key: 'date' }, deliveryInfo.express_window)
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
    registerPlugin('wlm-delivery-info-slot-fill', {
        render: DeliveryInfoSlotFill,
        scope: 'woocommerce-checkout'
    });
    
    console.log('[WLM Blocks] Plugin registered: wlm-delivery-info-slot-fill');
    
})();
