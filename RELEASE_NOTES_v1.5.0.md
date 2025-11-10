# Release Notes: WooCommerce Lieferzeiten Manager v1.5.0

**Release Date:** 10. November 2025  
**Type:** Major Bug Fix + Feature Release  
**Status:** Ready for Testing

---

## 🎯 Hauptziel dieser Version

**Problem behoben:** Shipping Rates erschienen in Debug-Logs aber NICHT im Cart/Checkout DOM.

Dieses kritische Problem verhinderte, dass Benutzer die konfigurierten Versandarten im Frontend sehen und auswählen konnten. Version 1.5.0 implementiert eine fundamentale Architekturänderung, um dieses Problem zu beheben.

---

## ✨ Was ist neu?

### 1. Proper WC_Shipping_Method Registration

**Vorher (v1.4.7):**
```php
// Rates wurden direkt über Filter hinzugefügt
add_filter('woocommerce_package_rates', array($this, 'add_shipping_rates'), 100, 2);
$rate = new WC_Shipping_Rate($method_id, $label, $cost);
$rates[$method_id] = $rate;
```

**Jetzt (v1.5.0):**
```php
// Proper WC_Shipping_Method Klassen werden registriert
add_filter('woocommerce_shipping_methods', array($this, 'register_shipping_methods'));

class WLM_Shipping_Method_123 extends WC_Shipping_Method {
    public function calculate_shipping($package) {
        $this->add_rate(array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost
        ));
    }
}
```

**Vorteile:**
- ✅ Volle Integration in WooCommerce's Shipping System
- ✅ Rates werden korrekt im DOM gerendert
- ✅ Benutzer können Versandarten auswählen
- ✅ Kompatibel mit WooCommerce Themes und Plugins

### 2. Automatische Shipping Zone Integration

Neue Funktionalität: Versandarten werden **automatisch zu allen Shipping Zones hinzugefügt**.

**Implementierung:**
- `ensure_methods_in_zones()` in `WLM_Core` (läuft bei Plugin-Init)
- `update_zones_after_save()` in `WLM_Admin` (läuft beim Speichern)

**Ergebnis:**
- ✅ Keine manuelle Aktivierung in Zones notwendig
- ✅ Funktioniert wie "Conditional Shipping" Plugin
- ✅ Global verfügbar über alle Zones hinweg

### 3. Attribute-Bedingungen Datenstruktur Fix

**Problem:** Attribute-Bedingungen wurden als flache Keys gespeichert:
```php
// Falsch:
['attribute_conditions[0][attribute]' => 'pa_farbe']
['attribute_conditions[0][value]' => 'rot']
```

**Lösung:** Normalisierung zu verschachtelten Arrays:
```php
// Richtig:
['attribute_conditions' => [
    [
        'attribute' => 'pa_farbe',
        'operator' => '=',
        'value' => 'rot'
    ]
]]
```

### 4. AND/OR Logik für Bedingungen

Neue Funktionalität: Mehrere Attribute-Bedingungen können mit AND oder OR verknüpft werden.

**Beispiel:**
```php
// AND: Alle Bedingungen müssen erfüllt sein
'attribute_logic' => 'AND'
'attribute_conditions' => [
    ['attribute' => 'pa_farbe', 'value' => 'rot'],
    ['attribute' => 'pa_groesse', 'value' => 'L']
]
// → Nur wenn Produkt ROT UND GROSS L

// OR: Mindestens eine Bedingung muss erfüllt sein
'attribute_logic' => 'OR'
'attribute_conditions' => [
    ['attribute' => 'pa_farbe', 'value' => 'rot'],
    ['attribute' => 'pa_farbe', 'value' => 'blau']
]
// → Wenn Produkt ROT ODER BLAU
```

### 5. Erweiterte Operator-Unterstützung

Neue Operatoren für Attribute-Bedingungen:
- `=` (Gleich) - Standard
- `!=` (Ungleich)
- `contains` (Enthält)

**Beispiel:**
```php
// Versandart nur wenn Produkt NICHT "Sperrgut" ist
['attribute' => 'pa_versandklasse', 'operator' => '!=', 'value' => 'sperrgut']

// Versandart nur wenn Produktname "Express" enthält
['attribute' => 'pa_name', 'operator' => 'contains', 'value' => 'express']
```

---

## 🔧 Technische Änderungen

### Geänderte Dateien

