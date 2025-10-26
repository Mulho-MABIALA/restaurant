<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Configuration pagination
$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Configuration recherche et filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Messages
$message = '';
$message_type = '';

// GESTION DES CATÉGORIES (CRUD) - À traiter en premier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_category':
            $nom = trim($_POST['category_name']);
            $couleur = $_POST['category_color'] ?? '#3B82F6';
            $description = trim($_POST['category_description'] ?? '');
            
            if (!empty($nom)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO procedure_categories (nom, couleur, description) VALUES (?, ?, ?)");
                    $stmt->execute([$nom, $couleur, $description]);
                    $message = "Catégorie ajoutée avec succès.";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = "Erreur lors de l'ajout : " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'update_category':
            $id = intval($_POST['category_id']);
            $nom = trim($_POST['category_name']);
            $couleur = $_POST['category_color'];
            $description = trim($_POST['category_description'] ?? '');
            
            try {
                $stmt = $conn->prepare("UPDATE procedure_categories SET nom = ?, couleur = ?, description = ? WHERE id = ?");
                $stmt->execute([$nom, $couleur, $description, $id]);
                $message = "Catégorie mise à jour.";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour : " . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'delete_category':
            $id = intval($_POST['category_id']);
            try {
                // Vérifier si la catégorie est utilisée
                $stmt = $conn->prepare("SELECT COUNT(*) FROM procedures WHERE categorie = (SELECT nom FROM procedure_categories WHERE id = ?)");
                $stmt->execute([$id]);
                $usage_count = $stmt->fetchColumn();
                
                if ($usage_count > 0) {
                    $message = "Impossible de supprimer : catégorie utilisée par $usage_count procédure(s).";
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE procedure_categories SET active = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Catégorie supprimée.";
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                $message = "Erreur lors de la suppression : " . $e->getMessage();
                $message_type = 'error';
            }
            break;
    }
    
    // Redirection pour éviter la resoumission
    header("Location: " . $_SERVER['PHP_SELF'] . "?category_action=done");
    exit;
}

// SUPPRESSION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $conn->beginTransaction();

        // Récupérer titre et fichier
        $stmt = $conn->prepare("SELECT titre, fichier_url FROM procedures WHERE id = ?");
        $stmt->execute([$id]);
        $proc = $stmt->fetch(PDO::FETCH_ASSOC);
        $titre = $proc['titre'] ?? '';
        $file = $proc['fichier_url'] ?? null;

        // Supprimer le fichier si existe
        if ($file && file_exists('../../' . $file)) {
            unlink('../../' . $file);
        }

        // Archiver au lieu de supprimer
        $stmt = $conn->prepare("UPDATE procedures SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        // Log d'activité
        $stmt = $conn->prepare("INSERT INTO procedure_logs (procedure_id, admin_id, action, details, created_at) VALUES (?, ?, 'delete', ?, NOW())");
        $stmt->execute([$id, $_SESSION['admin_id'], "Suppression de: " . $titre]);

        $conn->commit();
        $message = "Procédure supprimée avec succès.";
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erreur lors de la suppression: " . $e->getMessage();
        $message_type = 'error';
    }
}

