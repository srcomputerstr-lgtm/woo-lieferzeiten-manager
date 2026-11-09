# Woo Lieferzeiten Manager 1.43.1 – Express / Einlagerung

**Datum:** 09.11.2026

## Bestätigte Regel

WLM-Express und Gratis Einlagerung schließen sich bei neuen Checkout-Auswahlen gegenseitig aus. Die bisherige Expressfunktion wird außerhalb einer Einlagerung nicht umgebaut.

## Gezielte Änderungen

- Bei Einlagerung keine WLM-Express-Versandrate und kein kurzfristiger Express-Vorschautermin.
- Bei Wechsel von Express zu Einlagerung: verfügbare reguläre Variante derselben Versandart, Entfernen der älteren Express-Zusatzauswahl und Neuberechnung von Versandkosten/Termin vor Übernahme.
- Einlagerungsfenster werden auch bei aktuell gewähltem Express gegen die reguläre Laufzeit geprüft. Kein Fenster, das ausschließlich per Express erreichbar wäre.
- Wenn die Änderung nicht erfüllbar ist, wird sie abgewiesen und der vorherige Auswahlzustand zurückgerollt.
- Rückkehr zu „So schnell wie möglich“: Express wieder nach bisherigen Regeln verfügbar; keine automatische Auswahl und keine Wiederherstellung alter Express-Zusatzauswahlen.
- Paketcache und serverseitige Versandprüfung verhindern verbliebene Expressraten. Ältere Express-Aktivierungsrequests werden nur bei Einlagerung gesperrt.
- Kurzer Hinweis im Checkout. Klassischer Express-Button erhält ausschließlich eine zusätzliche Schutzbedingung.

## Unverändert

Zentraler Calculator, Produktdaten, Datums-Hotfix, Stock-/SKU-Anzeige, Zuschläge, REST-Listen, Mail-/JTL-Positionsformat und bereits aufgegebene Bestellungen. Vorbestehende Express-Kostenregeln außerhalb einer Einlagerung werden nicht verändert oder nebenbei repariert. Gespeicherte Aktionseinstellungen bleiben erhalten.

## Update

Aktuell aktive Installation ersetzen, keine zweite WLM-Version aktivieren. ZIP enthält genau einen Hauptordner `woo-lieferzeiten-manager/`. Vor dem Live-Update sichern und beide Wechselrichtungen auf Staging mit den tatsächlichen Versand-/Zahlungsarten sowie JTL-Testimport prüfen. Fremde Expressmethoden ohne WLM-Zuordnung werden nicht anhand einer beliebigen Beschriftung verändert.

Der separate Testbericht dokumentiert die abschließenden Prüfungen und deren Grenzen.
