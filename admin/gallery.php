<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php'); 
    exit;
}

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
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Galerie — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="p-6 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Galerie d'images</h1>
            <div class="flex gap-2">
                <button onclick="openCategoryModal()" class="bg-purple-600 text-white px-3 py-2 rounded hover:bg-purple-700">
                    Gérer les catégories
                </button>
                <button onclick="openUploadModal()" class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700">
                    Ajouter une image
                </button>
            </div>
        </div>

        <!-- Messages de notification -->
        <div id="notification" class="hidden mb-4 p-3 rounded"></div>

        <!-- Info sur la catégorie plats -->
        <div class="bg-blue-50 border border-blue-200 text-blue-700 p-3 rounded mb-4">
            <p><strong>Note :</strong> La catégorie "plats" affiche automatiquement les plats depuis votre gestion des plats. 
            Pour modifier ou supprimer ces images, utilisez la page de gestion des plats.</p>
        </div>

        <!-- Grille des images -->
        <div id="gallery-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach($allImages as $img): ?>
                <?php 
                $isDish = isset($img['is_dish']) && $img['is_dish'];
                $imageUrl = $isDish ? DISHES_UPLOAD_URL . htmlspecialchars($img['filename']) : UPLOAD_URL . htmlspecialchars($img['filename']);
                $cardClass = $isDish ? 'bg-blue-50 border border-blue-200' : 'bg-white';
                ?>
                <div class="<?=$cardClass?> rounded shadow p-2 image-card" data-id="<?=$img['id']?>" data-is-dish="<?=$isDish ? 'true' : 'false'?>">
                    <img src="<?=$imageUrl?>" alt="" class="w-full h-48 object-cover rounded mb-2">
                    <div class="text-sm mb-2">
                        <span class="font-medium">Titre:</span> 
                        <span class="image-title"><?=htmlspecialchars($img['title'] ?: '-')?></span>
                        <?php if ($isDish): ?>
                            <span class="ml-2 text-xs bg-blue-600 text-white px-2 py-1 rounded">Plat</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-600 mb-2">
                        <span class="font-medium">Catégorie:</span> 
                        <span class="image-category font-semibold"><?=htmlspecialchars($img['category'])?></span>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($isDish): ?>
                            <button onclick="alert('Pour modifier ce plat, utilisez la page de gestion des plats.')" 
                                    class="flex-1 text-center border border-blue-500 text-blue-500 p-2 rounded hover:bg-blue-50">
                                Voir dans gestion plats
                            </button>
                        <?php else: ?>
                            <button onclick="openEditModal('<?=$img['id']?>', '<?=htmlspecialchars($img['title'], ENT_QUOTES)?>', '<?=htmlspecialchars($img['category'])?>')" 
                                    class="flex-1 text-center border border-blue-500 text-blue-500 p-2 rounded hover:bg-blue-50">
                                Modifier
                            </button>
                            <button onclick="deleteImage('<?=$img['id']?>')" 
                                    class="bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700">
                                Supprimer
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
                                <option value="<?=htmlspecialchars($cat)?>"><?=ucfirst(htmlspecialchars($cat))?></option>
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
                                <option value="<?=htmlspecialchars($cat)?>"><?=ucfirst(htmlspecialchars($cat))?></option>
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
        let currentCategories = <?=json_encode($allCategories)?>;

        // Gestion des notifications
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = `mb-4 p-3 rounded ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`;
            notification.textContent = message;
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
            const cards = document.querySelectorAll('.image-card');
            cards.forEach(card => {
                const categorySpan = card.querySelector('.image-category');
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

        // Gestion de la suppression
        function deleteImage(id) {
            // Vérifier si c'est un plat
            const card = document.querySelector(`[data-id="${id}"]`);
            if (card && card.dataset.isDish === 'true') {
                showNotification('Les images de plats ne peuvent être supprimées que depuis la gestion des plats.', 'error');
                return;
            }

            if (!confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    removeImageFromGrid(data.id);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la suppression.', 'error');
                console.error(error);
            });
        }

        // Fonctions de mise à jour de la grille
        function addImageToGrid(image) {
            const grid = document.getElementById('gallery-grid');
            const imageCard = createImageCard(image);
            grid.insertAdjacentHTML('afterbegin', imageCard);
        }

        function updateImageInGrid(image) {
            const card = document.querySelector(`[data-id="${image.id}"]`);
            if (card) {
                card.querySelector('.image-title').textContent = image.title || '-';
                card.querySelector('.image-category').textContent = image.category;
                
                // Mettre à jour les boutons
                const editButton = card.querySelector('button[onclick^="openEditModal"]');
                if (editButton) {
                    editButton.setAttribute('onclick', `openEditModal('${image.id}', '${image.title.replace(/'/g, "\\'")}', '${image.category}')`);
                }
            }
        }

        function removeImageFromGrid(id) {
            const card = document.querySelector(`[data-id="${id}"]`);
            if (card) {
                card.remove();
            }
        }

        function createImageCard(image) {
            const title = image.title ? image.title : '-';
            const imageUrl = '../uploads/gallery/' + image.filename;
            
            return `
                <div class="bg-white rounded shadow p-2 image-card" data-id="${image.id}" data-is-dish="false">
                    <img src="${imageUrl}" alt="" class="w-full h-48 object-cover rounded mb-2">
                    <div class="text-sm mb-2">
                        <span class="font-medium">Titre:</span> 
                        <span class="image-title">${title}</span>
                    </div>
                    <div class="text-xs text-gray-600 mb-2">
                        <span class="font-medium">Catégorie:</span> 
                        <span class="image-category font-semibold">${image.category}</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openEditModal('${image.id}', '${title.replace(/'/g, "\\'")}', '${image.category}')" 
                                class="flex-1 text-center border border-blue-500 text-blue-500 p-2 rounded hover:bg-blue-50">
                            Modifier
                        </button>
                        <button onclick="deleteImage('${image.id}')" 
                                class="bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700">
                            Supprimer
                        </button>
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
    </script>
</body>
</html>