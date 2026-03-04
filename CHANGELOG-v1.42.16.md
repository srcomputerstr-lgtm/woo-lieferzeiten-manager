# Changelog v1.42.16

## Bugfix: SKU-Badge im Block-Cart korrigiert

### Problem

In v1.42.15 wurde die SKU-Badge im Block-Cart auf `.wc-block-cart-item__prices::after` gesetzt — dieses Element existiert im Block-Cart nicht zuverlässig.

### Lösung

Die SKU-Badge verwendet jetzt dasselbe Element wie der Lagerstatus (`::before`), nur mit `::after`:

```
.wc-block-cart-items__row:nth-child(N) .wc-block-cart-item__quantity::after
```

Damit erscheint die SKU-Badge direkt unterhalb des Lagerstatus im Mengen-Container, genau wie der `::before`-Lagerstatus darüber.

### Geänderte Dateien

- `woo-lieferzeiten-manager.php` — Version 1.42.15 → 1.42.16
- `assets/js/blocks-cart-stock-status.js` — Selektor von `__prices::after` auf `__quantity::after` geändert
