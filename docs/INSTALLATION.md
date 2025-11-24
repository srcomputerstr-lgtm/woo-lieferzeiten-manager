# Installationsanleitung – Woo Lieferzeiten Manager

Diese Anleitung führt Sie Schritt für Schritt durch die Installation und Erstkonfiguration des **Woo Lieferzeiten Manager** Plugins für WooCommerce.

## Systemvoraussetzungen

Stellen Sie vor der Installation sicher, dass Ihr System folgende Anforderungen erfüllt:

- **WordPress**: Version 6.0 oder höher
- **PHP**: Version 7.4 oder höher
- **WooCommerce**: Version 8.0 oder höher
- **MySQL**: Version 5.7 oder höher (oder MariaDB 10.3+)
- **Webserver**: Apache oder Nginx mit mod_rewrite aktiviert

### Empfohlene Umgebung

Für optimale Performance empfehlen wir:
- PHP 8.1 oder höher
- WooCommerce 9.0 oder höher
- Mindestens 128 MB PHP Memory Limit
- HTTPS-Verschlüsselung für die REST API

## Installation

### Methode 1: Upload über WordPress-Admin

1. Laden Sie die Datei `woo-lieferzeiten-manager.zip` herunter
2. Melden Sie sich in Ihrem WordPress-Admin-Bereich an
3. Navigieren Sie zu **Plugins → Installieren**
4. Klicken Sie auf **Plugin hochladen**
5. Wählen Sie die ZIP-Datei aus und klicken Sie auf **Jetzt installieren**
6. Nach erfolgreicher Installation klicken Sie auf **Plugin aktivieren**

### Methode 2: Manuelle Installation via FTP

1. Entpacken Sie die Datei `woo-lieferzeiten-manager.zip`
2. Verbinden Sie sich via FTP mit Ihrem Webserver
3. Laden Sie den entpackten Ordner `woo-lieferzeiten-manager` in das Verzeichnis `/wp-content/plugins/` hoch
4. Melden Sie sich in Ihrem WordPress-Admin-Bereich an
5. Navigieren Sie zu **Plugins → Installierte Plugins**
6. Suchen Sie nach **Woo Lieferzeiten Manager** und klicken Sie auf **Aktivieren**

### Methode 3: Installation via WP-CLI

Wenn Sie Zugriff auf die Kommandozeile haben:

```bash
wp plugin install /pfad/zu/woo-lieferzeiten-manager.zip --activate
```

## Erstkonfiguration

Nach der Aktivierung des Plugins sollten Sie die Grundeinstellungen konfigurieren.

### Schritt 1: Zeiteinstellungen

1. Navigieren Sie zu **WooCommerce → Lieferzeiten**
2. Wählen Sie den Tab **Zeiten**
3. Konfigurieren Sie folgende Einstellungen:

#### Cutoff-Zeit
Legen Sie die Bestellannahmeschlusszeit fest (z.B. 14:00). Bestellungen nach dieser Zeit werden erst am nächsten Werktag bearbeitet.

#### Werktage
Wählen Sie die Tage aus, an denen Bestellungen bearbeitet werden. Standardmäßig sind Montag bis Freitag ausgewählt.

#### Feiertage
Fügen Sie Feiertage hinzu, die bei der Lieferzeitberechnung ausgeschlossen werden sollen:
- Klicken Sie auf **Feiertag hinzufügen**
- Wählen Sie das Datum aus
- Wiederholen Sie dies für alle relevanten Feiertage

#### Bearbeitungszeit
- **Min**: Minimale Bearbeitungszeit in Werktagen (z.B. 1)
- **Max**: Maximale Bearbeitungszeit in Werktagen (z.B. 2)

#### Standard-Lieferzeit
Legen Sie eine Fallback-Lieferzeit für Produkte ohne spezifische Angabe fest (z.B. 3 Tage).

#### Maximal sichtbarer Bestand
Definieren Sie die maximale Anzahl, die im Frontend als Lagerbestand angezeigt wird (z.B. 100 Stück).

4. Klicken Sie auf **Änderungen speichern**

### Schritt 2: Versandarten konfigurieren

1. Wechseln Sie zum Tab **Versandarten**
2. Klicken Sie auf **Versandart hinzufügen**
3. Konfigurieren Sie Ihre erste Versandart:

#### Beispiel: Standardversand

- **Name**: Paketdienst
- **Priorität**: 10
- **Kostentyp**: Nach Gewicht
- **Kosten**: 4.90 (Basispreis)
- **Gewicht Min**: 0 kg
- **Gewicht Max**: 30 kg
- **Transitzeit Min**: 1 Werktag
- **Transitzeit Max**: 3 Werktage

