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
                <label for="ship_notification_min_date">Bestellungen berücksichtigen ab</label>
            </th>
            <td>
                <input type="date" 
                       id="ship_notification_min_date" 
                       name="wlm_settings[ship_notification_min_date]" 
                       value="<?php echo esc_attr($settings['ship_notification_min_date'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">
                    Nur Bestellungen ab diesem Datum werden berücksichtigt. Leer lassen für alle Bestellungen.
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
            Die E-Mail wird nur an Bestellungen mit Status "Processing" gesendet. 
            Bestellungen mit Status "On-Hold" (unbezahlt) werden ausgeschlossen.
        </p>
    </div>
</div>

<div class="wlm-settings-section" style="margin-top: 40px;">
    <h2>📊 Täglicher Performance Report</h2>
    <p class="description">
        Erhalten Sie täglich einen automatischen Report mit KPIs zur Versandleistung des Vortages.
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="wlm_performance_report_enabled">Performance Report aktivieren</label>
            </th>
            <td>
                <label class="wlm-toggle">
                    <input type="checkbox" 
                           id="wlm_performance_report_enabled" 
                           name="wlm_performance_report_enabled" 
                           value="1" 
                           <?php checked(get_option('wlm_performance_report_enabled', false)); ?>>
                    <span class="wlm-toggle-slider"></span>
                </label>
                <p class="description">
                    Aktiviert tägliche Performance Reports mit Versand-KPIs.
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="wlm_performance_report_email">E-Mail-Adresse</label>
            </th>
            <td>
                <input type="email" 
                       id="wlm_performance_report_email" 
                       name="wlm_performance_report_email" 
                       value="<?php echo esc_attr(get_option('wlm_performance_report_email', get_option('admin_email'))); ?>" 
                       class="regular-text"
                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                <p class="description">
                    E-Mail-Adresse für den täglichen Performance Report.
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="wlm_performance_report_min_date">Bestellungen berücksichtigen ab</label>
            </th>
            <td>
                <input type="date" 
                       id="wlm_performance_report_min_date" 
                       name="wlm_performance_report_min_date" 
                       value="<?php echo esc_attr(get_option('wlm_performance_report_min_date', '')); ?>" 
                       class="regular-text">
                <p class="description">
                    Nur Bestellungen ab diesem Datum werden berücksichtigt. Leer lassen für alle Bestellungen.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="wlm_performance_report_send_empty">Leere Reports senden</label>
            </th>
            <td>
                <label class="wlm-toggle">
                    <input type="checkbox" 
                           id="wlm_performance_report_send_empty" 
                           name="wlm_performance_report_send_empty" 
                           value="1" 
                           <?php checked(get_option('wlm_performance_report_send_empty', false)); ?>>
                    <span class="wlm-toggle-slider"></span>
                </label>
                <p class="description">
                    Report auch senden, wenn keine Bestellungen im Zeitraum vorhanden sind.
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Externe Cronjob-URL (Performance Report)</th>
            <td>
                <?php 
                $perf_key = get_option('wlm_performance_report_cron_key');
                if (empty($perf_key)) {
                    $perf_key = bin2hex(random_bytes(16));
                    update_option('wlm_performance_report_cron_key', $perf_key);
                }
                $perf_url = rest_url('wlm/v1/cron/performance-report') . '?key=' . $perf_key;
                ?>
                <input type="text" 
                       value="<?php echo esc_attr($perf_url); ?>" 
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
                            <strong>💡 Empfehlung:</strong> Täglich um 08:00 Uhr für morgendliches Briefing
                        </p>
                    </div>
                </details>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Test-Report senden</th>
            <td>
                <button type="button" id="wlm-send-test-performance-report" class="button button-secondary">
                    📊 Test-Report jetzt senden
                </button>
                <p class="description">
                    Sendet sofort einen Test-Report mit den Daten von gestern.
                </p>
                <div id="wlm-test-performance-report-result" style="margin-top: 10px;"></div>
            </td>
        </tr>
    </table>

    <hr style="margin: 40px 0;">

    <h3>📊 KPIs im Performance Report</h3>
    <p class="description">
        Der tägliche Report enthält folgende Kennzahlen:
    </p>
    <ul style="list-style: disc; margin-left: 20px;">
        <li><strong>Pünktlichkeit:</strong> % der Bestellungen, die rechtzeitig versendet wurden</li>
        <li><strong>Überfällige Bestellungen:</strong> Anzahl und % der zu spät versendeten Bestellungen</li>
        <li><strong>Durchschnittliche Processing-Time:</strong> Tatsächliche vs. Soll-Bearbeitungszeit</li>
        <li><strong>Gesamtanzahl Bestellungen:</strong> Alle versendeten Bestellungen vom Vortag</li>
    </ul>
    <p class="description">
        Alle KPIs werden mit großen, farbigen Blöcken (Grün/Gelb/Rot) dargestellt für schnelle Übersicht.
    </p>

    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #F39200; border-radius: 4px;">
        <h4 style="margin: 0 0 10px 0;">💡 Hinweis</h4>
        <p style="margin: 0;">
            Der Report analysiert nur <strong>abgeschlossene Bestellungen</strong> (Status: "Completed") vom Vortag.
            So können Sie die tägliche Versandleistung und Einhaltung der Ship-By-Dates überprüfen.
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wlm-send-test-performance-report').on('click', function() {
        var $button = $(this);
        var $result = $('#wlm-test-performance-report-result');
        
        $button.prop('disabled', true).text('⏳ Sende...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_performance_report',
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
                $result.html('<div class="notice notice-error inline"><p>❌ Fehler beim Senden des Test-Reports.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('📊 Test-Report jetzt senden');
            }
        });
    });
});
</script>

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