1. **includes/class-wlm-shipping-methods.php**
   - Neue Methode: `register_shipping_methods()`
   - Neue Methode: `create_method_class()`
   - Überarbeitet: `check_method_conditions()` (Attribute-Logik)
   - Entfernt: `add_shipping_rates()`, `preserve_global_rates()`

2. **includes/class-wlm-core.php**
   - Neue Methode: `ensure_methods_in_zones()`
   - Neue Hooks: `woocommerce_shipping_init`, `woocommerce_init`

3. **includes/class-wlm-admin.php**
   - Neue Methode: `update_zones_after_save()`
   - Verbessert: `ajax_save_settings()` (Normalisierung)

4. **woo-lieferzeiten-manager.php**
   - Version aktualisiert: 1.4.7 → 1.5.0

### Neue Dateien

- `TESTING.md` - Umfassende Test-Anweisungen
- `CHANGELOG.md` - Versions-Historie
- `RELEASE_NOTES_v1.5.0.md` - Diese Datei

---

## 📋 Upgrade-Anweisungen

### Für Entwickler

```bash
# 1. Repository aktualisieren
cd wp-content/plugins/woo-lieferzeiten-manager
git pull origin main

# 2. Plugin deaktivieren und reaktivieren
# WordPress Admin → Plugins → WooCommerce Lieferzeiten Manager
# - Deaktivieren
# - Aktivieren

# 3. Caches leeren
# - Browser-Cache (Strg+Shift+R)
# - WordPress Object Cache
# - WooCommerce Transients (WooCommerce → Status → Tools)

# 4. Versandarten neu speichern
# WooCommerce → Einstellungen → Versand → MEGA Versandmanager
# - Einstellungen öffnen
# - "Speichern" klicken (triggert Zone-Update)
```

### Für Benutzer

1. **Plugin-Update installieren**
2. **Plugin deaktivieren und reaktivieren**
3. **Versandarten-Einstellungen öffnen und speichern**
4. **Shipping Zones prüfen**: WooCommerce → Einstellungen → Versand → Zones
   - Alle WLM-Versandarten sollten automatisch in allen Zones erscheinen
5. **Frontend testen**: Produkt in Warenkorb → Zur Kasse
   - Versandarten sollten sichtbar und auswählbar sein

---

## ✅ Test-Checkliste

### Kritische Tests

- [ ] **Frontend Rendering**
  - [ ] Versandarten erscheinen im Warenkorb
  - [ ] Versandarten erscheinen im Checkout
  - [ ] Versandarten sind auswählbar (Radio Buttons)
  - [ ] Lieferzeitfenster werden unter Versandarten angezeigt

- [ ] **Kostenberechnung**
  - [ ] Versandkosten werden korrekt angezeigt
  - [ ] Gesamtpreis wird bei Auswahl aktualisiert
  - [ ] Express-Aufpreis funktioniert

- [ ] **Bedingungen**
  - [ ] Gewichtsbedingungen funktionieren
  - [ ] Warenkorbwert-Bedingungen funktionieren
  - [ ] Attribute-Bedingungen funktionieren
  - [ ] AND/OR-Logik funktioniert

- [ ] **Shipping Zones**
  - [ ] Methoden erscheinen automatisch in allen Zones
  - [ ] Neue Methoden werden beim Speichern zu Zones hinzugefügt
  - [ ] "Rest of the World" Zone wird unterstützt

### Optionale Tests

- [ ] Express-Versand Button funktioniert
- [ ] Lieferzeitfenster-Berechnung korrekt
- [ ] Debug-Logs zeigen korrekte Informationen
- [ ] Admin-Interface funktioniert ohne Fehler

---

## 🐛 Bekannte Probleme

### Keine kritischen Probleme bekannt

Alle bekannten Probleme aus v1.4.7 wurden behoben.

### Potenzielle Probleme

1. **Cache-Probleme**
   - **Symptom:** Alte JavaScript-Version lädt
   - **Lösung:** Browser-Cache leeren (Strg+Shift+R)

2. **Theme-Kompatibilität**
   - **Symptom:** Versandarten werden nicht angezeigt
   - **Lösung:** Mit Standard-Theme (Storefront) testen
   - **Workaround:** Theme-Entwickler kontaktieren

