<?php
/**
 * Shipping methods class - Registers each configured shipping method as WooCommerce shipping method
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Shipping_Methods {
    /**
     * Constructor
     */
    public function __construct() {
        // Add shipping rates directly to packages (bypass zones)
        add_filter('woocommerce_package_rates', array($this, 'add_shipping_rates'), 10, 2);

        // Add delivery window to shipping rates
        add_action('woocommerce_after_shipping_rate', array($this, 'display_delivery_window'), 10, 2);
    }

    /**
     * Add shipping rates directly to package (bypass zones)
     *
     * @param array $rates Existing shipping rates.
     * @param array $package Shipping package.
     * @return array Modified shipping rates.
     */
    public function add_shipping_rates($rates, $package) {
        $configured_methods = $this->get_configured_methods();
        
        foreach ($configured_methods as $method) {
            // Check if method is enabled
            if (empty($method['enabled'])) {
                continue;
            }
            
            // Check conditions
            if (!$this->check_method_conditions($method, $package)) {
                continue;
            }
            
            // Calculate cost
            $cost = $this->calculate_method_cost($method, $package);
            
            // Get delivery window
            $label = $method['title'] ?? $method['name'] ?? 'Versandart';
            $calculator = WLM_Core::instance()->calculator;
            $window = $calculator->calculate_cart_window($method);
            
            if (!empty($window) && !empty($window['window_formatted'])) {
                $label .= '<br><span style="font-size: 0.9em; color: #666;">Lieferung: <strong style="color: #2c3e50;">' . esc_html($window['window_formatted']) . '</strong></span>';
            }
            
            // Create rate
            $rate = new WC_Shipping_Rate(
                $method['id'],
                $label,
                $cost,
                array(),
                $method['id']
            );
            
            // Add rate to rates array
            $rates[$method['id']] = $rate;
        }
        
        return $rates;
    }
    
    /**
     * Calculate cost for a method
     *
     * @param array $method Method configuration.
     * @param array $package Shipping package.
     * @return float Cost.
     */
    private function calculate_method_cost($method, $package) {
        $cost = floatval($method['cost'] ?? 0);
        $cost_type = $method['cost_type'] ?? 'flat';
        
        if ($cost_type === 'by_weight') {
            $total_weight = 0;
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                $total_weight += $product->get_weight() * $item['quantity'];
            }
            $cost = $cost * $total_weight;
        } elseif ($cost_type === 'by_qty') {
            $total_qty = array_sum(wp_list_pluck($package['contents'], 'quantity'));
            $cost = $cost * $total_qty;
        }
        
        return $cost;
    }



    /**
     * Get all configured shipping methods
     *
     * @return array Configured methods.
     */
    public function get_configured_methods() {
        $methods = get_option('wlm_shipping_methods', array());
        
        // Ensure methods is an array
        if (!is_array($methods)) {
            $methods = array();
        }
        
        // Add unique ID if missing
        foreach ($methods as $index => &$method) {
            if (empty($method['id'])) {
                $method['id'] = 'wlm_method_' . ($index + 1);
            }
        }
        
        // Filter out empty methods
        $methods = array_filter($methods, function($method) {
            return !empty($method['title']) || !empty($method['name']);
        });
        
        return $methods;
    }

    /**
     * Get method configuration by ID
     *
     * @param string $method_id Method ID.
     * @return array|null Method configuration.
     */
    public function get_method_by_id($method_id) {
        $methods = $this->get_configured_methods();
        
        foreach ($methods as $method) {
            if ($method['id'] === $method_id) {
                return $method;
            }
        }
        
        return null;
    }

    /**
     * Check if method conditions are met
     *
     * @param array $method Method configuration.
     * @param array $package Shipping package.
     * @return bool True if conditions are met.
     */
    public function check_method_conditions($method, $package) {
        // Check weight conditions
        if (!empty($method['weight_min']) || !empty($method['weight_max'])) {
            $total_weight = 0;
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                $weight = $product->get_weight();
                if ($weight) {
                    $total_weight += floatval($weight) * $item['quantity'];
                }
            }
            
            if (!empty($method['weight_min']) && $total_weight < floatval($method['weight_min'])) {
                return false;
            }
            
            if (!empty($method['weight_max']) && $total_weight > floatval($method['weight_max'])) {
                return false;
            }
        }
        
        // Check cart total conditions
        if (!empty($method['cart_total_min']) || !empty($method['cart_total_max'])) {
            $cart_total = 0;
            foreach ($package['contents'] as $item) {
                $cart_total += $item['line_total'];
            }
            
            if (!empty($method['cart_total_min']) && $cart_total < floatval($method['cart_total_min'])) {
                return false;
            }
            
            if (!empty($method['cart_total_max']) && $cart_total > floatval($method['cart_total_max'])) {
                return false;
            }
        }
        
        // Check required attributes
        if (!empty($method['required_attributes'])) {
            $required_attrs = array_filter(array_map('trim', explode("\n", $method['required_attributes'])));
            
            foreach ($required_attrs as $attr_line) {
                if (strpos($attr_line, '=') === false) {
                    continue;
                }
                
                list($attr_slug, $attr_value) = array_map('trim', explode('=', $attr_line, 2));
                
                $has_attribute = false;
                foreach ($package['contents'] as $item) {
                    $product = $item['data'];
                    
                    if ($product->is_type('variation')) {
                        $variation_attrs = $product->get_attributes();
                        if (isset($variation_attrs[$attr_slug]) && $variation_attrs[$attr_slug] === $attr_value) {
                            $has_attribute = true;
                            break;
                        }
                    } else {
                        $product_attr = $product->get_attribute($attr_slug);
                        if ($product_attr === $attr_value) {
                            $has_attribute = true;
                            break;
                        }
                    }
                }
                
                if (!$has_attribute) {
                    return false;
                }
            }
        }
        
        // Check required categories
        if (!empty($method['required_categories'])) {
            $required_cats = is_array($method['required_categories']) 
                ? $method['required_categories'] 
                : array_filter(array_map('trim', explode(',', $method['required_categories'])));
            
            if (!empty($required_cats)) {
                $has_category = false;
                foreach ($package['contents'] as $item) {
                    $product = $item['data'];
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                    
                    if (array_intersect($required_cats, $product_cats)) {
                        $has_category = true;
                        break;
                    }
                }
                
                if (!$has_category) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Display delivery window under shipping rate
     *
     * @param WC_Shipping_Rate $method Shipping method.
     * @param int $index Method index.
     */
    public function display_delivery_window($method, $index) {
        $calculator = WLM_Core::instance()->calculator;
        
        // Get method configuration
        $method_id = $method->get_id();
        $method_config = $this->get_method_by_id($method_id);
        
        if (!$method_config) {
            return;
        }
        
        // Calculate delivery window for this method
        $window = $calculator->calculate_cart_window($method_config);

        if (empty($window)) {
            return;
        }

        $is_express = WC()->session && WC()->session->get('wlm_express_selected') === $method_id;

        echo '<div class="wlm-shipping-window" style="margin-top: 0.5em; font-size: 0.9em; color: #666;">';
        
        if ($is_express) {
            echo '<div class="wlm-express-active" style="color: #2c3e50;">';
            echo '<span class="wlm-checkmark">✓</span> ';
            echo esc_html__('Express-Versand gewählt', 'woo-lieferzeiten-manager');
            echo ' – ' . esc_html__('Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo ' <button type="button" class="wlm-remove-express" data-method-id="' . esc_attr($method_id) . '" style="margin-left: 0.5em; padding: 0.2em 0.5em; font-size: 0.9em;">';
            echo esc_html__('✕ entfernen', 'woo-lieferzeiten-manager');
            echo '</button>';
            echo '</div>';
        } else {
            echo '<div class="wlm-delivery-estimate">';
            echo esc_html__('Lieferung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong style="color: #2c3e50;">' . esc_html($window['window_formatted']) . '</strong>';
            echo '</div>';

            // Check if express is available
            if (!empty($method_config['express_enabled'])) {
                $express_available = $calculator->is_express_available();
                
                if ($express_available) {
                    $express_cost = floatval($method_config['express_cost'] ?? 0);
                    $express_window = $calculator->calculate_cart_window($method_config, true);
                    
                    echo '<div class="wlm-express-cta" style="margin-top: 0.5em;">';
                    echo '<button type="button" class="wlm-activate-express" data-method-id="' . esc_attr($method_id) . '" style="padding: 0.4em 0.8em; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">';
                    echo esc_html__('⚡ Express-Versand', 'woo-lieferzeiten-manager') . ' ';
                    echo '(+' . wc_price($express_cost) . ') – ';
                    echo esc_html__('Zustellung:', 'woo-lieferzeiten-manager') . ' ';
                    echo '<strong>' . esc_html($express_window['window_formatted']) . '</strong>';
                    echo '</button>';
                    echo '</div>';
                }
            }
        }
        
        echo '</div>';
    }

    /**
     * Ensure methods are available in shipping zones
     */
    public function ensure_methods_in_zones() {
        // Make methods available globally (like Conditional Shipping)
        // They will appear in all zones automatically
        
        $configured_methods = $this->get_configured_methods();
        
        if (empty($configured_methods)) {
            return;
        }
        
        // Get all shipping zones
        $zones = WC_Shipping_Zones::get_zones();
        
        // Add "Rest of the World" zone
        $zones[] = array(
            'id' => 0,
            'zone_name' => __('Rest of the World', 'woocommerce')
        );
        
        foreach ($zones as $zone_data) {
            $zone_id = $zone_data['id'] ?? $zone_data['zone_id'] ?? 0;
            $zone = WC_Shipping_Zones::get_zone($zone_id);
            
            if (!$zone) {
                continue;
            }
            
            // Get existing shipping methods in this zone
            $existing_methods = $zone->get_shipping_methods();
            $existing_method_ids = array();
            
            foreach ($existing_methods as $method) {
                $existing_method_ids[] = $method->id;
            }
            
            // Add our methods if not already present
            foreach ($configured_methods as $method_config) {
                $method_id = $method_config['id'];
                
                // Skip if already in zone
                if (in_array($method_id, $existing_method_ids)) {
                    continue;
                }
                
                // Add method to zone
                $zone->add_shipping_method($method_id);
            }
        }
    }
}
