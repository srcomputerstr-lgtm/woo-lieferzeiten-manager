# ERP Integration Guide - SKU-basierte API

## 🎯 Für ERP-Systeme optimiert

Dieser Guide zeigt Ihnen **genau**, wie Sie Verfügbarkeitsdaten aus Ihrem ERP-System an WooCommerce senden.

---

## ⚡ Schnellstart

### Einzelnes Produkt aktualisieren (via SKU)

```bash
curl -X POST \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "available_from": "2025-12-20",
    "lead_time_days": 21
  }' \
  https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/availability
```

**Das war's!** 🎉

---

## 📋 Schritt-für-Schritt Anleitung

### Schritt 1: Application Password erstellen

1. WordPress Admin einloggen
2. **Benutzer** → **Profil** öffnen
3. Scrollen zu **"Anwendungspasswörter"**
4. Name eingeben: `ERP System`
5. **"Neues Anwendungspasswort hinzufügen"** klicken
6. Passwort kopieren (z.B. `abcd efgh ijkl mnop qrst uvwx`)

⚠️ **Wichtig:** Passwort wird nur einmal angezeigt!

---

### Schritt 2: HTTP Request aufbauen

#### **URL-Struktur:**

```
POST https://ihre-domain.de/wp-json/wlm/v1/products/sku/{SKU}/availability
```

Ersetzen Sie:
- `ihre-domain.de` → Ihre WooCommerce-Domain
- `{SKU}` → Ihre Artikelnummer (z.B. `ART-12345`)

#### **Headers:**

```
Authorization: Basic base64(username:password)
Content-Type: application/json
```

#### **Body (JSON):**

```json
{
  "available_from": "2025-12-20",
  "lead_time_days": 21
}
```

**Beide Felder sind optional!** Sie können auch nur eines senden.

---

## 🔐 Authentifizierung

### Methode: HTTP Basic Auth

**Format:**
```
Authorization: Basic base64(username:password)
```

**Beispiel:**
```
Username: admin
Password: abcd efgh ijkl mnop qrst uvwx

Base64: YWRtaW46YWJjZCBlZmdoIGlqa2wgbW5vcCBxcnN0IHV2d3g=

Header: Authorization: Basic YWRtaW46YWJjZCBlZmdoIGlqa2wgbW5vcCBxcnN0IHV2d3g=
```

**Tools zum Encodieren:**
- Online: https://www.base64encode.org/
- Linux/Mac: `echo -n "username:password" | base64`
- Python: `base64.b64encode(b"username:password").decode()`

---

## 📊 JSON-Struktur

### Einzelnes Produkt

```json
{
  "available_from": "2025-12-20",
  "lead_time_days": 21
}
```

**Felder:**

| Feld | Typ | Pflicht | Format | Beschreibung |
|------|-----|---------|--------|--------------|
| `available_from` | String | Nein | `YYYY-MM-DD` | Manuelles Verfügbarkeitsdatum |
| `lead_time_days` | Integer | Nein | `>= 0` | Lieferzeit in Werktagen |

**Varianten:**

```json
// Nur Datum
{"available_from": "2025-12-20"}

// Nur Lieferzeit
{"lead_time_days": 21}

// Beides
{"available_from": "2025-12-20", "lead_time_days": 21}

// Datum löschen (zurück zu automatisch)
{"available_from": ""}
```

---

### Batch-Update (mehrere Produkte)

```json
{
  "products": [
    {
      "sku": "ART-12345",
      "available_from": "2025-12-20"
    },
    {
      "sku": "ART-67890",
      "lead_time_days": 14
    },
    {
      "sku": "ART-11111",
      "available_from": "2025-12-25",
      "lead_time_days": 7
    }
  ]
}
```

**Endpoint:**
```
POST https://ihre-domain.de/wp-json/wlm/v1/products/sku/batch
```

---

## 💻 Code-Beispiele

### cURL (Linux/Mac/Windows)

```bash
curl -X POST \
  -u "admin:abcd efgh ijkl mnop qrst uvwx" \
  -H "Content-Type: application/json" \
  -d '{"available_from":"2025-12-20","lead_time_days":21}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/availability
```

---

### Python

```python
import requests
from requests.auth import HTTPBasicAuth

# Konfiguration
BASE_URL = "https://ihre-domain.de/wp-json/wlm/v1"
USERNAME = "admin"
PASSWORD = "abcd efgh ijkl mnop qrst uvwx"

# Einzelnes Produkt aktualisieren
def update_product(sku, available_from=None, lead_time_days=None):
    url = f"{BASE_URL}/products/sku/{sku}/availability"
    
    data = {}
    if available_from:
        data['available_from'] = available_from
    if lead_time_days is not None:
        data['lead_time_days'] = lead_time_days
    
    response = requests.post(
        url,
        json=data,
        auth=HTTPBasicAuth(USERNAME, PASSWORD)
    )
    
    return response.json()

# Beispiel-Aufruf
result = update_product(
    sku="ART-12345",
    available_from="2025-12-20",
    lead_time_days=21
)

print(result)
```

**Batch-Update:**

