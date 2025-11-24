# Shortcode-Dokumentation – Woo Lieferzeiten Manager

Diese Dokumentation beschreibt alle verfügbaren Shortcodes für die flexible Integration in Page Builder wie Oxygen, Elementor oder Gutenberg.

## 📋 Übersicht

Das Plugin bietet **5 Shortcodes** für die Produktseite:

| Shortcode | Beschreibung | Verwendung |
|-----------|--------------|------------|
| `[wlm_delivery_info]` | Komplettes Lieferzeiten-Panel | Alle Infos auf einmal |
| `[wlm_stock_status]` | Nur Lagerstatus | Einzelnes Element |
| `[wlm_shipping_method]` | Nur Versandart | Einzelnes Element |
| `[wlm_delivery_window]` | Nur Lieferfenster | Einzelnes Element |
| `[wlm_delivery_panel]` | Alias für `wlm_delivery_info` | Alternative Benennung |

## 🎯 Hauptshortcode: Komplettes Panel

### `[wlm_delivery_info]`

Zeigt das komplette Lieferzeiten-Panel mit allen Informationen an.

#### Basis-Verwendung

```
[wlm_delivery_info]
```

**Ausgabe**:
- 🟢 Lagerstatus (z.B. "Auf Lager: 100 Stück")
- 🚚 Versandart (z.B. "Versand via Paketdienst")
- 📅 Lieferfenster (z.B. "Lieferung ca.: Mi, 12.11. – Fr, 14.11.")

#### Mit Produkt-ID

```
[wlm_delivery_info product_id="123"]
```

Zeigt Informationen für ein spezifisches Produkt (nützlich für Cross-Selling-Bereiche).

#### Nur bestimmte Elemente anzeigen

```
[wlm_delivery_info show="stock,delivery"]
```

**Mögliche Werte für `show`**:
- `all` (Standard) - Alle Elemente
- `stock` - Nur Lagerstatus
- `shipping` - Nur Versandart
- `delivery` - Nur Lieferfenster

**Kombinationen**:
```
[wlm_delivery_info show="stock"]
[wlm_delivery_info show="stock,delivery"]
[wlm_delivery_info show="shipping,delivery"]
```

## 📦 Einzelne Elemente

### `[wlm_stock_status]`

Zeigt nur den Lagerstatus an.

#### Basis-Verwendung

```
[wlm_stock_status]
```

**Ausgabe**: 🟢 Auf Lager: 100 Stück

#### Ohne Icon

```
[wlm_stock_status show_icon="no"]
```

**Ausgabe**: Auf Lager: 100 Stück

#### Mit Produkt-ID

```
[wlm_stock_status product_id="456"]
```

#### Attribute

| Attribut | Standard | Beschreibung |
|----------|----------|--------------|
| `product_id` | aktuelles Produkt | Spezifische Produkt-ID |
| `show_icon` | `yes` | Icon anzeigen (🟢/🟡) |

### `[wlm_shipping_method]`

Zeigt nur die Versandart an.

#### Basis-Verwendung

```
[wlm_shipping_method]
```

**Ausgabe**: 🚚 Versand via Paketdienst ℹ️

#### Ohne Icon

```
[wlm_shipping_method show_icon="no"]
```

**Ausgabe**: Versand via Paketdienst ℹ️

#### Ohne Info-Icon

```
[wlm_shipping_method show_info="no"]
```

**Ausgabe**: 🚚 Versand via Paketdienst

#### Attribute

| Attribut | Standard | Beschreibung |
|----------|----------|--------------|
| `product_id` | aktuelles Produkt | Spezifische Produkt-ID |
| `show_icon` | `yes` | Versand-Icon anzeigen (🚚) |
| `show_info` | `yes` | Info-Icon mit Tooltip anzeigen |

### `[wlm_delivery_window]`

Zeigt nur das Lieferfenster an.

#### Basis-Verwendung

```
[wlm_delivery_window]
```

**Ausgabe**: 📅 Lieferung ca.: Mi, 12.11. – Fr, 14.11.

#### Ohne Label

```
[wlm_delivery_window show_label="no"]
```

**Ausgabe**: 📅 Mi, 12.11. – Fr, 14.11.

#### Ohne Icon

```
[wlm_delivery_window show_icon="no"]
```

**Ausgabe**: Lieferung ca.: Mi, 12.11. – Fr, 14.11.

#### Nur Datum

```
[wlm_delivery_window show_icon="no" show_label="no"]
```

**Ausgabe**: Mi, 12.11. – Fr, 14.11.

#### Format-Optionen

**Standard-Format** (Zeitraum):
```
[wlm_delivery_window format="default"]
```
**Ausgabe**: Mi, 12.11. – Fr, 14.11.

**Kurz-Format** (nur frühestes Datum):
```
[wlm_delivery_window format="short"]
```
**Ausgabe**: Mi, 12.11.

