<?php
/**
 * Isolated administration for the free-storage campaign.
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Free_Storage_Admin {
    const OPTION_NAME = 'wlm_free_storage';
    const NONCE_ACTION = 'wlm-free-storage-admin';
    const NONCE_FIELD = 'wlm_free_storage_nonce';

    /** @var WLM_Free_Storage_Admin|null */
    private static $instance = null;

    /** @return WLM_Free_Storage_Admin */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wlm_save_free_storage', array($this, 'ajax_save_settings'));
    }

    /**
     * Render inside the existing WooCommerce settings form, never a nested form.
     */
    public static function render() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = WLM_Free_Storage::instance()->get_settings();
        $gateways = self::get_payment_gateways();

        // Enqueue here so this script is loaded only when this tab is rendered.
        wp_enqueue_script(
            'wlm-free-storage-admin',
            WLM_PLUGIN_URL . 'admin/js/free-storage-admin.js',
            array(),
            WLM_VERSION,
            true
        );
        wp_localize_script('wlm-free-storage-admin', 'wlmFreeStorageAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'i18n' => array(
                'saved' => __('Gratis-Einlagerung-Einstellungen gespeichert.', 'woo-lieferzeiten-manager'),
                'saving' => __('Einstellungen werden gespeichert …', 'woo-lieferzeiten-manager'),
                'error' => __('Die Einstellungen konnten nicht gespeichert werden. Bitte versuchen Sie es erneut.', 'woo-lieferzeiten-manager'),
                'networkError' => __('Netzwerk- oder Sitzungsfehler. Bitte laden Sie die Seite neu und versuchen Sie es erneut.', 'woo-lieferzeiten-manager'),
                'dateOrder' => __('Das Ende muss am oder nach dem Beginn liegen.', 'woo-lieferzeiten-manager'),
                'remove' => __('Entfernen', 'woo-lieferzeiten-manager'),
                'removeRow' => __('Lieferzeitfenster entfernen', 'woo-lieferzeiten-manager'),
                'start' => __('Beginn (Eintreffen beim Kunden)', 'woo-lieferzeiten-manager'),
                'end' => __('Ende (Eintreffen beim Kunden)', 'woo-lieferzeiten-manager'),
                'label' => __('Bezeichnung (optional)', 'woo-lieferzeiten-manager'),
                'automatic' => __('Automatisch aus dem Zeitraum', 'woo-lieferzeiten-manager'),
                'emptyPeriods' => __('Es sind keine Lieferzeitfenster konfiguriert.', 'woo-lieferzeiten-manager'),
            ),
        ));

        require WLM_PLUGIN_DIR . 'admin/views/tab-free-storage.php';
    }

    /**
     * Save only our own option on the non-JavaScript WooCommerce save path.
     * The caller must return afterwards, without saving any baseline options.
     *
     * @return true|WP_Error
     */
    public static function save_from_post() {
        if (!current_user_can('manage_woocommerce')) {
            return self::report_error(new WP_Error(
                'wlm_free_storage_permission',
                __('Sie dürfen diese Einstellungen nicht ändern.', 'woo-lieferzeiten-manager')
            ));
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) && is_string($_POST[self::NONCE_FIELD])
            ? wp_unslash($_POST[self::NONCE_FIELD]) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return self::report_error(new WP_Error(
                'wlm_free_storage_nonce',
                __('Die Sicherheitsprüfung ist fehlgeschlagen. Bitte laden Sie die Seite neu.', 'woo-lieferzeiten-manager')
            ));
        }

        $data = isset($_POST['wlm_free_storage']) ? wp_unslash($_POST['wlm_free_storage']) : null;
        if (is_array($data)) {
            // Unchecked checkboxes and an intentionally empty row list are omitted by HTML.
            if (!array_key_exists('allowed_gateways', $data)) {
                $data['allowed_gateways'] = array();
            }
            if (!array_key_exists('periods', $data) && isset($data['periods_present']) && '1' === $data['periods_present']) {
                $data['periods'] = array();
            }
        }
        $settings = self::persist_settings($data);
        if (is_wp_error($settings)) {
            return self::report_error($settings);
        }

        if (class_exists('WC_Admin_Settings')) {
            $message = __('Gratis-Einlagerung-Einstellungen gespeichert.', 'woo-lieferzeiten-manager');
            if (empty($settings['allowed_gateways'])) {
                $message .= ' ' . __('Achtung: Ohne freigegebene Zahlungsmethode ist keine Einlagerung im Checkout möglich.', 'woo-lieferzeiten-manager');
            }
            WC_Admin_Settings::add_message($message);
        }
        return true;
    }

    /** Authenticated AJAX endpoint. */
    public function ajax_save_settings() {
        check_ajax_referer('wlm-free-storage-admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sie dürfen diese Einstellungen nicht ändern.', 'woo-lieferzeiten-manager')), 403);
            return;
        }

        $json = isset($_POST['settings']) && is_string($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
        $data = json_decode($json, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
            wp_send_json_error(array('message' => __('Ungültige Einstellungsdaten. Die bisherigen Einstellungen wurden nicht geändert.', 'woo-lieferzeiten-manager')), 400);
            return;
        }

        $settings = self::persist_settings($data);
        if (is_wp_error($settings)) {
            wp_send_json_error(array('message' => $settings->get_error_message()), 400);
            return;
        }
        wp_send_json_success(array('settings' => $settings));
    }

    /**
     * Reject invalid user data before the core sanitizer can discard invalid rows.
     * No writes occur here. Period IDs survive reordering, edits and saves.
     *
     * @param mixed $data Unslashed settings.
     * @return array|WP_Error
     */
    public static function validate_settings($data) {
        if (!is_array($data)
            || !array_key_exists('enabled', $data)
            || !array_key_exists('allowed_gateways', $data)
            || !is_array($data['allowed_gateways'])
            || !array_key_exists('periods', $data)
            || !is_array($data['periods'])
            || !in_array($data['enabled'], array(true, false, 1, 0, '1', '0'), true)) {
            return new WP_Error('wlm_free_storage_structure', __('Ungültige Einstellungsdaten. Die bisherigen Einstellungen wurden nicht geändert.', 'woo-lieferzeiten-manager'));
        }

        $known_gateways = self::get_payment_gateways();
        $allowed_gateways = array();
        foreach ($data['allowed_gateways'] as $gateway_id) {
            if (!is_string($gateway_id) || '' === $gateway_id) {
                return new WP_Error('wlm_free_storage_gateway', __('Bitte wählen Sie nur bekannte Zahlungsmethoden aus.', 'woo-lieferzeiten-manager'));
            }
            $gateway_id = sanitize_key($gateway_id);
            if ('' === $gateway_id || !isset($known_gateways[$gateway_id])) {
                return new WP_Error('wlm_free_storage_gateway', __('Eine ausgewählte Zahlungsmethode ist nicht mehr vorhanden. Bitte laden Sie die Seite neu und prüfen Sie die Freigaben.', 'woo-lieferzeiten-manager'));
            }
            $allowed_gateways[] = $gateway_id;
        }

        $periods = array();
        $seen_ids = array();
        foreach ($data['periods'] as $row) {
            $number = count($periods) + 1;
            if (!is_array($row)
                || !isset($row['id'], $row['start'], $row['end'])
                || !is_string($row['id'])
                || !self::is_valid_date($row['start'])
                || !self::is_valid_date($row['end'])
                || (isset($row['label']) && !is_string($row['label']))) {
                return new WP_Error('wlm_free_storage_period', sprintf(
                    __('Lieferzeitfenster %d ist unvollständig oder enthält ein ungültiges Datum. Bitte geben Sie gültige Daten (JJJJ-MM-TT) ein oder entfernen Sie die Zeile.', 'woo-lieferzeiten-manager'),
                    $number
                ));
            }
            $id = sanitize_key($row['id']);
            if ('' === $id || 'asap' === $id || isset($seen_ids[$id])) {
                return new WP_Error('wlm_free_storage_period_id', sprintf(
                    __('Lieferzeitfenster %d hat keine eindeutige gültige Kennung. Bitte laden Sie die Seite neu oder legen Sie die Zeile neu an.', 'woo-lieferzeiten-manager'),
                    $number
                ));
            }
            if ($row['end'] < $row['start']) {
                return new WP_Error('wlm_free_storage_period_order', sprintf(
                    __('Lieferzeitfenster %d: Das Ende darf nicht vor dem Beginn liegen.', 'woo-lieferzeiten-manager'),
                    $number
                ));
            }
            $seen_ids[$id] = true;
            $periods[] = array(
                'id' => $id,
                'label' => isset($row['label']) ? sanitize_text_field($row['label']) : '',
                'start' => $row['start'],
                'end' => $row['end'],
            );
        }

        return WLM_Free_Storage::sanitize_settings(array(
            'enabled' => in_array($data['enabled'], array(true, 1, '1'), true),
            'allowed_gateways' => array_values(array_unique($allowed_gateways)),
            'periods' => $periods,
        ));
    }

    /** @return array<string,WC_Payment_Gateway> All installed gateways, also disabled. */
    private static function get_payment_gateways() {
        if (!function_exists('WC') || !WC() || !WC()->payment_gateways()) {
            return array();
        }
        $gateways = array();
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if (is_object($gateway) && isset($gateway->id)) {
                $gateways[sanitize_key((string) $gateway->id)] = $gateway;
            }
        }
        return $gateways;
    }

    /** @param mixed $date @return bool */
    private static function is_valid_date($date) {
        if (!is_string($date) || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts)) {
            return false;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /** @param mixed $data @return array|WP_Error */
    private static function persist_settings($data) {
        $settings = self::validate_settings($data);
        if (is_wp_error($settings)) {
            return $settings;
        }
        // This is deliberately the only option written by this controller.
        $changed = update_option(self::OPTION_NAME, $settings);
        if (!$changed && get_option(self::OPTION_NAME, null) !== $settings) {
            return new WP_Error('wlm_free_storage_save', __('Die Einstellungen konnten nicht gespeichert werden.', 'woo-lieferzeiten-manager'));
        }
        return $settings;
    }

    /** @param WP_Error $error @return WP_Error */
    private static function report_error($error) {
        if (class_exists('WC_Admin_Settings')) {
            WC_Admin_Settings::add_error($error->get_error_message());
        }
        return $error;
    }
}
