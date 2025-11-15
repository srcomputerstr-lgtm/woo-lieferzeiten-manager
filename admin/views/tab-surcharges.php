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

    <!-- Global Surcharge Strategy -->
    <div class="wlm-global-settings postbox" style="margin-bottom: 20px;">
        <div class="postbox-header">
            <h3 class="hndle"><?php esc_html_e('Globale Einstellungen', 'woo-lieferzeiten-manager'); ?></h3>
        </div>
        <div class="inside">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="wlm_surcharge_application_strategy"><?php esc_html_e('Zuschlag-Anwendung', 'woo-lieferzeiten-manager'); ?></label>
                        </th>
                        <td>
                            <select name="wlm_surcharge_application_strategy" id="wlm_surcharge_application_strategy" class="regular-text">
                                <option value="all_charges" <?php selected($surcharge_application_strategy ?? 'all_charges', 'all_charges'); ?>>
                                    <?php esc_html_e('Alle Zuschläge (alle passenden Zuschläge addieren)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="first_match" <?php selected($surcharge_application_strategy ?? 'all_charges', 'first_match'); ?>>
                                    <?php esc_html_e('Erster Treffer (nur ersten passenden Zuschlag)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="smallest" <?php selected($surcharge_application_strategy ?? 'all_charges', 'smallest'); ?>>
                                    <?php esc_html_e('Kleinster Zuschlag (nur günstigsten Zuschlag)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="largest" <?php selected($surcharge_application_strategy ?? 'all_charges', 'largest'); ?>>
                                    <?php esc_html_e('Größter Zuschlag (nur teuersten Zuschlag)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="disabled" <?php selected($surcharge_application_strategy ?? 'all_charges', 'disabled'); ?>>
                                    <?php esc_html_e('Deaktiviert (keine Zuschläge anwenden)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Legt fest, welche Zuschläge angewendet werden, wenn mehrere Zuschläge auf den Warenkorb zutreffen.', 'woo-lieferzeiten-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

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
                                <!-- Grundeinstellungen -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Name', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               name="wlm_surcharges[<?php echo $index; ?>][name]" 
                                               value="<?php echo esc_attr($surcharge['name'] ?? ''); ?>" 
                                               class="regular-text wlm-surcharge-name-input">
                                        <p class="description"><?php esc_html_e('Interner Name des Zuschlags (nicht sichtbar für Kunden)', 'woo-lieferzeiten-manager'); ?></p>
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
                                        <label><?php esc_html_e('Priorität', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][priority]" 
                                               value="<?php echo esc_attr($surcharge['priority'] ?? 10); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="small-text">
                                        <p class="description"><?php esc_html_e('Niedrigere Zahlen = höhere Priorität (für "Erster Treffer" Strategie)', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Kosten -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Kostentyp', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wlm_surcharges[<?php echo $index; ?>][cost_type]" class="regular-text">
                                            <option value="flat" <?php selected($surcharge['cost_type'] ?? 'flat', 'flat'); ?>>
                                                <?php esc_html_e('Pauschalbetrag (€)', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="percentage" <?php selected($surcharge['cost_type'] ?? 'flat', 'percentage'); ?>>
                                                <?php esc_html_e('Prozentual (%)', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Betrag', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_surcharges[<?php echo $index; ?>][amount]" 
                                               value="<?php echo esc_attr($surcharge['amount'] ?? 0); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text">
                                        <span class="description"><?php esc_html_e('€ (bei Pauschalbetrag) oder % (bei Prozentual)', 'woo-lieferzeiten-manager'); ?></span>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Berechnung pro', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wlm_surcharges[<?php echo $index; ?>][charge_per]" class="regular-text">
                                            <option value="cart" <?php selected($surcharge['charge_per'] ?? 'cart', 'cart'); ?>>
                                                <?php esc_html_e('Warenkorb (einmalig)', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="shipping_class" <?php selected($surcharge['charge_per'] ?? 'cart', 'shipping_class'); ?>>
                                                <?php esc_html_e('Versandklasse', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="product_category" <?php selected($surcharge['charge_per'] ?? 'cart', 'product_category'); ?>>
                                                <?php esc_html_e('Produktkategorie', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="product" <?php selected($surcharge['charge_per'] ?? 'cart', 'product'); ?>>
                                                <?php esc_html_e('Produkt', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="cart_item" <?php selected($surcharge['charge_per'] ?? 'cart', 'cart_item'); ?>>
                                                <?php esc_html_e('Warenkorb-Position', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="quantity_unit" <?php selected($surcharge['charge_per'] ?? 'cart', 'quantity_unit'); ?>>
                                                <?php esc_html_e('Mengeneinheit (Stück)', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Bestimmt, wie oft der Zuschlag berechnet wird', 'woo-lieferzeiten-manager'); ?></p>
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

                                <!-- Bedingungen: Gewicht -->
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

                                <!-- Bedingungen: Warenkorbwert -->
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
                                        <p class="description"><?php esc_html_e('Warenkorbsumme (netto) als Bedingung', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Bedingungen: Produktattribute / Taxonomien -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Produktattribute / Taxonomien', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <div class="wlm-attribute-conditions" data-surcharge-index="<?php echo $index; ?>">
                                            <?php
                                            // Get all product attributes
                                            $attribute_taxonomies = wc_get_attribute_taxonomies();
                                            
                                            // Parse existing conditions
                                            $existing_conditions = array();
                                            
                                            // Check if attribute_conditions array exists (new format)
                                            if (!empty($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])) {
                                                $existing_conditions = $surcharge['attribute_conditions'];
                                            }
                                            
                                            // Display existing conditions
                                            if (!empty($existing_conditions)) {
                                                foreach ($existing_conditions as $cond_index => $condition) {
                                                    // Convert old format to new format
                                                    if (isset($condition['value']) && !isset($condition['values'])) {
                                                        $condition['values'] = array($condition['value']);
                                                        $condition['logic'] = 'at_least_one';
                                                    }
                                                    $logic = $condition['logic'] ?? 'at_least_one';
                                                    $values = $condition['values'] ?? array();
                                                    $condition_type = $condition['type'] ?? 'attribute';
                                                    ?>
                                                    <div class="wlm-attribute-condition-row" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                                                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                                            <!-- Logik-Operator -->
                                                            <select name="wlm_surcharges[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][logic]" class="wlm-logic-select" style="width: 150px;">
                                                                <option value="at_least_one" <?php selected($logic, 'at_least_one'); ?>><?php esc_html_e('at least one of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="all" <?php selected($logic, 'all'); ?>><?php esc_html_e('all of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="none" <?php selected($logic, 'none'); ?>><?php esc_html_e('none of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="only" <?php selected($logic, 'only'); ?>><?php esc_html_e('only', 'woo-lieferzeiten-manager'); ?></option>
                                                            </select>
                                                            
                                                            <!-- Bedingungstyp -->
                                                            <select name="wlm_surcharges[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][type]" class="wlm-condition-type-select" style="width: 150px;">
                                                                <option value="attribute" <?php selected($condition_type, 'attribute'); ?>><?php esc_html_e('Attribut', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="taxonomy" <?php selected($condition_type, 'taxonomy'); ?>><?php esc_html_e('Taxonomie', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="shipping_class" <?php selected($condition_type, 'shipping_class'); ?>><?php esc_html_e('Versandklasse', 'woo-lieferzeiten-manager'); ?></option>
                                                            </select>
                                                            
                                                            <!-- Attribut/Taxonomie -->
                                                            <select name="wlm_surcharges[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][attribute]" class="wlm-attribute-select" style="width: 200px;">
                                                                <option value=""><?php esc_html_e('-- Wählen --', 'woo-lieferzeiten-manager'); ?></option>
                                                                <optgroup label="<?php esc_attr_e('Produkt-Attribute', 'woo-lieferzeiten-manager'); ?>">
                                                                    <?php
                                                                    foreach ($attribute_taxonomies as $tax) {
                                                                        $attr_name = wc_attribute_taxonomy_name($tax->attribute_name);
                                                                        $selected = ($condition['attribute'] === $attr_name) ? 'selected' : '';
                                                                        echo '<option value="' . esc_attr($attr_name) . '" ' . $selected . '>' . esc_html($tax->attribute_label) . '</option>';
                                                                    }
                                                                    ?>
                                                                </optgroup>
                                                                <optgroup label="<?php esc_attr_e('Taxonomien', 'woo-lieferzeiten-manager'); ?>">
                                                                    <option value="product_cat" <?php selected($condition['attribute'], 'product_cat'); ?>><?php esc_html_e('Produktkategorie', 'woo-lieferzeiten-manager'); ?></option>
                                                                    <option value="product_tag" <?php selected($condition['attribute'], 'product_tag'); ?>><?php esc_html_e('Produkt-Tag', 'woo-lieferzeiten-manager'); ?></option>
                                                                </optgroup>
                                                                <optgroup label="<?php esc_attr_e('Versandklassen', 'woo-lieferzeiten-manager'); ?>">
                                                                    <?php
                                                                    $shipping_classes = WC()->shipping()->get_shipping_classes();
                                                                    foreach ($shipping_classes as $shipping_class) {
                                                                        $selected = ($condition['attribute'] === $shipping_class->slug) ? 'selected' : '';
                                                                        echo '<option value="' . esc_attr($shipping_class->slug) . '" ' . $selected . '>' . esc_html($shipping_class->name) . '</option>';
                                                                    }
                                                                    ?>
                                                                </optgroup>
                                                            </select>
                                                            
                                                            <button type="button" class="button wlm-remove-attribute-condition" style="margin-left: auto;"><?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?></button>
                                                        </div>
                                                        
                                                        <!-- Werte (Select2 Multiselect) -->
                                                        <div class="wlm-condition-values" style="margin-top: 10px;">
                                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e('Werte:', 'woo-lieferzeiten-manager'); ?></label>
                                                            <select 
                                                                multiple="multiple" 
                                                                class="wlm-values-select2" 
                                                                name="wlm_surcharges[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][values][]" 
                                                                data-attribute="<?php echo esc_attr($condition['attribute'] ?? ''); ?>"
                                                                data-surcharge-index="<?php echo $index; ?>"
                                                                data-condition-index="<?php echo $cond_index; ?>"
                                                                style="width: 100%;">
                                                                <?php
                                                                if (!empty($values)) {
                                                                    foreach ($values as $value) {
                                                                        echo '<option value="' . esc_attr($value) . '" selected>' . esc_html($value) . '</option>';
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                        
                                        <button type="button" class="button wlm-add-attribute-condition" data-surcharge-index="<?php echo $index; ?>">
                                            <?php esc_html_e('+ Bedingung hinzufügen', 'woo-lieferzeiten-manager'); ?>
                                        </button>
                                        
                                        <p class="description">
                                            <?php esc_html_e('Wählen Sie Attribute, Taxonomien oder Versandklassen aus und geben Sie den gewünschten Wert ein.', 'woo-lieferzeiten-manager'); ?><br>
                                            <?php esc_html_e('Zuschlag wird nur angewendet, wenn ALLE Bedingungen erfüllt sind.', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Optionen -->
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

<!-- Template for new attribute conditions -->
<script type="text/template" id="wlm-surcharge-condition-template">
    <div class="wlm-attribute-condition-row" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
            <!-- Logik-Operator -->
            <select name="wlm_surcharges[{{SURCHARGE_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][logic]" class="wlm-logic-select" style="width: 150px;">
                <option value="at_least_one"><?php esc_html_e('at least one of', 'woo-lieferzeiten-manager'); ?></option>
                <option value="all"><?php esc_html_e('all of', 'woo-lieferzeiten-manager'); ?></option>
                <option value="none"><?php esc_html_e('none of', 'woo-lieferzeiten-manager'); ?></option>
                <option value="only"><?php esc_html_e('only', 'woo-lieferzeiten-manager'); ?></option>
            </select>
            
            <!-- Bedingungstyp -->
            <select name="wlm_surcharges[{{SURCHARGE_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][type]" class="wlm-condition-type-select" style="width: 150px;">
                <option value="attribute"><?php esc_html_e('Attribut', 'woo-lieferzeiten-manager'); ?></option>
                <option value="taxonomy"><?php esc_html_e('Taxonomie', 'woo-lieferzeiten-manager'); ?></option>
                <option value="shipping_class"><?php esc_html_e('Versandklasse', 'woo-lieferzeiten-manager'); ?></option>
            </select>
            
            <!-- Attribut/Taxonomie/Versandklasse -->
            <select name="wlm_surcharges[{{SURCHARGE_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][attribute]" class="wlm-attribute-select" style="width: 200px;">
                <option value=""><?php esc_html_e('-- Wählen --', 'woo-lieferzeiten-manager'); ?></option>
                <optgroup label="<?php esc_attr_e('Produkt-Attribute', 'woo-lieferzeiten-manager'); ?>">
                    <?php
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    foreach ($attribute_taxonomies as $tax) {
                        $attr_name = wc_attribute_taxonomy_name($tax->attribute_name);
                        echo '<option value="' . esc_attr($attr_name) . '">' . esc_html($tax->attribute_label) . '</option>';
                    }
                    ?>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Taxonomien', 'woo-lieferzeiten-manager'); ?>">
                    <option value="product_cat"><?php esc_html_e('Produktkategorie', 'woo-lieferzeiten-manager'); ?></option>
                    <option value="product_tag"><?php esc_html_e('Produkt-Tag', 'woo-lieferzeiten-manager'); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Versandklassen', 'woo-lieferzeiten-manager'); ?>">
                    <?php
                    $shipping_classes = WC()->shipping()->get_shipping_classes();
                    foreach ($shipping_classes as $shipping_class) {
                        echo '<option value="' . esc_attr($shipping_class->slug) . '">' . esc_html($shipping_class->name) . '</option>';
                    }
                    ?>
                </optgroup>
            </select>
            
            <button type="button" class="button wlm-remove-attribute-condition" style="margin-left: auto;"><?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?></button>
        </div>
        
        <!-- Werte (Select2 Multiselect) -->
        <div class="wlm-condition-values" style="margin-top: 10px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e('Werte:', 'woo-lieferzeiten-manager'); ?></label>
            <select 
                multiple="multiple" 
                class="wlm-values-select2" 
                name="wlm_surcharges[{{SURCHARGE_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][values][]" 
                data-attribute=""
                data-surcharge-index="{{SURCHARGE_INDEX}}"
                data-condition-index="{{CONDITION_INDEX}}"
                style="width: 100%;">
            </select>
        </div>
    </div>
</script>
