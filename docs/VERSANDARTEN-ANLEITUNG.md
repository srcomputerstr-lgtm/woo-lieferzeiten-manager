# Anleitung: Versandarten konfigurieren

## Schritt-für-Schritt Anleitung

### 1. Versandarten-Seite öffnen

Gehen Sie zu: **WooCommerce → Lieferzeiten → Versandarten**

### 2. Neue Versandart hinzufügen

Klicken Sie auf **"+ Versandart hinzufügen"**

Eine neue Box erscheint mit vielen Feldern.

### 3. Grundeinstellungen

#### Name (Pflichtfeld!)
```
Beispiel: Musterversand
```
Dieser Name wird dem Kunden angezeigt!

#### Aktiviert
```
☑ Versandart aktivieren
```
**WICHTIG**: Diese Checkbox MUSS angehakt sein, sonst wird die Versandart nicht angezeigt!

#### Priorität
```
Beispiel: 10
```
Niedrigere Zahl = höhere Priorität

### 4. Kosten

#### Kostentyp
- **Pauschal**: Fester Betrag (z.B. 5,90 €)
- **Nach Gewicht**: Preis pro kg (z.B. 2,50 € pro kg)
- **Nach Stückzahl**: Preis pro Artikel (z.B. 1,00 € pro Stück)

#### Kosten (netto)
```
Beispiel: 5.90
```

### 5. Bedingungen (Optional)

#### Gewichtsbeschränkung
```
Min: 0 kg
Max: 30 kg
```
Versandart nur für Pakete zwischen 0 und 30 kg.

**Leer lassen** = keine Beschränkung

#### Warenkorbsumme
```
Min: 0 €
Max: 500 €
```
Versandart nur für Bestellungen zwischen 0 und 500 €.

**Leer lassen** = keine Beschränkung

#### Produktattribute / Taxonomien

Klicken Sie auf **"+ Bedingung hinzufügen"**

Eine neue Zeile erscheint:

```
[Dropdown: Attribut wählen ▼] = [Textfeld: Wert]  [Entfernen]
```

**Beispiel 1: Sperrgut**
```
[Sperrgut ▼] = [ja]
```

**Beispiel 2: Farbe**
```
[Farbe ▼] = [rot]
```

**Beispiel 3: Kategorie**
```
[Produktkategorie ▼] = [Elektronik]
```

**Wichtig**: Bei Kategorien und Tags geben Sie den **Namen** ein, nicht die ID!

#### Produktkategorien

Wählen Sie Kategorien aus der Liste:
```
☑ Elektronik
☑ Möbel
☐ Kleidung
```

Versandart wird nur angezeigt, wenn Produkt in einer der gewählten Kategorien ist.

### 6. Transitzeiten

#### Min. Transitzeit
```
Beispiel: 1
```
Mindestens 1 Werktag Versandzeit

#### Max. Transitzeit
```
Beispiel: 3
```
Maximal 3 Werktage Versandzeit

### 7. Express-Option (Optional)

#### Express-Option aktivieren
```
☑ Express-Option aktivieren
```

#### Express-Zuschlag (netto)
```
Beispiel: 9.90
```
Zusätzliche Kosten für Express

#### Express Cutoff-Zeit
```
Beispiel: 14:00
```
Bestellungen bis 14:00 Uhr werden noch am selben Tag versandt

#### Express Min. Transitzeit
```
Beispiel: 0
```
Express-Lieferung am selben Tag möglich

#### Express Max. Transitzeit
```
Beispiel: 1
```
Express-Lieferung spätestens am nächsten Tag

### 8. Speichern

Klicken Sie unten auf **"Änderungen speichern"**

## Vollständiges Beispiel

### Beispiel: Standard-Paketversand

```
Name: Paketversand
☑ Aktiviert
Priorität: 10

Kostentyp: Pauschal
Kosten: 5.90 €

Gewicht: Min 0 kg, Max 30 kg
Warenkorbsumme: Min 0 €, Max 500 €

Attribute: (keine)
Kategorien: (alle)

Transit Min: 2 Werktage
Transit Max: 4 Werktage

☐ Express-Option (deaktiviert)
```

