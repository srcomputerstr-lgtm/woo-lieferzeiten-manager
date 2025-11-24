# Fix: Serialisierungs-Problem bei Conditions

## 🐛 Problem-Beschreibung

Bedingungen wurden in der UI korrekt angezeigt und erstellt, aber nach dem Speichern und Neuladen waren sie verschwunden.

### Root Cause

Das JavaScript hat die verschachtelten Array-Strukturen **falsch serialisiert**:

**Erwartet:**
```json
{
  "attribute_conditions": [
    {
      "logic": "at_least_one",
      "attribute": "pa_versandgruppe",
      "values": ["paketgut", "musterversand"]
    }
  ]
}
```

**Tatsächlich:**
```json
{
  "attribute_conditions][0][logic": "at_least_one",
  "attribute_conditions][0][attribute": "pa_versandgruppe",
  "attribute_conditions][0][values][": ["paketgut", "musterversand"]
}
```

Die Keys waren **flach** statt **verschachtelt**!

## 🔍 Ursache

### JavaScript-Problem

Der ursprüngliche Regex war zu **gierig**:

```javascript
var match = name.match(/wlm_shipping_methods\[\d+\]\[(.+)\]/);
//                                                  ^^^^ GIERIG!
```

Bei `wlm_shipping_methods[0][attribute_conditions][0][logic]` captured er:
```
"attribute_conditions][0][logic"  // ❌ Fehlendes [ am Anfang!
```

### Browser-Caching

Selbst nach dem JavaScript-Fix wurde die **alte Version gecached**, sodass das Problem weiterhin bestand.

## ✅ Lösung: PHP-seitige Normalisierung

Statt auf JavaScript zu vertrauen, normalisiert PHP jetzt die Daten **beim Speichern**.

### Implementierung

**Datei:** `includes/class-wlm-admin.php` (Zeilen 382-415)

```php
// Fix flat keys like "attribute_conditions][0][logic" to nested structure
$conditions = array();
$keys_to_remove = array();

foreach ($method as $key => $value) {
    // Match: attribute_conditions][INDEX][FIELD] or attribute_conditions][INDEX][FIELD][]
    if (preg_match('/^attribute_conditions\]\[(\d+)\]\[([^\]]+)\](\[\])?$/', $key, $matches)) {
        $index = (int)$matches[1];
        $field = $matches[2];
        $is_array = isset($matches[3]) && $matches[3] === '[]';
        
        if (!isset($conditions[$index])) {
            $conditions[$index] = array(
                'logic' => 'at_least_one',
                'attribute' => '',
                'values' => array()
            );
        }
        
        if ($is_array) {
            // It's an array field like values[]
            $conditions[$index][$field] = is_array($value) ? $value : array($value);
        } else {
            $conditions[$index][$field] = $value;
        }
        
        $keys_to_remove[] = $key;
    }
    // Also fix required_categories][] format
    elseif ($key === 'required_categories][]' || $key === 'required_categories][') {
        $method['required_categories'] = is_array($value) ? $value : array();
        $keys_to_remove[] = $key;
    }
}

// Remove flat keys
foreach ($keys_to_remove as $key) {
    unset($method[$key]);
}

// Add normalized conditions if found
if (!empty($conditions)) {
    $method['attribute_conditions'] = array_values($conditions);
}
```

### Wie es funktioniert

1. **Regex erkennt flache Keys:**
   - Pattern: `attribute_conditions][0][logic`
   - Captured: Index `0`, Field `logic`

2. **Baut verschachtelte Struktur auf:**
   ```php
   $conditions[0]['logic'] = 'at_least_one';
   $conditions[0]['attribute'] = 'pa_versandgruppe';
   $conditions[0]['values'] = ['paketgut'];
   ```

3. **Entfernt flache Keys:**
   ```php
   unset($method['attribute_conditions][0][logic']);
   ```

4. **Fügt korrekte Struktur hinzu:**
   ```php
   $method['attribute_conditions'] = [
       ['logic' => 'at_least_one', 'attribute' => '...', 'values' => [...]]
   ];
   ```

## 🧪 Testing

### Vor dem Fix

**Debug-Log:**
```
[attribute_conditions][0][logic] => at_least_one
[attribute_conditions][0][attribute] => pa_versandgruppe
[attribute_conditions][0][values][] => Array(...)
```
❌ **Flache Keys** → Werden beim Laden nicht erkannt → Bedingungen verschwinden

### Nach dem Fix

**Debug-Log:**
```
[attribute_conditions] => Array
(
    [0] => Array
        (
            [logic] => at_least_one
            [attribute] => pa_versandgruppe
            [values] => Array
                (
                    [0] => paketgut
                    [1] => musterversand
                )
        )
)
```
✅ **Verschachtelte Struktur** → Wird korrekt geladen → Bedingungen bleiben erhalten!

## 📋 Deployment

### Schritte

1. **Repository pullen:**
   ```bash
   git pull origin main
   ```

2. **Browser-Cache KOMPLETT leeren:**
   - Chrome: Strg+Shift+Delete → "Cached Images and Files"
   - Firefox: Strg+Shift+Delete → "Cache"
   - Oder: Inkognito-Modus verwenden

