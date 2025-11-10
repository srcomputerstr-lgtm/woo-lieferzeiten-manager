/**
 * Frontend JavaScript for Woo Lieferzeiten Manager
 *
 * @package WooLieferzeitenManager
 */

(function($) {
    'use strict';

    var WLM_Frontend = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Product page: variation change
            $('form.variations_form').on('found_variation', this.onVariationChange.bind(this));
            $('form.variations_form').on('reset_data', this.onVariationReset.bind(this));

            // Product page: quantity change
            $('input.qty').on('change', this.onQuantityChange.bind(this));

            // Express activation
            $(document).on('click', '.wlm-activate-express', this.activateExpress.bind(this));

            // Express deactivation
            $(document).on('click', '.wlm-remove-express', this.deactivateExpress.bind(this));

            // Cart/checkout updates
            $(document.body).on('updated_cart_totals updated_checkout', this.onCartUpdate.bind(this));
            
            // Load delivery info via AJAX for shipping methods
            this.loadDeliveryInfoForShippingMethods();
            $(document.body).on('updated_cart_totals updated_checkout', this.loadDeliveryInfoForShippingMethods.bind(this));
        },

        /**
         * Handle variation change
         */
        onVariationChange: function(event, variation) {
            if (!variation || !variation.variation_id) {
                return;
            }

            var $panel = $('.wlm-pdp-panel');
            var productId = $panel.data('product-id');
            var quantity = $('input.qty').val() || 1;

            this.updateProductWindow(productId, variation.variation_id, quantity);
        },

        /**
         * Handle variation reset
         */
        onVariationReset: function() {
            var $panel = $('.wlm-pdp-panel');
            var productId = $panel.data('product-id');
            var quantity = $('input.qty').val() || 1;

            this.updateProductWindow(productId, 0, quantity);
        },

        /**
         * Handle quantity change
         */
        onQuantityChange: function() {
            var $panel = $('.wlm-pdp-panel');
            var productId = $panel.data('product-id');
            var variationId = $('input[name="variation_id"]').val() || 0;
            var quantity = $('input.qty').val() || 1;

            this.updateProductWindow(productId, variationId, quantity);
        },

        /**
         * Update product delivery window via AJAX
         */
        updateProductWindow: function(productId, variationId, quantity) {
            var $panel = $('.wlm-pdp-panel');

            if (!productId) {
                return;
            }

            $panel.addClass('wlm-loading');

            $.ajax({
                url: wlm_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wlm_calc_product_window',
                    nonce: wlm_params.nonce,
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success && response.data) {
                        this.renderProductWindow(response.data);
                    }
                }.bind(this),
                complete: function() {
                    $panel.removeClass('wlm-loading');
                }
            });
        },

        /**
         * Render product delivery window
         */
        renderProductWindow: function(data) {
            var $panel = $('.wlm-pdp-panel');
            var html = '';

            // Stock status
            if (data.stock_status) {
                var statusClass = data.stock_status.in_stock ? 'wlm--in-stock' : 'wlm--restock';
                var icon = data.stock_status.in_stock ? '🟢' : '🟡';
                html += '<div class="wlm-stock-status ' + statusClass + '">';
                html += icon + ' ' + data.stock_status.message;
                html += '</div>';
            }

            // Shipping method
            if (data.shipping_method) {
                html += '<div class="wlm-shipping-method">';
                html += '🚚 Versand via <strong>' + (data.shipping_method.title || 'Paketdienst') + '</strong>';
                html += '</div>';
            }

            // Delivery window
            if (data.window_formatted) {
                html += '<div class="wlm-delivery-window">';
                html += '📅 Lieferung ca.: <strong>' + data.window_formatted + '</strong>';
                html += '</div>';
            }

            $panel.html(html);
        },

        /**
         * Activate express shipping
         */
        activateExpress: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var methodId = $button.data('method-id');

            $button.prop('disabled', true).addClass('wlm-loading');

            $.ajax({
                url: wlm_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wlm_activate_express',
                    nonce: wlm_params.express_nonce,
                    method_id: methodId
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger cart/checkout update
                        $(document.body).trigger('update_checkout');
                        $('body').trigger('wc_update_cart');
                    } else {
                        alert(response.data.message || 'Fehler beim Aktivieren von Express');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('wlm-loading');
                }
            });
        },

        /**
         * Deactivate express shipping
         */
        deactivateExpress: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);

            $button.prop('disabled', true);

            $.ajax({
                url: wlm_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wlm_deactivate_express',
                    nonce: wlm_params.express_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger cart/checkout update
                        $(document.body).trigger('update_checkout');
                        $('body').trigger('wc_update_cart');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle cart/checkout updates
         */
        onCartUpdate: function() {
            // Move delivery time from label to below
            this.moveDeliveryTimeFromLabel();
        },

        /**
         * Move delivery time from shipping label to below the label
         */
        moveDeliveryTimeFromLabel: function() {
            // Find all shipping method labels
            $('.woocommerce-shipping-methods li label').each(function() {
                var $label = $(this);
                var $input = $label.find('input[type="radio"]');
                var methodId = $input.val();
                
                // Only process WLM methods
                if (!methodId || methodId.indexOf('wlm_method_') !== 0) {
                    return;
                }
                
                // Check if already processed
                if ($label.data('wlm-processed')) {
                    return;
                }
                $label.data('wlm-processed', true);
                
                // Extract delivery time from label HTML
                var labelHtml = $label.html();
                var deliveryTimeMatch = labelHtml.match(/<br><span[^>]*>([^<]+)<\/span>/);
                
                if (deliveryTimeMatch) {
                    // Remove delivery time from label
                    var cleanLabelHtml = labelHtml.replace(/<br><span[^>]*>.*?<\/span>/, '');
                    $label.html(cleanLabelHtml);
                    
                    // Add delivery time below label
                    var deliveryTimeHtml = deliveryTimeMatch[1];
                    var $deliveryTime = $('<div class="wlm-delivery-time" style="font-size: 0.9em; color: #666; margin-top: 5px;">' + deliveryTimeHtml + '</div>');
                    $label.closest('li').append($deliveryTime);
                }\n            });\n        },\n        \n        /**\n         * Load delivery info for shipping methods via AJAX\n         */\n        loadDeliveryInfoForShippingMethods: function() {\n            var self = this;\n            \n            // Wait for DOM to be ready\n            setTimeout(function() {\n                // Find all shipping rate items in blocks checkout\n                $('.wc-block-components-totals-item').each(function() {\n                    var $item = $(this);\n                    var $label = $item.find('.wc-block-components-totals-item__label');\n                    var $description = $item.find('.wc-block-components-totals-item__description');\n                    \n                    if ($label.length > 0 && $description.length > 0) {\n                        // Extract method ID from radio input value\n                        var $radio = $item.find('input[type="radio"]');\n                        if ($radio.length > 0) {\n                            var rateId = $radio.val();\n                            // Extract method ID (remove instance suffix)\n                            var methodId = rateId.replace(/:[0-9]+$/, '');\n                            \n                            // Load delivery info via AJAX\n                            self.fetchAndRenderDeliveryInfo(methodId, $description);\n                        }\n                    }\n                });\n                \n                // Also handle classic checkout\n                $('.woocommerce-shipping-methods li').each(function() {\n                    var $li = $(this);\n                    var $input = $li.find('input[type="radio"]');\n                    \n                    if ($input.length > 0) {\n                        var rateId = $input.val();\n                        var methodId = rateId.replace(/:[0-9]+$/, '');\n                        \n                        // Create container if not exists\n                        var $container = $li.find('.wlm-delivery-info-container');\n                        if ($container.length === 0) {\n                            $container = $('<div class="wlm-delivery-info-container"></div>');\n                            $li.append($container);\n                        }\n                        \n                        self.fetchAndRenderDeliveryInfo(methodId, $container);\n                    }\n                });\n            }, 100);\n        },\n        \n        /**\n         * Fetch delivery info via AJAX and render in target element\n         */\n        fetchAndRenderDeliveryInfo: function(methodId, $target) {\n            // Skip if already loading\n            if ($target.hasClass('wlm-loading')) {\n                return;\n            }\n            \n            $target.addClass('wlm-loading');\n            \n            $.ajax({\n                url: wlm_params.ajax_url,\n                type: 'POST',\n                data: {\n                    action: 'wlm_get_shipping_delivery_info',\n                    nonce: wlm_params.nonce,\n                    method_id: methodId\n                },\n                success: function(response) {\n                    if (response.success && response.data) {\n                        var html = '';\n                        \n                        // Delivery window\n                        if (response.data.delivery_window) {\n                            html += '<div class="wlm-order-window">';\n                            html += '<div class="wlm-delivery-estimate">';\n                            html += '<strong>Voraussichtliche Lieferung:</strong> ';\n                            html += '<span>' + response.data.delivery_window + '</span>';\n                            html += '</div>';\n                            html += '</div>';\n                        }\n                        \n                        // Express option\n                        if (response.data.express_available) {\n                            html += '<div class="wlm-express-section">';\n                            \n                            if (response.data.is_express_selected) {\n                                html += '<div class="wlm-express-active">';\n                                html += '<span class="wlm-checkmark">✓</span> ';\n                                html += '<strong>Express-Versand gewählt</strong><br>';\n                                html += '<span>Zustellung: <strong>' + response.data.express_window + '</strong></span>';\n                                html += '<button type="button" class="wlm-remove-express" data-method-id="' + methodId + '">✕ entfernen</button>';\n                                html += '</div>';\n                            } else {\n                                html += '<button type="button" class="wlm-activate-express" data-method-id="' + methodId + '">';\n                                html += '⚡ Express-Versand (' + response.data.express_cost_formatted + ') – ';\n                                html += 'Zustellung: <strong>' + response.data.express_window + '</strong>';\n                                html += '</button>';\n                            }\n                            \n                            html += '</div>';\n                        }\n                        \n                        $target.html(html);\n                        $target.show();\n                    }\n                },\n                complete: function() {\n                    $target.removeClass('wlm-loading');\n                }\n            });\n        }\n    };   // Initialize on document ready
    $(document).ready(function() {
        WLM_Frontend.init();
        
        // Move delivery time from labels on initial load
        WLM_Frontend.moveDeliveryTimeFromLabel();
    });

})(jQuery);
