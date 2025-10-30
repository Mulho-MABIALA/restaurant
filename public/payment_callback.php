<?php
/**
 * Payment Callback Handler
 *
 * Endpoint appelé après qu'un utilisateur a complété (ou annulé) le paiement
 * C'est la page de retour après paiement
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/classes/PaymentFactory.php';

// EmailService peut ne pas exister, on le charge conditionnellement
if (file_exists(__DIR__ . '/../admin/classes/EmailService.php')) {
    require_once __DIR__ . '/../admin/classes/EmailService.php';
}

// Démarrer la session
session_start();

// Récupérer les paramètres
$provider = $_GET['provider'] ?? '';
$paymentId = $_GET['payment_id'] ?? '';
$transactionId = $_GET['transaction_id'] ?? $_GET['token'] ?? '';

// Logger la requête
error_log("Payment callback received - Provider: $provider, PaymentID: $paymentId, TransactionID: $transactionId");

try {
    // Validation basique
    if (empty($provider) || empty($paymentId)) {
        throw new Exception('Paramètres manquants dans le callback');
    }

    // Récupérer le paiement depuis la BDD
    $stmt = $conn->prepare("SELECT * FROM paiements WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Paiement introuvable');
    }

    // Récupérer la commande
    $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$payment['commande_id']]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        throw new Exception('Commande introuvable');
    }

    // Créer le provider
    $paymentGateway = PaymentFactory::create($provider, $conn);

    if ($paymentGateway) {
        // Vérifier le statut du paiement via l'API
        $verifyResult = $paymentGateway->verifyPayment($transactionId ?: $payment['transaction_id']);

        if ($verifyResult['success']) {
            // Mettre à jour le paiement avec le statut vérifié
            $stmt = $conn->prepare("
                UPDATE paiements
                SET statut = ?,
                    callback_data = ?,
                    payment_confirmed_at = CASE WHEN ? = 'success' THEN NOW() ELSE payment_confirmed_at END
                WHERE id = ?
            ");

            $stmt->execute([
                $verifyResult['status'],
                json_encode([
                    'callback_time' => date('Y-m-d H:i:s'),
                    'get_params' => $_GET,
                    'verify_result' => $verifyResult
                ]),
                $verifyResult['status'],
                $paymentId
            ]);

            // Si paiement réussi, mettre à jour la commande
            if ($verifyResult['status'] === 'success') {
                $stmt = $conn->prepare("
                    UPDATE commandes
                    SET statut = 'Confirmée',
                        payment_status = 'paid',
                        payment_method = ?,
                        payment_id = ?,
                        paid_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([$provider, $paymentId, $commande['id']]);

                // Envoyer email de confirmation
                try {
                    $emailService = new EmailService();
                    $emailService->sendOrderConfirmation($commande['client_email'], [
                        'id' => $commande['id'],
                        'client_nom' => $commande['client_nom'],
                        'montant_total' => $commande['montant_total'],
                        'payment_method' => $provider,
                        'payment_status' => 'paid'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send confirmation email: " . $e->getMessage());
                }

                // Stocker les infos pour le modal newsletter
                if (!empty($commande['email'])) {
                    $_SESSION['commande_id'] = $commande['id'];
                    $_SESSION['commande_email'] = $commande['email'];
                    $_SESSION['commande_nom'] = $commande['nom_client'];
                    $_SESSION['show_newsletter_modal'] = true;
                }

                // Rediriger vers page de succès
                header('Location: confirmation.php?commande=' . $commande['id'] . '&payment_success=1');
                exit;
            } else {
                // Paiement échoué ou en attente
                header('Location: payment_failed.php?reason=' . urlencode($verifyResult['status']) . '&order_id=' . $commande['id']);
                exit;
            }
        } else {
            throw new Exception('Impossible de vérifier le statut du paiement');
        }
    } else {
        // Paiement cash - pas de vérification nécessaire
        header('Location: confirmation.php?id=' . $commande['id']);
        exit;
    }

} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());

    // Rediriger vers page d'erreur
    header('Location: payment_failed.php?error=' . urlencode($e->getMessage()));
    exit;
}
