<?php
/**
 * Service de notifications Push via Firebase Cloud Messaging (FCM)
 */

require_once __DIR__ . '/NotificationChannel.php';

class PushNotificationService extends NotificationChannel {
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct($conn, $config = []) {
        parent::__construct($conn, $config);
        $this->serverKey = $_ENV['FIREBASE_SERVER_KEY'] ?? $config['server_key'] ?? '';
    }

    public function getChannelName() {
        return 'push';
    }

    /**
     * Envoyer une notification push
     */
    public function send($recipient, $type, $data) {
        // Vérifier les préférences utilisateur
        if (!$this->checkUserPreferences($recipient, $type)) {
            return [
                'success' => false,
                'error' => 'Utilisateur a désactivé les notifications push pour ce type'
            ];
        }

        // Récupérer les tokens FCM de l'utilisateur
        $tokens = $this->getUserTokens($recipient);

        if (empty($tokens)) {
            return [
                'success' => false,
                'error' => 'Aucun token FCM trouvé pour cet utilisateur'
            ];
        }

        // Préparer le payload
        $payload = $this->buildPayload($data);

        // Envoyer à chaque token
        $results = [];
        foreach ($tokens as $token) {
            $result = $this->sendToToken($token['fcm_token'], $payload);
            $results[] = $result;

            // Logger chaque envoi
            $this->logNotification(
                $recipient,
                $type,
                $data,
                $result['success'] ? 'sent' : 'failed',
                $result
            );

            // Mettre à jour last_used_at si succès
            if ($result['success']) {
                $this->updateTokenLastUsed($token['id']);
            }
        }

        // Retourner le résultat global
        $successCount = count(array_filter($results, fn($r) => $r['success']));

        return [
            'success' => $successCount > 0,
            'tokens_sent' => $successCount,
            'total_tokens' => count($tokens),
            'results' => $results
        ];
    }

    /**
     * Récupérer les tokens FCM actifs d'un utilisateur
     */
    private function getUserTokens($recipient) {
        try {
            $sql = "SELECT * FROM push_notification_tokens WHERE is_active = 1 AND (";
            $params = [];

            if (!empty($recipient['user_id'])) {
                $sql .= "user_id = ? OR ";
                $params[] = $recipient['user_id'];
            }

            if (!empty($recipient['email'])) {
                $sql .= "email = ? OR ";
                $params[] = $recipient['email'];
            }

            if (!empty($recipient['phone'])) {
                $sql .= "telephone = ? OR ";
                $params[] = $recipient['phone'];
            }

            // Retirer le dernier OR
            $sql = rtrim($sql, ' OR ');
            $sql .= ")";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Erreur récupération tokens: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Construire le payload Firebase
     */
    private function buildPayload($data) {
        return [
            'notification' => [
                'title' => $data['title'] ?? (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho'),
                'body' => $data['message'] ?? '',
                'icon' => $data['icon'] ?? '/assets/img/logo.png',
                'image' => $data['image'] ?? null,
                'click_action' => $data['click_action'] ?? '/',
                'sound' => 'default',
                'badge' => '1'
            ],
            'data' => array_merge([
                'type' => $data['type'] ?? 'notification',
                'timestamp' => time()
            ], $data['data'] ?? []),
            'priority' => 'high',
            'time_to_live' => 86400 // 24 heures
        ];
    }

    /**
     * Envoyer à un token spécifique
     */
    private function sendToToken($token, $payload) {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'error' => 'Firebase Server Key non configurée'
            ];
        }

        $payload['to'] = $token;

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init($this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Erreur cURL: ' . $curlError,
                'provider' => 'firebase'
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode === 200 && isset($responseData['success']) && $responseData['success'] > 0) {
            return [
                'success' => true,
                'message_id' => $responseData['results'][0]['message_id'] ?? null,
                'provider' => 'firebase',
                'response' => $responseData
            ];
        }

        // Gérer les erreurs spécifiques
        $error = $responseData['results'][0]['error'] ?? 'Erreur inconnue';

        // Token invalide ou non enregistré
        if (in_array($error, ['InvalidRegistration', 'NotRegistered', 'MismatchSenderId'])) {
            $this->deactivateToken($token);
        }

        return [
            'success' => false,
            'error' => $error,
            'provider' => 'firebase',
            'response' => $responseData
        ];
    }

    /**
     * Enregistrer un nouveau token FCM
     */
    public function registerToken($tokenData) {
        try {
            // Vérifier si le token existe déjà
            $stmt = $this->conn->prepare("SELECT id FROM push_notification_tokens WHERE fcm_token = ?");
            $stmt->execute([$tokenData['fcm_token']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Mettre à jour
                $stmt = $this->conn->prepare("
                    UPDATE push_notification_tokens
                    SET is_active = 1,
                        last_used_at = NOW(),
                        updated_at = NOW()
                    WHERE fcm_token = ?
                ");
                $stmt->execute([$tokenData['fcm_token']]);
                return ['success' => true, 'token_id' => $existing['id']];
            }

            // Insérer nouveau token
            $stmt = $this->conn->prepare("
                INSERT INTO push_notification_tokens (
                    user_id,
                    email,
                    telephone,
                    fcm_token,
                    device_type,
                    device_name,
                    browser,
                    os,
                    is_active,
                    last_used_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                $tokenData['user_id'] ?? null,
                $tokenData['email'] ?? null,
                $tokenData['phone'] ?? null,
                $tokenData['fcm_token'],
                $tokenData['device_type'] ?? 'web',
                $tokenData['device_name'] ?? null,
                $tokenData['browser'] ?? null,
                $tokenData['os'] ?? null
            ]);

            return [
                'success' => true,
                'token_id' => $this->conn->lastInsertId()
            ];

        } catch (PDOException $e) {
            error_log("Erreur enregistrement token: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Désactiver un token invalide
     */
    private function deactivateToken($token) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE push_notification_tokens
                SET is_active = 0
                WHERE fcm_token = ?
            ");
            $stmt->execute([$token]);
        } catch (PDOException $e) {
            error_log("Erreur désactivation token: " . $e->getMessage());
        }
    }

    /**
     * Mettre à jour last_used_at
     */
    private function updateTokenLastUsed($tokenId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE push_notification_tokens
                SET last_used_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tokenId]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour token: " . $e->getMessage());
        }
    }

    /**
     * Envoyer une notification avec template
     */
    public function sendWithTemplate($recipient, $templateKey, $variables = []) {
        $template = $this->getTemplate($templateKey);

        if (!$template) {
            return [
                'success' => false,
                'error' => 'Template non trouvé'
            ];
        }

        $data = [
            'title' => $this->replaceVariables($template['push_title'], $variables),
            'message' => $this->replaceVariables($template['push_body'], $variables),
            'icon' => $template['push_icon'],
            'image' => $template['push_image'],
            'click_action' => $template['push_action_url'],
            'type' => $template['notification_type'],
            'data' => $variables
        ];

        return $this->send($recipient, $template['notification_type'], $data);
    }

    /**
     * Envoyer une notification à plusieurs utilisateurs
     */
    public function sendBulk($recipients, $type, $data) {
        $results = [];

        foreach ($recipients as $recipient) {
            $results[] = $this->send($recipient, $type, $data);
            usleep(100000); // 100ms entre chaque envoi pour éviter throttling
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results
        ];
    }
}
