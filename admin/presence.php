<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Configuration des alertes et seuils
$config = [
    'retard_seuil' => '09:00:00',
    'duree_max_legale' => 8,
    'absence_alerte' => 2,
    'heures_sup_seuil' => 35,
    'pause_min' => 1,
    'pause_max' => 2
];

// Paramètres de filtrage avec valeurs par défaut
$employe_id = $_GET['employe_id'] ?? null;
$date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$vue_type = $_GET['vue_type'] ?? 'journaliere';
$departement = $_GET['departement'] ?? '';

// Récupération des employés avec informations complètes
$employes_query = "
    SELECT e.*, d.nom as departement_nom,
           COUNT(DISTINCT DATE(p.created_at)) as jours_presence_mois,
           COALESCE(AVG(
               CASE WHEN p.type = 'sortie' THEN 
                   TIMESTAMPDIFF(SECOND, 
                       (SELECT created_at FROM pointages p2 
                        WHERE p2.employe_id = e.id AND p2.type = 'entree' 
                        AND DATE(p2.created_at) = DATE(p.created_at) 
                        ORDER BY p2.created_at DESC LIMIT 1),
                       p.created_at
                   ) / 3600
               END
           ), 0) as heures_moyenne_jour
    FROM employes e
    LEFT JOIN departements d ON e.departement_id = d.id
    LEFT JOIN pointages p ON e.id = p.employe_id 
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY e.id, e.nom, d.nom
    ORDER BY e.nom
";

try {
    $employes = $conn->query($employes_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employes = [];
    $error_message = "Erreur lors de la récupération des employés: " . $e->getMessage();
}

// Récupération des départements pour le filtre
$departements_query = "SELECT DISTINCT d.id, d.nom FROM departements d ORDER BY d.nom";
$departements = $conn->query($departements_query)->fetchAll(PDO::FETCH_ASSOC);

// Variables d'initialisation
$pointages = [];
$statistiques = [];
$alertes = [];

// Récupération des données selon les filtres
if ($employe_id) {
    try {
        // Requête optimisée pour les pointages
        $pointage_query = "
            SELECT p.*, e.nom as employe_nom, d.nom as departement_nom
            FROM pointages p
            INNER JOIN employes e ON p.employe_id = e.id  
            LEFT JOIN departements d ON e.departement_id = d.id
            WHERE p.employe_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at DESC
            LIMIT 1000
        ";
        $stmt = $conn->prepare($pointage_query);
        $stmt->execute([$employe_id, $date_debut, $date_fin]);
        $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculs des statistiques avancées
        $statistiques = calculerStatistiquesAvancees($conn, $employe_id, $date_debut, $date_fin, $config);
        
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
    }
} else {
    // Vue d'ensemble - tous les pointages récents
    try {
        $vue_generale = "
            SELECT p.*, e.nom as employe_nom, d.nom as departement_nom
            FROM pointages p
            INNER JOIN employes e ON p.employe_id = e.id  
            LEFT JOIN departements d ON e.departement_id = d.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            " . ($departement ? "AND d.id = ?" : "") . "
            ORDER BY p.created_at DESC
            LIMIT 200
        ";
        $stmt = $conn->prepare($vue_generale);
        $params = [$date_debut, $date_fin];
        if ($departement) {
            $params[] = $departement;
        }
        $stmt->execute($params);
        $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques globales
        $statistiques = calculerStatistiquesGlobales($conn, $date_debut, $date_fin, $departement);
        
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération des données générales: " . $e->getMessage();
    }
}

// Génération des alertes intelligentes
$alertes = genererAlertes($statistiques, $config);

// Fonction pour calculer les statistiques d'un employé
function calculerStatistiquesAvancees($conn, $employe_id, $debut, $fin, $config) {
    $stats = [
        'jours' => [],
        'total_heures' => 0,
        'nb_retards' => 0,
        'nb_depassements' => 0,
        'nb_absences' => 0,
        'taux_presence' => 0,
        'heures_supplementaires' => 0,
        'ponctualite_score' => 0
    ];
    
    try {
        // Analyse par jour
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as jour,
                   COUNT(*) as nb_pointages,
                   GROUP_CONCAT(
                       CONCAT(type, ':', TIME(created_at)) 
                       ORDER BY created_at SEPARATOR '|'
                   ) as pointages_detail
            FROM pointages 
            WHERE employe_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY jour
        ");
        $stmt->execute([$employe_id, $debut, $fin]);
        $jours_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jours_data as $jour_data) {
            $jour = analyserJour($jour_data, $config);
            $stats['jours'][] = $jour;
            
            $stats['total_heures'] += $jour['heures_travaillees'];
            if ($jour['retard']) $stats['nb_retards']++;
            if ($jour['depassement']) $stats['nb_depassements']++;
        }
        
        // Calcul des métriques globales
        $periode_jours = calculerJoursOuvrables($debut, $fin);
        $jours_travailles = count($stats['jours']);
        
        $stats['taux_presence'] = $periode_jours > 0 ? ($jours_travailles / $periode_jours) * 100 : 0;
        $stats['nb_absences'] = max(0, $periode_jours - $jours_travailles);
        $stats['heures_supplementaires'] = max(0, $stats['total_heures'] - ($jours_travailles * 8));
        $stats['ponctualite_score'] = $jours_travailles > 0 ? 
            (($jours_travailles - $stats['nb_retards']) / $jours_travailles) * 100 : 100;
            
    } catch (PDOException $e) {
        error_log("Erreur calcul statistiques: " . $e->getMessage());
    }
    
    return $stats;
}

