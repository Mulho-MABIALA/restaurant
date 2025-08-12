<?php
require_once 'config.php';

// Configuration des chemins
define('UPLOAD_URL', 'http://localhost/restaurant/uploads/gallery/');
define('DISHES_UPLOAD_URL', 'http://localhost/restaurant/uploads/'); // URL pour les images de plats

// Fonction pour récupérer les plats depuis la table des plats
function getDishesForGallery() {
    global $conn;
    
    try {
        // Supposons que votre table des plats s'appelle 'plats' avec les colonnes 'id', 'nom', 'image'
        // Ajustez selon votre structure de base de données
        $stmt = $conn->prepare('SELECT id, nom as title, image as filename, "plats" as category, created_at FROM plats WHERE image IS NOT NULL AND image != "" ORDER BY created_at DESC');
        $stmt->execute();
        $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Transformer les données pour correspondre au format de la galerie
        $dishesForGallery = [];
        foreach ($dishes as $dish) {
            $dishesForGallery[] = [
                'id' => 'dish_' . $dish['id'], // Préfixe pour différencier des images normales
                'filename' => $dish['filename'],
                'original_name' => $dish['title'],
                'title' => $dish['title'],
                'category' => 'plats',
                'created_at' => $dish['created_at'],
                'is_dish' => true // Marqueur pour identifier les plats
            ];
        }
        
        return $dishesForGallery;
    } catch (Exception $e) {
        return [];
    }
}