// DUPLICATION
if (isset($_GET['duplicate'])) {
    $id = intval($_GET['duplicate']);
    try {
        $stmt = $conn->prepare("SELECT * FROM procedures WHERE id = ?");
        $stmt->execute([$id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($original) {
            $stmt = $conn->prepare("INSERT INTO procedures (titre, categorie, contenu, fichier_url, status, version, created_at) VALUES (?, ?, ?, ?, 'draft', 1, NOW())");
            $stmt->execute([
                $original['titre'] . ' (Copie)',
                $original['categorie'],
                $original['contenu'],
                $original['fichier_url']
            ]);
            $message = "Procédure dupliquée avec succès.";
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = "Erreur lors de la duplication: " . $e->getMessage();
        $message_type = 'error';
    }
}

// MODIFICATION
$edit_proc = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM procedures WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$id]);
    $edit_proc = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les catégories AVANT leur utilisation
$categories_query = $conn->query("SELECT * FROM procedure_categories WHERE active = 1 ORDER BY nom");
$available_categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

// TRAITEMENT DU FORMULAIRE DE PROCÉDURE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $titre = trim($_POST['titre']);
    $categorie = trim($_POST['categorie']);
    $contenu = trim($_POST['contenu']);
    $status = $_POST['status'] ?? 'draft';
    $fichier_url = $edit_proc['fichier_url'] ?? null;
    $errors = [];

    // Validation
    if (strlen($titre) < 3) $errors[] = "Le titre doit contenir au moins 3 caractères.";
    if (empty($categorie)) $errors[] = "La catégorie est obligatoire.";

    // Gestion fichier
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xlsx'];
        $max_size = 10 * 1024 * 1024; // 10MB
        $upload_dir = '../../uploads/procedures/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if ($_FILES['fichier']['size'] > $max_size) $errors[] = "Le fichier est trop volumineux (max 10MB).";
        $file_ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_ext)) $errors[] = "Type de fichier non autorisé.";

        if (empty($errors)) {
            $new_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $file_path)) {
                if ($edit_proc && $edit_proc['fichier_url'] && file_exists('../../' . $edit_proc['fichier_url'])) {
                    unlink('../../' . $edit_proc['fichier_url']);
                }
                $fichier_url = 'uploads/procedures/' . $new_name;
            } else {
                $errors[] = "Erreur lors du téléchargement du fichier.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            if ($edit_proc) {
                $new_version = $edit_proc['version'] + 1;
                $stmt = $conn->prepare("UPDATE procedures SET titre = ?, categorie = ?, contenu = ?, fichier_url = ?, status = ?, version = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$titre, $categorie, $contenu, $fichier_url, $status, $new_version, $edit_proc['id']]);

                $stmt = $conn->prepare("INSERT INTO procedure_logs (procedure_id, admin_id, action, details, created_at) VALUES (?, ?, 'update', ?, NOW())");
                $stmt->execute([$edit_proc['id'], $_SESSION['admin_id'], "Mise à jour v." . $new_version]);

                $message = "Procédure mise à jour avec succès (v." . $new_version . ").";
            } else {
                $stmt = $conn->prepare("INSERT INTO procedures (titre, categorie, contenu, fichier_url, status, version, created_by, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
                $stmt->execute([$titre, $categorie, $contenu, $fichier_url, $status, $_SESSION['admin_id']]);

                $procedure_id = $conn->lastInsertId();
                $stmt = $conn->prepare("INSERT INTO procedure_logs (procedure_id, admin_id, action, details, created_at) VALUES (?, ?, 'create', ?, NOW())");
                $stmt->execute([$procedure_id, $_SESSION['admin_id'], "Création de la procédure"]);

                $message = "Procédure ajoutée avec succès.";
            }

            $conn->commit();
            $message_type = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Erreur: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// EXPORT CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="procedures_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Titre', 'Catégorie', 'Status', 'Version', 'Créé le']);

    $stmt = $conn->query("SELECT id, titre, categorie, status, version, created_at FROM procedures WHERE status != 'deleted' ORDER BY created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Construction de la requête avec filtres
$where_conditions = ["p.status != 'deleted'"];
$params = [];

if ($search) {
    $where_conditions[] = "(p.titre LIKE ? OR p.contenu LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "p.categorie = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Total pour pagination
$count_query = "SELECT COUNT(*) FROM procedures p WHERE " . $where_clause;
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les procédures avec pagination
$allowed_sorts = ['titre', 'categorie', 'status', 'version', 'created_at', 'updated_at'];
if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'created_at';

$query = "SELECT p.*, a.username as created_by_name 
          FROM procedures p 
          LEFT JOIN admin a ON p.created_by = a.id 
          WHERE $where_clause 
          ORDER BY p.$sort_by $sort_order 
          LIMIT $items_per_page OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories distinctes pour le filtre
$used_categories = $conn->query("SELECT DISTINCT p.categorie 
                                FROM procedures p
                                WHERE p.status != 'deleted' 
                                ORDER BY p.categorie")
                        ->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN p.status = 'published' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN p.status = 'archived' THEN 1 ELSE 0 END) as archived
    FROM procedures p WHERE p.status != 'deleted'")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des procédures - Avancée</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .drag-over { border-color: #3b82f6; background-color: #eff6ff; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal { display: none; }
        .modal.active { display: flex; }
        
        .stats-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white shadow-sm border-b">
    <div class="container mx-auto px-6 py-4">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-book text-blue-600 mr-2"></i>
                Gestion des Procédures
            </h1>
            <div class="flex gap-3">
                <button onclick="exportToPDF()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-file-pdf mr-2"></i>Exporter PDF
                </button>
                <button onclick="showImportModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-upload mr-2"></i>Importer
                </button>
                <button onclick="showCategoryModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-tags mr-2"></i>Gérer les catégories
                </button>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-6 py-8">

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stats-card bg-white rounded-xl shadow-sm p-6 border-2 border-blue-200 hover:border-blue-400 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total</p>
                    <p class="text-3xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="stats-card bg-white rounded-xl shadow-sm p-6 border-2 border-green-200 hover:border-green-400 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Publiées</p>
                    <p class="text-3xl font-bold text-green-600"><?= $stats['published'] ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="stats-card bg-white rounded-xl shadow-sm p-6 border-2 border-yellow-200 hover:border-yellow-400 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Brouillons</p>
                    <p class="text-3xl font-bold text-yellow-600"><?= $stats['draft'] ?></p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="fas fa-edit text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="stats-card bg-white rounded-xl shadow-sm p-6 border-2 border-gray-200 hover:border-gray-400 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Archivées</p>
                    <p class="text-3xl font-bold text-gray-600"><?= $stats['archived'] ?></p>
                </div>
                <div class="bg-gray-100 p-3 rounded-full">
                    <i class="fas fa-archive text-gray-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
            <div class="flex items-center">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
                <?= $message ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filtres et recherche -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
        <div class="flex flex-wrap gap-4 items-center justify-between">
            <div class="flex flex-wrap gap-4 items-center">
                <!-- Recherche -->
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Rechercher..." 
                           class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?= htmlspecialchars($search) ?>">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>

             <select id="categoryFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
    <option value="">Toutes les catégories</option>
    <?php foreach ($available_categories as $cat): ?>
        <?php
        // Vérifier si cette catégorie est utilisée
        $is_used = in_array($cat['nom'], $used_categories);
        $count = 0;
        if ($is_used) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM procedures WHERE categorie = ? AND status != 'deleted'");
            $stmt->execute([$cat['nom']]);
            $count = $stmt->fetchColumn();
        }
        ?>
        <option value="<?= htmlspecialchars($cat['nom']) ?>" <?= $category_filter === $cat['nom'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nom']) ?>
            <?php if ($is_used): ?>
                (<?= $count ?>)
            <?php endif; ?>
        </option>
    <?php endforeach; ?>
</select>

                <!-- Tri -->
                <select id="sortSelect" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="created_at-DESC" <?= $sort_by === 'created_at' && $sort_order === 'DESC' ? 'selected' : '' ?>>Plus récent</option>
                    <option value="created_at-ASC" <?= $sort_by === 'created_at' && $sort_order === 'ASC' ? 'selected' : '' ?>>Plus ancien</option>
                    <option value="titre-ASC" <?= $sort_by === 'titre' && $sort_order === 'ASC' ? 'selected' : '' ?>>Titre A-Z</option>
                    <option value="titre-DESC" <?= $sort_by === 'titre' && $sort_order === 'DESC' ? 'selected' : '' ?>>Titre Z-A</option>
                    <option value="categorie-ASC" <?= $sort_by === 'categorie' && $sort_order === 'ASC' ? 'selected' : '' ?>>Catégorie A-Z</option>
                </select>

                <button onclick="applyFilters()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
                <button onclick="resetFilters()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-times mr-2"></i>Reset
                </button>
            </div>

            <button onclick="toggleForm()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>Nouvelle procédure
            </button>
        </div>
    </div>

    <!-- Formulaire ajout/modification -->
    <div id="procedureForm" class="bg-white rounded-xl shadow-sm p-6 mb-8 <?= $edit_proc ? '' : 'hidden' ?>">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">
                <i class="fas <?= $edit_proc ? 'fa-edit' : 'fa-plus' ?> mr-2"></i>
                <?= $edit_proc ? 'Modifier la procédure' : 'Nouvelle procédure' ?>
            </h2>
            <button onclick="toggleForm()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="mainForm" action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-heading mr-1"></i>Titre *
                    </label>
                    <input type="text" name="titre" id="titre" required 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?= htmlspecialchars($edit_proc['titre'] ?? '') ?>"
                           minlength="3" maxlength="255">
                    <div id="titreError" class="text-red-500 text-sm mt-1 hidden"></div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tags mr-1"></i>Catégorie *
                    </label>
                    <select name="categorie" id="categorie" required 
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Sélectionner une catégorie</option>
                        <?php foreach ($available_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['nom']) ?>" 
                                    <?= ($edit_proc['categorie'] ?? '') === $cat['nom'] ? 'selected' : '' ?>
                                    data-color="<?= htmlspecialchars($cat['couleur']) ?>">
                                <?= htmlspecialchars($cat['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="categorieError" class="text-red-500 text-sm mt-1 hidden"></div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-align-left mr-1"></i>Contenu
                </label>
                <textarea name="contenu" id="contenu" rows="6" 
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($edit_proc['contenu'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-paperclip mr-1"></i>Fichier joint (optionnel)
                </label>
                <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition cursor-pointer">
                    <input type="file" name="fichier" id="fichier" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xlsx">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600">Glissez-déposez un fichier ici ou cliquez pour parcourir</p>
                    <p class="text-sm text-gray-500 mt-2">Formats acceptés: PDF, DOC, DOCX, JPG, PNG, TXT, XLSX (max 10MB)</p>
                </div>
                <div id="fileInfo" class="mt-3 hidden"></div>
                <?php if ($edit_proc && $edit_proc['fichier_url']): ?>
                    <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-gray-600">Fichier actuel :</p>
                        <a href="../../<?= htmlspecialchars($edit_proc['fichier_url']) ?>" target="_blank" 
                           class="text-blue-600 hover:underline flex items-center">
                            <i class="fas fa-external-link-alt mr-1"></i>
                            <?= basename($edit_proc['fichier_url']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-flag mr-1"></i>Statut
                    </label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="draft" <?= ($edit_proc['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="published" <?= ($edit_proc['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publié</option>
                        <option value="archived" <?= ($edit_proc['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archivé</option>
                    </select>
                </div>

                <?php if ($edit_proc): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-code-branch mr-1"></i>Version actuelle
                    </label>
                    <input type="text" value="v<?= $edit_proc['version'] ?>" readonly 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50">
                </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="toggleForm()" 
                        class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Annuler
                </button>
                <button type="submit" id="submitBtn"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-2"></i>
                    <?= $edit_proc ? 'Mettre à jour' : 'Enregistrer' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Liste des procédures -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-list mr-2"></i>Liste des procédures
                <span class="text-sm font-normal text-gray-500">(<?= $total_items ?> résultat<?= $total_items > 1 ? 's' : '' ?>)</span>
            </h2>
        </div>

        <?php if (empty($procedures)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">Aucune procédure trouvée</p>
                <p class="text-gray-400 mt-2">Commencez par créer votre première procédure</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="#" onclick="sortBy('titre')" class="hover:text-gray-700">
                                    Titre <i class="fas fa-sort ml-1"></i>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="#" onclick="sortBy('categorie')" class="hover:text-gray-700">
                                    Catégorie <i class="fas fa-sort ml-1"></i>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="#" onclick="sortBy('status')" class="hover:text-gray-700">
                                    Statut <i class="fas fa-sort ml-1"></i>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="#" onclick="sortBy('version')" class="hover:text-gray-700">
                                    Version <i class="fas fa-sort ml-1"></i>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fichier</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="#" onclick="sortBy('created_at')" class="hover:text-gray-700">
                                    Créé le <i class="fas fa-sort ml-1"></i>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé par</th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($procedures as $proc): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <!-- Titre avec aperçu du contenu -->
                                <td class="px-6 py-4">
                                    <div class="flex items-start">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($proc['titre']) ?>
                                            </div>
                                            <?php if ($proc['contenu']): ?>
                                                <div class="text-sm text-gray-500 mt-1">
                                                    <?= htmlspecialchars(substr(strip_tags($proc['contenu']), 0, 100)) ?>...
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Catégorie -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($proc['categorie']) ?>
                                    </span>
                                </td>
                                
                                <!-- Statut -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'draft' => 'bg-yellow-100 text-yellow-800',
                                        'published' => 'bg-green-100 text-green-800',
                                        'archived' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $status_labels = [
                                        'draft' => 'Brouillon',
                                        'published' => 'Publié',
                                        'archived' => 'Archivé'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_colors[$proc['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                                        <?= $status_labels[$proc['status']] ?? $proc['status'] ?>
                                    </span>
                                </td>
                                
                                <!-- Version -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-gray-100 text-gray-800">
                                        v<?= $proc['version'] ?>
                                    </span>
                                </td>
                                
                                <!-- Fichier -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($proc['fichier_url']): ?>
                                        <a href="../../<?= htmlspecialchars($proc['fichier_url']) ?>" target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center">
                                            <?php
                                            $ext = strtolower(pathinfo($proc['fichier_url'], PATHINFO_EXTENSION));
                                            $icons = [
                                                'pdf' => 'fa-file-pdf text-red-500',
                                                'doc' => 'fa-file-word text-blue-500',
                                                'docx' => 'fa-file-word text-blue-500',
                                                'jpg' => 'fa-file-image text-green-500',
                                                'jpeg' => 'fa-file-image text-green-500',
                                                'png' => 'fa-file-image text-green-500',
                                                'txt' => 'fa-file-alt text-gray-500',
                                                'xlsx' => 'fa-file-excel text-green-500'
                                            ];
                                            ?>
                                            <i class="fas <?= $icons[$ext] ?? 'fa-file text-gray-500' ?> mr-1"></i>
                                            <span class="truncate max-w-24"><?= basename($proc['fichier_url']) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Aucun fichier</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Date de création -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex flex-col">
                                        <span><?= date('d/m/Y', strtotime($proc['created_at'])) ?></span>
                                        <span class="text-xs text-gray-400"><?= date('H:i', strtotime($proc['created_at'])) ?></span>
                                    </div>
                                </td>
                                
                                <!-- Créé par -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-700">
                                                    <?= strtoupper(substr($proc['created_by_name'] ?? 'N/A', 0, 2)) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-2">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($proc['created_by_name'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Bouton Aperçu -->
                                        <button onclick="previewProcedure(<?= $proc['id'] ?>)" 
                                                class="text-indigo-600 hover:text-indigo-800 p-1 rounded" 
                                                title="Aperçu">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Bouton Modifier -->
                                        <a href="?edit=<?= $proc['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 p-1 rounded" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Bouton Dupliquer -->
                                        <button onclick="duplicateProcedure(<?= $proc['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800 p-1 rounded" 
                                                title="Dupliquer">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        
                                        <!-- Bouton Supprimer -->
                                        <button onclick="confirmDelete(<?= $proc['id'] ?>, '<?= addslashes($proc['titre']) ?>')" 
                                                class="text-red-600 hover:text-red-800 p-1 rounded" 
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-6 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= $sort_by ?>&order=<?= strtolower($sort_order) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Précédent
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= $sort_by ?>&order=<?= strtolower($sort_order) ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Suivant
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Affichage <span class="font-medium"><?= $offset + 1 ?></span> à <span class="font-medium"><?= min($offset + $items_per_page, $total_items) ?></span>
                                sur <span class="font-medium"><?= $total_items ?></span> résultats
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= $sort_by ?>&order=<?= strtolower($sort_order) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= $sort_by ?>&order=<?= strtolower($sort_order) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= $sort_by ?>&order=<?= strtolower($sort_order) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de gestion des catégories -->
<div id="categoryModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
                <i class="fas fa-tags mr-2"></i>Gestion des catégories
            </h3>
            <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Formulaire d'ajout -->
        <form method="POST" class="mb-6 p-4 bg-gray-50 rounded-lg" id="categoryForm">
            <input type="hidden" name="action" value="add_category" id="categoryAction">
            <input type="hidden" name="category_id" value="" id="categoryId">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
                    <input type="text" name="category_name" id="categoryName" required 
                           class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                    <input type="color" name="category_color" id="categoryColor" value="#3B82F6" 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="flex items-end">
                    <button type="submit" id="categorySubmitBtn" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-1"></i>Ajouter
                    </button>
                </div>
            </div>
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="category_description" id="categoryDescription" rows="2" 
                          class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </form>
        
        <!-- Liste des catégories -->
        <div class="max-h-96 overflow-y-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Couleur</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usage</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($available_categories as $cat): ?>
                        <?php
                        // Compter l'usage de la catégorie
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM procedures WHERE categorie = ? AND status != 'deleted'");
                        $stmt->execute([$cat['nom']]);
                        $usage_count = $stmt->fetchColumn();
                        ?>
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium"><?= htmlspecialchars($cat['nom']) ?></span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded" style="background-color: <?= htmlspecialchars($cat['couleur']) ?>"></div>
                                    <span class="ml-2 text-sm text-gray-500"><?= htmlspecialchars($cat['couleur']) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?= htmlspecialchars(substr($cat['description'] ?? '', 0, 50)) ?>
                                <?= strlen($cat['description'] ?? '') > 50 ? '...' : '' ?>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <?= $usage_count ?> procédure<?= $usage_count > 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button onclick="editCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['nom']) ?>', '<?= $cat['couleur'] ?>', '<?= addslashes($cat['description'] ?? '') ?>')" 
                                        class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($usage_count == 0): ?>
                                    <button onclick="deleteCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['nom']) ?>')" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-4">Confirmer la suppression</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Êtes-vous sûr de vouloir supprimer la procédure "<span id="deleteTitle"></span>" ?
                    Cette action ne peut pas être annulée.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">
                    Supprimer définitivement
                </button>
                <button onclick="closeDeleteModal()" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let editor;
let deleteId = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeEditor();
    initializeDragDrop();
    initializeFormValidation();
});

// Initialiser l'éditeur WYSIWYG
function initializeEditor() {
    ClassicEditor
        .create(document.querySelector('#contenu'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
        })
        .then(editorInstance => {
            editor = editorInstance;
        })
        .catch(error => {
            console.error(error);
        });
}

// Initialiser le drag & drop
function initializeDragDrop() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fichier');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            displayFileInfo(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            displayFileInfo(e.target.files[0]);
        }
    });
}

// Afficher les informations du fichier
function displayFileInfo(file) {
    const fileInfo = document.getElementById('fileInfo');
    const maxSize = 10 * 1024 * 1024; // 10MB

    let html = `<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-file mr-2 text-gray-500"></i>
            <span class="text-sm font-medium">${file.name}</span>
            <span class="text-xs text-gray-500 ml-2">(${formatFileSize(file.size)})</span>
        </div>
        <button type="button" onclick="clearFile()" class="text-red-500 hover:text-red-700">
            <i class="fas fa-times"></i>
        </button>
    </div>`;

    if (file.size > maxSize) {
        html += `<div class="text-red-500 text-sm mt-1">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Fichier trop volumineux (max 10MB)
        </div>`;
    }

    fileInfo.innerHTML = html;
    fileInfo.classList.remove('hidden');
}

// Supprimer le fichier sélectionné
function clearFile() {
    document.getElementById('fichier').value = '';
    document.getElementById('fileInfo').classList.add('hidden');
}

// Formater la taille du fichier
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Validation du formulaire
function initializeFormValidation() {
    const form = document.getElementById('mainForm');
    const titreInput = document.getElementById('titre');
    const categorieSelect = document.getElementById('categorie');

    // Validation en temps réel du titre
    titreInput.addEventListener('input', function() {
        const titreError = document.getElementById('titreError');
        if (this.value.length < 3) {
            titreError.textContent = 'Le titre doit contenir au moins 3 caractères';
            titreError.classList.remove('hidden');
            this.classList.add('border-red-500');
        } else {
            titreError.classList.add('hidden');
            this.classList.remove('border-red-500');
        }
    });

    // Validation de la catégorie
    categorieSelect.addEventListener('change', function() {
        const categorieError = document.getElementById('categorieError');
        if (!this.value) {
            categorieError.textContent = 'Veuillez sélectionner une catégorie';
            categorieError.classList.remove('hidden');
            this.classList.add('border-red-500');
        } else {
            categorieError.classList.add('hidden');
            this.classList.remove('border-red-500');
        }
    });

    // Validation avant soumission
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Vérifier le titre
        if (titreInput.value.length < 3) {
            document.getElementById('titreError').classList.remove('hidden');
            titreInput.classList.add('border-red-500');
            isValid = false;
        }

        // Vérifier la catégorie
        if (!categorieSelect.value) {
            document.getElementById('categorieError').classList.remove('hidden');
            categorieSelect.classList.add('border-red-500');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            showAlert('Veuillez corriger les erreurs dans le formulaire', 'error');
        }
    });
}

// Gestion des formulaires et modales
function toggleForm() {
    const form = document.getElementById('procedureForm');
    const isHidden = form.classList.contains('hidden');
    
    if (isHidden) {
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth' });
        document.getElementById('titre').focus();
    } else {
        form.classList.add('hidden');
        // Reset du formulaire
        document.getElementById('mainForm').reset();
        if (editor) {
            editor.setData('');
        }
        clearFile();
        // Retirer les paramètres d'édition de l'URL
        const url = new URL(window.location);
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url);
    }
}

// Gestion des catégories
function showCategoryModal() {
    document.getElementById('categoryModal').classList.add('active');
    resetCategoryForm();
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
    resetCategoryForm();
}

function resetCategoryForm() {
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryAction').value = 'add_category';
    document.getElementById('categoryId').value = '';
    document.getElementById('categorySubmitBtn').innerHTML = '<i class="fas fa-plus mr-1"></i>Ajouter';
    document.getElementById('categoryColor').value = '#3B82F6';
}

function editCategory(id, nom, couleur, description) {
    document.getElementById('categoryAction').value = 'update_category';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = nom;
    document.getElementById('categoryColor').value = couleur;
    document.getElementById('categoryDescription').value = description;
    document.getElementById('categorySubmitBtn').innerHTML = '<i class="fas fa-save mr-1"></i>Modifier';
}

function deleteCategory(id, nom) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${nom}" ?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Gestion de la suppression des procédures

function confirmDelete(id, title) {
    deleteId = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteModal').classList.add('active');
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        // Afficher un indicateur de chargement
        const btn = document.getElementById('confirmDeleteBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Suppression...';
        btn.disabled = true;
        
        // Effectuer la suppression
        fetch(`?delete=${deleteId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                // Fermer la modal
                closeDeleteModal();
                showAlert('Procédure supprimée avec succès', 'success');
                
                // Actualiser la page après un court délai
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error('Erreur lors de la suppression');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur lors de la suppression', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    };
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    deleteId = null;
}

// Actions sur les procédures
function duplicateProcedure(id) {
    if (confirm('Voulez-vous dupliquer cette procédure ?')) {
        // Afficher un indicateur de chargement
        showAlert('Duplication en cours...', 'info');
        
        fetch(`?duplicate=${id}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                showAlert('Procédure dupliquée avec succès', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error('Erreur lors de la duplication');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur lors de la duplication', 'error');
        });
    }
}

function previewProcedure(id) {
    // Ouvrir dans une nouvelle fenêtre/onglet pour l'aperçu
    const previewWindow = window.open(`preview.php?id=${id}`, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
    
    // Vérifier si la fenêtre s'est ouverte correctement
    if (!previewWindow) {
        showAlert('Impossible d\'ouvrir l\'aperçu. Veuillez autoriser les pop-ups pour ce site.', 'error');
    }
}

// Filtres et tri
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const sortValue = document.getElementById('sortSelect').value;
    const [sortBy, sortOrder] = sortValue.split('-');
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('category', category);
    url.searchParams.set('sort', sortBy);
    url.searchParams.set('order', sortOrder.toLowerCase());
    url.searchParams.delete('page'); // Reset pagination
    
    window.location.href = url.toString();
}

function resetFilters() {
    const url = new URL(window.location);
    url.search = ''; // Supprimer tous les paramètres
    window.location.href = url.toString();
}

function sortBy(column) {
    const currentSort = new URL(window.location).searchParams.get('sort');
    const currentOrder = new URL(window.location).searchParams.get('order') || 'desc';
    
    let newOrder = 'asc';
    if (currentSort === column && currentOrder === 'asc') {
        newOrder = 'desc';
    }
    
    const url = new URL(window.location);
    url.searchParams.set('sort', column);
    url.searchParams.set('order', newOrder);
    url.searchParams.delete('page'); // Reset pagination
    
    window.location.href = url.toString();
}

// Recherche en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500); // Attendre 500ms après la fin de la saisie
    });
    
    // Recherche à l'appui sur Entrée
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            clearTimeout(searchTimeout);
            applyFilters();
        }
    });
});

// Export PDF
function exportToPDF() {
    showAlert('Export PDF en cours...', 'info');
    
    // Créer un formulaire temporaire pour l'export
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_pdf.php';
    form.target = '_blank';
    
    // Ajouter les filtres actuels
    const url = new URL(window.location);
    const search = url.searchParams.get('search') || '';
    const category = url.searchParams.get('category') || '';
    
    form.innerHTML = `
        <input type="hidden" name="search" value="${search}">
        <input type="hidden" name="category" value="${category}">
        <input type="hidden" name="export_type" value="pdf">
    `;
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
function refreshTable() {
    const url = new URL(window.location);
    url.searchParams.set('ajax', '1');
    
    fetch(url.toString())
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Mettre à jour uniquement le tableau et les statistiques
            const newTable = doc.querySelector('.bg-white.rounded-xl.shadow-sm.overflow-hidden');
            const currentTable = document.querySelector('.bg-white.rounded-xl.shadow-sm.overflow-hidden');
            
            if (newTable && currentTable) {
                currentTable.innerHTML = newTable.innerHTML;
            }
            
            // Mettre à jour les statistiques
            const newStats = doc.querySelectorAll('.stats-card');
            const currentStats = document.querySelectorAll('.stats-card');
            
            newStats.forEach((newStat, index) => {
                if (currentStats[index]) {
                    const newValue = newStat.querySelector('.text-3xl').textContent;
                    animateValue(currentStats[index].querySelector('.text-3xl'), 
                               parseInt(currentStats[index].querySelector('.text-3xl').textContent), 
                               parseInt(newValue), 500);
                }
            });
        })
        .catch(error => {
            console.error('Erreur lors de l\'actualisation:', error);
        });
}
// Modal d'import
function showImportModal() {
    // Créer la modal d'import dynamiquement
    const modal = document.createElement('div');
    modal.id = 'importModal';
    modal.className = 'modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 active';
    
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-upload mr-2"></i>Importer des procédures
                </h3>
                <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="importForm" method="POST" enctype="multipart/form-data" action="import.php">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fichier CSV ou Excel
                    </label>
                    <input type="file" name="import_file" accept=".csv,.xlsx,.xls" required
                           class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">
                        Formats acceptés: CSV, XLSX, XLS
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="update_existing" value="1" class="mr-2">
                        <span class="text-sm text-gray-700">Mettre à jour les procédures existantes</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeImportModal()" 
                            class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-upload mr-1"></i>Importer
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.remove();
    }
}

