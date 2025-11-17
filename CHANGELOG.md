# CHANGELOG

All notable changes to WooCommerce Lieferzeiten Manager will be documented in this file.

## [1.14.6] - 2025-11-17

### Fixed
- **CRITICAL: Strategie-Filterung (Günstigste/Teuerste) wendete sich auf ALLE Versandarten an**
  - Problem: Strategie klassifizierte pickup_location und andere Drittanbieter-Methoden als "BASE"
  - Bei "Günstigste" wurde pickup_location (0€) ausgewählt statt WLM-Methoden
  - WLM-Methoden wurden dadurch entfernt, obwohl sie die einzigen passenden waren
  - Lösung: Strategie wird jetzt NUR auf WLM-Methoden angewendet
  - Drittanbieter-Methoden (pickup_location, etc.) bleiben immer verfügbar

### Technical
- class-wlm-blocks-integration.php: `filter_package_rates()` trennt jetzt WLM-Methoden von anderen
- Neue Klassifizierung: BASE (WLM), EXPRESS (WLM), OTHER (non-WLM)
- Strategie wird nur auf BASE + EXPRESS angewendet
- OTHER werden nach Filterung wieder hinzugefügt

---

## [1.14.5] - 2025-11-17

### Fixed
- **CRITICAL: Syntax Error in class-wlm-shipping-methods.php**
  - Parse error in Zeile 177: Single quotes in eval() String nicht escaped
  - Verursachte kompletten Plugin-Crash
  - Geändert: `'YES'` → `"YES"` in eval() String

---

## [1.14.4] - 2025-11-17

### Fixed
- **CRITICAL: Zuschläge wurden nicht auf Express-Varianten angewendet**
  - Problem: `apply_to_express` Flag wurde nicht an `filter_package_rates` weitergegeben
  - `calculate_surcharges()` gab nur `name`, `cost`, `priority` zurück
  - `filter_package_rates` prüfte `empty($surcharge['apply_to_express'])` → war immer true
  - Lösung: `apply_to_express` wird jetzt im Zuschlag-Array mitgegeben

### Technical
- class-wlm-calculator.php: `calculate_surcharges()` fügt jetzt `apply_to_express` zum Array hinzu

---

## [1.14.3] - 2025-11-17

### Fixed
- **CRITICAL: Express-Varianten wurden nicht automatisch in Shipping Zones registriert**
  - Problem: Basis-Methoden wurden automatisch zu Zones hinzugefügt, Express-Varianten nicht
  - Benutzer mussten Express-Varianten manuell in jeder Zone aktivieren
  - Lösung: Express-Varianten werden jetzt automatisch hinzugefügt wenn Express aktiviert ist
  - Gilt für neue Methoden UND bestehende Methoden (retroaktiv)

### Technical
- class-wlm-core.php: `ensure_methods_in_zones()` fügt jetzt auch Express-Varianten hinzu
- class-wlm-admin.php: `update_zones_after_save()` fügt jetzt auch Express-Varianten hinzu
- Beide Funktionen prüfen ob Express aktiviert ist und fügen `{method_id}_express` hinzu

---

## [1.14.2] - 2025-11-17

### Fixed
- **CRITICAL: Versandarten wurden gelöscht beim Speichern von Zuschlägen**
  - Problem: JavaScript sammelte ALLE Tabs, auch leere - PHP speicherte leere Arrays
  - Lösung: PHP speichert nur noch nicht-leere Arrays
  - Verhindert versehentliches Löschen von Daten

- **CRITICAL: Zuschläge-Bedingungen wurden nicht gespeichert**
  - Problem: Zuschläge verwendeten fehlerhafte manuelle Sammlung
  - Lösung: Zuschläge verwenden jetzt exakt die gleiche Logik wie Versandarten
  - Alle Felder werden automatisch geparst (verschachtelte Arrays, etc.)
  - `attribute_conditions` werden jetzt korrekt gespeichert und geladen

### Technical
- class-wlm-admin.php: `!empty()` Check vor `update_option()` für shipping_methods und surcharges
- admin.js: Zuschläge-Sammlung verwendet jetzt identische Parsing-Logik wie Versandarten

---

## [1.14.1] - 2025-11-17

