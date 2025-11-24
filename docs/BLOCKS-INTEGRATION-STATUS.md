# WooCommerce Blocks Integration - Status Update

## Version 1.6.0 - Blocks Integration Fixes

### Durchgeführte Änderungen

#### 1. React Slot-Fill Props Fix
**Problem:** Der React Component hat Props als Parameter erwartet, aber `registerPlugin` übergibt diese nicht automatisch.

**Lösung:** 
- ✅ `wp.data.useSelect` Hook verwenden um Cart-Daten aus dem WooCommerce Store zu holen
- ✅ `ExperimentalOrderShippingPackages.Fill` statt nur `ExperimentalOrderShippingPackages` verwenden
- ✅ `wp-data` Dependency hinzugefügt

**Datei:** `assets/js/blocks-delivery-info.js`

#### 2. Store API Extension Konflikt behoben
**Problem:** Es gab zwei konkurrierende Blocks-Integrationen:
- Alte `blocks-integration.js` (nutzt ExperimentalOrderMeta)
- Neue `blocks-delivery-info.js` (nutzt ExperimentalOrderShippingPackages)

**Lösung:**
- ✅ Store API Extension wird jetzt direkt in `class-wlm-frontend.php` registriert
- ✅ Alte `blocks-integration.js` wird NICHT mehr geladen
- ✅ Nur noch `blocks-delivery-info.js` wird verwendet

**Dateien:** 
- `includes/class-wlm-frontend.php`
- `includes/class-wlm-blocks-integration.php`

#### 3. Calculator Method Config Fix
**Problem:** `calculate_cart_window()` hat die `$method_config` nicht an `calculate_product_window()` weitergegeben.

**Lösung:**
- ✅ `$method_config` und `$is_express` werden jetzt korrekt weitergegeben
- ✅ Jede Versandmethode bekommt ihr eigenes Lieferzeitfenster basierend auf Transit-Zeiten
- ✅ Express-Zeiten werden korrekt berechnet

**Datei:** `includes/class-wlm-calculator.php`

#### 4. Umfangreiches Debug-Logging
**Hinzugefügt:**
- ✅ Console-Logs für jeden Schritt der React Component Lifecycle
- ✅ Logging von verfügbaren Globals (wp, wc, wp.data, etc.)
- ✅ Logging von Cart-Daten und Extensions
- ✅ Logging von Shipping Rates und ausgewählter Methode
- ✅ Logging von Delivery Info Daten

**Datei:** `assets/js/blocks-delivery-info.js`

#### 5. Debug Test Script
**Erstellt:** `debug-blocks.js` - Ein vollständiges Test-Script für die Browser-Konsole

**Prüft:**
- WordPress und WooCommerce Globals
- WooCommerce Store Registrierung
- Cart-Daten Verfügbarkeit
- Extensions und WLM Extension
- Shipping Rates
- ExperimentalOrderShippingPackages Verfügbarkeit
- Plugin Registrierung

---

## Testing-Anweisungen

### 1. Plugin aktualisieren
```bash
cd /pfad/zu/wp-content/plugins/woo-lieferzeiten-manager
git pull origin main
```

Oder: Plugin-Dateien neu hochladen via FTP/SFTP.

### 2. Cache leeren
- Browser-Cache leeren (Strg+Shift+Delete)
- WordPress Cache leeren (falls Plugin aktiv)
- WooCommerce Cache leeren: WooCommerce → Status → Tools → "Clear transients"

### 3. Checkout-Seite testen

#### Vorbereitung:
1. Mindestens eine Versandmethode in WLM konfigurieren
2. Produkte im Warenkorb haben
3. Zur Checkout-Seite navigieren (Block-basierter Checkout!)

#### Browser-Konsole öffnen:
- Chrome/Edge: F12 → Console Tab
- Firefox: F12 → Konsole Tab
- Safari: Entwickler → JavaScript-Konsole

#### Erwartete Console-Logs:
```
[WLM Blocks] Script loaded
[WLM Blocks] Available globals: {wp: "object", wc: "object", ...}
[WLM Blocks] Registering plugin...
[WLM Blocks] Plugin registered: wlm-delivery-info-slot-fill
[WLM Blocks] DeliveryInfoSlotFill render started
[WLM Blocks] useSelect callback running
[WLM Blocks] Store select: {...}
[WLM Blocks] Cart data from store: {...}
[WLM Blocks] Component mounted/updated
[WLM Blocks] Cart data: {...}
[WLM Blocks] Extensions: {...}
[WLM Blocks] WLM Extension: {delivery_info: {...}}
[WLM Blocks] Shipping rates: [...]
[WLM Blocks] Selected method: {...}
[WLM Blocks] Method ID: wlm_method_XXX
[WLM Blocks] All delivery info: {...}
[WLM Blocks] Delivery info for method: {...}
[WLM Blocks] Rendering delivery info UI
```

