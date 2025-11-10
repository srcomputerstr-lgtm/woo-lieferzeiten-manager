<?php
/**
 * Shortcodes class for flexible placement in page builders
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Shortcodes {
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcodes
        add_shortcode('wlm_delivery_info', array($this, 'delivery_info_shortcode'));
        add_shortcode('wlm_stock_status', array($this, 'stock_status_shortcode'));
        add_shortcode('wlm_shipping_method', array($this, 'shipping_method_shortcode'));
        add_shortcode('wlm_delivery_window', array($this, 'delivery_window_shortcode'));
        add_shortcode('wlm_delivery_panel', array($this, 'delivery_panel_shortcode'));
        
        // Blocks checkout shortcodes
        add_shortcode('wlm_order_window', array($this, 'order_window_shortcode'));
        add_shortcode('wlm_express_toggle', array($this, 'express_toggle_shortcode'));
    }

    /**
     * Complete delivery info panel shortcode
     * 
     * Usage: [wlm_delivery_info] or [wlm_delivery_info product_id="123"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function delivery_info_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => null,
            'show' => 'all', // all, stock, shipping, delivery
        ), $atts);

        $product = $this->get_product($atts['product_id']);
        
        if (!$product) {
            return '';
        }

        $calculator = WLM_Core::instance()->calculator;
        $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;

        $window = $calculator->calculate_product_window($product_id, $variation_id, 1);

        if (empty($window)) {
            return '';
        }

        $show_parts = array_map('trim', explode(',', $atts['show']));
        $show_all = in_array('all', $show_parts);

        ob_start();
        ?>
        <div class="wlm-pdp-panel wlm-shortcode" data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php
            // Stock status
            if ($show_all || in_array('stock', $show_parts)) {
                $stock_status = $window['stock_status'];
                if ($stock_status['in_stock']) {
                    echo '<div class="wlm-stock-status wlm--in-stock">';
                    echo '🟢 ' . esc_html($stock_status['message']);
                    echo '</div>';
                } else {
                    echo '<div class="wlm-stock-status wlm--restock">';
                    echo '🟡 ' . esc_html($stock_status['message']);
                    echo '</div>';
                }
            }

            // Shipping method
            if (($show_all || in_array('shipping', $show_parts)) && !empty($window['shipping_method']) && !empty($window['shipping_method']['title'])) {
                $method = $window['shipping_method'];
                echo '<div class="wlm-shipping-method">';
                echo '🚚 ' . esc_html__('Versand via', 'woo-lieferzeiten-manager') . ' ';
                echo '<strong>' . esc_html($method['title']) . '</strong>';
                
                if (!empty($method['cost_info'])) {
                    echo ' <span class="wlm-info-icon" title="' . esc_attr($method['cost_info']) . '">ℹ️</span>';
                }
                echo '</div>';
            }

            // Delivery window
            if ($show_all || in_array('delivery', $show_parts)) {
                echo '<div class="wlm-delivery-window">';
                echo '📅 ' . esc_html__('Lieferung ca.:', 'woo-lieferzeiten-manager') . ' ';
                echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Stock status only shortcode
     * 
     * Usage: [wlm_stock_status] or [wlm_stock_status product_id="123"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function stock_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => null,
            'show_icon' => 'yes',
        ), $atts);

        $product = $this->get_product($atts['product_id']);
        
        if (!$product) {
            return '';
        }

        $calculator = WLM_Core::instance()->calculator;
        $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;

        $window = $calculator->calculate_product_window($product_id, $variation_id, 1);

        if (empty($window) || empty($window['stock_status'])) {
            return '';
        }

        $stock_status = $window['stock_status'];
        $show_icon = $atts['show_icon'] === 'yes';

        ob_start();
        
        if ($stock_status['in_stock']) {
            echo '<div class="wlm-stock-status wlm--in-stock wlm-shortcode">';
            if ($show_icon) echo '🟢 ';
            echo esc_html($stock_status['message']);
            echo '</div>';
        } else {
            echo '<div class="wlm-stock-status wlm--restock wlm-shortcode">';
            if ($show_icon) echo '🟡 ';
            echo esc_html($stock_status['message']);
            echo '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Shipping method only shortcode
     * 
     * Usage: [wlm_shipping_method] or [wlm_shipping_method product_id="123" show_icon="yes"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shipping_method_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => null,
            'show_icon' => 'yes',
            'show_info' => 'yes',
        ), $atts);

        $product = $this->get_product($atts['product_id']);
        
        if (!$product) {
            return '';
        }

        $calculator = WLM_Core::instance()->calculator;
        $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;

        $window = $calculator->calculate_product_window($product_id, $variation_id, 1);

        if (empty($window) || empty($window['shipping_method'])) {
            return '';
        }

        $method = $window['shipping_method'];
        $show_icon = $atts['show_icon'] === 'yes';
        $show_info = $atts['show_info'] === 'yes';

        if (empty($method['title'])) {
            return '';
        }
        
        ob_start();
        echo '<div class="wlm-shipping-method wlm-shortcode">';
        if ($show_icon) echo '🚚 ';
        echo esc_html__('Versand via', 'woo-lieferzeiten-manager') . ' ';
        echo '<strong>' . esc_html($method['title']) . '</strong>';
        
        if ($show_info && !empty($method['cost_info'])) {
            echo ' <span class="wlm-info-icon" title="' . esc_attr($method['cost_info']) . '">ℹ️</span>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Delivery window only shortcode
     * 
     * Usage: [wlm_delivery_window] or [wlm_delivery_window product_id="123" format="short"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function delivery_window_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => null,
            'show_icon' => 'yes',
            'show_label' => 'yes',
            'format' => 'default', // default, short, dates_only
        ), $atts);

        $product = $this->get_product($atts['product_id']);
        
        if (!$product) {
            return '';
        }

        $calculator = WLM_Core::instance()->calculator;
        $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;

        $window = $calculator->calculate_product_window($product_id, $variation_id, 1);

        if (empty($window)) {
            return '';
        }

        $show_icon = $atts['show_icon'] === 'yes';
        $show_label = $atts['show_label'] === 'yes';

        ob_start();
        echo '<div class="wlm-delivery-window wlm-shortcode">';
        
        if ($show_icon) echo '📅 ';
        
        if ($show_label) {
            echo esc_html__('Lieferung ca.:', 'woo-lieferzeiten-manager') . ' ';
        }
        
        if ($atts['format'] === 'dates_only') {
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
        } elseif ($atts['format'] === 'short') {
            echo '<strong>' . esc_html($window['earliest_formatted']) . '</strong>';
        } else {
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
        }
        
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Alias for delivery_info_shortcode (alternative name)
     * 
     * Usage: [wlm_delivery_panel]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function delivery_panel_shortcode($atts) {
        return $this->delivery_info_shortcode($atts);
    }

    /**
     * Get product object
     *
     * @param int|null $product_id Product ID.
     * @return WC_Product|null
     */
    private function get_product($product_id = null) {
        if ($product_id) {
            return wc_get_product($product_id);
        }

        global $product;
        
        if ($product && is_a($product, 'WC_Product')) {
            return $product;
        }

        // Try to get from global post
        global $post;
        if ($post && $post->post_type === 'product') {
            return wc_get_product($post->ID);
        }

        return null;
    }
    
    /**
     * Order delivery window shortcode for blocks checkout
     * 
     * Usage: [wlm_order_window]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */

    /**
     * Order window shortcode for blocks checkout
     * 
     * Usage: [wlm_order_window] or [wlm_order_window method_id="wlm_method_123"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function order_window_shortcode($atts) {
        $atts = shortcode_atts(array(
            'method_id' => null,
        ), $atts);
        
        if (!WC()->cart) {
            return '';
        }
        
        // Get method ID
        if (!empty($atts['method_id'])) {
            $base_method_id = $atts['method_id'];
        } else {
            // Fallback: Get selected shipping method
            $chosen_methods = WC()->session->get('chosen_shipping_methods');
            if (empty($chosen_methods)) {
                return '';
            }
            
            $chosen_method_id = $chosen_methods[0];
            $base_method_id = preg_replace('/:.*$/', '', $chosen_method_id);
        }
        
        // Get method configuration
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        $method_config = $shipping_methods->get_method_by_id($base_method_id);
        
        if (!$method_config) {
            return '';
        }
        
        // Calculate delivery window for this specific method
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window($method_config, false);
        
        if (empty($window)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wlm-order-window" style="margin-top: 0.5em; padding: 0.75em; background: #f7f7f7; border-radius: 4px;">
            <div class="wlm-delivery-estimate" style="font-size: 0.9em; color: #666;">
                <strong style="color: #2c3e50;"><?php echo esc_html__('Voraussichtliche Lieferung:', 'woo-lieferzeiten-manager'); ?></strong><br>
                <span style="font-size: 1.1em; color: #2c3e50;"><?php echo esc_html($window['window_formatted']); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Express toggle shortcode for blocks checkout
     * 
     * Usage: [wlm_express_toggle] or [wlm_express_toggle method_id="wlm_method_123"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function express_toggle_shortcode($atts) {
        $atts = shortcode_atts(array(
            'method_id' => null,
        ), $atts);
        
        if (!WC()->cart) {
            return '';
        }
        
        // Get method ID
        if (!empty($atts['method_id'])) {
            $base_method_id = $atts['method_id'];
        } else {
            // Fallback: Get selected shipping method
            $chosen_methods = WC()->session->get('chosen_shipping_methods');
            if (empty($chosen_methods)) {
                return '';
            }
            
            $chosen_method_id = $chosen_methods[0];
            $base_method_id = preg_replace('/:.*$/', '', $chosen_method_id);
        }
        
        // Get method configuration
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        $method_config = $shipping_methods->get_method_by_id($base_method_id);
        
        // Check if express is enabled for THIS method
        if (!$method_config || empty($method_config['express_enabled'])) {
            return ''; // No express for this method
        }
        
        $calculator = WLM_Core::instance()->calculator;
        $express_available = $calculator->is_express_available($method_config['express_cutoff'] ?? '12:00');
        
        if (!$express_available) {
            return ''; // Express not available (cutoff time passed)
        }
        
        $is_express = WC()->session && WC()->session->get('wlm_express_selected') === $base_method_id;
        $express_window = $calculator->calculate_cart_window($method_config, true);
        $express_cost = floatval($method_config['express_cost'] ?? 0);
        
        ob_start();
        ?>
        <div class="wlm-express-section" style="margin-top: 0.5em;">
            <?php if ($is_express): ?>
                <div class="wlm-express-active" style="padding: 0.75em; background: #e8f5e9; border-radius: 4px; border-left: 4px solid #4caf50;">
                    <span class="wlm-checkmark" style="color: #4caf50; font-weight: bold;">✓</span>
                    <strong style="color: #2e7d32;"><?php echo esc_html__('Express-Versand gewählt', 'woo-lieferzeiten-manager'); ?></strong><br>
                    <span style="font-size: 0.9em; color: #666;">
                        <?php echo esc_html__('Zustellung:', 'woo-lieferzeiten-manager'); ?>
                        <strong><?php echo esc_html($express_window['window_formatted']); ?></strong>
                    </span>
                    <button type="button" class="wlm-remove-express" data-method-id="<?php echo esc_attr($base_method_id); ?>" 
                            style="margin-left: 10px; padding: 0.25em 0.5em; background: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer;">
                        ✕ <?php echo esc_html__('entfernen', 'woo-lieferzeiten-manager'); ?>
                    </button>
                </div>
            <?php else: ?>
                <button type="button" class="wlm-activate-express" data-method-id="<?php echo esc_attr($base_method_id); ?>" 
                        style="width: 100%; padding: 0.75em; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: left;">
                    ⚡ <?php echo esc_html__('Express-Versand', 'woo-lieferzeiten-manager'); ?> 
                    (<?php echo wc_price($express_cost); ?>) – 
                    <?php echo esc_html__('Zustellung:', 'woo-lieferzeiten-manager'); ?> 
                    <strong><?php echo esc_html($express_window['window_formatted']); ?></strong>
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
