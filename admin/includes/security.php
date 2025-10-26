<?php
/**
 * Système de sécurité centralisé
 * Gère les sessions, CSRF, authentification et autorisations
 */

class SecurityManager {
    private static $initialized = false;

    /**
     * Initialise une session sécurisée
     */
    public static function initSecureSession() {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des sessions
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_lifetime', 0); // Expire à la fermeture du navigateur
            ini_set('session.gc_maxlifetime', 3600); // 1 heure

            session_name('RESTAURANT_ADMIN_SESSION');
            session_start();

            // Régénération de l'ID de session après login
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
                $_SESSION['created_at'] = time();
            }

            // Vérification du timeout
            self::checkSessionTimeout();

            // Protection contre le détournement de session
            self::validateSessionFingerprint();

            $_SESSION['last_activity'] = time();
        }

        self::$initialized = true;
    }

    /**
     * Vérifie le timeout de session
     */
    private static function checkSessionTimeout() {
        $timeout = 3600; // 1 heure

        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $timeout)) {
            self::destroySession();
            header('Location: /restaurant/admin/login.php?timeout=1');
            exit;
        }
    }

    /**
     * Valide l'empreinte de session pour prévenir le vol
     */
    private static function validateSessionFingerprint() {
        $current_fingerprint = self::generateFingerprint();

        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $current_fingerprint;
        } elseif ($_SESSION['fingerprint'] !== $current_fingerprint) {
            // Empreinte différente = session potentiellement volée
            self::destroySession();
            header('Location: /restaurant/admin/login.php?security=invalid');
            exit;
        }
    }

    /**
     * Génère une empreinte unique basée sur le navigateur
     */
    private static function generateFingerprint() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';

        return hash('sha256', $user_agent . $accept_language);
    }

    /**
     * Détruit proprement la session
     */
    public static function destroySession() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Génère un token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valide un token CSRF
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public static function requireAuthentication() {
        self::initSecureSession();

        if (!isset($_SESSION['admin_logged_in']) ||
            $_SESSION['admin_logged_in'] !== true ||
            !isset($_SESSION['admin_id'])) {

            // Sauvegarde l'URL de destination
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];

            header('Location: /restaurant/admin/login.php');
            exit;
        }
    }

    /**
     * Vérifie une permission spécifique
     */
    public static function requirePermission($conn, $permission) {
        self::requireAuthentication();

        $admin_id = $_SESSION['admin_id'];

        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM permissions p
             JOIN admin_permissions ap ON p.id = ap.permission_id
             WHERE ap.admin_id = ? AND p.code = ?"
        );
        $stmt->execute([$admin_id, $permission]);

        if ($stmt->fetchColumn() == 0) {
            http_response_code(403);
            die('Accès refusé : vous n\'avez pas la permission requise.');
        }
    }

    /**
     * Nettoie et valide une entrée
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }

        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) ?: 0;

            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) ?: 0.0;

            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) ?: '';

            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) ?: '';

            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Protection contre le brute force
     */
    public static function isRateLimited($identifier, $max_attempts = 5, $time_window = 300) {
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $now = time();
        $key = hash('sha256', $identifier);

        // Nettoyer les anciennes tentatives
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($now, $time_window) {
                    return ($now - $timestamp) < $time_window;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }

        // Vérifier le nombre de tentatives
        if (count($_SESSION['rate_limit'][$key]) >= $max_attempts) {
            return true; // Rate limited
        }

        // Enregistrer la tentative
        $_SESSION['rate_limit'][$key][] = $now;

        return false; // Pas de limite
    }

    /**
     * Enregistre une tentative de connexion échouée
     */
    public static function recordFailedLogin($conn, $username, $ip) {
        $stmt = $conn->prepare(
            "INSERT INTO login_attempts (username, ip_address, attempted_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$username, $ip]);
    }

    /**
     * Vérifie si une IP est bloquée
     */
    public static function isIpBlocked($conn, $ip, $max_attempts = 5, $block_duration = 900) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ?
             AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$ip, $block_duration]);

        return $stmt->fetchColumn() >= $max_attempts;
    }

    /**
     * Nettoie les anciennes tentatives de connexion
     */
    public static function cleanOldLoginAttempts($conn, $days = 30) {
        $stmt = $conn->prepare(
            "DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
    }

    /**
     * Génère un mot de passe sécurisé
     */
    public static function generateSecurePassword($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Hash un mot de passe de manière sécurisée
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Vérifie un mot de passe
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Vérifie la force d'un mot de passe
     */
    public static function isStrongPassword($password) {
        $min_length = 8;

        if (strlen($password) < $min_length) {
            return false;
        }

        // Doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial
        $has_lowercase = preg_match('/[a-z]/', $password);
        $has_uppercase = preg_match('/[A-Z]/', $password);
        $has_number = preg_match('/[0-9]/', $password);
        $has_special = preg_match('/[^a-zA-Z0-9]/', $password);

        return $has_lowercase && $has_uppercase && $has_number && $has_special;
    }
}
