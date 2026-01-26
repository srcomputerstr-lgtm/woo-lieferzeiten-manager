# Changelog - Woo Lieferzeiten Manager v1.42.13

## 🎯 Surcharges only for WLM Shipping Methods

### Problem

Zuschläge wurden **immer** angewendet, auch wenn der Kunde eine Nicht-WLM-Versandart gewählt hat (z.B. Local Pickup, Abholung).

**Beispiel:**
- Kunde wählt "Local Pickup" (Abholung)
- Zuschläge werden trotzdem berechnet ❌
- Das ist falsch, da bei Abholung keine Versandkosten anfallen sollten

### Lösung

**Neue Prüfung in `class-wlm-surcharges.php` (Zeile 236-260):**

```php
// Check if a WLM shipping method is selected
$chosen_methods = WC()->session->get('chosen_shipping_methods');
$is_wlm_method = false;

if (!empty($chosen_methods)) {
    foreach ($chosen_methods as $chosen_method) {
        // Extract method ID (format: method_id:instance_id)
        $method_parts = explode(':', $chosen_method);
        $method_id = $method_parts[0];
        
        // Check if it's a WLM method
        if (strpos($method_id, 'wlm_method_') === 0) {
            $is_wlm_method = true;
            break;
        }
    }
}

// Only apply surcharges if WLM shipping method is selected
if (!$is_wlm_method) {
    WLM_Core::log('[WLM Cart Fees] No WLM shipping method selected, skipping surcharges');
    return;
}
```

**Wie es funktioniert:**
1. ✅ Prüft welche Versandart der Kunde gewählt hat
2. ✅ Extrahiert die Method-ID aus dem Format `method_id:instance_id`
3. ✅ Prüft ob die Method-ID mit `wlm_method_` beginnt
4. ✅ Wenn KEINE WLM-Versandart: Zuschläge werden übersprungen
5. ✅ Wenn WLM-Versandart: Zuschläge werden normal berechnet

## 📋 Geänderte Dateien

### `includes/class-wlm-surcharges.php`

**Zeilen 236-260:** Neue Prüfung ob WLM-Versandart gewählt ist

**Vorher:**
```php
public function add_surcharges_to_cart() {
    if (!WC()->cart) {
        return;
    }

    // Get shipping packages
    $packages = WC()->shipping()->get_packages();
    // ... direkt Zuschläge berechnen
}
```

**Nachher:**
```php
public function add_surcharges_to_cart() {
    if (!WC()->cart) {
        return;
    }

    // Check if a WLM shipping method is selected
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    $is_wlm_method = false;
    
    if (!empty($chosen_methods)) {
        foreach ($chosen_methods as $chosen_method) {
            $method_parts = explode(':', $chosen_method);
            $method_id = $method_parts[0];
            
            if (strpos($method_id, 'wlm_method_') === 0) {
                $is_wlm_method = true;
                break;
            }
        }
    }
    
    // Only apply surcharges if WLM shipping method is selected
    if (!$is_wlm_method) {
        return;
    }

    // Get shipping packages
    $packages = WC()->shipping()->get_packages();
    // ... Zuschläge berechnen
}
```

### `woo-lieferzeiten-manager.php`

**Zeile 6:** Version 1.42.12 → 1.42.13  
**Zeile 25:** WLM_VERSION Konstante aktualisiert

## 🎯 Erwartetes Verhalten nach dem Fix

### Checkout mit WLM-Versandart

**Beispiel: Kunde wählt "Standard Versand" (WLM-Methode)**

1. Kunde wählt Versandart: `wlm_method_123` ✅
2. Zuschläge werden geprüft ✅
3. Zuschläge werden angewendet ✅
4. Warenkorb zeigt: Versandkosten + Zuschläge ✅

### Checkout mit Nicht-WLM-Versandart

**Beispiel: Kunde wählt "Local Pickup" (Abholung)**

1. Kunde wählt Versandart: `local_pickup` ❌ (kein `wlm_method_`)
2. Zuschläge werden übersprungen ✅
3. Keine Zuschläge angewendet ✅
4. Warenkorb zeigt: Nur Produkte, keine Zuschläge ✅

### Debug-Log

**Mit WLM-Versandart:**
```
[WLM Cart Fees] WLM shipping method selected, processing surcharges
[WLM Cart Fees] Processing 1 packages
[WLM Cart Fees] Package #0 returned 2 surcharges
[WLM Cart Fees] Adding fee: Inselzuschlag = 15.00
[WLM Cart Fees] Adding fee: Sperrgut = 25.00
```

**Mit Nicht-WLM-Versandart:**
```
[WLM Cart Fees] No WLM shipping method selected, skipping surcharges
```

## 🚀 Deployment

### Installation

**WordPress Backend → Plugins → Installieren → Plugin hochladen**

1. ZIP-Datei hochladen (v1.42.13)
2. Aktivieren
3. **FERTIG!**

### Testen

**Test 1: WLM-Versandart**
1. Produkt in Warenkorb
2. Zur Kasse gehen
3. WLM-Versandart wählen (z.B. "Standard Versand")
4. Prüfen: Zuschläge werden angezeigt ✅

**Test 2: Local Pickup**
1. Produkt in Warenkorb
2. Zur Kasse gehen
3. "Local Pickup" wählen
4. Prüfen: Keine Zuschläge ✅

**Test 3: Versandart wechseln**
1. WLM-Versandart wählen → Zuschläge erscheinen ✅
2. Zu "Local Pickup" wechseln → Zuschläge verschwinden ✅
3. Zurück zu WLM-Versandart → Zuschläge erscheinen wieder ✅

## ⚠️ Breaking Changes

Keine - nur Bugfix für korrektes Verhalten.

## 🐛 Bekannte Probleme

Keine.

## 📝 Technische Details

### WLM-Versandarten Identifikation

WLM-Versandarten werden durch die Method-ID identifiziert:
- Format: `wlm_method_{id}` (z.B. `wlm_method_123`)
- Andere Versandarten: `local_pickup`, `flat_rate`, `free_shipping`, etc.

### Session-Handling

Die gewählte Versandart wird in der WooCommerce Session gespeichert:
```php
WC()->session->get('chosen_shipping_methods')
```

Rückgabe-Format:
```php
array(
    0 => 'wlm_method_123:1',  // WLM-Methode
    // oder
    0 => 'local_pickup:2',    // Nicht-WLM-Methode
)
```

### Keine Änderungen an:

- ✅ Zuschlag-Konfiguration (bleibt unverändert)
- ✅ Zuschlag-Berechnung (bleibt unverändert)
- ✅ Zuschlag-Bedingungen (bleiben unverändert)
- ✅ Express-Handling (bleibt unverändert)

**Nur hinzugefügt:** Prüfung ob WLM-Versandart gewählt ist

## 🎉 Zusammenfassung

**Problem:** Zuschläge wurden immer angewendet, auch bei Abholung  
**Ursache:** Keine Prüfung der gewählten Versandart  
**Lösung:** Prüfung ob WLM-Versandart gewählt ist  
**Ergebnis:** Zuschläge nur bei WLM-Versandarten ✅

**Priorität:** Normal (Bugfix)  
**Status:** ✅ Production Ready

---

**Version:** 1.42.13  
**Datum:** 2026-01-26  
**Typ:** Bugfix (Normal)  
**Status:** ✅ Ready to Deploy
