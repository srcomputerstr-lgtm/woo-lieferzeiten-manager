<?php
/**
 * Blocks integration for Cart and Checkout blocks
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class WLM_Blocks_Integration implements IntegrationInterface {
    /**
     * Get the name of the integration
     *
     * @return string
     */
    public function get_name() {
        return 'woo-lieferzeiten-manager';
    }

    /**
     * Initialize the integration
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
        // Store API extension is now registered directly in class-wlm-frontend.php
        
        // Filter shipping package rates to hide methods that don't meet conditions
        add_filter('woocommerce_package_rates', array($this, 'filter_package_rates'), 10, 2);
    }
    
    /**
     * Filter shipping package rates based on product conditions
     *
     * @param array $rates Available shipping rates.
     * @param array $package Shipping package.
     * @return array Filtered shipping rates.
     */
    public function filter_package_rates($rates, $package) {
        WLM_Core::log('[WLM Package Rates] ===== FILTER CALLED ===== with ' . count($rates) . ' rates');
        
        $calculator = WLM_Core::instance()->calculator;
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        
        // Get all configured methods
        $methods = $shipping_methods->get_configured_methods();
        
        foreach ($rates as $rate_id => $rate) {
            WLM_Core::log('[WLM Package Rates] === Processing rate: ' . $rate_id);
            
            // Find matching method config
            // For Express rates, strip _express suffix to find base method config
            $is_express_rate = strpos($rate_id, '_express') !== false;
            $search_id = $is_express_rate ? str_replace('_express', '', $rate_id) : $rate_id;
            
            WLM_Core::log('[WLM Package Rates] Is Express: ' . ($is_express_rate ? 'YES' : 'NO') . ' | Search ID: ' . $search_id);
            
            $method_config = null;
            foreach ($methods as $method) {
                if (isset($method['id']) && strpos($search_id, $method['id']) === 0) {
                    $method_config = $method;
                    WLM_Core::log('[WLM Package Rates] Found matching method config: ' . $method['id']);
                    break;
                }
            }
            
            if (!$method_config) {
                WLM_Core::log('[WLM Package Rates] No method config found - skipping (not our method)');
                continue; // Not our method
            }
            
            // Check if method has attribute conditions
            if (empty($method_config['attribute_conditions'])) {
                WLM_Core::log('[WLM Package Rates] No attribute conditions - skipping check');
                continue; // No conditions to check
            }
            
            WLM_Core::log('[WLM Package Rates] Checking conditions for rate: ' . $rate_id);
            WLM_Core::log('[WLM Package Rates] Conditions: ' . print_r($method_config['attribute_conditions'], true));
            
            // Check if ANY product in package meets conditions (cart-level check)
            if (!$calculator->check_cart_conditions($package, $method_config)) {
                WLM_Core::log('[WLM Package Rates] ❌ Cart does not meet conditions - REMOVING rate ' . $rate_id);
                unset($rates[$rate_id]);
            } else {
                WLM_Core::log('[WLM Package Rates] ✅ Cart meets conditions - KEEPING rate ' . $rate_id);
            }
        }
        
        // Surcharges are added as separate cart fees via add_surcharges_to_cart()
        // DO NOT add them to shipping rates to avoid double charging
        WLM_Core::log('[WLM Package Rates] Surcharges handled separately as cart fees');
        
        // Apply shipping selection strategy
        $strategy = get_option('wlm_shipping_selection_strategy', 'customer_choice');
        WLM_Core::log('[WLM Package Rates] Strategy from DB: ' . $strategy . ' | Rates count: ' . count($rates));
        
        if ($strategy !== 'customer_choice' && count($rates) > 0) {
            WLM_Core::log('[WLM Package Rates] Applying strategy: ' . $strategy . ' to ' . count($rates) . ' rates');
            
            // Separate WLM base methods from express methods (exclude non-WLM methods)
            $base_rates = array();
            $express_rates = array();
            $other_rates = array(); // Non-WLM methods (e.g., pickup_location)
            
            foreach ($rates as $rate_id => $rate) {
                WLM_Core::log('[WLM Package Rates] Processing rate: ' . $rate_id);
                
                // Check if this is a WLM method
                $is_wlm_method = false;
                foreach ($methods as $method) {
                    if (isset($method['id']) && strpos($rate_id, $method['id']) === 0) {
                        $is_wlm_method = true;
                        break;
                    }
                }
                
                if (!$is_wlm_method) {
                    // Not a WLM method - keep it but don't apply strategy
                    $other_rates[$rate_id] = $rate;
                    WLM_Core::log('[WLM Package Rates] -> Classified as OTHER (non-WLM)');
                } elseif (strpos($rate_id, '_express') !== false) {
                    $express_rates[$rate_id] = $rate;
                    WLM_Core::log('[WLM Package Rates] -> Classified as EXPRESS');
                } else {
                    $base_rates[$rate_id] = $rate;
                    WLM_Core::log('[WLM Package Rates] -> Classified as BASE');
                }
            }
            
            WLM_Core::log('[WLM Package Rates] Base rates: ' . implode(', ', array_keys($base_rates)));
            WLM_Core::log('[WLM Package Rates] Express rates: ' . implode(', ', array_keys($express_rates)));
            
            // Apply strategy to base methods only
            if (count($base_rates) > 1) {
                $selected_base = null;
                
                switch ($strategy) {
                    case 'by_priority':
                        // Find method with lowest priority number (highest priority)
                        $lowest_priority = PHP_INT_MAX;
                        foreach ($base_rates as $rate_id => $rate) {
                            // Find method config to get priority
                            foreach ($methods as $method) {
                                if (isset($method['id']) && strpos($rate_id, $method['id']) === 0) {
                                    $priority = (int)($method['priority'] ?? 10);
                                    if ($priority < $lowest_priority) {
                                        $lowest_priority = $priority;
                                        $selected_base = $rate_id;
                                    }
                                    break;
                                }
                            }
                        }
                        break;
                        
                    case 'cheapest':
                        // Find cheapest rate
                        $lowest_cost = PHP_FLOAT_MAX;
                        foreach ($base_rates as $rate_id => $rate) {
                            $cost = (float)$rate->cost;
                            if ($cost < $lowest_cost) {
                                $lowest_cost = $cost;
                                $selected_base = $rate_id;
                            }
                        }
                        break;
                        
                    case 'most_expensive':
                        // Find most expensive rate
                        $highest_cost = 0;
                        foreach ($base_rates as $rate_id => $rate) {
                            $cost = (float)$rate->cost;
                            if ($cost > $highest_cost) {
                                $highest_cost = $cost;
                                $selected_base = $rate_id;
                            }
                        }
                        break;
                }
                
                if ($selected_base) {
                    WLM_Core::log('[WLM Package Rates] Selected base method: ' . $selected_base);
                    
                    // Keep only selected base method
                    $filtered_rates = array($selected_base => $base_rates[$selected_base]);
                    
                    // Keep express variant of selected method
                    // Extract base ID without instance ID (e.g., "wlm_method_1763118746930:12" -> "wlm_method_1763118746930")
                    $base_id_parts = explode(':', $selected_base);
                    $base_id_without_instance = $base_id_parts[0];
                    
                    WLM_Core::log('[WLM Package Rates] Looking for express variant of: ' . $base_id_without_instance);
                    
                    // Find matching express rate (e.g., "wlm_method_1763118746930_express:14")
                    foreach ($express_rates as $express_id => $express_rate) {
                        if (strpos($express_id, $base_id_without_instance . '_express') === 0) {
                            $filtered_rates[$express_id] = $express_rate;
                            WLM_Core::log('[WLM Package Rates] Keeping express variant: ' . $express_id);
                            break;
                        }
                    }
                    
                    // Add back non-WLM methods (e.g., pickup_location)
                    foreach ($other_rates as $other_id => $other_rate) {
                        $filtered_rates[$other_id] = $other_rate;
                        WLM_Core::log('[WLM Package Rates] Keeping non-WLM method: ' . $other_id);
                    }
                    
                    $rates = $filtered_rates;
                }
            }
        }
        
        return $rates;
    }

    /**
     * Get script handles for this integration
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array('wlm-blocks-integration');
    }

    /**
     * Get editor script handles for this integration
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array('wlm-blocks-integration-editor');
    }

    /**
     * Get script data for this integration
     *
     * @return array
     */
    public function get_script_data() {
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window();
        $express = WLM_Core::instance()->express;
        $express_status = $express->get_express_status();

        return array(
            'delivery_window' => $window,
            'express_status' => $express_status,
            'nonce' => wp_create_nonce('wlm-express-nonce')
        );
    }

    /**
     * Register block frontend scripts
     */
    private function register_block_frontend_scripts() {
        $script_path = '/assets/js/blocks-integration.js';
        $script_url = WLM_PLUGIN_URL . 'assets/js/blocks-integration.js';
        $script_asset_path = WLM_PLUGIN_DIR . 'assets/js/blocks-integration.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => WLM_VERSION
            );

        wp_register_script(
            'wlm-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'wlm-blocks-integration',
            'woo-lieferzeiten-manager'
        );
        
        // Register cart stock status script
        $stock_script_url = WLM_PLUGIN_URL . 'assets/js/blocks-cart-stock-status.js';
        wp_register_script(
            'wlm-cart-stock-status',
            $stock_script_url,
            array('wp-data', 'wp-element', 'wp-plugins'),
            WLM_VERSION,
            true
        );
        
        // Enqueue on frontend (will only run if cart block is present)
        wp_enqueue_script('wlm-cart-stock-status');
    }

    /**
     * Register block editor scripts
     */
    private function register_block_editor_scripts() {
        $script_path = '/assets/js/blocks-integration-editor.js';
        $script_url = WLM_PLUGIN_URL . 'assets/js/blocks-integration-editor.js';
        $script_asset_path = WLM_PLUGIN_DIR . 'assets/js/blocks-integration-editor.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => WLM_VERSION
            );

        wp_register_script(
            'wlm-blocks-integration-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'wlm-blocks-integration-editor',
            'woo-lieferzeiten-manager'
        );
    }

    /**
     * Extend Store API with delivery data
     * NOTE: This is now called directly from class-wlm-frontend.php
     * to avoid double registration issues.
     */

    /**
     * Extend cart data
     *
     * @return array
     */
    public function extend_cart_data() {
        $calculator = WLM_Core::instance()->calculator;
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        $express = WLM_Core::instance()->express;
        
        // Get all configured shipping methods
        $methods = $shipping_methods->get_configured_methods();
        $delivery_info = array();
        
        // DEBUG: Log method configs
        WLM_Core::log('[WLM DEBUG] Method configs: ' . print_r($methods, true));
        
        foreach ($methods as $method_config) {
            // Get the actual method ID from config
            $method_id = $method_config['id'] ?? null;
            if (!$method_id) {
                continue;
            }
            
            // Check cart-level attribute conditions
            if (!empty($method_config['attribute_conditions'])) {
                WLM_Core::log('[WLM Blocks] Checking cart-level attribute conditions for method: ' . $method_id);
                
                // Build package from cart
                $package = array('contents' => array());
                if (WC()->cart) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $package['contents'][] = $cart_item;
                    }
                }
                
                if (!$calculator->check_cart_conditions($package, $method_config)) {
                    WLM_Core::log('[WLM Blocks] Cart does not meet conditions - skipping method ' . $method_id);
                    continue;
                }
            }
            
            // Calculate delivery window for normal method
            $window = $calculator->calculate_cart_window($method_config, false);
            
            // Add normal method delivery info
            $delivery_info[$method_id] = array(
                'method_id' => $method_id,
                'method_name' => $method_config['name'] ?? '',
                'delivery_window' => $window ? $window['window_formatted'] : null,
                'earliest_date' => $window ? date('Y-m-d', $window['earliest']) : null,
                'latest_date' => $window ? date('Y-m-d', $window['latest']) : null,
                'is_express_rate' => false
            );
            
            WLM_Core::log('[WLM Blocks] Added normal method: ' . $method_id . ' - ' . ($window ? $window['window_formatted'] : 'no window'));
            
            // If Express is enabled, check if all products are in stock
            WLM_Core::log('[WLM Blocks] Checking Express for ' . $method_id . ': enabled=' . ($method_config['express_enabled'] ?? 'NOT SET'));
            
            // Check if all cart items are in stock (with sufficient quantity)
            $all_in_stock = true;
            if (WC()->cart) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $product = $cart_item['data'];
                    if (!$product) continue;
                    
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $variation_id = $product->get_parent_id() ? $product->get_id() : 0;
                    $quantity = $cart_item['quantity'];
                    
                    // Check stock status WITH quantity to ensure sufficient stock for Express
                    $stock_status = $calculator->get_stock_status($product, $quantity);
                    
                    if (!$stock_status['in_stock']) {
                        $all_in_stock = false;
                        WLM_Core::log('[WLM Blocks] Product ' . $product_id . ' not fully in stock (requested: ' . $quantity . ') - Express not available');
                        break;
                    }
                }
            }
            
            if (!empty($method_config['express_enabled']) && $all_in_stock) {
                $express_method_id = $method_id . '_express';
                
                // Calculate express window using express transit times
                $express_config = $method_config;
                $express_config['transit_min'] = intval($method_config['express_transit_min'] ?? 0);
                $express_config['transit_max'] = intval($method_config['express_transit_max'] ?? 1);
                
                // Check cutoff time to adjust transit days
                $cutoff_time = $method_config['express_cutoff'] ?? '14:00';
                $is_after_cutoff = !$calculator->is_express_available($cutoff_time);
                
                if ($is_after_cutoff) {
                    // Add 1 day to transit times if after cutoff
                    $express_config['transit_min'] += 1;
                    $express_config['transit_max'] += 1;
                    WLM_Core::log('[WLM Blocks] After cutoff - added 1 day to transit times');
                }
                
                $express_window = $calculator->calculate_cart_window($express_config, true);
                
                $delivery_info[$express_method_id] = array(
                    'method_id' => $express_method_id,
                    'method_name' => ($method_config['name'] ?? '') . ' - Express',
                    'delivery_window' => $express_window ? $express_window['window_formatted'] : null,
                    'earliest_date' => $express_window ? date('Y-m-d', $express_window['earliest']) : null,
                    'latest_date' => $express_window ? date('Y-m-d', $express_window['latest']) : null,
                    'is_express_rate' => true
                );
                
                WLM_Core::log('[WLM Blocks] Added express method: ' . $express_method_id . ' - ' . ($express_window ? $express_window['window_formatted'] : 'no window'));
            }
        }
        
        // Get stock status for all cart items
        $cart_items_stock = array();
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                if (!$product) continue;
                
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $variation_id = $product->get_parent_id() ? $product->get_id() : 0;
                
                // Get stock status with quantity check
                $quantity = $cart_item['quantity'];
                $stock_status = $calculator->get_stock_status($product, $quantity);
                
                // Get product attributes
                $attributes = array();
                
                if ($product->is_type('variation')) {
                    // For variations: Get parent product attributes first
                    $parent_product = wc_get_product($product_id);
                    if ($parent_product) {
                        $parent_attributes = $parent_product->get_attributes();
                        foreach ($parent_attributes as $attr_slug => $attribute) {
                            if ($attribute->is_taxonomy()) {
                                $terms = wc_get_product_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                                if (!empty($terms)) {
                                    $attributes[$attr_slug] = $terms;
                                }
                            }
                        }
                    }
                    
                    // Then get variation-specific attributes (override parent if set)
                    $variation_attrs = $product->get_attributes();
                    foreach ($variation_attrs as $attr_slug => $attr_value) {
                        if (!empty($attr_value)) {
                            $attributes[$attr_slug] = is_array($attr_value) ? $attr_value : array($attr_value);
                        }
                    }
                } else {
                    // For simple products: Get all attributes
                    $product_attributes = $product->get_attributes();
                    foreach ($product_attributes as $attr_slug => $attribute) {
                        if ($attribute->is_taxonomy()) {
                            $terms = wc_get_product_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                            if (!empty($terms)) {
                                $attributes[$attr_slug] = $terms;
                            }
                        } else {
                            $options = $attribute->get_options();
                            if (!empty($options)) {
                                $attributes[$attr_slug] = $options;
                            }
                        }
                    }
                }
                
                // Get product categories
                $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
                
                $cart_items_stock[$cart_item_key] = array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'in_stock' => $stock_status['in_stock'],
                    'message' => $stock_status['message'] ?? '',
                    'available_date' => $stock_status['available_date'] ?? null,
                    'available_date_formatted' => $stock_status['available_date_formatted'] ?? null,
                    'attributes' => $attributes,
                    'categories' => $categories
                );
            }
        }
        
        return array(
            'delivery_info' => $delivery_info,
            'cart_items_stock' => $cart_items_stock
        );
    }

    /**
     * Extend cart schema
     *
     * @return array
     */
    public function extend_cart_schema() {
        return array(
            'delivery_info' => array(
                'description' => __('Lieferinformationen pro Versandart', 'woo-lieferzeiten-manager'),
                'type' => 'object',
                'readonly' => true
            ),
            'cart_items_stock' => array(
                'description' => __('Lagerstatus pro Warenkorb-Artikel', 'woo-lieferzeiten-manager'),
                'type' => 'object',
                'readonly' => true
            )
        );
    }

    /**
     * Extend checkout data
     *
     * @return array
     */
    public function extend_checkout_data() {
        return $this->extend_cart_data();
    }

    /**
     * Extend checkout schema
     *
     * @return array
     */
    public function extend_checkout_schema() {
        return $this->extend_cart_schema();
    }
}
