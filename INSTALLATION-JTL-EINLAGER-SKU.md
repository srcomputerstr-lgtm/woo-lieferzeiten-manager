# JTL-SKU für Gratis Einlagerung – Zusatzadapter zu WLM 1.43.2

## WLM-Update und Connector-Adapter sind zwei Schritte

WLM 1.43.2 vergibt je Einlagerungsoption eine stabile SKU und speichert sie auf neuen Bestellungen/Positionen. Der offizielle JTL-WooCommerce-Connector liest für produktlose Positionen allerdings keine SKU aus beliebigen Positionsmetadaten. Deshalb genügt das **WLM-Update allein nicht**, um `sku: "EINLAGER-001"` im Standard-JTL-Export zu erhalten. [1]

Der Zusatzadapter setzt im Exportereignis ausschließlich das SKU-Feld der genau zugeordneten markierten Einlagerungsposition. Produktreferenz, Name, Menge, Preise, Steuern und alle normalen Artikel bleiben unverändert. Keine Artikelanlage, keine Bestandsänderung, kein künstliches Warenkorbprodukt und kein Speichern während des Exports.

## Installation zuerst auf Staging

1. Datenbank, WLM und JTL-Connector sichern. Tatsächliche JTL-Connector-Version und Pluginordner prüfen. Die verwendete Schnittstelle wurde mit offiziellen Connector-2.5.0-/aktuellen Quellen und Connector-Core 6.0.1 geprüft; andere installierte Versionen nicht ungeprüft als kompatibel annehmen.
2. Die aktive WLM-Installation durch 1.43.2 ersetzen. Anschließend im Einlagerungs-Tab einmal speichern. Die SKU-Spalte zeigt die Zuordnung. Die bestehenden Standardoptionen erhalten `EINLAGER-001` bis `EINLAGER-006`; neue Optionen die nächste Nummer.
3. Das separate `JTL-WlmFreeStorage-v1.43.2.zip` entpacken. Es enthält den Ordner `WlmFreeStorage` mit exakt `Bootstrap.php`. Diese Datei ist ebenfalls im WLM-ZIP unter `integrations/jtl/WlmFreeStorage/Bootstrap.php` enthalten.
4. Den Ordner in das tatsächlich vom Connector gescannte **Connector-Pluginverzeichnis** kopieren. Im Standardfall lautet die Zieldatei:

   ```text
   wp-content/plugins/<tatsächlicher-jtl-connector-ordner>/plugins/WlmFreeStorage/Bootstrap.php
   ```

   Beispiel nur bei entsprechendem Ordnernamen:

   ```text
   wp-content/plugins/woo-jtl-connector/plugins/WlmFreeStorage/Bootstrap.php
   ```

   Bestehende Connector-Dateien, andere Adapter und Konfigurationen **nicht ersetzen**. Nur den neuen Unterordner ergänzen. `Bootstrap.php` samt Großschreibung beibehalten.
5. Kein Aktivieren dieses ZIPs als normales WordPress-Plugin. Es ist ein JTL-Connector-Plugin. Einfaches Ablegen im WLM-Ordner registriert keinen Listener.
6. Falls `plugins_dir` im Connector abweichend konfiguriert wurde, zählt dessen tatsächlicher Scanpfad. Das bekannte Geschwisterverzeichnis `woo-jtl-connector-custom-plugins` ist nicht ohne bestätigte Discovery automatisch ausreichend; Autoloading allein bedeutet nicht Registrierung. Keine globale Pfadumstellung vornehmen, durch die andere Adapter verschwinden. [2]

## Vor Live-Einsatz zwingend prüfen

Eine **neue** Einlagerungs-Testbestellung abrufen. In der tatsächlichen JTL-Connector-Rohantwort muss genau deren kostenlose Position die richtige SKU haben, beispielsweise:

```json
{
  "sku": "EINLAGER-001",
  "quantity": 1,
  "price": 0,
  "priceGross": 0,
  "productId": { "endpoint": "" }
}
```

Die Produktreferenz muss weiterhin leer sein, nicht `"0"` oder eine neue Produkt-ID. Normale Artikel müssen ihre bisherigen SKUs behalten. Zweite Option mit `EINLAGER-002`, ASAP ohne Einlagerungsposition und wiederholtes Abrufen derselben Bestellung ebenfalls prüfen.

Danach in der **echten JTL-Wawi** kontrollieren, dass die Freiposition ihre Artikelnummer/SKU behält und die gewünschte Workflowbedingung diese Nummer auswerten kann. Ein korrekt serialisiertes Exportfeld beweist nicht automatisch den Import oder die Workflowmöglichkeiten der konkreten Wawi-Version. Auf ungewollte Zuordnung zu schon existierenden `EINLAGER-*`-Artikeln achten.

## Verhalten und Rücknahme

Der Listener heißt `customer_order.after.pull`; er verwendet ausschließlich den gespeicherten SKU-Snapshot und die tatsächlichen WooCommerce-Bestell-/Positions-IDs. Kein Berechnen aus aktueller Optionsreihenfolge, keine Sitzung und kein Produktlookup. Auch nach Abschalten der Aktion behalten neue bereits aufgegebene Bestellungen ihren Snapshot. Alte Bestellungen ohne SKU werden nicht nachträglich geändert. [2]

Zur Rücknahme nur den hinzugefügten Ordner `WlmFreeStorage` entfernen beziehungsweise aus dem Scanpfad nehmen. Die WLM-Funktion und gespeicherten Termine bleiben erhalten; der Standard-Connector exportiert die produktlose Position wieder ohne ergänzte SKU.

**Bei jedem JTL-Connector-Update prüfen:** Das zusätzliche Verzeichnis liegt im Connector-Pluginordner und kann beim Update verschwinden. Adapter sichern und nach dem Update im tatsächlich gescannten Pfad wieder einsetzen; danach einen erneuten Test-Pull durchführen. Diese Version installiert oder kopiert ihn nicht automatisch in fremde Pluginverzeichnisse.

## Quellen

[1]: https://raw.githubusercontent.com/jtl-software/connector-woocommerce3/2.5.0/src/Controllers/Order/CustomerOrderItemController.php "Offizielles direktes Produkt- und SKU-Mapping im Connector"
[2]: https://raw.githubusercontent.com/jtl-software/connector-core/7e7929c696217343e5f0f30f52f02e6b41878779/src/Application/Application.php "Connector-Core: Discovery, Ereignisse und Antwortverarbeitung"
