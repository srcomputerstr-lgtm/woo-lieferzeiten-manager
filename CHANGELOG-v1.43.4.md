# Woo Lieferzeiten Manager 1.43.4

**12.11.2026 – neue ausdrücklich globale Ausschlussart für Zuschläge.**

## Neu

Auswahl **Warenkorb: keiner** (`none_in_cart`) im bestehenden Zuschlags-Tab, für Attribut-, Taxonomie- und Versandklassenbedingungen. Ein einziger Artikel mit einem der ausgewählten Werte verhindert den betreffenden Zuschlag. Der vollständige WooCommerce-Warenkorb wird geprüft, einschließlich anderer Versandpakete und virtueller Artikel. Bei direktem Calculator-Aufruf werden auch die übergebenen Paketartikel berücksichtigt.

Neue eigene Werteauslesung unterstützt Varianten-/Elternattribute, mehrere benutzerdefinierte Textwerte sowie Taxonomie-/Versandklassen-Namen und -Slugs. Diese Auslesung gilt ausschließlich für die neue Auswahl, nicht als Umstellung des alten Parsers.

Backend-Hinweis verdeutlicht die Reichweite. Leere/ungültige neue Ausschlüsse werden vor Speicher-Schreibvorgängen zurückgewiesen und zur Laufzeit nicht zu bedingungslosen Zuschlägen. Produkthinweise bleiben eine Einzelprodukt-Vorschau.

## Unverändert

Alte Operatoren `none of`, `only`, `all of`, `at least one of`, deren Produktreichweite und bestehende gespeicherte Regeln. Keine automatische Migration. Maßprüfung, Gewicht/Warenkorbwert, Kosten-/Zählregeln, Strategien, Lieferzeiten, Verfügbarkeits-Hotfix, Express, Einlagerung/SKUs, Zahlungsarten und REST nicht umgebaut.

## Bedienung

Die bestehenden gewünschten Ausschlusszeilen bewusst von **none of** auf **Warenkorb: keiner** ändern. Die Maßbedingung belassen. Fehlende oder nicht ausgeschlossene Merkmale sind nicht verboten; das ist kein automatischer positiver Nachweis, dass jeder Artikel Paketgut ist. Details in `ANLEITUNG-Warenkorb-Ausschluss.md`.

Version und Stable-Tag 1.43.4. Plugin-ZIP mit Hauptordner `woo-lieferzeiten-manager/`. Vor Live-Update sichern und auf Staging testen, nur eine WLM-Version aktivieren. Prüfungen und Grenzen im separaten Testbericht.
