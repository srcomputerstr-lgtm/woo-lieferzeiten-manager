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
        $calculator = WLM_Core::instance()->calculator;
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        
        // Get all configured methods
        $methods = $shipping_methods->get_configured_methods();
        
        foreach ($rates as $rate_id => $rate) {
            // Find matching method config
            $method_config = null;
            foreach ($methods as $method) {
                if (isset($method['id']) && strpos($rate_id, $method['id']) === 0) {
                    $method_config = $method;
                    break;
                }
            }
            
            if (!$method_config) {
                continue; // Not our method
            }
            
            // Check if method has attribute conditions
            if (empty($method_config['attribute_conditions'])) {
                continue; // No conditions to check
            }
            
            error_log('[WLM Package Rates] Checking conditions for rate: ' . $rate_id);
            
            // Check each product in package
            $should_show = true;
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                if (!$product) continue;
                
                if (!$calculator->check_product_conditions($product, $method_config)) {
                    error_log('[WLM Package Rates] Product ' . $product->get_name() . ' does not meet conditions - removing rate ' . $rate_id);
                    $should_show = false;
                    break;
                }
            }
            
            // Remove rate if conditions not met
            if (!$should_show) {
                unset($rates[$rate_id]);
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
        error_log('[WLM DEBUG] Method configs: ' . print_r($methods, true));
        
        foreach ($methods as $method_config) {
            // Get the actual method ID from config
            $method_id = $method_config['id'] ?? null;
            if (!$method_id) {
                continue;
            }
            
            // Check product attribute conditions
            $should_show_method = true;
            if (!empty($method_config['attribute_conditions'])) {
                error_log('[WLM Blocks] Checking attribute conditions for method: ' . $method_id);
                error_log('[WLM Blocks] Conditions: ' . print_r($method_config['attribute_conditions'], true));
                
                // Check each product in cart
                if (WC()->cart) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $product = $cart_item['data'];
                        if (!$product) continue;
                        
                        // Check if product meets conditions
                        if (!$calculator->check_product_conditions($product, $method_config)) {
                            error_log('[WLM Blocks] Product ' . $product->get_name() . ' does not meet conditions - hiding method ' . $method_id);
                            $should_show_method = false;
                            break; // Stop checking, method is hidden
                        }
                    }
                }
            }
            
            // Skip this method if conditions not met
            if (!$should_show_method) {
                error_log('[WLM Blocks] Skipping method ' . $method_id . ' due to unmet conditions');
                continue;
            }
            
            // Calculate delivery window for normal method
            $window = $calculator->calculate_cart_window($method_config, false);
            
            // Add normal method delivery info
            $delivery_info[$method_id] = array(
                'method_id' => $method_id,
                'method_name' => $method_config['name'] ?? '',
                'delivery_window' => $window ? $window['window_formatted'] : null,
                'is_express_rate' => false
            );
            
            error_log('[WLM Blocks] Added normal method: ' . $method_id . ' - ' . ($window ? $window['window_formatted'] : 'no window'));
            
            // If Express is enabled, check if all products are in stock
            error_log('[WLM Blocks] Checking Express for ' . $method_id . ': enabled=' . ($method_config['express_enabled'] ?? 'NOT SET'));
            
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
                        error_log('[WLM Blocks] Product ' . $product_id . ' not fully in stock (requested: ' . $quantity . ') - Express not available');
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
                    error_log('[WLM Blocks] After cutoff - added 1 day to transit times');
                }
                
                $express_window = $calculator->calculate_cart_window($express_config, true);
                
                $delivery_info[$express_method_id] = array(
                    'method_id' => $express_method_id,
                    'method_name' => ($method_config['name'] ?? '') . ' - Express',
                    'delivery_window' => $express_window ? $express_window['window_formatted'] : null,
                    'is_express_rate' => true
                );
                
                error_log('[WLM Blocks] Added express method: ' . $express_method_id . ' - ' . ($express_window ? $express_window['window_formatted'] : 'no window'));
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
