# Testing Guide für WooCommerce Lieferzeiten Manager v1.5.0

## Kritische Änderungen in v1.5.0

Version 1.5.0 implementiert eine **fundamentale Architekturänderung**:

### Vorher (v1.4.7):
- ❌ Shipping Rates wurden direkt über `woocommerce_package_rates` Filter hinzugefügt
- ❌ Rates erschienen in Debug Logs aber NICHT im Cart/Checkout DOM
- ❌ WooCommerce erkannte die Rates nicht als gültige Versandarten

### Jetzt (v1.5.0):
- ✅ Proper `WC_Shipping_Method` Klassen werden dynamisch erstellt
- ✅ Registrierung über `woocommerce_shipping_methods` Filter
- ✅ Automatisches Hinzufügen zu allen Shipping Zones
- ✅ Volle Integration in WooCommerce's Shipping System

## Test-Checkliste

### 1. Plugin-Installation/Update

```bash
# Auf WordPress-Installation
cd wp-content/plugins/
git clone https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager.git
# ODER bei bestehendem Plugin:
cd woo-lieferzeiten-manager
git pull origin main
```

**Nach dem Update:**
1. WordPress Admin → Plugins → "WooCommerce Lieferzeiten Manager" deaktivieren
2. Plugin wieder aktivieren (triggert Re-Registration der Shipping Methods)
3. Alle Caches leeren:
   - Browser-Cache (Strg+Shift+R / Cmd+Shift+R)
   - WordPress Object Cache (falls vorhanden)
   - WooCommerce Transients: WooCommerce → Status → Tools → "Clear transients"

### 2. Versandarten konfigurieren

**Navigation:** WooCommerce → Einstellungen → Versand → MEGA Versandmanager

**Test-Konfiguration erstellen:**

#### Versandart 1: Standard-Versand
- **Name:** Standard-Versand
- **Titel:** Standardversand (3-5 Werktage)
- **Kosten:** 4.90 €
- **Aktiviert:** ✓
- **Lieferzeitfenster:**
  - Von: 3 Tage
  - Bis: 5 Tage
  - Typ: Werktage

#### Versandart 2: Express-Versand
- **Name:** Express-Versand
- **Titel:** Expressversand (1-2 Werktage)
- **Kosten:** 9.90 €
- **Aktiviert:** ✓
- **Lieferzeitfenster:**
  - Von: 1 Tag
  - Bis: 2 Tage
  - Typ: Werktage

**Speichern** → Sollte Erfolgsmeldung zeigen

### 3. Shipping Zones prüfen

**Navigation:** WooCommerce → Einstellungen → Versand → Zones

**Erwartetes Verhalten:**
- ✅ Alle aktivierten WLM-Versandarten sollten **automatisch** in allen Zones erscheinen
- ✅ Method IDs sollten wie `wlm_method_1762783567431` aussehen
- ✅ Titel sollten korrekt angezeigt werden

**Falls Methoden NICHT erscheinen:**
1. Zurück zu MEGA Versandmanager
2. Versandarten erneut speichern (triggert Zone-Update)
3. Zones-Seite neu laden

### 4. Frontend-Test: Cart/Checkout

**Vorbereitung:**
1. Produkt mit Preis und Gewicht anlegen
2. Produkt in den Warenkorb legen
3. Zur Kasse gehen

**Erwartetes Verhalten:**

#### Im Warenkorb:
- ✅ Versandarten werden unter "Versand" angezeigt
- ✅ Titel sind korrekt (z.B. "Standardversand (3-5 Werktage)")
- ✅ Kosten werden angezeigt (z.B. "4,90 €")
- ✅ Lieferzeitfenster wird unter der Versandart angezeigt:
  ```
  Lieferung: 13.11.2025 - 15.11.2025
  ```

#### Im Checkout:
- ✅ Versandarten sind auswählbar (Radio Buttons)
- ✅ Titel und Kosten korrekt
- ✅ Lieferzeitfenster sichtbar
- ✅ Bei Auswahl wird Gesamtpreis korrekt aktualisiert

### 5. Debug-Logs prüfen

**WordPress Debug aktivieren:**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Debug-Logs ansehen:**
```bash
tail -f wp-content/debug.log
```

**Erwartete Log-Einträge:**

