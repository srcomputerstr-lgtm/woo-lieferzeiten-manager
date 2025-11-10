# Woo Lieferzeiten Manager

Ein umfassendes WooCommerce-Plugin zur Verwaltung von Lieferzeiten, Versandarten, Express-Optionen und Versandzuschlägen mit voller Unterstützung für Block-basierte Warenkorb- und Checkout-Seiten.

## Features

### Kernfunktionalität

- **Zentrale Lieferzeitverwaltung**: Verwaltung aller Lieferzeit- und Versandlogik im WordPress-Backend
- **Dynamische Echtzeit-Berechnung**: Automatische Berechnung von Lieferfenstern auf Produktseiten, im Warenkorb und im Checkout
- **Produktverfügbarkeit**: Custom Fields für "Lieferbar ab"-Datum und Lead-Time
- **Versandarten**: Konfigurierbare Versandmethoden mit Bedingungen, Kosten und Transitzeiten
- **Express-Option**: Optionale Express-Versand-Funktion mit reduzierten Lieferzeiten
- **Versandzuschläge**: Regelbasierte Zuschläge für Sperrgut, Gefahrgut etc.
- **REST API**: ERP-Integration für automatisierte Produktverfügbarkeitspflege
- **Block-Kompatibilität**: Volle Unterstützung für WooCommerce Cart und Checkout Blocks

### Berechnungslogik

- **Cutoff-Zeit**: Globale und methodenspezifische Bestellannahmeschlusszeiten
- **Business Days**: Nur Werktage (Mo-Fr standardmäßig, konfigurierbar)
- **Feiertage**: Ausschluss von Feiertagen bei der Berechnung
- **Processing Time**: Bearbeitungszeit (Min/Max in Werktagen)
- **Transit Time**: Versandzeit pro Methode (Min/Max in Werktagen)

### Frontend-Anzeige

#### Produktdetailseite
- Lagerstatus mit Anzahl oder "Wieder verfügbar ab"-Datum
- Versandart mit Info-Icon und Tooltip
- Lieferfenster (z.B. "Mi, 12.11. – Fr, 14.11.")
- AJAX-Aktualisierung bei Varianten- oder Mengenänderung

#### Warenkorb
- Lagerstatus je Artikel
- Lieferfenster unter Versandarten
- Express-CTA (wenn verfügbar)
- Gesamtlieferfenster über Versandartenliste

#### Checkout
- Lieferfenster-Anzeige
- Express-Status mit Entfernen-Button
- Block-Layout-Unterstützung

## Installation

1. Laden Sie den Plugin-Ordner `woo-lieferzeiten-manager` in das Verzeichnis `/wp-content/plugins/` hoch
2. Aktivieren Sie das Plugin über das 'Plugins'-Menü in WordPress
3. Navigieren Sie zu WooCommerce → Lieferzeiten, um die Einstellungen zu konfigurieren

## Konfiguration

### Tab "Zeiten"

- **Cutoff-Zeit**: Bestellannahmeschluss (z.B. 14:00)
- **Werktage**: Auswahl der Bearbeitungstage (Mo-Fr)
- **Feiertage**: Datumsauswahl für auszuschließende Tage
- **Bearbeitungszeit**: Min/Max in Werktagen
- **Standard-Lieferzeit**: Fallback für Produkte ohne spezifische Angabe
- **Maximal sichtbarer Bestand**: Obergrenze für Frontend-Anzeige
- **Debug-Modus**: Detaillierte Berechnungsinformationen (nur für Admins)

### Tab "Versandarten"

Konfiguration eigener Versandmethoden mit:
- Name und Priorität
- Kostentyp (Pauschal, nach Gewicht, nach Stückzahl)
- Kosten (netto)
- Gewichtsbeschränkungen (Min/Max)
- Transitzeiten (Min/Max in Werktagen)
- Express-Option (aktivierbar mit eigenem Zuschlag und Cutoff-Zeit)

### Tab "Zuschläge"

