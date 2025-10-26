<?php
/**
 * Provider Orange Money
 *
 * Intégration avec l'API Orange Money pour le Sénégal
 * Documentation: https://developer.orange.com/apis/orange-money-webpay/
 */

require_once __DIR__ . '/../PaymentGateway.php';

class OrangeMoneyProvider extends PaymentGateway {
    private $merchantKey;
    private $apiUrl;
    private $accessToken;
    private $authUrl;

    public function __construct($conn, $config = []) {
        parent::__construct($conn, $config);

        $this->merchantKey = $_ENV['ORANGE_MONEY_MERCHANT_KEY'] ?? '';
        $this->authUrl = $this->isTestMode
            ? 'https://api.orange.com/oauth/v3/token'
            : 'https://api.orange.com/oauth/v3/token';

        $this->apiUrl = $this->isTestMode
            ? 'https://api.orange.com/orange-money-webpay/dev/v1'
            : 'https://api.orange.com/orange-money-webpay/sn/v1';

        // Authentifier et obtenir le token
        $this->authenticate();
    }

    protected function getProviderName() {
        return 'orange_money';
    }

    /**
     * Authentification OAuth2 pour obtenir le token d'accès
     */
    private function authenticate() {
        $clientId = $_ENV['ORANGE_MONEY_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['ORANGE_MONEY_CLIENT_SECRET'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            $this->logger->log('Orange Money credentials not configured', 'ERROR');
            return false;
        }

        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->authUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isTestMode);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'] ?? null;
            return true;
        }

        $this->logger->log('Orange Money authentication failed', 'ERROR', [
            'http_code' => $httpCode,
            'response' => $response
        ]);

        return false;
    }

    /**
     * Créer un paiement Orange Money
     */
    public function createPayment($orderData, $amount) {
        try {
            // Valider le montant
            $this->validateAmount($amount);

            if (empty($this->accessToken)) {
                throw new Exception('Orange Money access token not available');
            }

            // Créer l'enregistrement de paiement
            $paymentId = $this->logPayment(
                $orderData['id'],
                $amount,
                'orange_money',
                'pending'
            );

            // Générer un order_id unique
            $orderId = 'OM-' . $orderData['id'] . '-' . time();

            // Préparer la requête
            $requestData = [
                'merchant_key' => $this->merchantKey,
                'currency' => 'XOF',
                'order_id' => $orderId,
                'amount' => (int)$amount, // Orange Money attend un entier
                'return_url' => $this->getCallbackUrl() . '?provider=orange_money&payment_id=' . $paymentId,
                'cancel_url' => $this->getCancelUrl() . '?provider=orange_money&payment_id=' . $paymentId,
                'notif_url' => $this->getWebhookUrl() . '?provider=orange_money',
                'lang' => 'fr',
                'reference' => 'CMD-' . $orderData['id']
            ];

            // Appel API
            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            $response = $this->makeRequest(
                $this->apiUrl . '/webpayment',
                'POST',
                $requestData,
                $headers
            );

            if (!$response['success']) {
                throw new Exception('Orange Money API error: ' . json_encode($response));
            }

            $responseData = $response['data'];

            // Vérifier la réponse
            if (empty($responseData['payment_url']) || empty($responseData['pay_token'])) {
                throw new Exception('Invalid Orange Money response: ' . json_encode($responseData));
            }

            // Mettre à jour le paiement avec les infos de réponse
            $this->updatePayment($paymentId, [
                'payment_token' => $responseData['pay_token'],
                'transaction_id' => $orderId,
                'response_data' => $responseData
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'payment_url' => $responseData['payment_url'],
                'payment_token' => $responseData['pay_token'],
                'transaction_id' => $orderId,
                'provider' => 'orange_money'
            ];

        } catch (Exception $e) {
            $this->logger->log('Orange Money createPayment failed', 'ERROR', [
                'error' => $e->getMessage(),
                'order_id' => $orderData['id'] ?? null
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function verifyPayment($transactionId) {
        try {
            if (empty($this->accessToken)) {
                $this->authenticate();
            }

            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json'
            ];

            $response = $this->makeRequest(
                $this->apiUrl . '/webpayment/' . $transactionId,
                'GET',
                [],
                $headers
            );

            if (!$response['success']) {
                return [
                    'success' => false,
                    'status' => 'unknown',
                    'error' => 'API error'
                ];
            }

            $data = $response['data'];

            // Mapper le statut Orange Money vers notre système
            $status = $this->mapOrangeMoneyStatus($data['status'] ?? '');

            return [
                'success' => true,
                'status' => $status,
                'transaction_id' => $transactionId,
                'data' => $data
            ];

        } catch (Exception $e) {
            $this->logger->log('Orange Money verifyPayment failed', 'ERROR', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtenir le statut d'un paiement
     */
    public function getPaymentStatus($transactionId) {
        $result = $this->verifyPayment($transactionId);
        return $result['status'] ?? 'unknown';
    }

    /**
     * Rembourser un paiement (Note: Orange Money ne supporte pas toujours les remboursements automatiques)
     */
    public function refundPayment($transactionId, $amount = null, $reason = '') {
        // Orange Money ne supporte pas les remboursements automatiques via API
        // Il faut contacter le support Orange Money

        $this->logger->log('Orange Money refund requested (manual process required)', 'WARNING', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        return [
            'success' => false,
            'error' => 'Les remboursements Orange Money nécessitent un traitement manuel. Veuillez contacter le support Orange Money.',
            'manual_process_required' => true
        ];
    }

    /**
     * Traiter un webhook Orange Money
     */
    public function handleWebhook($payload) {
        try {
            // Logger le webhook
            $stmt = $this->db->prepare("
                INSERT INTO payment_webhooks_log
                (provider, event_type, payload, ip_address, headers)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                'orange_money',
                $payload['event'] ?? 'payment.notification',
                json_encode($payload),
                $_SERVER['REMOTE_ADDR'] ?? null,
                json_encode(getallheaders())
            ]);

            $webhookLogId = $this->db->lastInsertId();

            // Extraire les données
            $orderId = $payload['order_id'] ?? null;
            $status = $payload['status'] ?? null;
            $payToken = $payload['pay_token'] ?? null;

            if (!$orderId) {
                throw new Exception('Missing order_id in webhook');
            }

            // Trouver le paiement
            $payment = $this->getPaymentByTransactionId($orderId);

            if (!$payment) {
                throw new Exception('Payment not found for order_id: ' . $orderId);
            }

            // Mapper le statut
            $mappedStatus = $this->mapOrangeMoneyStatus($status);

            // Mettre à jour le paiement
            $updateData = [
                'statut' => $mappedStatus,
                'webhook_data' => $payload
            ];

            if ($mappedStatus === 'success') {
                $updateData['payment_confirmed_at'] = date('Y-m-d H:i:s');
            }

            $this->updatePayment($payment['id'], $updateData);

            // Marquer le webhook comme traité
            $stmt = $this->db->prepare("
                UPDATE payment_webhooks_log
                SET processed = 1, processed_at = NOW(), paiement_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$payment['id'], $webhookLogId]);

            return [
                'success' => true,
                'transaction_id' => $orderId,
                'status' => $mappedStatus,
                'payment_id' => $payment['id']
            ];

        } catch (Exception $e) {
            $this->logger->log('Orange Money webhook processing failed', 'ERROR', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            // Marquer le webhook comme échoué
            if (isset($webhookLogId)) {
                $stmt = $this->db->prepare("
                    UPDATE payment_webhooks_log
                    SET processed = 0, error_message = ?
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $webhookLogId]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mapper les statuts Orange Money vers nos statuts
     */
    private function mapOrangeMoneyStatus($orangeStatus) {
        $statusMap = [
            'SUCCESS' => 'success',
            'INITIATED' => 'pending',
            'PENDING' => 'processing',
            'FAILED' => 'failed',
            'EXPIRED' => 'failed',
            'CANCELLED' => 'cancelled'
        ];

        return $statusMap[strtoupper($orangeStatus)] ?? 'pending';
    }
}