#### Express-Option (optional)

- **Express aktivieren**: ✓ aktiviert
- **Express-Zuschlag**: 9.90 €
- **Express Cutoff-Zeit**: 14:00

4. Fügen Sie weitere Versandarten nach Bedarf hinzu
5. Klicken Sie auf **Änderungen speichern**

### Schritt 3: Zuschläge einrichten (optional)

Wenn Sie Zuschläge für Sperrgut, Gefahrgut oder andere Sonderfälle benötigen:

1. Wechseln Sie zum Tab **Zuschläge**
2. Klicken Sie auf **Zuschlag hinzufügen**
3. Konfigurieren Sie den Zuschlag:

#### Beispiel: Sperrgut-Zuschlag

- **Name**: Sperrgut-Zuschlag
- **Aktiviert**: ✓
- **Betrag**: 24.90 €
- **Steuerklasse**: Standard
- **Gewicht Min**: 30 kg
- **Produktattribute**: `pa_sperrgut=ja`
- **Stacking-Regel**: Addieren
- **Optionen**:
  - ✓ Versandkostenfreigrenze ignorieren
  - ✓ Auch bei Express anwenden

4. Klicken Sie auf **Änderungen speichern**

## Produktkonfiguration

Nach der Grundkonfiguration sollten Sie Ihre Produkte mit Lieferzeitinformationen versehen.

### Einzelne Produkte konfigurieren

1. Navigieren Sie zu **Produkte → Alle Produkte**
2. Öffnen Sie ein Produkt zur Bearbeitung
3. Scrollen Sie zum Tab **Lagerbestand**
4. Im Abschnitt **Lieferzeiten** finden Sie:

#### Lieferbar ab
Geben Sie ein Datum ein (Format: YYYY-MM-DD), ab dem das Produkt verfügbar ist. Wenn Sie dieses Feld leer lassen, wird es automatisch basierend auf der Lead-Time berechnet.

#### Lieferzeit (Tage)
Geben Sie die Anzahl der Werktage bis zur Verfügbarkeit ein (z.B. 5). Dies wird zur automatischen Berechnung des "Lieferbar ab"-Datums verwendet.

#### Maximal sichtbarer Bestand
Optional: Überschreiben Sie die globale Einstellung für dieses spezifische Produkt.

5. Klicken Sie auf **Aktualisieren**

### Variable Produkte

Bei variablen Produkten können Sie die Lieferzeitfelder für jede Variante individuell konfigurieren:

1. Öffnen Sie ein variables Produkt
2. Wechseln Sie zum Tab **Variationen**
3. Erweitern Sie eine Variante
4. Scrollen Sie zu den **Lieferzeiten**-Feldern
5. Konfigurieren Sie die Felder wie bei einfachen Produkten
6. Klicken Sie auf **Änderungen speichern**

## REST API Einrichtung (für ERP-Integration)

Wenn Sie die REST API für automatisierte ERP-Updates nutzen möchten:

### Schritt 1: Application Password erstellen

1. Navigieren Sie zu **Benutzer → Ihr Profil**
2. Scrollen Sie zum Abschnitt **Anwendungspasswörter**
3. Geben Sie einen Namen ein (z.B. "ERP System")
4. Klicken Sie auf **Neues Anwendungspasswort hinzufügen**
5. Kopieren Sie das generierte Passwort (wird nur einmal angezeigt)

### Schritt 2: API-Zugriff testen

Testen Sie den API-Zugriff mit einem Tool wie cURL oder Postman:

```bash
curl -X GET "https://ihre-domain.de/wp-json/wlm/v1/products/123/delivery-info" \
  -u "benutzername:anwendungspasswort"
```

### Schritt 3: ERP-System konfigurieren

Konfigurieren Sie Ihr ERP-System mit folgenden Informationen:

- **Base URL**: `https://ihre-domain.de/wp-json/wlm/v1`
- **Authentifizierung**: HTTP Basic Auth
- **Benutzername**: Ihr WordPress-Benutzername
- **Passwort**: Das generierte Anwendungspasswort

## WooCommerce Blocks Integration

Das Plugin unterstützt automatisch die neuen WooCommerce Cart und Checkout Blocks.

### Blocks aktivieren

Wenn Sie noch die klassischen Shortcode-basierten Seiten verwenden:

