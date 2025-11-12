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
            
            // Move delivery info to description div
            this.moveDeliveryInfoToDescriptionDiv();
            $(document.body).on('updated_cart_totals updated_checkout', this.moveDeliveryInfoToDescriptionDiv.bind(this));
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
        
        /***
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
        },
        
        /**
         * Load delivery info for shipping methods via AJAX
         */
        loadDeliveryInfoForShippingMethods: function() {
            var self = this;
            
            // Wait for DOM to be ready
            setTimeout(function() {
                // Find all shipping rate items in blocks checkout
                $('.wc-block-components-totals-item').each(function() {
                    var $item = $(this);
                    var $label = $item.find('.wc-block-components-totals-item__label');
                    var $description = $item.find('.wc-block-components-totals-item__description');
                    
                    if ($label.length > 0 && $description.length > 0) {
                        // Extract method ID from radio input value
                        var $radio = $item.find('input[type="radio"]');
                        if ($radio.length > 0) {
                            var rateId = $radio.val();
                            // Extract method ID (remove instance suffix)
                            var methodId = rateId.replace(/:[0-9]+$/, '');
                            
                            // Load delivery info via AJAX
                            self.fetchAndRenderDeliveryInfo(methodId, $description);
                        }
                    }
                });
                
                // Also handle classic checkout
                $('.woocommerce-shipping-methods li').each(function() {
                    var $li = $(this);
                    var $input = $li.find('input[type="radio"]');
                    
                    if ($input.length > 0) {
                        var rateId = $input.val();
                        var methodId = rateId.replace(/:[0-9]+$/, '');
                        
                        // Create container if not exists
                        var $container = $li.find('.wlm-delivery-info-container');
                        if ($container.length === 0) {
                            $container = $('<div class="wlm-delivery-info-container"></div>');
                            $li.append($container);
                        }
                        
                        self.fetchAndRenderDeliveryInfo(methodId, $container);
                    }
                });
            }, 100);
        },
        
        /**
         * Fetch delivery info via AJAX and render in target element
         */
        fetchAndRenderDeliveryInfo: function(methodId, $target) {
            // Skip if already loading
            if ($target.hasClass('wlm-loading')) {
                return;
            }
            
            $target.addClass('wlm-loading');
            
            $.ajax({
                url: wlm_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wlm_get_shipping_delivery_info',
                    nonce: wlm_params.nonce,
                    method_id: methodId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var html = '';
                        
                        // Delivery window
                        if (response.data.delivery_window) {
                            html += '<div class="wlm-order-window">';
                            html += '<div class="wlm-delivery-estimate">';
                            html += '<strong>Voraussichtliche Lieferung:</strong> ';
                            html += '<span>' + response.data.delivery_window + '</span>';
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        // Express option
                        if (response.data.express_available) {
                            html += '<div class="wlm-express-section">';
                            
                            if (response.data.is_express_selected) {
                                html += '<div class="wlm-express-active">';
                                html += '<span class="wlm-checkmark">✓</span> ';
                                html += '<strong>Express-Versand gewählt</strong><br>';
                                html += '<span>Zustellung: <strong>' + response.data.express_window + '</strong></span>';
                                html += '<button type="button" class="wlm-remove-express" data-method-id="' + methodId + '">✕ entfernen</button>';
                                html += '</div>';
                            } else {
                                html += '<button type="button" class="wlm-activate-express" data-method-id="' + methodId + '">';
                                html += '⚡ Express-Versand (' + response.data.express_cost_formatted + ') – ';
                                html += 'Zustellung: <strong>' + response.data.express_window + '</strong>';
                                html += '</button>';
                            }
                            
                            html += '</div>';
                        }
                        
                        $target.html(html);
                        $target.show();
                    }
                },
                complete: function() {
                    $target.removeClass('wlm-loading');
                }
            });
        },
        /**
         * Move delivery info from label to description div
         * This runs after DOMContentLoaded and after every AJAX update
         */
        moveDeliveryInfoToDescriptionDiv: function() {
            var self = this;
            
            console.log('[WLM] moveDeliveryInfoToDescriptionDiv() called');
            
            // Wait a bit for DOM to be fully ready (especially after AJAX)
            setTimeout(function() {
                console.log('[WLM] Searching for shipping items...');
                
                // Find all shipping items in WooCommerce Blocks checkout
                var $items = $('.wc-block-components-totals-item');
                console.log('[WLM] Found ' + $items.length + ' shipping items');
                
                $items.each(function(index) {
                    var $item = $(this);
                    var $label = $item.find('.wc-block-components-totals-item__label');
                    var $description = $item.find('.wc-block-components-totals-item__description');
                    
                    console.log('[WLM] Item ' + index + ': label=' + $label.length + ', description=' + $description.length);
                    
                    // Check if both elements exist
                    if ($label.length > 0 && $description.length > 0) {
                        // Find .wlm-shipping-extras in label
                        var $extras = $label.find('.wlm-shipping-extras');
                        
                        console.log('[WLM] Item ' + index + ': Found ' + $extras.length + ' .wlm-shipping-extras elements');
                        
                        if ($extras.length > 0) {
                            console.log('[WLM] Item ' + index + ': Moving .wlm-shipping-extras to description div');
                            
                            // Move it to description div
                            $extras.detach().appendTo($description);
                            
                            // Make description visible
                            $description.show();
                            
                            console.log('[WLM] Item ' + index + ': Successfully moved!');
                        } else {
                            console.log('[WLM] Item ' + index + ': No .wlm-shipping-extras found in label');
                            console.log('[WLM] Item ' + index + ': Label HTML:', $label.html());
                        }
                    }
                });
                
                console.log('[WLM] moveDeliveryInfoToDescriptionDiv() finished');
            }, 100);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WLM_Frontend.init();
        
        // Move delivery time from labels on initial load
        WLM_Frontend.moveDeliveryTimeFromLabel();
    });

})(jQuery);
