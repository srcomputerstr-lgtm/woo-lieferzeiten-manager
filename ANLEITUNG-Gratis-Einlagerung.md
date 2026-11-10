# Gratis Einlagerung – Woo Lieferzeiten Manager 1.43.2

## Vor dem Update

Datenbank und bisherige Plugin-Dateien sichern. Zuerst auf einer Staging-Kopie mit den tatsächlich eingesetzten Zahlungsplugins und dem JTL-Connector testen. Das Update nimmt keine bestehende Bestellung oder Produktberechnung in eine Einlagerungsaktion auf. Bei erstmaliger Installation ist die Aktion **deaktiviert**; bereits gespeicherte Einstellungen bleiben bei einem Update erhalten.

Es darf weiterhin nur **eine Version des Woo Lieferzeiten Managers aktiv** sein. Im Shop gab es zuvor mehrere Plugin-Ordner. Dieses ZIP enthält ausschließlich `woo-lieferzeiten-manager/`. Beim Hochladen die aktuell verwendete Installation ersetzen; nicht eine zweite Installation parallel aktivieren. Falls WordPress eine unerwartet alte Version zum Ersetzen anbietet, zunächst die Plugin-Ordner prüfen statt ein weiteres Duplikat zu aktivieren. Inaktive Altordner nicht ohne vorherige Sicherung löschen.

## Backend

Unter **WooCommerce → Einstellungen → Versand → Lieferzeiten Manager / MEGA Versandmanager → Gratis Einlagerung** liegt der neue Tab. Die Beschriftung des bestehenden Versand-Unterbereichs richtet sich nach dem bisherigen Plugin.

Hier lässt sich die Aktion ein-/ausschalten. Außerdem werden Zahlungsarten per Checkbox freigegeben. Die Liste zeigt auch installierte, momentan deaktivierte Zahlungsarten; eine Einlagerungsfreigabe aktiviert diese nicht automatisch in WooCommerce. Ohne freigegebene Zahlungsmethode kann kein Einlagerungszeitfenster gewählt werden.

Lieferzeitfenster haben Beginn, Ende und eine optionale Bezeichnung. Ohne individuelle Bezeichnung wird der Datumsbereich angezeigt. Fenster können geändert, hinzugefügt oder entfernt werden. Änderungen betreffen zukünftige Auswahlvorgänge; bereits aufgegebene Einlagerungsbestellungen behalten ihre gespeicherten Termine.

Jede Option hat ab 1.43.2 eine automatische SKU, sichtbar in der eigenen Backend-Spalte: `EINLAGER-001`, `EINLAGER-002` usw. Die sechs bestehenden Standardoptionen erhalten 001–006 in ihrer bisherigen Reihenfolge. Nach dem Update einmal die Einlagerungseinstellungen speichern, um die angezeigte Zuordnung dauerhaft in der Konfiguration festzuhalten. Bei neuen Zeilen erscheint die endgültige Nummer nach dem Speichern.

Die SKU gehört zur dauerhaften Options-ID, nicht zur jeweils aktuellen Tabellenposition: Umsortieren und Ändern von Datum oder Bezeichnung behalten sie bei. Entfernte Nummern bleiben reserviert; neu angelegte Optionen bekommen die nächste freie laufende Nummer. Aufgegebene Bestellungen behalten den gespeicherten SKU-Snapshot, auch wenn die Aktion oder die Option später geändert wird. Alte Bestellungen ohne SKU werden nicht nachträglich umgeschrieben.

Vorgegeben sind:

| Eintreffen beim Kunden von | bis |
|---|---|
| 01.03.2027 | 05.03.2027 |
| 08.03.2027 | 12.03.2027 |
| 15.03.2027 | 19.03.2027 |
| 22.03.2027 | 26.03.2027 |
| 29.03.2027 | 02.04.2027 |
| 05.04.2027 | 09.04.2027 |

## Block-Checkout

Nach Aktivierung erscheint automatisch eine Radioauswahl vor dem Zahlungsbereich. Der gespeicherte Seiteninhalt und das Oxygen-Layout werden nicht umgeschrieben. Die Ergänzung nutzt die nativen Checkout-Innenblöcke von WooCommerce.

Ab 1.43.2 steht die Überschrift wie bei Versand-/Zahlungsoptionen über dem umrandeten Auswahlkasten. Die Gratisposition in der Bestellübersicht übernimmt die nativen horizontalen Innenabstände.

**So schnell wie möglich** ist Standard. Lieferzeitraum, Artikel, Preise und Zahlungsarten entsprechen dann dem bisherigen Verhalten.

