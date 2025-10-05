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

// Récupérer les factures clients (commandes payées)
$stmt_factures = $conn->query("
    SELECT
        id,
        numero_commande as numero_facture,
        date_commande as date_facture,
        nom_client,
        total as montant_ttc,
        statut_paiement as statut,
        mode_paiement
    FROM commandes
    WHERE statut_paiement = 'Payé'
    ORDER BY date_commande DESC
    LIMIT 50
");
$factures_clients = $stmt_factures->fetchAll(PDO::FETCH_ASSOC);

// Statistiques factures
$stmt_stats = $conn->query("
    SELECT
        COUNT(*) as total_factures,
        COALESCE(SUM(total), 0) as montant_total,
        COUNT(CASE WHEN statut_paiement = 'Payé' THEN 1 END) as factures_payees,
        COUNT(CASE WHEN statut_paiement = 'En attente' THEN 1 END) as factures_attente
    FROM commandes
    WHERE MONTH(date_commande) = MONTH(CURDATE())
");
$stats_factures = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturation - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        .facturation-tab {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .facturation-tab.active {
            border-bottom: 2px solid #3B82F6;
            color: #3B82F6;
        }

        .tab-content.hidden {
            display: none !important;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 0.75rem;
        }

        .notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
                                <i class="fas fa-file-invoice mr-2 text-blue-600"></i>
                                Gestion Facturation
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Tableau de bord
                                </a>
                                <a href="facturation.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
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
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- KPIs Factures -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="dashboard-card card-blue">
                        <div class="icon-wrapper icon-blue">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Factures</h3>
                            <p class="card-value"><?= number_format($stats_factures['total_factures']) ?></p>
                            <p class="card-subtitle text-gray-600">Ce mois</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Factures Payées</h3>
                            <p class="card-value"><?= number_format($stats_factures['factures_payees']) ?></p>
                            <p class="card-subtitle text-green-600">Réglées</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">En Attente</h3>
                            <p class="card-value"><?= number_format($stats_factures['factures_attente']) ?></p>
                            <p class="card-subtitle text-orange-600">À suivre</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-purple">
                        <div class="icon-wrapper icon-purple">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Montant Total</h3>
                            <p class="card-value"><?= number_format($stats_factures['montant_total']) ?></p>
                            <p class="card-subtitle text-gray-600">FCFA</p>
                        </div>
                    </div>
                </div>

                <!-- Liste des factures -->
                <div class="dashboard-card card-blue mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold flex items-center text-gray-900">
                            <div class="icon-wrapper icon-blue mr-3">
                                <i class="fas fa-list"></i>
                            </div>
                            Factures Clients
                        </h3>
                        <div class="flex space-x-3">
                            <input type="date" id="filtreDate" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <button onclick="filtrerFactures()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm">
                                <i class="fas fa-filter mr-1"></i>Filtrer
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode Paiement</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($factures_clients)): ?>
                                    <?php foreach ($factures_clients as $facture): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($facture['numero_facture'] ?? 'N/A') ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?= $facture['date_facture'] ? date('d/m/Y', strtotime($facture['date_facture'])) : 'N/A' ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($facture['nom_client'] ?? 'Client sur place') ?></td>
                                            <td class="px-4 py-3 text-gray-700">
                                                <?php
                                                $mode_icons = [
                                                    'Espèces' => 'fa-money-bill-wave text-green-600',
                                                    'Carte bancaire' => 'fa-credit-card text-blue-600',
                                                    'Mobile Money' => 'fa-mobile-alt text-purple-600'
                                                ];
                                                $icon = $mode_icons[$facture['mode_paiement'] ?? ''] ?? 'fa-wallet text-gray-600';
                                                ?>
                                                <i class="fas <?= $icon ?> mr-2"></i><?= htmlspecialchars($facture['mode_paiement'] ?? 'N/A') ?>
                                            </td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900"><?= number_format($facture['montant_ttc'] ?? 0) ?> FCFA</td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>Payée
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button onclick="voirDetails(<?= $facture['id'] ?>)" class="text-blue-600 hover:text-blue-800 p-2 rounded hover:bg-blue-50" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="imprimerFacture(<?= $facture['id'] ?>)" class="text-green-600 hover:text-green-800 p-2 rounded hover:bg-green-50" title="Imprimer">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                            Aucune facture trouvée
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
        function filtrerFactures() {
            const date = document.getElementById('filtreDate').value;
            if (date) {
                window.location.href = '?date=' + date;
            }
        }

        function voirDetails(factureId) {
            window.location.href = `../commande_details.php?id=${factureId}`;
        }

        function imprimerFacture(factureId) {
            window.open(`../../api/generer_facture.php?id=${factureId}`, '_blank');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 notification ${
                type === 'error' ? 'bg-red-600 text-white' :
                type === 'success' ? 'bg-green-600 text-white' :
                'bg-blue-600 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>

</body>
</html>
