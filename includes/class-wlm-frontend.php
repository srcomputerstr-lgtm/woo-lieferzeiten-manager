<?php
/**
 * Frontend class for product pages, cart and checkout
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Frontend {
    /**
     * Constructor
     */
    public function __construct() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Product page
        add_action('woocommerce_single_product_summary', array($this, 'display_product_delivery_info'), 25);

        // Cart
        add_action('woocommerce_after_cart_item_name', array($this, 'display_cart_item_stock_status'), 10, 2);
        add_action('woocommerce_cart_totals_before_shipping', array($this, 'display_cart_delivery_window'));

        // Checkout
        add_action('woocommerce_review_order_before_shipping', array($this, 'display_checkout_delivery_window'));

        // AJAX handlers
        add_action('wp_ajax_wlm_calc_product_window', array($this, 'ajax_calculate_product_window'));
        add_action('wp_ajax_nopriv_wlm_calc_product_window', array($this, 'ajax_calculate_product_window'));
        add_action('wp_ajax_wlm_get_shipping_delivery_info', array($this, 'ajax_get_shipping_delivery_info'));
        add_action('wp_ajax_nopriv_wlm_get_shipping_delivery_info', array($this, 'ajax_get_shipping_delivery_info'));

        // Blocks integration
        // Register Store API extension early (before blocks are loaded)
        add_action('woocommerce_init', array($this, 'register_blocks_integration'), 5);
        
        // Block-based checkout: Add delivery info after shipping options
        add_filter('woocommerce_cart_shipping_packages', array($this, 'add_delivery_info_to_shipping_packages'));
        add_filter('woocommerce_package_rates', array($this, 'add_delivery_info_to_rates'), 1000, 2);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (is_product() || is_cart() || is_checkout()) {
        wp_enqueue_style(
            'wlm-frontend',
            WLM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WLM_VERSION
        );
            


            // Enqueue simple delivery info script (no React Slot-Fills)
            if (is_checkout() || is_cart()) {
                wp_enqueue_script(
                    'wlm-blocks-delivery-info-simple',
                    WLM_PLUGIN_URL . 'assets/js/blocks-delivery-info-simple.js',
                    array('wp-data'),
                    WLM_VERSION,
                    true
                );
                
                wp_enqueue_style(
                    'wlm-blocks-simple',
                    WLM_PLUGIN_URL . 'assets/css/blocks-simple.css',
                    array(),
                    WLM_VERSION
                );
            }
            
            wp_enqueue_script(
                'wlm-frontend',
                WLM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WLM_VERSION,
                true
            );

            wp_localize_script('wlm-frontend', 'wlm_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wlm-frontend-nonce'),
                'express_nonce' => wp_create_nonce('wlm-express-nonce')
            ));
        }
    }

    /**
     * Display product delivery info on product page
     */
    public function display_product_delivery_info() {
        global $product;

        if (!$product) {
            return;
        }

        $calculator = WLM_Core::instance()->calculator;
        $product_id = $product->get_id();
        $variation_id = $product->is_type('variation') ? $product_id : 0;
        $parent_id = $variation_id > 0 ? $product->get_parent_id() : $product_id;

        $window = $calculator->calculate_product_window($parent_id, $variation_id, 1);

        if (empty($window)) {
            return;
        }

        ?>
        <div class="wlm-pdp-panel" data-product-id="<?php echo esc_attr($parent_id); ?>">
            <?php
            // Stock status
            $stock_status = $window['stock_status'];
            if ($stock_status['in_stock']) {
                echo '<div class="wlm-stock-status wlm--in-stock">';
                echo '🟢 ' . esc_html($stock_status['message']);
                echo '</div>';
            } else {
                echo '<div class="wlm-stock-status wlm--restock">';
                echo '🟡 ' . esc_html($stock_status['message']);
                echo '</div>';
            }

            // Shipping method
            if (!empty($window['shipping_method']) && !empty($window['shipping_method']['title'])) {
                $method = $window['shipping_method'];
                echo '<div class="wlm-shipping-method">';
                echo '🚚 ' . esc_html__('Versand via', 'woo-lieferzeiten-manager') . ' ';
                echo '<strong>' . esc_html($method['title']) . '</strong>';
                
                // Add info icon with tooltip
                if (!empty($method['cost_info'])) {
                    echo ' <span class="wlm-info-icon" title="' . esc_attr($method['cost_info']) . '">ℹ️</span>';
                }
                echo '</div>';
            }

            // Delivery window
            echo '<div class="wlm-delivery-window">';
            echo '📅 ' . esc_html__('Lieferung ca.:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo '</div>';
            
            // Express option
            if (!empty($window['shipping_method']) && !empty($window['shipping_method']['express_enabled']) && $stock_status['in_stock']) {
                $method = $window['shipping_method'];
                $express_window = $calculator->calculate_product_window($parent_id, $variation_id, 1, $method, true);
                if (!empty($express_window)) {
                    $express_cost = floatval($method['express_cost'] ?? 0);
                    $express_cost_text = $express_cost > 0 ? strip_tags(wc_price($express_cost)) : __('Kostenlos', 'woo-lieferzeiten-manager');
                    
                    echo '<div class="wlm-express-info">';
                    echo '⚡ ' . esc_html__('Express verfügbar:', 'woo-lieferzeiten-manager') . ' ';
                    echo '<strong>' . esc_html($express_window['window_formatted']) . '</strong>';
                    echo ' <span class="wlm-info-icon" title="' . esc_attr(sprintf(__('Express-Versand: %s', 'woo-lieferzeiten-manager'), $express_cost_text)) . '">ℹ️</span>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Display stock status for cart item
     *
     * @param array $cart_item Cart item data.
     * @param string $cart_item_key Cart item key.
     */
    public function display_cart_item_stock_status($cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $stock_status = $product->get_stock_status();

        if ($stock_status === 'instock') {
            echo '<div class="wlm-cart-stock wlm--in-stock">';
            echo '🟢 ' . esc_html__('Auf Lager', 'woo-lieferzeiten-manager');
            echo '</div>';
        } elseif ($stock_status === 'onbackorder') {
            $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);
            if ($available_from) {
                echo '<div class="wlm-cart-stock wlm--restock">';
                echo '🟡 ' . sprintf(
                    esc_html__('Wieder verfügbar ab: %s', 'woo-lieferzeiten-manager'),
                    esc_html(date_i18n('d.m.Y', strtotime($available_from)))
                );
                echo '</div>';
            }
        }
    }

    /**
     * Display delivery window in cart
     */
    public function display_cart_delivery_window() {
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window();

        if (empty($window)) {
            return;
        }

        ?>
        <tr class="wlm-cart-delivery-window">
            <td colspan="2">
                <div class="wlm-delivery-estimate">
                    <strong><?php esc_html_e('Deine Bestellung voraussichtlich:', 'woo-lieferzeiten-manager'); ?></strong>
                    <br>
                    <?php echo esc_html($window['window_formatted']); ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Display delivery window in checkout
     */
    public function display_checkout_delivery_window() {
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window();

        if (empty($window)) {
            return;
        }

        ?>
        <tr class="wlm-checkout-delivery-window">
            <td colspan="2">
                <div class="wlm-delivery-estimate">
                    <strong><?php esc_html_e('Deine Bestellung voraussichtlich:', 'woo-lieferzeiten-manager'); ?></strong>
                    <span><?php echo esc_html($window['window_formatted']); ?></span>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX handler for calculating product window
     */
    public function ajax_calculate_product_window() {
        check_ajax_referer('wlm-frontend-nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Ungültige Produkt-ID', 'woo-lieferzeiten-manager')));
        }

        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_product_window($product_id, $variation_id, $quantity);

        if (empty($window)) {
            wp_send_json_error(array('message' => __('Lieferfenster konnte nicht berechnet werden', 'woo-lieferzeiten-manager')));
        }

        wp_send_json_success($window);
    }

    /**
     * Register blocks integration - Store API Extension only
     */
    public function register_blocks_integration() {
        // Register Store API extension for delivery data
        if (function_exists('woocommerce_store_api_register_endpoint_data')) {
            require_once WLM_PLUGIN_DIR . 'includes/class-wlm-blocks-integration.php';
            
            $blocks_integration = new WLM_Blocks_Integration();
            $blocks_integration->initialize(); // Initialize filters and hooks
            
            // Register Store API extension for Cart endpoint
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
                    'namespace' => 'woo-lieferzeiten-manager',
                    'data_callback' => array($blocks_integration, 'extend_cart_data'),
                    'schema_callback' => array($blocks_integration, 'extend_cart_schema'),
                    'schema_type' => ARRAY_A
                )
            );
            
            // Register Store API extension for Checkout endpoint
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
                    'namespace' => 'woo-lieferzeiten-manager',
                    'data_callback' => array($blocks_integration, 'extend_checkout_data'),
                    'schema_callback' => array($blocks_integration, 'extend_checkout_schema'),
                    'schema_type' => ARRAY_A
                )
            );
        }
    }
    
    /**
     * Add delivery info to shipping packages for blocks
     *
     * @param array $packages Shipping packages.
     * @return array Modified packages.
     */
    public function add_delivery_info_to_shipping_packages($packages) {
        // This is called before rates are calculated
        // We'll use it to inject our shortcodes later
        return $packages;
    }
    
    /**
     * Add delivery info to shipping rates for blocks checkout
     *
     * @param array $rates Shipping rates.
     * @param array $package Shipping package.
     * @return array Modified rates.
     */
    public function add_delivery_info_to_rates($rates, $package) {
        foreach ($rates as $rate_id => $rate) {
            $method_id = $rate->get_method_id();
            
            // DO NOT modify label - keep it clean!
            // Store delivery info as meta data for JavaScript to access
            
            // Render shortcodes with method_id parameter
            $delivery_info = do_shortcode('[wlm_order_window method_id="' . esc_attr($method_id) . '"]');
            $express_info = do_shortcode('[wlm_express_toggle method_id="' . esc_attr($method_id) . '"]');
            
            // Store as meta data
            if (!empty($delivery_info) || !empty($express_info)) {
                $combined = '<div class="wlm-shipping-extras">';
                $combined .= $delivery_info;
                $combined .= $express_info;
                $combined .= '</div>';
                
                $rate->add_meta_data('wlm_delivery_info_html', $combined, true);
            }
        }
        
        return $rates;
    }
    
    /**
     * AJAX handler to get delivery info for a shipping method
     */
    public function ajax_get_shipping_delivery_info() {
        check_ajax_referer('wlm-frontend-nonce', 'nonce');
        
        $method_id = isset($_POST['method_id']) ? sanitize_text_field($_POST['method_id']) : '';
        
        if (empty($method_id)) {
            wp_send_json_error(array('message' => 'Invalid method ID'));
        }
        
        // Get method configuration
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        $method_config = $shipping_methods->get_method_by_id($method_id);
        
        if (!$method_config) {
            wp_send_json_error(array('message' => 'Method not found'));
        }
        
        // Calculate delivery window
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window($method_config, false);
        
        // Check express availability
        $express_available = false;
        $express_window = null;
        $express_cost = 0;
        
        if (!empty($method_config['express_enabled'])) {
            $cutoff_time = $method_config['express_cutoff'] ?? '12:00';
            $express_available = $calculator->is_express_available($cutoff_time);
            
            if ($express_available) {
                $express_window = $calculator->calculate_cart_window($method_config, true);
                $express_cost = floatval($method_config['express_cost'] ?? 0);
            }
        }
        
        // Check if express is currently selected
        $is_express_selected = WC()->session && WC()->session->get('wlm_express_selected') === $method_id;
        
        wp_send_json_success(array(
            'delivery_window' => $window['window_formatted'] ?? '',
            'express_available' => $express_available,
            'express_window' => $express_window['window_formatted'] ?? '',
            'express_cost' => $express_cost,
            'express_cost_formatted' => wc_price($express_cost),
            'is_express_selected' => $is_express_selected,
            'method_id' => $method_id
        ));
    }
}
