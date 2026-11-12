# Woo Lieferzeiten Manager 1.43.3

**Datum:** 12.11.2026

## Neue Zuschlagsbedingung „Produktmaß“

Zusätzlich zu Attribut, Taxonomie und Versandklasse kann ein Zuschlag nun anhand der WooCommerce-Versandmaße ausgelöst werden. Auswahl: Länge, Breite, Höhe oder längste Seite. Vergleich: streng größer als frei einstellbaren Grenzwert, mit sichtbarer Maßeinheit und Unterstützung für Dezimalwerte.

Es wird pro Produkt geprüft, nicht die Summe der Seitenlängen oder die mit der Menge multiplizierte Länge. Der WooCommerce-eigene Varianten-Lesezugriff berücksichtigt eigene Maße und den normalen Eltern-Fallback. Fehlende Maße lösen keinen Treffer aus. Gespeicherte Regeln behalten ihre Maßeinheit; der Vergleich verwendet die WooCommerce-eigene Einheitenumrechnung.

Die aktive Zuschlagsberechnung und die bestehenden konfigurierbaren Produkthinweise verwenden denselben neuen Maßprüfer. Ungültige Maßbedingungen werden vor dem Speichern zurückgewiesen; keine versehentlich bedingungslosen Zuschläge durch Wegfiltern einer neuen fehlerhaften Regel.

## Unverändert

Bisherige Attribute-, Taxonomie- und Versandklassenbedingungen, deren Logik sowie Zuschlagshäufigkeit, Betrag, Priorität, Steuerfelder und Strategien. Keine Produktmaß-, Produkt-, Bestell- oder Einstellungs-Migration. Keine Änderungen an Lieferzeitberechnung, Verfügbarkeits-Hotfix, Einlagerung/SKUs, Checkout-Layout, Express, Zahlungsarten oder REST-API-Routen.

## Bedienung und Update

Bestehender Backend-Tab Zuschläge → Bedingung hinzufügen → Produktmaß → Maß und Grenzwert wählen → speichern. Die bisherige Auswahl „Berechnung pro“ bleibt maßgeblich; sie zählt weiterhin nach den vorhandenen Paketregeln, nicht ausschließlich Maßtreffer. Details: `ANLEITUNG-Produktmass-Zuschlag.md`.

Neue Version 1.43.3 im Pluginheader und Stable-Tag. ZIP mit Hauptordner `woo-lieferzeiten-manager/`. Vor Live-Update sichern und auf Staging testen; nur eine WLM-Version aktivieren. Ergebnis und Grenzen der Prüfungen stehen im separaten Testbericht.