Regelbasierte Versandzuschläge mit:
- Name und Aktivierungsstatus
- Betrag (netto)
- Steuerklasse
- Bedingungen (Gewicht, Warenkorbwert, Produktattribute)
- Stacking-Regel (Addieren, Maximum, Erster Treffer)
- Optionen (Freigrenze ignorieren, Rabattierbar, Bei Express anwenden)

## Shortcodes

Das Plugin bietet flexible Shortcodes für die Integration in Page Builder wie Oxygen, Elementor oder Gutenberg.

### Verfügbare Shortcodes

- `[wlm_delivery_info]` - Komplettes Lieferzeiten-Panel
- `[wlm_stock_status]` - Nur Lagerstatus
- `[wlm_shipping_method]` - Nur Versandart
- `[wlm_delivery_window]` - Nur Lieferfenster
- `[wlm_delivery_panel]` - Alias für wlm_delivery_info

### Beispiele

```
[wlm_delivery_info]
[wlm_delivery_info product_id="123"]
[wlm_delivery_info show="stock,delivery"]
[wlm_stock_status show_icon="no"]
[wlm_delivery_window format="short"]
```

**Detaillierte Dokumentation**: Siehe [SHORTCODES.md](SHORTCODES.md)

## Produkteinstellungen

Im Produkt-Editor (Tab "Lagerbestand"):

- **Lieferbar ab**: Datum im Format YYYY-MM-DD (wird automatisch berechnet, wenn nicht gesetzt)
- **Lieferzeit (Tage)**: Anzahl der Werktage bis zur Verfügbarkeit
- **Maximal sichtbarer Bestand**: Produktspezifische Obergrenze (optional)

Diese Felder sind auch für Varianten verfügbar.

## REST API

### Authentifizierung

Verwenden Sie WordPress Application Passwords oder Token mit der Berechtigung `edit_products`.

### Endpoints

#### Verfügbarkeitsdatum setzen
```
POST /wp-json/wlm/v1/products/{id}/availability
{
  "available_from": "2025-03-15"
}
```

#### Lieferzeit setzen
```
POST /wp-json/wlm/v1/products/{id}/lead-time
{
  "lead_time_days": 5
}
```

#### Batch-Update
```
POST /wp-json/wlm/v1/products/batch
{
  "products": [
    {
      "id": 123,
      "available_from": "2025-03-15",
      "lead_time_days": 5
    },
    {
      "id": 456,
      "lead_time_days": 3
    }
  ]
}
```

#### Lieferinformationen abrufen
```
GET /wp-json/wlm/v1/products/{id}/delivery-info
```

## Entwicklung

### Verzeichnisstruktur

```
woo-lieferzeiten-manager/
├── woo-lieferzeiten-manager.php    # Hauptdatei
├── includes/                        # PHP-Klassen
│   ├── class-wlm-core.php
│   ├── class-wlm-calculator.php
│   ├── class-wlm-shipping-methods.php
│   ├── class-wlm-shipping-method.php
│   ├── class-wlm-express.php
│   ├── class-wlm-surcharges.php
│   ├── class-wlm-product-fields.php
│   ├── class-wlm-rest-api.php
│   ├── class-wlm-frontend.php
│   ├── class-wlm-admin.php
│   └── class-wlm-blocks-integration.php
├── admin/                           # Backend-Dateien
│   ├── views/
│   │   ├── tab-times.php
│   │   ├── tab-shipping.php
│   │   └── tab-surcharges.php
│   ├── js/
│   │   └── admin.js
│   └── css/
│       └── admin.css
├── assets/                          # Frontend-Assets
│   ├── js/
│   │   ├── frontend.js
│   │   └── blocks-integration.js
│   └── css/
│       └── frontend.css
└── languages/                       # Übersetzungsdateien
```

### Hooks und Filter

Das Plugin verwendet Standard-WooCommerce-Hooks:

