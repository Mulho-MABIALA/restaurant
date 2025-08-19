<?php
require_once '../config.php';

$categorieId = isset($plat['categorie_id']) ? $plat['categorie_id'] : null;
$nomCategorie = isset($categories[$categorieId]) ? $categories[$categorieId] : 'Non catégorisé';

try {
    // Récupération des catégories
    $categories = $conn->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

    // Filtrage sécurisé
    $filtreCategorie = isset($_GET['categorie']) ? (int)$_GET['categorie'] : null;
    $idsCategorie = array_column($categories, 'id');
    $hasFilter = $filtreCategorie && in_array($filtreCategorie, $idsCategorie);

    $query = "SELECT p.id, p.nom, p.description, p.prix, p.image, c.nom AS categorie_nom 
              FROM plats p
              LEFT JOIN categories c ON p.categorie_id = c.id";
    if ($hasFilter) {
        $query .= " WHERE p.categorie_id = :categorie_id";
    }

    $query .= " ORDER BY p.nom ASC";

    // Préparation
    $stmt = $conn->prepare($query);

    // Exécution
    if ($hasFilter) {
        $stmt->execute(['categorie_id' => $filtreCategorie]);
    } else {
        $stmt->execute();
    }

    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $totalCategories = count($categories);
    $totalPlats = count($plats);
    $platCountByCategory = array_reduce($plats, function($acc, $plat) {
        $acc[$plat['categorie_nom']] = ($acc[$plat['categorie_nom']] ?? 0) + 1;
        return $acc;
    }, []);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion des Plats</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-in': 'bounceIn 0.8s ease-out',
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        bounceIn: {
                            '0%': { opacity: '0', transform: 'scale(0.3)' },
                            '50%': { opacity: '1', transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { opacity: '1', transform: 'scale(1)' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-8px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(229, 231, 235, 0.4);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-accent, #3b82f6);
            border-radius: 16px 16px 0 0;
        }
        
        .dashboard-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .card-purple { --card-accent: #8b5cf6; }
        .card-red { --card-accent: #ef4444; }
        .card-blue { --card-accent: #3b82f6; }
        .card-green { --card-accent: #10b981; }
        .card-orange { --card-accent: #f59e0b; }
        .card-cyan { --card-accent: #06b6d4; }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .btn-view {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-color: rgba(16, 185, 129, 0.2);
        }
        
        .btn-view:hover {
            background: rgba(16, 185, 129, 0.2);
        }
        
        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            border-color: rgba(59, 130, 246, 0.2);
        }
        
        .btn-edit:hover {
            background: rgba(59, 130, 246, 0.2);
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.2);
        }
        
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .icon-purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .icon-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .icon-cyan { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
        
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .modal-backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .modal-exit {
            animation: modalSlideOut 0.2s ease-in forwards;
        }
        
        @keyframes modalSlideOut {
            from {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
            to {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
        }
        
        .table-modern tr {
            transition: all 0.2s ease;
        }
        
        .table-modern tr:hover {
            background: rgba(249, 250, 251, 0.8);
        }
    </style>
</head>

<body class="bg-gray-50 font-inter">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <div class="p-6">
                <!-- Header avec design moderne -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="bg-blue-100 p-3 rounded-xl mr-4">
                            <i class="fas fa-utensils text-2xl text-blue-600"></i>
                        </div>
                        <div>
                            <h1 class="text-4xl font-bold mb-2 text-gray-900">Gestion des Plats</h1>
                            <p class="text-gray-600 text-lg font-medium">Interface d'administration avancée</p>
                        </div>
                    </div>
                </div>

                <!-- Cartes statistiques modernes -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total des plats -->
                    <div class="dashboard-card card-purple animate-fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Total des plats</p>
                                <p class="text-3xl font-bold text-gray-900"><?= count($plats) ?></p>
                                <p class="text-sm text-green-600 flex items-center mt-2">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +12% ce mois
                                </p>
                            </div>
                            <div class="icon-wrapper icon-purple">
                                <i class="fas fa-utensils"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Catégories -->
                    <div class="dashboard-card card-blue animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Catégories</p>
                                <p class="text-3xl font-bold text-gray-900"><?= $totalCategories ?></p>
                                <p class="text-sm text-blue-600 flex items-center mt-2">
                                    <i class="fas fa-equals mr-1"></i>
                                    Stable
                                </p>
                            </div>
                            <div class="icon-wrapper icon-blue">
                                <i class="fas fa-tags"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statut système -->
                    <div class="dashboard-card card-green animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Statut système</p>
                                <p class="text-xl font-bold text-green-600">En ligne</p>
                                <p class="text-sm text-gray-600 flex items-center mt-2">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                    Opérationnel
                                </p>
                            </div>
                            <div class="icon-wrapper icon-green">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section filtres et actions -->
                <div class="bg-white rounded-2xl p-6 mb-8 shadow-sm border border-gray-200">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                        <!-- Filtres -->
                        <form method="get" class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full lg:w-auto">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-100 p-2 rounded-lg">
                                    <i class="fas fa-filter text-blue-600"></i>
                                </div>
                                <label for="categorie" class="font-semibold text-gray-700">Filtrer par catégorie</label>
                            </div>
                            
                            <div class="flex gap-3 w-full sm:w-auto">
                                <select name="categorie" id="categorie" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-48">
                                    <option value="">🍽️ Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $filtreCategorie) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                    <i class="fas fa-search mr-2"></i>Filtrer
                                </button>
                            </div>
                            
                            <?php if($filtreCategorie): ?>
                                <a href="gestion_plats.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors flex items-center gap-2 font-medium">
                                    <i class="fas fa-times"></i>Réinitialiser
                                </a>
                            <?php endif; ?>
                        </form>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <form method="post" action="export_plats_pdf.php" class="inline w-full sm:w-auto">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors w-full sm:w-auto">
                                    <i class="fas fa-file-pdf mr-2"></i>Exporter PDF
                                </button>
                            </form>
                            
                            <a href="ajouter_plat.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center w-full sm:w-auto">
                                <i class="fas fa-plus mr-2"></i>Ajouter un plat
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tableau moderne -->
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-table mr-3 text-gray-600"></i>
                            Liste des plats
                            <?php if($filtreCategorie): ?>
                                <span class="ml-3 bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    Filtré
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-modern">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Nom du plat
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Prix
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">
                                        Catégorie
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">
                                        Image
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                        
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (!empty($plats)): ?>
                                    <?php foreach ($plats as $index => $plat): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-gray-900 font-medium"><?= $plat['id'] ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900">
                                                <?= htmlspecialchars($plat['nom']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-gray-600 text-sm max-w-xs truncate">
                                                <?= htmlspecialchars($plat['description']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="font-semibold text-gray-900">
                                                <?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                            <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?= htmlspecialchars($plat['categorie_nom'] ?? 'Non catégorisé') ?>
                                            </span>
                                        </td>
                                    
                                        <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                            <?php if (!empty($plat['image']) && file_exists('../uploads/' . $plat['image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                     class="h-12 w-12 rounded-lg object-cover" 
                                                     alt="<?= htmlspecialchars($plat['nom']) ?>">
                                            <?php else: ?>
                                                <div class="h-12 w-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($plat), ENT_QUOTES, 'UTF-8') ?>)"
                                                       class="action-btn btn-edit">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="hidden sm:inline">Modifier</span>
                                                </button>
                                                <button onclick="confirmDelete(<?= $plat['id'] ?>, '<?= addslashes($plat['nom']) ?>')" 
                                                        class="action-btn btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                    <span class="hidden sm:inline">Supprimer</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
                                            <div class="flex flex-col items-center gap-4">
                                                <div class="bg-gray-100 p-6 rounded-full">
                                                    <i class="fas fa-utensils text-4xl text-gray-400"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucun plat trouvé</h3>
                                                    <p class="text-gray-500">
                                                        <?php if($filtreCategorie): ?>
                                                            Aucun plat n'est disponible dans cette catégorie.
                                                        <?php else: ?>
                                                            Commencez par ajouter votre premier plat.
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <?php if(!$filtreCategorie): ?>
                                                    <a href="ajouter_plat.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                                        <i class="fas fa-plus mr-2"></i>Ajouter le premier plat
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="fixed inset-0 modal-backdrop hidden items-center justify-center z-50 p-4">
        <div class="modal-content bg-white rounded-2xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white bg-opacity-20 p-2 rounded-lg">
                            <i class="fas fa-edit text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold">Modifier le plat</h2>
                    </div>
                    <button onclick="closeEditModal()" class="bg-white bg-opacity-20 hover:bg-opacity-30 p-2 rounded-lg transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <form id="editForm" method="post" enctype="multipart/form-data" class="p-6">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nom du plat -->
                    <div class="md:col-span-2">
                        <label for="edit_nom" class="block text-sm font-semibold text-gray-700 mb-2">
                            Nom du plat
                        </label>
                        <input type="text" id="edit_nom" name="nom" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Prix -->
                    <div>
                        <label for="edit_prix" class="block text-sm font-semibold text-gray-700 mb-2">
                            Prix (FCFA)
                        </label>
                        <input type="number" id="edit_prix" name="prix" step="0.01" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <!-- Catégorie -->
                    <div>
                        <label for="edit_categorie" class="block text-sm font-semibold text-gray-700 mb-2">
                            Catégorie
                        </label>
                        <select id="edit_categorie" name="categorie_id"
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="edit_description" class="block text-sm font-semibold text-gray-700 mb-2">
                            Description
                        </label>
                        <textarea id="edit_description" name="description" rows="4"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
                    </div>

                    <!-- Image actuelle -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Image actuelle
                        </label>
                        <div id="current_image_container" class="mb-4">
                            <!-- L'image actuelle sera affichée ici -->
                        </div>
                    </div>

                    <!-- Nouvelle image -->
                    <div class="md:col-span-2">
                        <label for="edit_image" class="block text-sm font-semibold text-gray-700 mb-2">
                            Nouvelle image (optionnel)
                        </label>
                        <input type="file" id="edit_image" name="image" accept="image/*"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <p class="text-sm text-gray-500 mt-2">Laissez vide pour conserver l'image actuelle</p>
                    </div>
                </div>

                <!-- Messages d'erreur/succès -->
                <div id="modal_messages" class="mt-6"></div>

                <!-- Boutons d'action -->
                <div class="flex flex-col sm:flex-row gap-4 mt-8">
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-times mr-2"></i>Annuler
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white py-3 px-6 rounded-lg font-semibold transition-all">
                        <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Fonction pour ouvrir le modal de modification
        function openEditModal(plat) {
            // Remplir les champs du formulaire
            document.getElementById('edit_id').value = plat.id;
            document.getElementById('edit_nom').value = plat.nom;
            document.getElementById('edit_prix').value = plat.prix;
            document.getElementById('edit_description').value = plat.description || '';
            
            // Sélectionner la catégorie
            const categorieSelect = document.getElementById('edit_categorie');
            for (let option of categorieSelect.options) {
                if (option.text === plat.categorie_nom) {
                    option.selected = true;
                    break;
                }
            }

            // Afficher l'image actuelle
            const imageContainer = document.getElementById('current_image_container');
            if (plat.image) {
                imageContainer.innerHTML = `
                    <div class="relative inline-block">
                        <img src="../uploads/${plat.image}" 
                             class="h-24 w-24 rounded-lg object-cover shadow-md border border-gray-200" 
                             alt="${plat.nom}">
                        <div class="absolute -top-2 -right-2 bg-green-500 text-white p-1 rounded-full text-xs">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                `;
            } else {
                imageContainer.innerHTML = `
                    <div class="h-24 w-24 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                        <i class="fas fa-image text-gray-400 text-xl"></i>
                    </div>
                `;
            }

            // Définir l'action du formulaire
            document.getElementById('editForm').action = 'modifier_plat_ajax.php';

            // Afficher le modal
            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Empêcher le scroll du body
            document.body.style.overflow = 'hidden';
        }

        // Fonction pour fermer le modal de modification
        function closeEditModal() {
            const modal = document.getElementById('editModal');
            const modalContent = modal.querySelector('.modal-content');
            
            // Animation de sortie
            modalContent.classList.add('modal-exit');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modalContent.classList.remove('modal-exit');
                
                // Réactiver le scroll du body
                document.body.style.overflow = 'auto';
                
                // Nettoyer les messages
                document.getElementById('modal_messages').innerHTML = '';
            }, 200);
        }

        // Gestion de la soumission du formulaire de modification
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Désactiver le bouton et afficher le loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
            
            fetch('modifier_plat_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messagesDiv = document.getElementById('modal_messages');
                
                if (data.success) {
                    messagesDiv.innerHTML = `
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>${data.message}</span>
                            </div>
                        </div>
                    `;
                    
                    // Fermer le modal après 1.5 secondes et recharger la page
                    setTimeout(() => {
                        closeEditModal();
                        location.reload();
                    }, 1500);
                } else {
                    messagesDiv.innerHTML = `
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span>${data.message}</span>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('modal_messages').innerHTML = `
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>Une erreur est survenue lors de la modification.</span>
                        </div>
                    </div>
                `;
            })
            .finally(() => {
                // Réactiver le bouton
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Fonction de confirmation de suppression améliorée
        function confirmDelete(id, nom) {
            // Créer une modal personnalisée
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl transform scale-95 transition-all duration-300">
                    <div class="text-center">
                        <div class="bg-red-100 p-4 rounded-2xl inline-block mb-4">
                            <i class="fas fa-trash text-red-600 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Confirmer la suppression</h3>
                        <p class="text-gray-600 mb-6">
                            Êtes-vous sûr de vouloir supprimer le plat <strong>"${nom}"</strong> ?
                            <br><br>
                            <span class="text-red-600 text-sm">⚠️ Cette action est irréversible.</span>
                        </p>
                        <div class="flex gap-4">
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-lg font-semibold transition-colors">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                            <button onclick="window.location.href='supprimer_plat.php?id=${id}'" 
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors">
                                <i class="fas fa-trash mr-2"></i>Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Animation d'entrée
            setTimeout(() => {
                modal.querySelector('.bg-white').style.transform = 'scale(1)';
            }, 10);
        }

        // Fermer le modal en cliquant sur le backdrop
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Fermer le modal avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('editModal').classList.contains('hidden')) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>