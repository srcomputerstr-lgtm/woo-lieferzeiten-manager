<?php
/**
 * REST API class for ERP integration
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_REST_API {
    /**
     * API namespace
     */
    const NAMESPACE = 'wlm/v1';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Set product availability
        register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)/availability', array(
            'methods' => 'POST',
            'callback' => array($this, 'set_product_availability'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'available_from' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                )
            )
        ));

        // Set product lead time
        register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)/lead-time', array(
            'methods' => 'POST',
            'callback' => array($this, 'set_product_lead_time'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'lead_time_days' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                )
            )
        ));

        // Batch update products
        register_rest_route(self::NAMESPACE, '/products/batch', array(
            'methods' => 'POST',
            'callback' => array($this, 'batch_update_products'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'products' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                )
            )
        ));

        // Get product delivery info
        register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)/delivery-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product_delivery_info'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // SKU-based endpoints for ERP integration
        
        // Set product availability by SKU
        register_rest_route(self::NAMESPACE, '/products/sku/(?P<sku>[a-zA-Z0-9_-]+)/availability', array(
            'methods' => 'POST',
            'callback' => array($this, 'set_product_availability_by_sku'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'sku' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'available_from' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'lead_time_days' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                )
            )
        ));

        // Batch update products by SKU
        register_rest_route(self::NAMESPACE, '/products/sku/batch', array(
            'methods' => 'POST',
            'callback' => array($this, 'batch_update_products_by_sku'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'products' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                )
            )
        ));

        // Get product delivery info by SKU
        register_rest_route(self::NAMESPACE, '/products/sku/(?P<sku>[a-zA-Z0-9_-]+)/delivery-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product_delivery_info_by_sku'),
            'permission_callback' => '__return_true',
            'args' => array(
                'sku' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    /**
     * Check API permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_permission($request) {
        return current_user_can('edit_products');
    }

    /**
     * Set product availability
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function set_product_availability($request) {
        $product_id = (int) $request->get_param('id');
        $available_from = sanitize_text_field($request->get_param('available_from'));

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
            ), 404);
        }

        update_post_meta($product_id, '_wlm_available_from', $available_from);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Verfügbarkeitsdatum aktualisiert', 'woo-lieferzeiten-manager'),
            'data' => array(
                'product_id' => $product_id,
                'available_from' => $available_from
            )
        ), 200);
    }

    /**
     * Set product lead time
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function set_product_lead_time($request) {
        $product_id = (int) $request->get_param('id');
        $lead_time_days = (int) $request->get_param('lead_time_days');

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
            ), 404);
        }

        update_post_meta($product_id, '_wlm_lead_time_days', $lead_time_days);

        // Auto-calculate available from date
        $calculator = WLM_Core::instance()->calculator;
        $available_from = $calculator->calculate_available_from_date($lead_time_days);
        update_post_meta($product_id, '_wlm_available_from', $available_from);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Lieferzeit aktualisiert', 'woo-lieferzeiten-manager'),
            'data' => array(
                'product_id' => $product_id,
                'lead_time_days' => $lead_time_days,
                'available_from' => $available_from
            )
        ), 200);
    }

    /**
     * Batch update products
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function batch_update_products($request) {
        $products = $request->get_param('products');
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        foreach ($products as $product_data) {
            if (!isset($product_data['id'])) {
                $results['failed'][] = array(
                    'data' => $product_data,
                    'message' => __('Produkt-ID fehlt', 'woo-lieferzeiten-manager')
                );
                continue;
            }

            $product_id = (int) $product_data['id'];
            $product = wc_get_product($product_id);

            if (!$product) {
                $results['failed'][] = array(
                    'id' => $product_id,
                    'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
                );
                continue;
            }

            // Update available from
            if (isset($product_data['available_from'])) {
                update_post_meta($product_id, '_wlm_available_from', sanitize_text_field($product_data['available_from']));
            }

            // Update lead time
            if (isset($product_data['lead_time_days'])) {
                $lead_time = (int) $product_data['lead_time_days'];
                update_post_meta($product_id, '_wlm_lead_time_days', $lead_time);

                // Auto-calculate available from if not provided
                if (!isset($product_data['available_from'])) {
                    $calculator = WLM_Core::instance()->calculator;
                    $available_from = $calculator->calculate_available_from_date($lead_time);
                    update_post_meta($product_id, '_wlm_available_from', $available_from);
                }
            }

            $results['success'][] = array(
                'id' => $product_id,
                'message' => __('Erfolgreich aktualisiert', 'woo-lieferzeiten-manager')
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => sprintf(
                __('%d Produkte aktualisiert, %d fehlgeschlagen', 'woo-lieferzeiten-manager'),
                count($results['success']),
                count($results['failed'])
            ),
            'results' => $results
        ), 200);
    }

    /**
     * Get product delivery info
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_product_delivery_info($request) {
        $product_id = (int) $request->get_param('id');
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
            ), 404);
        }

        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_product_window($product_id);

        $data = array(
            'product_id' => $product_id,
            'available_from' => get_post_meta($product_id, '_wlm_available_from', true),
            'lead_time_days' => get_post_meta($product_id, '_wlm_lead_time_days', true),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'delivery_window' => $window
        );

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data
        ), 200);
    }

    /**
     * Helper: Get product ID by SKU
     *
     * @param string $sku Product SKU.
     * @return int|false Product ID or false if not found.
     */
    private function get_product_id_by_sku($sku) {
        global $wpdb;
        
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value=%s LIMIT 1",
            $sku
        ));
        
        return $product_id ? (int) $product_id : false;
    }

    /**
     * Set product availability by SKU
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function set_product_availability_by_sku($request) {
        $sku = sanitize_text_field($request->get_param('sku'));
        $available_from = $request->get_param('available_from');
        $lead_time_days = $request->get_param('lead_time_days');

        error_log('[WLM API] SKU-based update request:');
        error_log('[WLM API] - SKU: ' . $sku);
        error_log('[WLM API] - available_from: ' . var_export($available_from, true));
        error_log('[WLM API] - lead_time_days: ' . var_export($lead_time_days, true));

        // Find product by SKU
        $product_id = $this->get_product_id_by_sku($sku);
        error_log('[WLM API] - Found product_id: ' . var_export($product_id, true));
        
        if (!$product_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => sprintf(__('Produkt mit SKU "%s" nicht gefunden', 'woo-lieferzeiten-manager'), $sku)
            ), 404);
        }

        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
            ), 404);
        }

        $updated_fields = array();

        // Update available_from if provided
        if ($available_from !== null) {
            error_log('[WLM API] Updating available_from to: ' . $available_from);
            $result = update_post_meta($product_id, '_wlm_available_from', $available_from);
            error_log('[WLM API] update_post_meta result: ' . var_export($result, true));
            $updated_fields['available_from'] = $available_from;
        }

        // Update lead_time_days if provided
        if ($lead_time_days !== null) {
            error_log('[WLM API] Updating lead_time_days to: ' . $lead_time_days);
            $result = update_post_meta($product_id, '_wlm_lead_time_days', (int) $lead_time_days);
            error_log('[WLM API] update_post_meta result: ' . var_export($result, true));
            $updated_fields['lead_time_days'] = (int) $lead_time_days;
        }

        error_log('[WLM API] Updated fields: ' . print_r($updated_fields, true));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Produkt aktualisiert', 'woo-lieferzeiten-manager'),
            'data' => array(
                'product_id' => $product_id,
                'sku' => $sku,
                'updated_fields' => $updated_fields
            )
        ), 200);
    }

    /**
     * Batch update products by SKU
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function batch_update_products_by_sku($request) {
        $products = $request->get_param('products');
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        foreach ($products as $product_data) {
            if (!isset($product_data['sku'])) {
                $results['failed'][] = array(
                    'sku' => 'UNKNOWN',
                    'message' => __('SKU fehlt', 'woo-lieferzeiten-manager')
                );
                continue;
            }

            $sku = sanitize_text_field($product_data['sku']);
            $product_id = $this->get_product_id_by_sku($sku);

            if (!$product_id) {
                $results['failed'][] = array(
                    'sku' => $sku,
                    'message' => sprintf(__('Produkt mit SKU "%s" nicht gefunden', 'woo-lieferzeiten-manager'), $sku)
                );
                continue;
            }

            $product = wc_get_product($product_id);
            
            if (!$product) {
                $results['failed'][] = array(
                    'sku' => $sku,
                    'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
                );
                continue;
            }

            // Update fields
            if (isset($product_data['available_from'])) {
                $available_from = sanitize_text_field($product_data['available_from']);
                update_post_meta($product_id, '_wlm_available_from', $available_from);
            }

            if (isset($product_data['lead_time_days'])) {
                $lead_time = (int) $product_data['lead_time_days'];
                update_post_meta($product_id, '_wlm_lead_time_days', $lead_time);
            }

            $results['success'][] = array(
                'sku' => $sku,
                'product_id' => $product_id,
                'message' => __('Erfolgreich aktualisiert', 'woo-lieferzeiten-manager')
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => sprintf(
                __('%d Produkte aktualisiert, %d fehlgeschlagen', 'woo-lieferzeiten-manager'),
                count($results['success']),
                count($results['failed'])
            ),
            'results' => $results
        ), 200);
    }

    /**
     * Get product delivery info by SKU
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_product_delivery_info_by_sku($request) {
        $sku = sanitize_text_field($request->get_param('sku'));
        $product_id = $this->get_product_id_by_sku($sku);

        if (!$product_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => sprintf(__('Produkt mit SKU "%s" nicht gefunden', 'woo-lieferzeiten-manager'), $sku)
            ), 404);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Produkt nicht gefunden', 'woo-lieferzeiten-manager')
            ), 404);
        }

        $calculator = WLM_Core::instance()->calculator;
        $window = $calculator->calculate_product_window($product_id);

        $data = array(
            'product_id' => $product_id,
            'sku' => $sku,
            'available_from' => get_post_meta($product_id, '_wlm_available_from', true),
            'calculated_available_date' => get_post_meta($product_id, '_wlm_calculated_available_date', true),
            'lead_time_days' => get_post_meta($product_id, '_wlm_lead_time_days', true),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'delivery_window' => $window
        );

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data
        ), 200);
    }

}
