# Changelog v1.42.3

## Änderungen

### ✅ Settings UI komplett überarbeitet

**Problem:** Die neuen Einstellungen für "Bestellungen berücksichtigen ab" und "Verzögerungs-Benachrichtigungen" wurden nicht im Backend angezeigt.

**Lösung:** 
- Settings werden jetzt direkt in der View-Datei `admin/views/tab-notifications.php` gerendert (nicht über Filter)
- Alle 3 Benachrichtigungssysteme haben jetzt das Feld "Bestellungen berücksichtigen ab"
- Verzögerungs-Benachrichtigungen Sektion komplett hinzugefügt

### 📋 Neue Einstellungen im Tab "Benachrichtigungen"

#### 1. Versandbenachrichtigungen
- ✅ **Bestellungen berücksichtigen ab** (Datumsfeld)
  - Filtert alte Bestellungen mit falschen Ship-By-Dates aus
  - Leer lassen = alle Bestellungen

#### 2. Performance Report  
- ✅ **Bestellungen berücksichtigen ab** (Datumsfeld)
  - Filtert alte Bestellungen aus dem Report
  - Leer lassen = alle Bestellungen

#### 3. Verzögerungs-Benachrichtigungen (NEU)
- ✅ **Verzögerungs-Benachrichtigungen aktivieren** (Toggle)
- ✅ **Bestellungen berücksichtigen ab** (Datumsfeld)
- ✅ **Verzögerung in Tagen** (Zahl, Standard: 1)
  - Anzahl Tage nach Ship-By-Date, bevor Benachrichtigung gesendet wird
- ✅ **BCC E-Mail-Adresse** (Optional)
  - Für Controlling-Kopien aller Verzögerungs-Mails
- ✅ **Externe Cronjob-URL** mit Anleitung für All-Inkl
- ✅ **Test-Benachrichtigung senden** Button

### 🔧 Backend-Änderungen

#### `includes/class-wlm-admin.php`
- ✅ AJAX-Handler `ajax_send_test_delay_notification()` hinzugefügt
- ✅ Speichern der Performance Report Einstellungen:
  - `wlm_performance_report_enabled`
  - `wlm_performance_report_email`
  - `wlm_performance_report_min_date` ⭐ NEU
  - `wlm_performance_report_send_empty`
- ✅ Speichern der Delay Notification Einstellungen:
  - `wlm_delay_notification_enabled`
  - `wlm_delay_notification_min_date` ⭐ NEU
  - `wlm_delay_notification_days`
  - `wlm_delay_notification_bcc`

#### `includes/class-wlm-delay-notification.php`
- ✅ `trigger_manual()` Methode hinzugefügt für Test-Button
- ✅ `min_date` Filter bereits implementiert (Zeile 74-90)
- ✅ Berücksichtigt nur Bestellungen mit Status "processing"

#### `includes/class-wlm-ship-notifications.php`
- ✅ `min_date` Filter bereits implementiert (Zeile 129-132)

#### `includes/class-wlm-performance-report.php`
- ✅ `min_date` Filter bereits implementiert (Zeile 120-137)

#### `admin/views/tab-notifications.php`
- ✅ Feld "Bestellungen berücksichtigen ab" zu Versandbenachrichtigungen hinzugefügt
- ✅ Feld "Bestellungen berücksichtigen ab" zu Performance Report hinzugefügt
- ✅ Komplette Verzögerungs-Benachrichtigungen Sektion hinzugefügt mit:
  - Aktivierungs-Toggle
  - Datumsfilter
  - Verzögerungs-Tage Einstellung
  - BCC-Adresse
  - Cronjob-URL mit Anleitung
  - Test-Button mit AJAX-Handler

### 🎯 Funktionsweise

**Datumsfilter "Bestellungen berücksichtigen ab":**
- Filtert Bestellungen nach Bestelldatum (nicht Versanddatum)
- Verhindert, dass alte Bestellungen mit falschen Ship-By-Dates in Benachrichtigungen/Reports auftauchen
- Wird in allen 3 Systemen konsistent verwendet
- Leer lassen = keine Filterung

**Verzögerungs-Benachrichtigungen:**
- Prüft täglich alle Bestellungen mit Status "processing"
- Vergleicht Ship-By-Date mit aktuellem Datum
- Sendet E-Mail an Kunden, wenn Ship-By-Date + Verzögerungstage überschritten
- Verwendet WooCommerce E-Mail-Template für Shop-Design-Konsistenz
- Speichert in Order Meta, dass Benachrichtigung gesendet wurde (keine Duplikate)

### 📊 Technische Details

**Datenbankfelder (wp_options):**
- `wlm_settings[ship_notification_min_date]` - Datumsfilter für Versandbenachrichtigungen
- `wlm_performance_report_min_date` - Datumsfilter für Performance Report
- `wlm_delay_notification_enabled` - Aktivierung Verzögerungs-Benachrichtigungen
- `wlm_delay_notification_min_date` - Datumsfilter für Verzögerungs-Benachrichtigungen
- `wlm_delay_notification_days` - Verzögerung in Tagen (Standard: 1)
- `wlm_delay_notification_bcc` - BCC E-Mail-Adresse
- `wlm_delay_notification_cron_key` - Sicherheitsschlüssel für Cronjob

**Order Meta:**
- `_wlm_delay_notification_count` - Anzahl gesendeter Verzögerungs-Benachrichtigungen
- `_wlm_last_delay_notification` - Datum der letzten Benachrichtigung

### 🚀 Deployment

1. Plugin auf v1.42.3 aktualisieren
2. Im Backend: WooCommerce → Einstellungen → Versand → MEGA Versandmanager → Tab "Benachrichtigungen"
3. Alle 3 Bereiche prüfen:
   - Versandbenachrichtigungen: Datumsfilter setzen (z.B. 01.01.2025)
   - Performance Report: Datumsfilter setzen
   - Verzögerungs-Benachrichtigungen: Aktivieren und konfigurieren
4. Cronjob-URL für Verzögerungs-Benachrichtigungen in All-Inkl einrichten (täglich um 10:00)
5. Test-Buttons verwenden zum Testen

### ⚠️ Breaking Changes

Keine - alle bestehenden Funktionen bleiben erhalten.

### 🐛 Bekannte Probleme

Keine.

---

**Version:** 1.42.3  
**Datum:** 2025-01-08  
**Status:** ✅ Bereit für Deployment
