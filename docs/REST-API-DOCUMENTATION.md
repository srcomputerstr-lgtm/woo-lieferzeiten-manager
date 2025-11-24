# MEGA Versandmanager - REST API Dokumentation

## Übersicht

Das Plugin bietet eine REST API zum Verwalten von Produktverfügbarkeiten und Lieferzeiten.

**Base URL:** `https://ihre-domain.de/wp-json/wlm/v1`

**Namespace:** `wlm/v1`

---

## Authentifizierung

Alle Endpunkte (außer GET-Anfragen) erfordern WordPress-Authentifizierung mit `edit_products` Berechtigung.

### Methode 1: Application Passwords (Empfohlen)

1. WordPress Admin → Benutzer → Profil
2. Scrollen zu "Anwendungspasswörter"
3. Name eingeben (z.B. "ERP System") → "Neues Anwendungspasswort hinzufügen"
4. Passwort kopieren (wird nur einmal angezeigt!)

**Verwendung:**
```bash
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/availability
```

### Methode 2: Cookie-basiert

Für AJAX-Anfragen aus dem WordPress-Backend:
```javascript
fetch('/wp-json/wlm/v1/products/123/availability', {
  method: 'POST',
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ available_from: '2025-12-25' })
});
```

---

## Endpunkte

### 1. Verfügbarkeitsdatum setzen

Setzt das manuelle "Lieferbar ab" Datum für ein Produkt.

**Endpoint:** `POST /products/{id}/availability`

**Parameter:**
- `id` (required, integer) - Produkt-ID
- `available_from` (required, string) - Datum im Format `YYYY-MM-DD`

**Beispiel:**
```bash
curl -X POST \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"available_from":"2025-12-20"}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/availability
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Verfügbarkeitsdatum aktualisiert",
  "data": {
    "product_id": 123,
    "available_from": "2025-12-20"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Produkt nicht gefunden"
}
```

---

### 2. Lieferzeit in Tagen setzen

Setzt die Lieferzeit in Werktagen. Das System berechnet automatisch das Verfügbarkeitsdatum.

**Endpoint:** `POST /products/{id}/lead-time`

**Parameter:**
- `id` (required, integer) - Produkt-ID
- `lead_time_days` (required, integer) - Lieferzeit in Werktagen (≥ 0)

**Beispiel:**
```bash
curl -X POST \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"lead_time_days":21}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/lead-time
```

**Response:**
```json
{
  "success": true,
  "message": "Lieferzeit aktualisiert",
  "data": {
    "product_id": 123,
    "lead_time_days": 21,
    "available_from": "2025-12-26"
  }
}
```

**Hinweis:** Das `available_from` Datum wird automatisch berechnet basierend auf `lead_time_days`.

---

### 3. Batch-Update für mehrere Produkte

Aktualisiert mehrere Produkte in einer Anfrage.

**Endpoint:** `POST /products/batch`

**Parameter:**
- `products` (required, array) - Array von Produkt-Objekten

**Produkt-Objekt:**
```json
{
  "id": 123,
  "available_from": "2025-12-20",  // Optional
  "lead_time_days": 21              // Optional
}
```

**Beispiel:**
```bash
curl -X POST \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "products": [
      {"id": 123, "available_from": "2025-12-20"},
      {"id": 456, "lead_time_days": 14},
      {"id": 789, "available_from": "2025-12-25", "lead_time_days": 7}
    ]
  }' \
  https://ihre-domain.de/wp-json/wlm/v1/products/batch
```

**Response:**
```json
{
  "success": true,
  "message": "3 Produkte aktualisiert, 0 fehlgeschlagen",
  "results": {
    "success": [
      {"id": 123, "message": "Erfolgreich aktualisiert"},
      {"id": 456, "message": "Erfolgreich aktualisiert"},
      {"id": 789, "message": "Erfolgreich aktualisiert"}
    ],
    "failed": []
  }
}
```

---

### 4. Lieferinformationen abrufen

Ruft alle Lieferinformationen für ein Produkt ab.

**Endpoint:** `GET /products/{id}/delivery-info`

**Parameter:**
- `id` (required, integer) - Produkt-ID

**Beispiel:**
```bash
curl https://ihre-domain.de/wp-json/wlm/v1/products/123/delivery-info
```

**Response:**
```json
{
  "success": true,
  "data": {
    "product_id": 123,
    "available_from": "2025-12-20",
    "lead_time_days": "21",
    "calculated_available_date": "2025-12-26",
    "stock_status": "instock",
    "stock_quantity": 122,
    "delivery_window": {
      "start": "2025-11-18",
      "end": "2025-11-21",
      "window_formatted": "Mo, 18.11. – Do, 21.11."
    }
  }
}
```

**Hinweis:** Dieser Endpunkt erfordert KEINE Authentifizierung (öffentlich zugänglich).

---

## Logik: Welches Datum wird verwendet?

Das Plugin verwendet eine **Prioritäts-Logik** bei der Datumsauswahl:

### Priorität 1: Manuelles "Lieferbar ab" Datum
```
IF "Lieferbar ab" (_wlm_available_from) befüllt AND >= Heute
  → Verwende dieses Datum
```

**Setzen via API:**
```bash
POST /products/123/availability
{"available_from": "2025-12-20"}
```

### Priorität 2: Berechnetes Verfügbarkeitsdatum
```
ELSE IF "Berechnetes Verfügbarkeitsdatum" (_wlm_calculated_available_date) befüllt AND >= Heute
  → Verwende dieses Datum
```

