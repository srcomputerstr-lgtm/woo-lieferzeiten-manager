<?php
/**
 * Express shipping class
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Express {
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers for express activation/deactivation
        add_action('wp_ajax_wlm_activate_express', array($this, 'ajax_activate_express'));
        add_action('wp_ajax_nopriv_wlm_activate_express', array($this, 'ajax_activate_express'));
        add_action('wp_ajax_wlm_deactivate_express', array($this, 'ajax_deactivate_express'));
        add_action('wp_ajax_nopriv_wlm_deactivate_express', array($this, 'ajax_deactivate_express'));

        // Add express to cart fragments
        add_filter('woocommerce_update_order_review_fragments', array($this, 'add_express_fragments'));
        
        // Add express fee to cart
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_express_fee'));
    }

    /**
     * Check if express is available
     *
     * @return bool
     */
    public function is_express_available() {
        if (!WC()->cart) {
            return false;
        }

        // Express is always available (cutoff time is checked per shipping method)
        // Stock status is not a blocker for express
        return true;
    }

    /**
     * Get express cost
     *
     * @param string $method_id Shipping method ID.
     * @return float
     */
    public function get_express_cost($method_id = null) {
        // Default express cost
        $cost = 9.90;

        // Get method-specific cost if available
        if ($method_id) {
            $methods = WLM_Core::instance()->get_shipping_methods();
            foreach ($methods as $method) {
                if ($method['id'] === $method_id && isset($method['express_cost'])) {
                    $cost = (float) $method['express_cost'];
                    break;
                }
            }
        }

        return $cost;
    }

    /**
     * Calculate express delivery date
     *
     * @return string Formatted delivery date.
     */
    public function calculate_express_delivery_date() {
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
     * AJAX handler for activating express
     */
    public function ajax_activate_express() {
        check_ajax_referer('wlm-express-nonce', 'nonce');

        $method_id = isset($_POST['method_id']) ? sanitize_text_field($_POST['method_id']) : '';

        if (empty($method_id)) {
            wp_send_json_error(array('message' => __('Ungültige Versandart', 'woo-lieferzeiten-manager')));
        }
        
        // Get method configuration
        $shipping_methods = WLM_Core::instance()->shipping_methods;
        $method_config = $shipping_methods->get_method_by_id($method_id);
        
        if (!$method_config || empty($method_config['express_enabled'])) {
            wp_send_json_error(array('message' => __('Express für diese Versandart nicht verfügbar', 'woo-lieferzeiten-manager')));
        }
        
        $cutoff_time = $method_config['express_cutoff'] ?? '12:00';
        $calculator = WLM_Core::instance()->calculator;
        
        if (!$calculator->is_express_available($cutoff_time)) {
            wp_send_json_error(array('message' => __('Express ist derzeit nicht verfügbar (Cutoff-Zeit überschritten)', 'woo-lieferzeiten-manager')));
        }

        // Store express selection in session
        WC()->session->set('wlm_express_selected', $method_id);

        // Recalculate cart totals
        WC()->cart->calculate_totals();

        wp_send_json_success(array(
            'message' => __('Express-Versand aktiviert', 'woo-lieferzeiten-manager'),
            'delivery_date' => $this->calculate_express_delivery_date(),
            'cost' => $this->get_express_cost($method_id)
        ));
    }

    /**
     * AJAX handler for deactivating express
     */
    public function ajax_deactivate_express() {
        check_ajax_referer('wlm-express-nonce', 'nonce');

        // Remove express selection from session
        WC()->session->set('wlm_express_selected', null);

        // Recalculate cart totals
        WC()->cart->calculate_totals();

        wp_send_json_success(array(
            'message' => __('Express-Versand entfernt', 'woo-lieferzeiten-manager')
        ));
    }

    /**
     * Add express fragments for cart updates
     *
     * @param array $fragments Existing fragments.
     * @return array Modified fragments.
     */
    public function add_express_fragments($fragments) {
        $is_express = WC()->session && WC()->session->get('wlm_express_selected');
        
        ob_start();
        if ($is_express) {
            echo '<div class="wlm-express-indicator">';
            echo esc_html__('Express-Versand aktiv', 'woo-lieferzeiten-manager');
            echo '</div>';
        }
        $fragments['.wlm-express-indicator'] = ob_get_clean();

        return $fragments;
    }

    /**
     * Get express status
     *
     * @return array Express status data.
     */
    public function get_express_status() {
        $is_selected = WC()->session && WC()->session->get('wlm_express_selected');
        $is_available = $this->is_express_available();

        return array(
            'selected' => (bool) $is_selected,
            'available' => $is_available,
            'method_id' => $is_selected ? WC()->session->get('wlm_express_selected') : null,
            'delivery_date' => $is_available ? $this->calculate_express_delivery_date() : null,
            'cost' => $is_available ? $this->get_express_cost() : 0
        );
    }

    /**
     * Add express fee to cart
     */
    public function add_express_fee() {
        if (!WC()->cart) {
            return;
        }

        $is_express = WC()->session && WC()->session->get('wlm_express_selected');
        
        if ($is_express) {
            $method_id = WC()->session->get('wlm_express_selected');
            $cost = $this->get_express_cost($method_id);

            WC()->cart->add_fee(
                __('Express-Versand', 'woo-lieferzeiten-manager'),
                $cost,
                true
            );
        }
    }
}
