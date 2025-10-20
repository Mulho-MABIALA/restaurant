<?php
session_start();
require_once '../config.php';
require_once 'permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Récupérer les alertes
$stmt = $conn->query("
    SELECT * FROM alertes_financieres
    ORDER BY
        CASE priorite
            WHEN 'critical' THEN 1
            WHEN 'warning' THEN 2
            WHEN 'info' THEN 3
        END,
        date_creation DESC
    LIMIT 100
");
$alertes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $conn->query("SELECT COUNT(*) FROM alertes_financieres WHERE statut = 'active'");
$nb_non_lues = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM alertes_financieres WHERE statut = 'lue'");
$nb_lues = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM alertes_financieres WHERE statut = 'resolue'");
$nb_traitees = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes Financières - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-bell mr-2 text-red-600"></i>
                            Alertes Financières
                        </h1>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- Stats Alertes -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="dashboard-card card-red">
                        <div class="icon-wrapper icon-red">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Non Lues</h3>
                            <p class="card-value"><?= $nb_non_lues ?></p>
                            <p class="card-subtitle text-red-600">À traiter</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Lues</h3>
                            <p class="card-value"><?= $nb_lues ?></p>
                            <p class="card-subtitle text-gray-600">En cours</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Traitées</h3>
                            <p class="card-value"><?= $nb_traitees ?></p>
                            <p class="card-subtitle text-green-600">Résolues</p>
                        </div>
                    </div>
                </div>

                <!-- Liste Alertes -->
                <div class="dashboard-card card-red">
                    <h3 class="text-lg font-semibold mb-6">Liste des Alertes</h3>

                    <div class="space-y-4">
                        <?php foreach ($alertes as $alerte):
                            $priorite_colors = [
                                'critical' => 'bg-red-100 border-red-500 text-red-900',
                                'warning' => 'bg-orange-100 border-orange-500 text-orange-900',
                                'info' => 'bg-blue-100 border-blue-500 text-blue-900'
                            ];
                            $color = $priorite_colors[$alerte['priorite']] ?? 'bg-gray-100 border-gray-500 text-gray-900';

                            $type_icons = [
                                'rupture_stock' => 'fa-box-open',
                                'echeance_facture' => 'fa-clock',
                                'ecart_caisse' => 'fa-cash-register',
                                'baisse_marge' => 'fa-chart-line',
                                'objectif_rate' => 'fa-target'
                            ];
                            $icon = $type_icons[$alerte['type_alerte']] ?? 'fa-bell';
                        ?>
                            <div class="border-l-4 <?= $color ?> p-4 rounded-lg <?= $alerte['statut'] == 'active' ? 'bg-white' : 'bg-gray-50' ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-3 flex-1">
                                        <i class="fas <?= $icon ?> text-xl mt-1"></i>
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2">
                                                <h4 class="font-semibold"><?= htmlspecialchars($alerte['titre']) ?></h4>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                                                    <?= ucfirst($alerte['priorite']) ?>
                                                </span>
                                                <?php if ($alerte['statut'] == 'active'): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-500 text-white">NOUVEAU</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-gray-700 mt-2"><?= htmlspecialchars($alerte['message']) ?></p>
                                            <p class="text-sm text-gray-500 mt-2">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($alerte['date_creation'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <?php if ($alerte['statut'] == 'active'): ?>
                                            <button onclick="marquerLue(<?= $alerte['id'] ?>)" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                                                <i class="fas fa-eye mr-1"></i>Marquer lue
                                            </button>
                                        <?php elseif ($alerte['statut'] == 'lue'): ?>
                                            <button onclick="marquerTraitee(<?= $alerte['id'] ?>)" class="px-3 py-1 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                                                <i class="fas fa-check mr-1"></i>Traiter
                                            </button>
                                        <?php else: ?>
                                            <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Traitée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($alertes)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                                <p class="text-xl text-gray-600">Aucune alerte financière</p>
                                <p class="text-gray-500 mt-2">Tout va bien !</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function marquerLue(id) {
            const response = await fetch(`../../api/finances_api.php?action=alerte_marquer_lue&id=${id}`);
            const result = await response.json();
            if (result.success) {
                location.reload();
            }
        }

        async function marquerTraitee(id) {
            const response = await fetch(`../../api/finances_api.php?action=alerte_marquer_traitee&id=${id}`);
            const result = await response.json();
            if (result.success) {
                location.reload();
            }
        }
    </script>

</body>
</html>
