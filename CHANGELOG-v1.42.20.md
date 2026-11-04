# Woo Lieferzeiten Manager – Hotfix 1.42.20

**Datum:** 4. November 2026  
**Basis:** GitHub `main`, Version 1.42.19, Commit `ab6d35827df9fd2d0cd7819743ecf0fdd026a0ff`.

## Zweck

Das manuelle Feld **„Lieferbar ab“** (`_wlm_available_from`) darf kein vergangenes oder ungültiges Datum als Override behalten. Beispielsweise wird der gespeicherte Wert `0001-01-01`, im deutschen Datumsfeld als `01.01.0001` angezeigt, entfernt. Das vorhandene **berechnete Verfügbarkeitsdatum** (`_wlm_calculated_available_date`) kann anschließend wieder über die bestehende Berechnungspriorität verwendet werden.

Aus dem Screenshot lässt sich nicht ableiten, wer oder welcher Prozess den fehlerhaften Wert geschrieben hat. Dieser Hotfix ist eine Schutzmaßnahme, keine Behauptung über eine Ursache durch PHP oder WordPress.

## Änderung ausschließlich am manuellen Datumsfeld

Gültig ist ein echtes Kalenderdatum im gespeicherten Format `YYYY-MM-DD`, das mindestens dem heutigen Datum in der **WordPress-Zeitzone** entspricht. Heute und zukünftige manuelle Termine bleiben erhalten. Leere Eingaben, Vergangenheitsdaten und ungültige Angaben werden als „kein manueller Termin“ behandelt.

Bereits gespeicherte Fehlwerte werden beim Auslesen für die Verfügbarkeitsberechnung, beim Anzeigen der Produkt-/Variantenfelder im Admin und im **bestehenden täglichen Verfügbarkeits-Cronjob** gelöscht. Der Cronjob behält seine bisherige Auswahl veröffentlichter Produkte und Varianten; seine Terminplanung und eigentliche Datumsberechnung werden nicht verändert. Es gibt keine neue synchrone Massenmigration beim Plugin-Update.

Beim Admin-Speichern eines Produkts oder einer Variante bleibt ein leeres bzw. ungültiges manuelles Feld leer. Die beiden bisherigen Speicherblöcke, die bei leerer Eingabe automatisch wieder einen Wert in das manuelle Feld geschrieben haben, sind ausschließlich für dieses Feld entfernt. Andernfalls würde erneut ein manueller Override entstehen. Die Lieferzeit in Tagen und der maximal sichtbare Bestand werden unverändert gespeichert.

Zusätzliche eng begrenzte Hooks bereinigen dieses eine Feld auch bei WordPress-Metadaten-Schreibzugriffen und vor WooCommerce-Produktspeicherung. Der WooCommerce-Hook berücksichtigt dabei den Metadatenzustand des Produkt-/Variantenobjekts, nicht nur den gespeicherten Datenbankwert.

Die bestehende Verfügbarkeitspriorität bleibt erhalten:

1. Bei ausreichendem Lagerbestand: sofort verfügbar.
2. Bei nicht ausreichendem Lagerbestand: gültiger manueller Termin ab heute.
3. Ansonsten: vorhandenes gültiges berechnetes Verfügbarkeitsdatum ab heute.
4. Ansonsten: bisherige Produkt-Lieferzeit bzw. globale Lieferzeit.
5. Ansonsten: bisheriger Rückfall auf den aktuellen Zeitpunkt.

## Nicht geändert

Die Berechnung von `_wlm_calculated_available_date`, Werktage, Feiertage, Cut-off, Versandarten, Express, Zuschläge, Bestellungen, Benachrichtigungen, REST-Routen sowie der JavaScript-/CSS-Code von Produktanzeige, SKU-Badge, Block-Warenkorb und Checkout werden nicht umgebaut.

Die REST-Antwortformate bleiben unverändert: Eine bestehende Schreibrouten-Antwort kann weiterhin den übermittelten Originalwert wiedergeben, obwohl ein ungültiger Wert durch den Schutz-Hook bereits entfernt wurde. Bestehende REST-Funktionen, die aus einer Lieferzeit heute/zukünftige manuelle Datumswerte erzeugen, bleiben ebenfalls unverändert. Der Hotfix entfernt nur ungültige oder abgelaufene Werte; er deaktiviert keine gültigen automatisierten Datumsupdates.

## Geänderte Laufzeitdateien

