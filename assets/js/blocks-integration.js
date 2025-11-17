/**
 * WooCommerce Blocks Integration
 * 
 * Displays delivery information in Cart and Checkout blocks
 */

const { registerPlugin } = wp.plugins;
const { ExperimentalOrderMeta } = wc.blocksCheckout;
const { createElement } = wp.element;
const { useSelect } = wp.data;
const { CART_STORE_KEY } = wc.wcBlocksData;

/**
 * Delivery Info Component for Cart/Checkout Blocks
 */
const DeliveryInfoComponent = () => {
    // Get cart data including our custom extension
    const cartData = useSelect((select) => {
        const store = select(CART_STORE_KEY);
        return {
            extensions: store.getCartData()?.extensions || {},
        };
    }, []);

    const deliveryData = cartData.extensions['woo-lieferzeiten-manager'] || {};
    const deliveryWindow = deliveryData.delivery_window || {};
    const expressStatus = deliveryData.express_status || {};

    // Don't render if no delivery window
    if (!deliveryWindow || !deliveryWindow.window_formatted) {
        return null;
    }

    return createElement(
        'div',
        { className: 'wlm-blocks-delivery-info', style: { marginTop: '20px', padding: '15px', background: '#f7f7f7', borderRadius: '4px' } },
        [
            // Stock status (if available)
            deliveryWindow.stock_status && createElement(
                'div',
                { key: 'stock', className: 'wlm-stock-status', style: { marginBottom: '10px' } },
                [
                    createElement('span', { key: 'icon' }, deliveryWindow.stock_status.in_stock ? '🟢 ' : '🟡 '),
                    createElement('span', { key: 'text' }, deliveryWindow.stock_status.message || '')
                ]
            ),

            // Shipping method (if available)
            deliveryWindow.shipping_method && deliveryWindow.shipping_method.title && createElement(
                'div',
                { key: 'shipping', className: 'wlm-shipping-method', style: { marginBottom: '10px' } },
                [
                    createElement('span', { key: 'icon' }, '🚚 '),
                    createElement('span', { key: 'label' }, 'Versand via '),
                    createElement('strong', { key: 'method' }, deliveryWindow.shipping_method.title),
                    deliveryWindow.shipping_method.cost_info && createElement(
                        'span',
                        { key: 'info', className: 'wlm-info-icon', title: deliveryWindow.shipping_method.cost_info, style: { marginLeft: '5px' } },
                        'ℹ️'
                    )
                ]
            ),

            // Delivery window
            createElement(
                'div',
                { key: 'delivery', className: 'wlm-delivery-window', style: { marginBottom: '10px' } },
                [
                    createElement('span', { key: 'icon' }, '📅 '),
                    createElement('span', { key: 'label' }, 'Lieferung ca.: '),
                    createElement('strong', { key: 'window' }, deliveryWindow.window_formatted)
                ]
            ),

            // Express option (if available)
            expressStatus.available && !expressStatus.active && createElement(
                'div',
                { key: 'express', className: 'wlm-express-cta', style: { marginTop: '15px' } },
                createElement(
                    'button',
                    {
                        type: 'button',
                        className: 'button wlm-activate-express',
                        style: { padding: '10px 15px', cursor: 'pointer' },
                        onClick: () => {
                            // Express activation would require AJAX call
                            (window.wlm_params?.debug) && console.log('Express activation clicked');
                        }
                    },
                    '⚡ Express-Versand verfügbar'
                )
            ),

            // Express active status
            expressStatus.active && createElement(
                'div',
                { key: 'express-active', className: 'wlm-express-active', style: { marginTop: '15px', color: '#2c7a2c' } },
                [
                    createElement('span', { key: 'checkmark' }, '✓ '),
                    createElement('span', { key: 'text' }, 'Express-Versand gewählt')
                ]
            )
        ].filter(Boolean) // Remove null elements
    );
};

/**
 * Register the plugin for Checkout block
 */
if (typeof ExperimentalOrderMeta !== 'undefined') {
    registerPlugin('woo-lieferzeiten-manager', {
        render: () => {
            return createElement(
                ExperimentalOrderMeta,
                null,
                createElement(DeliveryInfoComponent)
            );
        },
        scope: 'woocommerce-checkout',
    });
}

/**
 * For Cart block, we need to use a different approach
 * Add delivery info to cart totals area
 */