```python
def batch_update_products(products):
    url = f"{BASE_URL}/products/sku/batch"
    
    response = requests.post(
        url,
        json={'products': products},
        auth=HTTPBasicAuth(USERNAME, PASSWORD)
    )
    
    return response.json()

# Beispiel
products = [
    {"sku": "ART-12345", "available_from": "2025-12-20"},
    {"sku": "ART-67890", "lead_time_days": 14},
    {"sku": "ART-11111", "available_from": "2025-12-25"}
]

result = batch_update_products(products)
print(result)
```

---

### PHP

```php
<?php

// Konfiguration
$base_url = 'https://ihre-domain.de/wp-json/wlm/v1';
$username = 'admin';
$password = 'abcd efgh ijkl mnop qrst uvwx';

// Einzelnes Produkt aktualisieren
function updateProduct($sku, $available_from = null, $lead_time_days = null) {
    global $base_url, $username, $password;
    
    $url = "$base_url/products/sku/$sku/availability";
    
    $data = [];
    if ($available_from) {
        $data['available_from'] = $available_from;
    }
    if ($lead_time_days !== null) {
        $data['lead_time_days'] = $lead_time_days;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("$username:$password")
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Beispiel-Aufruf
$result = updateProduct(
    'ART-12345',
    '2025-12-20',
    21
);

print_r($result);
?>
```

---

### C# (.NET)

```csharp
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

public class WooCommerceAPI
{
    private readonly string baseUrl;
    private readonly string username;
    private readonly string password;
    private readonly HttpClient client;

    public WooCommerceAPI(string baseUrl, string username, string password)
    {
        this.baseUrl = baseUrl;
        this.username = username;
        this.password = password;
        this.client = new HttpClient();
        
        // Set Basic Auth
        var authBytes = Encoding.UTF8.GetBytes($"{username}:{password}");
        var authHeader = Convert.ToBase64String(authBytes);
        client.DefaultRequestHeaders.Authorization = 
            new AuthenticationHeaderValue("Basic", authHeader);
    }

    public async Task<string> UpdateProduct(
        string sku, 
        string availableFrom = null, 
        int? leadTimeDays = null)
    {
        var url = $"{baseUrl}/products/sku/{sku}/availability";
        
        var data = new Dictionary<string, object>();
        if (availableFrom != null)
            data["available_from"] = availableFrom;
        if (leadTimeDays.HasValue)
            data["lead_time_days"] = leadTimeDays.Value;
        
        var json = JsonSerializer.Serialize(data);
        var content = new StringContent(json, Encoding.UTF8, "application/json");
        
        var response = await client.PostAsync(url, content);
        return await response.Content.ReadAsStringAsync();
    }
}

// Verwendung
var api = new WooCommerceAPI(
    "https://ihre-domain.de/wp-json/wlm/v1",
    "admin",
    "abcd efgh ijkl mnop qrst uvwx"
);

var result = await api.UpdateProduct(
    "ART-12345",
    "2025-12-20",
    21
);

Console.WriteLine(result);
```

---

### Java

```java
import java.net.http.*;
import java.net.URI;
import java.util.Base64;

public class WooCommerceAPI {
    private final String baseUrl;
    private final String authHeader;
    private final HttpClient client;

    public WooCommerceAPI(String baseUrl, String username, String password) {
        this.baseUrl = baseUrl;
        String auth = username + ":" + password;
        this.authHeader = "Basic " + Base64.getEncoder()
            .encodeToString(auth.getBytes());
        this.client = HttpClient.newHttpClient();
    }

    public String updateProduct(String sku, String availableFrom, Integer leadTimeDays) 
            throws Exception {
        String url = baseUrl + "/products/sku/" + sku + "/availability";
        
        StringBuilder json = new StringBuilder("{");
        if (availableFrom != null) {
            json.append("\"available_from\":\"").append(availableFrom).append("\"");
        }
        if (leadTimeDays != null) {
            if (availableFrom != null) json.append(",");
            json.append("\"lead_time_days\":").append(leadTimeDays);
        }
        json.append("}");
        
        HttpRequest request = HttpRequest.newBuilder()
            .uri(URI.create(url))
            .header("Content-Type", "application/json")
            .header("Authorization", authHeader)
            .POST(HttpRequest.BodyPublishers.ofString(json.toString()))
            .build();
        
        HttpResponse<String> response = client.send(
            request, 
            HttpResponse.BodyHandlers.ofString()
        );
        
        return response.body();
    }

    public static void main(String[] args) throws Exception {
        WooCommerceAPI api = new WooCommerceAPI(
            "https://ihre-domain.de/wp-json/wlm/v1",
            "admin",
            "abcd efgh ijkl mnop qrst uvwx"
        );
        
        String result = api.updateProduct("ART-12345", "2025-12-20", 21);
        System.out.println(result);
    }
}
```

---

## 📤 Response-Beispiele

### Erfolg (200 OK)

```json
{
  "success": true,
  "message": "Produkt aktualisiert",
  "data": {
    "product_id": 123,
    "sku": "ART-12345",
    "updated_fields": {
      "available_from": "2025-12-20",
      "lead_time_days": 21
    }
  }
}
```

---

### Produkt nicht gefunden (404)

