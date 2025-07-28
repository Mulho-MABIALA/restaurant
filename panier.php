<?php
require_once 'config.php';
session_start(); // Important si ce n'était pas présent !

// Traiter les actions du panier
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'update' && isset($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $id => $qty) {
            if ($qty > 0) {
                $_SESSION['panier'][$id] = (int)$qty;
            } else {
                unset($_SESSION['panier'][$id]);
            }
        }
    } elseif ($_POST['action'] == 'remove' && isset($_POST['id'])) {
        unset($_SESSION['panier'][$_POST['id']]);
    }
    header('Location: panier.php');
    exit;
}

// Récupérer les plats du panier
$plats_panier = [];
$total = 0;

if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    $ids = array_keys($_SESSION['panier']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("SELECT * FROM plats WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    while ($plat = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $plat['quantite'] = $_SESSION['panier'][$plat['id']];
        $plat['sous_total'] = $plat['prix'] * $plat['quantite'];
        $total += $plat['sous_total'];
        $plats_panier[] = $plat;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre Panier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1e40af',
                        accent: '#10b981',
                        danger: '#ef4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v5a2 2 0 01-2 2H9a2 2 0 01-2-2v-5m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                    </svg>
                    Votre Panier
                </h1>
                <a href="index.php" class="text-primary hover:text-secondary transition-colors duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Continuer mes achats
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (empty($plats_panier)): ?>
            <!-- Panier vide -->
            <div class="bg-white rounded-2xl shadow-lg p-8 sm:p-12 text-center">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v5a2 2 0 01-2 2H9a2 2 0 01-2-2v-5m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Votre panier est vide</h3>
                <p class="text-gray-600 mb-8">Découvrez nos délicieux plats et ajoutez-les à votre panier</p>
                <a href="index.php" class="inline-flex items-center px-6 py-3 bg-primary text-white font-medium rounded-xl hover:bg-secondary transform hover:scale-105 transition-all duration-200 shadow-md">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Voir les plats
                </a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Liste des produits -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-primary to-secondary p-6">
                                <h2 class="text-xl font-semibold text-white flex items-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    Mes articles (<?= count($plats_panier) ?>)
                                </h2>
                            </div>
                            
                            <div class="p-6 space-y-4">
                                <?php foreach ($plats_panier as $index => $plat): ?>
                                    <div class="flex flex-col sm:flex-row gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors duration-200 <?= $index < count($plats_panier) - 1 ? 'border-b border-gray-200' : '' ?>">
                                        <!-- Image du produit -->
                                        <div class="w-full sm:w-28 h-32 sm:h-28 flex-shrink-0">
                                            <?php if (!empty($plat['image'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                     class="w-full h-full object-cover rounded-lg shadow-md" 
                                                     alt="<?= htmlspecialchars($plat['nom']) ?>">
                                            <?php else: ?>
                                                <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 rounded-lg flex items-center justify-center shadow-md">
                                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Informations du produit -->
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-lg font-bold text-gray-900 mb-1 truncate"><?= htmlspecialchars($plat['nom']) ?></h3>
                                            <p class="text-primary font-semibold text-lg mb-3"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</p>
                                            
                                            <!-- Contrôles de quantité -->
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                                <div class="flex items-center gap-2">
                                                    <label class="text-sm font-medium text-gray-700">Quantité:</label>
                                                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
                                                        <input type="number" 
                                                               name="quantities[<?= $plat['id'] ?>]" 
                                                               value="<?= $plat['quantite'] ?>" 
                                                               min="1" 
                                                               class="w-16 px-3 py-2 text-center border-0 focus:outline-none focus:ring-2 focus:ring-primary">
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" 
                                                        name="action" 
                                                        value="remove" 
                                                        onclick="document.getElementById('remove_id').value=<?= $plat['id'] ?>" 
                                                        class="inline-flex items-center px-3 py-2 text-sm text-danger hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Retirer
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Sous-total -->
                                        <div class="text-right sm:text-right">
                                            <p class="text-sm text-gray-500 mb-1">Sous-total</p>
                                            <p class="text-xl font-bold text-gray-900"><?= number_format($plat['sous_total'], 0, ',', ' ') ?> FCFA</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Actions du panier -->
                            <div class="bg-gray-50 p-6 border-t">
                                <input type="hidden" name="id" id="remove_id">
                                <button type="submit" 
                                        name="action" 
                                        value="update" 
                                        class="inline-flex items-center px-6 py-3 bg-accent text-white font-medium rounded-xl hover:bg-green-600 transform hover:scale-105 transition-all duration-200 shadow-md">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Mettre à jour le panier
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé de la commande -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl shadow-lg overflow-hidden sticky top-8">
                            <div class="bg-gradient-to-r from-secondary to-primary p-6">
                                <h4 class="text-xl font-semibold text-white flex items-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Résumé de la commande
                                </h4>
                            </div>
                            
                            <div class="p-6">
                                <div class="space-y-4 mb-6">
                                    <div class="flex justify-between text-gray-600">
                                        <span>Articles (<?= count($plats_panier) ?>)</span>
                                        <span><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Livraison</span>
                                        <span class="text-accent font-medium">Gratuite</span>
                                    </div>
                                    <hr class="border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-semibold text-gray-900">Total</span>
                                        <span class="text-2xl font-bold text-primary"><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
                                    </div>
                                </div>
                                
                                <a href="commander.php" 
                                   class="block w-full text-center bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg">
                                    <span class="flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                        </svg>
                                        Passer la commande
                                    </span>
                                </a>
                                
                                <div class="mt-4 text-xs text-gray-500 text-center">
                                    <p class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-1 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Paiement sécurisé
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
</body>
</html>