<div class="wlm-settings-section" style="margin-top: 40px;">
    <h2>⚠️ Verzögerungs-Benachrichtigungen</h2>
    <p class="description">
        Senden Sie automatische E-Mails an Kunden, wenn das Ship-By-Date überschritten wurde und die Bestellung noch nicht versendet wurde.
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="wlm_delay_notification_enabled">Verzögerungs-Benachrichtigungen aktivieren</label>
            </th>
            <td>
                <label class="wlm-toggle">
                    <input type="checkbox" 
                           id="wlm_delay_notification_enabled" 
                           name="wlm_delay_notification_enabled" 
                           value="1" 
                           <?php checked(get_option('wlm_delay_notification_enabled', false)); ?>>
                    <span class="wlm-toggle-slider"></span>
                </label>
                <p class="description">
                    Aktiviert automatische Benachrichtigungen an Kunden bei Versandverzögerungen.
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="wlm_delay_notification_min_date">Bestellungen berücksichtigen ab</label>
            </th>
            <td>
                <input type="date" 
                       id="wlm_delay_notification_min_date" 
                       name="wlm_delay_notification_min_date" 
                       value="<?php echo esc_attr(get_option('wlm_delay_notification_min_date', '')); ?>" 
                       class="regular-text">
                <p class="description">
                    Nur Bestellungen ab diesem Datum werden berücksichtigt. Leer lassen für alle Bestellungen.
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="wlm_delay_notification_days">Verzögerung in Tagen</label>
            </th>
            <td>
                <input type="number" 
                       id="wlm_delay_notification_days" 
                       name="wlm_delay_notification_days" 
                       value="<?php echo esc_attr(get_option('wlm_delay_notification_days', 1)); ?>" 
                       min="1" 
                       max="30" 
                       class="small-text">
                <p class="description">
                    Anzahl der Tage nach Ship-By-Date, nach denen die Benachrichtigung gesendet wird (Standard: 1 Tag).
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="wlm_delay_notification_bcc">BCC E-Mail-Adresse</label>
            </th>
            <td>
                <input type="email" 
                       id="wlm_delay_notification_bcc" 
                       name="wlm_delay_notification_bcc" 
                       value="<?php echo esc_attr(get_option('wlm_delay_notification_bcc', '')); ?>" 
                       class="regular-text"
                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                <p class="description">
                    Optional: E-Mail-Adresse für BCC-Kopien aller Verzögerungs-Benachrichtigungen (z.B. für Controlling).
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Externe Cronjob-URL (Verzögerungs-Benachrichtigungen)</th>
            <td>
                <?php 
                $delay_key = get_option('wlm_delay_notification_cron_key');
                if (empty($delay_key)) {
                    $delay_key = bin2hex(random_bytes(16));
                    update_option('wlm_delay_notification_cron_key', $delay_key);
                }
                $delay_url = rest_url('wlm/v1/cron/delay-notification') . '?key=' . $delay_key;
                ?>
                <input type="text" 
                       value="<?php echo esc_attr($delay_url); ?>" 
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
                            <li>Wähle <strong>"Täglich"</strong> und stelle die Uhrzeit ein (z.B. 10:00)</li>
                            <li>Speichern</li>
                        </ol>
                        <p style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2271b1;">
                            <strong>💡 Empfehlung:</strong> Täglich um 10:00 Uhr ausführen
                        </p>
                    </div>
                </details>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Test-Benachrichtigung senden</th>
            <td>
                <button type="button" id="wlm-send-test-delay-notification" class="button button-secondary">
                    ⚠️ Test-Benachrichtigung jetzt senden
                </button>
                <p class="description">
                    Sendet sofort eine Test-Benachrichtigung für alle überfälligen Bestellungen.
                </p>
                <div id="wlm-test-delay-notification-result" style="margin-top: 10px;"></div>
            </td>
        </tr>
    </table>

    <hr style="margin: 40px 0;">

    <h3>📧 E-Mail-Inhalt</h3>
    <p class="description">
        Die E-Mail wird an den Kunden gesendet und enthält:
    </p>
    <ul style="list-style: disc; margin-left: 20px;">
        <li>Persönliche Anrede mit Kundennamen</li>
        <li>Bestellnummer und Bestelldetails</li>
        <li>Information über die Verzögerung</li>
        <li>Entschuldigung und Dankeschön</li>
        <li>Link zur Bestellübersicht</li>
    </ul>

    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <h4 style="margin: 0 0 10px 0;">⚠️ Wichtig</h4>
        <p style="margin: 0;">
            Die Benachrichtigung wird nur an Bestellungen mit Status <strong>"Processing"</strong> gesendet. 
            Bestellungen mit Status "Packed" oder "Completed" werden automatisch ausgeschlossen.
            Jede Bestellung erhält die Benachrichtigung nur einmal (wird in Order Meta gespeichert).
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wlm-send-test-delay-notification').on('click', function() {
        var $button = $(this);
        var $result = $('#wlm-test-delay-notification-result');
        
        $button.prop('disabled', true).text('⏳ Sende...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_delay_notification',
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
                $result.html('<div class="notice notice-error inline"><p>❌ Fehler beim Senden der Test-Benachrichtigung.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('⚠️ Test-Benachrichtigung jetzt senden');
            }
        });
    });
});
</script>