```json
{
  "success": false,
  "message": "Produkt mit SKU \"ART-99999\" nicht gefunden"
}
```

---

### Batch-Update Response

```json
{
  "success": true,
  "message": "3 Produkte aktualisiert, 1 fehlgeschlagen",
  "results": {
    "success": [
      {
        "sku": "ART-12345",
        "product_id": 123,
        "message": "Erfolgreich aktualisiert"
      },
      {
        "sku": "ART-67890",
        "product_id": 456,
        "message": "Erfolgreich aktualisiert"
      }
    ],
    "failed": [
      {
        "sku": "ART-99999",
        "message": "Produkt mit SKU \"ART-99999\" nicht gefunden"
      }
    ]
  }
}
```

---

## 🔄 CSV-Import Workflow

### Schritt 1: CSV-Datei vorbereiten

```csv
sku,available_from,lead_time_days
ART-12345,2025-12-20,21
ART-67890,2025-12-25,14
ART-11111,,7
ART-22222,2026-01-05,
```

**Hinweis:** Leere Felder werden ignoriert.

---

### Schritt 2: Python-Script

```python
import csv
import requests
from requests.auth import HTTPBasicAuth

BASE_URL = "https://ihre-domain.de/wp-json/wlm/v1"
USERNAME = "admin"
PASSWORD = "abcd efgh ijkl mnop qrst uvwx"

# CSV lesen und Batch-Update
with open('products.csv', 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    products = []
    
    for row in reader:
        product = {'sku': row['sku']}
        
        if row['available_from']:
            product['available_from'] = row['available_from']
        
        if row['lead_time_days']:
            product['lead_time_days'] = int(row['lead_time_days'])
        
        products.append(product)

# Batch-Update senden
response = requests.post(
    f"{BASE_URL}/products/sku/batch",
    json={'products': products},
    auth=HTTPBasicAuth(USERNAME, PASSWORD)
)

result = response.json()
print(f"✅ Erfolgreich: {len(result['results']['success'])}")
print(f"❌ Fehlgeschlagen: {len(result['results']['failed'])}")

# Details ausgeben
for item in result['results']['failed']:
    print(f"  - {item['sku']}: {item['message']}")
```

---

## 🧪 Testing

### 1. Mit cURL testen

```bash
# Test 1: Produkt-Info abrufen (ohne Auth)
curl https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/delivery-info

# Test 2: Verfügbarkeitsdatum setzen
curl -X POST \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{"available_from":"2025-12-20"}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/availability

# Test 3: Lieferzeit setzen
curl -X POST \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{"lead_time_days":21}' \
  https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/availability
```

---

### 2. Mit Postman testen

**Request Setup:**

1. **Method:** POST
2. **URL:** `https://ihre-domain.de/wp-json/wlm/v1/products/sku/ART-12345/availability`
3. **Authorization:**
   - Type: Basic Auth
   - Username: `admin`
   - Password: `abcd efgh ijkl mnop qrst uvwx`
4. **Headers:**
   - `Content-Type: application/json`
5. **Body (raw JSON):**
   ```json
   {
     "available_from": "2025-12-20",
     "lead_time_days": 21
   }
   ```

---

## ⚙️ Erweiterte Szenarien

### Szenario 1: Täglicher Sync aus ERP

```python
import schedule
import time

def sync_availability():
    # Daten aus ERP-Datenbank holen
    products = get_products_from_erp()
    
    # An WooCommerce senden
    result = batch_update_products(products)
    
    print(f"Sync abgeschlossen: {len(result['results']['success'])} Produkte")

# Täglich um 2:00 Uhr ausführen
schedule.every().day.at("02:00").do(sync_availability)

while True:
    schedule.run_pending()
    time.sleep(60)
```

---

### Szenario 2: Webhook bei Lieferanten-Update

```python
from flask import Flask, request

app = Flask(__name__)

@app.route('/webhook/supplier', methods=['POST'])
def supplier_webhook():
    data = request.json
    
    # Daten aus Webhook extrahieren
    sku = data['article_number']
    available_from = data['delivery_date']
    
    # An WooCommerce senden
    result = update_product(sku, available_from=available_from)
    
    return {'status': 'success'}

app.run(port=5000)
```

---

## 🚨 Fehlerbehandlung

### Retry-Logik (Python)

```python
import time

def update_product_with_retry(sku, max_retries=3, **kwargs):
    for attempt in range(max_retries):
        try:
            result = update_product(sku, **kwargs)
            
            if result.get('success'):
                return result
            else:
                print(f"Fehler: {result.get('message')}")
                
        except requests.exceptions.RequestException as e:
            print(f"Versuch {attempt + 1} fehlgeschlagen: {e}")
            
            if attempt < max_retries - 1:
                time.sleep(2 ** attempt)  # Exponential backoff
            else:
                raise
    
    return None
```

---

## 📞 Support

Bei Fragen zur ERP-Integration:
- GitHub: https://github.com/srcomputerstr-lgtm/woo-lieferzeiten-manager
- Support: https://help.manus.im

---

**Version:** 1.10.0  
**Letzte Aktualisierung:** 14.11.2025