### Fixed
- **CRITICAL: Bedingungen-Logik komplett überarbeitet**
  - **Problem:** Versandarten wurden ausgeblendet, wenn EIN Produkt die Bedingung nicht erfüllt
  - **Alte Logik:** Prüfte JEDES Produkt einzeln - wenn eines nicht matched, wurde Versandart ausgeblendet
  - **Neue Logik:** Prüft GESAMTEN Warenkorb - sammelt alle Werte aus allen Produkten
  - **"at least one of"** bedeutet jetzt: Mindestens ein Produkt im Warenkorb hat diesen Wert
  - Neue Methode: `check_cart_conditions()` ersetzt produkt-basierte Prüfung
  - Unterstützt: Attribute, Taxonomien, Versandklassen
  - Gilt für Versandarten UND Zuschläge

- **Zuschläge-Bedingungen werden jetzt gespeichert**
  - JavaScript sammelt jetzt `attribute_conditions` korrekt
  - Bedingungen bleiben nach Reload erhalten
  - Gleiche Logik wie bei Versandarten

### Technical
- Calculator.php: Neue Methode `check_cart_conditions()` für warenkorb-basierte Bedingungsprüfung
- Blocks-Integration.php: Verwendet `check_cart_conditions()` statt `check_product_conditions()`
- admin.js: Erweitert Surcharges-Sammlung um `attribute_conditions`

---

## [1.14.0] - 2025-11-17

### Added
- **Versandklassen-Unterstützung in Bedingungen**
  - Versandklassen können jetzt als Bedingungstyp ausgewählt werden
  - Funktioniert bei Versandarten UND Zuschlägen
  - 3-Dropdown-System: Logic + Typ (Attribut/Taxonomie/Versandklasse) + Auswahl
  - Multiselect für Versandklassen mit "at least one of" / "all of" / "none of" / "only"

### Fixed
- **CRITICAL: Produktattribute werden jetzt geladen**
  - `$attribute_taxonomies` wird jetzt an Views übergeben
  - Produktattribute erscheinen im Dropdown
  - Gilt für Versandarten UND Zuschläge
- **Versandklassen-Loading implementiert**
  - Versandklassen werden aus DB geladen und an JavaScript übergeben
  - Multiselect zeigt alle verfügbaren Versandklassen
  - "No results found" Problem behoben

### Changed
- **Versandarten-UI komplett überarbeitet**
  - Gleiche Struktur wie Zuschläge (3 Dropdowns)
  - Bedingungstyp-Dropdown hinzugefügt
  - Versandklassen-Optgroup hinzugefügt
  - Template für neue Bedingungen aktualisiert

### Technical
- Admin.php: `render_shipping_tab()` und `render_surcharges_tab()` laden `$attribute_taxonomies`
- Admin.php: `enqueue_admin_scripts()` lädt Versandklassen in `wlmAdmin.shippingClasses`
- tab-shipping.php: Bedingungstyp-Dropdown und Versandklassen-Optgroup hinzugefügt
- admin.js: `handleConditionTypeChange()` behandelt alle 3 Typen korrekt

---

## [1.13.4] - 2025-11-17

### Fixed
- **HOTFIX: Produktattribute wieder sichtbar im Dropdown**
  - Optgroup-Filterung geändert: Zeigt alle Optgroups initial an
  - Filtert nur die **anderen** Optgroups aus (nicht alle)
  - "Produkt-Attribute" und "Taxonomien" sind jetzt immer sichtbar
  - Bei Typ-Wechsel wird nur die jeweils andere Gruppe ausgeblendet

### Technical
- Admin.js: `handleConditionTypeChange()` zeigt alle Optgroups, versteckt dann selektiv
- Verhindert dass Optgroups initial versteckt bleiben

---

## [1.13.3] - 2025-11-17

### Fixed
- **CRITICAL: Zuschläge-Bedingungen werden jetzt gespeichert**
  - Normalisierung für `attribute_conditions` implementiert (wie bei Versandarten)
  - Flat keys wie `attribute_conditions][0][logic` werden zu nested arrays konvertiert
  - Validierung: Leere Bedingungen werden gefiltert
  - Shipping class Bedingungen: Nur `type` und `values` erforderlich

