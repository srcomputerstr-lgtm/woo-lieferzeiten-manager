<?php
/**
 * Export/Import Tab Template
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wlm-export-import-section">
    <h2><?php esc_html_e('Export / Import', 'woo-lieferzeiten-manager'); ?></h2>
    
    <div class="wlm-section-grid">
        <!-- Export Section -->
        <div class="wlm-card">
            <h3>📤 <?php esc_html_e('Einstellungen exportieren', 'woo-lieferzeiten-manager'); ?></h3>
            <p><?php esc_html_e('Exportiere alle Plugin-Einstellungen als JSON-Datei. Diese kann auf einer anderen WordPress-Installation importiert werden.', 'woo-lieferzeiten-manager'); ?></p>
            
            <div class="wlm-export-options">
                <label>
                    <input type="checkbox" name="export_settings" value="1" checked>
                    <strong><?php esc_html_e('Allgemeine Einstellungen', 'woo-lieferzeiten-manager'); ?></strong>
                    <span class="description"><?php esc_html_e('Werktage, Cut-Off Zeit, Bearbeitungszeit, Feiertage', 'woo-lieferzeiten-manager'); ?></span>
                </label>
                
                <label>
                    <input type="checkbox" name="export_shipping_methods" value="1" checked>
                    <strong><?php esc_html_e('Versandarten', 'woo-lieferzeiten-manager'); ?></strong>
                    <span class="description"><?php esc_html_e('Alle konfigurierten Versandarten mit Transit-Zeiten und Express-Optionen', 'woo-lieferzeiten-manager'); ?></span>
                </label>
                
                <label>
                    <input type="checkbox" name="export_surcharges" value="1" checked>
                    <strong><?php esc_html_e('Versandzuschläge', 'woo-lieferzeiten-manager'); ?></strong>
                    <span class="description"><?php esc_html_e('Alle konfigurierten Zuschläge', 'woo-lieferzeiten-manager'); ?></span>
                </label>
            </div>
            
            <button type="button" id="wlm-export-settings" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Jetzt exportieren', 'woo-lieferzeiten-manager'); ?>
            </button>
            
            <div id="wlm-export-result" class="wlm-result-message" style="display:none;"></div>
        </div>
        
        <!-- Import Section -->
        <div class="wlm-card">
            <h3>📥 <?php esc_html_e('Einstellungen importieren', 'woo-lieferzeiten-manager'); ?></h3>
            <p><?php esc_html_e('Importiere zuvor exportierte Einstellungen. Bestehende Einstellungen werden überschrieben!', 'woo-lieferzeiten-manager'); ?></p>
            
            <div class="wlm-import-area">
                <input type="file" id="wlm-import-file" accept=".json" style="display:none;">
                <button type="button" id="wlm-select-file" class="button">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('JSON-Datei auswählen', 'woo-lieferzeiten-manager'); ?>
                </button>
                <span id="wlm-selected-file" class="description"></span>
            </div>
            
            <div id="wlm-import-preview" class="wlm-import-preview" style="display:none;">
                <h4><?php esc_html_e('Vorschau:', 'woo-lieferzeiten-manager'); ?></h4>
                <div id="wlm-import-preview-content"></div>
                
                <div class="wlm-import-options">
                    <label>
                        <input type="checkbox" name="import_settings" value="1" checked>
                        <strong><?php esc_html_e('Allgemeine Einstellungen importieren', 'woo-lieferzeiten-manager'); ?></strong>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="import_shipping_methods" value="1" checked>
                        <strong><?php esc_html_e('Versandarten importieren', 'woo-lieferzeiten-manager'); ?></strong>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="import_surcharges" value="1" checked>
                        <strong><?php esc_html_e('Versandzuschläge importieren', 'woo-lieferzeiten-manager'); ?></strong>
                    </label>
                </div>
                
                <div class="wlm-import-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php esc_html_e('Achtung:', 'woo-lieferzeiten-manager'); ?></strong>
                    <?php esc_html_e('Bestehende Einstellungen werden überschrieben! Bitte erstelle vorher ein Backup.', 'woo-lieferzeiten-manager'); ?>
                </div>
                
                <button type="button" id="wlm-import-settings" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Jetzt importieren', 'woo-lieferzeiten-manager'); ?>
                </button>
            </div>
            
            <div id="wlm-import-result" class="wlm-result-message" style="display:none;"></div>
        </div>
    </div>
    
    <div class="wlm-info-box">
        <h4>ℹ️ <?php esc_html_e('Hinweise', 'woo-lieferzeiten-manager'); ?></h4>
        <ul>
            <li><?php esc_html_e('Die Export-Datei enthält alle Plugin-Einstellungen im JSON-Format.', 'woo-lieferzeiten-manager'); ?></li>
            <li><?php esc_html_e('Produktspezifische Lieferzeiten werden NICHT exportiert (nur Plugin-Einstellungen).', 'woo-lieferzeiten-manager'); ?></li>
            <li><?php esc_html_e('Beim Import werden bestehende Einstellungen komplett überschrieben.', 'woo-lieferzeiten-manager'); ?></li>
            <li><?php esc_html_e('Erstelle vor dem Import immer ein Backup deiner aktuellen Einstellungen.', 'woo-lieferzeiten-manager'); ?></li>
        </ul>
    </div>
</div>

<style>
.wlm-export-import-section {
    max-width: 1200px;
}

.wlm-section-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.wlm-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.wlm-card h3 {
    margin-top: 0;
    font-size: 18px;
}

.wlm-export-options label,
.wlm-import-options label {
    display: block;
    margin: 15px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.wlm-export-options label input,
.wlm-import-options label input {
    margin-right: 8px;
}

.wlm-export-options .description,
.wlm-import-options .description {
    display: block;
    margin-left: 24px;
    color: #666;
    font-size: 13px;
}

.wlm-import-area {
    margin: 20px 0;
}

.wlm-import-preview {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.wlm-import-preview-content {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    margin: 10px 0;
    font-family: monospace;
    font-size: 13px;
    max-height: 200px;
    overflow-y: auto;
}

.wlm-import-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    padding: 12px;
    margin: 15px 0;
    color: #856404;
}

.wlm-import-warning .dashicons {
    color: #ffc107;
    margin-right: 5px;
}

.wlm-result-message {
    margin: 15px 0;
    padding: 12px;
    border-radius: 4px;
}

.wlm-result-message.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.wlm-result-message.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.wlm-info-box {
    background: #e7f3ff;
    border: 1px solid #2196f3;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.wlm-info-box h4 {
    margin-top: 0;
}

.wlm-info-box ul {
    margin: 10px 0;
    padding-left: 20px;
}

.wlm-info-box li {
    margin: 8px 0;
}

@media (max-width: 768px) {
    .wlm-section-grid {
        grid-template-columns: 1fr;
    }
}
</style>
