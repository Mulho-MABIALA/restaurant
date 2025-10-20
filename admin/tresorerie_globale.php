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

// Filtres de date
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Dernier jour du mois

// ==================== CALCUL DES ENTRÉES ====================
// Total des ventes (commandes payées)
$stmt_entrees = $conn->prepare("
    SELECT
        COALESCE(SUM(total), 0) as total_entrees,
        COUNT(*) as nb_commandes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Espèces' THEN total ELSE 0 END), 0) as entrees_especes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Carte bancaire' THEN total ELSE 0 END), 0) as entrees_cartes,
        COALESCE(SUM(CASE WHEN mode_paiement = 'Mobile Money' THEN total ELSE 0 END), 0) as entrees_mobile
    FROM commandes
    WHERE DATE(date_commande) BETWEEN ? AND ?
    AND statut_paiement = 'Payé'
");
$stmt_entrees->execute([$date_debut, $date_fin]);
$entrees = $stmt_entrees->fetch(PDO::FETCH_ASSOC);

// Évolution entrées par jour
$stmt_entrees_jour = $conn->prepare("
    SELECT
        DATE(date_commande) as date,
        COALESCE(SUM(total), 0) as total
    FROM commandes
    WHERE DATE(date_commande) BETWEEN ? AND ?
    AND statut_paiement = 'Payé'
    GROUP BY DATE(date_commande)
    ORDER BY date ASC
");
$stmt_entrees_jour->execute([$date_debut, $date_fin]);
$entrees_par_jour = $stmt_entrees_jour->fetchAll(PDO::FETCH_ASSOC);

// ==================== CALCUL DES SORTIES ====================
// Total des paiements fournisseurs
// Note: Adapté pour la structure existante de votre table
$stmt_sorties = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN statut = 'payee' THEN montant_ttc ELSE 0 END), 0) as total_sorties,
        COUNT(*) as nb_factures
    FROM factures_fournisseurs
    WHERE DATE(date_facture) BETWEEN ? AND ?
");
$stmt_sorties->execute([$date_debut, $date_fin]);
$sorties = $stmt_sorties->fetch(PDO::FETCH_ASSOC);