// Alertes et notifications
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm fade-in ${
        type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
        type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' :
        type === 'warning' ? 'bg-yellow-100 border border-yellow-400 text-yellow-700' :
        'bg-blue-100 border border-blue-400 text-blue-700'
    }`;
    
    const icon = type === 'success' ? 'fa-check-circle' :
                 type === 'error' ? 'fa-exclamation-triangle' :
                 type === 'warning' ? 'fa-exclamation-triangle' :
                 'fa-info-circle';
    
    alertDiv.innerHTML = `
        <div class="flex items-start">
            <i class="fas ${icon} mr-2 mt-0.5"></i>
            <div class="flex-1">
                ${message}
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-suppression après 5 secondes
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Statistiques en temps réel
function updateStats() {
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.stats-card').forEach((card, index) => {
                    const valueElement = card.querySelector('.text-3xl');
                    const values = [data.total, data.published, data.draft, data.archived];
                    animateValue(valueElement, parseInt(valueElement.textContent), values[index], 1000);
                });
            }
        })
        .catch(error => console.error('Erreur lors de la mise à jour des stats:', error));
}

// Animation des valeurs numériques
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N : Nouvelle procédure
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (document.getElementById('procedureForm').classList.contains('hidden')) {
            toggleForm();
        }
    }
    
    // Échap : Fermer les modales
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.active');
        modals.forEach(modal => modal.classList.remove('active'));
        
        // Fermer le formulaire si ouvert
        if (!document.getElementById('procedureForm').classList.contains('hidden')) {
            toggleForm();
        }
    }
    
    // Ctrl/Cmd + S : Sauvegarder si formulaire ouvert
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        if (!document.getElementById('procedureForm').classList.contains('hidden')) {
            e.preventDefault();
            document.getElementById('mainForm').dispatchEvent(new Event('submit', { bubbles: true }));
        }
    }
});