// Récupération de toutes les catégories disponibles (images normales + plats)
try {
    $stmt = $conn->prepare('SELECT DISTINCT category FROM images WHERE category IS NOT NULL AND category != "" ORDER BY category');
    $stmt->execute();
    $availableCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Vérifier s'il y a des plats et ajouter "plats" aux catégories
    $dishes = getDishesForGallery();
    if (!empty($dishes) && !in_array('plats', $availableCategories)) {
        $availableCategories[] = 'plats';
        sort($availableCategories);
    }
    
    // Si aucune catégorie trouvée dans les images, récupérer depuis gallery_categories
    if (empty($availableCategories)) {
        $stmt = $conn->prepare('SELECT name FROM gallery_categories ORDER BY name');
        $stmt->execute();
        $availableCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $availableCategories = [];
    $dishes = [];
}

// Catégorie demandée (par défaut la première disponible ou "tous")
$category = $_GET['category'] ?? '';

// Si aucune catégorie spécifiée ou si la catégorie n'existe pas, prendre la première ou "tous"
if (!$category || (!in_array($category, $availableCategories) && $category !== 'tous')) {
    $category = !empty($availableCategories) ? $availableCategories[0] : 'tous';
}

// Récupération des images normales
try {
    if ($category === 'tous') {
        // Récupérer toutes les images normales
        $stmt = $conn->prepare('SELECT * FROM images ORDER BY created_at DESC');
        $stmt->execute();
        $normalImages = $stmt->fetchAll();
    } elseif ($category === 'plats') {
        // Pour la catégorie plats, on ne récupère que les plats
        $normalImages = [];
    } else {
        // Récupérer les images de la catégorie sélectionnée
        $stmt = $conn->prepare('SELECT * FROM images WHERE category = ? ORDER BY created_at DESC');
        $stmt->execute([$category]);
        $normalImages = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $normalImages = [];
}

// Combiner les images selon la catégorie sélectionnée
if ($category === 'tous') {
    // Combiner toutes les images (normales + plats)
    $allImages = array_merge($dishes, $normalImages);
} elseif ($category === 'plats') {
    // Afficher seulement les plats
    $allImages = $dishes;
} else {
    // Afficher seulement les images normales de la catégorie
    $allImages = $normalImages;
}

// Trier toutes les images par date de création (les plus récentes en premier)
usort($allImages, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Compter le nombre d'images par catégorie pour l'affichage
$categoryCounts = [];
try {
    // Compter les images normales par catégorie
    $stmt = $conn->prepare('SELECT category, COUNT(*) as count FROM images WHERE category IS NOT NULL AND category != "" GROUP BY category');
    $stmt->execute();
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $categoryCounts = $counts;
    
    // Ajouter le compte des plats
    if (!empty($dishes)) {
        $categoryCounts['plats'] = count($dishes);
    }
    
    // Compter le total pour "tous"
    $stmt = $conn->prepare('SELECT COUNT(*) FROM images');
    $stmt->execute();
    $totalNormalImages = $stmt->fetchColumn();
    $totalImages = $totalNormalImages + count($dishes);
} catch (Exception $e) {
    $categoryCounts = [];
    $totalImages = count($allImages);
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Galerie <?= $category !== 'tous' ? '- ' . ucfirst(htmlspecialchars($category)) : '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Style pour la lightbox */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        
        .lightbox-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 5%;
        }
        
        .lightbox-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .lightbox-close:hover {
            color: #bbb;
        }
        
        .lightbox-info {
            text-align: center;
            color: #ccc;
            padding: 10px;
            font-size: 14px;
        }
        
        .image-hover {
            transition: transform 0.3s ease;
        }
        
        .image-hover:hover {
            transform: scale(1.05);
        }

        /* Style spécial pour les plats */
        .dish-card {
            border: 2px solid #3B82F6;
            background: linear-gradient(135deg, #EBF4FF 0%, #DBEAFE 100%);
        }
        
        .dish-badge {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<?php include('includes/navbar.php'); ?>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- En-tête -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Notre Galerie</h1>
            <p class="text-gray-600">
                <?php if ($category === 'tous'): ?>
                    Découvrez toutes nos créations
                <?php elseif ($category === 'plats'): ?>
                    Nos délicieux plats
                <?php else: ?>
                    Catégorie : <?= ucfirst(htmlspecialchars($category)) ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Navigation par catégorie -->
        <div class="mb-8">
            <div class="flex flex-wrap justify-center gap-2 md:gap-4">
                <!-- Bouton "Tous" -->
                <a href="?category=tous"
                   class="px-4 py-2 rounded-full text-sm md:text-base font-medium transition-colors duration-200 
                   <?= $category === 'tous' 
                       ? 'bg-blue-600 text-white shadow-lg' 
                       : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                   Tous <?= $totalImages > 0 ? "($totalImages)" : '' ?>
                </a>
                
                <!-- Boutons des catégories -->
                <?php foreach ($availableCategories as $cat): ?>
                    <a href="?category=<?= urlencode($cat) ?>"
                       class="px-4 py-2 rounded-full text-sm md:text-base font-medium transition-colors duration-200 
                       <?= $cat === $category 
                           ? 'bg-blue-600 text-white shadow-lg' 
                           : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                       <?= ucfirst(htmlspecialchars($cat)) ?>
                       <?= isset($categoryCounts[$cat]) ? "({$categoryCounts[$cat]})" : '' ?>
                       <?php if ($cat === 'plats'): ?>
                           🍽️
                       <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Information spéciale pour la catégorie plats -->
        <?php if ($category === 'plats' && !empty($dishes)): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg mb-6 text-center">
                <p class="font-medium">🍽️ Découvrez nos délicieux plats, préparés avec soin par notre chef</p>
            </div>
        <?php endif; ?>

        <!-- Grille des images -->
        <?php if (count($allImages) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach($allImages as $img): ?>
                    <?php 
                    $isDish = isset($img['is_dish']) && $img['is_dish'];
                    $imageUrl = $isDish ? DISHES_UPLOAD_URL . htmlspecialchars($img['filename']) : UPLOAD_URL . htmlspecialchars($img['filename']);
                    $cardClass = $isDish ? 'dish-card' : 'bg-white';
                    ?>
                    <div class="<?= $cardClass ?> rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="relative group cursor-pointer" onclick="openLightbox('<?= $imageUrl ?>', '<?= htmlspecialchars($img['title'] ?: $img['original_name']) ?>', '<?= htmlspecialchars($img['category']) ?>', <?= $isDish ? 'true' : 'false' ?>)">
                            <img src="<?= $imageUrl ?>"
                                 alt="<?= htmlspecialchars($img['title'] ?: $img['original_name']) ?>"
                                 class="w-full h-48 object-cover image-hover">
                            
                            <!-- Badge spécial pour les plats -->
                            <?php if ($isDish): ?>
                                <div class="absolute top-2 left-2">
                                    <span class="dish-badge">Plat</span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Overlay au survol -->
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
                                <div class="text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations de l'image -->
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-800 mb-1 truncate">
                                <?= htmlspecialchars($img['title'] ?: 'Sans titre') ?>
                            </h3>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span class="<?= $isDish ? 'dish-badge text-xs' : 'bg-gray-100 px-2 py-1 rounded-full text-xs' ?>">
                                    <?= ucfirst(htmlspecialchars($img['category'])) ?>
                                    <?= $isDish ? ' 🍽️' : '' ?>
                                </span>
                                <time><?= date('d/m/Y', strtotime($img['created_at'])) ?></time>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Message quand aucune image -->
            <div class="text-center py-16">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-500 mb-2">Aucune image trouvée</h3>
                <p class="text-gray-400">
                    <?php if ($category === 'tous'): ?>
                        La galerie est actuellement vide.
                    <?php elseif ($category === 'plats'): ?>
                        Aucun plat disponible pour le moment.
                    <?php else: ?>
                        Aucune image dans la catégorie "<?= htmlspecialchars($category) ?>".
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img id="lightbox-img" class="lightbox-content">
        <div id="lightbox-info" class="lightbox-info"></div>
    </div>

    <script>
        // Fonctions de la lightbox
        function openLightbox(src, title, category, isDish = false) {
            const lightbox = document.getElementById('lightbox');
            const img = document.getElementById('lightbox-img');
            const info = document.getElementById('lightbox-info');
            
            img.src = src;
            info.innerHTML = `
                <div class="font-semibold">${title} ${isDish ? '🍽️' : ''}</div>
                <div class="text-sm mt-1">
                    Catégorie: ${category} 
                    ${isDish ? '<span class="ml-2 px-2 py-1 bg-blue-600 rounded text-xs">Plat de notre chef</span>' : ''}
                </div>
            `;
            
            lightbox.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Empêcher le scroll
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.style.display = 'none';
            document.body.style.overflow = 'auto'; // Réactiver le scroll
        }

        // Fermer avec la touche Echap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });

        // Empêcher la fermeture quand on clique sur l'image
        document.getElementById('lightbox-img').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.getElementById('lightbox-info').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>