if (typeof wp.hooks !== 'undefined') {
    const { addFilter } = wp.hooks;
    
    // Add delivery info after cart totals
    addFilter(
        'woocommerce_blocks_cart_totals_after',
        'woo-lieferzeiten-manager',
        (Component) => {
            return (props) => {
                return createElement(
                    'div',
                    null,
                    [
                        createElement(Component, { ...props, key: 'original' }),
                        createElement(DeliveryInfoComponent, { key: 'delivery-info' })
                    ]
                );
            };
        }
    );
}

/**
 * Filter shipping methods based on product conditions
 */
if (typeof wc !== 'undefined' && wc.blocksCheckout && wc.blocksCheckout.__experimentalRegisterCheckoutFilters) {
    const { __experimentalRegisterCheckoutFilters, __experimentalApplyCheckoutFilter } = wc.blocksCheckout;
    
    __experimentalRegisterCheckoutFilters('woo-lieferzeiten-manager', {
        shippingMethods: (shippingMethods, extensions, args) => {
            (window.wlm_params?.debug) && console.log('[WLM] Filtering shipping methods', shippingMethods);
            
            // Get cart items with attributes
            const cartData = extensions?.['woo-lieferzeiten-manager'] || {};
            const cartItemsStock = cartData.cart_items_stock || {};
            const deliveryInfo = cartData.delivery_info || {};
            
            (window.wlm_params?.debug) && console.log('[WLM] Cart items stock:', cartItemsStock);
            (window.wlm_params?.debug) && console.log('[WLM] Delivery info:', deliveryInfo);
            
            // Filter shipping methods based on conditions
            return shippingMethods.filter(method => {
                (window.wlm_params?.debug) && console.log('[WLM] Checking method:', method.id, method);
                
                // Find method config in delivery_info
                const methodConfig = Object.values(deliveryInfo).find(info => 
                    info.method_id === method.id || 
                    method.id.includes(info.method_id)
                );
                
                if (!methodConfig) {
                    (window.wlm_params?.debug) && console.log('[WLM] No config found for method:', method.id);
                    return true; // Keep method if no config
                }
                
                (window.wlm_params?.debug) && console.log('[WLM] Method config:', methodConfig);
                
                // Check if method has attribute conditions
                const conditions = methodConfig.attribute_conditions || [];
                if (conditions.length === 0) {
                    (window.wlm_params?.debug) && console.log('[WLM] No conditions for method:', method.id);
                    return true; // Keep method if no conditions
                }
                
                (window.wlm_params?.debug) && console.log('[WLM] Checking', conditions.length, 'conditions');
                
                // Check each product in cart against all conditions
                for (const [cartItemKey, itemData] of Object.entries(cartItemsStock)) {
                    (window.wlm_params?.debug) && console.log('[WLM] Checking product:', itemData);
                    
                    const productAttrs = itemData.attributes || {};
                    const productCats = itemData.categories || [];
                    
                    // Check all conditions for this product
                    for (const condition of conditions) {
                        const attrSlug = condition.attribute;
                        const requiredValues = (condition.values || []).map(v => v.toLowerCase());
                        const logic = condition.logic || 'at_least_one';
                        
                        (window.wlm_params?.debug) && console.log('[WLM] Condition:', logic, attrSlug, requiredValues);
                        
                        // Get product values for this attribute
                        let productValues = [];
                        if (attrSlug === 'product_cat') {
                            productValues = productCats;
                        } else if (productAttrs[attrSlug]) {
                            const attrValue = productAttrs[attrSlug];
                            productValues = Array.isArray(attrValue) ? attrValue : [attrValue];
                        }
                        
                        // Normalize to lowercase
                        productValues = productValues.map(v => String(v).toLowerCase());
                        
                        (window.wlm_params?.debug) && console.log('[WLM] Product values:', productValues);
                        
                        // Check logic
                        let conditionMet = false;
                        
                        switch (logic) {
                            case 'at_least_one':
                                conditionMet = requiredValues.some(v => productValues.includes(v));
                                break;
                            case 'all':
                                conditionMet = requiredValues.every(v => productValues.includes(v));
                                break;
                            case 'none':
                                conditionMet = !requiredValues.some(v => productValues.includes(v));
                                break;
                            case 'only':
                                conditionMet = productValues.every(v => requiredValues.includes(v));
                                break;
                        }
                        
                        (window.wlm_params?.debug) && console.log('[WLM] Condition met:', conditionMet);
                        
                        if (!conditionMet) {
                            (window.wlm_params?.debug) && console.log('[WLM] Product does not meet condition - hiding method:', method.id);
                            return false; // Hide this shipping method
                        }
                    }
                }
                
                (window.wlm_params?.debug) && console.log('[WLM] All conditions met - showing method:', method.id);
                return true; // Show this shipping method
            });
        }
    });
}
