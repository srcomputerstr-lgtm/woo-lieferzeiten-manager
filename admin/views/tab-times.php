<?php
/**
 * Admin view for Times tab
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

$cutoff_time = isset($settings['cutoff_time']) ? $settings['cutoff_time'] : '14:00';
$business_days = isset($settings['business_days']) ? $settings['business_days'] : array(1, 2, 3, 4, 5);
$holidays = isset($settings['holidays']) ? $settings['holidays'] : array();
$processing_min = isset($settings['processing_min']) ? $settings['processing_min'] : 1;
$processing_max = isset($settings['processing_max']) ? $settings['processing_max'] : 2;
$default_lead_time = isset($settings['default_lead_time']) ? $settings['default_lead_time'] : 3;
$max_visible_stock = isset($settings['max_visible_stock']) ? $settings['max_visible_stock'] : 100;
$debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
?>

<div class="wlm-tab-content">
    <h2><?php esc_html_e('Zeiteinstellungen', 'woo-lieferzeiten-manager'); ?></h2>
    
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="wlm_cutoff_time"><?php esc_html_e('Cutoff-Zeit', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <input type="time" 
                           id="wlm_cutoff_time" 
                           name="wlm_settings[cutoff_time]" 
                           value="<?php echo esc_attr($cutoff_time); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Bestellungen nach dieser Zeit werden erst am nächsten Werktag bearbeitet.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Werktage', 'woo-lieferzeiten-manager'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e('Werktage', 'woo-lieferzeiten-manager'); ?></span>
                        </legend>
                        <?php
                        $days = array(
                            1 => __('Montag', 'woo-lieferzeiten-manager'),
                            2 => __('Dienstag', 'woo-lieferzeiten-manager'),
                            3 => __('Mittwoch', 'woo-lieferzeiten-manager'),
                            4 => __('Donnerstag', 'woo-lieferzeiten-manager'),
                            5 => __('Freitag', 'woo-lieferzeiten-manager'),
                            6 => __('Samstag', 'woo-lieferzeiten-manager'),
                            7 => __('Sonntag', 'woo-lieferzeiten-manager')
                        );
                        
                        foreach ($days as $day_num => $day_name) {
                            $checked = in_array($day_num, $business_days) ? 'checked' : '';
                            ?>
                            <label>
                                <input type="checkbox" 
                                       name="wlm_settings[business_days][]" 
                                       value="<?php echo esc_attr($day_num); ?>" 
                                       <?php echo $checked; ?>>
                                <?php echo esc_html($day_name); ?>
                            </label><br>
                            <?php
                        }
                        ?>
                        <p class="description">
                            <?php esc_html_e('Wählen Sie die Tage aus, an denen Bestellungen bearbeitet werden.', 'woo-lieferzeiten-manager'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_holidays"><?php esc_html_e('Feiertage', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <div id="wlm-holidays-list">
                        <?php
                        if (!empty($holidays)) {
                            foreach ($holidays as $index => $holiday) {
                                ?>
                                <div class="wlm-holiday-item">
                                    <input type="date" 
                                           name="wlm_settings[holidays][]" 
                                           value="<?php echo esc_attr($holiday); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button wlm-remove-holiday">
                                        <?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?>
                                    </button>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <button type="button" id="wlm-add-holiday" class="button">
                        <?php esc_html_e('Feiertag hinzufügen', 'woo-lieferzeiten-manager'); ?>
                    </button>
                    <p class="description">
                        <?php esc_html_e('Fügen Sie Feiertage hinzu, die bei der Lieferzeitberechnung ausgeschlossen werden.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_processing_min"><?php esc_html_e('Bearbeitungszeit Min (Werktage)', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="wlm_processing_min" 
                           name="wlm_settings[processing_min]" 
                           value="<?php echo esc_attr($processing_min); ?>" 
                           min="0" 
                           step="1" 
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e('Minimale Bearbeitungszeit in Werktagen.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_processing_max"><?php esc_html_e('Bearbeitungszeit Max (Werktage)', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="wlm_processing_max" 
                           name="wlm_settings[processing_max]" 
                           value="<?php echo esc_attr($processing_max); ?>" 
                           min="0" 
                           step="1" 
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e('Maximale Bearbeitungszeit in Werktagen.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_default_lead_time"><?php esc_html_e('Standard-Lieferzeit (Tage)', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="wlm_default_lead_time" 
                           name="wlm_settings[default_lead_time]" 
                           value="<?php echo esc_attr($default_lead_time); ?>" 
                           min="0" 
                           step="1" 
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e('Standard-Lieferzeit für Produkte ohne spezifische Angabe.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_max_visible_stock"><?php esc_html_e('Maximal sichtbarer Bestand', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="wlm_max_visible_stock" 
                           name="wlm_settings[max_visible_stock]" 
                           value="<?php echo esc_attr($max_visible_stock); ?>" 
                           min="0" 
                           step="1" 
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e('Maximale Anzahl, die im Frontend als Lagerbestand angezeigt wird.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_out_of_stock_text"><?php esc_html_e('Nicht-auf-Lager-Text', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <?php
                    $out_of_stock_text = isset($settings['out_of_stock_text']) ? $settings['out_of_stock_text'] : 'Zurzeit nicht auf Lager';
                    ?>
                    <input type="text" 
                           id="wlm_out_of_stock_text" 
                           name="wlm_settings[out_of_stock_text]" 
                           value="<?php echo esc_attr($out_of_stock_text); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Text, der angezeigt wird, wenn ein Produkt nicht auf Lager ist (gelbe Ampel).', 'woo-lieferzeiten-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wlm_cronjob_time"><?php esc_html_e('Cronjob-Zeit', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <?php
                    $cronjob_time = isset($settings['cronjob_time']) ? $settings['cronjob_time'] : '01:00';
                    $last_run = get_option('wlm_cronjob_last_run', 0);
                    $last_count = get_option('wlm_cronjob_last_count', 0);
                    $next_scheduled = wp_next_scheduled('wlm_daily_availability_update');
                    ?>
                    <input type="time" 
                           id="wlm_cronjob_time" 
                           name="wlm_settings[cronjob_time]" 
                           value="<?php echo esc_attr($cronjob_time); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Uhrzeit für die tägliche Berechnung der Verfügbarkeitsdaten.', 'woo-lieferzeiten-manager'); ?>
                    </p>
                    
                    <?php if ($last_run > 0): ?>
                    <p class="description">
                        <strong><?php esc_html_e('Letzter Lauf:', 'woo-lieferzeiten-manager'); ?></strong> 
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_run); ?>
                        (<?php echo $last_count; ?> <?php esc_html_e('Produkte verarbeitet', 'woo-lieferzeiten-manager'); ?>)
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($next_scheduled): ?>
                    <p class="description">
                        <strong><?php esc_html_e('Nächster Lauf:', 'woo-lieferzeiten-manager'); ?></strong> 
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled); ?>
                    </p>
                    <?php endif; ?>
                    
                    <p>
                        <button type="button" id="wlm-run-cronjob-now" class="button button-secondary">
                            <?php esc_html_e('Jetzt ausführen', 'woo-lieferzeiten-manager'); ?>
                        </button>
                        <span id="wlm-cronjob-status" style="margin-left: 10px;"></span>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Externer Cronjob (All-Inkl, etc.)', 'woo-lieferzeiten-manager'); ?></label>
                </th>
                <td>
                    <?php
                    // Generate secret key if not exists
                    $secret_key = isset($settings['cronjob_secret_key']) ? $settings['cronjob_secret_key'] : '';
                    if (empty($secret_key)) {
                        $secret_key = wp_generate_password(32, false, false);
                        $settings['cronjob_secret_key'] = $secret_key;
                        update_option('wlm_settings', $settings);
                    }
                    
                    $cronjob_url = home_url('/wlm-cronjob/?key=' . $secret_key);
                    ?>
                    <p class="description" style="margin-bottom: 10px;">
                        <?php esc_html_e('Falls WordPress Cron nicht zuverlässig funktioniert, kannst du einen externen Cronjob einrichten (z.B. bei All-Inkl).', 'woo-lieferzeiten-manager'); ?>
                    </p>
                    
                    <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
                        <strong><?php esc_html_e('Cronjob-URL:', 'woo-lieferzeiten-manager'); ?></strong><br>
                        <input type="text" 
                               id="wlm-cronjob-url" 
                               value="<?php echo esc_attr($cronjob_url); ?>" 
                               readonly 
                               style="width: 100%; margin-top: 5px; font-family: monospace; font-size: 12px;"
                               onclick="this.select();">
                        <button type="button" 
                                class="button button-secondary" 
                                style="margin-top: 5px;"
                                onclick="navigator.clipboard.writeText(document.getElementById('wlm-cronjob-url').value); alert('URL kopiert!');">  
                            <?php esc_html_e('URL kopieren', 'woo-lieferzeiten-manager'); ?>
                        </button>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 10px;">
                        <strong>⚠️ <?php esc_html_e('Wichtig:', 'woo-lieferzeiten-manager'); ?></strong><br>
                        <?php esc_html_e('Diese URL enthält einen geheimen Schlüssel. Teile sie nicht öffentlich!', 'woo-lieferzeiten-manager'); ?><br>
                        <?php esc_html_e('Bei All-Inkl: Gehe zu "Cronjobs" und füge die URL hinzu. Empfohlen: Täglich zur gewünschten Zeit.', 'woo-lieferzeiten-manager'); ?>
                    </div>
                    
                    <details style="margin-top: 10px;">
                        <summary style="cursor: pointer; font-weight: bold;"><?php esc_html_e('Anleitung für All-Inkl Cronjob', 'woo-lieferzeiten-manager'); ?></summary>
                        <div style="padding: 10px; background: #f9f9f9; margin-top: 10px; border-radius: 4px;">
                            <ol style="margin-left: 20px;">
                                <li><?php esc_html_e('Logge dich in dein All-Inkl KAS ein', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Gehe zu "Tools" → "Cronjobs"', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Klicke auf "Neuen Cronjob anlegen"', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Wähle "URL aufrufen (wget)"', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Füge die obige URL ein', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Stelle die Zeit ein (z.B. täglich um 18:00 Uhr)', 'woo-lieferzeiten-manager'); ?></li>
                                <li><?php esc_html_e('Speichern – Fertig!', 'woo-lieferzeiten-manager'); ?></li>
                            </ol>
                        </div>
                    </details>
                    
                    <p style="margin-top: 10px;">
                        <button type="button" 
                                class="button button-secondary"
                                onclick="window.open(document.getElementById('wlm-cronjob-url').value, '_blank'); alert('Cronjob-URL wurde in neuem Tab geöffnet. Du solltest eine JSON-Antwort sehen.');">  
                            <?php esc_html_e('URL testen', 'woo-lieferzeiten-manager'); ?>
                        </button>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Debug-Modus', 'woo-lieferzeiten-manager'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e('Debug-Modus', 'woo-lieferzeiten-manager'); ?></span>
                        </legend>
                        <label>
                            <input type="checkbox" 
                                   name="wlm_settings[debug_mode]" 
                                   value="1" 
                                   <?php checked($debug_mode, true); ?>>
                            <?php esc_html_e('Debug-Modus aktivieren (nur für Administratoren sichtbar)', 'woo-lieferzeiten-manager'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Zeigt detaillierte Informationen zur Lieferzeitberechnung an.', 'woo-lieferzeiten-manager'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
</div>