**Wird automatisch gesetzt durch:**
- Täglichen Cronjob (1:00 Uhr nachts)
- Basierend auf "Lieferzeit (Tage)" + heutiges Datum

### Priorität 3: On-the-fly Berechnung
```
ELSE IF "Lieferzeit (Tage)" (_wlm_lead_time_days) > 0
  → Berechne: Heute + Lieferzeit(Tage) = Verfügbarkeitsdatum
```

**Setzen via API:**
```bash
POST /products/123/lead-time
{"lead_time_days": 21}
```

### Priorität 4: Fallback
```
ELSE
  → Sofort verfügbar (current_time)
```

---

## Anwendungsfälle

### Use Case 1: ERP-System setzt Verfügbarkeitsdatum

Ihr ERP-System weiß genau wann ein Produkt wieder verfügbar ist:

```bash
# Produkt 123 ist ab 20.12.2025 wieder verfügbar
curl -X POST \
  -u "erp-user:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"available_from":"2025-12-20"}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/availability
```

**Ergebnis:**
- Frontend zeigt: "Wieder verfügbar ab: Fr, 20.12."
- Cronjob überschreibt dieses Datum **NICHT**
- Datum bleibt bis es manuell geändert oder gelöscht wird

---

### Use Case 2: Lieferant gibt Lieferzeit in Tagen an

Ihr Lieferant sagt: "Produkt kommt in 21 Werktagen":

```bash
curl -X POST \
  -u "erp-user:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"lead_time_days":21}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/lead-time
```

**Ergebnis:**
- System berechnet: Heute + 21 Werktage = 26.12.2025
- Cronjob aktualisiert täglich das berechnete Datum
- Frontend zeigt immer aktuelles Datum

---

### Use Case 3: Bulk-Update aus CSV/Excel

Sie haben eine CSV mit Verfügbarkeitsdaten:

```csv
product_id,available_from
123,2025-12-20
456,2025-12-25
789,2026-01-05
```

**Python-Script:**
```python
import csv
import requests
from requests.auth import HTTPBasicAuth

auth = HTTPBasicAuth('username', 'xxxx xxxx xxxx xxxx xxxx xxxx')
base_url = 'https://ihre-domain.de/wp-json/wlm/v1'

# Lese CSV
with open('availability.csv', 'r') as f:
    reader = csv.DictReader(f)
    products = [
        {
            'id': int(row['product_id']),
            'available_from': row['available_from']
        }
        for row in reader
    ]

# Batch-Update
response = requests.post(
    f'{base_url}/products/batch',
    json={'products': products},
    auth=auth
)

print(response.json())
```

---

### Use Case 4: Datum löschen (zurück zu automatischer Berechnung)

Um ein manuelles Datum zu entfernen und zurück zur automatischen Berechnung zu wechseln:

```bash
# Leeres Datum setzen
curl -X POST \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"available_from":""}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/availability
```

**Oder via WooCommerce REST API:**
```bash
curl -X PUT \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"meta_data":[{"key":"_wlm_available_from","value":""}]}' \
  https://ihre-domain.de/wp-json/wc/v3/products/123
```

---

## Fehlerbehandlung

### HTTP Status Codes

- `200` - Erfolg
- `404` - Produkt nicht gefunden
- `400` - Ungültige Parameter
- `401` - Nicht authentifiziert
- `403` - Keine Berechtigung

### Beispiel-Fehler

**Produkt nicht gefunden:**
```json
{
  "success": false,
  "message": "Produkt nicht gefunden"
}
```

**Ungültiges Datum:**
```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): available_from",
  "data": {
    "status": 400,
    "params": {
      "available_from": "available_from must match pattern /^\\d{4}-\\d{2}-\\d{2}$/"
    }
  }
}
```

---

## Testing

### Mit cURL testen

```bash
# Test 1: Verfügbarkeitsdatum setzen
curl -X POST \
  -u "username:password" \
  -H "Content-Type: application/json" \
  -d '{"available_from":"2025-12-20"}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/availability

# Test 2: Lieferzeit setzen
curl -X POST \
  -u "username:password" \
  -H "Content-Type: application/json" \
  -d '{"lead_time_days":21}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/123/lead-time

# Test 3: Lieferinformationen abrufen
curl https://ihre-domain.de/wp-json/wlm/v1/products/123/delivery-info
```

### Mit Postman testen

1. **Authorization:**
   - Type: Basic Auth
   - Username: Ihr WordPress-Username
   - Password: Application Password

2. **Headers:**
   - `Content-Type: application/json`

3. **Body (raw JSON):**
   ```json
   {
     "available_from": "2025-12-20"
   }
   ```

---

## Cronjob-Integration

Das berechnete Verfügbarkeitsdatum wird täglich automatisch aktualisiert.

**Cronjob-Zeit konfigurieren:**
1. WooCommerce → Einstellungen → Versand → MEGA Versandmanager
2. Tab "Zeiten"
3. "Cronjob-Zeit" einstellen (z.B. 01:00)

**Manuell ausführen:**
- Button "Jetzt ausführen" im Admin-Panel

**Was macht der Cronjob:**
```
FOR EACH Produkt WHERE "Lieferzeit (Tage)" > 0:
  Berechne: Heute + Lieferzeit(Tage) = Neues Datum
  Speichere in "_wlm_calculated_available_date"
```

**Wichtig:** Der Cronjob überschreibt **NIE** das manuelle "Lieferbar ab" Datum!

---

## Support

Bei Fragen zur API:
- GitHub: https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager
- Support: https://help.manus.im

---

**Version:** 1.10.0  
**Letzte Aktualisierung:** 14.11.2025
