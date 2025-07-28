<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Message par défaut
$message = $_GET['message'] ?? 'Merci pour votre commande !';

// ID de commande
$commande_id = $_GET['commande_id'] ?? $_SESSION['commande_id'] ?? null;
$mode_paiement = $_GET['mode'] ?? 'livraison'; // 'livraison' ou 'en ligne'

if (!$commande_id) {
    die("ID de commande manquant.");
}

// Récupération de la commande et des détails
try {
    $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        die("Commande non trouvée.");
    }

    $stmt = $conn->prepare("SELECT nom_plat, quantite, prix FROM commande_details WHERE commande_id = ?");
    $stmt->execute([$commande_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commande confirmée</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        localStorage.removeItem('cart');
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
                <div class="mb-6">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full">
                        <i class="fas fa-check text-green-500 text-4xl animate-pulse"></i>
                    </div>
                </div>

                <h1 class="text-3xl font-bold text-dark mb-4">Commande confirmée !</h1>
                <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>

                <!-- Résumé commande -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                    <h3 class="font-semibold text-dark mb-4">Détails de la commande</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">N° de commande</span>
                            <span class="font-semibold text-primary">#<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date</span>
                            <span class="font-semibold"><?= date('d/m/Y'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Client</span>
                            <span class="font-semibold"><?= htmlspecialchars($commande['nom_client']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Téléphone</span>
                            <span class="font-semibold"><?= htmlspecialchars($commande['telephone']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email</span>
                            <span class="font-semibold"><?= htmlspecialchars($commande['email']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Adresse</span>
                            <span class="font-semibold"><?= nl2br(htmlspecialchars($commande['adresse'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Mode de paiement</span>
                            <span class="font-semibold text-blue-700">
                                <?= $mode_paiement === 'enligne' ? 'En ligne (PayDunya)' : 'À la livraison' ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total</span>
                            <span class="font-bold text-danger text-lg"><?= number_format($commande['total'], 2); ?> fcfa</span>
                        </div>
                    </div>
                </div>

                <!-- Détails des plats -->
                <?php if (!empty($details)): ?>
                <div class="bg-white rounded-lg p-6 mb-6 text-left border border-gray-200">
                    <h3 class="font-semibold text-dark mb-4">Produits commandés</h3>
                    <table class="w-full text-sm table-auto">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="text-left py-2 px-2">Produit</th>
                                <th class="text-center py-2 px-2">Quantité</th>
                                <th class="text-right py-2 px-2">Prix unitaire</th>
                                <th class="text-right py-2 px-2">Sous-total</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            <?php
                            $totalCommande = 0;
                            foreach ($details as $item):
                                $sousTotal = $item['quantite'] * $item['prix'];
                                $totalCommande += $sousTotal;
                            ?>
                            <tr class="border-b">
                                <td class="py-2 px-2"><?= htmlspecialchars($item['nom_plat']) ?></td>
                                <td class="text-center py-2 px-2"><?= (int)$item['quantite'] ?></td>
                                <td class="text-right py-2 px-2"><?= number_format($item['prix'], 2) ?> fcfa</td>
                                <td class="text-right py-2 px-2 font-semibold"><?= number_format($sousTotal, 2) ?> fcfa</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right font-bold py-2 px-2">Total</td>
                                <td class="text-right font-bold text-red-600 py-2 px-2"><?= number_format($totalCommande, 2) ?> fcfa</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Prochaines étapes -->
                <div class="bg-blue-50 rounded-lg p-4 mb-6 text-left">
                    <h4 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-2"></i> Prochaines étapes
                    </h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Un e-mail de confirmation vous a été envoyé</li>
                        <li>• Préparation de votre commande</li>
                        <li>• Livraison dans les délais annoncés</li>
                        <?php if ($mode_paiement === 'livraison'): ?>
                            <li>• Paiement à la livraison</li>
                        <?php else: ?>
                            <li>• Paiement en ligne validé avec succès</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <button onclick="window.print()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-semibold mb-4">
                    <i class="fas fa-print mr-2"></i> Imprimer
                </button>

                <a href="index.php" class="btn btn-primary w-full">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</body>
</html>
