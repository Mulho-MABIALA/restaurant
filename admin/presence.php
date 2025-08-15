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
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR p.created_at IS NULL
    GROUP BY e.id
    ORDER BY e.nom
";
$employes = $conn->query($employes_query)->fetchAll(PDO::FETCH_ASSOC);

// Paramètres de filtrage
$employe_id = $_GET['employe_id'] ?? $_SESSION['admin_id'] ?? null; // Par défaut, employé connecté
$date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$vue_type = $_GET['vue_type'] ?? 'journaliere';

// Récupération des données selon les filtres - TOUJOURS afficher quelque chose
$pointages = [];
$statistiques = [];

// Si aucun employé sélectionné, utiliser l'employé connecté
if (!$employe_id && isset($_SESSION['admin_id'])) {
    $employe_id = $_SESSION['admin_id'];
}

if ($employe_id) {
    // Requête pour récupérer les pointages
    $stmt = $conn->prepare("
        SELECT p.*, e.nom
        FROM pointages p
        LEFT JOIN employes e ON p.employe_id = e.id  
        WHERE p.employe_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
        LIMIT 1000
    ");
    $stmt->execute([$employe_id, $date_debut, $date_fin]);
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculs analytiques avancés
    $statistiques = calculerStatistiquesAvancees($conn, $employe_id, $date_debut, $date_fin);
} else {
    // Afficher tous les pointages récents si aucun employé spécifique
    $stmt = $conn->prepare("
        SELECT p.*, e.nom
        FROM pointages p
        LEFT JOIN employes e ON p.employe_id = e.id  
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$date_debut, $date_fin]);
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction de calcul des statistiques
function calculerStatistiquesAvancees($conn, $employe_id, $debut, $fin) {
    $stats = [];
    
    // Heures par jour avec détection d'anomalies
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as jour,
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
        $jour['retard'] = $jour['premiere_entree'] && strtotime($jour['premiere_entree']) > strtotime('09:00:00');
        $jour['depassement'] = $jour['heures_travaillees'] > 8;
        $jour['anomalie'] = $jour['nb_pointages'] % 2 != 0; // Nombre impair de pointages
    }
    
    $stats['jours'] = $jours;
    $stats['total_heures'] = array_sum(array_column($jours, 'heures_travaillees'));
    $stats['nb_retards'] = count(array_filter($jours, fn($j) => $j['retard']));
    $stats['nb_depassements'] = count(array_filter($jours, fn($j) => $j['depassement']));
    
    // Calcul du taux de présence
    $jours_periode = max(1, (strtotime($fin) - strtotime($debut)) / 86400 + 1);
    $jours_weekend = 0;
    for ($i = 0; $i < $jours_periode; $i++) {
        $date_check = date('N', strtotime($debut . " +$i days"));
        if ($date_check >= 6) $jours_weekend++; // Samedi et dimanche
    }
    $jours_ouvrables = $jours_periode - $jours_weekend;
    $stats['taux_presence'] = count($jours) / max(1, $jours_ouvrables) * 100;
    
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
        $alertes[] = ['type' => 'warning', 'message' => 'Retards fréquents détectés (' . $statistiques['nb_retards'] . ' fois)'];
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
                <a href="badgeuse.php" class="hover:text-blue-200 transition-colors">Badgeuse</a>
                <a href="presence.php" class="hover:text-blue-200 transition-colors">Dashboard</a>
                <a href="#" class="hover:text-blue-200 transition-colors">Rapports</a>
                <a href="logout.php" class="hover:text-blue-200 transition-colors">Déconnexion</a>
            </nav>
        </div>
    </div>
</header>

<div class="container mx-auto px-6 py-8">
    
    <!-- Message informatif si aucun pointage -->
    <?php if (empty($pointages)): ?>
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            <span>Aucun pointage trouvé pour la période sélectionnée. 
                <?php if (!$employe_id): ?>
                    Sélectionnez un employé pour voir ses pointages.
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
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
                            <?= htmlspecialchars($e['nom'] ?? 'Employé #' . $e['id']) ?> 
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
                
                <?php if (!empty($pointages)): ?>
                <button type="button" onclick="exportData()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Exporter
                </button>
                <?php endif; ?>
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
    <?php if (!empty($statistiques['jours'])): ?>
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
    <?php endif; ?>

    <?php endif; ?>

    <!-- Tableau détaillé -->
    <?php if (!empty($pointages)): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-table mr-2 text-gray-600"></i>
                    Détail des pointages (<?= count($pointages) ?> enregistrements)
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
            <table class="min-w-full divide-y divide-gray-200" id="pointagesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heure</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Département</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pointages as $p): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= date('H:i:s', strtotime($p['created_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $p['type'] === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= ucfirst($p['type']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($p['nom'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Filtrage tableau
    document.getElementById('searchTable').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        const rows = document.querySelectorAll('#pointagesTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    });

    document.getElementById('filterType').addEventListener('change', function() {
        const type = this.value;
        const rows = document.querySelectorAll('#pointagesTable tbody tr');
        rows.forEach(row => {
            if (!type || row.querySelector('td:nth-child(3)').textContent.toLowerCase() === type) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Export CSV
    function exportData() {
        let csv = 'Date,Heure,Type,Employé,Département\n';
        document.querySelectorAll('#pointagesTable tbody tr').forEach(row => {
            if (row.style.display !== 'none') {
                const cols = row.querySelectorAll('td');
                csv += Array.from(cols).map(td => `"${td.textContent.trim()}"`).join(',') + '\n';
            }
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'pointages.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Chart.js - Heures par jour
    <?php if (!empty($statistiques['jours'])): ?>
    const ctx = document.getElementById('heuresChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['labels_jours']) ?>,
            datasets: [{
                label: 'Heures travaillées',
                data: <?= json_encode($chart_data['heures_par_jour']) ?>,
                backgroundColor: '#667eea'
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Chart.js - Répartition présence
    const ctx2 = document.getElementById('presenceChart').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Présence', 'Absence'],
            datasets: [{
                data: [
                    <?= count($statistiques['jours']) ?>,
                    <?= max(0, $jours_ouvrables - count($statistiques['jours'])) ?>
                ],
                backgroundColor: ['#4facfe', '#f5576c']
            }]
        }
    });
    <?php endif; ?>
</script>

</body>
</html>