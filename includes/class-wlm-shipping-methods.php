<?php
/**
 * Shipping Methods Handler
 *
 * @package WooCommerce_Lieferzeiten_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Shipping_Methods {
    /**
     * Constructor
     */
    public function __construct() {
        // Register our shipping methods with WooCommerce
        add_filter('woocommerce_shipping_methods', array($this, 'register_shipping_methods'));
        
        // Debug: Log final rates
        add_filter('woocommerce_package_rates', array($this, 'debug_final_rates'), 999, 2);

        // Add delivery window to shipping rates
        add_action('woocommerce_after_shipping_rate', array($this, 'display_delivery_window'), 10, 2);
    }

    /**
     * Register our shipping methods with WooCommerce
     *
     * @param array $methods Existing shipping methods.
     * @return array Modified shipping methods.
     */
    public function register_shipping_methods($methods) {
        // Get configured methods
        $configured_methods = $this->get_configured_methods();
        
        // Register each configured method with a dynamic class
        foreach ($configured_methods as $method_config) {
            if (empty($method_config['enabled'])) {
                continue;
            }
            
            $method_id = $method_config['id'];
            
            // Create a unique class for this method
            $this->create_method_class($method_id, $method_config, false);
            
            // Register the class
            $class_name = 'WLM_Shipping_Method_' . str_replace('wlm_method_', '', $method_id);
            $methods[$method_id] = $class_name;
            
            // If Express is enabled, create Express class too
            if (!empty($method_config['express_enabled'])) {
                $express_method_id = $method_id . '_express';
                $this->create_method_class($express_method_id, $method_config, true);
                
                $express_class_name = 'WLM_Shipping_Method_' . str_replace('wlm_method_', '', $express_method_id);
                $methods[$express_method_id] = $express_class_name;
                
                error_log('[WLM] Registered Express method: ' . $express_method_id);
            }
        }
        
        return $methods;
    }
    
    /**
     * Create a dynamic shipping method class
     *
     * @param string $method_id Method ID.
     * @param array $method_config Method configuration.
     * @param bool $is_express Whether this is an Express method.
     */
    private function create_method_class($method_id, $method_config, $is_express = false) {
        $class_name = 'WLM_Shipping_Method_' . str_replace('wlm_method_', '', $method_id);
        
        // Skip if class already exists
        if (class_exists($class_name)) {
            return;
        }
        
        // Prepare values for the dynamic class
        $method_name = addslashes($method_config['name'] ?? 'WLM Versandart');
        if ($is_express) {
            $method_name .= ' - Express';
        }
        
        $method_title = isset($method_config['title']) ? addslashes($method_config['title']) : (isset($method_config['name']) ? addslashes($method_config['name']) : 'Versandart');
        if ($is_express) {
            $method_title .= ' - Express';
        }
        
        // Create the class dynamically
        $code = '
        class ' . $class_name . ' extends WC_Shipping_Method {
            private $wlm_method_id = "' . addslashes($method_id) . '";
            private $wlm_method_config = null;
            
            public function __construct($instance_id = 0) {
                $this->id = "' . addslashes($method_id) . '";
                $this->instance_id = absint($instance_id);
                $this->method_title = "' . $method_name . '";
                $this->method_description = "Lieferzeiten Manager Versandart";
                $this->title = "' . $method_title . '";
                $this->enabled = "yes";
                
                $this->supports = array(
                    "shipping-zones",
                    "instance-settings",
                    "instance-settings-modal",
                );
                
                $this->init();
            }
            
            public function init() {
                $this->init_form_fields();
                $this->init_settings();
                
                add_action("woocommerce_update_options_shipping_" . $this->id, array($this, "process_admin_options"));
            }
            
            public function init_form_fields() {
                $this->form_fields = array(
                    "enabled" => array(
                        "title" => __("Aktiviert", "woo-lieferzeiten-manager"),
                        "type" => "checkbox",
                        "label" => __("Diese Versandart aktivieren", "woo-lieferzeiten-manager"),
                        "default" => "yes"
                    ),
                    "title" => array(
                        "title" => __("Titel", "woo-lieferzeiten-manager"),
                        "type" => "text",
                        "description" => __("Wird dem Kunden angezeigt", "woo-lieferzeiten-manager"),
                        "default" => $this->method_title,
                        "desc_tip" => true
                    ),
                    "note" => array(
                        "title" => __("Hinweis", "woo-lieferzeiten-manager"),
                        "type" => "textarea",
                        "description" => __("Diese Versandart wird über WooCommerce → Einstellungen → Lieferzeiten Manager konfiguriert.", "woo-lieferzeiten-manager"),
                        "custom_attributes" => array("readonly" => "readonly"),
                        "default" => ""
                    )
                );
            }
            
            private function get_method_config() {
                if ($this->wlm_method_config === null) {
                    $methods_handler = WLM_Core::instance()->shipping_methods;
                    
                    // For Express methods, get base method config
                    $base_method_id = str_replace("_express", "", $this->wlm_method_id);
                    $this->wlm_method_config = $methods_handler->get_method_by_id($base_method_id);
                    
                    // If this is an Express method, modify config
                    if (strpos($this->wlm_method_id, "_express") !== false && $this->wlm_method_config) {
                        // Use Express transit times
                        $this->wlm_method_config["transit_min"] = intval($this->wlm_method_config["express_transit_min"] ?? 0);
                        $this->wlm_method_config["transit_max"] = intval($this->wlm_method_config["express_transit_max"] ?? 1);
                        $this->wlm_method_config["is_express"] = true;
                    }
                }
                return $this->wlm_method_config;
            }
            
            public function calculate_shipping($package = array()) {
                error_log("WLM: === calculate_shipping CALLED for: " . $this->wlm_method_id);
                
                $method_config = $this->get_method_config();
                
                if (!$method_config) {
                    error_log("WLM: Method config not found for ID: " . $this->wlm_method_id);
                    return;
                }
                
                error_log("WLM: Method config found. Enabled: " . ($method_config["enabled"] ? 'YES' : 'NO'));
                
                if (empty($method_config["enabled"])) {
                    error_log("WLM: Method disabled: " . $this->wlm_method_id);
                    return;
                }
                
                $methods_handler = WLM_Core::instance()->shipping_methods;
                
                if (!$methods_handler->check_method_conditions($method_config, $package)) {
                    error_log("WLM: Method conditions not met: " . $this->wlm_method_id);
                    return;
                }
                
                // Check stock availability for Express methods
                if (!empty($method_config["is_express"])) {
                    $calculator = WLM_Core::instance()->calculator;
                    $all_in_stock = true;
                    
                    // Check each item in the package
                    foreach ($package["contents"] as $item) {
                        $product = $item["data"];
                        $quantity = $item["quantity"];
                        
                        if (!$product) continue;
                        
                        // Check if sufficient stock is available
                        $stock_status = $calculator->get_stock_status($product, $quantity);
                        
                        if (!$stock_status["in_stock"]) {
                            $all_in_stock = false;
                            error_log("WLM: Express not available - Product " . $product->get_id() . " insufficient stock (requested: " . $quantity . ")");
                            break;
                        }
                    }
                    
                    // If not all items are in stock, do not offer Express
                    if (!$all_in_stock) {
                        error_log("WLM: Express method hidden due to insufficient stock: " . $this->wlm_method_id);
                        return;
                    }
                }
                
                $cost = $methods_handler->calculate_method_cost($method_config, $package);
                
                // Add Express surcharge if this is an Express method
                if (!empty($method_config["is_express"])) {
                    $express_cost = floatval($method_config["express_cost"] ?? 0);
                    $cost += $express_cost;
                    error_log("WLM: Express surcharge added: " . $express_cost);
                }
                
                $rate = array(
                    "id" => $this->get_rate_id(),
                    "label" => $this->title,
                    "cost" => $cost,
                    "package" => $package,
                );
                
                $this->add_rate($rate);
                
                error_log("WLM: Added rate for method: " . $this->wlm_method_id . " - Cost: " . $cost);
            }
        }
        ';
        
        eval($code);
    }

    /**
     * Get all configured shipping methods
     *
     * @return array Array of method configurations.
     */
    public function get_configured_methods() {
        $methods = get_option('wlm_shipping_methods', array());
        
        if (!is_array($methods)) {
            return array();
        }
        
        // Ensure each method has an ID and merge with instance settings
        foreach ($methods as $key => $method) {
            if (empty($method['id'])) {
                $methods[$key]['id'] = 'wlm_method_' . $key;
            }
            
            $method_id = $methods[$key]['id'];
            
            // Try to get instance settings from WooCommerce Shipping Zones
            $instance_settings = $this->get_method_instance_settings($method_id);
            
            if ($instance_settings) {
                // Merge instance settings into method config
                // IMPORTANT: Merge order matters! Instance settings first, then our custom fields
                // This way our custom fields (transit_min/max, etc.) override WooCommerce defaults
                $methods[$key] = array_merge($instance_settings, $methods[$key]);
            }
        }
        
        // Filter out empty methods
        $methods = array_filter($methods, function($method) {
            return !empty($method['title']) || !empty($method['name']);
        });
        
        return $methods;
    }
    
    /**
     * Get method instance settings from WooCommerce Shipping Zones
     *
     * @param string $method_id Method ID.
     * @return array|null Instance settings.
     */
    private function get_method_instance_settings($method_id) {
        global $wpdb;
        
        // Query for shipping zone method instances
        $query = $wpdb->prepare(
            "SELECT instance_id, method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s AND is_enabled = 1 LIMIT 1",
            $method_id
        );
        
        $result = $wpdb->get_row($query);
        
        if (!$result) {
            return null;
        }
        
        // Get instance settings
        $option_key = 'woocommerce_' . $method_id . '_' . $result->instance_id . '_settings';
        $settings = get_option($option_key, array());
        
        return $settings;
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
        
        // Check attribute conditions
        if (!empty($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
            // Get logic operator (AND or OR)
            $logic_operator = $method['attribute_logic'] ?? 'AND';
            
            $condition_results = array();
            
            foreach ($method['attribute_conditions'] as $condition) {
                if (empty($condition['attribute']) || empty($condition['value'])) {
                    continue;
                }
                
                $attr_name = $condition['attribute'];
                $attr_value = $condition['value'];
                $operator = $condition['operator'] ?? '=';
                
                $condition_met = false;
                
                // Check all products in package
                foreach ($package['contents'] as $item) {
                    $product = $item['data'];
                    $product_attr = $product->get_attribute($attr_name);
                    
                    if ($product_attr) {
                        // Apply operator
                        switch ($operator) {
                            case '=':
                                if (strtolower($product_attr) === strtolower($attr_value)) {
                                    $condition_met = true;
                                }
                                break;
                            case '!=':
                                if (strtolower($product_attr) !== strtolower($attr_value)) {
                                    $condition_met = true;
                                }
                                break;
                            case 'contains':
                                if (stripos($product_attr, $attr_value) !== false) {
                                    $condition_met = true;
                                }
                                break;
                        }
                    }
                    
                    if ($condition_met) {
                        break; // Found matching product
                    }
                }
                
                $condition_results[] = $condition_met;
            }
            
            // Apply logic operator
            if (!empty($condition_results)) {
                if ($logic_operator === 'OR') {
                    // At least one condition must be true
                    if (!in_array(true, $condition_results, true)) {
                        return false;
                    }
                } else {
                    // All conditions must be true (AND)
                    if (in_array(false, $condition_results, true)) {
                        return false;
                    }
                }
            }
        }
        
        // Backwards compatibility: Check old required_attributes format
        if (!empty($method['required_attributes']) && is_string($method['required_attributes'])) {
            $required_attrs = array_filter(array_map('trim', explode("\n", $method['required_attributes'])));
            
            foreach ($required_attrs as $attr_line) {
                if (strpos($attr_line, '=') === false) {
                    continue;
                }
                
                list($attr_name, $attr_value) = array_map('trim', explode('=', $attr_line, 2));
                
                $found = false;
                foreach ($package['contents'] as $item) {
                    $product = $item['data'];
                    $product_attr = $product->get_attribute($attr_name);
                    
                    if ($product_attr && strtolower($product_attr) === strtolower($attr_value)) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Calculate cost for a shipping method
     *
     * @param array $method Method configuration.
     * @param array $package Shipping package.
     * @return float Calculated cost.
     */
    public function calculate_method_cost($method, $package) {
        $base_cost = floatval($method['cost'] ?? 0);
        
        // Check if express is selected
        $is_express = WC()->session && WC()->session->get('wlm_express_selected') === $method['id'];
        
        if ($is_express && !empty($method['express_enabled'])) {
            $base_cost += floatval($method['express_cost'] ?? 0);
        }
        
        return $base_cost;
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
        
        // Extract base method ID (remove instance ID suffix)
        $base_method_id = preg_replace('/:.*$/', '', $method_id);
        
        $method_config = $this->get_method_by_id($base_method_id);
        
        if (!$method_config) {
            return;
        }
        
        // Calculate delivery window for this method
        $window = $calculator->calculate_cart_window($method_config);

        if (empty($window)) {
            return;
        }

        $is_express = WC()->session && WC()->session->get('wlm_express_selected') === $base_method_id;

        echo '<div class="wlm-shipping-window" style="margin-top: 0.5em; font-size: 0.9em; color: #666;">';
        
        if ($is_express) {
            echo '<div class="wlm-express-active" style="color: #2c3e50;">';
            echo '<span class="wlm-checkmark">✓</span> ';
            echo esc_html__('Express-Versand gewählt', 'woo-lieferzeiten-manager');
            echo ' – ' . esc_html__('Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo ' <button type="button" class="wlm-remove-express" data-method-id="' . esc_attr($base_method_id) . '" style="margin-left: 0.5em; padding: 0.2em 0.5em; font-size: 0.9em;">';
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
                    echo '<button type="button" class="wlm-activate-express" data-method-id="' . esc_attr($base_method_id) . '" style="padding: 0.4em 0.8em; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">';
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
     * Debug: Log final shipping rates
     *
     * @param array $rates Shipping rates.
     * @param array $package Shipping package.
     * @return array Shipping rates.
     */
    public function debug_final_rates($rates, $package) {
        error_log('WLM: === FINAL RATES (Priority 999) ===');
        error_log('WLM: Total rates: ' . count($rates));
        
        foreach ($rates as $rate_id => $rate) {
            error_log('WLM: Rate ID: ' . $rate_id . ' - Label: ' . $rate->get_label());
        }
        
        return $rates;
    }
}


