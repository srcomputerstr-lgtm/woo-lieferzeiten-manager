<?php
/**
 * Admin view for Surcharges tab
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wlm-tab-content">
    <h2><?php esc_html_e('Versandzuschläge', 'woo-lieferzeiten-manager'); ?></h2>
    
    <p class="description">
        <?php esc_html_e('Konfigurieren Sie Zuschläge für Sperrgut, Gefahrgut, Inselzustellung etc. basierend auf Produktattributen, Gewicht oder anderen Bedingungen.', 'woo-lieferzeiten-manager'); ?>
    </p>

    <div id="wlm-surcharges-list">
        <?php
        if (!empty($surcharges)) {
            foreach ($surcharges as $index => $surcharge) {
                ?>
                <div class="wlm-surcharge-item postbox" data-index="<?php echo esc_attr($index); ?>">
                    <div class="postbox-header">
                        <h3 class="hndle">
                            <span class="wlm-surcharge-title"><?php echo esc_html($surcharge['name'] ?? __('Neuer Zuschlag', 'woo-lieferzeiten-manager')); ?></span>
                        </h3>
                        <div class="handle-actions">
                            <button type="button" class="handlediv button-link" aria-expanded="true">
                                <span class="screen-reader-text"><?php esc_html_e('Umschalten', 'woo-lieferzeiten-manager'); ?></span>
                                <span class="toggle-indicator" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Name', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               name="wlm_surcharges[<?php echo $index; ?>][name]" 
                                               value="<?php echo esc_attr($surcharge['name'] ?? ''); ?>" 
                                               class="regular-text wlm-surcharge-name-input">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Aktiviert', 'woo-lieferzeiten-manager'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wlm_surcharges[<?php echo $index; ?>][enabled]" 
                                                   value="1" 
                                                   <?php checked($surcharge['enabled'] ?? true, true); ?>>
                                            <?php esc_html_e('Zuschlag aktivieren', 'woo-lieferzeiten-manager'); ?>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Betrag (netto)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][amount]" 
                                               value="<?php echo esc_attr($surcharge['amount'] ?? 0); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text"> €
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Steuerklasse', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wlm_surcharges[<?php echo $index; ?>][tax_class]">
                                            <option value="" <?php selected($surcharge['tax_class'] ?? '', ''); ?>>
                                                <?php esc_html_e('Standard', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <?php
                                            $tax_classes = WC_Tax::get_tax_classes();
                                            foreach ($tax_classes as $class) {
                                                $class_slug = sanitize_title($class);
                                                ?>
                                                <option value="<?php echo esc_attr($class_slug); ?>" <?php selected($surcharge['tax_class'] ?? '', $class_slug); ?>>
                                                    <?php echo esc_html($class); ?>
                                                </option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Gewicht Min/Max (kg)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][weight_min]" 
                                               value="<?php echo esc_attr($surcharge['weight_min'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Min">
                                        –
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][weight_max]" 
                                               value="<?php echo esc_attr($surcharge['weight_max'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Max">
                                        <p class="description">
                                            <?php esc_html_e('Leer lassen, um keine Gewichtsbeschränkung zu setzen.', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Warenkorbwert Min/Max (€)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][cart_value_min]" 
                                               value="<?php echo esc_attr($surcharge['cart_value_min'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Min">
                                        –
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][cart_value_max]" 
                                               value="<?php echo esc_attr($surcharge['cart_value_max'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Max">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Produktattribute', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <textarea name="wlm_surcharges[<?php echo $index; ?>][attributes]" 
                                                  rows="3" 
                                                  class="large-text" 
                                                  placeholder="pa_sperrgut=ja"><?php echo esc_textarea($surcharge['attributes'] ?? ''); ?></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Ein Attribut pro Zeile im Format: attribut_slug=wert (z.B. pa_sperrgut=ja)', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Stacking-Regel', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wlm_surcharges[<?php echo $index; ?>][stacking]">
                                            <option value="add" <?php selected($surcharge['stacking'] ?? 'add', 'add'); ?>>
                                                <?php esc_html_e('Addieren', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="max" <?php selected($surcharge['stacking'] ?? 'add', 'max'); ?>>
                                                <?php esc_html_e('Maximum', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="first_match" <?php selected($surcharge['stacking'] ?? 'add', 'first_match'); ?>>
                                                <?php esc_html_e('Erster Treffer', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Wie mehrere Zuschläge kombiniert werden sollen.', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Optionen', 'woo-lieferzeiten-manager'); ?>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" 
                                                       name="wlm_surcharges[<?php echo $index; ?>][ignore_free_shipping]" 
                                                       value="1" 
                                                       <?php checked($surcharge['ignore_free_shipping'] ?? true, true); ?>>
                                                <?php esc_html_e('Versandkostenfreigrenze ignorieren', 'woo-lieferzeiten-manager'); ?>
                                            </label>
                                            <br>
                                            <label>
                                                <input type="checkbox" 
                                                       name="wlm_surcharges[<?php echo $index; ?>][discountable]" 
                                                       value="1" 
                                                       <?php checked($surcharge['discountable'] ?? false, true); ?>>
                                                <?php esc_html_e('Rabattierbar', 'woo-lieferzeiten-manager'); ?>
                                            </label>
                                            <br>
                                            <label>
                                                <input type="checkbox" 
                                                       name="wlm_surcharges[<?php echo $index; ?>][apply_to_express]" 
                                                       value="1" 
                                                       <?php checked($surcharge['apply_to_express'] ?? true, true); ?>>
                                                <?php esc_html_e('Auch bei Express anwenden', 'woo-lieferzeiten-manager'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                        <input type="hidden" 
                                               name="wlm_surcharges[<?php echo $index; ?>][id]" 
                                               value="<?php echo esc_attr($surcharge['id'] ?? uniqid('wlm_surcharge_')); ?>">
                                        <button type="button" class="button wlm-remove-surcharge">
                                            <?php esc_html_e('Zuschlag entfernen', 'woo-lieferzeiten-manager'); ?>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>

    <button type="button" id="wlm-add-surcharge" class="button">
        <?php esc_html_e('Zuschlag hinzufügen', 'woo-lieferzeiten-manager'); ?>
    </button>
</div>
