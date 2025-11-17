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
                
                // Check for applicable surcharge notices
                $surcharge_notices = $this->get_applicable_surcharge_notices($product);
                if (!empty($surcharge_notices)) {
                    foreach ($surcharge_notices as $notice) {
                        echo '<div class="wlm-surcharge-notice" style="font-size: 0.9em; color: #666; margin-top: 5px;">';
                        echo '⚠️ ' . esc_html($notice);
                        echo '</div>';
                    }
                }
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
        
        // Disabled - delivery info now shown via CSS in totals section
        return '';
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
        
        // Disabled - Express now available as separate shipping method in checkout
        return '';
    }
    
    /**
     * Get applicable surcharge notices for a product
     *
     * @param WC_Product $product Product object.
     * @return array Array of notice strings.
     */
    private function get_applicable_surcharge_notices($product) {
        $notices = array();
        
        if (!$product) {
            return $notices;
        }
        
        $surcharges = get_option('wlm_surcharges', array());
        
        if (empty($surcharges)) {
            return $notices;
        }
        
        foreach ($surcharges as $surcharge) {
            // Skip if disabled
            if (isset($surcharge['enabled']) && !$surcharge['enabled']) {
                continue;
            }
            
            // Skip if no notice for product page
            if (empty($surcharge['notice_product_page'])) {
                continue;
            }
            
            // Check if surcharge applies to this product
            if ($this->check_surcharge_conditions($surcharge, $product)) {
                $notices[] = $surcharge['notice_product_page'];
            }
        }
        
        return $notices;
    }
    
    /**
     * Check if surcharge conditions are met for a product
     *
     * @param array $surcharge Surcharge configuration.
     * @param WC_Product $product Product object.
     * @return bool
     */
    private function check_surcharge_conditions($surcharge, $product) {
        // Check weight conditions
        $weight = $product->get_weight();
        if ($weight) {
            if (!empty($surcharge['weight_min']) && $weight < floatval($surcharge['weight_min'])) {
                return false;
            }
            if (!empty($surcharge['weight_max']) && $weight > floatval($surcharge['weight_max'])) {
                return false;
            }
        }
        
        // Check cart value conditions (use product price as proxy)
        $price = $product->get_price();
        if ($price) {
            if (!empty($surcharge['cart_value_min']) && $price < floatval($surcharge['cart_value_min'])) {
                return false;
            }
            if (!empty($surcharge['cart_value_max']) && $price > floatval($surcharge['cart_value_max'])) {
                return false;
            }
        }
        
        // Check attribute/taxonomy/shipping class conditions
        if (!empty($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])) {
            foreach ($surcharge['attribute_conditions'] as $condition) {
                $condition_type = $condition['type'] ?? 'attribute';
                $attr_slug = $condition['attribute'] ?? '';
                $values = $condition['values'] ?? array();
                $logic = $condition['logic'] ?? 'at_least_one';
                
                if (empty($attr_slug) || empty($values)) {
                    continue;
                }
                
                // Get product values based on condition type
                $product_values = array();
                
                if ($condition_type === 'shipping_class') {
                    // Check shipping class
                    $shipping_class = $product->get_shipping_class();
                    if ($shipping_class) {
                        $product_values[] = $shipping_class;
                    }
                } elseif ($condition_type === 'taxonomy') {
                    // Check taxonomy (category, tag)
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $terms = wp_get_post_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                    if (!is_wp_error($terms)) {
                        $product_values = $terms;
                    }
                } else {
                    // Check product attribute
                    if ($product->is_type('variation')) {
                        $variation_attrs = $product->get_attributes();
                        if (isset($variation_attrs[$attr_slug])) {
                            $product_values[] = $variation_attrs[$attr_slug];
                        } else {
                            // Fallback to parent product
                            $parent_id = $product->get_parent_id();
                            if ($parent_id) {
                                $parent_product = wc_get_product($parent_id);
                                if ($parent_product) {
                                    $parent_attr = $parent_product->get_attribute($attr_slug);
                                    if ($parent_attr) {
                                        $product_values = array_map('trim', explode(',', $parent_attr));
                                    }
                                }
                            }
                        }
                    } else {
                        $product_attr = $product->get_attribute($attr_slug);
                        if ($product_attr) {
                            $product_values = array_map('trim', explode(',', $product_attr));
                        }
                    }
                }
                
                // Check logic
                $calculator = WLM_Core::instance()->calculator;
                $product_values_normalized = array_map('strtolower', array_map('trim', $product_values));
                $values_normalized = array_map('strtolower', array_map('trim', $values));
                
                // Use reflection to access private method
                $reflection = new ReflectionClass($calculator);
                $method = $reflection->getMethod('check_attribute_logic');
                $method->setAccessible(true);
                $condition_met = $method->invoke($calculator, $product_values_normalized, $values_normalized, $logic);
                
                if (!$condition_met) {
                    return false;
                }
            }
        }
        
        return true;
    }
}
