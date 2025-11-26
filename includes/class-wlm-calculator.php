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
     * @param string|array|null $shipping_zone Shipping zone or method config.
     * @param bool $is_express Whether to calculate for express shipping.
     * @return array Delivery window data.
     */
    public function calculate_product_window($product_id, $variation_id = 0, $quantity = 1, $shipping_zone = null, $is_express = false) {
        // Build cache key - if shipping_zone is array (method config), use method ID
        $zone_key = is_array($shipping_zone) ? ($shipping_zone['id'] ?? 'unknown') : (string)$shipping_zone;
        $cache_key = sprintf('%d_%d_%d_%s_%s', $product_id, $variation_id, $quantity, $zone_key, $is_express ? 'express' : 'normal');
        
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

        // Get product availability (considering requested quantity)
        $available_from = $this->get_product_available_from($product, $quantity);
        
        if ($available_from > $start_date) {
            $start_date = $available_from;
        }

        // Add processing time (single fixed value)
        $processing_days = floatval($settings['processing_days'] ?? 1);
        // Round up to next full business day
        $processing_days_rounded = (int) ceil($processing_days);
        
        $after_processing = $this->add_business_days($start_date, $processing_days_rounded);

        // Get shipping method and add transit time
        // If shipping_zone is an array, it's a method config
        if (is_array($shipping_zone)) {
            $shipping_method = $shipping_zone;
            WLM_Core::log('[WLM DEBUG Calculator] Using method config: ' . ($shipping_method['name'] ?? 'unknown'));
        } else {
            $shipping_method = $this->get_applicable_shipping_method($product, $quantity, $shipping_zone);
        }
        
        if ($shipping_method) {
            // Use express transit times if express mode
            if ($is_express && !empty($shipping_method['express_enabled'])) {
                // Use express cutoff time if set, otherwise use standard cutoff
                $express_cutoff = $shipping_method['express_cutoff'] ?? $cutoff_time;
                $start_date = $this->get_start_date($current_time, $express_cutoff);
                
                // Recalculate processing time from new start date
                $after_processing = $this->add_business_days($start_date, $processing_days_rounded);
                
                $transit_min = (int) ($shipping_method['express_transit_min'] ?? 0);
                $transit_max = (int) ($shipping_method['express_transit_max'] ?? 1);
                
                WLM_Core::log('[WLM DEBUG Calculator] Express mode: start=' . date('Y-m-d', $start_date) . ', after_processing=' . date('Y-m-d', $after_processing) . ', transit=' . $transit_min . '-' . $transit_max);
            } else {
                $transit_min = (int) ($shipping_method['transit_min'] ?? 1);
                $transit_max = (int) ($shipping_method['transit_max'] ?? 3);
                WLM_Core::log('[WLM DEBUG Calculator] Standard mode: after_processing=' . date('Y-m-d', $after_processing) . ', transit=' . $transit_min . '-' . $transit_max);
            }
            
            // Add transit time to the date after processing
            $earliest_date = $this->add_business_days($after_processing, $transit_min);
            $latest_date = $this->add_business_days($after_processing, $transit_max);
            
            // Calculate ship-by date (when order must be shipped to meet earliest delivery)
            // This is the date after processing is complete, before transit begins
            $ship_by_date = $after_processing;
        }

        $result = array(
            'earliest' => $earliest_date,
            'latest' => $latest_date,
            'ship_by_date' => $ship_by_date,
            'earliest_formatted' => $this->format_date($earliest_date),
            'latest_formatted' => $this->format_date($latest_date),
            'ship_by_date_formatted' => $this->format_date($ship_by_date),
            'window_formatted' => $this->format_date_range($earliest_date, $latest_date),
            'stock_status' => $this->get_stock_status($product, $quantity),
            'shipping_method' => $shipping_method,
            'surcharge_notices' => $this->get_applicable_surcharge_notices($product, $quantity)
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

            // Pass method_config and is_express to product calculation
            $window = $this->calculate_product_window($product_id, $variation_id, $quantity, $method_config, $is_express);

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
            'earliest_formatted' => $this->format_date($earliest),
            'latest_formatted' => $this->format_date($latest),
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
        
        $current_time_formatted = date('Y-m-d H:i:s', $current_time);
        $cutoff_formatted = date('Y-m-d H:i:s', $today_cutoff);
        $is_after_cutoff = $current_time > $today_cutoff;
        
        WLM_Core::log('[WLM DEBUG] get_start_date: current_time=' . $current_time_formatted . ', cutoff=' . $cutoff_formatted . ', after_cutoff=' . ($is_after_cutoff ? 'YES' : 'NO'));

        if ($current_time > $today_cutoff) {
            // After cutoff, start next business day
            $start_date = strtotime('+1 day', $current_time);
            WLM_Core::log('[WLM DEBUG] After cutoff - moving to next day: ' . date('Y-m-d', $start_date));
            $start_date = $this->get_next_business_day($start_date);
        } else {
            // Before cutoff, start today if business day
            WLM_Core::log('[WLM DEBUG] Before cutoff - checking if today is business day');
            $start_date = $this->get_next_business_day($current_time);
        }
        
        WLM_Core::log('[WLM DEBUG] Final start_date: ' . date('Y-m-d (l)', $start_date));

        return $start_date;
    }

    /**
     * Get product available from date
     *
     * @param WC_Product $product Product object.
     * @param int $quantity Requested quantity.
     * @return int Timestamp.
     */
    private function get_product_available_from($product, $quantity = 1) {
        // Check if product is in stock with sufficient quantity
        $stock_quantity = $product->get_stock_quantity();
        $is_in_stock = $product->get_stock_status() === 'instock';
        
        // If sufficient stock available, product is immediately available
        if ($is_in_stock && $stock_quantity !== null && $quantity <= $stock_quantity) {
            return current_time('timestamp');
        }
        
        // If not enough stock, check for manual available_from date (Priority 1)
        $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);

        if ($available_from) {
            $available_timestamp = strtotime($available_from);
            $today_start = strtotime('today', current_time('timestamp'));
            // Only use if date is today or in the future
            if ($available_timestamp >= $today_start) {
                return $available_timestamp;
            }
        }

        // If manual date not set or in past, use calculated date (Priority 2)
        $calculated_date = get_post_meta($product->get_id(), '_wlm_calculated_available_date', true);
        
        if ($calculated_date) {
            $calculated_timestamp = strtotime($calculated_date);
            $today_start = strtotime('today', current_time('timestamp'));
            // Only use if date is today or in the future
            if ($calculated_timestamp >= $today_start) {
                return $calculated_timestamp;
            }
        }

        // Fallback: Calculate on-the-fly from lead time (Priority 3)
        $lead_time = get_post_meta($product->get_id(), '_wlm_lead_time_days', true);
        
        if ($lead_time && is_numeric($lead_time) && $lead_time > 0) {
            return $this->add_business_days(current_time('timestamp'), (int) $lead_time);
        }
        
        // Fallback: Use default lead time from settings (Priority 4)
        $settings = WLM_Core::instance()->get_settings();
        $default_lead_time = isset($settings['default_lead_time']) ? (int) $settings['default_lead_time'] : 0;
        
        if ($default_lead_time > 0) {
            return $this->add_business_days(current_time('timestamp'), $default_lead_time);
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
        
        // Ensure holidays is always an array (fix for malformed data)
        if (!is_array($holidays)) {
            $holidays = empty($holidays) ? array() : array($holidays);
        }
        
        WLM_Core::log('[WLM DEBUG] add_business_days: start=' . date('Y-m-d (l)', $start_timestamp) . ', days_to_add=' . $days);

        $current = $start_timestamp;
        $added = 0;

        while ($added < $days) {
            $current = strtotime('+1 day', $current);
            
            // Check if it's a business day
            $day_of_week = (int) date('N', $current); // 1 (Monday) to 7 (Sunday)
            $date_str = date('Y-m-d', $current);
            $day_name = date('l', $current);
            
            $is_business_day = in_array($day_of_week, $business_days);
            $is_holiday = in_array($date_str, $holidays);
            
            if (!$is_business_day) {
                WLM_Core::log('[WLM DEBUG]   - Skip ' . $date_str . ' (' . $day_name . '): not a business day');
                continue;
            }

            if ($is_holiday) {
                WLM_Core::log('[WLM DEBUG]   - Skip ' . $date_str . ' (' . $day_name . '): holiday');
                continue;
            }

            $added++;
            WLM_Core::log('[WLM DEBUG]   + Count ' . $date_str . ' (' . $day_name . '): added=' . $added . '/' . $days);
        }
        
        WLM_Core::log('[WLM DEBUG] add_business_days result: ' . date('Y-m-d (l)', $current));

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
        
        // Ensure holidays is always an array (fix for malformed data)
        if (!is_array($holidays)) {
            $holidays = empty($holidays) ? array() : array($holidays);
        }
        
        // Map day numbers to names
        $day_names = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
        $business_day_names = array_map(function($d) use ($day_names) { return $day_names[$d] ?? $d; }, $business_days);
        
        WLM_Core::log('[WLM DEBUG] get_next_business_day: business_days=' . implode(', ', $business_day_names) . ' (raw: ' . implode(',', $business_days) . ')');
        WLM_Core::log('[WLM DEBUG] get_next_business_day: holidays=' . (empty($holidays) ? 'none' : implode(', ', $holidays)));
        WLM_Core::log('[WLM DEBUG] get_next_business_day: checking from ' . date('Y-m-d (l)', $timestamp));

        $current = $timestamp;
        $max_iterations = 30; // Prevent infinite loop
        $iterations = 0;

        while ($iterations < $max_iterations) {
            $day_of_week = (int) date('N', $current);
            $date_str = date('Y-m-d', $current);
            $day_name = date('l', $current);
            
            $is_business_day = in_array($day_of_week, $business_days);
            $is_holiday = in_array($date_str, $holidays);
            
            WLM_Core::log('[WLM DEBUG]   - Checking ' . $date_str . ' (' . $day_name . ', N=' . $day_of_week . '): is_business_day=' . ($is_business_day ? 'YES' : 'NO') . ', is_holiday=' . ($is_holiday ? 'YES' : 'NO'));

            if ($is_business_day && !$is_holiday) {
                WLM_Core::log('[WLM DEBUG]   ✓ Found next business day: ' . $date_str . ' (' . $day_name . ')');
                return $current;
            }

            $current = strtotime('+1 day', $current);
            $iterations++;
        }
        
        WLM_Core::log('[WLM DEBUG] WARNING: Max iterations reached, returning original timestamp');
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
            'cost_info' => $this->format_cost_info($selected_method),
            // Express fields
            'express_enabled' => $selected_method['express_enabled'] ?? false,
            'express_cutoff' => $selected_method['express_cutoff'] ?? '12:00',
            'express_cost' => $selected_method['express_cost'] ?? 0,
            'express_transit_min' => $selected_method['express_transit_min'] ?? 0,
            'express_transit_max' => $selected_method['express_transit_max'] ?? 1
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
        WLM_Core::log('[WLM DEBUG] ========== check_shipping_method_conditions START ==========');
        WLM_Core::log('[WLM DEBUG] Method: ' . ($method['name'] ?? 'unknown'));
        WLM_Core::log('[WLM DEBUG] Product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')');
        WLM_Core::log('[WLM DEBUG] Product Type: ' . $product->get_type());
        
        // Check if method is enabled
        if (isset($method['enabled']) && !$method['enabled']) {
            WLM_Core::log('[WLM DEBUG] Method is disabled - returning false');
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
        WLM_Core::log('[WLM] Checking ' . count($required_attrs) . ' attribute conditions for product: ' . $product->get_name());
        
        foreach ($required_attrs as $condition) {
            // Convert old format to new format if needed
            if (isset($condition['value']) && !isset($condition['values'])) {
                $condition['values'] = array($condition['value']);
                $condition['logic'] = 'at_least_one';
            }
            
            $attr_slug = $condition['attribute'] ?? '';
            $values = $condition['values'] ?? array();
            $logic = $condition['logic'] ?? 'at_least_one';
            $type = $condition['type'] ?? '';
            
            // Handle shipping_class type - use product_shipping_class taxonomy
            if ($type === 'shipping_class') {
                $attr_slug = 'product_shipping_class';
                WLM_Core::log('[WLM DEBUG] Detected type=shipping_class, using taxonomy: product_shipping_class');
            }
            
            if (empty($attr_slug) || empty($values)) {
                WLM_Core::log('[WLM DEBUG] Skipping condition - empty attr_slug or values');
                continue;
            }
            
            // Get product attribute values
            $product_values = array();
            
            WLM_Core::log('[WLM DEBUG] Checking attribute: ' . $attr_slug);
            WLM_Core::log('[WLM DEBUG] Required values: ' . implode(', ', $values));
            WLM_Core::log('[WLM DEBUG] Logic: ' . $logic);
            WLM_Core::log('[WLM DEBUG] Type: ' . $type);
            
            // Handle shipping class using WooCommerce method (same as cart)
            if ($type === 'shipping_class') {
                WLM_Core::log('[WLM DEBUG] Using get_shipping_class() method');
                $shipping_class = $product->get_shipping_class();
                if ($shipping_class) {
                    $product_values[] = $shipping_class;
                    WLM_Core::log('[WLM DEBUG] Shipping class found: ' . $shipping_class);
                } else {
                    WLM_Core::log('[WLM DEBUG] No shipping class assigned to product');
                }
            }
            // Check if this is a taxonomy (categories, tags, custom taxonomies)
            elseif (taxonomy_exists($attr_slug)) {
                WLM_Core::log('[WLM DEBUG] ' . $attr_slug . ' is a TAXONOMY');
                // Get taxonomy terms for the product
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                WLM_Core::log('[WLM DEBUG] Using product ID: ' . $product_id);
                $terms = wp_get_post_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                WLM_Core::log('[WLM DEBUG] wp_get_post_terms result: ' . print_r($terms, true));
                if (!is_wp_error($terms) && !empty($terms)) {
                    $product_values = $terms;
                    WLM_Core::log('[WLM DEBUG] Taxonomy ' . $attr_slug . ' values: ' . implode(',', $product_values));
                } else {
                    WLM_Core::log('[WLM DEBUG] No taxonomy terms found or error');
                }
            } else {
                WLM_Core::log('[WLM DEBUG] ' . $attr_slug . ' is NOT a taxonomy, checking as attribute');
                // Regular product attribute
                if ($product->is_type('variation')) {
                    // Try variation attributes first
                    $variation_attrs = $product->get_attributes();
                    if (isset($variation_attrs[$attr_slug])) {
                        $product_values[] = $variation_attrs[$attr_slug];
                    } else {
                        // Fallback to parent product attributes
                        $parent_id = $product->get_parent_id();
                        if ($parent_id) {
                            $parent_product = wc_get_product($parent_id);
                            if ($parent_product) {
                                $parent_attr = $parent_product->get_attribute($attr_slug);
                                if ($parent_attr) {
                                    $product_values = array_map('trim', explode(',', $parent_attr));
                                    WLM_Core::log('[WLM] Variation inherited attribute from parent: ' . $attr_slug . ' = ' . $parent_attr);
                                }
                            }
                        }
                    }
                } else {
                    $product_attr = $product->get_attribute($attr_slug);
                    if ($product_attr) {
                        // Split by comma for multi-value attributes
                        $product_values = array_map('trim', explode(',', $product_attr));
                    }
                }
            }
            
            WLM_Core::log('[WLM DEBUG] Product values found: ' . implode(', ', $product_values));
            
            // Apply logic operator
            $condition_met = $this->check_attribute_logic($product_values, $values, $logic);
            
            WLM_Core::log('[WLM DEBUG] Condition check result: ' . ($condition_met ? 'PASSED' : 'FAILED'));
            WLM_Core::log('[WLM DEBUG] - Attribute: ' . $attr_slug);
            WLM_Core::log('[WLM DEBUG] - Logic: ' . $logic);
            WLM_Core::log('[WLM DEBUG] - Required: ' . implode(',', $values));
            WLM_Core::log('[WLM DEBUG] - Product has: ' . implode(',', $product_values));
            
            if (!$condition_met) {
                WLM_Core::log('[WLM DEBUG] Condition NOT met - returning FALSE');
                WLM_Core::log('[WLM DEBUG] ========== check_shipping_method_conditions END (FALSE) ==========');
                return false;
            }
            WLM_Core::log('[WLM DEBUG] Condition passed, continuing...');
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

        WLM_Core::log('[WLM DEBUG] All conditions passed - returning TRUE');
        WLM_Core::log('[WLM DEBUG] ========== check_shipping_method_conditions END (TRUE) ==========');
        return true;
    }
    
    /**
     * Check attribute logic
     *
     * @param array $product_values Product attribute values.
     * @param array $required_values Required values from condition.
     * @param string $logic Logic operator (at_least_one, all, none, only).
     * @return bool Whether condition is met.
     */
    private function check_attribute_logic($product_values, $required_values, $logic) {
        // Normalize values for comparison (case-insensitive)
        $product_values = array_map('strtolower', $product_values);
        $required_values = array_map('strtolower', $required_values);
        
        switch ($logic) {
            case 'at_least_one':
                // At least one of the required values must be present
                return !empty(array_intersect($product_values, $required_values));
                
            case 'all':
                // All required values must be present
                foreach ($required_values as $value) {
                    if (!in_array($value, $product_values)) {
                        return false;
                    }
                }
                return true;
                
            case 'none':
                // None of the required values must be present
                return empty(array_intersect($product_values, $required_values));
                
            case 'only':
                // Only the required values (and no others) must be present
                // Product values must be subset of required values
                foreach ($product_values as $value) {
                    if (!in_array($value, $required_values)) {
                        return false;
                    }
                }
                return !empty($product_values);
                
            default:
                // Default to at_least_one
                return !empty(array_intersect($product_values, $required_values));
        }
    }

    /**
     * Get stock status for product
     *
     * @param WC_Product $product Product object.
     * @param int $requested_quantity Requested quantity (for cart items).
     * @return array Stock status data.
     */
    public function get_stock_status($product, $requested_quantity = 1) {
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();
        $settings = WLM_Core::instance()->get_settings();
        
        // Get max visible stock (product-specific or global)
        $product_max_visible = get_post_meta($product->get_id(), '_wlm_max_visible_stock', true);
        $max_visible = !empty($product_max_visible) ? absint($product_max_visible) : ($settings['max_visible_stock'] ?? 100);

        $result = array(
            'status' => $stock_status,
            'in_stock' => $stock_status === 'instock',
            'insufficient_stock' => false,
            'quantity' => null,
            'message' => '',
            'available_date' => null,
            'available_date_formatted' => null
        );

        if ($stock_status === 'instock' && $stock_quantity !== null) {
            // Check if requested quantity exceeds available stock
            if ($requested_quantity > $stock_quantity) {
                // Backorder needed - calculate available date
                $backorder_enabled = $product->get_backorders();
                if ($backorder_enabled && $backorder_enabled !== 'no') {
                    // Get available date using proper logic (manual > calculated > lead time)
                    $available_timestamp = $this->get_product_available_from($product, $requested_quantity);
                    $current_timestamp = current_time('timestamp');
                    
                    // Only show backorder info if date is in the future
                    if ($available_timestamp > $current_timestamp) {
                        $result['in_stock'] = false;
                        $result['available_date'] = date('Y-m-d', $available_timestamp);
                        $result['available_date_formatted'] = $this->format_date($available_timestamp);
                        
                        // Differentiate between partial stock and no stock
                        if ($stock_quantity > 0) {
                            // Partial stock available
                            $display_quantity = min($stock_quantity, $max_visible);
                            $result['quantity'] = $display_quantity;
                            $result['message'] = sprintf(
                                __('Auf Lager: %d Stück - Rest ab: %s', 'woo-lieferzeiten-manager'),
                                $display_quantity,
                                $result['available_date_formatted']
                            );
                        } else {
                            // No stock available
                            $result['message'] = sprintf(__('Wieder verfügbar ab: %s', 'woo-lieferzeiten-manager'), $result['available_date_formatted']);
                        }
                    } else {
                        // Date is not in future (shouldn't happen with proper logic)
                        $result['in_stock'] = false;
                        if ($stock_quantity > 0) {
                            $display_quantity = min($stock_quantity, $max_visible);
                            $result['quantity'] = $display_quantity;
                            $result['message'] = sprintf(
                                __('Auf Lager: %d Stück - Rest zurzeit nicht verfügbar', 'woo-lieferzeiten-manager'),
                                $display_quantity
                            );
                        } else {
                            $result['message'] = __('Zurzeit nicht auf Lager', 'woo-lieferzeiten-manager');
                        }
                    }
                } else {
                    // Backorder not enabled
                    $result['in_stock'] = false;
                    $result['insufficient_stock'] = true;
                    $result['message'] = 'Nicht genügend auf Lager';
                }
            } else {
                // Enough stock available
                // Store actual stock for internal calculations
                $result['actual_stock'] = $stock_quantity;
                
                // Display message based on max_visible_stock (for customer display only)
                if ($stock_quantity > $max_visible) {
                    $result['quantity'] = $max_visible;
                    $result['message'] = sprintf(
                        'Mehr als %d auf Lager',
                        $max_visible
                    );
                } else {
                    $result['quantity'] = $stock_quantity;
                    $result['message'] = sprintf(
                        '%d Stück auf Lager',
                        $stock_quantity
                    );
                }
            }
        } elseif ($stock_status === 'onbackorder') {
            $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);
            if ($available_from) {
                $result['available_date'] = $available_from;
                $result['available_date_formatted'] = $this->format_date(strtotime($available_from));
                $result['message'] = sprintf(__('Wieder verfügbar ab: %s', 'woo-lieferzeiten-manager'), $result['available_date_formatted']);
            } else {
                // Use configurable out-of-stock text
                $out_of_stock_text = $settings['out_of_stock_text'] ?? __('Zurzeit nicht auf Lager', 'woo-lieferzeiten-manager');
                $result['message'] = $out_of_stock_text;
            }
        } elseif ($stock_status === 'outofstock') {
            // Use configurable out-of-stock text
            $out_of_stock_text = $settings['out_of_stock_text'] ?? __('Zurzeit nicht auf Lager', 'woo-lieferzeiten-manager');
            $result['message'] = $out_of_stock_text;
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
        
        $parts = array();
        
        // Cost
        if ($cost <= 0) {
            $parts[] = __('Kostenlos', 'woo-lieferzeiten-manager');
        } else {
            $cost_gross = WLM_Core::get_shipping_price_with_tax($cost);
            $cost_text = strip_tags(wc_price($cost_gross));
            
            switch ($cost_type) {
                case 'by_weight':
                    $cost_text .= ' ' . __('pro kg', 'woo-lieferzeiten-manager');
                    break;
                case 'by_qty':
                    $cost_text .= ' ' . __('pro Stück', 'woo-lieferzeiten-manager');
                    break;
            }
            
            $parts[] = $cost_text;
        }
        
        // Weight limits
        if (!empty($method['weight_min']) || !empty($method['weight_max'])) {
            $weight_text = '';
            if (!empty($method['weight_min']) && !empty($method['weight_max'])) {
                $weight_text = sprintf(__('%s-%s kg', 'woo-lieferzeiten-manager'), 
                    number_format_i18n($method['weight_min'], 2),
                    number_format_i18n($method['weight_max'], 2)
                );
            } elseif (!empty($method['weight_max'])) {
                $weight_text = sprintf(__('bis %s kg', 'woo-lieferzeiten-manager'), 
                    number_format_i18n($method['weight_max'], 2)
                );
            } elseif (!empty($method['weight_min'])) {
                $weight_text = sprintf(__('ab %s kg', 'woo-lieferzeiten-manager'), 
                    number_format_i18n($method['weight_min'], 2)
                );
            }
            if ($weight_text) {
                $parts[] = $weight_text;
            }
        }
        
        // Cart total limits
        if (!empty($method['cart_total_min']) || !empty($method['cart_total_max'])) {
            $total_text = '';
            if (!empty($method['cart_total_min']) && !empty($method['cart_total_max'])) {
                $total_text = sprintf(__('Warenkorbwert: %s-%s', 'woo-lieferzeiten-manager'), 
                    strip_tags(wc_price($method['cart_total_min'])),
                    strip_tags(wc_price($method['cart_total_max']))
                );
            } elseif (!empty($method['cart_total_max'])) {
                $total_text = sprintf(__('Warenkorbwert bis %s', 'woo-lieferzeiten-manager'), 
                    strip_tags(wc_price($method['cart_total_max']))
                );
            } elseif (!empty($method['cart_total_min'])) {
                $total_text = sprintf(__('Warenkorbwert ab %s', 'woo-lieferzeiten-manager'), 
                    strip_tags(wc_price($method['cart_total_min']))
                );
            }
            if ($total_text) {
                $parts[] = $total_text;
            }
        }
        
        return implode(' | ', $parts);
    }
    
    /**
     * Check if express shipping is available based on cutoff time
     *
     * @param string $cutoff_time Cutoff time in HH:MM format (default: 12:00).
     * @return bool True if express is available.
     */
    public function is_express_available($cutoff_time = '12:00') {
        // Get current time
        $current_time = current_time('H:i');
        
        // Compare times
        return $current_time < $cutoff_time;
    }

    /**
     * Check if product meets attribute and category conditions
     * Public wrapper for use by other classes
     *
     * @param WC_Product $product Product object.
     * @param array $method Method configuration with attribute_conditions and required_categories.
     * @return bool Whether product meets all conditions.
     */
    /**
     * Check if cart meets attribute conditions (cart-level check)
     * 
     * @param array $package Cart package
     * @param array $method Method configuration
     * @return bool True if conditions are met
     */
    public function check_cart_conditions($package, $method) {
        $required_attrs = array();
        
        // New format: attribute_conditions array
        if (!empty($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
            $required_attrs = $method['attribute_conditions'];
        }
        
        if (empty($required_attrs)) {
            return true; // No conditions = always show
        }
        
        WLM_Core::log('[WLM] Checking ' . count($required_attrs) . ' cart-level attribute conditions');
        
        // Check each condition
        foreach ($required_attrs as $condition) {
            $attr_slug = $condition['attribute'] ?? '';
            $required_values = $condition['values'] ?? array();
            $logic = $condition['logic'] ?? 'at_least_one';
            $type = $condition['type'] ?? 'attribute';
            
            if (empty($required_values)) {
                continue;
            }
            
            // Collect all values from all products in cart
            $cart_values = array();
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                if (!$product) continue;
                
                $product_values = array();
                
                if ($type === 'shipping_class') {
                    // Get shipping class
                    $shipping_class = $product->get_shipping_class();
                    if ($shipping_class) {
                        $product_values[] = $shipping_class;
                    }
                } elseif ($type === 'taxonomy') {
                    // Get taxonomy terms (categories, tags)
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $terms = wp_get_post_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                    if (!is_wp_error($terms)) {
                        $product_values = $terms;
                    }
                } else {
                    // Get product attribute
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    
                    if ($product->is_type('variation')) {
                        $variation_attrs = $product->get_attributes();
                        if (isset($variation_attrs[$attr_slug]) && !empty($variation_attrs[$attr_slug])) {
                            $product_values[] = $variation_attrs[$attr_slug];
                        } else {
                            $parent_product = wc_get_product($product_id);
                            if ($parent_product) {
                                $parent_attr = $parent_product->get_attribute($attr_slug);
                                if ($parent_attr) {
                                    $product_values = array_map('trim', explode(',', $parent_attr));
                                }
                            }
                        }
                    } else {
                        $product_attr = $product->get_attribute($attr_slug);
                        if ($product_attr) {
                            $product_values = array_map('trim', explode(',', $product_attr));
                        }
                    }
                }
                
                // Add to cart values
                $cart_values = array_merge($cart_values, $product_values);
            }
            
            // Remove duplicates and normalize
            $cart_values = array_unique(array_map('strtolower', array_map('trim', $cart_values)));
            $required_values_normalized = array_map('strtolower', array_map('trim', $required_values));
            
            // Apply logic operator to cart-level values
            $condition_met = $this->check_attribute_logic($cart_values, $required_values_normalized, $logic);
            
            WLM_Core::log('[WLM] Cart condition - Type: ' . $type . ', Attribute: ' . $attr_slug . ', Logic: ' . $logic . ', Required: ' . implode(',', $required_values) . ', Cart has: ' . implode(',', $cart_values) . ', Met: ' . ($condition_met ? 'YES' : 'NO'));
            
            if (!$condition_met) {
                WLM_Core::log('[WLM] Cart condition NOT met - returning false');
                return false;
            }
        }
        
        return true;
    }
    
    public function check_product_conditions($product, $method) {
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
        WLM_Core::log('[WLM] Checking ' . count($required_attrs) . ' attribute conditions for product: ' . $product->get_name());
        
        foreach ($required_attrs as $condition) {
            // Convert old format to new format if needed
            if (isset($condition['value']) && !isset($condition['values'])) {
                $condition['values'] = array($condition['value']);
                $condition['logic'] = 'at_least_one';
            }
            
            $attr_slug = $condition['attribute'] ?? '';
            $values = $condition['values'] ?? array();
            $logic = $condition['logic'] ?? 'at_least_one';
            $type = $condition['type'] ?? '';
            
            // Handle shipping_class type - use product_shipping_class taxonomy
            if ($type === 'shipping_class') {
                $attr_slug = 'product_shipping_class';
                WLM_Core::log('[WLM DEBUG] Detected type=shipping_class, using taxonomy: product_shipping_class');
            }
            
            if (empty($attr_slug) || empty($values)) {
                WLM_Core::log('[WLM DEBUG] Skipping condition - empty attr_slug or values');
                continue;
            }
            
            // Get product attribute values
            $product_values = array();
            
            // Get product ID (parent for variations)
            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            
            if ($product->is_type('variation')) {
                // For variations: Try to get from variation first, then from parent
                $variation_attrs = $product->get_attributes();
                if (isset($variation_attrs[$attr_slug]) && !empty($variation_attrs[$attr_slug])) {
                    $product_values[] = $variation_attrs[$attr_slug];
                } else {
                    // Try parent product
                    $parent_product = wc_get_product($product_id);
                    if ($parent_product) {
                        $parent_attr = $parent_product->get_attribute($attr_slug);
                        if ($parent_attr) {
                            $product_values = array_map('trim', explode(',', $parent_attr));
                        }
                    }
                }
            } else {
                $product_attr = $product->get_attribute($attr_slug);
                if ($product_attr) {
                    // Split by comma for multi-value attributes
                    $product_values = array_map('trim', explode(',', $product_attr));
                }
            }
            
            WLM_Core::log('[WLM DEBUG] Product values found: ' . implode(', ', $product_values));
            
            // Apply logic operator
            $condition_met = $this->check_attribute_logic($product_values, $values, $logic);
            
            WLM_Core::log('[WLM DEBUG] Condition check result: ' . ($condition_met ? 'PASSED' : 'FAILED'));
            WLM_Core::log('[WLM DEBUG] - Attribute: ' . $attr_slug);
            WLM_Core::log('[WLM DEBUG] - Logic: ' . $logic);
            WLM_Core::log('[WLM DEBUG] - Required: ' . implode(',', $values));
            WLM_Core::log('[WLM DEBUG] - Product has: ' . implode(',', $product_values));
            
            if (!$condition_met) {
                WLM_Core::log('[WLM DEBUG] Condition NOT met - returning FALSE');
                WLM_Core::log('[WLM DEBUG] ========== check_shipping_method_conditions END (FALSE) ==========');
                return false;
            }
            WLM_Core::log('[WLM DEBUG] Condition passed, continuing...');
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
     * Calculate applicable surcharges for cart package
     *
     * @param array $package Cart package data.
     * @return array Array of applicable surcharges with calculated costs.
     */
    public function calculate_surcharges($package) {
        $surcharges = get_option('wlm_surcharges', array());
        $strategy = get_option('wlm_surcharge_application_strategy', 'all_charges');
        
        // If disabled, return empty
        if ($strategy === 'disabled' || empty($surcharges)) {
            return array();
        }
        
        $applicable_surcharges = array();
        
        // Get cart totals
        $cart_total = 0;
        $cart_weight = 0;
        
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $quantity = $item['quantity'];
            
            $cart_total += $product->get_price() * $quantity;
            $weight = $product->get_weight();
            if ($weight) {
                $cart_weight += floatval($weight) * $quantity;
            }
        }
        
        // Check each surcharge
        foreach ($surcharges as $surcharge) {
            // Skip if disabled
            if (isset($surcharge['enabled']) && !$surcharge['enabled']) {
                continue;
            }
            
            // Check weight conditions
            if (!empty($surcharge['weight_min']) && $cart_weight < floatval($surcharge['weight_min'])) {
                continue;
            }
            if (!empty($surcharge['weight_max']) && $cart_weight > floatval($surcharge['weight_max'])) {
                continue;
            }
            
            // Check cart value conditions
            if (!empty($surcharge['cart_value_min']) && $cart_total < floatval($surcharge['cart_value_min'])) {
                continue;
            }
            if (!empty($surcharge['cart_value_max']) && $cart_total > floatval($surcharge['cart_value_max'])) {
                continue;
            }
            
            // Check attribute/taxonomy/shipping class conditions
            if (!empty($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])) {
                $all_conditions_met = true;
                
                WLM_Core::log('[WLM Surcharge] Checking conditions for: ' . ($surcharge['name'] ?? 'Unknown'));
                
                foreach ($surcharge['attribute_conditions'] as $condition) {
                    $condition_type = $condition['type'] ?? 'attribute';
                    $attr_slug = $condition['attribute'] ?? '';
                    $values = $condition['values'] ?? array();
                    $logic = $condition['logic'] ?? 'at_least_one';
                    
                    WLM_Core::log('[WLM Surcharge] Condition - Type: ' . $condition_type . ', Attribute: ' . $attr_slug . ', Values: ' . print_r($values, true));
                    
                    // For shipping_class, attribute is not needed (values are the slugs)
                    if ($condition_type === 'shipping_class') {
                        if (empty($values)) {
                            WLM_Core::log('[WLM Surcharge] Skipping - empty values for shipping_class');
                            continue;
                        }
                    } else {
                        // For attribute and taxonomy, both attribute and values are required
                        if (empty($attr_slug) || empty($values)) {
                            WLM_Core::log('[WLM Surcharge] Skipping - empty attr_slug or values');
                            continue;
                        }
                    }
                    
                    // Check condition for at least one product in cart
                    $condition_met_for_any_product = false;
                    
                    foreach ($package['contents'] as $item) {
                        $product = $item['data'];
                        
                        // Get product values based on condition type
                        $product_values = array();
                        
                        if ($condition_type === 'shipping_class') {
                            // Check shipping class
                            $shipping_class = $product->get_shipping_class();
                            WLM_Core::log('[WLM Surcharge] Product shipping class: ' . ($shipping_class ?: '(none)'));
                            if ($shipping_class) {
                                $product_values[] = $shipping_class;
                            }
                        } elseif ($condition_type === 'taxonomy') {
                            // Check taxonomy (category, tag)
                            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                            $terms = wp_get_post_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                            if (!is_wp_error($terms)) {
                                $product_values = $terms;
                            }
                        } else {
                            // Check product attribute
                            if ($product->is_type('variation')) {
                                $variation_attrs = $product->get_attributes();
                                if (isset($variation_attrs[$attr_slug])) {
                                    $product_values[] = $variation_attrs[$attr_slug];
                                } else {
                                    // Try parent product
                                    $parent = wc_get_product($product->get_parent_id());
                                    if ($parent) {
                                        $parent_attr = $parent->get_attribute($attr_slug);
                                        if ($parent_attr) {
                                            $product_values = array_map('trim', explode(',', $parent_attr));
                                        }
                                    }
                                }
                            } else {
                                $product_attr = $product->get_attribute($attr_slug);
                                if ($product_attr) {
                                    $product_values = array_map('trim', explode(',', $product_attr));
                                }
                            }
                        }
                        
                        // Check logic
                        $logic_result = $this->check_attribute_logic($product_values, $values, $logic);
                        WLM_Core::log('[WLM Surcharge] Logic check - Product values: ' . print_r($product_values, true) . ', Required: ' . print_r($values, true) . ', Logic: ' . $logic . ', Result: ' . ($logic_result ? 'PASS' : 'FAIL'));
                        if ($logic_result) {
                            $condition_met_for_any_product = true;
                            break;
                        }
                    }
                    
                    if (!$condition_met_for_any_product) {
                        WLM_Core::log('[WLM Surcharge] Condition NOT met for any product - skipping surcharge');
                        $all_conditions_met = false;
                        break;
                    }
                }
                
                if (!$all_conditions_met) {
                    WLM_Core::log('[WLM Surcharge] Not all conditions met - skipping surcharge');
                    continue;
                }
                WLM_Core::log('[WLM Surcharge] All conditions met - adding surcharge!');
            }
            
            // Calculate surcharge cost
            $cost = $this->calculate_surcharge_cost($surcharge, $package, $cart_total);
            
            if ($cost > 0) {
                $applicable_surcharges[] = array(
                    'id' => $surcharge['id'] ?? uniqid('surcharge_'),
                    'name' => $surcharge['name'] ?? 'Zuschlag',
                    'cost' => $cost,
                    'priority' => $surcharge['priority'] ?? 10,
                    'tax_class' => $surcharge['tax_class'] ?? '',
                    'apply_to_express' => !empty($surcharge['apply_to_express']), // Pass through Express flag
                );
            }
        }
        
        // Apply strategy
        return $this->apply_surcharge_strategy($applicable_surcharges, $strategy);
    }
    
    /**
     * Calculate cost for a single surcharge
     *
     * @param array $surcharge Surcharge configuration.
     * @param array $package Cart package.
     * @param float $cart_total Cart total.
     * @return float Calculated cost.
     */
    private function calculate_surcharge_cost($surcharge, $package, $cart_total) {
        $cost_type = $surcharge['cost_type'] ?? 'flat';
        $amount = floatval($surcharge['amount'] ?? 0);
        $charge_per = $surcharge['charge_per'] ?? 'cart';
        
        if ($amount <= 0) {
            return 0;
        }
        
        $cost = 0;
        
        switch ($charge_per) {
            case 'cart':
                // Once per cart
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100);
                } else {
                    $cost = $amount;
                }
                break;
                
            case 'shipping_class':
                // Per unique shipping class
                $shipping_classes = array();
                foreach ($package['contents'] as $item) {
                    $shipping_class = $item['data']->get_shipping_class();
                    if ($shipping_class && !in_array($shipping_class, $shipping_classes)) {
                        $shipping_classes[] = $shipping_class;
                    }
                }
                $count = count($shipping_classes);
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100) * $count;
                } else {
                    $cost = $amount * $count;
                }
                break;
                
            case 'product_category':
                // Per unique product category
                $categories = array();
                foreach ($package['contents'] as $item) {
                    $product_id = $item['data']->get_parent_id() ? $item['data']->get_parent_id() : $item['data']->get_id();
                    $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                    foreach ($product_cats as $cat) {
                        if (!in_array($cat, $categories)) {
                            $categories[] = $cat;
                        }
                    }
                }
                $count = count($categories);
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100) * $count;
                } else {
                    $cost = $amount * $count;
                }
                break;
                
            case 'product':
                // Per unique product
                $count = count($package['contents']);
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100) * $count;
                } else {
                    $cost = $amount * $count;
                }
                break;
                
            case 'cart_item':
                // Per cart item (same as product)
                $count = count($package['contents']);
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100) * $count;
                } else {
                    $cost = $amount * $count;
                }
                break;
                
            case 'quantity_unit':
                // Per quantity unit
                $total_quantity = 0;
                foreach ($package['contents'] as $item) {
                    $total_quantity += $item['quantity'];
                }
                if ($cost_type === 'percentage') {
                    $cost = $cart_total * ($amount / 100) * $total_quantity;
                } else {
                    $cost = $amount * $total_quantity;
                }
                break;
        }
        
        return $cost;
    }
    
    /**
     * Apply surcharge strategy to filter applicable surcharges
     *
     * @param array $surcharges Array of applicable surcharges.
     * @param string $strategy Strategy (all_charges, first_match, smallest, largest).
     * @return array Filtered surcharges.
     */
    private function apply_surcharge_strategy($surcharges, $strategy) {
        if (empty($surcharges)) {
            return array();
        }
        
        switch ($strategy) {
            case 'first_match':
                // Sort by priority (lowest first)
                usort($surcharges, function($a, $b) {
                    return ($a['priority'] ?? 10) - ($b['priority'] ?? 10);
                });
                return array(array_shift($surcharges));
                
            case 'smallest':
                // Return only the smallest surcharge
                usort($surcharges, function($a, $b) {
                    return $a['cost'] <=> $b['cost'];
                });
                return array(array_shift($surcharges));
                
            case 'largest':
                // Return only the largest surcharge
                usort($surcharges, function($a, $b) {
                    return $b['cost'] <=> $a['cost'];
                });
                return array(array_shift($surcharges));
                
            case 'all_charges':
            default:
                // Return all surcharges
                return $surcharges;
        }
    }
    
    /**
     * Get applicable surcharge notices for a product
     *
     * @param WC_Product $product Product object.
     * @param int $quantity Quantity to check (default: 1).
     * @return array Array of notice strings.
     */
    public function get_applicable_surcharge_notices($product, $quantity = 1) {
        $notices = array();
        
        if (!$product) {
            return $notices;
        }
        
        $surcharges = get_option('wlm_surcharges', array());
        
        if (empty($surcharges)) {
            return $notices;
        }
        
        foreach ($surcharges as $surcharge) {
            // Skip if disabled
            if (isset($surcharge['enabled']) && !$surcharge['enabled']) {
                continue;
            }
            
            // Skip if no notice for product page
            if (empty($surcharge['notice_product_page'])) {
                continue;
            }
            
            // Check if surcharge applies to this product
            if ($this->check_surcharge_product_conditions($surcharge, $product, $quantity)) {
                $notices[] = $surcharge['notice_product_page'];
            }
        }
        
        return $notices;
    }
    
    /**
     * Check if surcharge conditions are met for a product
     *
     * @param array $surcharge Surcharge configuration.
     * @param WC_Product $product Product object.
     * @param int $quantity Quantity to check (default: 1).
     * @return bool
     */
    private function check_surcharge_product_conditions($surcharge, $product, $quantity = 1) {
        // Check weight conditions (multiply by quantity for total weight)
        $weight = $product->get_weight();
        if ($weight) {
            $total_weight = $weight * $quantity;
            if (!empty($surcharge['weight_min']) && $total_weight < floatval($surcharge['weight_min'])) {
                return false;
            }
            if (!empty($surcharge['weight_max']) && $total_weight > floatval($surcharge['weight_max'])) {
                return false;
            }
        }
        
        // Check cart value conditions (multiply by quantity for total price)
        $price = $product->get_price();
        if ($price) {
            $total_price = $price * $quantity;
            if (!empty($surcharge['cart_value_min']) && $total_price < floatval($surcharge['cart_value_min'])) {
                return false;
            }
            if (!empty($surcharge['cart_value_max']) && $total_price > floatval($surcharge['cart_value_max'])) {
                return false;
            }
        }
        
        // Check attribute/taxonomy/shipping class conditions
        if (!empty($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])) {
            // Filter out empty conditions
            $valid_conditions = array_filter($surcharge['attribute_conditions'], function($condition) {
                $condition_type = $condition['type'] ?? 'attribute';
                $attr_slug = $condition['attribute'] ?? '';
                $values = $condition['values'] ?? array();
                
                // For shipping_class, attribute is not needed (values are the slugs)
                if ($condition_type === 'shipping_class') {
                    return !empty($values);
                }
                
                // For attribute and taxonomy, both attribute and values are required
                return !empty($attr_slug) && !empty($values);
            });
            
            // If no valid conditions, skip this surcharge
            if (empty($valid_conditions)) {
                return false;
            }
            
            foreach ($valid_conditions as $condition) {
                $condition_type = $condition['type'] ?? 'attribute';
                $attr_slug = $condition['attribute'] ?? '';
                $values = $condition['values'] ?? array();
                $logic = $condition['logic'] ?? 'at_least_one';
                
                // Get product values based on condition type
                $product_values = array();
                
                if ($condition_type === 'shipping_class') {
                    // Check shipping class
                    $shipping_class = $product->get_shipping_class();
                    if ($shipping_class) {
                        $product_values[] = $shipping_class;
                    }
                } elseif ($condition_type === 'taxonomy') {
                    // Check taxonomy (category, tag)
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $terms = wp_get_post_terms($product_id, $attr_slug, array('fields' => 'slugs'));
                    if (!is_wp_error($terms)) {
                        $product_values = $terms;
                    }
                } else {
                    // Check product attribute
                    if ($product->is_type('variation')) {
                        // First try variation-specific attributes
                        $variation_attrs = $product->get_attributes();
                        if (isset($variation_attrs[$attr_slug])) {
                            $product_values[] = $variation_attrs[$attr_slug];
                        }
                        
                        // If not found, ALWAYS fallback to parent product
                        if (empty($product_values)) {
                            $parent_id = $product->get_parent_id();
                            if ($parent_id) {
                                $parent_product = wc_get_product($parent_id);
                                if ($parent_product) {
                                    $parent_attr = $parent_product->get_attribute($attr_slug);
                                    if ($parent_attr) {
                                        $product_values = array_map('trim', explode(',', $parent_attr));
                                    }
                                }
                            }
                        }
                    } else {
                        // Simple product - get attribute directly
                        $product_attr = $product->get_attribute($attr_slug);
                        if ($product_attr) {
                            $product_values = array_map('trim', explode(',', $product_attr));
                        }
                    }
                }
                
                // Check logic
                $product_values_normalized = array_map('strtolower', array_map('trim', $product_values));
                $values_normalized = array_map('strtolower', array_map('trim', $values));
                
                $condition_met = $this->check_attribute_logic($product_values_normalized, $values_normalized, $logic);
                
                if (!$condition_met) {
                    return false;
                }
            }
        }
        
        return true;
    }
}