- `includes/class-wlm-product-fields.php`: zentrale Prüfung, Bereinigung und Speicherung des manuellen Datumsfeldes.
- `includes/class-wlm-calculator.php`: genau ein Lesezugriff für das manuelle Datum auf den Schutz-Helper umgestellt; übrige Berechnung unverändert.
- `includes/class-wlm-core.php`: Bereinigung im bereits vorhandenen Cronjob ergänzt; übrige Berechnung unverändert.
- `woo-lieferzeiten-manager.php`: ausschließlich Versionsheader und Versionskonstante auf `1.42.20` erhöht.

Zusätzlich: Versionsangabe in `readme.txt` und Changelog-Dateien. Keine anderen vorhandenen Laufzeitdateien geändert.

## Update in einer Live-Umgebung

1. Vor dem Update das bestehende Plugin und die Datenbank sichern. Die neue Schutzlogik entfernt bewusst vergangene/ungültige manuelle Werte; ein reines Plugin-Rollback stellt diese Daten nicht wieder her.
2. Das ZIP als Update des bestehenden Plugins installieren; der oberste ZIP-Ordner heißt `woo-lieferzeiten-manager/`.
3. Ein betroffenes Produkt öffnen: „Lieferbar ab“ muss leer sein, das vorhandene berechnete Datum darf durch diese Bereinigung nicht verändert werden.
4. Produktseite und Warenkorb bei einem nicht verfügbaren Produkt prüfen. In dem gezeigten Beispiel soll das vorhandene berechnete Datum `2026-12-03` statt des Fehlwertes greifen.
5. Für die Bereinigung aller vom bisherigen Cronjob erfassten Produkte den **vorhandenen Verfügbarkeits-Cronjob** einmal über die bisher genutzte Funktion ausführen oder seinen nächsten regulären Lauf abwarten. Nicht mit Bestellungs-/Benachrichtigungs-Cronjobs verwechseln.

Das Update wurde in einer isolierten Sandbox vorbereitet. Es wurde nicht auf dem Live-Shop installiert; ohne dessen konkrete WordPress-/WooCommerce-Version, weitere Plugins und Hostingkonfiguration ist eine Ausfallfreiheit nicht garantierbar.

## Abgeschlossene Prüfungen

- **119/119 gezielte Regressionstests bestanden** mit den realen WLM-Klassen und isolierten WordPress-/WooCommerce-Testdoubles: Datumsvalidierung, WordPress-Tagesgrenze, Speicherung, Löschung, Varianten, berechneter Fallback, Bestand, Nachbestellungen, Lieferzeiten und Cronjob.
- **417/417 echte WordPress-/WooCommerce-Integrationsprüfungen bestanden**, 0 Fehler: WordPress 7.1.4, WooCommerce 11.2.1, PHP 8.3.6, offizielles SQLite Database Integration 3.1.3, Zeitzone Europe/Berlin. Echte Produkte/Varianten, SQL-Rohdaten, WooCommerce CRUD, WP-Metadatenzugriffe, Admin-Speicherhooks, Calculator und vorhandener Cronjob. Der getestete Quellstand entspricht dem veröffentlichten Hotfix.
- **24/24 vorhandene PHP-Dateien syntaxfehlerfrei** unter PHP 8.3.6. Normaler Plugin- und gesonderter Admin-Bootstrap ohne Fatal.
- **71 vorhandene Dateien bytegenau unverändert** gegenüber 1.42.19; in Calculator genau ein Lesezugriff geändert, im Core genau drei Zeilen im bisherigen Cronloop ergänzt, in der Hauptdatei ausschließlich die Version geändert.
- ZIP-Struktur, vollständige Dateiinhalte und Archivintegrität geprüft.

**Testgrenzen:** SQLite statt MySQL; keine Tests auf eurem Live-Shop, keine vollständigen Browser-/REST-/ERP-End-to-End-Läufe und keine PHP-/WordPress-/WooCommerce-Versionsmatrix. Bei direktem externem `update_post_meta` oder ID-basierter Altwertbereinigung werden beliebige zuvor geladene WooCommerce-Objekte nicht automatisch nachgeführt. Die Konsistenz beim Speichern desselben WooCommerce-Produktobjekts ist hingegen ausdrücklich getestet. Ein nicht-fatales Translation-Loading-Notice wurde beim lokalen Aktivieren beobachtet; es wurde im begrenzten Datums-Hotfix nicht separat bearbeitet.
