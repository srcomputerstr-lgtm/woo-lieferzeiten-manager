<?php
/**
 * Calculator class for delivery time calculations
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Calculator {
    /**
     * Memoization cache for current request
     *
     * @var array
     */
    private $cache = array();

    /**
     * Calculate delivery window for a product
     *
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID (optional).
     * @param int $quantity Quantity.
     * @param string $shipping_zone Shipping zone.
     * @return array Delivery window data.
     */
    public function calculate_product_window($product_id, $variation_id = 0, $quantity = 1, $shipping_zone = null) {
        $cache_key = sprintf('%d_%d_%d_%s', $product_id, $variation_id, $quantity, $shipping_zone);
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $product = wc_get_product($variation_id > 0 ? $variation_id : $product_id);
        
        if (!$product) {
            return array();
        }

        // Get current time and settings
        $settings = WLM_Core::instance()->get_settings();
        $current_time = current_time('timestamp');
        $cutoff_time = $settings['cutoff_time'] ?? '14:00';

        // Determine start date
        $start_date = $this->get_start_date($current_time, $cutoff_time);

        // Get product availability
        $available_from = $this->get_product_available_from($product);
        
        if ($available_from > $start_date) {
            $start_date = $available_from;
        }

        // Add processing time
        $processing_min = (int) ($settings['processing_min'] ?? 1);
        $processing_max = (int) ($settings['processing_max'] ?? 2);
        
        $earliest_date = $this->add_business_days($start_date, $processing_min);
        $latest_date = $this->add_business_days($start_date, $processing_max);

        // Get shipping method and add transit time
        $shipping_method = $this->get_applicable_shipping_method($product, $quantity, $shipping_zone);
        
        if ($shipping_method) {
            $transit_min = (int) ($shipping_method['transit_min'] ?? 1);
            $transit_max = (int) ($shipping_method['transit_max'] ?? 3);
            
            $earliest_date = $this->add_business_days($earliest_date, $transit_min);
            $latest_date = $this->add_business_days($latest_date, $transit_max);
        }

        $result = array(
            'earliest' => $earliest_date,
            'latest' => $latest_date,
            'earliest_formatted' => $this->format_date($earliest_date),
            'latest_formatted' => $this->format_date($latest_date),
            'window_formatted' => $this->format_date_range($earliest_date, $latest_date),
            'stock_status' => $this->get_stock_status($product),
            'shipping_method' => $shipping_method
        );

        $this->cache[$cache_key] = $result;
        
        return $result;
    }

    /**
     * Calculate delivery window for cart
     *
     * @param array|null $method_config Optional shipping method configuration.
     * @param bool $is_express Whether to calculate for express shipping.
     * @return array Delivery window data.
     */
    public function calculate_cart_window($method_config = null, $is_express = false) {
        if (!WC()->cart) {
            return array();
        }

        $cart_items = WC()->cart->get_cart();
        $earliest = null;
        $latest = null;

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            $quantity = $cart_item['quantity'];

            $window = $this->calculate_product_window($product_id, $variation_id, $quantity);

            if (empty($window)) {
                continue;
            }

            if ($earliest === null || $window['earliest'] > $earliest) {
                $earliest = $window['earliest'];
            }

            if ($latest === null || $window['latest'] > $latest) {
                $latest = $window['latest'];
            }
        }

        if ($earliest === null || $latest === null) {
            return array();
        }

        return array(
            'earliest' => $earliest,
            'latest' => $latest,
            'window_formatted' => $this->format_date_range($earliest, $latest)
        );
    }

    /**
     * Get start date based on current time and cutoff
     *
     * @param int $current_time Current timestamp.
     * @param string $cutoff_time Cutoff time (HH:MM).
     * @return int Start timestamp.
     */
    private function get_start_date($current_time, $cutoff_time) {
        $cutoff_parts = explode(':', $cutoff_time);
        $cutoff_hour = (int) $cutoff_parts[0];
        $cutoff_minute = (int) ($cutoff_parts[1] ?? 0);

        $today_cutoff = strtotime(date('Y-m-d', $current_time) . ' ' . $cutoff_time);

        if ($current_time > $today_cutoff) {
            // After cutoff, start next business day
            $start_date = strtotime('+1 day', $current_time);
            $start_date = $this->get_next_business_day($start_date);
        } else {
            // Before cutoff, start today if business day
            $start_date = $this->get_next_business_day($current_time);
        }

        return $start_date;
    }

    /**
     * Get product available from date
     *
     * @param WC_Product $product Product object.
     * @return int Timestamp.
     */
    private function get_product_available_from($product) {
        $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);

        if ($available_from) {
            return strtotime($available_from);
        }

        // If not set, calculate from lead time
        $lead_time = get_post_meta($product->get_id(), '_wlm_lead_time_days', true);
        
        if ($lead_time && is_numeric($lead_time)) {
            return $this->add_business_days(current_time('timestamp'), (int) $lead_time);
        }

        return current_time('timestamp');
    }

    /**
     * Calculate available from date based on lead time
     *
     * @param int $lead_time_days Lead time in days.
     * @return string Date in Y-m-d format.
     */
    public function calculate_available_from_date($lead_time_days) {
        $timestamp = $this->add_business_days(current_time('timestamp'), (int) $lead_time_days);
        return date('Y-m-d', $timestamp);
    }

    /**
     * Add business days to a date
     *
     * @param int $start_timestamp Start timestamp.
     * @param int $days Number of business days to add.
     * @return int Result timestamp.
     */
    public function add_business_days($start_timestamp, $days) {
        $settings = WLM_Core::instance()->get_settings();
        $business_days = $settings['business_days'] ?? array(1, 2, 3, 4, 5);
        $holidays = $settings['holidays'] ?? array();

        $current = $start_timestamp;
        $added = 0;

        while ($added < $days) {
            $current = strtotime('+1 day', $current);
            
            // Check if it's a business day
            $day_of_week = (int) date('N', $current); // 1 (Monday) to 7 (Sunday)
            
            if (!in_array($day_of_week, $business_days)) {
                continue;
            }

            // Check if it's a holiday
            $date_str = date('Y-m-d', $current);
            if (in_array($date_str, $holidays)) {
                continue;
            }

            $added++;
        }

        return $current;
    }

    /**
     * Get next business day
     *
     * @param int $timestamp Starting timestamp.
     * @return int Next business day timestamp.
     */
    private function get_next_business_day($timestamp) {
        $settings = WLM_Core::instance()->get_settings();
        $business_days = $settings['business_days'] ?? array(1, 2, 3, 4, 5);
        $holidays = $settings['holidays'] ?? array();

        $current = $timestamp;
        $max_iterations = 30; // Prevent infinite loop
        $iterations = 0;

        while ($iterations < $max_iterations) {
            $day_of_week = (int) date('N', $current);
            $date_str = date('Y-m-d', $current);

            if (in_array($day_of_week, $business_days) && !in_array($date_str, $holidays)) {
                return $current;
            }

            $current = strtotime('+1 day', $current);
            $iterations++;
        }

        return $timestamp;
    }

    /**
     * Get applicable shipping method for product
     *
     * @param WC_Product $product Product object.
     * @param int $quantity Quantity.
     * @param string $shipping_zone Shipping zone.
     * @return array|null Shipping method data or null.
     */
    private function get_applicable_shipping_method($product, $quantity, $shipping_zone) {
        $shipping_methods = WLM_Core::instance()->get_shipping_methods();
        
        // Debug logging
        if (WLM_Core::instance()->is_debug_mode()) {
            WLM_Core::instance()->debug_log('get_applicable_shipping_method called', array(
                'product_id' => $product->get_id(),
                'quantity' => $quantity,
                'shipping_methods_count' => count($shipping_methods),
                'shipping_methods' => $shipping_methods
            ));
        }
        
        if (empty($shipping_methods)) {
            return null;
        }

        $applicable = array();

        foreach ($shipping_methods as $method) {
            // Skip if method is not enabled
            if (isset($method['enabled']) && !$method['enabled']) {
                continue;
            }
            
            if ($this->check_shipping_method_conditions($method, $product, $quantity, $shipping_zone)) {
                $applicable[] = $method;
            }
        }
        
        // Debug logging
        if (WLM_Core::instance()->is_debug_mode()) {
            WLM_Core::instance()->debug_log('Applicable shipping methods found', array(
                'count' => count($applicable),
                'methods' => $applicable
            ));
        }

        if (empty($applicable)) {
            return null;
        }

        // Sort by priority, then price, then delivery time
        usort($applicable, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            if ($a['cost'] != $b['cost']) {
                return $a['cost'] - $b['cost'];
            }
            return $a['transit_min'] - $b['transit_min'];
        });
        
        $selected_method = $applicable[0];
        
        // Ensure proper structure for frontend display
        return array(
            'id' => $selected_method['id'] ?? '',
            'name' => $selected_method['name'] ?? '',
            'title' => $selected_method['name'] ?? '', // Use 'name' as 'title' for frontend
            'cost' => $selected_method['cost'] ?? 0,
            'cost_type' => $selected_method['cost_type'] ?? 'flat',
            'transit_min' => $selected_method['transit_min'] ?? 1,
            'transit_max' => $selected_method['transit_max'] ?? 3,
            'cost_info' => $this->format_cost_info($selected_method)
        );
    }

    /**
     * Check if shipping method conditions are met
     *
     * @param array $method Shipping method data.
     * @param WC_Product $product Product object.
     * @param int $quantity Quantity.
     * @param string $shipping_zone Shipping zone.
     * @return bool
     */
    private function check_shipping_method_conditions($method, $product, $quantity, $shipping_zone) {
        // Check if method is enabled
        if (isset($method['enabled']) && !$method['enabled']) {
            return false;
        }
        
        // Check weight
        $weight = $product->get_weight();
        if ($weight) {
            $total_weight = floatval($weight) * $quantity;
            
            if (!empty($method['weight_min']) && $total_weight < floatval($method['weight_min'])) {
                return false;
            }
            if (!empty($method['weight_max']) && $total_weight > floatval($method['weight_max'])) {
                return false;
            }
        }

        // Check quantity
        if (!empty($method['qty_min']) && $quantity < intval($method['qty_min'])) {
            return false;
        }
        if (!empty($method['qty_max']) && $quantity > intval($method['qty_max'])) {
            return false;
        }
        
        // Check product price (for cart total simulation)
        $product_price = $product->get_price() * $quantity;
        if (!empty($method['cart_total_min']) && $product_price < floatval($method['cart_total_min'])) {
            return false;
        }
        if (!empty($method['cart_total_max']) && $product_price > floatval($method['cart_total_max'])) {
            return false;
        }
        
        // Check required attributes
        $required_attrs = array();
        
        // New format: attribute_conditions array
        if (!empty($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
            $required_attrs = $method['attribute_conditions'];
        }
        // Old format: required_attributes string
        elseif (!empty($method['required_attributes'])) {
            $lines = array_filter(array_map('trim', explode("\n", $method['required_attributes'])));
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($attr, $val) = array_map('trim', explode('=', $line, 2));
                    $required_attrs[] = array('attribute' => $attr, 'value' => $val);
                }
            }
        }
        
        // Check each attribute condition
        foreach ($required_attrs as $condition) {
            $attr_slug = $condition['attribute'] ?? '';
            $attr_value = $condition['value'] ?? '';
            
            if (empty($attr_slug) || empty($attr_value)) {
                continue;
            }
            
            $has_attribute = false;
            
            if ($product->is_type('variation')) {
                $variation_attrs = $product->get_attributes();
                if (isset($variation_attrs[$attr_slug]) && $variation_attrs[$attr_slug] === $attr_value) {
                    $has_attribute = true;
                }
            } else {
                $product_attr = $product->get_attribute($attr_slug);
                if ($product_attr === $attr_value) {
                    $has_attribute = true;
                }
            }
            
            if (!$has_attribute) {
                return false;
            }
        }
        
        // Check required categories
        if (!empty($method['required_categories'])) {
            $required_cats = is_array($method['required_categories']) 
                ? $method['required_categories'] 
                : array_filter(array_map('trim', explode(',', $method['required_categories'])));
            
            if (!empty($required_cats)) {
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                
                if (!array_intersect($required_cats, $product_cats)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get stock status for product
     *
     * @param WC_Product $product Product object.
     * @return array Stock status data.
     */
    private function get_stock_status($product) {
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();
        $settings = WLM_Core::instance()->get_settings();
        $max_visible = $settings['max_visible_stock'] ?? 100;

        $result = array(
            'status' => $stock_status,
            'in_stock' => $stock_status === 'instock',
            'quantity' => null,
            'message' => ''
        );

        if ($stock_status === 'instock' && $stock_quantity !== null) {
            $display_quantity = min($stock_quantity, $max_visible);
            $result['quantity'] = $display_quantity;
            $result['message'] = sprintf(__('Auf Lager: %d Stück', 'woo-lieferzeiten-manager'), $display_quantity);
        } elseif ($stock_status === 'onbackorder') {
            $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);
            if ($available_from) {
                $result['message'] = sprintf(__('Wieder verfügbar ab: %s', 'woo-lieferzeiten-manager'), $this->format_date(strtotime($available_from)));
            }
        }

        return $result;
    }

    /**
     * Format date
     *
     * @param int $timestamp Timestamp.
     * @return string Formatted date.
     */
    private function format_date($timestamp) {
        // Format: "Mi, 12.11."
        $day_names = array(
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        );

        $day_of_week = (int) date('N', $timestamp);
        $day_name = $day_names[$day_of_week];
        $date_part = date('d.m.', $timestamp);

        return $day_name . ', ' . $date_part;
    }

    /**
     * Format date range
     *
     * @param int $start_timestamp Start timestamp.
     * @param int $end_timestamp End timestamp.
     * @return string Formatted date range.
     */
    private function format_date_range($start_timestamp, $end_timestamp) {
        return $this->format_date($start_timestamp) . ' – ' . $this->format_date($end_timestamp);
    }
    
    /**
     * Format cost info for shipping method
     *
     * @param array $method Shipping method data.
     * @return string Cost info string.
     */
    private function format_cost_info($method) {
        if (empty($method)) {
            return '';
        }
        
        $cost = floatval($method['cost'] ?? 0);
        $cost_type = $method['cost_type'] ?? 'flat';
        
        if ($cost <= 0) {
            return __('Kostenlos', 'woo-lieferzeiten-manager');
        }
        
        $info = wc_price($cost);
        
        switch ($cost_type) {
            case 'by_weight':
                $info .= ' ' . __('pro kg', 'woo-lieferzeiten-manager');
                break;
            case 'by_qty':
                $info .= ' ' . __('pro Stück', 'woo-lieferzeiten-manager');
                break;
            case 'flat':
            default:
                // No suffix for flat rate
                break;
        }
        
        return $info;
    }
}