1. Navigieren Sie zu **WooCommerce → Einstellungen → Erweitert**
2. Aktivieren Sie **Cart- und Checkout-Blöcke verwenden**
3. Die Lieferzeitinformationen werden automatisch in den Blocks angezeigt

### Blocks anpassen

Die Lieferzeitanzeige wird automatisch in die Checkout-Zusammenfassung integriert. Sie können die Position über den Block-Editor anpassen:

1. Bearbeiten Sie die Checkout-Seite
2. Suchen Sie nach dem Block **Woo Lieferzeiten Manager / Delivery Window**
3. Verschieben Sie ihn an die gewünschte Position

## Überprüfung der Installation

Nach Abschluss der Konfiguration sollten Sie folgende Tests durchführen:

### Frontend-Test

1. **Produktseite**: Öffnen Sie eine Produktseite und überprüfen Sie, ob das Lieferfenster-Panel angezeigt wird
2. **Warenkorb**: Fügen Sie Produkte zum Warenkorb hinzu und prüfen Sie die Lieferzeitanzeige
3. **Checkout**: Gehen Sie zur Kasse und überprüfen Sie die Gesamtlieferzeit
4. **Express**: Testen Sie die Express-Aktivierung (falls konfiguriert)

### Backend-Test

1. **Cron-Job**: Überprüfen Sie, ob der tägliche Cron-Job registriert ist:
   - Navigieren Sie zu **Werkzeuge → Site Health → Info → Geplante Ereignisse**
   - Suchen Sie nach `wlm_daily_availability_update`

2. **REST API**: Testen Sie einen API-Aufruf wie oben beschrieben

## Fehlerbehebung

### Plugin lässt sich nicht aktivieren

**Problem**: Fehlermeldung "WooCommerce ist erforderlich"

**Lösung**: Stellen Sie sicher, dass WooCommerce installiert und aktiviert ist, bevor Sie das Plugin aktivieren.

### Lieferfenster wird nicht angezeigt

**Mögliche Ursachen**:
1. Theme-Kompatibilität: Ihr Theme überschreibt möglicherweise WooCommerce-Templates
2. Caching: Leeren Sie alle Caches (Browser, Plugin, Server)
3. JavaScript-Fehler: Öffnen Sie die Browser-Konsole und prüfen Sie auf Fehler

**Lösung**: Aktivieren Sie den Debug-Modus in den Plugin-Einstellungen und prüfen Sie die Logs.

### Express-Option nicht verfügbar

**Mögliche Ursachen**:
1. Produkte sind nicht auf Lager
2. Cutoff-Zeit wurde überschritten
3. Express ist für die Versandart nicht aktiviert

**Lösung**: Überprüfen Sie die Express-Einstellungen im Tab "Versandarten".

### REST API gibt 401 Unauthorized zurück

**Lösung**: 
1. Überprüfen Sie, ob das Anwendungspasswort korrekt ist
2. Stellen Sie sicher, dass der Benutzer die Berechtigung `edit_products` hat
3. Prüfen Sie, ob mod_rewrite aktiviert ist

## Deinstallation

Wenn Sie das Plugin deinstallieren möchten:

1. Navigieren Sie zu **Plugins → Installierte Plugins**
2. Deaktivieren Sie **Woo Lieferzeiten Manager**
3. Klicken Sie auf **Löschen**

**Hinweis**: Die Plugin-Einstellungen und Custom Fields bleiben in der Datenbank erhalten. Wenn Sie diese ebenfalls entfernen möchten, führen Sie folgende SQL-Befehle aus:

```sql
DELETE FROM wp_options WHERE option_name LIKE 'wlm_%';
DELETE FROM wp_postmeta WHERE meta_key LIKE '_wlm_%';
```

## Support

Bei Fragen oder Problemen wenden Sie sich bitte an:

- **Dokumentation**: Siehe README.md im Plugin-Verzeichnis
- **GitHub Issues**: [Link zu Ihrem Repository]
- **E-Mail**: [Ihre Support-E-Mail]

## Nächste Schritte

Nach erfolgreicher Installation empfehlen wir:

1. **Testbestellungen durchführen**: Testen Sie den gesamten Bestellprozess
2. **Produktdaten importieren**: Nutzen Sie die REST API für Bulk-Updates
3. **Styling anpassen**: Passen Sie die CSS-Dateien an Ihr Theme an
4. **Performance optimieren**: Aktivieren Sie Object Caching für bessere Performance
5. **Backup einrichten**: Sichern Sie regelmäßig Ihre Datenbank und Dateien

Viel Erfolg mit dem Woo Lieferzeiten Manager!