- `woocommerce_single_product_summary` - Produktseiten-Info-Panel
- `woocommerce_after_cart_item_name` - Warenkorb-Lagerstatus
- `woocommerce_after_shipping_rate` - Lieferfenster unter Versandarten
- `woocommerce_cart_calculate_fees` - Zuschläge hinzufügen
- `woocommerce_update_order_review_fragments` - AJAX-Fragments für Express

### AJAX-Endpoints

- `wlm_calc_product_window` - Produktlieferfenster berechnen
- `wlm_activate_express` - Express-Versand aktivieren
- `wlm_deactivate_express` - Express-Versand deaktivieren

## Kompatibilität

- **WordPress**: 6.0+
- **PHP**: 7.4+
- **WooCommerce**: 8.0+
- **WooCommerce Blocks**: Volle Unterstützung
- **HPOS (High-Performance Order Storage)**: Kompatibel
- **WooCommerce Germanized**: Kompatibel

## Cron-Jobs

Das Plugin registriert einen täglichen Cron-Job (`wlm_daily_availability_update`), der automatisch "Lieferbar ab"-Daten basierend auf Lead-Times aktualisiert.

## Performance

- **Caching**: Pro `(product_id, variation_id, qty, zone)` mit kurzer TTL
- **In-Request-Memoization**: Vermeidung redundanter Berechnungen
- **Minimalistisches JavaScript**: Kein Polling, nur Event-basierte Updates
- **Optimierte Fragments**: Nur relevante Daten bei WooCommerce-Updates

## Sicherheit

- **Capability-Checks**: `edit_products` für REST API
- **Nonce-Validierung**: Bei allen AJAX-Requests
- **Input-Sanitization**: Alle Benutzereingaben werden bereinigt
- **Output-Escaping**: Sichere Ausgabe im Frontend

## Support

Für Fragen, Feature-Requests oder Bug-Reports wenden Sie sich bitte an den Plugin-Autor.

## Lizenz

GPL v2 oder höher

## Changelog

### Version 1.3.6 🐞
- 🐞 **CRITICAL HOTFIX**: Parse Error behoben
  - PHP Parse Error in Zeile 177 und 54 von `class-wlm-shipping-methods.php`
  - Fehler: `syntax error, unexpected identifier "font"`
  - Ursache: Falsche Anführungszeichen in HTML-Attributen (`style='...'` statt `style="..."`)
  - Gelöst: Einfache und doppelte Anführungszeichen korrekt getauscht
  - Plugin funktioniert jetzt wieder!

### Version 1.3.5 🚀
- 🚀 **MAJOR CHANGE**: Versandarten laufen jetzt parallel zu WooCommerce (wie Conditional Shipping)
  - Versandarten werden **nicht mehr** in Versandzonen angezeigt
  - Keine manuelle Aktivierung in Zonen mehr nötig
  - Versandarten werden direkt über `woocommerce_package_rates` Filter hinzugefügt
  - Funktionieren global, unabhängig von Versandzonen
  - Alte WC_Shipping_Method Registrierung entfernt
- ✅ **BACKEND FIX**: Speichern funktioniert jetzt zuverlässig
  - Eigenes Formular mit manuellem Handling
  - Korrekter Nonce: `wlm-settings`
  - Redirect nach Speichern mit Success-Message
  - Daten werden korrekt in Optionen gespeichert
- 🎨 **FRONTEND FIX**: Lieferzeit wird wieder angezeigt
  - Lieferzeit im Label mit HTML-Filter
  - Format: "Lieferung: **Mi, 12.11. – Fr, 14.11.**"
  - Kleinere Schrift, graue Farbe
  - Filter `woocommerce_cart_shipping_method_full_label` erlaubt HTML

### Version 1.3.4 ✅
- ✅ **BACKEND FIX**: Speichern funktioniert jetzt endlich!
  - Kein eigenes Formular mehr in WooCommerce-Settings
  - WooCommerce stellt das `<form>` Tag bereit
  - Kein eigener Save-Button mehr (WooCommerce fügt automatisch hinzu)
  - Korrektes Nonce-Handling mit `woocommerce-settings`
  - Kein "Link abgelaufen" Fehler mehr
  - Nur noch **ein** Button (von WooCommerce)
