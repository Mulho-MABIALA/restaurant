<?php
session_start();
require_once '../config.php';
require_once './permissions.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$categorieId = isset($plat['categorie_id']) ? $plat['categorie_id'] : null;
$nomCategorie = isset($categories[$categorieId]) ? $categories[$categorieId] : 'Non catégorisé';
requireAccess($conn, $_SESSION['admin_id'], 'gestion_plats');

// Récupérer les infos de l'admin
$stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
$stmt_admin->execute([$_SESSION['admin_id']]);
$admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $admin_info['email'] ?? 'admin@restaurant.com';
$admin_photo = null; // Photo non disponible dans la base de données

try {
    // Récupération des catégories
    $categories = $conn->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

    // Filtrage sécurisé
    $filtreCategorie = isset($_GET['categorie']) ? (int)$_GET['categorie'] : null;
    $idsCategorie = array_column($categories, 'id');
    $hasFilter = $filtreCategorie && in_array($filtreCategorie, $idsCategorie);

    $query = "SELECT p.id, p.nom, p.description, p.prix, p.image, p.disponible, c.nom AS categorie_nom 
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
    $platsDisponibles = count(array_filter($plats, fn($plat) => $plat['disponible'] == 1));
    $platsBloqués = $totalPlats - $platsDisponibles;
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cards-design.css">
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

        .plat-blocked {
            opacity: 0.6;
            background: rgba(239, 68, 68, 0.05);
        }
    </style>
</head>

<body class="bg-gray-50 font-inter">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <div class="p-6">
                <!-- Header Professionnel -->
                <header class="bg-slate-900 shadow-lg sticky top-0 z-40 -mx-8 -mt-8 mb-8">
                    <div class="px-4 sm:px-6 lg:px-8 py-4">
                        <div class="flex justify-between items-center">
                            <!-- Section Titre -->
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-teal-600 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-chart-line text-white text-lg"></i>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        Tableau de Bord
                                    </h1>
                                    <p class="text-gray-400 text-sm">
                                        Bienvenue, <?= htmlspecialchars($admin_name) ?> ✨
                                    </p>
                                </div>
                            </div>

                            <!-- Contrôles -->
                            <div class="flex items-center space-x-4" x-data="{ profileOpen: false }">
                                <!-- Widget Date/Heure -->
                                <div class="hidden sm:flex items-center space-x-5 bg-slate-800 rounded-xl px-5 py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-calendar text-blue-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400 uppercase">Aujourd'hui</p>
                                            <p class="text-sm font-bold text-white"><?= date('d M Y') ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-clock text-teal-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400 uppercase">Heure</p>
                                            <p class="text-sm font-bold text-white font-mono" id="live-clock"><?= date('H:i:s') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Menu Profil -->
                                <div class="relative">
                                    <button
                                        @click="profileOpen = !profileOpen"
                                        class="relative w-12 h-12 rounded-xl flex items-center justify-center hover:opacity-90 transition-opacity focus:outline-none overflow-hidden"
                                        type="button"
                                    >
                                        <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                                            <img src="<?= htmlspecialchars($admin_photo) ?>"
                                                 alt="Photo de profil"
                                                 class="w-full h-full object-cover rounded-xl">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                                <span class="text-white font-bold text-base">
                                                    <?= strtoupper(substr($admin_name, 0, 1)) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-400 border-2 border-slate-900 rounded-full"></div>
                                    </button>

                                    <!-- Dropdown Profil -->
                                    <div
                                        x-show="profileOpen"
                                        @click.away="profileOpen = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        class="absolute right-0 mt-2 w-72 bg-slate-800 rounded-xl shadow-xl overflow-hidden z-50"
                                        style="display: none;"
                                    >
                                        <!-- En-tête -->
                                        <div class="px-5 py-4 border-b border-slate-700">
                                            <div class="flex items-center space-x-3">
                                                <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                                                    <img src="<?= htmlspecialchars($admin_photo) ?>"
                                                         alt="Photo de profil"
                                                         class="w-12 h-12 object-cover rounded-lg">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                                                        <span class="text-white font-bold text-base"><?= strtoupper(substr($admin_name, 0, 1)) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="text-white font-semibold text-base"><?= htmlspecialchars($admin_name) ?></p>
                                                    <p class="text-gray-400 text-sm"><?= htmlspecialchars($admin_email) ?></p>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center">
                                                <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                                <span class="text-green-400 text-sm">En ligne</span>
                                            </div>
                                        </div>

                                        <!-- Menu -->
                                        <div class="py-2">
                                            <a href="profile.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                                                <i class="fas fa-user text-blue-400 w-5"></i>
                                                <span class="ml-3 text-white text-sm">Mon profil</span>
                                                <span class="ml-auto text-gray-400">›</span>
                                            </a>
                                            <a href="settings.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                                                <i class="fas fa-envelope text-purple-400 w-5"></i>
                                                <span class="ml-3 text-white text-sm">Changer email</span>
                                                <span class="ml-auto text-gray-400">›</span>
                                            </a>
                                            <div class="border-t border-slate-700 my-2"></div>
                                            <a href="logout.php" class="flex items-center px-5 py-3 hover:bg-red-900/20 transition-colors">
                                                <i class="fas fa-sign-out-alt text-red-400 w-5"></i>
                                                <span class="ml-3 text-red-400 text-sm font-medium">Déconnexion</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Cartes statistiques modernes -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
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
                    
                    <!-- Plats disponibles -->
                    <div class="dashboard-card card-green animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Disponibles</p>
                                <p class="text-3xl font-bold text-gray-900"><?= $platsDisponibles ?></p>
                                <p class="text-sm text-green-600 flex items-center mt-2">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    En service
                                </p>
                            </div>
                            <div class="icon-wrapper icon-green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plats bloqués -->
                    <div class="dashboard-card card-red animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Bloqués</p>
                                <p class="text-3xl font-bold text-gray-900"><?= $platsBloqués ?></p>
                                <p class="text-sm text-red-600 flex items-center mt-2">
                                    <i class="fas fa-ban mr-1"></i>
                                    Non disponibles
                                </p>
                            </div>
                            <div class="icon-wrapper icon-red">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Catégories -->
                    <div class="dashboard-card card-blue animate-fade-in" style="animation-delay: 0.3s;">
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
                </div>

                <!-- Section filtres et actions -->
                <div class="bg-white rounded-2xl p-6 mb-8 shadow-sm border-2 border-gray-200">
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
                                <select name="categorie" id="categorie" class="border-2 border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-48">
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

                <!-- Tableau moderne avec bordures visibles -->
                <div class="bg-white table-container shadow-lg">
                    <div class="bg-gray-50 px-6 py-4 border-b-2 border-gray-200">
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
                                        <i class="fas fa-hashtag mr-2"></i>N°
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-utensils mr-2"></i>Nom du plat
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-align-left mr-2"></i>Description
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-coins mr-2"></i>Prix
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">
                                        <i class="fas fa-tags mr-2"></i>Catégorie
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">
                                        <i class="fas fa-info-circle mr-2"></i>Statut
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">
                                        <i class="fas fa-image mr-2"></i>Image
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-cogs mr-2"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                        
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (!empty($plats)): ?>
                                    <?php foreach ($plats as $index => $plat): ?>
                                    <?php 
                                        $numeroLigne = $index + 1;
                                        $isBlocked = $plat['disponible'] == 0;
                                        // Déterminer la classe de couleur basée sur le numéro
                                        $colorClass = '';
                                        switch($numeroLigne % 5) {
                                            case 1: $colorClass = ''; break; // Bleu par défaut
                                            case 2: $colorClass = 'alt-1'; break; // Vert
                                            case 3: $colorClass = 'alt-2'; break; // Violet
                                            case 4: $colorClass = 'alt-3'; break; // Orange
                                            case 0: $colorClass = 'alt-4'; break; // Rouge
                                        }
                                    ?>
                                    <tr class="<?= $isBlocked ? 'plat-blocked' : '' ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center justify-center">
                                                <div class="number-badge <?= $colorClass ?>">
                                                    <?= $numeroLigne ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900 <?= $isBlocked ? 'line-through' : '' ?>">
                                                <?= htmlspecialchars($plat['nom']) ?>
                                                <?php if ($isBlocked): ?>
                                                    <i class="fas fa-ban text-red-500 ml-2" title="Plat non disponible"></i>
                                                <?php endif; ?>
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
                                            <?php if ($isBlocked): ?>
                                                <span class="status-badge status-blocked">
                                                    <i class="fas fa-ban mr-1"></i>Bloqué
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-available">
                                                    <i class="fas fa-check-circle mr-1"></i>Disponible
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                            <?php if (!empty($plat['image']) && file_exists('../uploads/' . $plat['image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                     class="h-12 w-12 rounded-lg object-cover border border-gray-200 <?= $isBlocked ? 'grayscale' : '' ?>" 
                                                     alt="<?= htmlspecialchars($plat['nom']) ?>">
                                            <?php else: ?>
                                                <div class="h-12 w-12 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                                                    <i class="fas fa-image text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($plat), ENT_QUOTES, 'UTF-8') ?>)"
                                                       class="action-btn btn-edit">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="hidden sm:inline">Modifier</span>
                                                </button>
                                                
                                                <?php if ($isBlocked): ?>
                                                    <button onclick="togglePlatStatus(<?= $plat['id'] ?>, 'unblock', '<?= addslashes($plat['nom']) ?>')" 
                                                            class="action-btn btn-unblock">
                                                        <i class="fas fa-check-circle"></i>
                                                        <span class="hidden sm:inline">Débloquer</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="togglePlatStatus(<?= $plat['id'] ?>, 'block', '<?= addslashes($plat['nom']) ?>')" 
                                                            class="action-btn btn-block">
                                                        <i class="fas fa-ban"></i>
                                                        <span class="hidden sm:inline">Bloquer</span>
                                                    </button>
                                                <?php endif; ?>
                                                
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
                                        <td colspan="8" class="text-center py-12">
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
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Prix -->
                    <div>
                        <label for="edit_prix" class="block text-sm font-semibold text-gray-700 mb-2">
                            Prix (FCFA)
                        </label>
                        <input type="number" id="edit_prix" name="prix" step="0.01" required
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <!-- Catégorie -->
                    <div>
                        <label for="edit_categorie" class="block text-sm font-semibold text-gray-700 mb-2">
                            Catégorie
                        </label>
                        <select id="edit_categorie" name="categorie_id"
                                class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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
                                  class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
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
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
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

        // Fonction pour basculer le statut d'un plat (bloquer/débloquer)
        function togglePlatStatus(id, action, nom) {
            const actionText = action === 'block' ? 'bloquer' : 'débloquer';
            const actionIcon = action === 'block' ? 'fa-ban' : 'fa-check-circle';
            const actionColor = action === 'block' ? 'red' : 'green';
            
            // Créer une modal personnalisée
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl transform scale-95 transition-all duration-300 border-2 border-gray-200">
                    <div class="text-center">
                        <div class="bg-${actionColor}-100 p-4 rounded-2xl inline-block mb-4 border-2 border-${actionColor}-200">
                            <i class="fas ${actionIcon} text-${actionColor}-600 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Confirmer l'action</h3>
                        <p class="text-gray-600 mb-6">
                            Êtes-vous sûr de vouloir ${actionText} le plat <strong>"${nom}"</strong> ?
                            <br><br>
                            <span class="text-${actionColor}-600 text-sm">
                                ${action === 'block' ? '⚠️ Le plat ne sera plus disponible pour les clients.' : '✅ Le plat redeviendra disponible pour les clients.'}
                            </span>
                        </p>
                        <div class="flex gap-4">
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-lg font-semibold transition-colors border-2 border-gray-300">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                            <button onclick="executeToggleStatus(${id}, '${action}'); this.closest('.fixed').remove()" 
                                    class="flex-1 bg-${actionColor}-600 hover:bg-${actionColor}-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors border-2 border-${actionColor}-600">
                                <i class="fas ${actionIcon} mr-2"></i>${actionText.charAt(0).toUpperCase() + actionText.slice(1)}
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

        // Fonction pour exécuter le changement de statut
        function executeToggleStatus(id, action) {
            // Envoyer la requête AJAX
            fetch('toggle_plat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher un message de succès
                    showNotification(data.message, 'success');
                    // Recharger la page après 1 seconde
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
            });
        }

        // Fonction pour afficher les notifications
        function showNotification(message, type) {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animation d'entrée
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Suppression automatique après 3 secondes
            setTimeout(() => {
                notification.style.transform = 'translateX(full)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

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
                             class="h-24 w-24 rounded-lg object-cover shadow-md border-2 border-gray-200" 
                             alt="${plat.nom}">
                        <div class="absolute -top-2 -right-2 bg-green-500 text-white p-1 rounded-full text-xs">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                `;
            } else {
                imageContainer.innerHTML = `
                    <div class="h-24 w-24 bg-gray-100 rounded-lg flex items-center justify-center border-2 border-gray-200">
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
                        <div class="bg-green-50 border-2 border-green-200 text-green-800 px-4 py-3 rounded-lg">
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
                        <div class="bg-red-50 border-2 border-red-200 text-red-800 px-4 py-3 rounded-lg">
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
                    <div class="bg-red-50 border-2 border-red-200 text-red-800 px-4 py-3 rounded-lg">
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
                <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl transform scale-95 transition-all duration-300 border-2 border-gray-200">
                    <div class="text-center">
                        <div class="bg-red-100 p-4 rounded-2xl inline-block mb-4 border-2 border-red-200">
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
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-lg font-semibold transition-colors border-2 border-gray-300">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                            <button onclick="window.location.href='supprimer_plat.php?id=${id}'" 
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors border-2 border-red-600">
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

        // Live clock update
        setInterval(() => {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const clockElement = document.getElementById('live-clock');
            if (clockElement) {
                clockElement.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }, 1000);
    </script>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>