// Auto-sauvegarde (brouillon)
let autoSaveTimeout;
function autoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        const form = document.getElementById('mainForm');
        if (!form || document.getElementById('procedureForm').classList.contains('hidden')) {
            return;
        }
        
        const formData = new FormData(form);
        formData.append('auto_save', '1');
        
        fetch('auto_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Brouillon sauvegardé automatiquement', 'success');
            }
        })
        .catch(error => console.log('Auto-save error:', error));
    }, 30000); // Auto-save après 30 secondes d'inactivité
}

// Déclencher l'auto-save sur les changements
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['titre', 'categorie', 'contenu'];
    inputs.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', autoSave);
            element.addEventListener('change', autoSave);
        }
    });
    
    // Pour l'éditeur WYSIWYG
    if (editor) {
        editor.model.document.on('change:data', autoSave);
    }
});

// Gestion de la perte de connexion
window.addEventListener('online', () => showAlert('Connexion rétablie', 'success'));
window.addEventListener('offline', () => showAlert('Connexion perdue', 'warning'));

// Actualiser les stats périodiquement
setInterval(updateStats, 300000); // Toutes les 5 minutes

// Initialisation complète au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Système de gestion des procédures initialisé');
    
    // Vérifier s'il y a des messages à afficher
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('category_action')) {
        // Supprimer le paramètre de l'URL
        urlParams.delete('category_action');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }
});
</script>

</body>
</html>