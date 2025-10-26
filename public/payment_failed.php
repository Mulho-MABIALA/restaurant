<?php
/**
 * Payment Failed Page
 *
 * Page affichée quand le paiement a échoué
 */

require_once __DIR__ . '/../config.php';
session_start();

$reason = $_GET['reason'] ?? 'unknown';
$orderId = $_GET['order_id'] ?? '';
$error = $_GET['error'] ?? '';

// Messages d'erreur traduits
$errorMessages = [
    'failed' => 'Le paiement a échoué. Veuillez réessayer.',
    'pending' => 'Le paiement est en attente. Veuillez patienter.',
    'expired' => 'Le lien de paiement a expiré.',
    'insufficient_funds' => 'Fonds insuffisants.',
    'unknown' => 'Une erreur s\'est produite lors du paiement.'
];

$displayMessage = $error ?: ($errorMessages[$reason] ?? $errorMessages['unknown']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement échoué - <?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <!-- Icône -->
            <div class="mb-6">
                <div class="mx-auto w-20 h-20 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-4xl"></i>
                </div>
            </div>

            <!-- Message -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Paiement échoué
            </h1>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800">
                    <?= htmlspecialchars($displayMessage) ?>
                </p>
            </div>

            <?php if ($reason === 'pending'): ?>
            <p class="text-gray-600 mb-6 text-sm">
                Si vous avez déjà effectué le paiement, veuillez patienter quelques instants.
                Vous recevrez une confirmation par email une fois le paiement validé.
            </p>
            <?php endif; ?>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="commander.php" class="block w-full bg-yellow-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-yellow-700 transition">
                    <i class="fas fa-redo mr-2"></i>
                    Réessayer le paiement
                </a>

                <?php if ($orderId): ?>
                <a href="commander.php?retry_order=<?= $orderId ?>" class="block w-full bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-300 transition">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Utiliser une autre méthode de paiement
                </a>
                <?php endif; ?>

                <a href="menu.php" class="block w-full text-gray-600 py-2 hover:text-gray-900 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour au menu
                </a>
            </div>

            <!-- Conseils -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-3">Conseils:</h3>
                <ul class="text-sm text-gray-600 text-left space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>Vérifiez que vous avez suffisamment de solde</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>Assurez-vous que votre numéro est correct</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>Essayez une autre méthode de paiement</span>
                    </li>
                </ul>
            </div>

            <!-- Support -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-500 mb-2">
                    Problème persistant?
                </p>
                <a href="tel:+221XXXXXXXXX" class="text-yellow-600 hover:text-yellow-700 font-medium">
                    <i class="fas fa-phone mr-1"></i>
                    Contactez notre support
                </a>
            </div>
        </div>
    </div>
</body>
</html>
