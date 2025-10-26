<?php
/**
 * Classe abstraite PaymentGateway
 *
 * Définit l'interface commune pour tous les providers de paiement
 * (Orange Money, Wave, Paydunya, Stripe, etc.)
 */

abstract class PaymentGateway {
    protected $db;
    protected $config;
    protected $isTestMode;
    protected $logger;

    /**
     * Constructeur
     */
    public function __construct($conn, $config = []) {
        $this->db = $conn;
        $this->config = $config;
        $this->isTestMode = $config['test_mode'] ?? false;
        $this->logger = new PaymentLogger($conn);
    }

    /**
     * Créer un paiement
     *
     * @param array $orderData Données de la commande
     * @param float $amount Montant en FCFA
     * @return array ['success' => bool, 'payment_url' => string, 'payment_token' => string, ...]
     */
    abstract public function createPayment($orderData, $amount);

    /**
     * Vérifier le statut d'un paiement
     *
     * @param string $transactionId ID de la transaction
     * @return array ['success' => bool, 'status' => string, 'data' => array]
     */
    abstract public function verifyPayment($transactionId);

    /**
     * Rembourser un paiement
     *
     * @param string $transactionId ID de la transaction
     * @param float $amount Montant à rembourser (optionnel, sinon montant total)
     * @param string $reason Raison du remboursement
     * @return array ['success' => bool, 'refund_id' => string, ...]
     */
    abstract public function refundPayment($transactionId, $amount = null, $reason = '');

    /**
     * Obtenir le statut d'un paiement
     *
     * @param string $transactionId ID de la transaction
     * @return string 'pending', 'success', 'failed', 'refunded', 'cancelled'
     */
    abstract public function getPaymentStatus($transactionId);

    /**
     * Traiter un webhook
     *
     * @param array $payload Données du webhook
     * @return array ['success' => bool, 'transaction_id' => string, 'status' => string]
     */
    abstract public function handleWebhook($payload);

