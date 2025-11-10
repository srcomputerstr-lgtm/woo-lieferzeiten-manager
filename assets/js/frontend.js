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
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WLM_Frontend.init();
        
        // Move delivery time from labels on initial load
        WLM_Frontend.moveDeliveryTimeFromLabel();
    });

})(jQuery);
