<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Configuration des alertes
$config_alerts = [
    'retard_seuil' => 9 * 3600, // 9h00
    'duree_max_legale' => 8 * 3600, // 8h
    'absence_alerte' => 2, // 2 jours
    'heures_sup_seuil' => 35 * 3600 // 35h/semaine
];

// Récupération des employés avec statistiques
$employes_query = "
    SELECT e.*, 
           COUNT(DISTINCT DATE(p.created_at)) as jours_presence_mois,
           AVG(
               CASE WHEN p.type = 'sortie' THEN 
                   TIMESTAMPDIFF(SECOND, 
                       (SELECT created_at FROM pointages p2 WHERE p2.employe_id = e.id AND p2.type = 'entree' AND DATE(p2.created_at) = DATE(p.created_at) ORDER BY p2.created_at DESC LIMIT 1),
                       p.created_at
                   )
               END
           ) as heures_moyenne_jour
    FROM employes e
    LEFT JOIN pointages p ON e.id = p.employe_id 
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY e.id
";
$employes = $conn->query($employes_query)->fetchAll(PDO::FETCH_ASSOC);

// Paramètres de filtrage
$employe_id = $_GET['employe_id'] ?? null;
$date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$departement = $_GET['departement'] ?? '';
$vue_type = $_GET['vue_type'] ?? 'journaliere';

// Récupération des données selon les filtres
$pointages = [];
$statistiques = [];

