/**
 * Admin JavaScript for Woo Lieferzeiten Manager
 *
 * @package WooLieferzeitenManager
 */

(function($) {
    'use strict';

    var WLM_Admin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initPostboxes();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Holidays
            $(document).on('click', '#wlm-add-holiday', this.addHoliday.bind(this));
            $(document).on('click', '.wlm-remove-holiday', this.removeHoliday.bind(this));

            // Shipping methods
            $(document).on('click', '#wlm-add-shipping-method', this.addShippingMethod.bind(this));
            $(document).on('click', '.wlm-remove-shipping-method', this.removeShippingMethod.bind(this));
            $(document).on('input', '.wlm-method-name-input', this.updateMethodTitle.bind(this));

            // Surcharges
            $(document).on('click', '#wlm-add-surcharge', this.addSurcharge.bind(this));
            $(document).on('click', '.wlm-remove-surcharge', this.removeSurcharge.bind(this));
            $(document).on('input', '.wlm-surcharge-name-input', this.updateSurchargeTitle.bind(this));
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
         * Initialize postboxes (collapsible)
         */
        initPostboxes: function() {
            $('.wlm-shipping-method-item .handlediv, .wlm-shipping-method-item .postbox-header').on('click', function() {
                var $postbox = $(this).closest('.postbox');
                $postbox.find('.inside').slideToggle();
                $postbox.find('.handlediv').attr('aria-expanded', function(i, attr) {
                    return attr === 'true' ? 'false' : 'true';
                });
            });

            $('.wlm-surcharge-item .handlediv, .wlm-surcharge-item .postbox-header').on('click', function() {
                var $postbox = $(this).closest('.postbox');
                $postbox.find('.inside').slideToggle();
                $postbox.find('.handlediv').attr('aria-expanded', function(i, attr) {
                    return attr === 'true' ? 'false' : 'true';
                });
            });
        },

        /**
         * Add holiday
         */
        addHoliday: function(e) {
            e.preventDefault();

            var html = '<div class="wlm-holiday-item">';
            html += '<input type="date" name="wlm_settings[holidays][]" value="" class="regular-text">';
            html += '<button type="button" class="button wlm-remove-holiday">Entfernen</button>';
            html += '</div>';

            $('#wlm-holidays-list').append(html);
        },

        /**
         * Remove holiday
         */
        removeHoliday: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.wlm-holiday-item').remove();
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
            
            html += '<tr><th scope="row"><label>Name</label></th>';
            html += '<td><input type="text" name="wlm_shipping_methods[' + index + '][name]" value="" class="regular-text wlm-method-name-input"></td></tr>';
            
            html += '<tr><th scope="row"><label>Priorität</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][priority]" value="10" min="0" step="1" class="small-text"></td></tr>';
            
            html += '<tr><th scope="row"><label>Kostentyp</label></th>';
            html += '<td><select name="wlm_shipping_methods[' + index + '][cost_type]">';
            html += '<option value="flat">Pauschal</option>';
            html += '<option value="by_weight">Nach Gewicht</option>';
            html += '<option value="by_qty">Nach Stückzahl</option>';
            html += '</select></td></tr>';
            
            html += '<tr><th scope="row"><label>Kosten (netto)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][cost]" value="0" min="0" step="0.01" class="small-text"> €</td></tr>';
            
            html += '<tr><th scope="row"><label>Transitzeit Min/Max (Werktage)</label></th>';
            html += '<td><input type="number" name="wlm_shipping_methods[' + index + '][transit_min]" value="1" min="0" step="1" class="small-text"> – ';
            html += '<input type="number" name="wlm_shipping_methods[' + index + '][transit_max]" value="3" min="0" step="1" class="small-text"></td></tr>';
            
            html += '<tr><td colspan="2">';
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
            
            html += '<tr><th scope="row"><label>Betrag (netto)</label></th>';
            html += '<td><input type="number" name="wlm_surcharges[' + index + '][amount]" value="0" min="0" step="0.01" class="small-text"> €</td></tr>';
            
            html += '<tr><td colspan="2">';
            html += '<input type="hidden" name="wlm_surcharges[' + index + '][id]" value="' + id + '">';
            html += '<button type="button" class="button wlm-remove-surcharge">Zuschlag entfernen</button>';
            html += '</td></tr>';
            
            html += '</tbody></table>';
            html += '</div>';
            html += '</div>';

            return html;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WLM_Admin.init();
    });

})(jQuery);
