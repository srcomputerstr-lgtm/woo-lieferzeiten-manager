# Woo Lieferzeiten Manager 1.43.0 – Gratis Einlagerung

**Datum:** 09.11.2026
**Basis:** 1.42.20 (`fac8cde`)

## Neue Funktion

Eine wiederverwendbare, standardmäßig deaktivierte Saisonaktion „Gratis Einlagerung“. Sie besitzt einen eigenen Backend-Tab mit Aktionsschalter, pflegbaren Eintreffzeitfenstern und Checkbox-Freigaben für Zahlungsarten. Die sechs vorgegebenen Fenster reichen vom 01.–05.03.2027 bis zum 05.–09.04.2027.

Im WooCommerce **Block-Checkout** erscheint bei aktiver Aktion automatisch eine native Radioauswahl vor der Zahlung. „So schnell wie möglich“ bleibt der Standard und nutzt die bisherige Lieferberechnung. Ein anderes Fenster gilt für die gesamte Bestellung und ersetzt das Eintreffzeitfenster beim Kunden.

Eine gültige Auswahl erzeugt eine kostenlose Anzeigezeile in der Positionsübersicht. Bei Änderung wird sie ersetzt, bei Rückkehr zum Standard entfernt. Erst in der Bestellung entsteht genau eine echte Artikelposition ohne SKU/Produktreferenz, Menge 1 und Betrag 0. Kein zusätzlicher Warenartikel oder Gebühreneintrag beeinflusst den Warenkorb.

## Sicherheitsprüfungen

- Zu frühe, bereits begonnene, abgelaufene oder versandseitig nicht erfüllbare Fenster werden ausgegraut und serverseitig abgewiesen.
- Aktuelle Produktverfügbarkeit, Mengen, Bearbeitungszeit und gewählte WLM-Versandart werden berücksichtigt. Versand-Deadline wird anhand maximaler Laufzeit und des vorhandenen Werktag-/Feiertagskalenders berechnet.
- Die Auswahländerung nutzt `extensionCartUpdate`. Versandraten werden vor ihrer Validierung auch in einem frischen HTTP-Request geladen.
- Nur freigegebene, ohnehin aktive Zahlungen werden bei Einlagerung angeboten; serverseitige Kontrolle vor Zahlung. Hinweis: „Durch die ausgewählte Einlagerungsoption können nur bestimmte Zahlungsarten angeboten werden.“
- Nach Warenkorb-/Adress-/Versandänderungen wird erneut geprüft. Eine ungültige Wahl muss vor Abschluss korrigiert werden.
- Eine deaktivierte Aktion entfernt alte Einlagerungswahlen aus offenen Sitzungen und lässt den normalen Checkout zu; bereits aufgegebene Bestellungen bleiben erhalten.
- Einlagerungs-Snapshot schützt bestehende Liefer-/Ship-by-Felder vor späterer Standard-Neuberechnung. Die Zahlungsfreigaben der aufgegebenen Bestellung werden ebenfalls gespeichert.
- Neue Antwortdaten liegen nur im Cart-Extension-Schema. Der Checkout-POST erhält keine fälschlich als Eingabedaten validierten Antwortfeld-Defaults.
- Backend-Speicherung nutzt eigene Nonce/Berechtigung und schreibt ausschließlich die neue Option `wlm_free_storage`.

## Bestehende Dateien: begrenzter Eingriff

Vier bestehende PHP-Klassen erhalten ausschließlich neue Anbindung beziehungsweise Schutzabfragen: Admin-Navigation/-Speicherung, Blocks-Lieferanzeige bei gültiger Auswahl, Core-Bestellspeicherung und Frontend-Bestelltermine. Hauptdatei lädt die getrennten neuen Klassen; Versionsfelder und Changelog wurden gepflegt.

**71 bestehende Dateien sind gegenüber 1.42.20 bytegenau unverändert**, darunter zentraler Calculator, Produktfelder und Datums-Hotfix, Stock-/SKU-JavaScript, Versandmethoden, Express-/Zuschlagsklassen, REST-API und Benachrichtigungsklassen. Das Paket enthält genau einen obersten Ordner `woo-lieferzeiten-manager/`.

## Tests

Die bisherige Datums-Hotfix-Testsuite besteht weiterhin mit **119/119 Assertions**. Die echte lokale WordPress-/WooCommerce-Integration prüft vollständige Bestellabschlüsse, Versand-/Fensterarithmetik, Standard-/deaktivierte Aktion, Manipulationsabwehr, Snapshot und Gratisposition. Der native Block-Checkout wurde zusätzlich mit echten HTTP-Auswahländerungen im Browser geprüft: ausgegrautes zu frühes Fenster, neue und aktualisierte Gratiszeile, Zahlungsmethoden-Sperre, unveränderte Beträge und Standard-Rückkehr.

Ein gesonderter Testbericht beschreibt den finalen Teststand und Grenzen. PHP-Dateien und neue JavaScript-Dateien wurden auf Syntax geprüft.

## Grenzen und Update

Keine Live-Shopänderung wurde durchgeführt. Vor Update Datenbank/Plugin sichern; nur eine WLM-Installation aktiv halten. Bei vorhandenen Altordnern die tatsächliche aktive Installation prüfen. Die Aktion nach Staging-Abnahme gezielt einschalten.

Der offizielle JTL-Connector exportiert produktlose 0-€-Artikelpositionen ohne SKU. **Der tatsächliche Wawi-Import und kundenspezifische Versand-/Zahlungsworkflows müssen vor Live-Freigabe getestet werden.** Die neue auswählbare UI ist für den Block-Checkout implementiert; bestehender klassischer Checkout wird nicht umgestaltet. Keine pauschale Zusage für alle Oxygen-/Theme-/Zahlungsanbieter-Konfigurationen.
