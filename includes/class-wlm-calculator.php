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

        // Add processing time
        $processing_min = (int) ($settings['processing_min'] ?? 1);
        $processing_max = (int) ($settings['processing_max'] ?? 2);
        
        $earliest_date = $this->add_business_days($start_date, $processing_min);
        $latest_date = $this->add_business_days($start_date, $processing_max);

        // Get shipping method and add transit time
        // If shipping_zone is an array, it's a method config
        if (is_array($shipping_zone)) {
            $shipping_method = $shipping_zone;
            error_log('[WLM DEBUG Calculator] Using method config: ' . ($shipping_method['name'] ?? 'unknown'));
        } else {
            $shipping_method = $this->get_applicable_shipping_method($product, $quantity, $shipping_zone);
        }
        
        if ($shipping_method) {
            // Use express transit times if express mode
            if ($is_express && !empty($shipping_method['express_enabled'])) {
                // Use express cutoff time
                $express_cutoff = $shipping_method['express_cutoff'] ?? '14:00';
                $start_date = $this->get_start_date($current_time, $express_cutoff);
                
                // Recalculate with express processing (usually 0)
                $earliest_date = $start_date;
                $latest_date = $start_date;
                
                $transit_min = (int) ($shipping_method['express_transit_min'] ?? 0);
                $transit_max = (int) ($shipping_method['express_transit_max'] ?? 1);
            } else {
                $transit_min = (int) ($shipping_method['transit_min'] ?? 1);
                $transit_max = (int) ($shipping_method['transit_max'] ?? 3);
                error_log('[WLM DEBUG Calculator] Method: ' . ($shipping_method['name'] ?? 'unknown') . ', transit_min=' . $transit_min . ', transit_max=' . $transit_max);
            }
            
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
        
        // If not enough stock, check for manual available_from date
        $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);

        if ($available_from) {
            return strtotime($available_from);
        }

        // If not set, calculate from lead time (only when stock is insufficient)
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
            // Convert old format to new format if needed
            if (isset($condition['value']) && !isset($condition['values'])) {
                $condition['values'] = array($condition['value']);
                $condition['logic'] = 'at_least_one';
            }
            
            $attr_slug = $condition['attribute'] ?? '';
            $values = $condition['values'] ?? array();
            $logic = $condition['logic'] ?? 'at_least_one';
            
            if (empty($attr_slug) || empty($values)) {
                continue;
            }
            
            // Get product attribute values
            $product_values = array();
            
            if ($product->is_type('variation')) {
                $variation_attrs = $product->get_attributes();
                if (isset($variation_attrs[$attr_slug])) {
                    $product_values[] = $variation_attrs[$attr_slug];
                }
            } else {
                $product_attr = $product->get_attribute($attr_slug);
                if ($product_attr) {
                    // Split by comma for multi-value attributes
                    $product_values = array_map('trim', explode(',', $product_attr));
                }
            }
            
            // Apply logic operator
            $condition_met = $this->check_attribute_logic($product_values, $values, $logic);
            
            if (!$condition_met) {
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
        $max_visible = $settings['max_visible_stock'] ?? 100;

        $result = array(
            'status' => $stock_status,
            'in_stock' => $stock_status === 'instock',
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
                    // Check for manual available_from date
                    $available_from = get_post_meta($product->get_id(), '_wlm_available_from', true);
                    if ($available_from) {
                        $result['in_stock'] = false;
                        $result['available_date'] = $available_from;
                        $result['available_date_formatted'] = $this->format_date(strtotime($available_from));
                        
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
                        // Calculate based on delivery_days_min
                        $delivery_days = (int) get_post_meta($product->get_id(), '_wlm_delivery_days_min', true);
                        if ($delivery_days > 0) {
                            $available_timestamp = $this->add_business_days(time(), $delivery_days);
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
                            // No delivery days configured
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
                    }
                } else {
                    // Backorder not enabled
                    $result['in_stock'] = false;
                    $result['message'] = __('Nicht genügend auf Lager', 'woo-lieferzeiten-manager');
                }
            } else {
                // Enough stock available
                $result['message'] = __('Auf Lager', 'woo-lieferzeiten-manager');
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
            $cost_text = strip_tags(wc_price($cost));
            
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
}
