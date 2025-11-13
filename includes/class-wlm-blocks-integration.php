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
            
            // If Express is enabled, add Express method too
            error_log('[WLM Blocks] Checking Express for ' . $method_id . ': enabled=' . ($method_config['express_enabled'] ?? 'NOT SET'));
            
            if (!empty($method_config['express_enabled'])) {
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
                
                // Get stock status
                $stock_status = $calculator->get_stock_status($product_id, $variation_id);
                
                $cart_items_stock[$cart_item_key] = array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'in_stock' => $stock_status['in_stock'],
                    'available_date' => $stock_status['available_date'] ?? null,
                    'available_date_formatted' => $stock_status['available_date_formatted'] ?? null
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
