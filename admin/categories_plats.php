<?php
require_once '../config.php'; // Connexion via PDO depuis config.php

// Connexion avec gestion d'erreur PDO (au cas où config.php n'initialise pas `$conn`)
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de configuration PDO : " . $e->getMessage());
}

// Initialisation du message
$message = null;

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_categorie'])) {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($nom)) {
        try {
            // Vérifier si la catégorie existe déjà
            $stmt = $conn->prepare("SELECT id FROM categories WHERE nom = ?");
            $stmt->execute([$nom]);

            if ($stmt->rowCount() === 0) {
                $insert = $conn->prepare("INSERT INTO categories (nom, description) VALUES (?, ?)");
                $insert->execute([$nom, $description]);
                $message = ['type' => 'success', 'text' => 'Catégorie ajoutée avec succès'];
            } else {
                $message = ['type' => 'error', 'text' => 'Cette catégorie existe déjà'];
            }
        } catch (PDOException $e) {
            $message = ['type' => 'error', 'text' => 'Erreur technique : ' . $e->getMessage()];
        }
    } else {
        $message = ['type' => 'error', 'text' => 'Le nom de la catégorie est obligatoire'];
    }
}

// Traitement de la suppression
if (isset($_GET['supprimer'])) {
    $id = (int) $_GET['supprimer'];

    try {
        // Vérifier si des plats utilisent cette catégorie
        $stmt = $conn->prepare("SELECT COUNT(*) FROM plats WHERE categorie_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            $message = ['type' => 'success', 'text' => 'Catégorie supprimée avec succès'];
        } else {
            $message = ['type' => 'error', 'text' => 'Impossible de supprimer : catégorie utilisée par des plats'];
        }
    } catch (PDOException $e) {
        $message = ['type' => 'error', 'text' => 'Erreur lors de la suppression : ' . $e->getMessage()];
    }

    // Redirection pour éviter le renvoi du formulaire
    header("Location: categories_plats.php?message=" . urlencode($message['type'] . ':' . $message['text']));
    exit;
}

// Récupération des catégories
try {
    $categories = $conn->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    $message = ['type' => 'error', 'text' => 'Erreur lors de la récupération des catégories'];
}

