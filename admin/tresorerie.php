<?php
session_start();
require_once '../config.php';
require_once 'permissions.php';

// Vérifier authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier les permissions
requireAccess($conn, $_SESSION['admin_id'], 'finances');

$date_filter = $_GET['date'] ?? date('Y-m-d');

// Statistiques du jour
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as nb_commandes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Espèces' THEN total ELSE 0 END), 0) as total_especes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Carte bancaire' THEN total ELSE 0 END), 0) as total_cartes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Mobile Money' THEN total ELSE 0 END), 0) as total_mobile,
        COALESCE(SUM(total), 0) as total_ventes
    FROM commandes
    WHERE DATE(date_commande) = ? AND statut_paiement = 'Payé'
");
$stmt->execute([$date_filter]);
$stats_jour = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques mensuelles
$stmt = $conn->query("
    SELECT
        COALESCE(SUM(total), 0) as ca_mois,
        COUNT(*) as nb_commandes_mois
    FROM commandes
    WHERE MONTH(date_commande) = MONTH(CURDATE())
    AND YEAR(date_commande) = YEAR(CURDATE())
    AND statut_paiement = 'Payé'
");
$stats_mois = $stmt->fetch(PDO::FETCH_ASSOC);

// Évolution 7 derniers jours
$stmt = $conn->query("
    SELECT
        DATE(date_commande) as date,
        COALESCE(SUM(total), 0) as total_jour
    FROM commandes
    WHERE DATE(date_commande) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND statut_paiement = 'Payé'
    GROUP BY DATE(date_commande)
    ORDER BY date ASC
");
$evolution_7j = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trésorerie - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        /* Scrollbar pour light theme */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #cbd5e1 0%, #94a3b8 100%);
            border-radius: 5px;
            border: 2px solid #f1f5f9;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
        }

        /* Scrollbar pour dark mode */
        @media (prefers-color-scheme: dark) {
            ::-webkit-scrollbar-track {
                background: #1e293b;
            }
            ::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, #475569 0%, #334155 100%);
                border-color: #1e293b;
            }
            ::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(180deg, #64748b 0%, #475569 100%);
            }
        }

        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <!-- Navigation Finances -->
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center space-x-4">
                            <h1 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-wallet mr-2 text-teal-600"></i>
                                Gestion Trésorerie
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Tableau de bord
                                </a>
                                <a href="facturation.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-1"></i>Facturation
                                </a>
                                <a href="rapports.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                                <a href="tresorerie.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <input type="date" id="dateSelector" value="<?= $date_filter ?>" class="px-3 py-2 border rounded-lg text-sm" onchange="window.location.href='?date='+this.value">
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- KPIs Trésorerie -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Espèces</h3>
                            <p class="card-value"><?= number_format($stats_jour['total_especes']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Cartes Bancaires</h3>
                            <p class="card-value"><?= number_format($stats_jour['total_cartes']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-purple">
                        <div class="icon-wrapper icon-purple">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Mobile Money</h3>
                            <p class="card-value"><?= number_format($stats_jour['total_mobile']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-teal">
                        <div class="icon-wrapper icon-teal">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Ventes</h3>
                            <p class="card-value"><?= number_format($stats_jour['total_ventes']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>
                </div>

                <!-- Résumé Caisse -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Détails Caisse du Jour -->
                    <div class="dashboard-card card-blue">
                        <h3 class="text-lg font-semibold mb-6 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-blue mr-3">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            Caisse du Jour
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-700">Nombre de transactions</span>
                                <span class="font-semibold text-lg"><?= number_format($stats_jour['nb_commandes']) ?></span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                <span class="text-gray-700">Total Espèces</span>
                                <span class="font-semibold text-lg text-green-600"><?= number_format($stats_jour['total_especes']) ?> FCFA</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-gray-700">Total Cartes</span>
                                <span class="font-semibold text-lg text-blue-600"><?= number_format($stats_jour['total_cartes']) ?> FCFA</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                <span class="text-gray-700">Total Mobile Money</span>
                                <span class="font-semibold text-lg text-purple-600"><?= number_format($stats_jour['total_mobile']) ?> FCFA</span>
                            </div>
                            <hr class="my-4">
                            <div class="flex justify-between items-center p-4 bg-gradient-to-r from-teal-50 to-cyan-50 rounded-lg">
                                <span class="text-gray-900 font-medium text-lg">Total Encaissé</span>
                                <span class="font-bold text-2xl text-teal-600"><?= number_format($stats_jour['total_ventes']) ?> FCFA</span>
                            </div>
                        </div>
                    </div>

                    <!-- Évolution 7 jours -->
                    <div class="dashboard-card card-green">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-green mr-3">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            Évolution sur 7 jours
                        </h3>
                        <div class="chart-container">
                            <canvas id="chartEvolution"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Statistiques Mensuelles -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">CA Mensuel</h3>
                            <p class="card-value"><?= number_format($stats_mois['ca_mois']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA (<?= date('F Y') ?>)</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-indigo">
                        <div class="icon-wrapper icon-indigo">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Commandes du Mois</h3>
                            <p class="card-value"><?= number_format($stats_mois['nb_commandes_mois']) ?></p>
                            <p class="card-subtitle text-gray-600">Transactions</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Graphique Évolution 7 jours
        const ctxEvolution = document.getElementById('chartEvolution').getContext('2d');
        const dataEvolution = <?= json_encode($evolution_7j) ?>;

        new Chart(ctxEvolution, {
            type: 'bar',
            data: {
                labels: dataEvolution.map(d => new Date(d.date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })),
                datasets: [{
                    label: 'Ventes (FCFA)',
                    data: dataEvolution.map(d => d.total_jour),
                    backgroundColor: 'rgba(20, 184, 166, 0.8)',
                    borderColor: 'rgb(20, 184, 166)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' FCFA';
                            }
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>