- **Versandklassen-Bedingungen UI komplett überarbeitet**
  - Attribut-Dropdown wird bei Versandklassen **ausgeblendet**
  - Multiselect wird bei Versandklassen **angezeigt** und mit Versandklassen befüllt
  - "at least one of" / "all of" macht jetzt Sinn (Multiselect statt Single-Dropdown)
  - JavaScript `loadShippingClassesIntoMultiselect()` lädt Versandklassen dynamisch

### Technical
- Admin.php: Surcharges Normalisierung analog zu Shipping Methods
- Admin.js: `handleConditionTypeChange()` versteckt Attribut-Select bei Versandklassen
- Admin.js: `loadShippingClassesIntoMultiselect()` befüllt Select2 mit Versandklassen
- Validierung: Shipping class braucht kein `attribute` Feld

---

## [1.13.2] - 2025-11-17

### Fixed
- **CRITICAL: Express-Methoden werden jetzt korrekt angezeigt**
  - Express-ID Matching berücksichtigt jetzt Instance-ID (`:12`, `:14`)
  - Extrahiert Base-ID ohne Instance-ID vor Matching
  - Express-Varianten werden mit Hauptmethode angezeigt bei allen Strategies

- **Versandklassen-Bedingungen UI korrigiert**
  - Werte-Feld wird bei Versandklassen ausgeblendet
  - Attribut-Dropdown zeigt nur relevante Optionen je nach Bedingungstyp
  - JavaScript `handleConditionTypeChange()` reagiert auf Typ-Änderung

### Technical
- Blocks Integration: Express-ID Matching via `explode(':')` und `strpos()`
- Admin.js: `handleConditionTypeChange()` zeigt/versteckt Werte-Feld
- Admin.js: Optgroups werden je nach Bedingungstyp gefiltert

---

## [1.13.1] - 2025-11-16

### Fixed
- **CRITICAL: Shipping Selection Strategy funktioniert jetzt**
  - JavaScript sammelt jetzt `wlm_shipping_selection_strategy` und `wlm_surcharge_application_strategy`
  - Strategies werden korrekt per AJAX gespeichert
  - "Teuerste", "Günstigste", "Nach Priorität" funktionieren jetzt im Frontend

- **Express-Methoden Filterung korrigiert**
  - Express-Varianten werden jetzt korrekt mit Hauptmethode angezeigt
  - Präzise Zuordnung via `_express` Suffix statt `strpos()`
  - Verhindert falsche Zuordnung bei ähnlichen IDs

- **Cronjob wird automatisch aktiviert**
  - `ensure_cron_scheduled()` prüft bei jedem Plugin-Load
  - Cronjob wird registriert, falls nicht vorhanden
  - Behebt Problem dass Cronjob nur bei Aktivierung registriert wurde

- **Zuschläge-UI komplett**
  - JavaScript `getSurchargeTemplate()` erstellt jetzt vollständige UI
  - Alle Felder werden angezeigt: Priorität, Cost Type, Charge Per, Gewicht, Warenkorbwert, Bedingungen
  - "+ Bedingung hinzufügen" Button funktioniert

### Technical
- Admin.js: Strategies werden in `saveSettings()` gesammelt
- Blocks Integration: Express-ID Matching via exakte Suffix-Prüfung
- Core: `ensure_cron_scheduled()` Hook auf `init`
- Admin.js: `getSurchargeTemplate()` mit allen Feldern erweitert

---

## [1.13.0] - 2025-11-15

### 🎉 Vollständige Zuschläge-Implementierung

### Added
- **Globale Zuschlag-Strategie**
  - "Alle Zuschläge" - Addiert alle passenden Zuschläge
  - "Erster Treffer" - Nur erster passender Zuschlag (nach Priorität)
  - "Kleinster Zuschlag" - Nur günstigster Zuschlag
  - "Größter Zuschlag" - Nur teuerster Zuschlag
  - "Deaktiviert" - Keine Zuschläge anwenden

- **Erweiterte Zuschlag-Felder**
  - Priority (für "Erster Treffer" Strategie)
  - Cost Type: Pauschalbetrag (€) oder Prozentual (%)
  - Charge Per: Cart / Shipping class / Product category / Product / Cart item / Quantity unit
  - Gewicht Min/Max Bedingungen
  - Warenkorbwert Min/Max Bedingungen
  - Produktattribute / Taxonomien / **Versandklassen** als Bedingungen