- 🎨 **FRONTEND FIX**: Lieferzeit-Darstellung korrigiert
  - Lieferzeit nicht mehr im Label (wird escaped)
  - Lieferzeit wird über `woocommerce_after_shipping_rate` Hook angezeigt
  - Kleinere Schrift unter der Versandart
  - Format: "Lieferung: **Mi, 12.11. – Fr, 14.11.**"
  - Express-Option wird jetzt angezeigt (wenn aktiviert)
  - Inline-Styling für konsistente Darstellung

### Version 1.3.3 🐛
- 🐛 **CRITICAL FIX**: Speichern-Button jetzt innerhalb des Formulars
  - Button war außerhalb des `<form>` Tags und funktionierte nicht
  - Jetzt wird ein eigener Button innerhalb des Formulars gerendert
  - Speichern funktioniert jetzt korrekt
  - Button ist an der richtigen Position (nach den Tabs)

### Version 1.3.2 🐛
- 🐛 **FIX**: Doppelten "Speichern"-Button entfernt
  - WooCommerce-Einstellungsseite zeigt nur noch einen Speichern-Button
  - Verhindert Verwirrung welcher Button der richtige ist
  - submit_button() wird nur für standalone Seite angezeigt
- 📝 **Improvement**: Klarere Button-Struktur
  - WooCommerce verwendet seinen eigenen Speichern-Button
  - Formular funktioniert jetzt korrekt

### Version 1.3.1 🐛
- 🐛 **CRITICAL FIX**: Speicherproblem im Backend behoben
  - Formular-Action für WooCommerce-Einstellungen korrigiert
  - Speichern funktioniert jetzt korrekt in WooCommerce → Einstellungen → Versand → MEGA Versandmanager
  - Nonce-Prüfung hinzugefügt
  - Redirect nach Speichern implementiert
  - Success-Message wird angezeigt
- 📅 **FIX**: Lieferzeit-Anzeige im Cart/Checkout
  - Lieferzeit wird jetzt direkt im Versandarten-Label angezeigt
  - Format: "Paketversand<br>📅 Mi, 12.11. – Fr, 14.11."
  - Funktioniert in klassischem und Block-basiertem Cart/Checkout
  - Grauer Text, kleinere Schrift für bessere Lesbarkeit
- 🔧 **Improvement**: Tab-Navigation für WooCommerce-Einstellungen
  - Tabs verwenden wlm_tab Parameter
  - Korrekte URLs für WooCommerce-Kontext
  - Funktioniert auch standalone (falls direkt aufgerufen)

### Version 1.3.0 🚀
- 🎯 **MAJOR FEATURE**: WooCommerce-Integration als echte Versandmethoden
  - **Backend verschoben**: Jetzt unter WooCommerce → Einstellungen → Versand → MEGA Versandmanager
  - **Echte WC_Shipping_Methods**: Versandarten werden als native WooCommerce-Versandmethoden registriert
  - **Automatische Verfügbarkeit**: In allen Versandzonen automatisch verfügbar (wie Conditional Shipping)
  - **Checkout-Integration**: Kunden können Versandarten im Checkout wählen
- 📦 **Versandarten-Registrierung**:
  - Jede konfigurierte Versandart wird als `WC_Shipping_Method` Klasse erstellt
  - Dynamische Klassen-Generierung zur Laufzeit
  - Unterstützung für Versandzonen und Instance-Settings
- 💰 **Kostenberechnung**:
  - Pauschalkosten
  - Kosten nach Gewicht
  - Kosten nach Stückzahl
  - Automatische Berechnung im Checkout
- ✅ **Bedingungsprüfung**:
  - Gewichtsgrenzen
  - Warenkorbwert-Grenzen
  - Attribut-Bedingungen mit Logik-Operatoren
  - Kategorie-Bedingungen
- 📅 **Lieferzeit-Anzeige**:
  - Unter jeder Versandart im Checkout
  - Express-Option mit Kosten und Lieferzeit
  - Formatierte Lieferfenster
