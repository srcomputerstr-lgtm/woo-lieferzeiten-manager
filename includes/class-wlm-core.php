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
        // Run migration on admin init
        add_action('admin_init', array($this, 'maybe_migrate_settings'));
        
        // Add cron job action
        add_action('wlm_daily_availability_update', array($this, 'update_product_availability'));
        
        // Ensure cron job is scheduled
        add_action('init', array($this, 'ensure_cron_scheduled'));
        
        // Custom endpoint for external cronjob execution
        add_action('init', array($this, 'register_cronjob_endpoint'));
        add_action('template_redirect', array($this, 'handle_cronjob_endpoint'));
        
        // Allow HTML in shipping method labels
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'allow_html_in_shipping_label'), 10, 2);
        
        // Ensure our methods are added to all zones
        add_action('woocommerce_shipping_init', array($this, 'ensure_methods_in_zones'));
        add_action('woocommerce_init', array($this, 'ensure_methods_in_zones'));
        
        // Save delivery timeframe to order meta (after order is processed)
        add_action('woocommerce_checkout_order_processed', array($this, 'save_delivery_timeframe_to_order'), 10, 3);
        
        // Display delivery timeframe in order details (admin)
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_order_delivery_timeframe'));
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
        $next_scheduled = wp_next_scheduled('wlm_daily_availability_update');
        $settings = $this->get_settings();
        $cronjob_time = isset($settings['cronjob_time']) ? $settings['cronjob_time'] : '01:00';
        
        // Calculate next run timestamp based on configured time
        list($hour, $minute) = explode(':', $cronjob_time);
        $today_run = strtotime('today ' . $cronjob_time, current_time('timestamp'));
        $tomorrow_run = strtotime('tomorrow ' . $cronjob_time, current_time('timestamp'));
        
        // If today's time has passed, schedule for tomorrow
        $scheduled_time = (current_time('timestamp') < $today_run) ? $today_run : $tomorrow_run;
        
        // If not scheduled OR scheduled time doesn't match configured time, reschedule
        if (!$next_scheduled || abs($next_scheduled - $scheduled_time) > 3600) {
            // Clear existing schedule
            if ($next_scheduled) {
                wp_unschedule_event($next_scheduled, 'wlm_daily_availability_update');
            }
            
            // Schedule new event
            wp_schedule_event($scheduled_time, 'daily', 'wlm_daily_availability_update');
            WLM_Core::log('[WLM] Cron job scheduled for ' . date('Y-m-d H:i:s', $scheduled_time) . ' (configured time: ' . $cronjob_time . ')');
        }
    }
    
    /**
     * Update product availability (cron job)
     * Calculates and updates the calculated availability date for all products with lead time
     */
    public function update_product_availability() {
        WLM_Core::log('[WLM Cronjob] Starting product availability update...');
        
        // Get ALL products (not just those with _wlm_lead_time_days)
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );

        $products = get_posts($args);
        $processed_count = 0;
        $settings = $this->get_settings();
        $default_lead_time = isset($settings['default_lead_time']) ? (int) $settings['default_lead_time'] : 0;
        
        WLM_Core::log('[WLM Cronjob] Found ' . count($products) . ' products total, default_lead_time=' . $default_lead_time . ' days');

        foreach ($products as $product) {
            // Try product-specific lead time first
            $lead_time = get_post_meta($product->ID, '_wlm_lead_time_days', true);
            
            // Fallback to default lead time if not set
            if (empty($lead_time) || !is_numeric($lead_time) || $lead_time <= 0) {
                $lead_time = $default_lead_time;
            }
            
            // Only process if there's a valid lead time (product-specific or default)
            if ($lead_time && is_numeric($lead_time) && $lead_time > 0) {
                // Calculate date: Today + Lead Time (business days)
                $calculated_date = $this->calculator->calculate_available_from_date($lead_time);
                
                WLM_Core::log('[WLM Cronjob] Product ID ' . $product->ID . ': Lead time=' . $lead_time . ' days, Calculated date=' . $calculated_date);
                
                // Save to calculated field (not the manual field!)
                update_post_meta($product->ID, '_wlm_calculated_available_date', $calculated_date);
                $processed_count++;
            }
        }
        
        WLM_Core::log('[WLM Cronjob] Processed ' . $processed_count . ' products');
        
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
                    // Base method exists, but check if Express variant needs to be added
                    if (!empty($method_config['express_enabled'])) {
                        $express_method_id = $method_id . '_express';
                        if (!in_array($express_method_id, $existing_method_ids)) {
                            $zone->add_shipping_method($express_method_id);
                            WLM_Core::log('WLM: Added EXPRESS method ' . $express_method_id . ' to zone ' . $zone_id);
                        }
                    }
                    continue;
                }
                
                // Add base method to zone
                $zone->add_shipping_method($method_id);
                WLM_Core::log('WLM: Added method ' . $method_id . ' to zone ' . $zone_id);
                
                // If Express is enabled, also add Express variant
                if (!empty($method_config['express_enabled'])) {
                    $express_method_id = $method_id . '_express';
                    $zone->add_shipping_method($express_method_id);
                    WLM_Core::log('WLM: Added EXPRESS method ' . $express_method_id . ' to zone ' . $zone_id);
                }
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
        if ($this->is_debug_mode()) {
            if (!empty($context)) {
                WLM_Core::log('[WLM Debug] ' . $message . ' | Context: ' . print_r($context, true));
            } else {
                WLM_Core::log('[WLM Debug] ' . $message);
            }
        }
    }
    
    /**
     * Static helper for debug logging (can be called from anywhere)
     *
     * @param string $message Debug message.
     * @param array $context Additional context.
     */
    public static function log($message, $context = array()) {
        $instance = self::instance();
        if ($instance && $instance->is_debug_mode()) {
            if (!empty($context)) {
                error_log('[WLM] ' . $message . ' | Context: ' . print_r($context, true));
            } else {
                error_log('[WLM] ' . $message);
            }
        }
    }

    /**
     * Register custom endpoint for cronjob execution
     */
    public function register_cronjob_endpoint() {
        add_rewrite_rule('^wlm-cronjob/?$', 'index.php?wlm_cronjob=1', 'top');
        add_rewrite_tag('%wlm_cronjob%', '([^&]+)');
    }

    /**
     * Handle cronjob endpoint request
     */
    public function handle_cronjob_endpoint() {
        if (!get_query_var('wlm_cronjob')) {
            return;
        }

        // Get secret key from settings
        $settings = $this->get_settings();
        $secret_key = isset($settings['cronjob_secret_key']) ? $settings['cronjob_secret_key'] : '';
        
        // If no secret key is set, generate one
        if (empty($secret_key)) {
            $secret_key = wp_generate_password(32, false, false);
            $settings['cronjob_secret_key'] = $secret_key;
            $this->update_settings($settings);
        }

        // Verify secret key
        $provided_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        
        if (empty($provided_key) || $provided_key !== $secret_key) {
            status_header(403);
            wp_die('Forbidden: Invalid secret key', 'WLM Cronjob', array('response' => 403));
        }

        // Execute the cronjob
        WLM_Core::log('[WLM] Cronjob triggered via custom endpoint');
        $this->update_product_availability();

        // Return success response
        status_header(200);
        header('Content-Type: application/json');
        
        $response = array(
            'success' => true,
            'message' => 'WLM Cronjob executed successfully',
            'timestamp' => current_time('mysql'),
            'processed_count' => get_option('wlm_cronjob_last_count', 0)
        );
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Save delivery timeframe to order meta
     *
     * @param int $order_id Order ID.
     * @param array $posted_data Posted data.
     * @param WC_Order $order Order object.
     */
    public function save_delivery_timeframe_to_order($order_id, $posted_data = array(), $order = null) {
        // Get order object if not provided
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            WLM_Core::log('Could not get order object for ID: ' . $order_id);
            return;
        }
        // Get chosen shipping method
        $shipping_methods = $order->get_shipping_methods();
        
        if (empty($shipping_methods)) {
            WLM_Core::log('No shipping method found for order ' . $order->get_id());
            return;
        }
        
        // Get first shipping method
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        
        WLM_Core::log('Saving delivery timeframe for order ' . $order->get_id() . ' with method: ' . $method_id);
        
        // Check if it's a WLM method
        if (strpos($method_id, 'wlm_method_') !== 0) {
            WLM_Core::log('Not a WLM method, skipping delivery timeframe save');
            return;
        }
        
        // Get cart items to calculate delivery window
        $items = $order->get_items();
        $cart_items = array();
        
        foreach ($items as $item) {
            $product = $item->get_product();
            if ($product) {
                $cart_items[] = array(
                    'product' => $product,
                    'quantity' => $item->get_quantity()
                );
            }
        }
        
        if (empty($cart_items)) {
            WLM_Core::log('No cart items found for order ' . $order->get_id());
            return;
        }
        
        // Get shipping method config
        $methods = $this->get_shipping_methods();
        $method_config = null;
        
        foreach ($methods as $method) {
            if (isset($method['id']) && $method['id'] === $method_id) {
                $method_config = $method;
                break;
            }
        }
        
        if (!$method_config) {
            WLM_Core::log('Method config not found for: ' . $method_id);
            return;
        }
        
        // Calculate delivery window using Calculator
        $window = $this->calculator->calculate_delivery_window($cart_items, $method_config);
        
        if (!$window || empty($window['earliest_date']) || empty($window['latest_date'])) {
            WLM_Core::log('Could not calculate delivery window for order ' . $order->get_id());
            return;
        }
        
        // Save to order meta
        $order->update_meta_data('_wlm_earliest_delivery', $window['earliest_date']);
        $order->update_meta_data('_wlm_latest_delivery', $window['latest_date']);
        $order->update_meta_data('_wlm_delivery_window', $window['window_formatted']);
        $order->update_meta_data('_wlm_shipping_method_name', $method_config['name'] ?? '');
        
        $order->save();
        
        WLM_Core::log('Saved delivery timeframe for order ' . $order->get_id() . ': ' . $window['earliest_date'] . ' - ' . $window['latest_date']);
    }
    
    /**
     * Display delivery timeframe in order details (admin)
     *
     * @param WC_Order $order Order object.
     */
    public function display_order_delivery_timeframe($order) {
        $earliest = $order->get_meta('_wlm_earliest_delivery');
        $latest = $order->get_meta('_wlm_latest_delivery');
        $window = $order->get_meta('_wlm_delivery_window');
        $method_name = $order->get_meta('_wlm_shipping_method_name');
        
        if (empty($earliest) || empty($latest)) {
            return;
        }
        
        // Format dates for display
        $earliest_formatted = date_i18n(get_option('date_format'), strtotime($earliest));
        $latest_formatted = date_i18n(get_option('date_format'), strtotime($latest));
        
        ?>
        <div class="wlm-order-delivery-timeframe" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Lieferzeitraum', 'woo-lieferzeiten-manager'); ?></h3>
            
            <?php if ($method_name): ?>
            <p style="margin: 5px 0;">
                <strong><?php esc_html_e('Versandart:', 'woo-lieferzeiten-manager'); ?></strong>
                <?php echo esc_html($method_name); ?>
            </p>
            <?php endif; ?>
            
            <?php if ($window): ?>
            <p style="margin: 5px 0;">
                <strong><?php esc_html_e('Lieferzeitraum:', 'woo-lieferzeiten-manager'); ?></strong>
                <?php echo esc_html($window); ?>
            </p>
            <?php endif; ?>
            
            <p style="margin: 5px 0;">
                <strong><?php esc_html_e('Früheste Zustellung:', 'woo-lieferzeiten-manager'); ?></strong>
                <?php echo esc_html($earliest_formatted); ?>
                <code style="margin-left: 10px; background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($earliest); ?></code>
            </p>
            
            <p style="margin: 5px 0;">
                <strong><?php esc_html_e('Späteste Zustellung:', 'woo-lieferzeiten-manager'); ?></strong>
                <?php echo esc_html($latest_formatted); ?>
                <code style="margin-left: 10px; background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($latest); ?></code>
            </p>
            
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                <em><?php esc_html_e('Diese Daten werden automatisch an das ERP-System übertragen.', 'woo-lieferzeiten-manager'); ?></em>
            </p>
        </div>
        <?php
    }
    
    /**
     * Migrate old settings to new format
     * Converts processing_min/max to processing_days
     */
    public function maybe_migrate_settings() {
        // Check if migration is needed
        $migrated_v128 = get_option('wlm_settings_migrated_v128', false);
        $migrated_v129 = get_option('wlm_settings_migrated_v129', false);
        
        $settings = $this->get_settings();
        $needs_save = false;
        
        // Migration v1.28: processing_min/max to processing_days
        if (!$migrated_v128 && (isset($settings['processing_min']) || isset($settings['processing_max']))) {
            // Use processing_min as the new processing_days value
            // (or average of min/max if both exist)
            if (isset($settings['processing_min']) && isset($settings['processing_max'])) {
                $processing_days = ($settings['processing_min'] + $settings['processing_max']) / 2;
            } elseif (isset($settings['processing_min'])) {
                $processing_days = $settings['processing_min'];
            } else {
                $processing_days = $settings['processing_max'];
            }
            
            // Update settings
            $settings['processing_days'] = $processing_days;
            unset($settings['processing_min']);
            unset($settings['processing_max']);
            
            $needs_save = true;
            
            WLM_Core::log('[WLM Migration v1.28] Migrated processing_min/max to processing_days: ' . $processing_days);
        }
        
        // Migration v1.29: Fix malformed array keys (business_days][], holidays][], etc.)
        if (!$migrated_v129) {
            $malformed_keys = array();
            
            foreach ($settings as $key => $value) {
                // Check if key ends with ][] (malformed)
                if (substr($key, -3) === '][]') {
                    $correct_key = substr($key, 0, -3); // Remove ][]
                    $malformed_keys[$key] = $correct_key;
                }
            }
            
            if (!empty($malformed_keys)) {
                foreach ($malformed_keys as $old_key => $new_key) {
                    // Copy value to correct key
                    $settings[$new_key] = $settings[$old_key];
                    // Remove malformed key
                    unset($settings[$old_key]);
                    
                    WLM_Core::log('[WLM Migration v1.29] Fixed malformed key: ' . $old_key . ' -> ' . $new_key);
                }
                
                $needs_save = true;
            }
        }
        
        // Save if needed
        if ($needs_save) {
            update_option('wlm_settings', $settings);
        }
        
        // Mark migrations as complete
        if (!$migrated_v128) {
            update_option('wlm_settings_migrated_v128', true);
        }
        if (!$migrated_v129) {
            update_option('wlm_settings_migrated_v129', true);
        }
    }
}
