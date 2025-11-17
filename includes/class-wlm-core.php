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
        
        // Ensure cron job is scheduled
        add_action('init', array($this, 'ensure_cron_scheduled'));
        
        // Allow HTML in shipping method labels
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'allow_html_in_shipping_label'), 10, 2);
        
        // Ensure our methods are added to all zones
        add_action('woocommerce_shipping_init', array($this, 'ensure_methods_in_zones'));
        add_action('woocommerce_init', array($this, 'ensure_methods_in_zones'));
    }
    
    /**
     * Allow HTML in shipping method labels
     *
     * @param string $label Shipping method label.
     * @param WC_Shipping_Rate $method Shipping method.
     * @return string
     */
    public function allow_html_in_shipping_label($label, $method) {
        // Check if this is one of our methods
        $method_id = $method->get_id();
        if (strpos($method_id, 'wlm_method_') === 0) {
            // Return label as-is (with HTML)
            return $label;
        }
        return $label;
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
            
            // Register AJAX handlers
            add_action('wp_ajax_wlm_get_attribute_values', array($this->admin, 'ajax_get_attribute_values'));
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
     * Ensure cron job is scheduled
     */
    public function ensure_cron_scheduled() {
        if (!wp_next_scheduled('wlm_daily_availability_update')) {
            wp_schedule_event(time(), 'daily', 'wlm_daily_availability_update');
            error_log('[WLM] Cron job scheduled for daily availability updates');
        }
    }
    
    /**
     * Update product availability (cron job)
     * Calculates and updates the calculated availability date for all products with lead time
     */
    public function update_product_availability() {
        error_log('[WLM Cronjob] Starting product availability update...');
        
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
        $processed_count = 0;
        
        error_log('[WLM Cronjob] Found ' . count($products) . ' products with lead time');

        foreach ($products as $product) {
            $lead_time = get_post_meta($product->ID, '_wlm_lead_time_days', true);
            
            if ($lead_time && is_numeric($lead_time) && $lead_time > 0) {
                // Calculate date: Today + Lead Time (business days)
                $calculated_date = $this->calculator->calculate_available_from_date($lead_time);
                
                error_log('[WLM Cronjob] Product ID ' . $product->ID . ': Lead time=' . $lead_time . ' days, Calculated date=' . $calculated_date);
                
                // Save to calculated field (not the manual field!)
                update_post_meta($product->ID, '_wlm_calculated_available_date', $calculated_date);
                $processed_count++;
            }
        }
        
        error_log('[WLM Cronjob] Processed ' . $processed_count . ' products');
        
        // Update last run timestamp and count
        update_option('wlm_cronjob_last_run', current_time('timestamp'));
        update_option('wlm_cronjob_last_count', $processed_count);
        
        // Log for debugging
        if ($this->is_debug_mode()) {
            $this->debug_log('Cronjob executed', array(
                'processed_products' => $processed_count,
                'timestamp' => current_time('mysql')
            ));
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
     * Ensure our shipping methods are added to all zones
     */
    public function ensure_methods_in_zones() {
        // Only run once per request
        static $already_run = false;
        if ($already_run) {
            return;
        }
        $already_run = true;
        
        // Get configured methods
        $configured_methods = $this->shipping_methods->get_configured_methods();
        
        if (empty($configured_methods)) {
            return;
        }
        
        // Get all shipping zones
        $zones = WC_Shipping_Zones::get_zones();
        
        // Add "Rest of the World" zone
        $zones[] = array('id' => 0);
        
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
                if (empty($method_config['enabled'])) {
                    continue;
                }
                
                $method_id = $method_config['id'];
                
                // Skip if already in zone
                if (in_array($method_id, $existing_method_ids)) {
                    continue;
                }
                
                // Add method to zone
                $zone->add_shipping_method($method_id);
                
                error_log('WLM: Added method ' . $method_id . ' to zone ' . $zone_id);
            }
        }
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
