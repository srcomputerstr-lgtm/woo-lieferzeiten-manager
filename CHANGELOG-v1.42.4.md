# Changelog v1.42.4 - HOTFIX

## 🐛 Kritischer Bugfix

### Problem
Der Datumsfilter "Bestellungen berücksichtigen ab" für **Versandbenachrichtigungen** funktionierte nicht. Alte Bestellungen aus Dezember wurden trotz Einstellung auf "01.01.2026" weiterhin in den E-Mails angezeigt.

### Ursache
**Inkonsistente Speicherung und Abruf:**
- Das Feld wird als `wlm_settings[ship_notification_min_date]` im Array gespeichert
- Der Code versuchte es mit `get_option('wlm_ship_notification_min_date')` abzurufen
- Das ist ein **falscher Optionsname** → Einstellung wurde nie geladen

### Lösung
✅ **Ship Notifications Klasse korrigiert** (`class-wlm-ship-notifications.php`)
- Ändert Abruf von `get_option('wlm_ship_notification_min_date')` 
- Zu korrektem Abruf aus Settings-Array: `$settings['ship_notification_min_date']`
- Jetzt konsistent mit der View-Datei

## 📋 Geänderte Dateien

### `includes/class-wlm-ship-notifications.php`
**Vorher (Zeile 129-132):**
```php
$min_date = get_option('wlm_ship_notification_min_date', '');
if (!empty($min_date)) {
    $args['date_created'] = '>=' . strtotime($min_date . ' 00:00:00');
}
```

**Nachher (Zeile 129-133):**
```php
$settings = get_option('wlm_settings', array());
$min_date = $settings['ship_notification_min_date'] ?? '';
if (!empty($min_date)) {
    $args['date_created'] = '>=' . strtotime($min_date . ' 00:00:00');
}
```

## ✅ Verifikation

**Performance Report und Delay Notifications:**
- Verwenden bereits separate Optionen (`wlm_performance_report_min_date`, `wlm_delay_notification_min_date`)
- Funktionieren korrekt ✅
- Keine Änderungen nötig

**Ship Notifications:**
- Verwendet jetzt korrekt das Settings-Array
- Datumsfilter funktioniert jetzt ✅

## 🚀 Deployment

1. **Plugin aktualisieren auf v1.42.4**
2. **Einstellung erneut prüfen:**
   - WooCommerce → Einstellungen → Versand → MEGA Versandmanager
   - Tab "Benachrichtigungen"
   - Versandbenachrichtigungen → "Bestellungen berücksichtigen ab": `01.01.2026`
   - Speichern
3. **Test-E-Mail senden:**
   - Button "Test-E-Mail jetzt senden" klicken
   - Prüfen: Nur Bestellungen ab 01.01.2026 sollten enthalten sein
   - Alte Dezember-Bestellungen sollten NICHT mehr enthalten sein

## 🎯 Erwartetes Verhalten nach Fix

**Vor dem Fix:**
- Datumsfilter wird ignoriert
- Alle Bestellungen werden angezeigt (auch aus Dezember)

**Nach dem Fix:**
- Datumsfilter wird korrekt angewendet
- Nur Bestellungen ab dem eingestellten Datum (z.B. 01.01.2026) werden angezeigt
- Alte Bestellungen mit falschen Ship-By-Dates werden ausgefiltert

## ⚠️ Breaking Changes

Keine - nur Bugfix.

## 🐛 Bekannte Probleme

Keine.

---

**Version:** 1.42.4  
**Datum:** 2025-01-08  
**Typ:** HOTFIX  
**Status:** ✅ Bereit für Deployment
