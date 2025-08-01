<?php
// config_security.php - Configuration sécurisée

// Configuration email (à déplacer dans des variables d'environnement)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'mulhomabiala29@gmail.com');
define('SMTP_PASSWORD', 'khli pyzj ihte qdgu'); // À CHANGER !
define('SMTP_PORT', 587);
define('EMAIL_FROM', 'no-reply@tonrestaurant.com');
define('EMAIL_FROM_NAME', 'Ton Restaurant - Sécurité');

// Configuration de sécurité
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('ACCOUNT_LOCKOUT_ATTEMPTS', 5);
define('ACCOUNT_LOCKOUT_TIME', 1800); // 30 minutes
define('CODE_EXPIRY_TIME', 300); // 5 minutes
define('MAX_CODE_ATTEMPTS', 3);
define('RESEND_COOLDOWN', 60); // 1 minute

// Configuration session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Uniquement en HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 heure

// Headers de sécurité
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com; style-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com; img-src \'self\' data:');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Fonction pour créer un admin avec mot de passe sécurisé
function createSecureAdmin($conn, $username, $password, $email) {
    $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3          // 3 threads
    ]);
    
    $stmt = $conn->prepare("INSERT INTO admin (username, password, email, active) VALUES (?, ?, ?, 1)");
    return $stmt->execute([$username, $hashedPassword, $email]);
}

// Fonction de validation des mots de passe
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
    }
    
    // Vérification contre les mots de passe communs
    $commonPasswords = [
        'password', '123456', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', '12345678'
    ];
    
    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = "Ce mot de passe est trop commun";
    }
    
    return $errors;
}

// Fonction de nettoyage automatique des tentatives expirées
function cleanupExpiredData($conn) {
    // Nettoyer les tentatives de connexion expirées
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([time() - LOCKOUT_TIME]);
    
    // Débloquer les comptes dont le délai est expiré
    $stmt = $conn->prepare("UPDATE admin SET locked_until = NULL WHERE locked_until < ?");
    $stmt->execute([time()]);
}

// Fonction de génération de rapports de sécurité
function generateSecurityReport($conn) {
    $report = [];
    
    // Tentatives de connexion des dernières 24h
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE attempt_time > ?");
    $stmt->execute([time() - 86400]);
    $report['failed_attempts_24h'] = $stmt->fetchColumn();
    
    // IPs les plus actives
    $stmt = $conn->prepare("
        SELECT ip_address, COUNT(*) as attempts 
        FROM login_attempts 
        WHERE attempt_time > ? 
        GROUP BY ip_address 
        ORDER BY attempts DESC 
        LIMIT 10
    ");
    $stmt->execute([time() - 86400]);
    $report['top_ips'] = $stmt->fetchAll();
    
    // Comptes verrouillés
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin WHERE locked_until > ?");
    $stmt->execute([time()]);
    $report['locked_accounts'] = $stmt->fetchColumn();
    
    return $report;
}

// Middleware de sécurité pour toutes les pages admin
function requireSecureAdmin() {
    setSecurityHeaders();
    
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit;
    }
    
    // Vérifier la validité de la session (max 1 heure)
    if (isset($_SESSION['login_time']) && time() - $_SESSION['login_time'] > 3600) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    
    // Régénérer l'ID de session périodiquement
    if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Fonction de log sécurisé
function securityLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $logEntry = "[$timestamp] [$level] $message | IP: $ip | User-Agent: " . substr($userAgent, 0, 100) . "\n";
    
    // Écrire dans un fichier de log sécurisé
    file_put_contents('../logs/security.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Fonction de détection d'anomalies
function detectAnomalies($conn, $ip, $username = null) {
    $anomalies = [];
    
    // Trop de tentatives depuis cette IP
    $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
    $stmt->execute([$ip, time() - 3600]);
    if ($stmt->fetchColumn() > 20) {
        $anomalies[] = "IP suspecte - trop de tentatives";
    }
    
    // Tentatives sur plusieurs comptes depuis la même IP
    if ($username) {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT username) FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
        $stmt->execute([$ip, time() - 3600]);
        if ($stmt->fetchColumn() > 5) {
            $anomalies[] = "Tentative d'énumération d'utilisateurs";
        }
    }
    
    return $anomalies;
}
?>