// Gestion du message passé en GET
if (isset($_GET['message']) && !$message) {
    list($type, $text) = explode(':', $_GET['message'], 2);
    $message = ['type' => $type, 'text' => $text];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .animate-slide-up {
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        
        .animate-scale-in {
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .glass-effect {
            backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(209, 213, 219, 0.3);
        }
        
        .gradient-border {
            background: linear-gradient(white, white), linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: padding-box, border-box;
            background-origin: padding-box, border-box;
            border: 2px solid transparent;
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .category-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            border-left-color: #3b82f6;
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            transform: translateX(4px);
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <main class="p-4 md:p-6 lg:p-8">
                <!-- En-tête amélioré -->
                <div class="relative mb-8">
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl opacity-90"></div>
                    <div class="relative glass-effect rounded-2xl p-6 md:p-8 text-white overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-10 rounded-full -translate-y-32 translate-x-32"></div>
                        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white opacity-10 rounded-full translate-y-24 -translate-x-24"></div>
                        
                        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                            <div class="space-y-2">
                                <h1 class="text-3xl md:text-4xl font-bold flex items-center animate-slide-up">
                                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-tags text-xl"></i>
                                    </div>
                                    Gestion des Catégories
                                </h1>
                                <p class="text-white/90 text-lg animate-fade-in">Organisez et gérez vos catégories de plats avec style</p>
                            </div>
                            
                            <div class="flex space-x-4">
                                <div class="bg-white/10 backdrop-blur-sm px-6 py-3 rounded-xl border border-white/20 animate-scale-in">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold"><?= count($categories) ?></div>
                                        <div class="text-sm text-white/80">Catégories</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages améliorés -->
                <?php if (!empty($message)): ?>
                    <div class="mb-6 animate-slide-up">
                        <div class="<?= $message['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800' ?> border-l-4 <?= $message['type'] === 'success' ? 'border-l-emerald-500' : 'border-l-red-500' ?> p-4 rounded-r-lg shadow-sm">
                            <div class="flex items-center">
                                <i class="fas <?= $message['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-triangle text-red-500' ?> mr-3"></i>
                                <span class="font-medium"><?= $message['text'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 md:gap-8">
                    <!-- Formulaire d'ajout amélioré -->
                    <div class="xl:col-span-1">
                        <div class="bg-white rounded-2xl shadow-xl hover-lift border border-gray-100">
                            <div class="p-6 md:p-8">
                                <div class="flex items-center mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl flex items-center justify-center mr-3">
                                        <i class="fas fa-plus text-white"></i>
                                    </div>
                                    <h2 class="text-xl md:text-2xl font-bold text-gray-800">Nouvelle Catégorie</h2>
                                </div>
                                
                                <form method="POST" class="space-y-6">
                                    <div class="space-y-2">
                                        <label for="nom" class="block text-sm font-semibold text-gray-700">Nom de la catégorie</label>
                                        <input type="text" id="nom" name="nom" required
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:bg-white"
                                            placeholder="Ex: Entrées, Plats principaux...">
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label for="description" class="block text-sm font-semibold text-gray-700">Description (optionnelle)</label>
                                        <textarea id="description" name="description" rows="3"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:bg-white resize-none"
                                            placeholder="Décrivez cette catégorie..."></textarea>
                                    </div>
                                    
                                    <button type="submit" name="ajouter_categorie"
                                        class="w-full btn-gradient text-white px-6 py-3 rounded-xl font-semibold flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Créer la catégorie</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des catégories en cartes -->
                    <div class="xl:col-span-2">
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100">
                            <div class="p-6 md:p-8">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl flex items-center justify-center mr-3">
                                            <i class="fas fa-list text-white"></i>
                                        </div>
                                        <h2 class="text-xl md:text-2xl font-bold text-gray-800">Mes Catégories</h2>
                                    </div>
                                    
                                    <div class="hidden md:flex items-center space-x-4">
                                        <div class="text-sm text-gray-500">
                                            <?= count($categories) ?> catégorie<?= count($categories) > 1 ? 's' : '' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (empty($categories)): ?>
                                    <div class="text-center py-12">
                                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <i class="fas fa-inbox text-3xl text-gray-400"></i>
                                        </div>
                                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucune catégorie</h3>
                                        <p class="text-gray-500">Commencez par créer votre première catégorie de plats</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Vue mobile : cartes empilées -->
                                    <div class="md:hidden space-y-4">
                                        <?php foreach ($categories as $index => $categorie): ?>
                                            <div class="category-card rounded-xl p-4 animate-slide-up" style="animation-delay: <?= $index * 0.1 ?>s;">
                                                <div class="flex justify-between items-start mb-3">
                                                    <div class="flex-1 min-w-0">
                                                        <h3 class="font-bold text-gray-800 truncate"><?= htmlspecialchars($categorie['nom'] ?? '') ?></h3>
                                                        <p class="text-xs text-gray-500 mt-1">#<?= htmlspecialchars($categorie['id']) ?></p>
                                                    </div>
                                                    <div class="flex space-x-2 ml-3">
                                                        <a href="modifier_categorie.php?id=<?= urlencode($categorie['id']) ?>" 
                                                           class="w-8 h-8 bg-blue-100 hover:bg-blue-200 rounded-lg flex items-center justify-center transition-colors">
                                                            <i class="fas fa-edit text-blue-600 text-sm"></i>
                                                        </a>
                                                        <a href="?supprimer=<?= urlencode($categorie['id']) ?>" 
                                                           onclick="return confirm('Êtes-vous sûr ? Cette action est irréversible.');"
                                                           class="w-8 h-8 bg-red-100 hover:bg-red-200 rounded-lg flex items-center justify-center transition-colors">
                                                            <i class="fas fa-trash text-red-600 text-sm"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                                <?php if (!empty($categorie['description'])): ?>
                                                    <p class="text-sm text-gray-600 leading-relaxed"><?= htmlspecialchars($categorie['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Vue desktop/tablette : tableau moderne -->
                                    <div class="hidden md:block overflow-hidden rounded-xl border border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Catégorie</th>
                                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Description</th>
                                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-100">
                                                <?php foreach ($categories as $index => $categorie): ?>
                                                    <tr class="hover:bg-gray-50 transition-colors duration-200 animate-fade-in" style="animation-delay: <?= $index * 0.05 ?>s;">
                                                        <td class="px-6 py-4">
                                                            <div class="flex items-center">
                                                                <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center mr-3">
                                                                    <span class="text-white font-bold text-sm"><?= strtoupper(substr($categorie['nom'], 0, 1)) ?></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($categorie['nom'] ?? '') ?></div>
                                                                    <div class="text-sm text-gray-500">#<?= htmlspecialchars($categorie['id']) ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 max-w-xs">
                                                            <p class="text-gray-700 truncate">
                                                                <?= !empty($categorie['description']) ? htmlspecialchars($categorie['description']) : '<span class="text-gray-400 italic">Aucune description</span>' ?>
                                                            </p>
                                                        </td>
                                                        <td class="px-6 py-4 text-center">
                                                            <div class="flex justify-center space-x-3">
                                                                <a href="modifier_categorie.php?id=<?= urlencode($categorie['id']) ?>" 
                                                                   class="w-9 h-9 bg-blue-100 hover:bg-blue-200 rounded-xl flex items-center justify-center transition-all duration-200 hover:scale-110">
                                                                    <i class="fas fa-edit text-blue-600"></i>
                                                                </a>
                                                                <a href="?supprimer=<?= urlencode($categorie['id']) ?>" 
                                                                   onclick="return confirm('Êtes-vous sûr ? Cette action est irréversible.');"
                                                                   class="w-9 h-9 bg-red-100 hover:bg-red-200 rounded-xl flex items-center justify-center transition-all duration-200 hover:scale-110">
                                                                    <i class="fas fa-trash text-red-600"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Animation des éléments au chargement
        document.addEventListener('DOMContentLoaded', () => {
            // Animer les cartes avec délai progressif
            const animatedElements = document.querySelectorAll('.animate-slide-up, .animate-fade-in, .animate-scale-in');
            animatedElements.forEach((element, index) => {
                element.style.opacity = '0';
                setTimeout(() => {
                    element.style.opacity = '1';
                }, index * 100);
            });
            
            // Effet de survol sur les cartes de catégories
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateX(8px) scale(1.02)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateX(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html>