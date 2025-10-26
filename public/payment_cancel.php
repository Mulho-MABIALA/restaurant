<?php
/**
 * Payment Cancel Handler
 *
 * Page affichée quand l'utilisateur annule le paiement
 */

require_once __DIR__ . '/../config.php';
session_start();

// Récupérer les paramètres
$provider = $_GET['provider'] ?? '';
$paymentId = $_GET['payment_id'] ?? '';

// Mettre à jour le statut du paiement si on a l'ID
if (!empty($paymentId)) {
    try {
        $stmt = $conn->prepare("
            UPDATE paiements
            SET statut = 'cancelled',
                callback_data = ?
            WHERE id = ?
        ");

        $stmt->execute([
            json_encode([
                'cancelled_at' => date('Y-m-d H:i:s'),
                'get_params' => $_GET
            ]),
            $paymentId
        ]);

    } catch (Exception $e) {
        error_log("Error updating cancelled payment: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement annulé - <?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <!-- Icône -->
            <div class="mb-6">
                <div class="mx-auto w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-yellow-600 text-4xl"></i>
                </div>
            </div>

            <!-- Message -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Paiement annulé
            </h1>

            <p class="text-gray-600 mb-8">
                Vous avez annulé le paiement. Votre commande n'a pas été confirmée.
            </p>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="commander.php" class="block w-full bg-yellow-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-yellow-700 transition">
                    <i class="fas fa-redo mr-2"></i>
                    Réessayer le paiement
                </a>

                <a href="menu.php" class="block w-full bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-300 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour au menu
                </a>

                <a href="index.php" class="block w-full text-gray-600 py-2 hover:text-gray-900 transition">
                    Retour à l'accueil
                </a>
            </div>

            <!-- Aide -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-500 mb-2">
                    Besoin d'aide?
                </p>
                <a href="tel:+221XXXXXXXXX" class="text-yellow-600 hover:text-yellow-700 font-medium">
                    <i class="fas fa-phone mr-1"></i>
                    Contactez-nous
                </a>
            </div>
        </div>
    </div>
</body>
</html>