// Fonction pour analyser une journée de travail
function analyserJour($jour_data, $config) {
    $jour = [
        'date' => $jour_data['jour'],
        'nb_pointages' => $jour_data['nb_pointages'],
        'heures_travaillees' => 0,
        'premiere_entree' => null,
        'derniere_sortie' => null,
        'retard' => false,
        'depassement' => false,
        'anomalie' => false,
        'pauses' => []
    ];
    
    // Parse des pointages
    $pointages = [];
    if ($jour_data['pointages_detail']) {
        foreach (explode('|', $jour_data['pointages_detail']) as $p) {
            list($type, $heure) = explode(':', $p, 2);
            $pointages[] = ['type' => $type, 'heure' => $heure];
        }
    }
    
    // Analyse des entrées/sorties
    $entree = null;
    $total_secondes = 0;
    
    foreach ($pointages as $p) {
        if ($p['type'] === 'entree') {
            $entree = strtotime($p['heure']);
            if (!$jour['premiere_entree']) {
                $jour['premiere_entree'] = $p['heure'];
                $jour['retard'] = strtotime($p['heure']) > strtotime($config['retard_seuil']);
            }
        } elseif ($p['type'] === 'sortie' && $entree) {
            $sortie = strtotime($p['heure']);
            $total_secondes += ($sortie - $entree);
            $jour['derniere_sortie'] = $p['heure'];
            $entree = null;
        }
    }
    
    $jour['heures_travaillees'] = round($total_secondes / 3600, 2);
    $jour['depassement'] = $jour['heures_travaillees'] > $config['duree_max_legale'];
    $jour['anomalie'] = $jour['nb_pointages'] % 2 !== 0; // Nombre impair de pointages
    
    return $jour;
}

// Calcul des jours ouvrables (lundi à vendredi)
function calculerJoursOuvrables($debut, $fin) {
    $count = 0;
    $current = strtotime($debut);
    $end = strtotime($fin);
    
    while ($current <= $end) {
        $dayOfWeek = date('N', $current);
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Lundi à vendredi
            $count++;
        }
        $current = strtotime('+1 day', $current);
    }
    
    return $count;
}

