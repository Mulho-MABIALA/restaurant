<?php
/**
 * Provider Paydunya
 *
 * Intégration avec Paydunya (Orange Money, Wave, Cartes bancaires pour l'Afrique de l'Ouest)
 * Documentation: https://paydunya.com/developers/
 */

require_once __DIR__ . '/../PaymentGateway.php';

class PaydunyaProvider extends PaymentGateway {
    private $masterKey;
    private $privateKey;
    private $publicKey;
    private $token;
    private $apiUrl;

    public function __construct($conn, $config = []) {
        parent::__construct($conn, $config);

        $this->masterKey = $_ENV['PAYDUNYA_MASTER_KEY'] ?? '';
        $this->privateKey = $_ENV['PAYDUNYA_PRIVATE_KEY'] ?? '';
        $this->publicKey = $_ENV['PAYDUNYA_PUBLIC_KEY'] ?? '';
        $this->token = $_ENV['PAYDUNYA_TOKEN'] ?? '';

        $this->apiUrl = $this->isTestMode
            ? 'https://app.paydunya.com/sandbox-api/v1'
            : 'https://app.paydunya.com/api/v1';
    }

    protected function getProviderName() {
        return 'paydunya';
    }

    /**
     * Créer un paiement Paydunya
     */
    public function createPayment($orderData, $amount) {
        try {
            // Valider le montant
            $this->validateAmount($amount);

            if (empty($this->masterKey) || empty($this->privateKey) || empty($this->token)) {
                throw new Exception('Paydunya API credentials not configured');
            }

            // Créer l'enregistrement de paiement
            $paymentId = $this->logPayment(
                $orderData['id'],
                $amount,
                'paydunya',
                'pending'
            );

            // Préparer la requête selon le format Paydunya
            $requestData = [
                'invoice' => [
                    'total_amount' => $amount,
                    'description' => 'Commande #' . $orderData['id'] . ' - ' . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho')
                ],
                'store' => [
                    'name' => defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho',
                    'tagline' => 'Cuisine sénégalaise authentique',
                    'phone' => $_ENV['RESTAURANT_PHONE'] ?? '',
                    'postal_address' => 'Dakar, Sénégal',
                    'logo_url' => $this->getBaseUrl() . '/assets/img/logo.png'
                ],
                'actions' => [
                    'cancel_url' => $this->getCancelUrl() . '?provider=paydunya&payment_id=' . $paymentId,
                    'return_url' => $this->getCallbackUrl() . '?provider=paydunya&payment_id=' . $paymentId,
                    'callback_url' => $this->getWebhookUrl() . '?provider=paydunya'
                ],
                'custom_data' => [
                    'order_id' => $orderData['id'],
                    'payment_id' => $paymentId,
                    'client_name' => $orderData['client_nom'] ?? '',
                    'client_phone' => $orderData['client_telephone'] ?? ''
                ]
            ];

            // Headers avec authentification Paydunya
            $headers = [
                'PAYDUNYA-MASTER-KEY: ' . $this->masterKey,
                'PAYDUNYA-PRIVATE-KEY: ' . $this->privateKey,
                'PAYDUNYA-TOKEN: ' . $this->token,
                'Content-Type: application/json'
            ];

            // Appel API
            $response = $this->makeRequest(
                $this->apiUrl . '/checkout-invoice/create',
                'POST',
                $requestData,
                $headers
            );

            if (!$response['success']) {
                throw new Exception('Paydunya API error: ' . json_encode($response));
            }

            $responseData = $response['data'];

            // Vérifier la réponse
            if (empty($responseData['response_code']) || $responseData['response_code'] !== '00') {
                throw new Exception('Paydunya error: ' . ($responseData['response_text'] ?? 'Unknown error'));
            }

            if (empty($responseData['token']) || empty($responseData['response_text'])) {
                throw new Exception('Invalid Paydunya response: ' . json_encode($responseData));
            }

            // Construire l'URL de paiement
            $paymentUrl = $this->isTestMode
                ? 'https://app.paydunya.com/sandbox-invoice/' . $responseData['token']
                : 'https://app.paydunya.com/invoice/' . $responseData['token'];

            // Mettre à jour le paiement
            $this->updatePayment($paymentId, [
                'payment_token' => $responseData['token'],
                'transaction_id' => $responseData['token'],
                'response_data' => $responseData
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'payment_url' => $paymentUrl,
                'payment_token' => $responseData['token'],
                'transaction_id' => $responseData['token'],
                'provider' => 'paydunya'
            ];

        } catch (Exception $e) {
            $this->logger->log('Paydunya createPayment failed', 'ERROR', [
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
     * Vérifier le statut d'un paiement Paydunya
     */
    public function verifyPayment($transactionId) {
        try {
            if (empty($this->masterKey) || empty($this->token)) {
                throw new Exception('Paydunya API credentials not configured');
            }

            $headers = [
                'PAYDUNYA-MASTER-KEY: ' . $this->masterKey,
                'PAYDUNYA-PRIVATE-KEY: ' . $this->privateKey,
                'PAYDUNYA-TOKEN: ' . $this->token,
                'Accept: application/json'
            ];

            $response = $this->makeRequest(
                $this->apiUrl . '/checkout-invoice/confirm/' . $transactionId,
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

            // Vérifier le code de réponse
            if ($data['response_code'] !== '00') {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error' => $data['response_text'] ?? 'Unknown error'
                ];
            }

            // Mapper le statut Paydunya
            $status = $this->mapPaydunyaStatus($data['status'] ?? '');

            return [
                'success' => true,
                'status' => $status,
                'transaction_id' => $transactionId,
                'data' => $data
            ];

        } catch (Exception $e) {
            $this->logger->log('Paydunya verifyPayment failed', 'ERROR', [
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
     * Rembourser un paiement Paydunya (non supporté directement par l'API)
     */
    public function refundPayment($transactionId, $amount = null, $reason = '') {
        // Paydunya ne supporte pas les remboursements automatiques via API
        // Il faut contacter le support Paydunya

        $this->logger->log('Paydunya refund requested (manual process required)', 'WARNING', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        return [
            'success' => false,
            'error' => 'Les remboursements Paydunya nécessitent un traitement manuel. Veuillez contacter le support Paydunya.',
            'manual_process_required' => true
        ];
    }

    /**
     * Traiter un webhook Paydunya
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
                'paydunya',
                'payment.notification',
                json_encode($payload),
                $_SERVER['REMOTE_ADDR'] ?? null,
                json_encode(getallheaders())
            ]);

            $webhookLogId = $this->db->lastInsertId();

            // Extraire les données
            $token = $payload['data']['token'] ?? null;
            $status = $payload['data']['status'] ?? null;

            if (!$token) {
                throw new Exception('Missing token in webhook');
            }

            // Trouver le paiement
            $payment = $this->getPaymentByTransactionId($token);

            if (!$payment) {
                throw new Exception('Payment not found for token: ' . $token);
            }

            // Vérifier le paiement via l'API (double vérification de sécurité)
            $verifyResult = $this->verifyPayment($token);

            if (!$verifyResult['success']) {
                throw new Exception('Payment verification failed');
            }

            $mappedStatus = $verifyResult['status'];

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
                'transaction_id' => $token,
                'status' => $mappedStatus,
                'payment_id' => $payment['id']
            ];

        } catch (Exception $e) {
            $this->logger->log('Paydunya webhook processing failed', 'ERROR', [
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
     * Mapper les statuts Paydunya vers nos statuts
     */
    private function mapPaydunyaStatus($paydunyaStatus) {
        $statusMap = [
            'completed' => 'success',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'failed' => 'failed'
        ];

        return $statusMap[strtolower($paydunyaStatus)] ?? 'pending';
    }
}
