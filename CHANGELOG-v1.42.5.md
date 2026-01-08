# Changelog v1.42.5

## 📝 Text-Updates: Wöchentlich → Täglich

### Problem
Der Performance Report wurde bereits auf **täglich** (gestern's Daten) umgestellt, aber die E-Mail-Texte und UI-Beschreibungen zeigten noch:
- "Wöchentliche Versandleistung KW 02"
- "jeden Montag"
- "letzten 7 Tage"

Das war verwirrend für Benutzer.

### Lösung
Alle Texte wurden von "wöchentlich" auf "täglich" aktualisiert.

## 📋 Geänderte Texte

### E-Mail-Header (`class-wlm-performance-report.php`)

**Vorher:**
```
📊 Performance Report
Wöchentliche Versandleistung KW 02
```

**Nachher:**
```
📊 Performance Report
Tägliche Versandleistung vom 07.01.2025
```

### Backend UI (`admin/views/tab-notifications.php`)

**Vorher:**
- "📊 Wöchentlicher Performance Report"
- "Erhalten Sie jeden Montag einen automatischen Report mit KPIs zur Versandleistung der letzten 7 Tage"
- "Aktiviert wöchentliche Performance Reports"
- "E-Mail-Adresse für den wöchentlichen Performance Report"
- "Wähle 'Wöchentlich' und stelle Montag 08:00 ein"
- "Empfehlung: Jeden Montag um 08:00 Uhr für Wochenstart-Briefing"
- "Der wöchentliche Report enthält..."
- "Alle versendeten Bestellungen der letzten 7 Tage"
- "Report analysiert nur abgeschlossene Bestellungen der letzten 7 Tage"

**Nachher:**
- "📊 Täglicher Performance Report"
- "Erhalten Sie täglich einen automatischen Report mit KPIs zur Versandleistung des Vortages"
- "Aktiviert tägliche Performance Reports"
- "E-Mail-Adresse für den täglichen Performance Report"
- "Wähle 'Täglich' und stelle die Uhrzeit ein (z.B. 08:00)"
- "Empfehlung: Täglich um 08:00 Uhr für morgendliches Briefing"
- "Der tägliche Report enthält..."
- "Alle versendeten Bestellungen vom Vortag"
- "Report analysiert nur abgeschlossene Bestellungen vom Vortag"

### Code-Kommentare und Funktionsnamen

**Vorher:**
```php
/**
 * Weekly Performance Report
 * Sends weekly KPI email about shipping performance
 */
public function send_weekly_report() {
    // Get last 7 days of data
    $stats = $this->get_weekly_stats();
}
```

**Nachher:**
```php
/**
 * Daily Performance Report
 * Sends daily KPI email about shipping performance (yesterday's data)
 */
public function send_daily_report() {
    // Get yesterday's data
    $stats = $this->get_daily_stats();
}
```

### Log-Meldungen

**Vorher:**
- "Generating weekly performance report for..."
- "No completed orders in the last 7 days"

**Nachher:**
- "Generating daily performance report for..."
- "No completed orders yesterday"

## 📦 Geänderte Dateien

### `includes/class-wlm-performance-report.php`
- Datei-Header: "Weekly" → "Daily"
- Funktionsname: `send_weekly_report()` → `send_daily_report()`
- Funktionsname: `get_weekly_stats()` → `get_daily_stats()`
- E-Mail-Header: "Wöchentliche Versandleistung KW XX" → "Tägliche Versandleistung vom DD.MM.YYYY"
- Alle Kommentare und Log-Meldungen aktualisiert

### `admin/views/tab-notifications.php`
- Überschrift: "Wöchentlicher" → "Täglicher"
- Alle Beschreibungstexte aktualisiert
- Cronjob-Anleitung: "Wöchentlich/Montag" → "Täglich"
- KPI-Beschreibung: "letzten 7 Tage" → "vom Vortag"

## ✅ Funktionalität

**Keine Änderungen an der Funktionalität:**
- Report wird weiterhin täglich mit gestern's Daten erstellt
- Nur die **Texte** wurden angepasst für Konsistenz

## 🚀 Deployment

1. **Plugin auf v1.42.5 aktualisieren**
2. **Keine Einstellungsänderungen nötig** - nur Texte wurden angepasst
3. **Test-Report senden** um die neuen Texte zu sehen:
   - WooCommerce → Einstellungen → Versand → MEGA Versandmanager
   - Tab "Benachrichtigungen"
   - Button "Test-Report jetzt senden"
4. **E-Mail prüfen:**
   - Header sollte zeigen: "Tägliche Versandleistung vom [Datum]"
   - Keine "KW XX" Referenz mehr

## 🎯 Erwartetes Verhalten

**E-Mail-Header:**
```
📊 Performance Report
Tägliche Versandleistung vom 07.01.2025
```

**Backend-Überschrift:**
```
📊 Täglicher Performance Report
Erhalten Sie täglich einen automatischen Report mit KPIs zur Versandleistung des Vortages.
```

**Cronjob-Anleitung:**
```
Wähle "Täglich" und stelle die Uhrzeit ein (z.B. 08:00)
💡 Empfehlung: Täglich um 08:00 Uhr für morgendliches Briefing
```

## ⚠️ Breaking Changes

Keine - nur Text-Updates für Konsistenz.

## 🐛 Bekannte Probleme

Keine.

---

**Version:** 1.42.5  
**Datum:** 2025-01-08  
**Typ:** Text-Updates  
**Status:** ✅ Bereit für Deployment
