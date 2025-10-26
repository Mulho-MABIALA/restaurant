<?php
/**
 * Provider Wave
 *
 * Intégration avec l'API Wave pour le Sénégal
 * Documentation: https://developer.wave.com/
 */

require_once __DIR__ . '/../PaymentGateway.php';

class WaveProvider extends PaymentGateway {
    private $apiKey;
    private $apiSecret;
    private $apiUrl;

    public function __construct($conn, $config = []) {
        parent::__construct($conn, $config);

        $this->apiKey = $_ENV['WAVE_API_KEY'] ?? '';
        $this->apiSecret = $_ENV['WAVE_API_SECRET'] ?? '';

        $this->apiUrl = $this->isTestMode
            ? 'https://api.wave.com/v1/test'
            : 'https://api.wave.com/v1';
    }

    protected function getProviderName() {
        return 'wave';
    }

    /**
     * Créer un paiement Wave
     */
    public function createPayment($orderData, $amount) {
        try {
            // Valider le montant
            $this->validateAmount($amount);

            if (empty($this->apiKey) || empty($this->apiSecret)) {
                throw new Exception('Wave API credentials not configured');
            }

            // Créer l'enregistrement de paiement
            $paymentId = $this->logPayment(
                $orderData['id'],
                $amount,
                'wave',
                'pending'
            );

            // Préparer la requête
            $requestData = [
                'amount' => $amount,
                'currency' => 'XOF',
                'error_url' => $this->getCancelUrl() . '?provider=wave&payment_id=' . $paymentId,
                'success_url' => $this->getCallbackUrl() . '?provider=wave&payment_id=' . $paymentId,
                'metadata' => [
                    'order_id' => $orderData['id'],
                    'payment_id' => $paymentId,
                    'client_name' => $orderData['client_nom'] ?? '',
                    'client_phone' => $orderData['client_telephone'] ?? ''
                ]
            ];

            // Headers avec authentification
            $headers = [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ];

            // Appel API
            $response = $this->makeRequest(
                $this->apiUrl . '/checkout/sessions',
                'POST',
                $requestData,
                $headers
            );

            if (!$response['success']) {
                throw new Exception('Wave API error: ' . json_encode($response));
            }

            $responseData = $response['data'];

            // Vérifier la réponse
            if (empty($responseData['id']) || empty($responseData['wave_launch_url'])) {
                throw new Exception('Invalid Wave response: ' . json_encode($responseData));
            }

            // Mettre à jour le paiement
            $this->updatePayment($paymentId, [
                'payment_token' => $responseData['id'],
                'transaction_id' => $responseData['id'],
                'response_data' => $responseData
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'payment_url' => $responseData['wave_launch_url'],
                'payment_token' => $responseData['id'],
                'transaction_id' => $responseData['id'],
                'provider' => 'wave'
            ];

        } catch (Exception $e) {
            $this->logger->log('Wave createPayment failed', 'ERROR', [
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
     * Vérifier le statut d'un paiement Wave
     */
    public function verifyPayment($transactionId) {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('Wave API key not configured');
            }

            $headers = [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json'
            ];

            $response = $this->makeRequest(
                $this->apiUrl . '/checkout/sessions/' . $transactionId,
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

            // Mapper le statut Wave
            $status = $this->mapWaveStatus($data['status'] ?? '');

            return [
                'success' => true,
                'status' => $status,
                'transaction_id' => $transactionId,
                'data' => $data
            ];

        } catch (Exception $e) {
            $this->logger->log('Wave verifyPayment failed', 'ERROR', [
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
     * Rembourser un paiement Wave
     */
    public function refundPayment($transactionId, $amount = null, $reason = '') {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('Wave API key not configured');
            }

            // Récupérer le paiement original
            $payment = $this->getPaymentByTransactionId($transactionId);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            // Si pas de montant spécifié, rembourser le montant total
            if ($amount === null) {
                $amount = $payment['montant'];
            }

            // Vérifier que le montant ne dépasse pas le montant original
            if ($amount > $payment['montant']) {
                throw new Exception('Refund amount cannot exceed original amount');
            }

            $requestData = [
                'amount' => $amount,
                'reason' => $reason ?: 'Remboursement demandé par le marchand'
            ];

            $headers = [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ];

            $response = $this->makeRequest(
                $this->apiUrl . '/checkout/sessions/' . $transactionId . '/refund',
                'POST',
                $requestData,
                $headers
            );

            if (!$response['success']) {
                throw new Exception('Wave refund failed: ' . json_encode($response));
            }

            $responseData = $response['data'];

            // Mettre à jour le paiement
            $this->updatePayment($payment['id'], [
                'statut' => 'refunded',
                'refund_amount' => $amount,
                'refund_reason' => $reason,
                'refunded_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'refund_id' => $responseData['id'] ?? null,
                'amount' => $amount,
                'transaction_id' => $transactionId
            ];

        } catch (Exception $e) {
            $this->logger->log('Wave refund failed', 'ERROR', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'amount' => $amount
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Traiter un webhook Wave
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
                'wave',
                $payload['type'] ?? 'payment.notification',
                json_encode($payload),
                $_SERVER['REMOTE_ADDR'] ?? null,
                json_encode(getallheaders())
            ]);

            $webhookLogId = $this->db->lastInsertId();

            // Vérifier la signature du webhook (sécurité)
            if (!$this->verifyWaveWebhook($payload)) {
                throw new Exception('Invalid webhook signature');
            }

            // Extraire les données
            $sessionId = $payload['data']['id'] ?? null;

            if (!$sessionId) {
                throw new Exception('Missing session ID in webhook');
            }

            // Trouver le paiement
            $payment = $this->getPaymentByTransactionId($sessionId);

            if (!$payment) {
                throw new Exception('Payment not found for session: ' . $sessionId);
            }

            // Mapper le statut
            $status = $this->mapWaveStatus($payload['data']['status'] ?? '');

            // Mettre à jour le paiement
            $updateData = [
                'statut' => $status,
                'webhook_data' => $payload
            ];

            if ($status === 'success') {
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
                'transaction_id' => $sessionId,
                'status' => $status,
                'payment_id' => $payment['id']
            ];

        } catch (Exception $e) {
            $this->logger->log('Wave webhook processing failed', 'ERROR', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

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
     * Vérifier la signature d'un webhook Wave
     */
    private function verifyWaveWebhook($payload) {
        // Wave utilise une signature HMAC dans les headers
        $signature = $_SERVER['HTTP_X_WAVE_SIGNATURE'] ?? '';

        if (empty($signature)) {
            return false; // Pas de signature = webhook invalide
        }

        $payloadString = json_encode($payload);
        return $this->verifyWebhookSignature($payloadString, $signature, $this->apiSecret);
    }

    /**
     * Mapper les statuts Wave vers nos statuts
     */
    private function mapWaveStatus($waveStatus) {
        $statusMap = [
            'complete' => 'success',
            'completed' => 'success',
            'pending' => 'pending',
            'processing' => 'processing',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'expired' => 'failed'
        ];

        return $statusMap[strtolower($waveStatus)] ?? 'pending';
    }
}