if ($employe_id) {
    // Requête optimisée avec cache
    $cache_key = "pointages_{$employe_id}_{$date_debut}_{$date_fin}";
    
    $stmt = $conn->prepare("
        SELECT p.*, e.nom, e.departement,
               LAG(p.created_at) OVER (PARTITION BY p.employe_id, DATE(p.created_at) ORDER BY p.created_at) as precedent
        FROM pointages p
        JOIN employes e ON p.employe_id = e.id  
        WHERE p.employe_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
        LIMIT 1000
    ");
    $stmt->execute([$employe_id, $date_debut, $date_fin]);
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculs analytiques avancés
    $statistiques = calculerStatistiquesAvancees($conn, $employe_id, $date_debut, $date_fin);
}

// Fonction de calcul des statistiques
function calculerStatistiquesAvancees($conn, $employe_id, $debut, $fin) {
    $stats = [];
    
    // Heures par jour avec détection d'anomalies
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as jour,
               SUM(CASE WHEN type = 'entree' THEN -1 ELSE 1 END * UNIX_TIMESTAMP(created_at)) / 3600 as heures_brutes,
               COUNT(*) as nb_pointages,
               MIN(CASE WHEN type = 'entree' THEN TIME(created_at) END) as premiere_entree,
               MAX(CASE WHEN type = 'sortie' THEN TIME(created_at) END) as derniere_sortie
        FROM pointages 
        WHERE employe_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY jour
    ");
    $stmt->execute([$employe_id, $debut, $fin]);
    $jours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul précis des heures travaillées
    foreach ($jours as &$jour) {
        $jour['heures_travaillees'] = calculerHeuresJour($conn, $employe_id, $jour['jour']);
        $jour['retard'] = strtotime($jour['premiere_entree']) > strtotime('09:00:00');
        $jour['depassement'] = $jour['heures_travaillees'] > 8;
        $jour['anomalie'] = $jour['nb_pointages'] % 2 != 0; // Nombre impair de pointages
    }
    
    $stats['jours'] = $jours;
    $stats['total_heures'] = array_sum(array_column($jours, 'heures_travaillees'));
    $stats['nb_retards'] = count(array_filter($jours, fn($j) => $j['retard']));
    $stats['nb_depassements'] = count(array_filter($jours, fn($j) => $j['depassement']));
    $stats['taux_presence'] = count($jours) / max(1, (strtotime($fin) - strtotime($debut)) / 86400) * 100;
    
    return $stats;
}

function calculerHeuresJour($conn, $employe_id, $date) {
    $stmt = $conn->prepare("
        SELECT type, created_at FROM pointages 
        WHERE employe_id = ? AND DATE(created_at) = ? 
        ORDER BY created_at
    ");
    $stmt->execute([$employe_id, $date]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $heures = 0;
    $entree = null;
    
    foreach ($points as $p) {
        if ($p['type'] === 'entree') {
            $entree = strtotime($p['created_at']);
        } elseif ($p['type'] === 'sortie' && $entree) {
            $heures += (strtotime($p['created_at']) - $entree) / 3600;
            $entree = null;
        }
    }
    
    return round($heures, 2);
}

// Génération des alertes intelligentes
$alertes = [];
if ($employe_id && !empty($statistiques)) {
    if ($statistiques['nb_retards'] > 3) {
        $alertes[] = ['type' => 'warning', 'message' => 'Retards fréquents détectés (>' . $statistiques['nb_retards'] . ' fois)'];
    }
    if ($statistiques['taux_presence'] < 80) {
        $alertes[] = ['type' => 'danger', 'message' => 'Taux de présence faible: ' . round($statistiques['taux_presence'], 1) . '%'];
    }
    if ($statistiques['total_heures'] > 40) {
        $alertes[] = ['type' => 'info', 'message' => 'Heures supplémentaires: ' . round($statistiques['total_heures'] - 35, 1) . 'h'];
    }
}

// Données pour les graphiques (format JSON)
$chart_data = [
    'heures_par_jour' => array_column($statistiques['jours'] ?? [], 'heures_travaillees'),
    'labels_jours' => array_map(fn($j) => date('d/m', strtotime($j)), array_column($statistiques['jours'] ?? [], 'jour')),
    'retards' => array_column($statistiques['jours'] ?? [], 'retard')
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pointage Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .success-card { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .warning-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .loading { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header avec navigation -->
<header class="gradient-bg text-white shadow-lg">
    <div class="container mx-auto px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <i class="fas fa-clock text-2xl"></i>
                <h1 class="text-2xl font-bold">TimeTracker Pro</h1>
            </div>
            <nav class="flex space-x-6">
                <a href="#" class="hover:text-blue-200 transition-colors">Dashboard</a>
                <a href="#" class="hover:text-blue-200 transition-colors">Employés</a>
                <a href="#" class="hover:text-blue-200 transition-colors">Rapports</a>
                <a href="#" class="hover:text-blue-200 transition-colors">Paramètres</a>
            </nav>
        </div>
    </div>
</header>

<div class="container mx-auto px-6 py-8">
    
    <!-- Filtres avancés -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-blue-600"></i>
            Filtres avancés
        </h2>
        
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé</label>
                <select name="employe_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="this.form.submit()">
                    <option value="">-- Tous les employés --</option>
                    <?php foreach ($employes as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id'] == $employe_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nom']) ?> 
                            <?= $e['departement'] ? "({$e['departement']})" : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                <input type="date" name="date_debut" value="<?= $date_debut ?>" 
                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                <input type="date" name="date_fin" value="<?= $date_fin ?>" 
                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type de vue</label>
                <select name="vue_type" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="journaliere" <?= $vue_type == 'journaliere' ? 'selected' : '' ?>>Journalière</option>
                    <option value="hebdomadaire" <?= $vue_type == 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                    <option value="mensuelle" <?= $vue_type == 'mensuelle' ? 'selected' : '' ?>>Mensuelle</option>
                </select>
            </div>
            
            <div class="lg:col-span-4 flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                    <i class="fas fa-search mr-2"></i>
                    Analyser
                </button>
                
                <button type="button" onclick="exportData()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Exporter
                </button>
                
                <button type="button" onclick="toggleRealTime()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Temps réel
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($alertes)): ?>
    <!-- Alertes intelligentes -->
    <div class="mb-8">
        <?php foreach ($alertes as $alerte): ?>
            <div class="alert alert-<?= $alerte['type'] ?> bg-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-100 border border-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-400 text-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-700 px-4 py-3 rounded mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle mr-3"></i>
                <?= htmlspecialchars($alerte['message']) ?>
                <button onclick="this.parentElement.style.display='none'" class="ml-auto">
                    <i class="fas fa-times hover:text-gray-800"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($employe_id && !empty($statistiques)): ?>
    
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="success-card text-white p-6 rounded-lg shadow-lg card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100">Total Heures</p>
                    <p class="text-3xl font-bold"><?= round($statistiques['total_heures'], 1) ?>h</p>
                </div>
                <i class="fas fa-clock text-4xl text-blue-200"></i>
            </div>
        </div>
        
        <div class="stat-card text-white p-6 rounded-lg shadow-lg card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-pink-100">Taux Présence</p>
                    <p class="text-3xl font-bold"><?= round($statistiques['taux_presence'], 1) ?>%</p>
                </div>
                <i class="fas fa-chart-line text-4xl text-pink-200"></i>
            </div>
        </div>
        
        <div class="warning-card text-white p-6 rounded-lg shadow-lg card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100">Retards</p>
                    <p class="text-3xl font-bold"><?= $statistiques['nb_retards'] ?></p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-orange-200"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-purple-500 to-pink-500 text-white p-6 rounded-lg shadow-lg card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100">Dépassements</p>
                    <p class="text-3xl font-bold"><?= $statistiques['nb_depassements'] ?></p>
                </div>
                <i class="fas fa-clock text-4xl text-purple-200"></i>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-chart-bar mr-2 text-blue-600"></i>
                Heures par jour
            </h3>
            <canvas id="heuresChart" height="300"></canvas>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-green-600"></i>
                Répartition présence
            </h3>
            <canvas id="presenceChart" height="300"></canvas>
        </div>
    </div>

    <!-- Tableau détaillé avec pagination -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-table mr-2 text-gray-600"></i>
                    Détail des pointages
                </h3>
                <div class="flex items-center space-x-4">
                    <input type="text" id="searchTable" placeholder="Rechercher..." 
                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <select id="filterType" class="px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Tous types</option>
                        <option value="entree">Entrées</option>
                        <option value="sortie">Sorties</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="pointagesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(0)">
                            Date/Heure <i class="fas fa-sort ml-1"></i>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(1)">
                            Type <i class="fas fa-sort ml-1"></i>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Géolocalisation
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Durée session
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Statut
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                    <?php 
                    $current_session_start = null;
                    foreach ($pointages as $index => $p): 
                        $is_late = false;
                        $session_duration = '';
                        
                        if ($p['type'] === 'entree') {
                            $current_session_start = strtotime($p['created_at']);
                            $is_late = date('H:i', $current_session_start) > '09:00';
                        } elseif ($p['type'] === 'sortie' && $current_session_start) {
                            $session_duration = gmdate('H\h i\m', strtotime($p['created_at']) - $current_session_start);
                            $current_session_start = null;
                        }
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors" data-type="<?= $p['type'] ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= date('d/m/Y H:i:s', strtotime($p['created_at'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $p['type'] === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <i class="fas fa-<?= $p['type'] === 'entree' ? 'sign-in-alt' : 'sign-out-alt' ?> mr-1"></i>
                                    <?= ucfirst($p['type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($p['geoloc']) && str_contains($p['geoloc'], ',')): 
                                    [$lat, $lon] = explode(',', $p['geoloc']);
                                ?>
                                    <a href="https://maps.google.com/?q=<?= $lat ?>,<?= $lon ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 flex items-center">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        Voir carte
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">Non disponible</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $session_duration ?: '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($is_late): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>
                                        Retard
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>
                                        OK
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editPointage(<?= $p['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePointage(<?= $p['id'] ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Précédent
                    </button>
                    <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Suivant
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Affichage de <span class="font-medium">1</span> à <span class="font-medium"><?= count($pointages) ?></span> 
                            sur <span class="font-medium"><?= count($pointages) ?></span> résultats
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" id="pagination">
                            <!-- Pagination générée par JavaScript -->
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Scripts JavaScript avancés -->
<script>
// Configuration globale
const CONFIG = {
    chartData: <?= json_encode($chart_data) ?>,
    employe_id: <?= json_encode($employe_id) ?>,
    real_time: false,
    refresh_interval: null
};

// Initialisation des graphiques
document.addEventListener('DOMContentLoaded', function() {
    if (CONFIG.employe_id) {
        initCharts();
        initTableFeatures();
        checkNotificationPermission();
    }
});

// Graphique des heures par jour
function initCharts() {
    // Graphique en barres
    const ctx1 = document.getElementById('heuresChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: CONFIG.chartData.labels_jours,
            datasets: [{
                label: 'Heures travaillées',
                data: CONFIG.chartData.heures_par_jour,
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 12,
                    ticks: {
                        callback: function(value) {
                            return value + 'h';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + 'h';
                        }
                    }
                }
            }
        }
    });
    
    // Graphique en secteurs pour la présence
    const ctx2 = document.getElementById('presenceChart').getContext('2d');
    const totalJours = CONFIG.chartData.labels_jours.length;
    const joursPresents = CONFIG.chartData.heures_par_jour.filter(h => h > 0).length;
    
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Présent', 'Absent'],
            datasets: [{
                data: [joursPresents, totalJours - joursPresents],
                backgroundColor: ['#10B981', '#EF4444'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Fonctionnalités avancées du tableau
function initTableFeatures() {
    const searchInput = document.getElementById('searchTable');
    const filterType = document.getElementById('filterType');
    const tableBody = document.getElementById('tableBody');
    
    // Recherche en temps réel
    searchInput.addEventListener('input', function() {
        filterTable();
    });
    
    filterType.addEventListener('change', function() {
        filterTable();
    });
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const typeFilter = filterType.value;
        const rows = tableBody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const type = row.dataset.type;
            
            const matchSearch = searchTerm === '' || text.includes(searchTerm);
            const matchType = typeFilter === '' || type === typeFilter;
            
            row.style.display = matchSearch && matchType ? '' : 'none';
        });
    }
}

// Tri des colonnes
let sortDirection = {};
function sortTable(columnIndex) {
    const table = document.getElementById('pointagesTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const direction = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
    sortDirection[columnIndex] = direction;
    
    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();
        
        if (columnIndex === 0) { // Date
            return direction === 'asc' ? 
                new Date(aVal) - new Date(bVal) : 
                new Date(bVal) - new Date(aVal);
        }
        
        return direction === 'asc' ? 
            aVal.localeCompare(bVal) : 
            bVal.localeCompare(aVal);
    });
    
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
}

// Gestion temps réel
function toggleRealTime() {
    CONFIG.real_time = !CONFIG.real_time;
    const button = event.target.closest('button');
    
    if (CONFIG.real_time) {
        button.innerHTML = '<i class="fas fa-pause mr-2"></i>Pause';
        button.classList.remove('bg-purple-600', 'hover:bg-purple-700');
        button.classList.add('bg-red-600', 'hover:bg-red-700');
        
        CONFIG.refresh_interval = setInterval(refreshData, 30000); // 30 secondes
        showNotification('Mode temps réel activé', 'success');
    } else {
        button.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Temps réel';
        button.classList.remove('bg-red-600', 'hover:bg-red-700');
        button.classList.add('bg-purple-600', 'hover:bg-purple-700');
        
        clearInterval(CONFIG.refresh_interval);
        showNotification('Mode temps réel désactivé', 'info');
    }
}

// Actualisation des données
async function refreshData() {
    if (!CONFIG.employe_id) return;
    
    try {
        const response = await fetch(`api/pointages.php?employe_id=${CONFIG.employe_id}&action=refresh`);
        const data = await response.json();
        
        if (data.new_pointages && data.new_pointages.length > 0) {
            updateTable(data.new_pointages);
            showNotification(`${data.new_pointages.length} nouveau(x) pointage(s)`, 'info');
        }
    } catch (error) {
        console.error('Erreur lors du rafraîchissement:', error);
    }
}

// Mise à jour du tableau
function updateTable(newPointages) {
    const tbody = document.getElementById('tableBody');
    
    newPointages.forEach(pointage => {
        const row = createTableRow(pointage);
        tbody.insertBefore(row, tbody.firstChild);
    });
}

// Création d'une ligne de tableau
function createTableRow(pointage) {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 transition-colors animate-pulse';
    row.dataset.type = pointage.type;
    
    const isLate = pointage.type === 'entree' && new Date(pointage.created_at).getHours() > 9;
    
    row.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm font-medium text-gray-900">
                ${new Date(pointage.created_at).toLocaleString('fr-FR')}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${pointage.type === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                <i class="fas fa-${pointage.type === 'entree' ? 'sign-in-alt' : 'sign-out-alt'} mr-1"></i>
                ${pointage.type.charAt(0).toUpperCase() + pointage.type.slice(1)}
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            ${pointage.geoloc ? `<a href="https://maps.google.com/?q=${pointage.geoloc}" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center"><i class="fas fa-map-marker-alt mr-1"></i>Voir carte</a>` : '<span class="text-gray-400">Non disponible</span>'}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
        <td class="px-6 py-4 whitespace-nowrap">
            ${isLate ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fas fa-clock mr-1"></i>Retard</span>' : '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-check mr-1"></i>OK</span>'}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <button onclick="editPointage(${pointage.id})" class="text-blue-600 hover:text-blue-900 mr-3">
                <i class="fas fa-edit"></i>
            </button>
            <button onclick="deletePointage(${pointage.id})" class="text-red-600 hover:text-red-900">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    return row;
}

// Gestion des notifications
function checkNotificationPermission() {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
}

function showNotification(message, type = 'info') {
    // Notification navigateur
    if ("Notification" in window && Notification.permission === "granted") {
        new Notification("TimeTracker Pro", {
            body: message,
            icon: '/favicon.ico',
            badge: '/favicon.ico'
        });
    }
    
    // Notification sur la page
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    } text-white`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation-triangle' : 'info'} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Suppression automatique
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Fonctions d'édition et suppression
function editPointage(id) {
    // Modal d'édition
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Modifier le pointage</h3>
            <form id="editForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date et heure</label>
                    <input type="datetime-local" id="editDateTime" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select id="editType" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="entree">Entrée</option>
                        <option value="sortie">Sortie</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-500 hover:text-gray-700">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Sauvegarder
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Gestion de la soumission
    document.getElementById('editForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const response = await fetch('api/pointages.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id,
                    datetime: document.getElementById('editDateTime').value,
                    type: document.getElementById('editType').value
                })
            });
            
            if (response.ok) {
                showNotification('Pointage modifié avec succès', 'success');
                modal.remove();
                location.reload();
            } else {
                throw new Error('Erreur lors de la modification');
            }
        } catch (error) {
            showNotification('Erreur lors de la modification', 'error');
        }
    });
}

function deletePointage(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce pointage ?')) {
        fetch(`api/pointages.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => {
            if (response.ok) {
                showNotification('Pointage supprimé', 'success');
                location.reload();
            } else {
                throw new Error('Erreur lors de la suppression');
            }
        })
        .catch(error => {
            showNotification('Erreur lors de la suppression', 'error');
        });
    }
}

// Export des données
function exportData() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Exporter les données</h3>
            <div class="space-y-4">
                <button onclick="exportToPDF()" class="w-full flex items-center justify-center px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-file-pdf mr-2"></i>
                    Export PDF
                </button>
                <button onclick="exportToExcel()" class="w-full flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-file-excel mr-2"></i>
                    Export Excel
                </button>
                <button onclick="exportToCSV()" class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-file-csv mr-2"></i>
                    Export CSV
                </button>
                <button onclick="this.closest('.fixed').remove()" class="w-full px-4 py-3 text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg">
                    Annuler
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function exportToPDF() {
    const url = `exportp_pdf.php?employe_id=${CONFIG.employe_id}&date_debut=${document.querySelector('[name="date_debut"]').value}&date_fin=${document.querySelector('[name="date_fin"]').value}`;
    window.open(url, '_blank');
    document.querySelector('.fixed').remove();
}

function exportToExcel() {
    const url = `export_excel.php?employe_id=${CONFIG.employe_id}&date_debut=${document.querySelector('[name="date_debut"]').value}&date_fin=${document.querySelector('[name="date_fin"]').value}`;
    window.open(url, '_blank');
    document.querySelector('.fixed').remove();
}

function exportToCSV() {
    const url = `export_csv.php?employe_id=${CONFIG.employe_id}&date_debut=${document.querySelector('[name="date_debut"]').value}&date_fin=${document.querySelector('[name="date_fin"]').value}`;
    window.open(url, '_blank');
    document.querySelector('.fixed').remove();
}

// PWA - Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registered: ', registration);
            })
            .catch(function(registrationError) {
                console.log('ServiceWorker registration failed: ', registrationError);
            });
    });
}

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'f':
                e.preventDefault();
                document.getElementById('searchTable').focus();
                break;
            case 'r':
                e.preventDefault();
                if (CONFIG.employe_id) refreshData();
                break;
            case 'e':
                e.preventDefault();
                exportData();
                break;
        }
    }
});

// Sauvegarde automatique des préférences
function savePreferences() {
    const prefs = {
        vue_type: document.querySelector('[name="vue_type"]').value,
        real_time: CONFIG.real_time
    };
    localStorage.setItem('timetracker_prefs', JSON.stringify(prefs));
}

function loadPreferences() {
    const prefs = JSON.parse(localStorage.getItem('timetracker_prefs') || '{}');
    if (prefs.vue_type) {
        document.querySelector('[name="vue_type"]').value = prefs.vue_type;
    }
}

// Chargement des préférences au démarrage
document.addEventListener('DOMContentLoaded', loadPreferences);
</script>

<!-- Styles CSS additionnels pour les animations -->
<style>
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes fadeInUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.animate-slide-in-right {
    animation: slideInRight 0.3s ease-out;
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .lg\:col-span-4 {
        grid-column: span 2;
    }
}

/* Print styles */
@media print {
    .no-print { display: none !important; }
    .bg-gradient-to-r { background: #f3f4f6 !important; }
    .text-white { color: #000 !important; }
}
</style>

</body>
</html>