**Nur Daten** (ohne Text):
```
[wlm_delivery_window format="dates_only"]
```
**Ausgabe**: Mi, 12.11. – Fr, 14.11.

#### Attribute

| Attribut | Standard | Beschreibung |
|----------|----------|--------------|
| `product_id` | aktuelles Produkt | Spezifische Produkt-ID |
| `show_icon` | `yes` | Kalender-Icon anzeigen (📅) |
| `show_label` | `yes` | Label "Lieferung ca.:" anzeigen |
| `format` | `default` | Format: `default`, `short`, `dates_only` |

## 🎨 Integration in Oxygen Builder

### Schritt 1: Shortcode-Element hinzufügen

1. Öffnen Sie Ihr Produkt-Template in Oxygen
2. Fügen Sie ein **Shortcode**-Element hinzu
3. Geben Sie den gewünschten Shortcode ein

### Schritt 2: Styling anpassen

Die Shortcodes verwenden dieselben CSS-Klassen wie die automatische Integration:

```css
/* Haupt-Container */
.wlm-pdp-panel.wlm-shortcode {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px 16px;
}

/* Lagerstatus */
.wlm-stock-status.wlm-shortcode {
    font-size: 14px;
    margin: 8px 0;
}

/* Versandart */
.wlm-shipping-method.wlm-shortcode {
    color: #555;
}

/* Lieferfenster */
.wlm-delivery-window.wlm-shortcode {
    color: #333;
}
```

### Schritt 3: Responsive Design

Fügen Sie eigene CSS-Regeln in Oxygen hinzu:

```css
@media (max-width: 768px) {
    .wlm-pdp-panel.wlm-shortcode {
        padding: 10px 12px;
        font-size: 13px;
    }
}
```

## 💡 Anwendungsbeispiele

### Beispiel 1: Minimalistisches Design

Nur Lieferfenster ohne Icons und Label:

```
[wlm_delivery_window show_icon="no" show_label="no"]
```

### Beispiel 2: Zwei-Spalten-Layout

**Linke Spalte**:
```
[wlm_stock_status]
[wlm_shipping_method]
```

**Rechte Spalte**:
```
[wlm_delivery_window]
```

### Beispiel 3: Kompakte Anzeige

Nur die wichtigsten Infos:

```
[wlm_delivery_info show="stock,delivery"]
```

### Beispiel 4: Cross-Selling

Zeige Lieferinfo für verwandtes Produkt:

```
[wlm_delivery_window product_id="789" show_icon="no"]
```

### Beispiel 5: Custom HTML-Struktur

```html
<div class="my-custom-delivery-box">
    <h3>Verfügbarkeit</h3>
    [wlm_stock_status show_icon="no"]
    
    <h3>Lieferung</h3>
    [wlm_delivery_window show_label="no"]
</div>
```

## 🔧 Technische Details

### Automatische Produkt-Erkennung

Die Shortcodes erkennen automatisch das aktuelle Produkt in folgender Reihenfolge:

1. **Attribut `product_id`** (wenn angegeben)
2. **Globale Variable `$product`** (WooCommerce-Standard)
3. **Globaler Post** (wenn Post-Type = 'product')

### AJAX-Kompatibilität

Die Shortcodes funktionieren auch nach AJAX-Updates (z.B. bei Varianten-Wechsel), wenn Sie das Frontend-JavaScript einbinden.

### Caching

Die Lieferzeit-Berechnungen werden gecacht für optimale Performance. Der Cache wird automatisch geleert bei:
- Produktaktualisierungen
- Einstellungsänderungen
- Warenkorb-Updates

## 🐛 Fehlerbehebung

### Shortcode zeigt nichts an

**Mögliche Ursachen**:
1. Kein Produkt im Kontext → Verwenden Sie `product_id="123"`
2. WooCommerce nicht aktiv → Prüfen Sie Plugin-Status
3. Produkt hat keine Lieferzeit-Daten → Konfigurieren Sie Produkt-Felder

### Styling funktioniert nicht

**Lösung**: Stellen Sie sicher, dass die Frontend-CSS-Datei geladen wird:

```php
// In functions.php (falls nötig)
wp_enqueue_style('wlm-frontend');
```

### Icons werden nicht angezeigt

**Ursache**: Emoji-Support fehlt im Theme

**Lösung**: Verwenden Sie `show_icon="no"` oder fügen Sie Emoji-Support hinzu.

## 📚 Weitere Ressourcen

- **Hauptdokumentation**: Siehe README.md
- **Installation**: Siehe INSTALLATION.md
- **REST API**: Siehe README.md → REST API Sektion
- **Support**: GitHub Issues oder E-Mail

## 🔄 Changelog

### Version 1.0.1
- ✨ Neue Shortcodes hinzugefügt
- 🎨 Oxygen Builder Unterstützung
- 📝 Umfassende Dokumentation

### Version 1.0.0
- 🚀 Initial Release
