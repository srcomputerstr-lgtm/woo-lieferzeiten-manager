# Produktmaß-Zuschläge ab WLM 1.43.3

## Neue Bedingung anlegen

Im bestehenden WooCommerce-Versandbereich des Lieferzeiten Managers den Tab **Zuschläge** öffnen. Einen Zuschlag hinzufügen oder bearbeiten, eine Bedingung ergänzen und als Typ **Produktmaß** wählen. Verfügbare Maße: **Länge**, **Breite**, **Höhe** oder **Längste Seite**. Der Vergleich lautet ausschließlich **größer als**. Grenzwert ohne Einheit eintragen, beispielsweise `200` oder `200,5`, dann speichern.

Betrag, Berechnung pro, Priorität, Steuerklasse, Expressfreigabe und Zuschlagsstrategie weiterhin in den vorhandenen Feldern einstellen. Bestehende Zuschläge werden nicht automatisch auf Maßbedingungen umgestellt.

## Datenquelle und Bedeutung

Die Prüfung verwendet ausschließlich die Produktfelder unter **Produktdaten → Versand → Länge/Breite/Höhe**. Ein gleichnamiges Produktattribut, etwa „Länge“, ist ausdrücklich nicht die Datenquelle.

**Längste Seite größer als 200 cm** trifft zu, sobald mindestens ein Produkt im geprüften Versandpaket eine Länge, Breite oder Höhe über 200 cm besitzt. Ein Produkt mit exakt 200 cm löst keinen Treffer aus. Maße werden weder zusammengerechnet noch mit der bestellten Menge multipliziert. Mehrere Stücke eines kurzen Artikels ergeben also keine längere Seite.

Bei Varianten werden die eigenen Maße verwendet. Ist ein Variantenmaß leer, übernimmt WooCommerce in seinem normalen Lesezugriff den entsprechenden Wert des Elternprodukts. Ein fehlender oder nicht positiver Maßwert wird nicht als Treffer behandelt. Bei „längste Seite“ reicht eine vorhandene tatsächlich zu lange Seite; es müssen nicht alle drei Maße hinterlegt sein.

Dabei bleibt auch die native WooCommerce-Nullwert-Semantik erhalten: Ein Variantenmaß `0` gilt im normalen Lesezugriff wie leer und kann deshalb auf ein hinterlegtes Elternmaß zurückfallen. Es wird nicht künstlich als eigene Null-Länge erzwungen.

## Einheit und Eingaben

Die Einheit neuer Regeln stammt aus **WooCommerce → Einstellungen → Produkte → Maßeinheit** und wird im Regelbereich angezeigt. Vorhandene Regeln behalten ihre gespeicherte Einheit; der Vergleich berücksichtigt die aktuelle Shop-Einheit mit der WooCommerce-eigenen Umrechnung. Bei Änderung der Shop-Einheit müssen natürlich auch die Produktmaßwerte korrekt gepflegt beziehungsweise tatsächlich umgerechnet sein.

Grenzwerte ab 0 sowie Dezimalpunkt oder Dezimalkomma sind zulässig. Keine Einheit oder Tausendertrennzeichen ins Eingabefeld schreiben. Eine ungültige Maßbedingung führt beim Speichern zu einer Fehlermeldung; die bisher gespeicherten Einstellungen bleiben erhalten. Sie wird nicht einfach gelöscht, wodurch der Zuschlag versehentlich bedingungslos werden könnte.

## Bestehende Logik bleibt bestehen

Mehrere Bedingungen eines Zuschlags sind weiterhin UND-verknüpft. Im Versandpaket prüft jede Bedingung wie bisher, ob mindestens ein Produkt sie erfüllt; bestehende Kombinationen werden nicht auf eine neue gemeinsame Ein-Produkt-Logik umgestellt.

**Berechnung pro** wird nicht verändert: Der neue Typ entscheidet nur, ob der Zuschlag greift. Bei Warenkorb erfolgt die bisherige pauschale Berechnung; bei Produkt/Position/Menge gelten die bisherigen Zählregeln für das Paket, nicht eine neu eingeführte Zählung ausschließlich der Maßtreffer. Vorhandene Attribute-, Taxonomie- und Versandklassenbedingungen sind unverändert.

Ist ein Produkthinweis beim Zuschlag gepflegt, verwendet er dieselbe Maßprüfung. Keine zusätzliche Hinweis- oder Debugbox wird eingeführt.

## Update sicher einspielen

Shop/Plugin vorher sichern und die neue Version zuerst auf Staging installieren. Die aktive WLM-Installation ersetzen, nicht eine zweite gleichzeitig aktivieren. Es erfolgt keine Produkt-/Bestellmigration und keine Änderung bestehender Maßdaten. Eine neue Maßregel muss bewusst angelegt und gespeichert werden.

Empfohlener Test mit einem bekannten Produkt: Maß unter Grenzwert, genau am Grenzwert, darüber; zusätzlich eine Variante sowie „So schnell wie möglich“/Einlagerung und gegebenenfalls Express im Checkout prüfen. Das Release-ZIP hat den Plugin-Hauptordner `woo-lieferzeiten-manager/`.