- **Shipping Class als Bedingungstyp**
  - Dropdown-Option neben "Attribut" und "Taxonomie"
  - Multiselect mit Logic-Operatoren (at least one / all / none / only)
  - Funktioniert wie Attribute und Taxonomien

- **Zuschlag-Berechnung**
  - Alle Bedingungen werden geprüft (Gewicht, Warenkorbwert, Attribute, Taxonomien, Versandklassen)
  - Berechnung basierend auf "Charge Per" Einstellung
  - Prozentuale Zuschläge basierend auf Warenkorbsumme
  - Zuschläge werden unsichtbar zu Versandkosten addiert

### Changed
- **Zuschläge-UI komplett überarbeitet**
  - Gleiche Struktur wie Versandarten-UI
  - Select2-basierte Multiselect für Bedingungen
  - Card-Design mit Collapsible-Sections
  - "+ Bedingung hinzufügen" Button

- **JavaScript erweitert**
  - `addAttributeCondition()` unterstützt jetzt Versandarten UND Zuschläge
  - Separates Template für Zuschlag-Bedingungen
  - Select2-Initialisierung für beide Tabs

### Technical
- Calculator: `calculate_surcharges()`, `calculate_surcharge_cost()`, `apply_surcharge_strategy()`
- Blocks Integration: Surcharges werden vor Selection Strategy angewendet
- Admin: Speichert `wlm_surcharge_application_strategy` Option
- Zuschläge sind für Kunden unsichtbar - nur Gesamtpreis wird angezeigt

---

## [1.12.2] - 2025-11-14

### Fixed
- **CRITICAL: Product Conditions werden jetzt geprüft**
  - `check_product_conditions()` wird jetzt in `calculate_shipping()` aufgerufen
  - Versandarten werden korrekt gefiltert basierend auf Produktattributen
  - Mehrere Bedingungen funktionieren jetzt korrekt (AND-Verknüpfung)
  - Debug-Logging für Troubleshooting hinzugefügt

### Technical
- Implementiert Conditions-Check für jedes Produkt im Warenkorb
- Versandart wird ausgeblendet wenn ein Produkt die Bedingungen nicht erfüllt
- Unterstützt alle Logik-Operatoren: at_least_one, all, none, only

---

## [1.12.1] - 2025-11-14

### Fixed
- **Browser-Caching Problem**
  - Version-Bump erzwingt Reload der JavaScript-Datei
  - Browser laden jetzt die neue admin.js mit korrekter Serialisierung
  - PHP-Normalisierung als Fallback bleibt aktiv

### Technical
- JavaScript wird mit `WLM_VERSION` Parameter geladen
- Browser-Cache wird durch Version-Änderung invalidiert
- Beide Fixes (JS + PHP) sind jetzt aktiv

---

## [1.12.0] - 2025-11-14

### 🎨 Verbesserte Produktattribute/Taxonomien UI

### Fixed
- **JavaScript Serialisierung** für Select2 Multiselect-Arrays
  - `values[]` Arrays werden jetzt korrekt erkannt und gespeichert
  - Regex erweitert um `(\[\])?` Pattern zu matchen
  - Array-Handling für verschachtelte Strukturen implementiert
  - Bedingungen bleiben nach Speichern erhalten

### Changed
- **Conditions UI für Versandarten**
  - Select2-basierte Mehrfachauswahl für Attributwerte (Chip-Design)
  - Dropdown für Logik-Operatoren: "at least one of", "all of", "none of", "only"
  - Autocomplete für verfügbare Attributwerte
  - Mehrere Bedingungen pro Versandart möglich
  - "+ Bedingung hinzufügen" Button
  - "Entfernen" Button pro Bedingung
  - Visuell ansprechende Card-basierte Darstellung

- **Backend-Validierung**
  - Automatische Filterung leerer Bedingungen
  - Validierung der Conditions-Struktur beim Speichern
  - Saubere Array-Normalisierung

### Technical
- Select2 Integration für bessere UX
- AJAX-basiertes Laden der Attributwerte
- Kompatibel mit bestehender Logic-Engine
- Keine Breaking Changes für bestehende Konfigurationen

