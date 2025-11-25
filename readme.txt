=== Woo Lieferzeiten Manager ===
Contributors: seoparden
Tags: woocommerce, shipping, delivery, lead time, express shipping
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.35.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professionelles Lieferzeitenmanagement für WooCommerce mit Express-Optionen, dynamischen Versandarten und ERP-Integration.

== Description ==

**Woo Lieferzeiten Manager** ist ein umfassendes Plugin zur Verwaltung von Lieferzeiten, Versandarten und Express-Optionen in WooCommerce. Perfekt für Shops die präzise Lieferzeitangaben benötigen und mit ERP-Systemen integrieren möchten.

= Hauptfunktionen =

* **Dynamische Lieferzeitberechnung** - Automatische Berechnung basierend auf Bearbeitungszeit, Transitzeit und Werktagen
* **Express-Versandoptionen** - Biete schnellere Lieferung gegen Aufpreis an
* **Produktspezifische Lieferzeiten** - Individuelle Lieferzeiten pro Produkt
* **Werktage & Feiertage** - Konfigurierbare Geschäftstage und Feiertage
* **Cut-Off Zeiten** - Bestellungen nach Cut-Off werden am nächsten Werktag bearbeitet
* **REST API** - Vollständige API für ERP-Integration
* **Block-Editor Support** - Volle Kompatibilität mit WooCommerce Blocks
* **HPOS Compatible** - Unterstützt High-Performance Order Storage

= Für wen ist dieses Plugin? =

* **E-Commerce Shops** mit komplexen Lieferzeitanforderungen
* **B2B Händler** die präzise Lieferzusagen benötigen
* **Shops mit ERP-Systemen** die Lieferzeiten automatisieren möchten
* **Logistik-orientierte Shops** mit verschiedenen Versandarten

= REST API Endpoints =

Das Plugin bietet umfangreiche REST API Endpoints für die Integration mit ERP-Systemen:

* `GET /wlm/v1/orders/{id}/ship-by-date` - Versanddatum abrufen
* `GET /wlm/v1/orders/{id}/earliest-delivery` - Früheste Zustellung
* `GET /wlm/v1/orders/{id}/latest-delivery` - Späteste Zustellung
* `GET /wlm/v1/orders/ship-by/{date}` - Bestellungen nach Versanddatum filtern
* `POST /wlm/v1/products/sku/{sku}/availability` - Produktverfügbarkeit setzen

Vollständige API-Dokumentation im `docs/` Verzeichnis.

= Technische Features =

* Kompatibel mit WooCommerce Blocks (Cart & Checkout)
* HPOS (High-Performance Order Storage) Support
* Session-basierte Lieferzeitberechnung
* Automatische Order Meta Speicherung
* Debug-Modus für Entwickler
* Mehrsprachig (i18n ready)

== Installation ==

1. Lade das Plugin hoch oder installiere es über das WordPress Plugin-Verzeichnis
2. Aktiviere das Plugin über das 'Plugins' Menü in WordPress
3. Gehe zu WooCommerce → Einstellungen → Versand → MEGA Versandmanager
4. Konfiguriere deine Werktage, Cut-Off Zeiten und Bearbeitungszeiten
5. Erstelle deine Versandarten mit Transittzeiten

== Frequently Asked Questions ==

= Ist das Plugin mit WooCommerce Blocks kompatibel? =

Ja! Das Plugin unterstützt vollständig den neuen Block-basierten Checkout von WooCommerce.

= Kann ich das Plugin mit meinem ERP-System integrieren? =

Ja! Das Plugin bietet umfangreiche REST API Endpoints für die Integration. Siehe `docs/REST-API-DOCUMENTATION.md` für Details.

= Werden Feiertage berücksichtigt? =

Ja! Du kannst beliebige Feiertage konfigurieren die bei der Lieferzeitberechnung übersprungen werden.

= Kann ich Express-Versand anbieten? =

Ja! Jede Versandart kann eine Express-Option mit eigener Cut-Off Zeit und Transitzeit haben.

= Ist das Plugin HPOS-kompatibel? =

Ja! Das Plugin ist vollständig kompatibel mit WooCommerce High-Performance Order Storage.

== Screenshots ==

1. Lieferzeitberechnung im Checkout
2. Admin-Einstellungen für Werktage und Cut-Off Zeiten
3. Versandarten-Konfiguration mit Express-Optionen
4. Lieferzeitanzeige auf der Thank-You-Page
5. Backend Order-Details mit Lieferzeitraum

== Changelog ==

= 1.35.0 - 2025-11-25 =
* Fix: Hook-Registrierung nach WooCommerce-Init verschoben (Status-Wechsel funktioniert jetzt)
* Cleanup: Debug-Code entfernt

= 1.34.2 - 2025-11-24 =
* Fix: Ship-by Date wird IMMER neu berechnet bei Status-Wechsel zu "In Bearbeitung"
* Fix: Auch manuelle Status-Änderungen im Backend triggern Neuberechnung

= 1.34.1 - 2025-11-24 =
* Fix: Ship-by Date wird jetzt immer gespeichert (auch bei pending orders)
* Fix: Backend zeigt Ship-by Date wieder an

