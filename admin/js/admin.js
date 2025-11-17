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
            this.initSelect2();
            this.bindEvents();
        },

        /**
         * Initialize Select2 on existing elements
         */
        initSelect2: function() {
            // Initialize Select2 on all existing attribute value selects
            $('.wlm-values-select2').each(function() {
                var $select = $(this);
                var attribute = $select.attr('data-attribute');
                
                if (attribute && attribute !== '') {
                    // Has attribute - will be populated by loadAttributeValues
                    $select.select2({
                        placeholder: 'Werte auswählen...',
                        allowClear: true,
                        width: '100%'
                    });
                } else {
                    // No attribute yet
                    $select.select2({
                        placeholder: 'Zuerst Attribut wählen...',
                        tags: true,
                        allowClear: true,
                        width: '100%'
                    });
                }
            });
        },

        /**
         * Bind events
         */
        bindEvents: function() {
        // Save settings via AJAX
        $(document).on('click', '#wlm-save-settings', this.saveSettings.bind(this));
        
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
        $(document).on('change', '.wlm-condition-type-select', this.handleConditionTypeChange.bind(this));
        
        // Value tags
        $(document).on('click', '.wlm-add-value', this.addValueTag.bind(this));
        $(document).on('click', '.wlm-remove-value-tag', this.removeValueTag.bind(this));
        $(document).on('keypress', '.wlm-value-input', this.handleValueInputKeypress.bind(this));
        
        // Cronjob
        $(document).on('click', '#wlm-run-cronjob-now', this.runCronjob.bind(this));
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $button = $('#wlm-save-settings');
            var $spinner = $('.wlm-save-spinner');
            
            // Collect all form data
            var formData = {};
            
            // Collect wlm_settings
            $('[name^="wlm_settings"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/wlm_settings\[(.+)\](?:\[\])?/);
                if (match) {
                    var key = match[1];
                    if ($(this).is(':checkbox')) {
                        if (!formData.wlm_settings) formData.wlm_settings = {};
                        if (!formData.wlm_settings[key]) formData.wlm_settings[key] = [];
                        if ($(this).is(':checked')) {
                            formData.wlm_settings[key].push($(this).val());
                        }
                    } else {
                        if (!formData.wlm_settings) formData.wlm_settings = {};
                        formData.wlm_settings[key] = $(this).val();
                    }
                }
            });
            
            // Collect wlm_shipping_methods
            formData.wlm_shipping_methods = [];
            $('.wlm-shipping-method-item').each(function() {
                var method = {};
                $(this).find('[name^="wlm_shipping_methods"]').each(function() {
                    var name = $(this).attr('name');
                    var value = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
                    
                    // Parse: wlm_shipping_methods[0][path]
                    // Extract path after method index
                    var methodMatch = name.match(/wlm_shipping_methods\[\d+\](.*)/);
                    if (!methodMatch) return;
                    
                    var fullPath = methodMatch[1];
                    if (!fullPath) return;
                    
                    // Parse all bracket segments: [key1][key2][key3][]
                    var segments = [];
                    var segmentRegex = /\[([^\]]+)\]/g;
                    var segmentMatch;
                    while ((segmentMatch = segmentRegex.exec(fullPath)) !== null) {
                        segments.push(segmentMatch[1]);
                    }
                    
                    if (segments.length === 0) return;
                    
                    // Build nested structure
                    var current = method;
                    for (var i = 0; i < segments.length; i++) {
                        var segment = segments[i];
                        var isLast = (i === segments.length - 1);
                        var nextSegment = isLast ? null : segments[i + 1];
                        
                        // Check if this is an array index (numeric)
                        var isIndex = /^\d+$/.test(segment);
                        
                        // Check if next segment is empty (indicates array notation [])
                        var isArrayNotation = (segment === '');
                        
                        if (isArrayNotation) {
                            // This is [], skip it - value should be an array already
                            break;
                        }
                        
                        if (isLast) {
                            // Last segment - assign value
                            current[segment] = value;
                        } else {
                            // Not last - create nested structure
                            if (!current[segment]) {
                                // Check if next segment is numeric (array index)
                                if (/^\d+$/.test(nextSegment)) {
                                    current[segment] = [];
                                } else {
                                    current[segment] = {};
                                }
                            }
                            current = current[segment];
                        }
                    }
                });
                if (Object.keys(method).length > 0) {
                    formData.wlm_shipping_methods.push(method);
                }
            });
            
            // Collect wlm_surcharges
            formData.wlm_surcharges = [];
            $('.wlm-surcharge-item').each(function() {
                var surcharge = {};
                $(this).find('[name^="wlm_surcharges"]').each(function() {
                    var name = $(this).attr('name');
                    var match = name.match(/wlm_surcharges\[\d+\]\[(.+)\]/);
                    if (match) {
                        var key = match[1];
                        if ($(this).is(':checkbox')) {
                            surcharge[key] = $(this).is(':checked');
                        } else {
                            surcharge[key] = $(this).val();
                        }
                    }
                });
                if (Object.keys(surcharge).length > 0) {
                    formData.wlm_surcharges.push(surcharge);
                }
            });
            
            // Collect shipping selection strategy
            var shippingStrategy = $('#wlm_shipping_selection_strategy').val();
            if (shippingStrategy) {
                formData.wlm_shipping_selection_strategy = shippingStrategy;
            }
            
            // Collect surcharge application strategy
            var surchargeStrategy = $('#wlm_surcharge_application_strategy').val();
            if (surchargeStrategy) {
                formData.wlm_surcharge_application_strategy = surchargeStrategy;
            }
            
            // DEBUG: Log collected data
            console.log('Collected formData:', formData);
            
            // Show spinner
            $button.prop('disabled', true);
            $spinner.show();
            
            // Send AJAX request
            $.ajax({
                url: wlmAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wlm_save_settings',
                    nonce: wlmAdmin.nonce,
                    data: JSON.stringify(formData)
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>')
                            .insertAfter('.wrap h1, .wrap h2.nav-tab-wrapper')
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                    }
                },
                error: function() {
                    alert('Fehler beim Speichern der Einstellungen.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            });
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
            if (typeof wlmAdmin !== 'undefined' && wlmAdmin.attributes) {
                $.each(wlmAdmin.attributes, function(key, label) {
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
            
            // Name
            html += '<tr><th scope="row"><label>Name</label></th>';
            html += '<td><input type="text" name="wlm_surcharges[' + index + '][name]" value="" class="regular-text wlm-surcharge-name-input">';
            html += '<p class="description">Interner Name des Zuschlags (nicht sichtbar für Kunden)</p></td></tr>';
            
            // Aktiviert
            html += '<tr><th scope="row">Aktiviert</th>';
            html += '<td><label><input type="checkbox" name="wlm_surcharges[' + index + '][enabled]" value="1" checked> Zuschlag aktivieren</label></td></tr>';
            
            // Priorität
            html += '<tr><th scope="row"><label>Priorität</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][priority]" value="10" min="0" step="1" class="small-text">';
            html += '<p class="description">Niedrigere Zahlen = höhere Priorität (für "Erster Treffer" Strategie)</p></td></tr>';
            
            // Kostentyp
            html += '<tr><th scope="row"><label>Kostentyp</label></th>';
            html += '<td><select name="wlm_surcharges[' + index + '][cost_type]" class="regular-text">';
            html += '<option value="flat">Pauschalbetrag (€)</option>';
            html += '<option value="percentage">Prozentual (%)</option>';
            html += '</select></td></tr>';
            
            // Betrag
            html += '<tr><th scope="row"><label>Betrag</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][amount]" value="0" min="0" step="0.01" class="small-text">';
            html += '<p class="description">Zuschlag in € oder % (je nach Kostentyp)</p></td></tr>';
            
            // Charge Per
            html += '<tr><th scope="row"><label>Berechnung pro</label></th>';
            html += '<td><select name="wlm_surcharges[' + index + '][charge_per]" class="regular-text">';
            html += '<option value="cart">Warenkorb (einmalig)</option>';
            html += '<option value="shipping_class">Versandklasse</option>';
            html += '<option value="product_category">Produktkategorie</option>';
            html += '<option value="product">Produkt</option>';
            html += '<option value="cart_item">Warenkorb-Position</option>';
            html += '<option value="quantity_unit">Mengeneinheit</option>';
            html += '</select>';
            html += '<p class="description">Wie oft wird der Zuschlag berechnet?</p></td></tr>';
            
            // Gewicht Min/Max
            html += '<tr><th scope="row"><label>Gewicht Min/Max (kg)</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][weight_min]" value="" min="0" step="0.01" class="small-text" placeholder="Min"> – ';
            html += '<input type="number" name="wlm_surcharges[' + index + '][weight_max]" value="" min="0" step="0.01" class="small-text" placeholder="Max">';
            html += '<p class="description">Leer lassen für keine Beschränkung</p></td></tr>';
            
            // Warenkorbwert Min/Max
            html += '<tr><th scope="row"><label>Warenkorbwert Min/Max (€)</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][cart_value_min]" value="" min="0" step="0.01" class="small-text" placeholder="Min"> – ';
            html += '<input type="number" name="wlm_surcharges[' + index + '][cart_value_max]" value="" min="0" step="0.01" class="small-text" placeholder="Max">';
            html += '<p class="description">Leer lassen für keine Beschränkung</p></td></tr>';
            
            // Bedingungen
            html += '<tr><th scope="row"><label>Produktattribute / Taxonomien / Versandklassen</label></th>';
            html += '<td><div class="wlm-attribute-conditions" data-surcharge-index="' + index + '">';
            html += '<p class="description">Bedingungen für Produktattribute, Taxonomien oder Versandklassen</p>';
            html += '</div>';
            html += '<button type="button" class="button wlm-add-attribute-condition" data-surcharge-index="' + index + '">+ Bedingung hinzufügen</button>';
            html += '<p class="description">Zuschlag wird nur angewendet, wenn ALLE Bedingungen erfüllt sind.</p></td></tr>';
            
            // Entfernen Button
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
            var surchargeIndex = $button.data('surcharge-index');
            
            var $container, template, placeholder;
            
            // Check if this is for shipping method or surcharge
            if (typeof methodIndex !== 'undefined') {
                // Shipping method
                $container = $('.wlm-attribute-conditions[data-method-index="' + methodIndex + '"]');
                template = $('#wlm-attribute-condition-template').html();
                placeholder = '{{METHOD_INDEX}}';
            } else if (typeof surchargeIndex !== 'undefined') {
                // Surcharge
                $container = $('.wlm-attribute-conditions[data-surcharge-index="' + surchargeIndex + '"]');
                template = $('#wlm-surcharge-condition-template').html();
                placeholder = '{{SURCHARGE_INDEX}}';
            } else {
                return;
            }
            
            // Get next condition index
            var conditionIndex = $container.find('.wlm-attribute-condition-row').length;
            
            // Replace placeholders
            if (typeof methodIndex !== 'undefined') {
                template = template.replace(/{{METHOD_INDEX}}/g, methodIndex);
            } else {
                template = template.replace(/{{SURCHARGE_INDEX}}/g, surchargeIndex);
            }
            template = template.replace(/{{CONDITION_INDEX}}/g, conditionIndex);
            
            // Append to container
            $container.append(template);
            
            // Initialize Select2 on the newly added values select
            var $newRow = $container.find('.wlm-attribute-condition-row').last();
            var $valuesSelect = $newRow.find('.wlm-values-select2');
            $valuesSelect.select2({
                placeholder: 'Zuerst Attribut wählen...',
                tags: true,
                allowClear: true,
                width: '100%'
            });
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
         * Load attribute values via AJAX and populate Select2
         */
        loadAttributeValues: function(e) {
            var $select = $(e.currentTarget);
            var attribute = $select.val();
            var $row = $select.closest('.wlm-attribute-condition-row');
            var $valuesSelect = $row.find('.wlm-values-select2');
            
            // Update data-attribute
            $valuesSelect.attr('data-attribute', attribute);
            
            if (!attribute) {
                // Clear and disable select2
                $valuesSelect.empty().trigger('change');
                if ($valuesSelect.hasClass('select2-hidden-accessible')) {
                    $valuesSelect.select2('destroy');
                }
                return;
            }
            
            // AJAX request to get attribute values
            $.ajax({
                url: wlmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wlm_get_attribute_values',
                    attribute: attribute,
                    nonce: wlmAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        // Populate select with options
                        $valuesSelect.empty();
                        $.each(response.data, function(i, item) {
                            var option = new Option(item.label, item.value, false, false);
                            $valuesSelect.append(option);
                        });
                        
                        // Initialize or refresh Select2
                        if ($valuesSelect.hasClass('select2-hidden-accessible')) {
                            $valuesSelect.select2('destroy');
                        }
                        $valuesSelect.select2({
                            placeholder: 'Werte auswählen...',
                            allowClear: true,
                            width: '100%'
                        });
                    } else {
                        // No values found - allow manual input
                        if ($valuesSelect.hasClass('select2-hidden-accessible')) {
                            $valuesSelect.select2('destroy');
                        }
                        $valuesSelect.select2({
                            placeholder: 'Werte eingeben...',
                            tags: true,
                            allowClear: true,
                            width: '100%'
                        });
                    }
                },
                error: function() {
                    // Error - allow manual input
                    if ($valuesSelect.hasClass('select2-hidden-accessible')) {
                        $valuesSelect.select2('destroy');
                    }
                    $valuesSelect.select2({
                        placeholder: 'Werte eingeben...',
                        tags: true,
                        allowClear: true,
                        width: '100%'
                    });
                }
            });
        },
        
        /**
         * Add value tag
         */
        addValueTag: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $conditionRow = $button.closest('.wlm-attribute-condition-row');
            var $valueInput = $conditionRow.find('.wlm-value-input');
            var $tagsContainer = $conditionRow.find('.wlm-value-tags');
            var value = $valueInput.val().trim();
            
            if (!value) {
                return;
            }
            
            // Get method and condition indices from name attributes
            var $logicSelect = $conditionRow.find('.wlm-logic-select');
            var nameAttr = $logicSelect.attr('name');
            var matches = nameAttr.match(/\[(\d+)\].*\[(\d+)\]/);
            
            if (!matches) {
                return;
            }
            
            var methodIndex = matches[1];
            var conditionIndex = matches[2];
            
            // Create tag
            var $tag = $('<span class="wlm-value-tag" style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; background: #0073aa; color: white; border-radius: 3px;">' +
                '<span>' + this.escapeHtml(value) + '</span>' +
                '<input type="hidden" name="wlm_shipping_methods[' + methodIndex + '][attribute_conditions][' + conditionIndex + '][values][]" value="' + this.escapeHtml(value) + '">' +
                '<button type="button" class="wlm-remove-value-tag" style="background: none; border: none; color: white; cursor: pointer; padding: 0; font-size: 16px;">×</button>' +
                '</span>');
            
            // Append tag
            $tagsContainer.append($tag);
            
            // Clear input
            $valueInput.val('');
        },
        
        /**
         * Remove value tag
         */
        removeValueTag: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.wlm-value-tag').fadeOut(200, function() {
                $(this).remove();
            });
        },
        
        /**
         * Handle Enter key in value input
         */
        handleValueInputKeypress: function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(e.currentTarget).closest('.wlm-condition-values').find('.wlm-add-value').click();
            }
        },
        
        /**
         * Run cronjob manually
         */
        runCronjob: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $status = $('#wlm-cronjob-status');
            
            // Disable button
            $button.prop('disabled', true).text(wlmAdmin.i18n.running || 'Wird ausgeführt...');
            $status.html('<span style="color: #0073aa;">⏳ Wird ausgeführt...</span>');
            
            $.ajax({
                url: wlmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wlm_run_cronjob',
                    nonce: wlmAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                        
                        // Update last run info if present
                        if (response.data.last_run) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ Fehler: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color: #dc3232;">✗ AJAX-Fehler</span>');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(wlmAdmin.i18n.runNow || 'Jetzt ausführen');
                }
            });
        },
        
        /**
         * Handle condition type change
         */
        handleConditionTypeChange: function(e) {
            var $select = $(e.currentTarget);
            var type = $select.val();
            var $conditionRow = $select.closest('.wlm-attribute-condition-row');
            var $valuesContainer = $conditionRow.find('.wlm-condition-values');
            var $attributeSelect = $conditionRow.find('.wlm-attribute-select');
            
            // Show/hide attribute select and values field based on type
            if (type === 'shipping_class') {
                // Hide attribute select for shipping class
                $attributeSelect.hide();
                // Show values field (multiselect) for shipping classes
                $valuesContainer.show();
                // Load shipping classes into multiselect
                this.loadShippingClassesIntoMultiselect($conditionRow);
            } else {
                // Show attribute select for attributes and taxonomies
                $attributeSelect.show();
                // Show values field
                $valuesContainer.show();
                // Show all optgroups first (reset)
                $attributeSelect.find('optgroup').show();
                // Then filter based on type
                if (type === 'attribute') {
                    $attributeSelect.find('optgroup[label*="Taxonomien"]').hide();
                } else if (type === 'taxonomy') {
                    $attributeSelect.find('optgroup[label*="Produkt-Attribute"]').hide();
                }
                // If no type selected, show all
            }
        },
        
        /**
         * Load shipping classes into multiselect
         */
        loadShippingClassesIntoMultiselect: function($conditionRow) {
            var $select = $conditionRow.find('.wlm-values-select2');
            
            // Clear existing options
            $select.empty();
            
            // Get shipping classes from global variable (if available)
            if (typeof wlmAdmin !== 'undefined' && wlmAdmin.shippingClasses) {
                $.each(wlmAdmin.shippingClasses, function(slug, name) {
                    $select.append(new Option(name, slug, false, false));
                });
            } else {
                // Fallback: Load via AJAX
                $.ajax({
                    url: wlmAdmin.ajaxUrl,
                    type: 'GET',
                    data: {
                        action: 'wlm_get_shipping_classes',
                        nonce: wlmAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            $.each(response.data, function(i, item) {
                                $select.append(new Option(item.label, item.value, false, false));
                            });
                        }
                    }
                });
            }
            
            // Reinitialize Select2
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                placeholder: 'Versandklassen wählen...',
                allowClear: true
            });
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WLM_Admin.init();
    });

})(jQuery);
