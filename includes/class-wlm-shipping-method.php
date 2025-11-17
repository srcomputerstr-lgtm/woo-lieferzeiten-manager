<?php
/**
 * Custom WooCommerce Shipping Method
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Shipping_Method extends WC_Shipping_Method {
    /**
     * Constructor
     *
     * @param int $instance_id Instance ID.
     */
    public function __construct($instance_id = 0) {
        $this->id = 'wlm_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('WLM Versandart', 'woo-lieferzeiten-manager');
        $this->method_description = __('Konfigurierbare Versandart mit Lieferzeitberechnung', 'woo-lieferzeiten-manager');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal'
        );

        $this->init();
    }

    /**
     * Initialize settings
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Aktivieren', 'woo-lieferzeiten-manager'),
                'type' => 'checkbox',
                'label' => __('Diese Versandart aktivieren', 'woo-lieferzeiten-manager'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Titel', 'woo-lieferzeiten-manager'),
                'type' => 'text',
                'description' => __('Titel, der dem Kunden angezeigt wird', 'woo-lieferzeiten-manager'),
                'default' => __('Standardversand', 'woo-lieferzeiten-manager'),
                'desc_tip' => true
            ),
            'cost_type' => array(
                'title' => __('Kostentyp', 'woo-lieferzeiten-manager'),
                'type' => 'select',
                'default' => 'flat',
                'options' => array(
                    'flat' => __('Pauschal', 'woo-lieferzeiten-manager'),
                    'by_weight' => __('Nach Gewicht', 'woo-lieferzeiten-manager'),
                    'by_qty' => __('Nach Stückzahl', 'woo-lieferzeiten-manager')
                )
            ),
            'cost' => array(
                'title' => __('Kosten', 'woo-lieferzeiten-manager'),
                'type' => 'price',
                'description' => __('Versandkosten (netto)', 'woo-lieferzeiten-manager'),
                'default' => '0',
                'desc_tip' => true
            ),
            'weight_min' => array(
                'title' => __('Min. Gewicht (kg)', 'woo-lieferzeiten-manager'),
                'type' => 'number',
                'description' => __('Minimales Gewicht für diese Versandart', 'woo-lieferzeiten-manager'),
                'default' => '',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'weight_max' => array(
                'title' => __('Max. Gewicht (kg)', 'woo-lieferzeiten-manager'),
                'type' => 'number',
                'description' => __('Maximales Gewicht für diese Versandart', 'woo-lieferzeiten-manager'),
                'default' => '',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'transit_min' => array(
                'title' => __('Transit Min (Werktage)', 'woo-lieferzeiten-manager'),
                'type' => 'number',
                'description' => __('Minimale Transitzeit in Werktagen', 'woo-lieferzeiten-manager'),
                'default' => '1',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'transit_max' => array(
                'title' => __('Transit Max (Werktage)', 'woo-lieferzeiten-manager'),
                'type' => 'number',
                'description' => __('Maximale Transitzeit in Werktagen', 'woo-lieferzeiten-manager'),
                'default' => '3',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'priority' => array(
                'title' => __('Priorität', 'woo-lieferzeiten-manager'),
                'type' => 'number',
                'description' => __('Niedrigere Zahl = höhere Priorität', 'woo-lieferzeiten-manager'),
                'default' => '10',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0'
                ),
                'desc_tip' => true
            ),
            'express_enabled' => array(
                'title' => __('Express aktivieren', 'woo-lieferzeiten-manager'),
                'type' => 'checkbox',
                'label' => __('Express-Option für diese Versandart aktivieren', 'woo-lieferzeiten-manager'),
                'default' => 'no'
            ),
            'express_cost' => array(
                'title' => __('Express-Zuschlag', 'woo-lieferzeiten-manager'),
                'type' => 'price',
                'description' => __('Zusätzliche Kosten für Express-Versand', 'woo-lieferzeiten-manager'),
                'default' => '9.90',
                'desc_tip' => true
            ),
            'express_cutoff' => array(
                'title' => __('Express Cutoff-Zeit', 'woo-lieferzeiten-manager'),
                'type' => 'text',
                'description' => __('Cutoff-Zeit für Express (HH:MM)', 'woo-lieferzeiten-manager'),
                'default' => '14:00',
                'desc_tip' => true
            )
        );
    }

    /**
     * Calculate shipping cost
     *
     * @param array $package Package data.
     */
    public function calculate_shipping($package = array()) {
        $cost_type = $this->get_option('cost_type', 'flat');
        $base_cost = (float) $this->get_option('cost', 0);
        $weight_min = (float) $this->get_option('weight_min', 0);
        $weight_max = (float) $this->get_option('weight_max', 0);

        // Calculate package weight
        $package_weight = 0;
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $package_weight += (float) $product->get_weight() * $item['quantity'];
        }

        // Check weight constraints
        if ($weight_min > 0 && $package_weight < $weight_min) {
            return;
        }
        if ($weight_max > 0 && $package_weight > $weight_max) {
            return;
        }
        
        // Check product attribute conditions
        $method_data = array(
            'attribute_conditions' => $this->get_option('attribute_conditions', array()),
            'required_categories' => $this->get_option('required_categories', array())
        );
        
        WLM_Core::log('[WLM Shipping] Checking conditions for method: ' . $this->id . ', attribute_conditions: ' . print_r($method_data['attribute_conditions'], true));
        
        // Check each product in cart against conditions
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $calculator = WLM_Core::instance()->calculator;
            
            if (!$calculator->check_product_conditions($product, $method_data)) {
                WLM_Core::log('[WLM Shipping] Product ' . $product->get_name() . ' does not meet conditions - hiding method');
                return; // Don't offer this shipping method
            }
        }

        // Calculate cost based on type
        $cost = $base_cost;

        if ($cost_type === 'by_weight') {
            $cost = $base_cost * $package_weight;
        } elseif ($cost_type === 'by_qty') {
            $qty = array_sum(wp_list_pluck($package['contents'], 'quantity'));
            $cost = $base_cost * $qty;
        }

        // Surcharges are added as separate cart fees, not included in shipping cost
        
        // Add normal rate
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package
        );
        $this->add_rate($rate);
        
        // TESTING: Add express rate ALWAYS (no conditions)
        $express_cost = 10; // Hardcoded for testing
        
        $express_rate = array(
            'id' => $this->get_rate_id() . ':express',
            'label' => $this->title . ' - Express (TEST)',
            'cost' => $cost + $express_cost,
            'package' => $package
        );
        $this->add_rate($express_rate);
    }
}
