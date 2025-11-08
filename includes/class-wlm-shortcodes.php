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
            if (($show_all || in_array('shipping', $show_parts)) && !empty($window['shipping_method'])) {
                $method = $window['shipping_method'];
                echo '<div class="wlm-shipping-method">';
                echo '🚚 ' . esc_html__('Versand via', 'woo-lieferzeiten-manager') . ' ';
                echo '<strong>' . esc_html($method['title'] ?? __('Paketdienst', 'woo-lieferzeiten-manager')) . '</strong>';
                
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

        ob_start();
        echo '<div class="wlm-shipping-method wlm-shortcode">';
        if ($show_icon) echo '🚚 ';
        echo esc_html__('Versand via', 'woo-lieferzeiten-manager') . ' ';
        echo '<strong>' . esc_html($method['title'] ?? __('Paketdienst', 'woo-lieferzeiten-manager')) . '</strong>';
        
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
}
