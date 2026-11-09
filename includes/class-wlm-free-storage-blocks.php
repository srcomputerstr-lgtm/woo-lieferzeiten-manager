<?php
/** Native Checkout inner-block integration for the optional free-storage action. */
if (!defined('ABSPATH')) {
    exit;
}

if (!interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')) {
    return;
}

class WLM_Free_Storage_Blocks implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
    public static function register() {
        add_action('woocommerce_blocks_checkout_block_registration', function($registry) {
            $registry->register(new WLM_Free_Storage_Blocks());
        });
        add_filter('render_block_woocommerce/checkout', array(__CLASS__, 'add_checkout_placeholders'), 20, 2);
    }

    public function get_name() {
        return 'wlm-free-storage';
    }

    public function initialize() {
        wp_register_script(
            'wlm-free-storage-checkout',
            WLM_PLUGIN_URL . 'assets/js/free-storage-checkout.js',
            array('wp-element', 'wp-data', 'wp-plugins', 'wc-blocks-checkout', 'wc-blocks-registry'),
            WLM_VERSION,
            true
        );
        wp_register_style('wlm-free-storage-checkout', WLM_PLUGIN_URL . 'assets/css/free-storage-checkout.css', array(), WLM_VERSION);
    }

    public function get_script_handles() {
        if (!WLM_Free_Storage::instance()->is_enabled()) {
            return array();
        }
        wp_enqueue_style('wlm-free-storage-checkout');
        return array('wlm-free-storage-checkout');
    }

    public function get_editor_script_handles() {
        return array();
    }

    public function get_script_data() {
        return array('namespace' => 'wlm-free-storage');
    }

    /** Add native frontend block placeholders only; never save or rewrite the checkout page. */
    public static function add_checkout_placeholders($content, $block) {
        if (is_admin() || !WLM_Free_Storage::instance()->is_enabled()) {
            return $content;
        }
        if (strpos($content, 'data-block-name="wlm/free-storage-selector"') === false) {
            $selector = '<div data-block-name="wlm/free-storage-selector" class="wp-block-wlm-free-storage-selector"></div>';
            $pattern = '/(<div\b[^>]*data-block-name="woocommerce\/checkout-payment-block"[^>]*>)/';
            $content = preg_replace($pattern, $selector . '$1', $content, 1);
        }
        if (strpos($content, 'data-block-name="wlm/free-storage-summary"') === false) {
            $summary = '<div data-block-name="wlm/free-storage-summary" class="wp-block-wlm-free-storage-summary"></div>';
            $pattern = '/(<div\b[^>]*data-block-name="woocommerce\/checkout-order-summary-cart-items-block"[^>]*>\s*<\/div>)/';
            $content = preg_replace($pattern, '$1' . $summary, $content, 1);
        }
        return $content;
    }
}
