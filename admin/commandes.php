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

// Fonction pour mettre à jour automatiquement le champ vu_admin après 1 heure
function updateOldOrdersVuStatus($conn) {
    try {
        $stmt = $conn->prepare("
            UPDATE commandes 
            SET vu_admin = 1 
            WHERE vu_admin = 0 
            AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 1
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

// Mettre à jour le statut "vu" des anciennes commandes automatiquement
updateOldOrdersVuStatus($conn);

// Gestion de la modification AJAX
if (isset($_POST['action']) && $_POST['action'] === 'modifier' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    
    try {
        $id = $_POST['id'];
        $statut = $_POST['statut'] ?? '';
        $vu_admin = isset($_POST['vu_admin']) && $_POST['vu_admin'] === '1' ? 1 : 0;

        $stmt = $conn->prepare("UPDATE commandes SET statut = :statut, vu_admin = :vu_admin WHERE id = :id");
        $result = $stmt->execute([
            'statut' => $statut,
            'vu_admin' => $vu_admin,
            'id' => $id
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Commande modifiée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification: ' . $e->getMessage()]);
    }
    exit;
}

// Gestion pour récupérer une commande (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'get_commande' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = :id");
        $stmt->execute(['id' => $_POST['id']]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande) {
            echo json_encode(['success' => true, 'data' => $commande]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
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

// Statistiques améliorées
$total_cmd = count($commandes);
$nouvelles_cmd = count(array_filter($commandes, fn($c) => !$c['vu_admin']));
$cmd_aujourdhui = count(array_filter($commandes, fn($c) => date('Y-m-d', strtotime($c['created_at'] ?? $c['date_commande'] ?? 'now')) === date('Y-m-d')));
$total_ventes = array_sum(array_column($commandes, 'total'));
$moyenne_cmd = $total_cmd > 0 ? intval($total_ventes / $total_cmd) : 0;

// Liste des statuts possibles
$statuts_disponibles = ['En cours', 'Préparation en cours', 'Livré', 'Terminée', 'Annulé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        /* Cards redesignées - Plus simples et élégantes */
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 2px solid;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Couleurs de contour pour chaque card */
        .card-total { border-color: rgba(99, 102, 241, 0.4); }
        .card-nouvelles { border-color: rgba(239, 68, 68, 0.4); }
        .card-aujourdhui { border-color: rgba(6, 182, 212, 0.4); }
        .card-ventes { border-color: rgba(16, 185, 129, 0.4); }
        
        .card-total:hover { border-color: rgba(99, 102, 241, 0.7); }
        .card-nouvelles:hover { border-color: rgba(239, 68, 68, 0.7); }
        .card-aujourdhui:hover { border-color: rgba(6, 182, 212, 0.7); }
        .card-ventes:hover { border-color: rgba(16, 185, 129, 0.7); }
        
        /* Icônes colorées pour chaque card */
        .icon-total { color: #6366f1; background: rgba(99, 102, 241, 0.1); }
        .icon-nouvelles { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .icon-aujourdhui { color: #06b6d4; background: rgba(6, 182, 212, 0.1); }
        .icon-ventes { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        /* Indicateurs de tendance */
        .trend-indicator {
            width: 60px;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(90deg, transparent 0%, currentColor 100%);
            opacity: 0.6;
        }
        
        .table-row {
            transition: all 0.2s ease;
        }
        .table-row:hover {
            background-color: #f9fafb;
        }
        .action-btn {
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-voir {
            background-color: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .btn-voir:hover {
            background-color: #bbf7d0;
            border-color: #86efac;
        }
        .btn-modifier {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .btn-modifier:hover {
            background-color: #bfdbfe;
            border-color: #93c5fd;
        }
        .btn-supprimer {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-supprimer:hover {
            background-color: #fecaca;
            border-color: #fca5a5;
        }
        .status-badge {
            border-radius: 12px;
            font-weight: 500;
            font-size: 12px;
            padding: 4px 12px;
        }
        .modal-overlay {
            backdrop-filter: blur(4px);
        }
        
        /* Styles pour le modal de modification */
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .form-input {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        
        .form-input:focus {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Gestion des Commandes</h1>
                        <p class="text-lg text-gray-600 mt-1">Interface d'administration avancée</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-lg shadow-sm">
                            <span class="font-medium">Premium Dashboard</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-6">
                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-600 font-semibold mb-4">TABLEAU DE BORD</h2>
                </div>

                <!-- Statistiques Cards - Design simplifié et élégant -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <!-- Total Commandes -->
                    <div class="stat-card card-total p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 rounded-xl icon-total flex items-center justify-center mr-4">
                                        <i class="fas fa-shopping-cart text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Commandes</p>
                                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $total_cmd ?></h3>
                                    </div>
                                </div>
                                <div class="trend-indicator icon-total"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nouvelles Commandes -->
                    <div class="stat-card card-nouvelles p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 rounded-xl icon-nouvelles flex items-center justify-center mr-4">
                                        <i class="fas fa-bell text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Nouvelles</p>
                                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $nouvelles_cmd ?></h3>
                                    </div>
                                </div>
                                <div class="trend-indicator icon-nouvelles" style="width: <?= $total_cmd > 0 ? min(($nouvelles_cmd / $total_cmd) * 60, 60) : 0 ?>px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aujourd'hui -->
                    <div class="stat-card card-aujourdhui p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 rounded-xl icon-aujourdhui flex items-center justify-center mr-4">
                                        <i class="fas fa-calendar-day text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Aujourd'hui</p>
                                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $cmd_aujourdhui ?></h3>
                                    </div>
                                </div>
                                <div class="trend-indicator icon-aujourdhui" style="width: <?= $total_cmd > 0 ? min(($cmd_aujourdhui / $total_cmd) * 60, 60) : 0 ?>px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Ventes -->
                    <div class="stat-card card-ventes p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 rounded-xl icon-ventes flex items-center justify-center mr-4">
                                        <i class="fas fa-chart-line text-xl"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Ventes</p>
                                        <h3 class="text-2xl font-bold text-gray-900 mt-1 truncate"><?= number_format($total_ventes, 0, ',', ' ') ?></h3>
                                        <p class="text-sm font-medium text-gray-500 mt-1">FCFA</p>
                                    </div>
                                </div>
                                <div class="trend-indicator icon-ventes"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-300 my-8"></div>

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-600 font-semibold mb-4">ACTIONS & FILTRES</h2>
                </div>

                <!-- Filtres et Recherche -->
                <div class="bg-white/80 backdrop-blur-md border border-gray-200 rounded-lg shadow-md p-6 mb-8">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <div class="md:col-span-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Recherche</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Recherche par nom, email ou téléphone..." 
                                       value="<?= e($search) ?>"
                                       class="block w-full pl-10 pr-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                            </div>
                        </div>
                        
                        <div class="md:col-span-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Statut</label>
                            <select name="statut" class="block w-full px-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                                <option value="">Tous les statuts</option>
                                <option value="En cours" <?= $filtre_statut == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="Livré" <?= $filtre_statut == 'Livré' ? 'selected' : '' ?>>Livré</option>
                                <option value="Annulé" <?= $filtre_statut == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-lg hover:from-emerald-600 hover:to-teal-700 focus:ring-2 focus:ring-emerald-500 transition-colors font-medium">
                                Filtrer
                            </button>
                        </div>
                    </form>
                </div>

                <div class="border-t border-gray-300 my-8"></div>

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-600 font-semibold mb-4">LISTE DES COMMANDES</h2>
                </div>

                <!-- Tableau des Commandes -->
                <div class="bg-white/80 backdrop-blur-md border border-gray-200 rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/80 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-800">Commandes</h3>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50/80">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vu</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white/50 divide-y divide-gray-200" id="commandesTableBody">
                                <?php if (empty($commandes)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-20 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                    <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                                                </div>
                                                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                                <p class="text-gray-500">Essayez de modifier vos critères de recherche</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($commandes as $cmd): ?>
                                        <tr class="table-row" id="commande-<?= $cmd['id'] ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-base font-bold text-gray-800">#<?= e($cmd['id']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center mr-3">
                                                        <span class="text-white font-medium text-sm"><?= strtoupper(substr(e($cmd['nom_client']), 0, 1)) ?></span>
                                                    </div>
                                                    <span class="text-base font-medium text-gray-800"><?= e($cmd['nom_client']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-1">
                                                    <div class="text-sm text-gray-800"><?= e($cmd['email']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= e($cmd['telephone']) ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-base font-bold text-gray-800"><?= number_format($cmd['total'], 0) ?> FCFA</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $statutClass = '';
                                                switch($cmd['statut']) {
                                                    case 'En cours':
                                                        $statutClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'Livré':
                                                    case 'Terminée':
                                                        $statutClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'Annulé':
                                                        $statutClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'Préparation en cours':
                                                        $statutClass = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    default:
                                                        $statutClass = 'bg-gray-100 text-gray-800';
                                                }
                                                ?>
                                                <span class="status-badge <?= $statutClass ?>" id="statut-<?= $cmd['id'] ?>"><?= e($cmd['statut']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($cmd['vu_admin']): ?>
                                                    <span class="status-badge bg-green-100 text-green-800" id="vu-<?= $cmd['id'] ?>">Consulté</span>
                                                <?php else: ?>
                                                    <span class="status-badge bg-red-100 text-red-800" id="vu-<?= $cmd['id'] ?>">Nouveau</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-500"><?= e($cmd['date_commande'] ?? $cmd['created_at'] ?? 'Non défini') ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex space-x-2">
                                                    <a href="recu.php?id=<?= $cmd['id'] ?>" 
                                                       target="_blank"
                                                       class="action-btn btn-voir">
                                                        <i class="fas fa-eye"></i>
                                                        Voir
                                                    </a>
                                                    <button onclick="openEditModal(<?= $cmd['id'] ?>)"
                                                       class="action-btn btn-modifier">
                                                        <i class="fas fa-edit"></i>
                                                        Modifier
                                                    </button>
                                                    <button onclick="confirmDelete(<?= $cmd['id'] ?>)"
                                                       class="action-btn btn-supprimer">
                                                        <i class="fas fa-trash"></i>
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

    <!-- Modal de modification -->
    <div id="editModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="modal-content p-8 m-4 max-w-md w-full">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-edit text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Modifier la commande</h3>
                        <p class="text-gray-600" id="editCommandeInfo"></p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="editForm" class="space-y-6">
                <input type="hidden" id="editCommandeId" name="id">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Statut de la commande</label>
                    <select id="editStatut" name="statut" class="form-input block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base" required>
                        <?php foreach ($statuts_disponibles as $statut): ?>
                            <option value="<?= e($statut) ?>"><?= e($statut) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <input type="checkbox" id="editVuAdmin" name="vu_admin" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 transition-colors">
                    <label for="editVuAdmin" class="ml-3 text-sm font-medium text-gray-700">
                        <span class="flex items-center">
                            <i class="fas fa-eye text-gray-400 mr-2"></i>
                            Marquer comme consulté par l'admin
                        </span>
                    </label>
                </div>
                
                <div class="flex space-x-4 pt-4">
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 px-4 py-3 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Annuler
                    </button>
                    <button type="submit" 
                            id="saveEditBtn"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-medium hover:from-blue-600 hover:to-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 m-4 max-w-md w-full border border-gray-200 shadow-xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Confirmer la suppression</h3>
                <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement la commande :</p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
                    <p class="font-medium text-gray-800" id="deleteCommandeInfo"></p>
                </div>
                <p class="text-red-600 text-sm font-medium mb-6">Cette action est irréversible !</p>
                <div class="flex space-x-3">
                    <button onclick="closeDeleteModal()" 
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Annuler
                    </button>
                    <button onclick="deleteCommande()" 
                            id="confirmDeleteBtn"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
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
        let commandeToEdit = null;

        // Auto-refresh page every 5 minutes to update status
        setInterval(() => {
            if (confirm('Voulez-vous actualiser la page pour mettre à jour les statuts ?')) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Fonction pour ouvrir le modal de modification
        function openEditModal(id) {
            commandeToEdit = id;
            const modal = document.getElementById('editModal');
            
            // Récupérer les données de la commande via AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_commande&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commande = data.data;
                    
                    // Remplir le formulaire
                    document.getElementById('editCommandeId').value = commande.id;
                    document.getElementById('editCommandeInfo').textContent = `#${commande.id} - ${commande.nom_client}`;
                    document.getElementById('editStatut').value = commande.statut;
                    document.getElementById('editVuAdmin').checked = commande.vu_admin == 1;
                    
                    // Afficher le modal
                    modal.classList.remove('hidden');
                } else {
                    showToast('Erreur: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            });
        }

        // Fonction pour fermer le modal de modification
        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.add('hidden');
            commandeToEdit = null;
        }

        // Gestionnaire de soumission du formulaire de modification
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!commandeToEdit) return;
            
            const saveBtn = document.getElementById('saveEditBtn');
            const originalText = saveBtn.innerHTML;
            
            // Animation de chargement
            saveBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Enregistrement...
            `;
            saveBtn.disabled = true;
            
            // Récupérer les données du formulaire
            const formData = new FormData(this);
            formData.append('action', 'modifier');
            
            // Requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'affichage dans le tableau
                    updateTableRow(commandeToEdit, formData);
                    
                    showToast('Commande modifiée avec succès!', 'success');
                    closeEditModal();
                    
                    // Optionnel: actualiser les statistiques
                    updateStats();
                } else {
                    showToast('Erreur: ' + data.message, 'error');
                }
                
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        });

        // Fonction pour mettre à jour une ligne du tableau
        function updateTableRow(commandeId, formData) {
            const row = document.getElementById('commande-' + commandeId);
            if (!row) return;
            
            // Mettre à jour le statut
            const statutElement = document.getElementById('statut-' + commandeId);
            const newStatut = formData.get('statut');
            statutElement.textContent = newStatut;
            
            // Mettre à jour la classe CSS du statut
            statutElement.className = 'status-badge';
            switch(newStatut) {
                case 'En cours':
                    statutElement.className += ' bg-yellow-100 text-yellow-800';
                    break;
                case 'Livré':
                case 'Terminée':
                    statutElement.className += ' bg-green-100 text-green-800';
                    break;
                case 'Annulé':
                    statutElement.className += ' bg-red-100 text-red-800';
                    break;
                case 'Préparation en cours':
                    statutElement.className += ' bg-blue-100 text-blue-800';
                    break;
                default:
                    statutElement.className += ' bg-gray-100 text-gray-800';
            }
            
            // Mettre à jour le statut "vu"
            const vuElement = document.getElementById('vu-' + commandeId);
            const vuAdmin = formData.get('vu_admin') === '1';
            if (vuAdmin) {
                vuElement.textContent = 'Consulté';
                vuElement.className = 'status-badge bg-green-100 text-green-800';
            } else {
                vuElement.textContent = 'Nouveau';
                vuElement.className = 'status-badge bg-red-100 text-red-800';
            }
            
            // Animation de mise à jour
            row.style.backgroundColor = '#f0fdf4';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);
        }

        // Fonction pour afficher la modale de confirmation de suppression
        function confirmDelete(id) {
            commandeToDelete = id;
            const modal = document.getElementById('deleteModal');
            const commandeRow = document.getElementById('commande-' + id);
            const nomClient = commandeRow.querySelector('.text-base.font-medium.text-gray-800').textContent;
            
            document.getElementById('deleteCommandeInfo').textContent = `Commande #${id} - ${nomClient}`;
            modal.classList.remove('hidden');
        }

        // Fonction pour fermer la modale de suppression
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
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
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        row.remove();
                        updateStats();
                    }, 300);
                    
                    showToast('Commande supprimée avec succès!', 'success');
                    closeDeleteModal();
                } else {
                    showToast('Erreur: ' + data.message, 'error');
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }

        // Fonction pour afficher les notifications toast
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-all duration-300 font-medium max-w-sm`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="${icon} mr-3"></i>
                    <span>${message}</span>
                    <button onclick="this.closest('div').remove()" class="ml-4 text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }

        // Fonction pour mettre à jour les statistiques
        function updateStats() {
            const remainingRows = document.querySelectorAll('#commandesTableBody tr:not([colspan])');
            const totalCommandes = remainingRows.length;
            
            // Compter les nouvelles commandes
            let nouvellesCommandes = 0;
            let commandesAujourdhui = 0;
            
            remainingRows.forEach(row => {
                // Compter les nouvelles
                const vuCell = row.cells[5];
                if (vuCell && vuCell.textContent.includes('Nouveau')) {
                    nouvellesCommandes++;
                }
                
                // Compter aujourd'hui (vous devrez adapter selon votre format de date)
                const dateCell = row.cells[6];
                if (dateCell && dateCell.textContent.includes(new Date().toISOString().split('T')[0])) {
                    commandesAujourdhui++;
                }
            });
            
            // Mettre à jour les cards de statistiques
            const statCards = document.querySelectorAll('.stat-card');
            
            // Total
            if (statCards[0]) {
                statCards[0].querySelector('.text-2xl.font-bold').textContent = totalCommandes;
            }
            
            // Nouvelles
            if (statCards[1]) {
                statCards[1].querySelector('.text-2xl.font-bold').textContent = nouvellesCommandes;
                const trendIndicator = statCards[1].querySelector('.trend-indicator');
                const newWidth = totalCommandes > 0 ? Math.min((nouvellesCommandes / totalCommandes) * 60, 60) : 0;
                trendIndicator.style.width = newWidth + 'px';
            }
            
            // Aujourd'hui
            if (statCards[2]) {
                statCards[2].querySelector('.text-2xl.font-bold').textContent = commandesAujourdhui;
                const trendIndicator = statCards[2].querySelector('.trend-indicator');
                const newWidth = totalCommandes > 0 ? Math.min((commandesAujourdhui / totalCommandes) * 60, 60) : 0;
                trendIndicator.style.width = newWidth + 'px';
            }
            
            // Vérifier s'il n'y a plus de commandes
            if (totalCommandes === 0) {
                const tbody = document.getElementById('commandesTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                <p class="text-gray-500">Toutes les commandes ont été supprimées</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        // Animation au chargement des cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
            
            // Animation des indicateurs de tendance
            setTimeout(() => {
                const trendIndicators = document.querySelectorAll('.trend-indicator');
                trendIndicators.forEach((indicator, index) => {
                    const originalWidth = indicator.style.width;
                    indicator.style.width = '0px';
                    setTimeout(() => {
                        indicator.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                        indicator.style.width = originalWidth || '60px';
                    }, 200 + (index * 100));
                });
            }, 800);
        });

        // Fermer les modales en cliquant à l'extérieur
        document.getElementById('editModal').addEventListener('click', (e) => {
            if (e.target.id === 'editModal') {
                closeEditModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Fermer les modales avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (!document.getElementById('editModal').classList.contains('hidden')) {
                    closeEditModal();
                }
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    closeDeleteModal();
                }
            }
        });

        // Fonction pour vérifier et mettre à jour automatiquement les statuts
        function checkStatusUpdates() {
            // Cette fonction peut être appelée périodiquement pour vérifier les mises à jour
            fetch(window.location.href + '?ajax_check_status=1')
            .then(response => response.json())
            .then(data => {
                if (data.updated_count > 0) {
                    showToast(`${data.updated_count} commande(s) mise(s) à jour automatiquement`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.log('Vérification des statuts échouée:', error);
            });
        }

        // Vérifier les mises à jour de statut toutes les 10 minutes
        setInterval(checkStatusUpdates, 600000); // 10 minutes

        // Notification en temps réel pour les nouvelles commandes
        function checkNewOrders() {
            const currentCount = parseInt(document.querySelector('.stat-card .text-2xl.font-bold').textContent);
            
            fetch(window.location.href + '?ajax_count=1')
            .then(response => response.json())
            .then(data => {
                if (data.count > currentCount) {
                    showToast(`${data.count - currentCount} nouvelle(s) commande(s) reçue(s)!`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                }
            })
            .catch(error => {
                console.log('Vérification des nouvelles commandes échouée:', error);
            });
        }

        // Vérifier les nouvelles commandes toutes les 2 minutes
        setInterval(checkNewOrders, 120000); // 2 minutes
    </script>
</body>
</html>