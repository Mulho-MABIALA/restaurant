<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once './permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'gallery');
define('UPLOAD_URL', 'http://localhost/restaurant/uploads/gallery/');
define('DISHES_UPLOAD_URL', 'http://localhost/restaurant/uploads/'); // URL pour les images de plats
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 Mo max
define('UPLOAD_DIR', __DIR__ . '/../uploads/gallery/');
$ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// ===== GESTION AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'upload':
            $response = handleUpload();
            break;
        case 'update':
            $response = handleUpdate();
            break;
        case 'delete':
            $response = handleDelete();
            break;
        case 'add_category':
            $response = handleAddCategory();
            break;
        case 'delete_category':
            $response = handleDeleteCategory();
            break;
        case 'get_categories':
            $response = handleGetCategories();
            break;
        default:
            $response = ['success' => false, 'message' => 'Action non reconnue'];
    }
    
    echo json_encode($response);
    exit;
}

function handleUpload() {
    global $conn, $ALLOWED_MIMES;
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Aucune image reçue ou erreur d\'upload.'];
    }
    
    $file = $_FILES['image'];
    
    // Vérification taille
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Fichier trop volumineux (max : ' . (MAX_FILE_SIZE / 1024 / 1024) . ' Mo).'];
    }
    
    // Vérification type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $ALLOWED_MIMES)) {
        return ['success' => false, 'message' => 'Format non autorisé. Formats acceptés : JPG, PNG, GIF, WEBP.'];
    }
    
    // Récupération des champs
    $category = trim($_POST['category'] ?? 'Sans catégorie');
    $title = trim($_POST['title'] ?? '');
    
    // Empêcher l'ajout manuel dans la catégorie "plats"
    if (strtolower($category) === 'plats') {
        return ['success' => false, 'message' => 'La catégorie "plats" est gérée automatiquement depuis la gestion des plats.'];
    }
    
    // Création dossier si inexistant
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Génération nom unique
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $base = bin2hex(random_bytes(8));
    $newName = $base . '.' . strtolower($ext);
    $target = UPLOAD_DIR . $newName;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        // Insertion en base
        $stmt = $conn->prepare('INSERT INTO images (filename, original_name, category, title) VALUES (?, ?, ?, ?)');
        $stmt->execute([$newName, $file['name'], $category, $title]);
        
        // Récupérer l'image ajoutée pour la retourner
        $id = $conn->lastInsertId();
        $stmt = $conn->prepare('SELECT * FROM images WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        
        return [
            'success' => true, 
            'message' => 'Image ajoutée avec succès.',
            'image' => $image
        ];
    } else {
        return ['success' => false, 'message' => 'Impossible de déplacer le fichier.'];
    }
}

function handleUpdate() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Sans catégorie');
    
    if (!$id) {
        return ['success' => false, 'message' => 'ID manquant.'];
    }
    
    // Empêcher la modification vers la catégorie "plats"
    if (strtolower($category) === 'plats') {
        return ['success' => false, 'message' => 'La catégorie "plats" est réservée aux plats gérés automatiquement.'];
    }
    
    $stmt = $conn->prepare('UPDATE images SET title = ?, category = ? WHERE id = ?');
    $success = $stmt->execute([$title, $category, $id]);
    
    if ($success) {
        // Récupérer l'image modifiée
        $stmt = $conn->prepare('SELECT * FROM images WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        
        return [
            'success' => true, 
            'message' => 'Image modifiée avec succès.',
            'image' => $image
        ];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la modification.'];
    }
}

function handleDelete() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        return ['success' => false, 'message' => 'ID manquant.'];
    }
    
    // Vérifier si c'est une image de la catégorie "plats" (protection)
    $stmt = $conn->prepare('SELECT category FROM images WHERE id = ?');
    $stmt->execute([$id]);
    $category = $stmt->fetchColumn();
    
    if (strtolower($category) === 'plats') {
        return ['success' => false, 'message' => 'Les images de plats ne peuvent être supprimées que depuis la gestion des plats.'];
    }
    
    // Récupérer le nom du fichier
    $stmt = $conn->prepare('SELECT filename FROM images WHERE id = ?');
    $stmt->execute([$id]);
    $filename = $stmt->fetchColumn();
    
    if ($filename) {
        $filePath = UPLOAD_DIR . $filename;
        
        // Supprimer le fichier s'il existe
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Supprimer l'entrée en base
        $del = $conn->prepare('DELETE FROM images WHERE id = ?');
        $success = $del->execute([$id]);
        
        return [
            'success' => $success, 
            'message' => $success ? 'Image supprimée avec succès.' : 'Erreur lors de la suppression.',
            'id' => $id
        ];
    } else {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }
}

