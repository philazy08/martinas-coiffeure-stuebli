<?php
/**
 * Admin-Bereich mit Gegenvorschlag-Funktion
 */

session_start();

define('ADMIN_PASSWORD_HASH', '3b0a6fa4a10a4ca271feed6ab209484e441562b16f5ad709a4fb681127129a61');
define('COIFFEUR_EMAIL', 'info@martinas-coiffeur-stuebli.ch');
define('SITE_URL', 'https://martinas-coiffeur-stuebli.ch');
define('CUSTOMER_RESPONSE_SECRET', 'change-this-long-random-secret-2026');

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['action'])) {
    $submitted_password_hash = hash('sha256', $_POST['password']);
    if (hash_equals(ADMIN_PASSWORD_HASH, $submitted_password_hash)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = 'Falsches Passwort!';
    }
}

$success_message = '';
$error_msg = '';

// Termin-Aktionen (Bestätung/Ablehnung/Gegenvorschlag)
if (isset($_POST['action']) && isset($_POST['appointment_id']) && ($_SESSION['admin_logged_in'] ?? false)) {
    require_once 'db_connect.php';
    
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    $counter_offer = isset($_POST['counter_offer']) ? trim($_POST['counter_offer']) : '';
    
    if ($action === 'confirm') {
        $new_status = 'confirmed';
    } elseif ($action === 'reject') {
        $new_status = 'cancelled';
    } elseif ($action === 'counter_offer') {
        $new_status = 'counter_offered';
    } else {
        $error_msg = 'Ungültige Aktion';
        $new_status = null;
    }
    
    if ($new_status) {
        try {
            $stmt = $pdo->prepare('
                SELECT customer_name, customer_email, service_type, appointment_date 
                FROM appointments 
                WHERE id = :id
            ');
            $stmt->execute([':id' => $appointment_id]);
            $appointment = $stmt->fetch();
            
            if ($appointment) {
                if ($new_status === 'counter_offered') {
                    // Gegenvorschlag speichern
                    $update_stmt = $pdo->prepare('
                        UPDATE appointments 
                        SET status = :status, counter_offer_time = :counter_offer 
                        WHERE id = :id
                    ');
                    $update_stmt->execute([
                        ':status' => $new_status,
                        ':counter_offer' => $counter_offer,
                        ':id' => $appointment_id
                    ]);
                } else {
                    // Normale Update
                    $update_stmt = $pdo->prepare('
                        UPDATE appointments 
                        SET status = :status 
                        WHERE id = :id
                    ');
                    $update_stmt->execute([
                        ':status' => $new_status,
                        ':id' => $appointment_id
                    ]);
                }
                
                // Email versenden
                $headers = "From: " . COIFFEUR_EMAIL . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                
                if ($new_status === 'confirmed') {
                    $subject = '✓ Ihre Buchung bestätigt!';
                    $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                            .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
                            .content { background: white; padding: 20px; border-radius: 5px; }
                            .field { margin: 15px 0; padding: 10px; background-color: #ecf0f1; border-left: 4px solid #27ae60; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>✓ Ihre Buchung ist bestätigt!</h2>
                            </div>
                            <div class='content'>
                                <p>Liebe/r " . htmlspecialchars($appointment['customer_name']) . ",</p>
                                <p>wir freuen uns, Ihren Termin bestätigen zu können:</p>
                                
                                <div class='field'>
                                    <strong>Dienstleistung:</strong> " . htmlspecialchars($appointment['service_type']) . "<br>
                                    <strong>Termin:</strong> " . date('d.m.Y um H:i', strtotime($appointment['appointment_date'])) . " Uhr
                                </div>
                                
                                <p>Bitte erscheinen Sie etwa 5-10 Minuten vor Ihrem Termin.</p>
                                <p><strong>Kontakt:</strong><br>
                                📧 " . htmlspecialchars(COIFFEUR_EMAIL) . "<br>
                                📞 +41 79 470 02 38</p>
                                <p>Viele Grüsse,<br><strong>Martina's Coiffeur Stübli</strong></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                } elseif ($new_status === 'cancelled') {
                    $subject = '✕ Ihre Buchungsanfrage';
                    $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                            .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
                            .content { background: white; padding: 20px; border-radius: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>✕ Termin nicht verfügbar</h2>
                            </div>
                            <div class='content'>
                                <p>Liebe/r " . htmlspecialchars($appointment['customer_name']) . ",</p>
                                <p>leider können wir die Zeit " . date('d.m.Y um H:i', strtotime($appointment['appointment_date'])) . " Uhr nicht annehmen.</p>
                                <p>Bitte kontaktieren Sie uns für einen anderen Termin:<br>
                                📧 " . htmlspecialchars(COIFFEUR_EMAIL) . "<br>
                                📞 +41 79 470 02 38</p>
                                <p>Viele Grüsse,<br><strong>Martina's Coiffeur Stübli</strong></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                } elseif ($new_status === 'counter_offered') {
                    $accept_sig = hash_hmac('sha256', $appointment_id . '|accept', CUSTOMER_RESPONSE_SECRET);
                    $suggest_sig = hash_hmac('sha256', $appointment_id . '|suggest', CUSTOMER_RESPONSE_SECRET);
                    $accept_url = SITE_URL . '/customer_counter_offer.php?id=' . urlencode((string)$appointment_id) . '&action=accept&sig=' . urlencode($accept_sig);
                    $suggest_url = SITE_URL . '/customer_counter_offer.php?id=' . urlencode((string)$appointment_id) . '&action=suggest&sig=' . urlencode($suggest_sig);

                    $subject = '⏰ Gegenvorschlag für Ihren Termin';
                    $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                            .header { background-color: #3498db; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
                            .content { background: white; padding: 20px; border-radius: 5px; }
                            .field { margin: 15px 0; padding: 10px; background-color: #ecf0f1; border-left: 4px solid #3498db; }
                            .actions { margin-top: 20px; }
                            .btn { display: inline-block; padding: 10px 16px; margin-right: 8px; border-radius: 6px; text-decoration: none; font-weight: bold; }
                            .btn-accept { background: #27ae60; color: #fff !important; }
                            .btn-decline { background: #e74c3c; color: #fff !important; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>⏰ Gegenvorschlag für Ihren Termin</h2>
                            </div>
                            <div class='content'>
                                <p>Liebe/r " . htmlspecialchars($appointment['customer_name']) . ",</p>
                                <p>vielen Dank für Ihre Buchungsanfrage! Die anfgeforderte Zeit ist leider nicht verfügbar. Gerne machen wir Ihnen einen Gegenvorschlag:</p>
                                
                                <div class='field'>
                                    <strong>Vorgeschlagene Zeit:</strong><br>
                                    " . htmlspecialchars($counter_offer) . "
                                </div>
                                
                                <p>Passt Ihnen dieser Termin? Bitte klicken Sie direkt auf eine Option:</p>
                                <div class='actions'>
                                    <a class='btn btn-accept' href='" . htmlspecialchars($accept_url) . "'>✓ Gegenvorschlag annehmen</a>
                                    <a class='btn btn-decline' href='" . htmlspecialchars($suggest_url) . "'>✕ Passt nicht - neuen Termin vorschlagen</a>
                                </div>
                                <p style='margin-top: 15px;'>Bei Fragen:<br>
                                📧 " . htmlspecialchars(COIFFEUR_EMAIL) . "<br>
                                📞 +41 79 470 02 38</p>
                                <p>Viele Grüsse,<br><strong>Martina's Coiffeur Stübli</strong></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                }
                
                mail($appointment['customer_email'], $subject, $message, $headers);
                
                $success_msgs = [
                    'confirmed' => '✓ Termin bestätigt und Email versendet!',
                    'cancelled' => '✕ Termin abgelehnt und Kunde informiert.',
                    'counter_offered' => '⏰ Gegenvorschlag versendet!'
                ];
                $success_message = $success_msgs[$new_status] ?? 'Aktion abgeschlossen.';
            } else {
                $error_msg = 'Termin nicht gefunden.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Datenbankfehler: ' . $e->getMessage();
            error_log($error_msg);
        }
    }
}

// Login-Seite
if (!($_SESSION['admin_logged_in'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 100%;
            }
            .login-header h1 { font-size: 28px; color: #333; margin-bottom: 5px; }
            .login-header p { color: #999; font-size: 14px; margin-bottom: 30px; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; color: #555; font-weight: 600; }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
            }
            input[type="password"]:focus { outline: none; border-color: #667eea; }
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }
            button:hover { transform: translateY(-2px); }
            .error { color: #e74c3c; background: #fadbd8; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>🔐 Admin-Bereich</h1>
                <p>Martina's Coiffeur Stübli</p>
            </div>
            <?php if ($login_error): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Passwort:</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit">Anmelden</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Admin-Dashboard
require_once 'db_connect.php';

try {
    $stmt = $pdo->prepare('SELECT * FROM appointments ORDER BY appointment_date DESC');
    $stmt->execute();
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
    $error_msg = 'Fehler: ' . $e->getMessage();
}

$pending = count(array_filter($appointments, fn($a) => $a['status'] === 'pending'));
$confirmed = count(array_filter($appointments, fn($a) => $a['status'] === 'confirmed'));
$cancelled = count(array_filter($appointments, fn($a) => $a['status'] === 'cancelled'));
$counter_offered = count(array_filter($appointments, fn($a) => $a['status'] === 'counter_offered'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Terminaverwaltung</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header a {
            background: rgba(255,255,255,0.2);
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .header a:hover { background: rgba(255,255,255,0.3); }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat h3 { color: #999; font-size: 12px; text-transform: uppercase; margin-bottom: 10px; }
        .stat .number { font-size: 32px; font-weight: bold; color: #667eea; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .appointments {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .appointments-header { background: #f9f9f9; padding: 20px; border-bottom: 1px solid #eee; }
        .appointment {
            border-bottom: 1px solid #eee;
            padding: 20px;
        }
        .appointment:last-child { border-bottom: none; }
        .appointment:hover { background: #f9f9f9; }
        .apt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .apt-field label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 3px; }
        .apt-field .value { font-size: 15px; color: #333; font-weight: 500; word-break: break-all; }
        .apt-field a { color: #667eea; text-decoration: none; }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-counter_offered { background: #cfe2ff; color: #084298; }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }
        .btn-confirm { background: #27ae60; color: white; }
        .btn-confirm:hover { background: #229954; }
        .btn-reject { background: #e74c3c; color: white; }
        .btn-reject:hover { background: #c0392b; }
        .btn-counter { background: #3498db; color: white; }
        .btn-counter:hover { background: #2980b9; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-input {
            width: 100%;
            padding: 10px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: Arial;
            font-size: 14px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .modal-btn-submit { background: #3498db; color: white; }
        .modal-btn-submit:hover { background: #2980b9; }
        .modal-btn-cancel { background: #bdc3c7; color: #333; }
        .modal-btn-cancel:hover { background: #95a5a6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📅 Terminaverwaltung</h1>
                <p>Martina's Coiffeur Stübli</p>
            </div>
            <a href="?logout">Abmelden</a>
        </div>

        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <h3>⏳ Ausstehend</h3>
                <div class="number"><?php echo $pending; ?></div>
            </div>
            <div class="stat">
                <h3>✓ Bestätigt</h3>
                <div class="number"><?php echo $confirmed; ?></div>
            </div>
            <div class="stat">
                <h3>⏰ Gegenvorschlag</h3>
                <div class="number"><?php echo $counter_offered; ?></div>
            </div>
            <div class="stat">
                <h3>✕ Abgelehnt</h3>
                <div class="number"><?php echo $cancelled; ?></div>
            </div>
        </div>

        <div class="appointments">
            <div class="appointments-header">
                <h2>Alle Buchungen (<?php echo count($appointments); ?>)</h2>
            </div>

            <?php if (empty($appointments)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>📭 Keine Buchungen vorhanden</p>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                    <div class="appointment">
                        <div class="apt-grid">
                            <div class="apt-field">
                                <label>Name</label>
                                <div class="value"><?php echo htmlspecialchars($apt['customer_name']); ?></div>
                            </div>
                            <div class="apt-field">
                                <label>Email</label>
                                <div class="value"><a href="mailto:<?php echo htmlspecialchars($apt['customer_email']); ?>"><?php echo htmlspecialchars($apt['customer_email']); ?></a></div>
                            </div>
                            <div class="apt-field">
                                <label>Telefon</label>
                                <div class="value"><a href="tel:<?php echo htmlspecialchars($apt['customer_phone']); ?>"><?php echo htmlspecialchars($apt['customer_phone']); ?></a></div>
                            </div>
                            <div class="apt-field">
                                <label>Service</label>
                                <div class="value"><?php echo htmlspecialchars($apt['service_type']); ?></div>
                            </div>
                            <div class="apt-field">
                                <label>Termin</label>
                                <div class="value"><?php echo date('d.m.Y H:i', strtotime($apt['appointment_date'])); ?></div>
                            </div>
                            <div class="apt-field">
                                <label>Status</label>
                                <span class="status status-<?php echo htmlspecialchars($apt['status']); ?>">
                                    <?php
                                    if ($apt['status'] === 'confirmed' && !empty($apt['counter_offer_time'])) {
                                        echo '✓ Gegenvorschlag angenommen';
                                    } else {
                                        echo match($apt['status']) {
                                            'pending' => '⏳ Ausstehend',
                                            'confirmed' => '✓ Bestätigt',
                                            'cancelled' => '✕ Abgelehnt',
                                            'counter_offered' => '⏰ Gegenvorschlag',
                                            default => ucfirst($apt['status'])
                                        };
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($apt['notes']): ?>
                            <div style="background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 14px;">
                                <strong>Kundennotizen:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($apt['notes']) && str_contains($apt['notes'], 'Kundenvorschlag am')): ?>
                            <div style="background: #fff9e8; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 14px; border: 1px solid #f0dca5;">
                                <strong>Neuer Terminvorschlag vom Kunden eingegangen</strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($apt['counter_offer_time']): ?>
                            <div style="background: #cfe2ff; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 14px;">
                                <strong>Dein Gegenvorschlag:</strong> <?php echo htmlspecialchars($apt['counter_offer_time']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($apt['status'] === 'pending'): ?>
                            <div class="actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" class="btn btn-confirm">✓ Bestätigen</button>
                                </form>
                                <button type="button" class="btn btn-counter" onclick="openCounterModal(<?php echo $apt['id']; ?>)">⏰ Gegenvorschlag</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject">✕ Ablehnen</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <p style="margin-top: 10px; color: #999; font-size: 13px;">✓ Bearbeitet</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="counterModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCounterModal()">&times;</span>
            <h2 style="margin-bottom: 20px;">⏰ Gegenvorschlag</h2>
            <form method="POST" id="counterForm">
                <input type="hidden" name="appointment_id" id="appointmentId">
                <input type="hidden" name="action" value="counter_offer">
                <label for="counterOffer"><strong>Vorgeschlagene Zeit:</strong></label>
                <textarea id="counterOffer" name="counter_offer" class="modal-input" placeholder="z.B. Mittwoch, 17.05.2026 um 14:30 Uhr" required style="height: 80px;"></textarea>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCounterModal()">Abbrechen</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Versenden</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCounterModal(appointmentId) {
            document.getElementById('appointmentId').value = appointmentId;
            document.getElementById('counterModal').style.display = 'block';
        }
        function closeCounterModal() {
            document.getElementById('counterModal').style.display = 'none';
        }
        window.onclick = function(e) {
            const modal = document.getElementById('counterModal');
            if (e.target === modal) modal.style.display = 'none';
        }
    </script>
</body>
</html>
