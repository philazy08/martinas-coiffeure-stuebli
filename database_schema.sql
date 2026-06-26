-- 📋 SQL-SCHEMA für Coiffeur Buchungstool
-- Diese Tabelle muss in deiner MariaDB-Datenbank existieren
-- Falls nicht vorhanden: In phpMyAdmin (Hostpoint) ausführen

-- ========== Tabelle: appointments ==========

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Eindeutige ID des Termins',
    customer_name VARCHAR(255) NOT NULL COMMENT 'Name des Kunden',
    customer_email VARCHAR(255) NOT NULL COMMENT 'E-Mail des Kunden',
    customer_phone VARCHAR(20) NOT NULL COMMENT 'Telefonnummer des Kunden',
    service_type VARCHAR(255) NOT NULL COMMENT 'Art der Dienstleistung (Haarschnitt, etc.)',
    appointment_date DATETIME NOT NULL COMMENT 'Termin Datum und Uhrzeit',
    notes TEXT COMMENT 'Allgemeine Notizen vom Kunden',
    counter_offer_time TEXT COMMENT 'Gegenvorschlag für Termin von der Coiffeuse',
    status ENUM('pending', 'confirmed', 'cancelled', 'counter_offered') DEFAULT 'pending' COMMENT 'Status des Termins',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Zeitstempel der Erstellung',
    
    -- Indizes für Performance
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Speichert alle Koiffeur-Termine';

-- ========== Optional: Zusätzliche Tabelle für Services ==========

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    duration INT NOT NULL COMMENT 'Dauer in Minuten',
    price DECIMAL(10, 2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Verfügbare Coiffeur-Dienstleistungen';

-- Beispiel-Daten für services-Tabelle
INSERT INTO services (name, duration, price) VALUES
('Haarschnitt', 30, 45.00),
('Haarfarbe', 60, 65.00),
('Dauerwelle', 120, 85.00),
('Hochsteckfrisur', 45, 50.00)
ON DUPLICATE KEY UPDATE name=name;

-- ========== Optional: Tabelle für Admin-Logs ==========

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'confirm, reject, etc.',
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    changed_by VARCHAR(100) DEFAULT 'admin',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_appointment_id (appointment_id),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit-Log für Termin-Änderungen';

-- ========== Nützliche SELECT-Abfragen ==========

-- Alle Termine anzeigen (sichtbar im Admin):
-- SELECT * FROM appointments ORDER BY appointment_date DESC;

-- Nur ausstehende Termine:
-- SELECT * FROM appointments WHERE status = 'pending' ORDER BY appointment_date ASC;

-- Termine von heute:
-- SELECT * FROM appointments WHERE DATE(appointment_date) = CURDATE();

-- Statistiken:
-- SELECT status, COUNT(*) as count FROM appointments GROUP BY status;

-- Alle Termine eines Kunden:
-- SELECT * FROM appointments WHERE customer_email = 'kunde@example.ch' ORDER BY appointment_date DESC;

-- ========== Indizes für bessere Performance ==========

-- Falls nötig, können weitere Indizes erstellt werden:
-- ALTER TABLE appointments ADD INDEX idx_customer_email (customer_email);
-- ALTER TABLE appointments ADD INDEX idx_service_type (service_type);

-- ========== Datenbank aufräumen (optional) ==========

-- Alte, abgelehnte Termine löschen (älter als 6 Monate):
-- DELETE FROM appointments WHERE status = 'cancelled' AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