3. **WordPress-Admin aufrufen**

4. **Testen:**
   - Versandart öffnen
   - Bedingung erstellen
   - Speichern
   - **Seite neu laden (F5)**
   - ✅ Bedingung sollte noch da sein!

### Wichtig: Cache leeren!

Das JavaScript wurde mehrfach geändert, daher ist es **kritisch** den Browser-Cache zu leeren!

**Alternativen:**
- Inkognito-Modus verwenden
- Hard Reload: Strg+Shift+R (Windows) / Cmd+Shift+R (Mac)
- Cache über WordPress-Plugin leeren (falls vorhanden)

## 🔧 Bonus: JavaScript-Fix

Der JavaScript-Code wurde ebenfalls verbessert (auch wenn PHP jetzt die Hauptarbeit macht):

**Datei:** `admin/js/admin.js` (Zeilen 108-172)

```javascript
// Parse all bracket segments: [key1][key2][key3][]
var segments = [];
var segmentRegex = /\[([^\]]+)\]/g;
var segmentMatch;
while ((segmentMatch = segmentRegex.exec(fullPath)) !== null) {
    segments.push(segmentMatch[1]);
}

// Build nested structure
var current = method;
for (var i = 0; i < segments.length; i++) {
    var segment = segments[i];
    var isLast = (i === segments.length - 1);
    
    if (isLast) {
        current[segment] = value;
    } else {
        if (!current[segment]) {
            current[segment] = isNextNumeric ? [] : {};
        }
        current = current[segment];
    }
}
```

**Vorteil:** Wenn der Browser-Cache geleert wird, funktioniert es auch JavaScript-seitig korrekt!

## 📊 Vergleich

| Aspekt | Vor Fix | Nach Fix |
|--------|---------|----------|
| **JavaScript** | Gieriger Regex | Segment-Parsing |
| **PHP** | Keine Normalisierung | Flache Keys → Nested |
| **Speichern** | ❌ Flache Keys | ✅ Verschachtelt |
| **Laden** | ❌ Nicht erkannt | ✅ Korrekt |
| **Bedingungen** | ❌ Verschwinden | ✅ Bleiben erhalten |
| **Browser-Cache** | ❌ Kritisch | ⚠️ Sollte geleert werden |

## 🐛 Troubleshooting

### Bedingungen verschwinden immer noch

**Ursache:** Browser-Cache

**Lösung:**
1. **Hard Reload:** Strg+Shift+R
2. **Cache leeren:** Browser-Einstellungen
3. **Inkognito-Modus:** Testen ohne Cache

### Debug-Log prüfen

**Aktivieren:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Log-Datei:** `wp-content/debug.log`

**Suchen nach:**
```
[WLM] Normalized flat keys to attribute_conditions
```

**Erwartetes Ergebnis:**
```
[WLM] Normalized flat keys to attribute_conditions for method 0: Array
(
    [0] => Array
        (
            [logic] => at_least_one
            [attribute] => pa_versandgruppe
            [values] => Array(...)
        )
)
```

### JavaScript-Konsole prüfen

**Öffnen:** F12 → Console

**Beim Speichern prüfen:**
- Gibt es JavaScript-Fehler?
- Was wird im Network-Tab gesendet?

**Erwartetes Payload:**
```json
{
  "wlm_shipping_methods": [{
    "attribute_conditions": [{
      "logic": "at_least_one",
      "attribute": "pa_versandgruppe",
      "values": ["paketgut"]
    }]
  }]
}
```

**Oder (wird von PHP normalisiert):**
```json
{
  "wlm_shipping_methods": [{
    "attribute_conditions][0][logic": "at_least_one",
    "attribute_conditions][0][attribute": "pa_versandgruppe",
    "attribute_conditions][0][values][": ["paketgut"]
  }]
}
```
→ Beide funktionieren jetzt, da PHP normalisiert!

## 📚 Commits

### 1. JavaScript-Fix (Versuch 1)
**Commit:** `30aee4f`  
**Status:** ⚠️ Funktioniert, aber Browser cached alte Version

### 2. PHP-Normalisierung (Final Fix)
**Commit:** `e362e0d`  
**Status:** ✅ Funktioniert unabhängig vom JavaScript

## 💡 Lessons Learned

1. **Browser-Caching ist kritisch** bei JavaScript-Änderungen
2. **PHP-seitige Validierung** ist robuster als JavaScript
3. **Defensive Programming:** Beide Seiten sollten korrekt funktionieren
4. **Debug-Logging** ist essentiell für Troubleshooting

## 🎯 Fazit

**Problem:** Bedingungen verschwanden nach Speichern  
**Ursache:** Flache Keys statt verschachtelter Arrays  
**Lösung:** PHP normalisiert beim Speichern  
**Status:** ✅ **GEFIXT!**

---

**Version:** 1.12.0  
**Datum:** 14. November 2025  
**Commits:** `30aee4f`, `e362e0d`  
**Status:** ✅ Produktionsreif
