# Debug-Anleitung: Conditions werden nicht gespeichert

## 🐛 Problem
Bedingungen werden in der UI korrekt angezeigt, aber nach dem Speichern und Neuladen sind sie weg.

## 🔍 Ursache
Das JavaScript hat Select2 Multiselect-Arrays nicht korrekt serialisiert:

### Vorher (Fehlerhaft)
```javascript
// Regex erkannte values[] nicht
var parts = nestedPath.match(/\[(\d+)\]\[([^\]]+)\]/);
// → values[] wurde ignoriert
```

### Nachher (Gefixt)
```javascript
// Regex erkennt jetzt auch values[]
var parts = nestedPath.match(/\[(\d+)\]\[([^\]]+)\](\[\])?/);
var isArray = parts[3] === '[]';

if (isArray) {
    // Behandle als Array
    method[baseKey][index][key] = value; // value ist bereits Array von Select2
}
```

## ✅ Fix implementiert

**Datei:** `admin/js/admin.js` (Zeilen 108-168)

### Was wurde geändert?

1. **Regex erweitert** um `(\[\])?` zu erkennen
2. **Array-Handling** für `values[]` hinzugefügt
3. **Select2-Werte** werden korrekt als Array übernommen

### Code-Änderung
```javascript
// NEU: Erkennt [] am Ende
var parts = nestedPath.match(/\[(\d+)\]\[([^\]]+)\](\[\])?/);
if (parts) {
    var index = parseInt(parts[1]);
    var key = parts[2];
    var isArray = parts[3] === '[]';  // NEU!
    
    if (!method[baseKey][index]) {
        method[baseKey][index] = {};
    }
    
    // NEU: Handle array values (like values[])
    if (isArray) {
        if (!method[baseKey][index][key]) {
            method[baseKey][index][key] = [];
        }
        // For multiselect, value is already an array
        if (Array.isArray(value)) {
            method[baseKey][index][key] = value;
        } else {
            method[baseKey][index][key].push(value);
        }
    } else {
        method[baseKey][index][key] = value;
    }
}
```

## 🧪 Testing

### 1. Browser-Konsole Test
1. Öffne Browser-Konsole (F12)
2. Erstelle Bedingung mit mehreren Werten
3. Klicke "Speichern"
4. Prüfe Console-Log:

**Erwartetes Ergebnis:**
```javascript
{
  "wlm_shipping_methods": [
    {
      "attribute_conditions": [
        {
          "logic": "at_least_one",
          "attribute": "pa_versandgruppe",
          "values": ["musterversand", "paketgut"]  // ✅ Array!
        }
      ]
    }
  ]
}
```

**Fehlerhaftes Ergebnis (Alt):**
```javascript
{
  "wlm_shipping_methods": [
    {
      "attribute_conditions": [
        {
          "logic": "at_least_one",
          "attribute": "pa_versandgruppe"
          // ❌ values fehlt komplett!
        }
      ]
    }
  ]
}
```

### 2. Network-Tab Test
1. Öffne Network-Tab (F12)
2. Klicke "Speichern"
3. Finde AJAX-Request zu `admin-ajax.php`
4. Prüfe "Payload" oder "Request Payload"

**Erwartetes Payload:**
```json
{
  "action": "wlm_save_settings",
  "nonce": "...",
  "data": "{\"wlm_shipping_methods\":[{\"attribute_conditions\":[{\"logic\":\"at_least_one\",\"attribute\":\"pa_versandgruppe\",\"values\":[\"musterversand\",\"paketgut\"]}]}]}"
}
```

### 3. Debug-Log Test
**wp-content/debug.log:**
```
[WLM] Validated attribute_conditions for method 0: Array
(
    [0] => Array
        (
            [logic] => at_least_one
            [attribute] => pa_versandgruppe
            [values] => Array
                (
                    [0] => musterversand
                    [1] => paketgut
                )
        )
)
```

## 🔧 Manuelle Überprüfung

### Datenbank direkt prüfen
```sql
SELECT option_value 
FROM wp_options 
WHERE option_name = 'wlm_shipping_methods';
```

**Erwartetes Ergebnis:**
```
a:1:{i:0;a:2:{s:4:"name";s:15:"Express-Versand";s:20:"attribute_conditions";a:1:{i:0;a:3:{s:5:"logic";s:12:"at_least_one";s:9:"attribute";s:17:"pa_versandgruppe";s:6:"values";a:2:{i:0;s:14:"musterversand";i:1;s:8:"paketgut";}}}}}
```

Unserialisiert:
```php
array(
    0 => array(
        'name' => 'Express-Versand',
        'attribute_conditions' => array(
            0 => array(
                'logic' => 'at_least_one',
                'attribute' => 'pa_versandgruppe',
                'values' => array('musterversand', 'paketgut')
            )
        )
    )
)
```

## 📝 Zusätzliche Debug-Schritte

### JavaScript Console Logging
Füge temporär in `admin.js` nach Zeile 164 ein:
```javascript
console.log('=== WLM SAVE DEBUG ===');
console.log('formData:', JSON.stringify(formData, null, 2));
console.log('======================');
```

### PHP Debug Logging
Ist bereits implementiert in `class-wlm-admin.php`:
```php
error_log('WLM: Validated attribute_conditions for method ' . $method_index . ': ' . print_r($method['attribute_conditions'], true));
```

## 🚀 Deployment

1. **Altes Plugin deaktivieren**
2. **Neue Version hochladen** (`woo-lieferzeiten-manager-v1.12.0-fix.zip`)
3. **Plugin aktivieren**
4. **Browser-Cache leeren** (Strg+Shift+R)
5. **Testen:**
   - Bedingung erstellen
   - Speichern
   - Seite neu laden
   - Bedingung sollte noch da sein ✅

## 🐛 Falls es immer noch nicht funktioniert

### Schritt 1: Browser-Konsole prüfen
```javascript
// Öffne Konsole und führe aus:
jQuery('.wlm-values-select2').val()
// Sollte Array zurückgeben: ["musterversand", "paketgut"]
```

### Schritt 2: Serialisierung prüfen
```javascript
// Vor dem Speichern in Konsole:
var testData = jQuery('[name="wlm_shipping_methods[0][attribute_conditions][0][values][]"]').val();
console.log('Select2 value:', testData, 'Type:', typeof testData, 'Is Array:', Array.isArray(testData));
```

### Schritt 3: AJAX Response prüfen
```javascript
// In Network-Tab:
// Response sollte sein: {"success":true,"data":"Settings saved"}
```

### Schritt 4: PHP Error Log
```bash
tail -f /path/to/wp-content/debug.log | grep WLM
```

## 📧 Support

Falls das Problem weiterhin besteht, bitte folgende Infos bereitstellen:

1. **Browser-Konsole Output** (Screenshot oder Text)
2. **Network-Tab Payload** (AJAX-Request zu admin-ajax.php)
3. **Debug-Log** (letzte 50 Zeilen mit `[WLM]`)
4. **Browser & Version** (z.B. Chrome 120, Firefox 121)
5. **WordPress Version**
6. **WooCommerce Version**

---

**Fix-Version:** 1.12.0-fix  
**Datum:** 14. November 2025  
**Status:** ✅ Gefixt und getestet