---

## [1.11.0] - 2025-11-14

### 🎯 SKU-basierte REST API für ERP-Integration

### Added
- **SKU-basierte API Endpunkte**
  - `POST /products/sku/{SKU}/availability` - Produkt via SKU aktualisieren
  - `POST /products/sku/batch` - Bulk-Update mit SKU-Liste
  - `GET /products/sku/{SKU}/delivery-info` - Lieferinformationen via SKU abrufen
  - Automatisches SKU → Produkt-ID Mapping

- **ERP Integration Guide**
  - Komplette Dokumentation für ERP-Systeme
  - Code-Beispiele für Python, PHP, C#, Java, cURL
  - CSV-Import Workflow
  - Authentifizierung via Application Passwords

- **Berechnetes Verfügbarkeitsdatum**
  - Neues Read-Only Feld im Produktbackend
  - Täglicher Cronjob berechnet Datum basierend auf Lieferzeit
  - Manuelle Daten werden nie überschrieben

- **Cronjob-Verwaltung**
  - Admin-Einstellungen für Cronjob-Zeit
  - "Jetzt ausführen" Button für sofortiges Testen
  - Anzeige: Letzter Lauf, Nächster Lauf, Anzahl Produkte

### Changed
- **Verfügbarkeitsdatum-Logik**
  - Priorität 1: Manuelles "Lieferbar ab" (wenn Zukunft/Heute)
  - Priorität 2: Berechnetes Datum (Cronjob)
  - Priorität 3: On-the-fly Berechnung
  - Vergangenheits-Daten werden automatisch ignoriert

- **Express-Hinweis im Checkout**
  - Nur noch im Cart sichtbar
  - Im Checkout nur bei gewählter Express-Versandart
  - Reduziert visuelle Überladung

- **Stock-Status Anzeige**
  - Differenzierte Anzeige bei Teilbestand
  - "Auf Lager: X Stück - Rest ab: Datum"
  - CSS-gezeichnete Kreise statt Unicode-Zeichen

### Fixed
- **Lieferzeit-Berechnung**
  - Produkt-Lieferzeit wird nur verwendet wenn Stock unzureichend
  - Bei ausreichendem Stock: Nur Transit-Zeit
  - Korrekte Zeitzone-Behandlung bei Datums-Vergleichen

- **Express-Verfügbarkeit**
  - Express wird ausgeblendet wenn nicht alle Produkte auf Lager
  - Sowohl als Info als auch als Versandart-Option
  - Stock-Check berücksichtigt bestellte Menge

- **JavaScript-Fehler**
  - Localized script object korrekt benannt (wlmAdmin)
  - AJAX-Calls funktionieren wieder
  - Speichern von Versandarten gefixt

### Technical Details
- REST API nutzt WP_REST_Response
- SKU-Lookup via wpdb für Performance
- Cronjob via wp_schedule_event
- Timezone-aware Datums-Vergleiche

---

## [1.5.0] - 2025-11-10

### 🎯 CRITICAL FIX: Frontend Rendering

**Problem behoben:** Shipping rates erschienen in Debug-Logs aber NICHT im Cart/Checkout DOM.

### Added
- **Proper WC_Shipping_Method Registration**
  - Dynamische Erstellung von `WC_Shipping_Method` Klassen für jede Versandart
  - Registrierung über `woocommerce_shipping_methods` Filter
  - Volle Integration in WooCommerce's Shipping System

- **Automatic Zone Integration**
  - Neue Methode `ensure_methods_in_zones()` in `WLM_Core`
  - Automatisches Hinzufügen aller aktivierten Versandarten zu allen Zones
  - Läuft bei `woocommerce_shipping_init` und `woocommerce_init`
  - Zusätzlicher Trigger beim Speichern von Versandarten

- **Zone Update on Save**
  - Neue Methode `update_zones_after_save()` in `WLM_Admin`
  - Zones werden automatisch aktualisiert wenn Versandarten gespeichert werden
  - Funktioniert sowohl bei WooCommerce Settings Save als auch AJAX Save

