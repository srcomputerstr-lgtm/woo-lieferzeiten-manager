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
