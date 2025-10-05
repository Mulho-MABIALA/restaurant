<?php
session_start();
require_once '../../config.php';
require_once '../permissions.php';

// Vérifier authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Vérifier les permissions
requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Récupérer les données financières
$date_filter = $_GET['date'] ?? date('Y-m-d');

// CA du jour
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as nb_commandes,
        COALESCE(SUM(total), 0) as ca_total,
        COALESCE(AVG(total), 0) as panier_moyen
    FROM commandes
    WHERE DATE(date_commande) = ? AND statut_paiement = 'Payé'
");
$stmt->execute([$date_filter]);
$ventes_jour = $stmt->fetch(PDO::FETCH_ASSOC);

// Évolution 7 derniers jours
$stmt = $conn->query("
    SELECT
        DATE(date_commande) as date,
        COALESCE(SUM(total), 0) as ca_total
    FROM commandes
    WHERE DATE(date_commande) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND statut_paiement = 'Payé'
    GROUP BY DATE(date_commande)
    ORDER BY date ASC
");
$evolution_7j = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top plats vendus
try {
    $stmt = $conn->query("
        SELECT
            cd.nom_plat as nom,
            SUM(cd.quantite) as quantite
        FROM commande_details cd
        JOIN commandes c ON cd.commande_id = c.id
        WHERE DATE(c.date_commande) = CURDATE()
        GROUP BY cd.nom_plat
        ORDER BY quantite DESC
        LIMIT 5
    ");
    $top_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si erreur, tableau vide
    $top_plats = [];
}

// Objectif du jour
$objectif_ca = 500000; // À configurer
$progression = $ventes_jour['ca_total'] > 0 ? round(($ventes_jour['ca_total'] / $objectif_ca) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financier - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/cards-design.css">
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

        /* Chart container */
        .chart-container {
            position: relative;
            height: 250px;
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include '../sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <!-- Navigation Finances -->
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center space-x-4">
                            <h1 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-chart-line mr-2 text-green-600"></i>
                                Dashboard Financier
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Tableau de bord
                                </a>
                                <a href="facturation.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-1"></i>Facturation
                                </a>
                                <a href="rapports.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                                <a href="tresorerie.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <input type="date" id="dateSelector" class="px-3 py-2 border rounded-lg text-sm" value="<?= $date_filter ?>">
                            <button onclick="window.location.reload()" class="p-2 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- KPIs Principaux -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- CA du jour -->
                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">CA du Jour</h3>
                            <p class="card-value"><?= number_format($ventes_jour['ca_total']) ?> FCFA</p>
                            <p class="card-subtitle text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <?= $ventes_jour['nb_commandes'] ?> commandes
                            </p>
                        </div>
                    </div>

                    <!-- Panier moyen -->
                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Panier Moyen</h3>
                            <p class="card-value"><?= number_format($ventes_jour['panier_moyen']) ?> FCFA</p>
                            <p class="card-subtitle text-gray-600">
                                Par commande
                            </p>
                        </div>
                    </div>

                    <!-- Objectif -->
                    <div class="dashboard-card card-purple">
                        <div class="icon-wrapper icon-purple">
                            <i class="fas fa-target"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Objectif du Jour</h3>
                            <p class="card-value"><?= $progression ?>%</p>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-gradient-to-r from-purple-500 to-pink-600 h-2 rounded-full" style="width: <?= min(100, $progression) ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Commandes -->
                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Commandes</h3>
                            <p class="card-value"><?= $ventes_jour['nb_commandes'] ?></p>
                            <p class="card-subtitle text-gray-600">
                                Aujourd'hui
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Évolution des ventes -->
                    <div class="dashboard-card card-blue">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-blue mr-3">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            Évolution des ventes (7 jours)
                        </h3>
                        <div class="chart-container">
                            <canvas id="chartVentes"></canvas>
                        </div>
                    </div>

                    <!-- Top plats -->
                    <div class="dashboard-card card-orange">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-orange mr-3">
                                <i class="fas fa-fire"></i>
                            </div>
                            Top 5 Plats du Jour
                        </h3>
                        <div class="space-y-3">
                            <?php if (!empty($top_plats)): ?>
                                <?php foreach ($top_plats as $index => $plat): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-orange-400 to-pink-500 text-white text-xs font-bold">#<?= $index + 1 ?></span>
                                            <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($plat['nom']) ?></span>
                                        </div>
                                        <span class="text-sm font-semibold text-orange-600 bg-orange-50 px-3 py-1 rounded-full"><?= $plat['quantite'] ?> ventes</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-utensils text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-sm text-gray-500">Aucune vente aujourd'hui</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Détails par mode de paiement -->
                <div class="dashboard-card card-green mb-8">
                    <h3 class="text-lg font-semibold mb-6 flex items-center text-gray-900">
                        <div class="icon-wrapper icon-green mr-3">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        Répartition par mode de paiement
                    </h3>
                    <?php
                    $stmt = $conn->prepare("
                        SELECT
                            mode_paiement,
                            COUNT(*) as nb_transactions,
                            SUM(total) as montant_total
                        FROM commandes
                        WHERE DATE(date_commande) = ? AND statut_paiement = 'Payé'
                        GROUP BY mode_paiement
                    ");
                    $stmt->execute([$date_filter]);
                    $modes_paiement = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $icons = [
                        'Espèces' => 'fa-money-bill-wave',
                        'Carte bancaire' => 'fa-credit-card',
                        'Mobile Money' => 'fa-mobile-alt',
                        'Virement' => 'fa-exchange-alt'
                    ];

                    $colors = [
                        'Espèces' => ['bg' => 'bg-green-50', 'text' => 'text-green-600', 'icon' => 'text-green-500'],
                        'Carte bancaire' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'icon' => 'text-blue-500'],
                        'Mobile Money' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'icon' => 'text-purple-500'],
                        'Virement' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'icon' => 'text-orange-500']
                    ];
                    ?>
                    <?php if (!empty($modes_paiement)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php foreach ($modes_paiement as $mode): ?>
                                <?php
                                    $modeName = $mode['mode_paiement'];
                                    $color = $colors[$modeName] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'icon' => 'text-gray-500'];
                                    $icon = $icons[$modeName] ?? 'fa-wallet';
                                ?>
                                <div class="<?= $color['bg'] ?> border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between mb-3">
                                        <i class="fas <?= $icon ?> text-2xl <?= $color['icon'] ?>"></i>
                                        <span class="text-xs font-medium <?= $color['text'] ?> bg-white px-2 py-1 rounded-full"><?= $mode['nb_transactions'] ?> trans.</span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($modeName) ?></p>
                                    <p class="text-2xl font-bold <?= $color['text'] ?>"><?= number_format($mode['montant_total']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1">FCFA</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-wallet text-4xl text-gray-300 mb-3"></i>
                            <p class="text-sm text-gray-500">Aucune transaction enregistrée</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Graphique évolution des ventes
        const ctx = document.getElementById('chartVentes').getContext('2d');
        const evolutionData = <?= json_encode($evolution_7j) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: evolutionData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'CA (FCFA)',
                    data: evolutionData.map(d => d.ca_total),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
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

        // Date selector
        document.getElementById('dateSelector').addEventListener('change', function() {
            window.location.href = '?date=' + this.value;
        });
    </script>

</body>
</html>
