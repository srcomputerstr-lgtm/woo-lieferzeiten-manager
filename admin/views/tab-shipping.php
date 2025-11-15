<?php
/**
 * Admin view for Shipping tab
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wlm-tab-content">
    <h2><?php esc_html_e('Versandarten', 'woo-lieferzeiten-manager'); ?></h2>
    
    <p class="description">
        <?php esc_html_e('Konfigurieren Sie Ihre Versandarten mit individuellen Bedingungen, Kosten und Lieferzeiten. Die Versandarten werden automatisch als WooCommerce-Versandmethoden registriert.', 'woo-lieferzeiten-manager'); ?>
    </p>

    <!-- Global Shipping Selection Strategy -->
    <div class="wlm-global-settings postbox" style="margin-bottom: 20px;">
        <div class="postbox-header">
            <h3 class="hndle"><?php esc_html_e('Globale Einstellungen', 'woo-lieferzeiten-manager'); ?></h3>
        </div>
        <div class="inside">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="wlm_shipping_selection_strategy"><?php esc_html_e('Versandarten-Auswahl', 'woo-lieferzeiten-manager'); ?></label>
                        </th>
                        <td>
                            <select name="wlm_shipping_selection_strategy" id="wlm_shipping_selection_strategy" class="regular-text">
                                <option value="customer_choice" <?php selected($shipping_selection_strategy ?? 'customer_choice', 'customer_choice'); ?>>
                                    <?php esc_html_e('Kunde wählt (alle verfügbaren Versandarten anzeigen)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="by_priority" <?php selected($shipping_selection_strategy ?? 'customer_choice', 'by_priority'); ?>>
                                    <?php esc_html_e('Nach Priorität (nur Versandart mit höchster Priorität)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="cheapest" <?php selected($shipping_selection_strategy ?? 'customer_choice', 'cheapest'); ?>>
                                    <?php esc_html_e('Günstigste (nur billigste Versandart)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                                <option value="most_expensive" <?php selected($shipping_selection_strategy ?? 'customer_choice', 'most_expensive'); ?>>
                                    <?php esc_html_e('Teuerste (nur teuerste Versandart)', 'woo-lieferzeiten-manager'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Legt fest, welche Versandarten dem Kunden angezeigt werden, wenn mehrere Versandarten verfügbar sind. Express-Versandarten werden automatisch mit ihrer Basis-Versandart angezeigt.', 'woo-lieferzeiten-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="wlm-shipping-methods-list">
        <?php
        if (!empty($shipping_methods)) {
            foreach ($shipping_methods as $index => $method) {
                ?>
                <div class="wlm-shipping-method-item postbox" data-index="<?php echo esc_attr($index); ?>">
                    <div class="postbox-header">
                        <h3 class="hndle">
                            <span class="wlm-method-title"><?php echo esc_html($method['name'] ?? __('Neue Versandart', 'woo-lieferzeiten-manager')); ?></span>
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
                                               name="wlm_shipping_methods[<?php echo $index; ?>][name]" 
                                               value="<?php echo esc_attr($method['name'] ?? ''); ?>" 
                                               class="regular-text wlm-method-name-input">
                                        <p class="description"><?php esc_html_e('Name der Versandart, wie er dem Kunden angezeigt wird', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Aktiviert', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wlm_shipping_methods[<?php echo $index; ?>][enabled]" 
                                                   value="1" 
                                                   <?php checked($method['enabled'] ?? true, true); ?>>
                                            <?php esc_html_e('Versandart aktivieren', 'woo-lieferzeiten-manager'); ?>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Priorität', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][priority]" 
                                               value="<?php echo esc_attr($method['priority'] ?? 10); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="small-text">
                                        <p class="description">
                                            <?php esc_html_e('Niedrigere Zahl = höhere Priorität bei der Auswahl', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Kosten -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Kostentyp', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wlm_shipping_methods[<?php echo $index; ?>][cost_type]">
                                            <option value="flat" <?php selected($method['cost_type'] ?? 'flat', 'flat'); ?>>
                                                <?php esc_html_e('Pauschal', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="by_weight" <?php selected($method['cost_type'] ?? 'flat', 'by_weight'); ?>>
                                                <?php esc_html_e('Nach Gewicht', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                            <option value="by_qty" <?php selected($method['cost_type'] ?? 'flat', 'by_qty'); ?>>
                                                <?php esc_html_e('Nach Stückzahl', 'woo-lieferzeiten-manager'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Kosten (netto)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][cost]" 
                                               value="<?php echo esc_attr($method['cost'] ?? 0); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text"> €
                                        <p class="description"><?php esc_html_e('Basiskosten oder Kosten pro Einheit (kg/Stück)', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Bedingungen: Gewicht -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Gewichtsbeschränkung (kg)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][weight_min]" 
                                               value="<?php echo esc_attr($method['weight_min'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Min">
                                        –
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][weight_max]" 
                                               value="<?php echo esc_attr($method['weight_max'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Max">
                                        <p class="description"><?php esc_html_e('Leer lassen für keine Beschränkung', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Bedingungen: Warenkorbsumme -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Warenkorbsumme (€)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][cart_total_min]" 
                                               value="<?php echo esc_attr($method['cart_total_min'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Min">
                                        –
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][cart_total_max]" 
                                               value="<?php echo esc_attr($method['cart_total_max'] ?? ''); ?>" 
                                               min="0" 
                                               step="0.01" 
                                               class="small-text" 
                                               placeholder="Max">
                                        <p class="description"><?php esc_html_e('Warenkorbsumme (netto) als Bedingung', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Bedingungen: Produktattribute -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Produktattribute / Taxonomien', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <div class="wlm-attribute-conditions" data-method-index="<?php echo $index; ?>">
                                            <?php
                                            // Get all product attributes
                                            $attribute_taxonomies = wc_get_attribute_taxonomies();
                                            
                                            // Parse existing conditions
                                            $existing_conditions = array();
                                            
                                            // Check if attribute_conditions array exists (new format)
                                            if (!empty($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
                                                $existing_conditions = $method['attribute_conditions'];
                                            }
                                            // Fallback to required_attributes string (old format)
                                            elseif (!empty($method['required_attributes'])) {
                                                $lines = array_filter(array_map('trim', explode("\n", $method['required_attributes'])));
                                                foreach ($lines as $line) {
                                                    if (strpos($line, '=') !== false) {
                                                        list($attr, $val) = array_map('trim', explode('=', $line, 2));
                                                        $existing_conditions[] = array('attribute' => $attr, 'value' => $val);
                                                    }
                                                }
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
                                                    ?>
                                                    <div class="wlm-attribute-condition-row" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                                                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                                            <!-- Logik-Operator -->
                                                            <select name="wlm_shipping_methods[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][logic]" class="wlm-logic-select" style="width: 150px;">
                                                                <option value="at_least_one" <?php selected($logic, 'at_least_one'); ?>><?php esc_html_e('at least one of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="all" <?php selected($logic, 'all'); ?>><?php esc_html_e('all of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="none" <?php selected($logic, 'none'); ?>><?php esc_html_e('none of', 'woo-lieferzeiten-manager'); ?></option>
                                                                <option value="only" <?php selected($logic, 'only'); ?>><?php esc_html_e('only', 'woo-lieferzeiten-manager'); ?></option>
                                                            </select>
                                                            
                                                            <!-- Attribut -->
                                                            <select name="wlm_shipping_methods[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][attribute]" class="wlm-attribute-select" style="width: 200px;">
                                                                <option value=""><?php esc_html_e('-- Attribut wählen --', 'woo-lieferzeiten-manager'); ?></option>
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
                                                            </select>
                                                            
                                                            <button type="button" class="button wlm-remove-attribute-condition" style="margin-left: auto;"><?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?></button>
                                                        </div>
                                                        
                                                        <!-- Werte (Select2 Multiselect) -->
                                                        <div class="wlm-condition-values" style="margin-top: 10px;">
                                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e('Werte:', 'woo-lieferzeiten-manager'); ?></label>
                                                            <select 
                                                                multiple="multiple" 
                                                                class="wlm-values-select2" 
                                                                name="wlm_shipping_methods[<?php echo $index; ?>][attribute_conditions][<?php echo $cond_index; ?>][values][]" 
                                                                data-attribute="<?php echo esc_attr($condition['attribute'] ?? ''); ?>"
                                                                data-method-index="<?php echo $index; ?>"
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
                                        
                                        <button type="button" class="button wlm-add-attribute-condition" data-method-index="<?php echo $index; ?>">
                                            <?php esc_html_e('+ Bedingung hinzufügen', 'woo-lieferzeiten-manager'); ?>
                                        </button>
                                        
                                        <p class="description">
                                            <?php esc_html_e('Wählen Sie Attribute oder Taxonomien aus und geben Sie den gewünschten Wert ein.', 'woo-lieferzeiten-manager'); ?><br>
                                            <?php esc_html_e('Versandart wird nur angezeigt, wenn ALLE Bedingungen erfüllt sind.', 'woo-lieferzeiten-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Bedingungen: Produktkategorien -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Produktkategorien', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        $product_categories = get_terms(array(
                                            'taxonomy' => 'product_cat',
                                            'hide_empty' => false,
                                        ));
                                        
                                        if (!empty($product_categories) && !is_wp_error($product_categories)) {
                                            $selected_categories = !empty($method['required_categories']) ? explode(',', $method['required_categories']) : array();
                                            echo '<select name="wlm_shipping_methods[' . $index . '][required_categories][]" multiple size="5" style="width: 100%; max-width: 400px;">';
                                            foreach ($product_categories as $category) {
                                                $selected = in_array($category->term_id, $selected_categories) ? 'selected' : '';
                                                echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                                            }
                                            echo '</select>';
                                        }
                                        ?>
                                        <p class="description"><?php esc_html_e('Versandart nur für Produkte aus diesen Kategorien (Mehrfachauswahl mit Strg/Cmd)', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Lieferzeiten -->
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Transitzeit (Werktage)', 'woo-lieferzeiten-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][transit_min]" 
                                               value="<?php echo esc_attr($method['transit_min'] ?? 1); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="small-text">
                                        –
                                        <input type="number" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][transit_max]" 
                                               value="<?php echo esc_attr($method['transit_max'] ?? 3); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="small-text">
                                        <p class="description"><?php esc_html_e('Versandzeit nach Bearbeitung', 'woo-lieferzeiten-manager'); ?></p>
                                    </td>
                                </tr>

                                <!-- Express-Option -->
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Express-Option', 'woo-lieferzeiten-manager'); ?>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" 
                                                       name="wlm_shipping_methods[<?php echo $index; ?>][express_enabled]" 
                                                       value="1" 
                                                       <?php checked($method['express_enabled'] ?? false, true); ?>>
                                                <?php esc_html_e('Express-Option aktivieren', 'woo-lieferzeiten-manager'); ?>
                                            </label>
                                        </fieldset>

                                        <div class="wlm-express-settings" style="margin-top: 10px;">
                                            <label>
                                                <?php esc_html_e('Express-Zuschlag (netto):', 'woo-lieferzeiten-manager'); ?>
                                                <input type="number" 
                                                       name="wlm_shipping_methods[<?php echo $index; ?>][express_cost]" 
                                                       value="<?php echo esc_attr($method['express_cost'] ?? 9.90); ?>" 
                                                       min="0" 
                                                       step="0.01" 
                                                       class="small-text"> €
                                            </label>
                                            <br>
                                            <label>
                                                <?php esc_html_e('Express Cutoff-Zeit:', 'woo-lieferzeiten-manager'); ?>
                                                <input type="time" 
                                                       name="wlm_shipping_methods[<?php echo $index; ?>][express_cutoff]" 
                                                       value="<?php echo esc_attr($method['express_cutoff'] ?? '14:00'); ?>" 
                                                       class="regular-text">
                                            </label>
                                            <br>
                                            <label>
                                                <?php esc_html_e('Express Transitzeit Min/Max (Werktage):', 'woo-lieferzeiten-manager'); ?>
                                                <input type="number" 
                                                       name="wlm_shipping_methods[<?php echo $index; ?>][express_transit_min]" 
                                                       value="<?php echo esc_attr($method['express_transit_min'] ?? 0); ?>" 
                                                       min="0" 
                                                       step="1" 
                                                       class="small-text">
                                                –
                                                <input type="number" 
                                                       name="wlm_shipping_methods[<?php echo $index; ?>][express_transit_max]" 
                                                       value="<?php echo esc_attr($method['express_transit_max'] ?? 1); ?>" 
                                                       min="0" 
                                                       step="1" 
                                                       class="small-text">
                                            </label>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                        <input type="hidden" 
                                               name="wlm_shipping_methods[<?php echo $index; ?>][id]" 
                                               value="<?php echo esc_attr($method['id'] ?? uniqid('wlm_method_')); ?>">
                                        <button type="button" class="button wlm-remove-shipping-method">
                                            <?php esc_html_e('Versandart entfernen', 'woo-lieferzeiten-manager'); ?>
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

    <button type="button" id="wlm-add-shipping-method" class="button">
        <?php esc_html_e('Versandart hinzufügen', 'woo-lieferzeiten-manager'); ?>
    </button>
    
    <!-- Hidden template for attribute conditions (used by JavaScript) -->
    <script type="text/template" id="wlm-attribute-condition-template">
        <div class="wlm-attribute-condition-row" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                <!-- Logik-Operator -->
                <select name="wlm_shipping_methods[{{METHOD_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][logic]" class="wlm-logic-select" style="width: 150px;">
                    <option value="at_least_one"><?php esc_html_e('at least one of', 'woo-lieferzeiten-manager'); ?></option>
                    <option value="all"><?php esc_html_e('all of', 'woo-lieferzeiten-manager'); ?></option>
                    <option value="none"><?php esc_html_e('none of', 'woo-lieferzeiten-manager'); ?></option>
                    <option value="only"><?php esc_html_e('only', 'woo-lieferzeiten-manager'); ?></option>
                </select>
                
                <!-- Attribut -->
                <select name="wlm_shipping_methods[{{METHOD_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][attribute]" class="wlm-attribute-select" style="width: 200px;">
                    <option value=""><?php esc_html_e('-- Attribut wählen --', 'woo-lieferzeiten-manager'); ?></option>
                    <optgroup label="<?php esc_attr_e('Produkt-Attribute', 'woo-lieferzeiten-manager'); ?>">
                        <?php
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
                </select>
                
                <button type="button" class="button wlm-remove-attribute-condition" style="margin-left: auto;"><?php esc_html_e('Entfernen', 'woo-lieferzeiten-manager'); ?></button>
            </div>
            
            <!-- Werte (Select2 Multiselect) -->
            <div class="wlm-condition-values" style="margin-top: 10px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e('Werte:', 'woo-lieferzeiten-manager'); ?></label>
                <select 
                    multiple="multiple" 
                    class="wlm-values-select2" 
                    name="wlm_shipping_methods[{{METHOD_INDEX}}][attribute_conditions][{{CONDITION_INDEX}}][values][]" 
                    data-attribute=""
                    data-method-index="{{METHOD_INDEX}}"
                    data-condition-index="{{CONDITION_INDEX}}"
                    style="width: 100%;">
                </select>
            </div>
        </div>
    </script>
</div>
