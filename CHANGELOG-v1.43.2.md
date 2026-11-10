# Woo Lieferzeiten Manager 1.43.2

**Datum:** 10.11.2026

## Layout

Die Einlagerungsposition in der Bestellübersicht verwendet den nativen WooCommerce-Totals-Item-Innenwrapper und übernimmt dessen horizontales Padding. Der kostenlose Betrag bleibt rechts, auch bei längeren Positionsnamen. Die Überschrift „Liefertermin / Gratis Einlagerung“ steht mit nativer Checkout-Schritt-Typografie über dem umrandeten Auswahlkasten. Keine globalen Theme-/Oxygen-Stile geändert.

## Einlagerungs-SKUs

Jede Option erhält automatisch eine dauerhafte SKU: EINLAGER-001, EINLAGER-002 usw. Anzeige in einer nicht editierbaren Backend-Spalte. Neue Optionen erhalten die nächste Nummer beim Speichern; bestehende Zuordnungen bleiben bei Umsortieren oder Änderung von Datum/Bezeichnung erhalten. Entfernte Nummern werden nicht neu vergeben.

Neue Bestellungen und die markierte kostenlose Position speichern die autoritativ zugeordnete SKU. Auswahlwechsel aktualisiert den Entwurf; ASAP entfernt die Einlagerungsposition samt Bestell-SKU. Bereits aufgegebene Bestellungen behalten ihren Snapshot. Alte Bestellungen ohne SKU bleiben unverändert. Der Browser kann keine eigene SKU einschleusen.

## JTL: gesonderter Adapter nötig

Der Standard-Connector liest keine SKU aus produktlosen Positionsmetadaten. Ein separater optionaler Connector-Adapter setzt ausschließlich das `sku`-Feld der zugehörigen markierten Exportposition und lässt die Produktreferenz leer. Keine Produkte, Bestände, Preise oder normalen Positionen geändert.

**WLM-Update allein aktiviert diese JTL-Anbindung nicht.** Adapter separat im tatsächlich gescannten Connector-Pluginordner installieren, danach Rohantwort und Wawi-Workflow testen. Anleitung: `INSTALLATION-JTL-EINLAGER-SKU.md`. Nach Connector-Updates Adapterverfügbarkeit erneut prüfen.

## Unverändert

Terminberechnung, Verfügbarkeits-Hotfix, Versandkosten, Express-Ausschluss, Zahlungsfreigaben, echte Produkt-SKUs, Stock-Anzeige und bisherige API-Listen. ZIP enthält ausschließlich `woo-lieferzeiten-manager/`.

Testresultate und Umgebungsgrenzen stehen im separaten Testbericht. Vor Live-Update sichern und Staging-Abnahme durchführen; nur eine WLM-Version aktivieren.
