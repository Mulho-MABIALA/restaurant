<?php
require_once '../config.php'; // Connexion à la base
session_start();
// Vérifie que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire d'ajout
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = $_POST['nom'];
    $quantite = $_POST['quantite'];
    $unite = $_POST['unite'];
    $seuil_alerte = $_POST['seuil_alerte'];
    $categorie = $_POST['categorie'] ?? 'Autre';
    
    $stmt = $conn->prepare("INSERT INTO stocks (nom, quantite, unite, seuil_alerte, categorie, date_creation) VALUES (?, ?, ?, ?, ?, NOW())");
    $success = $stmt->execute([$nom, $quantite, $unite, $seuil_alerte, $categorie]);
    
    if ($success) {
        // Enregistrer le mouvement
        $stock_id = $conn->lastInsertId();
        $stmt_mvt = $conn->prepare("INSERT INTO mouvements_stock (stock_id, type_mouvement, quantite, commentaire, date_mouvement) VALUES (?, 'entree', ?, 'Stock initial', NOW())");
        $stmt_mvt->execute([$stock_id, $quantite]);
        
        $message = "Ingrédient ajouté avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur lors de l'ajout de l'ingrédient.";
        $message_type = "error";
    }
}

// Traitement du formulaire de modification
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id = $_POST['id'];
    $nom = $_POST['nom'];
    $quantite = $_POST['quantite'];
    $unite = $_POST['unite'];
    $seuil_alerte = $_POST['seuil_alerte'];
    $categorie = $_POST['categorie'] ?? 'Autre';
    
    $stmt = $conn->prepare("UPDATE stocks SET nom = ?, quantite = ?, unite = ?, seuil_alerte = ?, categorie = ? WHERE id = ?");
    $success = $stmt->execute([$nom, $quantite, $unite, $seuil_alerte, $categorie, $id]);
    
    if ($success) {
        $message = "Ingrédient modifié avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur lors de la modification de l'ingrédient.";
        $message_type = "error";
    }
}

// Traitement de la suppression
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $id = $_POST['id'];
    
    $stmt = $conn->prepare("DELETE FROM stocks WHERE id = ?");
    $success = $stmt->execute([$id]);
    
    if ($success) {
        $message = "Ingrédient supprimé avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur lors de la suppression de l'ingrédient.";
        $message_type = "error";
    }
}

// Traitement de l'ajustement rapide
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'ajuster') {
    $id = $_POST['id'];
    $operation = $_POST['operation']; // 'add' ou 'remove'
    $quantite_ajustement = $_POST['quantite_ajustement'];
    $commentaire = $_POST['commentaire'] ?? '';
    
    // Récupérer la quantité actuelle
    $stmt = $conn->prepare("SELECT quantite, nom FROM stocks WHERE id = ?");
    $stmt->execute([$id]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stock) {
        $nouvelle_quantite = $operation === 'add' ? 
            $stock['quantite'] + $quantite_ajustement : 
            max(0, $stock['quantite'] - $quantite_ajustement);
        
        // Mettre à jour le stock
        $stmt = $conn->prepare("UPDATE stocks SET quantite = ? WHERE id = ?");
        $success = $stmt->execute([$nouvelle_quantite, $id]);
        
        if ($success) {
            // Enregistrer le mouvement
            $type_mouvement = $operation === 'add' ? 'entree' : 'sortie';
            $stmt_mvt = $conn->prepare("INSERT INTO mouvements_stock (stock_id, type_mouvement, quantite, commentaire, date_mouvement) VALUES (?, ?, ?, ?, NOW())");
            $stmt_mvt->execute([$id, $type_mouvement, $quantite_ajustement, $commentaire]);
            
            $message = "Stock ajusté avec succès !";
            $message_type = "success";
        }
    }
}

// Paramètres de recherche et filtrage
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_unite = $_GET['unite'] ?? '';
$filter_categorie = $_GET['categorie'] ?? '';
$sort_by = $_GET['sort'] ?? 'nom';
$sort_order = $_GET['order'] ?? 'ASC';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "nom LIKE ?";
    $params[] = "%$search%";
}

if (!empty($filter_unite)) {
    $where_conditions[] = "unite = ?";
    $params[] = $filter_unite;
}

