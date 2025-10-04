<?php
require_once '../../config.php';
    session_start();

    // Vérifie l'accès admin
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trésorerie - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    
    <!-- Navigation Finances -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold text-gray-800">Gestion Trésorerie</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="dashboard.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Tableau de bord</a>
                        <a href="facturation.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Facturation</a>
                        <a href="rapports.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Rapports</a>
                        <a href="tresorerie.php" class="nav-btn active bg-blue-600 text-white px-4 py-2 rounded-lg">Trésorerie</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <input type="date" id="dateSelector" class="px-3 py-2 border rounded-lg" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Caisse Quotidienne -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Caisse Quotidienne</h3>
                    <p class="text-sm text-gray-600">Date: <span id="dateCaisse"><?= date('d/m/Y') ?></span></p>
                </div>
                <div class="p-6">
                    <div id="caisseQuotidienne" class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span>Statut:</span>
                            <span id="statutCaisseDetail" class="px-3 py-1 rounded-full text-sm bg-red-100 text-red-800">Fermée</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Fonds d'ouverture:</span>
                            <span id="fondsOuverture" class="font-medium">0,00 €</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Espèces théoriques:</span>
                            <span id="especesTheoriques" class="font-medium">0,00 €</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Espèces réelles:</span>
                            <span id="especesReelles" class="font-medium">0,00 €</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cartes bancaires:</span>
                            <span id="totalCartes" class="font-medium">0,00 €</span>
                        </div>
                        <hr>
                        <div class="flex justify-between font-semibold text-lg">
                            <span>Écart caisse:</span>
                            <span id="ecartCaisseDetail" class="text-red-600">0,00 €</span>
                        </div>
                        <div class="flex justify-between font-semibold text-lg">
                            <span>Total ventes:</span>
                            <span id="totalVentes" class="text-green-600">0,00 €</span>
                        </div>
                        
                        <div class="mt-6 space-y-2">
                            <button onclick="showModal('modalOuvrirCaisse')" id="btnOuvrirCaisse" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-unlock mr-2"></i>Ouvrir la caisse
                            </button>
                            <button onclick="showModal('modalFermerCaisse')" id="btnFermerCaisse" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700" disabled>
                                <i class="fas fa-lock mr-2"></i>Fermer la caisse
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mouvements de Trésorerie -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Mouvements de Trésorerie</h3>
                    <button onclick="showModal('modalMouvementTresorerie')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Nouveau Mouvement
                    </button>
                </div>
                <div class="p-6">
                    <div id="mouvementsTresorerie" class="space-y-3 max-h-96 overflow-y-auto">
                        <!-- Mouvements seront chargés ici -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique Trésorerie -->
        <div class="mt-6 bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Évolution de la Trésorerie (30 jours)</h3>
            </div>
            <div class="p-6">
                <canvas id="chartTresorerie" width="800" height="300"></canvas>
            </div>
        </div>

        <!-- Résumé mensuel -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Entrées du mois</p>
                        <p class="text-2xl font-bold text-green-600" id="entreesMois">0 €</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-arrow-up text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Sorties du mois</p>
                        <p class="text-2xl font-bold text-red-600" id="sortiesMois">0 €</p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full">
                        <i class="fas fa-arrow-down text-red-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Solde net</p>
                        <p class="text-2xl font-bold text-blue-600" id="soldeNet">0 €</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-balance-scale text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ouvrir Caisse -->
    <div id="modalOuvrirCaisse" class="modal fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md mx-auto mt-20">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Ouvrir la Caisse</h3>
                <button onclick="closeModal('modalOuvrirCaisse')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formOuvrirCaisse" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Date d'ouverture</label>
                    <input type="date" id="dateOuverture" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Fonds d'ouverture (€)</label>
                    <input type="number" id="fondsOuvertureInput" class="w-full px-3 py-2 border rounded-lg" step="0.01" value="100.00" required>
                    <p class="text-xs text-gray-500 mt-1">Montant en espèces pour démarrer la journée</p>
                </div>
                <div class="flex justify-end space-x-4 pt-4">
                    <button type="button" onclick="closeModal('modalOuvrirCaisse')" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Ouvrir la caisse
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Fermer Caisse -->
    <div id="modalFermerCaisse" class="modal fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md mx-auto mt-20">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Fermer la Caisse</h3>
                <button onclick="closeModal('modalFermerCaisse')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formFermerCaisse" class="p-6 space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium mb-2">Résumé de la journée</h4>
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <span>Espèces théoriques:</span>
                            <span id="resumeEspecesTheoriques">0,00 €</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cartes bancaires:</span>
                            <span id="resumeCartes">0,00 €</span>
                        </div>
                        <div class="flex justify-between font-medium">
                            <span>Total ventes:</span>
                            <span id="resumeTotalVentes">0,00 €</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Espèces réellement comptées (€)</label>
                    <input type="number" id="especesReellesInput" class="w-full px-3 py-2 border rounded-lg" step="0.01" required>
                    <p class="text-xs text-gray-500 mt-1">Comptez physiquement les espèces en caisse</p>
                </div>
                
                <div id="ecartCalcule" class="hidden bg-yellow-50 border border-yellow-200 p-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                        <span class="font-medium">Écart détecté: <span id="montantEcart"></span></span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Notes (optionnel)</label>
                    <textarea id="notesFermeture" class="w-full px-3 py-2 border rounded-lg" rows="3" placeholder="Remarques sur la journée..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 pt-4">
                    <button type="button" onclick="closeModal('modalFermerCaisse')" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Fermer la caisse
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Mouvement Trésorerie -->
    <div id="modalMouvementTresorerie" class="modal fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto mt-8">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Nouveau Mouvement de Trésorerie</h3>
                <button onclick="closeModal('modalMouvementTresorerie')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formMouvementTresorerie" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Type</label>
                    <select id="typeMouvement" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="entree">Entrée d'argent</option>
                        <option value="sortie">Sortie d'argent</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Catégorie</label>
                    <select id="categorieMouvement" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="vente">Vente</option>
                        <option value="achat">Achat</option>
                        <option value="salaire">Salaire</option>
                        <option value="charge">Charge</option>
                        <option value="taxe">Taxe</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Montant (€)</label>
                    <input type="number" id="montantMouvement" class="w-full px-3 py-2 border rounded-lg" step="0.01" required>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <input type="text" id="descriptionMouvement" class="w-full px-3 py-2 border rounded-lg" placeholder="Description du mouvement" required>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Mode de paiement</label>
                    <select id="modePaiementMouvement" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="especes">Espèces</option>
                        <option value="carte">Carte bancaire</option>
                        <option value="cheque">Chèque</option>
                        <option value="virement">Virement</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Date</label>
                    <input type="date" id="dateMouvement" class="w-full px-3 py-2 border rounded-lg" required>
                </div>

                <div class="flex justify-end space-x-4 pt-4">
                    <button type="button" onclick="closeModal('modalMouvementTresorerie')" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let tresorerieData = {};
        let chartTresorerie = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadTresorerieData();
            
            // Event listeners
            document.getElementById('dateSelector').addEventListener('change', function() {
                loadTresorerieData();
            });

            document.getElementById('formOuvrirCaisse').addEventListener('submit', function(e) {
                e.preventDefault();
                ouvrirCaisse();
            });

            document.getElementById('formFermerCaisse').addEventListener('submit', function(e) {
                e.preventDefault();
                fermerCaisse();
            });

            document.getElementById('formMouvementTresorerie').addEventListener('submit', function(e) {
                e.preventDefault();
                enregistrerMouvementTresorerie();
            });

            // Calculer écart en temps réel
            document.getElementById('especesReellesInput').addEventListener('input', function() {
                calculerEcart();
            });

            // Définir dates par défaut
            document.getElementById('dateOuverture').value = new Date().toISOString().split('T')[0];
            document.getElementById('dateMouvement').value = new Date().toISOString().split('T')[0];
        });

        // Charger données trésorerie
        async function loadTresorerieData() {
            try {
                const date = document.getElementById('dateSelector').value;
                
                // Charger statut caisse
                const caisseResponse = await fetch(`../../api/finance.php?action=caisse&sous_action=status&date=${date}`);
                const caisseData = await caisseResponse.json();
                
                // Charger mouvements
                const mouvementsResponse = await fetch(`../../api/finance.php?action=mouvement_tresorerie&date=${date}`);
                const mouvementsData = await mouvementsResponse.json();
                
                // Charger stats mensuelles
                const statsResponse = await fetch(`../../api/finance.php?action=stats_tresorerie&jours=30&date_fin=${date}`);
                const statsData = await statsResponse.json();

                tresorerieData = {
                    caisse: caisseData,
                    mouvements: mouvementsData,
                    stats: statsData
                };

                updateCaisseStatus();
                updateMouvementsList();
                updateMonthlyStats();
                updateTresorerieChart();

            } catch (error) {
                console.error('Erreur chargement trésorerie:', error);
                showNotification('Erreur lors du chargement des données', 'error');
            }
        }

        // Mettre à jour statut caisse
        function updateCaisseStatus() {
            const caisse = tresorerieData.caisse;
            
            document.getElementById('statutCaisseDetail').textContent = caisse.statut === 'ouverte' ? 'Ouverte' : 'Fermée';
            document.getElementById('statutCaisseDetail').className = `px-3 py-1 rounded-full text-sm ${
                caisse.statut === 'ouverte' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            
            document.getElementById('fondsOuverture').textContent = formatMontant(caisse.fonds_ouverture || 0);
            document.getElementById('especesTheoriques').textContent = formatMontant(caisse.total_especes_theorique || 0);
            document.getElementById('especesReelles').textContent = formatMontant(caisse.total_especes_reel || 0);
            document.getElementById('totalCartes').textContent = formatMontant(caisse.total_cartes || 0);
            document.getElementById('ecartCaisseDetail').textContent = formatMontant(caisse.ecart || 0);
            document.getElementById('totalVentes').textContent = formatMontant(caisse.total_ventes || 0);
            
            // Couleur écart
            const ecart = caisse.ecart || 0;
            document.getElementById('ecartCaisseDetail').className = ecart === 0 ? 'text-green-600' : 'text-red-600';
            
            // État des boutons
            const estOuverte = caisse.statut === 'ouverte';
            document.getElementById('btnOuvrirCaisse').disabled = estOuverte;
            document.getElementById('btnFermerCaisse').disabled = !estOuverte;
            
            if (estOuverte) {
                document.getElementById('btnOuvrirCaisse').className = 'w-full bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed';
                document.getElementById('btnFermerCaisse').className = 'w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700';
            } else {
                document.getElementById('btnOuvrirCaisse').className = 'w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700';
                document.getElementById('btnFermerCaisse').className = 'w-full bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed';
            }
        }

        // Mettre à jour liste mouvements
        function updateMouvementsList() {
            const container = document.getElementById('mouvementsTresorerie');
            const mouvements = tresorerieData.mouvements || [];
            
            if (mouvements.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-gray-500">Aucun mouvement aujourd\'hui</div>';
                return;
            }

            container.innerHTML = mouvements.map(mouvement => `
                <div class="flex justify-between items-center p-3 border rounded-lg">
                    <div class="flex-1">
                        <div class="font-medium">${mouvement.description}</div>
                        <div class="text-sm text-gray-600">
                            ${mouvement.categorie} • ${mouvement.mode_paiement}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium ${mouvement.type === 'entree' ? 'text-green-600' : 'text-red-600'}">
                            ${mouvement.type === 'entree' ? '+' : '-'}${formatMontant(Math.abs(mouvement.montant))}
                        </div>
                        <div class="text-xs text-gray-500">${formatHeure(mouvement.date_creation)}</div>
                    </div>
                </div>
            `).join('');
        }

        // Mettre à jour stats mensuelles
        function updateMonthlyStats() {
            const stats = tresorerieData.stats || [];
            
            let entreesMois = 0;
            let sortiesMois = 0;
            
            stats.forEach(stat => {
                entreesMois += stat.entrees || 0;
                sortiesMois += stat.sorties || 0;
            });
            
            const soldeNet = entreesMois - sortiesMois;
            
            document.getElementById('entreesMois').textContent = formatMontant(entreesMois);
            document.getElementById('sortiesMois').textContent = formatMontant(sortiesMois);
            document.getElementById('soldeNet').textContent = formatMontant(soldeNet);
            
            // Couleur solde
            document.getElementById('soldeNet').className = `text-2xl font-bold ${
                soldeNet >= 0 ? 'text-green-600' : 'text-red-600'
            }`;
        }

        // Graphique trésorerie
        function updateTresorerieChart() {
            const ctx = document.getElementById('chartTresorerie').getContext('2d');
            
            if (chartTresorerie) {
                chartTresorerie.destroy();
            }

            const stats = tresorerieData.stats || [];
            
            chartTresorerie = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: stats.map(s => formatDate(s.date_mouvement)),
                    datasets: [
                        {
                            label: 'Entrées',
                            data: stats.map(s => s.entrees || 0),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: false
                        },
                        {
                            label: 'Sorties',
                            data: stats.map(s => s.sorties || 0),
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: false
                        },
                        {
                            label: 'Solde cumulé',
                            data: stats.map(s => s.solde_cumule || 0),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatMontant(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatMontant(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatMontant(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Ouvrir caisse
        async function ouvrirCaisse() {
            try {
                const data = {
                    date: document.getElementById('dateOuverture').value,
                    fonds_ouverture: parseFloat(document.getElementById('fondsOuvertureInput').value),
                    employe_id: <?= $_SESSION['admin_id'] ?>
                };

                const response = await fetch('../../api/finance.php?action=caisse&sous_action=ouvrir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Caisse ouverte avec succès', 'success');
                    closeModal('modalOuvrirCaisse');
                    loadTresorerieData();
                } else {
                    showNotification('Erreur lors de l\'ouverture de la caisse', 'error');
                }

            } catch (error) {
                console.error('Erreur ouverture caisse:', error);
                showNotification('Erreur lors de l\'ouverture de la caisse', 'error');
            }
        }

        // Fermer caisse
        async function fermerCaisse() {
            try {
                const data = {
                    date: document.getElementById('dateSelector').value,
                    especes_reel: parseFloat(document.getElementById('especesReellesInput').value),
                    employe_id: <?= $_SESSION['admin_id'] ?>,
                    notes: document.getElementById('notesFermeture').value
                };

                const response = await fetch('../../api/finance.php?action=caisse&sous_action=fermer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Caisse fermée avec succès', 'success');
                    closeModal('modalFermerCaisse');
                    loadTresorerieData();
                } else {
                    showNotification('Erreur lors de la fermeture de la caisse', 'error');
                }

            } catch (error) {
                console.error('Erreur fermeture caisse:', error);
                showNotification('Erreur lors de la fermeture de la caisse', 'error');
            }
        }

        // Calculer écart caisse
        function calculerEcart() {
            const especesReelles = parseFloat(document.getElementById('especesReellesInput').value) || 0;
            const especesTheoriques = tresorerieData.caisse?.total_especes_theorique || 0;
            const ecart = especesReelles - especesTheoriques;
            
            const ecartDiv = document.getElementById('ecartCalcule');
            const montantEcart = document.getElementById('montantEcart');
            
            if (Math.abs(ecart) > 0.01) {
                ecartDiv.classList.remove('hidden');
                montantEcart.textContent = formatMontant(ecart);
                
                if (ecart > 0) {
                    ecartDiv.className = 'bg-green-50 border border-green-200 p-3 rounded-lg';
                    montantEcart.className = 'text-green-600 font-bold';
                } else {
                    ecartDiv.className = 'bg-red-50 border border-red-200 p-3 rounded-lg';
                    montantEcart.className = 'text-red-600 font-bold';
                }
            } else {
                ecartDiv.classList.add('hidden');
            }
            
            // Mettre à jour résumé
            document.getElementById('resumeEspecesTheoriques').textContent = formatMontant(especesTheoriques);
            document.getElementById('resumeCartes').textContent = formatMontant(tresorerieData.caisse?.total_cartes || 0);
            document.getElementById('resumeTotalVentes').textContent = formatMontant(tresorerieData.caisse?.total_ventes || 0);
        }

        // Enregistrer mouvement trésorerie
        async function enregistrerMouvementTresorerie() {
            try {
                const data = {
                    type: document.getElementById('typeMouvement').value,
                    categorie: document.getElementById('categorieMouvement').value,
                    montant: parseFloat(document.getElementById('montantMouvement').value),
                    description: document.getElementById('descriptionMouvement').value,
                    mode_paiement: document.getElementById('modePaiementMouvement').value,
                    date_mouvement: document.getElementById('dateMouvement').value
                };

                const response = await fetch('../../api/finance.php?action=mouvement_tresorerie', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Mouvement enregistré avec succès', 'success');
                    closeModal('modalMouvementTresorerie');
                    document.getElementById('formMouvementTresorerie').reset();
                    document.getElementById('dateMouvement').value = new Date().toISOString().split('T')[0];
                    loadTresorerieData();
                } else {
                    showNotification('Erreur lors de l\'enregistrement', 'error');
                }

            } catch (error) {
                console.error('Erreur enregistrement mouvement:', error);
                showNotification('Erreur lors de l\'enregistrement', 'error');
            }
        }

        // Fonctions utilitaires
        function formatMontant(montant) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(montant || 0);
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit'
            });
        }

        function formatHeure(dateTimeStr) {
            return new Date(dateTimeStr).toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'error' ? 'bg-red-600 text-white' :
                type === 'success' ? 'bg-green-600 text-white' :
                'bg-blue-600 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        function showModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            
            // Préremplir données si fermeture caisse
            if (modalId === 'modalFermerCaisse') {
                calculerEcart();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // CSS pour modal responsive
        const style = document.createElement('style');
        style.textContent = `
            .modal {
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding: 2rem;
                overflow-y: auto;
            }
            @media (max-width: 768px) {
                .modal {
                    padding: 1rem;
                }
                .modal-content {
                    max-width: calc(100vw - 2rem);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
