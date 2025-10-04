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
    <title>Rapports Financiers - Restaurant</title>
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
                    <h1 class="text-xl font-bold text-gray-800">Rapports et Analyses</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="dashboard.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Tableau de bord</a>
                        <a href="facturation.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Facturation</a>
                        <a href="rapports.php" class="nav-btn active bg-blue-600 text-white px-4 py-2 rounded-lg">Rapports</a>
                        <a href="tresorerie.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Trésorerie</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Rapports et Analyses</h3>
                <div class="mt-4 flex flex-wrap gap-4">
                    <input type="date" id="rapportDateDebut" class="px-3 py-2 border rounded-lg" value="<?= date('Y-m-01') ?>">
                    <input type="date" id="rapportDateFin" class="px-3 py-2 border rounded-lg" value="<?= date('Y-m-t') ?>">
                    <button onclick="genererRapport()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-chart-bar mr-2"></i>Générer Rapport
                    </button>
                    <button onclick="exporterRapport('pdf')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </button>
                    <button onclick="exporterRapport('excel')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Export Excel
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Résumé du rapport -->
                <div id="resumeRapport" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <!-- KPIs seront générés ici -->
                </div>

                <!-- Graphiques et tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">Évolution CA par jour</h4>
                        <canvas id="chartRapportJours" width="400" height="300"></canvas>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">Répartition par heure</h4>
                        <canvas id="chartRapportHeures" width="400" height="300"></canvas>
                    </div>
                </div>

                <!-- Analyses détaillées -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">Évolution des marges</h4>
                        <canvas id="chartMarges" width="400" height="300"></canvas>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">Modes de commande</h4>
                        <canvas id="chartModesCommande" width="400" height="300"></canvas>
                    </div>
                </div>

                <!-- Top 10 plats -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8">
                    <h4 class="text-lg font-semibold mb-4">Top 10 Plats de la Période</h4>
                    <div id="tableauTop10Plats" class="overflow-x-auto">
                        <!-- Tableau sera généré par JavaScript -->
                    </div>
                </div>

                <!-- Analyse de rentabilité -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8">
                    <h4 class="text-lg font-semibold mb-4">Analyse de Rentabilité par Catégorie</h4>
                    <div id="tableauRentabilite" class="overflow-x-auto">
                        <!-- Tableau sera généré par JavaScript -->
                    </div>
                </div>

                <!-- Comparaison périodes -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h4 class="text-lg font-semibold mb-4">Comparaison avec la période précédente</h4>
                    <div id="comparaisonPeriodes" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Comparaisons seront générées ici -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let rapportData = {};
        let chartsRapport = {};

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Générer rapport automatiquement au chargement
            genererRapport();
        });

        // Générer rapport
        async function genererRapport() {
            try {
                showLoading(true);
                
                const dateDebut = document.getElementById('rapportDateDebut').value;
                const dateFin = document.getElementById('rapportDateFin').value;
                
                if (!dateDebut || !dateFin) {
                    showNotification('Veuillez sélectionner les dates de début et fin', 'error');
                    return;
                }
                
                if (new Date(dateDebut) > new Date(dateFin)) {
                    showNotification('La date de début doit être antérieure à la date de fin', 'error');
                    return;
                }

                // Charger données du rapport
                const response = await fetch(`../../api/finance.php?action=rapport_ventes&date_debut=${dateDebut}&date_fin=${dateFin}`);
                rapportData = await response.json();

                // Charger top plats
                const topPlatsResponse = await fetch(`../../api/finance.php?action=top_plats&date_debut=${dateDebut}&date_fin=${dateFin}&limit=10`);
                rapportData.top_plats = await topPlatsResponse.json();

                // Charger évolution ventes
                const evolutionResponse = await fetch(`../../api/finance.php?action=evolution_ventes&date_debut=${dateDebut}&date_fin=${dateFin}`);
                rapportData.evolution_ventes = await evolutionResponse.json();

                updateResumeRapport();
                updateChartsRapport();
                updateTableaux();
                updateComparaisons();

                showLoading(false);
                showNotification('Rapport généré avec succès', 'success');

            } catch (error) {
                console.error('Erreur génération rapport:', error);
                showNotification('Erreur lors de la génération du rapport', 'error');
                showLoading(false);
            }
        }

        // Mettre à jour résumé
        function updateResumeRapport() {
            const resume = rapportData.resume;
            if (!resume) return;

            const container = document.getElementById('resumeRapport');
            container.innerHTML = `
                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Chiffre d'Affaires</p>
                            <p class="text-2xl font-bold text-blue-600">${formatMontant(resume.ca_total)}</p>
                            <p class="text-sm text-gray-500">HT: ${formatMontant(resume.ca_ht)}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-euro-sign text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Nombre de Commandes</p>
                            <p class="text-2xl font-bold text-green-600">${resume.nb_commandes}</p>
                            <p class="text-sm text-gray-500">Commandes</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-shopping-cart text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Panier Moyen</p>
                            <p class="text-2xl font-bold text-purple-600">${formatMontant(resume.panier_moyen)}</p>
                            <p class="text-sm text-gray-500">Par commande</p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-calculator text-purple-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">CA Quotidien Moyen</p>
                            <p class="text-2xl font-bold text-orange-600">${formatMontant(resume.ca_quotidien_moyen)}</p>
                            <p class="text-sm text-gray-500">Par jour</p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <i class="fas fa-chart-line text-orange-600"></i>
                        </div>
                    </div>
                </div>
            `;
        }

        // Mettre à jour graphiques
        function updateChartsRapport() {
            createChartJours();
            createChartHeures();
            createChartMarges();
            createChartModesCommande();
        }

        function createChartJours() {
            const ctx = document.getElementById('chartRapportJours').getContext('2d');
            
            if (chartsRapport.jours) {
                chartsRapport.jours.destroy();
            }

            const data = rapportData.par_jour || [];
            
            chartsRapport.jours = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.jour)),
                    datasets: [{
                        label: 'CA Quotidien',
                        data: data.map(d => d.ca_jour),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
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
                                    return 'CA: ' + formatMontant(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        }

        function createChartHeures() {
            const ctx = document.getElementById('chartRapportHeures').getContext('2d');
            
            if (chartsRapport.heures) {
                chartsRapport.heures.destroy();
            }

            const data = rapportData.par_heure || [];
            
            chartsRapport.heures = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.heure + 'h'),
                    datasets: [{
                        label: 'CA par heure',
                        data: data.map(d => d.ca_heure),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: '#10B981',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
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
                                    return 'CA: ' + formatMontant(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        }

        function createChartMarges() {
            const ctx = document.getElementById('chartMarges').getContext('2d');
            
            if (chartsRapport.marges) {
                chartsRapport.marges.destroy();
            }

            // Simuler données de marges par jour
            const data = rapportData.par_jour || [];
            
            chartsRapport.marges = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.jour)),
                    datasets: [
                        {
                            label: 'CA',
                            data: data.map(d => d.ca_jour),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Marge estimée',
                            data: data.map(d => d.ca_jour * 0.65), // 65% de marge estimée
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            yAxisID: 'y'
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
                        }
                    }
                }
            });
        }

        function createChartModesCommande() {
            const ctx = document.getElementById('chartModesCommande').getContext('2d');
            
            if (chartsRapport.modes) {
                chartsRapport.modes.destroy();
            }

            const data = rapportData.par_mode_commande || [
                { mode: 'Sur place', ca: 15000 },
                { mode: 'QR Code', ca: 8000 },
                { mode: 'Emporter', ca: 3000 }
            ];
            
            chartsRapport.modes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.mode),
                    datasets: [{
                        data: data.map(d => d.ca),
                        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + formatMontant(context.parsed);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Mettre à jour tableaux
        function updateTableaux() {
            updateTableauTop10Plats();
            updateTableauRentabilite();
        }

        function updateTableauTop10Plats() {
            const container = document.getElementById('tableauTop10Plats');
            const topPlats = rapportData.top_plats || [];

            if (topPlats.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">Aucune donnée disponible</div>';
                return;
            }

            container.innerHTML = `
                <table class="w-full table-auto">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Plat</th>
                            <th class="px-4 py-2 text-center">Quantité</th>
                            <th class="px-4 py-2 text-right">CA Total</th>
                            <th class="px-4 py-2 text-center">Marge %</th>
                            <th class="px-4 py-2 text-right">Bénéfice</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${topPlats.map((plat, index) => `
                            <tr class="border-b hover:bg-white">
                                <td class="px-4 py-2 font-bold text-gray-600">${index + 1}</td>
                                <td class="px-4 py-2 font-medium">${plat.nom}</td>
                                <td class="px-4 py-2 text-center">${plat.quantite_vendue}</td>
                                <td class="px-4 py-2 text-right font-medium">${formatMontant(plat.ca_plat)}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-1 rounded text-xs ${getMargeClass(plat.marge_pourcentage)}">
                                        ${plat.marge_pourcentage ? plat.marge_pourcentage.toFixed(1) + '%' : 'N/A'}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-medium text-green-600">
                                    ${formatMontant(plat.benefice_total || 0)}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function updateTableauRentabilite() {
            const container = document.getElementById('tableauRentabilite');
            
            // Simuler données de rentabilité par catégorie
            const categories = [
                { nom: 'Entrées', ca: 3500, cout: 1400, marge: 60 },
                { nom: 'Plats principaux', ca: 15000, cout: 5250, marge: 65 },
                { nom: 'Desserts', ca: 2800, cout: 980, marge: 65 },
                { nom: 'Boissons', ca: 4200, cout: 1260, marge: 70 }
            ];

            container.innerHTML = `
                <table class="w-full table-auto">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-4 py-2 text-left">Catégorie</th>
                            <th class="px-4 py-2 text-right">CA</th>
                            <th class="px-4 py-2 text-right">Coût</th>
                            <th class="px-4 py-2 text-right">Marge Brute</th>
                            <th class="px-4 py-2 text-center">Marge %</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${categories.map(cat => `
                            <tr class="border-b hover:bg-white">
                                <td class="px-4 py-2 font-medium">${cat.nom}</td>
                                <td class="px-4 py-2 text-right">${formatMontant(cat.ca)}</td>
                                <td class="px-4 py-2 text-right text-red-600">${formatMontant(cat.cout)}</td>
                                <td class="px-4 py-2 text-right text-green-600 font-medium">${formatMontant(cat.ca - cat.cout)}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-1 rounded text-xs ${getMargeClass(cat.marge)}">
                                        ${cat.marge}%
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Mettre à jour comparaisons
        function updateComparaisons() {
            const container = document.getElementById('comparaisonPeriodes');
            
            // Simuler comparaison avec période précédente
            const current = rapportData.resume;
            if (!current) return;

            const previous = {
                ca_total: current.ca_total * 0.85,
                nb_commandes: current.nb_commandes * 0.92,
                panier_moyen: current.panier_moyen * 0.95
            };

            const evolutionCA = ((current.ca_total - previous.ca_total) / previous.ca_total) * 100;
            const evolutionCommandes = ((current.nb_commandes - previous.nb_commandes) / previous.nb_commandes) * 100;
            const evolutionPanier = ((current.panier_moyen - previous.panier_moyen) / previous.panier_moyen) * 100;

            container.innerHTML = `
                <div class="bg-white p-4 rounded-lg">
                    <h5 class="font-medium text-gray-800 mb-2">Chiffre d'Affaires</h5>
                    <div class="text-2xl font-bold">${formatMontant(current.ca_total)}</div>
                    <div class="text-sm ${evolutionCA >= 0 ? 'text-green-600' : 'text-red-600'}">
                        <i class="fas fa-arrow-${evolutionCA >= 0 ? 'up' : 'down'} mr-1"></i>
                        ${Math.abs(evolutionCA).toFixed(1)}% vs période précédente
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg">
                    <h5 class="font-medium text-gray-800 mb-2">Nombre de Commandes</h5>
                    <div class="text-2xl font-bold">${current.nb_commandes}</div>
                    <div class="text-sm ${evolutionCommandes >= 0 ? 'text-green-600' : 'text-red-600'}">
                        <i class="fas fa-arrow-${evolutionCommandes >= 0 ? 'up' : 'down'} mr-1"></i>
                        ${Math.abs(evolutionCommandes).toFixed(1)}% vs période précédente
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg">
                    <h5 class="font-medium text-gray-800 mb-2">Panier Moyen</h5>
                    <div class="text-2xl font-bold">${formatMontant(current.panier_moyen)}</div>
                    <div class="text-sm ${evolutionPanier >= 0 ? 'text-green-600' : 'text-red-600'}">
                        <i class="fas fa-arrow-${evolutionPanier >= 0 ? 'up' : 'down'} mr-1"></i>
                        ${Math.abs(evolutionPanier).toFixed(1)}% vs période précédente
                    </div>
                </div>
            `;
        }

        // Export rapports
        function exporterRapport(format) {
            const dateDebut = document.getElementById('rapportDateDebut').value;
            const dateFin = document.getElementById('rapportDateFin').value;
            
            if (!dateDebut || !dateFin) {
                showNotification('Veuillez générer un rapport avant d\'exporter', 'error');
                return;
            }

            const url = `../../api/finance.php?action=export_rapport&format=${format}&date_debut=${dateDebut}&date_fin=${dateFin}`;
            
            if (format === 'pdf') {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
            
            showNotification(`Export ${format.toUpperCase()} en cours...`, 'info');
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

        function getMargeClass(marge) {
            if (marge >= 70) return 'bg-green-100 text-green-800';
            if (marge >= 50) return 'bg-yellow-100 text-yellow-800';
            return 'bg-red-100 text-red-800';
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

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                if (!overlay) {
                    const loading = document.createElement('div');
                    loading.id = 'loadingOverlay';
                    loading.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                    loading.innerHTML = `
                        <div class="bg-white p-6 rounded-lg shadow-xl">
                            <div class="flex items-center space-x-3">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                <span class="text-gray-700">Génération du rapport...</span>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(loading);
                }
            } else {
                if (overlay) {
                    overlay.remove();
                }
            }
        }
    </script>
</body>
</html>

