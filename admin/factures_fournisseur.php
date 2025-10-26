<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Récupérer les factures fournisseurs
$stmt = $conn->query("
    SELECT ff.*, f.nom as fournisseur_nom, f.telephone
    FROM factures_fournisseurs ff
    LEFT JOIN fournisseurs f ON ff.fournisseur_id = f.id
    ORDER BY ff.date_facture DESC
    LIMIT 100
");
$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les fournisseurs pour le formulaire
$stmt = $conn->query("SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY nom");
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $conn->query("SELECT SUM(montant_ttc) FROM factures_fournisseurs WHERE statut = 'en_attente'");
$total_en_attente = $stmt->fetchColumn() ?: 0;

$stmt = $conn->query("SELECT SUM(montant_ttc) FROM factures_fournisseurs WHERE statut = 'payee'");
$total_payees = $stmt->fetchColumn() ?: 0;

$stmt = $conn->query("SELECT COUNT(*) FROM factures_fournisseurs WHERE date_echeance < NOW() AND statut != 'payee'");
$nb_retard = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures Fournisseurs - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-file-invoice mr-2 text-blue-600"></i>
                            Factures Fournisseurs
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

                <!-- Onglets -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button onclick="switchTab('factures')" id="tab-factures" class="tab-button border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600">
                            <i class="fas fa-file-invoice mr-2"></i>Factures en cours
                        </button>
                        <button onclick="switchTab('historique')" id="tab-historique" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <i class="fas fa-history mr-2"></i>Historique
                        </button>
                    </nav>
                </div>

                <div id="content-factures">
                <!-- Stats Factures -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="dashboard-card card-orange">
                        <div class="icon-wrapper icon-orange">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">En Attente</h3>
                            <p class="card-value"><?= number_format($total_en_attente, 0, ',', ' ') ?> FCFA</p>
                            <p class="card-subtitle text-orange-600">À payer</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-green">
                        <div class="icon-wrapper icon-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Payées</h3>
                            <p class="card-value"><?= number_format($total_payees, 0, ',', ' ') ?> FCFA</p>
                            <p class="card-subtitle text-green-600">Total payé</p>
                        </div>
                    </div>

                    <div class="dashboard-card card-red">
                        <div class="icon-wrapper icon-red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">En Retard</h3>
                            <p class="card-value"><?= $nb_retard ?></p>
                            <p class="card-subtitle text-red-600">Échéance dépassée</p>
                        </div>
                    </div>
                </div>

                <!-- Liste Factures -->
                <div class="dashboard-card card-blue">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Liste des Factures</h3>
                        <button onclick="openModal('modalAjouterFacture')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Nouvelle Facture
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Numéro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fournisseur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Échéance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant TTC</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($factures as $facture):
                                    $statut_colors = [
                                        'en_attente' => 'bg-orange-100 text-orange-800',
                                        'payee' => 'bg-green-100 text-green-800',
                                        'annulee' => 'bg-red-100 text-red-800'
                                    ];
                                    $color = $statut_colors[$facture['statut']];
                                    $is_retard = strtotime($facture['date_echeance']) < time() && $facture['statut'] != 'payee';
                                ?>
                                    <tr class="hover:bg-gray-50 <?= $is_retard ? 'bg-red-50' : '' ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($facture['numero_facture']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($facture['fournisseur_nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($facture['date_facture'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?= $is_retard ? 'text-red-600 font-bold' : 'text-gray-500' ?>">
                                            <?= date('d/m/Y', strtotime($facture['date_echeance'])) ?>
                                            <?php if ($is_retard): ?>
                                                <i class="fas fa-exclamation-triangle ml-1"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            <?= number_format($facture['montant_ttc'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $color ?>">
                                                <?= ucfirst(str_replace('_', ' ', $facture['statut'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($facture['statut'] == 'en_attente'): ?>
                                                <button onclick="marquerPayee(<?= $facture['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                                    <i class="fas fa-check-circle"></i> Marquer payée
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="voirDetails(<?= $facture['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-eye"></i> Détails
                                            </button>
                                            <button onclick="imprimerFacture(<?= $facture['id'] ?>)" class="text-purple-600 hover:text-purple-900">
                                                <i class="fas fa-print"></i> Imprimer
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($factures)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-2"></i>
                                            <p>Aucune facture fournisseur</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>

                <!-- Historique Section -->
                <div id="content-historique" class="hidden">
                    <!-- Filtres -->
                    <div class="dashboard-card card-gray mb-6">
                        <h3 class="text-lg font-semibold mb-4">Filtres</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
                                <select id="filter-fournisseur" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Tous les fournisseurs</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                                <select id="filter-statut" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Tous les statuts</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="payee">Payée</option>
                                    <option value="annulee">Annulée</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                                <input type="date" id="filter-date-debut" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                                <input type="date" id="filter-date-fin" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end space-x-3">
                            <button onclick="resetFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                <i class="fas fa-redo mr-2"></i>Réinitialiser
                            </button>
                            <button onclick="applyFilters()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                            </button>
                        </div>
                    </div>

                    <!-- Statistiques historique -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="dashboard-card card-blue">
                            <div class="icon-wrapper icon-blue">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Total Factures</h3>
                                <p class="card-value" id="stat-total-factures"><?= count($factures) ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card card-green">
                            <div class="icon-wrapper icon-green">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Total Payé</h3>
                                <p class="card-value" id="stat-total-paye"><?= number_format($total_payees, 0, ',', ' ') ?> FCFA</p>
                            </div>
                        </div>
                        <div class="dashboard-card card-orange">
                            <div class="icon-wrapper icon-orange">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">En Attente</h3>
                                <p class="card-value" id="stat-en-attente"><?= number_format($total_en_attente, 0, ',', ' ') ?> FCFA</p>
                            </div>
                        </div>
                        <div class="dashboard-card card-purple">
                            <div class="icon-wrapper icon-purple">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Total Global</h3>
                                <p class="card-value" id="stat-total-global"><?= number_format($total_payees + $total_en_attente, 0, ',', ' ') ?> FCFA</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau historique -->
                    <div class="dashboard-card card-blue">
                        <h3 class="text-lg font-semibold mb-6">Historique Complet</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="table-historique">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Numéro</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fournisseur</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Facture</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Paiement</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant TTC</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($factures as $facture):
                                        $statut_colors = [
                                            'en_attente' => 'bg-orange-100 text-orange-800',
                                            'payee' => 'bg-green-100 text-green-800',
                                            'annulee' => 'bg-red-100 text-red-800'
                                        ];
                                        $color = $statut_colors[$facture['statut']];
                                    ?>
                                        <tr class="hover:bg-gray-50"
                                            data-fournisseur="<?= $facture['fournisseur_id'] ?>"
                                            data-statut="<?= $facture['statut'] ?>"
                                            data-date="<?= $facture['date_facture'] ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($facture['numero_facture']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($facture['fournisseur_nom']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y', strtotime($facture['date_facture'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $facture['date_paiement'] ? date('d/m/Y', strtotime($facture['date_paiement'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                <?= number_format($facture['montant_ttc'], 0, ',', ' ') ?> FCFA
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $color ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $facture['statut'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <button onclick="voirDetails(<?= $facture['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-eye"></i> Détails
                                                </button>
                                                <button onclick="imprimerFacture(<?= $facture['id'] ?>)" class="text-purple-600 hover:text-purple-900">
                                                    <i class="fas fa-print"></i> Imprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Ajouter Facture -->
    <div id="modalAjouterFacture" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Nouvelle Facture Fournisseur</h3>
                <button onclick="closeModal('modalAjouterFacture')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formFacture">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur *</label>
                        <select name="fournisseur_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Numéro Facture *</label>
                        <input type="text" name="numero_facture" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Facture *</label>
                        <input type="date" name="date_facture" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Échéance *</label>
                        <input type="date" name="date_echeance" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant HT *</label>
                        <input type="number" name="montant_ht" id="montant_ht" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="calculerTotaux()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">TVA (%)</label>
                        <input type="number" name="tva_pourcentage" id="tva_pourcentage" value="18" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="calculerTotaux()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant TVA</label>
                        <input type="number" name="montant_tva" id="montant_tva" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant TTC</label>
                        <input type="number" name="montant_ttc" id="montant_ttc" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 font-bold">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('modalAjouterFacture')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function calculerTotaux() {
            const ht = parseFloat(document.getElementById('montant_ht').value) || 0;
            const tva_pct = parseFloat(document.getElementById('tva_pourcentage').value) || 0;
            const tva = ht * (tva_pct / 100);
            const ttc = ht + tva;

            document.getElementById('montant_tva').value = tva.toFixed(2);
            document.getElementById('montant_ttc').value = ttc.toFixed(2);
        }

        document.getElementById('formFacture').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            const response = await fetch('../../api/finances_api.php?action=facture_fournisseur_ajouter', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('Facture ajoutée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + (result.message || 'Erreur inconnue'));
            }
        });

        async function marquerPayee(id) {
            if (!confirm('Confirmer le paiement de cette facture ?')) return;

            const response = await fetch(`../../api/finances_api.php?action=facture_fournisseur_payer&id=${id}`);
            const result = await response.json();

            if (result.success) {
                alert('Facture marquée comme payée');
                location.reload();
            } else {
                alert('Erreur: ' + (result.message || 'Erreur inconnue'));
            }
        }

        async function voirDetails(id) {
            const response = await fetch(`../../api/finances_api.php?action=facture_fournisseur_details&id=${id}`);
            const result = await response.json();

            if (result.success) {
                const f = result.facture;
                const modalContent = `
                    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" onclick="this.remove()">
                        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6" onclick="event.stopPropagation()">
                            <div class="flex justify-between items-center mb-6 border-b pb-4">
                                <h3 class="text-2xl font-bold text-gray-800">Détails Facture #${f.numero_facture}</h3>
                                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-2xl"></i>
                                </button>
                            </div>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500 font-semibold">Fournisseur</p>
                                        <p class="text-lg font-medium">${f.fournisseur_nom}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 font-semibold">Contact</p>
                                        <p class="text-lg">${f.contact_nom || '-'}</p>
                                        <p class="text-sm text-gray-600">${f.telephone || ''}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500 font-semibold">Date Facture</p>
                                        <p class="text-lg">${new Date(f.date_facture).toLocaleDateString('fr-FR')}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 font-semibold">Échéance</p>
                                        <p class="text-lg">${new Date(f.date_echeance).toLocaleDateString('fr-FR')}</p>
                                    </div>
                                </div>
                                <div class="border-t pt-4">
                                    <div class="grid grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Montant HT</p>
                                            <p class="text-xl font-semibold">${parseInt(f.montant_ht).toLocaleString()} FCFA</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">TVA</p>
                                            <p class="text-xl font-semibold text-orange-600">${parseInt(f.montant_tva).toLocaleString()} FCFA</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Montant TTC</p>
                                            <p class="text-2xl font-bold text-blue-600">${parseInt(f.montant_ttc).toLocaleString()} FCFA</p>
                                        </div>
                                    </div>
                                </div>
                                ${f.notes ? `
                                <div class="border-t pt-4">
                                    <p class="text-sm text-gray-500 font-semibold mb-2">Notes</p>
                                    <p class="text-gray-700 bg-gray-50 p-3 rounded">${f.notes}</p>
                                </div>
                                ` : ''}
                                <div class="border-t pt-4">
                                    <p class="text-sm text-gray-500 font-semibold mb-2">Statut</p>
                                    <span class="px-4 py-2 rounded-full text-sm font-semibold ${f.statut == 'payee' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'}">
                                        ${f.statut == 'payee' ? 'Payée le ' + new Date(f.date_paiement).toLocaleDateString('fr-FR') : 'En attente'}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                    Fermer
                                </button>
                                <button onclick="imprimerFacture(${f.id})" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                    <i class="fas fa-print mr-2"></i>Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalContent);
            } else {
                alert('Erreur: ' + (result.message || 'Erreur inconnue'));
            }
        }

        function imprimerFacture(id) {
            window.open(`imprimer_facture_fournisseur.php?id=${id}`, '_blank');
        }

        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-500');
            document.getElementById(`tab-${tab}`).classList.add('border-blue-500', 'text-blue-600');

            // Update content
            document.getElementById('content-factures').classList.add('hidden');
            document.getElementById('content-historique').classList.add('hidden');
            document.getElementById(`content-${tab}`).classList.remove('hidden');
        }

        function applyFilters() {
            const fournisseur = document.getElementById('filter-fournisseur').value;
            const statut = document.getElementById('filter-statut').value;
            const dateDebut = document.getElementById('filter-date-debut').value;
            const dateFin = document.getElementById('filter-date-fin').value;

            const rows = document.querySelectorAll('#table-historique tbody tr');
            let totalFactures = 0;
            let totalPaye = 0;
            let totalEnAttente = 0;

            rows.forEach(row => {
                let show = true;

                if (fournisseur && row.dataset.fournisseur !== fournisseur) {
                    show = false;
                }

                if (statut && row.dataset.statut !== statut) {
                    show = false;
                }

                if (dateDebut && row.dataset.date < dateDebut) {
                    show = false;
                }

                if (dateFin && row.dataset.date > dateFin) {
                    show = false;
                }

                if (show) {
                    row.style.display = '';
                    totalFactures++;
                    const montant = parseFloat(row.cells[4].textContent.replace(/[^0-9]/g, ''));
                    if (row.dataset.statut === 'payee') {
                        totalPaye += montant;
                    } else if (row.dataset.statut === 'en_attente') {
                        totalEnAttente += montant;
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Update stats
            document.getElementById('stat-total-factures').textContent = totalFactures;
            document.getElementById('stat-total-paye').textContent = totalPaye.toLocaleString() + ' FCFA';
            document.getElementById('stat-en-attente').textContent = totalEnAttente.toLocaleString() + ' FCFA';
            document.getElementById('stat-total-global').textContent = (totalPaye + totalEnAttente).toLocaleString() + ' FCFA';
        }

        function resetFilters() {
            document.getElementById('filter-fournisseur').value = '';
            document.getElementById('filter-statut').value = '';
            document.getElementById('filter-date-debut').value = '';
            document.getElementById('filter-date-fin').value = '';
            applyFilters();
        }
    </script>

</body>
</html>
