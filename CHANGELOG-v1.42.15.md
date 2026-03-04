# Changelog v1.42.15

## Neue Funktion: SKU-Badge im Warenkorb

### Was wurde hinzugefügt?

Im Warenkorb wird jetzt bei jedem Produkt die Artikelnummer (SKU) als kleines Badge angezeigt.

### Technische Umsetzung

**Klassischer WooCommerce-Cart:**
- Neuer PHP-Hook `woocommerce_after_cart_item_name` (Priorität 15, nach dem Lagerstatus-Badge bei Priorität 10)
- Neue Methode `display_cart_item_sku_badge()` in `class-wlm-frontend.php`
- Gibt ein `<span class="wlm-sku-badge">` mit Label und SKU-Wert aus
- Wird nur angezeigt wenn das Produkt eine SKU hat

**Block-basierter WooCommerce-Cart:**
- SKU wird nun in den Store API-Daten unter `cart_items_stock[key].sku` mitgeliefert
- Das bestehende `blocks-cart-stock-status.js` injiziert zusätzlich CSS `::after`-Regeln auf `.wc-block-cart-item__prices`
- Gleiche visuelle Darstellung wie im klassischen Cart

**CSS:**
- Neue Klassen `.wlm-sku-badge`, `.wlm-sku-badge__label`, `.wlm-sku-badge__value` in `frontend.css`
- Leicht grauer Hintergrund (`#efefef`), abgerundete Ecken, kleine Schrift (11px)
- Kein Einfluss auf bestehende Styles

### Geänderte Dateien

- `woo-lieferzeiten-manager.php` — Version 1.42.14 → 1.42.15
- `includes/class-wlm-frontend.php` — Neuer Hook + neue Methode `display_cart_item_sku_badge()`
- `includes/class-wlm-blocks-integration.php` — SKU-Feld in `cart_items_stock` ergänzt
- `assets/js/blocks-cart-stock-status.js` — CSS `::after`-Regeln für SKU-Badge
- `assets/css/frontend.css` — Neue Badge-CSS-Klassen

### Keine Breaking Changes

Alle bestehenden Funktionen (Lagerstatus, Lieferfenster, Express, Zuschläge) bleiben unverändert.
