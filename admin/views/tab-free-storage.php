<?php
/**
 * Free-storage campaign settings, rendered inside the WooCommerce settings form.
 *
 * @package WooLieferzeitenManager
 * @var array $settings
 * @var array $gateways Includes disabled payment gateways.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
    #wlm-free-storage-settings { max-width: 1200px; }
    #wlm-free-storage-settings .wlm-free-storage-panel { background: #fff; border: 1px solid #c3c4c7; padding: 16px 20px; margin: 16px 0; }
    #wlm-free-storage-settings .wlm-free-storage-panel h3 { margin-top: 0; }
    #wlm-free-storage-settings .wlm-free-storage-gateways label { display: block; margin: 0 0 10px; }
    #wlm-free-storage-settings .wlm-free-storage-table-wrap { overflow-x: auto; }
    #wlm-free-storage-settings .wlm-free-storage-periods th { padding: 10px; }
    #wlm-free-storage-settings .wlm-free-storage-periods td { padding: 10px; vertical-align: middle; }
    #wlm-free-storage-settings .wlm-free-storage-periods input[type="date"] { width: 160px; }
    #wlm-free-storage-settings .wlm-free-storage-periods input[type="text"] { width: 100%; min-width: 160px; }
    #wlm-free-storage-settings .notice { margin: 12px 0; }
    #wlm-free-storage-settings [hidden] { display: none !important; }
    #wlm-free-storage-settings .wlm-free-storage-help { margin: 10px 0 0; }
</style>

<div id="wlm-free-storage-settings" class="wlm-tab-content">
    <input type="hidden" name="wlm_free_storage_form" value="1">
    <input type="hidden" name="wlm_free_storage[periods_present]" value="1">
    <?php wp_nonce_field(WLM_Free_Storage_Admin::NONCE_ACTION, WLM_Free_Storage_Admin::NONCE_FIELD, false); ?>

    <h2><?php esc_html_e('Gratis Einlagerung', 'woo-lieferzeiten-manager'); ?></h2>
    <p><?php esc_html_e('Kunden können im Checkout per Radioauswahl zwischen „So schnell wie möglich“ und einem späteren Lieferzeitfenster für die gesamte Bestellung wählen. Die Aktion ist standardmäßig deaktiviert.', 'woo-lieferzeiten-manager'); ?></p>
    <div id="wlm-free-storage-notice" class="notice" role="status" aria-live="polite" tabindex="-1" hidden><p></p></div>

    <div class="wlm-free-storage-panel">
        <h3><?php esc_html_e('Aktion im Checkout', 'woo-lieferzeiten-manager'); ?></h3>
        <input type="hidden" name="wlm_free_storage[enabled]" value="0">
        <label for="wlm-free-storage-enabled">
            <input type="checkbox" id="wlm-free-storage-enabled" name="wlm_free_storage[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
            <?php esc_html_e('Gratis Einlagerung zur Auswahl anbieten', 'woo-lieferzeiten-manager'); ?>
        </label>
        <p class="description wlm-free-storage-help"><?php esc_html_e('„So schnell wie möglich“ bleibt vorausgewählt und nutzt die unveränderte reguläre Lieferzeitberechnung sowie die bisherigen Zahlungsmethoden.', 'woo-lieferzeiten-manager'); ?></p>
        <p class="description wlm-free-storage-help"><?php esc_html_e('Eine spätere Auswahl erzeugt eine kostenfreie Bestellposition „Gratis Einlagerung – Lieferung [Zeitraum]“. Beim Zurückwechseln auf „So schnell wie möglich“ wird sie aus dem Checkout entfernt.', 'woo-lieferzeiten-manager'); ?></p>
        <p class="description wlm-free-storage-help"><?php esc_html_e('Das Deaktivieren der Aktion ändert keine bereits gespeicherten Einlagerungsbestellungen oder deren vereinbarte Lieferzeitfenster.', 'woo-lieferzeiten-manager'); ?></p>
    </div>

    <div class="wlm-free-storage-panel">
        <h3><?php esc_html_e('Freigegebene Zahlungsmethoden für Einlagerung', 'woo-lieferzeiten-manager'); ?></h3>
        <p class="description"><?php esc_html_e('Nur diese Zahlungsmethoden dürfen bei einem späteren Lieferzeitfenster genutzt werden. Eine Freigabe hier aktiviert keine Zahlungsmethode in WooCommerce; deaktivierte Methoden bleiben im Checkout deaktiviert.', 'woo-lieferzeiten-manager'); ?></p>
        <fieldset class="wlm-free-storage-gateways">
            <legend class="screen-reader-text"><?php esc_html_e('Zahlungsmethoden freigeben', 'woo-lieferzeiten-manager'); ?></legend>
            <?php foreach ($gateways as $gateway_id => $gateway): ?>
                <?php
                $gateway_title = (string) $gateway->get_title();
                if ('' === trim($gateway_title)) {
                    $gateway_title = (string) $gateway->get_method_title();
                }
                if ('' === trim($gateway_title)) {
                    $gateway_title = $gateway_id;
                }
                $gateway_status = isset($gateway->enabled) && 'yes' === $gateway->enabled
                    ? __('aktiv', 'woo-lieferzeiten-manager')
                    : __('deaktiviert', 'woo-lieferzeiten-manager');
                ?>
                <label>
                    <input type="checkbox" class="wlm-free-storage-gateway" name="wlm_free_storage[allowed_gateways][]" value="<?php echo esc_attr($gateway_id); ?>" <?php checked(in_array($gateway_id, $settings['allowed_gateways'], true)); ?>>
                    <?php echo esc_html($gateway_title); ?>
                    <span class="description"><?php echo esc_html('(' . $gateway_id . ' · ' . $gateway_status . ')'); ?></span>
                </label>
            <?php endforeach; ?>
            <?php if (empty($gateways)): ?>
                <p><?php esc_html_e('Es sind derzeit keine Zahlungsmethoden registriert.', 'woo-lieferzeiten-manager'); ?></p>
            <?php endif; ?>
        </fieldset>
        <div id="wlm-free-storage-gateway-warning" class="notice notice-warning inline" role="status" <?php echo !empty($settings['allowed_gateways']) ? 'hidden' : ''; ?>>
            <p><strong><?php esc_html_e('Keine Zahlungsmethode freigegeben:', 'woo-lieferzeiten-manager'); ?></strong> <?php esc_html_e('Ohne Freigabe ist keine Einlagerung im Checkout möglich, auch wenn die Aktion aktiviert ist. Wählen Sie mindestens eine geeignete Zahlungsmethode aus. Sie können die Konfiguration trotzdem speichern.', 'woo-lieferzeiten-manager'); ?></p>
        </div>
    </div>

    <div class="wlm-free-storage-panel">
        <h3><?php esc_html_e('Lieferzeitfenster', 'woo-lieferzeiten-manager'); ?></h3>
        <p id="wlm-free-storage-periods-help"><?php esc_html_e('Die Datumsbereiche beschreiben das Eintreffen der gesamten Bestellung beim Kunden – nicht die Versandwoche. Die Bezeichnung ist optional; bei leerem Feld wird sie automatisch aus dem Zeitraum erzeugt.', 'woo-lieferzeiten-manager'); ?></p>
        <div class="wlm-free-storage-table-wrap">
            <table class="widefat striped wlm-free-storage-periods" aria-describedby="wlm-free-storage-periods-help">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Beginn (Eintreffen)', 'woo-lieferzeiten-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Ende (Eintreffen)', 'woo-lieferzeiten-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Bezeichnung (optional)', 'woo-lieferzeiten-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('SKU (automatisch)', 'woo-lieferzeiten-manager'); ?></th>
                        <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Aktionen', 'woo-lieferzeiten-manager'); ?></span></th>
                    </tr>
                </thead>
                <tbody id="wlm-free-storage-periods">
                    <?php foreach ($settings['periods'] as $index => $period): ?>
                        <?php
                        // The core stores generated labels. Show them as empty so changing
                        // dates continues to generate the label instead of retaining old dates.
                        $automatic_label = substr($period['start'], 8, 2) . '.' . substr($period['start'], 5, 2) . '.' . substr($period['start'], 0, 4)
                            . ' – ' . substr($period['end'], 8, 2) . '.' . substr($period['end'], 5, 2) . '.' . substr($period['end'], 0, 4);
                        $input_label = ($period['label'] ?? '') === $automatic_label ? '' : ($period['label'] ?? '');
                        ?>
                        <tr class="wlm-free-storage-period" data-period-id="<?php echo esc_attr($period['id']); ?>">
                            <td>
                                <input type="hidden" data-field="id" name="wlm_free_storage[periods][<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($period['id']); ?>">
                                <input type="date" data-field="start" name="wlm_free_storage[periods][<?php echo esc_attr($index); ?>][start]" value="<?php echo esc_attr($period['start']); ?>" required aria-label="<?php esc_attr_e('Beginn (Eintreffen beim Kunden)', 'woo-lieferzeiten-manager'); ?>">
                            </td>
                            <td>
                                <input type="date" data-field="end" name="wlm_free_storage[periods][<?php echo esc_attr($index); ?>][end]" value="<?php echo esc_attr($period['end']); ?>" min="<?php echo esc_attr($period['start']); ?>" required aria-label="<?php esc_attr_e('Ende (Eintreffen beim Kunden)', 'woo-lieferzeiten-manager'); ?>">
                            </td>
                            <td>
                                <input type="text" data-field="label" name="wlm_free_storage[periods][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($input_label); ?>" placeholder="<?php esc_attr_e('Automatisch aus dem Zeitraum', 'woo-lieferzeiten-manager'); ?>" aria-label="<?php esc_attr_e('Bezeichnung (optional)', 'woo-lieferzeiten-manager'); ?>">
                            </td>
                            <td><code class="wlm-free-storage-sku"><?php echo esc_html($period['sku']); ?></code></td>
                            <td><button type="button" class="button wlm-free-storage-remove-period" aria-label="<?php esc_attr_e('Lieferzeitfenster entfernen', 'woo-lieferzeiten-manager'); ?>"><?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="wlm-free-storage-empty-periods" class="description" <?php echo !empty($settings['periods']) ? 'hidden' : ''; ?>><?php esc_html_e('Es sind keine Lieferzeitfenster konfiguriert.', 'woo-lieferzeiten-manager'); ?></p>
        <p><button type="button" id="wlm-free-storage-add-period" class="button"><?php esc_html_e('Lieferzeitfenster hinzufügen', 'woo-lieferzeiten-manager'); ?></button></p>
        <p class="description"><?php esc_html_e('Zu frühe oder nicht erreichbare Lieferzeitfenster werden im Checkout ausgegraut und serverseitig abgewiesen. Versandart, Werktage und konfigurierte Feiertage werden berücksichtigt. Nach Änderungen am Warenkorb wird die Auswahl erneut geprüft.', 'woo-lieferzeiten-manager'); ?></p>
        <p class="description"><?php esc_html_e('Jede Option erhält automatisch eine dauerhafte SKU: EINLAGER-001, EINLAGER-002 usw. Neue Optionen bekommen beim Speichern die nächste Nummer. Änderungen an Datum, Bezeichnung oder Reihenfolge ändern die SKU nicht; gelöschte Nummern werden nicht neu vergeben.', 'woo-lieferzeiten-manager'); ?></p>
        <p class="description"><?php esc_html_e('Speichern Sie über „Änderungen speichern“ im bestehenden WooCommerce-Formular. Leere oder ungültige Zeilen müssen vor dem Speichern vervollständigt oder entfernt werden.', 'woo-lieferzeiten-manager'); ?></p>
    </div>
</div>
