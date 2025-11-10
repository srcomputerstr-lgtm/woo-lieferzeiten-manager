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
        // Register all configured shipping methods
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_methods'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_methods'));

        // Add delivery window to shipping rates
        add_action('woocommerce_after_shipping_rate', array($this, 'display_delivery_window'), 10, 2);
        
        // Add to shipping zones automatically
        add_action('woocommerce_load_shipping_methods', array($this, 'ensure_methods_in_zones'));
    }

    /**
     * Initialize all configured shipping methods
     */
    public function init_shipping_methods() {
        $configured_methods = $this->get_configured_methods();
        
        foreach ($configured_methods as $method) {
            $this->register_shipping_method_class($method);
        }
    }

    /**
     * Register a dynamic shipping method class for each configured method
     *
     * @param array $method_config Method configuration.
     */
    private function register_shipping_method_class($method_config) {
        $method_id = $method_config['id'];
        $class_name = 'WLM_Shipping_Method_' . str_replace('-', '_', $method_id);
        
        // Skip if class already exists
        if (class_exists($class_name)) {
            return;
        }
        
        // Get method title (support both 'title' and 'name' keys)
        $method_title = !empty($method_config['title']) ? $method_config['title'] : ($method_config['name'] ?? 'Versandart');
        $method_enabled = isset($method_config['enabled']) && $method_config['enabled'] ? 'yes' : 'no';

        // Create dynamic class
        eval('
        class ' . $class_name . ' extends WC_Shipping_Method {
            private $method_config;
            
            public function __construct($instance_id = 0) {
                $this->id = "' . $method_id . '";
                $this->instance_id = absint($instance_id);
                $this->method_title = "' . esc_js($method_title) . '";
                $this->method_description = __("MEGA Versandmanager Versandart", "woo-lieferzeiten-manager");
                $this->supports = array("shipping-zones", "instance-settings");
                $this->enabled = "' . $method_enabled . '";
                $this->title = "' . esc_js($method_title) . '";
                
                // Load configuration
                $methods_manager = new WLM_Shipping_Methods();
                $this->method_config = $methods_manager->get_method_by_id("' . $method_id . '");
                
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
                        "title" => __("Aktivieren/Deaktivieren", "woo-lieferzeiten-manager"),
                        "type" => "checkbox",
                        "label" => __("Diese Versandmethode aktivieren", "woo-lieferzeiten-manager"),
                        "default" => "yes"
                    ),
                    "title" => array(
                        "title" => __("Titel", "woo-lieferzeiten-manager"),
                        "type" => "text",
                        "description" => __("Titel, der dem Kunden angezeigt wird", "woo-lieferzeiten-manager"),
                        "default" => $this->method_title,
                        "desc_tip" => true
                    )
                );
            }
            
            public function calculate_shipping($package = array()) {
                if (!$this->is_available($package)) {
                    return;
                }
                
                $cost = $this->calculate_cost($package);
                
                // Calculate delivery window
                $label = $this->title;
                if (!empty($this->method_config)) {
                    $calculator = WLM_Core::instance()->calculator;
                    $window = $calculator->calculate_cart_window($this->method_config);
                    
                    if (!empty($window) && !empty($window["window_formatted"])) {
                        $label .= "<br><small style=\"font-size: 0.9em; color: #666;\">📅 " . esc_html($window["window_formatted"]) . "</small>";
                    }
                }
                
                $rate = array(
                    "id" => $this->id,
                    "label" => $label,
                    "cost" => $cost,
                    "package" => $package
                );
                
                $this->add_rate($rate);
            }
            
            private function calculate_cost($package) {
                if (empty($this->method_config)) {
                    return 0;
                }
                
                $cost = floatval($this->method_config["cost"] ?? 0);
                $cost_type = $this->method_config["cost_type"] ?? "flat";
                
                if ($cost_type === "by_weight") {
                    $total_weight = 0;
                    foreach ($package["contents"] as $item) {
                        $product = $item["data"];
                        $total_weight += $product->get_weight() * $item["quantity"];
                    }
                    $cost = $cost * $total_weight;
                } elseif ($cost_type === "by_qty") {
                    $total_qty = array_sum(wp_list_pluck($package["contents"], "quantity"));
                    $cost = $cost * $total_qty;
                }
                
                return $cost;
            }
            
            public function is_available($package) {
                if ($this->enabled !== "yes") {
                    return false;
                }
                
                if (empty($this->method_config)) {
                    return false;
                }
                
                // Check conditions
                $methods_manager = new WLM_Shipping_Methods();
                return $methods_manager->check_method_conditions($this->method_config, $package);
            }
        }
        ');
    }

    /**
     * Add all configured shipping methods to WooCommerce
     *
     * @param array $methods Existing shipping methods.
     * @return array Modified shipping methods.
     */
    public function add_shipping_methods($methods) {
        $configured_methods = $this->get_configured_methods();
        
        foreach ($configured_methods as $method) {
            $method_id = $method['id'];
            $class_name = 'WLM_Shipping_Method_' . str_replace('-', '_', $method_id);
            
            if (class_exists($class_name)) {
                $methods[$method_id] = $class_name;
            }
        }
        
        return $methods;
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

        echo '<div class="wlm-shipping-window">';
        
        if ($is_express) {
            echo '<div class="wlm-express-active">';
            echo '<span class="wlm-checkmark">✓</span> ';
            echo esc_html__('Express-Versand gewählt', 'woo-lieferzeiten-manager');
            echo ' – ' . esc_html__('Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo ' <button type="button" class="wlm-remove-express" data-method-id="' . esc_attr($method_id) . '">';
            echo esc_html__('✕ entfernen', 'woo-lieferzeiten-manager');
            echo '</button>';
            echo '</div>';
        } else {
            echo '<div class="wlm-delivery-estimate">';
            echo '🚛 ' . esc_html__('Voraussichtliche Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo '</div>';

            // Check if express is available
            if (!empty($method_config['express_enabled'])) {
                $express_available = $calculator->is_express_available();
                
                if ($express_available) {
                    $express_cost = floatval($method_config['express_cost'] ?? 0);
                    $express_window = $calculator->calculate_cart_window($method_config, true);
                    
                    echo '<div class="wlm-express-cta">';
                    echo '<button type="button" class="wlm-activate-express" data-method-id="' . esc_attr($method_id) . '">';
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
