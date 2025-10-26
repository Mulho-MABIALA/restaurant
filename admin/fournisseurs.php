<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

// Récupérer la liste des fournisseurs
$stmt = $conn->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Fournisseurs - Restaurant</title>
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
                        <div class="flex items-center space-x-4">
                            <h1 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-truck mr-2 text-blue-600"></i>
                                Gestion Fournisseurs
                            </h1>
                            <div class="hidden md:flex space-x-2">
                                <a href="dashboard.php" class="text-gray-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium">
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
                        <button onclick="openModal('modalAjouterFournisseur')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Nouveau Fournisseur
                        </button>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 py-6">
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
                                            <button onclick='voirDetails(<?= json_encode($f) ?>)' class="text-blue-600 hover:text-blue-800 mr-2">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="factures_fournisseur.php?fournisseur_id=<?= $f['id'] ?>" class="text-green-600 hover:text-green-800">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
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
                        <input type="text" name="pays" value="France" class="w-full px-3 py-2 border rounded-lg">
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

    <script>
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

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function voirDetails(fournisseur) {
            alert(`Fournisseur: ${fournisseur.nom}\nContact: ${fournisseur.contact}\nTél: ${fournisseur.telephone}\nEmail: ${fournisseur.email}`);
        }
    </script>

</body>
</html>
