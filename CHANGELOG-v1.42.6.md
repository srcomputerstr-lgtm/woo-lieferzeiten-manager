# Changelog - Woo Lieferzeiten Manager v1.42.6

## 🐛 Kritischer Bugfix: E-Mail-Einstellungen werden jetzt korrekt gespeichert

### Problem

**Symptom:** E-Mail-Adressen für Benachrichtigungen wurden im Backend geändert, aber Cronjobs sendeten E-Mails immer noch an die ursprüngliche Adresse.

**Ursache:** Inkonsistente Speicherung und Abruf von Einstellungen:

1. **Ship Notifications:**
   - Gespeichert als: `wlm_settings[ship_notification_email]` (im Array)
   - Abgerufen als: `get_option('wlm_ship_notification_email')` (separate Option)
   - ❌ **Inkonsistent!**

2. **Performance Report:**
   - Gespeichert als: `wlm_performance_report_email` (separate Option)
   - Abgerufen als: `get_option('wlm_performance_report_email')`
   - ✅ **Konsistent!**

3. **Delay Notification:**
   - BCC-Funktionalität fehlte komplett
   - ❌ **Nicht implementiert!**

### Lösung

**1. Ship Notifications - Doppelte Speicherung (Zeile 173-192 in class-wlm-admin.php)**

Alle Ship Notification Einstellungen werden jetzt **zusätzlich** als separate Optionen gespeichert:

```php
// Save individual ship notification options (for backward compatibility with cronjobs)
if (isset($_POST['wlm_settings']['ship_notification_enabled'])) {
    update_option('wlm_ship_notification_enabled', true);
} else {
    update_option('wlm_ship_notification_enabled', false);
}
if (isset($_POST['wlm_settings']['ship_notification_email'])) {
    update_option('wlm_ship_notification_email', sanitize_email($_POST['wlm_settings']['ship_notification_email']));
}
if (isset($_POST['wlm_settings']['ship_notification_time'])) {
    update_option('wlm_ship_notification_time', sanitize_text_field($_POST['wlm_settings']['ship_notification_time']));
}
if (isset($_POST['wlm_settings']['ship_notification_send_empty'])) {
    update_option('wlm_ship_notification_send_empty', true);
} else {
    update_option('wlm_ship_notification_send_empty', false);
}
if (isset($_POST['wlm_settings']['ship_notification_min_date'])) {
    update_option('wlm_ship_notification_min_date', sanitize_text_field($_POST['wlm_settings']['ship_notification_min_date']));
}
```

**Warum doppelte Speicherung?**
- Im `wlm_settings` Array für UI-Kompatibilität
- Als separate Optionen für Cronjob-Kompatibilität
- Konsistent mit Performance Report

**2. Ship Notifications - min_date Fix (Zeile 129 in class-wlm-ship-notifications.php)**

```php
// VORHER
$settings = get_option('wlm_settings', array());
$min_date = $settings['ship_notification_min_date'] ?? '';

// NACHHER
$min_date = get_option('wlm_ship_notification_min_date', '');
```

**3. Delay Notification - BCC Support (Zeile 171-175 in class-wlm-delay-notification.php)**

```php
// Add BCC if configured
$bcc_email = get_option('wlm_delay_notification_bcc_email', '');
if (!empty($bcc_email) && is_email($bcc_email)) {
    $headers[] = 'Bcc: ' . $bcc_email;
}
```

## 📋 Geänderte Dateien

### `includes/class-wlm-admin.php`

**Zeilen 173-192:** Ship Notification Einstellungen werden zusätzlich als separate Optionen gespeichert

**Gespeicherte Optionen:**
- `wlm_ship_notification_enabled`
- `wlm_ship_notification_email`
- `wlm_ship_notification_time`
- `wlm_ship_notification_send_empty`
- `wlm_ship_notification_min_date`

### `includes/class-wlm-ship-notifications.php`

**Zeile 129:** `min_date` wird jetzt aus separater Option gelesen

**Vorher:**
```php
$settings = get_option('wlm_settings', array());
$min_date = $settings['ship_notification_min_date'] ?? '';
```

**Nachher:**
```php
$min_date = get_option('wlm_ship_notification_min_date', '');
```

