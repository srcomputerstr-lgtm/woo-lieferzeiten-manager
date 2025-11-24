/**
 * Export/Import functionality
 */
(function($) {
    'use strict';
    
    // Export settings
    $('#wlm-export-settings').on('click', function() {
        const button = $(this);
        const resultDiv = $('#wlm-export-result');
        
        // Get selected options
        const exportSettings = $('input[name="export_settings"]').is(':checked');
        const exportShipping = $('input[name="export_shipping_methods"]').is(':checked');
        const exportSurcharges = $('input[name="export_surcharges"]').is(':checked');
        
        if (!exportSettings && !exportShipping && !exportSurcharges) {
            resultDiv.removeClass('success').addClass('error')
                .html('Bitte wähle mindestens eine Option zum Exportieren.')
                .show();
            return;
        }
        
        button.prop('disabled', true).text('Exportiere...');
        resultDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wlm_export_settings',
                nonce: wlmAdmin.nonce,
                export_settings: exportSettings ? '1' : '0',
                export_shipping_methods: exportShipping ? '1' : '0',
                export_surcharges: exportSurcharges ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    // Create download
                    const dataStr = JSON.stringify(response.data.data, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    const url = URL.createObjectURL(dataBlob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    resultDiv.removeClass('error').addClass('success')
                        .html('✅ Export erfolgreich! Die Datei wurde heruntergeladen.')
                        .show();
                } else {
                    resultDiv.removeClass('success').addClass('error')
                        .html('❌ Fehler: ' + (response.data || 'Unbekannter Fehler'))
                        .show();
                }
            },
            error: function() {
                resultDiv.removeClass('success').addClass('error')
                    .html('❌ Netzwerkfehler beim Exportieren.')
                    .show();
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Jetzt exportieren');
            }
        });
    });
    
    // Select file for import
    $('#wlm-select-file').on('click', function() {
        $('#wlm-import-file').click();
    });
    
    // Handle file selection
    $('#wlm-import-file').on('change', function(e) {
        const file = e.target.files[0];
        
        if (!file) {
            return;
        }
        
        $('#wlm-selected-file').text(file.name);
        $('#wlm-import-result').hide();
        
        // Read file
        const reader = new FileReader();
        reader.onload = function(event) {
            try {
                const data = JSON.parse(event.target.result);
                
                // Validate
                if (!data.version) {
                    throw new Error('Ungültige Export-Datei: Version fehlt');
                }
                
                // Show preview
                let preview = '<ul>';
                if (data.settings) {
                    preview += '<li>✅ Allgemeine Einstellungen</li>';
                }
                if (data.shipping_methods) {
                    const count = Array.isArray(data.shipping_methods) ? data.shipping_methods.length : 0;
                    preview += '<li>✅ Versandarten (' + count + ')</li>';
                }
                if (data.surcharges) {
                    const count = Array.isArray(data.surcharges) ? data.surcharges.length : 0;
                    preview += '<li>✅ Zuschläge (' + count + ')</li>';
                }
                preview += '</ul>';
                preview += '<p><strong>Version:</strong> ' + data.version + '</p>';
                preview += '<p><strong>Exportiert am:</strong> ' + data.exported_at + '</p>';
                preview += '<p><strong>Von:</strong> ' + data.site_url + '</p>';
                
                $('#wlm-import-preview-content').html(preview);
                $('#wlm-import-preview').show();
                
                // Store data for import
                $('#wlm-import-preview').data('import-data', data);
                
            } catch (error) {
                $('#wlm-import-result').removeClass('success').addClass('error')
                    .html('❌ Fehler beim Lesen der Datei: ' + error.message)
                    .show();
                $('#wlm-import-preview').hide();
            }
        };
        reader.readAsText(file);
    });
    
    // Import settings
    $('#wlm-import-settings').on('click', function() {
        const button = $(this);
        const resultDiv = $('#wlm-import-result');
        const data = $('#wlm-import-preview').data('import-data');
        
        if (!data) {
            resultDiv.removeClass('success').addClass('error')
                .html('❌ Keine Daten zum Importieren.')
                .show();
            return;
        }
        
        // Get selected options
        const importSettings = $('input[name="import_settings"]').is(':checked');
        const importShipping = $('input[name="import_shipping_methods"]').is(':checked');
        const importSurcharges = $('input[name="import_surcharges"]').is(':checked');
        
        if (!importSettings && !importShipping && !importSurcharges) {
            resultDiv.removeClass('success').addClass('error')
                .html('❌ Bitte wähle mindestens eine Option zum Importieren.')
                .show();
            return;
        }
        
        // Confirm
        if (!confirm('Achtung! Bestehende Einstellungen werden überschrieben. Fortfahren?')) {
            return;
        }
        
        button.prop('disabled', true).text('Importiere...');
        resultDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wlm_import_settings',
                nonce: wlmAdmin.nonce,
                data: JSON.stringify(data),
                import_settings: importSettings ? '1' : '0',
                import_shipping_methods: importShipping ? '1' : '0',
                import_surcharges: importSurcharges ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('success')
                        .html('✅ ' + response.data.message + '<br><br>Die Seite wird neu geladen...')
                        .show();
                    
                    // Reload after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    resultDiv.removeClass('success').addClass('error')
                        .html('❌ Fehler: ' + (response.data || 'Unbekannter Fehler'))
                        .show();
                }
            },
            error: function() {
                resultDiv.removeClass('success').addClass('error')
                    .html('❌ Netzwerkfehler beim Importieren.')
                    .show();
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Jetzt importieren');
            }
        });
    });
    
})(jQuery);
