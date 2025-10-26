<?php
// admin_newsletter_analytics.php
require_once '../config.php';
session_start();

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Paramètres de période
$period = isset($_GET['period']) ? $_GET['period'] : '30';
$date_start = date('Y-m-d', strtotime("-{$period} days"));
$date_end = date('Y-m-d');

if (isset($_GET['date_start']) && isset($_GET['date_end'])) {
    $date_start = $_GET['date_start'];
    $date_end = $_GET['date_end'];
}

try {
    // Statistiques générales
    $general_stats = $conn->prepare("
        SELECT 
            COUNT(DISTINCT nc.id) as total_campaigns,
            COUNT(DISTINCT CASE WHEN nc.status = 'sent' THEN nc.id END) as sent_campaigns,
            SUM(nc.total_recipients) as total_recipients,
            SUM(nc.sent_count) as total_sent,
            SUM(nc.opened_count) as total_opened,
            SUM(nc.clicked_count) as total_clicked,
            COUNT(DISTINCT nt.subscriber_id) as unique_openers,
            COUNT(DISTINCT CASE WHEN nt.clicked_at IS NOT NULL THEN nt.subscriber_id END) as unique_clickers
        FROM newsletter_campaigns nc
        LEFT JOIN newsletter_tracking nt ON nc.id = nt.campaign_id
        WHERE nc.created_at BETWEEN ? AND ?
    ");
    $general_stats->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $stats = $general_stats->fetch();

    // Évolution quotidienne des envois
    $daily_sends = $conn->prepare("
        SELECT 
            DATE(nt.sent_at) as date,
            COUNT(*) as sends,
            COUNT(CASE WHEN nt.opened_at IS NOT NULL THEN 1 END) as opens,
            COUNT(CASE WHEN nt.clicked_at IS NOT NULL THEN 1 END) as clicks
        FROM newsletter_tracking nt
        WHERE nt.sent_at BETWEEN ? AND ?
        GROUP BY DATE(nt.sent_at)
        ORDER BY date ASC
    ");
    $daily_sends->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $daily_data = $daily_sends->fetchAll();

    // Top campagnes par performance
    $top_campaigns = $conn->prepare("
        SELECT 
            nc.name,
            nc.subject,
            nc.sent_count,
            nc.opened_count,
            nc.clicked_count,
            (nc.opened_count / NULLIF(nc.sent_count, 0)) * 100 as open_rate,
            (nc.clicked_count / NULLIF(nc.sent_count, 0)) * 100 as click_rate,
            nc.sent_at
        FROM newsletter_campaigns nc
        WHERE nc.status = 'sent' 
        AND nc.sent_at BETWEEN ? AND ?
        AND nc.sent_count > 0
        ORDER BY (nc.opened_count / NULLIF(nc.sent_count, 0)) DESC
        LIMIT 10
    ");
    $top_campaigns->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $campaigns_data = $top_campaigns->fetchAll();

    // Analyse des domaines d'email
    $domain_analysis = $conn->prepare("
        SELECT 
            SUBSTRING_INDEX(email, '@', -1) as domain,
            COUNT(*) as subscribers,
            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as active_subscribers,
            AVG(total_opens) as avg_opens,
            AVG(total_clicks) as avg_clicks
        FROM newsletter
        GROUP BY domain
        HAVING subscribers >= 5
        ORDER BY subscribers DESC
        LIMIT 10
    ");
    $domain_analysis->execute();
    $domains_data = $domain_analysis->fetchAll();

    // Analyse des heures d'ouverture
    $hourly_opens = $conn->prepare("
        SELECT 
            HOUR(opened_at) as hour,
            COUNT(*) as opens
        FROM newsletter_tracking
        WHERE opened_at BETWEEN ? AND ?
        GROUP BY HOUR(opened_at)
        ORDER BY hour
    ");
    $hourly_opens->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $hourly_data = $hourly_opens->fetchAll();

    // Croissance des abonnés
    $subscriber_growth = $conn->prepare("
        SELECT 
            DATE(date_inscription) as date,
            COUNT(*) as new_subscribers,
            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as active_new
        FROM newsletter
        WHERE date_inscription BETWEEN ? AND ?
        GROUP BY DATE(date_inscription)
        ORDER BY date ASC
    ");
    $subscriber_growth->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $growth_data = $subscriber_growth->fetchAll();

    // Segments les plus performants
    $segment_performance = $conn->prepare("
        SELECT 
            ns.name,
            COUNT(DISTINCT nss.subscriber_id) as subscribers,
            AVG(n.total_opens) as avg_opens,
            AVG(n.total_clicks) as avg_clicks,
            COUNT(CASE WHEN n.statut = 'actif' THEN 1 END) as active_count
        FROM newsletter_segments ns
        JOIN newsletter_subscriber_segments nss ON ns.id = nss.segment_id
        JOIN newsletter n ON nss.subscriber_id = n.id
        WHERE ns.is_active = 1
        GROUP BY ns.id, ns.name
        ORDER BY avg_opens DESC
        LIMIT 10
    ");
    $segment_performance->execute();
    $segments_data = $segment_performance->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur analytics: " . $e->getMessage());
    $stats = ['total_campaigns' => 0, 'sent_campaigns' => 0, 'total_recipients' => 0, 'total_sent' => 0, 'total_opened' => 0, 'total_clicked' => 0, 'unique_openers' => 0, 'unique_clickers' => 0];
    $daily_data = [];
    $campaigns_data = [];
    $domains_data = [];
    $hourly_data = [];
    $growth_data = [];
    $segments_data = [];
}

// Calculer les taux
$open_rate = $stats['total_sent'] > 0 ? ($stats['total_opened'] / $stats['total_sent']) * 100 : 0;
$click_rate = $stats['total_sent'] > 0 ? ($stats['total_clicked'] / $stats['total_sent']) * 100 : 0;
$engagement_rate = $stats['total_sent'] > 0 ? ($stats['unique_openers'] / $stats['total_sent']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .metric-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            border-color: #d1d5db;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .metric-card.positive { border-color: #10b981; }
        .metric-card.warning { border-color: #f59e0b; }
        .metric-card.danger { border-color: #ef4444; }
        .metric-card.info { border-color: #3b82f6; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-chart-line text-blue-600 mr-3"></i>
                        Analytics Newsletter
                    </h1>
                    <p class="text-gray-600">Analysez la performance de vos campagnes email</p>
                </div>
                <div class="flex gap-3">
                    <a href="admin_newsletter_campaigns.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                    <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-download mr-2"></i>Exporter
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres de période -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">Période :</label>
                    <select name="period" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="7" <?= $period === '7' ? 'selected' : '' ?>>7 derniers jours</option>
                        <option value="30" <?= $period === '30' ? 'selected' : '' ?>>30 derniers jours</option>
                        <option value="90" <?= $period === '90' ? 'selected' : '' ?>>3 derniers mois</option>
                        <option value="365" <?= $period === '365' ? 'selected' : '' ?>>12 derniers mois</option>
                        <option value="custom" <?= isset($_GET['date_start']) ? 'selected' : '' ?>>Personnalisée</option>
                    </select>
                </div>
                
                <div id="customPeriod" class="flex items-center gap-2 <?= isset($_GET['date_start']) ? '' : 'hidden' ?>">
                    <label class="text-sm font-medium text-gray-700">Du :</label>
                    <input type="date" name="date_start" value="<?= htmlspecialchars($date_start) ?>" class="px-3 py-2 border border-gray-300 rounded-md">
                    <label class="text-sm font-medium text-gray-700">Au :</label>
                    <input type="date" name="date_end" value="<?= htmlspecialchars($date_end) ?>" class="px-3 py-2 border border-gray-300 rounded-md">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Appliquer</button>
                </div>
            </form>
        </div>

        <!-- Métriques principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="metric-card info">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500"><?= number_format($stats['sent_campaigns']) ?> campagnes</p>
                    </div>
                    <i class="fas fa-paper-plane text-3xl text-blue-500"></i>
                </div>
            </div>

            <div class="metric-card positive">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Taux d'Ouverture</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($open_rate, 1) ?>%</p>
                        <p class="text-xs text-gray-500"><?= number_format($stats['total_opened']) ?> ouvertures</p>
                    </div>
                    <i class="fas fa-envelope-open text-3xl text-green-500"></i>
                </div>
            </div>

            <div class="metric-card warning">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Taux de Clic</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($click_rate, 1) ?>%</p>
                        <p class="text-xs text-gray-500"><?= number_format($stats['total_clicked']) ?> clics</p>
                    </div>
                    <i class="fas fa-mouse-pointer text-3xl text-orange-500"></i>
                </div>
            </div>

            <div class="metric-card info">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Engagement</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($engagement_rate, 1) ?>%</p>
                        <p class="text-xs text-gray-500"><?= number_format($stats['unique_openers']) ?> utilisateurs actifs</p>
                    </div>
                    <i class="fas fa-heart text-3xl text-purple-500"></i>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Évolution des envois -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                    Évolution des Envois
                </h3>
                <div class="chart-container">
                    <canvas id="sendsChart"></canvas>
                </div>
            </div>

            <!-- Répartition par heure -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-clock text-orange-500 mr-2"></i>
                    Ouvertures par Heure
                </h3>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Croissance des abonnés -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user-plus text-green-500 mr-2"></i>
                    Croissance des Abonnés
                </h3>
                <div class="chart-container">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <!-- Top domaines -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-at text-purple-500 mr-2"></i>
                    Top Domaines Email
                </h3>
                <div class="chart-container">
                    <canvas id="domainsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tableaux de performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top campagnes -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    Top Campagnes
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr class="text-gray-600 text-xs uppercase">
                                <th class="py-2 px-3 text-left">Campagne</th>
                                <th class="py-2 px-3 text-center">Ouverture</th>
                                <th class="py-2 px-3 text-center">Clic</th>
                                <th class="py-2 px-3 text-center">Envois</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach (array_slice($campaigns_data, 0, 8) as $campaign): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-3">
                                    <div class="font-medium text-gray-900 truncate max-w-xs" title="<?= htmlspecialchars($campaign['name']) ?>">
                                        <?= htmlspecialchars($campaign['name']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate" title="<?= htmlspecialchars($campaign['subject']) ?>">
                                        <?= htmlspecialchars($campaign['subject']) ?>
                                    </div>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        <?= $campaign['open_rate'] >= 25 ? 'bg-green-100 text-green-800' : 
                                           ($campaign['open_rate'] >= 15 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= number_format($campaign['open_rate'], 1) ?>%
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        <?= $campaign['click_rate'] >= 5 ? 'bg-green-100 text-green-800' : 
                                           ($campaign['click_rate'] >= 2 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= number_format($campaign['click_rate'], 1) ?>%
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center text-gray-600">
                                    <?= number_format($campaign['sent_count']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($campaigns_data)): ?>
                            <tr>
                                <td colspan="4" class="py-8 px-3 text-center text-gray-500">
                                    <i class="fas fa-inbox text-2xl mb-2 text-gray-300"></i>
                                    <p>Aucune campagne dans cette période</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Performance des segments -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-layer-group text-indigo-500 mr-2"></i>
                    Performance des Segments
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr class="text-gray-600 text-xs uppercase">
                                <th class="py-2 px-3 text-left">Segment</th>
                                <th class="py-2 px-3 text-center">Abonnés</th>
                                <th class="py-2 px-3 text-center">Moy. Ouvertures</th>
                                <th class="py-2 px-3 text-center">Moy. Clics</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach (array_slice($segments_data, 0, 8) as $segment): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-3">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($segment['name']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= number_format($segment['active_count']) ?> actifs
                                    </div>
                                </td>
                                <td class="py-2 px-3 text-center text-gray-600">
                                    <?= number_format($segment['subscribers']) ?>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="text-green-600 font-medium">
                                        <?= number_format($segment['avg_opens'], 1) ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="text-blue-600 font-medium">
                                        <?= number_format($segment['avg_clicks'], 1) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($segments_data)): ?>
                            <tr>
                                <td colspan="4" class="py-8 px-3 text-center text-gray-500">
                                    <i class="fas fa-layer-group text-2xl mb-2 text-gray-300"></i>
                                    <p>Aucun segment configuré</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Insights et recommandations -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                Insights et Recommandations
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $insights = [];
                
                // Analyse du taux d'ouverture
                if ($open_rate < 15) {
                    $insights[] = [
                        'type' => 'warning',
                        'title' => 'Taux d\'ouverture faible',
                        'message' => 'Votre taux d\'ouverture est de ' . number_format($open_rate, 1) . '%. Optimisez vos objets d\'email.',
                        'icon' => 'fas fa-envelope'
                    ];
                } elseif ($open_rate > 25) {
                    $insights[] = [
                        'type' => 'success',
                        'title' => 'Excellent taux d\'ouverture',
                        'message' => 'Votre taux d\'ouverture de ' . number_format($open_rate, 1) . '% est excellent !',
                        'icon' => 'fas fa-thumbs-up'
                    ];
                }
                
                // Analyse du taux de clic
                if ($click_rate < 2) {
                    $insights[] = [
                        'type' => 'danger',
                        'title' => 'Taux de clic à améliorer',
                        'message' => 'Votre taux de clic est de ' . number_format($click_rate, 1) . '%. Améliorez vos CTA.',
                        'icon' => 'fas fa-mouse-pointer'
                    ];
                }
                
                // Analyse de la croissance
                $recent_growth = count(array_filter($growth_data, function($g) {
                    return strtotime($g['date']) > strtotime('-7 days');
                }));
                
                if ($recent_growth === 0) {
                    $insights[] = [
                        'type' => 'info',
                        'title' => 'Croissance des abonnés',
                        'message' => 'Aucune nouvelle inscription ces 7 derniers jours. Boostez vos formulaires !',
                        'icon' => 'fas fa-user-plus'
                    ];
                }
                
                // Analyse des horaires
                $best_hours = array_slice(array_column($hourly_data, 'hour'), 0, 3);
                if (!empty($best_hours)) {
                    $insights[] = [
                        'type' => 'success',
                        'title' => 'Meilleurs créneaux',
                        'message' => 'Vos emails sont le plus ouverts entre ' . implode('h, ', $best_hours) . 'h.',
                        'icon' => 'fas fa-clock'
                    ];
                }
                
                // Limiter à 6 insights
                $insights = array_slice($insights, 0, 6);
                ?>
                
                <?php foreach ($insights as $insight): ?>
                <div class="p-4 rounded-lg border-l-4 
                    <?= $insight['type'] === 'success' ? 'bg-green-50 border-green-400' : 
                       ($insight['type'] === 'warning' ? 'bg-yellow-50 border-yellow-400' : 
                       ($insight['type'] === 'danger' ? 'bg-red-50 border-red-400' : 'bg-blue-50 border-blue-400')) ?>">
                    <div class="flex items-start">
                        <i class="<?= $insight['icon'] ?> text-lg mr-3 mt-1 
                            <?= $insight['type'] === 'success' ? 'text-green-600' : 
                               ($insight['type'] === 'warning' ? 'text-yellow-600' : 
                               ($insight['type'] === 'danger' ? 'text-red-600' : 'text-blue-600')) ?>"></i>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-1"><?= $insight['title'] ?></h4>
                            <p class="text-sm text-gray-600"><?= $insight['message'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($insights)): ?>
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Collectez plus de données pour obtenir des insights personnalisés</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Préparer les données pour les graphiques
    const dailyData = <?= json_encode($daily_data) ?>;
    const hourlyData = <?= json_encode($hourly_data) ?>;
    const growthData = <?= json_encode($growth_data) ?>;
    const domainsData = <?= json_encode($domains_data) ?>;

    // Configuration commune Chart.js
    Chart.defaults.font.family = 'Inter, system-ui, -apple-system, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.legend.display = false;

    // Graphique évolution des envois
    const sendsCtx = document.getElementById('sendsChart').getContext('2d');
    new Chart(sendsCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(d => new Date(d.date).toLocaleDateString('fr-FR', {month: 'short', day: 'numeric'})),
            datasets: [{
                label: 'Envois',
                data: dailyData.map(d => d.sends),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true
            }, {
                label: 'Ouvertures',
                data: dailyData.map(d => d.opens),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top' },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });

    // Graphique horaire
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    const hourlyLabels = Array.from({length: 24}, (_, i) => i + 'h');
    const hourlyValues = hourlyLabels.map(hour => {
        const h = parseInt(hour);
        const data = hourlyData.find(d => d.hour === h);
        return data ? data.opens : 0;
    });

    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hourlyLabels,
            datasets: [{
                data: hourlyValues,
                backgroundColor: '#f59e0b',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // Graphique croissance
    const growthCtx = document.getElementById('growthChart').getContext('2d');
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: growthData.map(d => new Date(d.date).toLocaleDateString('fr-FR', {month: 'short', day: 'numeric'})),
            datasets: [{
                label: 'Nouveaux abonnés',
                data: growthData.map(d => d.new_subscribers),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // Graphique domaines
    const domainsCtx = document.getElementById('domainsChart').getContext('2d');
    new Chart(domainsCtx, {
        type: 'doughnut',
        data: {
            labels: domainsData.map(d => d.domain),
            datasets: [{
                data: domainsData.map(d => d.subscribers),
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Gestion du filtre de période personnalisée
    document.querySelector('select[name="period"]').addEventListener('change', function() {
        const customPeriod = document.getElementById('customPeriod');
        if (this.value === 'custom') {
            customPeriod.classList.remove('hidden');
        } else {
            customPeriod.classList.add('hidden');
        }
    });

    // Export du rapport
    function exportReport() {
        const period = new URLSearchParams(window.location.search).get('period') || '30';
        window.open(`admin_newsletter_export.php?type=analytics&period=${period}`, '_blank');
    }

    // Rafraîchissement automatique des données toutes les 5 minutes
    setInterval(() => {
        const url = new URL(window.location);
        const lastRefresh = url.searchParams.get('refresh');
        const now = Date.now();
        
        if (!lastRefresh || (now - parseInt(lastRefresh)) > 300000) { // 5 minutes
            url.searchParams.set('refresh', now.toString());
            window.location.href = url.toString();
        }
    }, 300000);
    </script>
</body>
</html>