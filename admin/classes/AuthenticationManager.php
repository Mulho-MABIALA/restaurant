<?php
/**
 * Gestionnaire d'authentification centralisé
 * Gère login, 2FA, récupération de mot de passe
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/EmailService.php';

class AuthenticationManager {
    private $conn;
    private $emailService;

    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 900; // 15 minutes
    const CODE_EXPIRY = 300; // 5 minutes

    public function __construct($conn) {
        $this->conn = $conn;
        $this->emailService = new EmailService();
    }

    /**
     * Authentifie un utilisateur
     *
     * @param string $username
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'admin' => array|null]
     */
    public function authenticate($username, $password) {
        // Validation des entrées
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Nom d\'utilisateur et mot de passe requis'
            ];
        }

        // Récupérer l'admin
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, username, password, email, locked_until, failed_attempts, active
                 FROM admin WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Auth error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur système. Veuillez réessayer.'
            ];
        }

        // Vérifier si l'utilisateur existe
        if (!$admin) {
            // Log tentative
            SecurityManager::recordFailedLogin($this->conn, $username, $this->getClientIP());

            return [
                'success' => false,
                'message' => 'Identifiants incorrects'
            ];
        }

        // Vérifier si le compte est actif
        if (!$admin['active']) {
            return [
                'success' => false,
                'message' => 'Compte désactivé. Contactez l\'administrateur.'
            ];
        }

        // Vérifier si le compte est verrouillé
        if ($admin['locked_until'] && time() < strtotime($admin['locked_until'])) {
            $remaining = strtotime($admin['locked_until']) - time();
            $minutes = ceil($remaining / 60);

            return [
                'success' => false,
                'message' => "Compte verrouillé. Réessayez dans {$minutes} minute(s)."
            ];
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $admin['password'])) {
            // Incrémenter les tentatives échouées
            $this->incrementFailedAttempts($admin['id']);

            SecurityManager::recordFailedLogin($this->conn, $username, $this->getClientIP());

            return [
                'success' => false,
                'message' => 'Identifiants incorrects'
            ];
        }

        // Réinitialiser les tentatives échouées
        $this->resetFailedAttempts($admin['id']);

        // Authentification réussie
        return [
            'success' => true,
            'message' => 'Authentification réussie',
            'admin' => $admin
        ];
    }

    /**
     * Génère et envoie un code 2FA
     *
     * @param array $admin
     * @return array ['success' => bool, 'message' => string, 'code' => string|null]
     */
    public function sendTwoFactorCode($admin) {
        // Générer un code à 6 chiffres
        $code = $this->generateCode();

        // Envoyer l'email
        try {
            $sent = $this->emailService->send2FACode(
                $admin['email'],
                $code,
                $admin['username']
            );

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Code envoyé par email',
                    'code' => $code
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi de l\'email'
                ];
            }

        } catch (Exception $e) {
            error_log("2FA email error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du code'
            ];
        }
    }

    /**
     * Vérifie un code 2FA
     *
     * @param string $inputCode
     * @param string $storedCode
     * @param int $expiryTime
     * @return array
     */
    public function verifyTwoFactorCode($inputCode, $storedCode, $expiryTime) {
        // Vérifier l'expiration
        if (time() > $expiryTime) {
            return [
                'success' => false,
                'message' => 'Code expiré. Demandez un nouveau code.'
            ];
        }

        // Vérifier le code
        if ($inputCode !== $storedCode) {
            return [
                'success' => false,
                'message' => 'Code incorrect'
            ];
        }

        return [
            'success' => true,
            'message' => 'Code vérifié avec succès'
        ];
    }

    /**
     * Connecte un utilisateur (crée la session)
     *
     * @param array $admin
     */
    public function login($admin) {
        SecurityManager::initSecureSession();

        // Régénérer l'ID de session pour prévenir fixation
        session_regenerate_id(true);

        // Stocker les informations de session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Générer un token CSRF
        SecurityManager::generateCSRFToken();

        // Logger la connexion
        $this->logSuccessfulLogin($admin['id']);
    }

    /**
     * Déconnecte un utilisateur
     */
    public function logout() {
        SecurityManager::destroySession();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     *
     * @return bool
     */
    public function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) &&
               $_SESSION['admin_logged_in'] === true &&
               isset($_SESSION['admin_id']);
    }

    /**
     * Génère un code de vérification à 6 chiffres
     *
     * @return string
     */
    private function generateCode() {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Incrémente le compteur de tentatives échouées
     *
     * @param int $admin_id
     */
    private function incrementFailedAttempts($admin_id) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE admin
                 SET failed_attempts = failed_attempts + 1,
                     locked_until = CASE
                         WHEN failed_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                         ELSE locked_until
                     END
                 WHERE id = ?"
            );

            $stmt->execute([
                self::MAX_LOGIN_ATTEMPTS,
                self::LOCKOUT_DURATION,
                $admin_id
            ]);

        } catch (PDOException $e) {
            error_log("Failed to increment attempts: " . $e->getMessage());
        }
    }

    /**
     * Réinitialise le compteur de tentatives échouées
     *
     * @param int $admin_id
     */
    private function resetFailedAttempts($admin_id) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE admin
                 SET failed_attempts = 0, locked_until = NULL
                 WHERE id = ?"
            );

            $stmt->execute([$admin_id]);

        } catch (PDOException $e) {
            error_log("Failed to reset attempts: " . $e->getMessage());
        }
    }

    /**
     * Enregistre une connexion réussie
     *
     * @param int $admin_id
     */
    private function logSuccessfulLogin($admin_id) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO login_history (admin_id, ip_address, user_agent, login_at)
                 VALUES (?, ?, ?, NOW())"
            );

            $stmt->execute([
                $admin_id,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log login: " . $e->getMessage());
        }
    }

    /**
     * Récupère l'IP du client
     *
     * @return string
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    /**
     * Génère un token de réinitialisation de mot de passe
     *
     * @param string $email
     * @return array
     */
    public function requestPasswordReset($email) {
        try {
            // Vérifier si l'email existe
            $stmt = $this->conn->prepare(
                "SELECT id, username, email FROM admin WHERE email = ? AND active = 1"
            );
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                // Ne pas révéler si l'email existe ou non
                return [
                    'success' => true,
                    'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'
                ];
            }

            // Générer un token unique
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure

            // Stocker le token
            $stmt = $this->conn->prepare(
                "INSERT INTO password_resets (admin_id, token, expires_at, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE token = ?, expires_at = ?"
            );

            $stmt->execute([
                $admin['id'],
                $token,
                $expires,
                $token,
                $expires
            ]);

            // Envoyer l'email (à implémenter dans EmailService)
            // $this->emailService->sendPasswordResetEmail($admin['email'], $token);

            return [
                'success' => true,
                'message' => 'Un lien de réinitialisation a été envoyé.'
            ];

        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation.'
            ];
        }
    }

    /**
     * Réinitialise le mot de passe avec un token
     *
     * @param string $token
     * @param string $newPassword
     * @return array
     */
    public function resetPassword($token, $newPassword) {
        try {
            // Vérifier le token
            $stmt = $this->conn->prepare(
                "SELECT pr.admin_id, a.username
                 FROM password_resets pr
                 JOIN admin a ON pr.admin_id = a.id
                 WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0"
            );

            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                return [
                    'success' => false,
                    'message' => 'Token invalide ou expiré.'
                ];
            }

            // Vérifier la force du mot de passe
            if (!SecurityManager::isStrongPassword($newPassword)) {
                return [
                    'success' => false,
                    'message' => 'Mot de passe trop faible. Utilisez au moins 8 caractères avec majuscules, minuscules, chiffres et caractères spéciaux.'
                ];
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = SecurityManager::hashPassword($newPassword);

            // Mettre à jour le mot de passe
            $stmt = $this->conn->prepare(
                "UPDATE admin SET password = ? WHERE id = ?"
            );
            $stmt->execute([$hashedPassword, $reset['admin_id']]);

            // Marquer le token comme utilisé
            $stmt = $this->conn->prepare(
                "UPDATE password_resets SET used = 1 WHERE token = ?"
            );
            $stmt->execute([$token]);

            return [
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès.'
            ];

        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation.'
            ];
        }
    }

    /**
     * Change le mot de passe d'un utilisateur connecté
     *
     * @param int $admin_id
     * @param string $currentPassword
     * @param string $newPassword
     * @return array
     */
    public function changePassword($admin_id, $currentPassword, $newPassword) {
        try {
            // Récupérer le hash actuel
            $stmt = $this->conn->prepare(
                "SELECT password FROM admin WHERE id = ?"
            );
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable.'
                ];
            }

            // Vérifier l'ancien mot de passe
            if (!password_verify($currentPassword, $admin['password'])) {
                return [
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect.'
                ];
            }

            // Vérifier que le nouveau mot de passe est différent
            if ($currentPassword === $newPassword) {
                return [
                    'success' => false,
                    'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.'
                ];
            }

            // Vérifier la force du nouveau mot de passe
            if (!SecurityManager::isStrongPassword($newPassword)) {
                return [
                    'success' => false,
                    'message' => 'Mot de passe trop faible.'
                ];
            }

            // Hasher et mettre à jour
            $hashedPassword = SecurityManager::hashPassword($newPassword);

            $stmt = $this->conn->prepare(
                "UPDATE admin SET password = ? WHERE id = ?"
            );
            $stmt->execute([$hashedPassword, $admin_id]);

            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès.'
            ];

        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe.'
            ];
        }
    }
}