= 1.34.0 - 2025-11-24 =
* Fix: Ship-by Date wird jetzt korrekt ab Zahlungseingang berechnet (nicht ab Bestelldatum)
* Neu: Automatische Neuberechnung bei Status-Wechsel zu "In Bearbeitung"
* Neu: Pending-Order Handling - Ship-by Date erst nach Zahlungseingang
* Fix: Lieferzeitraum wird bei Zahlungseingang neu berechnet
* Verbesserung: Unterstützt Instant Payment (PayPal, Stripe) und manuelle Zahlung

= 1.33.3 - 2025-11-24 =
* Fix: Germanized API Call entfernt (verursachte Fatal Error)
* Fix: Sichere Datenbank-Abfrage mit automatischer Tabellen-Erkennung
* Fix: Unterstützt alle möglichen Spalten-Namen dynamisch

= 1.33.2 - 2025-11-24 =
* Fix: Provider-Dropdown lädt jetzt Provider über Germanized API statt Datenbank
* Verbesserung: Fallback auf mehrere mögliche Datenbank-Tabellen
* Verbesserung: Unterstützt verschiedene Germanized/Shiptastic Versionen

= 1.33.1 - 2025-11-24 =
* Fix: Syntax-Fehler in class-wlm-admin.php behoben (Klasse nicht korrekt geschlossen)

= 1.33.0 - 2025-11-24 =
* Neu: Tracking-Integration - Shiptastic Provider können Versandarten zugeordnet werden
* Neu: WLM_Tracking_Helper Klasse für externe Plugin-Integration
* Neu: AJAX Endpoint für Germanized/Shiptastic Provider
* Feature: Transit-Zeiten werden automatisch an Tracking-Plugin übermittelt
* Feature: Ship-by Date Verzögerungserkennung für Tracking
* Verbesserung: Dynamische Transit-Zeiten statt hardcoded Werte

= 1.32.2 - 2025-11-24 =
* Fix: Export-Funktion - Statischer Methodenaufruf korrigiert
* Verbesserung: Direkte get_option() Verwendung für bessere Performance

= 1.31.0 - 2025-11-24 =
* Neu: Custom REST API Endpoints für einzelne Lieferzeitwerte
* Neu: `/orders/{id}/ship-by-date` Endpoint
* Neu: `/orders/{id}/earliest-delivery` Endpoint
* Neu: `/orders/{id}/latest-delivery` Endpoint
* Neu: `/orders/ship-by/{date}` Endpoint für Logistik-Reminder
* Verbesserung: Effizientere API für ERP-Integration

= 1.30.4 - 2025-11-24 =
* Fix: Bestelldatum auf Thank-You-Page zeigt jetzt echtes Datum
* Fix: Ship-by Date wird korrekt im Backend angezeigt
* Verbesserung: Ship-by Date Berechnung auf Thank-You-Page

= 1.30.0 - 2025-11-24 =
* Neu: Ship-by Date Feature für Logistik-Reminder
* Neu: Horizontale Timeline auf Thank-You-Page
* Fix: Express-Berechnung berücksichtigt jetzt Bearbeitungszeit
* Fix: Single Processing Time statt Min/Max für einfachere Kalkulation

= 1.29.4 - 2025-11-24 =
* Fix: Kritischer Fehler bei Feiertagen behoben
* Fix: Holidays werden jetzt als Array gespeichert
* Verbesserung: Robustere Array-Handling im Admin-JS

= 1.29.0 - 2025-11-23 =
* Neu: Vereinfachte Bearbeitungszeit (single value statt min/max)
* Neu: Unterstützung für Dezimalwerte in Bearbeitungszeit
* Fix: Express-Berechnung korrigiert
* Verbesserung: Automatische Migration alter Einstellungen

Vollständiges Changelog siehe `CHANGELOG.md`

== Upgrade Notice ==

= 1.31.0 =
Neue REST API Endpoints für effiziente ERP-Integration. Empfohlenes Update für alle die mit externen Systemen arbeiten.

= 1.30.0 =
Wichtiges Update mit Ship-by Date Feature und Express-Fixes. Bitte testen Sie nach dem Update die Lieferzeitberechnung.

= 1.29.0 =
Breaking Change: Bearbeitungszeit wurde von Min/Max auf Single Value vereinfacht. Automatische Migration läuft beim ersten Admin-Zugriff.

== Developer Documentation ==

Ausführliche Entwickler-Dokumentation findest du im `docs/` Verzeichnis:

* `REST-API-DOCUMENTATION.md` - Vollständige API-Referenz
* `ERP-INTEGRATION-GUIDE.md` - Guide für ERP-Integration
* `SHORTCODES.md` - Verfügbare Shortcodes
* `DEBUG.md` - Debug-Modus und Logging

== Support ==

Bei Fragen oder Problemen:
* GitHub: https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager
* Website: https://seoparden.de

== Privacy Policy ==

Dieses Plugin speichert keine personenbezogenen Daten außer den für die Lieferzeitberechnung notwendigen Bestelldaten, die bereits von WooCommerce gespeichert werden.
