<?php
session_start();
require_once '../config.php';
require_once './permissions.php';

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
requireAccess($conn, $_SESSION['admin_id'], 'statistiques');

header('Content-Type: text/html; charset=utf-8');

// ============ RÉSERVATIONS ============
// Total Réservations
$stmt = $conn->query("SELECT COUNT(*) FROM reservations");
$totalReservations = $stmt->fetchColumn();

// Réservations du Mois actuel
$stmt = $conn->query("
    SELECT COUNT(*) FROM reservations
    WHERE MONTH(date_reservation) = MONTH(CURDATE())
    AND YEAR(date_reservation) = YEAR(CURDATE())
");
$monthlyReservations = $stmt->fetchColumn();

// Réservations du Mois dernier
$stmt = $conn->query("
    SELECT COUNT(*) FROM reservations
    WHERE MONTH(date_reservation) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(date_reservation) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");
$lastMonthReservations = $stmt->fetchColumn();

// Calculer variation mensuelle réservations
$reservationsVariation = 0;
if ($lastMonthReservations > 0) {
    $reservationsVariation = round((($monthlyReservations - $lastMonthReservations) / $lastMonthReservations) * 100, 1);
}

// Clients Uniques (mois actuel) - basé sur email unique dans commandes
$stmt = $conn->query("
    SELECT COUNT(DISTINCT email) FROM commandes
    WHERE MONTH(date_commande) = MONTH(CURDATE())
    AND YEAR(date_commande) = YEAR(CURDATE())
    AND email IS NOT NULL
");
$uniqueClients = $stmt->fetchColumn();

// Clients Uniques (mois dernier)
$stmt = $conn->query("
    SELECT COUNT(DISTINCT email) FROM commandes
    WHERE MONTH(date_commande) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(date_commande) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND email IS NOT NULL
");
$lastMonthClients = $stmt->fetchColumn();

// Calculer variation clients
$clientsVariation = 0;
if ($lastMonthClients > 0) {
    $clientsVariation = round((($uniqueClients - $lastMonthClients) / $lastMonthClients) * 100, 1);
}

// ============ COMMANDES ============
// Total Commandes du mois
$stmt = $conn->query("
    SELECT COUNT(*) FROM commandes
    WHERE MONTH(date_commande) = MONTH(CURDATE())
    AND YEAR(date_commande) = YEAR(CURDATE())
");
$monthlyOrders = $stmt->fetchColumn();

// Revenu du mois
$stmt = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM commandes
    WHERE MONTH(date_commande) = MONTH(CURDATE())
    AND YEAR(date_commande) = YEAR(CURDATE())
    AND statut_paiement = 'Payé'
");
$monthlyRevenue = $stmt->fetchColumn();

// Revenu du mois dernier
$stmt = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM commandes
    WHERE MONTH(date_commande) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(date_commande) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND statut_paiement = 'Payé'
");
$lastMonthRevenue = $stmt->fetchColumn();

// Variation revenu
$revenueVariation = 0;
if ($lastMonthRevenue > 0) {
    $revenueVariation = round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
}

// ============ EMPLOYÉS ============
$stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut = 'actif'");
$totalEmployees = $stmt->fetchColumn();

// ============ TAUX D'OCCUPATION ============
// Capacité du restaurant (à ajuster selon vos besoins)
$capaciteJournaliere = 50; // nombre de tables/personnes par jour
$joursOuvrables = 30; // jours dans le mois
$capaciteMensuelle = $capaciteJournaliere * $joursOuvrables;

$occupancyRate = $capaciteMensuelle > 0 ? min(100, round(($monthlyReservations / $capaciteMensuelle) * 100, 1)) : 0;

// ============ ÉVOLUTION MENSUELLE ============
$reservationsPerMonth = [];
for ($month = 1; $month <= 12; $month++) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE MONTH(date_reservation) = :month
        AND YEAR(date_reservation) = YEAR(CURDATE())
    ");
    $stmt->execute(['month' => $month]);
    $reservationsPerMonth[] = (int)$stmt->fetchColumn();
}

// ============ COMMANDES RÉCENTES ============
$stmt = $conn->query("
    SELECT nom_client, email, statut, date_commande as created_at
    FROM commandes
    ORDER BY date_commande DESC
    LIMIT 5
");
$recentReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============ PLATS POPULAIRES ============
// D'abord vérifier si la table existe et a des données
try {
    $stmt = $conn->query("
        SELECT p.nom, SUM(cd.quantite) as total_commandes
        FROM commande_details cd
        JOIN plats p ON cd.plat_nom = p.nom
        JOIN commandes c ON cd.commande_id = c.id
        WHERE MONTH(c.date_commande) = MONTH(CURDATE())
        AND YEAR(c.date_commande) = YEAR(CURDATE())
        GROUP BY p.nom
        ORDER BY total_commandes DESC
        LIMIT 5
    ");
    $popularDishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si erreur, utiliser une approche alternative basée sur le nom du plat
    try {
        $stmt = $conn->query("
            SELECT plat_nom as nom, SUM(quantite) as total_commandes
            FROM commande_details cd
            JOIN commandes c ON cd.commande_id = c.id
            WHERE MONTH(c.date_commande) = MONTH(CURDATE())
            AND YEAR(c.date_commande) = YEAR(CURDATE())
            GROUP BY plat_nom
            ORDER BY total_commandes DESC
            LIMIT 5
        ");
        $popularDishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $popularDishes = [];
    }
}

// ============ ALERTES ============
$alerts = [];

// Alerte capacité
if ($occupancyRate >= 85) {
    $alerts[] = [
        'type' => 'warning',
        'title' => 'Capacité bientôt atteinte',
        'message' => $occupancyRate . '% de la capacité mensuelle utilisée'
    ];
}

// Alerte objectif mensuel
if ($monthlyReservations >= 100) {
    $alerts[] = [
        'type' => 'success',
        'title' => 'Objectif mensuel atteint',
        'message' => $monthlyReservations . ' réservations ce mois'
    ];
}

// Alerte baisse de réservations
if ($reservationsVariation < -10) {
    $alerts[] = [
        'type' => 'warning',
        'title' => 'Baisse des réservations',
        'message' => 'Diminution de ' . abs($reservationsVariation) . '% ce mois'
    ];
}

// Si pas d'alertes, en créer une par défaut
if (empty($alerts)) {
    $alerts[] = [
        'type' => 'info',
        'title' => 'Tout est OK',
        'message' => 'Aucune alerte en cours'
    ];
}

// ============ FONCTION HELPER ============
function getInitials($nom, $prenom = '') {
    if (empty($prenom)) {
        // Si pas de prénom, extraire les initiales du nom complet
        $parts = explode(' ', trim($nom));
        if (count($parts) >= 2) {
            $n = mb_substr($parts[0], 0, 1, 'UTF-8');
            $p = mb_substr($parts[1], 0, 1, 'UTF-8');
            return strtoupper($n . $p);
        }
        return strtoupper(mb_substr($nom, 0, 2, 'UTF-8'));
    }
    $n = mb_substr($nom, 0, 1, 'UTF-8');
    $p = mb_substr($prenom, 0, 1, 'UTF-8');
    return strtoupper($n . $p);
}

function getStatusBadge($statut) {
    $badges = [
        'En cours' => ['bg-yellow-100', 'text-yellow-800', 'En cours'],
        'Prête' => ['bg-blue-100', 'text-blue-800', 'Prête'],
        'Livrée' => ['bg-green-100', 'text-green-800', 'Livrée'],
        'Annulée' => ['bg-red-100', 'text-red-800', 'Annulée'],
        'confirmee' => ['bg-green-100', 'text-green-800', 'Confirmée'],
        'en_attente' => ['bg-yellow-100', 'text-yellow-800', 'En attente'],
        'annulee' => ['bg-red-100', 'text-red-800', 'Annulée'],
        'terminee' => ['bg-blue-100', 'text-blue-800', 'Terminée']
    ];

    return $badges[$statut] ?? ['bg-gray-100', 'text-gray-800', ucfirst($statut)];
}

function timeAgo($datetime) {
    if (!$datetime) return 'N/A';

    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'j';

    return date('d/m/Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Statistiques - Restaurant Jungle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }

        /* Style scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Conteneur principal -->
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <!-- Contenu principal -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Breadcrumb -->
                <nav class="flex mb-8" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>
                                Accueil
                            </a>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-sm font-medium text-gray-500">Statistiques</span>
                            </div>
                        </li>
                    </ol>
                </nav>

                <!-- Cards d'Indicateurs -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <!-- Total Réservations -->
                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Réservations</h3>
                            <p class="card-value"><?= number_format($totalReservations) ?></p>
                            <p class="card-subtitle <?= $reservationsVariation >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $reservationsVariation >= 0 ? 'arrow-up' : 'arrow-down' ?> mr-1"></i>
                                <?= abs($reservationsVariation) ?>% vs mois dernier
                            </p>
                        </div>
                    </div>

                    <!-- Réservations du Mois -->
                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Ce Mois</h3>
                            <p class="card-value"><?= number_format($monthlyReservations) ?></p>
                            <p class="card-subtitle text-gray-600">Mois dernier: <?= number_format($lastMonthReservations) ?></p>
                        </div>
                    </div>

                    <!-- Clients Uniques -->
                    <div class="dashboard-card card-purple">
                        <div class="icon-wrapper icon-purple">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Clients Uniques</h3>
                            <p class="card-value"><?= number_format($uniqueClients) ?></p>
                            <p class="card-subtitle <?= $clientsVariation >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $clientsVariation >= 0 ? 'arrow-up' : 'arrow-down' ?> mr-1"></i>
                                <?= abs($clientsVariation) ?>% vs mois dernier
                            </p>
                        </div>
                    </div>

                    <!-- Taux d'Occupation -->
                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Taux d'Occupation</h3>
                            <p class="card-value"><?= $occupancyRate ?>%</p>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                                <div class="bg-gradient-to-r from-orange-400 to-orange-600 h-2 rounded-full" style="width: <?= $occupancyRate ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenu Mensuel -->
                    <div class="dashboard-card card-teal">
                        <div class="icon-wrapper icon-teal">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Revenu du Mois</h3>
                            <p class="card-value"><?= number_format($monthlyRevenue) ?> FCFA</p>
                            <p class="card-subtitle <?= $revenueVariation >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $revenueVariation >= 0 ? 'arrow-up' : 'arrow-down' ?> mr-1"></i>
                                <?= abs($revenueVariation) ?>% vs mois dernier
                            </p>
                        </div>
                    </div>

                    <!-- Commandes du Mois -->
                    <div class="dashboard-card card-cyan">
                        <div class="icon-wrapper icon-cyan">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Commandes du Mois</h3>
                            <p class="card-value"><?= number_format($monthlyOrders) ?></p>
                            <p class="card-subtitle text-gray-600">
                                Moyenne: <?= $monthlyOrders > 0 ? number_format($monthlyRevenue / $monthlyOrders) : 0 ?> FCFA
                            </p>
                        </div>
                    </div>

                    <!-- Employés Actifs -->
                    <div class="dashboard-card card-indigo">
                        <div class="icon-wrapper icon-indigo">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Employés Actifs</h3>
                            <p class="card-value"><?= number_format($totalEmployees) ?></p>
                            <p class="card-subtitle text-gray-600">
                                <i class="fas fa-user-check mr-1"></i>
                                Personnel disponible
                            </p>
                        </div>
                    </div>

                    <!-- Plat Populaire -->
                    <div class="dashboard-card card-pink">
                        <div class="icon-wrapper icon-pink">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Plat Populaire</h3>
                            <p class="card-value text-lg"><?= !empty($popularDishes) ? $popularDishes[0]['nom'] : 'N/A' ?></p>
                            <p class="card-subtitle text-gray-600">
                                <?= !empty($popularDishes) ? $popularDishes[0]['total_commandes'] . ' commandes' : 'Aucune donnée' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Graphique -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Évolution Mensuelle des Réservations</h2>
                            <p class="text-sm text-gray-500 mt-1">Analyse des tendances sur l'année <?= date('Y') ?></p>
                        </div>
                    </div>

                    <div class="h-80">
                        <canvas id="reservationsChart" class="w-full h-full"></canvas>
                    </div>
                </div>

                <!-- Section inférieure -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Plats Populaires -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-fire text-orange-500 mr-2"></i>
                            Top Plats
                        </h3>
                        <div class="space-y-3">
                            <?php if (!empty($popularDishes)): ?>
                                <?php foreach ($popularDishes as $index => $dish): ?>
                                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm font-bold text-gray-400">#<?= $index + 1 ?></span>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($dish['nom']) ?></p>
                                        </div>
                                        <span class="text-sm text-gray-500"><?= $dish['total_commandes'] ?> cmd</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">Aucune donnée disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alertes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-bell text-blue-500 mr-2"></i>
                            Alertes
                        </h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($alerts, 0, 3) as $alert): ?>
                                <?php
                                $colors = [
                                    'warning' => ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'icon' => 'text-yellow-600', 'text' => 'text-yellow-800'],
                                    'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'icon' => 'text-green-600', 'text' => 'text-green-800'],
                                    'info' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'icon' => 'text-blue-600', 'text' => 'text-blue-800']
                                ];
                                $color = $colors[$alert['type']] ?? $colors['info'];
                                ?>
                                <div class="flex items-start space-x-3 p-3 <?= $color['bg'] ?> border <?= $color['border'] ?> rounded-lg">
                                    <i class="fas fa-info-circle <?= $color['icon'] ?> mt-0.5"></i>
                                    <div>
                                        <p class="text-sm font-medium <?= $color['text'] ?>"><?= $alert['title'] ?></p>
                                        <p class="text-xs <?= $color['icon'] ?> mt-1"><?= $alert['message'] ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Réservations Récentes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-clock text-purple-500 mr-2"></i>
                            Récentes
                        </h3>
                        <div class="space-y-3">
                            <?php if (!empty($recentReservations)): ?>
                                <?php foreach (array_slice($recentReservations, 0, 4) as $reservation): ?>
                                    <?php
                                    $badge = getStatusBadge($reservation['statut'] ?? 'En cours');
                                    $initials = getInitials($reservation['nom_client'] ?? 'N/A');
                                    ?>
                                    <div class="flex items-center justify-between py-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-medium text-white"><?= $initials ?></span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($reservation['nom_client']) ?></p>
                                                <p class="text-xs text-gray-500"><?= timeAgo($reservation['created_at']) ?></p>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badge[0] ?> <?= $badge[1] ?>">
                                            <?= $badge[2] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">Aucune commande récente</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Chart.js -->
    <script>
        const ctx = document.getElementById('reservationsChart').getContext('2d');

        const reservationsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Réservations',
                    data: <?= json_encode($reservationsPerMonth) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        padding: 12,
                        callbacks: {
                            title: function(context) {
                                return context[0].label + ' <?= date('Y') ?>';
                            },
                            label: function(context) {
                                return context.parsed.y + ' réservations';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            font: { size: 12, weight: '500' },
                            color: '#6B7280',
                            padding: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(156, 163, 175, 0.2)',
                            drawBorder: false,
                        },
                        border: { display: false },
                        ticks: {
                            font: { size: 12, weight: '500' },
                            color: '#6B7280',
                            padding: 10
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