Ein anderes Fenster gilt für **die gesamte Bestellung** und beschreibt das **Eintreffen beim Kunden**, nicht die Versandwoche. Neben dem gewählten Fenster erscheint in der Positionsübersicht genau eine kostenlose Zeile: `Gratis Einlagerung – Lieferung [Zeitfenster]`. Bei einem Wechsel wird sie aktualisiert; bei Rückkehr zum Standard verschwindet sie.

Die neue Zeile ist kein zusätzliches Lagerprodukt und verändert selbst weder Warenkorbmengen, Stock-/SKU-Anzeige, Gutscheine noch Gesamtbetrag. Beim Bestellabschluss entsteht daraus eine echte Bestellposition mit Menge 1 und 0,00 € ohne Produktreferenz. Ab 1.43.2 wird ihre Einlagerungs-SKU auf der Bestellung und der Position gespeichert; sie ist als Positionsmetadatum „Einlagerungs-SKU“ auch im Standard-WooCommerce-Mailtemplate sichtbar. Ein ausdrücklich gewählter Wechsel von Express zu Einlagerung berechnet hingegen den regulären Versand ohne Expressaufschlag neu, wie im folgenden Abschnitt beschrieben.

Bei Einlagerung werden nur dafür freigegebene, ohnehin in WooCommerce verfügbare Zahlungsarten angeboten. Zusätzlich erscheint: **„Durch die ausgewählte Einlagerungsoption können nur bestimmte Zahlungsarten angeboten werden.“** Die Zahlung wird auch serverseitig geprüft.

## Express und Einlagerung schließen sich aus

Ab 1.43.1 können WLM-Express-Versand und Gratis Einlagerung nicht gleichzeitig für einen neuen Checkout gewählt werden. Bei Einlagerung verschwinden die WLM-Express-Versandarten und deren kurzfristige Vorschautermine. Ein kurzer Hinweis erklärt den Ausschluss; manipulierte Auswahlversuche werden serverseitig abgefangen.

War zuvor Express gewählt, wird beim Wechsel zu einem Einlagerungsfenster die tatsächlich verfügbare reguläre Variante derselben WLM-Versandart verwendet. Eine alte Express-Zusatzauswahl wird entfernt. Kosten und Liefertermin werden neu berechnet, bevor das Fenster übernommen wird. Ist kein regulärer Versand möglich oder ist dieser zu spät, bleibt das Fenster nicht wählbar beziehungsweise die Änderung wird abgewiesen und der vorherige Zustand wiederhergestellt. Es wird kein beliebiger anderer Versandweg ungefragt ausgewählt.

Auch die angebotenen Einlagerungsfenster werden gegen den regulären Versand geprüft, wenn aktuell noch „So schnell wie möglich“ mit Express gewählt ist. Ein nur per Express erfüllbares Fenster wird daher nicht als Einlagerungsoption angeboten.

Bei Rückkehr zu **„So schnell wie möglich“** oder ausgeschalteter Aktion stehen die bisher regulär verfügbaren WLM-Expressoptionen wieder zur Auswahl. Express wird dabei **nicht automatisch gewählt**, und entfernte alte Express-Zusatzauswahlen werden nicht wieder aktiviert. Außerhalb einer Einlagerung werden bestehende Expresslaufzeiten, Lager-/Mengenprüfungen und Kostenregeln nicht umgebaut.

Bereits aufgegebene Bestellungen, einschließlich eventuell zuvor mit 1.43.0 aufgegebener Express-Einlagerungsbestellungen, werden nicht nachträglich umgeschrieben. Zusätzliche Expressmethoden fremder Plugins ohne bekannte WLM-Zuordnung sind nicht automatisch anhand ihrer Beschriftung erkennbar; eine hierfür gewünschte Anbindung muss anhand der tatsächlichen Method-ID gesondert geprüft werden.

## Nicht auswählbare und nachträglich ungültige Fenster

Die Prüfung berücksichtigt die aktuelle Warenkorbzusammensetzung und Mengen, Verfügbarkeit, Bearbeitungszeit sowie die tatsächlich gewählte WLM-Versandart. Liegt das regulär berechnete späteste Lieferdatum hinter dem angebotenen Fenster oder kann nicht rechtzeitig versendet werden, ist das Fenster ausgegraut und nicht wählbar. Bereits begonnene/abgelaufene Fenster werden ebenfalls gesperrt.

Der Versandtermin wird vom Ende des gewünschten Eintrefffensters anhand der maximalen Versandlaufzeit zurückgerechnet. Die mögliche früheste/späteste Zustellung muss vollständig in das gewählte Fenster passen. Konfigurierte Werktage und Feiertage werden berücksichtigt. Feiertage müssen – wie bisher – im Plugin hinterlegt sein; diese Erweiterung lädt keinen neuen Feiertagskalender.

