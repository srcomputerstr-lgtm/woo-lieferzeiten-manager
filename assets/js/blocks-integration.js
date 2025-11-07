/**
 * Blocks integration for Cart and Checkout blocks
 * 
 * This file should be built with @wordpress/scripts or similar build tools
 * for production use with WooCommerce Blocks.
 *
 * @package WooLieferzeitenManager
 */

(function() {
    'use strict';

    // Check if WooCommerce Blocks are available
    if (typeof window.wc === 'undefined' || typeof window.wc.blocksCheckout === 'undefined') {
        console.warn('WLM: WooCommerce Blocks not available');
        return;
    }

    const { registerCheckoutBlock } = window.wc.blocksCheckout;
    const { createElement } = window.wp.element;
    const { __ } = window.wp.i18n;

    /**
     * Delivery Window Block Component
     */
    const DeliveryWindowBlock = ({ checkoutExtensionData }) => {
        const extensionData = checkoutExtensionData['woo-lieferzeiten-manager'] || {};
        const deliveryWindow = extensionData.delivery_window;
        const expressStatus = extensionData.express_status;

        if (!deliveryWindow || !deliveryWindow.window_formatted) {
            return null;
        }

        return createElement(
            'div',
            { className: 'wlm-checkout-delivery-window' },
            createElement(
                'div',
                { className: 'wlm-delivery-estimate' },
                createElement('strong', null, __('Deine Bestellung voraussichtlich:', 'woo-lieferzeiten-manager')),
                createElement('br'),
                createElement('span', null, deliveryWindow.window_formatted)
            ),
            expressStatus && expressStatus.selected && createElement(
                'div',
                { className: 'wlm-express-indicator' },
                __('Express-Versand aktiv', 'woo-lieferzeiten-manager')
            )
        );
    };

    /**
     * Register the block
     */
    if (typeof registerCheckoutBlock === 'function') {
        registerCheckoutBlock({
            metadata: {
                name: 'woo-lieferzeiten-manager/delivery-window',
                parent: ['woocommerce/checkout-order-summary-block']
            },
            component: DeliveryWindowBlock
        });
    }

})();