function handleAddCategory() {
    global $conn;
    
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'Le nom de la catégorie est requis.'];
    }
    
    // Empêcher la création manuelle de la catégorie "plats"
    if (strtolower($name) === 'plats') {
        return ['success' => false, 'message' => 'La catégorie "plats" est gérée automatiquement.'];
    }
    
    // Vérifier si la catégorie existe déjà
    $stmt = $conn->prepare('SELECT COUNT(*) FROM gallery_categories WHERE name = ?');
    $stmt->execute([$name]);
    $exists = $stmt->fetchColumn() > 0;
    
    if ($exists) {
        return ['success' => false, 'message' => 'Cette catégorie existe déjà.'];
    }
    
    // Insérer la nouvelle catégorie
    $stmt = $conn->prepare('INSERT INTO gallery_categories (name) VALUES (?)');
    $success = $stmt->execute([$name]);
    
    if ($success) {
        $id = $conn->lastInsertId();
        return [
            'success' => true, 
            'message' => 'Catégorie ajoutée avec succès.',
            'category' => ['id' => $id, 'name' => $name]
        ];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout de la catégorie.'];
    }
}

function handleDeleteCategory() {
    global $conn;
    
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'Le nom de la catégorie est requis.'];
    }
    
    // Empêcher la suppression de la catégorie "plats"
    if (strtolower($name) === 'plats') {
        return ['success' => false, 'message' => 'La catégorie "plats" ne peut pas être supprimée car elle est gérée automatiquement.'];
    }
    
    // Vérifier s'il y a des images dans cette catégorie
    $stmt = $conn->prepare('SELECT COUNT(*) FROM images WHERE category = ?');
    $stmt->execute([$name]);
    $imageCount = $stmt->fetchColumn();
    
    if ($imageCount > 0) {
        // Créer ou utiliser une catégorie "Sans catégorie" pour les images orphelines
        $stmt = $conn->prepare('SELECT COUNT(*) FROM gallery_categories WHERE name = ?');
        $stmt->execute(['Sans catégorie']);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare('INSERT INTO gallery_categories (name) VALUES (?)');
            $stmt->execute(['Sans catégorie']);
        }
        
        // Déplacer les images vers "Sans catégorie"
        $stmt = $conn->prepare('UPDATE images SET category = ? WHERE category = ?');
        $stmt->execute(['Sans catégorie', $name]);
    }
    
    // Supprimer la catégorie
    $stmt = $conn->prepare('DELETE FROM gallery_categories WHERE name = ?');
    $success = $stmt->execute([$name]);
    
    if ($success) {
        $message = $imageCount > 0 ? 
            "Catégorie supprimée. $imageCount images ont été déplacées vers 'Sans catégorie'." : 
            'Catégorie supprimée avec succès.';
        
        return [
            'success' => true, 
            'message' => $message,
            'name' => $name,
            'movedImages' => $imageCount
        ];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la suppression de la catégorie.'];
    }
}

function handleGetCategories() {
    global $conn;
    
    // Récupérer toutes les catégories de la galerie
    $stmt = $conn->prepare('SELECT name FROM gallery_categories ORDER BY name');
    $stmt->execute();
    $allCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Ajouter automatiquement "plats" si elle n'existe pas
    if (!in_array('plats', $allCategories)) {
        $allCategories[] = 'plats';
        sort($allCategories);
    }
    
    return [
        'success' => true, 
        'categories' => $allCategories
    ];
}

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

// Initialiser la table des catégories de galerie si elle n'existe pas
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS gallery_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table existe déjà ou erreur de création
}

// S'assurer que la catégorie "plats" existe
try {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM gallery_categories WHERE name = ?');
    $stmt->execute(['plats']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $conn->prepare('INSERT INTO gallery_categories (name) VALUES (?)');
        $stmt->execute(['plats']);
    }
} catch (Exception $e) {
    // Erreur lors de l'insertion
}

