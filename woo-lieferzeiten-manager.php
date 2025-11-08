<?php
/**
 * Plugin Name: Woo Lieferzeiten Manager
 * Plugin URI: https://example.com/woo-lieferzeiten-manager
 * Description: Zentrales Plugin für WooCommerce zur Verwaltung von Lieferzeiten, Versandarten, Express-Optionen und Versandzuschlägen mit Block-Layout-Unterstützung.
 * Version: 1.2.0
 * Author: Ihr Name
 * Author URI: https://example.com
 * Text Domain: woo-lieferzeiten-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WLM_VERSION', '1.2.0');
define('WLM_PLUGIN_FILE', __FILE__);
define('WLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WLM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function wlm_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wlm_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wlm_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Woo Lieferzeiten Manager benötigt WooCommerce, um zu funktionieren. Bitte installieren und aktivieren Sie WooCommerce.', 'woo-lieferzeiten-manager'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wlm_init() {
    if (!wlm_check_woocommerce()) {
        return;
    }

    // Load plugin text domain
    load_plugin_textdomain('woo-lieferzeiten-manager', false, dirname(WLM_PLUGIN_BASENAME) . '/languages');

    // Include core classes
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-core.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-calculator.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-shipping-methods.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-express.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-surcharges.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-product-fields.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-rest-api.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-frontend.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-shortcodes.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-wlm-admin.php';

    // Initialize core
    WLM_Core::instance();
}
add_action('plugins_loaded', 'wlm_init', 20);

/**
 * Activation hook
 */
function wlm_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Woo Lieferzeiten Manager benötigt WooCommerce. Bitte installieren und aktivieren Sie WooCommerce zuerst.', 'woo-lieferzeiten-manager'),
            esc_html__('Plugin-Aktivierung fehlgeschlagen', 'woo-lieferzeiten-manager'),
            array('back_link' => true)
        );
    }

    // Set default options
    $default_options = array(
        'cutoff_time' => '14:00',
        'business_days' => array(1, 2, 3, 4, 5), // Monday to Friday
        'holidays' => array(),
        'processing_min' => 1,
        'processing_max' => 2,
        'default_lead_time' => 3,
        'max_visible_stock' => 100,
        'debug_mode' => false
    );

    if (!get_option('wlm_settings')) {
        add_option('wlm_settings', $default_options);
    }

    // Schedule cron job for daily availability updates
    if (!wp_next_scheduled('wlm_daily_availability_update')) {
        wp_schedule_event(time(), 'daily', 'wlm_daily_availability_update');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wlm_activate');

/**
 * Deactivation hook
 */
function wlm_deactivate() {
    // Clear scheduled cron job
    $timestamp = wp_next_scheduled('wlm_daily_availability_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wlm_daily_availability_update');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wlm_deactivate');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Declare Cart and Checkout Blocks compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
