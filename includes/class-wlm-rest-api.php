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
                    'validate_callback' => function($param, $request, $key) {
                        // Allow null/empty for optional parameter
                        if ($param === null || $param === '') {
                            return true;
                        }
                        // Accept YYYY-MM-DD format
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
                            return true;
                        }
                        // Accept German format: DD.MM.YYYY or DD.MM.YYYY HH:MM:SS
                        if (preg_match('/^\d{2}\.\d{2}\.\d{4}(\s+\d{2}:\d{2}:\d{2})?$/', $param)) {
                            return true;
                        }
                        return false;
                    },
                    'sanitize_callback' => array($this, 'sanitize_date_param')
                ),
                'lead_time_days' => array(
                    'required' => false,
                    'validate_callback' => function($param, $request, $key) {
                        // Allow null for optional parameter
                        if ($param === null || $param === '') {
                            return true;
                        }
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
        
        // Get order delivery timeframe
        register_rest_route(self::NAMESPACE, '/orders/(?P<id>\d+)/delivery-timeframe', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_delivery_timeframe'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get multiple orders delivery timeframes
        register_rest_route(self::NAMESPACE, '/orders/delivery-timeframes', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders_delivery_timeframes'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'order_ids' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        if (empty($param)) return true;
                        $ids = explode(',', $param);
                        foreach ($ids as $id) {
                            if (!is_numeric(trim($id))) return false;
                        }
                        return true;
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'status' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'date_from' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'date_to' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'required' => false,
                    'default' => 100,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 1000;
                    }
                )
            )
        ));
        
        // Get ship-by date only
        register_rest_route(self::NAMESPACE, '/orders/(?P<id>\d+)/ship-by-date', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ship_by_date'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get earliest delivery date only
        register_rest_route(self::NAMESPACE, '/orders/(?P<id>\d+)/earliest-delivery', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_earliest_delivery'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get latest delivery date only
        register_rest_route(self::NAMESPACE, '/orders/(?P<id>\d+)/latest-delivery', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_latest_delivery'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get delivery window (formatted) only
        register_rest_route(self::NAMESPACE, '/orders/(?P<id>\d+)/delivery-window', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_delivery_window'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get orders that need to be shipped by a specific date
        register_rest_route(self::NAMESPACE, '/orders/ship-by/(?P<date>[0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders_by_ship_date'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'date' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'status' => array(
                    'required' => false,
                    'default' => 'processing',
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
        
        // DEBUG: Log all request data
        WLM_Core::log('[WLM API DEBUG] Request method: ' . $request->get_method());
        WLM_Core::log('[WLM API DEBUG] Request route: ' . $request->get_route());
        WLM_Core::log('[WLM API DEBUG] Content-Type: ' . $request->get_content_type());
        WLM_Core::log('[WLM API DEBUG] Body params: ' . print_r($request->get_body_params(), true));
        WLM_Core::log('[WLM API DEBUG] JSON params: ' . print_r($request->get_json_params(), true));
        WLM_Core::log('[WLM API DEBUG] All params: ' . print_r($request->get_params(), true));
        WLM_Core::log('[WLM API DEBUG] Raw body: ' . $request->get_body());
        
        // Get parameters from JSON body - manually parse since get_json_params() doesn't work with URL params
        $raw_body = $request->get_body();
        $json_params = json_decode($raw_body, true);
        
        WLM_Core::log('[WLM API DEBUG] Parsed JSON: ' . print_r($json_params, true));
        
        $available_from = isset($json_params['available_from']) ? $json_params['available_from'] : null;
        $lead_time_days = isset($json_params['lead_time_days']) ? $json_params['lead_time_days'] : null;
        
        // Sanitize available_from if provided
        if ($available_from !== null) {
            $available_from = $this->sanitize_date_param($available_from);
        }
        
        // Sanitize lead_time_days if provided
        if ($lead_time_days !== null) {
            $lead_time_days = (int) $lead_time_days;
        }

        WLM_Core::log('[WLM API] SKU-based update request:');
        WLM_Core::log('[WLM API] - SKU: ' . $sku);
        WLM_Core::log('[WLM API] - available_from: ' . var_export($available_from, true));
        WLM_Core::log('[WLM API] - lead_time_days: ' . var_export($lead_time_days, true));

        // Find product by SKU
        $product_id = $this->get_product_id_by_sku($sku);
        WLM_Core::log('[WLM API] - Found product_id: ' . var_export($product_id, true));
        
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
            WLM_Core::log('[WLM API] Updating available_from to: ' . $available_from);
            $result = update_post_meta($product_id, '_wlm_available_from', $available_from);
            WLM_Core::log('[WLM API] update_post_meta result: ' . var_export($result, true));
            $updated_fields['available_from'] = $available_from;
        }

        // Update lead_time_days if provided
        if ($lead_time_days !== null) {
            WLM_Core::log('[WLM API] Updating lead_time_days to: ' . $lead_time_days);
            $result = update_post_meta($product_id, '_wlm_lead_time_days', (int) $lead_time_days);
            WLM_Core::log('[WLM API] update_post_meta result: ' . var_export($result, true));
            $updated_fields['lead_time_days'] = (int) $lead_time_days;
        }

        WLM_Core::log('[WLM API] Updated fields: ' . print_r($updated_fields, true));

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

    /**
     * Sanitize date parameter - converts German format to YYYY-MM-DD
     *
     * @param string $date Date string in various formats.
     * @return string|null Sanitized date in YYYY-MM-DD format or null.
     */
    public function sanitize_date_param($date) {
        if (empty($date)) {
            return null;
        }

        WLM_Core::log('[WLM API] sanitize_date_param input: ' . $date);

        // Already in YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            WLM_Core::log('[WLM API] Date already in YYYY-MM-DD format: ' . $date);
            return $date;
        }

        // German format: DD.MM.YYYY or DD.MM.YYYY HH:MM:SS
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})(\s+\d{2}:\d{2}:\d{2})?$/', $date, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $converted = sprintf('%s-%s-%s', $year, $month, $day);
            WLM_Core::log('[WLM API] Converted German date to: ' . $converted);
            return $converted;
        }

        WLM_Core::log('[WLM API] Could not parse date format: ' . $date);
        return null;
    }
    
    /**
     * Get order delivery timeframe
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_order_delivery_timeframe($request) {
        $order_id = $request->get_param('id');
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error(
                'order_not_found',
                'Order not found',
                array('status' => 404)
            );
        }
        
        $earliest = $order->get_meta('_wlm_earliest_delivery');
        $latest = $order->get_meta('_wlm_latest_delivery');
        $window = $order->get_meta('_wlm_delivery_window');
        $method_name = $order->get_meta('_wlm_shipping_method_name');
        
        // Get shipping method from order
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method = !empty($shipping_methods) ? reset($shipping_methods) : null;
        
        $response = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : null,
            'earliest_delivery' => $earliest ?: null,
            'latest_delivery' => $latest ?: null,
            'delivery_window' => $window ?: null,
            'shipping_method' => array(
                'id' => $shipping_method ? $shipping_method->get_method_id() : null,
                'name' => $method_name ?: ($shipping_method ? $shipping_method->get_name() : null)
            )
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get multiple orders delivery timeframes
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_orders_delivery_timeframes($request) {
        $order_ids = $request->get_param('order_ids');
        $status = $request->get_param('status');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $limit = $request->get_param('limit') ?: 100;
        
        $args = array(
            'limit' => $limit,
            'return' => 'ids'
        );
        
        // Filter by specific order IDs
        if (!empty($order_ids)) {
            $ids = array_map('trim', explode(',', $order_ids));
            $args['post__in'] = $ids;
        }
        
        // Filter by status
        if (!empty($status)) {
            $args['status'] = $status;
        }
        
        // Filter by date range
        if (!empty($date_from)) {
            $args['date_created'] = '>=' . $date_from;
        }
        if (!empty($date_to)) {
            $args['date_created'] = '<=' . $date_to . ' 23:59:59';
        }
        
        $orders = wc_get_orders($args);
        
        $results = array();
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                continue;
            }
            
            $earliest = $order->get_meta('_wlm_earliest_delivery');
            $latest = $order->get_meta('_wlm_latest_delivery');
            $window = $order->get_meta('_wlm_delivery_window');
            $method_name = $order->get_meta('_wlm_shipping_method_name');
            
            // Only include orders with delivery timeframe data
            if (empty($earliest) && empty($latest)) {
                continue;
            }
            
            $shipping_methods = $order->get_shipping_methods();
            $shipping_method = !empty($shipping_methods) ? reset($shipping_methods) : null;
            
            $results[] = array(
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_status' => $order->get_status(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : null,
                'earliest_delivery' => $earliest ?: null,
                'latest_delivery' => $latest ?: null,
                'delivery_window' => $window ?: null,
                'shipping_method' => array(
                    'id' => $shipping_method ? $shipping_method->get_method_id() : null,
                    'name' => $method_name ?: ($shipping_method ? $shipping_method->get_name() : null)
                ),
                'customer' => array(
                    'email' => $order->get_billing_email(),
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name()
                )
            );
        }
        
        $response = array(
            'total' => count($results),
            'limit' => $limit,
            'orders' => $results
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get ship-by date only
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_ship_by_date($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $ship_by_date = $order->get_meta('_wlm_ship_by_date');
        
        if (!$ship_by_date) {
            return new WP_Error('no_data', 'No ship-by date found for this order', array('status' => 404));
        }
        
        return rest_ensure_response($ship_by_date);
    }
    
    /**
     * Get earliest delivery date only
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_earliest_delivery($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $earliest = $order->get_meta('_wlm_earliest_delivery');
        
        if (!$earliest) {
            return new WP_Error('no_data', 'No earliest delivery date found for this order', array('status' => 404));
        }
        
        return rest_ensure_response($earliest);
    }
    
    /**
     * Get latest delivery date only
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_latest_delivery($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $latest = $order->get_meta('_wlm_latest_delivery');
        
        if (!$latest) {
            return new WP_Error('no_data', 'No latest delivery date found for this order', array('status' => 404));
        }
        
        return rest_ensure_response($latest);
    }
    
    /**
     * Get delivery window (formatted) only
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_delivery_window($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $window = $order->get_meta('_wlm_delivery_window');
        
        if (!$window) {
            return new WP_Error('no_data', 'No delivery window found for this order', array('status' => 404));
        }
        
        return rest_ensure_response($window);
    }
    
    /**
     * Get orders that need to be shipped by a specific date
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_orders_by_ship_date($request) {
        $date = $request['date'];
        $status = $request['status'];
        
        $args = array(
            'limit' => -1,
            'status' => $status,
            'meta_query' => array(
                array(
                    'key' => '_wlm_ship_by_date',
                    'value' => $date,
                    'compare' => '<=',
                    'type' => 'DATE',
                ),
            ),
            'orderby' => 'meta_value',
            'meta_key' => '_wlm_ship_by_date',
            'order' => 'ASC',
        );
        
        $orders = wc_get_orders($args);
        
        $result = array();
        foreach ($orders as $order) {
            $result[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'ship_by_date' => $order->get_meta('_wlm_ship_by_date'),
                'earliest_delivery' => $order->get_meta('_wlm_earliest_delivery'),
                'latest_delivery' => $order->get_meta('_wlm_latest_delivery'),
                'delivery_window' => $order->get_meta('_wlm_delivery_window'),
                'shipping_method_name' => $order->get_meta('_wlm_shipping_method_name'),
                'status' => $order->get_status(),
            );
        }
        
        return rest_ensure_response(array(
            'date' => $date,
            'count' => count($result),
            'orders' => $result,
        ));
    }
}
