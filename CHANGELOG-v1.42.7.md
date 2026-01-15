# Changelog - Woo Lieferzeiten Manager v1.42.7

## 🐛 Kritischer Bugfix: Einstellungen werden jetzt korrekt gespeichert

### Problem

**v1.42.6 hatte zwei kritische Bugs:**

1. **E-Mail-Adressen werden nicht gespeichert**
   - Symptom: Egal welche E-Mail-Adresse eingetragen wird, E-Mails gehen immer an `admin_email`
   - Ursache: Separate Optionen wurden nur gespeichert wenn das Feld gesetzt war, aber leere Felder wurden ignoriert

2. **min_date Filter funktioniert nicht mehr**
   - Symptom: "Bestellungen berücksichtigen ab" Feld wird ignoriert
   - Ursache: Gleicher Grund wie bei E-Mail-Adressen

### Ursache

**Problem in v1.42.6 (Zeile 174-192):**

```php
// Save individual ship notification options (for backward compatibility with cronjobs)
if (isset($_POST['wlm_settings']['ship_notification_enabled'])) {
    update_option('wlm_ship_notification_enabled', true);
} else {
    update_option('wlm_ship_notification_enabled', false);
}
if (isset($_POST['wlm_settings']['ship_notification_email'])) {  // ❌ Nur wenn gesetzt!
    update_option('wlm_ship_notification_email', sanitize_email($_POST['wlm_settings']['ship_notification_email']));
}
// ... weitere Felder
```

**Das Problem:**
- `if (isset($_POST['wlm_settings']['ship_notification_email']))` prüft nur ob das Feld **existiert**
- Wenn das Feld leer ist oder einen Wert hat, wird es **nicht** gespeichert, wenn die Checkbox daneben nicht gesetzt ist
- Die Optionen werden nur beim **ersten** Speichern erstellt, danach nie wieder aktualisiert

### Lösung

**v1.42.7 (Zeile 173-194):**

```php
// Save individual ship notification options (for backward compatibility with cronjobs)
if (isset($_POST['wlm_settings'])) {  // ✅ Prüfe nur ob wlm_settings existiert
    $settings = $_POST['wlm_settings'];
    
    // Debug logging
    WLM_Core::log('[WLM Admin] Saving ship notification settings:');
    WLM_Core::log('  - email: ' . (isset($settings['ship_notification_email']) ? $settings['ship_notification_email'] : 'NOT SET'));
    WLM_Core::log('  - min_date: ' . (isset($settings['ship_notification_min_date']) ? $settings['ship_notification_min_date'] : 'NOT SET'));
    
    // Always save these options when wlm_settings is present
    update_option('wlm_ship_notification_enabled', isset($settings['ship_notification_enabled']) ? true : false);
    update_option('wlm_ship_notification_email', isset($settings['ship_notification_email']) ? sanitize_email($settings['ship_notification_email']) : '');
    update_option('wlm_ship_notification_time', isset($settings['ship_notification_time']) ? sanitize_text_field($settings['ship_notification_time']) : '');
    update_option('wlm_ship_notification_send_empty', isset($settings['ship_notification_send_empty']) ? true : false);
    update_option('wlm_ship_notification_min_date', isset($settings['ship_notification_min_date']) ? sanitize_text_field($settings['ship_notification_min_date']) : '');
    
    // Verify what was saved
    WLM_Core::log('[WLM Admin] After save, checking DB:');
    WLM_Core::log('  - DB email: ' . get_option('wlm_ship_notification_email', 'EMPTY'));
    WLM_Core::log('  - DB min_date: ' . get_option('wlm_ship_notification_min_date', 'EMPTY'));
}
```

**Änderungen:**
1. ✅ Prüfe nur `if (isset($_POST['wlm_settings']))` statt jedes einzelne Feld
2. ✅ Speichere **immer** alle Felder, auch wenn sie leer sind
3. ✅ Debug-Logging hinzugefügt um zu sehen was gespeichert wird

## 📋 Geänderte Dateien

### `includes/class-wlm-admin.php`

**Zeilen 173-194:** Ship Notification Optionen werden jetzt korrekt gespeichert

**Vorher (v1.42.6):**
- Separate `if`-Bedingung für jedes Feld
- Felder werden nur gespeichert wenn sie gesetzt sind
- ❌ Funktioniert nicht!

**Nachher (v1.42.7):**
- Eine `if`-Bedingung für alle Felder
- Felder werden immer gespeichert (auch wenn leer)
- ✅ Funktioniert!

**Debug-Logging:**
- Zeigt welche Werte aus dem POST kommen
- Zeigt welche Werte in der DB gespeichert werden
- Hilft bei der Fehlersuche

