# Update auf Version 1.12.0 - Verbesserte Conditions UI

## 🎨 Neue Features

### Produktattribute/Taxonomien UI

Die Bedingungen für Produktattribute und Taxonomien wurden komplett überarbeitet und bieten jetzt eine **professionelle, benutzerfreundliche Oberfläche** ähnlich wie bei Premium-Plugins:

#### ✅ Select2 Multiselect mit Chips
- **Chip-basierte Mehrfachauswahl** für Attributwerte
- Visuell ansprechende Tags mit × zum Entfernen
- Autocomplete-Funktion für schnelle Auswahl
- Unterstützt auch manuelle Eingabe (Tags)

#### ✅ Logik-Operatoren
- **at least one of** - Mindestens einer der Werte muss vorhanden sein
- **all of** - Alle Werte müssen vorhanden sein
- **none of** - Keiner der Werte darf vorhanden sein
- **only** - Nur die angegebenen Werte (und keine anderen)

#### ✅ Mehrere Bedingungen
- **"+ Bedingung hinzufügen"** Button
- **"Entfernen"** Button pro Bedingung
- Unbegrenzte Anzahl an Bedingungen pro Versandart
- Card-basiertes Design für bessere Übersicht

## 📸 Beispiel-Workflow

### Szenario: Versandart nur für bestimmte Versandgruppen

1. **Versandart öffnen** in WooCommerce → Einstellungen → Versand → WLM Versandarten
2. **Bedingung hinzufügen** klicken
3. **Logik-Operator** wählen: "at least one of"
4. **Attribut** wählen: "Versandgruppe"
5. **Werte auswählen**: "Musterversand", "Paketgut" (als Chips)
6. **Weitere Bedingung hinzufügen** (optional)
7. **Speichern**

## 🔧 Technische Details

### Kompatibilität
- ✅ **Keine Breaking Changes** - Bestehende Konfigurationen funktionieren weiter
- ✅ **Automatische Migration** - Alte Formate werden automatisch konvertiert
- ✅ **Abwärtskompatibel** - Alte Logik bleibt erhalten

### Neue Datenstruktur

```php
'attribute_conditions' => [
    [
        'logic' => 'at_least_one',  // Operator
        'attribute' => 'pa_versandgruppe',  // Attribut-Slug
        'values' => ['musterversand', 'paketgut']  // Array von Werten
    ],
    [
        'logic' => 'none',
        'attribute' => 'product_cat',
        'values' => ['sonderposten']
    ]
]
```

### JavaScript Integration
- **Select2** wird von WooCommerce bereitgestellt (keine zusätzlichen Dependencies)
- **AJAX-basiertes Laden** der verfügbaren Attributwerte
- **Dynamische Initialisierung** bei neuen Bedingungen

### Backend-Validierung
- Automatische Filterung leerer Bedingungen
- Validierung der Datenstruktur beim Speichern
- Saubere Array-Normalisierung
- Debug-Logging für Troubleshooting

## 🚀 Update-Prozess

### Automatisch
1. Plugin-Dateien aktualisieren
2. WordPress-Admin aufrufen
3. **Fertig!** - Keine manuellen Schritte nötig

### Was passiert beim Update?
- Bestehende Bedingungen werden automatisch konvertiert
- Alte Formate (`required_attributes` String) werden in neue Struktur überführt
- Default-Logik wird auf `at_least_one` gesetzt
- Keine Daten gehen verloren

## 📋 Checkliste nach Update

- [ ] WooCommerce → Einstellungen → Versand → WLM Versandarten öffnen
- [ ] Bestehende Versandarten prüfen
- [ ] Neue UI testen (Bedingung hinzufügen/entfernen)
- [ ] Select2 Multiselect testen
- [ ] Logik-Operatoren ausprobieren
- [ ] Testbestellung durchführen
- [ ] Versandarten werden korrekt angezeigt/ausgeblendet

## 🐛 Troubleshooting

### Select2 wird nicht geladen
**Lösung:** Browser-Cache leeren und Seite neu laden

### Werte werden nicht angezeigt
**Lösung:** Prüfen ob Attribut korrekt gewählt ist, dann automatisch geladen

### Alte Bedingungen fehlen
**Lösung:** Debug-Log prüfen (`wp-content/debug.log`), automatische Migration sollte erfolgen

## 📚 Weitere Ressourcen

- **CHANGELOG.md** - Vollständige Änderungsliste
- **REST-API-DOCUMENTATION.md** - API-Dokumentation
- **ERP-INTEGRATION-GUIDE.md** - ERP-Integration

## 💡 Tipps

### Best Practices
1. **Logik-Operatoren richtig wählen:**
   - `at_least_one` für flexible Bedingungen
   - `all` für strikte Anforderungen
   - `none` für Ausschlüsse
   - `only` für exklusive Bedingungen

2. **Mehrere Bedingungen kombinieren:**
   - Alle Bedingungen müssen erfüllt sein (UND-Verknüpfung)
   - Für ODER-Verknüpfung: Mehrere Werte in einer Bedingung

3. **Testen vor Produktiv-Einsatz:**
   - Testbestellungen mit verschiedenen Produkten
   - Verschiedene Attribut-Kombinationen prüfen

## 📞 Support

Bei Fragen oder Problemen:
- **Debug-Logging aktivieren** in `wp-config.php`: `define('WP_DEBUG_LOG', true);`
- **Log-Datei prüfen**: `wp-content/debug.log`
- **Prefix beachten**: `[WLM]` für Plugin-spezifische Einträge

---

**Version:** 1.12.0  
**Release-Datum:** 14. November 2025  
**Kompatibilität:** WordPress 6.0+, WooCommerce 8.0+, PHP 7.4+