Beim Laden des Checkouts:
```
WLM: Added rate for method: wlm_method_1762783567431 - Cost: 4.9
WLM: === FINAL RATES (Priority 999) ===
WLM: Total rates: 2
WLM: Rate ID: wlm_method_1762783567431:1 - Label: Standardversand (3-5 Werktage)
WLM: Rate ID: wlm_method_1762783567432:1 - Label: Expressversand (1-2 Werktage)
```

Beim Speichern von Versandarten:
```
WLM: Added method wlm_method_1762783567431 to zone 0
WLM: Added method wlm_method_1762783567432 to zone 0
```

### 6. Bedingungen testen

**Test: Gewichtsbedingungen**
1. Versandart mit Gewicht-Bedingung erstellen:
   - Min: 0 kg
   - Max: 5 kg
2. Produkt mit 6 kg in Warenkorb → Versandart sollte NICHT erscheinen
3. Produkt mit 3 kg in Warenkorb → Versandart sollte erscheinen

**Test: Warenkorbwert-Bedingungen**
1. Versandart mit Warenkorbwert-Bedingung:
   - Min: 50 €
   - Max: 200 €
2. Produkt für 30 € → Versandart NICHT sichtbar
3. Produkt für 100 € → Versandart sichtbar

### 7. Express-Versand testen

**Vorbereitung:**
1. Versandart mit Express-Option erstellen:
   - Express aktiviert: ✓
   - Express-Aufpreis: 5.00 €
   - Express-Zeitfenster: 1-2 Tage

**Test:**
1. Zur Kasse gehen
2. Unter Versandart sollte Button erscheinen:
   ```
   ⚡ Express-Versand (+5,00 €) – Zustellung: 11.11.2025 - 12.11.2025
   ```
3. Button klicken → Express wird aktiviert
4. Versandkosten sollten sich erhöhen
5. Lieferzeitfenster sollte sich ändern

## Bekannte Probleme

### Problem: Versandarten erscheinen nicht im Checkout

**Mögliche Ursachen:**
1. **Cache nicht geleert** → Browser + WordPress + WooCommerce Caches leeren
2. **Methoden nicht in Zones** → Versandarten erneut speichern
3. **Bedingungen nicht erfüllt** → Debug-Logs prüfen
4. **Theme-Konflikt** → Mit Standard-Theme (Storefront) testen

**Debugging-Schritte:**
```bash
# 1. Debug-Logs prüfen
tail -f wp-content/debug.log

# 2. WooCommerce System Status
WooCommerce → Status → System Status → Logs

# 3. Browser-Konsole prüfen (F12)
# Suche nach JavaScript-Fehlern

# 4. Shipping Zones prüfen
WooCommerce → Einstellungen → Versand → Zones
```

### Problem: Attribute-Bedingungen funktionieren nicht

**Status:** Bekanntes Problem (wird in v1.5.1 behoben)
- Datenstruktur wird falsch gespeichert (flat keys statt nested arrays)
- Workaround: Vorerst nur Gewicht- und Warenkorbwert-Bedingungen nutzen

## Erfolgs-Kriterien

✅ **Test erfolgreich wenn:**
1. Versandarten erscheinen im Cart/Checkout DOM (nicht nur in Logs)
2. Benutzer kann Versandarten auswählen
3. Kosten werden korrekt berechnet und angezeigt
4. Lieferzeitfenster werden unter Versandarten angezeigt
5. Bedingungen (Gewicht, Warenkorbwert) funktionieren

❌ **Test fehlgeschlagen wenn:**
1. Versandarten nur in Debug-Logs aber nicht im DOM
2. Versandarten nicht auswählbar
3. Kosten werden nicht berechnet
4. Lieferzeitfenster fehlen

## Support

Bei Problemen:
1. Debug-Logs sammeln (`wp-content/debug.log`)
2. Browser-Konsole-Logs (F12 → Console)
3. WooCommerce System Status (WooCommerce → Status → System Status)
4. Issue auf GitHub erstellen: https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager/issues

## Nächste Schritte nach erfolgreichem Test

Wenn v1.5.0 funktioniert:
1. ✅ Attribute-Bedingungen-Datenstruktur fixen
2. ✅ AND/OR-Logik für Bedingungen implementieren
3. ✅ Cache-Busting für admin.js hinzufügen
4. ✅ Version auf 1.5.1 erhöhen
