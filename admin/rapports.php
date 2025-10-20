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

// Paramètres de date
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');

// Statistiques globales
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as nb_commandes,
        COALESCE(SUM(total), 0) as ca_total,
        COALESCE(AVG(total), 0) as panier_moyen
    FROM commandes
    WHERE DATE(date_commande) BETWEEN ? AND ?
    AND statut_paiement = 'Payé'
");
$stmt->execute([$date_debut, $date_fin]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// CA par jour
$stmt = $conn->prepare("
    SELECT
        DATE(date_commande) as jour,
        COALESCE(SUM(total), 0) as ca_jour,
        COUNT(*) as nb_commandes
    FROM commandes
    WHERE DATE(date_commande) BETWEEN ? AND ?
    AND statut_paiement = 'Payé'
    GROUP BY DATE(date_commande)
    ORDER BY jour ASC
");
$stmt->execute([$date_debut, $date_fin]);
$ca_par_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 10 plats
$stmt = $conn->prepare("
    SELECT
        cd.nom_plat as nom,
        SUM(cd.quantite) as quantite_vendue,
        SUM(cd.prix * cd.quantite) as ca_plat
    FROM commande_details cd
    JOIN commandes c ON cd.commande_id = c.id
    WHERE DATE(c.date_commande) BETWEEN ? AND ?
    AND c.statut_paiement = 'Payé'
    GROUP BY cd.nom_plat
    ORDER BY ca_plat DESC
    LIMIT 10
");
$stmt->execute([$date_debut, $date_fin]);
$top_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CA par mode de paiement
$stmt = $conn->prepare("
    SELECT
        mode_paiement,
        COUNT(*) as nb_transactions,
        COALESCE(SUM(total), 0) as ca_total
    FROM commandes
    WHERE DATE(date_commande) BETWEEN ? AND ?
    AND statut_paiement = 'Payé'
    GROUP BY mode_paiement
");
$stmt->execute([$date_debut, $date_fin]);
$ca_par_mode = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul CA quotidien moyen
$nb_jours = count($ca_par_jour) > 0 ? count($ca_par_jour) : 1;
$ca_quotidien_moyen = $stats['ca_total'] / $nb_jours;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports Financiers - Restaurant</title>
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
                                <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                                Rapports et Analyses
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Tableau de bord
                                </a>
                                <a href="facturation.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-1"></i>Facturation
                                </a>
                                <a href="rapports.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                                <a href="tresorerie.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- Filtres de période -->
                <div class="dashboard-card card-blue mb-8">
                    <div class="flex flex-wrap gap-4 items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                            <input type="date" id="dateDebut" value="<?= $date_debut ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                            <input type="date" id="dateFin" value="<?= $date_fin ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button onclick="appliquerFiltre()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                        <button onclick="exporterPDF()" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                            <i class="fas fa-file-pdf mr-2"></i>Export PDF
                        </button>
                    </div>
                </div>

                <!-- KPIs Résumé -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">CA Total</h3>
                            <p class="card-value"><?= number_format($stats['ca_total']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Commandes</h3>
                            <p class="card-value"><?= number_format($stats['nb_commandes']) ?></p>
                            <p class="card-subtitle text-gray-600">Total</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-purple">
                        <div class="icon-wrapper icon-purple">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Panier Moyen</h3>
                            <p class="card-value"><?= number_format($stats['panier_moyen']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">CA Quotidien</h3>
                            <p class="card-value"><?= number_format($ca_quotidien_moyen) ?></p>
                            <p class="card-subtitle text-gray-600">Moyenne</p>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- CA par jour -->
                    <div class="dashboard-card card-blue">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-blue mr-3">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            Évolution CA par jour
                        </h3>
                        <div class="chart-container">
                            <canvas id="chartCAJour"></canvas>
                        </div>
                    </div>

                    <!-- CA par mode de paiement -->
                    <div class="dashboard-card card-green">
                        <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-green mr-3">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            Répartition par mode de paiement
                        </h3>
                        <div class="chart-container">
                            <canvas id="chartModePaiement"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top 10 Plats -->
                <div class="dashboard-card card-orange mb-8">
                    <h3 class="text-lg font-semibold mb-6 flex items-center text-gray-900">
                        <div class="icon-wrapper icon-orange mr-3">
                            <i class="fas fa-fire"></i>
                        </div>
                        Top 10 Plats de la Période
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plat</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">CA Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($top_plats)): ?>
                                    <?php foreach ($top_plats as $index => $plat): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3">
                                                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-orange-400 to-pink-500 text-white text-xs font-bold">
                                                    <?= $index + 1 ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($plat['nom']) ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                    <?= number_format($plat['quantite_vendue']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right font-semibold text-green-600"><?= number_format($plat['ca_plat']) ?> FCFA</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                            Aucune donnée pour cette période
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

    <script>
        // CA par jour
        const ctxCAJour = document.getElementById('chartCAJour').getContext('2d');
        const dataCAJour = <?= json_encode($ca_par_jour) ?>;

        new Chart(ctxCAJour, {
            type: 'line',
            data: {
                labels: dataCAJour.map(d => new Date(d.jour).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })),
                datasets: [{
                    label: 'CA Quotidien (FCFA)',
                    data: dataCAJour.map(d => d.ca_jour),
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

        // CA par mode de paiement
        const ctxModePaiement = document.getElementById('chartModePaiement').getContext('2d');
        const dataModePaiement = <?= json_encode($ca_par_mode) ?>;

        new Chart(ctxModePaiement, {
            type: 'doughnut',
            data: {
                labels: dataModePaiement.map(d => d.mode_paiement),
                datasets: [{
                    data: dataModePaiement.map(d => d.ca_total),
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(139, 92, 246)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toLocaleString() + ' FCFA';
                            }
                        }
                    }
                }
            }
        });

        function appliquerFiltre() {
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;

            if (!dateDebut || !dateFin) {
                alert('Veuillez sélectionner les deux dates');
                return;
            }

            window.location.href = `?date_debut=${dateDebut}&date_fin=${dateFin}`;
        }

        function exporterPDF() {
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            window.open(`../../api/export_rapport.php?date_debut=${dateDebut}&date_fin=${dateFin}`, '_blank');
        }
    </script>

</body>
</html>
