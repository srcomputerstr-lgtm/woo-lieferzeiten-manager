# Changelog - Woo Lieferzeiten Manager v1.42.11

## 🚨 EMERGENCY HOTFIX - Checkout Crash behoben!

### Problem

**KRITISCHER FEHLER auf der Kassen-Seite:**

```
Uncaught TypeError: Unsupported operand types: array * string 
in class-wlm-calculator.php:516
```

**Auswirkung:**
- ❌ Kunden können nicht bestellen
- ❌ Checkout-Prozess bricht ab
- ❌ Shop ist faktisch nicht nutzbar

**Betroffene Seite:** Checkout (Kasse)

### Ursache

**Zeile 516 in `class-wlm-calculator.php`:**

```php
// VORHER - FEHLER ❌
$product_price = $product->get_price() * $quantity;
```

**Das Problem:**
- `$product->get_price()` gibt manchmal ein **Array** zurück statt einem numerischen Wert
- PHP kann kein Array mit einem String multiplizieren
- TypeError wird geworfen und Checkout bricht ab

**Wann passiert das?**
- Variable Produkte ohne ausgewählte Variation
- Produkte mit fehlerhaften Preisdaten
- Bestimmte Product-Types (z.B. Grouped Products)
- Produkte mit komplexen Preis-Plugins

### Lösung

**v1.42.11 (Zeile 516-524):**

```php
// NACHHER - SICHER ✅
$price = $product->get_price();

// Safety check: Ensure price is numeric (not array or other type)
if (!is_numeric($price)) {
    WLM_Core::log('[WLM Calculator] Warning: Product price is not numeric (ID: ' . $product->get_id() . ', Type: ' . gettype($price) . '). Skipping price check.');
    $product_price = 0; // Skip price validation if price is invalid
} else {
    $product_price = floatval($price) * $quantity;
}
```

**Änderungen:**
1. ✅ Prüft ob Preis numerisch ist mit `is_numeric()`
2. ✅ Loggt Warnung wenn Preis ungültig ist (mit Produkt-ID und Typ)
3. ✅ Setzt `$product_price = 0` wenn Preis ungültig (überspringt Preis-Validierung)
4. ✅ Konvertiert zu `floatval()` vor Multiplikation wenn gültig

**Warum `$product_price = 0`?**
- Wenn der Preis ungültig ist, können wir die Preis-basierte Versandmethoden-Validierung nicht durchführen
- Durch Setzen auf 0 wird die Validierung übersprungen (min/max checks schlagen fehl)
- Das Produkt kann trotzdem in den Warenkorb und bestellt werden
- Besser als Checkout-Crash!

## 📋 Geänderte Dateien

### `includes/class-wlm-calculator.php`

**Zeilen 516-524:** Type-Safe Preis-Berechnung mit Validierung

**Vorher:**
```php
$product_price = $product->get_price() * $quantity;
```

**Nachher:**
```php
$price = $product->get_price();

if (!is_numeric($price)) {
    WLM_Core::log('[WLM Calculator] Warning: Product price is not numeric...');
    $product_price = 0;
} else {
    $product_price = floatval($price) * $quantity;
}
```

### `woo-lieferzeiten-manager.php`

**Zeile 6:** Version 1.42.10 → 1.42.11  
**Zeile 25:** WLM_VERSION Konstante aktualisiert

## 🎯 Erwartetes Verhalten nach dem Fix

### Checkout

**v1.42.10:** ❌ Crash mit TypeError bei bestimmten Produkten  
**v1.42.11:** ✅ Checkout funktioniert, auch wenn Preis ungültig ist

### Logging

**Wenn ungültiger Preis erkannt wird:**
```
[WLM Calculator] Warning: Product price is not numeric (ID: 12345, Type: array). Skipping price check.
```

**Das hilft bei der Diagnose:**
- Welches Produkt hat das Problem?
- Welcher Typ wird zurückgegeben?
- Kann dann manuell gefixt werden

## 🚀 Deployment

### SOFORT INSTALLIEREN! 🚨

**WordPress Backend → Plugins → Installieren → Plugin hochladen**

1. ZIP-Datei hochladen (v1.42.11)
2. Aktivieren
3. **FERTIG!**

### Testen

1. **Checkout testen:**
   - Produkt in den Warenkorb
   - Zur Kasse gehen
   - Prüfen: Kein Fehler mehr ✅

2. **Logs prüfen (optional):**
   - WooCommerce → Status → Logs → wlm-core
   - Prüfen ob Warnungen über ungültige Preise erscheinen
   - Wenn ja: Betroffene Produkte manuell prüfen

## ⚠️ Breaking Changes

Keine - nur Bugfix.

## 🐛 Bekannte Probleme

Keine.

## 📝 Warum ist das passiert?

**Die Ursache:**
- `$product->get_price()` ist in WooCommerce nicht type-safe
- Bei bestimmten Produkttypen oder Zuständen gibt es ein Array zurück
- Der Code hatte keine Validierung

**Die Lektion:**
- IMMER Type-Checking bei WooCommerce-Methoden
- NIEMALS davon ausgehen dass `get_price()` einen numerischen Wert zurückgibt
- Defensive Programmierung ist wichtig!

## 🎉 Zusammenfassung

**Problem:** Checkout-Crash durch ungültigen Produktpreis-Typ  
**Ursache:** Keine Type-Validierung vor Multiplikation  
**Lösung:** `is_numeric()` Check mit Fallback  
**Ergebnis:** Checkout funktioniert wieder ✅

**Priorität:** 🚨 KRITISCH - SOFORT INSTALLIEREN!

**Status:** ✅ Production Ready - Emergency Hotfix

---

**Version:** 1.42.11  
**Datum:** 2026-01-15  
**Typ:** Emergency Hotfix (Critical)  
**Status:** 🚨 SOFORT INSTALLIEREN!
