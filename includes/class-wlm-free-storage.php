<?php
/**
 * Isolated, server-authoritative free storage checkout extension.
 *
 * No cart products, fees, stock changes or changes to the normal calculator.
 * Instantiate after WLM_Core::instance(). Settings are saved by the admin UI.
 *
 * @package WooLieferzeitenManager
 */
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Free_Storage {
    const NAMESPACE_ID = 'wlm-free-storage';
    const OPTION_KEY = 'wlm_free_storage';
    const SESSION_KEY = 'wlm_free_storage_selection';
    const CONFLICT_KEY = 'wlm_free_storage_express_conflict';
    const PAYMENT_NOTICE = 'Durch die ausgewählte Einlagerungsoption können nur bestimmte Zahlungsarten angeboten werden.';

    private static $instance = null;
    private $registered = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_init', array($this, 'register_store_api'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout'), 30);
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'), 100);
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'capture_store_order'), 5);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'validate_store_order'), 100, 2);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'finalize_store_order'), 100);
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_classic_checkout'), 100, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'capture_classic_order'), 5, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'finalize_classic_order'), 100, 3);
        add_action('woocommerce_before_order_object_save', array($this, 'protect_order_dates'), 100, 2);
        add_action('woocommerce_thankyou', array($this, 'cleanup_after_thankyou'), 20);
        add_action('woocommerce_cart_emptied', array($this, 'clear_selection'));
        add_filter('woocommerce_cart_shipping_packages', array($this, 'mark_shipping_packages'), 100);
        add_filter('woocommerce_package_rates', array($this, 'filter_express_rates'), PHP_INT_MAX, 2);
        add_filter('woocommerce_shipping_packages', array($this, 'filter_cached_express_rates'), PHP_INT_MAX);
        add_action('woocommerce_before_calculate_totals', array($this, 'clear_storage_express'), 0);
        add_action('wp_ajax_wlm_activate_express', array($this, 'reject_storage_express_activation'), 0);
        add_action('wp_ajax_nopriv_wlm_activate_express', array($this, 'reject_storage_express_activation'), 0);
        add_filter('rest_request_before_callbacks', array($this, 'reject_storage_express_request'), 10, 3);
    }

    public static function defaults() {
        $dates = array(
            array('march-1', '2027-03-01', '2027-03-05'),
            array('march-2', '2027-03-08', '2027-03-12'),
            array('march-3', '2027-03-15', '2027-03-19'),
            array('march-4', '2027-03-22', '2027-03-26'),
            array('march-5', '2027-03-29', '2027-04-02'),
            array('april-1', '2027-04-05', '2027-04-09'),
        );
        $periods = array();
        foreach ($dates as $date) {
            $periods[] = array('id' => $date[0], 'start' => $date[1], 'end' => $date[2], 'label' => self::date_label($date[1], $date[2]));
        }
        return array('enabled' => false, 'periods' => $periods, 'allowed_gateways' => array());
    }

    /** Expired real dates may be saved, but are never offered to shoppers. */
    public static function sanitize_settings($data) {
        $data = is_array($data) ? $data : array();
        $defaults = self::defaults();
        $result = array('enabled' => self::flag($data['enabled'] ?? false), 'periods' => array(), 'allowed_gateways' => array());
        $rows = isset($data['periods']) && is_array($data['periods']) ? $data['periods'] : $defaults['periods'];
        $seen = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) && is_scalar($row['id']) ? sanitize_key((string) $row['id']) : '';
            $start = $row['start'] ?? '';
            $end = $row['end'] ?? '';
            if (!$id || 'asap' === $id || isset($seen[$id]) || !self::valid_date($start) || !self::valid_date($end) || $start > $end) {
                continue;
            }
            $label = isset($row['label']) && is_scalar($row['label']) ? sanitize_text_field((string) $row['label']) : '';
            $result['periods'][] = array('id' => $id, 'start' => $start, 'end' => $end, 'label' => $label ?: self::date_label($start, $end));
            $seen[$id] = true;
        }
        foreach ((isset($data['allowed_gateways']) && is_array($data['allowed_gateways']) ? $data['allowed_gateways'] : array()) as $gateway) {
            if (is_scalar($gateway)) {
                $id = sanitize_key((string) $gateway);
                if ('' !== $id) {
                    $result['allowed_gateways'][] = $id;
                }
            }
        }
        $result['allowed_gateways'] = array_values(array_unique($result['allowed_gateways']));
        // Keep retired IDs reserved: reordering/deleting a period must not reuse its SKU.
        $registry = array();
        $used = array();
        $next = 0;
        foreach ((isset($data['sku_map']) && is_array($data['sku_map']) ? $data['sku_map'] : array()) as $id => $sku) {
            $id = (string) $id;
            if ('' === $id || sanitize_key($id) !== $id || 'asap' === $id || !self::valid_sku($sku) || isset($used[$sku])) {
                continue;
            }
            $registry[$id] = $sku;
            $used[$sku] = true;
            $next = max($next, (int) substr($sku, 9));
        }
        foreach ($result['periods'] as &$period) {
            if (!isset($registry[$period['id']])) {
                $registry[$period['id']] = sprintf('EINLAGER-%03d', ++$next);
            }
            $period['sku'] = $registry[$period['id']];
        }
        unset($period);
        $result['sku_map'] = $registry;
        return $result;
    }

    public static function valid_sku($sku) {
        return is_string($sku) && preg_match('/^EINLAGER-[0-9]{3,8}$/D', $sku)
            && (int) substr($sku, 9) > 0 && sprintf('EINLAGER-%03d', (int) substr($sku, 9)) === $sku;
    }

    private static function flag($value) {
        return in_array($value, array(true, 1, '1', 'yes', 'true', 'on'), true);
    }

    public function get_settings() {
        $stored = get_option(self::OPTION_KEY, array());
        return self::sanitize_settings(array_merge(self::defaults(), is_array($stored) ? $stored : array()));
    }

    public function is_enabled() {
        return $this->get_settings()['enabled'];
    }

    private static function valid_date($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }
        if (!checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d') === $value;
    }

    private static function date_label($start, $end) {
        return substr($start, 8, 2) . '.' . substr($start, 5, 2) . '.' . substr($start, 0, 4)
            . ' – ' . substr($end, 8, 2) . '.' . substr($end, 5, 2) . '.' . substr($end, 0, 4);
    }

    private function session() {
        return function_exists('WC') && WC() ? WC()->session : null;
    }

    public function get_selection() {
        $session = $this->session();
        if (!$this->is_enabled()) {
            if ($session) {
                $session->__unset(self::SESSION_KEY);
                $session->__unset(self::CONFLICT_KEY);
            }
            return 'asap';
        }
        $value = $session ? $session->get(self::SESSION_KEY, 'asap') : 'asap';
        // Do not rewrite stale IDs: the UI must explicitly acknowledge/reset them.
        return is_string($value) && '' !== $value ? $value : 'asap';
    }

    public function is_storage_selected() {
        return 'asap' !== $this->get_selection();
    }

    /** Identify this plugin's native Express variants, not unrelated shipping labels. */
    private function express_base_id($rate_id) {
        $method_id = explode(':', (string) $rate_id)[0];
        if ('_express' !== substr($method_id, -8)) {
            return null;
        }
        $base = substr($method_id, 0, -8);
        foreach (WLM_Core::instance()->shipping_methods->get_configured_methods() as $method) {
            if (is_array($method) && isset($method['id']) && $base === (string) $method['id']) {
                return $base;
            }
        }
        return null;
    }

    private function regular_rate_id($rates, $chosen) {
        $base = $this->express_base_id($chosen);
        if (null === $base) {
            return isset($rates[$chosen]) ? $chosen : null;
        }
        foreach ($rates as $id => $rate) {
            if ($base === explode(':', (string) $id)[0]) {
                return $id;
            }
        }
        return null;
    }

    private function calculate_preserving_shipping($chosen) {
        $this->session()->set('chosen_shipping_methods', $chosen);
        // A changed rate list makes WooCommerce choose its first default, even if our
        // same-base regular choice is available. Preserve only valid choices, only here.
        $preserve = function($default, $rates, $selected) {
            return isset($rates[$selected]) ? $selected : $default;
        };
        add_filter('woocommerce_shipping_chosen_method', $preserve, PHP_INT_MAX, 3);
        try {
            WC()->cart->calculate_shipping();
        } finally {
            remove_filter('woocommerce_shipping_chosen_method', $preserve, PHP_INT_MAX);
        }
    }

    private function record_express_conflict() {
        $session = $this->session();
        if (!$this->is_storage_selected() || !$session) {
            return;
        }
        foreach ((array) $session->get('chosen_shipping_methods', array()) as $id) {
            if (null !== $this->express_base_id($id)) {
                // WooCommerce may silently resolve an unavailable Express ID to its
                // default. Remember the conflict until an explicit valid choice/reset.
                $session->set(self::CONFLICT_KEY, true);
                break;
            }
        }
    }

    public function mark_shipping_packages($packages) {
        if ($this->is_storage_selected() && is_array($packages)) {
            $this->record_express_conflict();
            foreach ($packages as &$package) {
                // Included in WooCommerce's package hash; ASAP/disabled has no marker.
                $package['wlm_free_storage_no_express'] = true;
            }
            unset($package);
        }
        return $packages;
    }

    public function filter_express_rates($rates, $package = array()) {
        if (!$this->is_storage_selected() || !is_array($rates)) {
            return $rates;
        }
        foreach ($rates as $id => $rate) {
            if (null !== $this->express_base_id($id)) {
                unset($rates[$id]);
            }
        }
        return $rates;
    }

    public function filter_cached_express_rates($packages) {
        if ($this->is_storage_selected() && is_array($packages)) {
            foreach ($packages as &$package) {
                $package['rates'] = $this->filter_express_rates($package['rates'] ?? array(), $package);
            }
            unset($package);
        }
        return $packages;
    }

    private function invalidate_shipping_cache() {
        $session = $this->session();
        if ($session && function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_shipping_packages() as $index => $package) {
                $session->__unset('shipping_for_package_' . $index);
            }
        }
    }

    public function clear_storage_express($cart = null) {
        $session = $this->session();
        $this->record_express_conflict();
        if ($this->is_storage_selected() && $session && $session->get('wlm_express_selected')) {
            $session->__unset('wlm_express_selected');
            $this->invalidate_shipping_cache();
        }
    }

    public function reject_storage_express_activation() {
        if ($this->is_storage_selected()) {
            check_ajax_referer('wlm-express-nonce', 'nonce');
            wp_send_json_error(array('message' => __('Express-Versand und Gratis Einlagerung können nicht gleichzeitig gewählt werden. Bitte wählen Sie zuerst „So schnell wie möglich“.', 'woo-lieferzeiten-manager')));
        }
    }

    public function reject_storage_express_request($response, $handler, $request) {
        if (null !== $response || 'POST' !== $request->get_method() || !preg_match('#^/wc/store/v[0-9]+/cart/select-shipping-rate$#D', $request->get_route())) {
            return $response;
        }
        $controller = '\Automattic\WooCommerce\StoreApi\Utilities\CartController';
        if (class_exists($controller)) {
            // This route's normal callback initializes the cart too. Read the correct
            // Store API session before any submitted shipping ID can replace its value.
            (new $controller())->load_cart();
        }
        $id = $request['rate_id'] ?? '';
        if ($this->is_storage_selected() && is_string($id) && null !== $this->express_base_id($id)) {
            return new WP_Error('wlm_free_storage_express_conflict', 'Express-Versand und Gratis Einlagerung können nicht gleichzeitig gewählt werden. Bitte wählen Sie zuerst „So schnell wie möglich“.', array('status' => 400));
        }
        return $response;
    }

    public function clear_selection() {
        $session = $this->session();
        if ($session) {
            $session->__unset(self::SESSION_KEY);
            $session->__unset(self::CONFLICT_KEY);
        }
    }

    public function register_store_api() {
        if ($this->registered || !function_exists('woocommerce_store_api_register_endpoint_data') || !function_exists('woocommerce_store_api_register_update_callback')) {
            return;
        }
        $cart_schema = '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema';
        if (!class_exists($cart_schema)) {
            return;
        }
        $this->registered = true;
        // These are response-only cart fields, not checkout request parameters.
        // Checkout uses the server session and revalidates it before payment.
        woocommerce_store_api_register_endpoint_data(array(
            'endpoint' => $cart_schema::IDENTIFIER,
            'namespace' => self::NAMESPACE_ID,
            'data_callback' => array($this, 'get_cart_data'),
            'schema_callback' => array($this, 'get_schema'),
            'schema_type' => ARRAY_A,
        ));
        woocommerce_store_api_register_update_callback(array('namespace' => self::NAMESPACE_ID, 'callback' => array($this, 'update_selection')));
    }

    public function get_schema() {
        $string = array('type' => 'string', 'readonly' => true);
        $boolean = array('type' => 'boolean', 'readonly' => true);
        $window = array('type' => array('object', 'null'), 'readonly' => true, 'properties' => array(
            'earliest_date' => $string, 'latest_date' => $string, 'ship_by_date' => $string, 'window' => $string,
        ));
        return array(
            'enabled' => $boolean, 'selected' => $string, 'selected_valid' => $boolean, 'invalid_reason' => $string,
            'options' => array('type' => 'array', 'readonly' => true, 'items' => array('type' => 'object', 'properties' => array(
                'id' => $string, 'label' => $string, 'start' => $string, 'end' => $string, 'available' => $boolean, 'reason' => $string, 'sku' => $string,
            ))),
            'default_window' => $window, 'selected_window' => $window,
            'allowed_gateways' => array('type' => 'array', 'readonly' => true, 'items' => $string),
            'title' => $string, 'payment_notice' => $string, 'line_name' => $string, 'line_sku' => $string,
        );
    }

    /** Public response exposes dates/payment IDs only, never shipping/admin config. */
    public function get_cart_data() {
        $settings = $this->get_settings();
        $selection = $this->get_selection();
        $context = $settings['enabled'] || 'asap' !== $selection ? $this->baseline() : array('window' => null, 'reason' => '');
        // Offer storage only if the regular variant can meet it, without changing ASAP Express.
        $conflict = $this->session() && $this->session()->get(self::CONFLICT_KEY);
        $storage_context = $settings['enabled'] && ('asap' === $selection || $conflict) ? $this->baseline(null, true) : $context;
        $options = array();
        $chosen = null;
        $chosen_sku = '';
        $reason = 'asap' === $selection ? '' : 'Das gewählte Einlagerungsfenster ist nicht mehr vorhanden. Bitte wählen Sie erneut.';
        foreach ($settings['periods'] as $period) {
            $evaluated = $this->evaluate_period($period, $settings, $storage_context);
            $options[] = array('id' => $period['id'], 'label' => $period['label'], 'start' => $period['start'], 'end' => $period['end'], 'available' => '' === $evaluated['reason'], 'reason' => $evaluated['reason'], 'sku' => $period['sku']);
            if ($period['id'] === $selection) {
                $chosen = $evaluated['window'];
                $chosen_sku = $period['sku'];
                $reason = $evaluated['reason'];
            }
        }
        if ('asap' !== $selection && $conflict) {
            $chosen = null;
            $reason = 'Die frühere Express-Auswahl ist nicht mit Gratis Einlagerung kombinierbar. Bitte wählen Sie erneut einen Lieferzeitraum oder „So schnell wie möglich“.';
        }
        return array(
            'enabled' => $settings['enabled'], 'selected' => $selection,
            'selected_valid' => '' === $reason, 'invalid_reason' => $reason, 'options' => $options,
            'default_window' => $context['window'] ?? null,
            'selected_window' => 'asap' === $selection ? ($context['window'] ?? null) : $chosen,
            'allowed_gateways' => $settings['allowed_gateways'], 'title' => 'Gratis Einlagerung',
            'payment_notice' => self::PAYMENT_NOTICE,
            'line_name' => 'asap' !== $selection && '' === $reason && $chosen ? 'Gratis Einlagerung – Lieferung ' . $chosen['window'] : '',
            'line_sku' => 'asap' !== $selection && '' === $reason && $chosen ? $chosen_sku : '',
        );
    }

    public function update_selection($data) {
        if (!is_array($data) || !isset($data['selection']) || !is_string($data['selection'])) {
            $this->route_error('Bitte wählen Sie eine gültige Lieferoption.');
        }
        $id = $data['selection'];
        $session = $this->session();
        if (!$session) {
            $this->route_error('Die Checkout-Sitzung ist nicht verfügbar. Bitte laden Sie die Seite neu.');
        }
        if ('asap' === $id) {
            $was_storage = $this->is_storage_selected();
            $chosen = $session->get('chosen_shipping_methods', array());
            $session->set(self::SESSION_KEY, $id);
            $session->__unset(self::CONFLICT_KEY);
            // Reappear after switching back, without automatically choosing Express.
            $this->invalidate_shipping_cache();
            if ($was_storage && function_exists('WC') && WC() && WC()->cart && WC()->cart->needs_shipping()) {
                $this->calculate_preserving_shipping($chosen);
            }
            return;
        }
        $settings = $this->get_settings();
        $known = false;
        foreach ($settings['periods'] as $period) {
            $known = $known || $period['id'] === $id;
        }
        if (!$settings['enabled'] || !$known) {
            $this->route_error('Das gewählte Einlagerungsfenster ist nicht verfügbar. Bitte wählen Sie erneut.');
        }
        if (!function_exists('WC') || !WC() || !WC()->cart || WC()->cart->is_empty() || !WC()->cart->needs_shipping()) {
            $this->route_error('Für diesen Warenkorb ist keine Einlagerung verfügbar. Bitte wählen Sie „So schnell wie möglich“.');
        }
        $previous = $this->get_selection();
        $previous_shipping = $session->get('chosen_shipping_methods', array());
        $previous_express = $session->get('wlm_express_selected');
        $previous_conflict = $session->get(self::CONFLICT_KEY);
        try {
            // Clearing legacy Express also removes its cost contribution from fresh rates.
            $session->__unset('wlm_express_selected');
            $this->invalidate_shipping_cache();
            $this->calculate_preserving_shipping($previous_shipping);
            $packages = WC()->shipping()->get_packages();
            $regular = $previous_shipping;
            foreach (WC()->cart->get_shipping_packages() as $index => $package) {
                $chosen = $previous_shipping[$index] ?? '';
                $regular[$index] = $this->regular_rate_id($packages[$index]['rates'] ?? array(), $chosen);
                if (!$regular[$index]) {
                    $this->route_error('Für Gratis Einlagerung ist keine passende reguläre Versandart verfügbar. Bitte wählen Sie „So schnell wie möglich“.');
                }
            }
            $session->set('chosen_shipping_methods', $regular);
            $session->set(self::SESSION_KEY, $id);
            $session->__unset(self::CONFLICT_KEY);
            $this->invalidate_shipping_cache();
            $this->calculate_preserving_shipping($regular);
            if ($session->get('chosen_shipping_methods') !== $regular) {
                $this->route_error('Die reguläre Versandart ist nicht mehr verfügbar. Bitte wählen Sie erneut.');
            }
            // Authoritative validation uses regular transit and ignores client date fields.
            $this->validated_selection($id);
        } catch (Exception $e) {
            // Failed choices must not silently cancel a customer's prior Express purchase.
            $session->set(self::SESSION_KEY, $previous);
            $session->set('chosen_shipping_methods', $previous_shipping);
            if (null === $previous_express) {
                $session->__unset('wlm_express_selected');
            } else {
                $session->set('wlm_express_selected', $previous_express);
            }
            if (null === $previous_conflict) {
                $session->__unset(self::CONFLICT_KEY);
            } else {
                $session->set(self::CONFLICT_KEY, $previous_conflict);
            }
            $this->invalidate_shipping_cache();
            if (function_exists('WC') && WC()->cart) {
                $this->calculate_preserving_shipping($previous_shipping);
                // calculate_shipping may auto-resolve stale IDs; preserve the rejected request's state.
                $session->set('chosen_shipping_methods', $previous_shipping);
            }
            throw $e;
        }
    }

    private function route_error($message) {
        $class = '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException';
        if (class_exists($class)) {
            throw new $class('wlm_free_storage_invalid', $message, 400);
        }
        throw new Exception($message, 400);
    }

    private function validated_selection($id = null, $method_id = null) {
        $id = null === $id ? $this->get_selection() : $id;
        if ('asap' === $id) {
            return null;
        }
        $settings = $this->get_settings();
        if (!$settings['enabled']) {
            $this->route_error('Gratis Einlagerung ist derzeit nicht verfügbar. Bitte wählen Sie „So schnell wie möglich“.');
        }
        if (!$settings['allowed_gateways']) {
            $this->route_error('Für Gratis Einlagerung ist keine Zahlungsmethode freigegeben. Bitte wählen Sie „So schnell wie möglich“.');
        }
        foreach ($settings['periods'] as $period) {
            if ($period['id'] === $id) {
                $context = $this->baseline($method_id);
                $evaluation = $this->evaluate_period($period, $settings, $context);
                if ('' !== $evaluation['reason']) {
                    $this->route_error($evaluation['reason']);
                }
                return array('id' => $id, 'period' => $period, 'window' => $evaluation['window'], 'default_window' => $context['window'], 'method_name' => $context['method_name']);
            }
        }
        $this->route_error('Das gewählte Einlagerungsfenster ist nicht mehr vorhanden. Bitte wählen Sie erneut.');
    }

    public function get_storage_window($method_id = null) {
        if ('asap' === $this->get_selection()) {
            return null;
        }
        try {
            $selected = $this->validated_selection(null, $method_id);
            return $selected ? $selected['window'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Date-only, bounded calendar arithmetic: never call the calculator for impossible calendars. */
    private function calendar() {
        $settings = WLM_Core::instance()->get_settings();
        $settings = is_array($settings) ? $settings : array();
        $weekdays = $settings['business_days'] ?? array(1, 2, 3, 4, 5);
        if (!is_array($weekdays)) {
            return null;
        }
        foreach ($weekdays as $day) {
            if (!is_scalar($day) || !preg_match('/^[1-7]$/D', (string) $day)) {
                return null;
            }
        }
        $weekdays = array_values(array_unique(array_filter(array_map('intval', $weekdays), function($day) { return $day >= 1 && $day <= 7; })));
        if (!$weekdays) {
            return null;
        }
        $holidays = $settings['holidays'] ?? array();
        $holidays = is_array($holidays) ? $holidays : (empty($holidays) ? array() : array($holidays));
        $holidays = array_filter($holidays, array(__CLASS__, 'valid_date'));
        return array('weekdays' => $weekdays, 'holidays' => array_fill_keys($holidays, true));
    }

    private function business_day($date, $calendar) {
        return in_array((int) $date->format('N'), $calendar['weekdays'], true) && !isset($calendar['holidays'][$date->format('Y-m-d')]);
    }

    private function move_business_days($date, $days, $calendar, $direction = 1) {
        if (!self::valid_date($date) || !$calendar || $days < 0 || $days > 3660) {
            return null;
        }
        $current = new DateTimeImmutable($date, new DateTimeZone('UTC'));
        $count = 0;
        for ($step = 0; $step <= 3660; $step++) {
            if ($count === $days) {
                return $current->format('Y-m-d');
            }
            $current = $current->modify($direction < 0 ? '-1 day' : '+1 day');
            if ($this->business_day($current, $calendar)) {
                $count++;
            }
        }
        return null;
    }

    private function preceding_business_day($date, $calendar) {
        $current = new DateTimeImmutable($date, new DateTimeZone('UTC'));
        for ($step = 0; $step <= 3660; $step++) {
            if ($this->business_day($current, $calendar)) {
                return $current->format('Y-m-d');
            }
            $current = $current->modify('-1 day');
        }
        return null;
    }

    private function baseline($method_id = null, $regular_for_storage = false) {
        $failure = array('window' => null, 'reason' => 'Für diesen Warenkorb ist kein verlässlicher gemeinsamer Liefertermin verfügbar.');
        if (!function_exists('WC') || !WC() || !WC()->cart || WC()->cart->is_empty() || !WC()->cart->needs_shipping() || !$this->session()) {
            return $failure;
        }
        if (!$regular_for_storage && $this->is_storage_selected() && $this->session()->get(self::CONFLICT_KEY)) {
            $failure['reason'] = 'Die frühere Express-Auswahl ist nicht mit Gratis Einlagerung kombinierbar. Bitte wählen Sie erneut einen Lieferzeitraum oder „So schnell wie möglich“.';
            return $failure;
        }
        $calendar = $this->calendar();
        if (!$calendar) {
            $failure['reason'] = 'Die konfigurierten Versandwerktage erlauben keinen Einlagerungstermin.';
            return $failure;
        }
        $chosen = $this->session()->get('chosen_shipping_methods', array());
        $packages = WC()->cart->get_shipping_packages();
        if (!is_array($chosen) || !$packages) {
            return $failure;
        }
        $rated_packages = WC()->shipping()->get_packages();
        foreach ($packages as $index => $package) {
            if ($regular_for_storage) {
                $chosen[$index] = $this->regular_rate_id($rated_packages[$index]['rates'] ?? array(), $chosen[$index] ?? '');
                if (!$chosen[$index]) {
                    $failure['reason'] = 'Für Gratis Einlagerung ist keine passende reguläre Versandart verfügbar.';
                    return $failure;
                }
            } elseif ($this->is_storage_selected() && null !== $this->express_base_id($chosen[$index] ?? '')) {
                $failure['reason'] = 'Express-Versand und Gratis Einlagerung können nicht gleichzeitig gewählt werden. Bitte wählen Sie eine reguläre Versandart oder „So schnell wie möglich“.';
                return $failure;
            }
        }
        $ids = array();
        foreach ($packages as $index => $package) {
            if (empty($chosen[$index]) || !is_string($chosen[$index])) {
                return $failure;
            }
            $ids[] = $chosen[$index];
        }
        if (count(array_unique($ids)) > 1) {
            $failure['reason'] = 'Gratis Einlagerung ist bei unterschiedlichen Versandarten in mehreren Paketen nicht verfügbar.';
            return $failure;
        }
        $chosen_id = reset($ids);
        // Optional Blocks method must describe the actual selected method, not a preview rate.
        if (null !== $method_id && $method_id !== $chosen_id && explode(':', (string) $method_id)[0] !== explode(':', $chosen_id)[0]) {
            return $failure;
        }
        // If WooCommerce has calculated rates, a stale session ID must not override them.
        if (method_exists(WC(), 'shipping') && WC()->shipping()) {
            $rated_packages = WC()->shipping()->get_packages();
            foreach ($packages as $index => $package) {
                if (!isset($rated_packages[$index]['rates'][$chosen[$index]])) {
                    return $failure;
                }
            }
        }
        $rate_method = explode(':', $chosen_id)[0];
        $express = substr($rate_method, -8) === '_express';
        $base_id = $express ? substr($rate_method, 0, -8) : $rate_method;
        $core = WLM_Core::instance();
        $methods = $core->shipping_methods->get_configured_methods();
        $config = null;
        foreach ($methods as $method) {
            if (is_array($method) && isset($method['id']) && $base_id === (string) $method['id'] && self::flag($method['enabled'] ?? true)) {
                $config = $method;
                break;
            }
        }
        if (!$config || ($express && !self::flag($config['express_enabled'] ?? false))) {
            return $failure;
        }
        $min = $express ? ($config['express_transit_min'] ?? 0) : ($config['transit_min'] ?? 1);
        $max = $express ? ($config['express_transit_max'] ?? 1) : ($config['transit_max'] ?? 3);
        foreach (array($min, $max) as $transit) {
            if (!is_scalar($transit) || !preg_match('/^\d+$/D', (string) $transit) || (int) $transit > 365) {
                return $failure;
            }
        }
        $min = (int) $min;
        $max = (int) $max;
        if ($min > $max) {
            return $failure;
        }
        $today = function_exists('current_time') ? current_time('Y-m-d') : gmdate('Y-m-d');
        $settings = $core->get_settings();
        $processing = $settings['processing_days'] ?? 1;
        $lead = $settings['default_lead_time'] ?? 0;
        if (!is_numeric($processing) || !is_numeric($lead) || $processing < 0 || $processing > 365 || $lead < 0 || $lead > 365) {
            return $failure;
        }
        $required = (int) ceil($processing) + (int) $lead + $max + 1;
        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if (!$product || !($item['quantity'] > 0)) {
                return $failure;
            }
            $product_lead = get_post_meta($product->get_id(), '_wlm_lead_time_days', true);
            if ('' !== $product_lead && (!is_numeric($product_lead) || $product_lead < 0 || $product_lead > 365)) {
                return $failure;
            }
            $required = max($required, (int) $product_lead + (int) ceil($processing) + $max + 1);
        }
        if (!$this->move_business_days($today, $required, $calendar)) {
            return $failure;
        }
        // This fresh instance deliberately avoids Core's request-level memoization.
        $calculator = new WLM_Calculator();
        $raw = $calculator->calculate_cart_window($config, $express);
        if (empty($raw['earliest']) || empty($raw['latest']) || empty($raw['ship_by'])) {
            return $failure;
        }
        $window = array('earliest_date' => gmdate('Y-m-d', (int) $raw['earliest']), 'latest_date' => gmdate('Y-m-d', (int) $raw['latest']), 'ship_by_date' => gmdate('Y-m-d', (int) $raw['ship_by']), 'window' => sanitize_text_field($raw['window_formatted'] ?? ''));
        if ($window['earliest_date'] > $window['latest_date'] || $window['ship_by_date'] > $window['latest_date']) {
            return $failure;
        }
        return array('window' => $window, 'reason' => '', 'calendar' => $calendar, 'transit_min' => $min, 'transit_max' => $max, 'method_name' => sanitize_text_field($config['name'] ?? ($config['title'] ?? '')) . ($express ? ' Express' : ''));
    }

    private function evaluate_period($period, $settings, $context) {
        $failure = array('window' => null, 'reason' => '');
        if (!$settings['enabled']) {
            $failure['reason'] = 'Gratis Einlagerung ist derzeit nicht verfügbar.';
        } elseif (!$settings['allowed_gateways']) {
            $failure['reason'] = 'Für Gratis Einlagerung ist keine Zahlungsmethode freigegeben.';
        } elseif ($period['start'] < current_time('Y-m-d')) {
            $failure['reason'] = 'Dieses Einlagerungsfenster ist bereits abgelaufen oder hat begonnen.';
        } elseif (!empty($context['reason']) || empty($context['window'])) {
            $failure['reason'] = $context['reason'] ?: 'Ein verlässlicher Liefertermin konnte nicht ermittelt werden.';
        }
        if ('' !== $failure['reason']) {
            return $failure;
        }
        $end = $this->preceding_business_day($period['end'], $context['calendar']);
        $dispatch = $end ? $this->move_business_days($end, $context['transit_max'], $context['calendar'], -1) : null;
        $arrival_min = $dispatch ? $this->move_business_days($dispatch, $context['transit_min'], $context['calendar']) : null;
        $arrival_max = $dispatch ? $this->move_business_days($dispatch, $context['transit_max'], $context['calendar']) : null;
        if (!$dispatch || !$arrival_min || !$arrival_max || $arrival_min < $period['start'] || $arrival_max > $period['end']) {
            $failure['reason'] = 'Für dieses Zeitfenster ist mit der gewählten Versandart kein passender Versandtermin möglich.';
        } elseif ($context['window']['latest_date'] > $period['end'] || $context['window']['ship_by_date'] > $dispatch || $dispatch < current_time('Y-m-d')) {
            $failure['reason'] = 'Der gesamte Warenkorb kann nicht rechtzeitig für dieses Einlagerungsfenster versendet werden.';
        } else {
            $failure['window'] = array('earliest_date' => $period['start'], 'latest_date' => $period['end'], 'ship_by_date' => $dispatch, 'window' => $period['label']);
        }
        return $failure;
    }

    public function filter_payment_gateways($gateways) {
        if (!is_array($gateways) || 'asap' === $this->get_selection() || (function_exists('is_admin') && is_admin() && !wp_doing_ajax())) {
            return $gateways;
        }
        try {
            $this->validated_selection();
        } catch (Exception $e) {
            // Never hide baseline gateways for a stale selection; checkout validation rejects it.
            return $gateways;
        }
        return array_intersect_key($gateways, array_fill_keys($this->get_settings()['allowed_gateways'], true));
    }

    private function validate_payment($payment, $selected) {
        if ($selected && (!is_string($payment) || !in_array($payment, $this->get_settings()['allowed_gateways'], true))) {
            $this->route_error('Die gewählte Zahlungsmethode ist für Gratis Einlagerung nicht freigegeben. Bitte wählen Sie eine freigegebene Zahlungsmethode.');
        }
    }

    private function validate_order_payment($order, $payment) {
        $allowed = $order->get_meta('_wlm_storage_allowed_gateways', true);
        if (!is_array($allowed) || !is_string($payment) || !in_array($payment, $allowed, true)) {
            $this->route_error('Die gewählte Zahlungsmethode ist für diese Einlagerungsbestellung nicht freigegeben. Bitte wählen Sie eine freigegebene Zahlungsmethode.');
        }
    }

    /** Existing placed orders are read-only even if the current session is ASAP. */
    public function capture_store_order($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        if ($order->get_id() && !$order->has_status('checkout-draft')) {
            $this->protect_order_dates($order);
            return;
        }
        $this->synchronize_order($order, $this->validated_selection());
    }

    public function validate_store_order($order, $request) {
        if (!$order instanceof WC_Order || !is_object($request) || !method_exists($request, 'get_method')) {
            return;
        }
        if ('POST' !== $request->get_method()) {
            return;
        }
        if ($order->has_status('checkout-draft') || !$order->get_id()) {
            $selected = $this->validated_selection();
            $this->validate_payment($request['payment_method'] ?? '', $selected);
            $this->synchronize_order($order, $selected);
        } elseif (self::has_storage_order($order)) {
            // Payment retry uses the immutable order, not an unrelated new cart/session.
            $this->validate_order_payment($order, $request['payment_method'] ?? '');
            $this->protect_order_dates($order);
        }
    }

    private function classic_selection($data) {
        if (isset($data[self::SESSION_KEY])) {
            $id = $data[self::SESSION_KEY];
        } elseif (isset($_POST[self::SESSION_KEY])) {
            $id = wp_unslash($_POST[self::SESSION_KEY]);
        } else {
            return;
        }
        if (isset($_POST['wlm_free_storage_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wlm_free_storage_nonce'])), self::NAMESPACE_ID)) {
            $this->route_error('Die Lieferoption konnte nicht bestätigt werden. Bitte laden Sie den Checkout neu.');
        }
        $this->update_selection(array('selection' => $id));
    }

    public function validate_classic_checkout($data, $errors) {
        try {
            $this->classic_selection(is_array($data) ? $data : array());
            $selected = $this->validated_selection();
            $this->validate_payment($data['payment_method'] ?? '', $selected);
        } catch (Exception $e) {
            $errors->add('wlm_free_storage_invalid', $e->getMessage());
        }
    }

    public function capture_classic_order($order, $data) {
        if (!$order instanceof WC_Order) {
            return;
        }
        if ($order->get_id() && self::has_storage_order($order) && !$order->has_status('checkout-draft')) {
            $this->protect_order_dates($order);
            return;
        }
        $this->classic_selection(is_array($data) ? $data : array());
        $selected = $this->validated_selection();
        $this->validate_payment($data['payment_method'] ?? '', $selected);
        $this->synchronize_order($order, $selected);
    }

    private function synchronize_order($order, $selected) {
        if (!$selected) {
            if (self::has_storage_order($order)) {
                $default = $order->get_meta('_wlm_storage_default_window', true);
                if (is_array($default) && self::valid_window($default)) {
                    $this->apply_delivery_data($order, $default);
                }
            }
            $this->remove_storage_lines($order);
            foreach (array('_wlm_storage_selection', '_wlm_storage_start', '_wlm_storage_end', '_wlm_storage_ship_by', '_wlm_storage_label', '_wlm_storage_method_name', '_wlm_storage_default_window', '_wlm_storage_allowed_gateways', '_wlm_storage_sku') as $key) {
                $order->delete_meta_data($key);
            }
            return;
        }
        $window = $selected['window'];
        $default = $selected['default_window'];
        $default['method_name'] = $selected['method_name'];
        // Defaults are recalculated for each draft update, never from client-hidden fields.
        $order->update_meta_data('_wlm_storage_default_window', $default);
        $order->update_meta_data('_wlm_storage_selection', $selected['id']);
        $order->update_meta_data('_wlm_storage_sku', $selected['period']['sku']);
        $order->update_meta_data('_wlm_storage_start', $window['earliest_date']);
        $order->update_meta_data('_wlm_storage_end', $window['latest_date']);
        $order->update_meta_data('_wlm_storage_ship_by', $window['ship_by_date']);
        $order->update_meta_data('_wlm_storage_label', $window['window']);
        $order->update_meta_data('_wlm_storage_method_name', $selected['method_name']);
        $order->update_meta_data('_wlm_storage_allowed_gateways', $this->get_settings()['allowed_gateways']);
        $this->protect_order_dates($order);
        $this->ensure_storage_line($order);
    }

    private function remove_storage_lines($order) {
        foreach ($order->get_items('line_item') as $key => $item) {
            if ($item->get_meta('_wlm_free_storage', true)) {
                $order->remove_item($key);
            }
        }
    }

    /** Update one tagged line in place; remove only our duplicates, never ordinary items. */
    private function ensure_storage_line($order) {
        if (!self::has_storage_order($order)) {
            return;
        }
        $line = null;
        foreach ($order->get_items('line_item') as $key => $item) {
            if ($item->get_meta('_wlm_free_storage', true)) {
                if (null === $line) {
                    $line = $item;
                } else {
                    $order->remove_item($key);
                }
            }
        }
        $new = null === $line;
        if ($new) {
            $line = new WC_Order_Item_Product();
        }
        $line->set_name('Gratis Einlagerung – Lieferung ' . $order->get_meta('_wlm_storage_label', true));
        $line->set_product_id(0);
        $line->set_variation_id(0);
        $line->set_quantity(1);
        $line->set_subtotal(0);
        $line->set_total(0);
        $line->set_subtotal_tax(0);
        $line->set_total_tax(0);
        $line->set_taxes(array('total' => array(), 'subtotal' => array()));
        $line->update_meta_data('_wlm_free_storage', '1');
        $sku = $order->get_meta('_wlm_storage_sku', true);
        if (self::valid_sku($sku)) {
            $line->update_meta_data('_wlm_storage_sku', $sku);
            $line->update_meta_data('Einlagerungs-SKU', $sku);
        }
        if ($new) {
            $order->add_item($line);
        }
    }

    private static function valid_window($window) {
        return is_array($window) && self::valid_date($window['earliest_date'] ?? null)
            && self::valid_date($window['latest_date'] ?? null) && self::valid_date($window['ship_by_date'] ?? null)
            && $window['earliest_date'] <= $window['latest_date'] && $window['ship_by_date'] <= $window['latest_date']
            && isset($window['window']) && is_string($window['window']) && '' !== $window['window'];
    }

    public static function has_storage_order($order) {
        if (!$order instanceof WC_Order) {
            return false;
        }
        $id = $order->get_meta('_wlm_storage_selection', true);
        if (!is_string($id) || '' === $id || 'asap' === $id || sanitize_key($id) !== $id) {
            return false;
        }
        return self::valid_window(array(
            'earliest_date' => $order->get_meta('_wlm_storage_start', true),
            'latest_date' => $order->get_meta('_wlm_storage_end', true),
            'ship_by_date' => $order->get_meta('_wlm_storage_ship_by', true),
            'window' => $order->get_meta('_wlm_storage_label', true),
        ));
    }

    /** Order-only helper: no session, settings, cart, gateway or calculator reads. */
    public static function order_delivery_data($order) {
        if (!self::has_storage_order($order)) {
            return array();
        }
        return array('earliest' => $order->get_meta('_wlm_storage_start', true), 'latest' => $order->get_meta('_wlm_storage_end', true), 'ship_by' => $order->get_meta('_wlm_storage_ship_by', true), 'window' => $order->get_meta('_wlm_storage_label', true), 'method_name' => $order->get_meta('_wlm_storage_method_name', true));
    }

    private function apply_delivery_data($order, $data) {
        $order->update_meta_data('_wlm_earliest_delivery', $data['earliest_date']);
        $order->update_meta_data('_wlm_latest_delivery', $data['latest_date']);
        $order->update_meta_data('_wlm_ship_by_date', $data['ship_by_date']);
        $order->update_meta_data('_wlm_delivery_window', $data['window']);
        $order->update_meta_data('_wlm_shipping_method_name', $data['method_name'] ?? '');
    }

    public function protect_order_dates($order, $data_store = null) {
        if (!self::has_storage_order($order)) {
            return;
        }
        $snapshot = self::order_delivery_data($order);
        $this->apply_delivery_data($order, array('earliest_date' => $snapshot['earliest'], 'latest_date' => $snapshot['latest'], 'ship_by_date' => $snapshot['ship_by'], 'window' => $snapshot['window'], 'method_name' => $snapshot['method_name']));
        // Do not call save here: this is also a before-order-save hook.
    }

    public function finalize_store_order($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        if (self::has_storage_order($order)) {
            // POST capture already validated the baseline. Finalization is order-only:
            // it must not replace an existing payment-retry snapshot with a new session.
            $this->validate_order_payment($order, $order->get_payment_method());
            $this->protect_order_dates($order);
            $this->ensure_storage_line($order);
            $order->save();
        } elseif ($order->has_status('checkout-draft') && 'asap' !== $this->get_selection()) {
            $this->route_error('Die Einlagerungsauswahl konnte nicht sicher in der Bestellung gespeichert werden. Bitte wählen Sie erneut.');
        }
    }

    public function finalize_classic_order($order_id, $posted_data = array(), $order = null) {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        if ($order && self::has_storage_order($order)) {
            $this->validate_order_payment($order, $order->get_payment_method());
            $this->protect_order_dates($order);
            $this->ensure_storage_line($order);
            $order->save();
        }
    }

    public function cleanup_after_thankyou($order_id) {
        $order = wc_get_order($order_id);
        // A direct visit to someone else's thank-you URL must not reset a new selection.
        if ($order && self::has_storage_order($order) && $this->get_selection() === $order->get_meta('_wlm_storage_selection', true)) {
            $key = isset($_GET['key']) && is_string($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
            if ($key && hash_equals($order->get_order_key(), $key)) {
                $this->clear_selection();
            }
        }
    }

    public function enqueue_checkout() {
        if (!$this->is_enabled() || !function_exists('is_checkout') || !is_checkout() || is_order_received_page() || (function_exists('is_checkout_pay_page') && is_checkout_pay_page())) {
            return;
        }
        // Parent's IntegrationInterface owns registration and block hydration dependencies.
        // Do not register a second script, alter its dependencies or force a Blocks enqueue.
        if (!wp_script_is('wlm-free-storage-checkout', 'registered')) {
            return;
        }
        $gateways = function_exists('WC') && WC() ? WC()->payment_gateways()->payment_gateways() : array();
        wp_localize_script('wlm-free-storage-checkout', 'wlmFreeStorage', array(
            'namespace' => self::NAMESPACE_ID, 'gateway_ids' => array_keys($gateways),
            'classic_nonce' => wp_create_nonce(self::NAMESPACE_ID),
            'nonce' => wp_create_nonce(self::NAMESPACE_ID), 'ajax_url' => admin_url('admin-ajax.php'),
            'data' => $this->get_cart_data(),
        ));
    }
}