// Statistiques globales pour vue d'ensemble
function calculerStatistiquesGlobales($conn, $debut, $fin, $departement = null) {
    $stats = [
        'total_employes' => 0,
        'employes_presents_aujourd_hui' => 0,
        'total_pointages' => 0,
        'retards_aujourd_hui' => 0,
        'heures_moyennes' => 0
    ];
    
    try {
        // Nombre total d'employés
        $query = "SELECT COUNT(*) FROM employes e";
        if ($departement) {
            $query .= " WHERE e.departement_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$departement]);
        } else {
            $stmt = $conn->query($query);
        }
        $stats['total_employes'] = $stmt->fetchColumn();
        
        // Employés présents aujourd'hui
        $today = date('Y-m-d');
        $query = "
            SELECT COUNT(DISTINCT p.employe_id)
            FROM pointages p
            INNER JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.created_at) = ?
        ";
        if ($departement) {
            $query .= " AND e.departement_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$today, $departement]);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->execute([$today]);
        }
        $stats['employes_presents_aujourd_hui'] = $stmt->fetchColumn();
        
        // Total pointages période
        $query = "
            SELECT COUNT(*)
            FROM pointages p
            INNER JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
        ";
        if ($departement) {
            $query .= " AND e.departement_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$debut, $fin, $departement]);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->execute([$debut, $fin]);
        }
        $stats['total_pointages'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Erreur statistiques globales: " . $e->getMessage());
    }
    
    return $stats;
}

// Génération des alertes intelligentes
function genererAlertes($statistiques, $config) {
    $alertes = [];
    
    if (isset($statistiques['nb_retards']) && $statistiques['nb_retards'] > 3) {
        $alertes[] = [
            'type' => 'warning', 
            'icon' => 'fas fa-clock',
            'message' => "Retards fréquents détectés ({$statistiques['nb_retards']} fois cette période)"
        ];
    }
    
    if (isset($statistiques['taux_presence']) && $statistiques['taux_presence'] < 80) {
        $alertes[] = [
            'type' => 'danger', 
            'icon' => 'fas fa-exclamation-triangle',
            'message' => "Taux de présence critique: " . round($statistiques['taux_presence'], 1) . "%"
        ];
    }
    
    if (isset($statistiques['heures_supplementaires']) && $statistiques['heures_supplementaires'] > 5) {
        $alertes[] = [
            'type' => 'info', 
            'icon' => 'fas fa-plus-circle',
            'message' => "Heures supplémentaires importantes: " . round($statistiques['heures_supplementaires'], 1) . "h"
        ];
    }
    
    if (isset($statistiques['ponctualite_score']) && $statistiques['ponctualite_score'] < 70) {
        $alertes[] = [
            'type' => 'warning', 
            'icon' => 'fas fa-user-clock',
            'message' => "Score de ponctualité faible: " . round($statistiques['ponctualite_score'], 1) . "%"
        ];
    }
    
    return $alertes;
}

// Préparation des données pour les graphiques
$chart_data = [
    'heures_par_jour' => [],
    'labels_jours' => [],
    'retards' => [],
    'presence_data' => []
];

