<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

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

// Mode d'affichage : 'globale' ou 'journaliere'
$mode = $_GET['mode'] ?? 'globale';

// ==================== VUE GLOBALE ====================
if ($mode === 'globale') {
    // Filtres de date
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
    $date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Dernier jour du mois

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

    // Total des paiements fournisseurs
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

    // Calcul du solde
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

    // Fusionner entrées et sorties par jour pour le graphique
    $evolution_jour = [];
    $dates_all = [];

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
}

// ==================== VUE JOURNALIÈRE ====================
else {
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
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trésorerie - Restaurant Mulho</title>
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

        .tab-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
        }

        .tab-inactive {
            background: white;
            color: #6b7280;
        }

        .tab-inactive:hover {
            background: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <!-- Navigation Finances -->
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center space-x-4">
                            <h1 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-wallet mr-2 text-purple-600"></i>
                                Gestion Trésorerie
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="finances_dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Dashboard
                                </a>
                                <a href="tresorerie_globale.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie
                                </a>
                                <a href="rapports.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                            </div>
                        </div>

                        <!-- Filtres selon le mode -->
                        <?php if ($mode === 'globale'): ?>
                        <div class="flex items-center space-x-3">
                            <label class="text-sm text-gray-600">Du</label>
                            <input type="date" id="dateDebut" value="<?= $date_debut ?>" class="px-3 py-2 border rounded-lg text-sm">
                            <label class="text-sm text-gray-600">Au</label>
                            <input type="date" id="dateFin" value="<?= $date_fin ?>" class="px-3 py-2 border rounded-lg text-sm">
                            <button onclick="filtrerGlobale()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-filter mr-1"></i>Filtrer
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <input type="date" id="dateSelector" value="<?= $date_filter ?>" class="px-3 py-2 border rounded-lg text-sm" onchange="filtrerJournaliere()">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- Onglets de navigation -->
                <div class="flex space-x-2 mb-6 bg-gray-100 p-2 rounded-lg">
                    <a href="?mode=globale" class="flex-1 text-center px-6 py-3 rounded-lg font-semibold transition <?= $mode === 'globale' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-chart-line mr-2"></i>Vue Globale (Période)
                    </a>
                    <a href="?mode=journaliere" class="flex-1 text-center px-6 py-3 rounded-lg font-semibold transition <?= $mode === 'journaliere' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-calendar-day mr-2"></i>Vue Journalière
                    </a>
                </div>

                <!-- ==================== CONTENU VUE GLOBALE ==================== -->
                <?php if ($mode === 'globale'): ?>

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
                                <a href="fournisseurs.php?mode=factures" class="mt-4 inline-block bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm">
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

                <!-- ==================== CONTENU VUE JOURNALIÈRE ==================== -->
                <?php else: ?>

                    <!-- KPIs Trésorerie du Jour -->
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
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chartEvolution7j"></canvas>
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

                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
        // Fonction de filtrage globale
        function filtrerGlobale() {
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            window.location.href = `tresorerie_globale.php?mode=globale&date_debut=${dateDebut}&date_fin=${dateFin}`;
        }

        // Fonction de filtrage journalière
        function filtrerJournaliere() {
            const date = document.getElementById('dateSelector').value;
            window.location.href = `tresorerie_globale.php?mode=journaliere&date=${date}`;
        }

        <?php if ($mode === 'globale'): ?>
        // ==================== GRAPHIQUES VUE GLOBALE ====================
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

        <?php else: ?>
        // ==================== GRAPHIQUES VUE JOURNALIÈRE ====================
        const ctxEvolution7j = document.getElementById('chartEvolution7j').getContext('2d');
        const dataEvolution = <?= json_encode($evolution_7j) ?>;

        new Chart(ctxEvolution7j, {
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
        <?php endif; ?>
    </script>

</body>
</html>
