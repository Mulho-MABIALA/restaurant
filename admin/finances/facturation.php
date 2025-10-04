<?php
require_once '../../config.php';
session_start();

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturation - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    
    <!-- Navigation Finances -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold text-gray-800">Gestion Facturation</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="dashboard.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Tableau de bord</a>
                        <a href="facturation.php" class="nav-btn active bg-blue-600 text-white px-4 py-2 rounded-lg">Facturation</a>
                        <a href="rapports.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Rapports</a>
                        <a href="tresorerie.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Trésorerie</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold">Gestion Facturation</h3>
                <div class="space-x-4">
                    <button type="button" onclick="showModal('modalFactureFournisseur')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Facture Fournisseur
                    </button>
                    <button onclick="genererFacturesEnAttente()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-file-invoice mr-2"></i>Générer Factures En Attente
                    </button>
                </div>
            </div>

            <!-- Onglets -->
            <div class="border-b">
                <div class="flex space-x-8 px-6">
                    <button onclick="switchTab('clients')" class="facturation-tab active py-4 border-b-2 border-blue-600 text-blue-600">Factures Clients</button>
                    <button onclick="switchTab('fournisseurs')" class="facturation-tab py-4 text-gray-600">Factures Fournisseurs</button>
                    <button onclick="switchTab('echeances')" class="facturation-tab py-4 text-gray-600">Échéances</button>
                </div>
            </div>

            <!-- Contenu onglets -->
            <div class="p-6">
                <!-- Factures Clients -->
                <div id="tabClients" class="tab-content">
                    <div class="mb-4 flex justify-between items-center">
                        <div class="flex space-x-4">
                            <input type="date" id="filtreFacturesDebut" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <input type="date" id="filtreFacturesFin" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button onclick="filtrerFacturesClients()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">Filtrer</button>
                        </div>
                    </div>
                    <div id="tableauFacturesClients" class="overflow-x-auto">
                        <div class="text-center py-8 text-gray-500">Chargement des factures...</div>
                    </div>
                </div>

                <!-- Factures Fournisseurs -->
                <div id="tabFournisseurs" class="tab-content hidden">
                    <div id="tableauFacturesFournisseurs" class="overflow-x-auto">
                        <div class="text-center py-8 text-gray-500">Chargement des factures fournisseurs...</div>
                    </div>
                </div>

                <!-- Échéances -->
                <div id="tabEcheances" class="tab-content hidden">
                    <div id="tableauEcheances" class="overflow-x-auto">
                        <div class="text-center py-8 text-gray-500">Chargement des échéances...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Facture Fournisseur - CACHÉ PAR DÉFAUT -->
    <div id="modalFactureFournisseur" class="modal fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="modal-content bg-white rounded-xl shadow-xl w-full max-w-4xl mx-auto mt-8 overflow-hidden">
            <!-- En-tête du modal avec coins arrondis en haut -->
            <div class="p-6 border-b relative bg-gray-50 rounded-t-xl">
                <h3 class="text-lg font-semibold text-gray-800">Nouvelle Facture Fournisseur</h3>
                <button type="button" onclick="closeModal('modalFactureFournisseur')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Corps du modal -->
            <form id="formFactureFournisseur" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Fournisseur</label>
                        <select id="fournisseurId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Sélectionner un fournisseur</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">N° Facture</label>
                        <input type="text" id="numeroFactureFournisseur" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Date Facture</label>
                        <input type="date" id="dateFactureFournisseur" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700">Date Échéance</label>
                        <input type="date" id="dateEcheance" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>

                <!-- Lignes de facture -->
                <div>
                    <label class="block text-sm font-medium mb-2 text-gray-700">Lignes de facture</label>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qté</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Prix HT</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">TVA %</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="lignesFacture" class="bg-white divide-y divide-gray-200">
                                <tr class="ligne-facture">
                                    <td class="px-3 py-2"><input type="text" placeholder="Désignation" class="w-full px-2 py-1 border border-gray-300 rounded designation focus:ring-1 focus:ring-blue-500 focus:border-blue-500" required></td>
                                    <td class="px-3 py-2"><input type="number" placeholder="1" class="w-20 px-2 py-1 border border-gray-300 rounded quantite focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                                    <td class="px-3 py-2"><input type="number" placeholder="0" class="w-24 px-2 py-1 border border-gray-300 rounded prix-ht focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="1" required></td>
                                    <td class="px-3 py-2"><input type="number" value="18" class="w-16 px-2 py-1 border border-gray-300 rounded tva focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                                    <td class="px-3 py-2 text-center"><span class="total-ligne font-medium text-gray-700">0 FCFA</span></td>
                                    <td class="px-3 py-2 text-center"><button type="button" onclick="supprimerLigne(this)" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 transition-colors"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="ajouterLigneFacture()" class="mt-3 text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        <i class="fas fa-plus mr-1"></i>Ajouter une ligne
                    </button>
                </div>

                <!-- Totaux -->
                <div class="grid grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg">
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-800" id="totalHT">0 FCFA</div>
                        <div class="text-sm text-gray-600">Total HT</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-800" id="totalTVA">0 FCFA</div>
                        <div class="text-sm text-gray-600">Total TVA</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-semibold text-blue-600" id="totalTTC">0 FCFA</div>
                        <div class="text-sm text-gray-600">Total TTC</div>
                    </div>
                </div>

                <!-- Boutons avec coins arrondis en bas -->
                <div class="flex justify-end space-x-4 pt-4 border-t bg-gray-50 -mx-6 px-6 py-4 rounded-b-xl">
                    <button type="button" onclick="closeModal('modalFactureFournisseur')" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        Annuler
                    </button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let currentTab = 'clients';

        document.addEventListener('DOMContentLoaded', function() {
            // Initialisation de la page
            loadFournisseurs();
            loadFacturesClients();
            
            // Event listeners pour formulaire
            document.getElementById('formFactureFournisseur').addEventListener('submit', function(e) {
                e.preventDefault();
                enregistrerFactureFournisseur();
            });

            // Event listener pour calculer totaux
            document.addEventListener('input', function(e) {
                if (e.target.closest('#lignesFacture')) {
                    calculerTotaux();
                }
            });

            // EVENT LISTENERS POUR MODAL
            const modal = document.getElementById('modalFactureFournisseur');
            
            // Fermer en cliquant sur l'arrière-plan
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal('modalFactureFournisseur');
                }
            });
            
            // Fermer avec la touche Échap
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal:not(.hidden)');
                    modals.forEach(modal => {
                        closeModal(modal.id);
                    });
                }
            });

            // Initialiser la date d'aujourd'hui
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateFactureFournisseur').value = today;
        });

        // Gestion des onglets
        function switchTab(tabName) {
            // Masquer tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Désactiver tous les boutons d'onglet
            document.querySelectorAll('.facturation-tab').forEach(btn => {
                btn.classList.remove('active', 'border-blue-600', 'text-blue-600');
                btn.classList.add('text-gray-600');
            });
            
            // Activer l'onglet sélectionné
            document.getElementById('tab' + capitalizeFirst(tabName)).classList.remove('hidden');
            event.target.classList.add('active', 'border-blue-600', 'text-blue-600');
            event.target.classList.remove('text-gray-600');
            
            currentTab = tabName;
            
            // Charger les données de l'onglet
            switch(tabName) {
                case 'clients':
                    loadFacturesClients();
                    break;
                case 'fournisseurs':
                    loadFacturesFournisseurs();
                    break;
                case 'echeances':
                    loadEcheances();
                    break;
            }
        }

        // Charger la liste des fournisseurs
        async function loadFournisseurs() {
            try {
                const response = await fetch('../../api/finance.php?action=fournisseurs');
                const fournisseurs = await response.json();
                
                const select = document.getElementById('fournisseurId');
                select.innerHTML = '<option value="">Sélectionner un fournisseur</option>';
                
                fournisseurs.forEach(fournisseur => {
                    select.innerHTML += `<option value="${fournisseur.id}">${fournisseur.nom}</option>`;
                });
            } catch (error) {
                console.error('Erreur chargement fournisseurs:', error);
                showNotification('Erreur lors du chargement des fournisseurs', 'error');
            }
        }

        // Charger factures clients
        async function loadFacturesClients() {
            try {
                const dateDebut = document.getElementById('filtreFacturesDebut').value;
                const dateFin = document.getElementById('filtreFacturesFin').value;
                
                let url = '../../api/finance.php?action=factures_clients';
                if (dateDebut) url += `&date_debut=${dateDebut}`;
                if (dateFin) url += `&date_fin=${dateFin}`;
                
                const response = await fetch(url);
                const factures = await response.json();
                
                const tableau = document.getElementById('tableauFacturesClients');
                tableau.innerHTML = generateFacturesClientsTable(factures);
                
            } catch (error) {
                console.error('Erreur chargement factures clients:', error);
                document.getElementById('tableauFacturesClients').innerHTML = '<div class="text-center py-8 text-red-500">Erreur lors du chargement des factures</div>';
            }
        }

        // Charger factures fournisseurs
        async function loadFacturesFournisseurs() {
            try {
                const response = await fetch('../../api/finance.php?action=factures_fournisseurs');
                const factures = await response.json();
                
                const tableau = document.getElementById('tableauFacturesFournisseurs');
                tableau.innerHTML = generateFacturesFournisseursTable(factures);
                
            } catch (error) {
                console.error('Erreur chargement factures fournisseurs:', error);
                document.getElementById('tableauFacturesFournisseurs').innerHTML = '<div class="text-center py-8 text-red-500">Erreur lors du chargement des factures</div>';
            }
        }

        // Charger échéances
        async function loadEcheances() {
            try {
                const response = await fetch('../../api/finance.php?action=echeances');
                const echeances = await response.json();
                
                const tableau = document.getElementById('tableauEcheances');
                tableau.innerHTML = generateEcheancesTable(echeances);
                
            } catch (error) {
                console.error('Erreur chargement échéances:', error);
                document.getElementById('tableauEcheances').innerHTML = '<div class="text-center py-8 text-red-500">Erreur lors du chargement des échéances</div>';
            }
        }

        // Générer tableau factures clients
        function generateFacturesClientsTable(factures) {
            if (!factures || factures.length === 0) {
                return '<div class="text-center py-8 text-gray-500">Aucune facture trouvée</div>';
            }

            return `
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant TTC</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${factures.map(facture => `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-900">${facture.numero_facture}</td>
                                <td class="px-4 py-3 text-gray-700">${formatDate(facture.date_facture)}</td>
                                <td class="px-4 py-3 text-gray-700">${facture.nom_client || 'Client sur place'}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">${formatMontant(facture.montant_ttc)}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${getStatutClass(facture.statut)}">
                                        ${getStatutLabel(facture.statut)}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="voirFacture(${facture.id})" class="text-blue-600 hover:text-blue-800 mr-2 p-1 rounded hover:bg-blue-50" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="telechargerFacture(${facture.id})" class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50" title="Télécharger">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Générer tableau factures fournisseurs
        function generateFacturesFournisseursTable(factures) {
            if (!factures || factures.length === 0) {
                return '<div class="text-center py-8 text-gray-500">Aucune facture fournisseur trouvée</div>';
            }

            return `
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Échéance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant TTC</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${factures.map(facture => `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-900">${facture.numero_facture}</td>
                                <td class="px-4 py-3 text-gray-700">${facture.nom_fournisseur}</td>
                                <td class="px-4 py-3 text-gray-700">${formatDate(facture.date_facture)}</td>
                                <td class="px-4 py-3 ${isEchuSoon(facture.date_echeance) ? 'text-red-600 font-medium' : 'text-gray-700'}">${formatDate(facture.date_echeance)}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">${formatMontant(facture.montant_ttc)}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${getStatutClass(facture.statut)}">
                                        ${getStatutLabel(facture.statut)}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="marquerPaye(${facture.id})" class="text-green-600 hover:text-green-800 mr-2 p-1 rounded hover:bg-green-50" title="Marquer comme payé">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="voirFactureFournisseur(${facture.id})" class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Générer tableau échéances
        function generateEcheancesTable(echeances) {
            if (!echeances || echeances.length === 0) {
                return '<div class="text-center py-8 text-gray-500">Aucune échéance trouvée</div>';
            }

            return `
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date échéance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jours restants</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${echeances.map(echeance => `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-900">${echeance.numero_facture}</td>
                                <td class="px-4 py-3 text-gray-700">${echeance.nom_fournisseur}</td>
                                <td class="px-4 py-3 text-gray-700">${formatDate(echeance.date_echeance)}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">${formatMontant(echeance.montant_ttc)}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${getJoursRestantsClass(echeance.jours_restants)}">
                                        ${echeance.jours_restants} jours
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="marquerPaye(${echeance.id})" class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50" title="Marquer comme payé">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                        </tbody>
                </table>
            `;
        }

        // Gestion des lignes de facture
        function ajouterLigneFacture() {
            const tbody = document.getElementById('lignesFacture');
            const newRow = document.createElement('tr');
            newRow.className = 'ligne-facture';
            newRow.innerHTML = `
                <td class="px-3 py-2"><input type="text" placeholder="Désignation" class="w-full px-2 py-1 border border-gray-300 rounded designation focus:ring-1 focus:ring-blue-500 focus:border-blue-500" required></td>
                <td class="px-3 py-2"><input type="number" placeholder="1" class="w-20 px-2 py-1 border border-gray-300 rounded quantite focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                <td class="px-3 py-2"><input type="number" placeholder="0" class="w-24 px-2 py-1 border border-gray-300 rounded prix-ht focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="1" required></td>
                <td class="px-3 py-2"><input type="number" value="18" class="w-16 px-2 py-1 border border-gray-300 rounded tva focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                <td class="px-3 py-2 text-center"><span class="total-ligne font-medium text-gray-700">0 FCFA</span></td>
                <td class="px-3 py-2 text-center"><button type="button" onclick="supprimerLigne(this)" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 transition-colors"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(newRow);
        }

        function supprimerLigne(button) {
            const row = button.closest('tr');
            if (document.querySelectorAll('.ligne-facture').length > 1) {
                row.remove();
                calculerTotaux();
            } else {
                showNotification('Au moins une ligne est requise', 'error');
            }
        }

        function calculerTotaux() {
            let totalHT = 0;
            let totalTVA = 0;

            document.querySelectorAll('.ligne-facture').forEach(row => {
                const quantite = parseFloat(row.querySelector('.quantite').value) || 0;
                const prixHT = parseFloat(row.querySelector('.prix-ht').value) || 0;
                const tauxTVA = parseFloat(row.querySelector('.tva').value) || 0;

                const ligneHT = quantite * prixHT;
                const ligneTVA = ligneHT * (tauxTVA / 100);
                const ligneTTC = ligneHT + ligneTVA;

                row.querySelector('.total-ligne').textContent = formatMontant(ligneTTC);

                totalHT += ligneHT;
                totalTVA += ligneTVA;
            });

            const totalTTC = totalHT + totalTVA;

            document.getElementById('totalHT').textContent = formatMontant(totalHT);
            document.getElementById('totalTVA').textContent = formatMontant(totalTVA);
            document.getElementById('totalTTC').textContent = formatMontant(totalTTC);
        }

        // Enregistrer facture fournisseur
        async function enregistrerFactureFournisseur() {
            try {
                // Validation des champs obligatoires
                const fournisseurId = document.getElementById('fournisseurId').value;
                const numeroFacture = document.getElementById('numeroFactureFournisseur').value;
                const dateFacture = document.getElementById('dateFactureFournisseur').value;
                const dateEcheance = document.getElementById('dateEcheance').value;

                if (!fournisseurId || !numeroFacture || !dateFacture || !dateEcheance) {
                    showNotification('Veuillez remplir tous les champs obligatoires', 'error');
                    return;
                }

                const lignes = [];
                document.querySelectorAll('.ligne-facture').forEach(row => {
                    const designation = row.querySelector('.designation').value.trim();
                    const quantite = parseFloat(row.querySelector('.quantite').value);
                    const prixHT = parseFloat(row.querySelector('.prix-ht').value);
                    const tauxTVA = parseFloat(row.querySelector('.tva').value);
                    
                    if (designation && quantite && prixHT) {
                        lignes.push({
                            designation: designation,
                            quantite: quantite,
                            prix_unitaire_ht: prixHT,
                            taux_tva: tauxTVA
                        });
                    }
                });

                if (lignes.length === 0) {
                    showNotification('Veuillez ajouter au moins une ligne valide à la facture', 'error');
                    return;
                }

                const data = {
                    numero_facture: numeroFacture,
                    fournisseur_id: fournisseurId,
                    date_facture: dateFacture,
                    date_echeance: dateEcheance,
                    lignes: lignes
                };

                showNotification('Enregistrement en cours...', 'info');

                const response = await fetch('../../api/finance.php?action=facture_fournisseur', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Facture fournisseur enregistrée avec succès', 'success');
                    closeModal('modalFactureFournisseur');
                    if (currentTab === 'fournisseurs') {
                        loadFacturesFournisseurs();
                    }
                } else {
                    showNotification('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
                }

            } catch (error) {
                console.error('Erreur enregistrement facture:', error);
                showNotification('Erreur lors de l\'enregistrement de la facture', 'error');
            }
        }

        // Reset formulaire facture
        function resetFormFacture() {
            document.getElementById('formFactureFournisseur').reset();
            
            // Remettre une seule ligne propre
            const tbody = document.getElementById('lignesFacture');
            tbody.innerHTML = `
                <tr class="ligne-facture">
                    <td class="px-3 py-2"><input type="text" placeholder="Désignation" class="w-full px-2 py-1 border border-gray-300 rounded designation focus:ring-1 focus:ring-blue-500 focus:border-blue-500" required></td>
                    <td class="px-3 py-2"><input type="number" placeholder="1" class="w-20 px-2 py-1 border border-gray-300 rounded quantite focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                    <td class="px-3 py-2"><input type="number" placeholder="0" class="w-24 px-2 py-1 border border-gray-300 rounded prix-ht focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="1" required></td>
                    <td class="px-3 py-2"><input type="number" value="18" class="w-16 px-2 py-1 border border-gray-300 rounded tva focus:ring-1 focus:ring-blue-500 focus:border-blue-500" step="0.01" required></td>
                    <td class="px-3 py-2 text-center"><span class="total-ligne font-medium text-gray-700">0 FCFA</span></td>
                    <td class="px-3 py-2 text-center"><button type="button" onclick="supprimerLigne(this)" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 transition-colors"><i class="fas fa-trash"></i></button></td>
                </tr>
            `;
            
            // Remettre les totaux à zéro
            document.getElementById('totalHT').textContent = '0 FCFA';
            document.getElementById('totalTVA').textContent = '0 FCFA';
            document.getElementById('totalTTC').textContent = '0 FCFA';
            
            // Remettre la date d'aujourd'hui
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateFactureFournisseur').value = today;
        }

        // Fonctions utilitaires
        function formatMontant(montant) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(montant || 0) + ' FCFA';
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('fr-FR');
        }

        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function getStatutClass(statut) {
            switch(statut) {
                case 'payee': return 'bg-green-100 text-green-800';
                case 'en_attente': return 'bg-yellow-100 text-yellow-800';
                case 'annulee': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getStatutLabel(statut) {
            switch(statut) {
                case 'payee': return 'Payée';
                case 'en_attente': return 'En attente';
                case 'annulee': return 'Annulée';
                default: return statut;
            }
        }

        function getJoursRestantsClass(jours) {
            if (jours < 0) return 'bg-red-100 text-red-800';
            if (jours <= 7) return 'bg-orange-100 text-orange-800';
            return 'bg-green-100 text-green-800';
        }

        function isEchuSoon(dateEcheance) {
            const echeance = new Date(dateEcheance);
            const today = new Date();
            const diffTime = echeance - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays <= 7;
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

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                const firstInput = modal.querySelector('input, select');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                if (modalId === 'modalFactureFournisseur') {
                    resetFormFacture();
                }
            }
        }

        function filtrerFacturesClients() {
            loadFacturesClients();
        }

        function genererFacturesEnAttente() {
            showNotification('Génération des factures en cours...', 'info');
        }

        function voirFacture(factureId) {
            window.open(`../../api/finance.php?action=pdf_facture&id=${factureId}`, '_blank');
        }

        function telechargerFacture(factureId) {
            window.location.href = `../../api/finance.php?action=download_facture&id=${factureId}`;
        }

        async function marquerPaye(factureId) {
            if (confirm('Marquer cette facture comme payée?')) {
                try {
                    const response = await fetch('../../api/finance.php?action=marquer_paye', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ facture_id: factureId })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('Facture marquée comme payée', 'success');
                        if (currentTab === 'fournisseurs') {
                            loadFacturesFournisseurs();
                        } else if (currentTab === 'echeances') {
                            loadEcheances();
                        }
                    } else {
                        showNotification('Erreur: ' + result.error, 'error');
                    }
                } catch (error) {
                    console.error('Erreur marquage paiement:', error);
                    showNotification('Erreur lors du marquage du paiement', 'error');
                }
            }
        }

        function voirFactureFournisseur(factureId) {
            showNotification('Fonctionnalité de visualisation en développement', 'info');
        }
    </script>

    <style>
        .modal {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            overflow-y: auto;
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
        
        @media (max-width: 768px) {
            .modal-content {
                margin: 1rem;
                max-width: calc(100vw - 2rem);
                border-radius: 0.5rem;
            }
            
            .modal {
                padding: 1rem;
            }
            
            .grid-cols-1.md\\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
            
            .overflow-x-auto table {
                min-width: 600px;
            }
        }
        
        .transition-colors {
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }
        
        table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        button:focus {
            outline: 2px solid #3B82F6;
            outline-offset: 2px;
        }
        
        .ligne-facture input {
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .ligne-facture input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 1px #3B82F6;
        }
    </style>
</body>
</html>