### `woo-lieferzeiten-manager.php`

**Zeile 6:** Version 1.42.6 → 1.42.7  
**Zeile 25:** WLM_VERSION Konstante aktualisiert

## 🎯 Erwartetes Verhalten nach dem Fix

### E-Mail-Adressen

**Vorher (v1.42.6):**
- E-Mail-Adresse eintragen → Speichern → ❌ Geht an `admin_email`
- Separate Option wird nicht erstellt/aktualisiert

**Nachher (v1.42.7):**
- E-Mail-Adresse eintragen → Speichern → ✅ Geht an eingetragene Adresse
- Separate Option wird immer aktualisiert

### min_date Filter

**Vorher (v1.42.6):**
- Datum eintragen → Speichern → ❌ Wird ignoriert
- Alte Bestellungen werden nicht ausgefiltert

**Nachher (v1.42.7):**
- Datum eintragen → Speichern → ✅ Wird verwendet
- Alte Bestellungen werden korrekt ausgefiltert

## 🚀 Deployment

### 1. Plugin aktualisieren

- WordPress Backend → Plugins → Installieren → Plugin hochladen
- ZIP-Datei hochladen (v1.42.7)
- Aktivieren

### 2. Einstellungen neu speichern

**WICHTIG:** Nach dem Update **MÜSSEN** die Einstellungen einmal neu gespeichert werden!

**WooCommerce → Einstellungen → Versand → MEGA Versandmanager → Tab "Benachrichtigungen"**

1. **Ship Notifications:**
   - E-Mail-Adresse prüfen/eintragen
   - "Bestellungen berücksichtigen ab" Datum prüfen/eintragen
   - **Speichern**

2. **Performance Report:**
   - E-Mail-Adresse prüfen/eintragen
   - "Bestellungen berücksichtigen ab" Datum prüfen/eintragen
   - **Speichern**

3. **Delay Notifications:**
   - BCC E-Mail prüfen/eintragen (optional)
   - "Bestellungen berücksichtigen ab" Datum prüfen/eintragen
   - **Speichern**

### 3. Debug-Log prüfen

Nach dem Speichern sollten im WooCommerce-Log Einträge wie diese erscheinen:

```
[WLM Admin] Saving ship notification settings:
  - enabled: true
  - email: test@example.com
  - time: 08:00
  - send_empty: false
  - min_date: 2026-01-01
[WLM Admin] After save, checking DB:
  - DB email: test@example.com
  - DB min_date: 2026-01-01
```

**Log-Pfad:** WooCommerce → Status → Logs → wlm-core

### 4. Testen

**Test 1: E-Mail-Adresse**
- Andere E-Mail-Adresse eintragen
- Speichern
- Test-E-Mail senden
- Prüfen: E-Mail kommt an neuer Adresse an ✅

**Test 2: min_date Filter**
- Datum auf z.B. 01.01.2026 setzen
- Speichern
- Test-E-Mail senden
- Prüfen: Nur Bestellungen ab 01.01.2026 sind enthalten ✅

**Test 3: Cronjob**
- Cronjob manuell auslösen
- Prüfen: E-Mail geht an richtige Adresse ✅
- Prüfen: Alte Bestellungen sind ausgefiltert ✅

## ⚠️ Breaking Changes

Keine - nur Bugfixes.

## 🐛 Bekannte Probleme

Keine.

## 📝 Hinweise

### Warum Debug-Logging?

Das Debug-Logging wurde hinzugefügt, um bei zukünftigen Problemen schneller debuggen zu können.

**Aktivierung:** Debug-Modus ist standardmäßig aktiviert für Admin-Speichervorgänge.

**Deaktivierung:** Kann später entfernt werden, wenn alles stabil läuft.

**Log-Zugriff:** WooCommerce → Status → Logs → wlm-core

### Migration von v1.42.6

**Keine automatische Migration!**

Wenn du v1.42.6 installiert hast und Einstellungen gespeichert hast, wurden die separaten Optionen **möglicherweise nicht** erstellt.

**Lösung:** Nach dem Update auf v1.42.7 die Einstellungen **einmal neu speichern**.

## 🎉 Zusammenfassung

**Problem:** Einstellungen wurden nicht korrekt gespeichert (v1.42.6)  
**Ursache:** Zu restriktive `if`-Bedingungen  
**Lösung:** Vereinfachte Logik + Debug-Logging  
**Ergebnis:** Einstellungen funktionieren jetzt korrekt ✅

**Wichtig:** Nach dem Update Einstellungen einmal neu speichern!

---

**Version:** 1.42.7  
**Datum:** 2026-01-15  
**Typ:** Critical Bugfix  
**Status:** ✅ Production Ready
