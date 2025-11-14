<?php
/**
 * Product fields class for custom meta fields
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Product_Fields {
    /**
     * Constructor
     */
    public function __construct() {
        // Add product fields
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_admin_process_product_object', array($this, 'save_product_fields'));

        // Add variation fields
        add_action('woocommerce_variation_options_pricing', array($this, 'add_variation_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_fields'), 10, 2);
    }

    /**
     * Add product fields to inventory tab
     */
    public function add_product_fields() {
        global $product_object;

        echo '<div class="options_group wlm-product-fields">';
        echo '<h3>' . esc_html__('Lieferzeiten', 'woo-lieferzeiten-manager') . '</h3>';

        // Available from date
        woocommerce_wp_text_input(array(
            'id' => '_wlm_available_from',
            'label' => __('Lieferbar ab', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Datum, ab dem das Produkt verfügbar ist (Format: YYYY-MM-DD). Wird automatisch berechnet, wenn nicht gesetzt.', 'woo-lieferzeiten-manager'),
            'type' => 'date',
            'value' => get_post_meta($product_object->get_id(), '_wlm_available_from', true)
        ));

        // Lead time in days
        woocommerce_wp_text_input(array(
            'id' => '_wlm_lead_time_days',
            'label' => __('Lieferzeit (Tage)', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Anzahl der Werktage bis zur Verfügbarkeit. Wird zur automatischen Berechnung des "Berechnetes Verfügbarkeitsdatum" verwendet.', 'woo-lieferzeiten-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            ),
            'value' => get_post_meta($product_object->get_id(), '_wlm_lead_time_days', true)
        ));
        
        // Calculated availability date (read-only)
        $calculated_date = get_post_meta($product_object->get_id(), '_wlm_calculated_available_date', true);
        $calculated_date_display = $calculated_date ? date_i18n(get_option('date_format'), strtotime($calculated_date)) : __('Nicht berechnet', 'woo-lieferzeiten-manager');
        
        woocommerce_wp_text_input(array(
            'id' => '_wlm_calculated_available_date_display',
            'label' => __('Berechnetes Verfügbarkeitsdatum', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Automatisch berechnet basierend auf "Lieferzeit (Tage)". Wird täglich per Cronjob aktualisiert. Nur-Lesen.', 'woo-lieferzeiten-manager'),
            'type' => 'text',
            'custom_attributes' => array(
                'readonly' => 'readonly',
                'style' => 'background-color: #f0f0f0; cursor: not-allowed;'
            ),
            'value' => $calculated_date_display
        ));

        // Max visible stock
        woocommerce_wp_text_input(array(
            'id' => '_wlm_max_visible_stock',
            'label' => __('Maximal sichtbarer Bestand', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Maximale Anzahl, die im Frontend angezeigt wird. Leer lassen für globale Einstellung.', 'woo-lieferzeiten-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            ),
            'value' => get_post_meta($product_object->get_id(), '_wlm_max_visible_stock', true)
        ));

        echo '</div>';
    }

    /**
     * Save product fields
     *
     * @param WC_Product $product Product object.
     */
    public function save_product_fields($product) {
        // Save available from date
        if (isset($_POST['_wlm_available_from'])) {
            $available_from = sanitize_text_field($_POST['_wlm_available_from']);
            $product->update_meta_data('_wlm_available_from', $available_from);
        }

        // Save lead time
        if (isset($_POST['_wlm_lead_time_days'])) {
            $lead_time = absint($_POST['_wlm_lead_time_days']);
            $product->update_meta_data('_wlm_lead_time_days', $lead_time);

            // Auto-calculate available from if not manually set
            if (empty($_POST['_wlm_available_from']) && $lead_time > 0) {
                $calculator = new WLM_Calculator();
                $available_from = $calculator->calculate_available_from_date($lead_time);
                $product->update_meta_data('_wlm_available_from', $available_from);
            }
        }

        // Save max visible stock
        if (isset($_POST['_wlm_max_visible_stock'])) {
            $max_visible = absint($_POST['_wlm_max_visible_stock']);
            $product->update_meta_data('_wlm_max_visible_stock', $max_visible);
        }
    }

    /**
     * Add variation fields
     *
     * @param int $loop Loop index.
     * @param array $variation_data Variation data.
     * @param WP_Post $variation Variation post object.
     */
    public function add_variation_fields($loop, $variation_data, $variation) {
        echo '<div class="wlm-variation-fields">';

        // Available from date
        woocommerce_wp_text_input(array(
            'id' => '_wlm_available_from[' . $loop . ']',
            'name' => '_wlm_available_from[' . $loop . ']',
            'label' => __('Lieferbar ab', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Datum, ab dem die Variante verfügbar ist (Format: YYYY-MM-DD).', 'woo-lieferzeiten-manager'),
            'type' => 'date',
            'value' => get_post_meta($variation->ID, '_wlm_available_from', true),
            'wrapper_class' => 'form-row form-row-full'
        ));

        // Lead time in days
        woocommerce_wp_text_input(array(
            'id' => '_wlm_lead_time_days[' . $loop . ']',
            'name' => '_wlm_lead_time_days[' . $loop . ']',
            'label' => __('Lieferzeit (Tage)', 'woo-lieferzeiten-manager'),
            'desc_tip' => true,
            'description' => __('Anzahl der Werktage bis zur Verfügbarkeit.', 'woo-lieferzeiten-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            ),
            'value' => get_post_meta($variation->ID, '_wlm_lead_time_days', true),
            'wrapper_class' => 'form-row form-row-full'
        ));

        echo '</div>';
    }

    /**
     * Save variation fields
     *
     * @param int $variation_id Variation ID.
     * @param int $loop Loop index.
     */
    public function save_variation_fields($variation_id, $loop) {
        // Save available from date
        if (isset($_POST['_wlm_available_from'][$loop])) {
            $available_from = sanitize_text_field($_POST['_wlm_available_from'][$loop]);
            update_post_meta($variation_id, '_wlm_available_from', $available_from);
        }

        // Save lead time
        if (isset($_POST['_wlm_lead_time_days'][$loop])) {
            $lead_time = absint($_POST['_wlm_lead_time_days'][$loop]);
            update_post_meta($variation_id, '_wlm_lead_time_days', $lead_time);

            // Auto-calculate available from if not manually set
            if (empty($_POST['_wlm_available_from'][$loop]) && $lead_time > 0) {
                $calculator = new WLM_Calculator();
                $available_from = $calculator->calculate_available_from_date($lead_time);
                update_post_meta($variation_id, '_wlm_available_from', $available_from);
            }
        }
    }
}