- 🔄 **Zonen-Integration**:
  - Automatisches Hinzufügen zu allen Zonen
  - "Rest of the World" Zone unterstützt
  - Später: Zonen-Auswahl pro Versandart konfigurierbar
- 🎨 **Backend-Verbesserungen**:
  - Tab-Name: "MEGA Versandmanager"
  - Saubere Integration in WooCommerce-Einstellungen
  - Scripts laden nur auf relevanten Seiten

### Version 1.2.0 🎉
- ✨ **MAJOR FEATURE**: Verbessertes Bedingungssystem für Attribut-Bedingungen
  - **Logik-Operatoren**: at least one, all, none, only
  - **Multi-Value-Support**: Mehrere Werte pro Bedingung möglich
  - **Tag-basierte UI**: Werte als Tags mit einfachem Hinzufügen/Entfernen
  - **Flexible Kombinationen**: Mehrere Attribute kombinierbar
- 🎯 **Logik-Operatoren erklärt**:
  - `at least one`: Mindestens einer der Werte muss zutreffen
  - `all`: Alle Werte müssen zutreffen
  - `none`: Keiner der Werte darf zutreffen
  - `only`: Nur die angegebenen Werte dürfen vorhanden sein
- 📦 **Beispiel**: Versandgruppe: [at least one] [Paketgut, Sperrgut]
- 🔄 **Backward Compatible**: Alte Bedingungen werden automatisch konvertiert
- 🎨 **Besseres Design**: Cleane UI mit Hover-Effekten und Boxen
- ⌨️ **UX-Verbesserungen**: 
  - Enter-Taste zum Hinzufügen von Werten
  - Fade-out Animation beim Entfernen
  - Visuelle Feedback-Effekte
- 📝 **Datenstruktur**: Neue flexible Struktur mit `logic` und `values` Arrays

### Version 1.1.3
- ⚡ **CRITICAL FIX**: Express-Hinweis wird jetzt im Frontend angezeigt
  - Express-Felder werden jetzt in get_applicable_shipping_method zurückgegeben
  - express_enabled, express_cost, express_cutoff, express_transit_min/max
- 🐛 **FIX**: Tooltip zeigt jetzt sauberen Text statt HTML-Code
  - strip_tags() für alle wc_price() Aufrufe
  - Kein `<span class="woocommerce-Price-amount">` mehr sichtbar
  - Tooltip ist jetzt lesbar: "3,00 € | bis 50,00 kg"
- 🎨 **Improvement**: Alle Preisformatierungen bereinigt

### Version 1.1.2
- 🐛 **CRITICAL FIX**: wlm_admin Variable-Problem behoben (war wlm_admin_params)
- ✅ **Fix**: Attribute werden jetzt in wp_localize_script geladen
- ⚡ **Fix**: Express-Hinweis im Frontend wird jetzt korrekt angezeigt
- 🔧 **Fix**: Express-Bedingung korrigiert (shipping_method statt method)
- 📊 **Improvement**: Alle WooCommerce-Attribute werden an JavaScript übergeben
- 📦 **Ready**: Vorbereitung für Conditional-Shipping-ähnliches Bedingungssystem in 1.2.0

### Version 1.1.1
- 🐛 **CRITICAL FIX**: JavaScript-Template für neue Versandarten vollständig repariert
- ✅ **Fix**: Alle erweiterten Felder jetzt verfügbar (Gewicht, Warenkorbwert, Attribute, Express)
- 🔧 **Fix**: Attribut-Bedingungen Template außerhalb der Schleife verschoben
- 📝 **Fix**: Doppeltes Template entfernt
- ⚙️ **Improvement**: Vollständiges Template mit allen 15+ Feldern

