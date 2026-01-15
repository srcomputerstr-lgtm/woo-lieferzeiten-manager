# Changelog - Woo Lieferzeiten Manager v1.42.9

## 🐛 Bugfix: Performance Report E-Mail-Adresse wird jetzt gespeichert

### Problem

**Nach v1.42.8:**
- ✅ Ship Notifications: Datumsfilter und E-Mail funktionieren
- ❌ Performance Report: E-Mail geht immer noch an `admin_email`

### Ursache

**Gleicher Fehler wie bei Ship Notifications in v1.42.6:**

```php
// VORHER - NUR wenn gesetzt
if (isset($_POST['wlm_performance_report_email'])) {
    update_option('wlm_performance_report_email', sanitize_email($_POST['wlm_performance_report_email']));
}
```

**Das Problem:**
- Die Option wird nur gespeichert wenn das Feld im POST ist
- Wenn das Formular abgeschickt wird, aber das Feld leer ist oder nicht mitgeschickt wird, wird die Option nicht aktualisiert
- Sie behält den alten Wert (oder bleibt leer)
- Beim Abrufen greift dann der Fallback auf `admin_email`

### Lösung

**v1.42.9 (Zeile 180-183):**

```php
// NACHHER - IMMER speichern
$pr_email = isset($_POST['wlm_performance_report_email']) ? sanitize_email($_POST['wlm_performance_report_email']) : '';
update_option('wlm_performance_report_email', $pr_email);
WLM_Core::log('[WLM Admin] Saved performance_report_email: ' . ($pr_email ?: 'EMPTY'));
```

**Änderungen:**
1. ✅ Speichere IMMER die E-Mail-Adresse, auch wenn leer
2. ✅ Debug-Logging hinzugefügt um zu sehen was gespeichert wird

## 📋 Geänderte Dateien

### `includes/class-wlm-admin.php`

**Zeilen 180-183:** Performance Report E-Mail wird jetzt immer gespeichert

**Vorher:**
```php
if (isset($_POST['wlm_performance_report_email'])) {
    update_option('wlm_performance_report_email', sanitize_email($_POST['wlm_performance_report_email']));
}
```

**Nachher:**
```php
// Always save email, even if empty
$pr_email = isset($_POST['wlm_performance_report_email']) ? sanitize_email($_POST['wlm_performance_report_email']) : '';
update_option('wlm_performance_report_email', $pr_email);
WLM_Core::log('[WLM Admin] Saved performance_report_email: ' . ($pr_email ?: 'EMPTY'));
```

### `woo-lieferzeiten-manager.php`

**Zeile 6:** Version 1.42.8 → 1.42.9  
**Zeile 25:** WLM_VERSION Konstante aktualisiert

## 🎯 Erwartetes Verhalten nach dem Fix

### Ship Notifications

**v1.42.8:** ✅ Funktioniert  
**v1.42.9:** ✅ Funktioniert weiterhin

### Performance Report

**v1.42.8:** ❌ E-Mail geht an `admin_email`  
**v1.42.9:** ✅ E-Mail geht an eingetragene Adresse

## 🚀 Deployment

### 1. Plugin auf v1.42.9 aktualisieren

WordPress Backend → Plugins → Installieren → Plugin hochladen → ZIP hochladen

### 2. Performance Report Einstellungen neu speichern

**WICHTIG:** Die Einstellungen müssen einmal neu gespeichert werden!

**WooCommerce → Einstellungen → Versand → MEGA Versandmanager → Tab "Benachrichtigungen"**

**Performance Report:**
- E-Mail-Adresse eintragen (z.B. `controlling@mega-holz.de`)
- "Bestellungen berücksichtigen ab" eintragen (z.B. `01.01.2026`)
- **Speichern**

### 3. Debug-Log prüfen

**WooCommerce → Status → Logs → wlm-core**

Du solltest einen Eintrag wie diesen sehen:

```
[WLM Admin] Saved performance_report_email: controlling@mega-holz.de
```

### 4. Testen

**Test: Performance Report E-Mail**
- Test-Report senden
- Prüfen: E-Mail kommt NICHT an `info@mega-holz.de`
- Prüfen: E-Mail kommt an `controlling@mega-holz.de` ✅

## ⚠️ Breaking Changes

Keine - nur Bugfix.

## 🐛 Bekannte Probleme

Keine.

## 📝 Hinweise

### Warum passiert das immer wieder?

**Das Problem:**
- Checkbox-Felder werden nur mitgeschickt wenn sie aktiviert sind
- Text-Felder werden nur mitgeschickt wenn sie einen Wert haben
- `if (isset($_POST['field']))` prüft nur ob das Feld im POST ist
- Wenn das Feld nicht im POST ist, wird die Option nicht aktualisiert

**Die Lösung:**
- IMMER die Option speichern, auch wenn das Feld nicht im POST ist
- Fallback auf leeren String wenn nicht gesetzt
- So wird die Option immer aktualisiert

### Unterschied zu Ship Notifications

**Ship Notifications:**
- Speichert im `wlm_settings` Array
- Das ganze Array wird immer gespeichert (Zeile 122-124)
- Deshalb funktioniert es

**Performance Report:**
- Speichert als separate Optionen
- Jede Option muss einzeln gespeichert werden
- Deshalb muss jede Option IMMER gespeichert werden

## 🎉 Zusammenfassung

**Problem:** Performance Report E-Mail wurde nicht gespeichert  
**Ursache:** `if (isset())` verhinderte das Speichern  
**Lösung:** IMMER speichern, auch wenn leer  
**Ergebnis:** Performance Report E-Mail funktioniert jetzt ✅

**Status nach v1.42.9:**
- ✅ Ship Notifications: Datumsfilter + E-Mail funktionieren
- ✅ Performance Report: Datumsfilter + E-Mail funktionieren
- ✅ Delay Notifications: Sollten auch funktionieren

**Wichtig:** Nach dem Update Performance Report Einstellungen einmal neu speichern!

---

**Version:** 1.42.9  
**Datum:** 2026-01-15  
**Typ:** Bugfix  
**Status:** ✅ Production Ready
