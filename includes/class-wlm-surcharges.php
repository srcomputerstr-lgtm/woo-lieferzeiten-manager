<?php
/**
 * Surcharges class for shipping surcharges
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Surcharges {
    /**
     * Constructor
     */
    public function __construct() {
        // Add surcharges to cart
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_surcharges_to_cart'));
    }

    /**
     * Calculate surcharges for package
     *
     * @param array $package Package data.
     * @return array Surcharges.
     */
    public function calculate_surcharges($package) {
        $surcharges_config = get_option('wlm_surcharges', array());
        $applied_surcharges = array();

        if (empty($surcharges_config)) {
            return $applied_surcharges;
        }

        foreach ($surcharges_config as $surcharge) {
            if (!isset($surcharge['enabled']) || !$surcharge['enabled']) {
                continue;
            }

            if ($this->check_surcharge_conditions($surcharge, $package)) {
                $amount = $this->calculate_surcharge_amount($surcharge, $package);
                
                if ($amount > 0) {
                    $applied_surcharges[] = array(
                        'id' => $surcharge['id'],
                        'name' => $surcharge['name'],
                        'amount' => $amount,
                        'taxable' => isset($surcharge['taxable']) ? $surcharge['taxable'] : true,
                        'tax_class' => isset($surcharge['tax_class']) ? $surcharge['tax_class'] : ''
                    );
                }
            }
        }

        // Handle stacking rules
        $applied_surcharges = $this->apply_stacking_rules($applied_surcharges, $surcharges_config);

        return $applied_surcharges;
    }

    /**
     * Check if surcharge conditions are met
     *
     * @param array $surcharge Surcharge configuration.
     * @param array $package Package data.
     * @return bool
     */
    private function check_surcharge_conditions($surcharge, $package) {
        // Check attribute-based conditions
        if (!empty($surcharge['attribute_conditions'])) {
            $has_matching_attribute = false;
            
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                
                foreach ($surcharge['attribute_conditions'] as $attr_slug => $attr_value) {
                    $product_attr = $product->get_attribute($attr_slug);
                    
                    if ($product_attr === $attr_value) {
                        $has_matching_attribute = true;
                        break 2;
                    }
                }
            }
            
            if (!$has_matching_attribute) {
                return false;
            }
        }

        // Check weight conditions
        if (!empty($surcharge['weight_min']) || !empty($surcharge['weight_max'])) {
            $package_weight = 0;
            
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                $package_weight += (float) $product->get_weight() * $item['quantity'];
            }

            if (!empty($surcharge['weight_min']) && $package_weight < $surcharge['weight_min']) {
                return false;
            }
            
            if (!empty($surcharge['weight_max']) && $package_weight > $surcharge['weight_max']) {
                return false;
            }
        }

        // Check cart value conditions
        if (!empty($surcharge['cart_value_min']) || !empty($surcharge['cart_value_max'])) {
            $cart_total = WC()->cart->get_subtotal();

            if (!empty($surcharge['cart_value_min']) && $cart_total < $surcharge['cart_value_min']) {
                return false;
            }
            
            if (!empty($surcharge['cart_value_max']) && $cart_total > $surcharge['cart_value_max']) {
                return false;
            }
        }

        // Check quantity conditions
        if (!empty($surcharge['qty_min']) || !empty($surcharge['qty_max'])) {
            $total_qty = array_sum(wp_list_pluck($package['contents'], 'quantity'));

            if (!empty($surcharge['qty_min']) && $total_qty < $surcharge['qty_min']) {
                return false;
            }
            
            if (!empty($surcharge['qty_max']) && $total_qty > $surcharge['qty_max']) {
                return false;
            }
        }

        // Check country conditions
        if (!empty($surcharge['countries'])) {
            $destination_country = $package['destination']['country'];
            
            if (!in_array($destination_country, $surcharge['countries'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate surcharge amount
     *
     * @param array $surcharge Surcharge configuration.
     * @param array $package Package data.
     * @return float
     */
    private function calculate_surcharge_amount($surcharge, $package) {
        $amount = 0;

        if (isset($surcharge['amount_type']) && $surcharge['amount_type'] === 'tiered') {
            // Tiered pricing
            if (!empty($surcharge['tiers'])) {
                $package_weight = 0;
                
                foreach ($package['contents'] as $item) {
                    $product = $item['data'];
                    $package_weight += (float) $product->get_weight() * $item['quantity'];
                }

                foreach ($surcharge['tiers'] as $tier) {
                    if ($package_weight >= $tier['min'] && ($tier['max'] === '' || $package_weight <= $tier['max'])) {
                        $amount = (float) $tier['amount'];
                        break;
                    }
                }
            }
        } else {
            // Flat amount
            $amount = isset($surcharge['amount']) ? (float) $surcharge['amount'] : 0;
        }

        // Apply cap if set
        if (!empty($surcharge['cap']) && $amount > $surcharge['cap']) {
            $amount = (float) $surcharge['cap'];
        }

        return $amount;
    }

    /**
     * Apply stacking rules to surcharges
     *
     * @param array $applied_surcharges Applied surcharges.
     * @param array $surcharges_config Surcharges configuration.
     * @return array
     */
    private function apply_stacking_rules($applied_surcharges, $surcharges_config) {
        if (empty($applied_surcharges)) {
            return $applied_surcharges;
        }

        // Get stacking mode from first surcharge (should be global setting)
        $stacking_mode = 'add'; // Default: add all surcharges

        foreach ($surcharges_config as $config) {
            if (isset($config['stacking'])) {
                $stacking_mode = $config['stacking'];
                break;
            }
        }

        switch ($stacking_mode) {
            case 'max':
                // Keep only the highest surcharge
                usort($applied_surcharges, function($a, $b) {
                    return $b['amount'] - $a['amount'];
                });
                return array($applied_surcharges[0]);

            case 'first_match':
                // Keep only the first matching surcharge
                return array($applied_surcharges[0]);

            case 'add':
            default:
                // Add all surcharges
                return $applied_surcharges;
        }
    }

    /**
     * Add surcharges to cart
     */
    public function add_surcharges_to_cart() {
        if (!WC()->cart) {
            return;
        }

        // Get shipping packages
        $packages = WC()->shipping()->get_packages();

        foreach ($packages as $package_key => $package) {
            // Use Calculator's calculate_surcharges which supports shipping class conditions
            $surcharges = WLM_Core::instance()->calculator->calculate_surcharges($package);

            foreach ($surcharges as $surcharge) {
                // Check if should apply to express
                $is_express = WC()->session && WC()->session->get('wlm_express_selected');
                $apply_to_express = true; // Default

                // Get surcharge config
                $surcharges_config = get_option('wlm_surcharges', array());
                foreach ($surcharges_config as $config) {
                    if ($config['id'] === $surcharge['id']) {
                        $apply_to_express = isset($config['apply_to_express']) ? $config['apply_to_express'] : true;
                        break;
                    }
                }

                if ($is_express && !$apply_to_express) {
                    continue;
                }

                WC()->cart->add_fee(
                    $surcharge['name'],
                    $surcharge['amount'],
                    $surcharge['taxable'],
                    $surcharge['tax_class']
                );
            }
        }
    }

    /**
     * Get configured surcharges
     *
     * @return array
     */
    public function get_surcharges() {
        return get_option('wlm_surcharges', array());
    }

    /**
     * Save surcharges
     *
     * @param array $surcharges Surcharges.
     * @return bool
     */
    public function save_surcharges($surcharges) {
        return update_option('wlm_surcharges', $surcharges);
    }

    /**
     * Add new surcharge
     *
     * @param array $surcharge Surcharge data.
     * @return bool
     */
    public function add_surcharge($surcharge) {
        $surcharges = $this->get_surcharges();
        $surcharge['id'] = uniqid('wlm_surcharge_');
        $surcharges[] = $surcharge;
        return $this->save_surcharges($surcharges);
    }

    /**
     * Update surcharge
     *
     * @param string $surcharge_id Surcharge ID.
     * @param array $surcharge Surcharge data.
     * @return bool
     */
    public function update_surcharge($surcharge_id, $surcharge) {
        $surcharges = $this->get_surcharges();
        
        foreach ($surcharges as $key => $existing_surcharge) {
            if ($existing_surcharge['id'] === $surcharge_id) {
                $surcharges[$key] = array_merge($existing_surcharge, $surcharge);
                return $this->save_surcharges($surcharges);
            }
        }
        
        return false;
    }

    /**
     * Delete surcharge
     *
     * @param string $surcharge_id Surcharge ID.
     * @return bool
     */
    public function delete_surcharge($surcharge_id) {
        $surcharges = $this->get_surcharges();
        
        foreach ($surcharges as $key => $surcharge) {
            if ($surcharge['id'] === $surcharge_id) {
                unset($surcharges[$key]);
                return $this->save_surcharges(array_values($surcharges));
            }
        }
        
        return false;
    }
}