// Récupérer toutes les images normales (hors plats)
$images = $conn->query('SELECT * FROM images ORDER BY created_at DESC')->fetchAll();

// Récupérer les plats pour la galerie
$dishes = getDishesForGallery();

// Combiner images et plats
$allImages = array_merge($dishes, $images);

// Trier par date de création (les plus récents en premier)
usort($allImages, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Récupérer toutes les catégories de galerie
$stmt = $conn->prepare('SELECT name FROM gallery_categories ORDER BY name');
$stmt->execute();
$allCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculer les statistiques
$totalImages = count($allImages);
$totalCategories = count($allCategories);
$dishImages = count($dishes);
$normalImages = count($images);

// Statistiques par catégorie
$categoryStats = [];
foreach ($allImages as $img) {
    $cat = $img['category'];
    if (!isset($categoryStats[$cat])) {
        $categoryStats[$cat] = 0;
    }
    $categoryStats[$cat]++;
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Restaurant Mulho - Galerie</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        /* Styles pour la galerie - Design inspiré de reservations.php */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding: 0;
        }

        .gallery-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 2px solid #e5e7eb;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: #667eea;
        }

        .gallery-card-image-wrapper {
            position: relative;
            width: 100%;
            height: 240px;
            overflow: hidden;
            background: #f3f4f6;
        }

        .gallery-card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gallery-card:hover .gallery-card-image {
            transform: scale(1.05);
        }

        .gallery-card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .badge-plat {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .badge-category {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .gallery-card-content {
            padding: 16px;
        }

        .gallery-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .gallery-card-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .gallery-card-category {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            font-size: 13px;
            font-weight: 500;
        }

        .gallery-card-date {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #6b7280;
            font-size: 12px;
        }

        .gallery-card-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid;
        }

        .btn-view {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        .btn-view:hover {
            background: #bfdbfe;
            transform: scale(1.05);
        }

        .btn-edit {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .btn-edit:hover {
            background: #fde68a;
            transform: scale(1.05);
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: scale(1.05);
        }

        .dish-card-notice {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 11px;
            text-align: center;
            font-weight: 500;
        }

        /* Lightbox styles - Design de cartes.php */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .lightbox.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
            margin: 0 auto;
        }

        .lightbox-img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
        }

        .lightbox-info {
            text-align: center;
            color: white;
            margin-top: 20px;
        }

        .lightbox-caption {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
        }

        .lightbox-close {
            position: absolute;
            top: -60px;
            right: 0;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
            transition: all 0.3s ease;
            background: transparent;
            border: none;
            width: auto;
            height: auto;
        }

        .lightbox-close:hover {
            color: #667eea;
            transform: scale(1.2);
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            padding: 20px;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-nav:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.2);
            transform: translateY(-50%) scale(1.1);
        }

        .lightbox-prev {
            left: -80px;
        }

        .lightbox-next {
            right: -80px;
        }

        .lightbox-counter {
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }

        /* Responsive Gallery - Design de cartes.php */
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                padding: 0 20px;
            }

            .gallery-item {
                height: 250px;
            }

            .lightbox-nav {
                font-size: 1.5rem;
                width: 45px;
                height: 45px;
            }

            .lightbox-prev {
                left: -60px;
            }

            .lightbox-next {
                right: -60px;
            }

            .lightbox-caption {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .gallery-grid {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }

            .lightbox-nav {
                position: fixed;
                top: auto;
                bottom: 20px;
                transform: none;
                font-size: 1.3rem;
                width: 40px;
                height: 40px;
            }

            .lightbox-prev {
                left: 20px;
            }

            .lightbox-next {
                right: 20px;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 font-sans antialiased min-h-screen">
    <div class="flex h-screen overflow-hidden relative z-10">
        <?php include 'sidebar.php'; ?>   
        
        <div class="flex-1 p-6 overflow-y-auto">
            <!-- Header avec gradient -->
            <div class="gradient-bg rounded-xl p-6 mb-8 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">
                            <i class="fas fa-images mr-3"></i>Galerie d'images
                        </h1>
                        <p class="text-gray-100 text-lg">Gérez vos images et créez une galerie attractive</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="openCategoryModal()" class="bg-white bg-opacity-20 backdrop-blur-sm text-white px-4 py-3 rounded-xl hover:bg-opacity-30 transition-all duration-300 flex items-center gap-2">
                            <i class="fas fa-tags"></i>
                            Gérer les catégories
                        </button>
                        <button onclick="openUploadModal()" class="bg-white bg-opacity-20 backdrop-blur-sm text-white px-4 py-3 rounded-xl hover:bg-opacity-30 transition-all duration-300 flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            Ajouter une image
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cartes de statistiques - Design de reservations.php -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total des images -->
                <div class="dashboard-card card-pink">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Total Images</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $totalImages ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="fas fa-layer-group mr-1"></i>
                                Toutes sources
                            </p>
                        </div>
                        <div class="icon-wrapper icon-pink">
                            <i class="fas fa-images"></i>
                        </div>
                    </div>
                </div>

                <!-- Images de plats -->
                <div class="dashboard-card card-blue">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Images de Plats</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $dishImages ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="fas fa-sync-alt mr-1"></i>
                                Auto-sync
                            </p>
                        </div>
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                </div>

                <!-- Images normales -->
                <div class="dashboard-card card-green">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Images Galerie</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $normalImages ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="fas fa-upload mr-1"></i>
                                Manuelles
                            </p>
                        </div>
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                </div>

                <!-- Total catégories -->
                <div class="dashboard-card card-orange">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Catégories</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $totalCategories ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="fas fa-folder-open mr-1"></i>
                                Collections
                            </p>
                        </div>
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages de notification -->
            <div id="notification" class="hidden mb-6 p-4 rounded-xl"></div>

            <!-- Info sur la catégorie plats avec design amélioré -->
            <div class="glass-effect border border-blue-200 text-blue-800 p-4 rounded-xl mb-6 flex items-center gap-3">
                <div class="bg-blue-100 p-2 rounded-full">
                    <i class="fas fa-info-circle text-blue-600"></i>
                </div>
                <div>
                    <p class="font-medium">Information importante</p>
                    <p class="text-sm text-blue-600">La catégorie "plats" affiche automatiquement les plats depuis votre gestion des plats. Pour modifier ou supprimer ces images, utilisez la page de gestion des plats.</p>
                </div>
            </div>

            <!-- Grille des images avec design de cards style reservations.php -->
            <div class="gallery-grid" id="gallery-grid">
                <?php foreach($allImages as $index => $img): ?>
                    <?php
                    $isDish = isset($img['is_dish']) && $img['is_dish'];
                    $imageUrl = $isDish ? DISHES_UPLOAD_URL . htmlspecialchars($img['filename']) : UPLOAD_URL . htmlspecialchars($img['filename']);
                    ?>
                    <div class="gallery-card" data-index="<?= $index ?>" data-is-dish="<?= $isDish ? 'true' : 'false' ?>" data-id="<?= $img['id'] ?? 'dish_'.$img['id'] ?>">
                        <!-- Image -->
                        <div class="gallery-card-image-wrapper" onclick="openLightbox(<?= $index ?>)">
                            <img src="<?= $imageUrl ?>"
                                 alt="<?= htmlspecialchars($img['title'] ?: 'Image de la galerie') ?>"
                                 class="gallery-card-image"
                                 loading="lazy">

                            <!-- Badge catégorie -->
                            <?php if ($isDish): ?>
                                <span class="gallery-card-badge badge-plat">
                                    <i class="fas fa-utensils"></i>
                                    Plat
                                </span>
                            <?php else: ?>
                                <span class="gallery-card-badge badge-category">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($img['category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Contenu de la card -->
                        <div class="gallery-card-content">
                            <h3 class="gallery-card-title"><?= htmlspecialchars($img['title'] ?: 'Sans titre') ?></h3>

                            <div class="gallery-card-info">
                                <span class="gallery-card-category">
                                    <i class="fas fa-folder"></i>
                                    <?= htmlspecialchars($img['category']) ?>
                                </span>
                                <span class="gallery-card-date">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/Y', strtotime($img['created_at'])) ?>
                                </span>
                            </div>

                            <!-- Actions -->
                            <?php if ($isDish): ?>
                                <div class="dish-card-notice">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Géré depuis la page des plats
                                </div>
                            <?php else: ?>
                                <div class="gallery-card-actions">
                                    <button onclick="openLightbox(<?= $index ?>)" class="action-btn btn-view">
                                        <i class="fas fa-eye"></i>
                                        Voir
                                    </button>
                                    <button onclick="event.stopPropagation(); openEditModal('<?= $img['id'] ?>', '<?= htmlspecialchars($img['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($img['category']) ?>')"
                                            class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                        Modifier
                                    </button>
                                    <button onclick="event.stopPropagation(); confirmDeleteImage('<?= $img['id'] ?>', '<?= htmlspecialchars($img['title'], ENT_QUOTES) ?>')"
                                            class="action-btn btn-delete">
                                        <i class="fas fa-trash"></i>
                                        Supprimer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($allImages)): ?>
                <div class="text-center py-16">
                    <div class="bg-gray-100 rounded-full w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-images text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Aucune image trouvée</h3>
                    <p class="text-gray-500 mb-6">Commencez par ajouter votre première image à la galerie</p>
                    <button onclick="openUploadModal()" class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-colors duration-300 flex items-center gap-2 mx-auto">
                        <i class="fas fa-plus"></i>
                        Ajouter une image
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lightbox pour l'affichage des images -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
            <div class="lightbox-counter" id="lightboxCounter"></div>

            <div class="lightbox-nav lightbox-prev" onclick="previousImage(event)">
                <i class="fas fa-chevron-left"></i>
            </div>

            <img id="lightbox-img" class="lightbox-img" src="" alt="">

            <div class="lightbox-nav lightbox-next" onclick="nextImage(event)">
                <i class="fas fa-chevron-right"></i>
            </div>

            <div class="lightbox-info">
                <div class="lightbox-caption" id="lightbox-caption"></div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression - Style reservations.php -->
    <div id="deleteImageModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 m-4 max-w-md w-full border border-gray-200 shadow-xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Confirmer la suppression</h3>
                <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement l'image :</p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
                    <p class="font-medium text-gray-800" id="deleteImageInfo"></p>
                </div>
                <p class="text-red-600 text-sm font-medium mb-6">Cette action est irréversible !</p>
                <div class="flex space-x-3">
                    <button onclick="closeDeleteImageModal()"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Annuler
                    </button>
                    <button onclick="executeDeleteImage()"
                            id="confirmDeleteImageBtn"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'upload -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h2 class="text-xl font-semibold mb-4">Ajouter une image</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="mb-3">
                    <label class="block mb-1 font-medium">Image (JPG, PNG, GIF, WEBP)</label>
                    <input type="file" name="image" accept="image/*" required class="border p-2 w-full rounded">
                </div>
                <div class="mb-3">
                    <label class="block mb-1 font-medium">Titre (optionnel)</label>
                    <input type="text" name="title" class="border p-2 w-full rounded">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 font-medium">Catégorie</label>
                    <select name="category" id="uploadCategorySelect" class="border p-2 w-full rounded">
                        <option value="">Choisir une catégorie...</option>
                        <?php foreach($allCategories as $cat): ?>
                            <?php if (strtolower($cat) !== 'plats'): // Exclure "plats" du select ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= ucfirst(htmlspecialchars($cat)) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-xs text-gray-500 mt-1">
                        Note: La catégorie "plats" est gérée automatiquement depuis la gestion des plats.
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 border rounded hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        Uploader
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'édition -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h2 class="text-xl font-semibold mb-4">Modifier l'image</h2>
            <form id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="mb-3">
                    <label class="block mb-1 font-medium">Titre</label>
                    <input type="text" name="title" id="editTitle" class="border p-2 w-full rounded">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 font-medium">Catégorie</label>
                    <select name="category" id="editCategory" class="border p-2 w-full rounded">
                        <option value="">Choisir une catégorie...</option>
                        <?php foreach($allCategories as $cat): ?>
                            <?php if (strtolower($cat) !== 'plats'): // Exclure "plats" du select ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= ucfirst(htmlspecialchars($cat)) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 border rounded hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de gestion des catégories -->
    <div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h2 class="text-xl font-semibold mb-4">Gestion des catégories</h2>
            
            <!-- Ajouter une catégorie -->
            <div class="mb-4">
                <h3 class="font-medium mb-2">Ajouter une catégorie</h3>
                <div class="flex gap-2">
                    <input type="text" id="newCategoryName" placeholder="Nom de la catégorie" class="flex-1 border p-2 rounded">
                    <button onclick="addCategory()" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                        Ajouter
                    </button>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Note: La catégorie "plats" est automatique et ne peut être modifiée.
                </div>
            </div>
            
            <!-- Liste des catégories -->
            <div class="mb-4">
                <h3 class="font-medium mb-2">Catégories existantes</h3>
                <div id="categoryList" class="max-h-60 overflow-y-auto">
                    <!-- Les catégories seront chargées ici -->
                </div>
            </div>
            
            <div class="flex justify-end">
                <button onclick="closeCategoryModal()" class="px-4 py-2 border rounded hover:bg-gray-50">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentCategories = <?= json_encode($allCategories) ?>;
        let galleryImages = <?= json_encode($allImages) ?>;
        let currentLightboxIndex = 0;

        // Variables globales pour la suppression
        let imageToDelete = null;

        // Initialisation de la lightbox
        function initLightbox() {
            // Navigation au clavier
            document.addEventListener('keydown', (e) => {
                const lightbox = document.getElementById('lightbox');
                if (lightbox.classList.contains('active')) {
                    switch(e.key) {
                        case 'Escape':
                            closeLightbox();
                            break;
                        case 'ArrowLeft':
                            previousImage(e);
                            break;
                        case 'ArrowRight':
                            nextImage(e);
                            break;
                    }
                }
            });
        }

        // Ouvrir la lightbox
        function openLightbox(index) {
            currentLightboxIndex = index;
            const lightbox = document.getElementById('lightbox');
            updateLightboxContent();
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Fermer la lightbox
        function closeLightbox(event) {
            if (event && event.target !== event.currentTarget && event.type !== 'click') return;

            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Image suivante
        function nextImage(event) {
            event.stopPropagation();
            currentLightboxIndex = (currentLightboxIndex + 1) % galleryImages.length;
            updateLightboxContent();
        }

        // Image précédente
        function previousImage(event) {
            event.stopPropagation();
            currentLightboxIndex = (currentLightboxIndex - 1 + galleryImages.length) % galleryImages.length;
            updateLightboxContent();
        }

        // Mettre à jour le contenu de la lightbox
        function updateLightboxContent() {
            if (galleryImages.length === 0) return;

            const image = galleryImages[currentLightboxIndex];
            const isDish = image.is_dish || false;
            const imageUrl = isDish ?
                '<?= DISHES_UPLOAD_URL ?>' + image.filename :
                '<?= UPLOAD_URL ?>' + image.filename;

            document.getElementById('lightbox-img').src = imageUrl;
            document.getElementById('lightbox-img').alt = image.title || 'Sans titre';
            document.getElementById('lightbox-caption').textContent = image.title || 'Sans titre';
            document.getElementById('lightboxCounter').textContent = `${currentLightboxIndex + 1} / ${galleryImages.length}`;
        }

        // Gestion des notifications
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = `mb-6 p-4 rounded-xl flex items-center gap-3 ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            notification.innerHTML = `
                <div class="bg-${type === 'success' ? 'green' : 'red'}-100 p-2 rounded-full">
                    <i class="${icon} text-${type === 'success' ? 'green' : 'red'}-600"></i>
                </div>
                <span>${message}</span>
            `;
            notification.classList.remove('hidden');
            
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 5000);
        }

        // Modal d'upload
        function openUploadModal() {
            updateCategorySelects();
            document.getElementById('uploadModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadForm').reset();
        }

        // Modal d'édition
        function openEditModal(id, title, category) {
            updateCategorySelects();
            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editCategory').value = category;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Modal de gestion des catégories
        function openCategoryModal() {
            loadCategoryList();
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
            document.getElementById('newCategoryName').value = '';
        }

        // Mettre à jour les selects de catégories (exclure "plats")
        function updateCategorySelects() {
            const selects = ['uploadCategorySelect', 'editCategory'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                const currentValue = select.value;
                select.innerHTML = '<option value="">Choisir une catégorie...</option>';
                
                currentCategories.forEach(cat => {
                    if (cat.toLowerCase() !== 'plats') { // Exclure "plats"
                        const option = new Option(cat.charAt(0).toUpperCase() + cat.slice(1), cat);
                        select.add(option);
                    }
                });
                
                // Restaurer la valeur sélectionnée si elle existe encore
                if (currentCategories.includes(currentValue) && currentValue.toLowerCase() !== 'plats') {
                    select.value = currentValue;
                }
            });
        }

        // Charger la liste des catégories
        function loadCategoryList() {
            fetch(window.location.href, {
                method: 'POST',
                body: new URLSearchParams({ action: 'get_categories' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentCategories = data.categories;
                    displayCategoryList(data.categories);
                }
            })
            .catch(error => console.error('Erreur:', error));
        }

        // Afficher la liste des catégories
        function displayCategoryList(categories) {
            const container = document.getElementById('categoryList');
            container.innerHTML = '';

            if (categories.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center p-4">Aucune catégorie. Ajoutez-en une ci-dessus.</div>';
                return;
            }

            categories.forEach(cat => {
                const div = document.createElement('div');
                div.className = 'flex justify-between items-center p-2 border-b';
                
                if (cat.toLowerCase() === 'plats') {
                    div.innerHTML = `
                        <span>${cat.charAt(0).toUpperCase() + cat.slice(1)} <em class="text-blue-600 text-xs">(automatique)</em></span>
                        <span class="text-gray-400 text-sm">Gérée automatiquement</span>
                    `;
                } else {
                    div.innerHTML = `
                        <span>${cat.charAt(0).toUpperCase() + cat.slice(1)}</span>
                        <button onclick="deleteCategory('${cat}')" class="text-red-600 hover:text-red-800 text-sm">Supprimer</button>
                    `;
                }
                container.appendChild(div);
            });
        }

        // Ajouter une catégorie
        function addCategory() {
            const name = document.getElementById('newCategoryName').value.trim();
            if (!name) {
                showNotification('Veuillez saisir un nom de catégorie.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_category');
            formData.append('name', name);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    document.getElementById('newCategoryName').value = '';
                    loadCategoryList();
                    updateCategorySelects();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de l\'ajout.', 'error');
                console.error(error);
            });
        }

        // Supprimer une catégorie
        function deleteCategory(name) {
            if (name.toLowerCase() === 'plats') {
                showNotification('La catégorie "plats" ne peut pas être supprimée.', 'error');
                return;
            }

            if (!confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${name}" ?\n\nLes images de cette catégorie seront déplacées vers "Sans catégorie".`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('name', name);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    loadCategoryList();
                    updateCategorySelects();
                    
                    // Mettre à jour les images affichées si nécessaire
                    if (data.movedImages > 0) {
                        updateImagesCategory(name, 'Sans catégorie');
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la suppression.', 'error');
                console.error(error);
            });
        }

        // Mettre à jour la catégorie des images affichées
        function updateImagesCategory(oldCategory, newCategory) {
            const cards = document.querySelectorAll('.gallery-item');
            cards.forEach(card => {
                const categorySpan = card.querySelector('.gallery-item-category');
                if (categorySpan && categorySpan.textContent === oldCategory) {
                    categorySpan.textContent = newCategory;
                }
            });
        }

        // Gestion de l'upload
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    closeUploadModal();
                    addImageToGrid(data.image);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de l\'upload.', 'error');
                console.error(error);
            });
        });

        // Gestion de la modification
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    closeEditModal();
                    updateImageInGrid(data.image);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la modification.', 'error');
                console.error(error);
            });
        });

        // Fonction pour afficher le modal de confirmation de suppression
        function confirmDeleteImage(id, title) {
            imageToDelete = id;

            const modal = document.getElementById('deleteImageModal');
            document.getElementById('deleteImageInfo').textContent = title || 'Sans titre';

            // S'assurer que le bouton est dans son état normal
            const confirmBtn = document.getElementById('confirmDeleteImageBtn');
            confirmBtn.innerHTML = 'Supprimer';
            confirmBtn.disabled = false;

            modal.classList.remove('hidden');
        }

        // Fonction pour fermer le modal de suppression
        function closeDeleteImageModal() {
            const modal = document.getElementById('deleteImageModal');
            modal.classList.add('hidden');

            // Réinitialiser
            imageToDelete = null;
            document.getElementById('deleteImageInfo').textContent = '';

            const confirmBtn = document.getElementById('confirmDeleteImageBtn');
            confirmBtn.innerHTML = 'Supprimer';
            confirmBtn.disabled = false;
        }

        // Fonction pour exécuter la suppression
        function executeDeleteImage() {
            if (!imageToDelete) return;

            const confirmBtn = document.getElementById('confirmDeleteImageBtn');
            const originalText = confirmBtn.innerHTML;

            // Animation de chargement
            confirmBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Suppression...
            `;
            confirmBtn.disabled = true;

            const imageId = imageToDelete;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', imageId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    removeImageFromGrid(data.id);
                    closeDeleteImageModal();
                } else {
                    showNotification(data.message, 'error');
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la suppression.', 'error');
                console.error(error);
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }

        // Fonctions de mise à jour de la grille
        function addImageToGrid(image) {
            const grid = document.getElementById('gallery-grid');
            const imageCard = createImageCard(image);
            grid.insertAdjacentHTML('afterbegin', imageCard);
            
            // Mettre à jour le tableau des images pour la lightbox
            galleryImages.unshift({
                ...image,
                is_dish: false
            });
            
            // Réinitialiser les écouteurs d'événements pour la lightbox
            initLightbox();
        }

        function updateImageInGrid(image) {
            const card = document.querySelector(`[data-id="${image.id}"]`);
            if (card) {
                // Mettre à jour le titre
                const titleElement = card.querySelector('.gallery-card-title');
                if (titleElement) {
                    titleElement.textContent = image.title || 'Sans titre';
                }

                // Mettre à jour la catégorie dans le badge
                const badgeElement = card.querySelector('.gallery-card-badge');
                if (badgeElement) {
                    badgeElement.innerHTML = `<i class="fas fa-tag"></i> ${image.category}`;
                }

                // Mettre à jour la catégorie dans les infos
                const categoryElement = card.querySelector('.gallery-card-category');
                if (categoryElement) {
                    categoryElement.innerHTML = `<i class="fas fa-folder"></i> ${image.category}`;
                }

                // Mettre à jour le tableau des images pour la lightbox
                const index = galleryImages.findIndex(img => img.id === image.id);
                if (index !== -1) {
                    galleryImages[index].title = image.title;
                    galleryImages[index].category = image.category;
                }
            }
        }

        function removeImageFromGrid(id) {
            const card = document.querySelector(`[data-id="${id}"]`);
            if (card) {
                card.style.transform = 'scale(0)';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
                
                // Mettre à jour le tableau des images pour la lightbox
                const index = galleryImages.findIndex(img => img.id === id);
                if (index !== -1) {
                    galleryImages.splice(index, 1);
                }
            }
        }

        function createImageCard(image) {
            const title = image.title ? image.title : 'Sans titre';
            const imageUrl = '../uploads/gallery/' + image.filename;
            const index = galleryImages.length;

            return `
                <div class="gallery-card" data-id="${image.id}" data-is-dish="false" data-index="${index}">
                    <div class="gallery-card-image-wrapper" onclick="openLightbox(${index})">
                        <img src="${imageUrl}" alt="${title}" class="gallery-card-image" loading="lazy">
                        <span class="gallery-card-badge badge-category">
                            <i class="fas fa-tag"></i>
                            ${image.category}
                        </span>
                    </div>
                    <div class="gallery-card-content">
                        <h3 class="gallery-card-title">${title}</h3>
                        <div class="gallery-card-info">
                            <span class="gallery-card-category">
                                <i class="fas fa-folder"></i>
                                ${image.category}
                            </span>
                            <span class="gallery-card-date">
                                <i class="fas fa-clock"></i>
                                Nouveau
                            </span>
                        </div>
                        <div class="gallery-card-actions">
                            <button onclick="openLightbox(${index})" class="action-btn btn-view">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            <button onclick="event.stopPropagation(); openEditModal('${image.id}', '${title.replace(/'/g, "\\'")}', '${image.category}')"
                                    class="action-btn btn-edit">
                                <i class="fas fa-edit"></i>
                                Modifier
                            </button>
                            <button onclick="event.stopPropagation(); confirmDeleteImage('${image.id}', '${title.replace(/'/g, "\\'")}')"
                                    class="action-btn btn-delete">
                                <i class="fas fa-trash"></i>
                                Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            if (e.target.id === 'uploadModal') {
                closeUploadModal();
            }
            if (e.target.id === 'editModal') {
                closeEditModal();
            }
            if (e.target.id === 'categoryModal') {
                closeCategoryModal();
            }
        });

        // Gestion de la touche Entrée pour ajouter une catégorie
        document.getElementById('newCategoryName').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addCategory();
            }
        });

        // Initialiser la lightbox au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            initLightbox();
        });
    </script>
</body>
</html>