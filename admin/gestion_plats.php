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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-in': 'bounceIn 0.8s ease-out',
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
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .gradient-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .card-elevated {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-elevated:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .btn-modern {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .table-modern tr {
            transition: all 0.2s ease;
        }
        
        .table-modern tr:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.01);
        }
        
        .floating-card {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .status-indicator {
            animation: pulse 2s infinite;
        }
        
        @media (max-width: 1024px) {
            .sidebar-container {
                width: auto;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <div class="p-4 lg:p-8">
                <!-- Header avec design moderne -->
                <div class="gradient-primary rounded-3xl p-6 lg:p-8 mb-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 bg-black opacity-10"></div>
                    <div class="absolute top-0 right-0 w-40 h-40 bg-white opacity-10 rounded-full -mr-20 -mt-20"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 bg-white opacity-5 rounded-full -ml-16 -mb-16"></div>
                    
                    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                        <div class="animate-slide-up">
                            <div class="flex items-center mb-4">
                                <div class="bg-white bg-opacity-20 p-3 rounded-2xl mr-4">
                                    <i class="fas fa-utensils text-2xl lg:text-3xl"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl lg:text-5xl font-bold mb-2">Gestion des Plats</h1>
                                    <p class="text-white/90 text-lg">Interface moderne pour gérer votre menu</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="floating-card">
                            <div class="glass-effect rounded-2xl p-6 text-center">
                                <div class="bg-white bg-opacity-30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-chart-line text-2xl"></i>
                                </div>
                                <p class="text-sm font-medium">Dashboard</p>
                                <p class="text-xs opacity-80">Temps réel</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cartes statistiques modernes -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="card-elevated bg-white rounded-2xl p-6 animate-bounce-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Total des plats</p>
                                <p class="text-3xl font-bold text-gray-900"><?= count($plats) ?></p>
                                <p class="text-xs text-green-600 flex items-center mt-1">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +12% ce mois
                                </p>
                            </div>
                            <div class="gradient-primary w-16 h-16 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-utensils text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-elevated bg-white rounded-2xl p-6 animate-bounce-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Catégories</p>
                                <p class="text-3xl font-bold text-gray-900"><?= $totalCategories ?></p>
                                <p class="text-xs text-blue-600 flex items-center mt-1">
                                    <i class="fas fa-equals mr-1"></i>
                                    Stable
                                </p>
                            </div>
                            <div class="gradient-success w-16 h-16 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-tags text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-elevated bg-white rounded-2xl p-6 animate-bounce-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Statut système</p>
                                <p class="text-xl font-bold text-green-600">En ligne</p>
                                <p class="text-xs text-gray-500 flex items-center mt-1">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 status-indicator"></div>
                                    Opérationnel
                                </p>
                            </div>
                            <div class="gradient-secondary w-16 h-16 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-server text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section filtres et actions avec design premium -->
                <div class="card-elevated bg-white rounded-3xl p-6 lg:p-8 mb-8 animate-fade-in">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                        <!-- Filtres modernisés -->
                        <form method="get" class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full lg:w-auto">
                            <div class="flex items-center gap-3">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-2 rounded-xl">
                                    <i class="fas fa-filter text-white"></i>
                                </div>
                                <label for="categorie" class="font-semibold text-gray-800">Filtrer par catégorie</label>
                            </div>
                            
                            <div class="flex gap-3 w-full sm:w-auto">
                                <select name="categorie" id="categorie" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all duration-300 bg-white shadow-sm min-w-48">
                                    <option value="">🍽️ Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $filtreCategorie) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" class="btn-modern gradient-primary text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-search mr-2"></i>Filtrer
                                </button>
                            </div>
                            
                            <?php if($filtreCategorie): ?>
                                <a href="gestion_plats.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl transition-all duration-300 flex items-center gap-2 font-medium">
                                    <i class="fas fa-times"></i>Réinitialiser
                                </a>
                            <?php endif; ?>
                        </form>

                        <!-- Actions avec boutons modernes -->
                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
                            <form method="post" action="export_plats_pdf.php" class="inline w-full sm:w-auto">
                                <button type="submit" class="btn-modern bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 w-full sm:w-auto">
                                    <i class="fas fa-file-pdf mr-2"></i>Exporter PDF
                                </button>
                            </form>
                            
                            <a href="ajouter_plat.php" class="btn-modern bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center w-full sm:w-auto">
                                <i class="fas fa-plus mr-2"></i>Ajouter un plat
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tableau moderne avec design premium -->
                <div class="card-elevated bg-white rounded-3xl overflow-hidden shadow-2xl animate-fade-in">
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-table mr-3 text-purple-600"></i>
                            Liste des plats
                            <?php if($filtreCategorie): ?>
                                <span class="ml-3 bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                    Filtré
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-modern">
                            <thead class="bg-gradient-to-r from-slate-100 to-slate-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-hashtag text-purple-600"></i>
                                            <span class="hidden sm:inline">ID</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-utensils text-blue-600"></i>Nom du plat
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-align-left text-green-600"></i>Description
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-euro-sign text-yellow-600"></i>Prix
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider hidden sm:table-cell">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-tags text-indigo-600"></i>Catégorie
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider hidden md:table-cell">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-image text-pink-600"></i>Image
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">
                                        <div class="flex items-center justify-center gap-2">
                                            <i class="fas fa-cogs text-red-600"></i>Actions
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                        
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (!empty($plats)): ?>
                                    <?php foreach ($plats as $index => $plat): ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-purple-50 hover:to-blue-50 transition-all duration-300" style="animation: fadeIn 0.5s ease-in-out <?= $index * 0.1 ?>s both;">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="bg-gradient-to-r from-purple-100 to-blue-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold inline-block">
                                                #<?= $plat['id'] ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900 text-lg">
                                                <?= htmlspecialchars($plat['nom']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-gray-600 text-sm max-w-xs truncate">
                                                <?= htmlspecialchars($plat['description']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 px-3 py-2 rounded-lg font-bold text-lg inline-block">
                                                <?= number_format($plat['prix'], 2) ?> <span class="text-sm">FCFA</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                            <span class="bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?= htmlspecialchars($plat['categorie_nom'] ?? 'Non catégorisé') ?>
                                            </span>
                                        </td>
                                    
                                        <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                            <?php if (!empty($plat['image']) && file_exists('../uploads/' . $plat['image'])): ?>
                                                <div class="relative group">
                                                    <img src="../uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                         class="h-16 w-16 rounded-2xl object-cover shadow-lg ring-4 ring-white group-hover:scale-110 transition-transform duration-300" 
                                                         alt="<?= htmlspecialchars($plat['nom']) ?>">
                                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-2xl transition-all duration-300"></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="h-16 w-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-400 text-xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="modifier_plat.php?id=<?= $plat['id'] ?>" 
                                                   class="bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    <span class="hidden sm:inline">Modifier</span>
                                                </a>
                                                <button onclick="confirmDelete(<?= $plat['id'] ?>, '<?= addslashes($plat['nom']) ?>')" 
                                                        class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                                                    <i class="fas fa-trash mr-1"></i>
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
                                                    <a href="ajouter_plat.php" class="btn-modern gradient-primary text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300">
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

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes
            const cards = document.querySelectorAll('.card-elevated');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });

        // Fonction de confirmation de suppression améliorée
        function confirmDelete(id, nom) {
            // Créer une modal personnalisée
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl transform scale-95 transition-all duration-300">
                    <div class="text-center">
                        <div class="bg-red-100 p-4 rounded-2xl inline-block mb-4">
                            <i class="fas fa-trash text-red-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Confirmer la suppression</h3>
                        <p class="text-gray-600 mb-6">
                            Êtes-vous sûr de vouloir supprimer le plat <strong>"${nom}"</strong> ?
                            <br><br>
                            <span class="text-red-600 text-sm">⚠️ Cette action est irréversible.</span>
                        </p>
                        <div class="flex gap-4">
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-xl font-semibold transition-all duration-300">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                            <button onclick="window.location.href='supprimer_plat.php?id=${id}'" 
                                    class="flex-1 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white py-3 px-6 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
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

        // Effet de parallaxe léger pour les éléments flottants
        document.addEventListener('mousemove', function(e) {
            const floating = document.querySelectorAll('.floating-card');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            floating.forEach(el => {
                const intensity = 10;
                el.style.transform = `translateX(${x * intensity}px) translateY(${y * intensity}px)`;
            });
        });
    </script>
</body>
</html>