if (!empty($filter_categorie)) {
    $where_conditions[] = "categorie = ?";
    $params[] = $filter_categorie;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Filtrage par statut après la requête (car basé sur comparaison quantite/seuil_alerte)
$having_clause = '';
if ($filter_status === 'alerte') {
    $having_clause = 'HAVING quantite <= seuil_alerte';
} elseif ($filter_status === 'ok') {
    $having_clause = 'HAVING quantite > seuil_alerte';
}

$sql = "SELECT * FROM stocks $where_clause $having_clause ORDER BY $sort_by $sort_order";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_ingredients = count($stocks);
$stocks_alertes = array_filter($stocks, function($stock) {
    return $stock['quantite'] <= $stock['seuil_alerte'];
});
$nb_alertes = count($stocks_alertes);

// Récupérer les unités et catégories pour les filtres
$units = $conn->query("SELECT DISTINCT unite FROM stocks ORDER BY unite")->fetchAll(PDO::FETCH_COLUMN);
$categories = $conn->query("SELECT DISTINCT categorie FROM stocks ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-container { max-height: 600px; overflow-y: auto; }
        .toast { transition: all 0.3s ease-in-out; }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .slide-in { animation: slideIn 0.3s ease-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-boxes text-blue-600 mr-3"></i>
                                Gestion des Stocks
                                <?php if ($nb_alertes > 0): ?>
                                    <span class="ml-3 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 animate-pulse">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <?= $nb_alertes ?> alerte<?= $nb_alertes > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </h1>
                            <p class="text-gray-600 mt-1">Surveillez et gérez vos ingrédients en temps réel</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-50 px-4 py-2 rounded-full">
                                <span class="text-sm font-medium text-blue-800">
                                    <i class="fas fa-clock mr-1"></i>
                                    Mis à jour maintenant
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Message de succès/erreur -->
                    <?php if (isset($message)): ?>
                        <div class="mb-6 p-4 rounded-lg slide-in <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                            <div class="flex items-center">
                                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                                <?= $message ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100">
                                    <i class="fas fa-boxes text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Ingrédients</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= $total_ingredients ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Alertes Stock</p>
                                    <p class="text-2xl font-bold text-red-600"><?= $nb_alertes ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100">
                                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Stock OK</p>
                                    <p class="text-2xl font-bold text-green-600"><?= $total_ingredients - $nb_alertes ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100">
                                    <i class="fas fa-tags text-purple-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Catégories</p>
                                    <p class="text-2xl font-bold text-purple-600"><?= count($categories) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alertes critiques -->
                    <?php if (!empty($stocks_alertes)): ?>
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                            <h3 class="text-lg font-semibold text-red-800 mb-3 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Stocks critiques à réapprovisionner
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($stocks_alertes as $stock): ?>
                                    <div class="bg-white p-3 rounded-lg border border-red-200">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars($stock['nom']) ?></p>
                                                <p class="text-sm text-red-600">
                                                    <?= $stock['quantite'] ?> <?= $stock['unite'] ?> 
                                                    (seuil: <?= $stock['seuil_alerte'] ?>)
                                                </p>
                                            </div>
                                            <button onclick="openAdjustModal(<?= $stock['id'] ?>, '<?= htmlspecialchars($stock['nom']) ?>', <?= $stock['quantite'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Barre de recherche et filtres -->
                    <div class="mb-6 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                        <form method="GET" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                                <!-- Recherche -->
                                <div class="lg:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-search mr-1"></i>Rechercher
                                    </label>
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Nom de l'ingrédient..." 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Filtre par statut -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-filter mr-1"></i>Statut
                                    </label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Tous</option>
                                        <option value="ok" <?= $filter_status === 'ok' ? 'selected' : '' ?>>Stock OK</option>
                                        <option value="alerte" <?= $filter_status === 'alerte' ? 'selected' : '' ?>>Stock faible</option>
                                    </select>
                                </div>

                                <!-- Filtre par unité -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-ruler mr-1"></i>Unité
                                    </label>
                                    <select name="unite" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Toutes</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?= $unit ?>" <?= $filter_unite === $unit ? 'selected' : '' ?>>
                                                <?= $unit ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Filtre par catégorie -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-tags mr-1"></i>Catégorie
                                    </label>
                                    <select name="categorie" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Toutes</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat ?>" <?= $filter_categorie === $cat ? 'selected' : '' ?>>
                                                <?= $cat ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-1"></i>Filtrer
                                </button>
                                <a href="?" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    <i class="fas fa-times mr-1"></i>Réinitialiser
                                </a>
                                <button type="button" onclick="exportCSV()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <i class="fas fa-download mr-1"></i>Exporter CSV
                                </button>
                                <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                    <i class="fas fa-print mr-1"></i>Imprimer
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Action Bar -->
                    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center space-x-4">
                            <div class="bg-white rounded-lg px-4 py-2 shadow-sm border border-gray-200">
                                <span class="text-sm text-gray-600">Résultats :</span>
                                <span class="ml-2 font-semibold text-gray-900"><?= count($stocks) ?></span>
                            </div>
                        </div>
                        <button onclick="openModal('add')" 
                           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-lg shadow-md hover:from-green-600 hover:to-green-700 transform hover:scale-105 transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Ajouter un ingrédient
                        </button>
                    </div>
                    
                    <!-- Stocks Table Card -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                        <!-- Table Header -->
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-list mr-2 text-gray-600"></i>
                                Liste des Ingrédients
                            </h2>
                        </div>
                        
                        <!-- Table -->
                        <div class="overflow-x-auto table-container">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'nom', 'order' => $sort_by === 'nom' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-blue-600">
                                                <i class="fas fa-tag mr-2"></i>Ingrédient
                                                <?php if ($sort_by === 'nom'): ?>
                                                    <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'quantite', 'order' => $sort_by === 'quantite' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-blue-600">
                                                <i class="fas fa-sort-numeric-up mr-2"></i>Quantité
                                                <?php if ($sort_by === 'quantite'): ?>
                                                    <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-ruler mr-2"></i>Unité
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-tags mr-2"></i>Catégorie
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>Statut
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-cogs mr-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($stocks)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                                <i class="fas fa-search text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun ingrédient trouvé</p>
                                                <p class="text-sm">Essayez de modifier vos critères de recherche</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($stocks as $row): ?>
                                            <?php
                                                $alerte = $row['quantite'] <= $row['seuil_alerte'];
                                                $rowClass = $alerte ? 'bg-red-50 border-l-4 border-red-400' : 'hover:bg-gray-50';
                                            ?>
                                            <tr class="<?= $rowClass ?> transition-colors duration-200">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full flex items-center justify-center mr-3">
                                                            <i class="fas fa-leaf text-blue-600"></i>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?= htmlspecialchars($row['nom']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center space-x-2">
                                                        <button onclick="adjustStock(<?= $row['id'] ?>, 'remove', 1)" 
                                                                class="w-6 h-6 bg-red-100 text-red-600 rounded-full hover:bg-red-200 flex items-center justify-center text-xs">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <span class="text-sm font-semibold text-gray-900 min-w-12 text-center">
                                                            <?= $row['quantite'] ?>
                                                        </span>
                                                        <button onclick="adjustStock(<?= $row['id'] ?>, 'add', 1)" 
                                                                class="w-6 h-6 bg-green-100 text-green-600 rounded-full hover:bg-green-200 flex items-center justify-center text-xs">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        <?= $row['unite'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <?= $row['categorie'] ?? 'Autre' ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($alerte): ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 animate-pulse">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            Stock faible
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <i class="fas fa-check-circle mr-1"></i>
                                                            Stock OK
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex items-center space-x-2">
                                                        <button onclick="openAdjustModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nom']) ?>', <?= $row['quantite'] ?>)" 
                                                           class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition-colors duration-200">
                                                            <i class="fas fa-adjust text-xs"></i>
                                                        </button>
                                                        <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nom']) ?>', <?= $row['quantite'] ?>, '<?= $row['unite'] ?>', <?= $row['seuil_alerte'] ?>, '<?= $row['categorie'] ?? 'Autre' ?>')" 
                                                           class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors duration-200">
                                                            <i class="fas fa-edit text-xs"></i>
                                                        </button>
                                                        <button onclick="duplicateStock(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nom']) ?>')" 
                                                           class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors duration-200">
                                                            <i class="fas fa-copy text-xs"></i>
                                                        </button>
                                                        <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nom']) ?>')" 
                                                           class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors duration-200">
                                                            <i class="fas fa-trash text-xs"></i>
                                                        </button>
                                                        <button onclick="showHistory(<?= $row['id'] ?>)" 
                                                           class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                                            <i class="fas fa-history text-xs"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Footer Info -->
                    <div class="mt-6 bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                        <div class="flex items-center justify-center text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Les ingrédients avec un stock faible sont surlignés en rouge pour une identification rapide
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal d'ajout d'ingrédient -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header du modal -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                        Ajouter un nouvel ingrédient
                    </h3>
                    <button onclick="closeModal('add')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Formulaire -->
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="ajouter">
                    
                    <div>
                        <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Nom de l'ingrédient
                        </label>
                        <input type="text" id="nom" name="nom" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Ex: Farine, Sucre, Œufs...">
                    </div>

                    <div>
                        <label for="categorie" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tags mr-1"></i>Catégorie
                        </label>
                        <select id="categorie" name="categorie" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Choisir une catégorie</option>
                            <option value="Farines et céréales">Farines et céréales</option>
                            <option value="Sucres et édulcorants">Sucres et édulcorants</option>
                            <option value="Produits laitiers">Produits laitiers</option>
                            <option value="Œufs">Œufs</option>
                            <option value="Matières grasses">Matières grasses</option>
                            <option value="Levures et agents">Levures et agents</option>
                            <option value="Arômes et extraits">Arômes et extraits</option>
                            <option value="Fruits et légumes">Fruits et légumes</option>
                            <option value="Chocolat et cacao">Chocolat et cacao</option>
                            <option value="Noix et graines">Noix et graines</option>
                            <option value="Épices">Épices</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="quantite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-sort-numeric-up mr-1"></i>Quantité
                            </label>
                            <input type="number" id="quantite" name="quantite" required min="0" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="Ex: 100">
                        </div>

                        <div>
                            <label for="unite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-ruler mr-1"></i>Unité
                            </label>
                            <select id="unite" name="unite" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Choisir une unité</option>
                                <option value="kg">Kilogramme (kg)</option>
                                <option value="g">Gramme (g)</option>
                                <option value="l">Litre (l)</option>
                                <option value="ml">Millilitre (ml)</option>
                                <option value="pièce">Pièce</option>
                                <option value="paquet">Paquet</option>
                                <option value="boîte">Boîte</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="seuil_alerte" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Seuil d'alerte
                        </label>
                        <input type="number" id="seuil_alerte" name="seuil_alerte" required min="0" step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Ex: 10">
                        <p class="text-xs text-gray-500 mt-1">Quantité minimale avant alerte de stock faible</p>
                    </div>

                    <!-- Boutons -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" onclick="closeModal('add')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors duration-200">
                            <i class="fas fa-times mr-1"></i>Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-md hover:from-green-600 hover:to-green-700 transition-all duration-200">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de modification d'ingrédient -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header du modal -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-edit text-blue-600 mr-2"></i>
                        Modifier l'ingrédient
                    </h3>
                    <button onclick="closeModal('edit')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Formulaire -->
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" id="edit_id" name="id" value="">
                    
                    <div>
                        <label for="edit_nom" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Nom de l'ingrédient
                        </label>
                        <input type="text" id="edit_nom" name="nom" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: Farine, Sucre, Œufs...">
                    </div>

                    <div>
                        <label for="edit_categorie" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tags mr-1"></i>Catégorie
                        </label>
                        <select id="edit_categorie" name="categorie" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Choisir une catégorie</option>
                            <option value="Farines et céréales">Farines et céréales</option>
                            <option value="Sucres et édulcorants">Sucres et édulcorants</option>
                            <option value="Produits laitiers">Produits laitiers</option>
                            <option value="Œufs">Œufs</option>
                            <option value="Matières grasses">Matières grasses</option>
                            <option value="Levures et agents">Levures et agents</option>
                            <option value="Arômes et extraits">Arômes et extraits</option>
                            <option value="Fruits et légumes">Fruits et légumes</option>
                            <option value="Chocolat et cacao">Chocolat et cacao</option>
                            <option value="Noix et graines">Noix et graines</option>
                            <option value="Épices">Épices</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_quantite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-sort-numeric-up mr-1"></i>Quantité
                            </label>
                            <input type="number" id="edit_quantite" name="quantite" required min="0" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ex: 100">
                        </div>

                        <div>
                            <label for="edit_unite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-ruler mr-1"></i>Unité
                            </label>
                            <select id="edit_unite" name="unite" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Choisir une unité</option>
                                <option value="kg">Kilogramme (kg)</option>
                                <option value="g">Gramme (g)</option>
                                <option value="l">Litre (l)</option>
                                <option value="ml">Millilitre (ml)</option>
                                <option value="pièce">Pièce</option>
                                <option value="paquet">Paquet</option>
                                <option value="boîte">Boîte</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="edit_seuil_alerte" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Seuil d'alerte
                        </label>
                        <input type="number" id="edit_seuil_alerte" name="seuil_alerte" required min="0" step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: 10">
                        <p class="text-xs text-gray-500 mt-1">Quantité minimale avant alerte de stock faible</p>
                    </div>

                    <!-- Boutons -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" onclick="closeModal('edit')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors duration-200">
                            <i class="fas fa-times mr-1"></i>Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700 transition-all duration-200">
                            <i class="fas fa-save mr-1"></i>Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal d'ajustement de stock -->
    <div id="adjustModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-adjust text-orange-600 mr-2"></i>
                        Ajuster le stock
                    </h3>
                    <button onclick="closeModal('adjust')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="ajuster">
                    <input type="hidden" id="adjust_id" name="id" value="">
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900" id="adjust_ingredient_name"></h4>
                        <p class="text-sm text-gray-600">Stock actuel: <span id="adjust_current_stock"></span></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type d'opération</label>
                            <select name="operation" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="add">Ajouter (+)</option>
                                <option value="remove">Retirer (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantité</label>
                            <input type="number" name="quantite_ajustement" required min="0.01" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                   placeholder="Ex: 5">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire (optionnel)</label>
                        <textarea name="commentaire" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                  placeholder="Raison de l'ajustement..."></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" onclick="closeModal('adjust')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors duration-200">
                            <i class="fas fa-times mr-1"></i>Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-md hover:from-orange-600 hover:to-orange-700 transition-all duration-200">
                            <i class="fas fa-save mr-1"></i>Ajuster
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-4">Confirmer la suppression</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Êtes-vous sûr de vouloir supprimer <strong id="delete_ingredient_name"></strong> ?
                        Cette action est irréversible.
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <form method="POST" action="" class="inline">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" id="delete_id" name="id" value="">
                        <button type="button" onclick="closeModal('delete')" 
                                class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-24 mr-3 hover:bg-gray-400">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-24 hover:bg-red-700">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'historique -->
    <div id="historyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Historique des mouvements
                    </h3>
                    <button onclick="closeModal('history')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="history_content" class="max-h-96 overflow-y-auto">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(type) {
            if (type === 'add') {
                document.getElementById('addModal').classList.remove('hidden');
            }
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(type) {
            const modals = {
                'add': 'addModal',
                'edit': 'editModal',
                'adjust': 'adjustModal',
                'delete': 'deleteModal',
                'history': 'historyModal'
            };
            
            if (modals[type]) {
                document.getElementById(modals[type]).classList.add('hidden');
            }
            document.body.classList.remove('overflow-hidden');
        }

        function openEditModal(id, nom, quantite, unite, seuil_alerte, categorie) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_quantite').value = quantite;
            document.getElementById('edit_unite').value = unite;
            document.getElementById('edit_seuil_alerte').value = seuil_alerte;
            document.getElementById('edit_categorie').value = categorie;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function openAdjustModal(id, nom, quantite) {
            document.getElementById('adjust_id').value = id;
            document.getElementById('adjust_ingredient_name').textContent = nom;
            document.getElementById('adjust_current_stock').textContent = quantite;
            
            document.getElementById('adjustModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function confirmDelete(id, nom) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_ingredient_name').textContent = nom;
            
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function adjustStock(id, operation, quantite) {
            // Ajustement rapide via AJAX
            const formData = new FormData();
            formData.append('action', 'ajuster');
            formData.append('id', id);
            formData.append('operation', operation);
            formData.append('quantite_ajustement', quantite);
            formData.append('commentaire', 'Ajustement rapide');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                location.reload();
            }).catch(error => {
                console.error('Erreur:', error);
            });
        }

        function duplicateStock(id, nom) {
            if (confirm(`Voulez-vous dupliquer l'ingrédient "${nom}" ?`)) {
                // Ici vous pouvez implémenter la logique de duplication
                // Pour l'instant, on ouvre juste le modal d'ajout
                openModal('add');
                // Vous pourriez pré-remplir les champs avec les données existantes
            }
        }

        function showHistory(id) {
            document.getElementById('historyModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // Simuler un chargement d'historique
            document.getElementById('history_content').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                    <p class="text-gray-500 mt-2">Chargement de l'historique...</p>
                </div>
            `;
            
            // Vous pouvez implémenter ici un appel AJAX pour récupérer l'historique réel
            setTimeout(() => {
                document.getElementById('history_content').innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-plus-circle text-green-600 mr-3"></i>
                                <div>
                                    <p class="font-medium">Entrée de stock</p>
                                    <p class="text-sm text-gray-600">+10 unités</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Il y a 2 jours</p>
                                <p class="text-xs text-gray-400">Réapprovisionnement</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-minus-circle text-red-600 mr-3"></i>
                                <div>
                                    <p class="font-medium">Sortie de stock</p>
                                    <p class="text-sm text-gray-600">-5 unités</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Il y a 3 jours</p>
                                <p class="text-xs text-gray-400">Utilisation production</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                                <div>
                                    <p class="font-medium">Stock initial</p>
                                    <p class="text-sm text-gray-600">+50 unités</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Il y a 1 semaine</p>
                                <p class="text-xs text-gray-400">Création de l'ingrédient</p>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function exportCSV() {
            // Créer les données CSV
            const csvData = [
                ['Nom', 'Quantité', 'Unité', 'Seuil d\'alerte', 'Catégorie', 'Statut']
            ];
            
            <?php foreach ($stocks as $stock): ?>
            csvData.push([
                '<?= addslashes($stock['nom']) ?>',
                '<?= $stock['quantite'] ?>',
                '<?= $stock['unite'] ?>',
                '<?= $stock['seuil_alerte'] ?>',
                '<?= addslashes($stock['categorie'] ?? 'Autre') ?>',
                '<?= $stock['quantite'] <= $stock['seuil_alerte'] ? 'Stock faible' : 'Stock OK' ?>'
            ]);
            <?php endforeach; ?>
            
            // Convertir en CSV
            const csvContent = csvData.map(row => 
                row.map(field => `"${field}"`).join(',')
            ).join('\n');
            
            // Télécharger le fichier
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'stocks_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            const modals = ['addModal', 'editModal', 'adjustModal', 'deleteModal', 'historyModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (e.target === modal) {
                    closeModal(modalId.replace('Modal', ''));
                }
            });
        });

        // Fermer les modals avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('add');
                closeModal('edit');
                closeModal('adjust');
                closeModal('delete');
                closeModal('history');
            }
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl+N pour ajouter un nouvel ingrédient
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
            
            // Ctrl+F pour focus sur la recherche
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });

        // Auto-hide success/error messages after 5 seconds
        <?php if (isset($message)): ?>
        setTimeout(function() {
            const messageDiv = document.querySelector('.bg-green-100, .bg-red-100');
            if (messageDiv) {
                messageDiv.style.transition = 'opacity 0.5s';
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>

        // Notifications en temps réel (simulation)
        function checkForAlerts() {
            const alertCount = <?= $nb_alertes ?>;
            if (alertCount > 0) {
                // Vous pouvez ajouter ici une logique de notification
                console.log(`${alertCount} alerte(s) de stock faible détectée(s)`);
            }
        }

        // Vérifier les alertes toutes les 5 minutes
        setInterval(checkForAlerts, 300000);

        // Animation pour les éléments qui apparaissent
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.slide-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateX(0)';
                }, index * 100);
            });
        });

        // Mode sombre (fonctionnalité bonus)
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }

        // Charger le mode sombre sauvegardé
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }

        // Recherche en temps réel (optionnel)
        let searchTimeout;
        document.querySelector('input[name="search"]')?.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Vous pouvez implémenter une recherche AJAX ici
                console.log('Recherche:', e.target.value);
            }, 500);
        });

        // Sélection multiple pour actions en lot
        let selectedItems = [];
        
        function toggleSelection(id) {
            const index = selectedItems.indexOf(id);
            if (index > -1) {
                selectedItems.splice(index, 1);
            } else {
                selectedItems.push(id);
            }
            updateBulkActions();
        }

        function updateBulkActions() {
            const bulkActionsDiv = document.getElementById('bulkActions');
            if (selectedItems.length > 0) {
                if (!bulkActionsDiv) {
                    // Créer la barre d'actions en lot
                    const actionsBar = document.createElement('div');
                    actionsBar.id = 'bulkActions';
                    actionsBar.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-white shadow-lg rounded-lg p-4 border border-gray-200 z-50';
                    actionsBar.innerHTML = `
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">${selectedItems.length} élément(s) sélectionné(s)</span>
                            <button onclick="bulkDelete()" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">
                                <i class="fas fa-trash mr-1"></i>Supprimer
                            </button>
                            <button onclick="bulkExport()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                <i class="fas fa-download mr-1"></i>Exporter
                            </button>
                            <button onclick="clearSelection()" class="px-3 py-1 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                                <i class="fas fa-times mr-1"></i>Annuler
                            </button>
                        </div>
                    `;
                    document.body.appendChild(actionsBar);
                } else {
                    bulkActionsDiv.querySelector('span').textContent = `${selectedItems.length} élément(s) sélectionné(s)`;
                }
            } else if (bulkActionsDiv) {
                bulkActionsDiv.remove();
            }
        }

        function clearSelection() {
            selectedItems = [];
            updateBulkActions();
            // Décocher toutes les cases
            document.querySelectorAll('input[type="checkbox"][data-stock-id]').forEach(cb => {
                cb.checked = false;
            });
        }

        function bulkDelete() {
            if (confirm(`Voulez-vous vraiment supprimer ${selectedItems.length} ingrédient(s) ?`)) {
                // Implémenter la suppression en lot
                console.log('Suppression en lot:', selectedItems);
            }
        }

        function bulkExport() {
            // Implémenter l'export des éléments sélectionnés
            console.log('Export en lot:', selectedItems);
        }

        // Fonction utilitaire pour formater les dates
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Fonction pour mettre à jour les statistiques en temps réel
        function updateStats() {
            // Cette fonction pourrait être appelée après chaque modification
            const totalElements = document.querySelectorAll('tbody tr:not(.hidden)').length;
            const alertElements = document.querySelectorAll('tbody tr .bg-red-100').length;
            
            // Mettre à jour les compteurs si nécessaire
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Système de gestion des stocks initialisé');
            console.log(`${<?= $total_ingredients ?>} ingrédients chargés`);
            console.log(`${<?= $nb_alertes ?>} alertes actives`);
            
            // Afficher un message de bienvenue si c'est la première visite
            if (!localStorage.getItem('stocksVisited')) {
                localStorage.setItem('stocksVisited', 'true');
                setTimeout(() => {
                    alert('Bienvenue dans le système de gestion des stocks !\n\nAstuce: Utilisez Ctrl+N pour ajouter rapidement un nouvel ingrédient.');
                }, 1000);
            }
        });
    </script>

    <!-- Styles d'impression -->
    <style media="print">
        .no-print { display: none !important; }
        body { background: white !important; }
        .bg-gradient-to-br { background: white !important; }
        .shadow-lg, .shadow-sm { box-shadow: none !important; }
        .border { border: 1px solid #ccc !important; }
        
        @page {
            margin: 1cm;
            size: A4;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            border: 1px solid #333 !important;
            padding: 8px !important;
            text-align: left;
        }
        
        th {
            background-color: #f0f0f0 !important;
            font-weight: bold;
        }
        
        .bg-red-50 {
            background-color: #fee !important;
        }
    </style>

    <!-- Version imprimable cachée -->
    <div class="hidden print:block">
        <div class="print-header">
            <h1>Liste des Stocks - <?= date('d/m/Y H:i') ?></h1>
            <p>Total: <?= $total_ingredients ?> ingrédients | Alertes: <?= $nb_alertes ?></p>
        </div>
    </div>
</body>
</html>