<?php
// admin_commande_details.php
session_start();
require_once '../config.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: admin.php');
    exit;
}

$commande_id = $_GET['id'];

// Récupérer la commande principale
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: admin.php');
    exit;
}

// Récupérer les détails de la commande
$stmt = $conn->prepare("SELECT * FROM commande_details WHERE commande_id = ?");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll();

// Calculer le total
$total = 0;
foreach ($details as $detail) {
    $total += $detail['prix'] * $detail['quantite'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la commande #<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .status-badge { 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Barre de navigation admin -->
    <nav class="bg-white shadow-md py-4 px-6 flex justify-between items-center">
        <div class="flex items-center space-x-2">
            <i class="fas fa-utensils text-primary text-xl"></i>
            <span class="font-bold text-xl text-gray-800">Admin Dashboard</span>
        </div>
        <a href="admin_logout.php" class="text-gray-600 hover:text-primary transition">
            <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
        </a>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Commande #<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-gray-600 mt-1">Détails de la commande</p>
            </div>
            <a href="admin.php" class="inline-flex items-center text-primary hover:text-primary-dark transition">
                <i class="fas fa-arrow-left mr-2"></i> Retour aux commandes
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Carte Informations client -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                    <i class="fas fa-user-circle mr-2 text-primary"></i>Informations client
                </h2>
                <div class="space-y-4">
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Nom</div>
                        <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['nom_client']) ?></div>
                    </div>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Email</div>
                        <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['email']) ?></div>
                    </div>
                    <?php if (!empty($commande['telephone'])): ?>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Téléphone</div>
                        <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['telephone']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Adresse</div>
                        <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['adresse']) ?></div>
                    </div>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Mode de retrait</div>
                        <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['mode_retrait']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Carte Résumé commande -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                    <i class="fas fa-receipt mr-2 text-primary"></i>Résumé de la commande
                </h2>
                <div class="space-y-4">
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Date</div>
                        <div class="w-2/3 font-medium"><?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></div>
                    </div>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Statut</div>
                        <div class="w-2/3">
                            <?php 
                                $statusColor = 'bg-gray-100 text-gray-800';
                                if ($commande['statut'] == 'En cours') $statusColor = 'bg-yellow-100 text-yellow-800';
                                if ($commande['statut'] == 'Prête') $statusColor = 'bg-blue-100 text-blue-800';
                                if ($commande['statut'] == 'Livrée') $statusColor = 'bg-green-100 text-green-800';
                                if ($commande['statut'] == 'Annulée') $statusColor = 'bg-red-100 text-red-800';
                            ?>
                            <span class="status-badge <?= $statusColor ?>">
                                <?= $commande['statut'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-1/3 text-gray-500">Total</div>
                        <div class="w-2/3 font-bold text-lg text-primary"><?= number_format($commande['total'], 0) ?> fcfa</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Articles commandés -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-list-alt mr-2 text-primary"></i>Articles commandés
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($details as $detail): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($detail['nom_plat']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-500">
                                <?= $detail['quantite'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-500">
                                <?= number_format($detail['prix'], 0) ?> fcfa
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                <?= number_format($detail['prix'] * $detail['quantite'], 0) ?> fcfa
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right text-gray-900">Total général</td>
                            <td class="px-6 py-4 text-right text-primary text-lg"><?= number_format($total, 0) ?> fcfa</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex flex-wrap justify-end gap-3">
            <a href="admin.php" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition shadow-sm">
                <i class="fas fa-chevron-left mr-2"></i> Retour
            </a>
            <a href="?supprimer=<?= $commande_id ?>" 
               class="px-5 py-2.5 bg-red-600 hover:bg-red-700 rounded-lg text-white transition shadow-sm"
               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?');">
                <i class="fas fa-trash-alt mr-2"></i> Supprimer
            </a>
        </div>
    </div>

    <footer class="mt-12 py-6 text-center text-gray-500 text-sm border-t">
        © <?= date('Y') ?> Restaurant Name. Tous droits réservés.
    </footer>
</body>
</html>