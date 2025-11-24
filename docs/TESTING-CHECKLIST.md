# Quick Testing Checklist

## Vor dem Test

- [ ] Plugin aktualisiert (git pull oder neu hochgeladen)
- [ ] Browser-Cache geleert
- [ ] WordPress Cache geleert (falls vorhanden)
- [ ] WooCommerce Transients gelöscht (WooCommerce → Status → Tools)
- [ ] Mindestens 1 Produkt im Warenkorb
- [ ] Mindestens 1 Versandmethode in WLM konfiguriert

## Test 1: Browser-Konsole prüfen

1. Checkout-Seite öffnen (Block-basiert!)
2. Browser-Konsole öffnen (F12 → Console)
3. Nach `[WLM Blocks]` Meldungen suchen

### Erwartete Logs (in dieser Reihenfolge):

```
✅ [WLM Blocks] Script loaded
✅ [WLM Blocks] Available globals: {wp: "object", wc: "object", ...}
✅ [WLM Blocks] Registering plugin...
✅ [WLM Blocks] Plugin registered: wlm-delivery-info-slot-fill
✅ [WLM Blocks] DeliveryInfoSlotFill render started
✅ [WLM Blocks] useSelect callback running
✅ [WLM Blocks] Store select: {...}
✅ [WLM Blocks] Cart data from store: {...}
✅ [WLM Blocks] Extensions: {...}
✅ [WLM Blocks] WLM Extension: {delivery_info: {...}}
```

### Häufige Fehler:

❌ **"wp is not defined"** → WordPress Scripts nicht geladen
❌ **"wc is not defined"** → WooCommerce Blocks Scripts nicht geladen
❌ **"wc/store/cart not available"** → WooCommerce Store nicht registriert
❌ **"Cart data from store: undefined"** → Store API Problem
❌ **"WLM Extension: undefined"** → Store API Extension nicht registriert

## Test 2: Frontend visuell prüfen

### Erwartetes Ergebnis:

Unterhalb der Versandmethode sollte eine Box erscheinen:

```
┌─────────────────────────────────────────────┐
│ Voraussichtliche Lieferung: 15.11. - 18.11. │
│                                             │
│ ⚡ Express-Versand (5,00 €) –               │
│    Zustellung: 14.11.                       │
└─────────────────────────────────────────────┘
```

**Styling:**
- [ ] Grauer Hintergrund (#f7f7f7)
- [ ] Blauer linker Border (3px solid #2271b1)
- [ ] 12px Padding
- [ ] 12px Margin-Top

**Inhalt:**
- [ ] Lieferzeitfenster wird angezeigt
- [ ] Express-Button wird angezeigt (falls aktiviert und vor Cutoff)
- [ ] Preis ist korrekt formatiert

## Test 3: Debug Script ausführen

1. Öffne `debug-blocks.js` in einem Text-Editor
2. Kopiere den gesamten Inhalt
3. Füge ihn in die Browser-Konsole ein
4. Drücke Enter

### Erwartete Ausgabe:

```
=== WLM Blocks Debug Test ===

1. Checking globals...
✅ Globals available

2. Checking WooCommerce store...
✅ wc/store/cart is registered

3. Getting cart data...
✅ Cart data available

4. Checking extensions...
✅ Extensions available

5. Checking WLM extension...
✅ WLM extension found
Delivery info: {...}

6. Checking shipping rates...
✅ Shipping method selected
Rate ID: wlm_method_XXX:1
Method ID: wlm_method_XXX

7. Checking ExperimentalOrderShippingPackages...
✅ ExperimentalOrderShippingPackages available
✅ Fill: function

8. Checking plugin registration...
✅ WLM plugin registered

=== Debug Test Complete ===
```

## Test 4: Network Tab prüfen

1. Browser DevTools öffnen (F12)
2. Network Tab öffnen
3. Filter: `store/cart` eingeben
4. Checkout-Seite neu laden
5. Request `store/cart` anklicken
6. Response Tab öffnen

### Erwartete Response:

```json
{
  "extensions": {
    "woo-lieferzeiten-manager": {
      "delivery_info": {
        "wlm_method_XXX": {
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

## Test 5: Verschiedene Szenarien

- [ ] **Verschiedene Versandmethoden:** Wechseln zwischen Versandarten → Lieferzeitfenster ändert sich
- [ ] **Express-Button:** Klicken auf Express-Button → Status ändert sich
- [ ] **Verschiedene Produkte:** Verschiedene Produkte im Warenkorb → Zeitfenster passt sich an
- [ ] **Keine Versandmethode:** Keine Versandmethode ausgewählt → Keine Delivery Info
- [ ] **Nicht-WLM Methode:** Standard WooCommerce Methode → Keine Delivery Info

## Ergebnis melden

### ✅ Wenn alles funktioniert:

Bitte melden:
- "Funktioniert! Delivery Info wird korrekt angezeigt."
- Screenshot vom Checkout
- Besondere Beobachtungen (falls vorhanden)

### ❌ Wenn es NICHT funktioniert:

Bitte bereitstellen:
1. **Console-Logs** (alle `[WLM Blocks]` Meldungen)
2. **Debug Script Ausgabe** (vollständig)
3. **Screenshot vom Checkout**
4. **Network Tab Response** (store/cart Endpoint)
5. **Fehlermeldungen** (falls vorhanden)

### ⚠️ Wenn es teilweise funktioniert:

Bitte beschreiben:
- Was funktioniert?
- Was funktioniert NICHT?
- Console-Logs und Screenshots bereitstellen

---

## Zusätzliche Informationen

**WordPress Version:** _____
**WooCommerce Version:** _____
**PHP Version:** _____
**Theme:** _____
**Andere aktive Plugins:** _____

**Browser:**
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari
- [ ] Andere: _____

**Checkout-Typ:**
- [ ] WooCommerce Blocks Checkout (neu)
- [ ] Classic Checkout (alt)
- [ ] Nicht sicher
