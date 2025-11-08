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
