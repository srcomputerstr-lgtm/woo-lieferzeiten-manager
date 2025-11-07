<?php
/**
 * Shipping methods class
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Shipping_Methods {
    /**
     * Constructor
     */
    public function __construct() {
        // Register custom shipping method
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        // Add delivery window to shipping rates
        add_action('woocommerce_after_shipping_rate', array($this, 'display_delivery_window'), 10, 2);
    }

    /**
     * Initialize custom shipping method
     */
    public function init_shipping_method() {
        if (!class_exists('WLM_Shipping_Method')) {
            require_once WLM_PLUGIN_DIR . 'includes/class-wlm-shipping-method.php';
        }
    }

    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods Existing shipping methods.
     * @return array Modified shipping methods.
     */
    public function add_shipping_method($methods) {
        $methods['wlm_shipping'] = 'WLM_Shipping_Method';
        return $methods;
    }

    /**
     * Display delivery window under shipping rate
     *
     * @param WC_Shipping_Rate $method Shipping method.
     * @param int $index Method index.
     */
    public function display_delivery_window($method, $index) {
        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_cart_window();

        if (empty($window)) {
            return;
        }

        $method_id = $method->get_id();
        $is_express = WC()->session->get('wlm_express_selected') === $method_id;

        echo '<div class="wlm-shipping-window">';
        
        if ($is_express) {
            echo '<div class="wlm-express-active">';
            echo '<span class="wlm-checkmark">✓</span> ';
            echo esc_html__('Express-Versand gewählt', 'woo-lieferzeiten-manager');
            echo ' – ' . esc_html__('Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo ' <button type="button" class="wlm-remove-express" data-method-id="' . esc_attr($method_id) . '">';
            echo esc_html__('✕ entfernen', 'woo-lieferzeiten-manager');
            echo '</button>';
            echo '</div>';
        } else {
            echo '<div class="wlm-delivery-estimate">';
            echo '🚛 ' . esc_html__('Voraussichtliche Zustellung:', 'woo-lieferzeiten-manager') . ' ';
            echo '<strong>' . esc_html($window['window_formatted']) . '</strong>';
            echo '</div>';

            // Check if express is available
            if ($this->is_express_available($method_id)) {
                $express_cost = $this->get_express_cost($method_id);
                $express_window = $this->get_express_window();

                echo '<div class="wlm-express-cta">';
                echo '<button type="button" class="wlm-activate-express" data-method-id="' . esc_attr($method_id) . '">';
                echo sprintf(
                    esc_html__('Express aktivieren (+ %s) – Zustellung: %s', 'woo-lieferzeiten-manager'),
                    wc_price($express_cost),
                    esc_html($express_window)
                );
                echo '</button>';
                echo '</div>';
            }
        }

        echo '</div>';
    }

    /**
     * Check if express is available for method
     *
     * @param string $method_id Method ID.
     * @return bool
     */
    private function is_express_available($method_id) {
        // Check if all products are in stock
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product->get_stock_status() !== 'instock') {
                return false;
            }
        }

        // Check cutoff time
        $settings = WLM_Core::instance()->get_settings();
        $cutoff_time = $settings['cutoff_time'] ?? '14:00';
        $current_time = current_time('H:i');

        if ($current_time > $cutoff_time) {
            return false;
        }

        return true;
    }

    /**
     * Get express cost for method
     *
     * @param string $method_id Method ID.
     * @return float
     */
    private function get_express_cost($method_id) {
        // Default express cost - should be configurable per method
        return 9.90;
    }

    /**
     * Get express delivery window
     *
     * @return string
     */
    private function get_express_window() {
        $calculator = WLM_Core::instance()->calculator;
        $current_time = current_time('timestamp');
        
        // Express: next business day
        $delivery_date = $calculator->add_business_days($current_time, 1);
        
        $day_names = array(
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        );

        $day_of_week = (int) date('N', $delivery_date);
        $day_name = $day_names[$day_of_week];
        $date_part = date('d.m.Y', $delivery_date);

        return $day_name . ', ' . $date_part;
    }

    /**
     * Get configured shipping methods
     *
     * @return array
     */
    public function get_methods() {
        return get_option('wlm_shipping_methods', array());
    }

    /**
     * Save shipping methods
     *
     * @param array $methods Shipping methods.
     * @return bool
     */
    public function save_methods($methods) {
        return update_option('wlm_shipping_methods', $methods);
    }

    /**
     * Add new shipping method
     *
     * @param array $method Method data.
     * @return bool
     */
    public function add_method($method) {
        $methods = $this->get_methods();
        $method['id'] = uniqid('wlm_method_');
        $methods[] = $method;
        return $this->save_methods($methods);
    }

    /**
     * Update shipping method
     *
     * @param string $method_id Method ID.
     * @param array $method Method data.
     * @return bool
     */
    public function update_method($method_id, $method) {
        $methods = $this->get_methods();
        
        foreach ($methods as $key => $existing_method) {
            if ($existing_method['id'] === $method_id) {
                $methods[$key] = array_merge($existing_method, $method);
                return $this->save_methods($methods);
            }
        }
        
        return false;
    }

    /**
     * Delete shipping method
     *
     * @param string $method_id Method ID.
     * @return bool
     */
    public function delete_method($method_id) {
        $methods = $this->get_methods();
        
        foreach ($methods as $key => $method) {
            if ($method['id'] === $method_id) {
                unset($methods[$key]);
                return $this->save_methods(array_values($methods));
            }
        }
        
        return false;
    }
}
