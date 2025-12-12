<?php
/**
 * Notifications Settings Tab
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wlm_settings', array());
$notification_enabled = $settings['ship_notification_enabled'] ?? false;
$notification_email = $settings['ship_notification_email'] ?? get_option('admin_email');
$notification_time = $settings['ship_notification_time'] ?? '08:00';
$send_empty = $settings['ship_notification_send_empty'] ?? false;

// Get next scheduled time
$next_run = wp_next_scheduled('wlm_daily_ship_notification');
$next_run_formatted = $next_run ? date_i18n('d.m.Y H:i', $next_run) : 'Nicht geplant';
?>

<div class="wlm-settings-section">
    <h2>📧 Versandbenachrichtigungen</h2>
    <p class="description">
        Konfigurieren Sie tägliche E-Mail-Benachrichtigungen mit allen Bestellungen, die heute versendet werden müssen.
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="ship_notification_enabled">Benachrichtigungen aktivieren</label>
            </th>
            <td>
                <label class="wlm-toggle">
                    <input type="checkbox" 
                           id="ship_notification_enabled" 
                           name="wlm_settings[ship_notification_enabled]" 
                           value="1" 
                           <?php checked($notification_enabled, true); ?>>
                    <span class="wlm-toggle-slider"></span>
                </label>
                <p class="description">
                    Sendet täglich eine E-Mail mit allen Bestellungen, die heute versendet werden müssen (basierend auf Ship-By-Date).
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="ship_notification_email">E-Mail-Adresse</label>
            </th>
            <td>
                <input type="email" 
                       id="ship_notification_email" 
                       name="wlm_settings[ship_notification_email]" 
                       value="<?php echo esc_attr($notification_email); ?>" 
                       class="regular-text"
                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                <p class="description">
                    E-Mail-Adresse, an die die tägliche Versandliste gesendet wird.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="ship_notification_time">Uhrzeit</label>
            </th>
            <td>
                <input type="time" 
                       id="ship_notification_time" 
                       name="wlm_settings[ship_notification_time]" 
                       value="<?php echo esc_attr($notification_time); ?>" 
                       class="regular-text">
                <p class="description">
                    Uhrzeit, zu der die E-Mail täglich versendet wird (z.B. 08:00 für 8 Uhr morgens).
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="ship_notification_send_empty">Leere Benachrichtigungen senden</label>
            </th>
            <td>
                <label class="wlm-toggle">
                    <input type="checkbox" 
                           id="ship_notification_send_empty" 
                           name="wlm_settings[ship_notification_send_empty]" 
                           value="1" 
                           <?php checked($send_empty, true); ?>>
                    <span class="wlm-toggle-slider"></span>
                </label>
                <p class="description">
                    Wenn aktiviert, wird die E-Mail auch gesendet, wenn keine Bestellungen versendet werden müssen.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">Nächste Ausführung</th>
            <td>
                <p style="margin: 0;">
                    <strong><?php echo esc_html($next_run_formatted); ?></strong>
                </p>
                <p class="description">
                    Der Cronjob wird automatisch täglich zur konfigurierten Uhrzeit ausgeführt.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">Externe Cronjob-URL</th>
            <td>
                <?php
                $cron_key = get_option('wlm_cron_secret_key');
                if (empty($cron_key)) {
                    $cron_key = wp_generate_password(32, false);
                    update_option('wlm_cron_secret_key', $cron_key);
                }
                $cron_url = rest_url('wlm/v1/cron/ship-notification');
                $full_url = $cron_url . '?key=' . $cron_key;
                ?>
                <input type="text" 
                       value="<?php echo esc_attr($full_url); ?>" 
                       readonly 
                       class="large-text" 
                       onclick="this.select();" 
                       style="font-family: monospace; background: #fffacd; font-size: 13px;">
                <p class="description">
                    👆 <strong>Kopiere diese URL für deinen All-Inkl Cronjob</strong> (enthält bereits den Sicherheitsschlüssel)
                </p>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; color: #2271b1;">📋 Anleitung für All-Inkl Cronjob</summary>
                    <div style="background: #f5f5f5; padding: 15px; margin-top: 10px; border-radius: 4px;">
                        <ol style="margin: 0; padding-left: 20px;">
                            <li>Logge dich bei <strong>All-Inkl KAS</strong> ein</li>
                            <li>Gehe zu <strong>Tools → Cronjobs</strong></li>
                            <li>Klicke auf <strong>"Neuer Cronjob"</strong></li>
                            <li>Kopiere die gelbe URL oben und füge sie als <strong>URL</strong> ein</li>
                            <li>Wähle <strong>"Täglich"</strong> und stelle die Uhrzeit ein (z.B. 08:00)</li>
                            <li>Speichern</li>
                        </ol>
                        <p style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2271b1;">
                            <strong>💡 Tipp:</strong> Bei All-Inkl kannst du einfach die URL eingeben - keine curl-Befehle nötig!
                        </p>
                    </div>
                </details>
            </td>
        </tr>

        <tr>
            <th scope="row">Test-E-Mail senden</th>
            <td>
                <button type="button" id="wlm-send-test-notification" class="button button-secondary">
                    📧 Test-E-Mail jetzt senden
                </button>
                <p class="description">
                    Sendet sofort eine Test-E-Mail mit den aktuellen Bestellungen, die heute versendet werden müssen.
                </p>
                <div id="wlm-test-notification-result" style="margin-top: 10px;"></div>
            </td>
        </tr>
    </table>

    <hr style="margin: 40px 0;">

    <h3>📋 Vorschau der E-Mail</h3>
    <p class="description">
        Die E-Mail enthält eine übersichtliche HTML-Tabelle mit folgenden Informationen:
    </p>
    <ul style="list-style: disc; margin-left: 20px;">
        <li>Bestellnummer mit Link zum Backend</li>
        <li>Kundenname</li>
        <li>Bestellte Artikel (Name, SKU, Menge)</li>
        <li>Versandart</li>
        <li>Lieferzeitraum</li>
        <li>Bestellstatus</li>
        <li>Gesamtbetrag</li>
    </ul>
    <p class="description">
        Überfällige Bestellungen (Ship-By-Date in der Vergangenheit) werden farblich hervorgehoben.
    </p>

    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
        <h4 style="margin: 0 0 10px 0;">💡 Hinweis</h4>
        <p style="margin: 0;">
            Die E-Mail wird nur an Bestellungen mit Status "Processing" oder "On-Hold" gesendet. 
            Bereits versendete oder abgeschlossene Bestellungen werden nicht berücksichtigt.
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wlm-send-test-notification').on('click', function() {
        var $button = $(this);
        var $result = $('#wlm-test-notification-result');
        
        $button.prop('disabled', true).text('⏳ Sende...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_notification',
                nonce: '<?php echo wp_create_nonce('wlm-admin-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>✅ ' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error inline"><p>❌ Fehler beim Senden der Test-E-Mail.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('📧 Test-E-Mail jetzt senden');
            }
        });
    });
});
</script>
