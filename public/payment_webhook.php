<?php
/**
 * Payment Webhook Handler
 *
 * Endpoint appelé par les providers de paiement pour notifier des changements de statut
 * Fonctionne en arrière-plan (ne redirige pas l'utilisateur)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/classes/PaymentFactory.php';

// EmailService peut ne pas exister, on le charge conditionnellement
if (file_exists(__DIR__ . '/../admin/classes/EmailService.php')) {
    require_once __DIR__ . '/../admin/classes/EmailService.php';
}

// Récupérer le provider depuis les paramètres
$provider = $_GET['provider'] ?? '';

// Logger la requête
$logData = [
    'provider' => $provider,
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'timestamp' => date('Y-m-d H:i:s')
];

error_log("Webhook received: " . json_encode($logData));

try {
    // Validation basique
    if (empty($provider)) {
        throw new Exception('Provider manquant dans le webhook');
    }

    // Récupérer le payload
    $payload = [];

    // Essayer JSON d'abord
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $payload = json_decode($rawInput, true);
    }

    // Sinon essayer $_POST
    if (empty($payload)) {
        $payload = $_POST;
    }

    // Si toujours vide, utiliser $_GET (certains providers utilisent GET pour les webhooks)
    if (empty($payload)) {
        $payload = $_GET;
    }

    if (empty($payload)) {
        throw new Exception('Payload vide dans le webhook');
    }

    // Créer le provider
    $paymentGateway = PaymentFactory::create($provider, $conn);

    if (!$paymentGateway) {
        throw new Exception("Provider non supporté: $provider");
    }

    // Traiter le webhook
    $result = $paymentGateway->handleWebhook($payload);

    if ($result['success']) {
        // Webhook traité avec succès

        // Si paiement réussi, envoyer notification
        if ($result['status'] === 'success') {
            // Récupérer le paiement et la commande
            $stmt = $conn->prepare("
                SELECT p.*, c.*
                FROM paiements p
                INNER JOIN commandes c ON p.commande_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$result['payment_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data && !empty($data['client_email'])) {
                try {
                    $emailService = new EmailService();
                    $emailService->sendOrderConfirmation($data['client_email'], [
                        'id' => $data['commande_id'],
                        'client_nom' => $data['client_nom'],
                        'montant_total' => $data['montant'],
                        'payment_method' => $provider,
                        'payment_status' => 'paid'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send confirmation email from webhook: " . $e->getMessage());
                }
            }
        }

        // Répondre au provider (200 OK)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'transaction_id' => $result['transaction_id'] ?? null
        ]);

    } else {
        // Échec du traitement
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Webhook processing failed'
        ]);
    }

} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());

    // Répondre avec erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