Ändern sich Warenkorb, Adresse oder Versandart, wird erneut geprüft. Wird die bisherige Auswahl ungültig, erscheint ein Hinweis und der Bestellabschluss wird bis zur erneuten gültigen Auswahl beziehungsweise Rückkehr zu „So schnell wie möglich“ blockiert. Es wird keine unerfüllbare Einlagerungszusage still übernommen.

Ohne zuverlässig berechenbare WLM-Versandart, bei virtuellen/leeren Warenkörben oder mehreren Paketen mit unterschiedlichen Versandarten bleibt der Standard möglich, aber die Einlagerungsfenster sind nicht verfügbar.

## Bestelldaten und bestehende Schnittstellen

Für Einlagerungsbestellungen werden die bestehenden Felder `_wlm_earliest_delivery`, `_wlm_latest_delivery`, `_wlm_delivery_window` und `_wlm_ship_by_date` auf das gewählte Fenster beziehungsweise den passenden Versandtermin gesetzt. Damit lesen vorhandene Plugin-Schnittstellen dieselben konsistenten Termine; es wird kein neuer REST-Listenendpunkt eingeführt.

Zusätzlich hält die Bestellung einen Einlagerungssnapshot. Zahlungsstatuswechsel, die Danke-Seite und das spätere Ausschalten der Aktion überschreiben diesen nicht wieder mit den Standardterminen. Alte Bestellungen ohne Einlagerungssnapshot behalten ihr bisheriges Verhalten.

## JTL: vor Live-Freigabe unbedingt prüfen

Der geprüfte offizielle JTL-WooCommerce-Connector exportiert produktlose Artikelpositionen mit Name, Menge und Preis, liest deren SKU aber nicht aus beliebigen Positionsmetadaten. **Das WLM-Update allein setzt deshalb noch keine SKU im Standard-JTL-Export.** Dafür wird der separat mitgelieferte Connector-Adapter benötigt; siehe `INSTALLATION-JTL-EINLAGER-SKU.md` und `integrations/jtl/WlmFreeStorage/Bootstrap.php` im Plugin-ZIP. Der Adapter setzt ausschließlich das exportierte SKU-Feld und lässt die Produktreferenz leer. Es wird kein Lager-/Warenkorbprodukt angelegt.

Vor Freigabe mindestens eine Einlagerungsbestellung über euren echten Connector importieren. Prüfen: genau eine kostenlose Freiposition mit richtiger SKU, keine neue Lagerartikelanlage, unveränderte Beträge/Versandkosten, sichtbares Zeitfenster in Mails und erhaltene Liefer-/Versanddaten nach Zahlung. Erst in der echten JTL-Wawi prüfen, dass die SKU erhalten bleibt und die gewünschte Workflowbedingung darauf reagieren kann. Ein korrektes Exportfeld allein garantiert keinen Wawi-Workflow.

Der Standard-Connector überträgt nicht automatisch beliebige WooCommerce-Order-Metafelder. Falls eure Versandsteuerung bereits die WLM-REST-Routen verwendet, stehen dort die angepassten bestehenden Datumsfelder zur Verfügung. Falls JTL die Versandplanung aus anderen Feldern ableitet, ist diese bestehende Anbindung separat zu prüfen. Die Positionsbezeichnung allein steuert keinen JTL-Versandworkflow.

## Kurzer Abnahmetest

1. Aktion ausgeschaltet: Kasse, Lieferberechnung, SKU/Lagerstatus und Zahlungsarten unverändert.
2. Aktion einschalten, mindestens eine aktive Zahlungsart freigeben und Einstellungen speichern.
3. Standardauswahl prüfen; dann ein zulässiges Fenster wählen: eine 0-€-Zeile und nur erlaubte Zahlungen.
4. Zwischen zwei Fenstern wechseln; anschließend auf Standard zurück: keine doppelte oder verbleibende Zeile.
5. Einen Artikel/Menge mit späterer Verfügbarkeit hinzufügen: zu frühe Fenster gesperrt; ungültige vorhandene Auswahl erfordert neue Entscheidung.
6. Testbestellung abschließen: Termine, Ship-by, Mailposition und JTL-Import prüfen.
7. Zahlung/Statuswechsel sowie Ausschalten der Aktion prüfen: aufgegebene Einlagerungsbestellung behält ihren Termin.
8. Express wählen und danach Einlagerung: reguläre Versandvariante, kein Expressaufschlag oder Express-Vorschautermin. Zurück auf Standard: Express wieder verfügbar, aber nicht automatisch gewählt.

Die lokale Testinstallation bildet nicht sämtliche Oxygen-, Theme-, Zahlungsanbieter- und JTL-Konfigurationen des Live-Shops ab. Daher keine Installation beziehungsweise Live-Aktivierung allein auf Grundlage eines pauschalen Kompatibilitätsversprechens.