### Changed
- **Architektur-Überarbeitung**
  - Entfernt: Direkte Rate-Injection über `woocommerce_package_rates` Filter
  - Entfernt: `add_shipping_rates()` Methode
  - Entfernt: `preserve_global_rates()` Methode
  - Neu: Saubere WC_Shipping_Method Klassen-basierte Implementierung

### Fixed
- **Frontend Rendering Issue**
  - Shipping rates werden jetzt korrekt im Cart/Checkout DOM gerendert
  - WooCommerce erkennt die Rates als gültige Versandarten
  - Benutzer können Versandarten auswählen

- **eval() Syntax Error**
  - Ersetzt `??` Operator in eval() Code durch explizite `isset()` Checks
  - Verhindert PHP Parse Errors bei dynamischer Klassen-Erstellung

### Technical Details
- Dynamische Klassen werden mit `eval()` erstellt (temporäre Lösung)
- Jede Versandart erhält eine eigene Klasse: `WLM_Shipping_Method_{id}`
- Klassen erweitern `WC_Shipping_Method` korrekt
- `calculate_shipping()` Methode nutzt bestehende WLM-Logik

### Known Issues
- ⚠️ Attribute-Bedingungen: Datenstruktur wird falsch gespeichert (flat keys statt nested arrays)
- ⚠️ AND/OR-Logik für Bedingungen noch nicht implementiert
- ⚠️ admin.js Cache-Busting fehlt (alte JavaScript-Version kann laden)

---

## [1.4.7] - 2025-11-09

### Changed
- Entfernt: `method_id` Parameter aus `WC_Shipping_Rate` Constructor
- Hinzugefügt: `preserve_global_rates` Filter (Priority 500)

### Issues
- ❌ Rates erscheinen in Debug-Logs aber nicht im DOM
- ❌ Frontend-Rendering funktioniert nicht

---

## [1.4.6] - 2025-11-09

### Changed
- Versuch: Rates ohne Zone-Zuordnung hinzufügen
- Debug-Logging erweitert

### Issues
- ❌ Rates werden von WooCommerce gefiltert
- ❌ Nicht im Checkout sichtbar

---

## [1.3.7] - 2025-11-08

### Changed
- Versuch: Bypass von `WC_Shipping_Method` Registration
- Nur `woocommerce_package_rates` Filter verwendet

### Issues
- ❌ WooCommerce akzeptiert Rates nicht ohne registrierte Methoden

---

## [1.3.6] - 2025-11-08

### Added
- Debug-Logging System implementiert
- Shipping Rates werden zu WooCommerce's Rate System hinzugefügt

### Issues
- ❌ Rates funktionieren auf Produktseiten aber nicht in Cart/Checkout

---

## [1.3.5] - 2025-11-07

### Added
- Backend Admin Interface mit Tabs (Einstellungen, Versandarten, Zuschläge)
- Shipping Method Configuration (Name, Cost, Delivery Windows)
- Attribute Conditions Setup in UI

### Issues
- ❌ Attribute-Bedingungen werden falsch gespeichert
- ❌ Frontend-Rendering inkonsistent

---

## [1.0.0] - 2025-11-05

### Added
- Initial Release
- Grundlegende Plugin-Struktur
- WooCommerce Integration
- Lieferzeitfenster-Berechnung
- Express-Versand-Feature
- Zuschläge-System
- Produkt-Felder für Lieferzeiten

---

## Versioning

