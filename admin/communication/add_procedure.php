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
}

// MODIFICATION
$edit_proc = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM procedures WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$id]);
    $edit_proc = $stmt->fetch(PDO::FETCH_ASSOC);
}

// TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
           // header("Location: manage_procedures.php");
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

// Récupérer les catégories
$categories = $conn->query("SELECT DISTINCT p.categorie 
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
                <button onclick="exportData()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i>Exporter
                </button>
                <button onclick="showImportModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-upload mr-2"></i>Importer
                </button>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-6 py-8">

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
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
        <div class="bg-white rounded-xl shadow-sm p-6">
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
        <div class="bg-white rounded-xl shadow-sm p-6">
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
        <div class="bg-white rounded-xl shadow-sm p-6">
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

                <!-- Filtre catégorie -->
                <select id="categoryFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
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
                    <input type="text" name="categorie" id="categorie" required list="categories"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?= htmlspecialchars($edit_proc['categorie'] ?? '') ?>">
                    <datalist id="categories">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
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
                <?php if ($edit_proc): ?>
                <button type="button" onclick="previewChanges()" 
                        class="px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                    <i class="fas fa-eye mr-2"></i>Aperçu
                </button>
                <?php endif; ?>
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
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($proc['titre']) ?></div>
                                            <?php if ($proc['contenu']): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($proc['contenu'], 0, 100)) ?>...</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($proc['categorie']) ?>
                                    </span>
                                </td>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    v<?= $proc['version'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($proc['fichier_url']): ?>
                                        <a href="../../<?= htmlspecialchars($proc['fichier_url']) ?>" target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center">
                                            <i class="fas fa-paperclip mr-1"></i>
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
                                            echo '<i class="fas ' . ($icons[$ext] ?? 'fa-file text-gray-500') . ' mr-1"></i>';
                                            ?>
                                            Voir
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y à H:i', strtotime($proc['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($proc['created_by_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button onclick="viewHistory(<?= $proc['id'] ?>)" 
                                                class="text-gray-600 hover:text-gray-800" title="Historique">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <a href="?edit=<?= $proc['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="duplicateProcedure(<?= $proc['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800" title="Dupliquer">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $proc['id'] ?>, '<?= addslashes($proc['titre']) ?>')" 
                                                class="text-red-600 hover:text-red-800" title="Supprimer">
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

<!-- Modal d'historique -->
<div id="historyModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
                <i class="fas fa-history mr-2"></i>Historique des modifications
            </h3>
            <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="historyContent" class="max-h-96 overflow-y-auto">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<!-- Modal d'import -->
<div id="importModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
                <i class="fas fa-upload mr-2"></i>Importer des procédures
            </h3>
            <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="importForm" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Fichier CSV</label>
                <input type="file" name="import_file" accept=".csv" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Format: titre, categorie, contenu, status</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeImportModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Importer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'aperçu -->
<div id="previewModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
                <i class="fas fa-eye mr-2"></i>Aperçu des modifications
            </h3>
            <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="previewContent" class="max-h-96 overflow-y-auto">
            <!-- Le contenu sera chargé dynamiquement -->
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
    const categorieInput = document.getElementById('categorie');

    titreInput.addEventListener('blur', validateTitre);
    categorieInput.addEventListener('blur', validateCategorie);

    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
}

function validateTitre() {
    const titre = document.getElementById('titre').value.trim();
    const error = document.getElementById('titreError');
    
    if (titre.length < 3) {
        error.textContent = 'Le titre doit contenir au moins 3 caractères.';
        error.classList.remove('hidden');
        return false;
    }
    
    error.classList.add('hidden');
    return true;
}

function validateCategorie() {
    const categorie = document.getElementById('categorie').value.trim();
    const error = document.getElementById('categorieError');
    
    if (categorie === '') {
        error.textContent = 'La catégorie est obligatoire.';
        error.classList.remove('hidden');
        return false;
    }
    
    error.classList.add('hidden');
    return true;
}

function validateForm() {
    const titreValid = validateTitre();
    const categorieValid = validateCategorie();
    return titreValid && categorieValid;
}

// Gestion du formulaire
function toggleForm() {
    const form = document.getElementById('procedureForm');
    form.classList.toggle('hidden');
    
    if (!form.classList.contains('hidden')) {
        document.getElementById('titre').focus();
    }
}

// Filtres et recherche
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const sort = document.getElementById('sortSelect').value;
    
    const [sortBy, sortOrder] = sort.split('-');
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('category', category);
    url.searchParams.set('sort', sortBy);
    url.searchParams.set('order', sortOrder.toLowerCase());
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function resetFilters() {
    const url = new URL(window.location);
    url.search = '';
    window.location.href = url.toString();
}

function sortBy(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort');
    const currentOrder = url.searchParams.get('order');
    
    let newOrder = 'desc';
    if (currentSort === column && currentOrder === 'desc') {
        newOrder = 'asc';
    }
    
    url.searchParams.set('sort', column);
    url.searchParams.set('order', newOrder);
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

// Actions sur les procédures
function confirmDelete(id, title) {
    deleteId = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteModal').classList.add('active');
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        window.location.href = `?delete=${id}`;
    };
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    deleteId = null;
}

function duplicateProcedure(id) {
    if (confirm('Dupliquer cette procédure ?')) {
        window.location.href = `?duplicate=${id}`;
    }
}

function viewHistory(id) {
    document.getElementById('historyModal').classList.add('active');
    
    // Simulation du chargement de l'historique
    document.getElementById('historyContent').innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
            <p class="text-gray-500 mt-2">Chargement de l'historique...</p>
        </div>
    `;
    
    // Ici, vous feriez un appel AJAX pour récupérer l'historique
    setTimeout(() => {
        document.getElementById('historyContent').innerHTML = `
            <div class="space-y-4">
                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                    <div class="bg-blue-500 rounded-full p-1 mt-1">
                        <i class="fas fa-edit text-white text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">Modification v.3</p>
                            <span class="text-xs text-gray-500">Aujourd'hui à 14:30</span>
                        </div>
                        <p class="text-sm text-gray-600">Mise à jour du contenu et ajout d'un fichier joint</p>
                        <p class="text-xs text-gray-500 mt-1">par Admin User</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                    <div class="bg-yellow-500 rounded-full p-1 mt-1">
                        <i class="fas fa-edit text-white text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">Modification v.2</p>
                            <span class="text-xs text-gray-500">Hier à 09:15</span>
                        </div>
                        <p class="text-sm text-gray-600">Correction de la catégorie</p>
                        <p class="text-xs text-gray-500 mt-1">par Admin User</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                    <div class="bg-green-500 rounded-full p-1 mt-1">
                        <i class="fas fa-plus text-white text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">Création v.1</p>
                            <span class="text-xs text-gray-500">Il y a 3 jours à 16:45</span>
                        </div>
                        <p class="text-sm text-gray-600">Création initiale de la procédure</p>
                        <p class="text-xs text-gray-500 mt-1">par Admin User</p>
                    </div>
                </div>
            </div>
        `;
    }, 1000);
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('active');
}

function previewChanges() {
    const titre = document.getElementById('titre').value;
    const categorie = document.getElementById('categorie').value;
    const contenu = editor.getData();
    
    document.getElementById('previewModal').classList.add('active');
    document.getElementById('previewContent').innerHTML = `
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">${titre}</h3>
                <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">${categorie}</span>
            </div>
            <div class="prose max-w-none">
                ${contenu}
            </div>
        </div>
    `;
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.remove('active');
}

// Export et Import
function exportData() {
    window.location.href = '?export=1';
}

function showImportModal() {
    document.getElementById('importModal').classList.add('active');
}

function closeImportModal() {
    document.getElementById('importModal').classList.remove('active');
}

// Gestion de l'import
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('import_procedures.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Import réussi : ' + data.imported + ' procédures importées');
            location.reload();
        } else {
            alert('Erreur lors de l\'import : ' + data.message);
        }
    })
    .catch(error => {
        alert('Erreur lors de l\'import : ' + error.message);
    });
    
    closeImportModal();
});

// Recherche en temps réel
document.getElementById('searchInput').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    // Ctrl+N pour nouvelle procédure
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        toggleForm();
    }
    
    // Echap pour fermer les modales
    if (e.key === 'Escape') {
        closeDeleteModal();
        closeHistoryModal();
        closeImportModal();
        closePreviewModal();
    }
    
    // Ctrl+S pour sauvegarder
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn && !document.getElementById('procedureForm').classList.contains('hidden')) {
            submitBtn.click();
        }
    }
});

// Auto-sauvegarde (brouillon)
let autoSaveTimer;
function startAutoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const form = document.getElementById('mainForm');
        if (!form.classList.contains('hidden')) {
            // Sauvegarder en tant que brouillon
            saveAsDraft();
        }
    }, 30000); // 30 secondes
}

function saveAsDraft() {
    if (!validateForm()) return;
    
    const formData = new FormData(document.getElementById('mainForm'));
    formData.set('status', 'draft');
    formData.set('auto_save', '1');
    
    fetch('auto_save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Brouillon sauvegardé automatiquement', 'success');
        }
    })
    .catch(error => {
        console.error('Erreur auto-sauvegarde:', error);
    });
}

// Notifications toast
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-triangle' :
                type === 'warning' ? 'fa-exclamation-triangle' :
                'fa-info-circle'
            } mr-2"></i>
            ${message}
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.transition = 'transform 0.3s ease-out';
    }, 10);
    
    // Auto-suppression après 5 secondes
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Démarrer l'auto-sauvegarde quand on édite
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#mainForm input, #mainForm textarea, #mainForm select');
    inputs.forEach(input => {
        input.addEventListener('input', startAutoSave);
    });
    
    // Pour l'éditeur CKEditor
    if (typeof editor !== 'undefined') {
        editor.model.document.on('change:data', startAutoSave);
    }
});

// Confirmation avant de quitter si modifications non sauvegardées
let hasUnsavedChanges = false;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainForm');
    const inputs = form.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        input.addEventListener('change', () => {
            hasUnsavedChanges = true;
        });
    });
    
    form.addEventListener('submit', () => {
        hasUnsavedChanges = false;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
    }
});

// Système de thème sombre/clair
function toggleTheme() {
    const isDark = document.body.classList.contains('dark');
    if (isDark) {
        document.body.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        document.body.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}

// Charger le thème au démarrage
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
    }
});

// Fonctions utilitaires
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Copié dans le presse-papier', 'success');
    }, function(err) {
        console.error('Erreur de copie: ', err);
        showNotification('Erreur lors de la copie', 'error');
    });
}

function shareProcedure(id, title) {
    const url = `${window.location.origin}/procedure.php?id=${id}`;
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: `Consultez cette procédure: ${title}`,
            url: url
        });
    } else {
        copyToClipboard(url);
    }
}

// Fonction de recherche avancée avec highlighting
function highlightSearchTerms(text, searchTerm) {
    if (!searchTerm) return text;
    
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200">$1</mark>');
}

// Gestion des erreurs globales
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript:', e.error);
    showNotification('Une erreur inattendue s\'est produite', 'error');
});

// Performance monitoring
function measurePerformance(name, fn) {
    const start = performance.now();
    const result = fn();
    const end = performance.now();
    console.log(`${name} took ${end - start} milliseconds`);
    return result;
}

// Lazy loading des images
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Initialiser le lazy loading
document.addEventListener('DOMContentLoaded', lazyLoadImages);

// Service Worker pour le cache (PWA)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(registration) {
            console.log('ServiceWorker registration successful');
        }, function(err) {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}

// Fonction de backup automatique
function createBackup() {
    fetch('backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'create_backup' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Sauvegarde créée avec succès', 'success');
        } else {
            showNotification('Erreur lors de la sauvegarde', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la sauvegarde', 'error');
    });
}

// Planifier des sauvegardes automatiques
setInterval(createBackup, 24 * 60 * 60 * 1000); // Une fois par jour

// Analytics et tracking des actions utilisateur
function trackUserAction(action, details = {}) {
    // Ici vous pourriez envoyer les données à votre système d'analytics
    console.log('User action:', action, details);
    
    // Exemple d'envoi vers Google Analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            'custom_parameter': details
        });
    }
}

// Système de favoris/bookmarks
function toggleBookmark(procedureId) {
    const bookmarks = JSON.parse(localStorage.getItem('procedure_bookmarks') || '[]');
    const index = bookmarks.indexOf(procedureId);
    
    if (index > -1) {
        bookmarks.splice(index, 1);
        showNotification('Retiré des favoris', 'info');
    } else {
        bookmarks.push(procedureId);
        showNotification('Ajouté aux favoris', 'success');
    }
    
    localStorage.setItem('procedure_bookmarks', JSON.stringify(bookmarks));
    updateBookmarkUI(procedureId, index === -1);
}

function updateBookmarkUI(procedureId, isBookmarked) {
    const button = document.querySelector(`[data-bookmark-id="${procedureId}"]`);
    if (button) {
        button.innerHTML = isBookmarked ? 
            '<i class="fas fa-star text-yellow-500"></i>' : 
            '<i class="far fa-star text-gray-400"></i>';
    }
}

// Initialiser les favoris au chargement
document.addEventListener('DOMContentLoaded', function() {
    const bookmarks = JSON.parse(localStorage.getItem('procedure_bookmarks') || '[]');
    bookmarks.forEach(id => updateBookmarkUI(id, true));
});

// Fonction de comparaison de versions
function compareProcedureVersions(id, version1, version2) {
    fetch(`compare_versions.php?id=${id}&v1=${version1}&v2=${version2}`)
        .then(response => response.json())
        .then(data => {
            showVersionComparison(data);
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la comparaison', 'error');
        });
}

function showVersionComparison(data) {
    // Afficher une modale avec la comparaison des versions
    // Utiliser une librairie comme diff2html pour un meilleur rendu
    console.log('Version comparison:', data);
}

// Système de commentaires/annotations
function addComment(procedureId, comment) {
    fetch('add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            procedure_id: procedureId,
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Commentaire ajouté', 'success');
            loadComments(procedureId);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// Fonction de suggestion automatique basée sur l'IA
function getSuggestions(title, category, content) {
    fetch('ai_suggestions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            title: title,
            category: category,
            content: content
        })
    })
    .then(response => response.json())
    .then(data => {
        displaySuggestions(data.suggestions);
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

function displaySuggestions(suggestions) {
    const suggestionsContainer = document.getElementById('suggestions');
    if (suggestions && suggestions.length > 0) {
        suggestionsContainer.innerHTML = suggestions.map(suggestion => 
            `<div class="suggestion-item p-2 bg-blue-50 rounded mb-2">
                <strong>${suggestion.type}:</strong> ${suggestion.text}
                <button onclick="applySuggestion('${suggestion.type}', '${suggestion.value}')" 
                        class="ml-2 text-blue-600 hover:text-blue-800">
                    Appliquer
                </button>
            </div>`
        ).join('');
        suggestionsContainer.classList.remove('hidden');
    }
}

function applySuggestion(type, value) {
    switch(type) {
        case 'title':
            document.getElementById('titre').value = value;
            break;
        case 'category':
            document.getElementById('categorie').value = value;
            break;
        case 'content':
            if (editor) {
                editor.setData(value);
            }
            break;
    }
    showNotification('Suggestion appliquée', 'success');
}

console.log('Script de gestion des procédures chargé avec succès');
</script>

<style>
/* Styles additionnels pour les fonctionnalités avancées */
.suggestion-item {
    transition: all 0.3s ease;
}

.suggestion-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.dark {
    background-color: #1a202c;
    color: #e2e8f0;
}

.dark .bg-white {
    background-color: #2d3748;
}

.dark .text-gray-900 {
    color: #e2e8f0;
}

.dark .border-gray-300 {
    border-color: #4a5568;
}

.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}

.lazy.loaded {
    opacity: 1;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .print-break {
        page-break-after: always;
    }
}

/* Animations personnalisées */
@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.slide-in {
    animation: slideInFromRight 0.3s ease-out;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .modal > div {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
    
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex {
        flex-direction: column;
    }
    
    .space-x-2 > * {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}

/* Loading states */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Accessibility improvements */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.focus-visible:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .border-gray-300 {
        border-color: #000;
    }
    
    .text-gray-500 {
        color: #000;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

</body>
</html>