### Version 1.1.0
- ✨ **Feature**: Attributwert-Dropdown mit AJAX - automatische Werte-Vorschläge beim Auswählen von Attributen
- ⚙️ **Feature**: Konfigurierbarer Nicht-auf-Lager-Text im Backend (Zeiten-Tab)
- 💬 **Feature**: Tooltip mit Versandkosten und Bedingungen (Gewicht, Warenkorbwert)
- ⚡ **Feature**: Express-Info auf Produktdetailseite mit Lieferzeit und Kosten-Tooltip
- 🔧 **Improvement**: Erweiterte format_cost_info mit Gewichts- und Warenkorbgrenzen
- 🎯 **UX**: Datalist für Attributwerte verhindert Tippfehler
- 📦 **API**: AJAX-Endpoint für Attributwerte (wlm_get_attribute_values)
- 📊 **Calculation**: Express-Modus in calculate_product_window integriert

### Version 1.0.5
- 🐛 **Bugfix**: Attribut-Bedingungen werden jetzt korrekt gespeichert und geladen
- 🔄 **Kompatibilität**: Unterstützung für beide Datenformate (Array + String)
- ✅ **Fix**: Backend lädt `attribute_conditions` Array statt nur `required_attributes` String
- 🔧 **Fix**: Calculator prüft beide Formate für Attribut-Bedingungen
- 💾 **Backward Compatible**: Alte String-Bedingungen funktionieren weiterhin

### Version 1.0.4
- 🐛 **CRITICAL FIX**: Sanitize-Callback entfernt - verhindert Datenverlust beim Speichern
- ⚠️ **Warnung**: Versandarten müssen nach Update neu konfiguriert werden
- 📝 **Docs**: VERSANDARTEN-ANLEITUNG.md mit Schritt-für-Schritt-Anleitung
- ✅ **Validation**: WordPress Standard-Sanitization statt Custom-Callback
- 🔧 **Fix**: Alle Felder (Express, Attribute, Bedingungen) bleiben erhalten

### Version 1.0.3
- 🐛 **Bugfix**: "Paketdienst" Fallback entfernt - nur konfigurierte Versandarten werden angezeigt
- 🔍 **Debug**: Umfangreiches Debug-Logging für Versandarten-Auswahl
- ✅ **Validation**: Versandarten müssen aktiviert sein und Namen haben
- 📦 **Struktur**: Einheitliche Datenstruktur für Frontend-Anzeige
- 📊 **Info**: Cost-Info-Formatierung (Kostenlos, pro kg, pro Stück)
- 📝 **Docs**: DEBUG.md mit Troubleshooting-Anleitung hinzugefügt
- 🧩 **Blocks**: Verbesserte WooCommerce Blocks Integration

### Version 1.0.2
- ✨ Benutzerfreundliche Dropdown-Auswahl für Produktattribute und Taxonomien
- 🎯 Dynamisches Hinzufügen/Entfernen von Attribut-Bedingungen
- 🔍 Alle verfügbaren WooCommerce-Attribute werden automatisch geladen
- 📋 Unterstützung für Produktkategorien und Tags als Bedingungen
- 💾 Automatische Konvertierung zwischen altem und neuem Format
- 🎨 Verbesserte UX im Backend mit visuellen Bedingungszeilen

### Version 1.0.1
- ✨ Neue Shortcodes für flexible Produktseiten-Integration (Oxygen Builder)
- 🚀 Versandarten werden jetzt als echte WooCommerce-Versandmethoden registriert
- ⚙️ Erweiterte Bedingungen: Gewichtsgrenzen, Warenkorbsummen, Produktattribute, Kategorien
- 📝 Umfassende Shortcode-Dokumentation (SHORTCODES.md)
- 🐛 Bugfix: Versandarten-Namen werden jetzt korrekt angezeigt
- ✅ Versionierung korrekt implementiert

### Version 1.0.0
- Initiale Veröffentlichung
- Lieferzeitberechnung mit Cutoff-Zeit und Business Days
- Produktverfügbarkeitsfelder
- Versandarten-Management
- Express-Option
- Versandzuschläge
- REST API für ERP-Integration
- Block-Layout-Unterstützung für Cart und Checkout
- HPOS-Kompatibilität
