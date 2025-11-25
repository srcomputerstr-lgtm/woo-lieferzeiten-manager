<?php
/**
 * Helper class for Tracking Plugin integration
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Tracking_Helper {
    
    /**
     * Get transit times for a Germanized/Shiptastic shipping provider
     *
     * @param string $provider_slug Germanized provider slug (e.g., 'gls-2', 'raben')
     * @return array ['min' => int, 'max' => int] Transit times in business days
     */
    public static function get_transit_times($provider_slug) {
        // Get all WLM shipping methods
        $shipping_methods = get_option('wlm_shipping_methods', array());
        
        if (empty($shipping_methods)) {
            return self::get_default_transit_times();
        }
        
        // Find method with matching provider
        foreach ($shipping_methods as $method_id => $method) {
            if (isset($method['shiptastic_provider']) && $method['shiptastic_provider'] === $provider_slug) {
                // Found matching method, return transit times
                if (isset($method['transit_min']) && isset($method['transit_max'])) {
                    return array(
                        'min' => (int) $method['transit_min'],
                        'max' => (int) $method['transit_max']
                    );
                }
            }
        }
        
        // No matching method found, return default
        return self::get_default_transit_times();
    }
    
    /**
     * Get default transit times (fallback)
     *
     * @return array
     */
    private static function get_default_transit_times() {
        return array(
            'min' => 3,
            'max' => 5
        );
    }
    
    /**
     * Get ship-by date from WLM order meta
     *
     * @param int $order_id WooCommerce order ID
     * @return string|null Ship-by date in Y-m-d format or null
     */
    public static function get_ship_by_date($order_id) {
        $ship_by = get_post_meta($order_id, '_wlm_ship_by_date', true);
        
        if (empty($ship_by)) {
            return null;
        }
        
        // Convert to Y-m-d if needed
        if (strpos($ship_by, '.') !== false) {
            // German format: DD.MM.YYYY
            $parts = explode('.', $ship_by);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        }
        
        return $ship_by;
    }
    
    /**
     * Check if order is delayed based on ship-by date
     * Only shows delay if order is NOT yet shipped/completed
     *
     * @param int $order_id WooCommerce order ID
     * @return array ['delayed' => bool, 'message' => string]
     */
    public static function check_ship_delay($order_id) {
        $result = array(
            'delayed' => false,
            'message' => ''
        );
        
        $ship_by_date = self::get_ship_by_date($order_id);
        
        if (empty($ship_by_date)) {
            return $result;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return $result;
        }
        
        $order_status = $order->get_status();
        $today = date('Y-m-d');
        
        // Check if ship-by date is in the past
        if (strtotime($ship_by_date) < strtotime($today)) {
            // Only show delay if order is NOT yet shipped/completed
            if (!in_array($order_status, array('completed', 'shipped'))) {
                $result['delayed'] = true;
                $result['message'] = 'Wir sind etwas später dran als geplant. Wir stellen in Kürze die Versandinformationen zur Verfügung, sobald Ihre Bestellung versendet wurde.';
            }
        }
        
        return $result;
    }
    
    /**
     * Get WLM delivery window from order meta
     *
     * @param int $order_id WooCommerce order ID
     * @return array|null ['earliest' => string, 'latest' => string] in DD.MM.YYYY format or null
     */
    public static function get_wlm_delivery_window($order_id) {
        $earliest = get_post_meta($order_id, '_wlm_earliest_delivery', true);
        $latest = get_post_meta($order_id, '_wlm_latest_delivery', true);
        
        if (empty($earliest) || empty($latest)) {
            return null;
        }
        
        return array(
            'earliest' => $earliest,
            'latest' => $latest
        );
    }
    
    /**
     * Get shipping method name from order meta
     *
     * @param int $order_id WooCommerce order ID
     * @return string|null
     */
    public static function get_shipping_method_name($order_id) {
        return get_post_meta($order_id, '_wlm_shipping_method_name', true);
    }
    
    /**
     * Get transit times from order's actual shipping method (not provider-based)
     * This ensures we get the correct transit times even when multiple methods
     * are mapped to the same Shiptastic provider
     *
     * @param int|WC_Order $order Order ID or Order object
     * @return array ['min' => int, 'max' => int] Transit times in business days
     */
    public static function get_transit_times_from_order($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            error_log('WLM DEBUG: get_transit_times_from_order - No order found');
            return self::get_default_transit_times();
        }
        
        // Get shipping methods from order
        $shipping_methods = $order->get_shipping_methods();
        
        if (empty($shipping_methods)) {
            error_log('WLM DEBUG: get_transit_times_from_order - No shipping methods in order ' . $order->get_id());
            return self::get_default_transit_times();
        }
        
        // Get first shipping method
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        $instance_id = $shipping_method->get_instance_id();
        
        // Build full method ID (e.g., 'flat_rate:5')
        $full_method_id = $method_id;
        if ($instance_id) {
            $full_method_id .= ':' . $instance_id;
        }
        
        error_log('WLM DEBUG: Order ' . $order->get_id() . ' - method_id: ' . $method_id . ', instance_id: ' . $instance_id . ', full: ' . $full_method_id);
        
        // Get all WLM shipping methods
        $wlm_methods = get_option('wlm_shipping_methods', array());
        
        if (empty($wlm_methods)) {
            error_log('WLM DEBUG: No WLM shipping methods configured');
            return self::get_default_transit_times();
        }
        
        error_log('WLM DEBUG: WLM methods array has ' . count($wlm_methods) . ' entries');
        
        // WLM stores methods as numeric array (0, 1, 2...)
        // Each method has an 'id' property that matches the WooCommerce method ID
        foreach ($wlm_methods as $key => $method) {
            if (!is_array($method)) {
                continue;
            }
            
            // Get method ID from config
            $wlm_method_id = isset($method['id']) ? $method['id'] : '';
            
            error_log('WLM DEBUG: Checking method ' . $key . ' - id: ' . $wlm_method_id);
            
            // Try exact match first (with instance ID)
            if ($wlm_method_id === $full_method_id) {
                if (isset($method['transit_min']) && isset($method['transit_max'])) {
                    error_log('WLM DEBUG: Found exact match! Transit: ' . $method['transit_min'] . '-' . $method['transit_max']);
                    return array(
                        'min' => (int) $method['transit_min'],
                        'max' => (int) $method['transit_max']
                    );
                }
            }
            
            // Try match without instance ID
            if ($wlm_method_id === $method_id) {
                if (isset($method['transit_min']) && isset($method['transit_max'])) {
                    error_log('WLM DEBUG: Found match without instance! Transit: ' . $method['transit_min'] . '-' . $method['transit_max']);
                    return array(
                        'min' => (int) $method['transit_min'],
                        'max' => (int) $method['transit_max']
                    );
                }
            }
        }
        
        // No matching method found, return default
        error_log('WLM DEBUG: No matching WLM method found for ' . $full_method_id . ' - using default');
        return self::get_default_transit_times();
    }
}