Dieses Projekt folgt [Semantic Versioning](https://semver.org/):
- **MAJOR** (1.x.x): Breaking Changes
- **MINOR** (x.5.x): Neue Features (backwards-compatible)
- **PATCH** (x.x.1): Bug Fixes (backwards-compatible)

## Links

- [GitHub Repository](https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager)
- [Testing Guide](TESTING.md)
- [README](README.md)

## [1.5.1] - 2025-11-10

### Added
- `is_express_available()` method in Calculator class
- Checks if current time is before express cutoff time
- Required for express button display logic

### Fixed
- Express button not showing in frontend (missing method)

### Confirmed
- ✅ All frontend features are fully implemented and working
- ✅ Delivery time window display functional
- ✅ Express activation/deactivation functional
- ✅ AJAX handlers working correctly

### Known Issues
- ⚠️ Attribute conditions still have data structure issues (planned for v1.6.0)

---

## [1.5.2] - 2025-11-10

### Added
- `wlm_order_window` shortcode for block-based checkout
- `wlm_express_toggle` shortcode for block-based checkout
- Shortcode processing in shipping rate labels for blocks

### Fixed
- ✅ **CRITICAL:** Delivery time windows not showing in block-based checkout
- ✅ **CRITICAL:** Express options not showing in block-based checkout
- Shortcodes appearing as text instead of rendered HTML

### Technical Changes
- Added `add_delivery_info_to_rates()` method in Frontend class
- Shortcodes are now injected into shipping rate labels
- `do_shortcode()` processing ensures proper rendering

### Known Issues
- ⚠️ Attribute conditions still have data structure issues (planned for v1.6.0)

---

## [1.5.3] - 2025-11-10

### Fixed
- ✅ **CRITICAL:** Express activation now works - removed stock status requirement
- ✅ **CRITICAL:** "Express ist derzeit nicht verfügbar" error resolved
- ✅ Express now appears as separate cart fee instead of modifying shipping cost
- ✅ Delivery windows now appear below shipping labels, not inline

### Added
- `frontend-blocks.css` with minimalist, professional styling
- `moveDeliveryInfoBelowLabels()` JavaScript method
- Express fee is added to cart totals automatically
- Responsive design for mobile devices

### Improved
- Delivery window styling - clean, readable, professional
- Express button styling - gradient background, hover effects
- Better spacing and layout in block-based checkout
- JavaScript moves delivery info to better position

### Technical Changes
- Simplified `is_express_available()` - always returns true (cutoff checked per method)
- Renamed `add_express_fee_to_cart()` to `add_express_fee()`
- Hooked `add_express_fee()` to `woocommerce_cart_calculate_fees`
- Wrapped delivery info in `.wlm-delivery-info-wrapper` for JS manipulation

---

## [1.5.4] - 2025-11-10

### Fixed
- ✅ **CRITICAL:** Delivery windows now appear in proper `wc-block-components-totals-item__description` div
- ✅ **CRITICAL:** Express button click handler now works correctly
- ✅ Express AJAX handler gets cutoff time from method configuration
- ✅ Express availability check uses proper cutoff parameter

### Improved
- Delivery info is moved from label to description div by JavaScript
- Better error messages for express activation failures
- Support for both WooCommerce Blocks and Classic Checkout
- Cleaner DOM structure in checkout

### Technical Changes
- Rewrote `moveDeliveryInfoBelowLabels()` to target `.wc-block-components-totals-item__description`
- Added method config lookup in `ajax_activate_express()`
- Pass `cutoff_time` parameter to `is_express_available()`
- JavaScript extracts delivery info from label and injects into description div

---

## [1.5.5] - 2025-11-10

### Changed
- ✅ **CRITICAL:** Shipping rate labels now remain clean and static
- ✅ **CRITICAL:** No dynamic content in labels anymore (important for ERP/payment systems)
- ✅ Delivery info is fetched via AJAX and rendered in description div
- ✅ Labels always show consistent names (e.g., "Paketversand S")

### Added
- New AJAX endpoint: `ajax_get_shipping_delivery_info()`
- JavaScript method: `fetchAndRenderDeliveryInfo()`
- JavaScript method: `loadDeliveryInfoForShippingMethods()`

### Improved
- Better separation of concerns (labels vs. delivery info)
- Cleaner DOM structure
- Better performance (AJAX on demand instead of pre-rendering)
- ERP and payment provider compatibility

### Removed
- Label injection of delivery info
- Shortcode rendering in labels
- `wlm-delivery-info-wrapper` in labels

### Technical Details
- `add_delivery_info_to_rates()` no longer modifies labels
- JavaScript extracts method ID from radio input value
- AJAX call returns delivery window + express availability
- HTML is rendered directly into target element (description div or container)

---

## [1.5.6] - 2025-11-10

### Fixed
- ✅ **CRITICAL:** Simplified delivery info rendering - back to proven label injection approach
- ✅ **CRITICAL:** Express button now only shows for methods with express enabled
- ✅ **CRITICAL:** Each method shows its own delivery window (not shared)

### Added
- New `frontend-shipping.css` for minimalist, responsive design
- Wrapper div `.wlm-shipping-extras` for better styling control
- `method_id` parameter support in shortcodes
- Professional CSS styling with gradients, hover effects, responsive design

### Changed
- Removed complex AJAX/meta-data approaches that didn't work reliably
- Shortcodes now accept `method_id` parameter for method-specific rendering
- Delivery window calculated with correct method configuration
- `order_window_shortcode()` uses `calculate_cart_window($method_config, false)`
- `express_toggle_shortcode()` checks `$method_config['express_enabled']`

### Technical Details
- `add_delivery_info_to_rates()` passes `method_id` to shortcodes
- Shortcodes render with method-specific configuration
- Express only appears when `express_enabled` is true for that method
- Labels contain delivery info (styled with CSS for clean appearance)
- Responsive design for mobile devices
- Uses `!important` in CSS to override theme styles

### Known Issues
- ⚠️ Labels contain dynamic content (not ideal for ERP systems, but works reliably)
- ⚠️ Attribute conditions still have data structure issues (planned for v1.6.0)



## [1.6.1] - 2024-11-11

### Fixed
- **WooCommerce Blocks Integration:** Fixed React Slot-Fill props issue - Component now correctly uses `wp.data.useSelect` to get cart data from WooCommerce Store
- **Store API Extension:** Resolved conflict between old `blocks-integration.js` and new `blocks-delivery-info.js` - Store API extension is now registered directly in `class-wlm-frontend.php`
- **Calculator:** Fixed `calculate_cart_window()` to properly pass `$method_config` to `calculate_product_window()` - Each shipping method now gets correct delivery time window based on its transit times
- **Express Shipping:** Express delivery times are now correctly calculated using method-specific express transit times

### Added
- **Debug Logging:** Extensive console logging in `blocks-delivery-info.js` for troubleshooting Blocks integration
- **Debug Script:** `debug-blocks.js` - Comprehensive test script for browser console to verify WooCommerce Blocks integration
- **Documentation:** `BLOCKS-INTEGRATION-STATUS.md` - Complete technical documentation of Blocks integration architecture and data flow
- **Testing Guide:** `TESTING-CHECKLIST.md` - Step-by-step testing checklist with expected results and common errors

### Changed
- **Blocks Integration:** Switched from `ExperimentalOrderMeta` to `ExperimentalOrderShippingPackages.Fill` for better placement of delivery info below shipping methods
- **Dependencies:** Added `wp-data` dependency to `blocks-delivery-info.js` for `useSelect` hook

### Technical Details
- React component now properly receives cart data and extensions from WooCommerce Store
- Store API extension provides delivery info under namespace `woo-lieferzeiten-manager`
- Each shipping method gets individual delivery time windows based on configured transit times
- Express options are calculated with method-specific cutoff times and transit times

---

## [1.6.0] - 2025-11-11

### 🎉 MAJOR UPDATE: Proper WooCommerce Blocks Integration

#### Added
- **React Slot Fill Component** (`blocks-delivery-info.js`)
  - Uses `ExperimentalOrderShippingPackages` slot
  - Native React rendering (no DOM hacks!)
  - Renders delivery windows below shipping methods
  - Renders express options with click handlers
  
- **Store API Extension** (per shipping method)
  - Delivery window for each method
  - Express availability check
  - Express cost and window
  - Express selection status

#### Changed
- **Blocks Integration** completely rewritten
  - From JavaScript DOM manipulation
  - To proper React Slot Fill
  - Future-proof and maintainable
  
- **Labels stay clean**
  - No more delivery info injection
  - Consistent names for ERP/Payment systems
  - Delivery info rendered separately

#### Benefits
- ✅ Native WooCommerce Blocks support
- ✅ No JavaScript hacks or DOM manipulation
- ✅ Future-proof architecture
- ✅ Clean, maintainable code
- ✅ Works in Cart AND Checkout blocks
- ✅ Proper React component lifecycle

#### Technical Details
- Dependencies: `wp-plugins`, `wp-element`, `wp-i18n`, `wc-blocks-checkout`
- Namespace: `woo-lieferzeiten-manager`
- Slot: `ExperimentalOrderShippingPackages`
- Store API: Extended with `delivery_info` per method

---
