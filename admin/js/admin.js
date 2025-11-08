/**
 * WLM Admin JavaScript
 */
(function($) {
    'use strict';

    var WLM_Admin = {
        /**
         * Initialize
         */
        init: function() {
            this.initPostboxes();
            this.initSortable();
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
        // Shipping methods
        $(document).on('click', '#wlm-add-shipping-method', this.addShippingMethod.bind(this));
        $(document).on('click', '.wlm-remove-shipping-method', this.removeShippingMethod.bind(this));
        $(document).on('input', '.wlm-method-name-input', this.updateMethodTitle.bind(this));
        
        // Surcharges
        $(document).on('click', '#wlm-add-surcharge', this.addSurcharge.bind(this));
        $(document).on('click', '.wlm-remove-surcharge', this.removeSurcharge.bind(this));
        $(document).on('input', '.wlm-surcharge-name-input', this.updateSurchargeTitle.bind(this));
        
        // Attribute conditions
        $(document).on('click', '.wlm-add-attribute-condition', this.addAttributeCondition.bind(this));
        $(document).on('click', '.wlm-remove-attribute-condition', this.removeAttributeCondition.bind(this));
        $(document).on('change', '.wlm-attribute-select', this.loadAttributeValues.bind(this));
        },

        /**
         * Initialize sortable
         */
        initSortable: function() {
            if ($.fn.sortable) {
                $('#wlm-shipping-methods-list').sortable({
                    handle: '.postbox-header',
                    placeholder: 'wlm-sortable-placeholder',
                    cursor: 'move'
                });

                $('#wlm-surcharges-list').sortable({
                    handle: '.postbox-header',
                    placeholder: 'wlm-sortable-placeholder',
                    cursor: 'move'
                });
            }
        },

        /**
         * Initialize postboxes
         */
        initPostboxes: function() {
            // Toggle postbox
            $('.postbox .handlediv').off('click').on('click', function() {
                $(this).closest('.postbox').find('.inside').slideToggle();
                $(this).attr('aria-expanded', function(i, attr) {
                    return attr === 'true' ? 'false' : 'true';
                });
            });
        },

        /**
         * Add shipping method
         */
        addShippingMethod: function(e) {
            e.preventDefault();

            var index = $('#wlm-shipping-methods-list .wlm-shipping-method-item').length;
            var id = 'wlm_method_' + Date.now();

            var html = this.getShippingMethodTemplate(index, id);
            $('#wlm-shipping-methods-list').append(html);

            // Reinitialize postbox handlers
            this.initPostboxes();
        },

        /**
         * Remove shipping method
         */
        removeShippingMethod: function(e) {
            e.preventDefault();

            if (confirm('Möchten Sie diese Versandart wirklich entfernen?')) {
                $(e.currentTarget).closest('.wlm-shipping-method-item').remove();
                this.reindexShippingMethods();
            }
        },

        /**
         * Update method title
         */
        updateMethodTitle: function(e) {
            var $input = $(e.currentTarget);
            var name = $input.val();
            var $title = $input.closest('.wlm-shipping-method-item').find('.wlm-method-title');
            $title.text(name || 'Neue Versandart');
        },

        /**
         * Reindex shipping methods
         */
        reindexShippingMethods: function() {
            $('#wlm-shipping-methods-list .wlm-shipping-method-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', name);
                    }
                });
            });
        },

        /**
         * Get shipping method template
         */
        getShippingMethodTemplate: function(index, id) {
            // Get all available attributes
            var attributesHtml = '';
            if (typeof wlm_admin !== 'undefined' && wlm_admin.attributes) {
                $.each(wlm_admin.attributes, function(key, label) {
                    attributesHtml += '<option value="' + key + '">' + label + '</option>';
                });
            }
            
            var html = '<div class="wlm-shipping-method-item postbox" data-index="' + index + '">';
            html += '<div class="postbox-header">';
            html += '<h3 class="hndle"><span class="wlm-method-title">Neue Versandart</span></h3>';
            html += '<div class="handle-actions">';
            html += '<button type="button" class="handlediv button-link" aria-expanded="true">';
            html += '<span class="toggle-indicator" aria-hidden="true"></span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="inside">';
            html += '<table class="form-table"><tbody>';
            
            // Name
            html += '<tr><th scope="row"><label>Name</label></th>';
            html += '<td><input type="text" name="wlm_shipping_methods[' + index + '][name]" value="" class="regular-text wlm-method-name-input">';
            html += '<p class="description">Name der Versandart, wie er dem Kunden angezeigt wird</p></td></tr>';
            
            // Aktiviert
            html += '<tr><th scope="row"><label>Aktiviert</label></th>';
            html += '<td><label><input type="checkbox" name="wlm_shipping_methods[' + index + '][enabled]" value="1" checked> Versandart aktivieren</label></td></tr>';
            
            // Priorität
            html += '<tr><th scope="row"><label>Priorität</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][priority]" value="10" min="0" step="1" class="small-text">';
            html += '<p class="description">Niedrigere Zahl = höhere Priorität bei der Auswahl</p></td></tr>';
            
            // Kostentyp
            html += '<tr><th scope="row"><label>Kostentyp</label></th>';
            html += '<td><select name="wlm_shipping_methods[' + index + '][cost_type]">';
            html += '<option value="flat">Pauschal</option>';
            html += '<option value="by_weight">Nach Gewicht</option>';
            html += '<option value="by_qty">Nach Stückzahl</option>';
            html += '</select></td></tr>';
            
            // Kosten
            html += '<tr><th scope="row"><label>Kosten (netto)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][cost]" value="0" min="0" step="0.01" class="small-text"> €</td></tr>';
            
            // Transitzeit
            html += '<tr><th scope="row"><label>Transitzeit Min/Max (Werktage)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][transit_min]" value="1" min="0" step="1" class="small-text"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][transit_max]" value="3" min="0" step="1" class="small-text"></td></tr>';
            
            // Gewicht
            html += '<tr><th scope="row"><label>Gewicht Min/Max (kg)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][weight_min]" value="" min="0" step="0.01" class="small-text" placeholder="Min"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][weight_max]" value="" min="0" step="0.01" class="small-text" placeholder="Max">';
            html += '<p class="description">Leer lassen für keine Beschränkung</p></td></tr>';
            
            // Stückzahl
            html += '<tr><th scope="row"><label>Stückzahl Min/Max</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][qty_min]" value="" min="0" step="1" class="small-text" placeholder="Min"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][qty_max]" value="" min="0" step="1" class="small-text" placeholder="Max">';
            html += '<p class="description">Leer lassen für keine Beschränkung</p></td></tr>';
            
            // Warenkorbsumme
            html += '<tr><th scope="row"><label>Warenkorbsumme Min/Max (€)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][cart_total_min]" value="" min="0" step="0.01" class="small-text" placeholder="Min"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][cart_total_max]" value="" min="0" step="0.01" class="small-text" placeholder="Max">';
            html += '<p class="description">Leer lassen für keine Beschränkung</p></td></tr>';
            
            // Produktattribute/Taxonomien
            html += '<tr><th scope="row"><label>Produktattribute / Taxonomien</label></th>';
            html += '<td><div class="wlm-attribute-conditions" data-method-index="' + index + '">';
            html += '<p class="description">Bedingungen für Produktattribute oder Taxonomien</p>';
            html += '</div>';
            html += '<button type="button" class="button wlm-add-attribute-condition" data-method-index="' + index + '">+ Bedingung hinzufügen</button>';
            html += '</td></tr>';
            
            // Produktkategorien
            html += '<tr><th scope="row"><label>Produktkategorien</label></th>';
            html += '<td><input type="text" name="wlm_shipping_methods[' + index + '][required_categories]" value="" class="regular-text" placeholder="z.B. kategorie-slug">';
            html += '<p class="description">Kommagetrennte Liste von Kategorie-Slugs</p></td></tr>';
            
            // Express-Option
            html += '<tr><th scope="row" colspan="2"><h3 style="margin-top: 20px; margin-bottom: 10px;">Express-Option</h3></th></tr>';
            
            html += '<tr><th scope="row"><label>Express aktiviert</label></th>';
            html += '<td><label><input type="checkbox" name="wlm_shipping_methods[' + index + '][express_enabled]" value="1"> Express-Versand für diese Methode aktivieren</label></td></tr>';
            
            html += '<tr><th scope="row"><label>Express Cutoff-Zeit</label></th>';
            html += '<td><input type="time" name="wlm_shipping_methods[' + index + '][express_cutoff]" value="12:00" class="regular-text">';
            html += '<p class="description">Bestellungen bis zu dieser Zeit werden noch am selben Tag versandt</p></td></tr>';
            
            html += '<tr><th scope="row"><label>Express Kosten (netto)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][express_cost]" value="0" min="0" step="0.01" class="small-text"> €</td></tr>';
            
            html += '<tr><th scope="row"><label>Express Transitzeit Min/Max (Werktage)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][express_transit_min]" value="0" min="0" step="1" class="small-text"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][express_transit_max]" value="1" min="0" step="1" class="small-text"></td></tr>';
            
            // Remove button
            html += '<tr><td colspan="2" style="padding-top: 20px;">';
            html += '<input type="hidden" name="wlm_shipping_methods[' + index + '][id]" value="' + id + '">';
            html += '<button type="button" class="button wlm-remove-shipping-method">Versandart entfernen</button>';
            html += '</td></tr>';
            
            html += '</tbody></table>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        /**
         * Add surcharge
         */
        addSurcharge: function(e) {
            e.preventDefault();

            var index = $('#wlm-surcharges-list .wlm-surcharge-item').length;
            var id = 'wlm_surcharge_' + Date.now();

            var html = this.getSurchargeTemplate(index, id);
            $('#wlm-surcharges-list').append(html);

            // Reinitialize postbox handlers
            this.initPostboxes();
        },

        /**
         * Remove surcharge
         */
        removeSurcharge: function(e) {
            e.preventDefault();

            if (confirm('Möchten Sie diesen Zuschlag wirklich entfernen?')) {
                $(e.currentTarget).closest('.wlm-surcharge-item').remove();
                this.reindexSurcharges();
            }
        },

        /**
         * Update surcharge title
         */
        updateSurchargeTitle: function(e) {
            var $input = $(e.currentTarget);
            var name = $input.val();
            var $title = $input.closest('.wlm-surcharge-item').find('.wlm-surcharge-title');
            $title.text(name || 'Neuer Zuschlag');
        },

        /**
         * Reindex surcharges
         */
        reindexSurcharges: function() {
            $('#wlm-surcharges-list .wlm-surcharge-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', name);
                    }
                });
            });
        },

        /**
         * Get surcharge template
         */
        getSurchargeTemplate: function(index, id) {
            var html = '<div class="wlm-surcharge-item postbox" data-index="' + index + '">';
            html += '<div class="postbox-header">';
            html += '<h3 class="hndle"><span class="wlm-surcharge-title">Neuer Zuschlag</span></h3>';
            html += '<div class="handle-actions">';
            html += '<button type="button" class="handlediv button-link" aria-expanded="true">';
            html += '<span class="toggle-indicator" aria-hidden="true"></span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="inside">';
            html += '<table class="form-table"><tbody>';
            
            html += '<tr><th scope="row"><label>Name</label></th>';
            html += '<td><input type="text" name="wlm_surcharges[' + index + '][name]" value="" class="regular-text wlm-surcharge-name-input"></td></tr>';
            
            html += '<tr><th scope="row">Aktiviert</th>';
            html += '<td><label><input type="checkbox" name="wlm_surcharges[' + index + '][enabled]" value="1" checked> Zuschlag aktivieren</label></td></tr>';
            
            html += '<tr><th scope="row"><label>Typ</label></th>';
            html += '<td><select name="wlm_surcharges[' + index + '][type]">';
            html += '<option value="fixed">Fest</option>';
            html += '<option value="percentage">Prozentual</option>';
            html += '</select></td></tr>';
            
            html += '<tr><th scope="row"><label>Betrag</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][amount]" value="0" min="0" step="0.01" class="small-text"></td></tr>';
            
            html += '<tr><td colspan="2">';
            html += '<input type="hidden" name="wlm_surcharges[' + index + '][id]" value="' + id + '">';
            html += '<button type="button" class="button wlm-remove-surcharge">Zuschlag entfernen</button>';
            html += '</td></tr>';
            
            html += '</tbody></table>';
            html += '</div>';
            html += '</div>';

            return html;
        },
        
        /**
         * Add attribute condition
         */
        addAttributeCondition: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var methodIndex = $button.data('method-index');
            var $container = $('.wlm-attribute-conditions[data-method-index="' + methodIndex + '"]');
            
            // Get template
            var template = $('#wlm-attribute-condition-template').html();
            
            // Get next condition index
            var conditionIndex = $container.find('.wlm-attribute-condition-row').length;
            
            // Replace placeholders
            template = template.replace(/{{METHOD_INDEX}}/g, methodIndex);
            template = template.replace(/{{CONDITION_INDEX}}/g, conditionIndex);
            
            // Append to container
            $container.append(template);
        },
        
        /**
         * Remove attribute condition
         */
        removeAttributeCondition: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $row = $button.closest('.wlm-attribute-condition-row');
            
            // Fade out and remove
            $row.fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        /**
         * Load attribute values via AJAX
         */
        loadAttributeValues: function(e) {
            var $select = $(e.currentTarget);
            var attribute = $select.val();
            var $row = $select.closest('.wlm-attribute-condition-row');
            var $valueInput = $row.find('input[type="text"]');
            var currentValue = $valueInput.val();
            
            if (!attribute) {
                // Reset to text input
                $valueInput.replaceWith('<input type="text" name="' + $valueInput.attr('name') + '" value="" placeholder="Wert" class="regular-text" style="width: 200px;">');
                return;
            }
            
            // Show loading
            $valueInput.prop('disabled', true).val('Lädt...');
            
            // AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wlm_get_attribute_values',
                    attribute: attribute,
                    nonce: wlm_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        // Create datalist
                        var datalistId = 'wlm-values-' + Math.random().toString(36).substr(2, 9);
                        var $newInput = $('<input type="text" list="' + datalistId + '" name="' + $valueInput.attr('name') + '" value="' + currentValue + '" placeholder="Wert wählen oder eingeben" class="regular-text" style="width: 200px;">');
                        var $datalist = $('<datalist id="' + datalistId + '"></datalist>');
                        
                        // Add options
                        $.each(response.data, function(i, item) {
                            $datalist.append('<option value="' + item.value + '">' + item.label + '</option>');
                        });
                        
                        // Replace input with datalist version
                        $valueInput.replaceWith($newInput);
                        $newInput.after($datalist);
                    } else {
                        // No values found, keep text input
                        $valueInput.prop('disabled', false).val(currentValue).attr('placeholder', 'Wert eingeben');
                    }
                },
                error: function() {
                    // Error, keep text input
                    $valueInput.prop('disabled', false).val(currentValue).attr('placeholder', 'Wert eingeben');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WLM_Admin.init();
    });

})(jQuery);
