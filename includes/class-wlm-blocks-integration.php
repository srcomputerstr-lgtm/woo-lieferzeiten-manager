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
            
            // Calculate delivery window for this method
            $window = $calculator->calculate_cart_window($method_config);
            
            // Check if express is available for this method
            $express_available = false;
            $express_cost = 0;
            $express_window = null;
            
            if (!empty($method_config['express_enabled'])) {
                $cutoff_time = $method_config['express_cutoff'] ?? '12:00';
                $express_available = $calculator->is_express_available($cutoff_time);
                
                if ($express_available) {
                    $express_cost = floatval($method_config['express_cost'] ?? 0);
                    
                    // Calculate express window
                    $express_config = $method_config;
                    $express_config['min_days'] = intval($method_config['express_min_days'] ?? 1);
                    $express_config['max_days'] = intval($method_config['express_max_days'] ?? 2);
                    $express_window = $calculator->calculate_cart_window($express_config);
                }
            }
            
            // Check if express is currently selected
            $express_status = $express->get_express_status();
            $is_express_selected = !empty($express_status['active']);
            
            $delivery_info[$method_id] = array(
                'method_id' => $method_id,
                'method_name' => $method_config['name'] ?? '',
                'delivery_window' => $window ? $window['window_formatted'] : null,
                'express_available' => $express_available,
                'express_cost' => $express_cost,
                'express_cost_formatted' => wc_price($express_cost),
                'express_window' => $express_window ? $express_window['window_formatted'] : null,
                'is_express_selected' => $is_express_selected
            );
        }
        
        return array(
            'delivery_info' => $delivery_info
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
