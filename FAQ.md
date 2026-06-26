# ❓ FAQ - Häufig gestellte Fragen

## 🔌 Datenbankverbindung

### F: "Datenbankverbindung fehlgeschlagen"
**A:** 
1. Überprüfe in `db_connect.php` dass die Werte korrekt sind:
   - DB_HOST
   - DB_USER
   - DB_PASS
   - DB_NAME
2. Teste die Verbindung mit phpMyAdmin (Hostpoint)
3. Stelle sicher, dass der MySQL-User diese Datenbank darf

### F: Wo finde ich die Datenbank-Credentials bei Hostpoint?
**A:** 
1. Kundencenter → Hosting
2. "Service-Daten" oder "Datenbanken"
3. Dort findest du:
   - MySQL-Server (Host)
   - Datenbank-Name
   - Benutzer und Passwort

### F: Tabelle appointments existiert nicht
**A:** 
1. Öffne in phpMyAdmin (Hostpoint) deine Datenbank
2. Gehe zu "SQL" → kopiere den Inhalt von `database_schema.sql`
3. Ausführen
4. Fertig!

---

## 📧 Email-Probleme

### F: E-Mails kommen nicht an
**A:**
Häufigste Gründe:
1. **From-Adresse ungültig** - Muss eine echte Domain sein (nicht `noreply@localhost`)
   - In `submit_booking.php` Zeile 115 anpassen
2. **Spam-Filter** - Prüfe Spam-Ordner
3. **SPF/DKIM nicht konfiguriert** - Hostpoint Support kontaktieren
4. **mail() Funktion disabled** - Mit Hostpoint Support klären

**Test-Email versenden:**
```php
<?php
$to = 'deine@email.ch';
$subject = 'Test';
$message = 'Test Email';
$headers = "From: geschaeft@deine-domain.ch\r\n";
if (mail($to, $subject, $message, $headers)) {
    echo "Email versendet!";
} else {
    echo "Email FEHLER!";
}
?>
```

### F: HTML-Emails werden als Text angezeigt
**A:** Prüfe in `submit_booking.php` dass diese Header gesetzt sind:
```php
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
```

---

## 👤 Admin-Bereich

### F: Admin-Login funktioniert nicht
**A:**
1. Hast du das Passwort in `admin.php` (Zeile 25) geändert?
2. Cookies müssen im Browser aktiviert sein
3. Prüfe: Ist dir "Passwort" das was du eingegeben hast?
4. Versuche einen anderen Browser

### F: Session läuft ab / "Du bist nicht angemeldet"
**A:**
Das ist normal nach längerer Inaktivität. Einfach erneut anmelden.
Die Session-Dauer kannst du in `admin.php` anpassen (nach ~3600 Sekunden = 1h).

### F: Admin-Panel zeigt keine Termine
**A:**
1. Wurden Termine eingegeben? (Check in DB)
2. Fehler in der Konsole (F12)?
3. Datenbankverbindung ok?
4. Prüfe error_log von Hostpoint

---

## 🔐 Sicherheit

### F: Soll ich Passwort gehashed speichern?
**A:** JA! Besser mit `password_hash()`:

```php
// Admin-Passwort hashen (1x machen):
echo password_hash('mein_passwort_123', PASSWORD_BCRYPT);
// Ergebnis kopieren → in ADMIN_PASSWORD_HASH eintragen

// Dann in admin.php:
if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
    $_SESSION['admin_logged_in'] = true;
}
```

### F: Kann als Hacker mein System angreifen?
**A:** Das System ist relativ sicher:
- ✅ Prepared Statements (gegen SQL-Injection)
- ✅ Input-Validierung
- ✅ HTML-Escaping (gegen XSS)
- ✅ Sessions für Auth

Zusätzlich kannst du:
- Firewall-Regeln setzen
- HTTPS erzwingen
- WAF (Web Application Firewall) nutzen
- Regelmäßige Backups machen

---

## 🚀 Deployment