if (!empty($statistiques['jours'])) {
    foreach ($statistiques['jours'] as $jour) {
        $chart_data['labels_jours'][] = date('d/m', strtotime($jour['date']));
        $chart_data['heures_par_jour'][] = $jour['heures_travaillees'];
        $chart_data['retards'][] = $jour['retard'] ? 1 : 0;
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard TimeTracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        
        .gradient-bg { background: var(--primary-gradient); }
        .card-hover { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            backdrop-filter: blur(10px);
        }
        .card-hover:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); 
        }
        
        .success-card { background: var(--success-gradient); }
        .warning-card { background: var(--warning-gradient); }
        .info-card { background: var(--info-gradient); }
        .danger-card { background: var(--danger-gradient); }
        
        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        
        .status-indicator {
            position: relative;
            display: inline-block;
        }
        
        .status-indicator::before {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            top: 50%;
            left: -15px;
            transform: translateY(-50%);
        }
        
        .status-present::before { background-color: #10b981; }
        .status-absent::before { background-color: #ef4444; }
        .status-late::before { background-color: #f59e0b; }
        
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f7fafc;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }
        
        .alert-slide {
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">

    <!-- Header Navigation -->
    <header class="gradient-bg text-white shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="container mx-auto px-6 py-4 relative z-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="p-2 bg-white bg-opacity-20 rounded-lg">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">TimeTracker Pro</h1>
                        <p class="text-blue-100 text-sm">Tableau de bord intelligent</p>
                    </div>
                </div>
                <nav class="hidden md:flex space-x-8">
                    <a href="badgeuse.php" class="hover:text-blue-200 transition-colors duration-300 flex items-center space-x-2">
                        <i class="fas fa-fingerprint"></i>
                        <span>Badgeuse</span>
                    </a>
                    <a href="presence.php" class="text-blue-200 border-b-2 border-blue-200 pb-1 flex items-center space-x-2">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="hover:text-blue-200 transition-colors duration-300 flex items-center space-x-2">
                        <i class="fas fa-file-alt"></i>
                        <span>Rapports</span>
                    </a>
                    <a href="logout.php" class="hover:text-red-200 transition-colors duration-300 flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        
        <!-- Messages d'erreur -->
        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg fade-in">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-lg"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Message informatif si aucun pointage -->
        <?php if (empty($pointages) && !isset($error_message)): ?>
        <div class="glass-effect border border-blue-200 text-blue-700 px-6 py-4 rounded-xl mb-8 fade-in">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <div>
                        <h3 class="font-semibold">Aucune donnée trouvée</h3>
                        <p class="text-sm">
                            <?php if (!$employe_id): ?>
                                Sélectionnez un employé pour voir ses statistiques détaillées.
                            <?php else: ?>
                                Aucun pointage trouvé pour la période <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <button onclick="this.parentElement.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Panneau de filtres avancés -->
        <div class="glass-effect rounded-2xl shadow-xl p-8 mb-8 fade-in">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-sliders-h mr-3 text-blue-600"></i>
                    Filtres & Analyse
                </h2>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-600">Dernière mise à jour:</span>
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?= date('H:i') ?>
                    </span>
                </div>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6" id="filterForm">
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-user mr-2"></i>Employé
                    </label>
                    <select name="employe_id" class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
                        <option value="">🏢 Vue d'ensemble</option>
                        <?php foreach ($employes as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $e['id'] == $employe_id ? 'selected' : '' ?>>
                                👤 <?= htmlspecialchars($e['nom'] ?? 'Employé #' . $e['id']) ?> 
                                <?php if ($e['departement_nom']): ?>
                                    (<?= htmlspecialchars($e['departement_nom']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-building mr-2"></i>Département
                    </label>
                    <select name="departement" class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Tous les départements</option>
                        <?php foreach ($departements as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $departement ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-calendar mr-2"></i>Date début
                    </label>
                    <input type="date" name="date_debut" value="<?= $date_debut ?>" 
                           class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-calendar-alt mr-2"></i>Date fin
                    </label>
                    <input type="date" name="date_fin" value="<?= $date_fin ?>" 
                           class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-eye mr-2"></i>Type de vue
                    </label>
                    <select name="vue_type" class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        <option value="journaliere" <?= $vue_type == 'journaliere' ? 'selected' : '' ?>>📅 Journalière</option>
                        <option value="hebdomadaire" <?= $vue_type == 'hebdomadaire' ? 'selected' : '' ?>>📊 Hebdomadaire</option>
                        <option value="mensuelle" <?= $vue_type == 'mensuelle' ? 'selected' : '' ?>>📈 Mensuelle</option>
                    </select>
                </div>
                
                <div class="lg:col-span-5 flex flex-wrap gap-4 pt-4 border-t border-gray-200">
                    <button type="button" onclick="refreshData()" class="bg-gradient-to-r from-indigo-500 to-blue-600 text-white px-8 py-3 rounded-xl hover:from-indigo-600 hover:to-blue-700 transition-all duration-300 flex items-center shadow-lg">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Actualiser
                    </button>
                </div>
            </form>
        </div>

        <!-- Alertes intelligentes -->
        <?php if (!empty($alertes)): ?>
        <div class="mb-8 space-y-3">
            <?php foreach ($alertes as $index => $alerte): ?>
                <div class="alert-slide bg-gradient-to-r from-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-100 to-white border-l-4 border-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-500 p-4 rounded-r-xl shadow-lg" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="<?= $alerte['icon'] ?> mr-3 text-lg text-<?= $alerte['type'] == 'warning' ? 'yellow' : ($alerte['type'] == 'danger' ? 'red' : 'blue') ?>-600"></i>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($alerte['message']) ?></span>
                        </div>
                        <button onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <?php if ($employe_id && !empty($statistiques) && isset($statistiques['total_heures'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Heures -->
            <div class="success-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium mb-1">Total Heures</p>
                        <p class="text-4xl font-bold mb-1"><?= round($statistiques['total_heures'], 1) ?>h</p>
                        <div class="flex items-center text-green-200 text-xs">
                            <i class="fas fa-calendar-week mr-1"></i>
                            <span>Cette période</span>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Taux de Présence -->
            <div class="info-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium mb-1">Taux Présence</p>
                        <p class="text-4xl font-bold mb-1"><?= round($statistiques['taux_presence'], 1) ?>%</p>
                        <div class="flex items-center text-blue-200 text-xs">
                            <i class="fas fa-user-check mr-1"></i>
                            <span><?= count($statistiques['jours']) ?> jours travaillés</span>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-chart-line text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Ponctualité -->
            <div class="warning-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.2s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-pink-100 text-sm font-medium mb-1">Ponctualité</p>
                        <p class="text-4xl font-bold mb-1"><?= round($statistiques['ponctualite_score'] ?? 100, 1) ?>%</p>
                        <div class="flex items-center text-pink-200 text-xs">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span><?= $statistiques['nb_retards'] ?> retards</span>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-user-clock text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Heures Supplémentaires -->
            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.3s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium mb-1">Heures Sup.</p>
                        <p class="text-4xl font-bold mb-1"><?= round($statistiques['heures_supplementaires'], 1) ?>h</p>
                        <div class="flex items-center text-purple-200 text-xs">
                            <i class="fas fa-plus-circle mr-1"></i>
                            <span><?= $statistiques['nb_depassements'] ?> dépassements</span>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques d'analyse -->
        <?php if (!empty($statistiques['jours'])): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Graphique Heures par jour -->
            <div class="glass-effect p-8 rounded-2xl shadow-xl fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-chart-bar mr-3 text-blue-600"></i>
                        Heures par jour
                    </h3>
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        <span>Heures travaillées</span>
                    </div>
                </div>
                <div class="relative h-80">
                    <canvas id="heuresChart"></canvas>
                </div>
            </div>
            
            <!-- Graphique Répartition présence -->
            <div class="glass-effect p-8 rounded-2xl shadow-xl fade-in" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-chart-pie mr-3 text-green-600"></i>
                        Répartition présence
                    </h3>
                    <div class="text-sm text-gray-600">
                        <span>Période analysée</span>
                    </div>
                </div>
                <div class="relative h-80">
                    <canvas id="presenceChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif (!$employe_id && !empty($statistiques)): ?>
        <!-- Vue d'ensemble - KPI globaux -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="success-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Total Employés</p>
                        <p class="text-4xl font-bold"><?= $statistiques['total_employes'] ?></p>
                    </div>
                    <i class="fas fa-users text-4xl text-green-200"></i>
                </div>
            </div>
            
            <div class="info-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Présents Aujourd'hui</p>
                        <p class="text-4xl font-bold"><?= $statistiques['employes_presents_aujourd_hui'] ?></p>
                    </div>
                    <i class="fas fa-user-check text-4xl text-blue-200"></i>
                </div>
            </div>
            
            <div class="warning-card text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.2s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-pink-100 text-sm font-medium">Pointages Total</p>
                        <p class="text-4xl font-bold"><?= $statistiques['total_pointages'] ?></p>
                    </div>
                    <i class="fas fa-fingerprint text-4xl text-pink-200"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6 rounded-2xl shadow-xl card-hover fade-in" style="animation-delay: 0.3s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-100 text-sm font-medium">Taux Global</p>
                        <p class="text-4xl font-bold">
                            <?= $statistiques['total_employes'] > 0 ? round(($statistiques['employes_presents_aujourd_hui'] / $statistiques['total_employes']) * 100, 1) : 0 ?>%
                        </p>
                    </div>
                    <i class="fas fa-percentage text-4xl text-indigo-200"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tableau des pointages -->
        <?php if (!empty($pointages)): ?>
        <div class="glass-effect rounded-2xl shadow-xl overflow-hidden fade-in">
            <div class="p-8 border-b border-gray-200">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-table mr-3 text-gray-600"></i>
                            Historique des pointages
                        </h3>
                        <p class="text-gray-600 text-sm mt-1">
                            <?= count($pointages) ?> enregistrements trouvés
                            <?php if ($employe_id): ?>
                                <?php
                                $employe_info = array_filter($employes, fn($e) => $e['id'] == $employe_id);
                                $employe_info = reset($employe_info);
                                ?>
                                pour <?= htmlspecialchars($employe_info['nom'] ?? 'Employé sélectionné') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchTable" placeholder="Rechercher dans le tableau..." 
                                   class="pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                        </div>
                        <select id="filterType" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">🔍 Tous types</option>
                            <option value="entree">📍 Entrées</option>
                            <option value="sortie">📍 Sorties</option>
                        </select>
                        <button onclick="clearFilters()" class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                            <i class="fas fa-eraser mr-2"></i>Effacer
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto table-responsive">
                <table class="min-w-full divide-y divide-gray-200" id="pointagesTable">
                    <thead class="bg-gradient-to-r from-gray-50 to-blue-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-calendar mr-2"></i>Date
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-clock mr-2"></i>Heure
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-tag mr-2"></i>Type
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-user mr-2"></i>Employé
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-building mr-2"></i>Département
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-2"></i>Statut
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pointages as $index => $p): ?>
                        <tr class="hover:bg-blue-50 transition-colors duration-200 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3"></div>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                    <?= date('H:i:s', strtotime($p['created_at'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $p['type'] === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <i class="fas fa-<?= $p['type'] === 'entree' ? 'sign-in-alt' : 'sign-out-alt' ?> mr-1"></i>
                                    <?= ucfirst($p['type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white text-xs font-bold mr-3">
                                        <?= strtoupper(substr($p['employe_nom'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($p['employe_nom'] ?? 'Inconnu') ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600">
                                    <?= htmlspecialchars($p['departement_nom'] ?? 'Non assigné') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $heure_pointage = date('H:i', strtotime($p['created_at']));
                                $is_late = $p['type'] === 'entree' && $heure_pointage > '09:00';
                                ?>
                                <span class="status-indicator <?= $is_late ? 'status-late' : 'status-present' ?> text-xs font-medium">
                                    <?= $is_late ? 'Retard' : 'Normal' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination si nécessaire -->
            <?php if (count($pointages) >= 1000): ?>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">
                        Affichage limité à 1000 résultats. Affinez vos filtres pour voir plus de données.
                    </span>
                    <button onclick="exportData()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Exporter tout ↗
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Footer avec statistiques rapides -->
        <div class="mt-12 text-center">
            <div class="inline-flex items-center space-x-6 bg-white bg-opacity-60 backdrop-blur-lg rounded-xl px-8 py-4 text-sm text-gray-600">
                <span>📊 Période: <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?></span>
                <span>•</span>
                <span>🕒 Dernière mise à jour: <?= date('d/m/Y H:i') ?></span>
                <span>•</span>
                <span>👥 <?= count($employes) ?> employés au total</span>
            </div>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <script>
        // Configuration globale
        const CONFIG = {
            colors: {
                primary: '#667eea',
                success: '#11998e',
                warning: '#f093fb',
                info: '#4facfe',
                danger: '#ff9a9e'
            },
            animations: {
                duration: 300,
                easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
            }
        };

        // Filtrage avancé du tableau
        class TableFilter {
            constructor() {
                this.searchInput = document.getElementById('searchTable');
                this.typeFilter = document.getElementById('filterType');
                this.table = document.getElementById('pointagesTable');
                this.rows = this.table ? this.table.querySelectorAll('tbody tr') : [];
                
                this.init();
            }
            
            init() {
                if (this.searchInput) {
                    this.searchInput.addEventListener('input', this.debounce(() => this.filterTable(), 300));
                }
                if (this.typeFilter) {
                    this.typeFilter.addEventListener('change', () => this.filterTable());
                }
            }
            
            filterTable() {
                if (!this.table) return;
                
                const searchTerm = this.searchInput?.value.toLowerCase() || '';
                const typeFilter = this.typeFilter?.value.toLowerCase() || '';
                let visibleCount = 0;
                
                this.rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const typeCell = row.querySelector('td:nth-child(3)');
                    const rowType = typeCell ? typeCell.textContent.toLowerCase() : '';
                    
                    const matchesSearch = !searchTerm || text.includes(searchTerm);
                    const matchesType = !typeFilter || rowType.includes(typeFilter);
                    
                    if (matchesSearch && matchesType) {
                        row.style.display = '';
                        row.style.animation = 'fadeIn 0.3s ease-out';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                this.updateResultsCount(visibleCount);
            }
            
            updateResultsCount(count) {
                const header = document.querySelector('#pointagesTable').closest('.glass-effect').querySelector('h3');
                if (header) {
                    const countSpan = header.querySelector('.results-count') || document.createElement('span');
                    countSpan.className = 'results-count text-sm text-gray-500 ml-2';
                    countSpan.textContent = `(${count} résultats)`;
                    if (!header.querySelector('.results-count')) {
                        header.appendChild(countSpan);
                    }
                }
            }
            
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        }

        // Fonction pour effacer les filtres
        function clearFilters() {
            document.getElementById('searchTable').value = '';
            document.getElementById('filterType').value = '';
            new TableFilter().filterTable();
        }

        // Export des données en CSV
        function exportData() {
            if (!document.getElementById('pointagesTable')) return;
            
            let csv = 'Date,Heure,Type,Employé,Département,Statut\n';
            const rows = document.querySelectorAll('#pointagesTable tbody tr');
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cols = row.querySelectorAll('td');
                    const rowData = Array.from(cols).map(td => {
                        return `"${td.textContent.trim().replace(/"/g, '""')}"`;
                    }).join(',');
                    csv += rowData + '\n';
                }
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `pointages_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            // Notification de succès
            showNotification('Export réussi!', 'success');
        }

        // Fonction d'impression
        function printReport() {
            const printContent = document.querySelector('.container').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Rapport de Présence - TimeTracker Pro</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .no-print { display: none; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .header { text-align: center; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>TimeTracker Pro - Rapport de Présence</h1>
                        <p>Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
                    </div>
                    ${printContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Actualisation des données
        function refreshData() {
            showNotification('Actualisation en cours...', 'info');
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Système de notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg text-white transition-all duration-300 transform translate-x-full`;
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            notification.classList.add(colors[type] || colors.info);
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Graphiques Chart.js
        <?php if (!empty($statistiques['jours'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Configuration commune des graphiques
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#6B7280';
            
            // Graphique des heures par jour
            const ctxHeures = document.getElementById('heuresChart');
            if (ctxHeures) {
                new Chart(ctxHeures, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($chart_data['labels_jours']) ?>,
                        datasets: [{
                            label: 'Heures travaillées',
                            data: <?= json_encode($chart_data['heures_par_jour']) ?>,
                            borderColor: CONFIG.colors.primary,
                            backgroundColor: CONFIG.colors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: CONFIG.colors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: CONFIG.colors.primary,
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        return `${context.parsed.y}h travaillées`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 12,
                                grid: {
                                    color: '#E5E7EB'
                                }
                            }
                        }
                    }
                });
            }

            // Graphique de répartition présence
            const ctxPresence = document.getElementById('presenceChart');
            if (ctxPresence) {
                const joursOuvrables = <?= calculerJoursOuvrables($date_debut, $date_fin) ?>;
                const joursPresents = <?= count($statistiques['jours']) ?>;
                const joursAbsents = Math.max(0, joursOuvrables - joursPresents);
                
                new Chart(ctxPresence, {
                    type: 'doughnut',
                    data: {
                        labels: ['Jours présents', 'Jours absents'],
                        datasets: [{
                            data: [joursPresents, joursAbsents],
                            backgroundColor: [
                                CONFIG.colors.success,
                                CONFIG.colors.danger
                            ],
                            borderWidth: 0,
                            hoverBorderWidth: 4,
                            hoverBorderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} jours (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // Initialisation des composants
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser le filtre de tableau
            new TableFilter();
            
            // Animation des cartes KPI au scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observer toutes les cartes avec animation
            document.querySelectorAll('.fade-in').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                observer.observe(card);
            });
            
            // Auto-submit sur changement d'employé
            const employeSelect = document.querySelector('select[name="employe_id"]');
            if (employeSelect) {
                employeSelect.addEventListener('change', function() {
                    if (this.value !== '') {
                        showNotification('Chargement des données...', 'info');
                        setTimeout(() => {
                            document.getElementById('filterForm').submit();
                        }, 500);
                    }
                });
            }
            
            // Raccourcis clavier
            document.addEventListener('keydown', function(e) {
                // Ctrl + E pour exporter
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    exportData();
                }
                
                // Ctrl + P pour imprimer
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    printReport();
                }
                
                // Ctrl + R pour actualiser
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    refreshData();
                }
                
                // Échap pour effacer les filtres
                if (e.key === 'Escape') {
                    clearFilters();
                }
            });
            
            // Tooltip pour les raccourcis
            const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
            tooltipTriggers.forEach(trigger => {
                trigger.addEventListener('mouseenter', showTooltip);
                trigger.addEventListener('mouseleave', hideTooltip);
            });
        });

        // Fonctions pour les tooltips
        function showTooltip(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-800 rounded shadow-lg';
            tooltip.textContent = e.target.dataset.tooltip;
            tooltip.style.top = e.target.offsetTop - 30 + 'px';
            tooltip.style.left = e.target.offsetLeft + 'px';
            e.target.parentNode.appendChild(tooltip);
        }

        function hideTooltip(e) {
            const tooltip = e.target.parentNode.querySelector('.absolute.z-50');
            if (tooltip) {
                tooltip.remove();
            }
        }

        // Gestion de l'état de chargement
        function showLoading(element) {
            element.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Chargement...';
            element.disabled = true;
        }

        function hideLoading(element, originalText) {
            element.innerHTML = originalText;
            element.disabled = false;
        }

        // Sauvegarde automatique des préférences de filtre
        const preferences = {
            save: function(key, value) {
                try {
                    const prefs = JSON.parse(localStorage.getItem('timetracker_prefs') || '{}');
                    prefs[key] = value;
                    localStorage.setItem('timetracker_prefs', JSON.stringify(prefs));
                } catch (e) {
                    console.warn('Impossible de sauvegarder les préférences');
                }
            },
            
            load: function(key, defaultValue = null) {
                try {
                    const prefs = JSON.parse(localStorage.getItem('timetracker_prefs') || '{}');
                    return prefs[key] || defaultValue;
                } catch (e) {
                    return defaultValue;
                }
            }
        };

        // Sauvegarde des filtres à chaque changement
        document.querySelectorAll('#filterForm input, #filterForm select').forEach(input => {
            input.addEventListener('change', function() {
                preferences.save(this.name, this.value);
            });
        });

        // Chargement des préférences au démarrage
        window.addEventListener('load', function() {
            document.querySelectorAll('#filterForm input, #filterForm select').forEach(input => {
                const savedValue = preferences.load(input.name);
                if (savedValue && !input.value) {
                    input.value = savedValue;
                }
            });
        });

        // Gestion des erreurs globales
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
            showNotification('Une erreur s\'est produite. Veuillez rafraîchir la page.', 'error');
        });

        // Mode sombre (optionnel)
        const darkModeToggle = function() {
            const isDark = document.body.classList.toggle('dark');
            preferences.save('darkMode', isDark);
            showNotification(isDark ? 'Mode sombre activé' : 'Mode clair activé', 'info');
        };

        // Performance monitoring
        if ('performance' in window) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const timing = performance.timing;
                    const loadTime = timing.loadEventEnd - timing.navigationStart;
                    console.log(`Page chargée en ${loadTime}ms`);
                    
                    if (loadTime > 3000) {
                        console.warn('Temps de chargement élevé détecté');
                    }
                }, 0);
            });
        }
    </script>

    <!-- CSS additionnel pour les animations -->
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .glass-effect { background: white !important; box-shadow: none !important; }
        }
        
        .dark {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .dark .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .dark .text-gray-800 { color: white; }
        .dark .text-gray-600 { color: #cbd5e0; }
        .dark .border-gray-200 { border-color: #4a5568; }
        
        /* Animation pour les alertes qui disparaissent */
        .alert-fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.95); }
        }
        
        /* Styles pour les états de chargement */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Amélioration de l'accessibilité */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Focus visible pour l'accessibilité */
        .focus-visible:focus {
            outline: 2px solid #4f46e5;
            outline-offset: 2px;
        }
        
        /* Responsive pour très petits écrans */
        @media (max-width: 480px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .text-4xl {
                font-size: 2rem;
            }
        }
    </style>
</body>
</html>