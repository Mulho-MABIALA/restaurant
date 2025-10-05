<?php
session_start();
require_once '../../config.php';
require_once '../permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Récupérer les plats disponibles
$stmt = $conn->query("
    SELECT
        p.id,
        p.nom as plat_nom,
        p.prix as prix_vente
    FROM plats p
    ORDER BY p.nom
");
$plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les marges pour chaque plat
$marges = [];
$total_marges = 0;
$nb_marges_faibles = 0;
$nb_marges_bonnes = 0;

foreach ($plats as $plat) {
    $prix_vente = floatval($plat['prix_vente']);

    // Utiliser un coût estimé basé sur le prix de vente
    // Pour les restaurants, le coût des ingrédients représente généralement 30-35% du prix de vente
    $cout_revient = $prix_vente * 0.32;

    $marge_fcfa = $prix_vente - $cout_revient;
    $marge_pourcentage = $prix_vente > 0 ? ($marge_fcfa / $prix_vente) * 100 : 0;

    $marges[] = [
        'id' => $plat['id'],
        'plat_nom' => $plat['plat_nom'],
        'prix_vente' => $prix_vente,
        'cout_revient' => $cout_revient,
        'marge_fcfa' => $marge_fcfa,
        'marge_pourcentage' => $marge_pourcentage
    ];

    $total_marges += $marge_pourcentage;

    if ($marge_pourcentage < 30) $nb_marges_faibles++;
    if ($marge_pourcentage >= 50) $nb_marges_bonnes++;
}

// Trier par marge croissante
usort($marges, function($a, $b) {
    return $a['marge_pourcentage'] <=> $b['marge_pourcentage'];
});

$marge_moyenne = count($marges) > 0 ? $total_marges / count($marges) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse des Marges - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/cards-design.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include '../sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-chart-line mr-2 text-purple-600"></i>
                            Analyse des Marges
                        </h1>
                        <div class="flex space-x-4">
                            <a href="dashboard.php" class="text-gray-600 hover:text-blue-600"><i class="fas fa-chart-line mr-1"></i>Dashboard</a>
                            <a href="fournisseurs.php" class="text-gray-600 hover:text-blue-600"><i class="fas fa-truck mr-1"></i>Fournisseurs</a>
                            <a href="rapports.php" class="text-gray-600 hover:text-blue-600"><i class="fas fa-file-alt mr-1"></i>Rapports</a>
                            <a href="tresorerie.php" class="text-gray-600 hover:text-blue-600"><i class="fas fa-cash-register mr-1"></i>Trésorerie</a>
                            <a href="alertes.php" class="text-gray-600 hover:text-blue-600"><i class="fas fa-bell mr-1"></i>Alertes</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- Stats Marges -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Marge Moyenne</h3>
                            <p class="card-value"><?= number_format($marge_moyenne, 1) ?>%</p>
                            <p class="card-subtitle text-gray-600">Sur tous les plats</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-red">
                        <div class="icon-wrapper icon-red">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Marges Faibles</h3>
                            <p class="card-value"><?= $nb_marges_faibles ?></p>
                            <p class="card-subtitle text-red-600">< 30%</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Bonnes Marges</h3>
                            <p class="card-value"><?= $nb_marges_bonnes ?></p>
                            <p class="card-subtitle text-green-600">≥ 50%</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Graphique Répartition -->
                    <div class="dashboard-card card-purple">
                        <h3 class="text-lg font-semibold mb-4">Répartition des Marges</h3>
                        <canvas id="chartMarges"></canvas>
                    </div>

                    <!-- Top/Flop 5 -->
                    <div class="dashboard-card card-orange">
                        <h3 class="text-lg font-semibold mb-4">Top 5 & Flop 5</h3>
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-semibold text-green-600 mb-2"><i class="fas fa-trophy mr-1"></i> Meilleures marges</h4>
                                <ul class="space-y-1">
                                    <?php
                                    $top5 = array_slice(array_reverse($marges), 0, 5);
                                    foreach ($top5 as $m):
                                    ?>
                                        <li class="flex justify-between text-sm">
                                            <span><?= htmlspecialchars($m['plat_nom']) ?></span>
                                            <span class="font-semibold text-green-600"><?= number_format($m['marge_pourcentage'], 1) ?>%</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <hr>
                            <div>
                                <h4 class="font-semibold text-red-600 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> Marges à améliorer</h4>
                                <ul class="space-y-1">
                                    <?php
                                    $flop5 = array_slice($marges, 0, 5);
                                    foreach ($flop5 as $m):
                                    ?>
                                        <li class="flex justify-between text-sm">
                                            <span><?= htmlspecialchars($m['plat_nom']) ?></span>
                                            <span class="font-semibold text-red-600"><?= number_format($m['marge_pourcentage'], 1) ?>%</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau Complet -->
                <div class="dashboard-card card-blue">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Toutes les Marges par Plat</h3>
                        <button onclick="recalculerMarges()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            <i class="fas fa-sync-alt mr-2"></i>Recalculer les marges
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plat</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix Vente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coût Revient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marge (FCFA)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marge (%)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Évaluation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($marges as $marge):
                                    if ($marge['marge_pourcentage'] < 30) {
                                        $badge_color = 'bg-red-100 text-red-800';
                                        $evaluation = 'Faible';
                                        $icon = 'fa-arrow-down';
                                    } elseif ($marge['marge_pourcentage'] < 50) {
                                        $badge_color = 'bg-yellow-100 text-yellow-800';
                                        $evaluation = 'Moyenne';
                                        $icon = 'fa-minus';
                                    } else {
                                        $badge_color = 'bg-green-100 text-green-800';
                                        $evaluation = 'Bonne';
                                        $icon = 'fa-arrow-up';
                                    }
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($marge['plat_nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= number_format($marge['prix_vente'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= number_format($marge['cout_revient'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            <?= number_format($marge['marge_fcfa'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                            <span class="text-lg"><?= number_format($marge['marge_pourcentage'], 1) ?>%</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?= $badge_color ?>">
                                                <i class="fas <?= $icon ?> mr-1"></i>
                                                <?= $evaluation ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($marges)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                            <i class="fas fa-chart-line text-4xl mb-2"></i>
                                            <p>Aucune marge calculée</p>
                                            <button onclick="recalculerMarges()" class="mt-4 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                                                <i class="fas fa-sync-alt mr-2"></i>Calculer les marges
                                            </button>
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
        // Graphique Répartition
        <?php
        $faibles = 0;
        $moyennes = 0;
        $bonnes = 0;
        foreach ($marges as $m) {
            if ($m['marge_pourcentage'] < 30) $faibles++;
            elseif ($m['marge_pourcentage'] < 50) $moyennes++;
            else $bonnes++;
        }
        ?>

        const ctx = document.getElementById('chartMarges').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Faibles (< 30%)', 'Moyennes (30-50%)', 'Bonnes (≥ 50%)'],
                datasets: [{
                    data: [<?= $faibles ?>, <?= $moyennes ?>, <?= $bonnes ?>],
                    backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        async function recalculerMarges() {
            if (!confirm('Recalculer toutes les marges ? Cela peut prendre quelques secondes.')) return;

            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Calcul en cours...';

            const response = await fetch('../../api/finances_api.php?action=calculer_marges');
            const result = await response.json();

            if (result.success) {
                alert('Marges recalculées avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + (result.message || 'Erreur inconnue'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Recalculer les marges';
            }
        }
    </script>

</body>
</html>
