<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Mode d'affichage : 'fournisseurs' ou 'factures'
$mode = $_GET['mode'] ?? 'fournisseurs';

// ==================== VUE FOURNISSEURS ====================
if ($mode === 'fournisseurs') {
    // Récupérer la liste des fournisseurs
    $stmt = $conn->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== VUE FACTURES ====================
else {
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
    $fournisseurs_actifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $stmt = $conn->query("SELECT SUM(montant_ttc) FROM factures_fournisseurs WHERE statut = 'en_attente'");
    $total_en_attente = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT SUM(montant_ttc) FROM factures_fournisseurs WHERE statut = 'payee'");
    $total_payees = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT COUNT(*) FROM factures_fournisseurs WHERE date_echeance < NOW() AND statut != 'payee'");
    $nb_retard = $stmt->fetchColumn();
}

// Récupérer les fournisseurs pour les deux vues
if ($mode === 'fournisseurs') {
    $stmt = $conn->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
} else {
    $stmt = $conn->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
}
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Fournisseurs - Restaurant Mulho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
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
            <nav class="bg-white shadow-sm border-b sticky top-0 z-10">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center space-x-4">
                            <h1 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-truck mr-2 text-blue-600"></i>
                                Gestion Fournisseurs
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="finances_dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-dashboard mr-1"></i>Dashboard
                                </a>
                                <a href="facturation.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-1"></i>Facturation
                                </a>
                                <a href="rapports.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-1"></i>Rapports
                                </a>
                                <a href="tresorerie_globale.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-wallet mr-1"></i>Trésorerie
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">

                <!-- Onglets de navigation -->
                <div class="flex space-x-2 mb-6 bg-gray-100 p-2 rounded-lg">
                    <a href="?mode=fournisseurs" class="flex-1 text-center px-6 py-3 rounded-lg font-semibold transition <?= $mode === 'fournisseurs' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-truck mr-2"></i>Fournisseurs
                    </a>
                    <a href="?mode=factures" class="flex-1 text-center px-6 py-3 rounded-lg font-semibold transition <?= $mode === 'factures' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-file-invoice mr-2"></i>Factures
                    </a>
                </div>

                <!-- ==================== VUE FOURNISSEURS ==================== -->
                <?php if ($mode === 'fournisseurs'): ?>

                    <div class="flex justify-end mb-6">
                        <button onclick="openModal('modalAjouterFournisseur')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Nouveau Fournisseur
                        </button>
                    </div>

                    <div class="dashboard-card card-blue">
                        <h3 class="text-lg font-semibold mb-6 flex items-center text-gray-900">
                            <div class="icon-wrapper icon-blue mr-3">
                                <i class="fas fa-list"></i>
                            </div>
                            Liste des Fournisseurs
                        </h3>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ville</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($f['nom']) ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($f['contact_nom'] ?? '-') ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($f['telephone'] ?? '-') ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($f['ville'] ?? '-') ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium <?= $f['actif'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $f['actif'] == 1 ? 'Actif' : 'Inactif' ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button onclick='voirDetails(<?= json_encode($f) ?>)' class="text-blue-600 hover:text-blue-800 mr-2" title="Voir les détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="?mode=factures&fournisseur_id=<?= $f['id'] ?>" class="text-green-600 hover:text-green-800" title="Voir les factures">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <!-- ==================== VUE FACTURES ==================== -->
                <?php else: ?>

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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                                <?php if ($facture['statut'] == 'en_attente'): ?>
                                                    <button onclick="marquerPayee(<?= $facture['id'] ?>)" class="text-green-600 hover:text-green-900" title="Marquer comme payée">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="voirDetails(<?= $facture['id'] ?>)" class="text-blue-600 hover:text-blue-900" title="Voir les détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="imprimerFacture(<?= $facture['id'] ?>)" class="text-purple-600 hover:text-purple-900" title="Imprimer">
                                                    <i class="fas fa-print"></i>
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

                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Modal Ajouter Fournisseur -->
    <div id="modalAjouterFournisseur" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Nouveau Fournisseur</h3>
            </div>
            <form id="formFournisseur" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nom *</label>
                        <input type="text" name="nom" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Contact</label>
                        <input type="text" name="contact_nom" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Téléphone</label>
                        <input type="tel" name="telephone" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Email</label>
                        <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-2">Adresse</label>
                        <textarea name="adresse" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Ville</label>
                        <input type="text" name="ville" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Code Postal</label>
                        <input type="text" name="code_postal" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Pays</label>
                        <input type="text" name="pays" value="Sénégal" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">SIRET</label>
                        <input type="text" name="siret" maxlength="14" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Numéro TVA</label>
                        <input type="text" name="tva_numero" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Conditions Paiement (jours)</label>
                        <input type="number" name="conditions_paiement" placeholder="30" value="30" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Mode de Paiement</label>
                        <select name="mode_paiement" class="w-full px-3 py-2 border rounded-lg">
                            <option value="virement">Virement</option>
                            <option value="cheque">Chèque</option>
                            <option value="carte">Carte</option>
                            <option value="especes">Espèces</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-2">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 pt-4">
                    <button type="button" onclick="closeModal('modalAjouterFournisseur')" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Enregistrer</button>
                </div>
            </form>
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
                            <?php foreach ($fournisseurs_actifs ?? $fournisseurs as $f): ?>
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
        // ==================== FONCTIONS COMMUNES ====================
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // ==================== FOURNISSEURS ====================
        document.getElementById('formFournisseur').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            const response = await fetch('../../api/finances_api.php?action=fournisseur_ajouter', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                alert('Fournisseur ajouté avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + result.error);
            }
        });

        function voirDetails(fournisseur) {
            alert(`Fournisseur: ${fournisseur.nom}\nContact: ${fournisseur.contact}\nTél: ${fournisseur.telephone}\nEmail: ${fournisseur.email}`);
        }

        // ==================== FACTURES ====================
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
    </script>

</body>
</html>
