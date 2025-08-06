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
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.5s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideIn: {
                            '0%': { transform: 'translateY(-20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @media (max-width: 640px) {
            .cart-item {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            }
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .shadow-luxury {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">
    <!-- Header amélioré -->
    <header class="glass-effect shadow-luxury border-b border-white/20 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gradient flex items-center animate-slide-in">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 mr-3 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v5a2 2 0 01-2 2H9a2 2 0 01-2-2v-5m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                        </svg>
                    </div>
                    <span class="hidden sm:inline">Votre</span> Panier
                </h1>
                
                <div class="flex items-center space-x-3">
                    <!-- Bouton info -->
                    <button onclick="toggleInfoModal()" 
                            class="p-2 sm:p-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-full hover:from-indigo-600 hover:to-purple-700 transform hover:scale-110 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>
                    
                    <a href="index.php" class="hidden sm:flex text-primary hover:text-secondary transition-colors duration-200 items-center bg-white/80 px-4 py-2 rounded-full shadow-md hover:shadow-lg transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Continuer mes achats
                    </a>
                    
                    <!-- Version mobile du lien retour -->
                    <a href="index.php" class="sm:hidden p-2 text-primary hover:text-secondary transition-colors duration-200 bg-white/80 rounded-full shadow-md">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <?php if (empty($plats_panier)): ?>
            <!-- Panier vide amélioré -->
            <div class="glass-effect rounded-3xl shadow-luxury p-8 sm:p-12 text-center animate-bounce-in">
                <div class="mx-auto w-24 h-24 sm:w-32 sm:h-32 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mb-6 shadow-inner">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v5a2 2 0 01-2 2H9a2 2 0 01-2-2v-5m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Votre panier est vide</h3>
                <p class="text-gray-600 mb-8 text-base sm:text-lg">Découvrez nos délicieux plats et ajoutez-les à votre panier</p>
                <a href="index.php" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-primary to-secondary text-white font-bold rounded-2xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Voir les plats
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="animate-fade-in">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
                    <!-- Liste des produits améliorée -->
                    <div class="xl:col-span-2 space-y-6">
                        <div class="glass-effect rounded-3xl shadow-luxury overflow-hidden">
                            <div class="bg-gradient-to-r from-primary via-blue-500 to-secondary p-6">
                                <h2 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                                    <div class="w-8 h-8 mr-3 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <span class="hidden sm:inline">Mes articles</span>
                                    <span class="sm:hidden">Articles</span>
                                    <span class="ml-2 bg-white/20 px-3 py-1 rounded-full text-sm"><?= count($plats_panier) ?></span>
                                </h2>
                            </div>
                            
                            <div class="p-4 sm:p-6 space-y-4">
                                <?php foreach ($plats_panier as $index => $plat): ?>
                                    <div class="cart-item flex flex-col sm:flex-row gap-4 p-4 sm:p-6 bg-gradient-to-r from-white to-gray-50 rounded-2xl hover:from-blue-50 hover:to-indigo-50 transition-all duration-300 shadow-md hover:shadow-luxury transform hover:scale-[1.02] <?= $index < count($plats_panier) - 1 ? 'border-b border-gray-100' : '' ?>">
                                        <!-- Image du produit -->
                                        <div class="w-full sm:w-32 h-40 sm:h-32 flex-shrink-0">
                                            <?php if (!empty($plat['image'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                     class="w-full h-full object-cover rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300" 
                                                     alt="<?= htmlspecialchars($plat['nom']) ?>">
                                            <?php else: ?>
                                                <div class="w-full h-full bg-gradient-to-br from-gray-200 via-gray-300 to-gray-400 rounded-xl flex items-center justify-center shadow-lg">
                                                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Informations du produit -->
                                        <div class="flex-1 min-w-0 space-y-3">
                                            <div>
                                                <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 line-clamp-2"><?= htmlspecialchars($plat['nom']) ?></h3>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-primary font-bold text-xl sm:text-2xl"><?= number_format($plat['prix'], 0, ',', ' ') ?></span>
                                                    <span class="text-primary font-semibold">FCFA</span>
                                                    <span class="text-sm text-gray-500">l'unité</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Contrôles de quantité améliorés -->
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                                <div class="flex items-center gap-3">
                                                    <label class="text-sm font-semibold text-gray-700 whitespace-nowrap">Quantité:</label>
                                                    <div class="flex items-center bg-white border-2 border-gray-200 rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-shadow duration-200">
                                                        <input type="number" 
                                                               name="quantities[<?= $plat['id'] ?>]" 
                                                               value="<?= $plat['quantite'] ?>" 
                                                               min="1" 
                                                               class="w-20 px-4 py-3 text-center border-0 focus:outline-none focus:ring-2 focus:ring-primary rounded-xl font-semibold text-gray-800 bg-gradient-to-r from-gray-50 to-white">
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" 
                                                        name="action" 
                                                        value="remove" 
                                                        onclick="document.getElementById('remove_id').value=<?= $plat['id'] ?>" 
                                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-danger hover:text-white hover:bg-danger/90 bg-red-50 hover:bg-red-500 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Retirer
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Sous-total amélioré -->
                                        <div class="text-center sm:text-right sm:min-w-[120px] bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-4 shadow-inner">
                                            <p class="text-sm text-gray-600 mb-1 font-medium">Sous-total</p>
                                            <p class="text-xl sm:text-2xl font-bold text-primary"><?= number_format($plat['sous_total'], 0, ',', ' ') ?></p>
                                            <p class="text-sm text-primary font-semibold">FCFA</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Actions du panier améliorées -->
                            <div class="bg-gradient-to-r from-gray-50 to-blue-50 p-6 border-t">
                                <input type="hidden" name="id" id="remove_id">
                                <button type="submit" 
                                        name="action" 
                                        value="update" 
                                        class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-accent to-green-600 text-white font-bold rounded-2xl hover:from-green-600 hover:to-accent transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Mettre à jour le panier
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé de la commande amélioré -->
                    <div class="xl:col-span-1">
                        <div class="glass-effect rounded-3xl shadow-luxury overflow-hidden sticky top-24">
                            <div class="bg-gradient-to-r from-secondary via-indigo-600 to-primary p-6">
                                <h4 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                                    <div class="w-8 h-8 mr-3 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <span class="hidden sm:inline">Résumé</span> Commande
                                </h4>
                            </div>
                            
                            <div class="p-6 space-y-6">
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                                        <span class="text-gray-700 font-medium">Articles (<?= count($plats_panier) ?>)</span>
                                        <span class="font-bold text-gray-900"><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
                                    </div>
                                    <div class="flex justify-between items-center p-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                                        <span class="text-gray-700 font-medium">Livraison</span>
                                        <span class="text-accent font-bold flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Gratuite
                                        </span>
                                    </div>
                                    <div class="h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
                                    <div class="flex justify-between items-center p-4 bg-gradient-to-r from-primary/10 to-secondary/10 rounded-xl border-2 border-primary/20">
                                        <span class="text-xl font-bold text-gray-900">Total</span>
                                        <div class="text-right">
                                            <span class="text-3xl font-bold text-gradient"><?= number_format($total, 0, ',', ' ') ?></span>
                                            <span class="text-primary font-bold ml-1">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <a href="commander.php" 
                                   class="block w-full text-center bg-gradient-to-r from-primary via-blue-500 to-secondary text-white font-bold py-5 px-6 rounded-2xl hover:from-secondary hover:via-indigo-600 hover:to-primary transform hover:scale-105 transition-all duration-300 shadow-luxury hover:shadow-2xl">
                                    <span class="flex items-center justify-center text-lg">
                                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                        </svg>
                                        Passer la commande
                                    </span>
                                </a>
                                
                                <div class="text-center space-y-2">
                                    <p class="flex items-center justify-center text-sm text-gray-600">
                                        <svg class="w-4 h-4 mr-2 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Paiement 100% sécurisé
                                    </p>
                                    <p class="flex items-center justify-center text-sm text-gray-600">
                                        <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Livraison rapide garantie
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Modal d'informations -->
    <div id="infoModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="glass-effect rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-bounce-in">
            <!-- Header du modal -->
            <div class="bg-gradient-to-r from-indigo-500 via-purple-600 to-pink-500 p-6 rounded-t-3xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <div class="w-8 h-8 mr-3 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        Informations sur votre commande
                    </h3>
                    <button onclick="toggleInfoModal()" class="text-white/80 hover:text-white transition-colors duration-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Contenu du modal -->
            <div class="p-6 space-y-6">
                <!-- Informations de livraison -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h3z"></path>
                        </svg>
                        Livraison
                    </h4>
                    <div class="space-y-3 text-sm text-gray-700">
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <strong>Livraison gratuite</strong> pour toutes les commandes
                        </p>
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <strong>Délai :</strong> 30-45 minutes après confirmation
                        </p>
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <strong>Zone de livraison :</strong> Dakar et banlieue
                        </p>
                    </div>
                </div>

                <!-- Informations de paiement -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Moyens de paiement
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-gray-700">
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Paiement à la livraison
                        </p>
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Orange Money
                        </p>
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Wave
                        </p>
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Free Money
                        </p>
                    </div>
                </div>

                <?php if (!empty($plats_panier)): ?>
                <!-- Détails de la commande -->
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Résumé de votre commande
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($plats_panier as $plat): ?>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-700">
                                    <strong><?= htmlspecialchars($plat['nom']) ?></strong>
                                    <span class="text-gray-500 ml-1">(x<?= $plat['quantite'] ?>)</span>
                                </span>
                                <span class="font-semibold text-purple-600"><?= number_format($plat['sous_total'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                        <?php endforeach; ?>
                        <div class="border-t border-purple-200 pt-3 mt-3">
                            <div class="flex justify-between items-center font-bold text-base">
                                <span class="text-gray-900">Total</span>
                                <span class="text-purple-600 text-lg"><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Politique et conditions -->
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Conditions importantes
                    </h4>
                    <div class="space-y-3 text-sm text-gray-700">
                        <p class="flex items-start">
                            <svg class="w-4 h-4 mr-2 mt-0.5 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <strong>Annulation :</strong> Possible jusqu'à 10 minutes après la commande
                        </p>
                        <p class="flex items-start">
                            <svg class="w-4 h-4 mr-2 mt-0.5 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <strong>Modification :</strong> Contactez-nous immédiatement après commande
                        </p>
                        <p class="flex items-start">
                            <svg class="w-4 h-4 mr-2 mt-0.5 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <strong>Support :</strong> +221 77 123 45 67 (disponible 24h/7j)
                        </p>
                    </div>
                </div>

                <!-- Contact et support -->
                <div class="bg-gradient-to-r from-cyan-50 to-blue-50 rounded-2xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Besoin d'aide ?
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <a href="tel:+221771234567" class="flex items-center p-3 bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-200">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Téléphone</p>
                                <p class="text-sm text-gray-600">+221 77 123 45 67</p>
                            </div>
                        </a>
                        <a href="mailto:support@restaurant.com" class="flex items-center p-3 bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-200">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Email</p>
                                <p class="text-sm text-gray-600">support@restaurant.com</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleInfoModal() {
            const modal = document.getElementById('infoModal');
            modal.classList.toggle('hidden');
            modal.classList.toggle('flex');
            
            // Empêcher le scroll de la page quand le modal est ouvert
            document.body.classList.toggle('overflow-hidden');
        }

        // Fermer le modal en cliquant en dehors
        document.getElementById('infoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleInfoModal();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('infoModal');
                if (!modal.classList.contains('hidden')) {
                    toggleInfoModal();
                }
            }
        });

        // Animation de hover sur les cartes produit
        document.querySelectorAll('.cart-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Validation des quantités
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 1) {
                    this.value = 1;
                }
            });
        });

        // Animation au chargement
        window.addEventListener('load', function() {
            document.querySelectorAll('.animate-fade-in, .animate-slide-in, .animate-bounce-in').forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Scroll smooth pour le header sticky
        let lastScrollY = window.scrollY;
        const header = document.querySelector('header');

        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            
            if (currentScrollY > lastScrollY && currentScrollY > 100) {
                header.style.transform = 'translateY(-100%)';
            } else {
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollY = currentScrollY;
        });
    </script>
</body>
</html>