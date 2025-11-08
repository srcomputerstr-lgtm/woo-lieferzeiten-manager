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
                            console.log('Express activation clicked');
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