### F: Beim Upload zu Hostpoint funktioniert nichts mehr
**A:**
1. Sind alle Dateien richtig hochgeladen?
2. Pfade richtig? (Klein-/Großschreibung!)
3. Zugriff-Rechte ok? (644 Dateien, 755 Ordner)
4. Prüfe Error-Logs in Hostpoint-Panel
5. Tester: `https://deine-domain.ch/booking-form.html`

### F: Welche PHP-Version brauche ich?
**A:** Mindestens PHP 7.4, besser PHP 8.0+
Hostpoint bietet meist 8.0 oder höher.

### F: Brauche ich HTTPS?
**A:** 
- **Empfohlen:** JA! (Sicherheit)
- **Mandatorisch:** Bei .ch Domain ist SSL-Zertifikat oft inklusive
- Hostpoint bietet Let's Encrypt kostenlos

---

## 📊 Performance

### F: Werden Termine ewig angezeigt?
**A:** Alte Termine kannst du löschen (siehe `database_schema.sql`):
```sql
DELETE FROM appointments 
WHERE status = 'cancelled' 
  AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### F: Admin wird langsam mit 1000 Terminen
**A:** 
1. Indizes prüfen (sind in `database_schema.sql`)
2. Pagination einbauen (nur letzte 50 Termine zeigen)
3. Archiv-Tabelle erstellen
4. Upgrade auf mehr RAM

---

## 💾 Backups

### F: Wie sicherung ich meine Daten?
**A:**
1. **Datenbank:** Hostpoint → phpMyAdmin → Export (SQL)
2. **Dateien:** FTP/SFTP Download
3. **Automatisch:** Hostpoint-Backups nutzen

**Regelmäßigkeit:** Mindestens 1x pro Woche!

---

## 🛠️ Anpassungen

### F: Wie ändere ich die Dienstleistungen?
**A:** In `booking-form.html` Zeile ~105:
```html
<option value="Mein Service">Mein Service</option>
```

### F: Wie ändere ich Farben/Design?
**A:** 
- `booking-form.html` - CSS im `<style>` Tag
- `admin.php` - CSS im `<style>` Tag (Zeile ~120)
- Farben suchen (z.B. `#667eea`) und ändern

### F: Kann ich Felder hinzufügen?
**A:** JA, aber dann auch in der DB-Tabelle:
1. Neue Spalte in `appointments` erstellt
2. Input-Feld in `booking-form.html` hinzufügen
3. Name muss `name="spaltenname"` sein
4. In `submit_booking.php` im SQL-Insert hinzufügen

---

## 📱 Mobile

### F: Funktioniert auf dem Handy?
**A:** JA! `booking-form.html` ist responsive:
- ✅ Mobile-optimiert
- ✅ Touch-freundlich
- ✅ Kleine Bildschirme
- ✅ Alle modernen Browser

### F: Kann ich App statt Website machen?
**A:** Das Backend bleibt gleich, könnte aber:
- React/Vue-App bauen
- Flutter App bauen
- Native App (iOS/Android)
Alle würden die PHP-APIs aufrufen.

---

## 🚨 Fehlerbehandlung

### F: Welche HTTP-Status-Codes gibt es?
**A:**
| Code | Bedeutung |
|------|-----------|
| 200 | OK |
| 201 | Created (neuer Termin) |
| 400 | Bad Request (Validierungsfehler) |
| 405 | Method Not Allowed (nur POST erlaubt) |
| 500 | Server Error (DB-Error) |

### F: Fehlerlog prüfen?
**A:** 
1. Hostpoint-Panel
2. Oder in Terminal SSH
3. Logfile-Pfad (Hostpoint spezifisch)

---

## 📞 Kontakt & Support

### Wenn nichts hilft:
1. Hostpoint Support: support.hostpoint.ch
2. Stack Overflow: Tag `php`, `mysql`, `pdo`
3. Deine/n Webentwickler fragen
4. Neuladen (Ctrl+F5) nicht vergessen!

---

**Mehr Fragen?** Sieh INTEGRATION_GUIDE.md an.