3. **Plugin-Konflikte**
   - **Symptom:** Versandarten verschwinden
   - **Lösung:** Andere Shipping-Plugins deaktivieren
   - **Debug:** WooCommerce → Status → Logs prüfen

---

## 🔍 Debug-Informationen

### Wichtige Log-Einträge

**Erfolgreiche Registrierung:**
```
WLM: Added method wlm_method_1762783567431 to zone 0
```

**Rate wird berechnet:**
```
WLM: Added rate for method: wlm_method_1762783567431 - Cost: 4.9
```

**Finale Rates:**
```
WLM: === FINAL RATES (Priority 999) ===
WLM: Total rates: 2
WLM: Rate ID: wlm_method_1762783567431:1 - Label: Standardversand
```

**Attribute-Normalisierung:**
```
WLM: Normalized attribute_conditions for method 0: Array(...)
```

### Debug-Modus aktivieren

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs ansehen:
```bash
tail -f wp-content/debug.log
```

---

## 📊 Performance

### Geschwindigkeits-Optimierungen

- **Einmalige Registrierung:** Shipping Methods werden nur einmal pro Request registriert
- **Zone-Update:** Läuft nur beim Speichern, nicht bei jedem Request
- **Caching:** WooCommerce's internes Caching wird genutzt

### Erwartete Performance

- **Frontend:** Keine spürbare Verzögerung
- **Backend:** Minimal längere Speicherzeit (Zone-Updates)
- **Checkout:** Identisch zu Standard-WooCommerce Shipping Methods

---

## 🎓 Für Entwickler

### Architektur-Übersicht

```
WooCommerce Shipping System
    ↓
woocommerce_shipping_methods Filter
    ↓
WLM_Shipping_Methods::register_shipping_methods()
    ↓
Dynamische Klassen-Erstellung (eval)
    ↓
WLM_Shipping_Method_{id} extends WC_Shipping_Method
    ↓
calculate_shipping() → add_rate()
    ↓
WooCommerce rendert Rates im Frontend
```

### Hook-Reihenfolge

1. `woocommerce_shipping_init` → `ensure_methods_in_zones()`
2. `woocommerce_shipping_methods` → `register_shipping_methods()`
3. `woocommerce_package_rates` (Priority 999) → `debug_final_rates()`
4. `woocommerce_after_shipping_rate` → `display_delivery_window()`

### Erweiterungsmöglichkeiten

**Eigene Bedingungen hinzufügen:**
```php
add_filter('wlm_check_method_conditions', function($result, $method, $package) {
    // Eigene Logik
    if ($method['custom_condition']) {
        return my_custom_check($package);
    }
    return $result;
}, 10, 3);
```

**Eigene Operatoren hinzufügen:**
```php
// In check_method_conditions() Methode erweitern:
case 'my_operator':
    if (my_custom_comparison($product_attr, $attr_value)) {
        $condition_met = true;
    }
    break;
```

---

## 📞 Support

### Bei Problemen

1. **Debug-Logs sammeln** (`wp-content/debug.log`)
2. **Browser-Konsole prüfen** (F12 → Console)
3. **WooCommerce System Status** (WooCommerce → Status → System Status)
4. **Issue auf GitHub erstellen**: https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager/issues

### Hilfreiche Informationen für Support-Anfragen

- WordPress Version
- WooCommerce Version
- PHP Version
- Aktives Theme
- Aktive Plugins (besonders andere Shipping-Plugins)
- Debug-Logs
- Screenshots vom Problem

---

## 🚀 Roadmap

### Geplant für v1.5.1

- [ ] UI-Verbesserungen für Attribute-Bedingungen
- [ ] Operator-Auswahl im Admin-Interface
- [ ] AND/OR-Toggle im Admin-Interface
- [ ] Bulk-Edit für Versandarten
- [ ] Import/Export-Funktion

### Geplant für v1.6.0

- [ ] Multi-Zone-Unterstützung (verschiedene Methoden pro Zone)
- [ ] Zeitbasierte Bedingungen (z.B. nur an Wochentagen)
- [ ] Produktkategorie-Bedingungen
- [ ] Benutzergruppen-Bedingungen
- [ ] API für Drittanbieter-Integration

---

## 📝 Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für vollständige Versions-Historie.

---

## 📄 Lizenz

GPL v2 or later

---

**Viel Erfolg beim Testen! 🎉**

Bei Fragen oder Problemen erstellen Sie bitte ein Issue auf GitHub.