### `includes/class-wlm-delay-notification.php`

**Zeilen 171-175:** BCC-Support hinzugefügt

**Neu:**
```php
// Add BCC if configured
$bcc_email = get_option('wlm_delay_notification_bcc_email', '');
if (!empty($bcc_email) && is_email($bcc_email)) {
    $headers[] = 'Bcc: ' . $bcc_email;
}
```

### `woo-lieferzeiten-manager.php`

**Zeile 6:** Version 1.42.5 → 1.42.6  
**Zeile 25:** WLM_VERSION Konstante aktualisiert

## 🎯 Erwartetes Verhalten nach dem Fix

### Ship Notifications

**Vorher:**
- E-Mail-Adresse im Backend ändern → ❌ Cronjob sendet an alte Adresse
- `min_date` wird nicht korrekt gefiltert

**Nachher:**
- E-Mail-Adresse im Backend ändern → ✅ Cronjob sendet an neue Adresse
- `min_date` filtert korrekt

### Performance Report

**Vorher:** ✅ Funktionierte bereits korrekt

**Nachher:** ✅ Funktioniert weiterhin korrekt

### Delay Notification

**Vorher:**
- Keine BCC-Funktionalität
- ❌ Controlling-Abteilung erhält keine Kopie

**Nachher:**
- BCC-E-Mail kann konfiguriert werden
- ✅ Controlling erhält automatisch Kopie aller Verzögerungs-E-Mails

## 🚀 Deployment

### 1. Plugin aktualisieren

- WordPress Backend → Plugins → Installieren → Plugin hochladen
- ZIP-Datei hochladen (v1.42.6)
- Aktivieren

### 2. Einstellungen prüfen

**WooCommerce → Einstellungen → Versand → MEGA Versandmanager → Tab "Benachrichtigungen"**

**Ship Notifications:**
- E-Mail-Adresse prüfen und ggf. neu eingeben
- Speichern

**Performance Report:**
- E-Mail-Adresse prüfen (sollte bereits korrekt sein)

**Delay Notifications:**
- BCC E-Mail-Adresse eingeben (optional)
- Speichern

### 3. Testen

**Test 1: Ship Notifications**
- E-Mail-Adresse ändern
- Test-E-Mail senden
- Prüfen: E-Mail kommt an neuer Adresse an ✅

**Test 2: Performance Report**
- E-Mail-Adresse ändern (falls nötig)
- Test-Report senden
- Prüfen: E-Mail kommt an ✅

**Test 3: Delay Notifications**
- BCC E-Mail-Adresse eintragen
- Test-Benachrichtigung senden
- Prüfen: BCC-Empfänger erhält Kopie ✅

## ⚠️ Breaking Changes

Keine - nur Bugfixes und neue Features.

## 🐛 Bekannte Probleme

Keine.

## 📝 Hinweise

### Doppelte Speicherung

Die Ship Notification Einstellungen werden jetzt **doppelt** gespeichert:

1. **Im `wlm_settings` Array** - für UI-Kompatibilität
2. **Als separate Optionen** - für Cronjob-Kompatibilität

Das ist gewollt und notwendig, um Abwärtskompatibilität zu gewährleisten.

### Migration

**Keine Migration nötig!** 

Beim ersten Speichern nach dem Update werden die Einstellungen automatisch als separate Optionen gespeichert.

**Empfehlung:** Nach dem Update einmal die Benachrichtigungs-Einstellungen öffnen und speichern, auch wenn nichts geändert wird.

## 🎉 Zusammenfassung

**Problem:** E-Mail-Einstellungen wurden nicht korrekt gespeichert/abgerufen  
**Ursache:** Inkonsistente Speicherung (Array vs. separate Optionen)  
**Lösung:** Doppelte Speicherung für Kompatibilität  
**Ergebnis:** E-Mail-Einstellungen funktionieren jetzt korrekt ✅

**Bonus:** BCC-Support für Delay Notifications hinzugefügt ✅

---

**Version:** 1.42.6  
**Datum:** 2026-01-15  
**Typ:** Bugfix + Feature  
**Status:** ✅ Production Ready