    /**
     * Enregistrer un paiement dans la base de données
     *
     * @param int $commandeId ID de la commande
     * @param float $amount Montant
     * @param string $provider Nom du provider
     * @param string $status Statut initial
     * @param array $requestData Données envoyées au provider
     * @return int ID du paiement créé
     */
    protected function logPayment($commandeId, $amount, $provider, $status = 'pending', $requestData = []) {
        $stmt = $this->db->prepare("
            INSERT INTO paiements
            (commande_id, montant, provider, statut, request_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $requestDataJson = json_encode($requestData);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt->execute([
            $commandeId,
            $amount,
            $provider,
            $status,
            $requestDataJson,
            $ipAddress,
            $userAgent
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Mettre à jour un paiement
     *
     * @param int $paymentId ID du paiement
     * @param array $data Données à mettre à jour
     */
    protected function updatePayment($paymentId, $data) {
        $allowedFields = ['statut', 'transaction_id', 'payment_token', 'response_data',
                          'callback_data', 'webhook_data', 'payment_confirmed_at'];

        $updates = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";

                // Encoder en JSON si c'est un tableau
                if (is_array($value)) {
                    $values[] = json_encode($value);
                } else {
                    $values[] = $value;
                }
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $paymentId;

        $sql = "UPDATE paiements SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Récupérer un paiement par ID
     */
    protected function getPayment($paymentId) {
        $stmt = $this->db->prepare("SELECT * FROM paiements WHERE id = ?");
        $stmt->execute([$paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un paiement par transaction_id
     */
    protected function getPaymentByTransactionId($transactionId) {
        $stmt = $this->db->prepare("SELECT * FROM paiements WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Faire une requête HTTP cURL
     *
     * @param string $url URL de l'API
     * @param string $method Méthode HTTP (GET, POST, PUT)
     * @param array $data Données à envoyer
     * @param array $headers Headers HTTP
     * @return array ['success' => bool, 'data' => mixed, 'http_code' => int]
     */
    protected function makeRequest($url, $method = 'POST', $data = [], $headers = []) {
        $ch = curl_init();

        // Configuration commune
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isTestMode);

        // Méthode HTTP
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'GET':
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        // Headers
        if (empty($headers)) {
            $headers = ['Content-Type: application/json'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Exécution
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Logger la requête
        $this->logger->logRequest($url, $method, $data, $response, $httpCode);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'data' => $responseData ?? $response,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }

    /**
     * Générer une URL de callback
     */
    protected function getCallbackUrl() {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/payment_callback.php';
    }

    /**
     * Générer une URL de webhook
     */
    protected function getWebhookUrl() {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/payment_webhook.php';
    }

    /**
     * Générer une URL de retour (cancel)
     */
    protected function getCancelUrl() {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/payment_cancel.php';
    }

    /**
     * Obtenir l'URL de base du site
     */
    protected function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Valider un montant
     */
    protected function validateAmount($amount) {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception("Montant invalide: $amount");
        }

        // Vérifier les limites du provider
        $method = $this->getPaymentMethod();
        if ($method) {
            if ($method['min_amount'] && $amount < $method['min_amount']) {
                throw new Exception("Montant minimum: {$method['min_amount']} FCFA");
            }
            if ($method['max_amount'] && $amount > $method['max_amount']) {
                throw new Exception("Montant maximum: {$method['max_amount']} FCFA");
            }
        }

        return true;
    }

    /**
     * Obtenir la configuration du provider depuis la BDD
     */
    protected function getPaymentMethod() {
        $provider = $this->getProviderName();
        $stmt = $this->db->prepare("
            SELECT * FROM payment_methods
            WHERE provider = ? AND is_active = 1
        ");
        $stmt->execute([$provider]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir le nom du provider (à implémenter dans les classes filles)
     */
    abstract protected function getProviderName();

    /**
     * Formater un numéro de téléphone sénégalais
     *
     * @param string $phone Numéro de téléphone
     * @return string Numéro formaté (+221XXXXXXXXX)
     */
    protected function formatPhoneSenegal($phone) {
        // Nettoyer
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ajouter indicatif pays si absent
        if (substr($phone, 0, 3) !== '221') {
            $phone = '221' . $phone;
        }

        return '+' . $phone;
    }

    /**
     * Vérifier la signature d'un webhook (si le provider en utilise)
     *
     * @param string $payload Payload brut
     * @param string $signature Signature reçue
     * @param string $secret Secret partagé
     * @return bool
     */
    protected function verifyWebhookSignature($payload, $signature, $secret) {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Calculer les frais du provider
     */
    protected function calculateFees($amount) {
        $method = $this->getPaymentMethod();
        if (!$method) {
            return 0;
        }

        switch ($method['fee_type']) {
            case 'fixed':
                return $method['fee_value'];
            case 'percentage':
                return $amount * ($method['fee_value'] / 100);
            default:
                return 0;
        }
    }
}

/**
 * Classe pour logger les requêtes de paiement (debug)
 */
class PaymentLogger {
    private $db;
    private $logToDatabase = true;
    private $logToFile = true;
    private $logFilePath;

    public function __construct($conn) {
        $this->db = $conn;
        $this->logFilePath = __DIR__ . '/../../logs/payments.log';

        // Créer le dossier logs si n'existe pas
        $logsDir = dirname($this->logFilePath);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }

    public function logRequest($url, $method, $request, $response, $httpCode) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $url,
            'method' => $method,
            'request' => $request,
            'response' => $response,
            'http_code' => $httpCode
        ];

        if ($this->logToFile) {
            $logLine = json_encode($logEntry) . PHP_EOL;
            file_put_contents($this->logFilePath, $logLine, FILE_APPEND);
        }
    }

    public function log($message, $level = 'INFO', $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        if ($this->logToFile) {
            $logLine = json_encode($logEntry) . PHP_EOL;
            file_put_contents($this->logFilePath, $logLine, FILE_APPEND);
        }
    }
}