### 4. Debug Test Script ausführen

1. Öffnen Sie `debug-blocks.js` in einem Text-Editor
2. Kopieren Sie den gesamten Inhalt
3. Fügen Sie ihn in die Browser-Konsole ein und drücken Sie Enter
4. Analysieren Sie die Ausgabe:
   - ✅ = Funktioniert
   - ❌ = Problem gefunden
   - ⚠️ = Warnung

### 5. Erwartetes Ergebnis im Frontend

**Unterhalb der Versandmethode sollte erscheinen:**

```
┌─────────────────────────────────────────────┐
│ Voraussichtliche Lieferung: 15.11. - 18.11. │
│                                             │
│ ⚡ Express-Versand (5,00 €) –               │
│    Zustellung: 14.11.                       │
└─────────────────────────────────────────────┘
```

**Styling:**
- Grauer Hintergrund (#f7f7f7)
- Blauer linker Border (#2271b1)
- 12px Padding
- 12px Margin-Top

---

## Bekannte Probleme

### Noch NICHT behoben:
1. ❌ **frontend.js Syntax Error** (Zeile 253) - Datei hat literal `\n` Zeichen statt echte Newlines
2. ⚠️ **Attribute Conditions** - Datenstruktur-Probleme (für spätere Version geplant)

### Workarounds:
- **frontend.js:** Wird für Blocks-Checkout nicht benötigt (nur für Classic Checkout)
- **Attribute Conditions:** Aktuell nur Weight und Cart Value Conditions funktionieren

---

## Nächste Schritte

### Wenn es NICHT funktioniert:

1. **Console-Logs kopieren** und bereitstellen
2. **Debug Script Ausgabe** kopieren und bereitstellen
3. **Screenshot vom Checkout** machen
4. **Network Tab prüfen:**
   - Filter: `store/cart`
   - Request anklicken
   - Response Tab → Extensions prüfen
   - Sollte `woo-lieferzeiten-manager` enthalten

### Wenn es funktioniert:

1. ✅ Verschiedene Versandmethoden testen
2. ✅ Express-Button testen (falls aktiviert)
3. ✅ Verschiedene Produkte im Warenkorb testen
4. ✅ Lieferzeitfenster-Berechnung prüfen

---

## Technische Details

### Architektur

```
┌─────────────────────────────────────────────────────────┐
│ WooCommerce Blocks Checkout                             │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ Shipping Methods (WooCommerce Core)                │ │
│  │  ○ Standard Versand                                │ │
│  │  ○ Express Versand                                 │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ ExperimentalOrderShippingPackages.Fill (WLM)       │ │
│  │                                                     │ │
│  │  📦 Delivery Info Component (React)                │ │
│  │     • Lieferzeitfenster                            │ │
│  │     • Express-Option Button                        │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘

         ↑                                    ↑
         │                                    │
         │ useSelect('wc/store/cart')         │
         │                                    │
         │                                    │
┌────────┴────────┐                  ┌────────┴────────┐
│ WooCommerce     │                  │ Store API       │
│ Store           │                  │ Extension       │
│ (wp.data)       │◄─────────────────│ (PHP)           │
└─────────────────┘                  └─────────────────┘
                                              ↑
                                              │
                                     ┌────────┴────────┐
                                     │ WLM Calculator  │
                                     │ (PHP)           │
                                     └─────────────────┘
```

### Datenfluss

1. **PHP:** `WLM_Blocks_Integration::extend_cart_data()` wird aufgerufen
2. **PHP:** Für jede Versandmethode wird `calculate_cart_window($method_config)` aufgerufen
3. **PHP:** Daten werden in Store API Extensions unter Namespace `woo-lieferzeiten-manager` gespeichert
4. **React:** `useSelect` holt Cart-Daten inkl. Extensions aus WooCommerce Store
5. **React:** Component rendert Delivery Info für ausgewählte Versandmethode
6. **React:** `ExperimentalOrderShippingPackages.Fill` fügt Component unterhalb Shipping Methods ein

### Datenstruktur

**Store API Extension Data:**
```json
{
  "extensions": {
    "woo-lieferzeiten-manager": {
      "delivery_info": {
        "wlm_method_123": {
          "delivery_window": "15.11. - 18.11.",
          "express_available": true,
          "express_cost": 5.00,
          "express_cost_formatted": "5,00 €",
          "express_window": "14.11.",
          "is_express_selected": false
        }
      }
    }
  }
}
```

---

## Kontakt & Support

Bei Problemen bitte folgende Informationen bereitstellen:
- ✅ Console-Logs (vollständig)
- ✅ Debug Script Ausgabe
- ✅ Screenshot vom Checkout
- ✅ Network Tab Response (store/cart)
- ✅ WordPress Version
- ✅ WooCommerce Version
- ✅ PHP Version
- ✅ Theme Name