### Beispiel: Sperrgut-Versand

```
Name: Sperrgutversand
☑ Aktiviert
Priorität: 20

Kostentyp: Nach Gewicht
Kosten: 3.50 € pro kg

Gewicht: Min 30 kg, Max 1000 kg
Warenkorbsumme: (keine Beschränkung)

Attribute:
  [Sperrgut ▼] = [ja]

Kategorien: Möbel, Garten

Transit Min: 5 Werktage
Transit Max: 10 Werktage

☐ Express-Option (nicht verfügbar für Sperrgut)
```

### Beispiel: Express-Versand

```
Name: Express-Versand
☑ Aktiviert
Priorität: 5

Kostentyp: Pauschal
Kosten: 12.90 €

Gewicht: Min 0 kg, Max 5 kg
Warenkorbsumme: (keine Beschränkung)

Attribute: (keine)
Kategorien: Elektronik, Kleidung

Transit Min: 0 Werktage
Transit Max: 1 Werktag

☑ Express-Option aktivieren
  Zuschlag: 9.90 €
  Cutoff: 14:00
  Min Transit: 0
  Max Transit: 1
```

## Häufige Fehler

### ❌ Versandart wird nicht angezeigt

**Ursache 1**: Checkbox "Aktiviert" nicht angehakt
**Lösung**: ☑ Versandart aktivieren

**Ursache 2**: Name ist leer
**Lösung**: Geben Sie einen Namen ein

**Ursache 3**: Bedingungen sind nicht erfüllt
**Lösung**: 
- Prüfen Sie Gewichtsgrenzen
- Prüfen Sie Warenkorbsumme
- Prüfen Sie Attribute
- Prüfen Sie Kategorien

**Ursache 4**: Änderungen nicht gespeichert
**Lösung**: Klicken Sie auf "Änderungen speichern"

### ❌ "Paketdienst" wird angezeigt

**Ursache**: Keine Versandart passt
**Lösung**: 
- Legen Sie mindestens eine Versandart ohne Bedingungen an
- Oder lockern Sie die Bedingungen

### ❌ Felder sind verschwunden

**Ursache**: Sanitize-Callback hat Daten gelöscht (in Version 1.0.3)
**Lösung**: 
- Aktualisieren Sie auf Version 1.0.4
- Konfigurieren Sie die Versandarten neu

## Tipps

### Tipp 1: Standard-Versandart

Legen Sie immer eine Standard-Versandart **ohne Bedingungen** an:
```
Name: Standard-Versand
☑ Aktiviert
Priorität: 99 (niedrig)
Kosten: 5.90 €
Bedingungen: (keine)
```

Diese wird angezeigt, wenn keine andere Versandart passt.

### Tipp 2: Prioritäten nutzen

```
Priorität 5:  Express-Versand (wird bevorzugt)
Priorität 10: Standard-Versand
Priorität 20: Sperrgut-Versand
Priorität 99: Fallback-Versand
```

### Tipp 3: Bedingungen kombinieren

Sie können mehrere Bedingungen kombinieren:
```
Gewicht: 0-5 kg
UND
Warenkorbsumme: 0-100 €
UND
Kategorie: Elektronik
```

Alle Bedingungen müssen erfüllt sein!

### Tipp 4: Attribute vs. Kategorien

**Attribute**: Für produktspezifische Eigenschaften
```
Sperrgut = ja
Farbe = rot
Material = Holz
```

**Kategorien**: Für Produktgruppen
```
Elektronik
Möbel
Kleidung
```

## Support

Bei Problemen:
1. Lesen Sie DEBUG.md
2. Aktivieren Sie WP_DEBUG
3. Prüfen Sie /wp-content/debug.log
4. Erstellen Sie ein GitHub Issue
