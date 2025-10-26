<?php
/**
 * Service de notifications SMS via Orange SMS API (Sénégal)
 */

require_once __DIR__ . '/NotificationChannel.php';

class SMSService extends NotificationChannel {
    private $clientId;
    private $clientSecret;
    private $senderName;
    private $apiUrl;
    private $tokenUrl;
    private $accessToken;
    private $costPerSMS = 25; // Prix en FCFA par SMS

    public function __construct($conn, $config = []) {
        parent::__construct($conn, $config);

        $this->clientId = $_ENV['ORANGE_SMS_CLIENT_ID'] ?? $config['client_id'] ?? '';
        $this->clientSecret = $_ENV['ORANGE_SMS_CLIENT_SECRET'] ?? $config['client_secret'] ?? '';
        $this->senderName = $_ENV['ORANGE_SMS_SENDER_NAME'] ?? $config['sender_name'] ?? 'RestauMulho';

        // API URLs Sénégal
        $this->apiUrl = 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B221338000000/requests';
        $this->tokenUrl = 'https://api.orange.com/oauth/v3/token';
    }

    public function getChannelName() {
        return 'sms';
    }

    /**
     * Envoyer un SMS
     */
    public function send($recipient, $type, $data) {
        // Vérifier les préférences utilisateur
        if (!$this->checkUserPreferences($recipient, $type)) {
            return [
                'success' => false,
                'error' => 'Utilisateur a désactivé les SMS pour ce type de notification'
            ];
        }

        // Valider le numéro de téléphone
        $phone = $this->validatePhone($recipient['phone'] ?? '');

        if (!$phone) {
            return [
                'success' => false,
                'error' => 'Numéro de téléphone invalide'
            ];
        }

        // Obtenir le message
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return [
                'success' => false,
                'error' => 'Message vide'
            ];
        }

        // Limiter à 160 caractères (1 SMS)
        $message = mb_substr($message, 0, 160);

        // Obtenir le token d'accès
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'error' => 'Échec d\'authentification Orange API'
            ];
        }

        // Envoyer le SMS
        $result = $this->sendSMS($phone, $message);

        // Logger la notification
        $this->logNotification(
            $recipient,
            $type,
            $data,
            $result['success'] ? 'sent' : 'failed',
            array_merge($result, ['cost' => $this->costPerSMS])
        );

        return $result;
    }

    /**
     * Authentification OAuth2 avec Orange
     */
    private function authenticate() {
        if (!empty($this->accessToken)) {
            // Token déjà obtenu
            return true;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            error_log("Orange SMS: Credentials non configurées");
            return false;
        }

        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $postData = 'grant_type=client_credentials';

        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'] ?? null;
            return !empty($this->accessToken);
        }

        error_log("Orange SMS Auth Error: " . $response);
        return false;
    }

    /**
     * Envoyer le SMS via Orange API
     */
    private function sendSMS($phone, $message) {
        // Préparer le payload
        $payload = [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:' . $phone,
                'senderAddress' => 'tel:+221338000000', // Numéro court Orange
                'senderName' => $this->senderName,
                'outboundSMSTextMessage' => [
                    'message' => $message
                ]
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Erreur cURL: ' . $curlError,
                'provider' => 'orange_sms'
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode === 201) {
            // SMS envoyé avec succès
            return [
                'success' => true,
                'message_id' => $responseData['outboundSMSMessageRequest']['resourceURL'] ?? null,
                'provider' => 'orange_sms',
                'cost' => $this->costPerSMS,
                'response' => $responseData
            ];
        }

        return [
            'success' => false,
            'error' => 'Erreur HTTP ' . $httpCode,
            'provider' => 'orange_sms',
            'response' => $responseData
        ];
    }

    /**
     * Envoyer un SMS avec template
     */
    public function sendWithTemplate($recipient, $templateKey, $variables = []) {
        $template = $this->getTemplate($templateKey);

        if (!$template) {
            return [
                'success' => false,
                'error' => 'Template non trouvé'
            ];
        }

        $message = $this->replaceVariables($template['sms_message'], $variables);

        $data = [
            'message' => $message,
            'type' => $template['notification_type']
        ];

        return $this->send($recipient, $template['notification_type'], $data);
    }

    /**
     * Envoyer des SMS en masse
     */
    public function sendBulk($recipients, $type, $data) {
        $results = [];
        $totalCost = 0;

        foreach ($recipients as $recipient) {
            $result = $this->send($recipient, $type, $data);
            $results[] = $result;

            if ($result['success']) {
                $totalCost += $this->costPerSMS;
            }

            // Délai entre chaque envoi (rate limiting)
            usleep(200000); // 200ms
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'total_cost' => $totalCost,
            'results' => $results
        ];
    }

    /**
     * Vérifier le solde SMS (si API disponible)
     */
    public function checkBalance() {
        // Orange API ne fournit pas cette fonctionnalité directement
        // Calculer basé sur les SMS envoyés ce mois

        try {
            $stmt = $this->conn->query("
                SELECT
                    COUNT(*) as sms_sent,
                    SUM(cost) as total_cost
                FROM notifications_log
                WHERE channel = 'sms'
                AND MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['sms_sent' => 0, 'total_cost' => 0];
        }
    }

    /**
     * Obtenir le coût par SMS
     */
    public function getCostPerSMS() {
        return $this->costPerSMS;
    }

    /**
     * Définir le coût par SMS
     */
    public function setCostPerSMS($cost) {
        $this->costPerSMS = $cost;
    }
}
