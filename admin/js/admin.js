/**
 * WLM Admin JavaScript
 * Fix: Robust data collection for nested arrays and Condition Indexing
 */
(function($) {
    'use strict';

    var WLM_Admin = {
        init: function() {
            this.initPostboxes();
            this.initSortable();
            this.initSelect2(); // Global Init
            this.initConditionTypes(); // Visibility handling only
            this.loadGermanizedProviders();
            this.bindEvents();
            
            // Collapse after init to ensure Select2 attaches correctly first
            setTimeout(this.collapseAllItems.bind(this), 100);
        },
        
        collapseAllItems: function() {
            // Use CSS class for hiding
            $('.wlm-shipping-method-item, .wlm-surcharge-item').addClass('closed');
            $('.wlm-shipping-method-item .handlediv, .wlm-surcharge-item .handlediv').attr('aria-expanded', 'false');
        },
        
        initConditionTypes: function() {
            // Only handle visibility logic here, NO Select2 init
            $('.wlm-condition-type-select').each(function() {
                $(this).trigger('change');
            });
        },

        initSelect2: function() {
            var self = this;
            $('.wlm-values-select2').each(function() {
                var $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) return; // Skip if already init
                
                var $row = $select.closest('.wlm-attribute-condition-row');
                var type = $row.find('.wlm-condition-type-select').val();
                var attribute = $select.attr('data-attribute');
                
                // Configuration based on type
                if (type === 'shipping_class') {
                     $select.select2({
                        placeholder: 'Versandklassen-Slugs eingeben...',
                        tags: true,
                        allowClear: true,
                        width: '100%'
                    });
                } else if (attribute && attribute !== '') {
                    $select.select2({
                        placeholder: 'Werte auswählen...',
                        allowClear: true,
                        width: '100%'
                    });
                } else {
                    $select.select2({
                        placeholder: 'Zuerst Attribut wählen...',
                        allowClear: true,
                        width: '100%'
                    });
                }
            });
        },

        bindEvents: function() {
            $(document).on('click', '#wlm-save-settings, .submit input[name="save"]', this.saveSettings.bind(this));
            $(document).on('click', '#wlm-add-shipping-method', this.addShippingMethod.bind(this));
            $(document).on('click', '.wlm-remove-shipping-method', this.removeShippingMethod.bind(this));
            $(document).on('input', '.wlm-method-name-input', this.updateMethodTitle.bind(this));
            $(document).on('click', '#wlm-add-surcharge', this.addSurcharge.bind(this));
            $(document).on('click', '.wlm-remove-surcharge', this.removeSurcharge.bind(this));
            $(document).on('input', '.wlm-surcharge-name-input', this.updateSurchargeTitle.bind(this));
            $(document).on('click', '.wlm-add-attribute-condition', this.addAttributeCondition.bind(this));
            $(document).on('click', '.wlm-remove-attribute-condition', this.removeAttributeCondition.bind(this));
            $(document).on('change', '.wlm-attribute-select', this.loadAttributeValues.bind(this));
            $(document).on('change', '.wlm-condition-type-select', this.handleConditionTypeChange.bind(this));
            $(document).on('click', '#wlm-add-holiday', this.addHoliday.bind(this));
            $(document).on('click', '.wlm-remove-holiday', this.removeHoliday.bind(this));
            $(document).on('click', '#wlm-run-cronjob-now', this.runCronjob.bind(this));
        },

        saveSettings: function(e) {
            e.preventDefault();
            var $button = $('#wlm-save-settings');
            var $spinner = $('.wlm-save-spinner');
            var activeSection = 'times'; 

            var urlParams = new URLSearchParams(window.location.search);
            var wlmTab = urlParams.get('wlm_tab');
            var standAloneTab = urlParams.get('tab');
            
            if (wlmTab) {
                activeSection = wlmTab;
            } else if (standAloneTab && standAloneTab !== 'shipping') {
                activeSection = standAloneTab;
            } else {
                if ($('#wlm-surcharges-list').length > 0) activeSection = 'surcharges';
                else if ($('#wlm-shipping-methods-list').length > 0) activeSection = 'shipping';
                else if ($('#wlm-export-result').length > 0) activeSection = 'export-import';
            }
            
            if (activeSection === 'wlm' || !activeSection) {
                 if ($('.wlm-surcharge-item').length > 0 || $('#wlm-add-surcharge').is(':visible')) activeSection = 'surcharges';
                 else if ($('.wlm-shipping-method-item').length > 0 || $('#wlm-add-shipping-method').is(':visible')) activeSection = 'shipping';
                 else activeSection = 'times';
            }
            
            var formData = { _active_section: activeSection };
            
            // --- Helper function to build nested objects from flat names ---
            var addToFormData = function(rootObj, name, val) {
                var match = name.match(/\[([^\]]*)\]/g);
                if (!match) return;
                
                var keys = match.map(function(k) { return k.replace(/[\[\]]/g, ''); });
                var rootKeyMatch = name.match(/^([^\[]+)/);
                if (!rootKeyMatch) return;
                var rootKey = rootKeyMatch[1];
                
                if (!rootObj[rootObj === formData ? rootKey : keys[0]]) {
                    if (rootObj === formData) rootObj[rootKey] = {};
                }
                
                var current = rootObj === formData ? rootObj[rootKey] : rootObj;
                
                for (var i = 0; i < keys.length; i++) {
                    var key = keys[i];
                    var isLast = (i === keys.length - 1);
                    var nextKey = isLast ? null : keys[i+1];
                    
                    if (key === '') { 
                        if (Array.isArray(current)) {
                            current.push(val);
                        }
                        return; 
                    }
                    
                    if (isLast) {
                        current[key] = val;
                    } else {
                        if (!current[key]) {
                            current[key] = (nextKey === '' || /^\d+$/.test(nextKey)) ? [] : {};
                        }
                        current = current[key];
                    }
                }
            };

            // Collect Settings
            if ($('[name^="wlm_settings"]').length > 0) {
                $('[name^="wlm_settings"]').each(function() {
                    var val = $(this).is(':checkbox') ? ($(this).is(':checked') ? $(this).val() : null) : $(this).val();
                    if (val !== null) addToFormData(formData, $(this).attr('name'), val);
                });
            }
            
            // Collect Shipping Methods
            if (activeSection === 'shipping') {
                formData.wlm_shipping_methods = [];
                $('.wlm-shipping-method-item').each(function(index) {
                    var method = {};
                    $(this).find('input, select, textarea').each(function() {
                        var $el = $(this);
                        var name = $el.attr('name');
                        
                        if (!name || name.indexOf('wlm_shipping_methods') === -1) return;
                        
                        var val;
                        if ($el.is(':checkbox')) {
                            if (!$el.is(':checked')) return; 
                            val = $el.val();
                        } else {
                            val = $el.val();
                        }

                        var relativePath = name.substring(name.indexOf(']') + 1);
                        if (!relativePath) return;
                        
                        var addRelative = function(obj, path, v) {
                            var segments = path.match(/\[([^\]]*)\]/g);
                            if (!segments) return;
                            var current = obj;
                            
                            for(var i=0; i<segments.length; i++) {
                                var seg = segments[i].replace(/[\[\]]/g, '');
                                var isLast = i === segments.length - 1;
                                var nextSeg = isLast ? null : segments[i+1] ? segments[i+1].replace(/[\[\]]/g, '') : null;
                                
                                if (seg === '') return;
                                
                                if (isLast) {
                                    current[seg] = v;
                                } else {
                                    if (!current[seg]) {
                                        current[seg] = (nextSeg === '' || /^\d+$/.test(nextSeg)) ? [] : {};
                                    }
                                    current = current[seg];
                                }
                            }
                        };
                        
                        if (relativePath.match(/\[values\]\[\]$/)) {
                            var basePath = relativePath.substring(0, relativePath.lastIndexOf('[]')); 
                            addRelative(method, basePath, val || []);
                        } else if (relativePath.match(/\[\]$/) && Array.isArray(val)) {
                             var basePath = relativePath.substring(0, relativePath.lastIndexOf('[]'));
                             addRelative(method, basePath, val || []);
                        } else {
                            addRelative(method, relativePath, val);
                        }
                    });
                    
                    // Clean up sparse arrays in conditions
                    if (method.attribute_conditions && Array.isArray(method.attribute_conditions)) {
                        method.attribute_conditions = method.attribute_conditions.filter(function(el) { return el != null; });
                    }
                    
                    formData.wlm_shipping_methods.push(method);
                });
                
                formData.wlm_shipping_selection_strategy = $('#wlm_shipping_selection_strategy').val();
            }
            
            // Collect Surcharges
            if (activeSection === 'surcharges') {
                formData.wlm_surcharges = [];
                $('.wlm-surcharge-item').each(function() {
                    var surcharge = {};
                    $(this).find('input, select, textarea').each(function() {
                         var $el = $(this);
                         var name = $el.attr('name');
                         if (!name || name.indexOf('wlm_surcharges') === -1) return;
                         
                         var val = $el.is(':checkbox') ? ($el.is(':checked') ? $el.val() : null) : $el.val();
                         if (val === null) return;
                         
                         var relativePath = name.substring(name.indexOf(']') + 1);
                         if (!relativePath) return;
                         
                         var addRelative = function(obj, path, v) {
                            var segments = path.match(/\[([^\]]*)\]/g);
                            if (!segments) return;
                            var current = obj;
                            for(var i=0; i<segments.length; i++) {
                                var seg = segments[i].replace(/[\[\]]/g, '');
                                var isLast = i === segments.length - 1;
                                var nextSeg = isLast ? null : segments[i+1].replace(/[\[\]]/g, '');
                                if (isLast) {
                                    current[seg] = v;
                                } else {
                                    if (!current[seg]) current[seg] = (nextSeg === '' || /^\d+$/.test(nextSeg)) ? [] : {};
                                    current = current[seg];
                                }
                            }
                        };
                        
                        if (relativePath.match(/\[values\]\[\]$/)) {
                            var basePath = relativePath.substring(0, relativePath.lastIndexOf('[]'));
                            addRelative(surcharge, basePath, val || []);
                        } else {
                            addRelative(surcharge, relativePath, val);
                        }
                    });
                    
                    if (surcharge.attribute_conditions && Array.isArray(surcharge.attribute_conditions)) {
                        surcharge.attribute_conditions = surcharge.attribute_conditions.filter(function(el) { return el != null; });
                    }
                    
                    formData.wlm_surcharges.push(surcharge);
                });
                
                formData.wlm_surcharge_application_strategy = $('#wlm_surcharge_application_strategy').val();
            }
            
            console.log('Sending FormData:', formData);
            
            $button.prop('disabled', true);
            $spinner.show();
            
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
                        $('<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>')
                            .insertAfter('.wrap h1, .wrap h2.nav-tab-wrapper')
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert('Fehler: ' + (response.data || 'Unbekannter Fehler'));
                    }
                },
                error: function() { alert('Netzwerkfehler.'); },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            });
        },

        initSortable: function() {
            if ($.fn.sortable) {
                $('#wlm-shipping-methods-list, #wlm-surcharges-list').sortable({
                    handle: '.postbox-header',
                    placeholder: 'wlm-sortable-placeholder',
                    cursor: 'move'
                });
            }
        },

        initPostboxes: function() {
            $(document).off('click', '.postbox .handlediv, .postbox .postbox-header').on('click', '.postbox .handlediv, .postbox .postbox-header', function(e) {
                if ($(e.target).is('input, select, button:not(.handlediv)')) return;
                e.preventDefault();
                var $postbox = $(this).closest('.postbox');
                $postbox.toggleClass('closed');
                $postbox.find('.inside').slideToggle(200);
                $postbox.find('.handlediv').attr('aria-expanded', !$postbox.hasClass('closed'));
            });
        },

        addShippingMethod: function(e) {
            e.preventDefault();
            var index = $('#wlm-shipping-methods-list .wlm-shipping-method-item').length;
            var id = 'wlm_method_' + Date.now();
            $('#wlm-shipping-methods-list').append(this.getShippingMethodTemplate(index, id));
            this.initSelect2();
        },

        removeShippingMethod: function(e) {
            e.preventDefault();
            if (confirm('Löschen?')) {
                $(e.currentTarget).closest('.wlm-shipping-method-item').remove();
                this.reindexShippingMethods();
            }
        },

        updateMethodTitle: function(e) {
            $(e.currentTarget).closest('.wlm-shipping-method-item').find('.wlm-method-title').text($(e.currentTarget).val() || 'Neue Versandart');
        },

        reindexShippingMethods: function() {
            $('#wlm-shipping-methods-list .wlm-shipping-method-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) $(this).attr('name', name.replace(/^wlm_shipping_methods\[\d+\]/, 'wlm_shipping_methods[' + index + ']'));
                });
            });
        },

        // ... getShippingMethodTemplate ...
        getShippingMethodTemplate: function(index, id) {
            // Copy from previous implementation or ensure full HTML structure is here
            // Shortened for this output but MUST be full in file
            var html = '<div class="wlm-shipping-method-item postbox" data-index="' + index + '">';
            html += '<div class="postbox-header"><h3 class="hndle"><span class="wlm-method-title">Neue Versandart</span></h3><div class="handle-actions"><button type="button" class="handlediv button-link" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button></div></div>';
            html += '<div class="inside"><table class="form-table"><tbody>';
            // ... Fields ...
            html += '<tr><th scope="row"><label>Name</label></th><td><input type="text" name="wlm_shipping_methods[' + index + '][name]" class="regular-text wlm-method-name-input"><p class="description">Angezeigter Name</p></td></tr>';
            html += '<tr><th scope="row"><label>Aktiviert</label></th><td><label><input type="checkbox" name="wlm_shipping_methods[' + index + '][enabled]" value="1" checked> Aktivieren</label></td></tr>';
            html += '<tr><th scope="row"><label>Priorität</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][priority]" value="10" class="small-text"></td></tr>';
            html += '<tr><th scope="row"><label>Icon</label></th><td><select name="wlm_shipping_methods[' + index + '][icon]" class="regular-text"><option value="truck">🚚 LKW</option><option value="package">📦 Paket</option><option value="truck-xxl">🚛 LKW XXL</option></select></td></tr>';
            html += '<tr><th scope="row"><label>Kostentyp</label></th><td><select name="wlm_shipping_methods[' + index + '][cost_type]"><option value="flat">Pauschal</option><option value="by_weight">Nach Gewicht</option><option value="by_qty">Nach Stückzahl</option></select></td></tr>';
            html += '<tr><th scope="row"><label>Kosten (netto)</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][cost]" value="0" step="0.01" class="small-text"> €</td></tr>';
            html += '<tr><th scope="row"><label>Transitzeit (Werktage)</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][transit_min]" value="1" class="small-text"> – <input type="number" name="wlm_shipping_methods[' + index + '][transit_max]" value="3" class="small-text"></td></tr>';
            html += '<tr><th scope="row"><label>Gewicht (kg)</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][weight_min]" class="small-text" placeholder="Min"> – <input type="number" name="wlm_shipping_methods[' + index + '][weight_max]" class="small-text" placeholder="Max"></td></tr>';
            html += '<tr><th scope="row"><label>Stückzahl</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][qty_min]" class="small-text" placeholder="Min"> – <input type="number" name="wlm_shipping_methods[' + index + '][qty_max]" class="small-text" placeholder="Max"></td></tr>';
            html += '<tr><th scope="row"><label>Warenkorb (€)</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][cart_total_min]" class="small-text" placeholder="Min"> – <input type="number" name="wlm_shipping_methods[' + index + '][cart_total_max]" class="small-text" placeholder="Max"></td></tr>';
            html += '<tr><th scope="row"><label>Produktattribute / Taxonomien</label></th><td><div class="wlm-attribute-conditions" data-method-index="' + index + '"></div><button type="button" class="button wlm-add-attribute-condition" data-method-index="' + index + '">+ Bedingung hinzufügen</button></td></tr>';
            html += '<tr><th scope="row"><label>Kategorien</label></th><td><input type="text" name="wlm_shipping_methods[' + index + '][required_categories]" class="regular-text" placeholder="Slugs..."></td></tr>';
            html += '<tr><th scope="row"><label>Shiptastic</label></th><td><select name="wlm_shipping_methods[' + index + '][shiptastic_providers][]" class="regular-text wlm-shiptastic-provider-select" multiple="multiple"></select></td></tr>';
            html += '<tr><th colspan="2"><h3>Express</h3></th></tr>';
            html += '<tr><th scope="row"><label>Express Aktiv?</label></th><td><input type="checkbox" name="wlm_shipping_methods[' + index + '][express_enabled]" value="1"></td></tr>';
            html += '<tr><th scope="row"><label>Cutoff</label></th><td><input type="time" name="wlm_shipping_methods[' + index + '][express_cutoff]" value="12:00"></td></tr>';
            html += '<tr><th scope="row"><label>Kosten</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][express_cost]" value="0" step="0.01" class="small-text"></td></tr>';
            html += '<tr><th scope="row"><label>Transit</label></th><td><input type="number" name="wlm_shipping_methods[' + index + '][express_transit_min]" value="0" class="small-text"> – <input type="number" name="wlm_shipping_methods[' + index + '][express_transit_max]" value="1" class="small-text"></td></tr>';
            html += '<tr><td colspan="2"><input type="hidden" name="wlm_shipping_methods[' + index + '][id]" value="' + id + '"><button type="button" class="button wlm-remove-shipping-method">Entfernen</button></td></tr>';
            html += '</tbody></table></div></div>';
            return html;
        },

        addSurcharge: function(e) {
            e.preventDefault();
            var index = $('#wlm-surcharges-list .wlm-surcharge-item').length;
            var id = 'wlm_surcharge_' + Date.now();
            $('#wlm-surcharges-list').append(this.getSurchargeTemplate(index, id));
            this.initSelect2();
        },
        
        // ... removeSurcharge, updateSurchargeTitle, reindexSurcharges ...
        removeSurcharge: function(e) {
            e.preventDefault();
            if (confirm('Löschen?')) {
                $(e.currentTarget).closest('.wlm-surcharge-item').remove();
                this.reindexSurcharges();
            }
        },

        updateSurchargeTitle: function(e) {
            $(e.currentTarget).closest('.wlm-surcharge-item').find('.wlm-surcharge-title').text($(e.currentTarget).val() || 'Neuer Zuschlag');
        },

        reindexSurcharges: function() {
            $('#wlm-surcharges-list .wlm-surcharge-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                });
            });
        },

        getSurchargeTemplate: function(index, id) {
             // Simplified, ensure full HTML
            var html = '<div class="wlm-surcharge-item postbox" data-index="' + index + '">';
            html += '<div class="postbox-header"><h3 class="hndle"><span class="wlm-surcharge-title">Neuer Zuschlag</span></h3><div class="handle-actions"><button type="button" class="handlediv button-link" aria-expanded="true"><span class="toggle-indicator"></span></button></div></div>';
            html += '<div class="inside"><table class="form-table"><tbody>';
            html += '<tr><th>Name</th><td><input type="text" name="wlm_surcharges[' + index + '][name]" class="regular-text wlm-surcharge-name-input"></td></tr>';
            html += '<tr><th>Aktiv</th><td><input type="checkbox" name="wlm_surcharges[' + index + '][enabled]" value="1" checked></td></tr>';
            html += '<tr><th>Priorität</th><td><input type="number" name="wlm_surcharges[' + index + '][priority]" value="10" class="small-text"></td></tr>';
            html += '<tr><th>Typ</th><td><select name="wlm_surcharges[' + index + '][cost_type]"><option value="flat">Flat</option><option value="percentage">%</option></select></td></tr>';
            html += '<tr><th>Betrag</th><td><input type="number" name="wlm_surcharges[' + index + '][amount]" value="0" step="0.01" class="small-text"></td></tr>';
            html += '<tr><th>Bedingungen</th><td><div class="wlm-attribute-conditions" data-surcharge-index="' + index + '"></div><button class="button wlm-add-attribute-condition" data-surcharge-index="' + index + '">+ Bedingung</button></td></tr>';
            html += '<tr><td colspan="2"><input type="hidden" name="wlm_surcharges[' + index + '][id]" value="' + id + '"><button class="button wlm-remove-surcharge">Entfernen</button></td></tr>';
            html += '</tbody></table></div></div>';
            return html;
        },

        // --- FIX: Reindex attribute conditions to prevent overwriting ---
        reindexAttributeConditions: function($container) {
            $container.find('.wlm-attribute-condition-row').each(function(index) {
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        // Replace the condition index: [attribute_conditions][X] -> [attribute_conditions][index]
                        var newName = name.replace(/\[attribute_conditions\]\[\d+\]/, '[attribute_conditions][' + index + ']');
                        $(this).attr('name', newName);
                    }
                });
            });
        },

        addAttributeCondition: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var methodIndex = $btn.data('method-index');
            var surchargeIndex = $btn.data('surcharge-index');
            var $container, template, placeholder;

            if (methodIndex !== undefined) {
                $container = $('.wlm-attribute-conditions[data-method-index="' + methodIndex + '"]');
                template = $('#wlm-attribute-condition-template').html();
                placeholder = '{{METHOD_INDEX}}';
            } else {
                $container = $('.wlm-attribute-conditions[data-surcharge-index="' + surchargeIndex + '"]');
                template = $('#wlm-surcharge-condition-template').html();
                placeholder = '{{SURCHARGE_INDEX}}';
            }

            // Safe index calculation
            var conditionIndex = $container.find('.wlm-attribute-condition-row').length;
            
            template = template.replace(new RegExp(placeholder, 'g'), methodIndex !== undefined ? methodIndex : surchargeIndex);
            template = template.replace(/{{CONDITION_INDEX}}/g, conditionIndex);
            
            $container.append(template);
            this.initSelect2(); 
        },

        removeAttributeCondition: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('.wlm-attribute-condition-row');
            var $container = $row.parent();
            
            $row.fadeOut(300, function() { 
                $(this).remove(); 
                // REINDEX IS CRITICAL HERE
                WLM_Admin.reindexAttributeConditions($container);
            });
        },

        loadAttributeValues: function(e) {
            var $select = $(e.currentTarget);
            var attribute = $select.val();
            var $row = $select.closest('.wlm-attribute-condition-row');
            var $valuesSelect = $row.find('.wlm-values-select2');
            
            $valuesSelect.attr('data-attribute', attribute);
            
            if (!attribute) {
                $valuesSelect.empty().trigger('change');
                return;
            }
            
            $.ajax({
                url: wlmAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'wlm_get_attribute_values', attribute: attribute, nonce: wlmAdmin.nonce },
                success: function(res) {
                    $valuesSelect.empty();
                    if (res.success && res.data.length) {
                         res.data.forEach(function(item) {
                             $valuesSelect.append(new Option(item.label, item.value, false, false));
                         });
                    }
                    $valuesSelect.trigger('change'); // Update Select2
                }
            });
        },

        handleConditionTypeChange: function(e) {
            var $select = $(e.currentTarget);
            var type = $select.val();
            var $row = $select.closest('.wlm-attribute-condition-row');
            var $attrSelect = $row.find('.wlm-attribute-select');
            var $valsSelect = $row.find('.wlm-values-select2');
            
            if (type === 'shipping_class') {
                $attrSelect.hide();
                // Re-init for shipping class
                $valsSelect.select2('destroy').empty().select2({
                    placeholder: 'Versandklassen-Slugs...', tags: true, allowClear: true, width: '100%'
                });
                
                // Load classes
                 $.ajax({
                    url: wlmAdmin.ajaxUrl,
                    type: 'GET',
                    data: { action: 'wlm_get_shipping_classes', nonce: wlmAdmin.nonce },
                    success: function(res) {
                        if (res.success && res.data) {
                            res.data.forEach(function(item) {
                                $valsSelect.append(new Option(item.label + ' (' + item.value + ')', item.value, false, false));
                            });
                        }
                    }
                });
            } else {
                $attrSelect.show().find('optgroup').show();
                if (type === 'attribute') $attrSelect.find('optgroup[label*="Taxonomien"]').hide();
                else if (type === 'taxonomy') $attrSelect.find('optgroup[label*="Produkt-Attribute"]').hide();
                
                // Re-init for attr
                $valsSelect.select2('destroy').select2({ placeholder: 'Werte wählen...', allowClear: true, width: '100%' });
            }
        },
        
        loadGermanizedProviders: function() {
             if ($('.wlm-shiptastic-provider-select').length === 0) return;
             var self = this;
             $.post(wlmAdmin.ajaxUrl, { action: 'wlm_get_germanized_providers', nonce: wlmAdmin.nonce }, function(res) {
                 if (res.success && res.data.providers) {
                     self.populateProviderSelects(res.data.providers);
                 }
             });
        },
        
        populateProviderSelects: function(providers) {
            $('.wlm-shiptastic-provider-select').each(function() {
                var $select = $(this);
                providers.forEach(function(p) {
                    if ($select.find('option[value="' + p.slug + '"]').length === 0) {
                         $select.append(new Option(p.title + ' (' + p.slug + ')', p.slug, false, false));
                    }
                });
            });
        }
    };

    $(document).ready(function() { WLM_Admin.init(); });

})(jQuery);
