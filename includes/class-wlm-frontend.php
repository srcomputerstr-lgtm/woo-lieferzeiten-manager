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
        
        // Custom checkout fields for order meta
        add_action('woocommerce_after_checkout_billing_form', array($this, 'add_delivery_timeframe_hidden_fields'));
        add_action('woocommerce_blocks_checkout_block_registration', array($this, 'add_delivery_timeframe_hidden_fields_blocks'));
        add_action('wp_footer', array($this, 'add_delivery_timeframe_hidden_fields_footer'));
        add_filter('woocommerce_checkout_fields', array($this, 'register_delivery_timeframe_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_timeframe_fields'));
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_delivery_timeframe_fields_blocks'));

        // AJAX handlers
        add_action('wp_ajax_wlm_calc_product_window', array($this, 'ajax_calculate_product_window'));
        add_action('wp_ajax_nopriv_wlm_calc_product_window', array($this, 'ajax_calculate_product_window'));
        add_action('wp_ajax_wlm_get_shipping_delivery_info', array($this, 'ajax_get_shipping_delivery_info'));
        add_action('wp_ajax_nopriv_wlm_get_shipping_delivery_info', array($this, 'ajax_get_shipping_delivery_info'));
        add_action('wp_ajax_wlm_save_delivery_to_session', array($this, 'ajax_save_delivery_to_session'));
        add_action('wp_ajax_nopriv_wlm_save_delivery_to_session', array($this, 'ajax_save_delivery_to_session'));
        
        // Thank-You page (priority 5 to display at top)
        add_action('woocommerce_thankyou', array($this, 'display_and_save_delivery_timeframe_on_thankyou'), 5, 1);

        // Blocks integration
        // Register Store API extension early (before blocks are loaded)
        add_action('woocommerce_init', array($this, 'register_blocks_integration'), 5);
        
        // Order status change - register after WooCommerce init
        add_action('woocommerce_init', array($this, 'register_status_change_hook'), 10);
        
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

            $settings = WLM_Core::instance()->get_settings();
            $debug_mode = isset($settings['debug_mode']) ? (bool) $settings['debug_mode'] : false;
            
            wp_localize_script('wlm-frontend', 'wlm_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wlm-frontend-nonce'),
                'express_nonce' => wp_create_nonce('wlm-express-nonce'),
                'debug' => $debug_mode
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
                    $express_cost_gross = WLM_Core::get_shipping_price_with_tax($express_cost);
                    $express_cost_text = $express_cost > 0 ? strip_tags(wc_price($express_cost_gross)) : __('Kostenlos', 'woo-lieferzeiten-manager');
                    
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
            'earliest_date' => $window['earliest_date'] ?? '',
            'latest_date' => $window['latest_date'] ?? '',
            'express_available' => $express_available,
            'express_window' => $express_window['window_formatted'] ?? '',
            'express_earliest_date' => $express_window['earliest_date'] ?? '',
            'express_latest_date' => $express_window['latest_date'] ?? '',
            'express_cost' => $express_cost,
            'express_cost_formatted' => wc_price(WLM_Core::get_shipping_price_with_tax($express_cost)),
            'is_express_selected' => $is_express_selected,
            'method_id' => $method_id,
            'method_name' => $method_config['name'] ?? ''
        ));
    }
    
    /**
     * Register delivery timeframe fields for checkout
     *
     * @param array $fields Checkout fields.
     * @return array
     */
    public function register_delivery_timeframe_fields($fields) {
        $fields['order']['wlm_earliest_delivery'] = array(
            'type' => 'text',
            'label' => __('Earliest Delivery', 'woo-lieferzeiten-manager'),
            'required' => false,
            'class' => array('form-row-hidden'),
            'clear' => true
        );
        
        $fields['order']['wlm_latest_delivery'] = array(
            'type' => 'text',
            'label' => __('Latest Delivery', 'woo-lieferzeiten-manager'),
            'required' => false,
            'class' => array('form-row-hidden'),
            'clear' => true
        );
        
        $fields['order']['wlm_delivery_window'] = array(
            'type' => 'text',
            'label' => __('Delivery Window', 'woo-lieferzeiten-manager'),
            'required' => false,
            'class' => array('form-row-hidden'),
            'clear' => true
        );
        
        $fields['order']['wlm_shipping_method_name'] = array(
            'type' => 'text',
            'label' => __('Shipping Method Name', 'woo-lieferzeiten-manager'),
            'required' => false,
            'class' => array('form-row-hidden'),
            'clear' => true
        );
        
        return $fields;
    }
    
    /**
     * Add hidden fields for delivery timeframe
     */
    public function add_delivery_timeframe_hidden_fields() {
        ?>
        <div class="wlm-delivery-timeframe-fields" style="display: none;">
            <input type="hidden" name="wlm_earliest_delivery" id="wlm_earliest_delivery" value="" />
            <input type="hidden" name="wlm_latest_delivery" id="wlm_latest_delivery" value="" />
            <input type="hidden" name="wlm_delivery_window" id="wlm_delivery_window" value="" />
            <input type="hidden" name="wlm_shipping_method_name" id="wlm_shipping_method_name" value="" />
        </div>
        <?php
    }
    
    /**
     * Save delivery timeframe fields to order meta
     *
     * @param int $order_id Order ID.
     */
    public function save_delivery_timeframe_fields($order_id) {
        if (!empty($_POST['wlm_earliest_delivery'])) {
            update_post_meta($order_id, '_wlm_earliest_delivery', sanitize_text_field($_POST['wlm_earliest_delivery']));
        }
        
        if (!empty($_POST['wlm_latest_delivery'])) {
            update_post_meta($order_id, '_wlm_latest_delivery', sanitize_text_field($_POST['wlm_latest_delivery']));
        }
        
        if (!empty($_POST['wlm_delivery_window'])) {
            update_post_meta($order_id, '_wlm_delivery_window', sanitize_text_field($_POST['wlm_delivery_window']));
        }
        
        if (!empty($_POST['wlm_shipping_method_name'])) {
            update_post_meta($order_id, '_wlm_shipping_method_name', sanitize_text_field($_POST['wlm_shipping_method_name']));
        }
        
        WLM_Core::log('Saved delivery timeframe for order ' . $order_id . ': ' . $_POST['wlm_earliest_delivery'] . ' - ' . $_POST['wlm_latest_delivery']);
    }
    
    /**
     * Add hidden fields for delivery timeframe in footer (for Block-Checkout)
     */
    public function add_delivery_timeframe_hidden_fields_footer() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <script>
        (function() {
            // Wait for DOM to be ready
            function addHiddenFields() {
                // Check if we're on block checkout
                const blockForm = document.querySelector('form.wc-block-checkout__form');
                if (!blockForm) {
                    return;
                }
                
                // Check if fields already exist
                if (document.getElementById('wlm_earliest_delivery')) {
                    return;
                }
                
                // Create hidden fields
                const fields = [
                    {name: 'wlm_earliest_delivery', id: 'wlm_earliest_delivery'},
                    {name: 'wlm_latest_delivery', id: 'wlm_latest_delivery'},
                    {name: 'wlm_delivery_window', id: 'wlm_delivery_window'},
                    {name: 'wlm_shipping_method_name', id: 'wlm_shipping_method_name'}
                ];
                
                fields.forEach(function(fieldConfig) {
                    const field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = fieldConfig.name;
                    field.id = fieldConfig.id;
                    field.value = '';
                    blockForm.appendChild(field);
                });
                
                (window.wlm_params?.debug) && console.log('[WLM] Hidden fields added to block checkout form');
            }
            
            // Try immediately
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(addHiddenFields, 500);
                });
            } else {
                setTimeout(addHiddenFields, 500);
            }
            
            // Also try when cart updates
            if (window.wp && window.wp.data) {
                window.wp.data.subscribe(function() {
                    addHiddenFields();
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Add hidden fields for Block-Checkout registration
     */
    public function add_delivery_timeframe_hidden_fields_blocks() {
        // This hook is for block registration, not for adding fields
        // Fields are added via wp_footer instead
    }
    
    /**
     * Save delivery timeframe fields for Block-Checkout
     *
     * @param \WC_Order $order Order object.
     */
    public function save_delivery_timeframe_fields_blocks($order) {
        $order_id = $order->get_id();
        
        WLM_Core::log('save_delivery_timeframe_fields_blocks called for order ' . $order_id);
        
        // Get shipping methods from order
        $shipping_methods = $order->get_shipping_methods();
        
        if (empty($shipping_methods)) {
            WLM_Core::log('No shipping methods found for order ' . $order_id);
            return;
        }
        
        // Get first shipping method
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        $instance_id = $shipping_method->get_instance_id();
        $full_method_id = $method_id . ':' . $instance_id;
        
        WLM_Core::log('Shipping method for order ' . $order_id . ': ' . $full_method_id);
        
        // Check if this is a WLM method
        if (strpos($method_id, 'wlm_method_') !== 0) {
            WLM_Core::log('Not a WLM method, skipping: ' . $method_id);
            return;
        }
        
        // Check if this is an express method
        $is_express = false;
        $base_method_id = $method_id;
        
        if (strpos($method_id, '_express') !== false) {
            $is_express = true;
            $base_method_id = str_replace('_express', '', $method_id);
            WLM_Core::log('Express method detected, base method: ' . $base_method_id);
        }
        
        // Get method configuration
        $shipping_methods_config = WLM_Core::instance()->get_shipping_methods();
        $method_config = null;
        
        WLM_Core::log('Looking for method config: ' . $base_method_id . ' in ' . count($shipping_methods_config) . ' methods');
        
        foreach ($shipping_methods_config as $config) {
            if (isset($config['id']) && $config['id'] == $base_method_id) {
                $method_config = $config;
                break;
            }
        }
        
        if (!$method_config) {
            WLM_Core::log('Method config not found for: ' . $base_method_id);
            return;
        }
        
        // If express, adjust config
        if ($is_express) {
            $method_config['transit_min'] = intval($method_config['express_transit_min'] ?? 0);
            $method_config['transit_max'] = intval($method_config['express_transit_max'] ?? 1);
            WLM_Core::log('Using express transit times: ' . $method_config['transit_min'] . '-' . $method_config['transit_max']);
        }
        
        // Calculate delivery window based on order items
        $calculator = new WLM_Calculator();
        
        // Get all order items and calculate window
        $items = $order->get_items();
        $earliest = null;
        $latest = null;
        $ship_by = null;
        
        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $quantity = $item->get_quantity();
            $item_window = $calculator->calculate_product_window($product, $quantity, $method_config);
            
            if (!$item_window) continue;
            
            if ($earliest === null || $item_window['earliest'] < $earliest) {
                $earliest = $item_window['earliest'];
            }
            
            if ($latest === null || $item_window['latest'] > $latest) {
                $latest = $item_window['latest'];
            }
            
            // Track earliest ship-by date (most urgent)
            if ($ship_by === null || $item_window['ship_by_date'] < $ship_by) {
                $ship_by = $item_window['ship_by_date'];
            }
        }
        
        if ($earliest === null || $latest === null) {
            WLM_Core::log('Could not calculate window for order ' . $order_id);
            return;
        }
        
        // Format date range manually (format_date_range is private)
        $window = array(
            'earliest' => $earliest,
            'latest' => $latest,
            'window_formatted' => date('D, d.m.', $earliest) . ' - ' . date('D, d.m.', $latest)
        );
        
        if (!$window) {
            WLM_Core::log('Could not calculate window for order ' . $order_id);
            return;
        }
        
        // Save to order meta
        $earliest_date = date('Y-m-d', $window['earliest']);
        $latest_date = date('Y-m-d', $window['latest']);
        $delivery_window = $window['window_formatted'];
        $method_name = $method_config['name'] ?? '';
        
        if ($is_express) {
            $method_name .= ' - Express';
        }
        
        $ship_by_date = $ship_by ? date('Y-m-d', $ship_by) : '';
        
        $order->update_meta_data('_wlm_earliest_delivery', $earliest_date);
        $order->update_meta_data('_wlm_latest_delivery', $latest_date);
        $order->update_meta_data('_wlm_ship_by_date', $ship_by_date);
        $order->update_meta_data('_wlm_delivery_window', $delivery_window);
        $order->update_meta_data('_wlm_shipping_method_name', $method_name);
        
        $order->save();
        
        WLM_Core::log('Saved delivery timeframe for order (blocks) ' . $order_id . ': earliest=' . $earliest_date . ', latest=' . $latest_date . ', ship_by=' . $ship_by_date);
    }
    
    /**
     * AJAX handler to save delivery timeframe to session
     */
    public function ajax_save_delivery_to_session() {
        check_ajax_referer('wlm-frontend-nonce', 'nonce');
        
        $earliest = sanitize_text_field($_POST['earliest'] ?? '');
        $latest = sanitize_text_field($_POST['latest'] ?? '');
        $ship_by = sanitize_text_field($_POST['ship_by'] ?? '');
        $window = sanitize_text_field($_POST['window'] ?? '');
        $method_name = sanitize_text_field($_POST['method_name'] ?? '');
        
        if (empty($earliest) || empty($latest)) {
            wp_send_json_error('Missing required fields');
            return;
        }
        
        // Save to WooCommerce session
        if (WC()->session) {
            WC()->session->set('wlm_delivery_timeframe', array(
                'earliest' => $earliest,
                'latest' => $latest,
                'ship_by' => $ship_by,
                'window' => $window,
                'method_name' => $method_name
            ));
            
            WLM_Core::log('Saved delivery timeframe to session: ' . $earliest . ' - ' . $latest);
            wp_send_json_success('Saved to session');
        } else {
            WLM_Core::log('WC Session not available');
            wp_send_json_error('Session not available');
        }
    }
    
    /**
     * Display and save delivery timeframe on thank-you page
     *
     * @param int $order_id Order ID.
     */
    public function display_and_save_delivery_timeframe_on_thankyou($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get from session
        $delivery_data = WC()->session ? WC()->session->get('wlm_delivery_timeframe') : null;
        
        if (!$delivery_data || empty($delivery_data['earliest']) || empty($delivery_data['latest'])) {
            WLM_Core::log('No delivery timeframe in session for order ' . $order_id);
            return;
        }
        
        WLM_Core::log('Retrieved delivery timeframe from session for order ' . $order_id . ': ' . $delivery_data['earliest'] . ' - ' . $delivery_data['latest']);
        
        // Calculate ship_by_date from order date + processing time
        // Ship-by date = order date + processing days (internal handling time)
        // Use ship_by from session if available (calculated correctly in calculator)
        if (!empty($delivery_data['ship_by'])) {
            $ship_by_date = $delivery_data['ship_by'];
        } else {
            // Fallback: Calculate from order date
            $order_date = $order->get_date_created()->getTimestamp();
            // Get processing days from settings (default 15)
            $processing_days = (float) get_option('wlm_processing_days', 1);
            $ship_by_timestamp = strtotime('+' . $processing_days . ' days', $order_date);
            $ship_by_date = date('Y-m-d', $ship_by_timestamp);
        }
        
        WLM_Core::log('Calculated ship_by_date for order ' . $order_id . ': ' . $ship_by_date . ' (earliest: ' . $delivery_data['earliest'] . ')');
        
        // Check order status for pending handling
        $order_status = $order->get_status();
        $is_pending = ($order_status === 'pending' || $order_status === 'on-hold');
        
        // Save to order meta (always save ship_by_date, even for pending orders)
        $order->update_meta_data('_wlm_earliest_delivery', $delivery_data['earliest']);
        $order->update_meta_data('_wlm_latest_delivery', $delivery_data['latest']);
        $order->update_meta_data('_wlm_ship_by_date', $ship_by_date);
        $order->update_meta_data('_wlm_delivery_window', $delivery_data['window']);
        $order->update_meta_data('_wlm_shipping_method_name', $delivery_data['method_name']);
        
        // Mark if dates are pending (will be recalculated after payment)
        $order->update_meta_data('_wlm_is_pending_payment', $is_pending ? 'yes' : 'no');
        $order->save();
        
        WLM_Core::log('Saved delivery timeframe to order meta: ' . $order_id);
        
        // Display on thank-you page with tracking timeline style
        $order_date = $order->get_date_created() ? $order->get_date_created()->date_i18n('d.m.Y') : date('d.m.Y');
        
        echo '<section class="wlm-thankyou-delivery-timeline" style="margin: 30px 0; padding: 30px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
        
        // Package icon and shipping method
        echo '<div style="margin-bottom: 20px;">';
        echo '<div style="font-size: 48px; margin-bottom: 10px;">📦</div>';
        echo '<p style="margin: 0; color: #666; font-size: 14px;">' . esc_html__('Versandart:', 'woo-lieferzeiten-manager') . ' ' . esc_html($delivery_data['method_name']) . '</p>';
        echo '</div>';
        
        // Timeline
        echo '<div class="wlm-timeline" style="display: flex; justify-content: space-between; align-items: flex-start; max-width: 900px; margin: 0 auto; position: relative;">';
        
        // Progress line
        echo '<div style="position: absolute; top: 20px; left: 10%; right: 10%; height: 2px; background: #ddd; z-index: 0;"></div>';
        echo '<div style="position: absolute; top: 20px; left: 10%; width: 20%; height: 2px; background: #ffa500; z-index: 1;"></div>';
        
        // Step 1: Bestellung erhalten (active)
        echo '<div style="flex: 1; position: relative; z-index: 2;">';
        echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #ffa500; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold; box-shadow: 0 2px 8px rgba(255,165,0,0.3);">1</div>';
        echo '<div style="font-weight: 600; margin-bottom: 5px;">' . esc_html__('Bestellung erhalten', 'woo-lieferzeiten-manager') . '</div>';
        echo '<div style="font-size: 12px; color: #666;">' . esc_html($order_date) . '</div>';
        echo '</div>';
        
        // Step 2: In Bearbeitung
        echo '<div style="flex: 1; position: relative; z-index: 2;">';
        echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">2</div>';
        echo '<div style="font-weight: 600; color: #666;">' . esc_html__('In Bearbeitung', 'woo-lieferzeiten-manager') . '</div>';
        echo '</div>';
        
        // Step 3: Verpackt
        echo '<div style="flex: 1; position: relative; z-index: 2;">';
        echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">3</div>';
        echo '<div style="font-weight: 600; color: #666;">' . esc_html__('Verpackt', 'woo-lieferzeiten-manager') . '</div>';
        echo '</div>';
        
        // Step 4: Abgeholt
        echo '<div style="flex: 1; position: relative; z-index: 2;">';
        echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">4</div>';
        echo '<div style="font-weight: 600; color: #666;">' . esc_html__('Abgeholt', 'woo-lieferzeiten-manager') . '</div>';
        echo '</div>';
        
        // Step 5: Voraussichtliche Zustellung (highlighted)
        echo '<div style="flex: 1; position: relative; z-index: 2;">';
        echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #28a745; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold; box-shadow: 0 2px 8px rgba(40,167,69,0.3);">5</div>';
        echo '<div style="font-weight: 600; color: #28a745;">' . esc_html__('Voraussichtliche Zustellung', 'woo-lieferzeiten-manager') . '</div>';
        echo '<div style="font-size: 14px; color: #28a745; font-weight: 600; margin-top: 5px;">' . esc_html($delivery_data['window']) . '</div>';
        echo '</div>';
        
        echo '</div>'; // end timeline
        echo '</section>';
        
        // Cleanup session
        if (WC()->session) {
            WC()->session->__unset('wlm_delivery_timeframe');
            WLM_Core::log('Cleaned up delivery timeframe from session');
        }
    }
    
    /**
     * Register status change hook after WooCommerce init
     */
    public function register_status_change_hook() {
        add_action('woocommerce_order_status_changed', array($this, 'recalculate_ship_by_on_status_change'), 10, 4);
    }
    
    /**
     * Recalculate ship-by date when order status changes to processing
     * This ensures ship-by date is calculated from payment received date, not order date
     *
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param WC_Order $order Order object
     */
    public function recalculate_ship_by_on_status_change($order_id, $old_status, $new_status, $order) {
        // DEBUG: Log every time hook fires
        error_log("WLM DEBUG: Hook fired! Order #{$order_id}: {$old_status} → {$new_status}");
        
        // Only recalculate when order moves to processing (payment received)
        if ($new_status !== 'processing') {
            error_log("WLM DEBUG: Skipping - new status is not 'processing'");
            return;
        }
        
        // Always recalculate when moving to processing (payment received)
        error_log("WLM DEBUG: Recalculating ship-by date for order #{$order_id}");
        WLM_Core::log('Order ' . $order_id . ' status changed to processing, recalculating ship-by date from payment date');
        
        // Get earliest and latest delivery dates (should already be saved)
        $earliest = $order->get_meta('_wlm_earliest_delivery');
        $latest = $order->get_meta('_wlm_latest_delivery');
        
        if (empty($earliest) || empty($latest)) {
            WLM_Core::log('No delivery dates found for order ' . $order_id . ', skipping ship-by calculation');
            return;
        }
        
        // Calculate ship-by date from NOW (payment received date) + processing days
        $payment_received_date = current_time('timestamp');
        $processing_days = (float) get_option('wlm_processing_days', 1);
        $ship_by_timestamp = strtotime('+' . $processing_days . ' days', $payment_received_date);
        $ship_by_date = date('Y-m-d', $ship_by_timestamp);
        
        // Also recalculate delivery dates based on new ship-by date
        $calculator = new WLM_Calculator();
        
        // Get shipping method from order
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method_id = null;
        foreach ($shipping_methods as $method) {
            $shipping_method_id = $method->get_method_id();
            error_log("WLM DEBUG: Found shipping method ID: {$shipping_method_id}");
            break;
        }
        
        $transit_min = null;
        $transit_max = null;
        
        if ($shipping_method_id) {
            // Get transit times from shipping method
            $settings = get_option('wlm_settings', array());
            $shipping_method = null;
            
            foreach ($settings['shipping_methods'] ?? array() as $method) {
                if ($method['id'] === $shipping_method_id) {
                    $shipping_method = $method;
                    break;
                }
            }
            
            if ($shipping_method) {
                $transit_min = (int) ($shipping_method['transit_min'] ?? 1);
                $transit_max = (int) ($shipping_method['transit_max'] ?? 3);
                error_log("WLM DEBUG: Found WLM shipping method with transit: min={$transit_min}, max={$transit_max}");
            } else {
                error_log("WLM DEBUG: Shipping method ID '{$shipping_method_id}' not found in WLM settings");
            }
        }
        
        // FALLBACK: If no transit times found, calculate from old dates
        if ($transit_min === null || $transit_max === null) {
            error_log("WLM DEBUG: Using fallback - calculating transit from old dates");
            
            $old_ship_by = $order->get_meta('_wlm_ship_by_date');
            $old_earliest = $order->get_meta('_wlm_earliest_delivery');
            $old_latest = $order->get_meta('_wlm_latest_delivery');
            
            if ($old_ship_by && $old_earliest && $old_latest) {
                // Calculate transit days from old dates
                $old_ship_by_ts = strtotime($old_ship_by);
                $old_earliest_ts = strtotime($old_earliest);
                $old_latest_ts = strtotime($old_latest);
                
                $transit_min = max(0, round(($old_earliest_ts - $old_ship_by_ts) / (24 * 60 * 60)));
                $transit_max = max(0, round(($old_latest_ts - $old_ship_by_ts) / (24 * 60 * 60)));
                
                error_log("WLM DEBUG: Calculated transit from old dates: min={$transit_min}, max={$transit_max}");
            } else {
                // Last resort: use defaults
                $transit_min = 1;
                $transit_max = 3;
                error_log("WLM DEBUG: No old dates found, using defaults: min={$transit_min}, max={$transit_max}");
            }
        }
        
        // Calculate new delivery dates from ship-by date + transit
        $earliest_timestamp = $calculator->add_business_days($ship_by_timestamp, $transit_min);
        $latest_timestamp = $calculator->add_business_days($ship_by_timestamp, $transit_max);
        
        $new_earliest = date('Y-m-d', $earliest_timestamp);
        $new_latest = date('Y-m-d', $latest_timestamp);
        $new_window = date_i18n(get_option('date_format'), $earliest_timestamp) . ' – ' . date_i18n(get_option('date_format'), $latest_timestamp);
        
        // Update order meta with recalculated dates
        $order->update_meta_data('_wlm_ship_by_date', $ship_by_date);
        $order->update_meta_data('_wlm_earliest_delivery', $new_earliest);
        $order->update_meta_data('_wlm_latest_delivery', $new_latest);
        $order->update_meta_data('_wlm_delivery_window', $new_window);
        $order->update_meta_data('_wlm_is_pending_payment', 'no');
        $order->save();
        
        error_log("WLM DEBUG: SUCCESS! Updated order #{$order_id}: ship_by={$ship_by_date}, earliest={$new_earliest}, latest={$new_latest}");
        WLM_Core::log('Recalculated delivery dates for order ' . $order_id . ' on status change to processing: ship_by=' . $ship_by_date . ', earliest=' . $new_earliest . ', latest=' . $new_latest);
    }
}
