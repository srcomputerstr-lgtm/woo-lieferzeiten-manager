# WLM 1.43.4 – Warenkorbweiter Zuschlags-Ausschluss

## Deine Konfiguration

Im bestehenden Zuschlag die Maßbedingung **Längste Seite größer als 120 cm** beibehalten. In den beiden Ausschlusszeilen die bisherige Auswahl **none of** bewusst auf die neue Auswahl **Warenkorb: keiner** umstellen:

| Auswahl | Typ | Merkmal | Ausgeschlossene Werte |
|---|---|---|---|
| Produktmaß | Produktmaß | Längste Seite > 120 cm | – |
| Warenkorb: keiner | Attribut | Versandgruppe | Musterversand, Speditionsgut |
| Warenkorb: keiner | Versandklasse | – | Langgut |

Die erste Zeile ist wie bisher eine normale Produktmaß-Bedingung ohne separaten Logikoperator. Die beiden übrigen Zeilen sind der neue globale Ausschluss. Die passenden tatsächlich gespeicherten Werte aus der Werteliste wählen und speichern.

**Sobald irgendein Artikel Musterversand oder Speditionsgut als Versandgruppe hat oder zur Versandklasse Langgut gehört, greift dieser Zuschlag nicht.** Ein anderer erlaubter Paketgut-Artikel kann den Ausschluss nicht mehr „überstimmen“. Das gilt unabhängig von Reihenfolge und Menge und auch dann, wenn der ausgeschlossene Artikel in einem anderen Versandpaket liegt. Virtuelle Artikel gehören ebenfalls zum vollständigen Warenkorb.

Mehrere Werte innerhalb einer Ausschlusszeile wirken als ODER: Ein einziger ausgewählter Treffer genügt zum Verhindern des Zuschlags. Mehrere Bedingungszeilen bleiben UND-verknüpft; jeder Ausschluss muss bestehen und die übrigen Bedingungen müssen erfüllt sein. Die Maß-, Gewicht- und Kostenberechnung bleibt in ihrem bisherigen Paketkontext; nur der neue Ausschluss prüft den gesamten Warenkorb.

## Beispiele

- Nur erlaubtes Paketgut, mindestens eine Seite über 120 cm: Zuschlag kann greifen, sofern auch Gewicht, Warenkorbwert und sonstige bestehende Bedingungen passen.
- Paketgut über 120 cm plus ein Speditionsgut-Artikel: kein Zuschlag aus dieser Regel.
- Paketgut über 120 cm plus ein Musterversand-Artikel: kein Zuschlag aus dieser Regel.
- Erlaubte Versandgruppe, aber irgendein Artikel hat Versandklasse Langgut: kein Zuschlag aus dieser Regel.
- Nur erlaubte Artikel, aber keine Seite über 120 cm: kein Zuschlag aus dieser Regel.

Andere Zuschläge werden durch diesen Ausschluss nicht pauschal abgeschaltet. Er wirkt ausschließlich auf die Regel, in der er eingetragen ist.

## Wichtig: Ausschluss ist keine positive Vollständigkeitskontrolle

Artikel ohne Versandgruppe oder mit einer anderen, nicht ausgeschlossenen Versandgruppe lösen keinen Ausschluss aus. Die Regel bedeutet also **„Keiner der ausgewählten verbotenen Werte darf vorkommen“**, nicht automatisch **„Jeder Artikel muss ausdrücklich Paketgut sein“**. Wenn es weitere Gruppen gibt, die ebenfalls nicht erlaubt sind, müssen sie zusätzlich ausgeschlossen werden. Eine zwingende positive Prüfung „alle Artikel besitzen Paketgut“ ist eine andere Prüfart und wurde hier nicht heimlich eingeführt.

## Alte Regeln bleiben unverändert

**none of**, **only**, **all of** und **at least one of** behalten ihre bisherige Einzelprodukt-Bedeutung. Bestehende gespeicherte Regeln werden nicht automatisch migriert. Du musst nur die gewünschten Ausschlusszeilen auf **Warenkorb: keiner** umstellen.

Die neue Auswahl ist für Attribute, Taxonomien und Versandklassen vorgesehen, nicht für die Maßprüfung. Leere oder fehlerhafte neue Ausschlussregeln werden beim Speichern abgewiesen; vorhandene Einstellungen bleiben erhalten. Ungültige gespeicherte neue Regeln werden auch zur Laufzeit nicht als bedingungslos gültig behandelt.

Produkthinweise bleiben eine Einzelprodukt-Vorschau. Die tatsächliche Warenkorbzusammensetzung und der daraus resultierende Zuschlag werden im Warenkorb/Checkout geprüft.

## Sicher installieren

Vor Live-Update Plugin und Shop sichern, möglichst auf Staging testen und die aktive WLM-Installation ersetzen. Nur eine WLM-Version aktivieren. Das ZIP verwendet den Hauptordner `woo-lieferzeiten-manager/`. Keine Produkt-/Bestellmigration und keine automatische Änderung der bisherigen Zuschlagskonfiguration.
