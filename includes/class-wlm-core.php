<?php
/**
 * Core class for Woo Lieferzeiten Manager
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Core {
    /**
     * Single instance of the class
     *
     * @var WLM_Core
     */
    private static $instance = null;

    /**
     * Calculator instance
     *
     * @var WLM_Calculator
     */
    public $calculator;

    /**
     * Shipping methods instance
     *
     * @var WLM_Shipping_Methods
     */
    public $shipping_methods;

    /**
     * Express instance
     *
     * @var WLM_Express
     */
    public $express;

    /**
     * Surcharges instance
     *
     * @var WLM_Surcharges
     */
    public $surcharges;

    /**
     * Product fields instance
     *
     * @var WLM_Product_Fields
     */
    public $product_fields;

    /**
     * REST API instance
     *
     * @var WLM_REST_API
     */
    public $rest_api;

    /**
     * Frontend instance
     *
     * @var WLM_Frontend
     */
    public $frontend;

    /**
     * Shortcodes instance
     *
     * @var WLM_Shortcodes
     */
    public $shortcodes;

    /**
     * Admin instance
     *
     * @var WLM_Admin
     */
    public $admin;

    /**
     * Get instance
     *
     * @return WLM_Core
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_classes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add cron job action
        add_action('wlm_daily_availability_update', array($this, 'update_product_availability'));
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        $this->calculator = new WLM_Calculator();
        $this->shipping_methods = new WLM_Shipping_Methods();
        $this->express = new WLM_Express();
        $this->surcharges = new WLM_Surcharges();
        $this->product_fields = new WLM_Product_Fields();
        $this->rest_api = new WLM_REST_API();
        $this->frontend = new WLM_Frontend();
        $this->shortcodes = new WLM_Shortcodes();
        
        if (is_admin()) {
            $this->admin = new WLM_Admin();
        }
    }

    /**
     * Get plugin settings
     *
     * @param string $key Optional. Specific setting key.
     * @return mixed
     */
    public function get_settings($key = null) {
        $settings = get_option('wlm_settings', array());
        
        if ($key !== null) {
            return isset($settings[$key]) ? $settings[$key] : null;
        }
        
        return $settings;
    }

    /**
     * Update plugin settings
     *
     * @param array $settings Settings array.
     * @return bool
     */
    public function update_settings($settings) {
        return update_option('wlm_settings', $settings);
    }

    /**
     * Update product availability (cron job)
     */
    public function update_product_availability() {
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_wlm_lead_time_days',
                    'compare' => 'EXISTS'
                )
            )
        );

        $products = get_posts($args);

        foreach ($products as $product) {
            $lead_time = get_post_meta($product->ID, '_wlm_lead_time_days', true);
            
            if ($lead_time && is_numeric($lead_time)) {
                $available_from = $this->calculator->calculate_available_from_date($lead_time);
                update_post_meta($product->ID, '_wlm_available_from', $available_from);
            }
        }
    }

    /**
     * Get shipping methods
     *
     * @return array
     */
    public function get_shipping_methods() {
        return get_option('wlm_shipping_methods', array());
    }

    /**
     * Get surcharges
     *
     * @return array
     */
    public function get_surcharges() {
        return get_option('wlm_surcharges', array());
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function is_debug_mode() {
        return (bool) $this->get_settings('debug_mode');
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message.
     * @param array $context Additional context.
     */
    public function debug_log($message, $context = array()) {
        if ($this->is_debug_mode() && current_user_can('manage_options')) {
            error_log('WLM Debug: ' . $message . ' | Context: ' . print_r($context, true));
        }
    }
}
