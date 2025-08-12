<?php
require_once '../config.php';
session_start();

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Fonction pour échapper les valeurs
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Gestion de la suppression AJAX
if (isset($_POST['action']) && $_POST['action'] === 'supprimer' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $conn->prepare("DELETE FROM commandes WHERE id = :id");
        $stmt->execute(['id' => $_POST['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
    exit;
}

// Recherche & filtre par statut
$search = $_GET['search'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';

try {
    $sql = "SELECT * FROM commandes WHERE 1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nom_client LIKE :search OR email LIKE :search OR telephone LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($filtre_statut)) {
        $sql .= " AND statut = :statut";
        $params['statut'] = $filtre_statut;
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Statistiques
$total_cmd = count($commandes);
$total_ventes = array_sum(array_column($commandes, 'total'));
$moyenne_cmd = $total_cmd > 0 ? round($total_ventes / $total_cmd, 2) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                        'pulse-glow': 'pulseGlow 2s ease-in-out infinite',
                        'shimmer': 'shimmer 2s linear infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        bounceIn: {
                            '0%': { opacity: '0', transform: 'scale(0.3)' },
                            '50%': { opacity: '1', transform: 'scale(1.05)' },
                            '100%': { opacity: '1', transform: 'scale(1)' }
                        },
                        pulseGlow: {
                            '0%, 100%': { boxShadow: '0 0 20px rgba(59, 130, 246, 0.3)' },
                            '50%': { boxShadow: '0 0 30px rgba(59, 130, 246, 0.6)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200px 0' },
                            '100%': { backgroundPosition: 'calc(200px + 100%) 0' }
                        }
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
                        'glass': 'linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0))',
                        'shimmer-gradient': 'linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent)',
                    },
                    backdropBlur: {
                        'xs': '2px',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .glass-dark {
            background: rgba(17, 24, 39, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(75, 85, 99, 0.3);
        }
        .shimmer {
            background-image: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            background-size: 200px 100%;
            animation: shimmer 2s infinite;
        }
        .glow-blue {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.3);
        }
        .glow-green {
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
        }
        .glow-purple {
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        .stat-card:hover::before {
            left: 100%;
        }
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .table-row {
            transition: all 0.3s ease;
        }
        .table-row:hover {
            transform: translateX(5px);
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.05), rgba(255, 255, 255, 0.9));
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header avec gradient et effets -->
            <header class="glass-card shadow-2xl border-b border-white/20 px-8 py-6 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-purple-600/10"></div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl shadow-xl floating">
                            <i class="fas fa-shopping-cart text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-black bg-gradient-to-r from-gray-900 via-blue-900 to-purple-900 bg-clip-text text-transparent">
                                Gestion des Commandes
                            </h1>
                            <p class="text-gray-600 font-medium">Interface d'administration avancée</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="px-4 py-2 bg-gradient-to-r from-green-400 to-blue-500 rounded-full shadow-lg">
                            <span class="text-white font-semibold text-sm">✨ Premium Dashboard</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-8">
                <!-- Statistiques Cards avec effets premium -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
                    <!-- Total Commandes -->
                    <div class="stat-card glass-card rounded-3xl shadow-2xl p-8 glow-blue hover:shadow-blue-500/25 transition-all duration-500 transform hover:-translate-y-2 animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-2">Total Commandes</p>
                                <p class="text-4xl font-black bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent"><?= $total_cmd ?></p>
                                <p class="text-xs text-blue-500 font-semibold mt-2">
                                    <i class="fas fa-trending-up mr-1"></i>
                                    Commandes traitées
                                </p>
                            </div>
                            <div class="p-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-xl floating">
                                <i class="fas fa-receipt text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-2 bg-blue-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full shimmer"></div>
                        </div>
                    </div>
                    
                    <!-- Total Ventes -->
                    <div class="stat-card glass-card rounded-3xl shadow-2xl p-8 glow-green hover:shadow-green-500/25 transition-all duration-500 transform hover:-translate-y-2 animate-slide-up" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-2">Total Ventes</p>
                                <p class="text-4xl font-black bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent"><?= number_format($total_ventes, 0) ?></p>
                                <p class="text-xs text-green-500 font-semibold mt-2">
                                    <i class="fas fa-coins mr-1"></i>
                                    FCFA générés
                                </p>
                            </div>
                            <div class="p-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-xl floating" style="animation-delay: 0.5s;">
                                <i class="fas fa-chart-line text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-2 bg-green-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-green-500 to-emerald-500 rounded-full shimmer"></div>
                        </div>
                    </div>
                    
                    <!-- Moyenne par Commande -->
                    <div class="stat-card glass-card rounded-3xl shadow-2xl p-8 glow-purple hover:shadow-purple-500/25 transition-all duration-500 transform hover:-translate-y-2 animate-slide-up" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-2">Moyenne/Commande</p>
                                <p class="text-4xl font-black bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent"><?= number_format($moyenne_cmd, 0) ?></p>
                                <p class="text-xs text-purple-500 font-semibold mt-2">
                                    <i class="fas fa-calculator mr-1"></i>
                                    FCFA par commande
                                </p>
                            </div>
                            <div class="p-4 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl shadow-xl floating" style="animation-delay: 1s;">
                                <i class="fas fa-analytics text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-2 bg-purple-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full shimmer"></div>
                        </div>
                    </div>
                </div>

                <!-- Filtres et Recherche avec design premium -->
                <div class="glass-card rounded-3xl shadow-2xl p-8 mb-8 animate-fade-in">
                    <div class="flex items-center mb-6">
                        <div class="p-2 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl mr-4">
                            <i class="fas fa-search text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Recherche & Filtres</h3>
                    </div>
                    
                    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <div class="md:col-span-6">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Recherche Avancée</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400 group-focus-within:text-blue-500 transition-colors"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Recherche par nom, email ou téléphone..." 
                                       value="<?= e($search) ?>"
                                       class="block w-full pl-12 pr-4 py-4 bg-white/70 border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 font-medium placeholder-gray-400 hover:bg-white/90">
                            </div>
                        </div>
                        
                        <div class="md:col-span-4">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Filtrer par Statut</label>
                            <select name="statut" class="block w-full px-4 py-4 bg-white/70 border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 font-medium hover:bg-white/90">
                                <option value="">🎯 Tous les statuts</option>
                                <option value="En cours" <?= $filtre_statut == 'En cours' ? 'selected' : '' ?>>⏳ En cours</option>
                                <option value="Livré" <?= $filtre_statut == 'Livré' ? 'selected' : '' ?>>✅ Livré</option>
                                <option value="Annulé" <?= $filtre_statut == 'Annulé' ? 'selected' : '' ?>>❌ Annulé</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="w-full px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl hover:from-blue-700 hover:to-purple-700 focus:ring-4 focus:ring-blue-500/20 transform hover:scale-105 transition-all duration-300 font-bold shadow-xl hover:shadow-2xl">
                                <i class="fas fa-magic mr-2"></i>
                                Filtrer
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tableau des Commandes avec design ultra-premium -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden animate-fade-in">
                    <div class="px-8 py-6 bg-gradient-to-r from-gray-50 to-blue-50 border-b border-gray-200/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl mr-4">
                                    <i class="fas fa-table text-white"></i>
                                </div>
                                <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-800 to-blue-800 bg-clip-text text-transparent">
                                    Liste des Commandes
                                </h3>
                            </div>
                            <div class="px-4 py-2 bg-gradient-to-r from-green-400 to-blue-500 rounded-full shadow-lg">
                                <span class="text-white font-bold text-sm"><?= count($commandes) ?> résultats</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 via-blue-50 to-purple-50">
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-hashtag mr-2 text-blue-500"></i>ID
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-user mr-2 text-green-500"></i>Client
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-address-book mr-2 text-purple-500"></i>Contact
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-money-bill mr-2 text-yellow-500"></i>Total
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-flag mr-2 text-red-500"></i>Statut
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-eye mr-2 text-indigo-500"></i>Vu
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-calendar mr-2 text-pink-500"></i>Date
                                    </th>
                                    <th class="px-8 py-6 text-left text-xs font-black text-gray-600 uppercase tracking-wider">
                                        <i class="fas fa-cog mr-2 text-gray-500"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white/90 backdrop-blur-sm" id="commandesTableBody">
                                <?php if (empty($commandes)): ?>
                                    <tr>
                                        <td colspan="8" class="px-8 py-20 text-center">
                                            <div class="flex flex-col items-center animate-bounce-in">
                                                <div class="p-8 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full mb-6">
                                                    <i class="fas fa-search-minus text-6xl bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent"></i>
                                                </div>
                                                <h3 class="text-2xl font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                                <p class="text-gray-500 text-lg">Essayez de modifier vos critères de recherche</p>
                                                <div class="mt-6 px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-full shadow-lg">
                                                    <span class="font-semibold">💡 Astuce: Utilisez des filtres différents</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($commandes as $index => $cmd): ?>
                                        <tr class="table-row border-b border-gray-100/50" id="commande-<?= $cmd['id'] ?>" style="animation-delay: <?= $index * 0.05 ?>s;">
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="p-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg mr-3">
                                                        <span class="text-white font-bold text-xs">#</span>
                                                    </div>
                                                    <span class="text-lg font-black text-gray-900"><?= e($cmd['id']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="p-2 bg-gradient-to-r from-green-400 to-blue-500 rounded-full mr-4 text-white font-bold">
                                                        <?= strtoupper(substr(e($cmd['nom_client']), 0, 1)) ?>
                                                    </div>
                                                    <span class="text-lg font-bold text-gray-900"><?= e($cmd['nom_client']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6">
                                                <div class="space-y-1">
                                                    <div class="flex items-center text-gray-900 font-medium">
                                                        <i class="fas fa-envelope text-blue-500 mr-2"></i>
                                                        <?= e($cmd['email']) ?>
                                                    </div>
                                                    <div class="flex items-center text-gray-600">
                                                        <i class="fas fa-phone text-green-500 mr-2"></i>
                                                        <?= e($cmd['telephone']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="p-2 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-lg mr-3">
                                                        <i class="fas fa-coins text-white"></i>
                                                    </div>
                                                    <div>
                                                        <span class="text-xl font-black bg-gradient-to-r from-yellow-600 to-orange-600 bg-clip-text text-transparent">
                                                            <?= number_format($cmd['total'], 0) ?>
                                                        </span>
                                                        <span class="text-sm font-semibold text-gray-600">FCFA</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <?php
                                                $statutClass = '';
                                                $statutIcon = '';
                                                switch($cmd['statut']) {
                                                    case 'En cours':
                                                        $statutClass = 'from-yellow-400 to-orange-500 text-white';
                                                        $statutIcon = 'fas fa-clock';
                                                        break;
                                                    case 'Livré':
                                                        $statutClass = 'from-green-400 to-emerald-500 text-white';
                                                        $statutIcon = 'fas fa-check-circle';
                                                        break;
                                                    case 'Annulé':
                                                        $statutClass = 'from-red-400 to-pink-500 text-white';
                                                        $statutIcon = 'fas fa-times-circle';
                                                        break;
                                                    default:
                                                        $statutClass = 'from-gray-400 to-gray-500 text-white';
                                                        $statutIcon = 'fas fa-question-circle';
                                                }
                                                ?>
                                                <div class="inline-flex items-center px-4 py-2 rounded-full bg-gradient-to-r <?= $statutClass ?> shadow-lg font-bold text-sm">
                                                    <i class="<?= $statutIcon ?> mr-2"></i>
                                                    <?= e($cmd['statut']) ?>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <?php if ($cmd['vu_admin']): ?>
                                                    <div class="inline-flex items-center px-4 py-2 rounded-full bg-gradient-to-r from-green-400 to-emerald-500 text-white shadow-lg font-bold text-sm">
                                                        <i class="fas fa-eye mr-2"></i>Consulté
                                                    </div>
                                                <?php else: ?>
                                                    <div class="inline-flex items-center px-4 py-2 rounded-full bg-gradient-to-r from-red-400 to-pink-500 text-white shadow-lg font-bold text-sm animate-pulse">
                                                        <i class="fas fa-exclamation-triangle mr-2"></i>Nouveau
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex items-center text-gray-600 font-medium">
                                                    <i class="fas fa-calendar-alt text-purple-500 mr-2"></i>
                                                    <?= e($cmd['date_commande'] ?? $cmd['created_at'] ?? 'Non défini') ?>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex space-x-3">
                                                    <a href="recu.php?id=<?= $cmd['id'] ?>" 
                                                       target="_blank"
                                                       class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:ring-4 focus:ring-blue-500/20 transform hover:scale-105 transition-all duration-300 font-bold shadow-lg hover:shadow-xl">
                                                        <i class="fas fa-receipt mr-2"></i>
                                                        Reçu
                                                    </a>
                                                    <a href="modifier_commande.php?id=<?= $cmd['id'] ?>" 
                                                       target="_blank"
                                                       class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-yellow-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:ring-4 focus:ring-blue-500/20 transform hover:scale-105 transition-all duration-300 font-bold shadow-lg hover:shadow-xl">
                                                        <i class="fas fa-receipt mr-2"></i>
                                                        modifier
                                                    </a>
                                                    <button onclick="confirmDelete(<?= $cmd['id'] ?>)"
                                                       class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl hover:from-red-600 hover:to-pink-700 focus:ring-4 focus:ring-red-500/20 transform hover:scale-105 transition-all duration-300 font-bold shadow-lg hover:shadow-xl">
                                                        <i class="fas fa-trash mr-2"></i>
                                                        Supprimer
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
            </main>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card rounded-3xl p-8 m-4 max-w-md w-full animate-bounce-in">
            <div class="text-center">
                <div class="p-4 bg-gradient-to-r from-red-500 to-pink-600 rounded-full w-20 h-20 mx-auto mb-6 flex items-center justify-center animate-pulse">
                    <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">⚠️ Confirmer la suppression</h3>
                <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement la commande :</p>
                <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-2xl p-4 mb-6">
                    <p class="font-bold text-red-800" id="deleteCommandeInfo"></p>
                </div>
                <p class="text-red-600 font-semibold mb-8">⚠️ Cette action est irréversible !</p>
                <div class="flex space-x-4">
                    <button onclick="closeDeleteModal()" 
                            class="flex-1 px-6 py-3 bg-gray-200 text-gray-800 rounded-2xl font-bold hover:bg-gray-300 transition-colors transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Annuler
                    </button>
                    <button onclick="deleteCommande()" 
                            id="confirmDeleteBtn"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-2xl font-bold hover:from-red-600 hover:to-pink-700 transition-all transform hover:scale-105 shadow-xl">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-4"></div>

    <script>
        let commandeToDelete = null;

        // Fonction pour afficher la modale de confirmation
        function confirmDelete(id) {
            commandeToDelete = id;
            const modal = document.getElementById('deleteModal');
            const commandeRow = document.getElementById('commande-' + id);
            const nomClient = commandeRow.querySelector('.text-lg.font-bold.text-gray-900').textContent;
            
            document.getElementById('deleteCommandeInfo').textContent = `Commande #${id} - ${nomClient}`;
            modal.classList.remove('hidden');
            
            // Animation d'apparition
            setTimeout(() => {
                modal.classList.add('animate-fade-in');
            }, 10);
        }

        // Fonction pour fermer la modale
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('opacity-0', 'scale-95', 'animate-fade-in');
            }, 300);
            commandeToDelete = null;
        }

        // Fonction pour supprimer la commande via AJAX
        function deleteCommande() {
            if (!commandeToDelete) return;
            
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;
            
            // Animation de chargement
            confirmBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Suppression...
            `;
            confirmBtn.disabled = true;
            
            // Requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=supprimer&id=${commandeToDelete}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de suppression de la ligne
                    const row = document.getElementById('commande-' + commandeToDelete);
                    row.style.transform = 'translateX(-100%)';
                    row.style.opacity = '0';
                    
                    setTimeout(() => {
                        row.remove();
                        updateStats();
                    }, 300);
                    
                    showToast('✅ Commande supprimée avec succès!', 'success');
                    closeDeleteModal();
                } else {
                    showToast('❌ Erreur: ' + data.message, 'error');
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('❌ Erreur de connexion', 'error');
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }

        // Fonction pour afficher les notifications toast
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'from-green-500 to-emerald-600' : 'from-red-500 to-pink-600';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.className = `glass-card px-6 py-4 bg-gradient-to-r ${bgColor} text-white rounded-2xl shadow-2xl transform translate-x-full transition-all duration-500 font-bold max-w-sm`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="${icon} mr-3 text-xl"></i>
                    <span>${message}</span>
                    <button onclick="this.closest('.glass-card').remove()" class="ml-4 text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Animation d'apparition
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto-suppression après 5 secondes
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 500);
                }
            }, 5000);
        }

        // Fonction pour mettre à jour les statistiques
        function updateStats() {
            const remainingRows = document.querySelectorAll('#commandesTableBody tr:not([colspan])');
            const totalCommandes = remainingRows.length;
            
            // Mettre à jour le compteur total
            const totalElement = document.querySelector('.text-4xl.font-black.bg-gradient-to-r.from-blue-600');
            if (totalElement) {
                animateCounter(totalElement, totalCommandes);
            }
            
            // Recalculer et mettre à jour le total des ventes
            let totalVentes = 0;
            remainingRows.forEach(row => {
                const totalCell = row.querySelector('.text-xl.font-black.bg-gradient-to-r.from-yellow-600');
                if (totalCell) {
                    const amount = parseInt(totalCell.textContent.replace(/[^\d]/g, ''));
                    totalVentes += amount;
                }
            });
            
            const ventesElement = document.querySelector('.text-4xl.font-black.bg-gradient-to-r.from-green-600');
            if (ventesElement) {
                animateCounter(ventesElement, totalVentes);
            }
            
            // Recalculer la moyenne
            const moyenne = totalCommandes > 0 ? Math.round(totalVentes / totalCommandes) : 0;
            const moyenneElement = document.querySelector('.text-4xl.font-black.bg-gradient-to-r.from-purple-600');
            if (moyenneElement) {
                animateCounter(moyenneElement, moyenne);
            }
            
            // Mettre à jour le compteur de résultats
            const resultatsElement = document.querySelector('.text-white.font-bold.text-sm');
            if (resultatsElement) {
                resultatsElement.textContent = `${totalCommandes} résultats`;
            }
            
            // Vérifier s'il n'y a plus de commandes
            if (totalCommandes === 0) {
                const tbody = document.getElementById('commandesTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center animate-bounce-in">
                                <div class="p-8 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full mb-6">
                                    <i class="fas fa-search-minus text-6xl bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                <p class="text-gray-500 text-lg">Toutes les commandes ont été supprimées</p>
                                <div class="mt-6 px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-full shadow-lg">
                                    <span class="font-semibold">🔄 Rechargez la page pour voir les nouvelles commandes</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        // Animation de compteur
        function animateCounter(element, targetValue) {
            const currentValue = parseInt(element.textContent.replace(/[^\d]/g, ''));
            const increment = (targetValue - currentValue) / 20;
            let current = currentValue;
            
            const timer = setInterval(() => {
                current += increment;
                if (Math.abs(current - targetValue) < Math.abs(increment)) {
                    current = targetValue;
                    clearInterval(timer);
                }
                
                if (targetValue > 1000) {
                    element.textContent = Math.floor(current).toLocaleString();
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 50);
        }

        // Fermer la modale en cliquant à l'extérieur
        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Fermer la modale avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
                closeDeleteModal();
            }
        });

        // Animations avancées au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes statistiques
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px) scale(0.9)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0) scale(1)';
                }, index * 150);
            });

            // Animation des lignes du tableau
            const tableRows = document.querySelectorAll('.table-row');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease-out';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 500 + (index * 50));
            });

            // Effet de brillance sur les cartes
            setInterval(() => {
                statCards.forEach(card => {
                    if (Math.random() > 0.7) {
                        card.style.transform = 'scale(1.02)';
                        setTimeout(() => {
                            card.style.transform = 'scale(1)';
                        }, 200);
                    }
                });
            }, 3000);

            // Effet de survol amélioré pour les boutons
            const buttons = document.querySelectorAll('button, a[class*="bg-gradient"]');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', () => {
                    if (!button.disabled) {
                        button.style.transform = 'translateY(-2px) scale(1.05)';
                        button.style.boxShadow = '0 20px 40px rgba(0,0,0,0.2)';
                    }
                });
                
                button.addEventListener('mouseleave', () => {
                    if (!button.disabled) {
                        button.style.transform = 'translateY(0) scale(1)';
                        button.style.boxShadow = '';
                    }
                });
            });

            // Animation de compteur pour les statistiques
            const counters = document.querySelectorAll('.stat-card .text-4xl');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^\d]/g, ''));
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    if (target > 1000) {
                        counter.textContent = Math.floor(current).toLocaleString();
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 20);
            });

            // Effet de particules sur le header
            const header = document.querySelector('header');
            const createParticle = () => {
                const particle = document.createElement('div');
                particle.className = 'absolute w-1 h-1 bg-blue-400 rounded-full opacity-70';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animation = 'float 4s linear infinite';
                header.appendChild(particle);
                
                setTimeout(() => {
                    particle.remove();
                }, 4000);
            };

            setInterval(createParticle, 500);

            // Effet de recherche en temps réel
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
            searchInput?.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchInput.style.background = 'linear-gradient(90deg, rgba(59, 130, 246, 0.1), rgba(255, 255, 255, 0.9))';
                
                searchTimeout = setTimeout(() => {
                    searchInput.style.background = '';
                }, 1000);
            });

            // Effet de loading sur le formulaire
            const form = document.querySelector('form');
            form?.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = `
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Recherche...
                `;
                submitBtn.disabled = true;
                
                // Rétablir après 2 secondes si la page ne se recharge pas
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            });

            // Animation des icônes
            const icons = document.querySelectorAll('i[class*="fas"]');
            icons.forEach(icon => {
                icon.addEventListener('mouseenter', () => {
                    icon.style.transform = 'rotate(360deg) scale(1.2)';
                    icon.style.transition = 'transform 0.5s ease';
                });
                
                icon.addEventListener('mouseleave', () => {
                    icon.style.transform = 'rotate(0deg) scale(1)';
                });
            });

            // Effet de pulsation pour les nouveaux éléments
            const newItems = document.querySelectorAll('[class*="animate-pulse"]');
            setInterval(() => {
                newItems.forEach(item => {
                    item.style.boxShadow = '0 0 30px rgba(239, 68, 68, 0.6)';
                    setTimeout(() => {
                        item.style.boxShadow = '0 0 15px rgba(239, 68, 68, 0.3)';
                    }, 500);
                });
            }, 1000);

            console.log('🎨 Interface premium chargée avec succès!');
        });

        // Fonction pour copier les informations
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('📋 Informations copiées!', 'success');
            });
        }

        // Confirmation de sortie si une suppression est en cours
        window.addEventListener('beforeunload', (e) => {
            if (commandeToDelete && !document.getElementById('deleteModal').classList.contains('hidden')) {
                e.preventDefault();
                e.returnValue = 'Une suppression est en cours. Êtes-vous sûr de vouloir quitter ?';
            }
        });
    </script>
</body>
</html>