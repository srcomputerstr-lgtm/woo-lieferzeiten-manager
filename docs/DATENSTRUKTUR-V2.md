# Datenstruktur Version 2.0 - Bedingungssystem

## Übersicht

Version 1.2.0 führt ein neues, flexibles Bedingungssystem ein, inspiriert von Conditional Shipping.

## Versandarten-Struktur

```php
$shipping_method = array(
    // Basis-Informationen
    'id' => 'unique_id',
    'name' => 'Versandart Name',
    'enabled' => true,
    'priority' => 10,
    
    // Kosten
    'cost_type' => 'flat', // flat, by_weight, by_qty
    'cost' => 5.00,
    
    // Transitzeit
    'transit_min' => 1,
    'transit_max' => 3,
    
    // Express-Option
    'express_enabled' => false,
    'express_cutoff' => '12:00',
    'express_cost' => 9.90,
    'express_transit_min' => 0,
    'express_transit_max' => 1,
    
    // Bedingungen (NEU!)
    'conditions' => array(
        'logic' => 'AND', // AND oder OR zwischen Bedingungsgruppen
        'groups' => array(
            array(
                'type' => 'weight',
                'operator' => 'between', // between, less_than, greater_than, equals
                'min' => 5,
                'max' => 30
            ),
            array(
                'type' => 'cart_total',
                'operator' => 'greater_than',
                'value' => 50
            ),
            array(
                'type' => 'attributes',
                'logic' => 'at_least_one', // at_least_one, all, none, only
                'values' => array(
                    array(
                        'attribute' => 'pa_farbe',
                        'terms' => array('rot', 'blau', 'grün')
                    ),
                    array(
                        'attribute' => 'pa_groesse',
                        'terms' => array('xl', 'xxl')
                    )
                )
            ),
            array(
                'type' => 'categories',
                'logic' => 'all',
                'terms' => array(15, 23, 42) // Category IDs
            ),
            array(
                'type' => 'quantity',
                'operator' => 'between',
                'min' => 1,
                'max' => 10
            )
        )
    )
);
```

## Bedingungstypen

### 1. Gewicht (weight)
```php
array(
    'type' => 'weight',
    'operator' => 'between', // between, less_than, greater_than
    'min' => 5,
    'max' => 30,
    'unit' => 'kg'
)
```

### 2. Warenkorbwert (cart_total)
```php
array(
    'type' => 'cart_total',
    'operator' => 'between', // between, less_than, greater_than
    'min' => 50,
    'max' => 500
)
```

### 3. Stückzahl (quantity)
```php
array(
    'type' => 'quantity',
    'operator' => 'between',
    'min' => 1,
    'max' => 10
)
```

### 4. Produktattribute (attributes)
```php
array(
    'type' => 'attributes',
    'logic' => 'at_least_one', // at_least_one, all, none, only
    'values' => array(
        array(
            'attribute' => 'pa_farbe',
            'terms' => array('rot', 'blau')
        )
    )
)
```

### 5. Produktkategorien (categories)
```php
array(
    'type' => 'categories',
    'logic' => 'at_least_one', // at_least_one, all, none, only
    'terms' => array(15, 23, 42)
)
```

## Logik-Operatoren

### Für Attribute und Kategorien

- **at_least_one**: Mindestens einer der ausgewählten Werte muss zutreffen
- **all**: Alle ausgewählten Werte müssen zutreffen
- **none**: Keiner der ausgewählten Werte darf zutreffen
- **only**: Nur die ausgewählten Werte dürfen vorhanden sein

### Für numerische Werte

- **between**: Wert muss zwischen Min und Max liegen
- **less_than**: Wert muss kleiner als Max sein
- **greater_than**: Wert muss größer als Min sein
- **equals**: Wert muss genau gleich sein

## Verknüpfung von Bedingungen

```php
'conditions' => array(
    'logic' => 'AND', // Alle Bedingungsgruppen müssen erfüllt sein
    'groups' => array(
        // Bedingung 1 UND Bedingung 2 UND Bedingung 3
    )
)

// ODER

'conditions' => array(
    'logic' => 'OR', // Mindestens eine Bedingungsgruppe muss erfüllt sein
    'groups' => array(
        // Bedingung 1 ODER Bedingung 2 ODER Bedingung 3
    )
)
```

## Migration von alter Struktur

Alte Struktur (Version 1.x):
```php
'weight_min' => 5,
'weight_max' => 30,
'cart_total_min' => 50,
'attribute_conditions' => array(
    array('attribute' => 'pa_farbe', 'value' => 'rot')
)
```

Wird konvertiert zu:
```php
'conditions' => array(
    'logic' => 'AND',
    'groups' => array(
        array(
            'type' => 'weight',
            'operator' => 'between',
            'min' => 5,
            'max' => 30
        ),
        array(
            'type' => 'cart_total',
            'operator' => 'greater_than',
            'value' => 50
        ),
        array(
            'type' => 'attributes',
            'logic' => 'at_least_one',
            'values' => array(
                array(
                    'attribute' => 'pa_farbe',
                    'terms' => array('rot')
                )
            )
        )
    )
)
```

## Vorteile der neuen Struktur

1. **Flexibler**: Beliebige Kombinationen von Bedingungen
2. **Erweiterbar**: Neue Bedingungstypen einfach hinzufügbar
3. **Logisch**: Klare AND/OR-Verknüpfung
4. **Multi-Value**: Mehrere Werte pro Attribut
5. **Übersichtlich**: Strukturierte Datenorganisation
