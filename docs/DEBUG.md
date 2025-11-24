# Debug-Anleitung für Woo Lieferzeiten Manager

## Problem: Versandarten werden nicht angezeigt

### Schritt 1: Debug-Modus aktivieren

Fügen Sie in Ihrer `wp-config.php` folgende Zeile hinzu:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Schritt 2: Versandarten prüfen

1. Gehen Sie zu **WooCommerce → Lieferzeiten → Versandarten**
2. Prüfen Sie:
   - ✅ Ist mindestens eine Versandart angelegt?
   - ✅ Ist die Versandart **aktiviert** (Checkbox "Versandart aktivieren")?
   - ✅ Hat die Versandart einen **Namen**?

### Schritt 3: Datenbank prüfen

Öffnen Sie phpMyAdmin und führen Sie aus:

```sql
SELECT * FROM wp_options WHERE option_name = 'wlm_shipping_methods';
```

**Erwartetes Ergebnis**: Ein Array mit Ihren Versandarten

**Wenn leer**: Die Versandarten wurden nicht gespeichert!

### Schritt 4: Produktseite prüfen

1. Öffnen Sie eine Produktseite
2. Öffnen Sie die Browser-Konsole (F12)
3. Suchen Sie nach Fehlern

### Schritt 5: Shortcode testen

Fügen Sie auf einer Testseite ein:

```
[wlm_delivery_info]
```

**Wenn nichts erscheint**:
- Produkt-Kontext fehlt
- Versandarten nicht konfiguriert
- Plugin nicht aktiviert

### Schritt 6: Debug-Logs prüfen

Die Debug-Logs finden Sie unter:
```
/wp-content/debug.log
```

Suchen Sie nach:
```
WLM Debug: get_applicable_shipping_method called
```

**Wichtige Informationen**:
- `shipping_methods_count`: Anzahl der konfigurierten Versandarten
- `shipping_methods`: Array mit allen Versandarten
- `count`: Anzahl der anwendbaren Versandarten

## Häufige Probleme

### Problem 1: "Paketdienst" wird angezeigt

**Ursache**: Keine Versandart gefunden oder Name ist leer

**Lösung**:
1. Prüfen Sie, ob Versandarten angelegt sind
2. Prüfen Sie, ob die Versandart aktiviert ist
3. Prüfen Sie, ob die Bedingungen erfüllt sind

### Problem 2: Versandart nicht in Warenkorb sichtbar

**Ursache**: Block-Warenkorb verwendet andere API

**Lösung**:
- Das Plugin unterstützt jetzt WooCommerce Blocks
- Stellen Sie sicher, dass Sie WooCommerce 8.0+ verwenden
- Prüfen Sie, ob die Blocks-Integration geladen wird

### Problem 3: Bedingungen werden nicht geprüft

**Ursache**: Bedingungen sind falsch konfiguriert

**Lösung**:
- Gewicht: Prüfen Sie, ob Produkte ein Gewicht haben
- Warenkorbsumme: Prüfen Sie die Min/Max-Werte
- Attribute: Verwenden Sie die Dropdown-Auswahl (nicht manuell eingeben!)
- Kategorien: Wählen Sie Kategorien aus der Liste

## Manuelle Prüfung der Versandarten

### PHP-Code zum Testen

Fügen Sie temporär in Ihre `functions.php` ein:

```php
add_action('wp_footer', function() {
    if (!is_admin() && current_user_can('manage_options')) {
        $methods = get_option('wlm_shipping_methods', array());
        echo '<pre style="background: #fff; padding: 20px; position: fixed; bottom: 0; right: 0; z-index: 9999; max-width: 500px; overflow: auto;">';
        echo 'WLM Versandarten Debug:' . "\n\n";
        echo 'Anzahl: ' . count($methods) . "\n\n";
        print_r($methods);
        echo '</pre>';
    }
});
```

**Wichtig**: Entfernen Sie diesen Code nach dem Testen!

## Support

Wenn das Problem weiterhin besteht:

1. Exportieren Sie die Versandarten-Konfiguration
2. Prüfen Sie die Debug-Logs
3. Erstellen Sie ein GitHub Issue mit:
   - WordPress-Version
   - WooCommerce-Version
   - PHP-Version
   - Debug-Logs
   - Screenshots der Konfiguration