// Évolution sorties par jour
$stmt_sorties_jour = $conn->prepare("
    SELECT
        DATE(date_facture) as date,
        COALESCE(SUM(CASE WHEN statut = 'payee' THEN montant_ttc ELSE 0 END), 0) as total
    FROM factures_fournisseurs
    WHERE DATE(date_facture) BETWEEN ? AND ?
    GROUP BY DATE(date_facture)
    ORDER BY date ASC
");
$stmt_sorties_jour->execute([$date_debut, $date_fin]);
$sorties_par_jour = $stmt_sorties_jour->fetchAll(PDO::FETCH_ASSOC);

// Détail des sorties par fournisseur
$stmt_sorties_fournisseur = $conn->prepare("
    SELECT
        f.nom as fournisseur_nom,
        COALESCE(SUM(CASE WHEN ff.statut = 'payee' THEN ff.montant_ttc ELSE 0 END), 0) as total_paye,
        COUNT(ff.id) as nb_factures
    FROM factures_fournisseurs ff
    JOIN fournisseurs f ON ff.fournisseur_id = f.id
    WHERE DATE(ff.date_facture) BETWEEN ? AND ?
    GROUP BY f.id, f.nom
    ORDER BY total_paye DESC
    LIMIT 10
");
$stmt_sorties_fournisseur->execute([$date_debut, $date_fin]);
$sorties_fournisseurs = $stmt_sorties_fournisseur->fetchAll(PDO::FETCH_ASSOC);

// ==================== CALCUL DU SOLDE ====================
$solde = $entrees['total_entrees'] - $sorties['total_sorties'];
$taux_marge = $entrees['total_entrees'] > 0 ? (($solde / $entrees['total_entrees']) * 100) : 0;

// Factures fournisseurs en attente de paiement
$stmt_attente = $conn->query("
    SELECT
        COALESCE(SUM(montant_ttc), 0) as total_a_payer,
        COUNT(*) as nb_factures_attente
    FROM factures_fournisseurs
    WHERE statut IN ('en_attente', 'validee')
");
$attente_paiement = $stmt_attente->fetch(PDO::FETCH_ASSOC);

// ==================== ÉVOLUTION QUOTIDIENNE ====================
// Fusionner entrées et sorties par jour pour le graphique
$evolution_jour = [];
$dates_all = [];

// Collecter toutes les dates
foreach ($entrees_par_jour as $e) {
    $dates_all[$e['date']] = ['date' => $e['date'], 'entrees' => $e['total'], 'sorties' => 0, 'solde' => 0];
}
foreach ($sorties_par_jour as $s) {
    if (isset($dates_all[$s['date']])) {
        $dates_all[$s['date']]['sorties'] = $s['total'];
    } else {
        $dates_all[$s['date']] = ['date' => $s['date'], 'entrees' => 0, 'sorties' => $s['total'], 'solde' => 0];
    }
}

// Trier par date et calculer solde cumulé
ksort($dates_all);
$solde_cumule = 0;
foreach ($dates_all as $date => $data) {
    $solde_jour = $data['entrees'] - $data['sorties'];
    $solde_cumule += $solde_jour;
    $evolution_jour[] = [
        'date' => $date,
        'entrees' => $data['entrees'],
        'sorties' => $data['sorties'],
        'solde_jour' => $solde_jour,
        'solde_cumule' => $solde_cumule
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trésorerie Globale - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        .chart-container {
            position: relative;
            height: 350px;
        }

        .positive {
            color: #10b981;
        }

        .negative {
            color: #ef4444;
        }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
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
                                <i class="fas fa-chart-line mr-2 text-purple-600"></i>
                                Trésorerie Globale
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="finances_dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Dashboard
                                </a>
                                <a href="tresorerie_globale.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-line mr-1"></i>Trésorerie Globale
                                </a>
                                <a href="tresorerie.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie détaillée
                                </a>
                                <a href="rapports.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <label class="text-sm text-gray-600">Du</label>
                            <input type="date" id="dateDebut" value="<?= $date_debut ?>" class="px-3 py-2 border rounded-lg text-sm">
                            <label class="text-sm text-gray-600">Au</label>
                            <input type="date" id="dateFin" value="<?= $date_fin ?>" class="px-3 py-2 border rounded-lg text-sm">
                            <button onclick="filtrer()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-filter mr-1"></i>Filtrer
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- KPIs Principaux - Solde -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

                    <!-- Solde -->
                    <div class="dashboard-card <?= $solde >= 0 ? 'card-purple' : 'card-red' ?> md:col-span-2">
                        <div class="icon-wrapper <?= $solde >= 0 ? 'icon-purple' : 'icon-red' ?>">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Solde de Trésorerie</h3>
                            <p class="card-value text-3xl <?= $solde >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($solde, 0, ',', ' ') ?> FCFA
                            </p>
                            <p class="card-subtitle">
                                Marge : <?= number_format($taux_marge, 1) ?>%
                            </p>
                        </div>
                    </div>

                    <!-- Entrées -->
                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Entrées</h3>
                            <p class="card-value positive"><?= number_format($entrees['total_entrees'], 0, ',', ' ') ?> FCFA</p>
                            <p class="card-subtitle text-gray-600">
                                <?= $entrees['nb_commandes'] ?> ventes
                            </p>
                        </div>
                    </div>

                    <!-- Sorties -->
                    <div class="dashboard-card card-red">
                        <div class="icon-wrapper icon-red">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Sorties</h3>
                            <p class="card-value negative"><?= number_format($sorties['total_sorties'], 0, ',', ' ') ?> FCFA</p>
                            <p class="card-subtitle text-gray-600">
                                <?= $sorties['nb_factures'] ?> paiements
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Détail Entrées / Sorties / À Payer -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

                    <!-- Répartition Entrées -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                            Détail Entrées
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-money-bill text-green-500 mr-2"></i>
                                    Espèces
                                </span>
                                <span class="font-semibold"><?= number_format($entrees['entrees_especes'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-green-500" style="width: <?= $entrees['total_entrees'] > 0 ? ($entrees['entrees_especes']/$entrees['total_entrees'])*100 : 0 ?>%"></div>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-credit-card text-blue-500 mr-2"></i>
                                    Cartes
                                </span>
                                <span class="font-semibold"><?= number_format($entrees['entrees_cartes'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-blue-500" style="width: <?= $entrees['total_entrees'] > 0 ? ($entrees['entrees_cartes']/$entrees['total_entrees'])*100 : 0 ?>%"></div>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-mobile-alt text-purple-500 mr-2"></i>
                                    Mobile Money
                                </span>
                                <span class="font-semibold"><?= number_format($entrees['entrees_mobile'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-purple-500" style="width: <?= $entrees['total_entrees'] > 0 ? ($entrees['entrees_mobile']/$entrees['total_entrees'])*100 : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Fournisseurs (Sorties) -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-truck text-red-500 mr-2"></i>
                            Top Fournisseurs
                        </h3>
                        <div class="space-y-2">
                            <?php if (empty($sorties_fournisseurs)): ?>
                                <p class="text-gray-500 text-sm text-center py-4">Aucun paiement fournisseur</p>
                            <?php else: ?>
                                <?php foreach (array_slice($sorties_fournisseurs, 0, 5) as $sf): ?>
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <div>
                                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($sf['fournisseur_nom']) ?></span>
                                            <span class="text-xs text-gray-500 ml-2">(<?= $sf['nb_factures'] ?> factures)</span>
                                        </div>
                                        <span class="text-sm font-semibold negative"><?= number_format($sf['total_paye'], 0, ',', ' ') ?> FCFA</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Factures à Payer -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>
                            À Payer
                        </h3>
                        <div class="text-center py-4">
                            <p class="text-4xl font-bold text-orange-500"><?= number_format($attente_paiement['total_a_payer'], 0, ',', ' ') ?></p>
                            <p class="text-gray-600 text-sm mt-2">FCFA à régler</p>
                            <div class="mt-4 pt-4 border-t">
                                <p class="text-2xl font-semibold text-gray-700"><?= $attente_paiement['nb_factures_attente'] ?></p>
                                <p class="text-gray-600 text-sm">Factures en attente</p>
                            </div>
                            <a href="factures_fournisseur.php" class="mt-4 inline-block bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm">
                                <i class="fas fa-file-invoice mr-1"></i>
                                Voir les factures
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Graphique Évolution -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-chart-area text-blue-600 mr-2"></i>
                        Évolution Quotidienne
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartEvolution"></canvas>
                    </div>
                </div>

                <!-- Graphique Camembert Entrées vs Sorties -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                            Répartition Entrées / Sorties
                        </h3>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="chartRepartition"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Résumé de la Période
                        </h3>
                        <div class="space-y-4">
                            <div class="border-l-4 border-green-500 pl-4">
                                <p class="text-sm text-gray-600">Total Entrées</p>
                                <p class="text-2xl font-bold positive"><?= number_format($entrees['total_entrees'], 0, ',', ' ') ?> FCFA</p>
                                <p class="text-xs text-gray-500"><?= $entrees['nb_commandes'] ?> transactions</p>
                            </div>

                            <div class="border-l-4 border-red-500 pl-4">
                                <p class="text-sm text-gray-600">Total Sorties</p>
                                <p class="text-2xl font-bold negative"><?= number_format($sorties['total_sorties'], 0, ',', ' ') ?> FCFA</p>
                                <p class="text-xs text-gray-500"><?= $sorties['nb_factures'] ?> paiements</p>
                            </div>

                            <div class="border-l-4 border-purple-500 pl-4">
                                <p class="text-sm text-gray-600">Solde Net</p>
                                <p class="text-2xl font-bold <?= $solde >= 0 ? 'positive' : 'negative' ?>"><?= number_format($solde, 0, ',', ' ') ?> FCFA</p>
                                <p class="text-xs text-gray-500">Marge de <?= number_format($taux_marge, 1) ?>%</p>
                            </div>

                            <div class="border-l-4 border-orange-500 pl-4">
                                <p class="text-sm text-gray-600">Trésorerie Prévisionnelle</p>
                                <p class="text-2xl font-bold text-orange-500">
                                    <?= number_format($solde - $attente_paiement['total_a_payer'], 0, ',', ' ') ?> FCFA
                                </p>
                                <p class="text-xs text-gray-500">Après paiement des factures en attente</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Fonction de filtrage
        function filtrer() {
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            window.location.href = `tresorerie_globale.php?date_debut=${dateDebut}&date_fin=${dateFin}`;
        }

        // Données PHP vers JS
        const evolutionData = <?= json_encode($evolution_jour) ?>;

        // Graphique évolution
        const ctxEvolution = document.getElementById('chartEvolution').getContext('2d');
        new Chart(ctxEvolution, {
            type: 'line',
            data: {
                labels: evolutionData.map(e => new Date(e.date).toLocaleDateString('fr-FR', {day: '2-digit', month: 'short'})),
                datasets: [
                    {
                        label: 'Entrées',
                        data: evolutionData.map(e => e.entrees),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Sorties',
                        data: evolutionData.map(e => e.sorties),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Solde Cumulé',
                        data: evolutionData.map(e => e.solde_cumule),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.3,
                        fill: false,
                        borderWidth: 3,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                }
            }
        });

        // Graphique camembert
        const ctxRepartition = document.getElementById('chartRepartition').getContext('2d');
        new Chart(ctxRepartition, {
            type: 'doughnut',
            data: {
                labels: ['Entrées', 'Sorties'],
                datasets: [{
                    data: [<?= $entrees['total_entrees'] ?>, <?= $sorties['total_sorties'] ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = <?= $entrees['total_entrees'] + $sorties['total_sorties'] ?>;
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed.toLocaleString('fr-FR') + ' FCFA (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>
